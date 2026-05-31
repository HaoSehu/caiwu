<template>
  <div class="client-page invoice-detail-page">
    <section class="detail-grid" v-loading="loading">
      <article class="detail-card">
        <div class="card-head">
          <h3>基础信息</h3>
          <el-button
            v-if="showPayActions"
            :disabled="paying"
            :loading="canceling"
            text
            type="danger"
            @click="handleCancel"
          >
            取消账单
          </el-button>
        </div>
        <el-descriptions :column="1" border>
          <el-descriptions-item label="账单号">{{ detail?.invoice_no || '--' }}</el-descriptions-item>
          <el-descriptions-item label="商品">{{ detail?.combined_display_name || detail?.product_display_name || '--' }}</el-descriptions-item>
          <el-descriptions-item label="账单类型">{{ detail?.type_label || '--' }}</el-descriptions-item>
          <el-descriptions-item label="状态">
            <el-tag :type="resolveInvoiceTagType(detail?.status)" effect="light">
              {{ detail?.status_label || '--' }}
            </el-tag>
          </el-descriptions-item>
          <el-descriptions-item label="创建时间">{{ detail?.created_at || '--' }}</el-descriptions-item>
          <el-descriptions-item label="截止时间">{{ detail?.due_date || '--' }}</el-descriptions-item>
        </el-descriptions>
      </article>

      <article class="detail-card">
        <h3>金额信息</h3>
        <el-descriptions :column="1" border>
          <el-descriptions-item label="账单金额">¥ {{ detail?.amount || '0.00' }}</el-descriptions-item>
          <el-descriptions-item label="优惠金额">¥ {{ detail?.discount || '0.00' }}</el-descriptions-item>
          <el-descriptions-item label="已支付">¥ {{ detail?.paid_amount || '0.00' }}</el-descriptions-item>
          <el-descriptions-item label="待支付">¥ {{ detail?.payable_amount || '0.00' }}</el-descriptions-item>
        </el-descriptions>
      </article>
    </section>

    <section v-if="detail" class="pay-stage">
      <article class="detail-card pay-card">
        <div class="card-head">
          <h3>支付操作</h3>
          <el-tag v-if="detail?.payment_security?.expires_at" effect="plain" type="info">
            会话截止 {{ detail.payment_security.expires_at }}
          </el-tag>
        </div>

        <div class="pay-main">
          <div class="pay-methods">
            <button
              v-for="method in payMethods"
              :key="method.key"
              type="button"
              class="pay-method"
              :class="{ 'is-active': selectedPayMethod === method.key }"
              :disabled="!canPay || paying || (method.key === 'alipay' && !alipayAvailable)"
              @click="selectPayMethod(method.key)"
            >
              <span class="pay-method__icon">
                <el-icon v-if="method.key === 'balance'"><Wallet /></el-icon>
                <el-icon v-else-if="method.key === 'alipay'"><CreditCard /></el-icon>
                <el-icon v-else><CircleCheckFilled /></el-icon>
              </span>
              <span class="pay-method__text">
                <strong>{{ method.name }}</strong>
                <small v-if="method.key === 'alipay' && !alipayAvailable">当前未启用</small>
                <small v-else-if="method.key === 'balance'">余额足够时可直接完成支付</small>
                <small v-else-if="method.key === 'alipay'">扫码支付，余额不足时可先抵扣余额</small>
                <small v-else>无需额外金额，确认后立即完成</small>
              </span>
            </button>
          </div>

          <div v-if="showBalanceDeductionOption" class="alipay-option-block">
            <el-checkbox v-model="allowBalanceDeduction" @change="handleDeductionToggle">
              允许使用余额进行抵扣
            </el-checkbox>
            <div v-if="allowBalanceDeduction" class="deduction-summary">
              <span>当前余额 {{ balanceText }}</span>
              <span>预计抵扣 ¥ {{ autoDeductionAmountText }}</span>
              <span>支付宝待付 ¥ {{ estimatedAlipayAmountText }}</span>
            </div>
          </div>

          <div class="pay-actions">
            <el-button
              v-if="selectedPayMethod === 'balance'"
              type="primary"
              :loading="paying"
              :disabled="!canPay"
              @click="handlePayByBalance"
            >
              余额支付
            </el-button>
            <el-button
              v-else-if="selectedPayMethod === 'alipay'"
              type="primary"
              :loading="paying"
              :disabled="!canPay || !alipayAvailable"
              @click="handlePayByAlipay"
            >
              支付
            </el-button>
            <el-button
              v-else-if="selectedPayMethod === 'free'"
              type="primary"
              :disabled="true"
            >
              零元账单无需操作
            </el-button>
            <el-button :disabled="paying || loading" @click="loadDetail">
              刷新详情
            </el-button>
          </div>

          <el-alert
            v-if="payTip"
            :title="payTip"
            type="info"
            :closable="false"
            show-icon
          />
        </div>
      </article>
    </section>

    <el-dialog
      v-model="alipayDialogVisible"
      title="支付宝支付"
      width="360px"
      align-center
      destroy-on-close
      class="alipay-dialog"
    >
      <div class="dialog-qrcode">
        <qrcode-vue
          v-if="alipayQrCode"
          :value="alipayQrCode"
          :size="180"
          level="H"
          render-as="svg"
        />
      </div>
      <div class="dialog-meta">
        <p>金额 ¥ {{ alipayPayableAmount }}</p>
        <p>支付单号 {{ alipayPaymentNo || '--' }}</p>
      </div>
      <template #footer>
        <el-button
          type="primary"
          :loading="polling"
          :disabled="!alipayPollingReady"
          @click="pollAlipayStatus"
        >
          我已完成支付，刷新状态
        </el-button>
      </template>
    </el-dialog>

    <section v-if="detail?.payment_summary" class="detail-card">
      <h3>支付信息</h3>
      <el-descriptions :column="1" border>
        <el-descriptions-item label="支付方式">{{ detail.payment_summary.gateway_label || '--' }}</el-descriptions-item>
        <el-descriptions-item label="支付状态">{{ detail.payment_summary.status_label || '--' }}</el-descriptions-item>
        <el-descriptions-item label="支付单号">{{ detail.payment_summary.payment_no || '--' }}</el-descriptions-item>
        <el-descriptions-item label="支付金额">¥ {{ detail.payment_summary.amount || '0.00' }}</el-descriptions-item>
      </el-descriptions>
    </section>

    <el-empty v-if="!loading && !detail" description="未找到账单详情" />
  </div>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { CircleCheckFilled, CreditCard, Wallet } from '@element-plus/icons-vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import QrcodeVue from 'qrcode.vue'
