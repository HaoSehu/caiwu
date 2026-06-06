import assert from 'node:assert/strict'

import { resolveAddServiceProductLabel } from '../src/views/admin/Users/detail/composables/addServiceProductLabel.js'

function testPrefersCpuMemoryConfigurationOverRawName() {
  const label = resolveAddServiceProductLabel({
    id: 42,
    name: 'gscs',
    display_name: 'gscs',
    cpu_memory_display: '2 vCPU 4G',
  })

  assert.equal(label, '2vcpu4gib')
}

function testFallsBackToCombinedDisplayNameConfiguration() {
  const label = resolveAddServiceProductLabel({
    id: 43,
    name: 'gscs',
    display_name: 'gscs',
    combined_display_name: 'gscs-4vcpu8gib',
  })

  assert.equal(label, '4vcpu8gib')
}

function testFallsBackToDisplayNameWhenNoConfigurationExists() {
  const label = resolveAddServiceProductLabel({
    id: 44,
    display_name: '高防套餐',
  })

  assert.equal(label, '高防套餐')
}

function testFallsBackToUnconfiguredPlaceholder() {
  const label = resolveAddServiceProductLabel({
    id: 45,
    name: 'gscs',
  })

  assert.equal(label, '未配置规格 #45')
}

testPrefersCpuMemoryConfigurationOverRawName()
testFallsBackToCombinedDisplayNameConfiguration()
testFallsBackToDisplayNameWhenNoConfigurationExists()
testFallsBackToUnconfiguredPlaceholder()

console.log('addServiceProductLabel tests passed')
