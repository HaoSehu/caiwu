<template>
  <el-card shadow="never" v-loading="loading">
    <template #header>
      <div class="panel-header">
        <div class="panel-header-meta">
          <strong>推广达人排行</strong>
          <span>按累计销售额倒序展示当前收益表现最好的推广用户</span>
        </div>
      </div>
    </template>

    <el-table :data="topReferrers" stripe>
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
        <template #default="{ row }">{{ formatCurrency(row.total_sales_amount) }}</template>
      </el-table-column>

      <el-table-column label="冻结中" min-width="120" align="right">
        <template #default="{ row }">{{ formatCurrency(row.referral_frozen_amount) }}</template>
      </el-table-column>

      <el-table-column label="可提现" min-width="120" align="right">
        <template #default="{ row }">{{ formatCurrency(row.referral_available_amount) }}</template>
      </el-table-column>

      <el-table-column label="提现中" min-width="120" align="right">
        <template #default="{ row }">{{ formatCurrency(row.referral_withdrawing_amount) }}</template>
      </el-table-column>

      <el-table-column label="已提现" min-width="120" align="right">
        <template #default="{ row }">{{ formatCurrency(row.referral_withdrawn_amount) }}</template>
      </el-table-column>
    </el-table>
  </el-card>
</template>

<script setup>
defineProps({
  topReferrers: { type: Array, required: true },
  formatCurrency: { type: Function, required: true },
  loading: { type: Boolean, default: false },
})
</script>

<style lang="scss" scoped>
.panel-header { display: flex; gap: 12px; align-items: center; justify-content: space-between; }
.panel-header-meta { display: flex; flex-direction: column; gap: 4px; }
.panel-header-meta strong { color: $text-color-primary; font-size: 15px; font-weight: 600; }
.panel-header-meta span { color: $text-color-secondary; font-size: 12px; line-height: 1.6; }

.user-cell, .level-chip { display: flex; flex-direction: column; gap: 4px; }
.user-cell strong, .level-chip strong { color: $text-color-primary; font-size: 15px; font-weight: 600; }
.user-cell span { color: $text-color-secondary; font-size: 12px; }
.level-chip span { color: $color-primary; font-size: 12px; }

.panel-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 8px;
  min-height: 220px;
}

.panel-empty strong { color: $text-color-primary; font-size: 15px; font-weight: 600; }
.panel-empty p { color: $text-color-secondary; font-size: 12px; }
</style>
