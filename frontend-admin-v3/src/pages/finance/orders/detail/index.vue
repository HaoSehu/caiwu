<template>
  <div class="order-detail-page">
    <RecordDetailPage
      :loading="detailLoading"
      :ready="Boolean(order.id)"
      back-text="返回订单列表"
      eyebrow="订单详情"
      :title="fieldValue(order.order_no || order.id)"
      :description="`服务ID：${serviceIdLabel(order.service)}`"
      :status-label="order.status_label || orderStatusLabel(order.status)"
      :status-theme="orderStatusTheme(order.status)"
      :metrics="summaryMetrics"
      :tabs="tabs"
      :active-tab="activeTab"
      empty-text="订单不存在"
      @back="goBack"
      @refresh="loadDetail"
      @update:active-tab="(value) => (activeTab = value)"
    >
      <template #relations>
        <t-button v-if="order.invoice?.id || order.invoice_id" variant="outline" size="small" @click="openInvoiceDetail(order.invoice?.id || order.invoice_id)">
          查看账单详情
        </t-button>
        <t-button v-if="order.user_id" variant="outline" size="small" @click="router.push(`/admin/users/${order.user_id}`)">
          查看用户详情
        </t-button>
      </template>

      <template #tab-basic>
        <section class="order-detail-section">
          <h4>订单信息</h4>
          <div class="detail-kv-grid detail-kv-grid--two">
            <div class="detail-kv-item detail-kv-item--span-2">
              <span>订单号</span>
              <strong>{{ fieldValue(order.order_no) }}</strong>
            </div>
            <div class="detail-kv-item">
              <span>订单类型</span>
              <strong>{{ order.type_label || orderTypeLabel(order.type) }}</strong>
            </div>
            <div class="detail-kv-item">
              <span>状态</span>
              <t-tag :theme="orderStatusTheme(order.status)" variant="light">
                {{ order.status_label || orderStatusLabel(order.status) }}
              </t-tag>
            </div>
            <div class="detail-kv-item">
              <span>数量</span>
              <strong>{{ order.quantity || 1 }}</strong>
            </div>
            <div class="detail-kv-item">
              <span>计费周期</span>
              <strong>{{ fieldValue(order.billing_cycle) }}</strong>
            </div>
            <div class="detail-kv-item">
              <span>订单金额</span>
              <strong>{{ formatMoney(order.amount) }}</strong>
            </div>
            <div class="detail-kv-item">
              <span>优惠金额</span>
              <strong>{{ formatMoney(order.discount) }}</strong>
            </div>
            <div class="detail-kv-item">
              <span>实付金额</span>
              <strong>{{ formatMoney(order.paid_amount) }}</strong>
            </div>
            <div class="detail-kv-item">
              <span>支付时间</span>
              <strong>{{ formatDateTime(order.paid_at) }}</strong>
            </div>
            <div class="detail-kv-item">
              <span>创建时间</span>
              <strong>{{ formatDateTime(order.created_at) }}</strong>
            </div>
            <div class="detail-kv-item">
              <span>更新时间</span>
              <strong>{{ formatDateTime(order.updated_at) }}</strong>
            </div>
            <div v-if="order.remark" class="detail-kv-item detail-kv-item--span-2">
              <span>备注</span>
              <strong>{{ order.remark }}</strong>
            </div>
          </div>
        </section>

        <section class="order-detail-section">
          <h4>关联信息</h4>
          <div class="detail-kv-grid detail-kv-grid--two">
            <div class="detail-kv-item">
              <span>用户 ID</span>
              <strong>{{ order.user_id || '-' }}</strong>
            </div>
            <div class="detail-kv-item">
              <span>用户</span>
              <strong>{{ userName(order.user) }}</strong>
            </div>
            <div class="detail-kv-item detail-kv-item--span-2">
              <span>账单号</span>
              <div class="detail-inline-action">
                <strong>{{ fieldValue(order.invoice?.invoice_no) }}</strong>
                <t-button v-if="order.invoice?.id || order.invoice_id" size="small" variant="text" theme="primary" @click="openInvoiceDetail(order.invoice?.id || order.invoice_id)">
                  查看
                </t-button>
              </div>
            </div>
            <div class="detail-kv-item">
              <span>账单金额</span>
              <strong>{{ formatMoney(order.invoice?.amount) }}</strong>
            </div>
            <div class="detail-kv-item">
              <span>账单支付时间</span>
              <strong>{{ formatDateTime(order.invoice?.paid_at) }}</strong>
            </div>
            <div class="detail-kv-item detail-kv-item--span-2">
              <span>服务 ID</span>
              <strong>{{ serviceIdLabel(order.service) }}</strong>
            </div>
            <div class="detail-kv-item detail-kv-item--span-2">
              <span>服务到期</span>
              <strong>{{ formatDateTime(order.service?.expires_at) }}</strong>
            </div>
          </div>
        </section>

        <section v-if="couponInfo" class="order-detail-section">
          <h4>优惠券信息</h4>
          <div class="detail-kv-grid detail-kv-grid--two">
            <div class="detail-kv-item detail-kv-item--span-2">
              <span>优惠码</span>
              <strong>{{ fieldValue(couponInfo.code) }}</strong>
            </div>
            <div v-if="couponInfo.name" class="detail-kv-item">
              <span>优惠券名称</span>
              <strong>{{ fieldValue(couponInfo.name) }}</strong>
            </div>
            <div v-if="couponInfo.type" class="detail-kv-item">
              <span>类型</span>
              <strong>{{ fieldValue(couponInfo.type) }}</strong>
            </div>
            <div v-if="couponInfo.value" class="detail-kv-item detail-kv-item--span-2">
              <span>面值</span>
              <strong>{{ fieldValue(couponInfo.value) }}</strong>
            </div>
          </div>
        </section>
      </template>

      <template #tab-product>
        <section class="order-detail-section">
          <h4>产品信息</h4>
          <div class="detail-kv-grid detail-kv-grid--two">
            <div class="detail-kv-item detail-kv-item--span-2">
              <span>分类链路</span>
              <strong>{{ fieldValue(order.product_full_path || order.product_name) }}</strong>
            </div>
          </div>
        </section>

        <section v-if="configItems.length" class="order-detail-section">
          <h4>配置快照</h4>
          <div class="config-list">
            <div v-for="item in configItems" :key="item.label" class="config-item">
              <span>{{ item.label }}</span>
              <strong>{{ item.value }}</strong>
            </div>
          </div>
        </section>

        <section v-if="pricingItems.length" class="order-detail-section">
          <h4>配置定价</h4>
          <div class="config-list">
            <div v-for="item in pricingItems" :key="item.label" class="config-item">
              <span>{{ item.label }}</span>
              <strong>{{ item.value }}</strong>
            </div>
          </div>
        </section>
      </template>

      <template #tab-payments>
        <section class="order-detail-section">
          <h4>支付记录</h4>
          <div class="payment-list">
            <div v-for="payment in payments" :key="String(payment.id || payment.payment_no)" class="payment-item">
              <div class="payment-item__head">
                <div>
                  <span>支付单号</span>
                  <strong>{{ fieldValue(payment.payment_no || payment.id) }}</strong>
                </div>
                <t-tag :theme="paymentStatusTheme(payment)" variant="light">{{ paymentStatusLabel(payment) }}</t-tag>
              </div>
              <div class="detail-kv-grid detail-kv-grid--two">
                <div class="detail-kv-item">
                  <span>支付方式</span>
                  <strong>{{ fieldValue(payment.gateway_label || payment.gateway) }}</strong>
                </div>
                <div class="detail-kv-item detail-kv-item--span-2">
                  <span>第三方单号</span>
                  <strong>{{ fieldValue(payment.trade_no) }}</strong>
                </div>
                <div class="detail-kv-item">
                  <span>金额</span>
                  <strong>{{ formatMoney(payment.amount) }}</strong>
                </div>
                <div class="detail-kv-item">
                  <span>支付时间</span>
                  <strong>{{ formatDateTime(payment.paid_at || payment.created_at) }}</strong>
                </div>
              </div>
            </div>
          </div>
        </section>
      </template>
    </RecordDetailPage>

    <InvoiceDetailDrawer
      v-model:visible="invoiceDrawer.visible"
      :loading="invoiceDrawer.loading"
      :invoice="currentInvoice"
      :payments="invoicePayments"
      :items="invoiceItems"
      :logs="invoiceLogs"
      :status-label="invoiceStatusLabel(currentInvoice.raw_status ?? currentInvoice.status)"
      :status-theme="invoiceStatusTheme(currentInvoice.raw_status ?? currentInvoice.status)"
      @close="closeInvoiceDetail"
      @refresh="reloadInvoiceDetail"
      @view-order="(id) => id && router.push(`/admin/finance/orders/${id}`)"
      @view-user="(id) => id && router.push(`/admin/users/${id}`)"
    />
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { MessagePlugin } from 'tdesign-vue-next';

