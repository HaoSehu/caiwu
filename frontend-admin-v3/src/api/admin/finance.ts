import { request } from '@/utils/request';
import type {
  InvoiceListParams,
  InvoiceRecord,
  InvoiceDetailResponse,
  OrderListParams,
  OrderRecord,
  RechargeListParams,
  RechargeRecord,
  NewCustomerSummaryParams,
  NewCustomerDailySummary,
} from './types';

export const invoiceApi = {
  list: (params: InvoiceListParams) =>
    request.get<{ list?: InvoiceRecord[]; total?: number; page?: number; page_size?: number }>({
      url: '/admin/invoices',
      params,
    }),
  detail: (id: number | string) => request.get<InvoiceDetailResponse>({ url: `/admin/invoices/${id}` }),
  cancel: (id: number | string) => request.post({ url: `/admin/invoices/${id}/cancel` }),
};

export const orderApi = {
  list: (params: OrderListParams) =>
    request.get<{ list?: OrderRecord[]; total?: number; page?: number; page_size?: number }>({
      url: '/admin/orders',
      params,
    }),
  detail: (id: number | string) => request.get<OrderRecord>({ url: `/admin/orders/${id}` }),
};

export const financeMenuApi = {
  recharges: (params: RechargeListParams) =>
    request.get<{ list?: RechargeRecord[]; total?: number; page?: number; page_size?: number }>({
      url: '/admin/finance/recharges',
      params,
    }),
  newCustomerDailySummary: (params: NewCustomerSummaryParams) =>
    request.get<NewCustomerDailySummary>({
      url: '/admin/finance/new-customer-daily-summary',
      params,
    }),
  renewalOrders: (params: OrderListParams) =>
    request.get<{ list?: OrderRecord[]; total?: number; page?: number; page_size?: number }>({
      url: '/admin/finance/renewal-orders',
      params,
    }),
  upgradeOrders: (params: OrderListParams) =>
    request.get<{ list?: OrderRecord[]; total?: number; page?: number; page_size?: number }>({
      url: '/admin/finance/upgrade-orders',
      params,
    }),
};
