import { dashboardApi } from './dashboard';
import { invoiceApi, orderApi, financeMenuApi } from './finance';
import { settingsApi, schedulesApi } from './settings';
import { logsApi } from './logs';
import { contentApi, mediaApi, siteHeroApi } from './content';
import { couponsApi, couponCampaignsApi } from './coupon';
import { referralApi } from './referral';
import { ticketsApi } from './ticket';
import { verificationsApi } from './verification';
import { memberLevelsApi } from './memberLevel';
import { instanceSpecCatalogApi, cpuModelCatalogApi } from './spec';

// Re-export types for backward compatibility
export * from './types';

// Combine all domain APIs into a single adminApi object (matching original structure)
export const adminApi = {
  // Dashboard
  dashboardStats: dashboardApi.stats,
  dashboardRecentInvoices: dashboardApi.recentInvoices,
  dashboardMonthlyRevenue: dashboardApi.monthlyRevenue,

  // Invoices
  invoices: invoiceApi,

  // Orders
  orders: orderApi,

  // Finance Menu
  financeMenu: financeMenuApi,

  // Settings
  settings: settingsApi,

  // Logs
  logs: logsApi,

  // Schedules
  schedules: schedulesApi,

  // Member Levels
  memberLevels: memberLevelsApi,

  // Content
  content: contentApi,

  // Media
  media: mediaApi,

  // Site Hero
  siteHero: siteHeroApi,

  // Instance Spec Catalog
  instanceSpecCatalog: instanceSpecCatalogApi,

  // CPU Model Catalog
  cpuModelCatalog: cpuModelCatalogApi,

  // Coupons
  coupons: couponsApi,

  // Coupon Campaigns
  couponCampaigns: couponCampaignsApi,

  // Referral
  referral: referralApi,

  // Tickets
  tickets: ticketsApi,

  // Verifications
  verifications: verificationsApi,
};