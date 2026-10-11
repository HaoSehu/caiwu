<template>
  <div class="service-transfers-page">
    <t-card :bordered="false">
      <template #actions>
        <t-space>
          <t-button v-if="canManage" theme="primary" @click="openInitiateDialog">
            <template #icon><add-icon /></template>
            代发起过户
          </t-button>
        </t-space>
      </template>
      <div class="transfers-filter">
        <t-select v-model="filters.status" class="filter-status" clearable placeholder="状态" @change="handleSearch">
          <t-option v-for="item in statusOptions" :key="item.value" :label="item.label" :value="item.value" />
        </t-select>
        <t-input
          v-model="filters.keyword"
          class="filter-keyword"
          clearable
          placeholder="搜索转出/接收邮箱"
          @enter="handleSearch"
          @clear="handleSearch"
        >
          <template #suffix-icon><search-icon /></template>
        </t-input>
        <div class="filter-actions">
          <t-button theme="primary" @click="handleSearch">查询</t-button>
          <t-button theme="default" variant="base" @click="resetFilters">重置</t-button>
        </div>
      </div>

      <div class="table-scroll">
        <t-table row-key="id" :data="transfers" :columns="columns" :loading="loading" hover table-layout="fixed">
          <template #service="{ row }">
            <div class="stack-cell">
              <strong>{{ fieldValue(row.service_name) || `服务 #${row.service_id}` }}</strong>
              <span>{{ fieldValue(row.service_domain) }}</span>
            </div>
          </template>
          <template #fromUser="{ row }">
            <div class="stack-cell">
              <strong>{{ fieldValue(row.from_user_email) }}</strong>
              <span>ID {{ row.from_user_id }}</span>
            </div>
          </template>
          <template #toUser="{ row }">
            <div class="stack-cell">
              <strong>{{ fieldValue(row.to_user_email) }}</strong>
              <span>ID {{ row.to_user_id }}</span>
            </div>
          </template>
          <template #status="{ row }">
            <t-tag :theme="statusTheme(Number(row.status))" variant="light">
              {{ row.status_label || statusLabel(Number(row.status)) }}
            </t-tag>
          </template>
          <template #initiatedBy="{ row }">{{ row.initiated_by_label || initiatedByLabel(row.initiated_by) }}</template>
          <template #expiresAt="{ row }">{{ formatDateTime(row.expires_at) }}</template>
          <template #createdAt="{ row }">{{ formatDateTime(row.created_at) }}</template>
          <template #actions="{ row }">
            <t-space size="small">
              <t-button theme="primary" variant="text" @click="openDetailDrawer(row)">详情</t-button>
              <template v-if="canManage && isActive(row)">
                <t-button theme="success" variant="text" @click="confirmApprove(row)">复核通过</t-button>
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

    <t-drawer v-model:visible="detailVisible" size="560px" :footer="false" header="过户详情">
      <t-loading :loading="detailLoading" size="small">
        <template v-if="detail">
          <div class="transfer-detail">
            <div class="transfer-detail__item">
              <span>服务</span>
              <strong>{{ fieldValue(detail.service_name) || `服务 #${detail.service_id}` }}</strong>
            </div>
            <div class="transfer-detail__item" v-if="detail.service_domain">
              <span>域名</span>
              <strong>{{ detail.service_domain }}</strong>
            </div>
            <div class="transfer-detail__item">
              <span>转出方</span>
              <strong>{{ fieldValue(detail.from_user_email) }}（ID {{ detail.from_user_id }}）</strong>
            </div>
            <div class="transfer-detail__item">
              <span>接收方</span>
              <strong>{{ fieldValue(detail.to_user_email) }}（ID {{ detail.to_user_id }}）</strong>
            </div>
            <div class="transfer-detail__item">
              <span>状态</span>
              <t-tag :theme="statusTheme(Number(detail.status))" variant="light">
                {{ detail.status_label || statusLabel(Number(detail.status)) }}
              </t-tag>
            </div>
            <div class="transfer-detail__item">
              <span>发起方</span>
              <strong>{{ detail.initiated_by_label || initiatedByLabel(detail.initiated_by) }}</strong>
            </div>
            <div class="transfer-detail__item">
              <span>转出方确认时间</span>
              <strong>{{ formatDateTime(detail.from_confirmed_at) }}</strong>
            </div>
            <div class="transfer-detail__item">
              <span>接收方确认时间</span>
              <strong>{{ formatDateTime(detail.to_confirmed_at) }}</strong>
            </div>
            <div class="transfer-detail__item">
              <span>确认截止时间</span>
              <strong>{{ formatDateTime(detail.expires_at) }}</strong>
            </div>
            <div class="transfer-detail__item" v-if="detail.reject_reason">
              <span>驳回原因</span>
              <strong>{{ detail.reject_reason }}</strong>
            </div>
            <div class="transfer-detail__item" v-if="detail.trace_id">
              <span>Trace ID</span>
              <strong>{{ detail.trace_id }}</strong>
            </div>
            <div class="transfer-detail__item">
              <span>发起时间</span>
              <strong>{{ formatDateTime(detail.created_at) }}</strong>
            </div>
          </div>

          <div v-if="riskFlags.length" class="transfer-risk">
            <h4 class="transfer-risk__title">风险标记</h4>
            <t-space size="small">
              <t-tag v-for="flag in riskFlags" :key="flag" theme="danger" variant="light">{{ riskFlagLabel(flag) }}</t-tag>
            </t-space>
          </div>
        </template>
      </t-loading>
    </t-drawer>

    <t-dialog
      v-model:visible="initiateVisible"
      header="管理员代发起过户"
      width="520px"
      :confirm-btn="{ content: '确定', theme: 'primary' }"
      :confirm-loading="initiating"
      @confirm="submitInitiate"
    >
      <t-alert theme="info" message="管理员代发起视为转出方已确认，仅需接收方确认后生效；服务需为可用状态且转出方名下无未结账单。" />
      <t-form :data="initiateForm" label-align="top" class="initiate-form">
        <t-form-item label="服务 ID" name="service_id">
          <t-input-number v-model="initiateForm.service_id" :min="1" style="width: 100%" placeholder="填写服务实例 ID" />
        </t-form-item>
        <t-form-item label="接收方邮箱" name="to_user_email">
          <t-input v-model="initiateForm.to_user_email" type="email" placeholder="接收方账号邮箱" />
        </t-form-item>
      </t-form>
    </t-dialog>
  </div>
