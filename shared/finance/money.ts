/**
 * 金额展示的跨端单一实现。
 *
 * 背景（docs/DESIGN.md 金额与货币规范）：
 * - 半角 ¥ 紧贴数值，负数符号必须在 ¥ 之前（-¥5.00）；
 * - 千分位分组、两位小数；
 * - 金额列配合 tabular-nums，禁止截断。
 *
 * 管理端、用户控制台与官网此前各自维护 toFixed / toLocaleString 版本，
 * 小数位、千分位、异常值兜底三处口径不一（审查条目 A7）。三端一律从本模块取数。
 */

/** 金额数值部分：千分位 + 两位小数，不含货币符号。非有限数值按 0 处理。 */
export function formatMoneyAmount(value: unknown): string {
  const amount = Number(value ?? 0);
  if (!Number.isFinite(amount)) return '0.00';
  return amount.toLocaleString('zh-CN', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
}

/** 带货币符号的金额：¥1,234.56；负数为 -¥1,234.56（符号顺序固定）。 */
export function formatMoneyText(value: unknown): string {
  const amount = Number(value ?? 0);
  if (!Number.isFinite(amount)) return '¥0.00';
  return `${amount < 0 ? '-' : ''}¥${formatMoneyAmount(Math.abs(amount))}`;
}
