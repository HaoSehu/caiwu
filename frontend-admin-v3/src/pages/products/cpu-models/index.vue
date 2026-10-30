<template>
  <div class="cpu-models-page">
    <div class="page-inline-actions">
      <t-button v-if="canManage" theme="primary" :loading="saving" @click="handleSave">保存目录</t-button>
    </div>

    <t-alert v-if="dirty" theme="warning" message="目录有未保存的修改，请点「保存目录」后生效。" />

    <div class="cpu-layout">
      <t-card :bordered="false">
        <div class="group-head">
          <strong>CPU 分组</strong>
          <t-button v-if="canManage" theme="primary" variant="outline" @click="openGroupDialog()">
            <template #icon><add-icon /></template>
            新增分组
          </t-button>
        </div>

        <div v-if="groups.length" class="cpu-group-list">
          <article
            v-for="group in groups"
            :key="group.id"
            class="cpu-group-item"
            :class="{ active: activeGroupId === group.id }"
          >
            <button type="button" @click="activeGroupId = group.id">
              <strong>{{ group.name || '-' }}</strong>
              <span>{{ group.model_count || group.models.length }} 个型号</span>
            </button>
            <t-space v-if="canManage" size="small">
              <t-button size="small" variant="text" theme="primary" @click="openGroupDialog(group)">编辑</t-button>
              <t-button size="small" variant="text" theme="danger" @click="handleDeleteGroup(group)">删除</t-button>
            </t-space>
          </article>
        </div>
        <t-empty v-else description="还没有 CPU 分组">
          <template #action>
            <t-button v-if="canManage" theme="primary" @click="openGroupDialog()">新增第一个分组</t-button>
          </template>
        </t-empty>
      </t-card>

      <t-card :bordered="false">
        <div class="model-head">
          <div>
            <h2>CPU 型号</h2>
            <p>{{ currentGroup ? `当前分组：${currentGroup.name}` : '请先选择左侧 CPU 分组' }}</p>
          </div>
          <t-button
            v-if="canManage"
            theme="primary"
            variant="outline"
            :disabled="!currentGroup"
            @click="openModelDialog()"
          >
            <template #icon><add-icon /></template>
            新增型号
          </t-button>
        </div>

        <template v-if="currentGroup">
          <div class="table-scroll">
            <t-table
              row-key="id"
              :data="currentGroup.models"
              :columns="columns"
              :loading="loading"
              hover
              table-layout="fixed"
              aria-label="CPU 型号列表"
            >
              <template #empty>
                <t-empty :description="loading ? '加载中…' : '暂无 CPU 型号'" />
              </template>
              <template #frequency="{ row }">
                <div class="frequency-summary">
                  <span>主频 {{ displayFrequency(row.base_frequency) }}</span>
                  <span>睿频 {{ displayFrequency(row.turbo_frequency) }}</span>
                </div>
              </template>
              <template #bindings="{ row }">
                <product-binding-tree-select
                  v-if="canManage"
                  v-model="row.binding_ids"
                  mode="batch"
                  :existing-bindings="row.bindings"
                  @change="handleBindingSelectionChange(row, $event)"
                />
                <span v-else class="muted">{{
                  row.bindings.length ? `已绑定 ${row.bindings.length} 项` : '未绑定'
                }}</span>
              </template>
              <template #operation="{ row }">
                <t-space v-if="canManage" size="small">
                  <t-button size="small" variant="text" theme="primary" @click="openModelDialog(row)">编辑</t-button>
                  <t-button size="small" variant="text" theme="danger" @click="handleDeleteModel(row)">删除</t-button>
                </t-space>
              </template>
            </t-table>
          </div>
          <p class="cpu-footer">
            共 {{ currentGroup.models.length }} 个 CPU 型号。当前页面保存的是目录配置，不会自动改动商品现有配置项。
          </p>
        </template>
        <t-empty v-else description="请选择左侧 CPU 分组" />
      </t-card>
    </div>

    <t-dialog
      v-model:visible="groupDialogVisible"
      :header="editingGroupId ? '编辑 CPU 分组' : '新建 CPU 分组'"
      width="min(520px, calc(100vw - 32px))"
      :confirm-btn="{ content: '确定', loading: groupSubmitting }"
      @confirm="handleSubmitGroup"
    >
      <t-form ref="groupFormRef" :data="groupForm" :rules="groupRules" label-align="right">
        <t-form-item label="分组名称" name="name">
          <t-input v-model="groupForm.name" maxlength="80" placeholder="例如：Intel Xeon E5 / AMD EPYC" />
        </t-form-item>
        <t-form-item label="内部标识" name="value">
          <t-input v-model="groupForm.value" maxlength="60" placeholder="可选，留空将自动生成" />
        </t-form-item>
      </t-form>
    </t-dialog>

    <t-dialog
      v-model:visible="modelDialogVisible"
      :header="editingModelId ? '编辑 CPU 型号' : '新建 CPU 型号'"
      width="min(520px, calc(100vw - 32px))"
      :confirm-btn="{ content: '确定', loading: modelSubmitting }"
      @confirm="handleSubmitModel"
    >
      <t-form ref="modelFormRef" :data="modelForm" :rules="modelRules" label-align="right">
        <t-form-item label="型号名称" name="name">
          <t-input v-model="modelForm.name" maxlength="80" placeholder="例如：Intel Xeon Gold 6133" />
        </t-form-item>
        <t-form-item label="内部标识" name="value">
          <t-input v-model="modelForm.value" maxlength="60" placeholder="可选，留空将自动生成" />
        </t-form-item>
        <t-form-item label="主频" name="base_frequency">
          <t-input v-model="modelForm.base_frequency" maxlength="40" placeholder="例如：2.50">
            <template #suffix>{{ FREQUENCY_UNIT }}</template>
          </t-input>
        </t-form-item>
        <t-form-item label="睿频" name="turbo_frequency">
          <t-input v-model="modelForm.turbo_frequency" maxlength="40" placeholder="例如：3.20">
            <template #suffix>{{ FREQUENCY_UNIT }}</template>
          </t-input>
        </t-form-item>
      </t-form>
    </t-dialog>
  </div>
