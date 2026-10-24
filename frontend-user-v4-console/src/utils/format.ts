import { formatMoneyAmount, formatMoneyText as formatSharedMoneyText } from '@caiwu/shared/finance';
import { MessagePlugin } from 'tdesign-vue-next/es/message/plugin';

/**
 * 金额格式化（不含货币符号，¥ 由模板/调用方拼接）。
 *
 * 口径由 shared/finance/money 统一，与管理端、官网共用同一实现（审查条目 A7）：
 * - 非有限数字 → '0.00'
 * - 其余 → 千分位分组 + 两位小数
 */
export function formatMoney(value: unknown): string {
  return formatMoneyAmount(value);
}

/**
 * 带货币符号的金额格式化。
 *
 * 负号必须排在 `¥` 之前（`-¥5.00`），不能写成 `¥-5.00`——
 * 后者是「先拼 ¥ 再让 formatMoney 输出负号」的产物，不符合 docs/DESIGN.md §6 的金额排版。
 * 凡是取值可能为负（流水 change_amount、折扣、账户余额）的展示位一律用本函数，不要手写 `¥` 前缀。
 */
export function formatMoneyText(value: unknown): string {
  return formatSharedMoneyText(value);
}

const pad = (num: number) => String(num).padStart(2, '0');
const DASH_RE = /-/g;

/**
 * 日期时间格式化为 `YYYY-MM-DD HH:mm`。
 * 兼容 `YYYY-MM-DD HH:mm:ss` 与带 `-` 分隔的字符串（Safari 友好）。
 */
export function formatDateTime(value: unknown): string {
  if (!value) return '--';
  const date = new Date(String(value).replace(DASH_RE, '/'));
  if (Number.isNaN(date.getTime())) return String(value);
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

/**
 * 日期时间格式化为 `MM-DD HH:mm`（仪表盘等紧凑场景）。
 */
export function formatShortDateTime(value: unknown): string {
  if (!value) return '--';
  const date = new Date(String(value).replace(DASH_RE, '/'));
  if (Number.isNaN(date.getTime())) return '--';
  return `${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

export interface CopyTextOptions {
  /** 复制成功提示文案；为空则不弹提示 */
  successMsg?: string;
  /** 复制失败提示文案 */
  errorMsg?: string;
}

/**
 * 统一的剪贴板复制能力，收敛自各 domain 内重复实现。
 * 空值/占位符 `--` 直接跳过；可选地弹出成功/失败提示。
 *
 * @returns 是否复制成功
 */
export async function copyText(value: unknown, options: CopyTextOptions = {}): Promise<boolean> {
  const text = String(value ?? '').trim();
  if (!text || text === '--') return false;

  try {
    await navigator.clipboard.writeText(text);
    if (options.successMsg) MessagePlugin.success(options.successMsg);
    return true;
  } catch {
    MessagePlugin.warning(options.errorMsg ?? '复制失败，请手动复制');
    return false;
  }
}
