/**
 * 统一错误提示信息提取。
 *
 * 实现已上提到 `@caiwu/shared/runtime`（三端共用一份），本文件只做 re-export，
 * 保证既有 `import { errorMessage } from '@/utils/userMessage'` 不用改动。
 * 新增页面请直接使用本文件导出的 errorMessage，禁止本地重写。
 */
export { errorMessage, isTrustedChineseMessage, toUserMessage, validationErrorMessage } from '@caiwu/shared/runtime';