</template>
<script setup lang="ts">
import './index.less';

import { AddIcon } from 'tdesign-icons-vue-next';
import type { FormInstanceFunctions, FormRule, PrimaryTableCol } from 'tdesign-vue-next';
import { DialogPlugin } from 'tdesign-vue-next/es/dialog/plugin';
import { MessagePlugin } from 'tdesign-vue-next/es/message/plugin';
import { computed, onMounted, reactive, ref } from 'vue';

import type { CpuModelGroupRecord, CpuModelRecord, ProductBindingRecord } from '@/api/admin';
import { adminApi } from '@/api/admin';
import ProductBindingTreeSelect from '@/components/product-binding-tree-select/index.vue';
import { AdminPermissions } from '@/constants/permissions';
import { useListPage } from '@/hooks/useListPage';
import { normalizeProductBindings as normalizeBindings } from '@/hooks/useProductBindingTree';
import { useUnsavedGuard } from '@/hooks/useUnsavedGuard';
import { hasAdminPermission } from '@/utils/permission';
import { errorMessage } from '@/utils/userMessage';

defineOptions({ name: 'AdminCpuModels' });

// CPU 目录与实例规格一样走 PRODUCT_MANAGE 写权限，只读岗看不到写入口
const canManage = computed(() => hasAdminPermission(AdminPermissions.PRODUCT_MANAGE));

const FREQUENCY_UNIT = 'GHz';

type CpuModel = Omit<
  CpuModelRecord,
  'id' | 'value' | 'name' | 'base_frequency' | 'turbo_frequency' | 'sort_order' | 'bindings' | 'binding_ids'
> & {
  id: string;
  value: string;
  name: string;
  base_frequency: string;
  turbo_frequency: string;
  sort_order: number;
  bindings: ProductBindingRecord[];
  binding_ids: string[];
};

type CpuGroup = Omit<CpuModelGroupRecord, 'id' | 'value' | 'name' | 'sort_order' | 'model_count' | 'models'> & {
  id: string;
  value: string;
  name: string;
  sort_order: number;
  model_count: number;
  models: CpuModel[];
};

