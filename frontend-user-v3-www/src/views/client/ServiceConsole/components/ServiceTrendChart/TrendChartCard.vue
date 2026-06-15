<template>
  <article class="trend-card" :class="{ 'is-loading': loading }" :style="cardStyle">
    <Teleport to="body">
      <div v-if="activePoint" class="trend-tooltip" :style="tooltipStyle">
        <strong>{{ activePoint.time }}</strong>
        <template v-if="activePoint.seriesPoints.length <= 1">
          <span>{{ activePoint.valueText }}</span>
        </template>
        <div v-else class="trend-tooltip__series">
          <span v-for="seriesPoint in activePoint.seriesPoints" :key="seriesPoint.key" class="trend-tooltip__row">
            <i :style="{ backgroundColor: seriesPoint.color }"></i>
            <b>{{ seriesPoint.name }}</b>
            <em>{{ seriesPoint.valueText }}</em>
          </span>
        </div>
      </div>
    </Teleport>
    <div class="trend-head">
      <div class="trend-head-main">
        <div class="trend-head-copy">
          <h4>{{ displayTitle }}</h4>
          <p v-if="subtitle">{{ subtitle }}</p>
        </div>
      </div>
      <el-tag size="small" effect="plain" class="trend-unit-tag">
        <span v-if="loading" class="trend-skeleton trend-skeleton-tag"></span>
        <template v-else>{{ unitText }}</template>
      </el-tag>
    </div>

    <div class="trend-summary" :style="summaryGridStyle">
      <div
        v-for="item in summaryCards"
        :key="item.key"
        class="trend-summary-item"
        :style="{ '--trend-summary-color': item.color }"
      >
        <span>{{ item.label }}</span>
        <i v-if="loading" class="trend-skeleton trend-skeleton-value"></i>
        <strong v-else>{{ item.value }}</strong>
      </div>
    </div>

    <div class="trend-canvas">
      <div v-if="loading" class="trend-loading-canvas" aria-hidden="true">
        <span class="trend-loading-line trend-loading-line--one"></span>
        <span class="trend-loading-line trend-loading-line--two"></span>
        <span class="trend-loading-line trend-loading-line--three"></span>
      </div>
      <div v-else-if="hasData" class="trend-chart-shell">
        <div class="trend-value-axis">
          <span
            v-for="tick in valueAxisTicks"
            :key="tick.key"
            class="trend-value-axis__tick"
            :style="{ top: `${tick.top}%` }"
          >
            {{ tick.label }}
          </span>
        </div>

        <div class="trend-canvas-inner">
          <svg
            viewBox="0 0 320 160"
            preserveAspectRatio="none"
            aria-hidden="true"
            @mousemove="handlePointerMove"
            @mouseleave="clearActivePoint"
          >
            <defs>
              <linearGradient :id="gradientId" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" :stop-color="metricMeta.gradientTop" />
                <stop offset="100%" :stop-color="metricMeta.gradientBottom" />
              </linearGradient>
            </defs>
            <g class="trend-grid">
              <line
                v-for="tick in valueAxisTicks"
                :key="`${tick.key}-line`"
                x1="0"
                :y1="tick.y"
                :x2="CHART_WIDTH"
                :y2="tick.y"
              />
            </g>
            <path v-if="areaPath" :d="areaPath" :fill="`url(#${gradientId})`" />
            <template v-for="series in plottedSeries" :key="`${series.key}-line`">
              <path
                class="trend-line trend-line-shadow"
                :d="series.linePath"
                fill="none"
                :stroke="series.shadowColor"
                :stroke-width="series.shadowWidth"
                stroke-linecap="round"
                stroke-linejoin="round"
              />
              <path
                class="trend-line"
                :d="series.linePath"
                fill="none"
                :stroke="series.color"
                :stroke-width="series.lineWidth"
                stroke-linecap="round"
                stroke-linejoin="round"
              />
            </template>
            <circle
              v-if="!isMultiSeries"
              v-for="point in markerPoints"
              :key="point.key"
              :cx="point.x"
              :cy="point.y"
              :r="point.emphasis ? 3.4 : 2.4"
              :fill="point.emphasis ? metricMeta.color : '#ffffff'"
              :stroke="point.emphasis ? '#ffffff' : metricMeta.color"
              :stroke-width="point.emphasis ? 1.6 : 1.3"
            />
            <g v-if="activePoint">
              <line
                class="trend-hover-line"
                :x1="activePoint.x"
                y1="0"
                :x2="activePoint.x"
                :y2="CHART_HEIGHT"
                :stroke="metricMeta.color"
                stroke-width="1.1"
                stroke-dasharray="4 4"
              />
              <template v-for="seriesPoint in activePoint.seriesPoints" :key="`${seriesPoint.key}-point`">
                <circle
                  :cx="seriesPoint.x"
                  :cy="seriesPoint.y"
                  r="5.4"
                  fill="#ffffff"
                  :stroke="seriesPoint.color"
                  stroke-width="1.8"
                />
                <circle
                  :cx="seriesPoint.x"
                  :cy="seriesPoint.y"
                  r="2.6"
                  :fill="seriesPoint.color"
                />
              </template>
            </g>
          </svg>
        </div>
      </div>
      <div v-else class="trend-canvas-empty">{{ errorText || '当前时间范围内暂无监控数据' }}</div>
    </div>

    <div class="trend-axis-shell">
      <span class="trend-axis-shell__spacer" aria-hidden="true"></span>
      <div class="trend-axis">
        <template v-if="loading">
          <span class="trend-skeleton trend-skeleton-axis"></span>
          <span class="trend-skeleton trend-skeleton-axis"></span>
          <span class="trend-skeleton trend-skeleton-axis"></span>
        </template>
        <template v-else-if="hasData">
          <span class="trend-axis__label trend-axis__label--start">{{ axis.start }}</span>
          <span class="trend-axis__label trend-axis__label--middle">{{ axis.middle }}</span>
          <span class="trend-axis__label trend-axis__label--end">{{ axis.end }}</span>
        </template>
      </div>
    </div>
  </article>
