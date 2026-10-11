<template>
  <div class="care-plans-page">
    <t-card :bordered="false">
      <div class="care-plan-filter">
        <t-input
          v-model="filters.keyword"
          class="care-plan-filter__keyword"
          clearable
          placeholder="搜索计划名称"
          @enter="handleSearch"
          @clear="handleSearch"
        >
          <template #suffix-icon><search-icon /></template>
        </t-input>
        <t-select
          v-model="filters.type"
          class="care-plan-filter__type"
          clearable
          placeholder="计划类型"
          @change="handleSearch"
        >
          <t-option v-for="option in typeOptions" :key="option.value" :label="option.label" :value="option.value" />
        </t-select>
        <div class="care-plan-filter__actions">
          <t-button theme="primary" @click="handleSearch">查询</t-button>
          <t-button theme="default" variant="base" @click="resetFilters">重置</t-button>
        </div>
      </div>
    </t-card>

    <t-card :bordered="false">
      <template #actions>
        <t-button v-if="canManage" theme="primary" @click="openCreateDialog">
          <template #icon><add-icon /></template>
          新建关怀计划
        </t-button>
      </template>
      <t-table row-key="id" :data="list" :columns="columns" :loading="loading" hover table-layout="fixed">
        <template #empty>
          <t-empty :description="loading ? '加载中...' : hasActiveFilters ? '未找到匹配结果' : '暂无关怀计划'" />
        </template>
        <template #type="{ row }">
          <t-tag :theme="typeTheme(row.type)" variant="light">{{ typeLabel(row.type) }}</t-tag>
        </template>
        <template #offsetDays="{ row }">{{ offsetLabel(row.type, row.offset_days) }}</template>
        <template #channel="{ row }">
          <t-tag variant="light">{{ channelLabel(row.channel) }}</t-tag>
        </template>
        <template #template="{ row }">{{ row.template_name || '自定义内容' }}</template>
        <template #enabled="{ row }">
          <t-tag :theme="Number(row.is_enabled) === 1 ? 'success' : 'default'" variant="light">
            {{ Number(row.is_enabled) === 1 ? '启用' : '停用' }}
          </t-tag>
        </template>
        <template #lastPlanned="{ row }">{{ row.last_planned_date || '-' }}</template>
        <template #createdAt="{ row }">{{ formatDateTime(row.created_at) }}</template>
        <template #actions="{ row }">
          <t-space size="small">
            <t-button theme="primary" variant="text" size="small" @click="openRunsDrawer(row)">运行历史</t-button>
            <t-button
              v-if="canManage"
              theme="success"
              variant="text"
              size="small"
              @click="openTriggerDialog(row)"
              >触发</t-button
            >
            <t-button v-if="canManage" theme="primary" variant="text" size="small" @click="openEditDialog(row)"
              >编辑</t-button
            >
            <t-button v-if="canManage" theme="danger" variant="text" size="small" @click="handleDelete(row)"
              >删除</t-button
            >
          </t-space>
        </template>
      </t-table>

      <div v-if="total > 0" class="care-plan-pagination">
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
      :header="form.id ? '编辑关怀计划' : '新建关怀计划'"
      width="860px"
      :confirm-btn="{ content: '保存', theme: 'primary' }"
      :confirm-loading="saving"
      @confirm="submitForm"
    >
      <t-form ref="formRef" :data="form" :rules="rules" label-align="top">
        <div class="care-plan-form-grid">
          <t-form-item label="计划名称" name="name">
            <t-input v-model="form.name" :maxlength="100" placeholder="例如：生日祝福关怀" />
          </t-form-item>
          <t-form-item label="计划类型" name="type">
            <t-radio-group v-model="form.type">
              <t-radio-button :value="1">生日</t-radio-button>
              <t-radio-button :value="2">到期</t-radio-button>
              <t-radio-button :value="3">召回</t-radio-button>
              <t-radio-button :value="4">自定义</t-radio-button>
            </t-radio-group>
          </t-form-item>
          <t-form-item label="时间偏移（天）" name="offset_days">
            <t-input-number v-model="form.offset_days" :min="-365" :max="365" :step="1" theme="column" />
            <span class="care-plan-form-hint">{{ offsetHint }}</span>
          </t-form-item>
          <t-form-item label="发送通道" name="channel">
            <t-radio-group v-model="form.channel" @change="handleChannelChange">
              <t-radio-button :value="1">邮件</t-radio-button>
              <t-radio-button :value="2">站内信</t-radio-button>
              <t-radio-button :value="3">两者</t-radio-button>
            </t-radio-group>
          </t-form-item>
          <t-form-item label="是否启用" name="is_enabled">
            <t-switch v-model="form.is_enabled" :custom-value="[1, 0]" :label="['启用', '停用']" />
            <span class="care-plan-form-hint">仅启用中的计划可被每日调度与手动触发</span>
          </t-form-item>
          <t-form-item label="内容方式" name="content_mode">
            <t-radio-group v-model="form.content_mode" :disabled="Number(form.channel) === 2">
              <t-radio value="template">套用邮件模板</t-radio>
              <t-radio value="custom">自定义内容</t-radio>
            </t-radio-group>
          </t-form-item>
          <t-form-item
            v-if="form.content_mode === 'template'"
            class="care-plan-form-span"
            label="邮件模板"
            name="template_code"
          >
            <t-select
              v-model="form.template_code"
              :options="templateOptions"
              :loading="templatesLoading"
              clearable
              filterable
              placeholder="请选择邮件模板"
              @change="handleTemplateChange"
            />
          </t-form-item>
          <template v-else>
            <t-form-item class="care-plan-form-span" label="邮件主题" name="subject">
              <t-input v-model="form.subject" :maxlength="200" placeholder="邮件通道必填，最长 200 字" />
            </t-form-item>
            <t-form-item class="care-plan-form-span" label="内容" name="content">
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
    </t-dialog>

    <!-- 触发对话框 -->
    <t-dialog
      v-model:visible="triggerVisible"
      :header="`触发关怀计划 · ${triggerRow?.name || ''}`"
      width="480px"
      :confirm-btn="{ content: triggerDryRun ? '开始试算' : '确认触发', theme: 'primary' }"
      :confirm-loading="triggering"
      @confirm="confirmTrigger"
    >
      <div class="care-plan-trigger">
        <p class="care-plan-trigger__tip">
          手动触发会立即按计划执行一轮发送（受运行周期幂等保护，同计划同目标日期不会重发）。
        </p>
        <t-checkbox v-model="triggerDryRun">仅试算（dry run，不发送任何消息）</t-checkbox>
      </div>
    </t-dialog>

    <!-- 运行历史抽屉 -->
    <t-drawer
      v-model:visible="runsVisible"
      :header="runsRow ? `运行历史 · ${runsRow.name}` : '运行历史'"
      size="820px"
      :footer="false"
    >
      <t-table
        row-key="id"
        size="small"
        :data="runs"
        :columns="runColumns"
        :loading="runsLoading"
        hover
        table-layout="fixed"
      >
        <template #empty>
          <t-empty description="暂无运行记录" />
        </template>
        <template #runStatus="{ row }">
          <t-tag :theme="runStatusTheme(row.status)" variant="light">{{ row.status_label }}</t-tag>
        </template>
        <template #runProgress="{ row }">
          发送 {{ row.sent_count }} / 共 {{ row.total_count }}
          <span v-if="Number(row.failed_count) > 0" class="care-plan-run-failed">（失败 {{ row.failed_count }}）</span>
          <span v-if="Number(row.skipped_count) > 0">（跳过 {{ row.skipped_count }}）</span>
        </template>
        <template #startedAt="{ row }">{{ formatDateTime(row.started_at) }}</template>
        <template #finishedAt="{ row }">{{ formatDateTime(row.finished_at) }}</template>
      </t-table>
      <div v-if="runsTotal > runs.length" class="care-plan-pagination">
        <t-pagination
          size="small"
          :current="runsPagination.page"
          :page-size="runsPagination.page_size"
          :total="runsTotal"
          @current-change="handleRunsPageChange"
        />
      </div>
    </t-drawer>
  </div>
