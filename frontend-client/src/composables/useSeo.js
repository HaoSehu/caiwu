import { onBeforeUnmount, onMounted, toValue, watch } from 'vue'
import { useAppStore } from '@/stores/app'

/**
 * 统一管理页面级 SEO 元数据：title、description、keywords、canonical、robots、JSON-LD。
 *
 * 用法（script setup）：
 *   useSeo(() => ({
 *     title: product.value?.name,
 *     description: product.value?.meta_description || product.value?.description,
 *     keywords: product.value?.meta_keywords,
 *     jsonLd: buildProductJsonLd(product.value),
 *   }))
 *
 * 入参可以是 ref、reactive、computed 或普通对象，也可以是返回对象的函数。
 * 组件卸载时自动恢复 head 到站点级默认值。
 */
let seoScopeCounter = 0

export function useSeo(source) {
  const scopeId = `scope-${++seoScopeCounter}`
  let current = null

  function apply(raw = toValue(source)) {
    current = normalize(raw)
    renderHead(current, scopeId)
  }

  let appStore = null
  try {
    appStore = useAppStore()
  } catch {
    appStore = null
  }

  onMounted(() => {
    apply()
  })

  watch(
    () => toValue(source),
    (value) => {
      current = normalize(value)
      renderHead(current, scopeId)
    },
    { deep: true },
  )

  if (appStore) {
    watch(
      () => [
        appStore.siteName,
        appStore.browserTitle,
        appStore.siteLogo,
        appStore.siteDescription,
        appStore.siteKeywords,
        appStore.robotsDirective,
        appStore.canonicalBase,
        appStore.verifyGoogle,
        appStore.verifyBaidu,
        appStore.verifyBing,
        appStore.verify360,
        appStore.verifySogou,
      ],
      () => {
        if (current) renderHead(current, scopeId)
      },
    )
  }

  onBeforeUnmount(() => {
    // 恢复到站点级默认，并清理本次 useSeo 写入的 JSON-LD
    renderHead(null, scopeId)
    clearJsonLdForScope(scopeId)
  })
}

function normalize(input) {
  if (!input || typeof input !== 'object') return null
  const pick = (...keys) => {
    for (const k of keys) {
      const v = input[k]
      if (v !== undefined && v !== null && String(v).trim() !== '') return String(v).trim()
    }
    return ''
  }

  return {
    title: pick('title', 'meta_title'),
    description: pick('description', 'meta_description', 'summary'),
    keywords: pick('keywords', 'meta_keywords'),
    canonical: pick('canonical', 'url'),
    jsonLd: input.jsonLd || input.structuredData || null,
    robots: pick('robots'),
  }
}

