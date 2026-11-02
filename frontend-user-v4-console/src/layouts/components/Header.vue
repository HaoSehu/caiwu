<template>
  <div :class="layoutCls">
    <t-head-menu :class="menuCls" :theme="menuTheme" expand-type="popup" :value="active">
      <template #logo>
        <!-- 品牌位是页面内导航入口：与 SideNav 品牌位同口径，必须可聚焦并用键盘触发（L-15 同类） -->
        <span
          v-if="showLogo"
          class="header-logo-container"
          role="button"
          tabindex="0"
          :aria-label="`返回控制台首页（${siteBranding.siteName}）`"
          @click="handleNav('/client/dashboard')"
          @keydown.enter.self.prevent="handleNav('/client/dashboard')"
          @keydown.space.self.prevent="handleNav('/client/dashboard')"
        >
          <span class="header-logo-mark">{{ siteBranding.brandInitials }}</span>
          <span class="header-logo-name">{{ siteBranding.siteName }}</span>
        </span>
        <div v-else class="header-operate-left">
          <t-button
            theme="default"
            shape="square"
            variant="text"
            :title="sidebarButtonTitle"
            :aria-label="sidebarButtonTitle"
            @click="changeCollapsed"
          >
            <!-- 移动端与桌面统一用三道横杠（view-list）：menu 图标是带边框的窗口样式，易被误读为列表 -->
            <t-icon class="collapsed-icon" name="view-list" />
          </t-button>
          <span class="header-page-brand">{{ siteBranding.siteName }}</span>
        </div>
      </template>
      <template v-if="layout !== 'side'" #default>
        <menu-content class="header-menu" :nav-data="menu" />
      </template>
      <template #operations>
        <div class="operations-container">
          <t-popup
            v-model:visible="inboxVisible"
            trigger="click"
            :placement="isMobile ? 'bottom' : 'bottom-right'"
            :overlay-inner-style="{ padding: 0 }"
            overlay-class-name="inbox-popup"
            destroy-on-close
            @visible-change="handleInboxVisible"
          >
            <template #content>
              <div class="inbox-panel">
                <div class="inbox-panel__head">
                  <span class="inbox-panel__title">站内信</span>
                  <t-button
                    v-if="inboxUnread > 0"
                    theme="primary"
                    variant="text"
                    size="small"
                    @click="handleMarkAllReadClick"
                  >
                    全部已读
                  </t-button>
                </div>
                <t-loading :loading="feedLoading" size="small">
                  <div v-if="feedItems.length" class="inbox-panel__list">
                    <div
                      v-for="item in feedItems"
                      :key="item.id"
                      class="inbox-item"
                      :class="{ 'inbox-item--unread': !item.read }"
                      role="button"
                      tabindex="0"
                      :aria-label="`${item.read ? '' : '未读'}消息：${item.title}`"
                      @click="handleInboxItemClick(item)"
                      @keydown.enter.prevent="handleInboxItemClick(item)"
                      @keydown.space.prevent="handleInboxItemClick(item)"
                    >
                      <div class="inbox-item__top">
                        <t-tag size="small" variant="light" :theme="resolveTagTheme(item.type)">{{
                          item.type_label
                        }}</t-tag>
                        <span class="inbox-item__time">{{ formatInboxTime(item.created_at) }}</span>
                      </div>
                      <div class="inbox-item__title">{{ item.title }}</div>
                      <div v-if="item.summary" class="inbox-item__summary">{{ item.summary }}</div>
                    </div>
                  </div>
                  <div v-else class="inbox-panel__empty">暂无消息</div>
                </t-loading>
                <div
                  class="inbox-panel__foot"
                  role="button"
                  tabindex="0"
                  aria-label="查看全部站内信"
                  @click="handleNav('/client/notices')"
                  @keydown.enter.prevent="handleNav('/client/notices')"
                  @keydown.space.prevent="handleNav('/client/notices')"
                >
                  查看全部
                </div>
              </div>
            </template>
            <t-badge :count="inboxUnread" :offset="[4, 4]" size="small">
              <t-button theme="default" shape="square" variant="text" title="站内信" aria-label="站内信">
                <template #icon><notification-icon /></template>
              </t-button>
            </t-badge>
          </t-popup>
          <t-dropdown :min-column-width="160" trigger="click">
            <template #dropdown>
              <t-dropdown-item class="operations-dropdown-container-item" @click="handleOpenWebsite">
                <browse-icon />返回官网
              </t-dropdown-item>
              <t-dropdown-item class="operations-dropdown-container-item" @click="handleNav('/client/profile')">
                <user-circle-icon />个人资料
              </t-dropdown-item>
              <t-dropdown-item class="operations-dropdown-container-item" @click="handleNav('/client/recharge')">
                <wallet-icon />账户充值
              </t-dropdown-item>
              <t-dropdown-item class="operations-dropdown-container-item" @click="handleLogout">
                <poweroff-icon />退出登录
              </t-dropdown-item>
            </template>
            <t-button class="header-user-btn" theme="default" variant="text">
              <template #icon>
                <t-avatar class="header-user-avatar" size="small">{{ userInitials }}</t-avatar>
              </template>
              <span class="header-user-account">{{ accountName }}</span>
              <span class="header-user-balance">余额 {{ formattedBalance }}</span>
              <template #suffix><chevron-down-icon /></template>
            </t-button>
          </t-dropdown>
        </div>
      </template>
    </t-head-menu>
  </div>
