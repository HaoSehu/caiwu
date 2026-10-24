<template>
  <section class="user-detail-section">
    <div class="detail-toolbar compact">
      <t-select v-model="state.filters.direction" clearable placeholder="方向" @change="search">
        <t-option label="充值入账" value="in" />
        <t-option label="退款冲抵" value="out" />
      </t-select>
      <t-button v-if="canRecharge" theme="primary" @click="emit('recharge')">手动充值</t-button>
    </div>
    <div class="table-scroll">
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
          <t-empty :description="state.loading ? '加载中…' : hasActiveFilters ? '未找到匹配结果' : '暂无数据'" />
        </template>
        <template #recordTime="{ row }">{{ formatDateTime(row.created_at) }}</template>
        <template #recordAmount="{ row }">
          <span :class="row.direction === 'out' ? 'amount-down' : 'amount-up'">{{ signedAmount(row) }}</span>
        </template>
        <template #recordEntryType="{ row }">
          <t-tag :theme="row.direction === 'out' ? 'danger' : 'success'" variant="light">{{
            entryTypeLabel(row.entry_type)
          }}</t-tag>
        </template>
        <template #recordInvoice="{ row }">{{ row.invoice_no || '-' }}</template>
      </t-table>
    </div>
  </section>
</template>
<script setup lang="ts">
import type { PageInfo, PrimaryTableCol, TableRowData } from 'tdesign-vue-next';
import { MessagePlugin } from 'tdesign-vue-next/es/message/plugin';
import { computed, onMounted, reactive } from 'vue';

import type { PageParams, UserRechargeRecord } from '@/api/user';
import { userApi } from '@/api/user';
import { AdminPermissions } from '@/constants/permissions';
import { formatDateTime, formatMoney } from '@/utils/format';
import { hasAdminPermission } from '@/utils/permission';
import { errorMessage } from '@/utils/userMessage';

const props = defineProps<{ userId: number | string }>();
const emit = defineEmits<{ (event: 'recharge'): void }>();

const canRecharge = computed(() => hasAdminPermission(AdminPermissions.USER_RECHARGE));

const ENTRY_TYPE_LABELS: Record<string, string> = {
  third_party_payment: '在线支付',
  manual_recharge: '手工充值',
  account_recharge: '余额充值',
  refund_offset: '退款冲抵',
};

interface TabPageState {
  loading: boolean;
  list: UserRechargeRecord[];
  total: number;
  page: number;
  pageSize: number;
  filters: Record<string, string | number>;
}

const state = reactive<TabPageState>({
  loading: false,
  list: [],
  total: 0,
  page: 1,
  pageSize: 10,
  filters: { direction: '' },
});

// 空态文案区分「未筛选但无数据」与「筛选后无匹配」（docs/DESIGN.md 术语表）
const hasActiveFilters = computed(() => Boolean(state.filters.direction));

/** 列表请求序号，用于丢弃过期响应 */
let requestSeq = 0;

const columns: PrimaryTableCol<TableRowData>[] = [
  { title: '时间', colKey: 'recordTime', width: 180 },
  { title: '充值单号', colKey: 'record_no', width: 220 },
  { title: '方式', colKey: 'recordEntryType', width: 120 },
  { title: '金额', colKey: 'recordAmount', width: 140 },
  { title: '关联账单', colKey: 'recordInvoice', width: 200 },
  { title: '操作人', colKey: 'operator_name', width: 140 },
  { title: '备注', colKey: 'remark', ellipsis: true },
];

const pagination = computed(() => ({
  current: state.page,
  pageSize: state.pageSize,
  total: state.total,
  showJumper: true,
}));

function entryTypeLabel(value: unknown) {
  return ENTRY_TYPE_LABELS[String(value)] || String(value || '-');
}

// 后端退款冲抵记录的 amount 本身已带负号（FinanceDocumentService 的 refund_offset），
// 因此这里只能由 formatMoney 统一排负号，不能再按 direction 手工拼一次前缀，
// 否则会渲染成「--¥100.00」（docs/DESIGN.md §6 金额符号口径）。
function signedAmount(row: UserRechargeRecord) {
  return formatMoney(row.amount);
}

async function load() {
  // 请求竞态防护：连续筛选/翻页时只允许最后一次请求回写结果（docs/FRONTEND.md）
  const currentSeq = ++requestSeq;
  state.loading = true;
  try {
    const params: PageParams = { ...state.filters, page: state.page, page_size: state.pageSize };
    const response = await userApi.rechargeRecords(props.userId, params);
    if (currentSeq !== requestSeq) return;
    state.list = Array.isArray(response.list) ? response.list : [];
    state.total = Number(response.total || 0);
    state.page = Number(response.page || state.page);
    state.pageSize = Number(response.page_size || state.pageSize);
  } catch (error) {
    if (currentSeq !== requestSeq) return;
    MessagePlugin.error(errorMessage(error, '加载充值记录失败'));
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
