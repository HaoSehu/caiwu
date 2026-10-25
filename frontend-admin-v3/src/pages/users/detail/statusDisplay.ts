/**
 * 用户详情页的状态文案与标签配色映射。
 *
 * 全部为纯函数：输入原始状态值，输出展示文本或 TDesign tag 主题。
 * 从 index.vue 抽出后，模板与列定义共用同一套口径，避免两处手写漂移。
 */
import { INVOICE_STATUS_MAP, SERVICE_STATUS_MAP, toLabelMap, toTagTypeMap } from '@caiwu/shared/statusConfig';

import { fieldValue } from '@/utils/format';

type TagTheme = 'default' | 'success' | 'primary' | 'warning' | 'danger';

const serviceStatusLabelMap = toLabelMap(SERVICE_STATUS_MAP);
const serviceStatusTypeMap = toTagTypeMap(SERVICE_STATUS_MAP);
const invoiceStatusLabelMap = toLabelMap(INVOICE_STATUS_MAP);
const invoiceStatusTypeMap = toTagTypeMap(INVOICE_STATUS_MAP);

/** 把上游的 tone 值收敛到 TDesign 支持的 tag 主题，未知值退化为 default。 */
export function serviceTone(tone: unknown): TagTheme {
  const normalized = String(tone || '').toLowerCase();
  if (['success', 'primary', 'warning', 'danger'].includes(normalized)) return normalized as TagTheme;
  return 'default';
}

export function serviceStatusLabel(status: unknown): string {
  return serviceStatusLabelMap[String(status ?? '')] || '-';
}

export function serviceStatusTheme(status: unknown): TagTheme {
  const value = serviceStatusTypeMap[String(status ?? '')] || 'default';
  return value === 'info' || value === 'purple' ? 'default' : serviceTone(value);
}

export function invoiceStatusLabel(status: unknown): string {
  return invoiceStatusLabelMap[String(status ?? '')] || '-';
}

export function invoiceTypeLabel(type: unknown): string {
  return (
    (
      {
        new: '新购',
        renew: '续费',
        recharge: '充值',
        deduction: '扣款',
        referral_credit: '推荐奖励',
        refund: '退款',
        manual: '手工',
        upgrade: '附加配置',
      } as Record<string, string>
    )[String(type)] || '-'
  );
}

export function invoiceStatusTheme(status: unknown): TagTheme {
  const value = invoiceStatusTypeMap[String(status ?? '')] || 'default';
  if (value === 'success' || value === 'warning' || value === 'danger') return value;
  return 'default';
}

export function priorityLabel(priority: unknown): string {
  return (
    (
      { 1: '低', 2: '中', 3: '高', 4: '紧急', low: '低', medium: '中', high: '高', urgent: '紧急' } as Record<
        string,
        string
      >
    )[String(priority)] || '-'
  );
}

export function priorityTheme(priority: unknown): 'default' | 'warning' | 'danger' {
  const value = String(priority).toLowerCase();
  if (['3', 'high', '4', 'urgent'].includes(value)) return 'danger';
  if (['2', 'medium'].includes(value)) return 'warning';
  return 'default';
}

export function ticketStatusLabel(status: unknown): string {
  return ({ 0: '待处理', 1: '用户回复', 2: '客服回复', 3: '已关闭' } as Record<number, string>)[Number(status)] || '-';
}

export function ticketStatusTheme(status: unknown): 'default' | 'success' | 'warning' {
  const value = Number(status);
  if (value === 3) return 'success';
  if ([0, 1, 2].includes(value)) return 'warning';
  return 'default';
}

export function noticeLabel(status: unknown): string {
  return (
    ({ success: '成功', failed: '失败', pending: '待发送' } as Record<string, string>)[String(status)] ||
    fieldValue(status)
  );
}

export function noticeTheme(status: unknown): 'default' | 'success' | 'warning' | 'danger' {
  if (status === 'success') return 'success';
  if (status === 'failed') return 'danger';
  if (status === 'pending') return 'warning';
  return 'default';
}
