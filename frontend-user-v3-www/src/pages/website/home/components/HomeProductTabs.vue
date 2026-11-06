<template>
  <section class="product-tabs-section" :aria-busy="loading ? 'true' : 'false'">
    <div class="container">
      <header class="product-tabs__head">
        <h2 class="product-tabs__title">安全、稳定、可信赖的产品与服务</h2>
        <p class="product-tabs__desc">
          {{
            appStore.siteName
          }}
          的技术积淀与交付实践，助力企业上云与产业互联网。
          <router-link to="/products" class="product-tabs__more">
            查看全部产品
            <chevron-right-icon />
          </router-link>
        </p>
      </header>

      <div
        class="product-tabs__list"
        role="tablist"
        aria-label="产品分类"
        @keydown="onTablistKeydown"
      >
        <button
          v-for="item in enterpriseServiceItems"
          :id="`product-tab-${item.key}`"
          :key="item.key"
          type="button"
          role="tab"
          class="product-tabs__tab"
          :class="{ 'is-active': item.key === activeEnterpriseServiceKey }"
          :aria-selected="item.key === activeEnterpriseServiceKey"
          :aria-controls="productPanelId"
          :tabindex="item.key === activeEnterpriseServiceKey ? 0 : -1"
          @click="activateEnterpriseService(item.key)"
        >
          {{ item.label }}
        </button>
      </div>

      <!-- tab 必须指向一个真实面板：role=tabpanel + aria-labelledby 指回当前选中的 tab，
           否则读屏进入标签页模式后找不到对应内容 -->
      <div
        :id="productPanelId"
        class="product-tabs__grid"
        role="tabpanel"
        :aria-labelledby="activeTabElementId"
        tabindex="0"
      >
        <button
          v-for="card in activeEnterpriseService.cards"
          :key="`${activeEnterpriseService.key}-${card.key}`"
          type="button"
          class="product-card"
          @click="router.push(card.path)"
        >
          <strong class="product-card__title">{{ card.title }}</strong>
          <p class="product-card__desc">{{ card.desc }}</p>
          <span class="product-card__cta">
            立即选购
            <chevron-right-icon />
          </span>
        </button>
      </div>
    </div>
  </section>
</template>

<script setup>
import { computed, shallowRef, watch } from "vue";
import { useRouter } from "vue-router";
import { ChevronRightIcon } from "tdesign-icons-vue-next";
import { useAppStore } from "@/app/stores/app";
import { buildWebsiteProductPath } from "@/utils/productRoute";
import { resolveProductDisplayName } from "@/utils/websiteProductConfig";

const EMPTY_LIST = Object.freeze([]);

const props = defineProps({
  loading: {
    type: Boolean,
    default: false,
  },
  productTypes: {
    type: Array,
    default: () => [],
  },
  rootGroups: {
    type: Array,
    default: () => [],
  },
  groupCatalogMap: {
    type: Object,
    default: () => ({}),
  },
});

const router = useRouter();
const appStore = useAppStore();
const activeEnterpriseServiceKey = shallowRef("");

// WAI-ARIA Tabs：tabpanel 的 id 固定，tab 通过 aria-controls 指向它，
// 面板再用 aria-labelledby 指回当前选中的 tab，形成完整的 tab ↔ tabpanel 关联
const productPanelId = "home-product-panel";
const activeTabElementId = computed(() =>
  activeEnterpriseServiceKey.value
    ? `product-tab-${activeEnterpriseServiceKey.value}`
    : undefined,
);
const resolvedProductTypes = computed(() => {
  if (props.productTypes.length) {
    return props.productTypes;
  }

  return deriveProductTypesFromGroups(props.rootGroups);
});

const activeEnterpriseType = computed(
  () =>
    resolvedProductTypes.value.find(
      (item) => item.value === activeEnterpriseServiceKey.value,
    ) ||
    resolvedProductTypes.value[0] ||
    null,
);

const activeEnterpriseGroups = computed(() => {
  const activeTypeValue = activeEnterpriseType.value?.value || "";
  return props.rootGroups.filter(
    (group) =>
      activeTypeValue === "" ||
      group.first_product_group_code === activeTypeValue,
  );
});

