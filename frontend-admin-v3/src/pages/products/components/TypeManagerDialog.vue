<template>
  <t-dialog
    :visible="visible"
    class="type-manager-dialog"
    header="管理一级分类"
    width="880px"
    :footer="false"
    @update:visible="emit('update:visible', $event)"
  >
    <div class="type-manager">
      <section class="type-manager-editor" :class="{ 'is-editing': editingTypeValue }">
        <div class="type-manager-editor__head">
          <div>
            <strong>{{ editingTypeValue ? '编辑一级分类' : '新建一级分类' }}</strong>
            <span v-if="editingTypeValue">{{ normalizeBusinessProductType(typeForm.product_type) }}</span>
          </div>
        </div>

        <t-form :data="typeForm" label-align="top" class="type-form">
          <div class="type-form-grid">
            <t-form-item label="一级分类名称" name="label" required-mark>
              <t-input v-model="typeForm.label" maxlength="40" placeholder="如 云服务器" />
            </t-form-item>
            <t-form-item label="产品类型" name="product_type" required-mark>
              <t-select v-model="typeForm.product_type" :options="businessProductTypeOptions" />
            </t-form-item>
            <t-form-item label="图标" name="icon" class="type-icon-form-item">
              <div class="type-icon-picker" role="radiogroup" aria-label="选择一级分类图标">
                <button
                  v-for="item in productTypeIconOptions"
                  :key="item.value"
                  type="button"
                  class="type-icon-option"
                  :class="{ active: normalizedTypeFormIcon === item.value }"
                  role="radio"
                  :aria-checked="normalizedTypeFormIcon === item.value"
                  @click="selectTypeIcon(item.value)"
                >
                  <t-icon :name="item.value" />
                  <span>{{ item.label }}</span>
                </button>
              </div>
            </t-form-item>
            <t-form-item class="type-form-actions">
              <t-button theme="primary" :loading="submitting" @click="handleSubmit">
                <template #icon><add-icon v-if="!editingTypeValue" /><edit-icon v-else /></template>
                {{ editingTypeValue ? '保存' : '新建' }}
              </t-button>
              <t-button variant="outline" @click="resetTypeForm">{{ editingTypeValue ? '取消编辑' : '重置' }}</t-button>
            </t-form-item>
          </div>
        </t-form>
      </section>

      <div class="type-manager-list">
        <div class="type-manager-list-head">
          <div>
            <strong>分类列表</strong>
            <span>{{ productTypes.length }} 个分类 · {{ productCount }} 个商品 · {{ hiddenCount }} 个隐藏</span>
          </div>
          <t-button size="small" variant="outline" :loading="loading" @click="emit('refresh')">
            <template #icon><refresh-icon /></template>
            刷新
          </t-button>
        </div>

        <div class="type-manager-items">
          <article
            v-for="(item, index) in productTypes"
            :key="item.value"
            class="type-manager-item"
            :class="{ 'is-hidden': item.is_hidden, 'is-editing': editingTypeValue === item.value }"
          >
            <span class="type-manager-item__order">{{ index + 1 }}</span>
            <div class="type-manager-item__main">
              <div class="type-manager-item__title">
                <strong>{{ item.label }}</strong>
                <t-tag v-if="item.is_hidden" theme="warning" variant="light">隐藏</t-tag>
                <t-tag v-if="editingTypeValue === item.value" theme="primary" variant="light">编辑中</t-tag>
              </div>
              <div class="type-manager-item__meta">
                <span>{{ normalizeBusinessProductType(item.product_type) }}</span>
                <span v-if="item.icon" class="type-manager-item__icon-meta">
                  <t-icon :name="resolveProductTypeIconName(item.icon)" />
                  {{ productTypeIconDisplayLabel(item.icon) }}
                </span>
              </div>
            </div>
            <dl class="type-manager-item__stats">
              <div>
                <dt>商品</dt>
                <dd>{{ item.usage_count || 0 }}</dd>
              </div>
              <div>
                <dt>分组</dt>
                <dd>{{ item.group_count || 0 }}</dd>
              </div>
            </dl>
            <div class="type-manager-actions">
              <div class="type-manager-order-actions">
                <t-tooltip content="上移">
                  <t-button
                    size="small"
                    shape="square"
                    variant="outline"
                    aria-label="上移一级分类"
                    :disabled="index === 0 || submitting"
                    @click="moveType(item.value, -1)"
                  >
                    <template #icon><arrow-up-icon /></template>
                  </t-button>
                </t-tooltip>
                <t-tooltip content="下移">
                  <t-button
                    size="small"
                    shape="square"
                    variant="outline"
                    aria-label="下移一级分类"
                    :disabled="index === productTypes.length - 1 || submitting"
                    @click="moveType(item.value, 1)"
                  >
                    <template #icon><arrow-down-icon /></template>
                  </t-button>
                </t-tooltip>
              </div>
              <t-tooltip content="编辑">
                <t-button
                  size="small"
                  shape="square"
                  variant="text"
                  theme="primary"
                  aria-label="编辑一级分类"
                  :disabled="submitting"
                  @click="editType(item)"
                >
                  <template #icon><edit-icon /></template>
                </t-button>
              </t-tooltip>
              <t-tooltip :content="item.is_hidden ? '显示' : '隐藏'">
                <t-button
                  size="small"
                  shape="square"
                  variant="text"
                  aria-label="切换一级分类显示状态"
                  :disabled="submitting"
                  @click="toggleTypeHidden(item)"
                >
                  <template #icon><browse-icon v-if="item.is_hidden" /><browse-off-icon v-else /></template>
                </t-button>
              </t-tooltip>
              <t-tooltip content="删除">
                <t-button
                  size="small"
                  shape="square"
                  variant="text"
                  theme="danger"
                  aria-label="删除一级分类"
                  :disabled="submitting"
                  @click="deleteType(item, resetTypeForm)"
                >
                  <template #icon><delete-icon /></template>
                </t-button>
              </t-tooltip>
            </div>
          </article>
        </div>
        <t-empty v-if="productTypes.length === 0" description="暂无一级分类" />
      </div>
      <div class="type-manager-footer">
        <t-button variant="outline" @click="emit('update:visible', false)">关闭</t-button>
      </div>
    </div>
  </t-dialog>
