<template>
  <el-dialog
    v-if="!embedded"
    v-model="visible"
    :title="null"
    width="900px"
    class="ticket-detail-dialog"
    :class="{ 'is-mobile-dialog': isMobile }"
    :close-on-click-modal="false"
  >
    <div v-loading="detailLoading" class="ticket-panel" :class="{ 'is-mobile': isMobile }">
      <el-button
        v-if="detail?.status !== 3 && !isMobile"
        class="manual-close-button"
        plain
        :loading="closing"
        @click="handleClose"
      >
        关闭工单
      </el-button>

      <!-- 移动端 Tab 切换 -->
      <div v-if="isMobile" class="mobile-tab-bar">
        <button :class="{ active: mobileTab === 'chat' }" @click="mobileTab = 'chat'">交流</button>
        <button :class="{ active: mobileTab === 'detail' }" @click="mobileTab = 'detail'">详情</button>
      </div>

      <!-- ========== 桌面端布局（不变） ========== -->
      <template v-if="!isMobile">
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
      </template>

      <!-- ========== 移动端：交流 Tab ========== -->
      <template v-if="isMobile && mobileTab === 'chat'">
        <section class="mobile-conversation-section">
          <div class="mobile-message-list" ref="messageListRef">
            <div v-if="detail?.content" class="mobile-message mobile-message--customer">
              <div class="mobile-message__bubble-wrap">
                <div class="mobile-message__meta">
                  <span>{{ detail.user?.display_name || '我' }}</span>
                  <span>{{ formatDateTime(detail?.created_at) }}</span>
                </div>
                <div class="mobile-message__bubble">
                  <div class="mobile-message__content">{{ detail.content }}</div>
                  <div v-if="hasAttachments(detail)" class="mobile-message__images">
                    <div
                      v-for="att in parseAttachments(detail)"
                      :key="att.id"
                      class="mobile-message__img"
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
              class="mobile-message"
              :class="reply.is_staff ? 'mobile-message--staff' : 'mobile-message--customer'"
            >
              <div class="mobile-message__bubble-wrap">
                <div class="mobile-message__meta">
                  <span>{{ reply.sender_name || (reply.is_staff ? '客服' : '我') }}</span>
                  <span>{{ reply.created_at }}</span>
                </div>
                <div class="mobile-message__bubble">
                  <div class="mobile-message__content">{{ reply.content || '无文字内容' }}</div>
                  <div v-if="hasAttachments(reply)" class="mobile-message__images">
                    <div
                      v-for="att in parseAttachments(reply)"
                      :key="att.id"
                      class="mobile-message__img"
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

        <section class="mobile-reply-section" v-if="detail?.status !== 3">
          <div v-if="replyAttachments.length" class="mobile-draft-images">
            <button
              v-for="attachment in replyAttachments"
              :key="attachment.id || attachment.uid"
              class="mobile-draft-img"
              type="button"
              @click="handleDraftAttachmentPreview(attachment)"
            >
              <img :src="attachment.url" alt="附件" />
              <span class="mobile-draft-remove" @click.stop="removeReplyAttachment(attachment.id || attachment.uid)">×</span>
            </button>
          </div>

          <div class="mobile-composer-bar">
            <el-upload
              class="mobile-composer-upload"
              accept=".jpg,.jpeg,.png,.webp"
              multiple
              :show-file-list="false"
              :http-request="handleReplyUpload"
              :before-upload="beforeReplyUpload"
              :on-exceed="handleReplyUploadExceed"
              :limit="MAX_IMAGES"
              :disabled="replying"
            >
              <button class="mobile-plus-btn" type="button" :disabled="replyUploadDisabled || replying">+</button>
            </el-upload>
            <textarea
              v-model="replyContent"
              class="mobile-textarea"
              rows="1"
              maxlength="5000"
              placeholder="输入回复内容..."
              @keydown.enter.exact.prevent="handleReply"
            ></textarea>
            <button
              class="mobile-send-btn"
              :disabled="replySubmittingDisabled"
              :class="{ 'is-loading': replying }"
              @click="handleReply"
            >
              发送
            </button>
          </div>
        </section>

        <div v-if="detail?.status === 3" class="mobile-closed-notice">
          <el-icon><CircleCheck /></el-icon>
          <span>
            此工单已关闭
            {{ detail?.close_reason_label ? `（${detail.close_reason_label}）` : '' }}
          </span>
        </div>
      </template>

      <!-- ========== 移动端：详情 Tab ========== -->
      <template v-if="isMobile && mobileTab === 'detail'">
        <div class="mobile-detail-panel">
          <div class="mobile-detail-card">
            <div class="mobile-detail-card__head">
              <span>工单信息</span>
              <span class="mobile-detail-card__sub">#{{ detail?.id || '--' }}</span>
            </div>
            <div class="mobile-detail-card__body">
              <div class="mobile-user-summary">
                <div class="mobile-user-summary__avatar">{{ userName[0] }}</div>
                <div class="mobile-user-summary__info">
                  <span class="mobile-user-summary__name">{{ userName }}</span>
                  <span class="mobile-user-summary__id">ID {{ detail?.user?.id || detail?.user_id || '--' }}</span>
                </div>
              </div>
              <div class="mobile-meta-grid">
                <div class="mobile-meta-cell">
                  <span class="mobile-meta-cell__label"><span class="mobile-dot mobile-dot--purple"></span>工单分类</span>
                  <span class="mobile-meta-cell__value">{{ resolveDepartmentLabel(detail?.department) }}</span>
                </div>
                <div class="mobile-meta-cell">
                  <span class="mobile-meta-cell__label"><span class="mobile-dot mobile-dot--danger"></span>优先级</span>
                  <span class="mobile-meta-cell__value" :style="{ color: detail?.priority >= 3 ? '#DC2626' : '' }">{{ resolvePriorityLabel(detail?.priority) }}</span>
                </div>
                <div class="mobile-meta-cell">
                  <span class="mobile-meta-cell__label"><span class="mobile-dot mobile-dot--blue"></span>状态</span>
                  <span class="mobile-meta-cell__value">{{ props.resolveTicketStatusLabel?.(detail?.status) || '--' }}</span>
                </div>
                <div class="mobile-meta-cell">
                  <span class="mobile-meta-cell__label">处理人</span>
                  <span class="mobile-meta-cell__value">{{ assigneeName }}</span>
                </div>
                <div class="mobile-meta-cell" style="grid-column: 1 / -1; border-right: none;">
                  <span class="mobile-meta-cell__label">关联服务</span>
                  <span class="mobile-meta-cell__value" style="font-family:monospace;font-size:12px">
                    {{ detail?.service?.id || detail?.service_id || '--' }}
                    <template v-if="detail?.service?.display_name || detail?.service?.product_name">
                      （{{ detail?.service?.display_name || detail?.service?.product_name }}）
                    </template>
                  </span>
                </div>
                <div class="mobile-meta-cell">
                  <span class="mobile-meta-cell__label">创建时间</span>
                  <span class="mobile-meta-cell__value">{{ formatDateTime(detail?.created_at) }}</span>
                </div>
                <div class="mobile-meta-cell">
                  <span class="mobile-meta-cell__label">更新时间</span>
                  <span class="mobile-meta-cell__value">{{ formatDateTime(detail?.updated_at) }}</span>
                </div>
              </div>
            </div>
          </div>

          <button
            v-if="detail?.status !== 3"
            class="mobile-close-btn"
            :disabled="closing"
            @click="handleClose"
          >
            关闭工单
          </button>
        </div>
      </template>
    </div>
  </el-dialog>

  <div v-else class="ticket-detail-embedded" :class="{ 'is-mobile-embedded': isMobile }">
    <div v-loading="detailLoading" class="ticket-panel ticket-panel--embedded" :class="{ 'is-mobile': isMobile }">
      <el-button
        v-if="detail?.status !== 3 && !isMobile"
        class="manual-close-button"
        plain
        :loading="closing"
        @click="handleClose"
      >
        关闭工单
      </el-button>

      <!-- 移动端 Tab 切换 -->
      <div v-if="isMobile" class="mobile-tab-bar">
        <button :class="{ active: mobileTab === 'chat' }" @click="mobileTab = 'chat'">交流</button>
        <button :class="{ active: mobileTab === 'detail' }" @click="mobileTab = 'detail'">详情</button>
      </div>

      <!-- ========== 桌面端布局 ========== -->
      <template v-if="!isMobile">
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
      </template>

      <!-- ========== 移动端：交流 Tab（embedded） ========== -->
      <template v-if="isMobile && mobileTab === 'chat'">
        <section class="mobile-conversation-section">
          <div class="mobile-message-list" ref="messageListRef">
            <div v-if="detail?.content" class="mobile-message mobile-message--customer">
              <div class="mobile-message__bubble-wrap">
                <div class="mobile-message__meta">
                  <span>{{ detail.user?.display_name || '我' }}</span>
                  <span>{{ formatDateTime(detail?.created_at) }}</span>
                </div>
                <div class="mobile-message__bubble">
                  <div class="mobile-message__content">{{ detail.content }}</div>
                  <div v-if="hasAttachments(detail)" class="mobile-message__images">
                    <div
                      v-for="att in parseAttachments(detail)"
                      :key="att.id"
                      class="mobile-message__img"
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
              class="mobile-message"
              :class="reply.is_staff ? 'mobile-message--staff' : 'mobile-message--customer'"
            >
              <div class="mobile-message__bubble-wrap">
                <div class="mobile-message__meta">
                  <span>{{ reply.sender_name || (reply.is_staff ? '客服' : '我') }}</span>
                  <span>{{ reply.created_at }}</span>
                </div>
                <div class="mobile-message__bubble">
                  <div class="mobile-message__content">{{ reply.content || '无文字内容' }}</div>
                  <div v-if="hasAttachments(reply)" class="mobile-message__images">
                    <div
                      v-for="att in parseAttachments(reply)"
                      :key="att.id"
                      class="mobile-message__img"
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

        <section class="mobile-reply-section" v-if="detail?.status !== 3">
          <div v-if="replyAttachments.length" class="mobile-draft-images">
            <button
              v-for="attachment in replyAttachments"
              :key="attachment.id || attachment.uid"
              class="mobile-draft-img"
              type="button"
              @click="handleDraftAttachmentPreview(attachment)"
            >
              <img :src="attachment.url" alt="附件" />
              <span class="mobile-draft-remove" @click.stop="removeReplyAttachment(attachment.id || attachment.uid)">×</span>
            </button>
          </div>

          <div class="mobile-composer-bar">
            <el-upload
              class="mobile-composer-upload"
              accept=".jpg,.jpeg,.png,.webp"
              multiple
              :show-file-list="false"
              :http-request="handleReplyUpload"
              :before-upload="beforeReplyUpload"
              :on-exceed="handleReplyUploadExceed"
              :limit="MAX_IMAGES"
              :disabled="replying"
            >
              <button class="mobile-plus-btn" type="button" :disabled="replyUploadDisabled || replying">+</button>
            </el-upload>
            <textarea
              v-model="replyContent"
              class="mobile-textarea"
              rows="1"
              maxlength="5000"
              placeholder="输入回复内容..."
              @keydown.enter.exact.prevent="handleReply"
            ></textarea>
            <button
              class="mobile-send-btn"
              :disabled="replySubmittingDisabled"
              :class="{ 'is-loading': replying }"
              @click="handleReply"
            >
              发送
            </button>
          </div>
        </section>

        <div v-if="detail?.status === 3" class="mobile-closed-notice">
          <el-icon><CircleCheck /></el-icon>
          <span>
            此工单已关闭
            {{ detail?.close_reason_label ? `（${detail.close_reason_label}）` : '' }}
          </span>
        </div>
      </template>

      <!-- ========== 移动端：详情 Tab（embedded） ========== -->
      <template v-if="isMobile && mobileTab === 'detail'">
        <div class="mobile-detail-panel">
          <div class="mobile-detail-card">
            <div class="mobile-detail-card__head">
              <span>工单信息</span>
              <span class="mobile-detail-card__sub">#{{ detail?.id || '--' }}</span>
            </div>
            <div class="mobile-detail-card__body">
              <div class="mobile-user-summary">
                <div class="mobile-user-summary__avatar">{{ userName[0] }}</div>
                <div class="mobile-user-summary__info">
                  <span class="mobile-user-summary__name">{{ userName }}</span>
                  <span class="mobile-user-summary__id">ID {{ detail?.user?.id || detail?.user_id || '--' }}</span>
                </div>
              </div>
              <div class="mobile-meta-grid">
                <div class="mobile-meta-cell">
                  <span class="mobile-meta-cell__label"><span class="mobile-dot mobile-dot--purple"></span>工单分类</span>
                  <span class="mobile-meta-cell__value">{{ resolveDepartmentLabel(detail?.department) }}</span>
                </div>
                <div class="mobile-meta-cell">
                  <span class="mobile-meta-cell__label"><span class="mobile-dot mobile-dot--danger"></span>优先级</span>
                  <span class="mobile-meta-cell__value" :style="{ color: detail?.priority >= 3 ? '#DC2626' : '' }">{{ resolvePriorityLabel(detail?.priority) }}</span>
                </div>
                <div class="mobile-meta-cell">
                  <span class="mobile-meta-cell__label"><span class="mobile-dot mobile-dot--blue"></span>状态</span>
                  <span class="mobile-meta-cell__value">{{ props.resolveTicketStatusLabel?.(detail?.status) || '--' }}</span>
                </div>
                <div class="mobile-meta-cell">
                  <span class="mobile-meta-cell__label">处理人</span>
                  <span class="mobile-meta-cell__value">{{ assigneeName }}</span>
                </div>
                <div class="mobile-meta-cell" style="grid-column: 1 / -1; border-right: none;">
                  <span class="mobile-meta-cell__label">关联服务</span>
                  <span class="mobile-meta-cell__value" style="font-family:monospace;font-size:12px">
                    {{ detail?.service?.id || detail?.service_id || '--' }}
                    <template v-if="detail?.service?.display_name || detail?.service?.product_name">
                      （{{ detail?.service?.display_name || detail?.service?.product_name }}）
                    </template>
                  </span>
                </div>
                <div class="mobile-meta-cell">
                  <span class="mobile-meta-cell__label">创建时间</span>
                  <span class="mobile-meta-cell__value">{{ formatDateTime(detail?.created_at) }}</span>
                </div>
                <div class="mobile-meta-cell">
                  <span class="mobile-meta-cell__label">更新时间</span>
                  <span class="mobile-meta-cell__value">{{ formatDateTime(detail?.updated_at) }}</span>
                </div>
              </div>
            </div>
          </div>

          <button
            v-if="detail?.status !== 3"
            class="mobile-close-btn"
            :disabled="closing"
            @click="handleClose"
          >
            关闭工单
          </button>
        </div>
      </template>
    </div>
  </div>

  <!-- 附件预览 -->
  <el-dialog v-model="previewVisible" title="附件预览" width="720px" append-to-body>
    <img v-if="previewUrl" :src="previewUrl" class="preview-image" alt="预览" />
  </el-dialog>
