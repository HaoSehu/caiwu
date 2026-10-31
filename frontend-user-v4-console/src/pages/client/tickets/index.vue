<template>
  <section class="client-tickets">
    <h1 class="sr-only">工单支持</h1>
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
            <template #icon><add-icon /></template>
            提交工单
          </t-button>
        </div>
      </div>
    </t-card>

    <section class="ticket-list-card">
      <data-state
        :loading="loading"
        :empty="!loading && !loadError && !list.length"
        :error="!loading && loadError"
        :error-text="loadErrorText"
        :description="hasActiveFilters ? '未找到匹配结果' : '暂无工单记录'"
        @retry="loadTickets"
      >
        <!-- W-17：无筛选时给「提交工单」，命中筛选时给「清除筛选条件」——两种空态都要有下一步动作。 -->
        <template v-if="hasActiveFilters" #empty-action>
          <t-button theme="primary" variant="outline" @click="resetFilters">清除筛选条件</t-button>
        </template>
        <template v-else #empty-action>
          <t-button theme="primary" @click="openCreateDialog">提交工单</t-button>
        </template>
        <div v-if="isDesktop" class="ticket-table-shell">
          <t-table row-key="id" :data="list" :columns="columns" :pagination="null" hover>
            <template #subject="{ row }">
              <router-link class="ticket-link" :to="`/client/tickets/${row.id}`">
                #{{ row.id }} {{ row.subject || '--' }}
              </router-link>
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

        <div v-else class="mobile-ticket-list">
          <button v-for="row in list" :key="row.id" type="button" class="mobile-ticket-card" @click="openDetail(row)">
            <span class="mobile-ticket-card__top">
              <strong>#{{ row.id }}</strong>
              <t-tag :theme="resolveTicketTagTheme(row.status)" variant="light">{{
                resolveTicketStatusLabel(row.status)
              }}</t-tag>
            </span>
            <span class="mobile-ticket-card__title">{{ row.subject || '--' }}</span>
            <span class="mobile-ticket-card__meta">
              <span>优先级：{{ resolvePriorityLabel(row.priority) }}</span>
              <span>{{ formatTicketTime(row.updated_at) }}</span>
            </span>
          </button>
        </div>
      </data-state>
    </section>

    <div v-if="total > 0" class="ticket-pagination">
      <t-pagination
        v-model="filters.page"
        v-model:page-size="filters.page_size"
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
      :confirm-btn="{ content: '确定', loading: creating, disabled: !createForm.subject.trim() || uploading }"
      cancel-btn="取消"
      @confirm="submitTicket"
      @close="closeCreateDialog"
    >
      <t-form label-align="top" class="ticket-create-form">
        <t-form-item label="问题分类" required-mark>
          <t-select v-model="createForm.department">
            <t-option
              v-for="item in TICKET_DEPARTMENT_OPTIONS"
              :key="item.value"
              :label="item.label"
              :value="item.value"
            />
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
            <t-option
              v-for="item in TICKET_PRIORITY_OPTIONS"
              :key="item.value"
              :label="item.label"
              :value="item.value"
            />
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
        <t-form-item label="上传附件">
          <div class="ticket-attachment-box">
            <label class="upload-trigger" :class="{ 'is-uploading': uploading }" :aria-disabled="uploading">
              <input
                type="file"
                accept=".jpg,.jpeg,.png,.webp"
                multiple
                :disabled="uploading || uploadLimitReached"
                @change="handleCreateUpload"
              />
              <span><add-icon /> {{ uploading ? '上传中…' : '上传图片' }}</span>
            </label>
            <span class="upload-tip">支持 jpg/png/webp，最多 9 张，单张不超过 5MB</span>
            <!--
              PR-38：key 用内容地址而不是 id。上传接口返回的 payload 里 id 可能缺省，原来回落到
              数组下标，删掉中间一张后剩余项的 key 全部前移，Vue 会复用错位的 img 节点。
            -->
            <!--
              T-02：缩略图原先是一个 <button>，里面再套一个 span[role="button"] 当删除键——
              button 内嵌可交互元素是非法嵌套，读屏会把两层都念成按钮，Tab 顺序也会卡在里层。
              改成兄弟结构：预览一张、删除一个，各自是独立的真 button。
            -->
            <div v-if="uploadFiles.length" class="attachment-list">
              <div
                v-for="(file, index) in uploadFiles"
                :key="file.path || file.url || String(index)"
                class="attachment-thumb"
              >
                <button
                  type="button"
                  class="attachment-thumb__preview"
                  aria-label="预览该附件"
                  @click="previewUploadFile(file)"
                >
                  <img :src="file.url || file.path" alt="附件" loading="lazy" decoding="async" />
                </button>
                <button
                  type="button"
                  class="attachment-thumb__remove"
                  aria-label="删除该附件"
                  @click="removeUploadFile(index)"
                >
                  删除
                </button>
              </div>
            </div>
          </div>
        </t-form-item>
      </t-form>
    </t-dialog>

    <t-dialog
      v-model:visible="previewVisible"
      header="附件预览"
      width="min(45rem, calc(100vw - var(--td-comp-margin-xl)))"
    >
      <img v-if="previewUrl" :src="previewUrl" class="preview-image" alt="附件预览" loading="lazy" decoding="async" />
    </t-dialog>
  </section>
