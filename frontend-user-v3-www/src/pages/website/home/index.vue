<template>
  <div class="home-page">
    <HomeHeroCarousel v-if="homeHeroReady" :hero="homeHero || {}" />
    <HomeSectionSkeleton v-else type="hero" />

    <div v-if="homeRequestFailed" class="home-error-bar" role="alert">
      <span class="home-error-bar__text">页面数据加载失败，请检查网络连接</span>
      <button type="button" class="home-error-bar__retry" @click="retryLoad">
        重新加载
      </button>
    </div>

    <HomeProductTabs
      v-if="productContentReady"
      :loading="loading"
      :product-types="productTypes"
      :root-groups="rootGroups"
      :group-catalog-map="groupCatalogMap"
    />
    <HomeSectionSkeleton v-else type="products" />

    <template v-if="homeContentReady && mountDeferredSections">
      <HomeSolutionSection />
      <HomePartnerSection />
      <HomeNewsSection :notices="notices" />
      <HomeRegisterBar />
    </template>
    <div v-else ref="deferredSkeletonRef">
      <HomeSectionSkeleton type="solutions" />
      <HomeSectionSkeleton type="partner" />
      <HomeSectionSkeleton type="news" />
      <HomeSectionSkeleton type="register" />
    </div>
  </div>
</template>

<script setup lang="ts">
import {
  computed,
  defineAsyncComponent,
  onBeforeUnmount,
  onMounted,
  ref,
} from "vue";
import siteApi from "@/api/site";
import { useAppStore } from "@/stores/app";
import HomeHeroCarousel from "@/views/website/Home/components/HomeHeroCarousel.vue";
import HomeProductTabs from "@/views/website/Home/components/HomeProductTabs.vue";
import HomeSectionSkeleton from "@/views/website/Home/components/HomeSectionSkeleton.vue";

const HomeSolutionSection = defineAsyncComponent(
  () => import("@/views/website/Home/components/HomeSolutionSection.vue"),
);
const HomePartnerSection = defineAsyncComponent(
  () => import("@/views/website/Home/components/HomePartnerSection.vue"),
);
const HomeNewsSection = defineAsyncComponent(
  () => import("@/views/website/Home/components/HomeNewsSection.vue"),
);
const HomeRegisterBar = defineAsyncComponent(
  () => import("@/views/website/Home/components/HomeRegisterBar.vue"),
);

interface ProductTypeItem {
  id?: number;
  value?: string;
  label?: string;
  product_count?: number;
}

const appStore = useAppStore();
const loading = ref(true);
const homeLoaded = ref(false);
const homeLoadSucceeded = ref(false);
const homeRequestFailed = ref(false);
const mountDeferredSections = ref(false);
const deferredSkeletonRef = ref<HTMLElement | null>(null);
let deferredObserver: IntersectionObserver | null = null;
const homeHero = ref<Record<string, unknown> | null>(null);
const notices = ref<any[]>([]);
const rootGroups = ref<any[]>([]);
const groupCatalogMap = ref<Record<string, unknown>>({});
const productTypes = ref<ProductTypeItem[]>([]);
const homeContentReady = computed(() => homeLoaded.value);
// 请求完成即视为就绪：空数据/请求失败时由子组件各自的兜底接管，
// 避免整页永久骨架屏（HomeHeroCarousel 有 DEFAULT_SLIDES，HomeProductTabs 有空态）
const homeHeroReady = computed(() => homeLoaded.value);
const productContentReady = computed(() => homeLoaded.value);

function deriveProductTypesFromGroups(groups: any[]) {
  const map = new Map<string, ProductTypeItem>();

  groups.forEach((group, index) => {
    const value = String(group?.first_product_group_code || "");
    if (!value) {
      return;
    }

    const current = map.get(value) || {
      id: Number(group?.product_type_id || index + 1),
      value,
      label: String(group?.first_product_group_name || `产品分类 ${index + 1}`),
      product_count: 0,
    };

    current.product_count =
      Number(current.product_count || 0) + Number(group?.product_count || 0);
    map.set(value, current);
  });

  return Array.from(map.values());
}

