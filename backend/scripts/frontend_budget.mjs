import { gzipSync } from 'node:zlib';
import fs from 'node:fs';
import path from 'node:path';

// 首屏体积预算（gzip 字节）。以三端实测首屏为基线，只留出吸收正常波动的余量：
// admin 523,820 B → 560,000 B（+6.9%）、console 368,801 B → 420,000 B（+13.9%）。
// admin 的 560,000 已低于优化前基线 605,762 B，因此「优化收益被整体回退」会立刻撞红，
// 不再只是拦截大幅膨胀（审查条目 P2-D）。
//
// www 的 145,000 是「闭包口径」下的基线，与旧口径不可直接比对：
// 旧口径只累加 index.html 直引的 4 个资源（实测 102,182 B），漏掉了路由级懒加载的
// layout 与首页 chunk；而 www 是唯一用 lazyRouteView 的应用，这两个 chunk 属于首屏
// 必需内容。改为按 vite manifest 求静态闭包后实测为 134,631 B（入口 102,182
// + 布局增量 15,421 + 首页路由增量 17,028），故基线取 134,631 → 145,000（+7.7%）。
// 明细见 FIRST_SCREEN_MANIFEST_ENTRIES 的注释。
export const FIRST_SCREEN_BUDGETS = {
  admin: 560 * 1000,
  www: 145 * 1000,
  console: 420 * 1000,
};

// 单分片 raw 预算。admin 的 950KB 有仓库内依据：frontend-admin-v3/vite.config.ts:249-253 注释写明
// hydun CDN 回源不带 Accept-Encoding，identity 全量超过约 1MiB 会被节点截断，故 vendor-tdesign 单桶必须留在 950KB 内。
// console 没有同一 CDN 约束（frontend-user-v4-console/vite.config.ts 无相关注释），这里的 1,120KB 只是防膨胀哨兵：
// 以 2026-10-23 实测 vendor-tdesign 972,583 B 为基线放宽 15%，越线说明桶又膨胀了，需要人工确认（审查条目 P2-1）。
// www 过去没有这一条，导致 chunkChecks 被 .filter((check) => check.budget) 静默过滤掉，
// 单分片膨胀只能在首屏汇总里吸收。基线取 2026 年修复后实测最大单文件 vendor-vue 153,833 B，放宽 17% 到 180,000 B：
// 该桶内是 vue/vue-router/pinia/@vue 运行时，随框架小版本升级缓慢增长，正常不会逼近；越线通常意味着
// manualChunks 误把业务代码并进 vendor（历史上整体分桶 element-plus 曾把首屏从约 106KB 顶到约 400KB）。
export const CHUNK_BUDGETS = {
  admin: 950 * 1000,
  console: 1120 * 1000,
  www: 180 * 1000,
};

// 单分片 raw 预算（CSS）。JS 预算只能兜住「JS 膨胀」，样式一旦回退成全量引入，
// 增量会落在 CSS 分片上：admin 的 vendor-tdesign 从 302,911 B 回退到 462,351 B 只多 159,440 B raw /
// 16,316 B gzip，而首屏预算余量还有 36,161 B —— 首屏汇总拦不住这种组合，必须单独设限。
// 基线取实测最大单文件：admin 302,911 B、console 264,090 B、www 54,902 B（原注释沿用旧产物
// 的 56,866 B，已按当前 dist 实测量更正），统一放宽约 15%。
// www 用 Element Plus 且未做按需样式拆分，基线低但同属防回退哨兵。
export const CSS_CHUNK_BUDGETS = {
  admin: 350 * 1000,
  console: 305 * 1000,
  www: 70 * 1000,
};

