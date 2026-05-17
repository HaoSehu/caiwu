<template>
  <div class="page-container admin-page ticket-conversation-page">
    <section class="admin-page-head conversation-head">
      <div class="admin-page-heading">
        <el-button text type="primary" class="back-button" @click="goBack">
          <el-icon><ArrowLeft /></el-icon>
          返回工单列表
        </el-button>
        <div class="title-row">
          <span class="ticket-id-pill">#{{ detail?.id || route.params.id }}</span>
          <h2>{{ detail?.subject || '工单详情' }}</h2>
          <el-tag v-if="detail" :type="statusType(detail.status)" effect="plain">
            {{ statusLabel(detail.status) }}
          </el-tag>
          <el-button
            v-if="detail && !isClosed(detail.status)"
            type="danger"
            plain
            size="small"
            class="header-close-button"
            :loading="closeLoading"
            @click="handleClose"
          >
            <el-icon><Close /></el-icon>
            关闭工单
          </el-button>
        </div>
        <p>独立交流页面，可直接完成指派、回复与关闭。</p>
      </div>
    </section>

    <div v-loading="detailLoading" class="conversation-body">
      <template v-if="detail">
        <main class="conversation-workbench">
          <section class="admin-conversation-panel">
            <div class="conversation-title-row">
              <span>沟通记录</span>
              <span>{{ (detail.replies?.length || 0) + (detail.content ? 1 : 0) }} 条消息</span>
            </div>
            <div class="admin-message-list">
              <div v-if="detail.content" class="admin-message admin-message--client">
                <div class="admin-message__body">
                  <div class="admin-message__meta">
                    <span>{{ detail.user?.display_name || detail.user?.email || '客户' }}</span>
                    <span>{{ formatDateTime(detail.created_at) }}</span>
                  </div>
                  <div class="admin-message__bubble">
                    <div class="content-text">{{ detail.content }}</div>
                    <div v-if="hasAttachments(detail)" class="content-attachments">
                      <button
                        v-for="att in parseAttachments(detail)"
                        :key="att.id"
                        type="button"
                        class="content-attachment"
                        @click="handleAttachmentPreview(att)"
                      >
                        <el-image :src="att.url" fit="cover" />
                      </button>
                    </div>
                  </div>
                </div>
              </div>

              <div
                v-for="reply in detail.replies || []"
                :key="reply.id"
                class="admin-message"
                :class="reply.is_staff ? 'admin-message--staff' : 'admin-message--client'"
              >
                <div class="admin-message__body">
                  <div class="admin-message__meta">
                    <span>{{ reply.sender_name || (reply.is_staff ? '客服' : '客户') }}</span>
                    <span>{{ formatDateTime(reply.created_at) }}</span>
                  </div>
                  <div class="admin-message__bubble">
                    <div class="reply-content">{{ reply.content || '无文字内容' }}</div>
                    <div v-if="hasAttachments(reply)" class="reply-attachments">
                      <button
                        v-for="att in parseAttachments(reply)"
                        :key="att.id"
                        type="button"
                        class="reply-attachment"
                        :class="{ 'reply-attachment--deleted': att.deleted || !att.url }"
                        @click="att.url && handleAttachmentPreview(att)"
                      >
                        <template v-if="att.url">
                          <el-image
                            :src="att.url"
                            fit="cover"
                            :preview-src-list="getAttachmentUrls(reply)"
                            :initial-index="getAttachmentIndex(reply, att)"
                          />
                        </template>
                        <template v-else>
                          <span class="deleted-placeholder">已删除</span>
                        </template>
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </section>

          <aside class="ticket-side-panel">
            <section class="admin-ticket-meta">
              <div class="meta-item">
                <span class="meta-label">提交用户</span>
                <button type="button" class="meta-value meta-link" @click="goUserDetail(detail.user?.id)">
                  {{ detail.user?.display_name || detail.user?.email || `用户 #${detail.user_id}` }}
                </button>
              </div>
              <div class="meta-item">
                <span class="meta-label">工单分类</span>
                <strong class="meta-value">{{ departmentLabel(detail.department) }}</strong>
              </div>
              <div class="meta-item">
                <span class="meta-label">优先级</span>
                <strong class="meta-value">{{ priorityLabel(detail.priority) }}</strong>
              </div>
              <div class="meta-item">
                <span class="meta-label">处理人</span>
                <div class="meta-assignee">
                  <el-select
                    v-model="assignForm.assignee_id"
                    placeholder="选择处理人"
                    clearable
                    filterable
                    class="meta-assignee-select"
                  >
                    <el-option
                      v-for="admin in adminUsers"
                      :key="admin.id"
                      :label="adminOptionLabel(admin)"
                      :value="admin.id"
                    />
                  </el-select>
                  <el-button type="primary" plain size="small" :loading="assignLoading" @click="handleAssign">
                    保存
                  </el-button>
                </div>
              </div>
              <div class="meta-item">
                <span class="meta-label">关联服务 id</span>
                <strong class="meta-value">{{ detail.service?.id || detail.service_id || '--' }}</strong>
              </div>
              <div class="meta-item">
                <span class="meta-label">创建时间</span>
                <strong class="meta-value">{{ formatDateTime(detail.created_at) }}</strong>
              </div>
            </section>

            <section v-if="!isClosed(detail.status)" class="action-section">
              <div class="action-header">
                <span class="action-title">处理操作</span>
                <span class="action-subtitle">写回复并上传图片</span>
              </div>
              <div class="action-body">
                <div class="reply-composer">
                  <el-upload
                    class="reply-upload"
                    accept=".jpg,.jpeg,.png,.webp"
                    multiple
                    :show-file-list="false"
                    :http-request="handleReplyUpload"
                    :before-upload="beforeReplyUpload"
                    :on-exceed="handleReplyUploadExceed"
                    :limit="MAX_TICKET_IMAGES"
                  >
                    <button class="reply-image-button" type="button" :disabled="replyUploadDisabled">
                      <el-icon><Plus /></el-icon>
                    </button>
                  </el-upload>
                  <el-input
                    v-model="replyForm.content"
                    type="textarea"
                    :autosize="{ minRows: 3, maxRows: 7 }"
                    maxlength="10000"
                    placeholder="输入处理回复内容..."
                    class="reply-composer-input"
                  />
                  <el-button
                    type="primary"
                    class="reply-submit-button"
                    :loading="replyLoading"
                    :disabled="replySubmitDisabled"
                    @click="handleReply"
                  >
                    <el-icon><Promotion /></el-icon>
                    回复
                  </el-button>
                </div>

                <div v-if="replyAttachments.length" class="reply-draft-list">
                  <button
                    v-for="attachment in replyAttachments"
                    :key="attachment.id"
                    type="button"
                    class="reply-draft-thumb"
                    @click="handleDraftAttachmentPreview({ ...attachment, uid: attachment.id })"
                  >
                    <img :src="attachment.url" alt="附件" />
                    <span class="reply-draft-remove" @click.stop="removeReplyAttachment(attachment.id)">×</span>
                  </button>
                </div>
              </div>
            </section>

            <section v-else class="closed-notice">
              <el-icon><CircleClose /></el-icon>
              <span>此工单已关闭</span>
            </section>

            <section class="linked-service-panel">
              <div class="side-panel-head">
                <strong>关联服务信息</strong>
                <span>ID {{ linkedServiceId }}</span>
              </div>
              <div class="service-info-grid">
                <div class="service-info-item">
                  <span>商品名称</span>
                  <strong>{{ linkedServiceDisplayName }}</strong>
                </div>
                <div class="service-info-item">
                  <span>公网 IP</span>
                  <strong class="mono copyable" @click="copyText(linkedServiceConnection.dedicated_ip)">
                    {{ linkedServiceConnection.dedicated_ip || '--' }}
                  </strong>
                </div>
                <div class="service-info-item">
                  <span>登录账号</span>
                  <strong class="mono copyable" @click="copyText(linkedServiceConnection.username)">
                    {{ linkedServiceConnection.username || '--' }}
                  </strong>
                </div>
                <div class="service-info-item">
                  <span>登录密码</span>
                  <strong class="mono password-value">
                    <template v-if="linkedServiceConnection.has_password">
                      <span>{{ servicePasswordVisible ? linkedServiceConnection.password : '••••••••' }}</span>
                      <el-button link :icon="servicePasswordVisible ? Hide : View" @click="toggleServicePassword" />
                      <el-button link :icon="CopyDocument" @click="copyText(linkedServiceConnection.password)" />
                    </template>
                    <span v-else>--</span>
                  </strong>
                </div>
                <div class="service-info-item">
                  <span>登录端口</span>
                  <strong class="mono">{{ linkedServiceConnection.port || '--' }}</strong>
                </div>
                <div class="service-info-item">
                  <span>到期时间</span>
                  <strong>{{ detail.service?.expires_at || '--' }}</strong>
                </div>
              </div>
              <div class="service-spec-panel">
                <span class="service-spec-title">规格</span>
                <div v-if="linkedServiceSpecs.length" class="service-spec-list">
                  <div v-for="item in linkedServiceSpecs" :key="item.label" class="service-spec-item">
                    <span>{{ item.label }}</span>
                    <strong>{{ item.value }}</strong>
                  </div>
                </div>
                <div v-else class="service-empty-slot">暂无规格信息</div>
              </div>
            </section>
          </aside>
        </main>
      </template>

      <el-empty v-else-if="!detailLoading" description="工单不存在">
        <el-button type="primary" @click="goBack">返回工单列表</el-button>
      </el-empty>
    </div>

    <el-dialog v-model="replyPreviewVisible" title="附件预览" width="720px" append-to-body>
      <img v-if="replyPreviewUrl" :src="replyPreviewUrl" class="preview-image" alt="预览" />
    </el-dialog>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ArrowLeft, CircleClose, Close, CopyDocument, Hide, Plus, Promotion, View } from '@element-plus/icons-vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import adminApi from '@/api/admin'
