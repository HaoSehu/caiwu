<template>
  <t-config-provider :global-config="zhConfig">
    <router-view :key="mode" :class="[mode]" />
  </t-config-provider>
</template>
<script setup lang="ts">
import zhConfig from 'tdesign-vue-next/es/locale/zh_CN';
import { computed } from 'vue';

import { useSettingStore } from '@/store';

const store = useSettingStore();

const mode = computed(() => {
  return store.displayMode;
});
</script>
<style lang="less" scoped>
#nprogress .bar {
  background: var(--td-brand-color) !important;
}
</style>
