import { computed, onMounted, reactive, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import adminApi from '@/api/admin'
import { useUserStore } from '@/stores/user'

export function useVerification() {
  const userStore = useUserStore()
  const activeTab = ref('list')
  const apiFormRef = ref(null)
  const feeFormRef = ref(null)
  const loading = ref(false)
  const listLoading = ref(false)
  const feeLoading = ref(false)
  const actionLoadingId = ref(0)
  const list = ref([])
  const total = ref(0)
  const page = ref(1)
  const pageSize = ref(20)
  const quickStatus = ref('all')
  const filters = reactive({
    keyword: '',
    verification_status: '',
    is_verified: '',
  })
  const form = reactive({
    verification_api: '',
    verification_key: '',
    verification_biz_code: 'FACE',
  })
  const feeForm = reactive({
    free_attempts: 3,
    retry_fee: 2.00,
  })

  const validateCredentialPair = (currentKey, peerKey) => (_rule, value, callback) => {
    const current = String(value ?? '').trim()
    const peer = String(form[peerKey] ?? '').trim()

    if ((current && !peer) || (!current && peer)) {
      callback(new Error('API ID 和 API KEY 需要同时填写或同时留空'))
      return
    }

    callback()
  }

  const apiRules = computed(() => ({
    verification_api: [{ validator: validateCredentialPair('verification_api', 'verification_key'), trigger: 'blur' }],
    verification_key: [{ validator: validateCredentialPair('verification_key', 'verification_api'), trigger: 'blur' }],
    verification_biz_code: [{ required: true, message: '请选择认证方式', trigger: 'change' }],
  }))

  const feeRules = {
    free_attempts: [
      {
        validator: (_rule, value, callback) => {
          const parsed = Number(value)
          if (!Number.isInteger(parsed) || parsed < 0 || parsed > 10) {
            callback(new Error('免费认证次数必须是 0-10 的整数'))
            return
          }

          callback()
        },
        trigger: 'change',
      },
    ],
    retry_fee: [
      {
        validator: (_rule, value, callback) => {
          const parsed = Number(value)
          if (Number.isNaN(parsed) || parsed < 0) {
            callback(new Error('再次认证费用不能小于 0'))
            return
          }

          callback()
        },
        trigger: 'change',
      },
    ],
  }

  const createEmptyDetail = () => ({
    id: null,
    display_name: '',
    email: '',
    phone: '',
    real_name: '',
    id_card: '',
    id_card_masked: '',
    verification_status: 0,
    verification_message: '',
    verification_certify_id: '',
    verification_method_label: '-',
    verification_type_label: '个人认证',
    document_type_label: '-',
    identity_region_label: '-',
    created_at: '',
    updated_at: '',
    verified_at: '',
  })

  const detailDialog = reactive({
    visible: false,
    loading: false,
    data: createEmptyDetail(),
  })
  const historyDialog = reactive({
    visible: false,
    loading: false,
    userId: null,
    userName: '',
    list: [],
  })
  const summary = reactive({
    stats: {
      total: 0,
      verified: 0,
      pending: 0,
      failed: 0,
      unbound: 0,
    },
    config: {
      configured: false,
    },
  })

  const bizCodeOptions = {
    FACE: '人脸识别',
    CERT_PHOTO: '证照认证',
    CERT_PHOTO_FACE: '证照+人脸',
    SMART_FACE: '快捷认证',
  }

  const detailDialogTitle = computed(() => detailDialog.data.verification_type_label || '实名认证详情')
  const historyDialogTitle = computed(() => {
    return historyDialog.userName ? `历史记录(${historyDialog.userName})` : '历史记录'
  })
  const detailMetaItems = computed(() => {
    const detail = detailDialog.data
    return [
      { label: '邮箱', value: formatDetailValue(detail.email) },
      { label: '手机', value: formatDetailValue(detail.phone) },
      { label: '创建时间', value: formatDetailValue(detail.created_at) },
      { label: '更新时间', value: formatDetailValue(detail.updated_at) },
    ]
  })
  const detailFields = computed(() => {
    const detail = detailDialog.data
    return [
      { label: '用户名称', value: formatDetailValue(detail.display_name) },
      { label: '真实姓名', value: formatDetailValue(detail.real_name) },
      { label: '认证方式', value: formatDetailValue(detail.verification_method_label) },
      { label: '认证类型', value: formatDetailValue(detail.verification_type_label) },
      { label: '证件类型', value: formatDetailValue(detail.document_type_label) },
      { label: '身份地区', value: formatDetailValue(detail.identity_region_label) },
      { label: '认证完成时间', value: formatDetailValue(detail.verified_at) },
      { label: '证件号码', value: formatDetailValue(detail.id_card || detail.id_card_masked), wide: true },
      { label: '接口单号', value: formatDetailValue(detail.verification_certify_id), wide: true },
      { label: '状态说明', value: formatDetailValue(detail.verification_message), wide: true, multiline: true },
    ]
  })

  const maskedKey = computed(() => {
    if (!form.verification_key) return '-'
    if (form.verification_key.length <= 8) return '已配置'
    return `${form.verification_key.slice(0, 4)}******${form.verification_key.slice(-4)}`
  })

  const formatDetailValue = (value) => {
    if (value === undefined || value === null || value === '') return '-'
    return value
  }

  const getBizCodeLabel = (bizCode) => {
    return bizCodeOptions[bizCode] || '人脸识别'
  }

  const canRejectVerification = computed(() => {
    return userStore.permissions.includes('*') || userStore.permissions.includes('verification.unbind')
  })

  const hasVerificationRecord = (row) => {
    return Number(row?.verification_status || 0) > 0
      || Boolean(row?.real_name)
      || Boolean(row?.id_card_masked && row.id_card_masked !== '-')
  }

  const verificationMethodLabel = (row) => {
    if (!hasVerificationRecord(row)) return '-'
    return getBizCodeLabel(summary.config.verification_biz_code || form.verification_biz_code)
  }

  const canReject = (row) => {
    return canRejectVerification.value && Number(row?.verification_status || 0) === 2
  }

  const statusTagType = (status) => {
    if (status === 2) return 'success'
    if (status === 3 || status === 5) return 'danger'
    if (status === 1 || status === 4) return 'warning'
    return 'info'
  }

  const isPendingStatus = (status) => {
    return Number(status || 0) === 1 || Number(status || 0) === 4
  }

  const normalizeVerificationMessage = (message) => {
    const normalized = String(message ?? '').trim()
    if (normalized === '' || normalized.toLowerCase() === 'null') return ''
    if (normalized === '0' || normalized === '1') return ''
    if (normalized === '等待认证' || normalized === '待认证') return ''
    return normalized
  }

  const buildFallbackDetail = (row) => {
    const hasIdCard = row?.id_card_masked && row.id_card_masked !== '-'
    return {
      ...createEmptyDetail(),
      id: row?.id ?? null,
      display_name: row?.display_name || '',
      email: row?.email || '',
      phone: row?.phone || '',
      real_name: row?.real_name || '',
      id_card_masked: row?.id_card_masked || '',
      verification_status: row?.verification_status ?? 0,
      verification_message: row?.verification_message || '',
      verification_method_label: verificationMethodLabel(row),
      document_type_label: hasIdCard ? '居民身份证' : '-',
      identity_region_label: hasIdCard ? '大陆' : '-',
      created_at: row?.created_at || '',
    }
  }

  const buildFallbackHistory = (row) => {
    if (!hasVerificationRecord(row)) return []
    return [{
      id: `fallback-${row?.id ?? 'unknown'}`,
      real_name: row?.real_name || row?.display_name || '',
      id_card_masked: row?.id_card_masked || '',
      verification_method_label: verificationMethodLabel(row),
      verification_type_label: '个人认证',
      verification_status: row?.verification_status ?? 0,
      verification_message: row?.verification_message || '',
      submitted_at: row?.created_at || '',
    }]
  }

  const verificationStatusLabel = (row) => {
    const status = Number(row?.verification_status || 0)
    const message = normalizeVerificationMessage(row?.verification_message)
    if (status === 2) return '认证成功'
    if (status === 3) return message ? `认证失败：${message}` : '认证失败'
    if (status === 5) return message ? `已驳回：${message}` : '已驳回'
    if (isPendingStatus(status)) return '待认证'
    return message || '未提交认证'
  }

  const openDetail = async (row) => {
    detailDialog.visible = true
    detailDialog.loading = true
    detailDialog.data = buildFallbackDetail(row)
    try {
      const res = await adminApi.verifications.detail(row.id)
      detailDialog.data = { ...createEmptyDetail(), ...res.data }
    } catch (error) {
      ElMessage.error(error.response?.data?.message || '加载实名详情失败')
    } finally {
      detailDialog.loading = false
    }
  }

  const openHistory = async (row) => {
    historyDialog.visible = true
    historyDialog.loading = true
    historyDialog.userId = row?.id ?? null
    historyDialog.userName = row?.display_name || row?.real_name || ''
    historyDialog.list = buildFallbackHistory(row)
    try {
      const res = await adminApi.verifications.history(row.id)
      historyDialog.userName = res.data.user_name || historyDialog.userName
      historyDialog.list = Array.isArray(res.data.list) && res.data.list.length
        ? res.data.list
        : buildFallbackHistory(row)
    } catch (error) {
      ElMessage.error(error.response?.data?.message || '加载历史记录失败')
    } finally {
      historyDialog.loading = false
    }
  }

  const handleReject = async (row) => {
    if (!canReject(row) || actionLoadingId.value === row.id) {
      return
    }

    try {
      const { value: rejectReason } = await ElMessageBox.prompt(
        `确认驳回用户“${row.display_name || row.real_name || row.id}”的实名认证吗？`,
        '驳回实名',
        {
          type: 'warning',
          confirmButtonText: '确认驳回',
          cancelButtonText: '取消',
          inputType: 'textarea',
          inputPlaceholder: '请输入驳回原因',
          inputValidator: (value) => String(value || '').trim() !== '' || '请输入驳回原因',
        },
      )
      row.__reject_reason__ = String(rejectReason || '').trim()
    } catch {
      return
    }

    actionLoadingId.value = row.id

    try {
      const res = await adminApi.verifications.unbind(row.id, {
        reject_reason: row.__reject_reason__ || '',
      })
      ElMessage.success(res.message || '操作成功')
      const refreshResults = await Promise.allSettled([loadList(), loadSummary()])

      if (refreshResults.some((item) => item.status === 'rejected')) {
        ElMessage.warning('驳回成功，但列表刷新失败，请手动刷新后确认最新状态')
      }

      const latestRow = list.value.find((item) => item.id === row.id) || row

      if (detailDialog.visible && Number(detailDialog.data.id) === row.id) {
        await openDetail(latestRow)
      }

      if (historyDialog.visible && Number(historyDialog.userId) === row.id) {
        await openHistory(latestRow)
      }
    } catch (error) {
      ElMessage.error(error.response?.data?.message || error.message || '驳回失败')
    } finally {
      actionLoadingId.value = 0
    }
  }

  const buildListParams = () => {
    const params = { page: page.value, page_size: pageSize.value }
    if (filters.keyword) params.keyword = filters.keyword
    if (quickStatus.value === 'success') {
      params.verification_status = 2
      params.is_verified = 1
    } else if (quickStatus.value === 'pending') {
      params.verification_status = 1
    } else if (quickStatus.value === 'failed') {
      params.verification_status = 3
    }
    return params
  }

  const loadList = async () => {
    listLoading.value = true
    try {
      const res = await adminApi.verifications.list(buildListParams())
      list.value = res.data.list
      total.value = res.data.total
    } finally {
      listLoading.value = false
    }
  }

  const handleQuickStatusChange = (status) => {
    quickStatus.value = status || 'all'
    page.value = 1
    return loadList()
  }

  const loadSettings = async () => {
    try {
      const res = await adminApi.verifications.settings()
      res.data.forEach(item => {
        form[item.key] = item.value
      })
      apiFormRef.value?.clearValidate?.()
    } catch {
      ElMessage.error('加载配置失败')
    }
  }

  const loadSummary = async () => {
    try {
      const res = await adminApi.verifications.summary()
      Object.assign(summary.stats, res.data.stats || {})
      Object.assign(summary.config, res.data.config || {})
    } catch {
      ElMessage.error('加载实名概览失败')
    }
  }

  const resetFilters = () => {
    quickStatus.value = 'all'
    filters.keyword = ''
    page.value = 1
    loadList()
  }

  const handleSave = async () => {
    const valid = await apiFormRef.value?.validate?.().catch(() => false)
    if (!valid) {
      return
    }

    loading.value = true
    try {
      await adminApi.verifications.saveSettings({ ...form })
      ElMessage.success('保存成功')
      await loadSummary()
    } catch {
      ElMessage.error('保存失败')
    } finally {
      loading.value = false
    }
  }

  const loadFeeSettings = async () => {
    try {
      const { data } = await adminApi.settings.list({ group: 'verification' })
      data.forEach(item => {
        if (item.key === 'free_attempts') {
          feeForm.free_attempts = parseInt(item.value)
        } else if (item.key === 'retry_fee') {
          feeForm.retry_fee = parseFloat(item.value)
        }
      })
      feeFormRef.value?.clearValidate?.()
    } catch (error) {
      console.error('加载费用设置失败:', error)
    }
  }

  const saveFeeSettings = async () => {
    const valid = await feeFormRef.value?.validate?.().catch(() => false)
    if (!valid) {
      return
    }

    feeLoading.value = true
    try {
      await adminApi.settings.save({
        group: 'verification',
        settings: {
          free_attempts: feeForm.free_attempts,
          retry_fee: feeForm.retry_fee,
        },
      })
      ElMessage.success('费用设置已保存')
    } catch (error) {
      ElMessage.error(error.response?.data?.message || '保存失败')
    } finally {
      feeLoading.value = false
    }
  }

  onMounted(() => {
    loadList()
    loadSummary()
    loadSettings()
    loadFeeSettings()
  })

  return {
    activeTab,
    apiFormRef,
    feeFormRef,
    loading,
    listLoading,
    feeLoading,
    actionLoadingId,
    list,
    total,
    page,
    pageSize,
    quickStatus,
    filters,
    form,
    feeForm,
    apiRules,
    feeRules,
    detailDialog,
    historyDialog,
    summary,
    detailDialogTitle,
    historyDialogTitle,
    detailMetaItems,
    detailFields,
    maskedKey,
    formatDetailValue,
    verificationMethodLabel,
    canReject,
    statusTagType,
    verificationStatusLabel,
    openDetail,
    openHistory,
    handleReject,
    loadList,
    handleQuickStatusChange,
    loadSettings,
    loadSummary,
    resetFilters,
    handleSave,
    loadFeeSettings,
    saveFeeSettings,
  }
}
