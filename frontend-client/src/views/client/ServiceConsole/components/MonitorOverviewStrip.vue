<template>
  <div class="monitor-overview-strip">
    <div
      v-for="item in metricCards"
      :key="item.type"
      class="monitor-overview-item"
      :class="{ 'is-loading': item.loading }"
    >
      <span class="monitor-overview-icon" :class="`is-${item.tone}`">
        <el-icon><component :is="item.icon" /></el-icon>
      </span>
      <div class="monitor-overview-text">
        <span class="monitor-overview-label">{{ item.label }}</span>
        <template v-if="item.loading">
          <i class="monitor-overview-skeleton monitor-overview-skeleton--value"></i>
          <i class="monitor-overview-skeleton monitor-overview-skeleton--sub"></i>
        </template>
        <template v-else>
          <strong class="monitor-overview-value" :style="{ color: item.color }">{{ item.value }}</strong>
          <span class="monitor-overview-sub">{{ item.sub }}</span>
        </template>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import {
  Cpu,
  Histogram,
  Monitor,
  TrendCharts,
} from '@element-plus/icons-vue'

const METRIC_META = {
  cpu: { icon: Cpu, tone: 'cpu', color: '#165dff', label: 'CPU 使用率' },
  disk: { icon: Histogram, tone: 'storage', color: '#d97706', label: '硬盘 I/O' },
  memory: { icon: Monitor, tone: 'memory', color: '#0f9d94', label: '内存' },
  flow: { icon: TrendCharts, tone: 'network', color: '#1f9d55', label: '带宽' },
}

const FALLBACK_ORDER = ['cpu', 'disk', 'memory', 'flow']

const props = defineProps({
  charts: {
    type: Array,
    default: () => [],
  },
})

const metricCards = computed(() => {
  const chartMap = new Map()
  for (const chart of props.charts) {
    if (chart?.type) chartMap.set(chart.type, chart)
  }

  const types = chartMap.size
    ? [...chartMap.keys()]
    : FALLBACK_ORDER

  return types.slice(0, 4).map((type) => {
    const chart = chartMap.get(type)
    const meta = METRIC_META[type] || { icon: TrendCharts, tone: 'default', color: '#3b82f6', label: type }
    const loading = !chart || chart.loading
    const summary = chart?.summary

    let value = '--'
    let sub = ''

    if (summary) {
      const seriesSummary = Array.isArray(summary.series) ? summary.series.filter(Boolean) : []

      if (seriesSummary.length > 1) {
        value = String(seriesSummary[0]?.latest?.text || '--')
        sub = seriesSummary.slice(1).map((s) => `${s.label || ''} ${s.latest?.text || '--'}`).join(' · ')
      } else {
        value = String(summary.latest?.text || '--')
        sub = summary.peak?.text ? `峰值 ${summary.peak.text}` : ''
      }
    }

    return {
      type,
      icon: meta.icon,
      tone: meta.tone,
      color: meta.color,
      label: chart?.label || meta.label,
      loading,
      value,
      sub,
    }
  })
})
</script>

<style scoped lang="scss">
.monitor-overview-strip {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 14px;
  margin-bottom: 18px;
}

.monitor-overview-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px 16px;
  border: 1px solid $divider-color;
  border-radius: $sm-border-radius;
  background: $bg-color-card;
  box-shadow: $shadow-sm;
  transition: box-shadow $motion-fast ease-out, transform $motion-fast ease-out;

  &:hover {
    box-shadow: $shadow-md;
    transform: translateY(-1px);
  }
}

.monitor-overview-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 42px;
  height: 42px;
  border-radius: 10px;
  flex-shrink: 0;

  .el-icon {
    font-size: 20px;
  }

  &.is-cpu {
    background: rgba(22, 93, 255, 0.1);
    color: #165dff;
  }

  &.is-storage {
    background: rgba(217, 119, 6, 0.1);
    color: #d97706;
  }

  &.is-memory {
    background: rgba(15, 157, 148, 0.1);
    color: #0f9d94;
  }

  &.is-network {
    background: rgba(31, 157, 85, 0.1);
    color: #1f9d55;
  }

  &.is-default {
    background: rgba(59, 130, 246, 0.1);
    color: #3b82f6;
  }
}

.monitor-overview-text {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.monitor-overview-label {
  color: $text-color-placeholder;
  font-size: 12px;
  line-height: 1.3;
}

.monitor-overview-value {
  font-size: 20px;
  font-weight: 700;
  letter-spacing: -0.02em;
  line-height: 1.3;
}

.monitor-overview-sub {
  color: $text-color-placeholder;
  font-size: 11px;
  line-height: 1.3;
}

.monitor-overview-skeleton {
  display: block;
  border-radius: 6px;
  background: linear-gradient(90deg, rgba(226, 232, 240, 0.9), rgba(241, 245, 249, 1), rgba(226, 232, 240, 0.9));
  background-size: 200% 100%;
  animation: monitorOverviewShimmer 1.2s ease-in-out infinite;

  &--value {
    width: 72%;
    height: 22px;
    margin-top: 2px;
  }

  &--sub {
    width: 48%;
    height: 12px;
    margin-top: 4px;
  }
}

@keyframes monitorOverviewShimmer {
  0% { background-position: 100% 50%; }
  100% { background-position: 0 50%; }
}

@media (max-width: 1200px) {
  .monitor-overview-strip {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 900px) {
  .monitor-overview-strip {
    grid-template-columns: 1fr;
  }
}
</style>
