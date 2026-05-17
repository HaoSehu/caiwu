<template>
  <div class="logs-hub admin-page">
    <el-tabs :model-value="activeTab" class="hub-tabs" @tab-change="onTabChange">
      <el-tab-pane label="系统日志" name="system" />
      <el-tab-pane label="管理员登录" name="admin-logins" />
      <el-tab-pane label="API 日志" name="api" />
      <el-tab-pane label="短信日志" name="sms" />
      <el-tab-pane label="邮件日志" name="email" />
      <el-tab-pane label="任务日志" name="tasks" />
      <el-tab-pane label="定时任务" name="schedules" />
      <el-tab-pane label="日志清理" name="cleanup" />
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
const VALID_TABS = Object.freeze(['system', 'admin-logins', 'api', 'sms', 'email', 'tasks', 'schedules', 'cleanup'])
const tabComponents = Object.freeze({
  system: defineAsyncComponent(() => import('@/views/admin/Logs/SystemLogs.vue')),
  'admin-logins': defineAsyncComponent(() => import('@/views/admin/Logs/AdminLoginLogs.vue')),
  api: defineAsyncComponent(() => import('@/views/admin/Logs/ApiLogs.vue')),
  sms: defineAsyncComponent(() => import('@/views/admin/Logs/SmsLogs.vue')),
  email: defineAsyncComponent(() => import('@/views/admin/Notifications/EmailLogs/index.vue')),
  tasks: defineAsyncComponent(() => import('@/views/admin/Logs/TaskLogs.vue')),
  schedules: defineAsyncComponent(() => import('@/views/admin/Logs/ScheduleTasks.vue')),
  cleanup: defineAsyncComponent(() => import('@/views/admin/Logs/LogCleanup.vue')),
})

const { activeTab, onTabChange } = useRouteTabs(route, router, VALID_TABS, 'system')
const activeComponent = computed(() => tabComponents[activeTab.value] || tabComponents.system)
</script>
