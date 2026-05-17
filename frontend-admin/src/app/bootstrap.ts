import { createApp } from 'vue'
import { createPinia } from 'pinia'

import App from '@/App.vue'
import { createAdminRouter } from '@/app/router'
import { initAdminSessionActivityTracking } from '@/app/runtime/session'
import { warmupAdminRuntimeOrigins } from '@/app/runtime/network'
import { initAdminBodyRuntime } from '@/app/runtime/bodyClass'
import { useAppStore } from '@/stores/app'
import '@/assets/styles/element/index.scss'
import '@/assets/styles/global.scss'

export function bootstrapAdminApp() {
  initAdminBodyRuntime()

  const app = createApp(App)
  const pinia = createPinia()
  const router = createAdminRouter()

  app.use(pinia)
  initAdminSessionActivityTracking()

  warmupAdminRuntimeOrigins([
    import.meta.env.VITE_API_BASE_URL,
    import.meta.env.VITE_CDN_ASSET_HOST,
    import.meta.env.VITE_DOMESTIC_PRECONNECT_ORIGINS,
  ])

  const appStore = useAppStore(pinia)
  void appStore.fetchSiteConfig()

  app.use(router)
  app.mount('#app')
}
