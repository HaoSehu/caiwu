import type { AxiosRequestConfig } from 'axios';

import request from '@/utils/request';
import type {
  ApiEnvelope,
  BalanceLog,
  ConsoleSelectOption,
  ConsoleServiceDetail,
  ContentDetailPayload,
  ContentListPayload,
  ContentOverviewPayload,
  ContentUnreadCountPayload,
  CouponRecord,
  CouponSummary,
  FinanceLedgerRecord,
  FinanceLedgerSummary,
  InvoiceAlipayPaymentPayload,
  InvoiceAlipayStatusPayload,
  InvoiceBalancePaymentResult,
  InvoiceCreatePayload,
  InvoiceListSummary,
  InvoiceRecord,
  MonitorBatchPayload,
  NatForwardingPayload,
  OrderListSummary,
  OrderRecord,
  PagedList,
  PaymentRecord,
  RechargeOrderPayload,
  RechargeStatusPayload,
  ReferralAccountLogRecord,
  ReferralOverviewPayload,
  ReferralRewardRecord,
  ReferralWithdrawalApplyResult,
  ReferralWithdrawalRecord,
  ServiceNameUpdatePayload,
  ServiceOperationLogPayload,
  ServicePasswordResetPayload,
  ServicePowerActionPayload,
  ServiceReinstallOptionsPayload,
  ServiceReinstallPayload,
  ServiceInstance,
  ServiceOverviewPayload,
  ServiceRemarkUpdatePayload,
  ServiceRenewPreview,
  ServiceTrafficPackageOrderPayload,
  ServiceTrafficPackagePreview,
  ServiceTrafficPackageQuote,
  ServiceVncPayload,
  SecurityGroupPayload,
  SecurityRulePayload,
  SummaryRecord,
  TicketImageUploadPayload,
  TicketRecord,
  TicketServiceOption,
  ToolActionPayload,
} from '@/types/client';

import {
  normalizeContentDetailPayload,
  normalizeContentListPayload,
  normalizeContentOverviewPayload,
  withNormalizedData,
} from './contentNormalizer';

type QueryParams = Record<string, unknown> | undefined;
type UnknownEnvelope = ApiEnvelope<Record<string, unknown>>;
type RequestConfig = (AxiosRequestConfig & { silentError?: boolean }) | undefined;

const LONG_RUNNING_REQUEST_TIMEOUT = 45000;
const LONG_RUNNING_REQUEST_CONFIG: RequestConfig = { timeout: LONG_RUNNING_REQUEST_TIMEOUT };
const SILENT_LONG_RUNNING_REQUEST_CONFIG: RequestConfig = { ...LONG_RUNNING_REQUEST_CONFIG, silentError: true };

function getEnvelope<T>(url: string, config?: RequestConfig) {
  return request.get<ApiEnvelope<T>, ApiEnvelope<T>>(url, config);
}

function postEnvelope<T>(url: string, data?: Record<string, unknown> | FormData, config?: RequestConfig) {
  return request.post<ApiEnvelope<T>, ApiEnvelope<T>>(url, data, config);
}

function putEnvelope<T>(url: string, data?: Record<string, unknown> | FormData, config?: RequestConfig) {
  return request.put<ApiEnvelope<T>, ApiEnvelope<T>>(url, data, config);
}

