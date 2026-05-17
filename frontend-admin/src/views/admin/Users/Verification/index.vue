<template>
  <div class="verification-container">
    <el-tabs v-model="activeTab" type="border-card">
      <el-tab-pane label="实名列表" name="list">
        <VerificationList
          :list="list"
          :list-loading="listLoading"
          :total="total"
          :page="page"
          :page-size="pageSize"
          :quick-status="quickStatus"
          :filters="filters"
          :verification-method-label="verificationMethodLabel"
          :verification-status-label="verificationStatusLabel"
          :can-reject="canReject"
          :action-loading-id="actionLoadingId"
          @search="loadList"
          @status-change="handleQuickStatusChange"
          @open-detail="openDetail"
          @open-history="openHistory"
          @reject="handleReject"
          @update:page="page = $event"
          @update:page-size="pageSize = $event; page = 1"
        />
      </el-tab-pane>

      <el-tab-pane label="实名管理" name="manage">
        <div class="tab-content manage-panel">
          <el-divider content-position="left">费用设置</el-divider>
          <el-form
            ref="feeFormRef"
            :model="feeForm"
            :rules="feeRules"
            label-width="160px"
            class="verification-form"
            status-icon
            @submit.prevent
          >
            <el-form-item label="免费认证次数" prop="free_attempts">
              <el-input-number v-model="feeForm.free_attempts" :min="0" :max="10" />
              <div class="form-tip">每个用户可免费进行实名认证的次数。</div>
            </el-form-item>

            <el-form-item label="失败后再次认证费用" prop="retry_fee">
              <el-input-number v-model="feeForm.retry_fee" :min="0" :precision="2" :step="0.5" />
              <span class="fee-suffix">元</span>
              <div class="form-tip">超过免费次数后，每次再次认证收取的费用。</div>
            </el-form-item>

            <el-form-item>
              <el-button type="primary" :loading="feeLoading" @click="saveFeeSettings">保存费用设置</el-button>
            </el-form-item>
          </el-form>
        </div>
      </el-tab-pane>

      <el-tab-pane label="实名接口" name="api">
        <div class="tab-content">
          <el-form
            ref="apiFormRef"
            :model="form"
            :rules="apiRules"
            label-width="120px"
            class="verification-form"
            status-icon
            @submit.prevent
          >
            <el-form-item label="API ID" prop="verification_api">
              <el-input v-model="form.verification_api" placeholder="请输入 API ID" />
            </el-form-item>

            <el-form-item label="API KEY" prop="verification_key">
              <el-input v-model="form.verification_key" placeholder="请输入 API KEY" />
            </el-form-item>

            <el-form-item label="认证方式" prop="verification_biz_code">
              <el-select v-model="form.verification_biz_code">
                <el-option label="人脸识别" value="FACE" />
                <el-option label="证照认证" value="CERT_PHOTO" />
                <el-option label="证照+人脸" value="CERT_PHOTO_FACE" />
                <el-option label="快捷认证" value="SMART_FACE" />
              </el-select>
            </el-form-item>

            <el-form-item>
              <el-button type="primary" :loading="loading" @click="handleSave">保存配置</el-button>
            </el-form-item>
          </el-form>
        </div>
      </el-tab-pane>
    </el-tabs>

    <VerificationDetail
      :detail-dialog="detailDialog"
      :history-dialog="historyDialog"
      :detail-dialog-title="detailDialogTitle"
      :history-dialog-title="historyDialogTitle"
      :detail-meta-items="detailMetaItems"
      :detail-fields="detailFields"
      :verification-status-label="verificationStatusLabel"
      :format-detail-value="formatDetailValue"
    />
  </div>
</template>

<script setup>
import VerificationList from './components/VerificationList.vue'
import VerificationDetail from './components/VerificationDetail.vue'
import { useVerification } from './composables/useVerification.js'

const {
  activeTab,
  apiFormRef,
  feeFormRef,
  loading,
  listLoading,
  feeLoading,
  actionLoadingId,
  list,
  total,
  page,
  pageSize,
  quickStatus,
  filters,
  form,
  feeForm,
  apiRules,
  feeRules,
  detailDialog,
  historyDialog,
  detailDialogTitle,
  historyDialogTitle,
  detailMetaItems,
  detailFields,
  formatDetailValue,
  verificationMethodLabel,
  canReject,
  verificationStatusLabel,
  openDetail,
  openHistory,
  handleReject,
  loadList,
  handleQuickStatusChange,
  handleSave,
  saveFeeSettings,
} = useVerification()
</script>

<style lang="scss" scoped>
.verification-container {
  :deep(.el-tabs--border-card) {
    background-color: $bg-color-card;
    border: 1px solid $border-color;
    border-radius: $base-border-radius;

    .el-tabs__header {
      background-color: $bg-color-card;
      border-bottom: 1px solid $divider-color;

      .el-tabs__item {
        color: $text-color-secondary;
        background-color: $bg-color-card;
        border-left: 1px solid $border-color;
        border-bottom: 1px solid $divider-color;

        &:not(.is-active) {
          border-bottom-color: $divider-color;
        }

        &.is-active {
          color: $color-primary;
          background-color: $bg-color-card;
          border-bottom-color: transparent;
        }

        &:hover {
          color: $color-primary;
        }
      }
    }

    .el-tabs__content {
      background-color: $bg-color-card;
      padding: 0;
    }
  }

  :deep(.el-input__wrapper),
  :deep(.el-textarea__inner),
  :deep(.el-select__wrapper),
  :deep(.el-input-number__decrease),
  :deep(.el-input-number__increase) {
    background-color: $bg-color-hover;
    box-shadow: none;
    border: 1px solid $border-color;
    border-radius: $sm-border-radius;
  }

  :deep(.el-input__inner),
  :deep(.el-textarea__inner) {
    color: $text-color-primary;
  }

  :deep(.el-input__inner::placeholder) {
    color: $text-color-placeholder;
  }

  :deep(.el-input__wrapper.is-focus),
  :deep(.el-select__wrapper.is-focused) {
    box-shadow: 0 0 0 1px $color-primary inset;
    border-color: $color-primary;
  }

  :deep(.el-input-number__decrease),
  :deep(.el-input-number__increase) {
    background: $bg-color-hover;
    border-color: $border-color;
    color: $text-color-secondary;
  }

  :deep(.el-divider__text) {
    background: $bg-color-card;
    color: $text-color-secondary;
  }

  :deep(.el-divider__horizontal) {
    border-top-color: $divider-color;
  }

  .tab-content {
    padding: 20px;
  }

  .manage-panel {
    max-width: 860px;
  }

  .verification-form {
    max-width: 600px;
  }

  .form-tip {
    font-size: $font-size-sm;
    color: $text-color-secondary;
    margin-top: 5px;
  }

  .fee-suffix {
    margin-left: 10px;
    color: $text-color-secondary;
  }
}

</style>
