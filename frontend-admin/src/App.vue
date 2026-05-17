<template>
  <el-config-provider :locale="zhCn" size="default" :z-index="3200">
    <div class="app-runtime-shell">
      <div class="app-route-progress" :class="{ 'is-active': isRouteLoading }" />

      <router-view v-slot="{ Component }">
        <component :is="Component" v-if="Component" />
        <AdminRouteLoadingFallback v-else />
      </router-view>
    </div>
  </el-config-provider>
</template>

<script setup>
import AdminRouteLoadingFallback from '@/components/common/AdminRouteLoadingFallback.vue'
import { isRouteLoading } from '@/utils/routeLoading'
import zhCn from 'element-plus/es/locale/lang/zh-cn'
</script>

<style scoped lang="scss">
.app-runtime-shell {
  min-height: 100vh;
}

.app-route-progress {
  position: fixed;
  inset: 0 auto auto 0;
  z-index: 4200;
  width: 100%;
  height: 3px;
  opacity: 0;
  pointer-events: none;
  transition: opacity 0.18s ease-out;
}

.app-route-progress::before {
  content: '';
  display: block;
  width: 32%;
  height: 100%;
  border-radius: 999px;
  background: linear-gradient(90deg, rgba($primary-color, 0.08), $primary-color, rgba($primary-color, 0.1));
  box-shadow: 0 0 0 1px rgba($primary-color, 0.08);
  animation: app-route-progress 1s cubic-bezier(0.22, 1, 0.36, 1) infinite;
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