function resolveHome() {
  // 优先消费 index.html 预热期 fetch 的 /v2/site/home 响应（window.__HOME_FETCH__），
  // 复用正常化管线；预热失败/无预热则回退常规 axios 请求。
  const prefetchPromise = (window as any).__HOME_FETCH__;
  if (!prefetchPromise) {
    return siteApi.home();
  }

  (window as any).__HOME_FETCH__ = undefined;
  return prefetchPromise.then((raw: any) => {
    if (raw && raw.code === 0 && raw.data && typeof raw.data === "object") {
      return siteApi.homeFromRaw(raw);
    }
    return siteApi.home();
  });
}

async function loadHomePage() {
  loading.value = true;
  homeLoaded.value = false;
  homeLoadSucceeded.value = false;
  homeRequestFailed.value = false;

  const homeRes = await resolveHome().catch(() => undefined);
  if (homeRes?.data) {
    homeLoadSucceeded.value = true;
    const data = homeRes.data || {};
    notices.value = Array.isArray(data.notices) ? data.notices : [];
    rootGroups.value = Array.isArray(data.root_groups) ? data.root_groups : [];
    groupCatalogMap.value =
      data.group_catalog_map && typeof data.group_catalog_map === "object"
        ? data.group_catalog_map
        : {};

    // 从 home 响应中提取 hero 数据
    homeHero.value = data.hero || {};

    if (data.site_config) {
      appStore.hydrateSiteConfig(data.site_config);
    }
  } else {
    homeRequestFailed.value = true;
  }

  // 产品分类由 home 响应的 root_groups 推导，避免额外一次 /v2/site/product-types 请求
  productTypes.value = deriveProductTypesFromGroups(rootGroups.value);

  homeLoaded.value = true;
  loading.value = false;
}

function setupDeferredObserver() {
  if (typeof window !== "undefined" && "IntersectionObserver" in window) {
    deferredObserver = new IntersectionObserver(
      (entries) => {
        if (entries[0]?.isIntersecting) {
          mountDeferredSections.value = true;
          if (deferredObserver) {
            deferredObserver.disconnect();
            deferredObserver = null;
          }
        }
      },
      { rootMargin: "200px" },
    );

    // 骨架屏 ref 就绪后开始观察
    if (deferredSkeletonRef.value) {
      deferredObserver.observe(deferredSkeletonRef.value);
    } else {
      // ref 尚未挂载（数据就绪时骨架不会渲染），直接挂载
      mountDeferredSections.value = true;
    }
  } else {
    // 不支持 IntersectionObserver 的旧浏览器直接挂载
    mountDeferredSections.value = true;
  }
}

async function retryLoad() {
  homeRequestFailed.value = false;
  await loadHomePage();
  // 重试成功后延迟区可能尚未挂载，重建观察器；失败则骨架仍在，滚动后挂载兜底区块
  if (homeLoaded.value && !mountDeferredSections.value) {
    setupDeferredObserver();
  }
}

onMounted(async () => {
  await loadHomePage();

  // 无论请求成败都建立延迟区观察：成功时滚动挂载延迟区块，
  // 失败时骨架仍渲染，滚动进入后再挂载静态兜底区块，避免整页卡在骨架
  setupDeferredObserver();
});

onBeforeUnmount(() => {
  if (deferredObserver) {
    deferredObserver.disconnect();
    deferredObserver = null;
  }
});
</script>

<style scoped lang="scss">
.home-error-bar {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 16px;
  max-width: 1200px;
  margin: 24px auto 0;
  padding: 12px 20px;
  border: 1px solid #ffe1d8;
  border-radius: 8px;
  background: #fff7f5;

  &__text {
    color: #b54708;
    font-size: 14px;
  }

  &__retry {
    padding: 6px 16px;
    border: 1px solid #f04438;
    border-radius: 6px;
    background: #fff;
    color: #f04438;
    font-size: 14px;
    cursor: pointer;

    &:hover {
      background: #fff1f0;
    }
  }
}
</style>
