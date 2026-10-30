<template>
  <auth-shell
    title="免费注册"
    nav-text="已经有账户？"
    nav-link-text="立即登录"
    :nav-to="loginLink"
    cta-text="查看产品目录"
    cta-to="/products"
    hero-title="注册后即可进入控制台开始购买"
    hero-description="完成开户注册后，可以继续创建账单、管理服务，并处理实名认证与账户安全设置。"
  >
    <!-- A-11：校验结果的读屏通道，节点常驻（v-if 挂载不会被播报）。 -->
    <p class="client-auth-live" role="status" aria-live="polite">{{ liveText }}</p>
    <t-form ref="formRef" class="client-auth-form" :data="form" :rules="rules" label-width="0" @submit="handleRegister">
      <t-form-item name="account">
        <label class="client-auth-field">
          <span class="client-auth-label is-required">手机号 / 邮箱<span class="sr-only">（必填）</span></span>
          <t-input
            id="register-account"
            v-model="form.account"
            size="large"
            clearable
            autocomplete="username"
            placeholder="请输入手机号或邮箱"
            @enter="submitForm"
          />
        </label>
      </t-form-item>

      <t-form-item name="code">
        <label class="client-auth-field">
          <span class="client-auth-label is-required">验证码<span class="sr-only">（必填）</span></span>
          <div class="client-auth-code-row">
            <t-input
              id="register-code"
              v-model="form.code"
              size="large"
              maxlength="6"
              placeholder="请输入验证码"
              @enter="submitForm"
            />
            <t-button
              variant="outline"
              :disabled="countdown > 0"
              :loading="sendingCode || captchaLoading"
              @click="handleSendCode"
            >
              {{ countdown > 0 ? `${countdown}s` : '发送验证码' }}
            </t-button>
          </div>
        </label>
      </t-form-item>

      <t-form-item name="password">
        <label class="client-auth-field client-auth-field--password">
          <span class="client-auth-label is-required">登录密码<span class="sr-only">（必填）</span></span>
          <t-input
            id="register-password"
            v-model="form.password"
            size="large"
            :type="showPassword ? 'text' : 'password'"
            clearable
            autocomplete="new-password"
            placeholder="请输入至少 6 位密码"
            @enter="submitForm"
          >
            <template #prefix-icon><lock-on-icon /></template>
            <template #suffix>
              <password-toggle v-model="showPassword" />
            </template>
          </t-input>
        </label>
      </t-form-item>

      <t-form-item name="password_confirmation">
        <label class="client-auth-field client-auth-field--password">
          <span class="client-auth-label is-required">确认密码<span class="sr-only">（必填）</span></span>
          <t-input
            id="register-confirm-password"
            v-model="form.password_confirmation"
            size="large"
            :type="showConfirmPassword ? 'text' : 'password'"
            clearable
            autocomplete="new-password"
            placeholder="请再次输入密码"
            @enter="submitForm"
          >
            <template #prefix-icon><lock-on-icon /></template>
            <template #suffix>
              <password-toggle v-model="showConfirmPassword" />
            </template>
          </t-input>
        </label>
      </t-form-item>

      <!-- A-15：选填项原先夹在必填项之间，字号字重间距与必填完全一致，只靠红色星号区分，
           扫读时会被当成「怎么填都不对」。移到必填项之后单独成组，并给出分组标题。 -->
      <p class="client-auth-group-title">选填信息</p>

      <t-form-item name="nickname">
        <label class="client-auth-field">
          <span class="client-auth-label">用户名</span>
          <t-input
            id="register-nickname"
            v-model="form.nickname"
            size="large"
            maxlength="50"
            placeholder="选填，最多 50 个字符"
            @enter="submitForm"
          />
        </label>
      </t-form-item>

      <t-form-item name="referral_code">
        <label class="client-auth-field">
          <span class="client-auth-label">推广码</span>
          <t-input
            id="register-referral"
            v-model="form.referral_code"
            size="large"
            maxlength="24"
            placeholder="选填，如有推广邀请可填写"
            @enter="submitForm"
          />
        </label>
      </t-form-item>

      <t-button
        class="client-auth-submit"
        block
        size="large"
        theme="primary"
        :loading="loading"
        :disabled="loading"
        @click="submitForm"
      >
        注册并进入控制台
      </t-button>
    </t-form>
  </auth-shell>