</template>
<script setup lang="ts">
import { AddIcon, SearchIcon } from 'tdesign-icons-vue-next';
import type { PrimaryTableCol } from 'tdesign-vue-next';
import { DialogPlugin, MessagePlugin } from 'tdesign-vue-next';
import { computed, ref } from 'vue';

import type { ServiceTransferRecord } from '@/api/admin/serviceTransfer';
import { SERVICE_TRANSFER_STATUS_MAP, serviceTransferApi } from '@/api/admin/serviceTransfer';
import { AdminPermissions, hasPermissionInList } from '@/constants/permissions';
import { useListPage } from '@/hooks/useListPage';
import { useUserStore } from '@/store';
import { fieldValue, formatDateTime } from '@/utils/format';
import { errorMessage } from '@/utils/userMessage';

defineOptions({
  name: 'AdminServiceTransfers',
});

interface TransferFilter {
  status: string;
  keyword: string;
}

const RISK_FLAG_LABELS: Record<string, string> = {
  high_value_service: '高价值服务',
  recent_transfer: '近期过户',
  recent_change_contact: '近期改绑联系方式',
  unpaid_invoices: '存在未结账单',
};

const statusOptions = Object.entries(SERVICE_TRANSFER_STATUS_MAP).map(([value, item]) => ({
  value,
  label: item.label,
}));

const userStore = useUserStore();
const canManage = computed(() =>
  hasPermissionInList(
    (userStore.userInfo as { permissions?: string[] }).permissions,
    AdminPermissions.SERVICE_TRANSFER_MANAGE,
  ),
);

const {
  filters,
  list: transfers,
  total,
  loading,
  pagination,
  handleSearch,
  resetFilters,
  handlePaginationChange,
} = useListPage<TransferFilter, ServiceTransferRecord>({
  defaultFilters: {
    status: '',
    keyword: '',
  },
  defaultPageSize: 20,
  syncUrl: true,
  onError: (error) => MessagePlugin.error(errorMessage(error, '加载过户记录失败')),
  fetch: (params) => serviceTransferApi.list(params),
});

const columns: PrimaryTableCol<ServiceTransferRecord>[] = [
  { colKey: 'service', title: '服务', minWidth: 190 },
  { colKey: 'fromUser', title: '转出方', minWidth: 200 },
  { colKey: 'toUser', title: '接收方', minWidth: 200 },
  { colKey: 'status', title: '状态', width: 120 },
  { colKey: 'initiatedBy', title: '发起方', width: 110 },
  { colKey: 'expiresAt', title: '过期时间', width: 170 },
  { colKey: 'createdAt', title: '发起时间', width: 170 },
  { colKey: 'actions', title: '操作', fixed: 'right', width: 220 },
];

