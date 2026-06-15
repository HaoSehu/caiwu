import { request } from '@/utils/request';

export interface PermissionItem {
  key: string;
  module?: string;
  module_label?: string;
  group?: string;
  group_label?: string;
  name?: string;
  description?: string;
  action?: string;
  action_label?: string;
  risk_level?: 'low' | 'medium' | 'high' | string;
  is_dangerous?: boolean;
  is_all?: boolean;
  sort?: number;
}

export interface RoleRecord {
  id: number | string;
  name?: string;
  label?: string;
  permissions?: string[];
  stored_permissions?: string[];
  admin_count?: number | string;
  created_at?: string | null;
  updated_at?: string | null;
}

export interface RolePayload {
  name: string;
  label: string;
  permissions: string[];
}

export const adminRoleApi = {
  permissions: () => request.get<{ list?: PermissionItem[] }>({ url: '/admin/permissions' }),
  list: (params?: { keyword?: string }) => request.get<{ list?: RoleRecord[] }>({ url: '/admin/roles', params }),
  detail: (id: number | string) => request.get<RoleRecord>({ url: `/admin/roles/${id}` }),
  create: (data: RolePayload) => request.post<RoleRecord>({ url: '/admin/roles', data }),
  update: (id: number | string, data: RolePayload) => request.put<RoleRecord>({ url: `/admin/roles/${id}`, data }),
  delete: (id: number | string) => request.delete({ url: `/admin/roles/${id}` }),
  copy: (id: number | string) => request.post<RoleRecord>({ url: `/admin/roles/${id}/copy` }),
};
