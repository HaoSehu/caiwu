import { computed, onBeforeUnmount, onMounted, reactive, ref, shallowRef, watch } from 'vue';
import { DialogPlugin, MessagePlugin } from 'tdesign-vue-next';
import { useRoute, useRouter } from 'vue-router';

import clientApi from '@/api/client';
import { useUserStore } from '@/store';

type AnyRecord = Record<string, any>;
export type PayMethodKey = 'balance' | 'alipay' | 'free';

export const INVOICE_STATUS_OPTIONS = [
  { label: '待支付', value: 0 },
  { label: '已支付', value: 1 },
  { label: '已取消', value: 2 },
  { label: '已逾期', value: 3 },
  { label: '已退款', value: 5 },
];

export const INVOICE_TYPE_OPTIONS = [
  { label: '新购账单', value: 'new' },
  { label: '续费账单', value: 'renew' },
  { label: '升级账单', value: 'upgrade' },
  { label: '充值账单', value: 'recharge' },
  { label: '扣款账单', value: 'deduction' },
  { label: '推荐奖励账单', value: 'referral_credit' },
  { label: '手工账单', value: 'manual' },
];

const INVOICE_STATUS_LABELS: Record<number, string> = {
  0: '待支付',
  1: '已支付',
  2: '已取消',
  3: '已逾期',
  5: '已退款',
};

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

function resolveSummaryField(row: AnyRecord | null | undefined, field: string) {
  const summary = row?.summary;
  if (!summary || typeof summary !== 'object' || Array.isArray(summary)) {
    return '';
  }

  return normalizeText(summary[field]);
}

function hasProductBinding(row: AnyRecord | null | undefined) {
  return Number(row?.product?.id || row?.product_id || 0) > 0;
}

function normalizeTypeFilter(value: unknown) {
  const rawTypes = Array.isArray(value) ? value : String(value || '').split(',');
  return rawTypes.map((item) => normalizeText(item)).filter(Boolean).join(',');
}

export function formatMoney(value: unknown) {
  const amount = Number(value || 0);
  return Number.isFinite(amount) ? amount.toFixed(2) : '0.00';
}

export function normalizeMoney(value: unknown) {
  const amount = Number(value || 0);
  if (!Number.isFinite(amount)) return 0;
  return Math.max(0, Math.round(amount * 100) / 100);
}

