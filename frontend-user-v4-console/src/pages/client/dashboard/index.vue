<template>
  <section class="client-dashboard">
    <t-loading :loading="loading" text="正在加载控制台数据">
      <div class="summary-grid">
        <t-card class="account-card dashboard-card" :bordered="false">
          <div class="account-card__user">
            <t-avatar :image="avatarUrl || undefined" size="large">{{ avatarText }}</t-avatar>
            <div class="account-card__main">
              <h2>{{ greetingText }}，{{ displayName }}</h2>
              <p>{{ todayDateText }}</p>
              <div class="account-card__tags">
                <t-tag :theme="isVerified ? 'success' : 'warning'" variant="light">{{ isVerified ? '已实名' : '未实名' }}</t-tag>
                <t-tag :theme="isPhoneBound ? 'success' : 'default'" variant="light">{{ isPhoneBound ? '已绑手机' : '未绑手机' }}</t-tag>
                <t-tag :theme="isEmailBound ? 'success' : 'default'" variant="light">{{ isEmailBound ? '已绑邮箱' : '未绑邮箱' }}</t-tag>
              </div>
            </div>
          </div>
          <div class="account-card__actions">
            <t-button theme="primary" @click="router.push('/client/recharge')">
              <template #icon><WalletIcon /></template>
              在线充值
            </t-button>
            <t-button variant="outline" @click="router.push('/client/balance-logs')">
              <template #icon><FileIcon /></template>
              财务明细
            </t-button>
          </div>
        </t-card>

        <t-card v-for="item in summaryCards" :key="item.key" class="metric-card dashboard-card" :bordered="false">
          <div class="metric-card__label">{{ item.label }}</div>
          <div class="metric-card__value" :class="{ 'is-warning': item.warning }">
            {{ item.value }}
            <span>{{ item.unit }}</span>
          </div>
          <div class="metric-card__note">{{ item.note }}</div>
        </t-card>
      </div>

      <div class="dashboard-grid">
        <main class="dashboard-main">
          <t-card class="dashboard-card" :bordered="false">
            <template #title>我的产品</template>
            <template #actions>
              <t-button theme="primary" variant="text" @click="router.push('/client/services')">管理全部</t-button>
            </template>

            <div class="product-grid">
              <button
                v-for="item in productCards"
                :key="item.key"
                type="button"
                class="product-tile"
                @click="handleProductCardClick(item)"
              >
                <span class="product-tile__icon" :class="item.tone">
                  <component :is="item.icon" />
                </span>
                <span class="product-tile__content">
                  <strong>{{ item.title }}</strong>
                  <span>{{ item.countText }}</span>
                </span>
              </button>
            </div>
          </t-card>

          <div class="chart-grid">
            <t-card class="dashboard-card chart-card" :bordered="false">
              <template #title>每月产品消费占比</template>
              <template #actions>
                <span class="card-meta">{{ monthLabel }}</span>
              </template>

              <div v-if="productSegments.length" class="donut-panel">
                <div class="donut-chart" :style="{ background: donutBackground }">
                  <div class="donut-chart__center">
                    <span>月总消费</span>
                    <strong>{{ formatMoney(totalConsumptionAmount) }}</strong>
                  </div>
                </div>
                <div class="donut-legend">
                  <div v-for="slice in productSegments" :key="slice.label" class="donut-legend__row">
                    <span class="donut-legend__dot" :style="{ background: slice.color }"></span>
                    <span class="donut-legend__name">{{ slice.label }}</span>
                    <span class="donut-legend__percent">{{ slice.percent }}%</span>
                    <span class="donut-legend__amount">¥{{ formatMoney(slice.amount) }}</span>
                  </div>
                </div>
              </div>
              <t-empty v-else description="暂无本月产品消费数据" />
            </t-card>

            <t-card class="dashboard-card chart-card" :bordered="false">
              <template #title>近30天每日消费</template>
              <template #actions>
                <t-button theme="primary" variant="text" @click="router.push('/client/balance-logs')">消费明细</t-button>
              </template>

              <div v-if="dailyBarsHasData" class="bar-chart" aria-label="近30天每日消费">
                <div v-for="bar in dailyBars" :key="bar.date" class="bar-chart__slot">
                  <span class="bar-chart__bar" :style="{ height: bar.height }"></span>
                  <span v-if="bar.showLabel" class="bar-chart__label">{{ bar.label }}</span>
                </div>
              </div>
              <t-empty v-else description="暂无消费记录" />
              <p class="chart-foot">本月累计消费 <strong>{{ formatMoney(financeSummary.invoice_payment_out) }} 元</strong></p>
            </t-card>
          </div>

          <t-card class="dashboard-card" :bordered="false">
            <template #title>消息中心</template>
            <template #actions>
              <t-button theme="primary" variant="text" @click="router.push(activeNoticeTab === 'notice' ? '/client/notices' : '/client/help')">
                查看全部
              </t-button>
            </template>

            <t-tabs v-model="activeNoticeTab" class="message-tabs">
              <t-tab-panel value="notice" label="新闻公告">
                <div v-if="recentNotices.length" class="message-list">
                  <router-link v-for="item in recentNotices" :key="item.id" class="message-row" :to="`/client/notices/${item.id}`">
                    <span class="message-row__dot"></span>
                    <span class="message-row__title">{{ item.title }}</span>
                    <span class="message-row__time">{{ formatDate(item.publish_at || item.created_at) }}</span>
                  </router-link>
                </div>
                <t-empty v-else description="暂无公告" />
              </t-tab-panel>
              <t-tab-panel value="help" label="帮助中心">
                <div v-if="recentHelpArticles.length" class="message-list">
                  <router-link v-for="item in recentHelpArticles" :key="item.id" class="message-row" :to="`/client/help/${item.id}`">
                    <span class="message-row__dot is-help"></span>
                    <span class="message-row__title">{{ item.title }}</span>
                    <span class="message-row__time">{{ formatDate(item.publish_at || item.created_at) }}</span>
                  </router-link>
                </div>
                <t-empty v-else description="暂无帮助内容" />
              </t-tab-panel>
            </t-tabs>
          </t-card>
        </main>

        <aside class="dashboard-aside">
          <t-card class="dashboard-card" :bordered="false">
            <template #title>待办事项</template>
            <div class="todo-list">
              <button v-for="item in todoItems" :key="item.key" type="button" class="todo-row" @click="router.push(item.path)">
                <span class="todo-row__left">
                  <span class="todo-row__icon" :class="item.tone"><component :is="item.icon" /></span>
                  <span>{{ item.label }}</span>
                </span>
                <span class="todo-row__count" :class="{ 'is-alert': item.count > 0 }">{{ item.count }}</span>
              </button>
            </div>
          </t-card>

          <t-card class="dashboard-card" :bordered="false">
            <template #title>快捷入口</template>
            <div class="quick-grid">
              <router-link v-for="item in quickLinks" :key="item.path" :to="item.path" class="quick-link">
                <component :is="item.icon" />
                <span>{{ item.label }}</span>
              </router-link>
            </div>
          </t-card>

          <t-card class="dashboard-card" :bordered="false">
            <template #title>{{ supportGroupTitle }}</template>
            <div class="support-box">
              <img v-if="supportQr" :src="supportQr" alt="QQ群二维码" class="support-box__qr" />
              <div v-else class="support-box__qr support-box__qr--empty">{{ siteBranding.brandInitials }}</div>
              <div class="support-box__content">
                <strong>{{ supportGroupText }}</strong>
                <span>群号：{{ supportPhoneText }}</span>
                <t-button
                  v-if="supportGroupLink"
                  theme="primary"
                  size="small"
                  :href="supportGroupLink"
                  target="_blank"
                  variant="outline"
                >
                  加入群聊
                </t-button>
              </div>
            </div>
          </t-card>
        </aside>
      </div>
    </t-loading>
  </section>
