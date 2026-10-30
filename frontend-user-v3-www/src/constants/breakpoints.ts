// 官网响应式断点。
// 992 对齐控制台 useDeviceLayout 的 tablet 上限（2026-10-11 X1 裁决），
// 保证 960~992 区间两端导航与商品目录形态一致。
// 样式侧的同一取值见 src/assets/styles/variables.scss 的 $breakpoint-nav：
// SCSS 变量无法被脚本引用，改动断点时两处必须同步。
export const MOBILE_BREAKPOINT = 992
