import { request } from '@/utils/request';

import type { MemberLevelGroupDiscountMatrix, MemberLevelGroupDiscountRule, MemberLevelPayload, MemberLevelRecord } from './types';

interface MemberLevelListResponse {
  list?: MemberLevelRecord[];
}

export const memberLevelsApi = {
  list: async () => {
    const response = await request.get<MemberLevelListResponse>({ url: '/v2/admin/member-levels' });
    return response.list || [];
  },
  create: (data: MemberLevelPayload) => request.post<MemberLevelRecord>({ url: '/v2/admin/member-levels', data }),
  update: (id: number | string, data: MemberLevelPayload) =>
    request.put<MemberLevelRecord>({ url: `/v2/admin/member-levels/${id}`, data }),
  delete: (id: number | string) => request.delete({ url: `/v2/admin/member-levels/${id}` }),
  groupDiscounts: (id: number | string) =>
    request.get<MemberLevelGroupDiscountMatrix>({ url: `/v2/admin/member-levels/${id}/group-discounts` }),
  syncGroupDiscounts: (id: number | string, rules: Array<MemberLevelGroupDiscountRule & { marketing_product_group_id: number | string }>) =>
    request.put({ url: `/v2/admin/member-levels/${id}/group-discounts`, data: { rules } }),
};
