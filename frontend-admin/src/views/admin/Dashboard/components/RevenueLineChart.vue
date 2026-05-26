<template>
  <el-card shadow="never" class="panel-card">
    <template #header>
      <div class="panel-header">
        <strong>本月总营销额趋势</strong>
        <span>{{ monthLabel }}</span>
      </div>
    </template>

    <div v-if="chartData.length" ref="chartRef" class="chart-container" />
    <div v-else class="panel-empty">暂无营销数据</div>
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

function buildOption(data) {
  const dates = data.map((item) => `${item.day}日`)
  const amounts = data.map((item) => Number(item.amount) || 0)

  return {
    tooltip: {
      trigger: 'axis',
      confine: true,
      backgroundColor: '#fff',
      borderColor: '#E5EAF3',
      borderWidth: 1,
      textStyle: { color: '#1F2937', fontSize: 12 },
      formatter: ([point]) =>
        `${point.name}　¥${Number(point.value).toFixed(2)}`,
    },
    grid: {
      left: 8,
      right: 12,
      top: 12,
      bottom: 4,
      containLabel: true,
    },
    xAxis: {
      type: 'category',
      data: dates,
      boundaryGap: true,
      axisLabel: {
        fontSize: 10,
        color: '#94A0B2',
        interval: Math.max(0, Math.floor(dates.length / 8) - 1),
      },
      axisTick: { show: false },
      axisLine: { lineStyle: { color: '#EEF2F7' } },
    },
    yAxis: {
      type: 'value',
      axisLabel: {
        fontSize: 10,
        color: '#94A0B2',
        formatter: (v) => `¥${v >= 1000 ? `${(v / 1000).toFixed(1)}k` : v}`,
      },
      splitLine: { lineStyle: { color: '#EEF2F7' } },
    },
    series: [
      {
        type: 'bar',
        data: amounts,
        barWidth: Math.max(10, Math.floor(400 / amounts.length)),
        itemStyle: {
          color: '#165DFF',
          borderRadius: [2, 2, 0, 0],
        },
        emphasis: {
          itemStyle: { color: '#0E4FCC' },
        },
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