import { formatDateTime } from '@/utils/datetime'
import { TICKET_STATUS_MAP, getStatusLabel, getStatusTagType, resolveElTagType } from '@shared/statusConfig'

const MAX_TICKET_IMAGES = 9
const IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp']

const route = useRoute()
const router = useRouter()

const priorityOptions = [
  { label: '低', value: 1 },
  { label: '中', value: 2 },
  { label: '高', value: 3 },
  { label: '紧急', value: 4 },
]

const departmentOptions = [
  { label: '销售', value: 'sales' },
  { label: '技术支持', value: 'support' },
  { label: '财务', value: 'billing' },
  { label: '投诉', value: 'abuse' },
]

const detailLoading = ref(false)
const detail = ref(null)
const replyPreviewVisible = ref(false)
const replyPreviewUrl = ref('')
const replyForm = reactive({ content: '' })
const replyAttachments = ref([])
const replyUploadingCount = ref(0)
const assignForm = reactive({ assignee_id: null })
const replyLoading = ref(false)
const assignLoading = ref(false)
const closeLoading = ref(false)
const adminUsers = ref([])
const servicePasswordVisible = ref(false)

const currentAssigneeId = computed(() => (
  detail.value?.assignee_id ? Number(detail.value.assignee_id) : null
))

const replyUploadDisabled = computed(() => (
  replyAttachments.value.length + replyUploadingCount.value >= MAX_TICKET_IMAGES
))

