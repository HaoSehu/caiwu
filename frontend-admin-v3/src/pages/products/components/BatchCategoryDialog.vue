<template>
  <t-dialog
    v-model:visible="visible"
    header="批量归类"
    width="min(520px, calc(100vw - 32px))"
    :confirm-btn="{ content: '确定', loading: submitting }"
    @confirm="submit"
  >
    <div class="batch-dialog-summary">将 {{ targetKeys.length }} 个商品移动到目标分类。</div>
    <t-form :data="form" label-align="right" label-width="110px">
      <t-form-item label="目标分类" name="target_product_group_key">
        <t-select v-model="form.target_product_group_key" filterable clearable placeholder="请选择目标分类">
          <t-option
            v-for="item in options"
            :key="productGroupOptionKey(item)"
            :label="productGroupOptionLabel(item)"
            :value="productGroupOptionKey(item)"
          />
        </t-select>
      </t-form-item>
    </t-form>
  </t-dialog>
</template>
<script setup lang="ts">
import { MessagePlugin } from 'tdesign-vue-next/es/message/plugin';
import { reactive, ref } from 'vue';

import type { ProductCategoryRecord } from '@/api/product';
import { productApi } from '@/api/product';

import {
  errorMessage,
  findProductGroupByKey,
  productGroupOptionKey,
  productGroupOptionLabel,
  productGroupPayload,
} from '../composables/useProductShared';

// 从 ProductCatalog.vue 抽离：批量归类只依赖「可选分类列表」与一次批量写入，
// 分类数据由父级以 props 传入，避免子组件重复拉取分类树。
defineOptions({ name: 'BatchCategoryDialog' });

const props = defineProps<{ options: ProductCategoryRecord[] }>();

const emit = defineEmits<{
  (event: 'done'): void;
  (event: 'loading-change', value: boolean): void;
}>();

const visible = ref(false);
const submitting = ref(false);
const targetKeys = ref<Array<string | number>>([]);
const form = reactive({
  target_product_group_key: '',
});

function resolveGroupLabel(key: string) {
  const group = findProductGroupByKey(props.options, key);
  return group ? productGroupOptionLabel(group) : '目标分类';
}

function open(productIds: Array<string | number>) {
  targetKeys.value = productIds;
  form.target_product_group_key = '';
  visible.value = true;
}

async function submit() {
  const productIds = targetKeys.value.map((id) => Number(id)).filter((id) => Number.isFinite(id) && id > 0);
  const targetGroup = findProductGroupByKey(props.options, form.target_product_group_key);

  if (!productIds.length) {
    MessagePlugin.warning('请先选择商品');
    return;
  }
  if (!targetGroup) {
    MessagePlugin.warning('请选择目标分类');
    return;
  }

  submitting.value = true;
  emit('loading-change', true);
  try {
    const response = await productApi.batchUpdateCategory({
      product_ids: productIds,
      ...productGroupPayload(targetGroup, 'target_'),
    });
    const responseRecord = response && typeof response === 'object' ? (response as Record<string, unknown>) : {};
    const updatedCount = Number(responseRecord.updated_count ?? productIds.length);
    const targetLabel = String(
      responseRecord.target_effective_product_group_full_name ||
        responseRecord.target_effective_product_group_name ||
        resolveGroupLabel(form.target_product_group_key),
    );

    if (updatedCount > 0) {
      MessagePlugin.success(`已将 ${updatedCount} 个商品移入「${targetLabel}」`);
    } else {
      MessagePlugin.success(`所选商品已在「${targetLabel}」下`);
    }

    visible.value = false;
    targetKeys.value = [];
    emit('done');
  } catch (error) {
    MessagePlugin.error(errorMessage(error, '批量归类失败'));
  } finally {
    submitting.value = false;
    emit('loading-change', false);
  }
}

defineExpose({ open });
</script>
