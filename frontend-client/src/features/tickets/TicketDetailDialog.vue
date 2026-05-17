<template>
  <el-dialog
    v-if="!embedded"
    v-model="visible"
    :title="null"
    width="900px"
    class="ticket-detail-dialog"
    :close-on-click-modal="false"
  >
    <div v-loading="detailLoading" class="ticket-panel">
      <el-button
        v-if="detail?.status !== 3"
        class="manual-close-button"
        plain
        :loading="closing"
        @click="handleClose"
      >
        关闭工单
      </el-button>

      <section class="ticket-meta">
        <div class="meta-item meta-item--user">
          <span><strong>用户:</strong> {{ userName }}</span>
          <span class="meta-chip">id: {{ detail?.user?.id || detail?.user_id || '--' }}</span>
        </div>
        <div class="meta-item"><strong>工单分类:</strong> {{ resolveDepartmentLabel(detail?.department) }}</div>
        <div class="meta-item"><strong>优先级:</strong> {{ resolvePriorityLabel(detail?.priority) }}</div>
        <div class="meta-item"><strong>处理人:</strong> {{ assigneeName }}</div>
        <div class="meta-item">
          <strong>关联服务 id:</strong> {{ detail?.service?.id || detail?.service_id || '--' }}
        </div>
        <div class="meta-item">
          <strong>创建时间:</strong> {{ formatDateTime(detail?.created_at) }}
        </div>
      </section>

      <section class="conversation-section">
        <div class="message-list" ref="messageListRef">
          <div v-if="detail?.content" class="message-item message-customer">
            <div class="message-bubble">
              <div class="message-meta">
                <span class="message-sender">{{ detail.user?.display_name || '我' }}</span>
                <span class="message-time">{{ formatDateTime(detail?.created_at) }}</span>
              </div>
              <div class="message-content-wrapper">
                <div class="message-content">{{ detail.content }}</div>
                <div v-if="hasAttachments(detail)" class="message-attachments">
                  <div
                    v-for="att in parseAttachments(detail)"
                    :key="att.id"
                    class="attachment-thumb"
                    @click="handleAttachmentPreview(att)"
                  >
                    <el-image :src="att.url" fit="cover" />
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- 回复列表 -->
          <div
            v-for="reply in detail?.replies || []"
            :key="reply.id"
            class="message-item"
            :class="reply.is_staff ? 'message-staff' : 'message-customer'"
          >
            <div class="message-bubble">
              <div class="message-meta">
                <span class="message-sender">{{ reply.sender_name || (reply.is_staff ? '客服' : '我') }}</span>
                <span class="message-time">{{ reply.created_at }}</span>
              </div>
              <div class="message-content-wrapper">
                <div class="message-content">{{ reply.content || '无文字内容' }}</div>
                <div v-if="hasAttachments(reply)" class="message-attachments">
                  <div
                    v-for="att in parseAttachments(reply)"
                    :key="att.id"
                    class="attachment-thumb"
                    @click="handleAttachmentPreview(att)"
                  >
                    <el-image
                      :src="att.url"
                      fit="cover"
                      :preview-src-list="getPreviewList(reply)"
                      :initial-index="getPreviewIndex(reply, att)"
                      preview-teleported
                    />
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <section class="reply-section" v-if="detail?.status !== 3">
        <div v-if="replyAttachments.length" class="draft-attachments">
          <button
            v-for="attachment in replyAttachments"
            :key="attachment.id || attachment.uid"
            class="draft-attachment"
            type="button"
            @click="handleDraftAttachmentPreview(attachment)"
          >
            <img :src="attachment.url" alt="附件" />
          </button>
        </div>

        <div class="composer-bar">
          <el-upload
            class="composer-upload"
            accept=".jpg,.jpeg,.png,.webp"
            multiple
            :show-file-list="false"
            :http-request="handleReplyUpload"
            :before-upload="beforeReplyUpload"
            :on-exceed="handleReplyUploadExceed"
            :limit="MAX_IMAGES"
            :disabled="replying"
          >
            <button class="composer-plus" type="button" :disabled="replyUploadDisabled || replying">
              <el-icon><Plus /></el-icon>
            </button>
          </el-upload>
          <el-input
            v-model="replyContent"
            type="textarea"
            :autosize="{ minRows: 1, maxRows: 4 }"
            maxlength="5000"
            placeholder=""
            class="reply-textarea"
            @keydown.enter.exact.prevent="handleReply"
          />
          <el-button
            class="send-button"
            :loading="replying"
            :disabled="replySubmittingDisabled"
            @click="handleReply"
          >
            发送
          </el-button>
        </div>
      </section>

      <div v-if="detail?.status === 3" class="ticket-closed-notice">
        <el-icon><CircleCheck /></el-icon>
        <span>
          此工单已关闭
          {{ detail?.close_reason_label ? `（${detail.close_reason_label}）` : '' }}
        </span>
      </div>
    </div>
  </el-dialog>

  <div v-else class="ticket-detail-embedded">
    <div v-loading="detailLoading" class="ticket-panel ticket-panel--embedded">
      <el-button
        v-if="detail?.status !== 3"
        class="manual-close-button"
        plain
        :loading="closing"
        @click="handleClose"
      >
        关闭工单
      </el-button>

      <section class="ticket-meta">
        <div class="meta-item meta-item--user">
          <span><strong>用户:</strong> {{ userName }}</span>
          <span class="meta-chip">id: {{ detail?.user?.id || detail?.user_id || '--' }}</span>
        </div>
        <div class="meta-item"><strong>工单分类:</strong> {{ resolveDepartmentLabel(detail?.department) }}</div>
        <div class="meta-item"><strong>优先级:</strong> {{ resolvePriorityLabel(detail?.priority) }}</div>
        <div class="meta-item"><strong>处理人:</strong> {{ assigneeName }}</div>
        <div class="meta-item">
          <strong>关联服务 id:</strong> {{ detail?.service?.id || detail?.service_id || '--' }}
        </div>
        <div class="meta-item">
          <strong>创建时间:</strong> {{ formatDateTime(detail?.created_at) }}
        </div>
      </section>

      <section class="conversation-section">
        <div class="message-list" ref="messageListRef">
          <div v-if="detail?.content" class="message-item message-customer">
            <div class="message-bubble">
              <div class="message-meta">
                <span class="message-sender">{{ detail.user?.display_name || '我' }}</span>
                <span class="message-time">{{ formatDateTime(detail?.created_at) }}</span>
              </div>
              <div class="message-content-wrapper">
                <div class="message-content">{{ detail.content }}</div>
                <div v-if="hasAttachments(detail)" class="message-attachments">
                  <div
                    v-for="att in parseAttachments(detail)"
                    :key="att.id"
                    class="attachment-thumb"
                    @click="handleAttachmentPreview(att)"
                  >
                    <el-image :src="att.url" fit="cover" />
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div
            v-for="reply in detail?.replies || []"
            :key="reply.id"
            class="message-item"
            :class="reply.is_staff ? 'message-staff' : 'message-customer'"
          >
            <div class="message-bubble">
              <div class="message-meta">
                <span class="message-sender">{{ reply.sender_name || (reply.is_staff ? '客服' : '我') }}</span>
                <span class="message-time">{{ reply.created_at }}</span>
              </div>
              <div class="message-content-wrapper">
                <div class="message-content">{{ reply.content || '无文字内容' }}</div>
                <div v-if="hasAttachments(reply)" class="message-attachments">
                  <div
                    v-for="att in parseAttachments(reply)"
                    :key="att.id"
                    class="attachment-thumb"
                    @click="handleAttachmentPreview(att)"
                  >
                    <el-image
                      :src="att.url"
                      fit="cover"
                      :preview-src-list="getPreviewList(reply)"
                      :initial-index="getPreviewIndex(reply, att)"
                      preview-teleported
                    />
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <section class="reply-section" v-if="detail?.status !== 3">
        <div v-if="replyAttachments.length" class="draft-attachments">
          <button
            v-for="attachment in replyAttachments"
            :key="attachment.id || attachment.uid"
            class="draft-attachment"
            type="button"
            @click="handleDraftAttachmentPreview(attachment)"
          >
            <img :src="attachment.url" alt="附件" />
          </button>
        </div>

        <div class="composer-bar">
          <el-upload
            class="composer-upload"
            accept=".jpg,.jpeg,.png,.webp"
            multiple
            :show-file-list="false"
            :http-request="handleReplyUpload"
            :before-upload="beforeReplyUpload"
            :on-exceed="handleReplyUploadExceed"
            :limit="MAX_IMAGES"
            :disabled="replying"
          >
            <button class="composer-plus" type="button" :disabled="replyUploadDisabled || replying">
              <el-icon><Plus /></el-icon>
            </button>
          </el-upload>
          <el-input
            v-model="replyContent"
            type="textarea"
            :autosize="{ minRows: 1, maxRows: 4 }"
            maxlength="5000"
            placeholder=""
            class="reply-textarea"
            @keydown.enter.exact.prevent="handleReply"
          />
          <el-button
            class="send-button"
            :loading="replying"
            :disabled="replySubmittingDisabled"
            @click="handleReply"
          >
            发送
          </el-button>
        </div>
      </section>

      <div v-if="detail?.status === 3" class="ticket-closed-notice">
        <el-icon><CircleCheck /></el-icon>
        <span>
          此工单已关闭
          {{ detail?.close_reason_label ? `（${detail.close_reason_label}）` : '' }}
        </span>
      </div>
    </div>
  </div>

  <!-- 附件预览 -->
  <el-dialog v-model="previewVisible" title="附件预览" width="720px" append-to-body>
    <img v-if="previewUrl" :src="previewUrl" class="preview-image" alt="预览" />
  </el-dialog>
