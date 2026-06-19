<!--
  列表筛选区统一容器：flex 布局 + 搜索/重置/刷新按钮。
  用法：
    <ListFilterBar @search="handleSearch" @reset="resetFilters" @refresh="loadList">
      <t-input v-model="filters.keyword" placeholder="关键词" />
      <t-select v-model="filters.status" :options="statusOptions" />
    </ListFilterBar>
-->
<template>
  <div class="list-filter-bar">
    <div class="list-filter-bar__fields">
      <slot />
    </div>
    <div class="list-filter-bar__actions">
      <slot name="actions" />
      <t-button theme="primary" variant="outline" :loading="loading" @click="emit('search')">
        <template #icon><search-icon /></template>
        搜索
      </t-button>
      <t-button variant="text" @click="emit('reset')">
        <template #icon><refresh-icon /></template>
        重置
      </t-button>
      <t-button variant="text" @click="emit('refresh')">
        <template #icon><refresh-icon /></template>
        刷新
      </t-button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { RefreshIcon, SearchIcon } from 'tdesign-icons-vue-next';

defineProps<{ loading?: boolean }>();
const emit = defineEmits<{ search: []; reset: []; refresh: [] }>();
</script>

<style lang="less" scoped>
.list-filter-bar {
  display: flex;
  flex-wrap: wrap;
  gap: var(--td-comp-margin-s);
  align-items: center;
  margin-bottom: var(--td-comp-margin-l);

  &__fields {
    display: flex;
    flex-wrap: wrap;
    gap: var(--td-comp-margin-s);
    flex: 1;
  }

  &__actions {
    display: flex;
    gap: var(--td-comp-margin-s);
  }
}
</style>