</template>

<script setup lang="ts">
import type { Component } from 'vue';
import { computed, onMounted, ref } from 'vue';
import { MessagePlugin } from 'tdesign-vue-next';
import {
  CouponIcon,
  DashboardIcon,
  FileIcon,
  GiftIcon,
  HelpCircleIcon,
  MoneyIcon,
  NotificationIcon,
  ServerIcon,
  ServiceIcon,
  UserSafetyIcon,
  WalletIcon,
} from 'tdesign-icons-vue-next';
import { useRouter } from 'vue-router';

import clientApi from '@/api/client';
import { useSiteBrandingStore } from '@/app/stores/siteBranding';
import { useNoticeReadStatus } from '@/domains/content/useNoticeReadStatus';
import { useUserStore } from '@/store';

type GenericRecord = Record<string, any>;

interface ProductCard {
  key: string;
  title: string;
  countText: string;
  count: number;
  path: string;
  primaryServiceId: number;
  icon: Component;
  tone: string;
}

const router = useRouter();
const userStore = useUserStore();
const siteBranding = useSiteBrandingStore();
const { unreadCount, fetchUnreadCount } = useNoticeReadStatus();

const loading = ref(false);
const activeNoticeTab = ref<'notice' | 'help'>('notice');
const balanceLogsDaily = ref<GenericRecord[]>([]);
const paidInvoices = ref<GenericRecord[]>([]);
const recentNotices = ref<GenericRecord[]>([]);
const recentHelpArticles = ref<GenericRecord[]>([]);
const recentTickets = ref<GenericRecord[]>([]);
const financeSummary = ref<GenericRecord>({
  balance: '0.00',
  total_out: '0.00',
  recharge_in: '0.00',
  invoice_payment_out: '0.00',
  unpaid_count: 0,
  unpaid_amount: '0.00',
  total_invoices: 0,
});
const couponSummary = ref<GenericRecord>({
  total: 0,
  available: 0,
  used_up: 0,
  expired: 0,
});
const serviceOverview = ref<GenericRecord>({
  total: 0,
  list: [],
});

