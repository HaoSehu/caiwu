<template>
  <div class="ticket-preset-replies-page">
    <t-card :bordered="false" :loading="loading">
      <template #title>工单预设回复</template>
      <template #subtitle>共 {{ replies.length }} 个模板 · 客服回复工单时可直接插入，支持占位符自动渲染</template>
      <template #actions>
        <t-space>
          <t-button v-if="canManage" variant="outline" @click="openCategoryDialog">分类管理</t-button>
          <t-button v-if="canManage" theme="primary" @click="openCreateDialog">
            <template #icon><add-icon /></template>
            新建模板
          </t-button>
        </t-space>
      </template>

      <div class="preset-filter-bar">
        <t-input v-model="keyword" clearable placeholder="按标题搜索" @enter="loadReplies" @clear="loadReplies" />
        <t-button variant="outline" @click="loadReplies">搜索</t-button>
        <t-select
          v-if="isMobile"
          v-model="activeCategoryId"
          clearable
          placeholder="全部分类"
          @change="loadReplies"
        >
          <t-option
            v-for="category in categories"
            :key="category.id"
            :label="category.name"
            :value="category.id"
          />
        </t-select>
      </div>

      <div v-if="!isMobile" class="preset-layout">
        <nav class="category-panel">
          <span class="category-panel__title">分类</span>
          <button type="button" class="category-item" :class="{ 'is-active': activeCategoryId === null }" @click="switchCategory(null)">
            <span>全部</span>
            <small>{{ replies.length }}</small>
          </button>
          <button
            v-for="category in categories"
            :key="category.id"
            type="button"
            class="category-item"
            :class="{ 'is-active': activeCategoryId === category.id }"
            @click="switchCategory(category.id)"
          >
            <span>{{ category.name }}</span>
            <small>{{ Number(category.replies_count || 0) }}</small>
          </button>
        </nav>

        <div class="table-scroll">
          <t-table row-key="id" :data="replies" :columns="columns" hover table-layout="fixed">
            <template #title="{ row }">
              <div class="stack-cell">
                <strong>{{ String(row.title || '--') }}</strong>
                <small>排序 {{ Number(row.sort_order || 0) }}</small>
              </div>
            </template>
            <template #categoryName="{ row }">{{ String(row.category_name || '--') }}</template>
            <template #departmentName="{ row }">
              <t-tag v-if="row.department_name" theme="primary" variant="light">{{ row.department_name }}</t-tag>
              <t-tag v-else theme="default" variant="light">通用</t-tag>
            </template>
            <template #isEnabled="{ row }">
              <t-tag :theme="Number(row.is_enabled) === 1 ? 'success' : 'default'" variant="light">
                {{ Number(row.is_enabled) === 1 ? '启用' : '停用' }}
              </t-tag>
            </template>
            <template #usageCount="{ row }">{{ Number(row.usage_count || 0) }} 次</template>
            <template #updatedAt="{ row }">{{ formatDateTime(row.updated_at) }}</template>
            <template #actions="{ row }">
              <t-space size="small">
                <t-button v-if="canManage" theme="primary" variant="text" @click="openEditDialog(row)">编辑</t-button>
                <t-button
                  v-if="canManage"
                  :theme="Number(row.is_enabled) === 1 ? 'warning' : 'success'"
                  variant="text"
                  @click="handleToggle(row)"
                >
                  {{ Number(row.is_enabled) === 1 ? '停用' : '启用' }}
                </t-button>
                <t-button v-if="canManage" theme="danger" variant="text" @click="handleDelete(row)">删除</t-button>
              </t-space>
            </template>
          </t-table>
        </div>
      </div>

      <div v-else class="mobile-list">
        <article v-for="row in replies" :key="row.id" class="reply-mobile-card">
          <div class="reply-mobile-card__head">
            <div>
              <strong>{{ String(row.title || '--') }}</strong>
              <t-tag :theme="Number(row.is_enabled) === 1 ? 'success' : 'default'" variant="light">
                {{ Number(row.is_enabled) === 1 ? '启用' : '停用' }}
              </t-tag>
            </div>
            <t-dropdown
              v-if="canManage"
              trigger="click"
              placement="bottom-right"
              :options="mobileActionOptions"
              @click="handleMobileAction(row, $event)"
            >
              <t-button variant="text" shape="square" aria-label="更多操作">...</t-button>
            </t-dropdown>
          </div>
          <dl>
            <div>
              <dt>分类</dt>
              <dd>{{ String(row.category_name || '--') }}</dd>
            </div>
            <div>
              <dt>适用部门</dt>
              <dd>{{ String(row.department_name || '通用') }}</dd>
            </div>
            <div>
              <dt>使用次数</dt>
              <dd>{{ Number(row.usage_count || 0) }} 次</dd>
            </div>
            <div>
              <dt>更新时间</dt>
              <dd>{{ formatDateTime(row.updated_at) }}</dd>
            </div>
          </dl>
        </article>
      </div>
    </t-card>

    <t-dialog
      v-model:visible="dialogVisible"
      :header="form.id ? '编辑模板' : '新建模板'"
      width="720px"
      :confirm-btn="{ content: '保存', theme: 'primary' }"
      :confirm-loading="saving"
      @confirm="submitForm"
    >
      <t-form ref="formRef" :data="form" :rules="rules" label-align="top">
        <div class="form-grid">
          <t-form-item label="标题" name="title">
            <t-input v-model="form.title" placeholder="例如：账号无法登录处理指引" :maxlength="100" />
          </t-form-item>
          <t-form-item label="分类" name="category_id">
            <t-select v-model="form.category_id" clearable placeholder="不选则无分类">
              <t-option
                v-for="category in enabledCategories"
                :key="category.id"
                :label="category.name"
                :value="category.id"
              />
            </t-select>
          </t-form-item>
          <t-form-item label="适用部门" name="department_id">
            <t-select v-model="form.department_id" clearable placeholder="不选则通用（全部部门可用）">
              <t-option
                v-for="department in departmentOptions"
                :key="department.id"
                :label="department.name"
                :value="department.id"
              />
            </t-select>
          </t-form-item>
          <t-form-item label="排序（越小越靠前）" name="sort_order">
            <t-input-number v-model="form.sort_order" :min="0" :max="9999" theme="normal" />
          </t-form-item>
          <t-form-item label="状态" name="is_enabled">
            <t-switch v-model="form.is_enabled" :custom-value="[1, 0]" :label="['启用', '停用']" />
          </t-form-item>
          <t-form-item class="form-grid__span" label="内容（支持占位符）" name="content">
            <t-textarea
              v-model="form.content"
              :autosize="{ minRows: 5, maxRows: 10 }"
              :maxlength="10000"
              placeholder="可用占位符：{{user_email}} {{user_nickname}} {{ticket_subject}} {{ticket_id}} {{service_name}}"
            />
          </t-form-item>
        </div>
      </t-form>
    </t-dialog>

    <t-dialog
      v-model:visible="categoryDialogVisible"
      header="分类管理"
      width="560px"
      :footer="false"
    >
      <div class="category-manage-list">
        <div v-for="category in categories" :key="category.id" class="category-manage-item">
          <div>
            <strong>{{ category.name }}</strong>
            <small style="margin-left: 8px; color: var(--td-text-color-placeholder)">
              {{ Number(category.replies_count || 0) }} 个模板 · 排序 {{ Number(category.sort_order || 0) }}
              {{ Number(category.is_enabled) === 1 ? '' : ' · 已停用' }}
            </small>
          </div>
          <t-space size="small">
            <t-button theme="primary" variant="text" @click="openCategoryEdit(category)">编辑</t-button>
            <t-button theme="danger" variant="text" @click="handleCategoryDelete(category)">删除</t-button>
          </t-space>
        </div>
        <t-empty v-if="categories.length === 0" description="暂无分类" />
      </div>

      <t-form ref="categoryFormRef" :data="categoryForm" :rules="categoryRules" label-align="top">
        <div class="form-grid">
          <t-form-item :label="categoryForm.id ? '编辑分类名称' : '新增分类名称'" name="name">
            <t-input v-model="categoryForm.name" placeholder="例如：账号问题" :maxlength="50" />
          </t-form-item>
          <t-form-item label="排序" name="sort_order">
            <t-input-number v-model="categoryForm.sort_order" :min="0" :max="9999" theme="normal" />
          </t-form-item>
        </div>
        <t-form-item label="状态" name="is_enabled">
          <t-switch v-model="categoryForm.is_enabled" :custom-value="[1, 0]" :label="['启用', '停用']" />
        </t-form-item>
      </t-form>
      <div style="display: flex; gap: 8px; justify-content: flex-end">
        <t-button v-if="categoryForm.id" variant="outline" @click="resetCategoryForm">取消编辑</t-button>
        <t-button theme="primary" :loading="categorySaving" @click="submitCategoryForm">
          {{ categoryForm.id ? '保存修改' : '新增分类' }}
        </t-button>
      </div>
    </t-dialog>
  </div>
