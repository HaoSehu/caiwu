const PURCHASE_REQUIREMENT_DEFINITIONS = Object.freeze({
  require_verification: {
    key: 'require_verification',
    label: '实名认证',
    badge: '需实名认证',
    description: '购买前需完成实名认证',
    unmetMessage: '该商品购买前需先完成实名认证。',
    route: '/client/verification',
    actionLabel: '去实名认证',
  },
  require_phone: {
    key: 'require_phone',
    label: '手机号绑定',
    badge: '需绑定手机号',
    description: '购买前需先绑定手机号',
    unmetMessage: '该商品购买前需先绑定手机号。',
    route: '/client/profile',
    actionLabel: '去绑定手机号',
  },
})

function isRequirementEnabled(product, key) {
  return Boolean(product?.purchase_requires?.[key])
}

export function resolvePurchaseRequirementList(product) {
  return Object.values(PURCHASE_REQUIREMENT_DEFINITIONS).filter((item) => isRequirementEnabled(product, item.key))
}

export function resolvePurchaseRequirementSummary(product) {
  return resolvePurchaseRequirementList(product)
    .map((item) => item.description)
    .join('、')
}
