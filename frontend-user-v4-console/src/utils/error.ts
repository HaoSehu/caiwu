import { toUserMessage } from '@caiwu/shared/runtime';

/**
 * 统一的错误消息提取工具。
 *
 * 收敛自 12 处分散的 getErrorMessage 定义。
 * 行为：提取 Error.message；仅信任含中文的消息（后端 message / data.errors），
 * 英文原始异常（网络超时、TypeError 等第三方错误）一律回退到统一的中文提示，
 * 避免英文异常直接透出到页面错误态（对齐 docs/FRONTEND.md §2 文案规范）。
 */
export function getErrorMessage(error: unknown, fallback: string): string {
  if (error instanceof Error && error.message) return toUserMessage(error.message, fallback);
  if (
    typeof error === 'object' &&
    error !== null &&
    'message' in error &&
    typeof (error as Record<string, unknown>).message === 'string'
  ) {
    return toUserMessage((error as Record<string, unknown>).message, fallback);
  }
  return fallback;
}
