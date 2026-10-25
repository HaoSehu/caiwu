import { ref } from 'vue'

// 打开延迟：避免鼠标快速滑过一级导航时立刻拉接口
const MEGA_MENU_OPEN_DELAY = 120
// 关闭延迟：给鼠标从导航移入弹层留出通过间隙的时间
const MEGA_MENU_CLOSE_DELAY = 180

// 官网一级导航的巨幕弹层（mega menu）状态机。
// 只负责开合与计时，弹层内容与数据分别由 useNavProductMenu / useNavContentMenu 提供。
export function useMegaMenu({
  canHoverMegaMenu,
  initProducts,
  initNotices,
  initHelp,
}) {
  const activeMenuId = ref(null)
  let closeTimer = null
  let openTimer = null

  function clearMegaMenuTimers() {
    clearTimeout(closeTimer)
    clearTimeout(openTimer)
  }

  function openMegaMenu(menuId) {
    clearMegaMenuTimers()
    activeMenuId.value = menuId
    if (menuId === 'products') {
      initProducts()
    } else if (menuId === 'notices') {
      initNotices()
    } else if (menuId === 'help') {
      initHelp()
    }
  }

  function closeMegaMenu() {
    clearMegaMenuTimers()
    activeMenuId.value = null
  }

  function handleNavHover(item) {
    if (item.menuId && canHoverMegaMenu) {
      // 延迟打开，避免鼠标快速滑过导航时触发菜单与接口拉取
      clearTimeout(openTimer)
      openTimer = setTimeout(() => {
        openMegaMenu(item.menuId)
      }, MEGA_MENU_OPEN_DELAY)
    } else {
      scheduleCloseMegaMenu()
    }
  }

  function keepMegaMenu() {
    clearMegaMenuTimers()
  }

  function scheduleCloseMegaMenu() {
    clearMegaMenuTimers()
    closeTimer = setTimeout(() => {
      activeMenuId.value = null
    }, MEGA_MENU_CLOSE_DELAY)
  }

  return {
    activeMenuId,
    openMegaMenu,
    closeMegaMenu,
    handleNavHover,
    keepMegaMenu,
    scheduleCloseMegaMenu,
    clearMegaMenuTimers,
  }
}
