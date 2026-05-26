<template>
  <div class="admin-log-page">
    <section class="page-header">
      <div class="page-title-group">
        <h2>定时任务日志</h2>
        <p>聚合展示调度任务的执行结果、错误级别和原始日志内容。</p>
      </div>
    </section>

    <section class="summary-strip">
      <article class="summary-item">
        <strong>{{ summary.total }}</strong>
        <span>日志总数</span>
      </article>
      <article class="summary-item">
        <strong>{{ summary.tasks }}</strong>
        <span>涉及任务</span>
      </article>
      <article class="summary-item">
        <strong>{{ summary.errors }}</strong>
        <span>错误日志</span>
      </article>
    </section>

    <el-card shadow="never" class="panel-card">
      <el-form :model="searchForm" class="filter-form" @submit.prevent>
        <el-form-item label="任务名称">
          <el-select v-model="searchForm.task_key" placeholder="全部任务" clearable>
            <el-option
              v-for="item in TASK_LOG_OPTIONS"
              :key="item.value"
              :label="item.label"
              :value="item.value"
            />
          </el-select>
        </el-form-item>

        <el-form-item label="日志级别">
          <el-select v-model="searchForm.level" placeholder="全部级别" clearable>
            <el-option
              v-for="item in LOG_LEVEL_OPTIONS"
              :key="item"
              :label="item"
              :value="item"
            />
          </el-select>
        </el-form-item>

        <el-form-item label="关键词">
          <el-input
            v-model="searchForm.keyword"
            placeholder="任务名称或日志内容"
            clearable
            @keyup.enter="handleSearch"
          />
        </el-form-item>

        <el-form-item label="日期范围">
          <el-date-picker
            v-model="searchForm.dateRange"
            type="daterange"
            value-format="YYYY-MM-DD"
            range-separator="至"
            start-placeholder="开始日期"
            end-placeholder="结束日期"
            clearable
          />
        </el-form-item>

        <div class="filter-actions">
          <el-button type="primary" :icon="Search" @click="handleSearch">查询</el-button>
          <el-button :icon="RefreshRight" @click="handleReset">重置</el-button>
        </div>
      </el-form>
    </el-card>

    <el-card shadow="never" class="panel-card">
      <template #header>
        <div class="table-header">
          <div>
            <h3>执行明细</h3>
            <p>共 {{ pagination.total }} 条记录，支持按任务与日志级别筛选。</p>
          </div>
        </div>
      </template>

      <p class="mobile-scroll-hint">左右滑动可查看完整表格</p>

      <div class="table-scroll-shell">
        <el-table
          v-loading="loading"
          :data="tableData"
          border
          row-key="id"
          class="admin-log-table task-log-table"
        >
          <el-table-column label="记录时间" width="180">
            <template #default="{ row }">
              {{ formatLogDate(row.time) }}
            </template>
          </el-table-column>

          <el-table-column label="任务" min-width="220">
            <template #default="{ row }">
              <div class="task-cell">
                <strong>{{ row.task_title || row.task_key || '-' }}</strong>
                <span class="mono-text dim-text">{{ row.task_key || '-' }}</span>
              </div>
            </template>
          </el-table-column>

          <el-table-column label="级别" width="110" align="center">
            <template #default="{ row }">
              <el-tag :type="getLevelTagType(row.level)" effect="light">
                {{ row.level || '-' }}
              </el-tag>
            </template>
          </el-table-column>

          <el-table-column label="日志内容" min-width="420" show-overflow-tooltip>
            <template #default="{ row }">
              <div class="content-preview">{{ row.message || '-' }}</div>
            </template>
          </el-table-column>

          <el-table-column label="操作" width="88" :fixed="isMobile ? false : 'right'">
            <template #default="{ row }">
              <el-button link type="primary" :icon="View" @click="openDetail(row)">
                详情
              </el-button>
            </template>
          </el-table-column>

          <template #empty>
            <el-empty description="暂无定时任务日志" />
          </template>
        </el-table>
      </div>

      <div class="pagination-wrap">
        <el-pagination
          v-model:current-page="pagination.page"
          v-model:page-size="pagination.per_page"
          :page-sizes="[10, 20, 50, 100]"
          :total="pagination.total"
          layout="total, sizes, prev, pager, next, jumper"
          @current-change="handlePageChange"
          @size-change="handleSizeChange"
        />
      </div>
    </el-card>

    <el-drawer v-model="detailVisible" title="定时任务日志详情" size="640px">
      <template v-if="currentLog">
        <div class="drawer-grid">
          <div class="drawer-item">
            <span class="drawer-label">任务名称</span>
            <strong>{{ currentLog.task_title || currentLog.task_key || '-' }}</strong>
          </div>
          <div class="drawer-item">
            <span class="drawer-label">任务键</span>
            <strong>{{ currentLog.task_key || '-' }}</strong>
          </div>
          <div class="drawer-item">
            <span class="drawer-label">日志级别</span>
            <strong>{{ currentLog.level || '-' }}</strong>
          </div>
          <div class="drawer-item">
            <span class="drawer-label">记录时间</span>
            <strong>{{ formatLogDate(currentLog.time) }}</strong>
          </div>
        </div>

        <div class="json-title">格式化内容</div>
        <div class="json-block">{{ currentLog.message || '-' }}</div>

        <div class="json-title">原始日志</div>
        <div class="json-block">{{ currentLog.raw || '-' }}</div>
      </template>
    </el-drawer>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { ElMessage } from 'element-plus'
