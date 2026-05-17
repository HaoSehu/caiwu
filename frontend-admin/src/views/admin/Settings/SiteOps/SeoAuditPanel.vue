<template>
  <div class="seo-audit-panel">
    <iframe
      ref="auditFrame"
      class="audit-frame"
      sandbox="allow-same-origin allow-scripts"
      referrerpolicy="origin"
      aria-hidden="true"
      title="SEO 自检抓取 iframe"
    />

    <section class="audit-section">
      <h3 class="audit-section-title">1. 外部诊断工具</h3>
      <p class="audit-section-desc">
        下方按钮会在新标签页打开各家权威检测工具（PageSpeed、Mobile-Friendly、Schema 等）的分析结果。与下方「自动检测」互补使用。
      </p>
      <el-form label-position="top" class="audit-form">
        <el-form-item label="目标页面 URL">
          <el-input
            v-model="targetUrl"
            placeholder="例如：https://www.example.com/products"
            clearable
          >
            <template #append>
              <el-button :icon="HomeFilled" @click="resetToOrigin">回到站点根</el-button>
            </template>
          </el-input>
        </el-form-item>
      </el-form>
      <div class="audit-tools">
        <el-button
          v-for="tool in externalTools"
          :key="tool.key"
          :disabled="!canOpen"
          :icon="Promotion"
          @click="openTool(tool)"
        >
          {{ tool.name }}
        </el-button>
      </div>
      <p v-if="!canOpen" class="audit-hint">请先填写包含 http:// 或 https:// 的完整 URL。</p>
    </section>

    <el-divider />

    <section class="audit-section">
      <div class="audit-checklist-head">
        <div>
          <h3 class="audit-section-title">2. SEO 自动检测</h3>
          <p class="audit-section-desc">
            点击「开始自动检测」后，会在隐藏 iframe 中加载上方 URL，等 Vue 页面 hydrate 完成后读取真实的 DOM（能拿到 useSeo 注入的 title / canonical / h1 等）。如果目标与管理端跨域会自动回退到 fetch 抓包。
          </p>
        </div>
        <div class="audit-progress">
          <span class="audit-stats">
            <span class="stat stat-ok" title="通过">✓ {{ okCount }}</span>
            <span class="stat stat-warn" title="警告">△ {{ warnCount }}</span>
            <span class="stat stat-fail" title="失败">✗ {{ failCount }}</span>
            <span class="stat stat-skip" title="跳过">? {{ skipCount }}</span>
          </span>
          <el-button
            type="primary"
            size="small"
            :icon="Aim"
            :loading="running"
            :disabled="!canOpen"
            @click="runAutoAudit"
          >
            {{ running ? '检测中…' : '开始自动检测' }}
          </el-button>
          <el-button size="small" :icon="RefreshRight" @click="resetChecklist">重置</el-button>
        </div>
      </div>
      <el-alert
        v-if="auditError"
        :title="auditError"
        type="warning"
        :closable="false"
        show-icon
        class="audit-alert"
      />
      <div class="audit-checklist">
        <div v-for="group in checklist" :key="group.key" class="audit-group">
          <div class="audit-group-title">{{ group.title }}</div>
          <div
            v-for="item in group.items"
            :key="item.key"
            class="audit-item"
            :class="`is-${resolveItemStatus(item)}`"
          >
            <span class="audit-item-icon">
              <el-icon v-if="statusMap[item.key] === 'ok'"><CircleCheck /></el-icon>
              <el-icon v-else-if="statusMap[item.key] === 'warn'"><Warning /></el-icon>
              <el-icon v-else-if="statusMap[item.key] === 'fail'"><CircleClose /></el-icon>
              <el-icon v-else-if="statusMap[item.key] === 'skip'"><QuestionFilled /></el-icon>
              <el-icon v-else><Minus /></el-icon>
            </span>
            <div class="audit-item-body">
              <div class="audit-item-label">{{ item.label }}</div>
              <div v-if="detailMap[item.key]" class="audit-item-detail">{{ detailMap[item.key] }}</div>
            </div>
          </div>
        </div>
      </div>
    </section>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import {
  Aim,
  CircleCheck,
  CircleClose,
  HomeFilled,
  Minus,
  Promotion,
  QuestionFilled,
  RefreshRight,
  Warning,
} from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'