</template>

<script setup>
import { computed, ref } from 'vue'

const CHART_WIDTH = 320
const CHART_HEIGHT = 160
const CHART_PADDING_X = 0
const CHART_PADDING_Y = 16

const props = defineProps({
  title: {
    type: String,
    default: '',
  },
  subtitle: {
    type: String,
    default: '',
  },
  chart: {
    type: Object,
    default: () => ({}),
  },
  summary: {
    type: Object,
    default: null,
  },
  loading: {
    type: Boolean,
    default: false,
  },
  errorText: {
    type: String,
    default: '',
  },
})

const gradientId = `trend-gradient-${Math.random().toString(36).slice(2, 10)}`
const activePointIndex = ref(-1)
const pointerClientPos = ref({ x: 0, y: 0 })
const VALUE_AXIS_TICK_SEGMENTS = 3

const unitText = computed(() => String(props.chart?.unit || '无单位').trim() || '无单位')
const displayTitle = computed(() => {
  const rawTitle = String(props.title || '').trim()
  if (rawTitle === '') {
    return '趋势图'
  }

  if (rawTitle.toLowerCase() === 'cpu') {
    return 'CPU'
  }

  if (rawTitle.toLowerCase() === 'flow') {
    return '带宽'
  }

  return rawTitle.replace(/io/ig, 'I/O')
})

const metricMeta = computed(() => {
  const keyword = `${displayTitle.value} ${unitText.value}`.toLowerCase()

  if (keyword.includes('cpu') || keyword.includes('%')) {
    return {
      tone: 'cpu',
      color: '#165dff',
      softColor: 'rgba(22, 93, 255, 0.10)',
      borderColor: 'rgba(22, 93, 255, 0.22)',
      shadowColor: 'rgba(22, 93, 255, 0.08)',
      gradientTop: 'rgba(22, 93, 255, 0.12)',
      gradientBottom: 'rgba(22, 93, 255, 0.02)',
    }
  }

  if (keyword.includes('内存') || keyword.includes('gb')) {
    return {
      tone: 'memory',
      color: '#0f9d94',
      softColor: 'rgba(15, 157, 148, 0.10)',
      borderColor: 'rgba(15, 157, 148, 0.22)',
      shadowColor: 'rgba(15, 157, 148, 0.08)',
      gradientTop: 'rgba(15, 157, 148, 0.12)',
      gradientBottom: 'rgba(15, 157, 148, 0.02)',
    }
  }

  if (keyword.includes('带宽') || keyword.includes('mbps') || keyword.includes('bps')) {
    return {
      tone: 'network',
      color: '#1f9d55',
      softColor: 'rgba(31, 157, 85, 0.10)',
      borderColor: 'rgba(31, 157, 85, 0.22)',
      shadowColor: 'rgba(31, 157, 85, 0.08)',
      gradientTop: 'rgba(31, 157, 85, 0.12)',
      gradientBottom: 'rgba(31, 157, 85, 0.02)',
    }
  }

  if (keyword.includes('硬盘') || keyword.includes('i/o') || keyword.includes('mb/s')) {
    return {
      tone: 'storage',
      color: '#d97706',
      softColor: 'rgba(217, 119, 6, 0.10)',
      borderColor: 'rgba(217, 119, 6, 0.22)',
      shadowColor: 'rgba(217, 119, 6, 0.08)',
      gradientTop: 'rgba(217, 119, 6, 0.12)',
      gradientBottom: 'rgba(217, 119, 6, 0.02)',
    }
  }

  return {
    tone: 'default',
    color: '#3b82f6',
    softColor: 'rgba(59, 130, 246, 0.10)',
    borderColor: 'rgba(59, 130, 246, 0.22)',
    shadowColor: 'rgba(59, 130, 246, 0.08)',
    gradientTop: 'rgba(59, 130, 246, 0.12)',
    gradientBottom: 'rgba(59, 130, 246, 0.02)',
  }
})

