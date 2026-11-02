// 旧组件体系（Element Plus）残留守卫。
//
// 迁移到 TDesign Vue Next 之后，残留可能从五个方向回流：package.json 重新声明旧依赖、
// 源码重新写 <el-xxx> 标签或 --el-* 变量、构建配置重新指向已删的主题入口、
// 有人把旧解析器脚本加回来，以及通过 node_modules 间接路径把旧样式打进产物。
// 前几类都不会让构建失败（未注册的自定义元素只是渲染成空标签），
// 所以必须显式拦截，否则 verify:refactor 全绿也不代表没有回退。
//
// 扫描范围：默认模式扫 src/、scripts/、e2e/、构建配置文件，以及应用根目录第一层的散落文件
// （根目录那一层仍按 SCAN_EXTENSIONS 收窄，.md/.txt 不扫）；docs/ 整体跳过（历史存档与迁移
// 计划里保留旧组件体系原文，属预期记录）。
//
// 有意从严：**不剔除注释**，代码注释里写 element-plus / .el-button / <el-button> 同样会失败。
// 守卫的价值是「宁可误报也不漏报」，所以注释里提到旧组件体系时请改写措辞（例如写成
// 「旧组件体系」而不写包名），不要为了绕开守卫去改规则。
//
// 两种模式：
//   默认      → 扫源码、脚本、e2e 与构建配置（verify:refactor 的前置检查）
//   --dist    → 扫构建产物 dist 里的 .html/.css/.js（verify:refactor 在 build 之后调用）
//               .gz/.br 是同一份文件的预压缩副本，跳过以免重复解压。

import fs from 'node:fs';
import path from 'node:path';

const root = path.resolve(import.meta.dirname, '..');
const scanDist = process.argv.includes('--dist');

// 这些是旧组件体系的标识串。注意 '\.el-' 只匹配类名形态，避免误伤 ellipsis、elementFromPoint 等普通标识符。
const FORBIDDEN_PATTERNS = [
  // 这两条都要求两侧不是标识符字符：'my-element-plus-free-app'、'x@element-plus-y' 这类
  // 普通字符串不算引用，而 '"element-plus"'、'element-plus/es'、'@element-plus/icons-vue'
  // 仍然命中。@ 那条前面额外排除 '@'，避免与上一条对同一处重复报告。
  { pattern: /(?<![\w@-])element-plus(?![\w-])/, label: 'element-plus 包引用' },
  { pattern: /(?<![\w@-])@element-plus(?![\w-])/, label: '@element-plus 作用域包引用' },
  { pattern: /theme-chalk/, label: 'element 主题样式入口' },
  { pattern: /<El[A-Z]/, label: 'PascalCase 旧组件标签 <ElXxx>' },
  { pattern: /<\/?el-[a-z]/, label: '<el-xxx> 模板标签' },
  // 裸 el-* 标识串：Vue 里更常见的回流写法是 h('el-button')、class="el-button"、
  // 或模板里手写未闭合的小写标签。前置的 . - 与单词字符被排除，所以 --el-* 与 .el-*
  // 仍由下面两条独立规则报告，不会重复命中同一处。
  // 前置再排除 < 与 /：<el-button> 已由上一条 <el-xxx> 规则报出，不排除会对同一处出两条。
  { pattern: /(?<![.\w-</])el-[a-z]/, label: '裸 el-* 标识串（标签或类名字符串）' },
  { pattern: /--el-[a-z]/, label: '--el-* CSS 变量' },
  { pattern: /\.el-[a-z]/, label: '.el-* 类选择器' },
  // 旧命令式 API 名后必须断开：'ElMessageBoxLike' 只是名字相近，不是旧 API。
  { pattern: /(?<![\w$])El(?:Message|MessageBox|Loading|Notification)(?![\w$])/, label: '旧命令式 API' },
];

const SKIP_DIRS = new Set(['node_modules', 'dist', 'dist-e2e', 'test-results', 'docs']);
// .tsx/.jsx 一起扫：JSX 里的 <el-button /> 同样会静默渲染成空元素，
// 按扩展名收窄会整类逃出覆盖（当前仓库尚无这两类文件，属防御性收口）。
const SCAN_EXTENSIONS = new Set(['.js', '.mjs', '.ts', '.tsx', '.jsx', '.vue', '.scss', '.css', '.json', '.html']);
// 产物模式扫执行型产物与构建元数据：.json 里最关键的是 .vite/manifest.json，
// 它的 key 直接写模块来源路径（如 ../../node_modules/<包>/es/xxx/index.mjs），
// 是旧组件体系经间接依赖回流时最早的暴露点，不收进来等于留一个盲区。
// .svg 也扫：public/ 下的 svg 会原样进产物，内联 <style> 里可能带类名。
// 图片、字体、.map 与 .gz/.br 预压缩副本不参与（map 因 sourcemap:false 当前不存在）。
const DIST_EXTENSIONS = new Set(['.html', '.css', '.js', '.json', '.svg']);
// 本文件自身的规则表必然包含这些串，跳过。
const SKIP_FILES = new Set([path.normalize(path.join(root, 'scripts/check-no-legacy-ui.mjs'))]);

const problems = [];

