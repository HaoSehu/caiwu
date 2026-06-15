import { request } from '@/utils/request';
import type { MemberLevelRecord, MemberLevelPayload } from './types';

export const memberLevelsApi = {
  list: () => request.get<MemberLevelRecord[]>({ url: '/admin/member-levels' }),
  create: (data: MemberLevelPayload) =>
    request.post<MemberLevelRecord>({ url: '/admin/member-levels', data }),
  update: (id: number | string, data: MemberLevelPayload) =>
    request.put<MemberLevelRecord>({ url: `/admin/member-levels/${id}`, data }),
  delete: (id: number | string) => request.delete({ url: `/admin/member-levels/${id}` }),
};
