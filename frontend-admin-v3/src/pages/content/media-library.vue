<template>
  <div class="media-library-page">
    <t-card :bordered="false">
      <div class="media-toolbar">
        <t-input
          v-model="filters.keyword"
          clearable
          placeholder="按文件名搜索"
          @enter="handleSearch"
          @clear="handleSearch"
        >
          <template #suffix-icon><search-icon /></template>
        </t-input>
        <t-select v-model="filters.type" clearable placeholder="全部类型" @change="handleFilterChange">
          <t-option v-for="item in typeOptions" :key="item.value" :label="item.label" :value="item.value" />
        </t-select>
        <t-button theme="primary" @click="handleSearch">查询</t-button>
        <t-button theme="default" variant="base" @click="resetFilters">重置</t-button>
        <t-button variant="outline" :loading="reindexing" @click="handleReindex">
          <template #icon><refresh-icon /></template>
          重新获取
        </t-button>
        <t-button theme="primary" variant="outline" @click="openUploadDialog">
          <template #icon><upload-icon /></template>
          上传媒体
        </t-button>
      </div>
    </t-card>

    <t-card :bordered="false" class="media-library-card">
      <div v-if="selectedRowKeys.length" class="media-batch-bar">
        <span>已选 {{ selectedRowKeys.length }} 个媒体文件</span>
        <t-space size="small">
          <t-button variant="outline" @click="openMigrateDialog()">批量转存</t-button>
          <t-button variant="text" @click="clearSelection">清空选择</t-button>
        </t-space>
      </div>
      <t-table
        row-key="id"
        :loading="loading"
        :data="items"
        :columns="columns"
        :pagination="pagination"
        :hover="true"
        size="medium"
        table-layout="auto"
        :selected-row-keys="selectedRowKeys"
        aria-label="媒体库文件列表"
        @page-change="handlePageChange"
        @select-change="handleSelectChange"
      >
        <template #empty>
          <t-empty :description="loading ? '加载中…' : hasActiveFilters ? '未找到匹配结果' : '暂无数据'" />
        </template>
        <template #preview="{ row }">
          <div class="media-preview">
            <video
              v-if="isVideo(row)"
              class="media-preview__asset"
              :src="String(row.url || '')"
              muted
              preload="metadata"
              playsinline
            ></video>
            <img
              v-else
              class="media-preview__asset"
              :src="String(row.url || '')"
              :alt="String(row.filename || 'media')"
              loading="lazy"
            />
          </div>
        </template>

        <template #filename="{ row }">
          <div class="media-cell">
            <strong>{{ row.filename || '-' }}</strong>
            <span class="media-cell__sub">{{ row.path || '-' }}</span>
          </div>
        </template>

        <template #type="{ row }">
          <t-tag :theme="isVideo(row) ? 'warning' : 'primary'" variant="light">
            {{ isVideo(row) ? '视频' : '图片' }}
          </t-tag>
        </template>

        <template #disk="{ row }">
          <t-tag :theme="isRemoteDisk(row) ? 'primary' : undefined" variant="light">
            {{ diskLabel(row.disk) }}
          </t-tag>
        </template>

        <template #meta="{ row }">
          <div class="media-cell">
            <span>{{ formatFileSize(row.size) }}</span>
            <span class="media-cell__sub">{{ formatDimensions(row) }}</span>
          </div>
        </template>

        <template #actions="{ row }">
          <t-space size="8">
            <t-button variant="text" @click="copyText(String(row.url || ''))">复制 URL</t-button>
            <t-button variant="text" @click="copyText(String(row.path || ''))">复制路径</t-button>
            <t-button variant="text" @click="openMigrateDialog(row)">转存</t-button>
            <t-popconfirm
              content="删除后将无法恢复，确认继续？"
              :confirm-btn="{ theme: 'danger', content: '删除' }"
              @confirm="removeMedia(row)"
            >
              <t-button
                variant="text"
                theme="danger"
                :disabled="!canDelete(row)"
                :loading="deletingId === String(row.id || '')"
                >删除</t-button
              >
            </t-popconfirm>
          </t-space>
        </template>
      </t-table>

      <div v-if="!loading && !items.length" class="media-empty">暂无媒体文件，先上传一批图片或视频。</div>
    </t-card>

    <!-- 上传对话框：两步制（第一步选存储区 → 第二步选文件）+ 批量 + 可见进度 -->
    <t-dialog
      v-model:visible="uploadDialogVisible"
      header="上传媒体"
      width="min(640px, calc(100vw - 32px))"
      :close-on-overlay-click="!uploadRunning"
      :close-on-esc-keydown="!uploadRunning"
      :close-btn="!uploadRunning"
    >
      <div class="media-upload-form">
        <!-- 第一步：选择存储区（必经，未选时文件选择不可用） -->
        <div class="media-upload-form__row">
          <label class="media-upload-form__label">存储区</label>
          <t-select
            v-model="uploadTarget"
            class="media-upload-form__control"
            placeholder="请选择存储区"
            aria-label="上传存储区"
            :disabled="uploadRunning"
          >
            <t-option v-for="item in driverOptions" :key="item.value" :label="item.label" :value="item.value" />
          </t-select>
        </div>

        <!-- 第二步：选择文件（multiple 批量；未选存储区时禁用） -->
        <div class="media-upload-form__row">
          <label class="media-upload-form__label">文件</label>
          <div class="media-upload-form__control">
            <t-button variant="outline" :disabled="!uploadTarget || uploadRunning" @click="pickUploadFiles">
              <template #icon><upload-icon /></template>
              选择文件（可多选）
            </t-button>
            <p v-if="!uploadTarget" class="media-upload-form__hint">请先选择存储区，再选择要上传的文件。</p>
          </div>
        </div>

        <!-- 已选文件列表：每文件状态可分辨（pending/uploading/success/failed） -->
        <div v-if="uploadItems.length" class="media-upload-files">
          <div
            v-for="(item, index) in uploadItems"
            :key="index"
            class="media-upload-files__item"
            :class="{ 'is-failed': item.status === 'failed' }"
          >
            <div class="media-upload-files__head">
              <span class="media-upload-files__name" :title="item.name">{{ item.name }}</span>
              <span class="media-upload-files__status" :class="`is-${item.status}`">{{
                uploadStatusText(item.status)
              }}</span>
            </div>
            <t-progress
              v-if="item.status === 'uploading'"
              :percentage="item.percent"
              size="small"
              aria-label="单文件上传进度"
            />
            <p v-if="item.status === 'failed' && item.error" class="media-upload-files__error">{{ item.error }}</p>
          </div>
          <p class="media-upload-files__summary">共 {{ uploadItems.length }} 个，已结束 {{ uploadSettledCount }} 个</p>
        </div>
      </div>
      <template #footer>
        <t-space size="small">
          <t-button variant="outline" :disabled="uploadRunning" @click="closeUploadDialog">取消</t-button>
          <t-button
            v-if="failedUploadCount > 0"
            variant="outline"
            theme="danger"
            :disabled="uploadRunning"
            @click="retryFailedUploads"
          >
            重试失败文件（{{ failedUploadCount }}）
          </t-button>
          <t-button
            theme="primary"
            :loading="uploadRunning"
            :disabled="!uploadTarget || !uploadItems.length"
            @click="submitUpload"
          >
            {{ uploadRunning ? '上传中…' : '开始上传' }}
          </t-button>
        </t-space>
      </template>
    </t-dialog>

    <input
      ref="uploadFileInputRef"
      class="media-upload-input"
      type="file"
      multiple
      accept="image/jpeg,image/png,image/webp,video/mp4,video/webm,video/ogg,video/quicktime,video/x-m4v"
      @change="handleUploadFileChange"
    />

    <!-- 转存对话框：目标存储区必选 + 删除源文件开关（默认保留） -->
    <t-dialog
      v-model:visible="migrateDialogVisible"
      header="媒体转存"
      width="min(520px, calc(100vw - 32px))"
      :close-on-overlay-click="!migrating"
      :close-on-esc-keydown="!migrating"
    >
      <div class="media-migrate-form">
        <p class="media-migrate-form__summary">将 {{ migrateTargetCount }} 个媒体文件转存到指定存储区。</p>
        <div class="media-migrate-form__row">
          <label class="media-migrate-form__label">目标存储区</label>
          <t-select
            v-model="migrateForm.driver"
            class="media-migrate-form__control"
            placeholder="请选择目标存储区"
            aria-label="目标存储区"
          >
            <t-option v-for="item in migrateDriverOptions" :key="item.value" :label="item.label" :value="item.value" />
          </t-select>
        </div>
        <div class="media-migrate-form__row">
          <label class="media-migrate-form__label">删除源文件</label>
          <t-switch v-model="migrateForm.deleteSource" :disabled="migrating" />
          <span class="media-migrate-form__hint">
            {{ migrateForm.deleteSource ? '转存成功后删除源存储区文件' : '保留源存储区文件（默认）' }}
          </span>
        </div>
      </div>
      <template #footer>
        <t-space size="small">
          <t-button variant="outline" :disabled="migrating" @click="migrateDialogVisible = false">取消</t-button>
          <t-button theme="primary" :loading="migrating" :disabled="!migrateForm.driver" @click="submitMigrate">
            开始转存
          </t-button>
        </t-space>
      </template>
    </t-dialog>

    <!-- 转存进度对话框：轮询期间展示，不可关闭 -->
    <t-dialog
      v-model:visible="migrateProgressDialogVisible"
      header="转存进行中"
      width="min(440px, calc(100vw - 32px))"
      :close-on-overlay-click="false"
      :close-on-esc-keydown="false"
      :footer="false"
      :close-btn="false"
    >
      <div class="media-migrate-progress">
        <p>正在转存 {{ migrateProgressSummary.total }} 个文件，请勿关闭页面…</p>
        <p class="media-migrate-progress__sub">
          已完成 {{ migrateProgressSummary.done }} / {{ migrateProgressSummary.total }}
        </p>
      </div>
    </t-dialog>

    <!-- 结果反馈对话框：成功/失败/跳过统计 + 失败明细 -->
    <t-dialog v-model:visible="migrateResultDialogVisible" header="转存结果" width="min(560px, calc(100vw - 32px))">
      <div v-if="migrateResult" class="media-migrate-result">
        <p class="media-migrate-result__summary">
          <template v-if="migrateResultSummary.success">转存完成。</template>
          <template v-else>转存结束，存在失败项。</template>
          共 {{ migrateResultSummary.total }} 个，成功 {{ migrateResultSummary.done }} 个，失败
          {{ migrateResultSummary.failed }} 个，跳过 {{ migrateResultSummary.skipped }} 个。
        </p>
        <p v-if="migrateResultSummary.skipped > 0" class="media-migrate-result__hint">
          跳过的文件已在目标存储区，无需重复转存。
        </p>
        <div v-if="migrateResult.failures && migrateResult.failures.length" class="media-migrate-result__failures">
          <p class="media-migrate-result__failures-title">失败明细：</p>
          <ul>
            <li v-for="(failure, index) in migrateResult.failures" :key="index">
              #{{ failure.media_file_id }}（{{ failure.stage || '未知阶段' }}）：{{ failure.message || '未知原因' }}
            </li>
          </ul>
          <p class="media-migrate-result__hint">可稍后对失败文件重新发起转存。</p>
        </div>
        <p v-if="migrateTimedOut" class="media-migrate-result__hint">
          轮询超时：转存仍在后台执行，稍后刷新列表查看最终结果。
        </p>
      </div>
      <template #footer>
        <t-button theme="primary" @click="migrateResultDialogVisible = false">知道了</t-button>
      </template>
    </t-dialog>
  </div>
