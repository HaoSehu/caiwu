<template>
  <div v-if="!isRefreshing">
    <router-view v-if="!isFramePage" v-slot="{ Component }">
      <transition name="fade" mode="out-in">
        <keep-alive :include="aliveViews">
          <component :is="Component" :key="route.path" />
        </keep-alive>
      </transition>
    </router-view>
    <frame-page v-else />
  </div>

  <t-loading v-else />
</template>
<script setup lang="ts">
import { isBoolean, isUndefined } from 'lodash-es';
import type { ComputedRef } from 'vue';
import { computed } from 'vue';
import { useRoute } from 'vue-router';

import FramePage from '@/layouts/frame/index.vue';
import { useTabsRouterStore } from '@/store';

// KeepAlive 的缓存键由 vnode 的 key 决定：一个标签页对应一个 path，key 必须用 route.path。
// 若不写 key，同一组件（如 users/:id、tickets/:id、products/:id/edit）在多个标签间
// 只会保留一个实例，从「用户 1」切到「用户 2」时不会重新挂载，页面会显示上一个 id 的数据。
// 不能用 route.fullPath：页内切 tab 会改 query，那会把同一标签缓存成多份。

const aliveViews = computed(() => {
  const tabsRouterStore = useTabsRouterStore();
  const { tabRouters } = tabsRouterStore;
  return tabRouters
    .filter((route) => {
      const keepAliveConfig = route.meta?.keepAlive;
      const isRouteKeepAlive = isUndefined(keepAliveConfig) || (isBoolean(keepAliveConfig) && keepAliveConfig); // 默认开启keepalive
      return route.isAlive && isRouteKeepAlive;
    })
    .map((route) => (route.meta?.keepAliveName as string) || (route.name as string))
    .filter((name): name is string => Boolean(name));
}) as ComputedRef<string[]>;

const isRefreshing = computed(() => {
  const tabsRouterStore = useTabsRouterStore();
  const { refreshing } = tabsRouterStore;
  return refreshing;
});

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
