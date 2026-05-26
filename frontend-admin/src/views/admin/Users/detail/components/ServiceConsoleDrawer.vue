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
      <!-- 头部 -->
      <header class="console-header">
        <div class="console-top-row">
          <h3 class="console-title">{{ state.detail.name || `实例 #${state.serviceId}` }}</h3>
          <div class="console-top-actions">
            <el-button
              size="small"
              :loading="state.actionLoading === 'remote-status'"
              :icon="Refresh"
              @click="emit('refresh-remote')"
            />
            <el-button size="small" circle :icon="Close" @click="emit('close')" />
          </div>
        </div>
        <div class="console-tags">
          <el-tag :type="resolveServiceToneTagType(state.detail.status_tone)" effect="plain" size="small">
            {{ state.detail.status_label || '-' }}
          </el-tag>
          <el-tag v-if="upstreamStatusLabel" size="small" effect="plain" type="info">
            上游：{{ upstreamStatusLabel }}
          </el-tag>
          <el-tag v-if="runtimeLabel" size="small" effect="plain" :type="runtimeTagType">
            运行：{{ runtimeLabel }}
          </el-tag>
        </div>
      </header>

      <div v-if="upstreamError" class="console-alert">
        <el-alert :title="upstreamError" type="warning" show-icon :closable="false" />
      </div>

      <!-- 全部平铺内容 -->
      <div class="console-body">
        <!-- 基础信息 -->
        <section class="console-block">
          <div class="block-title">基础信息</div>
          <div class="info-grid">
            <div class="info-item"><span>实例 ID</span><strong>#{{ state.detail.id || '-' }}</strong></div>
            <div class="info-item"><span>配置名称</span><strong>{{ state.detail.product_display_name || state.detail.product?.display_name || (state.detail.product_id ? `未配置规格 #${state.detail.product_id}` : '-') }}</strong></div>
            <div class="info-item"><span>类型</span><strong>{{ state.detail.product?.type_label || '-' }}</strong></div>
            <div class="info-item"><span>计费</span><strong>{{ state.detail.billing_cycle_label || '-' }} · {{ formatMoney(state.detail.amount) }}</strong></div>
            <div class="info-item"><span>账单号</span><strong class="mono">{{ state.detail.invoice?.invoice_no || state.detail.order?.invoice_no || '-' }}</strong></div>
            <div class="info-item"><span>主机名</span><strong class="mono">{{ state.detail.domain || '-' }}</strong></div>
            <div class="info-item"><span>购买时间</span><strong>{{ state.detail.created_at || '-' }}</strong></div>
            <div class="info-item"><span>到期时间</span><strong>{{ state.detail.expires_at || '-' }}</strong></div>
          </div>
        </section>

        <!-- 连接信息 -->
        <section class="console-block">
          <div class="block-title">
            <span>连接信息</span>
            <div v-if="state.detail.upstream?.provider" class="block-meta">
              上游：{{ state.detail.upstream.provider }}<template v-if="state.detail.upstream.host_id">（host #{{ state.detail.upstream.host_id }}）</template>
              <el-button link type="primary" size="small" :disabled="isAnyActionLoading" @click="emit('edit-upstream')">更换id</el-button>
            </div>
          </div>
          <div class="info-grid">
            <div class="info-item"><span>公网 IP</span><strong class="mono copyable" @click="copy(connection.dedicated_ip)">{{ connection.dedicated_ip || '-' }}</strong></div>
            <div class="info-item"><span>内网 IP</span><strong class="mono copyable" @click="copy(connection.internal_ip)">{{ connection.internal_ip || '-' }}</strong></div>
            <div class="info-item"><span>登录账号</span><strong class="mono copyable" @click="copy(connection.username)">{{ connection.username || '-' }}</strong></div>
            <div class="info-item"><span>登录端口</span><strong class="mono">{{ connection.port || '-' }}</strong></div>
            <div class="info-item info-item--span-2">
              <span>登录密码</span>
              <strong class="mono password-cell">
                <template v-if="connection.has_password">
                  <span>{{ passwordVisible ? (connection.password || '') : '••••••••' }}</span>
                  <el-button link size="small" :icon="passwordVisible ? Hide : View" @click="togglePassword" />
                  <el-button link size="small" :icon="CopyDocument" @click="copy(connection.password)" />
                </template>
                <span v-else class="text-muted">未记录</span>
              </strong>
            </div>
          </div>
        </section>

        <!-- 规格 -->
        <section v-if="specs.length" class="console-block">
          <div class="block-title">规格</div>
          <div class="spec-grid">
            <div v-for="item in specs" :key="item.label" class="spec-chip">
              <span>{{ item.label }}</span>
              <strong>{{ item.value }}</strong>
            </div>
          </div>
        </section>

        <!-- 操作 -->
        <section class="console-block">
          <div class="block-title">操作</div>
          <div class="action-groups">
            <template v-if="canPowerOn || canPowerOff || canReboot">
              <div class="action-group-btns">
                <el-button v-if="canPowerOn" :loading="state.actionLoading === 'power:on'" :disabled="isAnyActionLoading" type="success" size="small" @click="emit('power', 'on')">开机</el-button>
                <el-button v-if="canPowerOff" :loading="state.actionLoading === 'power:off'" :disabled="isAnyActionLoading" type="danger" plain size="small" @click="emit('power', 'off')">关机</el-button>
                <el-button v-if="canReboot" :loading="state.actionLoading === 'power:reboot'" :disabled="isAnyActionLoading" type="warning" plain size="small" @click="emit('power', 'reboot')">重启</el-button>
              </div>
            </template>
            <div class="action-group-btns">
              <el-button size="small" :loading="state.actionLoading === 'reset-password'" :disabled="!actions.password_reset || isAnyActionLoading" @click="emit('reset-password')">重置密码</el-button>
              <el-button size="small" :disabled="isAnyActionLoading" @click="emit('edit-pricing')">改价格</el-button>
              <el-button size="small" :disabled="isAnyActionLoading" @click="emit('edit-name')">改名称</el-button>
              <el-button size="small" :loading="state.actionLoading === 'manual-provision'" :disabled="!actions.manual_provision || isAnyActionLoading" @click="emit('manual-provision')">手动开通</el-button>
            </div>
            <div v-if="canRefund || isRefunded" class="action-group-btns">
              <el-button v-if="canRefund" size="small" :loading="state.actionLoading === 'refund'" :disabled="isAnyActionLoading" type="danger" @click="openRefundDialog">退款</el-button>
              <el-tag v-else-if="isRefunded" type="danger" size="small" effect="plain">已退款</el-tag>
            </div>
          </div>
          <p class="console-hint">操作会直接调用上游 API。重装等高危操作请使用"代登录"进入用户控制台完成。</p>
        </section>
      </div>
    </div>
  </el-drawer>

  <!-- 退款弹窗 -->
  <el-dialog v-model="refundDialogVisible" title="服务退款" width="520px" destroy-on-close>
    <el-alert type="warning" :closable="false" show-icon title="退款将把对应账单标记为已退款，并关闭该实例的计费流程，当前仅支持全额退款。" />
    <el-form ref="refundFormRef" :model="refundForm" :rules="refundRules" label-width="90px" style="margin-top: 18px;">
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
        <div v-if="!canOriginalRefund" class="refund-hint">{{ originalRefundBlockedReason }}</div>
      </el-form-item>
      <el-form-item label="退款原因" prop="remark">
        <el-input v-model="refundForm.remark" type="textarea" :rows="4" maxlength="200" show-word-limit placeholder="请输入退款原因" />
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

