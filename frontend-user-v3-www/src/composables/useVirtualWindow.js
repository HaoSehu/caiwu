import { computed, shallowRef, unref, watch } from 'vue'

function normalizeSize(value, fallback) {
  const size = Number(value)
  return Number.isFinite(size) && size > 0 ? size : fallback
}

function normalizeKey(value, fallbackIndex) {
  if (value === null || value === undefined || value === '') {
    return String(fallbackIndex)
  }
  return String(value)
}

function findStartIndex(layout, targetTop) {
  if (!layout.length) return 0

  let low = 0
  let high = layout.length - 1
  let result = layout.length - 1

  while (low <= high) {
    const middle = Math.floor((low + high) / 2)
    const current = layout[middle]

    if (current.offset + current.size < targetTop) {
      low = middle + 1
    } else {
      result = middle
      high = middle - 1
    }
  }

  return result
}

export function useVirtualWindow(itemsSource, options = {}) {
  const estimateSizeSource = options.estimateSize ?? 96
  const overscanSource = options.overscan ?? 4
  const getItemKey = typeof options.getItemKey === 'function'
    ? options.getItemKey
    : (item, index) => item?.id ?? index

  const sizeMap = shallowRef(new Map())
  const scrollTop = shallowRef(0)
  const viewportHeight = shallowRef(normalizeSize(unref(options.viewportHeight), 0))

  const items = computed(() => {
    const source = unref(itemsSource)
    return Array.isArray(source) ? source : []
  })

  const estimateSize = computed(() => normalizeSize(unref(estimateSizeSource), 96))
  const overscan = computed(() => Math.max(0, Math.floor(normalizeSize(unref(overscanSource), 4))))

  const layout = computed(() => {
    let offset = 0

    return items.value.map((item, index) => {
      const key = normalizeKey(getItemKey(item, index), index)
      const size = sizeMap.value.get(key) ?? estimateSize.value
      const entry = {
        item,
        index,
        key,
        offset,
        size,
      }

      offset += size
      return entry
    })
  })

  const totalHeight = computed(() => {
    const lastItem = layout.value[layout.value.length - 1]
    return lastItem ? lastItem.offset + lastItem.size : 0
  })

  const visibleRange = computed(() => {
    if (!layout.value.length) {
      return { start: 0, end: -1 }
    }

    const viewportBottom = scrollTop.value + Math.max(viewportHeight.value, estimateSize.value)
    const visibleStart = findStartIndex(layout.value, scrollTop.value)

    let visibleEnd = visibleStart
    while (
      visibleEnd < layout.value.length - 1
      && layout.value[visibleEnd].offset < viewportBottom
    ) {
      visibleEnd += 1
    }

    return {
      start: Math.max(0, visibleStart - overscan.value),
      end: Math.min(layout.value.length - 1, visibleEnd + overscan.value),
    }
  })

  const visibleItems = computed(() => {
    if (visibleRange.value.end < visibleRange.value.start) {
      return []
    }

    return layout.value.slice(visibleRange.value.start, visibleRange.value.end + 1)
  })

  function setScrollTop(value) {
    scrollTop.value = Math.max(0, Number(value) || 0)
  }

  function setViewportHeight(value) {
    viewportHeight.value = Math.max(0, Number(value) || 0)
  }

  function measureItem(key, size) {
    const normalizedKey = normalizeKey(key, 0)
    const nextSize = normalizeSize(size, estimateSize.value)
    const currentSize = sizeMap.value.get(normalizedKey)

    if (currentSize === nextSize) return

    const nextMap = new Map(sizeMap.value)
    nextMap.set(normalizedKey, nextSize)
    sizeMap.value = nextMap
  }

  function resetMeasurements() {
    sizeMap.value = new Map()
  }

  watch([totalHeight, viewportHeight], ([nextHeight, nextViewport]) => {
    const maxScrollTop = Math.max(nextHeight - nextViewport, 0)
    if (scrollTop.value > maxScrollTop) {
      scrollTop.value = maxScrollTop
    }
  })

  watch(items, () => {
    const currentKeys = new Set(
      items.value.map((item, index) => normalizeKey(getItemKey(item, index), index)),
    )

    if (currentKeys.size === sizeMap.value.size) return

    const nextMap = new Map()
    currentKeys.forEach((key) => {
      if (sizeMap.value.has(key)) {
        nextMap.set(key, sizeMap.value.get(key))
      }
    })
    sizeMap.value = nextMap
  })

  return {
    items,
    layout,
    totalHeight,
    visibleItems,
    scrollTop,
    viewportHeight,
    setScrollTop,
    setViewportHeight,
    measureItem,
    resetMeasurements,
  }
}
