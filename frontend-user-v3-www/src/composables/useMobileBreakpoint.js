import { onBeforeUnmount, onMounted, ref } from "vue";
import {
  MOBILE_BREAKPOINT,
  PHONE_BREAKPOINT_MAX,
} from "@/constants/breakpoints";

/** 把断点常量拼成移动端媒体查询串：只有这里做断点→CSS 查询的转换，避免各处拼写漂移。 */
export function buildMobileMediaQuery(breakpoint = MOBILE_BREAKPOINT) {
  return `(max-width: ${breakpoint}px)`;
}

/**
 * 读取当前视口是否为移动端。
 * 无 window（SSR）或不支持 matchMedia 时返回 false，调用方无需再判环境。
 */
export function matchesMobileViewport(breakpoint = MOBILE_BREAKPOINT) {
  if (
    typeof window === "undefined" ||
    typeof window.matchMedia !== "function"
  ) {
    return false;
  }

  return window.matchMedia(buildMobileMediaQuery(breakpoint)).matches;
}

/**
 * 全站统一的移动端断点状态。
 *
 * 商品列表、商品详情与站点头部原先各自用 innerWidth / matchMedia 判断点，
 * 三处判定口径与监听方式都不同，改断点时容易漏改。这里统一为同一份
 * matchMedia 查询：断点取值来自 constants/breakpoints，监听由本 composable
 * 注册与移除，调用方只消费 isMobile。
 *
 * listen 为 false 时不注册监听，只提供状态与 updateMobileState()，
 * 供已经有自己的 resize 生命周期（如站点头部、商品目录）的调用方复用同一判定。
 */
export function useMobileBreakpoint({
  breakpoint = MOBILE_BREAKPOINT,
  listen = true,
} = {}) {
  // 声明时即读取初始值：避免移动端首帧先渲染桌面分支再切移动分支的一帧闪变
  const isMobile = ref(matchesMobileViewport(breakpoint));

  let mediaQuery = null;
  let mediaListener = null;

  /** 主动同步一次（供 resize 兜底调用）；状态未变化时不触发重渲染。 */
  function updateMobileState() {
    const nextMobile = matchesMobileViewport(breakpoint);
    if (nextMobile === isMobile.value) return;
    isMobile.value = nextMobile;
  }

  function startMobileBreakpoint() {
    if (!listen || mediaQuery) return;
    if (
      typeof window === "undefined" ||
      typeof window.matchMedia !== "function"
    ) {
      return;
    }

    // 实例必须复用：removeEventListener/removeListener 只对同一实例生效
    mediaQuery = window.matchMedia(buildMobileMediaQuery(breakpoint));
    mediaListener = (event) => {
      isMobile.value = event.matches;
    };
    if (typeof mediaQuery.addEventListener === "function") {
      mediaQuery.addEventListener("change", mediaListener);
    } else if (typeof mediaQuery.addListener === "function") {
      // 旧版 Safari 只有 addListener/removeListener
      mediaQuery.addListener(mediaListener);
    }
  }

  function disposeMobileBreakpoint() {
    if (!mediaQuery || !mediaListener) return;
    if (typeof mediaQuery.removeEventListener === "function") {
      mediaQuery.removeEventListener("change", mediaListener);
    } else if (typeof mediaQuery.removeListener === "function") {
      mediaQuery.removeListener(mediaListener);
    }
    mediaQuery = null;
    mediaListener = null;
  }

  if (listen) {
    onMounted(() => {
      // onMounted 再同步一次：脚本执行到挂载之间视口可能已变化
      updateMobileState();
      startMobileBreakpoint();
    });
    onBeforeUnmount(disposeMobileBreakpoint);
  }

  return {
    isMobile,
    updateMobileState,
    startMobileBreakpoint,
    disposeMobileBreakpoint,
  };
}

/**
 * 手机形态（≤767.98px）判定，与 `$breakpoint-mobile-max` 同口径。
 *
 * 与 useMobileBreakpoint 分开的原因：992 判定决定的是「导航抽屉 / 商品目录侧栏」
 * 这类布局换轨，768 判定决定的是「列表是否改卡片、详情字段是否单列」这类页面形态。
 * 平板竖屏（768~992）沿用桌面形态，因此页面形态标记必须用这一个。
 */
export function usePhoneBreakpoint({ listen = true } = {}) {
  return useMobileBreakpoint({ breakpoint: PHONE_BREAKPOINT_MAX, listen });
}