</template>
<script setup lang="ts">
import './media-library.less';

import { RefreshIcon, SearchIcon, UploadIcon } from 'tdesign-icons-vue-next';
import type { PageInfo, PrimaryTableCol } from 'tdesign-vue-next';
import { DialogPlugin } from 'tdesign-vue-next/es/dialog/plugin';
import { MessagePlugin } from 'tdesign-vue-next/es/message/plugin';
import { computed, onMounted, onUnmounted, reactive, ref } from 'vue';

import type { MediaFileRecord, MediaMigrationProgress, MediaStorageDriver } from '@/api/admin';
import { adminApi } from '@/api/admin';
import { copyToClipboard } from '@/utils/format';
import { errorMessage } from '@/utils/userMessage';

defineOptions({ name: 'AdminMediaLibrary' });

const PAGE_SIZE = 24;

const typeOptions = [
  { label: '全部类型', value: '' },
  { label: '图片', value: 'image' },
  { label: '视频', value: 'video' },
];

const loading = ref(false);
/** 列表请求序号，用于丢弃过期响应 */
let requestSeq = 0;
const reindexing = ref(false);
const deletingId = ref('');
const items = ref<MediaFileRecord[]>([]);
const total = ref(0);

// ─── 存储区（渠道）相关状态 ───
/** 上传对话框内第一步选定的目标存储区（两步制：未选时文件选择入口不可用） */
const uploadTarget = ref('');
/** 可用存储区列表（GET /v2/admin/media-storage-drivers） */
const storageDrivers = ref<MediaStorageDriver[]>([]);
/** 上传目标选项（始终保证 local 在列） */
const driverOptions = computed(() => {
  const options = storageDrivers.value
    .filter((driver) => String(driver.slug || '').trim())
    .map((driver) => ({ label: String(driver.name || driver.slug), value: String(driver.slug) }));
  if (!options.some((item) => item.value === 'local')) {
    options.unshift({ label: '本地存储', value: 'local' });
  }
  return options;
});
/** slug → 显示名映射；缺失时回退显示 slug 原值（渠道禁用后残留数据不出错） */
const diskLabelMap = computed(() => {
  const map = new Map<string, string>();
  for (const option of driverOptions.value) map.set(option.value, option.label);
  return map;
});
/** 多选行 keys */
const selectedRowKeys = ref<Array<string | number>>([]);
const filters = reactive({
  keyword: '',
  type: '',
  page: 1,
  page_size: PAGE_SIZE,
});