const cardStyle = computed(() => ({
  '--trend-accent': metricMeta.value.color,
  '--trend-accent-soft': metricMeta.value.softColor,
  '--trend-accent-border': metricMeta.value.borderColor,
}))

const secondarySeriesPalette = computed(() => {
  switch (metricMeta.value.tone) {
    case 'storage':
      return { color: '#d97706', shadowColor: 'rgba(217, 119, 6, 0.08)' }
    case 'memory':
      return { color: '#165dff', shadowColor: 'rgba(22, 93, 255, 0.08)' }
    case 'network':
      return { color: '#0ea5e9', shadowColor: 'rgba(14, 165, 233, 0.08)' }
    default:
      return { color: '#64748b', shadowColor: 'rgba(100, 116, 139, 0.08)' }
  }
})

const rawSeries = computed(() => (
  Array.isArray(props.chart?.series)
    ? props.chart.series.filter((item) => item && typeof item === 'object')
    : []
))

const chartSeries = computed(() => {
  const source = rawSeries.value.length
    ? rawSeries.value
    : [{ key: 'primary', name: displayTitle.value, list: props.chart?.list || [] }]

  return source
    .map((series, index) => {
      const list = Array.isArray(series?.list)
        ? series.list.filter((item) => item && Number.isFinite(Number(item.value)))
        : []

      if (!list.length) {
        return null
      }

      const name = String(series?.name || displayTitle.value || `系列 ${index + 1}`).trim() || `系列 ${index + 1}`
      const palette = resolveSeriesPalette(name)

      return {
        key: String(series?.key || name || `series-${index + 1}`),
        name,
        color: palette.color,
        shadowColor: palette.shadowColor,
        list,
      }
    })
    .filter(Boolean)
    .sort((left, right) => resolveSeriesPriority(right.name) - resolveSeriesPriority(left.name))
    .map((series, index) => ({
      ...series,
      lineWidth: index === 0 ? 2 : 1.7,
      shadowWidth: index === 0 ? 4.6 : 3.6,
    }))
})

const chartPoints = computed(() => chartSeries.value[0]?.list || [])
const hasData = computed(() => chartSeries.value.length > 0)
const isMultiSeries = computed(() => chartSeries.value.length > 1)

const summaryCards = computed(() => {
  const seriesSummary = Array.isArray(props.summary?.series) ? props.summary.series.filter((item) => item && typeof item === 'object') : []
  if (seriesSummary.length > 1) {
    return seriesSummary.slice(0, 4).map((item, index) => ({
      key: String(item.key || `series-summary-${index + 1}`),
      label: String(item.label || `指标 ${index + 1}`),
      value: String(item.latest?.text || '--'),
      color: resolveSeriesPalette(item.label || item.key || '').color,
    }))
  }

  return [
    { key: 'latest', label: '最新值', value: props.summary?.latest?.text || '--', color: metricMeta.value.color },
    { key: 'average', label: '平均值', value: props.summary?.average?.text || '--', color: metricMeta.value.color },
    { key: 'peak', label: '峰值', value: props.summary?.peak?.text || '--', color: metricMeta.value.color },
  ]
})

const summaryGridStyle = computed(() => {
  const count = summaryCards.value.length
  if (count <= 1) {
    return { gridTemplateColumns: '1fr' }
  }

  if (count === 2) {
    return { gridTemplateColumns: 'repeat(2, minmax(0, 1fr))' }
  }

  if (count === 4) {
    return { gridTemplateColumns: 'repeat(2, minmax(0, 1fr))' }
  }

  return { gridTemplateColumns: 'repeat(3, minmax(0, 1fr))' }
})