const replySubmitDisabled = computed(() => (
  replyUploadingCount.value > 0 || (!replyForm.content.trim() && replyAttachments.value.length === 0)
))

const linkedServiceId = computed(() => detail.value?.service?.id || detail.value?.service_id || '--')

const linkedServiceDisplayName = computed(() => (
  detail.value?.service?.display_name
  || detail.value?.service?.product_name
  || detail.value?.service?.name
  || '--'
))

const linkedServiceConnection = computed(() => ({
  dedicated_ip: '',
  internal_ip: '',
  username: '',
  password: '',
  has_password: false,
  port: 0,
  ...(detail.value?.service?.connection || {}),
}))

const linkedServiceSpecs = computed(() => {
  const specs = detail.value?.service?.specs
  if (!Array.isArray(specs)) return []

  return specs.filter((item) => item && (item.label || item.name))
    .map((item) => ({
      label: item.label || item.name,
      value: item.value ?? item.text ?? '--',
    }))
})

function resolveTicketId() {
  return Number(route.params.id || 0)
}

function departmentLabel(value) {
  return departmentOptions.find((item) => item.value === value)?.label || value || '--'
}

function priorityLabel(value) {
  return priorityOptions.find((item) => item.value === Number(value))?.label || '--'
}

function statusLabel(value) {
  return getStatusLabel(TICKET_STATUS_MAP, Number(value))
}

