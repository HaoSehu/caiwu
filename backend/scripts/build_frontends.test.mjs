import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import { randomBytes } from 'node:crypto';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const scriptDirectory = path.dirname(fileURLToPath(import.meta.url));
const repositoryRoot = path.resolve(scriptDirectory, '..', '..');
const buildScript = path.join(scriptDirectory, 'build_frontends.mjs');

function run(args) {
  return spawnSync(process.execPath, [buildScript, ...args], {
    cwd: repositoryRoot,
    encoding: 'utf8',
  });
}

// 默认 dry-run：构建目标为全部三个前端
{
  const result = run(['--dry-run']);
  assert.equal(result.status, 0, result.stderr);
  assert.match(result.stdout, /构建目标: admin, www, console/);
}

// --target 逐个
for (const target of ['admin', 'www', 'console']) {
  const result = run(['--dry-run', '--target', target]);
  assert.equal(result.status, 0, result.stderr);
  assert.match(result.stdout, new RegExp(`构建目标: ${target}`));
}

// 非法 --target 报错
{
  const result = run(['--dry-run', '--target', 'unknown']);
  assert.notEqual(result.status, 0);
  assert.match(result.stderr, /--target 只能是 all、admin、www 或 console/);
}

// 不支持的参数报错（如已废弃的 --env）
{
  const result = run(['--dry-run', '--env', 'production']);
  assert.notEqual(result.status, 0);
  assert.match(result.stderr, /不支持的参数/);
}

// 体积门禁：预算表存在，admin 单桶阈值与 vite.config.ts 注释口径一致，
// console 单桶阈值是以实测基线放宽 15% 的防膨胀哨兵（审查条目 P2-1）。
{
  const budgetModule = await import('./frontend_budget.mjs');
  assert.equal(typeof budgetModule.checkFrontendBudget, 'function', '体积门禁需导出 checkFrontendBudget');
  assert.equal(budgetModule.CHUNK_BUDGETS.admin, 950 * 1000, 'admin 单分片预算应对齐 vite.config.ts 注释的 950KB');
  assert.equal(budgetModule.CHUNK_BUDGETS.console, 1120 * 1000, 'console 单分片预算应是 972,583 B 基线放宽 15% 后的哨兵值');
  assert.ok(
    budgetModule.CHUNK_BUDGETS.console > 972583,
    'console 单分片预算必须高于当前实测 972,583 B，否则门禁会在健康构建上直接误报',
  );
  assert.deepEqual(Object.keys(budgetModule.FIRST_SCREEN_BUDGETS).sort(), ['admin', 'console', 'www']);
  // CSS 分片预算：三端都要有，且必须高于当前实测最大单文件，否则健康构建会误报（审查条目 P3-4）。
  assert.deepEqual(Object.keys(budgetModule.CSS_CHUNK_BUDGETS).sort(), ['admin', 'console', 'www']);
  const cssBaselines = { admin: 302911, console: 264090, www: 56866 };
  Object.entries(cssBaselines).forEach(([name, baseline]) => {
    assert.ok(
      budgetModule.CSS_CHUNK_BUDGETS[name] > baseline,
      `${name} 的 CSS 分片预算必须高于当前实测 ${baseline} B，否则门禁会在健康构建上误报`,
    );
  });
  // 预算必须低于优化前基线 605,762 B，否则「优化收益被整体回退」不会被这道闸拦住（审查条目 P2-D）。
  assert.ok(
    budgetModule.FIRST_SCREEN_BUDGETS.admin < 605762,
    'admin 首屏预算必须低于优化前基线 605,762 B，才能真正守住按需引入的收益',
  );
}

