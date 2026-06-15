import { shallowRef } from 'vue';
import type { RouteRecordRaw } from 'vue-router';

import { ComponentGridIcon, CpuIcon, ShopIcon } from 'tdesign-icons-vue-next';

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
      icon: shallowRef(ShopIcon),
      productTab: 'catalog',
      permission: 'product.list',
    },
  },
  {
    path: 'products/catalog',
    redirect: '/admin/products',
    meta: {
      title: {
        zh_CN: '商品目录',
        en_US: 'Product Catalog',
      },
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
      icon: shallowRef(ComponentGridIcon),
      permission: 'product.manage',
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
      icon: shallowRef(CpuIcon),
      permission: 'product.manage',
    },
  },
  {
    path: 'products/traffic-packages',
    name: 'AdminTrafficPackages',
    component: () => import('@/pages/products/index.vue'),
    meta: {
      title: {
        zh_CN: '流量包',
        en_US: 'Traffic Packages',
      },
      icon: shallowRef(ComponentGridIcon),
      productTab: 'traffic-packages',
      permission: 'product.list',
    },
  },
  {
    path: 'products/suppliers',
    name: 'AdminSuppliers',
    component: () => import('@/pages/products/index.vue'),
    meta: {
      title: {
        zh_CN: '提供商',
        en_US: 'Suppliers',
      },
      icon: shallowRef(ComponentGridIcon),
      productTab: 'suppliers',
      permission: 'product.list',
    },
  },
];