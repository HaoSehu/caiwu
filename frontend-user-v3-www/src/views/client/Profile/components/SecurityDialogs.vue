<template>
  <!-- 修改密码 -->
  <el-dialog :model-value="showPasswordDialog" @update:model-value="emit('update:showPasswordDialog', $event)" title="修改登录密码" width="500px">
    <el-form :model="passwordForm" :rules="passwordRules" ref="passwordFormRef" label-width="100px">
      <el-form-item label="原密码" prop="oldPassword">
        <el-input v-model="passwordForm.oldPassword" type="password" show-password />
      </el-form-item>
      <el-form-item label="新密码" prop="newPassword">
        <el-input v-model="passwordForm.newPassword" type="password" show-password />
      </el-form-item>
      <el-form-item label="确认密码" prop="confirmPassword">
        <el-input v-model="passwordForm.confirmPassword" type="password" show-password />
      </el-form-item>
    </el-form>
    <template #footer>
      <el-button @click="emit('update:showPasswordDialog', false)">取消</el-button>
      <el-button type="primary" @click="$emit('change-password')" :loading="passwordLoading">确定</el-button>
    </template>
  </el-dialog>

  <!-- 绑定手机 -->
  <el-dialog :model-value="showPhoneDialog" @update:model-value="emit('update:showPhoneDialog', $event)" width="360px" align-center class="bind-phone-dialog" :show-close="false">
    <div class="bind-phone-panel">
      <button class="dialog-close" type="button" @click="emit('update:showPhoneDialog', false)">
        <el-icon><Close /></el-icon>
      </button>
      <div class="dialog-top-icon"><el-icon><Phone /></el-icon></div>
      <div class="dialog-title">绑定手机</div>
      <el-form :model="phoneForm" :rules="phoneRules" ref="phoneFormRef" label-position="top" class="bind-phone-form">
        <el-form-item label="手机号" prop="phone">
          <el-input v-model="phoneForm.phone" placeholder="请输入手机号">
            <template #prefix><el-icon><Phone /></el-icon></template>
          </el-input>
        </el-form-item>
        <el-form-item label="短信验证码" prop="code">
          <div class="code-row">
            <el-input v-model="phoneForm.code" placeholder="请输入短信验证码">
              <template #prefix><el-icon><Grid /></el-icon></template>
            </el-input>
            <el-button
              class="send-code-btn"
              @click="$emit('send-phone-code')"
              :disabled="codeCountdown > 0"
              :loading="phoneCodeSending"
            >
              {{ codeCountdown > 0 ? `${codeCountdown}s` : '发送验证码' }}
            </el-button>
          </div>
        </el-form-item>
      </el-form>
      <el-button type="primary" class="confirm-btn" @click="$emit('change-phone')" :loading="phoneLoading">确认</el-button>
    </div>
  </el-dialog>

  <!-- 绑定邮箱 -->
  <el-dialog :model-value="showEmailDialog" @update:model-value="emit('update:showEmailDialog', $event)" width="360px" align-center class="bind-phone-dialog" :show-close="false">
    <div class="bind-phone-panel">
      <button class="dialog-close" type="button" @click="emit('update:showEmailDialog', false)">
        <el-icon><Close /></el-icon>
      </button>
      <div class="dialog-top-icon"><el-icon><Message /></el-icon></div>
      <div class="dialog-title">绑定邮箱</div>
      <el-form :model="emailForm" :rules="emailRules" ref="emailFormRef" label-position="top" class="bind-phone-form">
        <el-form-item label="邮箱" prop="email">
          <el-input v-model="emailForm.email" placeholder="请输入邮箱">
            <template #prefix><el-icon><Message /></el-icon></template>
          </el-input>
        </el-form-item>
        <el-form-item label="邮箱验证码" prop="code">
          <div class="code-row">
            <el-input v-model="emailForm.code" placeholder="请输入邮箱验证码">
              <template #prefix><el-icon><Grid /></el-icon></template>
            </el-input>
            <el-button
              class="send-code-btn"
              @click="$emit('send-email-code')"
              :disabled="emailCodeCountdown > 0"
              :loading="emailCodeSending"
            >
              {{ emailCodeCountdown > 0 ? `${emailCodeCountdown}s` : '发送验证码' }}
            </el-button>
          </div>
        </el-form-item>
      </el-form>
      <el-button type="primary" class="confirm-btn" @click="$emit('change-email')" :loading="emailLoading">确认</el-button>
    </div>
  </el-dialog>

  <!-- 实名信息展示 -->
  <el-dialog :model-value="showVerifiedInfoDialog" @update:model-value="emit('update:showVerifiedInfoDialog', $event)" title="实名信息" width="420px" align-center>
    <div class="verified-info-dialog">
      <div class="verified-info-icon">□</div>
      <div class="verified-info-title">实名信息</div>
      <div class="verified-info-row">姓名：{{ realName || '--' }}</div>
      <div class="verified-info-row">身份证号：{{ idCardMasked || '--' }}</div>
    </div>
  </el-dialog>

  <!-- 实名认证 -->
  <el-dialog :model-value="showVerificationDialog" @update:model-value="emit('update:showVerificationDialog', $event)" title="实名认证" width="500px">
    <el-form
      v-if="!verificationUrl"
      :model="verificationForm"
      :rules="verificationRules"
      ref="verificationFormRef"
      label-width="100px"
    >
      <el-form-item label="真实姓名" prop="realName">
        <el-input v-model="verificationForm.realName" placeholder="请输入真实姓名" />
      </el-form-item>
      <el-form-item label="身份证号" prop="idCard">
        <el-input v-model="verificationForm.idCard" placeholder="请输入身份证号" maxlength="18" />
      </el-form-item>
    </el-form>
    <div v-else class="verification-qrcode-panel">
      <qrcode-vue :value="verificationUrl" :size="300" level="H" />
      <p class="verification-qrcode-tip">请使用支付宝扫描二维码完成认证</p>
      <div class="verification-link-actions">
        <el-button @click="$emit('refresh-verification-link')" :loading="verificationLoading">刷新二维码</el-button>
        <el-button type="primary" @click="$emit('check-status')" :loading="checkingStatus">查询认证状态</el-button>
        <el-button v-if="canRestartVerification" type="warning" @click="$emit('restart-verification')" :loading="verificationLoading">重新认证</el-button>
      </div>
    </div>
    <template #footer>
      <el-button @click="emit('update:showVerificationDialog', false)">取消</el-button>
      <el-button v-if="!verificationUrl" type="primary" @click="$emit('submit-verification')" :loading="verificationLoading">提交认证</el-button>
    </template>
  </el-dialog>
