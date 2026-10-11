<template>
  <div class="mass-sends-page">
    <t-card :bordered="false">
      <div class="mass-send-filter">
        <t-input
          v-model="filters.keyword"
          class="mass-send-filter__keyword"
          clearable
          placeholder="搜索任务名称"
          @enter="handleSearch"
          @clear="handleSearch"
        >
          <template #suffix-icon><search-icon /></template>
        </t-input>
        <t-select
          v-model="filters.status"
          class="mass-send-filter__status"
          clearable
          placeholder="任务状态"
          @change="handleSearch"
        >
          <t-option v-for="option in statusOptions" :key="option.value" :label="option.label" :value="option.value" />
        </t-select>
        <div class="mass-send-filter__actions">
          <t-button theme="primary" @click="handleSearch">查询</t-button>
          <t-button theme="default" variant="base" @click="resetFilters">重置</t-button>
        </div>
      </div>
    </t-card>

    <t-card :bordered="false">
      <template #actions>
        <t-button v-if="canManage" theme="primary" @click="openCreateDialog">
          <template #icon><add-icon /></template>
          新建群发任务
        </t-button>
      </template>
      <t-table
        row-key="id"
        :data="list"
        :columns="columns"
        :loading="loading"
        hover
        table-layout="fixed"
        aria-label="群发任务列表"
        @row-click="openDrawer"
      >
        <template #empty>
          <t-empty :description="loading ? '加载中...' : hasActiveFilters ? '未找到匹配结果' : '暂无群发任务'" />
        </template>
        <template #name="{ row }">
          <button class="mass-send-name" type="button" @click.stop="openDrawer(row)">{{ row.name }}</button>
        </template>
        <template #channel="{ row }">
          <t-tag variant="light">{{ channelLabel(row.channel) }}</t-tag>
        </template>
        <template #marketing="{ row }">
          <t-tag :theme="Number(row.is_marketing) === 1 ? 'warning' : 'default'" variant="light">
            {{ Number(row.is_marketing) === 1 ? '营销' : '非营销' }}
          </t-tag>
        </template>
        <template #status="{ row }">
          <t-tag :theme="statusTheme(row.status)" variant="light">{{ statusLabel(row.status) }}</t-tag>
        </template>
        <template #progress="{ row }">
          <div class="mass-send-progress">
            <t-progress
              :percentage="progressPercent(row)"
              :theme="Number(row.status) === 3 ? 'success' : 'line'"
              :label="`${row.sent_count || 0}/${row.total_count || 0}`"
            />
            <span v-if="Number(row.failed_count) > 0" class="mass-send-progress__failed">
              失败 {{ row.failed_count }}
            </span>
          </div>
        </template>
        <template #scheduledAt="{ row }">{{ formatDateTime(row.scheduled_at) }}</template>
        <template #createdAt="{ row }">{{ formatDateTime(row.created_at) }}</template>
        <template #actions="{ row }">
          <t-space size="small" @click.stop>
            <t-button theme="primary" variant="text" size="small" @click="openDrawer(row)">详情</t-button>
            <t-button
              v-if="canManage && row.can_edit"
              theme="primary"
              variant="text"
              size="small"
              @click="openEditDialog(row)"
              >编辑</t-button
            >
            <t-button
              v-if="canManage && row.can_send"
              theme="success"
              variant="text"
              size="small"
              @click="handleSend(row)"
              >发送</t-button
            >
            <t-button
              v-if="canManage && row.can_cancel"
              theme="warning"
              variant="text"
              size="small"
              @click="handleCancel(row)"
              >取消</t-button
            >
            <t-button
              v-if="canManage && row.can_edit"
              theme="danger"
              variant="text"
              size="small"
              @click="handleDelete(row)"
              >删除</t-button
            >
          </t-space>
        </template>
      </t-table>

      <div v-if="total > 0" class="mass-send-pagination">
        <t-pagination
          :current="pagination.page"
          :page-size="pagination.page_size"
          :total="total"
          :page-size-options="[20, 50, 100]"
          show-jumper
          @change="handlePaginationChange"
        />
      </div>
    </t-card>

    <!-- 创建/编辑对话框 -->
    <t-dialog
      v-model:visible="dialogVisible"
      :header="form.id ? '编辑群发任务' : '新建群发任务'"
      width="860px"
      :confirm-btn="{ content: '保存', theme: 'primary' }"
      :confirm-loading="saving"
      @confirm="submitForm"
    >
      <t-form ref="formRef" :data="form" :rules="rules" label-align="top">
        <div class="mass-send-form-grid">
          <t-form-item label="任务名称" name="name">
            <t-input v-model="form.name" :maxlength="100" placeholder="例如：双十一活动通知" />
          </t-form-item>
          <t-form-item label="发送通道" name="channel">
            <t-radio-group v-model="form.channel" @change="handleChannelChange">
              <t-radio-button :value="1">邮件</t-radio-button>
              <t-radio-button :value="2">站内信</t-radio-button>
              <t-radio-button :value="3">两者</t-radio-button>
            </t-radio-group>
          </t-form-item>
          <t-form-item label="营销标记" name="is_marketing">
            <t-switch v-model="form.is_marketing" :custom-value="[1, 0]" :label="['营销', '非营销']" />
            <span class="mass-send-form-hint">营销类任务将剔除关闭营销提醒的用户</span>
          </t-form-item>
          <t-form-item label="计划发送时间" name="scheduled_at">
            <t-date-picker
              v-model="form.scheduled_at"
              value-type="YYYY-MM-DD HH:mm:ss"
              enable-time-picker
              allow-input
              clearable
              style="width: 100%"
              placeholder="留空则保存为草稿，稍后手动发送"
            />
          </t-form-item>
          <t-form-item class="mass-send-form-span" label="内容方式" name="content_mode">
            <t-radio-group v-model="form.content_mode" :disabled="Number(form.channel) === 2">
              <t-radio value="template">套用邮件模板</t-radio>
              <t-radio value="custom">自定义内容</t-radio>
            </t-radio-group>
          </t-form-item>
          <t-form-item
            v-if="form.content_mode === 'template'"
            class="mass-send-form-span"
            label="邮件模板"
            name="template_code"
          >
            <t-select
              v-model="form.template_code"
              :options="templateOptions"
              :loading="templatesLoading"
              clearable
              filterable
              placeholder="请选择邮件模板（内容将同时用于站内信渲染）"
              @change="handleTemplateChange"
            />
          </t-form-item>
          <template v-else>
            <t-form-item class="mass-send-form-span" label="邮件主题" name="subject">
              <t-input v-model="form.subject" :maxlength="200" placeholder="邮件通道必填，最长 200 字" />
            </t-form-item>
            <t-form-item class="mass-send-form-span" label="内容" name="content">
              <t-textarea
                v-model="form.content"
                :autosize="{ minRows: 4, maxRows: 10 }"
                placeholder="支持模板变量，例如 {nickname}"
              />
            </t-form-item>
          </template>
        </div>
      </t-form>
      <UserSegmentFilterForm v-model="form.filter_json" />
      <div class="mass-send-preview">
        <t-button variant="outline" :loading="previewing" @click="handlePreview">试算命中用户数</t-button>
        <span v-if="previewCount !== null" class="mass-send-preview__count">
          预计命中 <strong>{{ previewCount }}</strong> 个用户
        </span>
      </div>
    </t-dialog>

    <!-- 详情抽屉 -->
    <t-drawer v-model:visible="drawerVisible" :header="detail ? `群发任务 · ${detail.name}` : '群发任务详情'" size="900px" :footer="false">
      <t-loading :loading="detailLoading" show-overlay>
        <template v-if="detail">
          <t-descriptions :column="2" bordered size="small" title="任务信息">
            <t-descriptions-item label="状态">
              <t-tag :theme="statusTheme(detail.status)" variant="light">{{ statusLabel(detail.status) }}</t-tag>
            </t-descriptions-item>
            <t-descriptions-item label="通道">{{ channelLabel(detail.channel) }}</t-descriptions-item>
            <t-descriptions-item label="营销标记">
              {{ Number(detail.is_marketing) === 1 ? '营销' : '非营销' }}
            </t-descriptions-item>
            <t-descriptions-item label="模板">
              {{ detail.template_name || '自定义内容' }}
            </t-descriptions-item>
            <t-descriptions-item label="进度">
              已发 {{ detail.sent_count }} / 共 {{ detail.total_count }}
              <span v-if="Number(detail.failed_count) > 0">（失败 {{ detail.failed_count }}）</span>
            </t-descriptions-item>
            <t-descriptions-item label="创建人 ID">{{ detail.created_by }}</t-descriptions-item>
            <t-descriptions-item label="计划发送时间">{{ formatDateTime(detail.scheduled_at) }}</t-descriptions-item>
            <t-descriptions-item label="开始 / 完成时间">
              {{ formatDateTime(detail.started_at) }} / {{ formatDateTime(detail.finished_at) }}
            </t-descriptions-item>
            <t-descriptions-item v-if="detail.subject" label="主题" :span="2">
              {{ detail.subject }}
            </t-descriptions-item>
            <t-descriptions-item v-if="detail.content" label="内容" :span="2">
              <div class="mass-send-content">{{ detail.content }}</div>
            </t-descriptions-item>
          </t-descriptions>

          <div class="mass-send-targets">
            <div class="mass-send-targets__head">
              <strong>发送目标</strong>
              <t-select
                v-model="targetStatusFilter"
                class="mass-send-targets__status"
                clearable
                size="small"
                placeholder="目标状态"
                @change="handleTargetSearch"
              >
                <t-option label="待发" :value="0" />
                <t-option label="成功" :value="1" />
                <t-option label="失败" :value="2" />
              </t-select>
            </div>
            <t-table
              row-key="id"
              size="small"
              :data="targets"
              :columns="targetColumns"
              :loading="targetsLoading"
              hover
              table-layout="fixed"
            >
              <template #empty>
                <t-empty description="暂无目标记录" />
              </template>
              <template #targetStatus="{ row }">
                <t-tag
                  :theme="row.status === 1 ? 'success' : row.status === 2 ? 'danger' : 'default'"
                  variant="light"
                >
                  {{ row.status_label }}
                </t-tag>
              </template>
              <template #sentAt="{ row }">{{ formatDateTime(row.sent_at) }}</template>
              <template #targetError="{ row }">
                <span :title="row.error || ''" class="mass-send-targets__error">{{ row.error || '-' }}</span>
              </template>
            </t-table>
            <div v-if="targetsTotal > targets.length" class="mass-send-pagination">
              <t-pagination
                size="small"
                :current="targetPagination.page"
                :page-size="targetPagination.page_size"
                :total="targetsTotal"
                @current-change="handleTargetPageChange"
              />
            </div>
          </div>
        </template>
      </t-loading>
    </t-drawer>
  </div>
