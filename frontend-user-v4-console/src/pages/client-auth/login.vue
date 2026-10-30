<template>
  <auth-shell
    title="账号登录"
    nav-text="还没有账户？"
    nav-link-text="现在注册"
    :nav-to="registerLink"
    :hero-title="heroTitle"
    hero-description="登录后可继续查看实例、支付账单、提交工单，并维护账户安全与实名认证。"
  >
    <t-tabs v-model="loginMode" class="client-auth-tabs">
      <t-tab-panel value="password" label="密码登录" />
      <t-tab-panel value="code" label="验证码登录" />
    </t-tabs>

    <!-- A-11：校验结果的读屏通道。节点必须常驻（v-if 挂载的节点不会被播报），
         文本只跟随当前可见的那套表单，避免两套错误同时被读出来。 -->
    <p class="client-auth-live" role="status" aria-live="polite">{{ liveText }}</p>

    <!-- 密码登录 -->
    <t-form
      v-if="loginMode === 'password'"
      ref="formRef"
      class="client-auth-form"
      :data="form"
      :rules="rules"
      label-width="0"
      @submit="handleLogin"
    >
      <t-form-item name="account">
        <!-- A-09：t-input 只把传入的属性落到 .t-input__wrap 这个 div 上（input.mjs 的
             inputAttrs 白名单里没有 id / aria-*），原生 input 拿不到 id，所以 <label for>
             永远指不到控件，点标签不聚焦、读屏也不会把标签念给输入框。改成用 label 元素
             把控件包起来靠隐式关联，标签自身降级为 span。 -->
        <label class="client-auth-field">
          <span class="client-auth-label is-required">手机号 / 邮箱<span class="sr-only">（必填）</span></span>
          <t-input
            id="login-account"
            v-model="form.account"
            size="large"
            clearable
            autocomplete="username"
            placeholder="请输入手机号或邮箱"
            @enter="submitForm"
          >
            <template #prefix-icon><user-icon /></template>
          </t-input>
        </label>
      </t-form-item>

      <t-form-item name="password">
        <label class="client-auth-field client-auth-field--password">
          <span class="client-auth-label is-required">密码<span class="sr-only">（必填）</span></span>
          <t-input
            id="login-password"
            v-model="form.password"
            size="large"
            :type="showPassword ? 'text' : 'password'"
            clearable
            autocomplete="current-password"
            placeholder="请输入登录密码"
            @enter="submitForm"
          >
            <template #prefix-icon><lock-on-icon /></template>
            <template #suffix>
              <password-toggle v-model="showPassword" />
            </template>
          </t-input>
        </label>
      </t-form-item>

      <div class="client-auth-form__links">
        <router-link to="/client/forgot-password">忘记密码？</router-link>
      </div>

      <t-button
        class="client-auth-submit"
        block
        size="large"
        theme="primary"
        :loading="loading || captchaLoading"
        :disabled="loading || captchaLoading"
        @click="submitForm"
        >登录</t-button
      >
    </t-form>

    <!-- 验证码登录 -->
    <t-form
      v-if="loginMode === 'code'"
      ref="codeFormRef"
      class="client-auth-form"
      :data="codeForm"
      :rules="codeRules"
      label-width="0"
      @submit="handleCodeLogin"
    >
      <t-form-item name="account">
        <label class="client-auth-field">
          <span class="client-auth-label is-required">手机号 / 邮箱<span class="sr-only">（必填）</span></span>
          <t-input
            id="login-code-account"
            v-model="codeForm.account"
            size="large"
            clearable
            autocomplete="username"
            placeholder="请输入已注册的手机号或邮箱"
          >
            <template #prefix-icon><user-icon /></template>
          </t-input>
        </label>
      </t-form-item>

      <t-form-item name="code">
        <label class="client-auth-field">
          <span class="client-auth-label is-required">验证码<span class="sr-only">（必填）</span></span>
          <div class="client-auth-code-row">
            <t-input
              id="login-code"
              v-model="codeForm.code"
              size="large"
              maxlength="6"
              placeholder="请输入验证码"
              @enter="submitCodeForm"
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

      <t-button
        class="client-auth-submit"
        block
        size="large"
        theme="primary"
        :loading="codeLoading"
        :disabled="codeLoading || sendingCode"
        @click="submitCodeForm"
        >登录</t-button
      >
    </t-form>
  </auth-shell>
</template>
<script setup lang="ts">
import { toUserMessage } from '@caiwu/shared/runtime';
import { LockOnIcon, UserIcon } from 'tdesign-icons-vue-next';
import type { FormInstanceFunctions, FormRule, FormValidateMessage, SubmitContext } from 'tdesign-vue-next';
import { MessagePlugin } from 'tdesign-vue-next/es/message/plugin';
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';

