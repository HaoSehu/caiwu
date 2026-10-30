import { computed, onBeforeUnmount, ref } from 'vue';

import type { MonitorChartRecord, MonitorSummaryItem } from '@/types/client';

interface MonitorSeries {
  key: string;
  name: string;
  rawPoints: MonitorPoint[];
}
interface MonitorPoint {
  time: string;
  timestamp: number;
  value: number;
  displayValue: string;
}
type MonitorRenderPoint = MonitorPoint & { key: string; index: number; x: number; y: number };
interface MonitorRenderSeries {
  key: string;
  name: string;
  color: string;
  lineWidth: number;
  points: MonitorRenderPoint[];
  path: string;
}
interface MonitorAxisTick {
  key: string;
  label: string;
  y: number;
  top: number;
}
interface ActiveMonitorSeriesPoint {
  key: string;
  name: string;
  color: string;
  x: number;
  y: number;
  valueText: string;
}
interface ActiveMonitorPoint {
  x: number;
  y: number;
  time: string;
  seriesPoints: ActiveMonitorSeriesPoint[];
}
interface MonitorChartView {
  key: string;
  label: string;
  message: string;
  latestText: string;
  latestTime: string;
  averageText: string;
  peakText: string;
  lowestText: string;
  yAxisTicks: MonitorAxisTick[];
  xAxisLabels: { start: string; middle: string; end: string };
  series: MonitorRenderSeries[];
}

export const MONITOR_CHART_WIDTH = 320;
export const MONITOR_CHART_HEIGHT = 140;
export const MONITOR_CHART_TOP = 14;
export const MONITOR_CHART_BOTTOM = 116;
export const MONITOR_AXIS_SEGMENTS = 3;

// PR-02/SC-14：SVG 折线最多渲染这么多点。30 天区间上游可能回上千个点，
// 每个点都要算坐标、拼 path、再参与指针命中比较，而 320 单位宽的图上
// 超过这个数量后视觉已经无法分辨（相邻点水平间距小于 3 单位）。
const MONITOR_MAX_RENDER_POINTS = 120;

// 图表系列色严格取自 theme.css 官方 token 色值：brand-6 / success-6 / warning-6 / brand-4（裁决 2026-10-11：不引入体系外紫色）
const monitorPalette = ['#165dff', '#12b76a', '#f59e0b', '#5f9eff'];

