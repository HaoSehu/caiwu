<template>
  <div class="data-state">
    <t-loading v-if="loading" text="加载中" />
    <t-empty v-else-if="empty" :description="description || '暂无数据'" />
    <slot v-else />
  </div>
</template>

<script setup lang="ts">
defineProps<{
  loading?: boolean;
  empty?: boolean;
  description?: string;
}>();
</script>
