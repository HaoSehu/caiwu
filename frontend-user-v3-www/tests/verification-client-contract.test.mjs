import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'
import {
  getEmailBindingFormError,
  getPhoneBindingFormError,
  getVerificationFormError,
  IDENTITY_CARD_PATTERN,
  resolveVerificationQrUrl,
} from '../src/utils/verification.js'

const __dirname = dirname(fileURLToPath(import.meta.url))
const verificationPagePath = resolve(__dirname, '../src/pages/client/verification/index.vue')
const verificationPageSource = readFileSync(verificationPagePath, 'utf-8')

assert.equal(
  resolveVerificationQrUrl({
    proxy_url: ' https://www.example.com/api/client/verification/scan?certify_id=abc ',
    url: 'https://realname.example.com/certify',
  }),
  'https://realname.example.com/certify',
  'verification QR should prefer the provider URL instead of the backend proxy URL',
)

assert.equal(
  resolveVerificationQrUrl({
    url: ' https://realname.example.com/certify ',
  }),
  'https://realname.example.com/certify',
  'verification QR should use the provider URL',
)

assert.equal(
  resolveVerificationQrUrl({
    proxy_url: ' https://www.example.com/api/client/verification/scan?certify_id=abc ',
  }),
  '',
  'verification QR should not use backend proxy URLs when provider URL is absent',
)

assert.equal(IDENTITY_CARD_PATTERN.test('11010519491231002X'), true)
assert.equal(IDENTITY_CARD_PATTERN.test('abcdefghijk123456'), false)

assert.equal(
  getVerificationFormError({ realName: '李维佳', idCard: '610723200804157111' }),
  '',
  'valid verification form should be submittable without relying on a parent el-form ref',
)

assert.equal(
  getVerificationFormError({ realName: '', idCard: '610723200804157111' }),
  '请输入真实姓名',
)

assert.equal(
  getVerificationFormError({ realName: '李维佳', idCard: 'invalid' }),
  '身份证号格式不正确',
)

assert.equal(
  getPhoneBindingFormError({ phone: '13800138000', code: '123456' }),
  '',
  'valid phone binding form should be submittable without relying on a parent el-form ref',
)

assert.equal(
  getPhoneBindingFormError({ phone: '13800138000', code: '' }),
  '请输入短信验证码',
)

assert.equal(
  getPhoneBindingFormError({ phone: '123', code: '123456' }),
  '请输入正确的手机号',
)

assert.equal(
  getEmailBindingFormError({ email: 'test@example.com', code: '123456' }),
  '',
  'valid email binding form should be submittable without relying on a parent el-form ref',
)

assert.equal(
  getEmailBindingFormError({ email: 'bad-email', code: '123456' }),
  '请输入有效邮箱',
)

assert.equal(
  getEmailBindingFormError({ email: 'test@example.com', code: '123' }),
  '请输入6位邮箱验证码',
)

assert.match(
  verificationPageSource,
  /@click="openVerificationEntry"/,
  'standalone verification page should expose a clickable verification entry',
)

assert.match(
  verificationPageSource,
  /verification_callback/,
  'standalone verification page should handle provider callback query params',
)
