/**
 * Auth Token 工具单元测试
 * 运行: node ./tests/auth.test.mjs
 */
import assert from 'node:assert/strict'

import { createSessionDriver } from '../../shared/runtime/session.ts'

// 模拟 localStorage
const storage = new Map()
globalThis.window = {
  localStorage: {
    getItem(key) { return storage.get(key) || null },
    setItem(key, value) { storage.set(key, String(value)) },
    removeItem(key) { storage.delete(key) },
  },
}

const driver = createSessionDriver({
  tokenKey: 'admin_token',
  lastActiveKey: 'admin_last_active_at',
  idleTimeoutMs: 3 * 60 * 60 * 1000,
})

// 1. 初始状态：无 token
assert.equal(driver.getToken(), null, '初始状态应该无 token')
assert.equal(driver.isLoggedIn(), false, '初始状态未登录')

// 2. 写 token 后能正确读取
driver.setToken('test-token-abc')
assert.equal(driver.getToken(), 'test-token-abc', 'setToken 后应该能读取')
assert.equal(driver.isLoggedIn(), true, '有 token 应该是登录态')

// 3. 退出登录后 token 被移除
driver.removeToken()
assert.equal(driver.getToken(), null, 'removeToken 后 token 应为 null')
assert.equal(driver.isLoggedIn(), false, 'removeToken 后应为未登录')

// 4. 重复 removeToken 不抛错
assert.doesNotThrow(() => driver.removeToken(), '重复 removeToken 不应抛错')

// 5. 写空 token 不应生效
driver.setToken('')
assert.equal(driver.getToken(), null, '空字符串 token 不应生效')

// 6. isSessionExpired 初始行为
driver.setToken('valid-token')
assert.equal(driver.isSessionExpired(), false, '刚设置的 token 不过期')

// 7. touchSessionActivity 不抛错
assert.doesNotThrow(() => driver.touchSessionActivity(), 'touchSessionActivity 不应抛错')

// 8. readStorageItem
assert.equal(typeof driver.readStorageItem('admin_token'), 'string')

// 9. getUserType 不存在（admin 端已移除）
// auth 工具不再有 getUserType——验证 auth.js 不再导出
// 直接验证 session 驱动工作正常即可

delete globalThis.window

console.log('auth token tests passed')