import { adminApi, type InvoiceRecord, type OrderRecord } from '@/api/admin';
import InvoiceDetailDrawer from '@/components/finance-record-detail/InvoiceDetailDrawer.vue';
import RecordDetailPage, { type RecordDetailMetric, type RecordDetailTab } from '@/components/finance-record-detail/RecordDetailPage.vue';
import { INVOICE_STATUS_MAP, ORDER_STATUS_MAP, toLabelMap, toTagTypeMap } from '@shared/statusConfig';

import './index.less';

const ORDER_TYPE_MAP: Record<string, string> = {
  new: '新购',
  normal: '新购',
  renew: '续费',
  upgrade: '附加配置',
};

const INVOICE_TYPE_MAP: Record<string, string> = {
  new: '新购',
  normal: '新购',
  renew: '续费',
  recharge: '充值',
  upgrade: '附加配置',
  deduction: '扣款',
  referral_credit: '推荐奖励账单',
  manual: '手工账单',
};

const SNAPSHOT_LABEL_MAP: Record<string, string> = {
  bw: '带宽',
  in_bw: '下行带宽',
  out_bw: '上行带宽',
  os: '操作系统',
  cpu: 'CPU',
  area: '区域',
  region: '区域',
  node: '节点',
  node_group: '节点分组',
  ip: 'IP数量',
  ip_num: 'IP数量',
  ipv6_num: 'IPv6数量',
  memory: '内存',
  hostname: '主机名',
  quantity: '数量',
  setup_fee: '初装费',
  base_amount: '基础金额',
  total_amount: '合计金额',
  config_amount: '配置金额',
  subtotal_amount: '小计金额',
  discount_amount: '优惠金额',
  amount: '金额',
  price: '价格',
  pricing: '价格',
  items: '配置项',
  meta: '扩展信息',
  configoption: '配置参数',
  kind: '类型',
  mode: '模式',
  target_label: '目标服务',
  target_service_id: '目标服务ID',
  product_id: '产品ID',
  product_name: '产品名称',
  billing_cycle: '周期',
  billingcycle: '周期',
  billingcycle_zh: '周期',
  period: '周期',
  remark: '备注',
};