// --- local state ---
const passwordVisible = ref(false)

// --- drawer size ---
const drawerSize = computed(() => {
  if (typeof window === 'undefined') return '540px'
  return window.innerWidth <= 768 ? '92%' : '540px'
})

// --- detail accessors ---
const connection = computed(() => props.state.detail?.connection || {})
const actions = computed(() => props.state.detail?.actions || {})
const specs = computed(() => {
  const raw = props.state.detail?.specs
  if (!Array.isArray(raw)) return []
  return raw.filter((item) => item && (item.label || item.name)).map((item) => ({
    label: item.label || item.name,
    value: item.value || '-',
  }))
})

// --- status tags ---
const upstreamStatusLabel = computed(() => props.state.detail?.upstream?.status_label || '')
const upstreamError = computed(() => props.state.detail?.upstream?.remote_error || '')
const runtimeLabel = computed(() => props.state.detail?.runtime?.power_label || '')
const runtimeTagType = computed(() => {
  const state = props.state.detail?.runtime?.power_state || ''
  if (state === 'running') return 'success'
  if (state === 'stopped') return 'info'
  if (['starting', 'stopping', 'rebooting'].includes(state)) return 'warning'
  return 'info'
})

// --- power actions ---
const canPowerOn = computed(() => actions.value.available?.includes('power:on') && (props.state.detail?.runtime?.power_state !== 'running'))
const canPowerOff = computed(() => actions.value.available?.includes('power:off') && (props.state.detail?.runtime?.power_state === 'running'))
const canReboot = computed(() => actions.value.available?.includes('power:reboot') && (props.state.detail?.runtime?.power_state === 'running'))
const isAnyActionLoading = computed(() => !!props.state.actionLoading)
const canRefund = computed(() => {
  const status = props.state.detail?.status
  // 已取消、已删除、已退款的不允许退款
  if ([0, 5, 6].includes(Number(status))) return false
  return (actions.value.available || []).includes('refund') !== false
})
const isRefunded = computed(() => {
  const status = props.state.detail?.status
  return [5, 6].includes(Number(status))
})

// --- refund dialog ---
const refundDialogVisible = ref(false)
const refundSubmitting = ref(false)
const refundFormRef = ref(null)
const refundForm = reactive({ refund_method: 'balance', remark: '' })
const refundRules = {
  refund_method: [{ required: true, message: '请选择退款方式', trigger: 'change' }],
  remark: [{ required: true, message: '请填写退款原因', trigger: 'blur' }],
}

