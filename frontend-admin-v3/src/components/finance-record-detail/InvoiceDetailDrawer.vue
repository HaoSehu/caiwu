<template>
  <!-- embedded 模式：渲染为独立详情页内容（财务账单详情页复用），不套抽屉外壳 -->
  <component :is="shellComponent" v-bind="shellProps">
    <record-detail-page
      :loading="loading"
      :ready="Boolean(invoice.id || invoice.invoice_no)"
      :show-back="embedded"
      eyebrow="账单详情"
      :title="fieldValue(invoice.invoice_no || invoice.id)"
      :description="invoiceTitle(invoice)"
      :status-label="fieldValue(statusLabel)"
      :status-theme="statusTheme"
      :metrics="summaryMetrics"
      :tabs="tabs"
      :active-tab="activeTab"
      empty-text="账单不存在"
      @refresh="emit('refresh')"
      @back="handleBack"
      @update:active-tab="(value) => (activeTab = value)"
    >
      <template #toolbar-actions>
        <t-button v-if="canRefund" theme="warning" variant="outline" @click="emit('mark-refund')"> 退款 </t-button>
        <t-button v-if="cancelable" theme="danger" variant="outline" :loading="cancelLoading" @click="emit('cancel')">
          取消账单
        </t-button>
      </template>

      <template #relations>
        <t-button
          v-if="invoice.order?.id || invoice.order_id"
          variant="outline"
          size="small"
          @click="emit('view-order', invoice.order?.id || invoice.order_id)"
        >
          查看订单
        </t-button>
        <t-button
          v-if="invoice.user?.id || invoice.user_id"
          variant="outline"
          size="small"
          @click="emit('view-user', invoice.user?.id || invoice.user_id)"
        >
          查看用户
        </t-button>
      </template>

      <template #tab-basic>
        <section class="finance-detail-section">
          <h4>基础信息</h4>
          <div class="detail-kv-grid">
            <div class="detail-kv-item">
              <span class="detail-kv-item__label">账单类型</span>
              <div class="detail-kv-item__value">
                <t-tag theme="primary" variant="light">
                  {{ fieldValue(invoice.type_label || invoiceTypeLabel(invoice.type)) }}
                </t-tag>
              </div>
            </div>
            <div class="detail-kv-item">
              <span class="detail-kv-item__label">归属用户</span>
              <div class="detail-kv-item__value">
                <strong>{{ userName(invoice.user) }}</strong>
                <t-button
                  v-if="invoice.user_id"
                  size="small"
                  variant="text"
                  theme="primary"
                  @click="emit('view-user', invoice.user_id)"
                >
                  用户详情 →
                </t-button>
              </div>
            </div>
            <div class="detail-kv-item">
              <span class="detail-kv-item__label">关联订单</span>
              <div class="detail-kv-item__value">
                <t-link v-if="orderId" theme="primary" hover="color" @click="goToOrder">
                  {{ orderNo }}
                </t-link>
                <strong v-else>{{ fieldValue(invoice.order?.order_no || invoice.order_no) }}</strong>
              </div>
            </div>
            <div class="detail-kv-item">
              <span class="detail-kv-item__label">到期截止日</span>
              <strong class="detail-kv-item__value">{{ fieldValue(invoice.due_date) }}</strong>
            </div>
            <div class="detail-kv-item">
              <span class="detail-kv-item__label">创建时间</span>
              <strong class="detail-kv-item__value">{{ formatDateTime(invoice.created_at) }}</strong>
            </div>
            <div class="detail-kv-item">
              <span class="detail-kv-item__label">支付时间</span>
              <strong class="detail-kv-item__value">{{ formatDateTime(invoice.paid_at) }}</strong>
            </div>
            <div v-if="memberLevelName" class="detail-kv-item">
              <span class="detail-kv-item__label">会员等级</span>
              <div class="detail-kv-item__value">
                <t-tag theme="success" variant="light">{{ memberLevelName }}</t-tag>
              </div>
            </div>
            <div v-if="invoice.trace_id" class="detail-kv-item detail-kv-item--span-2">
              <span class="detail-kv-item__label">链路追踪 (Trace ID)</span>
              <div class="detail-kv-item__value detail-copy-wrap">
                <span style="font-family: monospace; font-size: 13px">{{ invoice.trace_id }}</span>
                <t-tooltip content="复制 Trace ID" placement="top">
                  <t-button variant="text" shape="square" size="small" @click="copyText(invoice.trace_id)">
                    <template #icon><file-copy-icon /></template>
                  </t-button>
                </t-tooltip>
              </div>
            </div>
            <div v-if="invoice.refund_trace_id" class="detail-kv-item detail-kv-item--span-2">
              <span class="detail-kv-item__label">退款追踪号</span>
              <div class="detail-kv-item__value detail-copy-wrap">
                <span style="font-family: monospace; font-size: 13px">{{ invoice.refund_trace_id }}</span>
                <t-tooltip content="复制退款追踪" placement="top">
                  <t-button variant="text" shape="square" size="small" @click="copyText(invoice.refund_trace_id)">
                    <template #icon><file-copy-icon /></template>
                  </t-button>
                </t-tooltip>
              </div>
            </div>
          </div>
        </section>

        <section v-if="hasRefundInfo" class="finance-detail-section">
          <h4>退款信息</h4>
          <div class="detail-kv-grid">
            <div class="detail-kv-item">
              <span class="detail-kv-item__label">退款状态</span>
              <div class="detail-kv-item__value">
                <t-tag :theme="refundTagTheme" variant="light">{{ refundStatusLabel }}</t-tag>
              </div>
            </div>
            <div class="detail-kv-item">
              <span class="detail-kv-item__label">累计已退款</span>
              <strong class="detail-kv-item__value">{{ formatMoney(invoice.refund_amount) }}</strong>
            </div>
            <div v-if="canRefund" class="detail-kv-item">
              <span class="detail-kv-item__label">可退余额</span>
              <strong class="detail-kv-item__value">{{ formatMoney(invoice.refundable_amount_for_balance) }}</strong>
            </div>
            <div v-if="invoice.refund_method" class="detail-kv-item">
              <span class="detail-kv-item__label">退款方式</span>
              <strong class="detail-kv-item__value">退回余额</strong>
            </div>
            <div v-if="invoice.refunded_at" class="detail-kv-item">
              <span class="detail-kv-item__label">最近退款时间</span>
              <strong class="detail-kv-item__value">{{ formatDateTime(invoice.refunded_at) }}</strong>
            </div>
          </div>
        </section>

        <section v-if="refundRecords.length" class="finance-detail-section">
          <h4>退款记录</h4>
          <div class="finance-line-list">
            <div v-for="record in refundRecords" :key="String(record.refund_no || record.id)" class="finance-line-item">
              <div class="finance-line-item__main">
                <span>{{ fieldValue(record.refund_no) }}</span>
                <span class="finance-line-item__meta">
                  {{ fieldValue(record.refund_method_label) }} · {{ fieldValue(record.operator_name) || '--' }} ·
                  {{ formatDateTime(record.refunded_at) }}
                </span>
              </div>
              <strong>{{ formatMoney(record.amount) }}</strong>
            </div>
          </div>
          <p v-if="refundReasonOfLatest" class="finance-detail-section__note">最近原因：{{ refundReasonOfLatest }}</p>
        </section>

        <section v-if="items.length" class="finance-detail-section">
          <h4>账单项目</h4>
          <div class="finance-line-list">
            <div
              v-for="item in items"
              :key="String(item.id || item.description || item.name)"
              class="finance-line-item"
            >
              <span>{{ fieldValue(item.description || item.name || item.title) }}</span>
              <strong>{{ formatMoney(item.amount) }}</strong>
            </div>
          </div>
        </section>
      </template>

      <template #tab-payments>
        <section class="finance-detail-section">
          <h4>支付记录</h4>
          <div class="finance-line-list">
            <div
              v-for="payment in payments"
              :key="String(payment.id || payment.payment_no)"
              class="finance-line-item finance-line-item--stacked"
            >
              <div class="finance-line-item__head">
                <strong>{{ fieldValue(payment.payment_no) }}</strong>
                <t-tag :theme="paymentStatusTheme(payment)" variant="light">{{ paymentStatusLabel(payment) }}</t-tag>
              </div>
              <span>第三方单号：{{ fieldValue(payment.trade_no) }}</span>
              <span>链路追踪：{{ fieldValue(payment.trace_id) }}</span>
              <span>{{ fieldValue(payment.gateway) }} / {{ formatMoney(payment.amount) }}</span>
              <span>{{ formatDateTime(payment.paid_at || payment.created_at) }}</span>
            </div>
          </div>
        </section>
      </template>

      <template #tab-logs>
        <section class="finance-detail-section">
          <h4>操作日志</h4>
          <div class="finance-line-list">
            <div
              v-for="log in logs"
              :key="String(log.id || log.created_at)"
              class="finance-line-item finance-line-item--stacked"
            >
              <strong>{{ fieldValue(log.summary || log.action || log.message) }}</strong>
              <span>{{ formatDateTime(log.created_at) }}</span>
            </div>
          </div>
        </section>
      </template>
    </record-detail-page>
  </component>
