<template>
  <div class="client-page profile-page">
    <ProfileLayout v-model="activeTab" :badge="notificationBadge">
      <template #profile>
        <div class="content-card">
          <div class="card-header">
            <div>
              <div class="header-title">
                <div class="title-bar"></div>
                <h3>个人资料</h3>
              </div>
              <p class="header-desc">维护公开资料与基础账户信息，昵称修改后会同步更新到客户中心展示。</p>
            </div>
            <div class="card-header__meta">基础信息</div>
          </div>

          <div class="card-body">
            <el-form
              ref="profileFormRef"
              :model="form"
              :rules="profileRules"
              label-width="92px"
              class="profile-form"
            >
              <el-form-item label="账户ID">
                <div class="input-with-btn">
                  <el-input :model-value="form.id" disabled />
                  <el-button class="copy-btn" @click="copyText(form.id)">复制</el-button>
                </div>
              </el-form-item>
              <el-form-item label="注册时间">
                <el-input :model-value="form.createdAt || '--'" disabled />
              </el-form-item>
              <el-form-item label="用户名" prop="nickname">
                <el-input
                  v-model="form.nickname"
                  maxlength="50"
                  show-word-limit
                  placeholder="请输入用户名"
                />
              </el-form-item>
              <el-form-item label="账户余额">
                <el-input :model-value="balanceText" disabled />
              </el-form-item>
              <el-form-item label="登录邮箱">
                <el-input :model-value="form.email || '--'" disabled />
              </el-form-item>
              <el-form-item label="账户状态">
                <el-tag :type="form.is_verified ? 'success' : 'info'" effect="light">
                  {{ form.is_verified ? '已实名' : '未实名' }}
                </el-tag>
              </el-form-item>
            </el-form>

            <div class="card-footer">
              <div class="footer-tip">保存后会立即更新当前账户资料。</div>
              <el-button type="primary" :loading="profileLoading" @click="handleUpdateProfile">
                保存资料
              </el-button>
            </div>
          </div>
        </div>
      </template>

      <template #security>
        <div class="security-panel">
          <div class="content-card">
            <div class="card-header">
              <div>
                <div class="header-title">
                  <div class="title-bar"></div>
                  <h3>账户安全</h3>
                </div>
                <p class="header-desc">管理登录凭证与安全通知，降低账户被盗用和信息丢失风险。</p>
              </div>
              <div class="card-header__meta">4 项设置</div>
            </div>

            <div class="card-body">
              <div class="security-list">
                <article
                  v-for="item in securityItems"
                  :key="item.key"
                  class="security-item"
                >
                  <div class="item-icon" :class="item.tone">
                    <el-icon><component :is="item.icon" /></el-icon>
                  </div>

                  <div class="item-info">
                    <div class="item-info__top">
                      <div class="item-name">{{ item.name }}</div>
                      <el-tag :type="item.tagType" effect="light">{{ item.tagLabel }}</el-tag>
                    </div>
                    <div class="item-desc">{{ item.desc }}</div>
                  </div>

                  <el-button
                    class="security-action"
                    type="primary"
                    text
                    @click="item.action"
                  >
                    {{ item.actionLabel }}
                  </el-button>
                </article>
              </div>
            </div>
          </div>
        </div>
      </template>

      <template #agent>
        <AgentPanel />
      </template>

      <template #notification>
        <NotificationPanel />
      </template>
    </ProfileLayout>

    <SecurityDialogs
      v-model:show-password-dialog="showPasswordDialog"
      v-model:show-phone-dialog="showPhoneDialog"
      v-model:show-email-dialog="showEmailDialog"
      v-model:show-verified-info-dialog="showVerifiedInfoDialog"
      v-model:show-verification-dialog="showVerificationDialog"
      :password-loading="passwordLoading"
      :password-form-ref="passwordFormRef"
      :password-form="passwordForm"
      :password-rules="passwordRules"
      :phone-loading="phoneLoading"
      :phone-code-sending="phoneCodeSending"
      :code-countdown="codeCountdown"
      :phone-form-ref="phoneFormRef"
      :phone-form="phoneForm"
      :phone-rules="phoneRules"
      :email-loading="emailLoading"
      :email-code-sending="emailCodeSending"
      :email-code-countdown="emailCodeCountdown"
      :email-form-ref="emailFormRef"
      :email-form="emailForm"
      :email-rules="emailRules"
      :verification-loading="verificationLoading"
      :checking-status="checkingStatus"
      :verification-url="verificationUrl"
      :can-restart-verification="canRestartVerification"
      :verification-form-ref="verificationFormRef"
      :verification-form="verificationForm"
      :verification-rules="verificationRules"
      :real-name="form.real_name"
      :id-card-masked="form.id_card_masked"
      @change-password="handleChangePassword(router)"
      @send-phone-code="handleSendPhoneCode"
      @change-phone="handleChangePhone"
      @send-email-code="handleSendEmailCode"
      @change-email="handleChangeEmail"
      @submit-verification="handleVerification"
      @refresh-verification-link="refreshVerificationLink"
      @check-status="checkVerificationStatus(false)"
      @restart-verification="handleRestartVerification"
    />
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import { CircleCheck, Iphone, Message, Lock } from '@element-plus/icons-vue'
import ProfileLayout from '@/views/client/Profile/components/ProfileLayout.vue'
import AgentPanel from '@/views/client/Profile/components/AgentPanel.vue'
import NotificationPanel from '@/views/client/Profile/components/NotificationPanel.vue'
import SecurityDialogs from '@/views/client/Profile/components/SecurityDialogs.vue'
import { useProfileForm } from '@/views/client/Profile/composables/useProfileForm'
import { useSecurityDialogs } from '@/views/client/Profile/composables/useSecurityDialogs'