</template>
<script setup lang="ts">
import { AddIcon, SearchIcon } from 'tdesign-icons-vue-next';
import type { FormInstanceFunctions, FormRule, PrimaryTableCol } from 'tdesign-vue-next';
import { DialogPlugin, MessagePlugin } from 'tdesign-vue-next';
import { computed, reactive, ref } from 'vue';

import type { CarePlanPayload, CarePlanRecord, CareTaskRunRecord } from '@/api/admin/carePlan';
import { carePlansApi } from '@/api/admin/carePlan';
import { settingsApi } from '@/api/admin/settings';
import type { NotificationTemplateItem } from '@/api/admin/types';
import UserSegmentFilterForm from '@/components/UserSegmentFilterForm.vue';
import { AdminPermissions, hasPermissionInList } from '@/constants/permissions';
import { useListPage } from '@/hooks/useListPage';
import { useUserStore } from '@/store';
import { formatDateTime } from '@/utils/format';
import { required } from '@/utils/formRules';
import { errorMessage } from '@/utils/userMessage';

const CHANNEL_INBOX = 2;

const userStore = useUserStore();
const canManage = hasPermissionInList(userStore.userInfo?.permissions || [], AdminPermissions.CARE_PLAN_MANAGE);

interface CarePlanFilter {
  keyword: string;
  type: string;
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
} = useListPage<CarePlanFilter, CarePlanRecord>({
  defaultFilters: { keyword: '', type: '' },
  onError: (error) => MessagePlugin.error(errorMessage(error, '加载关怀计划失败')),
  // 后端列表接口分页参数名为 per_page
  fetch: (params) => {
    const { page_size, ...rest } = params;
    return carePlansApi.list({ ...rest, per_page: page_size });
  },
});

