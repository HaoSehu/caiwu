<template>
  <el-drawer
    :model-value="state.visible"
    size="720px"
    destroy-on-close
    class="invoice-detail-drawer"
    @update:model-value="handleVisibleUpdate"
    @closed="emit('close')"
  >
    <div class="invoice-detail-shell" v-loading="state.loading">
      <header class="invoice-detail-header">
        <div class="invoice-detail-title">
          <p class="invoice-detail-kicker">账单详情</p>
          <h3>{{ invoice.summary?.headline || invoice.type_label || invoice.invoice_no || '账单详情' }}</h3>
          <p class="invoice-detail-subtitle">{{ invoice.summary?.subheadline || invoice.scene?.subheadline || '查看账单摘要、金额拆分、支付与退款信息。' }}</p>
        </div>

        <div class="invoice-detail-actions">
          <el-button :loading="state.loading" @click="emit('reload')">刷新</el-button>
          <el-button v-if="canCancel" :loading="state.cancelLoading" type="danger" plain @click="emit('cancel')">
            取消账单
          </el-button>
          <el-tag effect="plain" :type="statusTagType(invoice.status)">{{ invoice.status_label || '--' }}</el-tag>
          <el-tag effect="plain" type="info">{{ invoice.summary?.badge || invoice.type_label || '--' }}</el-tag>
          <el-button circle :icon="Close" @click="emit('close')" />
        </div>
      </header>

      <section class="invoice-summary-panel">
        <div class="summary-main">
          <div class="summary-number">{{ invoice.invoice_no || '--' }}</div>
          <div class="summary-meta">
            <span>用户：{{ invoice.user?.nickname || invoice.user?.email || invoice.user_email || '--' }}</span>
            <span>创建时间：{{ invoice.created_at || '--' }}</span>
            <span v-if="invoice.paid_at">支付时间：{{ invoice.paid_at }}</span>
          </div>
        </div>
        <div class="summary-highlight">
          <strong>{{ invoice.summary?.highlight || invoice.scene?.highlight || invoice.payment_summary?.gateway_label || '--' }}</strong>
          <p>{{ invoice.summary?.remark || invoice.scene?.remark || invoice.payment_summary?.refund_reason || '账单已进入当前流程状态。' }}</p>
        </div>
      </section>

      <section class="invoice-grid">
        <article class="invoice-card">
          <div class="invoice-card__head">
            <strong>账单概况</strong>
          </div>
          <div class="kv-grid">
            <div class="kv-item">
              <span>账单类型</span>
              <strong>{{ invoice.type_label || invoice.invoice_type_label || '--' }}</strong>
            </div>
            <div class="kv-item">
              <span>场景</span>
              <strong>{{ invoice.scene?.headline || '--' }}</strong>
            </div>
            <div class="kv-item">
              <span>账单金额</span>
              <strong>{{ formatMoney(invoice.amount) }}</strong>
            </div>
            <div class="kv-item">
              <span>折扣金额</span>
              <strong>{{ formatMoney(invoice.discount) }}</strong>
            </div>
            <div class="kv-item">
              <span>已付金额</span>
              <strong>{{ formatMoney(invoice.paid_amount) }}</strong>
            </div>
            <div class="kv-item">
              <span>应付金额</span>
              <strong>{{ formatMoney(invoice.payable_amount ?? invoice.amount) }}</strong>
            </div>
            <div class="kv-item">
              <span>计费周期</span>
              <strong>{{ invoice.billing_cycle || '--' }}</strong>
            </div>
            <div class="kv-item">
              <span>数量</span>
              <strong>{{ invoice.quantity || 1 }}</strong>
            </div>
          </div>
        </article>

        <article class="invoice-card">
          <div class="invoice-card__head">
            <strong>关联信息</strong>
          </div>
          <div class="kv-grid">
            <div class="kv-item">
              <span>用户</span>
              <strong>{{ invoice.user?.nickname || invoice.user?.email || '--' }}</strong>
            </div>
            <div class="kv-item">
              <span>配置名称</span>
              <strong>{{ invoice.product_spec_display || invoice.product_display_name || invoice.product?.display_name || '--' }}</strong>
            </div>
            <div class="kv-item">
              <span>订单号</span>
              <strong class="mono">{{ invoice.order?.order_no || invoice.order_no || '--' }}</strong>
            </div>
            <div class="kv-item">
              <span>服务</span>
              <strong>{{ invoice.service?.name || '--' }}</strong>
            </div>
            <div class="kv-item">
              <span>到期日</span>
              <strong>{{ invoice.due_date || '--' }}</strong>
            </div>
            <div class="kv-item">
              <span>更新时间</span>
              <strong>{{ invoice.updated_at || '--' }}</strong>
            </div>
          </div>
        </article>
      </section>

      <section v-if="sceneFields.length" class="invoice-card invoice-list-card">
        <div class="invoice-card__head">
          <strong>场景字段</strong>
          <span>{{ invoice.scene?.badge || invoice.type_label || '--' }}</span>
        </div>
        <div class="kv-grid">
          <div v-for="item in sceneFields" :key="`${item.label}-${item.value}`" class="kv-item">
            <span>{{ item.label || '--' }}</span>
            <strong>{{ item.value || '--' }}</strong>
          </div>
        </div>
      </section>

      <section v-if="sceneItems.length" class="invoice-card invoice-list-card">
        <div class="invoice-card__head">
          <strong>场景明细</strong>
          <span>{{ invoice.scene?.kind || invoice.type || '--' }}</span>
        </div>
        <div class="line-list">
          <div v-for="item in sceneItems" :key="item.id" class="line-item">
            <div>
              <strong>{{ item.description || '--' }}</strong>
              <p v-if="isRefundScene && Number(item.amount) < 0">退款明细</p>
            </div>
            <strong :class="Number(item.amount) < 0 ? 'amount-negative' : ''">{{ formatMoney(item.amount) }}</strong>
          </div>
        </div>
      </section>

      <section v-if="paymentList.length" class="invoice-card invoice-list-card">
        <div class="invoice-card__head">
          <strong>支付 / 退款记录</strong>
          <span>{{ paymentList.length }} 条</span>
        </div>
        <div class="line-list">
          <div v-for="payment in paymentList" :key="payment.id" class="line-item line-item--stacked">
            <div class="line-item__main">
              <strong>{{ payment.payment_no || '--' }}</strong>
              <p>
                {{ payment.gateway_label || payment.gateway || '--' }}
                <template v-if="payment.trade_no">· {{ payment.trade_no }}</template>
              </p>
            </div>
            <div class="line-item__meta">
              <el-tag effect="plain" size="small" :type="payment.status === 3 ? 'danger' : payment.status === 1 ? 'success' : 'info'">
                {{ payment.status_label || '--' }}
              </el-tag>
              <strong>{{ formatMoney(payment.amount) }}</strong>
              <span>{{ payment.paid_at || payment.created_at || '--' }}</span>
            </div>
            <div v-if="payment.refund_reason || payment.refund_method_label || payment.refunded_at" class="refund-meta">
              <span v-if="payment.refund_method_label">方式：{{ payment.refund_method_label }}</span>
              <span v-if="payment.refund_reason">原因：{{ payment.refund_reason }}</span>
              <span v-if="payment.refunded_at">退款时间：{{ payment.refunded_at }}</span>
            </div>
          </div>
        </div>
      </section>

      <section v-if="logList.length" class="invoice-card invoice-list-card">
        <div class="invoice-card__head">
          <strong>操作日志</strong>
          <span>{{ logList.length }} 条</span>
        </div>
        <div class="log-list">
          <div v-for="log in logList" :key="log.id" class="log-item">
            <el-tag effect="plain" size="small" :type="logTagType(log.tone)">{{ log.action || 'log' }}</el-tag>
            <div class="log-item__body">
              <strong>{{ log.summary || '--' }}</strong>
              <p>{{ log.created_at || '--' }}</p>
            </div>
          </div>
        </div>
      </section>

      <section v-if="!paymentList.length && !logList.length" class="invoice-empty-hint">
        <el-empty :image-size="80" description="当前账单暂无支付记录或日志" />
      </section>
    </div>
  </el-drawer>
