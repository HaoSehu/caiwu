import { createReadStream, existsSync } from 'node:fs'
import { mkdir, readFile, writeFile } from 'node:fs/promises'
import http from 'node:http'
import https from 'node:https'
import path from 'node:path'
import { fileURLToPath } from 'node:url'
import puppeteer from 'puppeteer'
import { fetchAllEntries } from './fetch-site-entries.mjs'

const __dirname = path.dirname(fileURLToPath(import.meta.url))
const projectRoot = path.resolve(__dirname, '..')
const distDir = path.join(projectRoot, 'dist')

const STATIC_ROUTES = [
  '/',
  '/products',
  '/about',
  '/terms',
  '/privacy',
  '/notices',
  '/help',
]

const PRERENDER_HOST = '127.0.0.1'
const PRERENDER_PORT = Number(process.env.PRERENDER_PORT || 4173)
const PRERENDER_TIMEOUT = Number(process.env.PRERENDER_TIMEOUT || 20000)
const PRERENDER_CONCURRENCY = Math.max(1, Number(process.env.PRERENDER_CONCURRENCY || 2))
const PRERENDER_DYNAMIC_LIMIT = Number(process.env.PRERENDER_DYNAMIC_LIMIT || 100)
const PRERENDER_INCLUDE_DYNAMIC = String(process.env.PRERENDER_INCLUDE_DYNAMIC || '1') !== '0'
const BACKEND_TARGET = new URL(
  String(process.env.PRERENDER_BACKEND_TARGET
    || process.env.BACKEND_PROXY_TARGET
    || 'http://127.0.0.1:8000').trim(),
)
const DYNAMIC_API_BASE = String(process.env.PRERENDER_DYNAMIC_API_BASE
  || `http://${PRERENDER_HOST}:${PRERENDER_PORT}`).trim()
const PROXY_PREFIXES = ['/api', '/uploads', '/storage', '/sanctum']

const CONTENT_TYPES = {
  '.css': 'text/css; charset=utf-8',
  '.gif': 'image/gif',
  '.html': 'text/html; charset=utf-8',
  '.ico': 'image/x-icon',
  '.jpeg': 'image/jpeg',
  '.jpg': 'image/jpeg',
  '.js': 'text/javascript; charset=utf-8',
  '.json': 'application/json; charset=utf-8',
  '.map': 'application/json; charset=utf-8',
  '.png': 'image/png',
  '.svg': 'image/svg+xml',
  '.txt': 'text/plain; charset=utf-8',
  '.webp': 'image/webp',
  '.woff': 'font/woff',
  '.woff2': 'font/woff2',
  '.xml': 'application/xml; charset=utf-8',
}

function resolveStaticFile(urlPath) {
  const decodedPath = decodeURIComponent(urlPath)
  const normalizedPath = decodedPath === '/' ? '/index.html' : decodedPath
  const directPath = path.join(distDir, normalizedPath)

  if (path.extname(normalizedPath) && existsSync(directPath)) {
    return directPath
  }

  if (!path.extname(normalizedPath)) {
    const nestedIndexPath = path.join(distDir, normalizedPath, 'index.html')
    if (existsSync(nestedIndexPath)) {
      return nestedIndexPath
    }
  }

  return path.join(distDir, 'index.html')
}

function proxyRequest(req, res, requestUrl) {
  const client = BACKEND_TARGET.protocol === 'https:' ? https : http
  const proxyHeaders = { ...req.headers, host: BACKEND_TARGET.host }
  const options = {
    protocol: BACKEND_TARGET.protocol,
    hostname: BACKEND_TARGET.hostname,
    port: BACKEND_TARGET.port,
    method: req.method,
    path: `${requestUrl.pathname}${requestUrl.search}`,
    headers: proxyHeaders,
    rejectUnauthorized: false,
  }

  const proxyReq = client.request(options, (proxyRes) => {
    res.writeHead(proxyRes.statusCode || 502, proxyRes.headers)
    proxyRes.pipe(res)
  })

  proxyReq.on('error', (error) => {
    res.writeHead(502, { 'content-type': 'application/json; charset=utf-8' })
    res.end(JSON.stringify({
      code: 502,
      message: `Prerender proxy error: ${error.message}`,
    }))
  })

  req.pipe(proxyReq)
}

function serveStaticFile(filePath, res) {
  const ext = path.extname(filePath).toLowerCase()
  const contentType = CONTENT_TYPES[ext] || 'application/octet-stream'

  res.writeHead(200, { 'content-type': contentType })
  createReadStream(filePath).pipe(res)
}

function createPrerenderServer() {
  return http.createServer((req, res) => {
    if (!req.url) {
      res.writeHead(400)
      res.end('Bad Request')
      return
    }

    const requestUrl = new URL(req.url, `http://${PRERENDER_HOST}:${PRERENDER_PORT}`)

    if (PROXY_PREFIXES.some((prefix) => requestUrl.pathname.startsWith(prefix))) {
      proxyRequest(req, res, requestUrl)
      return
    }

    const filePath = resolveStaticFile(requestUrl.pathname)
    serveStaticFile(filePath, res)
  })
}

