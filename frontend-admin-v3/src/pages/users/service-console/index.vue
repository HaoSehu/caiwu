<template>
  <div class="service-console-page">
    <div class="console-toolbar console-toolbar--page">
      <t-button variant="text" class="console-toolbar__back" @click="goBack">
        <template #icon><chevron-left-icon /></template>
        返回用户详情
      </t-button>
      <div class="console-toolbar__actions">
        <t-button
          variant="outline"
          :loading="loading || actionLoading === 'remote-status'"
          @click="handleToolbarRefresh"
          >刷新</t-button
        >
      </div>
    </div>

    <data-state
      v-if="!ready"
      :loading="loading"
      :error="Boolean(loadError)"
      :error-text="loadError"
      :empty="!loading && !loadError"
      description="暂无实例详情"
      @retry="loadDetail()"
    />

    <template v-else>
      <t-loading :loading="loading" size="small">
        <section class="console-summary">
          <div class="console-summary__identity">
            <span class="identity-eyebrow">服务实例</span>
            <div class="identity-headline">
              <strong class="identity-title">{{ serviceConsoleTitle }}</strong>
              <t-tag :theme="serviceStatusTheme(detail.status)" variant="light">{{
                serviceStatusLabel(detail.status)
              }}</t-tag>
              <t-tag v-if="serviceRuntimeLabel" :theme="serviceRuntimeTheme" variant="light">{{
                serviceRuntimeLabel
              }}</t-tag>
              <t-tooltip v-else content="上游暂未返回实例的电源状态">
                <t-tag theme="warning" variant="light">运行状态未知</t-tag>
              </t-tooltip>
            </div>
            <p v-if="serviceConsolePath" class="identity-description">{{ serviceConsolePath }}</p>
          </div>
          <div class="console-summary__metrics">
            <div v-for="item in summaryMetrics" :key="item.label" class="summary-metric-card">
              <span class="metric-label">{{ item.label }}</span>
              <strong class="metric-value">{{ item.value }}</strong>
            </div>
          </div>
        </section>
      </t-loading>

      <t-card :bordered="false">
        <t-tabs :value="activeTab" @change="handleTabChange">
          <t-tab-panel value="overview" label="概览" />
          <t-tab-panel value="orders" label="相关订单" />
          <t-tab-panel value="invoices" label="相关账单" />
          <t-tab-panel value="logs" label="操作日志" />
          <t-tab-panel value="tracking" label="历史跟踪" />
        </t-tabs>

        <section v-if="activeTab === 'overview'" class="console-overview">
          <div v-if="detail.upstream?.remote_error" class="console-alert">
            <t-alert theme="warning" :message="detail.upstream.remote_error" />
          </div>

          <section class="console-section">
            <h3 class="console-section__title">实例操作</h3>
            <div class="console-actions">
              <t-button
                v-if="canPowerOn"
                size="small"
                theme="success"
                :loading="actionLoading === 'power:on'"
                @click="handleServicePower('on')"
                >开机</t-button
              >
              <t-button
                v-if="canPowerOff"
                size="small"
                theme="danger"
                variant="outline"
                :loading="actionLoading === 'power:off'"
                @click="handleServicePower('off')"
                >关机</t-button
              >
              <t-button
                v-if="canReboot"
                size="small"
                theme="warning"
                variant="outline"
                :loading="actionLoading === 'power:reboot'"
                @click="handleServicePower('reboot')"
                >重启</t-button
              >
              <t-button
                size="small"
                theme="default"
                :loading="actionLoading === 'remote-status'"
                @click="handleRefreshRemoteStatus"
                >刷新远程</t-button
              >
              <t-button
                size="small"
                :disabled="!serviceActions.password_reset"
                :loading="actionLoading === 'reset-password'"
                @click="openResetPasswordDialog"
                >重置密码</t-button
              >
              <t-button size="small" @click="openUpstreamDialog">上游绑定</t-button>
              <t-button size="small" @click="openPricingDialog">调价</t-button>
              <t-button size="small" @click="openNameDialog">改名称</t-button>
              <t-tag v-if="isServiceRefunded" theme="danger" variant="light">已退款</t-tag>
            </div>
          </section>

          <section class="console-section">
            <h3 class="console-section__title">连接信息</h3>
            <div class="console-field-grid">
              <div class="console-field">
                <span>公网 IP</span>
                <strong>{{ fieldValue(detail.connection?.dedicated_ip || detail.upstream?.dedicated_ip) }}</strong>
              </div>
              <div class="console-field">
                <span>登录账号</span>
                <strong>{{ fieldValue(detail.connection?.username) }}</strong>
              </div>
              <div class="console-field">
                <span>登录端口</span>
                <strong>{{ serviceConnectionPort }}</strong>
              </div>
              <div v-if="detail.connection?.internal_ip" class="console-field">
                <span>内网 IP</span>
                <strong>{{ detail.connection.internal_ip }}</strong>
              </div>
            </div>
          </section>

          <section v-if="serviceSpecs.length" class="console-section">
            <h3 class="console-section__title">规格参数</h3>
            <div class="console-field-grid console-field-grid--compact">
              <div v-for="item in serviceSpecs" :key="item.label" class="console-field console-field--plain">
                <span>{{ item.label }}</span>
                <strong>{{ item.value }}</strong>
              </div>
            </div>
          </section>

          <section class="console-section">
            <h3 class="console-section__title">订单与上游</h3>
            <div class="console-field-grid">
              <div class="console-field">
                <span>账单号</span>
                <strong>{{ fieldValue(detail.invoice?.invoice_no || detail.order?.invoice_no) }}</strong>
              </div>
              <div class="console-field">
                <span>上游</span>
                <strong>{{ serviceUpstreamText }}</strong>
              </div>
              <div class="console-field">
                <span>创建时间</span>
                <strong>{{ formatDateTime(detail.created_at) }}</strong>
              </div>
            </div>
          </section>
        </section>

        <service-orders-tab
          v-else-if="activeTab === 'orders'"
          ref="ordersTabRef"
          :user-id="userId"
          :service-id="serviceId"
        />
        <service-invoices-tab
          v-else-if="activeTab === 'invoices'"
          ref="invoicesTabRef"
          :user-id="userId"
          :service-id="serviceId"
        />
        <service-operation-logs-tab
          v-else-if="activeTab === 'logs'"
          ref="logsTabRef"
          :user-id="userId"
          :service-id="serviceId"
        />
        <service-tracking-tab
          v-else-if="activeTab === 'tracking'"
          ref="trackingTabRef"
          :user-id="userId"
          :service-id="serviceId"
        />
      </t-card>
    </template>

    <t-dialog
      v-model:visible="resetPasswordVisible"
      header="重置登录密码"
      width="420px"
      :confirm-btn="{ content: '确定', loading: actionLoading === 'reset-password' }"
      @cancel="resetPasswordVisible = false"
      @confirm="handleResetPassword"
    >
      <t-form ref="resetPasswordFormRef" :data="resetPasswordForm" :rules="resetPasswordRules" label-align="top">
        <t-form-item label="新密码" name="password">
          <t-input v-model="resetPasswordForm.password" type="password" placeholder="至少 8 位" />
        </t-form-item>
      </t-form>
    </t-dialog>

    <t-dialog
      v-model:visible="upstreamVisible"
      header="上游绑定"
      width="520px"
      :confirm-btn="{ content: '确定', loading: upstreamSubmitting }"
      @cancel="upstreamVisible = false"
      @confirm="submitUpstream"
    >
      <t-form ref="upstreamFormRef" :data="upstreamForm" :rules="upstreamRules" label-align="top">
        <t-form-item label="上游接口" name="supplier_id">
          <t-select
            v-model="upstreamForm.supplier_id"
            clearable
            filterable
            :loading="upstreamLoading"
            placeholder="请选择上游接口"
          >
            <t-option v-for="item in upstreamOptions" :key="item.id" :label="item.label" :value="item.id" />
          </t-select>
        </t-form-item>
        <t-form-item label="上游实例 ID" name="upstream_host_id">
          <t-input-number v-model="upstreamForm.upstream_host_id" :min="1" style="width: 100%" />
        </t-form-item>
      </t-form>
    </t-dialog>

    <t-dialog
      v-model:visible="pricingVisible"
      header="调整价格"
      width="620px"
      :confirm-btn="{ content: '确定', loading: pricingSubmitting }"
      @cancel="pricingVisible = false"
      @confirm="submitPricing"
    >
      <t-form ref="pricingFormRef" :data="pricingForm" :rules="pricingRules" label-align="top">
        <t-form-item label="购买价格" name="amount">
          <t-input-number v-model="pricingForm.amount" :min="0" :decimal-places="2" style="width: 100%" />
        </t-form-item>
        <div v-if="pricingEntries.length" class="pricing-list">
          <div v-for="item in pricingEntries" :key="item.cycle" class="pricing-row">
            <div>
              <strong>{{ item.label }}</strong>
              <span>基础价 {{ item.base_amount ? formatMoney(item.base_amount) : '未配置' }}</span>
            </div>
            <t-switch v-model="pricingForm.locked_pricing[item.cycle].enabled" />
            <t-input-number
              v-model="pricingForm.locked_pricing[item.cycle].manual_amount"
              :min="0"
              :decimal-places="2"
              placeholder="手动价"
            />
          </div>
        </div>
        <t-checkbox v-model="pricingForm.clear_locked_pricing">恢复默认续费价格</t-checkbox>
      </t-form>
    </t-dialog>

    <t-dialog
      v-model:visible="nameVisible"
      header="编辑实例名称"
      width="420px"
      :confirm-btn="{ content: '确定', loading: nameSubmitting }"
      @cancel="nameVisible = false"
      @confirm="submitName"
    >
      <t-form :data="nameForm" label-align="top">
        <t-form-item label="实例名称">
          <t-input v-model="nameForm.service_name" :maxlength="120" placeholder="填写便于识别的实例名称" />
        </t-form-item>
      </t-form>
    </t-dialog>
  </div>
