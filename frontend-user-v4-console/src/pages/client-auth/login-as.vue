<template>
  <auth-shell title="登录中" nav-text="如需手动登录" nav-link-text="返回登录页" nav-to="/client/login">
    <t-result :theme="resultTheme" :title="resultTitle" :description="resultSubtitle">
      <template #extra>
        <t-space>
          <t-button v-if="status === 'error'" theme="primary" :loading="loading" @click="retryExchange">重试代登录</t-button>
          <t-button v-if="status !== 'loading'" variant="outline" @click="router.push('/client/login')">返回登录页</t-button>
        </t-space>
      </template>
    </t-result>
  </auth-shell>
</template>

<script setup lang="ts">
import { MessagePlugin } from 'tdesign-vue-next';
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';

import AuthShell from '@/components/auth/AuthShell.vue';
import { useUserStore } from '@/store';

const route = useRoute();
const router = useRouter();
const userStore = useUserStore();
const status = ref<'loading' | 'success' | 'error'>('loading');
const loading = ref(false);

const redirectPath = computed(() => {
  const redirect = route.query.redirect;
  return typeof redirect === 'string' && redirect.startsWith('/') ? redirect : '/client/dashboard';
});

const resultTheme = computed(() => {
  if (status.value === 'success') return 'success';
  if (status.value === 'error') return 'error';
  return 'info';
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
  } catch (error: any) {
    status.value = 'error';
    if (!error?.__handled) {
      MessagePlugin.error(error?.message || '代登录失败');
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
