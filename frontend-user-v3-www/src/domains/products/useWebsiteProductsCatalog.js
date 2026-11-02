import { computed, onBeforeUnmount, onMounted, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import siteApi, {
  getCachedCatalog,
  invalidateCatalogCache,
  setCachedCatalog,
} from "@/api/site";
import { getPendingWebsiteCouponId } from "@/utils/websiteCoupon";
import {
  buildWebsiteProductPath,
  hasWebsiteProductRouteParams,
  readWebsiteProductRouteParams,
} from "@/utils/productRoute";

import { MessagePlugin } from "tdesign-vue-next/es/message/plugin";

import { useMobileBreakpoint } from "@/composables/useMobileBreakpoint";

// 进行中的目录请求去重（site.js 的 catalogCache 负责结果缓存）
const catalogPendingMap = new Map();
// 已访问类型的根分组缓存：切换类型时本地过滤，避免重复请求 productGroups
const rootGroupsByType = new Map();
// 可用区（三级分组）的先行缓存：catalog 聚合接口要把该地区全部商品一起返回才能渲染，
// 而用户最先感知的是「可用区」标签是否出现。children 接口只查分组本身（服务端有
// 15 分钟缓存，实测约 250ms），可先把标签渲染出来，商品随后由 catalog 补齐。
const childGroupsCache = new Map();

function groupMatchesType(group, typeValue) {
  if (!typeValue) {
    return true;
  }

  return [group?.first_product_group_code]
    .map((value) => String(value || "").trim())
    .includes(typeValue);
}

function filterGroupsByType(groups = [], typeValue = "") {
  if (!Array.isArray(groups)) {
    return [];
  }

  return groups.filter((group) => groupMatchesType(group, typeValue));
}

function catalogBelongsToGroup(catalog, groupId) {
  return (
    Number(catalog?.effective_product_group_id || 0) === Number(groupId || 0)
  );
}

export function useWebsiteProductsCatalog({
  onProductSelect,
  onResetSelection,
}) {
  const route = useRoute();
  const router = useRouter();
  const pageLoading = ref(false);
  const pageError = ref(false);
  const sidebarCollapsed = ref(false);
  // 断点判定与商品详情、站点头部共用同一份 matchMedia 查询；
  // listen 传 false 是因为本 composable 已在自己的 onMounted 里注册 resize 监听，
  // 由 resize 调 updateMobileState 同步（与改造前的事件模型一致）。
  const {
    isMobile,
    updateMobileState,
  } = useMobileBreakpoint({ listen: false });
  const mobileProductDrawer = ref(false);
  const mobilePendingProductId = ref(0);

  const productTypes = ref([]);
  const rootGroups = ref([]);
  const childGroups = ref([]);
  const productsByGroup = ref({});

  const activeTypeValue = ref("");
  const activeGroupId = ref(0);
  const activeChildId = ref(0);
  const selectedProductId = ref(0);
  const mobileRegionDrawer = ref(false);
  const tempGroupId = ref(0);
  const tempChildGroups = ref([]);

  let routeSyncSuspendCount = 0;
  let groupToken = 0;
  let rootToken = 0;
  let groupAbortController = null;

  const activeGroup = computed(
    () =>
      rootGroups.value.find((group) => group.id === activeGroupId.value) ||
      null,
  );
  const activeGroupName = computed(() => activeGroup.value?.name || "");
  const activeChildName = computed(() => {
    if (activeChildId.value <= 0) return "";
    return (
      childGroups.value.find((g) => g.id === activeChildId.value)?.name || ""
    );
  });
  const activeCatalogCategoryId = computed(() => {
    if (activeChildId.value > 0) {
      return Number(
        childGroups.value.find((group) => group.id === activeChildId.value)
          ?.effective_product_group_id || 0,
      );
    }

    return Number(activeGroup.value?.effective_product_group_id || 0);
  });
  const shouldAutoSelectProduct = computed(
    () => getPendingWebsiteCouponId() <= 0,
  );
  const visibleProducts = computed(() => {
    const categoryId = activeCatalogCategoryId.value;
    return productsByGroup.value[categoryId] || [];
  });

  function getChildCategoryId(childId) {
    return Number(
      childGroups.value.find((group) => group.id === childId)
        ?.effective_product_group_id || 0,
    );
  }

  function hasDisplayPrice(product) {
    const pricingEntries = Array.isArray(product?.pricing_entries)
      ? product.pricing_entries
      : [];
    if (pricingEntries.some((item) => Number(item?.amount || 0) > 0)) {
      return true;
    }

    return Number(product?.primary_price || 0) > 0;
  }

  function shouldSyncRoute(options = {}) {
    return options.syncRoute !== false;
  }

  function withSuspendedRouteSync(task) {
    routeSyncSuspendCount += 1;

    return Promise.resolve(task()).finally(() => {
      routeSyncSuspendCount = Math.max(routeSyncSuspendCount - 1, 0);
    });
  }

  async function syncSelectionRoute() {
    if (routeSyncSuspendCount > 0) {
      return;
    }

    const targetPath = buildWebsiteProductPath({
      typeId: activeTypeValue.value,
      groupId: activeGroupId.value,
      childGroupId: activeChildId.value,
      productId: selectedProductId.value,
    });

    if (route.path === targetPath) {
      return;
    }

    await router.replace({
      path: targetPath,
      query: route.query,
    });
  }

  function resetSelectedProduct(options = {}) {
    selectedProductId.value = 0;
    onResetSelection?.();

    if (shouldSyncRoute(options)) {
      void syncSelectionRoute();
    }
  }

  function selectProduct(id, options = {}) {
    if (selectedProductId.value === id) {
      onProductSelect?.(id, { refreshStockOnly: true });

      if (shouldSyncRoute(options)) {
        void syncSelectionRoute();
      }

      return;
    }

    selectedProductId.value = id;
    onProductSelect?.(id);

    if (shouldSyncRoute(options)) {
      void syncSelectionRoute();
    }
  }

  function syncDefaultProduct(products = [], options = {}) {
    const targetProductId = Number(options.targetProductId || 0);

    if (!products.length) {
      resetSelectedProduct({ syncRoute: shouldSyncRoute(options) });
      return;
    }

    if (targetProductId > 0) {
      const matchedProduct = products.find(
        (item) => item.id === targetProductId,
      );
      if (matchedProduct) {
        selectProduct(matchedProduct.id, {
          syncRoute: shouldSyncRoute(options),
        });
        return;
      }
    }

    if (!shouldAutoSelectProduct.value) {
      resetSelectedProduct({ syncRoute: shouldSyncRoute(options) });
      return;
    }

    selectProduct(products[0].id, { syncRoute: shouldSyncRoute(options) });
  }

  function openMobileRegionDrawer() {
    if (!rootGroups.value.length) {
      // 地区目录还没加载出来时按钮点了毫无反应，用户无法判断是没数据还是坏了。
      MessagePlugin.warning("地区列表尚未加载完成，请稍后重试");
      return;
    }

    tempGroupId.value = activeGroupId.value;
    tempChildGroups.value = [...childGroups.value];
    mobileRegionDrawer.value = true;
  }

  async function selectTempGroup(id) {
    if (tempGroupId.value === id) {
      return;
    }

    tempGroupId.value = id;
    tempChildGroups.value = [];

    const cached = getCachedCatalog(id);
    if (cached) {
      tempChildGroups.value = cached.children || [];
      return;
    }

    try {
      const data = await siteApi
        .productGroupCatalog(id)
        .then((res) => res.data);
      setCachedCatalog(id, data);
      tempChildGroups.value = data.children || [];
    } catch (error) {
      if (error?.code === "ERR_CANCELED" || error?.name === "CanceledError") {
        return;
      }
      // 不再静默：此前失败后可用区列永久空白，用户既不知道失败也没有重试理由。
      // 保留用户刚选的 tempGroupId，仅提示并可再次点选重试。
      tempChildGroups.value = [];
      MessagePlugin.warning("可用区加载失败，请重新选择地区");
    }
  }

  function handleRegionChange(groupId) {
    return selectTempGroup(groupId);
  }

  async function confirmRegionSelection(groupId, childId) {
    const targetGroupId = groupId || tempGroupId.value;
    try {
      await withSuspendedRouteSync(async () => {
        await switchGroup(targetGroupId, {
          syncRoute: false,
          targetChildId:
            childId ||
            tempChildGroups.value.find((g) => g.id === (childId || 0))?.id ||
            0,
          targetProductId: selectedProductId.value,
        });
      });
    } catch {
      // 失败时保持抽屉打开：此前先关抽屉再请求，失败后抽屉已消失、页面无任何提示，
      // 用户以为「已切换」但数据仍是旧的。现在保留抽屉与失败提示，用户可重试或换地区。
      mobileRegionDrawer.value = true;
      MessagePlugin.warning("切换地区失败，请重试");
      return;
    }

    mobileRegionDrawer.value = false;
    await syncSelectionRoute();
  }

  async function openMobileProductDrawer() {
    if (
      activeGroupId.value > 0 &&
      visibleProducts.value.length &&
      visibleProducts.value.every((product) => !hasDisplayPrice(product))
    ) {
      invalidateCatalogCache(activeGroupId.value);
      try {
        await loadGroupPayload(activeGroupId.value, {
          syncRoute: false,
          targetChildId: activeChildId.value,
          targetProductId: selectedProductId.value,
        });
      } catch {
        // loadGroupPayload 在非取消场景会 throw，而本函数的调用方
        // products/index.vue 的 openMobileProductGroupDrawer 用 void 丢弃 Promise，
        // 不在此消化会变成 unhandled rejection：用户点「规格」既没有抽屉也没有提示。
        // 补价只是让列表带上价格，失败不该阻断选规格，因此提示后继续打开抽屉。
        MessagePlugin.warning("商品列表加载失败，请重试");
      }
    }

    mobilePendingProductId.value =
      selectedProductId.value || visibleProducts.value[0]?.id || 0;
    mobileProductDrawer.value = true;
  }

  function confirmMobileProductSelection() {
    if (mobilePendingProductId.value) {
      selectProduct(mobilePendingProductId.value, { syncRoute: true });
    }

    mobileProductDrawer.value = false;
  }

  async function switchGroup(id, options = {}) {
    activeGroupId.value = id;
    activeChildId.value = 0;
    resetSelectedProduct({ syncRoute: false });
    mobileProductDrawer.value = false;
    // 可用区标签先行：命中缓存同步渲染；未命中则与 catalog 并行请求，
    // 只用于尽快把「可用区」这一行的按钮画出来，商品与选中仍由 catalog 决定。
    primeChildGroups(id);
    await loadGroupPayload(id, options);
  }

  function primeChildGroups(groupId) {
    const id = Number(groupId || 0);
    if (!id) {
      return;
    }

    const cached = childGroupsCache.get(id);
    if (cached) {
      childGroups.value = cached;
      return;
    }

    // catalog 已在缓存或已发出时，它本身就会带回 children；再发 children 请求
    // 纯属重复网络。只有 catalog 还要现拉时才值得为「先画出可用区」多付一跳。
    if (getCachedCatalog(id) || catalogPendingMap.has(id)) {
      return;
    }

    void siteApi
      .productGroupChildren(id)
      .then((res) => {
        const list = res?.data?.list || res?.data || [];
        if (!Array.isArray(list) || !list.length) {
          return;
        }

        childGroupsCache.set(id, list);
        // 用户可能已切到别处：只在当前仍停在该分组时回写，避免把旧分组的
        // 可用区覆盖到新分组上。
        if (activeGroupId.value === id) {
          childGroups.value = list;
        }
      })
      .catch(() => {
        // 失败不影响主路径：catalog 仍会带回 children 并覆盖这里。
      });
  }

  function switchChild(id, options = {}) {
    activeChildId.value = id;
    resetSelectedProduct({ syncRoute: false });
    mobileProductDrawer.value = false;

    const products = productsByGroup.value[getChildCategoryId(id)] || [];
    syncDefaultProduct(products, options);
  }

  async function switchType(value, options = {}) {
    activeTypeValue.value = value;
    activeGroupId.value = 0;
    activeChildId.value = 0;
    resetSelectedProduct({ syncRoute: false });
    productsByGroup.value = {};
    childGroups.value = [];
    mobileProductDrawer.value = false;

    await loadRootGroups(options);
  }

  async function loadRootGroups(options = {}) {
    const token = ++rootToken;
    pageLoading.value = true;
    pageError.value = false;

    try {
      const typeValue = activeTypeValue.value || "";
      // 优先复用已加载过的类型分组，切换回已访问类型时不重复请求 productGroups
      let groups = rootGroupsByType.get(typeValue);
      if (!groups) {
        const res = await siteApi.productGroups({
          first_product_group_code: typeValue || undefined,
        });
        groups = filterGroupsByType(res.data.list || [], typeValue);
        rootGroupsByType.set(typeValue, groups);
      }

      // 已被更新的切换取代：不写状态，新切换会接管
      if (token !== rootToken) {
        return;
      }

      rootGroups.value = groups;

      if (rootGroups.value.length) {
        const targetGroupId = Number(options.targetGroupId || 0);
        const matchedGroup = rootGroups.value.find(
          (group) => group.id === targetGroupId,
        );
        const nextGroupId = matchedGroup?.id || rootGroups.value[0].id;
        activeGroupId.value = nextGroupId;
        // 切类型/首屏同样先画可用区标签，再等 catalog 带商品回来。
        primeChildGroups(nextGroupId);
        await loadGroupPayload(nextGroupId, options);
      } else {
        activeGroupId.value = 0;
        activeChildId.value = 0;
        productsByGroup.value = {};
        childGroups.value = [];
        resetSelectedProduct({ syncRoute: shouldSyncRoute(options) });
      }
    } catch (error) {
      // 失败态与空态分离：目录拉取失败必须显式呈现并允许重试，
      // 不能落入「当前分类暂无商品」的假空态
      if (error?.code === "ERR_CANCELED" || error?.name === "CanceledError") {
        return;
      }
      if (token === rootToken) {
        pageError.value = true;
      }
    } finally {
      if (token === rootToken) {
        pageLoading.value = false;
      }
    }
  }

  async function loadGroupPayload(groupId, options = {}) {
    const token = ++groupToken;
    groupAbortController?.abort();
    groupAbortController = new AbortController();

    const cached = getCachedCatalog(groupId);
    if (cached) {
      // 命中缓存是同步完成的一次「加载」，同样要清掉上一次切换留下的加载态：
      // 切到未缓存地区后立刻切回已缓存地区时，未缓存那次的 finally 因
      // token !== groupToken 直接 return，不会复位 pageLoading，这里不补就永久卡住。
      applyGroupPayload(cached, options);
      if (token === groupToken) {
        pageLoading.value = false;
      }
      return;
    }

    const pending = catalogPendingMap.get(groupId);
    if (pending) {
      try {
        const data = await pending;
        if (token !== groupToken) {
          return;
        }

        applyGroupPayload(data, options);
      } catch {
        // 由发起请求的调用方处理错误
      } finally {
        // 复用别人在途的请求同样是一次加载结束点，必须复位，
        // 否则发起方被新切换取代后会留下永久加载态。
        if (token === groupToken) {
          pageLoading.value = false;
        }
      }

      return;
    }

    pageLoading.value = true;
    const controller = groupAbortController;

    const request = siteApi
      .productGroupCatalog(groupId, {
        signal: controller.signal,
      })
      .then((res) => res.data);

    catalogPendingMap.set(groupId, request);

    try {
      const data = await request;
      if (token !== groupToken) {
        return;
      }

      setCachedCatalog(groupId, data);
      applyGroupPayload(data, options);
    } catch (error) {
      if (error?.code === "ERR_CANCELED" || error?.name === "CanceledError") {
        return;
      }

      throw error;
    } finally {
      catalogPendingMap.delete(groupId);
      if (token === groupToken) {
        pageLoading.value = false;
      }
    }
  }

  function applyGroupPayload(data, options = {}) {
    // 成功写入即代表本次加载已恢复：pageError 只在 loadRootGroups/loadGroupPayload 的
    // 失败分支置 true，命中缓存走 applyGroupPayload 时不会经过那些分支，
    // 若不复位，切换回已缓存地区后错误块会一直挂在页面上（配置区其实已正常渲染）。
    pageError.value = false;

    const children = data.children || [];
    childGroups.value = children;
    // 用 catalog 的权威结果回填先行缓存，下次切回该地区时标签可同步出现。
    if (children.length && Number(data.effective_product_group_id || 0) > 0) {
      childGroupsCache.set(Number(data.effective_product_group_id), children);
    }

    const map = {};
    (data.items_by_group || []).forEach((item) => {
      const groupId = Number(item.effective_product_group_id || 0);
      if (groupId > 0) {
        map[groupId] = item.products || [];
      }
    });
    productsByGroup.value = map;

    if (children.length) {
      const targetChildId = Number(options.targetChildId || 0);
      const matchedChild = children.find((item) => item.id === targetChildId);
      const nextChildId = matchedChild?.id || children[0].id;
      activeChildId.value = nextChildId;
      const activeChildCategoryId = Number(
        matchedChild?.effective_product_group_id ||
          children[0]?.effective_product_group_id ||
          0,
      );
      const products = map[activeChildCategoryId] || [];
      syncDefaultProduct(products, options);
    } else {
      activeChildId.value = 0;
      const rootCategoryId = Number(
        data.effective_product_group_id ||
          activeGroup.value?.effective_product_group_id ||
          0,
      );
      const products = map[rootCategoryId] || [];
      syncDefaultProduct(products, options);
    }
  }

  async function applyRouteSelection() {
    const routePayload = readWebsiteProductRouteParams(route);

    if (
      !hasWebsiteProductRouteParams(routePayload) ||
      !productTypes.value.length
    ) {
      return false;
    }

    const targetType = productTypes.value.find(
      (item) => String(item.value || "") === String(routePayload.typeId),
    );
    const nextTypeValue =
      targetType?.value ||
      activeTypeValue.value ||
      productTypes.value[0]?.value ||
      "";

    if (!nextTypeValue) {
      return false;
    }

    await withSuspendedRouteSync(async () => {
      if (activeTypeValue.value !== nextTypeValue || !rootGroups.value.length) {
        activeTypeValue.value = nextTypeValue;
        await loadRootGroups({
          targetGroupId: routePayload.groupId,
          targetChildId: routePayload.childGroupId,
          targetProductId: routePayload.productId,
          syncRoute: false,
        });
        return;
      }

      if (
        routePayload.groupId > 0 &&
        activeGroupId.value !== routePayload.groupId
      ) {
        await switchGroup(routePayload.groupId, {
          targetChildId: routePayload.childGroupId,
          targetProductId: routePayload.productId,
          syncRoute: false,
        });
        return;
      }

      if (
        routePayload.childGroupId > 0 &&
        activeChildId.value !== routePayload.childGroupId
      ) {
        switchChild(routePayload.childGroupId, {
          targetProductId: routePayload.productId,
          syncRoute: false,
        });
        return;
      }

      if (
        routePayload.productId > 0 &&
        selectedProductId.value !== routePayload.productId
      ) {
        const matchedProduct = visibleProducts.value.find(
          (item) => item.id === routePayload.productId,
        );

        if (matchedProduct) {
          selectProduct(matchedProduct.id, { syncRoute: false });
        } else if (activeGroupId.value > 0) {
          await loadGroupPayload(activeGroupId.value, {
            targetChildId: routePayload.childGroupId,
            targetProductId: routePayload.productId,
            syncRoute: false,
          });
        }
      }
    });

    await syncSelectionRoute();

    return true;
  }

  async function init() {
    pageLoading.value = true;
    pageError.value = false;

    try {
      const linked = await initWithAggregatedApi();

      if (!linked && productTypes.value.length && !isMobile.value) {
        if (!rootGroups.value.length) {
          activeTypeValue.value = productTypes.value[0].value;
          await loadRootGroups({ syncRoute: true });
        } else {
          void syncSelectionRoute();
        }
      }
    } catch {
      // init 内任何聚合/兜底请求失败都收敛为页面错误态，避免 unhandled rejection
      pageError.value = true;
    } finally {
      pageLoading.value = false;
    }
  }

  async function initWithAggregatedApi() {
    const routePayload = readWebsiteProductRouteParams(route);
    const hasRouteTarget = hasWebsiteProductRouteParams(routePayload);

    try {
      const res = await siteApi.productsInit();
      const data = res.data || {};
      productTypes.value = data.types || [];

      if (!productTypes.value.length) {
        return false;
      }

      if (hasRouteTarget) {
        // 先用 productsInit 响应的 root_groups 与 catalog 填充本地状态/缓存，
        // 让 applyRouteSelection 命中本地分组与目录，避免深链「purchase-context →
        // product-groups → 完整目录」重复拉取首组目录。
        const routePayload = readWebsiteProductRouteParams(route);
        const targetType = productTypes.value.find(
          (item) => String(item.value || "") === String(routePayload.typeId),
        );
        const nextTypeValue =
          targetType?.value || productTypes.value[0]?.value || "";
        if (nextTypeValue) {
          activeTypeValue.value = nextTypeValue;
          const groups = filterGroupsByType(
            data.root_groups || [],
            nextTypeValue,
          );
          rootGroupsByType.set(nextTypeValue, groups);
          rootGroups.value = groups;
        }
        if (
          data.catalog &&
          Number(data.catalog.effective_product_group_id || 0) > 0
        ) {
          setCachedCatalog(
            data.catalog.effective_product_group_id,
            data.catalog,
          );
        }

        const linked = await applyRouteSelection();
        return linked;
      }

      const types = productTypes.value;
      const firstTypeValue = types[0]?.value || "";
      activeTypeValue.value = firstTypeValue;
      rootGroups.value = filterGroupsByType(
        data.root_groups || [],
        firstTypeValue,
      );
      rootGroupsByType.set(firstTypeValue, rootGroups.value);

      if (
        rootGroups.value.length &&
        data.catalog &&
        catalogBelongsToGroup(data.catalog, rootGroups.value[0].id)
      ) {
        const firstGroupId = rootGroups.value[0].id;
        activeGroupId.value = firstGroupId;
        setCachedCatalog(firstGroupId, data.catalog);
        applyGroupPayload(data.catalog, { syncRoute: true });
      } else if (rootGroups.value.length) {
        activeGroupId.value = rootGroups.value[0].id;
        await loadGroupPayload(rootGroups.value[0].id, { syncRoute: true });
      }

      return true;
    } catch {
      const res = await siteApi.productTypes();
      productTypes.value = res.data.list || [];
      return false;
    }
  }

  watch(isMobile, async (mobile) => {
    if (mobile) {
      return;
    }

    mobileProductDrawer.value = false;

    if (!activeTypeValue.value && productTypes.value.length) {
      activeTypeValue.value = productTypes.value[0].value;
      await loadRootGroups({ syncRoute: true });
    }
  });

  watch(
    () =>
      [
        route.params.typeId,
        route.params.groupId,
        route.params.childGroupId,
        route.params.productId,
        route.query.type,
        route.query.group,
      ].join("|"),
    async () => {
      if (!productTypes.value.length) {
        return;
      }

      const routePayload = readWebsiteProductRouteParams(route);

      if (hasWebsiteProductRouteParams(routePayload)) {
        const currentTypeValue = activeTypeValue.value;
        const currentGroupId = activeGroupId.value;
        const currentChildId = activeChildId.value;
        const currentProductId = selectedProductId.value;

        const changed =
          String(routePayload.typeId) !== String(currentTypeValue) ||
          routePayload.groupId !== currentGroupId ||
          routePayload.childGroupId !== currentChildId ||
          (routePayload.productId > 0 &&
            routePayload.productId !== currentProductId);

        if (changed) {
          // 目录请求失败时 loadGroupPayload 会 throw；这里没有 catch 就会变成
          // unhandled rejection 且页面停在旧数据上，用户只看到地址变了、内容没变。
          try {
            await applyRouteSelection();
          } catch {
            pageError.value = true;
          }
        }

        return;
      }
    },
  );

  // 切换产品目录（类型）时要先拉 product-groups（实测 700ms 左右）才能确定该类型下
  // 的地区列表，用户点完侧栏要等这一跳。首屏稳定后利用空闲时间把其余类型的
  // 地区列表预取进 rootGroupsByType，点目录即可同步命中。失败静默，走原路径。
  let rootPrefetchIdle = 0;

  function cancelRootPrefetch() {
    if (typeof window !== "undefined" && rootPrefetchIdle) {
      window.cancelIdleCallback?.(rootPrefetchIdle);
      rootPrefetchIdle = 0;
    }
  }

  function scheduleRootPrefetch() {
    const types = productTypes.value.map((item) => item.value).filter(Boolean);
    const pending = types.filter((value) => !rootGroupsByType.has(value));
    if (
      !pending.length ||
      typeof window === "undefined" ||
      typeof window.requestIdleCallback !== "function"
    ) {
      return;
    }

    cancelRootPrefetch();
    // 串行预取：并发会让 3 个 product-groups 一起压住首屏的详情/报价请求，
    // 实测并发时每个都要 2s+。串行后单个回到 200~400ms，且不会延长首屏关键路径。
    const prefetchNext = (index) => {
      if (index >= pending.length) {
        rootPrefetchIdle = 0;
        return;
      }

      const value = pending[index];
      if (rootGroupsByType.has(value)) {
        prefetchNext(index + 1);
        return;
      }

      void siteApi
        .productGroups({ first_product_group_code: value })
        .then((res) => {
          if (!rootGroupsByType.has(value)) {
            rootGroupsByType.set(
              value,
              filterGroupsByType(res.data.list || [], value),
            );
          }
        })
        .catch(() => {
          // 预取失败不影响正常切换：切过去时仍会按原路径请求。
        })
        .finally(() => {
          prefetchNext(index + 1);
        });
    };

    rootPrefetchIdle = window.requestIdleCallback(
      () => {
        prefetchNext(0);
      },
      // 首屏（目录→详情→库存→报价）优先，空闲回调至少等关键路径稳定后再触发
      { timeout: 6000 },
    );
  }

  onMounted(() => {
    updateMobileState();
    window.addEventListener("resize", updateMobileState);
    void init();
  });

  onBeforeUnmount(() => {
    cancelRootPrefetch();
    groupAbortController?.abort();
    window.removeEventListener("resize", updateMobileState);
  });

  return {
    pageLoading,
    pageError,
    retryInit: init,
    // 由页面层在首屏报价完成后调用：把其余类型的产品目录预取进缓存，
    // 之后点左侧产品目录可直接命中，省掉 700ms 的 product-groups 往返。
    prefetchRootGroups: scheduleRootPrefetch,
    sidebarCollapsed,
    isMobile,
    mobileProductDrawer,
    mobileRegionDrawer,
    tempGroupId,
    tempChildGroups,
    mobilePendingProductId,
    productTypes,
    rootGroups,
    childGroups,
    activeTypeValue,
    activeGroupId,
    activeChildId,
    selectedProductId,
    activeGroupName,
    activeChildName,
    visibleProducts,
    openMobileRegionDrawer,
    selectTempGroup,
    handleRegionChange,
    confirmRegionSelection,
    openMobileProductDrawer,
    confirmMobileProductSelection,
    switchType,
    switchGroup,
    switchChild,
    selectProduct,
  };
}