const route = useRoute();
const router = useRouter();
const detailLoading = ref(false);
const order = ref<OrderRecord>({} as OrderRecord);
const activeTab = ref('basic');

const orderStatusLabelMap = toLabelMap(ORDER_STATUS_MAP);
const orderStatusTypeMap = toTagTypeMap(ORDER_STATUS_MAP);
const invoiceStatusLabelMap = toLabelMap(INVOICE_STATUS_MAP);
const invoiceStatusTypeMap = toTagTypeMap(INVOICE_STATUS_MAP);

const invoiceDrawer = reactive({
  visible: false,
  loading: false,
  currentId: 0,
  detail: { invoice: {}, payments: [], items: [], logs: [] } as {
    invoice: InvoiceRecord;
    payments: Record<string, unknown>[];
    items: Record<string, unknown>[];
    logs: Record<string, unknown>[];
  },
});
const payments = computed<Record<string, unknown>[]>(() => {
  const list = (order.value as Record<string, unknown>).payments;
  return Array.isArray(list) ? list : [];
});

const summaryMetrics = computed<RecordDetailMetric[]>(() => [
  { label: '订单金额', value: formatMoney(order.value.amount), primary: true },
  { label: '实付金额', value: formatMoney(order.value.paid_amount) },
  { label: '创建时间', value: formatDateTime(order.value.created_at) },
]);

