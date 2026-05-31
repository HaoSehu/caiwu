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
          class="tickets-search-input"
          @keyup.enter="handleSearch"
        >
          <template #prefix>
            <el-icon><Search /></el-icon>
          </template>
        </el-input>

        <el-select v-model="filters.status" placeholder="工单状态" clearable class="tickets-search-select">
          <el-option
            v-for="option in statusOptions"
            :key="option.value"
            :label="option.label"
            :value="option.value"
          />
        </el-select>

        <el-select v-model="filters.priority" placeholder="优先级" clearable class="tickets-search-select tickets-search-select--sm">
          <el-option
            v-for="option in priorityOptions"
            :key="option.value"
            :label="option.label"
            :value="option.value"
          />
        </el-select>

        <el-select v-model="filters.department" placeholder="工单分类" clearable class="tickets-search-select">
          <el-option
            v-for="option in departmentOptions"
            :key="option.value"
            :label="option.label"
            :value="option.value"
          />
        </el-select>
      </div>
    </section>

    <section class="ticket-card-list" v-loading="loading">
      <div v-if="list.length" class="ticket-card-grid">
        <article
          v-for="row in list"
          :key="row.id"
          class="ticket-card"
          @click="openDetail(row.id)"
        >
          <div class="ticket-card__head">
            <span class="ticket-card__id">#{{ row.id }}</span>
            <el-tag size="small" :type="statusType(row.status)" effect="light">
              {{ statusLabel(row.status) }}
            </el-tag>
          </div>

          <div class="ticket-card__title">{{ row.subject || '-' }}</div>

          <div class="ticket-card__meta">
            <span class="ticket-card__user">
              <el-icon><User /></el-icon>
              <button type="button" class="user-link" @click.stop="goUserDetail(row.user?.id)">
                {{ row.user?.nickname || row.user?.email || `用户 #${row.user_id}` }}
              </button>
            </span>
            <span class="ticket-card__assignee">
              处理人：{{ row.assignee?.nickname || row.assignee?.username || '未指派' }}
            </span>
          </div>

          <div class="ticket-card__foot">
            <el-tag size="small" effect="plain" :type="priorityType(row.priority)">
              {{ priorityLabel(row.priority) }}
            </el-tag>
            <el-tag size="small" effect="plain">{{ departmentLabel(row.department) }}</el-tag>
            <span class="ticket-card__time">{{ formatDateTime(row.updated_at) }}</span>
          </div>
        </article>
      </div>

      <el-empty v-if="!loading && !list.length" description="暂无工单记录" />
    </section>

    <div v-if="total > 0" class="ticket-pagination">
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
  </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { Search, User } from '@element-plus/icons-vue'
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

  .tickets-search-input {
    flex: 1 1 220px;
    min-width: 160px;
  }

  .tickets-search-select {
    flex: 0 1 140px;
    min-width: 110px;

    &--sm {
      flex: 0 1 110px;
      min-width: 90px;
    }
  }

  .el-button {
    flex-shrink: 0;
    white-space: nowrap;
  }
}

@include tablet-and-below {
  .tickets-search-bar {
    display: grid !important;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 8px;
    align-items: stretch;

    .tickets-search-input {
      grid-column: 1 / -1;
      min-width: 0;
    }

    .tickets-search-select {
      min-width: 0;
    }

    .el-button {
      grid-column: span 1;
      margin-left: 0 !important;
    }
  }
}

/* ── 卡片列表 ── */
.ticket-card-list {
  min-height: 240px;
}

.ticket-card-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
  gap: 14px;
}

.ticket-card {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding: 16px 18px;
  border: 1px solid $border-color;
  border-radius: $base-border-radius;
  background: $bg-color-card;
  box-shadow: $shadow-sm;
  cursor: pointer;
  transition: border-color 0.15s ease, box-shadow 0.15s ease, transform 0.15s ease;

  &:hover {
    border-color: rgba($color-primary, 0.24);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.06);
    transform: translateY(-1px);
  }
}

.ticket-card__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
}

.ticket-card__id {
  color: $text-color-placeholder;
  font-size: $font-size-sm;
  font-weight: 600;
}

.ticket-card__title {
  color: $text-color-primary;
  font-size: $font-size-base;
  font-weight: 600;
  line-height: 1.4;
  word-break: break-all;
}

.ticket-card__meta {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.ticket-card__user {
  display: flex;
  align-items: center;
  gap: 4px;
  color: $text-color-secondary;
  font-size: $font-size-sm;

  .el-icon {
    flex-shrink: 0;
  }
}

.ticket-card__assignee {
  color: $text-color-secondary;
  font-size: $font-size-sm;
}

.ticket-card__foot {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.ticket-card__time {
  margin-left: auto;
  color: $text-color-placeholder;
  font-size: $font-size-sm;
}

.user-link {
  border: none;
  padding: 0;
  background: transparent;
  color: $color-primary;
  font: inherit;
  cursor: pointer;

  &:hover {
    text-decoration: underline;
  }
}

.ticket-pagination {
  display: flex;
  justify-content: flex-end;
  margin-top: 16px;
}

@media (max-width: 767px) {
  .ticket-card-grid {
    grid-template-columns: minmax(0, 1fr);
  }
}
</style>
