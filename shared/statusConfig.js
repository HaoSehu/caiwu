/**
 * ============================================================
 *  IDC 财务系统 —— 全局状态颜色 & 映射配置
 * ============================================================
 *  两个前端（frontend-admin / frontend-client）共用此文件
 *  引入方式：import { ORDER_STATUS, ... } from '@shared/statusConfig'
 *
 *  颜色体系说明：
 *  ┌──────────┬────────────┬───────────────────────────────┐
 *  │  语义     │  色值       │  用途                          │
 *  ├──────────┼────────────┼───────────────────────────────┤
 *  │ warning  │ #E6A23C    │ 待支付/待付款 — 需要行动        │
 *  │ blue     │ #409EFF    │ 已支付/已付款 — 流程中          │
 *  │ primary  │ #409EFF    │ 开通中 — 处理中(带呼吸动画)     │
 *  │ success  │ #67C23A    │ 已开通/已完成 — 正向完结        │
 *  │ info     │ #909399    │ 已关闭/已取消/已到期 — 终态      │
 *  │ danger   │ #F56C6C    │ 已退款 — 资金逆向              │
 *  │ purple   │ #8B5CF6    │ 已冻结/已暂停 — 异常锁定        │
 *  └──────────┴────────────┴───────────────────────────────┘
 * ============================================================
 */

// ===================== 颜色常量 =====================

export const STATUS_COLORS = {
  warning: '#E6A23C',   // 待支付
  blue:    '#409EFF',   // 已支付 / 开通中
  success: '#67C23A',   // 已开通 / 已完成
  info:    '#909399',   // 已关闭 / 已取消 / 已到期
  danger:  '#F56C6C',   // 已退款
  purple:  '#8B5CF6',   // 已冻结 / 已暂停
}

// Element Plus Tag 对应的 type（purple 需自定义 class）
export const STATUS_TAG_TYPES = {
  warning: 'warning',
  blue:    '',          // 默认 primary
  success: 'success',
  info:    'info',
  danger:  'danger',
  purple:  'purple',    // 需要配合 global.scss 自定义样式
}

const VALID_EL_TAG_TYPES = new Set(['primary', 'success', 'info', 'warning', 'danger'])

// ===================== 订单状态 =====================

export const ORDER_STATUS = {
  PENDING:    0,  // 待付款
  PAID:       1,  // 已付款
  PROCESSING: 2,  // 开通中
  COMPLETED:  3,  // 已完成
  CANCELLED:  4,  // 已取消
  REFUNDED:   5,  // 已退款
}

export const ORDER_STATUS_MAP = {
  [ORDER_STATUS.PENDING]:    { label: '待付款', color: STATUS_COLORS.warning, tagType: 'warning',  icon: 'Clock'         },
  [ORDER_STATUS.PAID]:       { label: '已付款', color: STATUS_COLORS.blue,    tagType: '',         icon: 'CreditCard'    },
  [ORDER_STATUS.PROCESSING]: { label: '开通中', color: STATUS_COLORS.blue,    tagType: '',         icon: 'Loading',      pulse: true },
  [ORDER_STATUS.COMPLETED]:  { label: '已完成', color: STATUS_COLORS.success, tagType: 'success',  icon: 'CircleCheck'   },
  [ORDER_STATUS.CANCELLED]:  { label: '已取消', color: STATUS_COLORS.info,    tagType: 'info',     icon: 'CircleClose'   },
  [ORDER_STATUS.REFUNDED]:   { label: '已退款', color: STATUS_COLORS.danger,  tagType: 'danger',   icon: 'RefreshLeft'   },
}

// 订单类型
export const ORDER_TYPE_MAP = {
  new:     '新购',
  renew:   '续费',
  upgrade: '升降级',
}

// 账单类型
export const INVOICE_TYPE_MAP = {
  new:             '新购',
  normal:          '新购',
  renew:           '续费',
  recharge:        '充值',
  deduction:       '扣款',
  referral_credit: '推荐奖励账单',
  manual:          '手工账单',
}

// ===================== 账单/发票状态 =====================

export const INVOICE_STATUS = {
  UNPAID:    0,  // 待支付
  PAID:      1,  // 已支付
  CANCELLED: 2,  // 已取消
  OVERDUE:   3,  // 已逾期
  REFUNDED:  5,  // 已退款
}

