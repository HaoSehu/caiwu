<template>
  <t-layout :class="`${prefix}-layout`">
    <t-tabs
      v-if="settingStore.isUseTabsRouter"
      drag-sort
      theme="card"
      :class="`${prefix}-layout-tabs-nav`"
      :value="route.path"
      :style="{ position: 'sticky', top: 0, width: '100%' }"
      @change="(value: string | number) => handleChangeCurrentTab(value as string)"
      @remove="handleRemove"
      @drag-sort="handleDragend"
    >
      <t-tab-panel
        v-for="(routeItem, index) in tabRouters"
        :key="routeItem.path"
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
            <t-dropdown trigger="click" :min-column-width="128" :popup-props="tabPopupPropsMap[routeItem.path]">
              <span
                class="tab-item-more"
                role="button"
                tabindex="0"
                :aria-label="`${renderTitle(routeItem.title) || '首页'}标签页操作`"
                :aria-expanded="activeTabPath === routeItem.path"
                @click.stop
                @keydown.enter.prevent.stop="openTabMenu(routeItem.path)"
                @keydown.space.prevent.stop="openTabMenu(routeItem.path)"
              >
                <t-icon name="ellipsis" class="tab-item-more-icon" />
              </span>
              <template #dropdown>
                <t-dropdown-menu>
                  <t-dropdown-item @click="() => handleRefresh(routeItem, index)">
                    <t-icon name="refresh" />
                    刷新
                  </t-dropdown-item>
                  <t-dropdown-item v-if="index > 1" @click="() => handleCloseAhead(routeItem.path, index)">
                    <t-icon name="arrow-left" />
                    关闭左侧
                  </t-dropdown-item>
                  <t-dropdown-item
                    v-if="index < tabRouters.length - 1"
                    @click="() => handleCloseBehind(routeItem.path, index)"
                  >
                    <t-icon name="arrow-right" />
                    关闭右侧
                  </t-dropdown-item>
                  <t-dropdown-item v-if="tabRouters.length > 2" @click="() => handleCloseOther(routeItem.path, index)">
                    <t-icon name="close-circle" />
                    关闭其他
                  </t-dropdown-item>
                </t-dropdown-menu>
              </template>
            </t-dropdown>
          </div>
        </template>
      </t-tab-panel>
    </t-tabs>
    <t-content :class="`${prefix}-content-layout`">
      <l-breadcrumb v-if="settingStore.showBreadcrumb" />
      <l-content />
    </t-content>
  </t-layout>
