<template>
  <section class="console-panel-section">
    <t-card title="监控信息" :bordered="false">
      <template #actions>
        <t-space class="console-monitor-actions">
          <t-radio-group
            v-model="monitorState.range"
            class="monitor-range-group"
            variant="default-filled"
            @change="handleRangeChange"
          >
            <t-radio-button value="3h">3 小时</t-radio-button>
            <t-radio-button value="24h">24 小时</t-radio-button>
            <t-radio-button value="7d">7 天</t-radio-button>
            <t-radio-button value="30d">30 天</t-radio-button>
          </t-radio-group>
          <t-button class="console-monitor-refresh" :loading="monitorState.loading" @click="loadMonitor(true)"
            >刷新</t-button
          >
        </t-space>
      </template>

      <t-alert v-if="monitorState.error" theme="warning" class="console-inline-alert">{{ monitorState.error }}</t-alert>
      <t-empty v-if="monitorState.supported === false" :description="monitorState.message || '当前实例暂不支持监控'" />
      <div v-else class="monitor-grid">
        <article v-for="chart in monitorChartViews" :key="chart.key" class="monitor-card">
          <header class="monitor-card-header">
            <div>
              <span>{{ chart.label }}</span>
              <strong>{{ chart.latestText }}</strong>
            </div>
            <small v-if="chart.latestTime">{{ chart.latestTime }}</small>
          </header>

          <div v-if="chart.series.length" class="monitor-chart-shell">
            <div class="monitor-y-axis">
              <span v-for="tick in chart.yAxisTicks" :key="tick.key" :style="{ top: `${tick.top}%` }">
                {{ tick.label }}
              </span>
            </div>
            <div class="monitor-chart-main">
              <div class="monitor-chart-plot">
                <svg
                  class="monitor-chart"
                  :viewBox="`0 0 ${MONITOR_CHART_WIDTH} ${MONITOR_CHART_HEIGHT}`"
                  role="img"
                  tabindex="0"
                  :aria-label="`${chart.label}趋势图，聚焦后用左右方向键逐点查看读数`"
                  preserveAspectRatio="none"
                  @mousemove="handleMonitorPointerMove($event, chart)"
                  @mouseleave="clearMonitorPointer"
                  @touchstart.passive="handleMonitorPointerTouch($event, chart)"
                  @touchmove.passive="handleMonitorPointerTouch($event, chart)"
                  @touchend="clearMonitorPointer"
                  @keydown.left.prevent="stepMonitorPointer(chart, -1)"
                  @keydown.right.prevent="stepMonitorPointer(chart, 1)"
                  @keydown.home.prevent="stepMonitorPointer(chart, 'start')"
                  @keydown.end.prevent="stepMonitorPointer(chart, 'end')"
                  @keydown.esc="clearMonitorPointer"
                >
                  <g class="monitor-chart-grid">
                    <line
                      v-for="tick in chart.yAxisTicks"
                      :key="`${tick.key}-line`"
                      x1="0"
                      :y1="tick.y"
                      :x2="MONITOR_CHART_WIDTH"
                      :y2="tick.y"
                    />
                  </g>
                  <line
                    x1="0"
                    :y1="MONITOR_CHART_BOTTOM"
                    :x2="MONITOR_CHART_WIDTH"
                    :y2="MONITOR_CHART_BOTTOM"
                    class="monitor-chart-axis"
                  />
                  <line x1="0" :y1="MONITOR_CHART_TOP" x2="0" :y2="MONITOR_CHART_BOTTOM" class="monitor-chart-axis" />
                  <path
                    v-for="series in chart.series"
                    :key="series.key"
                    class="monitor-chart-line"
                    :d="series.path"
                    :stroke="series.color"
                    :stroke-width="series.lineWidth"
                  />
                  <g v-if="resolveActiveMonitorPointCached(chart)" class="monitor-chart-pointer">
                    <line
                      :x1="resolveActiveMonitorPointCached(chart)?.x"
                      :y1="MONITOR_CHART_TOP"
                      :x2="resolveActiveMonitorPointCached(chart)?.x"
                      :y2="MONITOR_CHART_BOTTOM"
                    />
                    <template
                      v-for="seriesPoint in resolveActiveMonitorPointCached(chart)?.seriesPoints || []"
                      :key="`${seriesPoint.key}-point`"
                    >
                      <circle
                        class="monitor-point-halo"
                        :cx="seriesPoint.x"
                        :cy="seriesPoint.y"
                        r="4.8"
                        :stroke="seriesPoint.color"
                        stroke-width="1.5"
                      />
                      <circle :cx="seriesPoint.x" :cy="seriesPoint.y" r="2.2" :fill="seriesPoint.color" />
                    </template>
                  </g>
                </svg>
                <div
                  v-if="resolveActiveMonitorPointCached(chart)"
                  class="monitor-tooltip"
                  :style="resolveMonitorTooltipStyle(chart)"
                >
                  <strong>{{ resolveActiveMonitorPointCached(chart)?.time }}</strong>
                  <span
                    v-for="seriesPoint in resolveActiveMonitorPointCached(chart)?.seriesPoints || []"
                    :key="seriesPoint.key"
                    class="monitor-tooltip-row"
                  >
                    <i :style="{ backgroundColor: seriesPoint.color }"></i>
                    <b>{{ seriesPoint.name || chart.label }}</b>
                    <em>{{ seriesPoint.valueText }}</em>
                  </span>
                </div>
              </div>
              <div class="monitor-x-axis">
                <span>{{ chart.xAxisLabels.start }}</span>
                <span>{{ chart.xAxisLabels.middle }}</span>
                <span>{{ chart.xAxisLabels.end }}</span>
              </div>
            </div>
          </div>
          <!-- SC-16：单图无数据原来是一段自绘占位文本，与页面其它空态（t-empty）两套语言；
               统一收进 t-empty，尺寸取 small 以贴合卡片内的行高。 -->
          <t-empty
            v-else
            class="monitor-chart-empty"
            size="small"
            :description="chart.message || '当前时间范围内暂无趋势数据'"
          />
          <div class="monitor-metrics">
            <span
              >平均 <strong>{{ chart.averageText }}</strong></span
            >
            <span
              >峰值 <strong>{{ chart.peakText }}</strong></span
            >
            <span
              >最低 <strong>{{ chart.lowestText }}</strong></span
            >
          </div>
        </article>
        <t-empty v-if="!monitorState.loading && !monitorState.charts.length" description="当前时间范围内暂无监控数据" />
      </div>
    </t-card>
  </section>
