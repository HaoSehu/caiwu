<template>
  <div class="referral-page admin-page">
    <section class="admin-page-head">
      <div class="admin-page-heading">
        <span class="admin-page-kicker">运营</span>
        <h2>推广返利与提现管理</h2>
        <p>查看推广概览、订单奖励记录和提现审核结果，统一管理推荐奖励业务流转。</p>
      </div>

      <div class="page-actions">
        <el-button @click="refreshCurrentTab">刷新当前视图</el-button>
        <el-button type="primary" @click="loadAll">刷新全部</el-button>
      </div>
    </section>

    <section class="tab-toolbar">
      <el-segmented
        v-model="currentTab"
        class="view-segmented"
        :options="tabs"
        :props="{ label: 'label', value: 'key' }"
      />

      <span class="tab-description">{{ currentTabDescription }}</span>
    </section>

    <template v-if="currentTab === 'overview'">
      <el-card shadow="never" v-loading="overviewLoading">
        <template #header>
          <div class="panel-header">
            <div class="panel-header-meta">
              <strong>推广达人排行</strong>
              <span>按累计销售额倒序展示当前收益表现最好的推广用户</span>
            </div>
          </div>
        </template>

        <el-table :data="overview.top_referrers || []" stripe>
          <template #empty>
            <div class="panel-empty">
              <strong>暂无推广排行数据</strong>
              <p>产生推广订单后，这里会展示累计销售额和返利余额排行。</p>
            </div>
          </template>

          <el-table-column label="推广用户" min-width="220">
            <template #default="{ row }">
              <div class="user-cell">
                <strong>{{ row.display_name || row.nickname || row.email || `用户 #${row.id}` }}</strong>
                <span>{{ row.email || '--' }}</span>
              </div>
            </template>
          </el-table-column>

          <el-table-column label="会员等级" min-width="140">
            <template #default="{ row }">
              <div class="level-chip">
                <strong>{{ row.member_level?.name || '未分级' }}</strong>
                <span>{{ row.member_level?.reward_rate ? `${Number(row.member_level.reward_rate).toFixed(2)}%` : '默认比例' }}</span>
              </div>
            </template>
          </el-table-column>

          <el-table-column label="累计销售额" min-width="120" align="right">
            <template #default="{ row }">
              {{ formatCurrency(row.total_sales_amount) }}
            </template>
          </el-table-column>

          <el-table-column label="冻结中" min-width="120" align="right">
            <template #default="{ row }">
              {{ formatCurrency(row.referral_frozen_amount) }}
            </template>
          </el-table-column>

          <el-table-column label="可提现" min-width="120" align="right">
            <template #default="{ row }">
              {{ formatCurrency(row.referral_available_amount) }}
            </template>
          </el-table-column>

          <el-table-column label="提现中" min-width="120" align="right">
            <template #default="{ row }">
              {{ formatCurrency(row.referral_withdrawing_amount) }}
            </template>
          </el-table-column>

          <el-table-column label="已提现" min-width="120" align="right">
            <template #default="{ row }">
              {{ formatCurrency(row.referral_withdrawn_amount) }}
            </template>
          </el-table-column>
        </el-table>
      </el-card>
    </template>

    <template v-else-if="currentTab === 'rewards'">
      <section class="table-toolbar-card">
        <div class="table-toolbar-head">
          <div class="panel-header-meta">
            <strong>奖励记录筛选</strong>
            <span>支持按推荐人、被推荐用户、账单号和奖励状态查询</span>
          </div>
        </div>

        <div class="search-bar">
          <el-input
            v-model="rewardFilters.keyword"
            clearable
            class="search-field search-field-wide"
            placeholder="搜索推荐人、被推荐人、账单号或配置"
            @keyup.enter="handleRewardSearch"
          />

          <el-select
            v-model="rewardFilters.status"
            clearable
            class="search-field"
            placeholder="全部状态"
          >
            <el-option
              v-for="item in rewardStatusOptions"
              :key="item.value"
              :label="item.label"
              :value="item.value"
            />
          </el-select>

          <el-button type="primary" @click="handleRewardSearch">搜索</el-button>
          <el-button @click="resetRewardFilters">重置</el-button>
        </div>
      </section>

      <el-card shadow="never" v-loading="rewardsLoading">
        <template #header>
          <div class="panel-header">
            <div class="panel-header-meta">
              <strong>奖励记录</strong>
              <span>共 {{ formatCount(rewardTotal) }} 条</span>
            </div>
          </div>
        </template>

        <el-table :data="rewardList" stripe>
          <template #empty>
            <div class="panel-empty">
              <strong>暂无奖励记录</strong>
              <p>推荐订单成功支付并命中奖励规则后，会在这里生成返利记录。</p>
            </div>
          </template>

          <el-table-column prop="id" label="ID" width="80" />

          <el-table-column label="推荐关系" min-width="220">
            <template #default="{ row }">
              <div class="relation-cell">
                <strong>{{ row.referrer?.display_name || row.referrer?.email || '--' }}</strong>
                <span>下级：{{ row.referred_user?.display_name || row.referred_user?.email || '--' }}</span>
              </div>
            </template>
          </el-table-column>

          <el-table-column label="订单 / 配置" min-width="220">
            <template #default="{ row }">
              <div class="relation-cell">
                <strong>{{ row.order?.order_no || '--' }}</strong>
                <span>{{ row.order?.product_spec_display || row.order?.product_display_name || row.product?.display_name || '未关联配置' }}</span>
              </div>
            </template>
          </el-table-column>

          <el-table-column label="订单金额" min-width="120" align="right">
            <template #default="{ row }">
              {{ formatCurrency(row.order_amount) }}
            </template>
          </el-table-column>

          <el-table-column label="奖励比例" min-width="110" align="right">
            <template #default="{ row }">
              {{ formatPercent(row.reward_rate) }}
            </template>
          </el-table-column>

          <el-table-column label="奖励金额" min-width="120" align="right">
            <template #default="{ row }">
              {{ formatCurrency(row.reward_amount) }}
            </template>
          </el-table-column>

          <el-table-column label="状态" width="120">
            <template #default="{ row }">
              <el-tag size="small" :type="rewardStatusType(row.status)">
                {{ rewardStatusLabel(row.status) }}
              </el-tag>
            </template>
          </el-table-column>

          <el-table-column label="时间" min-width="180">
            <template #default="{ row }">
              <div class="relation-cell">
                <strong>{{ row.rewarded_at || '--' }}</strong>
                <span>{{ row.released_at ? `释放：${row.released_at}` : `可用：${row.available_at || '--'}` }}</span>
              </div>
            </template>
          </el-table-column>

          <el-table-column label="备注" min-width="220">
            <template #default="{ row }">
              <span class="remark-text">{{ row.remark || '--' }}</span>
            </template>
          </el-table-column>
        </el-table>

        <div class="table-pagination">
          <el-pagination
            v-model:current-page="rewardPage"
            v-model:page-size="rewardPageSize"
            :total="rewardTotal"
            :page-sizes="[10, 20, 50, 100]"
            layout="total, sizes, prev, pager, next"
            @size-change="loadRewards"
            @current-change="loadRewards"
          />
        </div>
      </el-card>
    </template>

    <template v-else>
      <section class="table-toolbar-card">
        <div class="table-toolbar-head">
          <div class="panel-header-meta">
            <strong>提现记录筛选</strong>
            <span>支持按用户、支付宝账号和提现状态查询申请记录</span>
          </div>
        </div>

        <div class="search-bar">
          <el-input
            v-model="withdrawalFilters.keyword"
            clearable
            class="search-field search-field-wide"
            placeholder="搜索用户、邮箱、账号或备注"
            @keyup.enter="handleWithdrawalSearch"
          />

          <el-select
            v-model="withdrawalFilters.status"
            clearable
            class="search-field"
            placeholder="全部状态"
          >
            <el-option
              v-for="item in withdrawalStatusOptions"
              :key="item.value"
              :label="item.label"
              :value="item.value"
            />
          </el-select>

          <el-button type="primary" @click="handleWithdrawalSearch">搜索</el-button>
          <el-button @click="resetWithdrawalFilters">重置</el-button>
        </div>
      </section>

      <el-card shadow="never" v-loading="withdrawalsLoading">
        <template #header>
          <div class="panel-header">
            <div class="panel-header-meta">
              <strong>提现记录</strong>
              <span>共 {{ formatCount(withdrawalTotal) }} 条</span>
            </div>
          </div>
        </template>

        <el-table :data="withdrawalList" stripe>
          <template #empty>
            <div class="panel-empty">
              <strong>暂无提现记录</strong>
              <p>用户发起返利提现申请后，会在这里等待审核处理。</p>
            </div>
          </template>

          <el-table-column prop="id" label="ID" width="80" />

          <el-table-column label="申请用户" min-width="220">
            <template #default="{ row }">
              <div class="user-cell">
                <strong>{{ row.user?.display_name || row.user?.email || '--' }}</strong>
                <span>{{ row.user?.email || '--' }}</span>
              </div>
            </template>
          </el-table-column>

          <el-table-column label="提现金额" min-width="120" align="right">
            <template #default="{ row }">
              {{ formatCurrency(row.amount) }}
            </template>
          </el-table-column>

          <el-table-column label="提现方式" min-width="220">
            <template #default="{ row }">
              <div class="relation-cell">
                <strong>{{ withdrawalMethodLabel(row.method) }}</strong>
                <span>{{ row.account_name || '--' }} / {{ row.account_no || '--' }}</span>
              </div>
            </template>
          </el-table-column>

          <el-table-column label="状态" width="120">
            <template #default="{ row }">
              <el-tag size="small" :type="withdrawalStatusType(row.status)">
                {{ withdrawalStatusLabel(row.status) }}
              </el-tag>
            </template>
          </el-table-column>

          <el-table-column label="申请 / 处理时间" min-width="220">
            <template #default="{ row }">
              <div class="relation-cell">
                <strong>{{ formatDateTime(row.created_at) }}</strong>
                <span>{{ row.processed_at ? `处理：${formatDateTime(row.processed_at)}` : '待审核' }}</span>
              </div>
            </template>
          </el-table-column>

          <el-table-column label="处理信息" min-width="220">
            <template #default="{ row }">
              <div class="relation-cell">
                <strong>{{ row.operator || '--' }}</strong>
                <span>{{ row.remark || '暂无备注' }}</span>
              </div>
            </template>
          </el-table-column>

          <el-table-column label="操作" :width="isMobile ? 60 : 120" fixed="right">
            <template #default="{ row }">
              <div v-if="!isMobile">
                <div v-if="Number(row.status) === 0" class="table-actions">
                  <el-button size="small" text type="primary" @click="handleApprove(row)">通过</el-button>
                  <el-button size="small" text type="danger" @click="handleReject(row)">拒绝</el-button>
                </div>
                <span v-else class="muted-text">已处理</span>
              </div>
              <el-dropdown v-else-if="Number(row.status) === 0" trigger="click" @command="(cmd) => handleReferralAction(cmd, row)">
                <span class="action-link">···</span>
                <template #dropdown>
                  <el-dropdown-menu>
                    <el-dropdown-item command="approve">通过</el-dropdown-item>
                    <el-dropdown-item command="reject" divided>拒绝</el-dropdown-item>
                  </el-dropdown-menu>
                </template>
              </el-dropdown>
              <span v-else class="muted-text">已处理</span>
            </template>
          </el-table-column>
        </el-table>

        <div class="table-pagination">
          <el-pagination
            v-model:current-page="withdrawalPage"
            v-model:page-size="withdrawalPageSize"
            :total="withdrawalTotal"
            :page-sizes="[10, 20, 50, 100]"
            layout="total, sizes, prev, pager, next"
            @size-change="loadWithdrawals"
            @current-change="loadWithdrawals"
          />
        </div>
      </el-card>
    </template>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import adminApi from '@/api/admin'
