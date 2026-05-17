<template>
  <div
    ref="containerRef"
    class="virtual-window-list"
    :style="containerStyle"
    @scroll.passive="handleScroll"
  >
    <div class="virtual-window-list__spacer" :style="{ height: `${totalHeight}px` }">
      <div
        v-for="entry in visibleItems"
        :key="entry.key"
        class="virtual-window-list__item"
        :style="{ transform: `translateY(${entry.offset}px)` }"
        :ref="(element) => setItemElement(entry.key, element)"
      >
        <slot :item="entry.item" :index="entry.index" />
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, toRef } from 'vue'
import { useVirtualWindow } from '@/composables/useVirtualWindow'

const props = defineProps({
  items: { type: Array, default: () => [] },
  height: { type: [Number, String], default: 400 },
  estimateSize: { type: Number, default: 96 },
  overscan: { type: Number, default: 4 },
  itemKey: { type: [String, Function], default: 'id' },
})

const resolveItemKey = (item, index) => {
  if (typeof props.itemKey === 'function') {
    return props.itemKey(item, index)
  }
  return item?.[props.itemKey] ?? index
}

const {
  visibleItems,
  totalHeight,
  viewportHeight,
  setViewportHeight,
  setScrollTop,
  measureItem,
} = useVirtualWindow(toRef(props, 'items'), {
  estimateSize: toRef(props, 'estimateSize'),
  overscan: toRef(props, 'overscan'),
  getItemKey: resolveItemKey,
})

const containerRef = ref(null)

const itemElements = new Map()
let containerResizeObserver = null
let itemResizeObserver = null

const containerStyle = computed(() => ({
  height: typeof props.height === 'number' ? `${props.height}px` : props.height,
}))

function measureViewport() {
  if (!containerRef.value) return
  setViewportHeight(containerRef.value.clientHeight || 0)
}

function handleScroll(event) {
  setScrollTop(event.target?.scrollTop || 0)
}

function observeItem(element, key) {
  if (!element) return

  element.dataset.virtualKey = key
  itemElements.set(key, element)
  measureItem(key, element.offsetHeight)
  itemResizeObserver?.observe(element)
}

function unobserveItem(key) {
  const element = itemElements.get(key)
  if (!element) return

  itemResizeObserver?.unobserve(element)
  itemElements.delete(key)
}

function setItemElement(key, element) {
  const normalizedKey = String(key)

  if (!element) {
    unobserveItem(normalizedKey)
    return
  }

  const previousElement = itemElements.get(normalizedKey)
  if (previousElement && previousElement !== element) {
    itemResizeObserver?.unobserve(previousElement)
  }

  observeItem(element, normalizedKey)
}

async function scrollToBottom() {
  await nextTick()
  if (!containerRef.value) return

  const nextTop = Math.max(totalHeight.value - viewportHeight.value, 0)
  containerRef.value.scrollTop = nextTop
  setScrollTop(nextTop)
}

function scrollToTop() {
  if (!containerRef.value) return

  containerRef.value.scrollTop = 0
  setScrollTop(0)
}

defineExpose({
  scrollToBottom,
  scrollToTop,
})

onMounted(() => {
  if (typeof ResizeObserver !== 'undefined') {
    containerResizeObserver = new ResizeObserver(() => {
      measureViewport()
    })
    itemResizeObserver = new ResizeObserver((entries) => {
      entries.forEach((entry) => {
        const key = entry.target?.dataset?.virtualKey
        if (!key) return
        measureItem(key, entry.target?.offsetHeight || entry.contentRect.height)
      })
    })
  }

  if (containerRef.value) {
    containerResizeObserver?.observe(containerRef.value)
  }

  measureViewport()
})

onBeforeUnmount(() => {
  containerResizeObserver?.disconnect()
  itemResizeObserver?.disconnect()
  itemElements.clear()
})
</script>

<style scoped lang="scss">
.virtual-window-list {
  overflow-y: auto;
  overflow-x: hidden;
  min-height: 0;
}

.virtual-window-list__spacer {
  position: relative;
  min-width: 0;
}

.virtual-window-list__item {
  position: absolute;
  left: 0;
  right: 0;
  top: 0;
  min-width: 0;
  will-change: transform;
}
</style>
