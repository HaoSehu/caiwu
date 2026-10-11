<template>
  <div class="sales-commission-records-page">
    <t-card :bordered="false">
      <div class="records-filter">
        <t-select
          v-model="filters.status"
          class="filter-status"
          clearable
          placeholder="状态"
          @change="handleSearch"
        >
          <t-option v-for="item in commissionStatusOptions" :key="item.value" :label="item.label" :value="item.value" />
        </t-select>
        <t-select
          v-model="filters.admin_user_id"
          class="filter-sales"
          clearable
          filterable
          :loading="staffLoading"
          placeholder="归属销售"
          @change="handleSearch"
        >
          <t-option v-for="item in staffOptions" :key="item.value" :label="item.label" :value="item.value" />
        </t-select>
        <t-date-range-picker
          v-model="dateRange"
          class="filter-date"
          clearable
          format="YYYY-MM-DD"
          value-type="YYYY-MM-DD"
          placeholder="计提日期范围"
        />
        <div class="filter-actions">
          <t-button theme="primary" @click="handleSearch">查询</t-button>
          <t-button theme="default" variant="base" @click="resetFilters">重置</t-button>
        </div>
      </div>

      <div class="table-scroll">
        <t-table row-key="id" :data="records" :columns="columns" :loading="loading" hover table-layout="fixed">
          <template #sales="{ row }">
            <div class="stack-cell">
              <strong>{{ fieldValue(row.admin_user?.display_name) }}</strong>
              <span>ID {{ row.admin_user_id }}</span>
            </div>
          </template>
          <template #customer="{ row }">
            <div class="stack-cell">
              <strong>{{ customerName(row.user) }}</strong>
              <span>{{ fieldValue(row.user?.email) }}</span>
            </div>
          </template>
          <template #invoiceNo="{ row }">{{ fieldValue(row.invoice_no) }}</template>
          <template #baseAmount="{ row }">
            <span class="t-num-strong">{{ formatMoney(row.base_amount) }}</span>
          </template>
          <template #rate="{ row }">{{ Number(row.rate || 0).toFixed(2) }}%</template>
          <template #amount="{ row }">
            <span class="t-num-strong commission-amount">{{ formatMoney(row.commission_amount) }}</span>
          </template>
          <template #status="{ row }">
            <t-tag :theme="statusTheme(Number(row.status))" variant="light">{{ row.status_label }}</t-tag>
          </template>
          <template #settlement="{ row }">
            <t-tag v-if="row.settlement_id" theme="default" variant="light">批次 #{{ row.settlement_id }}</t-tag>
            <span v-else>--</span>
          </template>
          <template #createdAt="{ row }">{{ formatDateTime(row.created_at) }}</template>
        </t-table>
      </div>

      <div v-if="total > 0" class="pagination-row">
        <t-pagination
          :current="pagination.page"
          :page-size="pagination.page_size"
          :total="total"
          :page-size-options="[20, 50, 100]"
          show-jumper
          @change="handlePaginationChange"
        />
      </div>
    </t-card>
  </div>
</template>
<script setup lang="ts">
import type { PrimaryTableCol } from 'tdesign-vue-next';
import { MessagePlugin } from 'tdesign-vue-next';
import { ref } from 'vue';

import type { SalesCommissionRecord } from '@/api/admin/salesCommission';
import { salesCommissionApi } from '@/api/admin/salesCommission';
import type { StaffRecord } from '@/api/admin-staff';
import { adminStaffApi } from '@/api/admin-staff';
import { useListPage } from '@/hooks/useListPage';
import { fieldValue, formatDateTime, formatMoney } from '@/utils/format';
import { errorMessage } from '@/utils/userMessage';

defineOptions({
  name: 'AdminSalesCommissionRecords',
});

interface RecordsFilter {
  status: string;
  admin_user_id: string;
  date_from: string;
  date_to: string;
}

const COMMISSION_STATUS_MAP: Record<number, { label: string; theme: 'warning' | 'primary' | 'success' | 'default' }> = {
  0: { label: '待结算', theme: 'warning' },
  1: { label: '已入批次', theme: 'primary' },
  2: { label: '已发放', theme: 'success' },
  3: { label: '已取消', theme: 'default' },
};

const commissionStatusOptions = Object.entries(COMMISSION_STATUS_MAP).map(([value, item]) => ({
  value,
  label: item.label,
}));

const staffOptions = ref<Array<{ label: string; value: number }>>([]);
const staffLoading = ref(false);

const {
  filters,
  list: records,
  total,
  loading,
  pagination,
  handleSearch,
  resetFilters,
  handlePaginationChange,
  dateRange,
} = useListPage<RecordsFilter, SalesCommissionRecord>({
  defaultFilters: {
    status: '',
    admin_user_id: '',
    date_from: '',
    date_to: '',
  },
  defaultPageSize: 20,
  syncUrl: true,
  // 仅启用 dateRange 双向联动（页面不渲染快捷标签组件）
  enableQuickFilter: true,
  onError: (error) => MessagePlugin.error(errorMessage(error, '加载计提记录失败')),
  fetch: (params) => {
    // useListPage 的 dateRange 双向绑定写 start_date/end_date，后端 SalesCommissionController 只认 date_from/date_to
    const { start_date: startDate, end_date: endDate, ...rest } = params as RecordsFilter & {
      start_date?: string;
      end_date?: string;
    };
    return salesCommissionApi.commissions.list({
      ...(rest as RecordsFilter),
      date_from: startDate || '',
      date_to: endDate || '',
    });
  },
});

const columns: PrimaryTableCol<SalesCommissionRecord>[] = [
  { colKey: 'sales', title: '销售', minWidth: 160 },
  { colKey: 'customer', title: '客户', minWidth: 180 },
  { colKey: 'invoiceNo', title: '账单号', minWidth: 170, ellipsis: true },
  { colKey: 'baseAmount', title: '计提基数', width: 120, align: 'right' },
  { colKey: 'rate', title: '费率', width: 90, align: 'right' },
  { colKey: 'amount', title: '提成金额', width: 120, align: 'right' },
  { colKey: 'status', title: '状态', width: 110 },
  { colKey: 'settlement', title: '结算批次', width: 110 },
  { colKey: 'createdAt', title: '计提时间', width: 170 },
];

function statusTheme(status: number) {
  return COMMISSION_STATUS_MAP[status]?.theme || 'default';
}

function customerName(user: SalesCommissionRecord['user']) {
  return fieldValue(user?.nickname || user?.display_name || user?.email);
}

async function loadStaffOptions() {
  staffLoading.value = true;
  try {
    const response = await adminStaffApi.list({ page: 1, page_size: 100 });
    staffOptions.value = (response.list || []).map((item: StaffRecord) => ({
      label: String(item.nickname || item.username || item.email || `员工 #${item.id}`),
      value: Number(item.id),
    }));
  } catch (error) {
    MessagePlugin.error(errorMessage(error, '加载销售选项失败'));
  } finally {
    staffLoading.value = false;
  }
}

loadStaffOptions();
</script>
<style scoped>
.records-filter {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-bottom: 16px;
}

.filter-status {
  width: 160px;
}

.filter-sales {
  width: 180px;
}

.filter-date {
  width: 260px;
}

.filter-actions {
  display: flex;
  gap: 8px;
}

.pagination-row {
  display: flex;
  justify-content: flex-end;
  margin-top: 16px;
}

.commission-amount {
  color: var(--td-success-color, #2ba471);
}

.stack-cell {
  display: flex;
  flex-direction: column;
}
</style>