</template>

<script setup>
import { computed, ref, watch, nextTick } from 'vue'
import { CircleCheck, Plus } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import clientApi from '@/api/client'

const MAX_IMAGES = 9
const IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp']
const MAX_SIZE = 5 * 1024 * 1024

const props = defineProps({
  modelValue: Boolean,
  detail: Object,
  detailLoading: Boolean,
  replying: Boolean,
  closing: Boolean,
  embedded: Boolean,
  resolveTicketStatusLabel: Function,
  resolveDepartmentLabel: Function,
  resolvePriorityLabel: Function,
})

const emit = defineEmits(['update:modelValue', 'reply', 'close'])

const visible = defineModel('modelValue')
const replyContent = ref('')
const replyAttachments = ref([])
const replyUploadingCount = ref(0)
const messageListRef = ref(null)
const previewVisible = ref(false)
const previewUrl = ref('')

const userName = computed(() =>
  props.detail?.user?.display_name || props.detail?.user?.nickname || props.detail?.user?.email || '客户'
)

const assigneeName = computed(() =>
  props.detail?.assignee?.nickname || props.detail?.assignee?.username || '未分配'
)

const replyUploadDisabled = computed(() =>
  replyAttachments.value.length + replyUploadingCount.value >= MAX_IMAGES
)

