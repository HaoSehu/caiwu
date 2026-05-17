<template>
  <div class="client-page recharge-page">
    <section class="recharge-summary" v-loading="summaryLoading">
      <article
        v-for="item in summaryCards"
        :key="item.key"
        class="summary-card"
      >
        <div class="summary-head">
          <span class="summary-label">{{ item.label }}</span>
          <button
            v-if="item.quickFilter"
            type="button"
            class="summary-link-button"
            :aria-label="`查看${item.label}对应服务`"
            @click="openServiceQuickFilter(item.quickFilter)"
          >
            <el-icon><Tickets /></el-icon>
          </button>
        </div>
        <strong class="summary-value">
          {{ item.value }}
          <small>{{ item.suffix }}</small>
        </strong>
        <span v-if="item.hint" class="summary-hint">{{ item.hint }}</span>
      </article>
    </section>

    <section class="recharge-stage">
      <article class="recharge-panel">
        <div class="panel-main">
          <div class="amount-presets">
            <button
              v-for="item in presetAmounts"
              :key="item"
              type="button"
              class="preset-chip"
              :class="{ 'is-active': activePreset === item }"
              @click="selectPreset(item)"
            >
              ¥{{ item }}
            </button>
          </div>

          <div class="field-block">
            <label class="field-label">充值数量</label>
            <div class="amount-row">
              <el-input-number
                v-model="inputAmount"
                class="amount-input"
                :min="1"
                :max="50000"
                :step="10"
                :precision="0"
                controls-position="right"
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
              <button
                type="button"
                class="pay-method is-alipay"
                @click="handleCreateOrder"
              >
                <span class="pay-icon">支</span>
                <span class="pay-text">{{ paymentButtonText }}</span>
                <span class="pay-arrow">›</span>
              </button>
            </div>
          </div>

        </div>

        <aside class="qrcode-panel">
          <div class="qrcode-frame" :class="{ 'is-ready': qrCodeValue }">
            <qrcode-vue
              v-if="qrCodeValue"
              :value="qrCodeValue"
              :size="160"
              level="H"
              render-as="svg"
            />
            <div v-else class="qrcode-empty">
              <span class="empty-icon">¥</span>
              <p>选择金额后点击支付宝生成二维码</p>
            </div>
          </div>

          <div class="qrcode-meta">
            <p class="qrcode-title">{{ qrCodeTitle }}</p>
            <p v-if="qrCodeSubtitle" class="qrcode-subtitle">{{ qrCodeSubtitle }}</p>
            <div class="meta-row">
              <span>支付单号</span>
              <strong>{{ paymentPayload?.payment_no || '--' }}</strong>
            </div>
            <div class="meta-row">
              <span>应付金额</span>
              <strong>{{ amountText }} 元</strong>
            </div>
          </div>
        </aside>
      </article>
    </section>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { Tickets } from '@element-plus/icons-vue'
import { useRouter } from 'vue-router'
import QrcodeVue from 'qrcode.vue'
import { useRecharge } from '@/composables/useRecharge'

const presetAmounts = [10, 20, 50, 100, 200, 500]
const inputAmount = ref(10)
const activePreset = ref(10)
const router = useRouter()

const {
  amount,
  paymentPayload,
  summaryLoading,
  summaryCards,
  createRechargeOrder,
  startAutoPolling,
  stopAutoPolling,
  clearPaymentPayload,
  loadRechargeSummary,
} = useRecharge()

const amountText = computed(() => Number(inputAmount.value || 0).toFixed(0))
const qrCodeValue = computed(() => String(paymentPayload.value?.qr_code || ''))
const paymentButtonText = computed(() => paymentPayload.value ? '支付宝支付' : '生成支付宝二维码')
const qrCodeTitle = computed(() => qrCodeValue.value ? '请使用支付宝扫码支付' : '支付二维码待生成')
const qrCodeSubtitle = computed(() => qrCodeValue.value ? '' : '当前充值接口仅支持支付宝扫码支付。')

function normalizeAmount(rawValue) {
  const numericValue = Number(rawValue || 0)
  if (!Number.isFinite(numericValue)) return 10
  return Math.min(50000, Math.max(1, Math.round(numericValue)))
}

function syncPresetState(nextAmount) {
  activePreset.value = presetAmounts.includes(nextAmount) ? nextAmount : null
}

function selectPreset(value) {
  inputAmount.value = value
  activePreset.value = value
}

function handleAmountChange(value) {
  const normalizedValue = normalizeAmount(value)
  if (normalizedValue !== value) {
    inputAmount.value = normalizedValue
  }
  syncPresetState(normalizedValue)
}

