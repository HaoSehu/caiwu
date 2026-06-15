import { shallowRef } from 'vue';
import type { RouteRecordRaw } from 'vue-router';

import { ComponentGridIcon, NotificationIcon } from 'tdesign-icons-vue-next';

export const contentRoutes: RouteRecordRaw[] = [
  {
    path: 'content/notices',
    name: 'AdminContentNotices',
    component: () => import('@/pages/content/index.vue'),
    meta: {
      title: {
        zh_CN: '系统公告',
        en_US: 'Notices',
      },
      icon: shallowRef(ComponentGridIcon),
      contentType: 'notice',
      permission: 'content.list',
    },
  },
  {
    path: 'content/help',
    name: 'AdminContentHelp',
    component: () => import('@/pages/content/index.vue'),
    meta: {
      title: {
        zh_CN: '帮助中心',
        en_US: 'Help Center',
      },
      icon: shallowRef(ComponentGridIcon),
      contentType: 'help',
      permission: 'content.list',
    },
  },
  {
    path: 'content',
    redirect: '/admin/content/notices',
    meta: {
      title: {
        zh_CN: '内容中心',
        en_US: 'Content Redirect',
      },
    },
  },
  {
    path: 'notifications',
    name: 'AdminNotifications',
    component: () => import('@/pages/notifications/index.vue'),
    meta: {
      title: {
        zh_CN: '通知管理',
        en_US: 'Notifications',
      },
      icon: shallowRef(NotificationIcon),
      permission: 'content.list',
    },
  },
  {
    path: 'notifications/interfaces',
    redirect: '/admin/notifications',
    meta: {
      title: {
        zh_CN: '通知接口',
        en_US: 'Notification Interfaces',
      },
    },
  },
  {
    path: 'notifications/email-templates',
    redirect: '/admin/notifications?tab=email-templates',
    meta: {
      title: {
        zh_CN: '邮件模板',
        en_US: 'Email Templates',
      },
    },
  },
  {
    path: 'notifications/email-templates/:code',
    name: 'AdminNotificationEmailTemplateDetail',
    component: () => import('@/pages/notifications/email-template-detail/index.vue'),
    meta: {
      title: {
        zh_CN: '邮件模板详情',
        en_US: 'Email Template Detail',
      },
    },
  },
  {
    path: 'api-directory',
    redirect: '/admin/notifications?tab=api-directory',
    meta: {
      title: {
        zh_CN: 'API 目录',
        en_US: 'API Directory',
      },
    },
  },
];