import { defineStore } from 'pinia';
import { computed, ref } from 'vue';

/**
 * 账户策略（管理端开关）的前端副本。
 *
 * 数据源是 `GET /v2/client/auth/info` 的 `account_policy` 字段：由用户 store 在
 * `normalizeUserInfo` 时 hydrate 进来。不要写进 site-branding（那个 store 只放品牌与外观），
 * 也不要在页面里各自读 userInfo 再判一遍，开关口径只在这里归一。
 */
export const useAccountPolicyStore = defineStore('account-policy', () => {
  const requireVerification = ref(false);
  const requirePhone = ref(false);
  const requireEmail = ref(false);

  const enabled = computed(() => requireVerification.value || requirePhone.value || requireEmail.value);

  function hydrate(raw: unknown) {
    const policy = (raw && typeof raw === 'object' ? raw : {}) as Record<string, unknown>;
    // 后端下发的是布尔；兼容 0/1 与 '1'，避免旧缓存或代理改写导致恒 false。
    const toBool = (value: unknown) => value === true || value === 1 || value === '1' || value === 'true';
    requireVerification.value = toBool(policy.require_verification);
    requirePhone.value = toBool(policy.require_phone);
    requireEmail.value = toBool(policy.require_email);
  }

  function reset() {
    hydrate(null);
  }

  return {
    requireVerification,
    requirePhone,
    requireEmail,
    enabled,
    hydrate,
    reset,
  };
});
