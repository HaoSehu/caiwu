<template>
  <t-card class="console-header-card" :bordered="false">
    <div class="console-header-main">
      <div class="console-title-line">
        <h1>{{ detail.name || `服务 #${serviceId}` }}</h1>
        <t-button theme="primary" variant="text" @click="openNameDialog">编辑名称</t-button>
        <t-tag :theme="resolveTdesignStatusTheme(detail)" variant="light">{{
          resolveServiceStatusLabel(detail.status)
        }}</t-tag>
        <t-tag v-if="detail.product?.type_label" variant="light">{{ detail.product.type_label }}</t-tag>
      </div>

      <div class="console-meta-grid">
        <span class="meta-item meta-id">实例 ID：{{ detail.id || '--' }}</span>
        <span class="meta-item meta-region">地址：{{ serviceRegion }}</span>
        <span class="meta-item meta-ip">{{ primaryConnectionLabel }}：{{ primaryConnectionValues[0] || '--' }}</span>
        <div class="console-auto-renew-line">
          <span>自动续费</span>
          <t-switch
            :value="Number(detail.auto_renew) === 1"
            :loading="autoRenewLoading"
            @change="handleToggleAutoRenew"
          />
        </div>
        <div class="console-remark-line">
          <span>备注：</span>
          <strong :class="{ 'is-empty': !detail.remark }">{{ detail.remark || '点击设置备注' }}</strong>
          <t-button
            shape="square"
            variant="text"
            size="small"
            :aria-label="detail.remark ? '编辑备注' : '设置备注'"
            @click="openRemarkDialog"
          >
            <template #icon><edit-icon /></template>
          </t-button>
        </div>
      </div>
    </div>

    <div class="console-header-actions">
      <t-button
        v-if="powerPhase === 'running'"
        variant="outline"
        :disabled="!detail.actions?.power || powerLoading"
        @click="handlePowerAction('off')"
      >
        <template #icon><pause-circle-filled-icon /></template>
        关机
      </t-button>
      <t-button
        v-else-if="powerPhase === 'stopped'"
        theme="primary"
        :disabled="!detail.actions?.power || powerLoading"
        @click="handlePowerAction('on')"
      >
        <template #icon><play-circle-filled-icon /></template>
        开机
      </t-button>
      <!-- 中间态（开机中/关机中/重启中/失败）没有可下发的对立动作，显示真实进行中文案并禁用，
           否则会渲染成「开机」这类与当前动作方向相反的按钮。 -->
      <t-button v-else variant="outline" :disabled="true" :loading="powerLoading">
        <template #icon><rotate-icon /></template>
        {{ instanceStatusText }}
      </t-button>
      <t-button
        variant="outline"
        :disabled="powerPhase === 'transitional' || !detail.actions?.power || powerLoading"
        @click="handlePowerAction('reboot')"
      >
        <template #icon><rotate-icon /></template>
        重启
      </t-button>
      <t-button
        variant="outline"
        :loading="statusSyncing"
        :disabled="!canSyncStatus || powerLoading"
        @click="handleSyncStatus"
      >
        <template #icon><refresh-icon /></template>
        状态同步
      </t-button>
      <t-dropdown trigger="click" :options="moreOptions" @click="handleMoreClick">
        <t-button variant="outline">
          <template #icon><ellipsis-icon /></template>
          更多
        </t-button>
      </t-dropdown>
    </div>
  </t-card>
</template>
<script setup lang="ts">
import { SERVICE_STATUS } from '@caiwu/shared/statusConfig';
import {
  EditIcon,
  EllipsisIcon,
  PauseCircleFilledIcon,
  PlayCircleFilledIcon,
  RefreshIcon,
  RotateIcon,
} from 'tdesign-icons-vue-next';
import { computed, shallowRef, watch } from 'vue';

import { useServiceConsoleContext } from './context';

const {
  detail,
  serviceId,
  statusSyncing,
  powerLoading,
  autoRenewLoading,
  canSyncStatus,
  serviceRegion,
  primaryConnectionLabel,
  primaryConnectionValues,
  instanceStatusTheme,
  instanceStatusText,
  resolveServiceStatusLabel,
  resolveTdesignStatusTheme,
  openNameDialog,
  openRemarkDialog,
  handleSyncStatus,
  handlePowerAction,
  handleToggleAutoRenew,
  openPasswordDialog,
  openReinstallDialog,
} = useServiceConsoleContext();

// 电源动作必须按三态渲染：运行中→关机、已停止→开机、其余一律只读展示。
// 旧实现只用 instanceStatusTheme 二分：theme 为 'default' 就当成已停止，但那是文案匹配的兜底分支，
// 后端给 PENDING 的 tone 是 info、给 TERMINATED 的 tone 是 muted，两者经 resolveTdesignStatusTheme
// 折算都落到 'default' —— 「开通中」「已删除」的实例因此被渲染成可点的「开机」按钮。
// 现在先看真实状态码：只有 ACTIVE 才可能下发开机，其余状态（含 SUSPENDED/ABNORMAL）保持只读。
const powerPhase = computed<'running' | 'stopped' | 'transitional'>(() => {
  if (Number(detail.value.status) !== SERVICE_STATUS.ACTIVE) return 'transitional';
  const theme = instanceStatusTheme.value;
  if (theme === 'success') return 'running';
  if (theme === 'default') return 'stopped';
  return 'transitional';
});

// 下拉项只在「可用性真的变化」时重建：detail 会随状态同步频繁换引用，
// 派生式 computed 等于每轮同步都喂给 t-dropdown 一个新 options 数组。
const buildMoreOptions = (disabled: { password: boolean; reinstall: boolean; power: boolean }) => [
  { content: '重置密码', value: 'password', disabled: disabled.password },
  { content: '重装系统', value: 'reinstall', disabled: disabled.reinstall },
  // 中间态/已关机时「强制关机」「强制重启」无意义，与主电源按钮保持同一可用口径
  { content: '强制关机', value: 'hard_off', disabled: disabled.power },
  { content: '强制重启', value: 'hard_reboot', disabled: disabled.power },
];

const moreOptions = shallowRef(buildMoreOptions({ password: false, reinstall: false, power: false }));

watch(
  () => [
    Boolean(detail.value.actions?.password_reset),
    Boolean(detail.value.actions?.reinstall),
    powerPhase.value === 'running',
  ],
  ([passwordReset, reinstall, canForcePower]) => {
    moreOptions.value = buildMoreOptions({
      password: !passwordReset,
      reinstall: !reinstall,
      power: !canForcePower,
    });
  },
);

function handleMoreCommand(command: string) {
  if (command === 'hard_off' || command === 'hard_reboot') {
    handlePowerAction(command);
    return;
  }

  if (command === 'password') {
    openPasswordDialog();
    return;
  }

  if (command === 'reinstall') {
    void openReinstallDialog();
  }
}

function handleMoreClick(payload: { value: string | number }) {
  handleMoreCommand(String(payload.value));
}
</script>