const activeEnterpriseCards = computed(() =>
  activeEnterpriseGroups.value.map((group, index) => {
    const groupId = Number(group?.id || 0);
    const summary = props.groupCatalogMap[groupId] || null;
    const primaryProduct = resolveEnterprisePrimaryProduct(group, summary);

    return {
      key: String(groupId || index + 1),
      title: group?.name || `二级分类 ${index + 1}`,
      desc: resolveEnterpriseCategoryDesc(group, summary, primaryProduct),
      path: resolveEnterpriseCategoryPath(group, primaryProduct),
    };
  }),
);

const activeEnterpriseService = computed(() => {
  const cards = activeEnterpriseCards.value.length
    ? activeEnterpriseCards.value
    : [
        {
          key: "catalog",
          title: `${activeEnterpriseType.value?.label || "当前分类"}目录`,
          desc: "当前分类暂无可展示的二级分类，点击后可进入产品目录查看更多内容。",
          path: "/products",
        },
      ];

  return {
    key: activeEnterpriseType.value?.value || "",
    cards,
  };
});

const enterpriseServiceItems = computed(() =>
  resolvedProductTypes.value.map((type, index) => ({
    key: type.value || String(Number(type.id || index + 1)),
    id: Number(type.id || index + 1),
    label: type.label || `产品分类 ${index + 1}`,
  })),
);

function deriveProductTypesFromGroups(groups) {
  if (!groups.length) {
    return EMPTY_LIST;
  }

  const map = new Map();

  groups.forEach((group, index) => {
    const value = String(group?.first_product_group_code || "");

    if (!value) {
      return;
    }

    const current = map.get(value) || {
      id: Number(group?.product_type_id || index + 1),
      value,
      label: group?.first_product_group_name || `产品分类 ${index + 1}`,
      group_count: 0,
      product_count: 0,
    };

    current.group_count += 1;
    current.product_count += Number(group?.product_count || 0);
    map.set(value, current);
  });

  return Array.from(map.values());
}

function activateEnterpriseService(key) {
  activeEnterpriseServiceKey.value = String(key);
}

// WAI-ARIA Tabs 键盘模式：方向键移动选中项并管理 tabindex，Home/End 到首尾
function onTablistKeydown(event) {
  const keys = ["ArrowLeft", "ArrowRight", "Home", "End"];
  if (!keys.includes(event.key)) return;
  const items = enterpriseServiceItems.value;
  if (!items.length) return;
  event.preventDefault();

  const currentIndex = items.findIndex(
    (item) => item.key === activeEnterpriseServiceKey.value,
  );
  const activeIndex = currentIndex >= 0 ? currentIndex : 0;
  let nextIndex;

  if (event.key === "Home") {
    nextIndex = 0;
  } else if (event.key === "End") {
    nextIndex = items.length - 1;
  } else if (event.key === "ArrowRight") {
    nextIndex = (activeIndex + 1) % items.length;
  } else {
    nextIndex = (activeIndex - 1 + items.length) % items.length;
  }

  const next = items[nextIndex];
  if (next) activateEnterpriseService(next.key);
}

function resolveEnterpriseCategoryDesc(group, summary, primaryProduct) {
  const featuredName = resolveProductDisplayName(summary?.featured_product);
  if (featuredName) {
    return `主推 ${featuredName}，可在线查看配置并快速完成选型。`;
  }

  const previewName = resolveProductDisplayName(primaryProduct?.product);
  if (previewName) {
    return `主推 ${previewName}，可在线查看配置并快速完成选型。`;
  }

  if (group?.slogan) {
    return group.slogan;
  }

  const totalProducts = Number(group?.product_count || 0);
  return `${group?.product_type_label || "当前分类"} 下已上架 ${totalProducts} 款在售产品，可按业务需求快速选型。`;
}

function resolveEnterpriseCategoryPath(group, primaryProduct) {
  if (!primaryProduct?.product) {
    const typeCode = String(group?.first_product_group_code || "");
    const groupId = Number(group?.id || 0);

    if (typeCode && groupId > 0) {
      return {
        path: "/products",
        query: {
          type: typeCode,
          group: String(groupId),
        },
      };
    }

    return "/products";
  }

  return buildWebsiteProductPath({
    typeId: String(group?.first_product_group_code || ""),
    groupId: Number(group?.id || 0),
    childGroupId: Number(primaryProduct.childGroupId || 0),
    productId: Number(primaryProduct.product?.id || 0),
  });
}

