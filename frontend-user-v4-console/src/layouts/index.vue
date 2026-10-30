<template>
  <div>
    <a class="skip-to-content" href="#main-content">跳到主内容</a>
    <template v-if="setting.layout.value === 'side'">
      <t-layout key="side" :class="mainLayoutCls">
        <t-aside><layout-side-nav /></t-aside>
        <t-layout>
          <t-header><layout-header /></t-header>
          <t-content id="main-content"><layout-content /></t-content>
        </t-layout>
      </t-layout>
    </template>

    <template v-else>
      <t-layout key="no-side">
        <t-header><layout-header /> </t-header>
        <t-layout :class="mainLayoutCls">
          <layout-side-nav />
          <t-content id="main-content"><layout-content /></t-content>
        </t-layout>
      </t-layout>
    </template>
    <div id="aria-live-region" class="sr-only" aria-live="polite" role="status"></div>
  </div>
</template>
<script setup lang="ts">
import '@/style/layout.less';

import { storeToRefs } from 'pinia';
import { computed, onMounted, watch } from 'vue';
import { useRoute } from 'vue-router';

import { prefix } from '@/config/global';
import { useSettingStore, useTabsRouterStore } from '@/store';

import LayoutContent from './components/LayoutContent.vue';
import LayoutHeader from './components/LayoutHeader.vue';
import LayoutSideNav from './components/LayoutSideNav.vue';

const route = useRoute();
const settingStore = useSettingStore();
const tabsRouterStore = useTabsRouterStore();
const setting = storeToRefs(settingStore);

const mainLayoutCls = computed(() => [
  {
    't-layout--with-sider': settingStore.showSidebar,
  },
]);

const appendNewRoute = () => {
  const {
    path,
    query,
    meta: { title },
    name,
  } = route;
  const tabTitle = typeof title === 'string' ? title : '';
  tabsRouterStore.appendTabRouterList({ path, query, title: tabTitle, name, isAlive: true, meta: route.meta });
};

onMounted(() => {
  appendNewRoute();
});

// 只监听 path 会漏掉同页不同 query 的切换（如账单列表按状态筛选），也不会让详情页参数变化重建标签。
// 滚动容器在切换布局或首帧时可能尚未挂载，querySelector 返回 null 会直接抛错，必须先判空。
const scrollLayoutToTop = () => {
  const container = document.querySelector<HTMLElement>(`.${prefix}-layout`);
  if (!container) return;
  // 尊重系统「减少动态效果」偏好，避免强制平滑滚动
  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  container.scrollTo({ top: 0, behavior: reduceMotion ? 'auto' : 'smooth' });
};

watch(
  () => [route.path, route.query] as const,
  () => {
    appendNewRoute();
    scrollLayoutToTop();
  },
);
</script>
<style lang="less" scoped></style>