const canReply = computed(() =>
  replyContent.value.trim().length > 0 || replyAttachments.value.length > 0
)

const replySubmittingDisabled = computed(() =>
  !canReply.value || replyUploadingCount.value > 0
)

watch(visible, (val) => {
  if (!val) {
    resetReplyDraft()
  } else {
    nextTick(() => scrollToBottom())
  }
})

watch(() => props.detail?.replies, () => {
  nextTick(() => scrollToBottom())
}, { deep: true })

function scrollToBottom() {
  if (messageListRef.value) {
    messageListRef.value.scrollTop = messageListRef.value.scrollHeight
  }
}

function handleReply() {
  if (replyUploadingCount.value > 0) {
    ElMessage.warning('图片上传中，请稍后发送')
    return
  }

  if (!canReply.value) {
    ElMessage.warning('请输入回复内容或上传图片')
    return
  }

  emit('reply', {
    content: replyContent.value,
    attachments: replyAttachments.value.map((item) => item.path).filter(Boolean),
  })
}

function handleClose() {
  emit('close')
}

function hasAttachments(item) {
  const attachments = item?.attachments || item?.attachment_urls
  return Array.isArray(attachments) && attachments.length > 0
}

function resetReplyDraft() {
  replyContent.value = ''
  replyAttachments.value = []
  replyUploadingCount.value = 0
}

