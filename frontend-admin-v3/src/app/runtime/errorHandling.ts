import { MessagePlugin } from 'tdesign-vue-next/es/message/plugin';
import type { App } from 'vue';

import { errorMessage } from '@/utils/userMessage';

/**
 * 全局未捕获异常兜底。
 *
 * 背景：页面里存在 `try { await api() } finally { loading = false }` 这类漏写 catch 的异步处理器
 * （详见代码审查报告 H-2）。模板事件处理器返回的 Promise 若被拒绝，Vue 不会捕获，
 * 会直接冒泡为 unhandledrejection —— 既不给用户反馈，也不进入任何错误上报。
 *
 * 这里只做「最后一道防线」：记录日志并给出一次通用提示。
 * 正常路径仍应在各自的处理器内 catch 并用 errorMessage 给出精确文案。
 */
export function setupGlobalErrorHandling(app: App) {
  app.config.errorHandler = (error) => {
    console.error('[admin] 未捕获的组件错误:', error);
    MessagePlugin.error(errorMessage(error, '页面出现异常，请刷新后重试'));
  };

  window.addEventListener('unhandledrejection', (event) => {
    console.error('[admin] 未捕获的 Promise 拒绝:', event.reason);
    MessagePlugin.error(errorMessage(event.reason, '操作失败，请稍后重试'));
  });
}
