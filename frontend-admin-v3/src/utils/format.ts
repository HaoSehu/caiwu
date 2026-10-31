import { formatMoneyAmount, formatMoneyText } from '@caiwu/shared/finance';
import { MessagePlugin } from 'tdesign-vue-next/es/message/plugin';

/**
 * 通用格式化工具。所有业务页面应从本模块导入，禁止本地重写。
 */

/** 通用日期时间格式化：YYYY-MM-DD HH:mm:ss；空值返回 '-'，无法解析的值原样返回 */
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
 * 结束时间：后端按状态下发——已支付 / 已退款 / 已取消都是完整时间，待支付不下发。
 * 展示口径与全站时间一致（YYYY-MM-DD HH:mm:ss），空值占位。
 */
export function formatFinishTime(value?: unknown): string {
  return formatDateTime(value);
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
 * 指标卡金额格式化：完整金额（千分位 + 两位小数），不做「万」缩写。
 *
 * 口径见 docs/DESIGN.md §4.3：金额必须完整显示、不得截断，聚合指标同理，
 * 因此不再折算「万」单位（旧实现 ≥1 万显示「¥12.34万」，与明细口径不一致，易误读）。
 * 非有限值（NaN/Infinity）说明上游数据异常，显示 '-' 才能与「真的是 0」区分。
 */
export function formatMetricMoney(value?: unknown): string {
  const amount = Number(value ?? 0);
  if (!Number.isFinite(amount)) return '-';
  return `¥${formatMoneyAmount(amount)}`;
}

/** 字段值兜底：空字符串/undefined/null 显示 '-'，其余转为字符串返回 */
export function fieldValue(value?: unknown): string {
  if (value === '' || value === undefined || value === null) return '-';
  return String(value);
}

/** 取值收敛为普通对象：非对象（含 null/数组以外的原始值）返回空对象，避免模板里反复判空 */
export function toRecord(value: unknown): Record<string, unknown> {
  return value && typeof value === 'object' ? (value as Record<string, unknown>) : {};
}

/** JSON 美化：字符串先尝试解析再缩进，解析失败按去空白后的原文返回；其余类型直接 stringify */
export function formatJson(value: unknown, fallback = '-'): string {
  if (value === null || value === undefined || value === '') return fallback;
  if (typeof value === 'string') {
    const text = value.trim();
    if (!text) return fallback;
    try {
      return JSON.stringify(JSON.parse(text), null, 2);
    } catch {
      return text;
    }
  }
  try {
    return JSON.stringify(value, null, 2);
  } catch {
    return fallback;
  }
}

/** 数字千分位：空值按 0 展示，非有限数字回落原文展示（避免出现 'NaN'） */
export function numberText(value: unknown): string {
  const number = Number(value ?? 0);
  return Number.isFinite(number) ? number.toLocaleString('zh-CN') : fieldValue(value);
}

/** 计费周期中文映射（与用户控制台同口径：一个月 / 三个月 / 半年 / 一年 / 按量） */
const BILLING_CYCLE_LABELS: Record<string, string> = {
  monthly: '一个月',
  quarterly: '三个月',
  semi_annual: '半年',
  semi_annually: '半年',
  semiannually: '半年',
  annually: '一年',
  yearly: '一年',
  biennially: '两年',
  triennially: '三年',
  onetime: '按量',
  one_time: '按量',
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
