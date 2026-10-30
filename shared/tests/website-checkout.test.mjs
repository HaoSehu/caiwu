/**
 * 官网→控制台待建单载荷契约测试（shared/utils/websiteCheckout.ts）
 * 运行: node --experimental-strip-types ./tests/website-checkout.test.mjs
 *
 * 只覆盖纯函数与可 stub 的存储读写：载荷来自 URL 参数与 sessionStorage，
 * 都是外部可写输入，这里锁定「不合规载荷一律返回 null」的收敛边界。
 */
import assert from 'node:assert/strict'

import {
  CHECKOUT_TTL_MS,
  clearPendingWebsiteCheckout,
  decodePendingWebsiteCheckout,
  encodePendingWebsiteCheckout,
  getPendingWebsiteCheckout,
  normalizePendingWebsiteCheckout,
  savePendingWebsiteCheckout,
} from '../utils/websiteCheckout.ts'

const baseOrder = {
  product_id: 12,
  billing_cycle: 'monthly',
  quantity: 1,
  config: { region: 'hk', cpu: 2 },
}

// 1. 合规载荷：字段按白名单收敛，config 保留一层键值
{
  const payload = normalizePendingWebsiteCheckout({
    source: 'website-products',
    idempotencyKey: 'key-1',
    savedAt: 1700000000000,
    orderPayload: baseOrder,
  })
  assert.ok(payload)
  assert.equal(payload.source, 'website-products')
  assert.equal(payload.idempotencyKey, 'key-1')
  assert.equal(payload.savedAt, 1700000000000)
  assert.equal(payload.orderPayload.product_id, 12)
  assert.equal(payload.orderPayload.billing_cycle, 'monthly')
  assert.equal(payload.orderPayload.quantity, 1)
  assert.deepEqual(payload.orderPayload.config, { region: 'hk', cpu: 2 })
}

// 2. 旧载荷只写 createdAt：savedAt 回退读 createdAt，TTL 不能整条失效
{
  const payload = normalizePendingWebsiteCheckout({
    createdAt: 1700000000000,
    orderPayload: baseOrder,
  })
  assert.ok(payload)
  assert.equal(payload.savedAt, 1700000000000, '缺 savedAt 时必须以 createdAt 为 TTL 基准')
}

// 3. 数量上限对齐后端：StoreRequest 是 quantity in 1..1
{
  assert.equal(
    normalizePendingWebsiteCheckout({ orderPayload: { ...baseOrder, quantity: 2 } }),
    null,
    'quantity !== 1 必须在前端拦下'
  )
  assert.equal(normalizePendingWebsiteCheckout({ orderPayload: { ...baseOrder, quantity: 0 } }), null)
}

// 4. 报价凭证上限对齐后端 max:120
{
  const longToken = 'quote_' + 'a'.repeat(200)
  const payload = normalizePendingWebsiteCheckout({
    orderPayload: { ...baseOrder, quote_token: longToken },
  })
  assert.ok(payload)
  assert.equal(payload.orderPayload.quote_token.length, 120, '超长凭证应截断到后端上限 120')
}

// 5. 非法输入一律 null：缺 product_id / billing_cycle 超长 / 非对象
{
  assert.equal(normalizePendingWebsiteCheckout(null), null)
  assert.equal(normalizePendingWebsiteCheckout({ orderPayload: 'x' }), null)
  assert.equal(normalizePendingWebsiteCheckout({ orderPayload: { ...baseOrder, product_id: 0 } }), null)
  assert.equal(
    normalizePendingWebsiteCheckout({ orderPayload: { ...baseOrder, billing_cycle: 'x'.repeat(33) } }),
    null
  )
}

// 6. config 只允许一层标量：嵌套对象与超长键被丢弃
{
  const payload = normalizePendingWebsiteCheckout({
    orderPayload: {
      ...baseOrder,
      config: { ok: 'yes', nested: { a: 1 }, ['k'.repeat(65)]: 1 },
    },
  })
  assert.ok(payload)
  assert.deepEqual(payload.orderPayload.config, { ok: 'yes' })
}

// 6b. 截断边界：source 限 64、idempotencyKey 限 128（外部可构造超长串，不能让它们直通建单）
{
  const payload = normalizePendingWebsiteCheckout({
    source: 's'.repeat(100),
    idempotencyKey: 'k'.repeat(200),
    orderPayload: baseOrder,
  })
  assert.ok(payload)
  assert.equal(payload.source.length, 64, 'source 应截断到 64')
  assert.equal(payload.idempotencyKey.length, 128, 'idempotencyKey 应截断到 128')
}

