<template>
  <t-loading :loading="loading" show-overlay>
    <div class="dashboard-page">
      <top-panel :stats="dashboardStats" :loading="loading" />

      <todo-panel :todos="dashboardStats.todos" />

      <section class="chart-grid">
        <t-card :bordered="false" title="商品收入占比" :subtitle="monthLabel || '本月'">
          <div
            v-if="positiveChartData.length"
            ref="productChartRef"
            class="chart-box"
            role="img"
            :aria-label="`商品收入占比环形图，${monthLabel || '本月'}共 ${positiveChartData.length} 个商品分类`"
          />
          <t-empty
            v-else
            class="chart-empty"
            title="本月暂无正向收入"
            description="整月退款冲抵或暂无已付收入时不生成占比图。"
          />
          <div v-if="positiveChartData.length" class="chart-summary">
            <span class="chart-summary__item">
              <em>正向收入合计</em>
              <strong>{{ formatMoney(positiveTotal) }}</strong>
            </span>
            <span v-if="refundAdjustment > 0" class="chart-summary__item">
              <em>含退款冲抵</em>
              <strong>{{ formatMoney(refundAdjustment) }}</strong>
            </span>
          </div>
        </t-card>
        <t-card :bordered="false" title="每日收入与新增用户" :subtitle="monthLabel || '本月'">
          <div
            v-if="dailyRevenue.length"
            ref="dailyChartRef"
            class="chart-box"
            role="img"
            :aria-label="`每日收入与新增用户组合图，${monthLabel || '本月'}共 ${dailyRevenue.length} 天`"
          />
          <t-empty
            v-else
            class="chart-empty"
            title="暂无收入趋势"
            description="本月尚未产生已付账单，或收入数据加载失败。"
          />
        </t-card>
      </section>

      <section class="side-grid">
        <quick-actions />
        <service-overview :counts="dashboardStats.counts" :services="dashboardStats.services" />
      </section>
    </div>
  </t-loading>
</template>
<script setup lang="ts">
import {
  computed,
  nextTick,
  onActivated,
  onBeforeUnmount,
  onDeactivated,
  onMounted,
  ref,
  shallowRef,
  watch,
} from 'vue';

import type { DashboardStats, MonthlyRevenue } from '@/api/admin';
import { adminApi } from '@/api/admin';
import { getChartListColor } from '@/utils/color';
import echarts from '@/utils/echarts';
import { formatMoney } from '@/utils/format';

import QuickActions from './components/QuickActions.vue';
import ServiceOverview from './components/ServiceOverview.vue';
import TodoPanel from './components/TodoPanel.vue';
import TopPanel from './components/TopPanel.vue';

defineOptions({
  name: 'DashboardBase',
});

const loading = ref(false);
const monthlyRevenue = shallowRef<MonthlyRevenue>({});
const dashboardStats = shallowRef<DashboardStats>({});
const productChartRef = ref<HTMLDivElement>();
const dailyChartRef = ref<HTMLDivElement>();
let resizeFrame = 0;
let productChart: echarts.ECharts | null = null;
let dailyChart: echarts.ECharts | null = null;

const monthLabel = computed(() => monthlyRevenue.value.month_label || '');
const revenueByProduct = computed(() => monthlyRevenue.value.revenue_by_product || []);
const dailyRevenue = computed(() => monthlyRevenue.value.daily_revenue || []);

const productChartData = computed(() => {
  const grouped = new Map<string, number>();

  revenueByProduct.value.forEach((item) => {
    const name = String(item.label || item.product_name || item.name || '未知产品').trim() || '未知产品';
    const value = Number(item.income ?? item.amount ?? item.value ?? 0);

    grouped.set(name, (grouped.get(name) || 0) + value);
  });

  return Array.from(grouped, ([name, value]) => ({ name, value }));
});

// 饼图只绘制正向收入；退款冲抵单独提示，避免整月净退款时图表空白无解释
const positiveChartData = computed(() => productChartData.value.filter((item) => item.value > 0));
const positiveTotal = computed(() => positiveChartData.value.reduce((sum, item) => sum + item.value, 0));
const refundAdjustment = computed(() =>
  productChartData.value.filter((item) => item.value < 0).reduce((sum, item) => sum - item.value, 0),
);

/** 读取 TDesign 主题令牌，保证图表配色与浅色主题一致 */
function themeToken(name: string, fallback: string): string {
  const value = getComputedStyle(document.documentElement).getPropertyValue(name).trim();
  return value || fallback;
}

function renderProductChart() {
  // 数据为空时容器被 v-if 卸载，须释放实例，避免向脱离文档的节点写入
  if (!positiveChartData.value.length || !productChartRef.value) {
    productChart?.dispose();
    productChart = null;
    return;
  }
  if (!productChart) {
    productChart = echarts.init(productChartRef.value);
  }
  productChart.setOption({
    color: getChartListColor(),
    tooltip: {
      trigger: 'item',
      valueFormatter: (value: unknown) => formatMoney(value),
    },
    legend: { bottom: 0, type: 'scroll', icon: 'circle' },
    series: [
      {
        type: 'pie',
        radius: ['45%', '70%'],
        center: ['50%', '42%'],
        itemStyle: { borderColor: themeToken('--td-bg-color-container', '#ffffff'), borderWidth: 2 },
        label: { formatter: '{b}\n{d}%', color: themeToken('--td-text-color-secondary', '#5B6B82') },
        emphasis: { scale: true, scaleSize: 6 },
        data: positiveChartData.value,
      },
    ],
  });
}

