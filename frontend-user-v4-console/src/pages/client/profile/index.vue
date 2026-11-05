<template>
  <section class="profile-page">
    <h1 class="sr-only">个人资料</h1>

    <!-- 账户中心导航只渲染一份（AC-24）：原先移动标签栏与桌面侧栏同时存在于 DOM，
         靠 CSS 二选一显示，读屏与 Tab 键顺序里会各出现一次同样的三个入口。
         这里用 isDesktop（≥62rem）与下面 :596 的媒体查询同档，不会出现两份或零份。 -->
    <nav v-if="!isDesktop" class="profile-tabs-mobile" aria-label="账户中心">
      <button
        v-for="tab in profileTabs"
        :key="tab.value"
        type="button"
        class="profile-tabs-mobile__item"
        :class="{ 'is-active': activeTab === tab.value }"
        @click="handleProfileTabChange(tab.value)"
      >
        {{ tab.label }}
      </button>
    </nav>

    <aside v-else class="profile-nav">
      <t-card class="profile-card" :bordered="false">
        <template #title>账户中心</template>
        <t-menu :value="activeTab" theme="light" @change="handleProfileTabChange">
          <t-menu-item value="profile">个人资料</t-menu-item>
          <t-menu-item value="security">账户安全</t-menu-item>
          <t-menu-item value="notification">消息提醒</t-menu-item>
        </t-menu>
      </t-card>
    </aside>

    <main class="profile-main">
      <t-alert v-if="profileError" theme="error" class="profile-alert" :message="profileError">
        <template #operation>
          <t-button size="small" variant="outline" @click="reloadProfile">重试</t-button>
        </template>
      </t-alert>
      <t-card v-if="activeTab === 'profile'" class="profile-card" :bordered="false">
        <template #title>个人资料</template>
        <template #actions><t-tag variant="light">基础信息</t-tag></template>
        <!-- 平板及以下（≤ @screen-sm-max）标签改为顶部对齐：TDesign 在 top 对齐时不再写内联 width/marginLeft，样式无需 !important -->
        <t-form :label-align="isDesktop ? 'left' : 'top'" label-width="6rem" class="profile-form">
          <t-form-item label="账户ID">
            <div class="profile-id-row">
              <t-input :value="profileForm.id" readonly />
              <t-button variant="outline" shape="square" aria-label="复制账户ID" @click="copyText(profileForm.id)">
                <copy-icon />
              </t-button>
            </div>
          </t-form-item>
          <t-form-item label="注册时间"><t-input :value="profileForm.createdAt || '--'" readonly /></t-form-item>
          <t-form-item label="用户名" :help="nicknameHint"
            ><t-input v-model="profileForm.nickname" maxlength="50" placeholder="请输入用户名"
          /></t-form-item>
          <t-form-item label="账户余额"><t-input :value="balanceText" readonly /></t-form-item>
          <t-form-item label="登录邮箱"><t-input :value="profileForm.email || '--'" readonly /></t-form-item>
          <t-form-item label="账户状态">
            <t-tag :theme="profileForm.is_verified ? 'success' : 'default'" variant="light">
              {{ profileForm.is_verified ? '已实名' : '未实名' }}
            </t-tag>
          </t-form-item>
        </t-form>
        <div class="profile-footer">
          <span>保存后会立即更新当前账户资料。</span>
          <t-button theme="primary" :loading="savingProfile" :disabled="savingProfile" @click="updateProfile"
            >保存资料</t-button
          >
        </div>
      </t-card>

      <t-card v-else-if="activeTab === 'security'" class="profile-card" :bordered="false">
        <template #title>账户安全</template>
        <div class="security-list">
          <article v-for="item in securityItems" :key="item.key" class="security-item">
            <div>
              <div class="security-item__head">
                <strong>{{ item.name }}</strong>
                <t-tag :theme="item.theme" variant="light">{{ item.tag }}</t-tag>
              </div>
              <p>{{ item.desc }}</p>
            </div>
            <t-button theme="primary" variant="text" @click="item.action">{{ item.actionLabel }}</t-button>
          </article>
        </div>
      </t-card>

      <t-card v-else class="profile-card" :bordered="false">
        <template #title>消息提醒</template>
        <template #actions
          ><t-tag variant="light">已开启 {{ enabledNotificationCount }}</t-tag></template
        >
        <div class="notification-list">
          <article v-for="item in notificationList" :key="item.key" class="notification-item">
            <div>
              <strong>{{ item.name }}</strong>
              <p>{{ item.desc }}</p>
            </div>
            <t-switch v-model="item.enabled" />
          </article>
        </div>
        <div class="profile-footer">
          <span>关闭安全提醒可能会错过密码、邮箱或手机号变更通知。</span>
          <t-button theme="primary" :loading="notificationLoading" @click="saveNotificationPreferences"
            >保存设置</t-button
          >
        </div>
      </t-card>
    </main>

    <t-dialog
      v-model:visible="passwordDialogVisible"
      :header="passwordMode === 'old' ? '编辑登录密码' : '验证码重置密码'"
      width="min(30rem, calc(100vw - 2rem))"
      :close-btn="!changingPassword"
      :close-on-esc-keydown="!changingPassword"
      :close-on-overlay-click="!changingPassword"
      @close="handlePasswordDialogClose"
    >
      <t-form v-if="passwordMode === 'old'" ref="passwordFormRef" label-align="right">
        <t-form-item label="原密码" name="oldPassword" :rules="requiredBlur('请输入原密码')">
          <t-input v-model="passwordForm.oldPassword" type="password" />
        </t-form-item>
        <t-form-item label="新密码" name="newPassword" :rules="newPasswordRules">
          <t-input v-model="passwordForm.newPassword" type="password" />
        </t-form-item>
        <t-form-item label="确认密码" name="confirmPassword" :rules="confirmPasswordRulesOld">
          <t-input v-model="passwordForm.confirmPassword" type="password" />
        </t-form-item>
        <t-button variant="text" theme="primary" class="password-forgot" @click="togglePasswordMode"
          >忘记原密码？</t-button
        >
      </t-form>
      <t-form v-else ref="resetFormRef" label-align="right">
        <!-- 验证方式只有两个互斥选项，且不承载独立内容：用单选按钮组替代 t-tabs，
             避免在弹窗内再套一层标签页导航（AC-16） -->
        <t-form-item v-if="profileForm.phone && profileForm.email" label="验证方式">
          <t-radio-group v-model="resetForm.type" theme="button" variant="default-filled">
            <t-radio-button value="phone">手机验证</t-radio-button>
            <t-radio-button value="email">邮箱验证</t-radio-button>
          </t-radio-group>
        </t-form-item>
        <div v-else-if="profileForm.phone" class="reset-single-tip">验证方式：手机验证</div>
        <div v-else class="reset-single-tip">验证方式：邮箱验证</div>
        <t-form-item label="验证对象"
          ><t-input :value="resetForm.type === 'phone' ? profileForm.phone : profileForm.email" readonly
        /></t-form-item>
        <t-form-item label="验证码" name="code" :rules="requiredBlur('请输入验证码')">
          <div class="bind-code-row">
            <t-input v-model="resetForm.code" placeholder="请输入 6 位验证码" maxlength="6" inputmode="numeric" />
            <t-button
              variant="outline"
              :disabled="resetCountdown > 0"
              :loading="codeSending.reset"
              @click="sendResetCode"
            >
              {{ resetCountdown > 0 ? `${resetCountdown}s` : '发送验证码' }}
            </t-button>
          </div>
        </t-form-item>
        <t-form-item label="新密码" name="password" :rules="newPasswordRules">
          <t-input v-model="resetForm.password" type="password" placeholder="至少 6 位" />
        </t-form-item>
        <t-form-item label="确认密码" name="confirmPassword" :rules="confirmPasswordRulesReset">
          <t-input v-model="resetForm.confirmPassword" type="password" />
        </t-form-item>
        <t-button variant="text" theme="primary" class="password-forgot" @click="togglePasswordMode"
          >使用原密码修改</t-button
        >
      </t-form>
      <template #footer>
        <t-button variant="outline" :disabled="changingPassword" @click="handlePasswordDialogClose">取消</t-button>
        <t-button
          v-if="passwordMode === 'old'"
          theme="primary"
          :loading="changingPassword"
          :disabled="changingPassword"
          @click="confirmPasswordChange"
          >确定</t-button
        >
        <t-button
          v-else
          theme="primary"
          :loading="changingPassword"
          :disabled="changingPassword"
          @click="confirmResetPassword"
          >确定</t-button
        >
      </template>
    </t-dialog>

    <t-dialog v-model:visible="phoneDialogVisible" header="更换绑定手机" width="min(30rem, calc(100vw - 2rem))">
      <t-form v-if="phoneChangeStep === 1" label-align="right">
        <div class="reset-single-tip">为确认是本人操作，请先验证当前绑定的手机号 {{ profileForm.phone }}</div>
        <t-form-item label="验证码">
          <div class="bind-code-row">
            <t-input v-model="phoneForm.oldCode" placeholder="请输入 6 位验证码" maxlength="6" inputmode="numeric" />
            <t-button
              variant="outline"
              :disabled="phoneOldCountdown > 0"
              :loading="codeSending.phoneOld"
              @click="sendPhoneOldVerificationCode"
            >
              {{ phoneOldCountdown > 0 ? `${phoneOldCountdown}s` : '发送验证码' }}
            </t-button>
          </div>
        </t-form-item>
      </t-form>
      <t-form v-else ref="phoneFormRef" label-align="right">
        <t-form-item label="新手机号" name="phone" :rules="phoneRules">
          <t-input
            v-model="phoneForm.phone"
            type="tel"
            inputmode="numeric"
            maxlength="11"
            placeholder="请输入新手机号"
          />
        </t-form-item>
        <t-form-item label="验证码">
          <div class="bind-code-row">
            <t-input v-model="phoneForm.code" placeholder="请输入 6 位验证码" maxlength="6" inputmode="numeric" />
            <t-button
              variant="outline"
              :disabled="phoneCountdown > 0"
              :loading="codeSending.phone"
              @click="sendPhoneVerificationCode"
            >
              {{ phoneCountdown > 0 ? `${phoneCountdown}s` : '发送验证码' }}
            </t-button>
          </div>
        </t-form-item>
      </t-form>
      <template #footer>
        <t-button variant="outline" :disabled="submittingPhone" @click="phoneDialogVisible = false">取消</t-button>
        <t-button
          v-if="phoneChangeStep === 2 && profileForm.phone"
          variant="outline"
          :disabled="submittingPhone"
          @click="phoneChangeStep = 1"
          >上一步</t-button
        >
        <t-button
          v-if="phoneChangeStep === 1"
          theme="primary"
          :loading="submittingPhone"
          :disabled="submittingPhone"
          @click="goPhoneNextStep"
          >下一步</t-button
        >
        <t-button
          v-else
          theme="primary"
          :loading="submittingPhone"
          :disabled="submittingPhone"
          @click="confirmPhoneChange"
          >确定</t-button
        >
      </template>
    </t-dialog>

    <t-dialog v-model:visible="emailDialogVisible" header="更换绑定邮箱" width="min(30rem, calc(100vw - 2rem))">
      <t-form v-if="emailChangeStep === 1" label-align="right">
        <div class="reset-single-tip">为确认是本人操作，请先验证当前绑定的邮箱 {{ profileForm.email }}</div>
        <t-form-item label="验证码">
          <div class="bind-code-row">
            <t-input v-model="emailForm.oldCode" placeholder="请输入 6 位验证码" maxlength="6" />
            <t-button
              variant="outline"
              :disabled="emailOldCountdown > 0"
              :loading="codeSending.emailOld"
              @click="sendEmailOldVerificationCode"
            >
              {{ emailOldCountdown > 0 ? `${emailOldCountdown}s` : '发送验证码' }}
            </t-button>
          </div>
        </t-form-item>
      </t-form>
      <t-form v-else ref="emailFormRef" label-align="right">
        <t-form-item label="新邮箱" name="email" :rules="emailRules">
          <t-input v-model="emailForm.email" placeholder="请输入新邮箱" />
        </t-form-item>
        <t-form-item label="验证码">
          <div class="bind-code-row">
            <t-input v-model="emailForm.code" placeholder="请输入 6 位验证码" maxlength="6" />
            <t-button
              variant="outline"
              :disabled="emailCountdown > 0"
              :loading="codeSending.email"
              @click="sendEmailVerificationCode"
            >
              {{ emailCountdown > 0 ? `${emailCountdown}s` : '发送验证码' }}
            </t-button>
          </div>
        </t-form-item>
      </t-form>
      <template #footer>
        <t-button variant="outline" :disabled="submittingEmail" @click="emailDialogVisible = false">取消</t-button>
        <t-button
          v-if="emailChangeStep === 2 && profileForm.email"
          variant="outline"
          :disabled="submittingEmail"
          @click="emailChangeStep = 1"
          >上一步</t-button
        >
        <t-button
          v-if="emailChangeStep === 1"
          theme="primary"
          :loading="submittingEmail"
          :disabled="submittingEmail"
          @click="goEmailNextStep"
          >下一步</t-button
        >
        <t-button
          v-else
          theme="primary"
          :loading="submittingEmail"
          :disabled="submittingEmail"
          @click="confirmEmailChange"
          >确定</t-button
        >
      </template>
    </t-dialog>
  </section>
