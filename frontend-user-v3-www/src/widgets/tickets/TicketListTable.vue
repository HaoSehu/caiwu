<template>
  <div class="ticket-list-wrap">
    <!-- 筛选工具栏 -->
    <div class="toolbar-grid">
      <div class="toolbar-left">
        <el-input v-model="keyword" placeholder="搜索工单标题" clearable @keyup.enter="handleSearch" />
        <el-select v-model="status" placeholder="全部状态" clearable @change="handleSearch">
          <el-option label="开启" :value="0" />
          <el-option label="客户回复" :value="1" />
          <el-option label="员工回复" :value="2" />
          <el-option label="已关闭" :value="3" />
        </el-select>
      </div>
      <div class="toolbar-actions">
        <el-button type="primary" @click="emit('create')">
          <el-icon><Plus /></el-icon>
          提交工单
        </el-button>
      </div>
    </div>

    <!-- 列表区 -->
    <section class="ticket-list-shell" v-loading="loading">
      <el-table v-if="!isMobileScreen" :data="list">
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
        <el-table-column label="更新时间" min-width="180">
          <template #default="{ row }">{{ formatTime(row.updated_at) }}</template>
        </el-table-column>
      </el-table>

      <!-- 手机移动端流式卡片列表 -->
      <div v-else class="mobile-ticket-list">
        <div
          v-for="row in list"
          :key="row.id"
          class="mobile-ticket-card"
          @click="emit('view-detail', row)"
        >
          <div class="card-row card-row--top">
            <span class="ticket-id">#{{ row.id }}</span>
            <el-tag :type="resolveTicketTagType(row.status)" size="small" effect="light">
              {{ resolveTicketStatusLabel(row.status) }}
            </el-tag>
          </div>
          <div class="ticket-title">{{ row.subject || '--' }}</div>
          <div class="card-row card-row--bottom">
            <span class="ticket-meta-item">
              优先级：<strong :class="'prio-' + row.priority">{{ resolvePriorityLabel(row.priority) }}</strong>
            </span>
            <span class="ticket-time">{{ formatTime(row.updated_at) }}</span>
          </div>
        </div>
      </div>

      <el-empty v-if="!loading && !list.length" description="暂无工单记录" />
    </section>

    <!-- 分页 -->
    <div v-if="total > 0" class="ticket-pagination">
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
import { useViewport } from '@/composables/useViewport'

const { isMobileScreen } = useViewport()

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

function formatTime(val) {
  if (!val) return '--'
  const d = new Date(val)
  if (isNaN(d.getTime())) return val
  const pad = (n) => String(n).padStart(2, '0')
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}`
}
</script>

<style scoped lang="scss">
.ticket-list-wrap {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

/* ── 筛选工具栏 ── */
.toolbar-grid {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 16px;
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

.toolbar-actions {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
}

.toolbar-actions .el-button {
  white-space: nowrap;
}

/* ── 列表区 ── */
.ticket-list-shell {
  min-height: 240px;
}

.table-link-button {
  border: none;
  background: none;
  color: $color-primary;
  cursor: pointer;
  padding: 0;
}

/* ── 手机移动端流式卡片列表 ── */
.mobile-ticket-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.mobile-ticket-card {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 14px 16px;
  border: 1px solid $border-color;
  border-radius: $base-border-radius;
  background: $bg-color-card;
  box-shadow: $shadow-sm;
  cursor: pointer;
  transition: transform 0.15s ease, background-color 0.15s ease;

  &:active {
    transform: scale(0.99);
    background: $bg-color-hover;
  }

  .card-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
  }

  .ticket-id {
    color: $text-color-placeholder;
    font-size: $font-size-sm;
    font-weight: 600;
  }

  .ticket-title {
    color: $text-color-primary;
    font-size: $font-size-base;
    font-weight: 600;
    line-height: 1.4;
    word-break: break-all;
    text-align: left;
  }

  .ticket-meta-item {
    color: $text-color-secondary;
    font-size: $font-size-sm;

    strong {
      font-weight: 600;

      &.prio-3, &.prio-4 {
        color: $color-danger;
      }
    }
  }

  .ticket-time {
    color: $text-color-placeholder;
    font-size: $font-size-sm;
  }
}

/* ── 分页 ── */
.ticket-pagination {
  display: flex;
  justify-content: flex-end;
}

@media (max-width: 767px) {
  .toolbar-grid {
    grid-template-columns: 1fr;
  }

  .toolbar-actions {
    justify-content: flex-start;
  }

  .ticket-pagination {
    justify-content: flex-start;
  }
}
</style>
