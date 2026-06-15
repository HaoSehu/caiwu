<template>
  <div class="products-page">
    <ProductCatalog v-if="activeTab === 'catalog'" />
    <TrafficPackages v-else-if="activeTab === 'traffic-packages'" />
    <Suppliers v-else />
  </div>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue';
import { useRoute } from 'vue-router';

import ProductCatalog from './components/ProductCatalog.vue';
import Suppliers from './components/Suppliers.vue';
import TrafficPackages from './components/TrafficPackages.vue';

import './index.less';

defineOptions({ name: 'AdminProducts' });

const VALID_TABS = ['catalog', 'traffic-packages', 'suppliers'] as const;
type ProductTab = (typeof VALID_TABS)[number];

const route = useRoute();

const activeTab = ref<ProductTab>(resolveRouteProductTab());

function normalizeTab(value: unknown): ProductTab {
  return VALID_TABS.includes(value as ProductTab) ? (value as ProductTab) : 'catalog';
}

function resolveRouteProductTab(): ProductTab {
  return normalizeTab(route.meta.productTab || route.query.tab);
}

watch(
  () => route.fullPath,
  () => {
    const next = resolveRouteProductTab();
    if (next !== activeTab.value) {
      activeTab.value = next;
    }
  },
);
</script>
