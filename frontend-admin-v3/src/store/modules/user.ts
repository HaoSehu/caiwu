import { defineStore } from 'pinia';

import { adminAuthApi } from '@/api/auth';
import { getAdminToken, removeAdminToken, setAdminToken } from '@/app/runtime/session';
import type { UserInfo } from '@/types/interface';

const InitUserInfo: UserInfo = {
  name: '', // 用户名，用于展示在页面右上角头像处
  roles: [], // 前端权限模型使用 如果使用请配置modules/permission-fe.ts使用
};

export const useUserStore = defineStore('user', {
  state: () => ({
    token: getAdminToken() || '',
    userInfo: { ...InitUserInfo },
  }),
  getters: {
    roles: (state) => {
      return state.userInfo?.roles;
    },
  },
  actions: {
    async login(userInfo: Record<string, unknown>) {
      const username = String(userInfo.username || userInfo.account || '');
      const payload = {
        ...userInfo,
        username,
      };
      const res = (await adminAuthApi.login(payload)) as {
        token: string;
        admin?: Partial<UserInfo> & { permissions?: string[] };
      };
      setAdminToken(res.token);
      this.token = res.token;
      const admin = res.admin || {};
      const role = typeof admin.role === 'string' && admin.role ? admin.role : '';
      // 后端字段先展开，再由本地归一化字段覆盖：
      // 否则后端显式返回 permissions: undefined 时会把兜底的 [] 覆盖掉。
      this.userInfo = {
        ...admin,
        name: admin.nickname || admin.username || admin.email || '管理员',
        roles: role ? [role] : [],
        permissions: admin.permissions || [],
      };
    },
    syncTokenFromSession() {
      this.token = getAdminToken() || '';
    },
    async getUserInfo() {
      const token = getAdminToken();
      if (!token) {
        this.token = '';
        this.userInfo = { ...InitUserInfo };
        throw new Error('未登录');
      }
      this.token = token;
      const res = (await adminAuthApi.info()) as {
        admin?: Partial<UserInfo> & { permissions?: string[] };
      };
      const admin = res.admin || {};
      const role = typeof admin.role === 'string' && admin.role ? admin.role : '';
      this.userInfo = {
        ...admin,
        name: admin.nickname || admin.username || admin.email || '管理员',
        roles: role ? [role] : [],
        permissions: admin.permissions || [],
      };
      // 与 Cookie 中的最新 token 保持一致，避免 localStorage 残留旧值
      this.syncTokenFromSession();
      return this.userInfo;
    },
    async logout() {
      try {
        if (this.token) {
          await adminAuthApi.logout();
        }
      } catch {
        // 本地退出优先，接口失败不阻断清理 token。
      } finally {
        this.token = '';
        this.userInfo = { ...InitUserInfo };
        removeAdminToken();
      }
    },
    // 仅清理本地会话（token + userInfo），不调用后端 logout 接口。
    // 用于落地登录页时清理已失效的残留会话，避免误触发后端调用。
    resetLocalSession() {
      this.token = '';
      this.userInfo = { ...InitUserInfo };
      removeAdminToken();
    },
  },
  persist: {
    key: 'user',
    // token 单一由 Cookie/session 管理（setAdminToken），避免与 localStorage 双存储不同步。
    // 仅持久化 userInfo，减少刷新后重复请求 getUserInfo。
    // 菜单不在 hydration 阶段构建：此时没有权限数据，只会得到空菜单，
    // 交由 permission.ts 守卫在拿到 userInfo 后调用 permissionStore.setupMenus 构建。
    pick: ['userInfo'],
  },
});
