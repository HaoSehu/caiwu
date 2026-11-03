<template>
  <t-tree-select
    v-model="localValue"
    class="binding-tree-select"
    :class="{ 'binding-tree-select--compact': props.compact }"
    :data="treeOptions"
    :tree-props="treeProps"
    :loading="treeLoading"
    :disabled="props.disabled || treeLoading"
    :multiple="props.mode !== 'single'"
    filterable
    clearable
    :min-collapsed-num="minCollapsedNum"
    :placeholder="placeholderText"
    :popup-props="treePopupProps"
    :collapsed-items="collapsedItems"
    @change="handleChange"
    @popup-visible-change="handlePopupVisibleChange"
  />
</template>
<script setup lang="ts">
import { computed, ref, watch } from 'vue';

import type { ProductBindingRecord } from '@/api/admin';
import type { BindingTreeMode } from '@/hooks/useProductBindingTree';
import { useProductBindingTree } from '@/hooks/useProductBindingTree';

const props = withDefaults(
  defineProps<{
    modelValue: string | number | (string | number)[] | null | undefined;
    existingBindings?: ProductBindingRecord[];
    mode?: BindingTreeMode;
    placeholder?: string;
    compact?: boolean;
    hideTypeGroup?: boolean;
    expandAll?: boolean;
    popupMaxHeight?: number;
    disabled?: boolean;
  }>(),
  {
    mode: 'multiple',
    placeholder: '',
    compact: false,
    hideTypeGroup: false,
    expandAll: false,
    popupMaxHeight: 360,
    disabled: false,
  },
);

const emit = defineEmits<{
  'update:modelValue': [value: (string | number)[]];
  change: [payload: { binding_ids: string[]; bindings: ProductBindingRecord[] }];
}>();

const {
  treeLoading,
  treeOptions,
  treeProps,
  loadTree,
  normalizeSelectionForTree,
  firstSelectionForTree,
  selectionToBindings,
} = useProductBindingTree(props.mode, {
  hideTypeGroup: () => props.hideTypeGroup,
  expandAll: () => props.expandAll,
});

const localValue = ref<string | string[]>(
  props.mode === 'single' ? firstSelectionForTree(props.modelValue) : normalizeSelectionForTree(props.modelValue),
);

const placeholderText = computed(() => {
  if (props.placeholder) return props.placeholder;

  switch (props.mode) {
    case 'single':
      return '选择一个商品配置';
    case 'batch':
      return '按分类批量选择绑定配置';
    default:
      return '按分类选择绑定配置';
  }
});

const minCollapsedNum = computed(() => {
  if (props.mode === 'single') return 0;
  return props.compact ? 0 : 1;
});

const collapsedItems = computed(() => {
  if (!props.compact || props.mode === 'single') return undefined;
  return (_h: unknown, context: { count: number }) => `已选 ${context.count} 个配置`;
});

const treePopupProps = computed(() => ({
  overlayClassName: 'binding-tree-select-popup',
  overlayInnerStyle: {
    maxHeight: `${props.popupMaxHeight}px`,
    overflowY: 'auto',
  },
}));

// 选中值同步只依赖绑定值本身：回显不需要整棵商品树，树数据留到用户展开下拉时再加载。
watch([() => props.modelValue, treeOptions], ([modelValue]) => {
  if (props.mode === 'single') {
    const strVal = firstSelectionForTree(modelValue);
    if (localValue.value !== strVal) localValue.value = strVal;
  } else {
    const arrVal = normalizeSelectionForTree(modelValue);
    if (JSON.stringify(arrVal) !== JSON.stringify(localValue.value)) {
      localValue.value = arrVal;
    }
  }
});

function handleChange(value: unknown) {
  const result = selectionToBindings(value, props.existingBindings);

  if (props.mode === 'batch') {
    // 批量模式：localValue 保持原始选中值（含分类节点 ID）
    localValue.value = normalizeTreeValue(value);
  } else if (props.mode === 'single') {
    // 单选模式：TDesign 传回的是 string
    localValue.value = firstTreeValue(value);
  } else {
    // 多选模式
    localValue.value = normalizeTreeValue(value);
  }

  const currentValue = normalizeRawModelValue(props.modelValue);
  const useNumber = currentValue.length > 0 && typeof currentValue[0] === 'number';
  const emitValue: (string | number)[] = useNumber
    ? result.binding_ids.map((id) => Number(id)).filter((n) => !Number.isNaN(n))
    : result.binding_ids;
  emit('update:modelValue', emitValue);
  emit('change', result);
}

function normalizeRawModelValue(value: string | number | (string | number)[] | null | undefined): (string | number)[] {
  if (value === null || value === undefined || value === '') return [];
  return Array.isArray(value) ? value : [value];
}

function normalizeTreeValue(value: unknown): string[] {
  return (Array.isArray(value) ? value : [value]).map((item) => String(item || '').trim()).filter(Boolean);
}

function firstTreeValue(value: unknown): string {
  return normalizeTreeValue(value)[0] || '';
}

// 商品树实测要 23 个串行请求，不能在组件挂载时无条件发出：本组件大量出现在常驻的
// 抽屉/对话框里（用户详情的新建实例、优惠券抽屉）与表格每一行（规格、CPU 型号的绑定列），
// 从未展开也会把整棵树拉一遍、拖慢整页首屏。改为首次展开下拉时才加载。
// 已有选中值不需要树：绑定值本身是产品 ID（或树节点键），回显不依赖节点文案。
// 去重交给 useProductBindingTree 的 loadTree（并发复用同一 Promise、失败可重试），
// 这里不再自带「只请求一次」的布尔位——它永不复位，会让失败后的展开永远拿不到数据。
function handlePopupVisibleChange(visible: boolean) {
  if (visible) void loadTree();
}
</script>
<style scoped lang="less">
.binding-tree-select {
  width: 100%;
}

.binding-tree-select--compact {
  :deep(.t-tag-input) {
    min-height: 32px;
  }

  :deep(.t-tag) {
    max-width: 100%;
  }
}

:global(.binding-tree-select-popup) {
  max-width: calc(100vw - 32px);
}
</style>
