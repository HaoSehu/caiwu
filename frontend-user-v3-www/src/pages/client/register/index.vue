<template>
  <AuthShell
    title="免费注册"
    nav-text="已经有账户？"
    nav-link-text="立即登录"
    :nav-to="loginLink"
    cta-text="返回官网查看产品目录 >"
    cta-to="/products"
  >
    <el-form ref="formRef" :model="form" :rules="rules" class="auth-form" @submit.prevent>
      <div class="field-block">
        <div class="field-label is-required">手机号 / 邮箱</div>
        <el-form-item prop="account">
          <el-input
            v-model="form.account"
            placeholder="请输入手机号或邮箱"
            autocomplete="username"
            @keyup.enter="handleRegister"
          />
        </el-form-item>
      </div>

      <div class="field-block">
        <div class="field-label is-required">验证码</div>
        <el-form-item prop="code">
          <div class="code-row">
            <el-input
              v-model="form.code"
              placeholder="请输入验证码"
              maxlength="6"
              @keyup.enter="handleRegister"
            />
            <el-button
              class="code-button"
              @click="handleSendCode"
              :disabled="countdown > 0"
              :loading="sendingCode || captchaLoading"
            >
              {{ countdown > 0 ? `${countdown}s` : '发送验证码' }}
            </el-button>
          </div>
        </el-form-item>
      </div>

      <div class="field-block">
        <div class="field-label">用户名</div>
        <el-form-item prop="nickname">
          <el-input
            v-model="form.nickname"
            placeholder="选填，最多 50 个字符"
            maxlength="50"
            show-word-limit
          />
        </el-form-item>
      </div>

      <div class="field-block">
        <div class="field-label">推荐码</div>
        <el-form-item prop="referral_code">
          <el-input
            v-model="form.referral_code"
            placeholder="选填，如有邀请推荐可填写"
            maxlength="24"
          />
        </el-form-item>
      </div>

      <div class="field-block">
        <div class="field-label is-required">登录密码</div>
        <el-form-item prop="password">
          <el-input
            v-model="form.password"
            type="password"
            show-password
            placeholder="请输入至少 6 位密码"
            autocomplete="new-password"
          />
        </el-form-item>
      </div>

      <div class="field-block">
        <div class="field-label is-required">确认密码</div>
        <el-form-item prop="password_confirmation">
          <el-input
            v-model="form.password_confirmation"
            type="password"
            show-password
            placeholder="请再次输入密码"
            autocomplete="new-password"
            @keyup.enter="handleRegister"
          />
        </el-form-item>
      </div>

      <el-button type="primary" class="auth-submit-btn" :loading="loading" @click="handleRegister">
        注册并进入控制台
      </el-button>
    </el-form>
  </AuthShell>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import AuthShell from '@/components/auth/AuthShell.vue'
import { clientAuthApi } from '@/api/auth'
import { useGeeTestCaptcha } from '@/composables/useGeeTestCaptcha'
import { useUserStore } from '@/stores/user'
import { buildAccountPayload, detectAccountType, normalizeAccountValue } from '@/utils/account'

const route = useRoute()
const router = useRouter()
const userStore = useUserStore()
const formRef = ref()
const loading = ref(false)
const sendingCode = ref(false)
const countdown = ref(0)
const { loading: captchaLoading, runWithCaptcha } = useGeeTestCaptcha()
let countdownTimer: ReturnType<typeof setInterval> | null = null

const loginLink = computed(() => ({
  path: '/client/login',
  query: route.query.redirect ? { redirect: route.query.redirect } : {},
}))

const redirectPath = computed(() => {
  const redirect = route.query.redirect
  return typeof redirect === 'string' && redirect.startsWith('/') ? redirect : '/client/dashboard'
})

const form = reactive({
  account: '',
  code: '',
  nickname: '',
  referral_code: '',
  password: '',
  password_confirmation: '',
})

const rules = {
  account: [
    {
      required: true,
      validator: (_rule: unknown, value: string, callback: (error?: Error) => void) => {
        if (!detectAccountType(value)) {
          callback(new Error('请输入正确的手机号或邮箱'))
          return
        }
        callback()
      },
      trigger: 'blur',
    },
  ],
  code: [
    { required: true, message: '请输入验证码', trigger: 'blur' },
    { min: 6, max: 6, message: '验证码为 6 位', trigger: 'blur' },
  ],
  nickname: [
    { max: 50, message: '用户名不能超过 50 个字符', trigger: 'blur' },
  ],
  referral_code: [
    { max: 24, message: '推荐码不能超过 24 个字符', trigger: 'blur' },
  ],
  password: [
    { required: true, message: '请输入登录密码', trigger: 'blur' },
    { min: 6, message: '密码长度不能少于 6 位', trigger: 'blur' },
  ],
  password_confirmation: [
    { required: true, message: '请再次输入密码', trigger: 'blur' },
    {
      validator: (_rule: unknown, value: string, callback: (error?: Error) => void) => {
        if (value !== form.password) {
          callback(new Error('两次输入的密码不一致'))
          return
        }
        callback()
      },
      trigger: 'blur',
    },
  ],
}

function clearTimer() {
  if (countdownTimer) {
    clearInterval(countdownTimer)
    countdownTimer = null
  }
}

function startCountdown() {
  clearTimer()
  countdown.value = 60
  countdownTimer = setInterval(() => {
    countdown.value -= 1
    if (countdown.value <= 0) {
      clearTimer()
      countdown.value = 0
    }
  }, 1000)
}

async function handleSendCode() {
  const accountPayload = buildAccountPayload(form.account)
  if (!accountPayload) {
    ElMessage.warning('请先输入正确的手机号或邮箱')
    return
  }

  sendingCode.value = true
  try {
    await runWithCaptcha(async (captcha: unknown) => {
      if (accountPayload.accountType === 'phone') {
        await clientAuthApi.sendPhoneCode({
          phone: accountPayload.phone,
          captcha,
        })
      } else {
        await clientAuthApi.sendEmailCode({
          email: accountPayload.email,
          captcha,
        })
      }
    })

    ElMessage.success(`${accountPayload.accountType === 'phone' ? '短信' : '邮箱'}验证码已发送`)
    startCountdown()
  } catch (error: any) {
    if (!error?.__handled) {
      ElMessage.error(error?.message || '验证码发送失败')
    }
  } finally {
    sendingCode.value = false
  }
}

async function handleRegister() {
  try {
    await formRef.value?.validate()
  } catch {
    return
  }

  loading.value = true
  try {
    await userStore.clientRegister({
      account: normalizeAccountValue(form.account),
      code: form.code,
      nickname: form.nickname || undefined,
      referral_code: form.referral_code || undefined,
      password: form.password,
      password_confirmation: form.password_confirmation,
    })
    ElMessage.success('注册成功')
    await router.push(redirectPath.value)
  } catch (error: any) {
    if (!error?.__handled) {
      ElMessage.error(error?.message || '注册失败')
    }
  } finally {
    loading.value = false
  }
}

onBeforeUnmount(() => {
  clearTimer()
})
</script>

<style scoped lang="scss">
.auth-form {
  margin-top: 0;
}

.code-row {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 124px;
  gap: 10px;
  width: 100%;
}

.code-button {
  width: 124px;
}

@media (max-width: 767px) {
  .code-row {
    grid-template-columns: 1fr;
  }

  .code-button {
    width: 100%;
  }
}
</style>
