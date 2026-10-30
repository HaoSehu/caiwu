<template>
  <!-- 首屏加载只保留「骨架」一种表达：原先外层 t-loading show-overlay 与 TopPanel 内部骨架并存，
       同一屏同时出现整页遮罩和灰条骨架（审查条目 P2-12）。刷新由各区块自己的骨架承担。 -->
  <div class="dashboard-page">
    <top-panel :stats="dashboardStats" :loading="loading" />

    <todo-panel :todos="dashboardStats.todos" :loading="loading" />

    <section class="chart-grid">
      <t-card :bordered="false" title="商品收入占比" :subtitle="monthLabel || '本月'">
        <div
          v-if="positiveChartData.length"
          ref="productChartRef"
          class="chart-box"
          role="img"
          :aria-label="`商品收入占比环形图，${monthLabel || '本月'}共 ${positiveChartData.length} 个商品分类`"
        />
        <t-skeleton v-else-if="loading" :row-col="chartSkeletonRowCol" animation="gradient" class="chart-skeleton" />
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
        <t-skeleton v-else-if="loading" :row-col="chartSkeletonRowCol" animation="gradient" class="chart-skeleton" />
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
      <service-overview :counts="dashboardStats.counts" :services="dashboardStats.services" :loading="loading" />
    </section>
  </div>
</template>
<script setup lang="ts">
import type { SkeletonRowCol } from 'tdesign-vue-next';
import { MessagePlugin } from 'tdesign-vue-next/es/message/plugin';
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
import { errorMessage } from '@/utils/userMessage';

import QuickActions from './components/QuickActions.vue';
import ServiceOverview from './components/ServiceOverview.vue';
import TodoPanel from './components/TodoPanel.vue';
import TopPanel from './components/TopPanel.vue';

defineOptions({
  name: 'DashboardBase',
});

// 初值为 true：首帧直接出骨架，否则 <t-empty> 会先闪一帧「暂无数据」再被骨架替换
const loading = ref(true);
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

/**
 * 图表布局常量。
 * ECharts 的 grid / series / legend 只接受数值像素，无法直接消费 CSS 令牌，
 * 故把图中魔法值集中到本处以单点维护；数值取 8px 网格整数倍（docs/DESIGN.md §3.3）。
 */
const CHART_GRID = { left: 64, right: 56, top: 32, bottom: 52 };
const LEGEND_ITEM_SIZE = { itemWidth: 10, itemHeight: 10 };
const PIE_RADIUS: string[] = ['45%', '70%'];
const PIE_CENTER: string[] = ['50%', '42%'];
const PIE_BORDER_WIDTH = 2;
const PIE_EMPHASIS_SCALE = 6;
const DAILY_BAR_MAX_WIDTH = 14;
const DAILY_BAR_RADIUS: number[] = [3, 3, 0, 0];
const DAILY_BAR_OPACITY = 0.9;
const DAILY_AREA_OPACITY = 0.12;

/** 图表首屏骨架：与 .chart-box 同高，避免加载完成时页面跳动 */
const chartSkeletonRowCol: SkeletonRowCol = [[{ width: '100%', height: '100%' }]];

/** 读取 TDesign 主题令牌，保证图表配色与浅色主题一致 */
function themeToken(name: string, fallback: string): string {
  const value = getComputedStyle(document.documentElement).getPropertyValue(name).trim();
  return value || fallback;
}

// canvas 动画不受 CSS 的 prefers-reduced-motion 约束，需在 ECharts 侧显式关闭入场动画。
const prefersReducedMotion =
  typeof window !== 'undefined' && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

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
    animation: !prefersReducedMotion,
    color: getChartListColor(),
    tooltip: {
      trigger: 'item',
      valueFormatter: (value: unknown) => formatMoney(value),
    },
    legend: { bottom: 0, type: 'scroll', icon: 'circle' },
    series: [
      {
        type: 'pie',
        radius: PIE_RADIUS,
        center: PIE_CENTER,
        itemStyle: {
          borderColor: themeToken('--td-bg-color-container', '#ffffff'),
          borderWidth: PIE_BORDER_WIDTH,
        },
        label: { formatter: '{b}\n{d}%', color: themeToken('--td-text-color-secondary', '#5B6B82') },
        emphasis: { scale: true, scaleSize: PIE_EMPHASIS_SCALE },
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
    animation: !prefersReducedMotion,
    color: [brandColor, successColor],
    tooltip: {
      trigger: 'axis',
    },
    legend: {
      bottom: 0,
      icon: 'roundRect',
      ...LEGEND_ITEM_SIZE,
      textStyle: { color: axisColor },
    },
    grid: CHART_GRID,
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
        areaStyle: { opacity: DAILY_AREA_OPACITY },
        tooltip: { valueFormatter: (value: unknown) => formatMoney(value) },
        data: dailyRevenue.value.map((item) => Number(item.amount ?? item.income ?? 0)),
      },
      {
        name: '新增用户',
        type: 'bar',
        yAxisIndex: 1,
        barMaxWidth: DAILY_BAR_MAX_WIDTH,
        itemStyle: { borderRadius: DAILY_BAR_RADIUS, opacity: DAILY_BAR_OPACITY },
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
  } catch (error) {
    monthlyRevenue.value = {};
    dashboardStats.value = {};
    // 静默刷新（keep-alive 复访）失败不打断阅读，但首屏失败必须提示，避免空白图表被当成「无数据」
    if (!silent) MessagePlugin.error(errorMessage(error, '仪表盘数据加载失败'));
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
// 图表容器高度：与 ECharts grid 常量配套的单一真值，避免散落裸 px
@chart-height: 320px;

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
  height: @chart-height;
}

.chart-empty {
  padding: var(--td-comp-paddingTB-xxl) 0;
}

.chart-skeleton {
  height: @chart-height;
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
  font-size: var(--td-font-size-body-small);

  em {
    font-style: normal;
  }

  strong {
    color: var(--td-text-color-primary);
    font-variant-numeric: tabular-nums;
  }
}

@media (max-width: 1200px) {
  .chart-grid,
  .side-grid {
    grid-template-columns: minmax(0, 1fr);
  }
}

@media (max-width: 767.98px) {
  // 骨架必须跟着图表盒一起降：只改 .chart-box 会让加载完成瞬间从 320 塌到 260，
  // 正是上面 chartSkeletonRowCol 注释要避免的「页面跳动」。
  .chart-box,
  .chart-skeleton {
    height: 260px;
  }
}
</style>
