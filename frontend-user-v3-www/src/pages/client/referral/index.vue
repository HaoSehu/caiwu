<template>
  <div class="client-page referral-page">
    <div class="referral-stats-strip" v-loading="loading">
      <span v-for="item in summaryCards" :key="item.key" class="referral-stat" :class="{ 'referral-stat--primary': item.primary }">
        <em>{{ item.label }}</em>
        <strong>{{ item.value }}</strong>
      </span>
    </div>

    <section class="section-card referral-link-card">
      <header class="section-card__head">
        <div class="section-title-row">
          <h2>我的推荐链接</h2>
          <el-tag class="rate-tag" effect="plain">
            {{ levelName }} - 佣金比例 {{ rewardRateText }}
          </el-tag>
        </div>
      </header>
      <div class="section-card__body">
        <div class="link-row">
          <div class="link-field">
            <el-icon><Link /></el-icon>
            <span>{{ referralLink }}</span>
          </div>
          <el-button @click="copyReferralLink">复制链接</el-button>
        </div>
        <p class="link-tip">
          好友通过此链接注册并消费，您可获得 {{ rewardRateText }} 佣金，冻结 {{ freezeDaysText }} 天后自动释放。
          最低提现金额 ¥{{ withdrawMinAmountText }}。
        </p>
      </div>
    </section>

    <section class="section-card withdraw-card">
      <header class="section-card__head">
        <div class="section-title-row">
          <h2>提现支付宝</h2>
          <el-tag v-if="!isAlipayBound" effect="plain" class="bind-tag">提现前需先绑定</el-tag>
          <el-tag v-else type="success" effect="light">已绑定</el-tag>
        </div>
        <el-button v-if="!isAlipayBound" text type="primary" @click="openBindDialog">立即绑定</el-button>
      </header>

      <div v-if="!isAlipayBound" class="withdraw-empty">
        <el-empty description="提现前需要先绑定支付宝，并完成该手机号的短信验证。" />
      </div>

      <div v-else class="withdraw-form-wrap">
        <el-form
          ref="withdrawFormRef"
          :model="withdrawForm"
          :rules="withdrawRules"
          label-width="90px"
          class="withdraw-form"
        >
          <el-form-item label="收款账户">
            <div class="bound-account">
              <span>{{ alipayAccount.real_name || '--' }}</span>
              <strong>{{ alipayAccount.account || '--' }}</strong>
            </div>
          </el-form-item>
          <el-form-item label="提现金额" prop="amount">
            <el-input v-model="withdrawForm.amount" placeholder="请输入提现金额">
              <template #prefix>¥</template>
            </el-input>
          </el-form-item>
          <el-form-item>
            <el-button type="primary" :loading="withdrawSubmitting" @click="submitWithdrawal">
              提交提现
            </el-button>
            <span class="withdraw-hint">当前可提现 ¥{{ availableAmountText }}</span>
          </el-form-item>
        </el-form>
      </div>
    </section>

    <section class="records-section">
      <el-tabs v-model="activeTab" class="records-tabs">
        <el-tab-pane label="奖励明细" name="rewards" />
        <el-tab-pane label="提现记录" name="withdrawals" />
        <el-tab-pane label="账户流水" name="logs" />
      </el-tabs>

      <div class="records-card">
        <el-table
          v-if="activeTab === 'rewards'"
          :data="rewards"
          v-loading="loading"
          empty-text="暂无数据"
        >
          <el-table-column label="时间" min-width="160" prop="rewarded_at" />
          <el-table-column label="来源用户" min-width="160">
            <template #default="{ row }">{{ formatUser(row.referred_user) }}</template>
          </el-table-column>
          <el-table-column label="产品" min-width="260" show-overflow-tooltip>
            <template #default="{ row }">{{ formatProduct(row) }}</template>
          </el-table-column>
          <el-table-column label="账单金额" min-width="130">
            <template #default="{ row }">¥{{ money(row.order_amount) }}</template>
          </el-table-column>
          <el-table-column label="奖励金额" min-width="130">
            <template #default="{ row }">¥{{ money(row.reward_amount) }}</template>
          </el-table-column>
          <el-table-column label="释放时间" min-width="160">
            <template #default="{ row }">{{ row.released_at || row.available_at || '--' }}</template>
          </el-table-column>
          <el-table-column label="状态" min-width="120">
            <template #default="{ row }">
              <el-tag :type="rewardStatus(row.status).type" effect="light">
                {{ rewardStatus(row.status).label }}
              </el-tag>
            </template>
          </el-table-column>
        </el-table>

        <el-table
          v-else-if="activeTab === 'withdrawals'"
          :data="withdrawals"
          v-loading="loading"
          empty-text="暂无数据"
        >
          <el-table-column label="时间" min-width="160" prop="created_at" />
          <el-table-column label="提现方式" min-width="120">
            <template #default="{ row }">{{ methodLabel(row.method) }}</template>
          </el-table-column>
          <el-table-column label="提现金额" min-width="130">
            <template #default="{ row }">¥{{ money(row.amount) }}</template>
          </el-table-column>
          <el-table-column label="收款账户" min-width="220" show-overflow-tooltip>
            <template #default="{ row }">{{ row.account_name || '--' }} {{ row.account_no || '' }}</template>
          </el-table-column>
          <el-table-column label="处理时间" min-width="160">
            <template #default="{ row }">{{ row.processed_at || '--' }}</template>
          </el-table-column>
          <el-table-column label="状态" min-width="120">
            <template #default="{ row }">
              <el-tag :type="withdrawStatus(row.status).type" effect="light">
                {{ withdrawStatus(row.status).label }}
              </el-tag>
            </template>
          </el-table-column>
        </el-table>

        <el-table
          v-else
          :data="accountLogs"
          v-loading="loading"
          empty-text="暂无数据"
        >
          <el-table-column label="时间" min-width="160" prop="created_at" />
          <el-table-column label="事件" min-width="140">
            <template #default="{ row }">{{ accountEventLabel(row) }}</template>
          </el-table-column>
          <el-table-column label="变动金额" min-width="120">
            <template #default="{ row }">
              <span :class="Number(row.amount || 0) >= 0 ? 'amount-up' : 'amount-down'">
                ¥{{ money(row.amount) }}
              </span>
            </template>
          </el-table-column>
          <el-table-column label="可提现" min-width="120">
            <template #default="{ row }">¥{{ money(row.available_amount || row.available_balance) }}</template>
          </el-table-column>
          <el-table-column label="冻结中" min-width="120">
            <template #default="{ row }">¥{{ money(row.frozen_amount || row.frozen_balance) }}</template>
          </el-table-column>
          <el-table-column label="提现中" min-width="120">
            <template #default="{ row }">¥{{ money(row.withdrawing_amount || row.pending_withdrawal_balance) }}</template>
          </el-table-column>
          <el-table-column label="说明" min-width="220" prop="remark" show-overflow-tooltip />
        </el-table>
      </div>
    </section>

    <el-dialog
      v-model="bindDialogVisible"
      title="绑定提现支付宝"
      width="440px"
      destroy-on-close
      class="referral-dialog"
    >
      <el-form
        ref="bindFormRef"
        :model="bindForm"
        :rules="bindRules"
        label-position="top"
      >
        <el-form-item label="真实姓名" prop="real_name">
          <el-input v-model="bindForm.real_name" placeholder="请输入支付宝实名姓名" />
        </el-form-item>
        <el-form-item label="支付宝手机号" prop="account">
          <el-input v-model="bindForm.account" placeholder="请输入支付宝绑定手机号" />
        </el-form-item>
        <el-form-item label="短信验证码" prop="code">
          <div class="code-row">
            <el-input v-model="bindForm.code" placeholder="请输入短信验证码" maxlength="6" />
            <el-button
              :loading="codeSending || captchaLoading"
              :disabled="codeCountdown > 0"
              @click="sendBindCode"
            >
              {{ codeCountdown > 0 ? `${codeCountdown}s` : '发送验证码' }}
            </el-button>
          </div>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="bindDialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="bindSubmitting" @click="submitBindAlipay">
          保存绑定
        </el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import { ElMessage } from 'element-plus'
