import request from '@/utils/request'

export default {
  list: (params) => request.get('/admin/suppliers', { params }),
  summary: () => request.get('/admin/suppliers/summary'),
  balance: (id, config = {}) => request.get(`/admin/suppliers/${id}/balance`, config),
  products: (id, config = {}) => request.get(`/admin/suppliers/${id}/products`, config),
  batchConnectProducts: (id, data) => request.post(`/admin/suppliers/${id}/products/batch-connect`, data),
  productConfigTemplate: (supplierId, productId, config = {}) => (
    request.get(`/admin/suppliers/${supplierId}/products/${productId}/config-template`, config)
  ),
  detail: (id) => request.get(`/admin/suppliers/${id}`),
  create: (data) => request.post('/admin/suppliers', data),
  update: (id, data) => request.put(`/admin/suppliers/${id}`, data),
  delete: (id) => request.delete(`/admin/suppliers/${id}`),
  toggleStatus: (id) => request.post(`/admin/suppliers/${id}/toggle-status`),
}
