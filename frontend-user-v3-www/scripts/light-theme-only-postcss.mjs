// 浅色模式专用 PostCSS 插件：删除 TDesign 基础样式里永远命中不了的暗色块。
//
// tdesign-vue-next 的 es/style/index.css 自带 ":root.dark, :root[theme-mode='dark'] { ... }"
// 一整块暗色变量（源约 6 KB，minify 后约 5.6 KB），es/skeleton/style/index.css 另有一块只写
// ":root[theme-mode='dark']" 的同类声明。两种形态都由下面的选择器判定覆盖。
// 本站只做浅色（docs/DESIGN.md §2），theme.css 也已删除暗色块，这些声明永远不生效，
// 属于纯体积负担。走 postcss 而不是 generateBundle，是因为 postcss 在 Vite 计算
// 资源 [hash] 之前跑完，产物名与内容仍然一致。
const DARK_ROOT_SELECTOR = /^:root(?:\.dark|\[theme-mode=['"]?dark['"]?\])$/;

function isDarkOnlySelector(selectorText) {
  return selectorText
    .split(',')
    .map((part) => part.trim())
    .filter(Boolean)
    .every((part) => DARK_ROOT_SELECTOR.test(part));
}

export const lightThemeOnlyPostcssPlugin = {
  postcssPlugin: 'www-light-theme-only',
  Once(root) {
    root.walkRules((rule) => {
      // 只匹配整条选择器列表全部是暗色根选择器的情况，避免误删
      // ":root, :root[theme-mode='light']" 这类含浅色分支的变量块。
      if (rule.selector && rule.selector.includes('dark') && isDarkOnlySelector(rule.selector)) {
        rule.remove();
      }
    });
  },
};