</template>

<script setup>
import { Close, Grid, Message, Phone } from '@element-plus/icons-vue'
import QrcodeVue from 'qrcode.vue'

defineProps({
  // 密码
  showPasswordDialog: { type: Boolean, default: false },
  passwordLoading: { type: Boolean, default: false },
  passwordFormRef: { type: Object, default: null },
  passwordForm: { type: Object, required: true },
  passwordRules: { type: Object, required: true },
  // 手机
  showPhoneDialog: { type: Boolean, default: false },
  phoneLoading: { type: Boolean, default: false },
  phoneCodeSending: { type: Boolean, default: false },
  codeCountdown: { type: Number, default: 0 },
  phoneFormRef: { type: Object, default: null },
  phoneForm: { type: Object, required: true },
  phoneRules: { type: Object, required: true },
  // 邮箱
  showEmailDialog: { type: Boolean, default: false },
  emailLoading: { type: Boolean, default: false },
  emailCodeSending: { type: Boolean, default: false },
  emailCodeCountdown: { type: Number, default: 0 },
  emailFormRef: { type: Object, default: null },
  emailForm: { type: Object, required: true },
  emailRules: { type: Object, required: true },
  // 实名
  showVerifiedInfoDialog: { type: Boolean, default: false },
  showVerificationDialog: { type: Boolean, default: false },
  verificationLoading: { type: Boolean, default: false },
  checkingStatus: { type: Boolean, default: false },
  verificationUrl: { type: String, default: '' },
  canRestartVerification: { type: Boolean, default: false },
  verificationFormRef: { type: Object, default: null },
  verificationForm: { type: Object, required: true },
  verificationRules: { type: Object, required: true },
  realName: { type: String, default: '' },
  idCardMasked: { type: String, default: '' },
})

