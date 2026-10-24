<template>
  <div class="user-center-page">
    <t-card :bordered="false" title="个人信息" class="profile-card">
      <t-loading :loading="loading" show-overlay>
        <t-form
          ref="formRef"
          :data="formData"
          :rules="formRules"
          label-width="100px"
          :disabled="!editing"
          @submit="handleSave"
        >
          <t-form-item label="用户名" name="username">
            <t-input v-model="formData.username" disabled />
            <template #help>用户名不可修改</template>
          </t-form-item>
          <t-form-item label="昵称" name="nickname">
            <t-input v-model="formData.nickname" placeholder="请输入昵称" :maxlength="30" />
          </t-form-item>
          <t-form-item label="邮箱" name="email">
            <t-input v-model="formData.email" disabled />
          </t-form-item>
          <t-form-item label="角色" name="role">
            <t-tag theme="primary" variant="light">{{ formData.role || '-' }}</t-tag>
          </t-form-item>
          <t-form-item v-if="!editing">
            <t-space>
              <t-button theme="primary" @click="startEdit">编辑资料</t-button>
            </t-space>
          </t-form-item>
          <t-form-item v-if="editing">
            <t-space>
              <t-button theme="primary" type="submit" :loading="saving">保存</t-button>
              <t-button theme="default" @click="cancelEdit">取消</t-button>
            </t-space>
          </t-form-item>
        </t-form>
      </t-loading>
    </t-card>

    <t-card :bordered="false" title="双重验证" class="profile-card mfa-card">
      <t-loading :loading="mfaLoading" show-overlay>
        <div class="mfa-status">
          <span class="mfa-status-label">当前状态</span>
          <t-tag :theme="mfaEnabled ? 'success' : 'warning'" variant="light">
            {{ mfaEnabled ? '已启用' : '未启用' }}
          </t-tag>
        </div>
        <p class="mfa-tip">
          启用后登录需要输入身份验证器（如 Google Authenticator、1Password）中的 6 位动态码；
          这是防止密码泄漏后账号被直接接管的关键一道闸。
        </p>

        <!-- 绑定第一步：展示密钥与 otpauth 链接，由管理员自行录入认证器 -->
        <template v-if="!mfaEnabled && mfaSetupData">
          <t-alert
            theme="info"
            :message="`请把下面的密钥手动添加到身份验证器（有效期 ${mfaSetupData.expires_in / 60} 分钟）`"
          />
          <t-form-item label="密钥" class="mfa-secret-item">
            <t-input :value="mfaSetupData.secret" readonly />
          </t-form-item>
          <t-form-item label="otpauth" class="mfa-secret-item">
            <t-input :value="mfaSetupData.otpauth_uri" readonly />
          </t-form-item>
          <t-form ref="mfaEnableFormRef" :data="mfaEnableForm" :rules="mfaEnableRules" label-width="100px">
            <t-form-item label="动态码" name="code">
              <t-input v-model="mfaEnableForm.code" placeholder="输入认证器生成的 6 位动态码" :maxlength="6" />
            </t-form-item>
            <t-form-item>
              <t-space>
                <t-button theme="primary" :loading="mfaSubmitting" @click="handleEnableMfa">确认启用</t-button>
                <t-button theme="default" :disabled="mfaSubmitting" @click="cancelMfaSetup">取消</t-button>
              </t-space>
            </t-form-item>
          </t-form>
        </template>

        <!-- 绑定成功：恢复码只显示这一次，关闭后无法再次查看 -->
        <template v-if="recoveryCodes.length > 0">
          <t-alert
            theme="warning"
            message="恢复码仅显示这一次，请立即保存到安全位置。手机丢失时可用其中任意一条登录。"
          />
          <div class="mfa-recovery-codes">
            <code v-for="code in recoveryCodes" :key="code">{{ code }}</code>
          </div>
          <t-button theme="default" @click="recoveryCodes = []">我已保存</t-button>
        </template>

        <!-- 未启用且未开始绑定 -->
        <t-button
          v-if="!mfaEnabled && !mfaSetupData && recoveryCodes.length === 0"
          theme="primary"
          :loading="mfaSubmitting"
          @click="handleSetupMfa"
        >
          启用双重验证
        </t-button>

        <!-- 已启用：解绑需要密码 + 动态码两因子 -->
        <template v-if="mfaEnabled">
          <t-form ref="mfaDisableFormRef" :data="mfaDisableForm" :rules="mfaDisableRules" label-width="100px">
            <t-form-item label="当前密码" name="password">
              <t-input v-model="mfaDisableForm.password" type="password" placeholder="请输入当前登录密码" />
            </t-form-item>
            <t-form-item label="动态码" name="code">
              <t-input v-model="mfaDisableForm.code" placeholder="动态码或一条恢复码" :maxlength="32" />
            </t-form-item>
            <t-form-item>
              <t-button theme="danger" variant="outline" :loading="mfaSubmitting" @click="handleDisableMfa">
                解绑双重验证
              </t-button>
            </t-form-item>
          </t-form>
        </template>
      </t-loading>
    </t-card>
  </div>
</template>
<script setup lang="ts">
import type { FormInstanceFunctions, FormRule } from 'tdesign-vue-next';
import { MessagePlugin } from 'tdesign-vue-next/es/message/plugin';
import { onMounted, reactive, ref } from 'vue';

import { adminAuthApi } from '@/api/auth';
import { useUserStore } from '@/store';
import { errorMessage } from '@/utils/userMessage';

interface MfaSetupResult {
  secret: string;
  otpauth_uri: string;
  issuer: string;
  expires_in: number;
}

const userStore = useUserStore();

