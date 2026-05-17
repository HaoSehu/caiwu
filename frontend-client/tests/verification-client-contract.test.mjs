import assert from 'node:assert/strict'
import {
  IDENTITY_CARD_PATTERN,
  resolveVerificationQrUrl,
} from '../src/utils/verification.js'

assert.equal(
  resolveVerificationQrUrl({
    proxy_url: ' https://www.example.com/api/client/verification/scan?certify_id=abc ',
    url: 'https://realname.example.com/certify',
  }),
  'https://www.example.com/api/client/verification/scan?certify_id=abc',
  'verification QR should prefer the backend proxy URL',
)

assert.equal(
  resolveVerificationQrUrl({
    proxy_url: '   ',
    url: ' https://realname.example.com/certify ',
  }),
  'https://realname.example.com/certify',
  'verification QR should fall back to the provider URL',
)

assert.equal(IDENTITY_CARD_PATTERN.test('11010519491231002X'), true)
assert.equal(IDENTITY_CARD_PATTERN.test('abcdefghijk123456'), false)