</template>

<script setup>
import { computed, ref, watch, nextTick, onMounted, onUnmounted } from 'vue'
import { CircleCheck, Plus } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import clientApi from '@/api/client'

const MAX_IMAGES = 9
const IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp']
const MAX_SIZE = 5 * 1024 * 1024
const MOBILE_BREAKPOINT = 768

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
const isMobile = ref(false)
const mobileTab = ref('chat')

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

function checkMobile() {
  isMobile.value = window.innerWidth <= MOBILE_BREAKPOINT
}

watch(visible, (val) => {
  if (!val) {
    resetReplyDraft()
  } else {
    mobileTab.value = 'chat'
    nextTick(() => scrollToBottom())
  }
})

watch(() => props.detail?.replies, () => {
  nextTick(() => scrollToBottom())
}, { deep: true })

function scrollToBottom() {
  const el = messageListRef.value
  if (el) {
    el.scrollTop = el.scrollHeight
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

function removeReplyAttachment(id) {
  const targetId = String(id)
  replyAttachments.value = replyAttachments.value.filter(
    (item) => String(item.id || item.uid) !== targetId
  )
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

onMounted(() => {
  checkMobile()
  window.addEventListener('resize', checkMobile)
})

onUnmounted(() => {
  window.removeEventListener('resize', checkMobile)
})
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

  &.is-mobile-dialog {
    :deep(.el-dialog) {
      max-width: 100vw;
      width: 100vw !important;
      height: 100vh;
      max-height: 100vh;
      margin: 0;
      border-radius: 0;
    }

    :deep(.el-dialog__body) {
      height: 100%;
    }
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

  &.is-mobile {
    display: flex;
    flex-direction: column;
    height: 100%;
    overflow: hidden;
    padding: 0;
    border: none;
    border-radius: 0;
  }
}

.ticket-detail-embedded {
  min-height: calc(100vh - 150px);
  display: flex;
  flex-direction: column;

  &.is-mobile-embedded {
    flex: 1;
    min-height: 0;
    overflow: hidden;
  }
}

.ticket-panel--embedded {
  min-height: calc(100vh - 150px);
  padding: 18px 14px 14px;
  border-radius: 12px;
  box-shadow: none;

  &.is-mobile {
    display: flex;
    flex-direction: column;
    height: 100%;
    overflow: hidden;
    padding: 0;
    border-radius: 0;
  }
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

/* ── 移动端 Tab 切换条 ── */
.mobile-tab-bar {
  display: flex;
  gap: 0;
  height: 30px;
  margin: 12px 14px;
  background: #f8fafc;
  border-radius: 999px;
  padding: 2px;
  flex-shrink: 0;

  button {
    flex: 1;
    padding: 0 14px;
    height: 26px;
    border: none;
    border-radius: 999px;
    background: transparent;
    color: #5b6b82;
    font-size: 12px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.18s ease;
    font-family: inherit;

    &.active {
      background: #fff;
      color: #165DFF;
      box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
    }
  }
}

/* ── 移动端：交流 ── */
.mobile-conversation-section {
  flex: 1;
  display: flex;
  flex-direction: column;
  min-height: 0;
  overflow: hidden;
}

.mobile-message-list {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 14px;
  padding: 14px;
  overflow-y: auto;
  -webkit-overflow-scrolling: touch;
}

.mobile-message {
  display: flex;
  max-width: 88%;

  &--customer { align-self: flex-end; flex-direction: row-reverse; }
  &--staff { align-self: flex-start; }
}

.mobile-message__bubble-wrap {
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.mobile-message__meta {
  display: flex;
  gap: 6px;
  font-size: 11px;
  color: #94a0b2;
  padding: 0 4px;

  .mobile-message--customer & { flex-direction: row-reverse; }
}

.mobile-message__bubble {
  padding: 9px 12px;
  font-size: 14px;
  line-height: 1.6;
  color: #1f2937;
  white-space: pre-wrap;
  word-break: break-word;
  border: 1px solid;

  .mobile-message--staff & {
    background: #eaf2ff;
    border-color: #c9dcff;
    border-radius: 14px 4px 14px 14px;
  }

  .mobile-message--customer & {
    background: #fff;
    border-color: #dce5f2;
    border-radius: 4px 14px 14px 14px;
  }
}

.mobile-message__images {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
  margin-top: 8px;

  .mobile-message--staff & { justify-content: flex-end; }
}

.mobile-message__img {
  width: 68px;
  height: 68px;
  padding: 0;
  border-radius: 8px;
  overflow: hidden;
  border: 1px solid #e5eaf3;
  cursor: pointer;
  background: #f8fafc;
  transition: transform 0.18s ease;

  &:active { transform: scale(0.95); }

  :deep(.el-image) {
    width: 100%;
    height: 100%;
  }
}

/* 移动端底部输入 */
.mobile-reply-section {
  padding: 10px 14px 14px;
  background: #fff;
  border-top: 1px solid #eef2f7;
  flex-shrink: 0;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.mobile-draft-images {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}

.mobile-draft-img {
  position: relative;
  width: 44px;
  height: 44px;
  border-radius: 8px;
  overflow: hidden;
  border: 1px solid #e5eaf3;
  padding: 0;
  cursor: pointer;
  background: #fff;

  img {
    width: 100%;
    height: 100%;
    object-fit: cover;
  }
}

.mobile-draft-remove {
  position: absolute;
  top: -1px;
  right: -1px;
  width: 16px;
  height: 16px;
  border: none;
  background: rgba(31, 41, 55, 0.78);
  color: #fff;
  font-size: 10px;
  line-height: 1;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 0 8px 0 8px;
}

.mobile-composer-bar {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 6px 8px;
  border: 1px solid #d4dce8;
  border-radius: 999px;
  background: #fff;
  box-shadow: 0 6px 24px rgba(15, 23, 42, 0.06);
}

.mobile-composer-upload {
  display: flex;

  :deep(.el-upload) {
    display: inline-flex;
  }
}

.mobile-plus-btn {
  width: 38px;
  height: 38px;
  display: flex;
  align-items: center;
  justify-content: center;
  border: 1px solid #ccd6e5;
  border-radius: 50%;
  background: #fff;
  color: #637083;
  cursor: pointer;
  flex-shrink: 0;
  font-size: 22px;
  font-family: inherit;
  transition: all 0.18s ease;

  &:active {
    background: #e8f1ff;
    border-color: #c9dbff;
    color: #165DFF;
  }

  &:disabled {
    color: #b5b5b5;
    cursor: not-allowed;
    border-color: #d0d0d0;
  }
}

.mobile-textarea {
  flex: 1;
  min-width: 0;
  border: none;
  outline: none;
  font-size: 15px;
  line-height: 1.5;
  color: #1f2937;
  font-family: inherit;
  resize: none;
  padding: 4px 0;
  max-height: 100px;
  background: transparent;

  &::placeholder {
    color: #94a0b2;
  }
}

.mobile-send-btn {
  width: 64px;
  height: 38px;
  border: none;
  border-radius: 999px;
  background: #165DFF;
  color: #fff;
  font-size: 15px;
  font-weight: 600;
  cursor: pointer;
  flex-shrink: 0;
  transition: all 0.18s ease;
  font-family: inherit;

  &:active {
    background: #0e4fcc;
    transform: scale(0.96);
  }

  &:disabled {
    background: #c8d6e5;
    color: #fff;
    cursor: not-allowed;
    transform: none;
  }
}

.mobile-closed-notice {
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 10px;
  margin: 0 14px 14px;
  color: #B45309;
  font-size: 13px;
  background: #FEF3C7;
  border: 1px solid #FDE68A;
  border-radius: 10px;
}

/* ── 移动端：详情 ── */
.mobile-detail-panel {
  flex: 1;
  min-height: 0;
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding: 12px;
  overflow-y: auto;
  -webkit-overflow-scrolling: touch;
}

.mobile-detail-card {
  flex-shrink: 0;
  background: #fff;
  border: 1px solid #e5eaf3;
  border-radius: 14px;
  overflow: hidden;
}

.mobile-detail-card__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 11px 14px;
  background: #fbfcff;
  border-bottom: 1px solid #edf0f5;
  font-size: 13px;
  font-weight: 600;
  color: #1f2937;
}

.mobile-detail-card__sub {
  font-size: 11px;
  color: #94a0b2;
  font-weight: 400;
}

.mobile-detail-card__body {
  padding: 0;
}

.mobile-user-summary {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px;
}

.mobile-user-summary__avatar {
  width: 44px;
  height: 44px;
  border-radius: 50%;
  background: linear-gradient(135deg, #165DFF, #3B82F6);
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  font-size: 18px;
  font-weight: 700;
  flex-shrink: 0;
}

.mobile-user-summary__info {
  flex: 1;
  min-width: 0;
}

.mobile-user-summary__name {
  font-size: 15px;
  font-weight: 600;
  color: #1f2937;
  line-height: 1.3;
  display: block;
}

.mobile-user-summary__id {
  font-size: 12px;
  color: #5b6b82;
  display: block;
  margin-top: 2px;
}

.mobile-meta-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  border-top: 1px solid #eef2f7;
}

.mobile-meta-cell {
  display: flex;
  flex-direction: column;
  gap: 4px;
  padding: 11px 14px;
  min-height: 54px;
  border-bottom: 1px solid #eef2f7;
  border-right: 1px solid #eef2f7;

  &:nth-child(2n) { border-right: none; }
}

.mobile-meta-cell__label {
  font-size: 11px;
  color: #94a0b2;
  display: flex;
  align-items: center;
  gap: 4px;
}

.mobile-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  flex-shrink: 0;

  &--danger { background: #F04438; }
  &--purple { background: #8B5CF6; }
  &--blue { background: #165DFF; }
}

.mobile-meta-cell__value {
  font-size: 13px;
  font-weight: 500;
  color: #1f2937;
  word-break: break-all;
}

.mobile-close-btn {
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  width: 100%;
  height: 40px;
  border: 1px solid #fecaca;
  border-radius: 10px;
  background: #fff;
  color: #DC2626;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.18s ease;
  font-family: inherit;

  &:active {
    background: #FEF2F2;
    transform: scale(0.98);
  }

  &:disabled {
    opacity: 0.6;
    cursor: not-allowed;
  }
}

.preview-image {
  width: 100%;
  display: block;
}

/* 滚动条 */
.message-list {
  &::-webkit-scrollbar { width: 6px; }
  &::-webkit-scrollbar-track { background: $bg-color-soft; border-radius: 3px; }
  &::-webkit-scrollbar-thumb { background: $color-primary-border; border-radius: 3px; }
}

.mobile-message-list {
  &::-webkit-scrollbar { width: 0; }
}

/* ============ 桌面端 @media (max-width: 768px) 补丁 ============ */
@media (max-width: 768px) {
  .ticket-panel:not(.is-mobile) {
    grid-template-rows: auto minmax(280px, 1fr) auto;
    min-height: min(82vh, 720px);
    padding: 24px 14px 14px;
  }

  .ticket-panel--embedded:not(.is-mobile) {
    grid-template-rows: auto minmax(0, 1fr) auto;
    min-height: calc(100vh - 150px);
    padding: 18px 12px 12px;
  }

  .ticket-panel:not(.is-mobile) .manual-close-button {
    top: 22px;
    right: 44px;
    height: 28px;
    padding: 0 10px;
  }

  .ticket-panel:not(.is-mobile) .ticket-meta {
    padding-top: 30px;
    grid-template-columns: 1fr;
    row-gap: 6px;
    padding-left: 10px;
    font-size: 13px;
  }

  .ticket-panel:not(.is-mobile) .message-bubble {
    max-width: 86%;
  }

  .ticket-panel:not(.is-mobile) .composer-bar {
    grid-template-columns: 42px minmax(0, 1fr) 76px;
    gap: 6px;
    min-height: 60px;
    padding: 8px;
  }

  .ticket-panel:not(.is-mobile) .composer-plus {
    width: 42px;
    height: 42px;
  }

  .ticket-panel:not(.is-mobile) .send-button {
    width: 72px;
    height: 42px;
    font-size: 18px;
  }
}
</style>