function adminOptionLabel(admin) {
  return admin.email
    ? `${admin.nickname}（${admin.email}）`
    : `${admin.nickname}（未绑定邮箱）`
}

function statusType(value) {
  return resolveElTagType(getStatusTagType(TICKET_STATUS_MAP, Number(value)))
}

function isClosed(status) {
  return Number(status) === 3
}

function hasAttachments(item) {
  const attachments = item?.attachments || item?.attachment_urls
  return Array.isArray(attachments) && attachments.length > 0
}

function parseAttachments(item) {
  const attachments = item?.attachments || item?.attachment_urls || []
  return attachments.map((url, index) => ({
    id: index,
    url: typeof url === 'string' ? url : url.url,
    name: typeof url === 'string' ? null : url.name,
    deleted: typeof url === 'string' ? false : url.deleted,
  }))
}

function getAttachmentUrls(reply) {
  return parseAttachments(reply).filter((a) => a.url).map((a) => a.url)
}

function getAttachmentIndex(reply, att) {
  return getAttachmentUrls(reply).findIndex((url) => url === att.url)
}

function normalizeAttachmentPayload(attachments) {
  return attachments.map((item) => item.path)
}

function validateImageFile(file, currentCount, uploadingCount) {
  if (!IMAGE_TYPES.includes(file.type)) {
    ElMessage.warning('仅支持 jpg、png、webp 图片')
    return false
  }

  if (file.size / 1024 / 1024 > 5) {
    ElMessage.warning('单张图片不能超过 5MB')
    return false
  }

  if (currentCount + uploadingCount >= MAX_TICKET_IMAGES) {
    ElMessage.warning(`最多上传 ${MAX_TICKET_IMAGES} 张图片`)
    return false
  }

  return true
}

function beforeReplyUpload(file) {
  return validateImageFile(file, replyAttachments.value.length, replyUploadingCount.value)
}

function removeReplyAttachment(id) {
  const targetId = String(id)
  replyAttachments.value = replyAttachments.value.filter((item) => String(item.id) !== targetId)
}

function handleDraftAttachmentPreview(file) {
  replyPreviewUrl.value = file.url || ''
  replyPreviewVisible.value = replyPreviewUrl.value !== ''
}

function handleReplyUploadExceed() {
  ElMessage.warning(`最多上传 ${MAX_TICKET_IMAGES} 张图片`)
}

function handleAttachmentPreview(att) {
  replyPreviewUrl.value = att.url || ''
  replyPreviewVisible.value = !!replyPreviewUrl.value
}

function resetReplyDraft() {
  replyForm.content = ''
  replyAttachments.value = []
}

function goBack() {
  router.push('/admin/tickets')
}

function goUserDetail(userId) {
  if (!userId) return
  router.push(`/admin/users/${userId}`)
}

function toggleServicePassword() {
  servicePasswordVisible.value = !servicePasswordVisible.value
}

async function copyText(text) {
  const value = String(text || '').trim()
  if (!value || !navigator?.clipboard) return
  await navigator.clipboard.writeText(value)
  ElMessage.success('已复制')
}

