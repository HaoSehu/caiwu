import { getClientToken } from '@/app/runtime/session'
import { useAppStore } from '@/stores/app'
import { useUserStore } from '@/stores/user'

const DYNAMIC_IMPORT_RELOAD_KEY = 'client-router-dynamic-import-reload'
const dynamicImportErrorPattern = /Failed to fetch dynamically imported module|Importing a module script failed/i

let fetchingUser = false

export function registerClientGuards(router) {
  router.beforeEach(async (to, _from, next) => {
    const appStore = useAppStore()
    appStore.applyPageTitle(typeof to.meta.title === 'string' ? to.meta.title : '')

    const token = getClientToken()
    const allowGuestWithToken = to.name === 'ClientLoginAs'

    if (to.meta.guest) {
      if (token && !allowGuestWithToken) {
        const redirect = typeof to.query.redirect === 'string' ? to.query.redirect : ''
        return next(redirect.startsWith('/') ? redirect : '/client/dashboard')
      }
      return next()
    }

    if (to.meta.requireAuth) {
      if (!token) {
        return next({
          path: '/client/login',
          query: { redirect: to.fullPath },
        })
      }

      const userStore = useUserStore()
      if (!userStore.info && !fetchingUser) {
        fetchingUser = true
        try {
          await userStore.fetchUserInfo('client')
        } catch {
          fetchingUser = false
          return next({
            path: '/client/login',
            query: { redirect: to.fullPath },
          })
        }
        fetchingUser = false
      }
      return next()
    }

    next()
  })

  router.afterEach((to) => {
    if (typeof window === 'undefined') {
      return
    }

    if (window.sessionStorage.getItem(DYNAMIC_IMPORT_RELOAD_KEY) === to.fullPath) {
      window.sessionStorage.removeItem(DYNAMIC_IMPORT_RELOAD_KEY)
    }
  })

  router.onError((error, to) => {
    if (typeof window === 'undefined') {
      return
    }

    const message = String(error?.message || '')
    if (!dynamicImportErrorPattern.test(message)) {
      return
    }

    const targetPath = typeof to?.fullPath === 'string'
      ? to.fullPath
      : `${window.location.pathname}${window.location.search}${window.location.hash}`

    if (window.sessionStorage.getItem(DYNAMIC_IMPORT_RELOAD_KEY) === targetPath) {
      window.sessionStorage.removeItem(DYNAMIC_IMPORT_RELOAD_KEY)
      return
    }

    window.sessionStorage.setItem(DYNAMIC_IMPORT_RELOAD_KEY, targetPath)
    window.location.assign(targetPath)
  })
}
