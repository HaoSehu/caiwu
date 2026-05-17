<template>
  <div>
    <el-table :data="state.list" v-loading="state.loading" stripe :row-key="resolveRowKey">
      <el-table-column prop="id" label="ID" width="80" />
      <el-table-column prop="subject" label="标题" min-width="220" show-overflow-tooltip />
      <el-table-column label="优先级" width="90">
        <template #default="{ row }">
          <el-tag size="small" :type="priorityTagType(row.priority)" effect="plain">
            {{ resolvePriority(row.priority) }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column label="状态" width="100">
        <template #default="{ row }">
          <el-tag size="small" :type="ticketStatusTagType(row.status)" effect="plain">
            {{ resolveTicketStatus(row.status) }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column label="创建时间" min-width="160">
        <template #default="{ row }">{{ formatDateTime(row.created_at) }}</template>
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
defineProps({
  state: {
    type: Object,
    required: true,
  },
  resolvePriority: {
    type: Function,
    required: true,
  },
  resolveTicketStatus: {
    type: Function,
    required: true,
  },
})

const emit = defineEmits(['reload', 'update:page', 'update:pageSize'])

function priorityTagType(priority) {
  const map = { high: 'danger', medium: 'warning', low: '', urgent: 'danger' }
  return map[String(priority).toLowerCase()] || ''
}

function ticketStatusTagType(status) {
  const s = Number(status)
  if (s === 0 || s === 1) return 'warning'   // open / replied
  if (s === 2) return 'success'               // closed
  if (s === 3) return 'info'                  // on-hold
  return ''
}

function resolveRowKey(row) {
  return row?.id || `${row?.created_at || ''}-${row?.subject || ''}-${row?.status || ''}`
}

function handlePageChange(page) {
  emit('update:page', page)
  emit('reload')
}

function handlePageSizeChange(pageSize) {
  emit('update:pageSize', pageSize)
  emit('update:page', 1)
  emit('reload')
}
</script>

<style lang="scss" scoped>
.pager {
  display: flex;
  justify-content: flex-end;
  margin-top: 14px;
}
</style>
