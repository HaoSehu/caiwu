import type { RouteRecordRaw } from 'vue-router';

export const ticketsRoutes: RouteRecordRaw[] = [
  {
    path: 'tickets',
    name: 'AdminTickets',
    component: () => import('@/pages/tickets/index.vue'),
    meta: {
      title: {
        zh_CN: '工单列表',
        en_US: 'Tickets',
      },
      permission: 'ticket.list',
    },
  },
  {
    path: 'ticket-departments',
    name: 'AdminTicketDepartments',
    component: () => import('@/pages/tickets-departments/index.vue'),
    meta: {
      title: {
        zh_CN: '工单部门',
        en_US: 'Ticket Departments',
      },
      permission: 'ticket.manage',
    },
  },
  {
    path: 'ticket-preset-replies',
    name: 'AdminTicketPresetReplies',
    component: () => import('@/pages/ticket-preset-replies/index.vue'),
    meta: {
      title: {
        zh_CN: '预设回复',
        en_US: 'Preset Replies',
      },
      permission: 'ticket.reply',
    },
  },
  {
    path: 'ticket-conversations/:id',
    name: 'AdminTicketConversation',
    component: () => import('@/pages/tickets/detail/index.vue'),
    meta: {
      title: {
        zh_CN: '工单交流',
        en_US: 'Ticket Conversation',
      },
      permission: 'ticket.list',
      hidden: true,
    },
  },
];