</template>
<script setup lang="ts">
import './index.less';

import DataState from '@caiwu/shared/user-v3/components/DataState.vue';
import { ChevronLeftIcon } from 'tdesign-icons-vue-next';
import type { FormInstanceFunctions, FormRule } from 'tdesign-vue-next';
import { DialogPlugin } from 'tdesign-vue-next/es/dialog/plugin';
import { MessagePlugin } from 'tdesign-vue-next/es/message/plugin';
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';

import { supplierApi } from '@/api/supplier';
import { userApi } from '@/api/user';
import {
  createLockedPricingForm,
  mergeServiceDetail,
  normalizeServiceDetail,
} from '@/pages/users/detail/serviceDetailModel';
import { serviceStatusLabel, serviceStatusTheme } from '@/pages/users/detail/statusDisplay';
import { fieldValue, formatBillingCycle, formatDateTime, formatMoney } from '@/utils/format';
import { errorMessage } from '@/utils/userMessage';

import ServiceInvoicesTab from './components/ServiceInvoicesTab.vue';
import ServiceOperationLogsTab from './components/ServiceOperationLogsTab.vue';
import ServiceOrdersTab from './components/ServiceOrdersTab.vue';
import ServiceTrackingTab from './components/ServiceTrackingTab.vue';

type Row = Record<string, any>;

