<template>
  <div class="auth-page">
    <section class="auth-left">
      <div class="left-content">
        <router-link to="/" class="brand-bar">
          <img v-if="appStore.siteLogo" :src="appStore.siteLogo" :alt="appStore.siteName" class="brand-logo" />
        </router-link>

        <div class="hero-text">
          <h1>{{ heroTitle }}</h1>
          <h2>{{ heroAccentTitle }}</h2>
        </div>

        <div class="feature-tags">
          <span v-for="item in featureTags" :key="item" class="tag">{{ item }}</span>
        </div>

        <router-link class="cta-link" :to="ctaTo">{{ ctaText }}</router-link>
      </div>

      <div class="decoration" aria-hidden="true">
        <div class="deco-building b1" />
        <div class="deco-building b2" />
        <div class="deco-building b3" />
        <div class="deco-shield">
          <svg width="48" height="48" viewBox="0 0 48 48" fill="none">
            <circle cx="24" cy="24" r="22" fill="#d6e4ff" opacity="0.6" />
            <circle cx="24" cy="24" r="16" fill="#a3bffa" opacity="0.5" />
            <path d="M17 24l5 5 9-9" stroke="#3b82f6" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
          </svg>
        </div>
        <div class="deco-dot" />
      </div>
    </section>

    <section class="auth-right">
      <div class="form-card">
        <div class="card-header">
          <h3 class="card-title">{{ title }}</h3>
          <div v-if="navText || navLinkText" class="card-nav">
            <span>{{ navText }}</span>
            <router-link v-if="navLinkText" :to="navTo">{{ navLinkText }}</router-link>
          </div>
        </div>

        <slot />
      </div>
    </section>

    <footer class="auth-footer">Copyright © {{ currentYear }} {{ appStore.siteName }} 版权所有</footer>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useAppStore } from '@/stores/app'
import { useSeo } from '@/composables/useSeo'

const appStore = useAppStore()

// 登录 / 注册 / 找回密码 / LoginAs 四个页面共用本壳，统一标记为 noindex
useSeo({ robots: 'noindex,nofollow' })

defineProps({
  heroTitle: {
    type: String,
    default: '满足企业云计算需求',
  },
  heroAccentTitle: {
    type: String,
    default: '一站式 IDC 服务平台',
  },
  title: {
    type: String,
    required: true,
  },
  navText: {
    type: String,
    default: '',
  },
  navLinkText: {
    type: String,
    default: '',
  },
  navTo: {
    type: [String, Object],
    default: '/client/login',
  },
  ctaText: {
    type: String,
    default: '登录控制台体验更多功能 >',
  },
  ctaTo: {
    type: [String, Object],
    default: '/client/login',
  },
  featureTags: {
    type: Array,
    default: () => ([
      '云服务器托管',
      '数据安全防护',
      '高防 IP 接入',
      '域名与 SSL',
      '余额与账单',
      '工单技术支持',
    ]),
  },
})

const currentYear = computed(() => new Date().getFullYear())
</script>

<style scoped lang="scss">
.auth-page {
  min-height: 100vh;
  display: flex;
  position: relative;
  overflow: hidden;
  background:
    radial-gradient(circle at 18% 18%, rgba(221, 122, 31, 0.1), transparent 24%),
    radial-gradient(circle at 82% 12%, rgba(22, 93, 255, 0.12), transparent 30%),
    linear-gradient(160deg, #f8fafc, #eef3fb 42%, #f6f7fb);
}

.auth-page::before,
.auth-page::after {
  content: '';
  position: absolute;
  border-radius: 50%;
  pointer-events: none;
}

.auth-page::before {
  top: -160px;
  right: -160px;
  width: 460px;
  height: 460px;
  background: radial-gradient(circle, rgba(22, 93, 255, 0.14), rgba(22, 93, 255, 0));
}

.auth-page::after {
  left: 260px;
  bottom: -160px;
  width: 380px;
  height: 380px;
  background: radial-gradient(circle, rgba(221, 122, 31, 0.12), rgba(221, 122, 31, 0));
}

.auth-left {
  width: 440px;
  padding: 48px 40px 0;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  position: relative;
  z-index: 1;
}

.left-content {
  width: 100%;
}

.brand-bar {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  text-decoration: none;
}

.brand-logo {
  display: block;
  width: 150px;
  object-fit: contain;
  object-position: left center;
}

.hero-text {
  margin-top: 48px;
}

.hero-text h1,
.hero-text h2 {
  font-size: 26px;
  font-weight: 700;
  line-height: 1.4;
}

.hero-text h2 {
  margin-top: 4px;
  color: $color-primary;
}

.feature-tags {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  margin: 32px 0 24px;
}

.tag {
  padding: 8px 18px;
  border: 1px solid $border-color;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.5);
  color: $text-color-primary;
  font-size: 14px;
  line-height: 1.6;
}

