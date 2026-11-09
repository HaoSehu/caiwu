// demo 兜底守卫：production 产物中不得出现「演练版全量权限」等 demo 专用兜底。
//
// 背景（审查条目 M-4）：管理端在 --mode demo 下有两处兜底——
//   1. store/modules/user.ts：auth/info 权限为空时兜底 [AdminPermissions.ALL]；
//   2. utils/request/index.ts：demo adapter 为 auth/info 提供含全量权限的静态响应。
// 两者都包在 import.meta.env.MODE === 'demo' 里，production 构建会被 Vite 静态求值剔除。
// 但这是构建器的行为，不是代码的保证：一旦有人把条件改错（例如改用运行时判断、
// 或把 demo 数据集 import 成静态依赖），兜底会静默进入生产包，任何管理员会话都能拿到全量权限。
// 所以构建后再扫一遍产物，出现 demo 标记串即失败。
//
// 用法：node ./scripts/check-demo-fallbacks.mjs [--dist <目录>]
//   默认扫 dist（production 产物）；build:demo 输出 dist-demo，不经过本检查。

import fs from 'node:fs';
import path from 'node:path';

const root = path.resolve(import.meta.dirname, '..');

const distIndex = process.argv.indexOf('--dist');
const distDirectory =
  distIndex !== -1 && process.argv[distIndex + 1]
    ? path.resolve(root, process.argv[distIndex + 1])
    : path.join(root, 'dist');

// 只收录「只会由 demo 分支产出」的串：演示身份、demo 假 token。
// 不要收录「演示」「demo」这类常见词，否则正常文案会误报。
const DEMO_MARKERS = [
  { marker: 'demo-admin@example.test', label: 'demo 兜底管理员邮箱' },
  { marker: 'demo-admin-token', label: 'demo 假登录 token' },
  { marker: '演示管理员', label: 'demo 兜底管理员昵称' },
];

const SCAN_EXTENSIONS = new Set(['.js', '.mjs', '.html']);

const problems = [];

function scanFile(file) {
  const source = fs.readFileSync(file, 'utf8');
  for (const { marker, label } of DEMO_MARKERS) {
    if (source.includes(marker)) {
      problems.push(`${path.relative(root, file)} ${label} → ${marker}`);
    }
  }
}

function walk(directory) {
  for (const entry of fs.readdirSync(directory, { withFileTypes: true })) {
    const next = path.join(directory, entry.name);
    if (entry.isDirectory()) {
      // 预压缩副本（.gz/.br）与 sourcemap 是同一份内容的其它形态，不参与
      walk(next);
      continue;
    }
    if (SCAN_EXTENSIONS.has(path.extname(entry.name).toLowerCase())) scanFile(next);
  }
}

if (!fs.existsSync(distDirectory)) {
  console.error(`demo 兜底守卫：未找到构建产物目录 ${distDirectory}，请先构建 production 产物。`);
  process.exit(1);
}

walk(distDirectory);

if (problems.length > 0) {
  console.error(
    `${path.relative(root, distDirectory)} 含 demo 专用兜底，说明该产物被误当作 production 发布时会让所有会话拿到全量权限。\n- ${problems.join('\n- ')}`,
  );
  process.exit(1);
}

console.log(`demo 兜底守卫通过：${path.relative(root, distDirectory)} 不含 demo 专用兜底。`);
