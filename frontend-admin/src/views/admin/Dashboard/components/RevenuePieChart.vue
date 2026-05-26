<template>
  <el-card shadow="never" class="panel-card">
    <template #header>
      <div class="panel-header">
        <strong>本月产品营收占比</strong>
        <span>{{ monthLabel }}</span>
      </div>
    </template>

    <div v-if="chartData.length" ref="chartRef" class="chart-container" />
    <div v-else class="panel-empty">暂无营收数据</div>
  </el-card>
</template>

<script setup>
import { ref, watch, onMounted, onBeforeUnmount, nextTick } from 'vue'
import * as echarts from 'echarts'

const props = defineProps({
  chartData: { type: Array, required: true },
  monthLabel: { type: String, default: '' },
})

const chartRef = ref(null)
let chartInstance = null

// 与 HeadlineGrid 一致的品牌色系
const TONE_COLORS = [
  '#165DFF', '#12B76A', '#F59E0B', '#F04438', '#8B5CF6',
  '#06B6D4', '#EC4899', '#84CC16', '#6366F1',
]

function buildOption(data) {
  return {
    tooltip: {
      trigger: 'item',
      confine: true,
      backgroundColor: '#fff',
      borderColor: '#E5EAF3',
      borderWidth: 1,
      textStyle: { color: '#1F2937', fontSize: 12 },
      formatter: ({ name, value, percent }) =>
        `${name}　¥${Number(value).toFixed(2)}　${percent}%`,
    },
    legend: { show: false },
    series: [
      {
        type: 'pie',
        radius: ['40%', '62%'],
        center: ['50%', '50%'],
        avoidLabelOverlap: true,
        itemStyle: { borderColor: '#fff', borderWidth: 2 },
        label: {
          show: true,
          position: 'outside',
          formatter: '{b} {d}%',
          fontSize: 11,
          color: '#5B6B82',
        },
        labelLine: {
          show: true,
          length: 12,
          length2: 8,
          lineStyle: { color: '#D4D9E1' },
        },
        emphasis: {
          itemStyle: { shadowBlur: 6, shadowColor: 'rgba(0,0,0,0.08)' },
        },
        data: data.map((item, index) => ({
          value: item.amount,
          name: item.label.length > 10 ? item.label.substring(0, 9) + '…' : item.label,
          itemStyle: { color: TONE_COLORS[index % TONE_COLORS.length] },
        })),
      },
    ],
  }
}

function renderChart() {
  if (!chartRef.value || !props.chartData.length) return
  if (!chartInstance) {
    chartInstance = echarts.init(chartRef.value)
  }
  chartInstance.setOption(buildOption(props.chartData), true)
}

function handleResize() {
  chartInstance?.resize()
}

onMounted(() => {
  nextTick(renderChart)
  window.addEventListener('resize', handleResize)
})

onBeforeUnmount(() => {
  window.removeEventListener('resize', handleResize)
  chartInstance?.dispose()
  chartInstance = null
})

watch(() => props.chartData, () => nextTick(renderChart), { deep: true })
</script>

<style lang="scss" scoped>
.panel-card {
  border-radius: $base-border-radius;
  background: $bg-color-card;
}

.panel-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
}

.panel-header strong { color: $text-color-primary; font-size: 15px; font-weight: 600; }
.panel-header span { color: $text-color-placeholder; font-size: 12px; white-space: nowrap; }

.chart-container {
  width: 100%;
  height: 280px;
}

.panel-empty {
  padding: 40px 0;
  text-align: center;
  color: $text-color-placeholder;
  font-size: 13px;
}
</style>