async function handleReplyUpload(options) {
  const formData = new FormData()
  formData.append('file', options.file)
  replyUploadingCount.value += 1

  try {
    const res = await adminApi.tickets.uploadImage(formData)
    replyAttachments.value = [...replyAttachments.value, res.data].slice(0, MAX_TICKET_IMAGES)
    options.onSuccess?.({}, options.file)
  } catch (error) {
    options.onError?.(error)
  } finally {
    replyUploadingCount.value = Math.max(0, replyUploadingCount.value - 1)
  }
}

async function loadAdmins() {
  const res = await adminApi.tickets.adminUsers()
  adminUsers.value = res.data || []
}

async function loadDetail() {
  const id = resolveTicketId()
  if (!id) {
    ElMessage.error('工单不存在')
    await router.replace('/admin/tickets')
    return
  }

  detailLoading.value = true
  detail.value = null
  assignForm.assignee_id = null
  resetReplyDraft()

  try {
    const res = await adminApi.tickets.detail(id)
    detail.value = res.data
    assignForm.assignee_id = res.data?.assignee_id ? Number(res.data.assignee_id) : null
  } finally {
    detailLoading.value = false
  }
}

async function reloadCurrentDetail() {
  if (!detail.value?.id) return
  const res = await adminApi.tickets.detail(detail.value.id)
  detail.value = res.data
  assignForm.assignee_id = res.data?.assignee_id ? Number(res.data.assignee_id) : null
}

async function handleAssign() {
  if (!detail.value?.id) return
  if (!assignForm.assignee_id) {
    ElMessage.warning('请选择处理人')
    return
  }
  if (assignForm.assignee_id === currentAssigneeId.value) {
    ElMessage.warning('处理人未发生变化')
    return
  }

  assignLoading.value = true
  try {
    await adminApi.tickets.assign(detail.value.id, { assignee_id: assignForm.assignee_id })
    ElMessage.success('指派成功')
    await reloadCurrentDetail()
  } catch (error) {
    ElMessage.error(error?.response?.data?.message || '指派失败')
  } finally {
    assignLoading.value = false
  }
}

async function handleReply() {
  if (!detail.value?.id) return
  if (!replyForm.content.trim() && replyAttachments.value.length === 0) {
    ElMessage.warning('请输入回复内容或上传图片')
    return
  }

  replyLoading.value = true
  try {
    await adminApi.tickets.reply(detail.value.id, {
      content: replyForm.content,
      attachments: normalizeAttachmentPayload(replyAttachments.value),
    })
    resetReplyDraft()
    ElMessage.success('回复成功')
    await reloadCurrentDetail()
  } catch (error) {
    ElMessage.error(error?.response?.data?.message || '回复失败')
  } finally {
    replyLoading.value = false
  }
}

async function handleClose() {
  if (!detail.value?.id) return

  try {
    await ElMessageBox.confirm('确认关闭该工单吗？关闭后图片会被物理删除。', '关闭工单', {
      type: 'warning',
      confirmButtonText: '确认关闭',
      cancelButtonText: '取消',
    })
  } catch {
    return
  }

  closeLoading.value = true
  try {
    await adminApi.tickets.close(detail.value.id)
    ElMessage.success('工单已关闭')
    await reloadCurrentDetail()
  } catch (error) {
    ElMessage.error(error?.response?.data?.message || '关闭失败')
  } finally {
    closeLoading.value = false
  }
}

onMounted(() => {
  void Promise.allSettled([loadAdmins(), loadDetail()])
})
</script>

<style scoped lang="scss">
.ticket-conversation-page {
  gap: 12px;
  min-height: 0;
  height: 100%;
  overflow: hidden;
}

.conversation-head {
  align-items: flex-start;
  padding-bottom: 10px;
}

.back-button {
  align-self: flex-start;
  padding-left: 0;
  min-height: 28px;
}

.title-row {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;

  h2 {
    margin: 0;
    font-size: 20px;
    line-height: 1.25;
  }
}

