<template>
  <el-container class="client-shell" :class="{ 'client-shell--tablet': isTablet }">
    <el-aside
      v-if="!isMobile"
      :width="sidebarWidth"
      class="client-sidebar"
    >
      <div class="sidebar-shell">
        <button type="button" class="brand-panel" @click="go('/client/dashboard')">
          <img
            v-if="effectiveSidebarCollapsed"
            :src="sidebarCompactLogo"
            :alt="appStore.siteName"
            class="brand-mini-logo"
          />

          <div v-else class="brand-lockup-wrap">
            <img
              :src="sidebarLogo"
              :alt="appStore.siteName"
              class="brand-lockup"
            />
          </div>
        </button>

        <el-scrollbar class="sidebar-scroll">
          <section
            v-for="section in navigationSections"
            :key="section.label"
            class="menu-section"
          >
            <p v-show="!effectiveSidebarCollapsed" class="menu-section-title">{{ section.label }}</p>

            <el-menu
              :default-active="activeMenu"
              :collapse="effectiveSidebarCollapsed"
              :collapse-transition="false"
              router
              class="sidebar-menu"
              @select="handleMenuSelect"
            >
              <el-menu-item
                v-for="item in section.items"
                :key="item.index"
                :index="item.index"
              >
                <el-icon><component :is="item.icon" /></el-icon>
                <template #title>{{ item.title }}</template>
              </el-menu-item>
            </el-menu>
          </section>
        </el-scrollbar>
      </div>
    </el-aside>

    <el-container class="client-main-shell">
      <el-header class="client-topbar">
        <div class="topbar-left">
          <button type="button" class="shell-trigger" @click="toggleSidebar">
            <el-icon>
              <Menu v-if="isMobile || isTablet" />
              <Fold v-else-if="!appStore.sidebarCollapsed" />
              <Expand v-else />
            </el-icon>
          </button>

          <div class="page-context">
            <el-breadcrumb separator="/" class="page-breadcrumb">
              <el-breadcrumb-item :to="{ path: '/client/dashboard' }">客户中心</el-breadcrumb-item>
              <el-breadcrumb-item v-for="item in breadcrumbItems" :key="item.path">
                {{ item.title }}
              </el-breadcrumb-item>
            </el-breadcrumb>
            <strong>{{ currentPageTitle }}</strong>
          </div>
        </div>

        <div class="topbar-right">
          <div class="site-copy">
            <span>{{ appStore.siteName }}</span>
          </div>

          <el-dropdown
            trigger="click"
            placement="bottom-end"
            popper-class="client-account-dropdown"
            @command="handleUserAction"
          >
            <button type="button" class="account-entry" aria-label="账户菜单">
              <el-avatar :size="32" class="account-avatar">
                {{ userInitial }}
              </el-avatar>
              <span class="account-copy">
                <strong>{{ displayName }}</strong>
                <small>{{ balanceText }}</small>
              </span>
              <el-icon class="account-entry-arrow"><ArrowDown /></el-icon>
            </button>

            <template #dropdown>
              <el-dropdown-menu>
                <el-dropdown-item disabled class="account-overview-item">
                  <div class="account-meta">
                    <strong>{{ displayName }}</strong>
                    <p>{{ userStore.info?.email || '未绑定邮箱' }}</p>
                    <small>{{ balanceText }}</small>
                  </div>
                </el-dropdown-item>
                <el-dropdown-item command="profile" class="account-menu-item">
                  <el-icon><User /></el-icon>
                  <span>个人资料</span>
                </el-dropdown-item>
                <el-dropdown-item command="verification" class="account-menu-item">
                  <el-icon><CircleCheck /></el-icon>
                  <span>实名认证</span>
                </el-dropdown-item>
                <el-dropdown-item command="recharge" class="account-menu-item">
                  <el-icon><Wallet /></el-icon>
                  <span>账户充值</span>
                </el-dropdown-item>
                <el-dropdown-item command="logout" divided class="account-menu-item danger">
                  <el-icon><SwitchButton /></el-icon>
                  <span>退出登录</span>
                </el-dropdown-item>
              </el-dropdown-menu>
            </template>
          </el-dropdown>
        </div>
      </el-header>

      <el-main class="client-main">
        <div class="client-stage">
          <router-view v-slot="{ Component }">
            <transition name="page-fade" mode="out-in">
              <component :is="Component" />
            </transition>
          </router-view>
        </div>
      </el-main>
    </el-container>

    <el-drawer
      v-model="mobileNavVisible"
      direction="ltr"
      size="200px"
      :with-header="false"
      class="client-mobile-drawer"
    >
      <div class="drawer-shell">
        <div class="brand-panel mobile" @click="go('/client/dashboard')">
          <div class="brand-lockup-wrap">
            <img
              :src="sidebarLogo"
              :alt="appStore.siteName"
              class="brand-lockup"
            />
          </div>
        </div>

        <el-scrollbar class="sidebar-scroll mobile">
          <section
            v-for="section in navigationSections"
            :key="section.label"
            class="menu-section"
          >
            <p class="menu-section-title">{{ section.label }}</p>

            <el-menu
              :default-active="activeMenu"
              router
              class="sidebar-menu"
              @select="handleMenuSelect"
            >
              <el-menu-item
                v-for="item in section.items"
                :key="item.index"
                :index="item.index"
              >
                <el-icon><component :is="item.icon" /></el-icon>
                <template #title>{{ item.title }}</template>
              </el-menu-item>
            </el-menu>
          </section>
        </el-scrollbar>
      </div>
    </el-drawer>
  </el-container>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAppStore } from '@/stores/app'
