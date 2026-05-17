import axios, { type InternalAxiosRequestConfig } from 'axios'
import { ElMessage } from 'element-plus'
import router from '@/router'
import { createRequestId, isRetryableNetworkError } from '@caiwu/shared/runtime'
import { getAdminToken, removeAdminToken } from '@/app/runtime/session'

const DEFAULT_TIMEOUT = 15000
const WEAK_NETWORK_TIMEOUT = 22000
const SAFE_RETRY_METHODS = new Set(['get', 'head', 'options'])
const RETRYABLE_STATUS_CODES = new Set([408, 425, 429, 500, 502, 503, 504])

interface AdminRuntimeRequestConfig extends InternalAxiosRequestConfig {
  retry?: number
  retryDelay?: number
  __retryCount?: number
  silent?: boolean
}

function resolveAdaptiveTimeout(baseTimeout = DEFAULT_TIMEOUT) {
  if (typeof navigator === 'undefined') {
    return baseTimeout
  }

  const connection = (navigator as Navigator & {
    connection?: {
      saveData?: boolean
      effectiveType?: string
    }
  }).connection

  if (!connection) {
    return baseTimeout
  }

  if (connection.saveData) {
    return Math.max(baseTimeout, WEAK_NETWORK_TIMEOUT)
  }

  return ['slow-2g', '2g', '3g'].includes(String(connection.effectiveType || ''))
    ? Math.max(baseTimeout, WEAK_NETWORK_TIMEOUT)
    : baseTimeout
}

function resolveRetryLimit(config: Partial<AdminRuntimeRequestConfig> = {}) {
  if (typeof config.retry === 'number') {
    return Math.max(0, config.retry)
  }

  const method = String(config.method || 'get').toLowerCase()
  return SAFE_RETRY_METHODS.has(method) ? 2 : 0
}

function shouldRetry(error: any) {
  const config = (error.config || {}) as Partial<AdminRuntimeRequestConfig>
  const method = String(config.method || 'get').toLowerCase()

  if (!SAFE_RETRY_METHODS.has(method)) {
    return false
  }

  if (config.signal?.aborted || error.code === 'ERR_CANCELED') {
    return false
  }

  const retriedCount = config.__retryCount || 0
  if (retriedCount >= resolveRetryLimit(config)) {
    return false
  }

  const status = error.response?.status
  if (status) {
    return RETRYABLE_STATUS_CODES.has(status)
  }

  return isRetryableNetworkError(error)
}

function wait(ms: number) {
  return new Promise((resolve) => {
    window.setTimeout(resolve, ms)
  })
}

const request = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || '/api',
  timeout: DEFAULT_TIMEOUT,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
})

request.interceptors.request.use(
  (config) => {
    const token = getAdminToken()
    const runtimeConfig = config as AdminRuntimeRequestConfig

    if (token) {
      config.headers.Authorization = `Bearer ${token}`
    }

    runtimeConfig.timeout = resolveAdaptiveTimeout(runtimeConfig.timeout || DEFAULT_TIMEOUT)
    if (runtimeConfig.retry === undefined && SAFE_RETRY_METHODS.has(String(runtimeConfig.method || 'get').toLowerCase())) {
      runtimeConfig.retry = 2
    }

    config.headers['X-Request-Id'] = createRequestId()
    return config
  },
  (error) => Promise.reject(error)
)

request.interceptors.response.use(
  (response) => {
    const res = response.data
    const silent = (response.config as AdminRuntimeRequestConfig | undefined)?.silent === true

    if (res.code !== 0) {
      if (!silent) {
        ElMessage.error(res.message || '请求失败')
      }

      if (res.code === 40100) {
        removeAdminToken()
        router.push('/admin/login')
      }

      return Promise.reject(new Error(res.message || '请求失败'))
    }

    return res
  },
  (error) => {
    if (shouldRetry(error)) {
      const config = (error.config || {}) as AdminRuntimeRequestConfig
      config.__retryCount = (config.__retryCount || 0) + 1
      const retryDelay = Math.min(
        typeof config.retryDelay === 'number' ? config.retryDelay * config.__retryCount : 300 * config.__retryCount,
        1200
      )

      return wait(retryDelay).then(() => request(config))
    }

    let msg = error.response?.data?.message || error.message || '网络异常'
    const silent = (error.config as AdminRuntimeRequestConfig | undefined)?.silent === true

    if (error.response?.status === 422 && error.response?.data?.errors) {
      const errors = Object.values(error.response.data.errors).flat()
      msg = errors.join(', ')
    }

    if (!silent) {
      ElMessage.error(msg)
    }

    return Promise.reject(error)
  }
)

export default request
