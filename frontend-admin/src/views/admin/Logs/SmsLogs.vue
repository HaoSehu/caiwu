<template>
  <div class="sms-logs-page admin-page">
    <section class="section-header">
      <div class="section-copy">
        <h2>短信日志</h2>
        <p>
          查看短信发送状态、请求号与失败原因。
        </p>
      </div>
      <div class="section-actions">
        <el-button :icon="RefreshRight" @click="loadData">刷新数据</el-button>
      </div>
    </section>

    <el-card shadow="never" class="panel-card filter-card">
      <el-form :model="searchForm" class="filter-form" @submit.prevent>
        <el-form-item label="手机号">
          <el-input
            v-model="searchForm.phone"
            placeholder="输入接收手机号"
            clearable
            @keyup.enter="handleSearch"
          />
        </el-form-item>
        <el-form-item label="模板 / 请求号">
          <el-input
            v-model="searchForm.keyword"
            placeholder="搜索模板编号、请求号或内容"
            clearable
            @keyup.enter="handleSearch"
          />
        </el-form-item>
        <el-form-item label="发送状态">
          <el-select v-model="searchForm.status" placeholder="全部状态" clearable>
            <el-option label="待发送" value="pending" />
            <el-option label="发送成功" value="success" />
            <el-option label="发送失败" value="failed" />
          </el-select>
        </el-form-item>
        <div class="filter-actions">
          <el-button type="primary" :icon="Search" @click="handleSearch">查询</el-button>
          <el-button :icon="RefreshRight" @click="handleReset">重置</el-button>
        </div>
      </el-form>
    </el-card>

    <el-card shadow="never" class="panel-card table-card">
      <template #header>
        <div class="table-header">
          <div>
            <h3>发送明细</h3>
            <p>共 {{ pagination.total }} 条记录，支持查看模板参数、回执请求号和完整发送内容。</p>
          </div>
        </div>
      </template>

      <p class="mobile-scroll-hint">左右滑动可查看完整表格</p>

      <div class="table-scroll-shell">
        <el-table :data="tableData" border v-loading="loading" row-key="id" class="logs-table">
          <el-table-column prop="id" label="ID" width="80" />
          <el-table-column label="接收信息" min-width="200">
            <template #default="{ row }">
              <div class="recipient-cell">
                <span class="recipient-phone">{{ row.phone || '-' }}</span>
                <span class="recipient-time">发送时间：{{ formatDate(row.sent_at) }}</span>
              </div>
            </template>
          </el-table-column>
          <el-table-column label="模板信息" min-width="190">
            <template #default="{ row }">
              <div class="template-cell">
                <strong>{{ row.template_code || '-' }}</strong>
                <span>通道：{{ row.provider || '-' }}</span>
              </div>
            </template>
          </el-table-column>
          <el-table-column label="内容摘要" min-width="260">
            <template #default="{ row }">
              <div class="content-preview">{{ row.content || '-' }}</div>
            </template>
          </el-table-column>
          <el-table-column prop="status" label="状态" width="120" align="center">
            <template #default="{ row }">
              <el-tag :type="statusMeta[row.status]?.type || 'info'" effect="light">
                {{ statusMeta[row.status]?.label || '未知状态' }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column label="请求号" min-width="180" show-overflow-tooltip>
            <template #default="{ row }">
              <span class="request-id">{{ row.request_id || '-' }}</span>
            </template>
          </el-table-column>
          <el-table-column label="错误信息" min-width="220" show-overflow-tooltip>
            <template #default="{ row }">
              <span :class="{ 'error-text': row.error_msg }">{{ row.error_msg || '-' }}</span>
            </template>
          </el-table-column>
          <el-table-column label="创建时间" width="180">
            <template #default="{ row }">
              {{ formatDate(row.created_at) }}
            </template>
          </el-table-column>
          <el-table-column label="操作" width="96" :fixed="isMobile ? false : 'right'">
            <template #default="{ row }">
              <el-button link type="primary" :icon="View" @click="openDetail(row)">
                详情
              </el-button>
            </template>
          </el-table-column>

          <template #empty>
            <el-empty description="暂无短信日志">
              <el-button type="primary" :icon="RefreshRight" @click="loadData">重新加载</el-button>
            </el-empty>
          </template>
        </el-table>
      </div>

      <div class="pagination-wrap">
        <el-pagination
          v-model:current-page="pagination.page"
          v-model:page-size="pagination.per_page"
          :page-sizes="[10, 15, 30, 50]"
          :total="pagination.total"
          layout="total, sizes, prev, pager, next, jumper"
          @current-change="handlePageChange"
          @size-change="handleSizeChange"
        />
      </div>
    </el-card>

    <el-drawer v-model="detailVisible" title="短信详情" size="560px">
      <template v-if="currentLog">
        <div class="detail-section">
          <div class="detail-grid">
            <div class="detail-item">
              <span class="detail-label">手机号</span>
              <strong>{{ currentLog.phone || '-' }}</strong>
            </div>
            <div class="detail-item">
              <span class="detail-label">发送状态</span>
              <el-tag :type="statusMeta[currentLog.status]?.type || 'info'" effect="light" round>
                {{ statusMeta[currentLog.status]?.label || '未知状态' }}
              </el-tag>
            </div>
            <div class="detail-item">
              <span class="detail-label">模板编号</span>
              <strong>{{ currentLog.template_code || '-' }}</strong>
            </div>
            <div class="detail-item">
              <span class="detail-label">供应商</span>
              <strong>{{ currentLog.provider || '-' }}</strong>
            </div>
            <div class="detail-item">
              <span class="detail-label">请求号</span>
              <strong>{{ currentLog.request_id || '-' }}</strong>
            </div>
            <div class="detail-item">
              <span class="detail-label">发送时间</span>
              <strong>{{ formatDate(currentLog.sent_at) }}</strong>
            </div>
            <div class="detail-item">
              <span class="detail-label">创建时间</span>
              <strong>{{ formatDate(currentLog.created_at) }}</strong>
            </div>
            <div class="detail-item">
              <span class="detail-label">错误信息</span>
              <strong class="error-text">{{ currentLog.error_msg || '-' }}</strong>
            </div>
          </div>
        </div>

        <div class="detail-section">
          <div class="section-title">模板参数</div>
          <div class="params-panel">{{ formatParams(currentLog.params) }}</div>
        </div>

        <div class="detail-section">
          <div class="section-title">短信内容</div>
          <div class="content-panel">{{ currentLog.content || '暂无短信内容' }}</div>
        </div>
      </template>
    </el-drawer>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { RefreshRight, Search, View } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import { getSmsLogs, getSmsLogsSummary } from '@/api/admin'
import { formatDateTime } from '@/utils/datetime'
import { NOTIFY_STATUS_MAP } from '@shared/statusConfig'
import { usePagedQuery } from './usePagedQuery'
import { useResponsive } from '@/composables/useResponsive'

const { isMobile } = useResponsive()
const detailVisible = ref(false)
const currentLog = ref(null)
const tableData = ref([])
const summary = ref({
  total: 0,
  success: 0,
  failed: 0,
  pending: 0,
})

const statusMeta = {
  success: { label: NOTIFY_STATUS_MAP.success.label, type: NOTIFY_STATUS_MAP.success.tagType },
  failed:  { label: NOTIFY_STATUS_MAP.failed.label,  type: NOTIFY_STATUS_MAP.failed.tagType  },
  pending: { label: NOTIFY_STATUS_MAP.pending.label, type: NOTIFY_STATUS_MAP.pending.tagType },
}

const formatDate = (value) => formatDateTime(value)

const formatParams = (params) => {
  if (!params || (typeof params === 'object' && !Object.keys(params).length)) {
    return '暂无模板参数'
  }

  if (typeof params === 'string') {
    return params
  }

  try {
    return JSON.stringify(params, null, 2)
  } catch {
    return '模板参数解析失败'
  }
}

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
    phone: '',
    keyword: '',
    status: '',
  }),
  initialPerPage: 15,
  query: async ({ filters, pagination }) => {
    const params = {
      ...filters,
      page: pagination.page,
      per_page: pagination.per_page,
    }

    const [{ data }, { data: summaryData }] = await Promise.all([
      getSmsLogs(params),
      getSmsLogsSummary({
        phone: filters.phone,
        keyword: filters.keyword,
        status: filters.status,
      }),
    ])

    return {
      rows: data?.data || [],
      pagination: {
        total: data?.total || 0,
        page: data?.current_page || pagination.page,
        per_page: data?.per_page || pagination.per_page,
      },
      extra: {
        summary: summaryData || {
          total: data?.total || 0,
          success: 0,
          failed: 0,
          pending: 0,
        },
      },
    }
  },
  onSuccess: ({ rows, extra }) => {
    tableData.value = rows || []
    summary.value = extra?.summary || {
      total: 0,
      success: 0,
      failed: 0,
      pending: 0,
    }
  },
  onError: (error) => {
    ElMessage.error(error.message || '加载短信日志失败')
  },
})

