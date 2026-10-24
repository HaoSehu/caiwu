import { gzipSync } from 'node:zlib';
import fs from 'node:fs';
import path from 'node:path';

// 首屏体积预算（gzip 字节）。以 2026-10-23 优化后三端实测首屏为基线，只留出吸收正常波动的余量：
// admin 523,820 B → 560,000 B（+6.9%）、www 106,505 B → 115,000 B（+8.0%）、
// console 368,801 B → 420,000 B（+13.9%）。
// admin 的 560,000 已低于优化前基线 605,762 B，因此「优化收益被整体回退」会立刻撞红，
// 不再只是拦截大幅膨胀（审查条目 P2-D）。
export const FIRST_SCREEN_BUDGETS = {
  admin: 560 * 1000,
  www: 115 * 1000,
  console: 420 * 1000,
};

// 单分片 raw 预算。admin 的 950KB 有仓库内依据：frontend-admin-v3/vite.config.ts:249-253 注释写明
// hydun CDN 回源不带 Accept-Encoding，identity 全量超过约 1MiB 会被节点截断，故 vendor-tdesign 单桶必须留在 950KB 内。
// console 没有同一 CDN 约束（frontend-user-v4-console/vite.config.ts 无相关注释），这里的 1,120KB 只是防膨胀哨兵：
// 以 2026-10-23 实测 vendor-tdesign 972,583 B 为基线放宽 15%，越线说明桶又膨胀了，需要人工确认（审查条目 P2-1）。
export const CHUNK_BUDGETS = {
  admin: 950 * 1000,
  console: 1120 * 1000,
};

// 单分片 raw 预算（CSS）。JS 预算只能兜住「JS 膨胀」，样式一旦回退成全量引入，
// 增量会落在 CSS 分片上：admin 的 vendor-tdesign 从 302,911 B 回退到 462,351 B 只多 159,440 B raw /
// 16,316 B gzip，而首屏预算余量还有 36,161 B —— 首屏汇总拦不住这种组合，必须单独设限。
// 基线取 2026-10-24 实测最大单文件：admin 302,911 B、console 264,090 B、www 56,866 B，统一放宽约 15%。
// www 用 Element Plus 且未做按需样式拆分，基线低但同属防回退哨兵。
export const CSS_CHUNK_BUDGETS = {
  admin: 350 * 1000,
  console: 305 * 1000,
  www: 70 * 1000,
};

const INDEX_ASSET_PATTERN = /(?:src|href)="([^"]*\.(?:js|css))"/g;

function collectEntryAssets(html) {
  const assets = new Set();
  let match = INDEX_ASSET_PATTERN.exec(html);

  while (match) {
    const reference = match[1];
    if (reference && !/^https?:\/\//i.test(reference)) {
      assets.add(reference.replace(/^\//, ''));
    }
    match = INDEX_ASSET_PATTERN.exec(html);
  }

  INDEX_ASSET_PATTERN.lastIndex = 0;

  return Array.from(assets);
}

function measureGzip(filePath) {
  return gzipSync(fs.readFileSync(filePath), { level: 9 }).length;
}

function formatBytes(value) {
  return `${value} B（${(value / 1000).toFixed(1)} kB）`;
}

/**
 * 校验单个前端的产物体积，超限时抛出错误。
 * @param {{ name: string, directory: string }} application
 * @param {string} repositoryRoot
 */
export function checkFrontendBudget(application, repositoryRoot) {
  // 预算按 name 查表，缺 name 会让两道校验同时落空并静默放行（审查条目 P1-A）。
  // 这里显式失败，杜绝「调用方漏传字段 → 门禁形同虚设」再次发生。
  if (!application?.name) {
    throw new Error('体积门禁未收到应用标识 name：预算按 name 查表，缺失会被静默跳过');
  }
  if (
    !(application.name in FIRST_SCREEN_BUDGETS) &&
    !(application.name in CHUNK_BUDGETS) &&
    !(application.name in CSS_CHUNK_BUDGETS)
  ) {
    throw new Error(`体积门禁未配置 ${application.name} 的预算`);
  }

  const distDirectory = path.join(repositoryRoot, application.directory, 'dist');
  const indexPath = path.join(distDirectory, 'index.html');
  const failures = [];

  if (!fs.existsSync(indexPath)) {
    throw new Error(`未找到 ${application.name} 的构建产物：${indexPath}`);
  }

  const entryAssets = collectEntryAssets(fs.readFileSync(indexPath, 'utf8'));
  const firstScreenBytes = entryAssets.reduce((total, asset) => {
    const assetPath = path.join(distDirectory, asset);
    return fs.existsSync(assetPath) ? total + measureGzip(assetPath) : total;
  }, 0);
  const firstScreenBudget = FIRST_SCREEN_BUDGETS[application.name];

  if (firstScreenBudget && firstScreenBytes > firstScreenBudget) {
    failures.push(
      `${application.name} 首屏 gzip ${formatBytes(firstScreenBytes)} 超出预算 ${formatBytes(firstScreenBudget)}`,
    );
  }

  // 单分片体积：JS 与 CSS 分别设限。CSS 需要独立上限的原因是样式回退的增量小到能被首屏汇总吸收，
  // 见文件头 CSS_CHUNK_BUDGETS 的说明。
  const chunkChecks = [
    { budget: CHUNK_BUDGETS[application.name], directory: 'js', extension: '.js', label: '单分片' },
    { budget: CSS_CHUNK_BUDGETS[application.name], directory: 'css', extension: '.css', label: '样式分片' },
  ].filter((check) => check.budget);

  chunkChecks.forEach((check) => {
    const assetsDirectory = path.join(distDirectory, 'assets', check.directory);
    if (!fs.existsSync(assetsDirectory)) return;
    fs.readdirSync(assetsDirectory)
      .filter((file) => file.endsWith(check.extension))
      .map((file) => ({ file, bytes: fs.statSync(path.join(assetsDirectory, file)).size }))
      .filter((asset) => asset.bytes > check.budget)
      .forEach((asset) => {
        failures.push(
          `${application.name} ${check.label} ${asset.file} ${formatBytes(asset.bytes)} 超出预算 ${formatBytes(check.budget)}`,
        );
      });
  });

  const chunkBudget = CHUNK_BUDGETS[application.name];
  const cssChunkBudget = CSS_CHUNK_BUDGETS[application.name];

  if (failures.length > 0) {
    throw new Error(`前端体积门禁未通过：\n- ${failures.join('\n- ')}`);
  }

  return { firstScreenBytes, firstScreenBudget, chunkBudget, cssChunkBudget, entryAssets };
}
