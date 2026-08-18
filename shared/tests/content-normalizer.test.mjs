import assert from 'node:assert/strict'

import {
  normalizeContentArticleList,
  normalizeContentDetailPayload,
  normalizeContentListPayload,
  normalizeContentOverviewPayload,
  normalizeSiteHomePayload,
} from '../content/contentNormalizer.ts'

// 文章列表归一化：字段别名与数值化
const articles = normalizeContentArticleList([
  { article_id: '7', title: '通知标题', categoryId: 3, views: '12' },
  { id: 9, name: '帮助文档', category_name: 'FAQ' },
])

assert.equal(articles[0].id, 7)
assert.equal(articles[0].category_id, 3)
assert.equal(articles[0].view_count, 12)
assert.equal(articles[1].title, '帮助文档')
assert.equal(articles[1].category_name, 'FAQ')

// 列表载荷：分页字段默认值
{
  const payload = normalizeContentListPayload({ list: [{ article_id: '1' }] })
  assert.equal(payload.page, 1)
  assert.equal(payload.page_size, 10)
  assert.equal(payload.total, 0)
  assert.equal(payload.list.length, 1)
}

// 列表载荷：options 透传，文章封面图走资源解析
{
  const payload = normalizeContentListPayload(
    { list: [{ article_id: '1', cover_image: '/uploads/a.jpg' }] },
    { resolveAssetUrl: (value) => `https://cdn.example.test/${String(value || '').replace(/^\/+/, '')}` },
  )
  assert.equal(payload.list[0].cover_image, 'https://cdn.example.test/uploads/a.jpg')
}

// 概览载荷：数组字段归一
{
  const payload = normalizeContentOverviewPayload({
    notices: [{ article_id: '2' }],
    help_articles: undefined,
    notice_categories: null,
    helpCategories: [{ category_id: '5' }],
  })
  assert.equal(payload.notices[0].id, 2)
  assert.deepEqual(payload.help_articles, [])
  assert.deepEqual(payload.notice_categories, [])
  assert.equal(payload.help_categories[0].id, 5)
}

// 详情载荷：article 包裹与顶层直传
{
  assert.equal(normalizeContentDetailPayload({ article: { article_id: '3' } }).id, 3)
  assert.equal(normalizeContentDetailPayload({ id: 4, title: '直传' }).id, 4)
}

// 资源解析器：注入后改写 uploads 路径，缺省时原样返回
{
  const resolver = (value) => `https://cdn.example.test/${String(value || '').replace(/^\/+/, '')}`
  const withResolver = normalizeContentArticleList([{ article_id: '1', cover_image: '/uploads/a.jpg' }], {
    resolveAssetUrl: resolver,
  })
  assert.equal(withResolver[0].cover_image, 'https://cdn.example.test/uploads/a.jpg')

  const withoutResolver = normalizeContentArticleList([{ article_id: '1', cover_image: '/uploads/a.jpg' }])
  assert.equal(withoutResolver[0].cover_image, '/uploads/a.jpg')
}

// 站点首页载荷：site_config 归一并走资源解析
{
  const home = normalizeSiteHomePayload(
    { siteConfig: { siteName: '官网', site_logo: '/uploads/logo.svg' }, notices: [] },
    { resolveAssetUrl: (value) => `https://cdn.example.test/${String(value || '').replace(/^\/+/, '')}` },
  )
  assert.equal(home.site_config.site_name, '官网')
  assert.equal(home.site_config.site_logo, 'https://cdn.example.test/uploads/logo.svg')
  assert.deepEqual(home.root_groups, [])
}

console.log('content normalizer tests passed')
