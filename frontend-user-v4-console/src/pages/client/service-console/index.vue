<template>
  <section class="service-console">
    <ConsoleBreadcrumb />

    <LoadingState :loading="detailLoading" text="正在加载实例控制台">
      <ConsoleHeader />
      <ConsoleAlerts />

      <div class="console-workbench">
        <ConsoleSidebar v-model:active-tab="activeTab" :items="consoleNavItems" />
        <main class="console-content">
          <component :is="activeTabComponent" />
        </main>
      </div>
    </LoadingState>

    <ConsoleDialogs />
  </section>
</template>

<script setup lang="ts">
import { computed } from 'vue';

import { useServiceConsole } from '@/domains/services/useServiceConsole';
import LoadingState from '@shared/user-v3/components/LoadingState.vue';
import ConsoleAlerts from './components/ConsoleAlerts.vue';
import ConsoleBreadcrumb from './components/ConsoleBreadcrumb.vue';
import ConsoleDialogs from './components/ConsoleDialogs.vue';
import ConsoleHeader from './components/ConsoleHeader.vue';
import ConsoleSidebar from './components/ConsoleSidebar.vue';
import { provideServiceConsoleContext } from './components/context';
import { resolveConsoleNavItems, resolveConsoleTabComponent } from './components/registry';

const serviceConsole = useServiceConsole();
provideServiceConsoleContext(serviceConsole);

const { detailLoading, activeTab, availableTabs } = serviceConsole;

const consoleNavItems = computed(() => resolveConsoleNavItems(availableTabs.value));
const activeTabComponent = computed(() => resolveConsoleTabComponent(activeTab.value));
</script>

<style src="./components/styles.less" lang="less"></style>
