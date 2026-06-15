import request from '@/utils/request';

import {
  normalizeContentDetailPayload,
  normalizeContentListPayload,
  normalizeContentOverviewPayload,
  withNormalizedData,
} from './contentNormalizer';

type QueryParams = Record<string, unknown> | undefined;

const LONG_RUNNING_REQUEST_TIMEOUT = 45000;
const LONG_RUNNING_REQUEST_CONFIG = { timeout: LONG_RUNNING_REQUEST_TIMEOUT };

const clientApi = {
  services: (params?: QueryParams) => request.get('/client/services', { params }),
  groupedOverview: () => request.get('/client/services/grouped-overview'),
  serviceDetail: (id: number | string) => request.get(`/client/services/${id}`, { silentError: true } as any),
  serviceBaseDetail: (id: number | string) => request.get(`/client/services/${id}/base`),
  serviceRemoteStatus: (id: number | string) =>
    request.get(`/client/services/${id}/remote-status`, { ...LONG_RUNNING_REQUEST_CONFIG, silentError: true } as any),
  serviceConfig: (id: number | string) => request.get(`/client/services/${id}/config`),
  updateServiceName: (id: number | string, data: Record<string, unknown>) => request.put(`/client/services/${id}/name`, data),
  updateServiceRemark: (id: number | string, data: Record<string, unknown>) =>
    request.put(`/client/services/${id}/remark`, data),
  serviceTrafficPackages: (id: number | string) => request.get(`/client/services/${id}/traffic-packages`),
  quoteTrafficPackage: (id: number | string, data: Record<string, unknown>) =>
    request.post(`/client/services/${id}/traffic-packages/quote`, data),
  createTrafficPackageOrder: (id: number | string, data: Record<string, unknown>) =>
    request.post(`/client/services/${id}/traffic-packages/order`, data),
  serviceRenewPreview: (id: number | string, params?: QueryParams) => request.get(`/client/services/${id}/renew`, { params }),
  createRenewOrder: (id: number | string, data: Record<string, unknown>) =>
    request.post(`/client/services/${id}/renew`, data),
  updateAutoRenew: (id: number | string, data: Record<string, unknown>) =>
    request.put(`/client/services/${id}/renew/auto`, data),
  servicePower: (id: number | string, data: Record<string, unknown>) => request.post(`/client/services/${id}/power`, data),
  serviceModuleStatus: (id: number | string, params?: QueryParams) =>
    request.get(`/client/services/${id}/module-status`, { params }),
  serviceReinstallOptions: (id: number | string, params?: QueryParams) =>
    request.get(`/client/services/${id}/reinstall/options`, { params }),
  serviceResetPassword: (id: number | string, data: Record<string, unknown>) =>
    request.put(`/client/services/${id}/password/reset`, data),
  serviceReinstall: (id: number | string, data: Record<string, unknown>) =>
    request.put(`/client/services/${id}/reinstall`, data),
  serviceOperationLogs: (id: number | string, params?: QueryParams) =>
    request.get(`/client/services/${id}/operation-logs`, { params }),
  serviceMonitor: (id: number | string, params?: QueryParams, config: Record<string, unknown> = {}) =>
    request.get(`/client/services/${id}/monitor`, { params, ...config }),
  serviceMonitorBatch: (id: number | string, params?: QueryParams, config: Record<string, unknown> = {}) =>
    request.get(`/client/services/${id}/monitor/batch`, { params, ...config }),
  serviceNatForwardings: (id: number | string) =>
    request.get(`/client/services/${id}/nat-forwardings`, LONG_RUNNING_REQUEST_CONFIG),
  createNatForwarding: (id: number | string, data: Record<string, unknown>) =>
    request.post(`/client/services/${id}/nat-forwardings`, data, LONG_RUNNING_REQUEST_CONFIG),
  deleteNatForwarding: (id: number | string, forwardingId: number | string) =>
    request.delete(`/client/services/${id}/nat-forwardings/${forwardingId}`, LONG_RUNNING_REQUEST_CONFIG),
  serviceSecurityGroups: (id: number | string, params?: QueryParams) =>
    request.get(`/client/services/${id}/security-groups`, { params }),
  serviceSecurityGroupRules: (id: number | string, groupId: number | string) =>
    request.get(`/client/services/${id}/security-groups/${groupId}/rules`),
  createSecurityGroup: (id: number | string, data: Record<string, unknown>) =>
    request.post(`/client/services/${id}/security-groups`, data),
  applySecurityGroup: (id: number | string, groupId: number | string) =>
    request.post(`/client/services/${id}/security-groups/${groupId}/apply`),
  deleteSecurityGroup: (id: number | string, groupId: number | string) =>
    request.delete(`/client/services/${id}/security-groups/${groupId}`),
  createSecurityRule: (id: number | string, groupId: number | string, data: Record<string, unknown>) =>
    request.post(`/client/services/${id}/security-groups/${groupId}/rules`, data),
  deleteSecurityRule: (id: number | string, groupId: number | string, ruleId: number | string) =>
    request.delete(`/client/services/${id}/security-groups/${groupId}/rules/${ruleId}`),
  serviceVnc: (id: number | string, config: Record<string, unknown> = {}) => request.get(`/client/services/${id}/vnc`, config),
  vncToken: (token: string) => request.get(`/client/vnc-tokens/${token}`),

  balanceLogs: (params?: QueryParams) => request.get('/client/balance-logs', { params }),
  balanceLogsSummary: (params?: QueryParams) => request.get('/client/balance-logs/summary', { params }),
  financeLedger: (params?: QueryParams) => request.get('/client/finance/ledger', { params }),
  financeLedgerSummary: (params?: QueryParams) => request.get('/client/finance/ledger/summary', { params }),
  financeLedgerDetail: (id: number | string) => request.get(`/client/finance/ledger/${id}`),
  coupons: (params?: QueryParams) => request.get('/client/coupons', { params }),
  couponsSummary: (params?: QueryParams) => request.get('/client/coupons/summary', { params }),
  publicCoupons: (params?: QueryParams) => request.get('/client/coupons/public', { params }),
  publicCouponsSummary: (params?: QueryParams) => request.get('/client/coupons/public/summary', { params }),
  claimCoupon: (couponId: number | string) => request.post(`/client/coupons/${couponId}/claim`),

  recharge: (data: Record<string, unknown>) => request.post('/client/recharge', data),
  rechargeStatus: (paymentNo: string, params?: QueryParams) => request.get(`/client/recharge/${paymentNo}/status`, { params }),

  invoices: (params?: QueryParams) => request.get('/client/invoices', { params }),
  invoicesSummary: (params?: QueryParams) => request.get('/client/invoices/summary', { params }),
  createInvoice: (data: Record<string, unknown>, config?: Record<string, unknown>) => request.post('/client/invoices', data, config),
  invoiceDetail: (id: number | string) => request.get(`/client/invoices/${id}`),
  cancelInvoice: (id: number | string) => request.post(`/client/invoices/${id}/cancel`),
  payInvoiceByBalance: (id: number | string, data: Record<string, unknown>) =>
    request.post(`/client/invoices/${id}/pay/balance`, data),
  payInvoiceByBalanceAndAlipay: (id: number | string, data: Record<string, unknown>) =>
    request.post(`/client/invoices/${id}/pay/mix`, data),
  payInvoiceByAlipay: (id: number | string, data: Record<string, unknown>) =>
    request.post(`/client/invoices/${id}/pay/alipay`, data),
  queryInvoiceAlipayStatus: (id: number | string, params?: QueryParams) =>
    request.get(`/client/invoices/${id}/pay/alipay/status`, { params }),

  orders: (params?: QueryParams) => request.get('/client/orders', { params }),
  ordersSummary: (params?: QueryParams) => request.get('/client/orders/summary', { params }),
  orderDetail: (id: number | string) => request.get(`/client/orders/${id}`),

  payments: (params?: QueryParams) => request.get('/client/payments', { params }),
  paymentsSummary: (params?: QueryParams) => request.get('/client/payments/summary', { params }),

  referralOverview: () => request.get('/client/referral/overview'),
  referralRewards: (params?: QueryParams) => request.get('/client/referral/rewards', { params }),
  referralAccountLogs: (params?: QueryParams) => request.get('/client/referral/account-logs', { params }),
  referralWithdrawals: (params?: QueryParams) => request.get('/client/referral/withdrawals', { params }),
  applyWithdrawal: (data: Record<string, unknown>) => request.post('/client/referral/withdrawals', data),

  tickets: (params?: QueryParams) => request.get('/client/tickets', { params }),
  ticketServiceOptions: (params?: QueryParams) => request.get('/client/tickets/service-options', { params }),
  ticketDetail: (id: number | string) => request.get(`/client/tickets/${id}`),
  createTicket: (data: Record<string, unknown>) => request.post('/client/tickets', data),
  replyTicket: (id: number | string, data: Record<string, unknown>) => request.post(`/client/tickets/${id}/reply`, data),
  recallTicketReply: (id: number | string, replyId: number | string) =>
    request.post(`/client/tickets/${id}/replies/${replyId}/recall`),
  closeTicket: (id: number | string) => request.post(`/client/tickets/${id}/close`),
  uploadTicketImage: (data: Record<string, unknown>) =>
    request.post('/client/tickets/upload-image', data, {
      headers: { 'Content-Type': 'multipart/form-data' },
    }),

  contentOverview: () =>
    request.get('/client/content/overview').then((response) => withNormalizedData(response as any, normalizeContentOverviewPayload)),
  notices: (params?: QueryParams) =>
    request.get('/client/notices', { params }).then((response) => withNormalizedData(response as any, normalizeContentListPayload)),
  noticeDetail: (id: number | string) =>
    request.get(`/client/notices/${id}`).then((response) => withNormalizedData(response as any, normalizeContentDetailPayload)),
  noticeUnreadCount: () => request.get('/client/notices/unread-count'),
  markNoticeRead: (id: number | string) => request.post(`/client/notices/${id}/mark-read`),
  markAllNoticesRead: () => request.post('/client/notices/mark-all-read'),

  // 站内信（铃铛：公告 + 个性化通知聚合）
  notificationUnreadCount: () => request.get('/client/notifications/unread-count'),
  notificationFeed: (limit = 10) => request.get('/client/notifications/feed', { params: { limit } }),
  notificationList: (params?: QueryParams) => request.get('/client/notifications', { params }),
  markNotificationRead: (id: number | string) => request.post(`/client/notifications/${id}/mark-read`),
  markAllNotificationsRead: () => request.post('/client/notifications/mark-all-read'),
  helpArticles: (params?: QueryParams) =>
    request
      .get('/client/help-articles', { params })
      .then((response) => withNormalizedData(response as any, normalizeContentListPayload)),
  helpDetail: (id: number | string) =>
    request.get(`/client/help-articles/${id}`).then((response) => withNormalizedData(response as any, normalizeContentDetailPayload)),

  blackholeQuery: (data: Record<string, unknown>) => request.post('/client/blackhole/query', data),
  blackholeAddNingboWhitelist: (data: Record<string, unknown>) =>
    request.post('/client/blackhole/ningbo/whitelist', data),
  blackholeToggleShiyanLayer7Rule: (data: Record<string, unknown>) =>
    request.post('/client/blackhole/shiyan/layer7/toggle', data),
  blackholeAddShiyanLayer4Rule: (data: Record<string, unknown>) =>
    request.post('/client/blackhole/shiyan/layer4/add', data),
  blackholeDeleteShiyanLayer4Rule: (data: Record<string, unknown>) =>
    request.post('/client/blackhole/shiyan/layer4/delete', data),
};

export default clientApi;
