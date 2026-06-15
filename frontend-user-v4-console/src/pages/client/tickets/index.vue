<template>
  <section class="client-tickets">
    <t-card class="ticket-filter-card" :bordered="false">
      <div class="ticket-filter-bar">
        <t-input
          v-model="filters.keyword"
          class="ticket-filter-bar__search"
          clearable
          placeholder="搜索工单标题"
          @enter="handleSearch"
          @clear="handleSearch"
        />
        <t-select v-model="filters.status" clearable placeholder="全部状态" @change="handleSearch">
          <t-option v-for="item in TICKET_STATUS_OPTIONS" :key="item.value" :label="item.label" :value="item.value" />
        </t-select>
        <div class="ticket-filter-actions">
          <t-button theme="primary" @click="openCreateDialog">
            <template #icon><AddIcon /></template>
            提交工单
          </t-button>
          <t-button variant="outline" @click="resetFilters">重置</t-button>
        </div>
      </div>
    </t-card>

    <section class="ticket-list-card">
      <t-loading :loading="loading" text="正在加载工单">
        <template v-if="list.length">
          <div class="ticket-table-shell">
            <t-table row-key="id" :data="list" :columns="columns" :pagination="null" hover>
              <template #subject="{ row }">
                <button type="button" class="ticket-link" @click="openDetail(row)">#{{ row.id }} {{ row.subject || '--' }}</button>
              </template>
              <template #status="{ row }">
                <t-tag :theme="resolveTicketTagTheme(row.status)" variant="light">
                  {{ resolveTicketStatusLabel(row.status) }}
                </t-tag>
              </template>
              <template #priority="{ row }">
                <t-tag :theme="resolvePriorityTheme(row.priority)" variant="light">
                  {{ resolvePriorityLabel(row.priority) }}
                </t-tag>
              </template>
              <template #updated_at="{ row }">{{ formatTicketTime(row.updated_at) }}</template>
              <template #operation="{ row }">
                <t-button size="small" theme="primary" variant="text" @click="openDetail(row)">查看交流</t-button>
              </template>
            </t-table>
          </div>

          <div class="mobile-ticket-list">
            <button v-for="row in list" :key="row.id" type="button" class="mobile-ticket-card" @click="openDetail(row)">
              <span class="mobile-ticket-card__top">
                <strong>#{{ row.id }}</strong>
                <t-tag :theme="resolveTicketTagTheme(row.status)" variant="light">{{ resolveTicketStatusLabel(row.status) }}</t-tag>
              </span>
              <span class="mobile-ticket-card__title">{{ row.subject || '--' }}</span>
              <span class="mobile-ticket-card__meta">
                <span>优先级：{{ resolvePriorityLabel(row.priority) }}</span>
                <span>{{ formatTicketTime(row.updated_at) }}</span>
              </span>
            </button>
          </div>
        </template>
        <t-empty v-else description="暂无工单记录" />
      </t-loading>
    </section>

    <div v-if="total > 0" class="ticket-pagination">
      <t-pagination
        v-model="filters.page"
        v-model:pageSize="filters.page_size"
        :total="total"
        :page-size-options="[10, 20, 50]"
        show-total
        @change="loadTickets"
        @page-size-change="handlePageSizeChange"
      />
    </div>

    <t-dialog
      v-model:visible="createVisible"
      header="提交工单"
      width="min(44rem, calc(100vw - var(--td-comp-margin-xl)))"
      destroy-on-close
      :confirm-btn="{ content: '提交工单', loading: creating, disabled: !createForm.subject.trim() || uploading }"
      cancel-btn="取消"
      @confirm="submitTicket"
      @close="closeCreateDialog"
    >
      <t-form label-align="top" class="ticket-create-form">
        <t-form-item label="问题分类" required-mark>
          <t-select v-model="createForm.department">
            <t-option v-for="item in TICKET_DEPARTMENT_OPTIONS" :key="item.value" :label="item.label" :value="item.value" />
          </t-select>
        </t-form-item>
        <t-form-item label="工单标题" required-mark>
          <t-input v-model="createForm.subject" maxlength="200" placeholder="请简要描述您的问题" />
        </t-form-item>
        <t-form-item label="关联服务">
          <t-select
            v-model="createForm.service_id"
            clearable
            filterable
            :loading="serviceLoading"
            placeholder="可选，如与具体服务相关"
          >
            <t-option v-for="item in serviceSelectOptions" :key="item.value" :label="item.label" :value="item.value" />
          </t-select>
        </t-form-item>
        <t-form-item label="优先级">
          <t-select v-model="createForm.priority">
            <t-option v-for="item in TICKET_PRIORITY_OPTIONS" :key="item.value" :label="item.label" :value="item.value" />
          </t-select>
        </t-form-item>
        <t-form-item label="问题描述">
          <t-textarea
            v-model="createForm.content"
            :autosize="{ minRows: 5, maxRows: 8 }"
            maxlength="10000"
            placeholder="请详细描述您遇到的问题"
          />
        </t-form-item>
        <t-form-item label="添加附件">
          <div class="ticket-attachment-box">
            <label class="upload-trigger">
              <input type="file" accept=".jpg,.jpeg,.png,.webp" multiple @change="handleCreateUpload" />
              <span><AddIcon /> 上传图片</span>
            </label>
            <span class="upload-tip">支持 jpg/png/webp，最多 9 张，单张不超过 5MB</span>
            <div v-if="uploadFiles.length" class="attachment-list">
              <button
                v-for="(file, index) in uploadFiles"
                :key="file.id || file.path || index"
                type="button"
                class="attachment-thumb"
                @click="previewUploadFile(file)"
              >
                <img :src="file.url || file.path" alt="附件" />
                <span @click.stop="removeUploadFile(index)">移除</span>
              </button>
            </div>
          </div>
        </t-form-item>
      </t-form>
    </t-dialog>

    <t-dialog v-model:visible="previewVisible" header="附件预览" width="min(45rem, calc(100vw - var(--td-comp-margin-xl)))">
      <img v-if="previewUrl" :src="previewUrl" class="preview-image" alt="附件预览" />
    </t-dialog>
  </section>
