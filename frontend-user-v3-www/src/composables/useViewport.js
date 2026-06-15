import { onBeforeUnmount, onMounted, ref } from 'vue'

const DEFAULT_COMPACT_BREAKPOINT = 768
const DEFAULT_MOBILE_BREAKPOINT = 640

/**
 * 轻量视口尺寸响应式钩子，统一处理 window resize 订阅。
 * 返回 viewportWidth 与两个常用布尔量，避免在多个页面重复绑定 resize。
 *
 * @param {object} options
 * @param {number} [options.compactBreakpoint] 紧凑布局（表格折叠、对话框收缩）阈值
 * @param {number} [options.mobileBreakpoint]  手机端布局阈值
 */
export function useViewport(options = {}) {
  const compactBreakpoint = Number(options.compactBreakpoint ?? DEFAULT_COMPACT_BREAKPOINT)
  const mobileBreakpoint = Number(options.mobileBreakpoint ?? DEFAULT_MOBILE_BREAKPOINT)

  const initialWidth = typeof window === 'undefined' ? 1440 : window.innerWidth
  const viewportWidth = ref(initialWidth)
  const isCompactScreen = ref(initialWidth <= compactBreakpoint)
  const isMobileScreen = ref(initialWidth <= mobileBreakpoint)

  function update() {
    if (typeof window === 'undefined') return
    const width = window.innerWidth
    viewportWidth.value = width
    isCompactScreen.value = width <= compactBreakpoint
    isMobileScreen.value = width <= mobileBreakpoint
  }

  onMounted(() => {
    if (typeof window === 'undefined') return
    update()
    window.addEventListener('resize', update, { passive: true })
  })

  onBeforeUnmount(() => {
    if (typeof window === 'undefined') return
    window.removeEventListener('resize', update)
  })

  return {
    viewportWidth,
    isCompactScreen,
    isMobileScreen,
  }
}

/**
 * 把一组"桌面端期望宽度"映射成响应式 el-dialog width。
 * desktop:   viewport 足够时的目标像素宽度
 * maxVw:     对话框不超过视口的比例（默认 96%）
 *
 * 小屏自动塌缩到 min(desktop, 视口 - gutter)。
 */
export function resolveDialogWidth(viewportWidth, desktop, options = {}) {
  const gutter = Number(options.gutter ?? 32)
  const maxVwRatio = Number(options.maxVwRatio ?? 0.96)

  if (!Number.isFinite(viewportWidth) || viewportWidth <= 0) {
    return `${desktop}px`
  }

  const maxAllowed = Math.max(280, Math.floor(viewportWidth * maxVwRatio) - gutter)
  const width = Math.min(desktop, maxAllowed)
  return `${width}px`
}
