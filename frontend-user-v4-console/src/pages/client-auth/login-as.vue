<template>
  <auth-shell
    title="登录中"
    nav-text="如需手动登录"
    nav-link-text="返回登录页"
    nav-to="/client/login"
    hero-title="正在校验本次身份切换请求"
    hero-description="验证通过后将自动进入控制台，本次跳转不会改动现有业务数据。"
  >
    <div class="login-as-result" :class="`login-as-result--${status}`">
      <div class="login-as-result__icon">
        <check-circle-icon v-if="status === 'success'" />
        <error-circle-icon v-else-if="status === 'error'" />
        <loading-icon v-else />
      </div>
      <h3 class="login-as-result__title">{{ resultTitle }}</h3>
      <p class="login-as-result__desc">{{ resultSubtitle }}</p>
      <t-space>
        <t-button v-if="status === 'error'" theme="primary" :loading="loading" @click="retryExchange"
          >重试代登录</t-button
        >
        <t-button v-if="status !== 'loading'" variant="outline" @click="router.push('/client/login')"
          >返回登录页</t-button
        >
      </t-space>
    </div>
  </auth-shell>
</template>
<script setup lang="ts">
import { CheckCircleIcon, ErrorCircleIcon, LoadingIcon } from 'tdesign-icons-vue-next';
import { MessagePlugin } from 'tdesign-vue-next';
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';

import AuthShell from '@/components/auth/AuthShell.vue';
import { useUserStore } from '@/store';

interface RuntimeHandledError {
  __handled?: boolean;
  message?: string;
}

const route = useRoute();
const router = useRouter();
const userStore = useUserStore();
const status = ref<'loading' | 'success' | 'error'>('loading');
const loading = ref(false);

const redirectPath = computed(() => {
  const redirect = route.query.redirect;
  return typeof redirect === 'string' && redirect.startsWith('/') ? redirect : '/client/dashboard';
});

const resultTitle = computed(() => {
  if (status.value === 'success') return '代登录成功';
  if (status.value === 'error') return '代登录失败';
  return '正在处理代登录';
});

const resultSubtitle = computed(() => {
  if (status.value === 'success') return '已完成身份切换，正在进入客户控制台。';
  if (status.value === 'error') return '代登录链接无效、已过期，或当前请求已被拒绝。';
  return '正在校验代登录凭证，请稍候。';
});

async function runExchange() {
  const code = typeof route.query.code === 'string' ? route.query.code.trim() : '';

  if (!code) {
    status.value = 'error';
    MessagePlugin.error('缺少代登录凭证');
    return;
  }

  status.value = 'loading';
  loading.value = true;
  try {
    await userStore.exchangeLoginAsCode(code);
    status.value = 'success';
    MessagePlugin.success('代登录成功');
    await router.replace(redirectPath.value);
  } catch (error: unknown) {
    const runtimeError = error as RuntimeHandledError;
    status.value = 'error';
    if (!runtimeError.__handled) {
      MessagePlugin.error(runtimeError.message || '代登录失败');
    }
  } finally {
    loading.value = false;
  }
}

function retryExchange() {
  void runExchange();
}

onMounted(() => {
  void runExchange();
});
</script>
<style lang="less" scoped>
.login-as-result {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--td-comp-margin-m);
  padding: var(--td-comp-paddingTB-xxl) var(--td-comp-paddingLR-l);
  text-align: center;
}

.login-as-result__icon {
  display: inline-flex;
  font-size: 3rem;
  line-height: 1;

  .login-as-result--success & {
    color: var(--td-success-color);
  }

  .login-as-result--error & {
    color: var(--td-error-color);
  }

  .login-as-result--loading & {
    color: var(--td-brand-color);
    animation: login-as-spin 1s linear infinite;
  }
}

.login-as-result__title {
  margin: 0;
  color: var(--td-text-color-primary);
  font: var(--td-font-title-large);
}

.login-as-result__desc {
  max-width: 22rem;
  margin: 0;
  color: var(--td-text-color-secondary);
  font: var(--td-font-body-medium);
}

@keyframes login-as-spin {
  to {
    transform: rotate(360deg);
  }
}
</style>
