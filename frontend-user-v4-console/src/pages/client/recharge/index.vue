<template>
  <section class="client-recharge">
    <t-card class="recharge-stage" :bordered="false">
      <div class="recharge-panel">
        <template v-if="isMobileScreen">
          <div class="recharge-mobile">
            <header class="recharge-mobile__hero">
              <h1>在线充值</h1>
            </header>

            <section class="mobile-balance-card">
              <span class="mobile-balance-card__label">当前余额</span>
              <strong class="mobile-balance-card__value">{{ balanceText }}</strong>
            </section>

            <section class="recharge-mobile__section">
              <label class="field-label recharge-mobile__label">充值金额</label>
              <div class="mobile-amount-grid">
                <button
                  v-for="item in RECHARGE_PRESET_AMOUNTS"
                  :key="item"
                  type="button"
                  class="mobile-select-card mobile-select-card--amount"
                  :class="{ 'is-active': !mobileCustomSelected && activePreset === item }"
                  :aria-pressed="!mobileCustomSelected && activePreset === item"
                  @click="handleMobilePresetSelect(item)"
                >
                  <span class="mobile-select-card__value">¥{{ item }}</span>
                  <check-icon v-if="!mobileCustomSelected && activePreset === item" class="mobile-select-card__mark" />
                </button>
                <button
                  type="button"
                  class="mobile-select-card mobile-select-card--amount"
                  :class="{ 'is-active': mobileCustomSelected }"
                  :aria-pressed="mobileCustomSelected"
                  @click="activateMobileCustomAmount"
                >
                  <span class="mobile-select-card__value">自定义</span>
                  <check-icon v-if="mobileCustomSelected" class="mobile-select-card__mark" />
                </button>
              </div>

              <div v-if="mobileCustomSelected" class="field-block recharge-mobile__custom-field">
                <t-input-number
                  v-model="inputAmount"
                  class="amount-input amount-input--mobile"
                  :min="1"
                  :max="50000"
                  :step="1"
                  :decimal-places="2"
                  theme="column"
                  prefix="¥"
                  @change="handleAmountChange"
                />
              </div>

              <div class="amount-payable amount-payable--mobile">
                <span>实付金额</span>
                <strong>¥{{ amountText }}</strong>
              </div>
            </section>

            <section v-if="hasPaymentGateways" class="recharge-mobile__section">
              <label class="field-label recharge-mobile__label">支付方式</label>
              <div class="mobile-method-list">
                <button
                  v-for="method in paymentGateways"
                  :key="paymentOptionKey(method)"
                  type="button"
                  class="pay-method-card pay-method-card--mobile"
                  :class="{ 'is-active': selectedGateway === paymentOptionKey(method) }"
                  :aria-pressed="selectedGateway === paymentOptionKey(method)"
                  @click="selectPaymentGateway(paymentOptionKey(method))"
                >
                  <span class="pay-method-card__icon">
                    <component :is="paymentMethodIcon(method)" />
                  </span>
                  <span class="pay-method-card__text">
                    <strong>{{ method.name || method.label }}</strong>
                    <small>{{ method.label || '扫码支付' }}</small>
                  </span>
                  <span v-if="selectedGateway === paymentOptionKey(method)" class="pay-method-card__check">
                    <check-icon />
                  </span>
                </button>
              </div>
            </section>
            <t-alert
              v-else
              class="recharge-empty-payment"
              theme="warning"
              :message="paymentGatewaysLoading ? '支付方式加载中' : '暂无可用支付方式，请通过工单联系我们开通'"
            />

            <div class="mobile-agreement">
              <t-checkbox v-model="mobileAgreementChecked">我已了解充值说明</t-checkbox>
              <t-button variant="text" theme="primary" size="small" @click="router.push('/client/help')"
                >帮助中心</t-button
              >
            </div>

            <t-button
              theme="primary"
              size="large"
              block
              class="mobile-submit-button"
              :loading="submitting"
              :disabled="!mobileAgreementChecked || !hasPaymentGateways || paymentGatewaysLoading"
              @click="handleMobileSubmit"
            >
              {{ mobileSubmitText }}
            </t-button>

            <div v-if="paymentNo || rechargePaid" class="mobile-payment-meta">
              <div class="meta-row">
                <span>支付状态</span>
                <strong>{{ rechargePaid ? '已支付' : rechargeCancelled ? '已取消' : '待支付' }}</strong>
              </div>
              <div class="meta-row">
                <span>商家订单号</span>
                <strong>{{ paymentNo || '--' }}</strong>
              </div>
              <div class="meta-row">
                <span>应付金额</span>
                <strong>¥{{ amountText }}</strong>
              </div>
            </div>

            <div v-if="qrCodeValue && !rechargePaid && !rechargeCancelled" class="mobile-pay-helper">
              <p>{{ mobilePayHelperText }}</p>
              <t-button size="small" theme="primary" variant="outline" @click="copyPayUrl">复制支付链接</t-button>
            </div>
          </div>
        </template>

        <template v-else>
          <div class="panel-main">
            <p class="desktop-balance-line">
              当前余额<strong>{{ balanceText }}</strong>
            </p>
            <div class="amount-presets">
              <t-button
                v-for="item in RECHARGE_PRESET_AMOUNTS"
                :key="item"
                :theme="activePreset === item ? 'primary' : 'default'"
                :variant="activePreset === item ? 'base' : 'outline'"
                class="preset-chip"
                @click="selectPreset(item)"
              >
                ¥{{ item }}
              </t-button>
            </div>

            <div class="field-block">
              <label class="field-label">充值数量</label>
              <div class="amount-row">
                <t-input-number
                  v-model="inputAmount"
                  class="amount-input"
                  :min="1"
                  :max="50000"
                  :step="1"
                  :decimal-places="2"
                  theme="column"
                  prefix="¥"
                  @change="handleAmountChange"
                />
                <div class="amount-payable">
                  <span>实付金额：</span>
                  <strong>¥{{ amountText }}</strong>
                </div>
              </div>
            </div>

            <div v-if="hasPaymentGateways" class="field-block">
              <label class="field-label">选择支付方式</label>
              <div class="pay-methods">
                <t-button
                  v-for="method in paymentGateways"
                  :key="paymentOptionKey(method)"
                  :theme="selectedGateway === paymentOptionKey(method) ? 'primary' : 'default'"
                  :variant="selectedGateway === paymentOptionKey(method) ? 'base' : 'outline'"
                  :aria-pressed="selectedGateway === paymentOptionKey(method)"
                  class="pay-method"
                  :loading="submitting && selectedGateway === paymentOptionKey(method)"
                  :disabled="submitting || paymentGatewaysLoading"
                  @click="handleGatewayCreate(method)"
                >
                  <template #icon><component :is="paymentMethodIcon(method)" /></template>
                  {{
                    selectedGateway === paymentOptionKey(method)
                      ? paymentButtonText
                      : `生成${method.name || method.label || '支付'}二维码`
                  }}
                </t-button>
              </div>
            </div>
            <t-alert
              v-else
              class="recharge-empty-payment"
              theme="warning"
              :message="paymentGatewaysLoading ? '支付方式加载中' : '暂无可用支付方式，请通过工单联系我们开通'"
            />
          </div>

          <aside class="qrcode-panel">
            <div
              class="qrcode-frame"
              :class="{ 'is-ready': qrCodeValue, 'is-paid': rechargePaid, 'is-cancelled': rechargeCancelled }"
            >
              <qrcode-vue
                v-if="qrCodeValue"
                class="qrcode-svg"
                :class="{ 'is-muted': rechargePaid || rechargeCancelled }"
                :value="qrCodeValue"
                :size="160"
                level="H"
                render-as="svg"
              />
              <transition name="payment-success">
                <div v-if="rechargePaid" class="qrcode-success" aria-live="polite">
                  <span class="success-icon">
                    <check-circle-icon />
                  </span>
                  <strong>充值成功</strong>
                  <small>余额已刷新</small>
                </div>
              </transition>
              <div v-if="rechargeCancelled" class="qrcode-cancelled" aria-live="polite">
                <strong>该充值单已失效</strong>
                <small>请重新发起充值</small>
              </div>
              <div v-if="!qrCodeValue" class="qrcode-empty">
                <span class="empty-icon">¥</span>
                <p>选择金额后生成支付二维码</p>
              </div>
            </div>

            <div class="qrcode-meta">
              <p class="qrcode-title">{{ qrCodeTitle }}</p>
              <p v-if="qrCodeSubtitle" class="qrcode-subtitle">{{ qrCodeSubtitle }}</p>
              <div class="meta-row">
                <span>商家订单号</span>
                <strong>{{ paymentNo || '--' }}</strong>
              </div>
              <div class="meta-row">
                <span>应付金额</span>
                <strong>¥{{ amountText }}</strong>
              </div>
            </div>
          </aside>
        </template>
      </div>
    </t-card>
  </section>
