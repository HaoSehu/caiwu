<template>
  <div class="login-container">
    <div class="login-card">
      <div class="login-header">
        <h1>管理后台</h1>
        <p>{{ mfaChallenge ? '请输入身份验证器中的 6 位动态码' : '请登录您的管理员账号' }}</p>
      </div>
      <t-form
        v-if="!mfaChallenge"
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
            size="large"
            clearable
            autocomplete="username"
          />
        </t-form-item>
        <t-form-item label="密码" name="password">
          <t-input
            v-model="formData.password"
            type="password"
            placeholder="请输入密码"
            size="large"
            clearable
            autocomplete="current-password"
          />
        </t-form-item>
        <t-form-item class="login-submit-item">
          <t-button block theme="primary" size="large" type="submit" :loading="loading"> 登录 </t-button>
          <span class="sr-only" role="alert" aria-live="assertive">{{ errorMessage }}</span>
        </t-form-item>
      </t-form>
      <t-form
        v-else
        ref="mfaFormRef"
        :data="mfaFormData"
        :rules="mfaFormRules"
        label-align="top"
        @submit="handleVerifyMfa"
      >
        <t-form-item label="动态验证码" name="code">
          <t-input
            v-model="mfaFormData.code"
            placeholder="6 位动态码，或一条恢复码"
            size="large"
            clearable
            autocomplete="one-time-code"
            :maxlength="32"
          />
        </t-form-item>
        <t-form-item class="login-submit-item">
          <t-button block theme="primary" size="large" type="submit" :loading="loading"> 验证并登录 </t-button>
          <t-button block variant="text" size="large" :disabled="loading" @click="handleBackToPassword">
            返回账号密码登录
          </t-button>
          <span class="sr-only" role="alert" aria-live="assertive">{{ errorMessage }}</span>
        </t-form-item>
      </t-form>
      <div class="login-footer">
        <span>© {{ currentYear }} Caiwu 管理后台</span>
      </div>
    </div>
  </div>
</template>
<script setup lang="ts">
import type { FormInstanceFunctions, FormRule } from 'tdesign-vue-next';
import { MessagePlugin } from 'tdesign-vue-next/es/message/plugin';
import { computed, ref } from 'vue';
import { useRouter } from 'vue-router';

import { hasPermissionInList } from '@/constants/permissions';
import { getPermissionStore, useUserStore } from '@/store';

const router = useRouter();
const userStore = useUserStore();
const permissionStore = getPermissionStore();
const formRef = ref<FormInstanceFunctions>();
const mfaFormRef = ref<FormInstanceFunctions>();
const loading = ref(false);
const errorMessage = ref('');
const currentYear = computed(() => new Date().getFullYear());

const formData = ref({
  account: '',
  password: '',
});

const formRules: Record<string, FormRule[]> = {
  account: [{ required: true, message: '请输入账号', trigger: 'blur' }],
  password: [{ required: true, message: '请输入密码', trigger: 'blur' }],
};

// 双因素第二步状态：挑战凭证只在内存里，不写 localStorage/Cookie，
// 刷新页面即失效，与后端「5 分钟一次性」的语义保持一致。
const mfaChallenge = ref('');
const mfaFormData = ref({ code: '' });
const mfaFormRules: Record<string, FormRule[]> = {
  code: [{ required: true, message: '请输入动态验证码', trigger: 'blur' }],
};

function canAccessPath(path: string): boolean {
  const resolved = router.resolve(path);
  // 未注册路由（已下架/角色权限不全）不应放行，交由兜底处理
  if (resolved.matched.length === 0) return false;
  const required = resolved.meta?.permission as string | undefined;
  if (!required || required === '') return true;
  return hasPermissionInList(userStore.userInfo?.permissions || [], required);
}

/**
 * 登录成功后的统一收尾：建菜单 → 选落地页 → 处理 redirect。
 * 账号密码登录与双因素登录都必须走同一段逻辑，避免两条路径行为分叉。
 */
async function enterConsole(): Promise<void> {
  MessagePlugin.success('登录成功');
  // 先构建权限菜单，无仪表盘权限角色也能落到其首个可访问页面而非 403
  if (!permissionStore.menuBuilt || !userStore.userInfo?.name) {
    try {
      await permissionStore.setupMenus(userStore.userInfo?.permissions || []);
    } catch {
      errorMessage.value = '菜单初始化失败，请刷新页面重试';
      MessagePlugin.error(errorMessage.value);
      return;
    }
  }
  const fallbackPath = permissionStore.firstAccessibleAdminPath;
  if (!canAccessPath(fallbackPath)) {
    errorMessage.value = '当前账号无可用页面，请联系管理员';
    MessagePlugin.error(errorMessage.value);
    return;
  }
  const redirect = router.currentRoute.value.query.redirect;
  if (redirect && typeof redirect === 'string') {
    try {
      const decoded = decodeURIComponent(redirect);
      // redirect 目标若不在当前角色权限内，回退到首个可访问页面，避免落入 403
      if (decoded.startsWith('/') && !decoded.startsWith('//') && canAccessPath(decoded)) {
        router.push(decoded);
      } else {
        router.push(fallbackPath);
      }
    } catch {
      router.push(fallbackPath);
    }
  } else {
    router.push(fallbackPath);
  }
}

