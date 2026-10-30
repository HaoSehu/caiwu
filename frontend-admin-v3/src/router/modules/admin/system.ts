import type { RouteRecordRaw } from 'vue-router';

import { AdminPermissions } from '@/constants/permissions';

const integrationPluginDomains = ['captcha', 'verification', 'payment', 'mail', 'sms', 'upstream', 'addons'] as const;

function resolveIntegrationPluginDomain(value: unknown): (typeof integrationPluginDomains)[number] {
  const domain = Array.isArray(value) ? value[0] : value;
  return integrationPluginDomains.includes(domain as (typeof integrationPluginDomains)[number])
    ? (domain as (typeof integrationPluginDomains)[number])
    : 'captcha';
}

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
      permission: AdminPermissions.LOG_LIST,
      keepAlive: false,
      logTab: 'api',
    },
  },
  {
    path: 'logs/activity',
    name: 'AdminActivityLogs',
    component: () => import('@/pages/logs/index.vue'),
    meta: {
      title: {
        zh_CN: '业务审计日志',
        en_US: 'Activity Logs',
      },
      permission: AdminPermissions.LOG_LIST,
      keepAlive: false,
      logTab: 'activity',
    },
  },
  {
    path: 'logs/runtime',
    name: 'AdminRuntimeLogs',
    component: () => import('@/pages/logs/index.vue'),
    meta: {
      title: {
        zh_CN: '运行日志',
        en_US: 'Runtime Logs',
      },
      permission: AdminPermissions.LOG_LIST,
      keepAlive: false,
      logTab: 'runtime',
    },
  },
  {
    path: 'logs/admin-logins',
    name: 'AdminLoginLogs',
    component: () => import('@/pages/logs/index.vue'),
    meta: {
      title: {
        zh_CN: '管理员登录',
        en_US: 'Admin Logins',
      },
      permission: AdminPermissions.LOG_LIST,
      keepAlive: false,
      logTab: 'admin-logins',
    },
  },
  {
    path: 'logs/api',
    name: 'AdminApiLogs',
    component: () => import('@/pages/logs/index.vue'),
    meta: {
      title: {
        zh_CN: 'API 日志',
        en_US: 'API Logs',
      },
      permission: AdminPermissions.LOG_LIST,
      keepAlive: false,
      logTab: 'api',
    },
  },
  {
    path: 'logs/sms',
    name: 'AdminSmsLogs',
    component: () => import('@/pages/logs/index.vue'),
    meta: {
      title: {
        zh_CN: '短信日志',
        en_US: 'SMS Logs',
      },
      permission: AdminPermissions.LOG_LIST,
      keepAlive: false,
      logTab: 'sms',
    },
  },
  {
    path: 'logs/email',
    name: 'AdminEmailLogs',
    component: () => import('@/pages/logs/index.vue'),
    meta: {
      title: {
        zh_CN: '邮件日志',
        en_US: 'Email Logs',
      },
      permission: AdminPermissions.LOG_LIST,
      keepAlive: false,
      logTab: 'email',
    },
  },
  {
    path: 'logs/tasks',
    name: 'AdminTaskLogs',
    component: () => import('@/pages/logs/index.vue'),
    meta: {
      title: {
        zh_CN: '自动任务日志',
        en_US: 'Task Logs',
      },
      permission: AdminPermissions.LOG_LIST,
      keepAlive: false,
      logTab: 'tasks',
    },
  },
  {
    path: 'logs/gateway',
    name: 'AdminGatewayLogs',
    component: () => import('@/pages/logs/index.vue'),
    meta: {
      title: {
        zh_CN: '网关日志',
        en_US: 'Gateway Logs',
      },
      permission: AdminPermissions.LOG_LIST,
      keepAlive: false,
      logTab: 'gateway',
    },
  },
  {
    path: 'logs/schedules',
    name: 'AdminScheduleTasks',
    component: () => import('@/pages/logs/index.vue'),
    meta: {
      title: {
        zh_CN: '定时任务',
        en_US: 'Scheduled Tasks',
      },
      permission: AdminPermissions.SCHEDULE_VIEW,
      keepAlive: false,
      logTab: 'schedules',
    },
  },
  {
    path: 'logs/cleanup',
    name: 'AdminLogCleanup',
    component: () => import('@/pages/logs/index.vue'),
    meta: {
      title: {
        zh_CN: '日志清理',
        en_US: 'Log Cleanup',
      },
      permission: AdminPermissions.LOG_LIST,
      keepAlive: false,
      logTab: 'cleanup',
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
      permission: AdminPermissions.STAFF_LIST,
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
      permission: AdminPermissions.ROLE_LIST,
    },
  },
  {
    path: 'system/database',
    name: 'AdminDatabaseStatus',
    component: () => import('@/pages/admin/system/database/index.vue'),
    meta: {
      title: {
        zh_CN: '数据库状态',
        en_US: 'Database Status',
      },
      permission: AdminPermissions.DATABASE_VIEW,
      keepAlive: false,
    },
  },
  {
    path: 'automation',
    name: 'AdminAutomation',
    component: () => import('@/pages/settings/index.vue'),
    meta: {
      title: {
        zh_CN: '自动化策略',
        en_US: 'Automation',
      },
      permission: AdminPermissions.SCHEDULE_VIEW,
      settingsTab: 'automation',
      keepAliveName: 'AdminSettings',
    },
  },
  {
    path: 'log-archive',
    name: 'AdminLogArchiveSettings',
    component: () => import('@/pages/settings/index.vue'),
    meta: {
      title: {
        zh_CN: '日志归档',
        en_US: 'Log Archive',
      },
      permission: AdminPermissions.SETTINGS_VIEW,
      settingsTab: 'log_archive',
      keepAliveName: 'AdminSettings',
    },
  },
  {
    path: 'payment',
    name: 'AdminPayment',
    redirect: '/admin/integration-plugins/payment',
  },
  {
    path: 'integration-plugins',
    name: 'AdminIntegrationPlugins',
    redirect: (to) => {
      const query = { ...to.query };
      delete query.domain;
      return {
        path: `/admin/integration-plugins/${resolveIntegrationPluginDomain(to.query.domain)}`,
        query,
      };
    },
    meta: {
      title: {
        zh_CN: '插件管理',
        en_US: 'Plugins',
      },
      permission: AdminPermissions.INTEGRATION_PLUGIN_VIEW,
    },
  },
  {
    path: 'integration-plugins/captcha',
    name: 'AdminCaptchaPlugins',
    component: () => import('@/pages/integration-plugins/index.vue'),
    meta: {
      title: {
        zh_CN: '人机验证',
        en_US: 'Captcha Plugins',
      },
      permission: AdminPermissions.INTEGRATION_PLUGIN_VIEW,
      pluginDomain: 'captcha',
      keepAliveName: 'AdminIntegrationPlugins',
    },
  },
  {
    path: 'integration-plugins/verification',
    name: 'AdminVerificationPlugins',
    component: () => import('@/pages/integration-plugins/index.vue'),
    meta: {
      title: {
        zh_CN: '实名认证',
        en_US: 'Verification Plugins',
      },
      permission: AdminPermissions.INTEGRATION_PLUGIN_VIEW,
      pluginDomain: 'verification',
      keepAliveName: 'AdminIntegrationPlugins',
    },
  },
  {
    path: 'integration-plugins/payment',
    name: 'AdminPaymentPlugins',
    component: () => import('@/pages/integration-plugins/index.vue'),
    meta: {
      title: {
        zh_CN: '支付渠道',
        en_US: 'Payment Plugins',
      },
      permission: AdminPermissions.INTEGRATION_PLUGIN_VIEW,
      pluginDomain: 'payment',
      keepAliveName: 'AdminIntegrationPlugins',
    },
  },
  {
    path: 'integration-plugins/mail',
    name: 'AdminMailPlugins',
    component: () => import('@/pages/integration-plugins/index.vue'),
    meta: {
      title: {
        zh_CN: '邮件发送',
        en_US: 'Mail Plugins',
      },
      permission: AdminPermissions.INTEGRATION_PLUGIN_VIEW,
      pluginDomain: 'mail',
      keepAliveName: 'AdminIntegrationPlugins',
    },
  },
  {
    path: 'integration-plugins/sms',
    name: 'AdminSmsPlugins',
    component: () => import('@/pages/integration-plugins/index.vue'),
    meta: {
      title: {
        zh_CN: '短信发送',
        en_US: 'SMS Plugins',
      },
      permission: AdminPermissions.INTEGRATION_PLUGIN_VIEW,
      pluginDomain: 'sms',
      keepAliveName: 'AdminIntegrationPlugins',
    },
  },
  {
    path: 'integration-plugins/upstream',
    name: 'AdminUpstreamPlugins',
    component: () => import('@/pages/integration-plugins/index.vue'),
    meta: {
      title: {
        zh_CN: '上游开通',
        en_US: 'Upstream Plugins',
      },
      permission: AdminPermissions.INTEGRATION_PLUGIN_VIEW,
      pluginDomain: 'upstream',
      keepAliveName: 'AdminIntegrationPlugins',
    },
  },
  {
    path: 'integration-plugins/addons',
    name: 'AdminAddonPlugins',
    component: () => import('@/pages/integration-plugins/index.vue'),
    meta: {
      title: {
        zh_CN: '功能扩展',
        en_US: 'Addon Plugins',
      },
      permission: AdminPermissions.INTEGRATION_PLUGIN_VIEW,
      pluginDomain: 'addons',
      keepAliveName: 'AdminIntegrationPlugins',
    },
  },
  {
    path: 'referral-settings',
    name: 'AdminReferralSettings',
    component: () => import('@/pages/settings/index.vue'),
    meta: {
      title: {
        zh_CN: '推荐奖励',
        en_US: 'Referral Settings',
      },
      permission: AdminPermissions.SETTINGS_VIEW,
      settingsTab: 'referral',
      keepAliveName: 'AdminSettings',
    },
  },
];
