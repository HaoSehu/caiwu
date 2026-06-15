import { useAppStore } from '@/stores/app'

const DYNAMIC_IMPORT_RELOAD_KEY = 'www-router-dynamic-import-reload'
const dynamicImportErrorPattern = /Failed to fetch dynamically imported module|Importing a module script failed/i

export function registerClientGuards(router) {
  router.beforeEach((to, _from, next) => {
    const appStore = useAppStore()
    appStore.applyPageTitle(typeof to.meta.title === 'string' ? to.meta.title : '')
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
