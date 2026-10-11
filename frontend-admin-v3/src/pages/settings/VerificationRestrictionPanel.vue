<template>
  <t-card :bordered="false" :loading="loading">
    <template #title>未实名自动限制</template>
    <template #subtitle>开启后，未完成实名认证的用户在所选业务范围内将被自动拦截。</template>

    <t-form :data="form" label-align="top" class="settings-form" :disabled="!canManage">
      <div class="settings-field-grid">
        <article class="field-card field-card--switch">
          <div class="field-info">
            <strong>启用未实名拦截</strong>
            <p>开启后未实名用户在所选范围内自动拦截；关闭时所有业务对未实名用户放行。</p>
          </div>
          <div class="field-control">
            <t-switch v-model="form.enabled" aria-label="启用未实名拦截" />
          </div>
        </article>

        <article class="field-card">
          <div class="field-info">
            <strong>注册宽限天数</strong>
            <p>用户注册后该天数内不拦截，超过宽限期后仍未实名则按所选范围拦截。0 表示注册即拦截。</p>
          </div>
          <div class="field-control">
            <t-input-number
              v-model="form.graceDays"
              theme="normal"
              :min="0"
              :max="365"
              :disabled="!canManage"
              aria-label="注册宽限天数"
              placeholder="请输入注册宽限天数"
            />
          </div>
        </article>

        <article class="field-card field-card--wide">
          <div class="field-info">
            <strong>拦截范围</strong>
            <p>勾选需要对未实名用户自动拦截的业务范围，至少勾选一项。</p>
          </div>
          <div class="field-control">
            <t-checkbox-group v-model="form.scopes" :disabled="!canManage">
              <t-checkbox v-for="option in scopeOptions" :key="option.value" :value="option.value">
                {{ option.label }}
                <span v-if="option.help" class="scope-help">（{{ option.help }}）</span>
              </t-checkbox>
            </t-checkbox-group>
          </div>
        </article>

        <article class="field-card field-card--wide">
          <div class="field-info">
            <strong>豁免会员等级</strong>
            <p>所选会员等级的用户不受未实名拦截影响，留空表示不豁免任何等级。</p>
          </div>
          <div class="field-control">
            <t-select
              v-model="form.exemptMemberLevelIds"
              multiple
              clearable
              filterable
              :disabled="!canManage"
              :loading="memberLevelsLoading"
              aria-label="豁免会员等级"
              placeholder="请选择豁免会员等级"
            >
              <t-option
                v-for="level in memberLevelOptions"
                :key="String(level.id)"
                :label="String(level.name || `等级 #${level.id}`)"
                :value="level.id"
              />
            </t-select>
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

import type { VerificationRestrictionScope } from '@/api/admin/settings';
import { verificationRestrictionApi } from '@/api/admin/settings';
import { memberLevelsApi } from '@/api/admin/memberLevel';
import type { MemberLevelRecord } from '@/api/admin/types';
import { AdminPermissions } from '@/constants/permissions';
import { hasAdminPermission } from '@/utils/permission';
import { errorMessage } from '@/utils/userMessage';

interface ScopeOption {
  label: string;
  value: VerificationRestrictionScope;
  help?: string;
}

const ALL_SCOPES: VerificationRestrictionScope[] = [
  'purchase',
  'recharge',
  'renewal',
  'withdrawal',
  'service_transfer_receive',
];

const scopeOptions: ScopeOption[] = [
  { label: '新购', value: 'purchase' },
  { label: '充值', value: 'recharge' },
  { label: '续费', value: 'renewal', help: '当前版本续费拦截仅在用户主动续费时生效' },
  { label: '提现', value: 'withdrawal' },
  { label: '过户接收', value: 'service_transfer_receive' },
];

const loading = ref(false);
const saving = ref(false);
const memberLevelsLoading = ref(false);
const memberLevelOptions = ref<MemberLevelRecord[]>([]);

const canManage = computed(() => hasAdminPermission(AdminPermissions.SETTINGS_MANAGE));

const form = reactive({
  enabled: false,
  graceDays: 0,
  scopes: [...ALL_SCOPES] as VerificationRestrictionScope[],
  exemptMemberLevelIds: [] as Array<number | string>,
});

function normalizeScopes(value: unknown): VerificationRestrictionScope[] {
  const record = value && typeof value === 'object' ? (value as Record<string, unknown>) : {};
  return ALL_SCOPES.filter((scope) => record[scope] === true);
}

function normalizeExemptIds(value: unknown): Array<number | string> {
  if (!Array.isArray(value)) return [];
  return value
    .map((item) => (typeof item === 'number' ? item : Number(item)))
    .filter((item) => Number.isInteger(item) && item > 0);
}

async function load() {
  loading.value = true;
  try {
    const config = await verificationRestrictionApi.get();
    form.enabled = Boolean(config.enabled);
    form.graceDays = Number(config.grace_days) || 0;
    form.scopes = normalizeScopes(config.scope);
    form.exemptMemberLevelIds = normalizeExemptIds(config.exempt_member_level_ids);
  } catch (error) {
    MessagePlugin.error(errorMessage(error, '加载实名限制配置失败'));
  } finally {
    loading.value = false;
  }
}

async function loadMemberLevels() {
  memberLevelsLoading.value = true;
  try {
    memberLevelOptions.value = await memberLevelsApi.list();
  } catch (error) {
    MessagePlugin.error(errorMessage(error, '加载会员等级失败'));
  } finally {
    memberLevelsLoading.value = false;
  }
}

async function save() {
  if (!canManage.value) {
    MessagePlugin.warning('当前账号无系统配置管理权限');
    return;
  }
  if (form.scopes.length === 0) {
    MessagePlugin.warning('请至少选择一个拦截范围');
    return;
  }

  saving.value = true;
  try {
    const scope = ALL_SCOPES.reduce<Record<VerificationRestrictionScope, boolean>>(
      (result, scope) => {
        result[scope] = form.scopes.includes(scope);
        return result;
      },
      {} as Record<VerificationRestrictionScope, boolean>,
    );
    await verificationRestrictionApi.update({
      enabled: form.enabled,
      grace_days: Math.max(0, Math.min(365, Math.round(Number(form.graceDays) || 0))),
      exempt_member_level_ids: form.exemptMemberLevelIds.map((id) => Number(id)),
      scope,
    });
    MessagePlugin.success('实名限制配置已保存');
  } catch (error) {
    MessagePlugin.error(errorMessage(error, '保存实名限制配置失败'));
  } finally {
    saving.value = false;
  }
}

onMounted(() => {
  void load();
  void loadMemberLevels();
});
</script>

<style scoped>
.scope-help {
  color: var(--td-text-color-placeholder);
  font-size: var(--td-font-size-size-1, 12px);
}
</style>
