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
          <span v-if="showLogo" :class="`${prefix}-side-nav-logo-wrapper`" @click="goHome">
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

import { useSiteBrandingStore } from '@/app/stores/siteBranding';
import { useDeviceLayout } from '@/composables/useDeviceLayout';
import { prefix } from '@/config/global';
import { getActive } from '@/router';
import { useSettingStore } from '@/store';
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
  headerHeight: {
    type: String as PropType<string>,
    default: '64px',
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

// 响应式断点驱动侧边栏状态：平板自动收起，手机用抽屉，PC 保持展开
watch(
  [isMobile, isTablet],
  ([mobile, tablet], [wasMobile]) => {
    settingStore.updateConfig({
      isSidebarCompact: tablet,
      isMobileSidebarVisible: mobile ? (wasMobile ? settingStore.isMobileSidebarVisible : false) : false,
    });
  },
  { immediate: true },
);

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

  &__logo--expanded {
    width: 112px;
    height: 34px;
  }

  &__logo--collapsed {
    width: 34px;
    height: 34px;
  }

  &--collapsed {
    width: 64px;
    justify-content: center;
  }
}
</style>
