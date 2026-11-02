import { onBeforeUnmount, watch } from "vue";
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

  // 切换产品目录（类型）要先拉 product-groups（实测 700ms）才能列出地区，
  // 而首屏期间这么发会与详情/库存/报价抢带宽（实测被拖到 2s+）。
  // 因此等首屏报价拿到（quoteToken 就绪，关键路径已结束）后再预取其余类型的目录。
  const stopQuoteWatch = watch(
    () => purchase.quoteToken.value,
    (token) => {
      if (!token) return;
      stopQuoteWatch();
      catalog.prefetchRootGroups();
    },
  );

  // 离开页面时必须撤销排队中的空闲预取：回调里持有 purchase.prefetchProductDetails，
  // 组件卸载后仍会发出详情请求并写已销毁的响应式状态。
  onBeforeUnmount(cancelIdlePrefetch);

  return {
    ...catalog,
    ...purchase,
  };
}
