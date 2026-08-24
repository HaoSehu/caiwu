import { watch } from "vue";
import { useWebsiteProductsCatalog } from "./useWebsiteProductsCatalog";
import { useWebsiteProductsPurchase } from "./useWebsiteProductsPurchase";

export function useWebsiteProductsPage() {
  const purchase = useWebsiteProductsPurchase();
  const catalog = useWebsiteProductsCatalog({
    onProductSelect: purchase.loadSelectedProduct,
    onResetSelection: purchase.resetSelection,
  });

  // 首屏关键路径（目录/详情/库存/报价）不参与预取争抢：将列表其余商品详情
  // 的预取推迟到浏览器空闲时段执行，避免与 stock/quote 等阻塞性请求抢带宽。
  let idlePrefetchId = 0;

  function cancelIdlePrefetch() {
    if (typeof window !== "undefined" && idlePrefetchId) {
      window.cancelIdleCallback?.(idlePrefetchId);
      idlePrefetchId = 0;
    }
  }

  function scheduleIdlePrefetch(products) {
    cancelIdlePrefetch();
    if (
      typeof window === "undefined" ||
      typeof window.requestIdleCallback !== "function"
    ) {
      return;
    }

    idlePrefetchId = window.requestIdleCallback(
      () => {
        idlePrefetchId = 0;
        purchase.prefetchProductDetails(products);
      },
      // 本地/慢速环境下关键路径（详情→库存→报价）优先，预取推迟到价格可用之后
      { timeout: 4000 },
    );
  }

  watch(
    catalog.visibleProducts,
    (products) => {
      scheduleIdlePrefetch(products);
    },
    { immediate: true },
  );

  return {
    ...catalog,
    ...purchase,
  };
}
