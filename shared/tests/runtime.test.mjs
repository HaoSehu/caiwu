import assert from 'node:assert/strict'

import { deriveInitials } from '../runtime/branding.ts'
import { createSessionDriver } from '../runtime/session.ts'

assert.equal(deriveInitials('创欧云'), '创欧')
assert.equal(deriveInitials('Cloud Union'), 'CU')

const storage = new Map()
global.window = {
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

const driver = createSessionDriver({
  tokenKey: 'token',
  lastActiveKey: 'active_at',
  idleTimeoutMs: 60_000,
})

driver.setToken('abc')
assert.equal(driver.getToken(), 'abc')
assert.equal(driver.isLoggedIn(), true)
driver.removeToken()
assert.equal(driver.getToken(), null)

delete global.window

console.log('shared runtime tests passed')
