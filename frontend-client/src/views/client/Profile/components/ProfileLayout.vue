<template>
  <div class="profile-layout">
    <aside class="profile-sidebar">
      <div class="sidebar-card">
        <div class="sidebar-header">
          <div class="sidebar-header__icon">
            <el-icon><User /></el-icon>
          </div>
          <div class="sidebar-header__copy">
            <span>账户中心</span>
            <small>资料、安全与消息设置</small>
          </div>
        </div>

        <ul class="nav-list">
          <li v-for="item in navItems" :key="item.key">
            <button
              type="button"
              class="nav-item"
              :class="{ active: activeTab === item.key }"
              @click="activeTab = item.key"
            >
              <span class="nav-item__icon">
                <el-icon><component :is="item.icon" /></el-icon>
              </span>
              <span class="nav-item__copy">
                <strong>{{ item.label }}</strong>
                <small>{{ item.desc }}</small>
              </span>
              <el-badge v-if="item.badge" :value="item.badge" class="nav-badge" />
            </button>
          </li>
        </ul>
      </div>
    </aside>

    <main class="profile-main">
      <slot :name="activeTab" />
    </main>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { User, Lock, Connection, Bell } from '@element-plus/icons-vue'

const props = defineProps({
  modelValue: {
    type: String,
    default: 'profile',
  },
  badge: {
    type: Number,
    default: 0,
  },
})

const emit = defineEmits(['update:modelValue'])

const activeTab = computed({
  get: () => props.modelValue,
  set: (val) => emit('update:modelValue', val),
})

const navItems = computed(() => [
  { key: 'profile', label: '个人资料', desc: '昵称、邮箱与账户基础信息', icon: User },
  { key: 'security', label: '账户安全', desc: '密码、实名与绑定凭证管理', icon: Lock },
  { key: 'agent', label: '合作代理', desc: '代理权益与合作能力入口', icon: Connection },
  { key: 'notification', label: '消息提醒', desc: '安全通知与营销偏好设置', icon: Bell, badge: props.badge || null },
])
</script>

<style lang="scss" scoped>
.profile-layout {
  display: flex;
  gap: 28px;
  max-width: 1200px;
  margin: 0 auto;
  padding: 24px 24px 40px;
}

.profile-sidebar {
  width: 280px;
  flex-shrink: 0;
}

.sidebar-card {
  background: #fff;
  border-radius: $lg-border-radius;
  box-shadow: $shadow-sm;
  border: 1px solid $border-color;
  overflow: hidden;
  position: sticky;
  top: 24px;
}

.sidebar-header {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 22px 22px 18px;
  border-bottom: 1px solid $border-color;
  background: linear-gradient(180deg, rgba(248, 250, 252, 0.96), #fff);
}

.sidebar-header__icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 42px;
  height: 42px;
  border-radius: 14px;
  background: $color-primary-soft;
  color: $color-primary;
  flex-shrink: 0;

  .el-icon {
    font-size: 20px;
  }
}

.sidebar-header__copy {
  display: flex;
  flex-direction: column;
  gap: 4px;
  min-width: 0;

  span {
    font-size: 15px;
    font-weight: 700;
    color: $text-color-primary;
  }

  small {
    color: $text-color-secondary;
    font-size: 12px;
    line-height: 1.5;
  }
}

.nav-list {
  list-style: none;
  padding: 12px;
}

.nav-item {
  position: relative;
  display: flex;
  align-items: center;
  gap: 12px;
  width: 100%;
  padding: 14px;
  border: none;
  border-radius: 14px;
  background: transparent;
  color: $text-color-secondary;
  text-align: left;
  cursor: pointer;
  transition:
    background-color $motion-base ease,
    color $motion-base ease,
    transform $motion-fast ease,
    box-shadow $motion-fast ease;

  &:hover {
    background: $bg-color-soft;
    color: $text-color-primary;
    transform: translateY(-1px);
  }

  &.active {
    background: linear-gradient(135deg, rgba(232, 241, 255, 0.98), #fff);
    color: $color-primary;
    box-shadow: inset 0 0 0 1px rgba(201, 219, 255, 0.85);

    &::before {
      content: '';
      position: absolute;
      left: -12px;
      top: 18px;
      bottom: 18px;
      width: 3px;
      border-radius: 999px;
      background: $color-primary;
    }

    .nav-item__icon {
      background: rgba(22, 93, 255, 0.14);
      color: $color-primary;
    }
  }
}

.nav-item__icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 38px;
  height: 38px;
  border-radius: 12px;
  background: $bg-color-soft;
  color: inherit;
  flex-shrink: 0;

  .el-icon {
    font-size: 18px;
  }
}

.nav-item__copy {
  display: flex;
  flex: 1;
  min-width: 0;
  flex-direction: column;
  gap: 4px;

  strong {
    font-size: 14px;
    font-weight: 600;
    color: inherit;
  }

  small {
    color: $text-color-secondary;
    font-size: 12px;
    line-height: 1.5;
  }
}

.nav-badge {
  margin-left: auto;
}

.profile-main {
  flex: 1;
  min-width: 0;
}

@media (max-width: 900px) {
  .profile-layout {
    flex-direction: column;
    padding: 20px 16px 32px;
    gap: 16px;
  }

  .profile-sidebar {
    width: 100%;
  }

  .sidebar-card {
    position: static;
  }

  .sidebar-header {
    padding: 16px 16px 14px;
    gap: 12px;
  }

  .sidebar-header__icon {
    width: 36px;
    height: 36px;
    border-radius: 10px;

    .el-icon { font-size: 17px; }
  }

  .sidebar-header__copy {
    span { font-size: 14px; }
    small { font-size: 11px; }
  }

  .nav-list {
    display: flex;
    flex-wrap: nowrap;
    overflow-x: auto;
    gap: 0;
    padding: 0;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: none;
    &::-webkit-scrollbar { display: none; }
  }

  .nav-list li {
    flex-shrink: 0;
  }

  .nav-item {
    flex-direction: column;
    align-items: center;
    gap: 4px;
    padding: 12px 16px;
    border-radius: 0;
    min-width: 72px;

    &:hover {
      transform: none;
    }

    &.active {
      background: transparent;
      box-shadow: none;
      border-bottom: 2px solid $color-primary;
      border-radius: 0;

      &::before { display: none; }
    }
  }

  .nav-item__icon {
    width: 32px;
    height: 32px;
    border-radius: 8px;

    .el-icon { font-size: 16px; }
  }

  .nav-item__copy {
    align-items: center;
    gap: 0;

    strong { font-size: 12px; }
    small { display: none; }
  }
}

@media (max-width: 640px) {
  .profile-layout {
    padding: 12px 12px 24px;
    gap: 12px;
  }

  .sidebar-header {
    display: none;
  }

  .sidebar-card {
    border-radius: 10px;
  }

  .nav-item {
    padding: 10px 14px;
    min-width: 64px;
  }

  .nav-item__icon {
    width: 28px;
    height: 28px;
    border-radius: 6px;

    .el-icon { font-size: 14px; }
  }

  .nav-item__copy strong { font-size: 11px; }
}
</style>
