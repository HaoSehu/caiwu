<template>
  <div class="products-page">
    <ProductCatalog v-if="activeTab === 'catalog'" />
    <TrafficPackages v-else-if="activeTab === 'traffic-packages'" />
    <Suppliers v-else />
  </div>
</template>

<script setup lang="ts">
import { defineAsyncComponent, ref, watch } from 'vue';
import { useRoute } from 'vue-router';

import './index.less';

defineOptions({ name: 'AdminProducts' });

const VALID_TABS = ['catalog', 'traffic-packages', 'suppliers'] as const;
type ProductTab = (typeof VALID_TABS)[number];

const route = useRoute();

const ProductCatalog = defineAsyncComponent(() => import('./components/ProductCatalog.vue'));
const TrafficPackages = defineAsyncComponent(() => import('./components/TrafficPackages.vue'));
const Suppliers = defineAsyncComponent(() => import('./components/Suppliers.vue'));

const activeTab = ref<ProductTab>(resolveRouteProductTab());

function normalizeTab(value: unknown): ProductTab {
  return VALID_TABS.includes(value as ProductTab) ? (value as ProductTab) : 'catalog';
}

function resolveRouteProductTab(): ProductTab {
  return normalizeTab(route.query.tab || route.meta.productTab);
}

watch(
  () => [route.path, route.query.tab, route.meta.productTab],
  () => {
    const next = resolveRouteProductTab();
    if (next !== activeTab.value) {
      activeTab.value = next;
    }
  },
);
</script>
