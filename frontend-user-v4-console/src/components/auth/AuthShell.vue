<template>
  <main class="client-auth-shell">
    <section class="client-auth-shell__aside">
      <router-link class="client-auth-shell__brand" to="/client/login">
        <span class="client-auth-shell__brand-mark">{{ siteBranding.brandInitials }}</span>
        <span class="client-auth-shell__brand-name">{{ siteBranding.siteName }}</span>
      </router-link>

      <div class="client-auth-shell__intro">
        <p class="client-auth-shell__eyebrow">用户控制台</p>
        <h1>{{ heroTitle }}</h1>
        <p>{{ heroDescription }}</p>
      </div>

      <div class="client-auth-shell__support">
        <span>服务时间</span>
        <strong>{{ siteBranding.serviceHours }}</strong>
      </div>
    </section>

    <section class="client-auth-shell__main">
      <t-card class="client-auth-shell__card" :bordered="false">
        <template #header>
          <div class="client-auth-shell__header">
            <h2>{{ title }}</h2>
            <div v-if="navText || navLinkText" class="client-auth-shell__nav">
              <span>{{ navText }}</span>
              <router-link v-if="navLinkText" :to="navTo">{{ navLinkText }}</router-link>
            </div>
          </div>
        </template>

        <slot />

        <template v-if="ctaText" #footer>
          <router-link class="client-auth-shell__cta" :to="ctaTo">{{ ctaText }}</router-link>
        </template>
      </t-card>
    </section>

    <footer class="client-auth-shell__footer">Copyright © {{ currentYear }} {{ siteBranding.siteName }}</footer>
  </main>
</template>

<script setup lang="ts">
import { computed, onMounted } from 'vue';
import type { RouteLocationRaw } from 'vue-router';

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
const currentYear = computed(() => new Date().getFullYear());

onMounted(() => {
  siteBranding.fetchSiteConfig();
});
</script>

<style scoped lang="less">
.client-auth-shell {
  min-height: 100vh;
  display: grid;
  grid-template-columns: minmax(280px, 0.42fr) minmax(0, 1fr);
  position: relative;
  background: var(--td-bg-color-page);
  color: var(--td-text-color-primary);
}

.client-auth-shell__aside {
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  padding: var(--td-comp-paddingTB-xxl) var(--td-comp-paddingLR-xxl);
  border-right: 1px solid var(--td-component-border);
  background: var(--td-bg-color-container);
}

.client-auth-shell__brand {
  display: inline-flex;
  align-items: center;
  gap: var(--td-comp-margin-s);
  width: fit-content;
  color: var(--td-text-color-primary);
  text-decoration: none;
}

.client-auth-shell__brand-mark {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: var(--td-comp-size-xl);
  height: var(--td-comp-size-xl);
  border-radius: var(--td-radius-medium);
  background: var(--td-brand-color);
  color: var(--td-text-color-anti);
  font: var(--td-font-title-medium);
}

.client-auth-shell__brand-name {
  font: var(--td-font-title-medium);
}

.client-auth-shell__intro {
  display: grid;
  gap: var(--td-comp-margin-m);
  max-width: 28rem;
}

.client-auth-shell__intro h1,
.client-auth-shell__header h2 {
  margin: 0;
  color: var(--td-text-color-primary);
  font: var(--td-font-headline-medium);
}

.client-auth-shell__intro p {
  margin: 0;
  color: var(--td-text-color-secondary);
  line-height: var(--td-line-height-body-medium);
}

.client-auth-shell__eyebrow {
  color: var(--td-brand-color) !important;
  font: var(--td-font-body-medium);
}

.client-auth-shell__support {
  display: grid;
  gap: var(--td-comp-margin-xs);
  color: var(--td-text-color-secondary);
  font: var(--td-font-body-small);
}

.client-auth-shell__support strong {
  color: var(--td-text-color-primary);
  font: var(--td-font-body-medium);
}

.client-auth-shell__main {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: var(--td-comp-paddingTB-xxl) var(--td-comp-paddingLR-xxl);
}

.client-auth-shell__card {
  width: min(100%, 32rem);
  box-shadow: var(--td-shadow-1);
}

.client-auth-shell__header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--td-comp-margin-m);
}

.client-auth-shell__nav {
  display: flex;
  gap: var(--td-comp-margin-xs);
  color: var(--td-text-color-secondary);
  font: var(--td-font-body-small);
  white-space: nowrap;
}

.client-auth-shell__nav a,
.client-auth-shell__cta {
  color: var(--td-brand-color);
  text-decoration: none;
}

.client-auth-shell__footer {
  position: absolute;
  left: 0;
  right: 0;
  bottom: var(--td-comp-margin-m);
  color: var(--td-text-color-placeholder);
  font: var(--td-font-body-small);
  text-align: center;
  pointer-events: none;
}

@media (max-width: @screen-sm-max) {
  .client-auth-shell {
    display: flex;
    flex-direction: column;
  }

  .client-auth-shell__aside {
    gap: var(--td-comp-margin-l);
    padding: var(--td-comp-paddingTB-xl) var(--td-comp-paddingLR-l);
    border-right: 0;
    border-bottom: 1px solid var(--td-component-border);
  }

  .client-auth-shell__intro h1 {
    font: var(--td-font-title-large);
  }

  .client-auth-shell__support {
    display: none;
  }

  .client-auth-shell__main {
    flex: 1;
    align-items: flex-start;
    padding: var(--td-comp-paddingTB-l) var(--td-comp-paddingLR-l) var(--td-comp-paddingTB-xxl);
  }

  .client-auth-shell__header {
    display: grid;
  }

  .client-auth-shell__footer {
    position: static;
    padding: 0 var(--td-comp-paddingLR-l) var(--td-comp-paddingTB-l);
  }
}
</style>