function openServiceQuickFilter(quickFilter) {
  const targetFilter = String(quickFilter || '').trim()
  if (!targetFilter) return

  router.push({
    path: '/client/services',
    query: {
      quick_filter: targetFilter,
    },
  })
}

async function handleCreateOrder() {
  const normalizedValue = normalizeAmount(inputAmount.value)
  inputAmount.value = normalizedValue
  amount.value = normalizedValue

  const result = await createRechargeOrder(normalizedValue)
  if (result?.qr_code) {
    startAutoPolling()
  }
}

watch(inputAmount, (value) => {
  const normalizedValue = normalizeAmount(value)
  amount.value = normalizedValue
  syncPresetState(normalizedValue)

  if (paymentPayload.value && normalizedValue !== Number(paymentPayload.value.amount || 0)) {
    clearPaymentPayload()
  }
}, { immediate: true })

onMounted(() => {
  void loadRechargeSummary()
})

onBeforeUnmount(() => {
  stopAutoPolling()
})
</script>

<style scoped lang="scss">
.recharge-page {
  gap: 12px;
}

.recharge-summary {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0;
  padding: 8px 0;
  border: 1px solid rgba(229, 234, 243, 0.92);
  border-radius: 16px;
  background: rgba(255, 255, 255, 0.96);
  box-shadow: $shadow-sm;
}

.summary-card {
  display: flex;
  flex-direction: column;
  gap: 6px;
  min-width: 0;
  padding: 6px 22px 8px;
  position: relative;

  &:not(:last-child)::after {
    content: '';
    position: absolute;
    top: 8px;
    right: 0;
    width: 1px;
    height: calc(100% - 16px);
    background: rgba(229, 234, 243, 0.92);
  }
}

.summary-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
}

.summary-label {
  color: $text-color-secondary;
  font-size: 12px;
  line-height: 1.5;
}

.summary-link-button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 24px;
  height: 24px;
  border: 1px solid rgba(76, 132, 255, 0.14);
  border-radius: 8px;
  background: rgba(76, 132, 255, 0.08);
  color: $color-primary;
  cursor: pointer;
  transition:
    background-color $motion-base ease,
    border-color $motion-base ease,
    transform $motion-base ease;

  &:hover {
    border-color: rgba(76, 132, 255, 0.26);
    background: rgba(76, 132, 255, 0.14);
    transform: translateY(-1px);
  }
}

.summary-value {
  color: $text-color-primary;
  font-size: 18px;
  font-weight: 700;
  line-height: 1.2;

  small {
    margin-left: 4px;
    color: $text-color-secondary;
    font-size: 11px;
    font-weight: 500;
  }
}

.summary-hint {
  color: $text-color-secondary;
  font-size: 11px;
  line-height: 1.5;
}

.recharge-stage {
  border-radius: 16px;
  background:
    linear-gradient(180deg, rgba(255, 255, 255, 0.96), rgba(248, 250, 252, 0.9)),
    radial-gradient(circle at top left, rgba(22, 93, 255, 0.06), transparent 32%);
  box-shadow: $shadow-md;
}

.recharge-panel {
  display: grid;
  grid-template-columns: minmax(0, 1.6fr) minmax(220px, 0.75fr);
  gap: 28px;
  align-items: start;
  min-height: 340px;
  padding: 20px 22px;
  border: 1px solid rgba(229, 234, 243, 0.92);
  border-radius: 16px;
}

.panel-main {
  display: flex;
  flex-direction: column;
  gap: 20px;
  padding-top: 2px;
}

.amount-presets {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
}

.preset-chip {
  min-width: 78px;
  height: 38px;
  border: 1px solid $border-color;
  border-radius: 12px;
  background: #fff;
  color: $text-color-primary;
  font-size: 15px;
  font-weight: 700;
  cursor: pointer;
  transition:
    border-color $motion-base ease,
    box-shadow $motion-base ease,
    color $motion-base ease,
    transform $motion-base ease;

  &:hover {
    border-color: $color-primary-border;
    box-shadow: 0 8px 16px rgba(22, 93, 255, 0.08);
    transform: translateY(-1px);
  }

  &.is-active {
    border-color: $color-primary;
    color: $color-primary;
    box-shadow: 0 0 0 2px rgba(22, 93, 255, 0.1);
  }
}

.field-block {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.field-label {
  color: $text-color-primary;
  font-size: 14px;
  font-weight: 600;
}

.amount-row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 12px;
}

