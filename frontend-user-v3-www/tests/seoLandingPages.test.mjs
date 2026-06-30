import assert from 'node:assert/strict'
import {
  buildSeoLandingStructuredData,
  buildSeoLandingRouteMeta,
  getSeoLandingPageByPath,
  listSeoLandingSitemapRoutes,
  seoLandingPages,
} from '../src/data/seoLandingPages.js'

const expectedPaths = [
  '/cloud-server',
  '/hong-kong-server',
  '/us-server',
  '/high-defense-server',
  '/cloud-pc',
]

assert.deepEqual(seoLandingPages.map((page) => page.path), expectedPaths)
assert.equal(new Set(seoLandingPages.map((page) => page.path)).size, seoLandingPages.length)

for (const page of seoLandingPages) {
  assert.equal(getSeoLandingPageByPath(page.path)?.slug, page.slug)
  assert.ok(page.title.includes('创欧云'), `${page.path} title should include brand`)
  assert.ok(page.description.includes(page.keyword), `${page.path} description should include keyword`)
  assert.ok(page.keywords.includes(page.keyword), `${page.path} keywords should include keyword`)
  assert.ok(page.hero?.title.includes(page.keyword), `${page.path} hero title should include keyword`)
  assert.ok(page.features.length >= 3, `${page.path} should expose feature content`)
  assert.ok(page.scenarios.length >= 3, `${page.path} should expose scenario content`)
  assert.ok(page.faqs.length >= 3, `${page.path} should expose FAQ content`)

  const meta = buildSeoLandingRouteMeta(page)
  assert.equal(meta.title, page.title)
  assert.equal(meta.description, page.description)
  assert.equal(meta.keywords, page.keywords)
  assert.equal(meta.canonical, page.path)
  assert.equal(meta.seoLandingPath, page.path)
  assert.equal(typeof meta.structuredData, 'function')

  const structuredData = buildSeoLandingStructuredData(page, 'https://www.coyjs.cn')
  assert.ok(Array.isArray(structuredData), `${page.path} structured data should be an array`)
  assert.ok(structuredData.some((item) => item['@type'] === 'Organization'), `${page.path} should expose Organization JSON-LD`)
  assert.ok(structuredData.some((item) => item['@type'] === 'WebSite'), `${page.path} should expose WebSite JSON-LD`)
  assert.ok(structuredData.some((item) => item['@type'] === 'WebPage'), `${page.path} should expose WebPage JSON-LD`)
  assert.ok(structuredData.some((item) => item['@type'] === 'BreadcrumbList'), `${page.path} should expose BreadcrumbList JSON-LD`)
}

const sitemapRoutes = listSeoLandingSitemapRoutes()
assert.deepEqual(sitemapRoutes.map((route) => route.path), expectedPaths)

for (const route of sitemapRoutes) {
  assert.ok(route.title)
  assert.ok(route.description)
  assert.ok(route.keywords)
  assert.match(route.priority, /^0\.\d$/)
  assert.match(route.changefreq, /^(daily|weekly|monthly)$/)
  assert.equal(typeof route.structuredData, 'function')
}

console.log('seoLandingPages tests passed')
