<template>
  <div class="client-page dashboard-page">

    <!-- ── Hero strip ── -->
    <div class="dash-hero">
      <div class="hero-acct">
        <div class="hero-user-row">
          <el-avatar :size="44" :src="avatarUrl" class="hero-av">
            {{ displayName.slice(0, 1) || '客' }}
          </el-avatar>
          <div>
            <div class="hero-name">{{ displayName }}</div>
            <div class="hero-date">{{ greetingText }} · {{ todayDateText }}</div>
          </div>
        </div>
      </div>

      <div class="hero-metrics">
        <div class="hero-m">
          <div class="m-lbl">账户余额</div>
          <div class="m-val"><span class="hero-balance-num">{{ animMetrics.balance }}</span><span class="m-unit">元</span></div>
          <div class="m-sub">ID: {{ userIdText }}</div>
        </div>
        <div class="hero-m">
          <div class="m-lbl">本月充值</div>
          <div class="m-val">{{ animMetrics.recharge }}<span class="m-unit">元</span></div>
        </div>
        <div class="hero-m">
          <div class="m-lbl">本月消费</div>
          <div class="m-val is-orange">{{ animMetrics.payment }}<span class="m-unit">元</span></div>
          <div class="m-sub">今日 {{ todayExpenseText }} 元</div>
        </div>
        <div class="hero-m">
          <div class="m-lbl">可用优惠券</div>
          <div class="m-val">{{ animMetrics.coupons }}<span class="m-unit">张</span></div>
        </div>
      </div>

      <div class="hero-cta">
        <el-button type="primary" @click="router.push('/client/recharge')"><el-icon class="cta-ico"><Coin /></el-icon>在线充值</el-button>
        <el-button @click="router.push('/client/balance-logs')"><el-icon class="cta-ico"><Document /></el-icon>财务明细</el-button>
      </div>
    </div>

    <!-- ── 2-column grid ── -->
    <div class="dash-grid">

      <!-- Left column -->
      <div class="col-l">

        <!-- Products -->
        <article class="card">
          <header class="card-head">
            <div class="card-title">我的产品</div>
            <el-button text type="primary" @click="router.push('/client/services')">管理全部 →</el-button>
          </header>
          <div class="card-body">
            <div class="prod-grid">
              <template v-if="loading">
                <div v-for="n in 4" :key="'sk-' + n" class="prod-item prod-item--skeleton">
                  <div class="prod-ico prod-ico--skeleton"></div>
                  <div class="prod-meta prod-meta--skeleton">
                    <span class="skel-line skel-line--title"></span>
                    <span class="skel-line skel-line--sub"></span>
                  </div>
                </div>
              </template>
              <button
                v-else
                v-for="item in productCards"
                :key="item.key"
                type="button"
                class="prod-item"
                @click="handleProductCardClick(item)"
              >
                <div class="prod-ico">
                  <el-icon><component :is="item.icon" /></el-icon>
                </div>
                <div class="prod-meta">
                  <strong>{{ item.title }}</strong>
                  <span>{{ item.countText }}</span>
                </div>
              </button>
            </div>
          </div>
        </article>

        <!-- Chart row side-by-side -->
        <div class="dash-chart-row">
          <!-- Left: Product consumption share pie chart -->
          <article class="card pie-card">
            <header class="card-head">
              <div class="card-title">
                <el-icon class="card-icon"><PieChart /></el-icon>
                每月产品消费占比
              </div>
              <span class="card-date">{{ monthLabel }}</span>
            </header>
            <div class="card-body">
              <div class="donut-wrap">
                <!-- 加载骨架 -->
                <div v-if="loading" class="chart-skeleton chart-skeleton--pie">
                  <div class="chart-skeleton__ring"></div>
                  <div class="chart-skeleton__legend">
                    <span v-for="n in 4" :key="n" class="chart-skeleton__bar"></span>
                  </div>
                </div>
                <template v-else-if="pieSlices.length">
                  <!-- Donut Chart Left -->
                  <div class="donut-chart-box">
                    <svg viewBox="0 0 100 100" class="donut-svg">
                      <circle
                        v-for="(slice, i) in pieSlices"
                        :key="i"
                        cx="50" cy="50" r="30"
                        fill="transparent"
                        stroke-width="10"
                        :stroke="slice.color"
                        :stroke-dasharray="`${slice.strokeLength} ${188.5 - slice.strokeLength}`"
                        :stroke-dashoffset="slice.strokeOffset"
                        class="donut-slice"
                        :class="{ 'is-active': activeSliceIndex === i }"
                        @mouseenter="activeSliceIndex = i"
                        @mouseleave="activeSliceIndex = null"
                        transform="rotate(-90 50 50)"
                      />
                    </svg>
                    <!-- Center Text -->
                    <div class="donut-center-text">
                      <template v-if="activeSliceIndex !== null && pieSlices[activeSliceIndex]">
                        <span class="donut-center-lbl">{{ pieSlices[activeSliceIndex].label }}</span>
                        <strong class="donut-center-val">{{ pieSlices[activeSliceIndex].percent }}%</strong>
                      </template>
                      <template v-else>
                        <span class="donut-center-lbl">月总消费</span>
                        <strong class="donut-center-val">¥{{ totalConsumptionAmount.toFixed(1) }}</strong>
                      </template>
                    </div>
                  </div>

                  <!-- Legend Right -->
                  <div class="donut-legend">
                    <div
                      v-for="(slice, i) in pieSlices"
                      :key="i"
                      class="donut-legend-row"
                      :class="{ 'is-active': activeSliceIndex === i }"
                      @mouseenter="activeSliceIndex = i"
                      @mouseleave="activeSliceIndex = null"
                    >
                      <span class="donut-legend-dot" :style="{ backgroundColor: slice.color }"></span>
                      <span class="donut-legend-name">{{ slice.label }}</span>
                      <span class="donut-legend-percent">{{ slice.percent }}%</span>
                      <span class="donut-legend-amount">¥{{ slice.amount.toFixed(2) }}</span>
                    </div>
                  </div>
                </template>
                <el-empty v-else description="暂无本月产品消费数据" :image-size="56"/>
              </div>
            </div>
          </article>

          <!-- Right: Daily expense bar chart -->
          <article class="card bar-card">
            <header class="card-head">
              <div class="card-title">
                <el-icon class="card-icon"><DataLine /></el-icon>
                近30天每日消费
              </div>
              <el-button text type="primary" @click="router.push('/client/balance-logs')">消费明细 →</el-button>
            </header>
            <div class="card-body">
              <div class="echart-wrap">
                <!-- 加载骨架 -->
                <div v-if="loading" class="chart-skeleton chart-skeleton--bar">
                  <span v-for="n in 8" :key="n" class="chart-skeleton__col" :style="{ height: `${30 + Math.random() * 50}%`, animationDelay: `${n * 60}ms` }"></span>
                </div>
                <template v-else-if="chartSvgPoints.line">
                  <svg :key="chartSvgPoints.line" :viewBox="chartSvgPoints.viewBox" class="echart-svg">
                    <g v-for="tick in chartSvgPoints.yTicks" :key="tick.val">
                      <line x1="46" :y1="tick.y" :x2="chartSvgPoints.chartRight" :y2="tick.y" stroke="#EEF2F7" stroke-width="1"/>
                      <text x="42" :y="tick.y + 3.5" font-size="9.5" fill="#94A0B2" text-anchor="end">{{ tick.label }}</text>
                    </g>
                    <rect
                      v-for="(bar, i) in chartSvgPoints.bars"
                      :key="i"
                      class="echart-bar"
                      :x="bar.x" :y="bar.y"
                      :width="bar.width" :height="bar.height"
                      rx="2"
                      fill="#165DFF"
                      :style="{ '--bar-delay': `${i * 18}ms` }"
                    />
                    <text
                      v-for="lbl in chartSvgPoints.xLabels"
                      :key="lbl.label"
                      :x="lbl.x"
                      :y="chartSvgPoints.xBaseY"
                      font-size="9.5" fill="#94A0B2" text-anchor="middle"
                    >{{ lbl.label }}</text>
                  </svg>
                </template>
                <el-empty v-else description="暂无消费记录" :image-size="56"/>
              </div>
              <div class="echart-foot">
                <span>本月累计消费 <strong>{{ financeSummary.invoice_payment_out || '0.00' }} 元</strong></span>
              </div>
            </div>
          </article>
        </div>

        <!-- Notices + Help (tabbed) -->
        <article class="card">
          <header class="card-head">
            <div class="card-title">
              <el-icon class="card-icon"><Notification /></el-icon>
              消息中心
            </div>
            <el-button
              text type="primary"
              @click="router.push(activeNoticeTab === 'notice' ? '/client/notices' : '/client/help')"
            >查看全部 →</el-button>
          </header>
          <div class="tab-bar">
            <button class="tab" :class="{ active: activeNoticeTab === 'notice' }" @click="activeNoticeTab = 'notice'">新闻公告</button>
            <button class="tab" :class="{ active: activeNoticeTab === 'help' }" @click="activeNoticeTab = 'help'">帮助中心</button>
          </div>
          <div class="tab-body">
            <template v-if="loading">
              <div class="notice-skeleton">
                <div v-for="n in 4" :key="n" class="notice-skeleton__row">
                  <span class="skel-line" style="width: 6px; height: 6px; border-radius: 50%; flex-shrink: 0;"></span>
                  <span class="skel-line skel-line--title" :style="{ width: `${55 + n * 8}%` }"></span>
                </div>
              </div>
            </template>
            <Transition v-else name="tab-fade" mode="out-in">
              <div v-if="activeNoticeTab === 'notice'" key="notice">
                <el-tooltip
                  v-for="item in recentNotices"
                  :key="item.id"
                  :content="item.title"
                  placement="top"
                  :show-after="300"
                >
                  <router-link class="n-row" :to="`/client/notices/${item.id}`">
                    <span class="n-dot"></span>
                    <span class="n-title">{{ item.title }}</span>
                    <span class="n-time">{{ formatDate(item.publish_at || item.created_at) }}</span>
                  </router-link>
                </el-tooltip>
                <el-empty v-if="!recentNotices.length && !loading" description="暂无公告" :image-size="68" />
              </div>
              <div v-else key="help">
                <el-tooltip
                  v-for="item in recentHelpArticles"
                  :key="item.id"
                  :content="item.title"
                  placement="top"
                  :show-after="300"
                >
                  <router-link class="n-row" :to="`/client/help/${item.id}`">
                    <span class="n-dot is-purple"></span>
                    <span class="n-title">{{ item.title }}</span>
                    <span class="n-time">{{ formatDate(item.publish_at || item.created_at) }}</span>
                  </router-link>
                </el-tooltip>
                <el-empty v-if="!recentHelpArticles.length && !loading" description="暂无帮助内容" :image-size="68" />
              </div>
            </Transition>
          </div>
        </article>

      </div><!-- /col-l -->

      <!-- Right column -->
      <div class="col-r">

        <!-- Todo -->
        <article class="card">
          <header class="card-head">
            <div class="card-title">待办事项</div>
          </header>
          <div class="card-body todo-body">
            <div class="todo-list">
              <button
                v-for="item in todoItems"
                :key="item.key"
                type="button"
                class="todo-row"
                @click="router.push(item.path)"
              >
                <span class="todo-lbl">{{ item.label }}</span>
                <span class="todo-right">
                  <span class="todo-num" :class="item.count > 0 ? 'tn-danger' : ''">{{ item.count }}</span>
                  <el-icon class="todo-arr"><ArrowRight /></el-icon>
                </span>
              </button>
            </div>
          </div>
        </article>

        <!-- Quick links -->
        <article class="card">
          <header class="card-head">
            <div class="card-title">快捷入口</div>
          </header>
          <div class="card-body quick-body">
            <div class="quick-grid">
              <router-link to="/client/services" class="q-item">
                <el-icon><Box /></el-icon>
                <span>我的服务</span>
              </router-link>
              <router-link to="/client/invoices" class="q-item">
                <el-icon><Tickets /></el-icon>
                <span>订单记录</span>
              </router-link>
              <router-link to="/client/balance-logs" class="q-item">
                <el-icon><Wallet /></el-icon>
                <span>消费明细</span>
              </router-link>
              <router-link to="/client/tickets" class="q-item">
                <el-icon><Message /></el-icon>
                <span>工单管理</span>
              </router-link>
              <router-link to="/client/recharge" class="q-item">
                <el-icon><Coin /></el-icon>
                <span>在线充值</span>
              </router-link>
              <router-link to="/client/help" class="q-item">
                <el-icon><QuestionFilled /></el-icon>
                <span>帮助中心</span>
              </router-link>
            </div>
          </div>
        </article>

        <!-- QQ Support -->
        <article class="card">
          <header class="card-head">
            <div class="card-title">
              <el-icon class="card-icon"><Headset /></el-icon>
              官方QQ群聊
            </div>
          </header>
          <div class="card-body">
            <div class="sup-box">
              <div class="qr-box">
                <img v-if="supportQr" :src="supportQr" alt="QQ群二维码" />
                <span v-else class="qr-ph">
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 80 80" class="mock-qr-svg">
                    <rect x="4" y="4" width="20" height="20" rx="2" fill="#DCDFE6" />
                    <rect x="7" y="7" width="14" height="14" rx="1" fill="#FFFFFF" />
                    <rect x="9" y="9" width="10" height="10" rx="1" fill="#909399" />
                    <rect x="56" y="4" width="20" height="20" rx="2" fill="#DCDFE6" />
                    <rect x="59" y="7" width="14" height="14" rx="1" fill="#FFFFFF" />
                    <rect x="61" y="9" width="10" height="10" rx="1" fill="#909399" />
                    <rect x="4" y="56" width="20" height="20" rx="2" fill="#DCDFE6" />
                    <rect x="7" y="59" width="14" height="14" rx="1" fill="#FFFFFF" />
                    <rect x="9" y="61" width="10" height="10" rx="1" fill="#909399" />
                    <g fill="#909399">
                      <rect x="30" y="4" width="4" height="4" rx="1" />
                      <rect x="36" y="4" width="4" height="4" rx="1" />
                      <rect x="42" y="4" width="4" height="4" rx="1" />
                      <rect x="30" y="10" width="4" height="4" rx="1" />
                      <rect x="42" y="10" width="4" height="4" rx="1" />
                      <rect x="30" y="16" width="4" height="4" rx="1" />
                      <rect x="36" y="16" width="4" height="4" rx="1" />
                      <rect x="30" y="30" width="4" height="4" rx="1" />
                      <rect x="36" y="30" width="4" height="4" rx="1" />
                      <rect x="42" y="30" width="4" height="4" rx="1" />
                      <rect x="30" y="36" width="4" height="4" rx="1" />
                      <rect x="42" y="36" width="4" height="4" rx="1" />
                      <rect x="30" y="42" width="4" height="4" rx="1" />
                      <rect x="36" y="42" width="4" height="4" rx="1" />
                      <rect x="42" y="42" width="4" height="4" rx="1" />
                      <rect x="30" y="56" width="4" height="4" rx="1" />
                      <rect x="36" y="56" width="4" height="4" rx="1" />
                      <rect x="42" y="56" width="4" height="4" rx="1" />
                      <rect x="30" y="62" width="4" height="4" rx="1" />
                      <rect x="36" y="62" width="4" height="4" rx="1" />
                      <rect x="30" y="68" width="4" height="4" rx="1" />
                      <rect x="42" y="68" width="4" height="4" rx="1" />
                      <rect x="30" y="74" width="4" height="4" rx="1" />
                      <rect x="36" y="74" width="4" height="4" rx="1" />
                      <rect x="42" y="74" width="4" height="4" rx="1" />
                    </g>
                  </svg>
                </span>
              </div>
              <div class="sup-txt">
                <h4>加入官方QQ群聊</h4>
                <p>群号：{{ supportPhoneText }}</p>
                <el-button
                  v-if="supportGroupLink"
                  type="primary"
                  size="small"
                  tag="a"
                  :href="supportGroupLink"
                  target="_blank"
                >加入群聊</el-button>
              </div>
            </div>
          </div>
        </article>

      </div><!-- /col-r -->
    </div><!-- /dash-grid -->
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { ElMessage } from 'element-plus'
import { useRouter } from 'vue-router'
import {
  Box,
  ArrowRight,
  Bell,
  Briefcase,
  ChatLineSquare,
  ChromeFilled,
  Clock,
  Coin,
  Connection,
  Cpu,
  DataBoard,
  DataLine,
  Document,
  Files,
  Folder,
  FolderChecked,
  FolderOpened,
  Grid,
  Headset,
  Link,
  Lock,
  Message,
  Memo,
  Monitor,
  MostlyCloudy,
  Notification,
  OfficeBuilding,
  Orange,
  Platform,
  PieChart,
  QuestionFilled,
  SetUp,
  Suitcase,
  Tickets,
  Timer,
  User,
  UserFilled,
  Wallet,
} from '@element-plus/icons-vue'
import clientApi from '@/api/client'
import { useUserStore } from '@/stores/user'
import { useSiteBrandingStore } from '@/app/stores/siteBranding'

