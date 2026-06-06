import { resolveAddServiceProductLabel } from './addServiceProductLabel.js'

export function buildAddServiceProductTree(rawTree = [], productList = []) {
  const productsByGroupId = productList.reduce((map, product) => {
    const groupId = Number(product?.product_group_id || product?.group_id || 0)
    if (groupId <= 0) {
      return map
    }

    if (!map.has(groupId)) {
      map.set(groupId, [])
    }
    map.get(groupId).push(product)

    return map
  }, new Map())

  const typeMap = new Map()

  rawTree.forEach((root) => {
    const rootChildren = Array.isArray(root?.children) ? root.children : []
    if (!rootChildren.length) {
      return
    }

    const rootNode = buildCategoryNode(root, productsByGroupId)
    if (!rootNode) {
      return
    }

    const typeLabel = String(root.product_type_label || root.product_type || root.name || '').trim()
    if (!typeLabel) {
      return
    }

    if (!typeMap.has(typeLabel)) {
      typeMap.set(typeLabel, [])
    }
    typeMap.get(typeLabel).push(rootNode)
  })

  return Array.from(typeMap.entries())
    .map(([typeLabel, children]) => ({
      value: typeLabel,
      label: typeLabel,
      disabled: true,
      children,
    }))
    .filter((item) => item.children.length > 0)
}

function buildCategoryNode(category, productsByGroupId) {
  const childCategories = Array.isArray(category?.children) ? category.children : []
  const children = [
    ...childCategories
      .map((child) => buildCategoryNode(child, productsByGroupId))
      .filter(Boolean),
    ...buildProductNodes(resolveCategoryProducts(category, productsByGroupId)),
  ]

  if (!children.length) {
    return null
  }

  return {
    value: `category:${category.id}`,
    label: String(category.name || '').trim(),
    disabled: true,
    children,
  }
}

function resolveCategoryProducts(category, productsByGroupId) {
  const categoryIds = [
    category?.id,
    category?.category_id,
    category?.group_id,
    category?.legacy_group_id,
  ].map((id) => Number(id || 0)).filter((id) => id > 0)

  const seen = new Set()
  return Array.from(new Set(categoryIds))
    .flatMap((id) => productsByGroupId.get(id) || [])
    .filter((product) => {
      const pid = Number(product?.id || 0)
      if (pid <= 0 || seen.has(pid)) return false
      seen.add(pid)
      return true
    })
}

function buildProductNodes(products) {
  return products.map((product) => ({
    value: Number(product.id || 0),
    label: resolveAddServiceProductLabel(product),
    isProduct: true,
    leaf: true,
  })).filter((item) => item.value > 0 && item.label)
}
