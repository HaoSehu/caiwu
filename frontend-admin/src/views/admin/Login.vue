<template>
  <div class="login-page">
    <div class="login-decor" aria-hidden="true"></div>
    <div class="login-center">
      <div class="login-brand">
        <h1 class="login-brand-name">{{ appStore.siteName }}</h1>
        <p class="login-brand-sub">管理控制台 · 登录即同意服务与安全策略</p>
      </div>

      <div class="login-card">
        <div class="login-card-head">
          <strong>欢迎回来</strong>
          <p>输入账号与密码进入管理控制台</p>
        </div>

        <el-form
          ref="formRef"
          :model="form"
          :rules="rules"
          label-position="top"
          class="login-form"
          @keyup.enter="handleLogin"
        >
          <el-form-item label="用户名" prop="username" required>
            <el-input
              v-model="form.username"
              size="large"
              placeholder="请输入用户名"
            />
          </el-form-item>

          <el-form-item label="密码" prop="password" required>
            <el-input
              v-model="form.password"
              size="large"
              type="password"
              show-password
              placeholder="请输入密码"
            />
          </el-form-item>

          <div class="login-options">
            <el-checkbox v-model="rememberMe">记住我</el-checkbox>
            <a href="#" class="forgot-link">忘记密码？</a>
          </div>

          <el-button
            type="primary"
            size="large"
            :loading="loading"
            class="login-submit"
            @click="handleLogin"
          >
            登录
          </el-button>
        </el-form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { useUserStore } from '@/stores/user'
import { useAppStore } from '@/stores/app'

const router = useRouter()
const userStore = useUserStore()
const appStore = useAppStore()
const formRef = ref()
const loading = ref(false)
const rememberMe = ref(true)

const form = reactive({
  username: '',
  password: '',
})

const rules = {
  username: [{ required: true, message: '请输入账号', trigger: 'blur' }],
  password: [
    { required: true, message: '请输入密码', trigger: 'blur' },
    { min: 6, message: '密码至少 6 位', trigger: 'blur' },
  ],
}

async function handleLogin() {
  try {
    await formRef.value?.validate()
  } catch {
    return
  }

  loading.value = true
  try {
    await userStore.adminLogin(form)
    ElMessage.success('登录成功')
    router.push('/admin/dashboard')
  } finally {
    loading.value = false
  }
}
</script>

<style lang="scss" scoped>
.login-page {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 100vh;
  background: $bg-color;
  overflow: hidden;
}

// 背景装饰：柔和的品牌色径向光晕，不喧宾夺主
.login-decor {
  position: absolute;
  inset: 0;
  pointer-events: none;
  background:
    radial-gradient(circle at 10% 20%, rgba($color-primary, 0.08), transparent 42%),
    radial-gradient(circle at 90% 85%, rgba($color-primary, 0.06), transparent 50%),
    linear-gradient(180deg, $bg-color 0%, $bg-color-soft 100%);
}

.login-center {
  position: relative;
  z-index: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  width: 100%;
  padding: 32px 16px;
}

.login-brand {
  margin-bottom: 24px;
  text-align: center;
}

.login-brand-name {
  color: $text-color-primary;
  font-size: 26px;
  font-weight: 600;
  line-height: 1.3;
  letter-spacing: -0.4px;
}

.login-brand-sub {
  margin-top: 8px;
  color: $text-color-secondary;
  font-size: 13px;
  line-height: 1.6;
}

.login-card {
  width: 100%;
  max-width: 420px;
  padding: 32px;
  border: 1px solid $divider-color;
  border-radius: $lg-border-radius;
  background: $bg-color-card;
  box-shadow: $shadow-xl;
}

.login-card-head strong {
  display: block;
  color: $text-color-primary;
  font-size: 20px;
  font-weight: 600;
  letter-spacing: -0.2px;
}

.login-card-head p {
  margin-top: 6px;
  color: $text-color-secondary;
  font-size: 13px;
  line-height: 1.6;
}

.login-form {
  margin-top: 24px;
}

.login-form :deep(.el-form-item__label) {
  color: $text-color-primary;
  font-size: 13px;
  font-weight: 500;
}

.login-form :deep(.el-input__wrapper) {
  background: $bg-color-card;
}

.login-options {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 20px;
  font-size: 13px;
}

.forgot-link {
  color: $color-primary;
  font-size: 13px;
  text-decoration: none;
  transition: color $duration-fast $ease-standard;

  &:hover {
    color: $color-primary-hover;
  }
}

.login-submit {
  width: 100%;
  min-height: 44px;
  font-size: 15px;
  font-weight: 500;
  letter-spacing: 0.3px;
}

@include mobile-and-below {
  .login-card {
    padding: 24px 20px;
  }

  .login-brand-name {
    font-size: 22px;
  }
}
</style>
