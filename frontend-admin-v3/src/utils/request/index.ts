// 管理端请求入口：传输、重试、信封校验统一由 @caiwu/shared 的 createHttpClient 提供，
// 本文件只保留管理端历史调用约定（对象式参数、GET 防缓存时间戳、非 GET 的 params 兜底、错误静默）。
import type { ClientConfigExtras } from '@caiwu/shared/runtime';
import { createHttpClient } from '@caiwu/shared/runtime';
import type { AxiosRequestConfig } from 'axios';

import { getAdminToken, removeAdminToken } from '@/app/runtime/session';
import router from '@/router';

const host = String(import.meta.env.VITE_API_BASE_URL || '')
  .trim()
  .replace(/\/+$/, '');

if (!host) {
  throw new Error('VITE_API_BASE_URL 必须配置');
}

/**
 * 会话失效统一处理：清本地 token 并跳登录页，同时保留当前页面作为 redirect，
 * 登录后可回到原页面。已在登录页时不再导航，避免 vue-router 冗余导航告警。
 */
function redirectToLoginOnAuthFailure() {
  removeAdminToken();
  const current = router.currentRoute.value;
  if (current.path === '/admin/login') return;
  router.push({
    path: '/admin/login',
    query: { redirect: encodeURIComponent(current.fullPath) },
  });
}

const httpClient = createHttpClient({
  baseURL: host,
  // 管理端错误提示由各页面 catch 后调用 errorMessage() 自行完成，
  // 拦截器保持静默，避免与页面提示重复弹窗。
  showError: () => {},
  onUnauthorized: () => redirectToLoginOnAuthFailure(),
  resolveToken: () => getAdminToken(),
  // 历史实现按解包后的业务数据使用，保持返回 data 而非完整信封。
  unwrapEnvelope: true,
  // 管理端页面兜底文案依赖「原始错误文案不是中文」这条判断：
  // 传输层错误保留 axios 原始文案（如 Network Error），errorMessage() 才会回退到页面场景文案
  // （如「导出数据库备份失败」）；否则会被统一中文提示覆盖成「网络异常」。
  preserveErrorMessage: true,
});

/** 管理端请求配置：axios 配置 + 共享客户端扩展项 + 本项目的时间戳开关。 */
export interface AdminRequestConfig extends AxiosRequestConfig, ClientConfigExtras {
  /** 是否给 GET 追加防缓存时间戳，默认 true */
  joinTime?: boolean;
}

/** GET 追加 _t 时间戳防缓存，沿用历史行为（e2e 的通配符匹配依赖它）。 */
function withCacheBuster(config: AdminRequestConfig): AdminRequestConfig {
  if (config.joinTime === false) return config;

  const params = config.params;
  return {
    ...config,
    params: { ...(params && typeof params === 'object' ? params : {}), _t: Date.now() },
  };
}

function isNonEmptyBody(data: unknown): boolean {
  if (!data) return false;
  if (data instanceof FormData) return true;
  return typeof data === 'object' && Object.keys(data as Record<string, unknown>).length > 0;
}

/**
 * 非 GET 请求沿用历史兜底：调用方只传 params 时，把 params 当作请求体，
 * 避免 body 为空导致后端 FormRequest 校验失败。已有请求体时两者并存。
 */
function withBodyFallback(config: AdminRequestConfig): AdminRequestConfig {
  const params = config.params;
  if (isNonEmptyBody(config.data) || !params || typeof params !== 'object') {
    return config;
  }

  return { ...config, data: params, params: undefined };
}

/** FormData 上传必须显式声明 multipart，共享客户端不会猜测文件体。 */
function withFormDataHeaders(config: AdminRequestConfig): AdminRequestConfig {
  if (!(config.data instanceof FormData)) return config;

  return {
    ...config,
    headers: { ...config.headers, 'Content-Type': 'multipart/form-data' },
  };
}

const DEFAULT_SAFE_RETRY_COUNT = 3;

function send<T>(method: 'get' | 'post' | 'put' | 'patch' | 'delete', config: AdminRequestConfig): Promise<T> {
  const normalized = method === 'get' ? withCacheBuster(config) : withFormDataHeaders(withBodyFallback(config));

  const payload: AdminRequestConfig = {
    ...normalized,
    method,
    // 历史默认 GET 失败重试 3 次；非幂等请求由 shared 客户端自动跳过。
    safeRetryCount: normalized.safeRetryCount ?? DEFAULT_SAFE_RETRY_COUNT,
  };

  // shared 客户端已按 unwrapEnvelope 解包，运行时返回的就是业务数据；
  // axios 的泛型推导无法表达这一点，这里在入口处收敛为一个断言。
  return httpClient.request<unknown, T>(payload) as Promise<T>;
}

export const request = {
  get: <T = unknown>(config: AdminRequestConfig) => send<T>('get', config),
  post: <T = unknown>(config: AdminRequestConfig) => send<T>('post', config),
  put: <T = unknown>(config: AdminRequestConfig) => send<T>('put', config),
  patch: <T = unknown>(config: AdminRequestConfig) => send<T>('patch', config),
  delete: <T = unknown>(config: AdminRequestConfig) => send<T>('delete', config),
};