export function resolveInvoiceTitle(row: AnyRecord | null | undefined) {
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

export function resolveInvoiceSubtitle(row: AnyRecord | null | undefined) {
  const title = resolveInvoiceTitle(row);
  const combinedDisplayName = normalizeText(row?.combined_display_name);
  const productDisplayName = normalizeText(row?.product_display_name);

  if (hasProductBinding(row)) {
    return pickText(
      combinedDisplayName !== title ? combinedDisplayName : '',
      productDisplayName !== title ? productDisplayName : '',
      resolveSummaryField(row, 'subheadline'),
      resolveSummaryField(row, 'remark'),
      row?.type_label,
    );
  }

  return pickText(
    row?.product_spec_display,
    resolveSummaryField(row, 'subheadline'),
    resolveSummaryField(row, 'remark'),
    row?.type_label,
  );
}

export function resolveInvoiceNo(row: AnyRecord | null | undefined) {
  return normalizeText(row?.invoice_no) || `#${row?.id || 0}`;
}

export function resolveInvoiceStatusLabel(rowOrStatus: AnyRecord | number | string | null | undefined) {
  if (rowOrStatus && typeof rowOrStatus === 'object') {
    const label = normalizeText(rowOrStatus.status_label);
    if (label) return label;
    return INVOICE_STATUS_LABELS[Number(rowOrStatus.status)] || '--';
  }

  return INVOICE_STATUS_LABELS[Number(rowOrStatus)] || '--';
}

export function resolveInvoiceTagTheme(status: unknown) {
  const current = Number(status);
  if (current === 1) return 'success';
  if (current === 0) return 'warning';
  if (current === 5) return 'primary';
  return 'danger';
}

export function isPayableInvoice(row: AnyRecord | null | undefined) {
  const status = Number(row?.status);
  return status === 0 || status === 3;
}

function resolveListPayload(response: unknown) {
  const payload = (response as AnyRecord)?.data || {};
  return {
    list: Array.isArray(payload.list) ? payload.list : [],
    total: Number(payload.total || 0),
  };
}

export function useInvoiceList(options: { fixedTypes?: unknown; pageSize?: number } = {}) {
  const router = useRouter();
  const route = useRoute();
  const loading = ref(false);
  const summaryLoading = ref(false);
  const canceling = ref(false);
  const list = shallowRef<AnyRecord[]>([]);
  const total = ref(0);
  const summary = shallowRef<AnyRecord>({});
  const detailVisible = ref(false);
  const routeDetailId = ref(0);
  const currentRow = shallowRef<AnyRecord | null>(null);
  const filters = reactive({
    page: 1,
    page_size: Number(options.pageSize || 10),
    keyword: '',
    status: '' as string | number,
    type: '',
  });

  const showTypeSelector = computed(() => !normalizeTypeFilter(options.fixedTypes));
  const metricCards = computed(() => [
    {
      key: 'total',
      label: '账单总数',
      value: Number(summary.value.total_count ?? total.value ?? 0),
      copy: '当前筛选范围内的账单记录',
    },
    {
      key: 'unpaid',
      label: '待支付',
      value: Number(summary.value.unpaid_count || 0),
      copy: `待付 ¥${formatMoney(summary.value.unpaid_amount || 0)}`,
    },
    {
      key: 'paid',
      label: '已支付',
      value: Number(summary.value.paid_count || 0),
      copy: `累计 ¥${formatMoney(summary.value.paid_amount || summary.value.paid_total || 0)}`,
    },
    {
      key: 'amount',
      label: '账单金额',
      value: `¥${formatMoney(summary.value.total_amount || 0)}`,
      copy: '包含优惠前账单金额',
    },
  ]);

  function buildParams() {
    const fixedTypes = normalizeTypeFilter(options.fixedTypes);
    const params: AnyRecord = {
      page: filters.page,
      page_size: filters.page_size,
    };
    if (normalizeText(filters.keyword)) params.keyword = normalizeText(filters.keyword);
    if (filters.status !== '' && filters.status !== null && filters.status !== undefined) params.status = filters.status;
    if (fixedTypes) {
      params.type = fixedTypes;
    } else if (normalizeText(filters.type)) {
      params.type = normalizeText(filters.type);
    }
    return params;
  }

  async function loadSummary() {
    summaryLoading.value = true;
    try {
      const res = await clientApi.invoicesSummary(buildParams());
      summary.value = (res as AnyRecord).data || {};
    } catch {
      summary.value = {};
    } finally {
      summaryLoading.value = false;
    }
  }

  async function loadList() {
    loading.value = true;
    try {
      const res = await clientApi.invoices(buildParams());
      const payload = resolveListPayload(res);
      list.value = payload.list;
      total.value = payload.total;
    } catch (error: any) {
      MessagePlugin.error(error?.message || '账单列表加载失败');
    } finally {
      loading.value = false;
    }
  }

  async function loadData() {
    await Promise.allSettled([loadSummary(), loadList()]);
  }

  function handleSearch() {
    filters.page = 1;
    void loadData();
  }

  function handlePageSizeChange() {
    filters.page = 1;
    void loadList();
  }

  function resetFilters() {
    filters.page = 1;
    filters.page_size = Number(options.pageSize || 10);
    filters.keyword = '';
    filters.status = '';
    filters.type = '';
    void loadData();
  }

  function normalizeInvoiceId(value: unknown) {
    const raw = Array.isArray(value) ? value[0] : value;
    const id = Number(raw || 0);
    return Number.isFinite(id) && id > 0 ? id : 0;
  }

  async function openDetailById(invoiceId: number) {
    if (!invoiceId) return;
    routeDetailId.value = invoiceId;
    try {
      const res = await clientApi.invoiceDetail(invoiceId);
      if (routeDetailId.value !== invoiceId) return;
      currentRow.value = (res as AnyRecord).data || null;
      detailVisible.value = Boolean(currentRow.value);
    } catch (error: any) {
      MessagePlugin.error(error?.message || '账单详情加载失败');
    }
  }

  function openDetail(row: AnyRecord) {
    currentRow.value = row;
    detailVisible.value = true;
    const invoiceId = normalizeInvoiceId(row?.id);
    if (invoiceId) {
      void router.replace({
        path: '/client/invoices',
        query: { ...route.query, detail: String(invoiceId) },
      });
    }
  }

  function closeDetail() {
    detailVisible.value = false;
    currentRow.value = null;
    routeDetailId.value = 0;
    if (route.query.detail !== undefined) {
      const nextQuery = { ...route.query };
      delete nextQuery.detail;
      void router.replace({ path: '/client/invoices', query: nextQuery });
    }
  }

  function goToPay(row: AnyRecord) {
    detailVisible.value = false;
    router.push(`/client/invoices/${row.id}/pay`);
  }

  function cancelInvoice(row: AnyRecord) {
    const dialog = DialogPlugin.confirm({
      header: '取消账单',
      body: '确定取消该账单？取消后不可恢复。',
      confirmBtn: '确认取消',
      cancelBtn: '再想想',
      theme: 'warning',
      async onConfirm() {
        dialog.setConfirmLoading(true);
        canceling.value = true;
        try {
          await clientApi.cancelInvoice(row.id);
          MessagePlugin.success('账单已取消');
          closeDetail();
          await loadData();
          dialog.hide();
        } catch (error: any) {
          MessagePlugin.error(error?.message || '取消账单失败');
        } finally {
          canceling.value = false;
          dialog.setConfirmLoading(false);
        }
      },
    });
  }

  onMounted(() => {
    void loadData();
  });

  watch(
    () => route.query.detail,
    (value) => {
      const invoiceId = normalizeInvoiceId(value);
      if (invoiceId) {
        void openDetailById(invoiceId);
        return;
      }

      if (routeDetailId.value) {
        detailVisible.value = false;
        currentRow.value = null;
        routeDetailId.value = 0;
      }
    },
    { immediate: true },
  );

  return {
    router,
    loading,
    summaryLoading,
    canceling,
    list,
    total,
    summary,
    filters,
    detailVisible,
    currentRow,
    showTypeSelector,
    metricCards,
    loadList,
    loadData,
    loadSummary,
    handleSearch,
    handlePageSizeChange,
    resetFilters,
    openDetail,
    closeDetail,
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
  const polling = ref(false);
  const detail = shallowRef<AnyRecord | null>(null);
  const selectedPayMethod = ref<PayMethodKey>('balance');
  const allowBalanceDeduction = ref(false);
  const alipayDialogVisible = ref(false);
  const alipayQrCode = ref('');
  const alipayPaymentNo = ref('');
  const alipayPollToken = ref('');
  const appliedDeductionAmount = ref('0.00');
  const alipayAmount = ref('0.00');
  const pollTimer = ref<number | null>(null);

  const invoiceId = computed(() => Number(route.params.id || 0));
  const payMethods = computed<AnyRecord[]>(() => (Array.isArray(detail.value?.pay_methods) ? detail.value?.pay_methods : []));
  const paySecurity = computed<AnyRecord>(() => detail.value?.payment_security || {});
  const canPay = computed(() => Boolean(paySecurity.value.can_pay) && isPayableInvoice(detail.value));
  const alipayAvailable = computed(() => payMethods.value.some((item) => item.key === 'alipay'));
  const alipayPollingReady = computed(() => Boolean(alipayPaymentNo.value && alipayPollToken.value));
  const balanceAmount = computed(() => normalizeMoney(userStore.info?.balance || 0));
  const payableAmount = computed(() => normalizeMoney(detail.value?.payable_amount || 0));
  const canDeductBalance = computed(() => balanceAmount.value > 0 && payableAmount.value > 0);
  const autoDeductionAmount = computed(() => normalizeMoney(Math.min(balanceAmount.value, payableAmount.value)));
  const estimatedAlipayAmount = computed(() => normalizeMoney(Math.max(payableAmount.value - autoDeductionAmount.value, 0)));
  const balanceText = computed(() => `¥${formatMoney(balanceAmount.value)}`);
  const autoDeductionAmountText = computed(() => formatMoney(autoDeductionAmount.value));
  const estimatedAlipayAmountText = computed(() => formatMoney(estimatedAlipayAmount.value));
  const appliedDeductionAmountText = computed(() => formatMoney(appliedDeductionAmount.value));
  const hasAppliedBalanceDeduction = computed(() => Number(appliedDeductionAmount.value || 0) > 0);
  const alipayPayableAmount = computed(() => alipayAmount.value || formatMoney(payableAmount.value));
  const showBalanceDeductionOption = computed(() => selectedPayMethod.value === 'alipay' && canDeductBalance.value);
  const showPayActions = computed(() => Boolean(detail.value && isPayableInvoice(detail.value)));
  const payTip = computed(() => {
    if (!canPay.value) return '当前账单状态不支持继续支付。';
    if (selectedPayMethod.value === 'alipay' && allowBalanceDeduction.value) {
      return `将自动抵扣余额 ¥${autoDeductionAmountText.value}，支付宝支付剩余 ¥${estimatedAlipayAmountText.value}。`;
    }
    if (selectedPayMethod.value === 'alipay') return '生成二维码后请使用支付宝扫码完成支付，系统会自动轮询状态。';
    if (selectedPayMethod.value === 'balance') return '余额支付会直接扣减账户余额并完成账单。';
    return '零元账单无需额外支付。';
  });

  function clearPollingTimer() {
    if (pollTimer.value !== null) {
      window.clearInterval(pollTimer.value);
      pollTimer.value = null;
    }
  }

  function resetPaymentPayload() {
    alipayDialogVisible.value = false;
    alipayQrCode.value = '';
    alipayPaymentNo.value = '';
    alipayPollToken.value = '';
    appliedDeductionAmount.value = '0.00';
    alipayAmount.value = formatMoney(payableAmount.value);
    clearPollingTimer();
  }

  function syncPayMethod() {
    if (!payMethods.value.length) return;
    if (payMethods.value.some((item) => item.key === selectedPayMethod.value)) return;
    selectedPayMethod.value = (payMethods.value[0]?.key || 'balance') as PayMethodKey;
  }

  function selectPayMethod(value: unknown) {
    selectedPayMethod.value = String(value || 'balance') as PayMethodKey;
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

  function applyAlipayPayload(payload: AnyRecord, usedBalanceDeduction: boolean) {
    alipayQrCode.value = String(payload.qr_code || '');
    alipayPaymentNo.value = String(payload.payment_no || '');
    alipayPollToken.value = String(payload.poll_token || '');
    appliedDeductionAmount.value = usedBalanceDeduction ? String(payload.balance_amount || autoDeductionAmountText.value) : '0.00';
    alipayAmount.value = String(payload.amount || estimatedAlipayAmountText.value || detail.value?.payable_amount || '0.00');
    alipayDialogVisible.value = Boolean(alipayQrCode.value);
  }

  async function loadDetail() {
    if (!invoiceId.value) return;
    loading.value = true;
    try {
      const res = await clientApi.invoiceDetail(invoiceId.value);
      detail.value = (res as AnyRecord).data || null;
      alipayAmount.value = formatMoney(payableAmount.value);
      syncPayMethod();
      if (detail.value && !isPayableInvoice(detail.value)) {
        resetPaymentPayload();
        await router.replace({
          path: '/client/invoices',
          query: { detail: String(invoiceId.value) },
        });
        return;
      }
      if (detail.value?.status === 1) {
        resetPaymentPayload();
      }
    } catch (error: any) {
      MessagePlugin.error(error?.message || '账单详情加载失败');
    } finally {
      loading.value = false;
    }
  }

  function handleCancel() {
    if (!invoiceId.value) return;
    const dialog = DialogPlugin.confirm({
      header: '取消账单',
      body: '取消后需重新创建账单才能继续支付，确认取消吗？',
      confirmBtn: '确认取消',
      cancelBtn: '再想想',
      theme: 'warning',
      async onConfirm() {
        dialog.setConfirmLoading(true);
        canceling.value = true;
        try {
          await clientApi.cancelInvoice(invoiceId.value);
          MessagePlugin.success('账单已取消');
          resetPaymentPayload();
          await loadDetail();
          dialog.hide();
        } catch (error: any) {
          MessagePlugin.error(error?.message || '取消账单失败');
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
      MessagePlugin.warning('支付会话已失效，请刷新页面后重试');
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
      detail.value = (res as AnyRecord).data?.invoice || detail.value;
      await loadDetail();
    } catch (error: any) {
      MessagePlugin.error(error?.message || '余额支付失败');
    } finally {
      paying.value = false;
    }
  }

  function handlePayByBalance() {
    const dialog = DialogPlugin.confirm({
      header: '余额支付',
      body: `确认使用账户余额支付 ¥${formatMoney(payableAmount.value)} 吗？`,
      confirmBtn: '确认支付',
      cancelBtn: '取消',
      theme: 'warning',
      async onConfirm() {
        dialog.setConfirmLoading(true);
        try {
          await executeBalancePayment();
          if (Number(detail.value?.status) === 1) dialog.hide();
        } finally {
          dialog.setConfirmLoading(false);
        }
      },
    });
  }

  async function handlePayByAlipay() {
    const sessionToken = String(paySecurity.value.session_token || '');
    if (!invoiceId.value || !sessionToken) {
      MessagePlugin.warning('支付会话已失效，请刷新页面后重试');
      return;
    }

    if (allowBalanceDeduction.value && balanceAmount.value >= payableAmount.value) {
      handlePayByBalance();
      return;
    }

    const shouldUseBalanceDeduction = allowBalanceDeduction.value && canDeductBalance.value;
    paying.value = true;
    try {
      const res = shouldUseBalanceDeduction
        ? await clientApi.payInvoiceByBalanceAndAlipay(invoiceId.value, {
            payment_session_token: sessionToken,
            balance_amount: autoDeductionAmount.value,
          })
        : await clientApi.payInvoiceByAlipay(invoiceId.value, {
            payment_session_token: sessionToken,
          });
      const payload = (res as AnyRecord).data || {};
      applyAlipayPayload(payload, shouldUseBalanceDeduction);

      if (alipayQrCode.value) {
        MessagePlugin.success('支付宝二维码已生成');
        clearPollingTimer();
        pollTimer.value = window.setInterval(() => {
          if (!polling.value && alipayPollingReady.value) {
            void pollAlipayStatus(true);
          }
        }, 5000);
      }
    } catch (error: any) {
      MessagePlugin.error(error?.message || '生成支付宝二维码失败');
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
      });
      const payload = (res as AnyRecord).data || {};
      if (payload.paid) {
        clearPollingTimer();
        resetPaymentPayload();
        MessagePlugin.success('账单已支付');
        await refreshClientInfo();
        detail.value = payload.invoice || detail.value;
        await loadDetail();
      } else if (!silent) {
        MessagePlugin.info(payload.message || '当前仍未支付成功');
      }
    } catch (error: any) {
      MessagePlugin.error(error?.message || '查询支付状态失败');
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
    () => [detail.value?.payable_amount, userStore.info?.balance],
    () => {
      if (!showBalanceDeductionOption.value) {
        allowBalanceDeduction.value = false;
      }

      if (!alipayQrCode.value) {
        alipayAmount.value = formatMoney(payableAmount.value);
      }
    },
  );

  onMounted(() => {
    void loadDetail();
  });

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
    canPay,
    alipayAvailable,
    alipayPollingReady,
    balanceAmount,
    payableAmount,
    balanceText,
    autoDeductionAmountText,
    estimatedAlipayAmountText,
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