const columns = computed<PrimaryTableCol<MediaFileRecord>[]>(() => [
  { colKey: 'row-select', type: 'multiple', width: 54, fixed: 'left' },
  { colKey: 'preview', title: '预览', width: 112 },
  { colKey: 'filename', title: '文件', minWidth: 360 },
  { colKey: 'type', title: '类型', width: 96 },
  { colKey: 'disk', title: '存储区', width: 120 },
  { colKey: 'meta', title: '体积 / 尺寸', width: 160 },
  { colKey: 'created_at', title: '上传时间', width: 180 },
  { colKey: 'actions', title: '操作', width: 260, fixed: 'right' },
]);

const pagination = computed(() => ({
  current: filters.page,
  pageSize: filters.page_size,
  total: total.value,
}));

function isVideo(row: MediaFileRecord) {
  return String(row.type || '').toLowerCase() === 'video' || String(row.mime_type || '').startsWith('video/');
}

/** 存储区是否为远程（非 local 都按远程处理，含未知 slug 的残留数据） */
function isRemoteDisk(row: MediaFileRecord) {
  const disk = String(row.disk || 'local');
  return disk !== 'local';
}

/** 存储区显示名：映射 drivers 列表；slug 不在列表（渠道被禁用后的残留）时回退显示原值，不报错 */
function diskLabel(disk: unknown) {
  const slug = String(disk || 'local');
  return diskLabelMap.value.get(slug) || slug;
}

