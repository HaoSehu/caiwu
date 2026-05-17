<template>
  <div>
    <div class="toolbar compact">
      <el-input
        v-model="state.filters.keyword"
        placeholder="搜索 action 关键字"
        clearable
        @keyup.enter="emit('search')"
      />
      <el-button type="primary" @click="emit('search')">查询</el-button>
    </div>

    <el-table :data="state.list" v-loading="state.loading" stripe :row-key="resolveRowKey">
      <el-table-column label="时间" min-width="160">
        <template #default="{ row }">{{ formatDateTime(row.created_at) }}</template>
      </el-table-column>
      <el-table-column prop="action" label="动作" min-width="180" show-overflow-tooltip />
      <el-table-column prop="module" label="模块" width="120" />
      <el-table-column prop="ip_address" label="IP" min-width="130" />
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
})

const emit = defineEmits(['search', 'reload'])

function resolveRowKey(row) {
  return row?.id || `${row?.created_at || ''}-${row?.action || ''}-${row?.ip_address || ''}`
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
.toolbar {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 12px;
  flex-wrap: wrap;
}

.toolbar.compact :deep(.el-input) {
  width: 240px;
}

.pager {
  display: flex;
  justify-content: flex-end;
  margin-top: 14px;
}
</style>