const allSeriesValues = computed(() => (
  chartSeries.value.flatMap((series) => series.list.map((item) => Number(item.value)))
    .filter((value) => Number.isFinite(value))
))

const valueBounds = computed(() => {
  if (!allSeriesValues.value.length) {
    return { min: 0, max: 0, range: 0 }
  }

  const dataMin = Math.min(...allSeriesValues.value)
  const dataMax = Math.max(...allSeriesValues.value)

  // 后端可传 y_max（如内存总量），用作 Y 轴上限，min 固定为 0
  const yMax = Number(props.chart?.y_max)
  if (Number.isFinite(yMax) && yMax > 0 && yMax >= dataMax) {
    return {
      min: 0,
      max: yMax,
      range: yMax,
    }
  }

  const min = dataMin >= 0 ? 0 : dataMin
  const max = buildNiceAxisMax(dataMax, min)

  return {
    min,
    max,
    range: max - min,
  }
})

const plottedSeries = computed(() => {
  if (!chartSeries.value.length) {
    return []
  }

  const usableWidth = CHART_WIDTH - CHART_PADDING_X * 2
  const usableHeight = CHART_HEIGHT - CHART_PADDING_Y * 2

  return chartSeries.value.map((series, seriesIndex) => {
    const points = series.list.map((item, index) => {
      const numericValue = Number(item.value || 0)
      const x = series.list.length === 1
        ? CHART_WIDTH / 2
        : CHART_PADDING_X + (usableWidth * index) / (series.list.length - 1)
      const normalized = valueBounds.value.range === 0
        ? 0.5
        : (numericValue - valueBounds.value.min) / valueBounds.value.range
      const y = CHART_PADDING_Y + usableHeight - usableHeight * normalized

      return {
        key: `${series.key}-${item.timestamp || item.time}-${index}`,
        index,
        value: numericValue,
        time: resolvePointTimeText(item),
        x: Number(x.toFixed(2)),
        y: Number(y.toFixed(2)),
      }
    })

    return {
      ...series,
      points,
      linePath: buildSmoothPath(points),
      isPrimary: seriesIndex === 0,
    }
  })
})

const plottedPoints = computed(() => plottedSeries.value[0]?.points || [])

const markerIndexes = computed(() => {
  const points = plottedPoints.value
  if (!points.length) {
    return new Set()
  }

  const indexes = new Set([
    points.length - 1,
  ])
  const values = points.map((point) => point.value)
  indexes.add(values.indexOf(Math.max(...values)))

  if (points.length <= 8) {
    indexes.add(0)
  }

  return indexes
})

const markerPoints = computed(() => {
  const points = plottedPoints.value
  if (!points.length) {
    return []
  }

  const maxValue = Math.max(...points.map((point) => point.value))

  return points
    .filter((point) => markerIndexes.value.has(point.index))
    .map((point) => ({
      ...point,
      emphasis: point.index === points.length - 1 || point.value === maxValue,
    }))
})

const activePoint = computed(() => {
  if (activePointIndex.value < 0 || activePointIndex.value >= plottedPoints.value.length) {
    return null
  }

  const point = plottedPoints.value[activePointIndex.value]
  if (!point) {
    return null
  }

  const seriesPoints = plottedSeries.value
    .map((series) => {
      const targetPoint = series.points[Math.min(activePointIndex.value, series.points.length - 1)]
      if (!targetPoint) {
        return null
      }

      return {
        key: series.key,
        name: series.name,
        color: series.color,
        x: targetPoint.x,
        y: targetPoint.y,
        valueText: formatValueText(targetPoint.value),
      }
    })
    .filter(Boolean)

  return {
    ...point,
    valueText: seriesPoints[0]?.valueText || formatValueText(point.value),
    seriesPoints,
  }
})

const tooltipStyle = computed(() => {
  if (!activePoint.value) {
    return { display: 'none' }
  }

  return {
    left: `${pointerClientPos.value.x}px`,
    top: `${pointerClientPos.value.y}px`,
    transform: 'translate(-50%, calc(-100% - 12px))',
  }
})

