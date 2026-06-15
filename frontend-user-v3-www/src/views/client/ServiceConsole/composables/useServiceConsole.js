import { computed, onUnmounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import clientApi from '@/api/client'

// ─── Constants ────────────────────────────────────────────────────────────────

export const VALID_CONSOLE_TABS = ['overview', 'monitor', 'security', 'nat', 'power', 'logs', 'vnc']
export const DEFAULT_CONSOLE_TAB = 'overview'

const MONITOR_CHART_REQUEST_LIMIT = 4
const MONITOR_REQUEST_TIMEOUT = 12000
const POST_ACTION_STATUS_SYNC_DELAY_MS = 1500
const POST_ACTION_STATUS_SYNC_INTERVAL_MS = 3000
const POST_ACTION_STATUS_SYNC_ATTEMPTS = 6
const POWER_ACTION_RUNTIME_SNAPSHOTS = {
  on: { power_state: 'starting', power_label: '开机中', description: '开机中' },
  off: { power_state: 'stopping', power_label: '关机中', description: '关机中' },
  hard_off: { power_state: 'stopping', power_label: '关机中', description: '关机中' },
  reboot: { power_state: 'rebooting', power_label: '重启中', description: '重启中' },
  hard_reboot: { power_state: 'rebooting', power_label: '重启中', description: '重启中' },
}
const MONITOR_FALLBACK_CARDS = [
  { type: 'cpu', label: 'CPU' },
  { type: 'disk', label: '硬盘I/O' },
  { type: 'memory', label: '内存' },
  { type: 'flow', label: '带宽' },
]

// ─── Helpers ──────────────────────────────────────────────────────────────────

export function resolveToneTagType(tone) {
  return ({ success: 'success', warning: 'warning', danger: 'danger', primary: 'primary', info: 'info' })[tone] || 'info'
}

export function resolveRuntimeTagType(powerState) {
  const normalized = String(powerState || '').toLowerCase()
  if (['on', 'running', 'active'].includes(normalized)) return 'success'
  if (['off', 'stopped', 'shutdown'].includes(normalized)) return 'info'
  return normalized ? 'warning' : 'info'
}

export function formatMoney(value) {
  const amount = Number(value || 0)
  return Number.isFinite(amount) ? amount.toFixed(2) : '0.00'
}

export function formatTrafficAmount(value) {
  const amount = Number(value || 0)
  if (!Number.isFinite(amount) || amount <= 0) {
    return '0G'
  }

  if (amount >= 1024) {
    return `${normalizeNumericText(amount / 1024)}TB`
  }

  return `${normalizeNumericText(amount)}G`
}

export async function copyText(value) {
  const text = String(value || '').trim()
  if (!text) {
    ElMessage.warning('没有可复制的内容')
    return
  }
  try {
    await navigator.clipboard.writeText(text)
    ElMessage.success('内容已复制')
  } catch {
    ElMessage.warning('当前浏览器不支持自动复制，请手动复制')
  }
}

function resolveRequestErrorMessage(error, fallback) {
  const message = String(error?.message || '').trim()

  if (/timeout\s+of\s+\d+ms\s+exceeded/i.test(message)) {
    return `${fallback}，上游响应较慢，请稍后重试`
  }

  return message || fallback
}

function isRequestCanceled(error) {
  return error?.code === 'ERR_CANCELED' || error?.name === 'CanceledError'
}

function createEmptyDetail() {
  return {
    id: 0,
    name: '',
    remark: '',
    domain: '',
    status: 0,
    status_label: '',
    status_tone: 'info',
    billing_cycle: '',
    billing_cycle_label: '',
    amount: '0.00',
    expires_at: '',
    created_at: '',
    auto_renew: 0,
    machine_category: { key: '', label: '' },
    console_mode: '',
    can_manage: false,
    product: { id: 0, name: '', type: '', type_label: '', catalog_type: '', menu_name: '' },
    order: { id: 0, order_no: '', status: 0, status_label: '', paid_at: '' },
    upstream: { provider: '', host_id: 0, invoice_id: 0, status: '', status_label: '', remote_error: '', os: '', dedicated_ip: '' },
    runtime: { power_state: '', power_label: '', description: '' },
    traffic: {
      usage: '0.00',
      limit: 0,
      remaining: '',
      usage_label: '0G',
      limit_label: '不限',
      remaining_label: '不限',
      usage_percent: null,
      limited: false,
      button_text: '购买流量包',
      display_threshold_percent: 0,
      purchase_enabled: true,
    },
    connection: {
      hostname: '',
      username: '',
      password: '',
      has_password: false,
      port: 0,
      dedicated_ip: '',
      internal_ip: '',
      assigned_ips: [],
      nat_remote_address: '',
      nat_remote_host: '',
      nat_remote_port: 0,
      nat_remote_checked_at: '',
    },
    specs: [],
    actions: { refresh: true, power: false, module_status: false, password_reset: false, reinstall: false, traffic_package: false, available: [] },
  }
}

function normalizeMachineCategory(value) {
  if (value && typeof value === 'object' && !Array.isArray(value)) {
    return {
      key: String(value.key || '').trim(),
      label: String(value.label || '').trim(),
    }
  }

  const label = String(value || '').trim()
  return { key: '', label }
}

function normalizeDetail(payload = {}) {
  const base = createEmptyDetail()
  return {
    ...base,
    ...payload,
    machine_category: normalizeMachineCategory(payload.machine_category),
    product: { ...base.product, ...(payload.product || {}) },
    order: { ...base.order, ...(payload.order || {}) },
    upstream: { ...base.upstream, ...(payload.upstream || {}) },
    runtime: { ...base.runtime, ...(payload.runtime || {}) },
    traffic: { ...base.traffic, ...(payload.traffic || {}) },
    connection: { ...base.connection, ...(payload.connection || {}) },
    actions: { ...base.actions, ...(payload.actions || {}) },
    specs: Array.isArray(payload.specs) ? payload.specs : [],
  }
}

function mergeDetail(current = {}, patch = {}) {
  return normalizeDetail({
    ...current,
    ...patch,
    product: { ...(current.product || {}), ...(patch.product || {}) },
    order: { ...(current.order || {}), ...(patch.order || {}) },
    upstream: { ...(current.upstream || {}), ...(patch.upstream || {}) },
    runtime: { ...(current.runtime || {}), ...(patch.runtime || {}) },
    traffic: { ...(current.traffic || {}), ...(patch.traffic || {}) },
    connection: { ...(current.connection || {}), ...(patch.connection || {}) },
    actions: { ...(current.actions || {}), ...(patch.actions || {}) },
  })
}

function applyOptimisticPowerDetailSnapshot(currentDetail = {}, action) {
  const snapshot = POWER_ACTION_RUNTIME_SNAPSHOTS[String(action || '').trim()]
  if (!snapshot) {
    return normalizeDetail(currentDetail)
  }

  return mergeDetail(currentDetail, {
    runtime: {
      power_state: snapshot.power_state,
      power_label: snapshot.power_label,
      description: snapshot.description,
    },
  })
}

function buildMonitorWindow(range, apiRange = null) {
  const apiWindow = normalizeMonitorWindow(apiRange)
  if (apiWindow) {
    return apiWindow
  }

  const end = new Date()
  const start = new Date(end)
  if (range === '3h') start.setHours(start.getHours() - 3)
  else if (range === '7d') start.setDate(start.getDate() - 7)
  else if (range === '30d') start.setDate(start.getDate() - 30)
  else start.setHours(start.getHours() - 24)
  return { start: formatRangeDate(start), end: formatRangeDate(end) }
}

function normalizeMonitorWindow(range) {
  const start = normalizeMonitorTimestamp(range?.start)
  const end = normalizeMonitorTimestamp(range?.end)
  if (!start || !end || end < start) {
    return null
  }

  return {
    start: formatRangeDate(new Date(start)),
    end: formatRangeDate(new Date(end)),
  }
}

function normalizeMonitorTimestamp(value) {
  const numeric = Number(value)
  if (!Number.isFinite(numeric) || numeric <= 0) {
    return 0
  }

  if (numeric >= 1e12) {
    return Math.round(numeric)
  }

  if (numeric >= 1e9) {
    return Math.round(numeric * 1000)
  }

  return 0
}

function formatRangeDate(date) {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  const hour = String(date.getHours()).padStart(2, '0')
  const minute = String(date.getMinutes()).padStart(2, '0')
  return `${year}/${month}/${day} ${hour}:${minute}`
}

function normalizeSpecMatcherToken(value) {
  return String(value || '')
    .trim()
    .toLowerCase()
    .replace(/[\s_-]+/g, '')
}

function buildSpecMatcherTokens(item = {}) {
  return [
    normalizeSpecMatcherToken(item.key),
    normalizeSpecMatcherToken(item.label),
  ].filter(Boolean)
}

// ─── Composable ───────────────────────────────────────────────────────────────

export function useServiceConsole(props) {
  const route = useRoute()
  const router = useRouter()

  // ── Refs ──────────────────────────────────────────────────────────────────
  const detailLoading = ref(false)
  const detailRefreshing = ref(false)
  const statusSyncing = ref(false)
  const actionLoading = ref(false)
  const vncWindowLoading = ref(false)
  const autoRenewLoading = ref(false)
  const showPassword = ref(false)
  const activeTab = ref(DEFAULT_CONSOLE_TAB)
  const vncUrl = ref('')

  const detail = ref(createEmptyDetail())
  const taskState = reactive({ repassword: null, reinstall: null })

  // Renew dialog
  const renewDialogVisible = ref(false)
  const renewState = reactive({ loading: false, submitting: false, data: null, billing_cycle: '', user_coupon_id: 0 })

  // Traffic package dialog
  const trafficPackageDialogVisible = ref(false)
  const trafficPackageState = reactive({
    loading: false,
    quoting: false,
    submitting: false,
    data: null,
    target_value: 0,
    quote: null,
  })

  // Name dialog
  const nameDialogVisible = ref(false)
  const nameSubmitting = ref(false)
  const nameForm = reactive({ name: '' })

  // Remark dialog
  const remarkDialogVisible = ref(false)
  const remarkSubmitting = ref(false)
  const remarkForm = reactive({ remark: '' })

  // Password dialog
  const passwordDialogVisible = ref(false)
  const passwordForm = reactive({ password: '', password_confirmation: '' })
  const passwordRules = {
    password: [
      { required: true, message: '请输入新密码', trigger: 'blur' },
      { min: 8, message: '新密码至少需要 8 位', trigger: 'blur' },
    ],
    password_confirmation: [
      { required: true, message: '请再次输入新密码', trigger: 'blur' },
      {
        validator: (_rule, value, callback) => {
          if (String(value || '') !== String(passwordForm.password || '')) {
            callback(new Error('两次输入的密码不一致'))
            return
          }

          callback()
        },
        trigger: 'blur',
      },
    ],
  }

  // Reinstall dialog
  const reinstallDialogVisible = ref(false)
  const reinstallState = reactive({ loading: false, os: [], os_group: '', os_id: '' })
  const reinstallRules = {
    os_group: [{ required: true, message: '请选择系统分组', trigger: 'change' }],
    os_id: [{ required: true, message: '请选择系统版本', trigger: 'change' }],
  }

  // Monitor
  const monitorState = reactive({
    loading: false,
    range: '3h',
    window: null,
    supported: true,
    message: '',
    error: '',
    options: [],
    charts: [],
  })
  let monitorLoadSequence = 0
  let monitorAbortControllers = []
  let monitorRangeChangeTimer = null
  let postActionStatusSyncTimer = null
  let postActionStatusSyncToken = 0

  function abortPendingMonitorRequests() {
    monitorAbortControllers.forEach((controller) => controller.abort())
    monitorAbortControllers = []
  }

  function clearMonitorRangeChangeTimer() {
    if (monitorRangeChangeTimer) {
      clearTimeout(monitorRangeChangeTimer)
      monitorRangeChangeTimer = null
    }
  }

  function clearPostActionStatusSyncTimer() {
    if (postActionStatusSyncTimer) {
      clearTimeout(postActionStatusSyncTimer)
      postActionStatusSyncTimer = null
    }
  }

  function resetPostActionStatusSync() {
    postActionStatusSyncToken += 1
    clearPostActionStatusSyncTimer()
  }

  // NAT
  const natState = reactive({
    loading: false,
    submitting: false,
    supported: true,
    message: '',
    error: '',
    can_create: false,
    protocols: [],
    list: [],
  })
  const natForm = reactive({ name: '', ext_port: '', int_port: '', protocol: '' })
  const natRules = {
    name: [{ required: true, message: '请输入规则名称', trigger: 'blur' }],
    int_port: [{ required: true, message: '请输入内部端口', trigger: 'blur' }],
    protocol: [{ required: true, message: '请选择协议', trigger: 'change' }],
  }

  // Security
  const securityState = reactive({
    loading: false,
    submitting: false,
    rulesLoading: false,
    supported: true,
    message: '',
    error: '',
    directions: [],
    protocols: [],
    groups: [],
  })
  const activeSecurityGroupId = ref(0)
  const securityRules = ref([])
  const groupDialogVisible = ref(false)
  const groupForm = reactive({ name: '', description: '' })
  const groupRules = {
    name: [
      { required: true, message: '请输入安全组名称', trigger: 'blur' },
      { validator: validateSecurityGroupName, trigger: 'blur' },
    ],
  }
  const ruleDialogVisible = ref(false)
  const ruleForm = reactive({ direction: '', protocol: '', port: '', ip: '', description: '' })
  const ruleRules = {
    direction: [{ required: true, message: '请选择方向', trigger: 'change' }],
    protocol: [{ required: true, message: '请选择协议', trigger: 'change' }],
    port: [{ required: true, message: '请输入端口', trigger: 'blur' }],
    ip: [{ required: true, message: '请输入 IP 范围', trigger: 'blur' }],
  }

  // Logs
  const logsState = reactive({
    loading: false,
    list: [],
    total: 0,
    page: 1,
    page_size: 10,
    category: '',
    keyword: '',
    summary: { total: 0, today_total: 0, latest_created_at: '', service_name: '' },
  })

  const loadedTabs = reactive({ monitor: false, security: false, nat: false, logs: false })

  // ── Computed ──────────────────────────────────────────────────────────────
  const serviceId = computed(() => {
    const id = Number(route.params.id)
    return Number.isFinite(id) && id > 0 ? id : 0
  })

  const availableConsoleTabs = computed(() => {
    const profileModules = Array.isArray(props?.availableTabs) ? props.availableTabs : []

    if (profileModules.length > 0) {
      return profileModules.filter((item) => VALID_CONSOLE_TABS.includes(String(item || '').trim().toLowerCase()))
    }

    return VALID_CONSOLE_TABS.filter((item) => item !== 'nat')
  })
  const canUseNatForwarding = computed(() => Boolean(props?.permissions?.natForwarding))
  const canManageConsole = computed(() => Boolean(detail.value.actions?.module_status) || Number(detail.value.upstream?.host_id || 0) > 0)
  const canSyncStatus = computed(() => canManageConsole.value || Boolean(detail.value.actions?.refresh))

  const resolvedPassword = computed(() => {
    if (!detail.value.connection?.has_password) return '--'
    return showPassword.value ? (detail.value.connection?.password || '--') : '••••••••'
  })

  const renewAmount = computed(() => {
    const cycles = Array.isArray(renewState.data?.cycles) ? renewState.data.cycles : []
    const current = cycles.find((item) => item.billing_cycle === renewState.billing_cycle)
    return formatMoney(current?.amount || 0)
  })
  const renewAvailableCoupons = computed(() => (
    Array.isArray(renewState.data?.available_coupons) ? renewState.data.available_coupons : []
  ))
  const canPurchaseTrafficPackage = computed(() => Boolean(detail.value.actions?.traffic_package))
  const trafficPackageChoices = computed(() => (
    Array.isArray(trafficPackageState.data?.packages) ? trafficPackageState.data.packages : []
  ))
  const trafficPackageAmount = computed(() => formatMoney(trafficPackageState.quote?.pricing?.amount || 0))

  const reinstallGroupedOptions = computed(() => {
    const groups = {}
    for (const item of reinstallState.os) {
      const groupName = item.group_name || '默认分组'
      if (!groups[groupName]) groups[groupName] = { group_name: groupName, items: [] }
      groups[groupName].items.push(item)
    }
    return Object.values(groups)
  })

  const currentReinstallOptions = computed(() =>
    reinstallGroupedOptions.value.find((item) => item.group_name === reinstallState.os_group)?.items || []
  )

  const taskStatuses = computed(() => (
    [
      taskState.repassword ? { type: 'repassword', label: '密码重置进度', description: taskState.repassword.description || taskState.repassword.status || '--' } : null,
      taskState.reinstall ? { type: 'reinstall', label: '重装系统进度', description: taskState.reinstall.description || taskState.reinstall.status || '--' } : null,
    ].filter(Boolean)
  ))

  const activeSecurityGroup = computed(() =>
    securityState.groups.find((item) => item.id === activeSecurityGroupId.value) || null
  )

  const isNatConsole = computed(() => (
    Boolean(detail.value.is_nat_console) || String(detail.value.console_mode || '').trim().toLowerCase() === 'nat'
  ))
  const machineCategoryLabel = computed(() => String(detail.value.machine_category?.label || '').trim())
  const serviceRegion = computed(() => findSpecValue(['区域', '地区', '机房', '地域', '数据中心', 'area', 'region', 'node'], machineCategoryLabel.value || '--'))
  const serviceOs = computed(() => String(detail.value.upstream?.os || '').trim() || findSpecValue(['操作系统', 'os'], '--'))
  const natRemoteAddressText = computed(() => {
    const directAddress = String(detail.value.connection?.nat_remote_address || '').trim()
    if (directAddress) return directAddress

    const remoteHost = String(detail.value.connection?.nat_remote_host || '').trim()
    const remotePort = Number(detail.value.connection?.nat_remote_port || 0)

    if (remoteHost && Number.isFinite(remotePort) && remotePort > 0) {
      return `${remoteHost}:${remotePort}`
    }

    return remoteHost
  })
  const primaryConnectionLabel = computed(() => (isNatConsole.value ? '远程地址' : '公网 IP'))
  const primaryConnectionText = computed(() => {
    if (isNatConsole.value && natRemoteAddressText.value) return natRemoteAddressText.value

    return String(
      detail.value.connection?.dedicated_ip
      || detail.value.connection?.nat_remote_host
      || detail.value.upstream?.dedicated_ip
      || ''
    ).trim() || '--'
  })
  const connectionPortLabel = computed(() => '端口')
  const connectionEndpointLabel = computed(() => '主机名')
  const connectionEndpointText = computed(() => {
    return String(detail.value.connection?.hostname || detail.value.domain || '').trim() || '--'
  })

  const actualConnectionPort = computed(() => {
    const natPort = Number(detail.value.connection?.nat_remote_port || 0)
    if (Number.isFinite(natPort) && natPort > 0) return String(natPort)

    const natAddressMatch = String(detail.value.connection?.nat_remote_address || '').trim().match(/:(\d{1,5})$/)
    if (natAddressMatch?.[1]) return natAddressMatch[1]

    const port = Number(detail.value.connection?.port || 0)
    return Number.isFinite(port) && port > 0 ? String(port) : ''
  })

  const connectionPortText = computed(() => {
    if (actualConnectionPort.value !== '') return actualConnectionPort.value
    const osText = String(serviceOs.value || '').toLowerCase()
    if (!osText || osText === '--') return '--'
    if (['debian', 'ubuntu', 'centos', 'almalinux', 'alma', 'rocky'].some((keyword) => osText.includes(keyword))) return '22'
    if (osText.includes('windows')) return '3389'
    return '--'
  })

  const serviceIpCount = computed(() => {
    const bySpec = findSpecValue(['IP数量', 'IP 数量', 'IPv4', 'ip_num'], '')
    if (bySpec !== '') return bySpec
    const assignedCount = Array.isArray(detail.value.connection?.assigned_ips) ? detail.value.connection.assigned_ips.length : 0
    if (assignedCount > 0) return String(assignedCount)
    return detail.value.connection?.dedicated_ip ? '1' : '--'
  })

  const bandwidthText = computed(() => resolveBandwidthText())
  const renewPriceText = computed(() => `¥${formatMoney(detail.value.amount)} / ${detail.value.billing_cycle_label || '当前周期'}`)
  const autoRenewLabel = computed(() => (Number(detail.value.auto_renew) === 1 ? '已开启' : '已关闭'))
  const monitorWindow = computed(() => buildMonitorWindow(monitorState.range, monitorState.window))

  const baseLogCategoryOptions = [
    { label: '电源管理', value: 'power' },
    { label: '密码重置', value: 'password' },
    { label: '重装系统', value: 'reinstall' },
    { label: '续费管理', value: 'renew' },
    { label: '规格升级', value: 'upgrade' },
    { label: 'NAT 转发', value: 'nat_forwarding' },
    { label: '安全组', value: 'security_group' },
    { label: '安全组规则', value: 'security_rule' },
    { label: '实例变更', value: 'service' },
  ]

  const logCategoryOptions = computed(() => (
    canUseNatForwarding.value
      ? baseLogCategoryOptions
      : baseLogCategoryOptions.filter((item) => item.value !== 'nat_forwarding')
  ))

  function getDefaultSecurityDirection() {
    return securityState.directions.length ? String(securityState.directions[0].value || '') : ''
  }

  function getDefaultSecurityProtocol() {
    return securityState.protocols.length ? String(securityState.protocols[0].value || '') : ''
  }

  function normalizeSecurityGroupName(value) {
    return String(value || '').trim().toLowerCase().replace(/\s+/g, '')
  }

  function validateSecurityGroupName(_rule, value, callback) {
    const normalized = normalizeSecurityGroupName(value)
    if (!normalized) {
      callback()
      return
    }

    const exists = securityState.groups.some((item) => normalizeSecurityGroupName(item?.name) === normalized)
    if (exists) {
      callback(new Error('安全组名称已存在，请换一个名称'))
      return
    }

    callback()
  }

  function resetPasswordFormState() {
    passwordForm.password = ''
    passwordForm.password_confirmation = ''
  }

  function resetReinstallFormState(options = {}) {
    if (!options.preserveOptions) {
      reinstallState.os = []
    }

    reinstallState.os_group = ''
    reinstallState.os_id = ''
  }

  function resetTrafficPackageState() {
    trafficPackageState.loading = false
    trafficPackageState.quoting = false
    trafficPackageState.submitting = false
    trafficPackageState.data = null
    trafficPackageState.target_value = 0
    trafficPackageState.quote = null
  }

  function resetGroupFormState() {
    groupForm.name = ''
    groupForm.description = ''
  }

  function resetRuleFormState() {
    ruleForm.direction = getDefaultSecurityDirection()
    ruleForm.protocol = getDefaultSecurityProtocol()
    ruleForm.port = ''
    ruleForm.ip = ''
    ruleForm.description = ''
  }

  // ── Tab helpers ───────────────────────────────────────────────────────────
  function isConsoleTabAvailable(tab) {
    const normalized = String(tab || '').trim().toLowerCase()

    if (!availableConsoleTabs.value.includes(normalized)) {
      return false
    }

    if (normalized === 'nat') {
      return canUseNatForwarding.value
    }

    return true
  }

  function normalizeConsoleTab(value) {
    const normalized = String(value || '').trim().toLowerCase()

    return isConsoleTabAvailable(normalized) ? normalized : DEFAULT_CONSOLE_TAB
  }

  function syncActiveTabQuery(tab) {
    const normalized = normalizeConsoleTab(tab)
    const currentTab = normalizeConsoleTab(route.query.tab)
    if (currentTab === normalized) return
    const nextQuery = { ...route.query }
    if (normalized === DEFAULT_CONSOLE_TAB) {
      delete nextQuery.tab
    } else {
      nextQuery.tab = normalized
    }
    router.replace({ query: nextQuery }).catch(() => {})
  }

  // ── Spec helpers ──────────────────────────────────────────────────────────
  function findSpecValue(aliases = [], fallback = '--') {
    const list = Array.isArray(detail.value.specs) ? detail.value.specs : []
    const normalizedAliases = aliases
      .map((alias) => normalizeSpecMatcherToken(alias))
      .filter(Boolean)

    for (const alias of normalizedAliases) {
      const matched = list.find((item) => buildSpecMatcherTokens(item).some((token) => token === alias))
      const value = String(matched?.value ?? '').trim()
      if (value !== '') return value
    }

    for (const alias of normalizedAliases) {
      const matched = list.find((item) => {
        const tokens = buildSpecMatcherTokens(item)
        return tokens.some((token) => token.includes(alias) || alias.includes(token))
      })
      const value = String(matched?.value ?? '').trim()
      if (value !== '') return value
    }
    return fallback
  }

  function resolveBandwidthText() {
    const direct = findSpecValue(['带宽', '宽带', 'bandwidth', 'bw'], '')
    if (direct !== '') return direct
    const inbound = findSpecValue(['下行带宽', '下行', 'in_bw'], '')
    const outbound = findSpecValue(['上行带宽', '上行', 'out_bw'], '')
    if (inbound && outbound) return `${outbound} / ${inbound}`
    return inbound || outbound || '--'
  }

  // ── Security group helpers ────────────────────────────────────────────────
  function resolveSecurityGroupRowClassName({ row }) {
    return Number(row?.id || 0) === activeSecurityGroupId.value ? 'security-group-row--active' : ''
  }

  function sortSecurityGroups(groups = []) {
    return [...groups].sort((left, right) => {
      const leftApplied = Number(Boolean(left?.is_applied))
      const rightApplied = Number(Boolean(right?.is_applied))
      if (leftApplied !== rightApplied) return rightApplied - leftApplied
      return Number(left?.id || 0) - Number(right?.id || 0)
    })
  }

  // ── VNC helpers ───────────────────────────────────────────────────────────
  const VNC_CREDENTIAL_STORAGE_PREFIX = 'caiwu:vnc-credentials:'

  function decorateVncUrl(rawUrl) {
    const url = String(rawUrl || '').trim()
    if (!url) return ''
    try {
      const target = new URL(url, window.location.origin)
      target.searchParams.set('service_id', String(serviceId.value))
      return target.toString()
    } catch {
      return url
    }
  }

  function extractVncLaunchToken(rawUrl) {
    try {
      return new URL(String(rawUrl || ''), window.location.origin).searchParams.get('token') || ''
    } catch {
      return ''
    }
  }

  function normalizeVncCredentials(payload) {
    if (!payload || typeof payload !== 'object') return null

    const credentials = {}
    const username = String(payload.username || '').trim()
    const target = String(payload.target || '').trim()
    const password = String(payload.password || '').trim()

    if (username) credentials.username = username
    if (target) credentials.target = target
    if (password) credentials.password = password

    return Object.keys(credentials).length > 0 ? credentials : null
  }

  function storeVncCredentialsForUrl(rawUrl, payload) {
    const token = extractVncLaunchToken(rawUrl)
    const credentials = normalizeVncCredentials(payload)
    if (!token || !credentials) return

    try {
      // VNC 密码不放进 iframe URL；只在同源 sessionStorage 中短暂交给 noVNC 页面自动认证。
      window.sessionStorage?.setItem?.(
        `${VNC_CREDENTIAL_STORAGE_PREFIX}${token}`,
        JSON.stringify({ ...credentials, service_id: serviceId.value, saved_at: Date.now() })
      )
    } catch {}
  }

  // ── Monitor helpers ───────────────────────────────────────────────────────
  function buildMonitorChartItem(data, fallbackType = '') {
    const type = String(data?.type || data?.selected_type || fallbackType || '').trim()
    const chart = data?.chart && typeof data.chart === 'object' ? data.chart : null
    if (!type || !chart) return null
    return {
      key: '',
      type,
      label: String(data?.label || data?.selected_label || type),
      message: String(data?.message || ''),
      error: String(data?.error || ''),
      chart,
      summary: data?.summary && typeof data.summary === 'object' ? data.summary : null,
      loading: false,
    }
  }

  function resolveMonitorChartSource(options = []) {
    if (Array.isArray(options) && options.length) {
      const normalized = options
        .slice(0, MONITOR_CHART_REQUEST_LIMIT)
        .map((item, index) => ({
          type: String(item?.value || '').trim() || MONITOR_FALLBACK_CARDS[index]?.type || '',
          label: String(item?.label || item?.value || MONITOR_FALLBACK_CARDS[index]?.label || `监控项 ${index + 1}`).trim(),
        }))
        .filter((item) => item.type || item.label)
      if (normalized.length) return normalized
    }
    return MONITOR_FALLBACK_CARDS.slice(0, MONITOR_CHART_REQUEST_LIMIT)
  }

  function buildMonitorChartSlots(options = [], config = {}) {
    const source = resolveMonitorChartSource(options)
    const existingMap = new Map(
      monitorState.charts
        .filter((item) => item?.type)
        .map((item) => [String(item.type), item])
    )
    return source.map((item, index) => {
      const type = String(item?.type || '').trim() || `monitor-${index + 1}`
      const existing = existingMap.get(type)
      const shouldReset = Boolean(config.reset)
      const shouldKeepLoading = shouldReset
        ? Boolean(config.loading)
        : (config.targetType ? type !== config.targetType && Boolean(existing?.loading) : Boolean(config.loading))
      const chart = shouldReset ? {} : (existing?.chart || {})
      const summary = shouldReset ? null : (existing?.summary || null)
      const message = shouldReset ? '监控数据加载中' : (existing?.message || '基于上游采样数据生成')
      const error = shouldReset
        ? ''
        : (config.targetType === type ? (config.targetError || '') : (config.errorText || existing?.error || ''))
      return {
        key: `monitor-slot-${index + 1}`,
        type,
        label: String(item?.label || type),
        message,
        error,
        chart,
        summary,
        loading: shouldKeepLoading,
      }
    })
  }

  function primeMonitorChartSlots(options = []) {
    monitorState.charts = buildMonitorChartSlots(options, { reset: true, loading: true })
  }

  function markMonitorChartSettled(type, options = [], { error = '', message = '' } = {}) {
    const nextCharts = buildMonitorChartSlots(options, { reset: false, loading: false, targetType: type, targetError: error })
    const targetIndex = nextCharts.findIndex((item) => item.type === type)
    if (targetIndex !== -1 && message) {
      nextCharts[targetIndex] = {
        ...nextCharts[targetIndex],
        message,
        loading: false,
        error,
      }
    }
    monitorState.charts = nextCharts
  }

  function upsertMonitorChart(chartItem, options = []) {
    const nextCharts = buildMonitorChartSlots(options)
    const targetIndex = nextCharts.findIndex((item) => item.type === chartItem.type)
    if (targetIndex !== -1) {
      nextCharts[targetIndex] = {
        ...nextCharts[targetIndex],
        ...chartItem,
        key: nextCharts[targetIndex].key,
        loading: false,
        error: '',
        message: chartItem.message || '基于上游采样数据生成',
      }
    } else if (nextCharts.length) {
      nextCharts[0] = {
        ...nextCharts[0],
        ...chartItem,
        key: nextCharts[0].key,
        loading: false,
        error: '',
        message: chartItem.message || '基于上游采样数据生成',
      }
    }
    monitorState.charts = nextCharts
  }

  // ── Page lifecycle ────────────────────────────────────────────────────────
  function resetPageState() {
    monitorLoadSequence += 1
    clearMonitorRangeChangeTimer()
    abortPendingMonitorRequests()
    resetPostActionStatusSync()
    detail.value = createEmptyDetail()
    showPassword.value = false
    vncUrl.value = ''
    activeTab.value = normalizeConsoleTab(route.query.tab)
    statusSyncing.value = false
    taskState.repassword = null
    taskState.reinstall = null
    renewDialogVisible.value = false
    renewState.loading = false
    renewState.submitting = false
    renewState.data = null
    renewState.billing_cycle = ''
    renewState.user_coupon_id = 0
    trafficPackageDialogVisible.value = false
    resetTrafficPackageState()
    resetPasswordFormState()
    passwordDialogVisible.value = false
    reinstallState.loading = false
    resetReinstallFormState()
    reinstallDialogVisible.value = false
    monitorState.loading = false
    monitorState.range = '3h'
    monitorState.window = null
    monitorState.supported = true
    monitorState.message = ''
    monitorState.error = ''
    monitorState.options = []
    monitorState.charts = []
    natState.loading = false
    natState.submitting = false
    natState.supported = true
    natState.message = ''
    natState.error = ''
    natState.can_create = false
    natState.protocols = []
    natState.list = []
    natForm.name = ''
    natForm.ext_port = ''
    natForm.int_port = ''
    natForm.protocol = ''
    securityState.loading = false
    securityState.submitting = false
    securityState.rulesLoading = false
    securityState.supported = true
    securityState.message = ''
    securityState.error = ''
    securityState.directions = []
    securityState.protocols = []
    securityState.groups = []
    securityRules.value = []
    activeSecurityGroupId.value = 0
    groupDialogVisible.value = false
    resetGroupFormState()
    ruleDialogVisible.value = false
    resetRuleFormState()
    logsState.loading = false
    logsState.list = []
    logsState.total = 0
    logsState.page = 1
    logsState.page_size = 10
    logsState.category = ''
    logsState.keyword = ''
    logsState.summary.total = 0
    logsState.summary.today_total = 0
    logsState.summary.latest_created_at = ''
    logsState.summary.service_name = ''
    loadedTabs.monitor = false
    loadedTabs.security = false
    loadedTabs.nat = false
    loadedTabs.logs = false
  }

  async function loadDetailBase() {
    detailLoading.value = true
    try {
      const res = await clientApi.serviceBaseDetail(serviceId.value)
      detail.value = normalizeDetail(res.data || {})
    } catch (error) {
      ElMessage.error(error?.message || '加载实例信息失败')
    } finally {
      detailLoading.value = false
    }
  }

  async function fetchDetailRemoteStatus() {
    const res = await clientApi.serviceRemoteStatus(serviceId.value)
    detail.value = mergeDetail(detail.value, res.data || {})
  }

  async function refreshHostStatusSnapshot() {
    if (detail.value.actions?.module_status) {
      await clientApi.serviceModuleStatus(serviceId.value, { type: 'host' })
    }
    await fetchDetailRemoteStatus()
  }

  async function fetchTaskStatus(type) {
    const res = await clientApi.serviceModuleStatus(serviceId.value, { type })
    taskState[type] = res.data || null
    return taskState[type]
  }

  function queuePostActionStatusSync(options = {}) {
    if (!serviceId.value) return

    resetPostActionStatusSync()
    const token = postActionStatusSyncToken
    const attempts = Number.isFinite(Number(options.attempts))
      ? Math.max(1, Number(options.attempts))
      : POST_ACTION_STATUS_SYNC_ATTEMPTS
    const initialDelay = Number.isFinite(Number(options.initialDelay))
      ? Math.max(0, Number(options.initialDelay))
      : POST_ACTION_STATUS_SYNC_DELAY_MS
    const interval = Number.isFinite(Number(options.interval))
      ? Math.max(500, Number(options.interval))
      : POST_ACTION_STATUS_SYNC_INTERVAL_MS
    const refreshHost = options.refreshHost !== false
    const shouldPollHostByDefault = refreshHost && !(Array.isArray(options.moduleTypes) && options.moduleTypes.length)
    const moduleTypes = [...new Set(
      (Array.isArray(options.moduleTypes) ? options.moduleTypes : [])
        .map((item) => String(item || '').trim().toLowerCase())
        .filter(Boolean)
    )]

    const run = async (attempt) => {
      if (token !== postActionStatusSyncToken || !serviceId.value) return

      let shouldContinue = shouldPollHostByDefault

      if (refreshHost) {
        try {
          await refreshHostStatusSnapshot()
        } catch {}
      }

      for (const type of moduleTypes) {
        try {
          const status = await fetchTaskStatus(type)
          shouldContinue = shouldContinue || !status?.is_finished
        } catch {}
      }

      if (refreshHost && moduleTypes.length > 0 && !shouldContinue && token === postActionStatusSyncToken) {
        try {
          await refreshHostStatusSnapshot()
        } catch {}
      }

      if (attempt >= attempts || !shouldContinue || token !== postActionStatusSyncToken) {
        clearPostActionStatusSyncTimer()
        return
      }

      postActionStatusSyncTimer = setTimeout(() => {
        void run(attempt + 1)
      }, interval)
    }

    postActionStatusSyncTimer = setTimeout(() => {
      void run(1)
    }, initialDelay)
  }

  async function loadDetailRemoteStatus(silent = false) {
    detailRefreshing.value = true
    try {
      await fetchDetailRemoteStatus()
      if (!silent) ElMessage.success('实例状态已刷新')
    } catch (error) {
      if (!silent) ElMessage.error(error?.message || '刷新实例状态失败')
    } finally {
      detailRefreshing.value = false
    }
  }

  async function bootstrapPage() {
    if (!serviceId.value) return
    resetPageState()
    await loadDetailBase()
    void loadDetailRemoteStatus(true)
  }

  // ── Tab watches ───────────────────────────────────────────────────────────
  watch(
    () => route.params.id,
    async () => { await bootstrapPage() },
    { immediate: true }
  )

  watch(
    () => route.query.tab,
    (tab) => {
      const normalized = normalizeConsoleTab(tab)
      if (normalized !== activeTab.value) activeTab.value = normalized
    },
    { immediate: true }
  )

  watch(activeTab, async (tab) => {
    if (!isConsoleTabAvailable(tab)) {
      if (activeTab.value !== DEFAULT_CONSOLE_TAB) {
        activeTab.value = DEFAULT_CONSOLE_TAB
      }
      return
    }

    syncActiveTabQuery(tab)
    if (tab === 'monitor' && !loadedTabs.monitor) {
      loadedTabs.monitor = true
      await loadMonitor()
    }
    if (tab === 'security' && !loadedTabs.security) {
      loadedTabs.security = true
      await loadSecurityGroups()
    }
    if (tab === 'nat' && !loadedTabs.nat) {
      loadedTabs.nat = true
      await loadNatForwardings()
    }
    if (tab === 'logs' && !loadedTabs.logs) {
      loadedTabs.logs = true
      await loadLogs()
    }
  })

  watch(canUseNatForwarding, (enabled) => {
    if (!enabled && activeTab.value === 'nat') {
      activeTab.value = DEFAULT_CONSOLE_TAB
    }
  })

  watch(passwordDialogVisible, (visible) => {
    if (!visible) {
      resetPasswordFormState()
    }
  })

  watch(reinstallDialogVisible, (visible) => {
    if (!visible) {
      resetReinstallFormState()
    }
  })

  watch(trafficPackageDialogVisible, (visible) => {
    if (!visible) {
      resetTrafficPackageState()
    }
  })

  watch(groupDialogVisible, (visible) => {
    if (!visible) {
      resetGroupFormState()
    }
  })

  watch(ruleDialogVisible, (visible) => {
    if (!visible) {
      resetRuleFormState()
      return
    }

    if (!ruleForm.direction) {
      ruleForm.direction = getDefaultSecurityDirection()
    }

    if (!ruleForm.protocol) {
      ruleForm.protocol = getDefaultSecurityProtocol()
    }
  })

  watch(availableConsoleTabs, (tabs) => {
    if (!tabs.includes(activeTab.value)) {
      activeTab.value = DEFAULT_CONSOLE_TAB
    }
  })

  onUnmounted(() => {
    resetPostActionStatusSync()
    clearMonitorRangeChangeTimer()
    abortPendingMonitorRequests()
  })

  // ── Action handlers ───────────────────────────────────────────────────────
  async function handleSyncStatus() {
    statusSyncing.value = true
    try {
      await refreshHostStatusSnapshot()
      ElMessage.success(detail.value.actions?.module_status ? '实例状态已同步' : '实例状态已刷新')
    } catch (error) {
      ElMessage.error(error?.message || '同步实例状态失败')
    } finally {
      statusSyncing.value = false
    }
  }

  async function handlePowerAction(action) {
    const previousDetail = normalizeDetail(detail.value)
    detail.value = applyOptimisticPowerDetailSnapshot(detail.value, action)
    actionLoading.value = true
    try {
      const res = await clientApi.servicePower(serviceId.value, { action })
      if (res.data?.detail) detail.value = normalizeDetail(res.data.detail)
      ElMessage.success(res.data?.message || '操作已提交')
      queuePostActionStatusSync()
    } catch (error) {
      detail.value = previousDetail
      ElMessage.error(error?.message || '实例操作失败')
    } finally {
      actionLoading.value = false
    }
  }

  async function handleToggleAutoRenew(value) {
    autoRenewLoading.value = true
    try {
      await clientApi.updateAutoRenew(serviceId.value, { auto_renew: value ? 1 : 0 })
      detail.value = mergeDetail(detail.value, { auto_renew: value ? 1 : 0 })
      ElMessage.success(`自动续费已${value ? '开启' : '关闭'}`)
    } catch (error) {
      ElMessage.error(error?.message || '自动续费更新失败')
    } finally {
      autoRenewLoading.value = false
    }
  }

  async function loadRenewPreview() {
    renewState.loading = true
    try {
      const res = await clientApi.serviceRenewPreview(serviceId.value, {
        billing_cycle: renewState.billing_cycle || undefined,
        user_coupon_id: renewState.user_coupon_id || undefined,
      })
      renewState.data = res.data || null
      renewState.billing_cycle = String(res.data?.default_cycle || res.data?.billing_cycle || res.data?.cycles?.[0]?.billing_cycle || '')
      renewState.user_coupon_id = Number(res.data?.selected_user_coupon_id || 0)
    } catch (error) {
      ElMessage.error(error?.message || '加载续费信息失败')
    } finally {
      renewState.loading = false
    }
  }

  async function openRenewDialog() {
    renewDialogVisible.value = true
    renewState.loading = true
    renewState.data = null
    renewState.billing_cycle = ''
    renewState.user_coupon_id = 0
    await loadRenewPreview()
  }

  async function handleRenewCycleChange(value) {
    renewState.billing_cycle = String(value || '')
    await loadRenewPreview()
  }

  async function handleRenewCouponChange(value) {
    renewState.user_coupon_id = Number(value || 0)
    await loadRenewPreview()
  }

  async function submitRenew() {
    if (!renewState.billing_cycle) return
    renewState.submitting = true
    try {
      const res = await clientApi.createRenewOrder(serviceId.value, {
        billing_cycle: renewState.billing_cycle,
        user_coupon_id: renewState.user_coupon_id || undefined,
      })
      const invoiceId = Number(res.data?.id || 0)
      renewDialogVisible.value = false
      ElMessage.success('续费账单已创建，正在跳转支付')
      router.push(invoiceId > 0 ? `/client/invoices/${invoiceId}` : '/client/invoices')
    } catch (error) {
      ElMessage.error(error?.message || '创建续费账单失败')
    } finally {
      renewState.submitting = false
    }
  }

  async function loadTrafficPackagePreview() {
    trafficPackageState.loading = true
    try {
      const res = await clientApi.serviceTrafficPackages(serviceId.value)
      trafficPackageState.data = res.data || null
      trafficPackageState.target_value = Number(trafficPackageChoices.value[0]?.target_value || 0)

      if (res.data?.supported === false) {
        ElMessage.warning(res.data?.message || '当前服务暂不支持流量包购买')
        return
      }

      if (trafficPackageState.target_value > 0) {
        await quoteTrafficPackage()
      }
    } catch (error) {
      ElMessage.error(error?.message || '加载流量包信息失败')
    } finally {
      trafficPackageState.loading = false
    }
  }

  function resolveTrafficPackageQuotePayload() {
    return {
      target_value: Number(trafficPackageState.target_value || 0) || undefined,
    }
  }

  async function quoteTrafficPackage() {
    if (!trafficPackageState.data?.supported) {
      trafficPackageState.quote = null
      return
    }

    trafficPackageState.quoting = true
    try {
      const res = await clientApi.quoteTrafficPackage(serviceId.value, resolveTrafficPackageQuotePayload())
      trafficPackageState.quote = res.data || null
    } catch (error) {
      trafficPackageState.quote = null
      ElMessage.error(error?.message || '预览流量包报价失败')
    } finally {
      trafficPackageState.quoting = false
    }
  }

  async function openTrafficPackageDialog() {
    trafficPackageDialogVisible.value = true
    resetTrafficPackageState()
    await loadTrafficPackagePreview()
  }

  async function handleTrafficPackageChoiceChange(value) {
    trafficPackageState.target_value = Number(value || 0)
    await quoteTrafficPackage()
  }

  async function handleTrafficPackageQtyChange(value) {
    trafficPackageState.target_value = Number(value || 0)
    await quoteTrafficPackage()
  }

  async function submitTrafficPackageOrder() {
    if (trafficPackageState.submitting) {
      return
    }

    trafficPackageState.submitting = true
    try {
      const res = await clientApi.createTrafficPackageOrder(serviceId.value, resolveTrafficPackageQuotePayload())
      const invoiceId = Number(res.data?.id || 0)
      trafficPackageDialogVisible.value = false
      ElMessage.success('流量包账单已创建，正在跳转支付')
      router.push(invoiceId > 0 ? `/client/invoices/${invoiceId}` : '/client/invoices')
    } catch (error) {
      ElMessage.error(error?.message || '创建流量包账单失败')
    } finally {
      trafficPackageState.submitting = false
    }
  }

  function openNameDialog() {
    nameForm.name = String(detail.value.custom_service_name || detail.value.name || '')
    nameDialogVisible.value = true
  }

  async function submitName() {
    nameSubmitting.value = true
    try {
      const res = await clientApi.updateServiceName(serviceId.value, { name: nameForm.name })
      const customServiceName = String(res.data?.custom_service_name || '').trim()
      const resolvedServiceName = customServiceName || String(res.data?.name || detail.value.name || '').trim()
      detail.value = mergeDetail(detail.value, {
        name: resolvedServiceName,
        custom_service_name: customServiceName,
        has_custom_service_name: Boolean(res.data?.has_custom_service_name),
      })
      nameDialogVisible.value = false
      ElMessage.success('实例名称已保存')
    } catch (error) {
      ElMessage.error(error?.message || '实例名称保存失败')
    } finally {
      nameSubmitting.value = false
    }
  }

  function openRemarkDialog() {
    remarkForm.remark = String(detail.value.remark || '')
    remarkDialogVisible.value = true
  }

  async function submitRemark() {
    remarkSubmitting.value = true
    try {
      const res = await clientApi.updateServiceRemark(serviceId.value, { remark: remarkForm.remark })
      detail.value = mergeDetail(detail.value, { remark: String(res.data?.remark || '').trim() })
      remarkDialogVisible.value = false
      ElMessage.success('备注已保存')
    } catch (error) {
      ElMessage.error(error?.message || '备注保存失败')
    } finally {
      remarkSubmitting.value = false
    }
  }

  async function submitResetPassword() {
    actionLoading.value = true
    try {
      const res = await clientApi.serviceResetPassword(serviceId.value, {
        password: passwordForm.password,
        password_confirmation: passwordForm.password_confirmation,
      })
      if (res.data?.detail) detail.value = normalizeDetail(res.data.detail)
      if (res.data?.status) taskState.repassword = res.data.status
      passwordDialogVisible.value = false
      resetPasswordFormState()
      ElMessage.success(res.data?.message || '重置密码指令已提交')
      queuePostActionStatusSync({ moduleTypes: ['repassword'], attempts: 2 })
    } catch (error) {
      ElMessage.error(error?.message || '重置密码失败')
    } finally {
      actionLoading.value = false
    }
  }

  async function openReinstallDialog() {
    resetReinstallFormState()
    reinstallDialogVisible.value = true
    reinstallState.loading = true
    try {
      const res = await clientApi.serviceReinstallOptions(serviceId.value)
      reinstallState.os = Array.isArray(res.data?.os) ? res.data.os : []
      const firstGroup = reinstallGroupedOptions.value[0]
      reinstallState.os_group = firstGroup?.group_name || ''
      reinstallState.os_id = firstGroup?.items?.[0]?.os_id || ''
    } catch (error) {
      ElMessage.error(error?.message || '加载重装系统选项失败')
    } finally {
      reinstallState.loading = false
    }
  }

  function handleReinstallGroupChange(value) {
    const group = reinstallGroupedOptions.value.find((item) => item.group_name === value)
    reinstallState.os_id = group?.items?.[0]?.os_id || ''
  }

  async function submitReinstall() {
    actionLoading.value = true
    try {
      const res = await clientApi.serviceReinstall(serviceId.value, { os_id: reinstallState.os_id })
      if (res.data?.detail) detail.value = normalizeDetail(res.data.detail)
      if (res.data?.status) taskState.reinstall = res.data.status
      reinstallDialogVisible.value = false
      resetReinstallFormState()
      ElMessage.success(res.data?.message || '重装系统任务已提交')
      queuePostActionStatusSync({ moduleTypes: ['reinstall'], attempts: 8 })
    } catch (error) {
      ElMessage.error(error?.message || '重装系统失败')
    } finally {
      actionLoading.value = false
    }
  }

  async function handleFetchModuleStatus(type) {
    actionLoading.value = true
    try {
      if (type === 'host') {
        await refreshHostStatusSnapshot()
        ElMessage.success('实例状态已更新')
      } else {
        const taskStatus = await fetchTaskStatus(type)
        if (taskStatus?.is_finished) {
          try {
            await refreshHostStatusSnapshot()
            ElMessage.success('任务已完成，实例状态已同步')
          } catch {
            ElMessage.success('任务状态已刷新')
          }
        } else {
          ElMessage.success('任务状态已刷新')
        }
      }
    } catch (error) {
      ElMessage.error(error?.message || '读取任务状态失败')
    } finally {
      actionLoading.value = false
    }
  }

  async function requestVncUrl() {
    const res = await clientApi.serviceVnc(serviceId.value, { silentError: true })
    const url = String(res.data?.url || '').trim()
    if (!url) throw new Error('未获取到可用的 VNC 地址')
    const decoratedUrl = decorateVncUrl(url)
    storeVncCredentialsForUrl(decoratedUrl, res.data?.vnc_credentials)
    return decoratedUrl
  }

  function renderVncWindowLoading(targetWindow) {
    if (!targetWindow) return
    try {
      targetWindow.document.title = 'VNC 控制台'
      targetWindow.document.body.innerHTML = `
        <div style="margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;background:#0f172a;color:#e2e8f0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;">
          <div style="text-align:center;">
            <div style="font-size:16px;font-weight:600;margin-bottom:10px;">正在打开 VNC 控制台...</div>
            <div style="font-size:13px;color:#94a3b8;">请稍候，正在申请新的连接地址</div>
          </div>
        </div>
      `
    } catch {}
  }

  async function openVncNewWindow() {
    const popup = window.open('', '_blank')
    if (!popup) { ElMessage.warning('浏览器拦截了新窗口，请允许弹窗后重试'); return }
    vncWindowLoading.value = true
    renderVncWindowLoading(popup)
    try {
      const freshUrl = await requestVncUrl()
      popup.location.replace(freshUrl)
    } catch (error) {
      try { popup.close() } catch {}
      ElMessage.error(error?.message || '打开新窗口失败')
    } finally {
      vncWindowLoading.value = false
    }
  }

  async function handleOpenVnc() {
    actionLoading.value = true
    try {
      vncUrl.value = await requestVncUrl()
      activeTab.value = 'vnc'
    } catch (error) {
      ElMessage.error(error?.message || '获取 VNC 地址失败')
    } finally {
      actionLoading.value = false
    }
  }

  async function handleMoreCommand(command) {
    switch (command) {
      case 'password': passwordDialogVisible.value = true; break
      case 'reinstall': await openReinstallDialog(); break
      case 'hard_off': await handlePowerAction('hard_off'); break
      case 'hard_reboot': await handlePowerAction('hard_reboot'); break
      default: break
    }
  }

  // ── Monitor actions ───────────────────────────────────────────────────────

  async function loadMonitor(_forceRefresh = false) {
    const requestSequence = ++monitorLoadSequence
    abortPendingMonitorRequests()
    const range = monitorState.range
    const requestParams = { range }
    monitorState.loading = true
    monitorState.window = null
    monitorState.supported = true
    monitorState.message = ''
    monitorState.error = ''

    // 用已有 options 或 fallback 初始化卡位（全部 loading）
    const initialOptions = monitorState.options.length
      ? monitorState.options
      : MONITOR_FALLBACK_CARDS.map((c) => ({ value: c.type, label: c.label }))
    primeMonitorChartSlots(initialOptions)

    const activeOptions = resolveMonitorChartSource(initialOptions).map((item) => ({
      value: String(item?.type || item?.value || '').trim(),
      label: String(item?.label || item?.value || '').trim(),
    }))
    monitorState.options = activeOptions

    if (_forceRefresh) {
      requestParams.fresh = 1
    }

    const requestTypes = activeOptions
      .map((item) => String(item?.value || '').trim())
      .filter((type) => type !== '')

    if (requestTypes.length) {
      requestParams.types = requestTypes
    } else {
      requestParams.limit = MONITOR_CHART_REQUEST_LIMIT
    }

    const controller = new AbortController()
    monitorAbortControllers.push(controller)
    try {
      const response = await clientApi.serviceMonitorBatch(
        serviceId.value,
        requestParams,
        { timeout: MONITOR_REQUEST_TIMEOUT, signal: controller.signal }
      )
      if (requestSequence !== monitorLoadSequence) return

      const payload = response.data && typeof response.data === 'object' ? response.data : {}
      monitorState.window = payload.range || monitorState.window

      const responseOptions = Array.isArray(payload.options) && payload.options.length
        ? payload.options
        : activeOptions
      const resolvedOptions = resolveMonitorChartSource(responseOptions).map((item) => ({
        value: String(item?.type || item?.value || '').trim(),
        label: String(item?.label || item?.value || '').trim(),
      }))
      monitorState.options = resolvedOptions

      if (payload.supported === false) {
        monitorState.supported = false
        monitorState.message = String(payload.message || '当前服务暂不支持监控图表')
        monitorState.charts = []
        return
      }

      const chartMap = new Map(
        (Array.isArray(payload.charts) ? payload.charts : [])
          .map((item) => [String(item?.type || '').trim(), item])
          .filter(([type]) => type !== '')
      )

      monitorState.charts = buildMonitorChartSlots(resolvedOptions, { reset: true, loading: false }).map((slot) => {
        const chartPayload = chartMap.get(slot.type)
        const chartItem = buildMonitorChartItem(chartPayload, slot.type)

        if (chartItem) {
          return {
            ...slot,
            ...chartItem,
            key: slot.key,
            loading: false,
            error: String(chartPayload?.error || ''),
            message: chartItem.message || String(chartPayload?.message || '基于上游采样数据生成'),
          }
        }

        return {
          ...slot,
          loading: false,
          error: String(chartPayload?.error || ''),
          message: String(chartPayload?.message || payload.message || '当前时间范围内暂无监控数据'),
        }
      })

      const allFailed = monitorState.charts.length > 0 && monitorState.charts.every((item) => item.error)
      if (String(payload.error || '') && (allFailed || !monitorState.charts.length)) {
        monitorState.error = String(payload.error || '')
      } else if (allFailed) {
        monitorState.error = '加载监控数据失败'
      }
    } catch (error) {
      if (isRequestCanceled(error)) return
      if (requestSequence !== monitorLoadSequence) return
      const errorText = resolveRequestErrorMessage(error, '加载监控数据失败')
      monitorState.error = errorText
      monitorState.charts = buildMonitorChartSlots(activeOptions, { reset: false, loading: false, errorText })
    } finally {
      monitorAbortControllers = monitorAbortControllers.filter((item) => item !== controller)
      if (requestSequence === monitorLoadSequence) {
        monitorState.loading = false
      }
    }
  }

  function handleMonitorRangeChange() {
    clearMonitorRangeChangeTimer()
    monitorRangeChangeTimer = setTimeout(() => {
      monitorRangeChangeTimer = null
      loadMonitor(false)
    }, 180)
  }

  // ── NAT actions ───────────────────────────────────────────────────────────
  async function loadNatForwardings() {
    natState.loading = true
    try {
      const res = await clientApi.serviceNatForwardings(serviceId.value)
      natState.supported = res.data?.supported !== false
      natState.message = String(res.data?.message || '')
      natState.error = String(res.data?.error || '')
      natState.can_create = Boolean(res.data?.can_create)
      natState.protocols = Array.isArray(res.data?.protocols) ? res.data.protocols : []
      natState.list = Array.isArray(res.data?.list) ? res.data.list : []
      if (!natForm.protocol && natState.protocols.length) natForm.protocol = String(natState.protocols[0].value || '')
    } catch (error) {
      natState.error = resolveRequestErrorMessage(error, '加载 NAT 转发失败')
    } finally {
      natState.loading = false
    }
  }

  async function submitNatForwarding() {
    natState.submitting = true
    try {
      const res = await clientApi.createNatForwarding(serviceId.value, {
        name: natForm.name.trim(),
        ext_port: natForm.ext_port.trim() || undefined,
        int_port: Number(natForm.int_port),
        protocol: natForm.protocol,
      })
      natState.list = Array.isArray(res.data?.detail?.list) ? res.data.detail.list : natState.list
      natForm.name = ''
      natForm.ext_port = ''
      natForm.int_port = ''
      ElMessage.success(res.data?.message || '端口转发创建成功')
    } catch (error) {
      ElMessage.error(resolveRequestErrorMessage(error, '创建 NAT 转发失败'))
    } finally {
      natState.submitting = false
    }
  }

  async function handleDeleteNatForwarding(row) {
    try {
      const { ElMessageBox } = await import('element-plus')
      await ElMessageBox.confirm(`确认删除转发规则"${row.name}"吗？`, '删除端口转发', {
        confirmButtonText: '确认删除', cancelButtonText: '取消', type: 'warning',
      })
    } catch { return }
    natState.submitting = true
    try {
      const res = await clientApi.deleteNatForwarding(serviceId.value, row.id)
      natState.list = Array.isArray(res.data?.detail?.list) ? res.data.detail.list : natState.list.filter((item) => item.id !== row.id)
      ElMessage.success(res.data?.message || '端口转发已删除')
    } catch (error) {
      ElMessage.error(resolveRequestErrorMessage(error, '删除 NAT 转发失败'))
    } finally {
      natState.submitting = false
    }
  }

  // ── Security group actions ────────────────────────────────────────────────
  async function loadSecurityGroups(options = {}) {
    securityState.loading = true
    try {
      const params = options.fresh ? { fresh: 1 } : undefined
      const res = await clientApi.serviceSecurityGroups(serviceId.value, params)
      securityState.supported = res.data?.supported !== false
      securityState.message = String(res.data?.message || '')
      securityState.error = String(res.data?.error || '')
      securityState.directions = Array.isArray(res.data?.directions) ? res.data.directions : []
      securityState.protocols = Array.isArray(res.data?.protocols) ? res.data.protocols : []
      securityState.groups = sortSecurityGroups(Array.isArray(res.data?.groups) ? res.data.groups : [])
      if (!ruleDialogVisible.value) {
        resetRuleFormState()
      }
      const preferred = securityState.groups.find((item) => item.is_applied) || securityState.groups[0]
      if (preferred) {
        void selectSecurityGroup(preferred, true)
      } else {
        activeSecurityGroupId.value = 0
        securityRules.value = []
      }
    } catch (error) {
      securityState.error = error?.message || '加载安全组失败'
    } finally {
      securityState.loading = false
    }
  }

  async function selectSecurityGroup(group, silent = false) {
    activeSecurityGroupId.value = Number(group?.id || 0)
    if (!activeSecurityGroupId.value) { securityRules.value = []; return }
    securityState.rulesLoading = true
    try {
      const res = await clientApi.serviceSecurityGroupRules(serviceId.value, activeSecurityGroupId.value)
      securityRules.value = Array.isArray(res.data?.list) ? res.data.list : []
      if (!silent) ElMessage.success('安全组规则已加载')
    } catch (error) {
      ElMessage.error(error?.message || '加载安全组规则失败')
    } finally {
      securityState.rulesLoading = false
    }
  }

  async function submitSecurityGroup() {
    securityState.submitting = true
    try {
      const res = await clientApi.createSecurityGroup(serviceId.value, {
        name: groupForm.name.trim(),
        description: groupForm.description.trim(),
      })
      groupDialogVisible.value = false
      resetGroupFormState()
      ElMessage.success(res.data?.message || '安全组创建成功')
      await loadSecurityGroups()
    } catch (error) {
      ElMessage.error(error?.message || '创建安全组失败')
    } finally {
      securityState.submitting = false
    }
  }

  async function handleApplySecurityGroup(group) {
    securityState.submitting = true
    try {
      const res = await clientApi.applySecurityGroup(serviceId.value, group.id)
      ElMessage.success(res.data?.message || '安全组已应用')
      await loadSecurityGroups()
    } catch (error) {
      ElMessage.error(error?.message || '应用安全组失败')
    } finally {
      securityState.submitting = false
    }
  }

  async function handleDeleteSecurityGroup(group) {
    try {
      const { ElMessageBox } = await import('element-plus')
      await ElMessageBox.confirm(`确认删除安全组"${group.name}"吗？`, '删除安全组', {
        confirmButtonText: '确认删除', cancelButtonText: '取消', type: 'warning',
      })
    } catch { return }
    securityState.submitting = true
    try {
      const res = await clientApi.deleteSecurityGroup(serviceId.value, group.id)
      ElMessage.success(res.data?.message || '安全组已删除')
      await loadSecurityGroups()
    } catch (error) {
      ElMessage.error(error?.message || '删除安全组失败')
    } finally {
      securityState.submitting = false
    }
  }

  async function submitSecurityRule() {
    securityState.submitting = true
    try {
      const res = await clientApi.createSecurityRule(serviceId.value, activeSecurityGroupId.value, {
        direction: ruleForm.direction,
        protocol: ruleForm.protocol,
        port: ruleForm.port.trim(),
        ip: ruleForm.ip.trim(),
        description: ruleForm.description.trim(),
      })
      ruleDialogVisible.value = false
      resetRuleFormState()
      ElMessage.success(res.data?.message || '安全组规则创建成功')
      await selectSecurityGroup(activeSecurityGroup.value, true)
    } catch (error) {
      ElMessage.error(error?.message || '创建安全组规则失败')
    } finally {
      securityState.submitting = false
    }
  }

  async function handleDeleteSecurityRule(rule) {
    try {
      const { ElMessageBox } = await import('element-plus')
      await ElMessageBox.confirm('确认删除该安全组规则吗？', '删除规则', {
        confirmButtonText: '确认删除', cancelButtonText: '取消', type: 'warning',
      })
    } catch { return }
    securityState.submitting = true
    try {
      const res = await clientApi.deleteSecurityRule(serviceId.value, activeSecurityGroupId.value, rule.id)
      ElMessage.success(res.data?.message || '安全组规则已删除')
      await selectSecurityGroup(activeSecurityGroup.value, true)
    } catch (error) {
      ElMessage.error(error?.message || '删除安全组规则失败')
    } finally {
      securityState.submitting = false
    }
  }

  // ── Log actions ───────────────────────────────────────────────────────────
  async function loadLogs() {
    logsState.loading = true
    try {
      const params = { page: logsState.page, page_size: logsState.page_size }
      if (logsState.category) params.category = logsState.category
      if (logsState.keyword.trim()) params.keyword = logsState.keyword.trim()
      const res = await clientApi.serviceOperationLogs(serviceId.value, params)
      logsState.list = Array.isArray(res.data?.list) ? res.data.list : []
      logsState.total = Number(res.data?.total || 0)
      logsState.summary.total = Number(res.data?.summary?.total || res.data?.total || 0)
      logsState.summary.today_total = Number(res.data?.summary?.today_total || 0)
      logsState.summary.latest_created_at = String(res.data?.summary?.latest_created_at || '')
      logsState.summary.service_name = String(res.data?.summary?.service_name || '')
    } catch (error) {
      ElMessage.error(error?.message || '加载操作日志失败')
    } finally {
      logsState.loading = false
    }
  }

  function reloadLogs() { logsState.page = 1; loadLogs() }
  function resetLogFilters() { logsState.category = ''; logsState.keyword = ''; reloadLogs() }
  function handleLogPageSizeChange() { logsState.page = 1; loadLogs() }

  // ── Return ─────────────────────────────────────────────────────────────────
  return {
    // state
    detail, detailLoading, detailRefreshing, statusSyncing, actionLoading,
    vncWindowLoading, autoRenewLoading, showPassword, activeTab, vncUrl,
    taskState,
    renewDialogVisible, renewState,
    trafficPackageDialogVisible, trafficPackageState,
    nameDialogVisible, nameSubmitting, nameForm,
    remarkDialogVisible, remarkSubmitting, remarkForm,
    passwordDialogVisible, passwordForm, passwordRules,
    reinstallDialogVisible, reinstallState, reinstallRules,
    monitorState,
    natState, natForm, natRules,
    securityState, activeSecurityGroupId, securityRules,
    groupDialogVisible, groupForm, groupRules,
    ruleDialogVisible, ruleForm, ruleRules,
    logsState,
    // computed
    serviceId, canUseNatForwarding, canManageConsole, canSyncStatus,
    resolvedPassword, renewAmount, renewAvailableCoupons, canPurchaseTrafficPackage, trafficPackageChoices, trafficPackageAmount, reinstallGroupedOptions, currentReinstallOptions,
    taskStatuses, activeSecurityGroup, isNatConsole, serviceRegion, serviceOs,
    primaryConnectionLabel, primaryConnectionText, connectionPortLabel,
    connectionEndpointLabel, connectionEndpointText, connectionPortText, serviceIpCount, bandwidthText, renewPriceText,
    autoRenewLabel, monitorWindow, logCategoryOptions,
    // helpers
    findSpecValue, resolveSecurityGroupRowClassName,
    // handlers
    handleSyncStatus, handlePowerAction, handleToggleAutoRenew,
    openRenewDialog, handleRenewCycleChange, handleRenewCouponChange, submitRenew,
    openTrafficPackageDialog, handleTrafficPackageChoiceChange, handleTrafficPackageQtyChange, submitTrafficPackageOrder,
    openNameDialog, submitName,
    openRemarkDialog, submitRemark,
    submitResetPassword,
    openReinstallDialog, handleReinstallGroupChange, submitReinstall,
    handleFetchModuleStatus,
    handleOpenVnc, openVncNewWindow,
    handleMoreCommand,
    loadMonitor, handleMonitorRangeChange,
    loadNatForwardings, submitNatForwarding, handleDeleteNatForwarding,
    loadSecurityGroups, selectSecurityGroup,
    submitSecurityGroup, handleApplySecurityGroup, handleDeleteSecurityGroup,
    submitSecurityRule, handleDeleteSecurityRule,
    loadLogs, reloadLogs, resetLogFilters, handleLogPageSizeChange,
  }
}
