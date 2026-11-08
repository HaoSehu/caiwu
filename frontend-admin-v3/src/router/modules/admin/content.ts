import type { RouteRecordRaw } from 'vue-router';

import { AdminPermissions } from '@/constants/permissions';

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
      contentType: 'notice',
      permission: AdminPermissions.CONTENT_LIST,
      keepAliveName: 'AdminContentList',
    },
  },
  {
    path: 'content/notices/create',
    name: 'AdminContentNoticesCreate',
    component: () => import('@/pages/content/edit.vue'),
    meta: {
      title: {
        zh_CN: '新增公告',
        en_US: 'Create Notice',
      },
      contentType: 'notice',
      permission: AdminPermissions.CONTENT_MANAGE,
      hidden: true,
      keepAliveName: 'AdminContentEditor',
    },
  },
  {
    path: 'content/notices/:id',
    name: 'AdminContentNoticesEdit',
    component: () => import('@/pages/content/edit.vue'),
    meta: {
      title: {
        zh_CN: '编辑公告',
        en_US: 'Edit Notice',
      },
      contentType: 'notice',
      permission: AdminPermissions.CONTENT_MANAGE,
      hidden: true,
      keepAliveName: 'AdminContentEditor',
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
      contentType: 'help',
      permission: AdminPermissions.CONTENT_LIST,
      keepAliveName: 'AdminContentList',
    },
  },
  {
    path: 'content/help/create',
    name: 'AdminContentHelpCreate',
    component: () => import('@/pages/content/edit.vue'),
    meta: {
      title: {
        zh_CN: '新增帮助文章',
        en_US: 'Create Help Article',
      },
      contentType: 'help',
      permission: AdminPermissions.CONTENT_MANAGE,
      hidden: true,
      keepAliveName: 'AdminContentEditor',
    },
  },
  {
    path: 'content/help/:id',
    name: 'AdminContentHelpEdit',
    component: () => import('@/pages/content/edit.vue'),
    meta: {
      title: {
        zh_CN: '编辑帮助文章',
        en_US: 'Edit Help Article',
      },
      contentType: 'help',
      permission: AdminPermissions.CONTENT_MANAGE,
      hidden: true,
      keepAliveName: 'AdminContentEditor',
    },
  },
  {
    path: 'content/media-library',
    name: 'AdminMediaLibrary',
    component: () => import('@/pages/content/media-library.vue'),
    meta: {
      title: {
        zh_CN: '媒体库',
        en_US: 'Media Library',
      },
      permission: AdminPermissions.CONTENT_LIST,
    },
  },
  {
    path: 'site-info',
    name: 'AdminSiteInfo',
    component: () => import('@/pages/settings/index.vue'),
    meta: {
      title: {
        zh_CN: '站点信息',
        en_US: 'Site Info',
      },
      permission: AdminPermissions.SITE_VIEW,
      settingsTab: 'site_basic',
      keepAliveName: 'AdminSettings',
    },
  },
  {
    path: 'site-hero',
    name: 'AdminSiteHero',
    component: () => import('@/pages/settings/index.vue'),
    meta: {
      title: {
        zh_CN: '首页装修',
        en_US: 'Site Hero',
      },
      permission: AdminPermissions.SITE_VIEW,
      settingsTab: 'site_hero',
      keepAliveName: 'AdminSettings',
    },
  },
  {
    path: 'notifications',
    name: 'AdminNotifications',
    component: () => import('@/pages/notifications/index.vue'),
    meta: {
      title: {
        zh_CN: '消息配置',
        en_US: 'Message Configuration',
      },
      permission: AdminPermissions.SETTINGS_VIEW,
      keepAliveName: 'AdminNotificationList',
    },
  },
];
