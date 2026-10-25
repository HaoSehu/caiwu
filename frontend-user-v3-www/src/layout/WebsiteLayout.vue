<template>
  <div class="website-layout">
    <a class="skip-to-content" href="#main-content">跳到主内容</a>
    <header
      class="site-header"
      :class="{ scrolled: headerScrolled }"
      @keydown.esc="closeMegaMenu"
    >
      <div class="container header-bar">
        <router-link to="/" class="logo" :aria-label="appStore.siteName">
          <img
            :src="logoSrc"
            :alt="appStore.siteName"
            class="logo-image"
            loading="eager"
            @error="handleLogoError"
          />
          <span v-if="logoLoadFailed" class="logo-fallback">{{
            appStore.siteName
          }}</span>
        </router-link>

        <nav
          class="main-nav"
          @mouseleave="scheduleCloseMegaMenu()"
          aria-label="主导航"
        >
          <router-link
            v-for="item in navigationItems"
            :key="item.to"
            :to="item.to"
            class="main-nav__link"
            :class="{ 'is-active': isNavActive(item) }"
            @mouseenter="handleNavHover(item)"
            @focus="item.menuId && canHoverMegaMenu && openMegaMenu(item.menuId)"
            :aria-haspopup="item.menuId ? 'true' : undefined"
            :aria-expanded="
              item.menuId ? activeMenuId === item.menuId : undefined
            "
            :aria-controls="item.menuId ? 'mega-menu' : undefined"
          >
            <span>{{ item.label }}</span>
            <el-icon v-if="item.menuId" class="main-nav__arrow"
              ><ArrowDown
            /></el-icon>
          </router-link>
        </nav>

        <transition name="mega-menu">
          <div
            v-if="activeMenuId"
            id="mega-menu"
            class="mega-menu"
            @mouseenter="keepMegaMenu()"
            @mouseleave="scheduleCloseMegaMenu()"
          >
            <div class="mega-menu__inner container">
              <template v-if="activeMenuId === 'products'">
                <div class="mega-menu__types" role="group" aria-label="产品目录">
                  <span class="mega-menu__level-label">产品目录</span>
                  <button
                    v-for="type in navProductTypes"
                    :key="type.value"
                    type="button"
                    class="mega-type-btn"
                    :class="{ active: navActiveTypeValue === type.value }"
                    @mouseenter="navActivateType(type.value)"
                    @focus="navActivateType(type.value)"
                    @click="navActivateType(type.value)"
                  >
                    <span class="mega-type-btn__label">{{ type.label }}</span>
                    <span class="mega-type-btn__count">{{
                      type.product_count
                    }}</span>
                  </button>
                </div>
                <div class="mega-menu__groups" role="group" aria-label="地区">
                  <span class="mega-menu__level-label">地区</span>
                  <router-link
                    v-for="group in navActiveGroups"
                    :key="group.id"
                    :to="resolveGroupPath(group)"
                    class="mega-group-card"
                  >
                    <span class="mega-group-card__name">{{ group.name }}</span>
                    <span class="mega-group-card__desc">{{
                      group.slogan || `${group.product_count} 款产品`
                    }}</span>
                  </router-link>
                  <div
                    v-if="!navActiveGroups.length && !navLoading"
                    class="mega-menu__empty"
                  >
                    暂无产品分类
                  </div>
                </div>
              </template>

              <template v-else-if="activeMenuId === 'notices'">
                <div class="mega-menu__types">
                  <button
                    type="button"
                    class="mega-type-btn"
                    :class="{ active: !navNoticesActiveCategory }"
                    @mouseenter="navNoticesActivateCategory(null)"
                    @focus="navNoticesActivateCategory(null)"
                    @click="navNoticesActivateCategory(null)"
                  >
                    <span class="mega-type-btn__label">全部公告</span>
                    <span class="mega-type-btn__count">{{
                      navNoticesItems.length
                    }}</span>
                  </button>
                  <button
                    v-for="cat in navNoticesCategories"
                    :key="cat.label"
                    type="button"
                    class="mega-type-btn"
                    :class="{ active: navNoticesActiveCategory === cat.label }"
                    @mouseenter="navNoticesActivateCategory(cat.label)"
                    @focus="navNoticesActivateCategory(cat.label)"
                    @click="navNoticesActivateCategory(cat.label)"
                  >
                    <span class="mega-type-btn__label">{{ cat.label }}</span>
                    <span class="mega-type-btn__count">{{ cat.count }}</span>
                  </button>
                  <router-link to="/notices" class="mega-type-more"
                    >查看全部 →</router-link
                  >
                </div>
                <div class="mega-menu__groups">
                  <router-link
                    v-for="item in navNoticesFiltered"
                    :key="item.id"
                    :to="`/notices/${item.id}`"
                    class="mega-group-card"
                  >
                    <span class="mega-group-card__name">{{ item.title }}</span>
                    <span class="mega-group-card__desc">{{
                      item.summary || formatDate(item.publish_at)
                    }}</span>
                  </router-link>
                  <div
                    v-if="!navNoticesFiltered.length && !navNoticesLoading"
                    class="mega-menu__empty"
                  >
                    暂无公告
                  </div>
                </div>
              </template>

              <template v-else-if="activeMenuId === 'help'">
                <div class="mega-menu__types">
                  <button
                    type="button"
                    class="mega-type-btn"
                    :class="{ active: !navHelpActiveCategory }"
                    @mouseenter="navHelpActivateCategory(null)"
                    @focus="navHelpActivateCategory(null)"
                    @click="navHelpActivateCategory(null)"
                  >
                    <span class="mega-type-btn__label">全部文档</span>
                    <span class="mega-type-btn__count">{{
                      navHelpItems.length
                    }}</span>
                  </button>
                  <button
                    v-for="cat in navHelpCategories"
                    :key="cat.label"
                    type="button"
                    class="mega-type-btn"
                    :class="{ active: navHelpActiveCategory === cat.label }"
                    @mouseenter="navHelpActivateCategory(cat.label)"
                    @focus="navHelpActivateCategory(cat.label)"
                    @click="navHelpActivateCategory(cat.label)"
                  >
                    <span class="mega-type-btn__label">{{ cat.label }}</span>
                    <span class="mega-type-btn__count">{{ cat.count }}</span>
                  </button>
                  <router-link to="/help" class="mega-type-more"
                    >查看全部 →</router-link
                  >
                </div>
                <div class="mega-menu__groups">
                  <router-link
                    v-for="item in navHelpFiltered"
                    :key="item.id"
                    :to="`/help/${item.id}`"
                    class="mega-group-card"
                  >
                    <span class="mega-group-card__name">{{ item.title }}</span>
                    <span class="mega-group-card__desc">{{
                      item.summary || "查看详情"
                    }}</span>
                  </router-link>
                  <div
                    v-if="!navHelpFiltered.length && !navHelpLoading"
                    class="mega-menu__empty"
                  >
                    暂无文档
                  </div>
                </div>
              </template>

              <template v-else-if="activeMenuId === 'about'">
                <div class="mega-menu__types">
                  <div class="mega-type-heading">支持</div>
                  <div class="mega-type-desc">联系方式与常用服务入口</div>
                </div>
                <div class="mega-menu__groups">
                  <template v-for="link in aboutQuickLinks" :key="link.to">
                    <a
                      v-if="isConsolePath(link.to)"
                      :href="consoleUrl(link.to)"
                      class="mega-group-card"
                    >
                      <span class="mega-group-card__name">{{
                        link.title
                      }}</span>
                      <span class="mega-group-card__desc">{{ link.desc }}</span>
                    </a>
                    <router-link v-else :to="link.to" class="mega-group-card">
                      <span class="mega-group-card__name">{{
                        link.title
                      }}</span>
                      <span class="mega-group-card__desc">{{ link.desc }}</span>
                    </router-link>
                  </template>
                </div>
              </template>
            </div>
          </div>
        </transition>

        <div class="header-actions">
          <div
            v-if="userStore.isLoggedIn"
            ref="userMenuTriggerRef"
            class="user-menu"
          >
            <button
              type="button"
              class="header-user-trigger"
              :class="{ 'is-active': userMenuOpen }"
              aria-label="用户菜单"
              :aria-expanded="userMenuOpen"
              @click="toggleUserMenu"
            >
              <span class="user-avatar-initial">{{ userAvatarInitial }}</span>
              <span class="header-user-name">{{ userDisplayName }}</span>
              <el-icon class="header-user-arrow"><ArrowDown /></el-icon>
            </button>
            <transition name="user-menu-fade">
              <div v-if="userMenuOpen" class="user-menu-panel" role="menu">
                <button
                  type="button"
                  role="menuitem"
                  class="user-menu-item"
                  @click="onUserMenuCommand(consoleUrl('/client/dashboard'))"
                >
                  <el-icon><Monitor /></el-icon>控制台
                </button>
                <button
                  type="button"
                  role="menuitem"
                  class="user-menu-item"
                  @click="onUserMenuCommand(consoleUrl('/client/services'))"
                >
                  <el-icon><Box /></el-icon>我的服务
                </button>
                <div class="user-menu-divider"></div>
                <button
                  type="button"
                  role="menuitem"
                  class="user-menu-item"
                  @click="onUserMenuCommand(consoleUrl('/client/profile'))"
                >
                  <el-icon><User /></el-icon>个人资料
                </button>
                <button
                  type="button"
                  role="menuitem"
                  class="user-menu-item"
                  @click="onUserMenuCommand(consoleUrl('/client/recharge'))"
                >
                  <el-icon><Wallet /></el-icon>账户充值
                </button>
                <div class="user-menu-divider"></div>
                <button
                  type="button"
                  role="menuitem"
                  class="user-menu-item is-danger"
                  @click="onUserMenuCommand('logout')"
                >
                  <el-icon><CircleClose /></el-icon>退出登录
                </button>
              </div>
            </transition>
          </div>
          <template v-if="!userStore.isLoggedIn">
            <a :href="consoleUrl('/client/login')" class="header-link">登录</a>
            <a :href="consoleUrl('/client/register')" class="header-register"
              >免费注册</a
            >
          </template>
        </div>

        <div
          v-if="userStore.isLoggedIn"
          ref="mobileUserMenuTriggerRef"
          class="mobile-user-menu"
        >
          <button
            type="button"
            class="mobile-user-icon is-avatar"
            aria-label="用户菜单"
            :aria-expanded="mobileUserMenuOpen"
            @click="toggleMobileUserMenu"
          >
            <span class="user-avatar-initial">{{ userAvatarInitial }}</span>
          </button>
          <transition name="user-menu-fade">
            <div v-if="mobileUserMenuOpen" class="user-menu-panel" role="menu">
              <button
                type="button"
                role="menuitem"
                class="user-menu-item"
                @click="onUserMenuCommand(consoleUrl('/client/dashboard'))"
              >
                <el-icon><Monitor /></el-icon>控制台
              </button>
              <button
                type="button"
                role="menuitem"
                class="user-menu-item"
                @click="onUserMenuCommand(consoleUrl('/client/services'))"
              >
                <el-icon><Box /></el-icon>我的服务
              </button>
              <div class="user-menu-divider"></div>
              <button
                type="button"
                role="menuitem"
                class="user-menu-item"
                @click="onUserMenuCommand(consoleUrl('/client/profile'))"
              >
                <el-icon><User /></el-icon>个人资料
              </button>
              <button
                type="button"
                role="menuitem"
                class="user-menu-item"
                @click="onUserMenuCommand(consoleUrl('/client/recharge'))"
              >
                <el-icon><Wallet /></el-icon>账户充值
              </button>
              <div class="user-menu-divider"></div>
              <button
                type="button"
                role="menuitem"
                class="user-menu-item is-danger"
                @click="onUserMenuCommand('logout')"
              >
                <el-icon><CircleClose /></el-icon>退出登录
              </button>
            </div>
          </transition>
        </div>
        <a v-else :href="consoleUrl('/client/login')" class="mobile-user-icon">
          <el-icon :size="20"><User /></el-icon>
        </a>
        <button
          ref="mobileMenuToggleRef"
          type="button"
          class="mobile-menu-toggle"
          :class="{ 'is-open': mobileNavVisible }"
          :aria-label="mobileNavVisible ? '关闭菜单' : '打开菜单'"
          :aria-expanded="mobileNavVisible"
          aria-controls="mobile-menu-panel"
          @click="mobileNavVisible = !mobileNavVisible"
        >
          <span class="hamburger">
            <span class="hamburger-line" />
            <span class="hamburger-line" />
            <span class="hamburger-line" />
          </span>
        </button>
      </div>
    </header>

    <transition name="mobile-menu-mask">
      <div
        v-if="isMobile && mobileNavVisible"
        class="mobile-menu-mask"
        aria-hidden="true"
        @click="mobileNavVisible = false"
      />
    </transition>

    <transition name="mobile-menu-panel">
      <div
        v-if="isMobile && mobileNavVisible"
        id="mobile-menu-panel"
        class="mobile-menu-panel"
        role="dialog"
        aria-modal="true"
        aria-label="移动端菜单"
        @keydown.esc="closeMobileMenu"
      >
        <div class="mobile-menu-two-col">
          <div class="mobile-menu-col mobile-menu-col--left">
            <template
              v-for="item in navigationItems"
              :key="`mobile-${item.to}`"
            >
              <button
                v-if="item.menuId"
                type="button"
                class="mobile-first-level-item"
                :class="{ active: mobileActiveFirstLevel === item.menuId }"
                @click="mobileActiveFirstLevel = item.menuId"
              >
                <span class="mobile-first-level-label">{{ item.label }}</span>
              </button>
              <router-link
                v-else
                :to="item.to"
                class="mobile-first-level-item"
                :class="{ active: isMobileMenuNavActive(item) }"
                @click="mobileNavVisible = false"
              >
                <span class="mobile-first-level-label">{{ item.label }}</span>
              </router-link>
            </template>
            <div class="mobile-left-bottom">
              <template v-if="userStore.isLoggedIn">
                <a
                  :href="consoleUrl('/client/dashboard')"
                  class="mobile-left-login-btn"
                  >控制台</a
                >
              </template>
              <template v-else>
                <a
                  :href="consoleUrl('/client/login')"
                  class="mobile-left-login-btn"
                  >登录 / 注册</a
                >
              </template>
            </div>
          </div>

          <div class="mobile-menu-col mobile-menu-col--right">
            <template v-if="mobileActiveFirstLevel === 'products'">
              <template v-if="navProductTypes.length">
                <div class="mobile-menu-section-label">产品目录</div>
                <div
                  v-for="type in navProductTypes"
                  :key="`m-type-${type.value}`"
                  class="mobile-second-group"
                >
                  <button
                    type="button"
                    class="mobile-second-level-item"
                    :class="{ active: mobileExpandedType === type.value }"
                    @click="
                      mobileExpandedType =
                        mobileExpandedType === type.value ? '' : type.value;
                      navLoadGroupsForType(type.value);
                    "
                  >
                    <span class="mobile-second-level-label">{{
                      type.label
                    }}</span>
                    <span class="mobile-second-level-right">
                      <svg
                        class="mobile-second-level-arrow"
                        :class="{ expanded: mobileExpandedType === type.value }"
                        viewBox="0 0 10 6"
                        fill="none"
                        width="10"
                        height="6"
                        aria-hidden="true"
                      >
                        <path
                          d="M1 1l4 4 4-4"
                          stroke="currentColor"
                          stroke-width="1.5"
                          stroke-linecap="round"
                          stroke-linejoin="round"
                        />
                      </svg>
                    </span>
                  </button>
                  <div
                    v-if="
                      mobileExpandedType === type.value &&
                      navGetGroupsForType(type.value).length
                    "
                    class="mobile-third-level-list"
                  >
                    <div class="mobile-menu-section-label">地区</div>
                    <router-link
                      v-for="group in navGetGroupsForType(type.value)"
                      :key="`m-group-${group.id}`"
                      :to="resolveGroupPath(group)"
                      class="mobile-third-level-item"
                      @click="closeMobileMenu()"
                    >
                      {{ group.name }}
                    </router-link>
                  </div>
                </div>
              </template>
              <div v-else-if="navLoading" class="mobile-menu-loading">
                加载中…
              </div>
              <div v-else class="mobile-menu-empty">暂无产品分类</div>
            </template>

            <template v-else-if="mobileActiveFirstLevel === 'notices'">
              <template v-if="navNoticesCategories.length">
                <div
                  v-for="cat in navNoticesCategories"
                  :key="`m-ncat-${cat.label}`"
                  class="mobile-second-group"
                >
                  <button
                    type="button"
                    class="mobile-second-level-item"
                    :class="{ active: navNoticesActiveCategory === cat.label }"
                    @click="navNoticesActivateCategory(cat.label)"
                  >
                    <span class="mobile-second-level-label">{{
                      cat.label
                    }}</span>
                    <span class="mobile-second-level-right">
                      <svg
                        class="mobile-second-level-arrow"
                        :class="{
                          expanded: navNoticesActiveCategory === cat.label,
                        }"
                        viewBox="0 0 10 6"
                        fill="none"
                        width="10"
                        height="6"
                        aria-hidden="true"
                      >
                        <path
                          d="M1 1l4 4 4-4"
                          stroke="currentColor"
                          stroke-width="1.5"
                          stroke-linecap="round"
                          stroke-linejoin="round"
                        />
                      </svg>
                    </span>
                  </button>
                  <div
                    v-if="
                      navNoticesActiveCategory === cat.label &&
                      navNoticesFiltered.length
                    "
                    class="mobile-third-level-list"
                  >
                    <router-link
                      v-for="item in navNoticesFiltered"
                      :key="`m-ni-${item.id}`"
                      :to="`/notices/${item.id}`"
                      class="mobile-third-level-item"
                      @click="closeMobileMenu()"
                    >
                      {{ item.title }}
                    </router-link>
                  </div>
                </div>
                <router-link
                  to="/notices"
                  class="mobile-view-all"
                  @click="closeMobileMenu()"
                  >查看全部公告 →</router-link
                >
              </template>
              <div v-else-if="navNoticesLoading" class="mobile-menu-loading">
                加载中…
              </div>
              <div v-else class="mobile-menu-empty">暂无公告</div>
            </template>

            <template v-else-if="mobileActiveFirstLevel === 'help'">
              <template v-if="navHelpCategories.length">
                <div
                  v-for="cat in navHelpCategories"
                  :key="`m-hcat-${cat.label}`"
                  class="mobile-second-group"
                >
                  <button
                    type="button"
                    class="mobile-second-level-item"
                    :class="{ active: navHelpActiveCategory === cat.label }"
                    @click="navHelpActivateCategory(cat.label)"
                  >
                    <span class="mobile-second-level-label">{{
                      cat.label
                    }}</span>
                    <span class="mobile-second-level-right">
                      <svg
                        class="mobile-second-level-arrow"
                        :class="{
                          expanded: navHelpActiveCategory === cat.label,
                        }"
                        viewBox="0 0 10 6"
                        fill="none"
                        width="10"
                        height="6"
                        aria-hidden="true"
                      >
                        <path
                          d="M1 1l4 4 4-4"
                          stroke="currentColor"
                          stroke-width="1.5"
                          stroke-linecap="round"
                          stroke-linejoin="round"
                        />
                      </svg>
                    </span>
                  </button>
                  <div
                    v-if="
                      navHelpActiveCategory === cat.label &&
                      navHelpFiltered.length
                    "
                    class="mobile-third-level-list"
                  >
                    <router-link
                      v-for="item in navHelpFiltered"
                      :key="`m-hi-${item.id}`"
                      :to="`/help/${item.id}`"
                      class="mobile-third-level-item"
                      @click="closeMobileMenu()"
                    >
                      {{ item.title }}
                    </router-link>
                  </div>
                </div>
                <router-link
                  to="/help"
                  class="mobile-view-all"
                  @click="closeMobileMenu()"
                  >查看全部文档 →</router-link
                >
              </template>
              <div v-else-if="navHelpLoading" class="mobile-menu-loading">
                加载中…
              </div>
              <div v-else class="mobile-menu-empty">暂无文档</div>
            </template>

            <template v-else-if="mobileActiveFirstLevel === 'about'">
              <template
                v-for="link in aboutQuickLinks"
                :key="`m-about-${link.to}`"
              >
                <a
                  v-if="isConsolePath(link.to)"
                  :href="consoleUrl(link.to)"
                  class="mobile-third-level-item mobile-third-level-item--link"
                >
                  {{ link.title }}
                </a>
                <router-link
                  v-else
                  :to="link.to"
                  class="mobile-third-level-item mobile-third-level-item--link"
                  @click="closeMobileMenu()"
                >
                  {{ link.title }}
                </router-link>
              </template>
            </template>
          </div>
        </div>
      </div>
    </transition>

    <main id="main-content" class="site-main">
      <router-view v-slot="{ Component }">
        <transition name="page-fade" mode="out-in">
          <component :is="Component" />
        </transition>
      </router-view>
    </main>

    <WebsiteFooter />
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from "vue";
import { useRoute } from "vue-router";
import { ElIcon } from "element-plus/es/components/icon/index.mjs";
import {
  ArrowDown,
  Box,
  CircleClose,
  Monitor,
  User,
  Wallet,
} from "@element-plus/icons-vue";
import { useAppStore } from "@/app/stores/app";
import { useUserStore } from "@/app/stores/user";
import { buildConsoleUrl, isConsolePath } from "@/utils/consoleUrl";
import WebsiteFooter from "./components/WebsiteFooter.vue";
import { useUserMenu } from "./composables/useUserMenu";
import { useMegaMenu } from "./composables/useMegaMenu";
import { useMobileNav } from "./composables/useMobileNav";
import { useHeaderScroll } from "./composables/useHeaderScroll";
import { navigationItems, aboutQuickLinks } from "./navigationData";
import { useNavProductMenu } from "./useNavProductMenu";
import { useNavContentMenu } from "./useNavContentMenu";

