import axios, {
  type AxiosInstance,
  type InternalAxiosRequestConfig,
} from "axios";
import { getNetworkProfile } from "../networkHints.ts";
import {
  attachSafeRequestDedupe,
  buildSafeRequestKey,
  createRequestId,
  isSafeRequest,
  isWriteRequest,
  retrySafeRequest,
} from "./core.ts";
import { toUserMessage } from "./userMessage.ts";
import { extractValidationErrors } from "./validationErrors.ts";

export const DEFAULT_TIMEOUT = 18000;
export const SAFE_TIMEOUT = 16000;
export const WEAK_NETWORK_TIMEOUT_BOOST = 9000;

export interface ClientConfigExtras {
  safeRetryCount?: number;
  dedupeSafeRequest?: boolean;
  retrySafeRequest?: boolean;
  silentError?: boolean;
  silent?: boolean;
  __safeRequest?: boolean;
  __safeRequestKey?: string;
  __safeRetryCount?: number;
  __skipSafeDedupe?: boolean;
}

export interface ClientRuntimeRequestConfig
  extends InternalAxiosRequestConfig,
    ClientConfigExtras {
  /** 请求发出时携带的凭证，用于 401 时判断该失效是否已经过时（期间重新登录则跳过） */
  __requestToken?: string | null;
}

export interface RuntimeHandledError extends Error {
  __handled?: boolean;
  response?: unknown;
  config?: unknown;
}

export interface CreateHttpClientOptions {
  baseURL: string;
  /** 错误提示回调，由各端注入自己的 UI 实现（含去重） */
  showError: (message: string) => void;
  /**
   * 业务成功时是否直接返回信封中的 data。
   * 默认 false，返回完整信封 { code, message, data }；管理端历史实现按解包后使用，置 true 保持兼容。
   */
  unwrapEnvelope?: boolean;
  /**
   * 传输层错误是否保留原始错误文案。
   * 默认 false：抛出的 Error.message 使用面向用户的统一中文文案（网络异常/请求超时等）。
   * 管理端页面统一在 catch 里调 errorMessage(error, 具体场景兜底文案)，只有当 message 不是
   * 可信中文时才会回退到场景文案；保留原始文案（如 Network Error）才能继续走这段兜底逻辑。
   */
  preserveErrorMessage?: boolean;
  /**
   * 业务 40100 或 HTTP 401 时的统一处理（清 token、跳登录）；未提供则仅抛错。
   * requestToken 为触发 401 的请求发出时携带的凭证：与当前凭证不一致说明期间已重新登录，处理方应忽略该次失效。
   */
  onUnauthorized?: (
    message: string,
    requestToken?: string | null,
  ) => void;
  /** 返回本次请求的 Bearer token；返回空值则不附带 Authorization */
  resolveToken?: (
    config: ClientRuntimeRequestConfig,
  ) => string | null | undefined;
}

function hasExplicitTimeout(config: Partial<ClientRuntimeRequestConfig>) {
  return Number.isFinite(Number(config.timeout)) && Number(config.timeout) > 0;
}

function resolveTimeout(
  config: Partial<ClientRuntimeRequestConfig>,
  safeRequest: boolean,
) {
  if (hasExplicitTimeout(config)) {
    return Number(config.timeout);
  }

  if (!safeRequest) {
    return DEFAULT_TIMEOUT;
  }

  const profile = getNetworkProfile();
  return (
    SAFE_TIMEOUT + (profile.isWeakConnection ? WEAK_NETWORK_TIMEOUT_BOOST : 0)
  );
}

function canDedupeSafeRequest(
  config: Partial<ClientRuntimeRequestConfig>,
  safeRequest: boolean,
) {
  return Boolean(
    safeRequest &&
    config.dedupeSafeRequest !== false &&
    !config.signal &&
    !config.cancelToken,
  );
}

/**
 * 创建统一的后端 API 客户端：信封校验、__handled 语义、silentError、
 * 422/429/401 处理、弱网重试、幂等去重与超时策略全部收敛在此。
 * 各端只需注入 UI 提示、未授权跳转与 token 获取。
 */
