<template>
  <div class="finance-orders-page">
    <t-card :bordered="false">
      <div class="order-filter">
        <t-input
          class="filter-keyword"
          v-model="filters.keyword"
          clearable
          placeholder="搜索订单号 / 账单号 / 用户 / 服务"
          @enter="handleSearch"
          @clear="handleSearch"
        >
          <template #suffix-icon><search-icon /></template>
        </t-input>
        <t-select v-if="mode === 'orders'" class="filter-type" v-model="filters.type" clearable placeholder="类型" @change="handleSearch">
          <t-option v-for="item in orderTypeOptions" :key="item.value" :label="item.label" :value="item.value" />
        </t-select>
        <t-select v-if="mode === 'addons'" class="filter-type" v-model="filters.kind" clearable placeholder="配置类型" @change="handleSearch">
          <t-option label="全部" value="all" />
          <t-option label="流量包" value="traffic_package" />
        </t-select>
        <t-select class="filter-status" v-model="filters.status" clearable placeholder="状态" @change="handleSearch">
          <t-option v-for="item in orderStatusOptions" :key="item.value" :label="item.label" :value="item.value" />
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
        <t-table row-key="id" :data="orders" :columns="columns" :loading="loading" hover table-layout="fixed">
          <template #user="{ row }">
            <div class="stack-cell">
              <strong>{{ userName(row) }}</strong>
              <span>{{ fieldValue(row.user?.email) }}</span>
            </div>
          </template>
          <template #product="{ row }">
            <div class="stack-cell">
              <strong>{{ fieldValue(row.product_name) }}</strong>
              <span>{{ serviceIdLabel(row.service) }}</span>
            </div>
          </template>
          <template #type="{ row }">{{ row.type_label || orderTypeLabel(row.type) }}</template>
          <template #addon="{ row }">
            <div class="stack-cell">
              <strong>{{ row.addon_kind_label || '附加配置' }}</strong>
              <span>{{ fieldValue(row.addon_target_label || row.addon_mode) }}</span>
            </div>
          </template>
          <template #amount="{ row }">{{ formatMoney(row.amount) }}</template>
          <template #quantity="{ row }">{{ row.quantity || 1 }}</template>
          <template #status="{ row }">
            <t-tag :theme="orderStatusTheme(row.status)" variant="light">
              {{ orderStatusLabel(row.status) }}
            </t-tag>
          </template>
          <template #invoice="{ row }">
            <div class="stack-cell">
              <strong>{{ fieldValue(row.invoice?.invoice_no) }}</strong>
              <span v-if="row.invoice?.paid_at">支付：{{ formatDateTime(row.invoice.paid_at) }}</span>
            </div>
          </template>
          <template #createdAt="{ row }">{{ formatDateTime(row.created_at) }}</template>
          <template #operation="{ row }">
            <t-button size="small" variant="text" theme="primary" @click="goDetail(row)">详情</t-button>
          </template>
        </t-table>
      </div>

      <div v-else class="order-mobile-list">
        <t-loading :loading="loading" size="small">
          <div v-if="orders.length" class="order-mobile-stack">
            <MobileRecordCard
              v-for="row in orders"
              :key="row.id"
              :title="fieldValue(row.order_no || row.id)"
              :eyebrow="mobileEyebrow"
              :subtitle="row.type_label || orderTypeLabel(row.type)"
              :description="fieldValue(row.product_name)"
              highlight-label="订单金额"
              :highlight-value="formatMoney(row.amount)"
              :status-label="orderStatusLabel(row.status)"
              :status-theme="orderStatusTheme(row.status)"
              :rows="orderMobileRows(row)"
              :action-options="[{ content: '详情', value: 'detail' }]"
              @action="(value) => handleMobileAction(value, row)"
            />
          </div>
          <t-empty v-else description="暂无订单" />
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
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { RefreshIcon, SearchIcon } from 'tdesign-icons-vue-next';
import { MessagePlugin } from 'tdesign-vue-next';
import type { PrimaryTableCol } from 'tdesign-vue-next';

import { adminApi, type OrderRecord } from '@/api/admin';
import { fieldValue, formatDateTime, formatMoney } from '@/utils/format';
import { errorMessage } from '@/utils/userMessage';
import MobileRecordCard from '@/components/mobile-record-card/index.vue';
import { useMediaQuery } from '@/hooks/useMediaQuery';
import { ORDER_STATUS_MAP, toLabelMap, toSelectOptions, toTagTypeMap } from '@shared/statusConfig';

import './index.less';

type FinanceOrderMode = 'orders' | 'renewals' | 'addons';

const ORDER_TYPE_MAP: Record<string, string> = {
  new: '新购',
  normal: '新购',
  renew: '续费',
  upgrade: '附加配置',
};

