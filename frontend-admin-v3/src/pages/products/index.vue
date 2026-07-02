<template>
  <div class="products-page">
    <t-tabs :value="activeTab" @change="handleTabChange" class="products-tabs">
      <t-tab-panel value="catalog" label="商品目录" />
      <t-tab-panel value="traffic-packages" label="流量包" />
      <t-tab-panel value="suppliers" label="提供商" />
    </t-tabs>
    <ProductCatalog v-if="activeTab === 'catalog'" />
    <TrafficPackages v-else-if="activeTab === 'traffic-packages'" />
    <Suppliers v-else />
  </div>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';

import ProductCatalog from './components/ProductCatalog.vue';
import Suppliers from './components/Suppliers.vue';
import TrafficPackages from './components/TrafficPackages.vue';

import './index.less';

defineOptions({ name: 'AdminProducts' });

const VALID_TABS = ['catalog', 'traffic-packages', 'suppliers'] as const;
type ProductTab = (typeof VALID_TABS)[number];

const route = useRoute();
const router = useRouter();

const activeTab = ref<ProductTab>(resolveRouteProductTab());

function normalizeTab(value: unknown): ProductTab {
  return VALID_TABS.includes(value as ProductTab) ? (value as ProductTab) : 'catalog';
}

function resolveRouteProductTab(): ProductTab {
  // query.tab 优先于 meta.productTab，允许页面内切换
  return normalizeTab(route.query.tab || route.meta.productTab);
}

function handleTabChange(value: string | number) {
  const tab = normalizeTab(value);
  activeTab.value = tab;
  router.replace({ path: '/admin/products', query: tab === 'catalog' ? {} : { tab } });
}

watch(
  () => route.query.tab,
  (newTab) => {
    const next = normalizeTab(newTab);
    if (next !== activeTab.value) {
      activeTab.value = next;
    }
  },
);
</script>
