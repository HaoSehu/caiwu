<template>
  <section class="client-dashboard">
    <!-- 页面标题只保留在无障碍树里：视觉上首屏已有问候语与账户卡说明当前位置，不再单独占一行 -->
    <h1 class="sr-only">控制台</h1>
    <data-state v-if="loadError" error error-text="控制台数据加载失败，请重试" @retry="loadDashboard" />
    <template v-else>
      <div class="summary-grid">
        <t-card class="account-card dashboard-card" :bordered="false">
          <div v-if="!profileReady" class="account-card__user" aria-busy="true">
            <t-skeleton theme="avatar" />
            <div class="account-card__main">
              <t-skeleton
                :row-col="[
                  { width: '9rem', height: '1.5rem' },
                  { width: '12rem', height: '1rem' },
                ]"
              />
            </div>
          </div>
          <div v-else class="account-card__user">
            <t-avatar size="large">{{ avatarText }}</t-avatar>
            <div class="account-card__main">
              <h2>{{ greetingText }}，{{ displayName }}</h2>
              <p>{{ todayDateText }}</p>
              <div class="account-card__tags">
                <t-tag :theme="isVerified ? 'success' : 'warning'" variant="light">{{
                  isVerified ? '已实名' : '未实名'
                }}</t-tag>
                <t-tag :theme="isPhoneBound ? 'success' : 'default'" variant="light">{{
                  isPhoneBound ? '已绑手机' : '未绑手机'
                }}</t-tag>
                <t-tag :theme="isEmailBound ? 'success' : 'default'" variant="light">{{
                  isEmailBound ? '已绑邮箱' : '未绑邮箱'
                }}</t-tag>
              </div>
            </div>
          </div>
          <div class="account-card__actions">
            <t-button theme="primary" @click="router.push('/client/recharge')">
              <template #icon><wallet-icon /></template>
              在线充值
            </t-button>
            <t-button variant="outline" @click="router.push('/client/payments')">
              <template #icon><file-icon /></template>
              充值记录
            </t-button>
          </div>
        </t-card>

        <!--
          D-01：这张告警是 .summary-grid 的直接子元素，必须自己跨满一整行。
          账户卡占第一列、4 张指标卡各占一列，告警只占一格会把后面全部挤到下一行错位。
        -->
        <t-alert
          v-if="sectionFailed.summary"
          class="dashboard-section-alert"
          theme="error"
          message="账户概览数据部分加载失败，失败项显示为 --，可重试"
        >
          <template #operation>
            <t-button variant="text" theme="primary" size="small" @click="loadDashboard">重试</t-button>
          </template>
        </t-alert>

        <t-card
          v-for="item in summaryCards"
          :key="item.key"
          class="metric-card dashboard-card"
          :bordered="false"
          role="region"
          :aria-label="`${item.label}：${item.value}，${item.note}`"
        >
          <div class="metric-card__label">{{ item.label }}</div>
          <!--
            D-07：骨架行高按真实排版取值（标题 36 行高、说明 20 行高），行间距在样式表里压到 4，
            与 .metric-card__note 的 margin-top 同值。原先写 1.75rem / 1rem 且沿用 TDesign 默认的
            16 行间距，与真实排版没有对应关系，数据到达后卡片内部会整体上跳。
          -->
          <t-skeleton
            v-if="!item.ready && !item.failed"
            class="metric-card__skeleton"
            :row-col="[
              { width: '60%', height: 'var(--td-line-height-headline-medium)' },
              { width: '85%', height: 'var(--td-line-height-body-small)' },
            ]"
          />
          <!--
            D-01：ready 与 failed 必须逐卡判定。原先 4 张卡共用 summaryReady（= 账务或优惠券任一成功），
            账务接口失败而优惠券成功时，余额/充值/消费三张卡会把 0.00 当成真实数据渲染出来。
          -->
          <template v-else>
            <div class="metric-card__value" :class="{ 'is-warning': item.warning }">
              {{ item.failed ? '--' : item.value }}
            </div>
            <div class="metric-card__note">{{ item.failed ? '加载失败' : item.note }}</div>
          </template>
        </t-card>
      </div>

      <div class="dashboard-grid">
        <main class="dashboard-main">
          <t-card class="dashboard-card" :bordered="false">
            <template #title>我的产品</template>
            <template #actions>
              <t-button theme="primary" variant="text" @click="router.push('/client/services')">管理全部</t-button>
            </template>

            <t-alert
              v-if="sectionFailed.products"
              class="dashboard-section-alert"
              theme="error"
              message="我的产品加载失败，请稍后再试"
            >
              <template #operation>
                <t-button variant="text" theme="primary" size="small" @click="loadDashboard">重试</t-button>
              </template>
            </t-alert>
            <div v-if="!productsReady && !sectionFailed.products" class="product-grid" aria-busy="true">
              <t-skeleton
                v-for="placeholder in 6"
                :key="placeholder"
                class="product-tile-skeleton"
                :row-col="[
                  { width: '60%', height: 'var(--td-line-height-body-medium)' },
                  { width: '45%', height: 'var(--td-line-height-body-small)' },
                ]"
              />
            </div>
            <!-- D-13：没有已开通服务时不再渲染 6 张与真实卡片同款的占位卡
                 （用户会读成"我真的有这 6 个产品，只是 0 个"）。空态走 t-empty + 去产品目录。 -->
            <t-empty v-else-if="productsReady && !productCards.length" description="还没有已开通的产品">
              <template #action>
                <t-button theme="primary" variant="outline" @click="router.push('/client/catalog')">
                  去产品目录选购
                </t-button>
              </template>
            </t-empty>
            <div v-else-if="productsReady" class="product-grid">
              <router-link
                v-for="item in productCards"
                :key="item.key"
                class="product-tile"
                :to="`/client/services?catalog_type=${item.key}`"
              >
                <span class="product-tile__icon" :class="item.tone">
                  <component :is="item.icon" />
                </span>
                <span class="product-tile__content">
                  <strong>{{ item.title }}</strong>
                  <span>{{ item.countText }}</span>
                </span>
              </router-link>
            </div>
          </t-card>

          <div class="chart-grid">
            <t-card class="dashboard-card chart-card" :bordered="false">
              <template #title>今年消费</template>
              <template #actions>
                <div class="chart-card__actions">
                  <span class="card-meta">{{ currentYearLabel }}</span>
                  <span class="chart-summary">
                    合计 <strong>¥{{ formatMoney(currentYearConsumptionTotal) }}</strong>
                  </span>
                </div>
              </template>

              <t-loading :loading="!invoicesLoaded" text="加载中…">
                <!-- D-08：失败态统一交给 t-empty（原先自绘 .chart-load-failed 与页内 4 处 t-empty 并存，
                     同一个页面两种「没数据」的语言）。 -->
                <t-empty v-if="invoicesLoadFailed" size="small" type="fail" description="图表数据加载失败">
                  <template #action>
                    <t-button
                      theme="primary"
                      variant="outline"
                      size="small"
                      @click="() => loadConsumptionCharts('invoices', true)"
                    >
                      重试
                    </t-button>
                  </template>
                </t-empty>
                <div v-else-if="monthlyBarsHasData" class="bar-chart bar-chart--monthly" aria-label="今年月度消费">
                  <t-tooltip
                    v-for="(bar, index) in monthlyBars"
                    :key="bar.key"
                    :content="bar.tooltip"
                    placement="top"
                    show-arrow
                  >
                    <div
                      class="bar-chart__slot"
                      tabindex="0"
                      role="img"
                      :aria-label="`${bar.label}：${bar.amountLabel ? `¥${bar.amountLabel}` : '无消费'}`"
                    >
                      <div class="bar-chart__col" :style="{ height: bar.height }">
                        <span v-if="bar.amountLabel" class="bar-chart__value" :class="{ 'is-peak': bar.isPeak }">
                          ¥{{ bar.amountLabel }}
                        </span>
                        <span class="bar-chart__bar"></span>
                      </div>
                      <!-- D-04 同类：12 根月度柱在窄屏上每柱不足 1.9rem，「10月」并与相邻柱压字，窄屏隔一个显示。 -->
                      <span class="bar-chart__label" :class="{ 'bar-chart__label--wide-only': index % 2 === 1 }">{{
                        bar.label
                      }}</span>
                    </div>
                  </t-tooltip>
                </div>
                <t-empty v-else description="暂无今年消费数据" />
              </t-loading>
            </t-card>

            <t-card class="dashboard-card chart-card" :bordered="false">
              <template #title>近 7 天每日消费</template>
              <template #actions>
                <div class="chart-card__actions">
                  <span class="chart-summary">
                    合计 <strong>¥{{ formatMoney(last7DaysConsumptionTotal) }}</strong>
                  </span>
                  <t-button theme="primary" variant="text" @click="router.push('/client/payments')">消费明细</t-button>
                </div>
              </template>

              <t-loading :loading="!balanceLogsLoaded" text="加载中…">
                <t-empty v-if="balanceLogsLoadFailed" size="small" type="fail" description="图表数据加载失败">
                  <template #action>
                    <t-button
                      theme="primary"
                      variant="outline"
                      size="small"
                      @click="() => loadConsumptionCharts('balance', true)"
                    >
                      重试
                    </t-button>
                  </template>
                </t-empty>
                <div v-else-if="dailyBarsHasData" class="bar-chart bar-chart--daily" aria-label="近 7 天每日消费">
                  <t-tooltip
                    v-for="(bar, index) in dailyBars"
                    :key="bar.date"
                    :content="bar.tooltip"
                    placement="top"
                    show-arrow
                  >
                    <div
                      class="bar-chart__slot"
                      tabindex="0"
                      role="img"
                      :aria-label="`${bar.label} · 消费 ¥${formatMoney(bar.amount)}`"
                    >
                      <span class="bar-chart__bar" :style="{ height: bar.height }"></span>
                      <!-- D-04：窄屏每柱标签只有约 2.6rem 可用宽度，「10/29」需要 2rem，7 个并排必然互相压字；
                           桌面上 7 个都显示，窄屏只保留隔一个的标签，被隐藏的日期仍可从 tooltip 与 aria-label 读到。 -->
                      <span class="bar-chart__label" :class="{ 'bar-chart__label--wide-only': index % 2 === 1 }">
                        {{ bar.label }}
                      </span>
                    </div>
                  </t-tooltip>
                </div>
                <t-empty v-else description="暂无消费记录" />
              </t-loading>
            </t-card>
          </div>

          <t-card class="dashboard-card" :bordered="false">
            <template #title>消息中心</template>
            <template #actions>
              <t-button
                theme="primary"
                variant="text"
                @click="router.push(activeNoticeTab === 'notice' ? '/client/notices' : '/client/help')"
              >
                查看全部
              </t-button>
            </template>

            <t-alert
              v-if="sectionFailed.messages"
              class="dashboard-section-alert"
              theme="error"
              message="公告与帮助加载失败，请稍后再试"
            >
              <template #operation>
                <t-button variant="text" theme="primary" size="small" @click="loadDashboard">重试</t-button>
              </template>
            </t-alert>
            <div v-if="!messagesReady && !sectionFailed.messages" class="message-list" aria-busy="true">
              <t-skeleton
                v-for="placeholder in 3"
                :key="placeholder"
                :row-col="[{ width: '70%', height: 'var(--td-line-height-body-medium)' }]"
              />
            </div>
            <t-tabs v-else-if="messagesReady" v-model="activeNoticeTab" class="message-tabs">
              <t-tab-panel value="notice" label="新闻公告">
                <div v-if="recentNotices.length" class="message-list">
                  <router-link
                    v-for="item in recentNotices"
                    :key="item.id"
                    class="message-row"
                    :to="`/client/notices/${item.id}`"
                  >
                    <span class="message-row__title">{{ item.title }}</span>
                    <span class="message-row__time">{{ formatDate(item.publish_at || item.created_at) }}</span>
                  </router-link>
                </div>
                <t-empty v-else description="暂无公告" />
              </t-tab-panel>
              <t-tab-panel value="help" label="帮助中心">
                <div v-if="recentHelpArticles.length" class="message-list">
                  <router-link
                    v-for="item in recentHelpArticles"
                    :key="item.id"
                    class="message-row"
                    :to="`/client/help/${item.id}`"
                  >
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
            <t-alert
              v-if="sectionFailed.todos"
              class="dashboard-section-alert"
              theme="error"
              message="待办事项加载失败，请稍后再试"
            >
              <template #operation>
                <t-button variant="text" theme="primary" size="small" @click="loadDashboard">重试</t-button>
              </template>
            </t-alert>
            <div v-if="!todosReady && !sectionFailed.todos" class="todo-list" aria-busy="true">
              <t-skeleton
                v-for="placeholder in 4"
                :key="placeholder"
                :row-col="[{ width: '55%', height: 'var(--td-line-height-body-medium)' }]"
              />
            </div>
            <div v-else-if="todosReady" class="todo-list">
              <router-link
                v-for="item in todoItems"
                :key="item.key"
                class="todo-row"
                :to="item.path"
                :aria-label="item.count > 0 ? `${item.label}：${item.count} 项，需要处理` : `${item.label}：0 项`"
              >
                <span class="todo-row__left">
                  <span class="todo-row__icon" :class="item.tone"><component :is="item.icon" /></span>
                  <span>{{ item.label }}</span>
                </span>
                <span class="todo-row__count" :class="{ 'is-alert': item.count > 0 }">
                  <error-circle-icon v-if="item.count > 0" class="todo-row__alert-icon" aria-hidden="true" />
                  {{ item.count }}
                </span>
              </router-link>
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
              <img
                v-if="supportQr"
                :src="supportQr"
                alt="QQ 群二维码"
                class="support-box__qr"
                loading="lazy"
                decoding="async"
              />
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
    </template>
  </section>
