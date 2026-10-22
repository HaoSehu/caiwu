import { request } from '@/utils/request';

export interface AdminLoginPayload {
  username?: string;
  account?: string;
  password?: string;
  [key: string]: unknown;
}

export interface AdminProfilePayload {
  nickname?: string;
}

export interface AdminPasswordPayload {
  current_password: string;
  password: string;
  password_confirmation: string;
}

export interface AdminMfaChallengePayload {
  challenge_token: string;
  // 动态码或恢复码，二者长度不同但同一字段承载。
  code: string;
}

export interface AdminMfaEnablePayload {
  code: string;
}

export interface AdminMfaDisablePayload {
  password: string;
  code: string;
}

export const adminAuthApi = {
  login: (data: AdminLoginPayload) => request.post({ url: '/v2/admin/login', data }),
  // 双因素第二步：挑战凭证 + 动态码换正式令牌，两条登录路径共用同一接口层。
  verifyMfa: (data: AdminMfaChallengePayload) => request.post({ url: '/v2/admin/login/mfa', data }),
  info: () => request.get({ url: '/v2/admin/auth/info' }),
  updateProfile: (data: AdminProfilePayload) => request.put({ url: '/v2/admin/auth/profile', data }),
  updatePassword: (data: AdminPasswordPayload) => request.put({ url: '/v2/admin/auth/password', data }),
  mfaSetup: () => request.post({ url: '/v2/admin/auth/mfa/setup' }),
  mfaEnable: (data: AdminMfaEnablePayload) => request.post({ url: '/v2/admin/auth/mfa/enable', data }),
  mfaDisable: (data: AdminMfaDisablePayload) => request.post({ url: '/v2/admin/auth/mfa/disable', data }),
  logout: () => request.post({ url: '/v2/admin/auth/logout' }),
};
