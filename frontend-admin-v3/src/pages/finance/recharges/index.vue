<template>
  <div class="finance-recharges-page">
    <t-card :bordered="false">
      <div class="recharge-filter">
        <t-input
          class="filter-keyword"
          v-model="filters.keyword"
          clearable
          placeholder="搜索支付号 / 第三方单号 / 用户"
          @enter="handleSearch"
          @clear="handleSearch"
        >
          <template #suffix-icon><search-icon /></template>
        </t-input>
        <t-select class="filter-status" v-model="filters.status" clearable placeholder="状态" @change="handleSearch">
          <t-option v-for="item in paymentStatusOptions" :key="item.value" :label="item.label" :value="item.value" />
        </t-select>
        <t-date-picker
          class="filter-start"
          v-model="filters.start_date"
          clearable
          mode="date"
          format="YYYY-MM-DD"
          value-type="YYYY-MM-DD"
          placeholder="开始日期"
          @change="handleSearch"
        />
        <t-date-picker
          class="filter-end"
          v-model="filters.end_date"
          clearable
          mode="date"
          format="YYYY-MM-DD"
          value-type="YYYY-MM-DD"
          placeholder="结束日期"
          @change="handleSearch"
        />
        <t-button class="filter-search" theme="primary" @click="handleSearch">
          <template #icon><search-icon /></template>
          搜索
        </t-button>
        <t-button class="filter-reset" variant="outline" @click="resetFilters">
          <template #icon><refresh-icon /></template>
          重置
        </t-button>
        <t-button class="filter-refresh" variant="outline" :loading="loading" @click="loadList">
          <template #icon><refresh-icon /></template>
          刷新
        </t-button>
      </div>

      <div v-if="!isMobile" class="table-scroll">
        <t-table row-key="id" :data="recharges" :columns="columns" :loading="loading" hover table-layout="fixed">
          <template #user="{ row }">
            <div class="stack-cell">
              <strong>{{ userName(row.user) }}</strong>
              <span>{{ fieldValue(row.user?.email) }}</span>
            </div>
          </template>
          <template #payment="{ row }">
            <div class="stack-cell">
              <strong>{{ fieldValue(row.payment?.payment_no || row.payment_no) }}</strong>
              <span>{{ paymentSummary(row.payment || paymentRecord(row)) }}</span>
            </div>
          </template>
          <template #amount="{ row }">{{ formatMoney(row.amount) }}</template>
          <template #paid="{ row }">{{ formatMoney(row.paid_amount) }}</template>
          <template #status="{ row }">
            <t-tag :theme="invoiceStatusTheme(row.status)" variant="light">
              {{ row.status_label || invoiceStatusLabel(row.status) }}
            </t-tag>
          </template>
          <template #createdAt="{ row }">{{ formatDateTime(row.created_at) }}</template>
          <template #paidAt="{ row }">{{ formatDateTime(row.paid_at) }}</template>
          <template #operation="{ row }">
            <t-button size="small" variant="text" theme="primary" @click="openDetail(row)">详情</t-button>
          </template>
        </t-table>
      </div>

      <div v-else class="recharge-mobile-list">
        <t-loading :loading="loading" size="small">
          <div v-if="recharges.length" class="recharge-mobile-stack">
            <MobileRecordCard
              v-for="row in recharges"
              :key="row.id"
              :title="fieldValue(row.payment_no || row.payment?.payment_no || row.id)"
              eyebrow="充值管理"
              :subtitle="fieldValue(row.gateway_label || row.gateway || '第三方支付')"
              :description="paymentSummary(row.payment || paymentRecord(row))"
              highlight-label="充值金额"
              :highlight-value="formatMoney(row.amount)"
              :status-label="row.status_label || invoiceStatusLabel(row.status)"
              :status-theme="invoiceStatusTheme(row.status)"
              :rows="rechargeMobileRows(row)"
              :action-options="mobileActionOptions(row)"
              @action="(value) => handleMobileAction(value, row)"
            />
          </div>
          <t-empty v-else description="暂无充值记录" />
        </t-loading>
      </div>

      <div v-if="total > 0" class="pagination-row">
        <t-pagination
          :current="pagination.page"
          :page-size="pagination.page_size"
          :total="total"
          :page-size-options="[20, 50, 100]"
          show-jumper
          @change="handlePageChange"
        />
      </div>
    </t-card>

    <InvoiceDetailDrawer
      v-model:visible="detailState.visible"
      :loading="detailState.loading"
      :invoice="currentInvoice"
      :payments="invoicePayments"
      :items="invoiceItems"
      :logs="invoiceLogs"
      :status-label="currentInvoice.status_label || invoiceStatusLabel(currentInvoice.raw_status ?? currentInvoice.status)"
      :status-theme="invoiceStatusTheme(currentInvoice.raw_status ?? currentInvoice.status)"
      @close="closeDetail"
      @refresh="reloadDetail"
      @view-order="(id) => id && router.push(`/admin/finance/orders/${id}`)"
      @view-user="(id) => id && router.push(`/admin/users/${id}`)"
    />
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import { RefreshIcon, SearchIcon } from 'tdesign-icons-vue-next';
import { MessagePlugin } from 'tdesign-vue-next';
import type { PrimaryTableCol } from 'tdesign-vue-next';