</template>
<script setup lang="ts">
import DataState from '@caiwu/shared/user-v3/components/DataState.vue';
import {
  CouponIcon,
  DashboardIcon,
  DesktopIcon,
  ErrorCircleIcon,
  FileIcon,
  GamepadIcon,
  GiftIcon,
  GridViewIcon,
  HelpCircleIcon,
  InternetIcon,
  MoneyIcon,
  NotificationIcon,
  PreciseMonitorIcon,
  ServerIcon,
  ServiceIcon,
  WalletIcon,
} from 'tdesign-icons-vue-next';
import { MessagePlugin } from 'tdesign-vue-next/es/message/plugin';
import type { Component } from 'vue';
import { computed, onActivated, onDeactivated, onMounted, onUnmounted, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';

import clientApi from '@/api/client';
import { useNoticeReadStatus } from '@/domains/content/useNoticeReadStatus';
import { useSiteBrandingStore, useUserStore } from '@/store';
import type {
  ClientUserInfo,
  ContentArticleRecord,
  CouponSummary,
  FinanceLedgerSummary,
  ServiceOverviewGroup,
  ServiceOverviewPayload,
} from '@/types/client';
import { getErrorMessage } from '@/utils/error';
import { formatMoney, formatMoneyText, formatShortDateTime as formatDate } from '@/utils/format';

import { resolvePagedList, toDateText, toNumber, useDashboardSecondary } from './composables/useDashboardSecondary';

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

const {
  balanceLogsLoadFailed,
  invoicesLoadFailed,
  balanceLogsDaily,
  paidInvoices,
  balanceLogsLoaded,
  invoicesLoaded,
  recentHelpArticles,
  loadConsumptionCharts,
  loadSecondaryData,
} = useDashboardSecondary(fetchUnreadCount);

const loadError = ref(false);
// 分区就绪标记：首屏不再等最慢接口，各块拿到数据即可独立渲染。
const profileReady = ref(false);
const productsReady = ref(false);
const messagesReady = ref(false);
const todosReady = ref(false);
// 分区级失败标记：单笔请求失败时用「加载失败」占位，避免骨架屏无限转圈或把失败伪装成 0 数据
const sectionFailed = reactive({ summary: false, products: false, messages: false, todos: false });
// D-01：账户概览四张卡的数据源不是同一个接口（前三张来自 financeLedgerSummary，优惠券卡来自 couponsSummary），
// 用一个共享的 summaryReady 判定就绪，等于让一个接口的失败被另一个接口的成功掩盖。
// 这里按「财政组 / 优惠券组」各记一份就绪与失败，卡片再按自己归属的组取值。
const financeGroupReady = ref(false);
const financeGroupFailed = ref(false);
const couponsGroupReady = ref(false);
const couponsGroupFailed = ref(false);
const activeNoticeTab = ref<'notice' | 'help'>('notice');
const recentNotices = ref<ContentArticleRecord[]>([]);
// D-11：待处理工单数一律由后端聚合给出。此前是拉 5 条工单再本地 filter(status !== 3)，
// 用户只要开着 6 张工单，这个数字就永远停在 5 —— 它不是「待处理 5 张」，是「我最多只看了眼 5 张」。
const openTicketTotal = ref(0);
const financeSummary = ref<FinanceLedgerSummary>({
  cash_balance: '0.00',
  total_out: '0.00',
  recharge_in: '0.00',
  invoice_payment_out: '0.00',
  unpaid_count: 0,
  unpaid_amount: '0.00',
  total_invoices: 0,
});
const couponSummary = ref<CouponSummary>({
  total: 0,
  available: 0,
  used_up: 0,
  expired: 0,
});
const serviceOverview = ref<ServiceOverviewPayload>({
  total: 0,
  category_total: 0,
  list: [],
  catalog_types: [],
});

function isHandledError(error: unknown): error is { __handled: boolean } {
  return typeof error === 'object' && error !== null && '__handled' in error && Boolean(error.__handled);
}

function currentMonthRange() {
  const now = new Date();
  const start = new Date(now.getFullYear(), now.getMonth(), 1);
  const end = new Date(now.getFullYear(), now.getMonth() + 1, 0);
  return {
    start_date: toDateText(start),
    end_date: toDateText(end),
  };
}

// D-12：产品格图标只按后端下发的稳定标识解析，不再拿产品标题做中文 includes 匹配
// （标题文案一改图标就错配，等于把业务命名耦进视图层）。
// 标识优先级：product_type / key（一级菜单 code）→ 后端 icon 字符串 → 兜底。
const PRODUCT_CODE_META: Record<string, { icon: Component; tone: string }> = {
  cloud_server: { icon: ServerIcon, tone: 'is-brand' },
  physical_machine: { icon: DashboardIcon, tone: 'is-success' },
  bare_metal: { icon: DashboardIcon, tone: 'is-success' },
  game_cloud: { icon: GamepadIcon, tone: 'is-info' },
  cloud_desktop: { icon: DesktopIcon, tone: 'is-info' },
  web_hosting: { icon: ServiceIcon, tone: 'is-warning' },
  cdn: { icon: InternetIcon, tone: 'is-warning' },
  other_services: { icon: GridViewIcon, tone: 'is-muted' },
};

// 后端 ProductType::iconOf() 的取值域（Platform/Gamepad/Desktop/Server/Node/Grid/Monitor）。
// 它用的是自家图标命名，不是 tdesign 的组件名，必须显式对照，不能直接当组件用。
const BACKEND_ICON_MAP: Record<string, Component> = {
  Platform: ServerIcon,
  Gamepad: GamepadIcon,
  Desktop: DesktopIcon,
  Server: ServerIcon,
  Node: InternetIcon,
  Grid: GridViewIcon,
  Monitor: PreciseMonitorIcon,
};

function resolveProductMeta(item: ServiceOverviewGroup, key: string) {
  // key 为一级菜单 code（分组口径），业务类型另取 product_type 兜底。
  const codeKey = String(item.product_type || '').trim() || key;
  const codeMeta = PRODUCT_CODE_META[codeKey] ?? PRODUCT_CODE_META[key];
  const backendIcon = BACKEND_ICON_MAP[String(item.icon || '').trim()];
  if (codeMeta) {
    return { icon: backendIcon ?? codeMeta.icon, tone: codeMeta.tone };
  }
  if (backendIcon) {
    return { icon: backendIcon, tone: 'is-muted' };
  }
  return { icon: ServerIcon, tone: 'is-muted' };
}

const userInfo = computed<ClientUserInfo>(() => (userStore.info || {}) as ClientUserInfo);
const displayName = computed(() =>
  String(
    userInfo.value.nickname || userInfo.value.display_name || userInfo.value.email || userInfo.value.name || '客户账户',
  ),
);
// 头像只渲染首字：接口与 shared 均未下发头像地址，原先的 avatarUrl 恒为 '' ，
// 绑定到 t-avatar 的 :image 永远不会生效，属留了一个假的扩展点（PR-36）。
const avatarText = computed(() => displayName.value.slice(0, 1) || '客');
const userIdText = computed(() => String(userInfo.value.id || '--'));
const isVerified = computed(() => Number(userInfo.value.is_verified || 0) === 1);
const isPhoneBound = computed(() => Boolean(String(userInfo.value.phone || '').trim()));
const isEmailBound = computed(() => Boolean(String(userInfo.value.email || '').trim()));
const supportQr = computed(() => siteBranding.supportGroupQr || '');
const supportGroupLink = computed(() => siteBranding.supportGroupLink || '');
const supportPhoneText = computed(() => siteBranding.serviceQqGroup || '-');
const supportGroupTitle = computed(() => siteBranding.supportGroupTitle || '官方 QQ 群聊');
const supportGroupText = computed(() => siteBranding.supportGroupText || '加入官方 QQ 群聊');

const summaryCards = computed(() => [
  {
    key: 'balance',
    label: '账户余额',
    value: formatMoneyText(financeSummary.value.cash_balance),
    note: `ID: ${userIdText.value}`,
    ready: financeGroupReady.value,
    failed: financeGroupFailed.value,
  },
  {
    key: 'recharge',
    label: '本月充值',
    value: formatMoneyText(financeSummary.value.recharge_in),
    note: '财务入账汇总',
    ready: financeGroupReady.value,
    failed: financeGroupFailed.value,
  },
  {
    key: 'payment',
    label: '本月消费',
    value: formatMoneyText(financeSummary.value.invoice_payment_out),
    note: '本月账单支出',
    warning: true,
    ready: financeGroupReady.value,
    failed: financeGroupFailed.value,
  },
  {
    key: 'coupon',
    label: '可用优惠券',
    value: String(Number(couponSummary.value.available || 0)),
    unit: '张',
    note: `总计 ${Number(couponSummary.value.total || 0)} 张`,
    ready: couponsGroupReady.value,
    failed: couponsGroupFailed.value,
  },
]);

// 问候语、日期与「今年」标签都依赖当前时间，但 computed 无法感知时间流逝：
// 用户上午打开、下午回到控制台（keep-alive 常驻）时问候语仍是「早上好」。
// 用一个 60 秒的时钟推进依赖，页面不在前台时停止计数，避免后台空转。
const nowTick = ref(Date.now());
let nowTimer: number | null = null;

function startNowTimer() {
  stopNowTimer();
  nowTimer = window.setInterval(() => {
    nowTick.value = Date.now();
  }, 60 * 1000);
}

function stopNowTimer() {
  if (nowTimer !== null) {
    window.clearInterval(nowTimer);
    nowTimer = null;
  }
}

const currentYearLabel = computed(() => `${new Date(nowTick.value).getFullYear()} 年`);

const greetingText = computed(() => {
  const hour = new Date(nowTick.value).getHours();
  if (hour < 6) return '夜深了';
  if (hour < 9) return '早上好';
  if (hour < 12) return '上午好';
  if (hour < 14) return '中午好';
  if (hour < 18) return '下午好';
  if (hour < 22) return '晚上好';
  return '夜深了';
});

const todayDateText = computed(() => {
  const now = new Date(nowTick.value);
  const weekDays = ['日', '一', '二', '三', '四', '五', '六'];
  return `${now.getFullYear()}年 ${now.getMonth() + 1}月 ${now.getDate()}日 星期${weekDays[now.getDay()]}`;
});

const productCards = computed<ProductCard[]>(() => {
  if (serviceOverview.value.list.length) {
    return serviceOverview.value.list.slice(0, 6).map((item, index) => {
      const key = String(item.key || item.product_type || `product-${index}`);
      const meta = resolveProductMeta(item, key);
      return {
        key,
        title: String(item.title || item.name || item.product_type_label || '云产品'),
        countText: `${Number(item.active_count || 0)} 个`,
        count: Number(item.active_count || 0),
        path: '/client/services',
        primaryServiceId: Number(item.primary_service_id || 0),
        icon: meta.icon,
        tone: meta.tone,
      };
    });
  }

  // D-13：没有已开通服务时返回空数组，由模板渲染空态。
  // 原先这里硬编码 6 张与真实卡片同款的「0 个」占位卡，用户无法分辨那是推荐位还是自己真有的产品。
  return [];
});

const dailyExpenseData = computed(() => {
  const days: { date: string; amount: number }[] = [];
  const now = new Date();
  for (let i = 6; i >= 0; i -= 1) {
    const date = new Date(now);
    date.setDate(date.getDate() - i);
    days.push({ date: toDateText(date), amount: 0 });
  }

  for (const entry of balanceLogsDaily.value) {
    const change = toNumber(entry.change_amount);
    if (change >= 0) continue;
    const dateStr = String(entry.created_at || '').slice(0, 10);
    const matched = days.find((item) => item.date === dateStr);
    if (matched) {
      matched.amount = Math.round((matched.amount + Math.abs(change)) * 100) / 100;
    }
  }

  return days;
});

// D-04：原先每根柱都带 showLabel: true，7 个「10/29」式标签在窄屏上必然互相压字。
// 现在标签的疏密交给 CSS 断点决定（见 .bar-chart__label--wide-only），数据层不再输出 showLabel，
// 免得又出现「数据层说显示、样式层说不显示」的两套真源。
const dailyBars = computed(() => {
  const amounts = dailyExpenseData.value.map((item) => item.amount);
  const max = Math.max(...amounts, 1);
  return dailyExpenseData.value.map((item) => ({
    ...item,
    height: item.amount > 0 ? `${Math.max(8, Math.round((item.amount / max) * 100))}%` : '0%',
    label: `${Number(item.date.slice(5, 7))}/${Number(item.date.slice(8, 10))}`,
    tooltip: `${item.date.slice(5).replace('-', '/')} · 消费 ¥${formatMoney(item.amount)}`,
  }));
});

const dailyBarsHasData = computed(() => dailyExpenseData.value.some((item) => item.amount > 0));

const last7DaysConsumptionTotal = computed(
  () => Math.round(dailyExpenseData.value.reduce((sum, item) => sum + item.amount, 0) * 100) / 100,
);

const monthlyConsumptionData = computed(() => {
  const now = new Date();
  const year = now.getFullYear();
  const months: { key: string; label: string; month: number; amount: number; count: number }[] = [];
  for (let m = 0; m < 12; m += 1) {
    const key = `${year}-${String(m + 1).padStart(2, '0')}`;
    months.push({ key, label: `${String(m + 1).padStart(2, '0')}月`, month: m + 1, amount: 0, count: 0 });
  }

  for (const invoice of paidInvoices.value) {
    const date = String(invoice.paid_at || invoice.created_at || '');
    if (!date.startsWith(`${year}-`)) continue;
    const monthKey = date.slice(0, 7); // YYYY-MM
    const target = months.find((item) => item.key === monthKey);
    if (!target) continue;
    const amount = toNumber(invoice.paid_amount || invoice.amount);
    target.amount = Math.round((target.amount + amount) * 100) / 100;
    target.count += 1;
  }
  return months;
});

const monthlyBars = computed(() => {
  const amounts = monthlyConsumptionData.value.map((item) => item.amount);
  const max = Math.max(...amounts, 1);
  return monthlyConsumptionData.value.map((item) => ({
    ...item,
    height: item.amount > 0 ? `${Math.max(8, Math.round((item.amount / max) * 100))}%` : '0%',
    amountLabel: item.amount > 0 ? formatMoney(item.amount) : '',
    // 峰值柱常显金额，其余悬停显示：触屏拿不到 hover，否则整张图读不出数
    isPeak: item.amount > 0 && item.amount === Math.max(...amounts),
    tooltip: `${item.label} · 消费 ¥${formatMoney(item.amount)} · ${item.count} 笔账单`,
  }));
});

const monthlyBarsHasData = computed(() => monthlyConsumptionData.value.some((item) => item.amount > 0));
const currentYearConsumptionTotal = computed(
  () => Math.round(monthlyConsumptionData.value.reduce((sum, item) => sum + item.amount, 0) * 100) / 100,
);

const expiringServiceCount = computed(() =>
  serviceOverview.value.list.reduce((sum, item) => sum + Number(item.expiring_count || 0), 0),
);

const openTicketCount = computed(() => openTicketTotal.value);

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
    label: '未读公告',
    count: unreadCount.value,
    path: '/client/notices',
    icon: NotificationIcon,
    tone: 'is-muted',
  },
]);

