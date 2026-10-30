<template>
  <t-layout :class="`${prefix}-layout`">
    <t-tabs
      v-if="settingStore.isUseTabsRouter"
      drag-sort
      theme="card"
      :class="`${prefix}-layout-tabs-nav`"
      :value="route.path"
      :style="{ position: 'sticky', top: 0, width: '100%' }"
      @change="(value: unknown) => handleChangeCurrentTab(value as string)"
      @remove="handleRemove"
      @drag-sort="handleDragend"
    >
      <t-tab-panel
        v-for="(routeItem, index) in tabRouters"
        :key="`${routeItem.path}_${index}`"
        :value="routeItem.path"
        :removable="!routeItem.isHome"
        :draggable="!routeItem.isHome"
      >
        <template #label>
          <div
            class="tab-item-wrapper"
            @contextmenu.prevent="handleContextMenu($event, routeItem.path)"
            @mousedown="handleTabMouseDown"
            @auxclick="handleTabAuxClick($event, routeItem, index)"
          >
            <span class="tab-item-title">
              <template v-if="!routeItem.isHome">
                {{ renderTitle(routeItem.title) }}
              </template>
              <t-icon v-else name="home" />
            </span>
            <t-dropdown
              trigger="click"
              :min-column-width="128"
              :popup-props="{
                overlayClassName: 'route-tabs-dropdown',
                onVisibleChange: (visible: boolean, ctx: PopupVisibleChangeContext) =>
                  handleTabMenuClick(visible, ctx, routeItem.path),
                visible: activeTabPath === routeItem.path,
              }"
            >
              <span
                class="tab-item-more"
                role="button"
                tabindex="0"
                aria-label="标签页操作菜单"
                @click.stop
                @keydown.enter.prevent.stop="toggleTabMenu(routeItem.path)"
                @keydown.space.prevent.stop="toggleTabMenu(routeItem.path)"
              >
                <t-icon name="ellipsis" class="tab-item-more-icon" />
              </span>
              <template #dropdown>
                <t-dropdown-menu>
                  <t-dropdown-item @click="() => handleRefresh(routeItem)">
                    <t-icon name="refresh" />
                    {{ t('layout.tagTabs.refresh') }}
                  </t-dropdown-item>
                  <t-dropdown-item v-if="index > 1" @click="() => handleCloseAhead(routeItem.path)">
                    <t-icon name="arrow-left" />
                    {{ t('layout.tagTabs.closeLeft') }}
                  </t-dropdown-item>
                  <t-dropdown-item
                    v-if="index < tabRouters.length - 1"
                    @click="() => handleCloseBehind(routeItem.path)"
                  >
                    <t-icon name="arrow-right" />
                    {{ t('layout.tagTabs.closeRight') }}
                  </t-dropdown-item>
                  <t-dropdown-item v-if="tabRouters.length > 2" @click="() => handleCloseOther(routeItem.path)">
                    <t-icon name="close-circle" />
                    {{ t('layout.tagTabs.closeOther') }}
                  </t-dropdown-item>
                </t-dropdown-menu>
              </template>
            </t-dropdown>
          </div>
        </template>
      </t-tab-panel>
    </t-tabs>
    <t-content id="main-content" :class="`${prefix}-content-layout`">
      <l-breadcrumb v-if="settingStore.showBreadcrumb" />
      <l-content />
    </t-content>
    <t-footer v-if="settingStore.showFooter" :class="`${prefix}-footer-layout`">
      <l-footer />
    </t-footer>
  </t-layout>
