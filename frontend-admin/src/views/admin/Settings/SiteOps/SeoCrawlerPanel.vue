<template>
  <div class="seo-crawler-panel">
    <section>
      <h3 class="panel-title">访问日志原文</h3>
      <p class="panel-desc">
        粘贴 nginx access.log 或 Apache combined 格式的一段日志（一行一条记录）。日志仅在浏览器内解析，不会上传到服务器。
      </p>
      <el-input
        v-model="rawLog"
        type="textarea"
        :rows="8"
        placeholder='例如：192.168.1.1 - - [18/Apr/2026:10:00:00 +0800] "GET /products HTTP/1.1" 200 1234 "-" "Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)"'
        class="log-input"
      />
      <div class="actions">
        <el-button type="primary" :icon="Search" @click="parseLog">解析</el-button>
        <el-button :icon="Delete" @click="clearAll">清空</el-button>
      </div>
    </section>

    <section v-if="parsed.total > 0">
      <h3 class="panel-title">统计结果</h3>
      <div class="stats">
        <div class="stat-card stat-card--total">
          <div class="stat-label">总请求</div>
          <div class="stat-value">{{ parsed.total }}</div>
        </div>
        <div class="stat-card stat-card--bots">
          <div class="stat-label">爬虫请求</div>
          <div class="stat-value">{{ parsed.botCount }}</div>
          <div class="stat-meta">占 {{ parsed.botRatio }}%</div>
        </div>
        <div class="stat-card stat-card--bots-uniq">
          <div class="stat-label">识别出爬虫种类</div>
          <div class="stat-value">{{ parsed.botBreakdown.length }}</div>
          <div class="stat-meta">顶部：{{ parsed.botBreakdown[0]?.name || '—' }}</div>
        </div>
      </div>
    </section>

    <section v-if="parsed.botCount > 0" class="chart-section">
      <h3 class="panel-title">爬虫请求分析</h3>
      <div class="charts-grid">
        <div class="chart-card">
          <div class="chart-card-head">
            <div class="chart-card-title">时间趋势</div>
            <div class="chart-card-meta">粒度：{{ parsed.bucketLabel }} · {{ trendChart.points.length }} 个数据点</div>
          </div>
          <svg
            v-if="trendChart.points.length > 0"
            :viewBox="`0 0 ${CHART_W} ${CHART_H}`"
            class="trend-chart"
            preserveAspectRatio="none"
          >
            <g class="grid">
              <line
                v-for="(g, i) in trendChart.gridLines"
                :key="i"
                :x1="PAD.l"
                :y1="g.y"
                :x2="CHART_W - PAD.r"
                :y2="g.y"
              />
            </g>
            <g class="y-labels">
              <text
                v-for="(l, i) in trendChart.yLabels"
                :key="i"
                :x="PAD.l - 6"
                :y="l.y"
                text-anchor="end"
                dominant-baseline="middle"
              >{{ l.value }}</text>
            </g>
            <path :d="trendChart.areaPath" class="line-area" />
            <polyline :points="trendChart.linePoints" class="line" fill="none" />
            <g class="dots">
              <circle
                v-for="(p, i) in trendChart.points"
                :key="i"
                :cx="p.x"
                :cy="p.y"
                r="2.5"
              >
                <title>{{ p.label }}：{{ p.value }}</title>
              </circle>
            </g>
            <g class="x-labels">
              <text
                v-for="(l, i) in trendChart.xLabels"
                :key="i"
                :x="l.x"
                :y="CHART_H - 6"
                text-anchor="middle"
              >{{ l.value }}</text>
            </g>
          </svg>
          <div v-else class="chart-empty">跨度太小或时间解析失败，无法画趋势</div>
        </div>

        <div class="chart-card">
          <div class="chart-card-head">
            <div class="chart-card-title">爬虫类型分布</div>
            <div class="chart-card-meta">共 {{ parsed.botBreakdown.length }} 类，点击柱查看详细计数</div>
          </div>
          <svg
            v-if="barChart.bars.length > 0"
            :viewBox="`0 0 ${BAR_W} ${barChart.height}`"
            class="bar-chart"
            preserveAspectRatio="none"
          >
            <g v-for="(bar, i) in barChart.bars" :key="bar.label" class="bar-row">
              <text
                :x="BAR_PAD.l - 6"
                :y="bar.y + bar.h / 2"
                text-anchor="end"
                dominant-baseline="middle"
                class="bar-label"
              >{{ bar.label }}</text>
              <rect
                :x="BAR_PAD.l"
                :y="bar.y"
                :width="bar.w"
                :height="bar.h"
                class="bar"
              >
                <title>{{ bar.label }}：{{ bar.value }} 次，占爬虫请求 {{ bar.ratio }}%</title>
              </rect>
              <text
                :x="BAR_PAD.l + bar.w + 6"
                :y="bar.y + bar.h / 2"
                dominant-baseline="middle"
                class="bar-value"
              >{{ bar.value }}</text>
            </g>
          </svg>
        </div>
      </div>
    </section>

    <section v-if="parsed.rows.length > 0">
      <div class="row-head">
        <h3 class="panel-title">请求明细（最多 {{ MAX_ROWS }} 条）</h3>
        <el-radio-group v-model="filter" size="small">
          <el-radio-button value="bots">仅爬虫</el-radio-button>
          <el-radio-button value="all">全部</el-radio-button>
        </el-radio-group>
      </div>
      <el-table
        :data="displayRows"
        :row-key="(row) => row.id"
        size="small"
        stripe
        class="log-table"
      >
        <el-table-column label="时间" prop="time" width="190" />
        <el-table-column label="IP" prop="ip" width="140" />
        <el-table-column label="爬虫" width="130">
          <template #default="{ row }">
            <el-tag v-if="row.bot" size="small" type="success" effect="plain">{{ row.bot }}</el-tag>
            <span v-else class="muted">—</span>
          </template>
        </el-table-column>
        <el-table-column label="状态" prop="status" width="70" />
        <el-table-column label="方法" prop="method" width="70" />
        <el-table-column label="路径" prop="path" min-width="220" show-overflow-tooltip />
        <el-table-column label="User-Agent" prop="ua" min-width="260" show-overflow-tooltip />
      </el-table>
    </section>

    <el-empty
      v-if="!rawLog.trim() && parsed.rows.length === 0"
      description="粘贴日志并点击解析，下方会展示识别结果"
    />
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import { Delete, Search } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'

