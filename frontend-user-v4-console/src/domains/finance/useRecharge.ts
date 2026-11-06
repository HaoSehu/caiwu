import { MessagePlugin } from 'tdesign-vue-next/es/message/plugin';
import { computed, onBeforeUnmount, reactive, ref, shallowRef, watch } from 'vue';
import { useRouter } from 'vue-router';

import clientApi from '@/api/client';
import { useUserStore } from '@/store';
import type {
  RechargeGatewayOption,
  RechargeOrderPayload,
  RechargeStatusPayload,
  ServiceInstance,
} from '@/types/client';
import { getErrorMessage } from '@/utils/error';
import { copyText, formatMoney, formatMoneyText } from '@/utils/format';

import type { PaymentChannel } from './paymentLaunch';
import {
  detectPaymentBrowserEnv,
  detectPaymentChannel,
  normalizePaymentUrl,
  openPaymentLaunch,
  resolvePaymentLaunchPlan,
} from './paymentLaunch';

const ACTIVE_SERVICE_STATUS = 1;
const SEVEN_DAYS_MS = 7 * 24 * 60 * 60 * 1000;

export const RECHARGE_PRESET_AMOUNTS = [20, 50, 100, 200, 500];

export function normalizeRechargeAmount(value: unknown) {
  const amount = Number(value || 0);
  if (!Number.isFinite(amount)) return 20;
  const clamped = Math.min(50000, Math.max(1, amount));
  return Number(clamped.toFixed(2));
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

function normalizePaymentGatewayOption(item: RechargeGatewayOption): RechargeGatewayOption | null {
  const key = String(item?.key || '').trim();
  if (!key) return null;

  const paymentType = String(item.payment_type || '').trim();
  const optionKey = String(item.option_key || (paymentType ? `${key}:${paymentType}` : key)).trim();
  // channel_name 是用户端唯一允许展示的名字（渠道/支付方式，如「支付宝」）；
  // name 现在等于插件名（如「易支付」），只允许管理端展示，用户端任何位置都不得渲染。
  const channelName = String(item.channel_name || item.label || item.name || key).trim() || key;
  const pluginName = String(item.plugin_name || (item.name && item.name !== channelName ? item.name : '')).trim();

  return {
    key,
    option_key: optionKey || key,
    channel_name: channelName,
    label: channelName,
    ...(pluginName ? { plugin_name: pluginName, name: pluginName } : {}),
    ...(paymentType ? { payment_type: paymentType } : {}),
  };
}

function gatewayOptionKey(item: RechargeGatewayOption) {
  return String(item.option_key || item.key || '').trim();
}

/**
 * 充值支付单的轮询凭证持久化键。
 *
 * 微信内点支付会整页跳到网关收银台（公众号 JSAPI 只能整页跳），付完款由网关
 * 的 return_url 回到本页——那是全新挂载的页面，`paymentPayload` 已丢失。
 * 不把凭证带回来，用户看到的会是「还没生成二维码」的空态，而钱已经到账。
 *
 * 与账单页 `useInvoices.ts` 的 ALIPAY_POLL_STORAGE_PREFIX 同一套做法：用
 * sessionStorage 而不是 URL query——poll_token 是支付凭证，放 URL 会进浏览器
 * 历史与 Referer。sessionStorage 在同标签页跳转回来时仍然有效。
 */
const RECHARGE_POLL_STORAGE_KEY = 'client-recharge-poll';

/** 可恢复的支付单凭证；缺失任一字段都视为不可恢复。 */
interface PersistedRechargePoll {
  payment_no: string;
  poll_token: string;
  qr_code: string;
  amount: string;
  gateway: string;
  payment_type: string;
}

export function useRecharge() {
  const router = useRouter();
  const userStore = useUserStore();

  const amount = ref(10);
  const inputAmount = ref(20);
  const submitting = ref(false);
  const polling = ref(false);
  const rechargePaid = ref(false);
  // 支付单已被服务端取消（超时/窗口过期）：停止轮询并提示重新发起，避免对已取消单重复支付。
  const rechargeCancelled = ref(false);
  const summaryLoading = ref(false);
  const paymentGatewaysLoading = ref(true);
  // C-04：支付方式加载失败原先只弹一条 MessagePlugin.error，页面剩下的是一句「暂无可用支付方式」，
  // 既说明不了失败原因也没有重试入口——充值页因此没有可用的错误态。这里保留失败文案供模板渲染重试。
  const paymentGatewaysError = ref('');
  const selectedGateway = ref('');
  const paymentGateways = ref<RechargeGatewayOption[]>([]);
  const paymentPayload = shallowRef<RechargeOrderPayload | (RechargeOrderPayload & RechargeStatusPayload) | null>(null);
  // 只存原始数值：格式化为展示字符串是渲染层的职责，混存会让格式化结果被二次解析成 NaN。
  const rechargeSummary = reactive<{ cashBalance: number; renewNeeded7Days: number }>({
    cashBalance: 0,
    renewNeeded7Days: 0,
  });

  let pollingTimer: number | null = null;
  let pollFailureCount = 0;

  const activePreset = computed(() => (RECHARGE_PRESET_AMOUNTS.includes(inputAmount.value) ? inputAmount.value : null));
  const amountText = computed(() => formatMoney(inputAmount.value).replace(/\.00$/, ''));
  const qrCodeValue = computed(() => String(paymentPayload.value?.qr_code || ''));
  const paymentNo = computed(() => String(paymentPayload.value?.payment_no || ''));
  const hasPaymentGateways = computed(() => paymentGateways.value.length > 0);
  const selectedPaymentGateway = computed(
    () => paymentGateways.value.find((item) => gatewayOptionKey(item) === selectedGateway.value) || null,
  );
  // 用户端展示名只能取渠道名：`name` 是插件名，禁止出现在用户可见文案里。
  const selectedPaymentGatewayName = computed(
    () =>
      selectedPaymentGateway.value?.channel_name ||
      selectedPaymentGateway.value?.label ||
      String(selectedPaymentGateway.value?.key || '') ||
      '支付',
  );
  // 手机端拉起：渠道决定按钮文案（打开支付宝 / 打开微信），
  // mobilePayNotice 承接无法自动唤起的情况（微信内置浏览器打不开支付宝、微信外无法唤起微信支付等），
  // 放在页面里而不是只弹一次 toast，否则用户回头找不到失败原因。
  const mobilePayChannel = computed<PaymentChannel>(() => detectPaymentChannel(selectedPaymentGateway.value));
  const mobilePayNotice = ref('');
  // 最近一次实际发起的拉起目标（scheme 或收银台链接），用于页面标记本次真的唤起了 App。
  const mobilePayTarget = ref('');
  const mobileLaunchButtonText = computed(() => {
    if (mobilePayChannel.value === 'alipay') return '打开支付宝 App';
    // 微信没有可用的外部唤起协议：微信外点按钮只会把链接交出去，不能宣称「打开微信 App」。
    if (mobilePayChannel.value === 'wechat') return '在微信中打开支付';
    return '打开支付页面';
  });
  // 展示用链接：本场景下网关给的就是 https 收银台（scheme 链接不对外展示）。
  const mobilePayUrlText = computed(() => normalizePaymentUrl(qrCodeValue.value));
  /**
   * 微信支付、当前不在微信内、且网关给的是 https 收银台：只能由用户把链接搬进微信完成付款。
   *
   * 公众号 JSAPI 收银台（pay/jspay/…）只在微信内可用，微信外打开会被
   * 302 到微信 OAuth 后拒绝（invalid_source 一类来源校验失败）；H5 支付
   * 未开通时没有任何官方外部唤起方式，因此这里改为「显示链接 + 三步引导」，
   * 而不是假装能唤起微信。
   *
   * 已是 `weixin://` scheme 的链接不在此列——那种链接本来就能直接拉起，
   * 当成文本展示反而会把 scheme 明文暴露给用户、还丢掉可用的拉起入口。
   */
  const mobileWechatManualCopy = computed(
    () =>
      mobilePayChannel.value === 'wechat' &&
      detectPaymentBrowserEnv() !== 'wechat' &&
      /^https?:\/\//i.test(mobilePayUrlText.value),
  );
  const paymentButtonText = computed(() => {
    if (paymentGatewaysLoading.value) return '支付方式加载中…';
    if (!hasPaymentGateways.value) return '暂无可用支付方式';
    if (submitting.value) return '正在生成二维码';
    if (rechargePaid.value) return '继续充值';
    return paymentPayload.value ? '刷新支付二维码' : `生成${selectedPaymentGatewayName.value}二维码`;
  });
  const qrCodeTitle = computed(() => {
    if (rechargePaid.value) return '已支付，余额已刷新';
    if (rechargeCancelled.value) return '该充值单已失效';
    return qrCodeValue.value ? `请使用${selectedPaymentGatewayName.value}扫码支付` : '支付二维码待生成';
  });
  const summaryCards = computed(() => [
    {
      key: 'balance',
      label: '当前余额',
      value: formatMoneyText(rechargeSummary.cashBalance),
      suffix: '',
    },
    {
      key: 'renew-needed-7-days',
      label: '续费需要',
      value: formatMoneyText(rechargeSummary.renewNeeded7Days),
      suffix: '',
      hint: '检测 7 天内到期的续费金额',
      quickFilter: 'expiring_7d',
    },
  ]);

  function selectPreset(value: number) {
    inputAmount.value = value;
  }

  function selectPaymentGateway(optionKey: string) {
    if (!paymentGateways.value.some((item) => gatewayOptionKey(item) === optionKey)) return;
    selectedGateway.value = optionKey;
  }

  /**
   * 持久化当前支付单的轮询凭证：整页跳到网关收银台后，返回时据此恢复轮询与状态。
   *
   * sessionStorage 不可用（隐私模式、存储配额）时静默降级——只失去中断恢复能力，
   * 不影响本次支付本身。
   */
  function persistRechargePoll() {
    const payload = paymentPayload.value;
    if (!payload?.payment_no || !payload?.poll_token) return;

    try {
      window.sessionStorage.setItem(
        RECHARGE_POLL_STORAGE_KEY,
        JSON.stringify({
          payment_no: String(payload.payment_no),
          poll_token: String(payload.poll_token),
          qr_code: String(payload.qr_code || ''),
          amount: String(payload.amount || ''),
          gateway: String(payload.gateway || ''),
          payment_type: String(payload.payment_type || ''),
        } satisfies PersistedRechargePoll),
      );
    } catch {
      // 静默降级：仅失去跳转返回后的恢复能力
    }
  }

  function clearPersistedRechargePoll() {
    try {
      window.sessionStorage.removeItem(RECHARGE_POLL_STORAGE_KEY);
    } catch {
      // 忽略存储异常
    }
  }

  function readPersistedRechargePoll(): PersistedRechargePoll | null {
    try {
      const raw = window.sessionStorage.getItem(RECHARGE_POLL_STORAGE_KEY);
      if (!raw) return null;

      const parsed = JSON.parse(raw) as Partial<PersistedRechargePoll> | null;
      if (!parsed?.payment_no || !parsed?.poll_token) return null;

      return {
        payment_no: String(parsed.payment_no),
        poll_token: String(parsed.poll_token),
        qr_code: String(parsed.qr_code || ''),
        amount: String(parsed.amount || ''),
        gateway: String(parsed.gateway || ''),
        payment_type: String(parsed.payment_type || ''),
      };
    } catch {
      return null;
    }
  }

  /**
   * 从持久化凭证恢复支付单并继续轮询。
   *
   * 只在页面没有当前支付单时调用（跳转返回 = 全新挂载），恢复后立刻触发一次
   * 状态查询：若用户已完成支付，页面直接进入成功态，而不是等下一轮轮询。
   *
   * @returns 是否恢复了支付单
   */
  function restoreRechargePoll(): boolean {
    if (paymentPayload.value?.payment_no) return false;

    const persisted = readPersistedRechargePoll();
    if (!persisted) return false;

    paymentPayload.value = {
      payment_no: persisted.payment_no,
      poll_token: persisted.poll_token,
      qr_code: persisted.qr_code,
      amount: persisted.amount,
      gateway: persisted.gateway,
      ...(persisted.payment_type ? { payment_type: persisted.payment_type } : {}),
    };

    const restoredAmount = Number(persisted.amount || 0);
    if (Number.isFinite(restoredAmount) && restoredAmount > 0) {
      amount.value = restoredAmount;
      inputAmount.value = restoredAmount;
    }

    const gatewayOption = paymentGateways.value.find(
      (item) => item.key === persisted.gateway && String(item.payment_type || '') === persisted.payment_type,
    );
    if (gatewayOption) {
      selectedGateway.value = gatewayOptionKey(gatewayOption);
    }

    startAutoPolling();
    return true;
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
    // 用户主动放弃当前支付单（换金额/换渠道/重新下单）时，持久化凭证必须同步清除，
    // 否则下次进入页面会把这张已放弃的单「恢复」回来。
    clearPersistedRechargePoll();
    rechargePaid.value = false;
    rechargeCancelled.value = false;
    mobilePayNotice.value = '';
    mobilePayTarget.value = '';
    paymentPayload.value = null;
  }

  function notifyScreenReader(message: string) {
    const region = document.getElementById('aria-live-region');
    if (region) region.textContent = message;
  }

  async function createRechargeOrder(overrideAmount?: number) {
    const targetAmount = normalizeRechargeAmount(overrideAmount ?? amount.value);
    const gateway = selectedPaymentGateway.value;
    if (!gateway) {
      MessagePlugin.warning('暂无可用支付方式');
      return null;
    }

    submitting.value = true;
    rechargePaid.value = false;
    rechargeCancelled.value = false;
    try {
      const payload: Record<string, unknown> = { amount: targetAmount, gateway: gateway.key };
      if (gateway.payment_type) {
        payload.payment_type = gateway.payment_type;
      }

      const response = await clientApi.recharge(payload);
      amount.value = targetAmount;
      inputAmount.value = targetAmount;
      paymentPayload.value = response.data || null;
      // 先落盘再返回：调用方拿到结果后可能立刻整页跳走（微信内支付），
      // 晚一步写就来不及了。
      persistRechargePoll();
      MessagePlugin.success('充值二维码已生成');
      return paymentPayload.value;
    } catch (error: unknown) {
      // interceptor 对 HTTP 错误已调用 showError，避免重复弹窗
      const alreadyHandled =
        typeof error === 'object' && error !== null && (error as Record<string, unknown>).__handled === true;
      if (!alreadyHandled) {
        MessagePlugin.error(getErrorMessage(error, '创建充值账单失败'));
      }
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
      pollFailureCount = 0;
      const payload = response.data || {};
      if (payload.paid) {
        rechargePaid.value = true;
        paymentPayload.value = {
          ...paymentPayload.value,
          ...payload,
          paid: true,
        };
        clearPollingTimer();
        // 已到账的单没有恢复价值，清掉凭证避免下次进入页面又"恢复"一张已支付的单。
        clearPersistedRechargePoll();
        await refreshClientInfo();
        // 后端 status() 只在 paid=true 时回传 balance（= user_accounts.cash_balance 真源）；
        // 原先读的 cash_balance 不是该接口字段，永远落到 store 旧值上，到账后余额会滞后显示。
        rechargeSummary.cashBalance = Number(
          payload.balance ?? userStore.info?.cash_balance ?? rechargeSummary.cashBalance,
        );
        notifyScreenReader('支付已成功，充值金额已到账');
        MessagePlugin.success('充值成功，余额已刷新');
      } else if (Number(payload.status) === 4) {
        // PaymentStatus::CANCELLED：支付单已被服务端取消（超时/窗口过期），
        // 二维码已不可支付，停止轮询并引导重新发起，避免用户对失效单付款。
        rechargeCancelled.value = true;
        paymentPayload.value = {
          ...paymentPayload.value,
          ...payload,
        };
        clearPollingTimer();
        // 已取消的单同样没有恢复价值。
        clearPersistedRechargePoll();
        notifyScreenReader('该充值支付单已取消，请重新发起充值；若你已完成付款，款项会转入账户余额');
        MessagePlugin.warning('该充值单已失效，请重新发起充值；若你已完成付款，款项会转入账户余额');
      } else if (!options.silentPending) {
        notifyScreenReader('支付仍在处理中，请稍候');
        MessagePlugin.info(payload.message || '当前仍未支付成功');
      }
    } catch (error: unknown) {
      MessagePlugin.error(getErrorMessage(error, '查询充值状态失败'));
      // 轮询请求连续失败达到阈值即熔断，避免断网/CORS 期间无限重试与弹窗。
      if (pollingTimer !== null) {
        pollFailureCount += 1;
        if (pollFailureCount >= 3) {
          clearPollingTimer();
          pollFailureCount = 0;
          MessagePlugin.warning('网络异常，已停止自动查询，请稍后重试');
        }
      }
    } finally {
      polling.value = false;
    }
  }

  let pollingInterval = 2000;

  function startAutoPolling(interval = pollingInterval) {
    pollingInterval = interval;
    clearPollingTimer();
    pollFailureCount = 0;
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
      const payload = response.data;
      // renew_price 是目录基数（不含会员折扣），实际扣款取默认周期的折后应付价
      const cycles = Array.isArray(payload?.cycles) ? payload.cycles : [];
      const defaultCycle = cycles.find((cycle) => cycle?.billing_cycle === payload?.default_cycle) ?? cycles[0];
      const renewPrice = Number(defaultCycle?.amount || 0);
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
      const MAX_SCAN_PAGES = 3;

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
      } while (collectedServices.length < total && page <= MAX_SCAN_PAGES);

      const now = Date.now();
      const sevenDaysLater = now + SEVEN_DAYS_MS;
      const renewNeededServices = collectedServices.filter((service) => {
        const expiresAt = parseDateTime(service.expires_at);
        return isRenewableService(service) && expiresAt !== null && expiresAt >= now && expiresAt <= sevenDaysLater;
      });
      const renewNeeded7Days = await sumRenewAmounts(renewNeededServices);

      rechargeSummary.cashBalance = Number(userStore.info?.cash_balance || 0);
      rechargeSummary.renewNeeded7Days = Number(renewNeeded7Days || 0);
    } catch (error: unknown) {
      rechargeSummary.cashBalance = Number(userStore.info?.cash_balance || 0);
      MessagePlugin.error(getErrorMessage(error, '加载充值摘要失败'));
    } finally {
      summaryLoading.value = false;
    }
  }

  async function loadPaymentGateways() {
    paymentGatewaysLoading.value = true;
    paymentGatewaysError.value = '';

    try {
      const response = await clientApi.rechargeGateways();
      const list = Array.isArray(response.data?.list) ? response.data.list : [];
      paymentGateways.value = list
        .map((item) => normalizePaymentGatewayOption(item))
        .filter((item): item is RechargeGatewayOption => item !== null);

      if (!paymentGateways.value.some((item) => gatewayOptionKey(item) === selectedGateway.value)) {
        selectedGateway.value = paymentGateways.value[0] ? gatewayOptionKey(paymentGateways.value[0]) : '';
      }

      if (!selectedGateway.value) {
        clearPaymentPayload();
      }
    } catch (error: unknown) {
      paymentGateways.value = [];
      selectedGateway.value = '';
      clearPaymentPayload();
      // 错误文案同时留在页面上：toast 会消失，用户回头再看这块区域时需要知道发生了什么。
      paymentGatewaysError.value = getErrorMessage(error, '加载支付方式失败');
      MessagePlugin.error(paymentGatewaysError.value);
    } finally {
      paymentGatewaysLoading.value = false;
    }
  }

  /**
   * 手机端打开支付入口。
   *
   * 支付宝：直接拉起支付宝 App。微信：没有可用的外部唤起协议，微信内的 https
   * 收银台在微信外无法支付（`weixin://dl/businessWebview/link` 会报 invalid_source，
   * 2026-11 线上问题），因此微信外只提示复制链接到微信打开，由用户手势触发时的
   * 复制按钮完成搬运。
   *
   * 下单成功后立即调用一次（页面此时已进入移动分支），失败时用户还可以用
   * 「打开支付宝 App / 复制支付链接」按钮重试。
   */
  function launchMobilePayment(rawUrl?: unknown) {
    const url = String(rawUrl ?? qrCodeValue.value ?? '').trim();
    if (!url) return false;

    const plan = resolvePaymentLaunchPlan(url, mobilePayChannel.value);
    if (plan.kind === 'blocked') {
      mobilePayNotice.value = plan.message;
      mobilePayTarget.value = '';
      return false;
    }

    mobilePayNotice.value = '';
    mobilePayTarget.value = plan.url;
    return openPaymentLaunch(plan);
  }

  async function handleCreateOrder(isMobileScreen = false) {
    const targetAmount = normalizeRechargeAmount(inputAmount.value);
    inputAmount.value = targetAmount;
    amount.value = targetAmount;

    const result = await createRechargeOrder(targetAmount);
    if (!result?.qr_code) return;

    if (isMobileScreen) {
      launchMobilePayment(result.qr_code);
    }

    startAutoPolling();
  }

  async function copyPayUrl() {
    if (!qrCodeValue.value) return;
    await copyText(qrCodeValue.value, {
      // 微信场景复制后要立刻告诉用户下一步，否则「已复制」等于没说。
      successMsg: mobileWechatManualCopy.value
        ? '已复制，去微信「文件传输助手」粘贴发送后点开'
        : '支付链接已复制到剪贴板',
      errorMsg: mobileWechatManualCopy.value
        ? '当前浏览器不支持自动复制，请长按下方链接手动复制'
        : '当前浏览器不支持自动复制，请手动复制支付链接',
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

  watch(selectedGateway, (value, oldValue) => {
    if (oldValue && value !== oldValue) {
      clearPaymentPayload();
    }
  });

  // 注：keep-alive 暂停/恢复钩子已移除，但页面现在是真的会被缓存的
  // （Content.vue 按 route.name 做 key，2026-10-11 的「include 按组件名匹配、不会被缓存」结论已失效）；
  // 这里用 onBeforeUnmount 清理 timer —— 定时器只在弹窗打开时启动，
  // 而弹窗打开时用户切走页签，本页会在缓存里继续跑，故由各弹窗的开关负责停表（见 startXxx/stopXxx）。
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
    rechargePaid,
    rechargeCancelled,
    summaryLoading,
    paymentGatewaysLoading,
    paymentGatewaysError,
    loadPaymentGateways,
    selectedGateway,
    paymentGateways,
    paymentPayload,
    rechargeSummary,
    amountText,
    qrCodeValue,
    paymentNo,
    paymentButtonText,
    qrCodeTitle,
    hasPaymentGateways,
    selectedPaymentGateway,
    selectedPaymentGatewayName,
    mobilePayChannel,
    mobilePayNotice,
    mobilePayTarget,
    mobileLaunchButtonText,
    mobileWechatManualCopy,
    mobilePayUrlText,
    summaryCards,
    selectPreset,
    selectPaymentGateway,
    handleAmountChange,
    createRechargeOrder,
    pollRechargeStatus,
    startAutoPolling,
    clearPaymentPayload,
    loadRechargeSummary,
    restoreRechargePoll,
    handleCreateOrder,
    launchMobilePayment,
    copyPayUrl,
    openServiceQuickFilter,
  };
}
