import { defineStore } from 'pinia';

import { clientAuthApi } from '@/api/auth';
import { getClientToken, removeClientToken, setClientToken } from '@/app/runtime/session';
import { store } from '@/store';
import { useAccountPolicyStore } from '@/store/modules/account-policy';
import type { ClientAuthSessionPayload, ClientUserInfo } from '@/types/client';
import { localStorageKeys, removeLocalStorage } from '@/utils/storage';

const initUserInfo: ClientUserInfo = {
  name: '',
  roles: ['client'],
};

function normalizeUserInfo(raw: Record<string, unknown> = {}): ClientUserInfo {
  // 展示名以接口的 display_name 为准：后端已统一「昵称优先、真实姓名兜底」，
  // 前端各处只读 name，避免每个页面各自拼一遍 fallback 导致口径不一。
  const nickname = String(raw.nickname || '').trim();
  const displayName = String(raw.display_name || '').trim();
  const fallback = String(raw.name || raw.email || '').trim();

  // 账户策略随用户信息下发；统一在这里 hydrate，页面与弹窗只读 account-policy store。
  useAccountPolicyStore().hydrate(raw.account_policy);

  return {
    ...raw,
    name: displayName || nickname || fallback || '创欧云用户',
    nickname,
    roles: ['client'],
  };
}

// 在途的资料请求：并发调用 ensureUserInfo 时复用它，避免重复打 /v2/client/auth/info。
let pendingUserInfoRequest: Promise<ClientUserInfo> | null = null;

export const useUserStore = defineStore('user', {
  state: () => ({
    token: getClientToken() || '',
    profileHydrated: false,
    userInfo: { ...initUserInfo },
  }),
  getters: {
    roles: (state) => state.userInfo?.roles || ['client'],
    info: (state) => state.userInfo,
  },
  actions: {
    syncTokenFromSession() {
      this.token = getClientToken() || '';
      if (!this.token) {
        this.profileHydrated = false;
      }
    },
    clearLocalSession() {
      removeClientToken();
      removeLocalStorage(localStorageKeys.clientUser);
      this.token = '';
      this.profileHydrated = false;
      this.userInfo = { ...initUserInfo };
      pendingUserInfoRequest = null;
    },
    async clientLogin(loginData: Record<string, unknown>) {
      const res = await clientAuthApi.login(loginData);
      const payload = res.data as ClientAuthSessionPayload | undefined;
      const token = String(payload?.token || '');
      if (token) {
        setClientToken(token);
        this.token = token;
      }
      this.userInfo = normalizeUserInfo(payload?.user || {});
      this.profileHydrated = true;
      return res;
    },
    async clientLoginByCode(loginData: Record<string, unknown>) {
      const res = await clientAuthApi.loginByCode(loginData);
      const payload = res.data as ClientAuthSessionPayload | undefined;
      const token = String(payload?.token || '');
      if (token) {
        setClientToken(token);
        this.token = token;
      }
      this.userInfo = normalizeUserInfo(payload?.user || {});
      this.profileHydrated = true;
      return res;
    },
    async login(loginData: Record<string, unknown>) {
      return this.clientLogin(loginData);
    },
    async clientRegister(data: Record<string, unknown>) {
      const res = await clientAuthApi.register(data);
      const payload = res.data as ClientAuthSessionPayload | undefined;
      const token = String(payload?.token || '');
      if (token) {
        setClientToken(token);
        this.token = token;
      }
      this.userInfo = normalizeUserInfo(payload?.user || {});
      this.profileHydrated = true;
      return res;
    },
    async exchangeLoginAsCode(code: string) {
      const res = await clientAuthApi.exchangeLoginAsCode({ code });
      const token = String(res.data?.token || '');
      if (token) {
        setClientToken(token);
        this.token = token;
      }
      await this.getUserInfo();
      return res;
    },
    async getUserInfo() {
      const res = await clientAuthApi.info();
      this.userInfo = normalizeUserInfo(res.data || {});
      this.syncTokenFromSession();
      this.profileHydrated = true;
      return this.userInfo;
    },
    // 页面级复用入口：守卫已完成水合时直接返回本地资料，未水合时复用同一个在途请求。
    // 与 getUserInfo 的区别是「不强制刷新」，需要最新资料的场景仍应调用 getUserInfo。
    async ensureUserInfo() {
      if (this.profileHydrated) {
        return this.userInfo;
      }

      if (!pendingUserInfoRequest) {
        pendingUserInfoRequest = this.getUserInfo().finally(() => {
          pendingUserInfoRequest = null;
        });
      }

      return pendingUserInfoRequest;
    },
    async logout() {
      try {
        if (this.token) {
          await clientAuthApi.logout();
        }
      } catch {
        // 退出登录必须清除本地客户端会话，即使接口不可用。
      } finally {
        this.clearLocalSession();
      }
    },
  },
  persist: {
    key: 'client_user',
    pick: ['userInfo'],
  },
});

export function getUserStore() {
  return useUserStore(store);
}
