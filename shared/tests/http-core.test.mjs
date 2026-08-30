/**
 * Shared HTTP Core 单元测试
 * 运行: node --experimental-strip-types ./tests/http-core.test.mjs
 */
import assert from 'node:assert/strict'

import {
  isSafeRequest,
  isWriteRequest,
  createRequestId,
  buildSafeRequestKey,
  serializeKeyPart,
  isRetryableError,
  resolveSafeRetryLimit,
  resolveSafeRetryDelay,
} from '../runtime/http/core.ts'

// 1. isSafeRequest
assert.equal(isSafeRequest('get'), true, 'GET 是安全方法')
assert.equal(isSafeRequest('GET'), true, 'GET(大写) 是安全方法')
assert.equal(isSafeRequest('head'), true, 'HEAD 是安全方法')
assert.equal(isSafeRequest('options'), true, 'OPTIONS 是安全方法')
assert.equal(isSafeRequest('post'), false, 'POST 不是安全方法')
assert.equal(isSafeRequest('put'), false, 'PUT 不是安全方法')
assert.equal(isSafeRequest('delete'), false, 'DELETE 不是安全方法')
assert.equal(isSafeRequest(''), false, '空字符串不是安全方法')

// 2. isWriteRequest
assert.equal(isWriteRequest('post'), true, 'POST 是写方法')
assert.equal(isWriteRequest('put'), true, 'PUT 是写方法')
assert.equal(isWriteRequest('patch'), true, 'PATCH 是写方法')
assert.equal(isWriteRequest('delete'), true, 'DELETE 是写方法')
assert.equal(isWriteRequest('get'), false, 'GET 不是写方法')

// 3. createRequestId 生成 UUID 格式
{
  const id = createRequestId()
  assert.equal(typeof id, 'string', 'requestId 应为字符串')
  assert.ok(id.length > 0, 'requestId 不应为空')
  // 应包含 '-' 分隔符（UUID 格式）
  assert.ok(id.includes('-'), 'requestId 应为 UUID 格式')
}

// 4. serializeKeyPart
assert.equal(serializeKeyPart(null), '', 'null 应序列化为空字符串')
assert.equal(serializeKeyPart(undefined), '', 'undefined 应序列化为空字符串')
assert.equal(serializeKeyPart('hello'), 'hello', '字符串应不变')
assert.equal(serializeKeyPart(42), '42', '数字应序列化为字符串')
assert.equal(serializeKeyPart([1, 2, 3]), '[1,2,3]', '数组应序列化')
assert.equal(serializeKeyPart({ a: 1, b: 2 }), '{a:1,b:2}', '对象应按键排序序列化')
{
  const date = new Date('2026-01-01T00:00:00.000Z')
  assert.ok(serializeKeyPart(date).startsWith('2026'), 'Date 应序列化为 ISO 字符串')
}

// 5. buildSafeRequestKey 相同请求生成相同 key
{
  const key1 = buildSafeRequestKey({ method: 'get', baseURL: '/api', url: '/users', params: { page: 1 } })
  const key2 = buildSafeRequestKey({ method: 'get', baseURL: '/api', url: '/users', params: { page: 1 } })
  assert.equal(key1, key2, '相同请求应生成相同 key')
}
{
  const key1 = buildSafeRequestKey({ method: 'get', url: '/users', params: { page: 1 } })
  const key2 = buildSafeRequestKey({ method: 'get', url: '/users', params: { page: 2 } })
  assert.notEqual(key1, key2, '不同参数应生成不同 key')
}

// 6. resolveSafeRetryLimit
assert.equal(resolveSafeRetryLimit(3), 3, '显式指定次数应保留')
assert.equal(typeof resolveSafeRetryLimit(), 'number', '默认值应为数字')

// 7. resolveSafeRetryDelay 递增延迟
{
  const delay1 = resolveSafeRetryDelay({}, 1)
  const delay2 = resolveSafeRetryDelay({}, 2)
  // 第二次重试延迟应 >= 第一次
  assert.ok(delay2 >= delay1, '第二次重试延迟不应小于第一次')
}

// 8. isRetryableError — 取消的请求不重试
{
  assert.equal(isRetryableError({ code: 'ERR_CANCELED' }), false, '取消的请求不应重试')
  assert.equal(isRetryableError({ name: 'CanceledError' }), false, 'CanceledError 不应重试')
}

// 9. isRetryableError — 可重试状态码
{
  for (const code of [408, 425, 429, 500, 502, 503, 504]) {
    assert.equal(isRetryableError({ response: { status: code } }), true, `${code} 应可重试`)
  }
}

// 10. isRetryableError — 不可重试状态码
{
  assert.equal(isRetryableError({ response: { status: 400 } }), false, '400 不应重试')
  assert.equal(isRetryableError({ response: { status: 404 } }), false, '404 不应重试')
}

// 11. isRetryableError — 无响应的网络错误不应重试
{
  assert.equal(isRetryableError({ code: 'ECONNABORTED' }), false, '超时（无响应）不应重试')
  assert.equal(isRetryableError({ code: 'ETIMEDOUT' }), false, 'ETIMEDOUT（无响应）不应重试')
  assert.equal(isRetryableError({ code: 'ERR_NETWORK' }), false, 'ERR_NETWORK（CORS/断网）不应重试')
}

// 12. isRetryableError — 无响应的消息类错误不应重试
{
  assert.equal(isRetryableError({ message: 'request timeout' }), false, 'timeout 消息（无响应）不应重试')
  assert.equal(isRetryableError({ message: 'network error occurred' }), false, 'network error 消息不应重试')
  assert.equal(isRetryableError({ message: 'Failed to fetch' }), false, 'Failed to fetch 不应重试')
}

// 13. buildSafeRequestKey 考虑 responseType
{
  const key1 = buildSafeRequestKey({ method: 'get', url: '/data', responseType: 'json' })
  const key2 = buildSafeRequestKey({ method: 'get', url: '/data', responseType: 'blob' })
  assert.notEqual(key1, key2, '不同 responseType 应生成不同 key')
}

// 14. resolveSafeRetryDelay — 带 retry-after 头
{
  const delay = resolveSafeRetryDelay({ response: { headers: { 'retry-after': '5' } } }, 1)
  assert.ok(delay > 0 && delay <= 5000, 'retry-after 头应限制延迟上限')
}

console.log('http core tests passed')
