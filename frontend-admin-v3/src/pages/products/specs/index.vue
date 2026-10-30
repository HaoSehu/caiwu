<template>
  <div class="specs-page">
    <div class="page-inline-actions">
      <t-button v-if="canManage" theme="primary" :loading="saving" @click="handleSave">保存目录</t-button>
    </div>

    <t-alert v-if="dirty" theme="warning" message="规格目录有未保存的修改，请点「保存目录」后生效。" />

    <t-card :bordered="false">
      <div class="specs-filter">
        <t-input
          v-model="filters.keyword"
          clearable
          placeholder="搜索规格文本 / 别名 / 说明 / 绑定配置"
          @enter="requestReload"
          @clear="requestReload"
        >
          <template #suffix-icon><search-icon /></template>
        </t-input>
        <t-select v-model="filters.binding_status" clearable placeholder="绑定状态" @change="requestReload">
          <t-option value="bound" label="已绑定" />
          <t-option value="unbound" label="未绑定" />
        </t-select>
        <t-button theme="primary" @click="requestReload">查询</t-button>
        <t-button theme="default" variant="base" @click="resetFilters">重置</t-button>
        <t-button v-if="canManage" theme="primary" variant="outline" @click="openSpecDialog()">
          <template #icon><add-icon /></template>
          新增规格
        </t-button>
      </div>

      <div class="table-scroll">
        <t-table
          row-key="id"
          :data="specs"
          :columns="columns"
          :loading="loading"
          hover
          table-layout="fixed"
          aria-label="实例规格列表"
        >
          <template #empty>
            <t-empty :description="loading ? '加载中…' : hasActiveFilters ? '未找到匹配结果' : '暂无数据'" />
          </template>
          <template #spec="{ row }">
            <div class="spec-cell">
              <strong>{{ row.text || '-' }}</strong>
              <span>{{ row.alias || '未填写别名' }}</span>
            </div>
          </template>
          <template #note="{ row }">
            <span class="muted">{{ row.note || '-' }}</span>
          </template>
          <template #status="{ row }">
            <t-tag :theme="row.status === '隐藏' ? 'default' : 'success'" variant="light">
              {{ row.status || '展示中' }}
            </t-tag>
          </template>
          <template #bindings="{ row }">
            <product-binding-tree-select
              v-if="canManage"
              v-model="row.binding_ids"
              mode="batch"
              :existing-bindings="row.bindings"
              @change="handleBindingSelectionChange(row, $event)"
            />
            <span v-else class="muted">{{ row.bindings.length ? `已绑定 ${row.bindings.length} 项` : '未绑定' }}</span>
          </template>
          <template #operation="{ row }">
            <t-space v-if="canManage" size="small">
              <t-button size="small" variant="text" theme="primary" @click="openSpecDialog(row)">编辑</t-button>
              <t-button size="small" variant="text" theme="danger" @click="handleDeleteSpec(row)">删除</t-button>
            </t-space>
            <span v-else class="muted">只读</span>
          </template>
        </t-table>
      </div>

      <p class="specs-footer">规格目录会统一保存到后台设置中，绑定配置仅保存关联信息。</p>
    </t-card>

    <t-dialog
      v-model:visible="specDialogVisible"
      :header="editingSpecId ? '编辑实例规格' : '新建实例规格'"
      width="min(540px, calc(100vw - 32px))"
      :confirm-btn="{ content: '确定', loading: dialogSubmitting }"
      @confirm="handleSubmitSpec"
      @closed="handleDialogClosed"
    >
      <t-form ref="specFormRef" :data="specForm" :rules="specRules" label-align="right">
        <t-form-item label="实例规格文本" name="text">
          <t-input v-model="specForm.text" maxlength="80" placeholder="例如：ecs.g9i.2c2g" />
        </t-form-item>
        <t-form-item label="别名" name="alias">
          <t-input v-model="specForm.alias" maxlength="80" placeholder="例如：2 核 2G" />
        </t-form-item>
        <t-form-item label="说明" name="note">
          <t-textarea
            v-model="specForm.note"
            :autosize="{ minRows: 3, maxRows: 5 }"
            maxlength="255"
            placeholder="例如：入门款实例规格"
          />
        </t-form-item>
        <t-form-item label="状态" name="status">
          <t-select v-model="specForm.status">
            <t-option value="展示中" label="展示中" />
            <t-option value="隐藏" label="隐藏" />
          </t-select>
        </t-form-item>
      </t-form>
    </t-dialog>
  </div>
</template>
<script setup lang="ts">
import './index.less';

import { AddIcon, SearchIcon } from 'tdesign-icons-vue-next';
import type { FormInstanceFunctions, FormRule, PrimaryTableCol } from 'tdesign-vue-next';
import { DialogPlugin } from 'tdesign-vue-next/es/dialog/plugin';
import { MessagePlugin } from 'tdesign-vue-next/es/message/plugin';
import { computed, onMounted, reactive, ref } from 'vue';

