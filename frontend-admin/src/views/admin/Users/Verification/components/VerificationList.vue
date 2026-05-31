<template>
  <div class="list-panel tab-content">
    <div class="toolbar compact-toolbar">
      <el-input
        v-model="filters.keyword"
        placeholder="输入关键字"
        clearable
        style="width: 180px"
        @keyup.enter="$emit('search')"
      />
      <div class="toolbar-spacer" />
      <span class="filter-label">状态筛选:</span>
      <el-select :model-value="quickStatus" placeholder="请选择" style="width: 160px" @change="$emit('status-change', $event)">
        <el-option label="全部" value="all" />
        <el-option label="待认证" value="pending" />
        <el-option label="成功" value="success" />
        <el-option label="失败" value="failed" />
      </el-select>
    </div>

    <el-table :data="list" v-loading="listLoading" stripe class="verification-table" header-row-class-name="verification-table-header">
      <el-table-column prop="id" label="ID" width="78" />
      <el-table-column prop="display_name" label="姓名" min-width="130">
        <template #default="{ row }">
          <el-link type="primary" underline="never">{{ row.display_name || '-' }}</el-link>
        </template>
      </el-table-column>
      <el-table-column prop="real_name" label="实名认证名称" min-width="140">
        <template #default="{ row }">
          <el-link type="primary" underline="never">{{ row.real_name || '-' }}</el-link>
        </template>
      </el-table-column>
      <el-table-column prop="id_card_masked" label="身份证号码" min-width="160" />
      <el-table-column label="认证方式" width="110">
        <template #default="{ row }">
          {{ verificationMethodLabel(row) }}
        </template>
      </el-table-column>
      <el-table-column label="认证类型" width="100">
        <template #default>
          个人认证
        </template>
      </el-table-column>
      <el-table-column label="状态/原因" min-width="220">
        <template #default="{ row }">
          <StatusTag :status-map="VERIFICATION_STATUS_MAP" :status="row.verification_status">
            {{ verificationStatusLabel(row) }}
          </StatusTag>
        </template>
      </el-table-column>
      <el-table-column label="提交时间" min-width="160">
        <template #default="{ row }">
          {{ formatDateTime(row.created_at) }}
        </template>
      </el-table-column>
      <el-table-column label="操作" :width="isMobile ? 60 : 140" fixed="right">
        <template #default="{ row }">
          <div v-if="!isMobile" class="action-links">
            <el-button link type="success" @click="$emit('open-detail', row)">查看</el-button>
            <el-button link type="primary" @click="$emit('open-history', row)">历史记录</el-button>
            <el-button
              v-if="canReject(row)"
              link
              type="danger"
              :loading="actionLoadingId === row.id"
              @click="$emit('reject', row)"
            >
              驳回
            </el-button>
          </div>
          <el-dropdown v-else trigger="click" @command="(cmd) => handleVerificationAction(cmd, row)">
            <span class="action-link">···</span>
            <template #dropdown>
              <el-dropdown-menu>
                <el-dropdown-item command="detail">查看</el-dropdown-item>
                <el-dropdown-item command="history">历史记录</el-dropdown-item>
                <el-dropdown-item v-if="canReject(row)" command="reject" divided>驳回</el-dropdown-item>
              </el-dropdown-menu>
            </template>
          </el-dropdown>
        </template>
      </el-table-column>
    </el-table>

    <div class="pagination">
      <el-pagination
        :current-page="page"
        :page-size="pageSize"
        :total="total"
        :page-sizes="[20, 50, 100]"
        layout="total, sizes, prev, pager, next"
        @update:current-page="$emit('update:page', $event); $emit('search')"
        @update:page-size="$emit('update:pageSize', $event); $emit('search')"
      />
    </div>
  </div>
</template>

<script setup>
import StatusTag from '@shared/components/StatusTag.vue'
import { VERIFICATION_STATUS_MAP } from '@shared/statusConfig'
import { formatDateTime } from '@/utils/datetime'
import { useResponsive } from '@/composables/useResponsive'

const { isMobile } = useResponsive()

defineProps({
  list: { type: Array, default: () => [] },
  listLoading: { type: Boolean, default: false },
  total: { type: Number, default: 0 },
  page: { type: Number, default: 1 },
  pageSize: { type: Number, default: 20 },
  quickStatus: { type: String, default: 'all' },
  filters: { type: Object, required: true },
  verificationMethodLabel: { type: Function, required: true },
  verificationStatusLabel: { type: Function, required: true },
  canReject: { type: Function, required: true },
  actionLoadingId: { type: Number, default: 0 },
})

const emit = defineEmits(['search', 'status-change', 'open-detail', 'open-history', 'reject', 'update:page', 'update:pageSize'])

function handleVerificationAction(command, row) {
  if (command === 'detail') {
    emit('open-detail', row)
  } else if (command === 'history') {
    emit('open-history', row)
  } else if (command === 'reject') {
    emit('reject', row)
  }
}
</script>

<style lang="scss" scoped>
.tab-content {
  padding: 20px;
}

.toolbar {
  display: flex;
  gap: 12px;
  flex-wrap: wrap;
  margin-bottom: 16px;
}

.compact-toolbar {
  align-items: center;
  margin-bottom: 18px;
}

.toolbar-spacer {
  flex: 1;
}

.filter-label {
  color: $text-color-secondary;
  font-size: $font-size-base;
}

.verification-table {
  background-color: $bg-color-card;
  border: none;
  border-radius: $base-border-radius;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);

  :deep(.el-table__inner-wrapper::before) {
    height: 0;
  }
  :deep(.verification-table-header th) {
    background: $bg-color-hover;
    color: $text-color-secondary;
    font-weight: 500;
    border-bottom: 1px solid $divider-color;
  }

  :deep(.el-table__row td) {
    padding-top: 10px;
    padding-bottom: 10px;
  }
}

:deep(.el-table__row) {
  background-color: $bg-color-card;
  color: $text-color-primary;
}
:deep(.el-table__row.el-table__row--striped) {
  background-color: $bg-color-stripe;
}
:deep(.el-table__row:hover) {
  background-color: $bg-color-hover !important;
}
:deep(.el-table__cell) {
  border-bottom: 1px solid $divider-color;
}

.action-links {
  display: flex;
  align-items: center;
  gap: 4px;
  flex-wrap: wrap;
}

.pagination {
  display: flex;
  justify-content: flex-end;
  margin-top: $base-margin;

  :deep(.el-pagination) {
    background-color: $bg-color-card;
    padding: 10px 16px;
    border-radius: $base-border-radius;
  }
  :deep(.el-pagination__total),
  :deep(.el-pagination__sizes .el-input__inner),
  :deep(.el-pagination__jump) {
    color: $text-color-secondary;
  }
  :deep(.el-pager li) {
    background-color: $bg-color-hover;
    color: $text-color-secondary;
    border-radius: $sm-border-radius;
  }
  :deep(.el-pager li.is-active) {
    background-color: $color-primary;
    color: $text-color-primary;
  }
  :deep(.btn-prev),
  :deep(.btn-next) {
    background-color: $bg-color-hover;
    color: $text-color-secondary;
  }
}

@media (max-width: 640px) {
  .compact-toolbar {
    align-items: stretch;
  }

  .toolbar-spacer {
    display: none;
  }
}
</style>