const route = useRoute();
const router = useRouter();
const loading = ref(false);
const orders = ref<OrderRecord[]>([]);
const total = ref(0);
const isMobile = useMediaQuery('(max-width: 768px)');

const filters = reactive({
  keyword: '',
  type: '',
  kind: 'all',
  status: '',
  start_date: '',
  end_date: '',
});

const pagination = reactive({
  page: 1,
  page_size: 20,
});

const statusLabelMap = toLabelMap(ORDER_STATUS_MAP);
const statusTypeMap = toTagTypeMap(ORDER_STATUS_MAP);
const orderTypeOptions = Object.entries(ORDER_TYPE_MAP).map(([value, label]) => ({ value, label }));
const orderStatusOptions = computed(() => toSelectOptions(ORDER_STATUS_MAP, false));
const mode = computed<FinanceOrderMode>(() => {
  const value = route.meta.financeOrderMode;
  return value === 'renewals' || value === 'addons' ? value : 'orders';
});
const mobileEyebrow = computed(() => (mode.value === 'renewals' ? '续费订单' : mode.value === 'addons' ? '附加配置订单' : '订单管理'));

const columns = computed<PrimaryTableCol<OrderRecord>[]>(() => {
  const base: PrimaryTableCol<OrderRecord>[] = [
    { colKey: 'order_no', title: '订单号', minWidth: 170 },
    { colKey: 'user', title: '用户', minWidth: 180 },
    { colKey: 'product', title: '产品/服务', minWidth: 240 },
    { colKey: 'type', title: '类型', width: 110 },
  ];
  if (mode.value === 'addons') {
    base.push({ colKey: 'addon', title: '配置', minWidth: 140 });
  }
  base.push(
    { colKey: 'amount', title: '金额', width: 120 },
    { colKey: 'quantity', title: '数量', width: 80 },
    { colKey: 'status', title: '状态', width: 110 },
    { colKey: 'invoice', title: '关联账单', minWidth: 170 },
    { colKey: 'createdAt', title: '时间', width: 170 },
    { colKey: 'operation', title: '操作', width: 80, fixed: 'right' },
  );
  return base;
});

function buildParams() {
  const params: Record<string, unknown> = {
    page: pagination.page,
    page_size: pagination.page_size,
  };
  if (filters.keyword) params.keyword = filters.keyword;
  if (filters.status !== '') params.status = filters.status;
  if (mode.value === 'orders' && filters.type) params.type = filters.type;
  if (mode.value === 'addons') params.kind = filters.kind || 'all';
  if (filters.start_date || filters.end_date) params.date_range = [filters.start_date, filters.end_date].filter(Boolean);
  return params;
}

async function loadList() {
  loading.value = true;
  try {
    const apiCall =
      mode.value === 'renewals'
        ? adminApi.financeMenu.renewalOrders
        : mode.value === 'addons'
          ? adminApi.financeMenu.addonOrders
          : adminApi.orders.list;
    const response = await apiCall(buildParams());
    orders.value = response.list || [];
    total.value = Number(response.total || 0);
    pagination.page = Number(response.page || pagination.page);
    pagination.page_size = Number(response.page_size || pagination.page_size);
  } catch (error) {
    MessagePlugin.error(errorMessage(error, '加载订单列表失败'));
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
    type: '',
    kind: 'all',
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

function goDetail(row: OrderRecord) {
  router.push(`/admin/finance/orders/${row.id}`);
}

function handleMobileAction(value: unknown, row: OrderRecord) {
  if (value === 'detail') goDetail(row);
}

function orderMobileRows(row: OrderRecord) {
  return [
    { label: '用户', value: userName(row) },
    { label: '数量', value: String(row.quantity || 1) },
    { label: '服务', value: serviceIdLabel(row.service) },
    { label: '账单', value: fieldValue(row.invoice?.invoice_no) },
    { label: '时间', value: formatDateTime(row.created_at) },
  ];
}

watch(mode, () => resetFilters());

function userName(row: OrderRecord) {
  const user = toRecord(row.user);
  return fieldValue(user.nickname || user.display_name || user.email || (row.user_id ? `用户 #${row.user_id}` : ''));
}

function serviceIdLabel(service: unknown) {
  const record = toRecord(service);
  return fieldValue(record.service_id || record.id);
}

function orderTypeLabel(type: unknown) {
  return ORDER_TYPE_MAP[String(type || '')] || fieldValue(type);
}

function orderStatusLabel(status: unknown) {
  return statusLabelMap[String(status ?? '')] || fieldValue(status);
}

function orderStatusTheme(status: unknown) {
  const value = statusTypeMap[String(status ?? '')] || 'default';
  return value === 'info' ? 'default' : value;
}

function toRecord(value: unknown): Record<string, unknown> {
  return value && typeof value === 'object' ? (value as Record<string, unknown>) : {};
}


onMounted(() => loadList());
</script>