</template>
<script setup lang="ts">
import { AddIcon, SearchIcon } from 'tdesign-icons-vue-next';
import type { FormInstanceFunctions, FormRule, PrimaryTableCol } from 'tdesign-vue-next';
import { DialogPlugin, MessagePlugin } from 'tdesign-vue-next';
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue';

import type { MassSendDetailRecord, MassSendPayload, MassSendRecord, MassSendTargetRecord } from '@/api/admin/massSend';
import { massSendsApi } from '@/api/admin/massSend';
import { settingsApi } from '@/api/admin/settings';
import type { NotificationTemplateItem } from '@/api/admin/types';
import UserSegmentFilterForm from '@/components/UserSegmentFilterForm.vue';
import { AdminPermissions, hasPermissionInList } from '@/constants/permissions';
import { useListPage } from '@/hooks/useListPage';
import { useUserStore } from '@/store';
import { formatDateTime } from '@/utils/format';
import { required } from '@/utils/formRules';
import { errorMessage } from '@/utils/userMessage';

const CHANNEL_EMAIL = 1;
const CHANNEL_INBOX = 2;

const userStore = useUserStore();
const canManage = hasPermissionInList(userStore.userInfo?.permissions || [], AdminPermissions.MASS_SEND_MANAGE);

interface MassSendFilter {
  keyword: string;
  status: string;
}

