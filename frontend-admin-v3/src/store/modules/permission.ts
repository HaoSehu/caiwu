import { cloneDeep } from 'lodash-es';
import { defineStore } from 'pinia';
import {
  ArticleIcon,
  DashboardIcon,
  FileIcon,
  GiftIcon,
  ServerIcon,
  ShopIcon,
  ToolsIcon,
  UserCircleIcon,
} from 'tdesign-icons-vue-next';
import { shallowRef } from 'vue';
import type { RouteRecordRaw } from 'vue-router';

import router, { fixedRouterList, homepageRouterList } from '@/router';
import { store } from '@/store';
import { hasPermissionInList } from '@/constants/permissions';
import type { LocalizedTitle } from '@/locales';

interface MenuChildConfig {
  path: string;
  title?: LocalizedTitle;
  showWhenHidden?: boolean;
}

type MenuChild = string | MenuChildConfig;

interface MenuGroupConfig {
  path: string;
  title: LocalizedTitle;
  icon: ReturnType<typeof shallowRef>;
  orderNo: number;
  children: MenuChild[];
}

const ADMIN_MENU_GROUPS: MenuGroupConfig[] = [
  {
    path: '/admin/menu/analytics',
    title: { zh_CN: '经营分析', en_US: 'Analytics' },
    icon: shallowRef(DashboardIcon),
    orderNo: 0,
    children: [
      '/admin/dashboard',
      {
        path: '/admin/finance/new-customers',
        title: { zh_CN: '新客户日报', en_US: 'New Customer Daily' },
        showWhenHidden: true,
      },
    ],
  },
  {
    path: '/admin/menu/customer-identity',
    title: { zh_CN: '客户与身份', en_US: 'Customers & Identity' },
    icon: shallowRef(UserCircleIcon),
    orderNo: 10,
    children: ['/admin/users', '/admin/users/verification', '/admin/users/verification/manage'],
  },
  {
    path: '/admin/menu/product-supply',
    title: { zh_CN: '商品与供应', en_US: 'Products & Supply' },
    icon: shallowRef(ShopIcon),
    orderNo: 20,
    children: ['/admin/products', '/admin/products/traffic-packages', '/admin/products/suppliers', '/admin/specs', '/admin/cpu-models'],
  },
  {
    path: '/admin/menu/service-delivery',
    title: { zh_CN: '服务交付', en_US: 'Service Delivery' },
    icon: shallowRef(ServerIcon),
    orderNo: 30,
    children: [{ path: '/admin/services', title: { zh_CN: '服务实例', en_US: 'Service Instances' } }, '/admin/tickets'],
  },
  {
    path: '/admin/menu/finance',
    title: { zh_CN: '交易账务', en_US: 'Transactions & Billing' },
    icon: shallowRef(FileIcon),
    orderNo: 40,
    children: [
      '/admin/finance/orders',
      '/admin/finance/orders/normal',
      '/admin/finance/orders/renewals',
      '/admin/finance/orders/upgrades',
      '/admin/finance/invoices',
      { path: '/admin/finance/recharges', title: { zh_CN: '充值记录', en_US: 'Recharge Records' } },
    ],
  },
  {
    path: '/admin/menu/marketing-benefits',
    title: { zh_CN: '营销权益', en_US: 'Marketing & Benefits' },
    icon: shallowRef(GiftIcon),
    orderNo: 50,
    children: [
      '/admin/member-levels',
      '/admin/coupons',
      '/admin/coupon-campaigns',
      '/admin/referral',
      '/admin/referral/rewards',
      '/admin/referral/withdrawals',
      '/admin/referral-settings',
    ],
  },
  {
    path: '/admin/menu/site-content',
    title: { zh_CN: '站点内容', en_US: 'Site Content' },
    icon: shallowRef(ArticleIcon),
    orderNo: 60,
    children: [
      { path: '/admin/site-info', title: { zh_CN: '站点配置', en_US: 'Site Settings' } },
      { path: '/admin/site-hero', title: { zh_CN: '首页 Banner', en_US: 'Home Banner' } },
      '/admin/content/notices',
      '/admin/content/help',
      '/admin/content/media-library',
    ],
  },
  {
    path: '/admin/menu/notifications-api',
    title: { zh_CN: '通知与接口', en_US: 'Notifications & APIs' },
    icon: shallowRef(FileIcon),
    orderNo: 70,
    children: ['/admin/notifications', '/admin/notifications/sms-templates', '/admin/notifications/api-directory'],
  },
  {
    path: '/admin/menu/integration-channels',
    title: { zh_CN: '集成通道', en_US: 'Integration Channels' },
    icon: shallowRef(ToolsIcon),
    orderNo: 80,
    children: [
      '/admin/integration-plugins/captcha',
      '/admin/integration-plugins/verification',
      '/admin/integration-plugins/payment',
      '/admin/integration-plugins/mail',
      '/admin/integration-plugins/sms',
      '/admin/integration-plugins/upstream',
      '/admin/integration-plugins/addons',
    ],
  },
  {
    path: '/admin/menu/automation-audit',
    title: { zh_CN: '自动化审计', en_US: 'Automation & Audit' },
    icon: shallowRef(ToolsIcon),
    orderNo: 90,
    children: [
      '/admin/automation',
      '/admin/logs/system',
      '/admin/logs/runtime',
      '/admin/logs/admin-logins',
      '/admin/logs/api',
      '/admin/logs/sms',
      '/admin/logs/email',
      '/admin/logs/tasks',
      '/admin/logs/gateway',
      '/admin/logs/schedules',
      '/admin/logs/cleanup',
    ],
  },
  {
    path: '/admin/menu/org-permissions',
    title: { zh_CN: '组织权限', en_US: 'Organization & Permissions' },
    icon: shallowRef(UserCircleIcon),
    orderNo: 100,
    children: ['/admin/system/staff', '/admin/system/roles'],
  },
];

