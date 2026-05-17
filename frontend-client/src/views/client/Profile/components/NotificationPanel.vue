<template>
  <div class="content-card">
    <div class="card-header">
      <div>
        <div class="header-title">
          <div class="title-bar"></div>
          <h3>消息提醒</h3>
        </div>
        <p class="header-desc">按场景管理安全通知和业务消息，重要提醒建议保持开启。</p>
      </div>
      <div class="card-header__meta">6 项设置</div>
    </div>

    <div class="card-body">
      <section class="notification-summary">
        <div class="notification-summary__item">
          <span>已开启</span>
          <strong>{{ enabledCount }}</strong>
        </div>
        <div class="notification-summary__item">
          <span>安全提醒</span>
          <strong>建议全部开启</strong>
        </div>
      </section>

      <div class="subscribe-list">
        <div
          v-for="item in notificationList"
          :key="item.key"
          class="subscribe-item"
        >
          <div class="item-info">
            <div class="item-head">
              <div class="item-name">{{ item.name }}</div>
              <el-tag
                :type="item.enabled ? 'success' : 'info'"
                effect="light"
                size="small"
              >
                {{ item.enabled ? '已开启' : '已关闭' }}
              </el-tag>
            </div>
            <div class="item-desc">{{ item.desc }}</div>
          </div>
          <el-switch
            v-model="item.enabled"
            @change="handleChange(item)"
          />
        </div>
      </div>

      <div class="card-footer">
        <div class="footer-tip">关闭安全提醒可能会错过密码、邮箱或手机号变更通知。</div>
        <el-button type="primary" :loading="saving" @click="handleSave">保存设置</el-button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { ElMessage } from 'element-plus'
import { clientAuthApi } from '@/api/auth'
import { useUserStore } from '@/stores/user'

const saving = ref(false)
const userStore = useUserStore()

const notificationList = reactive([
  {
    key: 'login_notify',
    name: '账号登录提醒',
    desc: '每次账户成功登录后，向绑定邮箱发送登录安全提醒。',
    enabled: false,
  },
  {
    key: 'login_location_alert',
    name: '异地登录提醒',
    desc: '检测到新的登录 IP 环境时，额外发送一次异地登录风险提醒。',
    enabled: false,
  },
  {
    key: 'password_change_alert',
    name: '更改密码提醒',
    desc: '账户密码修改成功后，立即发送安全提醒邮件。',
    enabled: false,
  },
  {
    key: 'phone_change_alert',
    name: '更改手机号提醒',
    desc: '安全手机号发生变更时，及时发送变更提醒。',
    enabled: false,
  },
  {
    key: 'email_change_alert',
    name: '更改邮箱提醒',
    desc: '安全邮箱发生变更时，向原邮箱和新邮箱发送提醒。',
    enabled: false,
  },
  {
    key: 'marketing_alert',
    name: '营销提醒接收',
    desc: '接收产品更新、活动优惠和运营消息。',
    enabled: false,
  },
])

const enabledCount = computed(() => notificationList.filter((item) => item.enabled).length)

function handleChange(item) {
  return item
}

async function handleSave() {
  saving.value = true
  try {
    const settings = notificationList.reduce((acc, item) => {
      acc[item.key] = item.enabled
      return acc
    }, {})
    await clientAuthApi.updateNotificationPreferences(settings)
    if (userStore.info) {
      userStore.info = {
        ...userStore.info,
        ...Object.fromEntries(
          notificationList.map((item) => [item.key, item.enabled ? 1 : 0])
        ),
        login_email_alert: notificationList.find((item) => item.key === 'login_notify')?.enabled ? 1 : 0,
      }
    }
    ElMessage.success('设置保存成功')
  } catch (error) {
    ElMessage.error(error.message || '保存失败')
  } finally {
    saving.value = false
  }
}

async function syncSettingsFromUser() {
  const [{ data: settings }] = await Promise.all([
    clientAuthApi.notificationPreferences(),
    !userStore.info ? userStore.fetchUserInfo('client') : Promise.resolve(),
  ])

  notificationList.forEach((item) => {
    item.enabled = !!settings?.[item.key]
  })
}

onMounted(() => {
  syncSettingsFromUser().catch(() => {
    // 静默失败，使用默认值
  })
})
</script>

<style lang="scss" scoped>
.content-card {
  background: #fff;
  border-radius: $base-border-radius;
  box-shadow: $shadow-sm;
  border: 1px solid $border-color;
  overflow: hidden;
}

.card-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  padding: 28px 32px;
  border-bottom: 1px solid $border-color;
  background: linear-gradient(to right, #fff 0%, $bg-color-soft 100%);
}

.card-header__meta {
  padding: 6px 10px;
  border-radius: 999px;
  background: rgba(22, 93, 255, 0.08);
  color: $color-primary;
  font-size: 12px;
  font-weight: 600;
  white-space: nowrap;
}

.header-title {
  display: flex;
  align-items: center;
  gap: 10px;

  h3 {
    margin: 0;
    font-size: 18px;
    font-weight: 600;
    color: $text-color-primary;
  }
}

.title-bar {
  width: 4px;
  height: 20px;
  background: $color-primary;
  border-radius: 2px;
}

.header-desc {
  margin: 8px 0 0 14px;
  font-size: 13px;
  color: $text-color-secondary;
  line-height: 1.7;
}

.card-body {
  padding: 32px;
}

.notification-summary {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 14px;
  margin-bottom: 22px;
}

.notification-summary__item {
  padding: 16px 18px;
  border: 1px solid $divider-color;
  border-radius: 14px;
  background: $bg-color-soft;

  span {
    display: block;
    color: $text-color-secondary;
    font-size: 12px;
  }

  strong {
    display: block;
    margin-top: 8px;
    color: $text-color-primary;
    font-size: 16px;
    font-weight: 600;
  }
}

.subscribe-list {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.subscribe-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 18px;
  padding: 18px 20px;
  border: 1px solid $divider-color;
  border-radius: 14px;
  background: linear-gradient(180deg, rgba(248, 250, 252, 0.74), #fff);
}

.item-info {
  flex: 1;
  min-width: 0;
}

.item-head {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 10px;
}

.item-name {
  font-size: 14px;
  font-weight: 600;
  color: $text-color-primary;
}

.item-desc {
  margin-top: 6px;
  font-size: 13px;
  color: $text-color-secondary;
  line-height: 1.7;
}

.card-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  margin-top: 28px;
  padding-top: 24px;
  border-top: 1px dashed $border-color;
}

.footer-tip {
  color: $text-color-secondary;
  font-size: 13px;
  line-height: 1.7;
}

@media (max-width: 640px) {
  .card-header,
  .card-footer,
  .subscribe-item {
    flex-direction: column;
    align-items: flex-start;
  }

  .card-header,
  .card-body {
    padding: 20px;
  }

  .notification-summary {
    grid-template-columns: 1fr;
  }
}
</style>