const {
  filters,
  list,
  total,
  loading,
  hasActiveFilters,
  pagination,
  loadList,
  handleSearch,
  resetFilters,
  handlePaginationChange,
} = useListPage<MassSendFilter, MassSendRecord>({
  defaultFilters: { keyword: '', status: '' },
  onError: (error) => MessagePlugin.error(errorMessage(error, '加载群发任务失败')),
  // 后端列表接口分页参数名为 per_page
  fetch: (params) => {
    const { page_size, ...rest } = params;
    return massSendsApi.list({ ...rest, per_page: page_size });
  },
});

const statusOptions = [
  { label: '草稿', value: 0 },
  { label: '待发送', value: 1 },
  { label: '发送中', value: 2 },
  { label: '已完成', value: 3 },
  { label: '已取消', value: 4 },
];

function statusLabel(value: unknown) {
  return (
    (
      {
        0: '草稿',
        1: '待发送',
        2: '发送中',
        3: '已完成',
        4: '已取消',
      } as Record<number, string>
    )[Number(value)] || '--'
  );
}

function statusTheme(value: unknown): 'default' | 'primary' | 'success' | 'warning' | 'danger' {
  const status = Number(value);
  if (status === 1) return 'warning';
  if (status === 2) return 'primary';
  if (status === 3) return 'success';
  return 'default';
}