import { formatDateTime } from '@/utils/datetime'
import { REWARD_STATUS_MAP, getStatusLabel, getStatusTagType, resolveElTagType, toSelectOptions } from '@shared/statusConfig'
import { useResponsive } from '@/composables/useResponsive'

const { isMobile } = useResponsive()

const tabs = [
  { key: 'overview', label: '概览' },
  { key: 'rewards', label: '奖励' },
  { key: 'withdrawals', label: '提现' },
]

const rewardStatusOptions = toSelectOptions(REWARD_STATUS_MAP, false)

const withdrawalStatusOptions = [
  { label: '待审核', value: 0 },
  { label: '已通过', value: 1 },
  { label: '已拒绝', value: 2 },
]

const currentTab = ref('overview')
const overviewLoading = ref(false)
const rewardsLoading = ref(false)
const withdrawalsLoading = ref(false)

const overview = ref({
  summary: {},
  top_referrers: [],
})

const rewardList = ref([])
const rewardTotal = ref(0)
const rewardPage = ref(1)
const rewardPageSize = ref(20)
const rewardFilters = reactive({
  keyword: '',
  status: null,
})

const withdrawalList = ref([])
const withdrawalTotal = ref(0)
const withdrawalPage = ref(1)
const withdrawalPageSize = ref(20)
const withdrawalFilters = reactive({
  keyword: '',
  status: null,
})