</template>
<script setup lang="ts">
import { CopyIcon } from 'tdesign-icons-vue-next';
import type { FormInstanceFunctions, FormRule } from 'tdesign-vue-next';
import { ref } from 'vue';

import { useDeviceLayout } from '@/composables/useDeviceLayout';
import { useProfile } from '@/domains/account/useProfile';

const profileTabs = [
  { value: 'profile', label: '个人资料' },
  { value: 'security', label: '账户安全' },
  { value: 'notification', label: '消息提醒' },
] as const;

// 表格/表单的形态切换听 useDeviceLayout（mobile=48rem），与 :587 的 @media (max-width: @screen-sm-max) 恰好互补
const { isDesktop } = useDeviceLayout();

const {
  activeTab,
  savingProfile,
  changingPassword,
  submittingPhone,
  submittingEmail,
  codeSending,
  profileError,
  loadProfile,
  notificationLoading,
  passwordDialogVisible,
  phoneDialogVisible,
  emailDialogVisible,
  phoneChangeStep,
  emailChangeStep,
  passwordMode,
  profileForm,
  passwordForm,
  resetForm,
  resetCountdown,
  phoneForm,
  emailForm,
  phoneCountdown,
  emailCountdown,
  phoneOldCountdown,
  emailOldCountdown,
  notificationList,
  balanceText,
  nicknameHint,
  enabledNotificationCount,
  securityItems,
  copyText,
  updateProfile,
  changePassword,
  togglePasswordMode,
  sendResetCode,
  submitResetPassword,
  sendPhoneVerificationCode,
  sendEmailVerificationCode,
  sendPhoneOldVerificationCode,
  sendEmailOldVerificationCode,
  goPhoneNextStep,
  goEmailNextStep,
  submitPhoneChange,
  submitEmailChange,
  saveNotificationPreferences,
  handleProfileTabChange,
} = useProfile();

