import { ref, reactive } from 'vue'
import { ElMessage } from 'element-plus'
import clientApi from '@/api/client'

/**
 * 工单模块 composable
 * 封装工单相关的 API 调用和状态管理
 */
export function useTickets() {
  const loading = ref(false)
  const creating = ref(false)
  const replying = ref(false)
  const closing = ref(false)
  const detailLoading = ref(false)

  const list = ref([])
  const total = ref(0)
  const page = ref(1)
  const pageSize = ref(10)
  const keyword = ref('')
  const status = ref('')

  const detail = ref(null)
  const serviceOptions = ref([])

  // 状态映射函数
  function resolveTicketStatusLabel(value) {
    return ({ 0: '开启', 1: '客户回复', 2: '员工回复', 3: '已关闭' })[Number(value)] || '--'
  }

  function resolveTicketTagType(value) {
    if (Number(value) === 3) return 'info'
    if (Number(value) === 2) return 'success'
    if (Number(value) === 1) return 'warning'
    return 'primary'
  }

  function resolvePriorityLabel(value) {
    return ({ 1: '低', 2: '中', 3: '高', 4: '紧急' })[Number(value)] || '--'
  }

  function resolveDepartmentLabel(value) {
    return ({ sales: '销售', support: '技术支持', billing: '财务', abuse: '投诉' })[String(value)] || '--'
  }

  // 加载工单列表
  async function loadTickets() {
    loading.value = true
    try {
      const response = await clientApi.tickets({
        page: page.value,
        page_size: pageSize.value,
        keyword: keyword.value || undefined,
        status: status.value === '' ? undefined : status.value,
      })
      list.value = Array.isArray(response.data?.list) ? response.data.list : []
      total.value = Number(response.data?.total || 0)
    } catch (error) {
      if (!error?.__handled) ElMessage.error(error?.message || '工单列表加载失败')
    } finally {
      loading.value = false
    }
  }

  // 加载服务选项
  async function loadServiceOptions() {
    const response = await clientApi.ticketServiceOptions({ limit: 50 })
    serviceOptions.value = Array.isArray(response.data) ? response.data : []
  }

  // 搜索处理
  function handleSearch() {
    page.value = 1
    void loadTickets()
  }

  // 分页大小变更
  function handlePageSizeChange() {
    page.value = 1
    void loadTickets()
  }

  // 重置筛选
  function resetFilters() {
    keyword.value = ''
    status.value = ''
    page.value = 1
    void loadTickets()
  }

  // 提交工单
  async function submitTicket(createForm) {
    if (!createForm.subject?.trim()) {
      ElMessage.warning('请输入工单标题')
      return false
    }

    creating.value = true
    try {
      await clientApi.createTicket({
        department: createForm.department,
        subject: createForm.subject,
        content: createForm.content,
        priority: createForm.priority,
        service_id: createForm.service_id,
        attachments: createForm.attachments || [],
      })
      ElMessage.success('工单提交成功')
      await loadTickets()
      return true
    } catch (error) {
      if (!error?.__handled) ElMessage.error(error?.message || '工单提交失败')
      return false
    } finally {
      creating.value = false
    }
  }

  // 加载工单详情
  async function loadTicketDetail(id) {
    detailLoading.value = true
    try {
      const response = await clientApi.ticketDetail(id)
      detail.value = response.data || null
      return detail.value
    } catch (error) {
      if (!error?.__handled) ElMessage.error(error?.message || '工单详情加载失败')
      return null
    } finally {
      detailLoading.value = false
    }
  }

  // 提交回复
  async function submitReply(replyPayload) {
    const id = Number(detail.value?.id || 0)
    if (!id) return false

    const content = typeof replyPayload === 'string'
      ? replyPayload
      : (replyPayload?.content || '')
    const attachments = Array.isArray(replyPayload?.attachments) ? replyPayload.attachments : []

    if (!content.trim() && attachments.length === 0) {
      ElMessage.warning('请输入回复内容或上传图片')
      return false
    }

    replying.value = true
    try {
      await clientApi.replyTicket(id, { content, attachments })
      ElMessage.success('回复已发送')
      await loadTicketDetail(id)
      await loadTickets()
      return true
    } catch (error) {
      if (!error?.__handled) ElMessage.error(error?.message || '发送回复失败')
      return false
    } finally {
      replying.value = false
    }
  }

  // 关闭工单
  async function closeTicket() {
    const id = Number(detail.value?.id || 0)
    if (!id) return false

    closing.value = true
    try {
      await clientApi.closeTicket(id)
      ElMessage.success('工单已关闭')
      await loadTicketDetail(id)
      await loadTickets()
      return true
    } catch (error) {
      if (!error?.__handled) ElMessage.error(error?.message || '关闭工单失败')
      return false
    } finally {
      closing.value = false
    }
  }

  return {
    // 状态
    loading,
    creating,
    replying,
    closing,
    detailLoading,
    list,
    total,
    page,
    pageSize,
    keyword,
    status,
    detail,
    serviceOptions,

    // 状态映射
    resolveTicketStatusLabel,
    resolveTicketTagType,
    resolvePriorityLabel,
    resolveDepartmentLabel,

    // 方法
    loadTickets,
    loadServiceOptions,
    handleSearch,
    handlePageSizeChange,
    resetFilters,
    submitTicket,
    loadTicketDetail,
    submitReply,
    closeTicket,
  }
}
