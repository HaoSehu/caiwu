<template>
  <section class="console-tab-section">
    <div class="console-toolbar">
      <span class="console-toolbar__title">历史跟踪</span>
      <t-button theme="primary" @click="openCreateDialog">新增记录</t-button>
    </div>
    <t-loading :loading="state.loading" size="small">
      <div v-if="state.list.length" class="tracking-list">
        <article v-for="record in state.list" :key="record.id" class="tracking-item">
          <div class="tracking-item__head">
            <span class="tracking-item__time">{{ formatDateTime(record.tracked_at) }}</span>
            <span class="tracking-item__operator">操作人：{{ fieldValue(record.operator_name) }}</span>
            <t-button
              variant="text"
              theme="danger"
              size="small"
              class="tracking-item__delete"
              @click="handleDelete(record)"
              >删除</t-button
            >
          </div>
          <p class="tracking-item__content">{{ record.content }}</p>
          <div v-if="recordImages(record).length" class="tracking-item__images">
            <button
              v-for="image in recordImages(record)"
              :key="image.url"
              type="button"
              class="tracking-thumb"
              @click="openPreview(image.url)"
            >
              <img :src="image.url" :alt="image.name || '跟踪截图'" />
            </button>
          </div>
        </article>
      </div>
      <t-empty v-else :description="state.loading ? '加载中…' : '暂无历史跟踪记录'" />
    </t-loading>
    <div v-if="state.total > 0" class="console-pagination">
      <t-pagination
        :current="state.page"
        :page-size="state.pageSize"
        :total="state.total"
        :page-size-options="[10, 20, 50]"
        show-jumper
        @change="handlePageChange"
      />
    </div>

    <t-dialog
      v-model:visible="createVisible"
      header="新增跟踪记录"
      width="620px"
      :confirm-btn="{ content: '确定', loading: submitting }"
      @cancel="createVisible = false"
      @confirm="handleSubmit"
    >
      <t-form ref="formRef" :data="form" :rules="rules" label-align="top" class="dialog-form">
        <t-form-item label="记录时间" name="tracked_at">
          <t-date-picker
            v-model="form.tracked_at"
            enable-time-picker
            clearable
            format="YYYY-MM-DD HH:mm:ss"
            value-type="YYYY-MM-DD HH:mm:ss"
            placeholder="默认当前时间"
            style="width: 100%"
          />
        </t-form-item>
        <t-form-item label="留言" name="content">
          <t-textarea
            v-model="form.content"
            :maxlength="2000"
            :autosize="{ minRows: 4, maxRows: 8 }"
            placeholder="记录跟进过程、沟通结论或待办事项"
          />
        </t-form-item>
        <t-form-item label="图片">
          <t-upload
            v-model="uploadFiles"
            theme="image"
            accept="image/jpeg,image/png,image/webp"
            multiple
            :max="MAX_IMAGE_COUNT"
            :auto-upload="true"
            :size-limit="{ size: 5, unit: 'MB', message: '单张图片不能超过 5MB' }"
            :request-method="handleUpload"
            :before-upload="beforeUpload"
            :on-remove="handleUploadRemove"
            :on-preview="handleUploadPreview"
          />
        </t-form-item>
      </t-form>
    </t-dialog>

    <t-dialog v-model:visible="previewVisible" header="图片预览" width="720px" :footer="false">
      <img v-if="previewUrl" :src="previewUrl" class="tracking-preview" alt="跟踪截图预览" />
    </t-dialog>
  </section>
</template>
<script setup lang="ts">
import type { FormInstanceFunctions, FormRule, PageInfo } from 'tdesign-vue-next';
import { DialogPlugin } from 'tdesign-vue-next/es/dialog/plugin';
import { MessagePlugin } from 'tdesign-vue-next/es/message/plugin';
import { onMounted, reactive, ref } from 'vue';

import type { PageParams } from '@/api/user';
import { userApi } from '@/api/user';
import { fieldValue, formatDateTime } from '@/utils/format';
import { required } from '@/utils/formRules';
import { errorMessage } from '@/utils/userMessage';

type Row = Record<string, any>;

interface TrackingImage {
  url: string;
  name?: string;
}

interface UploadFileLike {
  name?: string;
  size?: number;
  type?: string;
  rawFile?: File;
  file?: File;
  response?: { url?: string; filename?: string; path?: string; [key: string]: unknown };
  url?: string;
  path?: string;
  [key: string]: unknown;
}

const props = defineProps<{ userId: number | string; serviceId: number | string }>();

/** 与后端 AdminServiceTrackingService::MAX_IMAGE_COUNT 保持一致。 */
const MAX_IMAGE_COUNT = 9;
const IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

const state = reactive({
  loading: false,
  list: [] as Row[],
  total: 0,
  page: 1,
  pageSize: 10,
});

const createVisible = ref(false);
const submitting = ref(false);
const uploadFiles = ref<UploadFileLike[]>([]);
const previewVisible = ref(false);
const previewUrl = ref('');
const formRef = ref<FormInstanceFunctions>();
const form = reactive({
  tracked_at: '',
  content: '',
});
const rules: Record<string, FormRule[]> = {
  tracked_at: [required('请选择记录时间')],
  content: [required('请填写留言')],
};

/** 列表请求序号，用于丢弃过期响应 */
let requestSeq = 0;

