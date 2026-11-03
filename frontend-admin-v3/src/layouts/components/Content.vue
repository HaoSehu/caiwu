<template>
  <div v-if="!isRefreshing">
    <router-view v-if="!isFramePage" v-slot="{ Component }">
      <!-- 必须保留 mode="out-in"：不留 out-in 时旧页与新页会同屏共存一帧，
           页面里的语义类名（如 .identity-title）会同时命中两份，e2e 与页面查询都会受干扰。
           「切页慢」的真正来源不是 out-in 本身，而是旧版把离场时长也设成 0.28s：
           每次切页都要先等旧页完整淡出（实测内容区空 315~333ms）才挂载新页。
           这里只缩短离场时长（见下方样式），既消除空白等待，又不破坏单页挂载语义。 -->
      <transition name="fade" mode="out-in">
        <keep-alive :include="aliveViews">
          <component :is="Component" :key="cacheKey" />
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

// KeepAlive 的缓存键由 vnode 的 key 决定，缓存槽位由 :include 的组件名决定，两者必须一致。
//
// 历史的 key 用 route.path，但 :include 用的是 meta.keepAliveName。当同一组件被多个路由
// 复用（商品目录/流量包/提供商共用 AdminProducts，订单四个 tab 共用 AdminFinanceOrders，
// 设置五个 tab 共用 AdminSettings……共 11 组）时，每个 path 都会占一个独立缓存槽，
// 且它们都命中 include 而全部常驻；切到组内任一页时，整组实例会一起重挂/重拉，
// 实测请求数随访问过的页数线性增长（连续访问三个商品子页后，规格页会多出 6 个请求）。
//
// 因此 key 优先用 meta.keepAliveName 归并同一页面组。但**含参数的路由必须仍按 path 分实例**：
// products/:id/edit、content/notices/:id、email-templates/:code 这些页面只在 onMounted 里
// 按 route.params 取数、不监听参数变化，若与同组共用缓存槽，从「商品 1」跳到「商品 2」时
// 会直接命中旧实例，既不重新挂载也不重新请求，页面继续显示上一个 id 的数据（实测已复现）。
// 判断依据用 route.matched 上的 path 是否含 ':'，不依赖路由名约定。
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

// 参数化路由按 path 分实例（理由见上方注释），其余同组路由按 keepAliveName 归并。
const cacheKey = computed(() => {
  const keepAliveName = route.meta?.keepAliveName as string | undefined;
  if (!keepAliveName) return route.path;
  const matchedPattern = route.matched[route.matched.length - 1]?.path || '';
  return matchedPattern.includes(':') ? route.path : keepAliveName;
});
</script>
<style lang="less" scoped>
// 离场刻意远短于入场：mode="out-in" 下离场时长就是用户等待新页的空白期。
// 旧版两侧同为 0.28s，切页要先空 0.28s 才见新内容；这里压到 0.06s（约 4 帧），
// 视觉上仍是淡出，但不再让人感到「点了没反应」。入场保持 0.2s 保证观感。
@content-leave-duration: 0.06s;

.fade-leave-active {
  transition: opacity @content-leave-duration @anim-time-fn-easing;
}

.fade-leave-to {
  opacity: 0;
}

.fade-enter-active {
  transition: opacity @anim-duration-base @anim-time-fn-easing;
}

.fade-enter-from {
  opacity: 0;
}
</style>
