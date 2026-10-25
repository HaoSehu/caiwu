import type { RouteRecordRaw } from 'vue-router';

export const usersRoutes: RouteRecordRaw[] = [
  {
    path: 'users',
    name: 'AdminUsers',
    component: () => import('@/pages/users/index.vue'),
    meta: {
      title: {
        zh_CN: '用户列表',
        en_US: 'User List',
      },
      permission: 'user.list',
    },
  },
  {
    path: 'users/verification',
    name: 'AdminUserVerification',
    component: () => import('@/pages/users/verification/index.vue'),
    meta: {
      title: {
        zh_CN: '实名列表',
        en_US: 'Verification List',
      },
      permission: 'verification.list',
      verificationPane: 'list',
    },
  },
  {
    path: 'users/verification/manage',
    name: 'AdminUserVerificationManage',
    component: () => import('@/pages/users/verification/index.vue'),
    meta: {
      title: {
        zh_CN: '实名管理',
        en_US: 'Verification Settings',
      },
      permission: 'verification.list',
      verificationPane: 'manage',
    },
  },
  {
    path: 'users/:id',
    name: 'AdminUserDetail',
    component: () => import('@/pages/users/detail/index.vue'),
    meta: {
      title: {
        zh_CN: '用户详情',
        en_US: 'User Detail',
      },
      permission: 'user.detail',
      hidden: true,
    },
  },
  {
    path: 'users/:id/services/:serviceId',
    name: 'AdminUserServiceConsole',
    component: () => import('@/pages/users/service-console/index.vue'),
    meta: {
      title: {
        zh_CN: '服务控制台',
        en_US: 'Service Console',
      },
      permission: 'user.detail',
      hidden: true,
    },
  },
];
