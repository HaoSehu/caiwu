import { INVOICE_STATUS_MAP, INVOICE_TYPE_MAP, toSelectOptions } from '@caiwu/shared/statusConfig';
import { DialogPlugin } from 'tdesign-vue-next/es/dialog/plugin';
import { MessagePlugin } from 'tdesign-vue-next/es/message/plugin';
import { computed, onBeforeUnmount, ref, shallowRef, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';

import clientApi from '@/api/client';
import { useUserStore } from '@/store';
import type {
  InvoiceAlipayPaymentPayload,
  InvoicePaymentMethod,
  InvoicePaymentSecurity,
  InvoiceRecord,
} from '@/types/client';
import { getErrorMessage } from '@/utils/error';
import { formatMoney, formatMoneyText } from '@/utils/format';

import { useRecordList } from './useRecords';

export type PayMethodKey = string;

export const INVOICE_STATUS_OPTIONS = toSelectOptions(INVOICE_STATUS_MAP, false);

export const INVOICE_TYPE_OPTIONS = [
  'new',
  'renew',
  'upgrade',
  'recharge',
  'deduction',
  'referral_credit',
  'manual',
].map((value) => ({
  label: INVOICE_TYPE_MAP[value] || value,
  value,
}));

function normalizeText(value: unknown) {
  if (typeof value === 'string') return value.trim();
  if (typeof value === 'number') return String(value);
  return '';
}

function pickText(...values: unknown[]) {
  for (const value of values) {
    const text = normalizeText(value);
    if (text) return text;
  }

  return '--';
}

function resolveSummaryField(
  row: InvoiceRecord | null | undefined,
  field: keyof NonNullable<InvoiceRecord['summary']> | string,
) {
  const summary = row?.summary;
  if (!summary || typeof summary !== 'object' || Array.isArray(summary)) {
    return '';
  }

  return normalizeText(summary[field]);
}

function hasProductBinding(row: InvoiceRecord | null | undefined) {
  return Number(row?.product?.id || row?.product_id || 0) > 0;
}

export function normalizeMoney(value: unknown) {
  const amount = Number(value || 0);
  if (!Number.isFinite(amount)) return 0;
  return Math.max(0, Math.round(amount * 100) / 100);
}

export function resolveInvoiceTitle(row: InvoiceRecord | null | undefined) {
  if (hasProductBinding(row)) {
    return pickText(
      row?.product_spec_display,
      row?.combined_display_name,
      row?.product_display_name,
      resolveSummaryField(row, 'headline'),
      row?.type_label,
    );
  }

  return pickText(
    row?.combined_display_name,
    row?.product_display_name,
    resolveSummaryField(row, 'headline'),
    row?.type_label,
  );
}

export function resolveInvoiceNo(row: InvoiceRecord | null | undefined) {
  return normalizeText(row?.invoice_no) || `#${row?.id || 0}`;
}

export function isPayableInvoice(row: InvoiceRecord | null | undefined) {
  const status = Number(row?.status);
  return status === 0;
}

/**
 * 续费账单判断：续费账单的支付窗口按账单 due_date 计（后端 CheckoutSecurityService::paymentSessionExpiresAt），
 * 与 5 分钟支付会话窗口不同，过期后的重建入口也不同（服务控制台重新发起续费）。
 */
export function isRenewInvoiceRow(row: InvoiceRecord | null | undefined) {
  return String(row?.type || '').toLowerCase() === 'renew';
}

/**
 * 列表行是否还能发起支付：状态为待支付，且后端未标记支付窗口已关闭。
 * 列表接口不下发支付会话凭证，窗口状态只能取后端 payment_window_expired（与详情页 can_pay 同一判据），
 * 否则窗口已过的账单仍会显示「去支付」，点进去才被拦回详情页。
 */
export function canPayInvoiceRow(row: InvoiceRecord | null | undefined) {
  return isPayableInvoice(row) && !row?.payment_window_expired;
}

/** 非待支付账单的引导文案：按 4 态值域（0 待支付/1 已支付/4 已取消/5 已退款）给出准确提示，避免一律提示"无需支付"。 */
export function resolveNonPayableInvoiceMessage(status: number): string {
  if (status === 1) return '该账单已支付，无需再次支付';
  if (status === 4) return '该账单已取消，无法支付';
  if (status === 5) return '该账单已退款，无法支付';
  return '该账单当前状态不支持支付';
}

function coercePayMethodKey(value: unknown): PayMethodKey {
  return String(value || '').trim();
}

function payMethodOptionKey(method: InvoicePaymentMethod | null | undefined) {
  const optionKey = normalizeText(method?.option_key);
  if (optionKey) return optionKey;

  return normalizeText(method?.key);
}

export function useInvoiceList() {
  const router = useRouter();
  const canceling = ref(false);
  const {
    loading,
    list,
    total,
    filters,
    hasRows,
    hasActiveFilters,
    loadError,
    loadErrorText,
    loadList,
    handleSearch,
    handlePageSizeChange,
    applyQuickFilter,
    resetFilters,
    dateRange,
  } = useRecordList<InvoiceRecord>({
    fetcher: (params) => clientApi.invoices(params),
    errorMessage: '账单列表加载失败',
  });

  function goToPay(row: InvoiceRecord) {
    router.push(`/client/invoices/${row.id}/pay`);
  }

  function cancelInvoice(row: InvoiceRecord) {
    const dialog = DialogPlugin.confirm({
      header: '取消账单',
      body: '确定取消该账单？取消后不可恢复，使用的优惠券会退回账户；新产品购买的库存将被释放。',
      confirmBtn: { content: '确定', theme: 'danger' },
      cancelBtn: '取消',
      theme: 'danger',
      async onConfirm() {
        dialog.setConfirmLoading(true);
        canceling.value = true;
        try {
          await clientApi.cancelInvoice(row.id);
          MessagePlugin.success('账单已取消');
          await loadList();
          dialog.hide();
        } catch (error: unknown) {
          MessagePlugin.error(getErrorMessage(error, '取消账单失败'));
        } finally {
          canceling.value = false;
          dialog.setConfirmLoading(false);
        }
      },
    });
  }

  return {
    loading,
    canceling,
    list,
    total,
    filters,
    hasRows,
    hasActiveFilters,
    loadError,
    loadErrorText,
    loadList,
    handleSearch,
    handlePageSizeChange,
    applyQuickFilter,
    resetFilters,
    dateRange,
    goToPay,
    cancelInvoice,
  };
}

export function useInvoiceDetail() {
  const route = useRoute();
  const router = useRouter();
  const userStore = useUserStore();

  const loading = ref(false);
  const canceling = ref(false);
  const paying = ref(false);
  // 支付确认框占位：确认框打开或请求在途时不再接受新的支付点击
  const payingConfirming = ref(false);
  const polling = ref(false);
  const detail = shallowRef<InvoiceRecord | null>(null);
  const selectedPayMethod = ref<PayMethodKey>('');
  const allowBalanceDeduction = ref(false);
  const alipayDialogVisible = ref(false);
  const alipayQrCode = ref('');
  const alipayPaymentNo = ref('');
  const alipayPollToken = ref('');
  const alipayGateway = ref('');
  const appliedDeductionAmount = ref('0.00');
  const alipayAmount = ref('0.00');
  const pollTimer = ref<number | null>(null);
  let pollFailureCount = 0;

  const invoiceId = computed(() => Number(route.params.id || 0));
  const loadError = ref(false);
  const loadErrorText = ref('');
  const payMethods = computed<InvoicePaymentMethod[]>(() =>
    Array.isArray(detail.value?.pay_methods) ? detail.value?.pay_methods : [],
  );
  const hasPayMethods = computed(() => payMethods.value.length > 0);
  const selectedPayMethodRecord = computed(
    () => payMethods.value.find((item) => payMethodOptionKey(item) === selectedPayMethod.value) || null,
  );
  const selectedGatewayKey = computed(() => normalizeText(selectedPayMethodRecord.value?.key));
  const selectedPaymentType = computed(() => normalizeText(selectedPayMethodRecord.value?.payment_type));
  // 用户端展示名只能取渠道名：`name` 是插件名，禁止出现在用户可见文案里。
  const selectedPayMethodName = computed(
    () =>
      normalizeText(selectedPayMethodRecord.value?.channel_name) ||
      normalizeText(selectedPayMethodRecord.value?.label) ||
      normalizeText(selectedPayMethodRecord.value?.key) ||
      '支付',
  );
  const paySecurity = computed<InvoicePaymentSecurity>(() => detail.value?.payment_security || {});
  const canPay = computed(() => Boolean(paySecurity.value.can_pay) && isPayableInvoice(detail.value));
  const selectedIsAlipay = computed(
    () => selectedGatewayKey.value === 'alipay' || selectedPaymentType.value === 'alipay',
  );
  const alipayPollingReady = computed(() => Boolean(alipayPaymentNo.value && alipayPollToken.value));
  const balanceAmount = computed(() => normalizeMoney(userStore.info?.cash_balance || 0));
  const payableAmount = computed(() => normalizeMoney(detail.value?.payable_amount || 0));
  const canDeductBalance = computed(() => balanceAmount.value > 0 && payableAmount.value > 0);
  const autoDeductionAmount = computed(() => normalizeMoney(Math.min(balanceAmount.value, payableAmount.value)));
  const estimatedAlipayAmount = computed(() =>
    normalizeMoney(Math.max(payableAmount.value - autoDeductionAmount.value, 0)),
  );
  const balanceText = computed(() => formatMoneyText(balanceAmount.value));
  const autoDeductionAmountText = computed(() => formatMoney(autoDeductionAmount.value));
  const estimatedAlipayAmountText = computed(() => formatMoney(estimatedAlipayAmount.value));
  const appliedDeductionAmountText = computed(() => formatMoney(appliedDeductionAmount.value));
  const hasAppliedBalanceDeduction = computed(() => Number(appliedDeductionAmount.value || 0) > 0);
  const alipayPayableAmount = computed(() => alipayAmount.value || formatMoney(payableAmount.value));
  const showBalanceDeductionOption = computed(() => selectedIsAlipay.value && canDeductBalance.value);
  const showPayActions = computed(() => Boolean(detail.value && isPayableInvoice(detail.value)));
  // 待支付却拿不到支付会话凭证 = 支付窗口已关闭（续费账单按 due_date 计，其余按 5 分钟）。
  // 此时任何支付按钮都会失败，刷新页面也不会延长窗口，必须给出真正可行的下一步。
  const payWindowClosed = computed(
    () => Boolean(detail.value) && isPayableInvoice(detail.value) && !paySecurity.value.can_pay,
  );
  const payTip = computed(() => {
    if (payWindowClosed.value) {
      return isRenewInvoiceRow(detail.value)
        ? '支付窗口已关闭，该续费账单无法继续支付。请返回服务控制台重新发起续费，系统会生成一张新的续费账单。'
        : '支付窗口已关闭，该账单无法继续支付。请联系客服协助处理。';
    }
    if (!canPay.value) return '当前账单状态不支持继续支付。';
    if (selectedIsAlipay.value && allowBalanceDeduction.value) {
      return `将自动抵扣余额 ¥${autoDeductionAmountText.value}，支付宝支付剩余 ¥${estimatedAlipayAmountText.value}。`;
    }
    if (selectedGatewayKey.value && !['balance', 'free'].includes(selectedGatewayKey.value)) {
      return `生成二维码后请使用${selectedPayMethodName.value}完成支付，系统会自动轮询状态。`;
    }
    if (selectedPayMethod.value === 'balance') return '余额支付会直接扣减账户余额并完成账单。';
    return '零元账单无需额外支付。';
  });

  function warnPaymentSessionUnavailable() {
    MessagePlugin.warning(payWindowClosed.value ? payTip.value : '支付会话已失效，请刷新页面后重试');
  }

  function clearPollingTimer() {
    if (pollTimer.value !== null) {
      window.clearInterval(pollTimer.value);
      pollTimer.value = null;
    }
  }

  const ALIPAY_POLL_STORAGE_PREFIX = 'client-invoice-alipay-poll:';

  // 轮询凭证持久化：跳转第三方支付中断返回后可恢复轮询与二维码，避免重复生成支付单
  function persistPollCredentials() {
    if (!invoiceId.value || !alipayPaymentNo.value || !alipayPollToken.value) return;
    try {
      window.sessionStorage.setItem(
        ALIPAY_POLL_STORAGE_PREFIX + invoiceId.value,
        JSON.stringify({
          payment_no: alipayPaymentNo.value,
          poll_token: alipayPollToken.value,
          gateway: alipayGateway.value,
          qr_code: alipayQrCode.value,
        }),
      );
    } catch {
      // sessionStorage 不可用时静默降级，仅失去中断恢复能力
    }
  }

  function clearPersistedPoll() {
    if (!invoiceId.value) return;
    try {
      window.sessionStorage.removeItem(ALIPAY_POLL_STORAGE_PREFIX + invoiceId.value);
    } catch {
      // 忽略存储异常
    }
  }

  function readPersistedPoll() {
    if (!invoiceId.value) return null;
    try {
      const raw = window.sessionStorage.getItem(ALIPAY_POLL_STORAGE_PREFIX + invoiceId.value);
      if (!raw) return null;
      const parsed = JSON.parse(raw) as {
        payment_no?: string;
        poll_token?: string;
        gateway?: string;
        qr_code?: string;
      };
      if (!parsed.payment_no || !parsed.poll_token) return null;
      return parsed;
    } catch {
      return null;
    }
  }

  function startPolling() {
    clearPollingTimer();
    pollFailureCount = 0;
    pollTimer.value = window.setInterval(() => {
      if (!polling.value && alipayPollingReady.value) {
        void pollAlipayStatus(true);
      }
    }, 5000);
  }

  function restoreAlipayPolling() {
    if (!detail.value || !isPayableInvoice(detail.value)) return;
    const persisted = readPersistedPoll();
    if (!persisted) return;
    alipayPaymentNo.value = persisted.payment_no;
    alipayPollToken.value = persisted.poll_token;
    alipayGateway.value = persisted.gateway || alipayGateway.value;
    // 同时还原二维码与弹窗，返回后可直接继续扫码支付
    if (persisted.qr_code) {
      alipayQrCode.value = persisted.qr_code;
      alipayDialogVisible.value = true;
    }
    startPolling();
    MessagePlugin.info('检测到未完成的在线支付，正在自动刷新支付状态');
  }

  function resetPaymentPayload() {
    alipayDialogVisible.value = false;
    alipayQrCode.value = '';
    alipayPaymentNo.value = '';
    alipayPollToken.value = '';
    alipayGateway.value = '';
    appliedDeductionAmount.value = '0.00';
    alipayAmount.value = formatMoney(payableAmount.value);
    clearPersistedPoll();
    clearPollingTimer();
  }

  function syncPayMethod() {
    if (!payMethods.value.length) {
      selectedPayMethod.value = '';
      return;
    }

    if (payMethods.value.some((item) => payMethodOptionKey(item) === selectedPayMethod.value)) return;
    selectedPayMethod.value = coercePayMethodKey(payMethodOptionKey(payMethods.value[0]));
  }

  function selectPayMethod(value: unknown) {
    selectedPayMethod.value = coercePayMethodKey(value);
    allowBalanceDeduction.value = false;
    resetPaymentPayload();
  }

  function handleDeductionToggle() {
    resetPaymentPayload();
  }

  async function refreshClientInfo() {
    try {
      await userStore.getUserInfo();
    } catch {
      // 余额信息可在下次进入页面时刷新，不影响当前支付状态展示。
    }
  }

  function applyAlipayPayload(payload: InvoiceAlipayPaymentPayload | null | undefined, usedBalanceDeduction: boolean) {
    const data = payload || {};
    alipayQrCode.value = String(data.qr_code || '');
    alipayPaymentNo.value = String(data.payment_no || '');
    alipayPollToken.value = String(data.poll_token || '');
    alipayGateway.value = String(data.gateway || data.gateway_key || selectedGatewayKey.value || '');
    appliedDeductionAmount.value = usedBalanceDeduction
      ? String(data.balance_amount || autoDeductionAmountText.value)
      : '0.00';
    alipayAmount.value = String(
      data.amount || estimatedAlipayAmountText.value || detail.value?.payable_amount || '0.00',
    );
    alipayDialogVisible.value = Boolean(alipayQrCode.value);
    persistPollCredentials();
  }

  // 详情并发守卫：快速切换账单/重复刷新时，旧请求晚返回不得覆盖当前路由的最新态。
  let detailRequestSeq = 0;

  /**
   * skipStatusNotice：调用方已明确知道账单变为非待支付（支付成功、取消成功）时传 true，
   * 跳过守卫的状态提示，避免与调用方刚弹的成功提示叠加成双重反馈。
   */
  async function loadDetail(options: { skipStatusNotice?: boolean } = {}) {
    const validId = Number(invoiceId.value);
    if (!Number.isInteger(validId) || validId <= 0) {
      // 无效 ID（0/负数/NaN）：失效进行中的请求并复位详情与加载态，避免调用接口。
      detailRequestSeq += 1;
      detail.value = null;
      loading.value = false;
      return;
    }
    const seq = ++detailRequestSeq;
    loading.value = true;
    loadError.value = false;
    loadErrorText.value = '';
    try {
      const res = await clientApi.invoiceDetail(validId);
      if (seq !== detailRequestSeq) {
        return;
      }
      detail.value = res.data || null;
      alipayAmount.value = formatMoney(payableAmount.value);
      syncPayMethod();
      if (detail.value && !isPayableInvoice(detail.value)) {
        resetPaymentPayload();
        if (!options.skipStatusNotice) {
          MessagePlugin.info(resolveNonPayableInvoiceMessage(Number(detail.value.status)));
        }
        await router.replace({
          path: '/client/invoices',
        });
        return;
      }
      if (detail.value?.status === 1) {
        resetPaymentPayload();
      }
    } catch (error: unknown) {
      if (seq !== detailRequestSeq) {
        return;
      }
      // 错误交给页面用 DataState 呈现（含重试），此处不再额外弹 toast 造成双重反馈
      detail.value = null;
      loadError.value = true;
      loadErrorText.value = getErrorMessage(error, '账单详情加载失败');
    } finally {
      if (seq === detailRequestSeq) {
        loading.value = false;
      }
    }
  }

  function handleCancel() {
    if (!invoiceId.value) return;
    const dialog = DialogPlugin.confirm({
      header: '取消账单',
      body: '取消后需重新创建账单才能继续支付，确认取消吗？',
      confirmBtn: '确定',
      cancelBtn: '取消',
      theme: 'warning',
      async onConfirm() {
        dialog.setConfirmLoading(true);
        canceling.value = true;
        try {
          await clientApi.cancelInvoice(invoiceId.value);
          MessagePlugin.success('账单已取消');
          resetPaymentPayload();
          // 取消成功是本次操作的结果，刷新详情时不再弹"该账单已取消"提示
          await loadDetail({ skipStatusNotice: true });
          dialog.hide();
        } catch (error: unknown) {
          MessagePlugin.error(getErrorMessage(error, '取消账单失败'));
        } finally {
          canceling.value = false;
          dialog.setConfirmLoading(false);
        }
      },
    });
  }

  async function executeBalancePayment() {
    const sessionToken = String(paySecurity.value.session_token || '');
    if (!invoiceId.value || !sessionToken) {
      warnPaymentSessionUnavailable();
      return;
    }

    paying.value = true;
    try {
      const res = await clientApi.payInvoiceByBalance(invoiceId.value, {
        payment_session_token: sessionToken,
      });
      MessagePlugin.success('账单已支付');
      resetPaymentPayload();
      await refreshClientInfo();
      detail.value = res.data?.invoice || detail.value;
      // 支付成功是本次操作的结果，刷新详情时不再弹"该账单已支付"提示
      await loadDetail({ skipStatusNotice: true });
    } catch (error: unknown) {
      MessagePlugin.error(getErrorMessage(error, '余额支付失败'));
    } finally {
      paying.value = false;
    }
  }

  function handlePayByBalance() {
    if (paying.value || payingConfirming.value) return;
    payingConfirming.value = true;
    const dialog = DialogPlugin.confirm({
      header: '余额支付',
      body: `确认使用账户余额支付 ¥${formatMoney(payableAmount.value)} 吗？`,
      confirmBtn: '确定',
      cancelBtn: '取消',
      theme: 'warning',
      async onConfirm() {
        dialog.setConfirmLoading(true);
        try {
          await executeBalancePayment();
          if (Number(detail.value?.status) === 1) dialog.hide();
        } finally {
          payingConfirming.value = false;
          dialog.setConfirmLoading(false);
        }
      },
      onCancel: () => {
        payingConfirming.value = false;
      },
      onClose: () => {
        payingConfirming.value = false;
      },
    });
  }

  async function handlePayByAlipay() {
    if (paying.value || payingConfirming.value) return;
    const sessionToken = String(paySecurity.value.session_token || '');
    if (!invoiceId.value || !sessionToken) {
      warnPaymentSessionUnavailable();
      return;
    }

    const gateway = selectedGatewayKey.value;
    if (!gateway || ['balance', 'free'].includes(gateway)) {
      MessagePlugin.warning('请选择可用支付方式');
      return;
    }

    // 支付宝 + 勾选余额抵扣 + 余额足额：实际会走全额余额扣款（按钮文案也从「生成二维码」变成「支付」），
    // 资金用途与用户预期不同，必须先显式确认，不能静默切换支付方式。
    if (selectedIsAlipay.value && allowBalanceDeduction.value && balanceAmount.value >= payableAmount.value) {
      // 从弹出确认框到用户做选择这段时间必须占住防重入标记：
      // 否则连点「立即支付」会叠出多个确认框，每个确认后都会各自走一次 handlePayByBalance。
      payingConfirming.value = true;
      let confirmed = false;
      try {
        confirmed = await new Promise<boolean>((resolve) => {
          const dialog = DialogPlugin.confirm({
            header: '改用余额支付',
            body: `当前账户余额足以覆盖账单 ¥${formatMoney(payableAmount.value)}，勾选余额抵扣后本次将直接从余额全额扣款，不再生成${selectedPayMethodName.value}二维码。是否继续？`,
            confirmBtn: '使用余额支付',
            cancelBtn: '取消',
            theme: 'warning',
            onConfirm: () => {
              dialog.destroy();
              resolve(true);
            },
            onCancel: () => {
              dialog.destroy();
              resolve(false);
            },
            onClose: () => {
              dialog.destroy();
              resolve(false);
            },
          });
        });
      } finally {
        // 先释放占位，再交给 handlePayByBalance 自己加锁，避免两次加锁互相挡住。
        payingConfirming.value = false;
      }
      if (confirmed) handlePayByBalance();
      return;
    }

    const gatewayPayload: Record<string, unknown> = {
      payment_session_token: sessionToken,
      gateway,
    };
    if (selectedPaymentType.value) {
      gatewayPayload.payment_type = selectedPaymentType.value;
    }

    const shouldUseBalanceDeduction = selectedIsAlipay.value && allowBalanceDeduction.value && canDeductBalance.value;
    paying.value = true;
    try {
      const res = shouldUseBalanceDeduction
        ? await clientApi.payInvoiceByBalanceAndAlipay(invoiceId.value, {
            payment_session_token: sessionToken,
            balance_amount: autoDeductionAmount.value,
          })
        : await clientApi.payInvoiceByAlipay(invoiceId.value, gatewayPayload);
      const payload = res.data;
      applyAlipayPayload(payload, shouldUseBalanceDeduction);

      if (alipayQrCode.value) {
        MessagePlugin.success(`${selectedPayMethodName.value}二维码已生成`);
        startPolling();
      }
    } catch (error: unknown) {
      MessagePlugin.error(getErrorMessage(error, `生成${selectedPayMethodName.value}二维码失败`));
    } finally {
      paying.value = false;
    }
  }

  async function pollAlipayStatus(silent = false) {
    if (!invoiceId.value || !alipayPollingReady.value) return;

    polling.value = true;
    try {
      const res = await clientApi.queryInvoiceAlipayStatus(invoiceId.value, {
        payment_no: alipayPaymentNo.value,
        poll_token: alipayPollToken.value,
        ...(alipayGateway.value ? { gateway: alipayGateway.value } : {}),
      });
      pollFailureCount = 0;
      const payload = res.data || {};
      if (payload.paid) {
        clearPollingTimer();
        resetPaymentPayload();
        MessagePlugin.success('账单已支付');
        await refreshClientInfo();
        detail.value = payload.invoice || detail.value;
        // 支付成功是本次操作的结果，刷新详情时不再弹"该账单已支付"提示
        await loadDetail({ skipStatusNotice: true });
      } else if (!silent) {
        MessagePlugin.info(payload.message || '当前仍未支付成功');
      }
    } catch (error: unknown) {
      MessagePlugin.error(getErrorMessage(error, '查询支付状态失败'));
      // 轮询请求连续失败达到阈值即熔断，避免断网/CORS 期间无限重试与弹窗。
      if (pollTimer.value !== null) {
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

  watch(
    () => detail.value?.status,
    (status) => {
      if (Number(status) === 1) {
        clearPollingTimer();
        resetPaymentPayload();
      }
    },
  );

  watch(
    () => [detail.value?.payable_amount, userStore.info?.cash_balance],
    () => {
      if (!showBalanceDeductionOption.value) {
        allowBalanceDeduction.value = false;
      }

      if (!alipayQrCode.value) {
        alipayAmount.value = formatMoney(payableAmount.value);
      }
    },
  );

  // 路由参数响应式：同路由换 id（账单↔账单跳转）时组件会被复用，必须靠 watch 重新拉取
  watch(
    invoiceId,
    async () => {
      await loadDetail();
      restoreAlipayPolling();
    },
    { immediate: true },
  );

  // 注：keep-alive 暂停/恢复钩子已移除，但页面现在是真的会被缓存的
  // （Content.vue 按 route.name 做 key，2026-10-11 的「include 按组件名匹配、不会被缓存」结论已失效）；
  // 这里用 onBeforeUnmount 清理 timer —— 定时器只在弹窗打开时启动，
  // 而弹窗打开时用户切走页签，本页会在缓存里继续跑，故由各弹窗的开关负责停表（见 startXxx/stopXxx）。
  onBeforeUnmount(() => {
    clearPollingTimer();
  });

  return {
    router,
    loading,
    canceling,
    paying,
    polling,
    detail,
    loadError,
    loadErrorText,
    selectedPayMethod,
    allowBalanceDeduction,
    alipayDialogVisible,
    alipayQrCode,
    alipayPaymentNo,
    appliedDeductionAmountText,
    hasAppliedBalanceDeduction,
    alipayPayableAmount,
    invoiceId,
    payMethods,
    hasPayMethods,
    canPay,
    alipayPollingReady,
    balanceAmount,
    payableAmount,
    balanceText,
    autoDeductionAmountText,
    estimatedAlipayAmountText,
    selectedPayMethodName,
    showBalanceDeductionOption,
    showPayActions,
    payTip,
    loadDetail,
    handleCancel,
    handlePayByBalance,
    handlePayByAlipay,
    handleDeductionToggle,
    pollAlipayStatus,
    selectPayMethod,
  };
}