function isActive(row: ServiceTransferRecord) {
  return [0, 1].includes(Number(row.status));
}

function statusTheme(status: number) {
  return SERVICE_TRANSFER_STATUS_MAP[status]?.theme || 'default';
}

function statusLabel(status: number) {
  return SERVICE_TRANSFER_STATUS_MAP[status]?.label || String(status);
}

function initiatedByLabel(value: number) {
  return Number(value) === 1 ? '管理员' : '转出方';
}

function riskFlagLabel(flag: string) {
  return RISK_FLAG_LABELS[flag] || flag;
}

// ─── 详情抽屉 ───────────────────────────────────────────────────────────────

const detailVisible = ref(false);
const detailLoading = ref(false);
const detail = ref<ServiceTransferRecord | null>(null);

const riskFlags = computed(() => (Array.isArray(detail.value?.risk_flags) ? detail.value!.risk_flags : []));

async function openDetailDrawer(row: ServiceTransferRecord) {
  detailVisible.value = true;
  detailLoading.value = true;
  detail.value = row;
  try {
    const response = await serviceTransferApi.detail(row.id);
    if (response.transfer) detail.value = response.transfer;
  } catch (error) {
    MessagePlugin.error(errorMessage(error, '加载过户详情失败'));
  } finally {
    detailLoading.value = false;
  }
}

// ─── 代发起 ─────────────────────────────────────────────────────────────────

const initiateVisible = ref(false);
const initiating = ref(false);
const initiateForm = ref({ service_id: null as number | null, to_user_email: '' });

function openInitiateDialog() {
  initiateForm.value = { service_id: null, to_user_email: '' };
  initiateVisible.value = true;
}

async function submitInitiate() {
  if (!initiateForm.value.service_id) {
    MessagePlugin.warning('请填写服务 ID');
    return;
  }
  if (!initiateForm.value.to_user_email.trim()) {
    MessagePlugin.warning('请填写接收方邮箱');
    return;
  }
  initiating.value = true;
  try {
    await serviceTransferApi.initiate({
      service_id: initiateForm.value.service_id,
      to_user_email: initiateForm.value.to_user_email.trim(),
    });
    MessagePlugin.success('代发起成功，等待接收方确认');
    initiateVisible.value = false;
    await handleSearch();
  } catch (error) {
    MessagePlugin.error(errorMessage(error, '代发起过户失败'));
  } finally {
    initiating.value = false;
  }
}

// ─── 复核（service_transfer.manage） ────────────────────────────────────────

function confirmApprove(row: ServiceTransferRecord) {
  const dialog = DialogPlugin.confirm({
    header: '复核通过',
    body: `确认代双方复核通过该过户并立即生效吗？服务「${fieldValue(row.service_name) || `#${row.service_id}`}」将转给 ${fieldValue(row.to_user_email)}。`,
    confirmBtn: { content: '确认生效', theme: 'primary' },
    async onConfirm() {
      try {
        await serviceTransferApi.approve(row.id);
        MessagePlugin.success('过户已复核通过并生效');
        dialog.hide();
        await handleSearch();
      } catch (error) {
        MessagePlugin.error(errorMessage(error, '复核通过失败'));
      }
    },
  });
}

function confirmReject(row: ServiceTransferRecord) {
  const dialog = DialogPlugin.confirm({
    header: '驳回过户',
    body: `确认驳回该过户请求吗？双方将收到驳回通知。`,
    confirmBtn: { content: '确认驳回', theme: 'danger' },
    async onConfirm() {
      try {
        await serviceTransferApi.reject(row.id);
        MessagePlugin.success('过户已驳回');
        dialog.hide();
        await handleSearch();
      } catch (error) {
        MessagePlugin.error(errorMessage(error, '驳回过户失败'));
      }
    },
  });
}
</script>
<style scoped>
.transfers-filter {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-bottom: 16px;
}

.filter-status {
  width: 160px;
}

.filter-keyword {
  width: 240px;
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

.stack-cell {
  display: flex;
  flex-direction: column;
}

.transfer-detail {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.transfer-detail__item {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.transfer-detail__item > span {
  font-size: 12px;
  color: var(--td-text-color-placeholder, rgba(0, 0, 0, 0.4));
}

.transfer-risk {
  margin-top: 20px;
  padding-top: 12px;
  border-top: 1px solid var(--td-component-border, #e7e7e7);
}

.transfer-risk__title {
  margin: 0 0 8px;
}

.initiate-form {
  margin-top: 12px;
}
</style>
