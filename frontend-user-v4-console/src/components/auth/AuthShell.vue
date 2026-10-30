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
            loading="eager"
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
        <button class="auth-shell__back" type="button" aria-label="返回上一页" @click="handleBack">
          <chevron-left-icon />
          <span>返回</span>
        </button>
      </header>

      <section class="auth-shell__content">
        <div class="auth-card-column">
          <div class="auth-card">
            <div class="auth-card__head">
              <!-- A-06：表单标题是本页唯一实质内容，用 h1；右侧营销文案降为 h2。 -->
              <h1 class="auth-card__title">{{ title }}</h1>
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
          <h2 class="auth-guide__title">{{ heroTitle }}</h2>
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

import { useSiteBrandingStore } from '@/store';

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
// C-21：间距、控件高度与可枚举的尺寸一律映射到 --td-* 令牌，令牌号段里没有的中间值
// （28/30/26/22/18/14/6px）用相邻令牌相加减表达，数值与原 rem 完全等价；
// 仍然保留 rem 的只有随视口缩放的栅格宽度与图标/文字的载荷宽度（minmax/clamp/min()/max-width 里的 85rem、26.25rem、28rem 等）。
// 表单样式在 pages/client-auth/shared-auth.less 中直接引用 --td-* 令牌，不再经别名中转。
.auth-shell {
  // dvh 兜底：移动端地址栏收缩时避免底部裁切（与 layout.less 的双写策略一致）
  min-height: 100vh;
  min-height: 100dvh;
  background: var(--td-bg-color-page);
  color: var(--td-text-color-primary);
}

.auth-shell__stage {
  display: flex;
  flex-direction: column;
  // 4.5rem(=72px) 是给两侧留白：56px 顶栏档 + 16px 间距
  width: min(85rem, calc(100% - (var(--td-comp-size-xxxl) + var(--td-comp-margin-l))));
  min-height: 100vh;
  min-height: 100dvh;
  margin: 0 auto;
  padding: calc(var(--td-comp-margin-xxl) + var(--td-comp-margin-xs)) 0 var(--td-comp-margin-xxl);
}

.auth-shell__header {
  display: flex;
  align-items: center;
  // 44px 触控基线：40 + 4
  min-height: calc(var(--td-comp-size-xl) + var(--td-comp-margin-xs));
}

.auth-shell__header-spacer {
  flex: 1;
}

.auth-shell__back {
  display: inline-flex;
  align-items: center;
  gap: var(--td-comp-margin-xs);
  padding: calc(var(--td-comp-margin-s) - var(--td-comp-margin-xxs)) var(--td-comp-margin-m);
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
  gap: calc(var(--td-comp-margin-l) - var(--td-comp-margin-xxs));
  min-width: 0;
  color: var(--td-text-color-primary);
  text-decoration: none;
}

.auth-brand__logo {
  display: block;
  width: auto;
  max-width: 15rem;
  height: var(--td-comp-size-xl);
  object-fit: contain;
}

.auth-brand__mark {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  width: var(--td-comp-size-xl);
  height: var(--td-comp-size-xl);
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
  height: var(--td-comp-size-xxs);
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
  padding: clamp(3rem, 8vh, 5.5rem) 0 calc(var(--td-comp-margin-xxxxl) - var(--td-comp-margin-xs));
}

.auth-card {
  width: 100%;
  padding: var(--td-comp-margin-xxxl) var(--td-comp-margin-xxxl)
    calc(var(--td-comp-margin-xxxl) - var(--td-comp-margin-xxs));
  border: thin solid var(--td-component-stroke);
  border-radius: var(--td-radius-medium);
  background: var(--td-bg-color-container);
  box-shadow: var(--td-shadow-1);
}

.auth-card__head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--td-comp-margin-l);
  margin-bottom: calc(var(--td-comp-margin-xxl) + var(--td-comp-margin-xxs));
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
  gap: var(--td-comp-margin-xs);
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
  margin: var(--td-comp-margin-l) 0 0;
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
  // 44px 触控基线：40 + 4
  height: calc(var(--td-comp-size-xl) + var(--td-comp-margin-xs));
  margin-top: var(--td-comp-margin-xxxl);
  padding: 0 calc(var(--td-comp-margin-xl) + var(--td-comp-margin-xxs));
  border: thin solid var(--td-component-stroke);
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
    width: min(78rem, calc(100% - var(--td-comp-size-xxxl)));
  }

  .auth-shell__content {
    grid-template-columns: minmax(23rem, 25rem) minmax(18rem, 1fr);
    gap: clamp(2.5rem, 6vw, 5rem);
  }
}

@media (max-width: @screen-sm-max) {
  .auth-shell__stage {
    width: calc(100% - var(--td-comp-margin-xxxl));
    padding: var(--td-comp-margin-xl) 0 var(--td-comp-margin-xxl);
  }

  .auth-shell__content {
    grid-template-columns: 1fr;
    gap: var(--td-comp-margin-xxl);
    padding: var(--td-comp-margin-xxxl) 0 var(--td-comp-margin-xxl);
  }

  .auth-brand {
    gap: var(--td-comp-margin-m);
  }

  .auth-brand__divider,
  .auth-brand__context {
    display: none;
  }

  .auth-brand__logo {
    max-width: 12.5rem;
    height: var(--td-comp-size-m);
  }

  .auth-brand__mark {
    width: var(--td-comp-size-l);
    height: var(--td-comp-size-l);
    font-size: var(--td-font-size-body-large);
  }

  .auth-brand__name {
    font-size: var(--td-font-size-body-medium);
  }

  .auth-card {
    padding: calc(var(--td-comp-margin-xxl) + var(--td-comp-margin-xxs)) var(--td-comp-margin-xl)
      calc(var(--td-comp-margin-xl) + var(--td-comp-margin-xxs));
  }

  .auth-card__head {
    flex-direction: column;
    gap: var(--td-comp-margin-s);
    margin-bottom: var(--td-comp-margin-xxl);
  }

  .auth-card__title {
    font-size: var(--td-font-size-title-large);
  }

  .auth-card__nav {
    max-width: none;
    justify-content: flex-start;
    text-align: left;
  }

  /* A-07 复核：原先这里整块 display:none，再靠页面外一份 .auth-guide__cta--mobile 副本
     保住「查看产品目录」入口——同一段 CTA 在 DOM 里出现两次，读屏和 Tab 顺序都会遇到两遍。
     现在不关整块，只关标题与描述：手机端只留 CTA，居中、去掉 desktop 的右内边距与上外边距。 */
  .auth-guide {
    align-items: center;
    padding-right: 0;
  }

  .auth-guide__title,
  .auth-guide__description {
    display: none;
  }

  .auth-guide__cta {
    margin-top: 0;
  }

  .auth-shell__footer {
    text-align: left;
  }
}

@media (max-width: @screen-xxs-rem) {
  .auth-shell__stage {
    width: calc(100% - var(--td-comp-margin-xxl));
  }

  .auth-brand__divider {
    display: none;
  }

  .auth-card {
    padding-inline: calc(var(--td-comp-margin-l) + var(--td-comp-margin-xxs));
  }

  .auth-card__title {
    font-size: var(--td-font-size-title-large);
  }
}
</style>
