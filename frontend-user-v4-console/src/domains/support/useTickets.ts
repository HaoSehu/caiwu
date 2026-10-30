import {
  getStatusLabel,
  getStatusTagType,
  resolveTdTagTheme,
  SERVICE_STATUS_MAP,
  TICKET_STATUS_MAP,
  toSelectOptions,
} from '@caiwu/shared/statusConfig';
import { DialogPlugin } from 'tdesign-vue-next/es/dialog/plugin';
import { MessagePlugin } from 'tdesign-vue-next/es/message/plugin';
import { computed, onActivated, onMounted, reactive, ref, shallowRef, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';

import clientApi from '@/api/client';
import type {
  TicketAttachment,
  TicketImageUploadPayload,
  TicketRecord,
  TicketReplyRecord,
  TicketServiceOption,
} from '@/types/client';
import { getErrorMessage } from '@/utils/error';

type ServiceOption = TicketServiceOption;

// 工单状态标签统一取 @caiwu/shared/statusConfig，控件与标签不会各写一份
export const TICKET_STATUS_OPTIONS = toSelectOptions(TICKET_STATUS_MAP, false);

export const TICKET_DEPARTMENT_OPTIONS = [
  { label: '销售', value: 'sales' },
  { label: '技术支持', value: 'support' },
  { label: '财务', value: 'billing' },
  { label: '投诉', value: 'abuse' },
];

export const TICKET_PRIORITY_OPTIONS = [
  { label: '低', value: 1 },
  { label: '中', value: 2 },
  { label: '高', value: 3 },
  { label: '紧急', value: 4 },
];

const MAX_IMAGES = 9;
/** 单张图片上传的结果：失败时带原因，供批量调用方汇总成一句可读提示。 */
export interface UploadResult {
  ok: boolean;
  reason?: string;
}

/**
 * 把一批上传的失败原因汇总成一句可读提示。
 * 同一句话只出现一次（用户一次选 3 张超大图，不该刷三遍同一句），
 * 多种原因用「；」连起来，并带上是第几次以外的信息：成功张数由调用方拼在前面。
 */
export function summarizeUploadFailures(failures: string[]): string {
  if (failures.length === 0) return '';
  const unique = Array.from(new Set(failures));
  if (unique.length === 1) return `${failures.length} 张图片上传失败：${unique[0]}`;
  return `${failures.length} 张图片上传失败：${unique.join('；')}`;
}
const IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
const MAX_SIZE = 5 * 1024 * 1024;

function normalizeList(data: unknown): TicketRecord[] {
  if (data && typeof data === 'object' && Array.isArray((data as { list?: unknown[] }).list)) {
    return (data as { list: TicketRecord[] }).list;
  }
  if (Array.isArray(data)) return data;
  return [];
}

export function formatTicketTime(value: unknown) {
  const raw = String(value || '').trim();
  if (!raw) return '--';
  const date = new Date(raw);
  if (Number.isNaN(date.getTime())) return raw.slice(0, 16).replace('T', ' ');
  const pad = (num: number) => String(num).padStart(2, '0');
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

export function resolveTicketStatusLabel(value: unknown) {
  return getStatusLabel(TICKET_STATUS_MAP, Number(value));
}

export function resolveTicketTagTheme(value: unknown) {
  // 待处理类（开启 / 客户回复）映射 warning、员工回复 success、已关闭 default，统一由 shared 映射表决定
  return resolveTdTagTheme(getStatusTagType(TICKET_STATUS_MAP, Number(value)));
}

export function resolvePriorityLabel(value: unknown) {
  return TICKET_PRIORITY_OPTIONS.find((item) => Number(item.value) === Number(value))?.label || '--';
}

export function resolvePriorityTheme(value: unknown) {
  if (Number(value) >= 4) return 'danger';
  if (Number(value) === 3) return 'warning';
  return 'default';
}

export function resolveDepartmentLabel(value: unknown) {
  return TICKET_DEPARTMENT_OPTIONS.find((item) => String(item.value) === String(value))?.label || '--';
}

export function parseAttachments(item: TicketRecord | TicketReplyRecord | null | undefined) {
  const attachments = item?.attachments || item?.attachment_urls || [];
  if (!Array.isArray(attachments)) return [];
  return attachments
    .map((attachment: TicketAttachment | string, index) => {
      if (typeof attachment === 'string') return { id: index, url: attachment, path: attachment };
      return {
        id: attachment?.id || attachment?.uid || index,
        url: attachment?.url || attachment?.path || '',
        path: attachment?.path || attachment?.url || '',
      };
    })
    .filter((item) => item.url);
}

function resolveServiceName(option?: ServiceOption) {
  const rawName = String(option?.name || option?.display_name || option?.product_name || '').trim();
  if (!rawName) return '';
  const parts = rawName
    .split('/')
    .map((item) => item.trim())
    .filter(Boolean);
  // 用下标取末项而非 Array.prototype.at：后者要 Chrome 92 / Safari 15.4，超出仓库声明的浏览器基线
  return parts.length >= 2 ? parts[parts.length - 1] : rawName;
}

export function formatTicketServiceOptionLabel(option?: ServiceOption, includeStatus = true) {
  const id = Number(option?.id || 0);
  const name = resolveServiceName(option);
  if (id <= 0 || !name) return '--';
  const status = getStatusLabel(SERVICE_STATUS_MAP, Number(option?.status));
  return includeStatus && status ? `#${id}-${name}-${status}` : `#${id}-${name}`;
}

// T-04 补：批量上传时校验失败的原因要交回调用方汇总，不能自己再弹一条。
// 原来 silent 只作用于「上传成功/请求失败」两条提示，校验失败仍各自弹一次，
// 结果是用户选了一张超限图片会先看到「单张图片不能超过 5MB」再看到「1 张图片上传失败」——同一件事说两遍。
function validateImage(file: File, currentCount: number, silent = false): string | null {
  const reason = (() => {
    if (!IMAGE_TYPES.includes(file.type)) return '仅支持 jpg、png、webp 图片';
    if (file.size > MAX_SIZE) return '单张图片不能超过 5MB';
    if (currentCount >= MAX_IMAGES) return `最多上传 ${MAX_IMAGES} 张图片`;
    return null;
  })();
  if (reason && !silent) MessagePlugin.warning(reason);
  return reason;
}

export function useTicketList() {
  const router = useRouter();
  const loading = ref(false);
  const creating = ref(false);
  const serviceLoading = ref(false);
  const createVisible = ref(false);
  const uploading = ref(false);
  // 附件已达上限：模板据此禁用选择入口，避免用户反复选文件后才发现超限（上限只在 validateImage 里判一次）
  const uploadLimitReached = computed(() => uploadFiles.value.length >= MAX_IMAGES);
  const list = ref<TicketRecord[]>([]);
  const total = ref(0);
  const loadError = ref(false);
  const loadErrorText = ref('');
  const serviceOptions = ref<ServiceOption[]>([]);
  const uploadFiles = ref<TicketImageUploadPayload[]>([]);
  const previewVisible = ref(false);
  const previewUrl = ref('');
  const filters = reactive({
    keyword: '',
    status: undefined as number | undefined,
    page: 1,
    page_size: 10,
  });
  const createForm = reactive({
    department: 'support',
    subject: '',
    service_id: undefined as number | undefined,
    priority: 2,
    content: '',
  });

  const serviceSelectOptions = computed(() =>
    serviceOptions.value.map((item) => ({
      label: formatTicketServiceOptionLabel(item),
      value: item.id,
    })),
  );

  function resetCreateForm() {
    createForm.department = 'support';
    createForm.subject = '';
    createForm.service_id = undefined;
    createForm.priority = 2;
    createForm.content = '';
    uploadFiles.value = [];
  }

  // C-10 / T-19：请求序号防竞态。快速切换筛选、翻页或改关键字时，晚返回的旧响应不能覆盖新结果
  // （列表是普通 ref 而非 shallowRef：这里只做值替换，没有就地改对象的需求，序号守卫已足够）。
  let ticketsRequestSeq = 0;

  async function loadTickets() {
    const requestSeq = ++ticketsRequestSeq;
    loading.value = true;
    loadError.value = false;
    try {
      const res = await clientApi.tickets({
        page: filters.page,
        page_size: filters.page_size,
        keyword: filters.keyword || undefined,
        status: filters.status === undefined ? undefined : filters.status,
      });
      if (requestSeq !== ticketsRequestSeq) return;
      const payload = res.data;
      list.value = normalizeList(payload);
      total.value = Number(payload?.total || list.value.length || 0);
    } catch (error: unknown) {
      if (requestSeq !== ticketsRequestSeq) return;
      loadError.value = true;
      loadErrorText.value = getErrorMessage(error, '工单列表加载失败');
      list.value = [];
      total.value = 0;
      MessagePlugin.error(loadErrorText.value);
    } finally {
      if (requestSeq === ticketsRequestSeq) loading.value = false;
    }
  }

  async function loadServiceOptions() {
    serviceLoading.value = true;
    try {
      const res = await clientApi.ticketServiceOptions({ limit: 50 });
      serviceOptions.value = Array.isArray(res.data) ? res.data : [];
    } catch (error: unknown) {
      MessagePlugin.error(getErrorMessage(error, '服务选项加载失败'));
    } finally {
      serviceLoading.value = false;
    }
  }

  function handleSearch() {
    filters.page = 1;
    void loadTickets();
  }

  // W-17：命中筛选但一条都没有时，空态必须给出「清除筛选」的出口。
  // 原先空态槽用 v-if="!hasActiveFilters" 兜住「暂无工单 + 提交工单」，
  // 命中筛选时整块槽都不渲染，用户只能自己去猜是哪几个条件叠在一起。
  function resetFilters() {
    filters.keyword = '';
    filters.status = undefined;
    filters.page = 1;
    void loadTickets();
  }

  // C-26：t-pagination 变更每页条数时同时 emit page-size-change 与 change，只重置页码避免双请求
  function handlePageSizeChange(value: number) {
    filters.page_size = Number(value || 10);
    filters.page = 1;
  }

  async function openCreateDialog() {
    resetCreateForm();
    createVisible.value = true;
    if (!serviceOptions.value.length) await loadServiceOptions();
  }

  function closeCreateDialog() {
    createVisible.value = false;
    resetCreateForm();
  }

  // T-04：silent=true 时不弹单张提示，由调用方按整批结果汇总一次。
  // 多选上传是「一次操作」，用户要的是「3 张成功 1 张失败」，不是四条独立 toast。
  // 返回值带上「没上传的原因」：校验不通过时调用方要能区分「这张不合法」和「这张请求失败」，
  // 否则只能笼统报「N 张失败」，用户不知道是自己选了超大图还是网络问题。
  async function uploadTicketImage(file: File, silent = false): Promise<UploadResult> {
    const invalidReason = validateImage(file, uploadFiles.value.length, silent);
    if (invalidReason) return { ok: false, reason: invalidReason };
    uploading.value = true;
    try {
      const formData = new FormData();
      formData.append('file', file);
      const res = await clientApi.uploadTicketImage(formData);
      const payload = res.data;
      uploadFiles.value = [...uploadFiles.value, payload].slice(0, MAX_IMAGES);
      if (!silent) MessagePlugin.success('图片已上传');
      return { ok: true };
    } catch (error: unknown) {
      const reason = getErrorMessage(error, '图片上传失败');
      if (!silent) MessagePlugin.error(reason);
      return { ok: false, reason };
    } finally {
      uploading.value = false;
    }
  }

  function removeUploadFile(index: number) {
    uploadFiles.value.splice(index, 1);
  }

  function previewUploadFile(file: TicketImageUploadPayload) {
    previewUrl.value = String(file.url || file.path || '');
    previewVisible.value = Boolean(previewUrl.value);
  }

  async function submitTicket() {
    if (!createForm.subject.trim()) {
      MessagePlugin.warning('请填写工单标题');
      return false;
    }
    if (!createForm.content.trim()) {
      MessagePlugin.warning('请填写问题描述');
      return false;
    }

    creating.value = true;
    try {
      await clientApi.createTicket({
        department: createForm.department,
        subject: createForm.subject,
        content: createForm.content,
        priority: createForm.priority,
        service_id: createForm.service_id,
        attachments: uploadFiles.value.map((item) => item.path).filter(Boolean),
      });
      MessagePlugin.success('工单提交成功');
      createVisible.value = false;
      resetCreateForm();
      await loadTickets();
      return true;
    } catch (error: unknown) {
      MessagePlugin.error(getErrorMessage(error, '工单提交失败'));
      return false;
    } finally {
      creating.value = false;
    }
  }

  function openDetail(row: TicketRecord) {
    router.push(`/client/tickets/${row.id}`);
  }

  return {
    router,
    loading,
    loadError,
    loadErrorText,
    creating,
    serviceLoading,
    createVisible,
    uploading,
    uploadLimitReached,
    list,
    total,
    filters,
    createForm,
    serviceOptions,
    serviceSelectOptions,
    uploadFiles,
    previewVisible,
    previewUrl,
    loadTickets,
    loadServiceOptions,
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
  };
}

export function useTicketDetail() {
  const route = useRoute();
  const router = useRouter();
  const loading = ref(false);
  const replying = ref(false);
  const closing = ref(false);
  // T-18：撤回按钮在会话列表里每条消息各有一个，原来共用一个布尔标志，
  // 点任意一条的「撤回」会让同一屏内所有可撤回消息一起转圈，也让重复点击判断失效。
  // 改为按回复 id 记录，按钮只跟随自己那条请求。
  const recallingIds = ref<number[]>([]);
  const isRecalling = (replyId: unknown) => recallingIds.value.includes(Number(replyId || 0));
  const replyUploading = ref(false);
  const detail = shallowRef<TicketRecord | null>(null);
  const detailLoadError = ref('');
  const replyContent = ref('');
  const replyAttachments = ref<TicketImageUploadPayload[]>([]);
  const previewVisible = ref(false);
  const previewUrl = ref('');
  const activeMobileTab = ref<'chat' | 'detail'>('chat');

  const ticketId = computed(() => Number(route.params.id || 0));
  const userName = computed(() =>
    String(detail.value?.user?.display_name || detail.value?.user?.nickname || detail.value?.user?.email || '客户'),
  );
  const assigneeName = computed(() =>
    String(detail.value?.assignee?.nickname || detail.value?.assignee?.username || '未分配'),
  );
  const isClosed = computed(() => Number(detail.value?.status) === 3);
  const canSubmitReply = computed(() => replyContent.value.trim().length > 0 || replyAttachments.value.length > 0);
  const titleText = computed(() => String(detail.value?.subject || '工单详情'));

  function currentUserId() {
    return Number(detail.value?.user?.id || detail.value?.user_id || 0);
  }

  function canRecall(reply: TicketReplyRecord) {
    if (!reply || reply.recalled || reply.is_staff) return false;
    const ownerId = currentUserId();
    if (ownerId && Number(reply.user_id) !== ownerId) return false;
    if (!reply.created_at) return false;
    return Date.now() - new Date(reply.created_at).getTime() <= 120000;
  }

  // 请求序号：watch(ticketId) 与 onMounted 会对同一 id 各发一次详情请求，
  // 加上路由快速切换，只让最后一次响应落地，避免旧响应覆盖新工单。
  let detailRequestSeq = 0;

  async function loadDetail() {
    if (!ticketId.value) {
      MessagePlugin.error('工单不存在');
      await router.replace('/client/tickets');
      return;
    }

    const seq = ++detailRequestSeq;

    loading.value = true;
    detailLoadError.value = '';
    try {
      const res = await clientApi.ticketDetail(ticketId.value);
      if (seq !== detailRequestSeq) return;
      detail.value = res.data || null;
      if (!detail.value) {
        detailLoadError.value = '工单不存在或已被删除';
      }
    } catch (error: unknown) {
      if (seq !== detailRequestSeq) return;
      detail.value = null;
      detailLoadError.value = getErrorMessage(error, '工单详情加载失败');
      MessagePlugin.error(detailLoadError.value);
    } finally {
      if (seq === detailRequestSeq) loading.value = false;
    }
  }

  function resetReplyDraft() {
    replyContent.value = '';
    replyAttachments.value = [];
  }

  // T-04：同 uploadTicketImage，silent 交给批量调用方汇总提示。
  async function uploadReplyImage(file: File, silent = false): Promise<UploadResult> {
    const invalidReason = validateImage(file, replyAttachments.value.length, silent);
    if (invalidReason) return { ok: false, reason: invalidReason };
    replyUploading.value = true;
    try {
      const formData = new FormData();
      formData.append('file', file);
      const res = await clientApi.uploadTicketImage(formData);
      replyAttachments.value = [...replyAttachments.value, res.data || {}].slice(0, MAX_IMAGES);
      if (!silent) MessagePlugin.success('图片已上传');
      return { ok: true };
    } catch (error: unknown) {
      const reason = getErrorMessage(error, '图片上传失败');
      if (!silent) MessagePlugin.error(reason);
      return { ok: false, reason };
    } finally {
      replyUploading.value = false;
    }
  }

  function removeReplyAttachment(index: number) {
    replyAttachments.value.splice(index, 1);
  }

  function previewAttachment(file: TicketImageUploadPayload | { url?: string; path?: string }) {
    previewUrl.value = String(file.url || file.path || '');
    previewVisible.value = Boolean(previewUrl.value);
  }

  async function submitReply() {
    if (replyUploading.value) {
      MessagePlugin.warning('图片上传中，请稍后发送');
      return false;
    }
    if (!canSubmitReply.value) {
      MessagePlugin.warning('请输入回复内容或上传图片');
      return false;
    }
    if (!ticketId.value) return false;

    replying.value = true;
    try {
      await clientApi.replyTicket(ticketId.value, {
        content: replyContent.value,
        attachments: replyAttachments.value.map((item) => item.path).filter(Boolean),
      });
      MessagePlugin.success('回复已发送');
      resetReplyDraft();
      await loadDetail();
      return true;
    } catch (error: unknown) {
      MessagePlugin.error(getErrorMessage(error, '发送回复失败'));
      return false;
    } finally {
      replying.value = false;
    }
  }

  async function recallReply(reply: TicketReplyRecord) {
    if (!ticketId.value || !reply?.id) return false;
    const replyId = Number(reply.id);
    if (recallingIds.value.includes(replyId)) return false;
    recallingIds.value = [...recallingIds.value, replyId];
    try {
      await clientApi.recallTicketReply(ticketId.value, reply.id);
      MessagePlugin.success('消息已撤回');
      await loadDetail();
      return true;
    } catch (error: unknown) {
      MessagePlugin.error(getErrorMessage(error, '撤回失败'));
      return false;
    } finally {
      recallingIds.value = recallingIds.value.filter((id) => id !== replyId);
    }
  }

  function closeTicket() {
    if (!ticketId.value) return;
    const dialog = DialogPlugin.confirm({
      header: '关闭工单',
      body: '关闭后将无法继续回复此工单，确认关闭吗？',
      confirmBtn: '确定',
      cancelBtn: '取消',
      theme: 'warning',
      async onConfirm() {
        dialog.setConfirmLoading(true);
        closing.value = true;
        try {
          await clientApi.closeTicket(ticketId.value);
          MessagePlugin.success('工单已关闭');
          await loadDetail();
          dialog.hide();
        } catch (error: unknown) {
          MessagePlugin.error(getErrorMessage(error, '关闭工单失败'));
        } finally {
          closing.value = false;
          dialog.setConfirmLoading(false);
        }
      },
    });
  }

  // T-11：原来无条件 push('/client/tickets')，从列表点进来再返回时，列表页会被重建成
  // 默认筛选与第 1 页，用户刚设好的筛选条件和翻到的页码全丢。
  // history.state.back 是 vue-router 记录的上一页地址，为空说明是直接打开/刷新进来的，那时才落到列表。
  function goBack() {
    const backTarget = (window.history.state as { back?: string | null } | null)?.back;
    if (backTarget) {
      router.back();
      return;
    }
    router.push('/client/tickets');
  }

  // 详情页只在 /client/tickets/:id 渲染；同组件内切换 id 仍要重新拉取详情
  watch(ticketId, (nextId, prevId) => {
    if (nextId && nextId !== prevId) {
      void loadDetail();
    }
  });

  onMounted(() => {
    void loadDetail();
  });

  // keep-alive 现在真的会缓存页面（Content.vue 按 route.name 做 key），onMounted 一生只跑一次。
  // 工单详情是唯一需要「回来就看到最新」的页面：客服可能刚好在你切走时回复或关单。
  let activatedOnce = false;
  onActivated(() => {
    if (!activatedOnce) {
      activatedOnce = true;
      return;
    }
    void loadDetail();
  });

  return {
    router,
    loading,
    replying,
    closing,
    recallingIds,
    isRecalling,
    replyUploading,
    detail,
    detailLoadError,
    replyContent,
    replyAttachments,
    previewVisible,
    previewUrl,
    activeMobileTab,
    ticketId,
    userName,
    assigneeName,
    isClosed,
    canSubmitReply,
    titleText,
    currentUserId,
    canRecall,
    loadDetail,
    resetReplyDraft,
    uploadReplyImage,
    removeReplyAttachment,
    previewAttachment,
    submitReply,
    recallReply,
    closeTicket,
    goBack,
  };
}