.header-close-button {
  margin-left: auto;
}

.ticket-id-pill {
  display: inline-flex;
  align-items: center;
  height: 24px;
  padding: 0 9px;
  color: $text-color-secondary;
  font-size: 12px;
  font-weight: 600;
  background: #eef3fb;
  border: 1px solid #dbe4f1;
  border-radius: 6px;
}

.conversation-body {
  display: flex;
  flex-direction: column;
  gap: 12px;
  min-height: 0;
  flex: 1;
  overflow: hidden;
}

.conversation-workbench {
  display: grid;
  grid-template-columns: minmax(0, 3fr) minmax(360px, 2fr);
  gap: 12px;
  min-height: 0;
  height: 100%;
  align-items: stretch;
  overflow: hidden;
}

.ticket-side-panel {
  display: grid;
  grid-template-rows: auto minmax(0, 1fr) auto;
  gap: 12px;
  min-width: 0;
  min-height: 0;
  overflow: hidden;
}

.admin-ticket-meta {
  display: grid;
  grid-template-columns: 1fr;
  gap: 0;
  overflow: hidden;
  background: #fff;
  border: 1px solid $border-color;
  border-radius: 8px;
}

.meta-item {
  display: grid;
  grid-template-columns: 92px minmax(0, 1fr);
  align-items: center;
  min-width: 0;
  min-height: 42px;
  padding: 0 12px;
  border-right: 1px solid #e5eaf3;
  border-bottom: 1px solid #e5eaf3;
  background: #fff;
}

.meta-item:nth-child(3n) {
  border-right: 1px solid #e5eaf3;
}

.meta-item:nth-child(n) {
  border-right: none;
}

.meta-item:nth-last-child(-n + 3) {
  border-bottom: 1px solid #e5eaf3;
}

.meta-item:last-child {
  border-bottom: none;
}

.meta-label {
  display: inline-flex;
  align-items: center;
  align-self: stretch;
  margin: 0 12px 0 -12px;
  padding-left: 12px;
  color: $text-color-placeholder;
  font-size: 12px;
  background: #f7f9fc;
  border-right: 1px solid #e5eaf3;
}

.meta-value {
  display: block;
  width: 100%;
  overflow: hidden;
  color: $text-color-primary;
  font-size: 14px;
  font-weight: 600;
  line-height: 1.35;
  text-align: left;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.meta-link {
  padding: 0;
  border: none;
  cursor: pointer;
  background: transparent;
  color: $color-primary;
}

.meta-assignee {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  align-items: center;
  gap: 8px;
  min-width: 0;
}

.meta-assignee-select {
  min-width: 0;

  :deep(.el-select__wrapper) {
    min-height: 32px;
    border-radius: 6px;
  }
}

.admin-conversation-panel {
  display: flex;
  flex-direction: column;
  min-height: 0;
  padding: 12px 14px 14px;
  background: #fff;
  border: 1px solid $border-color;
  border-radius: 8px;
}

.conversation-title-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding-bottom: 12px;
  color: $text-color-primary;
  font-size: 14px;
  font-weight: 600;
}

.conversation-title-row span:last-child {
  color: $text-color-placeholder;
  font-size: 12px;
  font-weight: 400;
}

.admin-message-list {
  display: flex;
  flex: 1;
  flex-direction: column;
  gap: 12px;
  min-height: 0;
  overflow-y: auto;
  padding-right: 4px;
}

.admin-message {
  display: flex;
}

.admin-message--staff {
  justify-content: flex-end;
}

.admin-message__body {
  max-width: 76%;
  min-width: 0;
}

.admin-message__meta {
  display: flex;
  gap: 8px;
  margin-bottom: 5px;
  color: $text-color-placeholder;
  font-size: 12px;
}

.admin-message--staff .admin-message__meta {
  justify-content: flex-end;
}

.admin-message__bubble {
  padding: 10px 13px;
  border: 1px solid #dce5f2;
  border-radius: 4px 12px 12px;
  background: #fff;
  box-shadow: 0 6px 14px rgba(15, 23, 42, 0.04);
}