const saving = ref(false);
// 分组/型号的增删改只落在本地数组，必须显式「保存目录」才落库，
// 因此用 dirty 标记把「已生效」与「待保存」区分开（docs/DESIGN.md §4.4 状态可感知）。
const dirty = ref(false);
useUnsavedGuard(() => dirty.value, {
  message: 'CPU 型号目录有未保存的修改，离开后这些改动会丢失。',
});
const groupSubmitting = ref(false);
const modelSubmitting = ref(false);
const activeGroupId = ref('');

// CPU 目录是「拉取一次 + 本地编辑 + 显式保存」的双栏编辑器，接列表引擎只取请求竞态、
// 统一错误提示与三态空文案；无筛选、无分页器（后端 CpuModelCatalogController 恒返回 page=1）。
// immediate: false —— 首屏加载统一走 loadCatalog，避免与下方 onMounted 双发。
const {
  list: groups,
  loading,
  loadList,
} = useListPage<Record<string, never>, CpuGroup>({
  defaultFilters: {},
  immediate: false,
  onError: (error) => MessagePlugin.error(errorMessage(error, '加载 CPU 型号目录失败')),
  fetch: async () => {
    const response = await adminApi.cpuModelCatalog.list();
    const list = (response.list || []).map((item, index) => normalizeGroup(item, index));
    return { list, total: list.length };
  },
  afterLoad: (list) => {
    syncActiveGroup(list);
    dirty.value = false;
  },
});
const groupDialogVisible = ref(false);
const modelDialogVisible = ref(false);
const editingGroupId = ref('');
const editingModelId = ref('');
const groupFormRef = ref<FormInstanceFunctions>();
const modelFormRef = ref<FormInstanceFunctions>();

const groupForm = reactive({ name: '', value: '' });
const modelForm = reactive({ name: '', value: '', base_frequency: '', turbo_frequency: '' });

const groupRules: Record<string, FormRule[]> = {
  name: [{ required: true, message: '请输入 CPU 分组名称', type: 'error' }],
};
const modelRules: Record<string, FormRule[]> = {
  name: [{ required: true, message: '请输入 CPU 型号名称', type: 'error' }],
};

const columns: PrimaryTableCol<CpuModel>[] = [
  { colKey: 'name', title: '型号名称', minWidth: 220 },
  { colKey: 'frequency', title: '主频 / 睿频', minWidth: 180 },
  { colKey: 'value', title: '内部标识', minWidth: 180 },
  { colKey: 'sort_order', title: '排序', width: 90 },
  { colKey: 'bindings', title: '绑定配置', minWidth: 360 },
  { colKey: 'operation', title: '操作', width: 140, fixed: 'right' },
];

const currentGroup = computed(() => groups.value.find((item) => item.id === activeGroupId.value) || null);

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

function stripFrequencyUnit(value: unknown) {
  return String(value || '')
    .trim()
    .replace(/\s*GHz$/i, '');
}

function buildFrequencyPayload(value: unknown) {
  const normalized = stripFrequencyUnit(value);
  if (!normalized) return '';
  return /^[+-]?\d+(?:\.\d+)?$/.test(normalized) ? `${normalized}${FREQUENCY_UNIT}` : normalized;
}

function displayFrequency(value: unknown) {
  const normalized = stripFrequencyUnit(value);
  if (!normalized) return '-';
  return /^[+-]?\d+(?:\.\d+)?$/.test(normalized) ? `${normalized}${FREQUENCY_UNIT}` : normalized;
}

function normalizeModel(modelValue: unknown, index = 0): CpuModel {
  const model = toPlainRecord(modelValue);
  const bindings = normalizeBindings(model.bindings);
  const name = String(model.name || '').trim();
  return {
    ...model,
    id: String(model.id || createLocalId('model')),
    value: String(model.value || slugify(name || `model-${index + 1}`)),
    name,
    base_frequency: stripFrequencyUnit(model.base_frequency),
    turbo_frequency: stripFrequencyUnit(model.turbo_frequency),
    sort_order: Number(model.sort_order || index + 1),
    bindings,
    binding_ids: bindings
      .map((binding) => String(binding.product_id || '').trim())
      .filter((id) => /^\d+$/.test(id) && Number(id) > 0),
  };
}