function renderDailyChart() {
  if (!dailyRevenue.value.length || !dailyChartRef.value) {
    dailyChart?.dispose();
    dailyChart = null;
    return;
  }
  if (!dailyChart) {
    dailyChart = echarts.init(dailyChartRef.value);
  }
  const axisColor = themeToken('--td-text-color-placeholder', '#606D80');
  const splitColor = themeToken('--td-border-level-1-color', '#E5EAF3');
  const brandColor = themeToken('--td-brand-color', '#165DFF');
  const successColor = themeToken('--td-success-color-6', '#12B76A');

  dailyChart.setOption({
    color: [brandColor, successColor],
    tooltip: {
      trigger: 'axis',
    },
    legend: { bottom: 0, icon: 'roundRect', itemWidth: 10, itemHeight: 10, textStyle: { color: axisColor } },
    grid: { left: 64, right: 56, top: 32, bottom: 52 },
    xAxis: {
      type: 'category',
      boundaryGap: true,
      data: dailyRevenue.value.map((item) => item.date || item.day || ''),
      axisLabel: {
        color: axisColor,
        formatter: (value: string) => String(value).slice(5),
      },
      axisLine: { lineStyle: { color: splitColor } },
    },
    yAxis: [
      {
        type: 'value',
        name: '收入',
        nameTextStyle: { color: axisColor },
        axisLabel: { color: axisColor, formatter: (value: number) => formatMoney(value) },
        splitLine: { lineStyle: { color: splitColor } },
      },
      {
        type: 'value',
        name: '新增用户',
        minInterval: 1,
        nameTextStyle: { color: axisColor },
        axisLabel: { color: axisColor },
        splitLine: { show: false },
      },
    ],
    series: [
      {
        name: '收入',
        type: 'line',
        smooth: true,
        showSymbol: false,
        yAxisIndex: 0,
        areaStyle: { opacity: 0.12 },
        tooltip: { valueFormatter: (value: unknown) => formatMoney(value) },
        data: dailyRevenue.value.map((item) => Number(item.amount ?? item.income ?? 0)),
      },
      {
        name: '新增用户',
        type: 'bar',
        yAxisIndex: 1,
        barMaxWidth: 14,
        itemStyle: { borderRadius: [3, 3, 0, 0], opacity: 0.9 },
        tooltip: { valueFormatter: (value: unknown) => `${Number(value ?? 0)} 人` },
        data: dailyRevenue.value.map((item) => Number(item.new_users ?? 0)),
      },
    ],
  });
}

function renderCharts() {
  renderProductChart();
  renderDailyChart();
}

function resizeCharts() {
  productChart?.resize();
  dailyChart?.resize();
}

function scheduleResizeCharts() {
  if (resizeFrame) return;
  resizeFrame = window.requestAnimationFrame(() => {
    resizeFrame = 0;
    resizeCharts();
  });
}

function disposeCharts() {
  productChart?.dispose();
  productChart = null;
  dailyChart?.dispose();
  dailyChart = null;
}

/**
 * @param options.silent 静默刷新：不触发整页 loading 遮罩与骨架，仅刷新数据与图表
 */
async function loadDashboard(options: { silent?: boolean } = {}) {
  const silent = options.silent === true;
  if (!silent) loading.value = true;
  try {
    const [monthlyRevenueRes, statsRes] = await Promise.all([
      adminApi.dashboardMonthlyRevenue(),
      adminApi.dashboardStats(),
    ]);
    monthlyRevenue.value = monthlyRevenueRes || {};
    dashboardStats.value = statsRes || {};
  } catch {
    monthlyRevenue.value = {};
    dashboardStats.value = {};
  } finally {
    loading.value = false;
    await nextTick();
    renderCharts();
  }
}

watch(monthlyRevenue, () => {
  nextTick(renderCharts);
});

onMounted(() => {
  loadDashboard();
  window.addEventListener('resize', scheduleResizeCharts);
});

onActivated(() => {
  // keep-alive 命中时(若后续放开保活)重新拉取并渲染，静默避免骨架闪烁
  loadDashboard({ silent: true });
});

onDeactivated(() => {
  // 切走时释放 echarts 实例，避免常驻内存
  disposeCharts();
});

onBeforeUnmount(() => {
  window.removeEventListener('resize', scheduleResizeCharts);
  if (resizeFrame) {
    window.cancelAnimationFrame(resizeFrame);
    resizeFrame = 0;
  }
  disposeCharts();
});
</script>
<style lang="less" scoped>
.dashboard-page {
  display: flex;
  flex-direction: column;
  gap: var(--td-comp-margin-xl);
}

.chart-grid {
  display: grid;
  grid-template-columns: minmax(0, 0.9fr) minmax(0, 1.4fr);
  gap: var(--td-comp-margin-l);
}

.side-grid {
  display: grid;
  grid-template-columns: minmax(0, 1.1fr) minmax(0, 1fr);
  gap: var(--td-comp-margin-l);
  align-items: start;
}

.chart-box {
  width: 100%;
  height: 320px;
}

.chart-empty {
  padding: var(--td-comp-paddingTB-xxl) 0;
}

.chart-summary {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: var(--td-comp-margin-xl);
  margin-top: var(--td-comp-margin-s);
}

.chart-summary__item {
  display: inline-flex;
  align-items: baseline;
  gap: var(--td-comp-margin-xs);
  color: var(--td-text-color-placeholder);
  font-size: var(--td-font-size-body-small, 12px);

  em {
    font-style: normal;
  }

  strong {
    color: var(--td-text-color-primary);
    font-variant-numeric: tabular-nums;
  }
}

@media (width <= 1200px) {
  .chart-grid,
  .side-grid {
    grid-template-columns: minmax(0, 1fr);
  }
}

@media (width <= 640px) {
  .chart-box {
    height: 260px;
  }
}
</style>
