import { createApp } from "vue";
import { createPinia } from "pinia";

// TDesign 组件按需引入（见 vite.config.js 的 createTDesignResolver）：模板里的 <t-xxx>
// 解析到 tdesign-vue-next/es/<组件>/index，子入口自带 import './style/css.mjs'。
// 因此这里只需要补基础变量与函数式插件样式，顺序必须是：
//   基础样式 → 站点设计令牌 → 函数式插件样式 → 站点全局样式。
// 函数式插件（src/app/http/request.ts 的 MessagePlugin、商品域的 DialogPlugin）的入口
// 不带样式，必须显式补 message/dialog 的 css.mjs，否则消息与二次确认框没有样式。
import "tdesign-vue-next/es/style/index.css";
import "../../../theme.css";
import "tdesign-vue-next/es/message/style/css.mjs";
import "tdesign-vue-next/es/dialog/style/css.mjs";

import App from "@/App.vue";
import { createClientRouter } from "@/app/router";
import {
  initClientRuntimeConnectionHints,
  primeClientConnectionHints,
} from "@/app/runtime/network";
import { initSessionActivityTracking } from "@/utils/auth";
import { useAppStore } from "@/app/stores/app";
import "@/assets/styles/global.scss";

const SITE_CONFIG_PREFETCH_TIMEOUT = 1200;

function isHomeRoute(pathname: string) {
  return pathname === "/" || pathname === "";
}

async function preloadSiteConfig(appStore: ReturnType<typeof useAppStore>) {
  if (typeof window === "undefined" || isHomeRoute(window.location.pathname)) {
    return false;
  }

  const timeoutPromise = new Promise<void>((resolve) => {
    window.setTimeout(resolve, SITE_CONFIG_PREFETCH_TIMEOUT);
  });

  const result = await Promise.race([
    appStore.fetchSiteConfig().catch(() => undefined),
    timeoutPromise,
  ]);

  return Boolean(result);
}

export function bootstrapClientApp() {
  const app = createApp(App);
  const pinia = createPinia();
  const router = createClientRouter();

  app.use(pinia);
  // TDesign 的默认全局配置已合并 zh_CN（es/config-provider/utils/context.mjs），
  // 与 admin/console 一样不再 provideGlobalConfig，省掉一层 ConfigProvider 上下文。
  app.use(router);

  initClientRuntimeConnectionHints({
    apiBaseUrl: import.meta.env.VITE_API_BASE_URL,
  });
  initSessionActivityTracking();

  const appStore = useAppStore();
  const splash = document.getElementById("app-splash");

  const hideSplash = () => {
    if (!splash) return;
    splash.classList.add("fade-out");
    splash.addEventListener("transitionend", () => splash.remove(), {
      once: true,
    });
    setTimeout(() => splash.remove(), 400);
  };

  // 性能：先挂载应用再并行预热站点配置，避免非首页入口（SEO 落地页/products/help）
  // 的首屏被 config 预热（最多 1200ms）串行阻塞，FCP/LCP 直接受影响。
  app.mount("#app");
  hideSplash();

  const primeHints = () => {
    primeClientConnectionHints({
      urls: [appStore.siteLogo, appStore.siteFavicon],
    });
  };

  // 首页 config 虽内嵌在 /v2/site/home 响应中，但仍需提前并行拉取，
  // 使首屏标题/图标尽快替换硬编码的默认品牌（preloadSiteConfig 对首页立即返回，不阻塞首屏）。
  void preloadSiteConfig(appStore).then((preloaded) => {
    if (preloaded) {
      primeHints();
      return;
    }
    void appStore.fetchSiteConfig().finally(primeHints);
  });
}
