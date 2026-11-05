<template>
  <t-dialog
    :visible="visible"
    header="完善账户信息"
    :close-btn="false"
    :close-on-esc-keydown="false"
    :close-on-overlay-click="false"
    :footer="false"
    width="min(32rem, calc(100vw - 2rem))"
    class="account-policy-dialog"
  >
    <p class="account-policy-dialog__intro">根据当前平台要求，完成以下信息后才能继续下单、支付、充值与提现。</p>
    <ul class="account-policy-dialog__list">
      <li v-for="item in missingItems" :key="item.key" class="account-policy-dialog__item">
        <div class="account-policy-dialog__text">
          <strong>{{ item.title }}</strong>
          <p>{{ item.desc }}</p>
        </div>
        <t-button theme="primary" variant="base" @click="goComplete(item.key)">去完成</t-button>
      </li>
    </ul>
  </t-dialog>
</template>
<script setup lang="ts">
import { computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';

import { useAccountPolicyStore, useUserStore } from '@/store';

/**
 * 账户策略引导弹窗：开关开启且用户未完成时显示，不可关闭。
 *
 * 豁免「去完成」的落地页（实名页 / 个人资料页），否则不可关闭的遮罩会挡住
 * 绑定表单，用户永远无法完成。其余控制台页面每次进入都会再次提示。
 * 完成后弹窗自动消失：实名与绑定成功后业务代码会 getUserInfo() 刷新 userInfo。
 */

type MissingKey = 'verification' | 'phone' | 'email';

const route = useRoute();
const router = useRouter();
const policy = useAccountPolicyStore();
const userStore = useUserStore();

const EXEMPT_ROUTE_NAMES = new Set(['ClientVerification', 'ClientProfile']);

const userInfo = computed(() => userStore.info || {});
const isVerified = computed(() => {
  const status = Number(userInfo.value.verification_status ?? -1);
  const legacy = Number(userInfo.value.is_verified ?? 0);
  // 与后端 User::hasCompletedVerification() 同口径
  return status === 2 || (status === 0 && legacy === 1);
});
const isPhoneBound = computed(() => Boolean(String(userInfo.value.phone || '').trim()));
const isEmailBound = computed(() => Boolean(String(userInfo.value.email || '').trim()));

const missingItems = computed(() => {
  const items: Array<{ key: MissingKey; title: string; desc: string }> = [];
  if (policy.requireVerification && !isVerified.value) {
    items.push({ key: 'verification', title: '完成实名认证', desc: '实名认证是下单与付款的前置条件。' });
  }
  if (policy.requirePhone && !isPhoneBound.value) {
    items.push({ key: 'phone', title: '绑定安全手机', desc: '用于接收验证码与安全提醒。' });
  }
  if (policy.requireEmail && !isEmailBound.value) {
    items.push({ key: 'email', title: '绑定安全邮箱', desc: '用于接收账单与安全通知。' });
  }
  return items;
});

const isExemptRoute = computed(() => {
  const name = typeof route.name === 'string' ? route.name : '';
  return EXEMPT_ROUTE_NAMES.has(name);
});

const visible = computed(() => missingItems.value.length > 0 && !isExemptRoute.value);

function goComplete(key: MissingKey) {
  if (key === 'verification') {
    void router.push('/client/verification');
    return;
  }
  void router.push({ path: '/client/profile', query: { tab: 'security', bind: key } });
}
</script>
<style scoped lang="less">
.account-policy-dialog {
  &__intro {
    margin: 0 0 var(--td-comp-margin-m);
    color: var(--td-text-color-secondary);
    font: var(--td-font-body-medium);
  }

  &__list {
    display: flex;
    flex-direction: column;
    gap: var(--td-comp-margin-s);
    padding: 0;
    margin: 0;
    list-style: none;
  }

  &__item {
    display: flex;
    gap: var(--td-comp-margin-m);
    align-items: center;
    justify-content: space-between;
    padding: var(--td-comp-paddingTB-m) var(--td-comp-paddingLR-m);
    background: var(--td-bg-color-container);
    border: thin solid var(--td-component-stroke);
    border-radius: var(--td-radius-medium);
  }

  &__text {
    min-width: 0;

    p {
      margin: var(--td-comp-margin-xs) 0 0;
      color: var(--td-text-color-secondary);
      font: var(--td-font-body-small);
    }
  }
}
</style>