function beforeReplyUpload(file) {
  if (!IMAGE_TYPES.includes(file.type)) {
    ElMessage.warning('仅支持 jpg、png、webp 图片')
    return false
  }

  if (file.size > MAX_SIZE) {
    ElMessage.warning('单张图片不能超过 5MB')
    return false
  }

  if (replyUploadDisabled.value) {
    ElMessage.warning(`最多上传 ${MAX_IMAGES} 张图片`)
    return false
  }

  return true
}

async function handleReplyUpload(options) {
  replyUploadingCount.value += 1
  try {
    const formData = new FormData()
    formData.append('file', options.file)
    const res = await clientApi.uploadTicketImage(formData)
    replyAttachments.value = [...replyAttachments.value, res.data].slice(0, MAX_IMAGES)
    options.onSuccess?.({}, options.file)
  } catch (error) {
    options.onError?.(error)
    ElMessage.error(error?.message || '图片上传失败')
  } finally {
    replyUploadingCount.value = Math.max(0, replyUploadingCount.value - 1)
  }
}

function handleDraftAttachmentPreview(file) {
  previewUrl.value = file.url || ''
  previewVisible.value = !!previewUrl.value
}

function handleReplyUploadExceed() {
  ElMessage.warning(`最多上传 ${MAX_IMAGES} 张图片`)
}

function parseAttachments(item) {
  const attachments = item?.attachments || item?.attachment_urls || []
  return attachments.map((url, index) => ({
    id: index,
    url: typeof url === 'string' ? url : url.url,
  }))
}

function handleAttachmentPreview(att) {
  previewUrl.value = att.url
  previewVisible.value = true
}

function getPreviewList(reply) {
  return parseAttachments(reply).map((a) => a.url)
}

function getPreviewIndex(reply, att) {
  return getPreviewList(reply).findIndex((url) => url === att.url)
}

function formatDateTime(dateStr) {
  if (!dateStr) return '--'
  return dateStr.slice(0, 16).replace('T', ' ')
}

</script>

<style scoped lang="scss">
.ticket-detail-dialog {
  :deep(.el-dialog) {
    max-width: calc(100vw - 32px);
    background: #fff;
    border-radius: 14px;
    box-shadow: 0 24px 70px rgba(15, 23, 42, 0.22);
  }

  :deep(.el-dialog__header) {
    padding: 0;
    margin-right: 0;
  }

  :deep(.el-dialog__body) {
    padding: 0;
  }

  :deep(.el-dialog__headerbtn) {
    top: 12px;
    right: 12px;
    z-index: 5;
    width: 30px;
    height: 30px;
    border: 1px solid #cbd6e6;
    border-radius: 50%;
    background: #fff;
    box-shadow: 0 8px 18px rgba(15, 23, 42, 0.08);
    transition:
      transform 0.18s cubic-bezier(0.2, 0.8, 0.2, 1),
      border-color 0.18s ease,
      box-shadow 0.18s ease,
      background 0.18s ease;
  }

  :deep(.el-dialog__headerbtn .el-dialog__close) {
    color: #53657f;
    font-size: 16px;
    transition:
      color 0.18s ease,
      transform 0.18s ease;
  }

  :deep(.el-dialog__headerbtn:hover) {
    border-color: #9fbcff;
    background: #f8fbff;
    box-shadow: 0 10px 20px rgba(37, 99, 235, 0.14);
    transform: translateY(-1px) scale(1.04);
  }

  :deep(.el-dialog__headerbtn:hover .el-dialog__close) {
    color: #2563eb;
    transform: rotate(90deg);
  }

  :deep(.el-dialog__headerbtn:active) {
    transform: translateY(0) scale(0.96);
  }
}

.ticket-panel {
  position: relative;
  display: grid;
  grid-template-rows: auto minmax(360px, 1fr) auto;
  min-height: min(78vh, 760px);
  padding: 28px 28px 14px;
  border: 1px solid #cfdcf0;
  border-radius: 12px;
  background: #fff;
}

.ticket-detail-embedded {
  min-height: calc(100vh - 150px);
}

.ticket-panel--embedded {
  min-height: calc(100vh - 150px);
  padding: 18px 14px 14px;
  border-radius: 12px;
  box-shadow: none;
}

