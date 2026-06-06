<template>
  <div class="page-container admin-page finance-order-page">
    <section class="admin-page-head">
      <div class="admin-page-heading">
        <span class="admin-page-kicker">财务</span>
        <h2>{{ pageMeta.title }}</h2>
        <p>{{ pageMeta.description }}</p>
      </div>
    </section>

    <section class="filter-panel">
      <div class="search-bar">
        <el-input
          v-model="filters.keyword"
          placeholder="搜索订单号 / 账单号 / 用户 / 服务"
          clearable
          @keyup.enter="handleSearch"
        >
          <template #prefix><el-icon><Search /></el-icon></template>
        </el-input>
        <el-select v-if="mode === 'orders'" v-model="filters.type" placeholder="类型" clearable @change="handleSearch">
          <el-option label="新购" value="new" />
          <el-option label="续费" value="renew" />
          <el-option label="附加配置" value="upgrade" />
        </el-select>
        <el-select v-if="mode === 'addons'" v-model="filters.kind" placeholder="配置类型" clearable @change="handleSearch">
          <el-option label="全部" value="all" />
          <el-option label="流量包" value="traffic_package" />
        </el-select>
        <el-select v-model="filters.status" placeholder="状态" clearable @change="handleSearch">
          <el-option v-for="item in orderStatusOptions" :key="item.value" :label="item.label" :value="item.value" />
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
        <el-table-column prop="order_no" label="订单号" min-width="170" />
        <el-table-column label="用户" min-width="170">
          <template #default="{ row }">
            <div class="stack-cell">
              <strong>{{ row.user?.nickname || row.user?.email || `用户 #${row.user_id}` }}</strong>
              <span>{{ row.user?.email || '--' }}</span>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="产品/服务" min-width="220">
          <template #default="{ row }">
            <div class="stack-cell">
              <strong>{{ row.product_name || '--' }}</strong>
              <span v-if="row.service?.name">{{ row.service.name }}</span>
              <span v-else-if="row.service?.domain">{{ row.service.domain }}</span>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="类型" width="110">
          <template #default="{ row }">{{ row.type_label || orderTypeLabel(row.type) }}</template>
        </el-table-column>
        <el-table-column v-if="mode === 'addons'" label="配置" min-width="130">
          <template #default="{ row }">
            <div class="stack-cell">
              <strong>{{ row.addon_kind_label || '附加配置' }}</strong>
              <span>{{ row.addon_target_label || row.addon_mode || '--' }}</span>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="金额" width="120">
          <template #default="{ row }">{{ formatMoney(row.amount) }}</template>
        </el-table-column>
        <el-table-column label="数量" width="80">
          <template #default="{ row }">{{ row.quantity || 1 }}</template>
        </el-table-column>
        <el-table-column label="状态" width="110">
          <template #default="{ row }">
            <StatusTag :status-map="ORDER_STATUS_MAP" :status="row.status">
              {{ row.status_label || getStatusLabel(ORDER_STATUS_MAP, row.status) }}
            </StatusTag>
          </template>
        </el-table-column>
        <el-table-column label="关联账单" min-width="160">
          <template #default="{ row }">
            <div class="stack-cell">
              <strong>{{ row.invoice?.invoice_no || '--' }}</strong>
              <span v-if="row.invoice?.paid_at">支付：{{ formatDateTime(row.invoice.paid_at) }}</span>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="时间" width="170">
          <template #default="{ row }">{{ formatDateTime(row.created_at) }}</template>
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
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { ElMessage } from 'element-plus'
import { Search } from '@element-plus/icons-vue'
import adminApi from '@/api/admin'
import { formatDateTime } from '@/utils/datetime'
import StatusTag from '@shared/components/StatusTag.vue'
import { ORDER_STATUS_MAP, getStatusLabel, toSelectOptions } from '@shared/statusConfig'

const route = useRoute()
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
  kind: 'all',
  status: '',
  date_range: null,
})

const orderStatusOptions = computed(() => toSelectOptions(ORDER_STATUS_MAP, false))
const mode = computed(() => route.meta.financeOrderMode || 'orders')
const pageMeta = computed(() => {
  if (mode.value === 'renewals') {
    return {
      title: '续费订单',
      description: '查看所有续费产品、关联服务、支付时间与履约状态。',
    }
  }
  if (mode.value === 'addons') {
    return {
      title: '附加配置订单',
      description: '查看流量包等配置升级订单，后续可扩展到磁盘、IP、带宽等配置。',
    }
  }
  return {
    title: '订单管理',
    description: '按履约订单口径查看新购、续费与附加配置订单。',
  }
})

function formatMoney(value) {
  return `¥${Number(value || 0).toFixed(2)}`
}

function orderTypeLabel(type) {
  const map = {
    new: '新购',
    normal: '新购',
    renew: '续费',
    upgrade: '附加配置',
  }
  return map[type] || type || '--'
}

function buildParams() {
  const params = {
    page: pagination.page,
    page_size: pagination.page_size,
  }

  ;['keyword', 'status'].forEach((key) => {
    if (filters[key] !== '' && filters[key] !== null && filters[key] !== undefined) {
      params[key] = filters[key]
    }
  })

  if (mode.value === 'orders' && filters.type) params.type = filters.type
  if (mode.value === 'addons') params.kind = filters.kind || 'all'
  if (Array.isArray(filters.date_range) && filters.date_range.length === 2) {
    params.date_range = filters.date_range
  }

  return params
}

async function loadList() {
  loading.value = true
  try {
    const apiCall = mode.value === 'renewals'
      ? adminApi.financeMenu.renewalOrders
      : mode.value === 'addons'
        ? adminApi.financeMenu.addonOrders
        : adminApi.orders.list
    const res = await apiCall(buildParams())
    list.value = Array.isArray(res.data?.list) ? res.data.list : []
    total.value = Number(res.data?.total || 0)
    pagination.page = Number(res.data?.page || pagination.page)
    pagination.page_size = Number(res.data?.page_size || pagination.page_size)
  } catch (error) {
    ElMessage.error(error?.response?.data?.message || error?.message || '加载订单列表失败')
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
  filters.kind = 'all'
  filters.status = ''
  filters.date_range = null
  pagination.page = 1
  void loadList()
}

watch(mode, () => {
  resetFilters()
})

onMounted(() => loadList())
</script>

<style lang="scss" scoped>
.finance-order-page {
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

.table-pagination {
  display: flex;
  justify-content: flex-end;
  margin-top: 14px;
}
</style>
