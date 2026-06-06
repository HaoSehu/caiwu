import request from '@/utils/request'

export default {
  // 仪表盘
  dashboard: () => request.get('/admin/dashboard'),
  dashboardStats: () => request.get('/admin/dashboard/stats'),
  dashboardRecentInvoices: () => request.get('/admin/dashboard/recent-invoices'),
  dashboardMonthlyRevenue: () => request.get('/admin/dashboard/monthly-revenue'),

  // 账单（主实体）
  orders: {
    list: (params) => request.get('/admin/orders', { params }),
  },
  invoices: {
    list:   (params) => request.get('/admin/invoices', { params }),
    detail: (id)     => request.get(`/admin/invoices/${id}`),
    cancel: (id)     => request.post(`/admin/invoices/${id}/cancel`),
  },
  financeMenu: {
    recharges: (params) => request.get('/admin/finance/recharges', { params }),
    newCustomerDailySummary: (params) => request.get('/admin/finance/new-customer-daily-summary', { params }),
    productIncomeSummary: (params) => request.get('/admin/finance/product-income-summary', { params }),
    renewalOrders: (params) => request.get('/admin/finance/renewal-orders', { params }),
    addonOrders: (params) => request.get('/admin/finance/addon-orders', { params }),
  },
  financeLedger: {
    list: (params) => request.get('/admin/finance/ledger', { params }),
    summary: (params) => request.get('/admin/finance/ledger/summary', { params }),
    detail: (id) => request.get(`/admin/finance/ledger/${id}`),
  },

  // 工单
  tickets: {
    summary: () => request.get('/admin/tickets/summary'),
    list: (params) => request.get('/admin/tickets', { params }),
    detail: (id) => request.get(`/admin/tickets/${id}`),
    uploadImage: (data) => request.post('/admin/tickets/upload-image', data, {
      headers: { 'Content-Type': 'multipart/form-data' },
    }),
    reply: (id, data) => request.post(`/admin/tickets/${id}/reply`, data),
    recall: (id, replyId) => request.post(`/admin/tickets/${id}/replies/${replyId}/recall`),
    close: (id) => request.post(`/admin/tickets/${id}/close`),
    assign: (id, data) => request.post(`/admin/tickets/${id}/assign`, data),
    adminUsers: () => request.get('/admin/tickets/admin-users'),
  },

  // 实名认证
  verifications: {
    list:    (params) => request.get('/admin/verifications', { params }),
    detail:  (id)     => request.get(`/admin/verifications/${id}`),
    history: (id)     => request.get(`/admin/verifications/${id}/history`),
    unbind:  (id, data) => request.post(`/admin/verifications/${id}/unbind`, data),
    summary: ()       => request.get('/admin/verifications/summary'),
    settings: ()      => request.get('/admin/settings', { params: { group: 'verification' } }),
    saveSettings: (data) => request.post('/admin/settings', { group: 'verification', settings: data }),
  },

  // 内容中心
  content: {
    summary: () => request.get('/admin/content/summary'),
    categories: {
      list: (params) => request.get('/admin/content/categories', { params }),
      create: (data) => request.post('/admin/content/categories', data),
      update: (id, data) => request.put(`/admin/content/categories/${id}`, data),
      delete: (id) => request.delete(`/admin/content/categories/${id}`),
    },
    articles: {
      list: (params) => request.get('/admin/content/articles', { params }),
      detail: (id) => request.get(`/admin/content/articles/${id}`),
      create: (data) => request.post('/admin/content/articles', data),
      update: (id, data) => request.put(`/admin/content/articles/${id}`, data),
      delete: (id) => request.delete(`/admin/content/articles/${id}`),
    },
    uploadImage: (data) => request.post('/admin/content/upload-image', data, {
      headers: { 'Content-Type': 'multipart/form-data' },
    }),
    media: {
      list: (params) => request.get('/admin/media-files', { params }),
      upload: (data) => request.post('/admin/media-files', data, {
        headers: { 'Content-Type': 'multipart/form-data' },
      }),
      delete: (id) => request.delete(`/admin/media-files/${id}`),
    },
  },

  // 系统设置
  settings: {
    list: (params) => request.get('/admin/settings', { params }),
    save: (data) => request.post('/admin/settings', data),
  },

  cpuModelCatalog: {
    list: () => request.get('/admin/cpu-model-catalog'),
    save: (data) => request.post('/admin/cpu-model-catalog', data),
  },

  instanceSpecCatalog: {
    list: (params) => request.get('/admin/instance-spec-catalog', { params }),
    save: (data) => request.post('/admin/instance-spec-catalog', data),
  },

  // 官网首页 Hero 轮播
  siteHero: {
    get: () => request.get('/admin/site/home-hero'),
    save: (data) => request.post('/admin/site/home-hero', data),
  },

  // 定时任务
  schedules: {
    overview: (config = {}) => request.get('/admin/schedules/overview', config),
    trigger: (data, config = {}) => request.post('/admin/schedules/trigger', data, config),
  },

  // 会员等级
  memberLevels: {
    list: () => request.get('/admin/member-levels'),
    create: (data) => request.post('/admin/member-levels', data),
    update: (id, data) => request.put(`/admin/member-levels/${id}`, data),
    delete: (id) => request.delete(`/admin/member-levels/${id}`),
  },

  // 推广返利
  referral: {
    overview: () => request.get('/admin/referral/overview'),
    rewards: (params) => request.get('/admin/referral/rewards', { params }),
    withdrawals: (params) => request.get('/admin/referral-withdrawals', { params }),
    approveWithdrawal: (id, data) => request.post(`/admin/referral-withdrawals/${id}/approve`, data),
    rejectWithdrawal: (id, data) => request.post(`/admin/referral-withdrawals/${id}/reject`, data),
  },

  // 优惠券
  coupons: {
    productTree: () => request.get('/admin/coupons/product-tree'),
    summary: (params) => request.get('/admin/coupons/summary', { params }),
    list: (params) => request.get('/admin/coupons', { params }),
    create: (data) => request.post('/admin/coupons', data),
    update: (id, data) => request.put(`/admin/coupons/${id}`, data),
    toggleStatus: (id) => request.post(`/admin/coupons/${id}/toggle-status`),
    delete: (id) => request.delete(`/admin/coupons/${id}`),
  },

  couponCampaigns: {
    summary: (params) => request.get('/admin/coupon-campaigns/summary', { params }),
    list: (params) => request.get('/admin/coupon-campaigns', { params }),
    create: (data) => request.post('/admin/coupon-campaigns', data),
    update: (id, data) => request.put(`/admin/coupon-campaigns/${id}`, data),
    toggleStatus: (id) => request.post(`/admin/coupon-campaigns/${id}/toggle-status`),
    trigger: (id) => request.post(`/admin/coupon-campaigns/${id}/trigger`),
    delete: (id) => request.delete(`/admin/coupon-campaigns/${id}`),
  },
}

export const getSmsLogs = (params) => request.get('/admin/logs/sms', { params })
export const getSmsLogsSummary = (params) => request.get('/admin/logs/sms/summary', { params })
export const getEmailLogs = (params) => request.get('/admin/logs/email', { params })
export const getEmailLogsSummary = (params) => request.get('/admin/logs/email/summary', { params })
export const getApiLogs = (params) => request.get('/admin/logs/api', { params })
export const getTaskLogs = (params) => request.get('/admin/logs/tasks', { params })
export const getTaskLogsSummary = (params) => request.get('/admin/logs/tasks/summary', { params })
export const getSystemLogs = (params) => request.get('/admin/logs/system', { params })
export const getSystemLogsSummary = (params) => request.get('/admin/logs/system/summary', { params })
export const getAdminLoginLogs = (params) => request.get('/admin/logs/admin-logins', { params })
export const getLogCleanupOverview = () => request.get('/admin/logs/cleanup/overview')
export const cleanupLogs = (data) => request.post('/admin/logs/cleanup', data)
