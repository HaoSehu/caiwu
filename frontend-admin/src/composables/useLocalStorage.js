import { ref, watch } from 'vue'

export function useLocalStorage(key, defaultValue) {
  let initial = defaultValue
  try {
    const raw = localStorage.getItem(key)
    if (raw !== null) {
      initial = JSON.parse(raw)
    }
  } catch {
    // 忽略解析错误，使用默认值
  }

  const value = ref(initial)

  watch(
    value,
    (val) => {
      try {
        localStorage.setItem(key, JSON.stringify(val))
      } catch {
        // 忽略写入错误
      }
    },
    { deep: true }
  )

  return value
}