async function startServer(server) {
  await new Promise((resolve, reject) => {
    server.once('error', reject)
    server.listen(PRERENDER_PORT, PRERENDER_HOST, () => {
      server.off('error', reject)
      resolve()
    })
  })
}

async function stopServer(server) {
  await new Promise((resolve) => {
    server.close(() => resolve())
  })
}

async function writeSnapshot(route, html) {
  if (route === '/') {
    await writeFile(path.join(distDir, 'index.html'), html, 'utf8')
    return
  }

  const outputDir = path.join(distDir, route.replace(/^\/+/, ''))
  await mkdir(outputDir, { recursive: true })
  await writeFile(path.join(outputDir, 'index.html'), html, 'utf8')
}

async function captureRoute(browser, route) {
  const page = await browser.newPage()

  try {
    await page.setViewport({ width: 1440, height: 900 })
    await page.goto(`http://${PRERENDER_HOST}:${PRERENDER_PORT}${route}`, {
      waitUntil: 'networkidle0',
      timeout: PRERENDER_TIMEOUT,
    })

    await page.waitForFunction(() => window.__SEO_READY__ === true, {
      timeout: 5000,
    }).catch(() => {})

    await page.waitForFunction(
      () => (document.querySelector('#app')?.textContent || '').trim().length > 0,
      { timeout: 5000 },
    ).catch(() => {})

    await page.evaluate(() => new Promise((resolve) => queueMicrotask(resolve)))
    await page.evaluate(() => {
      let titleEl = document.head.querySelector('title')
      if (!titleEl) {
        titleEl = document.createElement('title')
        document.head.appendChild(titleEl)
      }
      titleEl.textContent = document.title
    })

    const html = await page.content()
    await writeSnapshot(route, html)
    console.log(`[prerender] captured ${route}`)
    return { route, ok: true }
  } catch (error) {
    console.warn(`[prerender] failed ${route}: ${error.message}`)
    return { route, ok: false, error }
  } finally {
    await page.close()
  }
}

async function processRoutesInBatches(browser, routes) {
  const results = []
  for (let offset = 0; offset < routes.length; offset += PRERENDER_CONCURRENCY) {
    const batch = routes.slice(offset, offset + PRERENDER_CONCURRENCY)
    const batchResults = await Promise.all(batch.map((route) => captureRoute(browser, route)))
    results.push(...batchResults)
  }
  return results
}

async function resolveDynamicRoutes() {
  if (!PRERENDER_INCLUDE_DYNAMIC) {
    return []
  }

  const { products, notices, helpArticles } = await fetchAllEntries({
    apiBase: DYNAMIC_API_BASE,
    limit: PRERENDER_DYNAMIC_LIMIT,
  })

  const routes = []
  for (const item of products) routes.push(`/products/${item.id}`)
  for (const item of notices) routes.push(`/notices/${item.id}`)
  for (const item of helpArticles) routes.push(`/help/${item.id}`)
  return routes
}

async function ensureDistReady() {
  if (!existsSync(distDir)) {
    throw new Error('未找到 dist 目录，请先执行 npm run build')
  }

  const indexPath = path.join(distDir, 'index.html')
  await readFile(indexPath, 'utf8')
}

async function main() {
  await ensureDistReady()

  const server = createPrerenderServer()
  await startServer(server)

  const browser = await puppeteer.launch({
    headless: true,
    args: ['--no-sandbox', '--disable-setuid-sandbox'],
  })

  try {
    console.log(`[prerender] capturing ${STATIC_ROUTES.length} static routes`)
    const staticResults = await processRoutesInBatches(browser, STATIC_ROUTES)

    const dynamicRoutes = await resolveDynamicRoutes()
    if (dynamicRoutes.length) {
      console.log(`[prerender] capturing ${dynamicRoutes.length} dynamic routes`)
    } else if (PRERENDER_INCLUDE_DYNAMIC) {
      console.log('[prerender] no dynamic entries available (backend may be down or empty); skipping')
    }
    const dynamicResults = await processRoutesInBatches(browser, dynamicRoutes)

    const allResults = [...staticResults, ...dynamicResults]
    const failed = allResults.filter((r) => !r.ok)
    const succeeded = allResults.length - failed.length

    console.log(`[prerender] done: ${succeeded} succeeded, ${failed.length} failed`)
    if (failed.length) {
      for (const f of failed) console.warn(`  - ${f.route}`)
    }
  } finally {
    await browser.close()
    await stopServer(server)
  }
}

main().catch((error) => {
  console.error(`[prerender] failed: ${error.message}`)
  process.exitCode = 1
})