const currentTabDescription = computed(() => ({
  overview: '查看推广核心指标与推广达人排行。',
  rewards: '查看账单返利记录、冻结期和释放结果。',
  withdrawals: '审核返利提现申请并追踪处理状态。',
})[currentTab.value] || '')

function formatCount(value) {
  return Number(value || 0).toLocaleString('zh-CN')
}

function formatCurrency(value) {
  return `¥${Number(value || 0).toFixed(2)}`
}

function formatPercent(value) {
  return `${Number(value || 0).toFixed(2)}%`
}

function rewardStatusLabel(status) {
  return getStatusLabel(REWARD_STATUS_MAP, Number(status))
}

function rewardStatusType(status) {
  return resolveElTagType(getStatusTagType(REWARD_STATUS_MAP, Number(status)))
}

function withdrawalStatusLabel(status) {
  return ({
    0: '待审核',
    1: '已通过',
    2: '已拒绝',
  })[Number(status)] || '--'
}

function withdrawalStatusType(status) {
  return ({
    0: 'warning',
    1: 'success',
    2: 'danger',
  })[Number(status)] || 'info'
}

function withdrawalMethodLabel(method) {
  return ({
    alipay: '支付宝',
    balance: '余额转回',
  })[String(method || '')] || method || '--'
}

