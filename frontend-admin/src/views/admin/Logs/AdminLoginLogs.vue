<template>
  <div class="admin-log-page">
    <section class="page-header">
      <div class="page-title-group">
        <h2>管理员登录日志</h2>
        <p>记录管理员账号最近登录情况，支持区分操作日志来源与快照回退来源。</p>
      </div>
    </section>

    <section class="summary-strip">
      <article class="summary-item">
        <strong>{{ pagination.total }}</strong>
        <span>登录记录数</span>
      </article>
      <article class="summary-item">
        <strong>{{ sourceModeLabel }}</strong>
        <span>当前数据来源</span>
      </article>
      <article class="summary-item">
        <strong>{{ tableData.length }}</strong>
        <span>当前页记录</span>
      </article>
    </section>

    <el-card shadow="never" class="panel-card">
      <el-form :model="searchForm" class="filter-form" @submit.prevent>
        <el-form-item label="关键词">
          <el-input
            v-model="searchForm.keyword"
            placeholder="账号、昵称、角色或 IP"
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
            <h3>登录明细</h3>
            <p>共 {{ pagination.total }} 条记录，支持查看角色、IP 与回退来源说明。</p>
          </div>
        </div>
      </template>

      <p class="mobile-scroll-hint">左右滑动可查看完整表格</p>

      <div class="table-scroll-shell">
        <el-table v-loading="loading" :data="tableData" border row-key="id" class="admin-log-table">
          <el-table-column label="账号信息" min-width="240">
            <template #default="{ row }">
              <div class="account-cell">
                <strong>{{ row.admin_username || '-' }}</strong>
                <span class="dim-text">{{ row.admin_nickname || row.actor_name || '未设置昵称' }}</span>
              </div>
            </template>
          </el-table-column>

          <el-table-column prop="role_name" label="角色" min-width="160" />
          <el-table-column prop="ip_address" label="登录 IP" min-width="160" />

          <el-table-column label="登录时间" width="180">
            <template #default="{ row }">
              {{ formatLogDate(row.created_at) }}
            </template>
          </el-table-column>

          <el-table-column label="来源" width="140" align="center">
            <template #default="{ row }">
              <el-tag :type="row.source === 'operation_log' ? 'success' : 'warning'" effect="light">
                {{ getSourceLabel(row.source) }}
              </el-tag>
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
            <el-empty description="暂无管理员登录日志" />
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

    <el-drawer v-model="detailVisible" title="管理员登录详情" size="640px">
      <template v-if="currentLog">
        <div class="drawer-grid">
          <div class="drawer-item">
            <span class="drawer-label">登录账号</span>
            <strong>{{ currentLog.admin_username || '-' }}</strong>
          </div>
          <div class="drawer-item">
            <span class="drawer-label">昵称</span>
            <strong>{{ currentLog.admin_nickname || currentLog.actor_name || '-' }}</strong>
          </div>
          <div class="drawer-item">
            <span class="drawer-label">角色</span>
            <strong>{{ currentLog.role_name || '-' }}</strong>
          </div>
          <div class="drawer-item">
            <span class="drawer-label">登录时间</span>
            <strong>{{ formatLogDate(currentLog.created_at) }}</strong>
          </div>
          <div class="drawer-item">
            <span class="drawer-label">IP 地址</span>
            <strong>{{ currentLog.ip_address || '-' }}</strong>
          </div>
          <div class="drawer-item">
            <span class="drawer-label">数据来源</span>
            <strong>{{ getSourceLabel(currentLog.source) }}</strong>
          </div>
        </div>

        <div class="json-title">上下文详情</div>
        <div class="json-block">{{ formatJsonBlock(currentLog.detail) }}</div>
      </template>
    </el-drawer>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { ElMessage } from 'element-plus'
import { RefreshRight, Search, View } from '@element-plus/icons-vue'
import { getAdminLoginLogs } from '@/api/admin'
import {
  buildDateRangeParams,
  compactParams,
  formatJsonBlock,
  formatLogDate,
} from './logUtils'
import { usePagedQuery } from './usePagedQuery'
import { useResponsive } from '@/composables/useResponsive'

const { isMobile } = useResponsive()
const detailVisible = ref(false)
const currentLog = ref(null)
const tableData = ref([])
const sourceMode = ref('')

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
    dateRange: [],
  }),
  initialPerPage: 15,
  query: async ({ filters, pagination }) => {
    const res = await getAdminLoginLogs(compactParams({
      keyword: filters.keyword,
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
        sourceMode: res.data?.summary?.mode || '',
      },
    }
  },
  onSuccess: ({ rows, extra }) => {
    tableData.value = rows || []
    sourceMode.value = extra?.sourceMode || ''
  },
  onError: (error) => {
    ElMessage.error(error.message || '加载管理员登录日志失败')
  },
})

const sourceModeLabel = computed(() => getSourceLabel(sourceMode.value))

function getSourceLabel(source) {
  return ({
    operation_log: '操作日志',
    admin_snapshot: '账号快照',
  })[String(source || '').toLowerCase()] || '未知来源'
}

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

.account-cell {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.account-cell strong {
  color: $text-color-primary;
}

.json-title {
  margin-top: 16px;
  color: $text-color-primary;
  font-size: 13px;
  font-weight: 600;
}
</style>





