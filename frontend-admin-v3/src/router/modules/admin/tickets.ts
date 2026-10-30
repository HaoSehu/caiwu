import type { RouteRecordRaw } from 'vue-router';

import { AdminPermissions } from '@/constants/permissions';

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
      permission: AdminPermissions.TICKET_LIST,
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
      permission: AdminPermissions.TICKET_LIST,
      hidden: true,
    },
  },
];
