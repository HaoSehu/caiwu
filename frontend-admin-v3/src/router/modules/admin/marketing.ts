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
    path: 'promotion-ambassadors',
    name: 'AdminPromotionAmbassadors',
    component: () => import('@/pages/promotion-ambassadors/index.vue'),
    meta: {
      title: {
        zh_CN: '推广大使',
        en_US: 'Promotion Ambassadors',
      },
      permission: 'promotion_ambassador.list',
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
      permission: 'product.list',
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
      permission: 'product.list',
      couponTab: 'coupons',
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
      permission: 'product.list',
      couponTab: 'campaigns',
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
      permission: 'referral.list',
      referralTab: 'overview',
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
      permission: 'referral.list',
      referralTab: 'rewards',
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
      permission: 'referral_withdrawal.list',
      referralTab: 'withdrawals',
    },
  },
];