import { Link } from '@element-plus/icons-vue'
import { clientAuthApi } from '@/api/auth'
import clientApi from '@/api/client'
import { useGeeTestCaptcha } from '@/composables/useGeeTestCaptcha'
import { useReferral } from '@/composables/useReferral'

const activeTab = ref('rewards')
const withdrawFormRef = ref()
const bindFormRef = ref()
const withdrawSubmitting = ref(false)
const bindDialogVisible = ref(false)
const bindSubmitting = ref(false)
const codeSending = ref(false)
const codeCountdown = ref(0)
let codeTimer = null

const { loading: captchaLoading, runWithCaptcha } = useGeeTestCaptcha()

const {
  userStore,
  loading,
  overview,
  rewards,
  accountLogs,
  withdrawals,
  loadAll,
} = useReferral()

const alipayAccount = reactive({
  real_name: '',
  account: '',
  is_bound: false,
})

const withdrawForm = reactive({
  amount: '',
})

const bindForm = reactive({
  real_name: '',
  account: '',
  code: '',
})

const withdrawRules = {
  amount: [
    { required: true, message: '请输入提现金额', trigger: 'blur' },
    {
      validator: (_rule, value, callback) => {
        const amount = Number(value)
        if (!Number.isFinite(amount) || amount <= 0) {
          callback(new Error('提现金额必须大于 0'))
          return
        }
        if (amount < Number(withdrawMinAmountText.value)) {
          callback(new Error(`最低提现金额为 ¥${withdrawMinAmountText.value}`))
          return
        }
        if (amount > Number(availableAmountText.value)) {
          callback(new Error('提现金额不能超过可提现余额'))
          return
        }
        callback()
      },
      trigger: 'blur',
    },
  ],
}