import { clientAuthApi } from '@/api/auth';
import AuthShell from '@/components/auth/AuthShell.vue';
import PasswordToggle from '@/components/auth/PasswordToggle.vue';
import { useGeeTestCaptcha } from '@/composables/useGeeTestCaptcha';
import { useUserStore } from '@/store';
import { buildAccountPayload, detectAccountType, normalizeAccountValue } from '@/utils/account';
import { resolveLoginRedirectPath } from '@/utils/loginRedirect';
import { clearLoginRedirectHold, holdLoginRedirect } from '@/utils/request';

interface LoginForm {
  account: string;
  password: string;
}

interface RuntimeLoginError {
  __handled?: boolean;
  message?: string;
  response?: {
    data?: {
      data?: {
        captcha_required?: boolean;
      };
    };
  };
}

function asRuntimeLoginError(error: unknown): RuntimeLoginError {
  return typeof error === 'object' && error !== null ? (error as RuntimeLoginError) : {};
}

interface CodeLoginForm {
  account: string;
  code: string;
}

interface RuntimeCodeSendError {
  __handled?: boolean;
  message?: string;
}

const route = useRoute();
const router = useRouter();
const userStore = useUserStore();
const formRef = ref<FormInstanceFunctions<LoginForm>>();
const loading = ref(false);
const showPassword = ref(false);
const { enabled, loading: captchaLoading, runWithCaptcha } = useGeeTestCaptcha();

const form = reactive<LoginForm>({
  account: '',
  password: '',
});

const loginMode = ref<'password' | 'code'>('password');
const codeFormRef = ref<FormInstanceFunctions<CodeLoginForm>>();
const codeForm = reactive<CodeLoginForm>({ account: '', code: '' });
const codeLoading = ref(false);
const sendingCode = ref(false);
const countdown = ref(0);
let countdownTimer: ReturnType<typeof setInterval> | null = null;

const codeRules: Record<keyof CodeLoginForm, FormRule[]> = {
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
  code: [
    { required: true, message: '请输入验证码', type: 'error', trigger: 'blur' },
    { len: 6, message: '验证码为 6 位', type: 'error', trigger: 'blur' },
  ],
};

const redirectPath = computed(() => resolveLoginRedirectPath(route.query.redirect));

const registerLink = computed(() => ({
  path: '/client/register',
  query: route.query.redirect ? { redirect: route.query.redirect } : {},
}));

const heroTitle = '进入控制台，\n继续处理服务与账单';

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

const isCaptchaRequiredError = (error: unknown) =>
  Boolean(asRuntimeLoginError(error).response?.data?.data?.captcha_required);

async function performLogin(captcha: unknown = null) {
  await userStore.clientLogin({
    account: normalizeAccountValue(form.account),
    password: form.password,
    ...(captcha ? { captcha } : {}),
  });
}

async function submitForm() {
  if (loading.value || captchaLoading.value) return;
  if (!validateForm()) {
    return;
  }
  await runLogin();
}

async function handleLogin(ctx: SubmitContext) {
  if (loading.value || captchaLoading.value) return;
  if (ctx.validateResult !== true || !validateForm()) return;
  await runLogin();
}

// A-11：校验失败的读屏播报文案。视觉上的红字由 t-form 渲染，这里只补无障碍通道。
const formLiveText = ref('');
const codeLiveText = ref('');
const liveText = computed(() => (loginMode.value === 'password' ? formLiveText.value : codeLiveText.value));

function setFormErrors(errors: Partial<Record<keyof LoginForm, string>>) {
  formLiveText.value = Object.values(errors).filter(Boolean).join('；');
  const validateMessage: FormValidateMessage<LoginForm> = {
    account: errors.account ? [{ type: 'error', message: errors.account }] : [],
    password: errors.password ? [{ type: 'error', message: errors.password }] : [],
  };
  formRef.value?.setValidateMessage(validateMessage);
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

  formLiveText.value = '';
  formRef.value?.clearValidate();
  return true;
}

async function runLogin() {
  if (loading.value) return;
  // 登录提交期间屏蔽并发 401 弹跳，避免成功跳转被旧请求失效竞态打断
  holdLoginRedirect();
  loading.value = true;
  try {
    if (enabled.value) {
      await runWithCaptcha(
        async (captcha: unknown) => {
          await performLogin(captcha);
        },
        { required: true },
      );
    } else {
      await performLogin();
    }
    MessagePlugin.success('登录成功');
    await router.push(redirectPath.value);
  } catch (error: unknown) {
    clearLoginRedirectHold();
    const runtimeError = asRuntimeLoginError(error);
    if (!enabled.value && isCaptchaRequiredError(runtimeError)) {
      try {
        await runWithCaptcha(
          async (captcha: unknown) => {
            await performLogin(captcha);
          },
          { required: true },
        );
        MessagePlugin.success('登录成功');
        await router.push(redirectPath.value);
        return;
      } catch (captchaError: unknown) {
        const runtimeCaptchaError = asRuntimeLoginError(captchaError);
        if (!runtimeCaptchaError.__handled) {
          MessagePlugin.error(toUserMessage(runtimeCaptchaError.message, '登录失败'));
        }
        return;
      }
    }

    if (!runtimeError.__handled) {
      MessagePlugin.error(toUserMessage(runtimeError.message, '登录失败'));
    }
  } finally {
    loading.value = false;
  }
}

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
      // A-17：复位为 0。register / forgot-password 都这么做，只有这里漏了，
      // 计时器停在被减到的负数上（-1），按钮文案虽仍靠 countdown > 0 兜住，
      // 但两页对同一状态给出不同值，属实现漂移。
      countdown.value = 0;
    }
  }, 1000);
}

