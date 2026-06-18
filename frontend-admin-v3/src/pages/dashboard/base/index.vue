<template>
  <t-loading :loading="loading" show-overlay>
    <div class="dashboard-page">
      <TopPanel :stats="dashboardStats" />

      <section class="chart-grid">
        <t-card :bordered="false" title="商品收入占比" :subtitle="monthLabel || '本月'">
          <div ref="productChartRef" class="chart-box" />
        </t-card>
        <t-card :bordered="false" title="每日收入趋势" :subtitle="monthLabel || '本月'">
          <div ref="dailyChartRef" class="chart-box" />
        </t-card>
      </section>

      <t-card :bordered="false" title="最近账单" subtitle="展示最近业务流水" class="recent-invoices-card">
        <template #actions>
          <t-button theme="primary" variant="text" @click="router.push('/admin/finance/invoices')">查看全部</t-button>
        </template>
        <t-table
          v-if="recentInvoices.length"
          row-key="id"
          :data="recentInvoices"
          :columns="invoiceColumns"
          :pagination="null"
          table-layout="fixed"
        >
          <template #invoice_no="{ row }">
            <span class="invoice-no">{{ row.invoice_no || `#${row.id}` }}</span>
          </template>
          <template #amount="{ row }">
            <strong>{{ formatCurrency(row.amount) }}</strong>
          </template>
          <template #status="{ row }">
            <t-tag :theme="statusTheme(row.status)" variant="light">{{ statusText(row.status) }}</t-tag>
          </template>
          <template #created_at="{ row }">
            <span class="muted">{{ formatDateTime(row.created_at) }}</span>
          </template>
        </t-table>
        <t-empty v-else title="暂无最近账单" description="有新购、续费或充值账单后会显示在这里。">
          <t-button theme="primary" variant="outline" @click="router.push('/admin/finance/invoices')">进入账单列表</t-button>
        </t-empty>
      </t-card>
    </div>
  </t-loading>
</template>

<script setup lang="ts">
import { LineChart, PieChart } from 'echarts/charts';
import { GridComponent, LegendComponent, TooltipComponent } from 'echarts/components';
import * as echarts from 'echarts/core';
import { CanvasRenderer } from 'echarts/renderers';
import { computed, nextTick, onActivated, onBeforeUnmount, onDeactivated, onMounted, ref, shallowRef, watch } from 'vue';
import { useRouter } from 'vue-router';

import { adminApi, type DashboardStats, type MonthlyRevenue, type RecentInvoice } from '@/api/admin';
import { INVOICE_STATUS_MAP, toLabelMap, toTagTypeMap } from '@shared/statusConfig';
import { formatDateTime } from '@/utils/format';

import TopPanel from './components/TopPanel.vue';

defineOptions({
  name: 'DashboardBase',
});

echarts.use([TooltipComponent, LegendComponent, GridComponent, LineChart, PieChart, CanvasRenderer]);

const router = useRouter();
const loading = ref(false);
const recentInvoices = ref<RecentInvoice[]>([]);
const monthlyRevenue = shallowRef<MonthlyRevenue>({});
const dashboardStats = shallowRef<DashboardStats>({});
const productChartRef = ref<HTMLDivElement>();
const dailyChartRef = ref<HTMLDivElement>();
let productChart: echarts.ECharts | null = null;
let dailyChart: echarts.ECharts | null = null;

const statusLabelMap = toLabelMap(INVOICE_STATUS_MAP);
const statusTypeMap = toTagTypeMap(INVOICE_STATUS_MAP);

const invoiceColumns = [
  { colKey: 'invoice_no', title: '账单号', minWidth: 180 },
  { colKey: 'amount', title: '金额', width: 140, align: 'right' },
  { colKey: 'status', title: '状态', width: 120 },
  { colKey: 'created_at', title: '创建时间', width: 180 },
];

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

  return Array.from(grouped, ([name, value]) => ({ name, value })).filter((item) => item.value > 0);
});

function formatCurrency(value: unknown) {
  return `¥${Number(value || 0).toFixed(2)}`;
}

function statusText(status: unknown) {
  return statusLabelMap[String(status)] || '未知';
}

function statusTheme(status: unknown) {
  const tagType = statusTypeMap[String(status)] || 'default';
  if (tagType === 'success') return 'success';
  if (tagType === 'warning') return 'warning';
  if (tagType === 'danger') return 'danger';
  if (tagType === 'primary' || tagType === '') return 'primary';
  return 'default';
}

function ensureCharts() {
  if (productChartRef.value && !productChart) {
    productChart = echarts.init(productChartRef.value);
  }
  if (dailyChartRef.value && !dailyChart) {
    dailyChart = echarts.init(dailyChartRef.value);
  }
}

function renderCharts() {
  ensureCharts();

  productChart?.setOption({
    tooltip: { trigger: 'item' },
    legend: { bottom: 0, type: 'scroll' },
    series: [
      {
        type: 'pie',
        radius: ['45%', '70%'],
        center: ['50%', '42%'],
        data: productChartData.value,
      },
    ],
  });

  dailyChart?.setOption({
    tooltip: { trigger: 'axis' },
    grid: { left: 36, right: 24, top: 24, bottom: 36 },
    xAxis: {
      type: 'category',
      data: dailyRevenue.value.map((item) => item.date || item.day || ''),
    },
    yAxis: { type: 'value' },
    series: [
      {
        type: 'line',
        smooth: true,
        areaStyle: {},
        data: dailyRevenue.value.map((item) => Number(item.income ?? item.amount ?? 0)),
      },
    ],
  });
}

function resizeCharts() {
  productChart?.resize();
  dailyChart?.resize();
}

async function loadDashboard() {
  loading.value = true;
  try {
    const [recentInvoicesRes, monthlyRevenueRes, statsRes] = await Promise.all([
      adminApi.dashboardRecentInvoices(),
      adminApi.dashboardMonthlyRevenue(),
      adminApi.dashboardStats(),
    ]);
    recentInvoices.value = recentInvoicesRes?.recent_invoices || [];
    monthlyRevenue.value = monthlyRevenueRes || {};
    dashboardStats.value = statsRes || {};
  } catch {
    recentInvoices.value = [];
    monthlyRevenue.value = {};
    dashboardStats.value = {};
  } finally {
    loading.value = false;
    await nextTick();
    renderCharts();
  }
}

watch([productChartData, dailyRevenue], () => {
  nextTick(renderCharts);
});

onMounted(() => {
  loadDashboard();
  window.addEventListener('resize', resizeCharts);
});

onActivated(() => {
  // keep-alive 命中时(若后续放开保活)重新拉取并渲染
  loadDashboard();
});

onDeactivated(() => {
  // 切走时释放 echarts 实例，避免常驻内存
  productChart?.dispose();
  productChart = null;
  dailyChart?.dispose();
  dailyChart = null;
});

onBeforeUnmount(() => {
  window.removeEventListener('resize', resizeCharts);
  productChart?.dispose();
  productChart = null;
  dailyChart?.dispose();
  dailyChart = null;
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

.chart-box {
  width: 100%;
  height: 320px;
}

.recent-invoices-card :deep(.t-empty) {
  padding: var(--td-comp-paddingTB-xxl) 0;
}

.invoice-no {
  font-family: SFMono-Regular, Consolas, 'Liberation Mono', monospace;
}

.muted {
  color: var(--td-text-color-secondary);
}

@media (max-width: 1200px) {
  .chart-grid {
    grid-template-columns: minmax(0, 1fr);
  }
}

@media (max-width: 640px) {
  .chart-box {
    height: 260px;
  }
}
</style>
