<template>
  <div>
    <div class="toolbar">
      <el-input
        v-model="state.filters.keyword"
        placeholder="搜索服务名、域名、账单号、配置名"
        clearable
        @keyup.enter="emit('search')"
        @clear="emit('search')"
      />
      <el-select v-model="state.filters.status" placeholder="状态" clearable @change="emit('search')">
        <el-option
          v-for="option in serviceStatusOptions"
          :key="option.value"
          :label="option.label"
          :value="option.value"
        />
      </el-select>
      <div class="toolbar-actions">
        <el-button type="primary" @click="emit('add')">添加实例</el-button>
        <el-button
          :loading="state.refreshing || state.refreshingStatus"
          :icon="Refresh"
          class="refresh-btn"
          @click="handleRefresh"
        />
      </div>
    </div>

    <el-table :data="state.list" v-loading="state.loading" stripe :row-key="resolveRowKey">
      <el-table-column prop="id" label="ID" width="80" />
      <el-table-column label="配置/服务" min-width="240">
        <template #default="{ row }">
          <div class="service-name-cell">
            <strong>{{ row.name || row.product_display_name || row.product?.display_name || (row.product_id ? `未配置规格 #${row.product_id}` : '-') }}</strong>
            <div class="service-name-meta">
              <span>{{ row.domain || row.product?.group_name || '-' }}</span>
              <span>{{ row.invoice?.invoice_no || row.order?.invoice_no || '-' }}</span>
            </div>
          </div>
        </template>
      </el-table-column>
      <el-table-column label="公网 IP" min-width="140">
        <template #default="{ row }">{{ row.upstream?.dedicated_ip || '-' }}</template>
      </el-table-column>
      <el-table-column label="金额" width="120">
        <template #default="{ row }">{{ formatMoney(row.amount) }}</template>
      </el-table-column>
      <el-table-column label="产品类型" width="120">
        <template #default="{ row }">{{ row.product?.type_label || '-' }}</template>
      </el-table-column>
      <el-table-column label="计费周期" width="120">
        <template #default="{ row }">{{ row.billing_cycle_label || '-' }}</template>
      </el-table-column>
      <el-table-column label="购买时间" min-width="160">
        <template #default="{ row }">{{ formatDateTime(row.created_at) }}</template>
      </el-table-column>
      <el-table-column label="到期时间" min-width="160">
        <template #default="{ row }">{{ formatDateTime(row.expires_at) }}</template>
      </el-table-column>
      <el-table-column label="状态" width="100">
        <template #default="{ row }">
          <el-tag size="small" :type="resolveServiceToneTagType(row.status_tone)" effect="plain">
            {{ row.status_label || '-' }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column label="操作" :width="isMobile ? 60 : 160">
        <template #default="{ row }">
          <div v-if="!isMobile" class="row-actions">
            <span class="action-link action-link--primary" @click="emit('manage', row)">管理</span>
            <span class="action-link" @click="emit('refresh-row', row)">刷新</span>
            <el-popconfirm title="确定删除该服务记录？" @confirm="emit('delete-row', row)">
              <template #reference>
                <span class="action-link action-link--danger">删除</span>
              </template>
            </el-popconfirm>
          </div>
          <el-dropdown v-else trigger="click" @command="(cmd) => handleAction(cmd, row)">
            <span class="action-link">···</span>
            <template #dropdown>
              <el-dropdown-menu>
                <el-dropdown-item command="manage">管理</el-dropdown-item>
                <el-dropdown-item command="refresh">刷新</el-dropdown-item>
                <el-dropdown-item command="delete" divided>删除</el-dropdown-item>
              </el-dropdown-menu>
            </template>
          </el-dropdown>
        </template>
      </el-table-column>
    </el-table>

    <div class="pager">
      <el-pagination
        :current-page="state.page"
        :page-size="state.pageSize"
        :total="state.total"
        :page-sizes="[10, 20, 50]"
        layout="total, sizes, prev, pager, next"
        @current-change="handlePageChange"
        @size-change="handlePageSizeChange"
      />
    </div>
  </div>
</template>

<script setup>
import { Refresh } from '@element-plus/icons-vue'
import { formatDateTime } from '@/utils/datetime'
import { SERVICE_STATUS_MAP, toSelectOptions } from '@shared/statusConfig'
import { useResponsive } from '@/composables/useResponsive'

const { isMobile } = useResponsive()
const serviceStatusOptions = toSelectOptions(SERVICE_STATUS_MAP, false)

function handleAction(command, row) {
  if (command === 'delete') {
    emit('delete-row', row)
  } else {
    emit(command, row)
  }
}

function handleRefresh() {
  emit('reload')
  emit('refresh-status')
}

const props = defineProps({
  state: {
    type: Object,
    required: true,
  },
  formatMoney: {
    type: Function,
    required: true,
  },
  resolveServiceToneTagType: {
    type: Function,
    required: true,
  },
})

const emit = defineEmits([
  'search',
  'add',
  'reload',
  'refresh-status',
  'refresh-row',
  'delete-row',
  'manage',
])

function resolveRowKey(row) {
  return row?.id || row?.service_id || row?.order?.id || `${row?.created_at || ''}-${row?.name || ''}-${row?.domain || ''}`
}

function handlePageChange(page) {
  props.state.page = page
  emit('reload')
}

function handlePageSizeChange(pageSize) {
  props.state.pageSize = pageSize
  props.state.page = 1
  emit('reload')
}
</script>

<style lang="scss" scoped>
.toolbar {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 12px;
  flex-wrap: wrap;
}

.toolbar-actions {
  display: inline-flex;
  gap: 10px;
  margin-left: auto;
  flex-wrap: wrap;
  align-items: center;
}

.refresh-btn {
  width: 36px;
  min-width: 36px;
  padding: 0;
}

.toolbar :deep(.el-input) {
  width: 320px;
}

.toolbar :deep(.el-select) {
  width: 130px;
}

.pager {
  display: flex;
  justify-content: flex-end;
  margin-top: 14px;
}

.service-name-cell {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.service-name-cell strong {
  color: $text-color-primary;
  font-size: 13px;
  font-weight: 500;
  line-height: 1.5;
}

.service-name-meta {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
  color: $text-color-placeholder;
  font-size: 12px;
}

.row-actions {
  display: inline-flex;
  align-items: center;
  gap: 12px;
}

.action-link {
  cursor: pointer;
  color: $text-color-secondary;
  font-size: 13px;
  white-space: nowrap;
  transition: color $duration-fast $ease-standard;

  &:hover { color: $color-primary; }
  &--primary { color: $color-primary; }
  &--danger  { color: $text-color-secondary; &:hover { color: $color-danger; } }
}

@include tablet-and-below {
  .toolbar {
    gap: 8px;
  }

  .toolbar :deep(.el-input) {
    width: 100%;
    flex-basis: 100%;
  }

  .toolbar :deep(.el-select) {
    width: auto;
    min-width: 100px;
    flex: 1;
  }

  .toolbar-actions {
    margin-left: 0;
    gap: 8px;
    flex-shrink: 0;
  }

  .toolbar-actions :deep(.el-button) {
    flex: 1;
    min-width: 0;
  }

  .toolbar .refresh-btn {
    flex: 0 0 36px;
    min-width: 36px;
  }
}
</style>
