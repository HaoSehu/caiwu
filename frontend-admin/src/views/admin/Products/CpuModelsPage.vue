<template>
  <div class="page-container admin-page cpu-models-page" v-loading="loading">
    <section class="admin-page-head">
      <div class="admin-page-heading">
        <span class="admin-page-kicker">产品</span>
        <h2>CPU型号管理</h2>
        <p>允许维护 CPU 分组与 CPU 型号，保存后统一写入后台配置目录。</p>
      </div>
      <div class="page-actions">
        <el-button :icon="Refresh" @click="loadCatalog">刷新</el-button>
        <el-button type="primary" :icon="Check" :loading="saving" @click="handleSave">保存目录</el-button>
      </div>
    </section>

    <section class="cpu-models-layout">
      <el-card shadow="never" class="cpu-group-card">
        <template #header>
          <div class="card-head">
            <div>
              <strong>CPU 分组</strong>
              <p>先建立分组，再在右侧维护对应型号。</p>
            </div>
            <el-button type="primary" plain :icon="Plus" @click="openGroupDialog()">新增分组</el-button>
          </div>
        </template>

        <div v-if="groups.length" class="group-list">
          <button
            v-for="group in groups"
            :key="group.id"
            type="button"
            class="group-item"
            :class="{ active: activeGroupId === group.id }"
            @click="activeGroupId = group.id"
          >
            <div class="group-item-main">
              <strong>{{ group.name }}</strong>
              <span>{{ group.model_count || group.models.length }} 个型号</span>
            </div>
            <div class="group-item-actions" @click.stop>
              <el-button size="small" text type="primary" @click="openGroupDialog(group)">编辑</el-button>
              <el-popconfirm title="确认删除该 CPU 分组？" @confirm="handleDeleteGroup(group.id)">
                <template #reference>
                  <el-button size="small" text type="danger">删除</el-button>
                </template>
              </el-popconfirm>
            </div>
          </button>
        </div>
        <el-empty v-else description="还没有 CPU 分组">
          <el-button type="primary" @click="openGroupDialog()">新增第一个分组</el-button>
        </el-empty>
      </el-card>

      <el-card shadow="never" class="cpu-model-card">
        <template #header>
          <div class="card-head">
            <div>
              <strong>CPU 型号</strong>
              <p>{{ currentGroup ? `当前分组：${currentGroup.name}` : '请先选择左侧 CPU 分组' }}</p>
            </div>
            <el-button
              type="primary"
              plain
              :icon="Plus"
              :disabled="!currentGroup"
              @click="openModelDialog()"
            >
              新增型号
            </el-button>
          </div>
        </template>

        <template v-if="currentGroup">
          <el-table :data="currentGroup.models" stripe>
            <el-table-column prop="name" label="型号名称" min-width="220" />
            <el-table-column label="主频 / 睿频" min-width="180">
              <template #default="{ row }">
                <div class="frequency-summary">
                  <span>{{ displayFrequency(row.base_frequency) }}</span>
                  <span>{{ displayFrequency(row.turbo_frequency) }}</span>
                </div>
              </template>
            </el-table-column>
            <el-table-column prop="value" label="内部标识" min-width="180" />
            <el-table-column prop="sort_order" label="排序" width="90" />
            <el-table-column label="绑定配置" min-width="360">
              <template #default="{ row }">
                <div class="binding-inline-cell">
                  <el-cascader
                    v-model="row.binding_ids"
                    class="binding-cascader binding-cascader--inline"
                    :options="bindingTreeData"
                    :props="PRODUCT_BINDING_CASCADER_PROPS"
                    :disabled="bindingTreeLoading || !bindingTreeData.length"
                    filterable
                    clearable
                    :filter-method="filterProductBindingNode"
                    collapse-tags
                    collapse-tags-tooltip
                    :max-collapse-tags="0"
                    show-checked-strategy="child"
                    :show-all-levels="false"
                    placeholder="直接选择绑定配置"
                    @change="(value) => handleBindingSelectionChange(row, value)"
                  />
                </div>
              </template>
            </el-table-column>
            <el-table-column label="操作" :width="isMobile ? 60 : 120" fixed="right">
              <template #default="{ row }">
                <div v-if="!isMobile" class="table-actions">
                  <el-button size="small" text type="primary" @click="openModelDialog(row)">编辑</el-button>
                  <el-popconfirm title="确认删除该 CPU 型号？" @confirm="handleDeleteModel(row.id)">
                    <template #reference>
                      <el-button size="small" text type="danger">删除</el-button>
                    </template>
                  </el-popconfirm>
                </div>
                <el-dropdown v-else trigger="click" @command="(cmd) => handleCpuAction(cmd, row)">
                  <span class="action-link">···</span>
                  <template #dropdown>
                    <el-dropdown-menu>
                      <el-dropdown-item command="edit">编辑</el-dropdown-item>
                      <el-dropdown-item command="delete" divided>删除</el-dropdown-item>
                    </el-dropdown-menu>
                  </template>
                </el-dropdown>
              </template>
            </el-table-column>
          </el-table>

          <div class="table-footer-tip">
            共 {{ currentGroup.models.length }} 个 CPU 型号。当前页面保存的是目录配置，不会自动改动商品现有配置项。
          </div>
        </template>
        <el-empty v-else description="请选择左侧 CPU 分组" />
      </el-card>
    </section>

    <el-dialog
      v-model="groupDialogVisible"
      :title="editingGroupId ? '编辑 CPU 分组' : '新增 CPU 分组'"
      width="520px"
      destroy-on-close
    >
      <el-form ref="groupFormRef" :model="groupForm" :rules="groupRules" label-position="top">
        <el-form-item label="分组名称" prop="name">
          <el-input v-model="groupForm.name" maxlength="80" placeholder="例如：Intel Xeon E5 / AMD EPYC" />
        </el-form-item>
        <el-form-item label="内部标识">
          <el-input v-model="groupForm.value" maxlength="60" placeholder="可选，留空将自动生成" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="groupDialogVisible = false">取消</el-button>
        <el-button type="primary" @click="handleSubmitGroup">确认</el-button>
      </template>
    </el-dialog>

    <el-dialog
      v-model="modelDialogVisible"
      :title="editingModelId ? '编辑 CPU 型号' : '新增 CPU 型号'"
      width="520px"
      destroy-on-close
    >
      <el-form ref="modelFormRef" :model="modelForm" :rules="modelRules" label-position="top">
        <el-form-item label="型号名称" prop="name">
          <el-input v-model="modelForm.name" maxlength="80" placeholder="例如：Intel Xeon Gold 6133" />
        </el-form-item>
        <el-form-item label="内部标识">
          <el-input v-model="modelForm.value" maxlength="60" placeholder="可选，留空将自动生成" />
        </el-form-item>
        <el-form-item label="主频">
          <el-input
            v-model="modelForm.base_frequency"
            maxlength="40"
            placeholder="例如：2.50"
            inputmode="decimal"
          >
            <template #append>
              <span>{{ FREQUENCY_UNIT }}</span>
            </template>
          </el-input>
        </el-form-item>
        <el-form-item label="睿频">
          <el-input
            v-model="modelForm.turbo_frequency"
            maxlength="40"
            placeholder="例如：3.20"
            inputmode="decimal"
          >
            <template #append>
              <span>{{ FREQUENCY_UNIT }}</span>
            </template>
          </el-input>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="modelDialogVisible = false">取消</el-button>
        <el-button type="primary" @click="handleSubmitModel">确认</el-button>
      </template>
    </el-dialog>

  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { Check, Plus, Refresh } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import adminApi from '@/api/admin'