</template>

<script setup>
import { computed } from 'vue'
import { Close } from '@element-plus/icons-vue'
import { INVOICE_STATUS_MAP, resolveElTagType } from '@shared/statusConfig'

const props = defineProps({
  state: {
    type: Object,
    required: true,
  },
  formatMoney: {
    type: Function,
    required: true,
  },
})

const emit = defineEmits(['close', 'reload', 'cancel'])

const rawDetail = computed(() => props.state?.detail || {})
const invoice = computed(() => {
  const detail = rawDetail.value || {}
  return detail.invoice && typeof detail.invoice === 'object'
    ? detail.invoice
    : detail
})
const paymentList = computed(() => Array.isArray(rawDetail.value?.payments) ? rawDetail.value.payments : [])
const sceneFields = computed(() => {
  const fields = invoice.value?.scene?.fields
  return Array.isArray(fields) ? fields : []
})
const sceneItems = computed(() => {
  const sceneItemsValue = invoice.value?.scene?.items
  if (Array.isArray(sceneItemsValue) && sceneItemsValue.length) {
    return sceneItemsValue
  }

  return Array.isArray(rawDetail.value?.items) ? rawDetail.value.items : []
})
const logList = computed(() => Array.isArray(rawDetail.value?.logs) ? rawDetail.value.logs : [])
const isRefundScene = computed(() => String(invoice.value?.scene?.kind || '').trim() === 'refund' || Number(invoice.value?.status) === 5)
const canCancel = computed(() => {
  if (typeof invoice.value?.can_cancel === 'boolean') {
    return invoice.value.can_cancel
  }

  const rawStatus = Number(invoice.value?.raw_status ?? invoice.value?.status ?? -1)
  const orderStatus = Number(invoice.value?.order?.status ?? 0)
  return orderStatus === 0 && [0, 3].includes(rawStatus)
})

