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
                  v-for="gateway in gatewayViews"
                  :key="gateway.key"
                  type="button"
                  class="pay-method-card pay-method-card--mobile"
                  :class="{ 'is-active': gateway.isActive }"
                  :aria-pressed="gateway.isActive"
                  :disabled="submitting || gatewaysBusy"
                  :aria-busy="submitting || gatewaysBusy"
                  @click="handleMobileGatewaySelect(gateway.key)"
                >
                  <span class="pay-method-card__icon">
                    <component :is="gateway.icon" />
                  </span>
                  <span class="pay-method-card__text">
                    <strong>{{ gateway.name }}</strong>
                    <small>{{ gateway.label }}</small>
                  </span>
                  <span v-if="gateway.isActive" class="pay-method-card__check">
                    <check-icon />
                  </span>
                </button>
              </div>
            </section>
            <!-- C-04：支付方式区补全三态。加载中只说明「正在加载」，失败给出失败原因 + 重试按钮，
                 既没有支付方式又没有失败时才是真正的「暂无」。原先三种情况共用一句文案，用户无法判断该等还是该重试。 -->
            <t-alert
              v-else
              class="recharge-empty-payment"
              :theme="paymentGatewaysError ? 'error' : 'info'"
              :message="
                paymentGatewaysLoading
                  ? '支付方式加载中'
                  : paymentGatewaysError || '暂无可用支付方式，请通过工单联系我们开通'
              "
            >
              <template v-if="paymentGatewaysError && !paymentGatewaysLoading" #operation>
                <t-button theme="primary" variant="outline" size="small" @click="loadPaymentGateways">
                  重新加载
                </t-button>
              </template>
            </t-alert>

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
              :disabled="
                submitting || !mobileAgreementChecked || !hasPaymentGateways || paymentGatewaysLoading || confirmVisible
              "
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
                  v-for="gateway in gatewayViews"
                  :key="gateway.key"
                  :theme="gateway.isActive ? 'primary' : 'default'"
                  :variant="gateway.isActive ? 'base' : 'outline'"
                  :aria-pressed="gateway.isActive"
                  class="pay-method"
                  :loading="gateway.loading"
                  :disabled="submitting || paymentGatewaysLoading"
                  @click="handleGatewayCreate(gateway)"
                >
                  <template #icon><component :is="gateway.icon" /></template>
                  {{ gateway.buttonText }}
                </t-button>
              </div>
            </div>
            <!-- C-04：支付方式区补全三态。加载中只说明「正在加载」，失败给出失败原因 + 重试按钮，
                 既没有支付方式又没有失败时才是真正的「暂无」。原先三种情况共用一句文案，用户无法判断该等还是该重试。 -->
            <t-alert
              v-else
              class="recharge-empty-payment"
              :theme="paymentGatewaysError ? 'error' : 'info'"
              :message="
                paymentGatewaysLoading
                  ? '支付方式加载中'
                  : paymentGatewaysError || '暂无可用支付方式，请通过工单联系我们开通'
              "
            >
              <template v-if="paymentGatewaysError && !paymentGatewaysLoading" #operation>
                <t-button theme="primary" variant="outline" size="small" @click="loadPaymentGateways">
                  重新加载
                </t-button>
              </template>
            </t-alert>
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

import { useDeviceLayout } from '@/composables/useDeviceLayout';
import { RECHARGE_PRESET_AMOUNTS, useRecharge } from '@/domains/finance/useRecharge';
import type { RechargeGatewayOption } from '@/types/client';
import { formatMoneyText } from '@/utils/format';

const QrcodeVue = defineAsyncComponent(() => import('qrcode.vue'));

const { isMobile: isMobileScreen } = useDeviceLayout();
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
  paymentGatewaysError,
  loadPaymentGateways,
  selectedGateway,
  paymentGateways,
  rechargeSummary,
  amountText,
  qrCodeValue,
  paymentNo,
  paymentButtonText,
  qrCodeTitle,
  hasPaymentGateways,
  selectedPaymentGateway,
  selectPreset,
  selectPaymentGateway,
  handleAmountChange,
  handleCreateOrder,
  copyPayUrl,
} = useRecharge();

const mobileCustomSelected = computed(() => mobileCustomMode.value || activePreset.value === null);
// 确认框打开中：避免连点叠出多个确认框、或在下单过程中切换支付方式导致二维码与订单脱钩
const confirmVisible = ref(false);
// 下单或确认框打开期间冻结支付方式选择（移动端自绘 button 不走 t-button 的 loading 拦截）
const gatewaysBusy = computed(() => submitting.value || confirmVisible.value);
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
  // 两处都是原始数值（元），只在此处格式化一次；混入已格式化字符串会被 Number() 解析成 NaN。
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

function handleMobileGatewaySelect(optionKey: string) {
  // 下单/确认中切换支付方式会清空 paymentPayload，使已显示的二维码与最新订单脱钩，因此直接忽略
  if (gatewaysBusy.value) return;
  selectPaymentGateway(optionKey);
}

function handleMobileSubmit() {
  if (submitting.value || confirmVisible.value) return;
  const gatewayName = selectedPaymentGateway.value?.name || selectedPaymentGateway.value?.label || '在线支付';
  confirmVisible.value = true;
  const dialog = DialogPlugin.confirm({
    header: '在线充值',
    body: `确认通过 ${gatewayName} 充值 ¥${amountText.value}？`,
    confirmBtn: '确定',
    cancelBtn: '取消',
    async onConfirm() {
      dialog.setConfirmLoading(true);
      try {
        await handleCreateOrder(true);
      } finally {
        confirmVisible.value = false;
        dialog.destroy();
      }
    },
    onCancel: () => {
      confirmVisible.value = false;
    },
    onClose: () => {
      confirmVisible.value = false;
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

interface GatewayView {
  key: string;
  name: string;
  label: string;
  icon: typeof CreditcardIcon;
  isActive: boolean;
  loading: boolean;
  buttonText: string;
  method: RechargeGatewayOption;
}

/**
 * 支付方式视图模型：原先模板对同一个 method 调 paymentOptionKey 11 次、
 * 每次渲染都重新 String().trim() 并重复推导图标与按钮文案。
 */
const gatewayViews = computed<GatewayView[]>(() =>
  paymentGateways.value.map((method) => {
    const key = paymentOptionKey(method);
    const isActive = selectedGateway.value === key;
    const name = method.name || method.label || '支付';

    return {
      key,
      name,
      label: method.label || '扫码支付',
      icon: paymentMethodIcon(method),
      isActive,
      loading: submitting.value && isActive,
      buttonText: isActive ? paymentButtonText.value : `生成${name}二维码`,
      method,
    };
  }),
);

async function handleGatewayCreate(method: RechargeGatewayOption) {
  if (submitting.value || confirmVisible.value) return;
  selectPaymentGateway(paymentOptionKey(method));
  confirmVisible.value = true;
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
  // C-12：占位必须一直持有到下单结束。原来在 resolve 之后立刻把 confirmVisible 置回 false，
  // handleCreateOrder 还没跑完，重入守卫就提前开了口子；只有用户取消时才立即释放。
  if (!confirmed) {
    confirmVisible.value = false;
    return;
  }
  try {
    await handleCreateOrder(false);
  } finally {
    confirmVisible.value = false;
  }
}

onMounted(() => {
  void loadPaymentGateways();
});
</script>
<style src="./index.less" lang="less" scoped></style>
