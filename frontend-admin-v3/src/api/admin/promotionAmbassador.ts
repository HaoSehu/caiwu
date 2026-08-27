import { request } from '@/utils/request';

import type { PromotionAmbassadorPayload, PromotionAmbassadorRecord } from './types';

interface PromotionAmbassadorListResponse {
  list?: PromotionAmbassadorRecord[];
}

export const promotionAmbassadorsApi = {
  list: async () => {
    const response = await request.get<PromotionAmbassadorListResponse>({ url: '/v2/admin/promotion-ambassadors' });
    return response.list || [];
  },
  create: (data: PromotionAmbassadorPayload) =>
    request.post<PromotionAmbassadorRecord>({ url: '/v2/admin/promotion-ambassadors', data }),
  update: (id: number | string, data: PromotionAmbassadorPayload) =>
    request.put<PromotionAmbassadorRecord>({ url: `/v2/admin/promotion-ambassadors/${id}`, data }),
  delete: (id: number | string) => request.delete({ url: `/v2/admin/promotion-ambassadors/${id}` }),
};
