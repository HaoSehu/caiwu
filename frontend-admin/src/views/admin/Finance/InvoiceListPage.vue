<template>
  <div class="page-container admin-page finance-invoice-page">
    <section class="admin-page-head">
      <div class="admin-page-heading">
        <span class="admin-page-kicker">财务</span>
        <h2>账单管理</h2>
        <p>按账单口径查看新购、续费、充值、扣款、手工与附加配置账单。</p>
      </div>
    </section>

    <section class="filter-panel">
      <div class="search-bar">
        <el-input v-model="filters.keyword" placeholder="搜索账单号 / 订单号 / 用户" clearable @keyup.enter="handleSearch">
          <template #prefix><el-icon><Search /></el-icon></template>
        </el-input>
        <el-select v-model="filters.type" placeholder="类型" clearable @change="handleSearch">
          <el-option v-for="item in invoiceTypeOptions" :key="item.value" :label="item.label" :value="item.value" />
        </el-select>
        <el-select v-model="filters.status" placeholder="状态" clearable @change="handleSearch">
          <el-option v-for="item in invoiceStatusOptions" :key="item.value" :label="item.label" :value="item.value" />
        </el-select>
        <el-date-picker
          v-model="filters.date_range"
          type="daterange"
          value-format="YYYY-MM-DD"
          range-separator="至"
          start-placeholder="开始日期"
          end-placeholder="结束日期"
          clearable
          @change="handleSearch"
        />
      </div>
    </section>

    <el-card shadow="never" class="table-card">
      <el-table :data="list" v-loading="loading" stripe row-key="id">
        <el-table-column prop="invoice_no" label="账单号" min-width="170" />
        <el-table-column label="用户" min-width="170">
          <template #default="{ row }">
            <div class="stack-cell">
              <strong>{{ row.user?.nickname || row.user?.email || '--' }}</strong>
              <span>{{ row.user?.email || '--' }}</span>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="账单项目" min-width="220">
          <template #default="{ row }">
            <div class="stack-cell">
              <strong>{{ row.combined_display_name || row.product_display_name || row.product_spec_display || row.type_label }}</strong>
              <span>{{ row.order?.order_no || row.summary?.highlight || '--' }}</span>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="类型" width="120">
          <template #default="{ row }">{{ row.type_label || resolveInvoiceType(row.type) }}</template>
        </el-table-column>
        <el-table-column label="金额" width="120">
          <template #default="{ row }">{{ formatMoney(row.amount) }}</template>
        </el-table-column>
        <el-table-column label="已付" width="120">
          <template #default="{ row }">{{ formatMoney(row.paid_amount) }}</template>
        </el-table-column>
        <el-table-column label="状态" width="110">
          <template #default="{ row }">
            <StatusTag :status-map="INVOICE_STATUS_MAP" :status="row.raw_status ?? row.status">
              {{ row.status_label || getStatusLabel(INVOICE_STATUS_MAP, row.raw_status ?? row.status) }}
            </StatusTag>
          </template>
        </el-table-column>
        <el-table-column label="创建时间" width="170">
          <template #default="{ row }">{{ formatDateTime(row.created_at) }}</template>
        </el-table-column>
        <el-table-column label="支付时间" width="170">
          <template #default="{ row }">{{ formatDateTime(row.paid_at) }}</template>
        </el-table-column>
        <el-table-column label="操作" width="130" fixed="right">
          <template #default="{ row }">
            <div class="row-actions">
              <el-button link type="primary" @click="openDetail(row)">详情</el-button>
              <el-popconfirm v-if="canCancel(row)" title="确认取消该账单？" @confirm="cancelInvoice(row)">
                <template #reference>
                  <el-button link type="danger">取消</el-button>
                </template>
              </el-popconfirm>
            </div>
          </template>
        </el-table-column>
      </el-table>

      <div class="table-pagination">
        <el-pagination
          v-model:current-page="pagination.page"
          v-model:page-size="pagination.page_size"
          :total="total"
          :page-sizes="[20, 50, 100]"
          layout="total, sizes, prev, pager, next"
          @size-change="handlePageSizeChange"
          @current-change="loadList"
        />
      </div>
    </el-card>

    <InvoiceDetailDrawer
      :state="detailState"
      :format-money="formatMoney"
      @close="closeDetail"
      @reload="reloadDetail"
      @cancel="handleDrawerCancel"
    />
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { ElMessage } from 'element-plus'
import { Search } from '@element-plus/icons-vue'
import adminApi from '@/api/admin'
import { useUserStore } from '@/stores/user'
import { formatDateTime } from '@/utils/datetime'
import InvoiceDetailDrawer from '@/views/admin/Users/detail/components/InvoiceDetailDrawer.vue'
import StatusTag from '@shared/components/StatusTag.vue'
import { INVOICE_STATUS_MAP, INVOICE_TYPE_MAP, getStatusLabel, toSelectOptions } from '@shared/statusConfig'

