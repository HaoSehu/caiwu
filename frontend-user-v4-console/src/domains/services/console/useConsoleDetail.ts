import { computed, reactive, ref } from 'vue';
import { MessagePlugin } from 'tdesign-vue-next';
import { useRoute } from 'vue-router';

import clientApi from '@/api/client';
import { normalizeConsoleDetail, mergeConsoleDetail, isNatConsole, resolveErrorMessage, NAT_TABS, CLOUD_TABS, DEFAULT_TAB } from './useConsoleCore';

type AnyRecord = Record<string, any>;

export interface UseConsoleDetailOptions {
  //
}

export function useConsoleDetail(_options?: UseConsoleDetailOptions) {
  const route = useRoute();

  const detail = ref(normalizeConsoleDetail());
  const detailLoading = ref(false);
  const statusSyncing = ref(false);
  const actionLoading = ref(false);
  const autoRenewLoading = ref(false);
  const showPassword = ref(false);
  const activeTab = ref(DEFAULT_TAB);
  const operationStatus = reactive({ type: '', label: '' });

  let statusSyncTimer: number | null = null;

  const serviceId = computed(() => {
    const id = Number(route.params.id);
    return Number.isFinite(id) && id > 0 ? id : 0;
  });

  const availableTabs = computed(() => (isNatConsole(detail.value) ? NAT_TABS : CLOUD_TABS));
  const canManageConsole = computed(() => Boolean(detail.value.actions?.module_status) || Number(detail.value.upstream?.host_id || 0) > 0);
  const canSyncStatus = computed(() => Boolean(detail.value.actions?.refresh) || canManageConsole.value);

  function clearStatusSyncTimer() {
    if (statusSyncTimer !== null) {
      window.clearTimeout(statusSyncTimer);
      statusSyncTimer = null;
    }
  }

  function scheduleStatusSync(callback: () => void, delay: number) {
    clearStatusSyncTimer();
    statusSyncTimer = window.setTimeout(callback, delay);
  }

  function setOperationStatus(type: string, label: string) {
    operationStatus.type = type;
    operationStatus.label = label;
  }

  function clearOperationStatus(type = '') {
    if (type && operationStatus.type !== type) return;
    operationStatus.type = '';
    operationStatus.label = '';
  }

  function mergeDetail(patch: AnyRecord) {
    detail.value = mergeConsoleDetail(detail.value, patch);
  }

  async function loadDetailBase() {
    if (!serviceId.value) return;
    detailLoading.value = true;
    try {
      const res = await clientApi.serviceBaseDetail(serviceId.value);
      detail.value = normalizeConsoleDetail((res as AnyRecord).data || {});
    } catch (error: any) {
      MessagePlugin.error(resolveErrorMessage(error, '加载实例信息失败'));
    } finally {
      detailLoading.value = false;
    }
  }

  async function loadRemoteStatus(silent = false) {
    if (!serviceId.value) return;
    try {
      const res = await clientApi.serviceRemoteStatus(serviceId.value);
      detail.value = mergeConsoleDetail(detail.value, (res as AnyRecord).data || {});
      if (!silent) MessagePlugin.success('实例状态已刷新');
    } catch (error: any) {
      if (!silent) MessagePlugin.error(resolveErrorMessage(error, '刷新实例状态失败'));
    }
  }

  async function refreshHostStatus() {
    if (detail.value.actions?.module_status) {
      await clientApi.serviceModuleStatus(serviceId.value, { type: 'host' });
    }
    await loadRemoteStatus(true);
  }

  async function bootstrap() {
    if (!serviceId.value) return;
    clearOperationStatus();
    activeTab.value = DEFAULT_TAB;
    await loadDetailBase();
    void loadRemoteStatus(true);
  }

  return {
    route,
    detail,
    detailLoading,
    statusSyncing,
    actionLoading,
    autoRenewLoading,
    showPassword,
    activeTab,
    operationStatus,
    serviceId,
    availableTabs,
    canManageConsole,
    canSyncStatus,
    clearStatusSyncTimer,
    scheduleStatusSync,
    setOperationStatus,
    clearOperationStatus,
    mergeDetail,
    loadDetailBase,
    loadRemoteStatus,
    refreshHostStatus,
    bootstrap,
  };
}
