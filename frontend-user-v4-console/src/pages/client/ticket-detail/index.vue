<template>
  <section class="ticket-detail-page">
    <!--
      T-10：工具栏原先用 grid 三列（auto 1fr auto）把标题放在中间的弹性列里，
      左边「返回工单列表」文字长短一变，标题就跟着左右漂，和全站详情页
      （order-detail / payment-detail / invoice-detail-view 都是 flex 两栏）不一致。
      改成「左侧返回 + 标题成组，右侧动作」的 flex。
    -->
    <div class="ticket-detail-toolbar">
      <div class="ticket-detail-toolbar__main">
        <t-button variant="text" @click="goBack">返回工单列表</t-button>
        <h1>工单详情</h1>
      </div>
      <t-button v-if="detail && !isClosed" theme="danger" variant="outline" :loading="closing" @click="closeTicket">
        关闭工单
      </t-button>
    </div>

    <data-state
      :loading="loading"
      :error="!loading && Boolean(detailLoadError)"
      :error-text="detailLoadError"
      :empty="!loading && !detailLoadError && !detail"
      description="工单不存在或已被删除"
      @retry="loadDetail"
    >
      <template #empty-action>
        <t-button theme="primary" @click="goBack">返回工单列表</t-button>
      </template>
      <template v-if="detail">
        <div class="ticket-detail-shell">
          <aside class="ticket-meta-card">
            <div class="ticket-meta-card__head">
              <strong>{{ titleText }}</strong>
              <t-tag :theme="resolveTicketTagTheme(detail.status)" variant="light">
                {{ resolveTicketStatusLabel(detail.status) }}
              </t-tag>
            </div>
            <t-descriptions :column="1" bordered>
              <t-descriptions-item label="用户">{{ userName }}</t-descriptions-item>
              <t-descriptions-item label="工单分类">{{
                resolveDepartmentLabel(detail.department)
              }}</t-descriptions-item>
              <t-descriptions-item label="优先级">
                <t-tag :theme="resolvePriorityTheme(detail.priority)" variant="light">
                  {{ resolvePriorityLabel(detail.priority) }}
                </t-tag>
              </t-descriptions-item>
              <t-descriptions-item label="处理人">{{ assigneeName }}</t-descriptions-item>
              <t-descriptions-item label="关联服务">
                {{ detail.service?.id || detail.service_id || '--' }}
                <template v-if="detail.service?.display_name || detail.service?.product_name">
                  （{{ detail.service?.display_name || detail.service?.product_name }}）
                </template>
              </t-descriptions-item>
              <t-descriptions-item label="创建时间">{{ formatTicketTime(detail.created_at) }}</t-descriptions-item>
              <t-descriptions-item label="更新时间">{{ formatTicketTime(detail.updated_at) }}</t-descriptions-item>
              <t-descriptions-item v-if="detail.close_reason_label" label="关闭原因">
                {{ detail.close_reason_label }}
              </t-descriptions-item>
            </t-descriptions>
          </aside>

          <section class="ticket-conversation-card">
            <t-tabs v-model="activeMobileTab" class="mobile-ticket-tabs">
              <t-tab-panel value="chat" label="交流" />
              <t-tab-panel value="detail" label="详情" />
            </t-tabs>

            <div class="mobile-detail-card">
              <t-descriptions :column="1" bordered>
                <t-descriptions-item label="工单分类">{{
                  resolveDepartmentLabel(detail.department)
                }}</t-descriptions-item>
                <t-descriptions-item label="优先级">{{ resolvePriorityLabel(detail.priority) }}</t-descriptions-item>
                <t-descriptions-item label="状态">{{ resolveTicketStatusLabel(detail.status) }}</t-descriptions-item>
                <t-descriptions-item label="处理人">{{ assigneeName }}</t-descriptions-item>
                <t-descriptions-item label="关联服务">
                  <template v-if="detail.service?.id || detail.service_id">
                    {{ detail.service?.id || detail.service_id }}
                    <template v-if="detail.service?.display_name || detail.service?.name">
                      （{{ detail.service?.display_name || detail.service?.name }}）
                    </template>
                  </template>
                  <template v-else>--</template>
                </t-descriptions-item>
                <t-descriptions-item v-if="detail.close_reason_label" label="关闭原因">
                  {{ detail.close_reason_label }}
                </t-descriptions-item>
              </t-descriptions>
              <t-button v-if="!isClosed" theme="danger" variant="outline" block :loading="closing" @click="closeTicket">
                关闭工单
              </t-button>
            </div>

            <div class="ticket-chat-panel">
              <div class="message-list">
                <article v-if="detail.content" class="message-item message-item--user">
                  <div class="message-meta">
                    <span>{{ detail.user?.display_name || '我' }}</span>
                    <span>{{ formatTicketTime(detail.created_at) }}</span>
                  </div>
                  <div class="message-bubble">
                    <p>{{ detail.content }}</p>
                    <div v-if="detailAttachments.length" class="message-attachments">
                      <!--
                        key 优先用附件地址：parseAttachments 对纯字符串附件是把数组下标当 id 用的，
                        与对象附件的真实 id 混在同一列表里会撞键，删中间一张就会复用错位的 img。
                      -->
                      <button
                        v-for="(attachment, index) in detailAttachments"
                        :key="attachment.path || attachment.url || String(index)"
                        type="button"
                        class="message-attachment"
                        @click="previewAttachment(attachment)"
                      >
                        <img :src="attachment.url" alt="附件" loading="lazy" decoding="async" />
                      </button>
                    </div>
                  </div>
                </article>

                <article
                  v-for="reply in detail.replies || []"
                  :key="reply.id"
                  class="message-item"
                  :class="reply.is_staff ? 'message-item--staff' : 'message-item--user'"
                >
                  <div class="message-meta">
                    <span>{{ reply.sender_name || (reply.is_staff ? '客服' : '我') }}</span>
                    <t-tag size="small" :theme="reply.is_staff ? 'primary' : 'success'" variant="light">
                      {{ reply.is_staff ? '管理员' : '用户' }}
                    </t-tag>
                    <span>{{ formatTicketTime(reply.created_at) }}</span>
                  </div>
                  <div class="message-bubble">
                    <p v-if="reply.recalled" class="message-recalled">消息已撤回</p>
                    <template v-else>
                      <blockquote v-if="reply.quote">
                        {{ reply.quote.sender_name }}：{{ reply.quote.recalled ? '消息已撤回' : reply.quote.content }}
                      </blockquote>
                      <p>{{ reply.content || '无文字内容' }}</p>
                      <div v-if="replyAttachmentsMap.get(reply.id)?.length" class="message-attachments">
                        <!-- key 取附件地址，理由同上：字符串附件用的是数组下标，不是稳定 id。 -->
                        <button
                          v-for="(attachment, index) in replyAttachmentsMap.get(reply.id) || []"
                          :key="attachment.path || attachment.url || String(index)"
                          type="button"
                          class="message-attachment"
                          @click="previewAttachment(attachment)"
                        >
                          <img :src="attachment.url" alt="附件" loading="lazy" decoding="async" />
                        </button>
                      </div>
                    </template>
                  </div>
                  <div class="message-actions">
                    <t-button
                      v-if="canRecall(reply)"
                      size="small"
                      theme="danger"
                      variant="text"
                      :loading="isRecalling(reply.id)"
                      @click="recallReply(reply)"
                    >
                      撤回
                    </t-button>
                  </div>
                </article>
              </div>

              <section v-if="!isClosed" class="reply-composer">
                <!--
                  PR-38：key 用内容地址而不是 id。上传接口返回的 payload 里 id 可能缺省，原来回落到
                  数组下标，删掉中间一张后剩余项的 key 全部前移，Vue 会复用错位的 img 节点。
                -->
                <!-- T-02：同工单列表，预览与删除拆成并列的真按钮，不再 button 套 span[role="button"]。 -->
                <div v-if="replyAttachments.length" class="reply-attachments">
                  <div
                    v-for="(file, index) in replyAttachments"
                    :key="file.path || file.url || String(index)"
                    class="reply-attachment"
                  >
                    <button
                      type="button"
                      class="reply-attachment__preview"
                      aria-label="预览该附件"
                      @click="previewAttachment(file)"
                    >
                      <img :src="file.url || file.path" alt="附件" loading="lazy" decoding="async" />
                    </button>
                    <button
                      type="button"
                      class="reply-attachment__remove"
                      aria-label="删除该附件"
                      @click="removeReplyAttachment(index)"
                    >
                      删除
                    </button>
                  </div>
                </div>
                <div class="reply-composer__bar">
                  <label
                    class="composer-upload-trigger"
                    :aria-disabled="replyUploading"
                    :title="replyUploading ? '附件上传中' : '添加附件'"
                  >
                    <input
                      type="file"
                      accept=".jpg,.jpeg,.png,.webp"
                      multiple
                      aria-label="添加附件"
                      :disabled="replyUploading"
                      @change="handleReplyUpload"
                    />
                    <span aria-hidden="true"><add-icon /></span>
                  </label>
                  <!--
                    T-12：t-textarea 没有 onEnter 这个 prop（node_modules/tdesign-vue-next/es/textarea/props.mjs
                    只声明 onKeydown/onKeypress/onKeyup，onEnter 只在 input/props.mjs 里），原先的
                    @enter="submitReply" 挂上去是永远不触发的空绑定，回车发送从来没生效过。
                    改用原生 keydown，由 handleReplyEnter 自己判断要不要拦。
                    不能用 @keydown.enter.exact.prevent：Vue 的 withModifiers 会先跑 prevent 守卫
                    再调处理函数，而 withKeys 不检查 isComposing —— 中文输入法按回车选词时
                    keydown 已经被 preventDefault，函数里再 return 就等于把回车吞掉了。
                  -->
                  <t-textarea
                    v-model="replyContent"
                    class="reply-textarea"
                    :autosize="{ minRows: 1, maxRows: 4 }"
                    maxlength="5000"
                    placeholder="输入回复内容..."
                    @keydown="handleReplyEnter"
                  />
                  <t-button
                    theme="primary"
                    :loading="replying"
                    :disabled="!canSubmitReply || replyUploading"
                    @click="submitReply"
                  >
                    发送
                  </t-button>
                </div>
              </section>

              <div v-else class="ticket-closed-notice">此工单已关闭</div>
            </div>
          </section>
        </div>
      </template>
    </data-state>

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
import { MessagePlugin } from 'tdesign-vue-next/es/message/plugin';
import { computed } from 'vue';

