import { formatMoneyAmount, formatMoneyText } from '@caiwu/shared/finance';
import { MessagePlugin } from 'tdesign-vue-next/es/message/plugin';

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

/**
 * 金额格式化：¥X.XX（千分位分组，负数符号在 ¥ 之前）。
 *
 * 口径由 shared/finance/money 统一，管理端、用户控制台与官网共用同一实现（审查条目 A7）。
 * 空值视为 0。
 *
 * 与旧本地实现的可见差异（审查条目 P2-B，均为有意统一）：
 * - 非有限值（NaN/Infinity）由 '-' 变为 '¥0.00'；
 * - 增加千分位分组（旧实现只有 toFixed）；
 * - 负数符号由 '¥-5.00' 改为 '-¥5.00'，符合 docs/DESIGN.md §6 金额规范。
 */
export function formatMoney(value?: unknown): string {
  return formatMoneyText(value);
}

/**
 * 大额金额紧凑格式化：≥1 万折算为「万」单位，用于指标卡等窄容器展示。
 *
 * 空值口径与 formatMoney 有意不同：列表/详情里的金额是账务事实，空值按 0 显示为 ¥0.00；
 * 指标卡是聚合统计，非有限值（NaN/Infinity）说明上游数据异常，显示 '-' 才能与「真的是 0」区分。
 */
export function formatCompactMoney(value?: unknown): string {
  const amount = Number(value ?? 0);
  if (!Number.isFinite(amount)) return '-';
  if (Math.abs(amount) >= 10000) {
    return `¥${(amount / 10000).toFixed(2)}万`;
  }
  return `¥${formatMoneyAmount(amount)}`;
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