</template>
<script setup lang="ts">
import type { PopupVisibleChangeContext } from 'tdesign-vue-next';
import { computed, nextTick, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';

import { prefix } from '@/config/global';
import type { LocalizedTitle } from '@/locales';
import { t } from '@/locales';
import { useLocale } from '@/locales/useLocale';
import { useSettingStore, useTabsRouterStore } from '@/store';
import type { TRouterInfo, TTabRemoveOptions } from '@/types/interface';

import LBreadcrumb from './Breadcrumb.vue';
import LContent from './Content.vue';
import LFooter from './Footer.vue';

const route = useRoute();
const router = useRouter();

const settingStore = useSettingStore();
const tabsRouterStore = useTabsRouterStore();
const tabRouters = computed(() => tabsRouterStore.tabRouters.filter((route) => route.isAlive || route.isHome));
const activeTabPath = ref('');

const { locale } = useLocale();

const handleChangeCurrentTab = (path: string) => {
  const target = tabsRouterStore.tabRouters.find((i) => i.path === path);
  router.push({ path, query: target?.query });
};

const handleRemove = (options: TTabRemoveOptions) => {
  // 相邻标签必须从「渲染出来的标签」里取：tabRouterList 里还留着 keepAlive:false 的路由
  // （它们不渲染标签），按 store 下标取相邻会跳到用户看不到的页面上（审查 M2）。
  const visibleTabs = tabRouters.value;
  const closedIdx = visibleTabs.findIndex((i) => i.path === options.value);
  const nextRouter = closedIdx >= 0 ? visibleTabs[closedIdx + 1] || visibleTabs[closedIdx - 1] : visibleTabs[0];

  tabsRouterStore.subtractCurrentTabRouter({ path: options.value as string });
  if (nextRouter && (options.value as string) === route.path) {
    router.push({ path: nextRouter.path, query: nextRouter.query });
  }
};

const renderTitle = (title?: LocalizedTitle) => {
  if (!title) return '';
  return title[locale.value as keyof LocalizedTitle] || '';
};
const handleRefresh = (route: TRouterInfo) => {
  // 必须按 path 定位：这里的 route 来自 tabRouters（已过滤掉非保活标签），
  // 其下标与 store 里 tabRouterList 的真实下标不一致，传下标会刷新错标签。
  tabsRouterStore.toggleTabRouterAlive(route.path);
  nextTick(() => {
    tabsRouterStore.toggleTabRouterAlive(route.path);
    router.replace({ path: route.path, query: route.query });
  });
  activeTabPath.value = '';
};
const handleCloseAhead = (path: string) => {
  tabsRouterStore.subtractTabRouterAhead({ path });

  handleOperationEffect('ahead', path);
};
const handleCloseBehind = (path: string) => {
  tabsRouterStore.subtractTabRouterBehind({ path });

  handleOperationEffect('behind', path);
};
const handleCloseOther = (path: string) => {
  tabsRouterStore.subtractTabRouterOther({ path });

  handleOperationEffect('other', path);
};

// 处理非当前路由操作的副作用
const handleOperationEffect = (type: 'other' | 'ahead' | 'behind', path: string) => {
  const currentPath = router.currentRoute.value.path;
  // 与 handleRemove 同源：一律用渲染出来的标签列表，否则可能落到 keepAlive:false 的无标签页（审查 M2）。
  const visibleTabs = tabRouters.value;

  const currentIdx = visibleTabs.findIndex((i) => i.path === currentPath);
  const targetIdx = visibleTabs.findIndex((i) => i.path === path);
  // 存在三种情况需要刷新当前路由
  // 点击非当前路由的关闭其他、点击非当前路由的关闭左侧且当前路由小于触发路由、点击非当前路由的关闭右侧且当前路由大于触发路由
  const needRefreshRouter =
    (type === 'other' && currentIdx !== targetIdx) ||
    (type === 'ahead' && currentIdx < targetIdx) ||
    (type === 'behind' && currentIdx === -1);
  if (needRefreshRouter) {
    const nextRouteIdx = type === 'behind' ? visibleTabs.length - 1 : 1;
    const nextRouter = visibleTabs[nextRouteIdx];
    if (nextRouter) router.push({ path: nextRouter.path, query: nextRouter.query });
  }

  activeTabPath.value = '';
};
const handleTabMenuClick = (visible: boolean, ctx: PopupVisibleChangeContext, path: string) => {
  if (ctx.trigger === 'document') activeTabPath.value = '';
  if (visible) activeTabPath.value = path;
};

const handleContextMenu = (e: MouseEvent, path: string) => {
  activeTabPath.value = activeTabPath.value === path ? '' : path;
};

// 键盘可达：与右键/点击同一套开关逻辑，供 tab 的「更多」按钮在 Enter/Space 时触发
const toggleTabMenu = (path: string) => {
  activeTabPath.value = activeTabPath.value === path ? '' : path;
};

const handleTabMouseDown = (e: MouseEvent) => {
  if (e.button === 1) {
    e.preventDefault();
  }
};

const handleTabAuxClick = (e: MouseEvent, routeItem: TRouterInfo, index: number) => {
  if (e.button === 1 && !routeItem.isHome) {
    e.preventDefault();
    handleRemove({ value: routeItem.path, index, e });
  }
};

const handleDragend = (options: { currentIndex: number; targetIndex: number }) => {
  // currentIndex / targetIndex 是 tabRouters（过滤后）的下标，需先映射成 path 再在 store 里换位；
  // 直接改 tabRouters 是在改 computed 派生数组，不会写回 tabRouterList。
  const fromPath = tabRouters.value[options.currentIndex]?.path;
  const toPath = tabRouters.value[options.targetIndex]?.path;
  if (!fromPath || !toPath) return;

  tabsRouterStore.moveTabRouter(fromPath, toPath);
};
</script>
<style lang="less" scoped>
.tab-item-wrapper {
  display: inline-flex;
  align-items: center;
  user-select: none;
}

.tab-item-title {
  display: inline-flex;
  align-items: center;
}

.tab-item-more {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  margin-left: var(--td-comp-margin-xs);
  padding: 2px;
  border-radius: 3px;
  cursor: pointer;
  opacity: 0.6;
  transition:
    opacity 0.2s,
    background-color 0.2s;

  &:hover {
    opacity: 1;
    background-color: var(--td-bg-color-container-hover);
  }

  .tab-item-more-icon {
    font-size: var(--td-font-size-size-3, 14px);
  }
}
</style>
