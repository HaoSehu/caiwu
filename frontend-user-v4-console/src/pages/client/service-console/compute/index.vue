<template>
  <section class="service-console service-console--compute">
    <console-breadcrumb />

    <data-state
      :loading="detailLoading"
      :error="Boolean(detailError)"
      :error-text="detailError"
      :empty="!detailLoading && !detailError && !Number(detail.id || 0)"
      description="未找到该实例的控制台信息"
      @retry="handleRetry"
    >
      <console-header />
      <console-alerts />
      <console-workbench :nav-items="consoleNavItems" />
    </data-state>

    <console-dialogs />
  </section>
</template>
<script setup lang="ts">
import DataState from '@caiwu/shared/user-v3/components/DataState.vue';
import { computed, watch } from 'vue';
import { useRouter } from 'vue-router';

import { isNatConsole } from '@/domains/services/console/useConsoleCore';
import { useServiceConsole } from '@/domains/services/useServiceConsole';

import ConsoleAlerts from '../components/ConsoleAlerts.vue';
import ConsoleBreadcrumb from '../components/ConsoleBreadcrumb.vue';
import ConsoleDialogs from '../components/ConsoleDialogs.vue';
import ConsoleHeader from '../components/ConsoleHeader.vue';
import ConsoleWorkbench from '../components/ConsoleWorkbench.vue';
import { provideServiceConsoleContext } from '../components/context';
import { resolveConsoleNavItems } from '../components/registry';

const router = useRouter();
const serviceConsole = useServiceConsole();
provideServiceConsoleContext(serviceConsole);

const { detail, detailError, detailLoading, availableTabs, bootstrap } = serviceConsole;
// 标签集合随详情动态变化（actions.vnc=false 时隐藏 VNC），不能再用构建期常量
const consoleNavItems = computed(() => resolveConsoleNavItems(availableTabs.value));

function handleRetry() {
  void bootstrap();
}

watch(
  () => Number(detail.value.id || 0),
  (id) => {
    if (id > 0 && isNatConsole(detail.value)) {
      void router.replace({ name: 'ClientNatConsole', params: { id: String(id) } });
    }
  },
);
</script>
<style src="../components/styles.less" lang="less"></style>
