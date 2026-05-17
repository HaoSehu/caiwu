export function syncAdminBodyMobileClass() {
  if (typeof document === 'undefined' || typeof window === 'undefined') {
    return
  }

  document.body.classList.toggle('is-mobile', window.innerWidth <= 900)
  document.body.classList.toggle('is-small-mobile', window.innerWidth <= 640)
}

export function initAdminBodyRuntime() {
  if (typeof window === 'undefined') {
    return
  }

  syncAdminBodyMobileClass()
  window.addEventListener('resize', syncAdminBodyMobileClass)
}
