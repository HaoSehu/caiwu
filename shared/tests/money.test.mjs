/**
 * 金额格式化测试（shared/finance/money.ts）
 * 运行: node --experimental-strip-types ./tests/money.test.mjs
 *
 * 覆盖三端共用口径（docs/DESIGN.md §6 金额与货币）：
 * 半角 ¥ 紧贴数值、千分位分组、固定两位小数、负数符号在 ¥ 之前。
 */
import assert from 'node:assert/strict'

import { formatMoneyAmount, formatMoneyText } from '../finance/money.ts'

// 1. 整数与小数：固定两位小数
{
  assert.equal(formatMoneyAmount(0), '0.00', '零值应保留两位小数')
  assert.equal(formatMoneyAmount(5), '5.00', '整数应补足两位小数')
  assert.equal(formatMoneyAmount(12.3), '12.30', '一位小数应补足两位')
  assert.equal(formatMoneyAmount(12.345), '12.35', '多余小数按四舍五入保留两位')
}

// 2. 千分位分组
{
  assert.equal(formatMoneyAmount(1234.5), '1,234.50', '四位数应带千分位分隔符')
  assert.equal(formatMoneyAmount(1234567.89), '1,234,567.89', '大额应每三位分组')
}

// 3. 负数：数值版保留符号，文本版符号排在 ¥ 之前
{
  assert.equal(formatMoneyAmount(-12.5), '-12.50', '数值版负号在数字前')
  assert.equal(formatMoneyText(-12.5), '-¥12.50', '文本版负号必须排在 ¥ 之前')
  assert.equal(formatMoneyText(12.5), '¥12.50', '正数文本版为 ¥ 紧贴数值')
  assert.equal(formatMoneyText(-1234.5), '-¥1,234.50', '负数大额同样千分位且符号前置')
}

// 4. 文本版半角 ¥ 紧贴数值，且不带多余空格
{
  assert.equal(formatMoneyText(0), '¥0.00', '零值文本版应为 ¥0.00')
  assert.equal(formatMoneyText(1).includes('¥ '), false, '¥ 与数值之间不得有空格')
}

// 5. 异常输入兜底：不抛错、不输出 NaN
{
  assert.equal(formatMoneyAmount(undefined), '0.00', 'undefined 应兜底为 0.00')
  assert.equal(formatMoneyAmount(null), '0.00', 'null 应兜底为 0.00')
  assert.equal(formatMoneyAmount(''), '0.00', '空字符串应兜底为 0.00')
  assert.equal(formatMoneyAmount('abc'), '0.00', '非数字字符串应兜底为 0.00')
  assert.equal(formatMoneyAmount(Number.NaN), '0.00', 'NaN 应兜底为 0.00')
  assert.equal(formatMoneyAmount(Number.POSITIVE_INFINITY), '0.00', 'Infinity 应兜底为 0.00')
  assert.equal(formatMoneyText(Number.NaN), '¥0.00', '文本版 NaN 应兜底为 ¥0.00')
}

// 6. 数字字符串按数值处理（接口多以下发字符串金额）
{
  assert.equal(formatMoneyAmount('1234.5'), '1,234.50', '字符串金额应按数值格式化')
  assert.equal(formatMoneyText('-1234.5'), '-¥1,234.50', '字符串负数应按负数格式化')
}

console.log('money tests passed')
