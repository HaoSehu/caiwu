import assert from 'node:assert/strict'

import { deriveInitials } from '../runtime/branding.ts'
import { createSessionDriver } from '../runtime/session.ts'

assert.equal(deriveInitials('创欧云'), '创欧')
assert.equal(deriveInitials('Cloud Union'), 'CU')

const storage = new Map()
const cookies = new Map()
const cookieWrites = []

const documentMock = {}
Object.defineProperty(documentMock, 'cookie', {
  get() {
    return Array.from(cookies.entries())
      .map(([key, value]) => `${key}=${value}`)
      .join('; ')
  },
  set(value) {
    cookieWrites.push(String(value))
    const [pair, ...parts] = String(value).split('; ')
    const [rawKey, rawValue = ''] = pair.split('=')
    const key = decodeURIComponent(rawKey)
    const maxAgePart = parts.find((part) => part.startsWith('max-age='))
    if (maxAgePart === 'max-age=0') {
      cookies.delete(key)
      return
    }
    cookies.set(key, decodeURIComponent(rawValue))
  },
})

global.window = {
  location: { protocol: 'http:' },
  localStorage: {
    getItem(key) {
      return storage.get(key) || null
    },
    setItem(key, value) {
      storage.set(key, String(value))
    },
    removeItem(key) {
      storage.delete(key)
    },
  },
}
global.document = documentMock

const driver = createSessionDriver({
  tokenKey: 'token',
  lastActiveKey: 'active_at',
  idleTimeoutMs: 60_000,
})

driver.setToken('abc')
assert.equal(driver.getToken(), 'abc')
assert.equal(driver.isLoggedIn(), true)
// http 下不写 Secure；SameSite 必须是 Lax —— Strict 会让外部站（控制台/邮件/
// 搜索结果）点进来时的首个顶层 GET 请求不带凭证，表现为「进站显示未登录」。
assert.equal(cookieWrites.at(-1).includes('Secure'), false)
assert.equal(cookieWrites.at(-1).includes('SameSite=Lax'), true)
assert.equal(cookieWrites.at(-1).includes('SameSite=Strict'), false)
driver.removeToken()
assert.equal(driver.getToken(), null)

global.window.location.protocol = 'https:'
driver.setToken('secure-token')
assert.equal(cookieWrites.at(-1).includes('Secure'), true)
assert.equal(cookieWrites.at(-1).includes('SameSite=Lax'), true)

// 删除指令必须与写入姿态一致带上 Secure，否则浏览器允许同名 Cookie 并存
driver.removeToken()
assert.equal(cookieWrites.at(-1).includes('max-age=0'), true)
assert.equal(cookieWrites.at(-1).includes('Secure'), true)
// 删除指令的 SameSite 必须与写入一致，否则同名 Cookie 可能删不掉
assert.equal(cookieWrites.at(-1).includes('SameSite=Lax'), true)

delete global.window
delete global.document

console.log('shared runtime tests passed')