export function useConsoleMonitor(monitorState: { charts: MonitorChartRecord[] }) {
  const monitorChartViews = computed(() =>
    monitorState.charts.map((chart, index) => buildMonitorChartView(chart, index)),
  );
  const activeMonitorPoint = ref<{ chartKey: string; index: number } | null>(null);

  function buildMonitorChartView(chart: MonitorChartRecord, index: number): MonitorChartView {
    const summary = chart.summary || {};
    const series = normalizeMonitorSeries(chart);
    const range = resolveMonitorValueRange(series);
    const latestPoint = series[0]?.rawPoints[series[0].rawPoints.length - 1];
    const firstSeriesPoints = series[0]?.rawPoints || [];

    return {
      key: String(chart.type || chart.label || index),
      label: String(chart.label || chart.type || `指标 ${index + 1}`),
      message: String(chart.error || chart.message || ''),
      latestText: resolveMonitorSummaryText(summary.latest || null, series, 'latest') || '--',
      latestTime: String(summary.latest?.time || latestPoint?.time || ''),
      averageText: resolveMonitorSummaryText(summary.average || null, series, 'average') || '--',
      peakText: resolveMonitorSummaryText(summary.peak || null, series, 'peak') || '--',
      lowestText: resolveMonitorSummaryText(summary.lowest || null, series, 'lowest') || '--',
      yAxisTicks: buildMonitorYAxisTicks(range, resolveMonitorUnit(chart)),
      xAxisLabels: buildMonitorXAxisLabels(firstSeriesPoints),
      series: series
        .map((item, seriesIndex) => {
          // 降采样只作用于绘制与指针命中，summary / 坐标轴仍取原始序列
          const points = buildMonitorRenderPoints(downsampleMonitorPoints(item.rawPoints), range, item.key);
          return {
            key: item.key || `${index}-${seriesIndex}`,
            name: item.name,
            color: monitorPalette[seriesIndex % monitorPalette.length],
            lineWidth: seriesIndex === 0 ? 1.6 : 1.25,
            points,
            path: buildMonitorSmoothPath(points),
          };
        })
        .filter((item) => item.path),
    };
  }

  function normalizeMonitorSeries(chart: MonitorChartRecord): MonitorSeries[] {
    const chartData = chart.chart || {};
    const sourceSeries =
      Array.isArray(chartData.series) && chartData.series.length
        ? chartData.series
        : [
            {
              key: chart.type || 'series',
              name: chart.label || chart.type || '',
              list: Array.isArray(chartData.list) ? chartData.list : [],
            },
          ];

    return sourceSeries
      .map((series, index) => {
        const rawPoints = Array.isArray(series.list)
          ? series.list
              .map((point) => ({
                time: String(point?.time || ''),
                timestamp: normalizeMonitorTimestamp(point?.timestamp),
                value: Number(point?.value),
                displayValue: String(point?.display_value || point?.text || point?.value || '--'),
              }))
              .filter((point) => Number.isFinite(point.value))
          : [];

        return {
          key: String(series.key || series.name || index),
          name: String(series.name || series.key || ''),
          rawPoints,
        };
      })
      .filter((series) => series.rawPoints.length > 0);
  }

  function resolveMonitorValueRange(series: MonitorSeries[]) {
    const values = series
      .flatMap((item) => item.rawPoints.map((point) => point.value))
      .filter((value) => Number.isFinite(value));
    if (!values.length) return { min: 0, max: 1, range: 1 };
    // 用 reduce 而非 Math.min(...values)：监控区间点数多时展开传参会触发栈溢出
    const dataMin = values.reduce((acc, value) => (value < acc ? value : acc), values[0]);
    const dataMax = values.reduce((acc, value) => (value > acc ? value : acc), values[0]);
    const min = dataMin >= 0 ? 0 : dataMin;
    const max = dataMax === min ? min + 1 : buildNiceMonitorAxisMax(dataMax, min);
    return { min, max, range: max - min || 1 };
  }

  // PR-02/SC-14：渲染前降采样。每档保留该时间段内的最小值与最大值，
  // 折线形态（尤其是尖峰）不会因为抽稀而消失；首尾点始终保留，
  // 保证曲线两端仍然贴住图形区的左右边界。
  function downsampleMonitorPoints(points: MonitorPoint[]): MonitorPoint[] {
    if (points.length <= MONITOR_MAX_RENDER_POINTS) return points;
    const bucketCount = Math.max(1, Math.floor(MONITOR_MAX_RENDER_POINTS / 2));
    const stride = points.length / bucketCount;
    const result: MonitorPoint[] = [];
    let lastIndex = -1;
    for (let bucket = 0; bucket < bucketCount; bucket += 1) {
      const start = Math.floor(bucket * stride);
      const end = bucket === bucketCount - 1 ? points.length : Math.floor((bucket + 1) * stride);
      let minIndex = start;
      let maxIndex = start;
      for (let index = start + 1; index < end; index += 1) {
        if (points[index].value < points[minIndex].value) minIndex = index;
        if (points[index].value > points[maxIndex].value) maxIndex = index;
      }
      const lowIndex = Math.min(minIndex, maxIndex);
      const highIndex = Math.max(minIndex, maxIndex);
      if (lowIndex > lastIndex) {
        result.push(points[lowIndex]);
        lastIndex = lowIndex;
      }
      if (highIndex > lastIndex) {
        result.push(points[highIndex]);
        lastIndex = highIndex;
      }
    }
    // 不用 Array.prototype.at：它要 Chrome 92 / Safari 15.4，超出仓库声明的浏览器基线
    const lastPoint = points[points.length - 1];
    if (result[result.length - 1] !== lastPoint) result.push(lastPoint);
    return result;
  }

  function buildMonitorRenderPoints(
    points: MonitorPoint[],
    range: { min: number; max: number; range: number },
    seriesKey: string,
  ): MonitorRenderPoint[] {
    if (!points.length) return [];
    const height = MONITOR_CHART_BOTTOM - MONITOR_CHART_TOP;
    const denominator = Math.max(points.length - 1, 1);

    return points.map((point, index) => {
      const x = points.length === 1 ? MONITOR_CHART_WIDTH / 2 : (index / denominator) * MONITOR_CHART_WIDTH;
      const normalized = (point.value - range.min) / range.range;
      const y = MONITOR_CHART_BOTTOM - normalized * height;
      return {
        ...point,
        key: `${seriesKey}-${point.timestamp || point.time}-${index}`,
        index,
        x: Number(x.toFixed(2)),
        y: Number(Math.min(Math.max(y, MONITOR_CHART_TOP), MONITOR_CHART_BOTTOM).toFixed(2)),
      };
    });
  }

  function buildMonitorYAxisTicks(range: { min: number; max: number; range: number }, unit: string): MonitorAxisTick[] {
    return Array.from({ length: MONITOR_AXIS_SEGMENTS + 1 }, (_, index) => {
      const ratio = index / MONITOR_AXIS_SEGMENTS;
      const value = range.max - range.range * ratio;
      const y = MONITOR_CHART_TOP + (MONITOR_CHART_BOTTOM - MONITOR_CHART_TOP) * ratio;
      const top = (y / MONITOR_CHART_HEIGHT) * 100;
      return {
        key: `tick-${index}`,
        y: Number(y.toFixed(2)),
        top: Number(top.toFixed(2)),
        label: formatMonitorAxisValue(value, unit),
      };
    });
  }

  function buildMonitorXAxisLabels(points: MonitorPoint[]) {
    if (!points.length) return { start: '--', middle: '--', end: '--' };
    const middle = points[Math.floor((points.length - 1) / 2)];
    const last = points[points.length - 1];
    const sameDay = isSameMonitorDate(points[0], last);
    return {
      start: formatMonitorAxisTime(points[0], sameDay),
      middle: formatMonitorAxisTime(middle, sameDay),
      end: formatMonitorAxisTime(last, sameDay),
    };
  }

  function buildMonitorSmoothPath(points: MonitorRenderPoint[]) {
    if (!points.length) return '';
    if (points.length === 1) return `M ${points[0].x},${points[0].y}`;

    const segments = [`M ${points[0].x},${points[0].y}`];
    for (let index = 0; index < points.length - 1; index += 1) {
      const previous = points[Math.max(index - 1, 0)];
      const current = points[index];
      const next = points[index + 1];
      const afterNext = points[Math.min(index + 2, points.length - 1)];
      const controlOne = {
        x: clampMonitorNumber(current.x + (next.x - previous.x) / 6, current.x, next.x),
        y: clampMonitorNumber(current.y + (next.y - previous.y) / 6, MONITOR_CHART_TOP, MONITOR_CHART_BOTTOM),
      };
      const controlTwo = {
        x: clampMonitorNumber(next.x - (afterNext.x - current.x) / 6, current.x, next.x),
        y: clampMonitorNumber(next.y - (afterNext.y - current.y) / 6, MONITOR_CHART_TOP, MONITOR_CHART_BOTTOM),
      };
      segments.push(
        `C ${formatMonitorChartNumber(controlOne.x)},${formatMonitorChartNumber(controlOne.y)} ${formatMonitorChartNumber(controlTwo.x)},${formatMonitorChartNumber(controlTwo.y)} ${next.x},${next.y}`,
      );
    }
    return segments.join(' ');
  }

  function resolveMonitorSummaryText(
    summaryItem: MonitorSummaryItem | null,
    series: MonitorSeries[],
    mode: 'latest' | 'average' | 'peak' | 'lowest',
  ) {
    if (summaryItem && typeof summaryItem === 'object' && summaryItem.text) {
      return String(summaryItem.text);
    }

    const values = series
      .flatMap((item) => item.rawPoints.map((point) => point.value))
      .filter((value) => Number.isFinite(value));
    if (!values.length) return '';
    if (mode === 'latest') {
      const latestPoint = series[0]?.rawPoints[series[0].rawPoints.length - 1];
      return latestPoint?.displayValue || '';
    }
    const value =
      mode === 'average'
        ? values.reduce((sum, item) => sum + item, 0) / values.length
        : mode === 'peak'
          ? values.reduce((acc, item) => (item > acc ? item : acc), values[0])
          : values.reduce((acc, item) => (item < acc ? item : acc), values[0]);
    return Number.isInteger(value) ? String(value) : value.toFixed(2);
  }

  // 原实现每次 mousemove（浏览器可到 120Hz）都线性扫全部点并写响应式状态，
  // 一张图 120 个点 × 3 条序列时每秒要做上万次比较，且每次都触发整块模板重渲染。
  // 改为按帧合并：一帧内只处理最后一次指针位置，并在读取矩形前判空。
  let pointerRafId = 0;
  let pendingPointer: { clientX: number; target: SVGElement; chartKey: string } | null = null;

  function applyMonitorPointer() {
    pointerRafId = 0;
    const pending = pendingPointer;
    pendingPointer = null;
    if (!pending) return;
    const chart = monitorChartViews.value.find((item) => item.key === pending.chartKey);
    const points = chart?.series[0]?.points || [];
    if (!points.length) return;
    const bounds = pending.target.getBoundingClientRect();
    if (!bounds.width) return;
    const pointerX = ((pending.clientX - bounds.left) / bounds.width) * MONITOR_CHART_WIDTH;
    let nearestIndex = 0;
    let nearestDistance = Number.POSITIVE_INFINITY;
    points.forEach((point, index) => {
      const distance = Math.abs(point.x - pointerX);
      if (distance < nearestDistance) {
        nearestDistance = distance;
        nearestIndex = index;
      }
    });
    const current = activeMonitorPoint.value;
    if (current && current.chartKey === pending.chartKey && current.index === nearestIndex) return;
    activeMonitorPoint.value = { chartKey: pending.chartKey, index: nearestIndex };
  }

  function queuePointerUpdate(clientX: number, target: SVGElement, chart: MonitorChartView) {
    if (!chart.series[0]?.points?.length) return;
    pendingPointer = { clientX, target, chartKey: chart.key };
    if (pointerRafId) return;
    pointerRafId = window.requestAnimationFrame(applyMonitorPointer);
  }

  function handleMonitorPointerMove(event: MouseEvent, chart: MonitorChartView) {
    queuePointerUpdate(event.clientX, event.currentTarget as SVGElement, chart);
  }

  // 触屏没有 mousemove：手指按下/滑动时同样给出读数，否则移动端图表只能看不能读。
  function handleMonitorPointerTouch(event: TouchEvent, chart: MonitorChartView) {
    const touch = event.touches[0] || event.changedTouches[0];
    if (!touch) return;
    queuePointerUpdate(touch.clientX, event.currentTarget as SVGElement, chart);
  }

  // 键盘可达：聚焦图表后用左右方向键逐点移动读数（Home/End 跳到首尾）。
  function stepMonitorPointer(chart: MonitorChartView, delta: number | 'start' | 'end') {
    const points = chart.series[0]?.points || [];
    if (!points.length) return;
    const current = activeMonitorPoint.value;
    const currentIndex = current && current.chartKey === chart.key ? current.index : 0;
    let nextIndex: number;
    if (delta === 'start') {
      nextIndex = 0;
    } else if (delta === 'end') {
      nextIndex = points.length - 1;
    } else {
      nextIndex = Math.min(points.length - 1, Math.max(0, currentIndex + delta));
    }
    if (current && current.chartKey === chart.key && current.index === nextIndex) return;
    activeMonitorPoint.value = { chartKey: chart.key, index: nextIndex };
  }

  function clearMonitorPointer() {
    if (pointerRafId) {
      window.cancelAnimationFrame(pointerRafId);
      pointerRafId = 0;
    }
    pendingPointer = null;
    activeMonitorPoint.value = null;
  }

  function resolveActiveMonitorPoint(chart: MonitorChartView): ActiveMonitorPoint | null {
    if (!activeMonitorPoint.value || activeMonitorPoint.value.chartKey !== chart.key) return null;
    const firstPoint = chart.series[0]?.points[activeMonitorPoint.value.index];
    if (!firstPoint) return null;
    const seriesPoints = chart.series
      .map((series) => {
        const point = series.points[Math.min(activeMonitorPoint.value?.index || 0, series.points.length - 1)];
        if (!point) return null;
        return {
          key: series.key,
          name: series.name,
          color: series.color,
          x: point.x,
          y: point.y,
          valueText: point.displayValue || formatMonitorAxisValue(point.value, ''),
        };
      })
      .filter((item): item is ActiveMonitorSeriesPoint => item !== null);

    return {
      x: firstPoint.x,
      y: firstPoint.y,
      time: resolveMonitorPointTimeText(firstPoint),
      seriesPoints,
    };
  }

  // 模板内同一 chart 会多次查询 active point（M16）：用 computed 缓存「当前活跃图表的解析结果」，
  // 模板侧改用 resolveActiveMonitorPointCached(chart)，每次渲染每张图最多计算一次。
  const activeMonitorPointEntry = computed(() => {
    const active = activeMonitorPoint.value;
    if (!active) return null;
    const chart = monitorChartViews.value.find((item) => item.key === active.chartKey);
    if (!chart) return null;
    return { chartKey: chart.key, point: resolveActiveMonitorPoint(chart) };
  });

  function resolveActiveMonitorPointCached(chart: MonitorChartView): ActiveMonitorPoint | null {
    const entry = activeMonitorPointEntry.value;
    return entry && entry.chartKey === chart.key ? entry.point : null;
  }

  function resolveMonitorTooltipStyle(chart: MonitorChartView) {
    const activePoint = resolveActiveMonitorPointCached(chart);
    if (!activePoint) return { display: 'none' };
    const minY = Math.min(
      ...activePoint.seriesPoints.map((point) => Number(point.y)).filter((value) => Number.isFinite(value)),
      activePoint.y,
    );
    return {
      left: `${(activePoint.x / MONITOR_CHART_WIDTH) * 100}%`,
      top: `${(minY / MONITOR_CHART_HEIGHT) * 100}%`,
    };
  }

  onBeforeUnmount(() => {
    if (pointerRafId) {
      window.cancelAnimationFrame(pointerRafId);
      pointerRafId = 0;
    }
    pendingPointer = null;
  });

  return {
    monitorChartViews,
    activeMonitorPoint,
    handleMonitorPointerMove,
    handleMonitorPointerTouch,
    stepMonitorPointer,
    clearMonitorPointer,
    resolveActiveMonitorPointCached,
    resolveMonitorTooltipStyle,
  };
}

