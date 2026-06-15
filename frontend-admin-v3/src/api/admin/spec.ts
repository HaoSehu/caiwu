import { request } from '@/utils/request';
import type { InstanceSpecRecord, CpuModelGroupRecord } from './types';

export const instanceSpecCatalogApi = {
  list: (params?: Record<string, unknown>) =>
    request.get<{ list?: InstanceSpecRecord[] }>({ url: '/admin/instance-spec-catalog', params }),
  save: (data: { list: InstanceSpecRecord[] }) =>
    request.post<{ list?: InstanceSpecRecord[] }>({ url: '/admin/instance-spec-catalog', data }),
};

export const cpuModelCatalogApi = {
  list: () =>
    request.get<{ list?: CpuModelGroupRecord[] }>({ url: '/admin/cpu-model-catalog' }),
  save: (data: { list: CpuModelGroupRecord[] }) =>
    request.post<{ list?: CpuModelGroupRecord[] }>({ url: '/admin/cpu-model-catalog', data }),
};