function renderHead(config, scopeId = 'default') {
  if (typeof document === 'undefined') return

  const appStore = tryUseAppStore()
  const siteName = (appStore?.siteName || '创欧云').trim()
  const browserTitle = (appStore?.browserTitle || siteName).trim()
  const siteDescription = (appStore?.siteDescription
    || '创欧云 — 稳定、安全、高性价比的云服务器与 IDC 服务平台，覆盖香港、美国与国内多地节点。').trim()
  const siteKeywords = (appStore?.siteKeywords
    || '云服务器,独立服务器,高防服务器,云电脑,香港服务器,美国服务器,BGP 多线,IDC 服务,创欧云').trim()
  const canonicalBase = (appStore?.canonicalBase || '').replace(/\/$/, '')
  const robotsDefault = (appStore?.robotsDirective || 'index,follow').trim()
  const currentPath = typeof window !== 'undefined' ? `${window.location.pathname}${window.location.search}` : '/'

  const siteTagline = deriveSiteTagline(siteDescription, siteName)
  const title = (config?.title || '').trim()
  // 避免只输出站点名称导致 Bing 判定「标题过短 / 相同标题」。
  // - 页面已提供 title → "{title} - {browserTitle}"
  // - 页面未提供 title → "{browserTitle} - {tagline}"
  const fullTitle = title
    ? `${title} - ${browserTitle}`
    : (siteTagline ? `${browserTitle} - ${siteTagline}` : browserTitle)
  document.title = fullTitle

  setMetaContent('name', 'description', config?.description || siteDescription)
  setMetaContent('name', 'keywords', config?.keywords || siteKeywords)
  setMetaContent('name', 'robots', config?.robots || robotsDefault)

  const canonicalUrl = resolveAbsoluteUrl(config?.canonical || currentPath, canonicalBase)
  setLinkHref('canonical', canonicalUrl)

  // 搜索引擎站长平台验证码（站点级，全站统一输出）
  setMetaContent('name', 'google-site-verification', appStore?.verifyGoogle || '')
  setMetaContent('name', 'baidu-site-verification', appStore?.verifyBaidu || '')
  setMetaContent('name', 'msvalidate.01', appStore?.verifyBing || '')
  setMetaContent('name', '360-site-verification', appStore?.verify360 || '')
  setMetaContent('name', 'sogou_site_verification', appStore?.verifySogou || '')

  applyJsonLd(config?.jsonLd, scopeId)

  // 只有在已经拿到有效内容（标题、描述或 JSON-LD）时才标记 ready，
  // 避免预渲染脚本在动态详情页数据未到位前就抓取了不完整的快照。
  if (typeof window !== 'undefined') {
    const hasContent = Boolean(
      config
      && (config.title || config.description || (config.jsonLd && (Array.isArray(config.jsonLd) ? config.jsonLd.length : true))),
    )
    if (hasContent) {
      window.__SEO_READY__ = true
    }
  }
}

function tryUseAppStore() {
  try {
    return useAppStore()
  } catch {
    return null
  }
}

/**
 * 从 siteDescription 中派生一段简短的 tagline，用作无标题页面的补充短语，
 * 以避免 Bing 判定「标题过短」。优先拆 `站点名 — 说明` / `站点名 - 说明` 的右半部分。
 */
function deriveSiteTagline(description = '', siteName = '') {
  const text = String(description || '').trim()
  if (!text) return ''

  const separators = [' — ', ' – ', ' - ', '——', '：', ':']
  for (const sep of separators) {
    const idx = text.indexOf(sep)
    if (idx > 0) {
      const rest = text.slice(idx + sep.length).trim()
      if (rest) return truncateTagline(rest)
    }
  }

  // 去掉开头重复的站点名
  const name = String(siteName || '').trim()
  if (name && text.startsWith(name)) {
    return truncateTagline(text.slice(name.length).replace(/^[\s—–\-:：]+/, '').trim())
  }
  return truncateTagline(text)
}

function truncateTagline(value) {
  const text = String(value || '').trim()
  if (!text) return ''
  // 优先在第一个句号 / 中文逗号 / 分号处截断，保持语义完整
  const match = text.match(/^[^。；;]+[。；;]?/)
  const base = (match && match[0]) ? match[0].trim() : text
  if (base.length <= 48) return base
  return `${base.slice(0, 46)}…`
}

function setMetaContent(attribute, name, value) {
  if (typeof document === 'undefined') return
  const content = String(value ?? '').trim()
  if (!content) return
  const selector = `meta[${attribute}="${name}"]`
  let el = document.head.querySelector(selector)
  if (!el) {
    el = document.createElement('meta')
    el.setAttribute(attribute, name)
    document.head.appendChild(el)
  }
  el.setAttribute('content', content)
}

function setLinkHref(rel, href) {
  if (typeof document === 'undefined') return
  const value = String(href ?? '').trim()
  if (!value) return
  let el = document.head.querySelector(`link[rel="${rel}"]`)
  if (!el) {
    el = document.createElement('link')
    el.setAttribute('rel', rel)
    document.head.appendChild(el)
  }
  el.setAttribute('href', value)
}

const JSON_LD_SCOPE_ATTR = 'data-seo-scope'
const JSON_LD_INDEX_ATTR = 'data-seo-index'

