<!--
  TDesign 版通用状态标签。admin 端专用，避免引入 shared/StatusTag（el-tag）。
  用法：
    <StatusTag :status-map="ORDER_STATUS_MAP" :status="row.status" />
    <StatusTag :status-map="INVOICE_STATUS_MAP" :status="row.status" size="small" />
-->
<template>
  <t-tag :theme="tagTheme" :variant="variant" :size="size">
    {{ label }}
  </t-tag>
</template>
<script setup lang="ts">
import { getStatusConfig, resolveTdTagTheme } from '@caiwu/shared/statusConfig';
import type { PropType } from 'vue';
import { computed } from 'vue';

const props = defineProps({
  statusMap: { type: Object as PropType<Record<string, any>>, required: true },
  status: { type: [Number, String] as PropType<number | string>, required: true },
  size: { type: String as PropType<'small' | 'medium' | 'large'>, default: 'small' },
  variant: { type: String as PropType<'dark' | 'light' | 'outline' | 'light-outline'>, default: 'light' },
});

// tagType 的 TDesign 映射已下沉到 shared/statusConfig 的 resolveTdTagTheme，
// 与控制台 StatusTag 共用同一套规则（info/purple/'' 统一落到 default）。
const config = computed(() => getStatusConfig(props.statusMap, props.status));
const tagTheme = computed(() => resolveTdTagTheme(config.value?.tagType || ''));
const label = computed(() => config.value?.label ?? '-');
</script>
