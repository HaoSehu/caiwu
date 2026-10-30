import type { RouteRecordRaw } from 'vue-router';

import { AdminPermissions } from '@/constants/permissions';

export const productsRoutes: RouteRecordRaw[] = [
  {
    path: 'products',
    name: 'AdminProductCatalog',
    component: () => import('@/pages/products/index.vue'),
    meta: {
      title: {
        zh_CN: '商品目录',
        en_US: 'Product Catalog',
      },
      productTab: 'catalog',
      permission: AdminPermissions.PRODUCT_LIST,
      keepAliveName: 'AdminProducts',
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
      permission: AdminPermissions.PRODUCT_LIST,
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
      permission: AdminPermissions.PRODUCT_LIST,
    },
  },
  {
    path: 'products/create',
    name: 'AdminProductCreate',
    component: () => import('@/pages/products/edit.vue'),
    meta: {
      title: {
        zh_CN: '新增商品',
        en_US: 'Create Product',
      },
      permission: AdminPermissions.PRODUCT_LIST,
      hidden: true,
      keepAliveName: 'AdminProductForm',
    },
  },
  {
    path: 'products/:id/edit',
    name: 'AdminProductEdit',
    component: () => import('@/pages/products/edit.vue'),
    meta: {
      title: {
        zh_CN: '编辑商品',
        en_US: 'Edit Product',
      },
      permission: AdminPermissions.PRODUCT_LIST,
      hidden: true,
      keepAliveName: 'AdminProductForm',
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
      permission: AdminPermissions.PRODUCT_LIST,
      keepAliveName: 'AdminProducts',
    },
  },
  {
    path: 'products/suppliers',
    name: 'AdminSuppliers',
    component: () => import('@/pages/products/index.vue'),
    meta: {
      title: {
        zh_CN: '上游提供商',
        en_US: 'Suppliers',
      },
      productTab: 'suppliers',
      permission: AdminPermissions.SUPPLIER_LIST,
      keepAliveName: 'AdminProducts',
    },
  },
];