const route = useRoute();
const appStore = useAppStore();
const userStore = useUserStore();

// 登录态恢复放到 onMounted，避免 setup 顶层产生路由跳转副作用
// 跨端口登录传递仍走 query _token（与控制台 v4-console 约定保持一致），收到后立即持久化并从 URL 剥离
onMounted(() => {
  // 仅在本地已有 token 时才拉取用户信息，避免未登录时触发 401
  userStore.fetchUserInfo().catch(() => {});
});

const navProductMenu = useNavProductMenu();
const {
  productTypes: navProductTypes,
  activeTypeValue: navActiveTypeValue,
  activeGroups: navActiveGroups,
  getGroupsForType: navGetGroupsForType,
  loading: navLoading,
  activateType: navActivateType,
  loadGroupsForType: navLoadGroupsForType,
  init: navProductInit,
} = navProductMenu;
const navNoticesMenu = useNavContentMenu("notice");
const {
  items: navNoticesItems,
  loading: navNoticesLoading,
  categories: navNoticesCategories,
  activeCategory: navNoticesActiveCategory,
  filteredItems: navNoticesFiltered,
  activateCategory: navNoticesActivateCategory,
} = navNoticesMenu;
const navHelpMenu = useNavContentMenu("help");
const {
  items: navHelpItems,
  loading: navHelpLoading,
  categories: navHelpCategories,
  activeCategory: navHelpActiveCategory,
  filteredItems: navHelpFiltered,
  activateCategory: navHelpActivateCategory,
} = navHelpMenu;