import { useResponsive } from '@/composables/useResponsive'
import {
  PRODUCT_BINDING_CASCADER_PROPS,
  buildProductBindingMap,
  filterProductBindingNode,
  resolveProductBindings,
} from './bindingTreeUtils'
const { isMobile } = useResponsive()
const FREQUENCY_UNIT = 'GHz'

const loading = ref(false)
const saving = ref(false)
const groups = ref([])
const activeGroupId = ref('')

const groupDialogVisible = ref(false)
const modelDialogVisible = ref(false)
const groupFormRef = ref(null)
const modelFormRef = ref(null)
const editingGroupId = ref('')
const editingModelId = ref('')
const bindingTreeLoading = ref(false)
const bindingTreeData = ref([])
const groupForm = ref({
  name: '',
  value: '',
})
const modelForm = ref({
  name: '',
  value: '',
  base_frequency: '',
  turbo_frequency: '',
})

const currentGroup = computed(() => groups.value.find((item) => item.id === activeGroupId.value) || null)
const bindingTreeProductMap = computed(() => buildProductBindingMap(bindingTreeData.value))

const groupRules = {
  name: [{ required: true, message: '请输入 CPU 分组名称', trigger: 'blur' }],
}

const modelRules = {
  name: [{ required: true, message: '请输入 CPU 型号名称', trigger: 'blur' }],
}

