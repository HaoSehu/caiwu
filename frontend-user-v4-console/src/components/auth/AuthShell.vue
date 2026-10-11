<template>
  <main class="auth-shell">
    <div class="auth-shell__stage">
      <header class="auth-shell__header">
        <a class="auth-brand" :href="authBrandHref">
          <img
            v-if="showLogo"
            :src="authLogoSrc"
            :alt="siteBranding.siteName"
            class="auth-brand__logo"
            @error="handleLogoError"
          />
          <template v-else>
            <span class="auth-brand__mark">{{ siteBranding.brandInitials }}</span>
            <span class="auth-brand__name">{{ siteBranding.siteName }}</span>
          </template>
          <span class="auth-brand__divider" />
          <span class="auth-brand__context">用户控制台</span>
        </a>
        <span class="auth-shell__header-spacer" />
        <button class="auth-shell__back" type="button" @click="handleBack">
          <chevron-left-icon />
          <span>返回</span>
        </button>
      </header>

      <section class="auth-shell__content">
        <div class="auth-card-column">
          <div class="auth-card">
            <div class="auth-card__head">
              <h2 class="auth-card__title">{{ title }}</h2>
              <div v-if="navText || navLinkText" class="auth-card__nav">
                <span>{{ navText }}</span>
                <router-link v-if="navLinkText" :to="navTo">{{ navLinkText }}</router-link>
              </div>
            </div>

            <div class="auth-card__body">
              <slot />
            </div>
          </div>
        </div>

        <div class="auth-guide">
          <h1 class="auth-guide__title">{{ heroTitle }}</h1>
          <p class="auth-guide__description">{{ heroDescription }}</p>
          <router-link v-if="ctaText" class="auth-guide__cta" :to="ctaTo">{{ ctaText }}</router-link>
        </div>
      </section>

      <footer class="auth-shell__footer">Copyright © {{ currentYear }} {{ siteBranding.siteName }}</footer>
    </div>
  </main>
</template>
<script setup lang="ts">
import { ChevronLeftIcon } from 'tdesign-icons-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import type { RouteLocationRaw } from 'vue-router';
import { useRouter } from 'vue-router';

import { useSiteBrandingStore } from '@/app/stores/siteBranding';

withDefaults(
  defineProps<{
    title: string;
    navText?: string;
    navLinkText?: string;
    navTo?: RouteLocationRaw;
    ctaText?: string;
    ctaTo?: RouteLocationRaw;
    heroTitle?: string;
    heroDescription?: string;
  }>(),
  {
    navText: '',
    navLinkText: '',
    navTo: '/client/login',
    ctaText: '',
    ctaTo: '/client/login',
    heroTitle: '安全进入创欧云控制台',
    heroDescription: '管理云服务、账单、工单与账户资料，所有操作继续沿用原有用户端接口。',
  },
);

const siteBranding = useSiteBrandingStore();
const router = useRouter();
const currentYear = computed(() => new Date().getFullYear());
const logoLoadFailed = ref(false);
const authLogoSrc = computed(() => siteBranding.clientConsoleIcon || siteBranding.siteLogo);
const showLogo = computed(() => Boolean(authLogoSrc.value) && !logoLoadFailed.value);

function handleLogoError() {
  logoLoadFailed.value = true;
}

const authBrandHref = computed(() => siteBranding.frontendUrl || '/client/login');

function handleBack() {
  if (window.history.length > 1) {
    router.back();
  } else {
    router.push('/');
  }
}

watch(authLogoSrc, () => {
  logoLoadFailed.value = false;
});

onMounted(() => {
  void siteBranding.fetchSiteConfig();
});
</script>
<style scoped lang="less">
// 登录壳层严格走 TDesign 令牌，仅提供浅色一种形态（docs/DESIGN.md §2）。
// 表单样式在 pages/client-auth/shared-auth.less 中直接引用 --td-* 令牌，不再经别名中转。
.auth-shell {
  min-height: 100vh;
  background: var(--td-bg-color-page);
  color: var(--td-text-color-primary);
}

.auth-shell__stage {
  display: flex;
  flex-direction: column;
  width: min(85rem, calc(100% - 4.5rem));
  min-height: 100vh;
  margin: 0 auto;
  padding: 1.75rem 0 1.5rem;
}

.auth-shell__header {
  display: flex;
  align-items: center;
  min-height: 2.75rem;
}

.auth-shell__header-spacer {
  flex: 1;
}

.auth-shell__back {
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  padding: 0.375rem 0.75rem;
  border: none;
  border-radius: var(--td-radius-default);
  background: transparent;
  color: var(--td-text-color-secondary);
  font-size: var(--td-font-size-body-medium);
  cursor: pointer;
  transition: color 0.2s;

  &:hover {
    color: var(--td-text-color-primary);
    background: var(--td-bg-color-secondarycontainer);
  }
}

.auth-brand {
  display: inline-flex;
  align-items: center;
  gap: 0.875rem;
  min-width: 0;
  color: var(--td-text-color-primary);
  text-decoration: none;
}

.auth-brand__logo {
  display: block;
  width: auto;
  max-width: 15rem;
  height: 2.5rem;
  object-fit: contain;
}

.auth-brand__mark {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  width: 2.5rem;
  height: 2.5rem;
  border-radius: var(--td-radius-medium);
  background: var(--td-brand-color);
  color: var(--td-text-color-anti);
  font-size: var(--td-font-size-title-medium);
  font-weight: 600;
  letter-spacing: 0.02em;
}