const activeTab = ref('profile')

const {
  form,
  profileFormRef,
  profileLoading,
  profileRules,
  copyText,
  handleUpdateProfile,
  postCodeWithFallback,
  runWithCaptcha,
  router,
  userStore,
} = useProfileForm()

const {
  showPasswordDialog,
  passwordLoading,
  passwordFormRef,
  passwordForm,
  passwordRules,
  showPhoneDialog,
  phoneLoading,
  phoneCodeSending,
  codeCountdown,
  phoneFormRef,
  phoneForm,
  phoneRules,
  openPhoneDialog,
  handleSendPhoneCode,
  handleChangePhone,
  showEmailDialog,
  emailLoading,
  emailCodeSending,
  emailCodeCountdown,
  emailFormRef,
  emailForm,
  emailRules,
  openEmailDialog,
  handleSendEmailCode,
  handleChangeEmail,
  showVerificationDialog,
  showVerifiedInfoDialog,
  verificationLoading,
  checkingStatus,
  verificationUrl,
  canRestartVerification,
  verificationFormRef,
  verificationForm,
  verificationRules,
  handleVerification,
  refreshVerificationLink,
  checkVerificationStatus,
  handleRestartVerification,
  handleChangePassword,
} = useSecurityDialogs(form, postCodeWithFallback, runWithCaptcha, userStore)

const balanceText = computed(() => `¥ ${form.balance || '0.00'}`)

const notificationBadge = computed(() => 0)

const securityItems = computed(() => [
  {
    key: 'verification',
    icon: CircleCheck,
    tone: form.is_verified ? 'success' : 'warning',
    name: '实名认证',
    desc: form.real_name
      ? `${form.real_name}${form.id_card_masked ? ` · ${form.id_card_masked}` : ''}`
      : '完成实名认证后，可提升账户可信度与业务可用范围',
    tagType: form.is_verified ? 'success' : 'warning',
    tagLabel: form.is_verified ? '已完成' : '待处理',
    actionLabel: form.is_verified ? '查看资料' : '立即认证',
    action: () => {
      if (form.is_verified) {
        showVerifiedInfoDialog.value = true
        return
      }
      showVerificationDialog.value = true
    },
  },
  {
    key: 'phone',
    icon: Iphone,
    tone: form.phone ? 'success' : 'warning',
    name: '安全手机',
    desc: form.phone || '绑定手机号后，可用于验证码接收和安全校验',
    tagType: form.phone ? 'success' : 'warning',
    tagLabel: form.phone ? '已绑定' : '未绑定',
    actionLabel: form.phone ? '更换手机' : '立即绑定',
    action: openPhoneDialog,
  },
  {
    key: 'email',
    icon: Message,
    tone: form.email ? 'success' : 'warning',
    name: '安全邮箱',
    desc: form.email || '建议绑定常用邮箱，用于接收通知与安全提醒',
    tagType: form.email ? 'success' : 'warning',
    tagLabel: form.email ? '已绑定' : '未绑定',
    actionLabel: form.email ? '更换邮箱' : '立即绑定',
    action: openEmailDialog,
  },
  {
    key: 'password',
    icon: Lock,
    tone: 'success',
    name: '登录密码',
    desc: '建议定期更新密码，并避免与其他平台共用同一组凭证',
    tagType: 'success',
    tagLabel: '已设置',
    actionLabel: '修改密码',
    action: () => {
      showPasswordDialog.value = true
    },
  },
])
</script>