export function createHttpClient(
  options: CreateHttpClientOptions,
): AxiosInstance {
  const client = axios.create({
    baseURL: options.baseURL,
    timeout: DEFAULT_TIMEOUT,
    headers: {
      Accept: "application/json",
    },
  });

  client.interceptors.request.use(
    async (config) => {
      const runtimeConfig = config as ClientRuntimeRequestConfig;
      const method = String(runtimeConfig.method || "get").toLowerCase();
      const safeRequest = isSafeRequest(method);
      const writeRequest = isWriteRequest(method);

      runtimeConfig.silentError = Boolean(
        runtimeConfig.silentError || runtimeConfig.silent,
      );
      runtimeConfig.__safeRequest = safeRequest;
      runtimeConfig.timeout = resolveTimeout(runtimeConfig, safeRequest);

      const token = options.resolveToken?.(runtimeConfig);
      if (token) {
        runtimeConfig.headers.Authorization = `Bearer ${token}`;
      }
      runtimeConfig.__requestToken = token || null;

      if (writeRequest && !runtimeConfig.headers["Content-Type"]) {
        runtimeConfig.headers["Content-Type"] = "application/json";
      }

      if (writeRequest) {
        // 调用方显式传入的 X-Request-Id（如退款幂等键）优先，不被随机值覆盖。
        if (!runtimeConfig.headers["X-Request-Id"]) {
          runtimeConfig.headers["X-Request-Id"] = createRequestId();
        }
      }

      if (canDedupeSafeRequest(runtimeConfig, safeRequest)) {
        const sharedConfig = runtimeConfig as ClientRuntimeRequestConfig &
          Record<string, unknown>;
        sharedConfig.__safeRequestKey = buildSafeRequestKey(sharedConfig);
        attachSafeRequestDedupe(sharedConfig, client.defaults.adapter);
      }

      return runtimeConfig;
    },
    (error) => Promise.reject(error),
  );

  client.interceptors.response.use(
    (response) => {
      const res = response.data;
      const runtimeConfig = response.config as ClientRuntimeRequestConfig;

      if (res.code !== 0) {
        const msg = toUserMessage(res.message, "请求失败");
        const captchaRequired = Boolean(res.data?.captcha_required);

        if (res.code === 40100) {
          options.onUnauthorized?.(msg, runtimeConfig.__requestToken);
          return Promise.reject(new Error(msg));
        }

        const shownByInterceptor =
          !captchaRequired && !runtimeConfig?.silentError;
        if (shownByInterceptor) {
          options.showError(msg);
        }

        const err = new Error(msg) as RuntimeHandledError;
        // __handled 表示提示已经由拦截器或验证码流程接管，调用方据此避免重复弹窗。
        err.__handled = shownByInterceptor || captchaRequired;
        err.response = { ...response, data: res };
        err.config = response.config;
        return Promise.reject(err);
      }

      if (options.unwrapEnvelope) {
        return res.data;
      }

      return res;
    },
    async (error) => {
      if (
        axios.isCancel(error) ||
        error?.code === "ERR_CANCELED" ||
        error?.name === "CanceledError"
      ) {
        return Promise.reject(error);
      }

      try {
        return await retrySafeRequest((config) => client(config), error);
      } catch (retryError) {
        if (retryError !== error) {
          return Promise.reject(retryError);
        }
      }

      let msg = toUserMessage(error.response?.data?.message, "网络异常");

      if (typeof navigator !== "undefined" && navigator.onLine === false) {
        msg = "网络连接已断开，请检查后重试";
      } else if (
        error.code === "ECONNABORTED" ||
        String(error.message || "")
          .toLowerCase()
          .includes("timeout")
      ) {
        msg = "请求超时，请检查网络后重试";
      }

      if (error.response?.status === 422) {
        const errors = extractValidationErrors(error.response?.data);
        const trustedErrors = errors
          .map((item) => toUserMessage(item, ""))
          .filter(Boolean);
        if (trustedErrors.length > 0) {
          msg = trustedErrors.join(", ");
        } else {
          // 业务异常（BusinessException）也走 422，message 里有真实原因，优先展示
          const serverMsg = toUserMessage(error.response?.data?.message, "");
          msg = serverMsg || "参数填写有误，请检查后重试";
        }
      }

      if (error.response?.status === 429) {
        const retryAfter = Number(
          error.response?.headers?.["retry-after"] || 0,
        );
        msg =
          retryAfter > 0
            ? `请求过于频繁，请 ${retryAfter} 秒后重试`
            : "请求过于频繁，请稍后重试";
      }

      if (error.response?.status === 401) {
        const requestToken = (error.config || {})?.__requestToken;
        // 401 会把原始 axios 错误直接抛给调用方，这里必须把后端中文文案写回 error.message，
        // 否则调用方拿到的就是 "Request failed with status code 401" 这类英文原文。
        error.message = msg;
        options.onUnauthorized?.(msg, requestToken);
        return Promise.reject(error);
      }

      const runtimeConfig = (error.config || {}) as ClientRuntimeRequestConfig;
      const shownByInterceptor = !runtimeConfig.silentError;
      if (shownByInterceptor) {
        options.showError(msg);
      }

      // 保留原始文案只在「没有响应」时才有意义（断网/超时）：此时 axios 的英文文案不含中文，
      // 调用方的 errorMessage(error, 场景兜底) 才会回退到场景文案；一旦服务端给了消息，
      // 就必须用拦截器算好的 msg，否则 404/422 这类业务提示会被英文原文挤掉。
      const err = new Error(
        options.preserveErrorMessage && !error.response
          ? String(error?.message || msg)
          : msg,
      ) as RuntimeHandledError;
      // silentError 的请求由调用方自行提示，这里不能谎报已处理，否则错误会被静默吞掉。
      err.__handled = shownByInterceptor;
      err.response = error.response;
      err.config = error.config;
      return Promise.reject(err);
    },
  );

  return client;
}
