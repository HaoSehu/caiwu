<template>
  <AuthShell
    title="登录中"
    nav-text="如需手动登录"
    nav-link-text="返回登录页"
    nav-to="/client/login"
  >
    <div class="login-as-panel">
      <el-result
        :icon="resultIcon"
        :title="resultTitle"
        :sub-title="resultSubtitle"
      >
        <template #extra>
          <el-button v-if="status === 'error'" type="primary" @click="retryExchange">
            重试代登录
          </el-button>
          <el-button v-if="status !== 'loading'" @click="router.push('/client/login')">
            返回登录页
          </el-button>
        </template>
      </el-result>
    </div>
  </AuthShell>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import AuthShell from '@/components/auth/AuthShell.vue'
import { useUserStore } from '@/stores/user'

const route = useRoute()
const router = useRouter()
const userStore = useUserStore()
const status = ref<'loading' | 'success' | 'error'>('loading')

const redirectPath = computed(() => {
  const redirect = route.query.redirect
  return typeof redirect === 'string' && redirect.startsWith('/') ? redirect : '/client/dashboard'
})

const resultIcon = computed(() => {
  if (status.value === 'success') return 'success'
  if (status.value === 'error') return 'error'
  return 'info'
})

const resultTitle = computed(() => {
  if (status.value === 'success') return '代登录成功'
  if (status.value === 'error') return '代登录失败'
  return '正在处理代登录'
})

const resultSubtitle = computed(() => {
  if (status.value === 'success') return '已完成身份切换，正在进入客户控制台。'
  if (status.value === 'error') return '代登录链接无效、已过期，或当前请求已被拒绝。'
  return '正在校验代登录凭证，请稍候。'
})

async function runExchange() {
  const code = typeof route.query.code === 'string' ? route.query.code.trim() : ''

  if (!code) {
    status.value = 'error'
    ElMessage.error('缺少代登录凭证')
    return
  }

  status.value = 'loading'

  try {
    await userStore.exchangeLoginAsCode(code)
    status.value = 'success'
    ElMessage.success('代登录成功')
    await router.replace(redirectPath.value)
  } catch (error: any) {
    status.value = 'error'
    if (!error?.__handled) {
      ElMessage.error(error?.message || '代登录失败')
    }
  }
}

function retryExchange() {
  void runExchange()
}

onMounted(() => {
  void runExchange()
})
</script>

<style scoped lang="scss">
.login-as-panel {
  padding-top: 12px;
}
</style>
