import { request } from '@/utils/request';
import type {
  CouponListParams,
  CouponRecord,
  CouponPayload,
  CouponCampaignListParams,
  CouponCampaignRecord,
  CouponCampaignPayload,
} from './types';

export const couponsApi = {
  productTree: () =>
    request.get<{ tree?: Record<string, unknown>[] }>({ url: '/admin/coupons/product-tree' }),
  summary: (params?: Record<string, unknown>) =>
    request.get<{ enabled?: boolean; [key: string]: unknown }>({ url: '/admin/coupons/summary', params }),
  list: (params: CouponListParams) =>
    request.get<{ list?: CouponRecord[]; total?: number; page?: number; page_size?: number }>({
      url: '/admin/coupons',
      params,
    }),
  create: (data: CouponPayload) => request.post<CouponRecord>({ url: '/admin/coupons', data }),
  update: (id: number | string, data: CouponPayload) =>
    request.put<CouponRecord>({ url: `/admin/coupons/${id}`, data }),
  toggleStatus: (id: number | string) => request.post({ url: `/admin/coupons/${id}/toggle-status` }),
  delete: (id: number | string) => request.delete({ url: `/admin/coupons/${id}` }),
};

export const couponCampaignsApi = {
  summary: (params?: Record<string, unknown>) =>
    request.get<Record<string, unknown>>({ url: '/admin/coupon-campaigns/summary', params }),
  list: (params: CouponCampaignListParams) =>
    request.get<{ list?: CouponCampaignRecord[]; total?: number; page?: number; page_size?: number }>({
      url: '/admin/coupon-campaigns',
      params,
    }),
  create: (data: CouponCampaignPayload) =>
    request.post<CouponCampaignRecord>({ url: '/admin/coupon-campaigns', data }),
  update: (id: number | string, data: CouponCampaignPayload) =>
    request.put<CouponCampaignRecord>({ url: `/admin/coupon-campaigns/${id}`, data }),
  toggleStatus: (id: number | string) =>
    request.post({ url: `/admin/coupon-campaigns/${id}/toggle-status` }),
  trigger: (id: number | string) => request.post({ url: `/admin/coupon-campaigns/${id}/trigger` }),
  delete: (id: number | string) => request.delete({ url: `/admin/coupon-campaigns/${id}` }),
};
