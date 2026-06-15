import { ref, onBeforeUnmount } from 'vue'

export function useCountdown(initialSeconds = 60) {
  const countdown = ref(0)
  let timer = null

  function start() {
    stop()
    countdown.value = initialSeconds
    timer = setInterval(() => {
      countdown.value -= 1
      if (countdown.value <= 0) {
        stop()
      }
    }, 1000)
  }

  function stop() {
    if (timer) {
      clearInterval(timer)
      timer = null
    }
    countdown.value = 0
  }

  onBeforeUnmount(stop)

  return { countdown, start, stop, isCounting: () => countdown.value > 0 }
}
