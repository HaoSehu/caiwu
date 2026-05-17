import assert from 'node:assert/strict'

import { buildClientLoginAsUrl } from '../src/views/admin/Users/detail/composables/loginAsUrl.js'

function testUsesFrontendUrlRedirectFromBackend() {
  const url = buildClientLoginAsUrl('abc 123', {
    redirectUrl: 'https://sw7111.top/client/dashboard',
    currentLocation: 'https://admin.sw7111.top/admin/users/1',
  })

  assert.equal(url, 'https://sw7111.top/client/login-as?code=abc%20123')
}

function testUsesBackendRedirectUrlEvenWhenItDiffersFromAdminOrigin() {
  const url = buildClientLoginAsUrl('login-code', {
    redirectUrl: 'https://www.sw7111.top/client/dashboard',
    currentLocation: 'https://admin.sw7111.top/admin/users/1',
  })

  assert.equal(url, 'https://www.sw7111.top/client/login-as?code=login-code')
}

function testRequiresBackendFrontendUrlRedirect() {
  assert.throws(
    () => buildClientLoginAsUrl('login-code', {
      currentLocation: 'https://admin.sw7111.top/admin/users/1',
    }),
    /FRONTEND_URL/
  )
}

testUsesFrontendUrlRedirectFromBackend()
testUsesBackendRedirectUrlEvenWhenItDiffersFromAdminOrigin()
testRequiresBackendFrontendUrlRedirect()

console.log('loginAsUrl tests passed')