function stripFrequencyUnit(value) {
  const text = String(value || '').trim()

  if (!text) {
    return ''
  }

  return text.replace(/\s*GHz$/i, '')
}

function formatFrequency(value) {
  const text = String(value || '').trim()

  if (!text) {
    return '-'
  }

  if (/^[+-]?\d+(?:\.\d+)?$/.test(text)) {
    return `${text}${FREQUENCY_UNIT}`
  }

  return text.replace(/\s*GHz$/i, FREQUENCY_UNIT)
}

function displayFrequency(value) {
  return formatFrequency(value)
}

function buildFrequencyPayload(value) {
  const normalized = stripFrequencyUnit(value)

  if (!normalized) {
    return ''
  }

  if (/^[+-]?\d+(?:\.\d+)?$/.test(normalized)) {
    return `${normalized}${FREQUENCY_UNIT}`
  }

  return normalized
}

function createLocalId(prefix) {
  return `${prefix}_${Date.now()}_${Math.random().toString(36).slice(2, 8)}`
}

function slugify(value) {
  return String(value || '')
    .trim()
    .toLowerCase()
    .replace(/[^a-z0-9\u4e00-\u9fa5]+/g, '_')
    .replace(/^_+|_+$/g, '')
    .slice(0, 60)
}

function normalizeGroup(group, index = 0) {
  const models = Array.isArray(group?.models) ? group.models : []

  return {
    id: String(group?.id || createLocalId('group')),
    name: String(group?.name || '').trim(),
    value: String(group?.value || ''),
    sort_order: Number(group?.sort_order || index + 1),
    model_count: models.length,
    models: models.map((model, modelIndex) => ({
      id: String(model?.id || createLocalId('model')),
      name: String(model?.name || '').trim(),
      value: String(model?.value || ''),
      base_frequency: stripFrequencyUnit(model?.base_frequency),
      turbo_frequency: stripFrequencyUnit(model?.turbo_frequency),
      sort_order: Number(model?.sort_order || modelIndex + 1),
      bindings: normalizeBindings(model?.bindings),
      binding_ids: normalizeBindings(model?.bindings).map((binding) => Number(binding.product_id || 0)),
    })),
  }
}

function normalizeBindings(bindings = []) {
  if (!Array.isArray(bindings)) {
    return []
  }

  const seen = new Set()

  return bindings.reduce((result, item) => {
    const productId = Number(item?.product_id || 0)

    if (productId <= 0 || seen.has(productId)) {
      return result
    }

    seen.add(productId)
    result.push({
      product_id: productId,
      display_name: String(item?.display_name || '').trim(),
      cpu_memory_display: String(item?.cpu_memory_display || '').trim(),
      category_full_name: String(item?.category_full_name || '').trim(),
      primary_price: {
        cycle: String(item?.primary_price?.cycle || ''),
        amount: String(item?.primary_price?.amount || '0.00'),
      },
      status: Number(item?.status || 0) === 1 ? 1 : 0,
    })

    return result
  }, [])
}

function rebuildCatalogState(list = []) {
  groups.value = list.map((item, index) => normalizeGroup(item, index))

  if (!groups.value.length) {
    activeGroupId.value = ''
    return
  }

  if (!groups.value.some((item) => item.id === activeGroupId.value)) {
    activeGroupId.value = groups.value[0].id
  }
}

async function loadCatalog() {
  loading.value = true
  try {
    const response = await adminApi.cpuModelCatalog.list()
    rebuildCatalogState(response.data?.list || [])
  } finally {
    loading.value = false
  }
}

function openGroupDialog(group = null) {
  editingGroupId.value = group?.id || ''
  groupForm.value = {
    name: group?.name || '',
    value: group?.value || '',
  }
  groupDialogVisible.value = true
}

function handleCpuAction(command, row) {
  if (command === 'edit') {
    openModelDialog(row)
  } else if (command === 'delete') {
    handleDeleteModel(row.id)
  }
}