import {
  formatTicketTime,
  parseAttachments,
  resolveDepartmentLabel,
  resolvePriorityLabel,
  resolvePriorityTheme,
  resolveTicketStatusLabel,
  resolveTicketTagTheme,
  summarizeUploadFailures,
  useTicketDetail,
} from '@/domains/support/useTickets';

const {
  loading,
  replying,
  closing,
  isRecalling,
  replyUploading,
  detail,
  detailLoadError,
  replyContent,
  replyAttachments,
  previewVisible,
  previewUrl,
  activeMobileTab,
  userName,
  assigneeName,
  isClosed,
  canSubmitReply,
  titleText,
  canRecall,
  loadDetail,
  uploadReplyImage,
  removeReplyAttachment,
  previewAttachment,
  submitReply,
  recallReply,
  closeTicket,
  goBack,
} = useTicketDetail();

// 附件解析原本在模板里被调用 4 次（v-if 与 v-for 各两处），每次渲染都要重建整个数组。
// 提到 computed 后只在 detail 变化时解析一次。
const detailAttachments = computed(() => parseAttachments(detail.value));
const replyAttachmentsMap = computed(() => {
  const map = new Map<unknown, ReturnType<typeof parseAttachments>>();
  for (const reply of detail.value?.replies || []) {
    map.set(reply.id, parseAttachments(reply));
  }
  return map;
});