// ── M15：blur 即时校验（DESIGN.md §4.6：blur 触发 + 提交前汇总拦截） ──
const passwordFormRef = ref<FormInstanceFunctions>();
const resetFormRef = ref<FormInstanceFunctions>();
const phoneFormRef = ref<FormInstanceFunctions>();
const emailFormRef = ref<FormInstanceFunctions>();

const requiredBlur = (message: string): FormRule[] => [{ required: true, message, trigger: 'blur' }];

const newPasswordRules: FormRule[] = [
  ...requiredBlur('请输入新密码'),
  { min: 6, message: '密码至少 6 位', trigger: 'blur' },
];
const confirmPasswordRulesOld: FormRule[] = [
  ...requiredBlur('请再次输入新密码'),
  {
    validator: (val: unknown) => !val || val === passwordForm.newPassword,
    message: '两次输入的密码不一致',
    trigger: 'blur',
  },
];
const confirmPasswordRulesReset: FormRule[] = [
  ...requiredBlur('请再次输入新密码'),
  {
    validator: (val: unknown) => !val || val === resetForm.password,
    message: '两次输入的密码不一致',
    trigger: 'blur',
  },
];
const phoneRules: FormRule[] = [
  ...requiredBlur('请输入新手机号'),
  { pattern: /^1\d{10}$/, message: '手机号格式不正确', trigger: 'blur' },
];
const emailRules: FormRule[] = [
  ...requiredBlur('请输入新邮箱'),
  { pattern: /^\S[^\s@]*@\S[^\s.]*\.\S+$/, message: '邮箱格式不正确', trigger: 'blur' },
];

