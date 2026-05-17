import { createApp } from 'vue'
import { createPinia } from 'pinia'
import { provideGlobalConfig } from 'element-plus'
import zhCn from 'element-plus/es/locale/lang/zh-cn'

import App from '@/App.vue'
import { createClientRouter } from '@/app/router'
import { initClientSessionActivityTracking } from '@/app/runtime/session'
import { initClientRuntimeConnectionHints, primeClientConnectionHints } from '@/app/runtime/network'
import { useAppStore } from '@/stores/app'
import '@/assets/styles/element/index.scss'
import '@/assets/styles/global.scss'

export function bootstrapClientApp() {
  const app = createApp(App)
  const pinia = createPinia()
  const router = createClientRouter()

  app.use(pinia)
  initClientSessionActivityTracking()
  provideGlobalConfig({ locale: zhCn, size: 'default', zIndex: 3200 }, app, true)
  app.use(router)

  initClientRuntimeConnectionHints({
    apiBaseUrl: import.meta.env.VITE_API_BASE_URL,
    canonicalBase: import.meta.env.VITE_SITE_CANONICAL_BASE,
  })

  app.mount('#app')

  const appStore = useAppStore(pinia)
  if (typeof window === 'undefined' || window.location.pathname !== '/') {
    appStore.fetchSiteConfig()
      .finally(() => {
        primeClientConnectionHints({
          urls: [
            appStore.canonicalBase,
            appStore.siteLogo,
            appStore.siteFavicon,
          ],
        })
      })
  }
}
