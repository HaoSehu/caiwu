<template>
  <el-header class="shell-topbar">
    <div class="topbar-left">
      <button type="button" class="collapse-trigger" @click="emit('toggle-nav')">
        <el-icon>
          <Menu v-if="isMobile" />
          <Fold v-else-if="!appStore.sidebarCollapsed" />
          <Expand v-else />
        </el-icon>
      </button>

      <div class="page-context">
        <el-breadcrumb separator="/" class="page-breadcrumb">
          <el-breadcrumb-item :to="{ path: '/admin/dashboard' }">
            <el-icon><House /></el-icon>
          </el-breadcrumb-item>
          <el-breadcrumb-item v-for="item in breadcrumbItems" :key="item.path">
            {{ item.title }}
          </el-breadcrumb-item>
        </el-breadcrumb>
        <strong>{{ currentPageTitle }}</strong>
      </div>
    </div>

    <div class="topbar-right">
      <el-dropdown trigger="click" @command="handleCommand">
        <button type="button" class="account-entry">
          <el-avatar :size="34" class="account-avatar">
            {{ adminDisplayName.slice(0, 1).toUpperCase() }}
          </el-avatar>
          <span class="account-copy">
            <strong>{{ adminDisplayName }}</strong>
            <small>{{ adminAccountSubtitle }}</small>
          </span>
          <el-icon><ArrowDown /></el-icon>
        </button>
        <template #dropdown>
          <el-dropdown-menu>
            <el-dropdown-item command="profile">
              <el-icon><User /></el-icon>
              账号设置
            </el-dropdown-item>
            <el-dropdown-item command="logout">
              <el-icon><SwitchButton /></el-icon>
              退出登录
            </el-dropdown-item>
          </el-dropdown-menu>
        </template>
      </el-dropdown>
    </div>
  </el-header>
</template>

<script setup>
import {
  ArrowDown,
  Expand,
  Fold,
  House,
  Menu,
  SwitchButton,
  User,
} from '@element-plus/icons-vue'
import { useAppStore } from '@/stores/app'

defineProps({
  isMobile: { type: Boolean, default: false },
  adminDisplayName: { type: String, required: true },
  adminAccountSubtitle: { type: String, required: true },
  breadcrumbItems: { type: Array, default: () => [] },
  currentPageTitle: { type: String, default: '' },
})

const emit = defineEmits(['toggle-nav', 'command'])
const appStore = useAppStore()

function handleCommand(command) {
  emit('command', command)
}
</script>

<style lang="scss" scoped>
.shell-topbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  height: $navbar-height;
  padding: 0 20px;
  border-bottom: 1px solid $divider-color;
  background: $bg-color-card;
  position: sticky;
  top: 0;
  z-index: $zindex-navbar;
  backdrop-filter: saturate(180%) blur(8px);
}

.topbar-left {
  display: flex;
  flex: 1;
  align-items: center;
  gap: 12px;
  min-width: 0;
  overflow: hidden;
}

.topbar-right {
  display: flex;
  flex-shrink: 0;
  align-items: center;
  gap: 10px;
}

.collapse-trigger,
.account-entry {
  display: inline-flex;
  align-items: center;
  border: none;
  background: none;
  cursor: pointer;
  font-family: inherit;
}

.collapse-trigger {
  justify-content: center;
  width: 34px;
  height: 34px;
  border: 1px solid transparent;
  border-radius: $sm-border-radius;
  color: $text-color-secondary;
  transition:
    background-color $duration-fast $ease-standard,
    color $duration-fast $ease-standard,
    border-color $duration-fast $ease-standard;
}

.collapse-trigger:hover {
  border-color: $divider-color;
  background: $bg-color-soft;
  color: $text-color-primary;
}

.collapse-trigger:active {
  background: $bg-color-hover;
}

.page-context {
  display: flex;
  flex-direction: column;
  flex: 1;
  gap: 2px;
  min-width: 0;
  overflow: hidden;
}

.page-breadcrumb {
  font-size: 12px;
  line-height: 1.2;
}

.page-breadcrumb :deep(.el-breadcrumb__inner),
.page-breadcrumb :deep(.el-breadcrumb__separator),
.page-breadcrumb :deep(.el-breadcrumb__inner a) { color: $text-color-placeholder; }

.page-breadcrumb :deep(.el-breadcrumb__item:last-child .el-breadcrumb__inner) { color: $text-color-secondary; }

.page-context strong {
  overflow: hidden;
  color: $text-color-primary;
  font-size: 16px;
  font-weight: 600;
  text-overflow: ellipsis;
  white-space: nowrap;
  letter-spacing: -0.2px;
}

.account-entry {
  gap: 8px;
  padding: 3px 10px 3px 3px;
  border: 1px solid transparent;
  border-radius: 999px;
  color: $text-color-secondary;
  transition:
    background-color $duration-fast $ease-standard,
    border-color $duration-fast $ease-standard;
}

.account-entry:hover {
  border-color: $divider-color;
  background: $bg-color-soft;
}

.account-avatar {
  background: linear-gradient(135deg, $color-primary 0%, $color-primary-hover 100%);
  color: $text-color-inverse;
  font-weight: 600;
  letter-spacing: 0.5px;
}

.account-copy {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  min-width: 0;
  line-height: 1.2;
}

.account-copy strong { color: $text-color-primary; font-size: 13px; font-weight: 600; }

.account-copy small {
  display: block;
  max-width: 180px;
  margin-top: 2px;
  overflow: hidden;
  color: $text-color-placeholder;
  font-size: 11px;
  text-overflow: ellipsis;
  white-space: nowrap;
}

@include tablet-and-below {
  .page-breadcrumb { display: none; }
  .shell-topbar { padding: 0 16px; }
}

@include mobile-and-below {
  .shell-topbar { padding: 0 12px; gap: 8px; }
  .page-context strong { font-size: 14px; }
  .account-copy { display: none; }
  .collapse-trigger { width: 32px; height: 32px; }
}
</style>
