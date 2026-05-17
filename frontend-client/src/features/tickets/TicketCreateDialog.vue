<template>
  <el-dialog v-model="visible" title="提交工单" width="720px" destroy-on-close>
    <el-form label-position="top">
      <el-form-item label="问题分类" required>
        <el-select v-model="createForm.department" style="width: 100%;">
          <el-option label="销售" value="sales" />
          <el-option label="技术支持" value="support" />
          <el-option label="财务" value="billing" />
          <el-option label="投诉" value="abuse" />
        </el-select>
      </el-form-item>
      <el-form-item label="工单标题" required>
        <el-input v-model="createForm.subject" maxlength="200" placeholder="请简要描述您的问题" />
      </el-form-item>
      <el-form-item label="关联服务">
        <el-select v-model="createForm.service_id" clearable filterable placeholder="可选，如与具体服务相关" style="width: 100%;">
          <el-option
            v-for="item in serviceOptions"
            :key="item.id"
            :label="formatTicketServiceOptionLabel(item)"
            :value="item.id"
          >
            <div class="service-option">
              <span class="service-option__label">{{ formatTicketServiceOptionLabel(item, false) }}</span>
              <el-tag
                effect="light"
                size="small"
                :type="resolveTicketServiceStatusMeta(item).elTagType"
                :class="[
                  'service-option__status',
                  resolveTicketServiceStatusMeta(item).tagType === 'purple' ? 'el-tag--purple' : '',
                ]"
              >
                <span class="service-option__status-dot" />
                {{ resolveTicketServiceStatusMeta(item).label }}
              </el-tag>
            </div>
          </el-option>
        </el-select>
      </el-form-item>
      <el-form-item label="优先级">
        <el-select v-model="createForm.priority" style="width: 100%;">
          <el-option label="低" :value="1" />
          <el-option label="中" :value="2" />
          <el-option label="高" :value="3" />
          <el-option label="紧急" :value="4" />
        </el-select>
      </el-form-item>
      <el-form-item label="问题描述">
        <el-input
          v-model="createForm.content"
          type="textarea"
          :rows="5"
          maxlength="10000"
          show-word-limit
          placeholder="请详细描述您遇到的问题"
        />
      </el-form-item>
      <el-form-item label="添加附件">
        <div class="upload-section">
          <el-upload
            class="ticket-upload"
            accept=".jpg,.jpeg,.png,.webp"
            multiple
            list-type="picture-card"
            :file-list="uploadFileList"
            :http-request="handleUpload"
            :before-upload="beforeUpload"
            :on-preview="handlePreview"
            :on-remove="handleRemove"
            :on-exceed="handleExceed"
            :limit="MAX_IMAGES"
            :disabled="uploading"
          >
            <el-icon v-if="!uploadDisabled"><Plus /></el-icon>
          </el-upload>
          <span class="upload-tip">支持 jpg/png/webp，最多 {{ MAX_IMAGES }} 张，单张不超过 5MB</span>
        </div>
      </el-form-item>
    </el-form>
    <template #footer>
      <el-button @click="visible = false">取消</el-button>
      <el-button type="primary" :loading="creating" :disabled="!canSubmit" @click="handleSubmit">
        提交工单
      </el-button>
    </template>
  </el-dialog>

  <!-- 图片预览 -->
  <el-dialog v-model="previewVisible" title="附件预览" width="720px" append-to-body>
    <img v-if="previewUrl" :src="previewUrl" class="preview-image" alt="预览" />
  </el-dialog>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { Plus } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import clientApi from '@/api/client'
import { formatTicketServiceOptionLabel, resolveTicketServiceStatusMeta } from './serviceOptionLabel'

const MAX_IMAGES = 9
const IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp']
const MAX_SIZE = 5 * 1024 * 1024

const props = defineProps({
  modelValue: Boolean,
  creating: Boolean,
  serviceOptions: Array,
})

const emit = defineEmits(['update:modelValue', 'submit'])

const visible = defineModel('modelValue')

const createForm = reactive({
  department: 'support',
  subject: '',
  content: '',
  priority: 2,
  service_id: undefined,
})

const uploadedFiles = ref([])
const uploading = ref(false)
const uploadCount = ref(0)
const previewVisible = ref(false)
const previewUrl = ref('')

const uploadFileList = computed(() =>
  uploadedFiles.value.map((f) => ({
    name: f.name || '图片附件',
    url: f.url,
    status: 'success',
    uid: String(f.id || f.uid),
  }))
)

const uploadDisabled = computed(() =>
  uploadedFiles.value.length + uploadCount.value >= MAX_IMAGES
)

const canSubmit = computed(() => {
  return createForm.subject.trim().length > 0
})

watch(visible, (val) => {
  if (!val) {
    resetForm()
  }
})

function resetForm() {
  createForm.department = 'support'
  createForm.subject = ''
  createForm.content = ''
  createForm.priority = 2
  createForm.service_id = undefined
  uploadedFiles.value = []
}

function beforeUpload(file) {
  if (!IMAGE_TYPES.includes(file.type)) {
    ElMessage.warning('仅支持 jpg、png、webp 图片')
    return false
  }
  if (file.size > MAX_SIZE) {
    ElMessage.warning('单张图片不能超过 5MB')
    return false
  }
  if (uploadDisabled.value) {
    ElMessage.warning(`最多上传 ${MAX_IMAGES} 张图片`)
    return false
  }
  return true
}

async function handleUpload(options) {
  uploadCount.value++
  try {
    const formData = new FormData()
    formData.append('file', options.file)
    const res = await clientApi.uploadTicketImage(formData)
    uploadedFiles.value = [...uploadedFiles.value, res.data].slice(0, MAX_IMAGES)
    options.onSuccess?.({}, options.file)
  } catch (error) {
    options.onError?.(error)
    ElMessage.error(error?.message || '图片上传失败')
  } finally {
    uploadCount.value = Math.max(0, uploadCount.value - 1)
  }
}

function handleRemove(file) {
  const uid = String(file.uid || file.id)
  uploadedFiles.value = uploadedFiles.value.filter((f) => String(f.id || f.uid) !== uid)
}

function handlePreview(file) {
  previewUrl.value = file.url || ''
  previewVisible.value = !!previewUrl.value
}

function handleExceed() {
  ElMessage.warning(`最多上传 ${MAX_IMAGES} 张图片`)
}

async function handleSubmit() {
  if (!createForm.subject.trim()) {
    ElMessage.warning('请输入工单标题')
    return
  }

  const attachments = uploadedFiles.value
    .map((f) => f.path)
    .filter(Boolean)

  emit('submit', {
    department: createForm.department,
    subject: createForm.subject,
    content: createForm.content,
    priority: createForm.priority,
    service_id: createForm.service_id,
    attachments,
  })
}
</script>

<style scoped lang="scss">
.upload-section {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.service-option {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  width: 100%;
}

.service-option__label {
  min-width: 0;
  flex: 1;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.service-option__status {
  flex-shrink: 0;
}

.service-option__status-dot {
  display: inline-block;
  width: 6px;
  height: 6px;
  border-radius: 50%;
  margin-right: 6px;
  vertical-align: middle;
  background: currentColor;
}

.ticket-upload {
  :deep(.el-upload--picture-card) {
    width: 72px;
    height: 72px;
    border-radius: 8px;
  }
}

.upload-tip {
  font-size: 12px;
  color: $text-color-placeholder;
}

.preview-image {
  width: 100%;
  display: block;
}
</style>
