import assert from 'node:assert/strict'

import {
  applyAddServiceProductDetailToForm,
  canAddServiceProductLinkUpstream,
  normalizeAddServiceProductId,
  resolveAddServiceBillingOptions,
} from '../src/views/admin/Users/detail/composables/addServiceProductSelection.js'

function testNormalizesOnlyProductLeafIds() {
  assert.equal(normalizeAddServiceProductId(127), 127)
  assert.equal(normalizeAddServiceProductId('127'), 127)
  assert.equal(normalizeAddServiceProductId('category:40'), null)
  assert.equal(normalizeAddServiceProductId('4vcpu4gib'), null)
  assert.equal(normalizeAddServiceProductId(''), null)
  assert.equal(normalizeAddServiceProductId(null), null)
}

function testBuildsBillingOptionsFromProductDetailPricing() {
  const options = resolveAddServiceBillingOptions({
    pricing: {
      monthly: '39.9',
      quarterly: '0',
      annually: 399,
    },
  }, (value) => ({ monthly: '月付', annually: '年付' })[value] || value)

  assert.deepEqual(options, [
    { value: 'monthly', label: '月付 · ¥39.90', amount: 39.9 },
    { value: 'annually', label: '年付 · ¥399.00', amount: 399 },
  ])
}

function testAppliesProductDetailToForm() {
  const form = {
    source_type: 'upstream',
    name: '',
    billing_cycle: '',
    amount: null,
  }

  const productDetail = {
    id: 127,
    cpu_memory_display: '4 vCPU 4G',
    supplier_id: 0,
    supplier_product_id: 582,
    pricing: {
      monthly: '39.9',
    },
  }

  applyAddServiceProductDetailToForm(form, productDetail, (value) => ({ monthly: '月付' })[value] || value)

  assert.equal(form.name, '4vcpu4gib')
  assert.equal(form.billing_cycle, 'monthly')
  assert.equal(form.amount, 39.9)
  assert.equal(form.source_type, 'manual')
  assert.equal(canAddServiceProductLinkUpstream(productDetail), false)
}

testNormalizesOnlyProductLeafIds()
testBuildsBillingOptionsFromProductDetailPricing()
testAppliesProductDetailToForm()

console.log('addServiceProductSelection tests passed')