</template>
<script setup lang="ts">
import type { PopupVisibleChangeContext } from 'tdesign-vue-next';
import { computed, nextTick, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';

import { prefix } from '@/config/global';
import { useSettingStore, useTabsRouterStore } from '@/store';
import type { TRouterInfo, TTabRemoveOptions } from '@/types/interface';

import LBreadcrumb from './Breadcrumb.vue';
import LContent from './Content.vue';

const route = useRoute();
const router = useRouter();

const settingStore = useSettingStore();
const tabsRouterStore = useTabsRouterStore();
const tabRouters = computed(() => tabsRouterStore.tabRouters.filter((route) => route.isAlive || route.isHome));
const activeTabPath = ref('');

// 每个标签的下拉配置预先生成并缓存对象引用：模板里写字面量会每次渲染产生新对象与新函数，
// 让 t-dropdown 的 props 比较始终不相等，从而反复触发内部更新。
const tabPopupPropsMap = computed<Record<string, Record<string, unknown>>>(() => {
  const map: Record<string, Record<string, unknown>> = {};
  for (const item of tabRouters.value) {
    map[item.path] = {
      overlayClassName: 'route-tabs-dropdown',
      visible: activeTabPath.value === item.path,
      onVisibleChange: (visible: boolean, ctx: PopupVisibleChangeContext) =>
        handleTabMenuClick(visible, ctx, item.path),
    };
  }
  return map;
});

// 模板 v-for 遍历的是过滤后的可见标签（isAlive || isHome），而 tabsRouterStore 的各
// subtract*/toggle* 动作按 state.tabRouterList 的原始下标执行。两者在存在 isAlive=false
// 的标签时不等长，因此所有入口都先用 path 反查 store 真实下标，禁止直接透传列表下标。
const resolveStoreIdx = (path: string) => tabsRouterStore.tabRouters.findIndex((i) => i.path === path);

const handleChangeCurrentTab = (path: string) => {
  const target = tabsRouterStore.tabRouters.find((i) => i.path === path);
  if (!target) return;
  router.push({ path, query: target.query });
};

const handleRemove = (options: TTabRemoveOptions) => {
  const removingPath = options.value as string;
  const list = tabsRouterStore.tabRouters;
  const storeIdx = list.findIndex((i) => i.path === removingPath);
  if (storeIdx < 0) return;
  const isRemovingActive = removingPath === route.path;
  // 相邻标签同样按 store 原始列表取，且兜底到控制台，避免关闭最后一个非首页标签时读空对象
  const nextRouter = list[storeIdx + 1] ?? list[storeIdx - 1] ?? list[0];

  tabsRouterStore.subtractCurrentTabRouter({ path: removingPath, routeIdx: storeIdx });
  if (isRemovingActive && nextRouter) router.push({ path: nextRouter.path, query: nextRouter.query });
};

const renderTitle = (title?: string) => title || '';
const handleRefresh = (tab: TRouterInfo, _routeIdx: number) => {
  const storeIdx = resolveStoreIdx(tab.path);
  if (storeIdx < 0) return;
  tabsRouterStore.toggleTabRouterAlive(storeIdx);
  nextTick(() => {
    tabsRouterStore.toggleTabRouterAlive(storeIdx);
    router.replace({ path: tab.path, query: tab.query });
  });
  activeTabPath.value = null;
};
const handleCloseAhead = (path: string, _routeIdx: number) => {
  const storeIdx = resolveStoreIdx(path);
  if (storeIdx < 0) return;
  tabsRouterStore.subtractTabRouterAhead({ path, routeIdx: storeIdx });

  handleOperationEffect('ahead', storeIdx);
};
const handleCloseBehind = (path: string, _routeIdx: number) => {
  const storeIdx = resolveStoreIdx(path);
  if (storeIdx < 0) return;
  tabsRouterStore.subtractTabRouterBehind({ path, routeIdx: storeIdx });

  handleOperationEffect('behind', storeIdx);
};
const handleCloseOther = (path: string, _routeIdx: number) => {
  const storeIdx = resolveStoreIdx(path);
  if (storeIdx < 0) return;
  tabsRouterStore.subtractTabRouterOther({ path, routeIdx: storeIdx });

  handleOperationEffect('other', storeIdx);
};

// 处理非当前路由操作的副作用
const handleOperationEffect = (type: 'other' | 'ahead' | 'behind', routeIndex: number) => {
  const currentPath = router.currentRoute.value.path;
  const { tabRouters } = tabsRouterStore;

  const currentIdx = tabRouters.findIndex((i) => i.path === currentPath);
  // 存在三种情况需要刷新当前路由
  // 点击非当前路由的关闭其他、点击非当前路由的关闭左侧且当前路由小于触发路由、点击非当前路由的关闭右侧且当前路由大于触发路由
  const needRefreshRouter =
    (type === 'other' && currentIdx !== routeIndex) ||
    (type === 'ahead' && currentIdx < routeIndex) ||
    (type === 'behind' && currentIdx === -1);
  if (needRefreshRouter) {
    const nextRouteIdx = type === 'behind' ? tabRouters.length - 1 : 1;
    const nextRouter = tabRouters[nextRouteIdx];
    if (nextRouter) router.push({ path: nextRouter.path, query: nextRouter.query });
  }

  activeTabPath.value = null;
};
// 键盘可达：把「更多」菜单按 Enter/Space 视为打开，等同鼠标点击
const openTabMenu = (path: string) => {
  activeTabPath.value = path;
};

const handleTabMenuClick = (visible: boolean, ctx: PopupVisibleChangeContext, path: string) => {
  if (ctx.trigger === 'document') activeTabPath.value = null;
  if (visible) activeTabPath.value = path;
};

const handleContextMenu = (e: MouseEvent, path: string) => {
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
  // 拖拽下标来自过滤后的可见列表，需先换成 store 真实下标再交换，否则 isAlive=false
  // 的标签存在时会错位。home 标签在列表项上由 draggable={false} 直接禁掉拖拽，
  // 这里不做二次保护 —— 补一句「交换后保证 home 在首位」的注释会与实现不符。
  const currentPath = tabRouters.value[options.currentIndex]?.path;
  const targetPath = tabRouters.value[options.targetIndex]?.path;
  if (!currentPath || !targetPath) return;
  const fromIdx = resolveStoreIdx(currentPath);
  const toIdx = resolveStoreIdx(targetPath);
  if (fromIdx < 0 || toIdx < 0 || fromIdx === toIdx) return;
  const list = tabsRouterStore.tabRouters;
  [list[fromIdx], list[toIdx]] = [list[toIdx], list[fromIdx]];
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
  position: relative;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: var(--td-comp-size-xxs);
  height: var(--td-comp-size-xxs);
  margin-left: var(--td-comp-margin-xs);
  border-radius: var(--td-radius-small);
  color: var(--td-text-color-secondary);
  cursor: pointer;
  opacity: 0.75;
  transition:
    opacity 0.2s,
    background-color 0.2s;

  /* 视觉尺寸受标签高度限制：盒宽高是 --td-comp-size-xxs（--td-size-7 = 20），
     用伪元素四边各外扩 0.25rem 把命中区补到 28 见方 */
  &::after {
    content: '';
    position: absolute;
    inset: -0.25rem;
  }

  &:hover {
    opacity: 1;
    background-color: var(--td-bg-color-container-hover);
  }

  &:focus-visible {
    opacity: 1;
    outline: 0.125rem solid var(--td-brand-color);
    outline-offset: 0.0625rem;
  }

  .tab-item-more-icon {
    font-size: var(--td-font-size-body-medium);
  }
}
</style>
