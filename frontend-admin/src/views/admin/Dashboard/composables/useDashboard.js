import { computed, shallowRef, ref } from 'vue'
import { ChatDotRound, Document, Monitor, Wallet } from '@element-plus/icons-vue'
import adminApi from '@/api/admin'
import {
  INVOICE_STATUS_MAP,
  STATUS_COLORS,
  resolveElTagType,
  toLabelMap,
  toTagTypeMap,
} from '@shared/statusConfig'

const toneColorMap = {
  blue: STATUS_COLORS.blue,
  green: STATUS_COLORS.success,
  orange: STATUS_COLORS.warning,
  red: STATUS_COLORS.danger,
  slate: STATUS_COLORS.info,
}

const statusMap = toLabelMap(INVOICE_STATUS_MAP)

const statusTypeMap = toTagTypeMap(INVOICE_STATUS_MAP)

export function useDashboard() {
  const loading = ref(false)
  const refreshedAt = ref('--')
  const data = shallowRef({})

  const headlineCards = computed(() => [
    {
      label: '今日收入',
      value: formatCurrency(data.value.today?.income),
      note: `本月 ${formatCurrency(data.value.month?.income)}`,
      icon: Wallet,
      tone: 'green',
    },
    {
      label: '今日账单',
      value: `${formatNumber(data.value.today?.new_invoices)} 条`,
      note: `本月 ${formatNumber(data.value.month?.new_invoices)} 条`,
      icon: Document,
      tone: 'blue',
    },
    {
      label: '活跃服务',
      value: formatNumber(data.value.counts?.active_services),
      note: `总账单 ${formatNumber(data.value.counts?.total_invoices)}`,
      icon: Monitor,
      tone: 'slate',
    },
    {
      label: '待处理工单',
      value: formatNumber(data.value.counts?.open_tickets),
      note: `用户 ${formatNumber(data.value.counts?.total_users)} 人`,
      icon: ChatDotRound,
      tone: 'orange',
    },
  ])

  const progressItems = computed(() => [
    {
      label: '今日账单 / 本月',
      percent: calcPercent(data.value.today?.new_invoices, data.value.month?.new_invoices),
      note: `${formatNumber(data.value.today?.new_invoices)} / ${formatNumber(data.value.month?.new_invoices)} 条`,
      color: toneColorMap.blue,
    },
    {
      label: '今日用户 / 本月',
      percent: calcPercent(data.value.today?.new_users, data.value.month?.new_users),
      note: `${formatNumber(data.value.today?.new_users)} / ${formatNumber(data.value.month?.new_users)} 名`,
      color: toneColorMap.green,
    },
    {
      label: '活跃服务 / 总账单',
      percent: calcPercent(data.value.counts?.active_services, data.value.counts?.total_invoices),
      note: `${formatNumber(data.value.counts?.active_services)} / ${formatNumber(data.value.counts?.total_invoices)}`,
      color: toneColorMap.slate,
    },
  ])

  const statusDistribution = computed(() => {
    const recentInvoices = data.value.recent_invoices || []
    const total = recentInvoices.length
    if (!total) return []
    return Object.entries(statusMap).map(([status, label]) => {
      const count = recentInvoices.filter((item) => Number(item.status) === Number(status)).length
      return {
        label,
        count,
        percent: calcPercent(count, total),
        tone: toneClass(resolveElTagType(statusTypeMap[status])),
      }
    }).filter((item) => item.count > 0)
  })

  const recentInvoices = computed(() => data.value.recent_invoices || [])

  const revenueByProduct = computed(() => data.value.revenue_by_product || [])
  const dailyRevenue = computed(() => data.value.daily_revenue || [])
  const monthLabel = computed(() => data.value.month_label || '')

  function statusText(status) {
    return statusMap[status] || '未知'
  }

  function statusType(status) {
    return resolveElTagType(statusTypeMap[status])
  }

  function toneClass(type) {
    if (type === 'success') return 'green'
    if (type === 'warning') return 'orange'
    if (type === 'danger') return 'red'
    if (type === 'primary') return 'blue'
    return 'slate'
  }

  function calcPercent(current, total) {
    const safeCurrent = Number(current || 0)
    const safeTotal = Number(total || 0)
    if (safeCurrent <= 0 || safeTotal <= 0) return 0
    return Math.min(100, Math.round((safeCurrent / safeTotal) * 100))
  }

  function formatCurrency(value) {
    return `¥${Number(value || 0).toFixed(2)}`
  }

  function formatNumber(value) {
    return Number(value || 0).toLocaleString('zh-CN')
  }

  function updateRefreshTime() {
    refreshedAt.value = new Date().toLocaleString('zh-CN', { hour12: false })
  }

  async function loadDashboard() {
    loading.value = true
    try {
      const [statsRes, recentInvoicesRes, monthlyRevenueRes] = await Promise.all([
        adminApi.dashboardStats(),
        adminApi.dashboardRecentInvoices(),
        adminApi.dashboardMonthlyRevenue(),
      ])
      data.value = {
        ...(statsRes.data || {}),
        recent_invoices: recentInvoicesRes.data?.recent_invoices || [],
        revenue_by_product: monthlyRevenueRes.data?.revenue_by_product || [],
        daily_revenue: monthlyRevenueRes.data?.daily_revenue || [],
        month_label: monthlyRevenueRes.data?.month_label || '',
      }
    } catch {
      data.value = {}
    } finally {
      loading.value = false
      updateRefreshTime()
    }
  }

  return {
    loading,
    refreshedAt,
    headlineCards,
    progressItems,
    statusDistribution,
    recentInvoices,
    revenueByProduct,
    dailyRevenue,
    monthLabel,
    statusText,
    statusType,
    formatCurrency,
    loadDashboard,
  }
}
