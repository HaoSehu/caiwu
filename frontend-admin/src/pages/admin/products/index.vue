<template>
  <div class="products-hub admin-page">
    <el-tabs :model-value="activeTab" class="hub-tabs" @tab-change="onTabChange">
      <el-tab-pane label="商品目录" name="catalog" />
      <el-tab-pane label="流量包" name="traffic-packages" />
      <el-tab-pane label="供应商" name="suppliers" />
    </el-tabs>

    <keep-alive>
      <component :is="activeComponent" />
    </keep-alive>
  </div>
</template>

<script setup>
import { computed, defineAsyncComponent } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useRouteTabs } from '@/composables/useRouteTabs'

const route = useRoute()
const router = useRouter()
const VALID_TABS = Object.freeze(['catalog', 'traffic-packages', 'suppliers'])
const tabComponents = Object.freeze({
  catalog: defineAsyncComponent(() => import('@/pages/admin/products/catalog/index.vue')),
  'traffic-packages': defineAsyncComponent(() => import('@/views/admin/Products/TrafficPackagesPage.vue')),
  suppliers: defineAsyncComponent(() => import('@/views/admin/Products/SuppliersPage.vue')),
})

const { activeTab, onTabChange } = useRouteTabs(route, router, VALID_TABS, 'catalog')
const activeComponent = computed(() => tabComponents[activeTab.value] || tabComponents.catalog)
</script>

<style lang="scss" scoped>
.products-hub {
  :deep(.el-tabs__content) { padding-top: 20px; }
}
</style>
