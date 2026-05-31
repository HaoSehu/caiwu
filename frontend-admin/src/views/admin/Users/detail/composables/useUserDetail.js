import { computed, onUnmounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { INVOICE_TYPE_MAP, SERVICE_STATUS_MAP, toSelectOptions } from '@shared/statusConfig'
import productApi from '@/api/product'
import supplierApi from '@/api/supplier'
import userApi from '@/api/user'
import { PROVIDER_KEYS, providerTypeLabel } from '@/constants/providerTypes'
import { formatDateTime, parseDateTime } from '@/utils/datetime'
import { buildClientLoginAsUrl } from './loginAsUrl.js'

const INVOICE_STATUS_LABELS = {
  0: '待支付',
  1: '已支付',
  2: '已取消',
  3: '已逾期',
  5: '已退款',
}

const INVOICE_TYPE_LABELS = {
  ...INVOICE_TYPE_MAP,
  upgrade: '升降级账单',
}

const BALANCE_TYPE_LABELS = {
  recharge: '充值',
  consume: '消费',
  invoice_payment: '账单支付',
  refund: '退款',
  invoice_refund: '账单退款',
  adjust: '调整',
  system_adjustment: '系统调账',
  admin_deduct: '管理员扣款',
  manual_deduction: '手动扣款',
  manual_recharge: '手动充值',
  referral_withdraw_approved: '奖励转余额',
  referral_credit_cash: '奖励转余额',
}

const PRIORITY_LABELS = {
  1: '低',
  2: '中',
  3: '高',
  4: '紧急',
}

const TICKET_STATUS_LABELS = {
  0: '待处理',
  1: '用户回复',
  2: '客服回复',
  3: '已关闭',
}

const NOTICE_STATUS_LABELS = {
  success: '成功',
  failed: '失败',
  pending: '待发送',
}

const NOTICE_STATUS_TAG_TYPES = {
  success: 'success',
  failed: 'danger',
  pending: 'warning',
}

const BILLING_CYCLE_LABELS = {
  monthly: '月付',
  quarterly: '季付',
  semiannually: '半年付',
  annually: '年付',
  biennially: '两年付',
  triennially: '三年付',
  one_time: '一次性',
}

const serviceStatusOptions = toSelectOptions(SERVICE_STATUS_MAP, false)

const CONSOLE_POWER_STATUS_SYNC_DELAY_MS = 1500
const CONSOLE_POWER_STATUS_SYNC_INTERVAL_MS = 3000
const CONSOLE_POWER_STATUS_SYNC_ATTEMPTS = 6
const POWER_ACTION_RUNTIME_SNAPSHOTS = {
  on: { power_state: 'starting', power_label: '开机中', description: '开机中' },
  off: { power_state: 'stopping', power_label: '关机中', description: '关机中' },
  hard_off: { power_state: 'stopping', power_label: '关机中', description: '关机中' },
  reboot: { power_state: 'rebooting', power_label: '重启中', description: '重启中' },
  hard_reboot: { power_state: 'rebooting', power_label: '重启中', description: '重启中' },
}

function createEmptyUser() {
  return {
    id: 0,
    email: '',
    nickname: '',
    display_name: '',
    phone: '',
    company: '',
    qq: '',
    admin_note: '',
    referrer_user_id: null,
    balance: 0,
    credit_limit: 0,
    status: 1,
    is_verified: 0,
    real_name: '',
    id_card_masked: '',
    verification_status: null,
    last_login_at: '',
    last_login_ip: '',
    created_at: '',
    member_level: null,
  }
}

function createEmptyStats() {
  return {
    service_active: 0,
    service_total: 0,
    order_total: 0,
    order_pending: 0,
    total_income: 0,
    total_expense: 0,
    unpaid_amount: 0,
    ticket_open: 0,
    ticket_closed: 0,
    ticket_total: 0,
    invoice_unpaid: 0,
    invoice_paid: 0,
    direct_referral_count: 0,
    rewarded_orders_count: 0,
    total_referral_reward: 0,
  }
}

function createEmptyReferral() {
  return {
    referral_code: '',
    referrer_user_id: null,
    member_level: null,
    total_sales_amount: 0,
    referral_frozen_amount: 0,
    referral_available_amount: 0,
    referral_withdrawing_amount: 0,
    referral_withdrawn_amount: 0,
    recent_referrals: [],
  }
}

function createEmptyServiceConsoleDetail() {
  return {
    id: 0,
    name: '',
    domain: '',
    status: 0,
    status_label: '',
    status_tone: 'info',
    billing_cycle: '',
    billing_cycle_label: '',
    amount: '0.00',
    has_locked_pricing: false,
    renew_pricing_cycles: [],
    expires_at: '',
    created_at: '',
    product: { id: 0, name: '', type: '', type_label: '', group_name: '' },
    order: { id: 0, order_no: '', invoice_id: 0, invoice_no: '', status: 0, status_label: '', paid_at: '' },
    invoice: { id: 0, invoice_no: '', status: 0, paid_at: '' },
    upstream: { provider: '', supplier_id: 0, host_id: 0, status: '', status_label: '', remote_error: '', dedicated_ip: '' },
    runtime: { power_state: '', power_label: '', description: '' },
    connection: {
      hostname: '',
      username: '',
      password: '',
      has_password: false,
      port: 0,
      dedicated_ip: '',
      internal_ip: '',
      assigned_ips: [],
    },
    specs: [],
    actions: {
      refresh: true,
      power: false,
      module_status: false,
      manual_provision: false,
      password_reset: false,
      reinstall: false,
      available: [],
    },
  }
}

function normalizeServiceConsoleDetail(payload = {}) {
  const empty = createEmptyServiceConsoleDetail()
  return {
    ...empty,
    ...payload,
    product: { ...empty.product, ...(payload.product || {}) },
    order: { ...empty.order, ...(payload.order || {}) },
    invoice: { ...empty.invoice, ...(payload.invoice || {}) },
    upstream: { ...empty.upstream, ...(payload.upstream || {}) },
    runtime: { ...empty.runtime, ...(payload.runtime || {}) },
    connection: { ...empty.connection, ...(payload.connection || {}) },
    actions: { ...empty.actions, ...(payload.actions || {}) },
    specs: Array.isArray(payload.specs) ? payload.specs : [],
  }
}

function mergeServiceConsoleDetail(current = {}, patch = {}) {
  return normalizeServiceConsoleDetail({
    ...current,
    ...patch,
    product: { ...(current.product || {}), ...(patch.product || {}) },
    order: { ...(current.order || {}), ...(patch.order || {}) },
    invoice: { ...(current.invoice || {}), ...(patch.invoice || {}) },
    upstream: { ...(current.upstream || {}), ...(patch.upstream || {}) },
    runtime: { ...(current.runtime || {}), ...(patch.runtime || {}) },
    connection: { ...(current.connection || {}), ...(patch.connection || {}) },
    actions: { ...(current.actions || {}), ...(patch.actions || {}) },
  })
}

function applyOptimisticServiceConsolePowerSnapshot(currentDetail = {}, action) {
  const snapshot = POWER_ACTION_RUNTIME_SNAPSHOTS[String(action || '').trim()]
  if (!snapshot) {
    return normalizeServiceConsoleDetail(currentDetail)
  }

  return mergeServiceConsoleDetail(currentDetail, {
    runtime: {
      power_state: snapshot.power_state,
      power_label: snapshot.power_label,
      description: snapshot.description,
    },
  })
}

function isPendingConsolePowerState(value) {
  return ['process', 'task', 'starting', 'booting', 'stopping', 'shutting_down', 'reboot', 'rebooting']
    .includes(String(value || '').trim().toLowerCase())
}

function createDefaultAddServiceForm() {
  return {
    source_type: 'manual',
    product_id: null,
    billing_cycle: '',
    status: 1,
    name: '',
    amount: null,
    auto_renew: 1,
    upstream_host_id: null,
    upstream_status: '',
    os: '',
    remark: '',
  }
}

function resolveBillingCycleLabel(value) {
  return BILLING_CYCLE_LABELS[value] || value || '-'
}

export function useUserDetail() {
  const route = useRoute()
  const router = useRouter()

  const detailLoading = ref(false)
  const saveLoading = ref(false)
  const actionLoading = ref(false)
  const rechargeLoading = ref(false)
  const loginAsLoading = ref(false)

  const activeTab = ref('basic')
  const editDialogVisible = ref(false)
  const rechargeVisible = ref(false)

  const addServiceDialogVisible = ref(false)
  const addServiceSubmitting = ref(false)
  const addServiceProductsLoading = ref(false)
  const addServiceProductDetailLoading = ref(false)
  const addServiceFormRef = ref()

  const serviceUpstreamDialogVisible = ref(false)
  const serviceUpstreamSubmitting = ref(false)
  const serviceUpstreamSuppliersLoading = ref(false)
  const serviceUpstreamFormRef = ref()

  const servicePricingDialogVisible = ref(false)
  const servicePricingSubmitting = ref(false)
  const servicePricingFormRef = ref()
  const serviceNameDialogVisible = ref(false)
  const serviceNameSubmitting = ref(false)

  const userDetail = ref(createEmptyUser())
  const stats = ref(createEmptyStats())
  const referral = ref(createEmptyReferral())

  const editFormRef = ref()
  const editForm = reactive({
    nickname: '',
    phone: '',
    password: '',
    status: 1,
    credit_limit: 0,
  })

  const rechargeFormRef = ref()
  const rechargeForm = reactive({
    type: 'increase',
    amount: 100,
    remark: '',
    email: '',
  })

  const addServiceProductOptions = ref([])
  const addServiceProductDetail = ref(null)
  const addServiceOsOptions = ref([])
  const addServiceOsLoading = ref(false)
  const addServiceForm = reactive(createDefaultAddServiceForm())
  const addServiceCategoryTree = ref([])
  const addServiceCategoriesLoading = ref(false)
  const addServiceCategoryOptions = ref([])
  const addServiceSelectedCategory = ref(null)
  const addServiceAllProducts = ref([])

  const addServiceSubOptions = computed(() => {
    if (!addServiceSelectedCategory.value) return []
    const typeLabel = String(addServiceSelectedCategory.value)
    const type = addServiceCategoryTree.value.find((c) => c.value === typeLabel)
    if (!type || !type.children) return []
    return type.children
  })

  function handleAddServiceCategoryChange() {
    addServiceForm.product_id = null
    addServiceProductDetail.value = null
  }

  function handleAddServiceSubChange(productId) {
    addServiceForm.product_id = productId || null
    handleAddServiceProductChange()
  }
  const serviceUpstreamSupplierOptions = ref([])
  const serviceUpstreamForm = reactive({
    supplier_id: null,
    upstream_host_id: null,
  })
  const servicePricingForm = reactive({
    amount: null,
    locked_pricing: {},
    clear_locked_pricing: false,
  })
  const serviceNameForm = reactive({
    service_name: '',
  })

  const servicesState = reactive({
    loading: false,
    refreshing: false,
    refreshingStatus: false,
    list: [],
    total: 0,
    page: 1,
    pageSize: 10,
    filters: { keyword: '', status: '' },
  })

  const serviceConsoleState = reactive({
    visible: false,
    loading: false,
    actionLoading: '',
    serviceId: 0,
    detail: createEmptyServiceConsoleDetail(),
  })

  const invoicesState = reactive({
    loading: false,
    list: [],
    total: 0,
    page: 1,
    pageSize: 10,
    filters: { status: '', type: '' },
  })

  const balanceState = reactive({
    loading: false,
    list: [],
    total: 0,
    page: 1,
    pageSize: 10,
  })

  const ticketsState = reactive({
    loading: false,
    list: [],
    total: 0,
    page: 1,
    pageSize: 10,
  })

  const logsState = reactive({
    loading: false,
    list: [],
    total: 0,
    page: 1,
    pageSize: 10,
    filters: { keyword: '' },
  })

  const noticesState = reactive({
    loading: false,
    channel: 'email',
    list: [],
    total: 0,
    page: 1,
    pageSize: 10,
  })

  const loadedTabs = reactive({
    services: false,
    invoices: false,
    balance: false,
    tickets: false,
    logs: false,
    notices: false,
  })

  const userId = computed(() => {
    const id = Number(route.params.id)
    return Number.isFinite(id) && id > 0 ? id : 0
  })

  const pageTitle = computed(() => (
    userDetail.value.nickname
    || userDetail.value.display_name
    || userDetail.value.email
    || `用户 #${userId.value}`
  ))

  const avatarText = computed(() => (pageTitle.value || 'U').slice(0, 1).toUpperCase())
  const statusText = computed(() => (Number(userDetail.value.status) === 1 ? '正常' : '禁用'))
  const statusTagType = computed(() => (Number(userDetail.value.status) === 1 ? 'success' : 'danger'))
  const registeredDaysLabel = computed(() => resolveRegisteredDays(userDetail.value.created_at))

  const statsCards = computed(() => ([
    { label: '活跃服务', value: formatInteger(stats.value.service_active), tone: 'success' },
    { label: '服务总数', value: formatInteger(stats.value.service_total), tone: 'primary' },
    { label: '待处理账单', value: formatInteger(stats.value.order_pending), tone: 'danger' },
    { label: '待付金额', value: formatMoney(stats.value.unpaid_amount), tone: 'danger' },
    { label: '累计收入', value: formatMoney(stats.value.total_income), tone: 'success' },
    { label: '累计支出', value: formatMoney(stats.value.total_expense), tone: 'warning' },
    { label: '未关闭工单', value: formatInteger(stats.value.ticket_open), tone: 'warning' },
    { label: '账单总数', value: formatInteger(stats.value.order_total), tone: 'primary' },
  ]))

  const infoItems = computed(() => ([
    { label: '邮箱', value: userDetail.value.email || '-' },
    { label: '手机号', value: userDetail.value.phone || '-' },
    { label: '公司', value: userDetail.value.company || '-' },
    { label: 'QQ', value: userDetail.value.qq || '-' },
    { label: '账户余额', value: formatMoney(userDetail.value.balance), tone: 'success' },
    { label: '信用额度', value: formatMoney(userDetail.value.credit_limit), tone: 'primary' },
    { label: '会员等级', value: userDetail.value.member_level?.name || '未分级' },
    { label: '实名认证', value: resolveVerificationText(userDetail.value), tone: userDetail.value.is_verified ? 'success' : 'warning' },
    { label: '证件号', value: userDetail.value.id_card_masked || '-' },
    { label: '推荐人 ID', value: userDetail.value.referrer_user_id || '-' },
    { label: '最后登录时间', value: formatDateTime(userDetail.value.last_login_at) },
    { label: '最后登录 IP', value: userDetail.value.last_login_ip || '-' },
  ]))

  const addServiceCanLinkUpstream = computed(() => (
    Number(addServiceProductDetail.value?.supplier_id || 0) > 0
    && Number(addServiceProductDetail.value?.supplier_product_id || 0) > 0
  ))

  const addServiceBillingOptions = computed(() => {
    const pricing = addServiceProductDetail.value?.pricing || {}
    return Object.entries(pricing)
      .filter(([, amount]) => Number(amount) > 0)
      .map(([value, amount]) => ({
        value,
        label: `${resolveBillingCycleLabel(value)} · ¥${toNumber(amount).toFixed(2)}`,
        amount: toNumber(amount),
      }))
  })

  const addServiceUpstreamChannel = computed(() => (
    addServiceProductDetail.value?.supplier_name || '-'
  ))

  const servicePricingEntries = computed(() => (
    Object.entries(servicePricingForm.locked_pricing || {}).map(([cycle, item]) => ({
      cycle,
      label: resolveBillingCycleLabel(cycle),
      enabled: Boolean(item?.enabled),
      base_amount: item?.base_amount || null,
      manual_amount: item?.manual_amount ?? '',
    }))
  ))

  const editRules = {
    password: [{
      validator: (_rule, value, callback) => {
        if (value && value.length < 6) {
          callback(new Error('密码至少需要 6 位'))
          return
        }
        callback()
      },
      trigger: 'blur',
    }],
  }

  const rechargeRules = {
    amount: [{ required: true, message: '请输入金额', trigger: 'blur' }],
    remark: [{ required: true, message: '请填写操作备注', trigger: 'blur' }],
  }

  const addServiceRules = {
    product_id: [{ required: true, message: '请选择商品', trigger: 'change' }],
    billing_cycle: [{ required: true, message: '请选择计费周期', trigger: 'change' }],
    status: [{ required: true, message: '请选择服务状态', trigger: 'change' }],
    amount: [{ required: true, message: '请输入服务金额', trigger: 'blur' }],
    upstream_host_id: [{
      validator: (_rule, value, callback) => {
        if (addServiceForm.source_type === 'upstream' && (!value || Number(value) <= 0)) {
          callback(new Error('请输入有效的上游实例 ID'))
          return
        }
        callback()
      },
      trigger: 'blur',
    }],
  }

  const serviceUpstreamRules = {
    supplier_id: [{
      validator: (_rule, value, callback) => {
        const hostId = Number(serviceUpstreamForm.upstream_host_id || 0)
        if (hostId > 0 && (!value || Number(value) <= 0)) {
          callback(new Error('填写上游实例 ID 时必须选择上游接口'))
          return
        }
        if (value && Number(value) > 0 && hostId <= 0) {
          callback(new Error('重新绑定上游接口时必须填写新的上游实例 ID'))
          return
        }
        callback()
      },
      trigger: 'change',
    }],
    upstream_host_id: [{
      validator: (_rule, value, callback) => {
        if (value === null || value === '' || typeof value === 'undefined') {
          callback()
          return
        }
        if (Number(value) <= 0) {
          callback(new Error('请输入有效的上游实例 ID'))
          return
        }
        callback()
      },
      trigger: 'blur',
    }],
  }

  const servicePricingRules = {
    amount: [{
      required: true,
      message: '请输入购买价格',
      trigger: 'blur',
    }],
  }

  let consolePowerStatusSyncTimer = null
  let consolePowerStatusSyncToken = 0

  watch(userId, async (id) => {
    if (!id) {
      await router.replace('/admin/users')
      return
    }

    resetConsolePowerStatusSync()
    activeTab.value = 'basic'
    Object.assign(loadedTabs, {
      services: false,
      invoices: false,
      balance: false,
      tickets: false,
      logs: false,
      notices: false,
    })

    await loadDetail(id)
  }, { immediate: true })

  function clearConsolePowerStatusSyncTimer() {
    if (consolePowerStatusSyncTimer) {
      clearTimeout(consolePowerStatusSyncTimer)
      consolePowerStatusSyncTimer = null
    }
  }

  function resetConsolePowerStatusSync() {
    consolePowerStatusSyncToken += 1
    clearConsolePowerStatusSyncTimer()
  }

  function patchServiceConsoleDetail(detail) {
    const nextDetail = mergeServiceConsoleDetail(serviceConsoleState.detail, detail)
    serviceConsoleState.detail = nextDetail
    patchServiceListItem(nextDetail)
    return nextDetail
  }

  async function refreshServiceConsoleRemoteStatus(options = {}) {
    if (!userId.value || !serviceConsoleState.serviceId) return false

    try {
      const res = await userApi.serviceRemoteStatus(userId.value, serviceConsoleState.serviceId)
      patchServiceConsoleDetail(res.data || {})
      if (!options.silent) {
        ElMessage.success('远程状态已刷新')
      }
      return true
    } catch (error) {
      if (!options.silent) {
        ElMessage.error(error?.response?.data?.message || '刷新远程状态失败')
      }
      return false
    }
  }

  function queueConsolePowerStatusSync(options = {}) {
    if (!serviceConsoleState.serviceId) return

    resetConsolePowerStatusSync()
    const token = consolePowerStatusSyncToken
    const attempts = Number.isFinite(Number(options.attempts))
      ? Math.max(1, Number(options.attempts))
      : CONSOLE_POWER_STATUS_SYNC_ATTEMPTS
    const initialDelay = Number.isFinite(Number(options.initialDelay))
      ? Math.max(0, Number(options.initialDelay))
      : CONSOLE_POWER_STATUS_SYNC_DELAY_MS
    const interval = Number.isFinite(Number(options.interval))
      ? Math.max(500, Number(options.interval))
      : CONSOLE_POWER_STATUS_SYNC_INTERVAL_MS

    const run = async (attempt) => {
      if (token !== consolePowerStatusSyncToken || !serviceConsoleState.visible || !serviceConsoleState.serviceId) {
        return
      }

      await refreshServiceConsoleRemoteStatus({ silent: true })

      if (attempt >= attempts || !isPendingConsolePowerState(serviceConsoleState.detail?.runtime?.power_state) || token !== consolePowerStatusSyncToken) {
        clearConsolePowerStatusSyncTimer()
        return
      }

      consolePowerStatusSyncTimer = setTimeout(() => {
        void run(attempt + 1)
      }, interval)
    }

    consolePowerStatusSyncTimer = setTimeout(() => {
      void run(1)
    }, initialDelay)
  }

  onUnmounted(() => {
    resetConsolePowerStatusSync()
  })

  function syncEditForm() {
    editForm.nickname = userDetail.value.nickname || ''
    editForm.phone = userDetail.value.phone || ''
    editForm.password = ''
    editForm.status = Number(userDetail.value.status ?? 1)
    editForm.credit_limit = toNumber(userDetail.value.credit_limit)
  }

  function resetAddServiceForm() {
    Object.assign(addServiceForm, createDefaultAddServiceForm())
    addServiceProductDetail.value = null
    addServiceSelectedCategory.value = null
    addServiceFormRef.value?.clearValidate?.()
  }

  function createDefaultLockedPricingForm(detail = serviceConsoleState.detail) {
    const cycles = Array.isArray(detail?.renew_pricing_cycles) ? detail.renew_pricing_cycles : []
    return cycles.reduce((result, item) => {
      const cycleKey = String(item?.billing_cycle || '').trim()
      if (!cycleKey) return result
      result[cycleKey] = {
        enabled: Boolean(item?.enabled),
        base_amount: item?.base_amount || null,
        manual_amount: item?.manual_amount || '',
      }
      return result
    }, {})
  }

  async function loadServiceUpstreamSuppliers() {
    if (serviceUpstreamSupplierOptions.value.length) return
    serviceUpstreamSuppliersLoading.value = true
    try {
      const res = await supplierApi.list({
        status: 1,
        page: 1,
        page_size: 100,
      })
      serviceUpstreamSupplierOptions.value = (Array.isArray(res.data?.list) ? res.data.list : [])
        .filter((item) => String(item?.interface_type || '') === PROVIDER_KEYS.HOSTING_PANEL_API)
        .map((item) => ({
          id: Number(item.id),
          name: item.name || `接口 #${item.id}`,
          interface_type: String(item.interface_type || ''),
          label: `${item.name || `接口 #${item.id}`} · ${providerTypeLabel(item.interface_type)}`,
        }))
    } catch (error) {
      ElMessage.error(error?.response?.data?.message || '加载上游接口失败')
    } finally {
      serviceUpstreamSuppliersLoading.value = false
    }
  }

  async function openServiceUpstreamDialog() {
    await loadServiceUpstreamSuppliers()
    const detail = normalizeServiceConsoleDetail(serviceConsoleState.detail)
    serviceUpstreamForm.supplier_id = Number(detail.upstream?.supplier_id || 0) || null
    serviceUpstreamForm.upstream_host_id = Number(detail.upstream?.host_id || 0) || null
    serviceUpstreamDialogVisible.value = true
    serviceUpstreamFormRef.value?.clearValidate?.()
  }

  function closeServiceUpstreamDialog() {
    if (serviceUpstreamSubmitting.value) return
    serviceUpstreamDialogVisible.value = false
    serviceUpstreamFormRef.value?.clearValidate?.()
  }

  async function submitServiceUpstream() {
    if (!userId.value || !serviceConsoleState.serviceId) return

    await serviceUpstreamFormRef.value?.validate()

    const payload = {
      supplier_id: serviceUpstreamForm.supplier_id ? Number(serviceUpstreamForm.supplier_id) : null,
      upstream_host_id: serviceUpstreamForm.upstream_host_id ? Number(serviceUpstreamForm.upstream_host_id) : null,
    }

    serviceUpstreamSubmitting.value = true
    serviceConsoleState.actionLoading = 'meta-update'
    try {
      const res = await userApi.updateServiceMeta(userId.value, serviceConsoleState.serviceId, payload)
      patchServiceConsoleDetail(res.data || {})
      await loadServices()
      await reloadDetail()
      serviceUpstreamDialogVisible.value = false
      ElMessage.success('上游绑定已更新')
    } catch (error) {
      ElMessage.error(error?.response?.data?.message || '更新上游绑定失败')
    } finally {
      serviceUpstreamSubmitting.value = false
      if (serviceConsoleState.actionLoading === 'meta-update') {
        serviceConsoleState.actionLoading = ''
      }
    }
  }

  async function openServicePricingDialog() {
    const detail = normalizeServiceConsoleDetail(serviceConsoleState.detail)
    servicePricingForm.amount = toNumber(detail.amount)
    servicePricingForm.locked_pricing = createDefaultLockedPricingForm(detail)
    servicePricingForm.clear_locked_pricing = false
    servicePricingDialogVisible.value = true
    servicePricingFormRef.value?.clearValidate?.()
  }

  function closeServicePricingDialog() {
    if (servicePricingSubmitting.value) return
    servicePricingDialogVisible.value = false
    servicePricingFormRef.value?.clearValidate?.()
  }

  function openServiceNameDialog() {
    const detail = normalizeServiceConsoleDetail(serviceConsoleState.detail)
    serviceNameForm.service_name = String(detail.custom_service_name || detail.name || '')
    serviceNameDialogVisible.value = true
  }

  function closeServiceNameDialog() {
    if (serviceNameSubmitting.value) return
    serviceNameDialogVisible.value = false
  }

  async function submitServiceName() {
    if (!userId.value || !serviceConsoleState.serviceId) return

    const payload = {
      service_name: String(serviceNameForm.service_name || ''),
    }

    serviceNameSubmitting.value = true
    serviceConsoleState.actionLoading = 'meta-update'
    try {
      const res = await userApi.updateServiceMeta(userId.value, serviceConsoleState.serviceId, payload)
      patchServiceConsoleDetail(res.data || {})
      await loadServices()
      await reloadDetail()
      serviceNameDialogVisible.value = false
      ElMessage.success('实例名称已更新')
    } catch (error) {
      ElMessage.error(error?.response?.data?.message || '更新实例名称失败')
    } finally {
      serviceNameSubmitting.value = false
      if (serviceConsoleState.actionLoading === 'meta-update') {
        serviceConsoleState.actionLoading = ''
      }
    }
  }

  async function submitServicePricing() {
    if (!userId.value || !serviceConsoleState.serviceId) return

    await servicePricingFormRef.value?.validate()

    const payload = {
      amount: toNumber(servicePricingForm.amount),
    }

    if (servicePricingForm.clear_locked_pricing) {
      payload.clear_locked_pricing = true
    } else {
      payload.locked_pricing = Object.entries(servicePricingForm.locked_pricing || {}).reduce((result, [cycle, item]) => {
        result[cycle] = {
          enabled: Boolean(item?.enabled),
          manual_amount: item?.manual_amount === '' || item?.manual_amount === null || typeof item?.manual_amount === 'undefined'
            ? null
            : toNumber(item.manual_amount),
        }
        return result
      }, {})
    }

    servicePricingSubmitting.value = true
    serviceConsoleState.actionLoading = 'meta-update'
    try {
      const res = await userApi.updateServiceMeta(userId.value, serviceConsoleState.serviceId, payload)
      patchServiceConsoleDetail(res.data || {})
      await loadServices()
      await reloadDetail()
      servicePricingDialogVisible.value = false
      ElMessage.success('价格信息已更新')
    } catch (error) {
      ElMessage.error(error?.response?.data?.message || '更新价格信息失败')
    } finally {
      servicePricingSubmitting.value = false
      if (serviceConsoleState.actionLoading === 'meta-update') {
        serviceConsoleState.actionLoading = ''
      }
    }
  }

  function openEditDialog() {
    syncEditForm()
    editDialogVisible.value = true
  }

  async function loadDetail(id = userId.value) {
    detailLoading.value = true
    try {
      const res = await userApi.detail(id)
      userDetail.value = {
        ...createEmptyUser(),
        ...(res.data?.user || {}),
      }
      stats.value = {
        ...createEmptyStats(),
        ...(res.data?.stats || {}),
      }
      referral.value = {
        ...createEmptyReferral(),
        ...(res.data?.referral || {}),
        recent_referrals: Array.isArray(res.data?.referral?.recent_referrals) ? res.data.referral.recent_referrals : [],
      }
      syncEditForm()
    } catch (error) {
      if (error?.response?.status === 404) {
        await router.replace('/admin/users')
      } else {
        ElMessage.error(error?.response?.data?.message || '加载用户详情失败')
      }
    } finally {
      detailLoading.value = false
    }
  }

  async function reloadDetail() {
    await loadDetail()
  }

  async function handleTabChange(tabName) {
    if (tabName === 'services' && !loadedTabs.services) await loadServices()
    if (tabName === 'invoices' && !loadedTabs.invoices) await loadInvoices()
    if (tabName === 'balance' && !loadedTabs.balance) await loadBalance()
    if (tabName === 'tickets' && !loadedTabs.tickets) await loadTickets()
    if (tabName === 'logs' && !loadedTabs.logs) await loadLogs()
    if (tabName === 'notices' && !loadedTabs.notices) await loadNotices()
  }

  async function loadServices() {
    servicesState.loading = true
    try {
      const res = await userApi.services(userId.value, {
        ...servicesState.filters,
        page: servicesState.page,
        page_size: servicesState.pageSize,
      })
      servicesState.list = Array.isArray(res.data?.list) ? res.data.list : []
      servicesState.total = Number(res.data?.total || 0)
      servicesState.page = Number(res.data?.page || servicesState.page)
      servicesState.pageSize = Number(res.data?.page_size || servicesState.pageSize)
      loadedTabs.services = true
    } catch (error) {
      ElMessage.error(error?.response?.data?.message || '加载服务列表失败')
    } finally {
      servicesState.loading = false
    }
  }

  function searchServices() {
    servicesState.page = 1
    void loadServices()
  }

  function resetServicesFilters() {
    servicesState.filters.keyword = ''
    servicesState.filters.status = ''
    servicesState.page = 1
    void loadServices()
  }

  async function loadInvoices() {
    invoicesState.loading = true
    try {
      const res = await userApi.invoices(userId.value, {
        ...invoicesState.filters,
        page: invoicesState.page,
        page_size: invoicesState.pageSize,
      })
      invoicesState.list = Array.isArray(res.data?.list) ? res.data.list : []
      invoicesState.total = Number(res.data?.total || 0)
      invoicesState.page = Number(res.data?.page || invoicesState.page)
      invoicesState.pageSize = Number(res.data?.page_size || invoicesState.pageSize)
      loadedTabs.invoices = true
    } catch (error) {
      ElMessage.error(error?.response?.data?.message || '加载账单列表失败')
    } finally {
      invoicesState.loading = false
    }
  }

  function searchInvoices() {
    invoicesState.page = 1
    void loadInvoices()
  }

  function resetInvoicesFilters() {
    invoicesState.filters.status = ''
    invoicesState.filters.type = ''
    invoicesState.page = 1
    void loadInvoices()
  }

  async function loadBalance() {
    balanceState.loading = true
    try {
      const res = await userApi.balanceLogs(userId.value, {
        page: balanceState.page,
        page_size: balanceState.pageSize,
      })
      balanceState.list = Array.isArray(res.data?.list) ? res.data.list : []
      balanceState.total = Number(res.data?.total || 0)
      balanceState.page = Number(res.data?.page || balanceState.page)
      balanceState.pageSize = Number(res.data?.page_size || balanceState.pageSize)
      loadedTabs.balance = true
    } catch (error) {
      ElMessage.error(error?.response?.data?.message || '加载资金流水失败')
    } finally {
      balanceState.loading = false
    }
  }

  async function loadTickets() {
    ticketsState.loading = true
    try {
      const res = await userApi.tickets(userId.value, {
        page: ticketsState.page,
        page_size: ticketsState.pageSize,
      })
      ticketsState.list = Array.isArray(res.data?.list) ? res.data.list : []
      ticketsState.total = Number(res.data?.total || 0)
      ticketsState.page = Number(res.data?.page || ticketsState.page)
      ticketsState.pageSize = Number(res.data?.page_size || ticketsState.pageSize)
      loadedTabs.tickets = true
    } catch (error) {
      ElMessage.error(error?.response?.data?.message || '加载工单列表失败')
    } finally {
      ticketsState.loading = false
    }
  }

  async function loadLogs() {
    logsState.loading = true
    try {
      const res = await userApi.operationLogs(userId.value, {
        ...logsState.filters,
        page: logsState.page,
        page_size: logsState.pageSize,
      })
      logsState.list = Array.isArray(res.data?.list) ? res.data.list : []
      logsState.total = Number(res.data?.total || 0)
      logsState.page = Number(res.data?.page || logsState.page)
      logsState.pageSize = Number(res.data?.page_size || logsState.pageSize)
      loadedTabs.logs = true
    } catch (error) {
      ElMessage.error(error?.response?.data?.message || '加载操作日志失败')
    } finally {
      logsState.loading = false
    }
  }

  function searchLogs() {
    logsState.page = 1
    void loadLogs()
  }

  async function loadNotices() {
    noticesState.loading = true
    try {
      const method = noticesState.channel === 'email' ? userApi.emailLogs : userApi.smsLogs
      const res = await method(userId.value, {
        page: noticesState.page,
        page_size: noticesState.pageSize,
      })
      noticesState.list = Array.isArray(res.data?.list) ? res.data.list : []
      noticesState.total = Number(res.data?.total || 0)
      noticesState.page = Number(res.data?.page || noticesState.page)
      noticesState.pageSize = Number(res.data?.page_size || noticesState.pageSize)
      loadedTabs.notices = true
    } catch {
      noticesState.list = []
      noticesState.total = 0
    } finally {
      noticesState.loading = false
    }
  }

  function reloadNotices() {
    noticesState.page = 1
    void loadNotices()
  }

  async function handleSave() {
    try {
      await editFormRef.value?.validate()
    } catch {
      return
    }

    saveLoading.value = true
    try {
      const payload = {
        nickname: editForm.nickname,
        phone: editForm.phone,
        status: editForm.status,
        credit_limit: editForm.credit_limit,
        ...(editForm.password.trim() ? { password: editForm.password } : {}),
      }
      await userApi.update(userId.value, payload)
      ElMessage.success('用户资料已更新')
      editDialogVisible.value = false
      await loadDetail()
    } catch (error) {
      ElMessage.error(error?.response?.data?.message || '保存失败')
    } finally {
      saveLoading.value = false
    }
  }

  async function handleToggleStatus() {
    const nextActionText = Number(userDetail.value.status) === 1 ? '禁用' : '启用'

    try {
      await ElMessageBox.confirm(
        `确认${nextActionText}用户“${pageTitle.value}”吗？`,
        `${nextActionText}账号`,
        {
          confirmButtonText: `确认${nextActionText}`,
          cancelButtonText: '取消',
          type: 'warning',
        }
      )
    } catch {
      return
    }

    actionLoading.value = true
    try {
      await userApi.toggleStatus(userId.value)
      ElMessage.success(`用户已${nextActionText}`)
      await loadDetail()
    } catch (error) {
      ElMessage.error(error?.response?.data?.message || `${nextActionText}失败`)
    } finally {
      actionLoading.value = false
    }
  }

  function openRechargeDialog() {
    rechargeForm.email = userDetail.value.email || userDetail.value.phone || '-'
    rechargeForm.type = 'increase'
    rechargeForm.amount = 100
    rechargeForm.remark = ''
    rechargeVisible.value = true
  }

  async function handleRecharge() {
    try {
      await rechargeFormRef.value?.validate()
    } catch {
      return
    }

    const signedAmount = rechargeForm.type === 'decrease' ? -rechargeForm.amount : rechargeForm.amount
    rechargeLoading.value = true
    try {
      await userApi.recharge(userId.value, {
        amount: signedAmount,
        remark: rechargeForm.remark,
      })
      ElMessage.success(rechargeForm.type === 'decrease' ? '扣减成功' : '增加成功')
      rechargeVisible.value = false
      await loadDetail()
      if (loadedTabs.balance) {
        await loadBalance()
      }
    } catch (error) {
      ElMessage.error(error?.response?.data?.message || '操作失败')
    } finally {
      rechargeLoading.value = false
    }
  }

  async function handleLoginAs() {
    if (!userDetail.value.id || loginAsLoading.value) return

    const pendingWindow = window.open('about:blank', '_blank')
    if (pendingWindow) {
      try {
        pendingWindow.opener = null
      } catch {
        // Ignore browser restrictions.
      }
    }

    loginAsLoading.value = true
    try {
      const res = await userApi.loginAs(userDetail.value.id)
      const loginCode = String(res.data?.login_code || '').trim()
      if (!loginCode) {
        throw new Error('未获取到代登录凭证')
      }

      const targetUrl = buildClientLoginAsUrl(loginCode, {
        redirectUrl: res.data?.redirect_url,
      })
      if (pendingWindow && !pendingWindow.closed) {
        try {
          pendingWindow.location.replace(targetUrl)
          pendingWindow.focus?.()
          ElMessage.success('已打开客户端登录页')
          return
        } catch {
          // 跨域导航被阻止时，回退到当前窗口跳转
          pendingWindow.close()
        }
      }

      window.location.href = targetUrl
    } catch (error) {
      if (pendingWindow && !pendingWindow.closed) pendingWindow.close()
      ElMessage.error(error?.response?.data?.message || error?.message || '代登录失败')
    } finally {
      loginAsLoading.value = false
    }
  }

  async function loadAddServiceProducts() {
    addServiceProductsLoading.value = true
    try {
      const res = await productApi.list({ status: 1, page: 1, page_size: 200 })
      addServiceProductOptions.value = Array.isArray(res.data?.list) ? res.data.list : []
    } catch (error) {
      ElMessage.error(error?.response?.data?.message || '加载商品列表失败')
    } finally {
      addServiceProductsLoading.value = false
    }
  }

  async function loadAddServiceCategoryTree() {
    addServiceCategoriesLoading.value = true
    try {
      const [catRes, prodRes] = await Promise.all([
        productApi.categories(),
        productApi.list({ status: 1, page: 1, page_size: 200 }),
      ])

      const rawTree = (catRes?.data?.tree || [])
      const productList = Array.isArray(prodRes?.data?.list) ? prodRes.data.list : []
      addServiceAllProducts.value = productList

      // 按 product_type_label 一级分组
      const typeMap = new Map()
      rawTree.forEach((l1) => {
        if (!l1.children || !l1.children.length) return
        const typeLabel = l1.product_type_label || l1.product_type || l1.name
        if (!typeMap.has(typeLabel)) {
          typeMap.set(typeLabel, [])
        }
        // 把 l1 下的 l2 children 按 type 归并
        l1.children.forEach((l2) => {
          const products = productList
            .filter((p) => Number(p.product_group_id) === Number(l2.id))
            .map((p) => ({ value: p.id, label: p.name }))
          if (!products.length) return
          typeMap.get(typeLabel).push({
            value: l2.id,
            label: l2.name,
            children: products,
          })
        })
      })

      const tree = []
      typeMap.forEach((children, typeLabel) => {
        if (!children.length) return
        tree.push({
          value: typeLabel,
          label: typeLabel,
          children,
        })
      })

      addServiceCategoryTree.value = tree
      addServiceCategoryOptions.value = tree.map(({ value, label }) => ({ value, label }))
    } catch (error) {
      ElMessage.error(error?.response?.data?.message || '加载商品分类失败')
    } finally {
      addServiceCategoriesLoading.value = false
    }
  }

  function syncAddServiceAmountFromCycle() {
    const matchedCycle = addServiceBillingOptions.value.find((item) => item.value === addServiceForm.billing_cycle)
    addServiceForm.amount = matchedCycle ? matchedCycle.amount : null
  }

  async function handleAddServiceProductChange() {
    addServiceProductDetail.value = null
    addServiceForm.billing_cycle = ''
    addServiceForm.amount = null
    addServiceForm.upstream_host_id = null
    addServiceForm.os = ''
    addServiceOsOptions.value = []
    if (!addServiceForm.product_id) return

    addServiceProductDetailLoading.value = true
    try {
      const res = await productApi.detail(addServiceForm.product_id)
      addServiceProductDetail.value = res.data || null
      addServiceForm.name = addServiceForm.name || addServiceProductDetail.value?.name || ''
      const firstCycle = addServiceBillingOptions.value[0]
      addServiceForm.billing_cycle = firstCycle?.value || ''
      syncAddServiceAmountFromCycle()
      if (!addServiceBillingOptions.value.length) {
        ElMessage.warning('当前商品未配置价格，请联系管理员配置')
      }
      if (addServiceForm.source_type === 'upstream' && !addServiceCanLinkUpstream.value) {
        addServiceForm.source_type = 'manual'
      }
    } catch (error) {
      ElMessage.error(error?.response?.data?.message || '加载商品详情失败')
    } finally {
      addServiceProductDetailLoading.value = false
    }

    fetchAddServiceOsOptions()
  }

  async function fetchAddServiceOsOptions() {
    addServiceOsLoading.value = true
    try {
      const res = await userApi.osOptions()
      addServiceOsOptions.value = res?.data?.groups || []
    } catch {
      addServiceOsOptions.value = []
    } finally {
      addServiceOsLoading.value = false
    }
  }

  function handleAddServiceSourceChange() {
    if (addServiceForm.source_type === 'upstream' && !addServiceCanLinkUpstream.value) {
      ElMessage.warning('当前商品未绑定可控上游，无法对接上游主机')
      addServiceForm.source_type = 'manual'
    }
  }

  async function openAddServiceDialog() {
    resetAddServiceForm()
    addServiceDialogVisible.value = true
    if (!addServiceCategoryTree.value.length) {
      await loadAddServiceCategoryTree()
    }
  }

  async function handleSubmitAddService() {
    try {
      await addServiceFormRef.value?.validate()
    } catch {
      return
    }

    const payload = {
      ...addServiceForm,
      product_id: Number(addServiceForm.product_id || 0),
      amount: toNumber(addServiceForm.amount),
      auto_renew: Number(addServiceForm.auto_renew ? 1 : 0),
      upstream_host_id: null,
    }

    addServiceSubmitting.value = true
    try {
      await userApi.storeService(userId.value, payload)
      ElMessage.success('实例已添加')
      addServiceDialogVisible.value = false
      await Promise.all([loadServices(), reloadDetail()])
    } catch (error) {
      ElMessage.error(error?.response?.data?.message || '添加实例失败')
    } finally {
      addServiceSubmitting.value = false
    }
  }

  function goBack() {
    router.push('/admin/users')
  }

  function formatMoney(value) {
    return `¥${toNumber(value).toFixed(2)}`
  }

  function formatInteger(value) {
    return String(Math.round(toNumber(value)))
  }

  function toNumber(value) {
    const number = Number.parseFloat(value ?? 0)
    return Number.isFinite(number) ? number : 0
  }

  function resolveRegisteredDays(dateTime) {
    const parsed = parseDateTime(dateTime)
    if (!parsed) return '-'
    const diff = Date.now() - parsed.getTime()
    const days = Math.max(0, Math.floor(diff / 86400000))
    return `${days} 天`
  }

  function resolveVerificationText(user) {
    if (user.is_verified) {
      return user.real_name ? `已实名认证 / ${user.real_name}` : '已实名认证'
    }
    if (Number(user.verification_status) === 3) return '实名认证失败'
    if (Number(user.verification_status) === 1 || Number(user.verification_status) === 4) return '待实名认证'
    return '未实名认证'
  }

  function resolveInvoiceStatus(status) {
    return INVOICE_STATUS_LABELS[Number(status)] || '-'
  }

  function resolveInvoiceType(type) {
    return INVOICE_TYPE_LABELS[type] || '-'
  }

  function resolveBalanceType(type) {
    return BALANCE_TYPE_LABELS[type] || '-'
  }

  function resolvePriority(priority) {
    return PRIORITY_LABELS[Number(priority)] || '-'
  }

  function resolveTicketStatus(status) {
    return TICKET_STATUS_LABELS[Number(status)] || '-'
  }

  function resolveServiceToneTagType(tone) {
    return ({
      success: 'success',
      warning: 'warning',
      danger: 'danger',
      primary: 'primary',
      info: 'info',
    })[tone] || 'info'
  }

  function noticeStatusLabel(status) {
    return NOTICE_STATUS_LABELS[status] || status || '-'
  }

  function noticeStatusTagType(status) {
    return NOTICE_STATUS_TAG_TYPES[status] || 'info'
  }

  async function handleRefreshService(row) {
    if (!row?.id) return

    servicesState.refreshing = true
    try {
      const res = await userApi.serviceRemoteStatus(userId.value, row.id)
      patchServiceListItem(res.data || {})
      ElMessage.success('服务状态已刷新')
    } catch (error) {
      ElMessage.error(error?.response?.data?.message || '刷新服务状态失败')
    } finally {
      servicesState.refreshing = false
    }
  }

  async function handleRefreshServicesStatus() {
    if (!servicesState.list.length) return

    servicesState.refreshingStatus = true
    try {
      await userApi.refreshServiceStatuses(userId.value, {
        service_ids: servicesState.list.map((item) => item.id),
      })
      await loadServices()
      ElMessage.success('服务状态已批量刷新')
    } catch (error) {
      ElMessage.error(error?.response?.data?.message || '批量刷新失败')
    } finally {
      servicesState.refreshingStatus = false
    }
  }

  async function handleDeleteServiceRow(row) {
    if (!row?.id) return

    try {
      await ElMessageBox.confirm(
        `确认删除实例“${row.name || row.product_display_name || row.product?.display_name || `未配置规格 #${row.id}`}”记录吗？`,
        '删除实例记录',
        {
          confirmButtonText: '确认删除',
          cancelButtonText: '取消',
          type: 'warning',
        }
      )
    } catch {
      return
    }

    servicesState.refreshing = true
    try {
      await userApi.serviceDelete(userId.value, row.id)
      ElMessage.success('实例记录已删除')
      await Promise.all([loadServices(), reloadDetail()])
    } catch (error) {
      ElMessage.error(error?.response?.data?.message || '删除服务记录失败')
    } finally {
      servicesState.refreshing = false
    }
  }

  async function loadServiceConsoleDetail(serviceId) {
    if (!userId.value || !serviceId) return

    serviceConsoleState.loading = true
    try {
      const res = await userApi.serviceDetail(userId.value, serviceId)
      serviceConsoleState.detail = normalizeServiceConsoleDetail(res.data || {})
      patchServiceListItem(res.data || {})
    } catch (error) {
      ElMessage.error(error?.response?.data?.message || '加载实例详情失败')
    } finally {
      serviceConsoleState.loading = false
    }
  }

  async function openServiceConsole(row) {
    if (!row?.id) return

    resetConsolePowerStatusSync()
    serviceConsoleState.serviceId = Number(row.id)
    serviceConsoleState.detail = normalizeServiceConsoleDetail({
      id: row.id,
      name: row.name,
      domain: row.domain,
      status: row.status,
      status_label: row.status_label,
      status_tone: row.status_tone,
      billing_cycle: row.billing_cycle,
      billing_cycle_label: row.billing_cycle_label,
      amount: row.amount,
      expires_at: row.expires_at,
      created_at: row.created_at,
      product: row.product || {},
      order: row.order || {},
      upstream: row.upstream || {},
      connection: {
        dedicated_ip: row.upstream?.dedicated_ip || '',
      },
    })
    serviceConsoleState.visible = true
    await loadServiceConsoleDetail(row.id)
  }

  function closeServiceConsole() {
    resetConsolePowerStatusSync()
    serviceConsoleState.visible = false
    serviceConsoleState.serviceId = 0
    serviceConsoleState.detail = createEmptyServiceConsoleDetail()
    serviceConsoleState.actionLoading = ''
  }

  async function reloadServiceConsole() {
    if (!serviceConsoleState.serviceId) return
    await loadServiceConsoleDetail(serviceConsoleState.serviceId)
  }

  async function handleRefreshConsoleRemoteStatus() {
    if (!serviceConsoleState.serviceId) return

    serviceConsoleState.actionLoading = 'remote-status'
    try {
      await refreshServiceConsoleRemoteStatus()
    } finally {
      serviceConsoleState.actionLoading = ''
    }
  }

  async function handleServicePower(action) {
    if (!serviceConsoleState.serviceId || !action) return

    const actionLabelMap = { on: '开机', off: '关机', reboot: '重启' }
    const label = actionLabelMap[action] || action

    try {
      await ElMessageBox.confirm(`确认对实例执行“${label}”操作?`, `${label}确认`, {
        confirmButtonText: `确认${label}`,
        cancelButtonText: '取消',
        type: 'warning',
      })
    } catch {
      return
    }

    resetConsolePowerStatusSync()
    const previousDetail = normalizeServiceConsoleDetail(serviceConsoleState.detail)
    serviceConsoleState.detail = applyOptimisticServiceConsolePowerSnapshot(serviceConsoleState.detail, action)
    serviceConsoleState.actionLoading = `power:${action}`
    try {
      const res = await userApi.servicePower(userId.value, serviceConsoleState.serviceId, { action })
      if (res.data?.detail) {
        patchServiceConsoleDetail(res.data.detail)
      }
      ElMessage.success(res.data?.message || `${label}指令已下发`)
      queueConsolePowerStatusSync()
    } catch (error) {
      serviceConsoleState.detail = previousDetail
      ElMessage.error(error?.response?.data?.message || `${label}失败`)
    } finally {
      serviceConsoleState.actionLoading = ''
    }
  }

  async function handleResetServicePassword() {
    if (!serviceConsoleState.serviceId) return

    let inputValue
    try {
      const res = await ElMessageBox.prompt('请输入新密码（至少 8 位，建议包含大小写字母与数字）', '重置登录密码', {
        confirmButtonText: '确认重置',
        cancelButtonText: '取消',
        inputType: 'password',
        inputPlaceholder: '至少 8 位',
        inputValidator: (val) => (val && String(val).length >= 8 ? true : '密码长度至少 8 位'),
      })
      inputValue = res.value
    } catch {
      return
    }

    serviceConsoleState.actionLoading = 'reset-password'
    try {
      await userApi.serviceResetPassword(userId.value, serviceConsoleState.serviceId, { password: inputValue })
      ElMessage.success('密码重置指令已下发')
      await reloadServiceConsole()
    } catch (error) {
      ElMessage.error(error?.response?.data?.message || '密码重置失败')
    } finally {
      serviceConsoleState.actionLoading = ''
    }
  }

  async function handleManualProvisionFromConsole() {
    if (!serviceConsoleState.serviceId) return

    let inputValue
    try {
      const res = await ElMessageBox.prompt('请输入上游实例 ID（手动开通将建立上游关联）', '手动开通 / 关联上游', {
        confirmButtonText: '确认关联',
        cancelButtonText: '取消',
        inputPlaceholder: '上游 host_id，例如 18293',
        inputPattern: /^\d+$/,
        inputErrorMessage: '请输入正整数',
      })
      inputValue = Number(res.value)
    } catch {
      return
    }

    serviceConsoleState.actionLoading = 'manual-provision'
    try {
      await userApi.manualProvisionService(userId.value, serviceConsoleState.serviceId, {
        upstream_host_id: inputValue,
      })
      ElMessage.success('手动开通指令已下发')
      await reloadServiceConsole()
      await loadServices()
    } catch (error) {
      ElMessage.error(error?.response?.data?.message || '手动开通失败')
    } finally {
      serviceConsoleState.actionLoading = ''
    }
  }

  async function handleServiceRefund(payload) {
    if (!serviceConsoleState.serviceId || !userId.value) return

    serviceConsoleState.actionLoading = 'refund'
    try {
      const res = await userApi.refundService(userId.value, serviceConsoleState.serviceId, payload)
      ElMessage.success(res.message || '服务已完成退款')
      await reloadServiceConsole()
      await loadServices()
      await reloadDetail()
    } catch (error) {
      ElMessage.error(error?.response?.data?.message || '退款失败')
    } finally {
      serviceConsoleState.actionLoading = ''
    }
  }

  function patchServiceListItem(detail) {
    const nextDetail = normalizeServiceConsoleDetail(detail)
    const currentIndex = servicesState.list.findIndex((item) => Number(item.id) === Number(nextDetail.id))
    if (currentIndex === -1) return

    servicesState.list.splice(currentIndex, 1, {
      ...servicesState.list[currentIndex],
      name: nextDetail.name,
      domain: nextDetail.domain,
      status: nextDetail.status,
      status_label: nextDetail.status_label,
      status_tone: nextDetail.status_tone,
      amount: nextDetail.amount,
      created_at: nextDetail.created_at,
      expires_at: nextDetail.expires_at,
      billing_cycle_label: nextDetail.billing_cycle_label,
      upstream: {
        ...(servicesState.list[currentIndex].upstream || {}),
        dedicated_ip: nextDetail.connection?.dedicated_ip || nextDetail.upstream?.dedicated_ip || '',
        host_id: nextDetail.upstream?.host_id || 0,
        status: nextDetail.upstream?.status || '',
        status_label: nextDetail.upstream?.status_label || '',
      },
      product: {
        ...(servicesState.list[currentIndex].product || {}),
        name: nextDetail.product_display_name || nextDetail.product?.display_name || '',
        type: nextDetail.product?.type || '',
        type_label: nextDetail.product?.type_label || '',
      },
      custom_service_name: nextDetail.custom_service_name || '',
    })
  }

  return {
    userId,
    userDetail,
    stats,
    referral,
    detailLoading,
    saveLoading,
    actionLoading,
    rechargeLoading,
    loginAsLoading,
    activeTab,
    editDialogVisible,
    rechargeVisible,
    addServiceDialogVisible,
    addServiceSubmitting,
    addServiceProductsLoading,
    addServiceProductDetailLoading,
    addServiceCategoriesLoading,
    addServiceOsOptions,
    addServiceOsLoading,
    editFormRef,
    editForm,
    rechargeFormRef,
    rechargeForm,
    addServiceFormRef,
    addServiceForm,
    addServiceProductOptions,
    addServiceCategoryTree,
    addServiceCategoryOptions,
    addServiceSelectedCategory,
    addServiceSubOptions,
    servicesState,
    serviceConsoleState,
    invoicesState,
    balanceState,
    ticketsState,
    logsState,
    noticesState,
    pageTitle,
    avatarText,
    statusText,
    statusTagType,
    registeredDaysLabel,
    statsCards,
    infoItems,
    addServiceCanLinkUpstream,
    addServiceBillingOptions,
    addServiceUpstreamChannel,
    servicePricingEntries,
    serviceStatusOptions,
    editRules,
    rechargeRules,
    addServiceRules,
    handleTabChange,
    searchServices,
    resetServicesFilters,
    loadServices,
    searchInvoices,
    resetInvoicesFilters,
    loadInvoices,
    loadBalance,
    loadTickets,
    searchLogs,
    loadLogs,
    reloadDetail,
    loadNotices,
    reloadNotices,
    handleSave,
    handleToggleStatus,
    openRechargeDialog,
    handleRecharge,
    handleLoginAs,
    openEditDialog,
    openAddServiceDialog,
    handleAddServiceProductChange,
    handleAddServiceCategoryChange,
    handleAddServiceSubChange,
    handleAddServiceSourceChange,
    handleSubmitAddService,
    syncAddServiceAmountFromCycle,
    goBack,
    handleRefreshService,
    handleRefreshServicesStatus,
    handleDeleteServiceRow,
    openServiceConsole,
    closeServiceConsole,
    reloadServiceConsole,
    serviceUpstreamDialogVisible,
    serviceUpstreamSubmitting,
    serviceUpstreamSuppliersLoading,
    serviceUpstreamFormRef,
    serviceUpstreamForm,
    serviceUpstreamSupplierOptions,
    serviceUpstreamRules,
    openServiceUpstreamDialog,
    closeServiceUpstreamDialog,
    submitServiceUpstream,
    servicePricingDialogVisible,
    servicePricingSubmitting,
    servicePricingFormRef,
    serviceNameDialogVisible,
    serviceNameSubmitting,
    servicePricingForm,
    serviceNameForm,
    servicePricingRules,
    openServicePricingDialog,
    closeServicePricingDialog,
    submitServicePricing,
    openServiceNameDialog,
    closeServiceNameDialog,
    submitServiceName,
    handleRefreshConsoleRemoteStatus,
    handleServicePower,
    handleResetServicePassword,
    handleManualProvisionFromConsole,
    handleServiceRefund,
    formatMoney,
    formatInteger,
    formatDateTime,
    toNumber,
    resolveInvoiceStatus,
    resolveInvoiceType,
    resolveBalanceType,
    resolvePriority,
    resolveTicketStatus,
    resolveServiceToneTagType,
    noticeStatusLabel,
    noticeStatusTagType,
  }
}
