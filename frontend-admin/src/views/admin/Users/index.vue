<template>
  <div class="users-hub admin-page">
    <el-tabs :model-value="activeTab" class="hub-tabs" @tab-change="onTabChange">
      <el-tab-pane label="用户列表" name="list" />
      <el-tab-pane label="实名管理" name="verification" />
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
const VALID_TABS = Object.freeze(['list', 'verification'])
const tabComponents = Object.freeze({
  list: defineAsyncComponent(() => import('./ListPage.vue')),
  verification: defineAsyncComponent(() => import('./Verification/index.vue')),
})

const { activeTab, onTabChange } = useRouteTabs(route, router, VALID_TABS, 'list')
const activeComponent = computed(() => tabComponents[activeTab.value] || tabComponents.list)
</script>

<style lang="scss" scoped>
.users-hub {
  :deep(.el-tabs__content) { padding-top: 20px; }
}
</style>