function channelLabel(value: unknown) {
  return (
    (
      {
        1: '邮件',
        2: '站内信',
        3: '两者',
      } as Record<number, string>
    )[Number(value)] || '--'
  );
}

function progressPercent(row: MassSendRecord) {
  const total_ = Number(row.total_count || 0);
  if (total_ <= 0) return 0;
  return Math.min(100, Math.round((Number(row.sent_count || 0) / total_) * 100));
}

const columns: PrimaryTableCol<MassSendRecord>[] = [
  { colKey: 'name', title: '任务名称', minWidth: 180 },
  { colKey: 'channel', title: '通道', width: 90 },
  { colKey: 'marketing', title: '营销标记', width: 96 },
  { colKey: 'status', title: '状态', width: 96 },
  { colKey: 'progress', title: '发送进度', minWidth: 180 },
  { colKey: 'scheduledAt', title: '计划时间', width: 170 },
  { colKey: 'created_by', title: '创建人', width: 90 },
  { colKey: 'createdAt', title: '创建时间', width: 170 },
  { colKey: 'actions', title: '操作', fixed: 'right', width: 230 },
];

// ─── 创建/编辑 ────────────────────────────────────────────────────────────────

interface MassSendForm {
  id: number | string | null;
  name: string;
  channel: number;
  is_marketing: number;
  content_mode: 'template' | 'custom';
  template_code: string;
  subject: string;
  content: string;
  scheduled_at: string;
  filter_json: Record<string, unknown>;
}

const dialogVisible = ref(false);
const saving = ref(false);
const previewing = ref(false);
const previewCount = ref<number | null>(null);
const formRef = ref<FormInstanceFunctions>();
const templates = ref<NotificationTemplateItem[]>([]);
const templatesLoading = ref(false);

function createDefaultForm(): MassSendForm {
  return {
    id: null,
    name: '',
    channel: CHANNEL_EMAIL,
    is_marketing: 1,
    content_mode: 'template',
    template_code: '',
    subject: '',
    content: '',
    scheduled_at: '',
    filter_json: {},
  };
}

const form = reactive<MassSendForm>(createDefaultForm());

const rules: Record<string, FormRule[]> = {
  name: [required('请输入任务名称')],
};

