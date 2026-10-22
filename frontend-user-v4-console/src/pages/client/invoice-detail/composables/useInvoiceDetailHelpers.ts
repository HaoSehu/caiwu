/**
 * 拆分自 src/pages/client/invoice-detail/index.vue
 * 拆分日期：2026-10-11（纯机械搬移，零行为变化）
 */
import { CreditcardIcon, LogoAlipayFilledIcon, LogoWechatpayFilledIcon, WalletIcon } from 'tdesign-icons-vue-next';
import type { Ref } from 'vue';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

import { formatMoney } from '@/domains/finance/useInvoices';
import { formatBillingCycle, pricingSnapshotItems } from '@/domains/finance/useRecords';
import type { InvoicePaymentMethod, InvoiceRecord } from '@/types/client';

export const renewFlowItems = [
  {
    index: '1',
    label: '完成支付',
    description: '余额或支付宝支付成功后，账单状态会自动更新。',
  },
  {
    index: '2',
    label: '执行续费',
    description: '系统按当前续费周期处理服务有效期。',
  },
  {
    index: '3',
    label: '同步服务',
    description: '处理完成后可在服务控制台查看新的到期时间。',
  },
];

export function paymentOptionKey(method: InvoicePaymentMethod) {
  return String(method.option_key || method.key || '').trim();
}

export function paymentMethodIcon(method: InvoicePaymentMethod) {
  const key = String(method.key || '').trim();
  const paymentType = String(method.payment_type || '').trim();
  if (key === 'balance') return WalletIcon;
  if (key === 'alipay' || paymentType === 'alipay') return LogoAlipayFilledIcon;
  if (key === 'wechat' || paymentType === 'wxpay') return LogoWechatpayFilledIcon;
  return CreditcardIcon;
}

export function serviceRecord(row: InvoiceRecord | null | undefined) {
  return row?.service || null;
}

export function isNewPurchaseInvoice(row: InvoiceRecord | null | undefined) {
  const type = String(row?.type || '').toLowerCase();
  return ['new', 'normal'].includes(type);
}

export function isRenewInvoice(row: InvoiceRecord | null | undefined) {
  const type = String(row?.type || '').toLowerCase();
  return type === 'renew';
}

export function productPath(row: InvoiceRecord | null | undefined) {
  return (
    row?.product_full_path ||
    row?.combined_display_name ||
    row?.product_display_name ||
    row?.product_spec_display ||
    '--'
  );
}

export function orderBillingCycle(row: InvoiceRecord | null | undefined) {
  return formatBillingCycle(row?.billing_cycle);
}

export function renewServiceId(row: InvoiceRecord | null | undefined) {
  return Number(serviceRecord(row)?.id || row?.service_id || 0);
}

export function renewServiceName(row: InvoiceRecord | null | undefined) {
  const service = serviceRecord(row);
  const serviceId = renewServiceId(row);
  const productName = productPath(row);
  return service?.name || (productName !== '--' ? productName : '') || (serviceId > 0 ? `服务 #${serviceId}` : '--');
}

export function currentExpiresAt(row: InvoiceRecord | null | undefined) {
  const service = serviceRecord(row);
  return service?.expires_at || '--';
}

export function renewedExpiresAt(row: InvoiceRecord | null | undefined) {
  const current = currentExpiresAt(row);
  const currentTime = current === '--' ? 0 : parseSessionExpiresTime(String(current));
  const baseTime = currentTime > Date.now() ? currentTime : Date.now();
  const date = new Date(baseTime);
  const cycle = String(row?.billing_cycle || '')
    .trim()
    .toLowerCase();

  if (cycle === 'monthly') date.setMonth(date.getMonth() + 1);
  else if (cycle === 'quarterly') date.setMonth(date.getMonth() + 3);
  else if (cycle === 'semiannually') date.setMonth(date.getMonth() + 6);
  else if (cycle === 'annually' || cycle === 'yearly') date.setFullYear(date.getFullYear() + 1);
  else if (cycle === 'biennially') date.setFullYear(date.getFullYear() + 2);
  else if (cycle === 'triennially') date.setFullYear(date.getFullYear() + 3);
  else return currentTime ? formatSessionExpiresAt(baseTime, '--') : '--';

  return formatSessionExpiresAt(date.getTime(), '--');
}

export function renewInfoItems(row: InvoiceRecord | null | undefined) {
  const serviceId = renewServiceId(row);
  return [
    { label: '服务 ID', value: serviceId > 0 ? `#${serviceId}` : '--' },
    { label: '当前到期', value: currentExpiresAt(row) },
    { label: '续费周期', value: orderBillingCycle(row) },
    { label: '续费金额', value: `¥${formatMoney(row?.amount)}` },
    { label: '续费后到期', value: renewedExpiresAt(row) },
    { label: '本次应付', value: `¥${formatMoney(row?.payable_amount)}` },
  ];
}

