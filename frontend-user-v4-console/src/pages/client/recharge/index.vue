<template>
  <section class="client-recharge">
    <t-card class="recharge-stage" :bordered="false">
      <div class="recharge-panel">
        <div class="panel-main">
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
                :step="10"
                :decimal-places="0"
                theme="column"
                suffix="元"
                @change="handleAmountChange"
              />
              <div class="amount-payable">
                <span>实付金额：</span>
                <strong>{{ amountText }} 元</strong>
              </div>
            </div>
          </div>

          <div class="field-block">
            <label class="field-label">选择支付方式</label>
            <div class="pay-methods">
              <t-button theme="primary" class="pay-method" :loading="submitting" @click="handleCreateOrder(isMobileScreen)">
                <template #icon><CreditcardIcon /></template>
                {{ isMobileScreen ? '打开支付宝 App 支付' : paymentButtonText }}
              </t-button>
            </div>

            <div v-if="isMobileScreen && qrCodeValue && !rechargePaid" class="mobile-pay-helper">
              <p>若未能自动打开支付宝，请复制支付链接，在手机浏览器中打开。</p>
              <t-button size="small" theme="warning" variant="outline" @click="copyPayUrl">
                复制支付链接
              </t-button>
            </div>
          </div>
        </div>

        <aside class="qrcode-panel">
          <div class="qrcode-frame" :class="{ 'is-ready': qrCodeValue, 'is-paid': rechargePaid }">
            <qrcode-vue
              v-if="qrCodeValue"
              class="qrcode-svg"
              :class="{ 'is-muted': rechargePaid }"
              :value="qrCodeValue"
              :size="160"
              level="H"
              render-as="svg"
            />
            <transition name="payment-success">
              <div v-if="rechargePaid" class="qrcode-success" aria-live="polite">
                <span class="success-icon">
                  <CheckCircleIcon />
                </span>
                <strong>充值成功</strong>
                <small>余额已刷新</small>
              </div>
            </transition>
            <div v-if="!qrCodeValue" class="qrcode-empty">
              <span class="empty-icon">¥</span>
              <p>选择金额后点击支付宝生成二维码</p>
            </div>
          </div>

          <div class="qrcode-meta">
            <p class="qrcode-title">{{ qrCodeTitle }}</p>
            <p v-if="qrCodeSubtitle" class="qrcode-subtitle">{{ qrCodeSubtitle }}</p>
            <div class="meta-row">
              <span>支付单号</span>
              <strong>{{ paymentNo || '--' }}</strong>
            </div>
            <div class="meta-row">
              <span>应付金额</span>
              <strong>{{ amountText }} 元</strong>
            </div>
          </div>
        </aside>
      </div>
    </t-card>
  </section>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import QrcodeVue from 'qrcode.vue';
import { CheckCircleIcon, CreditcardIcon } from 'tdesign-icons-vue-next';

import { RECHARGE_PRESET_AMOUNTS, useRecharge } from '@/domains/finance/useRecharge';

const isMobileScreen = computed(() => {
  if (typeof window === 'undefined') return false;
  return window.matchMedia('(max-width: 48rem)').matches;
});

const {
  inputAmount,
  activePreset,
  submitting,
  rechargePaid,
  amountText,
  qrCodeValue,
  paymentNo,
  paymentButtonText,
  qrCodeTitle,
  qrCodeSubtitle,
  selectPreset,
  handleAmountChange,
  handleCreateOrder,
  copyPayUrl,
} = useRecharge();
</script>

<style scoped lang="less">
.client-recharge {
  display: flex;
  flex-direction: column;
  gap: var(--td-comp-margin-m);
  padding: var(--td-comp-paddingTB-l) var(--td-comp-paddingLR-l);
}

.recharge-stage {
  background: var(--td-bg-color-container);
  border: thin solid var(--td-border-color);
  border-radius: var(--td-radius-medium);
  box-shadow: var(--td-shadow-1);
}

.recharge-panel {
  display: grid;
  grid-template-columns: minmax(0, 1.6fr) minmax(17rem, 0.75fr);
  gap: var(--td-comp-margin-xl);
  align-items: start;
  min-height: 21rem;
}

.panel-main {
  display: flex;
  flex-direction: column;
  gap: var(--td-comp-margin-l);
}

.amount-presets {
  display: flex;
  flex-wrap: wrap;
  gap: var(--td-comp-margin-s);
}

.preset-chip {
  min-width: 4.875rem;
}

.field-block {
  display: flex;
  flex-direction: column;
  gap: var(--td-comp-margin-s);
}

.field-label {
  color: var(--td-text-color-primary);
  font: var(--td-font-body-medium);
  font-weight: 600;
}

.amount-row,
.pay-methods {
  display: flex;
  flex-wrap: wrap;
  gap: var(--td-comp-margin-s);
  align-items: center;
}

.amount-input {
  width: min(100%, 18rem);
}

.amount-payable {
  display: inline-flex;
  gap: var(--td-comp-margin-xs);
  align-items: baseline;
  color: var(--td-text-color-secondary);
  font: var(--td-font-body-small);

  strong {
    color: var(--td-warning-color);
    font: var(--td-font-body-large);
    font-weight: 700;
  }
}

