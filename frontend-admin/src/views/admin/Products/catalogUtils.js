import {
  billingCycleLabelMap,
  billingCycleOptions,
  configPricingCycles,
  interfaceTypeOptions,
  hostingPanelOptionCatalog,
  rangeConfigOptionTypes,
  supportedBillingCycleSet,
} from './catalogOptions'
import { PROVIDER_KEYS } from '@/constants/providerTypes'

let configOptionUid = 1

export function nextConfigOptionUid() {
  const current = configOptionUid
  configOptionUid += 1

  return `config-option-${current}`
}

export function roundPrice(value) {
  return Math.round(Number(value || 0) * 100) / 100
}

export function parseSupplierAmount(value) {
  if (value === null || value === undefined || value === '') {
    return null
  }

  const numericValue = Number(value)
  if (!Number.isFinite(numericValue)) {
    return null
  }

  return roundPrice(numericValue)
}

export function createEmptySubItemPricing() {
  return configPricingCycles.reduce((result, cycle) => {
    result[cycle.value] = ''
    return result
  }, {})
}

export function createDefaultPricing() {
  return billingCycleOptions.reduce((result, item) => {
    result[item.value] = null
    return result
  }, {})
}

export function resolveMonthlyAmountFromPricing(pricing = {}, cycles = billingCycleOptions) {
  for (const cycle of cycles) {
    const amount = parseSupplierAmount(pricing?.[cycle.value])
    if (amount === null) {
      continue
    }

    return roundPrice(amount / Number(cycle.months || 1))
  }

  return null
}

export function buildDerivedNumericPricingFromMonthly(monthlyAmount) {
  return billingCycleOptions.reduce((result, cycle) => {
    result[cycle.value] = roundPrice(monthlyAmount * Number(cycle.months || 1))
    return result
  }, {})
}

export function buildDerivedStringPricingFromMonthly(monthlyAmount) {
  return billingCycleOptions.reduce((result, cycle) => {
    result[cycle.value] = String(roundPrice(monthlyAmount * Number(cycle.months || 1)))
    return result
  }, createEmptySubItemPricing())
}

export function normalizeProductPricingFromSource(pricing = {}) {
  const monthlyAmount = resolveMonthlyAmountFromPricing(pricing)
  if (monthlyAmount === null) {
    return createDefaultPricing()
  }

  return buildDerivedNumericPricingFromMonthly(monthlyAmount)
}

export function normalizeConfigPricingFromSource(pricing = {}) {
  const monthlyAmount = resolveMonthlyAmountFromPricing(pricing)
  if (monthlyAmount === null) {
    return createEmptySubItemPricing()
  }

  return buildDerivedStringPricingFromMonthly(monthlyAmount)
}

export function syncConfigPricingFieldsFromMonthly(pricing = {}) {
  Object.assign(pricing, normalizeConfigPricingFromSource(pricing))
}

export function buildConfigPricingPayload(pricing = {}) {
  const monthlyAmount = resolveMonthlyAmountFromPricing(pricing)
  if (monthlyAmount === null) {
    return {}
  }

  return buildDerivedNumericPricingFromMonthly(monthlyAmount)
}

export function billingCycleLabel(value) {
  return billingCycleLabelMap[value] || ''
}

export function compactDateTime(value) {
  const normalizedValue = String(value || '').trim()
  if (!normalizedValue) {
    return '-'
  }

  if (normalizedValue.length >= 16) {
    return normalizedValue.slice(5, 16)
  }

  return normalizedValue
}

export function sanitizePricing(pricing = {}) {
  return normalizeProductPricingFromSource(Object.entries(pricing || {}).reduce((result, [cycle, amount]) => {
    if (!supportedBillingCycleSet.has(cycle)) {
      return result
    }

    result[cycle] = amount
    return result
  }, {}))
}

export function createDefaultCategoryForm(selectedProductType = '') {
  return {
    product_type: selectedProductType,
    parent_id: null,
    name: '',
    slogan: '',
    sort_order: 0,
    is_visible: 1,
  }
}

