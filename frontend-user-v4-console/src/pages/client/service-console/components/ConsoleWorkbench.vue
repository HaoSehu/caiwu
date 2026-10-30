<template>
  <!--
    SC-07：计算型与端口转发型控制台此前是两份逐行同构的页面文件，
    工作台这一块（侧栏 + 内容区 + 标签切换）完全一致，只在「有没有端口转发标签」上不同，
    结果任何一次修复都要手工同步两次（历史上已经出现只改一边的情况）。
    这里把工作台收成一个组件，两页只保留各自的 data-state 与路由归属判断。
  -->
  <div class="console-workbench">
    <console-sidebar v-model:active-tab="activeTab" :items="navItems" />
    <main class="console-content">
      <overview-tab v-if="activeTab === 'overview'" />
      <monitor-tab v-else-if="activeTab === 'monitor'" />
      <security-tab v-else-if="activeTab === 'security'" />
      <!-- 端口转发只出现在 NAT_TABS 的标签集合里，计算型控制台永远命中不到 -->
      <nat-tab v-else-if="hasNatTab && activeTab === 'nat'" />
      <logs-tab v-else-if="activeTab === 'logs'" />
      <finance-tab v-else-if="activeTab === 'finance'" />
      <!-- VNC 面板常驻（v-show）：切走再切回不卸载 iframe，避免 WebSocket 断连与一次性地址失效 -->
      <vnc-tab v-show="activeTab === 'vnc'" />
    </main>
  </div>
</template>
<script setup lang="ts">
import { computed } from 'vue';

import ConsoleSidebar from './ConsoleSidebar.vue';
import { useServiceConsoleContext } from './context';
import type { ServiceConsoleNavItem } from './registry';
import FinanceTab from './tabs/FinanceTab.vue';
import LogsTab from './tabs/LogsTab.vue';
import MonitorTab from './tabs/MonitorTab.vue';
import NatTab from './tabs/NatTab.vue';
import OverviewTab from './tabs/OverviewTab.vue';
import SecurityTab from './tabs/SecurityTab.vue';
import VncTab from './tabs/VncTab.vue';

const props = defineProps<{ navItems: ServiceConsoleNavItem[] }>();

const { activeTab } = useServiceConsoleContext();
const hasNatTab = computed(() => props.navItems.some((item) => item.key === 'nat'));
</script>