type GenericRecord = Record<string, any>

const router = useRouter()
const userStore = useUserStore()
const siteBrandingStore = useSiteBrandingStore()

const loading = ref(false)
const activeNoticeTab = ref<'notice' | 'help'>('notice')
const balanceLogsDaily = ref<GenericRecord[]>([])
const paidInvoices = ref<GenericRecord[]>([])
const activeSliceIndex = ref<number | null>(null)

const animMetrics = reactive({ balance: '0.00', recharge: '0.00', payment: '0.00', coupons: '0' })

function animateValue(to: number, decimals: number, set: (v: string) => void) {
  const duration = 650
  const start = performance.now()
  const run = (now: number) => {
    const t = Math.min((now - start) / duration, 1)
    const ease = 1 - Math.pow(1 - t, 3)
    const v = to * ease
    set(decimals > 0 ? v.toFixed(decimals) : String(Math.round(v)))
    if (t < 1) requestAnimationFrame(run)
  }
  requestAnimationFrame(run)
}

watch(loading, (val) => {
  if (val) return
  animateValue(parseFloat(String(financeSummary.value.balance || 0)), 2, v => { animMetrics.balance = v })
  animateValue(parseFloat(String(financeSummary.value.recharge_in || 0)), 2, v => { animMetrics.recharge = v })
  animateValue(parseFloat(String(financeSummary.value.invoice_payment_out || 0)), 2, v => { animMetrics.payment = v })
  animateValue(parseInt(String(couponSummary.value.available || 0)), 0, v => { animMetrics.coupons = v })
})
const recentNotices = ref<GenericRecord[]>([])
const recentHelpArticles = ref<GenericRecord[]>([])
const recentServices = ref<GenericRecord[]>([])
const recentInvoices = ref<GenericRecord[]>([])
const recentTickets = ref<GenericRecord[]>([])
const financeSummary = ref<GenericRecord>({
  balance: '0.00',
  total_out: '0.00',
  recharge_in: '0.00',
  invoice_payment_out: '0.00',
  unpaid_count: 0,
  unpaid_amount: '0.00',
  total_invoices: 0,
})
const couponSummary = ref<GenericRecord>({
  total: 0,
  available: 0,
  used_up: 0,
  expired: 0,
})
const serviceOverview = ref<GenericRecord>({
  total: 0,
  list: [],
})
const referralOverview = ref<GenericRecord>({})