// T-12：t-textarea 没有 onEnter（见模板注释），回车发送改由这里接管。
// 判断顺序必须是「先看要不要处理，再 preventDefault」：
// 输入法组合中的回车是选词，Shift+Enter 是换行，两者都要原样交还给 textarea，
// 只有在确定要发送时才 preventDefault，否则会把中文用户的选词回车吃掉。
function handleReplyEnter(event: KeyboardEvent) {
  if (event.key !== 'Enter' || event.shiftKey || event.isComposing) return;
  event.preventDefault();
  void submitReply();
}

// T-04：一次选多张时逐张串行上传，但提示只汇总一次——原来每张都弹一个「图片已上传」，
// 选 9 张就刷 9 条 toast，还会把真正的失败提示挤掉。
async function handleReplyUpload(event: Event) {
  const input = event.target as HTMLInputElement;
  const files = Array.from(input.files || []);
  let succeeded = 0;
  const failures: string[] = [];
  for (const file of files) {
    const result = await uploadReplyImage(file, true);
    if (result.ok) succeeded += 1;
    else failures.push(result.reason || '图片上传失败');
  }
  input.value = '';
  // 全成功 → 只报成功数；有失败 → 报失败数 + 去重后的原因（成功数在提示里一并交代）
  if (failures.length === 0) {
    if (succeeded > 0) MessagePlugin.success(`已上传 ${succeeded} 张图片`);
    return;
  }
  MessagePlugin.warning(
    succeeded > 0 ? `已上传 ${succeeded} 张，${summarizeUploadFailures(failures)}` : summarizeUploadFailures(failures),
  );
}
</script>
<style scoped lang="less">
.ticket-detail-page {
  display: flex;
  flex-direction: column;
  gap: var(--td-comp-margin-m);
  // O-19：顶栏实际高度是 comp-size-xxxl（56，见 style/layout.less:131-132/195/217 与 FrameContent.vue:64），
  // 这里原先多用了两个 x 的更大档令牌（72）会多减 16，页面底部凭空少一截。
  min-height: calc(100vh - var(--td-comp-size-xxxl));
  // padding 由 Starter 布局层统一提供
  // T-15：对话区 max-height 原本写死 calc(100vh - 20rem)，顶栏或工具栏一改高度就得重新猜。
  // 抽成 --ticket-detail-chrome，逐段列出对话区之外占掉的量：
  // 顶栏 + 工具栏 + 内容区上下留白 + 页面两处 gap + 会话卡内边距 + 回复区高度。
  --ticket-detail-chrome: calc(
    var(--td-comp-size-xxxl) + var(--td-comp-size-xxl) + var(--td-comp-margin-xxxl) + var(--td-comp-margin-xxl) +
      var(--td-comp-size-xl) + var(--td-comp-size-xxxxl) + var(--td-comp-margin-xxxl)
  );
}

