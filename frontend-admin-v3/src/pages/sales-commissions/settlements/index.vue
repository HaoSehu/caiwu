<template>
  <div class="sales-commission-settlements-page">
    <t-card :bordered="false">
      <template #actions>
        <t-space>
          <t-button v-if="canSettle" theme="primary" @click="openCreateDialog">
            <template #icon><add-icon /></template>
            创建结算批次
          </t-button>
        </t-space>
      </template>
      <div class="settlements-filter">
        <t-select v-model="filters.status" class="filter-status" clearable placeholder="状态" @change="handleSearch">
          <t-option v-for="item in settlementStatusOptions" :key="item.value" :label="item.label" :value="item.value" />
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
        <div class="filter-actions">
          <t-button theme="primary" @click="handleSearch">查询</t-button>
          <t-button theme="default" variant="base" @click="resetFilters">重置</t-button>
        </div>
      </div>

      <div class="table-scroll">
        <t-table row-key="id" :data="settlements" :columns="columns" :loading="loading" hover table-layout="fixed">
          <template #sales="{ row }">
            <div class="stack-cell">
              <strong>{{ fieldValue(row.admin_user?.display_name) }}</strong>
              <span>ID {{ row.admin_user_id }}</span>
            </div>
          </template>
          <template #period="{ row }">
            <span>{{ row.period_start || '--' }} ~ {{ row.period_end || '--' }}</span>
          </template>
          <template #totalAmount="{ row }">
            <span class="t-num-strong">{{ formatMoney(row.total_amount) }}</span>
          </template>
          <template #status="{ row }">
            <t-tag :theme="statusTheme(Number(row.status))" variant="light">{{ row.status_label }}</t-tag>
          </template>
          <template #settledAt="{ row }">{{ formatDateTime(row.settled_at) }}</template>
          <template #createdAt="{ row }">{{ formatDateTime(row.created_at) }}</template>
          <template #actions="{ row }">
            <t-space size="small">
              <t-button theme="primary" variant="text" @click="openDetailDrawer(row)">详情</t-button>
              <template v-if="canSettle && Number(row.status) === 0">
                <t-button theme="success" variant="text" @click="confirmApprove(row)">同意发放</t-button>
                <t-button theme="danger" variant="text" @click="confirmReject(row)">驳回</t-button>
              </template>
            </t-space>
          </template>
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

    <t-drawer v-model:visible="detailVisible" size="720px" :footer="false" header="结算批次详情">
      <t-loading :loading="detailLoading" size="small">
        <template v-if="detail">
          <div class="settlement-summary">
            <div class="settlement-summary__item">
              <span>归属销售</span>
              <strong>{{ fieldValue(detail.admin_user?.display_name) || `ID ${detail.admin_user_id}` }}</strong>
            </div>
            <div class="settlement-summary__item">
              <span>结算期间</span>
              <strong>{{ detail.period_start || '--' }} ~ {{ detail.period_end || '--' }}</strong>
            </div>
            <div class="settlement-summary__item">
              <span>登记总额</span>
              <strong class="t-num-strong">{{ formatMoney(detail.total_amount) }}</strong>
            </div>
            <div class="settlement-summary__item">
              <span>计提笔数</span>
              <strong>{{ detail.commission_count }}</strong>
            </div>
            <div class="settlement-summary__item">
              <span>状态</span>
              <t-tag :theme="statusTheme(Number(detail.status))" variant="light">{{ detail.status_label }}</t-tag>
            </div>
            <div class="settlement-summary__item">
              <span>发放时间</span>
              <strong>{{ formatDateTime(detail.settled_at) }}</strong>
            </div>
          </div>
          <p v-if="detail.remark" class="settlement-remark">备注：{{ detail.remark }}</p>

          <h4 class="settlement-commissions-title">计提明细</h4>
          <t-table
            row-key="id"
            :data="detailCommissionsPage"
            :columns="commissionColumns"
            :pagination="commissionPagination"
            table-layout="fixed"
            @page-change="handleCommissionPageChange"
          >
            <template #commissionAmount="{ row }">
              <span class="t-num-strong">{{ formatMoney(row.commission_amount) }}</span>
            </template>
            <template #commissionStatus="{ row }">
              <t-tag :theme="commissionStatusTheme(Number(row.status))" variant="light">{{ row.status_label }}</t-tag>
            </template>
          </t-table>
        </template>
      </t-loading>
    </t-drawer>

    <t-dialog
      v-model:visible="createVisible"
      header="创建结算批次"
      width="520px"
      :confirm-btn="{ content: '确定', theme: 'primary' }"
      :confirm-loading="creating"
      @confirm="submitCreate"
    >
      <t-form :data="createForm" label-align="top">
        <t-form-item label="归属销售" name="admin_user_id">
          <t-select
            v-model="createForm.admin_user_id"
            clearable
            filterable
            :loading="staffLoading"
            placeholder="选择销售"
          >
            <t-option v-for="item in staffOptions" :key="item.value" :label="item.label" :value="item.value" />
          </t-select>
        </t-form-item>
        <t-form-item label="结算期间" name="period_start">
          <t-date-range-picker
            v-model="createPeriodRange"
            clearable
            format="YYYY-MM-DD"
            value-type="YYYY-MM-DD"
            style="width: 100%"
          />
        </t-form-item>
        <t-form-item label="备注" name="remark">
          <t-input v-model="createForm.remark" :maxlength="255" placeholder="选填" />
        </t-form-item>
      </t-form>
      <p class="settlement-create-tip">
        将把该销售在期间内「待结算」的计提归入批次；期间有重叠的批次会被后端拒绝，届时会提示具体冲突批次。
      </p>
    </t-dialog>
  </div>
