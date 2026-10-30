<template>
  <t-dialog
    v-model:visible="visible"
    header="拆分商品"
    width="min(680px, calc(100vw - 32px))"
    :confirm-btn="{
      content: '确定',
      loading: submitting,
      disabled: previewLoading || !previewRows.length,
    }"
    @confirm="submit"
  >
    <div class="split-dialog-intro">
      <strong>按 CPU 与内存子选项生成独立商品</strong>
      <p>系统会读取已选商品的 CPU、内存配置子项，基础规格更新原商品，其余规格生成独立商品。</p>
    </div>

    <div class="split-product-meta">
      <span>已选商品 {{ targetKeys.length }} 个</span>
      <span v-if="previewCount">预计处理 {{ previewCount }} 个规格</span>
      <span v-if="skippedCount">跳过 {{ skippedCount }} 个</span>
    </div>

    <t-loading :loading="previewLoading">
      <div class="split-product-preview">
        <template v-if="previewRows.length">
          <article v-for="row in previewRows" :key="row.key" class="split-product-item">
            <div>
              <strong>{{ row.display_name || '-' }}</strong>
              <span>{{ row.source_display_name || '-' }}</span>
            </div>
            <t-tag :theme="row.action === 'update' ? 'warning' : 'success'" variant="light">
              {{ formatSplitAction(row.action) }}
            </t-tag>
          </article>
        </template>
        <t-empty v-else description="未找到可拆分的 CPU 或内存子选项" />
      </div>
    </t-loading>

    <t-alert theme="warning" message="基础规格沿用原商品；同名拆分商品会更新，不会重复创建。" />
  </t-dialog>
</template>
<script setup lang="ts">
import { MessagePlugin } from 'tdesign-vue-next/es/message/plugin';
import { computed, ref } from 'vue';

import { productApi } from '@/api/product';

import { errorMessage, toPlainRecord } from '../composables/useProductShared';

// 从 ProductCatalog.vue 抽离：拆分预览的拉取、预览行渲染与提交，父级只负责选中行与刷新目录。
defineOptions({ name: 'SplitProductDialog' });

const emit = defineEmits<{
  (event: 'done'): void;
  (event: 'loading-change', value: boolean): void;
}>();

interface SplitProductPreviewRow {
  key: string;
  product_id: number;
  display_name: string;
  source_product_id: number;
  source_display_name: string;
  action: string;
}

const visible = ref(false);
const previewLoading = ref(false);
const submitting = ref(false);
const targetKeys = ref<Array<string | number>>([]);
const preview = ref<Record<string, unknown> | null>(null);

const previewItems = computed(() => {
  const items = preview.value?.items;
  return Array.isArray(items) ? items : [];
});
const previewRows = computed<SplitProductPreviewRow[]>(() =>
  previewItems.value.flatMap((itemValue) => {
    const item = toPlainRecord(itemValue);
    const variants = Array.isArray(item.variants) ? item.variants : [];
    return variants.map((variantValue) => {
      const variant = toPlainRecord(variantValue);
      return {
        key: `${item.source_product_id || 'source'}-${variant.variant_key || variant.display_name || variant.product_id || 'row'}`,
        product_id: Number(variant.product_id || variant.id || 0),
        display_name: String(variant.display_name || '-'),
        source_product_id: Number(item.source_product_id || item.product_id || 0),
        source_display_name: String(variant.source_display_name || item.source_display_name || '-'),
        action: String(variant.action || 'create'),
      };
    });
  }),
);
const previewCount = computed(() => Number(preview.value?.preview_count || 0));
const skippedCount = computed(() => Number(preview.value?.skipped_count || 0));

function formatSplitAction(action: string) {
  return action === 'update' ? '更新' : '新建';
}

function normalizeProductIds(keys: Array<string | number>) {
  return keys.map((id) => Number(id)).filter((id) => Number.isFinite(id) && id > 0);
}

async function open(productIds: Array<string | number>) {
  if (!productIds.length) {
    MessagePlugin.warning('请先选择需要拆分的商品');
    return;
  }

  targetKeys.value = [...productIds];
  preview.value = null;
  visible.value = true;
  previewLoading.value = true;
  emit('loading-change', true);
  try {
    const response = await productApi.splitPreview({ product_ids: normalizeProductIds(targetKeys.value) });
    preview.value = toPlainRecord(response);
  } catch (error) {
    MessagePlugin.error(errorMessage(error, '加载拆分预览失败'));
  } finally {
    previewLoading.value = false;
    emit('loading-change', false);
  }
}

async function submit() {
  const productIds = normalizeProductIds(targetKeys.value);
  if (!productIds.length) {
    MessagePlugin.warning('请先选择需要拆分的商品');
    return;
  }

  submitting.value = true;
  try {
    const response = await productApi.splitProducts({ product_ids: productIds });
    const responseRecord = toPlainRecord(response);
    const createdCount = Number(responseRecord.created_count || 0);
    const updatedCount = Number(responseRecord.updated_count || 0);
    const skippedResponseCount = Number(responseRecord.skipped_count || 0);
    const changedCount = createdCount + updatedCount;

    if (changedCount > 0) {
      MessagePlugin.success(`已生成 ${createdCount} 个商品，更新 ${updatedCount} 个，跳过 ${skippedResponseCount} 个`);
    } else {
      MessagePlugin.warning(
        skippedResponseCount > 0 ? `未生成新商品，跳过 ${skippedResponseCount} 个` : '未找到可拆分商品',
      );
    }

    visible.value = false;
    targetKeys.value = [];
    emit('done');
  } catch (error) {
    MessagePlugin.error(errorMessage(error, '拆分商品失败'));
  } finally {
    submitting.value = false;
  }
}

defineExpose({ open });
</script>
<style lang="less" scoped>
.split-product-preview {
  display: flex;
  flex-direction: column;
  gap: var(--td-comp-margin-s);
  max-height: 300px;
  margin-bottom: var(--td-comp-margin-m);
  overflow-y: auto;
}

.split-product-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--td-comp-margin-m);
  border: 1px solid var(--td-component-border);
  border-radius: var(--td-radius-medium);
  padding: var(--td-comp-paddingTB-s) var(--td-comp-paddingLR-m);
  background: var(--td-bg-color-container);
}

.split-product-item > div {
  display: flex;
  flex-direction: column;
  gap: var(--td-comp-margin-xxs);
  min-width: 0;
}

.split-product-item strong,
.split-product-item span {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.split-product-item span {
  color: var(--td-text-color-secondary);
  font-size: var(--td-font-size-size-1, 12px);
}

@media (max-width: 767.98px) {
  .split-product-item {
    align-items: flex-start;
    flex-direction: column;
  }
}
</style>
