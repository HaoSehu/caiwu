<template>
  <el-drawer
    :model-value="visible"
    size="960px"
    destroy-on-close
    class="ledger-detail-drawer"
    @update:model-value="handleVisibleUpdate"
    @closed="emit('close')"
  >
    <div class="ledger-drawer-shell" v-loading="loading">
      <header class="ledger-drawer-header">
        <div class="ledger-drawer-title">
          <p class="ledger-drawer-kicker">审计链路</p>
          <h3>{{ detail.display?.title || detail.event_type_label || '资金详情' }}</h3>
          <p class="ledger-drawer-subtitle">
            {{ detail.display?.subtitle || detail.remark || '查看资金事件的来源对象、状态流转、支付回调与关联日志。' }}
          </p>
        </div>

        <div class="ledger-drawer-actions">
          <el-tag effect="plain" :type="resolveEventTagType(detail)">
            {{ detail.event_type_label || '--' }}
          </el-tag>
          <el-tag effect="plain" :type="resolveStatusTagType(detail)">
            {{ detail.display?.status_label || eventStatusFallback }}
          </el-tag>
          <el-button circle :icon="Close" @click="emit('close')" />
        </div>
      </header>

      <section class="ledger-summary-panel">
        <div class="summary-main">
          <div class="summary-number">
            {{ signedMoney(detail.change_amount) }}
          </div>
          <div class="summary-meta">
            <span>资金流水：#{{ detail.ledger_id || detail.id || '--' }}</span>
            <span>账户类型：{{ detail.account_type || 'cash' }}</span>
            <span>发生时间：{{ detail.occurred_at || detail.created_at || '--' }}</span>
          </div>
        </div>
        <div class="summary-highlight">
          <strong>{{ formatMoney(detail.balance_after) }}</strong>
          <p>变动后余额</p>
        </div>
      </section>

      <section class="ledger-card">
        <div class="ledger-card__head">
          <strong>基础摘要</strong>
        </div>
        <el-table :data="summaryRows" size="small" :show-header="false" stripe>
          <el-table-column prop="label" width="100" />
          <el-table-column prop="value">
            <template #default="{ row }">
              <span :class="row.class || ''">{{ row.value }}</span>
            </template>
          </el-table-column>
        </el-table>
      </section>

      <section class="ledger-card">
        <div class="ledger-card__head">
          <strong>关联对象</strong>
        </div>
        <el-table :data="referenceRows" size="small" :show-header="false" stripe>
          <el-table-column prop="label" width="100" />
          <el-table-column prop="value">
            <template #default="{ row }">
              <span :class="row.mono ? 'mono' : ''">{{ row.value }}</span>
            </template>
          </el-table-column>
        </el-table>
      </section>

      <section class="ledger-card">
        <div class="ledger-card__head">
          <strong>来源链路</strong>
          <span>{{ sourceChain.length }} 个节点</span>
        </div>
        <div v-if="sourceChain.length" class="source-chain">
          <div v-for="item in sourceChain" :key="`${item.key}-${item.id}-${item.no}`" class="source-node">
            <div class="source-node__icon">{{ sourceIcon(item.key) }}</div>
            <div class="source-node__body">
              <div class="source-node__title">
                <strong>{{ item.label || '--' }}</strong>
                <el-tag v-if="item.status_label" effect="plain" size="small" type="info">{{ item.status_label }}</el-tag>
              </div>
              <p class="source-node__meta">
                <span class="mono">{{ item.no || '--' }}</span>
                <span>{{ item.occurred_at || '--' }}</span>
              </p>
              <p class="source-node__desc">{{ item.description || '--' }}</p>
            </div>
          </div>
        </div>
        <el-empty v-else :image-size="72" description="暂无来源链路" />
      </section>

      <section class="ledger-card">
        <div class="ledger-card__head">
          <strong>状态流转</strong>
          <span>{{ statusTimeline.length }} 个状态点</span>
        </div>
        <el-timeline v-if="statusTimeline.length" class="status-timeline">
          <el-timeline-item
            v-for="item in statusTimeline"
            :key="`${item.key}-${item.occurred_at}`"
            :timestamp="item.occurred_at || '--'"
            :type="resolveTimelineType(item)"
          >
            <div class="timeline-card">
              <div class="timeline-card__head">
                <strong>{{ item.label || '--' }}</strong>
                <el-tag effect="plain" size="small" :type="resolveTimelineType(item)">
                  {{ item.status_label || '--' }}
                </el-tag>
              </div>
              <p class="timeline-card__meta">
                <span class="mono">{{ item.source || '--' }}</span>
              </p>
              <p v-if="item.description" class="timeline-card__desc">{{ item.description }}</p>
            </div>
          </el-timeline-item>
        </el-timeline>
        <el-empty v-else :image-size="72" description="暂无状态流转" />
      </section>

      <section class="ledger-grid ledger-grid--bottom">
        <article class="ledger-card">
          <div class="ledger-card__head">
            <strong>支付回调</strong>
            <span>{{ paymentCallbacks.length }} 条</span>
          </div>
          <div v-if="paymentCallbacks.length" class="line-list">
            <div v-for="callback in paymentCallbacks" :key="callback.id" class="line-item line-item--stacked">
              <div class="line-item__main">
                <strong>{{ callback.callback_type === 'refund' ? '退款回调' : '支付回调' }}</strong>
                <p class="mono">{{ callback.gateway_trade_no || detail.payment?.payment_no || '--' }}</p>
              </div>
              <div class="line-item__meta">
                <el-tag effect="plain" size="small" :type="Number(callback.is_verified) === 1 ? 'success' : 'warning'">
                  {{ Number(callback.is_verified) === 1 ? '已核验' : '待核验' }}
                </el-tag>
                <span>{{ callback.received_at || '--' }}</span>
              </div>
              <p class="line-item__desc">{{ callback.summary || '--' }}</p>
            </div>
          </div>
          <el-empty v-else :image-size="72" description="暂无支付回调" />
        </article>

        <article class="ledger-card">
          <div class="ledger-card__head">
            <strong>Trace 链接</strong>
            <span>{{ traceLinks.length }} 项</span>
          </div>
          <div v-if="traceLinks.length" class="trace-list">
            <div v-for="item in traceLinks" :key="`${item.kind}-${item.value}`" class="trace-item">
              <span>{{ item.label || '--' }}</span>
              <strong class="mono">{{ item.value || '--' }}</strong>
            </div>
          </div>
          <el-empty v-else :image-size="72" description="暂无 Trace 关联" />
        </article>
      </section>

      <section class="ledger-card">
        <div class="ledger-card__head">
          <strong>审计日志</strong>
          <span>{{ auditLogs.length }} 条</span>
        </div>
        <div v-if="auditLogs.length" class="log-list">
          <div v-for="log in auditLogs" :key="log.id" class="log-item">
            <div class="log-item__head">
              <el-tag effect="plain" size="small" :type="logTagType(log.tone)">
                {{ log.module || 'log' }}
              </el-tag>
              <strong>{{ log.action || '--' }}</strong>
              <span>{{ log.created_at || '--' }}</span>
            </div>
            <p class="log-item__summary">{{ log.summary || '--' }}</p>
            <div class="log-item__meta">
              <span v-if="log.operator_name">操作人：{{ log.operator_name }}</span>
              <span v-if="log.user_type">类型：{{ log.user_type }}</span>
              <span v-if="log.ip_address">IP：{{ log.ip_address }}</span>
              <span v-if="log.trace_id" class="mono">Trace：{{ log.trace_id }}</span>
            </div>
          </div>
        </div>
        <el-empty v-else :image-size="72" description="暂无关联日志" />
      </section>
    </div>
  </el-drawer>
