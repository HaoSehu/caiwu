<template>
  <AuthShell
    title="账号登录"
    nav-text="还没有账户？"
    nav-link-text="现在注册"
    :nav-to="registerLink"
  >
    <el-form ref="formRef" :model="form" :rules="rules" class="auth-form" @submit.prevent>
      <div class="field-block">
        <div class="field-label">手机号 / 邮箱</div>
        <el-form-item prop="account">
          <el-input
            v-model="form.account"
            placeholder="请输入手机号或邮箱"
            autocomplete="username"
            @keyup.enter="handleLogin"
          />
        </el-form-item>
        <div class="field-tip">支持手机号或邮箱登录</div>
      </div>

      <div class="field-block">
        <div class="field-label is-required">密码</div>
        <el-form-item prop="password">
          <el-input
            v-model="form.password"
            type="password"
            placeholder="请输入登录密码"
            autocomplete="current-password"
            @keyup.enter="handleLogin"
          />
        </el-form-item>
      </div>

      <div class="inline-action-row">
        <router-link class="auth-link" to="/client/forgot-password">忘记密码？</router-link>
      </div>

      <el-button type="primary" class="auth-submit-btn" :loading="loading || captchaLoading" @click="handleLogin">
        登录
      </el-button>
    </el-form>
  </AuthShell>
</template>

<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import AuthShell from '@/components/auth/AuthShell.vue'
import { useGeeTestCaptcha } from '@/composables/useGeeTestCaptcha'
import { useUserStore } from '@/stores/user'
import { detectAccountType, normalizeAccountValue } from '@/utils/account'

const route = useRoute()
const router = useRouter()
const userStore = useUserStore()
const formRef = ref()
const loading = ref(false)
const { loading: captchaLoading, runWithCaptcha } = useGeeTestCaptcha()

const redirectPath = computed(() => {
  const redirect = route.query.redirect
  return typeof redirect === 'string' && redirect.startsWith('/') ? redirect : '/client/dashboard'
})

const registerLink = computed(() => ({
  path: '/client/register',
  query: route.query.redirect ? { redirect: route.query.redirect } : {},
}))

const form = reactive({
  account: '',
  password: '',
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
  password: [
    { required: true, message: '请输入登录密码', trigger: 'blur' },
  ],
}

const isCaptchaRequiredError = (error: any) => Boolean(
  error?.response?.data?.data?.captcha_required,
)

const performLogin = async (captcha: unknown = null) => {
  await userStore.clientLogin({
    account: normalizeAccountValue(form.account),
    password: form.password,
    ...(captcha ? { captcha } : {}),
  })
}

const handleLogin = async () => {
  try {
    await formRef.value?.validate()
  } catch {
    return
  }

  loading.value = true
  try {
    await performLogin()
    ElMessage.success('登录成功')
    await router.push(redirectPath.value)
  } catch (error: any) {
    if (isCaptchaRequiredError(error)) {
      try {
        await runWithCaptcha(async (captcha: unknown) => {
          await performLogin(captcha)
        }, { required: true })
        ElMessage.success('登录成功')
        await router.push(redirectPath.value)
        return
      } catch (captchaError: any) {
        if (isCaptchaRequiredError(captchaError)) {
          ElMessage.error(captchaError.message || '行为验证未通过，请重试')
          return
        }

        if (!captchaError?.__handled) {
          ElMessage.error(captchaError.message || '登录失败')
        }
        return
      }
    }

    if (!error?.__handled) {
      ElMessage.error(error.message || '登录失败')
    }
  } finally {
    loading.value = false
  }
}
</script>

<style scoped lang="scss">
.auth-form {
  margin-top: 0;
}
</style>
