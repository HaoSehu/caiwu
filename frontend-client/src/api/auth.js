import request from '@/utils/request'

// 管理员认证 API
export const adminAuthApi = {
  login:  (data)  => request.post('/admin/login', data),
  info:   ()      => request.get('/admin/auth/info'),
  logout: ()      => request.post('/admin/auth/logout'),
}

// 客户认证 API
export const clientAuthApi = {
  login:    (data) => request.post('/client/login', data),
  register: (data) => request.post('/client/register', data),
  exchangeLoginAsCode: (data) => request.post('/client/auth/login-as/exchange', data),
  captchaConfig: () => request.get('/client/auth/captcha-config'),
  info:     ()     => request.get('/client/auth/info'),
  alipayAccount: () => request.get('/client/auth/alipay-account'),
  updateAlipayAccount: (data) => request.put('/client/auth/alipay-account', data),
  notificationPreferences: () => request.get('/client/auth/notification-preferences'),
  updateNotificationPreferences: (data) => request.put('/client/auth/notification-preferences', data),
  sendPhoneCode: (data) => request.post('/client/auth/phone-code', data),
  sendEmailCode: (data) => request.post('/client/auth/email-code', data),
  resetPassword: (data) => request.post('/client/auth/reset-password', data),
  logout:   ()     => request.post('/client/auth/logout'),
}