</template>
<script setup lang="ts">
import {
  getStatusLabel,
  getStatusTagType,
  INVOICE_TYPE_MAP,
  PAYMENT_STATUS_MAP,
  REFUND_STATUS,
} from '@shared/statusConfig';
import { FileCopyIcon } from 'tdesign-icons-vue-next';
import { computed, ref } from 'vue';
import { useRouter } from 'vue-router';

import type { InvoiceRecord } from '@/api/admin';
import type { RecordDetailMetric, RecordDetailTab } from '@/components/record-detail-page/index.vue';
import RecordDetailPage from '@/components/record-detail-page/index.vue';
import { useMediaQuery } from '@/hooks/useMediaQuery';
import { copyToClipboard, formatDateTime, formatMoney } from '@/utils/format';

const props = withDefaults(
  defineProps<{
    visible: boolean;
    loading?: boolean;
    invoice?: InvoiceRecord;
    payments?: Record<string, unknown>[];
    items?: Record<string, unknown>[];
    logs?: Record<string, unknown>[];
    statusLabel?: string;
    statusTheme?: string;
    cancelable?: boolean;
    cancelLoading?: boolean;
    /** 独立详情页模式：渲染为页面内容而非抽屉外壳 */
    embedded?: boolean;
  }>(),
  {
    loading: false,
    invoice: () => ({}),
    payments: () => [],
    items: () => [],
    logs: () => [],
    statusLabel: '',
    statusTheme: 'default',
    cancelable: false,
    cancelLoading: false,
    /** 独立详情页模式：渲染为页面内容而非抽屉外壳 */
    embedded: false,
  },
);