async function validateForm(formRef: FormInstanceFunctions | undefined) {
  if (!formRef) return true;
  return (await formRef.validate()) === true;
}

async function confirmPasswordChange() {
  if (!(await validateForm(passwordFormRef.value))) return;
  await changePassword();
}

async function confirmResetPassword() {
  if (!(await validateForm(resetFormRef.value))) return;
  await submitResetPassword();
}

async function confirmPhoneChange() {
  if (!(await validateForm(phoneFormRef.value))) return;
  await submitPhoneChange();
}

async function confirmEmailChange() {
  if (!(await validateForm(emailFormRef.value))) return;
  await submitEmailChange();
}

// AC-22：加载失败后从页面直接重试，不必整页刷新
async function reloadProfile() {
  try {
    await loadProfile();
  } catch {
    // 错误已写入 profileError，由页面 alert 呈现
  }
}

// AC-21：改密/重置密码请求进行中不允许关闭弹窗，避免关窗后仍被登出
function handlePasswordDialogClose() {
  if (changingPassword.value) return;
  passwordDialogVisible.value = false;
}
</script>
<style scoped lang="less">
.profile-page {
  display: grid;
  grid-template-columns: minmax(14rem, 18rem) minmax(0, 1fr);
  gap: var(--td-comp-margin-m);
  // padding 由 Starter 布局层统一提供
}

