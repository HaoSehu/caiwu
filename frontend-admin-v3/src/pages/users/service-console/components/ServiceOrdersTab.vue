<template>
  <section class="console-tab-section">
    <div class="console-toolbar">
      <t-select v-model="state.filters.status" clearable placeholder="状态" @change="search">
        <t-option v-for="option in statusOptions" :key="option.value" :label="option.label" :value="option.value" />
      </t-select>
      <t-select v-model="state.filters.type" clearable placeholder="类型" @change="search">
        <t-option v-for="option in typeOptions" :key="option.value" :label="option.label" :value="option.value" />
      </t-select>
    </div>
    <div class="console-table-scroll">
      <t-table
        row-key="id"
        :data="state.list"
        :columns="columns"
        :loading="state.loading"
        :pagination="pagination"
        table-layout="fixed"
        @page-change="handlePageChange"
      >
        <template #empty>
          <t-empty :description="state.loading ? '加载中…' : hasActiveFilters ? '未找到匹配结果' : '暂无订单'" />
        </template>
        <template #orderProduct="{ row }">
          <strong>{{ fieldValue(row.product_full_path || row.product_name) }}</strong>
        </template>
        <template #orderType="{ row }">{{ typeLabel(row.type) }}</template>
        <template #orderStatus="{ row }">
          <t-tag :theme="statusTheme(row.status)" variant="light">{{ statusLabel(row.status) }}</t-tag>
        </template>
        <template #orderAmount="{ row }">{{ formatMoney(row.amount) }}</template>
        <template #orderPaid="{ row }">{{ formatDateTime(row.paid_at) }}</template>
        <template #orderCreated="{ row }">{{ formatDateTime(row.created_at) }}</template>
        <template #orderOperation="{ row }">
          <div class="console-row-actions">
            <t-button variant="text" theme="primary" size="small" @click="goToOrderDetail(row)">详情</t-button>
          </div>
        </template>
      </t-table>
    </div>
  </section>
</template>
<script setup lang="ts">
import { ORDER_STATUS_MAP, toLabelMap, toSelectOptions, toTagTypeMap } from '@caiwu/shared/statusConfig';
import type { PageInfo, PrimaryTableCol, TableRowData } from 'tdesign-vue-next';
import { MessagePlugin } from 'tdesign-vue-next/es/message/plugin';
import { computed, onMounted, reactive } from 'vue';
import { useRouter } from 'vue-router';

import type { PageParams } from '@/api/user';
import { userApi } from '@/api/user';
import { fieldValue, formatDateTime, formatMoney } from '@/utils/format';
import { errorMessage } from '@/utils/userMessage';

type Row = Record<string, any>;

interface TabPageState {
  loading: boolean;
  list: Row[];
  total: number;
  page: number;
  pageSize: number;
  filters: Record<string, string | number>;
}

const props = defineProps<{ userId: number | string; serviceId: number | string }>();

const statusOptions = toSelectOptions(ORDER_STATUS_MAP, false);
const statusLabelMap = toLabelMap(ORDER_STATUS_MAP);
const statusTypeMap = toTagTypeMap(ORDER_STATUS_MAP);
const typeOptions = [
  { label: '新购', value: 'new' },
  { label: '续费', value: 'renew' },
  { label: '附加配置', value: 'upgrade' },
];

const state = reactive<TabPageState>({
  loading: false,
  list: [],
  total: 0,
  page: 1,
  pageSize: 10,
  filters: { status: '', type: '' },
});

// 空态文案区分「未筛选但无数据」与「筛选后无匹配」（docs/DESIGN.md 术语表）
const hasActiveFilters = computed(() => Boolean(state.filters.status) || Boolean(state.filters.type));

const router = useRouter();

/** 列表请求序号，用于丢弃过期响应 */
let requestSeq = 0;

/** 跳转财务订单详情页（订单↔账单联动详情，支持退款标记等操作入口）。 */
function goToOrderDetail(row: TableRowData) {
  const id = Number(row?.id ?? 0);
  if (!id) return;
  router.push(`/admin/finance/orders/${id}`);
}

const columns: PrimaryTableCol<TableRowData>[] = [
  { title: '订单号', colKey: 'order_no', width: 200 },
  { title: '产品', colKey: 'orderProduct', minWidth: 240 },
  { title: '类型', colKey: 'orderType', width: 110 },
  { title: '金额', colKey: 'orderAmount', width: 120 },
  { title: '状态', colKey: 'orderStatus', width: 110 },
  { title: '支付时间', colKey: 'orderPaid', width: 180 },
  { title: '创建时间', colKey: 'orderCreated', width: 180 },
  { title: '操作', colKey: 'orderOperation', width: 100 },
];

const pagination = computed(() => ({
  current: state.page,
  pageSize: state.pageSize,
  total: state.total,
  showJumper: true,
}));

function typeLabel(value: unknown) {
  return (typeOptions.find((option) => option.value === String(value)) || { label: String(value ?? '-') }).label;
}
function statusLabel(value: unknown) {
  return String(statusLabelMap[Number(value)] ?? value ?? '-');
}
function statusTheme(value: unknown): 'default' | 'success' | 'warning' | 'danger' {
  return (statusTypeMap[Number(value)] as 'default' | 'success' | 'warning' | 'danger') || 'default';
}

async function load() {
  // 请求竞态防护：连续筛选/翻页时只允许最后一次请求回写结果（docs/FRONTEND.md）
  const currentSeq = ++requestSeq;
  state.loading = true;
  try {
    // service_id 由后端按订单归属实例过滤，其余筛选与用户维度订单保持一致
    const params: PageParams = {
      ...state.filters,
      service_id: props.serviceId,
      page: state.page,
      page_size: state.pageSize,
    };
    const response = await userApi.orders(props.userId, params);
    if (currentSeq !== requestSeq) return;
    state.list = Array.isArray(response.list) ? response.list : [];
    state.total = Number(response.total || 0);
    state.page = Number(response.page || state.page);
    state.pageSize = Number(response.page_size || state.pageSize);
  } catch (error) {
    if (currentSeq !== requestSeq) return;
    MessagePlugin.error(errorMessage(error, '加载订单列表失败'));
  } finally {
    if (currentSeq === requestSeq) state.loading = false;
  }
}

function search() {
  state.page = 1;
  load();
}

function handlePageChange(pageInfo: PageInfo) {
  state.page = pageInfo.current;
  state.pageSize = pageInfo.pageSize;
  load();
}

defineExpose({ reload: load });

onMounted(load);
</script>
