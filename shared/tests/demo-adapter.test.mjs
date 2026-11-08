/**
 * 演示 adapter 单元测试
 * 运行: node --experimental-strip-types ./tests/demo-adapter.test.mjs
 */
import assert from 'node:assert/strict'

import { buildDemoKey, createDemoAdapter, matchDemoKey } from '../runtime/http/demoAdapter.ts'

// 1. 键规范化：去 origin、去查询串（含 _t 防缓存）、去尾斜杠、统一大写方法
// buildDemoKey 是纯规范化，不剥 baseURL 前缀；adapter 内部会按 config.baseURL 剥掉。
assert.equal(buildDemoKey('get', 'https://api.example.test/api/v2/admin/orders?page=2&_t=1730000000'), 'GET /api/v2/admin/orders')
assert.equal(buildDemoKey('POST', '/v2/site/products/12/quote'), 'POST /v2/site/products/12/quote')
assert.equal(buildDemoKey('get', '/v2/site/config/'), 'GET /v2/site/config')
assert.equal(buildDemoKey('', '/v2/site/config'), 'GET /v2/site/config')

// 2. 精确匹配优先，其次单段通配；跨段不命中
const dataset = {
  endpoints: {
    'GET /v2/admin/orders': { list: [{ id: 1 }], total: 1 },
    'GET /v2/admin/products/*': { id: 0, name: '通配商品' },
    'POST /v2/site/products/*/quote': { total: '99.00' },
  },
}
assert.deepEqual(matchDemoKey(dataset, 'GET', '/v2/admin/orders?page=1')?.data, { list: [{ id: 1 }], total: 1 })
assert.deepEqual(matchDemoKey(dataset, 'GET', '/v2/admin/products/12')?.data, { id: 0, name: '通配商品' })
assert.deepEqual(matchDemoKey(dataset, 'POST', '/v2/site/products/7/quote')?.data, { total: '99.00' })
assert.equal(matchDemoKey(dataset, 'GET', '/v2/admin/nope'), null)
// 通配只吃一个路径段：/products/12/gallery 不应命中 /products/*
assert.equal(matchDemoKey(dataset, 'GET', '/v2/admin/products/12/gallery'), null)
// 方法必须一致
assert.equal(matchDemoKey(dataset, 'DELETE', '/v2/admin/orders'), null)

// 3. adapter 返回成功信封；未命中同样成功但 data 为空对象
{
  const adapter = createDemoAdapter({ dataset })
  const hit = await adapter({ url: '/v2/admin/orders', method: 'get', headers: {} })
  assert.deepEqual(hit.data, { code: 0, message: 'ok', data: { list: [{ id: 1 }], total: 1 } })
  assert.equal(hit.status, 200)

  const miss = await adapter({ url: '/v2/admin/nope', method: 'get', headers: {} })
  assert.deepEqual(miss.data, { code: 0, message: 'ok', data: {} })

  const write = await adapter({ url: '/v2/admin/orders', method: 'post', headers: {}, data: '{"x":1}' })
  assert.equal(write.data.code, 0, '写操作未命中也要成功，避免页面卡在错误提示')
}

// 4. fallbackData 可定制
{
  const adapter = createDemoAdapter({ dataset, fallbackData: { list: [], total: 0 } })
  const miss = await adapter({ url: '/v2/admin/nope', method: 'get', headers: {} })
  assert.deepEqual(miss.data.data, { list: [], total: 0 })
}

// 5. dataUrl 加载失败时仍返回成功信封，不抛错
{
  const adapter = createDemoAdapter({ dataUrl: 'http://127.0.0.1:1/never.json' })
  const response = await adapter({ url: '/v2/admin/orders', method: 'get', headers: {} })
  assert.deepEqual(response.data, { code: 0, message: 'ok', data: {} })
}

// 6. onMatch 回报命中的键
{
  const seen = []
  const adapter = createDemoAdapter({ dataset, onMatch: (key) => seen.push(key) })
  await adapter({ url: '/v2/admin/products/9', method: 'get', headers: {} })
  assert.deepEqual(seen, ['GET /v2/admin/products/*'])
}

// 7. adapter 按 config.baseURL 剥掉路径前缀（三端 demo 配的是 /demo/api）
{
  const adapter = createDemoAdapter({ dataset })
  const hit = await adapter({ url: '/v2/admin/orders', baseURL: '/demo/api', method: 'get', headers: {} })
  assert.equal(hit.data.code, 0)
  assert.deepEqual(hit.data.data, { list: [{ id: 1 }], total: 1 })
}

console.log('demo adapter tests passed')
