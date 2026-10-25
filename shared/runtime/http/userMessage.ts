export function isTrustedChineseMessage(message: unknown) {
  const text = String(message || "").trim();
  return text !== "" && /[\u4e00-\u9fa5]/.test(text);
}

/**
 * 仅信任含中文的服务端消息，其余（英文框架错误、空值）回退到默认提示。
 */
export function toUserMessage(
  message: unknown,
  fallback = "操作失败，请稍后重试",
) {
  return isTrustedChineseMessage(message) ? String(message).trim() : fallback;
}

interface ErrorPayload {
  data?: unknown;
  errors?: unknown;
  response?: { data?: unknown };
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === "object" && value !== null;
}

function firstTrustedMessage(value: unknown): string | null {
  if (Array.isArray(value)) {
    for (const item of value) {
      const message = firstTrustedMessage(item);
      if (message) return message;
    }

    return null;
  }

  if (isTrustedChineseMessage(value)) {
    return String(value).trim();
  }

  if (isRecord(value)) {
    for (const item of Object.values(value)) {
      const message = firstTrustedMessage(item);
      if (message) return message;
    }
  }

  return null;
}

function getValidationErrors(payload: unknown): unknown {
  if (!isRecord(payload)) return undefined;

  const nestedData = payload.data;
  if (isRecord(nestedData) && Reflect.has(nestedData, "errors")) {
    return nestedData.errors;
  }

  if (Reflect.has(payload, "errors")) {
    return payload.errors;
  }

  return undefined;
}

/**
 * 从 Laravel 校验错误里提取第一条可信中文提示。
 * 依次尝试 response.data、data、error 本身，命中即返回，否则 null。
 */
export function validationErrorMessage(error: unknown): string | null {
  const payload = error as ErrorPayload;
  const candidates = [payload?.response?.data, payload?.data, error];

  for (const candidate of candidates) {
    const message = firstTrustedMessage(getValidationErrors(candidate));
    if (message) return message;
  }

  return null;
}

/**
 * 统一错误提示信息提取。
 * 优先用字段级验证错误，其次用可信的中文后端消息，否则回退 fallback。
 * 三端（管理端 / 官网 / 用户控制台）均应复用本函数，不要在页面里本地重写。
 */
export function errorMessage(
  error: { message?: unknown } | unknown,
  fallback = "操作失败，请稍后重试",
): string {
  const validation = validationErrorMessage(error);
  if (validation) return validation;

  const message = (error as { message?: unknown })?.message;
  return toUserMessage(message, fallback);
}