const MAX_ROWS = 500

const BOTS = [
  { name: 'Googlebot', pattern: /googlebot|google-inspectiontool|storebot-google/i },
  { name: 'Bingbot', pattern: /bingbot|adidxbot|msnbot/i },
  { name: 'Baiduspider', pattern: /baiduspider/i },
  { name: 'Sogou', pattern: /sogou (?:web|news|inst|spider)/i },
  { name: '360Spider', pattern: /360spider|haosouspider/i },
  { name: 'YisouSpider', pattern: /yisouspider/i },
  { name: 'Yandex', pattern: /yandex(?:bot|images)/i },
  { name: 'DuckDuckBot', pattern: /duckduckbot/i },
  { name: 'Applebot', pattern: /applebot/i },
  { name: 'Facebook', pattern: /facebookexternalhit|meta-externalagent/i },
  { name: 'Twitterbot', pattern: /twitterbot/i },
  { name: 'LinkedInBot', pattern: /linkedinbot/i },
  { name: 'Slackbot', pattern: /slackbot/i },
  { name: 'Bytespider', pattern: /bytespider/i },
  { name: 'PetalBot', pattern: /petalbot/i },
  { name: 'SemrushBot', pattern: /semrushbot/i },
  { name: 'AhrefsBot', pattern: /ahrefsbot/i },
  { name: 'MJ12bot', pattern: /mj12bot/i },
  { name: 'DotBot', pattern: /dotbot/i },
  { name: 'GPTBot', pattern: /gptbot|oai-searchbot|chatgpt-user/i },
  { name: 'ClaudeBot', pattern: /claudebot|claude-web/i },
  { name: 'PerplexityBot', pattern: /perplexitybot/i },
]

