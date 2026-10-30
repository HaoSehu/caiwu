import { mkdir, readFile, writeFile } from 'node:fs/promises'
import path from 'node:path'
import { listSeoLandingSitemapRoutes } from '../src/data/seoLandingMeta.js'
import { loadBuildEnv } from './build-env.mjs'
import { precompressAssets } from './precompress-assets.mjs'

const distDir = path.resolve(process.cwd(), 'dist')
const indexPath = path.join(distDir, 'index.html')
const DEFAULT_SITE_URL = 'https://www.coyjs.cn'
loadBuildEnv()
const siteUrl = normalizeSiteUrl(process.env.VITE_PUBLIC_SITE_URL || DEFAULT_SITE_URL)

const staticRoutes = [
  {
    path: '/',
    title: '创欧云 - 稳定、安全、高性价比的云服务器与 IDC 服务平台',
    description: '创欧云提供云服务器、独立服务器、云电脑与 IDC 服务，覆盖香港、美国与国内多地节点。',
  },
  {
    path: '/products',
    title: '产品与服务 - 创欧云',
    description: '浏览创欧云云服务器、独立服务器、云电脑与 IDC 产品方案。',
  },
  {
    path: '/about',
    title: '关于我们 - 创欧云',
    description: '了解创欧云的 IDC 服务能力、节点覆盖和平台优势。',
  },
  {
    path: '/terms',
    title: '服务条款 - 创欧云',
    description: '查看创欧云服务条款。',
  },
  {
    path: '/privacy',
    title: '隐私政策 - 创欧云',
    description: '查看创欧云隐私政策。',
  },
  {
    path: '/notices',
    title: '官方公告 - 创欧云',
    description: '查看创欧云平台公告和服务通知。',
  },
  {
    path: '/help',
    title: '帮助中心 - 创欧云',
    description: '查看创欧云产品购买、账单支付和服务管理帮助。',
  },
]

const routes = [
  ...staticRoutes,
  ...listSeoLandingSitemapRoutes(),
]

function normalizeSiteUrl(value) {
  return String(value || '').replace(/\/+$/, '') || DEFAULT_SITE_URL
}

function escapeHtml(value) {
  return String(value)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
}

function canonicalFor(routePath) {
  return `${siteUrl}${routePath === '/' ? '/' : routePath}`
}

function replaceTag(html, pattern, replacement) {
  if (pattern.test(html)) {
    return html.replace(pattern, replacement)
  }

  return html.replace('</head>', `  ${replacement}\n</head>`)
}

// vite.config.js 的 client-first-screen-hints 会把首屏依赖拆成 layout / home 两组，
// 分别打 data-first-screen 标记。首页路由 chunk 只有在 / 才用得上，
// 其余预渲染路由（含 5 条 SEO 落地页）带着它等于多下约 17 KB gzip。
function stripHomeFirstScreenHints(html) {
  return html.replace(/[ \t]*<link[^>]*data-first-screen="home"[^>]*>\r?\n/g, '')
}

function renderStructuredData(route) {
  if (typeof route.structuredData !== 'function') return ''
  const payload = route.structuredData(siteUrl)
  if (!payload) return ''

  return `<script type="application/ld+json" id="route-structured-data">${JSON.stringify(payload)}</script>`
}

function renderRouteHtml(template, route) {
  const title = escapeHtml(route.title)
  const description = escapeHtml(route.description)
  const keywords = escapeHtml(route.keywords || '')
  const canonical = canonicalFor(route.path)
  const structuredData = renderStructuredData(route)

  let html = template
    .replace(/<title>.*?<\/title>/i, `<title>${title}</title>`)
    .replace(/<meta name="description" content=".*?" \/>/i, `<meta name="description" content="${description}" />`)
    .replace(/<meta property="og:title" content=".*?" \/>/i, `<meta property="og:title" content="${title}" />`)
    .replace(/<meta property="og:description" content=".*?" \/>/i, `<meta property="og:description" content="${description}" />`)
    .replace(/<meta property="og:url" content=".*?" \/>/i, `<meta property="og:url" content="${canonical}" />`)

  if (keywords) {
    html = html.replace(/<meta name="keywords" content=".*?" \/>/i, `<meta name="keywords" content="${keywords}" />`)
  }

  if (route.robots) {
    html = replaceTag(
      html,
      /<meta name="robots" content=".*?" \/>/i,
      `<meta name="robots" content="${route.robots}" />`,
    )
  }

  html = replaceTag(
    html,
    /<link rel="canonical" href=".*?" \/>/i,
    `<link rel="canonical" href="${canonical}" />`,
  )

  if (structuredData) {
    html = replaceTag(
      html,
      /<script type="application\/ld\+json" id="route-structured-data">.*?<\/script>/i,
      structuredData,
    )
  }

  if (route.path !== '/') {
    html = stripHomeFirstScreenHints(html)
  }

  // 分组标记只服务于上面这步裁剪，产物里不该留下非标准属性。
  html = html.replace(/ data-first-screen="[a-z]+"/g, '')

  return html
}

async function writeRoute(route, html) {
  if (route.path === '/') {
    await writeFile(indexPath, html, 'utf8')
    return
  }

  const outputDirectory = path.join(distDir, route.path.replace(/^\/+/, ''))
  await mkdir(outputDirectory, { recursive: true })
  await writeFile(path.join(outputDirectory, 'index.html'), html, 'utf8')
}

const template = await readFile(indexPath, 'utf8')

for (const route of routes) {
  await writeRoute(route, renderRouteHtml(template, route))
}

// 软 404：为不存在的 URL 输出静态 404.html，配合 Nginx `error_page 404 = /404.html`
// 返回真实 404 状态码，避免未知 URL 被当作首页内容索引（soft-404）
await writeFile(
  path.join(distDir, '404.html'),
  renderRouteHtml(template, {
    path: '/404',
    title: '404 - 页面不存在',
    description: '您访问的页面不存在或已被移动。',
    robots: 'noindex,nofollow',
  }),
  'utf8',
)

// 预渲染会重写根 index.html，并新增 staticRoutes + SEO 落地页 + 404.html 十余个 HTML。
// vite 插件的 closeBundle 早于本脚本执行，那时这些文件还不存在（或内容仍是旧的），
// 所以必须在写完所有路由后重新跑一遍预压缩，否则线上永远拿到落后一版的 .gz/.br。
const precompression = await precompressAssets(distDir)
console.log(
  `Precompressed ${precompression.processed} files (${precompression.gzipCount} .gz / ${precompression.brotliCount} .br) for ${siteUrl}`,
)
console.log(`Prerendered ${routes.length} public routes for ${siteUrl}`)
