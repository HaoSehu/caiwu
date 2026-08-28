<template>
  <section class="client-result-404">
    <div class="result-404-card">
      <p class="result-404-code" aria-hidden="true">404</p>
      <h1 class="result-404-title">页面不存在或已下线</h1>
      <p class="result-404-description">请检查地址是否输入正确，或返回控制台继续操作。</p>
      <div class="result-404-actions">
        <t-button theme="primary" @click="router.push('/client/dashboard')">返回控制台首页</t-button>
        <t-button v-if="canGoBack" variant="outline" @click="router.back()">返回上一页</t-button>
      </div>
    </div>
  </section>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { useRouter } from 'vue-router';

const router = useRouter();

// 直接输入地址打开时无上一页历史，隐藏“返回上一页”避免无效按钮
const canGoBack = computed(() => {
  const state = window.history?.state as { back?: unknown } | null;
  return Boolean(state && typeof state.back === 'string');
});
</script>

<style scoped lang="less">
.client-result-404 {
  display: flex;
  flex: 1;
  align-items: center;
  justify-content: center;
  min-height: 60vh;
  // padding 由 Starter 布局层统一提供
}

.result-404-card {
  display: flex;
  flex-direction: column;
  gap: var(--td-comp-margin-s);
  align-items: center;
  width: min(100%, 26rem);
  padding: var(--td-comp-paddingTB-xxl) var(--td-comp-paddingLR-xl);
  text-align: center;
  background: var(--td-bg-color-container);
  border: thin solid var(--td-component-stroke);
}

.result-404-code {
  margin: 0;
  color: var(--td-brand-color);
  font: var(--td-font-headline-large);
  font-weight: 700;
  line-height: 1;
}

.result-404-title {
  margin: var(--td-comp-margin-xs) 0 0;
  color: var(--td-text-color-primary);
  font: var(--td-font-title-large);
  font-weight: 600;
}

.result-404-description {
  margin: 0;
  color: var(--td-text-color-secondary);
  font: var(--td-font-body-medium);
}

.result-404-actions {
  display: flex;
  flex-wrap: wrap;
  gap: var(--td-comp-margin-s);
  justify-content: center;
  margin-top: var(--td-comp-margin-m);
}
</style>
