<template>
  <div class="ledger-page admin-page">
    <section class="admin-page-head">
      <div class="admin-page-heading">
        <span class="admin-page-kicker">财务</span>
        <h2>资金台账中心</h2>
        <p>统一查看充值、账单支付、退款、人工调账与余额变动，减少跨页面拼接财务信息。</p>
      </div>
    </section>

    <section class="filter-panel">
      <div class="ledger-filter-top">
        <el-select v-model="filters.tab" size="small" class="ledger-select--compact" placeholder="全部资金" @change="handleSearch">
          <el-option v-for="item in tabOptions" :key="item.value" :label="item.label" :value="item.value" />
        </el-select>
        <el-select v-model="filters.event_type" size="small" class="ledger-select--compact" placeholder="类型" clearable @change="handleSearch">
          <el-option v-for="item in eventTypeOptions" :key="item.value" :label="item.label" :value="item.value" />
        </el-select>
        <el-select v-model="filters.direction" size="small" class="ledger-select--compact" placeholder="收支" clearable @change="handleSearch">
          <el-option label="收入" value="in" />
          <el-option label="支出" value="out" />
        </el-select>
        <el-select v-model="filters.status" size="small" class="ledger-select--compact" placeholder="状态" clearable @change="handleSearch">
          <el-option label="待支付" :value="0" />
          <el-option label="已支付" :value="1" />
          <el-option label="已取消" :value="2" />
          <el-option label="已逾期" :value="3" />
          <el-option label="已退款" :value="5" />
        </el-select>
      </div>
      <div class="ledger-filter-bottom">
        <el-input
          v-model="filters.keyword"
          size="small"
          class="ledger-keyword-input"
          placeholder="搜索用户 ID / 账单号 / 支付号"
          clearable
          @keyup.enter="handleSearch"
        >
          <template #prefix><el-icon><Search /></el-icon></template>
        </el-input>
        <el-date-picker
          v-model="filters.date_range"
          size="small"
          type="daterange"
          value-format="YYYY-MM-DD"
          range-separator="至"
          start-placeholder="开始日期"
          end-placeholder="结束日期"
          clearable
          class="ledger-date"
          @change="handleSearch"
        />
      </div>
    </section>

    <el-card shadow="never" class="ledger-table-card">
      <!-- 桌面端：表格 -->
      <el-table :data="list" v-loading="loading" stripe row-key="ledger_id" class="ledger-desktop-table">
        <el-table-column prop="ledger_id" label="流水号" width="72" />
        <el-table-column label="用户" min-width="130">
          <template #default="{ row }">
            <span class="user-cell-name">{{ row.user?.display_name || row.user?.nickname || `用户 #${row.user?.id}` || '-' }}</span>
          </template>
        </el-table-column>
        <el-table-column label="资金事项" min-width="140">
          <template #default="{ row }">
            <div class="event-cell">
              <strong>{{ row.display?.title || row.event_type_label || '--' }}</strong>
              <p>{{ row.display?.subtitle || row.remark || '--' }}</p>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="关联对象" min-width="120" class-name="hide-on-narrow">
          <template #default="{ row }">
            <div class="event-cell">
              <strong>{{ resolveReferenceNo(row) }}</strong>
              <p>{{ row.display?.scene_label || '--' }}</p>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="渠道" width="90" class-name="hide-on-narrow">
          <template #default="{ row }">{{ row.display?.channel_label || '--' }}</template>
        </el-table-column>
        <el-table-column label="金额" width="100">
          <template #default="{ row }">
            <span :class="Number(row.change_amount || 0) >= 0 ? 'amount-in' : 'amount-out'">
              {{ Number(row.change_amount || 0) >= 0 ? '+' : '' }}{{ formatMoney(row.change_amount) }}
            </span>
          </template>
        </el-table-column>
        <el-table-column label="余额" width="88" class-name="hide-on-narrow">
          <template #default="{ row }">{{ formatMoney(row.balance_after) }}</template>
        </el-table-column>
        <el-table-column label="状态" width="76">
          <template #default="{ row }">
            <el-tag effect="plain" size="small" :type="resolveStatusTagType(row)">
              {{ row.display?.status_label || '--' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作人" width="88" class-name="hide-on-narrow">
          <template #default="{ row }">{{ row.operator || '--' }}</template>
        </el-table-column>
        <el-table-column label="时间" width="148">
          <template #default="{ row }">{{ formatDateTime(row.occurred_at || row.created_at) }}</template>
        </el-table-column>
        <el-table-column label="操作" width="68" fixed="right">
          <template #default="{ row }">
            <el-button text type="primary" size="small" @click="openDetail(row)">详情</el-button>
          </template>
        </el-table-column>
      </el-table>

      <!-- 手机端：卡片列表 -->
      <div v-loading="loading" class="ledger-mobile-cards">
        <div v-for="row in list" :key="row.ledger_id" class="ledger-card" @click="openDetail(row)">
          <div class="ledger-card-row">
            <div class="ledger-card-left">
              <strong class="ledger-card-title">{{ row.display?.title || row.event_type_label || '--' }}</strong>
              <span class="ledger-card-user">{{ row.user?.display_name || row.user?.nickname || `用户 #${row.user?.id}` || '-' }}</span>
            </div>
            <div class="ledger-card-right">
              <span :class="Number(row.change_amount || 0) >= 0 ? 'amount-in' : 'amount-out'">
                {{ Number(row.change_amount || 0) >= 0 ? '+' : '' }}{{ formatMoney(row.change_amount) }}
              </span>
            </div>
          </div>
          <div class="ledger-card-meta">
            <el-tag effect="plain" size="small" :type="resolveStatusTagType(row)">
              {{ row.display?.status_label || '--' }}
            </el-tag>
            <span class="ledger-card-desc">{{ row.display?.subtitle || row.remark || '' }}</span>
            <span class="ledger-card-time">{{ formatDateTime(row.occurred_at || row.created_at) }}</span>
          </div>
        </div>
        <div v-if="!loading && list.length === 0" class="ledger-empty">暂无数据</div>
      </div>

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
import { Search } from '@element-plus/icons-vue'
import adminApi from '@/api/admin'
import { formatDateTime } from '@/utils/datetime'
import { FINANCE_LEDGER_EVENT_MAP } from '@shared/statusConfig'
import FinanceLedgerDetailDrawer from './components/FinanceLedgerDetailDrawer.vue'

const route = useRoute()
const router = useRouter()

const loading = ref(false)
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
  keyword: '',
  event_type: '',
  direction: '',
  status: '',
  date_range: null,
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

  ;['keyword', 'event_type', 'direction', 'status'].forEach((key) => {
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

async function loadAll() {
  await loadList()
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
  filters.keyword = ''
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

  filters.keyword = String(read('keyword', '')).trim()
  return filters.keyword !== ''
}

function syncQueryFromState() {
  const nextQuery = { ...route.query }

  if (filters.keyword) {
    nextQuery.keyword = filters.keyword
  } else {
    delete nextQuery.keyword
  }

  router.replace({ query: nextQuery })
}

watch(
  () => route.query.keyword,
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
  gap: 14px;
}

.filter-panel {
  padding: 16px;
  border: 1px solid $border-color;
  border-radius: $base-border-radius;
  background: $bg-color-card;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

// ========== 第一行：4 个下拉，居中 ==========
.ledger-filter-top {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  align-items: center;
  gap: 6px;

  .ledger-select--compact {
    flex-shrink: 0;
    width: 76px;

    // 压缩选中文字的显示空间
    :deep(.el-select__placeholder),
    :deep(.el-select__selected-item) {
      font-size: 12px;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
  }
}

// ========== 第二行：关键词 + 日期 ==========
.ledger-filter-bottom {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  align-items: center;
  gap: 8px;

  .ledger-keyword-input {
    flex: 1 1 180px;
    min-width: 150px;
    max-width: 320px;
  }

  .ledger-date {
    flex: 0 1 220px;
    min-width: 180px;
  }
}

// ========== 窄屏 ==========
@include tablet-and-below {
  .ledger-filter-top {
    .ledger-select--compact {
      flex: 1 1 auto;
      width: auto;
      min-width: 70px;
    }
  }

  .ledger-filter-bottom {
    .ledger-keyword-input,
    .ledger-date {
      flex: 1 1 100%;
      min-width: 0;
      max-width: none;
    }
  }
}

.ledger-table-card {
  overflow: hidden;

  :deep(.el-card__body) {
    padding: 12px;
  }

  :deep(.el-table) {
    font-size: 12px;
  }

  :deep(.el-table__cell) {
    padding: 6px 0;
  }
}

// 手机端卡片样式
.ledger-mobile-cards {
  display: none;
}

@include tablet-and-below {
  .filter-panel {
    padding: 12px;
  }

  .ledger-desktop-table {
    display: none !important;
  }

  .ledger-mobile-cards {
    display: flex;
    flex-direction: column;
    gap: 8px;
  }

  .ledger-card {
    padding: 12px 14px;
    border: 1px solid $border-color;
    border-radius: $sm-border-radius;
    background: $bg-color-card;
    cursor: pointer;
    transition: border-color $duration-fast $ease-standard;

    &:active {
      border-color: $color-primary;
    }
  }

  .ledger-card-row {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 12px;
  }

  .ledger-card-left {
    display: flex;
    flex-direction: column;
    gap: 2px;
    min-width: 0;
    flex: 1;
  }

  .ledger-card-title {
    font-size: 13px;
    font-weight: 600;
    color: $text-color-primary;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  .ledger-card-user {
    font-size: 12px;
    color: $text-color-placeholder;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  .ledger-card-right {
    flex-shrink: 0;
    text-align: right;

    .amount-in,
    .amount-out {
      font-size: 15px;
      font-weight: 700;
    }
  }

  .ledger-card-meta {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-top: 8px;
    flex-wrap: wrap;
  }

  .ledger-card-desc {
    font-size: 11px;
    color: $text-color-placeholder;
    flex: 1;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    min-width: 0;
  }

  .ledger-card-time {
    font-size: 11px;
    color: $text-color-placeholder;
    flex-shrink: 0;
    white-space: nowrap;
  }

  .ledger-empty {
    text-align: center;
    padding: 32px 0;
    color: $text-color-placeholder;
    font-size: 13px;
  }

  .ledger-table-card {
    :deep(.el-card__body) {
      padding: 8px;
    }
  }
}

.event-cell {
  display: flex;
  flex-direction: column;
  gap: 2px;
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
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  display: block;
}

.user-cell-email {
  margin-top: 2px;
  color: $text-color-placeholder;
  font-size: 12px;
  display: none;
}

.table-pagination {
  display: flex;
  justify-content: flex-end;
  margin-top: 12px;
}

@include tablet-and-below {
  .table-pagination {
    margin-top: 8px;
    justify-content: center;
  }
}

.amount-in {
  color: $color-success;
  font-weight: 700;
  font-variant-numeric: tabular-nums;
  font-feature-settings: "tnum";
}

.amount-out {
  color: $color-danger;
  font-weight: 700;
  font-variant-numeric: tabular-nums;
  font-feature-settings: "tnum";
}

</style>
