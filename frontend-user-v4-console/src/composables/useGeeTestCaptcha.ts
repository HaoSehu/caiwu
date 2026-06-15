import { onBeforeUnmount, onMounted, ref } from 'vue';

import { clientAuthApi } from '@/api/auth';

interface GeeTestConfig {
  enabled: boolean;
  captcha_id: string;
  script_url?: string;
}

interface GeeTestInstance {
  onReady: (callback: () => void) => void;
  onSuccess: (callback: () => void) => void;
  onError: (callback: (error: unknown) => void) => void;
  onClose?: (callback: () => void) => void;
  showCaptcha: () => void;
  getValidate: () => unknown;
  reset?: () => void;
  destroy?: () => void;
}

declare global {
  interface Window {
    initGeetest4?: (
      options: Record<string, unknown>,
      callback: (instance: GeeTestInstance) => void,
    ) => void;
  }
}

let captchaConfigPromise: Promise<GeeTestConfig> | null = null;
let geetestScriptPromise: Promise<typeof window.initGeetest4> | null = null;

const defaultConfig: GeeTestConfig = {
  enabled: false,
  captcha_id: '',
  script_url: 'https://static.geetest.com/v4/gt4.js',
};

async function getCaptchaConfig() {
  if (!captchaConfigPromise) {
    captchaConfigPromise = clientAuthApi
      .captchaConfig()
      .then((response: any) => response.data || defaultConfig)
      .catch(() => defaultConfig);
  }

  return captchaConfigPromise;
}

function loadGeeTestScript(src: string) {
  if (typeof window === 'undefined') {
    throw new Error('浏览器环境不可用');
  }

  if (window.initGeetest4) {
    return Promise.resolve(window.initGeetest4);
  }

  if (!geetestScriptPromise) {
    geetestScriptPromise = new Promise((resolve, reject) => {
      const existing = document.querySelector<HTMLScriptElement>('script[data-geetest-script="gt4"]');
      if (existing) {
        existing.addEventListener('load', () => resolve(window.initGeetest4), { once: true });
        existing.addEventListener('error', () => reject(new Error('GeeTest 脚本加载失败')), { once: true });
        return;
      }

      const script = document.createElement('script');
      script.src = src;
      script.async = true;
      script.defer = true;
      script.dataset.geetestScript = 'gt4';
      script.onload = () => resolve(window.initGeetest4);
      script.onerror = () => reject(new Error('GeeTest 脚本加载失败'));
      document.head.appendChild(script);
    });
  }

  return geetestScriptPromise;
}

export function useGeeTestCaptcha(options: Record<string, unknown> = {}) {
  const loading = ref(false);
  const ready = ref(false);
  const enabled = ref(false);
  const initialized = ref(false);

  let captchaObj: GeeTestInstance | null = null;
  let initPromise: Promise<GeeTestInstance | null> | null = null;
  let pendingResolver: ((value: unknown) => void) | null = null;
  let pendingRejecter: ((error: Error) => void) | null = null;
  let componentUnmounted = false;

  const clearPending = () => {
    pendingResolver = null;
    pendingRejecter = null;
    loading.value = false;
  };

  const rejectPending = (error: Error) => {
    pendingRejecter?.(error);
    clearPending();
  };

  const initCaptcha = async () => {
    if (componentUnmounted) {
      return null;
    }

    const config = await getCaptchaConfig();
    enabled.value = Boolean(config.enabled && config.captcha_id);
    initialized.value = true;

    if (!enabled.value) {
      return null;
    }

    if (captchaObj) {
      return captchaObj;
    }

    if (initPromise) {
      return initPromise;
    }

    const initGeetest4 = await loadGeeTestScript(config.script_url || defaultConfig.script_url || '');
    initPromise = new Promise((resolve, reject) => {
      try {
        initGeetest4?.(
          {
            captchaId: config.captcha_id,
            product: 'bind',
            language: 'zho',
            ...options,
          },
          (instance) => {
            captchaObj = instance;
            instance.onReady(() => {
              ready.value = true;
              resolve(instance);
            });
            instance.onSuccess(() => {
              const result = instance.getValidate();
              instance.reset?.();
              pendingResolver?.(result);
              clearPending();
            });
            instance.onError((error) => {
              rejectPending(error instanceof Error ? error : new Error('行为验证失败，请重试'));
            });
            instance.onClose?.(() => {
              rejectPending(new Error('请先完成行为验证'));
            });
          },
        );
      } catch (error) {
        reject(error);
      }
    });

    return initPromise;
  };

  const verify = async ({ required = false } = {}) => {
    const instance = await initCaptcha();
    if (!enabled.value) {
      if (required) {
        throw new Error('行为验证当前不可用，请稍后重试');
      }
      return null;
    }

    if (!instance || !ready.value) {
      throw new Error('行为验证组件初始化中，请稍后重试');
    }

    loading.value = true;

    return new Promise((resolve, reject) => {
      pendingResolver = resolve;
      pendingRejecter = reject;

      try {
        instance.showCaptcha();
      } catch (error) {
        rejectPending(error instanceof Error ? error : new Error('行为验证打开失败'));
      }
    });
  };

  const runWithCaptcha = async <T>(callback: (captcha: unknown) => Promise<T>, options = {}) => {
    const captcha = await verify(options);
    return callback(captcha);
  };

  onMounted(() => {
    initCaptcha().catch(() => {
      initialized.value = true;
    });
  });

  onBeforeUnmount(() => {
    componentUnmounted = true;
    rejectPending(new Error('行为验证已取消'));
    captchaObj?.destroy?.();
  });

  return {
    enabled,
    initialized,
    loading,
    ready,
    verify,
    runWithCaptcha,
  };
}
