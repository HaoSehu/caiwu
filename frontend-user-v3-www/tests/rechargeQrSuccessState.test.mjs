import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

const __dirname = dirname(fileURLToPath(import.meta.url))
const rechargePageSource = readFileSync(resolve(__dirname, '../src/pages/client/recharge/index.vue'), 'utf-8')
const rechargeComposableSource = readFileSync(resolve(__dirname, '../src/composables/useRecharge.js'), 'utf-8')

assert.match(
  rechargeComposableSource,
  /const rechargePaid = ref\(false\)/,
  'recharge composable should expose a paid state',
)

assert.match(
  rechargeComposableSource,
  /rechargePaid\.value = true/,
  'recharge composable should mark paid state after polling success',
)

assert.match(
  rechargeComposableSource,
  /function startAutoPolling\(interval = 2000\)/,
  'recharge auto polling should use a short default interval',
)

assert.match(
  rechargeComposableSource,
  /void pollRechargeStatus\(\{ silentPending: true \}\)/,
  'recharge auto polling should query once immediately after QR generation',
)

assert.match(
  rechargePageSource,
  /'is-paid': rechargePaid/,
  'recharge QR frame should receive a paid class for success animation',
)

assert.match(
  rechargePageSource,
  /class="qrcode-success"/,
  'recharge page should render a QR success overlay',
)

assert.match(
  rechargePageSource,
  /充值成功，余额已刷新/,
  'recharge success copy should be visible in the QR panel',
)
