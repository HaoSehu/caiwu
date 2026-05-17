import request from '@/utils/request'

function normalizeSharedProductParams(payload = {}) {
  const next = { ...payload }

  if (next.type !== undefined && next.product_type === undefined) {
    next.product_type = next.type
    delete next.type
  }

  return next
}

function normalizeProductParams(payload = {}) {
  return normalizeSharedProductParams(payload)
}

function normalizeCategoryParams(payload = {}) {
  const next = normalizeSharedProductParams(payload)

  if (next.parent_category_id !== undefined && next.parent_id === undefined) {
    next.parent_id = next.parent_category_id
    delete next.parent_category_id
  }

  if (next.target_parent_category_id !== undefined && next.target_parent_id === undefined) {
    next.target_parent_id = next.target_parent_category_id
    delete next.target_parent_category_id
  }

  return next
}

export default {
  summary: () => request.get('/admin/products/summary'),
  list: (params) => request.get('/admin/products', { params: normalizeProductParams(params) }),
  detail: (id) => request.get(`/admin/products/${id}`),
  create: (data) => request.post('/admin/products', normalizeProductParams(data)),
  update: (id, data) => request.put(`/admin/products/${id}`, normalizeProductParams(data)),
  splitPreview: (data) => request.post('/admin/products/split-preview', normalizeProductParams(data)),
  splitProducts: (data) => request.post('/admin/products/split', normalizeProductParams(data)),
  batchUpdateProvisionHostname: (data) => request.post('/admin/products/provision-hostname/batch', data),
  batchUpdateCategory: (data) => request.post('/admin/products/category/batch', normalizeProductParams(data)),
  pullTrafficPackages: (data) => request.post('/admin/products/traffic-packages/pull', normalizeProductParams(data)),
  delete: (id) => request.delete(`/admin/products/${id}`),
  toggleStatus: (id) => request.post(`/admin/products/${id}/toggle-status`),

  types: () => request.get('/admin/product-types'),
  reorderTypes: (data) => request.post('/admin/product-types/reorder', data),
  createType: (data) => request.post('/admin/product-types', data),
  updateType: (value, data) => request.put(`/admin/product-types/${value}`, data),
  deleteType: (value) => request.delete(`/admin/product-types/${value}`),

  groups: (params) => request.get('/admin/product-categories', { params: normalizeCategoryParams(params) }),
  categories: (params) => request.get('/admin/product-categories', { params: normalizeCategoryParams(params) }),
  reorderGroup: (data) => request.post('/admin/product-categories/reorder', normalizeCategoryParams(data)),
  reorderCategory: (data) => request.post('/admin/product-categories/reorder', normalizeCategoryParams(data)),
  createGroup: (data) => request.post('/admin/product-categories', normalizeCategoryParams(data)),
  createCategory: (data) => request.post('/admin/product-categories', normalizeCategoryParams(data)),
  updateGroup: (id, data) => request.put(`/admin/product-categories/${id}`, normalizeCategoryParams(data)),
  updateCategory: (id, data) => request.put(`/admin/product-categories/${id}`, normalizeCategoryParams(data)),
  deleteGroup: (id) => request.delete(`/admin/product-categories/${id}`),
  deleteCategory: (id) => request.delete(`/admin/product-categories/${id}`),

  reorderProduct: (data) => request.post('/admin/products/reorder', normalizeProductParams(data)),

  owners: (id, params) => request.get(`/admin/products/${id}/owners`, { params }),
}
