import type { AxiosRequestConfig } from 'axios';

import type {
  ApiEnvelope,
  ClientAlipayAccount,
  ClientAuthSessionPayload,
  ClientNotificationPreferences,
  ClientUserInfo,
  ClientVerificationPayload,
} from '@/types/client';
import request from '@/utils/request';

type RequestConfig = AxiosRequestConfig & { silentError?: boolean; authBearer?: boolean };

const SILENT_ERROR_CONFIG: RequestConfig = { silentError: true };
// 已登录态下发"验证已绑定号码"验证码：公开发码路由需显式携带凭证供后端识别绑定归属
export const AUTH_BOUND_CODE_CONFIG: RequestConfig = { authBearer: true };

function getEnvelope<T>(url: string, config?: RequestConfig) {
  return request.get<ApiEnvelope<T>, ApiEnvelope<T>>(url, config);
}

function postEnvelope<T>(url: string, data?: Record<string, unknown>, config?: RequestConfig) {
  return request.post<ApiEnvelope<T>, ApiEnvelope<T>>(url, data, config);
}

function putEnvelope<T>(url: string, data?: Record<string, unknown>) {
  return request.put<ApiEnvelope<T>, ApiEnvelope<T>>(url, data);
}

/**
 * 验证码发送载荷。
 *
 * `purpose` 必填：后端据此决定校验分支——登录/重置类（login、reset_password）做账号枚举保护，
 * 绑定类（verify_bound_phone/verify_bound_email/change_email）校验目标必须等于当前已绑定值。
 * 把 purpose 设为必填是为了让 TS 拦住遗漏（历史上 register/forgot-password 的邮箱分支曾漏传）。
 */
export interface SendAccountCodePayload {
  purpose: string;
  phone?: string;
  email?: string;
  captcha?: unknown;
}

export const clientAuthApi = {
  login: (data: Record<string, unknown>) => postEnvelope<ClientAuthSessionPayload>('/v2/client/login', data),
  loginByCode: (data: Record<string, unknown>) =>
    postEnvelope<ClientAuthSessionPayload>('/v2/client/auth/login-by-code', data),
  register: (data: Record<string, unknown>) => postEnvelope<ClientAuthSessionPayload>('/v2/client/register', data),
  exchangeLoginAsCode: (data: Record<string, unknown>) =>
    postEnvelope<ClientAuthSessionPayload>('/v2/client/auth/login-as/exchange', data),
  captchaConfig: () => request.get('/v2/client/auth/captcha-config'),
  info: () => getEnvelope<ClientUserInfo>('/v2/client/auth/info'),
  updateProfile: (data: Record<string, unknown>) => request.put('/v2/client/auth/profile', data),
  changePassword: (data: Record<string, unknown>) => request.put('/v2/client/password', data),
  updatePhone: (data: Record<string, unknown>) => request.put('/v2/client/auth/phone', data),
  updateEmail: (data: Record<string, unknown>) => request.put('/v2/client/auth/email', data),
  alipayAccount: () => getEnvelope<ClientAlipayAccount>('/v2/client/auth/alipay-account'),
  updateAlipayAccount: (data: Record<string, unknown>) =>
    putEnvelope<ClientAlipayAccount>('/v2/client/auth/alipay-account', data),
  initVerification: (data: Record<string, unknown>) =>
    postEnvelope<ClientVerificationPayload>('/v2/client/verification/init', data, SILENT_ERROR_CONFIG),
  verificationQrcode: (data: Record<string, unknown>) =>
    postEnvelope<ClientVerificationPayload>('/v2/client/verification/qrcode', data, SILENT_ERROR_CONFIG),
  closeVerificationSession: (data: Record<string, unknown>) =>
    postEnvelope<ClientVerificationPayload>('/v2/client/verification/close', data, SILENT_ERROR_CONFIG),
  verificationStatus: (params?: Record<string, unknown>) =>
    getEnvelope<ClientVerificationPayload>('/v2/client/verification/status', { params, ...SILENT_ERROR_CONFIG }),
  restartVerification: () =>
    postEnvelope<ClientVerificationPayload>('/v2/client/verification/restart', undefined, SILENT_ERROR_CONFIG),
  verificationFeeConfig: () => request.get('/v2/client/verification/fee-config'),
  notificationPreferences: () => getEnvelope<ClientNotificationPreferences>('/v2/client/auth/notification-preferences'),
  updateNotificationPreferences: (data: Record<string, unknown>) =>
    putEnvelope<ClientNotificationPreferences>('/v2/client/auth/notification-preferences', data),
  sendPhoneCode: (data: SendAccountCodePayload, config?: RequestConfig) =>
    request.post('/v2/client/auth/phone-code', data, config),
  sendEmailCode: (data: SendAccountCodePayload, config?: RequestConfig) =>
    request.post('/v2/client/auth/email-code', data, config),
  verifyBoundPhoneCode: (data: Record<string, unknown>) =>
    request.post('/v2/client/auth/phone/verify-bound-code', data),
  verifyBoundEmailCode: (data: Record<string, unknown>) =>
    request.post('/v2/client/auth/email/verify-bound-code', data),
  resetPassword: (data: Record<string, unknown>) => request.post('/v2/client/auth/reset-password', data),
  logout: () => request.post('/v2/client/auth/logout'),
};
