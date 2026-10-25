import { computed, ref } from 'vue'

// 官网头部用户菜单（桌面下拉 + 移动端用户入口）的全部状态与交互。
// 轻量自定义下拉，替代 el-dropdown，避免 element-plus popper 机制进首屏。
export function useUserMenu(userStore) {
  const userMenuOpen = ref(false)
  const mobileUserMenuOpen = ref(false)
  const userMenuTriggerRef = ref(null)
  const mobileUserMenuTriggerRef = ref(null)

  const userAvatarInitial = computed(() => {
    if (!userStore.isLoggedIn) return ''
    const info = userStore.info
    const name = (info?.display_name || info?.nickname || info?.email || '').trim()
    return name ? name.charAt(0).toUpperCase() : 'U'
  })

  const userDisplayName = computed(() => {
    if (!userStore.isLoggedIn) return ''
    const info = userStore.info
    return (info?.display_name || info?.nickname || info?.email || '').trim()
  })

  function handleUserMenuCommand(command) {
    if (command === 'logout') {
      userStore.logout().then(() => {
        window.location.reload()
      })
    } else {
      window.location.href = command
    }
  }

  function toggleUserMenu() {
    userMenuOpen.value = !userMenuOpen.value
  }

  function toggleMobileUserMenu() {
    mobileUserMenuOpen.value = !mobileUserMenuOpen.value
  }

  function onUserMenuCommand(command) {
    userMenuOpen.value = false
    mobileUserMenuOpen.value = false
    handleUserMenuCommand(command)
  }

  function closeUserMenusOnOutside(event) {
    const desktopRoot = userMenuTriggerRef.value
    const mobileRoot = mobileUserMenuTriggerRef.value
    const insideDesktop = desktopRoot && desktopRoot.contains(event.target)
    const insideMobile = mobileRoot && mobileRoot.contains(event.target)
    if (!insideDesktop && !insideMobile) {
      userMenuOpen.value = false
      mobileUserMenuOpen.value = false
    }
  }

  function closeUserMenusOnEscape(event) {
    if (event.key === 'Escape') {
      if (userMenuOpen.value) {
        userMenuOpen.value = false
        userMenuTriggerRef.value?.focus()
      }
      if (mobileUserMenuOpen.value) {
        mobileUserMenuOpen.value = false
        mobileUserMenuTriggerRef.value?.focus()
      }
    }
  }

  return {
    userAvatarInitial,
    userDisplayName,
    userMenuOpen,
    mobileUserMenuOpen,
    userMenuTriggerRef,
    mobileUserMenuTriggerRef,
    toggleUserMenu,
    toggleMobileUserMenu,
    onUserMenuCommand,
    closeUserMenusOnOutside,
    closeUserMenusOnEscape,
  }
}