import { adminApi, type InvoiceRecord, type RechargeRecord } from '@/api/admin';
import InvoiceDetailDrawer from '@/components/finance-record-detail/InvoiceDetailDrawer.vue';
import MobileRecordCard from '@/components/mobile-record-card/index.vue';
import { useMediaQuery } from '@/hooks/useMediaQuery';
import { toLabelMap, toTagTypeMap } from '@shared/statusConfig';

import './index.less';

const loading = ref(false);
const recharges = ref<RechargeRecord[]>([]);
const total = ref(0);
const router = useRouter();
const isMobile = useMediaQuery('(max-width: 768px)');
const detailState = reactive({
  visible: false,
  loading: false,
  currentId: 0,
  detail: {
    invoice: {} as InvoiceRecord,
    payments: [] as Record<string, unknown>[],
    items: [] as Record<string, unknown>[],
    logs: [] as Record<string, unknown>[],
  },
});

const filters = reactive({
  keyword: '',
  status: '',
  start_date: '',
  end_date: '',
});

const pagination = reactive({
  page: 1,
  page_size: 20,
});

const PAYMENT_STATUS_MAP = {
  0: { label: '待支付', tagType: 'warning' },
  1: { label: '已支付', tagType: 'success' },
  2: { label: '已失败', tagType: 'danger' },
  3: { label: '已退款', tagType: 'default' },
};

const statusLabelMap = toLabelMap(PAYMENT_STATUS_MAP);
const statusTypeMap = toTagTypeMap(PAYMENT_STATUS_MAP);
const paymentStatusOptions = computed(() => Object.entries(statusLabelMap).map(([value, label]) => ({ value, label })));

const columns: PrimaryTableCol<RechargeRecord>[] = [
  { colKey: 'payment_no', title: '支付单号', minWidth: 190 },
  { colKey: 'user', title: '用户', minWidth: 180 },
  { colKey: 'payment', title: '支付记录', minWidth: 220 },
  { colKey: 'amount', title: '金额', width: 120 },
  { colKey: 'paid', title: '到账', width: 120 },
  { colKey: 'status', title: '状态', width: 110 },
  { colKey: 'createdAt', title: '创建时间', width: 170 },
  { colKey: 'paidAt', title: '支付时间', width: 170 },
  { colKey: 'operation', title: '操作', width: 80, fixed: 'right' },
];

const currentInvoice = computed(() => detailState.detail.invoice || ({} as InvoiceRecord));
const invoicePayments = computed(() => detailState.detail.payments || []);
const invoiceItems = computed(() => {
  const sceneItems = currentInvoice.value.scene?.items;
  if (Array.isArray(sceneItems)) return sceneItems as Record<string, unknown>[];
  return detailState.detail.items || [];
});
const invoiceLogs = computed(() => detailState.detail.logs || []);

function buildParams() {
  const params: Record<string, unknown> = {
    page: pagination.page,
    page_size: pagination.page_size,
  };
  if (filters.keyword) params.keyword = filters.keyword;
  if (filters.status !== '') params.status = filters.status;
  if (filters.start_date || filters.end_date) params.date_range = [filters.start_date, filters.end_date].filter(Boolean);
  return params;
}

async function loadList() {
  loading.value = true;
  try {
    const response = await adminApi.financeMenu.recharges(buildParams());
    recharges.value = response.list || [];
    total.value = Number(response.total || 0);
    pagination.page = Number(response.page || pagination.page);
    pagination.page_size = Number(response.page_size || pagination.page_size);
  } catch (error) {
    MessagePlugin.error(errorMessage(error, '加载充值记录失败'));
  } finally {
    loading.value = false;
  }
}

function handleSearch() {
  pagination.page = 1;
  loadList();
}

function resetFilters() {
  Object.assign(filters, {
    keyword: '',
    status: '',
    start_date: '',
    end_date: '',
  });
  pagination.page = 1;
  loadList();
}

function handlePageChange(data: { current: number; pageSize: number }) {
  pagination.page = data.current;
  pagination.page_size = data.pageSize;
  loadList();
}