// 后端 notification-templates 接口未返回模板 id（仅有 code），value 以 code 承载；
// 运行时若后端补了 id 字段则优先走 template_id 关联
const templateOptions = computed(() =>
  templates.value
    .filter((item) => item.channel === 'email')
    .map((item) => ({ label: String(item.name || item.code), value: String(item.code) })),
);

const selectedTemplate = computed(
  () => templates.value.find((item) => String(item.code) === form.template_code) || null,
);

async function loadTemplates() {
  if (templates.value.length > 0) return;
  templatesLoading.value = true;
  try {
    const response = await settingsApi.notificationTemplates();
    templates.value = response.list || [];
  } catch (error) {
    MessagePlugin.error(errorMessage(error, '加载通知模板失败'));
  } finally {
    templatesLoading.value = false;
  }
}

async function openCreateDialog() {
  Object.assign(form, createDefaultForm());
  previewCount.value = null;
  dialogVisible.value = true;
  await loadTemplates();
}

async function openEditDialog(row: MassSendRecord) {
  Object.assign(form, createDefaultForm());
  previewCount.value = null;
  dialogVisible.value = true;
  await loadTemplates();
  try {
    const detail = await massSendsApi.show(row.id);
    form.id = detail.id;
    form.name = String(detail.name || '');
    form.channel = Number(detail.channel || CHANNEL_EMAIL);
    form.is_marketing = Number(detail.is_marketing || 0);
    // 模板接口无 id，编辑时统一回退为自定义内容并带出 subject/content
    form.content_mode = 'custom';
    form.template_code = '';
    form.subject = String(detail.subject || '');
    form.content = String(detail.content || '');
    form.scheduled_at = detail.scheduled_at || '';
    form.filter_json = (detail.filter_json || {}) as Record<string, unknown>;
  } catch (error) {
    MessagePlugin.error(errorMessage(error, '加载群发任务详情失败'));
    dialogVisible.value = false;
  }
}

// 站内信通道不支持套用邮件模板，强制自定义内容
function handleChannelChange() {
  if (form.channel === CHANNEL_INBOX) {
    form.content_mode = 'custom';
    form.template_code = '';
  }
}

/**
 * 选择模板：若模板带运行时 id 则维持模板关联；
 * 否则（当前契约下 notification-templates 不返回 id）把模板内容带出为自定义内容。
 */
function handleTemplateChange(code: unknown) {
  if (!code) return;
  const template = selectedTemplate.value;
  if (!template) return;
  const templateId = (template as unknown as { id?: number }).id;
  if (templateId) return;
  form.subject = String(template.subject || '');
  form.content = String(template.content || '');
  form.content_mode = 'custom';
  form.template_code = '';
  MessagePlugin.warning('该模板未返回 id，已将模板内容带出为自定义内容');
}

function handlePreview() {
  previewing.value = true;
  massSendsApi
    .preview(form.filter_json || {})
    .then((response) => {
      previewCount.value = Number(response.total_count || 0);
    })
    .catch((error) => MessagePlugin.error(errorMessage(error, '试算失败')))
    .finally(() => {
      previewing.value = false;
    });
}

function buildPayload(): MassSendPayload {
  const template = form.content_mode === 'template' && Number(form.channel) !== CHANNEL_INBOX ? selectedTemplate.value : null;
  const templateId = template ? (template as unknown as { id?: number }).id : undefined;
  const useTemplate = Boolean(template && templateId);
  const payload: MassSendPayload = {
    name: form.name.trim(),
    channel: Number(form.channel),
    is_marketing: Number(form.is_marketing || 0),
    filter_json: form.filter_json || {},
    scheduled_at: form.scheduled_at || null,
  };
  if (useTemplate) {
    payload.template_id = Number(templateId);
    payload.subject = null;
    payload.content = null;
  } else {
    payload.template_id = null;
    payload.subject = form.subject.trim() || null;
    payload.content = form.content || null;
  }
  return payload;
}