function handleVisibleUpdate(visible) {
  if (!visible) {
    emit('close')
  }
}

function statusTagType(status) {
  return resolveElTagType(INVOICE_STATUS_MAP[Number(status)]?.tagType || 'info')
}

function logTagType(tone) {
  const map = {
    danger: 'danger',
    warning: 'warning',
    success: 'success',
    info: 'info',
  }

  return map[tone] || 'info'
}
</script>

<style scoped lang="scss">
.invoice-detail-drawer :deep(.el-drawer__body) {
  padding: 0;
}

.invoice-detail-shell {
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding: 20px;
  background: linear-gradient(180deg, #f7faff 0%, #ffffff 100%);
}

.invoice-detail-header {
  display: flex;
  justify-content: space-between;
  gap: 16px;
  align-items: flex-start;
}

.invoice-detail-title h3 {
  margin: 2px 0 4px;
  font-size: 20px;
  color: $text-color-primary;
}

.invoice-detail-kicker {
  margin: 0;
  font-size: 12px;
  color: $color-primary;
  font-weight: 600;
}

.invoice-detail-subtitle {
  margin: 0;
  color: $text-color-secondary;
  line-height: 1.6;
}

.invoice-detail-actions {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}

.invoice-summary-panel,
.invoice-card {
  border: 1px solid $border-color;
  border-radius: 16px;
  background: #fff;
  box-shadow: 0 10px 28px rgba(15, 23, 42, 0.05);
}

.invoice-summary-panel {
  display: grid;
  grid-template-columns: 1.2fr 0.8fr;
  gap: 16px;
  padding: 18px;
}

.summary-number {
  font-size: 22px;
  font-weight: 700;
  color: $text-color-primary;
  margin-bottom: 10px;
}

.summary-meta {
  display: flex;
  flex-wrap: wrap;
  gap: 10px 16px;
  color: $text-color-secondary;
  font-size: 13px;
  line-height: 1.6;
}

.summary-highlight {
  padding: 16px;
  border-radius: 14px;
  background: linear-gradient(180deg, rgba($color-primary, 0.08), rgba($color-primary, 0.03));
  border: 1px solid rgba($color-primary, 0.16);
}

.summary-highlight strong {
  display: block;
  color: $text-color-primary;
  font-size: 16px;
  margin-bottom: 8px;
}

.summary-highlight p {
  margin: 0;
  color: $text-color-secondary;
  line-height: 1.7;
}

.invoice-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 16px;
}

.invoice-card {
  padding: 16px;
}

.invoice-card__head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 14px;
  color: $text-color-primary;
  font-weight: 600;
}

.kv-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
}

.kv-item {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.kv-item span,
.line-item p,
.log-item__body p,
.refund-meta {
  color: $text-color-secondary;
  font-size: 12px;
}

.kv-item strong,
.line-item strong,
.log-item__body strong {
  color: $text-color-primary;
  font-size: 14px;
  line-height: 1.6;
  word-break: break-word;
}

.invoice-list-card .line-list,
.invoice-list-card .log-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.line-item,
.log-item {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  padding: 12px 14px;
  border-radius: 12px;
  background: $bg-color-soft;
}

.line-item--stacked {
  flex-direction: column;
}

.line-item__main {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.line-item__meta {
  display: flex;
  align-items: center;
  gap: 10px 12px;
  flex-wrap: wrap;
  color: $text-color-secondary;
  font-size: 12px;
}

.refund-meta {
  display: flex;
  flex-wrap: wrap;
  gap: 8px 12px;
}

.amount-negative {
  color: $color-danger;
}

.log-item {
  align-items: center;
}

.log-item__body {
  flex: 1;
}

.invoice-empty-hint {
  border: 1px dashed $border-color;
  border-radius: 16px;
  background: rgba($bg-color-soft, 0.7);
}

@media (max-width: 960px) {
  .invoice-summary-panel,
  .invoice-grid,
  .kv-grid {
    grid-template-columns: 1fr;
  }

  .invoice-detail-header {
    flex-direction: column;
  }
}
</style>
