<template>
  <div class="client-page verification-page">
    <section class="verification-status-card">
      <div class="verification-status-card__main">
        <div>
          <span class="verification-status-card__label">当前状态</span>
          <h3>{{ form.is_verified ? '已完成实名认证' : '尚未完成实名认证' }}</h3>
          <p>
            {{ form.is_verified
              ? '当前账户已经通过实名校验，可继续购买需要实名的产品。'
              : '完成实名后，可继续购买受实名限制的商品并提升账户安全性。' }}
          </p>
        </div>
        <el-tag :type="form.is_verified ? 'success' : 'warning'" effect="light" round>
          {{ form.is_verified ? '已认证' : '待认证' }}
        </el-tag>
      </div>

      <div class="verification-meta-grid">
        <article class="verification-meta-item">
          <span>认证姓名</span>
          <strong>{{ form.real_name || '--' }}</strong>
        </article>
        <article class="verification-meta-item">
          <span>身份证号</span>
          <strong>{{ form.id_card_masked || '--' }}</strong>
        </article>
        <article class="verification-meta-item">
          <span>登录邮箱</span>
          <strong>{{ form.email || '--' }}</strong>
        </article>
        <article class="verification-meta-item">
          <span>绑定手机</span>
          <strong>{{ form.phone || '--' }}</strong>
        </article>
      </div>
    </section>

    <section class="verification-guide-grid">
      <article class="verification-guide-card">
        <h3>认证流程</h3>
        <ol>
          <li>填写真实姓名与身份证号。</li>
          <li>使用支付宝扫码完成实名校验。</li>
          <li>认证成功后自动刷新账户状态。</li>
        </ol>
      </article>
      <article class="verification-guide-card">
        <h3>温馨提示</h3>
        <ul>
          <li>请确保姓名与身份证号真实有效。</li>
          <li>认证失败后可重新获取二维码再次提交。</li>
          <li>如实名信息已存在，可在个人资料页查看。</li>
        </ul>
      </article>
    </section>

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

<script setup lang="ts">
import { useRouter } from 'vue-router'
import SecurityDialogs from '@/views/client/Profile/components/SecurityDialogs.vue'
import { useProfileForm } from '@/views/client/Profile/composables/useProfileForm'
import { useSecurityDialogs } from '@/views/client/Profile/composables/useSecurityDialogs'

const router = useRouter()
const {
  form,
  postCodeWithFallback,
  runWithCaptcha,
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
  handleSendPhoneCode,
  handleChangePhone,
  showEmailDialog,
  emailLoading,
  emailCodeSending,
  emailCodeCountdown,
  emailFormRef,
  emailForm,
  emailRules,
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
</script>

<style scoped lang="scss">
.verification-status-card,
.verification-guide-card {
  border: 1px solid $border-color;
  border-radius: $base-border-radius;
  background: #fff;
  box-shadow: $shadow-sm;
}

.verification-status-card {
  padding: 24px;
}

.verification-status-card__main {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;

  h3 {
    margin-top: 8px;
    color: $text-color-primary;
    font-size: 22px;
    font-weight: 700;
  }

  p {
    margin-top: 10px;
    color: $text-color-secondary;
    font-size: 13px;
    line-height: 1.8;
  }
}

.verification-status-card__label {
  color: $text-color-secondary;
  font-size: 13px;
}

.verification-meta-grid,
.verification-guide-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 16px;
}

.verification-meta-grid {
  margin-top: 20px;
}

.verification-meta-item,
.verification-guide-card {
  padding: 18px;
  background: $bg-color-soft;
}

.verification-meta-item {
  border-radius: $sm-border-radius;

  span {
    display: block;
    color: $text-color-secondary;
    font-size: 12px;
  }

  strong {
    display: block;
    margin-top: 8px;
    color: $text-color-primary;
    font-size: 15px;
    font-weight: 600;
    word-break: break-all;
  }
}

.verification-guide-card {
  h3 {
    margin: 0 0 14px;
    color: $text-color-primary;
    font-size: 16px;
    font-weight: 600;
  }

  ol,
  ul {
    display: flex;
    flex-direction: column;
    gap: 10px;
    color: $text-color-secondary;
    font-size: 13px;
    line-height: 1.7;
    padding-left: 18px;
    list-style: auto;
  }
}

@media (max-width: 767px) {
  .verification-status-card__main,
  .verification-meta-grid,
  .verification-guide-grid {
    grid-template-columns: 1fr;
    display: grid;
  }
}
</style>