const LOG_REGEX = /^(\S+)\s+\S+\s+\S+\s+\[([^\]]+)\]\s+"(\S+)\s+(\S+)\s+\S+"\s+(\d+)\s+\S+\s+"([^"]*)"\s+"([^"]*)"/

const rawLog = ref('')
const filter = ref('bots')
const parsed = ref({
  total: 0,
  botCount: 0,
  botRatio: '0',
  botBreakdown: [],
  rows: [],
  timeBuckets: [],
  bucketLabel: '',
})

// SVG 画布常量
const CHART_W = 640
const CHART_H = 220
const PAD = { t: 12, r: 12, b: 24, l: 32 }
const BAR_W = 640
const BAR_PAD = { t: 6, r: 36, b: 6, l: 96 }
const BAR_ROW_H = 22
const BAR_GAP = 6

const MONTHS = { Jan: 0, Feb: 1, Mar: 2, Apr: 3, May: 4, Jun: 5, Jul: 6, Aug: 7, Sep: 8, Oct: 9, Nov: 10, Dec: 11 }
const LOG_TIME_REGEX = /^(\d{2})\/(\w{3})\/(\d{4}):(\d{2}):(\d{2}):(\d{2})/

function detectBot(ua) {
  const s = String(ua || '')
  for (const bot of BOTS) {
    if (bot.pattern.test(s)) return bot.name
  }
  return null
}

function parseLogTime(s) {
  const m = String(s || '').match(LOG_TIME_REGEX)
  if (!m) return null
  const [, day, monStr, year, hh, mm, ss] = m
  const mon = MONTHS[monStr]
  if (mon === undefined) return null
  // 不处理时区，按本地时间处理（同一份日志通常同一时区，折线趋势不受影响）
  return new Date(Number(year), mon, Number(day), Number(hh), Number(mm), Number(ss))
}

function pickBucketMs(spanMs) {
  if (spanMs <= 30 * 60 * 1000) return 60 * 1000              // 30 分钟内 → 1 分钟
  if (spanMs <= 2 * 60 * 60 * 1000) return 5 * 60 * 1000      // 2 小时内 → 5 分钟
  if (spanMs <= 12 * 60 * 60 * 1000) return 30 * 60 * 1000    // 12 小时内 → 30 分钟
  if (spanMs <= 3 * 24 * 60 * 60 * 1000) return 60 * 60 * 1000 // 3 天内 → 1 小时
  if (spanMs <= 14 * 24 * 60 * 60 * 1000) return 6 * 60 * 60 * 1000  // 14 天内 → 6 小时
  return 24 * 60 * 60 * 1000                                  // 其余 → 1 天
}

function bucketLabelText(ms) {
  if (ms < 60 * 60 * 1000) return `${Math.round(ms / 60000)} 分钟`
  if (ms < 24 * 60 * 60 * 1000) return `${Math.round(ms / 3600000)} 小时`
  return `${Math.round(ms / 86400000)} 天`
}

function formatBucketTime(date, bucketMs) {
  const pad = (n) => String(n).padStart(2, '0')
  if (bucketMs < 24 * 60 * 60 * 1000) {
    return `${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}`
  }
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`
}

function niceCeil(n) {
  if (n <= 1) return 1
  if (n <= 5) return 5
  if (n <= 10) return 10
  const exp = Math.floor(Math.log10(n))
  const factor = Math.pow(10, exp)
  const mantissa = n / factor
  if (mantissa <= 2) return 2 * factor
  if (mantissa <= 5) return 5 * factor
  return 10 * factor
}

function parseLog() {
  const text = String(rawLog.value || '').trim()
  if (!text) {
    ElMessage.warning('请先粘贴日志内容')
    return
  }

  const lines = text.split(/\r?\n/).filter((line) => line.trim())
  let total = 0
  const rows = []
  const botBreakdownMap = {}

  for (const line of lines) {
    const match = line.match(LOG_REGEX)
    if (!match) continue
    total += 1
    const [, ip, time, method, path, status, , ua] = match
    const bot = detectBot(ua)
    if (bot) {
      botBreakdownMap[bot] = (botBreakdownMap[bot] || 0) + 1
    }
    if (rows.length < MAX_ROWS) {
      rows.push({
        id: `${total}-${ip}-${rows.length}`,
        ip,
        time,
        method,
        path,
        status,
        ua,
        bot,
      })
    }
  }

  const botCount = Object.values(botBreakdownMap).reduce((sum, v) => sum + v, 0)
  const botRatio = total > 0 ? ((botCount / total) * 100).toFixed(1) : '0'
  const botBreakdown = Object.entries(botBreakdownMap)
    .map(([name, count]) => ({ name, count }))
    .sort((a, b) => b.count - a.count)

  // 计算时间分桶（仅统计爬虫请求，帮助看 SEO 趋势）
  const dates = []
  for (const row of rows) {
    if (!row.bot) continue
    const d = parseLogTime(row.time)
    if (d) dates.push(d.getTime())
  }
  let timeBuckets = []
  let bucketLabel = ''
  if (dates.length > 0) {
    const minMs = Math.min(...dates)
    const maxMs = Math.max(...dates)
    const spanMs = Math.max(maxMs - minMs, 60 * 1000)
    const bucketMs = pickBucketMs(spanMs)
    bucketLabel = bucketLabelText(bucketMs)
    const bucketStart = Math.floor(minMs / bucketMs) * bucketMs
    const bucketEnd = Math.ceil((maxMs + 1) / bucketMs) * bucketMs
    const bucketCount = Math.max(1, Math.round((bucketEnd - bucketStart) / bucketMs))
    const counts = new Array(bucketCount).fill(0)
    for (const ts of dates) {
      const idx = Math.min(bucketCount - 1, Math.floor((ts - bucketStart) / bucketMs))
      counts[idx] += 1
    }
    timeBuckets = counts.map((count, i) => ({
      ts: bucketStart + i * bucketMs,
      label: formatBucketTime(new Date(bucketStart + i * bucketMs), bucketMs),
      count,
    }))
  }

  parsed.value = { total, botCount, botRatio, botBreakdown, rows, timeBuckets, bucketLabel }

  if (total === 0) {
    ElMessage.warning('未解析到有效行，请确认日志为 nginx combined 格式')
  } else {
    ElMessage.success(`已解析 ${total} 行，识别到 ${botCount} 条爬虫请求`)
  }
}

function clearAll() {
  rawLog.value = ''
  parsed.value = {
    total: 0,
    botCount: 0,
    botRatio: '0',
    botBreakdown: [],
    rows: [],
    timeBuckets: [],
    bucketLabel: '',
  }
}

const displayRows = computed(() => {
  if (filter.value === 'bots') {
    return parsed.value.rows.filter((row) => row.bot)
  }
  return parsed.value.rows
})

const trendChart = computed(() => {
  const buckets = parsed.value.timeBuckets || []
  if (buckets.length === 0) {
    return { linePoints: '', areaPath: '', points: [], gridLines: [], yLabels: [], xLabels: [] }
  }
  const innerW = CHART_W - PAD.l - PAD.r
  const innerH = CHART_H - PAD.t - PAD.b
  const max = Math.max(...buckets.map((b) => b.count), 1)
  const yMax = niceCeil(max)
  const ySteps = 4
  const gridLines = []
  const yLabels = []
  for (let i = 0; i <= ySteps; i++) {
    const y = PAD.t + (innerH * i) / ySteps
    gridLines.push({ y })
    yLabels.push({ y, value: Math.round(yMax * (1 - i / ySteps)) })
  }
  const denom = Math.max(buckets.length - 1, 1)
  const points = buckets.map((b, i) => ({
    x: PAD.l + (innerW * i) / denom,
    y: PAD.t + innerH * (1 - b.count / yMax),
    value: b.count,
    label: b.label,
  }))
  const linePoints = points.map((p) => `${p.x.toFixed(2)},${p.y.toFixed(2)}`).join(' ')
  const baseY = PAD.t + innerH
  const areaPath = `M${points[0].x.toFixed(2)},${baseY} L${points
    .map((p) => `${p.x.toFixed(2)},${p.y.toFixed(2)}`)
    .join(' L')} L${points[points.length - 1].x.toFixed(2)},${baseY} Z`
  const stride = Math.max(1, Math.ceil(buckets.length / 6))
  const xLabels = points
    .map((p, i) => ({ ...p, idx: i }))
    .filter((p) => p.idx % stride === 0 || p.idx === points.length - 1)
    .map((p) => ({ x: p.x, value: p.label }))
  return { linePoints, areaPath, points, gridLines, yLabels, xLabels }
})

const MAX_BARS = 12

const barChart = computed(() => {
  const breakdown = parsed.value.botBreakdown || []
  const botCount = parsed.value.botCount || 0
  if (breakdown.length === 0) return { bars: [], height: BAR_PAD.t + BAR_PAD.b }
  const visible = breakdown.slice(0, MAX_BARS)
  const max = Math.max(...visible.map((e) => e.count), 1)
  const innerW = BAR_W - BAR_PAD.l - BAR_PAD.r
  const bars = visible.map((entry, i) => ({
    label: entry.name,
    value: entry.count,
    ratio: botCount > 0 ? ((entry.count / botCount) * 100).toFixed(1) : '0',
    y: BAR_PAD.t + i * (BAR_ROW_H + BAR_GAP),
    h: BAR_ROW_H,
    w: Math.max(2, (entry.count / max) * innerW),
  }))
  const height = BAR_PAD.t + bars.length * (BAR_ROW_H + BAR_GAP) - BAR_GAP + BAR_PAD.b
  return { bars, height }
})
</script>

<style scoped lang="scss">
.seo-crawler-panel {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.panel-title {
  margin: 0;
  font-size: 15px;
  font-weight: 600;
  color: $text-color-primary;
}

.panel-desc {
  margin: 4px 0 12px;
  font-size: 12px;
  line-height: 1.6;
  color: $text-color-placeholder;
}

.log-input :deep(.el-textarea__inner) {
  font-family: 'SF Mono', 'Menlo', 'Consolas', monospace;
  font-size: 12px;
}

.actions {
  margin-top: 12px;
  display: flex;
  gap: 8px;
}

.stats {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
  gap: 12px;
}

.stat-card {
  padding: 14px;
  border: 1px solid $divider-color;
  border-radius: $sm-border-radius;
  background: $bg-color-soft;
}

.stat-card--total {
  border-color: $color-primary-border;
  background: rgba($color-primary, 0.06);
}

.stat-card--bots {
  border-color: rgba(103, 194, 58, 0.45);
  background: rgba(103, 194, 58, 0.08);
}

.stat-card--bots-uniq {
  border-color: rgba(230, 162, 60, 0.45);
  background: rgba(230, 162, 60, 0.08);
}

.chart-section {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.charts-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 16px;
}

@media (max-width: 1080px) {
  .charts-grid {
    grid-template-columns: 1fr;
  }
}

.chart-card {
  border: 1px solid $divider-color;
  border-radius: $sm-border-radius;
  background: $bg-color-soft;
  padding: 14px 16px 12px;
  display: flex;
  flex-direction: column;
  gap: 10px;
  min-width: 0;
}

.chart-card-head {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
}

.chart-card-title {
  font-size: 13px;
  font-weight: 600;
  color: $text-color-primary;
}

.chart-card-meta {
  font-size: 11px;
  color: $text-color-placeholder;
  font-variant-numeric: tabular-nums;
}

.chart-empty {
  font-size: 12px;
  color: $text-color-placeholder;
  padding: 20px 0;
  text-align: center;
}

.trend-chart,
.bar-chart {
  width: 100%;
  height: auto;
  display: block;
  font-size: 10px;
}

.trend-chart .grid line {
  stroke: $divider-color;
  stroke-width: 1;
  stroke-dasharray: 2 3;
}

.trend-chart .y-labels text,
.trend-chart .x-labels text {
  fill: $text-color-placeholder;
  font-size: 10px;
}

.trend-chart .line {
  stroke: $color-primary;
  stroke-width: 1.5;
  stroke-linejoin: round;
  stroke-linecap: round;
}

.trend-chart .line-area {
  fill: rgba(22, 93, 255, 0.1);
}

.trend-chart .dots circle {
  fill: $color-primary;
}

.bar-chart .bar {
  fill: $color-primary;
  rx: 2;
  transition: fill 0.18s ease;
}

.bar-chart .bar:hover {
  fill: rgba(22, 93, 255, 0.78);
}

.bar-chart .bar-label {
  fill: $text-color-secondary;
  font-size: 11px;
}

.bar-chart .bar-value {
  fill: $text-color-primary;
  font-size: 11px;
  font-weight: 600;
  font-variant-numeric: tabular-nums;
}

.stat-label {
  font-size: 12px;
  color: $text-color-placeholder;
}

.stat-value {
  margin-top: 4px;
  font-size: 22px;
  font-weight: 600;
  color: $text-color-primary;
}

.stat-meta {
  margin-top: 2px;
  font-size: 11px;
  color: $text-color-placeholder;
}

.row-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 8px;
  flex-wrap: wrap;
  gap: 12px;
}

.log-table :deep(.el-table__row) {
  font-family: 'SF Mono', 'Menlo', 'Consolas', monospace;
  font-size: 12px;
}

.muted {
  color: $text-color-placeholder;
}
</style>