const valueAxisTicks = computed(() => {
  if (!allSeriesValues.value.length) {
    return []
  }

  const topStart = (CHART_PADDING_Y / CHART_HEIGHT) * 100
  const topEnd = ((CHART_HEIGHT - CHART_PADDING_Y) / CHART_HEIGHT) * 100

  return Array.from({ length: VALUE_AXIS_TICK_SEGMENTS + 1 }, (_, index) => {
    const ratio = index / VALUE_AXIS_TICK_SEGMENTS
    const value = valueBounds.value.range === 0
      ? valueBounds.value.max
      : valueBounds.value.max - valueBounds.value.range * ratio
    const top = topStart + (topEnd - topStart) * ratio
    const y = CHART_PADDING_Y + (CHART_HEIGHT - CHART_PADDING_Y * 2) * ratio

    return {
      key: `tick-${index}`,
      top: Number(top.toFixed(2)),
      y: Number(y.toFixed(2)),
      label: formatValue(value),
    }
  })
})

const areaPath = computed(() => {
  if (isMultiSeries.value || !plottedPoints.value.length) {
    return ''
  }

  const first = plottedPoints.value[0]
  const last = plottedPoints.value[plottedPoints.value.length - 1]
  const line = buildSmoothPath(plottedPoints.value)

  return `${line} L ${last.x},${CHART_HEIGHT} L ${first.x},${CHART_HEIGHT} Z`
})

const axis = computed(() => {
  const points = chartPoints.value
  if (!points.length) {
    return { start: '--', middle: '--', end: '--' }
  }

  const middle = points[Math.floor((points.length - 1) / 2)]
  const sameDay = isSamePointDate(points[0], points[points.length - 1])

  return {
    start: resolvePointAxisText(points[0], sameDay),
    middle: resolvePointAxisText(middle, sameDay),
    end: resolvePointAxisText(points[points.length - 1], sameDay),
  }
})

function buildNiceAxisMax(max, min) {
  const range = Math.max(max - min, Math.abs(max), 1)
  const roughMax = max + range * 0.08
  if (roughMax <= 0) {
    return 1
  }

  const magnitude = 10 ** Math.floor(Math.log10(roughMax))
  const normalized = roughMax / magnitude
  let niceNormalized = 10

  if (normalized <= 1) {
    niceNormalized = 1
  } else if (normalized <= 2) {
    niceNormalized = 2
  } else if (normalized <= 5) {
    niceNormalized = 5
  }

  return niceNormalized * magnitude
}

function buildSmoothPath(points) {
  if (!Array.isArray(points) || points.length === 0) {
    return ''
  }

  if (points.length === 1) {
    const point = points[0]
    return `M ${point.x},${point.y}`
  }

  const segments = [`M ${points[0].x},${points[0].y}`]

  for (let index = 0; index < points.length - 1; index += 1) {
    const previous = points[Math.max(index - 1, 0)]
    const current = points[index]
    const next = points[index + 1]
    const afterNext = points[Math.min(index + 2, points.length - 1)]
    const controlOne = {
      x: clamp(current.x + (next.x - previous.x) / 6, current.x, next.x),
      y: clamp(current.y + (next.y - previous.y) / 6, CHART_PADDING_Y, CHART_HEIGHT - CHART_PADDING_Y),
    }
    const controlTwo = {
      x: clamp(next.x - (afterNext.x - current.x) / 6, current.x, next.x),
      y: clamp(next.y - (afterNext.y - current.y) / 6, CHART_PADDING_Y, CHART_HEIGHT - CHART_PADDING_Y),
    }

    segments.push(`C ${formatChartNumber(controlOne.x)},${formatChartNumber(controlOne.y)} ${formatChartNumber(controlTwo.x)},${formatChartNumber(controlTwo.y)} ${next.x},${next.y}`)
  }

  return segments.join(' ')
}

function clamp(value, min, max) {
  return Math.min(Math.max(value, min), max)
}

function formatChartNumber(value) {
  return Number(value.toFixed(2))
}

function resolveSeriesPalette(name) {
  const keyword = String(name || '').toLowerCase()
  const useSecondary = keyword.includes('写')
    || keyword.includes('流出')
    || keyword.includes('最大')

  if (!useSecondary) {
    return {
      color: metricMeta.value.color,
      shadowColor: metricMeta.value.shadowColor,
    }
  }

  return secondarySeriesPalette.value
}

function resolveSeriesPriority(name) {
  const text = String(name || '')

  if (text.includes('当前')) return 60
  if (text.includes('读取')) return 55
  if (text.includes('流入')) return 50
  if (text.includes('最大')) return 45
  if (text.includes('写入')) return 40
  if (text.includes('流出')) return 35

  return 10
}