</template>
<script setup lang="ts">
import './index.less';

import { AddIcon } from 'tdesign-icons-vue-next';
import type { DropdownOption, FormInstanceFunctions, FormRule, PrimaryTableCol } from 'tdesign-vue-next';
import { DialogPlugin, MessagePlugin } from 'tdesign-vue-next';
import { computed, onMounted, reactive, ref } from 'vue';

import type {
  TicketDepartmentRecord,
  TicketPresetReplyCategoryPayload,
  TicketPresetReplyCategoryRecord,
  TicketPresetReplyListParams,
  TicketPresetReplyPayload,
  TicketPresetReplyRecord,
} from '@/api/admin/ticket';
import { ticketsApi } from '@/api/admin/ticket';
import { AdminPermissions, hasPermissionInList } from '@/constants/permissions';
import { useMediaQuery } from '@/hooks/useMediaQuery';
import { useUserStore } from '@/store';
import { formatDateTime } from '@/utils/format';
import { required } from '@/utils/formRules';
import { errorMessage } from '@/utils/userMessage';

interface ReplyForm {
  id: number | string | null;
  category_id: number | null;
  department_id: number | null;
  title: string;
  content: string;
  is_enabled: number;
  sort_order: number;
}

interface CategoryForm {
  id: number | string | null;
  name: string;
  is_enabled: number;
  sort_order: number;
}

