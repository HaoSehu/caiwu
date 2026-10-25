import 'nprogress/nprogress.css'; // progress bar style

import NProgress from 'nprogress'; // progress bar
import { MessagePlugin } from 'tdesign-vue-next/es/message/plugin';

import { hasPermissionInList } from '@/constants/permissions';
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
  // 始终以 Cookie/session 中的 token 为准，避免 Pinia state 与 Cookie 不同步
  userStore.syncTokenFromSession();
  const token = userStore.token;

  if (token) {
    if (to.path === '/admin/login') {
      // 已登录访问登录页：放行（afterEach 会在落地后清理旧会话）
      next();
      return;
    }
    try {
      // 仅在菜单未构建时准备用户信息，避免每次导航都请求接口
      // （刷新或首次登录后 menuBuilt=false；后续跳转 menuBuilt=true 直接复用缓存）
      if (!permissionStore.menuBuilt || !userStore.userInfo?.name) {
        // 刷新后 userInfo 已由 pinia 持久化恢复（profileHydrated=true），可直接用缓存权限重建菜单；
        // 只有资料尚未水合（首次登录、清缓存、换账号）时才补一次 /v2/admin/auth/info。
        // 这样刷新不再串行等待该接口，同时又保留了「无有效资料必须回源」的兜底。
        if (!userStore.profileHydrated || !userStore.userInfo?.name) {
          await userStore.ensureUserInfo();
        }
        // 管理端路由为静态注册，此处只按权限重建菜单；页面级鉴权由下方 meta.permission 校验承担
        await permissionStore.setupMenus(userStore.userInfo?.permissions || []);

        if (to.name === PAGE_NOT_FOUND_ROUTE.name) {
          // 动态添加路由后，此处应当重定向到fullPath，否则会加载404页面内容
          next({ path: to.fullPath, replace: true, query: to.query });
        } else {
          let redirect = to.path;
          try {
            redirect = decodeURIComponent((from.query.redirect || to.path) as string);
          } catch {
            redirect = to.path;
          }
          // 仅接受站内绝对路径，拒绝 //evil.com 协议相对形态
          if (!redirect.startsWith('/') || redirect.startsWith('//')) {
            redirect = to.path;
          }
          next(to.path === redirect ? { ...to, replace: true } : { path: redirect, query: to.query });
        }
        return;
      }

      // 权限校验：检查路由 meta.permission
      const requiredPermission = to.meta?.permission as string | undefined;
      if (requiredPermission && requiredPermission !== '') {
        const userPermissions = userStore.userInfo?.permissions || [];
        const hasPermission = hasPermissionInList(userPermissions, requiredPermission);
        if (!hasPermission) {
          MessagePlugin.warning('您没有访问该页面的权限');
          next({ path: '/admin/403', replace: true });
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
      // getUserInfo 失败时仅跳转登录页，不在此处清除 token，
      // 避免 401/网络抖动等瞬时错误被放大为强制登出。
      // 真正的会话失效由登录页 onMounted 或用户主动登出处理。
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
  // 落地到登录页时，清理上一轮可能残留的动态路由与本地用户信息，
  // 但仅在确实没有有效 token 时才清除，避免误清正在使用的会话。
  if (to.path === '/admin/login') {
    const userStore = useUserStore();
    userStore.syncTokenFromSession();
    if (!userStore.token) {
      userStore.resetLocalSession();
    }
    const permissionStore = getPermissionStore();
    permissionStore.resetMenus();
  }
  NProgress.done();
});