</template>
<script setup lang="ts">
import {
  BrowseIcon,
  ChevronDownIcon,
  NotificationIcon,
  PoweroffIcon,
  UserCircleIcon,
  WalletIcon,
} from 'tdesign-icons-vue-next';
import { DialogPlugin } from 'tdesign-vue-next/es/dialog/plugin';
import { MessagePlugin } from 'tdesign-vue-next/es/message/plugin';
import type { PropType } from 'vue';
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';

import { useDeviceLayout } from '@/composables/useDeviceLayout';
import { prefix } from '@/config/global';
import type { InboxItem } from '@/domains/content/useInbox';
import { useInbox } from '@/domains/content/useInbox';
import { getActive } from '@/router';
import { useSettingStore, useSiteBrandingStore, useUserStore } from '@/store';
import type { MenuRoute, ModeType } from '@/types/interface';
import { getErrorMessage } from '@/utils/error';

import MenuContent from './MenuContent.vue';

const { theme, layout, showLogo, menu, isFixed, isCompact } = defineProps({
  theme: {
    type: String,
    default: 'light',
  },
  layout: {
    type: String,
    default: 'top',
  },
  showLogo: {
    type: Boolean,
    default: true,
  },
  menu: {
    type: Array as PropType<MenuRoute[]>,
    default: () => [],
  },
  isFixed: {
    type: Boolean,
    default: false,
  },
  isCompact: {
    type: Boolean,
    default: false,
  },
  maxLevel: {
    type: Number,
    default: 3,
  },
});

const router = useRouter();
const settingStore = useSettingStore();
const user = useUserStore();
const siteBranding = useSiteBrandingStore();
const {
  unreadCount: inboxUnread,
  feedItems,
  feedLoading,
  fetchUnreadCount,
  fetchFeed,
  markRead,
  markAllRead,
} = useInbox();
const { isMobile } = useDeviceLayout();

const active = computed(() => getActive());
const accountName = computed(() => user.userInfo.name || '创欧云用户');
const userInitials = computed(() => {
  const name = accountName.value.trim();
  return name.slice(0, 1) || siteBranding.brandInitials || '创';
});
const formattedBalance = computed(() => {
  const value = user.userInfo.cash_balance;
  const amount = Number(value);
  if (!Number.isFinite(amount)) {
    return '--';
  }
  return `¥${amount.toFixed(2)}`;
});

const layoutCls = computed(() => [`${prefix}-header-layout`]);

