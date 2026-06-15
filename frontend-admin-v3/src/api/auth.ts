import { request } from '@/utils/request';

export interface AdminLoginPayload {
  username?: string;
  account?: string;
  password?: string;
  [key: string]: unknown;
}

export interface AdminProfilePayload {
  nickname?: string;
  email?: string;
}

export const adminAuthApi = {
  login: (data: AdminLoginPayload) => request.post({ url: '/admin/login', data }),
  info: () => request.get({ url: '/admin/auth/info' }),
  updateProfile: (data: AdminProfilePayload) => request.put({ url: '/admin/auth/profile', data }),
  logout: () => request.post({ url: '/admin/auth/logout' }),
};
