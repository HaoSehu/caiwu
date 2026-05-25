/**
 * 管理端权限码常量，与后端 App\Support\AdminPermissions 一一对应。
 * 路由、菜单、按钮权限判断统一引用此文件，禁止手写权限字符串。
 */
export const AdminPermissions = {
  ALL: '*',

  DASHBOARD_VIEW: 'dashboard.view',

  USER_LIST: 'user.list',
  USER_DETAIL: 'user.detail',
  USER_MANAGE: 'user.manage',
  USER_RECHARGE: 'user.recharge',

  VERIFICATION_LIST: 'verification.list',
  VERIFICATION_UNBIND: 'verification.unbind',

  ORDER_LIST: 'order.list',
  ORDER_DETAIL: 'order.detail',
  ORDER_MANAGE: 'order.manage',

  INVOICE_LIST: 'invoice.list',
  INVOICE_DETAIL: 'invoice.detail',
  INVOICE_MANAGE: 'invoice.manage',

  TICKET_LIST: 'ticket.list',
  TICKET_REPLY: 'ticket.reply',
  TICKET_MANAGE: 'ticket.manage',

  PRODUCT_LIST: 'product.list',
  PRODUCT_MANAGE: 'product.manage',

  SETTINGS_MANAGE: 'settings.manage',

  LOG_LIST: 'log.list',
  LOG_MANAGE: 'log.manage',

  REFERRAL_LIST: 'referral.list',

  FINANCE_REPORT: 'finance.report',
  FINANCE_WITHDRAW: 'finance.withdraw',

  MEMBER_LEVEL_MANAGE: 'member_level.manage',

  CONTENT_LIST: 'content.list',
  CONTENT_MANAGE: 'content.manage',
} as const

export type AdminPermissionCode = (typeof AdminPermissions)[keyof typeof AdminPermissions]