import { useUserStore } from '@/stores/user'
import {
  ArrowDown,
  Bell,
  ChatDotRound,
  CircleCheck,
  Document,
  Expand,
  Fold,
  Grid,
  List,
  Menu,
  Monitor,
  Odometer,
  QuestionFilled,
  Share,
  SwitchButton,
  Tickets,
  Tools,
  User,
  Wallet,
} from '@element-plus/icons-vue'

const route = useRoute()
const router = useRouter()
const appStore = useAppStore()
const userStore = useUserStore()
const isMobile = ref(false)
const isTablet = ref(false)
const mobileNavVisible = ref(false)
const sidebarLogo = '/branding/logo.svg'
const sidebarCompactLogo = '/branding/logo1.svg'

const navigationSections = [
  {
    label: '总览',
    items: [
      { index: '/client/dashboard', title: '控制台', icon: Odometer },
      { index: '/client/services', title: '我的服务', icon: Monitor },
      { index: '/products', title: '产品目录', icon: Grid },
    ],
  },
  {
    label: '财务',
    items: [
      { index: '/client/orders', title: '订单记录', icon: Document },
      { index: '/client/invoices', title: '账单记录', icon: List },
      { index: '/client/payments', title: '充值记录', icon: Wallet },
      { index: '/client/recharge', title: '账户充值', icon: Wallet },
      { index: '/client/coupons', title: '优惠券中心', icon: Tickets },
      { index: '/client/referral', title: '推荐奖励', icon: Share },
    ],
  },
  {
    label: '支持',
    items: [
      { index: '/client/tickets', title: '工单支持', icon: ChatDotRound },
      // { index: '/client/tools', title: '管理工具', icon: Tools },
      { index: '/client/notices', title: '系统公告', icon: Bell },
      { index: '/client/help', title: '帮助中心', icon: QuestionFilled },
    ],
  },
  {
    label: '账户',
    items: [
      { index: '/client/verification', title: '实名认证', icon: CircleCheck },
      { index: '/client/profile', title: '个人资料', icon: User },
    ],
  },
]

const activeMenu = computed(() => {
  if (route.path === '/client/order/create') {
    return '/products'
  }

  if (route.path.startsWith('/client/invoices')) {
    return '/client/invoices'
  }

  if (route.path.startsWith('/client/notices')) {
    return '/client/notices'
  }

  if (route.path.startsWith('/client/coupons')) {
    return '/client/coupons'
  }

  if (route.path.startsWith('/client/help')) {
    return '/client/help'
  }

  if (route.path.startsWith('/client/tools')) {
    return '/client/tools'
  }

  if (route.path.startsWith('/products')) {
    return '/products'
  }

  return route.path
})

const breadcrumbItems = computed(() => (
  route.matched
    .filter((item) => item.path.startsWith('/client') && item.path !== '/client' && item.meta?.title)
    .map((item) => ({
      path: item.path,
      title: item.meta.title,
    }))
))

const currentPageTitle = computed(() => breadcrumbItems.value.at(-1)?.title || '客户中心')
const displayName = computed(() => userStore.info?.nickname || userStore.info?.email || '客户账户')
const userInitial = computed(() => displayName.value.trim().slice(0, 1).toUpperCase() || 'U')
const balanceText = computed(() => `余额 ¥${Number(userStore.info?.balance || 0).toFixed(2)}`)
const supportQqGroup = computed(() => appStore.serviceQqGroup)

