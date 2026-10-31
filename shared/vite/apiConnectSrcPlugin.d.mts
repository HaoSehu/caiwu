import type { Plugin } from "vite";

/**
 * 把开发/构建期真实的 API 源站补进 index.html 的 CSP 指令。
 * 仅当 `VITE_API_BASE_URL` 为 http 源时生效（生产 https 已被 CSP 的 `https:` 覆盖）。
 *
 * script-src 没有 `https:` 兜底，控制台的极验脚本来自 API 源站，
 * 调用方需显式传 `scriptDirective: "script-src"` 才会补进 script-src。
 */
export function createApiConnectSrcPlugin(
  env: Record<string, string | undefined>,
  options?: { placeholder?: string; directives?: string[]; scriptDirective?: string },
): Plugin;