const typeOptions = [
  { label: '生日关怀', value: 1 },
  { label: '到期提醒', value: 2 },
  { label: '沉默召回', value: 3 },
  { label: '自定义', value: 4 },
];

function typeLabel(value: unknown) {
  return (
    (
      {
        1: '生日关怀',
        2: '到期提醒',
        3: '沉默召回',
        4: '自定义',
      } as Record<number, string>
    )[Number(value)] || '--'
  );
}

function typeTheme(value: unknown): 'default' | 'primary' | 'success' | 'warning' {
  const type = Number(value);
  if (type === 1) return 'success';
  if (type === 2) return 'warning';
  if (type === 3) return 'primary';
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

function offsetLabel(type: unknown, offset: unknown) {
  const value = Number(offset || 0);
  const typeNumber = Number(type);
  if (typeNumber === 1) return value >= 0 ? `提前 ${value} 天` : `延后 ${Math.abs(value)} 天`;
  if (typeNumber === 2) return `提前 ${value} 天`;
  if (typeNumber === 3) return `沉默 ${value} 天`;
  return `${value} 天`;
}

const columns: PrimaryTableCol<CarePlanRecord>[] = [
  { colKey: 'name', title: '计划名称', minWidth: 180 },
  { colKey: 'type', title: '类型', width: 110 },
  { colKey: 'offsetDays', title: '时间偏移', width: 110 },
  { colKey: 'channel', title: '通道', width: 90 },
  { colKey: 'template', title: '模板', minWidth: 160 },
  { colKey: 'enabled', title: '启用', width: 90 },
  { colKey: 'lastPlanned', title: '最近执行日期', width: 130 },
  { colKey: 'createdAt', title: '创建时间', width: 170 },
  { colKey: 'actions', title: '操作', fixed: 'right', width: 240 },
];

// ─── 创建/编辑 ────────────────────────────────────────────────────────────────

interface CarePlanForm {
  id: number | string | null;
  name: string;
  type: number;
  offset_days: number | null;
  channel: number;
  is_enabled: number;
  content_mode: 'template' | 'custom';
  template_code: string;
  subject: string;
  content: string;
  filter_json: Record<string, unknown>;
}

const dialogVisible = ref(false);
const saving = ref(false);
const formRef = ref<FormInstanceFunctions>();
const templates = ref<NotificationTemplateItem[]>([]);
const templatesLoading = ref(false);

function createDefaultForm(): CarePlanForm {
  return {
    id: null,
    name: '',
    type: 1,
    offset_days: 3,
    channel: 1,
    is_enabled: 0,
    content_mode: 'template',
    template_code: '',
    subject: '',
    content: '',
    filter_json: {},
  };
}

const form = reactive<CarePlanForm>(createDefaultForm());

const rules: Record<string, FormRule[]> = {
  name: [required('请输入计划名称')],
};

const offsetHint = computed(() => {
  const type = Number(form.type);
  if (type === 1) return '生日关怀：生日前 N 天发送，支持负数（生日后 N 天）';
  if (type === 2) return '到期提醒：服务到期前 N 天发送';
  if (type === 3) return '沉默召回：最近登录早于 N 天前的用户';
  return '自定义类型由调度按天触发';
});

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
  dialogVisible.value = true;
  await loadTemplates();
}