async function openDetail(row: RechargeRecord) {
  const invoiceId = row.invoice_id || row.invoice?.id;
  detailState.visible = true;
  detailState.currentId = Number(invoiceId || 0);
  detailState.detail = {
    invoice: normalizeRechargeInvoice(row),
    payments: [row.payment || paymentRecord(row)],
    items: [],
    logs: [],
  };
  if (invoiceId) await reloadDetail();
}

async function reloadDetail() {
  if (!detailState.currentId) return;
  detailState.loading = true;
  try {
    const response = await adminApi.invoices.detail(detailState.currentId);
    detailState.detail = normalizeInvoiceDetail(response, currentInvoice.value);
  } catch (error) {
    MessagePlugin.error(errorMessage(error, '加载充值详情失败'));
  } finally {
    detailState.loading = false;
  }
}

function closeDetail() {
  detailState.visible = false;
  detailState.currentId = 0;
  detailState.detail = { invoice: {}, payments: [], items: [], logs: [] };
}

function normalizeInvoiceDetail(payload: Record<string, unknown> = {}, fallback: InvoiceRecord = {}) {
  const invoice = payload.invoice && typeof payload.invoice === 'object' ? (payload.invoice as InvoiceRecord) : (payload as InvoiceRecord);
  return {
    invoice: {
      ...fallback,
      ...invoice,
      type: invoice.type || fallback.type || 'recharge',
      payment_summary: { ...(fallback.payment_summary || {}), ...(invoice.payment_summary || {}) },
      order: invoice.order || fallback.order || null,
      product: invoice.product || fallback.product || null,
      scene: invoice.scene || fallback.scene || {},
    },
    payments: Array.isArray(payload.payments) ? (payload.payments as Record<string, unknown>[]) : detailState.detail.payments,
    items: Array.isArray(payload.items) ? (payload.items as Record<string, unknown>[]) : [],
    logs: Array.isArray(payload.logs) ? (payload.logs as Record<string, unknown>[]) : [],
  };
}

function normalizeRechargeInvoice(row: RechargeRecord): InvoiceRecord {
  const invoice = toRecord(row.invoice);
  return {
    ...(invoice as InvoiceRecord),
    id: (row.invoice_id || invoice.id) as string | number | undefined,
    invoice_no: row.invoice_no || String(invoice.invoice_no || ''),
    type: String(invoice.type || 'recharge'),
    user: row.user,
    amount: row.amount,
    paid_amount: row.paid_amount,
    status: row.status,
    status_label: row.status_label,
    paid_at: row.paid_at,
    created_at: row.created_at,
  };
}

function paymentRecord(row: RechargeRecord): Record<string, unknown> {
  return {
    id: row.id,
    payment_no: row.payment_no,
    gateway: row.gateway,
    gateway_label: row.gateway_label,
    trade_no: row.trade_no,
    amount: row.amount,
    status: row.status,
    status_label: row.status_label,
    paid_at: row.paid_at,
    created_at: row.created_at,
    invoice_id: row.invoice_id,
    invoice_no: row.invoice_no,
  };
}

function handleMobileAction(value: unknown, row: RechargeRecord) {
  if (value === 'detail') openDetail(row);
}

function mobileActionOptions(row: RechargeRecord) {
  return [
    { content: '详情', value: 'detail' },
  ];
}

function rechargeMobileRows(row: RechargeRecord) {
  return [
    { label: '用户', value: userName(row.user) },
    { label: '到账', value: formatMoney(row.paid_amount), strong: true },
    { label: '账单', value: fieldValue(row.invoice_no), show: Boolean(row.invoice_no) },
    { label: '三方单', value: fieldValue(row.trade_no || row.payment?.trade_no), show: Boolean(row.trade_no || row.payment?.trade_no) },
    { label: '创建', value: formatDateTime(row.created_at) },
    { label: '支付时', value: formatDateTime(row.paid_at), show: Boolean(row.paid_at) },
  ];
}

function userName(user: unknown) {
  const record = toRecord(user);
  return fieldValue(record.nickname || record.display_name || record.email);
}

function paymentSummary(payment: unknown) {
  const record = toRecord(payment);
  return fieldValue([record.gateway, record.trade_no].filter(Boolean).join(' '));
}

function invoiceStatusLabel(status: unknown) {
  return statusLabelMap[String(status ?? '')] || fieldValue(status);
}

function invoiceStatusTheme(status: unknown) {
  const value = statusTypeMap[String(status ?? '')] || 'default';
  return value === 'info' ? 'default' : value;
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

onMounted(() => loadList());
</script>
