import { shallowRef } from 'vue';
import type { RouteRecordRaw } from 'vue-router';

import { ComponentGridIcon, UserIcon } from 'tdesign-icons-vue-next';

export const systemRoutes: RouteRecordRaw[] = [
  {
    path: 'logs',
    name: 'AdminLogs',
    component: () => import('@/pages/logs/index.vue'),
    meta: {
      title: {
        zh_CN: '日志中心',
        en_US: 'Logs',
      },
      icon: shallowRef(ComponentGridIcon),
      permission: 'log.list',
      keepAlive: false,
    },
  },
  {
    path: 'notifications/sms-logs',
    redirect: '/admin/logs?tab=sms',
    meta: {
      title: {
        zh_CN: '短信日志',
        en_US: 'SMS Logs',
      },
    },
  },
  {
    path: 'notifications/email-logs',
    redirect: '/admin/logs?tab=email',
    meta: {
      title: {
        zh_CN: '邮件日志',
        en_US: 'Email Logs',
      },
    },
  },
  {
    path: 'logs/system',
    redirect: '/admin/logs?tab=system',
    meta: {
      title: {
        zh_CN: '系统日志',
        en_US: 'System Logs',
      },
    },
  },
  {
    path: 'logs/admin-logins',
    redirect: '/admin/logs?tab=admin-logins',
    meta: {
      title: {
        zh_CN: '管理员登录日志',
        en_US: 'Admin Login Logs',
      },
    },
  },
  {
    path: 'logs/api',
    redirect: '/admin/logs?tab=api',
    meta: {
      title: {
        zh_CN: 'API 日志',
        en_US: 'API Logs',
      },
    },
  },
  {
    path: 'logs/sms',
    redirect: '/admin/logs?tab=sms',
    meta: {
      title: {
        zh_CN: '短信日志',
        en_US: 'SMS Logs',
      },
    },
  },
  {
    path: 'logs/email',
    redirect: '/admin/logs?tab=email',
    meta: {
      title: {
        zh_CN: '邮件日志',
        en_US: 'Email Logs',
      },
    },
  },
  {
    path: 'logs/tasks',
    redirect: '/admin/logs?tab=tasks',
    meta: {
      title: {
        zh_CN: '任务日志',
        en_US: 'Task Logs',
      },
    },
  },
  {
    path: 'logs/cleanup',
    redirect: '/admin/logs?tab=cleanup',
    meta: {
      title: {
        zh_CN: '日志清理',
        en_US: 'Log Cleanup',
      },
    },
  },
  {
    path: 'schedules',
    redirect: '/admin/logs?tab=schedules',
    meta: {
      title: {
        zh_CN: '定时任务',
        en_US: 'Schedules',
      },
    },
  },
  {
    path: 'system/staff',
    name: 'AdminStaff',
    component: () => import('@/pages/admin/system/staff/index.vue'),
    meta: {
      title: {
        zh_CN: '员工管理',
        en_US: 'Staff',
      },
      icon: shallowRef(UserIcon),
      permission: 'staff.list',
    },
  },
  {
    path: 'system/roles',
    name: 'AdminRoles',
    component: () => import('@/pages/admin/system/roles/index.vue'),
    meta: {
      title: {
        zh_CN: '角色权限',
        en_US: 'Roles',
      },
      icon: shallowRef(ComponentGridIcon),
      permission: 'role.list',
    },
  },
  {
    path: 'settings',
    name: 'AdminSettings',
    component: () => import('@/pages/settings/index.vue'),
    meta: {
      title: {
        zh_CN: '系统设置',
        en_US: 'Settings',
      },
      icon: shallowRef(ComponentGridIcon),
      permission: 'settings.manage',
    },
  },
  {
    path: 'settings/basic',
    redirect: '/admin/settings?tab=system',
    meta: {
      title: {
        zh_CN: '基础设置',
        en_US: 'Basic Settings',
      },
    },
  },
  {
    path: 'settings/system',
    redirect: '/admin/settings?tab=system',
    meta: {
      title: {
        zh_CN: '系统设置',
        en_US: 'System Settings',
      },
    },
  },
  {
    path: 'settings/message-limit',
    redirect: '/admin/settings?tab=system',
    meta: {
      title: {
        zh_CN: '消息限制',
        en_US: 'Message Limit',
      },
    },
  },
  {
    path: 'settings/payment',
    redirect: '/admin/settings?tab=payment',
    meta: {
      title: {
        zh_CN: '支付配置',
        en_US: 'Payment Settings',
      },
    },
  },
  {
    path: 'settings/referral',
    redirect: '/admin/settings?tab=referral',
    meta: {
      title: {
        zh_CN: '推荐奖励',
        en_US: 'Referral Settings',
      },
    },
  },
  {
    path: 'settings/automation',
    redirect: '/admin/settings?tab=automation',
    meta: {
      title: {
        zh_CN: '自动化策略',
        en_US: 'Automation Settings',
      },
    },
  },
  {
    path: 'settings/site',
    redirect: '/admin/settings?tab=site_basic',
    meta: {
      title: {
        zh_CN: '站点设置',
        en_US: 'Site Settings',
      },
    },
  },
  {
    path: 'settings/site/basic',
    redirect: '/admin/settings?tab=site_basic',
    meta: {
      title: {
        zh_CN: '站点基础设置',
        en_US: 'Site Basic Settings',
      },
    },
  },
  {
    path: 'settings/site/hero',
    redirect: '/admin/settings?tab=site_hero',
    meta: {
      title: {
        zh_CN: '首页 Banner',
        en_US: 'Home Hero',
      },
    },
  },
  {
    path: 'site-ops',
    redirect: '/admin/settings?tab=site_basic',
    meta: {
      title: {
        zh_CN: '站点运维',
        en_US: 'Site Ops',
      },
    },
  },
];