const userInfo = computed<GenericRecord>(() => userStore.info || {});
const displayName = computed(() =>
  String(userInfo.value.nickname || userInfo.value.display_name || userInfo.value.email || userInfo.value.name || '客户账户'),
);
const avatarText = computed(() => displayName.value.slice(0, 1) || '客');
const avatarUrl = computed(() => String(userInfo.value.avatar || userInfo.value.avatar_url || userInfo.value.headimg || ''));
const userIdText = computed(() => String(userInfo.value.id || '--'));
const isVerified = computed(() => Number(userInfo.value.is_verified || 0) === 1);
const isPhoneBound = computed(() => Boolean(String(userInfo.value.phone || '').trim()));
const isEmailBound = computed(() => Boolean(String(userInfo.value.email || '').trim()));
const supportQr = computed(() => siteBranding.supportGroupQr || '');
const supportGroupLink = computed(() => siteBranding.supportGroupLink || '');
const supportPhoneText = computed(() => siteBranding.serviceQqGroup || '-');
const supportGroupTitle = computed(() => siteBranding.supportGroupTitle || '官方QQ群聊');
const supportGroupText = computed(() => siteBranding.supportGroupText || '加入官方QQ群聊');

const summaryCards = computed(() => [
  {
    key: 'balance',
    label: '账户余额',
    value: formatMoney(financeSummary.value.balance),
    unit: '元',
    note: `ID: ${userIdText.value}`,
  },
  {
    key: 'recharge',
    label: '本月充值',
    value: formatMoney(financeSummary.value.recharge_in),
    unit: '元',
    note: '财务入账汇总',
  },
  {
    key: 'payment',
    label: '本月消费',
    value: formatMoney(financeSummary.value.invoice_payment_out),
    unit: '元',
    note: '今日 0.00 元',
    warning: true,
  },
  {
    key: 'coupon',
    label: '可用优惠券',
    value: String(Number(couponSummary.value.available || 0)),
    unit: '张',
    note: `总计 ${Number(couponSummary.value.total || 0)} 张`,
  },
]);

function last30DaysRange(): string[] {
  const now = new Date();
  const start = new Date(now);
  start.setDate(start.getDate() - 29);
  return [toDateText(start), toDateText(now)];
}

function currentMonthRange(): string[] {
  const now = new Date();
  const start = new Date(now.getFullYear(), now.getMonth(), 1);
  const end = new Date(now.getFullYear(), now.getMonth() + 1, 0);
  return [toDateText(start), toDateText(end)];
}