</template>

<script setup lang="ts">
import { onMounted } from 'vue';
import type { PrimaryTableCol } from 'tdesign-vue-next';
import { AddIcon } from 'tdesign-icons-vue-next';

import {
  formatTicketTime,
  resolvePriorityLabel,
  resolvePriorityTheme,
  resolveTicketStatusLabel,
  resolveTicketTagTheme,
  TICKET_DEPARTMENT_OPTIONS,
  TICKET_PRIORITY_OPTIONS,
  TICKET_STATUS_OPTIONS,
  useTicketList,
} from '@/domains/support/useTickets';

const {
  loading,
  creating,
  serviceLoading,
  createVisible,
  uploading,
  list,
  total,
  filters,
  createForm,
  serviceSelectOptions,
  uploadFiles,
  previewVisible,
  previewUrl,
  loadTickets,
  handleSearch,
  handlePageSizeChange,
  resetFilters,
  openCreateDialog,
  closeCreateDialog,
  uploadTicketImage,
  removeUploadFile,
  previewUploadFile,
  submitTicket,
  openDetail,
} = useTicketList();

const columns: PrimaryTableCol[] = [
  { colKey: 'subject', title: '标题', minWidth: '18rem' },
  { colKey: 'status', title: '状态', width: '8rem' },
  { colKey: 'priority', title: '优先级', width: '8rem' },
  { colKey: 'updated_at', title: '更新时间', minWidth: '12rem' },
  { colKey: 'operation', title: '操作', width: '8rem', align: 'right' },
];

async function handleCreateUpload(event: Event) {
  const input = event.target as HTMLInputElement;
  const files = Array.from(input.files || []);
  for (const file of files) {
    await uploadTicketImage(file);
  }
  input.value = '';
}

onMounted(() => {
  void loadTickets();
});
</script>

<style scoped lang="less">
.client-tickets {
  display: flex;
  flex-direction: column;
  gap: var(--td-comp-margin-m);
  padding: var(--td-comp-paddingTB-l) var(--td-comp-paddingLR-l);
}

.ticket-filter-card {
  background: var(--td-bg-color-container);
  border: thin solid var(--td-border-color);
  border-radius: var(--td-radius-medium);
  box-shadow: var(--td-shadow-1);
}

