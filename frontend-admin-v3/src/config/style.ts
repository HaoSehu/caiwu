export default {
  showFooter: true,
  isSidebarCompact: false,
  showBreadcrumb: false,
  menuAutoCollapsed: false,
  mode: 'light',
  layout: 'side',
  splitMenu: false,
  sideMode: 'light',
  isFooterAside: false,
  isSidebarFixed: true,
  isHeaderFixed: true,
  // 多标签页开启：标签栏渲染、页面缓存与「关闭/刷新标签」都依赖它（layouts/components/LayoutContent.vue）。
  // 页面要真正被 keep-alive 命中，还需组件 defineOptions({ name }) 与路由 meta.keepAliveName 对齐，见 docs/FRONTEND.md。
  isUseTabsRouter: true,
  showHeader: true,
  hideHeaderLogo: false,
  brandTheme: '#165DFF',
};