function toDateText(value: Date) {
  const year = value.getFullYear();
  const month = String(value.getMonth() + 1).padStart(2, '0');
  const day = String(value.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
}

function toNumber(value: unknown) {
  const normalized = Number(value);
  return Number.isFinite(normalized) ? normalized : 0;
}

function formatMoney(value: unknown) {
  return toNumber(value).toFixed(2);
}

function resolveList(payload: any) {
  if (Array.isArray(payload?.list)) return payload.list;
  if (Array.isArray(payload?.items)) return payload.items;
  if (Array.isArray(payload)) return payload;
  return [];
}

function formatDate(dateStr: string | null | undefined): string {
  if (!dateStr) return '--';
  const date = new Date(dateStr);
  if (Number.isNaN(date.getTime())) return '--';
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  const hours = String(date.getHours()).padStart(2, '0');
  const minutes = String(date.getMinutes()).padStart(2, '0');
  return `${month}-${day} ${hours}:${minutes}`;
}

const monthLabel = computed(() => {
  const now = new Date();
  return `${now.getFullYear()}年${now.getMonth() + 1}月`;
});

const greetingText = computed(() => {
  const hour = new Date().getHours();
  if (hour < 6) return '夜深了';
  if (hour < 9) return '早上好';
  if (hour < 12) return '上午好';
  if (hour < 14) return '中午好';
  if (hour < 18) return '下午好';
  if (hour < 22) return '晚上好';
  return '夜深了';
});

const todayDateText = computed(() => {
  const now = new Date();
  const weekDays = ['日', '一', '二', '三', '四', '五', '六'];
  return `${now.getFullYear()}年${now.getMonth() + 1}月${now.getDate()}日 星期${weekDays[now.getDay()]}`;
});

function resolveProductMeta(item: GenericRecord, key: string) {
  const title = String(item.title || item.name || item.product_type_label || '').toLowerCase();
  if (key === 'vps' || title.includes('云服务器')) return { icon: ServerIcon, tone: 'is-brand' };
  if (key === 'dedicated' || title.includes('物理机') || title.includes('裸金属')) return { icon: DashboardIcon, tone: 'is-success' };
  if (key === 'hosting' || title.includes('虚拟主机')) return { icon: ServiceIcon, tone: 'is-warning' };
  if (key === 'domain' || title.includes('域名')) return { icon: HelpCircleIcon, tone: 'is-info' };
  if (title.includes('数据库') || title.includes('对象存储')) return { icon: FileIcon, tone: 'is-success' };
  return { icon: ServerIcon, tone: 'is-muted' };
}

const productCards = computed<ProductCard[]>(() => {
  const overviewList = Array.isArray(serviceOverview.value.list) ? serviceOverview.value.list : [];

  if (overviewList.length) {
    return overviewList.slice(0, 6).map((item: GenericRecord, index: number) => {
      const key = String(item.product_type || item.key || `product-${index}`);
      const meta = resolveProductMeta(item, key);
      return {
        key,
        title: item.title || item.name || item.product_type_label || '云产品',
        countText: `${Number(item.active_count || 0)} 个`,
        count: Number(item.active_count || 0),
        path: '/client/services',
        primaryServiceId: Number(item.primary_service_id || 0),
        icon: meta.icon,
        tone: meta.tone,
      };
    });
  }

  return [
    { key: 'cloud', title: '云服务器', countText: '0 个', count: 0, path: '/client/services', primaryServiceId: 0, icon: ServerIcon, tone: 'is-brand' },
    { key: 'storage', title: '对象存储', countText: '0 个', count: 0, path: '/client/services', primaryServiceId: 0, icon: FileIcon, tone: 'is-success' },
    { key: 'database', title: '云数据库', countText: '0 个', count: 0, path: '/client/services', primaryServiceId: 0, icon: DashboardIcon, tone: 'is-info' },
    { key: 'cdn', title: 'CDN 加速', countText: '0 个', count: 0, path: '/client/services', primaryServiceId: 0, icon: ServiceIcon, tone: 'is-warning' },
    { key: 'domain', title: '域名注册', countText: '0 个', count: 0, path: '/client/services', primaryServiceId: 0, icon: HelpCircleIcon, tone: 'is-muted' },
    { key: 'ssl', title: 'SSL 证书', countText: '0 个', count: 0, path: '/client/services', primaryServiceId: 0, icon: UserSafetyIcon, tone: 'is-success' },
  ];
});

function handleProductCardClick(item: ProductCard) {
  if (Number(item.primaryServiceId || 0) > 0) {
    router.push(`/client/services/${item.primaryServiceId}`);
    return;
  }
  router.push(item.path || '/client/services');
}

const dailyExpenseData = computed(() => {
  const days: { date: string; amount: number }[] = [];
  const now = new Date();
  for (let i = 29; i >= 0; i -= 1) {
    const d = new Date(now);
    d.setDate(d.getDate() - i);
    days.push({ date: toDateText(d), amount: 0 });
  }

  for (const entry of balanceLogsDaily.value) {
    const change = toNumber(entry.change_amount);
    if (change >= 0) continue;
    const dateStr = String(entry.created_at ?? '').slice(0, 10);
    const day = days.find((item) => item.date === dateStr);
    if (day) day.amount = Math.round((day.amount + Math.abs(change)) * 100) / 100;
  }

  return days;
});

const dailyBars = computed(() => {
  const amounts = dailyExpenseData.value.map((item) => item.amount);
  const max = Math.max(...amounts, 1);
  return dailyExpenseData.value.map((item, index) => ({
    ...item,
    height: item.amount > 0 ? `${Math.max(8, Math.round((item.amount / max) * 100))}%` : '0%',
    showLabel: index % 7 === 0 || index === dailyExpenseData.value.length - 1,
    label: `${Number(item.date.slice(5, 7))}/${Number(item.date.slice(8, 10))}`,
  }));
});

const dailyBarsHasData = computed(() => dailyExpenseData.value.some((item) => item.amount > 0));

const productConsumptionData = computed(() => {
  const now = new Date();
  const prefix = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
  const map: Record<string, { label: string; amount: number; count: number }> = {};

  for (const invoice of paidInvoices.value) {
    const date = String(invoice.paid_at || invoice.created_at || '');
    if (!date.startsWith(prefix)) continue;

    const name = String(invoice.product_display_name || invoice.combined_display_name || '其他服务');
    const amount = toNumber(invoice.paid_amount || invoice.amount);
    if (!map[name]) {
      map[name] = { label: name, amount: 0, count: 0 };
    }
    map[name].amount += amount;
    map[name].count += 1;
  }

  const list = Object.values(map).sort((a, b) => b.amount - a.amount);
  if (list.length <= 5) return list;

  const top = list.slice(0, 4);
  const otherAmount = list.slice(4).reduce((sum, item) => sum + item.amount, 0);
  const otherCount = list.slice(4).reduce((sum, item) => sum + item.count, 0);
  if (otherAmount > 0) top.push({ label: '其他', amount: Math.round(otherAmount * 100) / 100, count: otherCount });
  return top;
});

const totalConsumptionAmount = computed(() => productConsumptionData.value.reduce((sum, item) => sum + item.amount, 0));

const segmentColors = [
  'var(--td-brand-color)',
  'var(--td-success-color)',
  'var(--td-warning-color)',
  'var(--td-error-color)',
  'var(--td-brand-color-light)',
];

const productSegments = computed(() => {
  const total = totalConsumptionAmount.value;
  let cursor = 0;
  if (total <= 0) return [];

  return productConsumptionData.value.map((item, index) => {
    const percentValue = item.amount / total;
    const percent = Math.round(percentValue * 100);
    const start = cursor;
    const end = Math.min(100, cursor + percentValue * 100);
    cursor = end;
    return {
      ...item,
      percent,
      color: segmentColors[index % segmentColors.length],
      start,
      end,
    };
  });
});

const donutBackground = computed(() => {
  if (!productSegments.value.length) return 'var(--td-bg-color-component)';
  const parts = productSegments.value.map((item) => `${item.color} ${item.start}% ${item.end}%`);
  return `conic-gradient(${parts.join(', ')})`;
});

const expiringServiceCount = computed(() => {
  const list = Array.isArray(serviceOverview.value.list) ? serviceOverview.value.list : [];
  return list.reduce((sum: number, item: GenericRecord) => sum + Number(item.expiring_count || 0), 0);
});

const openTicketCount = computed(() => recentTickets.value.filter((item) => Number(item.status) !== 3).length);

const todoItems = computed(() => [
  {
    key: 'invoice',
    label: '待支付账单',
    count: Number(financeSummary.value.unpaid_count || 0),
    path: '/client/invoices',
    icon: FileIcon,
    tone: 'is-brand',
  },
  {
    key: 'ticket',
    label: '待处理工单',
    count: openTicketCount.value,
    path: '/client/tickets',
    icon: ServiceIcon,
    tone: 'is-warning',
  },
  {
    key: 'renew',
    label: '即将到期服务',
    count: expiringServiceCount.value,
    path: '/client/services',
    icon: ServerIcon,
    tone: 'is-success',
  },
  {
    key: 'notice',
    label: '未查看公告',
    count: unreadCount.value,
    path: '/client/notices',
    icon: NotificationIcon,
    tone: 'is-muted',
  },
]);

const quickLinks = [
  { label: '我的服务', path: '/client/services', icon: ServerIcon },
  { label: '账单记录', path: '/client/invoices', icon: FileIcon },
  { label: '余额流水', path: '/client/balance-logs', icon: MoneyIcon },
  { label: '工单支持', path: '/client/tickets', icon: ServiceIcon },
  { label: '账户充值', path: '/client/recharge', icon: WalletIcon },
  { label: '帮助中心', path: '/client/help', icon: HelpCircleIcon },
  { label: '优惠券', path: '/client/coupons', icon: CouponIcon },
  { label: '推荐奖励', path: '/client/referral', icon: GiftIcon },
];

async function loadDashboard() {
  loading.value = true;

  try {
    await userStore.getUserInfo().catch(() => {});

    if (!siteBranding.siteName) {
      await siteBranding.fetchSiteConfig();
    } else {
      void siteBranding.fetchSiteConfig();
    }

    const monthRange = currentMonthRange();
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
    ]);

    if (noticesRes.status === 'fulfilled') recentNotices.value = resolveList((noticesRes.value as any).data);
    if (helpArticlesRes.status === 'fulfilled') recentHelpArticles.value = resolveList((helpArticlesRes.value as any).data);
    if (servicesRes.status === 'fulfilled') resolveList((servicesRes.value as any).data);
    if (invoicesRes.status === 'fulfilled') resolveList((invoicesRes.value as any).data);
    if (ticketsRes.status === 'fulfilled') recentTickets.value = resolveList((ticketsRes.value as any).data);
    if (financeRes.status === 'fulfilled') {
      financeSummary.value = {
        ...financeSummary.value,
        ...((financeRes.value as any).data || {}),
      };
    }
    if (couponsRes.status === 'fulfilled') {
      couponSummary.value = {
        ...couponSummary.value,
        ...((couponsRes.value as any).data || {}),
      };
    }
    if (serviceOverviewRes.status === 'fulfilled') {
      serviceOverview.value = (serviceOverviewRes.value as any).data || { total: 0, list: [] };
    }
    if (referralRes.status === 'fulfilled') {
      const data = (referralRes.value as any).data || {};
      if (Number(data.available_coupons || 0) > Number(couponSummary.value.available || 0)) {
        couponSummary.value.available = data.available_coupons;
      }
    }
    if (balanceLogsDailyRes.status === 'fulfilled') {
      balanceLogsDaily.value = resolveList((balanceLogsDailyRes.value as any).data);
    }
    if (paidInvoicesRes.status === 'fulfilled') {
      paidInvoices.value = resolveList((paidInvoicesRes.value as any).data);
    }

    await fetchUnreadCount(true);
  } catch (error: any) {
    if (!error?.__handled) {
      MessagePlugin.error(error?.message || '控制台数据加载失败');
    }
  } finally {
    loading.value = false;
  }
}

