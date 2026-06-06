<template>
  <div class="services-page admin-page">
    <section class="admin-page-head">
      <div class="admin-page-heading">
        <span class="admin-page-kicker">财务</span>
        <h2>服务列表</h2>
        <p>查看所有用户的服务实例</p>
      </div>
    </section>

    <!-- 筛选 + 搜索 -->
    <div class="toolbar-card">
      <el-select
        v-model="filterStatus"
        placeholder="主机状态"
        clearable
        style="width: 140px"
        @change="handleStatusChange"
      >
        <el-option
          v-for="tab in statusTabs"
          :key="tab.value"
          :label="tab.label"
          :value="tab.value"
        />
      </el-select>

      <el-input
        v-model="keyword"
        placeholder="搜索主机ID / 主机IP / 实例ID / 用户名 / 账单号"
        clearable
        style="flex: 1; min-width: 200px"
        @keyup.enter="handleSearch"
        @clear="handleSearch"
      >
        <template #prefix>
          <el-icon><Search /></el-icon>
        </template>
      </el-input>

      <el-button :disabled="!selectedRows.length" @click="openBatchHostnameDialog">
        批量主机名<span v-if="selectedRows.length">({{ selectedRows.length }})</span>
      </el-button>
    </div>

    <!-- 表格 -->
    <div class="table-card">
      <el-table
        ref="tableRef"
        v-loading="loading"
        :data="list"
        stripe
        row-key="id"
        style="width: 100%"
        :header-cell-style="{ background: '#f7f8fa', color: '#1d2129', fontWeight: '600' }"
        :cell-style="{ padding: '14px 0', verticalAlign: 'middle' }"
        :row-style="{ background: '#ffffff' }"
        @selection-change="handleSelectionChange"
      >
        <el-table-column type="selection" width="48" />

        <!-- 服务 -->
        <el-table-column label="服务" min-width="320">
          <template #default="{ row }">
            <div class="service-cell">
              <div class="service-primary">
                <span class="service-id">服务/实例 #{{ row.service_id || row.id || '—' }}</span>
                <span v-if="row.invoice?.id" class="service-pill">账单 #{{ row.invoice.id }}</span>
              </div>
              <span v-if="row.invoice?.invoice_no" class="service-secondary">账单号 {{ row.invoice.invoice_no }}</span>
            </div>
          </template>
        </el-table-column>

        <!-- 主机信息 -->
        <el-table-column label="主机信息" min-width="220">
          <template #default="{ row }">
            <div class="host-cell">
              <span v-if="row.upstream_host_id_text || row.upstream_host_id" class="host-line host-id">
                <span class="meta-label">上游</span>{{ row.upstream_host_id_text || row.upstream_host_id }}
              </span>
              <span v-if="row.host_ips?.length" class="host-line host-ip">
                <span class="meta-label">IP</span>{{ row.host_ips.join(' / ') }}
              </span>
              <span v-if="row.host_username || row.connection?.username" class="host-line">
                <span class="meta-label">登录</span>{{ row.host_username || row.connection.username }}
              </span>
              <span v-if="!hasHostInfo(row)" class="text-muted">—</span>
            </div>
          </template>
        </el-table-column>

        <!-- 用户 -->
        <el-table-column label="用户" min-width="170">
          <template #default="{ row }">
            <div class="user-cell" :class="{ 'user-cell--link': row.user?.id }" @click="goUserDetail(row)">
              <div class="user-primary">
                <span class="user-name">{{ row.user?.username || '—' }}</span>
                <span v-if="row.user?.id" class="user-id">#{{ row.user.id }}</span>
              </div>
              <span v-if="row.user?.email" class="user-email">{{ row.user.email }}</span>
            </div>
          </template>
        </el-table-column>

        <!-- 配置 -->
        <el-table-column label="配置" min-width="160" show-overflow-tooltip>
          <template #default="{ row }">
            <span class="product-name">{{ row.product_display_name || row.product?.display_name || (row.product_id ? `未配置规格 #${row.product_id}` : '—') }}</span>
          </template>
        </el-table-column>

        <!-- 状态 -->
        <el-table-column label="状态" width="100">
          <template #default="{ row }">
            <StatusTag :status-map="SERVICE_STATUS_MAP" :status="row.status">
              {{ row.status_label || statusLabel(row.status) }}
            </StatusTag>
          </template>
        </el-table-column>

        <!-- 计费 -->
        <el-table-column label="计费/金额" width="120">
          <template #default="{ row }">
            <div class="billing-cell">
              <span class="billing-amount">¥{{ row.amount }}</span>
              <span class="billing-cycle">{{ billingCycleLabel(row.billing_cycle) }}</span>
            </div>
          </template>
        </el-table-column>

        <!-- 到期时间 -->
        <el-table-column label="到期时间" width="130">
          <template #default="{ row }">
            <span :class="isExpiringSoon(row.expires_at) ? 'expiring-soon' : ''">
              {{ row.expires_at ? row.expires_at.slice(0, 10) : '—' }}
            </span>
          </template>
        </el-table-column>

        <!-- 开通时间 -->
        <el-table-column label="开通时间" width="120">
          <template #default="{ row }">
            <span class="text-muted">{{ row.created_at ? row.created_at.slice(0, 10) : '—' }}</span>
          </template>
        </el-table-column>

      </el-table>

      <!-- 分页 -->
      <div class="pagination-wrap">
        <el-pagination
          v-model:current-page="page"
          v-model:page-size="pageSize"
          :total="total"
          :page-sizes="[20, 50, 100]"
          layout="total, sizes, prev, pager, next, jumper"
          background
          @size-change="handleSizeChange"
          @current-change="handlePageChange"
        />
      </div>
    </div>

    <el-dialog
      v-model="hostnameDialogVisible"
      :title="hostnameRows.length > 1 ? '批量设置自定义主机名' : '设置自定义主机名'"
      width="920px"
      destroy-on-close
    >
      <el-alert
        type="info"
        :closable="false"
        show-icon
        style="margin-bottom: 16px;"
      >
        <template #title>留空表示清空自定义主机名。设置后前后台优先展示该值，不会再被上游同步结果直接覆盖。</template>
      </el-alert>

      <el-table :data="hostnameRows" border size="small" max-height="420">
        <el-table-column prop="service_id" label="服务ID" width="90" />
        <el-table-column label="服务信息" min-width="240">
          <template #default="{ row }">
            <div class="hostname-service-cell">
              <strong>{{ row.service_name || '—' }}</strong>
              <span>{{ row.product_display_name || row.product_name || '—' }}</span>
              <span>{{ row.user_name || '—' }}</span>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="当前展示" min-width="170">
          <template #default="{ row }">
            <div class="hostname-current-cell">
              <span>{{ row.current_domain || '—' }}</span>
              <span v-if="row.current_custom_hostname" class="hostname-current-cell__custom">
                当前自定义：{{ row.current_custom_hostname }}
              </span>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="目标主机名" min-width="240">
          <template #default="{ row }">
            <el-input
              v-model="row.hostname"
              maxlength="200"
              clearable
              placeholder="留空则清空自定义主机名"
            />
          </template>
        </el-table-column>
      </el-table>

      <template #footer>
        <el-button @click="hostnameDialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="hostnameSubmitting" @click="handleSubmitHostnames">保存</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { nextTick, onMounted, ref } from 'vue'