function openModelDialog(model = null) {
  if (!currentGroup.value) {
    ElMessage.warning('请先选择 CPU 分组')
    return
  }

  editingModelId.value = model?.id || ''
  modelForm.value = {
    name: model?.name || '',
    value: model?.value || '',
    base_frequency: stripFrequencyUnit(model?.base_frequency),
    turbo_frequency: stripFrequencyUnit(model?.turbo_frequency),
  }
  modelDialogVisible.value = true
}

async function handleSubmitGroup() {
  await groupFormRef.value?.validate()

  const name = String(groupForm.value.name || '').trim()
  const value = String(groupForm.value.value || '').trim()
  const duplicated = groups.value.find((item) => item.name === name && item.id !== editingGroupId.value)

  if (duplicated) {
    ElMessage.warning('CPU 分组名称不能重复')
    return
  }

  if (editingGroupId.value) {
    groups.value = groups.value.map((item, index) => (
      item.id === editingGroupId.value
        ? {
            ...item,
            name,
            value,
            sort_order: index + 1,
          }
        : item
    ))
  } else {
    const nextGroup = normalizeGroup({
      id: createLocalId('group'),
      name,
      value,
      models: [],
    }, groups.value.length)

    groups.value = [...groups.value, nextGroup]
    activeGroupId.value = nextGroup.id
  }

  rebuildCatalogState(groups.value)
  groupDialogVisible.value = false
  ElMessage.success(editingGroupId.value ? 'CPU 分组已更新' : 'CPU 分组已添加')
}

async function handleSubmitModel() {
  await modelFormRef.value?.validate()

  if (!currentGroup.value) {
    ElMessage.warning('请先选择 CPU 分组')
    return
  }

  const name = String(modelForm.value.name || '').trim()
  const value = String(modelForm.value.value || '').trim()
  const baseFrequency = stripFrequencyUnit(modelForm.value.base_frequency)
  const turboFrequency = stripFrequencyUnit(modelForm.value.turbo_frequency)
  const duplicated = currentGroup.value.models.find((item) => item.name === name && item.id !== editingModelId.value)

  if (duplicated) {
    ElMessage.warning('同一 CPU 分组下的型号名称不能重复')
    return
  }

  const nextGroups = groups.value.map((group) => {
    if (group.id !== currentGroup.value.id) {
      return group
    }

    let nextModels

    if (editingModelId.value) {
      nextModels = group.models.map((item, index) => (
        item.id === editingModelId.value
          ? {
              ...item,
              name,
              value,
              base_frequency: baseFrequency,
              turbo_frequency: turboFrequency,
              sort_order: index + 1,
            }
          : item
      ))
    } else {
      nextModels = [
        ...group.models,
        {
          id: createLocalId('model'),
          name,
          value,
          base_frequency: baseFrequency,
          turbo_frequency: turboFrequency,
          sort_order: group.models.length + 1,
        },
      ]
    }

    return {
      ...group,
      model_count: nextModels.length,
      models: nextModels,
    }
  })

  rebuildCatalogState(nextGroups)
  modelDialogVisible.value = false
  ElMessage.success(editingModelId.value ? 'CPU 型号已更新' : 'CPU 型号已添加')
}

function handleDeleteGroup(groupId) {
  const nextGroups = groups.value.filter((item) => item.id !== groupId)
  rebuildCatalogState(nextGroups)
  ElMessage.success('CPU 分组已删除')
}

function handleDeleteModel(modelId) {
  if (!currentGroup.value) {
    return
  }

  const nextGroups = groups.value.map((group) => {
    if (group.id !== currentGroup.value.id) {
      return group
    }

    const nextModels = group.models
      .filter((item) => item.id !== modelId)
      .map((item, index) => ({
        ...item,
        sort_order: index + 1,
      }))

    return {
      ...group,
      model_count: nextModels.length,
      models: nextModels,
    }
  })

  rebuildCatalogState(nextGroups)
  ElMessage.success('CPU 型号已删除')
}

async function loadBindingTree() {
  bindingTreeLoading.value = true
  try {
    const response = await adminApi.coupons.productTree()
    bindingTreeData.value = Array.isArray(response.data?.tree) ? response.data.tree : []
  } catch (error) {
    ElMessage.error(error?.response?.data?.message || error?.message || '加载商品树失败')
  } finally {
    bindingTreeLoading.value = false
  }
}