type TabName = 'overview' | 'orders' | 'invoices' | 'logs' | 'tracking';

const VALID_TABS: TabName[] = ['overview', 'orders', 'invoices', 'logs', 'tracking'];

const route = useRoute();
const router = useRouter();

const userId = computed(() => String(route.params.id || ''));
const serviceId = computed(() => Number(route.params.serviceId || 0));

const loading = ref(false);
const ready = ref(false);
const loadError = ref('');
const loaded = ref(false);
const detail = ref<Row>(normalizeServiceDetail({}));

const activeTab = ref<TabName>('overview');
const ordersTabRef = ref<InstanceType<typeof ServiceOrdersTab>>();
const invoicesTabRef = ref<InstanceType<typeof ServiceInvoicesTab>>();
const logsTabRef = ref<InstanceType<typeof ServiceOperationLogsTab>>();
const trackingTabRef = ref<InstanceType<typeof ServiceTrackingTab>>();

const actionLoading = ref('');

const resetPasswordVisible = ref(false);
const resetPasswordForm = reactive({ password: '' });
const resetPasswordFormRef = ref<FormInstanceFunctions>();
const resetPasswordRules: Record<string, FormRule[]> = {
  password: [
    { validator: (val: unknown) => String(val || '').length >= 8, message: '密码长度至少 8 位', type: 'error' },
  ],
};

const upstreamVisible = ref(false);
const upstreamLoading = ref(false);
const upstreamSubmitting = ref(false);
const upstreamFormRef = ref<FormInstanceFunctions>();
const upstreamOptions = ref<Array<{ id: number; label: string }>>([]);
const upstreamForm = reactive({
  supplier_id: undefined as number | undefined,
  upstream_host_id: undefined as number | undefined,
});

