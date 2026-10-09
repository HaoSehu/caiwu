import { MessagePlugin } from 'tdesign-vue-next';

/**
 * 通用格式化工具。所有业务页面应从本模块导入，禁止本地重写。
 */

/** 通用日期时间格式化：YYYY-MM-DD HH:mm:ss，无效或空值返回 '-' */
export function formatDateTime(value?: unknown): string {
  if (!value && value !== 0) return '-';
  const date = new Date(value as string | number | Date);
  if (Number.isNaN(date.getTime())) return String(value);
  const pad = (item: number) => String(item).padStart(2, '0');
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(
    date.getHours(),
  )}:${pad(date.getMinutes())}:${pad(date.getSeconds())}`;
}

/** 金额格式化：¥X.XX，空值视为 0 */
export function formatMoney(value?: unknown): string {
  return `¥${Number(value || 0).toFixed(2)}`;
}

/** 大额金额紧凑格式化：≥1 万折算为「万」单位，用于指标卡等窄容器展示 */
export function formatCompactMoney(value?: unknown): string {
  const amount = Number(value || 0);
  if (Math.abs(amount) >= 10000) {
    return `¥${(amount / 10000).toFixed(2)}万`;
  }
  return `¥${amount.toFixed(2)}`;
}

/** 字段值兜底：空字符串/undefined/null 显示 '-'，其余转为字符串返回 */
export function fieldValue(value?: unknown): string {
  if (value === '' || value === undefined || value === null) return '-';
  return String(value);
}

/** 计费周期中文映射 */
const BILLING_CYCLE_LABELS: Record<string, string> = {
  monthly: '月付',
  quarterly: '季付',
  semi_annually: '半年付',
  semiannually: '半年付',
  annually: '年付',
  yearly: '年付',
  biennially: '两年付',
  triennially: '三年付',
  onetime: '一次性',
  one_time: '一次性',
  free: '免费',
};

/** 计费周期格式化，未知值直接回退显示原文本 */
export function formatBillingCycle(value?: unknown): string {
  if (!value) return '-';
  const key = String(value).toLowerCase().trim();
  return BILLING_CYCLE_LABELS[key] || String(value);
}

/** 复制到剪贴板并提示 */
export async function copyToClipboard(text: string, successMsg = '已复制到剪贴板'): Promise<boolean> {
  if (!text) return false;
  try {
    await navigator.clipboard.writeText(text);
    MessagePlugin.success(successMsg);
    return true;
  } catch {
    MessagePlugin.error('复制失败，请手动选择复制');
    return false;
  }
}
