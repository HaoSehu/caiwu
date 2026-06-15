import { request } from '@/utils/request';
import type { DashboardStats, RecentInvoice, MonthlyRevenue } from './types';

export const dashboardApi = {
  stats: () => request.get<DashboardStats>({ url: '/admin/dashboard/stats' }),
  recentInvoices: () =>
    request.get<{ recent_invoices?: RecentInvoice[] }>({ url: '/admin/dashboard/recent-invoices' }),
  monthlyRevenue: () => request.get<MonthlyRevenue>({ url: '/admin/dashboard/monthly-revenue' }),
};