const quickLinks = [
  { label: '我的服务', path: '/client/services', icon: ServerIcon },
  { label: '账单记录', path: '/client/invoices', icon: FileIcon },
  { label: '充值记录', path: '/client/payments', icon: MoneyIcon },
  { label: '工单支持', path: '/client/tickets', icon: ServiceIcon },
  { label: '账户充值', path: '/client/recharge', icon: WalletIcon },
  { label: '帮助中心', path: '/client/help', icon: HelpCircleIcon },
  { label: '优惠券', path: '/client/coupons', icon: CouponIcon },
  { label: '推广大使', path: '/client/referral', icon: GiftIcon },
];

function resetSectionReady() {
  profileReady.value = false;
  financeGroupReady.value = false;
  financeGroupFailed.value = false;
  couponsGroupReady.value = false;
  couponsGroupFailed.value = false;
  productsReady.value = false;
  messagesReady.value = false;
  todosReady.value = false;
  sectionFailed.summary = false;
  sectionFailed.products = false;
  sectionFailed.messages = false;
  sectionFailed.todos = false;
}

async function loadDashboard() {
  loadError.value = false;
  resetSectionReady();

  try {
    // 守卫已完成水合时直接复用，避免同一会话重复请求 /v2/client/auth/info（首屏串行阻塞点之一）。
    // 这里允许失败：账户信息只喂首屏问候语与头像首字，所有消费点都有 '--' / '客' 兜底，
    // 真把整页拖进错误态反而会遮住下面能正常渲染的余额与服务数据（C-22 已确认的取舍）。
    await userStore.ensureUserInfo().catch(() => {});
    profileReady.value = true;

    // 站名同理：缺失时回退默认品牌名，属装饰信息，失败不阻断首屏（C-22）。
    // 只在已有缓存时复用，否则仍要重新拉一次，避免同名占位一直留在侧栏。
    if (!siteBranding.siteName) {
      void siteBranding.fetchSiteConfig().catch(() => {});
    } else {
      void siteBranding.fetchSiteConfig();
    }

    const monthRange = currentMonthRange();
    // 首屏核心数据：余额、服务概览、待办项、公告（优先加载）
    const [noticesRes, ticketsRes, financeRes, couponsRes, serviceOverviewRes] = await Promise.allSettled([
      clientApi.notices({ page: 1, page_size: 5 }),
      // status='ongoing' 是后端聚合口径（未关闭三态），page_size=1 只是把响应压到最小：
      // 这一路要的只有 total，列表内容从不渲染。
      clientApi.tickets({ page: 1, page_size: 1, status: 'ongoing' }),
      clientApi.financeLedgerSummary(monthRange),
      clientApi.couponsSummary(),
      clientApi.groupedOverview(),
    ]);

    // 首屏全部失败时让 DataState 失败态可达（否则页面静默渲染全 0 数据）
    const results = [noticesRes, ticketsRes, financeRes, couponsRes, serviceOverviewRes];
    if (results.every((result) => result.status === 'rejected')) {
      const firstError = (results.find((result) => result.status === 'rejected') as PromiseRejectedResult).reason;
      throw firstError instanceof Error ? firstError : new Error('控制台数据加载失败');
    }

    // 处理首屏数据：各分区拿到结果立即渲染，不再等整批结束。
    // ready 必须按该分区的真实请求结果判定：只要有一笔成功就整体放行，会把失败分区渲染成
    // 「0 元 / 0 条」，把加载失败伪装成真实数据。
    if (noticesRes.status === 'fulfilled') {
      recentNotices.value = resolvePagedList(noticesRes.value.data);
    }
    messagesReady.value = noticesRes.status === 'fulfilled';
    sectionFailed.messages = noticesRes.status === 'rejected';
    if (ticketsRes.status === 'fulfilled') {
      // 只取 total：列表内容不参与首屏渲染，page_size=1 的 list 没有任何消费者。
      openTicketTotal.value = Math.max(0, Number(ticketsRes.value.data?.total || 0));
    }
    todosReady.value = ticketsRes.status === 'fulfilled';
    sectionFailed.todos = ticketsRes.status === 'rejected';
    if (financeRes.status === 'fulfilled') {
      financeSummary.value = {
        ...financeSummary.value,
        ...(financeRes.value.data || {}),
      };
    }
    if (couponsRes.status === 'fulfilled') {
      couponSummary.value = {
        ...couponSummary.value,
        ...(couponsRes.value.data || {}),
      };
    }
    financeGroupReady.value = financeRes.status === 'fulfilled';
    financeGroupFailed.value = financeRes.status === 'rejected';
    couponsGroupReady.value = couponsRes.status === 'fulfilled';
    couponsGroupFailed.value = couponsRes.status === 'rejected';
    // 告警只在「有卡真的拿不到数」时出，不再是「两组都失败」——一组失败同样会让卡显示不出来。
    sectionFailed.summary = financeGroupFailed.value || couponsGroupFailed.value;
    if (serviceOverviewRes.status === 'fulfilled') {
      serviceOverview.value = serviceOverviewRes.value.data || {
        total: 0,
        category_total: 0,
        list: [],
        catalog_types: [],
      };
    }
    productsReady.value = serviceOverviewRes.status === 'fulfilled';
    sectionFailed.products = serviceOverviewRes.status === 'rejected';
    // 首屏核心数据加载完成（总览卡片 + 产品概览 + 待办项）
    void loadSecondaryData();
  } catch (error: unknown) {
    loadError.value = true;
    // 失败态接管整页；重试时由 loadDashboard 开头的 resetSectionReady 恢复骨架，这里先复原避免闪回旧数据。
    resetSectionReady();
    if (!isHandledError(error)) {
      MessagePlugin.error(getErrorMessage(error, '控制台数据加载失败'));
    }
  }
}

onMounted(() => {
  void loadDashboard();
  startNowTimer();
});

onActivated(() => {
  nowTick.value = Date.now();
  startNowTimer();
});

onDeactivated(() => {
  stopNowTimer();
});

onUnmounted(() => {
  stopNowTimer();
});
</script>
<style src="./index.less" lang="less" scoped></style>
