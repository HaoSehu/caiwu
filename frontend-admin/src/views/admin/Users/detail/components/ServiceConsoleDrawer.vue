<template>
  <el-drawer
    :model-value="state.visible"
    :size="drawerSize"
    :with-header="false"
    destroy-on-close
    class="service-console-drawer"
    @update:model-value="handleVisibleUpdate"
  >
    <div class="console-shell" v-loading="state.loading">
      <header class="console-header">
        <div class="console-title">
          <h3>{{ state.detail.name || `实例 #${state.serviceId}` }}</h3>
          <el-tag
            :type="resolveServiceToneTagType(state.detail.status_tone)"
            effect="plain"
            size="small"
          >
            {{ state.detail.status_label || '-' }}
          </el-tag>
          <el-tag v-if="upstreamStatusLabel" size="small" effect="plain" type="info">
            上游：{{ upstreamStatusLabel }}
          </el-tag>
          <el-tag v-if="runtimeLabel" size="small" effect="plain" :type="runtimeTagType">
            运行：{{ runtimeLabel }}
          </el-tag>
        </div>
        <div class="console-title-side">
          <el-button
            :loading="state.actionLoading === 'remote-status'"
            :icon="Refresh"
            @click="emit('refresh-remote')"
          >
            刷新状态
          </el-button>
          <el-button circle :icon="Close" @click="emit('close')" />
        </div>
      </header>

      <div v-if="upstreamError" class="console-alert">
        <el-alert :title="upstreamError" type="warning" show-icon :closable="false" />
      </div>

      <section class="console-section">
        <div class="console-section-head">
          <strong>基础信息</strong>
        </div>
        <div class="console-grid">
          <div class="console-field">
            <span>实例名称</span>
            <strong>{{ state.detail.name || '-' }}</strong>
          </div>
          <div class="console-field">
            <span>实例 ID</span>
            <strong>#{{ state.detail.id || '-' }}</strong>
          </div>
          <div class="console-field">
            <span>配置名称</span>
            <strong>{{ state.detail.product_display_name || state.detail.product?.display_name || (state.detail.product_id ? `未配置规格 #${state.detail.product_id}` : '-') }}</strong>
          </div>
          <div class="console-field">
            <span>类型</span>
            <strong>{{ state.detail.product?.type_label || '-' }}</strong>
          </div>
          <div class="console-field">
            <span>计费</span>
            <strong>{{ state.detail.billing_cycle_label || '-' }} · {{ formatMoney(state.detail.amount) }}</strong>
          </div>
          <div class="console-field">
            <span>账单号</span>
            <strong class="mono">{{ state.detail.invoice?.invoice_no || state.detail.order?.invoice_no || '-' }}</strong>
          </div>
          <div class="console-field">
            <span>购买时间</span>
            <strong>{{ state.detail.created_at || '-' }}</strong>
          </div>
          <div class="console-field">
            <span>到期时间</span>
            <strong>{{ state.detail.expires_at || '-' }}</strong>
          </div>
          <div class="console-field">
            <span>主机名</span>
            <strong class="mono">{{ state.detail.domain || '-' }}</strong>
          </div>
        </div>
      </section>

      <section class="console-section">
        <div class="console-section-head">
          <strong>连接与上游</strong>
          <div v-if="state.detail.upstream?.provider" class="console-upstream-meta">
            <span>
              上游：{{ state.detail.upstream.provider }}
              <template v-if="state.detail.upstream.host_id">（host #{{ state.detail.upstream.host_id }}）</template>
            </span>
            <el-button
              link
              type="primary"
              :disabled="isAnyActionLoading"
              @click="emit('edit-upstream')"
            >
              更换id
            </el-button>
          </div>
        </div>
        <div class="console-grid">
          <div class="console-field">
            <span>公网 IP</span>
            <strong class="mono copyable" @click="copy(connection.dedicated_ip)">
              {{ connection.dedicated_ip || '-' }}
            </strong>
          </div>
          <div class="console-field">
            <span>内网 IP</span>
            <strong class="mono copyable" @click="copy(connection.internal_ip)">
              {{ connection.internal_ip || '-' }}
            </strong>
          </div>
          <div class="console-field">
            <span>登录账号</span>
            <strong class="mono copyable" @click="copy(connection.username)">
              {{ connection.username || '-' }}
            </strong>
          </div>
          <div class="console-field">
            <span>登录端口</span>
            <strong class="mono">{{ connection.port || '-' }}</strong>
          </div>
          <div class="console-field console-field--span-2">
            <span>登录密码</span>
            <strong class="mono password-cell">
              <template v-if="connection.has_password">
                <span>{{ passwordVisible ? (connection.password || '•••••••') : '••••••••' }}</span>
                <el-button link :icon="passwordVisible ? Hide : View" @click="togglePassword" />
                <el-button link :icon="CopyDocument" @click="copy(connection.password)" />
              </template>
              <span v-else class="text-muted">未记录</span>
            </strong>
          </div>
        </div>
      </section>

      <section v-if="specs.length" class="console-section">
        <div class="console-section-head">
          <strong>规格</strong>
        </div>
        <div class="console-spec">
          <div v-for="item in specs" :key="item.label" class="console-spec-item">
            <span>{{ item.label }}</span>
            <strong>{{ item.value }}</strong>
          </div>
        </div>
      </section>

      <section class="console-section">
        <div class="console-section-head">
          <strong>操作</strong>
        </div>

        <div class="console-actions">
          <el-button
            v-if="canPowerOn"
            :loading="state.actionLoading === 'power:on'"
            :disabled="isAnyActionLoading"
            type="success"
            @click="emit('power', 'on')"
          >
            开机
          </el-button>
          <el-button
            v-if="canPowerOff"
            :loading="state.actionLoading === 'power:off'"
            :disabled="isAnyActionLoading"
            type="danger"
            plain
            @click="emit('power', 'off')"
          >
            关机
          </el-button>
          <el-button
            v-if="canReboot"
            :loading="state.actionLoading === 'power:reboot'"
            :disabled="isAnyActionLoading"
            type="warning"
            plain
            @click="emit('power', 'reboot')"
          >
            重启
          </el-button>

          <el-divider direction="vertical" />

          <el-button
            :loading="state.actionLoading === 'reset-password'"
            :disabled="!actions.password_reset || isAnyActionLoading"
            @click="emit('reset-password')"
          >
            重置密码
          </el-button>
          <el-button
            :disabled="isAnyActionLoading"
            @click="emit('edit-pricing')"
          >
            改价格
          </el-button>
          <el-button
            :disabled="isAnyActionLoading"
            @click="emit('edit-name')"
          >
            改名称
          </el-button>
          <el-button
            :loading="state.actionLoading === 'manual-provision'"
            :disabled="!actions.manual_provision || isAnyActionLoading"
            @click="emit('manual-provision')"
          >
            手动开通
          </el-button>

          <el-divider direction="vertical" />

          <el-button
            v-if="canRefund"
            :loading="state.actionLoading === 'refund'"
            :disabled="isAnyActionLoading"
            type="danger"
            @click="openRefundDialog"
          >
            退款
          </el-button>
          <span v-else-if="isRefunded" class="console-refund-tag">
            <el-tag type="danger" size="small" effect="plain">已退款</el-tag>
          </span>
        </div>

        <p class="console-hint">
          操作会直接调用上游 API，请确认影响范围。重装等高危操作暂未开放，请使用“以该客户登录”进入用户控制台完成。
        </p>
      </section>
    </div>
  </el-drawer>

  <el-dialog v-model="refundDialogVisible" title="服务退款" width="520px" destroy-on-close>
    <el-alert
      type="warning"
      :closable="false"
      show-icon
      title="退款将把对应账单标记为已退款，并关闭该实例的计费流程，当前仅支持全额退款。"
    />

    <el-form
      ref="refundFormRef"
      :model="refundForm"
      :rules="refundRules"
      label-width="90px"
      style="margin-top: 18px;"
    >
      <el-form-item label="服务实例">
        <el-input :model-value="state.detail.name || `实例 #${state.serviceId}`" disabled />
      </el-form-item>
      <el-form-item label="关联账单">
        <el-input :model-value="state.detail.invoice?.invoice_no || state.detail.order?.invoice_no || '-'" disabled />
      </el-form-item>
      <el-form-item label="退款金额">
        <el-input :model-value="refundAmountText" disabled />
      </el-form-item>
      <el-form-item label="退款方式" prop="refund_method">
        <el-radio-group v-model="refundForm.refund_method">
          <el-radio value="balance">退回余额</el-radio>
          <el-radio value="original" :disabled="!canOriginalRefund">原路退款</el-radio>
        </el-radio-group>
        <div v-if="!canOriginalRefund" class="refund-hint">
          {{ originalRefundBlockedReason }}
        </div>
      </el-form-item>
      <el-form-item label="退款原因" prop="remark">
        <el-input
          v-model="refundForm.remark"
          type="textarea"
          :rows="4"
          maxlength="200"
          show-word-limit
          placeholder="请输入退款原因"
        />
      </el-form-item>
    </el-form>

    <template #footer>
      <el-button @click="closeRefundDialog">取消</el-button>
      <el-button type="danger" :loading="refundSubmitting" @click="submitRefund">确认退款</el-button>
    </template>
  </el-dialog>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { ElMessage } from 'element-plus'