.manual-close-button {
  position: absolute;
  top: 28px;
  right: 48px;
  z-index: 2;
  height: 30px;
  padding: 0 12px;
  color: #405675;
  border-color: #cfdcf0;
  border-radius: 999px;
  background: #fff;
  transition:
    transform 0.18s cubic-bezier(0.2, 0.8, 0.2, 1),
    border-color 0.18s ease,
    box-shadow 0.18s ease,
    color 0.18s ease;

  &:hover,
  &:focus {
    color: #dc2626;
    border-color: #fecaca;
    background: #fff;
    box-shadow: 0 8px 18px rgba(220, 38, 38, 0.12);
    transform: translateY(-1px);
  }

  &:active {
    transform: translateY(0) scale(0.98);
  }
}

.ticket-meta {
  display: grid;
  grid-template-columns: max-content max-content;
  column-gap: 26px;
  row-gap: 5px;
  justify-content: start;
  align-items: center;
  padding: 4px 0 18px 26px;
  color: #111827;
  font-size: 14px;
  line-height: 1.25;
}

.meta-item {
  min-height: 16px;
  color: #111827;
  white-space: nowrap;
}

.meta-item--user {
  display: flex;
  align-items: center;
  gap: 10px;
}

.meta-item strong {
  color: #111827;
  font-weight: 700;
}

.meta-chip {
  display: inline-flex;
  align-items: center;
  padding: 1px 7px;
  color: #5c6f8a;
  font-size: 12px;
  line-height: 18px;
  background: #eef4ff;
  border-radius: 999px;
}

.conversation-section {
  min-height: 0;
  padding: 4px 0 20px;
}

.message-list {
  display: flex;
  flex-direction: column;
  gap: 14px;
  height: 100%;
  max-height: none;
  overflow-y: auto;
  padding: 4px 6px 4px 0;
}

.message-item {
  display: flex;
}

.message-customer {
  flex-direction: row-reverse;

  .message-meta {
    flex-direction: row-reverse;
  }

  .message-attachments {
    justify-content: flex-end;
  }
}

.message-bubble {
  min-width: 0;
  max-width: 72%;
}

.message-meta {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 6px;
}

.message-sender {
  font-size: 12px;
  font-weight: 500;
  color: #44546a;
}

.message-time {
  font-size: 12px;
  color: #8b98aa;
}

