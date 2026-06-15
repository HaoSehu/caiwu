<template>
  <t-tag :theme="theme" variant="light-outline">{{ label }}</t-tag>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { getStatusConfig } from '@shared/statusConfig';

const props = defineProps<{
  statusMap: Record<string, unknown>;
  status: unknown;
}>();

const config = computed(() => getStatusConfig(props.statusMap, props.status));
const label = computed(() => config.value.label || String(props.status ?? '-'));
const theme = computed(() => {
  const tagType = config.value.tagType || config.value.color || 'default';
  if (['success', 'warning', 'danger', 'primary', 'default'].includes(tagType)) return tagType as 'success' | 'warning' | 'danger' | 'primary' | 'default';
  return 'default';
});
</script>
