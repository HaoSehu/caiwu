// 官网响应式断点。
// 992 对齐控制台 useDeviceLayout 的 tablet 上限（2026-10-11 X1 裁决），
// 保证 960~992 区间两端导航与商品目录形态一致。
// 样式侧的同一取值见 src/assets/styles/variables.scss 的 $breakpoint-nav：
// SCSS 变量无法被脚本引用，改动断点时两处必须同步。
export const MOBILE_BREAKPOINT = 992

// 手机形态断点：768 属于 sm（平板），因此手机形态上界是 767.98px，
// 与 variables.scss 的 $breakpoint-mobile-max（$breakpoint-narrow - 0.02px）
// 以及根 theme.css 的移动端查询同口径，见 docs/DESIGN.md §7。
// 该值只表达「手机竖屏」形态切换；导航与商品目录仍按上面的 992 判定。
export const PHONE_BREAKPOINT_MAX = 767.98
