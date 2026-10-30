import { MessagePlugin } from 'tdesign-vue-next/es/message/plugin';
import { computed, reactive, ref, shallowRef } from 'vue';
import { useRoute } from 'vue-router';

import clientApi from '@/api/client';
import type { ConsoleServiceDetail } from '@/types/client';
import { getErrorMessage } from '@/utils/error';

import {
  CLOUD_TABS,
  DEFAULT_TAB,
  isNatConsole,
  mergeConsoleDetail,
  NAT_TABS,
  normalizeConsoleDetail,
  takeConsoleDetail,
} from './useConsoleCore';

export interface UseConsoleDetailOptions {
  //
}

export function useConsoleDetail(_options?: UseConsoleDetailOptions) {
  const route = useRoute();

  // detail 始终整对象替换（normalize/merge 返回新对象），用 shallowRef 省去深响应式代理开销
  const detail = shallowRef<ConsoleServiceDetail>(normalizeConsoleDetail());
  const detailLoading = ref(false);
  // C-04：详情加载失败必须在页面上呈现可重试的错误态，仅 toast 会让页面停在空骨架上
  const detailError = ref('');
  const statusSyncing = ref(false);
  // SC-24：电源、重装、VNC、重置密码四条写链路原来共用一个 actionLoading，
  // 任一转圈其它按钮全会变灰，且弹窗里的提交按钮与顶栏动作区互相牵连。
  // 拆成独立 ref，每个按钮只被自己的请求约束。
  const powerLoading = ref(false);
  const reinstallLoading = ref(false);
  const vncLoading = ref(false);
  const passwordLoading = ref(false);
  const autoRenewLoading = ref(false);
  const activeTab = ref(DEFAULT_TAB);
  const operationStatus = reactive({ type: '', label: '' });

  let statusSyncTimer: number | null = null;

  const serviceId = computed(() => {
    const id = Number(route.params.id);
    return Number.isFinite(id) && id > 0 ? id : 0;
  });

  const availableTabs = computed(() => (isNatConsole(detail.value) ? NAT_TABS : CLOUD_TABS));
  const canManageConsole = computed(
    () => Boolean(detail.value.actions?.module_status) || Number(detail.value.upstream?.host_id || 0) > 0,
  );
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

  function mergeDetail(patch: Partial<ConsoleServiceDetail>) {
    detail.value = mergeConsoleDetail(detail.value, patch);
  }

  async function loadDetailBase() {
    if (!serviceId.value) return;
    // 入口页做控制台类型判定时已拉过同一份详情，直接复用，不再重复请求。
    // 这里刻意不置 detailLoading：已经拿到数据，再闪一帧骨架是倒退。
    // 约定：预取对象归本 hook 所有且按只读使用（takeConsoleDetail 取出即从缓存删除，不会再有第二处引用）。
    // 后续改动 detail 一律走 mergeDetail() 生成新对象，禁止就地修改。
    const prefetched = takeConsoleDetail(serviceId.value);
    if (prefetched) {
      detail.value = prefetched;
      detailError.value = '';
      return;
    }
    detailLoading.value = true;
    try {
      const res = await clientApi.serviceDetail(serviceId.value);
      detail.value = normalizeConsoleDetail(res.data || {});
      detailError.value = '';
    } catch (error: unknown) {
      detailError.value = getErrorMessage(error, '加载实例信息失败');
      MessagePlugin.error(detailError.value);
    } finally {
      detailLoading.value = false;
    }
  }

  async function fetchConnectionInfo() {
    if (!serviceId.value) return;
    try {
      const res = await clientApi.serviceConnection(serviceId.value);
      const connection = res.data;
      // 只合并连接信息：整对象替换会覆盖 loadRemoteStatus 刚写入的实时状态
      if (connection) mergeDetail({ connection });
    } catch {
      // 静默失败，不影响已展示的页面
    }
  }

  async function loadRemoteStatus(silent = false) {
    if (!serviceId.value) return;
    try {
      const res = await clientApi.serviceRemoteStatus(serviceId.value);
      detail.value = mergeConsoleDetail(detail.value, res.data || {});
      if (!silent) MessagePlugin.success('实例状态已刷新');
    } catch (error: unknown) {
      if (!silent) MessagePlugin.error(getErrorMessage(error, '刷新实例状态失败'));
    }
  }

  async function refreshHostStatus() {
    if (detail.value.actions?.module_status) {
      await clientApi.serviceModuleStatus(serviceId.value, { type: 'host' });
    }
    // 电源状态实时刷新（上游 DCIM 面板），失败静默：module-status 快照仍可回退展示
    if (detail.value.actions?.power_status) {
      await clientApi.servicePowerStatus(serviceId.value).catch(() => undefined);
    }
    await loadRemoteStatus(true);
  }

  async function bootstrap() {
    if (!serviceId.value) return;
    clearOperationStatus();
    activeTab.value = DEFAULT_TAB;
    await loadDetailBase();
    void loadRemoteStatus(true);
    void fetchConnectionInfo();
  }

  return {
    route,
    detail,
    detailLoading,
    detailError,
    statusSyncing,
    powerLoading,
    reinstallLoading,
    vncLoading,
    passwordLoading,
    autoRenewLoading,
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
    fetchConnectionInfo,
  };
}
