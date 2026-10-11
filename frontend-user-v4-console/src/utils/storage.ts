/**
 * 统一 localStorage 封装（FRONTEND.md：业务代码不直接读写 localStorage 键）
 *
 * 键名集中登记，避免裸字符串散落；所有方法自带 SSR/隐私模式防护。
 */
const KEYS = {
  /** 登录用户信息缓存（登出/401 时清理） */
  clientUser: 'client_user',
  /** 服务列表视图模式偏好（grid | list） */
  serviceViewMode: 'client-services-view-mode',
} as const;

export type StorageKey = (typeof KEYS)[keyof typeof KEYS];

export const localStorageKeys = KEYS;

export function readLocalStorage(key: StorageKey): string | null {
  if (typeof window === 'undefined') return null;
  try {
    return window.localStorage.getItem(key);
  } catch {
    return null;
  }
}

export function writeLocalStorage(key: StorageKey, value: string): void {
  if (typeof window === 'undefined') return;
  try {
    window.localStorage.setItem(key, value);
  } catch {
    // 隐私模式/配额溢出：静默失败，不影响主流程
  }
}

export function removeLocalStorage(key: StorageKey): void {
  if (typeof window === 'undefined') return;
  try {
    window.localStorage.removeItem(key);
  } catch {
    // 同上
  }
}