function canDelete(row: MediaFileRecord) {
  const id = String(row.id || '');
  return /^\d+$/.test(id);
}

function formatFileSize(size: unknown) {
  const value = Number(size || 0);
  if (!Number.isFinite(value) || value <= 0) return '0 B';
  if (value >= 1024 * 1024) return `${(value / 1024 / 1024).toFixed(2)} MB`;
  if (value >= 1024) return `${(value / 1024).toFixed(1)} KB`;
  return `${value} B`;
}

function formatDimensions(row: MediaFileRecord) {
  if (isVideo(row)) return '视频文件';
  const width = Number(row.width || 0);
  const height = Number(row.height || 0);
  if (width > 0 && height > 0) return `${width} × ${height}`;
  return '未记录尺寸';
}

/** 拉取可用存储区列表（失败静默：标签回退 slug、上传目标回退本地，不影响主列表） */
async function loadDrivers() {
  try {
    const response = await adminApi.media.drivers();
    storageDrivers.value = response.drivers || [];
  } catch {
    // 存储区接口失败不阻塞页面：driverOptions 兜底 local
  }
}

function handleSelectChange(keys: Array<string | number>) {
  selectedRowKeys.value = keys;
}

function clearSelection() {
  selectedRowKeys.value = [];
}

async function loadMediaList() {
  // 请求竞态防护：连续筛选/翻页时只允许最后一次请求回写结果（docs/FRONTEND.md）
  const currentSeq = ++requestSeq;
  loading.value = true;
  try {
    const response = await adminApi.media.list({
      keyword: filters.keyword.trim() || undefined,
      type: filters.type || undefined,
      page: filters.page,
      page_size: filters.page_size,
    });

    if (currentSeq !== requestSeq) return;
    items.value = response.list || [];
    total.value = Number(response.total || 0);
    // 数据刷新后旧选中可能指向不存在的行，清空多选（与 services 页保持一致）
    selectedRowKeys.value = [];
  } catch (error) {
    if (currentSeq !== requestSeq) return;
    MessagePlugin.error(errorMessage(error, '加载媒体库失败'));
    items.value = [];
    total.value = 0;
  } finally {
    if (currentSeq === requestSeq) loading.value = false;
  }
}