</template>
<script setup lang="ts">
import { toUserMessage } from '@caiwu/shared/runtime';
import { LockOnIcon } from 'tdesign-icons-vue-next';
import type { FormInstanceFunctions, FormRule, FormValidateMessage, SubmitContext } from 'tdesign-vue-next';
import { MessagePlugin } from 'tdesign-vue-next/es/message/plugin';
import { computed, onBeforeUnmount, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';

import { clientAuthApi } from '@/api/auth';
import AuthShell from '@/components/auth/AuthShell.vue';
import PasswordToggle from '@/components/auth/PasswordToggle.vue';
import { useGeeTestCaptcha } from '@/composables/useGeeTestCaptcha';
import { useUserStore } from '@/store';
import { buildAccountPayload, detectAccountType, normalizeAccountValue } from '@/utils/account';
import { resolveLoginRedirectPath } from '@/utils/loginRedirect';

interface RegisterForm {
  account: string;
  code: string;
  nickname: string;
  referral_code: string;
  password: string;
  password_confirmation: string;
}

interface RuntimeHandledError {
  __handled?: boolean;
  message?: string;
}

const route = useRoute();
const router = useRouter();
const userStore = useUserStore();
const formRef = ref<FormInstanceFunctions<RegisterForm>>();
const loading = ref(false);
const sendingCode = ref(false);
const countdown = ref(0);
const showPassword = ref(false);
const showConfirmPassword = ref(false);
const { loading: captchaLoading, runWithCaptcha } = useGeeTestCaptcha();
let countdownTimer: ReturnType<typeof setInterval> | null = null;

const form = reactive<RegisterForm>({
  account: '',
  code: '',
  nickname: '',
  referral_code: '',
  password: '',
  password_confirmation: '',
});

const loginLink = computed(() => ({
  path: '/client/login',
  query: route.query.redirect ? { redirect: route.query.redirect } : {},
}));

// A-23：原先这里自己写了一遍 `startsWith('/')` 判断，漏掉协议相对形态（//evil.com）。
// 登录导向全站只有一处口径：@/utils/loginRedirect 的 resolveLoginRedirectPath
// （路由守卫、401 拦截、login.vue 都用它），注册页也收敛到同一个函数。
const redirectPath = computed(() => resolveLoginRedirectPath(route.query.redirect));

const accountRule: FormRule = {
  validator: (value: string) => ({
    result: Boolean(detectAccountType(value)),
    message: '请输入正确的手机号或邮箱',
    type: 'error',
  }),
  trigger: 'blur',
};

const rules: Record<keyof RegisterForm, FormRule[]> = {
  account: [accountRule],
  code: [
    { required: true, message: '请输入验证码', type: 'error', trigger: 'blur' },
    { len: 6, message: '验证码为 6 位', type: 'error', trigger: 'blur' },
  ],
  nickname: [{ max: 50, message: '用户名不能超过 50 个字符', type: 'error', trigger: 'blur' }],
  referral_code: [{ max: 24, message: '推广码不能超过 24 个字符', type: 'error', trigger: 'blur' }],
  password: [
    { required: true, message: '请输入登录密码', type: 'error', trigger: 'blur' },
    { min: 6, message: '密码长度不能少于 6 位', type: 'error', trigger: 'blur' },
  ],
  password_confirmation: [
    { required: true, message: '请再次输入密码', type: 'error', trigger: 'blur' },
    {
      validator: (value: string) => ({
        result: value === form.password,
        message: '两次输入的密码不一致',
        type: 'error',
      }),
      trigger: 'blur',
    },
  ],
};

function clearTimer() {
  if (countdownTimer) {
    clearInterval(countdownTimer);
    countdownTimer = null;
  }
}

function startCountdown() {
  clearTimer();
  countdown.value = 60;
  countdownTimer = setInterval(() => {
    countdown.value -= 1;
    if (countdown.value <= 0) {
      clearTimer();
      countdown.value = 0;
    }
  }, 1000);
}

async function handleSendCode() {
  if (sendingCode.value || countdown.value > 0) return;
  const accountPayload = buildAccountPayload(form.account);
  if (!accountPayload) {
    MessagePlugin.warning('请先输入正确的手机号或邮箱');
    return;
  }

  sendingCode.value = true;
  try {
    await runWithCaptcha(async (captcha: unknown) => {
      if (accountPayload.accountType === 'phone') {
        await clientAuthApi.sendPhoneCode({ phone: accountPayload.phone, purpose: 'register', captcha });
      } else {
        await clientAuthApi.sendEmailCode({ email: accountPayload.email, purpose: 'register', captcha });
      }
    });

    MessagePlugin.success(`${accountPayload.accountType === 'phone' ? '短信' : '邮箱'}验证码已发送`);
    startCountdown();
  } catch (error: unknown) {
    const runtimeError = error as RuntimeHandledError;
    if (!runtimeError.__handled) {
      MessagePlugin.error(toUserMessage(runtimeError.message, '验证码发送失败'));
    }
  } finally {
    sendingCode.value = false;
  }
}

async function submitForm() {
  if (loading.value) return;
  if (!validateForm()) {
    return;
  }
  await runRegister();
}

async function handleRegister(ctx: SubmitContext) {
  if (loading.value) return;
  if (ctx.validateResult !== true || !validateForm()) return;
  await runRegister();
}

// A-11：校验失败的读屏播报文案。
const liveText = ref('');

function setFormErrors(errors: Partial<Record<keyof RegisterForm, string>>) {
  liveText.value = Object.values(errors).filter(Boolean).join('；');
  const validateMessage: FormValidateMessage<RegisterForm> = {
    account: errors.account ? [{ type: 'error', message: errors.account }] : [],
    code: errors.code ? [{ type: 'error', message: errors.code }] : [],
    nickname: errors.nickname ? [{ type: 'error', message: errors.nickname }] : [],
    referral_code: errors.referral_code ? [{ type: 'error', message: errors.referral_code }] : [],
    password: errors.password ? [{ type: 'error', message: errors.password }] : [],
    password_confirmation: errors.password_confirmation
      ? [{ type: 'error', message: errors.password_confirmation }]
      : [],
  };
  formRef.value?.setValidateMessage(validateMessage);
}

function validateForm() {
  const errors: Partial<Record<keyof RegisterForm, string>> = {};
  if (!detectAccountType(form.account)) {
    errors.account = '请输入正确的手机号或邮箱';
  }
  if (!form.code) {
    errors.code = '请输入验证码';
  } else if (form.code.length !== 6) {
    errors.code = '验证码为 6 位';
  }
  if (form.nickname.length > 50) {
    errors.nickname = '用户名不能超过 50 个字符';
  }
  if (form.referral_code.length > 24) {
    errors.referral_code = '推广码不能超过 24 个字符';
  }
  if (!form.password) {
    errors.password = '请输入登录密码';
  } else if (form.password.length < 6) {
    errors.password = '密码长度不能少于 6 位';
  }
  if (!form.password_confirmation) {
    errors.password_confirmation = '请再次输入密码';
  } else if (form.password_confirmation !== form.password) {
    errors.password_confirmation = '两次输入的密码不一致';
  }

  if (Object.keys(errors).length > 0) {
    setFormErrors(errors);
    return false;
  }

  liveText.value = '';
  formRef.value?.clearValidate();
  return true;
}

async function runRegister() {
  if (loading.value) return;
  loading.value = true;
  try {
    await userStore.clientRegister({
      account: normalizeAccountValue(form.account),
      code: form.code,
      nickname: form.nickname || undefined,
      referral_code: form.referral_code || undefined,
      password: form.password,
      password_confirmation: form.password_confirmation,
    });
    MessagePlugin.success('注册成功');
    await router.push(redirectPath.value);
  } catch (error: unknown) {
    const runtimeError = error as RuntimeHandledError;
    if (!runtimeError.__handled) {
      MessagePlugin.error(toUserMessage(runtimeError.message, '注册失败'));
    }
  } finally {
    loading.value = false;
  }
}

onBeforeUnmount(() => {
  clearTimer();
});
</script>
<style scoped lang="less">
@import './shared-auth.less';
</style>
