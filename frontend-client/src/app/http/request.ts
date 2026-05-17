import axios, { type InternalAxiosRequestConfig } from 'axios'
import { ElMessage } from 'element-plus'
import router from '@/router'
import {
  attachSafeRequestDedupe,
  buildSafeRequestKey,
  createRequestId,
  getNetworkProfile,
  isSafeRequest,
  isWriteRequest,
  retrySafeRequest,
} from '@caiwu/shared/runtime'
import { getClientToken, removeClientToken } from '@/app/runtime/session'

const DEFAULT_TIMEOUT = 18000
const SAFE_TIMEOUT = 16000
const WEAK_NETWORK_TIMEOUT_BOOST = 9000

interface ClientRuntimeRequestConfig extends InternalAxiosRequestConfig {
  safeRetryCount?: number
  dedupeSafeRequest?: boolean
  retrySafeRequest?: boolean
  silentError?: boolean
  silent?: boolean
  __safeRequest?: boolean
  __safeRequestKey?: string
  __safeRetryCount?: number
  __skipSafeDedupe?: boolean
}

interface RuntimeHandledError extends Error {
  __handled?: boolean
  response?: unknown
  config?: unknown
}

let lastErrorMsg = ''
let lastErrorTimer: ReturnType<typeof setTimeout> | null = null
let redirectingToLogin = false

function showError(msg: string) {
  if (msg === lastErrorMsg) {
    return
  }

  lastErrorMsg = msg
  ElMessage.error(msg)
  if (lastErrorTimer) {
    clearTimeout(lastErrorTimer)
  }
  lastErrorTimer = setTimeout(() => {
    lastErrorMsg = ''
  }, 1000)
}

function redirectLogin() {
  if (redirectingToLogin) {
    return
  }

  redirectingToLogin = true
  removeClientToken()
  router.push('/client/login').finally(() => {
    redirectingToLogin = false
  })
}

function hasExplicitTimeout(config: Partial<ClientRuntimeRequestConfig>) {
  return Number.isFinite(Number(config.timeout)) && Number(config.timeout) > 0
}

function resolveTimeout(config: Partial<ClientRuntimeRequestConfig>, safeRequest: boolean) {
  if (hasExplicitTimeout(config)) {
    return Number(config.timeout)
  }

  if (!safeRequest) {
    return DEFAULT_TIMEOUT
  }

  const profile = getNetworkProfile()
  return SAFE_TIMEOUT + (profile.isWeakConnection ? WEAK_NETWORK_TIMEOUT_BOOST : 0)
}

function canDedupeSafeRequest(config: Partial<ClientRuntimeRequestConfig>, safeRequest: boolean) {
  return Boolean(
    safeRequest
    && config.dedupeSafeRequest !== false
    && !config.signal
    && !config.cancelToken
  )
}

const request = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || '/api',
  timeout: DEFAULT_TIMEOUT,
  headers: {
    Accept: 'application/json',
  },
})

request.interceptors.request.use(
  (config) => {
    const runtimeConfig = config as ClientRuntimeRequestConfig
    const method = String(runtimeConfig.method || 'get').toLowerCase()
    const safeRequest = isSafeRequest(method)
    const writeRequest = isWriteRequest(method)
    const requestUrl = String(runtimeConfig.url || '')
    const isPublicSiteGetRequest = method === 'get' && requestUrl.startsWith('/site/')
    const token = getClientToken()

    runtimeConfig.headers = runtimeConfig.headers
    runtimeConfig.silentError = Boolean(runtimeConfig.silentError || runtimeConfig.silent)
    runtimeConfig.__safeRequest = safeRequest
    runtimeConfig.timeout = resolveTimeout(runtimeConfig, safeRequest)

    if (token && !isPublicSiteGetRequest) {
      runtimeConfig.headers.Authorization = `Bearer ${token}`
    }

    if (writeRequest && !runtimeConfig.headers['Content-Type']) {
      runtimeConfig.headers['Content-Type'] = 'application/json'
    }

    if (writeRequest) {
      runtimeConfig.headers['X-Request-Id'] = createRequestId()
    }

    if (canDedupeSafeRequest(runtimeConfig, safeRequest)) {
      runtimeConfig.__safeRequestKey = buildSafeRequestKey(runtimeConfig as any)
      attachSafeRequestDedupe(runtimeConfig as any, request.defaults.adapter)
    }

    return runtimeConfig
  },
  (error) => Promise.reject(error)
)

request.interceptors.response.use(
  (response) => {
    const res = response.data
    const runtimeConfig = response.config as ClientRuntimeRequestConfig

    if (res.code !== 0) {
      const msg = res.message || '请求失败'
      const captchaRequired = Boolean(res.data?.captcha_required)

      if (res.code === 40100) {
        redirectLogin()
        return Promise.reject(new Error(msg))
      }

      if (!captchaRequired && !runtimeConfig?.silentError) {
        showError(msg)
      }

      const err = new Error(msg) as RuntimeHandledError
      err.__handled = captchaRequired
      err.response = { ...response, data: res }
      err.config = response.config
      return Promise.reject(err)
    }

    return res
  },
  async (error) => {
    if (axios.isCancel(error) || error?.code === 'ERR_CANCELED' || error?.name === 'CanceledError') {
      return Promise.reject(error)
    }

    try {
      return await retrySafeRequest((config) => request(config), error)
    } catch (retryError) {
      if (retryError !== error) {
        return Promise.reject(retryError)
      }
    }

    let msg = error.response?.data?.message || error.message || '网络异常'

    if (typeof navigator !== 'undefined' && navigator.onLine === false) {
      msg = '网络连接已断开，请检查后重试'
    } else if (error.code === 'ECONNABORTED' || String(error.message || '').toLowerCase().includes('timeout')) {
      msg = '请求超时，请检查网络后重试'
    }

    if (error.response?.status === 422 && error.response?.data?.errors) {
      const errors = Object.values(error.response.data.errors).flat()
      msg = errors.join(', ')
    }

    if (error.response?.status === 429) {
      const retryAfter = Number(error.response?.headers?.['retry-after'] || 0)
      msg = retryAfter > 0
        ? `请求过于频繁，请 ${retryAfter} 秒后重试`
        : '请求过于频繁，请稍后重试'
    }

    if (error.response?.status === 401) {
      redirectLogin()
      return Promise.reject(error)
    }

    const runtimeConfig = (error.config || {}) as ClientRuntimeRequestConfig
    if (!runtimeConfig.silentError) {
      showError(msg)
    }

    const err = new Error(msg) as RuntimeHandledError
    err.__handled = true
    err.response = error.response
    err.config = error.config
    return Promise.reject(err)
  }
)

export default request