onMounted(() => {
  void loadDashboard();
});
</script>

<style scoped lang="less">
.client-dashboard {
  padding: var(--td-comp-paddingTB-l) var(--td-comp-paddingLR-l);
}

.summary-grid {
  display: grid;
  grid-template-columns: minmax(22rem, 2fr) repeat(4, minmax(10rem, 1fr));
  gap: var(--td-comp-margin-m);
}

.dashboard-grid {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(18rem, 24%);
  gap: var(--td-comp-margin-m);
  margin-top: var(--td-comp-margin-m);
}

.dashboard-main,
.dashboard-aside {
  display: flex;
  flex-direction: column;
  gap: var(--td-comp-margin-m);
  min-width: 0;
}

.chart-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: var(--td-comp-margin-m);
}

.dashboard-card {
  height: 100%;
  background: var(--td-bg-color-container);
  border: thin solid var(--td-border-color);
  border-radius: var(--td-radius-medium);
  box-shadow: var(--td-shadow-1);
}

.account-card__user {
  display: flex;
  gap: var(--td-comp-margin-m);
  align-items: flex-start;
}

.account-card__main {
  min-width: 0;

  h2 {
    margin: 0;
    overflow: hidden;
    color: var(--td-text-color-primary);
    font: var(--td-font-title-medium);
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  p {
    margin: var(--td-comp-margin-xs) 0 0;
    color: var(--td-text-color-secondary);
    font: var(--td-font-body-medium);
  }
}

.account-card__tags,
.account-card__actions {
  display: flex;
  flex-wrap: wrap;
  gap: var(--td-comp-margin-s);
  margin-top: var(--td-comp-margin-m);
}

.metric-card__label,
.metric-card__note,
.card-meta,
.chart-foot {
  color: var(--td-text-color-secondary);
  font: var(--td-font-body-small);
}

.metric-card__value {
  margin-top: var(--td-comp-margin-xs);
  color: var(--td-text-color-primary);
  font: var(--td-font-headline-medium);

  span {
    margin-left: var(--td-comp-margin-xs);
    color: var(--td-text-color-secondary);
    font: var(--td-font-body-medium);
  }

  &.is-warning {
    color: var(--td-warning-color);
  }
}

.metric-card__note {
  margin-top: var(--td-comp-margin-xs);
}

.product-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: var(--td-comp-margin-s);
}

