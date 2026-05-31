<template>
  <el-drawer
    :model-value="modelValue"
    direction="ltr"
    size="min(200px, 80vw)"
    :with-header="false"
    class="admin-mobile-drawer"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <div class="drawer-shell">
      <div class="brand-panel mobile">
        <div class="brand-lockup-wrap">
          <img
            :src="sidebarLogo"
            :alt="appStore.siteName"
            class="brand-lockup"
          />
          <span class="brand-subtitle">管理控制台</span>
        </div>
      </div>

      <el-scrollbar class="sidebar-scroll mobile">
        <section
          v-for="section in navigationSections"
          :key="section.key || section.label"
          class="menu-section"
        >
          <p class="menu-section-title">{{ section.label }}</p>

          <el-menu
            :default-active="activeMenu"
            :default-openeds="defaultOpeneds"
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
  </el-drawer>
</template>

<script setup>
import { useAppStore } from '@/stores/app'

defineProps({
  modelValue: { type: Boolean, default: false },
  navigationSections: { type: Array, required: true },
  activeMenu: { type: String, required: true },
  defaultOpeneds: { type: Array, default: () => [] },
})

const emit = defineEmits(['update:modelValue', 'menu-select'])

const appStore = useAppStore()
const sidebarLogo = '/branding/logo.svg'
</script>

<style lang="scss" scoped>
.drawer-shell {
  display: flex;
  flex-direction: column;
  height: 100%;
  padding: 12px 0;
  background: $bg-color-card;
}

.admin-mobile-drawer :deep(.el-drawer__body) {
  padding: 0 !important;
}

.brand-panel {
  display: flex;
  align-items: center;
  justify-content: flex-start;
}

.brand-panel.mobile {
  justify-content: flex-start;
  min-height: auto;
  padding: 0 0 8px;
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
  width: 160px;
  height: auto;
  max-height: 32px;
  object-fit: contain;
  object-position: left center;
}

.brand-subtitle {
  margin-top: 4px;
  color: $text-color-placeholder;
  font-size: 11px;
  line-height: 1.2;
  letter-spacing: 0.06em;
}

.sidebar-scroll.mobile {
  padding-right: 0;
  padding-left: 0;
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
</style>
