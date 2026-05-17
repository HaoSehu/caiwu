<template>
  <div class="app-runtime-shell">
    <div class="app-route-progress" :class="{ 'is-active': isRouteLoading }" />

    <router-view v-slot="{ Component }">
      <component :is="Component" v-if="Component" />
      <RouteLoadingFallback v-else />
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

.app-route-progress {
  position: fixed;
  inset: 0 auto auto 0;
  width: 100%;
  height: 3px;
  opacity: 0;
  pointer-events: none;
  z-index: 4200;
  transition: opacity 0.18s ease-out;
}

.app-route-progress::before {
  content: '';
  display: block;
  width: 34%;
  height: 100%;
  border-radius: 999px;
  background: linear-gradient(90deg, rgba($color-primary, 0.05), $color-primary, rgba($color-primary, 0.08));
  box-shadow: 0 0 0 1px rgba($color-primary, 0.08);
  animation: app-route-progress 1.05s cubic-bezier(0.22, 1, 0.36, 1) infinite;
}

.app-route-progress.is-active {
  opacity: 1;
}

@keyframes app-route-progress {
  0% {
    transform: translateX(-110%);
  }

  100% {
    transform: translateX(360%);
  }
}
</style>