const bindRules = {
  real_name: [{ required: true, message: '请输入真实姓名', trigger: 'blur' }],
  account: [
    { required: true, message: '请输入支付宝手机号', trigger: 'blur' },
    { pattern: /^1[3-9]\d{9}$/, message: '请输入正确的支付宝手机号', trigger: 'blur' },
  ],
  code: [
    { required: true, message: '请输入短信验证码', trigger: 'blur' },
    { min: 6, max: 6, message: '验证码为 6 位', trigger: 'blur' },
  ],
}

const availableAmountText = computed(() => money(overview.referral_available_amount || overview.available_amount))
const frozenAmountText = computed(() => money(overview.referral_frozen_amount || overview.pending_amount))
const totalRewardText = computed(() => money(overview.total_reward_amount || overview.total_amount))
const withdrawnAmountText = computed(() => money(overview.referral_withdrawn_amount || overview.withdrawn_amount))
const withdrawMinAmountText = computed(() => money(overview.withdraw_min_amount || 20))
const rewardRateText = computed(() => `${money(overview.reward_rate || overview.current_member_level?.reward_rate || 0)}%`)
const freezeDaysText = computed(() => Number(overview.reward_freeze_days || 0).toString())
const levelName = computed(() => overview.current_member_level?.name || 'v1')
const isAlipayBound = computed(() => Boolean(alipayAccount.is_bound && alipayAccount.account))
const referralLink = computed(() => {
  if (overview.referral_link) return overview.referral_link
  const code = overview.referral_code || userStore.info?.referral_code || ''
  if (typeof window !== 'undefined' && code) {
    return `${window.location.origin}/client/register?ref=${code}`
  }
  return '--'
})

const summaryCards = computed(() => [
  { key: 'available', label: '可提现余额', value: `¥${availableAmountText.value}`, primary: true },
  { key: 'frozen', label: '冻结中', value: `¥${frozenAmountText.value}` },
  { key: 'total', label: '累计奖励', value: `¥${totalRewardText.value}` },
  { key: 'withdrawn', label: '已提现', value: `¥${withdrawnAmountText.value}` },
  { key: 'direct', label: '直推人数', value: `${Number(overview.direct_referral_count || 0)} 人` },
  { key: 'orders', label: '推荐账单数', value: `${Number(overview.rewarded_orders_count || 0)} 单` },
])

function money(value) {
  const amount = Number(value || 0)
  return Number.isFinite(amount) ? amount.toFixed(2) : '0.00'
}

function formatUser(user) {
  return user?.display_name || user?.nickname || user?.email || '--'
}

function formatProduct(row) {
  return row.order?.product_display_name || row.product?.display_name || row.product?.name || '--'
}

