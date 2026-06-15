<template>
  <t-tree-select
    v-model="localValue"
    class="binding-tree-select"
    :data="treeOptions"
    :tree-props="treeProps"
    :loading="treeLoading"
    :disabled="treeLoading"
    :multiple="props.mode !== 'single'"
    filterable
    clearable
    :min-collapsed-num="props.mode === 'single' ? 0 : 1"
    :placeholder="placeholderText"
    @change="handleChange"
  />
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import type { ProductBindingRecord } from '@/api/admin';
import { useProductBindingTree, type BindingTreeMode } from '@/hooks/useProductBindingTree';

const props = withDefaults(defineProps<{
  modelValue: string | number | (string | number)[] | null | undefined;
  existingBindings?: ProductBindingRecord[];
  mode?: BindingTreeMode;
}>(), {
  mode: 'multiple',
});

const emit = defineEmits<{
  'update:modelValue': [value: (string | number)[]];
  change: [payload: { binding_ids: string[]; bindings: ProductBindingRecord[] }];
}>();

const { treeLoading, treeOptions, treeProps, loadTree, selectionToBindings } = useProductBindingTree(props.mode);

const localValue = ref<string | string[]>(props.mode === 'single' ? firstModelValue(props.modelValue) : normalizeModelValue(props.modelValue));

const placeholderText = computed(() => {
  switch (props.mode) {
    case 'single': return '选择一个商品配置';
    case 'batch': return '按分类批量选择绑定配置';
    default: return '按分类选择绑定配置';
  }
});

watch(
  () => props.modelValue,
  (val) => {
    if (props.mode === 'single') {
      const strVal = firstModelValue(val);
      if (localValue.value !== strVal) localValue.value = strVal;
    } else {
      const arrVal = normalizeModelValue(val);
      if (JSON.stringify(arrVal) !== JSON.stringify(localValue.value)) {
        localValue.value = arrVal;
      }
    }
  },
);

function handleChange(value: unknown) {
  const result = selectionToBindings(value, props.existingBindings);

  if (props.mode === 'batch') {
    // 批量模式：localValue 保持原始选中值（含分类节点 ID）
    localValue.value = normalizeTreeValue(value);
  } else if (props.mode === 'single') {
    // 单选模式：TDesign 传回的是 string
    localValue.value = firstModelValue(value as string | number | (string | number)[] | null | undefined);
  } else {
    // 多选模式
    localValue.value = result.binding_ids;
  }

  const currentValue = normalizeRawModelValue(props.modelValue);
  const useNumber = currentValue.length > 0 && typeof currentValue[0] === 'number';
  const emitValue: (string | number)[] = useNumber
    ? result.binding_ids.map((id) => Number(id)).filter((n) => !isNaN(n))
    : result.binding_ids;
  emit('update:modelValue', emitValue);
  emit('change', result);
}

function normalizeRawModelValue(value: string | number | (string | number)[] | null | undefined): (string | number)[] {
  if (value === null || value === undefined || value === '') return [];
  return Array.isArray(value) ? value : [value];
}

function normalizeModelValue(value: string | number | (string | number)[] | null | undefined): string[] {
  return normalizeRawModelValue(value).map((item) => String(item || '').trim()).filter(Boolean);
}

function firstModelValue(value: string | number | (string | number)[] | null | undefined): string {
  return normalizeModelValue(value)[0] || '';
}

function normalizeTreeValue(value: unknown): string[] {
  return (Array.isArray(value) ? value : [value])
    .map((item) => String(item || '').trim())
    .filter(Boolean);
}

loadTree();
</script>
