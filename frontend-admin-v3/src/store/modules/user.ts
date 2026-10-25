import { defineStore } from 'pinia';

import { adminAuthApi } from '@/api/auth';
import { getAdminToken, removeAdminToken, setAdminToken } from '@/app/runtime/session';
import type { UserInfo } from '@/types/interface';

/** 登录结果：要么直接拿到令牌，要么停在双因素第二步。 */
export interface AdminLoginResult {
  mfaRequired: boolean;
  challengeToken: string;
}

interface AdminLoginResponse {
  token?: string;
  mfa_required?: boolean;
  challenge_token?: string;
  admin?: Partial<UserInfo> & { permissions?: string[] };
}

const InitUserInfo: UserInfo = {
  name: '', // 用户名，用于展示在页面右上角头像处
  roles: [], // 前端权限模型使用 如果使用请配置modules/permission-fe.ts使用
};

// 在途的资料请求：并发调用 ensureUserInfo 时复用它，避免重复打 /v2/admin/auth/info。
let pendingUserInfoRequest: Promise<UserInfo> | null = null;

// 刷新时复用本地资料的有效期上限：水合阶段求值一次，超过后本次刷新仍回源一次 /v2/admin/auth/info。
// 边界要说清：它只约束「刷新后能否沿用缓存」，不约束长驻标签页——页面不 reload 时 profileHydrated
// 一直是 true，期间的权限调整由后端 permission: 中间件兜底，前端菜单要等下次刷新或重新登录才更新。
const PROFILE_HYDRATION_TTL_MS = 5 * 60 * 1000;

export const useUserStore = defineStore('user', {
  state: () => ({
    token: getAdminToken() || '',
    // 是否已持有可用用户资料（登录响应、/v2/admin/auth/info 或 localStorage 水合三条来源）。
    // 守卫据此决定刷新时能否直接用缓存权限重建菜单，不必再串行等一次 /auth/info。
    profileHydrated: false,
    // 最近一次拿到完整用户资料（登录响应 / auth/info / localStorage 水合）的时间戳，0 表示未知。
    profileSyncedAt: 0,
    userInfo: { ...InitUserInfo },
  }),
  getters: {
    roles: (state) => {
      return state.userInfo?.roles;
    },
  },
  actions: {
    async login(userInfo: Record<string, unknown>): Promise<AdminLoginResult> {
      const username = String(userInfo.username || userInfo.account || '');
      const payload = {
        ...userInfo,
        username,
      };
      const res = (await adminAuthApi.login(payload)) as AdminLoginResponse;

      // 启用双因素时后端不签令牌，只发 5 分钟一次性挑战凭证。
      // 此时绝不能写入任何会话状态，否则刷新页面会带着空 token 进入受保护路由。
      if (res.mfa_required) {
        removeAdminToken();
        this.token = '';
        this.profileHydrated = false;
        this.userInfo = { ...InitUserInfo };
        pendingUserInfoRequest = null;
        return { mfaRequired: true, challengeToken: String(res.challenge_token || '') };
      }

      this.applySession(res);
      return { mfaRequired: false, challengeToken: '' };
    },
    // 双因素第二步：用挑战凭证 + 动态码换令牌，成功后才落会话。
    async completeMfaLogin(challengeToken: string, code: string): Promise<void> {
      const res = (await adminAuthApi.verifyMfa({ challenge_token: challengeToken, code })) as AdminLoginResponse;
      this.applySession(res);
    },
    // 登录成功后的会话落地（账号密码登录与双因素登录共用同一口径）。
    applySession(res: AdminLoginResponse) {
      const token = String(res.token || '');
      setAdminToken(token);
      this.token = token;
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
      // 登录响应本身已带完整资料，视为已水合，守卫无需再补一次 /auth/info。
      this.profileHydrated = true;
      this.profileSyncedAt = Date.now();
      pendingUserInfoRequest = null;
    },
    syncTokenFromSession() {
      this.token = getAdminToken() || '';
      // 令牌不存在时资料一律视为未水合，避免登出后残留的 userInfo 被守卫当成有效会话。
      if (!this.token) {
        this.profileHydrated = false;
      }
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
      // 请求在途期间可能已被登出或 401 清理：此时不得把资料标记为可用，
      // 否则会出现「无 token 但已水合」的矛盾状态，与 afterHydrate 的口径不一致。
      if (!this.token) {
        this.userInfo = { ...InitUserInfo };
        this.profileHydrated = false;
        this.profileSyncedAt = 0;
        throw new Error('登录状态已失效，请重新登录');
      }
      this.profileHydrated = true;
      this.profileSyncedAt = Date.now();
      return this.userInfo;
    },
    // 页面级复用入口：已水合时直接返回本地资料，未水合时复用同一个在途请求。
    // 与 getUserInfo 的区别是「不强制刷新」，需要最新资料的场景（如个人资料页）仍应调用 getUserInfo。
    async ensureUserInfo(): Promise<UserInfo> {
      if (this.profileHydrated && this.userInfo?.name) {
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
          await adminAuthApi.logout();
        }
      } catch {
        // 本地退出优先，接口失败不阻断清理 token。
      } finally {
        this.token = '';
        this.profileHydrated = false;
        this.profileSyncedAt = 0;
        this.userInfo = { ...InitUserInfo };
        pendingUserInfoRequest = null;
        removeAdminToken();
      }
    },
    // 仅清理本地会话（token + userInfo），不调用后端 logout 接口。
    // 用于落地登录页时清理已失效的残留会话，避免误触发后端调用。
    resetLocalSession() {
      this.token = '';
      this.profileHydrated = false;
      this.profileSyncedAt = 0;
      this.userInfo = { ...InitUserInfo };
      pendingUserInfoRequest = null;
      removeAdminToken();
    },
  },
  persist: {
    key: 'user',
    // token 单一由 Cookie/session 管理（setAdminToken），避免与 localStorage 双存储不同步。
    // 仅持久化 userInfo，减少刷新后重复请求 getUserInfo。
    // 菜单不在 hydration 阶段构建：此时没有权限数据，只会得到空菜单，
    // 交由 permission.ts 守卫在拿到 userInfo 后调用 permissionStore.setupMenus 构建。
    pick: ['userInfo', 'profileSyncedAt'],
    // 水合完成后显式标记「已持有可用资料」：刷新时守卫据此跳过 /v2/admin/auth/info，
    // 直接用缓存权限重建菜单。仍以 Cookie 中的 token 为准，无 token 时不得标记为已水合；
    // 超过 PROFILE_HYDRATION_TTL_MS 的旧资料同样视为未水合，保证权限变更能在有限时间内生效。
    afterHydrate: ({ store }) => {
      const { userInfo, profileSyncedAt } = store.$state;
      const fresh = typeof profileSyncedAt === 'number' && Date.now() - profileSyncedAt < PROFILE_HYDRATION_TTL_MS;
      // 此处处于插件的 hydrateStore 阶段（$subscribe 尚未建立），直接写 $state 即可；
      // profileHydrated 不在 pick 中，本就不会被写回 localStorage。
      store.$state.profileHydrated = Boolean(getAdminToken() && userInfo?.name && fresh);
    },
  },
});