// 空态文案区分「未筛选但无数据」与「筛选后无匹配」（docs/DESIGN.md 术语表）
const hasActiveFilters = computed(() => Boolean(filters.keyword.trim()) || Boolean(filters.type));

function handleSearch() {
  filters.page = 1;
  loadMediaList();
}

function handleFilterChange() {
  filters.page = 1;
  loadMediaList();
}

/** 重置筛选并重新拉取；提交式筛选必须成对提供「查询/重置」（docs/DESIGN.md §5.1） */
function resetFilters() {
  filters.keyword = '';
  filters.type = '';
  handleSearch();
}

function handlePageChange(pageInfo: PageInfo) {
  filters.page = pageInfo.current;
  filters.page_size = pageInfo.pageSize;
  loadMediaList();
}

// ─── 上传（两步制：先选存储区 → 再选文件；串行逐文件上传）───
/** 单文件上传状态 */
interface UploadItem {
  file: File;
  name: string;
  /** pending 等待上传 / uploading 上传中 / success 成功 / failed 失败 */
  status: 'pending' | 'uploading' | 'success' | 'failed';
  /** 单文件进度百分比（axios onUploadProgress） */
  percent: number;
  /** 失败原因（可读文案） */
  error: string;
}

const uploadDialogVisible = ref(false);
/** 是否有上传正在进行（进行中对话框不可关闭） */
const uploadRunning = ref(false);
const uploadFileInputRef = ref<HTMLInputElement | null>(null);
const uploadItems = ref<UploadItem[]>([]);
/** 已结束（成功或失败）的文件数：整体进度（settled 计数） */
const uploadSettledCount = computed(
  () => uploadItems.value.filter((item) => item.status === 'success' || item.status === 'failed').length,
);
/** 失败文件数（「重试失败文件」入口依据） */
const failedUploadCount = computed(() => uploadItems.value.filter((item) => item.status === 'failed').length);
/** 组件卸载标志：卸载后不再回写状态（onUnmounted 清理） */
let uploadAborted = false;

function openUploadDialog() {
  uploadItems.value = [];
  uploadTarget.value = '';
  uploadDialogVisible.value = true;
}

