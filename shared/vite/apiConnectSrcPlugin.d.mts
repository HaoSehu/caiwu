import type { Plugin } from "vite";

/**
 * 把开发/构建期真实的 API 源站补进 index.html 的 CSP connect-src。
 * 仅当 `VITE_API_BASE_URL` 为 http 源时生效（生产 https 已被 CSP 的 `https:` 覆盖）。
 */
export function createApiConnectSrcPlugin(
  env: Record<string, string | undefined>,
  options?: { placeholder?: string },
): Plugin;
