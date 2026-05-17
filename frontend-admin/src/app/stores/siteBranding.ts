import { computed, ref } from 'vue'
import { defineStore } from 'pinia'
import { applyDocumentTitle, deriveInitials } from '@caiwu/shared/runtime'
import siteApi from '@/api/site'

const DEFAULT_SITE_NAME = import.meta.env.VITE_APP_TITLE || '创欧云'
const DEFAULT_SITE_LOGO = '/branding/logo.svg'
const DEFAULT_FAVICON = '/branding/logo1.svg'

export const useSiteBrandingStore = defineStore('site-branding', () => {
  const siteName = ref(DEFAULT_SITE_NAME)
  const browserTitle = ref(DEFAULT_SITE_NAME)
  const siteLogo = ref(DEFAULT_SITE_LOGO)
  const siteFavicon = ref(DEFAULT_FAVICON)
  const brandInitials = computed(() => deriveInitials(siteName.value))

  async function fetchSiteConfig() {
    try {
      const data = (await siteApi.config()).data || {}
      siteName.value = (data.site_name || '').trim() || DEFAULT_SITE_NAME
      browserTitle.value = (data.browser_title || '').trim() || siteName.value
      siteLogo.value = (data.site_logo || '').trim() || DEFAULT_SITE_LOGO
      siteFavicon.value = (data.site_favicon || '').trim() || DEFAULT_FAVICON
    } catch {
      siteLogo.value = siteLogo.value || DEFAULT_SITE_LOGO
      siteFavicon.value = siteFavicon.value || DEFAULT_FAVICON
    } finally {
      applyPageTitle('')
    }
  }

  function applyPageTitle(pageTitle = '') {
    const baseTitle = browserTitle.value || siteName.value || DEFAULT_SITE_NAME
    applyDocumentTitle(pageTitle, baseTitle, siteFavicon.value || DEFAULT_FAVICON, DEFAULT_FAVICON)
  }

  return {
    siteName,
    browserTitle,
    siteLogo,
    siteFavicon,
    brandInitials,
    applyPageTitle,
    fetchSiteConfig,
  }
})