.product-tile,
.todo-row,
.quick-link {
  border: thin solid var(--td-border-color);
  background: var(--td-bg-color-container);
  color: var(--td-text-color-primary);
  cursor: pointer;
  transition:
    border-color var(--td-anim-duration-base) var(--td-anim-time-fn-easing),
    background-color var(--td-anim-duration-base) var(--td-anim-time-fn-easing),
    color var(--td-anim-duration-base) var(--td-anim-time-fn-easing);
}

.product-tile {
  display: flex;
  gap: var(--td-comp-margin-s);
  align-items: center;
  min-width: 0;
  padding: var(--td-comp-paddingTB-m) var(--td-comp-paddingLR-m);
  text-align: left;
  border-radius: var(--td-radius-medium);

  &:hover {
    border-color: var(--td-brand-color);
    background: var(--td-brand-color-light);
  }
}

.product-tile__icon,
.todo-row__icon,
.quick-link svg {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  color: var(--td-text-color-secondary);
}

.product-tile__icon,
.todo-row__icon {
  width: 2.5rem;
  height: 2.5rem;
  border-radius: var(--td-radius-medium);
  color: var(--td-text-color-secondary);
  background: var(--td-bg-color-component);
}

.product-tile__content {
  display: flex;
  flex: 1;
  flex-direction: column;
  gap: var(--td-comp-margin-xxs);
  min-width: 0;

  strong,
  span {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  strong {
    color: var(--td-text-color-primary);
    font: var(--td-font-body-medium);
  }

  span {
    color: var(--td-text-color-secondary);
    font: var(--td-font-body-small);
  }
}

.chart-card {
  min-height: 23rem;
}

.donut-panel {
  display: grid;
  grid-template-columns: minmax(10rem, 38%) minmax(0, 1fr);
  gap: var(--td-comp-margin-l);
  align-items: center;
}

.donut-chart {
  position: relative;
  aspect-ratio: 1;
  width: min(100%, 14rem);
  margin: 0 auto;
  border-radius: 50%;

  &::after {
    position: absolute;
    inset: 18%;
    content: '';
    background: var(--td-bg-color-container);
    border-radius: 50%;
  }
}

.donut-chart__center {
  position: absolute;
  inset: 28%;
  z-index: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  color: var(--td-text-color-secondary);
  font: var(--td-font-body-small);
  text-align: center;

  strong {
    margin-top: var(--td-comp-margin-xxs);
    color: var(--td-text-color-primary);
    font: var(--td-font-title-medium);
  }
}

.donut-legend {
  display: flex;
  flex-direction: column;
  gap: var(--td-comp-margin-s);
  min-width: 0;
}

.donut-legend__row,
.message-row,
.todo-row {
  display: flex;
  align-items: center;
}

.donut-legend__row {
  gap: var(--td-comp-margin-s);
  color: var(--td-text-color-primary);
  font: var(--td-font-body-small);
}

.donut-legend__dot,
.message-row__dot {
  flex: 0 0 auto;
  border-radius: 50%;
}

.donut-legend__dot {
  width: var(--td-comp-size-xxxs);
  height: var(--td-comp-size-xxxs);
}

.donut-legend__name {
  flex: 1;
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.donut-legend__percent,
.donut-legend__amount {
  color: var(--td-text-color-secondary);
}

.bar-chart {
  display: flex;
  align-items: flex-end;
  height: 12rem;
  gap: var(--td-comp-margin-xxs);
  padding: var(--td-comp-paddingTB-s) 0 var(--td-comp-paddingTB-xl);
  border-bottom: thin solid var(--td-border-color);
}

.bar-chart__slot {
  position: relative;
  display: flex;
  flex: 1;
  align-items: flex-end;
  justify-content: center;
  height: 100%;
  min-width: 0;
}

.bar-chart__bar {
  width: 68%;
  min-height: 0;
  background: var(--td-brand-color);
  border-radius: var(--td-radius-small) var(--td-radius-small) 0 0;
}

.bar-chart__label {
  position: absolute;
  bottom: calc(var(--td-comp-margin-l) * -1);
  color: var(--td-text-color-placeholder);
  font: var(--td-font-body-small);
}

.chart-foot {
  margin: var(--td-comp-margin-s) 0 0;

  strong {
    color: var(--td-text-color-primary);
  }
}

.message-tabs {
  margin-top: calc(var(--td-comp-margin-s) * -1);
}

.message-list,
.todo-list {
  display: flex;
  flex-direction: column;
}

.message-row {
  gap: var(--td-comp-margin-s);
  min-height: var(--td-comp-size-xxl);
  color: var(--td-text-color-primary);
  text-decoration: none;
  border-bottom: thin solid var(--td-border-color);

  &:hover {
    color: var(--td-brand-color);
  }
}

.message-row__dot {
  width: var(--td-comp-size-xxxs);
  height: var(--td-comp-size-xxxs);
  background: var(--td-brand-color);

  &.is-help {
    background: var(--td-success-color);
  }
}

.message-row__title {
  flex: 1;
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.message-row__time {
  color: var(--td-text-color-secondary);
  font: var(--td-font-body-small);
}

.todo-row {
  justify-content: space-between;
  width: 100%;
  padding: var(--td-comp-paddingTB-s) 0;
  border-width: 0 0 thin;
  text-align: left;

  &:hover {
    color: var(--td-brand-color);
  }
}

.todo-row__left {
  display: flex;
  gap: var(--td-comp-margin-s);
  align-items: center;
  min-width: 0;
}

.todo-row__count {
  color: var(--td-text-color-secondary);
  font: var(--td-font-title-small);

  &.is-alert {
    color: var(--td-error-color);
  }
}

.quick-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: var(--td-comp-margin-s);
}

.quick-link {
  display: flex;
  flex-direction: column;
  gap: var(--td-comp-margin-xs);
  align-items: center;
  justify-content: center;
  min-height: 5rem;
  padding: var(--td-comp-paddingTB-m) var(--td-comp-paddingLR-s);
  text-align: center;
  text-decoration: none;
  border-radius: var(--td-radius-medium);

  &:hover {
    border-color: var(--td-brand-color);
    background: var(--td-brand-color-light);
  }
}

.support-box {
  display: grid;
  grid-template-columns: minmax(6rem, 32%) minmax(0, 1fr);
  gap: var(--td-comp-margin-m);
  align-items: center;
}

.support-box__qr {
  aspect-ratio: 1;
  width: 100%;
  object-fit: cover;
  border: thin solid var(--td-border-color);
  border-radius: var(--td-radius-medium);
}

.support-box__qr--empty {
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--td-text-color-secondary);
  background: var(--td-bg-color-component);
}

.support-box__content {
  display: flex;
  flex-direction: column;
  gap: var(--td-comp-margin-s);
  min-width: 0;

  strong {
    color: var(--td-text-color-primary);
    font: var(--td-font-body-medium);
  }

  span {
    color: var(--td-text-color-secondary);
    font: var(--td-font-body-small);
  }
}

@media (max-width: 73.75rem) {
  .summary-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .account-card {
    grid-column: 1 / -1;
  }

  .dashboard-grid {
    grid-template-columns: 1fr;
  }

  .dashboard-aside {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    align-items: stretch;
  }
}

@media (max-width: 56.25rem) {
  .chart-grid,
  .dashboard-aside {
    grid-template-columns: 1fr;
  }

  // 我的产品在窄屏下保持两列（6 个产品 → 3 行 2 列）
  .product-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .donut-panel {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 40rem) {
  .client-dashboard {
    padding: var(--td-comp-paddingTB-m) var(--td-comp-paddingLR-s);
  }

  // 数据卡保持 2 列，账户卡跨整行，避免单列堆叠浪费竖向空间
  .summary-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: var(--td-comp-margin-s);
  }

  .dashboard-grid {
    margin-top: var(--td-comp-margin-s);
  }

  .dashboard-main,
  .dashboard-aside {
    gap: var(--td-comp-margin-s);
  }

  // 小屏收紧账户卡：头像与信息间距缩小，标题降一级
  .account-card__user {
    gap: var(--td-comp-margin-s);
  }

  .account-card__main h2 {
    font: var(--td-font-title-small);
  }

  .account-card__tags,
  .account-card__actions {
    margin-top: var(--td-comp-margin-s);
  }

  .account-card__actions {
    flex-direction: column;

    // 纵向排列时按钮等宽撑满，并清除 TDesign 相邻按钮默认左边距，避免错位
    :deep(.t-button) {
      width: 100%;
      margin-left: 0;
    }
  }

  // 数据卡数字降一级，防止 2 列下大额数字溢出
  .metric-card__value {
    font: var(--td-font-title-large);
  }

  // 快捷入口改为紧凑 4 列图标网格，避免长列表
  .quick-grid {
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: var(--td-comp-margin-xs);
  }

  .quick-link {
    min-height: 4.25rem;
    padding: var(--td-comp-paddingTB-s) var(--td-comp-paddingLR-xs);

    span {
      overflow: hidden;
      font: var(--td-font-body-small);
      text-overflow: ellipsis;
      white-space: nowrap;
      max-width: 100%;
    }
  }

  .message-row {
    align-items: flex-start;
    flex-direction: column;
    gap: var(--td-comp-margin-xxs);
    padding: var(--td-comp-paddingTB-s) 0;
  }
}

// 极窄屏（≤480px）快捷入口降为 3 列，标签更宽松
@media (max-width: 30rem) {
  .quick-grid {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
}
</style>
