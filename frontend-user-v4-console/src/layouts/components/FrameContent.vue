<template>
  <div :class="prefixCls" :style="getWrapStyle">
    <t-loading :loading="loading" size="large" :style="getWrapStyle">
      <iframe ref="frameRef" :src="frameSrc" :class="`${prefixCls}__main`" @load="hideLoading"></iframe>
    </t-loading>
  </div>
</template>
<script lang="ts" setup>
import { useDebounceFn, useWindowSize } from '@vueuse/core';
import type { CSSProperties } from 'vue';
import { computed, ref, unref, watch } from 'vue';

import { prefix } from '@/config/global';
import { useSettingStore } from '@/store';

defineProps({
  frameSrc: {
    type: String,
    default: '',
  },
});

const { width, height } = useWindowSize();

const loading = ref(true);
const heightRef = ref(window.innerHeight);
const frameRef = ref<HTMLFrameElement>();
const prefixCls = computed(() => [`${prefix}-iframe-page`]);
const settingStore = useSettingStore();

const getWrapStyle = computed((): CSSProperties => {
  return {
    height: `${heightRef.value}px`,
  };
});

// 令牌在每次 calcHeight 现取，而不是 setup 顶层读一次缓存住（L-29）：这两个令牌的值由
// theme.css / 布局层样式决定，运行时可能被媒体查询或主题覆盖改写，缓存住的话重算时仍按旧值
// 叠加，iframe 高度会一直偏大。目前布局层的 ≤991px 媒体查询只改写了 --td-comp-paddingTB-l
// 等 -l 档，尚未触及这里用到的 --td-comp-size-xxxl（56）与 --td-comp-paddingTB-xxl（24），
// 现取是为了不把「当前没有被改写」当成永久前提。
function readTokenPx(name: string, fallback: number) {
  const raw = getComputedStyle(document.documentElement).getPropertyValue(name);
  const value = Number.parseFloat(raw);
  return Number.isFinite(value) ? value : fallback;
}

function getOuterHeight(dom: Element) {
  const computedStyle = window.getComputedStyle(dom);
  const px = (value: string) => Number.parseFloat(value) || 0;
  return (
    dom.clientHeight +
    px(computedStyle.marginTop) +
    px(computedStyle.marginBottom) +
    px(computedStyle.borderTopWidth) +
    px(computedStyle.borderBottomWidth)
  );
}

function calcHeight() {
  const iframe = unref(frameRef);
  if (!iframe) {
    return;
  }
  const { showFooter, isUseTabsRouter, showBreadcrumb } = settingStore;
  // 令牌缺失时按 theme.css 的默认值兜底（56px / 24px），避免 NaN 让 iframe 高度变 auto
  const headerHeight = readTokenPx('--td-comp-size-xxxl', 56);
  const navDom = document.querySelector('.t-tabs__nav');
  const navHeight = isUseTabsRouter && navDom ? getOuterHeight(navDom) : 0;
  const breadcrumbDom = document.querySelector('.t-breadcrumb');
  const breadcrumbHeight = showBreadcrumb && breadcrumbDom ? getOuterHeight(breadcrumbDom) : 0;
  const contentPadding = readTokenPx('--td-comp-paddingTB-xxl', 24) * 2;
  const footerDom = document.querySelector('.t-layout__footer');
  const footerHeight = showFooter && footerDom ? getOuterHeight(footerDom) : 0;
  const top = headerHeight + navHeight + breadcrumbHeight + contentPadding + footerHeight + 2;
  heightRef.value = window.innerHeight - top;
  iframe.style.height = `${document.documentElement.clientHeight - top}px`;
}

function hideLoading() {
  loading.value = false;
  calcHeight();
}

// 如果窗口大小发生变化
const debouncedCalcHeight = useDebounceFn(calcHeight, 250);

watch([width, height], debouncedCalcHeight);
watch(
  [() => settingStore.showFooter, () => settingStore.isUseTabsRouter, () => settingStore.showBreadcrumb],
  debouncedCalcHeight,
);
</script>
<style lang="less" scoped>
@prefix-cls: ~'@{starter-prefix}-iframe-page';

.@{prefix-cls} {
  &__mask {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
  }

  &__main {
    width: 100%;
    height: 100%;
    overflow: hidden;
    background-color: var(--td-bg-color-container);
    border: 0;
    box-sizing: border-box;
  }
}
</style>