import { ElMessage } from 'element-plus'
import { useRouter } from 'vue-router'
import { Search } from '@element-plus/icons-vue'
import serviceApi from '@/api/service'
import StatusTag from '@shared/components/StatusTag.vue'
import {
  SERVICE_STATUS_MAP,
  getStatusConfig,
  toSelectOptions,
} from '@shared/statusConfig'

const router = useRouter()

const statusTabs = [
  { label: '全部', value: '' },
  ...toSelectOptions(SERVICE_STATUS_MAP, false).map(o => ({ ...o, value: String(o.value) })),
]

const list        = ref([])
const total       = ref(0)
const page        = ref(1)
const pageSize    = ref(20)
const loading     = ref(false)
const tableRef    = ref()
const keyword     = ref('')
const filterStatus = ref('')
const selectedRows = ref([])
const hostnameDialogVisible = ref(false)
const hostnameSubmitting = ref(false)
const hostnameRows = ref([])

async function loadList() {
  loading.value = true
  try {
    const params = {
      page: page.value,
      page_size: pageSize.value,
    }
    if (keyword.value.trim()) params.keyword = keyword.value.trim()
    if (filterStatus.value !== '') params.status = filterStatus.value

    const res = await serviceApi.list(params)
    list.value  = res.data.list  || []
    total.value = res.data.total || 0
    selectedRows.value = []
    await nextTick()
    tableRef.value?.clearSelection?.()
  } finally {
    loading.value = false
  }
}

function handleSearch() {
  page.value = 1
  loadList()
}

function handleStatusChange() {
  page.value = 1
  loadList()
}

function handlePageChange(val) {
  page.value = val
  loadList()
}

function handleSizeChange(val) {
  pageSize.value = val
  page.value = 1
  loadList()
}

function goUserDetail(row) {
  if (row.user?.id) {
    router.push({ name: 'AdminUserDetail', params: { id: row.user.id } })
  }
}

function handleSelectionChange(rows) {
  selectedRows.value = Array.isArray(rows) ? rows : []
}

function buildHostnameRows(rows) {
  return rows.map((row) => ({
    service_id: Number(row?.id || 0),
    service_name: row?.name || row?.product_display_name || row?.product?.display_name || '',
    product_name: row?.product_display_name || row?.product?.display_name || (row?.product_id ? `未配置规格 #${row.product_id}` : ''),
    user_name: row?.user?.username || row?.user?.email || row?.user?.phone || '',
    current_domain: row?.domain || '',
    current_custom_hostname: row?.custom_hostname || '',
    hostname: row?.custom_hostname || '',
  }))
}

function openBatchHostnameDialog() {
  if (!selectedRows.value.length) {
    ElMessage.warning('请先选择需要批量设置的服务')
    return
  }
  hostnameRows.value = buildHostnameRows(selectedRows.value)
  hostnameDialogVisible.value = true
}