import clientApi from '@/api/client'
import { useUserStore } from '@/stores/user'

type PayMethodKey = 'balance' | 'alipay' | 'free'

const route = useRoute()
const userStore = useUserStore()

const loading = ref(false)
const canceling = ref(false)
const paying = ref(false)
const polling = ref(false)
const detail = ref<any>(null)
const selectedPayMethod = ref<PayMethodKey>('balance')
const allowBalanceDeduction = ref(false)
const alipayDialogVisible = ref(false)
const alipayQrCode = ref('')
const alipayPaymentNo = ref('')
const alipayPollToken = ref('')
const appliedDeductionAmount = ref('0.00')
const alipayAmount = ref('0.00')
const pollTimer = ref<number | null>(null)

const payMethods = computed(() => Array.isArray(detail.value?.pay_methods) ? detail.value.pay_methods : [])
const paySecurity = computed(() => detail.value?.payment_security || {})
const canPay = computed(() => Boolean(paySecurity.value?.can_pay) && (detail.value?.status === 0 || detail.value?.status === 3))
const alipayAvailable = computed(() => payMethods.value.some((item: any) => item.key === 'alipay'))
const alipayPollingReady = computed(() => Boolean(alipayPaymentNo.value && alipayPollToken.value))
const balanceAmount = computed(() => normalizeMoney(Number(userStore.info?.balance || 0)))
const payableAmount = computed(() => normalizeMoney(Number(detail.value?.payable_amount || 0)))
const canDeductBalance = computed(() => balanceAmount.value > 0 && balanceAmount.value < payableAmount.value)
const autoDeductionAmount = computed(() => normalizeMoney(Math.min(balanceAmount.value, payableAmount.value)))
const estimatedAlipayAmount = computed(() => normalizeMoney(Math.max(payableAmount.value - autoDeductionAmount.value, 0)))
const balanceText = computed(() => `¥ ${formatMoney(balanceAmount.value)}`)
const autoDeductionAmountText = computed(() => formatMoney(autoDeductionAmount.value))
const estimatedAlipayAmountText = computed(() => formatMoney(estimatedAlipayAmount.value))
const appliedDeductionAmountText = computed(() => formatMoney(appliedDeductionAmount.value))
const hasAppliedBalanceDeduction = computed(() => Number(appliedDeductionAmount.value || 0) > 0)
const alipayPayableAmount = computed(() => alipayAmount.value || formatMoney(payableAmount.value))
const showBalanceDeductionOption = computed(() => selectedPayMethod.value === 'alipay' && canDeductBalance.value)
const payTip = computed(() => {
  if (!canPay.value) return '当前账单状态不支持继续支付。'
  if (selectedPayMethod.value === 'alipay' && allowBalanceDeduction.value) {
    return `将自动抵扣余额 ¥ ${autoDeductionAmountText.value}，支付宝支付剩余 ¥ ${estimatedAlipayAmountText.value}。`
  }
  if (selectedPayMethod.value === 'alipay') return '生成二维码后请使用支付宝扫码完成支付，系统会自动轮询状态。'
  if (selectedPayMethod.value === 'balance') return '余额支付会直接扣减账户余额并完成账单。'
  return '零元账单已无需额外支付，刷新即可查看最新状态。'
})
const showPayActions = computed(() => Boolean(detail.value && (detail.value.status === 0 || detail.value.status === 3)))

