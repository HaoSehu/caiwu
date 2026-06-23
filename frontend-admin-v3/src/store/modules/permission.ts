import { cloneDeep } from 'lodash-es';
import { defineStore } from 'pinia';
import {
  ChatIcon,
  DashboardIcon,
  FileIcon,
  NotificationIcon,
  ShopIcon,
  ToolsIcon,
  UserCircleIcon,
} from 'tdesign-icons-vue-next';
import { shallowRef } from 'vue';
import type { RouteRecordRaw } from 'vue-router';

import router, { fixedRouterList, homepageRouterList } from '@/router';
import { store } from '@/store';

interface MenuGroupConfig {
  path: string;
  title: {
    zh_CN: string;
    en_US: string;
  };
  icon: ReturnType<typeof shallowRef>;
  orderNo: number;
  children: string[];
}

const ADMIN_MENU_GROUPS: MenuGroupConfig[] = [
  {
    path: '/admin/menu/workbench',
    title: { zh_CN: '工作台', en_US: 'Workbench' },
    icon: shallowRef(DashboardIcon),
    orderNo: 0,
    children: ['/admin/dashboard'],
  },
  {
    path: '/admin/menu/users',
    title: { zh_CN: '用户管理', en_US: 'Users' },
    icon: shallowRef(UserCircleIcon),
    orderNo: 10,
    children: ['/admin/users', '/admin/users/verification'],
  },
  {
    path: '/admin/menu/finance',
    title: { zh_CN: '财务管理', en_US: 'Finance' },
    icon: shallowRef(FileIcon),
    orderNo: 20,
    children: [
      '/admin/finance/invoices',
      '/admin/finance/orders',
      '/admin/finance/recharges',
      '/admin/finance/renewals',
      '/admin/finance/addons',
      '/admin/finance/new-customers',
      '/admin/services',
    ],
  },
  {
    path: '/admin/menu/products',
    title: { zh_CN: '商品与服务', en_US: 'Products & Services' },
    icon: shallowRef(ShopIcon),
    orderNo: 30,
    children: ['/admin/products', '/admin/products/traffic-packages', '/admin/products/suppliers', '/admin/specs', '/admin/cpu-models'],
  },
  {
    path: '/admin/menu/tickets',
    title: { zh_CN: '工单管理', en_US: 'Tickets' },
    icon: shallowRef(ChatIcon),
    orderNo: 40,
    children: ['/admin/tickets'],
  },
  {
    path: '/admin/menu/content',
    title: { zh_CN: '内容通知', en_US: 'Content' },
    icon: shallowRef(NotificationIcon),
    orderNo: 50,
    children: ['/admin/content/notices', '/admin/content/help', '/admin/notifications'],
  },
  {
    path: '/admin/menu/marketing',
    title: { zh_CN: '营销管理', en_US: 'Marketing' },
    icon: shallowRef(DashboardIcon),
    orderNo: 60,
    children: ['/admin/referral', '/admin/member-levels', '/admin/coupons', '/admin/coupon-campaigns'],
  },
  {
    path: '/admin/menu/system',
    title: { zh_CN: '系统管理', en_US: 'System' },
    icon: shallowRef(ToolsIcon),
    orderNo: 70,
    children: ['/admin/logs', '/admin/system/staff', '/admin/system/roles', '/admin/settings'],
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
    return ADMIN_MENU_GROUPS.map((group) => buildMenuGroup(group, children, permissions)).filter((group) => group.children?.length);
  });
}

function buildMenuGroup(group: MenuGroupConfig, adminChildren: RouteRecordRaw[], permissions: string[]): RouteRecordRaw {
  const routeMap = new Map<string, RouteRecordRaw>();
  adminChildren.forEach((child) => {
    const fullPath = resolveAdminChildPath(child.path);
    routeMap.set(fullPath, {
      ...child,
      path: fullPath,
    });
  });

  const groupChildren = group.children
    .map((path) => routeMap.get(path))
    .filter((item): item is RouteRecordRaw => Boolean(item) && canAccessMenuRoute(item, permissions));
  const firstChild = groupChildren[0];

  return {
    path: group.path,
    redirect: firstChild?.path,
    meta: {
      title: group.title,
      icon: group.icon,
      orderNo: group.orderNo,
    },
    children: groupChildren,
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

  return permissions.includes('*') || permissions.includes(permission);
}