const pricingVisible = ref(false);
const pricingSubmitting = ref(false);
const pricingFormRef = ref<FormInstanceFunctions>();
const pricingForm = reactive<{
  amount: number;
  locked_pricing: Record<
    string,
    { enabled: boolean; base_amount?: number | string | null; manual_amount?: number | null | '' }
  >;
  clear_locked_pricing: boolean;
}>({ amount: 0, locked_pricing: {}, clear_locked_pricing: false });

const nameVisible = ref(false);
const nameSubmitting = ref(false);
const nameForm = reactive({ service_name: '' });

const serviceActions = computed<Row>(() => detail.value.actions || {});

const serviceSpecHiddenLabels = ['product_full_path', 'product_path_segments'];

const serviceSpecs = computed<Array<{ label: string; value: string }>>(() => {
  const specs = Array.isArray(detail.value?.specs) ? detail.value.specs : [];
  return specs
    .filter((item: Row) => {
      const label = String(item?.label || item?.name || '');
      return (
        label &&
        !label.startsWith('_') &&
        !label.endsWith('_product_group_name') &&
        !serviceSpecHiddenLabels.includes(label)
      );
    })
    .map((item: Row) => ({ label: String(item.label || item.name || '-'), value: String(item.value ?? '-') }));
});

const serviceConsoleTitle = computed(() => fieldValue(detail.value.name || detail.value.domain));

const serviceConsolePath = computed(() =>
  String(detail.value.product_full_path || detail.value.product?.display_name || '').trim(),
);

const serviceUpstreamText = computed(() => {
  const providerKey = String(detail.value.upstream?.provider_key || '').trim();
  const hostId = detail.value.upstream?.host_id;
  if (!providerKey && !hostId) return '-';
  return hostId ? `${providerKey || '未登记上游'} / host #${hostId}` : providerKey;
});

const serviceRuntimeLabel = computed(() =>
  String(detail.value.runtime?.power_label || detail.value.runtime?.description || '').trim(),
);

const serviceConnectionPort = computed(() => {
  const port = Number(detail.value.connection?.port || 0);
  return port > 0 ? String(port) : '-';
});

const serviceRuntimeTheme = computed<'success' | 'default' | 'warning'>(() => {
  const state = String(detail.value.runtime?.power_state || '').toLowerCase();
  if (state === 'running') return 'success';
  if (['stopped', 'stop', 'shutdown'].includes(state)) return 'default';
  return 'warning';
});

const availableActions = computed<string[]>(() => {
  const available = serviceActions.value.available;
  return Array.isArray(available) ? available : [];
});

const powerState = computed(() => String(detail.value.runtime?.power_state || '').toLowerCase());

const canPowerOn = computed(() => availableActions.value.includes('power:on') && powerState.value !== 'running');
const canPowerOff = computed(() => availableActions.value.includes('power:off') && powerState.value === 'running');
const canReboot = computed(() => availableActions.value.includes('power:reboot') && powerState.value === 'running');

const isServiceRefunded = computed(() => [5, 6].includes(Number(detail.value.status)));

const summaryMetrics = computed(() => [
  { label: '金额', value: formatMoney(detail.value.amount) },
  { label: '计费周期', value: fieldValue(detail.value.billing_cycle_label) },
  { label: '到期时间', value: formatDateTime(detail.value.expires_at) },
]);

const pricingEntries = computed(() =>
  Object.entries(pricingForm.locked_pricing || {}).map(([cycle, item]) => ({
    cycle,
    label: formatBillingCycle(cycle),
    base_amount: item?.base_amount || null,
  })),
);

const upstreamRules: Record<string, FormRule[]> = {
  supplier_id: [{ validator: validateUpstreamPair, message: '选择上游接口时必须填写上游实例 ID', type: 'error' }],
  upstream_host_id: [{ validator: validateUpstreamPair, message: '填写上游实例 ID 时必须选择上游接口', type: 'error' }],
};
const pricingRules: Record<string, FormRule[]> = {
  amount: [{ required: true, message: '请输入购买价格', type: 'error' }],
};

/** 上游接口与上游实例 ID 必须成对填写，单独填写视为无效绑定。 */
function validateUpstreamPair() {
  const supplierId = Number(upstreamForm.supplier_id || 0);
  const hostId = Number(upstreamForm.upstream_host_id || 0);
  return (supplierId <= 0 && hostId <= 0) || (supplierId > 0 && hostId > 0);
}