.profile-card {
  background: var(--td-bg-color-container);
  border: thin solid var(--td-component-stroke);
  border-radius: var(--td-radius-medium);
  box-shadow: var(--td-shadow-1);
}

.profile-nav {
  position: sticky;
  top: var(--td-comp-margin-m);
  align-self: start;
}

.profile-main {
  min-width: 0;
}

// 资料加载失败提示跨满内容列
.profile-alert {
  margin-bottom: var(--td-comp-margin-m);
}

.profile-form {
  max-width: 46rem;
}

.profile-id-row {
  display: flex;
  gap: var(--td-comp-margin-s);
  align-items: center;
  width: 100%;
}

.bind-code-row {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  gap: 0.5rem;
  width: 100%;

  .t-button {
    min-width: 8.5rem;
    white-space: nowrap;
  }
}

// 原字号为 0.8125rem，不在令牌号段内（DESIGN.md 要求字号映射 TDesign 变量），改用 body-small
.password-forgot {
  margin-top: var(--td-comp-margin-xxs);
  // 用 --td-font-size-*（字号）而不是 --td-font-body-small：后者是 font 简写
  // （含「/行高 字体族」），放进 font-size 会让整条声明失效，字号回落到继承值。
  font-size: var(--td-font-size-body-small);
  color: var(--td-brand-color);
  cursor: pointer;
  user-select: none;

  &:hover {
    text-decoration: underline;
  }
}

