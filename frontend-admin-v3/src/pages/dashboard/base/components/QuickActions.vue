<template>
  <t-card :bordered="false" class="quick-actions" title="快捷入口" subtitle="常用管理页面一键直达">
    <div v-if="visibleActions.length" class="quick-grid">
      <button
        v-for="action in visibleActions"
        :key="action.key"
        type="button"
        class="quick-item"
        :aria-label="`前往${action.label}`"
        @click="go(action.to)"
      >
        <span class="quick-item__icon">
          <t-icon :name="action.icon" />
        </span>
        <span class="quick-item__label">{{ action.label }}</span>
      </button>
    </div>
    <t-empty v-else class="quick-empty" title="暂无可访问的快捷入口" />
  </t-card>
</template>
<script setup lang="ts">
import { computed } from 'vue';
import { useRouter } from 'vue-router';

import { AdminPermissions } from '@/constants/permissions';
import { hasAdminPermission } from '@/utils/permission';

defineOptions({ name: 'DashboardQuickActions' });

const router = useRouter();

const actions = [
  {
    key: 'invoices',
    label: '账单管理',
    to: '/admin/finance/invoices',
    icon: 'money',
    permission: AdminPermissions.INVOICE_LIST,
  },
  {
    key: 'orders',
    label: '订单管理',
    to: '/admin/finance/orders',
    icon: 'file-copy',
    permission: AdminPermissions.ORDER_LIST,
  },
  { key: 'tickets', label: '工单处理', to: '/admin/tickets', icon: 'chat', permission: AdminPermissions.TICKET_LIST },
  { key: 'users', label: '用户管理', to: '/admin/users', icon: 'user', permission: AdminPermissions.USER_LIST },
  {
    key: 'services',
    label: '服务实例',
    to: '/admin/services',
    icon: 'server',
    permission: AdminPermissions.PRODUCT_LIST,
  },
  {
    key: 'products',
    label: '商品管理',
    to: '/admin/products',
    icon: 'shop',
    permission: AdminPermissions.PRODUCT_LIST,
  },
  { key: 'coupons', label: '优惠券', to: '/admin/coupons', icon: 'coupon', permission: AdminPermissions.PRODUCT_LIST },
  {
    key: 'logs',
    label: '系统日志',
    to: '/admin/logs/runtime',
    icon: 'system-log',
    permission: AdminPermissions.LOG_LIST,
  },
];

// 只展示当前账号有权限进入的入口，避免点击后落到 403
const visibleActions = computed(() => actions.filter((action) => hasAdminPermission(action.permission)));

function go(to: string) {
  router.push(to);
}
</script>
<style lang="less" scoped>
.quick-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: var(--td-comp-margin-m);
}

.quick-item {
  display: flex;
  align-items: center;
  gap: var(--td-comp-margin-s);
  padding: var(--td-comp-paddingTB-m) var(--td-comp-paddingLR-m);
  border: 1px solid transparent;
  border-radius: var(--td-radius-medium);
  background: var(--td-bg-color-secondarycontainer);
  color: inherit;
  cursor: pointer;
  font: inherit;
  transition:
    border-color 0.2s ease,
    background-color 0.2s ease,
    color 0.2s ease;

  &:hover {
    border-color: var(--td-brand-color);
    background: var(--td-brand-color-light);
    color: var(--td-brand-color);
  }

  &:focus-visible {
    outline: 2px solid var(--td-brand-color-focus);
    outline-offset: 2px;
  }
}

.quick-item__icon {
  display: flex;
  flex: none;
  align-items: center;
  justify-content: center;
  font-size: 18px;
  color: var(--td-brand-color);
}

.quick-item__label {
  overflow: hidden;
  font-size: var(--td-font-size-body-medium, 14px);
  text-overflow: ellipsis;
  white-space: nowrap;
}

.quick-empty {
  padding: var(--td-comp-paddingTB-xl) 0;
}

@media (width <= 1200px) {
  .quick-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (width <= 640px) {
  .quick-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}
</style>