const emit = defineEmits([
  'change-password',
  'send-phone-code', 'change-phone',
  'send-email-code', 'change-email',
  'submit-verification', 'refresh-verification-link', 'check-status', 'restart-verification',
  'update:showPasswordDialog',
  'update:showPhoneDialog',
  'update:showEmailDialog',
  'update:showVerifiedInfoDialog',
  'update:showVerificationDialog',
])
</script>

<style lang="scss" scoped>
.verified-info-dialog {
  text-align: center;
  padding: 8px 0 16px;

  .verified-info-icon {
    width: 40px;
    height: 40px;
    margin: 0 auto 14px;
    border: 2px solid $color-primary;
    border-radius: 2px;
    color: $color-primary;
    font-size: 16px;
    line-height: 36px;
  }

  .verified-info-title {
    font-size: 20px;
    font-weight: 600;
    color: $text-color-primary;
    margin-bottom: 16px;
  }

  .verified-info-row {
    font-size: 15px;
    color: $text-color-secondary;
    line-height: 2;
  }
}

.bind-phone-dialog {
  :deep(.el-dialog) { border-radius: 2px; overflow: hidden; padding: 0; }
  :deep(.el-dialog__header) { display: none; }
  :deep(.el-dialog__body) { padding: 0; }
}

.bind-phone-panel {
  position: relative;
  padding: 20px;
  background: #fff;
}

.dialog-close {
  position: absolute;
  top: 12px;
  right: 12px;
  width: 32px;
  height: 32px;
  border: 1px solid $border-color;
  border-radius: 2px;
  background: #fff;
  color: $text-color-secondary;
  font-size: 16px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;

  &:hover { border-color: $color-primary; color: $color-primary; }
}

.dialog-top-icon {
  display: flex;
  justify-content: center;
  align-items: center;
  color: $color-primary;
  font-size: 28px;
  margin: 4px 0 8px;
}

.dialog-title {
  text-align: center;
  font-size: 16px;
  font-weight: 600;
  color: $text-color-primary;
  margin-bottom: 16px;
}

.bind-phone-form {
  :deep(.el-form-item__label) { padding-bottom: 6px; color: $text-color-secondary; font-size: 13px; }
  :deep(.el-input__wrapper) { background: $bg-color-soft; box-shadow: none; border-radius: 2px; }
}

.code-row {
  display: flex;
  gap: 10px;
}

.send-code-btn {
  min-width: 110px;
  border-radius: 2px;
  background: $color-primary-soft;
  color: $color-primary;
  border-color: $border-color;
  font-weight: 500;
}

.confirm-btn {
  width: 100%;
  height: 38px;
  border-radius: 2px;
  margin-top: 8px;
}

.verification-qrcode-panel {
  text-align: center;
}

.verification-qrcode-tip {
  margin: 20px 0 0;
  font-size: 14px;
  color: $text-color-secondary;
}

.verification-link-actions {
  display: flex;
  justify-content: center;
  flex-wrap: wrap;
  gap: 12px;
  margin-top: 20px;
}

@media (max-width: 767px) {
  .bind-phone-panel {
    padding: 18px 16px 16px;
  }

  .code-row {
    flex-direction: column;
    align-items: stretch;
  }

  .send-code-btn {
    width: 100%;
    min-width: 0;
  }

  .verification-qrcode-panel {
    :deep(canvas),
    :deep(svg) {
      width: min(100%, 240px) !important;
      height: auto !important;
    }
  }

  .verification-link-actions {
    flex-direction: column;
    align-items: stretch;
  }

  .verification-link-actions :deep(.el-button) {
    width: 100%;
    margin-left: 0 !important;
  }
}
</style>