function handlePointerMove(event) {
  if (!plottedPoints.value.length) {
    return
  }

  const bounds = event.currentTarget.getBoundingClientRect()
  if (!bounds.width) {
    return
  }

  const pointerX = ((event.clientX - bounds.left) / bounds.width) * CHART_WIDTH
  let nearestIndex = 0
  let nearestDistance = Number.POSITIVE_INFINITY

  plottedPoints.value.forEach((point, index) => {
    const distance = Math.abs(point.x - pointerX)
    if (distance < nearestDistance) {
      nearestDistance = distance
      nearestIndex = index
    }
  })

  activePointIndex.value = nearestIndex

  // 用最近数据点的 SVG x 坐标反算 viewport 位置，y 取鼠标位置
  const nearestPoint = plottedPoints.value[nearestIndex]
  const pointViewportX = bounds.left + (nearestPoint.x / CHART_WIDTH) * bounds.width
  pointerClientPos.value = { x: pointViewportX, y: event.clientY }
}

function clearActivePoint() {
  activePointIndex.value = -1
}

function formatValueText(value) {
  return formatValue(value, { includeUnit: true })
}

function resolvePointTimeText(point) {
  if (!point || typeof point !== 'object') {
    return '--'
  }

  const normalizedTimestamp = normalizePointTimestamp(point.timestamp)
  if (normalizedTimestamp > 0) {
    return formatDateTime(normalizedTimestamp)
  }

  const fallback = String(point.time || '--').trim()
  return fallback || '--'
}

function resolvePointAxisText(point, sameDay) {
  const normalizedTimestamp = normalizePointTimestamp(point?.timestamp)
  if (normalizedTimestamp > 0) {
    return formatAxisDateTime(normalizedTimestamp, sameDay)
  }

  const text = String(point?.time || '--').trim()
  if (!text || text === '--') {
    return '--'
  }

  const timeMatch = text.match(/(\d{2}:\d{2})(?::\d{2})?$/u)
  if (sameDay && timeMatch) {
    return timeMatch[1]
  }

  const dateTimeMatch = text.match(/(\d{2}-\d{2})\s+(\d{2}:\d{2})/u)
  if (dateTimeMatch) {
    return `${dateTimeMatch[1]} ${dateTimeMatch[2]}`
  }

  return text
}

function isSamePointDate(left, right) {
  const leftTimestamp = normalizePointTimestamp(left?.timestamp)
  const rightTimestamp = normalizePointTimestamp(right?.timestamp)
  if (leftTimestamp > 0 && rightTimestamp > 0) {
    const leftDate = new Date(leftTimestamp)
    const rightDate = new Date(rightTimestamp)
    return leftDate.getFullYear() === rightDate.getFullYear()
      && leftDate.getMonth() === rightDate.getMonth()
      && leftDate.getDate() === rightDate.getDate()
  }

  const leftText = String(left?.time || '').slice(0, 10)
  const rightText = String(right?.time || '').slice(0, 10)
  return leftText !== '' && leftText === rightText
}

function normalizePointTimestamp(value) {
  const numeric = Number(value)
  if (!Number.isFinite(numeric) || numeric <= 0) {
    return 0
  }

  if (numeric >= 1e12) {
    return Math.round(numeric)
  }

  if (numeric >= 1e9) {
    return Math.round(numeric * 1000)
  }

  return 0
}

function formatDateTime(timestamp) {
  const date = new Date(timestamp)
  if (Number.isNaN(date.getTime())) {
    return '--'
  }

  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  const hour = String(date.getHours()).padStart(2, '0')
  const minute = String(date.getMinutes()).padStart(2, '0')
  const second = String(date.getSeconds()).padStart(2, '0')

  return `${year}-${month}-${day} ${hour}:${minute}:${second}`
}

function formatAxisDateTime(timestamp, sameDay) {
  const date = new Date(timestamp)
  if (Number.isNaN(date.getTime())) {
    return '--'
  }

  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  const hour = String(date.getHours()).padStart(2, '0')
  const minute = String(date.getMinutes()).padStart(2, '0')

  if (sameDay) {
    return `${hour}:${minute}`
  }

  return `${month}-${day} ${hour}:${minute}`
}