const userStore = useUserStore();

function userPermissions() {
  const info = userStore.userInfo as { permissions?: string[] };
  return Array.isArray(info.permissions) ? info.permissions : [];
}

const canManage = hasPermissionInList(userPermissions(), AdminPermissions.TICKET_MANAGE);

const loading = ref(false);
const saving = ref(false);
const dialogVisible = ref(false);
const formRef = ref<FormInstanceFunctions>();
const replies = ref<TicketPresetReplyRecord[]>([]);
const categories = ref<TicketPresetReplyCategoryRecord[]>([]);
const departments = ref<TicketDepartmentRecord[]>([]);
const keyword = ref('');
const activeCategoryId = ref<number | null>(null);
const isMobile = useMediaQuery('(max-width: 768px)');

const categoryDialogVisible = ref(false);
const categorySaving = ref(false);
const categoryFormRef = ref<FormInstanceFunctions>();
const categoryForm = reactive<CategoryForm>(createDefaultCategoryForm());

const form = reactive<ReplyForm>(createDefaultForm());

const rules: Record<string, FormRule[]> = {
  title: [required('请输入模板标题')],
  content: [required('请输入模板内容')],
};

const categoryRules: Record<string, FormRule[]> = {
  name: [required('请输入分类名称')],
};

const columns: PrimaryTableCol<TicketPresetReplyRecord>[] = [
  { colKey: 'title', title: '标题', minWidth: 200 },
  { colKey: 'categoryName', title: '分类', width: 130 },
  { colKey: 'departmentName', title: '适用部门', width: 120 },
  { colKey: 'isEnabled', title: '状态', width: 90 },
  { colKey: 'usageCount', title: '使用次数', width: 100 },
  { colKey: 'updatedAt', title: '更新时间', width: 170 },
  { colKey: 'actions', title: '操作', fixed: 'right', width: 200 },
];

