import type { RouteRecordRaw } from 'vue-router';

export const financeRoutes: RouteRecordRaw[] = [
  {
    path: 'orders',
    name: 'AdminOrdersRedirect',
    redirect: '/admin/finance/invoices',
    meta: {
      title: {
        zh_CN: '旧订单入口',
        en_US: 'Orders Redirect',
      },
      permission: 'invoice.list',
      hidden: true,
    },
  },
  {
    path: 'finance/orders',
    name: 'AdminFinanceOrders',
    component: () => import('@/pages/finance/orders/index.vue'),
    meta: {
      title: {
        zh_CN: '全部订单',
        en_US: 'All Orders',
      },
      permission: 'order.list',
      orderTab: 'all',
      keepAliveName: 'AdminFinanceOrders',
    },
  },
  {
    path: 'finance/orders/normal',
    name: 'AdminFinanceNormalOrders',
    component: () => import('@/pages/finance/orders/index.vue'),
    meta: {
      title: {
        zh_CN: '普通订单',
        en_US: 'Normal Orders',
      },
      permission: 'order.list',
      orderTab: 'orders',
      keepAliveName: 'AdminFinanceOrders',
    },
  },
  {
    path: 'finance/orders/renewals',
    name: 'AdminFinanceRenewalOrders',
    component: () => import('@/pages/finance/orders/index.vue'),
    meta: {
      title: {
        zh_CN: '续费订单',
        en_US: 'Renewal Orders',
      },
      permission: 'order.list',
      orderTab: 'renewals',
      keepAliveName: 'AdminFinanceOrders',
    },
  },
  {
    path: 'finance/orders/upgrades',
    name: 'AdminFinanceUpgradeOrders',
    component: () => import('@/pages/finance/orders/index.vue'),
    meta: {
      title: {
        zh_CN: '附加配置',
        en_US: 'Upgrade Orders',
      },
      permission: 'order.list',
      orderTab: 'upgrade',
      keepAliveName: 'AdminFinanceOrders',
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
    path: 'finance/invoices/:id',
    name: 'AdminFinanceInvoiceDetail',
    component: () => import('@/pages/finance/invoices/detail/index.vue'),
    meta: {
      title: {
        zh_CN: '账单详情',
        en_US: 'Invoice Detail',
      },
      permission: 'invoice.detail',
      hidden: true,
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
    path: 'finance/product-income',
    name: 'AdminFinanceProductIncomeRedirect',
    redirect: '/admin/services',
    meta: {
      title: {
        zh_CN: '商品收入',
        en_US: 'Product Income Redirect',
      },
      permission: 'finance.report',
      hidden: true,
    },
  },
  {
    path: 'finance/renewals',
    name: 'AdminFinanceRenewals',
    redirect: '/admin/finance/orders/renewals',
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
    path: 'finance/upgrades',
    name: 'AdminFinanceUpgrades',
    redirect: '/admin/finance/orders/upgrades',
    meta: {
      title: {
        zh_CN: '附加配置订单',
        en_US: 'Upgrade Orders',
      },
      permission: 'invoice.list',
      hidden: true,
    },
  },
  {
    path: 'sales-commissions',
    name: 'AdminSalesCommissionRecords',
    component: () => import('@/pages/sales-commissions/records/index.vue'),
    meta: {
      title: {
        zh_CN: '销售提成记录',
        en_US: 'Sales Commissions',
      },
      permission: 'sales_commission.list',
    },
  },
  {
    path: 'sales-commission-plans',
    name: 'AdminSalesCommissionPlans',
    component: () => import('@/pages/sales-commissions/plans/index.vue'),
    meta: {
      title: {
        zh_CN: '提成方案管理',
        en_US: 'Commission Plans',
      },
      permission: 'sales_commission.list',
    },
  },
  {
    path: 'sales-commission-settlements',
    name: 'AdminSalesCommissionSettlements',
    component: () => import('@/pages/sales-commissions/settlements/index.vue'),
    meta: {
      title: {
        zh_CN: '提成结算批次',
        en_US: 'Commission Settlements',
      },
      permission: 'sales_commission.list',
    },
  },
  {
    path: 'service-transfers',
    name: 'AdminServiceTransfers',
    component: () => import('@/pages/service-transfers/index.vue'),
    meta: {
      title: {
        zh_CN: '服务过户复核',
        en_US: 'Service Transfers',
      },
      permission: 'service_transfer.list',
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
