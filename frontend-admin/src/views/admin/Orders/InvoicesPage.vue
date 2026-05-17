<template>
  <div class="ledger-page admin-page">
    <section class="admin-page-head">
      <div class="admin-page-heading">
        <span class="admin-page-kicker">财务</span>
        <h2>资金台账中心</h2>
        <p>统一查看充值、账单支付、退款、人工调账与余额变动，减少跨页面拼接财务信息。</p>
      </div>
    </section>

    <section class="ledger-summary-strip">
      <article class="ledger-summary-card">
        <span>资金记录</span>
        <strong>{{ summary.total_count || 0 }}</strong>
        <small>累计台账事件数</small>
      </article>
      <article class="ledger-summary-card">
        <span>累计入账</span>
        <strong class="amount-in">{{ formatMoney(summary.total_in) }}</strong>
        <small>充值、退款与奖励入账</small>
      </article>
      <article class="ledger-summary-card">
        <span>累计出账</span>
        <strong class="amount-out">{{ formatMoney(summary.total_out) }}</strong>
        <small>账单支付与人工扣款</small>
      </article>
      <article class="ledger-summary-card">
        <span>充值入账</span>
        <strong>{{ formatMoney(summary.recharge_in) }}</strong>
        <small>用户充值与人工加款</small>
      </article>
      <article class="ledger-summary-card">
        <span>退款金额</span>
        <strong>{{ formatMoney(summary.refund_in) }}</strong>
        <small>账单退款与原支付逆向</small>
      </article>
      <article class="ledger-summary-card">
        <span>待支付账单</span>
        <strong>{{ formatMoney(summary.unpaid_amount) }}</strong>
        <small>{{ summary.unpaid_count || 0 }} 笔待处理</small>
      </article>
    </section>

    <section class="filter-panel">
      <div class="search-bar ledger-search-bar">
        <el-select v-model="filters.tab" placeholder="台账视图" style="width: 136px;" @change="handleSearch">
          <el-option v-for="item in tabOptions" :key="item.value" :label="item.label" :value="item.value" />
        </el-select>
        <el-input
          v-model="filters.user_id"
          placeholder="用户 ID"
          clearable
          style="width: 120px;"
          @keyup.enter="handleSearch"
        />
        <el-input
          v-model="filters.invoice_no"
          placeholder="账单号"
          clearable
          style="width: 180px;"
          @keyup.enter="handleSearch"
        >
          <template #prefix><el-icon><Document /></el-icon></template>
        </el-input>
        <el-input
          v-model="filters.payment_no"
          placeholder="支付号"
          clearable
          style="width: 180px;"
          @keyup.enter="handleSearch"
        >
          <template #prefix><el-icon><Tickets /></el-icon></template>
        </el-input>
        <el-select v-model="filters.event_type" placeholder="资金类型" clearable style="width: 168px;" @change="handleSearch">
          <el-option v-for="item in eventTypeOptions" :key="item.value" :label="item.label" :value="item.value" />
        </el-select>
        <el-select v-model="filters.direction" placeholder="收支方向" clearable style="width: 132px;" @change="handleSearch">
          <el-option label="收入" value="in" />
          <el-option label="支出" value="out" />
        </el-select>
        <el-select v-model="filters.status" placeholder="状态" clearable style="width: 132px;" @change="handleSearch">
          <el-option label="待支付" :value="0" />
          <el-option label="已支付" :value="1" />
          <el-option label="已取消" :value="2" />
          <el-option label="已逾期" :value="3" />
          <el-option label="已退款" :value="5" />
        </el-select>
        <el-date-picker
          v-model="filters.date_range"
          type="daterange"
          value-format="YYYY-MM-DD"
          range-separator="至"
          start-placeholder="开始日期"
          end-placeholder="结束日期"
          style="width: 250px;"
          @change="handleSearch"
        />
        <el-button type="primary" :icon="Search" @click="handleSearch">搜索</el-button>
        <el-button :icon="Refresh" @click="resetFilters">重置</el-button>
      </div>
    </section>

    <el-card shadow="never" class="ledger-table-card">
      <el-table :data="list" v-loading="loading" stripe row-key="ledger_id">
        <el-table-column prop="ledger_id" label="流水号" width="100" />
        <el-table-column label="用户" min-width="170">
          <template #default="{ row }">
            <div class="user-cell-name">{{ row.user?.display_name || row.user?.nickname || '-' }}</div>
            <div class="user-cell-email">{{ row.user?.email || (row.user?.id ? `用户 #${row.user.id}` : '-') }}</div>
          </template>
        </el-table-column>
        <el-table-column label="资金事项" min-width="220">
          <template #default="{ row }">
            <div class="event-cell">
              <strong>{{ row.display?.title || row.event_type_label || '--' }}</strong>
              <p>{{ row.display?.subtitle || row.remark || '--' }}</p>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="关联对象" min-width="170">
          <template #default="{ row }">
            <div class="event-cell">
              <strong>{{ resolveReferenceNo(row) }}</strong>
              <p>{{ row.display?.scene_label || '--' }}</p>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="渠道" min-width="120">
          <template #default="{ row }">{{ row.display?.channel_label || '--' }}</template>
        </el-table-column>
        <el-table-column label="金额" width="120">
          <template #default="{ row }">
            <span :class="Number(row.change_amount || 0) >= 0 ? 'amount-in' : 'amount-out'">
              {{ Number(row.change_amount || 0) >= 0 ? '+' : '' }}{{ formatMoney(row.change_amount) }}
            </span>
          </template>
        </el-table-column>
        <el-table-column label="余额变更" width="130">
          <template #default="{ row }">{{ formatMoney(row.balance_after) }}</template>
        </el-table-column>
        <el-table-column label="状态" width="100">
          <template #default="{ row }">
            <el-tag effect="plain" size="small" :type="resolveStatusTagType(row)">
              {{ row.display?.status_label || '--' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作人" min-width="120">
          <template #default="{ row }">{{ row.operator || '--' }}</template>
        </el-table-column>
        <el-table-column label="时间" min-width="168">
          <template #default="{ row }">{{ formatDateTime(row.occurred_at || row.created_at) }}</template>
        </el-table-column>
        <el-table-column label="操作" width="120" fixed="right">
          <template #default="{ row }">
            <el-button text type="primary" @click="openDetail(row)">详情</el-button>
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

    <FinanceLedgerDetailDrawer
      :visible="detailVisible"
      :loading="detailLoading"
      :detail="detailRecord"
      @close="handleDetailClose"
    />
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { Document, Refresh, Search, Tickets } from '@element-plus/icons-vue'
import adminApi from '@/api/admin'
import { formatDateTime } from '@/utils/datetime'
import { FINANCE_LEDGER_EVENT_MAP } from '@shared/statusConfig'
import FinanceLedgerDetailDrawer from './components/FinanceLedgerDetailDrawer.vue'

const route = useRoute()
const router = useRouter()

const loading = ref(false)
const summaryLoading = ref(false)
const list = ref([])
const total = ref(0)
const detailVisible = ref(false)
const detailLoading = ref(false)
const detailRecord = ref(null)

const pagination = reactive({
  page: 1,
  page_size: 20,
})

const filters = reactive({
  tab: 'all',
  user_id: '',
  invoice_no: '',
  payment_no: '',
  event_type: '',
  direction: '',
  status: '',
  date_range: null,
})

const summary = reactive({
  total_count: 0,
  total_in: '0.00',
  total_out: '0.00',
  recharge_in: '0.00',
  refund_in: '0.00',
  unpaid_amount: '0.00',
  unpaid_count: 0,
})

const tabOptions = [
  { label: '全部资金', value: 'all' },
  { label: '应收账单', value: 'invoices' },
  { label: '余额收支', value: 'balance' },
  { label: '充值记录', value: 'recharge' },
  { label: '手动调账', value: 'adjustment' },
]

const eventTypeOptions = computed(() => (
  Object.entries(FINANCE_LEDGER_EVENT_MAP).map(([value, config]) => ({
    value,
    label: config.label,
  }))
))

function formatMoney(value) {
  return `¥${Number(value || 0).toFixed(2)}`
}

function buildParams() {
  const params = {
    page: pagination.page,
    page_size: pagination.page_size,
    tab: filters.tab,
  }

  ;['user_id', 'invoice_no', 'payment_no', 'event_type', 'direction', 'status'].forEach((key) => {
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
    const res = await adminApi.financeLedger.list(buildParams())
    list.value = Array.isArray(res.data?.list) ? res.data.list : []
    total.value = Number(res.data?.total || 0)
    pagination.page = Number(res.data?.page || pagination.page)
    pagination.page_size = Number(res.data?.page_size || pagination.page_size)
  } catch (error) {
    ElMessage.error(error?.response?.data?.message || error?.message || '加载资金台账失败')
  } finally {
    loading.value = false
  }
}

async function loadSummary() {
  summaryLoading.value = true
  try {
    const res = await adminApi.financeLedger.summary(buildParams())
    const payload = res.data || {}
    Object.assign(summary, {
      total_count: Number(payload.total_count || 0),
      total_in: payload.total_in ?? '0.00',
      total_out: payload.total_out ?? '0.00',
      recharge_in: payload.recharge_in ?? '0.00',
      refund_in: payload.refund_in ?? '0.00',
      unpaid_amount: payload.unpaid_amount ?? '0.00',
      unpaid_count: Number(payload.unpaid_count || 0),
    })
  } catch (error) {
    ElMessage.error(error?.response?.data?.message || error?.message || '加载资金汇总失败')
  } finally {
    summaryLoading.value = false
  }
}

async function loadAll() {
  await Promise.all([loadList(), loadSummary()])
}

function resolveStatusTagType(row) {
  const status = Number(row?.display?.status)
  if (status === 1) return 'success'
  if (status === 0) return 'warning'
  if (status === 3 || status === 5) return 'danger'
  if (status === 2) return 'info'
  return 'info'
}

function resolveReferenceNo(row) {
  if (row?.invoice?.invoice_no) return row.invoice.invoice_no
  if (row?.payment?.payment_no) return row.payment.payment_no
  return row?.source_id ? `#${row.source_id}` : '--'
}

function handleSearch() {
  pagination.page = 1
  syncQueryFromState()
  void loadAll()
}

function handlePageSizeChange() {
  pagination.page = 1
  void loadList()
}

function resetFilters() {
  filters.tab = 'all'
  filters.user_id = ''
  filters.invoice_no = ''
  filters.payment_no = ''
  filters.event_type = ''
  filters.direction = ''
  filters.status = ''
  filters.date_range = null
  pagination.page = 1
  detailVisible.value = false
  syncQueryFromState()
  void loadAll()
}

async function openDetail(row) {
  detailVisible.value = true
  detailRecord.value = row
  detailLoading.value = true
  try {
    const res = await adminApi.financeLedger.detail(row.ledger_id || row.id)
    detailRecord.value = res.data || row
  } catch (error) {
    ElMessage.error(error?.response?.data?.message || error?.message || '加载资金详情失败')
  } finally {
    detailLoading.value = false
  }
}

function handleDetailClose() {
  detailVisible.value = false
  detailLoading.value = false
}

function syncStateFromQuery() {
  const read = (key, fallback = '') => {
    const value = Array.isArray(route.query[key]) ? route.query[key][0] : route.query[key]
    return value ?? fallback
  }

  filters.invoice_no = String(read('invoice_no', '')).trim()
  filters.user_id = String(read('user_id', '')).trim()
  if (filters.invoice_no === '' && filters.user_id === '') {
    return false
  }

  return true
}

function syncQueryFromState() {
  const nextQuery = { ...route.query }

  if (filters.invoice_no) {
    nextQuery.invoice_no = filters.invoice_no
  } else {
    delete nextQuery.invoice_no
  }

  if (filters.user_id) {
    nextQuery.user_id = filters.user_id
  } else {
    delete nextQuery.user_id
  }

  router.replace({ query: nextQuery })
}

watch(
  () => [route.query.invoice_no, route.query.user_id],
  () => {
    const changed = syncStateFromQuery()
    if (changed) {
      pagination.page = 1
      void loadAll()
    }
  }
)

onMounted(() => {
  syncStateFromQuery()
  void loadAll()
})
</script>

<style lang="scss" scoped>
.ledger-page {
  display: flex;
  flex-direction: column;
  gap: 18px;
}

.ledger-summary-strip {
  display: grid;
  grid-template-columns: repeat(6, minmax(0, 1fr));
  gap: 14px;
}

.ledger-summary-card {
  padding: 18px 18px 16px;
  border-radius: 18px;
  background: linear-gradient(180deg, #ffffff, #f8fafc);
  border: 1px solid rgba(15, 23, 42, 0.06);
  box-shadow: 0 12px 30px rgba(15, 23, 42, 0.05);
}

.ledger-summary-card span {
  display: block;
  font-size: 12px;
  color: $text-color-secondary;
}

.ledger-summary-card strong {
  display: block;
  margin-top: 10px;
  font-size: 24px;
  line-height: 1.1;
  color: $text-color-primary;
}

.ledger-summary-card small {
  display: block;
  margin-top: 8px;
  font-size: 12px;
  color: $text-color-placeholder;
}

.ledger-search-bar {
  align-items: center;
}

.ledger-table-card {
  overflow: hidden;
}

.event-cell {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.event-cell strong {
  color: $text-color-primary;
  font-weight: 600;
}

.event-cell p {
  margin: 0;
  font-size: 12px;
  color: $text-color-placeholder;
}

.user-cell-name {
  color: $text-color-primary;
  font-weight: 500;
}

.user-cell-email {
  margin-top: 2px;
  color: $text-color-placeholder;
  font-size: 12px;
}

.table-pagination {
  display: flex;
  justify-content: flex-end;
  margin-top: 16px;
}

.amount-in {
  color: $color-success;
  font-weight: 700;
}

.amount-out {
  color: $color-danger;
  font-weight: 700;
}

@media (max-width: 1500px) {
  .ledger-summary-strip {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
}

@media (max-width: 768px) {
  .ledger-summary-strip {
    grid-template-columns: 1fr;
  }
}
</style>