function normalizeGroup(groupValue: unknown, index = 0): CpuGroup {
  const group = toPlainRecord(groupValue);
  const rawModels = Array.isArray(group.models) ? group.models : [];
  const models = rawModels.map((model, modelIndex) => normalizeModel(model, modelIndex));
  const name = String(group.name || '').trim();
  return {
    ...group,
    id: String(group.id || createLocalId('group')),
    value: String(group.value || slugify(name || `group-${index + 1}`)),
    name,
    sort_order: Number(group.sort_order || index + 1),
    model_count: Number(group.model_count || models.length),
    models,
  };
}

/** 选中分组失效时回落到第一个分组，保证右栏始终有内容或明确的空态 */
function syncActiveGroup(list: CpuGroup[]) {
  if (!list.length) {
    activeGroupId.value = '';
    return;
  }
  if (!list.some((item) => item.id === activeGroupId.value)) {
    activeGroupId.value = list[0].id;
  }
}

/** 只在删除分组等本地变更后重建左栏，不走服务端 */
function rebuildCatalogState(list: unknown[]) {
  groups.value = list.map((item, index) => normalizeGroup(item, index));
  syncActiveGroup(groups.value);
}

/** 拉取服务端目录；错误提示与 loading 由列表引擎统一处理 */
function loadCatalog() {
  return loadList();
}

function openGroupDialog(group?: CpuGroup) {
  editingGroupId.value = group?.id || '';
  Object.assign(groupForm, {
    name: group?.name || '',
    value: group?.value || '',
  });
  groupDialogVisible.value = true;
}

function openModelDialog(model?: CpuModel) {
  if (!currentGroup.value) {
    MessagePlugin.warning('请先选择 CPU 分组');
    return;
  }
  editingModelId.value = model?.id || '';
  Object.assign(modelForm, {
    name: model?.name || '',
    value: model?.value || '',
    base_frequency: stripFrequencyUnit(model?.base_frequency),
    turbo_frequency: stripFrequencyUnit(model?.turbo_frequency),
  });
  modelDialogVisible.value = true;
}

async function handleSubmitGroup() {
  const validateResult = await groupFormRef.value?.validate?.();
  if (validateResult !== true) return;
  const name = String(groupForm.name || '').trim();
  const duplicated = groups.value.find((item) => item.name === name && item.id !== editingGroupId.value);
  if (duplicated) {
    MessagePlugin.warning('CPU 分组名称不能重复');
    return;
  }

  groupSubmitting.value = true;
  try {
    if (editingGroupId.value) {
      groups.value = groups.value.map((item, index) =>
        item.id === editingGroupId.value
          ? {
              ...item,
              name,
              value: String(groupForm.value || '').trim() || slugify(name),
              sort_order: index + 1,
            }
          : item,
      );
    } else {
      const nextGroup = normalizeGroup(
        {
          id: createLocalId('group'),
          name,
          value: String(groupForm.value || '').trim() || slugify(name),
          models: [],
        },
        groups.value.length,
      );
      groups.value = [...groups.value, nextGroup];
      activeGroupId.value = nextGroup.id;
    }
    groupDialogVisible.value = false;
    dirty.value = true;
    MessagePlugin.success(
      editingGroupId.value ? 'CPU 分组已修改，请点「保存目录」生效' : 'CPU 分组已添加，请点「保存目录」生效',
    );
  } finally {
    groupSubmitting.value = false;
  }
}

