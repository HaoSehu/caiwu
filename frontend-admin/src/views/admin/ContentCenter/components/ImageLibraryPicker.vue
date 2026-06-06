<template>
  <el-dialog
    v-model="visible"
    title="图片库"
    width="800px"
    top="6vh"
    destroy-on-close
    append-to-body
    class="image-library-dialog"
    @opened="onOpened"
  >
    <el-tabs v-model="activeTab">
      <el-tab-pane label="图库选择" name="library">
        <div class="library-toolbar">
          <el-input
            v-model="keyword"
            placeholder="搜索文件名"
            clearable
            style="width: 220px"
            @keyup.enter="loadLibrary(1)"
            @clear="loadLibrary(1)"
          >
          </el-input>
        </div>

        <div v-loading="libraryLoading" class="library-grid">
          <div
            v-for="item in libraryList"
            :key="item.id"
            class="library-item"
            :class="{ 'is-selected': selectedId === item.id }"
            @click="selectItem(item)"
          >
            <img :src="item.url" :alt="item.filename" class="library-item__img" />
            <div class="library-item__overlay">
              <el-icon v-if="selectedId === item.id" :size="24"><Check /></el-icon>
            </div>
            <div class="library-item__info">
              <span class="library-item__name" :title="item.filename">{{ item.filename }}</span>
              <span class="library-item__size">{{ formatSize(item.size) }}</span>
            </div>
            <el-button
              class="library-item__delete"
              :icon="Delete"
              circle
              size="small"
              type="danger"
              @click.stop="handleDelete(item)"
            />
          </div>

          <el-empty v-if="!libraryLoading && !libraryList.length" description="暂无图片" />
        </div>

        <div v-if="libraryTotal > libraryPageSize" class="library-pager">
          <el-pagination
            v-model:current-page="libraryPage"
            :page-size="libraryPageSize"
            :total="libraryTotal"
            layout="total, prev, pager, next"
            small
            @current-change="loadLibrary"
          />
        </div>
      </el-tab-pane>

      <el-tab-pane label="上传新图" name="upload">
        <el-upload
          class="upload-area"
          drag
          accept=".jpg,.jpeg,.png,.webp"
          :show-file-list="false"
          :http-request="handleUpload"
        >
          <div class="upload-placeholder">
            <el-icon :size="40"><UploadFilled /></el-icon>
            <p>将图片拖拽到此处，或点击上传</p>
            <p class="upload-hint">支持 jpg / png / webp，最大 5MB</p>
          </div>
        </el-upload>

        <div v-if="uploadedUrl" class="upload-preview">
          <img :src="uploadedUrl" alt="已上传" class="upload-preview__img" />
          <p class="upload-preview__text">上传成功，点击下方确认使用</p>
        </div>
      </el-tab-pane>
    </el-tabs>

    <template #footer>
      <div class="dialog-footer">
        <el-button @click="visible = false">取消</el-button>
        <el-button type="primary" :disabled="!currentUrl" @click="handleConfirm">确认选择</el-button>
      </div>
    </template>
  </el-dialog>
</template>

<script setup>
import { computed, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Check, Delete, UploadFilled } from '@element-plus/icons-vue'
import adminApi from '@/api/admin'

const visible = defineModel({ type: Boolean })
const emit = defineEmits(['confirm'])

const props = defineProps({
  group: { type: String, default: 'content' },
})

const activeTab = ref('library')
const keyword = ref('')

const libraryList = ref([])
const libraryLoading = ref(false)
const libraryPage = ref(1)
const libraryPageSize = 18
const libraryTotal = ref(0)

const selectedId = ref(null)
const selectedUrl = ref('')
const uploadedUrl = ref('')

const currentUrl = computed(() => {
  if (activeTab.value === 'upload') return uploadedUrl.value
  return selectedUrl.value
})

function onOpened() {
  selectedId.value = null
  selectedUrl.value = ''
  uploadedUrl.value = ''
  loadLibrary(1)
}

async function loadLibrary(page = 1) {
  libraryLoading.value = true
  libraryPage.value = page

  try {
    const res = await adminApi.content.media.list({
      page,
      page_size: libraryPageSize,
      group: props.group,
      keyword: keyword.value || undefined,
    })
    libraryList.value = res.data?.list || []
    libraryTotal.value = Number(res.data?.total || 0)
  } catch {
    ElMessage.error('加载图库失败')
  } finally {
    libraryLoading.value = false
  }
}