function buildRewardParams() {
  return {
    keyword: rewardFilters.keyword || undefined,
    status: rewardFilters.status ?? undefined,
    page: rewardPage.value,
    page_size: rewardPageSize.value,
  }
}

function buildWithdrawalParams() {
  return {
    keyword: withdrawalFilters.keyword || undefined,
    status: withdrawalFilters.status ?? undefined,
    page: withdrawalPage.value,
    page_size: withdrawalPageSize.value,
  }
}

async function loadOverview() {
  overviewLoading.value = true
  try {
    const res = await adminApi.referral.overview()
    overview.value = {
      summary: res.data?.summary || {},
      top_referrers: res.data?.top_referrers || [],
    }
  } catch {
    overview.value = {
      summary: {},
      top_referrers: [],
    }
  } finally {
    overviewLoading.value = false
  }
}

async function loadRewards() {
  rewardsLoading.value = true
  try {
    const res = await adminApi.referral.rewards(buildRewardParams())
    rewardList.value = res.data?.list || []
    rewardTotal.value = res.data?.total || 0
    rewardPage.value = res.data?.page || rewardPage.value
    rewardPageSize.value = res.data?.page_size || rewardPageSize.value
  } catch {
    rewardList.value = []
    rewardTotal.value = 0
  } finally {
    rewardsLoading.value = false
  }
}

async function loadWithdrawals() {
  withdrawalsLoading.value = true
  try {
    const res = await adminApi.referral.withdrawals(buildWithdrawalParams())
    withdrawalList.value = res.data?.list || []
    withdrawalTotal.value = res.data?.total || 0
    withdrawalPage.value = res.data?.page || withdrawalPage.value
    withdrawalPageSize.value = res.data?.page_size || withdrawalPageSize.value
  } catch {
    withdrawalList.value = []
    withdrawalTotal.value = 0
  } finally {
    withdrawalsLoading.value = false
  }
}

async function loadAll() {
  await Promise.allSettled([loadOverview(), loadRewards(), loadWithdrawals()])
}

async function refreshCurrentTab() {
  if (currentTab.value === 'overview') {
    await loadOverview()
    return
  }

  if (currentTab.value === 'rewards') {
    await loadRewards()
    return
  }

  await loadWithdrawals()
}

function handleRewardSearch() {
  rewardPage.value = 1
  loadRewards()
}

function resetRewardFilters() {
  rewardFilters.keyword = ''
  rewardFilters.status = null
  rewardPage.value = 1
  loadRewards()
}

function handleWithdrawalSearch() {
  withdrawalPage.value = 1
  loadWithdrawals()
}

