<template>
  <section class="client-transfers">
    <t-card class="transfer-filter-card" :bordered="false">
      <div class="transfer-filter-bar">
        <t-tabs v-model="activeTab" class="transfer-tabs">
          <t-tab-panel value="initiated" label="我发起的" />
          <t-tab-panel value="received" label="我收到的" />
        </t-tabs>
        <t-select v-model="filters.status" clearable placeholder="全部状态" class="transfer-status-select" @change="loadData">
          <t-option
            v-for="item in TRANSFER_STATUS_OPTIONS"
            :key="item.value"
            :label="item.label"
            :value="item.value"
          />
        </t-select>
        <t-button variant="outline" aria-label="刷新过户记录" @click="loadData">
          <template #icon><refresh-icon /></template>
          刷新
        </t-button>
      </div>
    </t-card>

    <section class="transfer-list-card">
      <data-state
        :loading="loading"
        :empty="!loading && !loadError && !tabList.length"
        :error="!loading && loadError"
        :error-text="loadErrorText"
        :description="activeTab === 'initiated' ? '暂无发起的过户记录，可在「我的服务」中对运行中的服务发起过户' : '暂无收到的过户请求'"
        @retry="loadData"
      >
        <div class="transfer-table-shell">
          <t-table row-key="id" :data="pagedList" :columns="columns" :pagination="null" hover>
            <template #service="{ row }">
              <div class="transfer-service">
                <span class="transfer-service__name">{{ row.service_name || `服务 #${row.service_id}` }}</span>
                <span v-if="row.service_domain" class="transfer-service__domain">{{ row.service_domain }}</span>
                <span class="transfer-service__id">服务 ID {{ row.service_id }}</span>
              </div>
            </template>
            <template #partner="{ row }">
              <span class="transfer-partner">
                {{ resolvePartnerLabel(row) }}
                <span class="transfer-partner__id">{{ resolvePartnerId(row) }}</span>
              </span>
            </template>
            <template #status="{ row }">
              <t-tag :theme="resolveTransferTagTheme(row.status)" variant="light">{{ row.status_label || '--' }}</t-tag>
              <div v-if="row.status === TRANSFER_STATUS.REJECTED && row.reject_reason" class="transfer-reject-reason">
                {{ row.reject_reason }}
              </div>
            </template>
            <template #initiated_by="{ row }">{{ row.initiated_by_label || '--' }}</template>
            <template #expires="{ row }">{{ resolveTimeCellText(row) }}</template>
            <template #operation="{ row }">
              <t-space size="small">
                <t-button
                  v-if="canConfirm(row)"
                  size="small"
                  theme="primary"
                  variant="outline"
                  :loading="actingId === row.id"
                  @click="handleConfirm(row)"
                >
                  确认
                </t-button>
                <t-button
                  v-if="canReject(row)"
                  size="small"
                  theme="danger"
                  variant="outline"
                  :loading="actingId === row.id"
                  @click="handleReject(row)"
                >
                  拒绝
                </t-button>
                <t-button
                  v-if="canCancel(row)"
                  size="small"
                  variant="outline"
                  :loading="actingId === row.id"
                  @click="handleCancel(row)"
                >
                  撤销
                </t-button>
                <span v-if="!canConfirm(row) && !canReject(row) && !canCancel(row)" class="transfer-no-action">--</span>
              </t-space>
            </template>
          </t-table>
        </div>

        <div class="mobile-transfer-list">
          <div v-for="row in pagedList" :key="row.id" class="mobile-transfer-card">
            <span class="mobile-transfer-card__top">
              <strong>{{ row.service_name || `服务 #${row.service_id}` }}</strong>
              <t-tag :theme="resolveTransferTagTheme(row.status)" variant="light">
                {{ row.status_label || '--' }}
              </t-tag>
            </span>
            <span class="mobile-transfer-card__meta">
              <span>{{ resolvePartnerLabel(row) }}</span>
              <span>{{ row.initiated_by_label || '--' }}</span>
            </span>
            <span class="mobile-transfer-card__meta">
              <span>{{ resolveTimeCellText(row) }}</span>
              <span>发起于 {{ formatTransferTime(row.created_at) }}</span>
            </span>
            <div
              v-if="row.status === TRANSFER_STATUS.REJECTED && row.reject_reason"
              class="mobile-transfer-card__reason"
            >
              拒绝原因：{{ row.reject_reason }}
            </div>
            <div v-if="canConfirm(row) || canReject(row) || canCancel(row)" class="mobile-transfer-card__actions">
              <t-button v-if="canConfirm(row)" size="small" theme="primary" variant="outline" @click="handleConfirm(row)">
                确认
              </t-button>
              <t-button v-if="canReject(row)" size="small" theme="danger" variant="outline" @click="handleReject(row)">
                拒绝
              </t-button>
              <t-button v-if="canCancel(row)" size="small" variant="outline" @click="handleCancel(row)">撤销</t-button>
            </div>
          </div>
        </div>

        <div v-if="tabList.length > filters.page_size" class="transfer-pagination">
          <t-pagination
            v-model="filters.page"
            v-model:page-size="filters.page_size"
            :total="tabList.length"
            :page-size-options="[10, 20, 50]"
            show-total
          />
        </div>
      </data-state>
    </section>

    <p class="transfer-notice">
      过户说明：转移请求发出后，对方需在 72 小时内确认，逾期自动过期；双方确认后转移生效、服务归属变更；
      转出方名下存在该服务未结账单时转移会被拦截。
    </p>
  </section>