</template>

<script setup>
import { computed } from 'vue'
import { Close } from '@element-plus/icons-vue'
import { FINANCE_LEDGER_EVENT_MAP } from '@shared/statusConfig'

const props = defineProps({
  visible: {
    type: Boolean,
    default: false,
  },
  loading: {
    type: Boolean,
    default: false,
  },
  detail: {
    type: Object,
    default: () => ({}),
  },
})

const emit = defineEmits(['close'])

const sourceChain = computed(() => Array.isArray(props.detail?.audit?.source_chain) ? props.detail.audit.source_chain : [])
const statusTimeline = computed(() => Array.isArray(props.detail?.audit?.status_timeline) ? props.detail.audit.status_timeline : [])
const paymentCallbacks = computed(() => Array.isArray(props.detail?.audit?.payment_callbacks) ? props.detail.audit.payment_callbacks : [])
const auditLogs = computed(() => Array.isArray(props.detail?.audit?.audit_logs) ? props.detail.audit.audit_logs : [])
const traceLinks = computed(() => Array.isArray(props.detail?.audit?.trace_links) ? props.detail.audit.trace_links : [])
const eventStatusFallback = computed(() => props.detail?.direction === 'out' ? '已支出' : '已入账')

const summaryRows = computed(() => {
  const d = props.detail || {}
  const amountClass = Number(d.change_amount || 0) >= 0 ? 'amount-in' : 'amount-out'
  return [
    { label: '收支方向', value: d.direction === 'out' ? '支出' : '收入' },
    { label: '变动金额', value: signedMoney(d.change_amount), class: amountClass },
    { label: '变动后余额', value: formatMoney(d.balance_after) },
    { label: '操作人', value: d.operator || '--' },
    { label: '来源对象', value: resolveSourceMeta(d) },
    { label: 'Trace ID', value: d.trace_id || '--', mono: true },
  ]
})