export const INVOICE_STATUS_MAP = {
  [INVOICE_STATUS.UNPAID]:    { label: '待支付', color: STATUS_COLORS.warning, tagType: 'warning', icon: 'Clock'       },
  [INVOICE_STATUS.PAID]:      { label: '已支付', color: STATUS_COLORS.success, tagType: 'success', icon: 'Select'      },
  [INVOICE_STATUS.CANCELLED]: { label: '已取消', color: STATUS_COLORS.info,    tagType: 'info',    icon: 'CircleClose' },
  [INVOICE_STATUS.OVERDUE]:   { label: '已逾期', color: STATUS_COLORS.danger,  tagType: 'danger',  icon: 'WarningFilled'},
  [INVOICE_STATUS.REFUNDED]:  { label: '已退款', color: STATUS_COLORS.danger,  tagType: 'danger',  icon: 'RefreshLeft' },
}

// ===================== 服务/产品实例状态 =====================

export const SERVICE_STATUS = {
  PENDING:   0,  // 开通中
  ACTIVE:    1,  // 已开通
  SUSPENDED: 2,  // 已暂停（冻结）
  EXPIRED:   3,  // 已到期
  CANCELLED: 4,  // 已取消
}

export const SERVICE_STATUS_MAP = {
  [SERVICE_STATUS.PENDING]:   { label: '开通中', color: STATUS_COLORS.warning, tagType: 'warning', icon: 'Clock'       },
  [SERVICE_STATUS.ACTIVE]:    { label: '已开通', color: STATUS_COLORS.success, tagType: 'success', icon: 'CircleCheck' },
  [SERVICE_STATUS.SUSPENDED]: { label: '已暂停', color: STATUS_COLORS.purple,  tagType: 'purple',  icon: 'Lock'        },
  [SERVICE_STATUS.EXPIRED]:   { label: '已到期', color: STATUS_COLORS.info,    tagType: 'info',    icon: 'Timer'       },
  [SERVICE_STATUS.CANCELLED]: { label: '已取消', color: STATUS_COLORS.info,    tagType: 'info',    icon: 'CircleClose' },
}

// ===================== 工单状态 =====================

export const TICKET_STATUS = {
  OPEN:        0,  // 开启（待处理）
  USER_REPLY:  1,  // 客户回复
  STAFF_REPLY: 2,  // 员工回复
  CLOSED:      3,  // 已关闭
}

export const TICKET_STATUS_MAP = {
  [TICKET_STATUS.OPEN]:        { label: '开启',   color: STATUS_COLORS.warning, tagType: 'warning', icon: 'ChatDotRound'   },
  [TICKET_STATUS.USER_REPLY]:  { label: '客户回复', color: STATUS_COLORS.danger,  tagType: 'danger',  icon: 'Comment'        },
  [TICKET_STATUS.STAFF_REPLY]: { label: '员工回复', color: STATUS_COLORS.success, tagType: 'success', icon: 'ChatLineSquare' },
  [TICKET_STATUS.CLOSED]:      { label: '已关闭', color: STATUS_COLORS.info,    tagType: 'info',    icon: 'Finished'       },
}

// ===================== 邮件/通知状态 =====================

export const NOTIFY_STATUS = {
  PENDING: 'pending',
  SUCCESS: 'success',
  FAILED:  'failed',
}

export const NOTIFY_STATUS_MAP = {
  [NOTIFY_STATUS.PENDING]: { label: '待发送', color: STATUS_COLORS.warning, tagType: 'warning', icon: 'Clock'     },
  [NOTIFY_STATUS.SUCCESS]: { label: '成功',   color: STATUS_COLORS.success, tagType: 'success', icon: 'Select'    },
  [NOTIFY_STATUS.FAILED]:  { label: '失败',   color: STATUS_COLORS.danger,  tagType: 'danger',  icon: 'CloseBold' },
}

// ===================== 佣金奖励状态 =====================

export const REWARD_STATUS = {
  FROZEN:   0,  // 冻结中
  RELEASED: 1,  // 已释放
  REVOKED:  2,  // 已撤销
}

export const REWARD_STATUS_MAP = {
  [REWARD_STATUS.FROZEN]:   { label: '冻结中', color: STATUS_COLORS.purple,  tagType: 'purple',  icon: 'Lock'        },
  [REWARD_STATUS.RELEASED]: { label: '已释放', color: STATUS_COLORS.success, tagType: 'success', icon: 'CircleCheck' },
  [REWARD_STATUS.REVOKED]:  { label: '已回退', color: STATUS_COLORS.info,    tagType: 'info',    icon: 'CircleClose' },
}

