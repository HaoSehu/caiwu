import { ref, computed } from 'vue'
import siteApi from '@/api/site'

const CACHE_TTL = 5 * 60 * 1000
const caches = {
  notice: { data: null, timestamp: 0 },
  help: { data: null, timestamp: 0 },
}
const pending = {
  notice: null,
  help: null,
}

function normalizeItem(item) {
  return {
    id: Number(item.id || 0),
    title: item.title || '',
    summary: item.summary || '',
    category: item.category || item.category_name || '',
    publish_at: item.publish_at || item.published_at || '',
  }
}

export function useNavContentMenu(contentType) {
  const items = ref([])
  const loading = ref(false)
  const initialized = ref(false)

  const fetcher = contentType === 'help' ? siteApi.helpArticles : siteApi.notices

  async function load() {
    const cache = caches[contentType]
    if (cache.data && Date.now() - cache.timestamp < CACHE_TTL) {
      items.value = cache.data
      return
    }

    if (pending[contentType]) {
      await pending[contentType]
      items.value = caches[contentType].data || []
      return
    }

    loading.value = true
    pending[contentType] = fetcher({ page: 1, page_size: 6 })
      .then((res) => {
        const list = (res.data?.list || res.data?.items || []).map(normalizeItem)
        caches[contentType] = { data: list, timestamp: Date.now() }
        items.value = list
      })
      .catch((error) => {
        // 与 useNavProductMenu 同构：原先完全静默，导航面板会出现"入口在、列表空"却无任何线索。
        // 只告警不抛出；失败不写缓存（cache.data 保持 null），下次 init/load 会重新请求。
        console.warn('[useNavContentMenu] 加载内容列表失败', contentType, error)
        items.value = []
      })
      .finally(() => {
        loading.value = false
        pending[contentType] = null
      })

    await pending[contentType]
  }

  async function init() {
    if (initialized.value) return
    // 与 useNavProductMenu 同构修复：initialized 原先在 await 前置位，
    // load() 首次失败后再次调用会被这里短路，导航内容永不重试。
    await load()
    // 注意与产品导航的差异：本 hook 的 load() 在 promise 链里自带 .catch，
    // 失败时只是把 items 清空、缓存 data 仍为 null，load() 本身不会抛错。
    // 所以不能把"await 走完"当成成功——必须确认缓存真的写入了才置位，
    // 否则首次失败同样会被永久短路。空数组属于合法成功结果（[] 是真值）。
    if (caches[contentType].data) {
      initialized.value = true
    }
  }

  const activeCategory = ref(null)

  const categories = computed(() => {
    const map = {}
    items.value.forEach((item) => {
      const cat = item.category || '未分类'
      if (!map[cat]) map[cat] = 0
      map[cat]++
    })
    return Object.entries(map).map(([label, count]) => ({ label, count }))
  })

  const filteredItems = computed(() => {
    if (!activeCategory.value) return items.value
    return items.value.filter((item) => (item.category || '未分类') === activeCategory.value)
  })

  function activateCategory(cat) {
    activeCategory.value = activeCategory.value === cat ? null : cat
  }

  return { items, loading, init, categories, activeCategory, filteredItems, activateCategory }
}
