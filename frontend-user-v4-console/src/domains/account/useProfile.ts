import { MessagePlugin } from 'tdesign-vue-next/es/message/plugin';
import { computed, onActivated, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';

import { AUTH_BOUND_CODE_CONFIG, clientAuthApi } from '@/api/auth';
import { useGeeTestCaptcha } from '@/composables/useGeeTestCaptcha';
import { useUserStore } from '@/store';
import type { ClientNotificationPreferences, ClientUserInfo } from '@/types/client';
import { getErrorMessage } from '@/utils/error';
import { copyText as copyShared } from '@/utils/format';

type TagTheme = 'default' | 'success' | 'warning' | 'primary' | 'danger';
type ProfileTab = 'profile' | 'security' | 'agent' | 'notification';
type NotificationKey = keyof ClientNotificationPreferences;
interface NotificationItem {
  key: NotificationKey;
  name: string;
  desc: string;
  enabled: boolean;
}

const PROFILE_TABS = new Set<ProfileTab>(['profile', 'security', 'agent', 'notification']);

export function useProfile() {
  const router = useRouter();
  const userStore = useUserStore();
  const { runWithCaptcha } = useGeeTestCaptcha();
  const activeTab = ref<ProfileTab>('profile');
  // AC-19：按操作拆分布尔量，任一操作进行中不再联动禁用整页其他按钮
  const savingProfile = ref(false);
  const changingPassword = ref(false);
  const submittingPhone = ref(false);
  const submittingEmail = ref(false);
  // AC-20：五个「发送验证码」按钮各自的进行中标志
  const codeSending = reactive({ reset: false, phoneOld: false, emailOld: false, phone: false, email: false });
  // AC-22：资料加载失败给出可重试的明确错误，而非留一张空白表单
  const profileError = ref('');
  const passwordDialogVisible = ref(false);
  const phoneDialogVisible = ref(false);
  const emailDialogVisible = ref(false);
  const phoneChangeStep = ref<1 | 2>(1);
  const emailChangeStep = ref<1 | 2>(1);
  const notificationLoading = ref(false);
  const phoneForm = reactive({ phone: '', code: '', oldCode: '' });
  const emailForm = reactive({ email: '', code: '', oldCode: '' });
  const phoneCountdown = ref(0);
  const emailCountdown = ref(0);
  const phoneOldCountdown = ref(0);
  const emailOldCountdown = ref(0);
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
    verification_status: 0,
  });
  const passwordForm = reactive({ oldPassword: '', newPassword: '', confirmPassword: '' });
  const passwordMode = ref<'old' | 'reset'>('old');
  const resetForm = reactive({ type: 'phone' as 'phone' | 'email', code: '', password: '', confirmPassword: '' });
  const resetCountdown = ref(0);
  const notificationList = reactive<NotificationItem[]>([
    {
      key: 'login_notify',
      name: '账号登录提醒',
      desc: '每次账户成功登录后，向绑定邮箱发送登录安全提醒。',
      enabled: false,
    },
    {
      key: 'login_location_alert',
      name: '异地登录提醒',
      desc: '检测到新的登录 IP 环境时，额外发送一次异地登录风险提醒。',
      enabled: false,
    },
    {
      key: 'password_change_alert',
      name: '更改密码提醒',
      desc: '账户密码修改成功后，立即发送安全提醒邮件。',
      enabled: false,
    },
    {
      key: 'phone_change_alert',
      name: '更改手机号提醒',
      desc: '安全手机号发生变更时，及时发送变更提醒。',
      enabled: false,
    },
    {
      key: 'email_change_alert',
      name: '更改邮箱提醒',
      desc: '安全邮箱发生变更时，向原邮箱和新邮箱发送提醒。',
      enabled: false,
    },
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
      actionLabel: profileForm.phone ? '更换绑定' : '前往绑定',
      action: () => openPhoneDialog(),
    },
    {
      key: 'email',
      name: '安全邮箱',
      desc: profileForm.email || '建议绑定常用邮箱，用于接收通知与安全提醒',
      theme: (profileForm.email ? 'success' : 'warning') as TagTheme,
      tag: profileForm.email ? '已绑定' : '未绑定',
      actionLabel: profileForm.email ? '更换绑定' : '前往绑定',
      action: () => openEmailDialog(),
    },
    {
      key: 'password',
      name: '登录密码',
      desc: '建议定期更新密码，并避免与其他平台共用同一组凭证',
      theme: 'success' as TagTheme,
      tag: '已设置',
      actionLabel: '修改密码',
      action: () => openPasswordDialog(),
    },
  ]);

  // 五个验证码倒计时原本各起一个 setInterval：页面被 keep-alive 常驻时会同时挂 5 个
  // 每秒唤醒的定时器。合并成单一 1 秒时钟后每秒只唤醒一次，全部归零时自动停表。
  const countdownSlots = [resetCountdown, phoneOldCountdown, emailOldCountdown, phoneCountdown, emailCountdown];
  let countdownTimer: ReturnType<typeof setInterval> | null = null;

  function stopCountdownTimer() {
    if (countdownTimer) {
      clearInterval(countdownTimer);
      countdownTimer = null;
    }
  }

  function ensureCountdownTimer() {
    if (countdownTimer) return;
    countdownTimer = setInterval(() => {
      let running = false;
      for (const slot of countdownSlots) {
        if (slot.value > 0) {
          slot.value -= 1;
          if (slot.value > 0) running = true;
        }
      }
      if (!running) stopCountdownTimer();
    }, 1000);
  }

  // 只清自己的计数，共用时钟会在全部归零后自行停止
  function clearPhoneTimer() {
    phoneCountdown.value = 0;
  }
  function clearEmailTimer() {
    emailCountdown.value = 0;
  }
  function clearPhoneOldTimer() {
    phoneOldCountdown.value = 0;
  }
  function clearEmailOldTimer() {
    emailOldCountdown.value = 0;
  }
  function clearResetTimer() {
    resetCountdown.value = 0;
  }

  function openPasswordDialog() {
    passwordMode.value = 'old';
    passwordForm.oldPassword = '';
    passwordForm.newPassword = '';
    passwordForm.confirmPassword = '';
    resetForm.type = profileForm.phone ? 'phone' : 'email';
    resetForm.code = '';
    resetForm.password = '';
    resetForm.confirmPassword = '';
    resetCountdown.value = 0;
    clearResetTimer();
    passwordDialogVisible.value = true;
  }

  function togglePasswordMode() {
    passwordMode.value = passwordMode.value === 'old' ? 'reset' : 'old';
  }

  async function sendResetCode() {
    if (codeSending.reset || resetCountdown.value > 0) return;
    codeSending.reset = true;
    try {
      await runWithCaptcha(async (captcha: unknown) => {
        if (resetForm.type === 'phone') {
          await clientAuthApi.sendPhoneCode({ phone: profileForm.phone, purpose: 'reset_password', captcha });
        } else {
          await clientAuthApi.sendEmailCode({ email: profileForm.email, purpose: 'reset_password', captcha });
        }
      });
      MessagePlugin.success('验证码已发送');
      resetCountdown.value = 60;
      ensureCountdownTimer();
    } catch (error: unknown) {
      MessagePlugin.error(getErrorMessage(error, '验证码发送失败'));
    } finally {
      codeSending.reset = false;
    }
  }

  async function submitResetPassword() {
    if (!resetForm.code || resetForm.code.length !== 6) {
      MessagePlugin.warning('请输入 6 位验证码');
      return;
    }
    if (!resetForm.password) {
      MessagePlugin.warning('请输入新密码');
      return;
    }
    if (resetForm.password.length < 6) {
      MessagePlugin.warning('新密码至少 6 位');
      return;
    }
    if (resetForm.password !== resetForm.confirmPassword) {
      MessagePlugin.warning('两次密码输入不一致');
      return;
    }
    if (changingPassword.value) return;
    changingPassword.value = true;
    try {
      const account = resetForm.type === 'phone' ? profileForm.phone : profileForm.email;
      await clientAuthApi.resetPassword({
        account,
        code: resetForm.code,
        password: resetForm.password,
        password_confirmation: resetForm.confirmPassword,
      });
      MessagePlugin.success('密码重置成功，请重新登录');
      passwordDialogVisible.value = false;
      clearResetTimer();
      await userStore.logout();
      router.push('/client/login');
    } catch (error: unknown) {
      MessagePlugin.error(getErrorMessage(error, '密码重置失败'));
    } finally {
      changingPassword.value = false;
    }
  }

  function openPhoneDialog() {
    phoneForm.phone = '';
    phoneForm.code = '';
    phoneForm.oldCode = '';
    phoneCountdown.value = 0;
    phoneOldCountdown.value = 0;
    clearPhoneTimer();
    clearPhoneOldTimer();
    phoneChangeStep.value = profileForm.phone ? 1 : 2;
    phoneDialogVisible.value = true;
  }

  function openEmailDialog() {
    emailForm.email = '';
    emailForm.code = '';
    emailForm.oldCode = '';
    emailCountdown.value = 0;
    emailOldCountdown.value = 0;
    clearEmailTimer();
    clearEmailOldTimer();
    emailChangeStep.value = profileForm.email ? 1 : 2;
    emailDialogVisible.value = true;
  }

  async function goPhoneNextStep() {
    if (!phoneForm.oldCode || phoneForm.oldCode.length !== 6) {
      MessagePlugin.warning('请输入 6 位原手机号验证码');
      return;
    }
    if (submittingPhone.value) return;
    submittingPhone.value = true;
    try {
      await clientAuthApi.verifyBoundPhoneCode({ code: phoneForm.oldCode });
      phoneChangeStep.value = 2;
    } catch (error: unknown) {
      MessagePlugin.error(getErrorMessage(error, '原手机验证码错误或已过期'));
    } finally {
      submittingPhone.value = false;
    }
  }

  async function goEmailNextStep() {
    if (!emailForm.oldCode || emailForm.oldCode.length !== 6) {
      MessagePlugin.warning('请输入 6 位原邮箱验证码');
      return;
    }
    if (submittingEmail.value) return;
    submittingEmail.value = true;
    try {
      await clientAuthApi.verifyBoundEmailCode({ code: emailForm.oldCode });
      emailChangeStep.value = 2;
    } catch (error: unknown) {
      MessagePlugin.error(getErrorMessage(error, '原邮箱验证码错误或已过期'));
    } finally {
      submittingEmail.value = false;
    }
  }

  async function sendPhoneOldVerificationCode() {
    if (!profileForm.phone) {
      MessagePlugin.warning('当前账号未绑定手机号');
      return;
    }
    if (codeSending.phoneOld || phoneOldCountdown.value > 0) return;
    codeSending.phoneOld = true;
    try {
      await runWithCaptcha(async (captcha: unknown) => {
        await clientAuthApi.sendPhoneCode(
          { phone: profileForm.phone, purpose: 'verify_bound_phone', captcha },
          AUTH_BOUND_CODE_CONFIG,
        );
      });
      MessagePlugin.success('验证码已发送至原手机号');
      phoneOldCountdown.value = 60;
      ensureCountdownTimer();
    } catch (error: unknown) {
      MessagePlugin.error(getErrorMessage(error, '验证码发送失败'));
    } finally {
      codeSending.phoneOld = false;
    }
  }

  async function sendEmailOldVerificationCode() {
    if (!profileForm.email) {
      MessagePlugin.warning('当前账号未绑定邮箱');
      return;
    }
    if (codeSending.emailOld || emailOldCountdown.value > 0) return;
    codeSending.emailOld = true;
    try {
      await runWithCaptcha(async (captcha: unknown) => {
        await clientAuthApi.sendEmailCode(
          { email: profileForm.email, purpose: 'verify_bound_email', captcha },
          AUTH_BOUND_CODE_CONFIG,
        );
      });
      MessagePlugin.success('验证码已发送至原邮箱');
      emailOldCountdown.value = 60;
      ensureCountdownTimer();
    } catch (error: unknown) {
      MessagePlugin.error(getErrorMessage(error, '验证码发送失败'));
    } finally {
      codeSending.emailOld = false;
    }
  }

  async function sendPhoneVerificationCode() {
    if (!phoneForm.phone) {
      MessagePlugin.warning('请输入新手机号');
      return;
    }
    if (codeSending.phone || phoneCountdown.value > 0) return;
    codeSending.phone = true;
    try {
      await runWithCaptcha(async (captcha: unknown) => {
        await clientAuthApi.sendPhoneCode({
          phone: phoneForm.phone,
          purpose: profileForm.phone ? 'change_phone' : 'bind_phone',
          captcha,
        });
      });
      MessagePlugin.success('验证码已发送');
      phoneCountdown.value = 60;
      ensureCountdownTimer();
    } catch (error: unknown) {
      MessagePlugin.error(getErrorMessage(error, '验证码发送失败'));
    } finally {
      codeSending.phone = false;
    }
  }

  async function sendEmailVerificationCode() {
    if (!emailForm.email) {
      MessagePlugin.warning('请输入新邮箱');
      return;
    }
    if (codeSending.email || emailCountdown.value > 0) return;
    codeSending.email = true;
    try {
      await runWithCaptcha(async (captcha: unknown) => {
        // 这里发往的是「新邮箱」，不能用 change_email：后端 EMAIL_BOUND_PURPOSES 含 change_email，
        // 会校验目标必须等于当前已绑定邮箱，换绑新邮箱时必然 422。
        await clientAuthApi.sendEmailCode({ email: emailForm.email, purpose: 'bind_email', captcha });
      });
      MessagePlugin.success('验证码已发送');
      emailCountdown.value = 60;
      ensureCountdownTimer();
    } catch (error: unknown) {
      MessagePlugin.error(getErrorMessage(error, '验证码发送失败'));
    } finally {
      codeSending.email = false;
    }
  }

  async function submitPhoneChange() {
    if (!phoneForm.phone) {
      MessagePlugin.warning('请输入新手机号');
      return;
    }
    if (!phoneForm.code || phoneForm.code.length !== 6) {
      MessagePlugin.warning('请输入 6 位验证码');
      return;
    }
    if (profileForm.phone && (!phoneForm.oldCode || phoneForm.oldCode.length !== 6)) {
      MessagePlugin.warning('请输入原手机号验证码');
      return;
    }
    if (submittingPhone.value) return;
    submittingPhone.value = true;
    try {
      await clientAuthApi.updatePhone({
        phone: phoneForm.phone,
        code: phoneForm.code,
        old_code: phoneForm.oldCode || undefined,
      });
      MessagePlugin.success('手机号修改成功');
      phoneDialogVisible.value = false;
      clearPhoneTimer();
      clearPhoneOldTimer();
      await loadProfile();
    } catch (error: unknown) {
      MessagePlugin.error(getErrorMessage(error, '手机号修改失败'));
    } finally {
      submittingPhone.value = false;
    }
  }

  async function submitEmailChange() {
    if (!emailForm.email) {
      MessagePlugin.warning('请输入新邮箱');
      return;
    }
    if (!emailForm.code || emailForm.code.length !== 6) {
      MessagePlugin.warning('请输入 6 位验证码');
      return;
    }
    if (profileForm.email && (!emailForm.oldCode || emailForm.oldCode.length !== 6)) {
      MessagePlugin.warning('请输入原邮箱验证码');
      return;
    }
    if (submittingEmail.value) return;
    submittingEmail.value = true;
    try {
      await clientAuthApi.updateEmail({
        email: emailForm.email,
        code: emailForm.code,
        old_code: emailForm.oldCode || undefined,
      });
      MessagePlugin.success('邮箱修改成功');
      emailDialogVisible.value = false;
      clearEmailTimer();
      clearEmailOldTimer();
      await loadProfile();
    } catch (error: unknown) {
      MessagePlugin.error(getErrorMessage(error, '邮箱修改失败'));
    } finally {
      submittingEmail.value = false;
    }
  }

  function hydrateProfile(info: ClientUserInfo = {}) {
    profileForm.id = String(info.id || '');
    profileForm.email = String(info.email || '');
    profileForm.nickname = String(info.nickname || '');
    profileForm.phone = String(info.phone || '');
    profileForm.cash_balance = String(info.cash_balance || '0.00');
    profileForm.createdAt = String(info.created_at || '');
    profileForm.is_verified = Number(info.is_verified || 0);
    profileForm.real_name = String(info.real_name || '');
    profileForm.id_card_masked = String(info.id_card_masked || '');
    profileForm.verification_status = Number(info.verification_status || 0);
  }

  const isProfileVerified = computed(
    () => Number(profileForm.is_verified || 0) === 1 || Number(profileForm.verification_status || 0) === 2,
  );
  // 实名通过时后端会把用户名落成真实姓名，这里只做提示，不限制用户改回自定义名称。
  const nicknameHint = computed(() =>
    isProfileVerified.value && profileForm.real_name
      ? `已实名认证，用户名默认使用真实姓名「${profileForm.real_name}」，可按需修改为自定义名称。`
      : '用户名会显示在控制台右上角与业务记录中。',
  );

  async function loadProfile() {
    // AC-22：不吞异常也不放未处理 rejection，失败时把错误交给页面展示并提供重试入口
    try {
      const info = await userStore.getUserInfo();
      hydrateProfile(info);
      profileError.value = '';
    } catch (error: unknown) {
      profileError.value = getErrorMessage(error, '个人资料加载失败，请稍后重试');
      throw error;
    }
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
    const trimmed = (profileForm.nickname || '').trim();
    if (!trimmed) {
      MessagePlugin.warning('用户名不能为空');
      return;
    }
    if (trimmed.length > 50) {
      MessagePlugin.warning('用户名最多 50 个字符');
      return;
    }
    if (savingProfile.value) return;
    savingProfile.value = true;
    try {
      await clientAuthApi.updateProfile({ nickname: trimmed });
      await loadProfile();
      MessagePlugin.success('用户名修改成功');
    } catch (error: unknown) {
      MessagePlugin.error(getErrorMessage(error, '资料保存失败'));
    } finally {
      savingProfile.value = false;
    }
  }

  async function changePassword() {
    if (!passwordForm.oldPassword || !passwordForm.newPassword) {
      MessagePlugin.warning('请填写完整密码信息');
      return;
    }
    if (passwordForm.newPassword.length < 6) {
      MessagePlugin.warning('新密码至少 6 位');
      return;
    }
    if (passwordForm.newPassword !== passwordForm.confirmPassword) {
      MessagePlugin.warning('两次密码输入不一致');
      return;
    }
    if (changingPassword.value) return;
    changingPassword.value = true;
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
      changingPassword.value = false;
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

  function loadProfilePage() {
    // AC-22：Promise.all 中 loadProfile 会抛错，这里统一接住避免未处理 rejection；错误已由 profileError 呈现
    return Promise.all([loadProfile(), loadNotificationPreferences()]).catch(() => undefined);
  }

  onMounted(() => {
    void loadProfilePage();
  });

  // keep-alive 现在真的会缓存页面（Content.vue 按 route.name 做 key），onMounted 一生只跑一次，
  // 从别的页签切回来会看到上次留下的旧数据。这里补一次重新拉取，只在非首次激活时触发，
  // 避免刚挂载完紧跟着又来一次同样的请求。
  let activatedOnce = false;
  onActivated(() => {
    if (!activatedOnce) {
      activatedOnce = true;
      return;
    }
    void loadProfilePage();
  });

  onBeforeUnmount(() => {
    stopCountdownTimer();
  });

  return {
    router,
    activeTab,
    savingProfile,
    changingPassword,
    submittingPhone,
    submittingEmail,
    codeSending,
    profileError,
    loadProfile,
    notificationLoading,
    passwordDialogVisible,
    phoneDialogVisible,
    emailDialogVisible,
    passwordMode,
    profileForm,
    passwordForm,
    resetForm,
    resetCountdown,
    phoneForm,
    emailForm,
    phoneCountdown,
    emailCountdown,
    phoneOldCountdown,
    emailOldCountdown,
    phoneChangeStep,
    emailChangeStep,
    notificationList,
    balanceText,
    enabledNotificationCount,
    nicknameHint,
    securityItems,
    copyText,
    updateProfile,
    changePassword,
    togglePasswordMode,
    sendResetCode,
    submitResetPassword,
    sendPhoneVerificationCode,
    sendEmailVerificationCode,
    sendPhoneOldVerificationCode,
    sendEmailOldVerificationCode,
    goPhoneNextStep,
    goEmailNextStep,
    submitPhoneChange,
    submitEmailChange,
    saveNotificationPreferences,
    handleProfileTabChange,
  };
}