const menuCls = computed(() => {
  return [
    {
      [`${prefix}-header-menu`]: !isFixed,
      [`${prefix}-header-menu-fixed`]: isFixed,
      [`${prefix}-header-menu-fixed-side`]: layout === 'side' && isFixed,
      [`${prefix}-header-menu-fixed-side-compact`]: layout === 'side' && isFixed && isCompact,
    },
  ];
});
const menuTheme = computed(() => theme as ModeType);
const sidebarButtonTitle = computed(() => {
  if (isMobile.value) {
    return settingStore.isMobileSidebarVisible ? '关闭菜单' : '打开菜单';
  }
  // R5：§6 禁用「侧边栏」，统一称「菜单」
  return settingStore.isSidebarCompact ? '展开菜单' : '收起菜单';
});

const changeCollapsed = () => {
  if (isMobile.value) {
    settingStore.updateConfig({
      isMobileSidebarVisible: !settingStore.isMobileSidebarVisible,
    });
    return;
  }

  settingStore.updateConfig({
    isSidebarCompact: !settingStore.isSidebarCompact,
  });
};

const handleNav = (url: string) => {
  inboxVisible.value = false;
  router.push(url);
};

// 返回官网：站后台配置了官网地址则新窗口打开，否则走 public-redirect 的 /products 兜底
// L-21：window.open 会被浏览器拦截（非用户手势、企业策略、弹窗白名单），原实现直接丢弃结果，
// 用户点了「返回官网」什么都不发生且无提示，属静默失败。
// 这里不在 features 里写 'noopener'：规范规定带 noopener 时 window.open 恒返回 null，
// 无法区分「成功打开」与「被拦截」，检测会失真；改为打开后手动置 opener = null，
// 安全效果相同而返回值可靠，被拦时退回同窗口导航，至少不让操作落空。
const handleOpenWebsite = () => {
  inboxVisible.value = false;
  const siteUrl = String(siteBranding.frontendUrl || '').trim();
  if (/^https?:\/\//i.test(siteUrl)) {
    const opened = window.open(siteUrl, '_blank');
    if (opened) {
      opened.opener = null;
      return;
    }
    window.location.assign(siteUrl);
    return;
  }
  handleNav('/products');
};

const inboxVisible = ref(false);

// 打开面板不无条件重拉：每次点铃铛都打一次 /notifications/feed 属于纯浪费，
// 但也不能一直吃旧数据（未读状态会被 markRead 之外的端改写）。
// 折中口径：有缓存且 30s 内拉过就直接复用，过期或无缓存才请求。
const INBOX_FEED_TTL_MS = 30_000;
let lastFeedFetchedAt = 0;

const handleInboxVisible = async (visible: boolean) => {
  if (!visible) return;
  const fresh = feedItems.value.length > 0 && Date.now() - lastFeedFetchedAt < INBOX_FEED_TTL_MS;
  if (fresh) return;
  await fetchFeed(10);
  lastFeedFetchedAt = Date.now();
};

// 先完成已读上报再导航：markRead 失败时（后端不可达 / 401）不能假装已读，
// 服务端仍是未读，气泡数字会在下次拉取时回弹，所以这里 await 并显式提示。
const handleInboxItemClick = async (item: InboxItem) => {
  try {
    await markRead(item);
  } catch (error: unknown) {
    MessagePlugin.error(getErrorMessage(error, '标记已读失败，请稍后重试'));
  }
  inboxVisible.value = false;
  if (item.link) {
    router.push(item.link);
    return;
  }
  // 无跳转链接的消息（系统通知类）点开就是「已读」，不再静默留白
  MessagePlugin.info('该消息无关联页面');
};

const handleMarkAllRead = async () => {
  const confirmed = await new Promise<boolean>((resolve) => {
    const dialog = DialogPlugin.confirm({
      header: '全部标记已读',
      body: '确认将所有未读消息标记为已读吗？此操作不可撤销。',
      confirmBtn: '确定',
      onConfirm: () => {
        dialog.destroy();
        resolve(true);
      },
      onCancel: () => {
        dialog.destroy();
        resolve(false);
      },
      onClose: () => {
        dialog.destroy();
        resolve(false);
      },
    });
  });
  if (!confirmed) return;
  try {
    await markAllRead();
    MessagePlugin.success('已全部标记为已读');
  } catch (error: unknown) {
    // 失败必须落到用户可见的错误提示上，不能沿用「成功」文案
    MessagePlugin.error(getErrorMessage(error, '标记已读失败，请稍后重试'));
  }
};

