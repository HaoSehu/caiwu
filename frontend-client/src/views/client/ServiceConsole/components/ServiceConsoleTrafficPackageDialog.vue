<template>
  <el-dialog :model-value="visible" title="购买流量包" width="520px" destroy-on-close @update:model-value="emit('update:visible', $event)">
    <div v-loading="state.loading || state.quoting" class="dialog-body">
      <template v-if="state.data">
        <el-empty v-if="state.data.supported === false" :description="state.data.message || '当前服务暂不支持流量包购买'" :image-size="72" />
        <template v-else>
          <div class="traffic-pack-summary">
            <div class="traffic-pack-summary__item">
              <span>当前已用</span>
              <strong>{{ state.data.traffic?.usage_label || '0G' }}</strong>
            </div>
            <div class="traffic-pack-summary__item">
              <span>总额度</span>
              <strong>{{ state.data.traffic?.limit_label || '--' }}</strong>
            </div>
            <div class="traffic-pack-summary__item">
              <span>剩余额度</span>
              <strong>{{ state.data.traffic?.remaining_label || '--' }}</strong>
            </div>
          </div>

          <div class="traffic-pack-form">
            <div class="traffic-pack-form__head">
              <strong>流量包档位</strong>
              <span>当前套餐：{{ state.data.traffic?.limit_label || '--' }}</span>
            </div>

            <div class="traffic-pack-choice-grid">
              <button
                v-for="item in state.data.packages || []"
                :key="item.target_value"
                type="button"
                class="renew-cycle-btn traffic-pack-choice"
                :class="{ active: Number(state.target_value) === Number(item.target_value) }"
                @click="emit('choice-change', item.target_value)"
              >
                <span>{{ item.label }}</span>
                <strong>¥{{ item.price }}</strong>
              </button>
            </div>
          </div>

          <div v-if="state.quote" class="traffic-pack-quote">
            <div class="traffic-pack-quote__row">
              <span>购买后流量</span>
              <strong>{{ state.quote.selection?.target_label || '--' }}</strong>
            </div>
            <div class="traffic-pack-quote__row">
              <span>计费方式</span>
              <strong>{{ resolveBillingCycleLabel(state.quote.pricing?.billing_cycle) }}</strong>
            </div>
          </div>

          <div class="renew-total-line">
            <span>本次应付</span>
            <strong>¥{{ amount }}</strong>
          </div>
        </template>
      </template>
    </div>

    <template #footer>
      <el-button @click="emit('update:visible', false)">取消</el-button>
      <el-button
        type="primary"
        :loading="state.submitting"
        :disabled="state.data?.supported === false || !state.quote"
        @click="emit('submit')"
      >
        创建流量包账单
      </el-button>
    </template>
  </el-dialog>
</template>

<script setup>
defineProps({
  visible: { type: Boolean, default: false },
  state: { type: Object, required: true },
  amount: { type: String, default: '0.00' },
})

const emit = defineEmits(['update:visible', 'choice-change', 'qty-change', 'submit'])

function resolveBillingCycleLabel(value) {
  return {
    monthly: '月付',
    quarterly: '季付',
    semiannually: '半年付',
    annually: '年付',
    onetime: '一次性',
    one_time: '一次性',
  }[String(value || '').trim()] || '一次性'
}
</script>
