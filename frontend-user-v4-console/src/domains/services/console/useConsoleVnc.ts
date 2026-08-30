import { MessagePlugin } from 'tdesign-vue-next';
import { ref } from 'vue';

import clientApi from '@/api/client';
import type { ServiceVncCredentials } from '@/types/client';

import { resolveErrorMessage } from './useConsoleCore';

export interface UseConsoleVncOptions {
  serviceId: { value: number };
  actionLoading: { value: boolean };
  activeTab: { value: string };
}

export function useConsoleVnc(options: UseConsoleVncOptions) {
  const { serviceId, actionLoading, activeTab } = options;

  const vncUrl = ref('');

  function decorateVncUrl(rawUrl: unknown): string {
    const url = String(rawUrl || '').trim();
    if (!url) return '';
    try {
      const target = new URL(url, window.location.origin);
      target.searchParams.set('service_id', String(serviceId.value));
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

  async function handleOpenVnc(mode: 'embed' | 'window' = 'embed') {
    const popup = mode === 'window' ? window.open('about:blank', '_blank') : null;
    if (mode === 'window' && !popup) {
      MessagePlugin.warning('浏览器已拦截新窗口，请允许弹窗后重试');
      return;
    }

    if (popup) {
      popup.opener = null;
    }

    actionLoading.value = true;
    try {
      const url = await requestVncUrl();
      activeTab.value = 'vnc';
      if (mode === 'window' && popup) {
        popup.location.replace(url);
        MessagePlugin.success('VNC 新窗口已打开');
      } else {
        vncUrl.value = url;
        MessagePlugin.success('VNC 控制台已载入');
      }
    } catch (error: unknown) {
      if (popup) popup.close();
      MessagePlugin.error(resolveErrorMessage(error, '获取 VNC 地址失败'));
    } finally {
      actionLoading.value = false;
    }
  }

  return {
    vncUrl,
    handleOpenVnc,
  };
}