</template>
<script setup lang="ts">
import { onBeforeUnmount } from 'vue';

import {
  MONITOR_CHART_BOTTOM,
  MONITOR_CHART_HEIGHT,
  MONITOR_CHART_TOP,
  MONITOR_CHART_WIDTH,
  useConsoleMonitor,
} from '../../composables/useConsoleMonitor';
import { useServiceConsoleContext } from '../context';

const { monitorState, loadMonitor } = useServiceConsoleContext();

const {
  monitorChartViews,
  handleMonitorPointerMove,
  handleMonitorPointerTouch,
  stepMonitorPointer,
  clearMonitorPointer,
  resolveActiveMonitorPointCached,
  resolveMonitorTooltipStyle,
} = useConsoleMonitor(monitorState);

// 区间是离散点击，但连续切换会连着打满 4 个区间请求；合并到 200 毫秒只发最后一次。
const RANGE_CHANGE_DELAY_MS = 200;
let rangeChangeTimer: number | null = null;

function clearRangeChangeTimer() {
  if (rangeChangeTimer === null) return;
  window.clearTimeout(rangeChangeTimer);
  rangeChangeTimer = null;
}

function handleRangeChange() {
  clearRangeChangeTimer();
  rangeChangeTimer = window.setTimeout(() => {
    rangeChangeTimer = null;
    void loadMonitor(true);
  }, RANGE_CHANGE_DELAY_MS);
}

onBeforeUnmount(clearRangeChangeTimer);
</script>
