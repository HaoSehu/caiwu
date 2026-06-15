export const CONSOLE_PROFILE_KEYS = {
  CLOUD: 'cloud',
  NAT: 'nat',
}

export const CONSOLE_PROFILE_MAP = {
  [CONSOLE_PROFILE_KEYS.CLOUD]: {
    key: CONSOLE_PROFILE_KEYS.CLOUD,
    label: '云服务器控制台',
    modules: ['overview', 'monitor', 'security', 'power', 'logs', 'vnc'],
    permissions: {
      natForwarding: false,
    },
  },
  [CONSOLE_PROFILE_KEYS.NAT]: {
    key: CONSOLE_PROFILE_KEYS.NAT,
    label: 'NAT/云电脑控制台',
    modules: ['overview', 'monitor', 'security', 'nat', 'power', 'logs', 'vnc'],
    permissions: {
      natForwarding: true,
    },
  },
}

const NAT_CONSOLE_TYPE_VALUES = ['nat', 'cloud_desktop', 'cloud_pc', 'cloudpc', 'nat_console']

export function normalizeConsoleType(value) {
  return String(value || '').trim().toLowerCase().replace(/[\s-]+/g, '_')
}

export function resolveConsoleProfileKey(detail = {}) {
  const catalogType = normalizeConsoleType(detail?.product?.catalog_type || detail?.product?.type || '')

  if (NAT_CONSOLE_TYPE_VALUES.includes(catalogType)) {
    return CONSOLE_PROFILE_KEYS.NAT
  }

  if (String(detail?.console_mode || '').trim().toLowerCase() === CONSOLE_PROFILE_KEYS.NAT || detail?.is_nat_console === true) {
    return CONSOLE_PROFILE_KEYS.NAT
  }

  return CONSOLE_PROFILE_KEYS.CLOUD
}

export function getConsoleProfile(key = CONSOLE_PROFILE_KEYS.CLOUD) {
  const profile = CONSOLE_PROFILE_MAP[key] || CONSOLE_PROFILE_MAP[CONSOLE_PROFILE_KEYS.CLOUD]

  return {
    ...profile,
    modules: Array.isArray(profile.modules) ? [...profile.modules] : [],
    permissions: { ...(profile.permissions || {}) },
  }
}
