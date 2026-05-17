<template>
  <!-- 实名详情弹窗 -->
  <el-dialog
    v-model="detailDialog.visible"
    :title="detailDialogTitle"
    width="920px"
    class="verification-detail-dialog"
    destroy-on-close
  >
    <div v-loading="detailDialog.loading" class="detail-dialog-body">
      <div class="detail-hero">
        <div class="detail-hero-main">
          <p class="detail-eyebrow">实名认证详情</p>
          <h3>{{ detailDialog.data.real_name || detailDialog.data.display_name || '未命名记录' }}</h3>
          <p class="detail-subtitle">
            {{ [detailDialog.data.email, detailDialog.data.phone].filter(Boolean).join(' / ') || '暂无联系方式' }}
          </p>
          <div class="detail-meta-row">
            <div v-for="item in detailMetaItems" :key="item.label" class="detail-meta-item">
              <span>{{ item.label }}</span>
              <strong>{{ item.value }}</strong>
            </div>
          </div>
        </div>
        <StatusTag :status-map="VERIFICATION_STATUS_MAP" :status="detailDialog.data.verification_status" effect="light">
          {{ verificationStatusLabel(detailDialog.data) }}
        </StatusTag>
      </div>

      <div class="detail-grid">
        <div
          v-for="item in detailFields"
          :key="item.label"
          class="detail-card"
          :class="{ 'is-wide': item.wide, 'is-multiline': item.multiline }"
        >
          <div class="detail-label">{{ item.label }}</div>
          <div class="detail-value">{{ item.value }}</div>
        </div>
      </div>
    </div>
  </el-dialog>

  <!-- 历史记录弹窗 -->
  <el-dialog
    v-model="historyDialog.visible"
    :title="historyDialogTitle"
    width="1120px"
    class="verification-history-dialog"
    destroy-on-close
  >
    <div v-loading="historyDialog.loading" class="history-dialog-body">
      <el-table
        :data="historyDialog.list"
        stripe
        class="history-table"
        header-row-class-name="verification-history-header"
      >
        <el-table-column prop="real_name" label="实名认证名称" min-width="180" />
        <el-table-column label="身份证号码" min-width="180">
          <template #default="{ row }">
            {{ formatDetailValue(row.id_card || row.id_card_masked) }}
          </template>
        </el-table-column>
        <el-table-column prop="verification_method_label" label="认证方式" min-width="140" />
        <el-table-column prop="verification_type_label" label="认证类型" width="110" />
        <el-table-column label="状态/原因" min-width="180">
          <template #default="{ row }">
            <StatusTag :status-map="VERIFICATION_STATUS_MAP" :status="row.verification_status">
              {{ verificationStatusLabel(row) }}
            </StatusTag>
          </template>
        </el-table-column>
        <el-table-column label="提交时间" min-width="170">
          <template #default="{ row }">
            {{ formatDetailValue(row.submitted_at || row.created_at) }}
          </template>
        </el-table-column>
      </el-table>
    </div>
  </el-dialog>
</template>

<script setup>
import StatusTag from '@shared/components/StatusTag.vue'
import { VERIFICATION_STATUS_MAP } from '@shared/statusConfig'

defineProps({
  detailDialog: { type: Object, required: true },
  historyDialog: { type: Object, required: true },
  detailDialogTitle: { type: String, default: '实名认证详情' },
  historyDialogTitle: { type: String, default: '历史记录' },
  detailMetaItems: { type: Array, default: () => [] },
  detailFields: { type: Array, default: () => [] },
  verificationStatusLabel: { type: Function, required: true },
  formatDetailValue: { type: Function, required: true },
})
</script>

<style lang="scss" scoped>
:deep(.verification-detail-dialog) {
  .el-dialog {
    border-radius: 20px;
    overflow: hidden;
    max-width: calc(100vw - 32px);
    background-color: $bg-color-card;
    border: 1px solid $border-color;
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
  }

  .el-dialog__header {
    padding: 16px 20px 0;
    border-bottom: 1px solid $divider-color;
  }

  .el-dialog__title {
    color: $text-color-primary;
  }
  .el-dialog__close {
    color: $text-color-secondary;
  }

  .el-dialog__body {
    padding: 0 20px 18px;
  }

  .el-tag {
    border: none;
    &--success {
      background-color: rgba($color-success, 0.15);
      color: $color-success;
    }
    &--danger {
      background-color: rgba($color-danger, 0.15);
      color: $color-danger;
    }
  }
}

:deep(.verification-history-dialog) {
  .el-dialog {
    border-radius: 20px;
    overflow: hidden;
    max-width: calc(100vw - 32px);
    background-color: $bg-color-card;
    border: 1px solid $border-color;
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
  }

  .el-dialog__header {
    padding: 18px 24px 0;
    border-bottom: 1px solid $divider-color;
  }
  .el-dialog__title {
    color: $text-color-primary;
  }

  .el-dialog__body {
    padding: 20px 24px 24px;
  }
}

.detail-dialog-body {
  min-height: 180px;
  max-height: calc(78vh - 48px);
  overflow-y: auto;
}

.history-dialog-body {
  min-height: 220px;
}

.detail-hero {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 14px;
  padding: 14px 0 12px;
  border-bottom: 1px solid $divider-color;
}

.detail-hero-main {
  min-width: 0;

  h3 {
    margin: 0;
    font-size: 24px;
    color: $text-color-primary;
    line-height: 1.15;
  }
}

.detail-eyebrow {
  margin: 0 0 6px;
  font-size: $font-size-sm;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: $color-primary;
}

.detail-subtitle {
  margin: 6px 0 0;
  font-size: 13px;
  color: $text-color-secondary;
}

.detail-meta-row {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 8px;
  margin-top: 12px;
}

.detail-meta-item {
  display: flex;
  flex-direction: column;
  gap: 4px;
  padding: 10px 12px;
  border: 1px solid $divider-color;
  border-radius: 12px;
  background: $bg-color-soft;
}

.detail-meta-item span {
  color: $text-color-placeholder;
  font-size: 11px;
  font-weight: 600;
}

.detail-meta-item strong {
  color: $text-color-primary;
  font-size: 13px;
  font-weight: 600;
  line-height: 1.5;
  word-break: break-all;
}

.detail-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 10px;
  margin-top: 14px;
}

.detail-card {
  padding: 12px 14px;
  border: 1px solid $border-color;
  border-radius: 12px;
  background: $bg-color-soft;
  box-shadow: none;

  &.is-wide {
    grid-column: 1 / -1;
  }

  &.is-multiline .detail-value {
    white-space: pre-wrap;
    line-height: 1.65;
  }
}

.detail-label {
  margin-bottom: 4px;
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.02em;
  color: $text-color-secondary;
}

.detail-value {
  font-size: 14px;
  line-height: 1.45;
  color: $text-color-primary;
  word-break: break-word;
}

.history-table {
  :deep(.verification-history-header th) {
    background: $bg-color-hover;
    color: $text-color-secondary;
    font-weight: 500;
  }

  :deep(.el-table__row td) {
    padding-top: 10px;
    padding-bottom: 10px;
  }
}

@media (max-width: 900px) {
  .detail-meta-row,
  .detail-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 640px) {
  .detail-hero {
    flex-direction: column;
    align-items: stretch;
  }

  .detail-meta-row,
  .detail-grid {
    grid-template-columns: 1fr;
  }
}
</style>