const tabs = computed<RecordDetailTab[]>(() => [
  { value: 'basic', label: '基本信息' },
  { value: 'product', label: '产品配置' },
  { value: 'payments', label: '支付记录', show: payments.value.length > 0 },
]);

const couponInfo = computed(() => {
  const raw = order.value as Record<string, unknown>;
  const coupon = raw.coupon as Record<string, unknown> | null | undefined;
  const code = String(raw.coupon_code || coupon?.code || '');
  if (!coupon && !code) return null;
  return {
    code,
    name: coupon?.name as string | undefined,
    type: coupon?.type as string | undefined,
    value: coupon?.value as string | undefined,
  };
});

const configItems = computed(() => {
  const snapshot = order.value.config_snapshot;
  if (!snapshot || typeof snapshot !== 'object') return [];
  return flattenSnapshot(snapshot as Record<string, unknown>, configValueLabelMap.value);
});

const pricingItems = computed(() => {
  const snapshot = order.value.config_pricing_snapshot;
  if (!snapshot || typeof snapshot !== 'object') return [];
  return flattenSnapshot(snapshot as Record<string, unknown>);
});

const configValueLabelMap = computed<Record<string, string>>(() => {
  const snapshot = order.value.config_pricing_snapshot as Record<string, unknown> | null | undefined;
  const items = Array.isArray(snapshot?.items) ? snapshot.items : [];
  return items.reduce((result, item) => {
    const record = toRecord(item);
    const field = String(record.field || '').trim();
    const label = String(record.value_label || record.suboption_name || record.option_name || record.value || '').trim();
    if (field && label) result[field] = label;
    return result;
  }, {} as Record<string, string>);
});

const currentInvoice = computed(() => invoiceDrawer.detail.invoice || ({} as InvoiceRecord));
const invoicePayments = computed(() => invoiceDrawer.detail.payments || []);
const invoiceLogs = computed(() => invoiceDrawer.detail.logs || []);
const invoiceItems = computed(() => {
  const sceneItems = currentInvoice.value.scene?.items;
  if (Array.isArray(sceneItems)) return sceneItems as Record<string, unknown>[];
  return invoiceDrawer.detail.items || [];
});

function flattenSnapshot(obj: Record<string, unknown>, valueLabelMap: Record<string, string> = {}): { label: string; value: string }[] {
  const result: { label: string; value: string }[] = [];
  for (const [key, val] of Object.entries(obj)) {
    if (['unit_setup_fee', 'unit_base_amount', 'unit_total_amount', 'unit_config_amount'].includes(key)) continue;
    if (val === null || val === undefined || val === '') continue;
    if (key === 'items' && Array.isArray(val)) {
      val.forEach((item, index) => {
        const record = toRecord(item);
        result.push({
          label: snapshotLabel(record.label || record.name || record.option_name || record.spec_key || `${key}.${index + 1}`),
          value: formatSnapshotItem(record),
        });
      });
      continue;
    }
    if (val && typeof val === 'object' && !Array.isArray(val)) {
      const nested = flattenSnapshot(val as Record<string, unknown>, valueLabelMap);
      nested.forEach((item) => result.push({ label: `${snapshotLabel(key)} / ${item.label}`, value: item.value }));
    } else {
      result.push({ label: snapshotLabel(key), value: formatSnapshotValue(valueLabelMap[key] || val, key) });
    }
  }
  return result;
}