function closeUploadDialog() {
  if (uploadRunning.value) return;
  uploadDialogVisible.value = false;
  uploadItems.value = [];
}

/** 第二步：打开文件选择（未选存储区时按钮禁用，到不了这里） */
function pickUploadFiles() {
  if (!uploadTarget.value || uploadRunning.value) return;
  if (uploadFileInputRef.value) {
    uploadFileInputRef.value.value = '';
    uploadFileInputRef.value.click();
  }
}

function handleUploadFileChange(event: Event) {
  const files = Array.from((event.target as HTMLInputElement).files || []);
  if (!files.length) return;
  // 重新选择文件时整表替换（尚未开始上传才允许）
  if (!uploadRunning.value) {
    uploadItems.value = files.map((file) => ({ file, name: file.name, status: 'pending', percent: 0, error: '' }));
  }
}

function uploadStatusText(status: UploadItem['status']) {
  if (status === 'pending') return '等待上传';
  if (status === 'uploading') return '上传中';
  if (status === 'success') return '已上传';
  return '上传失败';
}

/**
 * 串行逐文件上传：单文件 axios 进度 + 整体 settled 计数；
 * 每文件 promise settle 后才标记终态（禁止「进度结束仍在上传/已完成仍显示进行中」）。
 */
async function submitUpload() {
  if (!uploadTarget.value) {
    MessagePlugin.warning('请先选择存储区');
    return;
  }
  const queue = uploadItems.value.filter((item) => item.status === 'pending' || item.status === 'failed');
  if (!queue.length) {
    MessagePlugin.warning('请先选择要上传的文件');
    return;
  }

  uploadRunning.value = true;
  let succeeded = 0;
  let failed = 0;
  try {
    for (const item of queue) {
      if (uploadAborted) return;
      // 重试的失败文件回到上传中
      item.status = 'uploading';
      item.percent = 0;
      item.error = '';

      const data = new FormData();
      data.append('file', item.file);
      data.append('group', 'content');
      // 上传目标存储区（渠道 slug；后端 driver 参数 nullable string max:24 alpha_dash）
      data.append('driver', uploadTarget.value || 'local');

      try {
        // await settle 后才标记终态；onUploadProgress 只在上传中回写百分比
        await adminApi.media.upload(data, (event) => {
          if (item.status !== 'uploading' || uploadAborted) return;
          if (event.total) {
            item.percent = Math.min(100, Math.round((event.loaded / event.total) * 100));
          }
        });
        if (uploadAborted) return;
        item.status = 'success';
        item.percent = 100;
        succeeded += 1;
      } catch (error) {
        if (uploadAborted) return;
        item.status = 'failed';
        item.error = errorMessage(error, '媒体上传失败');
        failed += 1;
      }
    }
  } finally {
    if (!uploadAborted) {
      uploadRunning.value = false;
      // 全部结束：明确结果反馈 + 失败文件可识别（列表标红 + 错误信息）
      if (failed === 0) {
        MessagePlugin.success(`媒体上传成功（${succeeded} 个文件）`);
      } else {
        MessagePlugin.warning(`上传结束：成功 ${succeeded} 个，失败 ${failed} 个`);
      }
      if (succeeded > 0) {
        filters.page = 1;
        await loadMediaList();
      }
    }
  }
}

/** 重试失败文件：同存储区重新入队（只重传 failed 项） */
function retryFailedUploads() {
  if (uploadRunning.value) return;
  const failedItems = uploadItems.value.filter((item) => item.status === 'failed');
  if (!failedItems.length) {
    MessagePlugin.info('没有需要重试的失败文件');
    return;
  }
  for (const item of failedItems) {
    item.status = 'pending';
    item.percent = 0;
  }
  void submitUpload();
}