import type { InstanceSpecRecord, ProductBindingRecord } from '@/api/admin';
import { adminApi } from '@/api/admin';
import ProductBindingTreeSelect from '@/components/product-binding-tree-select/index.vue';
import { AdminPermissions } from '@/constants/permissions';
import { useListPage } from '@/hooks/useListPage';
import { normalizeProductBindings } from '@/hooks/useProductBindingTree';
import { useUnsavedGuard } from '@/hooks/useUnsavedGuard';
import { hasAdminPermission } from '@/utils/permission';
import { errorMessage } from '@/utils/userMessage';

defineOptions({ name: 'AdminSpecs' });

type SpecRecord = Omit<
  InstanceSpecRecord,
  'id' | 'value' | 'text' | 'alias' | 'note' | 'status' | 'sort_order' | 'bindings' | 'binding_ids'
> & {
  id: string;
  value: string;
  text: string;
  alias: string;
  note: string;
  status: string;
  sort_order: number;
  bindings: ProductBindingRecord[];
  binding_ids: string[];
};

// 规格目录写入后再保存，缺少按钮级门禁等于让只读角色改商品绑定
const canManage = computed(() => hasAdminPermission(AdminPermissions.PRODUCT_MANAGE));

const saving = ref(false);
const dialogSubmitting = ref(false);
// 新增/编辑/删除只改本地数组，必须显式「保存目录」才落库
const dirty = ref(false);
useUnsavedGuard(() => dirty.value, {
  message: '实例规格目录有未保存的修改，离开后这些改动会丢失。',
});
const specDialogVisible = ref(false);
const editingSpecId = ref('');
const specFormRef = ref<FormInstanceFunctions>();

interface SpecFilter {
  keyword: string;
  binding_status: string;
}

// 规格目录是「拉取一次 + 本地编辑 + 显式保存」的编辑器，接列表引擎只取请求竞态、
// 统一错误提示与三态空文案；分页器不渲染（后端 InstanceSpecCatalogController 恒返回 page=1）。
// immediate: false —— 首屏加载走下方 loadCatalog，与「有未保存修改时先确认」的入口保持一致。
const {
  filters,
  list: specs,
  loading,
  loadList,
  hasActiveFilters,
} = useListPage<SpecFilter, SpecRecord>({
  defaultFilters: { keyword: '', binding_status: '' },
  immediate: false,
  onError: (error) => MessagePlugin.error(errorMessage(error, '加载实例规格失败')),
  fetch: async (params) => {
    const response = await adminApi.instanceSpecCatalog.list({
      keyword: String(params.keyword || '').trim() || undefined,
      binding_status: params.binding_status || undefined,
    });
    const list = (response.list || []).map((item, index) => normalizeSpec(item, index));
    return { list, total: list.length };
  },
  afterLoad: () => {
    dirty.value = false;
  },
});

const specForm = reactive({
  text: '',
  value: '',
  alias: '',
  note: '',
  status: '展示中',
});

const specRules: Record<string, FormRule[]> = {
  text: [{ required: true, message: '请输入实例规格文本', type: 'error' }],
};

const columns: PrimaryTableCol<SpecRecord>[] = [
  { colKey: 'spec', title: '实例规格文本', minWidth: 220 },
  { colKey: 'note', title: '说明', minWidth: 220, ellipsis: true },
  { colKey: 'status', title: '状态', width: 110 },
  { colKey: 'bindings', title: '绑定配置', minWidth: 360 },
  { colKey: 'operation', title: '操作', width: 150, fixed: 'right' },
];

function createLocalId(prefix: string) {
  return `${prefix}_${Date.now()}_${Math.random().toString(36).slice(2, 8)}`;
}

function slugify(value: unknown) {
  return String(value || '')
    .trim()
    .toLowerCase()
    .replace(/[^a-z0-9\u4E00-\u9FA5]+/g, '_')
    .replace(/^_+|_+$/g, '')
    .slice(0, 60);
}

function normalizeSpec(itemValue: unknown, index = 0): SpecRecord {
  const item = toPlainRecord(itemValue);
  const bindings = normalizeProductBindings(item.bindings);
  const text = String(item.text || item.name || '').trim();
  return {
    ...item,
    id: String(item.id || createLocalId('spec')),
    value: String(item.value || slugify(text || item.alias || `spec-${index + 1}`)),
    text,
    alias: String(item.alias || '').trim(),
    note: String(item.note || '').trim(),
    status: String(item.status || '展示中').trim() || '展示中',
    sort_order: Number(item.sort_order || index + 1),
    bindings,
    binding_ids: bindings
      .map((binding) => String(binding.product_id || '').trim())
      .filter((id) => /^\d+$/.test(id) && Number(id) > 0),
  };
}

