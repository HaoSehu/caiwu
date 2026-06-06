import { resolveAddServiceProductLabel } from './addServiceProductLabel.js'

export function normalizeAddServiceProductId(value) {
  const productId = Number(value)
  return Number.isInteger(productId) && productId > 0 ? productId : null
}

export function resolveAddServiceBillingOptions(productDetail, resolveBillingCycleLabel) {
  const pricing = productDetail && typeof productDetail.pricing === 'object' && !Array.isArray(productDetail.pricing)
    ? productDetail.pricing
    : {}

  return Object.entries(pricing)
    .filter(([, amount]) => toAddServiceNumber(amount) > 0)
    .map(([value, amount]) => ({
      value,
      label: `${resolveBillingCycleLabel(value)} · ¥${toAddServiceNumber(amount).toFixed(2)}`,
      amount: toAddServiceNumber(amount),
    }))
}

export function canAddServiceProductLinkUpstream(productDetail) {
  return Number(productDetail?.supplier_id || 0) > 0
    && Number(productDetail?.supplier_product_id || 0) > 0
}

export function applyAddServiceProductDetailToForm(form, productDetail, resolveBillingCycleLabel) {
  const billingOptions = resolveAddServiceBillingOptions(productDetail, resolveBillingCycleLabel)
  const firstCycle = billingOptions[0]
  const canLinkUpstream = canAddServiceProductLinkUpstream(productDetail)

  form.name = resolveAddServiceProductLabel(productDetail) || form.name
  form.billing_cycle = firstCycle?.value || ''
  form.amount = firstCycle ? firstCycle.amount : null

  if (form.source_type === 'upstream' && !canLinkUpstream) {
    form.source_type = 'manual'
  }
}

function toAddServiceNumber(value) {
  const number = Number.parseFloat(value ?? 0)
  return Number.isFinite(number) ? number : 0
}
