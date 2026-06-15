<template>
  <div class="client-page checkout-resume-page">
    <section class="resume-card">
      <el-result
        :icon="resultIcon"
        :title="resultTitle"
        :sub-title="resultSubtitle"
      >
        <template #extra>
          <div class="resume-actions">
            <el-button v-if="status === 'error'" type="primary" :loading="submitting" @click="resumeCheckout">
              重新创建账单
            </el-button>
            <el-button v-if="status === 'empty'" type="primary" @click="router.push('/products')">
              去选购产品
            </el-button>
            <el-button v-if="status !== 'loading'" @click="router.push('/client/invoices')">
              前往资金中心
            </el-button>
          </div>
        </template>
      </el-result>

      <div v-if="pendingCheckout" class="resume-payload">
        <div class="resume-payload__row">
          <span>来源场景</span>
          <strong>{{ pendingCheckout.source || '--' }}</strong>
        </div>
        <div class="resume-payload__row">
          <span>商品 ID</span>
          <strong>{{ pendingCheckout.orderPayload?.product_id || '--' }}</strong>
        </div>
        <div class="resume-payload__row">
          <span>计费周期</span>
          <strong>{{ pendingCheckout.orderPayload?.billing_cycle || '--' }}</strong>
        </div>
        <div class="resume-payload__row">
          <span>购买数量</span>
          <strong>{{ pendingCheckout.orderPayload?.quantity || 1 }}</strong>
        </div>
      </div>
    </section>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import clientApi from '@/api/client'
import { clearPendingWebsiteCheckout, getPendingWebsiteCheckout } from '@/utils/websiteCheckout'

const router = useRouter()
const submitting = ref(false)
const status = ref<'loading' | 'success' | 'error' | 'empty'>('loading')
const pendingCheckout = ref<any>(null)

const resultIcon = computed(() => {
  if (status.value === 'success') return 'success'
  if (status.value === 'error') return 'error'
  if (status.value === 'empty') return 'warning'
  return 'info'
})

const resultTitle = computed(() => {
  if (status.value === 'success') return '账单创建成功'
  if (status.value === 'error') return '恢复下单失败'
  if (status.value === 'empty') return '没有待恢复的下单信息'
  return '正在恢复下单'
})

const resultSubtitle = computed(() => {
  if (status.value === 'success') return '账单已创建，系统即将跳转到支付页面。'
  if (status.value === 'error') return '你可以点击重新创建账单，或返回产品页重新发起购买。'
  if (status.value === 'empty') return '当前会话中没有发现待继续的购买记录。'
  return '请稍候，系统正在读取登录前保存的商品配置并恢复账单创建流程。'
})

async function resumeCheckout() {
  pendingCheckout.value = getPendingWebsiteCheckout()

  if (!pendingCheckout.value?.orderPayload) {
    status.value = 'empty'
    return
  }

  submitting.value = true
  status.value = 'loading'

  try {
    const response = await clientApi.createInvoice(
      pendingCheckout.value.orderPayload,
      {
        headers: pendingCheckout.value.idempotencyKey
          ? { 'X-Idempotency-Key': pendingCheckout.value.idempotencyKey }
          : undefined,
      },
    )

    const invoiceId = Number(response.data?.id || 0)
    clearPendingWebsiteCheckout()
    status.value = 'success'
    ElMessage.success('账单创建成功，正在跳转')
    await router.replace(invoiceId > 0 ? `/client/invoices/${invoiceId}` : '/client/invoices')
  } catch (error: any) {
    status.value = 'error'
    if (!error?.__handled) {
      ElMessage.error(error?.message || '恢复下单失败')
    }
  } finally {
    submitting.value = false
  }
}

onMounted(() => {
  void resumeCheckout()
})
</script>

<style scoped lang="scss">
.resume-card {
  padding: 24px;
  border: 1px solid $border-color;
  border-radius: $base-border-radius;
  background: #fff;
  box-shadow: $shadow-sm;
}

.resume-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  justify-content: center;
}

.resume-payload {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
  margin-top: 20px;
}

.resume-payload__row {
  padding: 14px 16px;
  border: 1px solid $border-color;
  border-radius: $sm-border-radius;
  background: $bg-color-soft;

  span {
    display: block;
    color: $text-color-secondary;
    font-size: 12px;
  }

  strong {
    display: block;
    margin-top: 8px;
    color: $text-color-primary;
    font-size: 14px;
    font-weight: 600;
    word-break: break-word;
  }
}

@media (max-width: 767px) {
  .resume-payload {
    grid-template-columns: 1fr;
  }
}
</style>
