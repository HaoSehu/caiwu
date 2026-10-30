<template>
  <div v-if="!isRefreshing">
    <router-view v-if="!isFramePage" v-slot="{ Component }">
      <transition name="fade" mode="out-in">
        <!--
          不能用 :include="aliveViews"：include 匹配组件 name，而本仓库页面组件全是
          index.vue 的 <script setup>，推断出的 name 一律是 "index"，与路由 name 永不相等，
          keep-alive 一个页面都缓存不到（等于关闭缓存）。改为按路由 name 做 key，
          显式 keepAlive === false 的路由走不缓存旁路。
        -->
        <component :is="Component" v-if="route.meta.keepAlive === false" />
        <!-- L-10：:max 与页签上限同源（tabs-router.ts 的 MAX_TAB_COUNT），避免缓存容量与页签数脱钩 -->
        <keep-alive v-else :max="maxCachedViews">
          <component :is="Component" :key="String(route.name ?? route.path)" />
        </keep-alive>
      </transition>
    </router-view>
    <frame-page v-else />
  </div>

  <t-loading v-else />
</template>
<script setup lang="ts">
import { computed } from 'vue';
import { useRoute } from 'vue-router';

import FramePage from '@/layouts/frame/index.vue';
import { MAX_TAB_COUNT, useTabsRouterStore } from '@/store';

// <suspense>标签属于实验性功能，请谨慎使用
// 如果存在需解决/page/1=> /page/2 刷新数据问题 请修改代码 使用activeRouteFullPath 作为key
// <suspense>
//  <component :is="Component" :key="activeRouteFullPath" />
// </suspense>

// import { useRouter } from 'vue-router';
// const activeRouteFullPath = computed(() => {
//   const router = useRouter();
//   return router.currentRoute.value.fullPath;
// });

const isRefreshing = computed(() => {
  const tabsRouterStore = useTabsRouterStore();
  const { refreshing } = tabsRouterStore;
  return refreshing;
});

const maxCachedViews = MAX_TAB_COUNT;

const route = useRoute(); // 这个不能放到computed中，切换页面时会导致被缓存
const isFramePage = computed(() => {
  return !!route.meta?.frameSrc;
});
</script>
<style lang="less" scoped>
.fade-leave-active,
.fade-enter-active {
  transition: opacity @anim-duration-slow @anim-time-fn-easing;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