export function createDefaultProductForm(selectedProductType = 'other') {
  return {
    category_id: null,
    product_type: selectedProductType,
    remark: '',
    pricing: createDefaultPricing(),
    setup_fee: 0,
    stock: -1,
    status: 1,
    sort_order: 0,
    provision_module: '',
    auto_setup: 0,
    supplier_id: null,
    supplier_product_id: null,
    config_options: [],
    purchase_requires: {
      require_verification: false,
      require_phone: false,
      provision_hostname: {
        mode: 'system',
        value: '',
        length: 12,
      },
    },
  }
}

export function createDefaultConfigOptionForm() {
  return {
    uid: '',
    source: PROVIDER_KEYS.HOSTING_PANEL_API,
    spec_key: '',
    field: '',
    name: '',
    option_mode: 'select',
    show_advanced: false,
    description: '',
    suffix_text: '',
    required: false,
    default_value: '',
    sort_order: 0,
    hidden: false,
    allow_upgrade: false,
    allow_promo_code: true,
    sub_items: [],
    qty_minimum: 0,
    qty_maximum: 100,
    qty_step: 1,
    range_pricing: [],
    extra: {},
    parameter: '',
  }
}

export function normalizeProviderSource(value) {
  return String(value || PROVIDER_KEYS.HOSTING_PANEL_API).trim() || PROVIDER_KEYS.HOSTING_PANEL_API
}

export function interfaceTypeLabel(value) {
  return interfaceTypeOptions.find((item) => item.value === value)?.label || value || '-'
}

export function formatSupplierOptionLabel(supplier) {
  const name = String(supplier?.name || '').trim()
  const typeLabel = interfaceTypeLabel(supplier?.interface_type)

  if (!name) {
    return typeLabel
  }

  return `${name} / ${typeLabel}`
}

export function normalizeFlag(value, fallback = false) {
  if (value === null || value === undefined || value === '') {
    return fallback
  }

  if (typeof value === 'boolean') {
    return value
  }

  if (typeof value === 'number') {
    return value === 1
  }

  const normalizedValue = String(value).trim().toLowerCase()
  if (['1', 'true', 'yes', 'on'].includes(normalizedValue)) {
    return true
  }

  if (['0', 'false', 'no', 'off'].includes(normalizedValue)) {
    return false
  }

  return Boolean(value)
}

export function resolveHostingPanelOptionSpec(value) {
  const normalizedValue = String(value || '').trim()
  if (!normalizedValue) {
    return null
  }

  return hostingPanelOptionCatalog.find((item) => (
    item.field === normalizedValue || item.field.toLowerCase() === normalizedValue.toLowerCase()
  )) || null
}

export function formatConfigOptionParameter(value) {
  if (value === null || value === undefined || value === '') {
    return ''
  }

  if (Array.isArray(value)) {
    if (value.length === 0) {
      return ''
    }

    if (value.every((item) => ['string', 'number'].includes(typeof item))) {
      return value.map((item) => String(item)).join('\n')
    }
  }

  try {
    return JSON.stringify(value, null, 2)
  } catch {
    return ''
  }
}

export function buildConfigOptionField(value, index) {
  const normalizedValue = String(value || '')
    .trim()
    .toLowerCase()
    .replace(/[^\w]+/g, '_')
    .replace(/^_+|_+$/g, '')

  return normalizedValue || `config_${index + 1}`
}

export function normalizeConfigOptionType(value) {
  if (value === null || value === undefined || value === '') {
    return null
  }

  const normalized = Number(value)
  return Number.isFinite(normalized) ? normalized : null
}

export function resolveConfigOptionMode(source = {}) {
  const optionType = normalizeConfigOptionType(source.option_type ?? source.type)
  if (optionType !== null) {
    return rangeConfigOptionTypes.has(optionType) ? 'range' : 'select'
  }

  const explicitMode = String(source.option_mode || '').trim().toLowerCase()
  if (explicitMode === 'range' || explicitMode === 'select') {
    return explicitMode
  }

  if (Array.isArray(source.range_pricing) && source.range_pricing.length > 0) {
    return 'range'
  }

  if (
    Array.isArray(source.sub)
    && source.sub.some((item) => (
      item
      && typeof item === 'object'
      && !Array.isArray(item)
      && (Number(item.qty_minimum ?? 0) > 0 || Number(item.qty_maximum ?? 0) > 0)
    ))
  ) {
    return 'range'
  }

  if (
    Number(source.qty_minimum ?? 0) > 0
    || Number(source.qty_maximum ?? 0) > 0
    || Number(source.qty_step ?? source.qty_stage ?? 1) > 1
  ) {
    return 'range'
  }

  return 'select'
}

