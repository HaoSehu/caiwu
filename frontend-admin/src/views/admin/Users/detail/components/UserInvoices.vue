<template>
  <div>
    <div class="toolbar compact">
      <el-select v-model="state.filters.status" placeholder="状态" clearable>
        <el-option label="待支付" :value="0" />
        <el-option label="已支付" :value="1" />
        <el-option label="已取消" :value="2" />
        <el-option label="已逾期" :value="3" />
        <el-option label="已退款" :value="5" />
      </el-select>
      <el-select v-model="state.filters.type" placeholder="类型" clearable>
        <el-option label="新购" value="new" />
        <el-option label="续费" value="renew" />
        <el-option label="充值" value="recharge" />
        <el-option label="扣款" value="deduction" />
        <el-option label="推荐奖励" value="referral_credit" />
        <el-option label="手工" value="manual" />
      </el-select>
      <el-button type="primary" @click="emit('search')">查询</el-button>
      <el-button @click="emit('reset')">重置</el-button>
    </div>

    <el-table :data="state.list" v-loading="state.loading" stripe :row-key="resolveRowKey">
      <el-table-column prop="invoice_no" label="账单编号" min-width="180" />
      <el-table-column label="生成时间" min-width="160">
        <template #default="{ row }">{{ formatDateTime(row.created_at) }}</template>
      </el-table-column>
      <el-table-column label="到期时间" min-width="120">
        <template #default="{ row }">{{ row.due_date || '-' }}</template>
      </el-table-column>
      <el-table-column label="支付时间" min-width="160">
        <template #default="{ row }">{{ formatDateTime(row.paid_at) }}</template>
      </el-table-column>
      <el-table-column label="金额" width="110">
        <template #default="{ row }">{{ formatMoney(row.amount) }}</template>
      </el-table-column>
      <el-table-column label="支付方式" min-width="120">
        <template #default="{ row }">{{ row.payment_summary?.gateway_label || '--' }}</template>
      </el-table-column>
      <el-table-column label="状态" width="110">
        <template #default="{ row }">
          <el-tag :type="invoiceStatusTagType(row)" size="small" effect="plain">
            {{ row.status_label || resolveInvoiceStatus(row.status) }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column label="账单类型" width="120">
        <template #default="{ row }">{{ row.type_label || resolveInvoiceType(row.type) }}</template>
      </el-table-column>
      <el-table-column label="操作" :width="isMobile ? 60 : 150">
        <template #default="{ row }">
          <div v-if="!isMobile" class="row-actions">
            <span class="action-link action-link--primary" @click="openDetailDrawer(row)">详情</span>

            <el-popconfirm
              v-if="isCancelableRow(row)"
              title="取消账单后将关闭关联流程，确认继续吗？"
              @confirm="handleCancel(row)"
            >
              <template #reference>
                <span class="action-link action-link--danger">取消</span>
              </template>
            </el-popconfirm>
          </div>
          <el-dropdown v-else trigger="click" @command="(cmd) => handleInvoiceAction(cmd, row)">
            <span class="action-link">···</span>
            <template #dropdown>
              <el-dropdown-menu>
                <el-dropdown-item command="detail">详情</el-dropdown-item>
                <el-dropdown-item v-if="isCancelableRow(row)" command="cancel" divided>取消</el-dropdown-item>
              </el-dropdown-menu>
            </template>
          </el-dropdown>
        </template>
      </el-table-column>
    </el-table>

    <div class="pager">
      <el-pagination
        :current-page="state.page"
        :page-size="state.pageSize"
        :total="state.total"
        :page-sizes="[10, 20, 50]"
        layout="total, sizes, prev, pager, next"
        @current-change="handlePageChange"
        @size-change="handlePageSizeChange"
      />
    </div>

    <InvoiceDetailDrawer
      :state="detailDrawerState"
      :format-money="formatMoney"
      @close="closeDetailDrawer"
      @reload="reloadDetailDrawer"
      @cancel="handleDrawerCancel"
    />
  </div>
</template>

<script setup>
import { computed, reactive } from 'vue'
import { formatDateTime } from '@/utils/datetime'
import { ElMessage } from 'element-plus'
import adminApi from '@/api/admin'
import userApi from '@/api/user'
import { useUserStore } from '@/stores/user'
import { useResponsive } from '@/composables/useResponsive'
import InvoiceDetailDrawer from './InvoiceDetailDrawer.vue'

const { isMobile } = useResponsive()

const props = defineProps({
  userId: {
    type: Number,
    required: true,
  },
  state: {
    type: Object,
    required: true,
  },
  formatMoney: {
    type: Function,
    required: true,
  },
  resolveInvoiceStatus: {
    type: Function,
    required: true,
  },
  resolveInvoiceType: {
    type: Function,
    required: true,
  },
})

const emit = defineEmits(['search', 'reset', 'reload', 'detail-refresh'])
const userStore = useUserStore()

const loadingMap = reactive({})

const detailDrawerState = reactive({
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

const canManageOrder = computed(() => hasPermission('order.manage'))

function resolveRowKey(row) {
  return row?.id || row?.invoice_no || `${row?.created_at || ''}-${row?.amount || ''}-${row?.status || ''}`
}

function handlePageChange(page) {
  props.state.page = page
  emit('reload')
}

function handlePageSizeChange(pageSize) {
  props.state.pageSize = pageSize
  props.state.page = 1
  emit('reload')
}

function hasPermission(permission) {
  return userStore.permissions.includes('*') || userStore.permissions.includes(permission)
}

function invoiceStatusTagType(row) {
  const status = Number(row?.raw_status ?? row?.status ?? -1)
  if (status === 1) return 'success'
  if (status === 0) return 'warning'
  if (status === 3 || status === 5) return 'danger'
  return 'info'
}

function handleInvoiceAction(command, row) {
  if (command === 'detail') {
    openDetailDrawer(row)
  } else if (command === 'cancel') {
    handleCancel(row)
  }
}

function isCancelableRow(row) {
  const orderId = Number(row?.order?.id || row?.order_id || 0)
  const orderStatus = Number(row?.order?.status ?? 0)
  const invoiceStatus = Number(row?.raw_status ?? row?.status ?? -1)

  return canManageOrder.value
    && orderId > 0
    && orderStatus === 0
    && [0, 3].includes(invoiceStatus)
}

function normalizeDetailPayload(payload, fallbackRow = null) {
  const invoice = payload?.invoice && typeof payload.invoice === 'object'
    ? payload.invoice
    : payload

  if (fallbackRow) {
        const rowSummary = fallbackRow?.summary || {}
        return {
          invoice: {
            ...invoice,
        id: invoice?.id || fallbackRow.id,
        invoice_no: invoice?.invoice_no || fallbackRow.invoice_no,
        type: invoice?.type || fallbackRow.type,
        type_label: invoice?.type_label || fallbackRow.type_label,
        status: invoice?.status ?? fallbackRow.status,
        status_label: invoice?.status_label || fallbackRow.status_label,
        amount: invoice?.amount || fallbackRow.amount,
        paid_amount: invoice?.paid_amount || fallbackRow.paid_amount,
        payable_amount: invoice?.payable_amount || fallbackRow.payable_amount,
        created_at: invoice?.created_at || fallbackRow.created_at,
        paid_at: invoice?.paid_at || fallbackRow.paid_at,
        due_date: invoice?.due_date || fallbackRow.due_date,
        order_id: invoice?.order_id || fallbackRow?.order_id || fallbackRow?.order?.id || 0,
        order: invoice?.order || fallbackRow?.order || null,
            product: invoice?.product || fallbackRow?.product || null,
            payment_summary: invoice?.payment_summary || fallbackRow?.payment_summary || {},
            summary: invoice?.summary || rowSummary,
            scene: invoice?.scene || fallbackRow?.scene || rowSummary?.scene || {},
          },
      payments: Array.isArray(payload?.payments) ? payload.payments : [],
      items: Array.isArray(payload?.items) ? payload.items : [],
      logs: Array.isArray(payload?.logs) ? payload.logs : [],
    }
  }

  return {
    invoice: invoice || {},
    payments: Array.isArray(payload?.payments) ? payload.payments : [],
    items: Array.isArray(payload?.items) ? payload.items : [],
    logs: Array.isArray(payload?.logs) ? payload.logs : [],
  }
}

async function loadInvoiceDetail(invoiceId) {
  if (!props.userId || !invoiceId) return

  detailDrawerState.loading = true
  try {
    const res = await userApi.invoiceDetail(props.userId, invoiceId)
    detailDrawerState.detail = normalizeDetailPayload(res.data || {})
  } catch (error) {
    ElMessage.error(error?.response?.data?.message || '加载账单详情失败')
  } finally {
    detailDrawerState.loading = false
  }
}

async function openDetailDrawer(row) {
  if (!row?.id) return

  detailDrawerState.currentId = Number(row.id)
  detailDrawerState.detail = normalizeDetailPayload({
    invoice: {
      id: row.id,
      invoice_no: row.invoice_no,
      type: row.type,
      type_label: row.type_label,
      amount: row.amount,
      paid_amount: row.paid_amount,
      payable_amount: row.payable_amount,
      status: row.status,
      status_label: row.status_label,
      raw_status: row.raw_status,
      created_at: row.created_at,
      due_date: row.due_date,
      paid_at: row.paid_at,
      payment_summary: row?.payment_summary || {},
      order_id: row?.order?.id || row?.order_id || 0,
      order: row?.order || null,
      product: row?.product || row?.order?.product || null,
      summary: {
        headline: row.type_label || '',
        subheadline: '',
        badge: row.type_label || '',
        highlight: row?.order?.order_no || row?.invoice_no || '',
        remark: '',
      },
      scene: row?.scene || {},
    },
    payments: [],
    items: [],
    logs: [],
  }, row)
  detailDrawerState.visible = true
  await loadInvoiceDetail(row.id)
}

function closeDetailDrawer() {
  detailDrawerState.visible = false
  detailDrawerState.currentId = 0
  detailDrawerState.cancelLoading = false
  detailDrawerState.detail = { invoice: {}, payments: [], items: [], logs: [] }
}

async function reloadDetailDrawer() {
  if (!detailDrawerState.currentId) return
  await loadInvoiceDetail(detailDrawerState.currentId)
}

async function handleDrawerCancel() {
  const invoice = detailDrawerState.detail?.invoice || {}
  const invoiceId = Number(invoice.id || 0)
  if (!invoiceId) return

  detailDrawerState.cancelLoading = true
  try {
    await adminApi.invoices.cancel(invoiceId)
    ElMessage.success('账单已取消')
    emit('reload')
    emit('detail-refresh')
    await reloadDetailDrawer()
  } catch (error) {
    ElMessage.error(error?.response?.data?.message || '取消账单失败')
  } finally {
    detailDrawerState.cancelLoading = false
  }
}

async function handleCancel(row) {
  const invoiceId = Number(row?.id || 0)
  if (!invoiceId) return

  loadingMap[row.id] = true
  try {
    await adminApi.invoices.cancel(invoiceId)
    ElMessage.success('账单已取消')
    emit('reload')
    emit('detail-refresh')
  } catch (error) {
    ElMessage.error(error?.response?.data?.message || '取消账单失败')
  } finally {
    loadingMap[row.id] = false
  }
}
</script>

<style lang="scss" scoped>
.toolbar {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 12px;
  flex-wrap: wrap;
}

.toolbar.compact :deep(.el-input),
.toolbar.compact :deep(.el-select) {
  width: 150px;
}

.pager {
  display: flex;
  justify-content: flex-end;
  margin-top: 14px;
}

.row-actions {
  display: inline-flex;
  align-items: center;
  gap: 12px;
}

.action-link {
  cursor: pointer;
  color: $text-color-secondary;
  font-size: 13px;
  white-space: nowrap;
  transition: color $duration-fast $ease-standard;

  &:hover { color: $color-primary; }
  &--primary { color: $color-primary; }
  &--danger  { color: $text-color-secondary; &:hover { color: $color-danger; } }
  &.is-disabled { color: $text-color-placeholder; cursor: not-allowed; &:hover { color: $text-color-placeholder; } }
}
</style>