function formatValue(value, { includeUnit = false } = {}) {
  const numeric = Number(value)
  if (!Number.isFinite(numeric)) {
    return '--'
  }

  const abs = Math.abs(numeric)
  let text = ''

  if (abs >= 100) {
    text = trimTrailingZeros(numeric.toFixed(0))
  } else if (abs >= 10) {
    text = trimTrailingZeros(numeric.toFixed(1))
  } else if (abs >= 1) {
    text = trimTrailingZeros(numeric.toFixed(2))
  } else {
    text = trimTrailingZeros(numeric.toFixed(3))
  }

  if (!includeUnit || unitText.value === '无单位') {
    return text
  }

  return `${text} ${unitText.value}`
}

function trimTrailingZeros(text) {
  return String(text)
    .replace(/(\.\d*?[1-9])0+$/u, '$1')
    .replace(/\.0+$/u, '')
}
</script>

<style scoped lang="scss">
.trend-card {
  --trend-accent: #165dff;
  --trend-accent-soft: rgba(22, 93, 255, 0.1);
  --trend-accent-border: rgba(22, 93, 255, 0.22);
  --trend-axis-width: 50px;
  --trend-axis-gap: 0px;
  padding: 14px;
  min-height: 292px;
  border: 1px solid $divider-color;
  border-radius: 8px;
  background: $bg-color-card;
  transition: border-color $motion-fast ease-out;

  &:hover {
    border-color: rgba(22, 93, 255, 0.2);
  }
}

.trend-card.is-loading {
  overflow: hidden;
}

.trend-head {
  display: flex;
  justify-content: space-between;
  gap: 10px;
  align-items: center;
}

.trend-head-main {
  display: flex;
  align-items: center;
  min-width: 0;
}

.trend-head-copy {
  min-width: 0;

  h4 {
    display: flex;
    align-items: center;
    gap: 7px;
    color: $text-color-primary;
    font-size: 14px;
    font-weight: 700;
    line-height: 1.35;

    &::before {
      content: '';
      display: inline-block;
      width: 6px;
      height: 6px;
      border-radius: 999px;
      background: var(--trend-accent);
      flex-shrink: 0;
    }
  }

  p {
    margin-top: 3px;
    color: $text-color-secondary;
    font-size: 12px;
    line-height: 1.4;
  }
}

.trend-unit-tag {
  flex-shrink: 0;
  border-color: var(--trend-accent-border);
  color: var(--trend-accent);
  background: #fff;
}

.trend-skeleton {
  position: relative;
  display: inline-block;
  overflow: hidden;
  background: linear-gradient(90deg, rgba(226, 232, 240, 0.9), rgba(241, 245, 249, 1), rgba(226, 232, 240, 0.9));
  background-size: 200% 100%;
  animation: trendSkeletonShift 1.2s ease-in-out infinite;
}

.trend-skeleton-tag {
  width: 42px;
  height: 12px;
  border-radius: 999px;
}

.trend-summary {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 8px;
  margin-top: 10px;
  padding-top: 10px;
  border-top: 1px solid $divider-color;
}

.trend-summary-item {
  padding: 0;
  border-radius: 6px;

  span {
    display: flex;
    align-items: center;
    gap: 5px;
    color: $text-color-secondary;
    font-size: 12px;

    &::before {
      content: '';
      width: 5px;
      height: 5px;
      border-radius: 999px;
      background: var(--trend-summary-color);
      flex-shrink: 0;
    }
  }

  strong {
    display: block;
    margin-top: 4px;
    color: $text-color-primary;
    font-size: 14px;
    font-weight: 700;
  }
}

.trend-skeleton-value {
  display: block;
  width: 72%;
  height: 16px;
  margin-top: 4px;
  border-radius: 8px;
}

.trend-canvas {
  position: relative;
  margin-top: 12px;
  height: 144px;
  padding: 0;
  overflow: hidden;
  border-radius: 6px;
  background: linear-gradient(to top, rgba(22, 93, 255, 0.02), rgba(22, 93, 255, 0));

  svg {
    display: block;
    width: 100%;
    height: 100%;
    overflow: visible;
  }
}

.trend-chart-shell {
  display: grid;
  grid-template-columns: var(--trend-axis-width) minmax(0, 1fr);
  gap: var(--trend-axis-gap);
  width: 100%;
  height: 100%;
}

.trend-value-axis {
  position: relative;
  height: 100%;
  padding-right: 6px;
}

.trend-value-axis__tick {
  position: absolute;
  right: 6px;
  color: $text-color-placeholder;
  font-size: 11px;
  line-height: 1;
  white-space: nowrap;
  transform: translateY(-50%);
}

