<template>
  <div class="finance-invoices-page">
    <t-card :bordered="false">
      <quick-filter-tags v-model="filters.quickFilter" @change="applyQuickFilter" />
      <div class="invoice-filter">
        <t-input
          v-model="filters.keyword"
          class="filter-keyword"
          clearable
          placeholder="搜索账单号 / 订单号 / 用户"
          @enter="handleSearch"
          @clear="handleSearch"
        >
          <template #suffix-icon><search-icon /></template>
        </t-input>
        <t-select v-model="filters.type" class="filter-type" clearable placeholder="类型" @change="handleSearch">
          <t-option v-for="item in invoiceTypeOptions" :key="item.value" :label="item.label" :value="item.value" />
        </t-select>
        <t-select v-model="filters.status" class="filter-status" clearable placeholder="状态" @change="handleSearch">
          <t-option v-for="item in invoiceStatusOptions" :key="item.value" :label="item.label" :value="item.value" />
        </t-select>
        <t-date-range-picker
          v-model="dateRange"
          class="filter-date"
          clearable
          format="YYYY-MM-DD"
          value-type="YYYY-MM-DD"
          placeholder="选择日期范围"
        />
        <div class="filter-actions">
          <t-button theme="primary" @click="handleSearch">查询</t-button>
          <t-button theme="default" variant="base" @click="resetFilters">重置</t-button>
        </div>
      </div>

      <div v-if="!isMobile" class="table-scroll">
        <t-table
          row-key="id"
          :data="invoices"
          :columns="columns"
          :loading="loading"
          hover
          table-layout="auto"
          aria-label="账单列表"
        >
          <template #empty>
            <t-empty :description="loading ? '加载中…' : hasActiveFilters ? '未找到匹配结果' : '暂无账单'" />
          </template>
          <template #user="{ row }">
            <div class="stack-cell">
              <strong>{{ userName(row.user) }}</strong>
              <span>{{ fieldValue(row.user?.email) }}</span>
            </div>
          </template>
          <template #item="{ row }">
            <div class="stack-cell">
              <strong>{{ invoiceTitle(row) }}</strong>
              <span v-if="row.order?.id">
                订单：<t-link theme="primary" hover="color" @click.stop="goToOrder(row.order.id)">{{
                  row.order.order_no || row.order.id
                }}</t-link>
              </span>
              <span v-else>{{ fieldValue(row.summary?.highlight || row.order_no) }}</span>
            </div>
          </template>
          <template #type="{ row }">{{ row.type_label || invoiceTypeLabel(row.type) }}</template>
          <!-- 优惠金额展示规范 1.3 / 2.3：账单列表唯一金额列「实付金额」，取 paid_amount，不展开优惠明细 -->
          <template #paid_amount="{ row }"
            ><span class="t-num-strong">{{ formatMoney(row.paid_amount) }}</span></template
          >
          <template #billing_cycle="{ row }">{{ billingCycleLabel(row.billing_cycle) }}</template>
          <template #status="{ row }">
            <status-tag :status-map="INVOICE_STATUS_MAP" :status="row.status" />
          </template>
          <template #createdAt="{ row }">{{ formatDateTime(row.created_at) }}</template>
          <template #finishedAt="{ row }">{{ formatFinishTime(row.finished_at) }}</template>
          <template #operation="{ row }">
            <t-space v-if="!isMobile" size="small">
              <t-button size="small" variant="text" theme="primary" @click="goDetail(row)">详情</t-button>
              <t-button
                v-if="canCancel(row)"
                size="small"
                variant="text"
                theme="danger"
                :loading="cancelLoadingId === row.id"
                @click="confirmCancel(row)"
              >
                取消
              </t-button>
            </t-space>
            <t-dropdown v-else :options="mobileActionOptions(row)" @click="handleMobileActionHandler(row)">
              <t-button size="small" variant="text">更多</t-button>
            </t-dropdown>
          </template>
        </t-table>
      </div>

      <div v-else class="record-mobile-list">
        <t-loading :loading="loading" size="small">
          <div v-if="invoices.length" class="record-mobile-stack">
            <mobile-record-card
              v-for="row in invoices"
              :key="row.id"
              :title="fieldValue(row.invoice_no || row.id)"
              eyebrow="账单管理"
              :subtitle="row.type_label || invoiceTypeLabel(row.type)"
              :description="invoiceTitle(row)"
              highlight-label="实付金额"
              :highlight-value="formatMoney(row.paid_amount)"
              :status-map="INVOICE_STATUS_MAP"
              :status="row.status"
              :rows="invoiceMobileRows(row)"
              :action-options="mobileActionOptions(row)"
              @action="(value) => handleMobileAction(value, row)"
            />
          </div>
          <t-empty v-else :description="hasActiveFilters ? '未找到匹配结果' : '暂无账单'" />
        </t-loading>
      </div>

      <div v-if="total > 0" class="pagination-row">
        <t-pagination
          :current="pagination.page"
          :page-size="pagination.page_size"
          :total="total"
          :page-size-options="pagination.pageSizeOptions"
          show-jumper
          @change="handlePaginationChange"
        />
      </div>
    </t-card>
  </div>
