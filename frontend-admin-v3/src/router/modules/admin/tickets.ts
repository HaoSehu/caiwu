import { shallowRef } from 'vue';
import type { RouteRecordRaw } from 'vue-router';

import { ChatIcon } from 'tdesign-icons-vue-next';

export const ticketsRoutes: RouteRecordRaw[] = [
  {
    path: 'tickets',
    name: 'AdminTickets',
    component: () => import('@/pages/tickets/index.vue'),
    meta: {
      title: {
        zh_CN: '工单管理',
        en_US: 'Tickets',
      },
      icon: shallowRef(ChatIcon),
      permission: 'ticket.list',
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
    },
  },
];