function resolveEnterprisePrimaryProduct(group, summary) {
  const featuredProduct =
    summary?.featured_product && typeof summary.featured_product === "object"
      ? summary.featured_product
      : null;

  if (featuredProduct) {
    const productGroupId = Number(
      featuredProduct?.effective_product_group_id || 0,
    );
    return {
      product: featuredProduct,
      childGroupId:
        productGroupId > 0 && productGroupId !== Number(group?.id || 0)
          ? productGroupId
          : 0,
    };
  }

  const previewProduct = Array.isArray(summary?.preview_products)
    ? summary.preview_products[0]
    : null;

  if (previewProduct && typeof previewProduct === "object") {
    const productGroupId = Number(
      previewProduct?.effective_product_group_id || 0,
    );
    return {
      product: previewProduct,
      childGroupId:
        productGroupId > 0 && productGroupId !== Number(group?.id || 0)
          ? productGroupId
          : 0,
    };
  }

  return null;
}

watch(
  () => enterpriseServiceItems.value.map((item) => item.key).join("|"),
  () => {
    if (!enterpriseServiceItems.value.length) {
      activeEnterpriseServiceKey.value = "";
      return;
    }

    const currentExists = enterpriseServiceItems.value.some(
      (item) => item.key === activeEnterpriseServiceKey.value,
    );

    if (!currentExists) {
      activeEnterpriseServiceKey.value = enterpriseServiceItems.value[0].key;
    }
  },
  { immediate: true },
);
</script>

<style scoped lang="scss">
.container {
  @include site-container;
}

.product-tabs-section {
  position: relative;
  padding: 72px 0 80px;
  background: $bg-color-card;
}

.product-tabs__head {
  text-align: center;
}

.product-tabs__title {
  margin: 0;
  color: $text-color-heading;
  font-size: clamp(26px, 2.4vw, 30px);
  font-weight: 700;
  line-height: 1.28;
  letter-spacing: -0.01em;
}

.product-tabs__desc {
  max-width: 760px;
  margin: 12px auto 0;
  color: $text-color-secondary;
  font-size: 14px;
  line-height: 1.8;
}

.product-tabs__more {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  margin-left: 8px;
  color: $color-primary;
  font-weight: 500;
  text-decoration: none;
  transition: color 0.22s cubic-bezier(0.22, 1, 0.36, 1);
}

.product-tabs__more:hover {
  color: $color-primary-hover;
}

.product-tabs__more .t-icon {
  font-size: 12px;
}

.product-tabs__list {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: 0 32px;
  margin: 32px auto 28px;
  border-bottom: 1px solid rgba(15, 23, 42, 0.08);
}

.product-tabs__tab {
  position: relative;
  padding: 8px 2px 16px;
  border: none;
  background: transparent;
  color: $text-color-secondary;
  font-size: 15px;
  font-weight: 500;
  line-height: 1.5;
  cursor: pointer;
  transition: color 0.22s cubic-bezier(0.22, 1, 0.36, 1);
}

.product-tabs__tab:hover {
  color: $color-primary;
}

.product-tabs__tab::after {
  content: "";
  position: absolute;
  left: 50%;
  bottom: -1px;
  width: 0;
  height: 2px;
  background: $color-primary;
  transform: translateX(-50%);
  transition: width 0.28s cubic-bezier(0.22, 1, 0.36, 1);
}

.product-tabs__tab.is-active {
  color: $color-primary;
  font-weight: 600;
}

.product-tabs__tab.is-active::after {
  width: 100%;
}

.product-tabs__grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 16px;
}

