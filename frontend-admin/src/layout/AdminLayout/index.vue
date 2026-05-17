<template>
  <el-container class="admin-shell">
    <el-aside
      v-if="!isMobile"
      :width="appStore.sidebarCollapsed ? '72px' : '200px'"
      class="shell-sidebar"
    >
      <SidebarPanel
        :navigation-sections="navigationSections"
        :active-menu="activeMenu"
        :default-openeds="defaultOpeneds"
        @menu-select="handleMenuSelect"
      />
    </el-aside>

    <el-container class="shell-main">
      <TopBar
        :is-mobile="isMobile"
        :admin-display-name="adminDisplayName"
        :admin-account-subtitle="adminAccountSubtitle"
        :breadcrumb-items="breadcrumbItems"
        :current-page-title="currentPageTitle"
        @toggle-nav="toggleNavigation"
        @command="handleTopBarCommand"
      />

      <el-main ref="contentRef" class="shell-content">
        <div class="shell-stage">
          <router-view v-slot="{ Component }">
            <transition name="page-fade" mode="out-in">
              <component :is="Component" />
            </transition>
          </router-view>
        </div>
      </el-main>
    </el-container>

    <el-dialog
      v-model="profileDialogVisible"
      title="账号设置"
      width="460px"
      destroy-on-close
      @closed="handleProfileDialogClosed"
    >
      <el-form
        ref="profileFormRef"
        :model="profileForm"
        :rules="profileRules"
        label-width="88px"
      >
        <el-form-item label="昵称" prop="nickname">
          <el-input v-model="profileForm.nickname" maxlength="50" show-word-limit placeholder="请输入昵称" />
        </el-form-item>
        <el-form-item label="邮箱" prop="email">
          <el-input v-model="profileForm.email" maxlength="100" placeholder="请输入接收工单通知的邮箱" />
        </el-form-item>
      </el-form>

      <p class="profile-tip">客户提交工单或追加回复后，系统会将通知发送到该邮箱。</p>

      <template #footer>
        <div class="dialog-footer">
          <el-button @click="profileDialogVisible = false">取消</el-button>
          <el-button type="primary" :loading="profileSaving" @click="handleSaveProfile">保存</el-button>
        </div>
      </template>
    </el-dialog>

    <MobileDrawer
      v-model="mobileNavVisible"
      :navigation-sections="navigationSections"
      :active-menu="activeMenu"
      :default-openeds="defaultOpeneds"
      @menu-select="handleMenuSelect"
    />
  </el-container>
</template>

<script setup>
import { ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useAppStore } from '@/stores/app'
import { useAdminLayout } from './composables/useAdminLayout'
import { useProfileDialog } from './composables/useProfileDialog'
import SidebarPanel from './components/SidebarPanel.vue'
import TopBar from './components/TopBar.vue'
import MobileDrawer from './components/MobileDrawer.vue'

const appStore = useAppStore()
const contentRef = ref(null)
const route = useRoute()

watch(() => route.path, () => {
  if (contentRef.value?.$el) {
    contentRef.value.$el.scrollTop = 0
  }
})

const {
  isMobile,
  mobileNavVisible,
  navigationSections,
  activeMenu,
  defaultOpeneds,
  adminDisplayName,
  adminAccountSubtitle,
  breadcrumbItems,
  currentPageTitle,
  toggleNavigation,
  handleMenuSelect,
  handleLogout,
} = useAdminLayout()

const {
  profileDialogVisible,
  profileSaving,
  profileFormRef,
  profileForm,
  profileRules,
  openProfileDialog,
  handleProfileDialogClosed,
  handleSaveProfile,
} = useProfileDialog()

function handleTopBarCommand(command) {
  if (command === 'profile') {
    openProfileDialog()
    return
  }
  if (command === 'logout') {
    handleLogout()
  }
}
</script>

<style lang="scss" scoped>
.admin-shell {
  height: 100vh;
  overflow: hidden;
  background: $bg-color;
}

.shell-sidebar {
  border-right: 1px solid $divider-color;
  background: $bg-color-card;
  box-shadow: $shadow-xs;
  transition: width $duration-base $ease-standard;
}

.shell-main {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.shell-content {
  display: flex;
  flex-direction: column;
  min-width: 0;
  padding: 20px 24px 32px;
  overflow-y: auto;
  scroll-behavior: smooth;
}

.shell-stage {
  display: flex;
  flex: 1;
  flex-direction: column;
  min-height: 100%;
  min-width: 0;
  max-width: 1600px;
  width: 100%;
  margin: 0 auto;
  overflow-x: hidden;
}

.dialog-footer {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
}

.profile-tip {
  margin: 4px 0 0;
  color: $text-color-placeholder;
  font-size: 12px;
  line-height: 1.7;
}

.page-fade-enter-active {
  transition:
    opacity $duration-base $ease-standard,
    transform $duration-base $ease-standard;
}

.page-fade-leave-active {
  transition: opacity $duration-fast $ease-standard;
}

.page-fade-enter-from {
  opacity: 0;
  transform: translateY(6px);
}

.page-fade-leave-to {
  opacity: 0;
}

@include desktop-lg-and-below {
  .shell-content {
    padding: 16px 18px 24px;
  }
}

@include tablet-and-below {
  .shell-content {
    padding: 14px 16px 20px;
  }
}

@include mobile-and-below {
  .shell-content {
    padding: 12px 12px 20px;
  }
}
</style>
