<template>
  <section class="catalog-page">
    <h1 class="sr-only">产品目录</h1>
    <t-card class="catalog-card" :bordered="false">
      <!-- 手机形态标记：本页是单一入口说明卡，手机端为卡片形态 -->
      <div class="catalog-result" :data-mobile-view="isMobile ? 'card' : undefined">
        <t-empty type="maintenance" description="统一使用官网产品目录" />
        <p>购买、报价和配置流程已经收口到 /products，客户端这里保留为稳定入口。</p>
        <t-button theme="primary" @click="router.push('/products')">打开产品目录</t-button>
      </div>
    </t-card>
  </section>
</template>
<script setup lang="ts">
import { useRouter } from 'vue-router';

import { useDeviceLayout } from '@/composables/useDeviceLayout';

const router = useRouter();
// 手机形态标记：与 CSS 的 @screen-mobile-max 同源
const { isMobile } = useDeviceLayout();
</script>
<style scoped lang="less">
.catalog-page {
  // padding 由 Starter 布局层统一提供
}

.catalog-card {
  background: var(--td-bg-color-container);
  border: thin solid var(--td-component-stroke);
  border-radius: var(--td-radius-medium);
  box-shadow: var(--td-shadow-1);
}

.catalog-result {
  display: flex;
  flex-direction: column;
  gap: var(--td-comp-margin-m);
  align-items: center;
  padding: var(--td-comp-paddingTB-xl) var(--td-comp-paddingLR-l);
  text-align: center;

  p {
    margin: 0;
    color: var(--td-text-color-secondary);
    font: var(--td-font-body-medium);
  }
}

@media (max-width: @screen-mobile-max) {
  // 说明卡在手机端收窄上下留白、按钮整行，避免 375px 下横向挤压
  .catalog-result {
    gap: var(--td-comp-margin-s);
    padding: var(--td-comp-paddingTB-l) var(--td-comp-paddingLR-s);

    :deep(.t-button) {
      width: 100%;
      margin-left: 0;
    }
  }
}
</style>
