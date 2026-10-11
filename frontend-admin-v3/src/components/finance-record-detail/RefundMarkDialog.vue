<template>
  <t-dialog
    :visible="visible"
    :header="`退款处理 · ${targetLabel}`"
    width="600px"
    :confirm-btn="{ content: '确认提交', loading: submitting, theme: 'warning' }"
    @cancel="handleCancel"
    @confirm="handleSubmit"
  >
    <t-alert :theme="mode === 'balance' ? 'warning' : 'info'" :message="modeHint" style="margin-bottom: 16px" />

    <t-radio-group v-model="mode" variant="default-filled" class="refund-mode-group">
      <t-radio value="mark" :disabled="!canMarkRefund">线下补标记（不动钱）</t-radio>
      <t-radio value="balance" :disabled="!canRefundToBalance">退款到余额（真实退钱）</t-radio>
    </t-radio-group>
    <p v-if="balanceDisabledReason" class="refund-mode-hint">{{ balanceDisabledReason }}</p>

    <div class="refund-mark-dialog__summary">
      <div class="refund-mark-dialog__kv">
        <span class="refund-mark-dialog__label">实收金额</span>
        <span class="refund-mark-dialog__value">{{ formatMoney(paidAmount) }}</span>
      </div>
      <div class="refund-mark-dialog__kv">
        <span class="refund-mark-dialog__label">已退款</span>
        <span class="refund-mark-dialog__value">{{ formatMoney(refundedAmount) }}</span>
      </div>
      <div class="refund-mark-dialog__kv">
        <span class="refund-mark-dialog__label">本次可退上限</span>
        <span class="refund-mark-dialog__value refund-mark-dialog__value--strong">{{
          formatMoney(refundableLimit)
        }}</span>
      </div>
    </div>

    <t-form ref="formRef" :data="form" :rules="rules" label-align="top">
      <t-form-item label="退款金额" name="amount">
        <t-input-number
          v-model="form.amount"
          :min="0.01"
          :max="refundableLimit > 0 ? refundableLimit : 999999"
          :decimal-places="2"
          style="width: 100%"
        />
        <t-button
          v-if="refundableLimit > 0"
          variant="text"
          theme="primary"
          size="small"
          class="refund-mark-dialog__full-btn"
          @click="markFull"
        >
          全部可退 {{ formatMoney(refundableLimit) }}
        </t-button>
      </t-form-item>
      <t-form-item :label="mode === 'balance' ? '退款原因' : '退款标记原因'" name="remark">
        <t-textarea
          v-model="form.remark"
          :maxlength="200"
          :placeholder="
            mode === 'balance'
              ? '请填写退款原因（如用户在渠道侧确认、已改为余额退回的说明）'
              : '请填写退款标记原因（如线下退款渠道、凭证号）'
          "
        />
      </t-form-item>
    </t-form>
  </t-dialog>
</template>
<script setup lang="ts">
import type { FormInstanceFunctions, FormProps } from 'tdesign-vue-next';
import { MessagePlugin } from 'tdesign-vue-next';
import { computed, reactive, ref, watch } from 'vue';

import { formatMoney } from '@/utils/format';
import { required } from '@/utils/formRules';
import { errorMessage } from '@/utils/userMessage';

/**
 * 退款处理弹窗（2026-10-11）：两种互斥方式——
 * - mark：线下补标记，登记线下已退款事实，不动余额/台账/红字账单；
 * - balance：退款到余额，真实加余额 + 台账 + 红字账单 + 充值记录。
 * 口径「一单只选一种」（服务端互斥守卫），可选上限随方式切换。
 */
const props = defineProps<{
  visible: boolean;
  targetLabel: string;
  paidAmount: number;
  refundedAmount: number;
  /** 纯标记可退上限（累计口径） */
  markRefundableAmount: number;
  /** 真退款可退上限（单次口径的轻量近似） */
  balanceRefundableAmount: number;
  canMarkRefund: boolean;
  canRefundToBalance: boolean;
  hasManualMarkRefund: boolean;
  /** 提交动作（由父组件实现 API 调用与刷新；抛错即弹窗保持打开）。 */
  submit: (payload: { mode: 'mark' | 'balance'; amount: number; remark: string }) => Promise<void>;
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
const mode = ref<'mark' | 'balance'>('mark');

const rules: FormProps['rules'] = {
  amount: [required('请输入退款金额')],
  remark: [required('请填写退款原因')],
};

const refundableLimit = computed(() =>
  mode.value === 'balance' ? Math.max(props.balanceRefundableAmount, 0) : Math.max(props.markRefundableAmount, 0),
);

const modeHint = computed(() =>
  mode.value === 'balance'
    ? '将真实增加用户余额（同时写入台账与红字账单）；全额退款时账单与订单收口为「已退款」，部分退款保持已支付并标记「部分退款」。'
    : '仅登记「线下已退款」事实（支持部分退款），不改变用户余额、不产生资金流水、不生成红字账单。',
);

const balanceDisabledReason = computed(() => {
  if (props.canRefundToBalance) return '';
  if (props.hasManualMarkRefund) return '该账单已有线下退款标记，两种方式不可混用（一单只选一种）。';
  if (props.balanceRefundableAmount <= 0) return '该账单当前没有可退余额（可能已退完或无可退支付单）。';
  return '该账单当前不支持退款到余额（充值/补录账单或状态不满足）。';
});

watch(
  () => props.visible,
  (visible) => {
    if (visible) {
      form.amount = 0;
      form.remark = '';
      mode.value = props.canRefundToBalance && !props.canMarkRefund ? 'balance' : 'mark';
    }
  },
  { immediate: true },
);

function markFull(): void {
  form.amount = Number(refundableLimit.value.toFixed(2));
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
  if (amount - refundableLimit.value > 0.00001) {
    MessagePlugin.error(`退款金额超过可退上限（${formatMoney(refundableLimit.value)}）`);
    return;
  }
  if (mode.value === 'balance' && refundableLimit.value - amount > 0.00001) {
    // 与既有真退款语义一致：允许部分退款到余额，仅全额时才收口 Payment/账单/订单
    // （部分退款后账单仍显示「部分退款」标签，账单状态保持已支付是正确表现）。
    MessagePlugin.info('部分退款不会改变账单与订单状态，仅全额退款时才收口为「已退款」');
  }

  submitting.value = true;
  try {
    await props.submit({ mode: mode.value, amount, remark: form.remark.trim() });
    emit('update:visible', false);
  } catch (error) {
    MessagePlugin.error(errorMessage(error, '退款处理失败'));
  } finally {
    submitting.value = false;
  }
}
</script>
<style scoped>
.refund-mode-group {
  margin-bottom: 4px;
}

.refund-mode-hint {
  margin: 0 0 12px;
  font-size: 12px;
  color: var(--td-text-color-secondary);
}

.refund-mark-dialog__summary {
  display: flex;
  gap: 16px;
  margin: 12px 0 16px;
  padding: 12px 16px;
  border-radius: 6px;
  background: var(--td-bg-color-container);
}

.refund-mark-dialog__kv {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.refund-mark-dialog__label {
  font-size: 12px;
  color: var(--td-text-color-secondary);
}

.refund-mark-dialog__value {
  font-size: 16px;
  font-weight: 600;
  color: var(--td-text-color-primary);
}

.refund-mark-dialog__value--strong {
  color: var(--td-warning-color);
}

.refund-mark-dialog__full-btn {
  margin-top: 4px;
}
</style>