const logoLoadFailed = ref(false);

// mega menu 依赖 hover/focus 悬停语义，仅在具备鼠标悬停能力的设备启用：
// 触屏设备（hover: none）tap 一级导航会同时触发 mouseenter/focus 与路由跳转，
// 弹层与跳转叠加行为混乱，此时直接跳转列表页（993~1180 平板区间同样受益）
const canHoverMegaMenu =
  typeof window === "undefined"
    ? false
    : Boolean(
        window.matchMedia?.("(hover: hover) and (pointer: fine)")?.matches,
      );

// 用户菜单：桌面下拉 + 移动端用户入口
const {
  userAvatarInitial,
  userDisplayName,
  userMenuOpen,
  mobileUserMenuOpen,
  userMenuTriggerRef,
  mobileUserMenuTriggerRef,
  toggleUserMenu,
  toggleMobileUserMenu,
  onUserMenuCommand,
  closeUserMenusOnOutside,
  closeUserMenusOnEscape,
} = useUserMenu(userStore);

// mega menu：只负责开合与计时，弹层内容仍由两个 nav menu 提供
const {
  activeMenuId,
  openMegaMenu,
  closeMegaMenu,
  handleNavHover,
  keepMegaMenu,
  scheduleCloseMegaMenu,
  clearMegaMenuTimers,
} = useMegaMenu({
  canHoverMegaMenu,
  initProducts: navProductInit,
  initNotices: navNoticesMenu.init,
  initHelp: navHelpMenu.init,
});

