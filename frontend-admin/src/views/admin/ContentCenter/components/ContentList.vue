<template>
  <el-card shadow="never" class="content-table-card">
    <template #header>
      <div class="panel-header">
        <div class="panel-header-meta">
          <strong>{{ pageTitle }}列表</strong>
          <span>共 {{ total }} 条，按置顶、排序值和发布时间展示</span>
        </div>
      </div>
    </template>

    <el-table :data="list" v-loading="loading" stripe row-key="id">
      <template #empty>
        <div class="table-empty">
          <strong>暂无{{ currentArticleLabel }}</strong>
          <p>当前筛选条件下没有数据，可以先新增内容或调整筛选条件。</p>
          <div class="table-empty-actions">
            <el-button type="primary" size="small" @click="emit('create')">新增{{ currentArticleLabel }}</el-button>
            <el-button size="small" @click="emit('reset-filters')">清空筛选</el-button>
          </div>
        </div>
      </template>

      <el-table-column prop="id" label="ID" width="72" />

      <el-table-column label="标题" min-width="320">
        <template #default="{ row }">
          <button type="button" class="title-button" @click="emit('edit', row.id)">
            {{ row.title }}
          </button>
          <p class="title-summary">{{ row.summary || row.excerpt || '暂无摘要' }}</p>
        </template>
      </el-table-column>

      <el-table-column label="分类" min-width="140">
        <template #default="{ row }">
          <div class="category-cell">
            <span>{{ row.category_name || '未分类' }}</span>
            <small>{{ row.slug || '--' }}</small>
          </div>
        </template>
      </el-table-column>

      <el-table-column label="状态" width="110">
        <template #default="{ row }">
          <StatusTag :status-map="CONTENT_STATUS_MAP" :status="row.status">
            {{ row.status_label || statusLabel(row.status) }}
          </StatusTag>
        </template>
      </el-table-column>

      <el-table-column label="属性" min-width="160">
        <template #default="{ row }">
          <div class="flag-tags">
            <el-tag v-if="Number(row.is_pinned) === 1" size="small" type="danger" effect="plain">置顶</el-tag>
            <el-tag v-if="Number(row.is_recommended) === 1" size="small" type="success" effect="plain">推荐</el-tag>
            <span v-if="Number(row.is_pinned) !== 1 && Number(row.is_recommended) !== 1" class="muted-text">普通</span>
          </div>
        </template>
      </el-table-column>

      <el-table-column label="发布 / 浏览" min-width="170">
        <template #default="{ row }">
          <div class="date-cell">
            <span>{{ formatDateTime(row.publish_at || row.created_at) }}</span>
            <small>浏览 {{ row.view_count || 0 }}</small>
          </div>
        </template>
      </el-table-column>

      <el-table-column label="更新信息" min-width="180">
        <template #default="{ row }">
          <div class="date-cell">
            <span>{{ formatDateTime(row.updated_at) }}</span>
            <small>{{ row.operator || row.updater?.nickname || row.updater?.username || '系统' }}</small>
          </div>
        </template>
      </el-table-column>

      <el-table-column label="操作" :width="isMobile ? 60 : 140" fixed="right">
        <template #default="{ row }">
          <div v-if="!isMobile" class="action-inline">
            <el-button size="small" text type="primary" @click="emit('edit', row.id)">编辑</el-button>
            <el-button size="small" text type="danger" @click="emit('delete', row)">删除</el-button>
          </div>
          <el-dropdown v-else trigger="click" @command="(cmd) => handleContentAction(cmd, row)">
            <span class="action-link">···</span>
            <template #dropdown>
              <el-dropdown-menu>
                <el-dropdown-item command="edit">编辑</el-dropdown-item>
                <el-dropdown-item command="delete" divided>删除</el-dropdown-item>
              </el-dropdown-menu>
            </template>
          </el-dropdown>
        </template>
      </el-table-column>
    </el-table>

    <div class="table-pagination">
      <el-pagination
        :current-page="currentPage"
        :page-size="currentPageSize"
        :total="total"
        :page-sizes="[10, 20, 50, 100]"
        layout="total, sizes, prev, pager, next"
        @update:current-page="emit('update:currentPage', $event); emit('page-change')"
        @update:page-size="emit('update:currentPageSize', $event); emit('page-change')"
      />
    </div>
  </el-card>
</template>

<script setup>
import StatusTag from '@shared/components/StatusTag.vue'
import { getStatusLabel } from '@shared/statusConfig'
import { formatDateTime } from '@/utils/datetime'
import { CONTENT_STATUS_MAP } from '@shared/extraStatusMaps'
import { useResponsive } from '@/composables/useResponsive'

const { isMobile } = useResponsive()

defineProps({
  list: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  total: { type: Number, default: 0 },
  pageTitle: { type: String, default: '' },
  currentArticleLabel: { type: String, default: '' },
  currentPage: { type: Number, default: 1 },
  currentPageSize: { type: Number, default: 20 },
})

const emit = defineEmits(['edit', 'delete', 'create', 'reset-filters', 'page-change', 'update:currentPage', 'update:currentPageSize'])

function handleContentAction(command, row) {
  if (command === 'edit') {
    emit('edit', row.id)
  } else if (command === 'delete') {
    emit('delete', row)
  }
}

function statusLabel(value) {
  return getStatusLabel(CONTENT_STATUS_MAP, Number(value))
}
</script>

<style scoped lang="scss">
.panel-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
}

.panel-header-meta {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.panel-header-meta strong {
  color: $text-color-primary;
  font-size: 15px;
  font-weight: 600;
}

.panel-header-meta span {
  color: $text-color-placeholder;
  font-size: 12px;
}

.flag-tags,
.action-inline,
.table-empty-actions {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;
}

.muted-text,
.date-cell small,
.category-cell small,
.title-summary {
  color: $text-color-placeholder;
  font-size: 12px;
}

.content-table-card {
  overflow: hidden;
}

.title-button {
  border: none;
  padding: 0;
  background: transparent;
  color: $color-primary;
  font: inherit;
  font-weight: 600;
  text-align: left;
  cursor: pointer;
}

.title-button:hover {
  text-decoration: underline;
}

.title-summary {
  margin-top: 6px;
  line-height: 1.6;
  display: -webkit-box;
  overflow: hidden;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
}

.category-cell,
.date-cell {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.category-cell span,
.date-cell span {
  color: $text-color-primary;
  font-size: 13px;
}

.table-pagination {
  display: flex;
  justify-content: flex-end;
  margin-top: 16px;
}

.table-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 8px;
  min-height: 240px;
  padding: 20px 0;
}

.table-empty strong {
  color: $text-color-primary;
  font-size: 15px;
}

.table-empty p {
  color: $text-color-secondary;
  font-size: 12px;
  line-height: 1.6;
}
</style>
