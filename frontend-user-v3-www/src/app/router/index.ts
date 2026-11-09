import { createRouter, createWebHistory } from 'vue-router'
import { clientRoutes } from './routes'
import { registerClientGuards } from './guards'
import { matchesMobileViewport } from '@/composables/useMobileBreakpoint'

export function createClientRouter() {
  const router = createRouter({
    history: createWebHistory(),
    routes: clientRoutes,
    scrollBehavior(_to, _from, savedPosition) {
      if (savedPosition) {
        return savedPosition
      }

      const prefersReducedMotion = typeof window !== 'undefined'
        && typeof window.matchMedia === 'function'
        && window.matchMedia('(prefers-reduced-motion: reduce)').matches
      // 断点与 WebsiteLayout 的 isMobile 判定共用同一份 matchMedia 查询：
      // 用 innerWidth 会被桌面滚动条宽度带偏，(断点-滚动条宽, 断点] 区间里
      // 布局已切移动态、滚动动画却仍走 smooth，两者口径必须一致。
      const isMobile = matchesMobileViewport()

      return {
        top: 0,
        behavior: (prefersReducedMotion || isMobile) ? 'auto' : 'smooth',
      }
    },
  })

  registerClientGuards(router)
  return router
}
