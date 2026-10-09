<template>
  <div class="order-detail-page">
    <record-detail-page
      :loading="detailLoading"
      :ready="Boolean(order.id)"
      back-text="返回订单列表"
      eyebrow="订单详情"
      :title="fieldValue(order.order_no || order.id)"
      :description="serviceSubtitle"
      :status-label="orderStatusLabel(order.status)"
      :status-theme="orderStatusTheme(order.status)"
      :metrics="summaryMetrics"
      :tabs="tabs"
      :active-tab="activeTab"
      empty-text="订单不存在"
      @back="goBack"
      @refresh="loadDetail"
      @update:active-tab="(value) => (activeTab = value)"
    >
      <!-- 头部右上角快捷动作 -->
      <template #toolbar-actions>
        <t-button
          v-if="order.invoice_id"
          variant="outline"
          theme="primary"
          @click="openInvoiceDetail(order.invoice_id)"
        >
          查看关联账单
        </t-button>
        <t-button
          v-if="order.user_id"
          variant="outline"
          @click="router.push(`/admin/users/${order.user_id}`)"
        >
          查看用户资料
        </t-button>
        <t-button
          v-if="order.service_id"
          variant="outline"
          @click="router.push(`/admin/services?service_id=${order.service_id}`)"
        >
          查看服务实例
        </t-button>
      </template>

      <!-- 选项卡 1：基本信息与关联信息 -->
      <template #tab-basic>
        <!-- 订单主信息 -->
        <section class="order-detail-section">
          <div class="order-detail-section__header">
            <h4>订单主信息</h4>
          </div>
          <div class="detail-kv-grid">
            <div class="detail-kv-item detail-kv-item--span-2">
              <span class="detail-kv-item__label">订单号</span>
              <div class="detail-kv-item__value detail-copy-wrap">
                <strong>{{ fieldValue(order.order_no) }}</strong>
                <t-tooltip content="复制订单号" placement="top">
                  <t-button
                    variant="text"
                    shape="square"
                    size="small"
                    @click="copyText(order.order_no || '')"
                  >
                    <template #icon><file-copy-icon /></template>
                  </t-button>
                </t-tooltip>
              </div>
            </div>
            <div class="detail-kv-item">
              <span class="detail-kv-item__label">订单类型</span>
              <div class="detail-kv-item__value">
                <t-tag theme="primary" variant="light">
                  {{ order.type_label || orderTypeLabel(order.type) }}
                </t-tag>
              </div>
            </div>
            <div class="detail-kv-item">
              <span class="detail-kv-item__label">订单状态</span>
              <div class="detail-kv-item__value">
                <t-tag :theme="orderStatusTheme(order.status)" variant="light">
                  {{ orderStatusLabel(order.status) }}
                </t-tag>
              </div>
            </div>
            <div class="detail-kv-item">
              <span class="detail-kv-item__label">购买数量</span>
              <strong class="detail-kv-item__value">{{ order.quantity || 1 }} 台</strong>
            </div>
            <div class="detail-kv-item">
              <span class="detail-kv-item__label">计费周期</span>
              <strong class="detail-kv-item__value">{{ billingCycleDisplay(order.billing_cycle) }}</strong>
            </div>
            <div class="detail-kv-item">
              <span class="detail-kv-item__label">创建时间</span>
              <strong class="detail-kv-item__value">{{ formatDateTime(order.created_at) }}</strong>
            </div>
            <div class="detail-kv-item">
              <span class="detail-kv-item__label">更新时间</span>
              <strong class="detail-kv-item__value">{{ formatDateTime(order.updated_at) }}</strong>
            </div>
          </div>
        </section>

        <!-- 金额与结算明细 -->
        <section class="order-detail-section">
          <div class="order-detail-section__header">
            <h4>金额与结算明细</h4>
          </div>
          <div class="detail-kv-grid">
            <div class="detail-kv-item">
              <span class="detail-kv-item__label">订单应付金额</span>
              <strong class="detail-kv-item__value is-money">{{ formatMoney(order.amount) }}</strong>
            </div>
            <div class="detail-kv-item">
              <span class="detail-kv-item__label">优惠券减免</span>
              <div class="detail-kv-item__value" :class="{ 'is-discount': hasCouponDiscount }">
                <span v-if="hasCouponDiscount">-{{ formatMoney(order.discount) }}</span>
                <span v-else class="is-dimmed">无</span>
                <t-tag v-if="order.coupon_code" size="small" variant="light" theme="warning">
                  {{ order.coupon_code }}
                </t-tag>
              </div>
            </div>
            <div class="detail-kv-item">
              <span class="detail-kv-item__label">会员折扣</span>
              <div class="detail-kv-item__value" :class="{ 'is-discount': hasMemberDiscount }">
                <span v-if="hasMemberDiscount">-{{ formatMoney(order.member_discount_amount) }}</span>
                <span v-else class="is-dimmed">无</span>
                <t-tag v-if="memberDiscountLevelName" size="small" variant="light" theme="success">
                  {{ memberDiscountLevelName }}
                </t-tag>
              </div>
            </div>
            <div class="detail-kv-item">
              <span class="detail-kv-item__label">实付到账金额</span>
              <strong class="detail-kv-item__value is-money" :style="{ color: isPaid ? 'var(--td-brand-color)' : '' }">
                {{ formatMoney(order.paid_amount) }}
              </strong>
            </div>
            <div class="detail-kv-item detail-kv-item--span-2">
              <span class="detail-kv-item__label">支付完成时间</span>
              <strong class="detail-kv-item__value">{{ formatDateTime(order.paid_at) }}</strong>
            </div>
            <div class="detail-kv-item detail-kv-item--span-2">
              <span class="detail-kv-item__label">支付状态说明</span>
              <strong class="detail-kv-item__value">{{ paymentStateSummary }}</strong>
            </div>
          </div>
        </section>

        <!-- 关联业务实体 -->
        <section class="order-detail-section">
          <div class="order-detail-section__header">
            <h4>关联业务实体</h4>
          </div>
          <div class="detail-kv-grid">
            <div class="detail-kv-item detail-kv-item--span-2">
              <span class="detail-kv-item__label">关联用户</span>
              <div class="detail-kv-item__value detail-inline-action">
                <div>
                  <strong>{{ userName(order.user) }}</strong>
                  <span v-if="order.user_id" style="color: var(--td-text-color-placeholder); margin-left: 6px;">(#{{ order.user_id }})</span>
                </div>
                <t-button
                  v-if="order.user_id"
                  size="small"
                  variant="text"
                  theme="primary"
                  @click="router.push(`/admin/users/${order.user_id}`)"
                >
                  用户资料 →
                </t-button>
              </div>
            </div>

            <div class="detail-kv-item detail-kv-item--span-2">
              <span class="detail-kv-item__label">关联账单</span>
              <div class="detail-kv-item__value detail-inline-action">
                <strong v-if="order.invoice?.invoice_no">{{ order.invoice.invoice_no }}</strong>
                <span v-else class="is-dimmed">未关联账单</span>
                <t-button
                  v-if="order.invoice_id"
                  size="small"
                  variant="text"
                  theme="primary"
                  @click="openInvoiceDetail(order.invoice_id)"
                >
                  查看账单抽屉 →
                </t-button>
              </div>
            </div>

            <div v-if="order.service_id || order.service" class="detail-kv-item detail-kv-item--span-2">
              <span class="detail-kv-item__label">关联服务实例</span>
              <div class="detail-kv-item__value detail-inline-action">
                <strong>{{ serviceIdLabel(order.service) }}</strong>
                <t-button
                  v-if="order.service_id"
                  size="small"
                  variant="text"
                  theme="primary"
                  @click="router.push(`/admin/services?service_id=${order.service_id}`)"
                >
                  服务详情 →
                </t-button>
              </div>
            </div>

            <div v-if="order.trace_id" class="detail-kv-item detail-kv-item--span-2">
              <span class="detail-kv-item__label">链路追踪号 (Trace ID)</span>
              <div class="detail-kv-item__value detail-copy-wrap">
                <span style="font-family: monospace; font-size: 13px;">{{ order.trace_id }}</span>
                <t-tooltip content="复制 Trace ID" placement="top">
                  <t-button
                    variant="text"
                    shape="square"
                    size="small"
                    @click="copyText(order.trace_id || '')"
                  >
                    <template #icon><file-copy-icon /></template>
                  </t-button>
                </t-tooltip>
              </div>
            </div>
          </div>

          <!-- 订单备注 -->
          <div v-if="order.remark" class="order-remark-box">
            <span>管理员/系统备注</span>
            <p>{{ order.remark }}</p>
          </div>
        </section>
      </template>

      <!-- 选项卡 2：产品与配置快照 -->
      <template #tab-product>
        <section class="order-detail-section">
          <div class="order-detail-section__header">
            <h4>订购产品信息</h4>
          </div>
          <div class="detail-kv-grid">
            <div class="detail-kv-item detail-kv-item--span-2">
              <span class="detail-kv-item__label">产品名称</span>
              <strong class="detail-kv-item__value">{{ fieldValue(order.product_name || toRecord(order.product).name) }}</strong>
            </div>
            <div class="detail-kv-item detail-kv-item--span-2">
              <span class="detail-kv-item__label">产品完整路径</span>
              <strong class="detail-kv-item__value">{{ fieldValue(order.product_full_path) }}</strong>
            </div>
          </div>
        </section>

        <!-- 价格/配置选项快照 -->
        <section v-if="pricingItems.length" class="order-detail-section">
          <div class="order-detail-section__header">
            <h4>计价配置明细</h4>
          </div>
          <div class="config-list">
            <div v-for="item in pricingItems" :key="item.label" class="config-item">
              <span>{{ item.label }}</span>
              <strong>{{ item.value }}</strong>
            </div>
          </div>
        </section>

        <!-- 基础配置快照 -->
        <section v-if="configItems.length" class="order-detail-section">
          <div class="order-detail-section__header">
            <h4>产品参数快照</h4>
          </div>
          <div class="config-list">
            <div v-for="item in configItems" :key="item.label" class="config-item">
              <span>{{ item.label }}</span>
              <strong>{{ item.value }}</strong>
            </div>
          </div>
        </section>

        <!-- 开通服务实例快照（仅新购订单） -->
        <section v-if="serviceSnapshotItems.length" class="order-detail-section">
          <div class="order-detail-section__header">
            <h4>交付实例快照</h4>
          </div>
          <div class="config-list">
            <div v-for="item in serviceSnapshotItems" :key="item.label" class="config-item">
              <span>{{ item.label }}</span>
              <strong>{{ item.value }}</strong>
            </div>
          </div>
        </section>
      </template>

      <!-- 选项卡 3：关联支付记录 -->
      <template #tab-payments>
        <section class="order-detail-section">
          <div class="order-detail-section__header">
            <h4>第三方支付与入账记录</h4>
          </div>
          <div v-if="payments.length" class="payment-list">
            <div v-for="payment in payments" :key="String(payment.id || payment.payment_no)" class="payment-item">
              <div class="payment-item__head">
                <div>
                  <strong>{{ fieldValue(payment.payment_no) }}</strong>
                  <span style="font-size: 12px; color: var(--td-text-color-placeholder);">
                    第三方单号：{{ fieldValue(payment.trade_no) }}
                  </span>
                </div>
                <t-tag :theme="paymentStatusTheme(payment)" variant="light">
                  {{ paymentStatusLabel(payment) }}
                </t-tag>
              </div>
              <div class="payment-item__body">
                <div class="detail-kv-grid">
                  <div class="detail-kv-item">
                    <span class="detail-kv-item__label">支付渠道</span>
                    <strong class="detail-kv-item__value">{{ fieldValue(payment.gateway) }}</strong>
                  </div>
                  <div class="detail-kv-item">
                    <span class="detail-kv-item__label">支付金额</span>
                    <strong class="detail-kv-item__value is-money">{{ formatMoney(payment.amount) }}</strong>
                  </div>
                  <div class="detail-kv-item">
                    <span class="detail-kv-item__label">支付时间</span>
                    <strong class="detail-kv-item__value">{{ formatDateTime(payment.paid_at || payment.created_at) }}</strong>
                  </div>
                  <div class="detail-kv-item">
                    <span class="detail-kv-item__label">Trace ID</span>
                    <strong class="detail-kv-item__value">{{ fieldValue(payment.trace_id) }}</strong>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <t-empty v-else description="暂无支付记录" />
        </section>
      </template>
    </record-detail-page>

    <!-- 关联账单详情抽屉 -->
    <invoice-detail-drawer
      v-model:visible="invoiceDrawer.visible"
      :loading="invoiceDrawer.loading"
      :invoice="currentInvoice"
      :payments="invoicePayments"
      :items="invoiceItems"
      :logs="invoiceLogs"
      :status-label="invoiceStatusLabel(currentInvoice.status)"
      :status-theme="invoiceStatusTheme(currentInvoice.status)"
      @close="closeInvoiceDetail"
      @refresh="reloadInvoiceDetail"
      @view-order="(id) => id && router.push(`/admin/finance/orders/${id}`)"
      @view-user="(id) => id && router.push(`/admin/users/${id}`)"
    />
  </div>
</template>
<script setup lang="ts">
import './index.less';

import {
  getStatusLabel,
  getStatusTagType,
  INVOICE_STATUS_MAP,
  ORDER_STATUS_MAP,
  ORDER_TYPE_MAP,
  PAYMENT_STATUS_MAP,
  toLabelMap,
  toTagTypeMap,
} from '@shared/statusConfig';
import { FileCopyIcon } from 'tdesign-icons-vue-next';
import { MessagePlugin } from 'tdesign-vue-next';
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';

import type { InvoiceRecord, OrderRecord } from '@/api/admin';
import { adminApi } from '@/api/admin';
import InvoiceDetailDrawer from '@/components/finance-record-detail/InvoiceDetailDrawer.vue';
import type { RecordDetailMetric, RecordDetailTab } from '@/components/record-detail-page/index.vue';
import RecordDetailPage from '@/components/record-detail-page/index.vue';
import {
  copyToClipboard,
  fieldValue,
  formatBillingCycle,
  formatDateTime,
  formatMoney,
} from '@/utils/format';
import { errorMessage } from '@/utils/userMessage';

defineOptions({
  name: 'AdminFinanceOrderDetail',
});

const SNAPSHOT_LABEL_MAP: Record<string, string> = {
  bw: '带宽',
  in_bw: '下行带宽',
  out_bw: '上行带宽',
  os: '操作系统',
  cpu: 'CPU',
  memory: '内存',
  disk: '数据盘',
  system_disk: '系统盘',
  ip_num: 'IPv4 数量',
  ipv6_num: 'IPv6 数量',
  line: '线路',
  data_center: '数据中心',
  region: '区域',
  billing_cycle: '计费周期',
  upgrade_product_id: '升降级商品',
};

const route = useRoute();
const router = useRouter();

const detailLoading = ref(false);
const detailRequestSeq = ref(0);
const invoiceRequestSeq = ref(0);
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

const isPaid = computed(() => Number(order.value.status) === 1);
const hasCouponDiscount = computed(() => Number(order.value.discount || 0) > 0);
const hasMemberDiscount = computed(() => Number(order.value.member_discount_amount || 0) > 0);

const memberDiscountLevelName = computed(() => {
  const snap = order.value.member_discount_snapshot as Record<string, unknown> | null | undefined;
  if (!snap || typeof snap !== 'object') return '';
  return String(snap.member_level_name || snap.group_name || '').trim();
});

const serviceSubtitle = computed(() => {
  const service = order.value.service;
  if (service && typeof service === 'object') {
    const s = service as Record<string, unknown>;
    return `关联服务：#${s.id || s.service_id || '-'}${s.name ? ` (${s.name})` : ''}`;
  }
  return order.value.service_id ? `关联服务：#${order.value.service_id}` : '未关联独立服务实例';
});

const paymentStateSummary = computed(() => {
  if (isPaid.value) {
    return `已支付（实付 ${formatMoney(order.value.paid_amount)}）`;
  }
  if (Number(order.value.status) === 4) {
    return '订单已取消，无需支付';
  }
  if (Number(order.value.status) === 5) {
    return '订单已全额退款';
  }
  return `待支付（应付 ${formatMoney(order.value.amount)}）`;
});

const summaryMetrics = computed<RecordDetailMetric[]>(() => {
  const metrics: RecordDetailMetric[] = [
    { label: '订单金额', value: formatMoney(order.value.amount), primary: true },
  ];

  if (hasCouponDiscount.value || hasMemberDiscount.value) {
    const totalDiscount = Number(order.value.discount || 0) + Number(order.value.member_discount_amount || 0);
    metrics.push({
      label: '优惠抵扣',
      value: `-${formatMoney(totalDiscount)}`,
    });
  }

  metrics.push(
    { label: '实付金额', value: formatMoney(order.value.paid_amount) },
    { label: '创建时间', value: formatDateTime(order.value.created_at) },
  );

  return metrics;
});

const payments = computed<Record<string, unknown>[]>(() => {
  const list = (order.value as Record<string, unknown>).payments;
  return Array.isArray(list) ? list : [];
});

const tabs = computed<RecordDetailTab[]>(() => [
  { value: 'basic', label: '基本信息' },
  { value: 'product', label: '产品与配置' },
  { value: 'payments', label: '支付记录', show: payments.value.length > 0 },
]);

const pricingItems = computed(() => {
  const snapshot = order.value.config_pricing_snapshot;
  if (!snapshot || typeof snapshot !== 'object') return [];
  return flattenSnapshot(snapshot as Record<string, unknown>);
});

const configItems = computed(() => {
  const snapshot = order.value.config_snapshot;
  if (!snapshot || typeof snapshot !== 'object') return [];
  return flattenSnapshot(snapshot as Record<string, unknown>, configValueLabelMap.value);
});

const isNewOrder = computed(() => order.value.type === 'new');

const serviceSnapshotItems = computed(() => {
  if (!isNewOrder.value) return [];
  const snapshot = order.value.service_snapshot;
  if (!snapshot || typeof snapshot !== 'object') return [];
  return flattenSnapshot(snapshot as Record<string, unknown>);
});

const configValueLabelMap = computed<Record<string, string>>(() => {
  const snapshot = order.value.config_pricing_snapshot as Record<string, unknown> | null | undefined;
  const items = Array.isArray(snapshot?.items) ? snapshot.items : [];
  return items.reduce(
    (result, item) => {
      const record = toRecord(item);
      const field = String(record.field || '').trim();
      const label = String(
        record.value_label || record.suboption_name || record.option_name || record.value || '',
      ).trim();
      if (field && label) result[field] = label;
      return result;
    },
    {} as Record<string, string>,
  );
});

const currentInvoice = computed(() => invoiceDrawer.detail.invoice || ({} as InvoiceRecord));
const invoicePayments = computed(() => invoiceDrawer.detail.payments || []);
const invoiceLogs = computed(() => invoiceDrawer.detail.logs || []);
const invoiceItems = computed(() => {
  const sceneItems = currentInvoice.value.scene?.items;
  if (Array.isArray(sceneItems)) return sceneItems as Record<string, unknown>[];
  return invoiceDrawer.detail.items || [];
});

function billingCycleDisplay(cycle: unknown) {
  return formatBillingCycle(cycle);
}

function copyText(text: unknown) {
  void copyToClipboard(String(text || ''));
}

function flattenSnapshot(
  obj: Record<string, unknown>,
  valueLabelMap: Record<string, string> = {},
): { label: string; value: string }[] {
  const result: { label: string; value: string }[] = [];
  for (const [key, val] of Object.entries(obj)) {
    if (['unit_setup_fee', 'unit_base_amount', 'unit_total_amount', 'unit_config_amount'].includes(key)) continue;
    if (key.startsWith('_')) continue;
    if (val === null || val === undefined || val === '') continue;
    if (key === 'connection_secret') continue;
    if (key === 'items' && Array.isArray(val)) {
      val.forEach((item, index) => {
        const record = toRecord(item);
        result.push({
          label: snapshotLabel(
            record.label || record.name || record.option_name || record.spec_key || `${key}.${index + 1}`,
          ),
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
        if (item && typeof item === 'object')
          return `${index + 1}. ${formatSnapshotItem(item as Record<string, unknown>)}`;
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
  if (['bw', 'in_bw', 'out_bw'].includes(key) && /^\d+(?:\.\d+)?$/.test(raw)) return `${raw} Mbps`;
  if (key === 'memory' && /^\d+(?:\.\d+)?$/.test(raw)) return `${raw} MB`;
  if (['ip_num', 'ipv6_num', 'quantity'].includes(key) && /^\d+(?:\.\d+)?$/.test(raw)) return `${raw} 个`;
  return raw;
}

async function loadDetail() {
  const rawId = route.params.id as string;
  if (!/^\d+$/.test(rawId) || Number(rawId) < 1) {
    detailRequestSeq.value += 1;
    MessagePlugin.warning('无效的订单 ID');
    router.replace('/admin/finance/orders');
    return;
  }
  const seq = ++detailRequestSeq.value;
  detailLoading.value = true;
  try {
    const response = await adminApi.orders.detail(rawId);
    if (seq !== detailRequestSeq.value) return;
    order.value = response;
  } catch (error) {
    if (seq !== detailRequestSeq.value) return;
    MessagePlugin.error(errorMessage(error, '加载订单详情失败'));
  } finally {
    if (seq === detailRequestSeq.value) detailLoading.value = false;
  }
}

function goBack() {
  if (window.history.length > 1) {
    router.back();
    return;
  }
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
  const seq = ++invoiceRequestSeq.value;
  invoiceDrawer.loading = true;
  try {
    const response = await adminApi.invoices.detail(invoiceDrawer.currentId);
    if (seq !== invoiceRequestSeq.value) return;
    invoiceDrawer.detail = normalizeInvoiceDetail(response, currentInvoice.value);
  } catch (error) {
    if (seq !== invoiceRequestSeq.value) return;
    MessagePlugin.error(errorMessage(error, '加载账单详情失败'));
  } finally {
    if (seq === invoiceRequestSeq.value) invoiceDrawer.loading = false;
  }
}

function closeInvoiceDetail() {
  invoiceDrawer.visible = false;
  invoiceDrawer.currentId = 0;
  invoiceDrawer.detail = { invoice: {}, payments: [], items: [], logs: [] };
}

function normalizeInvoiceDetail(payload: Record<string, unknown> = {}, fallback: InvoiceRecord = {}) {
  const invoice =
    payload.invoice && typeof payload.invoice === 'object'
      ? (payload.invoice as InvoiceRecord)
      : (payload as InvoiceRecord);
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

function orderStatusTheme(status: unknown): 'default' | 'primary' | 'success' | 'warning' | 'danger' {
  const value = orderStatusTypeMap[String(status ?? '')] || 'default';
  if (value === 'info') return 'default';
  if (value === 'blue') return 'primary';
  return value as 'default' | 'primary' | 'success' | 'warning' | 'danger';
}

function invoiceStatusLabel(status: unknown) {
  return invoiceStatusLabelMap[String(status ?? '')] || fieldValue(status);
}

function invoiceStatusTheme(status: unknown) {
  const value = invoiceStatusTypeMap[String(status ?? '')] || 'default';
  return value === 'info' ? 'default' : value;
}

function paymentStatusLabel(payment: Record<string, unknown>) {
  return getStatusLabel(PAYMENT_STATUS_MAP, Number(payment.status));
}

function paymentStatusTheme(payment: Record<string, unknown>) {
  const value = getStatusTagType(PAYMENT_STATUS_MAP, Number(payment.status));
  return value === 'info' || value === 'purple' ? 'default' : value;
}

function userName(user: unknown) {
  const record = toRecord(user);
  return fieldValue(record.nickname || record.display_name || record.email);
}

function serviceIdLabel(service: unknown) {
  const record = toRecord(service);
  return fieldValue(record.service_id || record.id);
}

function toRecord(value: unknown): Record<string, unknown> {
  return value && typeof value === 'object' ? (value as Record<string, unknown>) : {};
}

onMounted(loadDetail);
watch(() => route.params.id, loadDetail);
onBeforeUnmount(() => {
  detailRequestSeq.value += 1;
  invoiceRequestSeq.value += 1;
});
</script>
