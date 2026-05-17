<template>
  <div class="page-container admin-page instance-specs-page" v-loading="loading">
    <section class="admin-page-head">
      <div class="admin-page-heading">
        <span class="admin-page-kicker">产品</span>
        <h2>实例规格管理</h2>
        <p>维护绑定机器使用的实例规格文本，并可把商品绑定到对应规格上。</p>
      </div>
      <div class="page-actions">
        <el-button :icon="Refresh" @click="loadCatalog">刷新</el-button>
        <el-button type="primary" plain :icon="Plus" @click="openSpecDialog()">新增规格</el-button>
        <el-button type="primary" :icon="Check" :loading="saving" @click="handleSave">保存目录</el-button>
      </div>
    </section>

    <section class="specs-summary">
      <article v-for="item in summaryCards" :key="item.label" class="specs-summary-card">
        <span>{{ item.label }}</span>
        <strong>{{ item.value }}</strong>
        <small>{{ item.note }}</small>
      </article>
    </section>

    <el-alert class="specs-alert" type="info" :closable="false" show-icon>
      <template #title>
        这里管理的是实例规格文本本身，例如 `ecs.g9i.2c2g`。绑定配置后，只会保存关联关系，不会自动改动原始商品数据。
      </template>
    </el-alert>

    <section class="filter-panel">
      <div class="search-bar specs-toolbar">
        <el-input
          v-model="filters.keyword"
          placeholder="搜索规格文本 / 别名 / 说明 / 绑定配置"
          clearable
          style="width: 320px"
          @keyup.enter="loadCatalog"
          @clear="loadCatalog"
        >
          <template #prefix>
            <el-icon><Search /></el-icon>
          </template>
        </el-input>
        <el-select v-model="filters.binding_status" placeholder="绑定状态" clearable style="width: 140px">
          <el-option label="全部" value="" />
          <el-option label="已绑定" value="bound" />
          <el-option label="未绑定" value="unbound" />
        </el-select>
        <el-button type="primary" :icon="Search" @click="loadCatalog">搜索</el-button>
        <el-button :icon="RefreshLeft" @click="resetFilters">重置</el-button>
      </div>
    </section>

    <el-card shadow="never" class="specs-table-card">
      <el-table :data="specs" v-loading="loading" stripe row-key="id">
        <el-table-column type="index" label="序号" width="72" />

        <el-table-column label="实例规格文本" min-width="220">
          <template #default="{ row }">
            <div class="spec-cell">
              <strong>{{ row.text }}</strong>
              <span>{{ row.alias || '未填写别名' }}</span>
            </div>
          </template>
        </el-table-column>

        <el-table-column label="说明" min-width="260" show-overflow-tooltip>
          <template #default="{ row }">
            <span class="spec-note">{{ row.note || '-' }}</span>
          </template>
        </el-table-column>

        <el-table-column label="状态" width="110">
          <template #default="{ row }">
            <el-tag :type="row.status === '隐藏' ? 'info' : 'success'" effect="plain" size="small">
              {{ row.status }}
            </el-tag>
          </template>
        </el-table-column>

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

        <el-table-column label="操作" :width="isMobile ? 60 : 180" fixed="right">
          <template #default="{ row }">
            <div v-if="!isMobile" class="table-actions">
              <el-button size="small" text type="primary" @click="openSpecDialog(row)">编辑</el-button>
              <el-popconfirm title="确认删除该实例规格？" @confirm="handleDeleteSpec(row.id)">
                <template #reference>
                  <el-button size="small" text type="danger">删除</el-button>
                </template>
              </el-popconfirm>
            </div>
            <el-dropdown v-else trigger="click" @command="(cmd) => handleSpecAction(cmd, row)">
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

      <div class="table-footer">
        <p class="footer-tip">
          规格目录会统一保存到后台设置中，绑定配置仅保存关联信息，不会自动改动原始商品数据。
        </p>
      </div>
    </el-card>

    <el-dialog
      v-model="specDialogVisible"
      :title="editingSpecId ? '编辑实例规格' : '新增实例规格'"
      width="540px"
      destroy-on-close
      @closed="handleSpecDialogClosed"
    >
      <el-form ref="specFormRef" :model="specForm" :rules="specRules" label-position="top">
        <el-form-item label="实例规格文本" prop="text">
          <el-input v-model="specForm.text" maxlength="80" placeholder="例如：ecs.g9i.2c2g" />
        </el-form-item>
        <el-form-item label="别名">
          <el-input v-model="specForm.alias" maxlength="80" placeholder="例如：2 核 2G" />
        </el-form-item>
        <el-form-item label="说明">
          <el-input
            v-model="specForm.note"
            type="textarea"
            :rows="4"
            maxlength="255"
            show-word-limit
            placeholder="例如：入门款实例规格，适合低配展示"
          />
        </el-form-item>
        <el-form-item label="状态">
          <el-select v-model="specForm.status" style="width: 100%">
            <el-option label="展示中" value="展示中" />
            <el-option label="隐藏" value="隐藏" />
          </el-select>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="specDialogVisible = false">取消</el-button>
        <el-button type="primary" @click="handleSubmitSpec">确认</el-button>
      </template>
    </el-dialog>

  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { Check, Plus, Refresh, RefreshLeft, Search } from '@element-plus/icons-vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import adminApi from '@/api/admin'
