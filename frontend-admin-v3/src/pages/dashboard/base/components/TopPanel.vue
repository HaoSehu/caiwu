<template>
  <div class="top-panel">
    <t-card
      v-for="card in cards"
      :key="card.key"
      :bordered="false"
      class="stat-card"
      :class="{ 'is-clickable': Boolean(card.to) }"
      :tabindex="card.to ? 0 : undefined"
      :role="card.to ? 'link' : undefined"
      @click="go(card.to)"
      @keydown.enter.prevent="go(card.to)"
      @keydown.space.prevent="go(card.to)"
    >
      <div class="stat-card__body">
        <div class="stat-card__info">
          <span class="stat-card__label">{{ card.label }}</span>
          <t-skeleton v-if="loading" :row-col="skeletonRowCol" animation="gradient" class="stat-card__skeleton" />
          <template v-else>
            <span class="stat-card__value">{{ card.value }}</span>
            <div class="stat-card__meta">
              <span v-if="card.trend" class="stat-card__trend" :class="`is-${card.trend.direction}`">
                <t-icon :name="card.trend.icon" />
                {{ card.trend.text }}
              </span>
              <span v-if="card.hint" class="stat-card__hint">{{ card.hint }}</span>
            </div>
          </template>
        </div>
        <div class="stat-card__icon" :class="`is-${card.tone}`">
          <t-icon :name="card.icon" />
        </div>
      </div>
    </t-card>
  </div>
</template>
<script setup lang="ts">
import type { SkeletonRowCol } from 'tdesign-vue-next';
import { computed } from 'vue';
import { useRouter } from 'vue-router';

import type { DashboardStats } from '@/api/admin';
import { formatCompactMoney } from '@/utils/format';

defineOptions({ name: 'DashboardTopPanel' });

const props = defineProps<{
  stats: DashboardStats;
  loading?: boolean;
}>();

const router = useRouter();

/** 指标卡数量：正整数展示，空值归零 */
function countText(value: unknown): string {
  return String(Number(value ?? 0));
}

interface TrendInfo {
  direction: 'up' | 'down' | 'flat';
  icon: string;
  text: string;
}

/**
 * 环比文案。昨日为 0 时不给百分比，避免出现除以零的误导性增幅。
 * 颜色遵循国内看板「涨红跌绿」约定，见样式 .stat-card__trend。
 */
function buildTrend(current: unknown, previous: unknown): TrendInfo | null {
  const cur = Number(current ?? 0);
  const prev = Number(previous ?? 0);
  if (!Number.isFinite(cur) || !Number.isFinite(prev)) return null;
  if (prev === 0) {
    if (cur === 0) return null;
    return { direction: 'up', icon: 'arrow-up', text: '较昨日新增' };
  }

  const diff = cur - prev;
  if (diff === 0) {
    return { direction: 'flat', icon: 'arrow-up', text: '较昨日持平' };
  }

  const percent = Math.abs((diff / prev) * 100);
  const percentText = percent >= 100 ? `${Math.round(percent)}%` : `${percent.toFixed(1)}%`;
  return {
    direction: diff > 0 ? 'up' : 'down',
    icon: diff > 0 ? 'arrow-up' : 'arrow-down',
    text: `较昨日 ${percentText}`,
  };
}

const skeletonRowCol: SkeletonRowCol = [
  [{ width: '56px', height: '14px' }],
  [{ width: '116px', height: '26px' }],
  [{ width: '84px', height: '14px' }],
];

const cards = computed(() => [
  {
    key: 'today_income',
    label: '今日营业额',
    value: formatCompactMoney(props.stats?.today?.income),
    trend: buildTrend(props.stats?.today?.income, props.stats?.yesterday?.income),
    hint: `今日新增账单 ${countText(props.stats?.today?.new_invoices)}`,
    icon: 'money',
    tone: 'brand',
    to: '/admin/finance/invoices',
  },
  {
    key: 'month_income',
    label: '本月营业额',
    value: formatCompactMoney(props.stats?.month?.income),
    trend: null,
    hint: `本月新增账单 ${countText(props.stats?.month?.new_invoices)}`,
    icon: 'chart',
    tone: 'success',
    to: '/admin/finance/invoices',
  },
  {
    key: 'open_tickets',
    label: '未处理工单',
    value: countText(props.stats?.todos?.open_tickets),
    trend: null,
    hint: `待支付账单 ${countText(props.stats?.todos?.unpaid_invoices)}`,
    icon: 'chat',
    tone: 'warning',
    to: '/admin/tickets',
  },
  {
    key: 'today_new_users',
    label: '今日新增用户',
    value: countText(props.stats?.today?.new_users),
    trend: buildTrend(props.stats?.today?.new_users, props.stats?.yesterday?.new_users),
    hint: `累计用户 ${countText(props.stats?.counts?.total_users)}`,
    icon: 'user',
    tone: 'brand',
    to: '/admin/users',
  },
]);

function go(to?: string) {
  if (to) router.push(to);
}
</script>
<style lang="less" scoped>
.top-panel {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: var(--td-comp-margin-l);
}

.stat-card {
  transition:
    box-shadow 0.2s ease,
    transform 0.2s ease;

  :deep(.t-card__body) {
    padding: var(--td-comp-paddingTB-xl) var(--td-comp-paddingLR-xl);
  }

  &.is-clickable {
    cursor: pointer;

    &:hover {
      box-shadow: var(--td-shadow-2);
      transform: translateY(-1px);
    }

    &:focus-visible {
      outline: 2px solid var(--td-brand-color-focus);
      outline-offset: 2px;
    }
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

.stat-card__meta {
  display: flex;
  min-width: 0;
  align-items: center;
  gap: var(--td-comp-margin-s);
}

.stat-card__trend {
  display: inline-flex;
  flex: none;
  align-items: center;
  gap: 2px;
  padding: 0 6px;
  border-radius: var(--td-radius-small, 2px);
  font-size: var(--td-font-size-body-small, 12px);
  font-variant-numeric: tabular-nums;
  line-height: 18px;
  white-space: nowrap;

  .t-icon {
    font-size: 12px;
  }

  /* 国内看板约定：涨红跌绿，与欧美配色相反 */
  &.is-up {
    background: var(--td-error-color-1);
    color: var(--td-error-color-8);
  }

  &.is-down {
    background: var(--td-success-color-1);
    color: var(--td-success-color-9);
  }

  &.is-flat {
    background: var(--td-bg-color-component);
    color: var(--td-text-color-secondary);
  }
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

.stat-card__skeleton {
  display: flex;
  flex-direction: column;
  gap: var(--td-comp-margin-xs);
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
