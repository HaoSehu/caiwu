// Element Plus 按需引入的「组件子路径」解析器。
//
// 背景：unplugin-vue-components 的 ElementPlusResolver 无论组件是什么，都生成
// from: "element-plus/es"（302 行的全量 barrel，见 node_modules/element-plus/es/index.mjs）。
// 该 barrel 第 294 行裸 import dayjs，而 dayjs 的 package.json 没有 sideEffects 字段，
// Rollup 无法把它摇掉，于是 dayjs 变成副作用边，被每个自动注册过 Element Plus 组件的
// chunk 静态引入（实测 16 个 chunk 都带 import "./dayjs.min-*.js"，纯为 dayjs 付出
// 7,084 B raw / 3,064 B gzip）。业务代码从未 import dayjs，删业务 import 无从下手。
//
// 这里包一层 resolver：保留它算好的组件名与样式副作用（sideEffects 指向
// element-plus/es/components/{base,<dir>}/style/index，保持不变），只把 from 改写成
// 组件自己的子路径 index.mjs，从源头切断对 barrel 的依赖边。
// 少数组件没有独立目录（ElOption / ElOptionGroup 从 select 导出，ElTabPane 从 tabs 导出），
// 用 directoryAliases 落到导出它们的父目录。
//
// 若某组件既没有自己的 index.mjs 也不在别名表里，退回原来的 barrel 引用并打印警告：
// 宁可体积回退，也不能让组件静默注册失败导致页面渲染成未知元素。
import fs from "node:fs";
import { createRequire } from "node:module";
import path from "node:path";

import { ElementPlusResolver } from "unplugin-vue-components/resolvers";

// element-plus 装在仓库根 node_modules（workspaces 提升），不在本应用目录下，
// 因此用 require.resolve 走 Node 的模块解析规则定位，不硬编码相对层级。
const requireFromHere = createRequire(import.meta.url);
const elementPlusPackageRoot = path.dirname(
  requireFromHere.resolve("element-plus/package.json"),
);
const elementPlusComponentsRoot = path.join(
  elementPlusPackageRoot,
  "es",
  "components",
);

// 没有独立 index.mjs 的组件目录 → 真正导出它的组件目录。
// 覆盖 element-plus 2.14 的全部「子组件」目录：这些目录只有 style/index，
// 组件实现由父目录 index.mjs 导出（如 el-form-item 从 form 导出）。
const directoryAliases = {
  option: "select",
  "option-group": "select",
  "tab-pane": "tabs",
  "button-group": "button",
  "checkbox-button": "checkbox",
  "checkbox-group": "checkbox",
  "radio-button": "radio",
  "radio-group": "radio",
  "form-item": "form",
  "menu-item": "menu",
  "menu-item-group": "menu",
  "sub-menu": "menu",
  "dropdown-item": "dropdown",
  "dropdown-menu": "dropdown",
  "table-column": "table",
  step: "steps",
  "timeline-item": "timeline",
  "carousel-item": "carousel",
  "collapse-item": "collapse",
  "descriptions-item": "descriptions",
  "skeleton-item": "skeleton",
  "splitter-panel": "splitter",
  "tour-step": "tour",
  "avatar-group": "avatar",
  "breadcrumb-item": "breadcrumb",
  aside: "container",
  header: "container",
  footer: "container",
  main: "container",
  "anchor-link": "anchor",
};

// 少数导出的名字与目录名不同构，靠 sideEffects 推不出目录，按导出名兜底。
// ElAutoResizer 还被 ElementPlusResolver 默认列入 noStylesComponents（importStyle: false），
// sideEffects 为 undefined，只能走这里。
const componentNameAliases = {
  ElAutoResizer: "table-v2",
  ElLoadingDirective: "loading",
  ElLoadingService: "loading",
  ElPopoverDirective: "popover",
  ElPopperArrow: "popper",
  ElPopperContent: "popper",
  ElPopperTrigger: "popper",
};

// resolver 返回的 sideEffects 形如：
// ["element-plus/es/components/base/style/index", "element-plus/es/components/button/style/index"]
// 第二项即该组件的样式目录，正好也是组件在 es/components 下的目录名。
const styleDirectoryPattern = /element-plus\/es\/components\/([^/]+)\/style\/index$/;

function extractComponentDirectory(sideEffects) {
  if (!Array.isArray(sideEffects)) {
    return null;
  }

  for (const entry of sideEffects) {
    const match = styleDirectoryPattern.exec(String(entry || ""));
    if (match && match[1] !== "base") {
      return match[1];
    }
  }

  return null;
}

function resolveComponentSubpath(name, sideEffects) {
  const directory = extractComponentDirectory(sideEffects);
  const candidates = [
    directory,
    directory && directoryAliases[directory],
    componentNameAliases[name],
  ].filter(Boolean);

  for (const candidate of candidates) {
    const entryFile = path.join(elementPlusComponentsRoot, candidate, "index.mjs");
    if (fs.existsSync(entryFile)) {
      return `element-plus/es/components/${candidate}/index.mjs`;
    }
  }

  // 找不到子路径必须失败而不是静默回退：回退会让该 chunk 重新吞下 dayjs 等 barrel 副作用
  // （实测 16 个 chunk 各多 3,064 B gzip），且只在构建日志里打一行 warn 无人察觉。
  throw new Error(
    `[element-plus-subpath-resolver] 找不到 ${name} 的组件子路径（目录 ${directory || "未知"}）。` +
      "该组件会重新引入 dayjs 等 barrel 副作用，请在 componentNameAliases 补一条指向导出它的目录。",
  );
}

/**
 * 生成把 Element Plus 组件解析到子路径的 resolver 列表。
 * @param {Parameters<typeof ElementPlusResolver>[0]} options 与 ElementPlusResolver 相同的选项
 */
export function createElementPlusSubpathResolvers(options = {}) {
  // ElementPlusResolver 返回 [组件 resolver, 指令 resolver]，两者都要改写。
  return ElementPlusResolver(options).map((resolver) => ({
    type: resolver.type,
    async resolve(name) {
      const resolved = await resolver.resolve(name);

      // 图标走 @element-plus/icons-vue，不是 EP 组件，原样返回。
      if (!resolved || resolved.from !== "element-plus/es") {
        return resolved;
      }

      return { ...resolved, from: resolveComponentSubpath(name, resolved.sideEffects) };
    },
  }));
}
