<template>
  <div class="page-container admin-page tickets-page">
    <section class="admin-page-head">
      <div class="admin-page-heading">
        <span class="admin-page-kicker">服务支持</span>
        <h2>工单处理</h2>
        <p>集中查看客户提交的工单，进入交流页面完成指派、回复与关闭操作。</p>
      </div>
    </section>

    <section class="filter-panel">
      <div class="search-bar tickets-search-bar">
        <el-input
          v-model="filters.keyword"
          placeholder="搜索工单标题或 ID"
          clearable
          style="width: 260px"
          @keyup.enter="handleSearch"
        >
          <template #prefix>
            <el-icon><Search /></el-icon>
          </template>
        </el-input>

        <el-select v-model="filters.status" placeholder="工单状态" clearable style="width: 140px">
          <el-option
            v-for="option in statusOptions"
            :key="option.value"
            :label="option.label"
            :value="option.value"
          />
        </el-select>

        <el-select v-model="filters.priority" placeholder="优先级" clearable style="width: 120px">
          <el-option
            v-for="option in priorityOptions"
            :key="option.value"
            :label="option.label"
            :value="option.value"
          />
        </el-select>

        <el-select v-model="filters.department" placeholder="工单分类" clearable style="width: 140px">
          <el-option
            v-for="option in departmentOptions"
            :key="option.value"
            :label="option.label"
            :value="option.value"
          />
        </el-select>

        <el-button type="primary" :icon="Search" @click="handleSearch">搜索</el-button>
        <el-button :icon="Refresh" @click="resetFilters">重置</el-button>
      </div>
    </section>

    <el-card shadow="never" class="tickets-table-card">
      <el-table :data="list" v-loading="loading" stripe row-key="id">
        <el-table-column prop="id" label="ID" width="80" />

        <el-table-column label="工单标题" min-width="260">
          <template #default="{ row }">
            <button type="button" class="subject-link" @click="openDetail(row.id)">
              {{ row.subject || '-' }}
            </button>
          </template>
        </el-table-column>

        <el-table-column label="用户" min-width="180">
          <template #default="{ row }">
            <button type="button" class="user-link" @click="goUserDetail(row.user?.id)">
              {{ row.user?.nickname || row.user?.email || `用户 #${row.user_id}` }}
            </button>
            <div class="sub-copy">{{ row.user?.email || '--' }}</div>
          </template>
        </el-table-column>

        <el-table-column label="分类" width="110">
          <template #default="{ row }">
            <el-tag size="small" effect="plain">{{ departmentLabel(row.department) }}</el-tag>
          </template>
        </el-table-column>

        <el-table-column label="优先级" width="100">
          <template #default="{ row }">
            <el-tag size="small" effect="plain" :type="priorityType(row.priority)">
              {{ priorityLabel(row.priority) }}
            </el-tag>
          </template>
        </el-table-column>

        <el-table-column label="状态" width="130">
          <template #default="{ row }">
            <el-tag size="small" :type="statusType(row.status)">
              {{ statusLabel(row.status) }}
            </el-tag>
            <div v-if="row.close_reason_label" class="close-reason-label">{{ row.close_reason_label }}</div>
          </template>
        </el-table-column>

        <el-table-column label="处理人" min-width="140">
          <template #default="{ row }">
            {{ row.assignee?.nickname || row.assignee?.username || '未指派' }}
          </template>
        </el-table-column>

        <el-table-column label="最后更新" min-width="160">
          <template #default="{ row }">{{ formatDateTime(row.updated_at) }}</template>
        </el-table-column>
      </el-table>

      <div class="table-pagination">
        <el-pagination
          v-model:current-page="page"
          v-model:page-size="pageSize"
          :total="total"
          :page-sizes="[20, 50, 100]"
          layout="total, sizes, prev, pager, next"
          @size-change="loadList"
          @current-change="loadList"
        />
      </div>
    </el-card>
  </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { Refresh, Search } from '@element-plus/icons-vue'
import adminApi from '@/api/admin'
import { formatDateTime } from '@/utils/datetime'
import { TICKET_STATUS_MAP, getStatusLabel, getStatusTagType, resolveElTagType, toSelectOptions } from '@shared/statusConfig'

const router = useRouter()
const DEFAULT_STATUS_FILTER = 'ongoing'

const statusOptions = [
  { label: '进行中', value: DEFAULT_STATUS_FILTER },
  ...toSelectOptions(TICKET_STATUS_MAP),
]

const priorityOptions = [
  { label: '低', value: 1 },
  { label: '中', value: 2 },
  { label: '高', value: 3 },
  { label: '紧急', value: 4 },
]

const departmentOptions = [
  { label: '销售', value: 'sales' },
  { label: '技术支持', value: 'support' },
  { label: '财务', value: 'billing' },
  { label: '投诉', value: 'abuse' },
]

const loading = ref(false)
const list = ref([])
const total = ref(0)
const page = ref(1)
const pageSize = ref(20)
const filters = reactive({
  keyword: '',
  status: DEFAULT_STATUS_FILTER,
  priority: '',
  department: '',
})

function departmentLabel(value) {
  return departmentOptions.find((item) => item.value === value)?.label || value || '--'
}

function priorityLabel(value) {
  return priorityOptions.find((item) => item.value === Number(value))?.label || '--'
}

function priorityType(value) {
  return ({
    1: 'info',
    2: 'success',
    3: 'warning',
    4: 'danger',
  })[Number(value)] || 'info'
}

function statusLabel(value) {
  return getStatusLabel(TICKET_STATUS_MAP, Number(value))
}

function statusType(value) {
  return resolveElTagType(getStatusTagType(TICKET_STATUS_MAP, Number(value)))
}

async function loadList() {
  loading.value = true
  try {
    const params = {
      ...filters,
      page: page.value,
      page_size: pageSize.value,
    }
    const res = await adminApi.tickets.list(params)
    list.value = res.data?.list || []
    total.value = res.data?.total || 0
    page.value = res.data?.page || page.value
    pageSize.value = res.data?.page_size || pageSize.value
  } catch {
    list.value = []
    total.value = 0
  } finally {
    loading.value = false
  }
}

function handleSearch() {
  page.value = 1
  loadList()
}

function resetFilters() {
  filters.keyword = ''
  filters.status = DEFAULT_STATUS_FILTER
  filters.priority = ''
  filters.department = ''
  page.value = 1
  loadList()
}

function openDetail(id) {
  router.push(`/admin/ticket-conversations/${id}`)
}

function goUserDetail(userId) {
  if (!userId) return
  router.push(`/admin/users/${userId}`)
}

onMounted(loadList)
</script>

<style scoped lang="scss">
.filter-panel {
  padding: 16px;
  border: 1px solid $border-color;
  border-radius: $base-border-radius;
  background: $bg-color-card;
}

.tickets-search-bar {
  align-items: center;
  margin: 0;
}

.tickets-table-card {
  overflow: hidden;
}

.subject-link,
.user-link {
  border: none;
  padding: 0;
  background: transparent;
  color: $color-primary;
  font: inherit;
  cursor: pointer;
}

.subject-link {
  text-align: left;
  font-weight: 600;
}

.subject-link:hover,
.user-link:hover {
  text-decoration: underline;
}

.sub-copy {
  margin-top: 4px;
  color: $text-color-placeholder;
  font-size: 12px;
}

.close-reason-label {
  color: $text-color-secondary;
  font-size: 12px;
}

.table-pagination {
  display: flex;
  justify-content: flex-end;
  margin-top: 16px;
}
</style>
