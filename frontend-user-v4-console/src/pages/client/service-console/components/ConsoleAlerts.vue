<template>
  <t-alert v-if="isExpired" theme="error" class="console-alert">
    实例已于 {{ detail.expires_at }} 到期，服务可能已中断，请立即续费。
    <template #operation>
      <t-button variant="text" theme="primary" @click="openRenewDialog">立即续费</t-button>
    </template>
  </t-alert>

  <t-alert v-else-if="isExpiringSoon" theme="warning" class="console-alert">
    实例将于 {{ detail.expires_at }} 到期，建议提前续费避免服务中断。
    <template #operation>
      <t-button variant="text" theme="primary" @click="openRenewDialog">立即续费</t-button>
    </template>
  </t-alert>

  <t-alert v-if="!canManageConsole" theme="info" class="console-alert">
    当前实例未接入完整控制能力，页面将以只读模式展示基础信息。
  </t-alert>
</template>
<script setup lang="ts">
import { computed } from 'vue';

import { useServiceConsoleContext } from './context';

const { detail, canManageConsole, openRenewDialog } = useServiceConsoleContext();

const expiresAtMs = computed(() => {
  if (!detail.value.expires_at) return Number.NaN;
  return new Date(String(detail.value.expires_at).replace(/-/g, '/')).getTime();
});

// SC-28：已过期与「7 天内到期」必须分开表达，否则过期实例仍显示"将于…到期"
const isExpired = computed(() => {
  const expiresAt = expiresAtMs.value;
  return Number.isFinite(expiresAt) && expiresAt <= Date.now();
});

const isExpiringSoon = computed(() => {
  const expiresAt = expiresAtMs.value;
  if (!Number.isFinite(expiresAt)) return false;
  const remaining = expiresAt - Date.now();
  return remaining > 0 && remaining <= 7 * 24 * 60 * 60 * 1000;
});
</script>