.message-content-wrapper {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.message-content {
  padding: 11px 14px;
  font-size: 14px;
  line-height: 1.6;
  color: #172033;
  white-space: pre-wrap;
  border: 1px solid #dce3ed;
  box-shadow: 0 10px 24px rgba(15, 23, 42, 0.05);
}

.message-staff .message-content {
  background: #fff;
  border-radius: 4px 14px 14px 14px;
}

.message-customer .message-content {
  background: #eaf2ff;
  border-color: #c9dcff;
  border-radius: 14px 4px 14px 14px;
}

.message-attachments {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}

.attachment-thumb {
  width: 72px;
  height: 72px;
  border-radius: 8px;
  overflow: hidden;
  border: 1px solid $border-color;
  cursor: pointer;
  transition: transform 0.2s;

  &:hover {
    transform: scale(1.05);
  }

  :deep(.el-image) {
    width: 100%;
    height: 100%;
  }
}

.reply-section {
  padding-top: 4px;
}

.draft-attachments {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
  margin: 0 18px 10px;
}

.draft-attachment {
  width: 54px;
  height: 54px;
  padding: 0;
  overflow: hidden;
  cursor: pointer;
  background: #fff;
  border: 1px solid #d7dce5;
  border-radius: 8px;
}

.draft-attachment img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.composer-bar {
  display: grid;
  grid-template-columns: 48px minmax(0, 1fr) 112px;
  align-items: center;
  gap: 10px;
  min-height: 64px;
  padding: 8px 10px;
  border: 1px solid #d4dce8;
  border-radius: 999px;
  background: #fff;
  box-shadow: 0 12px 34px rgba(15, 23, 42, 0.08);
}

.composer-upload {
  display: flex;
  justify-content: center;

  :deep(.el-upload) {
    display: inline-flex;
  }
}

.composer-plus {
  position: relative;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 48px;
  height: 48px;
  padding: 0;
  color: #637083;
  cursor: pointer;
  background: #fff;
  border: 1px solid #ccd6e5;
  border-radius: 50%;
  transition:
    transform 0.18s cubic-bezier(0.2, 0.8, 0.2, 1),
    border-color 0.18s ease,
    box-shadow 0.18s ease,
    color 0.18s ease;

  .el-icon {
    font-size: 28px;
    transition: transform 0.22s cubic-bezier(0.2, 0.8, 0.2, 1);
  }

  &:not(:disabled):hover {
    color: #2563eb;
    border-color: #9fbcff;
    box-shadow: 0 8px 18px rgba(37, 99, 235, 0.16);
    transform: translateY(-1px) scale(1.04);
  }

  &:not(:disabled):hover .el-icon {
    transform: rotate(90deg);
  }

  &:not(:disabled):active {
    transform: translateY(0) scale(0.96);
    box-shadow: 0 4px 10px rgba(37, 99, 235, 0.12);
  }

  &:disabled {
    color: #b5b5b5;
    cursor: not-allowed;
    border-color: #d0d0d0;
  }
}

.reply-textarea {
  :deep(.el-textarea__inner) {
    min-height: 40px !important;
    padding: 9px 4px;
    resize: none;
    border: none;
    box-shadow: none;
    font-size: 16px;
    line-height: 1.5;
  }

  :deep(.el-input__count) {
    display: none;
  }
}

.send-button {
  position: relative;
  overflow: hidden;
  width: 96px;
  height: 48px;
  color: #405675;
  font-size: 18px;
  font-weight: 600;
  border: none;
  border-radius: 999px;
  background: #fff;
  transition:
    transform 0.18s cubic-bezier(0.2, 0.8, 0.2, 1),
    color 0.18s ease,
    background 0.18s ease;

  &::after {
    position: absolute;
    inset: 0;
    content: '';
    background: linear-gradient(110deg, transparent 0%, rgba(37, 99, 235, 0.1) 45%, transparent 72%);
    transform: translateX(-130%);
    transition: transform 0.48s ease;
  }

  :deep(span) {
    position: relative;
    z-index: 1;
  }

  &:hover,
  &:focus {
    color: #2563eb;
    background: #f8fbff;
    transform: translateY(-1px);
  }

  &:hover::after,
  &:focus::after {
    transform: translateX(130%);
  }

  &:active {
    transform: translateY(0) scale(0.98);
  }

  &.is-disabled,
  &.is-disabled:hover,
  &.is-disabled:focus {
    color: #9aa6b8;
    background: #eef2f7;
    transform: none;
  }

  &.is-disabled::after {
    display: none;
  }
}

.ticket-closed-notice {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 16px;
  background: $color-warning-soft;
  border: 1px solid $border-color;
  border-radius: 10px;
  color: $color-warning;
  font-size: 14px;
}

/* 预览 */
.preview-image {
  width: 100%;
  display: block;
}

/* 滚动条 */
.message-list {
  &::-webkit-scrollbar {
    width: 6px;
  }

  &::-webkit-scrollbar-track {
    background: $bg-color-soft;
    border-radius: 3px;
  }

  &::-webkit-scrollbar-thumb {
    background: $color-primary-border;
    border-radius: 3px;
  }
}

@media (max-width: 768px) {
  .ticket-panel {
    grid-template-rows: auto minmax(280px, 1fr) auto;
    min-height: min(82vh, 720px);
    padding: 24px 14px 14px;
  }

  .ticket-panel--embedded {
    grid-template-rows: auto minmax(0, 1fr) auto;
    min-height: calc(100vh - 150px);
    padding: 18px 12px 12px;
  }

  .manual-close-button {
    top: 22px;
    right: 44px;
    height: 28px;
    padding: 0 10px;
  }

  .ticket-meta {
    padding-top: 30px;
    grid-template-columns: 1fr;
    row-gap: 6px;
    padding-left: 10px;
    font-size: 13px;
  }

  .message-bubble {
    max-width: 86%;
  }

  .composer-bar {
    grid-template-columns: 42px minmax(0, 1fr) 76px;
    gap: 6px;
    min-height: 60px;
    padding: 8px;
  }

  .composer-plus {
    width: 42px;
    height: 42px;
  }

  .send-button {
    width: 72px;
    height: 42px;
    font-size: 18px;
  }
}
</style>
