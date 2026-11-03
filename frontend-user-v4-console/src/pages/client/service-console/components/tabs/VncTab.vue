<template>
  <section class="console-panel-section">
    <t-card title="VNC 控制台" :bordered="false">
      <div class="vnc-panel">
        <div class="vnc-toolbar">
          <div class="vnc-toolbar-status">
            <t-tag :theme="statusTheme" variant="light">{{ statusLabel }}</t-tag>
            <p>{{ statusHint }}</p>
          </div>
          <div class="vnc-toolbar-actions">
            <t-button class="vnc-action-primary" theme="primary" :loading="vncLoading" @click="handleOpenVnc()">
              {{ vncUrl ? '重新连接' : '连接 VNC' }}
            </t-button>
            <!-- 移动端用「全屏」替代新窗口：手机浏览器开新标签页难返回，且无全屏时体验差 -->
            <t-button
              class="vnc-action-window"
              variant="outline"
              :loading="vncLoading"
              @click="handleOpenVnc('window')"
            >
              新窗口打开
            </t-button>
            <t-button v-if="vncUrl" variant="outline" @click="toggleFullscreen">
              {{ isFullscreen ? '退出全屏' : '全屏' }}
            </t-button>
            <t-button v-if="vncUrl" variant="outline" theme="danger" @click="handleDisconnectVnc">断开</t-button>
          </div>
        </div>

        <div ref="shellRef" class="vnc-frame-shell" :class="{ 'is-pseudo-fullscreen': pseudoFullscreen }">
          <iframe
            v-if="vncUrl"
            :key="vncUrl"
            class="vnc-frame"
            :src="vncUrl"
            title="VNC 控制台"
            allow="clipboard-read; clipboard-write; fullscreen"
            referrerpolicy="no-referrer"
          />
          <div v-else class="vnc-empty">
            <t-icon name="desktop" size="3rem" />
            <span>点击「连接 VNC」开始远程桌面会话</span>
          </div>
          <!-- 全屏时工具栏不可见，提供悬浮退出入口（原生全屏同样生效，Esc 亦可退出） -->
          <button v-if="vncUrl && isFullscreen" type="button" class="vnc-fullscreen-exit" @click="toggleFullscreen">
            退出全屏
          </button>
        </div>
      </div>
    </t-card>
  </section>
</template>
<script setup lang="ts">
import { computed, onUnmounted, ref, watch } from 'vue';

import type { VncStatus } from '@/domains/services/console/useConsoleVnc';
import { resolveVncStatusLabel } from '@/domains/services/console/useConsoleVnc';

import { useServiceConsoleContext } from '../context';

const { vncUrl, vncStatus, vncStatusMessage, vncLoading, handleOpenVnc, handleDisconnectVnc } =
  useServiceConsoleContext();

const STATUS_THEMES: Record<VncStatus, 'default' | 'primary' | 'success' | 'warning' | 'danger'> = {
  idle: 'default',
  connecting: 'warning',
  connected: 'success',
  disconnected: 'default',
  error: 'danger',
};

const statusTheme = computed(() => STATUS_THEMES[vncStatus.value] || 'default');
const statusLabel = computed(() => resolveVncStatusLabel(vncStatus.value));

const statusHint = computed(() => {
  if (vncStatus.value === 'error' && vncStatusMessage.value) return vncStatusMessage.value;
  if (vncStatus.value === 'connected') return '远程桌面已连接，切到其他标签不会断开。';
  if (vncStatus.value === 'connecting') return '正在向后端申请一次性地址并连接远程桌面…';
  if (vncStatus.value === 'disconnected') return '连接已断开，点击「重新连接」重新申请地址。';
  return '点击连接后将向后端申请一次性 VNC 地址。';
});

// ---- 全屏 ----
// 原生 Fullscreen API 在 iPhone Safari 上不可用（仅 video 可全屏），
// 此时退化为固定定位的伪全屏，让手机端也能把远程桌面铺满屏幕。
const shellRef = ref<HTMLElement>();
const pseudoFullscreen = ref(false);
const nativeFullscreen = ref(false);
const isFullscreen = computed(() => nativeFullscreen.value || pseudoFullscreen.value);

function nativeFullscreenElement(): Element | null {
  return (
    document.fullscreenElement ||
    (document as Document & { webkitFullscreenElement?: Element }).webkitFullscreenElement ||
    null
  );
}

function exitNativeFullscreen() {
  const exit =
    document.exitFullscreen ||
    (document as Document & { webkitExitFullscreen?: () => Promise<void> }).webkitExitFullscreen;
  const result = exit?.call(document) as Promise<void> | undefined;
  // 退出被拒（非本组件的全屏、用户手势过期等）时不能抛未捕获异常
  if (result && typeof result.catch === 'function') result.catch(() => undefined);
}