async function handleSubmitModel() {
  const validateResult = await modelFormRef.value?.validate?.();
  if (validateResult !== true) return;
  if (!currentGroup.value) return;

  const name = String(modelForm.name || '').trim();
  const duplicated = currentGroup.value.models.find((item) => item.name === name && item.id !== editingModelId.value);
  if (duplicated) {
    MessagePlugin.warning('同一 CPU 分组下的型号名称不能重复');
    return;
  }

  modelSubmitting.value = true;
  try {
    const groupId = currentGroup.value.id;
    groups.value = groups.value.map((group) => {
      if (group.id !== groupId) return group;
      const nextModel = {
        id: editingModelId.value || createLocalId('model'),
        value: String(modelForm.value || '').trim() || slugify(name),
        name,
        base_frequency: stripFrequencyUnit(modelForm.base_frequency),
        turbo_frequency: stripFrequencyUnit(modelForm.turbo_frequency),
        sort_order: group.models.length + 1,
        bindings: [] as ProductBindingRecord[],
        binding_ids: [] as string[],
      };
      const models = editingModelId.value
        ? group.models.map((item, index) =>
            item.id === editingModelId.value ? { ...item, ...nextModel, sort_order: index + 1 } : item,
          )
        : [...group.models, nextModel];
      return {
        ...group,
        model_count: models.length,
        models,
      };
    });
    modelDialogVisible.value = false;
    dirty.value = true;
    MessagePlugin.success(
      editingModelId.value ? 'CPU 型号已修改，请点「保存目录」生效' : 'CPU 型号已添加，请点「保存目录」生效',
    );
  } finally {
    modelSubmitting.value = false;
  }
}

function handleDeleteGroup(group: CpuGroup) {
  const dialog = DialogPlugin.confirm({
    header: '删除 CPU 分组',
    body: `确认删除「${group.name || group.id}」吗？`,
    theme: 'warning',
    confirmBtn: { content: '确定', theme: 'danger' },
    cancelBtn: '取消',
    onConfirm() {
      rebuildCatalogState(groups.value.filter((item) => item.id !== group.id));
      dirty.value = true;
      MessagePlugin.success('CPU 分组已移除，请点「保存目录」生效');
      dialog.hide();
    },
  });
}

function handleDeleteModel(model: CpuModel) {
  if (!currentGroup.value) return;
  const groupId = currentGroup.value.id;
  const dialog = DialogPlugin.confirm({
    header: '删除 CPU 型号',
    body: `确认删除「${model.name || model.id}」吗？`,
    theme: 'warning',
    confirmBtn: { content: '确定', theme: 'danger' },
    cancelBtn: '取消',
    onConfirm() {
      groups.value = groups.value.map((group) => {
        if (group.id !== groupId) return group;
        const models = group.models
          .filter((item) => item.id !== model.id)
          .map((item, index) => ({ ...item, sort_order: index + 1 }));
        return {
          ...group,
          model_count: models.length,
          models,
        };
      });
      dirty.value = true;
      MessagePlugin.success('CPU 型号已移除，请点「保存目录」生效');
      dialog.hide();
    },
  });
}

function handleBindingSelectionChange(
  row: CpuModel,
  payload: { binding_ids: string[]; bindings: ProductBindingRecord[] },
) {
  row.binding_ids = payload.binding_ids;
  row.bindings = payload.bindings;
  dirty.value = true;
}

function buildPayload(): CpuModelGroupRecord[] {
  return groups.value.map((group, groupIndex) => ({
    id: group.id,
    value: String(group.value || '').trim() || slugify(group.name),
    name: String(group.name || '').trim(),
    sort_order: groupIndex + 1,
    models: group.models.map((model, modelIndex) => ({
      id: model.id,
      value: String(model.value || '').trim() || slugify(model.name),
      name: String(model.name || '').trim(),
      base_frequency: buildFrequencyPayload(model.base_frequency),
      turbo_frequency: buildFrequencyPayload(model.turbo_frequency),
      sort_order: modelIndex + 1,
      bindings: model.bindings,
    })),
  }));
}

async function handleSave() {
  if (!groups.value.length) {
    MessagePlugin.warning('请至少添加一个 CPU 分组');
    return;
  }
  saving.value = true;
  try {
    const response = await adminApi.cpuModelCatalog.save({ list: buildPayload() });
    rebuildCatalogState(response.list || []);
    dirty.value = false;
    MessagePlugin.success('CPU 型号目录已保存');
  } catch (error) {
    MessagePlugin.error(errorMessage(error, '保存 CPU 型号目录失败'));
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