import { useResponsive } from '@/composables/useResponsive'
import {
  PRODUCT_BINDING_CASCADER_PROPS,
  buildProductBindingMap,
  filterProductBindingNode,
  normalizeProductBindings,
  resolveProductBindings,
} from './bindingTreeUtils'

const { isMobile } = useResponsive()

const loading = ref(false)
const saving = ref(false)
const specs = ref([])

const filters = reactive({
  keyword: '',
  binding_status: '',
})

const specDialogVisible = ref(false)
const specFormRef = ref(null)
const editingSpecId = ref('')
const specForm = ref({
  text: '',
  value: '',
  alias: '',
  note: '',
  status: '展示中',
})

const bindingTreeLoading = ref(false)
const bindingTreeData = ref([])

const specRules = {
  text: [{ required: true, message: '请输入实例规格文本', trigger: 'blur' }],
}

const bindingTreeProductMap = computed(() => buildProductBindingMap(bindingTreeData.value))

const boundProductCount = computed(() => {
  const ids = new Set()

  specs.value.forEach((spec) => {
    normalizeProductBindings(spec.bindings).forEach((binding) => {
      ids.add(Number(binding.product_id || 0))
    })
  })

  return ids.size
})

const summaryCards = computed(() => [
  { label: '实例规格数', value: specs.value.length, note: '当前目录中的规格条目总数' },
  { label: '已绑定规格', value: specs.value.filter((item) => normalizeProductBindings(item.bindings).length > 0).length, note: '至少绑定了一个配置的规格' },
  { label: '绑定配置数', value: boundProductCount.value, note: '去重后的绑定配置数量' },
])

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

function createEmptySpecForm() {
  return {
    text: '',
    value: '',
    alias: '',
    note: '',
    status: '展示中',
  }
}

function normalizeSpec(item, index = 0) {
  const bindings = normalizeProductBindings(item?.bindings)
  return {
    id: String(item?.id || createLocalId('spec')),
    value: String(item?.value || slugify(item?.text || item?.alias || `spec-${index + 1}`)),
    text: String(item?.text || item?.name || '').trim(),
    alias: String(item?.alias || '').trim(),
    note: String(item?.note || '').trim(),
    status: String(item?.status || '展示中').trim() || '展示中',
    sort_order: Number(item?.sort_order || index + 1),
    bindings,
    binding_ids: bindings.map((binding) => Number(binding.product_id || 0)),
  }
}

function rebuildCatalogState(list = []) {
  specs.value = list.map((item, index) => normalizeSpec(item, index))
}

async function loadCatalog() {
  loading.value = true
  try {
    const response = await adminApi.instanceSpecCatalog.list({
      keyword: String(filters.keyword || '').trim() || undefined,
      binding_status: filters.binding_status || undefined,
    })
    rebuildCatalogState(response.data?.list || [])
  } finally {
    loading.value = false
  }
}

function resetFilters() {
  filters.keyword = ''
  filters.binding_status = ''
  loadCatalog()
}

function handleSpecAction(command, row) {
  if (command === 'edit') {
    openSpecDialog(row)
  } else if (command === 'delete') {
    handleDeleteSpec(row.id)
  }
}

function openSpecDialog(spec = null) {
  editingSpecId.value = spec?.id || ''
  specForm.value = spec
    ? {
        text: spec.text || '',
        value: spec.value || '',
        alias: spec.alias || '',
        note: spec.note || '',
        status: spec.status || '展示中',
      }
    : createEmptySpecForm()
  specDialogVisible.value = true
}

function handleSpecDialogClosed() {
  specFormRef.value?.clearValidate?.()
}