function supportsNativeFullscreen(): boolean {
  const el = shellRef.value;
  if (!el) return false;
  return (
    Boolean(
      document.fullscreenEnabled ||
      (document as Document & { webkitFullscreenEnabled?: boolean }).webkitFullscreenEnabled,
    ) &&
    typeof (
      el.requestFullscreen ||
      (el as HTMLElement & { webkitRequestFullscreen?: () => Promise<void> }).webkitRequestFullscreen
    ) === 'function'
  );
}

function syncFullscreenState() {
  nativeFullscreen.value = nativeFullscreenElement() === shellRef.value;
  if (nativeFullscreen.value && pseudoFullscreen.value) setPseudoFullscreen(false);
}

/**
 * 伪全屏锁定页面滚动。
 * 本项目真正的滚动容器是 .tdesign-starter-layout（layout.less 有「仅锁 body 无效」的既有结论），
 * 因此给该容器挂锁定类，并保存/恢复 body 上可能存在的原内联 overflow。
 */
const SCROLL_LOCK_CLASS = 'vnc-pseudo-fullscreen-lock';
let originalBodyOverflow = '';

function setPseudoFullscreen(enabled: boolean) {
  if (enabled === pseudoFullscreen.value) return;
  pseudoFullscreen.value = enabled;
  const layout = document.querySelector('.tdesign-starter-layout');
  if (enabled) {
    originalBodyOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    layout?.classList.add(SCROLL_LOCK_CLASS);
    return;
  }
  document.body.style.overflow = originalBodyOverflow;
  originalBodyOverflow = '';
  layout?.classList.remove(SCROLL_LOCK_CLASS);
}

function toggleFullscreen() {
  if (!vncUrl.value) return;
  if (pseudoFullscreen.value) {
    setPseudoFullscreen(false);
    return;
  }
  if (nativeFullscreen.value) {
    exitNativeFullscreen();
    return;
  }
  // 别的元素（如内嵌 noVNC 页自己）正处于原生全屏：不能再抢着调 exitFullscreen，
  // 否则按钮文案是「全屏」却把查看器的全屏退掉了。
  if (nativeFullscreenElement()) return;
  const el = shellRef.value as (HTMLElement & { webkitRequestFullscreen?: () => Promise<void> }) | undefined;
  if (!el) return;
  if (supportsNativeFullscreen()) {
    const request = el.requestFullscreen || el.webkitRequestFullscreen;
    const result = request?.call(el) as Promise<void> | undefined;
    // 只有返回 Promise 时才能捕获异步拒绝；旧 WebKit 返回 undefined，
    // 失败经 fullscreenerror 事件上报，下面的事件监听负责回退。
    if (result && typeof result.catch === 'function') {
      result.catch(() => setPseudoFullscreen(true));
      return;
    }
  }
  setPseudoFullscreen(true);
}

function handleFullscreenChange() {
  syncFullscreenState();
}

// 原生全屏请求失败（旧内核走事件而非 Promise 拒绝）时回退伪全屏
function handleFullscreenError() {
  if (!nativeFullscreenElement()) setPseudoFullscreen(true);
}

document.addEventListener('fullscreenchange', handleFullscreenChange);
document.addEventListener('webkitfullscreenchange', handleFullscreenChange);
document.addEventListener('fullscreenerror', handleFullscreenError);
document.addEventListener('webkitfullscreenerror', handleFullscreenError);

function handleKeydown(event: KeyboardEvent) {
  if (event.key === 'Escape' && pseudoFullscreen.value) setPseudoFullscreen(false);
}
window.addEventListener('keydown', handleKeydown);

// 断开连接时收掉全屏，避免空 iframe 占据整屏。重连不再预清 vncUrl，由地址替换自然切换。
watch(vncUrl, (url) => {
  if (!url && isFullscreen.value) {
    if (pseudoFullscreen.value) setPseudoFullscreen(false);
    if (nativeFullscreen.value) exitNativeFullscreen();
  }
});

onUnmounted(() => {
  document.removeEventListener('fullscreenchange', handleFullscreenChange);
  document.removeEventListener('webkitfullscreenchange', handleFullscreenChange);
  document.removeEventListener('fullscreenerror', handleFullscreenError);
  document.removeEventListener('webkitfullscreenerror', handleFullscreenError);
  window.removeEventListener('keydown', handleKeydown);
  if (pseudoFullscreen.value) setPseudoFullscreen(false);
  // 原生全屏也要退出：否则离开页面后浏览器仍停留在全屏态
  if (nativeFullscreen.value) exitNativeFullscreen();
});
</script>