.product-card {
  position: relative;
  display: flex;
  flex-direction: column;
  align-items: stretch;
  gap: 8px;
  min-height: 100px;
  padding: 14px 20px 12px;
  border: 1px solid $border-color;
  border-radius: 8px;
  background: $bg-color-card;
  text-align: left;
  cursor: pointer;
  appearance: none;
  overflow: hidden;
  transition:
    border-color 0.24s cubic-bezier(0.22, 1, 0.36, 1),
    box-shadow 0.24s cubic-bezier(0.22, 1, 0.36, 1),
    transform 0.24s cubic-bezier(0.22, 1, 0.36, 1);
}

.product-card:hover {
  border-color: rgba(22, 93, 255, 0.4);
  box-shadow: 0 18px 40px $color-primary-a12;
  transform: translateY(-3px);
}

.product-card:active {
  transform: translateY(-1px) scale(0.99);
}

.product-card__title {
  flex: 1;
  min-width: 0;
  color: $text-color-heading;
  font-size: 16px;
  font-weight: 600;
  line-height: 1.4;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.product-card__desc {
  margin: 0;
  color: $text-color-secondary;
  font-size: 13px;
  line-height: 1.78;
  display: -webkit-box;
  overflow: hidden;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
  line-clamp: 2;
}

.product-card__cta {
  display: inline-flex;
  align-items: center;
  align-self: flex-end;
  gap: 4px;
  margin-top: auto;
  color: $color-primary;
  font-size: 13px;
  font-weight: 600;
  opacity: 1;
  transform: none;
  transition:
    transform 0.24s cubic-bezier(0.22, 1, 0.36, 1);
}

// 行动提示常显（触屏无 hover，原默认隐藏会让 CTA 在移动端不可见）；
// 悬停仅做箭头位移增强
@media (hover: hover) {
  .product-card__cta {
    opacity: 0.72;
    transform: translateX(-4px);
    transition:
      opacity 0.24s cubic-bezier(0.22, 1, 0.36, 1),
      transform 0.24s cubic-bezier(0.22, 1, 0.36, 1);
  }

  .product-card:hover .product-card__cta {
    opacity: 1;
    transform: translateX(0);
  }
}

@media (max-width: $breakpoint-wide) {
  .product-tabs__grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: $breakpoint-nav) {
  .product-tabs__desc {
    font-size: 13px;
  }

  .product-tabs__tab {
    font-size: 14px;
  }
}

@media (max-width: $breakpoint-compact) {
  .product-tabs-section {
    padding: 48px 0 56px;
  }

  .product-tabs__title {
    font-size: 20px;
  }

  .product-tabs__grid {
    grid-template-columns: 1fr;
  }
}

// 手机形态（≤767.98px）：容器留白、标签行与卡片热区按单手触控口径收口。
// 断点比 compact（640）宽，覆盖 360~767 全部手机竖屏。
@media (max-width: $breakpoint-mobile-max) {
  .container {
    width: calc(100% - 24px);
  }

  .product-tabs-section {
    padding: 40px 0 44px;
  }

  .product-tabs__title {
    font-size: 20px;
    line-height: 1.35;
  }

  .product-tabs__desc {
    margin-top: 10px;
    font-size: 13px;
    line-height: 1.7;
  }

  // 「查看全部产品」是行内文字链接，手机端补 44px 热区
  .product-tabs__more {
    min-height: 44px;
    padding: 0 4px;
  }

  // 分类标签：手机上 5 个标签会折成两行，改为单行横向滚动，
  // 与首页 hero 入口列、商品页类型切换同一范式
  .product-tabs__list {
    flex-wrap: nowrap;
    justify-content: flex-start;
    margin: 20px 0 16px;
    gap: 0 8px;
    overflow-x: auto;
    scrollbar-width: none;
    -webkit-overflow-scrolling: touch;

    &::-webkit-scrollbar {
      display: none;
    }
  }

  .product-tabs__tab {
    flex: 0 0 auto;
    min-height: 44px;
    padding: 0 4px;
    white-space: nowrap;
  }

  .product-tabs__grid {
    gap: 12px;
  }

  .product-card {
    min-height: 0;
    padding: 16px;
    border-radius: 12px;
  }

  // 卡片整块可点，热区由内容撑开；标题在手机上允许两行，不再单行省略
  .product-card__title {
    display: -webkit-box;
    overflow: hidden;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2;
    line-clamp: 2;
    white-space: normal;
  }
}
</style>