// 体积门禁必须真的会拦：用足以超限的假产物验证「超限抛错」与「缺 name 抛错」。
// 没有这两条，P1-A 那类「传参缺失导致静默放行」在测试层永远发现不了（审查条目 P2-E）。
{
  const budgetModule = await import('./frontend_budget.mjs');
  const sandboxRoot = fs.mkdtempSync(path.join(os.tmpdir(), 'caiwu-budget-'));
  const distDirectory = path.join(sandboxRoot, 'frontend-admin-v3', 'dist');
  const assetsDirectory = path.join(distDirectory, 'assets', 'js');
  fs.mkdirSync(assetsDirectory, { recursive: true });

  // 真随机字节：gzip 无法压缩，700,000 B 必然突破 admin 的 560,000 B 预算。
  // 注意不能用取模等伪随机序列——那类序列有明显周期，gzip 会压到几 KB，用例会假绿。
  const body = randomBytes(700 * 1000);
  fs.writeFileSync(path.join(assetsDirectory, 'oversized.js'), body);
  fs.writeFileSync(
    path.join(distDirectory, 'index.html'),
    '<!doctype html><html><head><script type="module" src="/assets/js/oversized.js"></script></head></html>',
  );

  assert.throws(
    () => budgetModule.checkFrontendBudget({ name: 'admin', directory: 'frontend-admin-v3' }, sandboxRoot),
    /首屏 gzip .* 超出预算/,
    '首屏超限时必须抛错，不能静默放行',
  );

  assert.throws(
    () => budgetModule.checkFrontendBudget({ directory: 'frontend-admin-v3' }, sandboxRoot),
    /未收到应用标识 name/,
    '缺少 name 时必须显式失败，避免预算查表落空后静默放行',
  );

  fs.rmSync(sandboxRoot, { recursive: true, force: true });

  // CSS 分片同样要被拦住：只查 assets/js 的实现会漏掉样式回退（审查条目 P3-4）。
  const cssSandboxRoot = fs.mkdtempSync(path.join(os.tmpdir(), 'caiwu-budget-css-'));
  const cssDist = path.join(cssSandboxRoot, 'frontend-admin-v3', 'dist');
  const cssDirectory = path.join(cssDist, 'assets', 'css');
  fs.mkdirSync(cssDirectory, { recursive: true });
  fs.writeFileSync(path.join(cssDirectory, 'oversized.css'), randomBytes(400 * 1000));
  fs.writeFileSync(path.join(cssDist, 'index.html'), '<!doctype html><html><head></head><body></body></html>');
  assert.throws(
    () => budgetModule.checkFrontendBudget({ name: 'admin', directory: 'frontend-admin-v3' }, cssSandboxRoot),
    /样式分片 oversized\.css .* 超出预算/,
    'CSS 分片超限时必须抛错，否则样式回退能被首屏汇总吸收',
  );
  fs.rmSync(cssSandboxRoot, { recursive: true, force: true });
}

// TDesign 按需引入的覆盖自检（审查条目 P3-5）。
// 两端用自定义 resolver 把模板里的 <t-xxx> 解析到 tdesign-vue-next/es/<dir>/index，
// 目录表漏项不会报错，只会让该组件静默不注册、运行时渲染成未知元素。
// 这里做静态差集：模板出现的每个 t-* 标签都必须能从表里推导出来。
{
  const applications = ['frontend-admin-v3', 'frontend-user-v4-console'];

  // 导出名 → 模板标签名的推导规则，与 resolver 的 name.slice(1) 反向对应
  const toTagName = (exportName) =>
    `t-${exportName
      .replace(/([a-z0-9])([A-Z])/g, '$1-$2')
      .replace(/([A-Z])([A-Z][a-z])/g, '$1-$2')
      .toLowerCase()}`;

  const readResolverKeys = (configFile) => {
    const source = fs.readFileSync(configFile, 'utf8');
    const start = source.indexOf('const TDESIGN_COMPONENT_DIRS');
    const end = source.indexOf('const TDESIGN_PLUGIN_DIRS', start);
    assert.ok(start >= 0 && end > start, `${configFile} 找不到 TDESIGN_COMPONENT_DIRS 表`);
    return new Set([...source.slice(start, end).matchAll(/^\s{2}([A-Za-z0-9]+):/gm)].map((match) => match[1]));
  };

  const collectVueFiles = (directory, files = []) => {
    for (const entry of fs.readdirSync(directory, { withFileTypes: true })) {
      const target = path.join(directory, entry.name);
      if (entry.isDirectory()) {
        collectVueFiles(target, files);
      } else if (entry.name.endsWith('.vue')) {
        files.push(target);
      }
    }
    return files;
  };

  for (const application of applications) {
    const applicationRoot = path.join(repositoryRoot, application);
    const knownTags = new Set([...readResolverKeys(path.join(applicationRoot, 'vite.config.ts'))].map(toTagName));
    const usedTags = new Set();
    for (const vueFile of collectVueFiles(path.join(applicationRoot, 'src'))) {
      const source = fs.readFileSync(vueFile, 'utf8');
      for (const match of source.matchAll(/<t-([a-z0-9-]+)/g)) {
        usedTags.add(`t-${match[1]}`);
      }
    }
    const missing = [...usedTags].filter((tag) => !knownTags.has(tag)).sort();
    assert.deepEqual(missing, [], `${application} 模板使用了 TDESIGN_COMPONENT_DIRS 未收录的组件：${missing.join(', ')}`);
  }
}

console.log('frontend build configuration tests passed');
