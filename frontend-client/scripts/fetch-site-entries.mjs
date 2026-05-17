/**
 * 供 prerender.mjs 和 generate-sitemap.mjs 共享的站点动态条目抓取逻辑。
 *
 * 输入：后端 API Base（如 https://www.example.com）、抓取上限
 * 输出：products / notices / help 三类条目数组，每项含 id 与 updated_at
 *
 * 没有后端或抓取失败时返回空数组，调用方需自行兜底。
 */

function normalizeApiBase(base) {
  return String(base || '').trim().replace(/\/$/, '')
}

async function fetchJson(url) {
  if (typeof fetch !== 'function') return null
  try {
    const response = await fetch(url, { headers: { Accept: 'application/json' } })
    if (!response.ok) return null
    return await response.json()
  } catch (error) {
    console.warn(`[site-entries] fetch failed: ${url}: ${error.message}`)
    return null
  }
}

function extractList(payload, keys = ['list', 'items', 'data']) {
  for (const key of keys) {
    const value = payload?.data?.[key] ?? payload?.[key]
    if (Array.isArray(value)) return value
  }
  if (Array.isArray(payload?.data)) return payload.data
  if (Array.isArray(payload)) return payload
  return []
}

function normalizeDate(value) {
  if (!value) return undefined
  const date = new Date(value)
  return Number.isNaN(date.getTime()) ? undefined : date.toISOString().slice(0, 10)
}

async function fetchProducts(apiBase, limit) {
  const base = normalizeApiBase(apiBase)
  if (!base) return []
  const payload = await fetchJson(`${base}/api/site/products?page=1&page_size=${limit}`)
  return extractList(payload).map((item) => ({
    id: Number(item.id),
    updatedAt: normalizeDate(item.updated_at || item.created_at),
  })).filter((item) => Number.isFinite(item.id) && item.id > 0)
}

async function fetchNotices(apiBase, limit) {
  const base = normalizeApiBase(apiBase)
  if (!base) return []
  const payload = await fetchJson(`${base}/api/site/notices?page=1&page_size=${limit}`)
  return extractList(payload).map((item) => ({
    id: Number(item.id),
    updatedAt: normalizeDate(item.last_published_at || item.publish_at || item.updated_at),
  })).filter((item) => Number.isFinite(item.id) && item.id > 0)
}

async function fetchHelpArticles(apiBase, limit) {
  const base = normalizeApiBase(apiBase)
  if (!base) return []
  const payload = await fetchJson(`${base}/api/site/help-articles?page=1&page_size=${limit}`)
  return extractList(payload).map((item) => ({
    id: Number(item.id),
    updatedAt: normalizeDate(item.last_published_at || item.publish_at || item.updated_at),
  })).filter((item) => Number.isFinite(item.id) && item.id > 0)
}

/**
 * 批量抓取三类条目。
 *
 * @param {object} options
 * @param {string} options.apiBase 后端 API base，例如 https://www.example.com
 * @param {number} [options.limit=200] 单类最多抓取数量
 * @returns {Promise<{ products: Array, notices: Array, helpArticles: Array }>}
 */
export async function fetchAllEntries({ apiBase, limit = 200 } = {}) {
  const [products, notices, helpArticles] = await Promise.all([
    fetchProducts(apiBase, limit),
    fetchNotices(apiBase, limit),
    fetchHelpArticles(apiBase, limit),
  ])
  return { products, notices, helpArticles }
}
