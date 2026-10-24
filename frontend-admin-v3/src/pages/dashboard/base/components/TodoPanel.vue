<template>
  <t-card :bordered="false" class="todo-panel">
    <template #title>
      <div class="todo-panel__heading">
        <span class="todo-panel__title">待办中心</span>
        <t-tag v-if="pendingTotal > 0" theme="warning" variant="light" size="small">
          {{ pendingTotal }} 项待处理
        </t-tag>
        <t-tag v-else theme="success" variant="light" size="small">暂无待处理</t-tag>
      </div>
    </template>

    <div class="todo-grid">
      <button
        v-for="item in items"
        :key="item.key"
        type="button"
        class="todo-item"
        :class="{ 'is-pending': item.count > 0 }"
        :aria-label="`${item.label} ${item.count} ${item.unit}，前往处理`"
        @click="go(item.to)"
      >
        <span class="todo-item__icon" :class="`is-${item.tone}`">
          <t-icon :name="item.icon" />
        </span>
        <span class="todo-item__main">
          <span class="todo-item__label">{{ item.label }}</span>
          <span class="todo-item__count">
            <strong>{{ item.count }}</strong>
            <em>{{ item.unit }}</em>
          </span>
        </span>
        <t-icon class="todo-item__arrow" name="chevron-right" />
      </button>
    </div>
  </t-card>
</template>
<script setup lang="ts">
import { computed } from 'vue';
import { useRouter } from 'vue-router';

import type { DashboardStats } from '@/api/admin';

defineOptions({ name: 'DashboardTodoPanel' });

const props = defineProps<{
  todos?: DashboardStats['todos'];
  loading?: boolean;
}>();

const router = useRouter();

function toCount(value: unknown): number {
  const num = Number(value ?? 0);
  return Number.isFinite(num) ? num : 0;
}

const items = computed(() => [
  {
    key: 'unpaid_invoices',
    label: '待支付账单',
    unit: '笔',
    count: toCount(props.todos?.unpaid_invoices),
    icon: 'bill',
    tone: 'brand',
    to: '/admin/finance/invoices',
  },
  {
    key: 'open_tickets',
    label: '未处理工单',
    unit: '个',
    count: toCount(props.todos?.open_tickets),
    icon: 'chat',
    tone: 'warning',
    to: '/admin/tickets',
  },
  {
    key: 'pending_verifications',
    label: '待审核实名',
    unit: '条',
    count: toCount(props.todos?.pending_verifications),
    icon: 'user-checked',
    tone: 'success',
    to: '/admin/users/verification',
  },
  {
    key: 'pending_withdrawals',
    label: '待处理提现',
    unit: '笔',
    count: toCount(props.todos?.pending_withdrawals),
    icon: 'money',
    tone: 'error',
    to: '/admin/referral/withdrawals',
  },
]);

const pendingTotal = computed(() => items.value.reduce((sum, item) => sum + item.count, 0));

function go(to: string) {
  router.push(to);
}
</script>
<style lang="less" scoped>
.todo-panel__heading {
  display: flex;
  align-items: center;
  gap: var(--td-comp-margin-s);
}

.todo-panel__title {
  font-size: var(--td-font-size-title-medium, 16px);
  font-weight: 600;
}

.todo-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: var(--td-comp-margin-l);
}

.todo-item {
  display: flex;
  align-items: center;
  gap: var(--td-comp-margin-m);
  padding: var(--td-comp-paddingTB-l) var(--td-comp-paddingLR-l);
  border: 1px solid var(--td-component-stroke);
  border-radius: var(--td-radius-medium);
  background: var(--td-bg-color-container);
  color: inherit;
  cursor: pointer;
  font: inherit;
  text-align: left;
  transition:
    border-color 0.2s ease,
    background-color 0.2s ease,
    box-shadow 0.2s ease;

  &:hover {
    border-color: var(--td-brand-color);
    background: var(--td-brand-color-light);
    box-shadow: var(--td-shadow-1);
  }

  &:focus-visible {
    outline: 2px solid var(--td-brand-color-focus);
    outline-offset: 2px;
  }
}

.todo-item__icon {
  display: flex;
  flex: none;
  align-items: center;
  justify-content: center;
  width: 40px;
  height: 40px;
  border-radius: var(--td-radius-medium);
  font-size: var(--td-font-size-title-extraLarge);

  &.is-brand {
    background: var(--td-brand-color-light);
    color: var(--td-brand-color);
  }

  &.is-success {
    background: var(--td-success-color-1);
    color: var(--td-success-color-6);
  }

  &.is-warning {
    background: var(--td-warning-color-1);
    color: var(--td-warning-color-6);
  }

  &.is-error {
    background: var(--td-error-color-1);
    color: var(--td-error-color-6);
  }
}

.todo-item__main {
  display: flex;
  min-width: 0;
  flex: 1;
  flex-direction: column;
  gap: 2px;
}

.todo-item__label {
  overflow: hidden;
  color: var(--td-text-color-secondary);
  font-size: var(--td-font-size-body-small, 12px);
  text-overflow: ellipsis;
  white-space: nowrap;
}

.todo-item__count {
  display: flex;
  align-items: baseline;
  gap: 4px;

  strong {
    color: var(--td-text-color-primary);
    font-size: var(--td-font-size-headline-small);
    font-weight: 600;
    font-variant-numeric: tabular-nums;
    line-height: 1.2;
  }

  em {
    color: var(--td-text-color-placeholder);
    font-size: var(--td-font-size-body-small, 12px);
    font-style: normal;
  }
}

/* 有待办时高亮数值，全部为 0 时保持中性灰，避免误报 */
.todo-item:not(.is-pending) .todo-item__count strong {
  color: var(--td-text-color-secondary);
}

.todo-item__arrow {
  flex: none;
  color: var(--td-text-color-placeholder);
}

@media (max-width: 1200px) {
  .todo-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 640px) {
  .todo-grid {
    grid-template-columns: minmax(0, 1fr);
  }
}
</style>