export const usePermissionStore = defineStore('permission', {
  state: () => ({
    whiteListRouters: ['/admin/login'],
    routers: [],
    removeRoutes: [],
    asyncRoutes: [],
    routesBuilt: false,
  }),
  actions: {
    async initRoutes(permissions: string[] = []) {
      const accessedRouters = this.asyncRoutes;

      // 菜单展示业务分组；真实路由仍使用 homepageRouterList 注册，不改变历史路径。
      this.routers = buildGroupedMenuRouters(cloneDeep([...homepageRouterList, ...accessedRouters]), permissions);
      // 在菜单只展示动态路由和首页
      // this.routers = [...homepageRouterList, ...accessedRouters];
      // 在菜单只展示动态路由
      // this.routers = [...accessedRouters];
    },
    async buildAsyncRoutes(permissions: string[] = []) {
      try {
        this.asyncRoutes = [];
        this.routesBuilt = true;
        await this.initRoutes(permissions);
        return this.asyncRoutes;
      } catch (error) {
      throw new Error("Can't build routes", { cause: error });
      }
    },
    async restoreRoutes() {
      // 不需要在此额外调用initRoutes更新侧边导肮内容，在登录后asyncRoutes为空会调用
      this.asyncRoutes.forEach((item: RouteRecordRaw) => {
        if (item.name) {
          router.removeRoute(item.name);
        }
      });
      this.asyncRoutes = [];
      this.routesBuilt = false;
    },
  },
});

export function getPermissionStore() {
  return usePermissionStore(store);
}

function buildGroupedMenuRouters(routes: RouteRecordRaw[], permissions: string[]) {
  return routes.flatMap((route) => {
    if (route.path !== '/admin') return route;

    const children = Array.isArray(route.children) ? route.children : [];
    return ADMIN_MENU_GROUPS.map((group) => buildMenuGroup(group, children, permissions)).filter(
      (group): group is RouteRecordRaw => Boolean(group?.children?.length),
    );
  });
}

function buildMenuGroup(group: MenuGroupConfig, adminChildren: RouteRecordRaw[], permissions: string[]): RouteRecordRaw | null {
  const routeMap = new Map<string, RouteRecordRaw>();
  adminChildren.forEach((child) => {
    const fullPath = resolveAdminChildPath(child.path);
    routeMap.set(fullPath, {
      ...child,
      path: fullPath,
    });
  });

  const groupChildren = group.children
    .map((child) => {
      const childConfig = normalizeMenuChild(child);
      const route = routeMap.get(childConfig.path);

      if (!route || !canAccessMenuRoute(route, permissions)) {
        return null;
      }

      return applyMenuChildOverrides(route, childConfig);
    })
    .filter((item): item is RouteRecordRaw => Boolean(item));
  const firstChild = groupChildren[0];

  if (!firstChild) {
    return null;
  }

  return {
    path: group.path,
    redirect: firstChild.path,
    meta: {
      title: group.title,
      icon: group.icon,
      orderNo: group.orderNo,
    },
    children: groupChildren,
  };
}

function normalizeMenuChild(child: MenuChild): MenuChildConfig {
  return typeof child === 'string' ? { path: child } : child;
}

function applyMenuChildOverrides(route: RouteRecordRaw, config: MenuChildConfig): RouteRecordRaw {
  if (!config.title && !config.showWhenHidden) {
    return route;
  }

  return {
    ...route,
    meta: {
      ...route.meta,
      ...(config.title ? { title: config.title } : {}),
      ...(config.showWhenHidden ? { hidden: false } : {}),
    },
  };
}

function resolveAdminChildPath(path: RouteRecordRaw['path']) {
  const rawPath = String(path);
  if (rawPath.startsWith('/')) return rawPath;
  return `/admin/${rawPath}`.replace(/\/+/g, '/');
}

function canAccessMenuRoute(route: RouteRecordRaw, permissions: string[]) {
  const permission = route.meta?.permission;
  if (typeof permission !== 'string' || permission === '') return true;

  return hasPermissionInList(permissions, permission);
}
