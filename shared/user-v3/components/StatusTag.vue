<template>
  <t-tag :theme="theme" :variant="variant" :size="size">{{ label }}</t-tag>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { getStatusConfig, resolveTdTagTheme } from '@caiwu/shared/statusConfig';

interface StatusConfig {
  label?: string;
  tagType?: string;
  color?: string;
}

const props = withDefaults(
  defineProps<{
    statusMap: Record<string, unknown>;
    status: unknown;
    variant?: 'light' | 'dark' | 'outline' | 'light-outline';
    size?: 'small' | 'medium' | 'large';
  }>(),
  {
    variant: 'light',
    size: 'small',
  },
);

const config = computed<StatusConfig>(() => (getStatusConfig(props.statusMap, props.status as string | number) || {}) as StatusConfig);
const label = computed(() => config.value.label || String(props.status ?? '-'));
// TDesign theme 映射统一走 shared/statusConfig，与管理端 StatusTag 共用同一套规则。
const theme = computed(() => resolveTdTagTheme(String(config.value.tagType || config.value.color || 'default')) as 'success' | 'warning' | 'danger' | 'primary' | 'default');
</script>