async function submitForm() {
  const result = await formRef.value?.validate?.();
  if (result !== true) return;

  const payload = buildPayload();
  const useTemplate = payload.template_id !== null && payload.template_id !== undefined;
  if (!useTemplate && (!payload.subject || !payload.content)) {
    MessagePlugin.error('自定义内容时邮件主题与内容均必填');
    return;
  }
  if (useTemplate && !payload.template_id) {
    MessagePlugin.error('请选择邮件模板');
    return;
  }

  saving.value = true;
  try {
    if (form.id) {
      await massSendsApi.update(form.id, payload);
      MessagePlugin.success('群发任务已更新');
    } else {
      await massSendsApi.create(payload);
      MessagePlugin.success('群发任务已创建');
    }
    dialogVisible.value = false;
    await loadList();
  } catch (error) {
    MessagePlugin.error(errorMessage(error, '保存群发任务失败'));
  } finally {
    saving.value = false;
  }
}

// ─── 发送 / 取消 / 删除 ───────────────────────────────────────────────────────

function refreshIfShowing(massSendId: number | string) {
  if (drawerVisible.value && detail.value && detail.value.id === massSendId) {
    void refreshDrawer(massSendId, true);
  }
}

function handleSend(row: MassSendRecord) {
  const dialog = DialogPlugin.confirm({
    header: '发送群发任务',
    body: `确认启动群发任务「${row.name}」吗？启动后立即按计划时间开始发送。`,
    confirmBtn: { content: '确认发送', theme: 'primary' },
    async onConfirm() {
      try {
        await massSendsApi.send(row.id);
        MessagePlugin.success('群发任务已启动');
        dialog.destroy();
        await loadList();
        refreshIfShowing(row.id);
      } catch (error) {
        MessagePlugin.error(errorMessage(error, '发送失败'));
      }
    },
  });
}

function handleCancel(row: MassSendRecord) {
  const dialog = DialogPlugin.confirm({
    header: '取消群发任务',
    body: `确认取消群发任务「${row.name}」吗？取消后不再继续发送。`,
    confirmBtn: { content: '确认取消', theme: 'danger' },
    async onConfirm() {
      try {
        await massSendsApi.cancel(row.id);
        MessagePlugin.success('群发任务已取消');
        dialog.destroy();
        await loadList();
        refreshIfShowing(row.id);
      } catch (error) {
        MessagePlugin.error(errorMessage(error, '取消失败'));
      }
    },
  });
}

function handleDelete(row: MassSendRecord) {
  const dialog = DialogPlugin.confirm({
    header: '删除群发任务',
    body: `确认删除群发任务「${row.name}」吗？删除后不可恢复。`,
    confirmBtn: { content: '确定', theme: 'danger' },
    async onConfirm() {
      try {
        await massSendsApi.delete(row.id);
        MessagePlugin.success('群发任务已删除');
        dialog.destroy();
        await loadList();
      } catch (error) {
        MessagePlugin.error(errorMessage(error, '删除失败'));
      }
    },
  });
}

// ─── 详情抽屉 ─────────────────────────────────────────────────────────────────

const drawerVisible = ref(false);
const detailLoading = ref(false);
const detail = ref<MassSendDetailRecord | null>(null);

const targetColumns: PrimaryTableCol<MassSendTargetRecord>[] = [
  { colKey: 'user_id', title: '用户 ID', width: 90 },
  { colKey: 'email', title: '邮箱', minWidth: 200 },
  { colKey: 'targetStatus', title: '状态', width: 80 },
  { colKey: 'targetError', title: '错误信息', minWidth: 180 },
  { colKey: 'sentAt', title: '发送时间', width: 160 },
];

const targets = ref<MassSendTargetRecord[]>([]);
const targetsLoading = ref(false);
const targetsTotal = ref(0);
const targetStatusFilter = ref<number | ''>('');
const targetPagination = reactive({ page: 1, page_size: 20 });

