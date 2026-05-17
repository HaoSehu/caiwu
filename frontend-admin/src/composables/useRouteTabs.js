import { ref, watch } from 'vue'

export function useRouteTabs(route, router, validTabs, defaultTab) {
  const resolveTab = (tab) => (validTabs.includes(tab) ? tab : defaultTab)
  const activeTab = ref(resolveTab(route.query.tab))
  const loadedTabs = ref([activeTab.value])

  function ensureTabLoaded(tab) {
    if (!loadedTabs.value.includes(tab)) {
      loadedTabs.value = [...loadedTabs.value, tab]
    }
  }

  watch(
    () => route.query.tab,
    (tab) => {
      const nextTab = resolveTab(tab)
      activeTab.value = nextTab
      ensureTabLoaded(nextTab)
    },
    { immediate: true }
  )

  function onTabChange(tab) {
    const nextTab = resolveTab(tab)
    ensureTabLoaded(nextTab)
    if (route.query.tab === nextTab) {
      return
    }
    router.replace({ query: { ...route.query, tab: nextTab } })
  }

  return {
    activeTab,
    loadedTabs,
    ensureTabLoaded,
    onTabChange,
  }
}
