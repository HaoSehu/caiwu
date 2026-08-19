import { existsSync, readFileSync, readdirSync, statSync } from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const scriptDir = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(scriptDir, "../..");
const docsRoot = path.join(root, "docs");
const flags = new Set(process.argv.slice(2));
const knownFlags = new Set(["--freshness", "--strict-freshness"]);
const checkFreshness =
  flags.has("--freshness") || flags.has("--strict-freshness");
const strictFreshness = flags.has("--strict-freshness");

const errors = [];
const freshnessFindings = [];

const catalogStatuses = new Set([
  "current",
  "needs-review",
  "active",
  "进行中",
  "completed",
  "tech-debt",
  "generated",
  "archived",
  "template",
]);

// 计划 frontmatter 允许写中文 `进行中`，catalog.json 统一登记为机器状态 `active`
const statusAliases = new Map([["进行中", "active"]]);

function normaliseStatus(status) {
  return statusAliases.get(status) ?? status;
}

const requiredGeneratedCatalogPaths = new Set([
  "DATABASE.md",
  "自动生成/接口/后端API清单.md",
]);
const statusesRequiringReviewBy = new Set(["current", "active"]);
// 同一天到期的复核记录超过该数量即视为“复核悬崖”：到期会一次性爆发而被整体忽略
const reviewByClusterLimit = 6;
const requiredDirectories = [
  "设计文档",
  "产品规格",
  "执行计划",
  "执行计划/进行中",
  "执行计划/已完成",
  "执行计划/技术债",
  "参考资料",
  "自动生成",
];
const requiredFiles = [
  "README.md",
  "设计文档/index.md",
  "产品规格/README.md",
  "执行计划/README.md",
  "参考资料/README.md",
  "自动生成/README.md",
  "catalog.json",
];

// 本地专用目录（短期文档）：被 .gitignore 忽略，不参与文档校验与 catalog 登记
const localOnlyDirectories = new Set(["_local"]);
// 模板目录内的 frontmatter 是给新文档抄的示例，不描述模板自身状态，故不参与元数据校验
const frontMatterExemptTopLevel = new Set(["模板"]);
// 这些旧式头部标签已由 frontmatter 承载，正文里不得再出现，否则会形成第二个元数据来源
const reservedHeaderLabels = ["文档性质", "对齐时间", "状态", "日期", "更新时间"];

for (const flag of flags) {
  if (!knownFlags.has(flag)) {
    errors.push(`unknown option: ${flag}`);
  }
}

function toRepoPath(filePath) {
  return path.relative(root, filePath).split(path.sep).join("/");
}

function toDocsPath(filePath) {
  return path.relative(docsRoot, filePath).split(path.sep).join("/");
}

function isWithin(basePath, candidatePath) {
  const relative = path.relative(basePath, candidatePath);

  return (
    relative === "" ||
    (!relative.startsWith(`..${path.sep}`) &&
      relative !== ".." &&
      !path.isAbsolute(relative))
  );
}

function isFile(filePath) {
  try {
    return statSync(filePath).isFile();
  } catch {
    return false;
  }
}

function isDirectory(filePath) {
  try {
    return statSync(filePath).isDirectory();
  } catch {
    return false;
  }
}

function walkMarkdown(directoryPath) {
  if (!isDirectory(directoryPath)) {
    return [];
  }

  const files = [];
  const entries = readdirSync(directoryPath, { withFileTypes: true }).sort(
    (left, right) => left.name.localeCompare(right.name),
  );

  for (const entry of entries) {
    const entryPath = path.join(directoryPath, entry.name);
    if (entry.isDirectory()) {
      if (localOnlyDirectories.has(entry.name)) {
        continue;
      }

      files.push(...walkMarkdown(entryPath));
    } else if (entry.isFile() && /\.md$/i.test(entry.name)) {
      files.push(entryPath);
    }
  }

  return files;
}

function isIndexMarkdown(filePath) {
  return /^(readme|index)\.md$/i.test(path.basename(filePath));
}