function buildNiceMonitorAxisMax(max: number, min: number) {
  const range = Math.max(max - min, Math.abs(max), 1);
  const roughMax = max + range * 0.08;
  const magnitude = 10 ** Math.floor(Math.log10(roughMax));
  const normalized = roughMax / magnitude;
  let niceNormalized = 10;
  if (normalized <= 1) niceNormalized = 1;
  else if (normalized <= 2) niceNormalized = 2;
  else if (normalized <= 5) niceNormalized = 5;
  return niceNormalized * magnitude;
}

function resolveMonitorUnit(chart: MonitorChartRecord) {
  const chartData = chart.chart || {};
  return String(chartData.unit || chart.type || '').trim();
}

function formatMonitorAxisValue(value: number, unit: string) {
  if (!Number.isFinite(value)) return '--';
  const abs = Math.abs(value);
  let text = '';
  if (abs >= 100) text = trimMonitorZeros(value.toFixed(0));
  else if (abs >= 10) text = trimMonitorZeros(value.toFixed(1));
  else if (abs >= 1) text = trimMonitorZeros(value.toFixed(2));
  else text = trimMonitorZeros(value.toFixed(3));
  return unit ? `${text} ${unit}` : text;
}

function trimMonitorZeros(text: string) {
  return String(text)
    .replace(/(\.\d*?[1-9])0+$/u, '$1')
    .replace(/\.0+$/u, '');
}