const formRef = ref<FormInstanceFunctions>();
const loading = ref(false);
const saving = ref(false);
const editing = ref(false);

const formData = reactive({
  username: '',
  nickname: '',
  email: '',
  role: '',
});

const formRules: Record<string, FormRule[]> = {
  nickname: [
    { required: true, message: '请输入昵称', type: 'error' },
    { max: 30, message: '昵称最多 30 个字符', type: 'warning' },
  ],
};

const mfaLoading = ref(false);
const mfaSubmitting = ref(false);
const mfaEnabled = ref(false);
const mfaSetupData = ref<MfaSetupResult | null>(null);
const recoveryCodes = ref<string[]>([]);

const mfaEnableFormRef = ref<FormInstanceFunctions>();
const mfaEnableForm = reactive({ code: '' });
const mfaEnableRules: Record<string, FormRule[]> = {
  code: [{ required: true, message: '请输入动态验证码', trigger: 'blur' }],
};

const mfaDisableFormRef = ref<FormInstanceFunctions>();
const mfaDisableForm = reactive({ password: '', code: '' });
const mfaDisableRules: Record<string, FormRule[]> = {
  password: [{ required: true, message: '请输入当前密码', trigger: 'blur' }],
  code: [{ required: true, message: '请输入动态验证码', trigger: 'blur' }],
};

const fetchProfile = async () => {
  loading.value = true;
  try {
    const info = await userStore.getUserInfo();
    formData.username = ((info as Record<string, unknown>).username as string) || '';
    formData.nickname = ((info as Record<string, unknown>).nickname as string) || '';
    formData.email = ((info as Record<string, unknown>).email as string) || '';
    formData.role = ((info as Record<string, unknown>).role as string) || '';
    // 后端 /auth/info 返回 mfa_enabled，是双因素状态的唯一真源，
    // 不额外加一个只为展示的接口。
    mfaEnabled.value = Boolean((info as Record<string, unknown>).mfa_enabled);
  } catch {
    // getUserInfo 内部已处理错误
  } finally {
    loading.value = false;
  }
};

onMounted(() => {
  fetchProfile();
});

const startEdit = () => {
  editing.value = true;
};

const cancelEdit = () => {
  editing.value = false;
  fetchProfile();
};

const handleSave = async () => {
  const result = await formRef.value?.validate?.();
  if (result !== true) return;

  saving.value = true;
  try {
    await adminAuthApi.updateProfile({ nickname: formData.nickname });
    MessagePlugin.success('资料已更新');
    editing.value = false;
    // 同步更新 store 中的用户信息
    userStore.userInfo = {
      ...userStore.userInfo,
      name: formData.nickname || userStore.userInfo.name,
      nickname: formData.nickname,
    };
  } catch (error) {
    MessagePlugin.error(errorMessage(error, '保存失败'));
  } finally {
    saving.value = false;
  }
};

const handleSetupMfa = async () => {
  mfaSubmitting.value = true;
  try {
    const res = (await adminAuthApi.mfaSetup()) as MfaSetupResult;
    mfaSetupData.value = res;
    mfaEnableForm.code = '';
    MessagePlugin.success('已生成绑定密钥，请录入身份验证器');
  } catch (error) {
    MessagePlugin.error(errorMessage(error, '生成绑定密钥失败'));
  } finally {
    mfaSubmitting.value = false;
  }
};

const cancelMfaSetup = () => {
  mfaSetupData.value = null;
  mfaEnableForm.code = '';
};

const handleEnableMfa = async () => {
  const result = await mfaEnableFormRef.value?.validate?.();
  if (result !== true) return;

  mfaSubmitting.value = true;
  try {
    const res = (await adminAuthApi.mfaEnable({ code: mfaEnableForm.code.trim() })) as {
      recovery_codes?: string[];
    };
    mfaEnabled.value = true;
    mfaSetupData.value = null;
    mfaEnableForm.code = '';
    recoveryCodes.value = res.recovery_codes || [];
    MessagePlugin.success('双重验证已启用');
  } catch (error) {
    MessagePlugin.error(errorMessage(error, '启用失败'));
  } finally {
    mfaSubmitting.value = false;
  }
};

const handleDisableMfa = async () => {
  const result = await mfaDisableFormRef.value?.validate?.();
  if (result !== true) return;

  mfaSubmitting.value = true;
  try {
    await adminAuthApi.mfaDisable({
      password: mfaDisableForm.password,
      code: mfaDisableForm.code.trim(),
    });
    mfaEnabled.value = false;
    mfaDisableForm.password = '';
    mfaDisableForm.code = '';
    MessagePlugin.success('双重验证已解绑');
  } catch (error) {
    MessagePlugin.error(errorMessage(error, '解绑失败'));
  } finally {
    mfaSubmitting.value = false;
  }
};
</script>
<style lang="less" scoped>
.profile-card {
  max-width: 600px;
}

.mfa-card {
  margin-top: 16px;
}

.mfa-status {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 8px;
}

.mfa-status-label {
  font-size: 14px;
  color: var(--td-text-color-secondary, #5b6b82);
}

.mfa-tip {
  margin-bottom: 16px;
  font-size: 13px;
  line-height: 1.7;
  color: var(--td-text-color-secondary, #5b6b82);
}

.mfa-secret-item {
  margin-top: 12px;
}

.mfa-recovery-codes {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
  gap: 8px;
  margin: 12px 0 16px;

  code {
    padding: 8px 10px;
    font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    font-size: 13px;
    letter-spacing: 0.06em;
    background: var(--td-bg-color-secondarycontainer, #f3f5f9);
    border-radius: var(--td-radius-default, 6px);
    text-align: center;
  }
}
</style>