const userInfo = computed<GenericRecord>(() => userStore.info || {})
const displayName = computed(() => userInfo.value.nickname || userInfo.value.display_name || userInfo.value.email || '客户账户')
const userIdText = computed(() => String(userInfo.value.id || '--'))
const avatarUrl = computed(() => userInfo.value.avatar || userInfo.value.avatar_url || userInfo.value.headimg || '')
const isVerified = computed(() => Number(userInfo.value.is_verified || 0) === 1)
const isPhoneBound = computed(() => Boolean(String(userInfo.value.phone || '').trim()))
const isEmailBound = computed(() => Boolean(String(userInfo.value.email || '').trim()))
const supportQr = computed(() => siteBrandingStore.supportGroupQr || '')
const supportGroupLink = computed(() => siteBrandingStore.supportGroupLink || '')
const supportPhoneText = computed(() => siteBrandingStore.serviceQqGroup || '-')
const todayExpenseText = computed(() => '0.00')

function last30DaysRange(): string[] {
  const now = new Date()
  const start = new Date(now)
  start.setDate(start.getDate() - 29)
  const fmt = (d: Date) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
  return [fmt(start), fmt(now)]
}

const chartDailyData = computed(() => {
  const days: { date: string; amount: number }[] = []
  const now = new Date()
  for (let i = 29; i >= 0; i--) {
    const d = new Date(now)
    d.setDate(d.getDate() - i)
    const dateStr = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
    days.push({ date: dateStr, amount: 0 })
  }
  for (const entry of balanceLogsDaily.value) {
    const change = parseFloat(String(entry.change_amount ?? 0))
    if (change >= 0) continue
    const dateStr = String(entry.created_at ?? '').slice(0, 10)
    const day = days.find(d => d.date === dateStr)
    if (day) day.amount = Math.round((day.amount + Math.abs(change)) * 100) / 100
  }
  return days
})

function niceStep(rawMax: number): number {
  if (rawMax <= 0) return 2
  const rawStep = rawMax / 5
  const mag = Math.pow(10, Math.floor(Math.log10(rawStep)))
  const norm = rawStep / mag
  if (norm < 1.5) return mag
  if (norm < 3) return 2 * mag
  if (norm < 7) return 5 * mag
  return 10 * mag
}

function fmtY(val: number): string {
  if (val >= 10000) return `¥${+(val / 10000).toFixed(1)}w`
  if (val >= 1000) return `¥${+(val / 1000).toFixed(1)}k`
  return `¥${val}`
}

const TONE_COLORS = [
  '#165DFF', // Blue
  '#12B76A', // Green
  '#F59E0B', // Amber
  '#F04438', // Red
  '#8B5CF6', // Purple
  '#06B6D4', // Cyan
]

const monthLabel = computed(() => {
  const now = new Date()
  return `${now.getFullYear()}年${now.getMonth() + 1}月`
})

const productConsumptionData = computed(() => {
  const now = new Date()
  const year = now.getFullYear()
  const month = String(now.getMonth() + 1).padStart(2, '0')
  const prefix = `${year}-${month}`
  const map: Record<string, { label: string; amount: number; count: number }> = {}

  for (const inv of paidInvoices.value) {
    const date = inv.paid_at || inv.created_at || ''
    if (!date.startsWith(prefix)) continue

    const name = inv.product_display_name || inv.combined_display_name || '其他服务'
    const amount = parseFloat(String(inv.paid_amount || inv.amount || 0))

    if (!map[name]) {
      map[name] = { label: name, amount: 0, count: 0 }
    }
    map[name].amount += amount
    map[name].count += 1
  }

  const list = Object.values(map).sort((a, b) => b.amount - a.amount)

  if (list.length > 5) {
    const top = list.slice(0, 4)
    const others = list.slice(4)
    const otherAmount = others.reduce((sum, item) => sum + item.amount, 0)
    const otherCount = others.reduce((sum, item) => sum + item.count, 0)
    if (otherAmount > 0) {
      top.push({ label: '其他', amount: Math.round(otherAmount * 100) / 100, count: otherCount })
    }
    return top
  }

  return list
})

const totalConsumptionAmount = computed(() => {
  return productConsumptionData.value.reduce((sum, item) => sum + item.amount, 0)
})

const pieSlices = computed(() => {
  const data = productConsumptionData.value
  const total = totalConsumptionAmount.value
  if (total <= 0) return []

  const r = 30
  const circ = 2 * Math.PI * r // ~188.495

  let accumulatedLength = 0

  return data.map((item, index) => {
    const percent = item.amount / total
    const strokeLength = Math.round(percent * circ * 100) / 100
    const strokeOffset = Math.round((circ - accumulatedLength) * 100) / 100
    accumulatedLength += strokeLength

    return {
      label: item.label,
      amount: item.amount,
      count: item.count,
      percent: Math.round(percent * 100),
      color: TONE_COLORS[index % TONE_COLORS.length],
      strokeLength,
      strokeOffset,
    }
  })
})

const chartSvgPoints = computed(() => {
  const data = chartDailyData.value
  const amounts = data.map(d => d.amount)
  const ML = 46, MR = 10, MT = 16, MB = 24
  const VW = 680, CH = 124
  const VH = MT + CH + MB
  const CW = VW - ML - MR

  const step = niceStep(Math.max(...amounts, 0.01))
  const niceMax = Math.max(step, Math.ceil(Math.max(...amounts, 0) / step) * step)
  const tickCount = Math.round(niceMax / step)
  const yTicks = Array.from({ length: tickCount + 1 }, (_, i) => {
    const val = step * i
    return { val, y: Math.round((MT + CH - (val / niceMax) * CH) * 10) / 10, label: fmtY(val) }
  })

  const hasData = amounts.some(a => a > 0)
  const slotW = CW / data.length
  const barW = Math.max(2, Math.floor(slotW * 0.65))

  const bars = hasData ? data.map((d, i) => {
    const cx = ML + (i + 0.5) * slotW
    const barH = d.amount > 0 ? Math.max(1, (d.amount / niceMax) * CH) : 0
    return {
      x: Math.round((cx - barW / 2) * 10) / 10,
      y: Math.round((MT + CH - barH) * 10) / 10,
      width: barW,
      height: Math.round(barH * 10) / 10,
      amount: d.amount,
    }
  }) : []

  const xLabels = data.reduce((acc, d, i) => {
    if (i % 3 === 0) {
      const cx = Math.round((ML + (i + 0.5) * slotW) * 10) / 10
      acc.push({ x: cx, label: `${+d.date.slice(5, 7)}/${+d.date.slice(8, 10)}` })
    }
    return acc
  }, [] as { x: number; label: string }[])

  const peakIdx = amounts.indexOf(Math.max(...amounts))
  return {
    bars,
    peak: hasData ? (bars[peakIdx] ?? null) : null,
    line: hasData ? 'bars' : '',
    yTicks,
    xLabels,
    xBaseY: MT + CH + 14,
    chartRight: ML + CW,
    viewBox: `0 0 ${VW} ${VH}`,
  }
})

const chartLabelDates = computed(() => {
  const now = new Date()
  return [29, 21, 14, 7].map(ago => {
    const d = new Date(now)
    d.setDate(d.getDate() - ago)
    return `${d.getMonth() + 1}/${d.getDate()}`
  })
})

const greetingText = computed(() => {
  const hour = new Date().getHours()
  if (hour < 6) return '夜深了'
  if (hour < 9) return '早上好'
  if (hour < 12) return '上午好'
  if (hour < 14) return '中午好'
  if (hour < 18) return '下午好'
  if (hour < 22) return '晚上好'
  return '夜深了'
})

