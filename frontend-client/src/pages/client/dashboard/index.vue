<template>
  <div class="client-page dashboard-page">
    <section class="dashboard-grid row-top">
      <!-- 账户卡片 -->
      <article class="card account-card">
        <header class="card-head">
          <h3>账户</h3>
        </header>
        <div class="card-body">
          <div class="account-row">
            <el-avatar :size="52" :src="avatarUrl" class="account-avatar">
              {{ displayName.slice(0, 1) || '客' }}
            </el-avatar>
            <div class="account-info">
              <div class="account-name">{{ displayName }}</div>
              <div class="account-id">ID: {{ userIdText }}</div>
            </div>
          </div>
          <div class="account-badges">
            <span class="chip" :class="isVerified ? 'chip-success' : 'chip-default'">
              <span class="chip-dot"></span>{{ isVerified ? '已实名' : '待实名' }}
            </span>
            <span class="chip" :class="isPhoneBound ? 'chip-success' : 'chip-default'">
              <span class="chip-dot"></span>{{ isPhoneBound ? '已绑手机' : '未绑手机' }}
            </span>
            <span class="chip" :class="isEmailBound ? 'chip-success' : 'chip-default'">
              <span class="chip-dot"></span>{{ isEmailBound ? '已绑邮箱' : '未绑邮箱' }}
            </span>
          </div>
        </div>
      </article>

      <!-- 财务概览卡片 -->
      <article class="card finance-card">
        <header class="card-head">
          <h3>财务概览</h3>
        </header>
        <div class="card-body">
          <div class="finance-metrics">
            <div class="finance-metric">
              <div class="label">账户余额</div>
              <div class="value primary">{{ financeSummary.balance || '0.00' }}<span class="unit">元</span></div>
            </div>
            <div class="finance-metric">
              <div class="label">我的优惠券</div>
              <div class="value">{{ couponSummary.available || 0 }}<span class="unit">张</span></div>
            </div>
          </div>
          <div class="finance-actions">
            <el-button type="primary" @click="router.push('/client/recharge')">在线充值</el-button>
            <div class="finance-links">
              <router-link to="/client/balance-logs">财务明细</router-link>
              <router-link to="/client/invoices">订单管理</router-link>
            </div>
          </div>
        </div>
      </article>

      <!-- 本月收支卡片 -->
      <article class="card trend-card">
        <header class="card-head">
          <h3>本月收支</h3>
        </header>
        <div class="card-body">
          <div class="trend-row">
            <div class="trend-block">
              <div class="label">本月充值</div>
              <div class="value blue">{{ financeSummary.recharge_in || '0.00' }}<span class="unit">元</span></div>
            </div>
            <div class="trend-block">
              <div class="label">本月消费</div>
              <div class="value orange">{{ financeSummary.invoice_payment_out || '0.00' }}<span class="unit">元</span></div>
            </div>
          </div>
          <div class="trend-footer">
            <span>今日消费：{{ todayExpenseText }} 元</span>
            <router-link to="/client/balance-logs">消费明细 →</router-link>
          </div>
        </div>
      </article>
    </section>

    <section class="dashboard-grid row-mid" style="margin-top: 16px;">
      <!-- 待办事项卡片 -->
      <article class="card todo-card">
        <header class="card-head">
          <div class="card-head-left">
            <el-icon class="card-head-icon"><Bell /></el-icon>
            <h3>待办事项</h3>
          </div>
        </header>
        <div class="card-body">
          <div class="todo-list">
            <button
              v-for="item in todoItems"
              :key="item.key"
              type="button"
              class="todo-item"
              @click="router.push(item.path)"
            >
              <div class="todo-left">
                <div class="todo-icon" :class="item.iconClass">
                  <el-icon><component :is="item.icon" /></el-icon>
                </div>
                <span class="todo-label">{{ item.label }}</span>
              </div>
              <div class="todo-right">
                <span class="todo-count" :class="{ 'has-items': item.count > 0 }">{{ item.count }}</span>
                <span class="todo-arrow">›</span>
              </div>
            </button>
          </div>
        </div>
      </article>

      <!-- 产品卡片 -->
      <article class="card products-card">
        <header class="card-head">
          <div class="card-head-left">
            <el-icon class="card-head-icon"><Grid /></el-icon>
            <h3>产品</h3>
          </div>
          <el-button text type="primary" @click="router.push('/client/services')">管理全部 →</el-button>
        </header>
        <div class="card-body">
          <div class="product-grid" v-loading="loading">
            <button
              v-for="item in productCards"
              :key="item.key"
              type="button"
              class="product-item"
              @click="handleProductCardClick(item)"
            >
              <div class="product-icon" :class="'is-' + item.theme">
                <el-icon><component :is="item.icon" /></el-icon>
              </div>
              <div class="product-meta">
                <strong>{{ item.title }}</strong>
                <p>{{ item.countText }}</p>
              </div>
            </button>
          </div>
        </div>
      </article>
    </section>

    <section class="dashboard-grid row-bot" style="margin-top: 16px;">
      <!-- 新闻公告卡片 -->
      <article class="card notices-card">
        <header class="card-head">
          <div class="card-head-left">
            <el-icon class="card-head-icon"><Notification /></el-icon>
            <h3>新闻公告</h3>
          </div>
          <el-button text type="primary" @click="router.push('/client/notices')">查看全部 →</el-button>
        </header>
        <div class="card-body">
          <div class="notice-list" v-loading="loading">
            <router-link
              v-for="item in recentNotices"
              :key="item.id"
              class="notice-item"
              :to="`/client/notices/${item.id}`"
            >
              <el-tooltip :content="item.title" placement="top" :show-after="300">
                <div class="notice-inner">
                  <span class="notice-badge">{{ item.category || '公告' }}</span>
                  <span class="notice-title">{{ item.title }}</span>
                  <span class="notice-time">{{ formatDate(item.publish_at || item.created_at) }}</span>
                </div>
              </el-tooltip>
            </router-link>
            <el-empty v-if="!recentNotices.length && !loading" description="暂无公告" :image-size="68" />
          </div>
        </div>
      </article>

      <!-- 帮助中心卡片 -->
      <article class="card help-card">
        <header class="card-head">
          <div class="card-head-left">
            <el-icon class="card-head-icon"><Document /></el-icon>
            <h3>帮助中心</h3>
          </div>
          <el-button text type="primary" @click="router.push('/client/help')">查看全部 →</el-button>
        </header>
        <div class="card-body">
          <div class="notice-list" v-loading="loading">
            <router-link
              v-for="item in recentHelpArticles"
              :key="item.id"
              class="notice-item"
              :to="`/client/help/${item.id}`"
            >
              <el-tooltip :content="item.title" placement="top" :show-after="300">
                <div class="notice-inner">
                  <span class="notice-badge is-help">{{ item.category || '帮助' }}</span>
                  <span class="notice-title">{{ item.title }}</span>
                  <span class="notice-time">{{ formatDate(item.publish_at || item.created_at) }}</span>
                </div>
              </el-tooltip>
            </router-link>
            <el-empty v-if="!recentHelpArticles.length && !loading" description="暂无帮助内容" :image-size="68" />
          </div>
        </div>
      </article>

      <!-- QQ群聊+快捷入口容器 -->
      <div class="right-stack">
        <!-- 官方QQ群聊卡片 -->
        <article class="card support-card">
          <header class="card-head">
            <div class="card-head-left">
              <el-icon class="card-head-icon"><Headset /></el-icon>
              <h3>官方QQ群聊</h3>
            </div>
          </header>
          <div class="card-body">
            <div class="support-layout">
              <div class="support-qr-row">
                <div class="qr-box">
                  <img v-if="supportQr" :src="supportQr" alt="QQ群二维码" />
                  <span v-else class="qr-placeholder">二维码</span>
                </div>
                <div class="qr-info">
                  <h4>加入官方QQ群聊</h4>
                  <p>获取最新活动与帮助信息</p>
                </div>
              </div>
              <div class="support-contacts">
                <div class="contact-item">
                  <el-icon><ChatLineSquare /></el-icon>
                  <span>群号：{{ supportPhoneText }}</span>
                  <el-button
                    v-if="supportGroupLink"
                    type="primary"
                    size="small"
                    tag="a"
                    :href="supportGroupLink"
                    target="_blank"
                    style="margin-left: 8px;"
                  >
                    加入群聊
                  </el-button>
                </div>
              </div>
            </div>
          </div>
        </article>

        <!-- 快捷入口卡片 -->
        <article class="card quick-links-card">
          <header class="card-head">
            <div class="card-head-left">
              <el-icon class="card-head-icon"><Link /></el-icon>
              <h3>快捷入口</h3>
            </div>
          </header>
          <div class="card-body">
            <div class="quick-links-grid">
              <router-link to="/client/services" class="quick-link-item">
                <div class="quick-link-icon">
                  <el-icon><Box /></el-icon>
                </div>
                <span>我的服务</span>
              </router-link>
              <router-link to="/client/invoices" class="quick-link-item">
                <div class="quick-link-icon">
                  <el-icon><Tickets /></el-icon>
                </div>
                <span>订单管理</span>
              </router-link>
              <router-link to="/client/balance-logs" class="quick-link-item">
                <div class="quick-link-icon">
                  <el-icon><Wallet /></el-icon>
                </div>
                <span>财务明细</span>
              </router-link>
              <router-link to="/client/tickets" class="quick-link-item">
                <div class="quick-link-icon">
                  <el-icon><Message /></el-icon>
                </div>
                <span>工单管理</span>
              </router-link>
              <router-link to="/client/recharge" class="quick-link-item">
                <div class="quick-link-icon">
                  <el-icon><Coin /></el-icon>
                </div>
                <span>在线充值</span>
              </router-link>
              <router-link to="/client/help" class="quick-link-item">
                <div class="quick-link-icon">
                  <el-icon><QuestionFilled /></el-icon>
                </div>
                <span>帮助中心</span>
              </router-link>
            </div>
          </div>
        </article>
      </div>
    </section>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
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
  QuestionFilled,
  SetUp,
  Suitcase,
  Tickets,
  User,
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
    key: 'renew',
    label: '待续费产品',
    count: expiringServiceCount.value,
    path: '/client/services',
    icon: Tickets,
    iconClass: 'orange',
  },
  {
    key: 'invoice',
    label: '未完成订单',
    count: Number(financeSummary.value.unpaid_count || 0),
    path: '/client/invoices',
    icon: Wallet,
    iconClass: 'blue',
  },
  {
    key: 'ticket',
    label: '未处理工单',
    count: openTicketCount.value,
    path: '/client/tickets',
    icon: Message,
    iconClass: 'green',
  },
  {
    key: 'notice',
    label: '未读通知',
    count: recentNotices.value.length,
    path: '/client/notices',
    icon: Bell,
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
    if (!userStore.info || userStore.userType !== 'client') {
      await userStore.fetchUserInfo('client')
    }

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
.dashboard-page {
  --card-padding-x: 20px;
  --card-padding-y: 16px;
  gap: 16px;
}

.dashboard-page-head {
  margin-bottom: -4px;
}

.dashboard-grid {
  display: grid;
  gap: 16px;
}

.row-top {
  grid-template-columns: 280px 1fr 1fr;
}

.row-mid {
  grid-template-columns: 1fr 1fr;
}

.row-bot {
  grid-template-columns: 1fr 360px;
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
  gap: 16px;
}

.card {
  border: 1px solid var(--el-border-color, #E5E9F0);
  border-radius: 14px;
  background: var(--el-fill-color-blank, #FFFFFF);
  box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04), 0 1px 2px rgba(15, 23, 42, 0.02);
  overflow: hidden;
  transition: box-shadow 0.15s;
}

.card:hover {
  box-shadow: 0 4px 12px rgba(15, 23, 42, 0.06);
}

.card-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px var(--card-padding-x) 0;
}

.card-head h3 {
  margin: 0;
  font-size: 14px;
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
  width: 28px;
  height: 28px;
  border-radius: 8px;
  background: var(--el-color-primary-light-9, #E8F1FF);
  color: var(--el-color-primary, #165DFF);
  margin-right: 8px;
  font-size: 14px;
}

.card-body {
  padding: var(--card-padding-y) var(--card-padding-x) 20px;
}

/* Account Card */
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
  background: var(--el-color-primary-light-9, #E8F1FF);
  color: var(--el-color-primary, #165DFF);
}

.account-info {
  min-width: 0;
}

.account-name {
  font-size: 18px;
  font-weight: 700;
  color: var(--el-text-color-primary, #1F2937);
  line-height: 1.2;
}

.account-id {
  font-size: 11px;
  color: var(--el-text-color-secondary, #94A0B2);
  margin-top: 2px;
}

.chip {
  display: inline-flex;
  align-items: center;
  height: 22px;
  padding: 0 8px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 600;
  gap: 4px;
}

.chip-success {
  background: var(--el-color-success-light-9, #EAFBF3);
  color: var(--el-color-success, #12B76A);
}

.chip-warning {
  background: var(--el-color-warning-light-9, #FFF6E5);
  color: var(--c-orange, #DD7A1F);
}

.chip-default {
  background: #F3F4F6;
  color: var(--el-text-color-secondary, #94A0B2);
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
  gap: 4px;
}

/* Finance Card */
.finance-metrics {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0;
}

.finance-metric {
  text-align: center;
  padding: 8px 0;
  position: relative;
}

.finance-metric + .finance-metric::before {
  content: '';
  position: absolute;
  left: 0;
  top: 12px;
  bottom: 12px;
  width: 1px;
  background: var(--el-border-color-lighter, #EEF1F6);
}

.finance-metric .label {
  font-size: 11px;
  color: var(--el-text-color-secondary, #94A0B2);
  margin-bottom: 6px;
}

.finance-metric .value {
  font-size: 26px;
  font-weight: 700;
  color: var(--el-text-color-primary, #1F2937);
  line-height: 1;
}

.finance-metric .value.primary {
  color: var(--el-color-primary, #165DFF);
}

.finance-metric .unit {
  font-size: 13px;
  font-weight: 500;
  margin-left: 2px;
}

.finance-actions {
  display: flex;
  gap: 8px;
  margin-top: 14px;
  padding-top: 14px;
  border-top: 1px solid var(--el-border-color-lighter, #EEF1F6);
}

.finance-links {
  display: flex;
  gap: 4px;
  flex-wrap: wrap;
}

.finance-links a {
  font-size: 11px;
  color: var(--el-color-primary, #165DFF);
  text-decoration: none;
  padding: 4px 8px;
  border-radius: 6px;
  transition: background 0.15s;
}

.finance-links a:hover {
  background: var(--el-color-primary-light-9, #E8F1FF);
}

/* Trend Card */
.trend-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}

.trend-block {
  padding: 14px 16px;
  border-radius: 10px;
  border: 1px solid var(--el-border-color-lighter, #EEF1F6);
}

.trend-block .label {
  font-size: 11px;
  color: var(--el-text-color-secondary, #94A0B2);
  margin-bottom: 6px;
}

.trend-block .value {
  font-size: 24px;
  font-weight: 700;
  line-height: 1;
}

.trend-block .value.blue {
  color: var(--el-color-primary, #165DFF);
}

.trend-block .value.orange {
  color: var(--c-orange, #DD7A1F);
}

.trend-block .unit {
  font-size: 11px;
  font-weight: 500;
  margin-left: 2px;
}

.trend-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-top: 12px;
  padding-top: 12px;
  border-top: 1px solid var(--el-border-color-lighter, #EEF1F6);
  font-size: 11px;
  color: var(--el-text-color-secondary, #94A0B2);
}

.trend-footer a {
  color: var(--el-color-primary, #165DFF);
  text-decoration: none;
  font-weight: 500;
}

/* Todo Card */
.todo-card .card-body {
  display: flex;
  flex-direction: column;
}

.todo-card .card-body .todo-list {
  flex: 1;
  display: flex;
  flex-direction: column;
  justify-content: flex-end;
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
  padding: 5px 0;
  border: none;
  border-bottom: 1px solid var(--el-border-color-lighter, #EEF1F6);
  cursor: pointer;
  transition: background 0.1s;
}

.todo-item:last-child {
  border-bottom: none;
}

.todo-item:hover .todo-label {
  color: var(--el-color-primary, #165DFF);
}

.todo-left {
  display: flex;
  align-items: center;
  gap: 8px;
}

.todo-icon {
  width: 28px;
  height: 28px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 13px;
  flex-shrink: 0;
}

.todo-icon.blue {
  background: var(--el-color-primary-light-9, #E8F1FF);
  color: var(--el-color-primary, #165DFF);
}

.todo-icon.orange {
  background: rgba(221, 122, 31, 0.12);
  color: var(--c-orange, #DD7A1F);
}

.todo-icon.green {
  background: var(--el-color-success-light-9, #EAFBF3);
  color: var(--el-color-success, #12B76A);
}

.todo-icon.gray {
  background: #F3F4F6;
  color: var(--el-text-color-secondary, #94A0B2);
}

.todo-label {
  font-size: 12px;
  color: var(--el-text-color-primary, #1F2937);
  transition: color 0.15s;
}

.todo-right {
  display: flex;
  align-items: center;
  gap: 4px;
}

.todo-count {
  font-size: 13px;
  font-weight: 700;
  color: var(--el-text-color-primary, #1F2937);
  min-width: 18px;
  text-align: right;
}

.todo-count.has-items {
  color: var(--el-color-danger, #F04438);
}

.todo-arrow {
  color: var(--el-text-color-secondary, #94A0B2);
  font-size: 11px;
}

/* Products Card */
.product-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 8px;
}

.product-item {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 12px 14px;
  background: var(--el-fill-color-blank, #FFFFFF);
  border: 1px solid var(--el-border-color-lighter, #EEF1F6);
  border-radius: 10px;
  cursor: pointer;
  transition: border-color 0.15s, box-shadow 0.15s;
}

.product-item:hover {
  border-color: #c9dbff;
  box-shadow: 0 2px 8px rgba(22, 93, 255, 0.06);
}

.product-icon {
  width: 36px;
  height: 36px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 17px;
  flex-shrink: 0;
}

.product-icon.blue {
  background: var(--el-color-primary-light-9, #E8F1FF);
  color: var(--el-color-primary, #165DFF);
}

.product-icon.azure {
  background: #EBF5FF;
  color: #3699FF;
}

.product-icon.indigo {
  background: #EEF0FF;
  color: #5B69FF;
}

.product-icon.sky {
  background: #E6F6FF;
  color: #28A5F7;
}

.product-icon.cyan {
  background: #E8F6F8;
  color: #22A8BD;
}

.product-icon.violet {
  background: #F3F0FF;
  color: #5D6FFF;
}

.product-icon.slate {
  background: #F1F3F6;
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
  line-height: 1.2;
}

.product-meta p {
  margin: 2px 0 0;
  font-size: 11px;
  color: var(--el-text-color-secondary, #94A0B2);
}

/* Notices Card */
.notice-list {
  display: flex;
  flex-direction: column;
  border: 1px solid var(--el-border-color-lighter, #EEF1F6);
  border-radius: 8px;
  overflow: hidden;
}

.notice-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 14px;
  border-bottom: 1px solid var(--el-border-color-lighter, #EEF1F6);
  text-decoration: none;
  color: inherit;
  transition: background-color 0.15s;
}

.notice-item:last-child {
  border-bottom: none;
}

.notice-item:hover {
  background-color: var(--el-fill-color-light, #F5F7FA);
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
  min-width: 44px;
  max-width: 80px;
  height: 22px;
  padding: 0 8px;
  border-radius: 4px;
  background: var(--el-color-primary-light-9, #E8F1FF);
  color: var(--el-color-primary, #165DFF);
  font-size: 11px;
  font-weight: 600;
  display: flex;
  align-items: center;
  justify-content: center;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.notice-badge.is-help {
  background: #EEF6FF;
  color: #3699FF;
}

.notice-time {
  flex-shrink: 0;
  width: 75px;
  font-size: 12px;
  color: var(--el-text-color-secondary, #94A0B2);
  text-align: right;
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

/* Support Card */
.support-layout {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.support-qr-row {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 12px;
  border-radius: 10px;
  background: #FAFBFD;
  border: 1px solid var(--el-border-color-lighter, #EEF1F6);
}

.qr-box {
  width: 88px;
  height: 88px;
  border-radius: 8px;
  border: 1px solid var(--el-border-color, #E5E9F0);
  background: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 11px;
  color: var(--el-text-color-secondary, #94A0B2);
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
  color: var(--el-text-color-secondary, #94A0B2);
}

.qr-info {
  flex: 1;
  min-width: 0;
}

.qr-info h4 {
  margin: 0;
  font-size: 15px;
  font-weight: 600;
  color: var(--el-text-color-primary, #1F2937);
}

.qr-info p {
  margin: 6px 0 0;
  font-size: 12px;
  color: var(--el-text-color-secondary, #94A0B2);
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
  color: var(--el-text-color-secondary, #94A0B2);
}

.contact-item .el-icon {
  font-size: 16px;
  color: var(--el-color-primary, #165DFF);
}

/* Quick Links Card */
.quick-links-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 6px;
}

.quick-link-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  padding: 8px 6px;
  border-radius: 6px;
  border: 1px solid var(--el-border-color-lighter, #EEF1F6);
  text-decoration: none;
  color: var(--el-text-color-primary, #1F2937);
  transition: border-color 0.15s, color 0.15s;
}

.quick-link-item:hover {
  border-color: var(--el-color-primary, #165DFF);
  color: var(--el-color-primary, #165DFF);
}

.quick-link-icon {
  width: 22px;
  height: 22px;
  border-radius: 4px;
  background: var(--el-color-primary-light-9, #E8F1FF);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
  color: var(--el-color-primary, #165DFF);
  transition: background 0.15s;
}

.quick-link-item:hover .quick-link-icon {
  background: var(--el-color-primary, #165DFF);
  color: #fff;
}

.quick-link-item span {
  font-size: 10px;
  font-weight: 500;
}

.support-actions {
  display: flex;
  gap: 8px;
  padding-top: 12px;
  border-top: 1px solid var(--el-border-color-lighter, #EEF1F6);
}

/* Responsive */
@media (max-width: 1100px) {
  .row-top {
    grid-template-columns: 1fr 1fr;
  }

  .account-card {
    grid-column: 1 / -1;
  }

  .account-card .card-body {
    flex-direction: row;
    align-items: center;
  }

  .account-badges {
    margin-left: auto;
  }
}

@media (max-width: 900px) {
  .row-top {
    grid-template-columns: 1fr;
  }

  .row-mid,
  .row-bot {
    grid-template-columns: 1fr;
  }

  .product-grid {
    grid-template-columns: repeat(2, 1fr);
  }

  .finance-metrics {
    grid-template-columns: 1fr 1fr;
  }

  .finance-metric + .finance-metric::before {
    display: none;
  }

  .support-contacts {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 600px) {
  .dashboard-page {
    padding: 0;
    gap: 12px;
    --card-padding-x: 12px;
    --card-padding-y: 12px;
  }

  .dashboard-grid {
    gap: 12px;
  }

  .card-head {
    padding: var(--card-padding-y) var(--card-padding-x) 0;
  }

  .card-head h3 {
    font-size: 13px;
  }

  .card-head-icon {
    width: 24px;
    height: 24px;
    font-size: 12px;
  }

  .card-body {
    padding: var(--card-padding-y) var(--card-padding-x) 12px;
  }

  .page-header {
    flex-direction: column;
    align-items: flex-start;
  }

  .product-grid {
    grid-template-columns: repeat(2, 1fr);
  }

  .trend-row {
    grid-template-columns: 1fr 1fr;
    gap: 8px;
  }

  .finance-metrics {
    grid-template-columns: 1fr 1fr;
    gap: 8px;
  }

  .finance-metric + .finance-metric::before {
    display: none;
  }

  .finance-metric {
    padding: 12px 10px;
    background: var(--el-fill-color-light, #F5F7FA);
    border-radius: 8px;
    text-align: center;
  }

  .finance-metric .value {
    font-size: 18px;
  }

  .finance-metric .unit {
    font-size: 11px;
  }

  .trend-block .value {
    font-size: 20px;
  }

  .quick-links-grid {
    grid-template-columns: repeat(3, 1fr);
  }

  .account-card .card-body {
    flex-direction: column;
    align-items: flex-start;
    gap: 12px;
  }

  .account-badges {
    margin-left: 0;
    width: 100%;
  }

  .account-badges .chip {
    font-size: 10px;
    height: 20px;
    padding: 0 6px;
  }

  .notice-inner {
    flex-wrap: nowrap;
    gap: 8px;
  }

  .notice-badge {
    min-width: 36px;
    max-width: 60px;
    font-size: 10px;
    height: 20px;
    padding: 0 6px;
  }

  .notice-time {
    width: 55px;
    font-size: 11px;
  }

  .notice-title {
    font-size: 12px;
  }

  .qr-box {
    width: 72px;
    height: 72px;
  }

  .qr-info h4 {
    font-size: 13px;
  }

  .qr-info p {
    font-size: 11px;
  }

  .support-qr-row {
    padding: 10px;
  }

  .todo-item {
    padding: 8px 0;
  }

  .todo-icon {
    width: 26px;
    height: 26px;
    font-size: 12px;
  }

  .todo-label {
    font-size: 12px;
  }

  .finance-actions {
    flex-direction: column;
    gap: 10px;
  }

  .finance-actions .el-button {
    width: 100%;
  }

  .finance-links {
    justify-content: center;
  }

  .trend-footer {
    flex-direction: column;
    gap: 6px;
    text-align: center;
  }

  .right-stack {
    gap: 12px;
  }

  .support-contacts {
    flex-direction: column;
    align-items: flex-start;
    gap: 8px;
  }
}

@media (max-width: 480px) {
  .dashboard-page {
    padding: 0;
    gap: 10px;
    --card-padding-x: 12px;
    --card-padding-y: 10px;
  }

  .dashboard-grid {
    gap: 10px;
  }

  .card-head {
    padding: var(--card-padding-y) var(--card-padding-x) 0;
  }

  .card-body {
    padding: var(--card-padding-y) var(--card-padding-x) 12px;
  }

  .product-grid {
    grid-template-columns: repeat(2, 1fr);
  }

  .account-name {
    font-size: 16px;
  }

  .finance-metric .value {
    font-size: 20px;
  }

  .trend-block .value {
    font-size: 18px;
  }

  .quick-links-grid {
    grid-template-columns: repeat(2, 1fr);
  }

  .product-meta strong {
    font-size: 12px;
  }

  .product-item {
    padding: 10px 12px;
  }

  .product-icon {
    width: 32px;
    height: 32px;
    font-size: 15px;
  }

  .card-head .el-button {
    font-size: 11px;
    padding: 4px 0;
  }

  .support-qr-row {
    flex-direction: column;
    align-items: center;
    text-align: center;
  }

  .qr-info {
    width: 100%;
  }

  .notice-inner {
    gap: 6px;
  }

  .notice-badge {
    display: none;
  }
}
</style>