import { Close, CopyDocument, Hide, Refresh, View } from '@element-plus/icons-vue'

const props = defineProps({
  state: { type: Object, required: true },
  formatMoney: { type: Function, required: true },
  resolveServiceToneTagType: { type: Function, required: true },
})

const emit = defineEmits([
  'close',
  'refresh-remote',
  'power',
  'reset-password',
  'edit-upstream',
  'edit-pricing',
  'edit-name',
  'manual-provision',
  'refund',
])

const passwordVisible = ref(false)

const drawerSize = computed(() => {
  if (typeof window === 'undefined') return '620px'
  return window.innerWidth <= 768 ? '92%' : '620px'
})

const connection = computed(() => props.state.detail?.connection || {})
const actions = computed(() => props.state.detail?.actions || {})
const specs = computed(() => {
  const raw = props.state.detail?.specs
  if (!Array.isArray(raw)) return []
  return raw.filter((item) => item && (item.label || item.name))
    .map((item) => ({
      label: item.label || item.name,
      value: item.value ?? item.text ?? '-',
    }))
})

const upstreamStatusLabel = computed(() => props.state.detail?.upstream?.status_label || '')
const upstreamError = computed(() => props.state.detail?.upstream?.remote_error || '')

const runtimeLabel = computed(() => props.state.detail?.runtime?.power_label || '')
const runtimeState = computed(() => String(props.state.detail?.runtime?.power_state || '').toLowerCase())