const todayDateText = computed(() => {
  const now = new Date()
  const weekDays = ['日', '一', '二', '三', '四', '五', '六']
  return `${now.getFullYear()}年${now.getMonth() + 1}月${now.getDate()}日 星期${weekDays[now.getDay()]}`
})

const productTypeIconMap: Record<string, any> = {
  Platform,
  Monitor,
  Connection,
  Cpu,
  OfficeBuilding,
  DataBoard,
  FolderChecked,
  Document,
  MostlyCloudy,
  Folder,
  Orange,
  Briefcase,
  Box,
  Grid,
  Coin,
  Files,
  Link,
  DataLine,
  ChromeFilled,
  SetUp,
  FolderOpened,
  Suitcase,
  Tickets,
  Memo,
}

const productTypeFallbackMetaMap: Record<string, { icon: any, theme: string }> = {
  vps: { icon: Platform, theme: 'blue' },
  dedicated: { icon: OfficeBuilding, theme: 'azure' },
  hosting: { icon: ChromeFilled, theme: 'indigo' },
  domain: { icon: Connection, theme: 'sky' },
  other: { icon: MostlyCloudy, theme: 'cyan' },
  other_services: { icon: Grid, theme: 'slate' },
}

function resolveProductCardMeta(item: GenericRecord, key: string) {
  const configuredIconName = String(item.icon || '').trim()
  if (configuredIconName && productTypeIconMap[configuredIconName]) {
    return {
      icon: productTypeIconMap[configuredIconName],
      theme: resolveProductCardTheme(key, item),
    }
  }

  const fallbackByType = productTypeFallbackMetaMap[key]
  if (fallbackByType) {
    return fallbackByType
  }

  const title = String(item.title || item.name || item.product_type_label || '').trim().toLowerCase()

  if (title.includes('裸金属')) return { icon: Cpu, theme: 'azure' }
  if (title.includes('物理机')) return { icon: OfficeBuilding, theme: 'azure' }
  if (title.includes('nat') || title.includes('云电脑')) return { icon: Connection, theme: 'sky' }
  if (title.includes('cdn')) return { icon: MostlyCloudy, theme: 'cyan' }
  if (title.includes('域名')) return { icon: Link, theme: 'cyan' }
  if (title.includes('云服务器')) return { icon: Platform, theme: 'blue' }
  if (title.includes('虚拟主机')) return { icon: ChromeFilled, theme: 'indigo' }
  if (title.includes('数据库')) return { icon: DataBoard, theme: 'indigo' }
  if (title.includes('对象存储')) return { icon: Document, theme: 'violet' }

  return { icon: Box, theme: 'blue' }
}

function resolveProductCardTheme(key: string, item: GenericRecord) {
  const typeThemeMap: Record<string, string> = {
    vps: 'blue',
    dedicated: 'azure',
    hosting: 'indigo',
    domain: 'sky',
    other: 'cyan',
    other_services: 'slate',
  }

  if (typeThemeMap[key]) {
    return typeThemeMap[key]
  }

  const title = String(item.title || item.name || item.product_type_label || '').trim().toLowerCase()
  if (title.includes('裸金属') || title.includes('物理机')) return 'azure'
  if (title.includes('nat') || title.includes('云电脑')) return 'sky'
  if (title.includes('cdn') || title.includes('域名')) return 'cyan'
  if (title.includes('数据库') || title.includes('虚拟主机')) return 'indigo'
  if (title.includes('对象存储')) return 'violet'

  return 'blue'
}

const todoItems = computed(() => [
  {
    key: 'invoice',
    label: '待支付账单',
    count: Number(financeSummary.value.unpaid_count || 0),
    path: '/client/invoices',
    icon: Tickets,
    iconClass: 'blue',
  },
  {
    key: 'ticket',
    label: '待处理工单',
    count: openTicketCount.value,
    path: '/client/tickets',
    icon: Headset,
    iconClass: 'orange',
  },
  {
    key: 'renew',
    label: '即将到期服务',
    count: expiringServiceCount.value,
    path: '/client/services',
    icon: Timer,
    iconClass: 'green',
  },
  {
    key: 'kyc',
    label: '待实名认证',
    count: isVerified.value ? 0 : 1,
    path: '/client/profile',
    icon: UserFilled,
    iconClass: 'gray',
  },
])

const expiringServiceCount = computed(() => {
  const list = Array.isArray(serviceOverview.value.list) ? serviceOverview.value.list : []
  return list.reduce((sum: number, item: GenericRecord) => sum + Number(item.expiring_count || 0), 0)
})

const openTicketCount = computed(() => {
  return recentTickets.value.filter((item) => Number(item.status) !== 3).length
})

const productCards = computed(() => {
  const overviewList = Array.isArray(serviceOverview.value.list) ? serviceOverview.value.list : []

  if (overviewList.length) {
    return overviewList.slice(0, 6).map((item: GenericRecord, index: number) => {
      const key = String(item.product_type || item.key || `product-${index}`)
      const resolvedMeta = resolveProductCardMeta(item, key)
      return {
        key,
        title: item.title || item.name || item.product_type_label || '云产品',
        description: item.description || '当前分类已开通服务，可进入控制台继续管理。',
        countText: `${Number(item.count || 0)} 个`,
        count: Number(item.count || 0),
        path: '/client/services',
        primaryServiceId: Number(item.primary_service_id || 0),
        icon: resolvedMeta.icon,
        theme: resolvedMeta.theme,
      }
    })
  }

  return [
    { key: 'cloud', title: '云服务器', description: '稳定安全的计算服务', countText: '0 个', count: 0, path: '/client/services', primaryServiceId: 0, icon: Box, theme: 'blue' },
    { key: 'storage', title: '对象存储', description: '安全高效的存储服务', countText: '0 个', count: 0, path: '/client/services', primaryServiceId: 0, icon: Connection, theme: 'azure' },
    { key: 'database', title: '云数据库', description: '稳定可靠的数据服务', countText: '0 个', count: 0, path: '/client/services', primaryServiceId: 0, icon: Coin, theme: 'indigo' },
    { key: 'cdn', title: 'CDN 加速', description: '快速分发全球内容', countText: '0 个', count: 0, path: '/client/services', primaryServiceId: 0, icon: Platform, theme: 'sky' },
    { key: 'domain', title: '域名注册', description: '便捷的域名注册服务', countText: '0 个', count: 0, path: '/client/services', primaryServiceId: 0, icon: Grid, theme: 'cyan' },
    { key: 'ssl', title: 'SSL 证书', description: '安全可靠的证书服务', countText: '0 个', count: 0, path: '/client/services', primaryServiceId: 0, icon: Lock, theme: 'violet' },
  ]
})

function formatDate(dateStr: string | null | undefined): string {
  if (!dateStr) return '--'
  const date = new Date(dateStr)
  if (isNaN(date.getTime())) return '--'
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  const hours = String(date.getHours()).padStart(2, '0')
  const minutes = String(date.getMinutes()).padStart(2, '0')
  return `${month}-${day} ${hours}:${minutes}`
}

function resolveList(payload: any) {
  if (Array.isArray(payload?.list)) return payload.list
  if (Array.isArray(payload?.items)) return payload.items
  if (Array.isArray(payload)) return payload
  return []
}

function currentMonthRange() {
  const now = new Date()
  const start = new Date(now.getFullYear(), now.getMonth(), 1)
  const end = new Date(now.getFullYear(), now.getMonth() + 1, 0)
  const toDateText = (value: Date) => {
    const year = value.getFullYear()
    const month = String(value.getMonth() + 1).padStart(2, '0')
    const day = String(value.getDate()).padStart(2, '0')
    return `${year}-${month}-${day}`
  }

  return [toDateText(start), toDateText(end)]
}

function handleProductCardClick(item: GenericRecord) {
  if (Number(item.primaryServiceId || 0) > 0) {
    router.push(`/client/services/${item.primaryServiceId}`)
    return
  }
  router.push(item.path || '/client/services')
}

