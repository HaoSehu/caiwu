import { useWebsiteProductsCatalog } from './useWebsiteProductsCatalog'
import { useWebsiteProductsPurchase } from './useWebsiteProductsPurchase'

export function useWebsiteProductsPage() {
  const purchase = useWebsiteProductsPurchase()
  const catalog = useWebsiteProductsCatalog({
    onProductSelect: purchase.loadSelectedProduct,
    onResetSelection: purchase.resetSelection,
  })

  return {
    ...catalog,
    ...purchase,
  }
}
