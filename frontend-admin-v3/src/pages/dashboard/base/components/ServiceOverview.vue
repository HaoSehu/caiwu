<template>
  <t-card :bordered="false" class="service-overview" title="服务与资源概览" subtitle="服务实例运行与到期情况">
    <div class="service-metrics">
      <div class="service-metric">
        <span class="service-metric__label">累计服务</span>
        <span class="service-metric__value">{{ metrics.total }}</span>
        <span class="service-metric__unit">个</span>
      </div>
      <div class="service-metric">
        <span class="service-metric__label">活跃服务</span>
        <span class="service-metric__value">{{ metrics.active }}</span>
        <span class="service-metric__unit">个</span>
      </div>
      <button
        type="button"
        class="service-metric is-clickable"
        :class="{ 'is-warning': metrics.expiringSoon > 0 }"
        aria-label="查看 7 天内到期服务"
        @click="go('/admin/services')"
      >
        <span class="service-metric__label">7 天内到期</span>
        <span class="service-metric__value">{{ metrics.expiringSoon }}</span>
        <span class="service-metric__unit">个</span>
      </button>
    </div>

    <div class="service-ratio">
      <div class="service-ratio__head">
        <span>活跃占比</span>
        <span class="service-ratio__percent">{{ activePercentText }}</span>
      </div>
      <div
        class="service-ratio__track"
        role="progressbar"
        :aria-valuenow="Math.round(activePercent)"
        aria-valuemin="0"
        aria-valuemax="100"
        :aria-label="`活跃服务占比 ${activePercentText}`"
      >
        <div class="service-ratio__bar" :style="{ width: `${activePercent}%` }" />
      </div>
      <p class="service-ratio__tip">
        {{
          metrics.expiringSoon > 0
            ? `${metrics.expiringSoon} 个服务将在 7 天内到期，请关注续费`
            : '近 7 天内暂无服务到期'
        }}
      </p>
    </div>
  </t-card>
</template>
<script setup lang="ts">
import { computed } from 'vue';
import { useRouter } from 'vue-router';

import type { DashboardStats } from '@/api/admin';

defineOptions({ name: 'DashboardServiceOverview' });

const props = defineProps<{
  counts?: DashboardStats['counts'];
  services?: DashboardStats['services'];
}>();

const router = useRouter();

function toCount(value: unknown): number {
  const num = Number(value ?? 0);
  return Number.isFinite(num) ? num : 0;
}

const metrics = computed(() => ({
  total: toCount(props.services?.total),
  active: toCount(props.counts?.active_services),
  expiringSoon: toCount(props.services?.expiring_soon),
}));

const activePercent = computed(() => {
  if (metrics.value.total <= 0) return 0;
  return Math.min(100, (metrics.value.active / metrics.value.total) * 100);
});

const activePercentText = computed(() => `${activePercent.value.toFixed(1)}%`);

function go(to: string) {
  router.push(to);
}
</script>
<style lang="less" scoped>
.service-metrics {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: var(--td-comp-margin-m);
}

.service-metric {
  display: flex;
  align-items: baseline;
  gap: 4px;
  padding: var(--td-comp-paddingTB-m) var(--td-comp-paddingLR-m);
  border: 1px solid transparent;
  border-radius: var(--td-radius-medium);
  background: var(--td-bg-color-secondarycontainer);
  color: inherit;
  font: inherit;
  text-align: left;

  &.is-clickable {
    cursor: pointer;
    transition:
      border-color 0.2s ease,
      background-color 0.2s ease;

    &:hover {
      border-color: var(--td-brand-color);
      background: var(--td-brand-color-light);
    }

    &:focus-visible {
      outline: 2px solid var(--td-brand-color-focus);
      outline-offset: 2px;
    }
  }

  &.is-warning {
    background: var(--td-warning-color-1);
  }
}

.service-metric__label {
  flex: none;
  color: var(--td-text-color-secondary);
  font-size: var(--td-font-size-body-small, 12px);
}

.service-metric__value {
  margin-left: auto;
  color: var(--td-text-color-primary);
  font-size: 22px;
  font-weight: 600;
  font-variant-numeric: tabular-nums;
  line-height: 1.2;
}

.service-metric__unit {
  color: var(--td-text-color-placeholder);
  font-size: var(--td-font-size-body-small, 12px);
}

.service-ratio {
  margin-top: var(--td-comp-margin-l);
}

.service-ratio__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  color: var(--td-text-color-secondary);
  font-size: var(--td-font-size-body-small, 12px);
}

.service-ratio__percent {
  color: var(--td-text-color-primary);
  font-variant-numeric: tabular-nums;
  font-weight: 600;
}

.service-ratio__track {
  overflow: hidden;
  height: 8px;
  margin-top: var(--td-comp-margin-xs);
  border-radius: var(--td-radius-round);
  background: var(--td-bg-color-component);
}

.service-ratio__bar {
  height: 100%;
  border-radius: var(--td-radius-round);
  background: var(--td-brand-color);
  transition: width 0.3s ease;
}

.service-ratio__tip {
  margin: var(--td-comp-margin-s) 0 0;
  color: var(--td-text-color-placeholder);
  font-size: var(--td-font-size-body-small, 12px);
  line-height: 1.5;
}

@media (width <= 640px) {
  .service-metrics {
    grid-template-columns: minmax(0, 1fr);
  }
}
</style>