.trend-canvas-inner {
  position: relative;
  width: 100%;
  height: 100%;
  overflow: hidden;
}

.trend-grid line {
  stroke: rgba(134, 144, 156, 0.09);
  stroke-width: 1;
  vector-effect: non-scaling-stroke;
}

:global(.trend-tooltip) {
  position: fixed;
  z-index: 9999;
  display: flex;
  flex-direction: column;
  gap: 4px;
  min-width: 88px;
  padding: 8px 10px;
  border-radius: 10px;
  border: 1px solid rgba(22, 93, 255, 0.12);
  background: rgba(255, 255, 255, 0.96);
  color: $text-color-primary;
  box-shadow: $shadow-md;
  pointer-events: none;

  strong {
    font-size: 12px;
    font-weight: 600;
    white-space: nowrap;
  }

  span {
    font-size: 12px;
    color: $text-color-secondary;
    white-space: nowrap;
  }
}

:global(.trend-tooltip__series) {
  display: flex;
  flex-direction: column;
  gap: 5px;
}

:global(.trend-tooltip__row) {
  display: grid;
  grid-template-columns: 8px auto 1fr;
  gap: 6px;
  align-items: center;

  i {
    width: 8px;
    height: 8px;
    border-radius: 999px;
  }

  b,
  em {
    font-size: 12px;
    line-height: 1.2;
    white-space: nowrap;
  }

  b {
    color: $text-color-secondary;
    font-weight: 600;
    font-style: normal;
  }

  em {
    color: $text-color-primary;
    text-align: right;
    font-style: normal;
  }
}

.trend-loading-canvas {
  position: relative;
  width: 100%;
  height: 100%;
}

.trend-loading-line {
  position: absolute;
  left: 14px;
  right: 14px;
  height: 2px;
  border-radius: 999px;
  background: linear-gradient(90deg, transparent 0%, var(--trend-accent) 18%, rgba(255, 255, 255, 0.95) 50%, var(--trend-accent) 82%, transparent 100%);
  opacity: 0.7;
  transform-origin: left center;
  animation: trendSkeletonPulse 1.6s ease-in-out infinite;
}

.trend-loading-line--one {
  top: 78%;
  transform: rotate(-14deg);
}

.trend-loading-line--two {
  top: 48%;
  transform: rotate(8deg);
  animation-delay: 0.2s;
}

.trend-loading-line--three {
  top: 62%;
  transform: rotate(-5deg);
  animation-delay: 0.35s;
}

.trend-canvas-empty {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  height: 100%;
  color: $text-color-placeholder;
  font-size: 13px;
}

.trend-line {
  vector-effect: non-scaling-stroke;
}

.trend-line-shadow {
  opacity: 0.36;
}

.trend-hover-line {
  opacity: 0.5;
}

.trend-axis-shell {
  display: grid;
  grid-template-columns: var(--trend-axis-width) minmax(0, 1fr);
  gap: var(--trend-axis-gap);
  align-items: center;
  margin-top: 8px;
  padding: 0;
}

.trend-axis-shell__spacer {
  width: 100%;
  height: 1px;
}

.trend-axis {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 8px;
  color: $text-color-placeholder;
  font-size: 11px;
}

.trend-axis__label {
  min-width: 0;
  line-height: 1.3;
  word-break: break-all;
}

.trend-axis__label--start {
  text-align: left;
}

.trend-axis__label--middle {
  text-align: center;
}

.trend-axis__label--end {
  text-align: right;
}

.trend-skeleton-axis {
  width: 56px;
  height: 12px;
  border-radius: 999px;
}

@keyframes trendSkeletonShift {
  0% {
    background-position: 100% 50%;
  }

  100% {
    background-position: 0 50%;
  }
}

@keyframes trendSkeletonPulse {
  0%,
  100% {
    opacity: 0.32;
    transform: scaleX(0.92);
  }

  50% {
    opacity: 0.82;
    transform: scaleX(1);
  }
}

@media (max-width: 768px) {
  .trend-card {
    --trend-axis-width: 46px;
    --trend-axis-gap: 0px;
  }

  .trend-head,
  .trend-head-main {
    align-items: flex-start;
  }

  .trend-head {
    flex-direction: column;
  }

  .trend-summary {
    grid-template-columns: 1fr;
  }

  .trend-chart-shell {
    grid-template-columns: var(--trend-axis-width) minmax(0, 1fr);
    gap: var(--trend-axis-gap);
  }

  .trend-value-axis__tick {
    font-size: 10px;
  }
}
</style>