</template>
<script setup lang="ts">
import {
  CheckCircleIcon,
  CheckIcon,
  CreditcardIcon,
  LogoAlipayFilledIcon,
  LogoWechatpayFilledIcon,
} from 'tdesign-icons-vue-next';
import { DialogPlugin } from 'tdesign-vue-next/es/dialog/plugin';
import { computed, defineAsyncComponent, onMounted, ref } from 'vue';

import { RECHARGE_PRESET_AMOUNTS, useRecharge } from '@/domains/finance/useRecharge';
import type { RechargeGatewayOption } from '@/types/client';
import { formatMoneyText } from '@/utils/format';

const QrcodeVue = defineAsyncComponent(() => import('qrcode.vue'));

import { useMediaQuery } from '@/composables/useMediaQuery';

const isMobileScreen = useMediaQuery('(max-width: 47.98rem)');
const mobileCustomMode = ref(false);
const mobileAgreementChecked = ref(true);

const {
  router,
  userStore,
  inputAmount,
  activePreset,
  submitting,
  rechargePaid,
  rechargeCancelled,
  paymentGatewaysLoading,
  selectedGateway,
  paymentGateways,
  rechargeSummary,
  amountText,
  qrCodeValue,
  paymentNo,
  paymentButtonText,
  qrCodeTitle,
  qrCodeSubtitle,
  hasPaymentGateways,
  selectedPaymentGateway,
  selectPreset,
  selectPaymentGateway,
  handleAmountChange,
  loadPaymentGateways,
  handleCreateOrder,
  copyPayUrl,
} = useRecharge();