function scanFile(file) {
  if (SKIP_FILES.has(path.normalize(file))) return;
  const source = fs.readFileSync(file, 'utf8');
  const lines = source.split(/\r?\n/);
  for (const { pattern, label } of FORBIDDEN_PATTERNS) {
    lines.forEach((line, index) => {
      if (pattern.test(line)) {
        problems.push(path.relative(root, file) + ':' + (index + 1) + ' ' + label + ' → ' + line.trim().slice(0, 120));
      }
    });
  }
}

function walk(dir, extensions, skipDirs) {
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    if (entry.isDirectory()) {
      if (!skipDirs.has(entry.name)) walk(path.join(dir, entry.name), extensions, skipDirs);
      continue;
    }
    if (extensions.has(path.extname(entry.name))) scanFile(path.join(dir, entry.name));
  }
}

if (scanDist) {
  // 产物模式：只校验构建结果，不碰源码。dist 不存在（未构建或已被清理）时明确跳过，
  // 因为这条检查的价值是「构建后产物是否干净」，不是「必须存在产物」。
  const distDir = path.join(root, 'dist');
  if (!fs.existsSync(distDir)) {
    // CI 里这条检查紧跟构建步骤，dist 必然存在；缺产物说明流程被打乱，
    // 此时静默通过会给出「产物干净」的假绿，必须显式失败。
    // 必须严格比字符串 'true'：process.env.CI 是字符串，写成 if (process.env.CI) 时
    // CI=false / CI=0 这类"明确声明不在 CI"的取值也会被判为真，本地跑就误报失败。
    // GitHub Actions 注入的是 CI=true，所以 CI 场景行为不变。
    if (process.env.CI === 'true') {
      console.error('构建产物残留检查失败：CI 环境下未找到 dist，无法证明产物干净。');
      process.exit(1);
    }
    console.log('No Element Plus residue (dist): 未找到 dist，跳过产物层检查。');
    process.exit(0);
  }
  // dist 里只有 assets/ 与预渲染 HTML 需要看，但统一全树遍历更稳（防止有人改 outDir 结构）。
  walk(distDir, DIST_EXTENSIONS, new Set());

  if (problems.length > 0) {
    console.error('构建产物中检测到旧组件体系残留：\n- ' + problems.join('\n- '));
    process.exit(1);
  }
  console.log('No Element Plus residue (dist): 构建产物的 html/css/js/json/svg 无旧组件体系引用。');
  process.exit(0);
}

// 1. 依赖层：不允许旧 UI 库，且 TDesign 两包必须在场。
const pkg = JSON.parse(fs.readFileSync(path.join(root, 'package.json'), 'utf8'));
const allDeps = { ...pkg.dependencies, ...pkg.devDependencies };
for (const name of Object.keys(allDeps)) {
  if (name === 'element-plus' || name === '@element-plus/icons-vue') {
    problems.push('package.json 依赖不得包含 ' + name);
  }
}
for (const name of ['tdesign-vue-next', 'tdesign-icons-vue-next']) {
  if (!allDeps[name]) problems.push('package.json 缺少依赖 ' + name);
}

// 2. 文本层：源码、脚本、e2e、构建配置、index.html。
for (const dir of ['src', 'scripts', 'e2e']) {
  const full = path.join(root, dir);
  if (fs.existsSync(full)) walk(full, SCAN_EXTENSIONS, SKIP_DIRS);
}
for (const file of ['vite.config.js', 'postcss.config.js', 'index.html', 'package.json']) {
  const full = path.join(root, file);
  if (fs.existsSync(full)) scanFile(full);
}
// 3. 应用根目录的散落文件：unplugin 时代的 components.d.ts / auto-imports.d.ts 这类生成物
//    不在 src/scripts/e2e 之下，只扫那三个目录会漏掉它们（历史上 components.d.ts 就曾带
//    16 处旧组件体系声明留在根目录）。这里只扫根目录第一层，不递归。
const EXPLICIT_ROOT_FILES = new Set(['vite.config.js', 'postcss.config.js', 'index.html', 'package.json']);
for (const entry of fs.readdirSync(root, { withFileTypes: true })) {
  if (!entry.isFile()) continue;
  // 上面已逐个扫过的四个配置文件名在这里跳过，否则同一行会报两遍（噪音，易让人以为有两处残留）。
  if (EXPLICIT_ROOT_FILES.has(entry.name)) continue;
  // 无扩展名的文件也要扫：Windows 上曾有把 components.d.ts 复制成 NUL（无扩展名）的事件，
  // 按扩展名过滤会漏掉它。有扩展名的仍按 SCAN_EXTENSIONS 收窄，避免扫图片字体。
  const extension = path.extname(entry.name);
  if (extension && !SCAN_EXTENSIONS.has(extension)) continue;
  // 点开头的文件（.env 等）存的是环境配置不是源码，跳过，避免把凭据读进内存。
  if (entry.name.startsWith('.')) continue;
  scanFile(path.join(root, entry.name));
}

if (problems.length > 0) {
  console.error('检测到旧组件体系残留：\n- ' + problems.join('\n- '));
  process.exit(1);
}

console.log('No Element Plus residue: ' + Object.keys(allDeps).length + ' 个依赖 / src、scripts、e2e 与构建配置均无旧组件体系引用。');
