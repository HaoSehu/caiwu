function isTrustedChineseMessage(message: unknown) {
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