.admin-message--staff .admin-message__bubble {
  border-color: #c9dcff;
  border-radius: 12px 4px 12px 12px;
  background: #eaf2ff;
}

.content-text,
.reply-content {
  font-size: 14px;
  line-height: 1.7;
  color: $text-color-primary;
  white-space: pre-wrap;
}

.content-attachments,
.reply-attachments {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 10px;
}

.content-attachment,
.reply-attachment {
  width: 64px;
  height: 64px;
  padding: 0;
  overflow: hidden;
  border: 1px solid $border-color;
  border-radius: 6px;
  cursor: pointer;
  background: #fff;
  transition: transform 0.2s;

  &:hover {
    transform: scale(1.05);
  }

  :deep(.el-image) {
    width: 100%;
    height: 100%;
  }
}

.reply-attachment--deleted {
  display: flex;
  align-items: center;
  justify-content: center;
  background: $bg-color-soft;
  border-style: dashed;
}

.deleted-placeholder {
  font-size: 11px;
  color: $text-color-placeholder;
}

.action-section {
  display: flex;
  flex-direction: column;
  min-height: 0;
  overflow: hidden;
  border: 1px solid #dfe5ef;
  border-radius: 8px;
  background: #fff;
  box-shadow: none;
}

.action-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 11px 16px;
  background: #fbfcff;
  border-bottom: 1px solid #e6ebf3;
}

.action-title {
  font-size: 14px;
  font-weight: 600;
  color: $text-color-primary;
}

.action-subtitle {
  font-size: 12px;
  color: $text-color-placeholder;
}

.action-body {
  display: flex;
  flex: 1;
  flex-direction: column;
  gap: 10px;
  min-height: 0;
  padding: 12px 14px 14px;
  background: #fff;
  overflow: hidden;
}

.reply-composer {
  display: grid;
  grid-template-columns: 42px minmax(0, 1fr) 104px;
  align-items: center;
  gap: 10px;
  min-height: 54px;
  padding: 6px;
  border: 1px solid #d9e0ea;
  border-radius: 10px;
  background: #fff;
}

.reply-upload {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 40px;

  :deep(.el-upload) {
    display: inline-flex;
  }
}

.reply-image-button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 42px;
  height: 42px;
  padding: 0;
  color: #637083;
  cursor: pointer;
  background: #fbfdff;
  border: 1px dashed #cfd8e6;
  border-radius: 50%;
  transition:
    transform 0.18s cubic-bezier(0.22, 1, 0.36, 1),
    border-color 0.18s cubic-bezier(0.22, 1, 0.36, 1),
    color 0.18s cubic-bezier(0.22, 1, 0.36, 1),
    background 0.18s cubic-bezier(0.22, 1, 0.36, 1);

  .el-icon {
    font-size: 22px;
    transition: transform 0.18s cubic-bezier(0.22, 1, 0.36, 1);
  }

  &:not(:disabled):hover {
    color: #2563eb;
    border-color: #9fbcff;
    background: #f8fbff;
    transform: translateY(-1px) scale(1.04);
  }

  &:not(:disabled):hover .el-icon {
    transform: rotate(90deg);
  }

  &:disabled {
    color: #b5b5b5;
    cursor: not-allowed;
    border-color: #d0d0d0;
  }
}

.reply-composer-input {
  min-height: 0;

  :deep(.el-textarea__inner) {
    min-height: 40px !important;
    max-height: 140px;
    padding: 9px 4px;
    resize: none;
    border: none;
    box-shadow: none;
    line-height: 1.45;
  }

  :deep(.el-input__count) {
    display: none;
  }
}

.reply-submit-button {
  justify-self: stretch;
  width: 104px;
  height: 40px;
  border-radius: 8px;
}

.reply-draft-list {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}

.reply-draft-thumb {
  position: relative;
  width: 46px;
  height: 46px;
  padding: 0;
  overflow: hidden;
  cursor: pointer;
  background: #fff;
  border: 1px solid #d7dce5;
  border-radius: 7px;
}

