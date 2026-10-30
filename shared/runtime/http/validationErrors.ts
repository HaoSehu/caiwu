function isRecord(value: unknown): value is Record<string, unknown> {
  return value !== null && typeof value === 'object' && !Array.isArray(value)
}

export function extractValidationErrors(payload: unknown): string[] {
  if (!isRecord(payload)) {
    return []
  }

  const nestedData = isRecord(payload.data) ? payload.data : {}
  const errors = isRecord(nestedData.errors)
    ? nestedData.errors
    : (isRecord(payload.errors) ? payload.errors : {})

  return Object.values(errors)
    .flatMap((value) => (Array.isArray(value) ? value : [value]))
    .map((value) => String(value || '').trim())
    .filter(Boolean)
}

/**
 * 提取字段级校验错误，保留「字段名 -> 错误列表」结构。
 *
 * extractValidationErrors 只返回打平后的文案，表单一类场景需要按字段定位，
 * 因此这里保留键名；两种数据形态（data.errors 与顶层 errors）都与打平版本保持一致。
 */
export function extractValidationErrorMap(payload: unknown): Record<string, string[]> {
  if (!isRecord(payload)) {
    return {}
  }

  const nestedData = isRecord(payload.data) ? payload.data : {}
  const errors = isRecord(nestedData.errors)
    ? nestedData.errors
    : (isRecord(payload.errors) ? payload.errors : {})

  const result: Record<string, string[]> = {}
  Object.entries(errors).forEach(([field, value]) => {
    const messages = (Array.isArray(value) ? value : [value])
      .map((item) => String(item || '').trim())
      .filter(Boolean)
    if (messages.length > 0) {
      result[field] = messages
    }
  })
  return result
}