const runtimeTagType = computed(() => {
  if (['running', 'on', 'started', 'poweron'].includes(runtimeState.value)) return 'success'
  if (['stopped', 'off', 'shutdown', 'poweroff'].includes(runtimeState.value)) return 'danger'
  if (['pending', 'starting', 'stopping', 'rebooting'].includes(runtimeState.value)) return 'warning'
  return 'info'
})

const canPowerOn = computed(() => (
  actions.value.power !== false
  && !['running', 'on', 'started', 'poweron'].includes(runtimeState.value)
))
const canPowerOff = computed(() => (
  actions.value.power !== false
  && !['stopped', 'off', 'shutdown', 'poweroff'].includes(runtimeState.value)
))
const canReboot = computed(() => (
  actions.value.power !== false
  && ['running', 'on', 'started', 'poweron', ''].includes(runtimeState.value)
))

const isAnyActionLoading = computed(() => Boolean(props.state.actionLoading))

const orderStatus = computed(() => Number(props.state.detail?.order?.status ?? -1))
const isRefunded = computed(() => orderStatus.value === 5)
const canRefund = computed(() => {
  return !isRefunded.value
    && Boolean(props.state.detail?.order?.id)
    && [1, 2, 3].includes(orderStatus.value)
})
const canOriginalRefund = computed(() => {
  const gateway = String(props.state.detail?.order?.payment_gateway || '').toLowerCase()
  return ['alipay', 'balance'].includes(gateway)
})
const originalRefundBlockedReason = computed(() => {
  if (canOriginalRefund.value) return ''
  const gateway = String(props.state.detail?.order?.payment_gateway || '').toLowerCase()
  return gateway ? '当前支付方式不支持原路退款' : '当前账单暂无原路退款信息'
})
const refundAmountText = computed(() => {
  const amount = props.state.detail?.amount || props.state.detail?.order?.amount || '0.00'
  return `¥${amount}`
})

const refundDialogVisible = ref(false)
const refundSubmitting = ref(false)
const refundFormRef = ref(null)
const refundForm = reactive({
  refund_method: 'balance',
  remark: '',
})
const refundRules = {
  refund_method: [{ required: true, message: '请选择退款方式', trigger: 'change' }],
  remark: [
    { required: true, message: '请输入退款原因', trigger: 'blur' },
    { min: 2, max: 200, message: '退款原因长度需为 2-200 个字符', trigger: 'blur' },
  ],
}

function openRefundDialog() {
  if (!canRefund.value) return
  refundForm.refund_method = canOriginalRefund.value ? 'original' : 'balance'
  refundForm.remark = refundForm.refund_method === 'original' ? '后台发起原路退款' : '后台退回用户余额'
  refundDialogVisible.value = true
}

function closeRefundDialog() {
  if (refundSubmitting.value) return
  refundDialogVisible.value = false
  refundForm.refund_method = 'balance'
  refundForm.remark = ''
  refundFormRef.value?.clearValidate?.()
}

async function submitRefund() {
  await refundFormRef.value?.validate()
  emit('refund', {
    refund_method: refundForm.refund_method,
    amount: props.state.detail?.amount || props.state.detail?.order?.amount,
    remark: refundForm.remark,
  })
}