</template>
<script setup lang="ts">
import {
  AddIcon,
  ArrowDownIcon,
  ArrowUpIcon,
  BrowseIcon,
  BrowseOffIcon,
  DeleteIcon,
  EditIcon,
  RefreshIcon,
} from 'tdesign-icons-vue-next';
import { computed, reactive, ref, watch } from 'vue';

import type { ProductTypeRecord } from '@/api/product';

import {
  businessProductTypeOptions,
  normalizeBusinessProductType,
  productTypeIconDisplayLabel,
  productTypeIconOptions,
  resolveProductTypeIconName,
} from '../composables/useProductTypeManager';

/**
 * 一级分类管理对话框。
 * 编辑态表单由组件内部持有；列表数据与增删改请求通过 props 由宿主注入（宿主来自 useProductTypeManager）。
 */
const props = defineProps<{
  visible: boolean;
  productTypes: ProductTypeRecord[];
  loading: boolean;
  submitting: boolean;
  productCount: number;
  hiddenCount: number;
  submitType: (payload: { value: string; label: string; product_type: string; icon: string }) => Promise<boolean>;
  toggleTypeHidden: (type: ProductTypeRecord) => Promise<void>;
  moveType: (value: string, direction: -1 | 1) => Promise<void>;
  deleteType: (type: ProductTypeRecord, onDeleted?: () => void) => void;
}>();

const emit = defineEmits<{
  (event: 'update:visible', value: boolean): void;
  (event: 'refresh'): void;
}>();

const editingTypeValue = ref('');
const typeForm = reactive({
  label: '',
  product_type: 'cloud_server',
  icon: '',
});
const normalizedTypeFormIcon = computed(() => resolveProductTypeIconName(typeForm.icon));

function selectTypeIcon(value: string) {
  typeForm.icon = value;
}

function resetTypeForm() {
  editingTypeValue.value = '';
  typeForm.label = '';
  typeForm.product_type = 'cloud_server';
  typeForm.icon = '';
}

function editType(type: ProductTypeRecord) {
  editingTypeValue.value = type.value;
  typeForm.label = type.label;
  typeForm.product_type = normalizeBusinessProductType(type.product_type);
  typeForm.icon = resolveProductTypeIconName(type.icon);
}

async function handleSubmit() {
  const saved = await props.submitType({
    value: editingTypeValue.value,
    label: typeForm.label,
    product_type: typeForm.product_type,
    icon: typeForm.icon,
  });
  if (saved) resetTypeForm();
}

// 打开弹窗时重置编辑表单，等价于原 openTypeManagerDialog() 内的 resetTypeForm()
watch(
  () => props.visible,
  (visible) => {
    if (visible) resetTypeForm();
  },
);
</script>