.cta-link {
  display: inline-block;
  margin-bottom: 36px;
  color: $color-primary;
  font-size: 14px;
  font-weight: 500;
  line-height: 1.6;
}

.decoration {
  width: 360px;
  height: 200px;
  position: relative;
}

.deco-building {
  position: absolute;
  bottom: 0;
  border-radius: $sm-border-radius $sm-border-radius 0 0;
  background: linear-gradient(#dbe4f1, #cdd8e8);
}

.deco-building.b1 {
  left: 20px;
  width: 50px;
  height: 100px;
}

.deco-building.b2 {
  left: 80px;
  width: 60px;
  height: 140px;
}

.deco-building.b3 {
  left: 150px;
  width: 45px;
  height: 80px;
}

.deco-shield {
  position: absolute;
  left: 120px;
  bottom: 70px;
}

.deco-dot {
  position: absolute;
  left: 40px;
  bottom: 30px;
  width: 10px;
  height: 10px;
  border-radius: 50%;
  background: $color-accent-orange;
}

.auth-right {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 40px;
  position: relative;
  z-index: 1;
}

.form-card {
  width: 100%;
  max-width: 520px;
  padding: 44px 44px 40px;
  border-radius: $lg-border-radius;
  background: $bg-color-card;
  box-shadow: $shadow-lg;
}

.card-header {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 32px;
  padding-bottom: 20px;
  border-bottom: 1px solid rgba(0, 0, 0, 0.04);
}

.card-title {
  color: $text-color-primary;
  font-size: 22px;
  font-weight: 700;
  line-height: 1.4;
  letter-spacing: -0.01em;
}

.card-nav {
  color: $text-color-secondary;
  font-size: 13px;
  line-height: 1.6;
  white-space: nowrap;
}

.card-nav a {
  margin-left: 4px;
  color: $color-primary;
  font-weight: 500;
  transition: color 0.2s ease;
}

.auth-footer {
  position: absolute;
  left: 0;
  right: 0;
  bottom: 0;
  padding: 12px 20px;
  color: $text-color-placeholder;
  font-size: 12px;
  line-height: 1.6;
  text-align: center;
  z-index: 1;
}

:deep(.auth-form) {
  width: 100%;
}

:deep(.field-block + .field-block) {
  margin-top: 22px;
}

:deep(.field-label) {
  margin-bottom: 10px;
  color: $text-color-primary;
  font-size: 14px;
  font-weight: 500;
  line-height: 1.6;
}

:deep(.field-label.is-required::before) {
  content: '*';
  margin-right: 4px;
  color: $color-danger;
}

:deep(.auth-form .el-form-item) {
  margin-bottom: 0;
}

:deep(.auth-form .el-form-item__content) {
  line-height: normal;
}

:deep(.auth-form .el-input__wrapper) {
  min-height: 46px;
  border-radius: $sm-border-radius !important;
  background: $bg-color-card !important;
  box-shadow: 0 0 0 1px $border-color inset !important;
  transition: box-shadow 0.2s ease, border-color 0.2s ease;
}

:deep(.auth-form .el-input__wrapper:hover) {
  box-shadow: 0 0 0 1px $border-color-strong inset !important;
}

:deep(.auth-form .el-input__wrapper.is-focus) {
  box-shadow: 0 0 0 2px rgba($color-primary, 0.15), 0 0 0 1px $color-primary inset !important;
}

:deep(.auth-form .el-input__inner) {
  color: $text-color-primary !important;
  font-size: 14px;
}

:deep(.field-tip) {
  margin-top: 8px;
  color: $text-color-placeholder;
  font-size: 12px;
  line-height: 1.6;
}

:deep(.inline-action-row) {
  display: flex;
  justify-content: flex-end;
  margin-top: 14px;
}

:deep(.auth-link) {
  color: $color-primary;
  font-size: 14px;
  font-weight: 500;
  line-height: 1.6;
  transition: color 0.2s ease;
}

:deep(.code-row) {
  display: flex;
  align-items: flex-start;
  gap: 10px;
}

:deep(.code-row .code-input) {
  flex: 1;
}

:deep(.send-code-btn) {
  width: 110px;
  height: 46px;
  padding: 0 14px;
  border-radius: $sm-border-radius !important;
  font-size: 13px;
  font-weight: 500;
}

:deep(.auth-submit-btn) {
  width: 100%;
  height: 48px;
  margin-top: 28px;
  border-radius: 8px !important;
  font-size: 15px;
  font-weight: 600;
  letter-spacing: 0.02em;
}

:deep(.agreement-row) {
  margin-top: 16px;
}

:deep(.agreement-row .el-checkbox) {
  align-items: center;
  height: auto;
  white-space: normal;
}

:deep(.agreement-row .el-checkbox__label) {
  padding-left: 8px;
  color: $text-color-secondary !important;
  font-size: 13px;
  line-height: 1.7;
}

:deep(.agreement-link) {
  color: $color-primary;
}

:deep(.agreement-row .el-form-item__error) {
  position: static;
  padding-top: 6px;
}

:deep(.auth-form .el-form-item__error) {
  line-height: 1.5;
  font-size: 12px;
}

@media (max-width: 1024px) {
  .auth-page {
    min-height: 100vh;
    flex-direction: column;
    align-items: center;
    padding-bottom: 24px;
  }

  .auth-page::before,
  .auth-page::after {
    display: none;
  }

  .auth-left,
  .auth-right {
    width: 100%;
    max-width: 560px;
    flex: none;
  }

  .auth-left {
    padding: 32px 24px 0;
  }

  .left-content {
    max-width: 520px;
    margin: 0 auto;
  }

  .hero-text {
    margin-top: 36px;
  }

  .decoration {
    width: 100%;
    max-width: 320px;
    margin: 0 auto;
  }

  .auth-right {
    padding: 0 24px 24px;
  }

  .form-card {
    width: 100%;
    max-width: 520px;
    margin: 0 auto;
  }

  .auth-footer {
    position: static;
    width: 100%;
    padding-top: 0;
    padding-bottom: 24px;
  }
}

@media (max-width: 640px) {
  .auth-page {
    padding-bottom: 20px;
    background:
      radial-gradient(circle at 82% 6%, rgba(22, 93, 255, 0.08), transparent 40%),
      linear-gradient(168deg, #f8fafc, #eef3fb 50%, #f6f7fb);
  }

  .brand-logo {
    width: 120px;
  }

  .hero-text {
    margin-top: 20px;
  }

  .hero-text h1,
  .hero-text h2 {
    font-size: 20px;
  }

  .feature-tags {
    display: none;
  }

  .cta-link {
    display: none;
  }

  .decoration {
    display: none;
  }

  .auth-left {
    padding: 24px 20px 0;
  }

  .left-content {
    max-width: 100%;
  }

  .auth-right {
    padding: 16px 20px 0;
  }

  .form-card {
    max-width: 100%;
    padding: 24px 20px;
    border-radius: 12px;
    box-shadow: 0 2px 12px rgba(15, 23, 42, 0.06);
  }

  .card-header {
    align-items: flex-start;
    flex-direction: column;
    gap: 6px;
    margin-bottom: 20px;
  }

  .card-title {
    font-size: 17px;
  }

  .card-nav {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
    font-size: 12px;
  }

  .card-nav a {
    margin-left: 0;
  }

  .auth-footer {
    padding: 16px 20px 24px;
    font-size: 11px;
  }

  :deep(.field-block + .field-block) {
    margin-top: 14px;
  }

  :deep(.field-label) {
    margin-bottom: 8px;
    font-size: 13px;
  }

  :deep(.auth-form .el-input__wrapper) {
    min-height: 42px;
  }

  :deep(.auth-form .el-input__inner) {
    font-size: 14px;
  }

  :deep(.auth-submit-btn) {
    height: 44px;
    margin-top: 20px;
    font-size: 14px;
    border-radius: 8px !important;
  }

  :deep(.code-row) {
    flex-direction: column;
  }

  :deep(.send-code-btn) {
    width: 100%;
    height: 42px;
    border-radius: 8px !important;
  }

  :deep(.field-tip) {
    margin-top: 6px;
    font-size: 11px;
  }

  :deep(.agreement-row) {
    margin-top: 12px;
  }

  :deep(.agreement-row .el-checkbox__label) {
    font-size: 12px;
    line-height: 1.6;
  }
}

@media (max-width: 380px) {
  .auth-left {
    padding: 20px 16px 0;
  }

  .hero-text h1,
  .hero-text h2 {
    font-size: 18px;
  }

  .auth-right {
    padding: 12px 16px 0;
  }

  .form-card {
    padding: 20px 16px;
  }

  .card-title {
    font-size: 16px;
  }
}
</style>
