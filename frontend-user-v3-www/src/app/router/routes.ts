import { lazyRouteView } from '@caiwu/shared/runtime'
import type { RouteRecordRaw } from 'vue-router'

export const clientRoutes: RouteRecordRaw[] = [
  {
    path: '/',
    component: lazyRouteView(() => import('@/layout/WebsiteLayout.vue')),
    children: [
      {
        path: '',
        name: 'WwwHome',
        component: lazyRouteView(() => import('@/pages/website/home/index.vue')),
        meta: { title: '首页' },
      },
      {
        path: 'products',
        name: 'WwwProducts',
        component: lazyRouteView(() => import('@/pages/website/products/index.vue')),
        meta: { title: '产品与服务' },
      },
      {
        path: 'products/:typeId(\\d+)/:groupId(\\d+)/:childGroupId(\\d+)/:productId(\\d+)',
        name: 'WwwProductsPurchaseWithChild',
        component: lazyRouteView(() => import('@/pages/website/products/index.vue')),
        meta: { title: '产品与服务', noSitemap: true },
      },
      {
        path: 'products/:typeId(\\d+)/:groupId(\\d+)/:productId(\\d+)',
        name: 'WwwProductsPurchase',
        component: lazyRouteView(() => import('@/pages/website/products/index.vue')),
        meta: { title: '产品与服务', noSitemap: true },
      },
      {
        path: 'products/:id',
        name: 'WwwProductDetail',
        component: lazyRouteView(() => import('@/pages/website/ProductDetail/index.vue')),
        meta: { title: '商品详情' },
      },
      {
        path: 'about',
        name: 'WwwAbout',
        component: lazyRouteView(() => import('@/pages/website/about/index.vue')),
        meta: { title: '关于我们' },
      },
      {
        path: 'terms',
        name: 'WwwTerms',
        component: lazyRouteView(() => import('@/pages/website/legal-document/index.vue')),
        meta: { title: '服务条款', documentKey: 'terms' },
      },
      {
        path: 'privacy',
        name: 'WwwPrivacy',
        component: lazyRouteView(() => import('@/pages/website/legal-document/index.vue')),
        meta: { title: '隐私政策', documentKey: 'privacy' },
      },
      {
        path: 'notices',
        name: 'WwwNotices',
        component: lazyRouteView(() => import('@/pages/website/notices/index.vue')),
        meta: { title: '官方公告' },
      },
      {
        path: 'notices/:id',
        name: 'WwwNoticeDetail',
        component: lazyRouteView(() => import('@/pages/website/notice-detail/index.vue')),
        meta: { title: '公告详情' },
      },
      {
        path: 'help',
        name: 'WwwHelp',
        component: lazyRouteView(() => import('@/pages/website/help/index.vue')),
        meta: { title: '帮助中心' },
      },
      {
        path: 'help/:id',
        name: 'WwwHelpDetail',
        component: lazyRouteView(() => import('@/pages/website/help-detail/index.vue')),
        meta: { title: '帮助详情' },
      },
    ],
  },
  {
    path: '/:pathMatch(.*)*',
    name: 'WwwNotFound',
    component: lazyRouteView(() => import('@/pages/common/NotFound.vue')),
    meta: { title: '404', robots: 'noindex,nofollow' },
  },
]