</template>
<script setup lang="ts">
import { AddIcon } from 'tdesign-icons-vue-next';
import type { PageInfo, PrimaryTableCol } from 'tdesign-vue-next';
import { DialogPlugin, MessagePlugin } from 'tdesign-vue-next';
import { computed, reactive, ref } from 'vue';

import type { SalesCommissionSettlementRecord } from '@/api/admin/salesCommission';
import { salesCommissionApi } from '@/api/admin/salesCommission';
import type { StaffRecord } from '@/api/admin-staff';
import { adminStaffApi } from '@/api/admin-staff';
import { AdminPermissions, hasPermissionInList } from '@/constants/permissions';
import { useListPage } from '@/hooks/useListPage';
import { useUserStore } from '@/store';
import { fieldValue, formatDateTime, formatMoney } from '@/utils/format';
import { errorMessage } from '@/utils/userMessage';

defineOptions({
  name: 'AdminSalesCommissionSettlements',
});

interface SettlementFilter {
  status: string;
  admin_user_id: string;
}

const SETTLEMENT_STATUS_MAP: Record<number, { label: string; theme: 'warning' | 'success' | 'danger' }> = {
  0: { label: '待审核', theme: 'warning' },
  1: { label: '已发放', theme: 'success' },
  2: { label: '已驳回', theme: 'danger' },
};

const settlementStatusOptions = Object.entries(SETTLEMENT_STATUS_MAP).map(([value, item]) => ({
  value,
  label: item.label,
}));

const userStore = useUserStore();
const canSettle = computed(() =>
  hasPermissionInList(
    (userStore.userInfo as { permissions?: string[] }).permissions,
    AdminPermissions.SALES_COMMISSION_SETTLE,
  ),
);

const staffOptions = ref<Array<{ label: string; value: number }>>([]);
const staffLoading = ref(false);

const {
  filters,
  list: settlements,
  total,
  loading,
  pagination,
  handleSearch,
  resetFilters,
  handlePaginationChange,
} = useListPage<SettlementFilter, SalesCommissionSettlementRecord>({
  defaultFilters: {
    status: '',
    admin_user_id: '',
  },
  defaultPageSize: 20,
  syncUrl: true,
  onError: (error) => MessagePlugin.error(errorMessage(error, '加载结算批次失败')),
  fetch: (params) => salesCommissionApi.settlements.list(params),
});

const columns: PrimaryTableCol<SalesCommissionSettlementRecord>[] = [
  { colKey: 'id', title: '批次', width: 80 },
  { colKey: 'sales', title: '归属销售', minWidth: 160 },
  { colKey: 'period', title: '结算期间', minWidth: 190 },
  { colKey: 'totalAmount', title: '登记总额', width: 130, align: 'right' },
  { colKey: 'commission_count', title: '笔数', width: 80, align: 'right' },
  { colKey: 'status', title: '状态', width: 100 },
  { colKey: 'settledAt', title: '发放时间', width: 170 },
  { colKey: 'createdAt', title: '创建时间', width: 170 },
  { colKey: 'actions', title: '操作', fixed: 'right', width: 220 },
];

const commissionColumns: PrimaryTableCol<NonNullable<SalesCommissionSettlementRecord['commissions']>[number]>[] = [
  { colKey: 'id', title: '计提 ID', width: 90 },
  { colKey: 'invoice_id', title: '账单 ID', width: 90 },
  { colKey: 'base_amount', title: '基数', align: 'right' },
  { colKey: 'rate', title: '费率', align: 'right' },
  { colKey: 'commissionAmount', title: '金额', align: 'right' },
  { colKey: 'commissionStatus', title: '状态', width: 100 },
];

// ─── 详情抽屉 ───────────────────────────────────────────────────────────────

const detailVisible = ref(false);
const detailLoading = ref(false);
const detail = ref<SalesCommissionSettlementRecord | null>(null);
const commissionPage = reactive({ current: 1, pageSize: 10 });

