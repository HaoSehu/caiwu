<template>
  <div class="admin-log-page">
    <section class="page-header">
      <div class="page-title-group">
        <h2>API 日志</h2>
        <p>审计后台与客户端接口访问情况，快速定位异常状态码与请求链路。</p>
      </div>
    </section>

    <el-card shadow="never" class="panel-card">
      <el-form :model="searchForm" class="filter-form api-filter-form" @submit.prevent>
        <el-form-item label="关键词" class="keyword-form-item">
          <el-input
            v-model="searchForm.keyword"
            placeholder="路径、模块、请求号或 IP"
            clearable
            @keyup.enter="handleSearch"
          />
        </el-form-item>

        <el-form-item label="请求方法" class="method-form-item">
          <el-select v-model="searchForm.method" placeholder="全部方法" clearable>
            <el-option
              v-for="item in methodOptions"
              :key="item"
              :label="item"
              :value="item"
            />
          </el-select>
        </el-form-item>

        <el-form-item label="模块" class="module-form-item">
          <el-input
            v-model="searchForm.module"
            placeholder="例如 auth、order"
            clearable
            @keyup.enter="handleSearch"
          />
        </el-form-item>

        <el-form-item label="调用端" class="user-type-form-item">
          <el-select v-model="searchForm.user_type" placeholder="全部调用端" clearable>
            <el-option label="管理员" value="admin" />
            <el-option label="客户" value="client" />
            <el-option label="访客" value="guest" />
          </el-select>
        </el-form-item>

        <el-form-item label="状态码" class="status-form-item">
          <el-select v-model="searchForm.status" placeholder="全部状态码" clearable>
            <el-option
              v-for="item in statusOptions"
              :key="item"
              :label="String(item)"
              :value="item"
            />
          </el-select>
        </el-form-item>

        <el-form-item label="日期范围" class="date-range-form-item">
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

        <div class="filter-actions" />
      </el-form>
    </el-card>

    <el-card shadow="never" class="panel-card">
      <template #header>
        <div class="table-header">
          <div>
            <h3>请求明细</h3>
            <p>共 {{ pagination.total }} 条记录，支持查看状态码、参数与请求上下文。</p>
          </div>
        </div>
      </template>

      <p class="mobile-scroll-hint">左右滑动可查看完整表格</p>

      <div class="table-scroll-shell">
        <el-table v-loading="loading" :data="tableData" border row-key="id" class="admin-log-table api-log-table">
          <el-table-column label="请求" min-width="320">
            <template #default="{ row }">
              <div class="request-cell">
                <div class="request-main">
                  <el-tag size="small" effect="plain" class="request-method-tag">{{ row.method || '-' }}</el-tag>
                  <strong>{{ row.path || '-' }}</strong>
                </div>
                <span class="mono-text dim-text">{{ row.request_id || '无请求号' }}</span>
              </div>
            </template>
          </el-table-column>

          <el-table-column label="状态码" width="110" align="center">
            <template #default="{ row }">
              <el-tag :type="getHttpStatusTagType(row.status)" effect="light">
                {{ row.status || '-' }}
              </el-tag>
            </template>
          </el-table-column>

          <el-table-column prop="module" label="模块" min-width="120" />

          <el-table-column label="调用端" min-width="160">
            <template #default="{ row }">
              <div class="actor-cell">
                <strong>{{ row.actor_name || getUserTypeLabel(row.user_type) }}</strong>
                <span class="dim-text">{{ getUserTypeLabel(row.user_type) }}</span>
              </div>
            </template>
          </el-table-column>

          <el-table-column prop="ip_address" label="IP" min-width="140" />

          <el-table-column label="记录时间" width="180">
            <template #default="{ row }">
              {{ formatLogDate(row.created_at) }}
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
            <el-empty description="暂无 API 日志" />
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

    <el-drawer v-model="detailVisible" title="API 日志详情" size="700px">
      <template v-if="currentLog">
        <div class="drawer-grid">
          <div class="drawer-item">
            <span class="drawer-label">请求方法</span>
            <strong>{{ currentLog.method || '-' }}</strong>
          </div>
          <div class="drawer-item">
            <span class="drawer-label">状态码</span>
            <strong>{{ currentLog.status || '-' }}</strong>
          </div>
          <div class="drawer-item">
            <span class="drawer-label">请求路径</span>
            <strong>{{ currentLog.path || '-' }}</strong>
          </div>
          <div class="drawer-item">
            <span class="drawer-label">模块</span>
            <strong>{{ currentLog.module || '-' }}</strong>
          </div>
          <div class="drawer-item">
            <span class="drawer-label">请求号</span>
            <strong>{{ currentLog.request_id || '-' }}</strong>
          </div>
          <div class="drawer-item">
            <span class="drawer-label">记录时间</span>
            <strong>{{ formatLogDate(currentLog.created_at) }}</strong>
          </div>
          <div class="drawer-item">
            <span class="drawer-label">调用端</span>
            <strong>{{ getUserTypeLabel(currentLog.user_type) }}</strong>
          </div>
          <div class="drawer-item">
            <span class="drawer-label">IP 地址</span>
            <strong>{{ currentLog.ip_address || '-' }}</strong>
          </div>
        </div>

        <div class="json-title">请求参数</div>
        <div class="json-block">{{ formatJsonBlock(currentLog.params) }}</div>

        <div class="json-title">完整上下文</div>
        <div class="json-block">{{ formatJsonBlock(currentLog.detail) }}</div>

        <div class="json-title">User-Agent</div>
        <div class="json-block">{{ formatJsonBlock(currentLog.user_agent) }}</div>
      </template>
    </el-drawer>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { ElMessage } from 'element-plus'
