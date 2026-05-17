#!/usr/bin/env node

import fs from 'node:fs/promises'
import path from 'node:path'

const DIST_DIR = path.resolve(process.cwd(), 'dist')
const DEFAULT_LOCAL_API_BASE = 'http://127.0.0.1:8000'
const FETCH_TIMEOUT_MS = Number(process.env.SITE_VERIFICATION_FETCH_TIMEOUT || 1500)
const MANAGED_START = '<!-- site-verification:managed:start -->'
const MANAGED_END = '<!-- site-verification:managed:end -->'
const SKIP_DIRECTORIES = new Set(['assets', 'novnc', 'vnc'])

const VERIFICATION_FIELDS = [
  { settingKey: 'verify_google', metaName: 'google-site-verification' },
  { settingKey: 'verify_baidu', metaName: 'baidu-site-verification' },
  { settingKey: 'verify_bing', metaName: 'msvalidate.01' },
  { settingKey: 'verify_360', metaName: '360-site-verification' },
  { settingKey: 'verify_sogou', metaName: 'sogou_site_verification' },
]

const API_BASE_CANDIDATES = Array.from(new Set([
  process.env.SITE_CONFIG_API_BASE,
  process.env.SEO_HEAD_API_BASE,
  process.env.SITEMAP_API_BASE,
  process.env.SITE_URL,
  process.env.PRERENDER_BACKEND_TARGET,
  process.env.BACKEND_PROXY_TARGET,
  DEFAULT_LOCAL_API_BASE,
].map(normalizeBase).filter(Boolean)))

async function main() {
  const verificationConfig = await resolveVerificationConfig()
  if (verificationConfig === null) {
    console.warn('[site-verification] skip: site config unavailable')
    return
  }

  const htmlFiles = await collectHtmlFiles(DIST_DIR)
  if (!htmlFiles.length) {
    console.warn('[site-verification] skip: no target html files found under dist')
    return
  }

  let updatedCount = 0
  for (const filePath of htmlFiles) {
    const source = await fs.readFile(filePath, 'utf8')
    const next = injectVerificationBlock(source, verificationConfig)
    if (next === source) continue
    await fs.writeFile(filePath, next, 'utf8')
    updatedCount += 1
  }

  console.log(`[site-verification] updated ${updatedCount}/${htmlFiles.length} html files`)
}

function normalizeBase(value) {
  return String(value || '').trim().replace(/\/$/, '')
}

function normalizeString(value) {
  return String(value ?? '').trim()
}

function escapeHtmlAttribute(value) {
  return String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/"/g, '&quot;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
}

function escapeRegExp(value) {
  return String(value).replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
}

function buildSiteConfigUrl(base) {
  if (/\/api$/i.test(base)) {
    return `${base}/site/config`
  }

  return `${base}/api/site/config`
}

function parseInlineConfig() {
  const raw = normalizeString(process.env.SITE_CONFIG_JSON)
  if (!raw) return undefined

  try {
    return JSON.parse(raw)
  } catch (error) {
    console.warn(`[site-verification] invalid SITE_CONFIG_JSON: ${error.message}`)
    return undefined
  }
}

async function fetchJson(url) {
  if (typeof fetch !== 'function') return null

  const controller = new AbortController()
  const timer = setTimeout(() => controller.abort(), FETCH_TIMEOUT_MS)

  try {
    const response = await fetch(url, {
      headers: { Accept: 'application/json' },
      signal: controller.signal,
    })

    if (!response.ok) {
      return null
    }

    return await response.json()
  } catch {
    return null
  } finally {
    clearTimeout(timer)
  }
}

function normalizeVerificationConfig(raw) {
  if (!raw || typeof raw !== 'object') {
    return null
  }

  const config = {}
  for (const { settingKey, metaName } of VERIFICATION_FIELDS) {
    const content = normalizeString(raw[settingKey])
    if (content) {
      config[metaName] = content
    }
  }

  return config
}

async function resolveVerificationConfig() {
  const inlineConfig = parseInlineConfig()
  if (inlineConfig !== undefined) {
    return normalizeVerificationConfig(inlineConfig?.data ?? inlineConfig)
  }

  for (const base of API_BASE_CANDIDATES) {
    const url = buildSiteConfigUrl(base)
    const payload = await fetchJson(url)
    const config = normalizeVerificationConfig(payload?.data ?? payload)
    if (config !== null) {
      console.log(`[site-verification] loaded config from ${url}`)
      return config
    }
  }

  return null
}

async function collectHtmlFiles(dirPath) {
  const entries = await fs.readdir(dirPath, { withFileTypes: true })
  const files = []

  for (const entry of entries) {
    const lowerName = entry.name.toLowerCase()
    const fullPath = path.join(dirPath, entry.name)

    if (entry.isDirectory()) {
      if (SKIP_DIRECTORIES.has(lowerName)) {
        continue
      }
      files.push(...await collectHtmlFiles(fullPath))
      continue
    }

    if (entry.isFile() && lowerName === 'index.html') {
      files.push(fullPath)
    }
  }

  return files
}

function removeVerificationTags(source) {
  let html = source

  const managedPattern = new RegExp(
    `\\s*${escapeRegExp(MANAGED_START)}[\\s\\S]*?${escapeRegExp(MANAGED_END)}\\s*`,
    'i',
  )
  html = html.replace(managedPattern, '')

  for (const { metaName } of VERIFICATION_FIELDS) {
    const metaPattern = new RegExp(
      `\\s*<meta\\b[^>]*\\bname=["']${escapeRegExp(metaName)}["'][^>]*>\\s*`,
      'gi',
    )
    html = html.replace(metaPattern, '')
  }

  return html
}

function injectVerificationBlock(source, verificationConfig) {
  if (!/<\/head>/i.test(source)) {
    return source
  }

  const newline = source.includes('\r\n') ? '\r\n' : '\n'
  let html = removeVerificationTags(source)

  const tags = VERIFICATION_FIELDS
    .map(({ metaName }) => {
      const content = verificationConfig[metaName]
      if (!content) return ''
      return `  <meta name="${metaName}" content="${escapeHtmlAttribute(content)}" />`
    })
    .filter(Boolean)

  if (!tags.length) {
    return html
  }

  const block = [
    `  ${MANAGED_START}`,
    ...tags,
    `  ${MANAGED_END}`,
  ].join(newline) + newline

  return html.replace(/<\/head>/i, `${block}</head>`)
}

main().catch((error) => {
  console.error(`[site-verification] failed: ${error.message}`)
  process.exitCode = 1
})