const effectiveSidebarCollapsed = computed(() => isTablet.value || appStore.sidebarCollapsed)
const sidebarWidth = computed(() => (effectiveSidebarCollapsed.value ? '44px' : '148px'))

function updateViewport() {
  if (typeof window === 'undefined') {
    return
  }

  const nextIsMobile = window.innerWidth <= 900
  const nextIsTablet = !nextIsMobile && window.innerWidth <= 1180
  isMobile.value = nextIsMobile
  isTablet.value = nextIsTablet

  if (!nextIsMobile && !nextIsTablet) {
    mobileNavVisible.value = false
  }
}

function toggleSidebar() {
  if (isMobile.value || isTablet.value) {
    mobileNavVisible.value = true
    return
  }

  appStore.toggleSidebar()
}

function handleMenuSelect() {
  if (isMobile.value || isTablet.value) {
    mobileNavVisible.value = false
  }
}

function go(path) {
  mobileNavVisible.value = false
  router.push(path)
}

function handleUserAction(command) {
  if (command === 'logout') {
    userStore.logout()
    router.push('/client/login')
    return
  }

  if (command === 'profile') {
    go('/client/profile')
    return
  }

  if (command === 'verification') {
    go('/client/verification')
    return
  }

  if (command === 'recharge') {
    go('/client/recharge')
  }
}

watch(() => route.fullPath, () => {
  mobileNavVisible.value = false
})

onMounted(() => {
  updateViewport()
  window.addEventListener('resize', updateViewport)
})

onBeforeUnmount(() => {
  window.removeEventListener('resize', updateViewport)
})

</script>

<style lang="scss" scoped>
.client-shell {
  height: 100vh;
  min-height: 100vh;
  overflow: hidden;
  background: $bg-color;
}

.client-sidebar {
  position: sticky;
  top: 0;
  height: 100vh;
  flex-shrink: 0;
  border-right: 1px solid $divider-color;
  background: $bg-color-card;
}

.sidebar-shell,
.drawer-shell {
  display: flex;
  flex-direction: column;
  height: 100%;
}

.brand-panel {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  min-height: 48px;
  padding: 10px 12px 6px;
  border: none;
  background: $bg-color-card;
  text-align: left;
  cursor: pointer;
}

.brand-panel.mobile {
  justify-content: flex-start;
  min-height: auto;
  padding: 0 0 8px;
}

.brand-lockup-wrap {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  width: 100%;
  min-width: 0;
}

.brand-lockup {
  display: block;
  width: min(160px, 100%);
  height: auto;
  max-height: 36px;
  object-fit: contain;
  object-position: left center;
}

.brand-mini-logo {
  display: block;
  width: 30px;
  height: 30px;
  object-fit: contain;
}

.brand-subtitle {
  margin-top: 4px;
  color: $text-color-secondary;
  font-size: 12px;
  line-height: 1.2;
}
.sidebar-scroll {
  flex: 1;
  padding: 6px 8px 10px;
}

.sidebar-scroll.mobile {
  padding-right: 0;
  padding-left: 0;
}

.menu-section + .menu-section {
  margin-top: 8px;
}

.menu-section-title {
  padding: 0 8px 4px;
  color: $text-color-placeholder;
  font-size: 11px;
  font-weight: 600;
}

.sidebar-menu :deep(.el-menu-item) {
  height: 36px;
  margin-bottom: 0;
  padding: 0 10px;
  border-radius: 6px;
  color: $text-color-secondary;
  font-size: 13px;
  line-height: 36px;
}

.sidebar-menu :deep(.el-menu-item:hover) {
  background: $bg-color-soft;
}

.sidebar-menu :deep(.el-menu-item.is-active) {
  color: $color-primary;
  background: $color-primary-soft;
  font-weight: 600;
}

.sidebar-menu :deep(.el-menu-item .el-icon) {
  margin-right: 8px;
  font-size: 16px;
}

.client-main-shell {
  display: flex;
  flex-direction: column;
  min-width: 0;
  height: 100vh;
  min-height: 0;
  overflow: hidden;
}

.client-topbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  height: 64px;
  padding: 0 20px;
  border-bottom: 1px solid $divider-color;
  background: $bg-color-card;
}

.topbar-left,
.topbar-right {
  display: flex;
  align-items: center;
  gap: 14px;
}

.shell-trigger,
.account-entry {
  display: inline-flex;
  align-items: center;
  border: none;
  background: none;
  cursor: pointer;
}

.shell-trigger {
  justify-content: center;
  width: 36px;
  height: 36px;
  border: 1px solid $border-color;
  background: $bg-color-card;
  color: $text-color-secondary;
}

