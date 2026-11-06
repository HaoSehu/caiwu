<template>
  <!-- data-mobile-view：手机端主容器形态标记（detail），桌面端不渲染 -->
  <div
    class="legal-page"
    :data-mobile-view="isPhone ? 'detail' : undefined"
  >
    <div class="legal-card">
      <h1>{{ pageTitle }}</h1>
      <p>{{ pageDescription }}</p>

      <div class="legal-actions">
        <t-button v-if="targetUrl" theme="primary" @click="openDocument"
          >打开原文</t-button
        >
        <t-button @click="router.push('/')">返回首页</t-button>
      </div>

      <!-- role=status：文档地址来自异步配置，取到空值时需读屏播报 -->
      <t-empty
        v-if="!targetUrl"
        role="status"
        aria-live="polite"
        title="站点暂未配置该文档地址"
        :image-style="{ fontSize: '72px' }"
      />
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from "vue";
import { useRoute, useRouter } from "vue-router";
import { MessagePlugin } from "tdesign-vue-next/es/message/plugin";
import { useAppStore } from "@/app/stores/app";
import { usePhoneBreakpoint } from "@/composables/useMobileBreakpoint";

const route = useRoute();
const router = useRouter();
const appStore = useAppStore();
// 手机形态标记：与 $breakpoint-mobile-max 同口径
const { isMobile: isPhone } = usePhoneBreakpoint();

const documentKey = computed(() => String(route.meta?.documentKey || "terms"));
const isPrivacy = computed(() => documentKey.value === "privacy");
const targetUrl = computed(() =>
  isPrivacy.value ? appStore.privacyUrl : appStore.termsUrl,
);
const pageTitle = computed(() => (isPrivacy.value ? "隐私政策" : "服务条款"));
const pageDescription = computed(() =>
  isPrivacy.value
    ? "查看平台的隐私政策与个人信息处理说明。"
    : "查看平台的服务条款、使用约定与相关说明。",
);

function openDocument() {
  if (!targetUrl.value) {
    return;
  }

  // 防 javascript:/vbscript:/file: 等协议注入：仅允许 http/https 打开外部文档
  try {
    const parsed = new URL(targetUrl.value);
    if (!["http:", "https:"].includes(parsed.protocol)) {
      MessagePlugin.warning("文档地址配置无效");
      return;
    }
  } catch {
    MessagePlugin.warning("文档地址配置无效");
    return;
  }

  // 浏览器可能拦截新窗口：取返回值判空，为空时提示用户放行后重试。
  // 注意不能在这里传 noopener/noreferrer 特性——按 HTML 规范这两种特性会让 window.open 恒定返回 null，
  // 导致每次点击都误报「被拦截」；改为打开后手动切断 opener 引用，达到同样的隔离效果。
  const opened = window.open(targetUrl.value, "_blank");
  if (!opened) {
    MessagePlugin.warning("浏览器已拦截新窗口，请允许本站打开新窗口后重试");
    return;
  }
  opened.opener = null;
}
</script>

<style scoped lang="scss">
.legal-page {
  min-height: 60vh;
  display: grid;
  place-items: center;
  padding: 56px 20px;
  background: $bg-color;
}

.legal-card {
  width: min(760px, 100%);
  padding: 36px 32px;
  border: 1px solid $border-color;
  border-radius: 20px;
  background: $bg-color-card;
  box-shadow: 0 24px 48px rgba(15, 23, 42, 0.08);
}

.legal-card h1 {
  margin: 0;
  color: $text-color-primary;
  font-size: 28px;
}

.legal-card p {
  margin: 16px 0 0;
  color: $text-color-secondary;
  line-height: 1.8;
}

.legal-actions {
  display: flex;
  gap: 12px;
  margin: 24px 0;
}

@media (max-width: $breakpoint-mobile-max) {
  .legal-page {
    padding: 32px 12px;
  }

  .legal-card {
    padding: 20px 16px;
    border-radius: 14px;
  }

  .legal-card h1 {
    font-size: 24px;
  }

  // 手机端两个操作按钮整行等分，避免并排时各剩不到 100px
  .legal-actions {
    flex-direction: column;
    align-items: stretch;
    gap: 10px;
  }
}
</style>
