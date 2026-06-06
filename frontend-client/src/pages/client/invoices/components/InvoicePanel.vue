<template>
  <div :class="['client-page', 'invoice-panel-page', pageClass]">
    <section v-if="title || description" class="client-page-head">
      <div class="client-page-heading">
        <h2 v-if="title">{{ title }}</h2>
        <p v-if="description">{{ description }}</p>
      </div>
    </section>

    <div class="toolbar-grid" :class="{ 'toolbar-grid--without-type': !showTypeSelector }">
      <el-input v-model="filters.keyword" :placeholder="keywordPlaceholder" clearable @keyup.enter="handleSearch" />
      <el-select v-model="filters.status" placeholder="全部状态" clearable @change="handleSearch">
        <el-option label="待支付" :value="0" />
        <el-option label="已支付" :value="1" />
        <el-option label="已取消" :value="2" />
        <el-option label="已过期" :value="3" />
        <el-option label="已退款" :value="5" />
      </el-select>
      <el-select v-if="showTypeSelector" v-model="filters.type" placeholder="全部类型" clearable @change="handleSearch">
        <el-option label="新购账单" value="new" />
        <el-option label="续费账单" value="renew" />
        <el-option label="升级账单" value="upgrade" />
        <el-option label="流量包" value="traffic" />
      </el-select>
    </div>

    <el-table v-if="!isMobileScreen" :data="list" v-loading="loading">
      <el-table-column label="账单号" min-width="170">
        <template #default="{ row }">{{ row.invoice_no || `#${row.id}` }}</template>
      </el-table-column>
      <el-table-column label="商品" min-width="220">
        <template #default="{ row }">
          <div class="stack-cell">
            <strong>{{ resolveInvoiceTitle(row) }}</strong>
            <span>{{ resolveInvoiceSubtitle(row) }}</span>
          </div>
        </template>
      </el-table-column>
      <el-table-column label="金额" min-width="120">
        <template #default="{ row }">¥ {{ row.amount || '0.00' }}</template>
      </el-table-column>
      <el-table-column label="待付" min-width="120">
        <template #default="{ row }">¥ {{ row.payable_amount || '0.00' }}</template>
      </el-table-column>
      <el-table-column label="状态" min-width="120">
        <template #default="{ row }">
          <el-tag :type="resolveInvoiceTagType(row.status)" effect="light">
            {{ row.status_label || '--' }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column label="创建时间" min-width="180" prop="created_at" />
      <el-table-column label="操作" width="80" fixed="right">
        <template #default="{ row }">
          <el-button text type="primary" size="small" @click="openDetail(row)">详情</el-button>
        </template>
      </el-table-column>
    </el-table>

    <div v-else class="mobile-invoice-list" v-loading="loading">
      <div
        v-for="row in list"
        :key="row.id"
        class="mobile-invoice-card"
        @click="openDetail(row)"
      >
        <div class="card-row card-row--top">
          <span class="invoice-no">{{ row.invoice_no || `#${row.id}` }}</span>
          <el-tag :type="resolveInvoiceTagType(row.status)" size="small" effect="light">
            {{ row.status_label || '--' }}
          </el-tag>
        </div>
        <div class="invoice-product-title">
          {{ resolveInvoiceTitle(row) }}
        </div>
        <div class="invoice-spec">
          {{ resolveInvoiceSubtitle(row) }}
        </div>
        <div class="card-row card-row--bottom">
          <span class="invoice-amount-row">
            账单：<strong>¥{{ row.amount || '0.00' }}</strong>
            <span v-if="row.payable_amount && Number(row.payable_amount) > 0" class="payable-sub">
              （待付：<strong class="payable-orange">¥{{ row.payable_amount }}</strong>）
            </span>
          </span>
          <span class="invoice-time">{{ row.created_at }}</span>
        </div>
      </div>
    </div>

    <el-empty v-if="!loading && !list.length" :description="emptyDescription" />

    <div v-if="total > 0" class="pager-wrap">
      <el-pagination
        v-model:current-page="page"
        v-model:page-size="pageSize"
        :page-sizes="pageSizeOptions"
        :total="total"
        layout="total, sizes, prev, pager, next"
        @current-change="loadList"
        @size-change="handlePageSizeChange"
      />
    </div>

    <el-drawer v-model="detailVisible" title="账单详情" size="480px" destroy-on-close>
      <div class="invoice-detail-content" v-if="currentRow">
        <el-descriptions :column="1" border>
          <el-descriptions-item label="账单号">{{ currentRow.invoice_no || `#${currentRow.id}` }}</el-descriptions-item>
          <el-descriptions-item label="类型">{{ currentRow.type_label || '--' }}</el-descriptions-item>
          <el-descriptions-item label="商品">{{ resolveInvoiceTitle(currentRow) }}</el-descriptions-item>
          <el-descriptions-item label="规格">{{ resolveInvoiceSubtitle(currentRow) }}</el-descriptions-item>
          <el-descriptions-item label="状态">
            <el-tag :type="resolveInvoiceTagType(currentRow.status)" effect="light">
              {{ currentRow.status_label || '--' }}
            </el-tag>
          </el-descriptions-item>
          <el-descriptions-item label="账单金额">¥{{ currentRow.amount || '0.00' }}</el-descriptions-item>
          <el-descriptions-item label="优惠金额">¥{{ currentRow.discount || '0.00' }}</el-descriptions-item>
          <el-descriptions-item label="已付金额">¥{{ currentRow.paid_amount || '0.00' }}</el-descriptions-item>
          <el-descriptions-item label="待付金额">¥{{ currentRow.payable_amount || '0.00' }}</el-descriptions-item>
          <el-descriptions-item label="创建时间">{{ currentRow.created_at || '--' }}</el-descriptions-item>
          <el-descriptions-item label="截止时间">{{ currentRow.due_date || '--' }}</el-descriptions-item>
          <el-descriptions-item v-if="currentRow.paid_at" label="支付时间">{{ currentRow.paid_at }}</el-descriptions-item>
        </el-descriptions>

        <div v-if="currentRow.status === 0 || currentRow.status === 3" class="detail-actions">
          <el-button type="primary" @click="goToPay(currentRow)">去支付</el-button>
          <el-button type="danger" plain @click="handleCancel(currentRow)">取消账单</el-button>
        </div>
      </div>
    </el-drawer>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { useInvoices } from '@/composables/useInvoices'
import { useViewport } from '@/composables/useViewport'
import clientApi from '@/api/client'

const props = defineProps({
  title: {
    type: String,
    default: '',
  },
  description: {
    type: String,
    default: '',
  },
  fixedTypes: {
    type: [Array, String],
    default: '',
  },
  showTypeFilter: {
    type: Boolean,
    default: true,
  },
  keywordPlaceholder: {
    type: String,
    default: '搜索账单号或商品名',
  },
  emptyDescription: {
    type: String,
    default: '暂无账单记录',
  },
  initialPageSize: {
    type: Number,
    default: 10,
  },
  pageSizeOptions: {
    type: Array,
    default: () => [10, 20, 50],
  },
  pageClass: {
    type: String,
    default: '',
  },
})

const router = useRouter()
const { isMobileScreen } = useViewport()
const {
  loading,
  list,
  total,
  page,
  pageSize,
  filters,
  resolveInvoiceTagType,
  loadList,
  loadData,
  handleSearch,
  handlePageSizeChange,
} = useInvoices({
  fixedTypes: () => props.fixedTypes,
  pageSize: props.initialPageSize,
})

const detailVisible = ref(false)
const currentRow = ref(null)

const showTypeSelector = computed(() => {
  return props.showTypeFilter && !hasFixedTypes(props.fixedTypes)
})

function normalizeDisplayText(value) {
  if (typeof value === 'string') return value.trim()
  if (typeof value === 'number') return String(value)
  return ''
}

function pickDisplayText(...values) {
  for (const value of values) {
    const text = normalizeDisplayText(value)
    if (text) return text
  }

  return '--'
}

function normalizeFixedTypes(value) {
  const rawTypes = Array.isArray(value) ? value : String(value || '').split(',')

  return rawTypes
    .map((item) => normalizeDisplayText(item))
    .filter(Boolean)
}

function hasFixedTypes(value) {
  return normalizeFixedTypes(value).length > 0
}

function resolveSummaryField(row, field) {
  const summaryValue = row?.summary
  if (!summaryValue || typeof summaryValue !== 'object' || Array.isArray(summaryValue)) {
    return ''
  }

  return normalizeDisplayText(summaryValue[field])
}

function hasProductBinding(row) {
  return Number(row?.product?.id || row?.product_id || 0) > 0
}

function resolveInvoiceTitle(row) {
  if (hasProductBinding(row)) {
    return pickDisplayText(
      row?.product_spec_display,
      row?.combined_display_name,
      row?.product_display_name,
      resolveSummaryField(row, 'headline'),
      row?.type_label,
    )
  }

  return pickDisplayText(
    row?.combined_display_name,
    row?.product_display_name,
    resolveSummaryField(row, 'headline'),
    row?.type_label,
  )
}

function resolveInvoiceSubtitle(row) {
  const title = resolveInvoiceTitle(row)
  const combinedDisplayName = normalizeDisplayText(row?.combined_display_name)
  const productDisplayName = normalizeDisplayText(row?.product_display_name)

  if (hasProductBinding(row)) {
    return pickDisplayText(
      combinedDisplayName !== title ? combinedDisplayName : '',
      productDisplayName !== title ? productDisplayName : '',
      resolveSummaryField(row, 'subheadline'),
      resolveSummaryField(row, 'remark'),
      row?.type_label,
    )
  }

  return pickDisplayText(
    row?.product_spec_display,
    resolveSummaryField(row, 'subheadline'),
    resolveSummaryField(row, 'remark'),
    row?.type_label,
  )
}

function openDetail(row) {
  currentRow.value = row
  detailVisible.value = true
}

function goToPay(row) {
  detailVisible.value = false
  router.push(`/client/invoices/${row.id}`)
}

async function handleCancel(row) {
  try {
    await ElMessageBox.confirm('确定取消该账单？取消后不可恢复。', '取消账单', { type: 'warning' })
    await clientApi.cancelInvoice(row.id)
    ElMessage.success('账单已取消')
    detailVisible.value = false
    void loadList()
  } catch (e) {
    if (e !== 'cancel') ElMessage.error(e?.message || '取消失败')
  }
}

onMounted(() => {
  void loadData()
})
</script>

<style scoped lang="scss">
.invoice-panel-page {
  gap: 20px;
}

.invoice-detail-content {
  .detail-actions {
    margin-top: 20px;
    display: flex;
    gap: 12px;
  }
}

.toolbar-grid {
  display: grid;
  gap: 16px;
  grid-template-columns: 1.3fr 180px 180px;
}

.toolbar-grid--without-type {
  grid-template-columns: minmax(220px, 1.3fr) 180px;
  justify-content: start;
}

.stack-cell {
  display: flex;
  flex-direction: column;
  gap: 4px;

  strong {
    color: $text-color-primary;
    font-size: 14px;
    font-weight: 600;
  }

  span {
    color: $text-color-secondary;
    font-size: 12px;
  }
}

.pager-wrap {
  display: flex;
  justify-content: flex-end;
  margin-top: 16px;
}

@media (max-width: 960px) {
  .toolbar-grid,
  .toolbar-grid--without-type {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 767px) {
  .toolbar-grid,
  .toolbar-grid--without-type {
    grid-template-columns: 1fr;
  }

  .pager-wrap {
    justify-content: flex-start;
  }
}

.mobile-invoice-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
  margin-top: 10px;
}

.mobile-invoice-card {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding: 14px 16px;
  border: 1px solid $border-color;
  border-radius: 12px;
  background: $bg-color-card;
  box-shadow: $shadow-sm;
  cursor: pointer;
  transition: transform 0.15s ease, background-color 0.15s ease;

  &:active {
    transform: scale(0.99);
    background: $bg-color-hover;
  }

  .card-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
  }

  .invoice-no {
    color: $text-color-placeholder;
    font-size: 12px;
    font-weight: 600;
  }

  .invoice-product-title {
    color: $text-color-primary;
    font-size: 14px;
    font-weight: 600;
    line-height: 1.4;
    text-align: left;
  }

  .invoice-spec {
    color: $text-color-secondary;
    font-size: 12px;
    line-height: 1.4;
    text-align: left;
    word-break: break-all;
  }

  .invoice-amount-row {
    color: $text-color-secondary;
    font-size: 12px;
    text-align: left;

    strong {
      color: $text-color-primary;
      font-weight: 600;
    }

    .payable-sub {
      color: $text-color-placeholder;
      font-size: 11px;
    }

    .payable-orange {
      color: $color-accent-orange;
      font-weight: 600;
    }
  }

  .invoice-time {
    color: $text-color-placeholder;
    font-size: 11px;
  }
}
</style>