async function loadDashboard() {
  loading.value = true

  try {
    await userStore.fetchUserInfo('client').catch(() => {})

    if (!siteBrandingStore.siteName) {
      await siteBrandingStore.fetchSiteConfig()
    }

    const monthRange = currentMonthRange()
    const [
      noticesRes,
      helpArticlesRes,
      servicesRes,
      invoicesRes,
      ticketsRes,
      financeRes,
      couponsRes,
      serviceOverviewRes,
      referralRes,
      balanceLogsDailyRes,
      paidInvoicesRes,
    ] = await Promise.allSettled([
      clientApi.notices({ page: 1, page_size: 10 }),
      clientApi.helpArticles({ page: 1, page_size: 10 }),
      clientApi.services({ page: 1, page_size: 6 }),
      clientApi.invoices({ page: 1, page_size: 5 }),
      clientApi.tickets({ page: 1, page_size: 5 }),
      clientApi.financeLedgerSummary({ date_range: monthRange }),
      clientApi.couponsSummary(),
      clientApi.groupedOverview(),
      clientApi.referralOverview(),
      clientApi.balanceLogs({ date_range: last30DaysRange(), page_size: 200 }),
      clientApi.invoices({ page: 1, page_size: 100, status: 1 }),
    ])

    if (noticesRes.status === 'fulfilled') {
      recentNotices.value = resolveList(noticesRes.value.data)
    }

    if (helpArticlesRes.status === 'fulfilled') {
      recentHelpArticles.value = resolveList(helpArticlesRes.value.data)
    }

    if (servicesRes.status === 'fulfilled') {
      recentServices.value = resolveList(servicesRes.value.data)
    }

    if (invoicesRes.status === 'fulfilled') {
      recentInvoices.value = resolveList(invoicesRes.value.data)
    }

    if (ticketsRes.status === 'fulfilled') {
      recentTickets.value = resolveList(ticketsRes.value.data)
    }

    if (financeRes.status === 'fulfilled') {
      financeSummary.value = {
        ...financeSummary.value,
        ...(financeRes.value.data || {}),
      }
    }

    if (couponsRes.status === 'fulfilled') {
      couponSummary.value = {
        ...couponSummary.value,
        ...(couponsRes.value.data || {}),
      }
    }

    if (serviceOverviewRes.status === 'fulfilled') {
      serviceOverview.value = serviceOverviewRes.value.data || { total: 0, list: [] }
    }

    if (referralRes.status === 'fulfilled') {
      referralOverview.value = referralRes.value.data || {}
    }

    if (balanceLogsDailyRes.status === 'fulfilled') {
      balanceLogsDaily.value = resolveList(balanceLogsDailyRes.value.data)
    }

    if (paidInvoicesRes.status === 'fulfilled') {
      paidInvoices.value = resolveList(paidInvoicesRes.value.data)
    }
  } catch (error: any) {
    if (!error?.__handled) {
      ElMessage.error(error?.message || '控制台概览加载失败')
    }
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  void loadDashboard()
})
</script>

<style lang="scss" scoped>
/* ── Page ── */
.dashboard-page {
  --card-px: 20px;
  --card-py: 16px;
  --c-border: #eef2f7;
  --c-bg-subtle: #f8fafc;
  --c-text-1: #1f2937;
  --c-text-2: #5b6b82;
  --c-text-3: #94a0b2;
  --c-accent: #165dff;
  --c-accent-soft: #eff4ff;
  --c-warm: #d97706;
  --c-warm-soft: #fffbf5;
}

/* ── Greeting ── */
.dashboard-greeting {
  margin-bottom: 4px;
}

