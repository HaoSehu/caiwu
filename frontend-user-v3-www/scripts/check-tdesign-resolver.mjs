
import fs from 'node:fs';
import path from 'node:path';

import {
  createTDesignResolver,
  TDESIGN_COMPONENT_DIRS,
  TDESIGN_PLUGIN_DIRS,
} from './tdesign-resolver.mjs';

const root = path.resolve(import.meta.dirname, '..');
const nodeModules = path.resolve(root, '..', 'node_modules');
const resolver = createTDesignResolver();

// 组件标签可能出现在 .vue 模板，也可能出现在 render 函数所在脚本里，
// 所以六种扩展名一起扫（.vue/.js/.mjs/.ts/.tsx/.jsx）；.tsx/.jsx 也纳入，避免 JSX
// 写法完全逃出覆盖（当前仓库这两类文件为 0 个，属预留）。
const TAG_FILE_EXTENSIONS = ['.vue', '.js', '.mjs', '.ts', '.tsx', '.jsx'];

function walk(dir, out = []) {
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    const target = path.join(dir, entry.name);
    if (entry.isDirectory()) walk(target, out);
    else if (TAG_FILE_EXTENSIONS.some((extension) => entry.name.endsWith(extension))) out.push(target);
  }
  return out;
}

// PascalCase 形态的标签里，这些不是 TDesign 组件：
// - Vue 内置组件由框架提供，解析器本来就不该收录；
// - *Icon 来自 tdesign-icons-vue-next（逐图标子包），不在 tdesign-vue-next 的组件表内。
const NON_TDESIGN_TAGS = new Set(['Transition', 'TransitionGroup', 'KeepAlive', 'Teleport', 'Suspense', 'Component', 'Slot', 'Template']);

function isTDesignTag(name) {
  if (!name.startsWith('T') || name.length < 2) return false;
  if (NON_TDESIGN_TAGS.has(name)) return false;
  return !name.endsWith('Icon');
}

// 注释里的示例写法（如本文件与 bootstrap.ts 的说明文字）不是真实使用，先剔除再匹配，
// 否则把脚本纳入扫描范围后会把自己文档里的 <t-xxx> 当成组件。
//
// HTML 注释只在 .vue 的顶层 <template> 段里剔除：整文件直接 replace(/<!--[\s\S]*?-->/g)
// 会被脚本里的 '<!--' 与 '-->' 两个字符串字面量夹住，把中间的真实标签整段吞掉（静默漏报）。
//
// 段的边界必须按 <template> 嵌套深度扫，不能用非贪婪正则：模板里一旦出现
// <template #default> 插槽，非贪婪匹配会在内层 </template> 处就闭合，外层剩余部分
// （含其后的注释）落在匹配区间之外，注释里的组件名就会被当成真实标签误报。
function findTopLevelTemplateRegion(source) {
  // 开始与结束标签都用 \b 收尾而不是写死 `>`：项目里存在 `</template` 换行后再写 `>` 的
  // 折行闭合（products/index.vue、ProductDetail/index.vue 共 3 处），写死 `>` 会让 depth
  // 永远归不了零、整段返回 null，该文件的注释就全部不再剔除。
  const boundary = /<template\b|<\/template\b/g;
  let depth = 0;
  let start = -1;
  let match;
  while ((match = boundary.exec(source)) !== null) {
    if (match[0].charAt(1) === '/') {
      if (depth === 0) continue;
      depth -= 1;
      if (depth === 0 && start !== -1) return [start, match.index + match[0].length];
      continue;
    }
    if (depth === 0) start = match.index;
    depth += 1;
  }
  return null;
}

// 剔除注释时还要避开字符串字面量里的 '<!--'：模板里写 <pre>{{ '<!--' }}</pre> 这类示例时，
// 它和后面的 '-->' 会把中间的真实标签夹成「注释」而漏报。
//
// 实现是启发式而非词法分析：只看 '<!--' 前面最近的非空白字符是不是引号。因此有多处已知边界，
// 当前仓库均为 0 实例，不再进一步处理（改成真正的 JS 词法分析成本远高于收益）：
// - 字符串里含 `<template` 字面量（script 内、模板插值内、正则里）会让段边界失效、整段不剔注释；
// - 引号在句中且其后紧跟注释时（如 他说:"<!-- ... -->"），该注释会被当成字符串内容而不剔除，
//   导致其中的组件名被误报为真实使用。
function stripHtmlCommentsInRegion(source) {
  const region = findTopLevelTemplateRegion(source);
  if (!region) return source;
  const [start, end] = region;
  const body = source.slice(start, end);
  let stripped = '';
  let index = 0;
  while (index < body.length) {
    const open = body.indexOf('<!--', index);
    if (open === -1) {
      stripped += body.slice(index);
      break;
    }
    let back = open - 1;
    while (back >= 0 && /\s/.test(body[back])) back -= 1;
    const previous = back >= 0 ? body[back] : '';
    if (previous === "'" || previous === '"' || previous === '`') {
      stripped += body.slice(index, open + 4);
      index = open + 4;
      continue;
    }
    const close = body.indexOf('-->', open + 4);
    if (close === -1) {
      stripped += body.slice(index, open);
      break;
    }
    stripped += body.slice(index, open);
    index = close + 3;
  }
  return source.slice(0, start) + stripped + source.slice(end);
}

