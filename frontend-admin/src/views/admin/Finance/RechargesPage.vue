<template>
  <div class="page-container admin-page finance-recharge-page">
    <section class="admin-page-head">
      <div class="admin-page-heading">
        <span class="admin-page-kicker">财务</span>
        <h2>充值管理</h2>
        <p>查看用户余额充值账单、支付渠道、到账金额与支付时间。</p>
      </div>
    </section>

    <section class="filter-panel">
      <div class="search-bar">
        <el-input v-model="filters.keyword" placeholder="搜索账单号 / 支付号 / 用户" clearable @keyup.enter="handleSearch">
          <template #prefix><el-icon><Search /></el-icon></template>
        </el-input>
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
        <el-table-column prop="invoice_no" label="充值账单" min-width="170" />
        <el-table-column label="用户" min-width="170">
          <template #default="{ row }">
            <div class="stack-cell">
              <strong>{{ row.user?.nickname || row.user?.email || '--' }}</strong>
              <span>{{ row.user?.email || '--' }}</span>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="支付记录" min-width="180">
          <template #default="{ row }">
            <div class="stack-cell">
              <strong>{{ row.payment?.payment_no || '--' }}</strong>
              <span>{{ row.payment?.gateway || '--' }} {{ row.payment?.trade_no || '' }}</span>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="金额" width="120">
          <template #default="{ row }">{{ formatMoney(row.amount) }}</template>
        </el-table-column>
        <el-table-column label="到账" width="120">
          <template #default="{ row }">{{ formatMoney(row.paid_amount) }}</template>
        </el-table-column>
        <el-table-column label="状态" width="110">
          <template #default="{ row }">
            <StatusTag :status-map="INVOICE_STATUS_MAP" :status="row.status">
              {{ row.status_label || getStatusLabel(INVOICE_STATUS_MAP, row.status) }}
            </StatusTag>
          </template>
        </el-table-column>
        <el-table-column label="创建时间" width="170">
          <template #default="{ row }">{{ formatDateTime(row.created_at) }}</template>
        </el-table-column>
        <el-table-column label="支付时间" width="170">
          <template #default="{ row }">{{ formatDateTime(row.paid_at) }}</template>
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
import { computed, onMounted, reactive, ref } from 'vue'
import { ElMessage } from 'element-plus'
import { Search } from '@element-plus/icons-vue'
import adminApi from '@/api/admin'
import { formatDateTime } from '@/utils/datetime'
import StatusTag from '@shared/components/StatusTag.vue'
import { INVOICE_STATUS_MAP, getStatusLabel, toSelectOptions } from '@shared/statusConfig'

const loading = ref(false)
const list = ref([])
const total = ref(0)

const pagination = reactive({ page: 1, page_size: 20 })
const filters = reactive({ keyword: '', status: '', date_range: null })
const invoiceStatusOptions = computed(() => toSelectOptions(INVOICE_STATUS_MAP, false))

function formatMoney(value) {
  return `¥${Number(value || 0).toFixed(2)}`
}

function buildParams() {
  const params = { page: pagination.page, page_size: pagination.page_size }
  if (filters.keyword) params.keyword = filters.keyword
  if (filters.status !== '') params.status = filters.status
  if (Array.isArray(filters.date_range) && filters.date_range.length === 2) params.date_range = filters.date_range
  return params
}

async function loadList() {
  loading.value = true
  try {
    const res = await adminApi.financeMenu.recharges(buildParams())
    list.value = Array.isArray(res.data?.list) ? res.data.list : []
    total.value = Number(res.data?.total || 0)
    pagination.page = Number(res.data?.page || pagination.page)
    pagination.page_size = Number(res.data?.page_size || pagination.page_size)
  } catch (error) {
    ElMessage.error(error?.response?.data?.message || error?.message || '加载充值记录失败')
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
  filters.status = ''
  filters.date_range = null
  pagination.page = 1
  void loadList()
}

onMounted(() => loadList())
</script>

<style lang="scss" scoped>
.finance-recharge-page {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.stack-cell {
  display: flex;
  flex-direction: column;
  gap: 2px;

  strong {
    color: $text-color-primary;
    font-weight: 600;
  }

  span {
    color: $text-color-placeholder;
    font-size: 12px;
  }
}

.table-pagination {
  display: flex;
  justify-content: flex-end;
  margin-top: 14px;
}
</style>
