import { shallowRef } from 'vue';
import type { RouteRecordRaw } from 'vue-router';

import { ComponentGridIcon, UserIcon } from 'tdesign-icons-vue-next';

export const financeRoutes: RouteRecordRaw[] = [
  {
    path: 'orders',
    redirect: '/admin/finance/invoices',
    meta: {
      title: {
        zh_CN: '旧订单入口',
        en_US: 'Orders Redirect',
      },
    },
  },
  {
    path: 'finance/orders',
    name: 'AdminFinanceOrders',
    component: () => import('@/pages/finance/orders/index.vue'),
    meta: {
      title: {
        zh_CN: '订单管理',
        en_US: 'Orders',
      },
      icon: shallowRef(ComponentGridIcon),
      financeOrderMode: 'orders',
      permission: 'order.list',
    },
  },
  {
    path: 'finance/orders/:id',
    name: 'AdminFinanceOrderDetail',
    component: () => import('@/pages/finance/orders/detail/index.vue'),
    meta: {
      title: {
        zh_CN: '订单详情',
        en_US: 'Order Detail',
      },
      permission: 'order.detail',
    },
  },
  {
    path: 'finance/invoices',
    name: 'AdminFinanceInvoices',
    component: () => import('@/pages/finance/invoices/index.vue'),
    meta: {
      title: {
        zh_CN: '账单管理',
        en_US: 'Invoices',
      },
      icon: shallowRef(ComponentGridIcon),
      permission: 'invoice.list',
    },
  },
  {
    path: 'finance/recharges',
    name: 'AdminFinanceRecharges',
    component: () => import('@/pages/finance/recharges/index.vue'),
    meta: {
      title: {
        zh_CN: '充值管理',
        en_US: 'Recharges',
      },
      icon: shallowRef(ComponentGridIcon),
      permission: 'invoice.list',
    },
  },
  {
    path: 'finance/new-customers',
    name: 'AdminFinanceNewCustomers',
    component: () => import('@/pages/finance/new-customers/index.vue'),
    meta: {
      title: {
        zh_CN: '新客户',
        en_US: 'New Customers',
      },
      icon: shallowRef(UserIcon),
      permission: 'finance.report',
    },
  },
  {
    path: 'finance/renewals',
    name: 'AdminFinanceRenewals',
    component: () => import('@/pages/finance/orders/index.vue'),
    meta: {
      title: {
        zh_CN: '续费订单',
        en_US: 'Renewal Orders',
      },
      icon: shallowRef(ComponentGridIcon),
      financeOrderMode: 'renewals',
      permission: 'order.list',
    },
  },
  {
    path: 'finance/addons',
    name: 'AdminFinanceAddons',
    component: () => import('@/pages/finance/orders/index.vue'),
    meta: {
      title: {
        zh_CN: '附加配置订单',
        en_US: 'Addon Orders',
      },
      icon: shallowRef(ComponentGridIcon),
      financeOrderMode: 'addons',
      permission: 'order.list',
    },
  },
  {
    path: 'finance/product-income',
    redirect: '/admin/services',
    meta: {
      title: {
        zh_CN: '商品收入',
        en_US: 'Product Income Redirect',
      },
    },
  },
  {
    path: 'services',
    name: 'AdminServices',
    component: () => import('@/pages/services/index.vue'),
    meta: {
      title: {
        zh_CN: '服务列表',
        en_US: 'Services',
      },
      icon: shallowRef(ComponentGridIcon),
      permission: 'order.list',
    },
  },
];