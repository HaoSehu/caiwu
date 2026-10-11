import { request } from '@/utils/request';

/**
 * M12 客户关怀计划 · 客群过滤条件（后端 UserSegmentService 白名单键，均可选填）
 */
export interface CarePlanFilterJson {
  status?: number[];
  verified?: boolean;
  member_level_ids?: number[];
  registered_between?: string[];
  last_login_before?: string;
  product_ids?: number[];
  balance_between?: (number | string)[];
}

export interface CarePlanRecord {
  id: number;
  name: string;
  type: number;
  type_label: string;
  offset_days: number;
  channel: number;
  channel_label: string;
  template_id: number | null;
  template_name: string;
  is_enabled: number;
  last_planned_date: string | null;
  created_at: string | null;
}

export interface CarePlanDetailRecord extends CarePlanRecord {
  subject?: string | null;
  content?: string | null;
  filter_json?: CarePlanFilterJson | null;
  updated_at?: string | null;
}

export interface CareTaskRunRecord {
  id: number;
  care_plan_id: number;
  planned_date: string;
  status: number;
  status_label: string;
  total_count: number;
  sent_count: number;
  failed_count: number;
  skipped_count: number;
  started_at: string | null;
  finished_at: string | null;
}

export interface CarePlanPayload {
  name: string;
  type: number;
  offset_days?: number | null;
  channel: number;
  template_id?: number | null;
  subject?: string | null;
  content?: string | null;
  filter_json?: Record<string, unknown> | null;
  is_enabled?: number;
}

interface CarePlanListResponse {
  list?: CarePlanRecord[];
  total?: number;
  page?: number;
  page_size?: number;
}

interface CarePlanRunsResponse {
  list?: CareTaskRunRecord[];
  total?: number;
  page?: number;
  page_size?: number;
}

export interface CarePlanTriggerResult {
  care_plan_id?: number;
  name?: string;
  type?: number;
  dry_run?: boolean;
  total_count?: number;
  sent_count?: number;
  failed_count?: number;
  skipped_count?: number;
}

export const carePlansApi = {
  list: (params?: Record<string, unknown>) =>
    request.get<CarePlanListResponse>({ url: '/v2/admin/care-plans', params }),
  show: (id: number | string) =>
    request.get<CarePlanDetailRecord>({ url: `/v2/admin/care-plans/${id}` }),
  create: (data: CarePlanPayload) =>
    request.post<{ care_plan?: CarePlanDetailRecord }>({ url: '/v2/admin/care-plans', data }),
  update: (id: number | string, data: CarePlanPayload) =>
    request.put<{ care_plan?: CarePlanDetailRecord }>({ url: `/v2/admin/care-plans/${id}`, data }),
  delete: (id: number | string) => request.delete({ url: `/v2/admin/care-plans/${id}` }),
  runs: (id: number | string, params?: Record<string, unknown>) =>
    request.get<CarePlanRunsResponse>({ url: `/v2/admin/care-plans/${id}/runs`, params }),
  trigger: (id: number | string, dryRun: boolean) =>
    request.post<CarePlanTriggerResult>({ url: `/v2/admin/care-plans/${id}/trigger`, data: { dry_run: dryRun } }),
};
