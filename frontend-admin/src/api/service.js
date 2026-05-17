import request from '@/utils/request'

export default {
  list: (params) => request.get('/admin/services', { params }),
  batchUpdateCustomHostnames: (data) => request.post('/admin/services/custom-hostnames/batch', data),
}