</template>
<script setup lang="ts">
import DataState from '@caiwu/shared/user-v3/components/DataState.vue';
import { AddIcon } from 'tdesign-icons-vue-next';
import type { PrimaryTableCol } from 'tdesign-vue-next';
import { MessagePlugin } from 'tdesign-vue-next/es/message/plugin';
import { computed, onMounted } from 'vue';

import { useDeviceLayout } from '@/composables/useDeviceLayout';
import {
  formatTicketTime,
  resolvePriorityLabel,
  resolvePriorityTheme,
  resolveTicketStatusLabel,
  resolveTicketTagTheme,
  summarizeUploadFailures,
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
  uploadLimitReached,
  list,
  total,
  filters,
  loadError,
  loadErrorText,
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

// 表格与移动卡片只渲染一套，避免两套模板同时存在于 DOM
const { isDesktop } = useDeviceLayout();

// 是否存在生效筛选：空态区分“暂无数据”与“未找到匹配结果”（DESIGN.md §4.3）
const hasActiveFilters = computed(() => filters.keyword.trim() !== '' || filters.status !== undefined);

const columns: PrimaryTableCol[] = [
  { colKey: 'subject', title: '标题', minWidth: '18rem' },
  { colKey: 'status', title: '状态', width: '8rem' },
  { colKey: 'priority', title: '优先级', width: '8rem' },
  { colKey: 'updated_at', title: '更新时间', minWidth: '12rem' },
  { colKey: 'operation', title: '操作', width: '8rem', align: 'right' },
];

// T-04：多选上传逐张串行，但提示按整批汇总一次（原来每张一个 toast，选几张刷几条）。
// 失败原因按「同一句话只报一次」去重：选 3 张超大图不该刷三遍「单张图片不能超过 5MB」。
async function handleCreateUpload(event: Event) {
  const input = event.target as HTMLInputElement;
  const files = Array.from(input.files || []);
  let succeeded = 0;
  const failures: string[] = [];
  for (const file of files) {
    const result = await uploadTicketImage(file, true);
    if (result.ok) succeeded += 1;
    else failures.push(result.reason || '图片上传失败');
  }
  input.value = '';
  if (failures.length === 0) {
    if (succeeded > 0) MessagePlugin.success(`已上传 ${succeeded} 张图片`);
    return;
  }
  MessagePlugin.warning(
    succeeded > 0 ? `已上传 ${succeeded} 张，${summarizeUploadFailures(failures)}` : summarizeUploadFailures(failures),
  );
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
  // padding 由 Starter 布局层统一提供
}

.ticket-filter-card {
  background: var(--td-bg-color-container);
  border: thin solid var(--td-component-stroke);
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

// 手机形态才切到卡片列表：模板已用 v-if="isDesktop" / v-else 二选一渲染，
// 这里不能再写 display: none 兜底——isDesktop 的断点是 992，而下面的媒体查询只到 @screen-mobile-max，
// 平板竖屏（48rem 到 @screen-sm-max 之间）会渲染卡片列表却命中不了打开规则，整段内容不可见。
.mobile-ticket-list {
  display: grid;
  gap: var(--td-comp-margin-m);
}

.mobile-ticket-card {
  position: relative;
  display: flex;
  flex-direction: column;
  gap: var(--td-comp-margin-s);
  width: 100%;
  padding: 0.875rem 0.875rem 0.75rem;
  overflow: hidden;
  color: var(--td-text-color-primary);
  text-align: left;
  cursor: pointer;
  background: var(--td-bg-color-container);
  border: thin solid var(--td-component-stroke);
  border-radius: var(--td-radius-medium);
  box-shadow: var(--td-shadow-1);
}

.mobile-ticket-card__top,
.mobile-ticket-card__meta {
  display: flex;
  gap: var(--td-comp-margin-s);
  align-items: center;
  justify-content: space-between;
}

.mobile-ticket-card__top {
  padding-bottom: 0.625rem;
  border-bottom: thin solid var(--td-component-stroke);
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

  // T-02：附件选择原来用 display:none 隐掉原生 input，键盘用户完全按不到它（display:none 的元素
  // 不可聚焦，label 只响应鼠标点击）。改成视觉隐藏但仍留在无障碍树里，焦点移到 input 时由
  // :focus-within 把描边画到可见的 span 上。
  input {
    position: absolute;
    width: 0.0625rem;
    height: 0.0625rem;
    padding: 0;
    overflow: hidden;
    white-space: nowrap;
    border: 0;
    clip-path: inset(50%);
  }

  &:focus-within span {
    outline: 0.125rem solid var(--td-brand-color);
    outline-offset: 0.125rem;
  }

  &.is-uploading {
    cursor: progress;

    span {
      color: var(--td-text-color-placeholder);
      background: var(--td-bg-color-container-hover);
      border-color: var(--td-component-stroke);
    }
  }

  // T-06：边框原先取 brand-color-light，与自己的背景同值，等于没有边框——
  // 上传区最容易靠这圈虚线被认出来，改成 dashed + brand 实色。
  span {
    display: inline-flex;
    gap: var(--td-comp-margin-xs);
    align-items: center;
    padding: var(--td-comp-paddingTB-s) var(--td-comp-paddingLR-m);
    color: var(--td-brand-color);
    background: var(--td-brand-color-light);
    border: thin dashed var(--td-brand-color);
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

// T-02：外层只是定位容器，预览与删除是两个并列的真按钮
.attachment-thumb {
  position: relative;
  width: var(--td-comp-size-xxxxl);
  height: var(--td-comp-size-xxxxl);
  overflow: hidden;
  background: var(--td-bg-color-container);
  border: thin solid var(--td-component-stroke);
  border-radius: var(--td-radius-medium);

  &__preview {
    display: block;
    width: 100%;
    height: 100%;
    padding: 0;
    cursor: pointer;
    background: none;
    border: 0;

    img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }
  }

  &__remove {
    position: absolute;
    right: 0;
    bottom: 0;
    left: 0;
    min-height: var(--td-comp-size-xxs);
    padding: 0;
    color: var(--td-text-color-anti);
    font: var(--td-font-body-small);
    text-align: center;
    cursor: pointer;
    background: var(--td-mask-active);
    border: 0;
  }

  &:focus-within {
    outline: 0.125rem solid var(--td-brand-color);
    outline-offset: 0.125rem;
  }
}

.preview-image {
  display: block;
  width: 100%;
}

@media (max-width: @screen-mobile-max) {
  .ticket-filter-bar {
    grid-template-columns: repeat(2, minmax(0, 1fr));

    > :first-child {
      grid-column: 1 / -1;
    }
  }

  .ticket-filter-actions {
    justify-content: flex-start;
  }

  .ticket-filter-actions :deep(.t-button) {
    width: 100%;
    min-height: var(--td-comp-size-l);
  }

  .ticket-pagination {
    justify-content: flex-start;
    overflow-x: auto;
  }

  .ticket-table-shell {
    display: none;
  }
}
</style>