const mobileActionOptions: DropdownOption[] = [
  { content: '编辑', value: 'edit' },
  { content: '启停', value: 'toggle' },
  { content: '删除', value: 'delete', theme: 'error' },
];

const enabledCategories = computed(() => categories.value.filter((item) => Number(item.is_enabled) === 1));

const departmentOptions = computed(() =>
  departments.value.filter((item) => Number(item.is_enabled) === 1).map((item) => ({ id: item.id, name: item.name })),
);

function createDefaultForm(): ReplyForm {
  return {
    id: null,
    category_id: null,
    department_id: null,
    title: '',
    content: '',
    is_enabled: 1,
    sort_order: 0,
  };
}

function createDefaultCategoryForm(): CategoryForm {
  return {
    id: null,
    name: '',
    is_enabled: 1,
    sort_order: 0,
  };
}

async function loadCategories() {
  try {
    categories.value = await ticketsApi.presetReplyCategories.list();
  } catch (error) {
    MessagePlugin.error(errorMessage(error, '加载预设回复分类失败'));
  }
}

async function loadDepartments() {
  try {
    departments.value = await ticketsApi.departments.list();
  } catch (error) {
    MessagePlugin.error(errorMessage(error, '加载工单部门失败'));
  }
}

async function loadReplies() {
  loading.value = true;
  try {
    const params: TicketPresetReplyListParams = {};
    if (activeCategoryId.value !== null) params.category_id = activeCategoryId.value;
    if (keyword.value.trim()) params.keyword = keyword.value.trim();
    replies.value = await ticketsApi.presetReplies.list(params);
  } catch (error) {
    MessagePlugin.error(errorMessage(error, '加载预设回复失败'));
  } finally {
    loading.value = false;
  }
}

function switchCategory(categoryId: number | null) {
  activeCategoryId.value = categoryId;
  void loadReplies();
}

function openCreateDialog() {
  Object.assign(form, createDefaultForm());
  if (activeCategoryId.value !== null) form.category_id = activeCategoryId.value;
  dialogVisible.value = true;
}

function openEditDialog(row: TicketPresetReplyRecord) {
  Object.assign(form, {
    id: row.id,
    category_id: row.category_id,
    department_id: row.department_id,
    title: String(row.title || ''),
    content: String(row.content || ''),
    is_enabled: Number(row.is_enabled ?? 1),
    sort_order: Number(row.sort_order || 0),
  });
  dialogVisible.value = true;
}

async function submitForm() {
  const result = await formRef.value?.validate?.();
  if (result !== true) return;

  const payload: TicketPresetReplyPayload = {
    category_id: form.category_id ?? null,
    department_id: form.department_id ?? null,
    title: form.title.trim(),
    content: form.content,
    is_enabled: Number(form.is_enabled ?? 1),
    sort_order: Number(form.sort_order || 0),
  };

  saving.value = true;
  try {
    if (form.id) {
      await ticketsApi.presetReplies.update(form.id, payload);
      MessagePlugin.success('模板已更新');
    } else {
      await ticketsApi.presetReplies.create(payload);
      MessagePlugin.success('模板已创建');
    }
    dialogVisible.value = false;
    await Promise.all([loadReplies(), ...(canManage ? [loadCategories()] : [])]);
  } catch (error) {
    MessagePlugin.error(errorMessage(error, '保存模板失败'));
  } finally {
    saving.value = false;
  }
}