const STORAGE_KEY = 'admin_seo_audit_result_v2'

const targetUrl = ref('')
const statusMap = reactive({})
const detailMap = reactive({})
const running = ref(false)
const auditError = ref('')
const auditFrame = ref(null)

const externalTools = [
  {
    key: 'pagespeed',
    name: 'PageSpeed Insights',
    url: (u) => `https://pagespeed.web.dev/analysis?url=${encodeURIComponent(u)}`,
  },
  {
    key: 'mobile',
    name: 'Mobile-Friendly Test',
    url: (u) => `https://search.google.com/test/mobile-friendly?url=${encodeURIComponent(u)}`,
  },
  {
    key: 'rich',
    name: 'Rich Results Test',
    url: (u) => `https://search.google.com/test/rich-results?url=${encodeURIComponent(u)}`,
  },
  {
    key: 'schema',
    name: 'Schema Validator',
    url: (u) => `https://validator.schema.org/?url=${encodeURIComponent(u)}`,
  },
  {
    key: 'html_validator',
    name: 'W3C HTML 校验',
    url: (u) => `https://validator.w3.org/nu/?doc=${encodeURIComponent(u)}`,
  },
]

// ---------- 自动检测函数 ----------

const SKIP_NO_DOC = { status: 'skip', detail: '跨域未能抓取页面，该项无法自动检测' }

function truncate(text, max = 40) {
  const s = String(text || '').trim()
  return s.length > max ? `${s.slice(0, max)}…` : s
}

function checkTitle(ctx) {
  if (!ctx.htmlDoc) return SKIP_NO_DOC
  const title = ctx.htmlDoc.querySelector('title')?.textContent?.trim() || ''
  if (!title) return { status: 'fail', detail: '未找到 <title> 标签' }
  const len = title.length
  if (len < 10) return { status: 'warn', detail: `title 当前 ${len} 字，建议 10–60。内容：${truncate(title)}` }
  if (len > 60) return { status: 'warn', detail: `title 当前 ${len} 字，超过建议上限。内容：${truncate(title)}` }
  return { status: 'ok', detail: `title 当前 ${len} 字：${truncate(title)}` }
}

function checkDescription(ctx) {
  if (!ctx.htmlDoc) return SKIP_NO_DOC
  const meta = ctx.htmlDoc.querySelector('meta[name="description"]')
  const desc = meta?.getAttribute('content')?.trim() || ''
  if (!desc) return { status: 'fail', detail: '未找到 <meta name="description">' }
  const len = desc.length
  if (len < 70) return { status: 'warn', detail: `description 当前 ${len} 字，建议 70–160` }
  if (len > 160) return { status: 'warn', detail: `description 当前 ${len} 字，会被搜索结果截断` }
  return { status: 'ok', detail: `description 当前 ${len} 字` }
}