.amount-input {
  width: 280px;

  :deep(.el-input-number) {
    width: 100%;
  }

  :deep(.el-input__wrapper) {
    height: 40px;
    padding: 0 14px;
    border-radius: 12px !important;
    background: #f7f8fa !important;
    box-shadow: 0 0 0 1px transparent inset !important;
  }

  :deep(.el-input__inner) {
    font-size: 16px;
    font-weight: 600;
  }

  :deep(.el-input-number__increase),
  :deep(.el-input-number__decrease) {
    width: 28px;
    color: $text-color-secondary !important;
    background: #fff !important;
  }
}

.amount-payable {
  display: inline-flex;
  align-items: baseline;
  gap: 6px;
  color: $text-color-secondary;
  font-size: 13px;

  strong {
    color: #f15f3b;
    font-size: 16px;
    font-weight: 700;
  }
}

.pay-methods {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
}

.pay-method {
  position: relative;
  display: inline-flex;
  align-items: center;
  gap: 10px;
  min-width: 160px;
  height: 42px;
  padding: 0 16px;
  border: 0;
  border-radius: 12px;
  color: #fff;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition:
    transform $motion-base cubic-bezier(0.22, 1, 0.36, 1),
    box-shadow $motion-base cubic-bezier(0.22, 1, 0.36, 1),
    opacity $motion-base ease;

  &:hover:not(:disabled) {
    transform: translateY(-1px);
  }

  &:disabled {
    cursor: not-allowed;
    opacity: 0.78;
  }

  &.is-alipay {
    background: linear-gradient(135deg, #4c82f2, #2f67de);
    box-shadow: 0 10px 18px rgba(61, 115, 231, 0.2);
  }
}

.pay-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 18px;
  height: 18px;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.16);
  font-size: 11px;
  font-weight: 700;
}

.pay-text {
  flex: 1;
  text-align: left;
}

.pay-arrow {
  font-size: 16px;
  line-height: 1;
  opacity: 0.82;
}

.qrcode-panel {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
  padding: 4px 0 0;
}

.qrcode-frame {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 220px;
  height: 220px;
  padding: 14px;
  border: 1px solid rgba(31, 41, 55, 0.16);
  border-radius: 8px;
  background: #fff;
  box-shadow: 0 12px 24px rgba(15, 23, 42, 0.08);

  &.is-ready {
    border-color: rgba(31, 41, 55, 0.28);
  }

  :deep(svg) {
    width: 100%;
    height: 100%;
  }
}

.qrcode-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  color: $text-color-secondary;
  text-align: center;
  line-height: 1.6;

  p {
    max-width: 150px;
    font-size: 12px;
  }
}

.empty-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 44px;
  height: 44px;
  border-radius: 12px;
  background: $color-primary-soft;
  color: $color-primary;
  font-size: 24px;
  font-weight: 700;
}

.qrcode-meta {
  width: 100%;
  max-width: 280px;
}

.qrcode-title {
  color: $text-color-primary;
  font-size: 14px;
  font-weight: 600;
  text-align: center;
}

.qrcode-subtitle {
  margin-top: 4px;
  color: $text-color-secondary;
  font-size: 12px;
  line-height: 1.5;
  text-align: center;
}

.meta-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  padding: 10px 0;
  border-bottom: 1px dashed $divider-color;
  color: $text-color-secondary;
  font-size: 12px;

  span {
    flex: 0 0 auto;
  }

  strong {
    min-width: 0;
    white-space: nowrap;
    word-break: normal;
    overflow: hidden;
    text-overflow: ellipsis;
    color: $text-color-primary;
    font-size: 12px;
    text-align: right;
  }
}

@media (max-width: 1200px) {
  .recharge-summary {
    grid-template-columns: 1fr;
  }

  .summary-card {
    padding: 10px 16px;

    &:not(:last-child)::after {
      top: auto;
      right: auto;
      left: 16px;
      bottom: 0;
      width: calc(100% - 32px);
      height: 1px;
    }
  }

  .recharge-panel {
    grid-template-columns: 1fr;
    gap: 22px;
  }

  .qrcode-panel {
    padding-top: 0;
  }
}

@media (max-width: 767px) {
  .recharge-panel {
    padding: 16px 14px;
    border-radius: 14px;
  }

  .summary-card {
    padding: 10px 14px;
  }

  .amount-presets {
    gap: 8px;
  }

  .preset-chip {
    min-width: calc(50% - 4px);
    height: 40px;
    font-size: 15px;
  }

  .amount-input {
    width: 100%;
  }

  .amount-payable {
    width: 100%;
  }

  .pay-method {
    width: 100%;
    min-width: 0;
  }

  .qrcode-frame {
    width: min(100%, 220px);
    height: min(100vw - 88px, 220px);
  }
}
</style>