function maskCharacters(value) {
  return value.replace(/[^\r\n]/g, " ");
}

function maskNonProse(markdown) {
  let masked = markdown.replace(/<!--[\s\S]*?-->/g, maskCharacters);
  const lines = masked.split(/(?<=\n)/);
  let fence = null;

  masked = lines
    .map((line) => {
      const fenceMatch = line.match(/^\s*(`{3,}|~{3,})/);
      if (fenceMatch) {
        const marker = fenceMatch[1];
        if (!fence) {
          fence = marker[0];
        } else if (fence === marker[0]) {
          fence = null;
        }

        return maskCharacters(line);
      }

      if (fence) {
        return maskCharacters(line);
      }

      return line;
    })
    .join("");

  return masked.replace(/`[^`\r\n]*`/g, maskCharacters);
}

function lineNumberAt(markdown, index) {
  let line = 1;
  for (let position = 0; position < index; position += 1) {
    if (markdown[position] === "\n") {
      line += 1;
    }
  }

  return line;
}

function parseMarkdownDestination(value) {
  const trimmed = value.trim();
  if (!trimmed) {
    return "";
  }

  if (trimmed.startsWith("<")) {
    const closingIndex = trimmed.indexOf(">");
    return closingIndex === -1
      ? trimmed
      : trimmed.slice(1, closingIndex).trim();
  }

  let destination = "";
  let escaped = false;
  for (const character of trimmed) {
    if (escaped) {
      destination += character;
      escaped = false;
      continue;
    }

    if (character === "\\") {
      escaped = true;
      continue;
    }

    if (/\s/.test(character)) {
      break;
    }

    destination += character;
  }

  return destination;
}

function normaliseReferenceLabel(label) {
  return label.trim().replace(/\s+/g, " ").toLocaleLowerCase();
}

function isExternalDestination(destination) {
  return (
    destination.startsWith("//") ||
    /^[a-z][a-z\d+.-]*:/i.test(destination) ||
    destination.startsWith("#") ||
    destination.startsWith("?")
  );
}

function decodeLinkPath(value) {
  try {
    return decodeURIComponent(value);
  } catch {
    return value;
  }
}

function validateLocalLink(sourceFile, destination, lineNumber) {
  if (!destination || isExternalDestination(destination)) {
    return;
  }

  const targetWithoutFragment = destination.split(/[?#]/, 1)[0];
  if (!targetWithoutFragment) {
    return;
  }

  const decodedTarget = decodeLinkPath(targetWithoutFragment).replace(
    /\\/g,
    "/",
  );
  let targetPath;
  if (decodedTarget.startsWith("/docs/")) {
    targetPath = path.resolve(root, decodedTarget.slice(1));
  } else if (decodedTarget.startsWith("/")) {
    return;
  } else if (
    path.win32.isAbsolute(decodedTarget) ||
    path.posix.isAbsolute(decodedTarget)
  ) {
    errors.push(
      `${toRepoPath(sourceFile)}:${lineNumber} local link must not use an absolute filesystem path: ${destination}`,
    );
    return;
  } else if (decodedTarget.startsWith("docs/")) {
    targetPath = path.resolve(root, decodedTarget);
  } else {
    targetPath = path.resolve(path.dirname(sourceFile), decodedTarget);
  }

  if (!isWithin(root, targetPath)) {
    errors.push(
      `${toRepoPath(sourceFile)}:${lineNumber} local link escapes the repository: ${destination}`,
    );
    return;
  }

  if (!existsSync(targetPath)) {
    errors.push(
      `${toRepoPath(sourceFile)}:${lineNumber} broken local link: ${destination}`,
    );
    return;
  }

  if (isDirectory(targetPath)) {
    errors.push(
      `${toRepoPath(sourceFile)}:${lineNumber} local link points to a directory: ${destination}`,
    );
  }
}

function checkMarkdownLinks(markdownFiles) {
  for (const markdownFile of markdownFiles) {
    const source = readFileSync(markdownFile, "utf8");
    const prose = maskNonProse(source);
    const definitions = new Map();
    const definitionPattern = /^\s{0,3}\[([^\]\r\n]+)]:\s*(.+?)\s*$/gm;
    let match;

    while ((match = definitionPattern.exec(prose))) {
      const label = normaliseReferenceLabel(match[1]);
      const destination = parseMarkdownDestination(match[2]);
      definitions.set(label, destination);
      validateLocalLink(
        markdownFile,
        destination,
        lineNumberAt(source, match.index),
      );
    }

    const inlinePattern = /!?\[[^\]\r\n]*]\(([^\r\n)]*)\)/g;
    while ((match = inlinePattern.exec(prose))) {
      const destination = parseMarkdownDestination(match[1]);
      validateLocalLink(
        markdownFile,
        destination,
        lineNumberAt(source, match.index),
      );
    }

    const referencePattern = /!?\[([^\]\r\n]+)]\[([^\]\r\n]*)]/g;
    while ((match = referencePattern.exec(prose))) {
      const label = normaliseReferenceLabel(match[2] || match[1]);
      const lineNumber = lineNumberAt(source, match.index);
      if (!definitions.has(label)) {
        errors.push(
          `${toRepoPath(markdownFile)}:${lineNumber} undefined reference link: ${match[0]}`,
        );
        continue;
      }

      validateLocalLink(markdownFile, definitions.get(label), lineNumber);
    }
  }
}

function parseYamlScalar(value) {
  const trimmed = value.trim();
  if (!trimmed) {
    return "";
  }

  if (
    (trimmed.startsWith('"') && trimmed.endsWith('"')) ||
    (trimmed.startsWith("'") && trimmed.endsWith("'"))
  ) {
    return trimmed.slice(1, -1).trim();
  }

  return trimmed.replace(/\s+#.*$/, "").trim();
}

function parseFrontMatter(markdown) {
  const source = markdown.replace(/^\uFEFF/, "");
  const lines = source.split(/\r?\n/);
  if (lines[0]?.trim() !== "---") {
    return null;
  }

  const properties = {};
  for (let index = 1; index < lines.length; index += 1) {
    const line = lines[index];
    if (line.trim() === "---" || line.trim() === "...") {
      return properties;
    }

    const property = line.match(/^([A-Za-z][A-Za-z\d_-]*):(?:\s*(.*))?$/);
    if (property) {
      properties[property[1]] = parseYamlScalar(property[2] ?? "");
    }
  }

  return null;
}

function isValidDate(value) {
  if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) {
    return false;
  }

  const date = new Date(`${value}T00:00:00.000Z`);
  return (
    !Number.isNaN(date.getTime()) && date.toISOString().slice(0, 10) === value
  );
}

function checkExecPlanRecords() {
  for (const folder of ["进行中", "已完成", "技术债"]) {
    const directoryPath = path.join(docsRoot, "执行计划", folder);
    for (const markdownFile of walkMarkdown(directoryPath)) {
      if (isIndexMarkdown(markdownFile)) {
        continue;
      }

      const source = readFileSync(markdownFile, "utf8");
      const displayPath = toRepoPath(markdownFile);
      const prose = maskNonProse(source);
      for (const heading of ["进度", "决策日志"]) {
        const headingPattern = new RegExp(`^##\\s+${heading}\\s*#*\\s*$`, "m");
        if (!headingPattern.test(prose)) {
          errors.push(
            `${displayPath} is missing required heading: ## ${heading}`,
          );
        }
      }
    }
  }
}

// 所有正文文档（索引与模板除外）必须用统一的 YAML frontmatter 声明元数据，
// 并且 status 必须与 catalog.json 登记一致，避免出现第二个状态真源。
function checkDocumentFrontMatter(markdownFiles, catalogStatusByPath) {
  for (const markdownFile of markdownFiles) {
    if (isIndexMarkdown(markdownFile)) {
      continue;
    }

    const docsPath = toDocsPath(markdownFile);
    if (frontMatterExemptTopLevel.has(docsPath.split("/")[0])) {
      continue;
    }

    const displayPath = toRepoPath(markdownFile);
    const frontMatter = parseFrontMatter(readFileSync(markdownFile, "utf8"));
    if (!frontMatter) {
      errors.push(`${displayPath} must start with YAML front matter`);
      continue;
    }

    for (const property of ["status", "updated", "owner"]) {
      if (!frontMatter[property]) {
        errors.push(`${displayPath} front matter is missing ${property}`);
      }
    }

    if (frontMatter.updated && !isValidDate(frontMatter.updated)) {
      errors.push(`${displayPath} updated must use YYYY-MM-DD`);
    }

    if (!frontMatter.status) {
      continue;
    }

    if (!catalogStatuses.has(frontMatter.status)) {
      errors.push(
        `${displayPath} has unsupported status: ${frontMatter.status}`,
      );
      continue;
    }

    const catalogStatus = catalogStatusByPath.get(docsPath);
    if (
      catalogStatus &&
      normaliseStatus(frontMatter.status) !== normaliseStatus(catalogStatus)
    ) {
      errors.push(
        `${displayPath} front matter status ${frontMatter.status} disagrees with docs/catalog.json status ${catalogStatus}`,
      );
    }
  }
}

// 正文不得用旧式头部条目重复声明元数据，否则 frontmatter 就不再是唯一来源
function checkReservedHeaderLabels(markdownFiles) {
  const labelPattern = new RegExp(
    `^\\s{0,3}(?:[-*+]|>)\\s*(${reservedHeaderLabels.join("|")})\\s*[:：]`,
  );

  for (const markdownFile of markdownFiles) {
    if (isIndexMarkdown(markdownFile)) {
      continue;
    }

    const docsPath = toDocsPath(markdownFile);
    if (frontMatterExemptTopLevel.has(docsPath.split("/")[0])) {
      continue;
    }

    const source = readFileSync(markdownFile, "utf8");
    const proseLines = maskNonProse(source).split(/\r?\n/);
    for (const [offset, line] of proseLines.entries()) {
      const match = line.match(labelPattern);
      if (match) {
        errors.push(
          `${toRepoPath(markdownFile)}:${offset + 1} uses retired header label “${match[1]}”; declare it in YAML front matter instead`,
        );
      }
    }
  }
}

// 索引表里的状态列是 catalog.json 的人工副本，逐行比对防止漂移
function checkIndexStatusCells(markdownFiles, catalogStatusByPath) {
  for (const markdownFile of markdownFiles) {
    if (!isIndexMarkdown(markdownFile)) {
      continue;
    }

    const source = readFileSync(markdownFile, "utf8");
    const prose = maskNonProse(source);
    const lines = source.split(/\r?\n/);
    const proseLines = prose.split(/\r?\n/);

    for (const [offset, proseLine] of proseLines.entries()) {
      if (!proseLine.trim().startsWith("|")) {
        continue;
      }

      const cells = proseLine
        .trim()
        .replace(/^\|/, "")
        .replace(/\|$/, "")
        .split("|");
      const statuses = cells
        .map((cell) => cell.trim())
        .filter((cell) => catalogStatuses.has(cell));
      if (statuses.length !== 1) {
        continue;
      }

      // 链接目标从原始行取，maskNonProse 会把行内代码抹成空格
      const targets = [];
      const inlinePattern = /!?\[[^\]\r\n]*]\(([^\r\n)]*)\)/g;
      let match;
      while ((match = inlinePattern.exec(lines[offset] ?? ""))) {
        const destination = parseMarkdownDestination(match[1]);
        if (!destination || isExternalDestination(destination)) {
          continue;
        }

        const decoded = decodeLinkPath(destination.split(/[?#]/, 1)[0]).replace(
          /\\/g,
          "/",
        );
        if (!decoded) {
          continue;
        }

        const resolved = decoded.startsWith("docs/")
          ? path.resolve(root, decoded)
          : path.resolve(path.dirname(markdownFile), decoded);
        if (!isWithin(docsRoot, resolved)) {
          continue;
        }

        const targetDocsPath = toDocsPath(resolved);
        if (catalogStatusByPath.has(targetDocsPath)) {
          targets.push(targetDocsPath);
        }
      }

      const uniqueTargets = [...new Set(targets)];
      if (uniqueTargets.length !== 1) {
        continue;
      }

      const catalogStatus = catalogStatusByPath.get(uniqueTargets[0]);
      if (normaliseStatus(statuses[0]) !== normaliseStatus(catalogStatus)) {
        errors.push(
          `${toRepoPath(markdownFile)}:${offset + 1} index status ${statuses[0]} disagrees with docs/catalog.json status ${catalogStatus} for ${uniqueTargets[0]}`,
        );
      }
    }
  }
}

function resolveCatalogPath(recordPath, recordLabel) {
  if (typeof recordPath !== "string" || !recordPath.trim()) {
    errors.push(`${recordLabel} path must be a non-empty string`);
    return null;
  }

  const normalized = recordPath.trim().replace(/\\/g, "/");
  if (normalized.startsWith("/") || path.win32.isAbsolute(normalized)) {
    errors.push(`${recordLabel} path must be relative to docs/: ${recordPath}`);
    return null;
  }

  const resolved = path.resolve(docsRoot, normalized);
  if (!isWithin(docsRoot, resolved)) {
    errors.push(`${recordLabel} path escapes docs/: ${recordPath}`);
    return null;
  }

  return resolved;
}

function checkCatalog(markdownFiles) {
  const catalogPath = path.join(docsRoot, "catalog.json");
  if (!isFile(catalogPath)) {
    return [];
  }

  let catalog;
  try {
    catalog = JSON.parse(readFileSync(catalogPath, "utf8"));
  } catch (error) {
    errors.push(`docs/catalog.json is not valid JSON: ${error.message}`);
    return [];
  }

  if (!catalog || typeof catalog !== "object" || Array.isArray(catalog)) {
    errors.push("docs/catalog.json must be an object");
    return [];
  }

  if (catalog.version !== 1) {
    errors.push("docs/catalog.json version must be 1");
  }

  if (!Array.isArray(catalog.records)) {
    errors.push("docs/catalog.json records must be an array");
    return [];
  }

  const catalogPaths = new Set();
  const validRecords = [];
  for (const [index, record] of catalog.records.entries()) {
    const recordLabel = `docs/catalog.json records[${index}]`;
    if (!record || typeof record !== "object" || Array.isArray(record)) {
      errors.push(`${recordLabel} must be an object`);
      continue;
    }

    const targetPath = resolveCatalogPath(record.path, recordLabel);
    if (targetPath) {
      const normalizedPath = toDocsPath(targetPath);
      if (catalogPaths.has(normalizedPath)) {
        errors.push(
          `${recordLabel} duplicates catalog path: ${normalizedPath}`,
        );
      }
      catalogPaths.add(normalizedPath);

      if (!/\.md$/i.test(targetPath)) {
        errors.push(
          `${recordLabel} path must point to a Markdown record: ${record.path}`,
        );
      } else if (!isFile(targetPath)) {
        errors.push(`${recordLabel} points to a missing file: ${record.path}`);
      }
    }

    if (
      typeof record.status !== "string" ||
      !catalogStatuses.has(record.status)
    ) {
      errors.push(
        `${recordLabel} has unsupported status: ${record.status ?? "(missing)"}`,
      );
    }

    if (typeof record.summary !== "string" || !record.summary.trim()) {
      errors.push(`${recordLabel} summary must be a non-empty string`);
    }

    if (
      targetPath &&
      typeof record.status === "string" &&
      catalogStatuses.has(record.status)
    ) {
      validRecords.push({ index, path: toDocsPath(targetPath), record });
    }
  }

  for (const markdownFile of markdownFiles) {
    if (isIndexMarkdown(markdownFile)) {
      continue;
    }

    const relativePath = toDocsPath(markdownFile);
    if (!catalogPaths.has(relativePath)) {
      errors.push(`docs/catalog.json is missing a record for ${relativePath}`);
    }
  }

  for (const generatedPath of requiredGeneratedCatalogPaths) {
    const record = validRecords.find(({ path: recordPath }) => recordPath === generatedPath);
    if (!record) {
      errors.push(`docs/catalog.json is missing generated record for ${generatedPath}`);
      continue;
    }

    if (record.record.status !== "generated") {
      errors.push(
        `docs/catalog.json record for ${generatedPath} must use status generated`,
      );
    }
  }

  return validRecords;
}

function checkCatalogFreshness(records) {
  const today = new Date().toISOString().slice(0, 10);
  const reviewByCounts = new Map();
  for (const { index, path: recordPath, record } of records) {
    const label = `docs/catalog.json records[${index}] (${recordPath})`;
    const reviewBy = record.review_by;
    if (typeof reviewBy !== "string" || !reviewBy.trim()) {
      if (statusesRequiringReviewBy.has(normaliseStatus(record.status))) {
        freshnessFindings.push(`${label} is missing required review_by`);
      }
      continue;
    }

    if (!isValidDate(reviewBy)) {
      freshnessFindings.push(`${label} review_by must use YYYY-MM-DD`);
      continue;
    }

    if (reviewBy < today) {
      freshnessFindings.push(`${label} review_by expired on ${reviewBy}`);
    }

    reviewByCounts.set(reviewBy, (reviewByCounts.get(reviewBy) ?? 0) + 1);
  }

  for (const [reviewBy, count] of [...reviewByCounts].sort()) {
    if (count > reviewByClusterLimit) {
      freshnessFindings.push(
        `docs/catalog.json clusters ${count} records on review_by ${reviewBy} (max ${reviewByClusterLimit}); stagger the dates so reviews do not all fall due at once`,
      );
    }
  }
}

if (!isDirectory(docsRoot)) {
  errors.push("required docs/ directory is missing");
} else {
  for (const directory of requiredDirectories) {
    if (!isDirectory(path.join(docsRoot, directory))) {
      errors.push(`required docs directory is missing: docs/${directory}`);
    }
  }

  for (const file of requiredFiles) {
    if (!isFile(path.join(docsRoot, file))) {
      errors.push(`required docs file is missing: docs/${file}`);
    }
  }

  const markdownFiles = walkMarkdown(docsRoot);
  const navigationFiles = [path.join(root, "AGENTS.md"), ...markdownFiles]
    .filter(isFile);
  checkMarkdownLinks(navigationFiles);
  const catalogRecords = checkCatalog(markdownFiles);
  const catalogStatusByPath = new Map(
    catalogRecords.map(({ path: recordPath, record }) => [
      recordPath,
      record.status,
    ]),
  );
  checkDocumentFrontMatter(markdownFiles, catalogStatusByPath);
  checkReservedHeaderLabels(markdownFiles);
  checkIndexStatusCells(markdownFiles, catalogStatusByPath);
  checkExecPlanRecords();
  if (checkFreshness) {
    checkCatalogFreshness(catalogRecords);
  }
}

if (freshnessFindings.length > 0) {
  console.log("Documentation freshness findings:");
  for (const finding of [...new Set(freshnessFindings)].sort()) {
    console.log(`  - ${finding}`);
  }
  console.log("");
}

if (errors.length > 0 || (strictFreshness && freshnessFindings.length > 0)) {
  console.log("Documentation check failed:");
  for (const error of [...new Set(errors)].sort()) {
    console.log(`  - ${error}`);
  }
  if (strictFreshness && freshnessFindings.length > 0) {
    console.log(
      "  - freshness findings fail because --strict-freshness is enabled",
    );
  }
  process.exit(1);
}

if (freshnessFindings.length > 0) {
  console.log(
    "Documentation check passed with non-blocking freshness findings.",
  );
} else {
  console.log("Documentation check passed.");
}