export function splitConfigOptionNameParts(value) {
  const normalizedValue = String(value || '').trim()
  if (!normalizedValue.includes('|')) {
    return { field: '', name: normalizedValue }
  }

  const [field, name] = normalizedValue.split('|')

  return {
    field: String(field || '').trim(),
    name: String(name || '').trim(),
  }
}

export function extractConfigOptionExtra(source = {}) {
  return Object.entries(source).reduce((result, [key, value]) => {
    if ([
      'field',
      'uid',
      'extra',
      'key',
      'code',
      'name',
      'label',
      'title',
      'type',
      'option_type',
      'input_type',
      'sort_order',
      'sort',
      'hidden',
      'is_hidden',
      'allow_upgrade',
      'allowUpgrade',
      'upgrade',
      'can_upgrade',
      'allow_promo_code',
      'apply_promo_code',
      'allow_coupon',
      'apply_coupon',
      'coupon_enabled',
      'option_mode',
      'parameter',
      'param_value',
      'param',
      'value',
      'description',
      'remark',
      'required',
      'is_required',
      'default_value',
      'default',
      'spec_key',
      'source',
      'options',
      'values',
      'items',
      'sub',
      'sub_items',
      'range_pricing',
      'qty_minimum',
      'qty_maximum',
      'qty_step',
      'qty_stage',
    ].includes(key)) {
      return result
    }

    result[key] = value
    return result
  }, {})
}

export function createConfigOptionRecordFromSource(source, index = 0) {
  const spec = resolveHostingPanelOptionSpec(source.spec_key ?? source.field ?? source.key ?? source.code ?? '')
  const nameParts = splitConfigOptionNameParts(source.name ?? source.label ?? source.title ?? '')
  const rawField = String(source.field ?? source.key ?? source.code ?? spec?.field ?? nameParts.field ?? '').trim()
  const rawName = String(source.label ?? source.title ?? nameParts.name ?? source.name ?? spec?.name ?? '').trim()
  const optionMode = resolveConfigOptionMode(source)
  const optionType = normalizeConfigOptionType(source.option_type ?? source.type)
  const sub = Array.isArray(source.sub) ? source.sub.filter((item) => item && typeof item === 'object') : []
  const qtyStep = Number(source.qty_step ?? source.qty_stage ?? 1)

  return {
    uid: source.uid || nextConfigOptionUid(),
    source: normalizeProviderSource(source.source),
    spec_key: spec?.field || '',
    field: rawField || buildConfigOptionField(rawName, index),
    name: rawName || `配置项${index + 1}`,
    option_type: optionType,
    option_mode: optionMode,
    parameter: String(
      source.parameter
      ?? source.param_value
      ?? source.param
      ?? source.value
      ?? formatConfigOptionParameter(source.options ?? source.values ?? source.items ?? ''),
    ).trim(),
    description: String(source.description ?? source.remark ?? spec?.description ?? '').trim(),
    required: normalizeFlag(source.required ?? source.is_required, spec?.required ?? false),
    default_value: String(source.default_value ?? source.default ?? spec?.defaultValue ?? '').trim(),
    sort_order: Number(source.sort_order ?? source.sort ?? index + 1) || 0,
    hidden: normalizeFlag(source.hidden ?? source.is_hidden, false),
    allow_upgrade: normalizeFlag(source.allow_upgrade ?? source.allowUpgrade ?? source.upgrade ?? source.can_upgrade, false),
    allow_promo_code: normalizeFlag(
      source.allow_promo_code ?? source.apply_promo_code ?? source.allow_coupon ?? source.apply_coupon ?? source.coupon_enabled,
      true,
    ),
    sub,
    sub_items: Array.isArray(source.sub_items) ? source.sub_items : [],
    range_pricing: Array.isArray(source.range_pricing) ? source.range_pricing : [],
    qty_minimum: optionMode === 'range' ? Number(source.qty_minimum ?? 0) : undefined,
    qty_maximum: optionMode === 'range' ? Number(source.qty_maximum ?? 0) : undefined,
    qty_step: optionMode === 'range' ? (Number.isFinite(qtyStep) && qtyStep > 0 ? qtyStep : 1) : undefined,
    extra: extractConfigOptionExtra(source),
  }
}