// 移动端抽屉：可见性、二级展开态、焦点陷阱与滚动锁
const {
  mobileNavVisible,
  mobileActiveFirstLevel,
  mobileExpandedType,
  mobileMenuToggleRef,
  closeMobileMenu,
  isMobileMenuNavActive,
  handleMobileMenuKeydown,
} = useMobileNav({
  initProducts: navProductInit,
  initNotices: navNoticesMenu.init,
  initHelp: navHelpMenu.init,
  isNavItemActive: isNavActive,
});

// 头部滚动状态与响应式断点
const { isMobile, headerScrolled, handleScroll, handleResize, disposeHeaderScroll } =
  useHeaderScroll({ closeMobileMenu, closeMegaMenu });

function resolveGroupPath(group) {
  const typeCode = String(group.first_product_group_code || "");
  const groupId = Number(group.id || 0);
  if (!typeCode || !groupId) {
    return "/products";
  }
  return `/products?type=${encodeURIComponent(typeCode)}&group=${groupId}`;
}

function formatDate(value) {
  if (!value) return "";
  const str = String(value);
  return str.slice(0, 10);
}

// 优先使用站点品牌配置的 logo（管理端可上传），失败时模板回退到文字标识
const logoSrc = computed(() => appStore.siteLogo);

function consoleUrl(path) {
  return buildConsoleUrl(path);
}