const handleMarkAllReadClick = () => {
  handleMarkAllRead();
};

const resolveTagTheme = (type: string): 'primary' | 'warning' | 'danger' | 'success' | 'default' => {
  if (type === 'invoice_overdue_reminder' || type === 'service_expire_reminder') return 'danger';
  if (type === 'invoice_payment_reminder' || type === 'service_renew_reminder' || type === 'ticket_pending_reminder')
    return 'warning';
  if (type === 'order_paid' || type === 'ticket_staff_reply') return 'success';
  if (type === 'notice') return 'primary';
  return 'default';
};

const formatInboxTime = (value: string | null): string => {
  if (!value) return '';
  const date = new Date(value.replace(/-/g, '/'));
  if (Number.isNaN(date.getTime())) return value;
  const diff = Date.now() - date.getTime();
  const minute = 60 * 1000;
  const hour = 60 * minute;
  const day = 24 * hour;
  if (diff < minute) return '刚刚';
  if (diff < hour) return `${Math.floor(diff / minute)} 分钟前`;
  if (diff < day) return `${Math.floor(diff / hour)} 小时前`;
  if (diff < 7 * day) return `${Math.floor(diff / day)} 天前`;
  return `${date.getMonth() + 1}-${date.getDate()}`;
};

const handleLogout = async () => {
  const confirmed = await new Promise<boolean>((resolve) => {
    const dialog = DialogPlugin.confirm({
      header: '退出登录',
      body: '确认退出当前账户吗？',
      confirmBtn: '确定',
      onConfirm: () => {
        dialog.destroy();
        resolve(true);
      },
      onCancel: () => {
        dialog.destroy();
        resolve(false);
      },
      onClose: () => {
        dialog.destroy();
        resolve(false);
      },
    });
  });
  if (!confirmed) return;
  const redirect = router.currentRoute.value.fullPath;
  await user.logout();
  await router.push({
    path: '/client/login',
    query: { redirect: encodeURIComponent(redirect) },
  });
};

onMounted(() => {
  siteBranding.fetchSiteConfig();
  fetchUnreadCount();
});
</script>
<style lang="less" scoped>
.@{starter-prefix}-header {
  &-menu-fixed {
    position: fixed;
    top: 0;
    z-index: 1001;

    :deep(.t-head-menu__inner) {
      padding-right: var(--td-comp-margin-xl);
    }

    &-side {
      // 与 layout.less 的侧栏占位同一源变量，避免三处各写一份 232px / 64px（L-23）
      left: @starter-sidebar-width;
      right: 0;
      z-index: 10;
      width: auto;
      // 仅过渡位移相关属性，避免 all 带来的全量合成开销与误过渡
      transition: left 0.3s cubic-bezier(0.38, 0, 0.24, 1);

      &-compact {
        left: @starter-sidebar-collapsed-width;
      }
    }
  }

  &-logo-container {
    cursor: pointer;
    display: inline-flex;
  }
}

.header-menu {
  flex: 1 1 auto;
  display: inline-flex;

  :deep(.t-menu__item) {
    min-width: unset;
  }
}

.operations-container {
  display: flex;
  align-items: center;

  .t-popup__reference {
    display: flex;
    align-items: center;
    justify-content: center;
  }

  gap: var(--td-comp-margin-s);
}