// ===================== 交易流水类型 =====================

export const TRANSACTION_TYPE_MAP = {
  recharge:  '充值',
  consume:   '消费',
  refund:    '退款',
  withdraw:  '提现',
  commission:'佣金',
  adjust:    '调账',
}

export const FINANCE_LEDGER_EVENT_MAP = {
  invoice_payment: { label: '账单支付', tagType: 'danger', direction: 'out' },
  invoice_refund: { label: '账单退款', tagType: 'success', direction: 'in' },
  recharge: { label: '充值到账', tagType: 'success', direction: 'in' },
  manual_recharge: { label: '手动充值', tagType: 'success', direction: 'in' },
  manual_deduction: { label: '手动扣款', tagType: 'danger', direction: 'out' },
  referral_credit_cash: { label: '奖励转余额', tagType: 'success', direction: 'in' },
  system_adjustment: { label: '系统调账', tagType: 'info', direction: 'in' },
}

// ===================== 通用工具函数 =====================

/**
 * 获取状态配置项
 * @param {object} statusMap - 状态映射对象（如 ORDER_STATUS_MAP）
 * @param {number|string} status - 状态值
 * @returns {{ label: string, color: string, tagType: string, icon: string, pulse?: boolean }}
 */
export function getStatusConfig(statusMap, status) {
  return statusMap[status] || { label: '未知', color: STATUS_COLORS.info, tagType: 'info', icon: 'QuestionFilled' }
}

/**
 * 获取状态标签文本
 */
export function getStatusLabel(statusMap, status) {
  return getStatusConfig(statusMap, status).label
}

/**
 * 获取 el-tag 的 type
 */
export function getStatusTagType(statusMap, status) {
  return getStatusConfig(statusMap, status).tagType
}

/**
 * 将共享状态 tagType 转为 Element Plus 可接受的类型
 */
export function resolveElTagType(tagType) {
  if (tagType === '') {
    return 'primary'
  }

  if (tagType === 'purple') {
    return 'info'
  }

  return VALID_EL_TAG_TYPES.has(tagType) ? tagType : 'info'
}

/**
 * 返回 el-tag 需要附加的自定义 class
 */
export function resolveElTagClass(tagType) {
  return tagType === 'purple' ? 'el-tag--purple' : ''
}

/**
 * 获取状态颜色
 */
export function getStatusColor(statusMap, status) {
  return getStatusConfig(statusMap, status).color
}

/**
 * 将状态映射转换为 el-select / el-option 数组
 * @param {object} statusMap - 状态映射对象
 * @param {boolean} includeAll - 是否包含"全部"选项
 * @returns {Array<{ label: string, value: number|string }>}
 */
export function toSelectOptions(statusMap, includeAll = true) {
  const options = Object.entries(statusMap).map(([value, config]) => ({
    label: config.label,
    value: isNaN(Number(value)) ? value : Number(value),
  }))
  if (includeAll) {
    options.unshift({ label: '全部', value: '' })
  }
  return options
}

/**
 * 兼容旧格式：生成 { 0: '待付款', 1: '已付款', ... } 扁平映射
 */
export function toLabelMap(statusMap) {
  const map = {}
  for (const [key, config] of Object.entries(statusMap)) {
    map[key] = config.label
  }
  return map
}

/**
 * 兼容旧格式：生成 { 0: 'warning', 1: 'success', ... } 的 tagType 映射
 */
export function toTagTypeMap(statusMap) {
  const map = {}
  for (const [key, config] of Object.entries(statusMap)) {
    map[key] = config.tagType
  }
  return map
}

export const VERIFICATION_STATUS_MAP = {
  0: { label: '未提交', color: STATUS_COLORS.info,    tagType: 'info',    icon: 'CircleClose' },
  1: { label: '待认证', color: STATUS_COLORS.warning, tagType: 'warning', icon: 'Clock' },
  2: { label: '认证成功', color: STATUS_COLORS.success, tagType: 'success', icon: 'CircleCheck' },
  3: { label: '认证失败', color: STATUS_COLORS.danger,  tagType: 'danger',  icon: 'WarningFilled' },
  4: { label: '待认证', color: STATUS_COLORS.warning, tagType: 'warning', icon: 'Clock' },
  5: { label: '已驳回', color: STATUS_COLORS.danger,  tagType: 'danger',  icon: 'CircleClose' },
}
