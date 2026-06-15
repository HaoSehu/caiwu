import { reactive, ref } from 'vue';
import { MessagePlugin } from 'tdesign-vue-next';

import clientApi from '@/api/client';
import { normalizeConsoleDetail, mergeConsoleDetail, resolveErrorMessage } from './useConsoleCore';

type AnyRecord = Record<string, any>;

export interface UseConsoleDialogsOptions {
  serviceId: { value: number };
  detail: { value: AnyRecord };
  actionLoading: { value: boolean };
  setOperationStatus: (type: string, label: string) => void;
  loadRemoteStatus: (silent: boolean) => Promise<void>;
  clearStatusSyncTimer: () => void;
  scheduleStatusSync: (callback: () => void, delay: number) => void;
  mergeDetail: (patch: AnyRecord) => void;
}

export function useConsoleDialogs(options: UseConsoleDialogsOptions) {
  const {
    serviceId,
    detail,
    actionLoading,
    setOperationStatus,
    loadRemoteStatus,
    clearStatusSyncTimer,
    scheduleStatusSync,
    mergeDetail,
  } = options;

  // --- Name dialog ---
  const nameVisible = ref(false);
  const nameSubmitting = ref(false);
  const nameForm = reactive({ name: '' });

  function openNameDialog() {
    nameForm.name = String(detail.value.custom_service_name || detail.value.name || '');
    nameVisible.value = true;
  }

  async function submitName() {
    nameSubmitting.value = true;
    try {
      const res = await clientApi.updateServiceName(serviceId.value, { name: nameForm.name });
      const payload = (res as AnyRecord).data || {};
      mergeDetail({
        name: payload.name || nameForm.name,
        custom_service_name: payload.custom_service_name || nameForm.name,
      });
      nameVisible.value = false;
      MessagePlugin.success('实例名称已保存');
    } catch (error: any) {
      MessagePlugin.error(resolveErrorMessage(error, '实例名称保存失败'));
    } finally {
      nameSubmitting.value = false;
    }
  }

  // --- Remark dialog ---
  const remarkVisible = ref(false);
  const remarkSubmitting = ref(false);
  const remarkForm = reactive({ remark: '' });

  function openRemarkDialog() {
    remarkForm.remark = String(detail.value.remark || '');
    remarkVisible.value = true;
  }

  async function submitRemark() {
    remarkSubmitting.value = true;
    try {
      const res = await clientApi.updateServiceRemark(serviceId.value, { remark: remarkForm.remark });
      mergeDetail({ remark: String((res as AnyRecord).data?.remark || remarkForm.remark).trim() });
      remarkVisible.value = false;
      MessagePlugin.success('备注已保存');
    } catch (error: any) {
      MessagePlugin.error(resolveErrorMessage(error, '备注保存失败'));
    } finally {
      remarkSubmitting.value = false;
    }
  }

  // --- Password dialog ---
  const passwordVisible = ref(false);
  const passwordForm = reactive({ password: '', password_confirmation: '' });

  function openPasswordDialog() {
    passwordForm.password = '';
    passwordForm.password_confirmation = '';
    passwordVisible.value = true;
  }

  function generateStrongPassword() {
    const lower = 'abcdefghijkmnopqrstuvwxyz';
    const upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    const digits = '23456789';
    const symbols = '!@#$%^&*_-+=';
    const pools = [lower, upper, digits, symbols];
    const all = pools.join('');
    const pick = (pool: string) => pool[randomInt(pool.length)];
    const chars = [
      ...pools.map(pick),
      ...Array.from({ length: 12 }, () => pick(all)),
    ];

    for (let index = chars.length - 1; index > 0; index -= 1) {
      const swapIndex = randomInt(index + 1);
      [chars[index], chars[swapIndex]] = [chars[swapIndex], chars[index]];
    }

    const password = chars.join('');
    passwordForm.password = password;
    passwordForm.password_confirmation = password;
  }

  function randomInt(max: number): number {
    if (max <= 0) return 0;
    if (typeof window !== 'undefined' && window.crypto?.getRandomValues) {
      const array = new Uint32Array(1);
      window.crypto.getRandomValues(array);
      return array[0] % max;
    }
    return Math.floor(Math.random() * max);
  }

  async function submitResetPassword() {
    if (!passwordForm.password || passwordForm.password.length < 8) {
      MessagePlugin.warning('新密码至少需要 8 位');
      return;
    }
    if (passwordForm.password !== passwordForm.password_confirmation) {
      MessagePlugin.warning('两次输入的密码不一致');
      return;
    }
    actionLoading.value = true;
    try {
      const res = await clientApi.serviceResetPassword(serviceId.value, {
        password: passwordForm.password,
        password_confirmation: passwordForm.password_confirmation,
      });
      const payload = (res as AnyRecord).data || {};
      if (payload.detail) detail.value = normalizeConsoleDetail(payload.detail);
      setOperationStatus('repassword', '重置密码中');
      passwordVisible.value = false;
      passwordForm.password = '';
      passwordForm.password_confirmation = '';
      MessagePlugin.success(String(payload.message || '重置密码指令已提交'));
      clearStatusSyncTimer();
      scheduleStatusSync(() => loadRemoteStatus(true), 1500);
    } catch (error: any) {
      MessagePlugin.error(resolveErrorMessage(error, '重置密码失败'));
    } finally {
      actionLoading.value = false;
    }
  }

  return {
    nameVisible,
    nameSubmitting,
    nameForm,
    openNameDialog,
    submitName,
    remarkVisible,
    remarkSubmitting,
    remarkForm,
    openRemarkDialog,
    submitRemark,
    passwordVisible,
    passwordForm,
    openPasswordDialog,
    generateStrongPassword,
    submitResetPassword,
  };
}