// 本应用单独构建时的体积门禁。
//
// 背景：`backend/scripts/frontend_budget.mjs` 的 checkFrontendBudget 只在
// `backend/scripts/build_frontends.mjs` 里被调用，也就是只有根目录的
// `npm run verify:frontends` / `npm run build:user-v3-www` 会走门禁。
// 开发者在 frontend-user-v3-www 目录里直接跑 `npm run build` 时完全不校验，
// 首屏与单分片膨胀要等到别人跑全量验证才发现。
// 这里把同一个函数挂到本目录的 build 末尾，保证两条路径口径一致（同一份预算表、同一套算法）。
import path from 'node:path';
import { fileURLToPath } from 'node:url';

import { checkFrontendBudget } from '../../backend/scripts/frontend_budget.mjs';

const scriptDirectory = path.dirname(fileURLToPath(import.meta.url));
const applicationDirectory = path.resolve(scriptDirectory, '..');
const repositoryRoot = path.resolve(applicationDirectory, '..');

try {
  const { firstScreenBytes, firstScreenBudget, chunkBudget, cssChunkBudget } = checkFrontendBudget(
    { name: 'www', directory: 'frontend-user-v3-www' },
    repositoryRoot,
  );

  console.log(
    `www 首屏 gzip ${firstScreenBytes} B，预算 ${firstScreenBudget} B，通过` +
      `（单分片 ${chunkBudget ? `${chunkBudget} B` : '未设'} / 样式分片 ${cssChunkBudget ? `${cssChunkBudget} B` : '未设'}）。`,
  );
} catch (error) {
  console.error(error instanceof Error ? error.message : String(error));
  process.exit(1);
}
