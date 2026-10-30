import type { RouteRecordRaw } from 'vue-router';

import { AdminPermissions } from '@/constants/permissions';

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
      permission: AdminPermissions.MEMBER_LEVEL_LIST,
    },
  },
  {
    path: 'promotion-ambassadors',
    name: 'AdminPromotionAmbassadors',
    component: () => import('@/pages/promotion-ambassadors/index.vue'),
    meta: {
      title: {
        zh_CN: '推广大使',
        en_US: 'Promotion Ambassadors',
      },
      permission: AdminPermissions.PROMOTION_AMBASSADOR_LIST,
    },
  },
  {
    path: 'marketing-groups',
    name: 'AdminMarketingGroups',
    component: () => import('@/pages/marketing-groups/index.vue'),
    meta: {
      title: {
        zh_CN: '产品营销组',
        en_US: 'Marketing Product Groups',
      },
      permission: AdminPermissions.PRODUCT_LIST,
    },
  },
  {
    path: 'coupons',
    name: 'AdminCoupons',
    component: () => import('@/pages/products/coupons/index.vue'),
    meta: {
      title: {
        zh_CN: '优惠券列表',
        en_US: 'Coupons',
      },
      permission: AdminPermissions.COUPON_LIST,
      couponTab: 'coupons',
      keepAliveName: 'AdminCouponList',
    },
  },
  {
    path: 'coupon-campaigns',
    name: 'AdminCouponCampaigns',
    component: () => import('@/pages/products/coupons/index.vue'),
    meta: {
      title: {
        zh_CN: '活动券管理',
        en_US: 'Coupon Campaigns',
      },
      permission: AdminPermissions.COUPON_LIST,
      couponTab: 'campaigns',
      keepAliveName: 'AdminCouponList',
    },
  },
  {
    path: 'referral',
    name: 'AdminReferral',
    component: () => import('@/pages/referral/index.vue'),
    meta: {
      title: {
        zh_CN: '推广概览',
        en_US: 'Referral Overview',
      },
      permission: AdminPermissions.REFERRAL_LIST,
      referralTab: 'overview',
      keepAliveName: 'AdminReferral',
    },
  },
  {
    path: 'referral/rewards',
    name: 'AdminReferralRewards',
    component: () => import('@/pages/referral/index.vue'),
    meta: {
      title: {
        zh_CN: '奖励记录',
        en_US: 'Referral Rewards',
      },
      permission: AdminPermissions.REFERRAL_LIST,
      referralTab: 'rewards',
      keepAliveName: 'AdminReferral',
    },
  },
  {
    path: 'referral/withdrawals',
    name: 'AdminReferralWithdrawals',
    component: () => import('@/pages/referral/index.vue'),
    meta: {
      title: {
        zh_CN: '提现审核',
        en_US: 'Referral Withdrawals',
      },
      permission: AdminPermissions.REFERRAL_WITHDRAWAL_LIST,
      referralTab: 'withdrawals',
      keepAliveName: 'AdminReferral',
    },
  },
];
