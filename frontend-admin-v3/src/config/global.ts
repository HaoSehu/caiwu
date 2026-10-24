export const prefix = 'tdesign-starter';

/**
 * 移动端断点。
 * 管理端此前在 `layouts/components/Header.vue`、`layouts/setting.vue`、`layouts/components/SideNav.vue`
 * 各写死一份 768，跨端口径已由用户控制台的 useDeviceLayout（mobile 768 / tablet 992 / wide 1200）
 * 与官网 router 的 992 对齐；此处收敛为唯一常量，避免三处继续漂移（审查条目 A9）。
 *
 * 语义：768 属于 SM，只有 **小于** 768 才是移动端（docs/DESIGN.md §7 xs < 768px）。
 * 因此消费点一律写 `window.innerWidth < MOBILE_BREAKPOINT`，与样式层 `max-width: 767.98px` 等价。
 */
export const MOBILE_BREAKPOINT = 768;

/** 平板断点，与控制台 tablet 上限、官网 isMobile 判定保持 992 口径一致。 */
export const TABLET_BREAKPOINT = 992;