const mobileCustomSelected = computed(() => mobileCustomMode.value || activePreset.value === null);
const mobileSubmitText = computed(() =>
  rechargePaid.value ? '继续充值' : rechargeCancelled.value ? '重新发起充值' : '立即充值',
);
const mobilePayHelperText = computed(() => {
  if (selectedPaymentGateway.value?.payment_type === 'wxpay') {
    return '请复制支付链接后在微信或手机浏览器中继续支付。';
  }

  return '若未能自动打开支付宝，请复制支付链接后在手机浏览器中继续支付。';
});

const balanceText = computed(() => {
  if (rechargePaid.value) {
    return formatMoneyText(rechargeSummary.cashBalance || userStore.info?.cash_balance || 0);
  }

  const storeBalance = userStore.info?.cash_balance;
  if (storeBalance !== undefined && storeBalance !== null && String(storeBalance).trim() !== '') {
    return formatMoneyText(storeBalance);
  }

  return formatMoneyText(rechargeSummary.cashBalance || 0);
});

function handleMobilePresetSelect(value: number) {
  mobileCustomMode.value = false;
  selectPreset(value);
}

function handleMobileSubmit() {
  if (submitting.value) return;
  const gatewayName = selectedPaymentGateway.value?.name || selectedPaymentGateway.value?.label || '在线支付';
  const dialog = DialogPlugin.confirm({
    header: '在线充值',
    body: `确认通过 ${gatewayName} 充值 ¥${amountText.value}？`,
    confirmBtn: '确定',
    cancelBtn: '取消',
    onConfirm: () => {
      dialog.destroy();
      void handleCreateOrder(true);
    },
  });
}

function activateMobileCustomAmount() {
  mobileCustomMode.value = true;
}

function paymentOptionKey(method: RechargeGatewayOption) {
  return String(method.option_key || method.key || '').trim();
}

function paymentMethodIcon(method: RechargeGatewayOption) {
  if (method.key === 'alipay' || method.payment_type === 'alipay') return LogoAlipayFilledIcon;
  if (method.key === 'wechat' || method.payment_type === 'wxpay') return LogoWechatpayFilledIcon;
  return CreditcardIcon;
}

async function handleGatewayCreate(method: RechargeGatewayOption) {
  selectPaymentGateway(paymentOptionKey(method));
  const confirmed = await new Promise<boolean>((resolve) => {
    const dialog = DialogPlugin.confirm({
      header: '在线充值',
      body: `确认通过 ${method.name || method.label || '支付'} 充值 ¥${amountText.value}？`,
      confirmBtn: '确定',
      cancelBtn: '取消',
      onConfirm: () => {
        dialog.destroy();
        resolve(true);
      },
      onCancel: () => {
        dialog.destroy();
        resolve(false);
      },
      onClose: () => {
        dialog.destroy();
        resolve(false);
      },
    });
  });
  if (!confirmed) return;
  await handleCreateOrder(false);
}

onMounted(() => {
  void loadPaymentGateways();
});
</script>
<style src="./index.less" lang="less" scoped></style>