/** 重置筛选并重新拉取；提交式筛选必须成对提供「查询/重置」（docs/DESIGN.md §5.1） */
function resetFilters() {
  filters.keyword = '';
  filters.binding_status = '';
  requestReload();
}

/** 筛选会重拉服务端数据并丢弃本地改动，未保存时先确认（docs/DESIGN.md §4.4 长表单离开保护） */
function requestReload() {
  if (!dirty.value) {
    void loadCatalog();
    return;
  }
  const dialog = DialogPlugin.confirm({
    header: '放弃未保存的修改',
    body: '规格目录有未保存的修改，继续会重新从服务端加载并丢弃这些修改。',
    theme: 'warning',
    confirmBtn: { content: '丢弃并继续', theme: 'danger' },
    cancelBtn: '取消',
    onConfirm() {
      dialog.hide();
      void loadCatalog();
    },
  });
}

/** 拉取服务端目录；错误提示与 loading 由列表引擎统一处理 */
function loadCatalog() {
  return loadList();
}

function openSpecDialog(spec?: SpecRecord) {
  editingSpecId.value = spec?.id || '';
  Object.assign(specForm, {
    text: spec?.text || '',
    value: spec?.value || '',
    alias: spec?.alias || '',
    note: spec?.note || '',
    status: spec?.status || '展示中',
  });
  specDialogVisible.value = true;
}

function handleDialogClosed() {
  specFormRef.value?.clearValidate?.();
}

async function handleSubmitSpec() {
  const validateResult = await specFormRef.value?.validate?.();
  if (validateResult !== true) return;

  const text = String(specForm.text || '').trim();
  const duplicated = specs.value.find((item) => item.text === text && item.id !== editingSpecId.value);
  if (duplicated) {
    MessagePlugin.warning('实例规格文本不能重复');
    return;
  }

  dialogSubmitting.value = true;
  try {
    const nextValue = String(specForm.value || '').trim() || slugify(text);
    if (editingSpecId.value) {
      specs.value = specs.value.map((item, index) =>
        item.id === editingSpecId.value
          ? {
              ...item,
              text,
              value: nextValue,
              alias: String(specForm.alias || '').trim(),
              note: String(specForm.note || '').trim(),
              status: String(specForm.status || '展示中').trim() || '展示中',
              sort_order: index + 1,
            }
          : item,
      );
    } else {
      specs.value = [
        ...specs.value,
        normalizeSpec(
          {
            id: createLocalId('spec'),
            text,
            value: nextValue,
            alias: specForm.alias,
            note: specForm.note,
            status: specForm.status,
            bindings: [],
          },
          specs.value.length,
        ),
      ];
    }
    dirty.value = true;
    MessagePlugin.success(
      editingSpecId.value ? '实例规格已修改，请点「保存目录」生效' : '实例规格已添加，请点「保存目录」生效',
    );
    specDialogVisible.value = false;
  } finally {
    dialogSubmitting.value = false;
  }
}

function handleDeleteSpec(row: SpecRecord) {
  const dialog = DialogPlugin.confirm({
    header: '删除实例规格',
    body: `确认删除「${row.text || row.id}」吗？`,
    theme: 'warning',
    confirmBtn: { content: '确定', theme: 'danger' },
    cancelBtn: '取消',
    onConfirm() {
      specs.value = specs.value.filter((item) => item.id !== row.id);
      dirty.value = true;
      MessagePlugin.success('实例规格已移除，请点「保存目录」生效');
      dialog.hide();
    },
  });
}

function handleBindingSelectionChange(
  row: SpecRecord,
  payload: { binding_ids: string[]; bindings: ProductBindingRecord[] },
) {
  row.bindings = payload.bindings;
  row.binding_ids = payload.binding_ids;
  dirty.value = true;
}

function buildPayload(): InstanceSpecRecord[] {
  return specs.value.map((spec, index) => ({
    id: spec.id,
    value: String(spec.value || '').trim() || slugify(spec.text),
    text: String(spec.text || '').trim(),
    alias: String(spec.alias || '').trim(),
    note: String(spec.note || '').trim(),
    status: String(spec.status || '展示中').trim() || '展示中',
    sort_order: index + 1,
    bindings: spec.bindings,
  }));
}

async function handleSave() {
  if (!specs.value.length) {
    MessagePlugin.warning('请至少添加一个实例规格');
    return;
  }
  saving.value = true;
  try {
    const response = await adminApi.instanceSpecCatalog.save({ list: buildPayload() });
    specs.value = (response.list || []).map((item, index) => normalizeSpec(item, index));
    dirty.value = false;
    MessagePlugin.success('实例规格目录已保存');
  } catch (error) {
    MessagePlugin.error(errorMessage(error, '保存实例规格失败'));
  } finally {
    saving.value = false;
  }
}

function toPlainRecord(value: unknown) {
  return value && typeof value === 'object' ? (value as Record<string, unknown>) : {};
}

onMounted(() => {
  void loadCatalog();
});
</script>