.ticket-detail-toolbar {
  display: flex;
  gap: var(--td-comp-margin-s);
  align-items: center;
  justify-content: space-between;

  &__main {
    display: flex;
    gap: var(--td-comp-margin-s);
    align-items: center;
    min-width: 0;
  }

  h1 {
    margin: 0;
    color: var(--td-text-color-primary);
    font: var(--td-font-title-large);
  }
}

.ticket-detail-shell {
  display: grid;
  grid-template-columns: minmax(17rem, 0.38fr) minmax(0, 1fr);
  gap: var(--td-comp-margin-m);
  min-height: 35rem;
}

.ticket-meta-card,
.ticket-conversation-card {
  background: var(--td-bg-color-container);
  border: thin solid var(--td-component-stroke);
  border-radius: var(--td-radius-medium);
  box-shadow: var(--td-shadow-1);
}

.ticket-meta-card {
  display: flex;
  flex-direction: column;
  gap: var(--td-comp-margin-m);
  padding: var(--td-comp-paddingTB-l) var(--td-comp-paddingLR-l);
}

.ticket-meta-card__head {
  display: flex;
  gap: var(--td-comp-margin-s);
  align-items: flex-start;
  justify-content: space-between;

  strong {
    color: var(--td-text-color-primary);
    font: var(--td-font-title-medium);
  }
}

.ticket-conversation-card {
  display: flex;
  flex-direction: column;
  min-width: 0;
  overflow: hidden;
}

.mobile-ticket-tabs,
.mobile-detail-card {
  display: none;
}

.ticket-chat-panel {
  display: flex;
  flex: 1;
  flex-direction: column;
  min-height: 0;
}

.message-list {
  display: flex;
  flex: 1;
  flex-direction: column;
  gap: var(--td-comp-margin-m);
  // 26rem 同样按令牌折算：六张缩略图高 + 一段内边距，保证空会话时也不塌成一条缝
  min-height: calc(var(--td-comp-size-xxxxl) * 6 + var(--td-comp-margin-xxxl));
  max-height: calc(100vh - var(--ticket-detail-chrome));
  padding: var(--td-comp-paddingTB-l) var(--td-comp-paddingLR-l);
  overflow-y: auto;
}

.message-item {
  display: flex;
  flex-direction: column;
  gap: var(--td-comp-margin-xs);
  max-width: min(72%, 40rem);
}

.message-item--user {
  align-self: flex-end;
  align-items: flex-end;

  .message-bubble {
    background: var(--td-brand-color-light);
    border-color: var(--td-brand-color-light);
  }
}

.message-item--staff {
  align-self: flex-start;
}

.message-meta,
.message-actions {
  display: flex;
  flex-wrap: wrap;
  gap: var(--td-comp-margin-xs);
  align-items: center;
  color: var(--td-text-color-secondary);
  font: var(--td-font-body-small);
}