function normalizeMoney(value: number) {
  if (!Number.isFinite(value)) return 0
  return Math.max(0, Math.round(value * 100) / 100)
}

function formatMoney(value: number | string) {
  const numericValue = Number(value || 0)
  return Number.isFinite(numericValue) ? numericValue.toFixed(2) : '0.00'
}

function resolveInvoiceTagType(status: number) {
  if (status === 1) return 'success'
  if (status === 0) return 'warning'
  if (status === 5) return 'info'
  return 'danger'
}

function clearPollingTimer() {
  if (pollTimer.value) {
    window.clearInterval(pollTimer.value)
    pollTimer.value = null
  }
}

function resetPaymentPayload() {
  alipayDialogVisible.value = false
  alipayQrCode.value = ''
  alipayPaymentNo.value = ''
  alipayPollToken.value = ''
  appliedDeductionAmount.value = '0.00'
  alipayAmount.value = formatMoney(payableAmount.value)
  clearPollingTimer()
}

function syncPayMethod() {
  if (!payMethods.value.length) return

  if (payMethods.value.some((item: any) => item.key === selectedPayMethod.value)) {
    return
  }

  selectedPayMethod.value = payMethods.value[0]?.key || 'balance'
}

function selectPayMethod(methodKey: PayMethodKey) {
  selectedPayMethod.value = methodKey
  allowBalanceDeduction.value = false
  resetPaymentPayload()
}

function handleDeductionToggle() {
  resetPaymentPayload()
}

async function refreshClientInfo() {
  try {
    await userStore.fetchUserInfo('client')
  } catch {
    // 保持当前页面状态，余额信息下次刷新时再同步
  }
}