export function pricingItems(row: InvoiceRecord | null | undefined) {
  return pricingSnapshotItems(row?.config_pricing_snapshot);
}

export function parseSessionExpiresTime(value: string) {
  if (!value) return 0;
  const direct = Date.parse(value);
  if (Number.isFinite(direct)) return direct;
  const normalized = value.replace(/-/g, '/').replace('T', ' ');
  const fallback = Date.parse(normalized);
  return Number.isFinite(fallback) ? fallback : 0;
}

export function formatSessionExpiresAt(timestamp: number, fallback: string) {
  if (!timestamp) return fallback || '--';
  const date = new Date(timestamp);
  const pad = (num: number) => String(num).padStart(2, '0');
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(
    date.getMinutes(),
  )}:${pad(date.getSeconds())}`;
}

export function formatCountdown(value: number) {
  const totalSeconds = Math.max(0, Math.floor(value / 1000));
  const days = Math.floor(totalSeconds / 86400);
  const hours = Math.floor((totalSeconds % 86400) / 3600);
  const minutes = Math.floor((totalSeconds % 3600) / 60);
  const seconds = totalSeconds % 60;
  const timeText = [hours, minutes, seconds].map((item) => String(item).padStart(2, '0')).join(':');
  return days > 0 ? `${days}天 ${timeText}` : timeText;
}

export function useInvoiceDetailHelpers(detail: Ref<InvoiceRecord | null | undefined>) {
  const now = ref(Date.now());
  let sessionCountdownTimer: number | null = null;

  const sessionExpiresAt = computed(() => String(detail.value?.payment_security?.expires_at || '').trim());
  const sessionExpiresTime = computed(() => parseSessionExpiresTime(sessionExpiresAt.value));

  // 记忆化模板函数：避免每秒倒计时 tick（now 变更）触发整模板重算这些纯函数
  const isRenewInvoiceView = computed(() => isRenewInvoice(detail.value));
  const renewInfoItemsView = computed(() => renewInfoItems(detail.value));
  const pricingItemsView = computed(() => pricingItems(detail.value));
  const productPathView = computed(() => productPath(detail.value));
  const memberDiscountAmount = computed(() => Math.max(0, Number(detail.value?.member_discount_amount || 0)));
  const couponDiscountAmount = computed(() => Math.max(0, Number(detail.value?.discount || 0)));
  // 券标识：优先券名（更易读），无券名回退券码
  const couponLabel = computed(() => String(detail.value?.coupon_name || detail.value?.coupon_code || '').trim());
  const memberDiscountLevelName = computed(() => {
    const snapshot = detail.value?.member_discount_snapshot;
    return snapshot && typeof snapshot === 'object'
      ? String((snapshot as Record<string, unknown>).member_level_name || '')
      : '';
  });
  const hasPriceDiscount = computed(() => memberDiscountAmount.value > 0 || couponDiscountAmount.value > 0);
  const originalAmountText = computed(() =>
    formatMoney(Number(detail.value?.amount || 0) + memberDiscountAmount.value + couponDiscountAmount.value),
  );
  const formattedSessionExpiresAt = computed(() =>
    formatSessionExpiresAt(sessionExpiresTime.value, sessionExpiresAt.value),
  );
  const sessionRemainingMs = computed(() => (sessionExpiresTime.value ? sessionExpiresTime.value - now.value : 0));
  const sessionExpired = computed(() => Boolean(sessionExpiresTime.value) && sessionRemainingMs.value <= 0);
  const sessionCountdownText = computed(() => {
    if (!sessionExpiresTime.value) return '剩余 --';
    if (sessionRemainingMs.value <= 0) return '已过期';
    return `剩余 ${formatCountdown(sessionRemainingMs.value)}`;
  });

  function startSessionCountdown() {
    if (sessionCountdownTimer !== null) return;
    sessionCountdownTimer = window.setInterval(() => {
      now.value = Date.now();
    }, 1000);
  }

  function stopSessionCountdown() {
    if (sessionCountdownTimer === null) return;
    window.clearInterval(sessionCountdownTimer);
    sessionCountdownTimer = null;
  }

  watch(
    sessionExpiresAt,
    (value) => {
      now.value = Date.now();
      if (value) {
        startSessionCountdown();
      } else {
        stopSessionCountdown();
      }
    },
    { immediate: true },
  );

  onMounted(() => {
    if (sessionExpiresAt.value) startSessionCountdown();
  });

  onBeforeUnmount(() => {
    stopSessionCountdown();
  });

  return {
    sessionExpiresAt,
    formattedSessionExpiresAt,
    sessionExpired,
    sessionCountdownText,
    isRenewInvoiceView,
    renewInfoItemsView,
    pricingItemsView,
    productPathView,
    memberDiscountAmount,
    couponDiscountAmount,
    couponLabel,
    memberDiscountLevelName,
    hasPriceDiscount,
    originalAmountText,
  };
}