// JS 注释规则只能作用在 <template> 段之外：模板里的 // 是文本（协议相对 URL 更常见）、
// /* 也可能是文案，按注释剔掉会把该行后面的真实标签整条吞掉，造成静默漏报。
function stripJsComments(source) {
  return source
    .replace(/\/\*[\s\S]*?\*\//g, '')
    .replace(/(^|[^:])\/\/[^\n]*/g, '$1');
}

function stripComments(source, extension) {
  if (extension !== '.vue') return stripJsComments(source);
  // 先剔 HTML 注释，再在剔后的串上重算模板段区间：删掉注释会让后续下标整体左移，
  // 拿原文算出的 start/end 去切剔后的串，会让段边界偏右、把段外的 JS 注释错划进段内
  // （表现为 script 注释里提到的组件名被当成真实标签误报）。
  const template = stripHtmlCommentsInRegion(source);
  const region = findTopLevelTemplateRegion(template);
  // 段边界识别失败时（大写 <TEMPLATE>、自闭合 <template />、script 里的 '<template' 字面量等）
  // 一律不剔任何注释：宁可让注释里提到的组件名被误报，也不能把整份 .vue 当 JS 处理——
  // 那样模板里的 // 会被当行注释删到行尾，紧跟其后的真实标签被吞掉，变成静默漏报。
  // 当前仓库 0 实例（34 个 .vue 全是正常小写 <template>），属防御性收口。
  if (!region) return template;
  const [start, end] = region;
  return stripJsComments(template.slice(0, start)) + template.slice(start, end) + stripJsComments(template.slice(end));
}

const tags = new Set();
for (const file of walk(path.join(root, 'src'))) {
  const source = stripComments(fs.readFileSync(file, 'utf8'), path.extname(file));
  // 两种写法都要收：模板里的小写 <t-xxx> 与 PascalCase 的 <Txxx>。
  // 只认小写会让 <TBogusWidget /> 静默渲染成空自定义元素，正是本守卫要防的失效方式。
  for (const match of source.matchAll(/<t-([a-z0-9-]+)/g)) {
    tags.add(
      'T' +
        match[1]
          .split('-')
          .map((part) => part[0].toUpperCase() + part.slice(1))
          .join(''),
    );
  }
  // PascalCase 标签必须满足两个边界条件，否则会把 TypeScript 泛型误判成组件：
  // - 左侧不能是标识符字符（`ref<TProps>()`、`Array<TItem>` 的 `<` 紧跟标识符）；
  // - 右侧必须紧跟空白、`/` 或 `>`（泛型实参常写成 `<TProps,>` 或 `<TProps>(...)`）。
  for (const match of source.matchAll(/(?:^|[^\w$.])<([A-Z][A-Za-z0-9]*)(?=[\s/>])/g)) {
    if (isTDesignTag(match[1])) tags.add(match[1]);
  }
}

const problems = [];
for (const name of [...tags].sort()) {
  const resolved = resolver.resolve(name);
  if (!resolved) {
    problems.push(name + '（TDESIGN_COMPONENT_DIRS 未收录）');
    continue;
  }
  const base = path.join(nodeModules, resolved.from);
  if (!fs.existsSync(base + '.mjs') && !fs.existsSync(path.join(base, 'index.mjs'))) {
    problems.push(name + ' -> ' + resolved.from + '（子入口不存在）');
  }
}

console.log('模板 t- 标签：' + [...tags].sort().join(', '));
console.log(
  '表条目：' + Object.keys(TDESIGN_COMPONENT_DIRS).length + ' 组件 / ' + Object.keys(TDESIGN_PLUGIN_DIRS).length + ' 插件',
);

if (problems.length > 0) {
  console.error('TDesign 解析器覆盖不全：\n- ' + problems.join('\n- '));
  process.exit(1);
}

console.log('TDesign resolver check passed.');
