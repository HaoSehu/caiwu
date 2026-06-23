import { computed, onMounted, reactive, ref } from 'vue';
import { MessagePlugin } from 'tdesign-vue-next';
import { useRouter } from 'vue-router';

import { clientAuthApi } from '@/api/auth';
import { copyText as copyShared } from '@/utils/format';
import { useUserStore } from '@/store';
import type { ClientNotificationPreferences, ClientUserInfo } from '@/types/client';

type TagTheme = 'default' | 'success' | 'warning' | 'primary' | 'danger';
type ProfileTab = 'profile' | 'security' | 'agent' | 'notification';
type NotificationKey = keyof ClientNotificationPreferences;
type NotificationItem = {
  key: NotificationKey;
  name: string;
  desc: string;
  enabled: boolean;
};

const PROFILE_TABS = new Set<ProfileTab>(['profile', 'security', 'agent', 'notification']);

function getErrorMessage(error: unknown, fallback: string) {
  return error instanceof Error && error.message ? error.message : fallback;
}

export function useProfile() {
  const router = useRouter();
  const userStore = useUserStore();
  const activeTab = ref<ProfileTab>('profile');
  const profileLoading = ref(false);
  const passwordDialogVisible = ref(false);
  const notificationLoading = ref(false);
  const profileForm = reactive({
    id: '',
    email: '',
    nickname: '',
    phone: '',
    cash_balance: '0.00',
    createdAt: '',
    is_verified: 0,
    real_name: '',
    id_card_masked: '',
  });
  const passwordForm = reactive({ oldPassword: '', newPassword: '', confirmPassword: '' });
  const notificationList = reactive<NotificationItem[]>([
    { key: 'login_notify', name: '账号登录提醒', desc: '每次账户成功登录后，向绑定邮箱发送登录安全提醒。', enabled: false },
    { key: 'login_location_alert', name: '异地登录提醒', desc: '检测到新的登录 IP 环境时，额外发送一次异地登录风险提醒。', enabled: false },
    { key: 'password_change_alert', name: '更改密码提醒', desc: '账户密码修改成功后，立即发送安全提醒邮件。', enabled: false },
    { key: 'phone_change_alert', name: '更改手机号提醒', desc: '安全手机号发生变更时，及时发送变更提醒。', enabled: false },
    { key: 'email_change_alert', name: '更改邮箱提醒', desc: '安全邮箱发生变更时，向原邮箱和新邮箱发送提醒。', enabled: false },
    { key: 'marketing_alert', name: '营销提醒接收', desc: '接收产品更新、活动优惠和运营消息。', enabled: false },
  ]);

  const balanceText = computed(() => `¥${profileForm.cash_balance || '0.00'}`);
  const enabledNotificationCount = computed(() => notificationList.filter((item) => item.enabled).length);
  const securityItems = computed(() => [
    {
      key: 'verification',
      name: '实名认证',
      desc: profileForm.real_name
        ? `${profileForm.real_name}${profileForm.id_card_masked ? ` · ${profileForm.id_card_masked}` : ''}`
        : '完成实名认证后，可提升账户可信度与业务可用范围',
      theme: (profileForm.is_verified ? 'success' : 'warning') as TagTheme,
      tag: profileForm.is_verified ? '已完成' : '待处理',
      actionLabel: profileForm.is_verified ? '查看认证' : '立即认证',
      action: () => router.push('/client/verification'),
    },
    {
      key: 'phone',
      name: '安全手机',
      desc: profileForm.phone || '绑定手机号后，可用于验证码接收和安全校验',
      theme: (profileForm.phone ? 'success' : 'warning') as TagTheme,
      tag: profileForm.phone ? '已绑定' : '未绑定',
      actionLabel: '前往绑定',
      action: () => MessagePlugin.info('手机绑定请在安全验证弹窗中完成'),
    },
    {
      key: 'email',
      name: '安全邮箱',
      desc: profileForm.email || '建议绑定常用邮箱，用于接收通知与安全提醒',
      theme: (profileForm.email ? 'success' : 'warning') as TagTheme,
      tag: profileForm.email ? '已绑定' : '未绑定',
      actionLabel: '前往绑定',
      action: () => MessagePlugin.info('邮箱绑定请在安全验证弹窗中完成'),
    },
    {
      key: 'password',
      name: '登录密码',
      desc: '建议定期更新密码，并避免与其他平台共用同一组凭证',
      theme: 'success' as TagTheme,
      tag: '已设置',
      actionLabel: '修改密码',
      action: () => {
        passwordDialogVisible.value = true;
      },
    },
  ]);

  function hydrateProfile(info: ClientUserInfo = {}) {
    profileForm.id = String(info.id || '');
    profileForm.email = String(info.email || '');
    profileForm.nickname = String(info.nickname || info.name || '');
    profileForm.phone = String(info.phone || '');
    profileForm.cash_balance = String(info.cash_balance || '0.00');
    profileForm.createdAt = String(info.created_at || '');
    profileForm.is_verified = Number(info.is_verified || 0);
    profileForm.real_name = String(info.real_name || '');
    profileForm.id_card_masked = String(info.id_card_masked || '');
  }

  async function loadProfile() {
    const info = await userStore.getUserInfo();
    hydrateProfile(info);
  }

  async function copyText(text: string) {
    await copyShared(text, { successMsg: '复制成功' });
  }

  function handleProfileTabChange(value: unknown) {
    if (typeof value === 'string' && PROFILE_TABS.has(value as ProfileTab)) {
      activeTab.value = value as ProfileTab;
    }
  }

  async function updateProfile() {
    profileLoading.value = true;
    try {
      await clientAuthApi.updateProfile({ nickname: profileForm.nickname });
      await loadProfile();
      MessagePlugin.success('用户名修改成功');
    } catch (error: unknown) {
      MessagePlugin.error(getErrorMessage(error, '资料保存失败'));
    } finally {
      profileLoading.value = false;
    }
  }

  async function changePassword() {
    if (!passwordForm.oldPassword || !passwordForm.newPassword) {
      MessagePlugin.warning('请填写完整密码信息');
      return;
    }
    if (passwordForm.newPassword !== passwordForm.confirmPassword) {
      MessagePlugin.warning('两次密码输入不一致');
      return;
    }
    profileLoading.value = true;
    try {
      await clientAuthApi.changePassword({
        oldPassword: passwordForm.oldPassword,
        newPassword: passwordForm.newPassword,
        confirmPassword: passwordForm.confirmPassword,
      });
      MessagePlugin.success('密码修改成功，请重新登录');
      passwordDialogVisible.value = false;
      await userStore.logout();
      router.push('/client/login');
    } catch (error: unknown) {
      MessagePlugin.error(getErrorMessage(error, '修改失败'));
    } finally {
      profileLoading.value = false;
    }
  }

  async function loadNotificationPreferences() {
    try {
      const response = await clientAuthApi.notificationPreferences();
      const data = response.data || {};
      notificationList.forEach((item) => {
        item.enabled = Boolean(data[item.key]);
      });
    } catch {
      // 通知设置失败时使用默认关闭状态，不影响资料页主体。
    }
  }

  async function saveNotificationPreferences() {
    notificationLoading.value = true;
    try {
      const settings = Object.fromEntries(notificationList.map((item) => [item.key, item.enabled]));
      await clientAuthApi.updateNotificationPreferences(settings);
      MessagePlugin.success('设置保存成功');
    } catch (error: unknown) {
      MessagePlugin.error(getErrorMessage(error, '保存失败'));
    } finally {
      notificationLoading.value = false;
    }
  }

  onMounted(() => {
    void Promise.all([loadProfile(), loadNotificationPreferences()]);
  });

  return {
    router,
    activeTab,
    profileLoading,
    notificationLoading,
    passwordDialogVisible,
    profileForm,
    passwordForm,
    notificationList,
    balanceText,
    enabledNotificationCount,
    securityItems,
    copyText,
    updateProfile,
    changePassword,
    saveNotificationPreferences,
    handleProfileTabChange,
  };
}
