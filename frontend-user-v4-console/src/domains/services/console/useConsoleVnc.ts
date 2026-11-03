import { MessagePlugin } from 'tdesign-vue-next/es/message/plugin';
import { onUnmounted, ref } from 'vue';

import clientApi from '@/api/client';
import type { ServiceVncCredentials } from '@/types/client';
import { getErrorMessage } from '@/utils/error';

export interface UseConsoleVncOptions {
  serviceId: { value: number };
  vncLoading: { value: boolean };
  activeTab: { value: string };
}

/** 外层展示的连接状态：与内嵌 noVNC 页回传的状态一一对应。 */
export type VncStatus = 'idle' | 'connecting' | 'connected' | 'disconnected' | 'error';

const VNC_STATUS_LABELS: Record<VncStatus, string> = {
  idle: '未连接',
  connecting: '连接中',
  connected: '已连接',
  disconnected: '已断开',
  error: '连接失败',
};

export function resolveVncStatusLabel(status: VncStatus): string {
  return VNC_STATUS_LABELS[status] || VNC_STATUS_LABELS.idle;
}

/** 查看器回传消息的协议标记，与 public/vnc/vnc.html 的 notifyParent 保持一致。 */
const VNC_MESSAGE_SOURCE = 'caiwu-vnc';

function isVncStatus(value: unknown): value is VncStatus {
  // 必须查自有属性：`in` 会把 toString / constructor / __proto__ 等原型链键判为合法。
  // 用 hasOwnProperty.call 而非 Object.hasOwn：后者要 Chrome 93 / Safari 15.4，
  // 超出仓库声明的浏览器基线（chrome>=84 / safari>=13.4，见 vite.config.ts 的 build.target 注释）。
  return typeof value === 'string' && Object.prototype.hasOwnProperty.call(VNC_STATUS_LABELS, value);
}

