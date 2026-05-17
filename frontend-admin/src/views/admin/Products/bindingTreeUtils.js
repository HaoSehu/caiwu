export const PRODUCT_BINDING_CASCADER_PROPS = {
  value: 'id',
  label: 'label',
  children: 'children',
  multiple: true,
  emitPath: false,
  checkStrictly: false,
  checkOnClickNode: false,
  checkOnClickLeaf: true,
  showPrefix: true,
}

export function filterProductBindingNode(node, keyword) {
  const text = [
    node?.data?.display_name,
    node?.data?.cpu_memory_display,
    node?.data?.label,
    node?.data?.category_full_name,
    node?.data?.group_full_name,
  ]
    .filter(Boolean)
    .join(' ')
    .toLowerCase()
  const normalizedKeyword = String(keyword || '').trim().toLowerCase()

  if (!normalizedKeyword) {
    return true
  }

  return text.includes(normalizedKeyword)
}

function normalizeBindingPrice(price = {}) {
  return {
    cycle: String(price?.cycle || ''),
    amount: String(price?.amount || '0.00'),
  }
}

function createBindingRecord(source = {}) {
  const productId = Number(source?.product_id || source?.id || 0)

  if (productId <= 0) {
    return null
  }

  return {
    product_id: productId,
    display_name: String(source?.display_name || source?.label || '').trim(),
    cpu_memory_display: String(source?.cpu_memory_display || '').trim(),
    category_full_name: String(source?.category_full_name || source?.group_full_name || '').trim(),
    primary_price: normalizeBindingPrice(source?.primary_price),
    status: Number(source?.status || 0) === 1 ? 1 : 0,
  }
}

export function normalizeProductBindings(bindings = []) {
  if (!Array.isArray(bindings)) {
    return []
  }

  const seen = new Set()

  return bindings.reduce((result, item) => {
    const record = createBindingRecord(item)

    if (!record || seen.has(record.product_id)) {
      return result
    }

    seen.add(record.product_id)
    result.push(record)
    return result
  }, [])
}

export function buildProductBindingMap(nodes = [], map = new Map()) {
  if (!Array.isArray(nodes)) {
    return map
  }

  nodes.forEach((node) => {
    if (!node || typeof node !== 'object') {
      return
    }

    const isProductNode = String(node.node_type || '') === 'product' || node.leaf === true
    const record = isProductNode ? createBindingRecord(node) : null

    if (record) {
      map.set(record.product_id, record)
    }

    if (Array.isArray(node.children) && node.children.length) {
      buildProductBindingMap(node.children, map)
    }
  })

  return map
}

export function resolveProductBindings(selectedIds = [], productMap = new Map(), fallbackMap = new Map()) {
  if (!Array.isArray(selectedIds)) {
    return []
  }

  const seen = new Set()

  return selectedIds.reduce((result, selectedId) => {
    const productId = Number(selectedId || 0)

    if (productId <= 0 || seen.has(productId)) {
      return result
    }

    seen.add(productId)

    const record = productMap.get(productId) || fallbackMap.get(productId)
    if (record) {
      result.push(record)
    }

    return result
  }, [])
}
