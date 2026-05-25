<template>
  <slot v-if="hasAccess" />
</template>

<script setup>
import { computed } from 'vue'
import { useUserStore } from '@/stores/user'

const props = defineProps({
  /** 所需权限码，单个字符串或数组 */
  permissions: { type: [String, Array], default: () => [] },
  /** 'any' 表示任一权限满足即显示，'all' 表示全部满足才显示 */
  mode: { type: String, default: 'any', validator: (v) => ['any', 'all'].includes(v) },
})

const userStore = useUserStore()

const required = computed(() => {
  const raw = props.permissions
  return Array.isArray(raw) ? raw : [raw]
})

const hasAccess = computed(() => {
  if (!required.value.length) return true
  if (userStore.permissions.includes('*')) return true
  if (props.mode === 'all') {
    return required.value.every((p) => userStore.permissions.includes(p))
  }
  return required.value.some((p) => userStore.permissions.includes(p))
})
</script>
