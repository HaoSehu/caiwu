import assert from 'node:assert/strict'

import { createHttpClient } from '../runtime/http/client.ts'
import { toUserMessage } from '../runtime/http/userMessage.ts'

function createAdapter(responder) {
  return (config) => new Promise((resolve, reject) => {
    const result = responder(config)
    if (result.reject) {
      const error = Object.assign(new Error('Request failed'), {
        config,
        response: result.response,
        isAxiosError: true,
      })
      error.response.config = config
      reject(error)
      return
    }
    result.response.config = config
    resolve(result.response)
  })
}

function envelopeResponse(data, status = 200) {
  return {
    data,
    status,
    statusText: String(status),
    headers: {},
    config: {},
    request: {},
  }
}

function createTestClient(responder, options = {}) {
  const errors = []
  const unauthorized = []
  const client = createHttpClient({
    baseURL: 'https://api.example.test/api',
    showError: (message) => errors.push(message),
    onUnauthorized: (message) => unauthorized.push(message),
    ...options,
  })
  client.defaults.adapter = createAdapter(responder)
  return { client, errors, unauthorized }
}

// toUserMessage：仅信任中文消息
assert.equal(toUserMessage('登录已过期'), '登录已过期')
assert.equal(toUserMessage('Server Error'), '操作失败，请稍后重试')
assert.equal(toUserMessage(undefined, '自定义回退'), '自定义回退')

// 成功信封直接返回 data 字段内容
{
  const { client, errors } = createTestClient(() => ({
    response: envelopeResponse({ code: 0, message: 'ok', data: { id: 1 } }),
  }))
  const res = await client.get('/anything')
  assert.deepEqual(res, { code: 0, message: 'ok', data: { id: 1 } })
  assert.equal(errors.length, 0)
}

// 业务错误：拦截器提示并标记 __handled
{
  const { client, errors } = createTestClient(() => ({
    response: envelopeResponse({ code: 42200, message: '参数错误', data: null }),
  }))
  await assert.rejects(client.get('/anything'), (err) => {
    assert.equal(err.message, '参数错误')
    assert.equal(err.__handled, true)
    return true
  })
  assert.deepEqual(errors, ['参数错误'])
}

// silentError：不提示且 __handled 为 false，由调用方接管
{
  const { client, errors } = createTestClient(() => ({
    response: envelopeResponse({ code: 42200, message: '参数错误', data: null }),
  }))
  await assert.rejects(client.get('/anything', { silentError: true }), (err) => {
    assert.equal(err.__handled, false)
    return true
  })
  assert.equal(errors.length, 0)
}

// captcha_required：验证码流程接管，不弹通用错误但 __handled 为 true
{
  const { client, errors } = createTestClient(() => ({
    response: envelopeResponse({ code: 42210, message: '请完成验证', data: { captcha_required: true } }),
  }))
  await assert.rejects(client.post('/login', {}), (err) => {
    assert.equal(err.__handled, true)
    assert.equal(err.response?.data?.data?.captcha_required, true)
    return true
  })
  assert.equal(errors.length, 0)
}

// 业务 40100：触发 onUnauthorized，不进入通用提示
{
  const { client, errors, unauthorized } = createTestClient(() => ({
    response: envelopeResponse({ code: 40100, message: '未登录或登录已过期', data: null }),
  }))
  await assert.rejects(client.get('/anything'), (err) => err.message === '未登录或登录已过期')
  assert.deepEqual(unauthorized, ['未登录或登录已过期'])
  assert.equal(errors.length, 0)
}

// HTTP 422：优先展示 data.errors 字段错误，其次服务端 message
{
  const { client, errors } = createTestClient(() => ({
    reject: true,
    response: envelopeResponse(
      { code: 42200, message: '参数验证失败', data: { errors: { email: ['邮箱不能为空'] } } },
      422
    ),
  }))
  await assert.rejects(client.post('/anything', {}), (err) => {
    assert.equal(err.message, '邮箱不能为空')
    assert.equal(err.__handled, true)
    return true
  })
  assert.deepEqual(errors, ['邮箱不能为空'])
}

{
  const { client } = createTestClient(() => ({
    reject: true,
    response: envelopeResponse({ code: 42200, message: '余额不足', data: null }, 422),
  }))
  await assert.rejects(client.post('/anything', {}), (err) => err.message === '余额不足')
}

// HTTP 429：读取 Retry-After
{
  const { client, errors } = createTestClient(() => ({
    reject: true,
    response: {
      data: { code: 42900, message: '请求过于频繁', data: null },
      status: 429,
      statusText: '429',
      headers: { 'retry-after': '30' },
      config: {},
      request: {},
    },
  }))
  await assert.rejects(client.get('/anything'), (err) => err.message === '请求过于频繁，请 30 秒后重试')
  assert.deepEqual(errors, ['请求过于频繁，请 30 秒后重试'])
}