function handleToggle(row: TicketPresetReplyRecord) {
  const enabled = Number(row.is_enabled) === 1;
  const dialog = DialogPlugin.confirm({
    header: enabled ? '停用模板' : '启用模板',
    body: `确认${enabled ? '停用' : '启用'}模板“${row.title}”吗？`,
    confirmBtn: { content: '确定', theme: enabled ? 'warning' : 'primary' },
    async onConfirm() {
      try {
        await ticketsApi.presetReplies.update(row.id, {
          category_id: row.category_id,
          department_id: row.department_id,
          title: String(row.title || ''),
          content: String(row.content || ''),
          is_enabled: enabled ? 0 : 1,
          sort_order: Number(row.sort_order || 0),
        });
        MessagePlugin.success(enabled ? '模板已停用' : '模板已启用');
        dialog.destroy();
        await loadReplies();
      } catch (error) {
        MessagePlugin.error(errorMessage(error, '更新模板状态失败'));
      }
    },
  });
}

function handleDelete(row: TicketPresetReplyRecord) {
  const dialog = DialogPlugin.confirm({
    header: '删除模板',
    body: `确认删除模板“${row.title}”吗？`,
    confirmBtn: { content: '确定', theme: 'danger' },
    async onConfirm() {
      try {
        await ticketsApi.presetReplies.delete(row.id);
        MessagePlugin.success('模板已删除');
        dialog.destroy();
        await Promise.all([loadReplies(), loadCategories()]);
      } catch (error) {
        MessagePlugin.error(errorMessage(error, '删除模板失败'));
      }
    },
  });
}

function handleMobileAction(row: TicketPresetReplyRecord, option: DropdownOption) {
  const action = String(option.value || '');
  if (action === 'edit') {
    openEditDialog(row);
    return;
  }
  if (action === 'toggle') handleToggle(row);
  if (action === 'delete') handleDelete(row);
}

function resetCategoryForm() {
  Object.assign(categoryForm, createDefaultCategoryForm());
}

function openCategoryDialog() {
  resetCategoryForm();
  categoryDialogVisible.value = true;
  void loadCategories();
}

function openCategoryEdit(category: TicketPresetReplyCategoryRecord) {
  Object.assign(categoryForm, {
    id: category.id,
    name: String(category.name || ''),
    is_enabled: Number(category.is_enabled ?? 1),
    sort_order: Number(category.sort_order || 0),
  });
}

async function submitCategoryForm() {
  const result = await categoryFormRef.value?.validate?.();
  if (result !== true) return;

  const payload: TicketPresetReplyCategoryPayload = {
    name: categoryForm.name.trim(),
    is_enabled: Number(categoryForm.is_enabled ?? 1),
    sort_order: Number(categoryForm.sort_order || 0),
  };

  categorySaving.value = true;
  try {
    if (categoryForm.id) {
      await ticketsApi.presetReplyCategories.update(categoryForm.id, payload);
      MessagePlugin.success('分类已更新');
    } else {
      await ticketsApi.presetReplyCategories.create(payload);
      MessagePlugin.success('分类已创建');
    }
    resetCategoryForm();
    await loadCategories();
  } catch (error) {
    MessagePlugin.error(errorMessage(error, '保存分类失败'));
  } finally {
    categorySaving.value = false;
  }
}

function handleCategoryDelete(category: TicketPresetReplyCategoryRecord) {
  const dialog = DialogPlugin.confirm({
    header: '删除分类',
    body: `确认删除分类“${category.name}”吗？该分类下模板会变为「无分类」，不会被删除。`,
    confirmBtn: { content: '确定', theme: 'danger' },
    async onConfirm() {
      try {
        await ticketsApi.presetReplyCategories.delete(category.id);
        MessagePlugin.success('分类已删除');
        dialog.destroy();
        if (activeCategoryId.value === category.id) activeCategoryId.value = null;
        await Promise.all([loadCategories(), loadReplies()]);
      } catch (error) {
        MessagePlugin.error(errorMessage(error, '删除分类失败'));
      }
    },
  });
}

onMounted(() => {
  // 分类接口要求 ticket.manage：reply-only 客服跳过加载（UI 写入口已按 canManage 显隐）
  void Promise.allSettled([...(canManage ? [loadCategories()] : []), loadDepartments(), loadReplies()]);
});
</script>