function snapshotLabel(value: unknown) {
  const key = String(value || '').trim();
  if (!key) return '-';
  return SNAPSHOT_LABEL_MAP[key] || key;
}

function formatSnapshotItem(record: Record<string, unknown>) {
  const value = fieldValue(
    record.value_label ||
      record.option_value_label ||
      record.suboption_label ||
      record.value ||
      record.option_value ||
      record.suboption_name ||
      record.suboption_name_first ||
      record.option_name_first ||
      record.version ||
      record.label ||
      record.name,
  );
  const amount = record.amount ?? record.total_amount ?? record.price ?? record.pricing ?? record.fee;
  const setupFee = record.setup_fee ?? record.setupfee;
  const parts = [value];
  if (amount !== null && amount !== undefined && amount !== '') parts.push(`金额 ${formatMoney(amount)}`);
  if (setupFee !== null && setupFee !== undefined && setupFee !== '') parts.push(`初装费 ${formatMoney(setupFee)}`);
  return parts.join(' / ');
}

function formatSnapshotValue(value: unknown, key = ''): string {
  if (value === null || value === undefined || value === '') return '-';
  if (Array.isArray(value)) {
    return value
      .map((item, index) => {
        if (item && typeof item === 'object') return `${index + 1}. ${formatSnapshotItem(item as Record<string, unknown>)}`;
        return fieldValue(item);
      })
      .join('；');
  }
  if (value && typeof value === 'object') {
    const record = value as Record<string, unknown>;
    return Object.entries(record)
      .filter(([, childValue]) => childValue !== null && childValue !== undefined && childValue !== '')
      .map(([childKey, childValue]) => `${snapshotLabel(childKey)}：${formatSnapshotValue(childValue, childKey)}`)
      .join('；');
  }
  const raw = String(value);
  if (['bw', 'in_bw', 'out_bw'].includes(key) && /^\d+(\.\d+)?$/.test(raw)) return `${raw} Mbps`;
  if (key === 'memory' && /^\d+(\.\d+)?$/.test(raw)) return `${raw} MB`;
  if (['ip_num', 'ipv6_num', 'quantity'].includes(key) && /^\d+(\.\d+)?$/.test(raw)) return `${raw} 个`;
  return raw;
}

async function loadDetail() {
  const id = route.params.id as string;
  if (!id) return;
  detailLoading.value = true;
  try {
    const response = await adminApi.orders.detail(id);
    order.value = response;
  } catch (error) {
    MessagePlugin.error(errorMessage(error, '加载订单详情失败'));
  } finally {
    detailLoading.value = false;
  }
}

function goBack() {
  router.push('/admin/finance/orders');
}

async function openInvoiceDetail(id: unknown) {
  if (!id) return;
  invoiceDrawer.visible = true;
  invoiceDrawer.currentId = Number(id);
  invoiceDrawer.detail = {
    invoice: order.value.invoice ? ({ ...order.value.invoice } as InvoiceRecord) : {},
    payments: [],
    items: [],
    logs: [],
  };
  await reloadInvoiceDetail();
}

async function reloadInvoiceDetail() {
  if (!invoiceDrawer.currentId) return;
  invoiceDrawer.loading = true;
  try {
    const response = await adminApi.invoices.detail(invoiceDrawer.currentId);
    invoiceDrawer.detail = normalizeInvoiceDetail(response, currentInvoice.value);
  } catch (error) {
    MessagePlugin.error(errorMessage(error, '加载账单详情失败'));
  } finally {
    invoiceDrawer.loading = false;
  }
}

