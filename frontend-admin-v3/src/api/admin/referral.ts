import { request } from '@/utils/request';
import type {
  ReferralListParams,
  ReferralOverview,
  ReferralRewardRecord,
  ReferralWithdrawalRecord,
  ReferralWithdrawalPayload,
} from './types';

export const referralApi = {
  overview: () => request.get<ReferralOverview>({ url: '/admin/referral/overview' }),
  rewards: (params: ReferralListParams) =>
    request.get<{ list?: ReferralRewardRecord[]; total?: number; page?: number; page_size?: number }>({
      url: '/admin/referral/rewards',
      params,
    }),
  withdrawals: (params: ReferralListParams) =>
    request.get<{ list?: ReferralWithdrawalRecord[]; total?: number; page?: number; page_size?: number }>({
      url: '/admin/referral-withdrawals',
      params,
    }),
  approveWithdrawal: (id: number | string, data: ReferralWithdrawalPayload) =>
    request.post({ url: `/admin/referral-withdrawals/${id}/approve`, data }),
  rejectWithdrawal: (id: number | string, data: ReferralWithdrawalPayload) =>
    request.post({ url: `/admin/referral-withdrawals/${id}/reject`, data }),
};
