import { formatDateTime } from '@/utils/datetime'

export const LOG_LEVEL_OPTIONS = [
  'DEBUG',
  'INFO',
  'NOTICE',
  'WARNING',
  'ERROR',
  'CRITICAL',
  'ALERT',
  'EMERGENCY',
]

export const TASK_LOG_OPTIONS = [
  { value: 'refresh-hosting-panel-auth', label: '接口认证刷新' },
  { value: 'service-auto-renew', label: '服务自动续费' },
  { value: 'referral-release-rewards', label: '推荐奖励释放' },
  { value: 'service-lifecycle-maintenance', label: '服务生命周期维护' },
  { value: 'service-status-sync', label: '用户产品状态同步' },
  { value: 'billing-maintenance', label: '账单自动化维护' },
  { value: 'product-upstream-config-sync', label: '上游产品配置同步' },
  { value: 'coupon-campaign-dispatch', label: '优惠券活动发放' },
  { value: 'ticket-auto-close', label: '工单自动关闭' },
  { value: 'order-cleanup', label: '账单与充值清理' },
  { value: 'sync-processing-order-status', label: '账单状态同步（兼容）' },
  { value: 'queue-backlog-drain', label: '队列积压消费' },
]

export function formatLogDate(value, fallback = '-') {
  return formatDateTime(value, { fallback })
}

export function formatJsonBlock(value, fallback = '-') {
  if (value === null || value === undefined || value === '') {
    return fallback
  }

  if (typeof value === 'string') {
    const text = value.trim()
    if (!text) {
      return fallback
    }

    try {
      return JSON.stringify(JSON.parse(text), null, 2)
    } catch {
      return text
    }
  }

  try {
    return JSON.stringify(value, null, 2)
  } catch {
    return fallback
  }
}

export function buildDateRangeParams(range) {
  if (!Array.isArray(range) || range.length !== 2) {
    return {}
  }

  const [startDate, endDate] = range

  return {
    start_date: startDate || undefined,
    end_date: endDate || undefined,
  }
}

export function compactParams(params) {
  return Object.fromEntries(
    Object.entries(params).filter(([, value]) => {
      if (value === '' || value === null || value === undefined) {
        return false
      }

      if (Array.isArray(value) && value.length === 0) {
        return false
      }

      return true
    })
  )
}

export function getLevelTagType(level) {
  return ({
    DEBUG: 'info',
    INFO: 'success',
    NOTICE: 'info',
    WARNING: 'warning',
    ERROR: 'danger',
    CRITICAL: 'danger',
    ALERT: 'danger',
    EMERGENCY: 'danger',
  })[String(level || '').toUpperCase()] || 'info'
}

export function getMessageStatusTagType(status) {
  return ({
    success: 'success',
    failed: 'danger',
    pending: 'warning',
  })[String(status || '').toLowerCase()] || 'info'
}

export function getMessageStatusLabel(status) {
  return ({
    success: '成功',
    failed: '失败',
    pending: '待处理',
  })[String(status || '').toLowerCase()] || '未知'
}

export function getUserTypeLabel(userType) {
  return ({
    admin: '管理员',
    client: '客户',
    guest: '访客',
  })[String(userType || '').toLowerCase()] || '系统'
}

export function getHttpStatusTagType(status) {
  const code = Number(status || 0)

  if (!code) {
    return 'info'
  }

  if (code >= 500) {
    return 'danger'
  }

  if (code >= 400) {
    return 'warning'
  }

  if (code >= 200) {
    return 'success'
  }

  return 'info'
}

export function formatBytes(value) {
  const size = Number(value || 0)
  if (!Number.isFinite(size) || size <= 0) {
    return '0 B'
  }

  const units = ['B', 'KB', 'MB', 'GB']
  let current = size
  let unitIndex = 0

  while (current >= 1024 && unitIndex < units.length - 1) {
    current /= 1024
    unitIndex += 1
  }

  return `${current.toFixed(current >= 10 || unitIndex === 0 ? 0 : 1)} ${units[unitIndex]}`
}
