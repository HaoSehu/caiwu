<template>
  <div>
	    <el-table :data="state.list" v-loading="state.loading" stripe :row-key="resolveRowKey">
	      <el-table-column label="时间" min-width="160">
	        <template #default="{ row }">{{ formatDateTime(row.occurred_at || row.created_at) }}</template>
	      </el-table-column>
      <el-table-column label="类型" width="120">
        <template #default="{ row }">
          <el-tag size="small" :type="balanceEventTagType(row.event_type)" effect="plain">
            {{ resolveBalanceType(row.event_type) }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column label="变动金额" width="120">
        <template #default="{ row }">
          <span :class="toNumber(row.change_amount) > 0 ? 'amount-up' : toNumber(row.change_amount) < 0 ? 'amount-down' : ''">
            {{ toNumber(row.change_amount) > 0 ? '+' : '' }}{{ formatMoney(row.change_amount) }}
          </span>
        </template>
      </el-table-column>
      <el-table-column label="变动后余额" width="130">
        <template #default="{ row }">{{ formatMoney(row.balance_after) }}</template>
      </el-table-column>
	      <el-table-column prop="remark" label="备注" min-width="180" show-overflow-tooltip />
	      <el-table-column label="操作人" min-width="120">
	        <template #default="{ row }">{{ row.operator || '--' }}</template>
	      </el-table-column>
	    </el-table>

    <div class="pager">
      <el-pagination
        :current-page="state.page"
        :page-size="state.pageSize"
        :total="state.total"
        :page-sizes="[10, 20, 50]"
        layout="total, sizes, prev, pager, next"
        @current-change="handlePageChange"
        @size-change="handlePageSizeChange"
      />
    </div>
  </div>
</template>

<script setup>
import { formatDateTime } from '@/utils/datetime'
const props = defineProps({
  state: {
    type: Object,
    required: true,
  },
  formatMoney: {
    type: Function,
    required: true,
  },
  toNumber: {
    type: Function,
    required: true,
  },
  resolveBalanceType: {
    type: Function,
    required: true,
  },
})

const emit = defineEmits(['reload'])

	function balanceEventTagType(eventType) {
	  const t = String(eventType || '').toLowerCase()
	  if (['recharge', 'refund', 'invoice_refund', 'manual_recharge', 'referral_credit_cash', 'referral_withdraw_approved'].includes(t)) return 'success'
	  if (['admin_deduct', 'deduct', 'manual_deduction', 'invoice_payment', 'consume'].includes(t)) return 'danger'
	  if (['payment', 'pay'].includes(t)) return 'warning'
	  return 'info'
	}
	
	function resolveRowKey(row) {
	  return row?.ledger_id || row?.id || `${row?.occurred_at || row?.created_at || ''}-${row?.event_type || ''}-${row?.change_amount || ''}`
	}

function handlePageChange(page) {
  props.state.page = page
  emit('reload')
}

function handlePageSizeChange(pageSize) {
  props.state.pageSize = pageSize
  props.state.page = 1
  emit('reload')
}
</script>

<style lang="scss" scoped>
.pager {
  display: flex;
  justify-content: flex-end;
  margin-top: 14px;
}

.amount-up {
  color: $color-success;
  font-weight: 600;
  font-variant-numeric: tabular-nums;
}

.amount-down {
  color: $color-danger;
  font-weight: 600;
  font-variant-numeric: tabular-nums;
}
</style>