function closeInvoiceDetail() {
  invoiceDrawer.visible = false;
  invoiceDrawer.currentId = 0;
  invoiceDrawer.detail = { invoice: {}, payments: [], items: [], logs: [] };
}

function normalizeInvoiceDetail(payload: Record<string, unknown> = {}, fallback: InvoiceRecord = {}) {
  const invoice = payload.invoice && typeof payload.invoice === 'object' ? (payload.invoice as InvoiceRecord) : (payload as InvoiceRecord);
  return {
    invoice: {
      ...fallback,
      ...invoice,
      payment_summary: { ...(fallback.payment_summary || {}), ...(invoice.payment_summary || {}) },
      order: invoice.order || fallback.order || null,
      product: invoice.product || fallback.product || null,
      scene: invoice.scene || fallback.scene || {},
    },
    payments: Array.isArray(payload.payments) ? (payload.payments as Record<string, unknown>[]) : [],
    items: Array.isArray(payload.items) ? (payload.items as Record<string, unknown>[]) : [],
    logs: Array.isArray(payload.logs) ? (payload.logs as Record<string, unknown>[]) : [],
  };
}

function orderTypeLabel(type: unknown) {
  return ORDER_TYPE_MAP[String(type || '')] || fieldValue(type);
}

function orderStatusLabel(status: unknown) {
  return orderStatusLabelMap[String(status ?? '')] || fieldValue(status);
}

function orderStatusTheme(status: unknown) {
  const value = orderStatusTypeMap[String(status ?? '')] || 'default';
  return value === 'info' ? 'default' : value;
}

function invoiceTypeLabel(type: unknown) {
  return INVOICE_TYPE_MAP[String(type || '')] || fieldValue(type);
}

function invoiceStatusLabel(status: unknown) {
  return invoiceStatusLabelMap[String(status ?? '')] || fieldValue(status);
}

function invoiceStatusTheme(status: unknown) {
  const value = invoiceStatusTypeMap[String(status ?? '')] || 'default';
  return value === 'info' ? 'default' : value;
}

function paymentStatusLabel(payment: Record<string, unknown>) {
  if (payment.status_label) return String(payment.status_label);
  const status = Number(payment.status);
  if (status === 0) return '待支付';
  if (status === 1) return '已支付';
  if (status === 2) return '已失败';
  if (status === 3) return '已退款';
  return fieldValue(payment.status);
}

function paymentStatusTheme(payment: Record<string, unknown>) {
  const status = Number(payment.status);
  if (status === 0) return 'warning';
  if (status === 1) return 'success';
  if (status === 2) return 'danger';
  return 'default';
}

function userName(user: unknown) {
  const record = toRecord(user);
  return fieldValue(record.nickname || record.display_name || record.email);
}

function serviceIdLabel(service: unknown) {
  const record = toRecord(service);
  return fieldValue(record.service_id || record.id);
}

function fieldValue(value: unknown) {
  if (value === null || value === undefined || value === '') return '-';
  return String(value);
}

function formatMoney(value: unknown) {
  return `¥${Number(value || 0).toFixed(2)}`;
}

function formatDateTime(value: unknown) {
  if (!value) return '-';
  const date = new Date(String(value).replace(/-/g, '/'));
  if (Number.isNaN(date.getTime())) return String(value);
  const pad = (num: number) => String(num).padStart(2, '0');
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

function toRecord(value: unknown): Record<string, unknown> {
  return value && typeof value === 'object' ? (value as Record<string, unknown>) : {};
}

function errorMessage(error: unknown, fallback: string) {
  const record = toRecord(error);
  const response = toRecord(record.response);
  const data = toRecord(response.data);
  return String(data.message || record.message || fallback);
}

onMounted(loadDetail);
</script>
