// 管理端 API 有两种取用方式，属于历史形成的双轨现状：
//   1) 按域直取：import { xxxApi } from '@/api/admin/xxx'（推荐，类型与实现同一文件，便于按域拆分）
//   2) 聚合对象：import { adminApi } from '@/api/admin'（下方 adminApi 摊平映射，仍有既有页面在用）
// 约定：新代码一律走第 1 种；不要往 adminApi 里新增字段，聚合对象只保持既有结构，不再扩张。
import { contentApi, mediaApi, siteHeroApi } from './content';
import { couponCampaignsApi, couponsApi } from './coupon';
import { dashboardApi } from './dashboard';
import { databaseApi } from './database';
import { financeMenuApi, invoiceApi, orderApi } from './finance';
import { logsApi } from './logs';
import { marketingProductGroupApi } from './marketingProductGroup';
import { memberLevelsApi } from './memberLevel';
import { pluginsApi } from './plugins';
import { promotionAmbassadorsApi } from './promotionAmbassador';
import { referralApi } from './referral';
import { schedulesApi, settingsApi } from './settings';
import { cpuModelCatalogApi, instanceSpecCatalogApi } from './spec';
import { ticketsApi } from './ticket';
import { verificationsApi } from './verification';

// 管理端 API 共享类型。
export * from './types';

// 汇总各域 API 为单一 adminApi 对象（保持原有结构）
export const adminApi = {
  // 仪表盘
  dashboardStats: dashboardApi.stats,
  dashboardMonthlyRevenue: dashboardApi.monthlyRevenue,

  // 发票
  invoices: invoiceApi,

  // 订单
  orders: orderApi,

  // 财务菜单
  financeMenu: financeMenuApi,

  // 系统设置
  settings: settingsApi,

  // 数据库
  database: databaseApi,

  // 日志
  logs: logsApi,

  // 计划任务
  schedules: schedulesApi,

  // 会员等级
  memberLevels: memberLevelsApi,

  // 推广大使
  promotionAmbassadors: promotionAmbassadorsApi,

  // 营销商品分组
  marketingProductGroups: marketingProductGroupApi,

  // 内容
  content: contentApi,

  // 媒体
  media: mediaApi,

  // 站点首屏
  siteHero: siteHeroApi,

  // 实例规格目录
  instanceSpecCatalog: instanceSpecCatalogApi,

  // CPU 型号目录
  cpuModelCatalog: cpuModelCatalogApi,

  // 优惠券
  coupons: couponsApi,

  // 优惠券活动
  couponCampaigns: couponCampaignsApi,

  // 推荐
  referral: referralApi,

  // 工单
  tickets: ticketsApi,

  // 实名认证
  verifications: verificationsApi,

  // 集成插件
  plugins: pluginsApi,
};
