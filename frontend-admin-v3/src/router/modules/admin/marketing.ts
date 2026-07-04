import type { RouteRecordRaw } from 'vue-router';

export const marketingRoutes: RouteRecordRaw[] = [
  {
    path: 'member-levels',
    name: 'AdminMemberLevels',
    component: () => import('@/pages/member-levels/index.vue'),
    meta: {
      title: {
        zh_CN: '会员等级',
        en_US: 'Member Levels',
      },
      permission: 'member_level.list',
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
      permission: 'product.list',
    },
  },
  {
    path: 'coupon-campaigns',
    name: 'AdminCouponCampaigns',
    redirect: { path: '/admin/coupons', query: { tab: 'campaigns' } },
    meta: {
      title: {
        zh_CN: '活动券',
        en_US: 'Coupon Campaigns',
      },
      permission: 'product.list',
      hidden: true,
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
      permission: 'referral.list',
    },
  },
];