function isNavActive(item) {
  if (item.menuId === "products" && route.meta?.seoLanding) {
    return true;
  }
  return item.match.includes(route.name);
}

function handleLogoError() {
  logoLoadFailed.value = true;
}

watch(
  () => route.fullPath,
  () => {
    closeMobileMenu();
    activeMenuId.value = null;
    mobileExpandedType.value = "";
  },
);

onMounted(() => {
  handleScroll();
  handleResize();
  window.addEventListener("scroll", handleScroll, { passive: true });
  window.addEventListener("resize", handleResize, { passive: true });
  document.addEventListener("pointerdown", closeUserMenusOnOutside);
  document.addEventListener("keydown", closeUserMenusOnEscape);
  document.addEventListener("keydown", handleMobileMenuKeydown);
});

onBeforeUnmount(() => {
  window.removeEventListener("scroll", handleScroll);
  window.removeEventListener("resize", handleResize);
  document.removeEventListener("pointerdown", closeUserMenusOnOutside);
  document.removeEventListener("keydown", closeUserMenusOnEscape);
  document.removeEventListener("keydown", handleMobileMenuKeydown);
  disposeHeaderScroll();
  document.body.style.overflow = "";
  clearMegaMenuTimers();
});
</script>

<style scoped lang="scss" src="./WebsiteLayout.scss">
</style>
