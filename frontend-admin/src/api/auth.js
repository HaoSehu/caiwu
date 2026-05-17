import request from '@/utils/request'

// 管理员认证 API
export const adminAuthApi = {
  login:  (data)  => request.post('/admin/login', data),
  info:   ()      => request.get('/admin/auth/info'),
  updateProfile: (data) => request.put('/admin/auth/profile', data),
  logout: ()      => request.post('/admin/auth/logout'),
}

// 客户认证 API
export const clientAuthApi = {
  login:    (data) => request.post('/client/login', data),
  register: (data) => request.post('/client/register', data),
  info:     ()     => request.get('/client/auth/info'),
  logout:   ()     => request.post('/client/auth/logout'),
}