const referenceRows = computed(() => {
  const d = props.detail || {}
  return [
    { label: '账单号', value: d.invoice?.invoice_no || '--', mono: true },
    { label: '账单状态', value: d.invoice?.status_label || '--' },
    { label: '支付号', value: d.payment?.payment_no || '--', mono: true },
    { label: '支付渠道', value: d.payment?.gateway_label || '--' },
    { label: '支付状态', value: d.payment?.status_label || '--' },
    { label: '渠道交易号', value: d.payment?.trade_no || '--', mono: true },
  ]
})

function handleVisibleUpdate(value) {
  if (!value) {
    emit('close')
  }
}

function formatMoney(value) {
  return `¥${Number(value || 0).toFixed(2)}`
}

function signedMoney(value) {
  const amount = Number(value || 0)
  return `${amount >= 0 ? '+' : ''}${formatMoney(amount)}`
}

function resolveEventTagType(row) {
  return row?.display?.badge_type || FINANCE_LEDGER_EVENT_MAP[row?.event_type]?.tagType || 'info'
}

function resolveStatusTagType(row) {
  const status = Number(row?.display?.status)
  if (status === 1) return 'success'
  if (status === 0) return 'warning'
  if (status === 3 || status === 5) return 'danger'
  if (status === 2) return 'info'
  return row?.direction === 'out' ? 'danger' : 'success'
}

function resolveTimelineType(item) {
  const key = String(item?.key || '')
  const status = Number(item?.status)

  if (key.startsWith('payment_callback_')) {
    return status === 1 ? 'success' : 'warning'
  }

  if ([2, 3, 5].includes(status)) return 'danger'
  if (status === 0) return 'warning'
  if (status === 1 || status === 4) return 'success'
  return 'primary'
}

function resolveSourceMeta(row) {
  const sourceType = row?.source_type || row?.origin_type || '--'
  const sourceId = row?.source_id || row?.origin_id || '--'
  return `${sourceType} / ${sourceId}`
}

