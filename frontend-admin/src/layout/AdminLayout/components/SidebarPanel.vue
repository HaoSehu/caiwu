<template>
  <div class="sidebar-shell">
    <div class="brand-panel">
      <img
        v-if="appStore.sidebarCollapsed"
        :src="sidebarCompactLogo"
        :alt="appStore.siteName"
        class="brand-mini-logo"
      />

      <div v-else class="brand-lockup-wrap">
        <img
          :src="sidebarLogo"
          :alt="appStore.siteName"
          class="brand-lockup"
        />
        <span class="brand-subtitle">管理控制台</span>
      </div>
    </div>

    <el-scrollbar class="sidebar-scroll">
      <section
        v-for="section in navigationSections"
        :key="section.key || section.label"
        class="menu-section"
      >
        <p v-show="!appStore.sidebarCollapsed" class="menu-section-title">{{ section.label }}</p>

        <el-menu
          :key="`${section.key || section.label}-${appStore.sidebarCollapsed}-${defaultOpeneds.join(',')}`"
          :default-active="activeMenu"
          :default-openeds="defaultOpeneds"
          :collapse="appStore.sidebarCollapsed"
          :collapse-transition="false"
          :unique-opened="true"
          router
          class="sidebar-menu"
          @select="emit('menu-select')"
        >
          <template v-for="item in section.items" :key="item.index">
            <el-menu-item v-if="!item.children" :index="item.index" class="sidebar-menu-item">
              <el-icon><component :is="item.icon" /></el-icon>
              <template #title>{{ item.title }}</template>
            </el-menu-item>

            <el-sub-menu v-else :index="item.index" class="sidebar-submenu">
              <template #title>
                <el-icon><component :is="item.icon" /></el-icon>
                <span>{{ item.title }}</span>
              </template>
              <el-menu-item
                v-for="child in item.children"
                :key="child.index"
                :index="child.index"
                class="sidebar-submenu-item"
              >
                {{ child.title }}
              </el-menu-item>
            </el-sub-menu>
          </template>
        </el-menu>
      </section>
    </el-scrollbar>
  </div>
</template>

<script setup>
import { useAppStore } from '@/stores/app'

defineProps({
  navigationSections: { type: Array, required: true },
  activeMenu: { type: String, required: true },
  defaultOpeneds: { type: Array, default: () => [] },
})

const emit = defineEmits(['menu-select'])

const appStore = useAppStore()
const sidebarLogo = '/branding/logo.svg'
const sidebarCompactLogo = '/branding/logo1.svg'
</script>

<style lang="scss" scoped>
.sidebar-shell {
  display: flex;
  flex-direction: column;
  height: 100%;
}

.brand-panel {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 48px;
  padding: 10px 16px 6px;
}

.brand-lockup-wrap {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  width: 100%;
  min-width: 0;
}

.brand-lockup {
  display: block;
  width: min(160px, 100%);
  height: auto;
  max-height: 32px;
  object-fit: contain;
  object-position: left center;
}

.brand-mini-logo {
  display: block;
  width: 28px;
  height: 28px;
  object-fit: contain;
}

.brand-subtitle {
  margin-top: 4px;
  color: $text-color-secondary;
  font-size: 12px;
  line-height: 1.2;
}

.sidebar-scroll {
  flex: 1;
  padding: 6px 10px 10px;

  :deep(.el-scrollbar__bar.is-vertical) {
    width: 4px;
  }
}

.menu-section + .menu-section {
  margin-top: 8px;
}

.menu-section-title {
  padding: 0 10px 4px;
  color: $text-color-placeholder;
  font-size: 11px;
  font-weight: 600;
}

.sidebar-menu {
  border: none;
  background: transparent;
}

.sidebar-menu :deep(.el-menu) {
  border: none;
  background: transparent;
}

.sidebar-menu :deep(.el-menu-item),
.sidebar-menu :deep(.el-sub-menu__title) {
  height: 36px;
  margin-bottom: 0;
  padding: 0 12px;
  border-radius: 6px;
  color: $text-color-secondary;
  line-height: 36px;
  font-size: 13px;
  font-weight: 500;
  transition:
    background-color $duration-fast $ease-standard,
    color $duration-fast $ease-standard,
    box-shadow $duration-fast $ease-standard;
}

.sidebar-menu :deep(.el-menu-item:hover),
.sidebar-menu :deep(.el-sub-menu__title:hover) {
  background: $bg-color-soft;
  color: $text-color-primary;
}

.sidebar-menu :deep(.el-menu-item.is-active) {
  background: $color-primary-soft;
  color: $color-primary;
  font-weight: 600;
}

.sidebar-menu :deep(.el-sub-menu .el-menu) {
  margin: 2px 0 4px;
  padding: 3px;
  border-radius: $base-border-radius;
  background: $bg-color-soft;
}

.sidebar-menu :deep(.el-sub-menu .el-menu-item) {
  min-width: auto;
  height: 30px;
  margin: 0;
  padding-left: 40px !important;
  font-size: 12px;
  line-height: 30px;
}

.sidebar-menu :deep(.el-sub-menu.is-opened > .el-sub-menu__title) {
  background: $bg-color-soft;
  color: $text-color-primary;
}

.sidebar-menu :deep(.el-menu-item .el-icon),
.sidebar-menu :deep(.el-sub-menu__title .el-icon) {
  margin-right: 8px;
  font-size: 16px;
}

.sidebar-menu :deep(.el-sub-menu .el-menu-item.is-active) {
  box-shadow: none;
  background: rgba($color-primary, 0.08);
}

:deep(.el-menu--collapse) {
  .el-menu-item,
  .el-sub-menu__title {
    padding: 0 !important;
    justify-content: center;
  }
}
</style>