</template>
<script setup lang="ts">
import DataState from '@shared/user-v3/components/DataState.vue';
import { RefreshIcon } from 'tdesign-icons-vue-next';
import { DialogPlugin, MessagePlugin, type PrimaryTableCol } from 'tdesign-vue-next';
import { computed, onMounted, reactive, ref, watch } from 'vue';

import clientTransferApi, { type ServiceTransferRecord } from '@/api/serviceTransfer';
import { getUserStore } from '@/store/modules/user';
import { getErrorMessage } from '@/utils/error';

const TRANSFER_STATUS = {
  PENDING: 0,
  CONFIRMED: 1,
  COMPLETED: 2,
  CANCELLED: 3,
  REJECTED: 4,
  EXPIRED: 5,
} as const;

const TRANSFER_STATUS_OPTIONS = [
  { label: '待对方确认', value: 0 },
  { label: '双方已确认待生效', value: 1 },
  { label: '已完成', value: 2 },
  { label: '已取消', value: 3 },
  { label: '已拒绝', value: 4 },
  { label: '已过期', value: 5 },
];

type TdesignTagTheme = 'default' | 'primary' | 'success' | 'warning' | 'danger';

function resolveTransferTagTheme(status: number): TdesignTagTheme {
  if (status === TRANSFER_STATUS.PENDING) return 'warning';
  if (status === TRANSFER_STATUS.CONFIRMED) return 'primary';
  if (status === TRANSFER_STATUS.COMPLETED) return 'success';
  if (status === TRANSFER_STATUS.REJECTED) return 'danger';
  return 'default';
}

function formatTransferTime(value: string | null | undefined) {
  const raw = String(value || '').trim();
  if (!raw) return '--';
  return raw.slice(0, 16);
}

const loading = ref(false);
const loadError = ref(false);
const loadErrorText = ref('');
const actingId = ref(0);
const activeTab = ref<'initiated' | 'received'>('initiated');
const records = ref<ServiceTransferRecord[]>([]);

const filters = reactive({
  status: undefined as number | undefined,
  page: 1,
  page_size: 10,
});

function currentUserId(): number {
  return Number(getUserStore().userInfo?.id || 0);
}

// 后端列表一次返回与我相关的全部记录（from/to 双视角），前端按 tab 分流
const tabList = computed(() =>
  records.value.filter((item) =>
    activeTab.value === 'initiated'
      ? Number(item.from_user_id) === currentUserId()
      : Number(item.to_user_id) === currentUserId(),
  ),
);

const pagedList = computed(() => {
  const start = (filters.page - 1) * filters.page_size;
  return tabList.value.slice(start, start + filters.page_size);
});

const columns: PrimaryTableCol[] = [
  { colKey: 'service', title: '服务', minWidth: '16rem' },
  { colKey: 'partner', title: '对方', minWidth: '14rem' },
  { colKey: 'status', title: '状态', minWidth: '11rem' },
  { colKey: 'initiated_by', title: '发起方式', width: '8rem' },
  { colKey: 'expires', title: '过期时间', minWidth: '13rem' },
  { colKey: 'operation', title: '操作', width: '12rem', align: 'right' },
];

function resolvePartnerLabel(row: ServiceTransferRecord) {
  return activeTab.value === 'initiated' ? row.to_user_email : row.from_user_email;
}

function resolvePartnerId(row: ServiceTransferRecord) {
  return activeTab.value === 'initiated' ? `ID ${row.to_user_id}` : `ID ${row.from_user_id}`;
}

