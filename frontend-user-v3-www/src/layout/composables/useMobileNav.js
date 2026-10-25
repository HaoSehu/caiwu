import { nextTick, ref, watch } from 'vue'

// 官网移动端抽屉导航：可见性、二级展开态、焦点陷阱与滚动锁。
// 抽屉声明了 aria-modal，键盘焦点必须困在面板内；数据加载由调用方的
// useNavProductMenu / useNavContentMenu 负责。
export function useMobileNav({ initProducts, initNotices, initHelp, isNavItemActive }) {
  const mobileNavVisible = ref(false)
  const mobileActiveFirstLevel = ref('products')
  const mobileExpandedType = ref('')
  const mobileMenuToggleRef = ref(null)

  function closeMobileMenu() {
    mobileNavVisible.value = false
  }

  // 手机端菜单：首页仅作返回入口，不参与选中态；一级分类选中由 mobileActiveFirstLevel 驱动
  function isMobileMenuNavActive(item) {
    if (item.to === '/') return false
    return isNavItemActive(item)
  }

  // Tab 在首尾元素间循环，焦点意外逃逸时（如面板内容动态增删）也拉回面板，Esc 兜底关闭。
  function handleMobileMenuKeydown(event) {
    if (!mobileNavVisible.value) return
    if (event.key === 'Escape') {
      closeMobileMenu()
      return
    }
    if (event.key !== 'Tab') return
    const panel = document.getElementById('mobile-menu-panel')
    if (!panel) return
    const focusables = panel.querySelectorAll(
      'button, a[href], input, select, textarea, [tabindex]:not([tabindex="-1"])',
    )
    if (!focusables.length) return
    const first = focusables[0]
    const last = focusables[focusables.length - 1]
    const active = document.activeElement
    const inside = panel.contains(active)
    if (event.shiftKey) {
      if (active === first || !inside) {
        event.preventDefault()
        last.focus()
      }
    } else if (active === last || !inside) {
      event.preventDefault()
      first.focus()
    }
  }

  watch(mobileNavVisible, (visible) => {
    if (visible) {
      document.body.style.overflow = 'hidden'
      mobileExpandedType.value = ''
      initProducts()
      initNotices()
      initHelp()
      // 焦点移入面板首个一级项，避免读屏/键盘从页面背后继续
      nextTick(() => {
        const panel = document.getElementById('mobile-menu-panel')
        const firstItem = panel?.querySelector('.mobile-first-level-item')
        if (firstItem) firstItem.focus()
      })
    } else {
      document.body.style.overflow = ''
      // 关闭后焦点归还触发按钮
      mobileMenuToggleRef.value?.focus()
    }
  })

  return {
    mobileNavVisible,
    mobileActiveFirstLevel,
    mobileExpandedType,
    mobileMenuToggleRef,
    closeMobileMenu,
    isMobileMenuNavActive,
    handleMobileMenuKeydown,
  }
}