const canOriginalRefund = computed(() => props.state.detail?.refund?.can_original ?? true)
const originalRefundBlockedReason = computed(() => props.state.detail?.refund?.original_blocked_reason || '当前不支持原路退款')
const refundAmountText = computed(() => {
  const amt = props.state.detail?.refund?.amount ?? props.state.detail?.amount ?? props.state.detail?.order?.amount
  return props.formatMoney(amt)
})

function openRefundDialog() {
  refundForm.refund_method = 'balance'
  refundForm.remark = ''
  refundFormRef.value?.clearValidate?.()
  refundDialogVisible.value = true
}

function closeRefundDialog() {
  refundDialogVisible.value = false
}

function submitRefund() {
  refundFormRef.value?.validate((valid) => {
    if (!valid) return
    emit('refund', {
      refund_method: refundForm.refund_method,
      amount: props.state.detail?.refund?.amount ?? props.state.detail?.amount ?? props.state.detail?.order?.amount,
      remark: refundForm.remark,
    })
  })
}

watch(() => props.state.actionLoading, (next, prev) => {
  if (prev === 'refund' && next !== 'refund' && refundDialogVisible.value) {
    refundDialogVisible.value = false
    refundForm.refund_method = 'balance'
    refundForm.remark = ''
    refundFormRef.value?.clearValidate?.()
  }
})

function handleVisibleUpdate(next) {
  if (!next) emit('close')
}

function togglePassword() {
  passwordVisible.value = !passwordVisible.value
}

async function copy(text) {
  const value = String(text || '').trim()
  if (!value) { ElMessage.info('内容为空'); return }
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

watch(() => props.state.visible, (next) => { if (!next) passwordVisible.value = false })
</script>

<style lang="scss" scoped>
.service-console-drawer :deep(.el-drawer__body) {
  padding: 0;
  overflow: hidden;
}

.console-shell {
  display: flex;
  flex-direction: column;
  gap: 12px;
  height: 100%;
  padding: 14px 16px 20px;
  overflow-y: auto;
}

.console-header {
  flex-shrink: 0;
}

.console-top-row {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 12px;
}

.console-title {
  flex: 1;
  min-width: 0;
  margin: 0;
  color: $text-color-primary;
  font-size: 16px;
  font-weight: 600;
  letter-spacing: -0.2px;
  line-height: 1.3;
  word-break: break-all;
}

.console-top-actions {
  display: flex;
  gap: 6px;
  flex-shrink: 0;
}

.console-tags {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  padding: 8px 0 2px;
}

.console-alert {
  flex-shrink: 0;
}

.console-body {
  display: flex;
  flex-direction: column;
  gap: 12px;
  flex: 1;
  min-height: 0;
}

.console-block {
  padding: 12px 14px;
  border: 1px solid $border-color;
  border-radius: $sm-border-radius;
  background: $bg-color-soft;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.block-title {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 6px;
  color: $text-color-primary;
  font-size: 13px;
  font-weight: 600;
}

.block-meta {
  font-size: 12px;
  color: $text-color-secondary;
  font-weight: 400;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  flex-wrap: wrap;
}

.info-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 8px 14px;
}

.info-item {
  display: flex;
  flex-direction: column;
  gap: 1px;
  min-width: 0;

  &--span-2 { grid-column: span 2; }

  span {
    color: $text-color-placeholder;
    font-size: 12px;
    line-height: 1.3;
  }

  strong {
    color: $text-color-primary;
    font-size: 13px;
    font-weight: 500;
    word-break: break-all;
  }
}

.spec-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(110px, 1fr));
  gap: 6px;
}

.spec-chip {
  display: flex;
  flex-direction: column;
  gap: 1px;
  padding: 6px 8px;
  border-radius: $sm-border-radius;
  background: $bg-color-card;
  border: 1px solid $divider-color;

  span { color: $text-color-placeholder; font-size: 11px; }
  strong { color: $text-color-primary; font-size: 12px; font-weight: 500; }
}

.action-groups {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.action-group-btns {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.console-hint {
  color: $text-color-placeholder;
  font-size: 12px;
  line-height: 1.6;
  margin-top: 4px;
}

.mono {
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
  font-variant-numeric: tabular-nums;
}

.copyable {
  cursor: copy;
  transition: color 0.15s;
  &:hover { color: $color-primary; }
}

.password-cell {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.text-muted { color: $text-color-placeholder; }
.refund-hint { margin-top: 6px; color: $text-color-placeholder; font-size: 12px; }

@media (max-width: 768px) {
  .console-shell {
    gap: 10px;
    padding: 12px 12px 16px;
  }

  .console-title { font-size: 15px; }

  .console-block { padding: 10px 12px; gap: 8px; }

  .info-grid {
    grid-template-columns: 1fr;
    gap: 6px 0;
  }

  .info-item--span-2 { grid-column: span 1; }

  .spec-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }

  .action-group-btns :deep(.el-button) {
    flex: 1;
    min-width: 0;
  }
}
</style>
