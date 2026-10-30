import { lazyRouteView } from '@caiwu/shared/runtime'
import { buildSeoLandingRouteMeta, seoLandingMetaPages } from '@/data/seoLandingMeta'
import type { RouteRecordRaw } from 'vue-router'

// slug → 落地页薄壳。每条只静态引入自己那份文案数据，
// 避免把 5 页全量文案塞进同一个 chunk（实测该数据块占 chunk gzip 的 50.3%）。
const seoLandingViews: Record<string, () => Promise<unknown>> = {
  'cloud-server': () => import('@/pages/website/seo-landing/pages/cloud-server.vue'),
  'hong-kong-server': () => import('@/pages/website/seo-landing/pages/hong-kong-server.vue'),
  'us-server': () => import('@/pages/website/seo-landing/pages/us-server.vue'),
  'high-defense-server': () => import('@/pages/website/seo-landing/pages/high-defense-server.vue'),
  'cloud-pc': () => import('@/pages/website/seo-landing/pages/cloud-pc.vue'),
}

// 落地页展示组件已改为必须接收 page prop（文案按 slug 拆分，见 src/data/seoLanding/）。
// 因此「meta 有这条 slug 但没配薄壳」不能回退到展示组件——那会以 undefined prop 渲染并抛错。
//
// 但也不能直接 throw：这份路由表在 main.js 的模块求值期就会跑完（bootstrap 之前），
// 一旦抛出，createApp 根本不会执行，#app-splash 会永久停留 = 全站白屏，比少一页严重得多。
// 因此开发期直接抛（第一时间暴露配置疏漏），生产期记错误并丢弃该条路由（只影响这一个落地页）。
function resolveSeoLandingView(page: { slug: string; path: string }) {
  const view = seoLandingViews[page.slug]

  if (view) return view

  const message =
    `SEO 落地页缺少薄壳组件：slug "${page.slug}"。请同时新增 ` +
    `src/pages/website/seo-landing/pages/${page.slug}.vue 与 src/data/seoLanding/${page.slug}.js`

  if (import.meta.env.DEV) {
    throw new Error(message)
  }

  console.error(`[router] ${message}（已跳过该落地页路由，避免整站白屏）`)
  return null
}

const seoLandingRoutes: RouteRecordRaw[] = seoLandingMetaPages.flatMap((page) => {
  const view = resolveSeoLandingView(page)
  if (!view) return []

  return [
    {
      path: page.path.replace(/^\/+/, ''),
      name: page.routeName,
      component: lazyRouteView(view),
      meta: buildSeoLandingRouteMeta(page),
    },
  ]
})

export const clientRoutes: RouteRecordRaw[] = [
  {
    path: '/',
    component: lazyRouteView(() => import('@/layout/WebsiteLayout.vue')),
    children: [
      {
        path: '',
        name: 'WwwHome',
        component: lazyRouteView(() => import('@/pages/website/home/index.vue')),
        meta: {
          // 首页标题由站点配置的 browser_title 动态提供，不硬编码品牌名
          title: '',
          description: '创欧云提供云服务器、独立服务器、云电脑与 IDC 服务，覆盖香港、美国与国内多地节点。',
          canonical: '/',
        },
      },
      {
        path: 'products',
        name: 'WwwProducts',
        component: lazyRouteView(() => import('@/pages/website/products/index.vue')),
        meta: {
          title: '产品与服务',
          description: '浏览创欧云云服务器、独立服务器、云电脑与 IDC 产品方案。',
          canonical: '/products',
        },
      },
      ...seoLandingRoutes,
      {
        path: 'products/:typeId/:groupId(\\d+)/:childGroupId(\\d+)/:productId(\\d+)',
        name: 'WwwProductsPurchaseWithChild',
        component: lazyRouteView(() => import('@/pages/website/products/index.vue')),
        meta: {
          title: '产品与服务',
          description: '确认产品规格与计费周期，进入控制台完成订单创建。',
          noSitemap: true,
          robots: 'noindex,nofollow',
        },
      },
      {
        path: 'products/:typeId/:groupId(\\d+)/:productId(\\d+)',
        name: 'WwwProductsPurchase',
        component: lazyRouteView(() => import('@/pages/website/products/index.vue')),
        meta: {
          title: '产品与服务',
          description: '确认产品规格与计费周期，进入控制台完成订单创建。',
          noSitemap: true,
          robots: 'noindex,nofollow',
        },
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
        meta: {
          title: '关于我们',
          description: '了解创欧云的 IDC 服务能力、节点覆盖和平台优势。',
          canonical: '/about',
        },
      },
      {
        path: 'terms',
        name: 'WwwTerms',
        component: lazyRouteView(() => import('@/pages/website/legal-document/index.vue')),
        meta: {
          title: '服务条款',
          description: '查看创欧云服务条款。',
          canonical: '/terms',
          documentKey: 'terms',
        },
      },
      {
        path: 'privacy',
        name: 'WwwPrivacy',
        component: lazyRouteView(() => import('@/pages/website/legal-document/index.vue')),
        meta: {
          title: '隐私政策',
          description: '查看创欧云隐私政策。',
          canonical: '/privacy',
          documentKey: 'privacy',
        },
      },
      {
        path: 'notices',
        name: 'WwwNotices',
        component: lazyRouteView(() => import('@/pages/website/notices/index.vue')),
        meta: {
          title: '官方公告',
          description: '查看创欧云平台公告和服务通知。',
          canonical: '/notices',
        },
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
        meta: {
          title: '帮助中心',
          description: '查看创欧云产品购买、账单支付和服务管理帮助。',
          canonical: '/help',
        },
      },
      {
        path: 'help/:id',
        name: 'WwwHelpDetail',
        component: lazyRouteView(() => import('@/pages/website/help-detail/index.vue')),
        meta: { title: '帮助详情' },
      },
      {
        // 404 兜底放在布局内，保留站头/页脚导航上下文
        path: ':pathMatch(.*)*',
        name: 'WwwNotFound',
        component: lazyRouteView(() => import('@/pages/common/NotFound.vue')),
        meta: {
          title: '404',
          description: '访问的页面不存在，请通过顶部导航或搜索返回创欧云首页。',
          robots: 'noindex,nofollow',
        },
      },
    ],
  },
]