function clampMonitorNumber(value: number, min: number, max: number) {
  return Math.min(Math.max(value, min), max);
}

function formatMonitorChartNumber(value: number) {
  return Number(value.toFixed(2));
}

function normalizeMonitorTimestamp(value: unknown) {
  const numeric = Number(value);
  if (!Number.isFinite(numeric) || numeric <= 0) return 0;
  if (numeric >= 1e12) return Math.round(numeric);
  if (numeric >= 1e9) return Math.round(numeric * 1000);
  return 0;
}

function resolveMonitorPointTimeText(point: MonitorPoint) {
  if (point.timestamp > 0) return formatMonitorDateTime(point.timestamp);
  return String(point.time || '--').trim() || '--';
}

function formatMonitorAxisTime(point: MonitorPoint, sameDay: boolean) {
  if (point.timestamp > 0) {
    const date = new Date(point.timestamp);
    if (!Number.isNaN(date.getTime())) {
      const month = String(date.getMonth() + 1).padStart(2, '0');
      const day = String(date.getDate()).padStart(2, '0');
      const hour = String(date.getHours()).padStart(2, '0');
      const minute = String(date.getMinutes()).padStart(2, '0');
      return sameDay ? `${hour}:${minute}` : `${month}-${day} ${hour}:${minute}`;
    }
  }
  const text = String(point.time || '--').trim();
  if (!text || text === '--') return '--';
  const timeMatch = text.match(/(\d{2}:\d{2})(?::\d{2})?$/u);
  if (sameDay && timeMatch) return timeMatch[1];
  const dateTimeMatch = text.match(/(\d{2}-\d{2})\s+(\d{2}:\d{2})/u);
  return dateTimeMatch ? `${dateTimeMatch[1]} ${dateTimeMatch[2]}` : text;
}

function isSameMonitorDate(left: MonitorPoint, right: MonitorPoint) {
  if (left.timestamp > 0 && right.timestamp > 0) {
    const leftDate = new Date(left.timestamp);
    const rightDate = new Date(right.timestamp);
    return (
      leftDate.getFullYear() === rightDate.getFullYear() &&
      leftDate.getMonth() === rightDate.getMonth() &&
      leftDate.getDate() === rightDate.getDate()
    );
  }
  const leftText = String(left.time || '').slice(0, 10);
  const rightText = String(right.time || '').slice(0, 10);
  return leftText !== '' && leftText === rightText;
}

function formatMonitorDateTime(timestamp: number) {
  const date = new Date(timestamp);
  if (Number.isNaN(date.getTime())) return '--';
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  const hour = String(date.getHours()).padStart(2, '0');
  const minute = String(date.getMinutes()).padStart(2, '0');
  const second = String(date.getSeconds()).padStart(2, '0');
  return `${year}-${month}-${day} ${hour}:${minute}:${second}`;
}
