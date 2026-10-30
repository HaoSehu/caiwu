import 'vue-router';

import type { Component, DefineComponent, FunctionalComponent } from 'vue';

import type { AdminPermissionCode } from '@/constants/permissions';
import type { LocalizedTitle } from '@/locales';

export {};

declare module 'vue-router' {
  interface RouteMeta {
    title?: LocalizedTitle;
    icon?: string | Component | FunctionalComponent | DefineComponent;
    expanded?: boolean;
    orderNo?: number;
    hidden?: boolean;
    hiddenBreadcrumb?: boolean;
    single?: boolean;
    keepAlive?: boolean;
    frameSrc?: string;
    frameBlank?: boolean;
    // 以下为业务使用的路由上下文键：页面通过 route.meta 读取，用于在同一组件的多个入口间切换视图。
    // 不声明只能隐式 any，拼错不会报错；声明后由 vue-router 的类型合并统一约束。
    /** 访问该路由所需权限码，与后端 AdminPermissions 对齐 */
    permission?: AdminPermissionCode;
    /** 功能插件分类（集成插件页按此切换 domain） */
    pluginDomain?: 'captcha' | 'verification' | 'payment' | 'mail' | 'sms' | 'upstream' | 'addons';
    /** 内容页类型：系统公告 / 帮助文档 */
    contentType?: 'notice' | 'help';
    /** 日志中心默认标签 */
    logTab?:
      'api' | 'activity' | 'runtime' | 'admin-logins' | 'email' | 'sms' | 'gateway' | 'tasks' | 'schedules' | 'cleanup';
    /** 设置页默认分组标签 */
    settingsTab?: 'site_basic' | 'site_hero' | 'referral' | 'automation' | 'log_archive';
    /** 财务订单页默认标签 */
    orderTab?: 'all' | 'orders' | 'renewals' | 'upgrade';
    /** 商品页默认标签 */
    productTab?: 'catalog' | 'suppliers' | 'traffic-packages';
    /** 推广页默认标签 */
    referralTab?: 'overview' | 'rewards' | 'withdrawals';
    /** 通知页默认标签 */
    notificationTab?: 'api-directory' | 'email-templates' | 'sms-templates';
    /** 优惠券页默认标签 */
    couponTab?: 'coupons' | 'campaigns';
    /** 用户实名认证面板默认视图 */
    verificationPane?: 'list' | 'manage';
    /** keep-alive 复用组件时使用的缓存名（多入口指向同一组件） */
    keepAliveName?: string;
  }
}
