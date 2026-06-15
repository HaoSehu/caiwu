import { computed, onBeforeUnmount, reactive, ref } from 'vue';
import { MessagePlugin } from 'tdesign-vue-next';
import { useRoute, useRouter } from 'vue-router';

import { clientAuthApi } from '@/api/auth';
import { useUserStore } from '@/store';

const IDENTITY_CARD_PATTERN = /(^\d{15}$)|(^\d{17}[\dXx]$)/;

function pickQrUrl(payload: Record<string, unknown>) {
  return String(payload.qrcode_url || payload.qr_code_url || payload.scan_url || payload.url || '').trim();
}

function resolveMessage(error: any, fallback: string) {
  return error?.message || fallback;
}

export function useVerification() {
  const route = useRoute();
  const router = useRouter();
  const userStore = useUserStore();

  const loading = ref(false);
  const verificationLoading = ref(false);
  const checkingStatus = ref(false);
  const showVerificationDialog = ref(false);
  const showVerifiedInfoDialog = ref(false);
  const verificationUrl = ref('');
  const certifyId = ref('');
  const canRestartVerification = ref(false);
  const verificationMessage = ref('');
  const form = reactive({
    id: '',
    email: '',
    nickname: '',
    phone: '',
    is_verified: 0,
    real_name: '',
    id_card_masked: '',
    verification_status: 0,
    verification_message: '',
  });
  const verificationForm = reactive({
    realName: '',
    idCard: '',
  });

  let pollingTimer: number | null = null;

  const isVerified = computed(() => Number(form.is_verified || 0) === 1 || Number(form.verification_status || 0) === 2);
  const statusTheme = computed(() => (isVerified.value ? 'success' : 'warning'));
  const statusLabel = computed(() => (isVerified.value ? '已认证' : '待认证'));
  const statusTitle = computed(() => (isVerified.value ? '已完成实名认证' : '尚未完成实名认证'));
  const statusDescription = computed(() =>
    isVerified.value
      ? '当前账户已经通过实名校验，可继续购买需要实名的产品。'
      : '完成实名后，可继续购买受实名限制的商品并提升账户安全性。',
  );
  const canSubmit = computed(() => verificationForm.realName.trim().length > 0 && verificationForm.idCard.trim().length > 0);

  function syncFromUserInfo(info: Record<string, unknown> = {}) {
    form.id = String(info.id || '');
    form.email = String(info.email || '');
    form.nickname = String(info.nickname || info.name || '');
    form.phone = String(info.phone || '');
    form.is_verified = Number(info.is_verified || 0);
    form.real_name = String(info.real_name || '');
    form.id_card_masked = String(info.id_card_masked || '');
    form.verification_status = Number(info.verification_status || 0);
    form.verification_message = String(info.verification_message || '');
    certifyId.value = String(info.verification_certify_id || certifyId.value || '');
  }

  async function refreshUserInfo() {
    const info = await userStore.getUserInfo();
    syncFromUserInfo(info as any);
  }

  async function loadProfile() {
    loading.value = true;
    try {
      if (!userStore.info?.name) {
        await refreshUserInfo();
      } else {
        syncFromUserInfo(userStore.info as any);
      }
    } catch (error: any) {
      MessagePlugin.error(resolveMessage(error, '实名信息加载失败'));
    } finally {
      loading.value = false;
    }
  }

  function openVerificationEntry() {
    if (isVerified.value) {
      showVerifiedInfoDialog.value = true;
      return;
    }
    if (!verificationForm.realName && form.real_name) verificationForm.realName = form.real_name;
    showVerificationDialog.value = true;
    if (certifyId.value && !verificationUrl.value) {
      void refreshVerificationLink();
    }
  }

  function validateVerificationForm() {
    if (!verificationForm.realName.trim()) {
      MessagePlugin.warning('请输入真实姓名');
      return false;
    }
    if (!verificationForm.idCard.trim()) {
      MessagePlugin.warning('请输入身份证号');
      return false;
    }
    if (!IDENTITY_CARD_PATTERN.test(verificationForm.idCard.trim())) {
      MessagePlugin.warning('身份证号格式不正确');
      return false;
    }
    return true;
  }

  function applyVerificationPayload(payload: Record<string, unknown>) {
    certifyId.value = String(payload.certify_id || certifyId.value || '');
    verificationUrl.value = pickQrUrl(payload);
  }

  async function submitVerification() {
    if (!validateVerificationForm()) return;
    verificationLoading.value = true;
    try {
      const res = await clientAuthApi.initVerification({
        realname: verificationForm.realName.trim(),
        idcard: verificationForm.idCard.trim(),
      });
      applyVerificationPayload((res as any).data || {});
      if (!verificationUrl.value) await refreshVerificationLink();
      if (verificationUrl.value) MessagePlugin.success('二维码已生成，请扫码继续认证');
      startPolling();
    } catch (error: any) {
      MessagePlugin.error(resolveMessage(error, '提交失败'));
    } finally {
      verificationLoading.value = false;
    }
  }

  async function refreshVerificationLink() {
    if (!certifyId.value) {
      MessagePlugin.warning('缺少认证会话，请重新提交实名信息');
      return;
    }
    verificationLoading.value = true;
    try {
      const res = await clientAuthApi.verificationQrcode({ certify_id: certifyId.value });
      applyVerificationPayload((res as any).data || {});
      if (!verificationUrl.value) throw new Error('未获取到实名服务商链接');
      startPolling();
    } catch (error: any) {
      MessagePlugin.error(resolveMessage(error, '获取认证链接失败'));
    } finally {
      verificationLoading.value = false;
    }
  }

  function stopPolling() {
    if (pollingTimer !== null) {
      window.clearInterval(pollingTimer);
      pollingTimer = null;
    }
  }

  function startPolling() {
    stopPolling();
    if (!showVerificationDialog.value || !verificationUrl.value || !certifyId.value) return;
    pollingTimer = window.setInterval(() => {
      void checkVerificationStatus(true);
    }, 1000);
  }

  async function checkVerificationStatus(silent = false) {
    if (!certifyId.value || checkingStatus.value) return;
    checkingStatus.value = true;
    try {
      const res = await clientAuthApi.verificationStatus({ certify_id: certifyId.value });
      const payload = (res as any).data || {};
      verificationMessage.value = String(payload.msg || payload.message || '');
      if (Number(payload.status) === 1) {
        MessagePlugin.success('认证成功');
        showVerificationDialog.value = false;
        verificationUrl.value = '';
        canRestartVerification.value = false;
        stopPolling();
        await refreshUserInfo();
      } else if (Number(payload.status) === 4) {
        canRestartVerification.value = false;
        if (!silent) MessagePlugin.warning(verificationMessage.value || '认证处理中，请稍后再试');
      } else {
        canRestartVerification.value = Boolean(payload.can_restart);
        if (!silent) MessagePlugin.warning(verificationMessage.value || '认证未完成');
      }
    } catch (error: any) {
      if (!silent) MessagePlugin.error(resolveMessage(error, '查询失败'));
    } finally {
      checkingStatus.value = false;
    }
  }

  async function restartVerification() {
    verificationLoading.value = true;
    canRestartVerification.value = false;
    stopPolling();
    try {
      const res = await clientAuthApi.restartVerification();
      applyVerificationPayload((res as any).data || {});
      if (!verificationUrl.value) await refreshVerificationLink();
      if (verificationUrl.value) MessagePlugin.success('已重新生成二维码，请重新扫码');
      startPolling();
    } catch (error: any) {
      canRestartVerification.value = true;
      MessagePlugin.error(resolveMessage(error, '重新认证失败'));
    } finally {
      verificationLoading.value = false;
    }
  }

  async function handleCallbackQuery() {
    if (route.query.verification_callback !== '1') return;

    const callbackCertifyId = typeof route.query.certify_id === 'string' ? route.query.certify_id : '';
    if (callbackCertifyId) certifyId.value = callbackCertifyId;
    if (!isVerified.value) showVerificationDialog.value = true;
    await checkVerificationStatus(false);

    const query = { ...route.query };
    delete query.verification_callback;
    delete query.certify_id;
    delete query.result_status;
    delete query.result_message;
    delete query.t;
    await router.replace({ path: route.path, query });
  }

  onBeforeUnmount(() => {
    stopPolling();
  });

  return {
    router,
    loading,
    verificationLoading,
    checkingStatus,
    showVerificationDialog,
    showVerifiedInfoDialog,
    verificationUrl,
    certifyId,
    canRestartVerification,
    verificationMessage,
    form,
    verificationForm,
    isVerified,
    statusTheme,
    statusLabel,
    statusTitle,
    statusDescription,
    canSubmit,
    loadProfile,
    refreshUserInfo,
    openVerificationEntry,
    submitVerification,
    refreshVerificationLink,
    checkVerificationStatus,
    restartVerification,
    handleCallbackQuery,
    stopPolling,
  };
}
