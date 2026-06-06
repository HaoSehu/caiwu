import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'

import {
  configureProviderTypes,
  resetProviderTypes,
} from '../src/constants/providerTypes.js'
import {
  buildServiceUpstreamSupplierOptions,
} from '../src/views/admin/Users/detail/composables/serviceUpstreamSuppliers.js'

const detailComposableSource = readFileSync(
  new URL('../src/views/admin/Users/detail/composables/useUserDetail.js', import.meta.url),
  'utf8'
)
const suppliersPageSource = readFileSync(
  new URL('../src/views/admin/Products/SuppliersPage.vue', import.meta.url),
  'utf8'
)

assert.doesNotMatch(
  detailComposableSource,
  /MANAGEABLE_PROVIDER_KEYS/,
  'service upstream supplier options should not be limited by a hardcoded provider whitelist'
)
assert.doesNotMatch(
  suppliersPageSource,
  /ZJMF_/,
  'generic supplier form should not expose Mofang-specific credential placeholders'
)

resetProviderTypes()
configureProviderTypes([
  { value: 'hosting_panel_api', label: '主机面板接口' },
  { value: 'third_panel_api', label: '第三方面板接口' },
])

const supplierOptions = buildServiceUpstreamSupplierOptions([
  { id: 1, name: '默认面板', interface_type: 'hosting_panel_api' },
  { id: 2, name: '第三方接口', interface_type: 'third_panel_api' },
  { id: 0, name: '无效接口', interface_type: 'third_panel_api' },
])

assert.deepEqual(supplierOptions, [
  {
    id: 1,
    name: '默认面板',
    interface_type: 'hosting_panel_api',
    label: '默认面板 · 主机面板接口',
  },
  {
    id: 2,
    name: '第三方接口',
    interface_type: 'third_panel_api',
    label: '第三方接口 · 第三方面板接口',
  },
])

resetProviderTypes()

console.log('serviceUpstreamSupplierModule tests passed')
