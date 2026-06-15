<template>
  <div class="login-container">
    <div class="login-card">
      <div class="login-header">
        <h1>管理后台</h1>
        <p>请登录您的管理员账号</p>
      </div>
      <t-form
        ref="formRef"
        :data="formData"
        :rules="formRules"
        label-align="top"
        @submit="handleLogin"
      >
        <t-form-item label="账号" name="account">
          <t-input
            v-model="formData.account"
            placeholder="请输入管理员账号"
            clearable
          />
        </t-form-item>
        <t-form-item label="密码" name="password">
          <t-input
            v-model="formData.password"
            type="password"
            placeholder="请输入密码"
            clearable
          />
        </t-form-item>
        <t-form-item>
          <t-button block theme="primary" type="submit" :loading="loading">
            登录
          </t-button>
        </t-form-item>
      </t-form>
    </div>
  </div>
</template>

<script setup lang="ts">
import type { FormInstanceFunctions, FormRule } from 'tdesign-vue-next';
import { MessagePlugin } from 'tdesign-vue-next';
import { ref } from 'vue';
import { useRouter } from 'vue-router';

import { useUserStore } from '@/store';

const router = useRouter();
const userStore = useUserStore();
const formRef = ref<FormInstanceFunctions>();
const loading = ref(false);

const formData = ref({
  account: '',
  password: '',
});

const formRules: Record<string, FormRule[]> = {
  account: [{ required: true, message: '请输入账号', trigger: 'blur' }],
  password: [{ required: true, message: '请输入密码', trigger: 'blur' }],
};

async function handleLogin() {
  const valid = await formRef.value?.validate();
  if (valid !== true) return;

  loading.value = true;
  try {
    await userStore.login({
      account: formData.value.account,
      password: formData.value.password,
    });
    MessagePlugin.success('登录成功');
    const redirect = router.currentRoute.value.query.redirect;
    if (redirect) {
      router.push(decodeURIComponent(redirect as string));
    } else {
      router.push('/admin/dashboard');
    }
  } catch (error) {
    MessagePlugin.error(error instanceof Error ? error.message : '登录失败，请检查账号密码');
  } finally {
    loading.value = false;
  }
}
</script>

<style lang="less" scoped>
.login-container {
  height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.login-card {
  width: 400px;
  padding: 40px;
  background: #fff;
  border-radius: 8px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
}

.login-header {
  text-align: center;
  margin-bottom: 32px;

  h1 {
    font-size: 24px;
    font-weight: 600;
    color: #333;
    margin-bottom: 8px;
  }

  p {
    font-size: 14px;
    color: #666;
  }
}
</style>