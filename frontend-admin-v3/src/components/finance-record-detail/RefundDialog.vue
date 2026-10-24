<template>
  <t-dialog
    :visible="visible"
    :header="`退款到余额 · ${targetLabel}`"
    width="600px"
    :confirm-btn="{ content: '确定', loading: submitting, theme: 'danger', disabled: !canRefund }"
    @cancel="handleCancel"
    @confirm="handleSubmit"
  >
    <t-alert
      theme="warning"
      message="退款将真实增加用户余额（同时写入资金台账与红字账单）；仅全额退款时账单与订单才收口为「已退款」。"
      style="margin-bottom: 16px"
    />

    <div class="refund-dialog__summary">
      <div class="refund-dialog__kv">
        <span class="refund-dialog__label">实收金额</span>
        <span class="refund-dialog__value">{{ formatMoney(paidAmount) }}</span>
      </div>
      <div class="refund-dialog__kv">
        <span class="refund-dialog__label">已退款</span>
        <span class="refund-dialog__value">{{ formatMoney(refundedAmount) }}</span>
      </div>
      <div class="refund-dialog__kv">
        <span class="refund-dialog__label">本次可退上限</span>
        <span class="refund-dialog__value refund-dialog__value--strong">{{ formatMoney(refundableAmount) }}</span>
      </div>
    </div>

    <t-form ref="formRef" :data="form" :rules="rules" label-align="top">
      <t-form-item label="退款金额" name="amount">
        <t-input-number
          v-model="form.amount"
          :min="0.01"
          :max="refundableAmount > 0 ? refundableAmount : 999999"
          :decimal-places="2"
          style="width: 100%"
        />
        <t-button
          v-if="refundableAmount > 0"
          variant="text"
          theme="primary"
          size="small"
          class="refund-dialog__full-btn"
          @click="refundFull"
        >
          全部可退 {{ formatMoney(refundableAmount) }}
        </t-button>
      </t-form-item>
      <t-form-item label="退款原因" name="remark">
        <t-textarea
          v-model="form.remark"
          :maxlength="200"
          placeholder="请填写退款原因（如用户在渠道侧确认、已改为余额退回的说明）"
        />
      </t-form-item>
    </t-form>
  </t-dialog>
</template>
<script setup lang="ts">
import type { FormInstanceFunctions, FormProps } from 'tdesign-vue-next';
import { MessagePlugin } from 'tdesign-vue-next/es/message/plugin';
import { computed, reactive, ref, watch } from 'vue';

import { formatMoney } from '@/utils/format';
import { required } from '@/utils/formRules';
import { errorMessage } from '@/utils/userMessage';

/**
 * 退款到余额弹窗（唯一退款通道，2026-10-20 裁决）。
 * manual_mark 纯标记通道已移除；金额与能力判定一律以服务端下发的
 * `can_refund_to_balance` / `refundable_amount_for_balance` 为准。
 */
const props = defineProps<{
  visible: boolean;
  targetLabel: string;
  paidAmount: number;
  refundedAmount: number;
  /** 可退上限（服务端累计口径的轻量近似，提交以服务端守卫为准） */
  refundableAmount: number;
  canRefund: boolean;
  /**
   * 提交动作（由父组件实现 API 调用与刷新；抛错即弹窗保持打开）。
   * idempotencyKey 由弹窗在打开时生成、重试中复用，父组件必须原样透传给服务端，
   * 否则网络重试/双击会变成两笔独立退款。
   */
  submit: (payload: { amount: number; remark: string; idempotencyKey: string }) => Promise<void>;
}>();

const emit = defineEmits<{
  (e: 'update:visible', value: boolean): void;
}>();

/** t-form validate() 结果判定（true/undefined 通过，对象为失败明细）。 */
function isValidationPass(result: unknown): boolean {
  return result === true || result === undefined;
}

const formRef = ref<FormInstanceFunctions>();
const submitting = ref(false);
const form = reactive({ amount: 0, remark: '' });

/**
 * 幂等键生命周期：每次打开弹窗生成一次，重试/双击复用同一个键，成功后清空。
 * 服务端同键同体重放返回既有结果，同键异体返回 409。
 */
const idempotencyKey = ref('');

function resetIdempotencyKey(): void {
  idempotencyKey.value =
    typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function'
      ? crypto.randomUUID()
      : `refund-${Date.now()}-${Math.random().toString(36).slice(2)}`;
}

const rules: FormProps['rules'] = {
  amount: [required('请输入退款金额')],
  remark: [required('请填写退款原因')],
};

const limit = computed(() => Math.max(props.refundableAmount, 0));

watch(
  () => props.visible,
  (visible) => {
    if (visible) {
      form.amount = 0;
      form.remark = '';
      resetIdempotencyKey();
    }
  },
  { immediate: true },
);

function refundFull(): void {
  form.amount = Number(limit.value.toFixed(2));
}

function handleCancel(): void {
  emit('update:visible', false);
}

async function handleSubmit(): Promise<void> {
  const result = await formRef.value?.validate?.();
  if (!isValidationPass(result)) {
    return;
  }
  const amount = Number(form.amount);
  if (!(amount > 0)) {
    MessagePlugin.warning('请输入大于 0 的退款金额');
    return;
  }
  if (amount - limit.value > 0.00001) {
    MessagePlugin.error(`退款金额超过可退上限（${formatMoney(limit.value)}）`);
    return;
  }
  if (limit.value - amount > 0.00001) {
    // 与真退款语义一致：允许部分退款到余额，仅全额时才收口 Payment/账单/订单
    // （部分退款后账单仍显示「部分退款」标签，账单状态保持已支付是正确表现）。
    MessagePlugin.info('部分退款不会改变账单与订单状态，仅全额退款时才收口为「已退款」');
  }

  if (!idempotencyKey.value) {
    resetIdempotencyKey();
  }

  submitting.value = true;
  try {
    await props.submit({
      amount,
      remark: form.remark.trim(),
      idempotencyKey: idempotencyKey.value,
    });
    // 成功后才作废本键：失败重试继续复用，避免「失败一次换一个键」绕过幂等。
    idempotencyKey.value = '';
    emit('update:visible', false);
  } catch (error) {
    MessagePlugin.error(errorMessage(error, '退款处理失败'));
  } finally {
    submitting.value = false;
  }
}
</script>
<style scoped>
.refund-dialog__summary {
  display: flex;
  gap: 16px;
  margin: 12px 0 16px;
  padding: 12px 16px;
  border-radius: 6px;
  background: var(--td-bg-color-container);
}

.refund-dialog__kv {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.refund-dialog__label {
  font-size: 12px;
  color: var(--td-text-color-secondary);
}

.refund-dialog__value {
  font-size: 16px;
  font-weight: 600;
  color: var(--td-text-color-primary);
}

.refund-dialog__value--strong {
  color: var(--td-warning-color);
}

.refund-dialog__full-btn {
  margin-top: 4px;
}
</style>
