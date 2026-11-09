import { request } from '@/utils/request';

import type {
  ReferralListParams,
  ReferralOverview,
  ReferralRewardRecord,
  ReferralWithdrawalPayload,
  ReferralWithdrawalRecord,
} from './types';

export const referralApi = {
  overview: () => request.get<ReferralOverview>({ url: '/v2/admin/referral/overview' }),
  rewards: (params: ReferralListParams) =>
    request.get<{ list?: ReferralRewardRecord[]; total?: number; page?: number; page_size?: number }>({
      url: '/v2/admin/referral/rewards',
      params,
    }),
  withdrawals: (params: ReferralListParams) =>
    request.get<{ list?: ReferralWithdrawalRecord[]; total?: number; page?: number; page_size?: number }>({
      url: '/v2/admin/referral-withdrawals',
      params,
    }),
  approveWithdrawal: (id: number | string, data: ReferralWithdrawalPayload) =>
    request.post({ url: `/v2/admin/referral-withdrawals/${id}/approvals`, data }),
  rejectWithdrawal: (id: number | string, data: ReferralWithdrawalPayload) =>
    request.post({ url: `/v2/admin/referral-withdrawals/${id}/rejections`, data }),
  // 审核通过后回填打款流水号，状态推进为已打款（后端仅允许支付宝方式、仅 APPROVED 状态）
  confirmWithdrawalPayment: (id: number | string, data: { payment_no: string; remark?: string }) =>
    request.post({ url: `/v2/admin/referral-withdrawals/${id}/payment-confirmations`, data }),
};
