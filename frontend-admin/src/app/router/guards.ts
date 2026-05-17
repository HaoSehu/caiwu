import { getAdminToken } from '@/app/runtime/session'
import { useAppStore } from '@/stores/app'
import { useUserStore } from '@/stores/user'

export function registerAdminGuards(router) {
  router.beforeEach(async (to, _from, next) => {
    const appStore = useAppStore()
    appStore.applyPageTitle(typeof to.meta.title === 'string' ? to.meta.title : '')
    const token = getAdminToken()

    if (to.meta.guest) {
      if (token) {
        return next('/admin/dashboard')
      }
      return next()
    }

    if (to.meta.requireAuth) {
      if (!token) {
        return next('/admin/login')
      }

      const userStore = useUserStore()
      if (!userStore.info) {
        try {
          await userStore.fetchUserInfo('admin')
        } catch {
          return next('/admin/login')
        }
      }

      return next()
    }

    next()
  })
}
