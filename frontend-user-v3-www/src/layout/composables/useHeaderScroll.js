import { ref } from 'vue'

// 官网头部的滚动状态与响应式断点。
// 事件监听由调用方在 onMounted / onBeforeUnmount 中成对注册与移除，
// 保证与用户菜单、移动端抽屉的监听共用同一处生命周期代码。
export function useHeaderScroll({ closeMobileMenu, closeMegaMenu, breakpoint = 992 }) {
  // 992 对齐控制台 useDeviceLayout 的 tablet 上限，跨端跳转时导航形态一致
  const isMobile = ref(
    typeof window === 'undefined' ? false : window.innerWidth <= breakpoint,
  )
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
    const nextMobile = window.innerWidth <= breakpoint
    if (nextMobile === isMobile.value) return
    isMobile.value = nextMobile
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
