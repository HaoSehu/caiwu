<template>
  <div class="admin-page page-container">
    <!-- 页头区 -->
    <header v-if="showHead" class="admin-page-head">
      <div class="admin-page-heading">
        <span v-if="kicker" class="admin-page-kicker">{{ kicker }}</span>
        <h2>{{ title }}</h2>
        <p v-if="subtitle">{{ subtitle }}</p>
      </div>
      <div class="admin-page-actions page-actions">
        <slot name="actions">
          <el-button v-if="refreshable" @click="handleRefresh">
            <el-icon class="refresh-icon"><Refresh /></el-icon>
            刷新
          </el-button>
          <slot name="extra-actions"></slot>
        </slot>
      </div>
    </header>

    <!-- 筛选/搜索栏 -->
    <section v-if="$slots.filter || hasSearch" class="filter-panel">
      <div class="filter-content">
        <slot name="filter">
          <div v-for="item in searchFields" :key="item.prop" class="search-field">
            <el-select
              v-if="item.type === 'select'"
              v-model="filters[item.prop]"
              :placeholder="item.placeholder"
              clearable
              :style="{ width: getSelectWidth(item.width) }"
              @change="handleSearch"
            >
              <el-option
                v-for="option in item.options"
                :key="option.value"
                :label="option.label"
                :value="option.value"
              />
            </el-select>
            <el-date-picker
              v-else-if="item.type === 'date'"
              v-model="filters[item.prop]"
              :type="item.dateType || 'date'"
              :placeholder="item.placeholder"
              clearable
              :style="{ width: getSelectWidth(item.width) }"
              @change="handleSearch"
            />
            <el-input
              v-else
              v-model="filters[item.prop]"
              :placeholder="item.placeholder"
              clearable
              :prefix-icon="item.icon || Search"
              :style="{ width: getSelectWidth(item.width) }"
              @keyup.enter="handleSearch"
            />
          </div>
        </slot>
      </div>
      <div class="filter-actions">
        <slot name="filter-actions"></slot>
      </div>
    </section>

    <!-- 主内容区 -->
    <main class="page-main">
      <slot></slot>
    </main>

    <!-- 分页区 -->
    <div v-if="showPagination && total > 0" class="page-pagination">
      <slot name="pagination">
        <el-pagination
          v-model:current-page="pagination.page"
          v-model:page-size="pagination.pageSize"
          :total="total"
          :page-sizes="[10, 20, 50, 100]"
          layout="total, sizes, prev, pager, next, jumper"
          @size-change="handleSizeChange"
          @current-change="handleCurrentChange"
        />
      </slot>
    </div>
  </div>
</template>

<script setup>
import { reactive, ref, watch } from 'vue'
import { Refresh, Search } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'

const emit = defineEmits(['refresh', 'search', 'reset', 'page-change', 'size-change'])

// Props
const props = defineProps({
  title: {
    type: String,
    required: true,
  },
  subtitle: {
    type: String,
    default: '',
  },
  kicker: {
    type: String,
    default: '',
  },
  showHead: {
    type: Boolean,
    default: true,
  },
  showPagination: {
    type: Boolean,
    default: true,
  },
  searchFields: {
    type: Array,
    default: () => [],
  },
  refreshable: {
    type: Boolean,
    default: true,
  },
})

// Filters
const filters = reactive({})

// Pagination
const pagination = reactive({
  page: 1,
  pageSize: 20,
})

const total = ref(0)

// Methods
function handleRefresh() {
  emit('refresh')
}

function handleSearch() {
  pagination.page = 1
  emit('search', { filters: filters, pagination: pagination })
}

function handleReset() {
  Object.keys(filters).forEach((key) => {
    filters[key] = undefined
  })
  pagination.page = 1
  emit('reset', { filters: {}, pagination: pagination })
}

function handleSizeChange(size) {
  pagination.pageSize = size
  pagination.page = 1
  emit('size-change', { size, pagination: pagination })
}

function handleCurrentChange(page) {
  pagination.page = page
  emit('page-change', { page, pagination: pagination })
}

function getSelectWidth(width) {
  return width || '180px'
}

// Watchers
defineExpose({
  filters,
  pagination,
  total,
  refresh: handleRefresh,
  reset: handleReset,
  setFilters: (newFilters) => {
    Object.assign(filters, newFilters)
  },
})
</script>

<style lang="scss" scoped>
.admin-page {
  display: flex;
  flex-direction: column;
  gap: 16px;
  min-width: 0;
}

.admin-page-head {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  gap: 16px;
  padding: 0 0 16px;
  border-bottom: 1px solid $divider-color;
}

.admin-page-heading {
  max-width: 760px;
  min-width: 0;
}

.admin-page-kicker {
  display: none;
}

.admin-page-heading h2 {
  margin-top: 0;
  color: $text-color-primary;
  font-size: $font-size-h1;
  font-weight: 600;
  line-height: 1.25;
  letter-spacing: -0.3px;
}

.admin-page-heading p {
  margin-top: 6px;
  color: $text-color-secondary;
  font-size: 14px;
  line-height: 1.7;
}

.page-actions,
.filter-actions {
  display: flex;
  flex-shrink: 0;
  flex-wrap: wrap;
  align-items: center;
  gap: 12px;
}

.filter-panel {
  padding: 16px;
  border: 1px solid $border-color;
  border-radius: $base-border-radius;
  background: $bg-color-card;
  box-shadow: $shadow-xs;
}

.filter-content {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  gap: 12px;
  margin-bottom: 12px;
}

.filter-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  margin-left: auto;
}

.page-main {
  flex: 1;
  min-width: 0;
}

.page-pagination {
  display: flex;
  justify-content: flex-end;
  padding-top: 12px;
}

.page-container {
  padding: 0;
}

// 移动端
@include tablet-and-below {
  .admin-page-school {
    gap: 12px;
  }

  .admin-page-head {
    flex-direction: column;
    align-items: flex-start;
  }

  .page-actions,
  .filter-actions {
    width: 100%;
  }

  .page-actions > .el-button,
  .filter-actions > .el-button {
    flex: 1 1 0;
    min-width: 0;
  }

  .filter-content {
    flex-direction: column;
    align-items: stretch;
  }

  .search-field .el-select,
  .search-field .el-date-picker,
  .search-field .el-input {
    width: 100% !important;
    flex: 1 1 auto;
  }
}
</style>