function sourceIcon(key) {
  const map = {
    source: '源',
    order: '订',
    invoice: '单',
    payment: '付',
    ledger: '账',
  }

  return map[key] || '链'
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
.ledger-detail-drawer :deep(.el-drawer__body) {
  padding: 0;
}

.ledger-drawer-shell {
  display: flex;
  flex-direction: column;
  gap: 16px;
  min-height: 100%;
  padding: 20px;
  background: linear-gradient(180deg, #f7faff 0%, #ffffff 100%);
}

.ledger-drawer-header {
  display: flex;
  justify-content: space-between;
  gap: 16px;
  align-items: flex-start;
}

.ledger-drawer-title h3 {
  margin: 2px 0 4px;
  font-size: 22px;
  color: $text-color-primary;
}

.ledger-drawer-kicker {
  margin: 0;
  font-size: 12px;
  color: $color-primary;
  font-weight: 600;
}

.ledger-drawer-subtitle {
  margin: 0;
  color: $text-color-secondary;
  line-height: 1.6;
}

.ledger-drawer-actions {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}

.ledger-summary-panel,
.ledger-card {
  border: 1px solid $border-color;
  border-radius: 20px;
  background: rgba(255, 255, 255, 0.96);
  box-shadow: 0 18px 40px rgba(15, 23, 42, 0.06);
}

.ledger-summary-panel {
  display: flex;
  justify-content: space-between;
  gap: 16px;
  padding: 20px 22px;
}

.summary-main {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.summary-number {
  font-size: 30px;
  line-height: 1.1;
  font-weight: 700;
  color: $text-color-primary;
}

.summary-meta {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  font-size: 13px;
  color: $text-color-secondary;
}

.summary-highlight {
  min-width: 160px;
  padding: 16px 18px;
  border-radius: 16px;
  background: linear-gradient(135deg, rgba(59, 130, 246, 0.1), rgba(96, 165, 250, 0.03));
  border: 1px solid rgba(59, 130, 246, 0.12);
}

.summary-highlight strong {
  display: block;
  font-size: 22px;
  color: $text-color-primary;
}

.summary-highlight p {
  margin: 8px 0 0;
  color: $text-color-secondary;
  font-size: 13px;
}

.ledger-grid--bottom {
  align-items: stretch;
}

.ledger-card {
  padding: 18px;
}

.ledger-card__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 14px;
}

.ledger-card__head strong {
  color: $text-color-primary;
  font-size: 16px;
}

.ledger-card__head span {
  font-size: 12px;
  color: $text-color-placeholder;
}

.ledger-card :deep(.el-table) {
  font-size: 13px;
}

.ledger-card :deep(.el-table__cell) {
  padding: 8px 0;
}

.ledger-card :deep(.el-table td:first-child .cell) {
  color: $text-color-secondary;
  font-size: 12px;
}

.source-chain {
  display: grid;
  gap: 12px;
}

.source-node {
  display: grid;
  grid-template-columns: 44px minmax(0, 1fr);
  gap: 14px;
  align-items: flex-start;
  padding: 14px 16px;
  border-radius: 16px;
  background: #f8fafc;
  border: 1px solid rgba(15, 23, 42, 0.06);
}

.source-node__icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 44px;
  height: 44px;
  border-radius: 14px;
  background: rgba(59, 130, 246, 0.12);
  color: $color-primary;
  font-weight: 700;
}

.source-node__title {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.source-node__title strong {
  color: $text-color-primary;
}

.source-node__meta,
.source-node__desc,
.timeline-card__meta,
.timeline-card__desc,
.line-item__desc,
.log-item__summary,
.log-item__meta {
  margin: 6px 0 0;
  color: $text-color-secondary;
  font-size: 13px;
  line-height: 1.6;
}

.source-node__meta,
.timeline-card__meta,
.log-item__meta {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
}

.status-timeline {
  padding-left: 6px;
}

.timeline-card {
  padding: 10px 14px 12px;
  border-radius: 14px;
  background: #f8fafc;
  border: 1px solid rgba(15, 23, 42, 0.06);
}

.timeline-card__head {
  display: flex;
  align-items: center;
  gap: 10px;
  justify-content: space-between;
}

.timeline-card__head strong {
  color: $text-color-primary;
}

.line-list,
.log-list,
.trace-list {
  display: grid;
  gap: 12px;
}

.line-item,
.trace-item,
.log-item {
  padding: 14px 16px;
  border-radius: 16px;
  background: #f8fafc;
  border: 1px solid rgba(15, 23, 42, 0.06);
}

.line-item--stacked {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.line-item__main,
.line-item__meta,
.log-item__head,
.trace-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  flex-wrap: wrap;
}

.line-item__main strong,
.log-item__head strong,
.trace-item strong {
  color: $text-color-primary;
}

.line-item__main p {
  margin: 4px 0 0;
  color: $text-color-secondary;
  font-size: 13px;
}

.trace-item span {
  color: $text-color-secondary;
  font-size: 13px;
}

.mono {
  font-family: 'SFMono-Regular', 'Consolas', 'Liberation Mono', monospace;
}

.amount-in {
  color: $color-success;
}

.amount-out {
  color: $color-danger;
}

@media (max-width: 1200px) {
  .ledger-grid--bottom {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 768px) {
  .ledger-detail-drawer :deep(.el-drawer) {
    width: 100% !important;
  }

  .ledger-drawer-shell {
    padding: 14px;
    gap: 12px;
  }

  .ledger-drawer-header {
    flex-direction: column;
    gap: 10px;
  }

  .ledger-drawer-title h3 {
    font-size: 17px;
  }

  .ledger-drawer-subtitle {
    font-size: 13px;
  }

  .ledger-drawer-actions {
    gap: 6px;
  }

  .ledger-summary-panel {
    flex-direction: column;
    padding: 14px;
    border-radius: 14px;
  }

  .summary-number {
    font-size: 24px;
  }

  .summary-meta {
    gap: 8px;
    font-size: 12px;
  }

  .summary-highlight {
    min-width: 0;
    width: 100%;
    padding: 12px 14px;
    border-radius: 12px;
  }

  .summary-highlight strong {
    font-size: 18px;
  }

  .ledger-grid--bottom {
    grid-template-columns: 1fr;
  }

  .ledger-summary-panel,
  .ledger-card {
    border-radius: 14px;
  }

  .ledger-card {
    padding: 14px;
  }

  .ledger-card__head {
    margin-bottom: 10px;
  }

  .ledger-card__head strong {
    font-size: 14px;
  }

  .ledger-card :deep(.el-table) {
    font-size: 12px;
  }

  .ledger-card :deep(.el-table__cell) {
    padding: 6px 0;
  }

  .source-node {
    grid-template-columns: 1fr;
    padding: 12px;
    border-radius: 12px;
    gap: 8px;
  }

  .source-node__icon {
    width: 32px;
    height: 32px;
    border-radius: 10px;
    font-size: 12px;
  }

  .source-node__title strong {
    font-size: 13px;
  }

  .timeline-card {
    padding: 10px 12px;
    border-radius: 10px;
  }

  .timeline-card__head strong {
    font-size: 13px;
  }

  .line-item,
  .trace-item,
  .log-item {
    padding: 12px;
    border-radius: 12px;
  }
}
</style>
