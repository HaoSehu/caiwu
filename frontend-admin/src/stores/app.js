import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { useSiteBrandingStore } from '@/app/stores/siteBranding'

export const useAppStore = defineStore('app', () => {
  const sidebarCollapsed = ref(false)
  const siteBrandingStore = useSiteBrandingStore()

  const siteName = computed(() => siteBrandingStore.siteName)
  const browserTitle = computed(() => siteBrandingStore.browserTitle)
  const siteLogo = computed(() => siteBrandingStore.siteLogo)
  const siteFavicon = computed(() => siteBrandingStore.siteFavicon)
  const brandInitials = computed(() => siteBrandingStore.brandInitials)

  function toggleSidebar() {
    sidebarCollapsed.value = !sidebarCollapsed.value
  }

  function applyPageTitle(pageTitle = '') {
    siteBrandingStore.applyPageTitle(pageTitle)
  }

  async function fetchSiteConfig() {
    await siteBrandingStore.fetchSiteConfig()
  }

  return {
    sidebarCollapsed,
    siteName,
    browserTitle,
    siteLogo,
    siteFavicon,
    brandInitials,
    toggleSidebar,
    applyPageTitle,
    fetchSiteConfig,
  }
})
