<template>
  <div class="growth-hub admin-page">
    <el-tabs :model-value="activeTab" class="hub-tabs" @tab-change="onTabChange">
      <el-tab-pane label="会员等级" name="member-levels" />
      <el-tab-pane label="推广返利" name="referral" />
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
const VALID_TABS = Object.freeze(['member-levels', 'referral'])
const tabComponents = Object.freeze({
  'member-levels': defineAsyncComponent(() => import('./MemberLevelsPage.vue')),
  referral: defineAsyncComponent(() => import('./Referral/index.vue')),
})

const { activeTab, onTabChange } = useRouteTabs(route, router, VALID_TABS, 'member-levels')
const activeComponent = computed(() => tabComponents[activeTab.value] || tabComponents['member-levels'])
</script>

<style lang="scss" scoped>
.growth-hub {
  :deep(.el-tabs__content) { padding-top: 20px; }
}
</style>
