export function createRafThrottle(fn) {
  let frameId = 0
  let lastArgs = null

  const flush = () => {
    frameId = 0

    if (!lastArgs) {
      return
    }

    const args = lastArgs
    lastArgs = null
    fn(...args)
  }

  const throttled = (...args) => {
    lastArgs = args

    if (typeof window === 'undefined' || typeof window.requestAnimationFrame !== 'function') {
      const directArgs = lastArgs
      lastArgs = null
      fn(...directArgs)
      return
    }

    if (frameId) {
      return
    }

    frameId = window.requestAnimationFrame(flush)
  }

  throttled.cancel = () => {
    if (frameId && typeof window !== 'undefined' && typeof window.cancelAnimationFrame === 'function') {
      window.cancelAnimationFrame(frameId)
    }

    frameId = 0
    lastArgs = null
  }

  throttled.flush = () => {
    if (!lastArgs) {
      return
    }

    if (frameId && typeof window !== 'undefined' && typeof window.cancelAnimationFrame === 'function') {
      window.cancelAnimationFrame(frameId)
    }

    flush()
  }

  return throttled
}