const emit = defineEmits<{
  (event: 'update:visible', value: boolean): void;
  (event: 'close'): void;
  (event: 'back'): void;
  (event: 'refresh'): void;
  (event: 'cancel'): void;
  (event: 'mark-refund'): void;
  (event: 'view-order', id: unknown): void;
  (event: 'view-user', id: unknown): void;
}>();

const isMobile = useMediaQuery('(max-width: 768px)');
const drawerSize = computed(() => (isMobile.value ? '100%' : '780px'));

// 外壳切换：抽屉模式套 t-drawer；embedded 模式渲染为页面内容（复用同一套详情 UI）
const shellComponent = computed(() => (props.embedded ? 'div' : 't-drawer'));
const shellProps = computed(() =>
  props.embedded
    ? { class: 'invoice-detail-embedded' }
    : {
        visible: props.visible,
        size: drawerSize.value,
        header: '账单详情',
        footer: false,
        'onUpdate:visible': (value: boolean) => emit('update:visible', value),
        onClose: () => emit('close'),
      },
);
const activeTab = ref('basic');

/** embedded（独立详情页）模式下返回走路由回退；抽屉模式由父组件处理。 */
function handleBack(): void {
  if (props.embedded) {
    router.back();
    return;
  }
  emit('back');
}
const invoice = computed(() => props.invoice || {});
const payments = computed(() => props.payments || []);
const logs = computed(() => props.logs || []);
const items = computed(() => {
  const sceneItems = invoice.value.scene?.items;
  if (Array.isArray(sceneItems)) return sceneItems as Record<string, unknown>[];
  return props.items || [];
});

