import { computed, onBeforeUnmount, reactive, ref, shallowRef, watch } from 'vue';
import { MessagePlugin } from 'tdesign-vue-next';
import { useRouter } from 'vue-router';

import clientApi from '@/api/client';
import { copyText, formatMoney } from '@/utils/format';
import { useUserStore } from '@/store';
import type { RechargeOrderPayload, RechargeStatusPayload, ServiceInstance } from '@/types/client';

const ACTIVE_SERVICE_STATUS = 1;
const SEVEN_DAYS_MS = 7 * 24 * 60 * 60 * 1000;

export const RECHARGE_PRESET_AMOUNTS = [20, 50, 100, 200, 500];

function getErrorMessage(error: unknown, fallback: string) {
  if (error instanceof Error && error.message) return error.message;
  if (typeof error === 'object' && error !== null && 'message' in error && typeof error.message === 'string') {
    return error.message;
  }
  return fallback;
}

export { formatMoney };

export function normalizeRechargeAmount(value: unknown) {
  const amount = Number(value || 0);
  if (!Number.isFinite(amount)) return 20;
  return Math.min(50000, Math.max(1, Math.round(amount)));
}

function parseDateTime(value: unknown) {
  const text = String(value || '').trim();
  if (!text) return null;
  const timestamp = new Date(text.replace(' ', 'T')).getTime();
  return Number.isFinite(timestamp) ? timestamp : null;
}

function isRenewableService(service: ServiceInstance) {
  return Number(service.status) === ACTIVE_SERVICE_STATUS && Number(service.id || 0) > 0;
}

