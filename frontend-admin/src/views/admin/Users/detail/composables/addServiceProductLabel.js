export function resolveAddServiceProductLabel(product) {
  if (!product || typeof product !== 'object') {
    return ''
  }

  const productId = Number(product.id || 0)
  const fallback = productId > 0 ? `未配置规格 #${productId}` : ''
  const specLabel = formatCpuMemoryDisplay(product.cpu_memory_display)
  const combinedSpecLabel = formatCpuMemoryDisplay(product.combined_display_name)
  const candidates = [
    specLabel,
    combinedSpecLabel,
    product.display_name,
    product.product_spec_display,
    product.product_display_name,
    product.combined_display_name,
    product.name,
    fallback,
  ]

  return candidates
    .map((value) => String(value || '').trim())
    .find((value) => value && !isUpstreamModuleSlug(value)) || ''
}

function formatCpuMemoryDisplay(value) {
  const source = String(value || '').trim()
  if (!source) {
    return ''
  }

  const normalized = source.toLowerCase()
  const cpuMatch = normalized.match(/(\d+(?:\.\d+)?)\s*(?:v?cpu|核|c)/i)
  const memoryMatch = normalized.match(/(\d+(?:\.\d+)?)\s*(tib|tb|t|gib|gb|g|mib|mb|m)/i)

  if (!cpuMatch || !memoryMatch) {
    return ''
  }

  const cpuValue = normalizeModelDisplayNumber(cpuMatch[1])
  const memoryValue = normalizeModelDisplayNumber(memoryMatch[1])
  const memoryUnit = normalizeModelMemoryUnit(memoryMatch[2])

  if (!cpuValue || !memoryValue || !memoryUnit) {
    return ''
  }

  return `${cpuValue}vcpu${memoryValue}${memoryUnit}`
}

function normalizeModelDisplayNumber(value) {
  const source = String(value || '').trim()
  if (!source) {
    return ''
  }

  const number = Number(value)
  if (!Number.isFinite(number) || number <= 0) {
    return ''
  }

  return String(number)
}

function normalizeModelMemoryUnit(value) {
  const source = String(value || '').trim().toLowerCase()
  if (!source) {
    return ''
  }

  if (source.startsWith('t')) {
    return 'tib'
  }

  if (source.startsWith('g')) {
    return 'gib'
  }

  if (source.startsWith('m')) {
    return 'mib'
  }

  return ''
}

function isUpstreamModuleSlug(value) {
  return ['gscs'].includes(String(value || '').trim().toLowerCase())
}