// 首屏「真实闭包」入口（vite manifest 的 key）。只有登记在本表的应用才走闭包口径，
// 其余应用继续用 index.html 直引资源，口径与历史完全一致（不改变它们的预算含义）。
//
// 为什么只有 www 需要：admin/console 的 index.html 已把 vendor 全量静态引入，直引口径
// 与闭包口径等价（实测 admin 13 个文件 504,319 B、console 4 个文件 204,975 B，完全相同）；
// 而 www 用 lazyRouteView 懒加载，index.html 只直引入口 chunk，布局 chunk 与首页路由
// chunk 都是入口 chunk 里的动态 import，直引口径根本看不到它们。
// 「入口 102,182 B 通过 115,000 B 预算」曾经被当成首屏达标证据，实际首屏另有 32,449 B
// 串行下载 —— 这就是本条要堵的漏洞。
export const FIRST_SCREEN_MANIFEST_ENTRIES = {
  www: ['index.html', 'src/layout/WebsiteLayout.vue', 'src/pages/website/home/index.vue'],
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

// 从 vite manifest 求「首屏必需资源」的静态闭包。
// manifest 的 key 是模块源码路径（入口 chunks 的 key 形如 "index.html"），value 里
// file 是产物文件、css 是该 chunk 引入的样式、imports 是静态依赖的 chunk key。
// 只沿 imports 展开（静态依赖），不碰 dynamicImports —— 那才是「首屏没下载」的部分。
function collectManifestClosure(manifest, entryKeys) {
  const missingKeys = entryKeys.filter((key) => !manifest[key]);
  if (missingKeys.length > 0) {
    throw new Error(
      `vite manifest 缺少首屏入口 ${missingKeys.join(', ')}：路由或入口改名后必须同步` +
        ' FIRST_SCREEN_MANIFEST_ENTRIES，否则首屏门禁会漏算该 chunk',
    );
  }

  const assets = [];
  const visitedAssets = new Set();
  const visitedChunks = new Set();

  const addAsset = (file) => {
    if (!file || visitedAssets.has(file)) return;
    visitedAssets.add(file);
    assets.push(file);
  };

  const visit = (key) => {
    if (visitedChunks.has(key)) return;
    const chunk = manifest[key];
    if (!chunk) return;
    visitedChunks.add(key);
    addAsset(chunk.file);
    (chunk.css || []).forEach(addAsset);
    (chunk.imports || []).forEach(visit);
  };

  entryKeys.forEach(visit);

  return assets;
}

// 首屏资源清单：登记了 manifest 入口的应用走闭包口径，其余继续用 index.html 直引。
function resolveFirstScreenAssets(application, distDirectory, indexPath) {
  const entryKeys = FIRST_SCREEN_MANIFEST_ENTRIES[application.name];
  if (!entryKeys) {
    return { assets: collectEntryAssets(fs.readFileSync(indexPath, 'utf8')), closure: false };
  }

  const manifestPath = path.join(distDirectory, '.vite', 'manifest.json');
  if (!fs.existsSync(manifestPath)) {
    throw new Error(
      `${application.name} 的首屏门禁需要 vite manifest，但未找到 ${manifestPath}：` +
        '请确认 vite.config.js 的 build.manifest 已开启',
    );
  }

  const manifest = JSON.parse(fs.readFileSync(manifestPath, 'utf8'));

  return { assets: collectManifestClosure(manifest, entryKeys), closure: true };
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

  const { assets: entryAssets, closure } = resolveFirstScreenAssets(application, distDirectory, indexPath);
  const firstScreenBytes = entryAssets.reduce((total, asset) => {
    const assetPath = path.join(distDirectory, asset);
    return fs.existsSync(assetPath) ? total + measureGzip(assetPath) : total;
  }, 0);
  const firstScreenBudget = FIRST_SCREEN_BUDGETS[application.name];

  const firstScreenLabel = closure ? '首屏静态闭包 gzip' : '首屏 gzip';

  if (firstScreenBudget && firstScreenBytes > firstScreenBudget) {
    failures.push(
      `${application.name} ${firstScreenLabel} ${formatBytes(firstScreenBytes)} 超出预算 ${formatBytes(firstScreenBudget)}` +
        (closure ? `（闭包含 ${entryAssets.length} 个资源）` : ''),
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

  return { firstScreenBytes, firstScreenBudget, chunkBudget, cssChunkBudget, entryAssets, closure };
}
