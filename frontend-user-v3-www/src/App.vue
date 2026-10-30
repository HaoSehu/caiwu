<template>
  <div class="app-runtime-shell">
    <div class="app-route-progress" :class="{ 'is-active': isRouteLoading }" />

    <router-view v-slot="{ Component }">
      <Transition name="route-fade" mode="out-in">
        <component :is="Component" v-if="Component" />
        <RouteLoadingFallback v-else />
      </Transition>
    </router-view>
  </div>
</template>

<script setup>
import RouteLoadingFallback from '@/components/common/RouteLoadingFallback.vue'
import { isRouteLoading } from '@/utils/routeLoading'
</script>

<style scoped lang="scss">
.app-runtime-shell {
  min-height: 100vh;
}

/* 路由切换过渡（作用范围：只有顶层组件真的被替换时才会触发）
   作用域说明：clientRoutes 只有一条顶层路由（/ 挂 WebsiteLayout），站内导航时
   App 层 router-view 的 Component 恒为同一个 WebsiteLayout 实例，本过渡不会执行。
   站内换页实际生效的是 layout/WebsiteLayout.vue 的 <transition name="page-fade">，
   样式在 layout/WebsiteLayout.scss（同样 0.5 起、0.5 止，不留全白空档）。
   这里只在 WebsiteLayout 本身被替换（未来新增独立顶层路由）时兜底：
   1) 保留 mode="out-in"——若改成并行（去掉 mode）会让新旧两页同时处于文档流中，
      叠加瞬间高度/布局跳动，风险明显更高，本次不动；
   2) leave 0.08s、enter 0.16s，串行合计 240ms；
   3) 两端都不落到 opacity: 0——淡出止于 0.5、淡入起于 0.5。
   代价：旧页淡出半程即被移除（80ms，肉眼近乎瞬时），视觉上靠新页 0.16s 提亮收尾。 */
.route-fade-enter-active {
  transition: opacity 0.16s ease-out;
}

.route-fade-leave-active {
  transition: opacity 0.08s ease-in;
}

.route-fade-enter-from,
.route-fade-leave-to {
  opacity: 0.5;
}

/* 顶部进度条 */
.app-route-progress {
  position: fixed;
  inset: 0 auto auto 0;
  width: 100%;
  height: 3px;
  opacity: 0;
  pointer-events: none;
  z-index: 4200;
  transition: opacity 0.2s ease-out;
  background: rgba($color-primary, 0.06);
}

.app-route-progress::before {
  content: '';
  display: block;
  width: 28%;
  height: 100%;
  border-radius: 999px;
  background: linear-gradient(
    90deg,
    rgba($color-primary, 0.02),
    $color-primary 40%,
    rgba($color-primary, 0.6)
  );
  box-shadow: 0 0 8px rgba($color-primary, 0.3);
}

/* 动画只在真正加载时挂载：原先写在 ::before 基类上，
   即使容器 opacity:0 不可见，合成层的 translateX 仍在每帧跑，
   空闲态白烧合成线程。挪到 .is-active 下，路由结束即停。 */
.app-route-progress.is-active {
  opacity: 1;
}

.app-route-progress.is-active::before {
  animation: app-route-progress 1s cubic-bezier(0.4, 0, 0.2, 1) infinite;
}

@keyframes app-route-progress {
  0% {
    transform: translateX(-100%);
    opacity: 0.6;
  }

  50% {
    opacity: 1;
  }

  100% {
    transform: translateX(420%);
    opacity: 0.4;
  }
}
</style>
