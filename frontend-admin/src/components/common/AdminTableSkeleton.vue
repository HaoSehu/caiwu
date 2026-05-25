<template>
  <div class="admin-skeleton-card">
    <div v-if="showFilter" class="skeleton-filter">
      <el-skeleton :rows="0" animated>
        <template #template>
          <div class="filter-row">
            <el-skeleton-item v-for="i in filterCount" :key="i" variant="rect" class="filter-item" />
          </div>
        </template>
      </el-skeleton>
    </div>

    <div class="skeleton-table">
      <el-skeleton :rows="rows" animated>
        <template #template>
          <!-- 表头 -->
          <div class="skeleton-header">
            <el-skeleton-item v-for="i in columns" :key="i" variant="text" class="header-cell" />
          </div>
          <!-- 表格行 -->
          <div v-for="row in rows" :key="row" class="skeleton-row">
            <el-skeleton-item v-for="i in columns" :key="i" variant="text" class="row-cell" />
          </div>
        </template>
      </el-skeleton>
    </div>

    <!-- 分页骨架 -->
    <div v-if="showPagination" class="skeleton-pagination">
      <el-skeleton :rows="0" animated>
        <template #template>
          <el-skeleton-item variant="rect" class="pagination-placeholder" />
        </template>
      </el-skeleton>
    </div>
  </div>
</template>

<script setup>
const props = defineProps({
  rows: {
    type: Number,
    default: 5,
  },
  columns: {
    type: Number,
    default: 6,
  },
  showFilter: {
    type: Boolean,
    default: true,
  },
  filterCount: {
    type: Number,
    default: 3,
  },
  showPagination: {
    type: Boolean,
    default: true,
  },
})
</script>

<style lang="scss" scoped>
.admin-skeleton-card {
  padding: 16px;
  border: 1px solid $border-color;
  border-radius: $base-border-radius;
  background: $bg-color-card;
}

.skeleton-filter {
  margin-bottom: 20px;
  padding-bottom: 16px;
  border-bottom: 1px solid $divider-color;
}

.filter-row {
  display: flex;
  gap: 12px;
  flex-wrap: wrap;
}

.filter-item {
  width: 160px;
  height: 32px;
  border-radius: $sm-border-radius;
}

.skeleton-table {
  margin-bottom: 16px;
}

.skeleton-header {
  display: flex;
  gap: 16px;
  padding: 12px 0;
  margin-bottom: 8px;
  border-bottom: 1px solid $divider-color;
}

.header-cell {
  flex: 1;
  height: 16px;
}

.skeleton-row {
  display: flex;
  gap: 16px;
  padding: 12px 0;
}

.row-cell {
  flex: 1;
  height: 14px;
}

.skeleton-pagination {
  display: flex;
  justify-content: flex-end;
}

.pagination-placeholder {
  width: 300px;
  height: 32px;
  border-radius: $sm-border-radius;
}

// 移动端
@include tablet-and-below {
  .filter-row {
    flex-direction: column;
  }

  .filter-item {
    width: 100%;
  }

  .skeleton-header,
  .skeleton-row {
    gap: 8px;
  }
}
</style>