.shell-trigger:hover {
  color: $color-primary;
  border-color: $color-primary;
}

.page-context {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.page-breadcrumb :deep(.el-breadcrumb__inner),
.page-breadcrumb :deep(.el-breadcrumb__separator),
.page-breadcrumb :deep(.el-breadcrumb__inner a) {
  color: $text-color-placeholder;
}

.page-context strong {
  color: $text-color-primary;
  font-size: 20px;
  font-weight: 700;
}

.site-copy {
  padding: 0 12px;
  border-left: 1px solid $divider-color;
}

.site-copy span {
  font-size: 13px;
  color: $text-color-secondary;
}

.account-entry {
  gap: 10px;
  padding: 2px 0 2px 2px;
  color: $text-color-secondary;
  transition:
    color 0.15s ease,
    transform 0.15s ease;
}

.account-entry:hover {
  color: $color-primary;
}

.account-avatar {
  background: $color-primary;
  color: $text-color-inverse;
  font-weight: 700;
}

.account-copy {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
}

.account-copy strong {
  color: $text-color-primary;
  font-size: 13px;
  font-weight: 600;
}

.account-copy small {
  color: $text-color-placeholder;
  font-size: 12px;
}

.account-entry-arrow {
  font-size: 14px;
  color: currentColor;
}

.client-main {
  min-height: 0;
  flex: 1;
  padding: 20px;
  overflow-y: auto;
  background: $bg-color;
}

.client-stage {
  min-height: 100%;
}

.account-meta {
  padding-bottom: 12px;
  border-bottom: 1px solid $divider-color;
}

.account-meta strong {
  display: block;
  font-size: 15px;
  color: $text-color-primary;
}

.account-meta p {
  margin-top: 6px;
  font-size: 12px;
  color: $text-color-secondary;
}

.account-meta small {
  display: block;
  margin-top: 6px;
  color: $text-color-placeholder;
  font-size: 12px;
}

:deep(.client-account-dropdown .el-dropdown-menu) {
  min-width: 220px;
  padding: 6px;
}

:deep(.client-account-dropdown .el-dropdown-menu__item) {
  border-radius: $sm-border-radius;
}

:deep(.client-account-dropdown .el-dropdown-menu__item.is-disabled) {
  opacity: 1;
}

:deep(.client-account-dropdown .el-dropdown-menu__item.is-disabled:hover) {
  background: transparent;
}

:deep(.client-account-dropdown .el-dropdown-menu__item--divided::before) {
  margin: 0 0 6px;
}

.account-overview-item {
  align-items: stretch;
  min-height: auto;
  padding: 10px 12px;
  cursor: default;
}

.account-menu-item {
  gap: 10px;
  min-width: 0;
  color: $text-color-primary;
}

.account-menu-item.danger {
  color: $color-danger;
}

.drawer-shell {
  display: flex;
  flex-direction: column;
  height: 100%;
  padding: 12px 0;
  background: $bg-color-card;
}

.client-mobile-drawer :deep(.el-drawer__body) {
  padding: 0 !important;
}

.page-fade-enter-active,
.page-fade-leave-active {
  transition: opacity $motion-fast ease;
}

.page-fade-enter-from,
.page-fade-leave-to {
  opacity: 0;
}

.client-shell--tablet {
  .brand-panel {
    min-height: 56px;
    padding: 10px 8px;
  }

  .sidebar-scroll {
    padding: 8px 6px 12px;
  }

  .client-topbar {
    gap: 12px;
    padding: 0 16px;
  }

  .client-main {
    padding: 16px;
  }

  .topbar-right {
    gap: 10px;
  }

  .site-copy {
    display: none;
  }

  .account-copy small {
    display: none;
  }

  .page-context strong {
    font-size: 18px;
  }
}

@media (max-width: 1100px) {
  .client-topbar {
    height: auto;
    padding: 14px 20px;
  }

  .topbar-left,
  .topbar-right {
    flex-wrap: wrap;
  }
}

@media (max-width: 900px) {
  .site-copy {
    display: none;
  }
}

@media (max-width: 768px) {
  .client-main {
    padding: 16px;
  }
}

@media (max-width: 640px) {
  .client-topbar,
  .client-main {
    padding-right: 14px;
    padding-left: 14px;
  }

  .client-main {
    padding-top: 14px;
    padding-bottom: 14px;
  }

  .page-context strong {
    font-size: 16px;
  }

  .account-copy {
    display: none;
  }
}
</style>