export function normalizeConfigOptions(configOptions) {
  if (Array.isArray(configOptions)) {
    return configOptions.map((item, index) => createConfigOptionRecordFromSource(
      item && typeof item === 'object' && !Array.isArray(item)
        ? item
        : { name: String(item || '') },
      index,
    ))
  }

  if (configOptions && typeof configOptions === 'object') {
    return Object.entries(configOptions).map(([key, value], index) => createConfigOptionRecordFromSource({
      field: buildConfigOptionField(key, index),
      name: String(key || `配置项${index + 1}`),
      parameter: formatConfigOptionParameter(value),
    }, index))
  }

  return []
}

export function serializeConfigOptions(configOptions = []) {
  return configOptions.reduce((result, item, index) => {
    const field = String(item.field || '').trim()
    const name = String(item.name || '').trim()
    const optionType = normalizeConfigOptionType(item.option_type ?? item.extra?.option_type)
    const sourceSub = Array.isArray(item.sub)
      ? item.sub
      : (Array.isArray(item.extra?.sub) ? item.extra.sub : [])

    if (!field) {
      throw new Error(`第 ${index + 1} 个配置项缺少标识`)
    }

    if (!name) {
      throw new Error(`第 ${index + 1} 个配置项缺少名称`)
    }

    const payload = {
      ...item.extra,
      spec_key: item.spec_key || field,
      source: normalizeProviderSource(item.source),
      field,
      name,
      ...(optionType !== null ? { option_type: optionType } : {}),
      option_mode: item.option_mode || 'select',
      parameter: String(item.parameter || '').trim(),
      description: String(item.description || '').trim(),
      suffix_text: String(item.suffix_text || '').trim(),
      required: item.required ? 1 : 0,
      default_value: String(item.default_value || '').trim(),
      sort_order: Math.max(Number(item.sort_order || 0), 0),
      hidden: item.hidden ? 1 : 0,
      allow_upgrade: item.allow_upgrade ? 1 : 0,
      allow_promo_code: item.allow_promo_code ? 1 : 0,
      sub: sourceSub,
    }

    if (item.option_mode === 'range') {
      payload.qty_minimum = Number(item.qty_minimum ?? 0)
      payload.qty_maximum = Number(item.qty_maximum ?? 0)
      payload.qty_stage = Number(item.qty_step ?? 1)
    }

    result.push(payload)
    return result
  }, [])
}

export function buildCategoryTreeNode(category, level = 1) {
  return {
    ...category,
    category_id: Number(category.category_id ?? category.id ?? 0),
    parent_category_id: Number(category.parent_category_id ?? category.parent_id ?? 0) || null,
    tree_key: `category-${category.id}`,
    level,
    products_count: Number(category.products_count || 0),
    children_count: Number(category.children_count ?? category.children?.length ?? 0),
    children: Array.isArray(category.children)
      ? category.children.map((child) => buildCategoryTreeNode(child, level + 1))
      : [],
  }
}

export function buildAssignableCategoryOptions(categories, ancestors = []) {
  return categories.flatMap((category) => {
    const currentNames = [...ancestors, String(category.name || '').trim()].filter(Boolean)
    const children = Array.isArray(category.children) ? category.children : []
    const currentOption = ancestors.length > 0
      ? [{
          id: Number(category.id),
          category_id: Number(category.category_id ?? category.id),
          label: currentNames.join(' / '),
        }]
      : []

    return [
      ...currentOption,
      ...buildAssignableCategoryOptions(children, currentNames),
    ]
  })
}

export function filterCategoryTree(categories, keyword) {
  if (!keyword) {
    return categories
  }

  return categories.reduce((result, category) => {
    const name = String(category.name || '').toLowerCase()
    const children = Array.isArray(category.children) ? category.children : []

    if (name.includes(keyword)) {
      result.push(category)
      return result
    }

    const matchedChildren = children.filter((child) => String(child.name || '').toLowerCase().includes(keyword))
    if (matchedChildren.length) {
      result.push({
        ...category,
        children: matchedChildren,
      })
    }

    return result
  }, [])
}