function toNumber(value: unknown) {
  const number = Number.parseFloat(String(value ?? 0));
  return Number.isFinite(number) ? number : 0;
}

function isValidationPass(result: unknown) {
  return result === true || result === undefined;
}

function applyTabFromRoute() {
  const tab = String(route.query.tab || '') as TabName;
  if (VALID_TABS.includes(tab)) activeTab.value = tab;
}

function handleTabChange(value: unknown) {
  const tab = String(value || '') as TabName;
  if (!VALID_TABS.includes(tab)) return;
  activeTab.value = tab;
  router.replace({ query: { ...route.query, tab: tab === 'overview' ? undefined : tab } });
}

async function loadDetail(options: { patchRemote?: boolean } = {}) {
  const patchRemote = options.patchRemote !== false;
  if (!userId.value || !serviceId.value) {
    loadError.value = '实例参数缺失';
    ready.value = true;
    return;
  }
  loading.value = true;
  loadError.value = '';
  try {
    // 首屏只取本地快照，避免等待上游四段串行网络往返；远程状态随后异步补丁
    const payload = await userApi.serviceDetail(userId.value, serviceId.value, false);
    detail.value = normalizeServiceDetail(payload);
    ready.value = true;
    loaded.value = true;
    if (patchRemote) {
      void loadRemoteStatusPatch();
    }
  } catch (error) {
    loadError.value = errorMessage(error, '加载实例详情失败');
    if (!loaded.value) {
      MessagePlugin.error(loadError.value);
    }
  } finally {
    loading.value = false;
  }
}

/** 页面已渲染后再异步补齐上游状态，失败不打断首屏（远程错误由告警条呈现） */
async function loadRemoteStatusPatch() {
  if (!userId.value || !serviceId.value) return;
  try {
    const payload = await userApi.serviceRemoteStatus(userId.value, serviceId.value);
    detail.value = mergeServiceDetail(detail.value, payload);
  } catch {
    // 静默失败：不阻断已渲染的本地快照，避免重复打扰
  }
}

/** 工具栏「刷新」= 重新拉本地快照 + 显式刷新远程状态；远程失败要给提示，不能静默丢弃用户意图 */
async function handleToolbarRefresh() {
  await loadDetail({ patchRemote: false });
  if (!ready.value || loadError.value) return;
  await handleRefreshRemoteStatus();
}

async function handleRefreshRemoteStatus() {
  actionLoading.value = 'remote-status';
  try {
    const payload = await userApi.serviceRemoteStatus(userId.value, serviceId.value);
    detail.value = mergeServiceDetail(detail.value, payload);
    MessagePlugin.success('远程状态已刷新');
  } catch (error) {
    MessagePlugin.error(errorMessage(error, '刷新远程状态失败'));
  } finally {
    actionLoading.value = '';
  }
}

/** 电源操作不可逆，需二次确认；指令下发后自动刷新远程状态。 */
function handleServicePower(action: 'on' | 'off' | 'reboot') {
  const label = { on: '开机', off: '关机', reboot: '重启' }[action];
  const dialog = DialogPlugin.confirm({
    header: `${label}确认`,
    body: `确认对实例执行“${label}”操作？`,
    confirmBtn: { content: '确定', theme: 'warning' },
    cancelBtn: '取消',
    theme: 'warning',
    async onConfirm() {
      actionLoading.value = `power:${action}`;
      try {
        const response = await userApi.servicePower(userId.value, serviceId.value, { action });
        if (response?.detail) {
          detail.value = mergeServiceDetail(detail.value, response.detail);
        }
        MessagePlugin.success(response?.message || `${label}指令已下发`);
        dialog.hide();
        await handleRefreshRemoteStatus();
      } catch (error) {
        MessagePlugin.error(errorMessage(error, `${label}失败`));
      } finally {
        actionLoading.value = '';
      }
    },
  });
}

function openResetPasswordDialog() {
  resetPasswordForm.password = '';
  resetPasswordVisible.value = true;
  resetPasswordFormRef.value?.clearValidate?.();
}

async function handleResetPassword() {
  const result = await resetPasswordFormRef.value?.validate?.();
  if (!isValidationPass(result)) return;
  actionLoading.value = 'reset-password';
  try {
    await userApi.serviceResetPassword(userId.value, serviceId.value, { password: resetPasswordForm.password });
    MessagePlugin.success('密码重置指令已下发');
    resetPasswordVisible.value = false;
    await loadDetail();
  } catch (error) {
    MessagePlugin.error(errorMessage(error, '密码重置失败'));
  } finally {
    actionLoading.value = '';
  }
}