async function handleReindex() {
  reindexing.value = true;
  try {
    const response = await adminApi.media.reindex();
    const created = Number(response.created || 0);
    const unrecognized = response.unrecognized || [];

    let msg = `重新获取完成，新增 ${created} 个媒体文件`;
    if (unrecognized.length > 0) {
      msg += `，${unrecognized.length} 个文件因类型不支持被跳过`;
    }
    MessagePlugin.success(msg);

    await loadMediaList();
  } catch (error) {
    MessagePlugin.error(errorMessage(error, '重新获取媒体文件失败'));
  } finally {
    reindexing.value = false;
  }
}

async function removeMedia(row: MediaFileRecord) {
  if (!canDelete(row)) return;

  const id = String(row.id || '');

  // 先检查引用
  let refs: string[] = [];
  try {
    const result = await adminApi.media.references(id);
    refs = result.references || [];
  } catch {
    // 引用检查失败时允许继续删除
  }

  if (refs.length > 0) {
    const refList = refs.join('；');
    const confirmed = await new Promise<boolean>((resolve) => {
      const dialog = DialogPlugin.confirm({
        header: '确认删除',
        body: `该媒体文件被以下内容引用：${refList}。删除后将导致这些内容出现死链，确认继续？`,
        confirmBtn: { theme: 'danger', content: '仍然删除' },
        cancelBtn: '取消',
        onConfirm: () => {
          dialog.destroy();
          resolve(true);
        },
        onCancel: () => {
          dialog.destroy();
          resolve(false);
        },
        onClose: () => {
          dialog.destroy();
          resolve(false);
        },
      });
    });

    if (!confirmed) return;
  }

  deletingId.value = id;
  try {
    await adminApi.media.remove(id);
    MessagePlugin.success('媒体已删除');

    if (items.value.length === 1 && filters.page > 1) {
      filters.page -= 1;
    }
    await loadMediaList();
  } catch (error) {
    MessagePlugin.error(errorMessage(error, '删除媒体失败'));
  } finally {
    deletingId.value = '';
  }
}

// ─── 转存（media migration）相关状态与逻辑 ───
const migrateDialogVisible = ref(false);
const migrating = ref(false);
/** 进度轮询对话框（进行中不可关闭） */
const migrateProgressDialogVisible = ref(false);
const migrateResultDialogVisible = ref(false);
const migrateResult = ref<MediaMigrationProgress | null>(null);
/** 轮询超时标志：仍然后台执行 */
const migrateTimedOut = ref(false);
/** 本次转存的目标文件数（单个或批量） */
const migrateTargetCount = ref(0);
const migrateForm = reactive({
  driver: '',
  deleteSource: false,
});
/** 轮询间隔 2s，上限 120 次（约 4 分钟），超时提示后台执行中 */
const MIGRATE_POLL_INTERVAL_MS = 2000;
const MIGRATE_POLL_MAX_TIMES = 120;
let migratePollTimer: ReturnType<typeof setTimeout> | null = null;
let migratePollCount = 0;

/** 转存对话框目标选项：排除当前文件所在的存储区没有意义——按契约全部可选，由后端 skipped 语义兜底 */
const migrateDriverOptions = computed(() => driverOptions.value);

const migrateProgressSummary = computed(() => ({
  total: Number(migrateResult.value?.total || migrateTargetCount.value || 0),
  done: Number(migrateResult.value?.done || 0),
}));

const migrateResultSummary = computed(() => ({
  total: Number(migrateResult.value?.total || 0),
  done: Number(migrateResult.value?.done || 0),
  failed: Number(migrateResult.value?.failed || 0),
  skipped: Number(migrateResult.value?.skipped || 0),
  // 完成且有失败项 → 部分失败语义（显示失败文案与明细）
  success: String(migrateResult.value?.status || '') === 'completed' && !Number(migrateResult.value?.failed || 0),
}));

/** 单行转存（row 传入）或批量转存（不传 row 用多选） */
function openMigrateDialog(row?: MediaFileRecord) {
  migrateRowRef = row;
  let count = 0;
  if (row) {
    count = 1;
  } else {
    count = selectedMigrateIds().length;
  }
  if (!count) {
    MessagePlugin.warning('请先选择需要转存的媒体文件');
    return;
  }
  migrateTargetCount.value = count;
  migrateForm.driver = '';
  migrateForm.deleteSource = false;
  migrateDialogVisible.value = true;
}

