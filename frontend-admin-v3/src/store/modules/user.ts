import { defineStore } from 'pinia';

import { adminAuthApi } from '@/api/auth';
import { getAdminToken, removeAdminToken, setAdminToken } from '@/app/runtime/session';
import { usePermissionStore } from '@/store';
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
      this.userInfo = {
        name: admin.nickname || admin.username || admin.email || '管理员',
        roles: role ? [role] : [],
        permissions: admin.permissions || [],
        ...admin,
      };
    },
    async getUserInfo() {
      const token = getAdminToken();
      if (!token) {
        throw new Error('未登录');
      }
      this.token = token;
      const res = (await adminAuthApi.info()) as Partial<UserInfo> & { permissions?: string[] };
      const role = typeof res.role === 'string' && res.role ? res.role : '';
      this.userInfo = {
        name: res.nickname || res.username || res.email || '管理员',
        roles: role ? [role] : [],
        permissions: res.permissions || [],
        ...res,
      };
    },
    async logout() {
      try {
        if (this.token) {
          await adminAuthApi.logout();
        }
      } catch {
        // 本地退出优先，接口失败不阻断清理 token。
      }
      this.token = '';
      this.userInfo = { ...InitUserInfo };
      removeAdminToken();
    },
  },
  persist: {
    afterHydrate: () => {
      const permissionStore = usePermissionStore();
      permissionStore.initRoutes();
    },
    key: 'user',
    pick: ['token'],
  },
});
