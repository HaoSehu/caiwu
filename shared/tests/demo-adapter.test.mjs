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

// 3. adapter 返回成功信封；未命中的读请求同样成功但 data 为空对象
{
  const adapter = createDemoAdapter({ dataset })
  const hit = await adapter({ url: '/v2/admin/orders', method: 'get', headers: {} })
  assert.deepEqual(hit.data, { code: 0, message: 'ok', data: { list: [{ id: 1 }], total: 1 } })
  assert.equal(hit.status, 200)

  const miss = await adapter({ url: '/v2/admin/nope', method: 'get', headers: {} })
  assert.deepEqual(miss.data, { code: 0, message: 'ok', data: {} })
}

// 3b. 未登记的写请求必须失败（M-49）：数据集是只读静态文件，
//     返回成功会让「演示里点退款」显示成功，误导体验者
{
  const adapter = createDemoAdapter({ dataset })
  const write = await adapter({ url: '/v2/admin/orders', method: 'post', headers: {}, data: '{"x":1}' })
  assert.equal(write.data.code, 42200, '未登记写请求必须返回失败信封')
  assert.equal(write.data.message, '演示环境不可执行该操作')
  assert.equal(write.data.data, null)

  for (const method of ['put', 'patch', 'delete']) {
    const res = await adapter({ url: '/v2/admin/orders/1', method, headers: {} })
    assert.equal(res.data.code, 42200, `${method} 未登记也必须失败`)
  }

  // 已登记的写端点（如官网报价）仍然成功返回数据
  const registered = createDemoAdapter({ dataset })
  const quote = await registered({ url: '/v2/site/products/7/quote', method: 'post', headers: {}, data: '{}' })
  assert.equal(quote.data.code, 0)
  assert.deepEqual(quote.data.data, { total: '99.00' })
}

// 3c. 退出登录是认证写请求，但只清本地会话、无服务端副作用：演示环境必须成功，
//     否则退出时会弹「演示环境不可执行该操作」的误导错误
{
  const adapter = createDemoAdapter({ dataset })
  const clientLogout = await adapter({ url: '/v2/client/auth/logout', method: 'post', headers: {} })
  assert.equal(clientLogout.data.code, 0, '客户端退出登录在演示环境必须成功')
  assert.equal(clientLogout.data.data, null)

  const adminLogout = await adapter({ url: '/v2/admin/auth/logout', method: 'post', headers: {} })
  assert.equal(adminLogout.data.code, 0, '管理端退出登录在演示环境必须成功')

  const realWrite = await adapter({ url: '/v2/admin/refunds', method: 'post', headers: {}, data: '{}' })
  assert.equal(realWrite.data.code, 42200, '除退出登录外的未登记写请求仍必须拒绝')
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

// 8. requiredFallbacks：数据集失败/缺键/空载荷时返回兜底，保住关键接口
{
  const fallback = { admin: { permissions: ['*'] } };

  // 8a. 数据集加载失败
  const broken = createDemoAdapter({
    dataUrl: 'http://127.0.0.1:1/never.json',
    requiredFallbacks: { 'GET /v2/admin/auth/info': fallback },
  })
  const r1 = await broken({ url: '/v2/admin/auth/info', method: 'get', headers: {} })
  assert.deepEqual(r1.data, { code: 0, message: 'ok', data: fallback })

  // 8b. 命中了但载荷是空对象（auth/info 返回 {}），也要兜底
  const emptyHit = createDemoAdapter({
    dataset: { endpoints: { 'GET /v2/admin/auth/info': {} } },
    requiredFallbacks: { 'GET /v2/admin/auth/info': fallback },
  })
  const r2 = await emptyHit({ url: '/v2/admin/auth/info', method: 'get', headers: {} })
  assert.deepEqual(r2.data, { code: 0, message: 'ok', data: fallback })

  // 8c. 有真实载荷时不覆盖
  const real = { admin: { permissions: ['dashboard.view'] } }
  const realHit = createDemoAdapter({
    dataset: { endpoints: { 'GET /v2/admin/auth/info': real } },
    requiredFallbacks: { 'GET /v2/admin/auth/info': fallback },
  })
  const r3 = await realHit({ url: '/v2/admin/auth/info', method: 'get', headers: {} })
  assert.deepEqual(r3.data, { code: 0, message: 'ok', data: real })
}

console.log('demo adapter tests passed')
