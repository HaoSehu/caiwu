<template>
  <div class="orders-hub admin-page">
    <el-tabs :model-value="activeTab" class="hub-tabs" @tab-change="onTabChange">
      <el-tab-pane label="账单管理" name="invoices" />
      <el-tab-pane label="服务列表" name="services" />
    </el-tabs>

    <Suspense timeout="0">
      <template #default>
        <keep-alive>
          <component :is="activeComponent" />
        </keep-alive>
      </template>
      <template #fallback>
        <AdminAsyncPane />
      </template>
    </Suspense>
  </div>
</template>

<script setup>
import { computed, defineAsyncComponent } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AdminAsyncPane from '@/components/common/AdminAsyncPane.vue'
import { useRouteTabs } from '@/composables/useRouteTabs'

const route = useRoute()
const router = useRouter()
const VALID_TABS = Object.freeze(['invoices', 'services'])
const tabComponents = Object.freeze({
  invoices: defineAsyncComponent(() => import('@/views/admin/Orders/InvoicesPage.vue')),
  services: defineAsyncComponent(() => import('@/views/admin/Orders/ServicesPage.vue')),
})

const { activeTab, onTabChange } = useRouteTabs(route, router, VALID_TABS, 'invoices')
const activeComponent = computed(() => tabComponents[activeTab.value] || tabComponents.invoices)
</script>

<style lang="scss" scoped>
.orders-hub {
  :deep(.el-tabs__content) { padding-top: 20px; }
}
</style>