// ─── 退款处理（2026-10-11）：标记 / 退到余额两种互斥方式 ───
const canRefund = computed(() => Boolean(invoice.value.can_refund_to_balance));
const refundStatusValue = computed(() => Number(invoice.value.refund_status ?? REFUND_STATUS.NONE));
const refundStatusLabel = computed(() => {
  if (refundStatusValue.value === REFUND_STATUS.FULL) return '已全额退款';
  if (refundStatusValue.value === REFUND_STATUS.PARTIAL) return '部分退款';
  return '未退款';
});
const refundTagTheme = computed(() => (refundStatusValue.value === REFUND_STATUS.NONE ? 'default' : 'danger'));
const refundRecords = computed(() => {
  const records = invoice.value.refunds;
  return Array.isArray(records) ? records : [];
});
const refundReasonOfLatest = computed(() => {
  const first = refundRecords.value[0];
  return first ? String(first.reason || '').trim() : '';
});
const hasRefundInfo = computed(() => refundStatusValue.value !== REFUND_STATUS.NONE || refundRecords.value.length > 0);

// 折扣分列：券减免与会员折扣来源不同，分别展示（formatMoney 自带 ￥，勿再补币符）
const couponDiscountAmount = computed(() => Number(invoice.value.discount || 0));
const memberDiscountAmount = computed(() => Number(invoice.value.member_discount_amount || 0));
const couponText = computed(() => String(invoice.value.coupon_name || invoice.value.coupon_code || '').trim());
const memberLevelName = computed(() => {
  const snap = invoice.value.member_discount_snapshot as Record<string, unknown> | null | undefined;
  if (!snap || typeof snap !== 'object') return '';
  return String(snap.member_level_name || snap.group_name || '').trim();
});

const router = useRouter();

const orderId = computed(() => {
  const o = invoice.value.order;
  if (o && typeof o === 'object') {
    return (o as Record<string, unknown>).id;
  }
  return invoice.value.order_id;
});

const orderNo = computed(() => {
  const o = invoice.value.order;
  if (o && typeof o === 'object') {
    return String((o as Record<string, unknown>).order_no || '');
  }
  return String(invoice.value.order_no || orderId.value || '');
});

function goToOrder() {
  if (orderId.value) {
    emit('update:visible', false);
    router.push(`/admin/finance/orders/${orderId.value}`);
  }
}

function copyText(text: unknown) {
  void copyToClipboard(String(text || ''));
}

const summaryMetrics = computed<RecordDetailMetric[]>(() => [
  { label: '账单金额', value: formatMoney(invoice.value.amount), primary: true },
  {
    label: '优惠券减免',
    value: `-${formatMoney(couponDiscountAmount.value)}`,
    show: couponDiscountAmount.value > 0,
  },
  {
    label: memberLevelName.value ? `会员折扣（${memberLevelName.value}）` : '会员折扣',
    value: `-${formatMoney(memberDiscountAmount.value)}`,
    show: memberDiscountAmount.value > 0,
  },
  { label: '优惠券', value: couponText.value, show: couponText.value !== '' },
  { label: '已付金额', value: formatMoney(invoice.value.paid_amount) },
  { label: '创建时间', value: formatDateTime(invoice.value.created_at) },
]);

const tabs = computed<RecordDetailTab[]>(() => [
  { value: 'basic', label: '基础信息' },
  { value: 'payments', label: '支付记录', show: payments.value.length > 0 },
  { value: 'logs', label: '操作日志', show: logs.value.length > 0 },
]);

function invoiceTitle(row: InvoiceRecord) {
  return fieldValue(
    row.product_full_path ||
      row.combined_display_name ||
      row.product_display_name ||
      row.product_spec_display ||
      row.type_label ||
      invoiceTypeLabel(row.type),
  );
}