function applyAlipayPayload(payload: Record<string, any>, usedBalanceDeduction: boolean) {
  alipayQrCode.value = String(payload.qr_code || '')
  alipayPaymentNo.value = String(payload.payment_no || '')
  alipayPollToken.value = String(payload.poll_token || '')
  appliedDeductionAmount.value = usedBalanceDeduction ? String(payload.balance_amount || autoDeductionAmountText.value) : '0.00'
  alipayAmount.value = String(payload.amount || estimatedAlipayAmountText.value || detail.value?.payable_amount || '0.00')
  alipayDialogVisible.value = Boolean(alipayQrCode.value)
}

async function loadDetail() {
  const id = Number(route.params.id || 0)
  if (!id) return
  loading.value = true
  try {
    const response = await clientApi.invoiceDetail(id)
    detail.value = response.data || null
    alipayAmount.value = formatMoney(payableAmount.value)
    syncPayMethod()

    if (!userStore.info || userStore.userType !== 'client') {
      await refreshClientInfo()
    }

    if (detail.value?.status === 1) {
      resetPaymentPayload()
    }
  } catch (error: any) {
    if (!error?.__handled) ElMessage.error(error?.message || '账单详情加载失败')
  } finally {
    loading.value = false
  }
}

async function handleCancel() {
  const id = Number(route.params.id || 0)
  if (!id) return

  try {
    await ElMessageBox.confirm('取消后需重新创建账单才能继续支付，确认取消吗？', '取消账单', {
      type: 'warning',
    })
  } catch {
    return
  }

  canceling.value = true
  try {
    await clientApi.cancelInvoice(id)
    ElMessage.success('账单已取消')
    resetPaymentPayload()
    await loadDetail()
  } catch (error: any) {
    if (!error?.__handled) ElMessage.error(error?.message || '取消账单失败')
  } finally {
    canceling.value = false
  }
}

async function handlePayByBalance() {
  const id = Number(route.params.id || 0)
  const sessionToken = String(paySecurity.value?.session_token || '')
  if (!id || !sessionToken) {
    ElMessage.warning('支付会话已失效，请刷新页面后重试')
    return
  }

  paying.value = true
  try {
    const response = await clientApi.payInvoiceByBalance(id, {
      payment_session_token: sessionToken,
    })
    ElMessage.success('账单已支付')
    resetPaymentPayload()
    await refreshClientInfo()
    detail.value = response.data?.invoice || detail.value
    await loadDetail()
  } catch (error: any) {
    if (!error?.__handled) ElMessage.error(error?.message || '余额支付失败')
  } finally {
    paying.value = false
  }
}

async function handlePayByAlipay() {
  const id = Number(route.params.id || 0)
  const sessionToken = String(paySecurity.value?.session_token || '')
  if (!id || !sessionToken) {
    ElMessage.warning('支付会话已失效，请刷新页面后重试')
    return
  }

  if (allowBalanceDeduction.value && balanceAmount.value >= payableAmount.value) {
    await handlePayByBalance()
    return
  }

  const shouldUseBalanceDeduction = allowBalanceDeduction.value && canDeductBalance.value

  paying.value = true
  try {
    const response = shouldUseBalanceDeduction
      ? await clientApi.payInvoiceByBalanceAndAlipay(id, {
          payment_session_token: sessionToken,
          balance_amount: autoDeductionAmount.value,
        })
      : await clientApi.payInvoiceByAlipay(id, {
          payment_session_token: sessionToken,
        })
    const payload = response.data || {}
    applyAlipayPayload(payload, shouldUseBalanceDeduction)

    if (alipayQrCode.value) {
      ElMessage.success('支付宝二维码已生成')
      clearPollingTimer()
      pollTimer.value = window.setInterval(() => {
        if (!polling.value && alipayPollingReady.value) {
          void pollAlipayStatus(true)
        }
      }, 5000)
    }
  } catch (error: any) {
    if (!error?.__handled) ElMessage.error(error?.message || '生成支付宝二维码失败')
  } finally {
    paying.value = false
  }
}

