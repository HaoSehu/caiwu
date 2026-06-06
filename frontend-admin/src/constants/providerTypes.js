export const PROVIDER_KEYS = {
  HOSTING_PANEL_API: 'hosting_panel_api',
  MOFANG_FINANCE_API: 'mofang_finance_api',
}

export const providerTypeLabels = {
  [PROVIDER_KEYS.HOSTING_PANEL_API]: '主机面板接口',
}

const defaultProviderTypeOptions = [
  { label: providerTypeLabels[PROVIDER_KEYS.HOSTING_PANEL_API], value: PROVIDER_KEYS.HOSTING_PANEL_API },
]

export const providerTypeOptions = [...defaultProviderTypeOptions]

export function configureProviderTypes(options = []) {
  const normalizedOptions = Array.isArray(options)
    ? options
      .map((item) => ({
        value: normalizeProviderType(item?.value),
        label: String(item?.label || '').trim(),
      }))
      .filter((item) => item.value && item.label)
    : []

  providerTypeOptions.splice(
    0,
    providerTypeOptions.length,
    ...(normalizedOptions.length > 0 ? normalizedOptions : defaultProviderTypeOptions)
  )

  Object.keys(providerTypeLabels).forEach((key) => {
    if (key !== PROVIDER_KEYS.HOSTING_PANEL_API) {
      delete providerTypeLabels[key]
    }
  })

  providerTypeOptions.forEach((item) => {
    providerTypeLabels[item.value] = item.label
  })
}

export function resetProviderTypes() {
  configureProviderTypes(defaultProviderTypeOptions)
}

export function normalizeProviderType(value) {
  const normalized = String(value || '').trim()

  return normalized
}

export function providerTypeLabel(value) {
  const normalized = String(value || '').trim()

  return providerTypeLabels[normalized]
    || providerTypeLabels[normalizeProviderType(normalized)]
    || normalized
    || '-'
}