import { RefreshRight, Search, View } from '@element-plus/icons-vue'
import { getTaskLogs, getTaskLogsSummary } from '@/api/admin'
import {
  buildDateRangeParams,
  compactParams,
  formatLogDate,
  getLevelTagType,
  LOG_LEVEL_OPTIONS,
  TASK_LOG_OPTIONS,
} from './logUtils'
import { usePagedQuery } from './usePagedQuery'
import { useResponsive } from '@/composables/useResponsive'

const { isMobile } = useResponsive()
const detailVisible = ref(false)
const currentLog = ref(null)
const tableData = ref([])
const summary = ref({
  total: 0,
  tasks: 0,
  errors: 0,
})

const {
  loading,
  filters: searchForm,
  pagination,
  loadData,
  handleSearch,
  handleReset,
  handlePageChange,
  handleSizeChange,
} = usePagedQuery({
  createFilters: () => ({
    task_key: '',
    level: '',
    keyword: '',
    dateRange: [],
  }),
  initialPerPage: 15,
  query: async ({ filters, pagination }) => {
    const params = compactParams({
      task_key: filters.task_key,
      level: filters.level,
      keyword: filters.keyword,
      ...buildDateRangeParams(filters.dateRange),
      page: pagination.page,
      per_page: pagination.per_page,
    })

    const [listRes, summaryRes] = await Promise.all([
      getTaskLogs(params),
      getTaskLogsSummary(compactParams({
        task_key: filters.task_key,
        level: filters.level,
        keyword: filters.keyword,
        ...buildDateRangeParams(filters.dateRange),
      })),
    ])

    return {
      rows: listRes.data?.data || [],
      pagination: {
        total: listRes.data?.total || 0,
        page: listRes.data?.current_page || pagination.page,
        per_page: listRes.data?.per_page || pagination.per_page,
      },
      extra: {
        summary: summaryRes.data || { total: 0, tasks: 0, errors: 0 },
      },
    }
  },
  onSuccess: ({ rows, extra }) => {
    tableData.value = rows || []
    summary.value = extra?.summary || { total: 0, tasks: 0, errors: 0 }
  },
  onError: (error) => {
    ElMessage.error(error.message || '加载定时任务日志失败')
  },
})

function openDetail(row) {
  currentLog.value = row
  detailVisible.value = true
}

onMounted(() => {
  loadData()
})
</script>

<style scoped lang="scss">
@use './logPage.scss';

.task-log-table {
  min-width: 1040px;
}

.task-cell {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.task-cell strong {
  color: $text-color-primary;
}

.json-title {
  margin-top: 16px;
  color: $text-color-primary;
  font-size: 13px;
  font-weight: 600;
}
</style>




