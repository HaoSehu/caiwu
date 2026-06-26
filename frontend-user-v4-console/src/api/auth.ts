import request from '@/utils/request';
import type {
  ApiEnvelope,
  ClientAlipayAccount,
  ClientAuthSessionPayload,
  ClientNotificationPreferences,
  ClientUserInfo,
  ClientVerificationPayload,
} from '@/types/client';

function getEnvelope<T>(url: string, config?: Record<string, unknown>) {
  return request.get<ApiEnvelope<T>, ApiEnvelope<T>>(url, config);
}

function postEnvelope<T>(url: string, data?: Record<string, unknown>) {
  return request.post<ApiEnvelope<T>, ApiEnvelope<T>>(url, data);
}

function putEnvelope<T>(url: string, data?: Record<string, unknown>) {
  return request.put<ApiEnvelope<T>, ApiEnvelope<T>>(url, data);
}

export const clientAuthApi = {
  login: (data: Record<string, unknown>) => postEnvelope<ClientAuthSessionPayload>('/client/login', data),
  loginByCode: (data: Record<string, unknown>) => postEnvelope<ClientAuthSessionPayload>('/client/auth/login-by-code', data),
  register: (data: Record<string, unknown>) => postEnvelope<ClientAuthSessionPayload>('/client/register', data),
  exchangeLoginAsCode: (data: Record<string, unknown>) =>
    postEnvelope<ClientAuthSessionPayload>('/client/auth/login-as/exchange', data),
  captchaConfig: () => request.get('/client/auth/captcha-config'),
  info: () => getEnvelope<ClientUserInfo>('/client/auth/info'),
  updateProfile: (data: Record<string, unknown>) => request.put('/client/auth/profile', data),
  changePassword: (data: Record<string, unknown>) => request.put('/client/password', data),
  updatePhone: (data: Record<string, unknown>) => request.put('/client/auth/phone', data),
  updateEmail: (data: Record<string, unknown>) => request.put('/client/auth/email', data),
  alipayAccount: () => getEnvelope<ClientAlipayAccount>('/client/auth/alipay-account'),
  updateAlipayAccount: (data: Record<string, unknown>) => putEnvelope<ClientAlipayAccount>('/client/auth/alipay-account', data),
  initVerification: (data: Record<string, unknown>) => postEnvelope<ClientVerificationPayload>('/client/verification/init', data),
  verificationQrcode: (data: Record<string, unknown>) => postEnvelope<ClientVerificationPayload>('/client/verification/qrcode', data),
  verificationStatus: (params?: Record<string, unknown>) =>
    getEnvelope<ClientVerificationPayload>('/client/verification/status', { params }),
  restartVerification: () => postEnvelope<ClientVerificationPayload>('/client/verification/restart'),
  verificationFeeConfig: () => request.get('/client/verification/fee-config'),
  notificationPreferences: () => getEnvelope<ClientNotificationPreferences>('/client/auth/notification-preferences'),
  updateNotificationPreferences: (data: Record<string, unknown>) =>
    putEnvelope<ClientNotificationPreferences>('/client/auth/notification-preferences', data),
  sendPhoneCode: (data: Record<string, unknown>) => request.post('/client/auth/phone-code', data),
  sendEmailCode: (data: Record<string, unknown>) => request.post('/client/auth/email-code', data),
  resetPassword: (data: Record<string, unknown>) => request.post('/client/auth/reset-password', data),
  logout: () => request.post('/client/auth/logout'),
};
