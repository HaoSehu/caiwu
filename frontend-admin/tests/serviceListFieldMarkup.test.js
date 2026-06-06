import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

const __dirname = dirname(fileURLToPath(import.meta.url))
const viewPath = resolve(__dirname, '../src/views/admin/Orders/ServicesPage.vue')
const source = readFileSync(viewPath, 'utf-8')

function assertIncludesField(label, pattern) {
  assert.match(source, pattern, `service list should display ${label}`)
}

assertIncludesField('service id', /服务ID/)
assertIncludesField('user id', /用户ID/)
assertIncludesField('invoice id', /账单ID/)
assertIncludesField('invoice number', /账单号/)
assertIncludesField('custom hostname', /自定义主机名/)
assertIncludesField('requested hostname', /主机名/)
assertIncludesField('upstream host id', /上游ID/)
assertIncludesField('host ip', /主机IP/)
assertIncludesField('host username', /登录用户/)

console.log('serviceListFieldMarkup tests passed')
