<template>
  <div class="about-page">
    <section class="about-hero">
      <div class="container">
        <p class="about-kicker">关于我们</p>
        <h1>{{ appStore.siteName || '创欧云' }}</h1>
        <p class="about-desc">
          我们专注于云服务器、独立服务器与相关基础设施服务，提供从选型、购买、开通到运维支持的一体化体验。
        </p>
      </div>
    </section>

    <section class="about-section">
      <div class="container about-grid">
        <article class="about-card">
          <h2>服务能力</h2>
          <p>覆盖产品展示、在线购买、统一账单、自动开通、售后支持与服务控制台。</p>
        </article>
        <article class="about-card">
          <h2>联系方式</h2>
          <!-- 运维缺失不应暴露给访客：未配置的联系方式整行不渲染 -->
          <p v-if="appStore.serviceEmail">客服邮箱：{{ appStore.serviceEmail }}</p>
          <p v-if="appStore.serviceHours">服务时间：{{ appStore.serviceHours }}</p>
          <p v-if="appStore.serviceQqGroup">官方群号：{{ appStore.serviceQqGroup }}</p>
        </article>
      </div>
    </section>
  </div>
</template>

<script setup lang="ts">
import { useAppStore } from '@/app/stores/app'

const appStore = useAppStore()
</script>

<style scoped lang="scss">
.container {
  @include site-container;
}

.about-hero {
  padding: 64px 0 32px;
  background: linear-gradient(180deg, $bg-color-card 0%, $bg-color 100%);
}

.about-kicker {
  margin: 0;
  color: $color-primary;
  font-weight: 600;
}

h1 {
  margin: 12px 0 0;
  color: $text-color-primary;
  font-size: 32px;
  line-height: 1.25;
}

.about-desc {
  max-width: 720px;
  margin-top: 16px;
  color: $text-color-secondary;
  line-height: 1.9;
}

.about-section {
  padding: 24px 0 72px;
}

.about-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 20px;
}

// 小屏容器留白与列表页、内容详情页保持一致（48px 侧边距在 390 视口只剩 342px 可读宽度）
@media (max-width: $breakpoint-compact) {
  .container {
    width: calc(100% - 24px);
  }
}

@media (max-width: $breakpoint-mobile-max) {
  .about-grid {
    grid-template-columns: 1fr;
  }

  // 移动端标题字号降级，避免 32px 在窄屏挤压换行
  h1 {
    font-size: 28px;
  }
}

.about-card {
  padding: 28px;
  border: 1px solid $border-color;
  border-radius: 18px;
  background: $bg-color-card;
  box-shadow: 0 18px 36px rgba(15, 23, 42, 0.06);
}

.about-card h2 {
  margin: 0 0 12px;
  color: $text-color-primary;
  // 不写 font-size 会退回继承字号，与正文 14px 几乎没有差别
  font-size: $font-size-h2;
  line-height: 1.5;
}

.about-card p {
  margin: 8px 0 0;
  color: $text-color-secondary;
  line-height: 1.8;
}
</style>
