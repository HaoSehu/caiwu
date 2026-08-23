/**
 * Sanctum SPA 模式 CSRF 握手。
 *
 * 后端 api 中间件组启用了 Sanctum 的 EnsureFrontendRequestsAreStateful，
 * 本域（stateful）发出的写请求必须携带 X-XSRF-TOKEN 头（axios 会自动从
 * XSRF-TOKEN cookie 读取并解码）。因此写请求前必须先 GET /sanctum/csrf-cookie
 * 让浏览器持有该 cookie，否则后端返回 419。
 *
 * 通过原生 fetch 发起，避免经过 axios 拦截器造成递归；获取失败不抛错，
 * 由后续业务请求自身暴露错误。
 */

const CSRF_COOKIE_NAME = "XSRF-TOKEN";
const CSRF_COOKIE_URL_SUFFIX = "/sanctum/csrf-cookie";
const CSRF_FETCH_TIMEOUT_MS = 5000;

let pendingFetch: Promise<void> | null = null;

/** 从 baseURL（通常以 /api 结尾）推导 csrf-cookie 完整地址。 */
export function resolveSanctumCsrfCookieUrl(baseURL: string): string {
  const trimmed = String(baseURL || "").trim().replace(/\/+$/, "");
  return `${trimmed.replace(/\/api$/, "")}${CSRF_COOKIE_URL_SUFFIX}`;
}

function hasXsrfCookie(): boolean {
  return (
    typeof document !== "undefined" &&
    document.cookie.includes(`${CSRF_COOKIE_NAME}=`)
  );
}

/**
 * 确保浏览器已持有 XSRF-TOKEN cookie（写请求前调用，幂等并发安全）。
 * 非浏览器环境（SSR / node 测试）与实际已持 cookie 时直接跳过。
 */
export async function ensureSanctumCsrfCookie(
  baseURL: string,
  withCredentials: boolean,
): Promise<void> {
  // 非浏览器环境（SSR / node 测试）没有 cookie 概念，直接跳过
  if (typeof document === "undefined" || hasXsrfCookie()) {
    return;
  }
  if (pendingFetch) {
    return pendingFetch;
  }

  pendingFetch = (async () => {
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), CSRF_FETCH_TIMEOUT_MS);
    try {
      await fetch(resolveSanctumCsrfCookieUrl(baseURL), {
        method: "GET",
        credentials: withCredentials ? "include" : "same-origin",
        signal: controller.signal,
      });
    } catch {
      // 握手失败不阻断主请求；后端校验不过时会按 419 正常报错
    } finally {
      clearTimeout(timer);
      pendingFetch = null;
    }
  })();

  return pendingFetch;
}