function handleBackToPassword() {
  mfaChallenge.value = '';
  mfaFormData.value.code = '';
  errorMessage.value = '';
}

async function handleLogin() {
  const valid = await formRef.value?.validate();
  if (valid !== true) return;

  loading.value = true;
  try {
    const result = await userStore.login({
      account: formData.value.account,
      password: formData.value.password,
    });

    if (result.mfaRequired) {
      if (!result.challengeToken) {
        errorMessage.value = '双因素登录凭证获取失败，请重新登录';
        MessagePlugin.error(errorMessage.value);
        return;
      }
      mfaChallenge.value = result.challengeToken;
      mfaFormData.value.code = '';
      errorMessage.value = '';
      return;
    }

    await enterConsole();
  } catch (error) {
    const msg = error instanceof Error ? error.message : '登录失败，请检查账号密码';
    errorMessage.value = msg;
    MessagePlugin.error(msg);
  } finally {
    loading.value = false;
  }
}

async function handleVerifyMfa() {
  const valid = await mfaFormRef.value?.validate();
  if (valid !== true) return;

  loading.value = true;
  try {
    await userStore.completeMfaLogin(mfaChallenge.value, mfaFormData.value.code.trim());
    mfaChallenge.value = '';
    mfaFormData.value.code = '';
    await enterConsole();
  } catch (error) {
    const msg = error instanceof Error ? error.message : '验证失败，请重新登录';
    errorMessage.value = msg;
    MessagePlugin.error(msg);
    // 后端挑战凭证一次性消费，验证码错一次即失效，必须退回密码登录。
    if (msg.includes('重新登录')) {
      handleBackToPassword();
    }
  } finally {
    loading.value = false;
  }
}
</script>
<style lang="less" scoped>
.login-container {
  // 100dvh 让移动端地址栏收起/展开时高度不跳变；100vh 作为旧内核回退。
  min-height: 100vh;
  min-height: 100dvh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 40px 20px;
  position: relative;
  overflow: hidden;
  background: var(--td-bg-color-page, #f5f7fb);

  /* brand-tinted radial glow spots */
  &::before,
  &::after {
    content: '';
    position: absolute;
    border-radius: 50%;
    pointer-events: none;
    z-index: 0;
  }

  &::before {
    top: -180px;
    right: -120px;
    width: 520px;
    height: 520px;
    background: radial-gradient(circle, rgb(22 93 255 / 10%), rgb(22 93 255 / 0%) 70%);
  }

  &::after {
    bottom: -140px;
    left: -100px;
    width: 440px;
    height: 440px;
    background: radial-gradient(circle, rgb(22 93 255 / 7%), rgb(22 93 255 / 0%) 70%);
  }
}

.login-card {
  width: 100%;
  max-width: 420px;
  padding: 48px 40px 36px;
  background: var(--td-bg-color-container, #fff);
  border-radius: var(--td-radius-extraLarge, 12px);
  box-shadow:
    0 8px 32px rgb(0 0 0 / 6%),
    0 1px 4px rgb(0 0 0 / 4%);
  position: relative;
  z-index: 1;
}

.login-header {
  text-align: center;
  margin-bottom: 36px;
  padding-bottom: 28px;
  border-bottom: 1px solid var(--td-component-stroke, #eef2f7);

  h1 {
    font-size: var(--td-font-size-size-7, 22px);
    font-weight: 600;
    color: var(--td-text-color-primary, #1f2937);
    margin-bottom: 6px;
    letter-spacing: -0.01em;
  }

  p {
    font-size: var(--td-font-size-size-3, 14px);
    color: var(--td-text-color-secondary, #5b6b82);
  }
}

.login-submit-item {
  :deep(.t-form__label) {
    display: none;
  }

  :deep(.t-button + .t-button) {
    margin-top: 8px;
  }
}

.login-footer {
  margin-top: 28px;
  text-align: center;
  font-size: var(--td-font-size-size-1, 12px);
  color: var(--td-text-color-placeholder, #94a0b2);
  line-height: 1.6;
}

/* responsive: remove decorative glow on small screens */
@media (max-width: 640px) {
  .login-container {
    padding: 24px 16px;

    &::before,
    &::after {
      display: none;
    }
  }

  .login-card {
    padding: 36px 24px 28px;
    border-radius: 10px;
    box-shadow: 0 2px 12px rgb(0 0 0 / 5%);
  }

  .login-header {
    margin-bottom: 28px;
    padding-bottom: 22px;

    h1 {
      font-size: var(--td-font-size-size-6, 20px);
    }

    p {
      font-size: var(--td-font-size-size-2, 13px);
    }
  }

  .login-footer {
    margin-top: 20px;
    font-size: 11px;
  }
}
</style>
