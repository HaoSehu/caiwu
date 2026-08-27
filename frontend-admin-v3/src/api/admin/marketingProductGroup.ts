import { request } from '@/utils/request';

import type { MarketingProductGroupPayload, MarketingProductGroupRecord } from './types';

export const marketingProductGroupApi = {
  list: async () => {
    const response = await request.get<MarketingProductGroupRecord[]>({ url: '/v2/admin/marketing-product-groups' });
    return Array.isArray(response) ? response : [];
  },
  show: (id: number | string) => request.get<MarketingProductGroupRecord>({ url: `/v2/admin/marketing-product-groups/${id}` }),
  create: (data: MarketingProductGroupPayload) =>
    request.post<MarketingProductGroupRecord>({ url: '/v2/admin/marketing-product-groups', data }),
  update: (id: number | string, data: MarketingProductGroupPayload) =>
    request.put<MarketingProductGroupRecord>({ url: `/v2/admin/marketing-product-groups/${id}`, data }),
  delete: (id: number | string) => request.delete({ url: `/v2/admin/marketing-product-groups/${id}` }),
  syncProducts: (id: number | string, productIds: Array<number | string>) =>
    request.put({ url: `/v2/admin/marketing-product-groups/${id}/products`, data: { product_ids: productIds } }),
};