function checkCanonical(ctx) {
  if (!ctx.htmlDoc) return SKIP_NO_DOC
  const href = ctx.htmlDoc.querySelector('link[rel="canonical"]')?.getAttribute('href')?.trim() || ''
  if (!href) return { status: 'fail', detail: '未找到 <link rel="canonical">' }
  if (!/^https:\/\//i.test(href)) return { status: 'warn', detail: `canonical 未启用 HTTPS：${href}` }
  return { status: 'ok', detail: `canonical：${href}` }
}

function checkRobotsMeta(ctx) {
  if (!ctx.htmlDoc) return SKIP_NO_DOC
  const content = (ctx.htmlDoc.querySelector('meta[name="robots"]')?.getAttribute('content') || '').trim().toLowerCase()
  if (!content) return { status: 'ok', detail: '未设置 robots meta，默认允许收录' }
  if (content.includes('noindex')) return { status: 'warn', detail: `robots meta 为 ${content}（禁止收录）` }
  return { status: 'ok', detail: `robots meta：${content}` }
}

function checkViewport(ctx) {
  if (!ctx.htmlDoc) return SKIP_NO_DOC
  const charset = ctx.htmlDoc.querySelector('meta[charset]')
  const viewport = ctx.htmlDoc.querySelector('meta[name="viewport"]')
  const missing = []
  if (!charset) missing.push('<meta charset>')
  if (!viewport) missing.push('<meta name="viewport">')
  if (missing.length) return { status: 'fail', detail: `缺少：${missing.join('、')}` }
  return { status: 'ok', detail: 'charset 与 viewport 均配置' }
}

function checkH1(ctx) {
  if (!ctx.htmlDoc) return SKIP_NO_DOC
  const h1s = ctx.htmlDoc.querySelectorAll('h1')
  if (h1s.length === 0) return { status: 'fail', detail: '页面没有 <h1>' }
  if (h1s.length > 1) return { status: 'warn', detail: `页面有 ${h1s.length} 个 <h1>，建议仅保留一个` }
  return { status: 'ok', detail: `仅一个 <h1>：${truncate(h1s[0].textContent)}` }
}

function checkHeadingOrder(ctx) {
  if (!ctx.htmlDoc) return SKIP_NO_DOC
  const headings = Array.from(ctx.htmlDoc.querySelectorAll('h1,h2,h3,h4,h5,h6'))
  let lastLevel = 0
  let jumps = 0
  for (const h of headings) {
    const level = Number(h.tagName.slice(1))
    if (lastLevel > 0 && level - lastLevel > 1) jumps += 1
    lastLevel = level
  }
  if (headings.length === 0) return { status: 'warn', detail: '页面未检测到任何标题元素' }
  if (jumps === 0) return { status: 'ok', detail: `${headings.length} 个标题，层级无跳级` }
  return { status: 'warn', detail: `发现 ${jumps} 处跳级（如 h2 跳到 h4）` }
}

function checkImgAlt(ctx) {
  if (!ctx.htmlDoc) return SKIP_NO_DOC
  const imgs = Array.from(ctx.htmlDoc.querySelectorAll('img'))
  if (imgs.length === 0) return { status: 'ok', detail: '页面无图片' }
  const missing = imgs.filter((img) => !(img.getAttribute('alt') || '').trim()).length
  if (missing === 0) return { status: 'ok', detail: `${imgs.length} 张图片均有 alt` }
  const ratio = missing / imgs.length
  if (ratio <= 0.2) return { status: 'warn', detail: `${missing} / ${imgs.length} 张图片缺失 alt` }
  return { status: 'fail', detail: `${missing} / ${imgs.length} 张图片缺失 alt` }
}

function checkLinksDescriptive(ctx) {
  if (!ctx.htmlDoc) return SKIP_NO_DOC
  const links = Array.from(ctx.htmlDoc.querySelectorAll('a'))
  const vague = ['点此', '点击这里', '更多', '阅读更多', 'click here', 'read more', '>>', '→']
  const bad = links.filter((a) => {
    const txt = (a.textContent || '').trim().toLowerCase()
    if (!txt) return false
    return vague.some((kw) => txt === kw.toLowerCase() || txt === kw)
  })
  if (links.length === 0) return { status: 'ok', detail: '页面无链接' }
  if (bad.length === 0) return { status: 'ok', detail: `共 ${links.length} 个链接，未发现「点此/更多」等模糊文案` }
  return { status: 'warn', detail: `${bad.length} / ${links.length} 个链接使用了模糊文案（点此/更多/>>）` }
}

function checkImagesLazy(ctx) {
  if (!ctx.htmlDoc) return SKIP_NO_DOC
  const imgs = Array.from(ctx.htmlDoc.querySelectorAll('img'))
  if (imgs.length === 0) return { status: 'ok', detail: '页面无图片' }
  const lazy = imgs.filter((img) => (img.getAttribute('loading') || '').toLowerCase() === 'lazy').length
  if (lazy === imgs.length) return { status: 'ok', detail: `全部 ${imgs.length} 张图片启用了 loading="lazy"` }
  if (lazy === 0) return { status: 'warn', detail: `0 / ${imgs.length} 张图片启用 lazy` }
  if (lazy >= imgs.length / 2) return { status: 'ok', detail: `${lazy} / ${imgs.length} 张图片启用 lazy（首屏可不加）` }
  return { status: 'warn', detail: `仅 ${lazy} / ${imgs.length} 张图片启用 lazy` }
}

function checkFontsPreload(ctx) {
  if (!ctx.htmlDoc) return SKIP_NO_DOC
  const preloads = Array.from(ctx.htmlDoc.querySelectorAll('link[rel="preload"]')).filter((l) => (l.getAttribute('as') || '') === 'font')
  const swap = /font-display\s*:\s*swap/i.test(ctx.htmlText || '')
  if (preloads.length > 0 && swap) return { status: 'ok', detail: `检测到 ${preloads.length} 个字体 preload，且存在 font-display: swap` }
  if (preloads.length > 0) return { status: 'ok', detail: `检测到 ${preloads.length} 个字体 preload` }
  if (swap) return { status: 'ok', detail: '检测到 font-display: swap 声明' }
  return { status: 'warn', detail: '未检测到 font preload 或 font-display: swap' }
}

function checkHttps(ctx) {
  if (!ctx.targetUrl) return { status: 'fail', detail: '未输入 URL' }
  if (!/^https:\/\//i.test(ctx.targetUrl)) return { status: 'fail', detail: `当前 URL 不是 https://` }
  if (ctx.fetchSuccess) return { status: 'ok', detail: `https 访问成功：${new URL(ctx.targetUrl).host}` }
  if (ctx.fetchError) return { status: 'warn', detail: `URL 为 https，但抓取错误：${ctx.fetchError}` }
  return { status: 'ok', detail: 'URL 为 https' }
}

function checkRobotsTxt(ctx) {
  if (ctx.robotsTxtAccessible === true) return { status: 'ok', detail: `${ctx.origin}/robots.txt 可访问` }
  if (ctx.robotsTxtAccessible === false) return { status: 'fail', detail: `${ctx.origin}/robots.txt 未返回 200` }
  return { status: 'skip', detail: '跨域未能探测 robots.txt' }
}

function checkStructuredData(ctx) {
  if (!ctx.htmlDoc) return SKIP_NO_DOC
  const scripts = Array.from(ctx.htmlDoc.querySelectorAll('script[type="application/ld+json"]'))
  if (scripts.length === 0) return { status: 'warn', detail: '未检测到 JSON-LD 结构化数据' }
  const types = []
  for (const s of scripts) {
    try {
      const raw = s.textContent || '{}'
      const data = JSON.parse(raw)
      const push = (d) => { if (d && d['@type']) types.push(d['@type']) }
      if (Array.isArray(data)) data.forEach(push)
      else push(data)
    } catch {
      // ignore
    }
  }
  return { status: 'ok', detail: `检测到 ${scripts.length} 段 JSON-LD${types.length ? `，类型：${types.join(', ')}` : ''}` }
}

// ---------- checklist ----------

const checklist = [
  {
    key: 'meta',
    title: '基础 meta',
    items: [
      { key: 'title', label: '页面 <title> 控制在 10-60 字符，能表达主体主题', check: checkTitle },
      { key: 'description', label: '<meta name="description"> 填写，70-160 字符', check: checkDescription },
      { key: 'canonical', label: '<link rel="canonical"> 指向当前页的主域名链接', check: checkCanonical },
      { key: 'robots_meta', label: '<meta name="robots"> 允许收录（index,follow）', check: checkRobotsMeta },
      { key: 'viewport', label: '<meta charset> 与 <meta name="viewport"> 存在且正确', check: checkViewport },
    ],
  },
  {
    key: 'heading',
    title: '内容结构',
    items: [
      { key: 'h1', label: '页面有且仅有一个 <h1>，内容与 title 呼应', check: checkH1 },
      { key: 'heading_order', label: '标题层级从 h1 到 h2、h3 有序，不跳级', check: checkHeadingOrder },
      { key: 'img_alt', label: '所有关键图片都有清晰的 alt 属性', check: checkImgAlt },
      { key: 'links_descriptive', label: '内部链接使用有语义的文字而非「点此」', check: checkLinksDescriptive },
    ],
  },
  {
    key: 'performance',
    title: '性能与可访问性',
    items: [
      { key: 'images_lazy', label: '首屏以外的图片启用了懒加载', check: checkImagesLazy },
      { key: 'fonts_preload', label: '关键字体 preload 或设置 font-display: swap', check: checkFontsPreload },
    ],
  },
  {
    key: 'infrastructure',
    title: '基础设施',
    items: [
      { key: 'https', label: '站点部署 HTTPS 证书，HTTP 自动跳转到 HTTPS', check: checkHttps },
      { key: 'robots_txt', label: 'robots.txt 已部署且不屏蔽关键目录', check: checkRobotsTxt },
      { key: 'structured_data', label: '首页、产品页、文章页输出了 JSON-LD 结构化数据', check: checkStructuredData },
    ],
  },
]

const allItemKeys = checklist.flatMap((group) => group.items.map((item) => item.key))
const canOpen = computed(() => /^https?:\/\//i.test(String(targetUrl.value || '').trim()))

const okCount = computed(() => Object.values(statusMap).filter((s) => s === 'ok').length)
const warnCount = computed(() => Object.values(statusMap).filter((s) => s === 'warn').length)
const failCount = computed(() => Object.values(statusMap).filter((s) => s === 'fail').length)
const skipCount = computed(() => Object.values(statusMap).filter((s) => s === 'skip').length)

function resolveItemStatus(item) {
  return statusMap[item.key] || 'idle'
}

onMounted(() => {
  try {
    const raw = localStorage.getItem(STORAGE_KEY)
    if (raw) {
      const saved = JSON.parse(raw)
      if (saved && typeof saved === 'object') {
        if (saved.targetUrl) targetUrl.value = saved.targetUrl
        if (saved.status && typeof saved.status === 'object') {
          for (const key of allItemKeys) {
            if (saved.status[key]) statusMap[key] = saved.status[key]
          }
        }
        if (saved.detail && typeof saved.detail === 'object') {
          for (const key of allItemKeys) {
            if (saved.detail[key]) detailMap[key] = saved.detail[key]
          }
        }
      }
    }
  } catch {
    // ignore
  }
  if (!targetUrl.value && typeof window !== 'undefined') {
    targetUrl.value = window.location.origin.replace(/\/$/, '')
  }
})

watch(
  () => ({
    targetUrl: targetUrl.value,
    status: { ...statusMap },
    detail: { ...detailMap },
  }),
  (val) => {
    try {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(val))
    } catch {
      // ignore
    }
  },
)

function openTool(tool) {
  const url = String(targetUrl.value || '').trim()
  if (!/^https?:\/\//i.test(url)) return
  window.open(tool.url(url), '_blank', 'noopener')
}

function resetToOrigin() {
  if (typeof window !== 'undefined') {
    targetUrl.value = window.location.origin.replace(/\/$/, '')
  }
}

function resetChecklist() {
  for (const key of allItemKeys) {
    delete statusMap[key]
    delete detailMap[key]
  }
  auditError.value = ''
}

async function runAutoAudit() {
  const url = String(targetUrl.value || '').trim()
  if (!/^https?:\/\//i.test(url)) {
    ElMessage.warning('请先填写合法的 http(s):// URL')
    return
  }

  let origin = ''
  try {
    origin = new URL(url).origin
  } catch {
    auditError.value = `URL 格式不正确：${url}`
    return
  }

  running.value = true
  auditError.value = ''

  const ctx = {
    targetUrl: url,
    origin,
    htmlDoc: null,
    htmlText: '',
    fetchSuccess: false,
    fetchError: '',
    robotsTxtAccessible: null,
    loadMode: '',
  }

  // 主页面抓取：优先用 iframe（同源可拿到 SPA hydrate 后的真 DOM），跨域时回退 fetch
  try {
    const doc = await loadInIframe(url, 8000)
    ctx.htmlDoc = doc
    ctx.htmlText = doc.documentElement?.outerHTML || ''
    ctx.fetchSuccess = true
    ctx.loadMode = 'iframe'
  } catch (iframeErr) {
    try {
      const res = await fetch(url, { mode: 'cors', redirect: 'follow' })
      if (!res.ok) throw new Error(`HTTP ${res.status}`)
      ctx.htmlText = await res.text()
      ctx.htmlDoc = new DOMParser().parseFromString(ctx.htmlText, 'text/html')
      ctx.fetchSuccess = true
      ctx.loadMode = 'fetch'
      auditError.value = `iframe 加载未成功（${iframeErr?.message || iframeErr}），已回退到 fetch 抓取静态 HTML。如果目标是 Vue/React SPA，fetch 拿到的是空壳，title / meta / h1 等动态内容看不到。`
    } catch (fetchErr) {
      ctx.fetchError = fetchErr?.message || String(fetchErr)
      auditError.value = `iframe 加载失败：${iframeErr?.message || iframeErr}；fetch 也失败：${ctx.fetchError}。请确保 URL 可访问，并且与管理端同源（避免跨域拦截）。`
    }
  }

  // robots.txt 探测
  try {
    const res = await fetch(`${origin}/robots.txt`, { mode: 'cors' })
    ctx.robotsTxtAccessible = res.ok
  } catch {
    ctx.robotsTxtAccessible = null
  }

  // 逐项执行检测
  for (const group of checklist) {
    for (const item of group.items) {
      try {
        const result = await item.check(ctx)
        statusMap[item.key] = result.status
        detailMap[item.key] = result.detail
      } catch (err) {
        statusMap[item.key] = 'skip'
        detailMap[item.key] = `检测出错：${err?.message || err}`
      }
    }
  }

  running.value = false

  const modeLabel = ctx.loadMode === 'iframe' ? '（iframe 模式）' : ctx.loadMode === 'fetch' ? '（fetch 回退）' : ''
  const summary = `自动检测完成${modeLabel}：✓ ${okCount.value}  △ ${warnCount.value}  ✗ ${failCount.value}  ? ${skipCount.value}`
  if (failCount.value > 0) ElMessage.warning(summary)
  else ElMessage.success(summary)
}

// 隐藏 iframe 加载目标 URL，轮询 contentWindow.__SEO_READY__ 以等 useSeo 跑完；
// 跨域读 contentDocument 会被浏览器报 SecurityError，调用方抓住后回退 fetch。
function loadInIframe(url, maxWaitMs = 8000) {
  return new Promise((resolve, reject) => {
    const iframe = auditFrame.value
    if (!iframe) {
      reject(new Error('iframe 元素未挂载'))
      return
    }

    let settled = false
    let timeoutId = null
    let pollId = null

    const cleanup = () => {
      iframe.onload = null
      if (timeoutId) clearTimeout(timeoutId)
      if (pollId) clearInterval(pollId)
      timeoutId = null
      pollId = null
    }

    const tryRead = (force) => {
      if (settled) return
      let doc = null
      let win = null
      try {
        doc = iframe.contentDocument
        win = iframe.contentWindow
      } catch (err) {
        settled = true
        cleanup()
        reject(new Error(`contentDocument 读取被拦截（可能跨域）：${err?.message || err}`))
        return
      }
      if (!doc || !doc.documentElement) return
      const ready = win && win.__SEO_READY__ === true
      if (force || ready) {
        settled = true
        cleanup()
        resolve(doc)
      }
    }

    iframe.onload = () => {
      // 加载完成后开始轮询 useSeo 是否跑完
      pollId = setInterval(() => tryRead(false), 200)
      // 200ms 后立即试一次（静态页面可能不会设 __SEO_READY__）
      setTimeout(() => tryRead(false), 200)
    }

    timeoutId = setTimeout(() => {
      // 超时则强制读一次（拿到啥算啥，总比报错好）
      tryRead(true)
      if (!settled) {
        settled = true
        cleanup()
        reject(new Error(`iframe 内 useSeo 未在 ${maxWaitMs}ms 内设置 __SEO_READY__，且 contentDocument 仍为空`))
      }
    }, maxWaitMs)

    // 清掉 src 再重设，确保重复点击同一 URL 也会重新加载
    try {
      iframe.removeAttribute('src')
    } catch {
      // ignore
    }
    iframe.src = url
  })
}
</script>

<style scoped lang="scss">
.seo-audit-panel {
  display: flex;
  flex-direction: column;
  gap: 4px;
  position: relative;
}

.audit-frame {
  position: absolute;
  top: -10000px;
  left: -10000px;
  width: 1280px;
  height: 800px;
  border: 0;
  pointer-events: none;
  opacity: 0;
}

.audit-section-title {
  margin: 0;
  font-size: 15px;
  font-weight: 600;
  color: $text-color-primary;
}

.audit-section-desc {
  margin: 4px 0 14px;
  font-size: 12px;
  line-height: 1.6;
  color: $text-color-placeholder;
}

.audit-form :deep(.el-form-item) {
  margin-bottom: 12px;
}

.audit-tools {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.audit-hint {
  margin: 8px 0 0;
  font-size: 12px;
  color: $color-warning;
}

.audit-checklist-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  flex-wrap: wrap;
  margin-bottom: 8px;
}

.audit-progress {
  display: inline-flex;
  align-items: center;
  gap: 12px;
}

.audit-progress-text {
  font-size: 12px;
  color: $text-color-secondary;
}

.audit-checklist {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 18px 24px;
}

.audit-group {
  padding: 14px 16px;
  border: 1px solid $divider-color;
  border-radius: $sm-border-radius;
  background: $bg-color-soft;
}

.audit-group-title {
  font-size: 13px;
  font-weight: 600;
  color: $text-color-primary;
  margin-bottom: 10px;
}

.audit-alert {
  margin-bottom: 12px;
}

.audit-stats {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  font-size: 12px;
}

.stat {
  display: inline-flex;
  align-items: center;
  padding: 2px 8px;
  border-radius: 999px;
  background: $bg-color-card;
  border: 1px solid $border-color;
  color: $text-color-secondary;
  font-variant-numeric: tabular-nums;
}

.stat-ok {
  color: #67c23a;
  border-color: rgba(103, 194, 58, 0.35);
  background: rgba(103, 194, 58, 0.08);
}

.stat-warn {
  color: #e6a23c;
  border-color: rgba(230, 162, 60, 0.4);
  background: rgba(230, 162, 60, 0.1);
}

.stat-fail {
  color: #f56c6c;
  border-color: rgba(245, 108, 108, 0.4);
  background: rgba(245, 108, 108, 0.1);
}

.stat-skip,
.stat-manual {
  color: $text-color-placeholder;
}

.audit-item {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 8px 10px;
  border-radius: $sm-border-radius;
  transition: background 0.2s ease;
  min-width: 0;

  & + .audit-item {
    margin-top: 4px;
  }
}

.audit-item.is-ok {
  background: rgba(103, 194, 58, 0.06);
}

.audit-item.is-warn {
  background: rgba(230, 162, 60, 0.08);
}

.audit-item.is-fail {
  background: rgba(245, 108, 108, 0.08);
}

.audit-item-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  width: 20px;
  height: 20px;
  margin-top: 1px;
  font-size: 16px;
}

.audit-item.is-ok .audit-item-icon :deep(.el-icon) { color: #67c23a; }
.audit-item.is-warn .audit-item-icon :deep(.el-icon) { color: #e6a23c; }
.audit-item.is-fail .audit-item-icon :deep(.el-icon) { color: #f56c6c; }
.audit-item.is-skip .audit-item-icon :deep(.el-icon) { color: $text-color-placeholder; }
.audit-item.is-idle .audit-item-icon :deep(.el-icon) { color: $text-color-placeholder; }

.audit-item-body {
  flex: 1 1 auto;
  min-width: 0;
}

.audit-item-label {
  font-size: 13px;
  line-height: 1.55;
  color: $text-color-primary;
  word-break: break-word;
}

.audit-item-detail {
  margin-top: 2px;
  font-size: 12px;
  line-height: 1.5;
  color: $text-color-secondary;
  word-break: break-word;
}

.audit-item-detail--muted {
  color: $text-color-placeholder;
}

.audit-item-manual :deep(.el-checkbox__inner) {
  transform: scale(0.95);
}

@media (max-width: 960px) {
  .audit-checklist {
    grid-template-columns: 1fr;
  }
}
</style>
