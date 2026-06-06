import { providerTypeLabel } from '../../../../../constants/providerTypes.js'

export function buildServiceUpstreamSupplierOptions(items = []) {
  return (Array.isArray(items) ? items : [])
    .map((item) => {
      const id = Number(item?.id || 0)
      const name = item?.name || `接口 #${item?.id}`
      const interfaceType = String(item?.interface_type || '')

      return {
        id,
        name,
        interface_type: interfaceType,
        label: `${name} · ${providerTypeLabel(interfaceType)}`,
      }
    })
    .filter((item) => item.id > 0)
}
