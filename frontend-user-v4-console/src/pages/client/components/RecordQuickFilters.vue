<template>
  <div class="quick-filter-tags">
    <t-tag
      v-for="item in options"
      :key="item.key"
      variant="light"
      :theme="modelValue === item.key ? 'primary' : 'default'"
      class="quick-filter-tag"
      role="button"
      tabindex="0"
      :aria-pressed="modelValue === item.key"
      @click="select(item.key)"
      @keydown.enter.prevent="select(item.key)"
      @keydown.space.prevent="select(item.key)"
    >
      {{ item.label }}
    </t-tag>
  </div>
</template>
<script setup lang="ts">
import type { QuickFilterOption } from '@/domains/finance/dateFilters';
import { QUICK_FILTER_OPTIONS } from '@/domains/finance/dateFilters';

withDefaults(
  defineProps<{
    modelValue?: string;
    options?: QuickFilterOption[];
  }>(),
  {
    modelValue: '',
    options: () => QUICK_FILTER_OPTIONS,
  },
);

const emit = defineEmits<{
  (event: 'update:model-value', value: string): void;
  (event: 'change', value: string): void;
}>();

function select(key: string) {
  emit('update:model-value', key);
  emit('change', key);
}
</script>
<style scoped lang="less">
.quick-filter-tags {
  display: flex;
  flex-wrap: wrap;
  gap: var(--td-comp-margin-s);
  margin-bottom: var(--td-comp-margin-m);
}

.quick-filter-tag {
  display: inline-flex;
  align-items: center;
  min-height: 2.75rem;
  cursor: pointer;
  user-select: none;
}

@media (max-width: @screen-mobile-max) {
  // 手机端四个快捷标签纵向两列等分：不再换行后左对齐成锯齿，
  // 每个标签的可点面积也随之放大（热区由 min-height 与列宽共同保证）。筛选语义不变。
  .quick-filter-tags {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: var(--td-comp-margin-xs);
    margin-bottom: var(--td-comp-margin-s);
  }

  .quick-filter-tag {
    justify-content: center;
    width: 100%;
    min-height: 2.75rem;
    margin: 0;
  }
}
</style>