function rewardStatus(status) {
  const map = {
    0: { label: '冻结中', type: 'warning' },
    1: { label: '已释放', type: 'success' },
    2: { label: '已冲正', type: 'danger' },
  }
  return map[Number(status)] || { label: String(status ?? '--'), type: 'info' }
}

function withdrawStatus(status) {
  const map = {
    0: { label: '审核中', type: 'warning' },
    1: { label: '已通过', type: 'success' },
    2: { label: '已拒绝', type: 'danger' },
  }
  return map[Number(status)] || { label: String(status ?? '--'), type: 'info' }
}

function methodLabel(method) {
  return method === 'balance' ? '转余额' : '支付宝'
}

function accountEventLabel(row) {
  const map = {
    reward_frozen: '奖励冻结',
    reward_released: '奖励释放',
    reward_reversed: '奖励冲正',
    withdraw_apply: '提现申请',
    withdraw_approved: '提现通过',
    withdraw_rejected: '提现驳回',
    referral_credit_cash: '奖励转余额',
    referral_withdraw_approved: '提现通过',
  }
  return row.event_label || map[row.event_type] || row.event_type || '--'
}

async function copyReferralLink() {
  const text = referralLink.value
  if (!text || text === '--') {
    ElMessage.warning('推荐链接暂不可用')
    return
  }

  try {
    await navigator.clipboard.writeText(text)
    ElMessage.success('推荐链接已复制')
  } catch {
    ElMessage.warning('复制失败，请手动复制')
  }
}

function openBindDialog() {
  bindForm.real_name = alipayAccount.real_name || userStore.info?.real_name || ''
  bindForm.account = alipayAccount.account || userStore.info?.phone || ''
  bindForm.code = ''
  bindDialogVisible.value = true
}

function clearCodeTimer() {
  if (codeTimer) {
    clearInterval(codeTimer)
    codeTimer = null
  }
}

function startCodeCountdown() {
  clearCodeTimer()
  codeCountdown.value = 60
  codeTimer = setInterval(() => {
    codeCountdown.value -= 1
    if (codeCountdown.value <= 0) {
      clearCodeTimer()
      codeCountdown.value = 0
    }
  }, 1000)
}

async function sendBindCode() {
  try {
    await bindFormRef.value?.validateField('account')
  } catch {
    return
  }

  codeSending.value = true
  try {
    await runWithCaptcha(async (captcha) => {
      await clientAuthApi.sendPhoneCode({
        phone: bindForm.account,
        captcha,
      })
    })
    ElMessage.success('短信验证码已发送')
    startCodeCountdown()
  } catch (error) {
    if (!error?.__handled) ElMessage.error(error?.message || '短信验证码发送失败')
  } finally {
    codeSending.value = false
  }
}

async function submitBindAlipay() {
  if (!bindFormRef.value) return
  await bindFormRef.value.validate()
  bindSubmitting.value = true
  try {
    const response = await clientAuthApi.updateAlipayAccount({
      real_name: bindForm.real_name,
      account: bindForm.account,
      code: bindForm.code,
    })
    Object.assign(alipayAccount, response.data || {})
    bindDialogVisible.value = false
    ElMessage.success('支付宝绑定成功')
    await userStore.fetchUserInfo('client')
  } catch (error) {
    if (!error?.__handled) ElMessage.error(error?.message || '支付宝绑定失败')
  } finally {
    bindSubmitting.value = false
  }
}

async function submitWithdrawal() {
  if (!withdrawFormRef.value) return
  await withdrawFormRef.value.validate()
  withdrawSubmitting.value = true
  try {
    await clientApi.applyWithdrawal({
      amount: withdrawForm.amount,
      method: 'alipay',
      account_name: alipayAccount.real_name,
      account_no: alipayAccount.account,
    })
    ElMessage.success('提现申请已提交')
    withdrawForm.amount = ''
    await loadAll()
  } catch (error) {
    if (!error?.__handled) ElMessage.error(error?.message || '提现申请提交失败')
  } finally {
    withdrawSubmitting.value = false
  }
}

async function loadAlipayAccount() {
  try {
    const response = await clientAuthApi.alipayAccount()
    Object.assign(alipayAccount, response.data || {})
  } catch {
    const fallback = userStore.info?.alipay_account || {}
    Object.assign(alipayAccount, fallback)
  }
}