.ticket-filter-bar {
  display: grid;
  grid-template-columns: minmax(16rem, 1fr) minmax(10rem, 0.45fr) auto;
  gap: var(--td-comp-margin-s);
  align-items: center;
}

.ticket-filter-actions {
  display: flex;
  flex-wrap: wrap;
  gap: var(--td-comp-margin-s);
  justify-content: flex-end;
}

.ticket-link {
  padding: 0;
  color: var(--td-brand-color);
  font: var(--td-font-body-medium);
  text-align: left;
  cursor: pointer;
  background: transparent;
  border: 0;
}

.mobile-ticket-list {
  display: none;
}

.mobile-ticket-card {
  position: relative;
  display: flex;
  flex-direction: column;
  gap: var(--td-comp-margin-s);
  width: 100%;
  padding: 14px 14px 12px;
  overflow: hidden;
  color: var(--td-text-color-primary);
  text-align: left;
  cursor: pointer;
  background: linear-gradient(180deg, var(--td-bg-color-container) 0%, #fbfdff 100%);
  border: 1px solid #d8e1ee;
  border-left: 1px solid #d8e1ee;
  border-radius: var(--td-radius-medium);
  box-shadow: 0 6px 18px rgba(25, 35, 50, 0.08);
}

.mobile-ticket-card__top,
.mobile-ticket-card__meta {
  display: flex;
  gap: var(--td-comp-margin-s);
  align-items: center;
  justify-content: space-between;
}

.mobile-ticket-card__top {
  padding-bottom: 10px;
  border-bottom: 1px solid #edf1f6;
}

.mobile-ticket-card__title {
  overflow-wrap: anywhere;
  color: var(--td-text-color-primary);
  font: var(--td-font-body-large);
  font-weight: 600;
}

.mobile-ticket-card__meta {
  color: var(--td-text-color-secondary);
  font: var(--td-font-body-small);
}

.ticket-pagination {
  display: flex;
  justify-content: flex-end;
}

.ticket-create-form {
  display: grid;
  gap: var(--td-comp-margin-s);
}

.ticket-attachment-box {
  display: grid;
  gap: var(--td-comp-margin-s);
}

.upload-trigger {
  display: inline-flex;
  width: fit-content;
  cursor: pointer;

  input {
    display: none;
  }

  span {
    display: inline-flex;
    gap: var(--td-comp-margin-xs);
    align-items: center;
    padding: var(--td-comp-paddingTB-s) var(--td-comp-paddingLR-m);
    color: var(--td-brand-color);
    background: var(--td-brand-color-light);
    border: thin solid var(--td-brand-color-light);
    border-radius: var(--td-radius-medium);
  }
}

.upload-tip {
  color: var(--td-text-color-placeholder);
  font: var(--td-font-body-small);
}

.attachment-list {
  display: flex;
  flex-wrap: wrap;
  gap: var(--td-comp-margin-s);
}

.attachment-thumb {
  position: relative;
  width: var(--td-comp-size-xxxxl);
  height: var(--td-comp-size-xxxxl);
  padding: 0;
  overflow: hidden;
  cursor: pointer;
  background: var(--td-bg-color-container);
  border: thin solid var(--td-border-color);
  border-radius: var(--td-radius-medium);

  img {
    width: 100%;
    height: 100%;
    object-fit: cover;
  }

  span {
    position: absolute;
    right: 0;
    bottom: 0;
    left: 0;
    color: var(--td-text-color-anti);
    font: var(--td-font-body-small);
    text-align: center;
    background: var(--td-mask-active);
  }
}

.preview-image {
  display: block;
  width: 100%;
}

@media (max-width: 48rem) {
  .client-tickets {
    padding: var(--td-comp-paddingTB-m) var(--td-comp-paddingLR-s);
  }

  .ticket-filter-bar {
    grid-template-columns: 1fr;
  }

  .ticket-filter-actions,
  .ticket-pagination {
    justify-content: flex-start;
  }

  .ticket-filter-actions :deep(.t-button) {
    flex: 1 1 auto;
  }

  .ticket-table-shell {
    display: none;
  }

  .mobile-ticket-list {
    display: grid;
    gap: 12px;
  }

  .ticket-pagination {
    overflow-x: auto;
  }
}
</style>
