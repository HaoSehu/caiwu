/**
 * 财务域共享文案。
 *
 * `resolveInvoiceTypeLabel` 收敛自 invoice-detail-view 与 payment-detail 各自维护的两份
 * 账单类型映射表——两份口径曾不一致（后者缺 referral_credit / manual），此处取并集。
 */
const INVOICE_TYPE_LABELS: Record<string, string> = {
  new: '新购',
  normal: '新购',
  renew: '续费',
  upgrade: '升降级',
  recharge: '充值',
  deduction: '扣款',
  referral_credit: '推荐奖励',
  manual: '手工账单',
};

export function resolveInvoiceTypeLabel(type?: string): string {
  if (!type) return '--';
  return INVOICE_TYPE_LABELS[type] || type;
}