function invoiceTypeLabel(type: unknown) {
  return INVOICE_TYPE_MAP[String(type || '')] || fieldValue(type);
}

function userName(user: unknown) {
  const record = toRecord(user);
  return fieldValue(record.nickname || record.display_name || record.email);
}

function paymentStatusLabel(payment: Record<string, unknown>) {
  return getStatusLabel(PAYMENT_STATUS_MAP, Number(payment.status));
}

function paymentStatusTheme(payment: Record<string, unknown>) {
  const value = getStatusTagType(PAYMENT_STATUS_MAP, Number(payment.status));
  return value === 'info' || value === 'purple' ? 'default' : value;
}

function fieldValue(value: unknown) {
  if (value === null || value === undefined || value === '') return '-';
  return String(value);
}

// formatMoney/formatDateTime 统一从 @/utils/format 导入（本文件此前本地重写违规，已修正）。

function toRecord(value: unknown): Record<string, unknown> {
  return value && typeof value === 'object' ? (value as Record<string, unknown>) : {};
}
</script>
<style lang="less" scoped>
// embedded 模式（独立账单详情页）：撑满内容区，不出现抽屉遮罩
.invoice-detail-embedded {
  min-height: 100%;
}
.finance-detail-section {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.finance-detail-section + .finance-detail-section {
  margin-top: 20px;
}

.finance-detail-section h4 {
  position: relative;
  margin: 0;
  padding-left: 10px;
  color: var(--td-text-color-primary);
  font-size: var(--td-font-size-title-medium, 14px);
  font-weight: 700;
  line-height: 22px;

  &::before {
    position: absolute;
    top: 3px;
    bottom: 3px;
    left: 0;
    width: 3px;
    border-radius: var(--td-radius-small, 2px);
    background: var(--td-brand-color);
    content: '';
  }
}

.detail-kv-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 10px;
}

.detail-kv-item {
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  gap: 6px;
  padding: 10px 12px;
  background: var(--td-bg-color-secondarycontainer);
  border: 1px solid var(--td-component-border);
  border-radius: var(--td-radius-medium, 6px);
  min-width: 0;

  &__label {
    color: var(--td-text-color-placeholder);
    font-size: var(--td-font-size-body-small, 12px);
    line-height: 1.4;
  }

  &__value {
    display: flex;
    align-items: center;
    gap: 6px;
    color: var(--td-text-color-primary);
    font-size: var(--td-font-size-body-medium, 13px);
    font-weight: 600;
    line-height: 1.5;
    font-variant-numeric: tabular-nums;
    overflow-wrap: anywhere;
  }

  &--span-2 {
    grid-column: span 2;
  }

  &--span-3 {
    grid-column: 1 / -1;
  }
}

.detail-copy-wrap {
  display: flex;
  align-items: center;
  gap: 6px;
}

.finance-line-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.finance-line-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 10px 14px;
  background: var(--td-bg-color-secondarycontainer);
  border: 1px solid var(--td-component-border);
  border-radius: var(--td-radius-medium, 6px);

  span {
    color: var(--td-text-color-secondary);
    font-size: var(--td-font-size-body-small, 12px);
  }

  strong {
    color: var(--td-text-color-primary);
    font-size: var(--td-font-size-body-medium, 13px);
    font-weight: 600;
    font-variant-numeric: tabular-nums;
  }
}

.finance-line-item--stacked {
  flex-direction: column;
  align-items: stretch;
  gap: 6px;

  .finance-line-item__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 2px;
  }
}

@media (max-width: 768px) {
  .detail-kv-grid {
    grid-template-columns: 1fr;
  }

  .detail-kv-item--span-2,
  .detail-kv-item--span-3 {
    grid-column: 1;
  }
}

.finance-line-item--stacked {
  align-items: stretch;
  flex-direction: column;
  gap: 5px;
}

.finance-line-item__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
}

.finance-line-item__head strong {
  min-width: 0;
}

@media (width <= 560px) {
  .finance-detail-grid {
    grid-template-columns: 1fr;
  }
}
</style>