</template>
<script setup lang="ts">
import './index.less';

import { INVOICE_STATUS_MAP, INVOICE_TYPE_MAP, toLabelMap } from '@caiwu/shared/statusConfig';
import StatusTag from '@caiwu/shared/user-v3/components/StatusTag.vue';
import { SearchIcon } from 'tdesign-icons-vue-next';
import type { DropdownOption, PrimaryTableCol } from 'tdesign-vue-next';
import { DialogPlugin } from 'tdesign-vue-next/es/dialog/plugin';
import { MessagePlugin } from 'tdesign-vue-next/es/message/plugin';
import { ref } from 'vue';
import { useRouter } from 'vue-router';

import type { InvoiceRecord } from '@/api/admin';
import { adminApi } from '@/api/admin';
import MobileRecordCard from '@/components/mobile-record-card/index.vue';
import QuickFilterTags from '@/components/quick-filter-tags/index.vue';
import { AdminPermissions, hasPermissionInList } from '@/constants/permissions';
import { useListPage } from '@/hooks/useListPage';
import { useMediaQuery } from '@/hooks/useMediaQuery';
import { useUserStore } from '@/store';
import {
  fieldValue,
  formatBillingCycle,
  formatDateTime,
  formatFinishTime,
  formatMoney,
  toRecord,
} from '@/utils/format';
import { errorMessage } from '@/utils/userMessage';

defineOptions({
  name: 'AdminFinanceInvoices',
});

interface InvoiceFilter {
  keyword: string;
  type: string;
  status: string;
  start_date: string;
  end_date: string;
  quickFilter: string;
}

const cancelLoadingId = ref<number | string | null>(null);
const userStore = useUserStore();
const router = useRouter();
const isMobile = useMediaQuery('(max-width: 767.98px)');

const {
  filters,
  list: invoices,
  total,
  loading,
  pagination,
  loadList,
  handleSearch,
  resetFilters,
  handlePaginationChange,
  applyQuickFilter,
  dateRange,
  hasActiveFilters,
} = useListPage<InvoiceFilter, InvoiceRecord>({
  defaultFilters: {
    keyword: '',
    type: '',
    status: '',
    start_date: '',
    end_date: '',
    quickFilter: '',
  },
  defaultPageSize: 20,
  syncUrl: true,
  enableQuickFilter: true,
  onError: (error) => MessagePlugin.error(errorMessage(error, '加载账单列表失败')),
  fetch: (params) => adminApi.invoices.list(params),
});

const statusLabelMap = toLabelMap(INVOICE_STATUS_MAP);

const invoiceTypeOptions = Object.entries(INVOICE_TYPE_MAP)
  .filter(([value]) => value !== 'normal') // 历史兼容别名，不单独列出
  .map(([value, label]) => ({ value, label }));
const invoiceStatusOptions = Object.entries(statusLabelMap).map(([value, label]) => ({ value, label }));

