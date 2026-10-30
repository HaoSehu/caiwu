<template>
  <template v-if="settingStore.showSidebar">
    <transition name="side-nav-mask-fade">
      <div
        v-if="isMobile && settingStore.isMobileSidebarVisible"
        :class="`${prefix}-side-nav-mask`"
        aria-hidden="true"
        @click="closeMobileSidebar"
      ></div>
    </transition>
    <div :class="sideNavCls">
      <t-menu
        :class="menuCls"
        :theme="theme"
        :value="active"
        :collapsed="collapsed"
        :expanded="expanded"
        :expand-mutex="menuAutoCollapsed"
        @change="handleMenuChange"
        @expand="onExpanded"
      >
        <template #logo>
          <!-- 品牌位是页面内的导航入口，必须可聚焦并用键盘触发（L-15） -->
          <span
            v-if="showLogo"
            class="client-side-brand__trigger"
            :class="[`${prefix}-side-nav-logo-wrapper`]"
            role="button"
            tabindex="0"
            :aria-label="`返回首页（${siteBranding.siteName}）`"
            @click="goHome"
            @keydown.enter.self.prevent="goHome"
            @keydown.space.self.prevent="goHome"
          >
            <span :class="brandLogoCls">
              <img
                class="client-side-brand__logo"
                :class="collapsed ? 'client-side-brand__logo--collapsed' : 'client-side-brand__logo--expanded'"
                :src="brandLogoSrc"
                :alt="siteBranding.siteName"
                loading="eager"
              />
            </span>
          </span>
        </template>
        <menu-content :nav-data="menu" />
        <template #operations>
          <span :class="versionCls">{{ !collapsed ? siteBranding.siteName : '' }}</span>
        </template>
      </t-menu>
      <div :class="`${prefix}-side-nav-placeholder${collapsed ? '-hidden' : ''}`"></div>
    </div>
  </template>
</template>
<script setup lang="ts">
import type { MenuValue } from 'tdesign-vue-next';
import type { PropType } from 'vue';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';

import { useDeviceLayout } from '@/composables/useDeviceLayout';
import { prefix } from '@/config/global';
import { getActive } from '@/router';
import { useSettingStore, useSiteBrandingStore } from '@/store';
import type { MenuRoute, ModeType } from '@/types/interface';

import MenuContent from './MenuContent.vue';

const { menu, showLogo, isFixed, layout, theme, isCompact } = defineProps({
  menu: {
    type: Array as PropType<MenuRoute[]>,
    default: () => [],
  },
  showLogo: {
    type: Boolean as PropType<boolean>,
    default: true,
  },
  isFixed: {
    type: Boolean as PropType<boolean>,
    default: true,
  },
  layout: {
    type: String as PropType<string>,
    default: '',
  },
  theme: {
    type: String as PropType<ModeType>,
    default: 'light',
  },
  isCompact: {
    type: Boolean as PropType<boolean>,
    default: false,
  },
});

const settingStore = useSettingStore();
const siteBranding = useSiteBrandingStore();
const { isMobile, isTablet } = useDeviceLayout();
const collapsed = computed(() => (isMobile.value ? false : settingStore.isSidebarCompact));
const menuAutoCollapsed = computed(() => settingStore.menuAutoCollapsed);
const brandLogoSrc = computed(() => siteBranding.clientConsoleIcon || siteBranding.siteLogo);

const active = computed(() => getActive());

const expanded = ref<MenuValue[]>([]);

const getExpanded = () => {
  const path = getActive();
  const result = findExpandedByMenu(menu as MenuRoute[], path) || fallbackExpanded(path);
  const merged = menuAutoCollapsed.value ? result : unionMenuValues(result, expanded.value);
  // 移动端抽屉一级分组默认全展开：高频入口保持“汉堡 → 点页面”两步可达
  expanded.value = isMobile.value ? unionMenuValues(merged, allTopLevelPaths()) : merged;
};

function allTopLevelPaths(): MenuValue[] {
  return (menu as MenuRoute[]).map((item) => String(item.path));
}

watch(
  () => active.value,
  () => {
    getExpanded();
    if (isMobile.value) {
      closeMobileSidebar();
    }
  },
);

const onExpanded = (value: MenuValue[]) => {
  const currentOperationMenu = expanded.value.filter((item) => !value.includes(item));
  expanded.value = unionMenuValues(value, expanded.value).filter((item) => !currentOperationMenu.includes(item));
};

const sideNavCls = computed(() => {
  return [
    `${prefix}-sidebar-layout`,
    {
      [`${prefix}-sidebar-compact`]: isCompact,
      [`${prefix}-sidebar-mobile`]: isMobile.value,
      [`${prefix}-sidebar-mobile-open`]: isMobile.value && settingStore.isMobileSidebarVisible,
    },
  ];
});
const brandLogoCls = computed(() => {
  return [
    'client-side-brand',
    {
      'client-side-brand--collapsed': collapsed.value,
    },
  ];
});
const versionCls = computed(() => {
  return [`version-container`];
});
const menuCls = computed(() => {
  return [
    `${prefix}-side-nav`,
    {
      [`${prefix}-side-nav-no-logo`]: !showLogo,
      [`${prefix}-side-nav-no-fixed`]: !isFixed,
      [`${prefix}-side-nav-mix-fixed`]: layout === 'mix' && isFixed,
    },
  ];
});

