import type { RouteRecordRaw } from 'vue-router';

export const productsRoutes: RouteRecordRaw[] = [
  {
    path: 'products',
    name: 'AdminProductCatalog',
    component: () => import('@/pages/products/index.vue'),
    meta: {
      title: {
        zh_CN: '商品目录',
        en_US: 'Products',
      },
      productTab: 'catalog',
      permission: 'product.list',
    },
  },
  {
    path: 'specs',
    name: 'AdminSpecs',
    component: () => import('@/pages/products/specs/index.vue'),
    meta: {
      title: {
        zh_CN: '规格管理',
        en_US: 'Specs',
      },
      permission: 'product.list',
    },
  },
  {
    path: 'cpu-models',
    name: 'AdminCpuModels',
    component: () => import('@/pages/products/cpu-models/index.vue'),
    meta: {
      title: {
        zh_CN: 'CPU型号管理',
        en_US: 'CPU Models',
      },
      permission: 'product.list',
    },
  },
  {
    path: 'products/traffic-packages',
    name: 'AdminTrafficPackages',
    component: () => import('@/pages/products/index.vue'),
    meta: {
      title: {
        zh_CN: '流量包管理',
        en_US: 'Traffic Packages',
      },
      productTab: 'traffic-packages',
      permission: 'product.list',
      hidden: true,
    },
  },
  {
    path: 'products/suppliers',
    name: 'AdminSuppliers',
    redirect: { path: '/admin/products', query: { tab: 'suppliers' } },
    meta: {
      title: {
        zh_CN: '提供商',
        en_US: 'Suppliers',
      },
      permission: 'supplier.list',
      hidden: true,
    },
  },
];