// HTTP 401：触发 onUnauthorized 并原样抛出 axios 错误
{
  const { client, unauthorized, errors } = createTestClient(() => ({
    reject: true,
    response: envelopeResponse({ code: 40100, message: '未登录或登录已过期', data: null }, 401),
  }))
  await assert.rejects(client.get('/anything'), (err) => err.response?.status === 401)
  assert.equal(unauthorized.length, 1)
  assert.equal(errors.length, 0)
}

// 401 透传请求发出时携带的凭证：onUnauthorized 第二参数可用于判断失效是否已过时
{
  const unauthorized = []
  const client = createHttpClient({
    baseURL: 'https://api.example.test/api',
    showError: () => {},
    onUnauthorized: (message, requestToken) => unauthorized.push([message, requestToken]),
    resolveToken: () => 'token-123',
  })
  client.defaults.adapter = createAdapter(() => ({
    reject: true,
    response: envelopeResponse({ message: 'Unauthenticated.' }, 401),
  }))
  await assert.rejects(client.get('/need-auth'), (err) => err.response?.status === 401)
  assert.deepEqual(unauthorized, [['网络异常', 'token-123']])
}

// 业务 40100 信封同样透传请求凭证
{
  const unauthorized = []
  const client = createHttpClient({
    baseURL: 'https://api.example.test/api',
    showError: () => {},
    onUnauthorized: (message, requestToken) => unauthorized.push([message, requestToken]),
    resolveToken: () => 'token-456',
  })
  client.defaults.adapter = createAdapter(() => ({
    response: envelopeResponse({ code: 40100, message: '未登录或登录已过期', data: null }),
  }))
  await assert.rejects(client.get('/anything'), (err) => err.message === '未登录或登录已过期')
  assert.deepEqual(unauthorized, [['未登录或登录已过期', 'token-456']])
}

// 写请求：附带 X-Request-Id 与 Content-Type；resolveToken 注入 Authorization
{
  const seen = []
  const { client } = createTestClient((config) => {
    seen.push(config)
    return { response: envelopeResponse({ code: 0, message: 'ok', data: null }) }
  }, {
    resolveToken: () => 'token-123',
  })
  await client.post('/orders', { sku: 'x' })
  assert.equal(seen[0].headers['X-Request-Id'].length > 0, true)
  assert.equal(seen[0].headers['Content-Type'], 'application/json')
  assert.equal(seen[0].headers.Authorization, 'Bearer token-123')
}

// resolveToken 返回空值时不附带 Authorization（公共站点 GET 免 token）
{
  const seen = []
  const { client } = createTestClient((config) => {
    seen.push(config)
    return { response: envelopeResponse({ code: 0, message: 'ok', data: null }) }
  }, {
    resolveToken: (config) => (String(config.url || '').startsWith('/v2/site/') ? null : 'token-123'),
  })
  await client.get('/v2/site/home')
  assert.equal(seen[0].headers.Authorization, undefined)
  await client.get('/client/services')
  assert.equal(seen[1].headers.Authorization, 'Bearer token-123')
}

// 无响应错误（CORS/断网/超时）：属确定性失败，safe GET 不重试，仅请求一次
{
  const seen = []
  const errors = []
  const client = createHttpClient({
    baseURL: 'https://api.example.test/api',
    showError: (message) => errors.push(message),
  })
  client.defaults.adapter = (config) => {
    seen.push(config.url)
    return Promise.reject(
      Object.assign(new Error('Network Error'), {
        config,
        code: 'ERR_NETWORK',
        isAxiosError: true,
      })
    )
  }
  await assert.rejects(client.get('/anything'), (err) => err.message === '网络异常')
  assert.equal(seen.length, 1, '无响应错误不应重试')
  assert.deepEqual(errors, ['网络异常'])
}

// 可重试状态码：safe GET 自动重试 1 次后成功
{
  let attempts = 0
  const client = createHttpClient({
    baseURL: 'https://api.example.test/api',
    showError: () => {},
  })
  client.defaults.adapter = (config) => {
    attempts += 1
    if (attempts === 1) {
      return Promise.reject(
        Object.assign(new Error('Request failed'), {
          config,
          response: { status: 503, headers: {}, config, data: null },
          isAxiosError: true,
        })
      )
    }
    return Promise.resolve({
      data: { code: 0, message: 'ok', data: null },
      status: 200,
      statusText: '200',
      headers: {},
      config,
      request: {},
    })
  }
  const res = await client.get('/anything')
  assert.equal(attempts, 2, '503 应重试一次')
  assert.equal(res.code, 0)
}

console.log('http client tests passed')
