import { MessagePlugin } from 'tdesign-vue-next/es/message/plugin';
import type { ComputedRef } from 'vue';
import { onBeforeUnmount, ref } from 'vue';

import { userApi } from '@/api/user';
import { errorMessage } from '@/utils/userMessage';

/** 客户端代登录握手用的 postMessage 协议常量 */
const LOGIN_AS_READY_EVENT = 'caiwu:login-as-ready';
const LOGIN_AS_CODE_EVENT = 'caiwu:login-as-code';
const LOGIN_AS_READY_TIMEOUT_MS = 10000;

export interface LoginAsOptions {
  userId: ComputedRef<string>;
  canLoginAs: ComputedRef<boolean>;
}

/**
 * 管理员代登录客户端：打开弹窗、注入登录凭证并等待客户端就绪回执。
 * 逻辑与页面内实现保持一致，仅把用户 ID 与权限改为注入。
 */
export function useLoginAs(options: LoginAsOptions) {
  const loginAsLoading = ref(false);

  async function handleLoginAs() {
    if (!options.canLoginAs.value) {
      MessagePlugin.error('当前账号没有代登录权限');
      return;
    }

    const popup = window.open('about:blank', '_blank');
    if (!popup) {
      MessagePlugin.error('浏览器拦截了代登录窗口，请允许弹窗后重试');
      return;
    }

    loginAsLoading.value = true;
    try {
      const response = await userApi.loginAs(options.userId.value);
      const code = String(response.login_code || '').trim();
      if (!code) {
        closeLoginAsPopup(popup);
        MessagePlugin.error('未获取到代登录凭证');
        return;
      }

      const target = resolveLoginAsTarget(response.target_url);
      if (!target) {
        closeLoginAsPopup(popup);
        MessagePlugin.error('客户端代登录地址无效，请检查客户端控制台配置');
        return;
      }

      popup.location.replace(target);
      await waitForLoginAsReady(popup, target);
      popup.postMessage({ type: LOGIN_AS_CODE_EVENT, code }, new URL(target, window.location.origin).origin);
      MessagePlugin.success('已打开客户端登录页');
    } catch (error) {
      closeLoginAsPopup(popup);
      MessagePlugin.error(errorMessage(error, '代登录失败'));
    } finally {
      loginAsLoading.value = false;
    }
  }

  function closeLoginAsPopup(popup: Window) {
    if (!popup.closed) {
      popup.close();
    }
  }

  function resolveLoginAsTarget(targetUrl: string | undefined) {
    const rawTarget = String(targetUrl || '').trim();
    if (!rawTarget) return '';

    try {
      const target = new URL(rawTarget, window.location.origin);
      if (!['http:', 'https:'].includes(target.protocol)) {
        return '';
      }
      target.pathname = '/client/login-as';
      target.search = '';
      target.hash = '';
      return target.toString();
    } catch {
      return '';
    }
  }

  function waitForLoginAsReady(targetWindow: Window, targetUrl: string) {
    const targetOrigin = new URL(targetUrl, window.location.origin).origin;

    return new Promise<void>((resolve, reject) => {
      let settled = false;
      const cleanup = () => {
        window.clearTimeout(timer);
        window.removeEventListener('message', handleMessage);
      };
      const settle = (fn: () => void) => {
        if (settled) return;
        settled = true;
        cleanup();
        fn();
      };

      const timer = window.setTimeout(() => {
        settle(() => reject(new Error('客户端代登录窗口未完成初始化')));
      }, LOGIN_AS_READY_TIMEOUT_MS);

      function handleMessage(event: MessageEvent) {
        if (event.source !== targetWindow || event.origin !== targetOrigin) {
          return;
        }

        if (event.data?.type !== LOGIN_AS_READY_EVENT) {
          return;
        }

        settle(resolve);
      }

      window.addEventListener('message', handleMessage);

      // 组件在等待期间卸载时立即摘除监听与定时器，避免悬挂监听；
      // 同时 reject 让调用方走既有的失败分支，不会对已关闭的窗口继续投递消息。
      onBeforeUnmount(() => {
        settle(() => reject(new Error('页面已离开，代登录流程已取消')));
      });
    });
  }
  return { loginAsLoading, handleLoginAs };
}
