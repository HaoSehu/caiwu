import { defineStore } from 'pinia';
import type { RouteRecordRaw } from 'vue-router';

import router, { fixedRouterList, homepageRouterList } from '@/router';
import { store } from '@/store';
import type { MenuRoute } from '@/types/interface';

function toMenuRoutes(routes: RouteRecordRaw[] = []): MenuRoute[] {
  return routes.map((route) => ({
    path: route.path,
    title: route.meta?.title as MenuRoute['title'],
    name: typeof route.name === 'string' ? route.name : String(route.name || ''),
    icon: route.meta?.icon as MenuRoute['icon'],
    redirect: typeof route.redirect === 'string' ? route.redirect : undefined,
    children: toMenuRoutes(route.children || []),
    meta: route.meta || {},
  }));
}

export const usePermissionStore = defineStore('permission', {
  state: () => ({
    whiteListRouters: ['/client/login', '/client/register', '/client/forgot-password', '/client/login-as'],
    routers: [],
    removeRoutes: [],
    asyncRoutes: [],
    routesBuilt: false,
  }),
  actions: {
    async initRoutes() {
      const clientRoute = ([...homepageRouterList, ...fixedRouterList, ...this.asyncRoutes] as RouteRecordRaw[]).find(
        (route: RouteRecordRaw) => route.path === '/client',
      );
      this.routers = toMenuRoutes(clientRoute?.children || []);
    },
    async buildAsyncRoutes() {
      try {
        this.asyncRoutes = [];
        this.routesBuilt = true;
        await this.initRoutes();
        return this.asyncRoutes;
      } catch (error) {
        // 不用 new Error(msg, { cause })：Error 的 cause 选项要 ES2022 运行时（Chrome 93 / Safari 15.4），
        // 超出仓库声明的浏览器基线。改成挂属性语义等价；一旦把 tsconfig 的 lib 按基线降到 ES2020，
        // 原写法就是这里的 tsc 挡点（TS2554），这样写才编得过。
        const wrapped = new Error("Can't build routes") as Error & { cause?: unknown };
        wrapped.cause = error;
        throw wrapped;
      }
    },
    async restoreRoutes() {
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