const openDetail = (row) => {
  currentLog.value = row
  detailVisible.value = true
}

onMounted(() => {
  loadData()
})
</script>

<style lang="scss" scoped>
.sms-logs-page {
  --panel-border: #{$border-color};
  --panel-shadow: none;
  --text-main: #{$text-color-primary};
  --text-secondary: #{$text-color-secondary};
  --brand: #{$color-primary};
  --brand-soft: #{$color-primary-soft};
  --success-soft: #{$color-success-soft};
  --danger-soft: #{$color-danger-soft};
  --pending-soft: #{$color-warning-soft};

  display: flex;
  flex-direction: column;
  gap: 16px;

  .section-header {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 16px;
    padding-bottom: 4px;
    border-bottom: 1px solid var(--panel-border);

    .section-copy h2 {
      margin: 0;
      font-size: 26px;
      color: var(--text-main);
    }

    .section-copy p {
      max-width: 720px;
      margin: 8px 0 0;
      line-height: 1.7;
      color: var(--text-secondary);
    }
  }

  .panel-card {
    border: 1px solid var(--panel-border);
    border-radius: $base-border-radius;
    box-shadow: var(--panel-shadow);
  }

  .filter-form {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr)) auto;
    gap: 16px;
    align-items: end;

    :deep(.el-form-item) {
      margin-bottom: 0;
    }

    :deep(.el-input__wrapper),
    :deep(.el-select__wrapper) {
      min-height: 40px;
    }
  }

  .filter-actions {
    display: flex;
    gap: 12px;
    justify-content: flex-end;
  }

  .table-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;

    h3 {
      margin: 0;
      font-size: 18px;
      color: var(--text-main);
    }

    p {
      margin: 6px 0 0;
      font-size: 13px;
      color: var(--text-secondary);
    }
  }

  .mobile-scroll-hint {
    display: none;
    margin: 0 0 10px;
    color: var(--text-secondary);
    font-size: 12px;
  }

  .table-scroll-shell {
    overflow-x: auto;
  }

  .table-scroll-shell :deep(.el-table) {
    width: 100%;
  }

  .logs-table {
    min-width: 1360px;
  }

  .recipient-cell,
  .template-cell {
    display: flex;
    flex-direction: column;
    gap: 4px;
  }

  .recipient-phone,
  .template-cell strong {
    font-weight: 600;
    color: var(--text-main);
    word-break: break-all;
  }

  .recipient-time,
  .template-cell span {
    font-size: 12px;
    color: #8a94a6;
  }

  .content-preview {
    display: -webkit-box;
    overflow: hidden;
    color: #475467;
    line-height: 1.6;
    word-break: break-word;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
  }

  .request-id {
    font-family: 'Consolas', 'SFMono-Regular', monospace;
    font-size: 12px;
    color: #344054;
  }

  .error-text {
    color: #d64545;
    word-break: break-word;
  }

  .pagination-wrap {
    display: flex;
    justify-content: flex-end;
    margin-top: 18px;
  }

  .detail-section + .detail-section {
    margin-top: 24px;
  }

  .detail-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px;
  }

  .detail-item {
    display: flex;
    flex-direction: column;
    gap: 8px;
    padding: 16px;
    border-radius: $sm-border-radius;
    background: $bg-color-soft;

    strong {
      color: var(--text-main);
      word-break: break-word;
    }
  }

  .detail-label {
    font-size: 12px;
    font-weight: 600;
    color: var(--text-secondary);
  }

  .section-title {
    margin-bottom: 12px;
    font-size: 14px;
    font-weight: 600;
    color: var(--text-main);
  }

  .params-panel,
  .content-panel {
    padding: 16px;
    border-radius: $sm-border-radius;
    line-height: 1.75;
    white-space: pre-wrap;
    word-break: break-word;
  }

  .params-panel {
    background: #f8fafc;
    color: #344054;
    font-family: 'Consolas', 'SFMono-Regular', monospace;
  }

  .content-panel {
    background: #0f172a;
    color: #f8fafc;
  }
}

@media (max-width: 1200px) {
  .sms-logs-page {
    .filter-form {
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .filter-actions {
      grid-column: 1 / -1;
      justify-content: flex-start;
    }
  }
}

@media (max-width: 768px) {
  .sms-logs-page {
    .section-header {
      flex-direction: column;
      align-items: flex-start;

      h2 {
        font-size: 24px;
      }
    }

    .filter-form,
    .detail-grid {
      grid-template-columns: 1fr;
    }

    .filter-actions,
    .pagination-wrap {
      justify-content: stretch;
    }

    .mobile-scroll-hint {
      display: block;
    }

    .table-scroll-shell {
      margin: 0 -12px;
      padding: 0 12px 6px;
    }

    .filter-actions {
      :deep(.el-button) {
        flex: 1;
      }
    }

    .pagination-wrap {
      overflow-x: auto;
    }
  }
}
</style>