function resetWithdrawalFilters() {
  withdrawalFilters.keyword = ''
  withdrawalFilters.status = null
  withdrawalPage.value = 1
  loadWithdrawals()
}

function handleReferralAction(command, row) {
  if (command === 'approve') {
    handleApprove(row)
  } else if (command === 'reject') {
    handleReject(row)
  }
}

async function handleApprove(row) {
  let promptValue = ''
  try {
    const result = await ElMessageBox.prompt('可选填写通过备注，留空则直接通过。', `通过提现申请 #${row.id}`, {
      confirmButtonText: '确认通过',
      cancelButtonText: '取消',
      inputPattern: /^.{0,255}$/,
      inputErrorMessage: '备注不能超过 255 个字符',
    })
    promptValue = result.value || ''
  } catch (action) {
    if (action === 'cancel' || action === 'close') {
      return
    }
    throw action
  }

  await adminApi.referral.approveWithdrawal(row.id, {
    remark: promptValue.trim() || undefined,
  })
  ElMessage.success('提现申请已通过')
  await Promise.allSettled([loadOverview(), loadWithdrawals()])
}

async function handleReject(row) {
  let promptValue = ''
  try {
    const result = await ElMessageBox.prompt('请填写拒绝原因，用户会基于该备注查看处理结果。', `拒绝提现申请 #${row.id}`, {
      confirmButtonText: '确认拒绝',
      cancelButtonText: '取消',
      inputPattern: /^.{1,255}$/,
      inputErrorMessage: '请输入 1-255 个字符的拒绝原因',
    })
    promptValue = result.value || ''
  } catch (action) {
    if (action === 'cancel' || action === 'close') {
      return
    }
    throw action
  }

  await adminApi.referral.rejectWithdrawal(row.id, {
    remark: promptValue.trim(),
  })
  ElMessage.success('提现申请已拒绝')
  await Promise.allSettled([loadOverview(), loadWithdrawals()])
}

onMounted(loadAll)
</script>

<style scoped lang="scss">
.page-actions,
.panel-header,
.table-toolbar-head,
.table-actions,
.table-pagination {
  display: flex;
  gap: 12px;
  align-items: center;
}

.page-actions {
  justify-content: flex-end;
}

.panel-header,
.table-toolbar-head,
.table-pagination {
  justify-content: space-between;
}

.panel-header-meta {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.panel-header-meta strong,
.user-cell strong,
.relation-cell strong,
.level-chip strong {
  color: $text-color-primary;
  font-size: 15px;
  font-weight: 600;
}

.panel-header-meta span,
.tab-description,
.user-cell span,
.relation-cell span,
.level-chip span,
.remark-text,
.muted-text {
  color: $text-color-secondary;
  font-size: 12px;
  line-height: 1.6;
}

.tab-toolbar,
.table-toolbar-card {
  padding: 16px;
  border: 1px solid $border-color;
  border-radius: $base-border-radius;
  background: $bg-color-card;
}

.tab-toolbar {
  display: flex;
  justify-content: space-between;
  gap: 16px;
  align-items: center;
}

.view-segmented {
  :deep(.el-segmented) {
    padding: 4px;
    border: 1px solid $border-color;
    border-radius: 999px;
    background: $bg-color-soft;
  }

  :deep(.el-segmented__item) {
    min-height: 34px;
    padding: 0 14px;
    color: $text-color-secondary;
    font-size: 13px;
    font-weight: 600;
  }

  :deep(.el-segmented__item-selected) {
    color: $color-primary;
    background: $bg-color-card;
    box-shadow: inset 0 0 0 1px $color-primary-border;
  }
}

.search-field {
  width: 180px;
}

.search-field-wide {
  width: 320px;
}

.user-cell,
.relation-cell,
.level-chip {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.level-chip span {
  color: $color-primary;
}

.remark-text {
  display: inline-block;
}

.panel-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 8px;
  min-height: 220px;
}

.panel-empty strong {
  color: $text-color-primary;
  font-size: 15px;
  font-weight: 600;
}

.panel-empty p {
  color: $text-color-secondary;
  font-size: 12px;
}

.table-pagination {
  margin-top: 16px;
}

@media (max-width: 900px) {
  .page-actions,
  .tab-toolbar,
  .table-toolbar-head,
  .table-pagination,
  .panel-header {
    flex-direction: column;
    align-items: stretch;
  }

  .search-field,
  .search-field-wide {
    width: 100%;
  }
}

</style>