/** 收集本次转存的文件 id 列表（单个用 row.id，批量用多选行 id） */
function selectedMigrateIds(row?: MediaFileRecord): Array<number | string> {
  if (row) {
    const id = row.id;
    return id === undefined || id === null ? [] : [id];
  }
  return selectedRowKeys.value.map((key) => key);
}

async function submitMigrate() {
  if (!migrateForm.driver) {
    MessagePlugin.warning('请选择目标存储区');
    return;
  }
  const ids = pendingMigrateIds();
  if (!ids.length) {
    MessagePlugin.warning('请先选择需要转存的媒体文件');
    return;
  }

  migrating.value = true;
  try {
    const snapshot = await adminApi.media.migrateCreate({
      // 目标为本地 → to_local；其他远程渠道 → to_remote
      direction: migrateForm.driver === 'local' ? 'to_local' : 'to_remote',
      driver: migrateForm.driver,
      scope: 'ids',
      ids,
      delete_source: migrateForm.deleteSource,
    });
    migrateDialogVisible.value = false;
    migrateResult.value = snapshot || null;
    migrateTimedOut.value = false;
    migratePollCount = 0;

    if (isMigrationFinished(snapshot)) {
      showMigrateResult();
    } else {
      migrateProgressDialogVisible.value = true;
      scheduleMigratePoll(String(snapshot?.id ?? ''));
    }
  } catch (error) {
    MessagePlugin.error(errorMessage(error, '发起转存失败'));
  } finally {
    migrating.value = false;
  }
}

/** 待转存 id：单个转存用打开对话框时的行，批量用当前多选（打开后多选变化也跟随最新选择） */
function pendingMigrateIds(): Array<number | string> {
  return selectedMigrateIds(migrateRowRef);
}

/** 打开对话框时记住的单个行（批量为 undefined） */
let migrateRowRef: MediaFileRecord | undefined;

function isMigrationFinished(progress?: MediaMigrationProgress | null) {
  const status = String(progress?.status || '');
  return status === 'completed' || status === 'failed';
}

function scheduleMigratePoll(batchId: string) {
  if (!batchId) {
    migrateProgressDialogVisible.value = false;
    showMigrateResult();
    return;
  }
  migratePollTimer = setTimeout(() => {
    void pollMigrateOnce(batchId);
  }, MIGRATE_POLL_INTERVAL_MS);
}

async function pollMigrateOnce(batchId: string) {
  try {
    const progress = await adminApi.media.migrateProgress(batchId);
    migrateResult.value = progress || null;
    if (isMigrationFinished(progress)) {
      finishMigratePolling();
      return;
    }
  } catch {
    // 单次轮询失败不中断：继续重试直至上限
  }
  migratePollCount += 1;
  if (migratePollCount >= MIGRATE_POLL_MAX_TIMES) {
    // 超时：停止轮询，提示后台执行中
    migrateTimedOut.value = true;
    finishMigratePolling();
    return;
  }
  scheduleMigratePoll(batchId);
}

function finishMigratePolling() {
  if (migratePollTimer) {
    clearTimeout(migratePollTimer);
    migratePollTimer = null;
  }
  migrateProgressDialogVisible.value = false;
  showMigrateResult();
  // 转存结束后刷新列表（disk 可能已变化）
  void loadMediaList();
}

function showMigrateResult() {
  migrateResultDialogVisible.value = true;
}

function copyText(value: string) {
  if (!value) {
    MessagePlugin.warning('没有可复制的内容');
    return;
  }

  void copyToClipboard(value);
}

onMounted(() => {
  loadDrivers();
  loadMediaList();
});

onUnmounted(() => {
  // 离开页面时清理轮询定时器，避免泄漏
  if (migratePollTimer) {
    clearTimeout(migratePollTimer);
    migratePollTimer = null;
  }
  // 上传进行中离开页面：置卸载标志，串行循环与进度回调不再回写状态
  uploadAborted = true;
});
</script>
