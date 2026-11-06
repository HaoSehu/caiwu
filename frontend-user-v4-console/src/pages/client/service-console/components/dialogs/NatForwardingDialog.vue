<template>
  <t-dialog v-model:visible="natVisible" header="新建端口转发" width="min(34rem, calc(100vw - 2rem))" destroy-on-close>
    <t-form :label-align="isMobile ? 'top' : 'right'" :label-width="isMobile ? 0 : 96" :disabled="natState.submitting">
      <t-form-item label="名称" required-mark>
        <t-input v-model="natForm.name" placeholder="例如：Web 服务" :maxlength="255" />
      </t-form-item>
      <t-form-item label="公网端口">
        <t-input v-model="natForm.ext_port" placeholder="留空由上游分配" :maxlength="5" />
      </t-form-item>
      <t-form-item label="内网端口" required-mark>
        <t-input v-model="natForm.int_port" placeholder="例如：80" :maxlength="5" />
      </t-form-item>
      <t-form-item label="协议" required-mark>
        <t-select v-model="natForm.protocol" placeholder="请选择协议">
          <t-option
            v-for="protocol in natState.protocols"
            :key="String(protocol.value)"
            :value="String(protocol.value)"
            :label="protocol.label"
          />
        </t-select>
      </t-form-item>
    </t-form>
    <template #footer>
      <t-button variant="outline" :disabled="natState.submitting" @click="natVisible = false">取消</t-button>
      <t-button
        theme="primary"
        :loading="natState.submitting"
        :disabled="natState.submitting"
        @click="submitNatForwarding"
        >确定</t-button
      >
    </template>
  </t-dialog>
</template>
<script setup lang="ts">
import { useDeviceLayout } from '@/composables/useDeviceLayout';

import { useServiceConsoleContext } from '../context';

const { isMobile } = useDeviceLayout();
const { natVisible, natForm, natState, submitNatForwarding } = useServiceConsoleContext();
</script>