const router = useRouter();

// 进入平板档自动收起；离开平板档时还原用户原来的折叠偏好。
// 不能每轮断点变化都直接写 isSidebarCompact（原实现还带 immediate）：
// 那会把 Header 折叠按钮写进 localStorage 的偏好覆盖掉，刷新后用户的手动折叠失效（L-14）。
// 刻意不加 immediate：首帧就落在平板档（例如直接用平板竖屏尺寸打开控制台）时不会强制收起，
// 用户看到的是自己上次的折叠偏好。这是取舍而不是遗漏 —— 要强制收起得先能区分
// 「用户自己折叠的」和「上个平板会话替用户折叠的」，当前没有这个标记位。
let compactBeforeTablet: boolean | null = null;
watch(isTablet, (tablet) => {
  if (tablet) {
    if (compactBeforeTablet === null) {
      compactBeforeTablet = settingStore.isSidebarCompact;
    }
    if (!settingStore.isSidebarCompact) {
      settingStore.updateConfig({ isSidebarCompact: true });
    }
    return;
  }
  if (compactBeforeTablet !== null) {
    const restore = compactBeforeTablet;
    compactBeforeTablet = null;
    if (settingStore.isSidebarCompact !== restore) {
      settingStore.updateConfig({ isSidebarCompact: restore });
    }
  }
});

// 手机档用抽屉：离开手机档必须收起抽屉，否则回到桌面会残留遮罩
watch(isMobile, (mobile) => {
  if (!mobile && settingStore.isMobileSidebarVisible) {
    settingStore.updateConfig({ isMobileSidebarVisible: false });
  }
});

onMounted(() => {
  getExpanded();
  siteBranding.fetchSiteConfig();
  document.addEventListener('keydown', handleSidebarEscKeydown);
});

const goHome = () => {
  closeMobileSidebar();
  router.push('/client/dashboard');
};

function findExpandedByMenu(list: MenuRoute[], activePath: string, parents: MenuValue[] = []): MenuValue[] | null {
  for (const item of list || []) {
    const currentPath = String(item.path);
    if (isRouteMatch(currentPath, activePath)) return parents;

    const childResult = findExpandedByMenu(item.children || [], activePath, [...parents, currentPath]);
    if (childResult) return childResult;
  }

  return null;
}

function isRouteMatch(routePath: string, activePath: string) {
  return activePath === routePath || activePath.startsWith(`${routePath}/`);
}

function fallbackExpanded(path: string) {
  const parts = path.split('/').slice(1);
  return parts.map((_, index) => `/${parts.slice(0, index + 1).join('/')}`);
}

function unionMenuValues(left: MenuValue[], right: MenuValue[]) {
  return Array.from(new Set([...left, ...right]));
}

function closeMobileSidebar() {
  if (!isMobile.value || !settingStore.isMobileSidebarVisible) return;

  settingStore.updateConfig({
    isMobileSidebarVisible: false,
  });
}

// 移动端抽屉打开时锁定布局滚动容器（滚动发生在 .tdesign-starter-layout 而非 body），
// 避免遮罩下主内容跟随手指滚动产生“抽屉没盖住”的失控感
watch(
  () => settingStore.isMobileSidebarVisible,
  (visible) => {
    if (typeof document === 'undefined') return;
    const locked = Boolean(visible && isMobile.value);
    document.body.classList.toggle('client-mobile-sidebar-locked', locked);
  },
  { immediate: true },
);

// 遮罩 z-index 高于 header，汉堡无法二次点击；补 Esc 作为键盘/外接键盘关闭路径
function handleSidebarEscKeydown(event: KeyboardEvent) {
  if (event.key === 'Escape') {
    closeMobileSidebar();
  }
}

function handleMenuChange() {
  closeMobileSidebar();
}

onBeforeUnmount(() => {
  document.removeEventListener('keydown', handleSidebarEscKeydown);
  if (typeof document !== 'undefined') {
    document.body.classList.remove('client-mobile-sidebar-locked');
  }
});
</script>
<style lang="less" scoped>
// 移动端抽屉遮罩淡入淡出，避免开关时生硬闪现
.side-nav-mask-fade-enter-active,
.side-nav-mask-fade-leave-active {
  transition: opacity 0.24s cubic-bezier(0, 0, 0.15, 1);
}

.side-nav-mask-fade-enter-from,
.side-nav-mask-fade-leave-to {
  opacity: 0;
}

.client-side-brand {
  display: inline-flex;
  align-items: center;
  min-width: 0;
  width: auto;

  &__logo {
    display: block;
    object-fit: contain;
  }

  &__trigger:focus-visible {
    outline: 0.125rem solid var(--td-brand-color);
    outline-offset: 0.125rem;
    border-radius: var(--td-radius-default);
  }

  // 高度与 Header 的品牌位同档（--td-comp-size-l = 36px），原来写死 34px 会让两处品牌标识差 2px（L-16）
  // 展开态宽度 112px 是品牌图比例决定的固定几何，令牌号段无对应值（C-23）
  &__logo--expanded {
    width: 112px;
    height: var(--td-comp-size-l);
  }

  &__logo--collapsed {
    width: var(--td-comp-size-l);
    height: var(--td-comp-size-l);
  }

  &--collapsed {
    width: @starter-sidebar-collapsed-width;
    justify-content: center;
  }
}
</style>