<style lang="scss" scoped>
.profile-page {
  padding: 0;
}

.content-card {
  background: #fff;
  border-radius: $base-border-radius;
  box-shadow: $shadow-sm;
  border: 1px solid $border-color;
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

.profile-form {
  max-width: 100%;

  :deep(.el-form-item) {
    margin-bottom: 20px;
  }

  :deep(.el-form-item__label) {
    color: $text-color-secondary;
    font-size: 13px;
  }

  :deep(.el-input__wrapper) {
    min-height: 40px;
  }
}

.input-with-btn {
  display: flex;
  gap: 8px;

  .el-input {
    flex: 1;
  }
}

.copy-btn {
  background: $color-primary-soft;
  border-color: $color-primary-border;
  color: $color-primary;

  &:hover {
    background: $color-primary;
    border-color: $color-primary;
    color: #fff;
  }
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
}

.security-panel {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.security-list {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.security-item {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 18px 20px;
  border: 1px solid $divider-color;
  border-radius: 14px;
  background: linear-gradient(180deg, rgba(248, 250, 252, 0.76), #fff);
  transition:
    border-color $motion-fast ease,
    box-shadow $motion-fast ease,
    transform $motion-fast ease;

  &:hover {
    border-color: $color-primary-border;
    box-shadow: 0 10px 24px rgba(15, 23, 42, 0.05);
    transform: translateY(-1px);
  }
}

.item-icon {
  width: 46px;
  height: 46px;
  border-radius: 14px;
  background: $color-primary-soft;
  color: $color-primary;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;

  .el-icon {
    font-size: 20px;
  }

  &.success {
    background: $color-success-soft;
    color: $color-success;
  }

  &.warning {
    background: $color-warning-soft;
    color: $color-warning;
  }
}

.item-info {
  flex: 1;
  min-width: 0;
}

.item-info__top {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}

.item-name {
  font-size: 15px;
  font-weight: 600;
  color: $text-color-primary;
}

.item-desc {
  margin-top: 6px;
  font-size: 13px;
  color: $text-color-secondary;
  line-height: 1.7;
}

.security-action {
  flex-shrink: 0;
}

@media (max-width: 640px) {
  .content-card {
    border-radius: 10px;
  }

  .card-header {
    flex-direction: column;
    align-items: flex-start;
    padding: 16px;
    gap: 8px;
  }

  .card-header__meta {
    display: none;
  }

  .header-title {
    gap: 8px;

    h3 { font-size: 15px; }
  }

  .title-bar {
    width: 3px;
    height: 16px;
  }

  .header-desc {
    margin: 4px 0 0 11px;
    font-size: 12px;
  }

  .card-body {
    padding: 16px;
  }

  .profile-form {
    :deep(.el-form-item) {
      margin-bottom: 14px;
      flex-direction: column;
    }

    :deep(.el-form-item__label) {
      font-size: 12px;
      width: auto !important;
      text-align: left;
      padding-right: 0;
      padding-bottom: 4px;
    }

    :deep(.el-form-item__content) {
      margin-left: 0 !important;
    }

    :deep(.el-input__wrapper) {
      min-height: 36px;
    }
  }

  .input-with-btn {
    flex-direction: column;
  }

  .copy-btn {
    width: 100%;
  }

  .card-footer {
    flex-direction: column;
    align-items: stretch;
    gap: 12px;
    margin-top: 20px;
    padding-top: 16px;

    .el-button { width: 100%; }
  }

  .footer-tip {
    font-size: 12px;
    text-align: center;
  }

  /* Security items — compact card style */
  .security-list {
    gap: 10px;
  }

  .security-item {
    gap: 10px;
    padding: 12px;
    border-radius: 10px;

    &:hover {
      transform: none;
      box-shadow: none;
    }
  }

  .item-icon {
    width: 34px;
    height: 34px;
    border-radius: 8px;

    .el-icon { font-size: 15px; }
  }

  .item-info {
    flex: 1;
    min-width: 0;
  }

  .item-info__top {
    gap: 6px;
  }

  .item-name {
    font-size: 13px;
  }

  .item-desc {
    margin-top: 2px;
    font-size: 12px;
    line-height: 1.4;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  .security-action {
    flex-shrink: 0;

    :deep(.el-button) {
      font-size: 12px;
      padding: 4px 10px;
    }
  }
}
</style>