async function handleSendCode() {
  if (sendingCode.value || countdown.value > 0) return;
  const accountPayload = buildAccountPayload(codeForm.account);
  if (!accountPayload) {
    MessagePlugin.warning('请先输入正确的手机号或邮箱');
    return;
  }

  sendingCode.value = true;
  try {
    await runWithCaptcha(async (captcha: unknown) => {
      if (accountPayload.accountType === 'phone') {
        await clientAuthApi.sendPhoneCode({ phone: accountPayload.phone, purpose: 'login', captcha });
      } else {
        await clientAuthApi.sendEmailCode({ email: accountPayload.email, purpose: 'login', captcha });
      }
    });

    MessagePlugin.success(`${accountPayload.accountType === 'phone' ? '短信' : '邮箱'}验证码已发送`);
    startCountdown();
  } catch (error: unknown) {
    const runtimeError = error as RuntimeCodeSendError;
    if (!runtimeError.__handled) {
      MessagePlugin.error(toUserMessage(runtimeError.message, '验证码发送失败'));
    }
  } finally {
    sendingCode.value = false;
  }
}

async function handleCodeLogin(ctx: SubmitContext) {
  if (codeLoading.value) return;
  if (ctx.validateResult !== true || !validateCodeForm()) return;
  await runCodeLogin();
}

function validateCodeForm() {
  const errors: Partial<Record<keyof CodeLoginForm, string>> = {};
  if (!detectAccountType(codeForm.account)) {
    errors.account = '请输入正确的手机号或邮箱';
  }
  if (!codeForm.code || codeForm.code.length !== 6) {
    errors.code = codeForm.code ? '验证码为 6 位' : '请输入验证码';
  }

  if (Object.keys(errors).length > 0) {
    codeLiveText.value = Object.values(errors).filter(Boolean).join('；');
    const validateMessage: FormValidateMessage<CodeLoginForm> = {
      account: errors.account ? [{ type: 'error', message: errors.account }] : [],
      code: errors.code ? [{ type: 'error', message: errors.code }] : [],
    };
    codeFormRef.value?.setValidateMessage(validateMessage);
    return false;
  }

  codeLiveText.value = '';
  codeFormRef.value?.clearValidate();
  return true;
}

async function runCodeLogin() {
  if (codeLoading.value) return;
  holdLoginRedirect();
  codeLoading.value = true;
  try {
    await userStore.clientLoginByCode({
      account: normalizeAccountValue(codeForm.account),
      code: codeForm.code,
    });
    MessagePlugin.success('登录成功');
    await router.push(redirectPath.value);
  } catch (error: unknown) {
    clearLoginRedirectHold();
    const runtimeError = error as RuntimeLoginError;
    if (!runtimeError.__handled) {
      MessagePlugin.error(toUserMessage(runtimeError.message, '登录失败'));
    }
  } finally {
    codeLoading.value = false;
  }
}

async function submitCodeForm() {
  if (codeLoading.value) return;
  if (!validateCodeForm()) return;
  await runCodeLogin();
}

onMounted(() => {
  if (typeof route.query.account === 'string') {
    // A-18：两套登录表单共用同一个入口参数。只回填密码表单时，
    // 从带 account 的链接进来再切「验证码登录」，账号栏是空的要重输一遍。
    form.account = route.query.account;
    codeForm.account = route.query.account;
  }
});

onBeforeUnmount(() => {
  clearTimer();
});
</script>
<style scoped lang="less">
@import './shared-auth.less';

// A-04：这里原先重定义了 .client-auth-code-row（flex + gap 0.75rem + 按钮 height:auto），
// 而 register / forgot-password 只 @import shared-auth.less，导致同一条验证码行在三个页面
// 呈两套布局、两套高度。验证码行属于通用表单骨架，统一由 shared-auth.less 提供。

// A-14：这里原先还有一条 .client-auth-submit { margin-top: var(--td-comp-margin-s) }，
// 与 shared-auth.less:144-146 完全重复；配合密码模式按钮漏写该类，造成切 tab 时间距跳变。
// 现统一由 shared-auth.less 提供，页面只保留 tabs 自身样式。
.client-auth-tabs {
  margin-bottom: var(--td-comp-margin-l);

  :deep(.t-tabs__nav-item) {
    font-size: var(--td-font-size-body-medium);
  }
}
</style>
