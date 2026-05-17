import { ref, reactive } from 'vue'
import { ElMessage } from 'element-plus'
import clientApi from '@/api/client'

/**
 * 账单模块 composable
 * 封装账单相关的 API 调用和状态管理
 */
export function useInvoices() {
  const loading = ref(false)
  const list = ref([])
  const total = ref(0)
  const page = ref(1)
  const pageSize = ref(10)
  const summary = reactive({
    total: 0,
    unpaid: 0,
    paid: 0,
    overdue: 0,
    unpaid_amount: '0.00',
  })
  const filters = reactive({
    keyword: '',
    status: '',
    type: '',
  })

  function resolveInvoiceTagType(status) {
    if (status === 1) return 'success'
    if (status === 0) return 'warning'
    if (status === 5) return 'info'
    return 'danger'
  }

  async function loadSummary() {
    const response = await clientApi.invoicesSummary()
    Object.assign(summary, response.data || {})
  }

  async function loadList() {
    loading.value = true
    try {
      const response = await clientApi.invoices({
        page: page.value,
        page_size: pageSize.value,
        status: filters.status === '' ? undefined : filters.status,
        type: filters.type || undefined,
        keyword: filters.keyword || undefined,
      })
      list.value = Array.isArray(response.data?.list) ? response.data.list : []
      total.value = Number(response.data?.total || 0)
    } catch (error) {
      if (!error?.__handled) ElMessage.error(error?.message || '账单列表加载失败')
    } finally {
      loading.value = false
    }
  }

  async function loadData() {
    await Promise.all([loadSummary(), loadList()])
  }

  function handleSearch() {
    page.value = 1
    void loadList()
  }

  function handlePageSizeChange() {
    page.value = 1
    void loadList()
  }

  function resetFilters() {
    filters.keyword = ''
    filters.status = ''
    filters.type = ''
    page.value = 1
    void loadData()
  }

  return {
    loading,
    list,
    total,
    page,
    pageSize,
    summary,
    filters,
    resolveInvoiceTagType,
    loadSummary,
    loadList,
    loadData,
    handleSearch,
    handlePageSizeChange,
    resetFilters,
  }
}
