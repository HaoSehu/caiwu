import { defineStore } from 'pinia'
import { computed } from 'vue'
import { useSiteBrandingStore } from '@/app/stores/siteBranding'

export const useAppStore = defineStore('app', () => {
  const siteBrandingStore = useSiteBrandingStore()

  return {
    sidebarCollapsed: computed(() => siteBrandingStore.sidebarCollapsed),
    siteName: computed(() => siteBrandingStore.siteName),
    browserTitle: computed(() => siteBrandingStore.browserTitle),
    siteLogo: computed(() => siteBrandingStore.siteLogo),
    siteFavicon: computed(() => siteBrandingStore.siteFavicon),
    siteDescription: computed(() => siteBrandingStore.siteDescription),
    siteKeywords: computed(() => siteBrandingStore.siteKeywords),
    serviceQqGroup: computed(() => siteBrandingStore.serviceQqGroup),
    serviceEmail: computed(() => siteBrandingStore.serviceEmail),
    serviceHours: computed(() => siteBrandingStore.serviceHours),
    supportGroupTitle: computed(() => siteBrandingStore.supportGroupTitle),
    supportGroupText: computed(() => siteBrandingStore.supportGroupText),
    supportGroupQr: computed(() => siteBrandingStore.supportGroupQr),
    robotsDirective: computed(() => siteBrandingStore.robotsDirective),
    canonicalBase: computed(() => siteBrandingStore.canonicalBase),
    termsUrl: computed(() => siteBrandingStore.termsUrl),
    privacyUrl: computed(() => siteBrandingStore.privacyUrl),
    verifyGoogle: computed(() => siteBrandingStore.verifyGoogle),
    verifyBaidu: computed(() => siteBrandingStore.verifyBaidu),
    verifyBing: computed(() => siteBrandingStore.verifyBing),
    verify360: computed(() => siteBrandingStore.verify360),
    verifySogou: computed(() => siteBrandingStore.verifySogou),
    brandInitials: computed(() => siteBrandingStore.brandInitials),
    toggleSidebar: siteBrandingStore.toggleSidebar,
    applyPageTitle: siteBrandingStore.applyPageTitle,
    hydrateSiteConfig: siteBrandingStore.hydrateSiteConfig,
    fetchSiteConfig: siteBrandingStore.fetchSiteConfig,
  }
})
