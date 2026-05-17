<template>
  <div class="email-logs-page admin-page">
    <section class="section-header">
      <div class="section-copy">
        <h2>邮件日志</h2>
        <p>
          查看邮件发送状态、失败原因与内容摘要。
        </p>
      </div>
      <div class="section-actions">
        <el-button :icon="RefreshRight" @click="loadData">刷新数据</el-button>
      </div>
    </section>

    <el-card shadow="never" class="panel-card filter-card">
      <el-form :model="searchForm" class="filter-form" @submit.prevent>
        <el-form-item label="收件邮箱">
          <el-input
            v-model="searchForm.email"
            placeholder="输入邮箱地址"
            clearable
            @keyup.enter="handleSearch"
          />
        </el-form-item>
        <el-form-item label="模板 / 主题">
          <el-input
            v-model="searchForm.keyword"
            placeholder="搜索模板编号、主题或正文关键词"
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
            <p>共 {{ pagination.total }} 条记录，按创建时间倒序展示。</p>
          </div>
        </div>
      </template>

      <p class="mobile-scroll-hint">左右滑动可查看完整表格</p>

      <div class="table-scroll-shell">
        <el-table :data="tableData" border v-loading="loading" row-key="id" class="logs-table">
          <el-table-column prop="id" label="ID" width="80" />
          <el-table-column label="收件信息" min-width="220">
            <template #default="{ row }">
              <div class="recipient-cell">
                <span class="recipient-email">{{ row.to_email || '-' }}</span>
                <span class="recipient-time">发送时间：{{ formatDate(row.sent_at) }}</span>
              </div>
            </template>
          </el-table-column>
          <el-table-column prop="template_code" label="模板编号" width="120" align="center">
            <template #default="{ row }">
              <el-tag effect="plain" size="small">{{ row.template_code || '-' }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="subject" label="主题" min-width="220" show-overflow-tooltip />
          <el-table-column label="正文摘要" min-width="260">
            <template #default="{ row }">
              <div class="content-preview">{{ getContentPreview(row.content) }}</div>
            </template>
          </el-table-column>
          <el-table-column prop="status" label="状态" width="120" align="center">
            <template #default="{ row }">
              <el-tag :type="statusMeta[row.status]?.type || 'info'" effect="light">
                {{ statusMeta[row.status]?.label || '未知状态' }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="error_msg" label="错误信息" min-width="220" show-overflow-tooltip>
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
            <el-empty description="暂无邮件日志">
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

    <el-drawer v-model="detailVisible" title="邮件详情" size="560px">
      <template v-if="currentLog">
        <div class="detail-section">
          <div class="detail-grid">
            <div class="detail-item">
              <span class="detail-label">收件邮箱</span>
              <strong>{{ currentLog.to_email || '-' }}</strong>
            </div>
            <div class="detail-item">
              <span class="detail-label">发送状态</span>
              <el-tag :type="statusMeta[currentLog.status]?.type || 'info'" effect="light" round>
                {{ statusMeta[currentLog.status]?.label || '未知状态' }}
              </el-tag>
            </div>
            <div class="detail-item">
              <span class="detail-label">主题</span>
              <strong>{{ currentLog.subject || '-' }}</strong>
            </div>
            <div class="detail-item">
              <span class="detail-label">模板编号</span>
              <strong>{{ currentLog.template_code || '-' }}</strong>
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
          <div class="section-title">邮件正文预览</div>
          <iframe
            class="content-preview-frame"
            :srcdoc="buildContentPreviewDoc(currentLog.content)"
            sandbox=""
            title="邮件正文预览"
          />
        </div>

        <div class="detail-section">
          <div class="section-title">HTML 源码</div>
          <div class="content-panel">{{ currentLog.content || '暂无正文内容' }}</div>
        </div>
      </template>
    </el-drawer>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { RefreshRight, Search, View } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import { getEmailLogs, getEmailLogsSummary } from '@/api/admin'
import { formatDateTime } from '@/utils/datetime'
import { NOTIFY_STATUS_MAP } from '@shared/statusConfig'
import { usePagedQuery } from '../../Logs/usePagedQuery'
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

const escapeHtml = (value) => String(value ?? '')
  .replace(/&/g, '&amp;')
  .replace(/</g, '&lt;')
  .replace(/>/g, '&gt;')
  .replace(/"/g, '&quot;')
  .replace(/'/g, '&#39;')

const extractTextContent = (content) => String(content ?? '')
  .replace(/<style[\s\S]*?<\/style>/gi, ' ')
  .replace(/<script[\s\S]*?<\/script>/gi, ' ')
  .replace(/<[^>]+>/g, ' ')
  .replace(/&nbsp;/gi, ' ')
  .replace(/\s+/g, ' ')
  .trim()

const looksLikeHtmlDocument = (content) => /<!doctype\s+html|<html\b|<body\b/i.test(String(content ?? ''))
const looksLikeHtmlFragment = (content) => /<([a-z][a-z0-9]*)(\s|>)/i.test(String(content ?? ''))

const getContentPreview = (content) => {
  const text = extractTextContent(content)
  if (!text) return '-'
  return text.length > 96 ? `${text.slice(0, 96)}...` : text
}

const buildContentPreviewDoc = (content) => {
  const normalized = String(content ?? '').trim()
  if (!normalized) {
    return `<!doctype html><html lang="zh-CN"><head><meta charset="UTF-8"></head><body style="margin:0;padding:24px;font-family:'PingFang SC','Microsoft YaHei',Arial,sans-serif;background:#f8fafc;color:#86909c;">暂无正文内容</body></html>`
  }

  if (looksLikeHtmlDocument(normalized)) {
    return normalized
  }

  if (looksLikeHtmlFragment(normalized)) {
    return `<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    body {
      margin: 0;
      padding: 24px;
      background: #f5f7fa;
      font-family: "PingFang SC", "Microsoft YaHei", Arial, sans-serif;
      color: #1f2329;
    }
    .mail-preview {
      max-width: 680px;
      margin: 0 auto;
      padding: 24px;
      border: 1px solid #dce7ff;
      border-radius: 16px;
      background: #ffffff;
      box-shadow: 0 10px 32px rgba(29, 33, 41, 0.08);
    }
  </style>
</head>
<body>
  <div class="mail-preview">${normalized}</div>
</body>
</html>`
  }

  return `<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin:0;padding:24px;font-family:'PingFang SC','Microsoft YaHei',Arial,sans-serif;background:#f8fafc;color:#1f2329;">
  <pre style="margin:0;white-space:pre-wrap;line-height:1.75;">${escapeHtml(normalized)}</pre>
</body>
</html>`
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
    email: '',
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
      getEmailLogs(params),
      getEmailLogsSummary({
        email: filters.email,
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
    ElMessage.error(error.message || '加载邮件日志失败')
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
.email-logs-page {
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
    min-width: 1440px;
  }

  .recipient-cell {
    display: flex;
    flex-direction: column;
    gap: 4px;
  }

  .recipient-email {
    font-weight: 600;
    color: var(--text-main);
    word-break: break-all;
  }

  .recipient-time {
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

  .content-panel {
    padding: 16px;
    border-radius: $sm-border-radius;
    background: #101828;
    color: #f8fafc;
    line-height: 1.75;
    white-space: pre-wrap;
    word-break: break-word;
  }

  .content-preview-frame {
    width: 100%;
    min-height: 460px;
    border: 1px solid #e5e7eb;
    border-radius: $sm-border-radius;
    background: #f5f7fa;
  }
}

@media (max-width: 1200px) {
  .email-logs-page {
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
  .email-logs-page {
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




