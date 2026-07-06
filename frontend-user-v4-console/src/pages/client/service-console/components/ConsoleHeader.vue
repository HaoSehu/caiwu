<template>
  <t-card class="console-header-card" :bordered="false">
    <div class="console-header-main">
      <div class="console-title-line">
        <h1>{{ detail.name || `服务 #${serviceId}` }}</h1>
        <t-button theme="primary" variant="text" @click="openNameDialog">修改名称</t-button>
        <t-tag :theme="resolveTdesignStatusTheme(detail)" variant="light">{{ resolveServiceStatusLabel(detail.status) }}</t-tag>
        <t-tag v-if="detail.product?.type_label" variant="light">{{ detail.product.type_label }}</t-tag>
      </div>

      <div class="console-meta-line">
        <span>实例 ID：{{ detail.id || '--' }}</span>
        <span>地址：{{ serviceRegion }}</span>
        <span>{{ primaryConnectionLabel }}：{{ primaryConnectionText }}</span>
      </div>

      <div class="console-remark-line">
        <span>备注：</span>
        <strong :class="{ 'is-empty': !detail.remark }">{{ detail.remark || '点击添加备注' }}</strong>
        <t-button
          shape="square"
          variant="text"
          size="small"
          :aria-label="detail.remark ? '编辑备注' : '添加备注'"
          @click="openRemarkDialog"
        >
          <template #icon><EditIcon /></template>
        </t-button>
      </div>
    </div>

    <div class="console-header-actions">
      <t-button v-if="isInstanceRunning" variant="outline" :disabled="!detail.actions?.power || actionLoading" @click="handlePowerAction('off')">
        <template #icon><PauseCircleFilledIcon /></template>
        关机
      </t-button>
      <t-button v-else theme="primary" :disabled="!detail.actions?.power || actionLoading" @click="handlePowerAction('on')">
        <template #icon><PlayCircleFilledIcon /></template>
        开机
      </t-button>
      <t-button variant="outline" :disabled="!detail.actions?.power || actionLoading" @click="handlePowerAction('reboot')">
        <template #icon><RotateIcon /></template>
        重启
      </t-button>
      <t-button variant="outline" :loading="statusSyncing" :disabled="!canSyncStatus || actionLoading" @click="handleSyncStatus">
        <template #icon><RefreshIcon /></template>
        状态同步
      </t-button>
      <t-dropdown trigger="click" :options="moreOptions" @click="handleMoreClick">
        <t-button variant="outline">
          <template #icon><EllipsisIcon /></template>
          更多
        </t-button>
      </t-dropdown>
    </div>
  </t-card>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { EditIcon, EllipsisIcon, PauseCircleFilledIcon, PlayCircleFilledIcon, RefreshIcon, RotateIcon } from 'tdesign-icons-vue-next';

import { useServiceConsoleContext } from './context';

const {
  detail,
  serviceId,
  statusSyncing,
  actionLoading,
  canSyncStatus,
  serviceRegion,
  primaryConnectionLabel,
  primaryConnectionText,
  instanceStatusTheme,
  resolveServiceStatusLabel,
  resolveTdesignStatusTheme,
  openNameDialog,
  openRemarkDialog,
  handleSyncStatus,
  handlePowerAction,
  openPasswordDialog,
  openReinstallDialog,
} = useServiceConsoleContext();

const moreOptions = computed(() => [
  { content: '重置密码', value: 'password', disabled: !detail.value.actions?.password_reset },
  { content: '重装系统', value: 'reinstall', disabled: !detail.value.actions?.reinstall },
  { content: '强制关机', value: 'hard_off', disabled: !detail.value.actions?.power },
  { content: '强制重启', value: 'hard_reboot', disabled: !detail.value.actions?.power },
]);

const isInstanceRunning = computed(() => instanceStatusTheme.value === 'success');

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
