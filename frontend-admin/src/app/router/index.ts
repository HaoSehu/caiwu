import { createRouter, createWebHistory } from 'vue-router'
import { adminRoutes } from './routes'
import { registerAdminGuards } from './guards'

export function createAdminRouter() {
  const router = createRouter({
    history: createWebHistory(),
    routes: adminRoutes,
  })

  registerAdminGuards(router)
  return router
}