.message-bubble {
  display: grid;
  gap: var(--td-comp-margin-s);
  padding: var(--td-comp-paddingTB-m) var(--td-comp-paddingLR-m);
  color: var(--td-text-color-primary);
  background: var(--td-bg-color-container);
  border: thin solid var(--td-component-stroke);
  border-radius: var(--td-radius-medium);

  p {
    margin: 0;
    white-space: pre-wrap;
    overflow-wrap: anywhere;
  }

  blockquote {
    margin: 0;
    padding-left: var(--td-comp-paddingLR-s);
    color: var(--td-text-color-secondary);
    border-left: var(--td-comp-margin-xxs) solid var(--td-component-stroke);
  }
}

.message-recalled {
  color: var(--td-text-color-placeholder);
  font-style: italic;
}

.message-attachments,
.reply-attachments {
  display: flex;
  flex-wrap: wrap;
  gap: var(--td-comp-margin-s);
}

.message-attachment {
  position: relative;
  width: var(--td-comp-size-xxxxl);
  height: var(--td-comp-size-xxxxl);
  padding: 0;
  overflow: hidden;
  cursor: pointer;
  background: var(--td-bg-color-container);
  border: thin solid var(--td-component-stroke);
  border-radius: var(--td-radius-medium);

  img {
    width: 100%;
    height: 100%;
    object-fit: cover;
  }
}

.reply-composer {
  display: grid;
  gap: var(--td-comp-margin-s);
  padding: var(--td-comp-paddingTB-m) var(--td-comp-paddingLR-l);
  border-top: thin solid var(--td-component-stroke);
}

// T-02：与 .attachment-thumb 同构——外层定位容器，预览/删除是两个并列按钮
.reply-attachment {
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

.reply-composer__bar {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr) auto;
  gap: var(--td-comp-margin-s);
  align-items: center;
}

.composer-upload-trigger {
  display: inline-flex;
  cursor: pointer;

  // T-02：同工单列表——display:none 的 input 不可聚焦，键盘用户无法添加附件。
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

  span {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: var(--td-comp-size-xl);
    height: var(--td-comp-size-xl);
    color: var(--td-brand-color);
    background: var(--td-brand-color-light);
    border-radius: var(--td-radius-round);
  }
}

.reply-textarea :deep(.t-textarea__inner) {
  resize: none;
  min-height: 2rem;
  max-height: 7.5rem;
}

.reply-textarea {
  align-self: center;
}

.reply-textarea :deep(.t-textarea__info_wrapper) {
  display: none;
}

.preview-image {
  display: block;
  width: 100%;
}

@media (max-width: @screen-mobile-max) {
  .ticket-detail-page {
    min-height: calc(100vh - var(--td-comp-size-xxxl));
  }

  // T-10：窄屏把主区折成两行——第一行返回与关闭并排，第二行标题独占（T-09：不隐藏标题）。
  .ticket-detail-toolbar {
    flex-wrap: wrap;

    &__main {
      flex-wrap: wrap;
      flex: 1 1 100%;
      order: 2;
    }

    h1 {
      min-width: 0;
      overflow: hidden;
      font: var(--td-font-title-medium);
      text-overflow: ellipsis;
      white-space: nowrap;
    }

    > .t-button:last-child {
      order: 1;
      margin-left: auto;
    }
  }

  .ticket-detail-shell {
    grid-template-columns: 1fr;
    min-height: 0;
  }

  .ticket-meta-card {
    display: none;
  }

  .mobile-ticket-tabs {
    display: block;
  }

  .mobile-detail-card {
    display: none;
    gap: var(--td-comp-margin-m);
    padding: var(--td-comp-paddingTB-m) var(--td-comp-paddingLR-m);
  }

  .ticket-chat-panel {
    display: flex;
  }

  // 本页只有「交流 / 详情」两个 tab，历史上按 nth-child(3) 判断导致「详情」永不命中，
  // 移动端切不到详情视图。这里改为第 2 个 tab 激活即展示详情卡。
  .ticket-conversation-card:has(.t-tabs__nav-item:nth-child(2).t-is-active) {
    .mobile-detail-card {
      display: grid;
    }

    .ticket-chat-panel {
      display: none;
    }
  }

  .message-list {
    min-height: 24rem;
    max-height: none;
    padding: var(--td-comp-paddingTB-m) var(--td-comp-paddingLR-m);
  }

  .message-item {
    max-width: 88%;
  }

  .reply-composer {
    padding: var(--td-comp-paddingTB-s) var(--td-comp-paddingLR-m);
  }

  .reply-composer__bar {
    grid-template-columns: auto minmax(0, 1fr) auto;
  }
}
</style>
