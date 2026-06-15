import { defineStore } from 'pinia';

import { clientAuthApi } from '@/api/auth';
import { getClientToken, removeClientToken, setClientToken } from '@/app/runtime/session';
import { store } from '@/store';

interface ClientUserInfo {
  id?: number | string;
  name: string;
  nickname?: string;
  email?: string;
  balance?: string | number;
  roles: string[];
  [key: string]: unknown;
}

const initUserInfo: ClientUserInfo = {
  name: '',
  roles: ['client'],
};

function normalizeUserInfo(raw: Record<string, unknown> = {}): ClientUserInfo {
  const nickname = String(raw.nickname || raw.name || raw.username || raw.email || '').trim();
  return {
    ...raw,
    name: nickname || '创欧云用户',
    nickname,
    roles: ['client'],
  };
}

export const useUserStore = defineStore('user', {
  state: () => ({
    token: getClientToken() || '',
    userInfo: { ...initUserInfo },
  }),
  getters: {
    roles: (state) => state.userInfo?.roles || ['client'],
    info: (state) => state.userInfo,
  },
  actions: {
    syncTokenFromSession() {
      this.token = getClientToken() || '';
    },
    async clientLogin(loginData: Record<string, unknown>) {
      const res = await clientAuthApi.login(loginData);
      const token = String((res as any).data?.token || '');
      if (token) {
        setClientToken(token);
        this.token = token;
      }
      this.userInfo = normalizeUserInfo((res as any).data?.user || {});
      return res;
    },
    async login(loginData: Record<string, unknown>) {
      return this.clientLogin(loginData);
    },
    async clientRegister(data: Record<string, unknown>) {
      const res = await clientAuthApi.register(data);
      const token = String((res as any).data?.token || '');
      if (token) {
        setClientToken(token);
        this.token = token;
      }
      this.userInfo = normalizeUserInfo((res as any).data?.user || {});
      return res;
    },
    async exchangeLoginAsCode(code: string) {
      const res = await clientAuthApi.exchangeLoginAsCode({ code });
      const token = String((res as any).data?.token || '');
      if (token) {
        setClientToken(token);
        this.token = token;
      }
      await this.getUserInfo();
      return res;
    },
    async getUserInfo() {
      const res = await clientAuthApi.info();
      this.userInfo = normalizeUserInfo((res as any).data || {});
      this.syncTokenFromSession();
      return this.userInfo;
    },
    async logout() {
      try {
        if (this.token) {
          await clientAuthApi.logout();
        }
      } catch {
        // Logout must always clear the local client session even if the API is unavailable.
      } finally {
        removeClientToken();
        this.token = '';
        this.userInfo = { ...initUserInfo };
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