async function openEditDialog(row: CarePlanRecord) {
  Object.assign(form, createDefaultForm());
  dialogVisible.value = true;
  await loadTemplates();
  try {
    const detail = await carePlansApi.show(row.id);
    form.id = detail.id;
    form.name = String(detail.name || '');
    form.type = Number(detail.type || 1);
    form.offset_days = detail.offset_days !== null && detail.offset_days !== undefined ? Number(detail.offset_days) : null;
    form.channel = Number(detail.channel || 1);
    form.is_enabled = Number(detail.is_enabled || 0);
    // 模板接口无 id，编辑时统一回退为自定义内容并带出 subject/content
    form.content_mode = 'custom';
    form.template_code = '';
    form.subject = String(detail.subject || '');
    form.content = String(detail.content || '');
    form.filter_json = (detail.filter_json || {}) as Record<string, unknown>;
  } catch (error) {
    MessagePlugin.error(errorMessage(error, '加载关怀计划详情失败'));
    dialogVisible.value = false;
  }
}

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

function buildPayload(): CarePlanPayload {
  const template = form.content_mode === 'template' && Number(form.channel) !== CHANNEL_INBOX ? selectedTemplate.value : null;
  const templateId = template ? (template as unknown as { id?: number }).id : undefined;
  const useTemplate = Boolean(template && templateId);
  const payload: CarePlanPayload = {
    name: form.name.trim(),
    type: Number(form.type),
    offset_days: form.offset_days === null ? null : Number(form.offset_days),
    channel: Number(form.channel),
    is_enabled: Number(form.is_enabled || 0),
    filter_json: form.filter_json && Object.keys(form.filter_json).length > 0 ? form.filter_json : null,
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
  if (payload.template_id === null && (!payload.subject || !payload.content)) {
    MessagePlugin.error('自定义内容时邮件主题与内容均必填');
    return;
  }

  saving.value = true;
  try {
    if (form.id) {
      await carePlansApi.update(form.id, payload);
      MessagePlugin.success('关怀计划已更新');
    } else {
      await carePlansApi.create(payload);
      MessagePlugin.success('关怀计划已创建');
    }
    dialogVisible.value = false;
    await loadList();
  } catch (error) {
    MessagePlugin.error(errorMessage(error, '保存关怀计划失败'));
  } finally {
    saving.value = false;
  }
}

function handleDelete(row: CarePlanRecord) {
  const dialog = DialogPlugin.confirm({
    header: '删除关怀计划',
    body: `确认删除关怀计划「${row.name}」吗？删除后调度不再执行该计划。`,
    confirmBtn: { content: '确定', theme: 'danger' },
    async onConfirm() {
      try {
        await carePlansApi.delete(row.id);
        MessagePlugin.success('关怀计划已删除');
        dialog.destroy();
        await loadList();
      } catch (error) {
        MessagePlugin.error(errorMessage(error, '删除失败'));
      }
    },
  });
}

// ─── 手动触发 ─────────────────────────────────────────────────────────────────

const triggerVisible = ref(false);
const triggering = ref(false);
const triggerDryRun = ref(false);
const triggerRow = ref<CarePlanRecord | null>(null);

function openTriggerDialog(row: CarePlanRecord) {
  triggerRow.value = row;
  triggerDryRun.value = false;
  triggerVisible.value = true;
}

async function confirmTrigger() {
  if (!triggerRow.value) return;
  const plan = triggerRow.value;
  triggering.value = true;
  try {
    const result = await carePlansApi.trigger(plan.id, triggerDryRun.value);
    if (triggerDryRun.value) {
      MessagePlugin.success(`试算完成（未发送）：预计命中 ${result.total_count ?? 0} 个用户，跳过 ${result.skipped_count ?? 0}`);
    } else {
      MessagePlugin.success(`触发完成：发送 ${result.sent_count ?? 0}，失败 ${result.failed_count ?? 0}，跳过 ${result.skipped_count ?? 0}`);
    }
    triggerVisible.value = false;
    await loadList();
    if (runsVisible.value && runsRow.value?.id === plan.id) void loadRuns(plan.id);
  } catch (error) {
    MessagePlugin.error(errorMessage(error, '触发失败'));
  } finally {
    triggering.value = false;
  }
}

// ─── 运行历史 ─────────────────────────────────────────────────────────────────

const runsVisible = ref(false);
const runsLoading = ref(false);
const runsRow = ref<CarePlanRecord | null>(null);
const runs = ref<CareTaskRunRecord[]>([]);
const runsTotal = ref(0);
const runsPagination = reactive({ page: 1, page_size: 20 });

const runColumns: PrimaryTableCol<CareTaskRunRecord>[] = [
  { colKey: 'planned_date', title: '执行日期', width: 110 },
  { colKey: 'runStatus', title: '状态', width: 90 },
  { colKey: 'runProgress', title: '执行结果', minWidth: 200 },
  { colKey: 'startedAt', title: '开始时间', width: 160 },
  { colKey: 'finishedAt', title: '完成时间', width: 160 },
];

function runStatusTheme(value: unknown): 'default' | 'primary' | 'success' | 'warning' | 'danger' {
  const status = Number(value);
  if (status === 1) return 'success';
  if (status === 2) return 'warning';
  if (status === 3) return 'danger';
  if (status === 4) return 'default';
  return 'primary';
}

function openRunsDrawer(row: CarePlanRecord) {
  runsRow.value = row;
  runsPagination.page = 1;
  runsVisible.value = true;
  void loadRuns(row.id);
}

async function loadRuns(planId: number | string) {
  runsLoading.value = true;
  try {
    const response = await carePlansApi.runs(planId, { page: runsPagination.page, per_page: runsPagination.page_size });
    runs.value = response.list || [];
    runsTotal.value = Number(response.total || 0);
  } catch (error) {
    MessagePlugin.error(errorMessage(error, '加载运行历史失败'));
  } finally {
    runsLoading.value = false;
  }
}

function handleRunsPageChange(page: number) {
  runsPagination.page = page;
  if (runsRow.value) void loadRuns(runsRow.value.id);
}
</script>
<style scoped>
.care-plan-filter {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  align-items: center;
}

.care-plan-filter__keyword {
  width: 260px;
}

.care-plan-filter__type {
  width: 160px;
}

.care-plan-filter__actions {
  display: flex;
  gap: 8px;
}

.care-plan-pagination {
  margin-top: 16px;
  display: flex;
  justify-content: flex-end;
}

.care-plan-form-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0 16px;
}

.care-plan-form-span {
  grid-column: span 2;
}

.care-plan-form-hint {
  margin-left: 8px;
  color: var(--td-text-color-secondary);
  font-size: 12px;
}

.care-plan-trigger__tip {
  margin: 0 0 12px;
  color: var(--td-text-color-secondary);
  line-height: 1.6;
}

.care-plan-run-failed {
  color: var(--td-error-color);
}

@media (max-width: 768px) {
  .care-plan-form-grid {
    grid-template-columns: minmax(0, 1fr);
  }

  .care-plan-form-span {
    grid-column: span 1;
  }
}
</style>
