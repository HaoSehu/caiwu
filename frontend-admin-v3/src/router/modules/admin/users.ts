import { shallowRef } from 'vue';
import type { RouteRecordRaw } from 'vue-router';

import { UserIcon } from 'tdesign-icons-vue-next';

export const usersRoutes: RouteRecordRaw[] = [
  {
    path: 'users',
    name: 'AdminUsers',
    component: () => import('@/pages/users/index.vue'),
    meta: {
      title: {
        zh_CN: '用户管理',
        en_US: 'Users',
      },
      icon: shallowRef(UserIcon),
      permission: 'user.list',
    },
  },
  {
    path: 'users/verification',
    name: 'AdminUserVerification',
    component: () => import('@/pages/users/verification/index.vue'),
    meta: {
      title: {
        zh_CN: '实名管理',
        en_US: 'Verification',
      },
      permission: 'verification.list',
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
    },
  },
];