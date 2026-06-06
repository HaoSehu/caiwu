<template>
  <el-dialog v-model="dialogVisible" title="退款操作" width="560px" destroy-on-close @closed="resetForm">
    <el-alert type="warning" :closable="false" show-icon title="退款将退回用户余额或原路退回，当前仅支持全额退款。" style="margin-bottom: 16px;" />

    <el-form ref="formRef" :model="form" :rules="rules" label-width="100px">
      <el-form-item label="账单号">
        <el-input :model-value="invoiceNo" disabled />
      </el-form-item>

      <el-form-item label="退款金额">
        <el-input :model-value="refundAmountText" disabled />
      </el-form-item>

      <el-form-item label="退款方式" prop="refund_method">
        <el-radio-group v-model="form.refund_method">
          <el-radio value="balance">退回余额</el-radio>
          <el-radio value="original" :disabled="!canOriginalRefund">原路退款</el-radio>
        </el-radio-group>
        <div v-if="!canOriginalRefund" class="refund-hint">当前不支持原路退款（余额支付或无对应支付宝记录）</div>
      </el-form-item>

      <el-form-item label="联动退款">
        <el-checkbox-group v-model="form.scope">
          <el-checkbox value="order" :disabled="!hasOrder">
            同时标记订单为已退款
            <span v-if="orderNo" class="scope-hint">（{{ orderNo }}）</span>
          </el-checkbox>
          <el-checkbox value="payment" :disabled="!hasPayment">
            同时标记支付单为已退款
            <span v-if="paymentNo" class="scope-hint">（{{ paymentNo }}）</span>
          </el-checkbox>
        </el-checkbox-group>
      </el-form-item>

      <el-form-item label="退款原因" prop="remark">
        <el-input v-model="form.remark" type="textarea" :rows="3" maxlength="200" show-word-limit placeholder="请输入退款原因" />
      </el-form-item>
    </el-form>

    <template #footer>
      <el-button @click="dialogVisible = false">取消</el-button>
      <el-button type="danger" :loading="submitting" @click="handleSubmit">确认退款</el-button>
    </template>
  </el-dialog>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { ElMessage } from 'element-plus'
import userApi from '@/api/user'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  userId: { type: [Number, String], default: 0 },
  invoiceId: { type: [Number, String], default: 0 },
  invoiceNo: { type: String, default: '--' },
  amount: { type: [Number, String], default: 0 },
  orderNo: { type: String, default: '' },
  paymentNo: { type: String, default: '' },
  hasOrder: { type: Boolean, default: false },
  hasPayment: { type: Boolean, default: false },
  canOriginalRefund: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'success'])

const dialogVisible = computed({
  get: () => props.modelValue,
  set: (v) => emit('update:modelValue', v),
})

const formRef = ref(null)
const submitting = ref(false)

const form = reactive({
  refund_method: 'balance',
  remark: '',
  scope: ['order', 'payment'],
})

const rules = {
  refund_method: [{ required: true, message: '请选择退款方式', trigger: 'change' }],
  remark: [{ required: true, message: '请填写退款原因', trigger: 'blur' }],
}

const refundAmountText = computed(() => `¥${Number(props.amount || 0).toFixed(2)}`)

watch(() => props.modelValue, (v) => {
  if (v) {
    form.scope = []
    if (props.hasOrder) form.scope.push('order')
    if (props.hasPayment) form.scope.push('payment')
  }
})

function resetForm() {
  form.refund_method = 'balance'
  form.remark = ''
  form.scope = ['order', 'payment']
  formRef.value?.clearValidate?.()
}

async function handleSubmit() {
  const valid = await formRef.value?.validate().catch(() => false)
  if (!valid) return

  submitting.value = true
  try {
    await userApi.refundInvoice(props.userId, props.invoiceId, {
      refund_method: form.refund_method,
      amount: Number(props.amount) || undefined,
      remark: form.remark,
      scope: form.scope,
    })
    ElMessage.success('退款成功')
    dialogVisible.value = false
    emit('success')
  } catch (e) {
    ElMessage.error(e?.response?.data?.message || e?.message || '退款失败')
  } finally {
    submitting.value = false
  }
}
</script>

<style scoped lang="scss">
.refund-hint {
  margin-top: 4px;
  color: var(--el-text-color-placeholder);
  font-size: 12px;
}
.scope-hint {
  color: var(--el-text-color-secondary);
  font-size: 12px;
}
</style>
