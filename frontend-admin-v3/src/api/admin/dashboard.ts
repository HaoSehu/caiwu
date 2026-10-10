import { request } from '@/utils/request';

import type { DashboardStats, MonthlyRevenue } from './types';

export const dashboardApi = {
  stats: () => request.get<DashboardStats>({ url: '/v2/admin/dashboard/stats' }),
  monthlyRevenue: () => request.get<MonthlyRevenue>({ url: '/v2/admin/dashboard/monthly-revenue' }),
};