const clientApi = {
  services: (params?: QueryParams) => getEnvelope<PagedList<ServiceInstance>>('/client/services', { params }),
  groupedOverview: () => getEnvelope<ServiceOverviewPayload>('/client/services/grouped-overview'),
  serviceDetail: (id: number | string) =>
    getEnvelope<ServiceInstance>(`/client/services/${id}`, { silentError: true }),
  serviceBaseDetail: (id: number | string) => getEnvelope<ConsoleServiceDetail>(`/client/services/${id}/base`),
  serviceRemoteStatus: (id: number | string) =>
    getEnvelope<Partial<ConsoleServiceDetail>>(`/client/services/${id}/remote-status`, SILENT_LONG_RUNNING_REQUEST_CONFIG),
  serviceConfig: (id: number | string) => request.get(`/client/services/${id}/config`),
  updateServiceName: (id: number | string, data: Record<string, unknown>) =>
    putEnvelope<ServiceNameUpdatePayload>(`/client/services/${id}/name`, data),
  updateServiceRemark: (id: number | string, data: Record<string, unknown>) =>
    putEnvelope<ServiceRemarkUpdatePayload>(`/client/services/${id}/remark`, data),
  serviceTrafficPackages: (id: number | string) =>
    getEnvelope<ServiceTrafficPackagePreview>(`/client/services/${id}/traffic-packages`),
  quoteTrafficPackage: (id: number | string, data: Record<string, unknown>) =>
    postEnvelope<ServiceTrafficPackageQuote>(`/client/services/${id}/traffic-packages/quote`, data),
  createTrafficPackageOrder: (id: number | string, data: Record<string, unknown>) =>
    postEnvelope<ServiceTrafficPackageOrderPayload>(`/client/services/${id}/traffic-packages/order`, data),
  serviceRenewPreview: (id: number | string, params?: QueryParams) =>
    getEnvelope<ServiceRenewPreview>(`/client/services/${id}/renew`, { params }),
  createRenewOrder: (id: number | string, data: Record<string, unknown>) =>
    postEnvelope<{ id?: number }>(`/client/services/${id}/renew`, data),
  updateAutoRenew: (id: number | string, data: Record<string, unknown>) =>
    request.put(`/client/services/${id}/renew/auto`, data),
  servicePower: (id: number | string, data: Record<string, unknown>) =>
    postEnvelope<ServicePowerActionPayload>(`/client/services/${id}/power`, data),
  serviceModuleStatus: (id: number | string, params?: QueryParams) =>
    request.get(`/client/services/${id}/module-status`, { params }),
  serviceReinstallOptions: (id: number | string, params?: QueryParams) =>
    getEnvelope<ServiceReinstallOptionsPayload>(`/client/services/${id}/reinstall/options`, { params }),
  serviceResetPassword: (id: number | string, data: Record<string, unknown>) =>
    putEnvelope<ServicePasswordResetPayload>(`/client/services/${id}/password/reset`, data),
  serviceReinstall: (id: number | string, data: Record<string, unknown>) =>
    putEnvelope<ServiceReinstallPayload>(`/client/services/${id}/reinstall`, data),
  serviceOperationLogs: (id: number | string, params?: QueryParams) =>
    getEnvelope<ServiceOperationLogPayload>(`/client/services/${id}/operation-logs`, { params }),
  serviceMonitor: (id: number | string, params?: QueryParams, config: Record<string, unknown> = {}) =>
    request.get(`/client/services/${id}/monitor`, { params, ...config }),
  serviceMonitorBatch: (id: number | string, params?: QueryParams, config: Record<string, unknown> = {}) =>
    request.get<ApiEnvelope<MonitorBatchPayload>, ApiEnvelope<MonitorBatchPayload>>(`/client/services/${id}/monitor/batch`, { params, ...config }),
  serviceNatForwardings: (id: number | string) =>
    request.get<ApiEnvelope<NatForwardingPayload>, ApiEnvelope<NatForwardingPayload>>(
      `/client/services/${id}/nat-forwardings`,
      LONG_RUNNING_REQUEST_CONFIG,
    ),
  createNatForwarding: (id: number | string, data: Record<string, unknown>) =>
    request.post(`/client/services/${id}/nat-forwardings`, data, LONG_RUNNING_REQUEST_CONFIG),
  deleteNatForwarding: (id: number | string, forwardingId: number | string) =>
    request.delete(`/client/services/${id}/nat-forwardings/${forwardingId}`, LONG_RUNNING_REQUEST_CONFIG),
  serviceSecurityGroups: (id: number | string, params?: QueryParams) =>
    getEnvelope<SecurityGroupPayload>(`/client/services/${id}/security-groups`, { params }),
  serviceSecurityGroupRules: (id: number | string, groupId: number | string) =>
    getEnvelope<SecurityRulePayload>(`/client/services/${id}/security-groups/${groupId}/rules`),
  createSecurityGroup: (id: number | string, data: Record<string, unknown>) =>
    postEnvelope<{ message?: string }>(`/client/services/${id}/security-groups`, data),
  applySecurityGroup: (id: number | string, groupId: number | string) =>
    postEnvelope<{ message?: string }>(`/client/services/${id}/security-groups/${groupId}/apply`),
  deleteSecurityGroup: (id: number | string, groupId: number | string) =>
    request.delete<ApiEnvelope<{ message?: string }>, ApiEnvelope<{ message?: string }>>(`/client/services/${id}/security-groups/${groupId}`),
  createSecurityRule: (id: number | string, groupId: number | string, data: Record<string, unknown>) =>
    postEnvelope<{ message?: string }>(`/client/services/${id}/security-groups/${groupId}/rules`, data),
  deleteSecurityRule: (id: number | string, groupId: number | string, ruleId: number | string) =>
    request.delete<ApiEnvelope<{ message?: string }>, ApiEnvelope<{ message?: string }>>(
      `/client/services/${id}/security-groups/${groupId}/rules/${ruleId}`,
    ),
  serviceVnc: (id: number | string, config: Record<string, unknown> = {}) =>
    getEnvelope<ServiceVncPayload>(`/client/services/${id}/vnc`, config),
  vncToken: (token: string) => request.get(`/client/vnc-tokens/${token}`),

  balanceLogs: (params?: QueryParams) => getEnvelope<PagedList<BalanceLog>>('/client/balance-logs', { params }),
  balanceLogsSummary: (params?: QueryParams) => request.get('/client/balance-logs/summary', { params }),
  financeLedger: (params?: QueryParams) => getEnvelope<PagedList<FinanceLedgerRecord>>('/client/finance/ledger', { params }),
  financeLedgerSummary: (params?: QueryParams) =>
    getEnvelope<FinanceLedgerSummary>('/client/finance/ledger/summary', { params }),
  financeLedgerDetail: (id: number | string) => request.get(`/client/finance/ledger/${id}`),
  coupons: (params?: QueryParams) => getEnvelope<PagedList<CouponRecord>>('/client/coupons', { params }),
  couponsSummary: (params?: QueryParams) => getEnvelope<CouponSummary>('/client/coupons/summary', { params }),
  publicCoupons: (params?: QueryParams) => getEnvelope<PagedList<CouponRecord>>('/client/coupons/public', { params }),
  publicCouponsSummary: (params?: QueryParams) => getEnvelope<CouponSummary>('/client/coupons/public/summary', { params }),
  claimCoupon: (couponId: number | string) => postEnvelope<CouponRecord>(`/client/coupons/${couponId}/claim`),

  recharge: (data: Record<string, unknown>) => postEnvelope<RechargeOrderPayload>('/client/recharge', data),
  rechargeStatus: (paymentNo: string, params?: QueryParams) =>
    getEnvelope<RechargeStatusPayload>(`/client/recharge/${paymentNo}/status`, { params }),

  invoices: (params?: QueryParams) => getEnvelope<PagedList<InvoiceRecord>>('/client/invoices', { params }),
  invoicesSummary: (params?: QueryParams) => getEnvelope<InvoiceListSummary>('/client/invoices/summary', { params }),
  createInvoice: (data: Record<string, unknown>, config?: Record<string, unknown>) =>
    postEnvelope<InvoiceCreatePayload>('/client/invoices', data, config),
  invoiceDetail: (id: number | string) => getEnvelope<InvoiceRecord>(`/client/invoices/${id}`),
  cancelInvoice: (id: number | string) => request.post(`/client/invoices/${id}/cancel`),
  payInvoiceByBalance: (id: number | string, data: Record<string, unknown>) =>
    postEnvelope<InvoiceBalancePaymentResult>(`/client/invoices/${id}/pay/balance`, data),
  payInvoiceByBalanceAndAlipay: (id: number | string, data: Record<string, unknown>) =>
    postEnvelope<InvoiceAlipayPaymentPayload>(`/client/invoices/${id}/pay/mix`, data),
  payInvoiceByAlipay: (id: number | string, data: Record<string, unknown>) =>
    postEnvelope<InvoiceAlipayPaymentPayload>(`/client/invoices/${id}/pay/alipay`, data),
  queryInvoiceAlipayStatus: (id: number | string, params?: QueryParams) =>
    getEnvelope<InvoiceAlipayStatusPayload>(`/client/invoices/${id}/pay/alipay/status`, { params }),

  payments: (params?: QueryParams) => getEnvelope<PagedList<PaymentRecord>>('/client/payments', { params }),
  paymentsSummary: (params?: QueryParams) => request.get('/client/payments/summary', { params }),
  paymentDetail: (id: number | string) => getEnvelope<PaymentRecord>(`/client/payments/${id}`),

  orders: (params?: QueryParams) => getEnvelope<PagedList<OrderRecord>>('/client/orders', { params }),
  orderSummary: (params?: QueryParams) => getEnvelope<OrderListSummary>('/client/orders/summary', { params }),
  orderDetail: (id: number | string) => getEnvelope<OrderRecord>(`/client/orders/${id}`),
  cancelOrder: (id: number | string) => request.post(`/client/orders/${id}/cancel`),

  referralOverview: () => getEnvelope<ReferralOverviewPayload>('/client/referral/overview'),
  referralRewards: (params?: QueryParams) => getEnvelope<PagedList<ReferralRewardRecord>>('/client/referral/rewards', { params }),
  referralAccountLogs: (params?: QueryParams) =>
    getEnvelope<PagedList<ReferralAccountLogRecord>>('/client/referral/account-logs', { params }),
  referralWithdrawals: (params?: QueryParams) =>
    getEnvelope<PagedList<ReferralWithdrawalRecord>>('/client/referral/withdrawals', { params }),
  applyWithdrawal: (data: Record<string, unknown>) =>
    postEnvelope<ReferralWithdrawalApplyResult>('/client/referral/withdrawals', data),

  tickets: (params?: QueryParams) => getEnvelope<PagedList<TicketRecord>>('/client/tickets', { params }),
  ticketServiceOptions: (params?: QueryParams) =>
    getEnvelope<TicketServiceOption[]>('/client/tickets/service-options', { params }),
  ticketDetail: (id: number | string) => getEnvelope<TicketRecord>(`/client/tickets/${id}`),
  createTicket: (data: Record<string, unknown>) => request.post('/client/tickets', data),
  replyTicket: (id: number | string, data: Record<string, unknown>) => request.post(`/client/tickets/${id}/reply`, data),
  recallTicketReply: (id: number | string, replyId: number | string) =>
    request.post(`/client/tickets/${id}/replies/${replyId}/recall`),
  closeTicket: (id: number | string) => request.post(`/client/tickets/${id}/close`),
  uploadTicketImage: (data: FormData) =>
    postEnvelope<TicketImageUploadPayload>('/client/tickets/upload-image', data, {
      headers: { 'Content-Type': 'multipart/form-data' },
    }),

  contentOverview: () =>
    getEnvelope<Record<string, unknown>>('/client/content/overview').then((response) =>
      withNormalizedData<ContentOverviewPayload>(response, normalizeContentOverviewPayload),
    ),
  notices: (params?: QueryParams) =>
    getEnvelope<Record<string, unknown>>('/client/notices', { params }).then((response) =>
      withNormalizedData<ContentListPayload>(response, normalizeContentListPayload),
    ),
  noticeDetail: (id: number | string) =>
    getEnvelope<Record<string, unknown>>(`/client/notices/${id}`).then((response) =>
      withNormalizedData<ContentDetailPayload>(response, normalizeContentDetailPayload),
    ),
  noticeUnreadCount: () => getEnvelope<ContentUnreadCountPayload>('/client/notices/unread-count'),
  markNoticeRead: (id: number | string) => request.post(`/client/notices/${id}/mark-read`),
  markAllNoticesRead: () => request.post('/client/notices/mark-all-read'),

  // 站内信（铃铛：公告 + 个性化通知聚合）
  notificationUnreadCount: () => request.get('/client/notifications/unread-count'),
  notificationFeed: (limit = 10) => request.get('/client/notifications/feed', { params: { limit } }),
  notificationList: (params?: QueryParams) => request.get('/client/notifications', { params }),
  markNotificationRead: (id: number | string) => request.post(`/client/notifications/${id}/mark-read`),
  markAllNotificationsRead: () => request.post('/client/notifications/mark-all-read'),
  helpArticles: (params?: QueryParams) =>
    getEnvelope<Record<string, unknown>>('/client/help-articles', { params }).then((response) =>
      withNormalizedData<ContentListPayload>(response, normalizeContentListPayload),
    ),
  helpDetail: (id: number | string) =>
    getEnvelope<Record<string, unknown>>(`/client/help-articles/${id}`).then((response) =>
      withNormalizedData<ContentDetailPayload>(response, normalizeContentDetailPayload),
    ),

  blackholeQuery: (data: Record<string, unknown>) => postEnvelope<ToolActionPayload>('/client/blackhole/query', data),
  blackholeAddNingboWhitelist: (data: Record<string, unknown>) =>
    postEnvelope<ToolActionPayload>('/client/blackhole/ningbo/whitelist', data),
  blackholeToggleShiyanLayer7Rule: (data: Record<string, unknown>) =>
    postEnvelope<ToolActionPayload>('/client/blackhole/shiyan/layer7/toggle', data),
  blackholeAddShiyanLayer4Rule: (data: Record<string, unknown>) =>
    postEnvelope<ToolActionPayload>('/client/blackhole/shiyan/layer4/add', data),
  blackholeDeleteShiyanLayer4Rule: (data: Record<string, unknown>) =>
    postEnvelope<ToolActionPayload>('/client/blackhole/shiyan/layer4/delete', data),
};

export default clientApi;
