export function isTrustedChineseMessage(message: unknown) {
  const text = String(message || '').trim();
  return text !== '' && /[\u4e00-\u9fa5]/.test(text);
}

export function toUserMessage(message: unknown, fallback = '操作失败，请稍后重试') {
  return isTrustedChineseMessage(message) ? String(message).trim() : fallback;
}

/**
 * 统一错误提示信息提取。
 * 优先用可信的中文后端消息，否则回退 fallback。
 * 所有页面应使用本函数，禁止本地重写 errorMessage。
 */
export function errorMessage(error: { message?: unknown } | unknown, fallback = '操作失败，请稍后重试'): string {
  const message = (error as { message?: unknown })?.message;
  return toUserMessage(message, fallback);
}
