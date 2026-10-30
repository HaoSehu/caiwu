import { computed, ref, shallowRef } from 'vue'
import siteApi from '@/api/site'

const CACHE_TTL = 5 * 60 * 1000
let cachedProductTypes = null
let cachedTimestamp = 0
let pendingRequest = null

export function useNavProductMenu() {
  const productTypes = ref([])
  const activeTypeValue = ref('')
  const groupsByType = shallowRef({})
  const loading = ref(false)
  const initialized = ref(false)

  const activeType = computed(() =>
    productTypes.value.find((t) => t.value === activeTypeValue.value) || null,
  )

  const activeGroups = computed(() => {
    const key = activeTypeValue.value
    return groupsByType.value[key] || []
  })

  function getGroupsForType(typeValue) {
    return groupsByType.value[typeValue] || []
  }

  async function loadProductTypes() {
    if (cachedProductTypes && Date.now() - cachedTimestamp < CACHE_TTL) {
      productTypes.value = cachedProductTypes
      if (cachedProductTypes.length && !activeTypeValue.value) {
        activeTypeValue.value = cachedProductTypes[0].value
      }
      return
    }

    if (pendingRequest) {
      await pendingRequest
      return
    }

    loading.value = true
    pendingRequest = siteApi.productTypes()
      .then((res) => {
        const list = res.data.list || []
        const normalized = list.map((item, index) => ({
          id: Number(item.id || index + 1),
          value: String(item.first_product_group_code || item.value || ''),
          label: item.label || item.product_type_label || `分类 ${index + 1}`,
          product_type: String(item.product_type || ''),
          product_count: Number(item.product_count || 0),
          group_count: Number(item.group_count || 0),
        })).filter((item) => item.value !== '')

        cachedProductTypes = normalized
        cachedTimestamp = Date.now()
        productTypes.value = normalized

        if (normalized.length && !activeTypeValue.value) {
          activeTypeValue.value = normalized[0].value
        }
      })
      .finally(() => {
        loading.value = false
        pendingRequest = null
      })

    await pendingRequest
  }

  async function loadGroupsForType(typeValue) {
    if (!typeValue || groupsByType.value[typeValue]) {
      return
    }

    try {
      const res = await siteApi.productGroups({ first_product_group_code: typeValue })
      const groups = (res.data.list || []).map((g) => ({
        id: Number(g.id || 0),
        name: g.name || '',
        slogan: g.slogan || '',
        product_count: Number(g.product_count || 0),
        product_type: g.product_type || '',
        first_product_group_code: g.first_product_group_code || '',
        children_count: Number(g.children_count || 0),
      }))

      groupsByType.value = { ...groupsByType.value, [typeValue]: groups }
    } catch (error) {
      // 分组拉取失败不能静默：导航下拉会呈现"分类在但分组空"，是典型难以复现的线上反馈。
      // 这里只告警不抛出，避免打断导航渲染；不写缓存，下次 activateType 仍会重试。
      console.warn('[useNavProductMenu] 加载产品分组失败', typeValue, error)
    }
  }

  async function activateType(typeValue) {
    if (activeTypeValue.value === typeValue) {
      return
    }
    activeTypeValue.value = typeValue
    await loadGroupsForType(typeValue)
  }

  async function init() {
    if (initialized.value) {
      return
    }
    // initialized 必须在首次加载成功后才置位：原先在 await 之前置 true，
    // 一旦 loadProductTypes 首次失败就直接 await pendingRequest 抛出，
    // 而 initialized 已是 true，后续任何调用都会被这里短路返回，永不重试。
    // 失败时这里只告警不向外抛：调用方 useMegaMenu/useMobileNav 都是 initProducts() 直接丢弃返回值的
    // （不 await），抛出去会变成 unhandled rejection，而导航本身不该因数据失败而中断。
    try {
      await loadProductTypes()
      if (activeTypeValue.value) {
        await loadGroupsForType(activeTypeValue.value)
      }
      initialized.value = true
    } catch (error) {
      console.warn('[useNavProductMenu] 初始化产品导航失败，保持未初始化以便重试', error)
    }
  }

  return {
    productTypes,
    activeTypeValue,
    activeType,
    activeGroups,
    getGroupsForType,
    loading,
    activateType,
    loadGroupsForType,
    init,
  }
}