import { View } from '@element-plus/icons-vue'
import { getApiLogs } from '@/api/admin'
import {
  buildDateRangeParams,
  compactParams,
  formatJsonBlock,
  formatLogDate,
  getHttpStatusTagType,
  getUserTypeLabel,
} from './logUtils'
import { usePagedQuery } from './usePagedQuery'
import { useResponsive } from '@/composables/useResponsive'

const { isMobile } = useResponsive()
const methodOptions = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'HEAD']
const statusOptions = [200, 201, 204, 400, 401, 403, 404, 422, 500]

const detailVisible = ref(false)
const currentLog = ref(null)
const tableData = ref([])
const summary = ref({
  total: 0,
  errors: 0,
  admin_count: 0,
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
    keyword: '',
    method: '',
    module: '',
    user_type: '',
    status: '',
    dateRange: [],
  }),
  initialPerPage: 15,
  query: async ({ filters, pagination }) => {
    const res = await getApiLogs(compactParams({
      keyword: filters.keyword,
      method: filters.method,
      module: filters.module,
      user_type: filters.user_type,
      status: filters.status,
      ...buildDateRangeParams(filters.dateRange),
      page: pagination.page,
      per_page: pagination.per_page,
    }))

    return {
      rows: res.data?.data || [],
      pagination: {
        total: res.data?.total || 0,
        page: res.data?.current_page || pagination.page,
        per_page: res.data?.per_page || pagination.per_page,
      },
      extra: {
        summary: {
          total: res.data?.summary?.total || 0,
          errors: res.data?.summary?.errors || 0,
          admin_count: res.data?.summary?.admin_count || 0,
        },
      },
    }
  },
  onSuccess: ({ rows, extra }) => {
    tableData.value = rows || []
    summary.value = extra?.summary || {
      total: 0,
      errors: 0,
      admin_count: 0,
    }
  },
  onError: (error) => {
    ElMessage.error(error.message || '加载 API 日志失败')
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

.request-cell,
.request-main,
.actor-cell {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.request-main strong,
.actor-cell strong {
  color: $text-color-primary;
  word-break: break-all;
}

.request-main {
  gap: 8px;
}

.api-filter-form {
  grid-template-columns: minmax(260px, 1.3fr) repeat(3, minmax(180px, 1fr)) minmax(120px, 140px);
}

.date-range-form-item {
  grid-column: span 2;
}

.filter-actions {
  grid-column: 3 / -1;
  justify-content: flex-start;
}

.status-form-item :deep(.el-select) {
  width: 100%;
  min-width: 120px;
}

.date-range-form-item :deep(.el-date-editor) {
  width: 100%;
}

.request-method-tag {
  align-self: flex-start;
}

.api-log-table {
  min-width: 1120px;
}

.json-title {
  margin-top: 16px;
  color: $text-color-primary;
  font-size: 13px;
  font-weight: 600;
}

@media (max-width: 1440px) {
  .api-filter-form {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }

  .date-range-form-item,
  .filter-actions {
    grid-column: auto;
  }
}

@media (max-width: 900px) {
  .api-filter-form {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 768px) {
  .api-filter-form {
    grid-template-columns: 1fr;
  }
}
</style>