function applyJsonLd(data, scopeId = 'default') {
  if (typeof document === 'undefined') return

  const items = Array.isArray(data)
    ? data.filter((item) => item && typeof item === 'object')
    : (data && typeof data === 'object' ? [data] : [])

  const selector = `script[type="application/ld+json"][${JSON_LD_SCOPE_ATTR}="${scopeId}"]`
  const existing = Array.from(document.head.querySelectorAll(selector))

  if (!items.length) {
    existing.forEach((el) => el.remove())
    return
  }

  items.forEach((item, index) => {
    const payload = JSON.stringify(item)
    const existingEl = existing[index]
    if (existingEl) {
      if (existingEl.textContent !== payload) existingEl.textContent = payload
      existingEl.setAttribute(JSON_LD_INDEX_ATTR, String(index))
      return
    }
    const script = document.createElement('script')
    script.type = 'application/ld+json'
    script.setAttribute(JSON_LD_SCOPE_ATTR, scopeId)
    script.setAttribute(JSON_LD_INDEX_ATTR, String(index))
    script.textContent = payload
    document.head.appendChild(script)
  })

  existing.slice(items.length).forEach((el) => el.remove())
}

function clearJsonLdForScope(scopeId) {
  if (typeof document === 'undefined') return
  const selector = `script[type="application/ld+json"][${JSON_LD_SCOPE_ATTR}="${scopeId}"]`
  document.head.querySelectorAll(selector).forEach((el) => el.remove())
}

function resolveAbsoluteUrl(path, base) {
  const str = String(path || '').trim()
  if (!str) return base || ''
  if (/^https?:\/\//i.test(str)) return str
  if (!base) return str
  if (str.startsWith('/')) return `${base}${str}`
  return `${base}/${str}`
}

/**
 * 帮助构建常见的结构化数据（JSON-LD）。
 */
export function buildArticleJsonLd({ headline, description, url, image, datePublished, dateModified, author }) {
  return {
    '@context': 'https://schema.org',
    '@type': 'Article',
    headline: headline || '',
    description: description || '',
    url: url || '',
    image: image ? [image] : undefined,
    datePublished: datePublished || undefined,
    dateModified: dateModified || datePublished || undefined,
    author: author ? { '@type': 'Organization', name: author } : undefined,
  }
}

export function buildProductJsonLd({ name, description, url, image, brand, sku, price, priceCurrency = 'CNY' }) {
  const offers = price !== undefined && price !== null && price !== '' ? {
    '@type': 'Offer',
    priceCurrency,
    price: String(price),
    availability: 'https://schema.org/InStock',
    url: url || '',
  } : undefined

  return {
    '@context': 'https://schema.org',
    '@type': 'Product',
    name: name || '',
    description: description || '',
    sku: sku ? String(sku) : undefined,
    image: image ? [image] : undefined,
    brand: brand ? { '@type': 'Brand', name: brand } : undefined,
    offers,
  }
}

export function buildBreadcrumbJsonLd(items) {
  const list = Array.isArray(items) ? items : []
  return {
    '@context': 'https://schema.org',
    '@type': 'BreadcrumbList',
    itemListElement: list.map((item, index) => ({
      '@type': 'ListItem',
      position: index + 1,
      name: item?.name || '',
      item: item?.url || undefined,
    })),
  }
}

export function buildOrganizationJsonLd({ name, url, logo, sameAs }) {
  return {
    '@context': 'https://schema.org',
    '@type': 'Organization',
    name: name || '',
    url: url || '',
    logo: logo || undefined,
    sameAs: Array.isArray(sameAs) && sameAs.length ? sameAs : undefined,
  }
}

export function buildWebSiteJsonLd({ name, url, description, inLanguage = 'zh-CN', searchUrlTemplate }) {
  const node = {
    '@context': 'https://schema.org',
    '@type': 'WebSite',
    name: name || '',
    url: url || '',
    description: description || undefined,
    inLanguage,
  }

  if (searchUrlTemplate && typeof searchUrlTemplate === 'string') {
    node.potentialAction = {
      '@type': 'SearchAction',
      target: {
        '@type': 'EntryPoint',
        urlTemplate: searchUrlTemplate,
      },
      'query-input': 'required name=search_term_string',
    }
  }

  return node
}
