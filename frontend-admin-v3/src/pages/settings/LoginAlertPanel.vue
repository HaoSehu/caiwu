<template>
  <t-card :bordered="false" :loading="loading">
    <template #title>登录成功提醒</template>
    <template #subtitle>配置新用户登录提醒的默认开关与提醒发送通道。</template>

    <t-form :data="form" label-align="top" class="settings-form" :disabled="!canManage">
      <div class="settings-field-grid">
        <article class="field-card field-card--switch">
          <div class="field-info">
            <strong>新用户默认开启登录提醒</strong>
            <p>新注册用户的登录成功提醒开关默认值；既有用户的开关不受影响，用户可在个人设置中自行调整。</p>
          </div>
          <div class="field-control">
            <t-switch v-model="form.defaultEnabled" aria-label="新用户默认开启登录提醒" />
          </div>
        </article>

        <article class="field-card field-card--wide">
          <div class="field-info">
            <strong>提醒通道</strong>
            <p>用户开关开启时，仅在所选通道上发送登录提醒；至少选择一个通道。</p>
          </div>
          <div class="field-control">
            <t-checkbox-group v-model="form.channels" :disabled="!canManage">
              <t-checkbox v-for="option in channelOptions" :key="option.value" :value="option.value">
                {{ option.label }}
              </t-checkbox>
            </t-checkbox-group>
          </div>
        </article>
      </div>
    </t-form>

    <div class="settings-bottom-actions">
      <t-button theme="primary" :loading="saving" :disabled="!canManage" @click="save">
        <template #icon><check-icon /></template>
        保存设置
      </t-button>
    </div>
  </t-card>
</template>

<script setup lang="ts">
import { CheckIcon } from 'tdesign-icons-vue-next';
import { MessagePlugin } from 'tdesign-vue-next';
import { computed, onMounted, reactive, ref } from 'vue';

import type { LoginAlertChannel } from '@/api/admin/settings';
import { loginAlertApi } from '@/api/admin/settings';
import { AdminPermissions } from '@/constants/permissions';
import { hasAdminPermission } from '@/utils/permission';
import { errorMessage } from '@/utils/userMessage';

const channelOptions: Array<{ label: string; value: LoginAlertChannel }> = [
  { label: '邮件', value: 'mail' },
  { label: '站内信', value: 'inbox' },
];

const ALL_CHANNELS: LoginAlertChannel[] = ['mail', 'inbox'];

const loading = ref(false);
const saving = ref(false);

const canManage = computed(() => hasAdminPermission(AdminPermissions.SETTINGS_MANAGE));

const form = reactive({
  defaultEnabled: true,
  channels: [...ALL_CHANNELS] as LoginAlertChannel[],
});

function normalizeChannels(value: unknown): LoginAlertChannel[] {
  if (!Array.isArray(value)) return [...ALL_CHANNELS];
  const parsed = value.filter(
    (item): item is LoginAlertChannel => item === 'mail' || item === 'inbox',
  );
  return parsed.length > 0 ? parsed : [...ALL_CHANNELS];
}

async function load() {
  loading.value = true;
  try {
    const config = await loginAlertApi.get();
    form.defaultEnabled = config.defaultEnabled;
    form.channels = normalizeChannels(config.channels);
  } catch (error) {
    MessagePlugin.error(errorMessage(error, '加载登录提醒设置失败'));
  } finally {
    loading.value = false;
  }
}

async function save() {
  if (!canManage.value) {
    MessagePlugin.warning('当前账号无系统配置管理权限');
    return;
  }
  if (form.channels.length === 0) {
    MessagePlugin.warning('请至少选择一个提醒通道');
    return;
  }

  saving.value = true;
  try {
    await loginAlertApi.save({
      defaultEnabled: form.defaultEnabled,
      channels: ALL_CHANNELS.filter((channel) => form.channels.includes(channel)),
    });
    MessagePlugin.success('登录提醒设置已保存');
  } catch (error) {
    MessagePlugin.error(errorMessage(error, '保存登录提醒设置失败'));
  } finally {
    saving.value = false;
  }
}

onMounted(() => {
  void load();
});
</script>
