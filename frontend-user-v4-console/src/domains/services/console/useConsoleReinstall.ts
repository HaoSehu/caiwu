import { computed, reactive, ref } from 'vue';
import { MessagePlugin } from 'tdesign-vue-next';

import clientApi from '@/api/client';
import { resolveErrorMessage } from './useConsoleCore';

type AnyRecord = Record<string, any>;

export interface UseConsoleReinstallOptions {
  serviceId: { value: number };
  actionLoading: { value: boolean };
  setOperationStatus: (type: string, label: string) => void;
  loadRemoteStatus: (silent: boolean) => Promise<void>;
  clearStatusSyncTimer: () => void;
  scheduleStatusSync: (callback: () => void, delay: number) => void;
  normalizeDetail: (payload: AnyRecord) => AnyRecord;
  mergeDetail: (patch: AnyRecord) => void;
}

export function useConsoleReinstall(options: UseConsoleReinstallOptions) {
  const {
    serviceId,
    actionLoading,
    setOperationStatus,
    loadRemoteStatus,
    clearStatusSyncTimer,
    scheduleStatusSync,
    normalizeDetail,
    mergeDetail,
  } = options;

  const reinstallVisible = ref(false);
  const reinstallState = reactive({
    loading: false,
    os: [] as AnyRecord[],
    os_group: '',
    os_id: '',
  });

  const reinstallGroupedOptions = computed(() => {
    const groups: Record<string, { group_name: string; items: AnyRecord[] }> = {};
    for (const item of reinstallState.os) {
      const groupName = String(item.group_name || '默认分组');
      if (!groups[groupName]) groups[groupName] = { group_name: groupName, items: [] };
      groups[groupName].items.push(item);
    }
    return Object.values(groups);
  });

  const currentReinstallOptions = computed(() =>
    reinstallGroupedOptions.value.find((item) => item.group_name === reinstallState.os_group)?.items || [],
  );

  async function openReinstallDialog() {
    reinstallVisible.value = true;
    reinstallState.loading = true;
    reinstallState.os = [];
    reinstallState.os_group = '';
    reinstallState.os_id = '';
    try {
      const res = await clientApi.serviceReinstallOptions(serviceId.value);
      const payload = (res as AnyRecord).data || {};
      reinstallState.os = Array.isArray(payload.os) ? payload.os : [];
      const firstGroup = reinstallGroupedOptions.value[0];
      reinstallState.os_group = firstGroup?.group_name || '';
      reinstallState.os_id = String(firstGroup?.items?.[0]?.os_id || '');
    } catch (error: any) {
      MessagePlugin.error(resolveErrorMessage(error, '加载重装系统选项失败'));
    } finally {
      reinstallState.loading = false;
    }
  }

  function handleReinstallGroupChange(value: unknown) {
    reinstallState.os_group = String(value || '');
    const group = reinstallGroupedOptions.value.find((item) => item.group_name === reinstallState.os_group);
    reinstallState.os_id = String(group?.items?.[0]?.os_id || '');
  }

  async function submitReinstall() {
    if (!reinstallState.os_id) {
      MessagePlugin.warning('请选择系统版本');
      return;
    }
    actionLoading.value = true;
    try {
      const res = await clientApi.serviceReinstall(serviceId.value, { os_id: reinstallState.os_id });
      const payload = (res as AnyRecord).data || {};
      if (payload.detail) mergeDetail(payload.detail);
      setOperationStatus('reinstall', '重装系统中');
      reinstallVisible.value = false;
      MessagePlugin.success(String(payload.message || '重装系统任务已提交'));
      clearStatusSyncTimer();
      scheduleStatusSync(() => loadRemoteStatus(true), 1500);
    } catch (error: any) {
      MessagePlugin.error(resolveErrorMessage(error, '重装系统失败'));
    } finally {
      actionLoading.value = false;
    }
  }

  return {
    reinstallVisible,
    reinstallState,
    reinstallGroupedOptions,
    currentReinstallOptions,
    openReinstallDialog,
    handleReinstallGroupChange,
    submitReinstall,
  };
}