const userStore = useUserStore()
const loading = ref(false)
const list = ref([])
const total = ref(0)

const pagination = reactive({
  page: 1,
  page_size: 20,
})

const filters = reactive({
  keyword: '',
  type: '',
  status: '',
  date_range: null,
})

const detailState = reactive({
  visible: false,
  loading: false,
  cancelLoading: false,
  currentId: 0,
  detail: {
    invoice: {},
    payments: [],
    items: [],
    logs: [],
  },
})

const invoiceStatusOptions = computed(() => toSelectOptions(INVOICE_STATUS_MAP, false))
const invoiceTypeOptions = computed(() => (
  Object.entries(INVOICE_TYPE_MAP).map(([value, label]) => ({ value, label }))
))

function hasPermission(permission) {
  return userStore.permissions.includes('*') || userStore.permissions.includes(permission)
}

function formatMoney(value) {
  return `¥${Number(value || 0).toFixed(2)}`
}

function resolveInvoiceType(type) {
  return INVOICE_TYPE_MAP[type] || type || '--'
}

function buildParams() {
  const params = {
    page: pagination.page,
    page_size: pagination.page_size,
  }

  ;['keyword', 'type', 'status'].forEach((key) => {
    if (filters[key] !== '' && filters[key] !== null && filters[key] !== undefined) {
      params[key] = filters[key]
    }
  })

  if (Array.isArray(filters.date_range) && filters.date_range.length === 2) {
    params.date_range = filters.date_range
  }

  return params
}

async function loadList() {
  loading.value = true
  try {
    const res = await adminApi.invoices.list(buildParams())
    list.value = Array.isArray(res.data?.list) ? res.data.list : []
    total.value = Number(res.data?.total || 0)
    pagination.page = Number(res.data?.page || pagination.page)
    pagination.page_size = Number(res.data?.page_size || pagination.page_size)
  } catch (error) {
    ElMessage.error(error?.response?.data?.message || error?.message || '加载账单列表失败')
  } finally {
    loading.value = false
  }
}

function handleSearch() {
  pagination.page = 1
  void loadList()
}

function handlePageSizeChange() {
  pagination.page = 1
  void loadList()
}

function resetFilters() {
  filters.keyword = ''
  filters.type = ''
  filters.status = ''
  filters.date_range = null
  pagination.page = 1
  void loadList()
}

function canCancel(row) {
  const status = Number(row?.raw_status ?? row?.status ?? -1)
  return hasPermission('invoice.manage') && [0, 3].includes(status)
}

async function openDetail(row) {
  if (!row?.id) return

  detailState.visible = true
  detailState.currentId = Number(row.id)
  detailState.detail = { invoice: row, payments: [], items: [], logs: [] }
  await reloadDetail()
}

async function reloadDetail() {
  if (!detailState.currentId) return

  detailState.loading = true
  try {
    const res = await adminApi.invoices.detail(detailState.currentId)
    detailState.detail = {
      invoice: res.data || {},
      payments: Array.isArray(res.data?.payments) ? res.data.payments : [],
      items: Array.isArray(res.data?.items) ? res.data.items : [],
      logs: Array.isArray(res.data?.logs) ? res.data.logs : [],
    }
  } catch (error) {
    ElMessage.error(error?.response?.data?.message || error?.message || '加载账单详情失败')
  } finally {
    detailState.loading = false
  }
}

function closeDetail() {
  detailState.visible = false
  detailState.currentId = 0
  detailState.cancelLoading = false
  detailState.detail = { invoice: {}, payments: [], items: [], logs: [] }
}

async function cancelInvoice(row) {
  if (!row?.id) return

  try {
    await adminApi.invoices.cancel(row.id)
    ElMessage.success('账单已取消')
    await loadList()
    if (detailState.currentId === Number(row.id)) await reloadDetail()
  } catch (error) {
    ElMessage.error(error?.response?.data?.message || error?.message || '取消账单失败')
  }
}

async function handleDrawerCancel() {
  const invoiceId = Number(detailState.detail?.invoice?.id || detailState.currentId || 0)
  if (!invoiceId) return

  detailState.cancelLoading = true
  try {
    await adminApi.invoices.cancel(invoiceId)
    ElMessage.success('账单已取消')
    await loadList()
    await reloadDetail()
  } catch (error) {
    ElMessage.error(error?.response?.data?.message || error?.message || '取消账单失败')
  } finally {
    detailState.cancelLoading = false
  }
}

onMounted(() => loadList())
</script>

<style lang="scss" scoped>
.finance-invoice-page {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.stack-cell {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;

  strong {
    color: $text-color-primary;
    font-weight: 600;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  span {
    color: $text-color-placeholder;
    font-size: 12px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
}

.row-actions {
  display: inline-flex;
  gap: 8px;
}

.table-pagination {
  display: flex;
  justify-content: flex-end;
  margin-top: 14px;
}
</style>
