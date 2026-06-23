import request from '@/utils/request'

// 客户认证 API（www 入口仅用于恢复登录态与登出，登录/注册由控制台 v4-console 承担）
export const clientAuthApi = {
  info:   () => request.get('/client/auth/info'),
  logout: () => request.post('/client/auth/logout'),
}
