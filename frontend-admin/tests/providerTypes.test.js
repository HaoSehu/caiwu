import assert from 'node:assert/strict'

import {
  configureProviderTypes,
  providerTypeLabel,
  providerTypeOptions,
  resetProviderTypes,
} from '../src/constants/providerTypes.js'

function testUsesBackendProviderTypeOptions() {
  resetProviderTypes()

  configureProviderTypes([
    { value: 'hosting_panel_api', label: '主机面板接口' },
    { value: 'mofang_finance_api', label: '魔方财务接口' },
  ])

  assert.equal(providerTypeLabel('mofang_finance_api'), '魔方财务接口')
  assert.deepEqual(providerTypeOptions, [
    { value: 'hosting_panel_api', label: '主机面板接口' },
    { value: 'mofang_finance_api', label: '魔方财务接口' },
  ])
}

function testFallsBackToRawProviderKeyWhenBackendOptionsAreMissing() {
  resetProviderTypes()

  assert.equal(providerTypeLabel('mofang_finance_api'), 'mofang_finance_api')
  assert.deepEqual(providerTypeOptions, [
    { value: 'hosting_panel_api', label: '主机面板接口' },
  ])
}

testUsesBackendProviderTypeOptions()
testFallsBackToRawProviderKeyWhenBackendOptionsAreMissing()
resetProviderTypes()

console.log('providerTypes tests passed')
