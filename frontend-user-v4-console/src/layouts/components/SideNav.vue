<template>
  <template v-if="settingStore.showSidebar">
    <div v-if="isMobile && settingStore.isMobileSidebarVisible" :class="`${prefix}-side-nav-mask`" @click="closeMobileSidebar"></div>
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
import { difference, remove, union } from 'lodash';
import type { MenuValue } from 'tdesign-vue-next';
import type { PropType } from 'vue';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';

import { useSiteBrandingStore } from '@/app/stores/siteBranding';
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

const MOBILE_POINT = 768;
const EXPANDED_LOGO_SRC = '/uploads/logo/logo.svg';
const COLLAPSED_LOGO_SRC = '/uploads/logo/logo1.svg';

const settingStore = useSettingStore();
const isMobile = ref(typeof window !== 'undefined' ? window.innerWidth <= MOBILE_POINT : false);
const collapsed = computed(() => (isMobile.value ? false : settingStore.isSidebarCompact));
const menuAutoCollapsed = computed(() => settingStore.menuAutoCollapsed);
const brandLogoSrc = computed(() => (collapsed.value ? COLLAPSED_LOGO_SRC : EXPANDED_LOGO_SRC));

const active = computed(() => getActive());

const expanded = ref<MenuValue[]>([]);

const getExpanded = () => {
  const path = getActive();
  const result = findExpandedByMenu(menu as MenuRoute[], path) || fallbackExpanded(path);

  expanded.value = menuAutoCollapsed.value ? result : union(result, expanded.value);
};

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
  const currentOperationMenu = difference(expanded.value, value);
  const allExpanded = union(value, expanded.value);
  remove(allExpanded, (item) => currentOperationMenu.includes(item));
  expanded.value = allExpanded;
};

const sideMode = computed(() => {
  return theme === 'dark';
});
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
      'client-side-brand--dark': sideMode.value,
    },
  ];
});
const versionCls = computed(() => {
  return [
    `version-container`,
    {
      [`${prefix}-side-nav-dark`]: sideMode.value,
    },
  ];
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
const siteBranding = useSiteBrandingStore();

const autoCollapsed = () => {
  const mobile = window.innerWidth <= MOBILE_POINT;
  const wasMobile = isMobile.value;

  isMobile.value = mobile;

  settingStore.updateConfig({
    isSidebarCompact: false,
    isMobileSidebarVisible: mobile ? (wasMobile ? settingStore.isMobileSidebarVisible : true) : false,
  });
};

onMounted(() => {
  getExpanded();
  autoCollapsed();
  siteBranding.fetchSiteConfig();

  window.addEventListener('resize', autoCollapsed);
});

onUnmounted(() => {
  window.removeEventListener('resize', autoCollapsed);
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

function closeMobileSidebar() {
  if (!isMobile.value || !settingStore.isMobileSidebarVisible) return;

  settingStore.updateConfig({
    isMobileSidebarVisible: false,
  });
}

function handleMenuChange() {
  closeMobileSidebar();
}
</script>
<style lang="less" scoped>
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
