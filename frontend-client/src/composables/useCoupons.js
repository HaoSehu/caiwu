import { reactive, ref } from 'vue'
import { ElMessage } from 'element-plus'
import clientApi from '@/api/client'

const COUPON_VIEW_MODE_STORAGE_KEY = 'client_coupon_view_mode'

function createTabState() {
  return reactive({
    loading: false,
    list: [],
    total: 0,
    page: 1,
    pageSize: 10,
    keyword: '',
    status: '',
  })
}

/**
 * 优惠券中心 composable
 * 管理“我拥有的优惠券”和“优惠券广场”两个 Tab 的独立状态
 */
export function useCoupons() {
  const activeTab = ref('owned')
  const claimingId = ref(0)
  const viewMode = ref('grid')
  const viewModeOptions = [
    { label: '卡片', value: 'grid' },
    { label: '列表', value: 'list' },
  ]

  const ownedState = createTabState()
  const plazaState = createTabState()

  function getTabState(tab = activeTab.value) {
    return tab === 'plaza' ? plazaState : ownedState
  }

  async function loadList(tab = activeTab.value) {
    const state = getTabState(tab)
    const requestMethod = tab === 'plaza' ? clientApi.publicCoupons : clientApi.coupons

    state.loading = true
    try {
      const response = await requestMethod({
        page: state.page,
        page_size: state.pageSize,
        keyword: state.keyword || undefined,
        status: state.status || undefined,
      })
      state.list = Array.isArray(response.data?.list) ? response.data.list : []
      state.total = Number(response.data?.total || 0)
    } catch (error) {
      if (!error?.__handled) {
        ElMessage.error(error?.message || (tab === 'plaza' ? '优惠券广场加载失败' : '优惠券列表加载失败'))
      }
    } finally {
      state.loading = false
    }
  }

  async function loadData(tab = activeTab.value) {
    await loadList(tab)
  }

  function handleSearch(tab = activeTab.value) {
    const state = getTabState(tab)
    state.page = 1
    void loadData(tab)
  }

  function handlePageChange(tab = activeTab.value) {
    void loadList(tab)
  }

  function handlePageSizeChange(tab = activeTab.value) {
    const state = getTabState(tab)
    state.page = 1
    void loadList(tab)
  }

  function resetFilters(tab = activeTab.value) {
    const state = getTabState(tab)
    state.keyword = ''
    state.status = ''
    state.page = 1
    void loadData(tab)
  }

  async function switchTab(tab) {
    activeTab.value = tab
    const state = getTabState(tab)

    if (!state.list.length && !state.loading) {
      await loadData(tab)
    }
  }

  function normalizeViewMode(value) {
    return value === 'list' ? 'list' : 'grid'
  }

  function setViewMode(value) {
    const nextMode = normalizeViewMode(value)
    if (viewMode.value === nextMode) return
    viewMode.value = nextMode
    if (typeof window !== 'undefined') {
      window.localStorage.setItem(COUPON_VIEW_MODE_STORAGE_KEY, nextMode)
    }
  }

  function restoreViewMode() {
    if (typeof window === 'undefined') return
    viewMode.value = normalizeViewMode(window.localStorage.getItem(COUPON_VIEW_MODE_STORAGE_KEY))
  }

  async function claimCoupon(couponId) {
    const id = Number(couponId || 0)
    if (id <= 0 || claimingId.value) return

    claimingId.value = id
    try {
      await clientApi.claimCoupon(id)
      ElMessage.success('领取成功')
      await Promise.all([loadData('owned'), loadData('plaza')])
    } catch (error) {
      if (!error?.__handled) ElMessage.error(error?.message || '优惠券领取失败')
    } finally {
      claimingId.value = 0
    }
  }

  return {
    activeTab,
    claimingId,
    viewMode,
    viewModeOptions,
    ownedState,
    plazaState,
    loadList,
    loadData,
    handleSearch,
    handlePageChange,
    handlePageSizeChange,
    resetFilters,
    switchTab,
    setViewMode,
    restoreViewMode,
    claimCoupon,
  }
}