function handleBindingSelectionChange(row, value) {
  const nextIds = Array.isArray(value) ? value : []
  const existingMap = new Map(normalizeBindings(row?.bindings).map((item) => [item.product_id, item]))
  const nextBindings = resolveProductBindings(nextIds, bindingTreeProductMap.value, existingMap).sort(
    (left, right) => Number(left.product_id) - Number(right.product_id),
  )

  row.bindings = nextBindings
  row.binding_ids = nextBindings.map((binding) => Number(binding.product_id || 0))
}

function buildPayload() {
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
      bindings: normalizeBindings(model.bindings).map((binding) => ({
        product_id: Number(binding.product_id || 0),
        display_name: String(binding.display_name || '').trim(),
        cpu_memory_display: String(binding.cpu_memory_display || '').trim(),
        category_full_name: String(binding.category_full_name || '').trim(),
        primary_price: {
          cycle: String(binding.primary_price?.cycle || ''),
          amount: String(binding.primary_price?.amount || '0.00'),
        },
        status: Number(binding.status || 0) === 1 ? 1 : 0,
      })),
    })),
  }))
}

async function handleSave() {
  if (!groups.value.length) {
    ElMessage.warning('请至少添加一个 CPU 分组')
    return
  }

  saving.value = true
  try {
    const response = await adminApi.cpuModelCatalog.save({
      list: buildPayload(),
    })
    rebuildCatalogState(response.data?.list || [])
    ElMessage.success('CPU 型号目录已保存')
  } finally {
    saving.value = false
  }
}

onMounted(() => {
  loadCatalog()
  loadBindingTree()
})
</script>

<style lang="scss" scoped>
.cpu-models-layout {
  display: grid;
  grid-template-columns: 320px minmax(0, 1fr);
  gap: 20px;
}

.card-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;

  strong {
    display: block;
    color: $text-color-primary;
    font-size: 16px;
    font-weight: 600;
  }

  p {
    margin: 6px 0 0;
    color: $text-color-secondary;
    font-size: 13px;
    line-height: 1.6;
  }
}

.group-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.group-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  width: 100%;
  padding: 14px 16px;
  border: 1px solid $border-color;
  border-radius: 12px;
  background: #fff;
  text-align: left;
  cursor: pointer;
  transition:
    border-color $duration-fast $ease-standard,
    box-shadow $duration-fast $ease-standard,
    background-color $duration-fast $ease-standard;

  &:hover {
    border-color: rgba($color-primary, 0.35);
    box-shadow: 0 10px 24px rgba(15, 23, 42, 0.06);
  }

  &.active {
    border-color: rgba($color-primary, 0.45);
    background: rgba($color-primary, 0.05);
  }
}

.group-item-main {
  min-width: 0;

  strong {
    display: block;
    color: $text-color-primary;
    font-size: 14px;
    font-weight: 600;
  }

  span {
    display: block;
    margin-top: 4px;
    color: $text-color-secondary;
    font-size: 12px;
  }
}

.group-item-actions {
  flex-shrink: 0;
}

.table-footer-tip {
  margin-top: 14px;
  color: $text-color-secondary;
  font-size: 13px;
  line-height: 1.7;
}

.frequency-summary {
  display: flex;
  flex-direction: column;
  gap: 4px;
  color: $text-color-primary;
  font-size: 13px;
}

.binding-dialog {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.binding-dialog-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
}

.binding-dialog-heading {
  strong {
    display: block;
    color: $text-color-primary;
    font-size: 16px;
    font-weight: 600;
  }

  p {
    margin: 6px 0 0;
    color: $text-color-secondary;
    font-size: 13px;
    line-height: 1.6;
  }
}

.binding-dialog-selected {
  flex-shrink: 0;
  padding: 8px 12px;
  border-radius: 999px;
  background: rgba($color-primary, 0.08);
  color: $color-primary;
  font-size: 13px;
  font-weight: 600;
}

.binding-alert {
  margin: 0;
}

.binding-cascader-space {
  min-height: 320px;
  padding-top: 4px;
}

.binding-cascader {
  width: 100%;
}

.binding-footer-note {
  color: $text-color-secondary;
  font-size: 13px;
  line-height: 1.7;
}

@media (max-width: 960px) {
  .cpu-models-layout {
    grid-template-columns: 1fr;
  }

  .binding-dialog-head {
    flex-direction: column;
    align-items: stretch;
  }

  .binding-dialog-selected {
    align-self: flex-start;
  }
}
</style>
