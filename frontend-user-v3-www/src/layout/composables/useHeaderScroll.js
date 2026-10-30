import { ref } from 'vue'
import { MOBILE_BREAKPOINT } from '@/constants/breakpoints'
import { useMobileBreakpoint } from '@/composables/useMobileBreakpoint'

// 官网头部的滚动状态与响应式断点。
// 事件监听由调用方在 onMounted / onBeforeUnmount 中成对注册与移除，
// 保证与用户菜单、移动端抽屉的监听共用同一处生命周期代码。
// 断点判定复用 useMobileBreakpoint 的同一份 matchMedia 查询（listen 传 false：
// 本 composable 的 resize 监听由布局注册，跨断点时还要顺带收起菜单与 mega menu）。
export function useHeaderScroll({ closeMobileMenu, closeMegaMenu, breakpoint = MOBILE_BREAKPOINT }) {
  const { isMobile, updateMobileState } = useMobileBreakpoint({ breakpoint, listen: false })
  const headerScrolled = ref(false)

  let scrollRaf = null

  function handleScroll() {
    // rAF 合并，一帧只处理一次滚动
    if (scrollRaf) return
    scrollRaf = requestAnimationFrame(() => {
      scrollRaf = null
      if (typeof window === 'undefined') return
      headerScrolled.value = window.scrollY > 8
    })
  }

  function handleResize() {
    if (typeof window === 'undefined') return
    const wasMobile = isMobile.value
    updateMobileState()
    if (wasMobile === isMobile.value) return
    if (!isMobile.value) {
      closeMobileMenu()
    }
    // 跨断点时隐藏态残留的 mega menu（DOM 被 media query 隐藏但状态仍在）
    closeMegaMenu()
  }

  function disposeHeaderScroll() {
    if (scrollRaf) cancelAnimationFrame(scrollRaf)
    scrollRaf = null
  }

  return {
    isMobile,
    headerScrolled,
    handleScroll,
    handleResize,
    disposeHeaderScroll,
  }
}