.greeting-text h2 {
  margin: 0;
  font-size: 22px;
  font-weight: 700;
  color: var(--el-text-color-primary, #1F2937);
  letter-spacing: -0.01em;
}

.greeting-text p {
  margin: 4px 0 0;
  font-size: 13px;
  color: var(--el-text-color-secondary, #5B6B82);
}

/* ── Grid ── */
.dashboard-grid {
  display: grid;
  gap: 20px;
}

.row-top {
  grid-template-columns: 260px 1fr 1fr;
}

.row-mid {
  grid-template-columns: 1fr 1.4fr;
}

.row-bot {
  grid-template-columns: 1fr 340px;
  align-items: stretch;
}

.row-bot > .card {
  display: flex;
  flex-direction: column;
}

.row-bot > .card > .card-body {
  flex: 1;
}

.right-stack {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

/* ── Card base ── */
.card {
  border-radius: 12px;
  background: #FFFFFF;
  border: 1px solid var(--c-border);
  overflow: hidden;
}

.card:hover {}

.card-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 18px var(--card-px) 0;

  .el-button { font-size: 13px; font-weight: 400; padding: 0; }
}

.card-head h3 {
  margin: 0;
  font-size: 15px;
  font-weight: 600;
  color: var(--el-text-color-primary, #1F2937);
}

.card-head-left {
  display: flex;
  align-items: center;
}

.card-head-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 30px;
  height: 30px;
  border-radius: 8px;
  background: #EFF4FF;
  color: var(--el-color-primary, #165DFF);
  margin-right: 10px;
  font-size: 15px;
}

.card-body {
  padding: var(--card-py) var(--card-px) 22px;
}

/* ── Account Card ── */
.account-card .card-body {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.account-row {
  display: flex;
  align-items: center;
  gap: 14px;
}

.account-avatar {
  flex-shrink: 0;
  background: #EFF4FF;
  color: var(--el-color-primary, #165DFF);
  font-weight: 700;
  font-size: 18px;
}

.account-info {
  min-width: 0;
}

.account-name {
  font-size: 16px;
  font-weight: 700;
  color: var(--el-text-color-primary, #1F2937);
  line-height: 1.3;
}

.account-id {
  font-size: 12px;
  color: var(--el-text-color-placeholder, #94A0B2);
  margin-top: 2px;
  font-variant-numeric: tabular-nums;
}

.chip {
  display: inline-flex;
  align-items: center;
  padding: 3px 10px;
  border-radius: 12px;
  font-size: 12px;
  font-weight: 500;
  gap: 5px;
}

.chip-success {
  background: #f0fdf4;
  color: #16a34a;
}

.chip-warning {
  background: var(--c-warm-soft);
  color: var(--c-warm);
}

.chip-default {
  background: var(--c-bg-subtle);
  color: var(--c-text-3);
}

.chip-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: currentColor;
}

.account-badges {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

/* ── Finance Card ── */
.finance-metrics {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0;
}

.finance-metric {
  text-align: center;
  padding: 10px 0;
  position: relative;
}

.finance-metric + .finance-metric::before {
  content: '';
  position: absolute;
  left: 0;
  top: 14px;
  bottom: 14px;
  width: 1px;
  background: var(--el-border-color-lighter, #EEF1F6);
}

.finance-metric .label {
  font-size: 12px;
  color: var(--el-text-color-secondary, #5B6B82);
  margin-bottom: 8px;
}

.finance-metric .value {
  font-size: 28px;
  font-weight: 700;
  color: var(--el-text-color-primary, #1F2937);
  line-height: 1;
  font-variant-numeric: tabular-nums;
}

.finance-metric .value.primary {
  color: var(--el-color-primary, #165DFF);
}

.finance-metric .unit {
  font-size: 13px;
  font-weight: 500;
  margin-left: 2px;
  color: var(--el-text-color-secondary, #5B6B82);
}

.finance-actions {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-top: 16px;
  padding-top: 16px;
  border-top: 1px solid var(--el-border-color-lighter, #EEF1F6);
}

.finance-links {
  display: flex;
  gap: 4px;
  flex-wrap: wrap;
}

.finance-links a {
  font-size: 12px;
  color: var(--el-color-primary, #165DFF);
  text-decoration: none;
  padding: 4px 10px;
  border-radius: 6px;
  transition: background 0.15s;
}

.finance-links a:hover {
  background: #EFF4FF;
}

/* ── Trend Card ── */
.trend-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}

.trend-block {
  padding: 14px 16px;
  border-radius: 10px;
  background: #F8FAFC;
  border: 1px solid var(--el-border-color-lighter, #EEF1F6);
}

.trend-block .label {
  font-size: 12px;
  color: var(--el-text-color-secondary, #5B6B82);
  margin-bottom: 6px;
}

.trend-block .value {
  font-size: 24px;
  font-weight: 700;
  line-height: 1;
  font-variant-numeric: tabular-nums;
}

.trend-block .value.blue {
  color: var(--el-color-primary, #165DFF);
}

.trend-block .value.orange {
  color: #D97706;
}

.trend-block .unit {
  font-size: 12px;
  font-weight: 500;
  margin-left: 2px;
  color: var(--el-text-color-secondary, #5B6B82);
}

.trend-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-top: 14px;
  padding-top: 14px;
  border-top: 1px solid var(--el-border-color-lighter, #EEF1F6);
  font-size: 12px;
  color: var(--el-text-color-secondary, #5B6B82);
}

.trend-footer a {
  color: var(--el-color-primary, #165DFF);
  text-decoration: none;
  font-weight: 500;
}

.trend-footer a:hover {
  text-decoration: underline;
}

/* ── Todo Card ── */
.todo-card .card-body {
  display: flex;
  flex-direction: column;
}

.todo-card .card-body .todo-list {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 0;
}

.todo-item,
.product-item {
  width: 100%;
  margin: 0;
  background: transparent;
  appearance: none;
  -webkit-appearance: none;
  font: inherit;
  color: inherit;
  text-align: left;
}

.todo-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 10px 0;
  border: none;
  border-bottom: 1px solid var(--el-border-color-lighter, #EEF1F6);
  cursor: pointer;
  transition: background 0.15s;
  border-radius: 0;
}

.todo-item:last-child {
  border-bottom: none;
}

.todo-item:hover {
  background: #FAFBFD;
}

.todo-item:hover .todo-label {
  color: var(--el-color-primary, #165DFF);
}

.todo-left {
  display: flex;
  align-items: center;
  gap: 12px;
}

.todo-icon {
  width: 32px;
  height: 32px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 14px;
  flex-shrink: 0;
}

.todo-icon.blue {
  background: #EFF4FF;
  color: var(--el-color-primary, #165DFF);
}

.todo-icon.orange {
  background: #FFF7ED;
  color: #D97706;
}

.todo-icon.green {
  background: #ECFDF5;
  color: #059669;
}

.todo-icon.gray {
  background: #F3F4F6;
  color: #6B7280;
}

.todo-label {
  font-size: 13px;
  color: var(--el-text-color-primary, #1F2937);
  transition: color 0.15s;
}

.todo-right {
  display: flex;
  align-items: center;
  gap: 6px;
}

.todo-count {
  font-size: 14px;
  font-weight: 700;
  color: var(--el-text-color-primary, #1F2937);
  min-width: 20px;
  text-align: right;
  font-variant-numeric: tabular-nums;
}

.todo-count.has-items {
  color: var(--el-color-danger, #F04438);
}

.todo-arrow {
  color: #C0C4CC;
  font-size: 13px;
}

/* ── Products Card ── */
.product-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 10px;
}

.product-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px 16px;
  background: #FAFBFD;
  border: 1px solid var(--el-border-color-lighter, #EEF1F6);
  border-radius: 10px;
  cursor: pointer;
  transition: border-color 0.2s, background 0.2s;
}

.product-item:hover {
  border-color: #C9DBFF;
  background: #F5F8FF;
}

.product-icon {
  width: 38px;
  height: 38px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 18px;
  flex-shrink: 0;
}

.product-icon.blue {
  background: #EFF4FF;
  color: var(--el-color-primary, #165DFF);
}

.product-icon.azure {
  background: #EFF6FF;
  color: #3B82F6;
}

.product-icon.indigo {
  background: #EEF2FF;
  color: #6366F1;
}

.product-icon.sky {
  background: #F0F9FF;
  color: #0EA5E9;
}

.product-icon.cyan {
  background: #ECFEFF;
  color: #06B6D4;
}

.product-icon.violet {
  background: #F5F3FF;
  color: #8B5CF6;
}

.product-icon.slate {
  background: #F1F5F9;
  color: #64748B;
}

.product-meta {
  min-width: 0;
}

.product-meta strong {
  display: block;
  font-size: 13px;
  font-weight: 600;
  color: var(--el-text-color-primary, #1F2937);
  line-height: 1.3;
}

.product-meta p {
  margin: 3px 0 0;
  font-size: 12px;
  color: var(--el-text-color-secondary, #5B6B82);
}

/* ── Notices Card ── */
.notice-list {
  display: flex;
  flex-direction: column;
  border: 1px solid var(--el-border-color-lighter, #EEF1F6);
  border-radius: 10px;
  overflow: hidden;
}

.notice-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 11px 16px;
  border-bottom: 1px solid var(--el-border-color-lighter, #EEF1F6);
  text-decoration: none;
  color: inherit;
  transition: background-color 0.15s;
}

.notice-item:last-child {
  border-bottom: none;
}

.notice-item:hover {
  background-color: #FAFBFD;
}

.notice-inner {
  display: flex;
  align-items: center;
  gap: 12px;
  flex: 1;
  overflow: hidden;
  cursor: pointer;
}

.notice-badge {
  flex-shrink: 0;
  min-width: 48px;
  max-width: 80px;
  height: 24px;
  padding: 0 10px;
  border-radius: 6px;
  background: #EFF4FF;
  color: var(--el-color-primary, #165DFF);
  font-size: 12px;
  font-weight: 500;
  display: flex;
  align-items: center;
  justify-content: center;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.notice-badge.is-help {
  background: #EFF6FF;
  color: #3B82F6;
}

.notice-time {
  flex-shrink: 0;
  width: 80px;
  font-size: 12px;
  color: var(--el-text-color-placeholder, #94A0B2);
  text-align: right;
  font-variant-numeric: tabular-nums;
}

.notice-title {
  flex: 1;
  font-size: 13px;
  color: var(--el-text-color-primary, #1F2937);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  transition: color 0.15s;
}

.notice-item:hover .notice-title {
  color: var(--el-color-primary, #165DFF);
}

/* ── Support Card ── */
.support-layout {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.support-qr-row {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 14px;
  border-radius: 10px;
  background: #FAFBFD;
  border: 1px solid var(--el-border-color-lighter, #EEF1F6);
}

.qr-box {
  width: 80px;
  height: 80px;
  border-radius: 8px;
  border: 1px solid var(--el-border-color, #E5E9F0);
  background: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
  color: var(--el-text-color-placeholder, #94A0B2);
  flex-shrink: 0;
  overflow: hidden;
}

.qr-box img {
  width: 100%;
  height: 100%;
  object-fit: contain;
}

.qr-placeholder {
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--el-text-color-placeholder, #94A0B2);
}

.qr-info {
  flex: 1;
  min-width: 0;
}

.qr-info h4 {
  margin: 0;
  font-size: 14px;
  font-weight: 600;
  color: var(--el-text-color-primary, #1F2937);
}

.qr-info p {
  margin: 6px 0 0;
  font-size: 12px;
  color: var(--el-text-color-secondary, #5B6B82);
}

.support-contacts {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 0 4px;
}

.contact-item {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  color: var(--el-text-color-secondary, #5B6B82);
}

.contact-item .el-icon {
  font-size: 16px;
  color: var(--el-color-primary, #165DFF);
}

/* ── Quick Links Card ── */
.quick-links-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 8px;
}

.quick-link-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
  padding: 12px 8px;
  border-radius: 10px;
  border: 1px solid var(--el-border-color-lighter, #EEF1F6);
  text-decoration: none;
  color: var(--el-text-color-primary, #1F2937);
  transition: border-color 0.2s, background 0.2s;
}

.quick-link-item:hover {
  border-color: #C9DBFF;
  background: #F5F8FF;
  color: var(--el-color-primary, #165DFF);
}

.quick-link-icon {
  width: 30px;
  height: 30px;
  border-radius: 8px;
  background: #EFF4FF;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 14px;
  color: var(--el-color-primary, #165DFF);
  transition: background 0.15s, color 0.15s;
}

.quick-link-item:hover .quick-link-icon {
  background: var(--el-color-primary, #165DFF);
  color: #fff;
}

.quick-link-item span {
  font-size: 12px;
  font-weight: 500;
}


/* ════════ NEW DESIGN CLASSES ════════ */

/* ── Hero strip ── */
.dash-hero {
  background: #ffffff;
  border: 1px solid var(--c-border);
  border-radius: 12px;
  display: flex;
  align-items: center;
  overflow: hidden;
  position: relative;
}

.hero-acct {
  flex: 0 0 auto;
  padding: 20px 24px;
  border-right: 1px solid var(--c-border);
  display: flex;
  align-items: center;
  z-index: 1;
  align-self: stretch;
}

.hero-user-row {
  display: flex;
  align-items: center;
  gap: 12px;
}

.hero-av {
  flex-shrink: 0;
  background: var(--c-accent-soft) !important;
  color: var(--c-accent) !important;
  font-weight: 600 !important;
  font-size: 16px !important;
}

.hero-name {
  font-size: 15px;
  font-weight: 600;
  color: var(--c-text-1);
  line-height: 1.3;
}

.hero-date {
  font-size: 12px;
  color: var(--c-text-3);
  margin-top: 2px;
}

.hero-metrics {
  flex: 1;
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  align-self: stretch; /* Stretch to fill height */
}

.hero-m {
  padding: 20px;
  display: flex;
  flex-direction: column;
  justify-content: center;
  gap: 4px;
  position: relative;

  &:not(:last-child)::after {
    content: '';
    position: absolute;
    right: 0;
    top: 50%;
    transform: translateY(-50%);
    width: 1px;
    height: 40px;
    background: var(--c-border);
  }
}

.m-lbl  { font-size: 12px; color: var(--c-text-3); }

.m-val {
  font-size: 20px;
  font-weight: 700;
  line-height: 1.2;
  font-variant-numeric: tabular-nums;
  color: var(--c-text-1);

  &.is-blue   { color: var(--c-accent); }
  &.is-orange { color: var(--c-warm); }
}

.hero-balance-num {
  font-size: 22px;
  font-weight: 700;
  color: var(--c-accent);
}

.m-unit { font-size: 12px; font-weight: 400; color: var(--c-text-3); margin-left: 2px; }
.m-sub  { font-size: 11px; color: var(--c-text-3); }

.hero-cta {
  display: flex;
  flex-direction: column;
  justify-content: center;
  gap: 8px;
  padding: 18px 20px;
  border-left: 1px solid var(--c-border);
  z-index: 1;
  align-self: stretch;

  .el-button {
    margin-left: 0;
    padding: 7px 20px;
    height: auto;
    font-size: 13px;
    font-weight: 600;
    border-radius: 6px;
  }

  .el-button--default {
    font-weight: 500;
    color: var(--c-text-2);
    border-color: var(--c-border);
  }
}

.cta-ico { display: none; }

/* ── Layout grid ── */
.dash-grid {
  display: grid;
  grid-template-columns: 1fr 310px;
  gap: 18px;
  align-items: start;
}

.col-l, .col-r {
  display: flex;
  flex-direction: column;
  gap: 18px;
}

/* ── Card title / icon (new header style) ── */
.card-title {
  font-size: 14px;
  font-weight: 600;
  color: var(--c-text-1);
}

.card-icon {
  display: none;
}

.ci-orange {
  display: none;
}

/* ── Products ── */
.prod-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 10px;
  min-height: 60px;
}

.prod-item {
  display: flex;
  align-items: center;
  gap: 11px;
  padding: 12px 14px;
  background: var(--c-bg-subtle);
  border: none;
  border-radius: 8px;
  cursor: pointer;
  text-align: left;
  width: 100%;
  transition: background .15s;

  &:hover {
    background: var(--c-accent-soft);
  }
}

.prod-ico {
  width: 34px;
  height: 34px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  font-size: 16px;
  background: #fff;
  color: var(--c-accent);
  border: 1px solid var(--c-border);
}

.pi-blue, .pi-azure, .pi-indigo, .pi-sky, .pi-cyan, .pi-violet, .pi-slate {
  background: #fff;
  color: var(--c-accent);
  border: 1px solid var(--c-border);
}

.prod-meta {
  min-width: 0;

  strong { display: block; font-size: 13px; font-weight: 500; color: var(--c-text-1); line-height: 1.3; }
  span   { font-size: 12px; color: var(--c-text-3); }
}

/* ── Product skeleton ── */
.prod-item--skeleton {
  cursor: default;
  pointer-events: none;
}

.prod-ico--skeleton {
  background: rgba(148, 163, 184, 0.1) !important;
}

.prod-meta--skeleton {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.skel-line {
  display: block;
  border-radius: 4px;
  background: rgba(148, 163, 184, 0.1);
  position: relative;
  overflow: hidden;

  &::after {
    content: '';
    position: absolute;
    inset: 0;
    transform: translateX(-100%);
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.6), transparent);
    animation: chartSkeletonShimmer 1.4s ease infinite;
  }
}

.skel-line--title { width: 72%; height: 14px; }
.skel-line--sub { width: 48%; height: 11px; }

/* ── Notice skeleton ── */
.notice-skeleton {
  display: flex;
  flex-direction: column;
  gap: 14px;
  padding: 4px 0;
}

.notice-skeleton__row {
  display: flex;
  align-items: center;
  gap: 10px;
}

/* ── Tab bar (notices/help) ── */
.tab-bar {
  display: flex;
  gap: 24px;
  padding: 12px 20px 0;
}

.tab {
  padding: 0 0 8px;
  font-size: 13px;
  font-weight: 500;
  color: var(--c-text-2);
  border: none;
  border-bottom: 2px solid transparent;
  background: none;
  cursor: pointer;
  transition: all .14s;

  &.active  { color: var(--c-accent); border-bottom-color: var(--c-accent); font-weight: 500; }
  &:hover:not(.active) { color: var(--c-text-1); }
}

.tab-body {
  padding: 8px 20px 16px;
  min-height: 80px;
}

.n-row {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 9px 0;
  color: inherit;
  text-decoration: none;
  cursor: pointer;

  &:hover .n-title { color: var(--c-accent); }
}

.n-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: var(--c-accent);
  flex-shrink: 0;

  &.is-purple { background: #6366f1; }
}

.n-title {
  flex: 1;
  font-size: 13px;
  color: var(--c-text-1);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  transition: color .14s;
}

.n-time {
  font-size: 11px;
  color: var(--c-text-3);
  white-space: nowrap;
  font-variant-numeric: tabular-nums;
  flex-shrink: 0;
}

/* ── Todo ── */
.todo-body { padding: 8px 20px 16px; }
.todo-list { display: flex; flex-direction: column; }

.todo-row {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 0;
  border: none;
  background: none;
  cursor: pointer;
  width: 100%;
  text-align: left;
  border-bottom: 1px solid var(--c-border);

  &:last-child { border-bottom: none; }
  &:hover .todo-lbl { color: var(--c-accent); }
}

.todo-ico {
  display: none;
}

.ti-orange, .ti-blue, .ti-green, .ti-gray { display: none; }

.todo-lbl {
  flex: 1;
  font-size: 13px;
  font-weight: 400;
  color: var(--c-text-1);
  transition: color .14s;
}

.todo-num {
  font-size: 13px;
  font-weight: 600;
  color: var(--c-text-2);
  font-variant-numeric: tabular-nums;
  min-width: 20px;
  text-align: right;
}

.todo-num.tn-danger { color: #ef4444; }

.todo-right {
  display: flex;
  align-items: center;
  gap: 6px;
}

.todo-arr {
  color: var(--c-text-3);
  font-size: 12px;
  flex-shrink: 0;
}

/* ── Chart Row Layout ── */
.dash-chart-row {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(0, 1.45fr);
  gap: 18px;
  align-items: stretch;
}

.pie-card, .bar-card {
  margin-bottom: 0 !important;
  display: flex;
  flex-direction: column;
  height: 100%;

  .card-icon { background: var(--c-accent-soft); }

  .card-body {
    flex: 1;
    display: flex;
    flex-direction: column;
    justify-content: center;
  }
}

.card-date {
  font-size: 12px;
  color: var(--c-text-3);
  align-self: center;
  margin-top: 1px;
}

/* ── Donut Chart ── */
.donut-wrap {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 24px;
  min-height: 168px;
  padding: 4px 6px;
}

.donut-chart-box {
  position: relative;
  width: 140px;
  height: 140px;
  flex-shrink: 0;
}

.donut-svg {
  width: 100%;
  height: 100%;
  transform: rotate(0deg);
}

@keyframes donutGrow {
  from { stroke-dasharray: 0 188.5; }
}

.donut-slice {
  transition: stroke-width 0.25s, opacity 0.25s;
  cursor: pointer;
  animation: donutGrow 0.65s cubic-bezier(0.25, 1, 0.5, 1) both;

  &:hover, &.is-active {
    stroke-width: 12.5;
    opacity: 0.92;
  }
}

.donut-center-text {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  width: 72px;
  pointer-events: none;
}

.donut-center-lbl {
  font-size: 10px;
  color: var(--c-text-2);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 100%;
  margin-bottom: 2px;
}

.donut-center-val {
  font-size: 13.5px;
  font-weight: 700;
  color: var(--c-text-1);
  font-variant-numeric: tabular-nums;
  line-height: 1.1;
}

/* ── Donut Legend ── */
.donut-legend {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 6.5px;
  justify-content: center;
}

.donut-legend-row {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 4.5px 8px;
  border-radius: 6px;
  cursor: pointer;
  transition: background-color 0.15s;

  &:hover, &.is-active {
    background-color: var(--c-bg-subtle);
  }
}

.donut-legend-dot {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  flex-shrink: 0;
}

.donut-legend-name {
  flex: 1;
  font-size: 11.5px;
  color: var(--c-text-1);
  font-weight: 500;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.donut-legend-percent {
  font-size: 11px;
  color: var(--c-text-2);
  width: 28px;
  text-align: right;
}

.donut-legend-amount {
  font-size: 11.5px;
  font-weight: 600;
  color: var(--c-text-1);
  font-variant-numeric: tabular-nums;
  width: 58px;
  text-align: right;
}

/* ── Expense bar chart ── */
.echart-wrap {
  position: relative;
  min-height: 120px;
  display: flex;
  align-items: center;

  svg { width: 100%; height: auto; display: block; overflow: visible; }
}

.echart-foot {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-top: 10px;
  padding-top: 10px;
  border-top: 1px solid var(--c-border);
  font-size: 12px;
  color: var(--c-text-2);

  strong { font-weight: 700; color: var(--c-text-1); }
}

/* ── Chart Skeleton (替代 v-loading 灰色遮罩) ── */
.chart-skeleton {
  width: 100%;
  display: flex;
  align-items: center;
  animation: chartSkeletonFadeIn 0.25s ease-out both;
}

.chart-skeleton--pie {
  gap: 24px;
  min-height: 148px;
  padding: 8px 0;
}

.chart-skeleton__ring {
  flex-shrink: 0;
  width: 108px;
  height: 108px;
  border-radius: 50%;
  border: 10px solid rgba(148, 163, 184, 0.12);
  position: relative;
  overflow: hidden;

  &::after {
    content: '';
    position: absolute;
    inset: -10px;
    border-radius: 50%;
    border: 10px solid transparent;
    border-top-color: rgba(22, 93, 255, 0.15);
    animation: chartSkeletonSpin 1.2s linear infinite;
  }
}

.chart-skeleton__legend {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.chart-skeleton__bar {
  display: block;
  height: 12px;
  border-radius: 6px;
  background: rgba(148, 163, 184, 0.1);
  position: relative;
  overflow: hidden;

  &:nth-child(1) { width: 85%; }
  &:nth-child(2) { width: 68%; }
  &:nth-child(3) { width: 76%; }
  &:nth-child(4) { width: 52%; }

  &::after {
    content: '';
    position: absolute;
    inset: 0;
    transform: translateX(-100%);
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.6), transparent);
    animation: chartSkeletonShimmer 1.4s ease infinite;
  }
}

.chart-skeleton--bar {
  min-height: 120px;
  align-items: flex-end;
  gap: 6px;
  padding: 12px 0;
}

.chart-skeleton__col {
  flex: 1;
  border-radius: 3px 3px 0 0;
  background: rgba(148, 163, 184, 0.1);
  position: relative;
  overflow: hidden;
  animation: chartSkeletonGrow 0.6s ease-out both;

  &::after {
    content: '';
    position: absolute;
    inset: 0;
    transform: translateX(-100%);
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.6), transparent);
    animation: chartSkeletonShimmer 1.4s ease infinite;
  }
}

@keyframes chartSkeletonFadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}

@keyframes chartSkeletonSpin {
  to { transform: rotate(360deg); }
}

@keyframes chartSkeletonShimmer {
  0% { transform: translateX(-100%); }
  100% { transform: translateX(100%); }
}

@keyframes chartSkeletonGrow {
  from { transform: scaleY(0.3); opacity: 0; }
  to { transform: scaleY(1); opacity: 1; }
}

/* ── Quick links ── */
.quick-body { padding: 8px 20px 16px; }

.quick-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 4px;
}

.q-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
  padding: 12px 8px;
  border-radius: 8px;
  color: var(--c-text-2);
  font-size: 12px;
  font-weight: 500;
  text-align: center;
  text-decoration: none;
  transition: color .15s, background .15s;

  .el-icon { font-size: 18px; }

  &:hover {
    color: var(--c-accent);
    background: var(--c-bg-subtle);
  }
}

.q-ico { display: none; }

/* ── QQ Support ── */
.sup-box {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 14px;
  background: var(--c-bg-subtle);
  border-radius: 10px;
}

.qr-box {
  width: 80px;
  height: 80px;
  flex-shrink: 0;
  border-radius: 8px;
  border: 1px solid var(--c-border);
  background: white;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;

  img { width: 100%; height: 100%; object-fit: contain; }
}

.qr-ph {
  display: block;
  width: 100%;
  height: 100%;
  padding: 10px;
  box-sizing: border-box;

  .mock-qr-svg {
    display: block;
    width: 100%;
    height: 100%;
  }
}

.sup-txt {
  display: flex;
  flex-direction: column;
  gap: 8px;

  h4 { font-size: 14px; font-weight: 600; color: var(--c-text-1); margin: 0; }
  p  { font-size: 12px; color: var(--c-text-2); margin: 0; }

  .el-button {
    align-self: flex-start;
  }
}

/* ── Entry animations ── */
@keyframes fadeUp {
  from { opacity: 0; transform: translateY(6px); }
  to   { opacity: 1; transform: translateY(0); }
}

.dash-hero                  { animation: fadeUp .25s ease both; }
.col-l > .card:nth-child(1) { animation: fadeUp .25s .04s ease both; }
.col-l > .dash-chart-row    { animation: fadeUp .25s .08s ease both; }
.col-l > .card:nth-child(3) { animation: fadeUp .25s .12s ease both; }
.col-r > .card:nth-child(1) { animation: fadeUp .25s .06s ease both; }
.col-r > .card:nth-child(2) { animation: fadeUp .25s .10s ease both; }
.col-r > .card:nth-child(3) { animation: fadeUp .25s .14s ease both; }

/* ── Card hover lift ── */
.card {
  transition: box-shadow .2s;
}

/* ── Hero metric reveal (removed — keep it simple) ── */

/* ── Bar chart ── */
@keyframes growBar {
  from { transform: scaleY(0); }
  to   { transform: scaleY(1); }
}

.echart-svg {
  width: 100%;
  height: auto;
  display: block;
  overflow: visible;

  text {
    transition: font-size 0.2s ease;
  }
}

.echart-bar {
  transform-box: fill-box;
  transform-origin: bottom center;
  animation: growBar .55s var(--bar-delay, 0ms) cubic-bezier(0.34, 1.56, 0.64, 1) both;
  cursor: default;
  transition: opacity .15s;
  &:hover { opacity: .78; }
}

/* ── Tab transition ── */
.tab-fade-enter-active { transition: opacity .18s ease, transform .18s ease; }
.tab-fade-leave-active { transition: opacity .12s ease, transform .12s ease; }
.tab-fade-enter-from   { opacity: 0; transform: translateY(5px); }
.tab-fade-leave-to     { opacity: 0; transform: translateY(-3px); }

/* ── Responsive ── */
@media (max-width: 1200px) {
  .hero-acct { flex: 0 0 auto; }
  .hero-metrics { grid-template-columns: repeat(2, 1fr); }
}

@media (max-width: 1024px) {
  .dash-hero { flex-wrap: wrap; height: auto; }
  .hero-acct { flex: 0 0 100%; border-right: none; border-bottom: 1px solid var(--c-border); padding: 18px 22px; align-self: auto; }
  .hero-acct::after { display: none; }
  .hero-metrics { flex: 1; grid-template-columns: repeat(4, 1fr); }
  .hero-cta { flex-direction: row; justify-content: center; width: 100%; padding: 18px 20px; border-left: none; border-top: 1px solid var(--c-border); align-self: auto; }
  .dash-grid { grid-template-columns: 1fr; }
  .col-r { display: grid; grid-template-columns: repeat(3, 1fr); }
  .dash-chart-row { grid-template-columns: 1fr; }
}

@media (max-width: 768px) {
  .dashboard-page { --card-px: 16px; }
  .dash-grid { grid-template-columns: 1fr; }
  .col-r { display: flex; flex-direction: column; }
  .dash-hero { flex-wrap: wrap; height: auto; }
  .hero-acct { flex: 0 0 100%; border-right: none; padding: 14px; align-self: auto; border-bottom: 1px solid var(--c-border); }
  .hero-acct::after { display: none; }
  .hero-av { width: 36px !important; height: 36px !important; font-size: 14px !important; }
  .hero-user-row { gap: 10px; }
  .hero-name { font-size: 14px; }
  .hero-date { font-size: 11px; }
  .hero-metrics { flex: 1 1 100%; grid-template-columns: repeat(2, 1fr); }
  .hero-m { padding: 12px; }
  .hero-m::after { display: none; }
  .hero-m:nth-child(-n+2) { border-bottom: 1px solid var(--c-border); }
  .m-lbl { font-size: 11px; }
  .m-val { font-size: 18px; }
  .m-unit { font-size: 11px; }
  .m-sub { font-size: 10px; }
  .hero-balance-num { font-size: 20px; }
  .hero-cta { flex-direction: row; width: 100%; padding: 12px; border-left: none; border-top: 1px solid var(--c-border); gap: 10px; align-self: auto; }
  .hero-cta .el-button { flex: 1; height: 38px; padding: 0; font-size: 13px; font-weight: 600; border-radius: 8px; }
  .hero-cta .el-button--default { border: none; background: var(--c-bg-subtle); color: var(--c-text-1); font-weight: 500; }
  .cta-ico { display: inline-flex; margin-right: 5px; font-size: 15px; }

  .card-head { padding: 18px 16px 0; }
  .card-head .el-button { font-size: 12px; }
  .card-body { padding: 16px; }
  .card-title { font-size: 13px; }

  .prod-grid { grid-template-columns: repeat(2, 1fr); }
  .prod-item { padding: 12px; gap: 10px; }
  .prod-ico { width: 34px; height: 34px; font-size: 15px; }
  .prod-name { font-size: 12px; }
  .prod-count { font-size: 11px; }

  .tab-bar { padding: 10px 16px 0; gap: 20px; }
  .tab-body { padding: 6px 16px 14px; }

  .todo-body { padding: 10px 16px 16px; }
  .quick-body { padding: 12px 16px 16px; }

  .sup-box { padding: 14px; gap: 14px; }
  .qr-box { width: 68px; height: 68px; }

  .dash-chart-row { grid-template-columns: 1fr; }
  .bar-card { display: none; }
  .echart-svg text { font-size: 15px !important; font-weight: 600; }
}

@media (max-width: 480px) {
  .dashboard-page { --card-px: 14px; }
  .hero-acct { padding: 12px; }
  .hero-m { padding: 10px; }
  .m-val { font-size: 16px; }
  .hero-balance-num { font-size: 18px; }
  .hero-metrics { grid-template-columns: 1fr 1fr; }
  .hero-cta {
    flex-direction: row;
    padding: 12px;
    gap: 10px;
  }
  .hero-cta .el-button {
    flex: 1;
    height: 38px;
    padding: 0 12px;
    font-size: 13px;
    font-weight: 600;
    border-radius: 8px;
  }
  .hero-cta .el-button--default {
    background: var(--c-bg-subtle);
    border: none;
    color: var(--c-text-1);
    font-weight: 500;
  }
  .cta-ico { display: inline-flex; margin-right: 5px; font-size: 15px; }
  .prod-grid { grid-template-columns: 1fr 1fr; }
}
</style>
