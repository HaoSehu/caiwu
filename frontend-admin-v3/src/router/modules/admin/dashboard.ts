import type { RouteRecordRaw } from 'vue-router';

import { AdminPermissions } from '@/constants/permissions';

export const dashboardRoutes: RouteRecordRaw[] = [
  {
    path: 'dashboard',
    name: 'DashboardBase',
    component: () => import('@/pages/dashboard/base/index.vue'),
    meta: {
      title: {
        zh_CN: '仪表盘',
        en_US: 'Dashboard',
      },
      permission: AdminPermissions.DASHBOARD_VIEW,
      keepAlive: false,
    },
  },
];
