import { createRouter, createWebHistory } from 'vue-router'
import { clientRoutes } from './routes'
import { registerClientGuards } from './guards'

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
      // 断点与 WebsiteLayout 的 isMobile 判定保持一致（960）
      const isMobile = typeof window !== 'undefined' && window.innerWidth <= 960

      return {
        top: 0,
        behavior: (prefersReducedMotion || isMobile) ? 'auto' : 'smooth',
      }
    },
  })

  registerClientGuards(router)
  return router
}
