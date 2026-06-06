import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

const __dirname = dirname(fileURLToPath(import.meta.url))
const viewPath = resolve(__dirname, '../src/views/admin/Users/detail/index.vue')
const source = readFileSync(viewPath, 'utf-8')
const productSelectorBlock = source.match(/<el-form-item label="选择商品"[\s\S]*?<\/el-form-item>/)?.[0] || ''

function testProductSelectorUsesCollapsedTreeSelect() {
  assert.match(productSelectorBlock, /<el-tree-select\b/)
  assert.doesNotMatch(productSelectorBlock, /\bdefault-expand-all\b/)
}

testProductSelectorUsesCollapsedTreeSelect()

console.log('addServiceProductTreeSelectMarkup tests passed')
