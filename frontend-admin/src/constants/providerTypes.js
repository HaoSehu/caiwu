export const PROVIDER_KEYS = {
  HOSTING_PANEL_API: 'hosting_panel_api',
  MOFANG_FINANCE_API: 'mofang_finance_api',
}

export const providerTypeLabels = {
  [PROVIDER_KEYS.HOSTING_PANEL_API]: '主机面板接口',
  [PROVIDER_KEYS.MOFANG_FINANCE_API]: '魔方财务接口',
}

export const providerTypeOptions = [
  { label: providerTypeLabels[PROVIDER_KEYS.HOSTING_PANEL_API], value: PROVIDER_KEYS.HOSTING_PANEL_API },
  { label: providerTypeLabels[PROVIDER_KEYS.MOFANG_FINANCE_API], value: PROVIDER_KEYS.MOFANG_FINANCE_API },
]

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
