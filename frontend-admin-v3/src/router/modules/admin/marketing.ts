import { shallowRef } from 'vue';
import type { RouteRecordRaw } from 'vue-router';

import { ComponentGridIcon } from 'tdesign-icons-vue-next';

export const marketingRoutes: RouteRecordRaw[] = [
  {
    path: 'growth',
    redirect: '/admin/member-levels',
    meta: {
      title: {
        zh_CN: '增长入口',
        en_US: 'Growth Redirect',
      },
    },
  },
  {
    path: 'member-levels',
    name: 'AdminMemberLevels',
    component: () => import('@/pages/member-levels/index.vue'),
    meta: {
      title: {
        zh_CN: '会员等级',
        en_US: 'Member Levels',
      },
      icon: shallowRef(ComponentGridIcon),
      permission: 'member_level.manage',
    },
  },
  {
    path: 'coupons',
    name: 'AdminCoupons',
    component: () => import('@/pages/products/coupons/index.vue'),
    meta: {
      title: {
        zh_CN: '优惠券',
        en_US: 'Coupons',
      },
      icon: shallowRef(ComponentGridIcon),
      permission: 'product.list',
    },
  },
  {
    path: 'coupon-campaigns',
    name: 'AdminCouponCampaigns',
    component: () => import('@/pages/products/coupon-campaigns/index.vue'),
    meta: {
      title: {
        zh_CN: '活动券',
        en_US: 'Coupon Campaigns',
      },
      icon: shallowRef(ComponentGridIcon),
      permission: 'product.list',
    },
  },
  {
    path: 'referral',
    name: 'AdminReferral',
    component: () => import('@/pages/referral/index.vue'),
    meta: {
      title: {
        zh_CN: '推广返利',
        en_US: 'Referral',
      },
      icon: shallowRef(ComponentGridIcon),
      permission: 'referral.list',
    },
  },
];