const detailCommissions = computed(() => detail.value?.commissions || []);
const detailCommissionsPage = computed(() => {
  const start = (commissionPage.current - 1) * commissionPage.pageSize;
  return detailCommissions.value.slice(start, start + commissionPage.pageSize);
});
const commissionPagination = computed(() => ({
  current: commissionPage.current,
  pageSize: commissionPage.pageSize,
  total: detailCommissions.value.length,
  showJumper: true,
}));

function handleCommissionPageChange(pageInfo: PageInfo) {
  commissionPage.current = pageInfo.current;
  commissionPage.pageSize = pageInfo.pageSize;
}

async function openDetailDrawer(row: SalesCommissionSettlementRecord) {
  detailVisible.value = true;
  detailLoading.value = true;
  detail.value = row;
  commissionPage.current = 1;
  try {
    detail.value = await salesCommissionApi.settlements.detail(row.id);
  } catch (error) {
    MessagePlugin.error(errorMessage(error, '加载批次详情失败'));
  } finally {
    detailLoading.value = false;
  }
}

// ─── 创建批次 ───────────────────────────────────────────────────────────────

const createVisible = ref(false);
const creating = ref(false);
const createForm = reactive({ admin_user_id: null as number | null, remark: '' });
const createPeriodRange = ref<string[]>([]);

function openCreateDialog() {
  createForm.admin_user_id = null;
  createForm.remark = '';
  createPeriodRange.value = [];
  createVisible.value = true;
}

async function submitCreate() {
  if (!createForm.admin_user_id) {
    MessagePlugin.warning('请选择归属销售');
    return;
  }
  if (!createPeriodRange.value || createPeriodRange.value.length !== 2) {
    MessagePlugin.warning('请选择结算期间');
    return;
  }
  creating.value = true;
  try {
    await salesCommissionApi.settlements.create({
      admin_user_id: createForm.admin_user_id,
      period_start: createPeriodRange.value[0],
      period_end: createPeriodRange.value[1],
      ...(createForm.remark.trim() ? { remark: createForm.remark.trim() } : {}),
    });
    MessagePlugin.success('结算批次已创建');
    createVisible.value = false;
    await handleSearch();
  } catch (error) {
    MessagePlugin.error(errorMessage(error, '创建结算批次失败'));
  } finally {
    creating.value = false;
  }
}

// ─── 审批（仅 sales_commission.settle 持有者可见） ──────────────────────────

function confirmApprove(row: SalesCommissionSettlementRecord) {
  const dialog = DialogPlugin.confirm({
    header: '同意发放',
    body: `确认将批次 #${row.id}（${formatMoney(row.total_amount)}，共 ${row.commission_count} 笔）登记为已发放吗？发放为线下登记，不动用户余额。`,
    confirmBtn: { content: '确认发放', theme: 'primary' },
    async onConfirm() {
      try {
        await salesCommissionApi.settlements.approve(row.id);
        MessagePlugin.success('批次已确认发放');
        dialog.hide();
        await handleSearch();
      } catch (error) {
        MessagePlugin.error(errorMessage(error, '同意发放失败'));
      }
    },
  });
}

function confirmReject(row: SalesCommissionSettlementRecord) {
  const dialog = DialogPlugin.confirm({
    header: '驳回批次',
    body: `确认驳回批次 #${row.id} 吗？批次内计提将退回「待结算」。`,
    confirmBtn: { content: '确认驳回', theme: 'danger' },
    async onConfirm() {
      try {
        await salesCommissionApi.settlements.reject(row.id);
        MessagePlugin.success('批次已驳回');
        dialog.hide();
        await handleSearch();
      } catch (error) {
        MessagePlugin.error(errorMessage(error, '驳回批次失败'));
      }
    },
  });
}

// ─── 公共 ───────────────────────────────────────────────────────────────────

function statusTheme(status: number) {
  return SETTLEMENT_STATUS_MAP[status]?.theme || 'default';
}

function commissionStatusTheme(status: number): 'warning' | 'primary' | 'success' | 'default' {
  const map: Record<number, 'warning' | 'primary' | 'success' | 'default'> = {
    0: 'warning',
    1: 'primary',
    2: 'success',
    3: 'default',
  };
  return map[status] || 'default';
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
.settlements-filter {
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

.settlement-summary {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px 24px;
  margin-bottom: 12px;
}

.settlement-summary__item {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.settlement-summary__item > span {
  font-size: 12px;
  color: var(--td-text-color-placeholder, rgba(0, 0, 0, 0.4));
}

.settlement-remark {
  margin: 0 0 12px;
  font-size: 13px;
  color: var(--td-text-color-secondary, rgba(0, 0, 0, 0.6));
}

.settlement-commissions-title {
  margin: 16px 0 8px;
}

.settlement-create-tip {
  margin: 0;
  font-size: 12px;
  color: var(--td-text-color-placeholder, rgba(0, 0, 0, 0.4));
}

.stack-cell {
  display: flex;
  flex-direction: column;
}
</style>