function resolveTimeCellText(row: ServiceTransferRecord) {
  if (Number(row.status) === TRANSFER_STATUS.COMPLETED) {
    // 生效时间：后端资源未输出 completed_at，以双方确认完成时间（to_confirmed_at）近似
    return `生效于 ${formatTransferTime(row.to_confirmed_at || row.from_confirmed_at)}`;
  }
  return `${formatTransferTime(row.expires_at)}`;
}

// 发起方：转出方发起→from；接收方发起→to（与后端 canCancel 口径一致）
function isInitiator(row: ServiceTransferRecord) {
  const myId = currentUserId();
  return Number(row.initiated_by) === 2 ? Number(row.to_user_id) === myId : Number(row.from_user_id) === myId;
}

function canConfirm(row: ServiceTransferRecord) {
  if (Number(row.status) !== TRANSFER_STATUS.PENDING) return false;
  const myId = currentUserId();
  return (
    (row.from_confirmed_at === null && Number(row.from_user_id) === myId) ||
    (row.to_confirmed_at === null && Number(row.to_user_id) === myId)
  );
}

function canReject(row: ServiceTransferRecord) {
  return (
    Number(row.status) === TRANSFER_STATUS.PENDING &&
    Number(row.to_user_id) === currentUserId() &&
    row.to_confirmed_at === null
  );
}

function canCancel(row: ServiceTransferRecord) {
  return Number(row.status) === TRANSFER_STATUS.PENDING && isInitiator(row);
}

async function loadData() {
  // 状态筛选/刷新回到第一页，避免旧页码在新结果集上切片越界出现"假死空表"
  filters.page = 1;
  loading.value = true;
  loadError.value = false;
  try {
    // 后端按 from/to 双视角合并返回且无视角筛选参数，这里拉全量后前端分流；
    // 频控 7 天 3 次，记录量小，单次 page_size=50 足够，超出时翻页补拉（上限 10 页）。
    const all: ServiceTransferRecord[] = [];
    const pageSize = 50;
    let page = 1;
    let total = 0;
    do {
      const res = await clientTransferApi.requests({
        page,
        page_size: pageSize,
        status: filters.status === undefined ? undefined : filters.status,
      });
      const list = Array.isArray(res.data?.list) ? res.data.list : [];
      all.push(...list);
      total = Number(res.data?.total || 0);
      page += 1;
    } while (all.length < total && page <= 10);
    records.value = all;
  } catch (error: unknown) {
    loadError.value = true;
    loadErrorText.value = getErrorMessage(error, '过户记录加载失败');
    records.value = [];
    MessagePlugin.error(loadErrorText.value);
  } finally {
    loading.value = false;
  }
}

function withActing(row: ServiceTransferRecord, action: () => Promise<unknown>) {
  actingId.value = row.id;
  return action().finally(() => {
    actingId.value = 0;
  });
}

function handleConfirm(row: ServiceTransferRecord) {
  const dialog = DialogPlugin.confirm({
    header: '确认接收过户',
    body: `确认接收服务「${row.service_name || `#${row.service_id}`}」吗？双方确认后服务归属将立即变更为您的账号。`,
    confirmBtn: '确认接收',
    cancelBtn: '取消',
    onConfirm: () => {
      dialog.setConfirmLoading(true);
      withActing(row, async () => clientTransferApi.confirm(row.id))
        .then(() => {
          MessagePlugin.success('已确认');
          dialog.hide();
          void loadData();
        })
        .catch((error: unknown) => {
          if (!(error as { __handled?: boolean })?.__handled) {
            MessagePlugin.error(getErrorMessage(error, '确认失败'));
          }
        })
        .finally(() => {
          dialog.setConfirmLoading(false);
        });
    },
  });
}

function handleReject(row: ServiceTransferRecord) {
  const dialog = DialogPlugin.confirm({
    header: '拒绝过户',
    body: '拒绝后本次过户请求将作废，确定拒绝吗？',
    confirmBtn: '确定拒绝',
    cancelBtn: '取消',
    theme: 'warning',
    onConfirm: () => {
      dialog.setConfirmLoading(true);
      withActing(row, async () => clientTransferApi.reject(row.id))
        .then(() => {
          MessagePlugin.success('已拒绝');
          dialog.hide();
          void loadData();
        })
        .catch((error: unknown) => {
          if (!(error as { __handled?: boolean })?.__handled) {
            MessagePlugin.error(getErrorMessage(error, '拒绝失败'));
          }
        })
        .finally(() => {
          dialog.setConfirmLoading(false);
        });
    },
  });
}