.pay-method {
  min-width: 13rem;
}

.mobile-pay-helper {
  display: grid;
  gap: var(--td-comp-margin-s);
  padding: var(--td-comp-paddingTB-m) var(--td-comp-paddingLR-m);
  color: var(--td-warning-color);
  background: var(--td-warning-color-light);
  border: thin dashed var(--td-warning-color);
  border-radius: var(--td-radius-medium);

  p {
    margin: 0;
    font: var(--td-font-body-small);
  }
}

.qrcode-panel {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--td-comp-margin-m);
}

.qrcode-frame {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 13.75rem;
  height: 13.75rem;
  padding: var(--td-comp-paddingTB-m);
  overflow: hidden;
  background: var(--td-bg-color-container);
  border: thin solid var(--td-border-color);
  border-radius: var(--td-radius-medium);
  box-shadow: var(--td-shadow-2);
  transition:
    border-color var(--td-anim-duration-base) var(--td-anim-time-fn-easing),
    box-shadow var(--td-anim-duration-base) var(--td-anim-time-fn-easing),
    transform var(--td-anim-duration-base) var(--td-anim-time-fn-easing);

  &.is-ready {
    border-color: var(--td-brand-color);
  }

  &.is-paid {
    border-color: var(--td-success-color);
    box-shadow: var(--td-shadow-3);
    transform: translateY(calc(-1 * var(--td-comp-margin-xxs)));
  }
}

.qrcode-svg {
  position: relative;
  z-index: 1;
  width: 100%;
  height: 100%;
  transition:
    opacity var(--td-anim-duration-base) var(--td-anim-time-fn-easing),
    transform var(--td-anim-duration-base) var(--td-anim-time-fn-easing);

  &.is-muted {
    opacity: 0.12;
    transform: scale(0.92);
  }
}

.qrcode-success {
  position: absolute;
  inset: 0;
  z-index: 2;
  display: flex;
  flex-direction: column;
  gap: var(--td-comp-margin-xs);
  align-items: center;
  justify-content: center;
  color: var(--td-success-color);
  text-align: center;

  strong {
    color: var(--td-success-color);
    font: var(--td-font-title-large);
    font-weight: 700;
  }

  small {
    color: var(--td-text-color-secondary);
    font: var(--td-font-body-small);
  }
}

.success-icon,
.empty-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: var(--td-radius-round);
}

.success-icon {
  width: var(--td-comp-size-xxxl);
  height: var(--td-comp-size-xxxl);
  color: var(--td-bg-color-container);
  font: var(--td-font-headline-large);
  background: var(--td-success-color);
  box-shadow: var(--td-shadow-2);
}

.qrcode-empty {
  display: flex;
  flex-direction: column;
  gap: var(--td-comp-margin-s);
  align-items: center;
  color: var(--td-text-color-secondary);
  text-align: center;

  p {
    max-width: 9.5rem;
    margin: 0;
    font: var(--td-font-body-small);
  }
}

.empty-icon {
  width: var(--td-comp-size-xxxl);
  height: var(--td-comp-size-xxxl);
  color: var(--td-brand-color);
  font: var(--td-font-headline-large);
  background: var(--td-brand-color-light);
}

.qrcode-meta {
  width: 100%;
  max-width: 17.5rem;
}

.qrcode-title,
.qrcode-subtitle {
  text-align: center;
}

.qrcode-title {
  margin: 0;
  color: var(--td-text-color-primary);
  font: var(--td-font-body-medium);
  font-weight: 600;
}

.qrcode-subtitle {
  margin: var(--td-comp-margin-xxs) 0 0;
  color: var(--td-text-color-secondary);
  font: var(--td-font-body-small);
}

.meta-row {
  display: flex;
  gap: var(--td-comp-margin-s);
  align-items: center;
  justify-content: space-between;
  padding: var(--td-comp-paddingTB-s) 0;
  color: var(--td-text-color-secondary);
  font: var(--td-font-body-small);
  border-bottom: thin dashed var(--td-border-color);

  strong {
    min-width: 0;
    overflow: hidden;
    color: var(--td-text-color-primary);
    text-align: right;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
}

.payment-success-enter-active,
.payment-success-leave-active {
  transition:
    opacity var(--td-anim-duration-base) var(--td-anim-time-fn-easing),
    transform var(--td-anim-duration-base) var(--td-anim-time-fn-easing);
}

.payment-success-enter-from,
.payment-success-leave-to {
  opacity: 0;
  transform: translateY(var(--td-comp-margin-xs)) scale(0.96);
}

@media (max-width: 75rem) {
  .recharge-panel {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 48rem) {
  .client-recharge {
    padding: var(--td-comp-paddingTB-m) var(--td-comp-paddingLR-s);
  }

  .recharge-panel {
    grid-template-columns: 1fr;
  }

  .preset-chip {
    flex: 1 1 calc(50% - var(--td-comp-margin-s));
  }

  .amount-input,
  .pay-method {
    width: 100%;
  }

  .amount-payable {
    width: 100%;
  }

  .qrcode-panel {
    display: none;
  }
}
</style>
