import { ref, onMounted } from 'vue'

const isMobile = ref(false)
const isSmallMobile = ref(false)

function update() {
  if (typeof window === 'undefined') return
  isMobile.value = window.innerWidth <= 900
  isSmallMobile.value = window.innerWidth <= 640
}

let initialized = false

export function useResponsive() {
  if (!initialized) {
    update()
    window.addEventListener('resize', update)
    initialized = true
  }
  onMounted(update)
  return { isMobile, isSmallMobile }
}
