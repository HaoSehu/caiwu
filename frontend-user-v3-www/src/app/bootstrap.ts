import { createApp } from 'vue'
import { createPinia } from 'pinia'
import { provideGlobalConfig } from 'element-plus'
import zhCn from 'element-plus/es/locale/lang/zh-cn'

import App from '@/App.vue'
import { createClientRouter } from '@/app/router'
import { initClientRuntimeConnectionHints, primeClientConnectionHints } from '@/app/runtime/network'
import { useAppStore } from '@/stores/app'
import '@/assets/styles/element/index.scss'
import '@/assets/styles/global.scss'

export function bootstrapClientApp() {
  const app = createApp(App)
  const pinia = createPinia()
  const router = createClientRouter()

  app.use(pinia)
  provideGlobalConfig({ locale: zhCn, size: 'default', zIndex: 3200 }, app, true)
  app.use(router)

  initClientRuntimeConnectionHints({
    apiBaseUrl: import.meta.env.VITE_API_BASE_URL,
  })

  // 淡出初始加载动画后再挂载，避免白闪
  const splash = document.getElementById('app-splash')
  if (splash) {
    splash.classList.add('fade-out')
    splash.addEventListener('transitionend', () => splash.remove(), { once: true })
    // 兜底：即使 transition 未触发也确保移除
    setTimeout(() => splash.remove(), 400)
  }

  app.mount('#app')

  const appStore = useAppStore()
  if (typeof window === 'undefined' || window.location.pathname !== '/') {
    appStore.fetchSiteConfig()
      .finally(() => {
        primeClientConnectionHints({
          urls: [
            appStore.siteLogo,
            appStore.siteFavicon,
          ],
        })
      })
  }
}