// 6c. billing_cycle 纯空白串必须判非法：trim 后为空，若放行就等于把空周期送进建单
{
  assert.equal(
    normalizePendingWebsiteCheckout({ orderPayload: { ...baseOrder, billing_cycle: '   ' } }),
    null,
    'billing_cycle 纯空白应判 null'
  )
  assert.equal(
    normalizePendingWebsiteCheckout({ orderPayload: { ...baseOrder, billing_cycle: '\t\n' } }),
    null,
    'billing_cycle 仅制表/换行同样应判 null'
  )
}

// 6d. config 值只认标量：undefined、数组、函数、对象一律丢弃，其余保留
{
  const payload = normalizePendingWebsiteCheckout({
    orderPayload: {
      ...baseOrder,
      config: { keep: 'yes', num: 3, bool: false, nil: null, undef: undefined, arr: [1], fn: () => 1 },
    },
  })
  assert.ok(payload)
  assert.deepEqual(
    payload.orderPayload.config,
    { keep: 'yes', num: 3, bool: false, nil: null },
    '只保留标量，undefined/数组/函数必须被丢弃'
  )
}

// 6e. config 全非法时整个 config 键都不出现，而不是留一个空对象
{
  const payload = normalizePendingWebsiteCheckout({
    orderPayload: { ...baseOrder, config: { arr: [1], nested: { a: 1 } } },
  })
  assert.ok(payload)
  assert.equal('config' in payload.orderPayload, false, 'config 全非法时应整键省略')
}

// 7. encode/decode 往返；垃圾 base64 返回 null 而不是抛错
{
  const encoded = encodePendingWebsiteCheckout({ orderPayload: baseOrder })
  assert.ok(encoded.length > 0)
  const decoded = decodePendingWebsiteCheckout(encoded)
  assert.ok(decoded)
  assert.equal(decoded.orderPayload.product_id, 12)
  assert.equal(decodePendingWebsiteCheckout('not-a-valid-payload'), null)
  assert.equal(decodePendingWebsiteCheckout(''), null)
}

// 8. savePendingWebsiteCheckout 只为缺失时间戳的载荷补打 savedAt
//    （控制台落地时会再保存一次，无条件覆盖会把 TTL 基准漂移到落地时刻）
{
  const store = new Map()
  globalThis.window = {
    sessionStorage: {
      getItem: (key) => (store.has(key) ? store.get(key) : null),
      setItem: (key, value) => store.set(key, String(value)),
      removeItem: (key) => store.delete(key),
    },
  }

  savePendingWebsiteCheckout({ orderPayload: baseOrder })
  const first = JSON.parse(store.get('website_pending_checkout'))
  assert.equal(typeof first.savedAt, 'number', '首次保存必须补打 savedAt')

  savePendingWebsiteCheckout({ ...normalizePendingWebsiteCheckout(first), savedAt: first.savedAt })
  const second = JSON.parse(store.get('website_pending_checkout'))
  assert.equal(second.savedAt, first.savedAt, '已有 savedAt 时必须原样保留')

  // 9. 超过 TTL 的载荷在读取时清除并返回 null
  store.set(
    'website_pending_checkout',
    JSON.stringify({ ...first, savedAt: Date.now() - CHECKOUT_TTL_MS - 1000 })
  )
  assert.equal(getPendingWebsiteCheckout(), null)
  assert.equal(store.has('website_pending_checkout'), false, '过期载荷应被移除')

  // 9b. 字符串形式的时间戳同样要参与 TTL：save 认 Number.isFinite(Number(...))，
  //     读侧若只认 typeof === 'number'，外部写进来的 "1700000000000" 就永不过期
  store.set(
    'website_pending_checkout',
    JSON.stringify({
      ...first,
      savedAt: String(Date.now() - CHECKOUT_TTL_MS - 1000),
    })
  )
  assert.equal(getPendingWebsiteCheckout(), null, '字符串时间戳超期同样应判过期')
  assert.equal(store.has('website_pending_checkout'), false, '字符串时间戳超期也应被移除')

  // 10. 未过期载荷正常返回，clear 后为空
  savePendingWebsiteCheckout({ orderPayload: baseOrder })
  assert.ok(getPendingWebsiteCheckout())
  clearPendingWebsiteCheckout()
  assert.equal(getPendingWebsiteCheckout(), null)

  delete globalThis.window
}

console.log('website checkout tests passed')
