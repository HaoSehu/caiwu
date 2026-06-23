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
        zh_CN: '系统设置',
        en_US: 'Settings',
      },
      permission: 'settings.manage',
    },
  },
];