function resolveAlipayLaunchUrl(rawUrl: unknown) {
  const url = String(rawUrl || '').trim();
  if (!url) return '';
  if (/^alipays?:\/\//i.test(url)) return url;
  if (/^https?:\/\//i.test(url)) {
    return `alipays://platformapi/startapp?appId=20000067&url=${encodeURIComponent(url)}`;
  }
  if (/^\/\//.test(url)) {
    return `alipays://platformapi/startapp?appId=20000067&url=${encodeURIComponent(`https:${url}`)}`;
  }
  if (/^qr\.alipay\.com\//i.test(url) || /^mclient\.alipay\.com\//i.test(url)) {
    return `alipays://platformapi/startapp?appId=20000067&url=${encodeURIComponent(`https://${url}`)}`;
  }
  return '';
}

export function useRecharge() {
  const router = useRouter();
  const userStore = useUserStore();

  const amount = ref(10);
  const inputAmount = ref(20);
  const submitting = ref(false);
  const polling = ref(false);
  const rechargePaid = ref(false);
  const summaryLoading = ref(false);
  const paymentPayload = shallowRef<RechargeOrderPayload | (RechargeOrderPayload & RechargeStatusPayload) | null>(null);
  const rechargeSummary = reactive({
    cashBalance: '0.00',
    renewNeeded7Days: '0.00',
  });

  let pollingTimer: number | null = null;

  const activePreset = computed(() => (RECHARGE_PRESET_AMOUNTS.includes(inputAmount.value) ? inputAmount.value : null));
  const amountText = computed(() => formatMoney(inputAmount.value).replace(/\.00$/, ''));
  const qrCodeValue = computed(() => String(paymentPayload.value?.qr_code || ''));
  const paymentNo = computed(() => String(paymentPayload.value?.payment_no || ''));
  const paymentButtonText = computed(() => {
    if (submitting.value) return '正在生成二维码';
    if (rechargePaid.value) return '继续充值';
    return paymentPayload.value ? '刷新支付宝二维码' : '生成支付宝二维码';
  });
  const qrCodeTitle = computed(() => {
    if (rechargePaid.value) return '充值成功，余额已刷新';
    return qrCodeValue.value ? '请使用支付宝扫码支付' : '支付二维码待生成';
  });
  const qrCodeSubtitle = computed(() => {
    if (rechargePaid.value) return '到账完成后，可继续购买或续费服务。';
    if (qrCodeValue.value && polling.value) return '正在自动确认支付状态，请勿重复支付。';
    return qrCodeValue.value ? '' : '当前充值接口仅支持支付宝扫码支付。';
  });
  const summaryCards = computed(() => [
    {
      key: 'balance',
      label: '当前余额',
      value: rechargeSummary.cashBalance,
      suffix: '元',
    },
    {
      key: 'renew-needed-7-days',
      label: '续费需要',
      value: rechargeSummary.renewNeeded7Days,
      suffix: '元',
      hint: '检测 7 天内到期的续费金额',
      quickFilter: 'expiring_7d',
    },
  ]);

  function selectPreset(value: number) {
    inputAmount.value = value;
  }

  function handleAmountChange(value: unknown) {
    const nextAmount = normalizeRechargeAmount(value);
    if (nextAmount !== value) {
      inputAmount.value = nextAmount;
    }
    amount.value = nextAmount;
  }

  function clearPollingTimer() {
    if (pollingTimer !== null) {
      window.clearInterval(pollingTimer);
      pollingTimer = null;
    }
  }

  function clearPaymentPayload() {
    clearPollingTimer();
    rechargePaid.value = false;
    paymentPayload.value = null;
  }

  async function createRechargeOrder(overrideAmount?: number) {
    const targetAmount = normalizeRechargeAmount(overrideAmount ?? amount.value);
    submitting.value = true;
    rechargePaid.value = false;
    try {
      const response = await clientApi.recharge({ amount: targetAmount });
      amount.value = targetAmount;
      inputAmount.value = targetAmount;
      paymentPayload.value = response.data || null;
      MessagePlugin.success('充值二维码已生成');
      return paymentPayload.value;
    } catch (error: unknown) {
      MessagePlugin.error(getErrorMessage(error, '创建充值账单失败'));
      return null;
    } finally {
      submitting.value = false;
    }
  }

  async function pollRechargeStatus(options: { silentPending?: boolean } = {}) {
    const pollPaymentNo = String(paymentPayload.value?.payment_no || '');
    const pollToken = String(paymentPayload.value?.poll_token || '');
    if (!pollPaymentNo || !pollToken) {
      MessagePlugin.warning('缺少支付轮询凭证');
      return;
    }

    polling.value = true;
    try {
      const response = await clientApi.rechargeStatus(pollPaymentNo, { poll_token: pollToken });
      const payload = response.data || {};
      if (payload.paid) {
        rechargePaid.value = true;
        paymentPayload.value = {
          ...paymentPayload.value,
          ...payload,
          paid: true,
        };
        clearPollingTimer();
        await refreshClientInfo();
        rechargeSummary.cashBalance = formatMoney(payload.cash_balance ?? userStore.info?.cash_balance ?? rechargeSummary.cashBalance);
        MessagePlugin.success('充值成功，余额已刷新');
      } else if (!options.silentPending) {
        MessagePlugin.info(payload.message || '当前仍未支付成功');
      }
    } catch (error: unknown) {
      MessagePlugin.error(getErrorMessage(error, '查询充值状态失败'));
    } finally {
      polling.value = false;
    }
  }

  function startAutoPolling(interval = 2000) {
    clearPollingTimer();
    if (!paymentPayload.value?.payment_no || !paymentPayload.value?.poll_token) return;

    void pollRechargeStatus({ silentPending: true });
    pollingTimer = window.setInterval(() => {
      if (!paymentPayload.value?.payment_no || polling.value) return;
      void pollRechargeStatus({ silentPending: true });
    }, interval);
  }

  async function refreshClientInfo() {
    try {
      await userStore.getUserInfo();
    } catch {
      // 余额刷新失败时保留轮询返回的余额，下次进入页面再同步账户资料。
    }
  }

  async function resolvePreviewRenewAmount(service: ServiceInstance) {
    const serviceId = Number(service.id || 0);
    if (serviceId <= 0) return 0;

    try {
      const response = await clientApi.serviceRenewPreview(serviceId);
      const renewPrice = Number(response.data?.renew_price || 0);
      return Number.isFinite(renewPrice) ? renewPrice : 0;
    } catch {
      return 0;
    }
  }

  async function sumRenewAmounts(services: ServiceInstance[], chunkSize = 4) {
    let totalAmount = 0;

    for (let index = 0; index < services.length; index += chunkSize) {
      const currentChunk = services.slice(index, index + chunkSize);
      const amounts = await Promise.all(currentChunk.map((service) => resolvePreviewRenewAmount(service)));
      totalAmount += amounts.reduce((sum, value) => sum + Number(value || 0), 0);
    }

    return totalAmount;
  }

  async function loadRechargeSummary() {
    summaryLoading.value = true;

    try {
      const collectedServices: ServiceInstance[] = [];
      let page = 1;
      let total = 0;
      let pageSize = 50;

      do {
        const response = await clientApi.services({
          page,
          page_size: pageSize,
          status_scope: 'active_pending',
        });

        const payload = response.data || { list: [], total: 0 };
        const list = Array.isArray(payload.list) ? payload.list : [];
        total = Number(payload.total || 0);
        pageSize = Number(payload.page_size || pageSize || 50);
        collectedServices.push(...list);
        page += 1;
      } while (collectedServices.length < total);

      const now = Date.now();
      const sevenDaysLater = now + SEVEN_DAYS_MS;
      const renewNeededServices = collectedServices.filter((service) => {
        const expiresAt = parseDateTime(service.expires_at);
        return isRenewableService(service) && expiresAt !== null && expiresAt >= now && expiresAt <= sevenDaysLater;
      });
      const renewNeeded7Days = await sumRenewAmounts(renewNeededServices);

      rechargeSummary.cashBalance = formatMoney(userStore.info?.cash_balance || 0);
      rechargeSummary.renewNeeded7Days = formatMoney(renewNeeded7Days);
    } catch (error: unknown) {
      rechargeSummary.cashBalance = formatMoney(userStore.info?.cash_balance || 0);
      MessagePlugin.error(getErrorMessage(error, '加载充值摘要失败'));
    } finally {
      summaryLoading.value = false;
    }
  }

  async function handleCreateOrder(isMobileScreen = false) {
    const targetAmount = normalizeRechargeAmount(inputAmount.value);
    inputAmount.value = targetAmount;
    amount.value = targetAmount;

    const result = await createRechargeOrder(targetAmount);
    if (!result?.qr_code) return;

    if (isMobileScreen) {
      const launchUrl = resolveAlipayLaunchUrl(result.qr_code);
      if (launchUrl) {
        const iframe = document.createElement('iframe');
        iframe.style.display = 'none';
        iframe.src = launchUrl;
        document.body.appendChild(iframe);
        window.setTimeout(() => {
          if (iframe.parentNode) iframe.parentNode.removeChild(iframe);
        }, 3000);
      }
    }

    startAutoPolling();
  }

  async function copyPayUrl() {
    if (!qrCodeValue.value) return;
    await copyText(qrCodeValue.value, {
      successMsg: '支付链接已复制到剪贴板',
      errorMsg: '当前浏览器不支持自动复制，请手动复制',
    });
  }

  function openServiceQuickFilter(quickFilter: unknown) {
    const targetFilter = String(quickFilter || '').trim();
    if (!targetFilter) return;

    router.push({
      path: '/client/services',
      query: { quick_filter: targetFilter },
    });
  }

  watch(
    inputAmount,
    (value) => {
      const nextAmount = normalizeRechargeAmount(value);
      amount.value = nextAmount;
      if (paymentPayload.value && nextAmount !== Number(paymentPayload.value.amount || 0)) {
        clearPaymentPayload();
      }
    },
    { immediate: true },
  );

  onBeforeUnmount(() => {
    clearPollingTimer();
  });

  return {
    router,
    userStore,
    amount,
    inputAmount,
    activePreset,
    submitting,
    polling,
    rechargePaid,
    summaryLoading,
    paymentPayload,
    rechargeSummary,
    amountText,
    qrCodeValue,
    paymentNo,
    paymentButtonText,
    qrCodeTitle,
    qrCodeSubtitle,
    summaryCards,
    selectPreset,
    handleAmountChange,
    createRechargeOrder,
    pollRechargeStatus,
    startAutoPolling,
    clearPaymentPayload,
    loadRechargeSummary,
    handleCreateOrder,
    copyPayUrl,
    openServiceQuickFilter,
  };
}