/** 记录图片列表做兜底过滤，兼容接口返回空值。 */
function recordImages(record: Row): TrackingImage[] {
  const images = record?.images;
  if (!Array.isArray(images)) return [];
  return images
    .map((image: Row) => ({ url: String(image?.url || ''), name: String(image?.name || '') }))
    .filter((image: TrackingImage) => Boolean(image.url));
}

async function load() {
  const currentSeq = ++requestSeq;
  state.loading = true;
  try {
    const params: PageParams = { page: state.page, page_size: state.pageSize };
    const response = await userApi.serviceTrackingRecords(props.userId, props.serviceId, params);
    if (currentSeq !== requestSeq) return;
    state.list = Array.isArray(response.list) ? response.list : [];
    state.total = Number(response.total || 0);
    state.page = Number(response.page || state.page);
    state.pageSize = Number(response.page_size || state.pageSize);
  } catch (error) {
    if (currentSeq !== requestSeq) return;
    MessagePlugin.error(errorMessage(error, '加载历史跟踪失败'));
  } finally {
    if (currentSeq === requestSeq) state.loading = false;
  }
}

function handlePageChange(pageInfo: PageInfo) {
  state.page = pageInfo.current;
  state.pageSize = pageInfo.pageSize;
  load();
}

/** 记录时间默认当前时刻，管理员可再调整。 */
function currentDateTime() {
  const now = new Date();
  const pad = (value: number) => String(value).padStart(2, '0');
  return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())} ${pad(now.getHours())}:${pad(
    now.getMinutes(),
  )}:${pad(now.getSeconds())}`;
}

function openCreateDialog() {
  form.tracked_at = currentDateTime();
  form.content = '';
  uploadFiles.value = [];
  createVisible.value = true;
  formRef.value?.clearValidate?.();
}

/** 图片数量与类型前置校验，避免无效文件占用上传额度。 */
function beforeUpload(file: UploadFileLike) {
  if (uploadFiles.value.length >= MAX_IMAGE_COUNT) {
    MessagePlugin.warning(`最多上传 ${MAX_IMAGE_COUNT} 张图片`);
    return false;
  }
  const type = String(file?.type || '');
  if (type && !IMAGE_TYPES.includes(type)) {
    MessagePlugin.warning('仅支持 jpg、png、webp 图片');
    return false;
  }
  return true;
}

async function handleUpload(files: UploadFileLike | UploadFileLike[]) {
  const file = Array.isArray(files) ? files[0] : files;
  const rawFile = (file?.rawFile || file?.file || file) as File;
  const formData = new FormData();
  formData.append('file', rawFile);
  try {
    const response = await userApi.serviceTrackingUpload(props.userId, props.serviceId, formData);
    const image = response?.image || {};
    return {
      status: 'success',
      response: { ...image, url: image.url || undefined },
    };
  } catch (error) {
    return { status: 'fail', error: errorMessage(error, '上传失败'), response: {} };
  }
}

function handleUploadRemove({ file, index }: { file: UploadFileLike; index: number }) {
  const path = file?.response?.path || file?.path;
  if (path) {
    uploadFiles.value = uploadFiles.value.filter((item) => (item.response?.path || item.path) !== path);
    return;
  }
  uploadFiles.value.splice(index, 1);
}

function handleUploadPreview({ file }: { file: UploadFileLike }) {
  const url = file?.response?.url || file?.url;
  if (!url) return;
  previewUrl.value = String(url);
  previewVisible.value = true;
}

function openPreview(url: string) {
  previewUrl.value = url;
  previewVisible.value = true;
}

/** 已上传成功的图片按后端要求的 { url, name } 结构提交。 */
function resolveImages(): TrackingImage[] {
  return uploadFiles.value
    .map((file) => {
      const url = String(file?.response?.url || file?.url || '');
      const name = String(file?.response?.filename || file?.name || '');
      return url ? { url, ...(name ? { name } : {}) } : null;
    })
    .filter((image): image is TrackingImage => Boolean(image));
}

async function handleSubmit() {
  const result = await formRef.value?.validate?.();
  if (result !== true && result !== undefined) return;
  submitting.value = true;
  try {
    await userApi.serviceTrackingStore(props.userId, props.serviceId, {
      tracked_at: form.tracked_at,
      content: form.content.trim(),
      ...(resolveImages().length ? { images: resolveImages() } : {}),
    });
    MessagePlugin.success('跟踪记录已新增');
    createVisible.value = false;
    state.page = 1;
    await load();
  } catch (error) {
    MessagePlugin.error(errorMessage(error, '新增跟踪记录失败'));
  } finally {
    submitting.value = false;
  }
}

/** 删除跟踪记录：备注类数据不可恢复，必须二次确认。 */
function handleDelete(record: Row) {
  const recordId = Number(record?.id || 0);
  if (!recordId) return;
  const dialog = DialogPlugin.confirm({
    header: '删除跟踪记录',
    body: '删除后该条备注与图片不可恢复，确认继续吗？',
    confirmBtn: { content: '确定删除', theme: 'danger' },
    cancelBtn: '取消',
    theme: 'warning',
    async onConfirm() {
      try {
        await userApi.serviceTrackingDelete(props.userId, props.serviceId, recordId);
        MessagePlugin.success('跟踪记录已删除');
        dialog.hide();
        await load();
      } catch (error) {
        MessagePlugin.error(errorMessage(error, '删除跟踪记录失败'));
      }
    },
  });
}

defineExpose({ reload: load });

onMounted(load);
</script>