.header-operate-left {
  display: flex;
  align-items: center;
  line-height: 0;
  gap: var(--td-comp-margin-m);
  // TDesign 的 .t-menu__logo > * 会给品牌位塞 24px 左边距：桌面刚好与内容区 padding 24px 对齐，
  // 移动端内容区 padding 只有 12px，折叠按钮会被多缩进一截。改为自己控制左内边距，
  // 两种断点下按钮的点击区左边缘都与下方内容左边缘对齐。
  margin-left: 0;
  padding-left: var(--td-comp-margin-xxl);

  .collapsed-icon {
    // 16px 的三道横杠放在小按钮里偏细，提到 18px 更清晰
    font-size: var(--td-font-size-title-large);
  }

  @media (max-width: @screen-sm-max) {
    gap: var(--td-comp-margin-s);
    padding-left: var(--td-comp-margin-m);
  }
}

.header-page-brand {
  color: var(--td-text-color-primary);
  font: var(--td-font-body-medium);
  line-height: var(--td-line-height-body-medium);
}

// 品牌位的宽度是布局几何（图标 36 + 间距 8 + 名称占位），TDesign 令牌号段没有对应值，
// 保留 px 并在此说明；其余间距类属性已全部走令牌（C-23）
.header-logo-container {
  min-width: 184px;
  height: var(--td-comp-size-l);
  display: flex;
  align-items: center;
  gap: var(--td-comp-margin-s);
  margin-left: var(--td-comp-margin-xl);
  color: var(--td-text-color-primary);
  font: var(--td-font-title-medium);

  &:hover {
    cursor: pointer;
  }

  &:focus-visible {
    outline: 0.125rem solid var(--td-brand-color);
    outline-offset: 0.125rem;
    border-radius: var(--td-radius-default);
  }
}

.header-logo-mark {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: var(--td-comp-size-l);
  height: var(--td-comp-size-l);
  border-radius: var(--td-radius-medium);
  color: var(--td-text-color-anti);
  background: var(--td-brand-color);
  font: var(--td-font-body-medium);
}

.header-logo-name {
  white-space: nowrap;
}

.header-user-account {
  display: inline-flex;
  align-items: center;
  color: var(--td-text-color-primary);
  max-width: 10em;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.header-user-btn {
  :deep(.t-button__text) {
    display: inline-flex;
    align-items: center;
    gap: var(--td-comp-margin-s);
  }
}

.header-user-avatar {
  background: var(--td-brand-color-light);
  color: var(--td-brand-color);
}

.header-user-balance {
  color: var(--td-text-color-secondary);
  font: var(--td-font-body-small);
}

:deep(.t-head-menu__inner) {
  border-bottom: thin solid var(--td-component-stroke);
}

.t-menu--light {
  .header-user-account {
    color: var(--td-text-color-primary);
  }
}

@media (max-width: @screen-sm-max) {
  .header-page-brand,
  .header-user-balance {
    display: none;
  }

  .header-user-account {
    max-width: 6em;
  }
}

.operations-dropdown-container-item {
  width: 100%;
  display: flex;
  align-items: center;

  :deep(.t-dropdown__item-text) {
    display: flex;
    align-items: center;
  }

  // 原误用尺寸令牌 --td-comp-size-xxxs 当字号，改用等值(16px)的字号令牌
  .t-icon {
    font-size: var(--td-font-size-body-large);
    margin-right: var(--td-comp-margin-s);
  }

  :deep(.t-dropdown__item) {
    width: 100%;
    margin-bottom: 0;
  }

  &:last-child {
    :deep(.t-dropdown__item) {
      margin-bottom: var(--td-comp-margin-s);
    }
  }
}

// 面板宽度与列表最大高度是弹层的几何尺寸（不是间距），TDesign 令牌号段里没有对应值，
// 保留 px 并在此说明；间距类属性已全部改回令牌（L-24）。
.inbox-panel {
  width: 340px;
  max-width: 88vw;

  &__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: var(--td-comp-margin-m) var(--td-comp-margin-l);
    border-bottom: thin solid var(--td-component-stroke);
  }

  &__title {
    font: var(--td-font-title-small);
    color: var(--td-text-color-primary);
  }

  &__list {
    max-height: 380px;
    overflow-y: auto;
  }

  &__empty {
    padding: var(--td-comp-margin-xxxxl) var(--td-comp-margin-l);
    text-align: center;
    color: var(--td-text-color-placeholder);
    font: var(--td-font-body-small);
  }

  &__foot {
    padding: var(--td-comp-paddingTB-m) var(--td-comp-margin-l);
    text-align: center;
    color: var(--td-brand-color);
    font: var(--td-font-body-small);
    border-top: thin solid var(--td-component-stroke);
    cursor: pointer;

    &:hover {
      background: var(--td-bg-color-container-hover);
    }
  }
}

