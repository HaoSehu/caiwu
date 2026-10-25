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
          <t-empty :description="state.loading ? '加载中…' : hasActiveFilters ? '未找到匹配结果' : '暂无账单'" />
        </template>
        <template #invoiceAmount="{ row }">{{ formatMoney(row.amount) }}</template>
        <template #invoiceStatus="{ row }">
          <t-tag :theme="invoiceStatusTheme(row.status)" variant="light">{{ invoiceStatusLabel(row.status) }}</t-tag>
        </template>
        <template #invoiceType="{ row }">{{ row.type_label || invoiceTypeLabel(row.type) }}</template>
        <template #invoiceCreated="{ row }">{{ formatDateTime(row.created_at) }}</template>
        <template #invoicePaid="{ row }">{{ formatDateTime(row.paid_at) }}</template>
        <template #invoiceOperation="{ row }">
          <div class="console-row-actions">
            <t-button variant="text" theme="primary" size="small" @click="goToInvoiceDetail(row)">详情</t-button>
            <t-button
              v-if="isCancelableInvoice(row)"
              variant="text"
              theme="danger"
              size="small"
              @click="handleCancelInvoice(row)"
              >取消</t-button
            >
          </div>
        </template>
      </t-table>
    </div>
  </section>
</template>
<script setup lang="ts">
import { INVOICE_STATUS_MAP, toSelectOptions } from '@caiwu/shared/statusConfig';
import type { PageInfo, PrimaryTableCol, TableRowData } from 'tdesign-vue-next';
import { DialogPlugin } from 'tdesign-vue-next/es/dialog/plugin';
import { MessagePlugin } from 'tdesign-vue-next/es/message/plugin';
import { computed, onMounted, reactive } from 'vue';
import { useRouter } from 'vue-router';

import { adminApi } from '@/api/admin';
import type { PageParams } from '@/api/user';
import { userApi } from '@/api/user';
import { invoiceStatusLabel, invoiceStatusTheme, invoiceTypeLabel } from '@/pages/users/detail/statusDisplay';
import { formatDateTime, formatMoney } from '@/utils/format';
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

const statusOptions = toSelectOptions(INVOICE_STATUS_MAP, false);
const typeOptions = [
  { label: '新购', value: 'new' },
  { label: '续费', value: 'renew' },
  { label: '充值', value: 'recharge' },
  { label: '扣款', value: 'deduction' },
  { label: '附加配置', value: 'upgrade' },
  { label: '退款', value: 'refund' },
  { label: '推荐奖励', value: 'referral_credit' },
  { label: '手工', value: 'manual' },
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

/** 跳转财务账单详情页。 */
function goToInvoiceDetail(row: TableRowData) {
  const id = Number(row?.id ?? 0);
  if (!id) return;
  router.push(`/admin/finance/invoices/${id}`);
}

/** 仅未关联已支付订单的待支付账单可取消，与用户详情页账单 tab 保持一致。 */
function isCancelableInvoice(row: TableRowData): boolean {
  const orderId = Number(row?.order?.id || row?.order_id || 0);
  const orderStatus = Number(row?.order?.status ?? -1);
  const invoiceStatus = Number(row?.status ?? -1);
  return orderId > 0 && orderStatus === 0 && invoiceStatus === 0;
}

const columns: PrimaryTableCol<TableRowData>[] = [
  { title: '账单编号', colKey: 'invoice_no', width: 180 },
  { title: '生成时间', colKey: 'invoiceCreated', width: 180 },
  { title: '到期时间', colKey: 'due_date', width: 140 },
  { title: '支付时间', colKey: 'invoicePaid', width: 180 },
  { title: '金额', colKey: 'invoiceAmount', width: 120 },
  { title: '支付方式', colKey: 'payment_summary.gateway', width: 140 },
  { title: '状态', colKey: 'invoiceStatus', width: 120 },
  { title: '账单类型', colKey: 'invoiceType', width: 140 },
  { title: '操作', colKey: 'invoiceOperation', width: 140 },
];

const pagination = computed(() => ({
  current: state.page,
  pageSize: state.pageSize,
  total: state.total,
  showJumper: true,
}));

async function load() {
  // 请求竞态防护：连续筛选/翻页时只允许最后一次请求回写结果（docs/FRONTEND.md）
  const currentSeq = ++requestSeq;
  state.loading = true;
  try {
    // service_id 由后端按账单归属实例过滤，其余筛选与用户维度账单保持一致
    const params: PageParams = {
      ...state.filters,
      service_id: props.serviceId,
      page: state.page,
      page_size: state.pageSize,
    };
    const response = await userApi.invoices(props.userId, params);
    if (currentSeq !== requestSeq) return;
    state.list = Array.isArray(response.list) ? response.list : [];
    state.total = Number(response.total || 0);
    state.page = Number(response.page || state.page);
    state.pageSize = Number(response.page_size || state.pageSize);
  } catch (error) {
    if (currentSeq !== requestSeq) return;
    MessagePlugin.error(errorMessage(error, '加载账单列表失败'));
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

/** 取消账单需二次确认：取消后关联订单流程同步关闭。 */
function handleCancelInvoice(row: TableRowData) {
  const invoiceId = Number(row?.id || 0);
  if (!invoiceId) return;
  const dialog = DialogPlugin.confirm({
    header: '取消账单',
    body: '取消账单后将关闭关联流程，确认继续吗？',
    confirmBtn: { content: '确定取消', theme: 'danger' },
    cancelBtn: '再想想',
    theme: 'warning',
    async onConfirm() {
      try {
        await adminApi.invoices.cancel(invoiceId);
        MessagePlugin.success('账单已取消');
        dialog.hide();
        await load();
      } catch (error) {
        MessagePlugin.error(errorMessage(error, '取消账单失败'));
      }
    },
  });
}

defineExpose({ reload: load });

onMounted(load);
</script>
