<template>
  <div class="ticket-list-table">
    <div class="toolbar-grid">
      <div class="toolbar-left">
        <el-input v-model="keyword" placeholder="搜索工单标题" clearable @keyup.enter="handleSearch" />
        <el-select v-model="status" placeholder="全部状态" clearable>
          <el-option label="开启" :value="0" />
          <el-option label="客户回复" :value="1" />
          <el-option label="员工回复" :value="2" />
          <el-option label="已关闭" :value="3" />
        </el-select>
      </div>
      <div class="toolbar-actions">
        <el-button @click="handleReset">重置</el-button>
        <el-button type="primary" @click="handleSearch">搜索</el-button>
        <el-button type="primary" @click="emit('create')">
          <el-icon><Plus /></el-icon>
          提交工单
        </el-button>
      </div>
    </div>

    <el-table :data="list" v-loading="loading">
      <el-table-column label="工单号" min-width="100" prop="id" />
      <el-table-column label="标题" min-width="240">
        <template #default="{ row }">
          <button type="button" class="table-link-button" @click="emit('view-detail', row)">
            {{ row.subject || '--' }}
          </button>
        </template>
      </el-table-column>
      <el-table-column label="状态" min-width="120">
        <template #default="{ row }">
          <el-tag :type="resolveTicketTagType(row.status)" effect="light">
            {{ resolveTicketStatusLabel(row.status) }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column label="优先级" min-width="100">
        <template #default="{ row }">{{ resolvePriorityLabel(row.priority) }}</template>
      </el-table-column>
      <el-table-column label="更新时间" min-width="180" prop="updated_at" />
    </el-table>

    <el-empty v-if="!loading && !list.length" description="暂无工单记录" />

    <div v-if="total > 0" class="pager-wrap">
      <el-pagination
        v-model:current-page="page"
        v-model:page-size="pageSize"
        :page-sizes="[10, 20, 50]"
        :total="total"
        layout="total, sizes, prev, pager, next"
        @current-change="emit('page-change')"
        @size-change="emit('size-change')"
      />
    </div>
  </div>
</template>

<script setup>
import { Plus } from '@element-plus/icons-vue'

const props = defineProps({
  list: Array,
  loading: Boolean,
  total: Number,
  page: Number,
  pageSize: Number,
  keyword: String,
  status: [String, Number],
  resolveTicketStatusLabel: Function,
  resolveTicketTagType: Function,
  resolvePriorityLabel: Function,
})

const emit = defineEmits([
  'update:keyword',
  'update:status',
  'update:page',
  'update:pageSize',
  'search',
  'reset',
  'create',
  'page-change',
  'size-change',
  'view-detail',
])

const keyword = defineModel('keyword')
const status = defineModel('status')
const page = defineModel('page')
const pageSize = defineModel('pageSize')

function handleSearch() {
  emit('search')
}

function handleReset() {
  emit('reset')
}
</script>

<style scoped lang="scss">
.ticket-list-table {
  padding: 20px;
  border: 1px solid $border-color;
  border-radius: $base-border-radius;
  background: #fff;
  box-shadow: $shadow-sm;
}

.toolbar-grid {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 16px;
  margin-bottom: 18px;
}

.toolbar-left {
  display: flex;
  gap: 12px;
  align-items: center;
  flex: 1;
}

.toolbar-left .el-input {
  width: 200px;
}

.toolbar-actions,
.pager-wrap {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
}

.toolbar-actions .el-button {
  white-space: nowrap;
}

.pager-wrap {
  margin-top: 16px;
}

.table-link-button {
  border: none;
  background: none;
  color: $color-primary;
  cursor: pointer;
  padding: 0;
}

@media (max-width: 767px) {
  .toolbar-grid {
    grid-template-columns: 1fr;
  }

  .toolbar-actions,
  .pager-wrap {
    justify-content: flex-start;
  }
}
</style>