function selectItem(item) {
  if (selectedId.value === item.id) {
    selectedId.value = null
    selectedUrl.value = ''
    return
  }
  selectedId.value = item.id
  selectedUrl.value = item.url
}

async function handleUpload(options) {
  const formData = new FormData()
  formData.append('file', options.file)
  formData.append('group', props.group)

  try {
    const res = await adminApi.content.media.upload(formData)
    uploadedUrl.value = res.data?.url || ''
    options.onSuccess?.({}, options.file)
    ElMessage.success('上传成功')
    loadLibrary(1)
  } catch {
    ElMessage.error('上传失败，请重试')
    options.onError?.(new Error('upload failed'))
  }
}

async function handleDelete(item) {
  try {
    await ElMessageBox.confirm(`确定删除图片「${item.filename}」？删除后不可恢复。`, '删除确认', {
      type: 'warning',
      confirmButtonText: '删除',
      cancelButtonText: '取消',
    })
  } catch {
    return
  }

  try {
    await adminApi.content.media.delete(item.id)
    ElMessage.success('删除成功')
    if (selectedId.value === item.id) {
      selectedId.value = null
      selectedUrl.value = ''
    }
    loadLibrary(libraryPage.value)
  } catch {
    ElMessage.error('删除失败')
  }
}

function handleConfirm() {
  if (!currentUrl.value) return
  emit('confirm', currentUrl.value)
  visible.value = false
}

function formatSize(bytes) {
  if (!bytes) return '--'
  if (bytes < 1024) return bytes + ' B'
  if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB'
  return (bytes / (1024 * 1024)).toFixed(2) + ' MB'
}
</script>

<style scoped lang="scss">
.library-toolbar {
  margin-bottom: 14px;
}

.library-grid {
  display: grid;
  grid-template-columns: repeat(6, minmax(0, 1fr));
  gap: 10px;
  min-height: 200px;
}

.library-item {
  position: relative;
  border: 2px solid $divider-color;
  border-radius: 6px;
  overflow: hidden;
  cursor: pointer;
  transition: border-color 0.2s;

  &:hover {
    border-color: $primary-color;
  }

  &.is-selected {
    border-color: $primary-color;
    box-shadow: 0 0 0 2px rgba($primary-color, 0.2);
  }
}

.library-item__img {
  display: block;
  width: 100%;
  aspect-ratio: 4 / 3;
  object-fit: cover;
  background: $bg-color-soft;
}

.library-item__overlay {
  position: absolute;
  top: 4px;
  left: 4px;
  display: grid;
  place-items: center;
  width: 28px;
  height: 28px;
  border-radius: 50%;
  background: $primary-color;
  color: #fff;
  opacity: 0;
  transition: opacity 0.2s;

  .is-selected & {
    opacity: 1;
  }
}

.library-item__info {
  padding: 4px 6px;
  background: $bg-color-soft;
}

.library-item__name {
  display: block;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-size: 11px;
  color: $text-color-secondary;
}

.library-item__size {
  font-size: 10px;
  color: $text-color-placeholder;
}

.library-item__delete {
  position: absolute;
  top: 4px;
  right: 4px;
  opacity: 0;
  transition: opacity 0.2s;

  .library-item:hover & {
    opacity: 1;
  }
}

.library-pager {
  display: flex;
  justify-content: flex-end;
  margin-top: 14px;
}

.upload-area {
  :deep(.el-upload-dragger) {
    padding: 40px 20px;
  }
}

.upload-placeholder {
  text-align: center;
  color: $text-color-placeholder;

  p {
    margin-top: 8px;
    font-size: 14px;
  }
}

.upload-hint {
  font-size: 12px !important;
  color: $text-color-placeholder;
}

.upload-preview {
  margin-top: 16px;
  text-align: center;
}

.upload-preview__img {
  max-width: 300px;
  max-height: 200px;
  border: 1px solid $divider-color;
  border-radius: 6px;
  object-fit: contain;
}

.upload-preview__text {
  margin-top: 8px;
  font-size: 12px;
  color: $text-color-secondary;
}

.dialog-footer {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
}

.image-library-dialog :deep(.el-dialog__body) {
  max-height: calc(100vh - 240px);
  overflow: auto;
}

@media (max-width: 900px) {
  .library-grid {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
}
</style>
