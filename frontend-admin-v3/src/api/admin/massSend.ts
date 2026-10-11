import { request } from '@/utils/request';

/**
 * M9 批量群发 · 客群条件（后端 UserSegmentService 白名单键，均可选填，键不出现即不限制）
 */
export interface MassSendFilterJson {
  status?: number[];
  verified?: boolean;
  member_level_ids?: number[];
  registered_between?: string[];
  last_login_before?: string;
  product_ids?: number[];
  balance_between?: (number | string)[];
}

export interface MassSendRecord {
  id: number;
  name: string;
  channel: number;
  channel_label: string;
  is_marketing: number;
  template_id: number | null;
  template_name: string;
  total_count: number;
  sent_count: number;
  failed_count: number;
  status: number;
  status_label: string;
  scheduled_at: string | null;
  started_at: string | null;
  finished_at: string | null;
  created_by: number;
  created_at: string | null;
  can_edit: boolean;
  can_send: boolean;
  can_cancel: boolean;
}

export interface MassSendDetailRecord extends MassSendRecord {
  subject: string | null;
  content: string | null;
  filter_json: MassSendFilterJson | null;
  updated_at: string | null;
}

export interface MassSendTargetRecord {
  id: number;
  mass_send_id: number;
  user_id: number;
  email: string | null;
  status: number;
  status_label: string;
  error: string | null;
  sent_at: string | null;
  created_at: string | null;
}

export interface MassSendPayload {
  name: string;
  channel: number;
  is_marketing?: number;
  template_id?: number | null;
  subject?: string | null;
  content?: string | null;
  filter_json: Record<string, unknown>;
  scheduled_at?: string | null;
}

interface MassSendListResponse {
  list?: MassSendRecord[];
  total?: number;
  page?: number;
  page_size?: number;
}

interface MassSendTargetsResponse {
  list?: MassSendTargetRecord[];
  total?: number;
  page?: number;
  page_size?: number;
}

export const massSendsApi = {
  list: (params?: Record<string, unknown>) =>
    request.get<MassSendListResponse>({ url: '/v2/admin/mass-sends', params }),
  show: (id: number | string) =>
    request.get<MassSendDetailRecord>({ url: `/v2/admin/mass-sends/${id}` }),
  create: (data: MassSendPayload) =>
    request.post<{ mass_send?: MassSendDetailRecord }>({ url: '/v2/admin/mass-sends', data }),
  update: (id: number | string, data: MassSendPayload) =>
    request.put<{ mass_send?: MassSendDetailRecord }>({ url: `/v2/admin/mass-sends/${id}`, data }),
  delete: (id: number | string) => request.delete({ url: `/v2/admin/mass-sends/${id}` }),
  preview: (filter_json: Record<string, unknown>) =>
    request.post<{ total_count?: number }>({ url: '/v2/admin/mass-sends/preview', data: { filter_json } }),
  send: (id: number | string) =>
    request.post<{ mass_send?: MassSendDetailRecord }>({ url: `/v2/admin/mass-sends/${id}/send` }),
  cancel: (id: number | string) =>
    request.post<{ mass_send?: MassSendDetailRecord }>({ url: `/v2/admin/mass-sends/${id}/cancel` }),
  targets: (id: number | string, params?: Record<string, unknown>) =>
    request.get<MassSendTargetsResponse>({ url: `/v2/admin/mass-sends/${id}/targets`, params }),
};