// 父组件完成退款后 actionLoading 从 'refund' 变为 ''，自动关闭弹窗
watch(() => props.state.actionLoading, (next, prev) => {
  if (prev === 'refund' && next !== 'refund' && refundDialogVisible.value) {
    refundDialogVisible.value = false
    refundForm.refund_method = 'balance'
    refundForm.remark = ''
    refundFormRef.value?.clearValidate?.()
  }
})

function handleVisibleUpdate(next) {
  if (!next) {
    emit('close')
  }
}

function togglePassword() {
  passwordVisible.value = !passwordVisible.value
}

async function copy(text) {
  const value = String(text || '').trim()
  if (!value) {
    ElMessage.info('内容为空')
    return
  }
  try {
    if (navigator.clipboard?.writeText) {
      await navigator.clipboard.writeText(value)
    } else {
      const input = document.createElement('textarea')
      input.value = value
      document.body.appendChild(input)
      input.select()
      document.execCommand('copy')
      document.body.removeChild(input)
    }
    ElMessage.success('已复制')
  } catch {
    ElMessage.error('复制失败，请手动选择文本')
  }
}

// 关闭抽屉时重置密码显隐
watch(() => props.state.visible, (next) => {
  if (!next) {
    passwordVisible.value = false
  }
})
</script>

<style lang="scss" scoped>
.service-console-drawer :deep(.el-drawer__body) {
  padding: 0;
  overflow: hidden;
}

.console-shell {
  display: flex;
  flex-direction: column;
  gap: 16px;
  height: 100%;
  padding: 18px 22px 24px;
  overflow-y: auto;
}

.console-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 12px;
  padding-bottom: 14px;
  border-bottom: 1px solid $divider-color;
}

.console-title {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
  min-width: 0;

  h3 {
    margin: 0 6px 0 0;
    color: $text-color-primary;
    font-size: 18px;
    font-weight: 600;
    letter-spacing: -0.2px;
    line-height: 1.3;
  }
}

.console-title-side {
  display: flex;
  gap: 8px;
  flex-shrink: 0;
}

.console-alert {
  margin-top: -4px;
}

.console-section {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding: 14px 16px;
  border: 1px solid $border-color;
  border-radius: $base-border-radius;
  background: $bg-color-soft;
}

.console-section-head {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  gap: 8px;
  color: $text-color-secondary;
  font-size: 12px;

  strong {
    color: $text-color-primary;
    font-size: 13px;
    font-weight: 600;
  }
}

.console-upstream-meta {
  display: inline-flex;
  align-items: center;
  justify-content: flex-end;
  flex-wrap: wrap;
  gap: 8px;
  min-width: 0;

  span {
    line-height: 1.5;
  }
}

.console-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 10px 14px;
}

.console-field {
  display: flex;
  flex-direction: column;
  gap: 4px;
  min-width: 0;

  span {
    color: $text-color-placeholder;
    font-size: 12px;
    line-height: 1.2;
  }

  strong {
    color: $text-color-primary;
    font-size: 13px;
    font-weight: 500;
    word-break: break-all;
  }
}

.console-field--span-2 {
  grid-column: span 2;
}

.mono {
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
  font-variant-numeric: tabular-nums;
}

.copyable {
  cursor: copy;
  transition: color $duration-fast $ease-standard;

  &:hover {
    color: $color-primary;
  }
}

.password-cell {
  display: inline-flex;
  align-items: center;
  gap: 8px;
}

.console-spec {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
  gap: 8px 14px;
}

.console-spec-item {
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding: 6px 8px;
  border-radius: $sm-border-radius;
  background: $bg-color-card;
  border: 1px solid $divider-color;

  span {
    color: $text-color-placeholder;
    font-size: 11px;
  }

  strong {
    color: $text-color-primary;
    font-size: 13px;
    font-weight: 500;
  }
}

.console-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  align-items: center;
}

.console-hint {
  color: $text-color-placeholder;
  font-size: 12px;
  line-height: 1.6;
  margin-top: 4px;
}

.text-muted {
  color: $text-color-placeholder;
}

.refund-hint {
  margin-top: 6px;
  color: $text-color-placeholder;
  font-size: 12px;
  line-height: 1.6;
}

.console-refund-tag {
  display: inline-flex;
  align-items: center;
}

@media (max-width: 768px) {
  .console-shell {
    padding: 14px 16px 20px;
  }

  .console-grid {
    grid-template-columns: 1fr;
  }

  .console-field--span-2 {
    grid-column: span 1;
  }

  .console-header {
    flex-direction: column;
    align-items: stretch;
  }

  .console-title-side {
    justify-content: flex-end;
  }
}
</style>