async function pollAlipayStatus(silent = false) {
  const id = Number(route.params.id || 0)
  if (!id || !alipayPollingReady.value) return

  polling.value = true
  try {
    const response = await clientApi.queryInvoiceAlipayStatus(id, {
      payment_no: alipayPaymentNo.value,
      poll_token: alipayPollToken.value,
    })
    if (response.data?.paid) {
      clearPollingTimer()
      resetPaymentPayload()
      ElMessage.success('账单已支付')
      await refreshClientInfo()
      detail.value = response.data?.invoice || detail.value
      await loadDetail()
    } else if (!silent) {
      ElMessage.info(response.data?.message || '当前仍未支付成功')
    }
  } catch (error: any) {
    if (!error?.__handled) ElMessage.error(error?.message || '查询支付状态失败')
  } finally {
    polling.value = false
  }
}

watch(() => detail.value?.status, (status) => {
  if (status === 1) {
    clearPollingTimer()
    resetPaymentPayload()
  }
})

watch(() => [detail.value?.payable_amount, userStore.info?.balance], () => {
  if (!showBalanceDeductionOption.value) {
    allowBalanceDeduction.value = false
  }

  if (!alipayQrCode.value) {
    alipayAmount.value = formatMoney(payableAmount.value)
  }
})

onMounted(() => {
  void loadDetail()
})

onBeforeUnmount(() => {
  clearPollingTimer()
})
</script>

<style scoped lang="scss">
.invoice-detail-page {
  gap: 20px;
}

.detail-grid,
.pay-stage {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 16px;
}

.detail-card {
  padding: 0;
  overflow: hidden;

  h3 {
    margin: 0 0 16px;
    color: $text-color-primary;
    font-size: 16px;
    font-weight: 600;
  }
}

.card-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 16px;
}

.pay-card {
  grid-column: 1 / -1;
}

.pay-main {
  display: flex;
  flex-direction: column;
  gap: 14px;
  min-width: 0;
}

.pay-methods {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
}

.pay-method {
  display: flex;
  align-items: center;
  gap: 12px;
  width: 100%;
  padding: 14px 16px;
  border: 1px solid $border-color;
  border-radius: $sm-border-radius;
  background: $bg-color-soft;
  cursor: pointer;
  text-align: left;

  &.is-active {
    border-color: $color-primary;
    background: rgba($color-primary, 0.08);
  }

  &:disabled {
    cursor: not-allowed;
    opacity: 0.6;
  }
}

.pay-method__icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background: #fff;
  color: $color-primary;
  flex: 0 0 auto;
}

.pay-method__text {
  display: flex;
  flex-direction: column;
  gap: 4px;
  min-width: 0;

  strong {
    color: $text-color-primary;
    font-size: 14px;
    font-weight: 600;
  }

  small {
    color: $text-color-secondary;
    font-size: 12px;
  }
}

.alipay-option-block {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.deduction-summary {
  display: flex;
  flex-wrap: wrap;
  gap: 10px 16px;
  padding: 12px 14px;
  border: 1px solid rgba(229, 234, 243, 0.92);
  border-radius: $sm-border-radius;
  background: $bg-color-soft;
  color: $text-color-secondary;
  font-size: 13px;
}

.pay-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
}

.dialog-qrcode {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 208px;
  padding: 12px;
}

.dialog-meta {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 0 8px;
  text-align: center;
}

.dialog-meta p {
  margin: 0;
  color: $text-color-secondary;
  font-size: 13px;
  word-break: break-all;
}

@media (max-width: 960px) {
  .detail-grid,
  .pay-stage {
    grid-template-columns: 1fr;
  }

  .pay-methods {
    grid-template-columns: 1fr;
  }
}
</style>