async function loadUpstreamOptions() {
  if (upstreamOptions.value.length) return;
  upstreamLoading.value = true;
  try {
    const response = await supplierApi.list({ status: 1, page: 1, page_size: 100 });
    upstreamOptions.value = (response.list || [])
      .map((item: Row) => {
        const name = String(item.name || `接口 #${item.id}`);
        const provider = String(item.provider_label || item.upstream_binding?.provider_key || '上游');
        return { id: Number(item.id), label: `${name} · ${provider}` };
      })
      .filter((item: { id: number }) => item.id > 0);
  } catch (error) {
    MessagePlugin.error(errorMessage(error, '加载上游接口失败'));
  } finally {
    upstreamLoading.value = false;
  }
}

async function openUpstreamDialog() {
  await loadUpstreamOptions();
  upstreamForm.supplier_id = Number(detail.value.upstream?.supplier_id || 0) || undefined;
  upstreamForm.upstream_host_id = Number(detail.value.upstream?.host_id || 0) || undefined;
  upstreamVisible.value = true;
  upstreamFormRef.value?.clearValidate?.();
}

async function submitUpstream() {
  const result = await upstreamFormRef.value?.validate?.();
  if (!isValidationPass(result)) return;
  upstreamSubmitting.value = true;
  try {
    const payload = await userApi.updateServiceMeta(userId.value, serviceId.value, {
      supplier_id: upstreamForm.supplier_id ? Number(upstreamForm.supplier_id) : null,
      upstream_host_id: upstreamForm.upstream_host_id ? Number(upstreamForm.upstream_host_id) : null,
    });
    detail.value = mergeServiceDetail(detail.value, payload);
    upstreamVisible.value = false;
    MessagePlugin.success('上游绑定已更新');
  } catch (error) {
    MessagePlugin.error(errorMessage(error, '更新上游绑定失败'));
  } finally {
    upstreamSubmitting.value = false;
  }
}

function openPricingDialog() {
  pricingForm.amount = toNumber(detail.value.amount);
  pricingForm.locked_pricing = createLockedPricingForm(detail.value);
  pricingForm.clear_locked_pricing = false;
  pricingVisible.value = true;
  pricingFormRef.value?.clearValidate?.();
}

async function submitPricing() {
  const result = await pricingFormRef.value?.validate?.();
  if (!isValidationPass(result)) return;
  pricingSubmitting.value = true;
  try {
    const payload: Record<string, unknown> = { amount: toNumber(pricingForm.amount) };
    if (pricingForm.clear_locked_pricing) {
      payload.clear_locked_pricing = true;
    } else {
      payload.locked_pricing = Object.entries(pricingForm.locked_pricing || {}).reduce(
        (result, [cycle, item]) => {
          result[cycle] = {
            enabled: Boolean(item?.enabled),
            manual_amount:
              item?.manual_amount === '' || item?.manual_amount === null ? null : toNumber(item?.manual_amount),
          };
          return result;
        },
        {} as Record<string, { enabled: boolean; manual_amount: number | null }>,
      );
    }
    const response = await userApi.updateServiceMeta(userId.value, serviceId.value, payload);
    detail.value = mergeServiceDetail(detail.value, response);
    pricingVisible.value = false;
    MessagePlugin.success('价格信息已更新');
  } catch (error) {
    MessagePlugin.error(errorMessage(error, '更新价格信息失败'));
  } finally {
    pricingSubmitting.value = false;
  }
}

function openNameDialog() {
  nameForm.service_name = String(detail.value.custom_service_name || detail.value.name || '');
  nameVisible.value = true;
}

async function submitName() {
  nameSubmitting.value = true;
  try {
    const response = await userApi.updateServiceMeta(userId.value, serviceId.value, {
      service_name: nameForm.service_name,
    });
    detail.value = mergeServiceDetail(detail.value, response);
    nameVisible.value = false;
    MessagePlugin.success('实例名称已更新');
  } catch (error) {
    MessagePlugin.error(errorMessage(error, '更新实例名称失败'));
  } finally {
    nameSubmitting.value = false;
  }
}

function goBack() {
  router.push(`/admin/users/${userId.value}?tab=services`);
}

onMounted(async () => {
  applyTabFromRoute();
  await loadDetail();
});
</script>
