<template>
  <div class="page-container admin-page">
    <section class="admin-page-head">
      <div class="admin-page-heading">
        <span class="admin-page-kicker">产品</span>
        <h2>供应商管理</h2>
        <p>
          总数 {{ summary.total }} · 启用 {{ summary.active }} · 停用 {{ summary.inactive }}
        </p>
      </div>
      <div class="page-actions">
        <el-button :icon="Refresh" @click="loadData">刷新</el-button>
        <el-button type="primary" :icon="Plus" @click="openDialog()">新增接口</el-button>
      </div>
    </section>

    <section class="filter-panel">
    <div class="search-bar">
      <el-input
        v-model="filters.keyword"
        placeholder="搜索接口名称 / 用户名"
        clearable
        style="width: 320px;"
        @keyup.enter="handleSearch"
      >
        <template #prefix>
          <el-icon><Search /></el-icon>
        </template>
      </el-input>
      <el-select v-model="filters.status" placeholder="接口状态" clearable style="width: 140px;">
        <el-option label="启用中" :value="1" />
        <el-option label="已停用" :value="0" />
      </el-select>
      <el-button type="primary" :icon="Search" @click="handleSearch">搜索</el-button>
      <el-button :icon="RefreshLeft" @click="resetFilters">重置</el-button>
    </div>
    </section>

    <el-card shadow="never">
      <el-table :data="list" v-loading="loading" stripe>
        <el-table-column prop="id" label="ID" width="72" />
        <el-table-column label="接口种类" width="150">
          <template #default="{ row }">
            <el-tag size="small" type="primary">
              {{ interfaceTypeLabel(row.interface_type) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="接口信息" min-width="240">
          <template #default="{ row }">
            <div class="supplier-cell">
              <div class="supplier-main">{{ row.name }}</div>
              <div class="supplier-sub">
                {{ canBatchConnect(row) ? '支持商品批量对接' : '请补全接口地址、用户名和 API 密钥后再使用远程能力' }}
              </div>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="接口地址" min-width="140">
          <template #default="{ row }">
            <span>{{ row.has_api_url ? '已隐藏' : '-' }}</span>
          </template>
        </el-table-column>
        <el-table-column label="用户名" min-width="140">
          <template #default="{ row }">
            <span>{{ row.api_username || '-' }}</span>
          </template>
        </el-table-column>
        <el-table-column label="API 密钥" min-width="220" show-overflow-tooltip>
          <template #default="{ row }">
            <span>{{ row.api_key || '-' }}</span>
          </template>
        </el-table-column>
        <el-table-column label="上游余额" min-width="170">
          <template #default="{ row }">
            <div class="balance-cell">
              <strong v-if="row.remote_balance_status === 'success'">¥ {{ row.remote_balance }}</strong>
              <span v-else-if="balanceLoadingMap[row.id] === true" class="balance-muted">同步中...</span>
              <span v-else-if="row.remote_balance_status === 'error'" class="balance-muted">同步失败</span>
              <span v-else-if="row.remote_balance_status === 'disabled'" class="balance-muted">未配置</span>
              <span v-else>-</span>
            </div>
          </template>
        </el-table-column>
        <el-table-column prop="status" label="状态" width="100">
          <template #default="{ row }">
            <el-tag :type="row.status === 1 ? 'success' : 'info'" size="small">
              {{ row.status === 1 ? '启用中' : '已停用' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="最近更新" min-width="160">
          <template #default="{ row }">{{ formatDateTime(row.updated_at) }}</template>
        </el-table-column>
        <el-table-column label="操作" :width="isMobile ? 60 : 200" fixed="right">
          <template #default="{ row }">
            <div v-if="!isMobile" class="table-actions">
              <el-button size="small" text type="primary" @click="openDialog(row)">编辑</el-button>
              <el-button
                size="small"
                text
                type="success"
                :disabled="!canBatchConnect(row)"
                :title="canBatchConnect(row) ? '批量导入并绑定上游商品' : '请先补全接口地址、用户名和 API 密钥'"
                @click="openBatchDialog(row)"
              >
                批量对接
              </el-button>
              <el-button size="small" text type="warning" @click="handleToggleStatus(row)">
                {{ row.status === 1 ? '停用' : '启用' }}
              </el-button>
              <el-popconfirm title="确认删除该接口档案？" @confirm="handleDelete(row.id)">
                <template #reference>
                  <el-button size="small" text type="danger">删除</el-button>
                </template>
              </el-popconfirm>
            </div>
            <el-dropdown v-else trigger="click" @command="(cmd) => handleSupplierAction(cmd, row)">
              <span class="action-link">···</span>
              <template #dropdown>
                <el-dropdown-menu>
                  <el-dropdown-item command="edit">编辑</el-dropdown-item>
                  <el-dropdown-item command="batch">批量对接</el-dropdown-item>
                  <el-dropdown-item command="toggle">{{ row.status === 1 ? '停用' : '启用' }}</el-dropdown-item>
                  <el-dropdown-item command="delete" divided>删除</el-dropdown-item>
                </el-dropdown-menu>
              </template>
            </el-dropdown>
          </template>
        </el-table-column>
      </el-table>

      <div class="table-footer">
        <p class="footer-tip">
          维护自动化接口的基础档案，并可从单个接口内批量拉取上游商品，直接完成本地商品创建或重新绑定。
        </p>
        <el-pagination
          v-model:current-page="page"
          v-model:page-size="pageSize"
          :total="total"
          :page-sizes="[10, 20, 50, 100]"
          layout="total, sizes, prev, pager, next"
          @size-change="loadList"
          @current-change="loadList"
        />
      </div>
    </el-card>

    <el-drawer
      v-model="dialogVisible"
      :title="editingSupplier ? '编辑接口' : '新增接口'"
      direction="rtl"
      size="620px"
      destroy-on-close
      class="supplier-edit-drawer"
      @closed="handleFormDrawerClosed"
    >
      <template #header>
        <div class="drawer-header">
          <strong>{{ supplierDrawerTitle }}</strong>
          <span>{{ supplierDrawerDescription }}</span>
        </div>
      </template>

      <div class="drawer-body">
        <div class="drawer-content">
          <div class="drawer-intro">
            <strong>{{ supplierDrawerTitle }}</strong>
            <p>{{ supplierDrawerIntro }}</p>
          </div>

          <el-form ref="formRef" :model="form" :rules="rules" label-position="top" class="supplier-form">
        <div class="form-grid">
          <el-form-item label="接口种类" prop="interface_type">
            <el-select v-model="form.interface_type" style="width: 100%;">
              <el-option
                v-for="option in interfaceTypeOptions"
                :key="option.value"
                :label="option.label"
                :value="option.value"
              />
            </el-select>
          </el-form-item>
          <el-form-item label="接口名称" prop="name">
            <el-input v-model="form.name" maxlength="120" placeholder="例如：极点云 / 美得云 / 自研对接接口" />
          </el-form-item>
          <el-form-item label="接口地址" prop="api_url">
            <el-input v-model="form.api_url" maxlength="255" placeholder="请输入接口地址" />
          </el-form-item>
          <el-form-item label="用户名" prop="api_username">
            <el-input v-model="form.api_username" maxlength="100" placeholder="请输入用户名" />
          </el-form-item>
          <el-form-item label="API 密钥" prop="api_key" class="is-span-2">
            <el-input
              v-model="form.api_key"
              maxlength="255"
              placeholder="请输入 API 密钥"
            />
          </el-form-item>
          <el-form-item label="上游会话 Cookie（可选）" prop="notes" class="is-span-2">
            <el-input
              v-model="form.notes"
              type="textarea"
              :rows="3"
              maxlength="4000"
              show-word-limit
              placeholder="API 登录不可用时可填：web_session_cookie=ZJMF_xxx=..."
            />
          </el-form-item>
          <el-form-item label="状态" prop="status">
            <el-switch
              v-model="form.status"
              :active-value="1"
              :inactive-value="0"
              active-text="启用"
              inactive-text="停用"
            />
          </el-form-item>
        </div>
          </el-form>
        </div>
      </div>

      <template #footer>
        <div class="drawer-footer">
          <el-button @click="dialogVisible = false">取消</el-button>
          <el-button type="primary" :loading="submitLoading" @click="handleSubmit">保存</el-button>
        </div>
      </template>
    </el-drawer>

    <el-drawer
      v-model="batchDialogVisible"
      :title="batchDialogTitle"
      direction="rtl"
      size="min(1360px, 92vw)"
      destroy-on-close
      class="supplier-batch-drawer"
      @closed="handleBatchDialogClosed"
    >
      <template #header>
        <div class="drawer-header">
          <strong>{{ batchDialogTitle }}</strong>
          <span>{{ batchDrawerDescription }}</span>
        </div>
      </template>

      <div class="drawer-body">
        <div v-loading="batchLoading" class="drawer-content drawer-content--batch">
          <div class="drawer-intro drawer-intro--soft">
            <strong>{{ batchDialogTitle }}</strong>
            <p>{{ batchDrawerDescription }}</p>
          </div>

          <div class="batch-shell">
        <el-alert type="info" :closable="false" show-icon class="batch-alert">
          <template #title>
            已对接商品会执行更新，未对接商品会新建本地记录。请先选择现有目标分类，再在下方筛选并勾选需要对接的上游商品。
          </template>
        </el-alert>

        <div class="batch-overview">
          <div class="batch-overview-item">
            <strong>{{ batchProducts.length }}</strong>
            <span>上游商品</span>
          </div>
          <div class="batch-overview-item">
            <strong>{{ batchPendingCount }}</strong>
            <span>未对接</span>
          </div>
          <div class="batch-overview-item">
            <strong>{{ batchConnectedCount }}</strong>
            <span>已对接</span>
          </div>
          <div class="batch-overview-item">
            <strong>{{ batchForm.product_ids.length }}</strong>
            <span>已选择</span>
          </div>
          <div class="batch-overview-actions">
            <el-button size="small" :loading="batchLoading" @click="reloadBatchProducts">刷新商品</el-button>
          </div>
        </div>

        <el-form
          ref="batchFormRef"
          :model="batchForm"
          :rules="batchRules"
          label-position="top"
          size="small"
          class="supplier-form supplier-form--dense"
        >
          <div class="batch-form-grid">
            <el-form-item label="所属一级菜单 / 导入到现有分类树" prop="product_type" class="is-span-2">
              <el-cascader
                v-model="batchCategorySelection"
                clearable
                filterable
                :options="batchCategoryTreeOptions"
                :props="batchCategoryCascaderProps"
                :show-all-levels="false"
                :disabled="batchProductTypesLoading"
                style="width: 100%;"
                placeholder="选择一级菜单后悬停展开分类树"
              />
              <div class="field-help">第一级为商品一级菜单，子级为现有分类树；支持悬停展开下级分类。</div>
            </el-form-item>
            <el-form-item label="默认上架状态">
              <el-switch
                v-model="batchForm.default_status"
                :active-value="1"
                :inactive-value="0"
                active-text="上架"
                inactive-text="下架"
              />
            </el-form-item>
            <el-form-item label="默认自动开通">
              <el-switch
                v-model="batchForm.default_auto_setup"
                :active-value="1"
                :inactive-value="0"
                active-text="开启"
                inactive-text="关闭"
              />
            </el-form-item>
            <el-form-item label="同步接口配置项">
              <el-switch
                v-model="batchForm.sync_config_options"
                :active-value="1"
                :inactive-value="0"
                active-text="同步"
                inactive-text="跳过"
              />
            </el-form-item>
          </div>
        </el-form>

        <div class="batch-toolbar">
          <div class="batch-toolbar-search">
            <el-cascader
              v-model="batchProductGroupSelection"
              :options="batchProductGroupOptions"
              :props="batchProductGroupCascaderProps"
              clearable
              filterable
              size="small"
              :disabled="batchProducts.length === 0"
              :show-all-levels="false"
              placeholder="选择上游商品分类"
              style="width: 360px;"
            />
            <el-input
              v-model="batchFilters.keyword"
              clearable
              size="small"
              placeholder="搜索上游商品 / 分组 / 本地商品"
            >
              <template #prefix>
                <el-icon><Search /></el-icon>
              </template>
            </el-input>
            <el-select v-model="batchFilters.connection" placeholder="对接状态" style="width: 160px;">
              <el-option label="全部状态" value="all" />
              <el-option label="仅未对接" value="pending" />
              <el-option label="仅已对接" value="connected" />
            </el-select>
          </div>
          <div class="batch-toolbar-actions">
            <el-button size="small" @click="selectVisibleBatchProducts">选择全部可见</el-button>
            <el-button size="small" @click="selectPendingBatchProducts">仅选未对接</el-button>
            <el-button size="small" @click="clearBatchSelection">清空选择</el-button>
          </div>
        </div>

        <el-empty
          v-if="!batchLoading && batchProducts.length === 0"
          description="接口暂未返回可导入的商品"
          class="batch-empty"
        />
        <el-empty
          v-else-if="!batchFilters.secondGroup"
          description="请先选择上游一级分类和二级分类，再查看对应商品"
          class="batch-empty"
        />

        <el-table
          v-else
          :data="batchVisibleProducts"
          stripe
          max-height="500"
          class="batch-table"
        >
          <el-table-column label="选择" width="78" fixed="left">
            <template #default="{ row }">
              <el-checkbox
                :model-value="isBatchProductSelected(row.id)"
                @change="(checked) => toggleBatchProductSelection(row.id, checked)"
              />
            </template>
          </el-table-column>
          <el-table-column label="上游分组" min-width="170">
            <template #default="{ row }">
              <div class="batch-group-cell">
                <strong>{{ row.remote_group_name || '-' }}</strong>
                <span>ID {{ row.id }}</span>
              </div>
            </template>
          </el-table-column>
          <el-table-column label="上游商品" min-width="320">
            <template #default="{ row }">
              <div class="batch-product-cell">
                <strong>{{ row.name }}</strong>
                <span>{{ row.type_label || '未标注类型' }}</span>
                <span>月付 {{ formatCurrency(row.monthly_price) }}</span>
                <span v-if="hasSetupFee(row)">设置费 {{ formatCurrency(row.setup_fee) }}</span>
              </div>
            </template>
          </el-table-column>
          <el-table-column label="本地状态" min-width="300">
            <template #default="{ row }">
              <div class="batch-local-cell">
                <el-tag :type="row.is_connected ? (row.connected_deleted ? 'warning' : 'success') : 'info'" size="small">
                  {{ row.is_connected ? (row.connected_deleted ? '已对接 / 已删除' : '已对接') : '未对接' }}
                </el-tag>
                <span v-if="row.connected_display_name">{{ row.connected_display_name }}</span>
                <span v-if="row.connected_group_full_name">{{ row.connected_group_full_name }}</span>
                <span v-if="row.connected_updated_at">最近更新 {{ row.connected_updated_at }}</span>
              </div>
            </template>
          </el-table-column>
        </el-table>

        <div v-if="batchResult" class="batch-result">
          <div class="batch-result-head">
            <strong>本次执行结果</strong>
            <span>
              新增 {{ batchResult.created_count || 0 }}，更新 {{ batchResult.updated_count || 0 }}，跳过 {{ batchResult.skipped_count || 0 }}
            </span>
          </div>

          <div class="batch-result-grid">
            <el-card v-if="(batchResult.imported_products || []).length" shadow="never">
              <template #header>
                <span>已导入 / 已更新</span>
              </template>
              <el-table :data="batchResult.imported_products" max-height="220" stripe>
                <el-table-column prop="supplier_product_id" label="上游 ID" width="92" />
                <el-table-column prop="supplier_display_name" label="上游规格" min-width="180" />
                <el-table-column prop="local_display_name" label="本地配置" min-width="180" />
                <el-table-column prop="group_full_name" label="落点分类" min-width="180" />
              </el-table>
            </el-card>

            <el-card v-if="(batchResult.skipped_items || []).length" shadow="never">
              <template #header>
                <span>已跳过</span>
              </template>
              <el-table :data="batchResult.skipped_items" max-height="220" stripe>
                <el-table-column prop="supplier_product_id" label="上游 ID" width="92" />
                <el-table-column prop="supplier_display_name" label="上游规格" min-width="180" />
                <el-table-column prop="reason" label="原因" min-width="260" />
              </el-table>
            </el-card>
          </div>
        </div>
      </div>
    </div>
  </div>

      <template #footer>
        <div class="drawer-footer">
          <el-button @click="batchDialogVisible = false">关闭</el-button>
          <el-button type="primary" :loading="batchSubmitting" @click="handleBatchConnect">
          开始批量对接
        </el-button>
        </div>
      </template>
    </el-drawer>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { ElMessage } from 'element-plus'
import { Plus, Refresh, RefreshLeft, Search } from '@element-plus/icons-vue'
import {
  PROVIDER_KEYS,
  providerTypeLabel,
  providerTypeOptions,
} from '@/constants/providerTypes'
import productApi from '@/api/product'
import supplierApi from '@/api/supplier'
import { formatDateTime } from '@/utils/datetime'
import { useResponsive } from '@/composables/useResponsive'

const { isMobile } = useResponsive()

const interfaceTypeLabel = providerTypeLabel
const interfaceTypeOptions = providerTypeOptions
const supplierDrawerTitle = computed(() => (editingSupplier.value ? '编辑接口' : '新增接口'))
const supplierDrawerDescription = computed(() => (
  editingSupplier.value ? '在侧边抽屉中更新接口配置，保存后立即刷新列表状态。' : '在侧边抽屉中录入新的供应商接口，保持当前列表上下文不被打断。'
))
const supplierDrawerIntro = computed(() => (
  editingSupplier.value ? '建议优先核对接口地址、用户名和 API 密钥，避免保存后无法拉取上游余额与商品。' : '新增后可直接在当前页继续批量对接上游商品，不需要跳转到独立页面。'
))

const loading = ref(false)
const submitLoading = ref(false)
const dialogVisible = ref(false)
const editingSupplier = ref(null)
const formRef = ref()
const balanceLoadingMap = reactive({})
let balanceBatchId = 0

const list = ref([])
const total = ref(0)
const page = ref(1)
const pageSize = ref(20)

const filters = reactive({
  keyword: '',
  status: '',
})

const summary = reactive({
  total: 0,
  active: 0,
  inactive: 0,
})

const createDefaultForm = () => ({
  interface_type: PROVIDER_KEYS.HOSTING_PANEL_API,
  name: '',
  api_url: '',
  api_username: '',
  api_key: '',
  notes: '',
  status: 1,
})

const form = reactive(createDefaultForm())

const rules = computed(() => ({
  interface_type: [{ required: true, message: '请选择接口种类', trigger: 'change' }],
  name: [{ required: true, message: '请输入接口名称', trigger: 'blur' }],
  api_url: [{ required: true, message: '请输入接口地址', trigger: 'blur' }],
  api_username: [{ required: true, message: '请输入用户名', trigger: 'blur' }],
  api_key: [{ required: true, message: '请输入 API 密钥', trigger: 'blur' }],
}))

const batchDialogVisible = ref(false)
const batchLoading = ref(false)
const batchSubmitting = ref(false)
const batchProductTypesLoading = ref(false)
const batchCategoryLoading = ref(false)
const batchSupplier = ref(null)
const batchFormRef = ref()
const batchProducts = ref([])
const productTypes = ref([])
const batchCategoryTrees = ref({})
const batchResult = ref(null)

const batchFilters = reactive({
  firstGroup: '',
  secondGroup: '',
  keyword: '',
  connection: 'all',
})

function createDefaultBatchForm() {
  return {
    product_type: '',
    root_category_id: null,
    child_category_id: null,
    product_ids: [],
    default_status: 1,
    default_auto_setup: 1,
    sync_config_options: 1,
  }
}

const batchForm = reactive(createDefaultBatchForm())

const batchRules = computed(() => ({
  product_type: [{ required: true, message: '请选择所属一级菜单', trigger: 'change' }],
}))

const productTypeOptions = computed(() => (
  productTypes.value.map((item) => ({
    label: item.label,
    value: item.value,
  }))
))

const batchDialogTitle = computed(() => (
  batchSupplier.value?.name ? `批量对接 · ${batchSupplier.value.name}` : '批量对接'
))
const batchDrawerDescription = computed(() => (
  batchSupplier.value?.name
    ? `在右侧工作台中批量导入并绑定 ${batchSupplier.value.name} 的上游商品。`
    : '在右侧工作台中批量导入并绑定上游商品。'
))

const batchSelectedIdSet = computed(() => new Set(
  batchForm.product_ids.map((id) => Number(id)).filter((id) => id > 0),
))

const batchPendingCount = computed(() => (
  batchProducts.value.filter((item) => !item.is_connected).length
))

const batchConnectedCount = computed(() => (
  batchProducts.value.filter((item) => item.is_connected).length
))

const batchProductGroupOptions = computed(() => {
  const firstGroupMap = new Map()

  batchProducts.value.forEach((item) => {
    const firstGroup = String(item.first_group_name || '').trim()
    const secondGroup = String(item.second_group_name || '').trim()

    if (!firstGroup || !secondGroup) {
      return
    }

    if (!firstGroupMap.has(firstGroup)) {
      firstGroupMap.set(firstGroup, new Set())
    }

    firstGroupMap.get(firstGroup).add(secondGroup)
  })

  return Array.from(firstGroupMap.entries()).map(([firstGroup, secondGroups]) => ({
    value: firstGroup,
    label: firstGroup,
    children: Array.from(secondGroups).map((secondGroup) => ({
      value: secondGroup,
      label: secondGroup,
    })),
  }))
})
const batchProductGroupCascaderProps = {
  value: 'value',
  label: 'label',
  children: 'children',
  expandTrigger: 'hover',
  emitPath: true,
}
const batchProductGroupSelection = computed({
  get() {
    if (batchFilters.firstGroup && batchFilters.secondGroup) {
      return [batchFilters.firstGroup, batchFilters.secondGroup]
    }

    return []
  },
  set(value) {
    const path = Array.isArray(value) ? value.map((item) => String(item || '').trim()).filter(Boolean) : []
    batchFilters.firstGroup = path[0] || ''
    batchFilters.secondGroup = path[1] || ''
  },
})

function buildBatchCategoryTreeOptions(nodes = []) {
  return (Array.isArray(nodes) ? nodes : [])
    .map((node) => ({
      value: Number(node?.id || 0),
      label: String(node?.name || ''),
      children: buildBatchCategoryTreeOptions(node?.children || []),
    }))
    .filter((node) => node.value > 0)
}

function findBatchCategoryNode(nodes, targetId, parent = null) {
  const normalizedTargetId = Number(targetId || 0)
  if (!normalizedTargetId) {
    return null
  }

  for (const node of Array.isArray(nodes) ? nodes : []) {
    const nodeId = Number(node?.id || 0)
    if (nodeId === normalizedTargetId) {
      return { node, parent }
    }

    const matched = findBatchCategoryNode(node?.children, normalizedTargetId, node)
    if (matched) {
      return matched
    }
  }

  return null
}

function applyBatchCategorySelection(value, nodes = []) {
  const selectedId = Number(value || 0)
  if (!selectedId) {
    batchForm.root_category_id = null
    batchForm.child_category_id = null
    return
  }

  const matched = findBatchCategoryNode(nodes, selectedId)
  if (!matched) {
    batchForm.root_category_id = null
    batchForm.child_category_id = null
    return
  }

  const parentId = Number(matched.parent?.id || 0)
  if (parentId > 0) {
    batchForm.root_category_id = parentId
    batchForm.child_category_id = Number(matched.node.id)
    return
  }

  batchForm.root_category_id = Number(matched.node.id)
  batchForm.child_category_id = null
}

const batchCategoryTreeOptions = computed(() => (
  productTypeOptions.value.map((item) => ({
    value: item.value,
    label: item.label,
    children: buildBatchCategoryTreeOptions(batchCategoryTrees.value?.[item.value] || []),
  }))
))
const batchCategoryCascaderProps = {
  value: 'value',
  label: 'label',
  children: 'children',
  emitPath: true,
  checkStrictly: true,
  expandTrigger: 'hover',
}

const batchCategorySelection = computed({
  get() {
    if (batchForm.child_category_id) {
      return [batchForm.product_type, batchForm.root_category_id, batchForm.child_category_id].filter(Boolean)
    }

    if (batchForm.root_category_id) {
      return [batchForm.product_type, batchForm.root_category_id].filter(Boolean)
    }

    if (batchForm.product_type) {
      return [batchForm.product_type]
    }

    return []
  },
  set(value) {
    const path = Array.isArray(value) ? value : []
    const productType = String(path[0] || '').trim()
    batchForm.product_type = productType

    if (!productType) {
      batchForm.root_category_id = null
      batchForm.child_category_id = null
      return
    }

    if (path.length < 2) {
      batchForm.root_category_id = null
      batchForm.child_category_id = null
      return
    }

    applyBatchCategorySelection(path[path.length - 1], batchCategoryTrees.value?.[productType] || [])
  },
})

const batchVisibleProducts = computed(() => {
  const firstGroup = String(batchFilters.firstGroup || '').trim()
  const secondGroup = String(batchFilters.secondGroup || '').trim()
  const keyword = String(batchFilters.keyword || '').trim().toLowerCase()
  const connection = String(batchFilters.connection || 'all')

  if (!firstGroup || !secondGroup) {
    return []
  }

  return batchProducts.value.filter((item) => {
    if (String(item.first_group_name || '').trim() !== firstGroup) {
      return false
    }

    if (String(item.second_group_name || '').trim() !== secondGroup) {
      return false
    }

    if (connection === 'pending' && item.is_connected) {
      return false
    }

    if (connection === 'connected' && !item.is_connected) {
      return false
    }

    if (!keyword) {
      return true
    }

    return [
      item.name,
      item.first_group_name,
      item.second_group_name,
      item.remote_group_name,
      item.connected_display_name,
      item.connected_group_full_name,
    ].some((field) => String(field || '').toLowerCase().includes(keyword))
  })
})

function canBatchConnect(row) {
  return Boolean(row?.has_api_url && row?.api_username && row?.has_api_key)
}

function resolveRequestErrorMessage(error, fallback = '请求失败') {
  const responseMessage = String(error?.response?.data?.message || '').trim()
  if (responseMessage) {
    return responseMessage
  }

  const validationErrors = error?.response?.data?.errors
  if (validationErrors && typeof validationErrors === 'object') {
    const flatErrors = Object.values(validationErrors).flat().filter(Boolean)
    if (flatErrors.length) {
      return flatErrors.join('，')
    }
  }

  const message = String(error?.message || '').trim()
  return message || fallback
}

function normalizeBatchProductConnection(value) {
  return value === true || Number(value) === 1
}

function resolveBatchRemoteGroupName(item = {}) {
  return String(
    item.remote_group_name
      || item.group_label
      || item.group_name
      || item.first_group_name
      || item.first_group_label
      || item._group_label
      || '',
  ).trim()
}

function normalizeBatchProduct(item = {}) {
  return {
    ...item,
    id: Number(item.id || item.product_id || 0),
    name: String(item.name || item.product_name || '').trim(),
    type_label: String(item.type_label || item.type_name || item.type || item.billingcycle || '').trim(),
    first_group_name: String(item.first_group_name || '').trim(),
    second_group_name: String(item.group_name || item.second_group_name || '').trim(),
    remote_group_name: resolveBatchRemoteGroupName(item),
    is_connected: normalizeBatchProductConnection(item.is_connected),
    connected_display_name: String(item.connected_display_name || '').trim(),
    connected_group_full_name: String(item.connected_group_full_name || item.connected_group_name || '').trim(),
    connected_deleted: Boolean(item.connected_deleted),
    connected_updated_at: String(item.connected_updated_at || '').trim(),
  }
}

function buildBatchProducts(payload = {}) {
  const directProducts = Array.isArray(payload.products) ? payload.products : []
  if (directProducts.length) {
    return directProducts
      .map((item) => normalizeBatchProduct(item))
      .filter((item) => item.id > 0)
  }

  const groups = Array.isArray(payload.groups) ? payload.groups : []
  return groups.flatMap((group) => {
    const groupLabel = String(group.label || group.name || '').trim()
    const items = Array.isArray(group.items) ? group.items : []
    return items
      .map((item) => normalizeBatchProduct({ ...item, _group_label: groupLabel }))
      .filter((item) => item.id > 0)
  })
}

function hasSetupFee(row) {
  return row?.setup_fee !== null && row?.setup_fee !== undefined && row?.setup_fee !== ''
}

function formatCurrency(value) {
  if (value === null || value === undefined || value === '') {
    return '-'
  }

  const amount = Number(value)
  if (!Number.isFinite(amount)) {
    return '-'
  }

  return `¥ ${amount.toFixed(2)}`
}

function setBatchSelectedIds(ids = []) {
  batchForm.product_ids = Array.from(new Set(
    ids.map((id) => Number(id)).filter((id) => id > 0),
  ))
}

function isBatchProductSelected(productId) {
  return batchSelectedIdSet.value.has(Number(productId))
}

function toggleBatchProductSelection(productId, checked) {
  const next = new Set(batchSelectedIdSet.value)
  const normalizedId = Number(productId)

  if (checked) {
    next.add(normalizedId)
  } else {
    next.delete(normalizedId)
  }

  setBatchSelectedIds(Array.from(next))
}

function selectVisibleBatchProducts() {
  setBatchSelectedIds(batchVisibleProducts.value.map((item) => item.id))
}

function selectPendingBatchProducts() {
  setBatchSelectedIds(batchVisibleProducts.value.filter((item) => !item.is_connected).map((item) => item.id))
}

function clearBatchSelection() {
  batchForm.product_ids = []
}

async function loadList() {
  loading.value = true
  try {
    const res = await supplierApi.list({
      ...filters,
      page: page.value,
      page_size: pageSize.value,
    })

    const rows = (res.data.list || []).map((item) => ({
      ...item,
      remote_balance: null,
      remote_balance_status: 'idle',
      remote_client: {},
    }))

    list.value = rows
    total.value = res.data.total
    resetBalanceLoadingMap(rows)
    void syncVisibleBalances(rows)
  } finally {
    loading.value = false
  }
}

async function loadSummary() {
  const res = await supplierApi.summary()
  summary.total = res.data.total
  summary.active = res.data.active
  summary.inactive = res.data.inactive
}

async function loadData() {
  try {
    await Promise.all([loadList(), loadSummary()])
  } catch {
    balanceBatchId += 1
    list.value = []
    total.value = 0
    resetBalanceLoadingMap([])
  }
}

function handleSearch() {
  page.value = 1
  loadData()
}

function resetFilters() {
  filters.keyword = ''
  filters.status = ''
  page.value = 1
  loadData()
}

function handleSupplierAction(command, row) {
  if (command === 'edit') {
    openDialog(row)
  } else if (command === 'batch') {
    openBatchDialog(row)
  } else if (command === 'toggle') {
    handleToggleStatus(row)
  } else if (command === 'delete') {
    handleDelete(row.id)
  }
}

async function openDialog(row = null) {
  editingSupplier.value = row
  Object.assign(form, createDefaultForm())

  if (!row) {
    dialogVisible.value = true
    return
  }

  try {
    const res = await supplierApi.detail(row.id)
    Object.assign(form, createDefaultForm(), res.data || {})
    dialogVisible.value = true
  } catch {
    editingSupplier.value = null
  }
}

function handleFormDrawerClosed() {
  formRef.value?.clearValidate?.()
}

function resetBalanceLoadingMap(rows) {
  const activeIds = new Set(rows.map((row) => Number(row.id)))

  Object.keys(balanceLoadingMap).forEach((key) => {
    if (!activeIds.has(Number(key))) {
      delete balanceLoadingMap[key]
    }
  })
}

async function syncVisibleBalances(rows = []) {
  const currentBatchId = ++balanceBatchId

  await Promise.allSettled(rows.map(async (row) => {
    if (!canBatchConnect(row)) {
      row.remote_balance = null
      row.remote_client = {}
      row.remote_balance_status = 'disabled'
      balanceLoadingMap[row.id] = false
      return
    }

    balanceLoadingMap[row.id] = true
    row.remote_balance = null
    row.remote_balance_status = 'loading'

    try {
      const res = await supplierApi.balance(row.id, { silent: true })

      if (currentBatchId !== balanceBatchId) {
        return
      }

      row.remote_balance = res.data.balance || '0.00'
      row.remote_client = res.data.client || {}
      row.remote_balance_status = 'success'
    } catch {
      if (currentBatchId !== balanceBatchId) {
        return
      }

      row.remote_balance = null
      row.remote_client = {}
      row.remote_balance_status = 'error'
    } finally {
      if (currentBatchId === balanceBatchId) {
        balanceLoadingMap[row.id] = false
      }
    }
  }))
}

async function handleSubmit() {
  try {
    await formRef.value?.validate()
  } catch {
    return
  }

  submitLoading.value = true
  try {
    if (editingSupplier.value) {
      await supplierApi.update(editingSupplier.value.id, form)
      ElMessage.success('接口已更新')
    } else {
      await supplierApi.create(form)
      ElMessage.success('接口已创建')
    }

    dialogVisible.value = false
    await loadData()
  } finally {
    submitLoading.value = false
  }
}

async function handleToggleStatus(row) {
  try {
    await supplierApi.toggleStatus(row.id)
    ElMessage.success(`接口已${row.status === 1 ? '停用' : '启用'}`)
    await loadData()
  } catch {
    // request interceptor already shows the error message
  }
}

async function handleDelete(id) {
  try {
    await supplierApi.delete(id)
    ElMessage.success('接口已删除')

    if (list.value.length === 1 && page.value > 1) {
      page.value -= 1
    }

    await loadData()
  } catch {
    // request interceptor already shows the error message
  }
}

async function loadBatchProductTypes() {
  if (productTypes.value.length) {
    return
  }

  batchProductTypesLoading.value = true
  try {
    const res = await productApi.types()
    productTypes.value = res.data.list || []
  } finally {
    batchProductTypesLoading.value = false
  }
}

async function loadAllBatchCategories() {
  if (!productTypes.value.length) {
    batchCategoryTrees.value = {}
    return
  }

  batchCategoryLoading.value = true
  try {
    const nextTrees = { ...batchCategoryTrees.value }
    await Promise.all(productTypes.value.map(async (item) => {
      const productType = String(item.value || '').trim()
      if (!productType || Array.isArray(nextTrees[productType])) {
        return
      }

      const res = await productApi.categories({ product_type: productType })
      nextTrees[productType] = Array.isArray(res.data?.tree) ? res.data.tree : []
    }))
    batchCategoryTrees.value = nextTrees

    const currentSelection = batchForm.child_category_id || batchForm.root_category_id
    if (currentSelection && batchForm.product_type) {
      applyBatchCategorySelection(currentSelection, nextTrees[batchForm.product_type] || [])
    }
  } finally {
    batchCategoryLoading.value = false
  }
}

async function loadBatchProducts(supplierId) {
  batchLoading.value = true
  try {
    const res = await supplierApi.products(supplierId, { silent: true })
    const nextProducts = buildBatchProducts(res.data || {})
    batchProducts.value = nextProducts

    const firstGroups = new Set(nextProducts.map((item) => String(item.first_group_name || '').trim()).filter(Boolean))
    if (batchFilters.firstGroup && !firstGroups.has(String(batchFilters.firstGroup || '').trim())) {
      batchFilters.firstGroup = ''
      batchFilters.secondGroup = ''
    } else if (batchFilters.firstGroup && batchFilters.secondGroup) {
      const secondGroups = new Set(
        nextProducts
          .filter((item) => String(item.first_group_name || '').trim() === String(batchFilters.firstGroup || '').trim())
          .map((item) => String(item.second_group_name || '').trim())
          .filter(Boolean),
      )

      if (!secondGroups.has(String(batchFilters.secondGroup || '').trim())) {
        batchFilters.secondGroup = ''
      }
    }

    if (batchForm.product_ids.length) {
      const availableIds = new Set(nextProducts.map((item) => item.id))
      setBatchSelectedIds(batchForm.product_ids.filter((id) => availableIds.has(Number(id))))
    }
  } finally {
    batchLoading.value = false
  }
}

async function openBatchDialog(row) {
  if (!canBatchConnect(row)) {
    ElMessage.warning('请先补全接口地址、用户名和 API 密钥')
    return
  }

  batchSupplier.value = row
  batchResult.value = null
  batchFilters.firstGroup = ''
  batchFilters.secondGroup = ''
  batchFilters.keyword = ''
  batchFilters.connection = 'all'
  Object.assign(batchForm, createDefaultBatchForm())
  batchDialogVisible.value = true

  try {
    await Promise.all([
      loadBatchProductTypes(),
      loadBatchProducts(row.id),
    ])

    if (!batchForm.product_type && productTypes.value.length) {
      batchForm.product_type = productTypes.value[0].value
    }

    await loadAllBatchCategories()
    selectPendingBatchProducts()
  } catch (error) {
    batchDialogVisible.value = false
    ElMessage.error(resolveRequestErrorMessage(error, '供应商商品拉取失败'))
  }
}

async function reloadBatchProducts() {
  if (!batchSupplier.value?.id) {
    return
  }

  try {
    await loadBatchProducts(batchSupplier.value.id)
  } catch (error) {
    ElMessage.error(resolveRequestErrorMessage(error, '供应商商品拉取失败'))
  }
}

async function handleBatchConnect() {
  try {
    await batchFormRef.value?.validate()
  } catch {
    return
  }

  if (!batchSupplier.value?.id) {
    ElMessage.warning('当前接口不存在')
    return
  }

  if (!batchForm.product_ids.length) {
    ElMessage.warning('请至少选择一个上游商品')
    return
  }

  if (!batchForm.child_category_id && !batchForm.root_category_id) {
    ElMessage.warning('请选择目标分类')
    return
  }

  batchSubmitting.value = true
  try {
    const res = await supplierApi.batchConnectProducts(batchSupplier.value.id, {
      product_type: batchForm.product_type,
      root_category_id: batchForm.root_category_id || null,
      child_category_id: batchForm.child_category_id || null,
      product_ids: batchForm.product_ids.map((id) => Number(id)).filter((id) => id > 0),
      default_status: Number(batchForm.default_status || 0),
      default_auto_setup: Number(batchForm.default_auto_setup || 0),
      sync_config_options: Number(batchForm.sync_config_options || 0),
    })

    batchResult.value = res.data || null
    ElMessage.success(`批量对接完成：新增 ${res.data?.created_count || 0}，更新 ${res.data?.updated_count || 0}，跳过 ${res.data?.skipped_count || 0}`)

    try {
      await loadBatchProducts(batchSupplier.value.id)
    } catch (error) {
      ElMessage.warning(resolveRequestErrorMessage(error, '已完成对接，但刷新上游商品失败'))
    }
  } catch (error) {
    ElMessage.error(resolveRequestErrorMessage(error, '批量对接失败'))
  } finally {
    batchSubmitting.value = false
  }
}

function handleBatchDialogClosed() {
  batchSupplier.value = null
  batchResult.value = null
  batchProducts.value = []
  batchCategoryTrees.value = {}
  batchFilters.firstGroup = ''
  batchFilters.secondGroup = ''
  batchFilters.keyword = ''
  batchFilters.connection = 'all'
  Object.assign(batchForm, createDefaultBatchForm())
}

onMounted(loadData)
</script>

<style scoped>
.page-actions {
  display: flex;
  gap: 12px;
  flex-wrap: wrap;
}

.page-meta {
  display: flex;
  gap: 16px;
  flex-wrap: wrap;
  margin-top: 8px;
  color: #909399;
  font-size: 13px;
}

.meta-item {
  line-height: 1.4;
}

.supplier-cell {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.supplier-main {
  font-weight: 600;
  color: #303133;
}

.supplier-sub {
  font-size: 12px;
  color: #909399;
}

.balance-cell {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
}

.balance-muted {
  color: #909399;
}

.table-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 16px;
  flex-wrap: wrap;
  margin-top: 16px;
}

.footer-tip {
  font-size: 13px;
  line-height: 1.6;
  color: #909399;
}

.drawer-header {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.drawer-header strong {
  font-size: 20px;
  line-height: 1.3;
  color: #303133;
}

.drawer-header span {
  font-size: 13px;
  line-height: 1.6;
  color: #909399;
}

.drawer-body {
  flex: 1;
  min-height: 0;
  overflow: auto;
}

.drawer-content {
  display: flex;
  flex-direction: column;
  gap: 16px;
  min-height: 100%;
}

.drawer-content--batch {
  padding-bottom: 8px;
}

.drawer-intro {
  padding: 16px 18px;
  background: linear-gradient(180deg, #f7f9fc 0%, #f4f7fb 100%);
  border: 1px solid #e4e7ed;
  border-radius: 14px;
}

.drawer-intro strong {
  display: block;
  margin-bottom: 6px;
  font-size: 15px;
  line-height: 1.4;
  color: #303133;
}

.drawer-intro p {
  margin: 0;
  font-size: 13px;
  line-height: 1.7;
  color: #606266;
}

.drawer-intro--soft {
  background: linear-gradient(180deg, #fbfcfe 0%, #f7f9fc 100%);
}

.drawer-footer {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
}

.supplier-form {
  padding-top: 8px;
}

.form-grid,
.batch-form-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 6px 16px;
}

.is-span-2 {
  grid-column: span 2;
}

.field-help {
  margin-top: 4px;
  font-size: 12px;
  line-height: 1.5;
  color: #909399;
}

.batch-shell {
  display: flex;
  flex-direction: column;
  gap: 16px;
  min-height: 220px;
}

.batch-alert {
  margin-bottom: 4px;
}

.batch-overview {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr)) auto;
  gap: 12px;
  align-items: stretch;
}

.batch-overview-item {
  display: flex;
  flex-direction: column;
  justify-content: center;
  gap: 6px;
  min-height: 74px;
  padding: 14px 16px;
  background: #f7f9fc;
  border: 1px solid #e4e7ed;
  border-radius: 12px;
}

.batch-overview-item strong {
  font-size: 22px;
  color: #303133;
  line-height: 1;
}

.batch-overview-item span {
  font-size: 12px;
  color: #909399;
}

.batch-overview-actions {
  display: flex;
  align-items: center;
  justify-content: flex-end;
}

.batch-toolbar {
  display: flex;
  justify-content: space-between;
  gap: 16px;
  flex-wrap: wrap;
}

.batch-toolbar-search {
  display: flex;
  gap: 12px;
  flex: 1;
  min-width: 320px;
}

.batch-toolbar-search :deep(.el-input) {
  flex: 1;
}

.batch-toolbar-actions {
  display: flex;
  gap: 12px;
  flex-wrap: wrap;
}

.batch-empty {
  padding: 24px 0 12px;
}

.batch-table {
  border: 1px solid #ebeef5;
  border-radius: 12px;
  overflow: hidden;
}

.batch-group-cell,
.batch-product-cell,
.batch-local-cell {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.batch-group-cell strong,
.batch-product-cell strong {
  color: #303133;
}

.batch-group-cell span,
.batch-product-cell span,
.batch-local-cell span {
  color: #606266;
  font-size: 12px;
  line-height: 1.5;
}

.batch-result {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.batch-result-head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}

.batch-result-head span {
  color: #909399;
  font-size: 13px;
}

.batch-result-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 16px;
}

.drawer-content--batch {
  gap: 12px;
}

.drawer-content--batch .drawer-intro--soft {
  display: none;
}

.drawer-content--batch .supplier-form--dense {
  padding-top: 0;
}

.drawer-content--batch :deep(.el-form-item) {
  margin-bottom: 10px;
}

.drawer-content--batch .batch-form-grid {
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 4px 12px;
}

.drawer-content--batch .is-span-2 {
  grid-column: span 3;
}

.batch-alert {
  margin: 0;
}

.batch-alert :deep(.el-alert__content) {
  padding-right: 0;
}

.batch-overview {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  align-items: center;
}

.batch-overview-item {
  min-height: auto;
  padding: 6px 10px;
  background: #f7f9fc;
  border: 1px solid #e4e7ed;
  border-radius: 999px;
  flex-direction: row;
  align-items: baseline;
  gap: 8px;
}

.batch-overview-item strong {
  font-size: 16px;
}

.batch-overview-item span {
  white-space: nowrap;
}

.batch-overview-actions {
  margin-left: auto;
}

.batch-toolbar {
  gap: 10px;
}

.batch-toolbar-actions {
  gap: 8px;
}

.batch-result-grid :deep(.el-card) {
  border-radius: 10px;
  box-shadow: none;
}

.batch-result-grid :deep(.el-card__header) {
  padding: 10px 12px;
}

.batch-result-grid :deep(.el-card__body) {
  padding: 0 12px 12px;
}

.supplier-edit-drawer :deep(.el-drawer__header),
.supplier-batch-drawer :deep(.el-drawer__header) {
  margin-bottom: 0;
  padding: 22px 24px 14px;
}

.supplier-edit-drawer :deep(.el-drawer__body),
.supplier-batch-drawer :deep(.el-drawer__body) {
  display: flex;
  flex-direction: column;
  min-height: 0;
  padding: 0 24px 20px;
  overflow: hidden;
}

.supplier-edit-drawer :deep(.el-drawer__footer),
.supplier-batch-drawer :deep(.el-drawer__footer) {
  margin: 0;
  padding: 16px 24px 20px;
  border-top: 1px solid #ebeef5;
}

@media (max-width: 960px) {
  .drawer-content--batch .batch-form-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .drawer-content--batch .is-span-2 {
    grid-column: span 2;
  }

  .batch-overview {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .batch-overview-actions {
    margin-left: 0;
    width: 100%;
    justify-content: flex-start;
  }

  .batch-result-grid {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 768px) {
  .page-actions,
  .batch-toolbar-actions,
  .batch-toolbar-search {
    width: 100%;
  }

  .supplier-edit-drawer :deep(.el-drawer__header),
  .supplier-batch-drawer :deep(.el-drawer__header),
  .supplier-edit-drawer :deep(.el-drawer__body),
  .supplier-batch-drawer :deep(.el-drawer__body),
  .supplier-edit-drawer :deep(.el-drawer__footer),
  .supplier-batch-drawer :deep(.el-drawer__footer) {
    padding-left: 16px;
    padding-right: 16px;
  }

  .page-actions :deep(.el-button) {
    flex: 1;
  }

  .form-grid,
  .batch-form-grid {
    grid-template-columns: 1fr;
  }

  .is-span-2 {
    grid-column: span 1;
  }

  .batch-overview {
    grid-template-columns: 1fr;
  }

  .batch-overview-actions {
    grid-column: span 1;
  }
}
</style>