.auth-brand__name {
  font-size: var(--td-font-size-title-medium);
  font-weight: 600;
  letter-spacing: -0.01em;
}

.auth-brand__divider {
  flex-shrink: 0;
  width: 1px;
  height: 1.25rem;
  background: var(--td-component-stroke);
}

.auth-brand__context {
  color: var(--td-text-color-secondary);
  font-size: var(--td-font-size-body-large);
  font-weight: 500;
  letter-spacing: 0.02em;
}

.auth-shell__content {
  flex: 1;
  display: grid;
  grid-template-columns: minmax(24rem, 26.25rem) minmax(20rem, 35rem);
  align-items: center;
  gap: clamp(3rem, 7vw, 7.5rem);
  padding: clamp(3rem, 8vh, 5.5rem) 0 2.25rem;
}

.auth-card {
  width: 100%;
  padding: 2rem 2rem 1.875rem;
  border: 1px solid var(--td-component-stroke);
  border-radius: var(--td-radius-medium);
  background: var(--td-bg-color-container);
  box-shadow: var(--td-shadow-1);
}

.auth-card__head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: 1.625rem;
}

.auth-card__title {
  margin: 0;
  color: var(--td-text-color-primary);
  font-size: var(--td-font-size-headline-small);
  font-weight: 600;
  line-height: 1.2;
  letter-spacing: -0.02em;
}

.auth-card__nav {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: flex-end;
  gap: 0.25rem;
  max-width: 12rem;
  color: var(--td-text-color-secondary);
  font-size: var(--td-font-size-body-small);
  line-height: 1.6;
  text-align: right;
}

.auth-card__nav a {
  color: var(--td-brand-color);
  font-weight: 600;
  text-decoration: none;
}

.auth-card__nav a:hover {
  text-decoration: underline;
}

.auth-card__body {
  width: 100%;
}

.auth-guide {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  justify-content: center;
  padding-right: clamp(0rem, 4vw, 3rem);
}

.auth-guide__title {
  max-width: 14em;
  margin: 0;
  color: var(--td-text-color-primary);
  font-size: var(--td-font-size-headline-medium);
  font-weight: 600;
  line-height: 1.24;
  letter-spacing: -0.01em;
  white-space: pre-line;
  text-wrap: balance;
}

.auth-guide__description {
  max-width: 28rem;
  margin: 1rem 0 0;
  color: var(--td-text-color-secondary);
  font-size: var(--td-font-size-body-medium);
  line-height: 1.86;
  text-wrap: pretty;
}

.auth-guide__cta {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 8.75rem;
  height: 2.75rem;
  margin-top: 2rem;
  padding: 0 1.375rem;
  border: 1px solid var(--td-component-stroke);
  border-radius: var(--td-radius-default);
  background: var(--td-bg-color-container);
  color: var(--td-brand-color);
  font-size: var(--td-font-size-body-medium);
  font-weight: 600;
  text-decoration: none;
  transition: border-color @anim-duration-base @anim-time-fn-easing;
}

.auth-guide__cta:hover {
  border-color: var(--td-brand-color-hover);
}

.auth-shell__footer {
  width: 100%;
  text-align: center;
  color: var(--td-text-color-secondary);
  font-size: var(--td-font-size-body-small);
  line-height: 1.6;
}

@media (max-width: @screen-lg-max) {
  .auth-shell__stage {
    width: min(78rem, calc(100% - 3.5rem));
  }

  .auth-shell__content {
    grid-template-columns: minmax(23rem, 25rem) minmax(18rem, 1fr);
    gap: clamp(2.5rem, 6vw, 5rem);
  }
}

@media (max-width: @screen-sm-max) {
  .auth-shell__stage {
    width: calc(100% - 2rem);
    padding: 1.25rem 0 1.5rem;
  }

  .auth-shell__content {
    grid-template-columns: 1fr;
    gap: 1.5rem;
    padding: 2rem 0 1.5rem;
  }

  .auth-brand {
    gap: 0.75rem;
  }

  .auth-brand__divider,
  .auth-brand__context {
    display: none;
  }

  .auth-brand__logo {
    max-width: 12.5rem;
    height: 2rem;
  }

  .auth-brand__mark {
    width: 2.25rem;
    height: 2.25rem;
    font-size: var(--td-font-size-body-large);
  }

  .auth-brand__name {
    font-size: var(--td-font-size-body-medium);
  }

  .auth-card {
    padding: 1.625rem 1.25rem 1.375rem;
  }

  .auth-card__head {
    flex-direction: column;
    gap: 0.5rem;
    margin-bottom: 1.5rem;
  }

  .auth-card__title {
    font-size: var(--td-font-size-title-large);
  }

  .auth-card__nav {
    max-width: none;
    justify-content: flex-start;
    text-align: left;
  }

  .auth-guide {
    display: none;
  }

  .auth-shell__footer {
    text-align: left;
  }
}

@media (max-width: @screen-xxs-rem) {
  .auth-shell__stage {
    width: calc(100% - 1.5rem);
  }

  .auth-brand__divider {
    display: none;
  }

  .auth-card {
    padding-inline: 1.125rem;
  }

  .auth-card__title {
    font-size: var(--td-font-size-title-large);
  }
}
</style>