export function useConsoleVnc(options: UseConsoleVncOptions) {
  const { serviceId, vncLoading, activeTab } = options;

  const vncUrl = ref('');
  const vncStatus = ref<VncStatus>('idle');
  const vncStatusMessage = ref('');

  function decorateVncUrl(rawUrl: unknown): string {
    const url = String(rawUrl || '').trim();
    if (!url) return '';
    try {
      const target = new URL(url, window.location.origin);
      target.searchParams.set('service_id', String(serviceId.value));
      // 查看器据此回传状态时锁定父窗口域：控制台可能经别名域/预览域访问，
      // 用后端配置的 CLIENT_CONSOLE_URL 会与当前 origin 不一致而丢失消息。
      target.searchParams.set('parent_origin', window.location.origin);
      return target.toString();
    } catch {
      return url;
    }
  }

  function encodeVncCredentials(credentials: ServiceVncCredentials): string {
    const bytes = new TextEncoder().encode(JSON.stringify(credentials));
    let binary = '';
    bytes.forEach((byte) => {
      binary += String.fromCharCode(byte);
    });
    return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
  }

  function normalizeVncCredentials(payload: unknown): ServiceVncCredentials | null {
    if (!payload || typeof payload !== 'object') return null;
    const source = payload as ServiceVncCredentials;
    const credentials: ServiceVncCredentials = {};
    const username = String(source.username || '').trim();
    const target = String(source.target || '').trim();
    const password = String(source.password || '').trim();

    if (username) credentials.username = username;
    if (target) credentials.target = target;
    if (password) credentials.password = password;

    return Object.keys(credentials).length > 0 ? credentials : null;
  }

  // 凭据经 URL fragment 随链接进入 noVNC 查看器页面（fragment 不发服务器），
  // 查看器读取后立即清除，不写 sessionStorage，避免会话级滞留被 XSS 读取。
  function attachVncCredentialsToFragment(rawUrl: string, payload: unknown): string {
    const credentials = normalizeVncCredentials(payload);
    if (!credentials) return rawUrl;

    try {
      const target = new URL(rawUrl, window.location.origin);
      target.hash = `vnc_auth=${encodeVncCredentials(credentials)}`;
      return target.toString();
    } catch {
      return rawUrl;
    }
  }

  async function requestVncUrl(): Promise<string> {
    const res = await clientApi.serviceVnc(serviceId.value, { silentError: true });
    const url = String(res.data?.url || '').trim();
    if (!url) throw new Error('未获取到可用的 VNC 地址');
    return attachVncCredentialsToFragment(decorateVncUrl(url), res.data?.vnc_credentials);
  }

  function resetVncState() {
    vncUrl.value = '';
    vncStatus.value = 'idle';
    vncStatusMessage.value = '';
  }

  /**
   * 内嵌页连接状态回传。
   * 只接受 VNC 查看器页面的消息：校验 data.source 标记、status 白名单与 event.origin
   * （必须与 iframe 地址同源），避免任意页面伪造 VNC 状态。
   */
  function handleVncMessage(event: MessageEvent) {
    const data = event.data as { source?: string; status?: unknown; message?: unknown } | null;
    if (!data || data.source !== VNC_MESSAGE_SOURCE) return;
    if (!vncUrl.value) return;
    try {
      if (new URL(vncUrl.value, window.location.origin).origin !== event.origin) return;
    } catch {
      return;
    }

    const status = data.status;
    if (!isVncStatus(status)) return;
    vncStatus.value = status;
    vncStatusMessage.value = status === 'error' ? String(data.message || '').trim() : '';
  }

  if (typeof window !== 'undefined') {
    window.addEventListener('message', handleVncMessage);
    onUnmounted(() => {
      window.removeEventListener('message', handleVncMessage);
    });
  }

  async function handleOpenVnc(mode: 'embed' | 'window' = 'embed') {
    const popup = mode === 'window' ? window.open('about:blank', '_blank') : null;
    if (mode === 'window' && !popup) {
      MessagePlugin.warning('浏览器已拦截新窗口，请允许新窗口后重试');
      return;
    }

    if (popup) {
      popup.opener = null;
    }

    vncLoading.value = true;
    // 重连不预先销毁旧 iframe：先拿到新地址再整体替换。取地址失败时（网络抖动、后端 422）
    // 既有会话继续可用，不会被一次失败的重连无条件掐断。
    const previousStatus = vncStatus.value;
    const previousStatusMessage = vncStatusMessage.value;
    // 两种模式都先进入「连接中」：新窗口也清掉上一次嵌入连接留下的失败提示，
    // 避免用户误以为新窗口同样失败。
    vncStatus.value = 'connecting';
    vncStatusMessage.value = '';
    try {
      const url = await requestVncUrl();
      activeTab.value = 'vnc';
      if (mode === 'window' && popup) {
        popup.location.replace(url);
        // 新窗口不在父页面内，状态无法回传；恢复嵌入会话的状态展示，避免与新窗口混淆
        vncStatus.value = previousStatus;
        vncStatusMessage.value = previousStatusMessage;
        MessagePlugin.success('VNC 新窗口已打开');
      } else {
        vncUrl.value = url;
        MessagePlugin.success('VNC 控制台已载入');
      }
    } catch (error: unknown) {
      if (popup) popup.close();
      if (vncUrl.value) {
        // 旧会话仍在（vncUrl 未被清空）时恢复原状态；没有旧会话则回到未连接
        vncStatus.value = previousStatus;
        vncStatusMessage.value = previousStatusMessage;
      } else {
        resetVncState();
      }
      MessagePlugin.error(getErrorMessage(error, '获取 VNC 地址失败'));
    } finally {
      vncLoading.value = false;
    }
  }

  /** 断开当前内嵌连接：移除 iframe 即断开 WebSocket，重新连接需再次申请一次性地址。 */
  function handleDisconnectVnc(silent = false) {
    if (!vncUrl.value) return;
    resetVncState();
    if (!silent) MessagePlugin.success('已断开 VNC 连接');
  }

  return {
    vncUrl,
    vncStatus,
    vncStatusMessage,
    handleOpenVnc,
    handleDisconnectVnc,
  };
}
