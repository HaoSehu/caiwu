import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'

const source = readFileSync(new URL('../src/views/admin/Users/detail/index.vue', import.meta.url), 'utf8')
const dialogMatch = source.match(/<el-dialog\s+v-model="serviceUpstreamDialogVisible"[\s\S]*?<\/el-dialog>/)

assert.ok(dialogMatch, 'service upstream dialog should exist')

const dialogBlock = dialogMatch[0]

assert.match(dialogBlock, /label="上游接口"/)
assert.match(dialogBlock, /label="上游实例 ID"/)
assert.doesNotMatch(dialogBlock, /上游产品 ID/)
assert.doesNotMatch(dialogBlock, /supplier_product_id/)

console.log('serviceUpstreamDialogMarkup tests passed')
