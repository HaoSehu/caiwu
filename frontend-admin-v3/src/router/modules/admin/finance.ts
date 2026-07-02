import type { RouteRecordRaw } from 'vue-router';

export const financeRoutes: RouteRecordRaw[] = [
  {
    path: 'finance/orders',
    name: 'AdminFinanceOrders',
    component: () => import('@/pages/finance/orders/index.vue'),
    meta: {
      title: {
        zh_CN: '订单管理',
        en_US: 'Orders',
      },
      permission: 'order.list',
      keepAlive: false,
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
      hidden: true,
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
      permission: 'invoice.list',
      keepAlive: false,
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
      permission: 'finance.report',
      hidden: true,
    },
  },
  {
    path: 'finance/renewals',
    name: 'AdminFinanceRenewals',
    redirect: { path: '/admin/finance/orders', query: { tab: 'renewals' } },
    meta: {
      title: {
        zh_CN: '续费订单',
        en_US: 'Renewal Orders',
      },
      permission: 'invoice.list',
      hidden: true,
    },
  },
  {
    path: 'finance/addons',
    name: 'AdminFinanceAddons',
    redirect: { path: '/admin/finance/orders', query: { tab: 'addons' } },
    meta: {
      title: {
        zh_CN: '附加配置订单',
        en_US: 'Addon Orders',
      },
      permission: 'invoice.list',
      hidden: true,
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
      permission: 'product.list',
    },
  },
];