async function handleSubmitSpec() {
  await specFormRef.value?.validate()

  const text = String(specForm.value.text || '').trim()
  const alias = String(specForm.value.alias || '').trim()
  const note = String(specForm.value.note || '').trim()
  const status = String(specForm.value.status || '展示中').trim() || '展示中'

  const duplicated = specs.value.find((item) => item.text === text && item.id !== editingSpecId.value)
  if (duplicated) {
    ElMessage.warning('实例规格文本不能重复')
    return
  }

  const nextValue = String(specForm.value.value || '').trim() || slugify(text)

  if (editingSpecId.value) {
    specs.value = specs.value.map((item, index) => (
      item.id === editingSpecId.value
        ? {
            ...item,
            text,
            value: nextValue,
            alias,
            note,
            status,
            sort_order: index + 1,
          }
        : item
    ))
  } else {
    specs.value = [
      ...specs.value,
      normalizeSpec({
        id: createLocalId('spec'),
        text,
        value: nextValue,
        alias,
        note,
        status,
        bindings: [],
      }, specs.value.length),
    ]
  }

  specDialogVisible.value = false
  ElMessage.success(editingSpecId.value ? '实例规格已更新' : '实例规格已添加')
}

async function handleDeleteSpec(specId) {
  try {
    await ElMessageBox.confirm('确认删除该实例规格？', '提示', { type: 'warning' })
  } catch {
    return
  }

  specs.value = specs.value.filter((item) => item.id !== specId)

  ElMessage.success('实例规格已删除')
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
  const existingMap = new Map(normalizeProductBindings(row?.bindings).map((item) => [item.product_id, item]))
  const nextBindings = resolveProductBindings(nextIds, bindingTreeProductMap.value, existingMap).sort(
    (left, right) => Number(left.product_id) - Number(right.product_id),
  )

  row.bindings = nextBindings
  row.binding_ids = nextBindings.map((binding) => Number(binding.product_id || 0))
}

function buildPayload() {
  return specs.value.map((spec, index) => ({
    id: spec.id,
    value: String(spec.value || '').trim() || slugify(spec.text),
    text: String(spec.text || '').trim(),
    alias: String(spec.alias || '').trim(),
    note: String(spec.note || '').trim(),
    status: String(spec.status || '展示中').trim() || '展示中',
    sort_order: index + 1,
    bindings: normalizeProductBindings(spec.bindings).map((binding) => ({
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
  }))
}

async function handleSave() {
  if (!specs.value.length) {
    ElMessage.warning('请至少添加一个实例规格')
    return
  }

  saving.value = true
  try {
    const response = await adminApi.instanceSpecCatalog.save({
      list: buildPayload(),
    })
    rebuildCatalogState(response.data?.list || [])
    ElMessage.success('实例规格目录已保存')
  } finally {
    saving.value = false
  }
}

onMounted(() => {
  loadCatalog()
  loadBindingTree()
})
</script>

<style scoped lang="scss">
.instance-specs-page {
  display: flex;
  flex-direction: column;
}

.specs-summary {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 16px;
  margin-bottom: 16px;
}

.specs-summary-card {
  padding: 16px 18px;
  border: 1px solid $border-color;
  border-radius: $base-border-radius;
  background: $bg-color-card;
  box-shadow: $shadow-xs;
}

.specs-summary-card span {
  display: block;
  color: $text-color-secondary;
  font-size: 12px;
  line-height: 1.2;
}

.specs-summary-card strong {
  display: block;
  margin-top: 8px;
  color: $text-color-primary;
  font-size: 28px;
  font-weight: 600;
  line-height: 1.1;
}

.specs-summary-card small {
  display: block;
  margin-top: 8px;
  color: $text-color-placeholder;
  font-size: 12px;
  line-height: 1.6;
}

.specs-alert {
  margin-bottom: 16px;
}

.specs-table-card {
  :deep(.el-card__body) {
    padding-top: 18px;
  }
}

.spec-cell {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.spec-cell strong {
  color: $text-color-primary;
  font-size: 14px;
  font-weight: 600;
  line-height: 1.4;
}

.spec-cell span,
.spec-note {
  color: $text-color-placeholder;
  font-size: 12px;
  line-height: 1.6;
}

.table-footer {
  margin-top: 14px;
}

.footer-tip {
  margin: 0;
  color: $text-color-secondary;
  font-size: 13px;
  line-height: 1.7;
}

.table-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.binding-inline-cell {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.binding-cascader--inline {
  width: 100%;
}

@media (max-width: 960px) {
  .specs-summary {
    grid-template-columns: 1fr;
  }
}
</style>
