#!/usr/bin/env node
/**
 * 构建后 sitemap 生成脚本
 *
 * 功能：
 *   1. 输出一份包含官网静态路由（/、/products、/about、/terms、/privacy、/notices、/help）的 sitemap.xml
 *   2. 如果配置了 SITEMAP_API_BASE 环境变量，会尝试拉取公告、帮助与商品列表接口，把动态条目也写进 sitemap
 *
 * 使用方式：
 *   SITE_URL=https://www.example.com SITEMAP_API_BASE=https://www.example.com node scripts/generate-sitemap.mjs
 *
 * 默认写入 dist/sitemap.xml；也可通过 SITEMAP_OUTPUT 环境变量指定输出路径。
 */

import fs from 'node:fs/promises'
import path from 'node:path'
import { fetchAllEntries } from './fetch-site-entries.mjs'

const SITE_URL = String(process.env.SITE_URL || '').trim().replace(/\/$/, '')
const API_BASE = String(process.env.SITEMAP_API_BASE || '').trim().replace(/\/$/, '')
const SITEMAP_LIMIT = Number(process.env.SITEMAP_LIMIT || 200)
const OUTPUT_PATH = process.env.SITEMAP_OUTPUT
  || path.resolve(process.cwd(), 'dist/sitemap.xml')
const ROBOTS_INPUT_PATH = path.resolve(process.cwd(), 'public/robots.txt')
const ROBOTS_OUTPUT_PATH = process.env.ROBOTS_OUTPUT
  || path.resolve(process.cwd(), 'dist/robots.txt')

const STATIC_ROUTES = [
  { path: '/', changefreq: 'daily', priority: '1.0' },
  { path: '/products', changefreq: 'daily', priority: '0.9' },
  { path: '/about', changefreq: 'monthly', priority: '0.6' },
  { path: '/notices', changefreq: 'daily', priority: '0.7' },
  { path: '/help', changefreq: 'weekly', priority: '0.7' },
  { path: '/terms', changefreq: 'yearly', priority: '0.3' },
  { path: '/privacy', changefreq: 'yearly', priority: '0.3' },
]

function resolveUrl(pathname) {
  const normalized = pathname.startsWith('/') ? pathname : `/${pathname}`
  if (!SITE_URL) return normalized
  return `${SITE_URL}${normalized}`
}

function xmlEscape(value) {
  return String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&apos;')
}

function formatEntry({ loc, lastmod, changefreq, priority }) {
  const fields = [
    `  <url>`,
    `    <loc>${xmlEscape(loc)}</loc>`,
  ]
  if (lastmod) fields.push(`    <lastmod>${xmlEscape(lastmod)}</lastmod>`)
  if (changefreq) fields.push(`    <changefreq>${changefreq}</changefreq>`)
  if (priority) fields.push(`    <priority>${priority}</priority>`)
  fields.push(`  </url>`)
  return fields.join('\n')
}

async function loadDynamicEntries() {
  if (!API_BASE) return { products: [], notices: [], helpArticles: [] }
  return fetchAllEntries({ apiBase: API_BASE, limit: SITEMAP_LIMIT })
}

function toSitemapEntry({ loc, updatedAt, changefreq, priority }) {
  return {
    loc,
    lastmod: updatedAt,
    changefreq,
    priority,
  }
}

async function syncRobotsTxt() {
  try {
    const source = await fs.readFile(ROBOTS_INPUT_PATH, 'utf8')
    const sitemapAbsolute = SITE_URL ? `${SITE_URL}/sitemap.xml` : '/sitemap.xml'
    const rewritten = source.replace(/^Sitemap:.*$/mi, `Sitemap: ${sitemapAbsolute}`)
    const finalContent = /^Sitemap:/mi.test(rewritten) ? rewritten : `${rewritten.trimEnd()}\n\nSitemap: ${sitemapAbsolute}\n`
    await fs.mkdir(path.dirname(ROBOTS_OUTPUT_PATH), { recursive: true })
    await fs.writeFile(ROBOTS_OUTPUT_PATH, finalContent, 'utf8')
    console.log(`[sitemap] wrote ${ROBOTS_OUTPUT_PATH} (Sitemap: ${sitemapAbsolute})`)
  } catch (error) {
    if (error?.code === 'ENOENT') {
      console.warn(`[sitemap] skip robots.txt sync: ${ROBOTS_INPUT_PATH} not found`)
      return
    }
    console.warn(`[sitemap] robots.txt sync failed: ${error.message}`)
  }
}

async function main() {
  const today = new Date().toISOString().slice(0, 10)

  const staticEntries = STATIC_ROUTES.map((route) => ({
    loc: resolveUrl(route.path),
    lastmod: today,
    changefreq: route.changefreq,
    priority: route.priority,
  }))

  const { products, notices, helpArticles } = await loadDynamicEntries()

  const productEntries = products.map((item) => toSitemapEntry({
    loc: resolveUrl(`/products/${item.id}`),
    updatedAt: item.updatedAt || today,
    changefreq: 'weekly',
    priority: '0.7',
  }))

  const noticeEntries = notices.map((item) => toSitemapEntry({
    loc: resolveUrl(`/notices/${item.id}`),
    updatedAt: item.updatedAt || today,
    changefreq: 'weekly',
    priority: '0.6',
  }))

  const helpEntries = helpArticles.map((item) => toSitemapEntry({
    loc: resolveUrl(`/help/${item.id}`),
    updatedAt: item.updatedAt || today,
    changefreq: 'monthly',
    priority: '0.5',
  }))

  const allEntries = [...staticEntries, ...productEntries, ...noticeEntries, ...helpEntries].filter(
    (entry) => entry?.loc
  )

  const xml = [
    `<?xml version="1.0" encoding="UTF-8"?>`,
    `<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">`,
    ...allEntries.map(formatEntry),
    `</urlset>`,
    '',
  ].join('\n')

  await fs.mkdir(path.dirname(OUTPUT_PATH), { recursive: true })
  await fs.writeFile(OUTPUT_PATH, xml, 'utf8')

  console.log(`[sitemap] wrote ${OUTPUT_PATH}`)
  console.log(`  static routes: ${staticEntries.length}`)
  console.log(`  products:      ${productEntries.length}`)
  console.log(`  notices:       ${noticeEntries.length}`)
  console.log(`  help articles: ${helpEntries.length}`)
  if (!SITE_URL) {
    console.warn('[sitemap] SITE_URL 未设置，使用了相对路径，请部署前重新执行并传入主域名。')
  }
  if (!API_BASE) {
    console.warn('[sitemap] SITEMAP_API_BASE 未设置，本次仅写入静态路由。')
  }

  await syncRobotsTxt()
}

main().catch((error) => {
  console.error('[sitemap] generate failed:', error)
  process.exit(1)
})
