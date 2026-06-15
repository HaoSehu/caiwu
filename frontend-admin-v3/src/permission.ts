import 'nprogress/nprogress.css'; // progress bar style

import NProgress from 'nprogress'; // progress bar
import { MessagePlugin } from 'tdesign-vue-next';
import type { RouteRecordRaw } from 'vue-router';

import router from '@/router';
import { getPermissionStore, useUserStore } from '@/store';
import { PAGE_NOT_FOUND_ROUTE } from '@/utils/route/constant';
import { toUserMessage } from '@/utils/userMessage';

NProgress.configure({ showSpinner: false });

router.beforeEach(async (to, from, next) => {
  NProgress.start();

  const permissionStore = getPermissionStore();
  const { whiteListRouters } = permissionStore;

  const userStore = useUserStore();

  if (userStore.token) {
    if (to.path === '/admin/login') {
      next();
      return;
    }
    try {
      await userStore.getUserInfo();

      const { routesBuilt } = permissionStore;

      if (!routesBuilt) {
        const routeList = await permissionStore.buildAsyncRoutes(userStore.userInfo?.permissions || []);
        routeList.forEach((item: RouteRecordRaw) => {
          router.addRoute(item);
        });

        if (to.name === PAGE_NOT_FOUND_ROUTE.name) {
          // 动态添加路由后，此处应当重定向到fullPath，否则会加载404页面内容
          next({ path: to.fullPath, replace: true, query: to.query });
        } else {
          const redirect = decodeURIComponent((from.query.redirect || to.path) as string);
          next(to.path === redirect ? { ...to, replace: true } : { path: redirect, query: to.query });
          return;
        }
      }

      // 权限校验：检查路由 meta.permission
      const requiredPermission = to.meta?.permission as string | undefined;
      if (requiredPermission && requiredPermission !== '') {
        const userPermissions = userStore.userInfo?.permissions || [];
        const hasPermission = userPermissions.includes('*') || userPermissions.includes(requiredPermission);
        if (!hasPermission) {
          MessagePlugin.warning('您没有访问该页面的权限');
          next({ path: '/admin/dashboard', replace: true });
          NProgress.done();
          return;
        }
      }

      if (router.hasRoute(to.name)) {
        next();
      } else {
        next(`/`);
      }
    } catch (error) {
      MessagePlugin.error(toUserMessage(error instanceof Error ? error.message : '', '登录状态已失效，请重新登录'));
      next({
        path: '/admin/login',
        query: { redirect: encodeURIComponent(to.fullPath) },
      });
      NProgress.done();
    }
  } else {
    /* white list router */
    if (whiteListRouters.includes(to.path)) {
      next();
    } else {
      next({
        path: '/admin/login',
        query: { redirect: encodeURIComponent(to.fullPath) },
      });
    }
    NProgress.done();
  }
});

router.afterEach((to) => {
  if (to.path === '/admin/login') {
    const userStore = useUserStore();
    const permissionStore = getPermissionStore();

    userStore.logout();
    permissionStore.restoreRoutes();
  }
  NProgress.done();
});