async function handleSubmitHostnames() {
  if (!hostnameRows.value.length) return

  hostnameSubmitting.value = true
  try {
    const res = await serviceApi.batchUpdateCustomHostnames({
      items: hostnameRows.value.map((row) => ({
        service_id: Number(row.service_id || 0),
        hostname: row.hostname || '',
      })),
    })

    hostnameDialogVisible.value = false
    ElMessage.success(res.message || '自定义主机名已更新')
    await loadList()
  } finally {
    hostnameSubmitting.value = false
  }
}

function statusLabel(status) {
  return getStatusConfig(SERVICE_STATUS_MAP, Number(status)).label
}

function billingCycleLabel(cycle) {
  const map = {
    monthly: '月付',
    quarterly: '季付',
    biannually: '半年付',
    annually: '年付',
    biennially: '两年付',
    triennially: '三年付',
    onetime: '一次性',
  }
  return map[cycle] || cycle || '—'
}

function isExpiringSoon(expiresAt) {
  if (!expiresAt) return false
  const diff = new Date(expiresAt) - Date.now()
  return diff > 0 && diff < 7 * 24 * 3600 * 1000
}

function hasHostInfo(row) {
  return Boolean(
    row.upstream_host_id_text ||
    row.upstream_host_id ||
    row.host_ips?.length ||
    row.host_username ||
    row.connection?.username
  )
}

onMounted(() => loadList())
</script>

<style scoped lang="scss">
.services-page {
  padding: 0;
}

.toolbar-card {
  margin: 16px 24px 0;
  background: #ffffff;
  border: 1px solid #e5e6eb;
  border-radius: 8px;
  padding: 12px 16px;
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}

@include tablet-and-below {
  .toolbar-card {
    flex-wrap: wrap;
  }

  .toolbar-card .el-select {
    width: 100% !important;
  }

  .toolbar-card .el-input {
    min-width: 100% !important;
  }
}

.table-card {
  margin: 12px 24px 24px;
  background: #ffffff;
  border: 1px solid #e5e6eb;
  border-radius: 8px;
  overflow: hidden;
}

.service-cell {
  display: flex;
  flex-direction: column;
  gap: 6px;
  min-width: 0;
}

.service-primary {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
  min-width: 0;
}

.service-id {
  color: #1d2129;
  font-size: 13px;
  font-weight: 600;
  line-height: 1.35;
}

.service-pill {
  display: inline-flex;
  align-items: center;
  height: 22px;
  padding: 0 8px;
  border-radius: 6px;
  background: #f2f3f5;
  color: #4e5969;
  font-size: 12px;
  line-height: 22px;
}

.service-secondary {
  color: #4e5969;
  font-size: 12px;
  line-height: 1.4;
  word-break: break-all;
}

.row-actions {
  display: inline-flex;
  align-items: center;
  gap: 8px;
}

.hostname-service-cell {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.hostname-service-cell strong {
  color: #1d2129;
  font-size: 13px;
  line-height: 1.5;
}

.hostname-service-cell span {
  color: #86909c;
  font-size: 12px;
}

.hostname-current-cell {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.hostname-current-cell__custom {
  color: #e6a23c;
  font-size: 12px;
}

.host-cell {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
  font-size: 12px;
  min-width: 0;
}

.host-line {
  display: inline-flex;
  align-items: center;
  min-width: 0;
  color: #1d2129;
  line-height: 1.4;
  word-break: break-all;
}

.meta-label {
  display: inline-flex;
  align-items: center;
  flex-shrink: 0;
  margin-right: 5px;
  color: #86909c;
}

.host-id {
  color: #0052d9;
  font-weight: 600;
}

.host-ip {
  color: #1d2129;
  font-family: monospace;
}

.user-cell {
  display: flex;
  flex-direction: column;
  gap: 5px;
  min-width: 0;
}

.user-cell--link {
  cursor: pointer;

  &:hover .user-name {
    color: $color-primary;
  }
}

.user-name {
  font-size: 13px;
  font-weight: 600;
  color: #1d2129;
  transition: color 0.15s ease-out;
}

.user-primary {
  display: flex;
  align-items: center;
  gap: 6px;
  min-width: 0;
}

.user-email {
  font-size: 12px;
  color: #86909c;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.user-id {
  font-size: 12px;
  color: #86909c;
}

.product-name {
  display: inline-flex;
  max-width: 100%;
  font-size: 13px;
  font-weight: 500;
  color: #1d2129;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.billing-cell {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.billing-cycle {
  font-size: 12px;
  color: #86909c;
}

.billing-amount {
  font-size: 13px;
  font-weight: 600;
  color: #1d2129;
  line-height: 1.25;
}

.expiring-soon {
  color: #f77234;
  font-weight: 600;
}

.text-muted {
  color: #86909c;
  font-size: 12px;
}

.pagination-wrap {
  display: flex;
  justify-content: flex-end;
  padding: 14px 16px;
  border-top: 1px solid #f2f3f5;
}
</style>