function handleCancel(row: ServiceTransferRecord) {
  const dialog = DialogPlugin.confirm({
    header: '撤销过户请求',
    body: '撤销后本次过户请求将作废，确定撤销吗？',
    confirmBtn: '确定撤销',
    cancelBtn: '取消',
    theme: 'warning',
    onConfirm: () => {
      dialog.setConfirmLoading(true);
      withActing(row, async () => clientTransferApi.cancel(row.id))
        .then(() => {
          MessagePlugin.success('已撤销');
          dialog.hide();
          void loadData();
        })
        .catch((error: unknown) => {
          if (!(error as { __handled?: boolean })?.__handled) {
            MessagePlugin.error(getErrorMessage(error, '撤销失败'));
          }
        })
        .finally(() => {
          dialog.setConfirmLoading(false);
        });
    },
  });
}

watch(activeTab, () => {
  filters.page = 1;
});

onMounted(() => {
  void loadData();
});
</script>
<style scoped lang="less">
.client-transfers {
  display: flex;
  flex-direction: column;
  gap: var(--td-comp-margin-m);
}

.transfer-filter-card {
  background: var(--td-bg-color-container);
  border: thin solid var(--td-component-stroke);
  border-radius: var(--td-radius-medium);
  box-shadow: var(--td-shadow-1);
}

.transfer-filter-bar {
  display: flex;
  flex-wrap: wrap;
  gap: var(--td-comp-margin-s);
  align-items: center;
  justify-content: space-between;
}

.transfer-tabs {
  width: fit-content;
}

.transfer-status-select {
  width: 10rem;
}

.transfer-table-shell {
  overflow-x: auto;
}

.transfer-service {
  display: flex;
  flex-direction: column;
  gap: 0.125rem;
}

.transfer-service__name {
  color: var(--td-text-color-primary);
  font: var(--td-font-body-medium);
  font-weight: 600;
}

.transfer-service__domain {
  color: var(--td-text-color-secondary);
  font: var(--td-font-body-small);
}

.transfer-service__id {
  color: var(--td-text-color-placeholder);
  font: var(--td-font-body-small);
}

.transfer-partner {
  display: inline-flex;
  gap: var(--td-comp-margin-xs);
  align-items: baseline;
  color: var(--td-text-color-primary);
  font: var(--td-font-body-medium);
  overflow-wrap: anywhere;
}

.transfer-partner__id {
  color: var(--td-text-color-placeholder);
  font: var(--td-font-body-small);
}

.transfer-reject-reason {
  margin-top: 0.25rem;
  color: var(--td-text-color-secondary);
  font: var(--td-font-body-small);
  overflow-wrap: anywhere;
}

.transfer-no-action {
  color: var(--td-text-color-placeholder);
}

.transfer-pagination {
  display: flex;
  justify-content: flex-end;
  margin-top: var(--td-comp-margin-m);
}

.transfer-notice {
  margin: 0;
  color: var(--td-text-color-secondary);
  font: var(--td-font-body-small);
}

.mobile-transfer-list {
  display: none;
}

.mobile-transfer-card {
  display: flex;
  flex-direction: column;
  gap: var(--td-comp-margin-s);
  padding: 0.875rem 0.875rem 0.75rem;
  background: var(--td-bg-color-container);
  border: 0.0625rem solid var(--td-component-stroke);
  border-radius: var(--td-radius-medium);
  box-shadow: var(--td-shadow-1);
}

.mobile-transfer-card__top {
  display: flex;
  gap: var(--td-comp-margin-s);
  align-items: center;
  justify-content: space-between;
  padding-bottom: 0.625rem;
  border-bottom: 0.0625rem solid var(--td-component-stroke);

  strong {
    color: var(--td-text-color-primary);
    font: var(--td-font-body-large);
    font-weight: 600;
  }
}

.mobile-transfer-card__meta {
  display: flex;
  gap: var(--td-comp-margin-s);
  justify-content: space-between;
  color: var(--td-text-color-secondary);
  font: var(--td-font-body-small);
}

.mobile-transfer-card__reason {
  color: var(--td-text-color-secondary);
  font: var(--td-font-body-small);
  overflow-wrap: anywhere;
}

.mobile-transfer-card__actions {
  display: flex;
  gap: var(--td-comp-margin-s);
  justify-content: flex-end;
}

@media (max-width: @screen-sm-rem) {
  .client-transfers {
    padding: var(--td-comp-paddingTB-l) var(--td-comp-paddingLR-l);
  }

  .transfer-table-shell {
    display: none;
  }

  .mobile-transfer-list {
    display: grid;
    gap: 0.75rem;
  }

  .transfer-status-select {
    width: 100%;
  }

  .transfer-pagination {
    justify-content: flex-start;
    overflow-x: auto;
  }
}
</style>
