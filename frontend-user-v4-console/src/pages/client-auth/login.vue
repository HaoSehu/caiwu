<template>
  <auth-shell title="账号登录" nav-text="还没有账户？" nav-link-text="现在注册" :nav-to="registerLink">
    <t-form ref="formRef" class="client-auth-form" :data="form" :rules="rules" label-width="0" @submit="handleLogin">
      <t-form-item name="account">
        <div class="client-auth-field">
          <label class="client-auth-label is-required">手机号 / 邮箱</label>
          <t-input
            v-model="form.account"
            size="large"
            clearable
            autocomplete="username"
            placeholder="请输入手机号或邮箱"
            @enter="submitForm"
          >
            <template #prefix-icon><user-icon /></template>
          </t-input>
          <p class="client-auth-tip">支持手机号或邮箱登录</p>
        </div>
      </t-form-item>

      <t-form-item name="password">
        <div class="client-auth-field">
          <label class="client-auth-label is-required">密码</label>
          <t-input
            v-model="form.password"
            size="large"
            :type="showPassword ? 'text' : 'password'"
            clearable
            autocomplete="current-password"
            placeholder="请输入登录密码"
            @enter="submitForm"
          >
            <template #prefix-icon><lock-on-icon /></template>
            <template #suffix-icon>
              <browse-icon v-if="showPassword" @click="showPassword = false" />
              <browse-off-icon v-else @click="showPassword = true" />
            </template>
          </t-input>
        </div>
      </t-form-item>

      <div class="client-auth-form__links">
        <router-link to="/client/forgot-password">忘记密码？</router-link>
      </div>

      <t-button block size="large" theme="primary" :loading="loading || captchaLoading" @click="submitForm">登录</t-button>
    </t-form>
  </auth-shell>
</template>

<script setup lang="ts">
import { BrowseIcon, BrowseOffIcon, LockOnIcon, UserIcon } from 'tdesign-icons-vue-next';
import type { FormInstanceFunctions, FormRule, SubmitContext } from 'tdesign-vue-next';
import { MessagePlugin } from 'tdesign-vue-next';
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';

import AuthShell from '@/components/auth/AuthShell.vue';
import { useGeeTestCaptcha } from '@/composables/useGeeTestCaptcha';
import { useUserStore } from '@/store';
import { detectAccountType, normalizeAccountValue } from '@/utils/account';
import { toUserMessage } from '@/utils/userMessage';

interface LoginForm {
  account: string;
  password: string;
}

const route = useRoute();
const router = useRouter();
const userStore = useUserStore();
const formRef = ref<FormInstanceFunctions<LoginForm>>();
const loading = ref(false);
const showPassword = ref(false);
const { loading: captchaLoading, runWithCaptcha } = useGeeTestCaptcha();

const form = reactive<LoginForm>({
  account: '',
  password: '',
});

const redirectPath = computed(() => {
  const redirect = route.query.redirect;
  return typeof redirect === 'string' && redirect.startsWith('/') ? redirect : '/client/dashboard';
});

const registerLink = computed(() => ({
  path: '/client/register',
  query: route.query.redirect ? { redirect: route.query.redirect } : {},
}));

const rules: Record<keyof LoginForm, FormRule[]> = {
  account: [
    {
      validator: (value: string) => ({
        result: Boolean(detectAccountType(value)),
        message: '请输入正确的手机号或邮箱',
        type: 'error',
      }),
      trigger: 'blur',
    },
  ],
  password: [{ required: true, message: '请输入登录密码', type: 'error', trigger: 'blur' }],
};

const isCaptchaRequiredError = (error: any) => Boolean(error?.response?.data?.data?.captcha_required);

async function performLogin(captcha: unknown = null) {
  await userStore.clientLogin({
    account: normalizeAccountValue(form.account),
    password: form.password,
    ...(captcha ? { captcha } : {}),
  });
}

async function submitForm() {
  if (!validateForm()) {
    return;
  }
  await runLogin();
}

async function handleLogin(ctx: SubmitContext) {
  if (ctx.validateResult !== true || !validateForm()) return;
  await runLogin();
}

function setFormErrors(errors: Partial<Record<keyof LoginForm, string>>) {
  formRef.value?.setValidateMessage(
    Object.fromEntries(
      Object.entries(errors).map(([field, message]) => [field, [{ type: 'error', message }]]),
    ) as any,
  );
}

function validateForm() {
  const errors: Partial<Record<keyof LoginForm, string>> = {};
  if (!detectAccountType(form.account)) {
    errors.account = '请输入正确的手机号或邮箱';
  }
  if (!form.password) {
    errors.password = '请输入登录密码';
  }

  if (Object.keys(errors).length > 0) {
    setFormErrors(errors);
    return false;
  }

  formRef.value?.clearValidate();
  return true;
}

async function runLogin() {
  loading.value = true;
  try {
    await performLogin();
    MessagePlugin.success('登录成功');
    await router.push(redirectPath.value);
  } catch (error: any) {
    if (isCaptchaRequiredError(error)) {
      try {
        await runWithCaptcha(async (captcha: unknown) => {
          await performLogin(captcha);
        }, { required: true });
        MessagePlugin.success('登录成功');
        await router.push(redirectPath.value);
        return;
      } catch (captchaError: any) {
        if (!captchaError?.__handled) {
          MessagePlugin.error(toUserMessage(captchaError?.message, '登录失败'));
        }
        return;
      }
    }

    if (!error?.__handled) {
      MessagePlugin.error(toUserMessage(error?.message, '登录失败'));
    }
  } finally {
    loading.value = false;
  }
}

onMounted(() => {
  if (typeof route.query.account === 'string') {
    form.account = route.query.account;
  }
});
</script>

<style scoped lang="less">
@import './shared-auth.less';
</style>
