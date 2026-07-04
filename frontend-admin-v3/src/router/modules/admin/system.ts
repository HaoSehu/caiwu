import type { RouteRecordRaw } from 'vue-router';

export const systemRoutes: RouteRecordRaw[] = [
  {
    path: 'logs',
    name: 'AdminLogs',
    component: () => import('@/pages/logs/index.vue'),
    meta: {
      title: {
        zh_CN: '日志中心',
        en_US: 'Logs',
      },
      permission: 'log.list',
      keepAlive: false,
    },
  },
  {
    path: 'system/staff',
    name: 'AdminStaff',
    component: () => import('@/pages/admin/system/staff/index.vue'),
    meta: {
      title: {
        zh_CN: '员工管理',
        en_US: 'Staff',
      },
      permission: 'staff.list',
    },
  },
  {
    path: 'system/roles',
    name: 'AdminRoles',
    component: () => import('@/pages/admin/system/roles/index.vue'),
    meta: {
      title: {
        zh_CN: '角色权限',
        en_US: 'Roles',
      },
      permission: 'role.list',
    },
  },
  {
    path: 'settings',
    name: 'AdminSettings',
    component: () => import('@/pages/settings/index.vue'),
    meta: {
      title: {
        zh_CN: '系统配置',
        en_US: 'System Settings',
      },
      permission: 'settings.view',
      settingsTab: 'system',
    },
  },
  {
    path: 'automation',
    name: 'AdminAutomation',
    component: () => import('@/pages/settings/index.vue'),
    meta: {
      title: {
        zh_CN: '自动化策略',
        en_US: 'Automation',
      },
      permission: 'schedule.view',
      settingsTab: 'automation',
    },
  },
  {
    path: 'payment',
    name: 'AdminPayment',
    redirect: '/admin/integration-plugins',
  },
  {
    path: 'integration-plugins',
    name: 'AdminIntegrationPlugins',
    component: () => import('@/pages/integration-plugins/index.vue'),
    meta: {
      title: {
        zh_CN: '插件管理',
        en_US: 'Plugins',
      },
      permission: 'integration_plugin.view',
    },
  },
  {
    path: 'referral-settings',
    name: 'AdminReferralSettings',
    component: () => import('@/pages/settings/index.vue'),
    meta: {
      title: {
        zh_CN: '推荐奖励',
        en_US: 'Referral Settings',
      },
      permission: 'settings.view',
      settingsTab: 'referral',
    },
  },
];
