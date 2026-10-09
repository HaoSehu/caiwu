<template>
  <div class="top-panel">
    <t-card v-for="card in cards" :key="card.key" :bordered="false" class="stat-card">
      <div class="stat-card__body">
        <div class="stat-card__info">
          <span class="stat-card__label">{{ card.label }}</span>
          <span class="stat-card__value">{{ card.value }}</span>
          <span v-if="card.hint" class="stat-card__hint">{{ card.hint }}</span>
        </div>
        <div class="stat-card__icon" :class="`is-${card.tone}`">
          <t-icon :name="card.icon" />
        </div>
      </div>
    </t-card>
  </div>
</template>
<script setup lang="ts">
import { computed } from 'vue';

import type { DashboardStats } from '@/api/admin';
import { formatCompactMoney } from '@/utils/format';

defineOptions({ name: 'DashboardTopPanel' });

const props = defineProps<{
  stats: DashboardStats;
}>();

/** 指标卡数量：正整数展示，空值归零 */
function countText(value: unknown): string {
  return String(Number(value ?? 0));
}

const cards = computed(() => [
  {
    key: 'today_income',
    label: '今日营业额',
    value: formatCompactMoney(props.stats?.today?.income),
    hint: `今日新增账单 ${countText(props.stats?.today?.new_invoices)}`,
    icon: 'money',
    tone: 'brand',
  },
  {
    key: 'month_income',
    label: '本月营业额',
    value: formatCompactMoney(props.stats?.month?.income),
    hint: `本月新增账单 ${countText(props.stats?.month?.new_invoices)}`,
    icon: 'chart',
    tone: 'success',
  },
  {
    key: 'open_tickets',
    label: '未处理工单',
    value: countText(props.stats?.counts?.open_tickets),
    hint: `在线服务 ${countText(props.stats?.counts?.active_services)}`,
    icon: 'chat',
    tone: 'warning',
  },
  {
    key: 'today_new_users',
    label: '今日新增用户',
    value: countText(props.stats?.today?.new_users),
    hint: `累计用户 ${countText(props.stats?.counts?.total_users)}`,
    icon: 'user',
    tone: 'brand',
  },
]);
</script>
<style lang="less" scoped>
.top-panel {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: var(--td-comp-margin-l);
}

.stat-card {
  :deep(.t-card__body) {
    padding: var(--td-comp-paddingTB-xl) var(--td-comp-paddingLR-xl);
  }
}

.stat-card__body {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--td-comp-margin-m);
}

.stat-card__info {
  display: flex;
  min-width: 0;
  flex-direction: column;
  gap: var(--td-comp-margin-xs);
}

.stat-card__label {
  color: var(--td-text-color-secondary);
  font-size: var(--td-font-size-body-small, 12px);
  line-height: 1.4;
}

.stat-card__value {
  overflow: hidden;
  color: var(--td-text-color-primary);
  font-size: 26px;
  font-weight: 600;
  font-variant-numeric: tabular-nums;
  line-height: 1.2;
  letter-spacing: -0.02em;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.stat-card__hint {
  overflow: hidden;
  color: var(--td-text-color-placeholder);
  font-size: var(--td-font-size-body-small, 12px);
  font-variant-numeric: tabular-nums;
  line-height: 1.4;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.stat-card__icon {
  display: flex;
  flex: none;
  align-items: center;
  justify-content: center;
  width: 48px;
  height: 48px;
  border-radius: var(--td-radius-extraLarge, 12px);

  .t-icon {
    font-size: 24px;
  }

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
}

@media (width <= 1024px) {
  .top-panel {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (width <= 640px) {
  .top-panel {
    grid-template-columns: minmax(0, 1fr);
  }

  .stat-card__value {
    font-size: 22px;
  }
}
</style>