// 优惠金额展示规范 1.3：账单列表只展示实付金额，金额列唯一且右对齐
const columns: PrimaryTableCol<InvoiceRecord>[] = [
  { colKey: 'invoice_no', title: '账单号', minWidth: 170 },
  { colKey: 'user', title: '用户', minWidth: 180 },
  { colKey: 'item', title: '账单项目', minWidth: 240 },
  { colKey: 'type', title: '类型', width: 120 },
  { colKey: 'billing_cycle', title: '计费周期', width: 110 },
  { colKey: 'createdAt', title: '创建时间', width: 170 },
  { colKey: 'paid_amount', title: '实付金额', width: 130, align: 'right' },
  { colKey: 'status', title: '状态', width: 110 },
  { colKey: 'finishedAt', title: '结束时间', width: 170 },
  { colKey: 'operation', title: '操作', width: 130, fixed: 'right' },
];

function userPermissions() {
  const info = userStore.userInfo as { permissions?: string[] };
  return Array.isArray(info.permissions) ? info.permissions : [];
}

function hasPermission(permission: string) {
  const permissions = userPermissions();
  return hasPermissionInList(permissions, permission);
}

function canCancel(row: InvoiceRecord) {
  const status = Number(row.status ?? -1);
  return hasPermission(AdminPermissions.INVOICE_MANAGE) && [0].includes(status);
}

// ─── 详情：跳转财务账单详情页（与订单列表一致，不在列表页内嵌抽屉） ───
function goDetail(row: InvoiceRecord) {
  const id = Number(row.id ?? 0);
  if (!id) return;
  router.push(`/admin/finance/invoices/${id}`);
}

function confirmCancel(row: InvoiceRecord) {
  const dialog = DialogPlugin.confirm({
    header: '取消账单',
    body: `确认取消账单「${row.invoice_no || row.id}」吗？`,
    theme: 'warning',
    confirmBtn: { content: '确定', theme: 'warning' },
    cancelBtn: '取消',
    async onConfirm() {
      await cancelInvoice(row);
      dialog.hide();
    },
  });
}

async function cancelInvoice(row: InvoiceRecord) {
  if (!row.id) return;
  cancelLoadingId.value = row.id;
  try {
    await adminApi.invoices.cancel(row.id);
    MessagePlugin.success('账单已取消');
    await loadList();
  } catch (error) {
    MessagePlugin.error(errorMessage(error, '取消账单失败'));
  } finally {
    cancelLoadingId.value = null;
  }
}

function goToOrder(orderId: unknown) {
  if (orderId) {
    router.push(`/admin/finance/orders/${orderId}`);
  }
}

function mobileActionOptions(row: InvoiceRecord) {
  const options = [{ content: '详情', value: 'detail' }];
  if (row.order?.id) {
    options.push({ content: '查看订单', value: 'order' });
  }
  if (canCancel(row)) {
    options.push({ content: '取消', value: 'cancel' });
  }
  return options;
}

function handleMobileActionHandler(row: InvoiceRecord) {
  return (data: DropdownOption) => handleMobileAction(data.value, row);
}

function handleMobileAction(action: unknown, row: InvoiceRecord) {
  if (action === 'detail') goDetail(row);
  if (action === 'order' && row.order?.id) goToOrder(row.order.id);
  if (action === 'cancel' && canCancel(row)) confirmCancel(row);
}

function invoiceMobileRows(row: InvoiceRecord) {
  // 实付金额已在卡片 highlight 位展示，行视图不再重复，避免出现第二个金额口径
  return [
    { label: '用户', value: userName(row.user) },
    { label: '计费周期', value: billingCycleLabel(row.billing_cycle) },
    { label: '订单', value: fieldValue(row.order?.order_no || row.order_no) },
    { label: '创建时间', value: formatDateTime(row.created_at) },
    { label: '结束时间', value: formatFinishTime(row.finished_at) },
  ];
}

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

// 计费周期：英文键（monthly / quarterly / semiannually / annually …）翻成 一个月 / 三个月 / 半年 / 一年 / 按量
function billingCycleLabel(cycle: unknown) {
  return formatBillingCycle(cycle);
}

function userName(user: unknown) {
  const record = toRecord(user);
  return fieldValue(record.nickname || record.display_name || record.email);
}
</script>