.inbox-item {
  padding: var(--td-comp-margin-m) var(--td-comp-margin-l);
  border-bottom: thin solid var(--td-component-stroke);
  cursor: pointer;
  transition: background 0.2s;

  &:hover {
    background: var(--td-bg-color-container-hover);
  }

  &:last-child {
    border-bottom: none;
  }

  &--unread {
    position: relative;

    .inbox-item__title {
      font-weight: 600;
    }

    // 6px / 18px 不在 TDesign 令牌号段内（margin 号段 2·4·8·12·16·20…），
    // 用相邻令牌加减表达，既保持像素不变又守住「不得硬编码数值」的约束（L-24）
    &::before {
      content: '';
      position: absolute;
      left: calc(var(--td-comp-margin-s) - var(--td-comp-margin-xxs));
      top: calc(var(--td-comp-margin-l) + var(--td-comp-margin-xxs));
      width: calc(var(--td-comp-margin-s) - var(--td-comp-margin-xxs));
      height: calc(var(--td-comp-margin-s) - var(--td-comp-margin-xxs));
      border-radius: var(--td-radius-circle);
      background: var(--td-error-color);
    }
  }

  &__top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: calc(var(--td-comp-margin-s) - var(--td-comp-margin-xxs));
  }

  &__time {
    color: var(--td-text-color-placeholder);
    font: var(--td-font-body-small);
  }

  &__title {
    color: var(--td-text-color-primary);
    font: var(--td-font-body-medium);
    margin-bottom: var(--td-comp-margin-xs);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  &__summary {
    color: var(--td-text-color-secondary);
    font: var(--td-font-body-small);
    display: -webkit-box;
    -webkit-line-clamp: 2;
    line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
  }
}
</style>
<!-- dropdown 与 popup 内容由 popper 挂到组件作用域外，scoped 无法命中；
     规则以 .operations-dropdown-container-item / .inbox-popup 命名空间收口（审查条目 C5） -->
<!-- eslint-disable-next-line vue-scoped-css/enforce-style-type -->
<style lang="less">
.operations-dropdown-container-item {
  .t-dropdown__item-text {
    display: flex;
    align-items: center;
  }
}

// 移动端站内信：确定性贴底定位（近全宽底部面板），替代 340px 迷你弹层的双重滚动。
// overlayClassName 挂在 popper 外层 .t-popup 元素上（源码 popup.mjs：class 合并
// [prefixCls, overlayClassName]），定位由 @popperjs/core 写内联 transform——
// 此处用 !important 固化为 fixed 贴底，不再依赖 popper 位移计算，机型间行为一致。
// popup content 在组件作用域外，必须放非 scoped 块。
@media (max-width: @screen-sm-max) {
  .t-popup.inbox-popup {
    position: fixed !important;
    inset: auto var(--td-comp-margin-s) var(--td-comp-margin-s) !important;
    transform: none !important;
  }

  .t-popup.inbox-popup .t-popup__content {
    width: 100%;
  }

  .t-popup.inbox-popup .inbox-panel {
    display: flex;
    flex-direction: column;
    width: 100%;
    max-width: none;
    max-height: calc(70dvh - var(--td-comp-margin-l));
    border-radius: var(--td-radius-large) var(--td-radius-large) 0 0;
    padding-bottom: env(safe-area-inset-bottom, 0);

    .inbox-panel__list {
      flex: 1 1 auto;
      max-height: none;
      -webkit-overflow-scrolling: touch;
    }
  }
}
</style>
