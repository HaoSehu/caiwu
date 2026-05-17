<template>
  <div class="notifications-hub admin-page">
    <el-tabs :model-value="activeTab" class="hub-tabs" @tab-change="onTabChange">
      <el-tab-pane label="接口配置" name="interfaces" />
      <el-tab-pane label="邮件模板" name="email-templates" />
      <el-tab-pane label="API 接口" name="api-directory" />
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
const VALID_TABS = Object.freeze(['interfaces', 'email-templates', 'api-directory'])
const tabComponents = Object.freeze({
  interfaces: defineAsyncComponent(() => import('@/views/admin/Notifications/InterfacesPage.vue')),
  'email-templates': defineAsyncComponent(() => import('@/views/admin/Notifications/EmailTemplates/ListPage.vue')),
  'api-directory': defineAsyncComponent(() => import('@/views/admin/Notifications/ApiDirectory/index.vue')),
})

const { activeTab, onTabChange } = useRouteTabs(route, router, VALID_TABS, 'interfaces')
const activeComponent = computed(() => tabComponents[activeTab.value] || tabComponents.interfaces)
</script>