.reply-draft-thumb img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.reply-draft-remove {
  position: absolute;
  top: -1px;
  right: -1px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 16px;
  height: 16px;
  color: #fff;
  font-size: 12px;
  line-height: 1;
  background: rgba(17, 24, 39, 0.72);
  border-radius: 0 7px 0 7px;
}

.closed-notice {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 14px;
  color: $text-color-secondary;
  background: #fff;
  border: 1px solid $border-color;
  border-radius: 8px;
}

.linked-service-panel {
  flex-shrink: 0;
  overflow: hidden;
  background: #fff;
  border: 1px solid $border-color;
  border-radius: 8px;
}

.side-panel-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  padding: 11px 14px;
  background: #fbfcff;
  border-bottom: 1px solid #e6ebf3;

  strong {
    color: $text-color-primary;
    font-size: 14px;
  }

  span {
    color: $text-color-placeholder;
    font-size: 12px;
  }
}

.service-info-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0;
}

.service-info-item {
  display: flex;
  flex-direction: column;
  gap: 4px;
  min-width: 0;
  min-height: 52px;
  padding: 8px 12px;
  border-right: 1px solid #e5eaf3;
  border-bottom: 1px solid #e5eaf3;

  &:nth-child(2n) {
    border-right: none;
  }

  span {
    color: $text-color-placeholder;
    font-size: 12px;
  }

  strong {
    overflow: hidden;
    color: $text-color-primary;
    font-size: 13px;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
}

.mono {
  font-family: "SFMono-Regular", Consolas, "Liberation Mono", monospace;
}

.copyable {
  cursor: pointer;
}

.password-value {
  display: inline-flex;
  align-items: center;
  gap: 4px;

  :deep(.el-button) {
    height: 20px;
    padding: 0 2px;
  }
}

.service-spec-panel {
  padding: 10px 12px 12px;
}

.service-spec-title {
  display: block;
  margin-bottom: 8px;
  color: $text-color-placeholder;
  font-size: 12px;
}

.service-spec-list {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 8px;
}

.service-spec-item,
.service-empty-slot {
  min-height: 38px;
  padding: 7px 9px;
  background: #f8fafc;
  border: 1px dashed #d8e1ee;
  border-radius: 7px;
}

.service-spec-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;

  span {
    color: $text-color-placeholder;
    font-size: 12px;
  }

  strong {
    overflow: hidden;
    color: $text-color-primary;
    font-size: 13px;
    text-align: right;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
}

.service-empty-slot {
  display: flex;
  align-items: center;
  color: $text-color-placeholder;
  font-size: 12px;
}

.preview-image {
  width: 100%;
  display: block;
}

@media (max-width: 900px) {
  .ticket-conversation-page {
    height: auto;
    overflow: visible;
  }

  .conversation-body,
  .conversation-workbench,
  .ticket-side-panel,
  .action-body {
    overflow: visible;
  }

  .conversation-workbench {
    grid-template-columns: 1fr;
    min-height: 0;
    height: auto;
  }

  .ticket-side-panel {
    grid-template-rows: auto auto auto;
  }

  .admin-ticket-meta {
    grid-template-columns: 1fr;
  }

  .meta-assignee {
    grid-template-columns: 1fr;
  }

  .admin-conversation-panel {
    min-height: 360px;
  }

  .service-info-grid,
  .service-spec-list {
    grid-template-columns: 1fr;
  }

  .service-info-item,
  .service-info-item:nth-child(2n) {
    border-right: none;
  }

  .header-close-button {
    margin-left: 0;
  }

  .reply-composer {
    grid-template-columns: 42px minmax(0, 1fr) 82px;
    min-height: 50px;
  }

  .reply-upload {
    align-items: center;
  }

  .reply-composer-input :deep(.el-textarea__inner) {
    min-height: 38px !important;
  }

  .admin-message__body {
    max-width: 92%;
  }
}
</style>