async function loadPage() {
  await Promise.all([loadAll(), loadAlipayAccount()])
}

onMounted(() => {
  void loadPage()
})

onBeforeUnmount(() => {
  clearCodeTimer()
})
</script>

<style scoped lang="scss">
.referral-page {
  gap: 16px;
}

.section-card,
.records-card {
  border: 1px solid $border-color;
  background: $bg-color-card;
  box-shadow: $shadow-sm;
}

.section-card {
  border-radius: 14px;
  overflow: hidden;
}

.referral-stats-strip {
  display: flex;
  flex-wrap: wrap;
  gap: 6px 20px;
  padding: 14px 18px;
  border: 1px solid $border-color;
  border-radius: $base-border-radius;
  background: $bg-color-card;
  box-shadow: $shadow-sm;
}

.referral-stat {
  display: inline-flex;
  align-items: baseline;
  gap: 6px;

  em {
    font-style: normal;
    color: $text-color-secondary;
    font-size: 13px;
  }

  strong {
    color: $text-color-primary;
    font-size: 14px;
    font-weight: 600;
    font-variant-numeric: tabular-nums;
  }
}

.referral-stat--primary strong {
  color: $color-primary;
}

.section-card__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  min-height: 54px;
  padding: 0 20px;
  border-bottom: 1px solid $divider-color;
}

.section-title-row {
  display: flex;
  align-items: center;
  gap: 10px;
  min-width: 0;

  h2 {
    margin: 0;
    color: $text-color-primary;
    font-size: 16px;
    font-weight: 700;
    line-height: 1.3;
    white-space: nowrap;
  }
}

.rate-tag,
.bind-tag {
  border: 0;
  background: $color-primary-soft;
  color: $color-primary;
}

.section-card__body {
  padding: 20px;
}

.link-row {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  gap: 10px;
  align-items: center;
}

.link-field {
  display: flex;
  align-items: center;
  gap: 10px;
  min-height: 40px;
  padding: 0 14px;
  overflow: hidden;
  border: 1px solid $border-color-strong;
  border-radius: 10px;
  color: $text-color-primary;
  background: #fff;

  .el-icon {
    flex-shrink: 0;
    color: $text-color-placeholder;
  }

  span {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
}

.link-tip {
  margin: 10px 0 0;
  color: $text-color-secondary;
  font-size: 13px;
  line-height: 1.7;
}

.withdraw-card {
  min-height: 292px;
}

.withdraw-empty {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 238px;
}

.withdraw-form-wrap {
  padding: 24px 20px 28px;
}

.withdraw-form {
  max-width: 520px;
}

.bound-account {
  display: flex;
  align-items: center;
  gap: 12px;
  min-height: 38px;
  color: $text-color-secondary;

  strong {
    color: $text-color-primary;
    font-weight: 600;
  }
}

.withdraw-hint {
  margin-left: 12px;
  color: $text-color-secondary;
  font-size: 13px;
}

.records-section {
  margin-top: 8px;
}

.records-tabs {
  :deep(.el-tabs__header) {
    margin: 0;
  }

  :deep(.el-tabs__nav-wrap::after) {
    height: 1px;
    background: $divider-color;
  }

  :deep(.el-tabs__item) {
    height: 42px;
    padding: 0 22px 0 0;
    color: $text-color-primary;
    font-weight: 500;
  }
}

.records-card {
  margin-top: 28px;
  border-radius: 10px;
  overflow: hidden;

  :deep(.el-table) {
    border: 0 !important;
    border-radius: 0 !important;
  }
}

.amount-up {
  color: $color-success;
}

.amount-down {
  color: $color-danger;
}

.code-row {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 112px;
  gap: 10px;
  width: 100%;
}

.referral-dialog {
  :deep(.el-dialog__body) {
    padding-bottom: 4px;
  }
}

@media (max-width: 767px) {
  .link-row,
  .code-row {
    grid-template-columns: 1fr;
  }

  .section-card__head {
    align-items: flex-start;
    flex-direction: column;
    padding: 16px;
  }

  .section-title-row {
    flex-wrap: wrap;
  }

  .section-card__body,
  .withdraw-form-wrap {
    padding: 16px;
  }

  .withdraw-hint {
    display: block;
    margin: 10px 0 0;
  }
}
</style>