function openDrawer(row: MassSendRecord) {
  // 先以列表行数据占位（subject/content 等详情字段由 refreshDrawer 立即补全），
  // 主题/内容区块有 v-if 守卫，占位期不展示。
  detail.value = {
    ...row,
    subject: null,
    content: null,
    filter_json: null,
    updated_at: null,
  } as MassSendDetailRecord;
  drawerVisible.value = true;
  targetStatusFilter.value = '';
  targetPagination.page = 1;
  void refreshDrawer(row.id, false);
}

async function refreshDrawer(id: number | string, silent: boolean) {
  if (!silent) detailLoading.value = true;
  try {
    const [detailResponse, targetsResponse] = await Promise.all([
      massSendsApi.show(id),
      massSendsApi.targets(id, {
        status: targetStatusFilter.value === '' ? undefined : targetStatusFilter.value,
        page: targetPagination.page,
        per_page: targetPagination.page_size,
      }),
    ]);
    detail.value = detailResponse;
    targets.value = targetsResponse.list || [];
    targetsTotal.value = Number(targetsResponse.total || 0);
  } catch (error) {
    MessagePlugin.error(errorMessage(error, '加载群发任务详情失败'));
  } finally {
    detailLoading.value = false;
  }
}

function handleTargetSearch() {
  targetPagination.page = 1;
  if (detail.value) void refreshDrawer(detail.value.id, true);
}

function handleTargetPageChange(page: number) {
  targetPagination.page = page;
  if (detail.value) void refreshDrawer(detail.value.id, true);
}

// ─── 发送中任务自动刷新（10s 轮询） ───────────────────────────────────────────

let pollTimer: ReturnType<typeof setInterval> | null = null;

function startPolling() {
  if (pollTimer !== null) return;
  pollTimer = setInterval(() => {
    const hasSending = list.value.some((row) => Number(row.status) === 2);
    const drawerSending = drawerVisible.value && detail.value && Number(detail.value.status) === 2;
    if (hasSending) void loadList();
    if (drawerSending && detail.value) void refreshDrawer(detail.value.id, true);
  }, 10000);
}

function stopPolling() {
  if (pollTimer !== null) {
    clearInterval(pollTimer);
    pollTimer = null;
  }
}

onMounted(() => {
  startPolling();
});

onBeforeUnmount(() => {
  stopPolling();
});
</script>
<style scoped>
.mass-send-filter {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  align-items: center;
}

.mass-send-filter__keyword {
  width: 260px;
}

.mass-send-filter__status {
  width: 160px;
}

.mass-send-filter__actions {
  display: flex;
  gap: 8px;
}

.mass-send-name {
  border: none;
  background: none;
  padding: 0;
  cursor: pointer;
  color: var(--td-brand-color);
  font-weight: 500;
  text-align: left;
}

.mass-send-progress {
  display: flex;
  align-items: center;
  gap: 8px;
}

.mass-send-progress :deep(.t-progress) {
  flex: 1;
}

.mass-send-progress__failed {
  color: var(--td-error-color);
  white-space: nowrap;
  font-size: 12px;
}

.mass-send-pagination {
  margin-top: 16px;
  display: flex;
  justify-content: flex-end;
}

.mass-send-form-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0 16px;
}

.mass-send-form-span {
  grid-column: span 2;
}

.mass-send-form-hint {
  margin-left: 8px;
  color: var(--td-text-color-secondary);
  font-size: 12px;
}

.mass-send-preview {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-top: 4px;
}

.mass-send-preview__count strong {
  color: var(--td-brand-color);
}

.mass-send-targets {
  margin-top: 20px;
}

.mass-send-targets__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 8px;
}

.mass-send-targets__status {
  width: 140px;
}

.mass-send-targets__error {
  display: inline-block;
  max-width: 100%;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  color: var(--td-error-color);
}

.mass-send-content {
  white-space: pre-wrap;
  word-break: break-word;
  max-height: 200px;
  overflow-y: auto;
}

@media (max-width: 768px) {
  .mass-send-form-grid {
    grid-template-columns: minmax(0, 1fr);
  }

  .mass-send-form-span {
    grid-column: span 1;
  }
}
</style>
