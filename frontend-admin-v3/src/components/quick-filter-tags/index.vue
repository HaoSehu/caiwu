<template>
  <div class="quick-filter-tags">
    <t-tag
      v-for="item in options"
      :key="item.key"
      :variant="modelValue === item.key ? 'outline' : 'light'"
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
  (event: 'update:modelValue', value: string): void;
  (event: 'change', value: string): void;
}>();

function select(key: string) {
  emit('update:modelValue', key);
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
  min-height: 32px;
  cursor: pointer;
  user-select: none;
}
</style>
