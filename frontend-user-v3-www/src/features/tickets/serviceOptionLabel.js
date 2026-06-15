import { SERVICE_STATUS_MAP, resolveElTagType } from '@caiwu/shared/statusConfig'

function resolveTicketServiceName(option = {}) {
  const rawName = String(option?.name || '').trim()
  if (!rawName) {
    return ''
  }

  const segments = rawName
    .split('/')
    .map((item) => item.trim())
    .filter(Boolean)

  if (segments.length >= 2) {
    return segments[segments.length - 1]
  }

  return rawName
}

export function resolveTicketServiceStatusMeta(option = {}) {
  const status = Number(option?.status)
  const explicitLabel = String(option?.status_label || '').trim()
  const config = SERVICE_STATUS_MAP[status]

  if (config) {
    return {
      label: explicitLabel || String(config.label || '--').trim() || '--',
      tagType: config.tagType || 'info',
      elTagType: resolveElTagType(config.tagType || 'info'),
      dot: true,
    }
  }

  if (!explicitLabel) {
    return {
      label: '--',
      tagType: 'info',
      elTagType: 'info',
      dot: true,
    }
  }

  return {
    label: explicitLabel,
    tagType: 'info',
    elTagType: 'info',
    dot: true,
  }
}

export function resolveTicketServiceStatusLabel(option = {}) {
  return resolveTicketServiceStatusMeta(option).label
}

export function formatTicketServiceOptionLabel(option = {}, includeStatus = true) {
  const id = Number(option?.id || 0)
  const name = resolveTicketServiceName(option)

  if (id <= 0 || !name) {
    return '--'
  }

  if (!includeStatus) {
    return `#${id}-${name}`
  }

  return `#${id}-${name}-${resolveTicketServiceStatusLabel(option)}`
}