// 同上：无对应令牌，统一到 body-small
.reset-single-tip {
  margin-bottom: var(--td-comp-margin-s);
  // 同上：取字号令牌，不取 font 简写
  font-size: var(--td-font-size-body-small);
  color: var(--td-text-color-secondary);
}

// 账户中心导航的显示由模板 v-if="!isDesktop" / v-else 决定，这里不再用 display 二选一（AC-24）
.profile-footer {
  display: flex;
  gap: var(--td-comp-margin-m);
  align-items: center;
  justify-content: space-between;
  margin-top: var(--td-comp-margin-l);
  padding-top: var(--td-comp-margin-m);
  color: var(--td-text-color-secondary);
  border-top: thin solid var(--td-component-stroke);
  font: var(--td-font-body-small);
}

.security-list,
.notification-list {
  display: flex;
  flex-direction: column;
  gap: var(--td-comp-margin-s);
}

.security-item,
.notification-item {
  display: flex;
  gap: var(--td-comp-margin-m);
  align-items: center;
  justify-content: space-between;
  padding: var(--td-comp-paddingTB-m) var(--td-comp-paddingLR-m);
  background: var(--td-bg-color-container);
  border: thin solid var(--td-component-stroke);
  border-radius: var(--td-radius-medium);

  p {
    margin: var(--td-comp-margin-xs) 0 0;
    color: var(--td-text-color-secondary);
    font: var(--td-font-body-small);
  }
}

.security-item__head {
  display: flex;
  flex-wrap: wrap;
  gap: var(--td-comp-margin-s);
  align-items: center;
}

@media (max-width: @screen-sm-max) {
  .profile-page {
    grid-template-columns: 1fr;
    gap: var(--td-comp-margin-s);
  }

  // 平板及以下（≤ @screen-sm-max，与布局层 padding 归零、isDesktop 判定同一档）改用顶部横向标签；
  // 切换由模板的 v-if="!isDesktop" 完成，这里只负责标签栏自身的样式
  .profile-tabs-mobile {
    display: flex;
    gap: var(--td-comp-margin-xxs);
    padding: var(--td-comp-paddingTB-xxs) var(--td-comp-paddingLR-xxs);
    background: var(--td-bg-color-container);
    border: thin solid var(--td-component-stroke);
    border-radius: var(--td-radius-medium);
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: none;

    &::-webkit-scrollbar {
      display: none;
    }

    &__item {
      flex: 1 0 auto;
      padding: var(--td-comp-paddingTB-s) var(--td-comp-paddingLR-m);
      color: var(--td-text-color-primary);
      background: transparent;
      border: none;
      border-radius: var(--td-radius-default);
      font: var(--td-font-body-medium);
      white-space: nowrap;
      cursor: pointer;
      transition:
        background 0.2s,
        color 0.2s;

      &.is-active {
        color: var(--td-brand-color);
        background: var(--td-brand-color-light);
        font-weight: 600;
      }
    }
  }

  // 表单标签顶部对齐，输入框占满整行。
  // 对齐方式由模板的 :label-align="isDesktop ? 'left' : 'top'" 切换：TDesign 在 top 对齐下
  // 不再给 label 写内联 width、给 controls 写内联 margin-left，故此处无需 !important（AC-15）
  .profile-form {
    :deep(.t-form__label) {
      padding-right: 0;
      padding-bottom: var(--td-comp-margin-xxs);
    }
  }

  .profile-id-row {
    flex-direction: row;
    align-items: stretch;
    gap: var(--td-comp-margin-xs);

    :deep(.t-input) {
      flex: 1;
      min-width: 0;
    }

    :deep(.t-button) {
      flex-shrink: 0;
      padding: 0 var(--td-comp-paddingLR-s);
    }
  }

  .profile-footer,
  .security-item,
  .notification-item {
    align-items: flex-start;
    flex-direction: column;
  }

  .profile-footer {
    .t-button {
      align-self: stretch;
    }
  }
}
</style>
