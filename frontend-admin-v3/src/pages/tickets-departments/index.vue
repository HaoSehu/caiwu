<template>
  <div class="tickets-departments-page">
    <t-card :bordered="false" :loading="loading">
      <template #title>工单部门</template>
      <template #subtitle
        >共 {{ departments.length }} 个部门 · 分派模式决定转派/建单时未指定处理人的自动分派策略</template
      >
      <template #actions>
        <t-space>
          <t-button v-if="canManage" theme="primary" @click="openCreateDialog">
            <template #icon><add-icon /></template>
            新建部门
          </t-button>
        </t-space>
      </template>

      <div v-if="!isMobile" class="table-scroll">
        <t-table row-key="id" :data="departments" :columns="columns" hover table-layout="fixed">
          <template #name="{ row }">
            <div class="stack-cell">
              <strong>{{ String(row.name || '--') }}</strong>
              <small>排序 {{ Number(row.sort_order || 0) }}</small>
            </div>
          </template>
          <template #autoAssignMode="{ row }">
            <t-tag :theme="autoAssignModeTheme(row.auto_assign_mode)" variant="light">
              {{ autoAssignModeLabel(row.auto_assign_mode) }}
            </t-tag>
          </template>
          <template #isEnabled="{ row }">
            <t-tag :theme="Number(row.is_enabled) === 1 ? 'success' : 'default'" variant="light">
              {{ Number(row.is_enabled) === 1 ? '启用' : '停用' }}
            </t-tag>
          </template>
          <template #staffCount="{ row }">{{ Number(row.staff_count || 0) }} 人</template>
          <template #updatedAt="{ row }">{{ formatDateTime(row.updated_at) }}</template>
          <template #actions="{ row }">
            <t-space size="small">
              <t-button v-if="canManage" theme="primary" variant="text" @click="openStaffDialog(row)">
                成员设置
              </t-button>
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

      <div v-else class="mobile-list">
        <article v-for="row in departments" :key="row.id" class="department-mobile-card">
          <div class="department-mobile-card__head">
            <div>
              <strong>{{ String(row.name || '--') }}</strong>
              <t-tag :theme="Number(row.is_enabled) === 1 ? 'success' : 'default'" variant="light">
                {{ Number(row.is_enabled) === 1 ? '启用' : '停用' }}
              </t-tag>
              <t-tag :theme="autoAssignModeTheme(row.auto_assign_mode)" variant="light">
                {{ autoAssignModeLabel(row.auto_assign_mode) }}
              </t-tag>
            </div>
            <t-dropdown
              v-if="canManage"
              trigger="click"
              placement="bottom-right"
              :options="mobileActionOptions"
              @click="handleMobileAction(row, $event)"
            >
              <t-button class="department-mobile-card__more" variant="text" shape="square" aria-label="更多操作">
                ...
              </t-button>
            </t-dropdown>
          </div>
          <dl>
            <div>
              <dt>描述</dt>
              <dd>{{ String(row.description || '--') }}</dd>
            </div>
            <div>
              <dt>成员数</dt>
              <dd>{{ Number(row.staff_count || 0) }} 人</dd>
            </div>
          </dl>
        </article>
      </div>
    </t-card>

    <t-dialog
      v-model:visible="dialogVisible"
      :header="form.id ? '编辑部门' : '新建部门'"
      width="720px"
      :confirm-btn="{ content: '保存', theme: 'primary' }"
      :confirm-loading="saving"
      @confirm="submitForm"
    >
      <t-form ref="formRef" :data="form" :rules="rules" label-align="top">
        <div class="form-grid">
          <t-form-item label="部门名称" name="name">
            <t-input v-model="form.name" placeholder="例如：技术支持" :maxlength="50" />
          </t-form-item>
          <t-form-item label="分派模式" name="auto_assign_mode">
            <t-select v-model="form.auto_assign_mode">
              <t-option
                v-for="option in autoAssignModeOptions"
                :key="option.value"
                :label="option.label"
                :value="option.value"
              />
            </t-select>
          </t-form-item>
          <t-form-item label="排序（越小越靠前）" name="sort_order">
            <t-input-number v-model="form.sort_order" :min="0" :max="9999" theme="normal" />
          </t-form-item>
          <t-form-item label="状态" name="is_enabled">
            <t-switch v-model="form.is_enabled" :custom-value="[1, 0]" :label="['启用', '停用']" />
          </t-form-item>
          <t-form-item class="form-grid__span" label="描述" name="description">
            <t-textarea v-model="form.description" :autosize="{ minRows: 3, maxRows: 5 }" :maxlength="255" />
          </t-form-item>
        </div>
      </t-form>
    </t-dialog>

    <t-dialog
      v-model:visible="staffDialogVisible"
      :header="staffDialogDepartment ? `成员设置 · ${staffDialogDepartment.name}` : '成员设置'"
      width="640px"
      :confirm-btn="{ content: '保存', theme: 'primary' }"
      :confirm-loading="staffSaving"
      @confirm="submitStaffForm"
    >
      <p class="member-dialog-tip">保存后按所选成员全量覆盖该部门；负责人必须是已选成员的子集。</p>
      <div class="member-dialog-section">
        <label>部门成员</label>
        <t-select
          v-model="staffForm.staff_ids"
          multiple
          filterable
          :loading="staffLoading"
          placeholder="选择该部门成员（可多选）"
        >
          <t-option
            v-for="item in staffOptions"
            :key="item.value"
            :label="item.label"
            :value="item.value"
            :disabled="item.disabled"
          />
        </t-select>
      </div>
      <div class="member-dialog-section">
        <label>负责人</label>
        <t-select v-model="staffForm.leader_ids" multiple filterable placeholder="从已选成员中指定负责人（可多选）">
          <t-option
            v-for="item in leaderOptions"
            :key="item.value"
            :label="item.label"
            :value="item.value"
          />
        </t-select>
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
  TicketDepartmentAutoAssignMode,
  TicketDepartmentPayload,
  TicketDepartmentRecord,
} from '@/api/admin/ticket';
import { ticketsApi } from '@/api/admin/ticket';
import { adminStaffApi, type StaffRecord } from '@/api/admin-staff';
import { AdminPermissions, hasPermissionInList } from '@/constants/permissions';
import { useMediaQuery } from '@/hooks/useMediaQuery';
import { useUserStore } from '@/store';
import { formatDateTime } from '@/utils/format';
import { required } from '@/utils/formRules';
import { errorMessage } from '@/utils/userMessage';

interface DepartmentForm {
  id: number | string | null;
  name: string;
  description: string;
  auto_assign_mode: TicketDepartmentAutoAssignMode;
  is_enabled: number;
  sort_order: number;
}

interface StaffOption {
  label: string;
  value: number;
  disabled?: boolean;
}

const AUTO_ASSIGN_MODE_LABELS: Record<number, string> = {
  0: '手动分派',
  1: '轮询分派',
  2: '最少负载',
};

const autoAssignModeOptions: { label: string; value: TicketDepartmentAutoAssignMode }[] = [
  { label: '手动分派', value: 0 },
  { label: '轮询分派', value: 1 },
  { label: '最少负载', value: 2 },
];

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
const departments = ref<TicketDepartmentRecord[]>([]);
const isMobile = useMediaQuery('(max-width: 768px)');

const staffDialogVisible = ref(false);
const staffDialogDepartment = ref<TicketDepartmentRecord | null>(null);
const staffSaving = ref(false);
const staffLoading = ref(false);
const staffList = ref<StaffRecord[]>([]);
const staffForm = reactive<{ staff_ids: number[]; leader_ids: number[] }>({ staff_ids: [], leader_ids: [] });

const form = reactive<DepartmentForm>(createDefaultForm());

const rules: Record<string, FormRule[]> = {
  name: [required('请输入部门名称')],
};

const columns: PrimaryTableCol<TicketDepartmentRecord>[] = [
  { colKey: 'name', title: '部门名称', minWidth: 200 },
  { colKey: 'description', title: '描述', minWidth: 220, ellipsis: true },
  { colKey: 'autoAssignMode', title: '分派模式', width: 130 },
  { colKey: 'isEnabled', title: '状态', width: 100 },
  { colKey: 'staffCount', title: '成员数', width: 100 },
  { colKey: 'updatedAt', title: '更新时间', width: 170 },
  { colKey: 'actions', title: '操作', fixed: 'right', width: 280 },
];

const mobileActionOptions: DropdownOption[] = [
  { content: '成员设置', value: 'staff' },
  { content: '编辑', value: 'edit' },
  { content: '启停', value: 'toggle' },
  { content: '删除', value: 'delete', theme: 'error' },
];

const staffOptions = computed<StaffOption[]>(() =>
  staffList.value.map((item) => ({
    value: Number(item.id),
    label: staffOptionLabel(item),
  })),
);

const leaderOptions = computed<StaffOption[]>(() =>
  staffOptions.value.filter((item) => staffForm.staff_ids.includes(item.value)),
);

function createDefaultForm(): DepartmentForm {
  return {
    id: null,
    name: '',
    description: '',
    auto_assign_mode: 0,
    is_enabled: 1,
    sort_order: 0,
  };
}

function staffOptionLabel(item: StaffRecord) {
  const name = String(item.nickname || item.username || `员工 #${item.id}`);
  return item.email ? `${name}(${item.email})` : name;
}

function autoAssignModeLabel(value: unknown) {
  return AUTO_ASSIGN_MODE_LABELS[Number(value)] || '手动分派';
}

function autoAssignModeTheme(value: unknown): 'default' | 'primary' | 'warning' {
  const mode = Number(value);
  if (mode === 1) return 'primary';
  if (mode === 2) return 'warning';
  return 'default';
}

async function loadDepartments() {
  loading.value = true;
  try {
    departments.value = await ticketsApi.departments.list();
  } catch (error) {
    MessagePlugin.error(errorMessage(error, '加载工单部门失败'));
  } finally {
    loading.value = false;
  }
}

function openCreateDialog() {
  Object.assign(form, createDefaultForm());
  dialogVisible.value = true;
}

function openEditDialog(row: TicketDepartmentRecord) {
  Object.assign(form, {
    id: row.id,
    name: String(row.name || ''),
    description: String(row.description || ''),
    auto_assign_mode: (Number(row.auto_assign_mode || 0) as TicketDepartmentAutoAssignMode) ?? 0,
    is_enabled: Number(row.is_enabled ?? 1),
    sort_order: Number(row.sort_order || 0),
  });
  dialogVisible.value = true;
}

async function submitForm() {
  const result = await formRef.value?.validate?.();
  if (result !== true) return;

  const payload: TicketDepartmentPayload = {
    name: form.name.trim(),
    description: form.description.trim() || null,
    auto_assign_mode: Number(form.auto_assign_mode) as TicketDepartmentAutoAssignMode,
    is_enabled: Number(form.is_enabled ?? 1),
    sort_order: Number(form.sort_order || 0),
  };

  saving.value = true;
  try {
    if (form.id) {
      await ticketsApi.departments.update(form.id, payload);
      MessagePlugin.success('部门已更新');
    } else {
      await ticketsApi.departments.create(payload);
      MessagePlugin.success('部门已创建');
    }
    dialogVisible.value = false;
    await loadDepartments();
  } catch (error) {
    MessagePlugin.error(errorMessage(error, '保存部门失败'));
  } finally {
    saving.value = false;
  }
}

function handleToggle(row: TicketDepartmentRecord) {
  const enabled = Number(row.is_enabled) === 1;
  const dialog = DialogPlugin.confirm({
    header: enabled ? '停用部门' : '启用部门',
    body: enabled
      ? `确认停用部门“${row.name}”吗？停用后提单与转派将不再可选该部门。`
      : `确认启用部门“${row.name}”吗？`,
    confirmBtn: { content: '确定', theme: enabled ? 'warning' : 'primary' },
    async onConfirm() {
      try {
        await ticketsApi.departments.update(row.id, {
          name: String(row.name || ''),
          description: row.description ? String(row.description) : null,
          auto_assign_mode: Number(row.auto_assign_mode || 0) as TicketDepartmentAutoAssignMode,
          is_enabled: enabled ? 0 : 1,
          sort_order: Number(row.sort_order || 0),
        });
        MessagePlugin.success(enabled ? '部门已停用' : '部门已启用');
        dialog.destroy();
        await loadDepartments();
      } catch (error) {
        MessagePlugin.error(errorMessage(error, '更新部门状态失败'));
      }
    },
  });
}

function handleDelete(row: TicketDepartmentRecord) {
  const dialog = DialogPlugin.confirm({
    header: '删除部门',
    body: `确认删除部门“${row.name}”吗？该部门下已有工单的部门归属会被清空。`,
    confirmBtn: { content: '确定', theme: 'danger' },
    async onConfirm() {
      try {
        await ticketsApi.departments.delete(row.id);
        MessagePlugin.success('部门已删除');
        dialog.destroy();
        await loadDepartments();
      } catch (error) {
        MessagePlugin.error(errorMessage(error, '删除部门失败'));
      }
    },
  });
}

async function openStaffDialog(row: TicketDepartmentRecord) {
  staffDialogDepartment.value = row;
  staffForm.staff_ids = (row.staff || []).map((item) => Number(item.admin_user_id));
  staffForm.leader_ids = (row.staff || [])
    .filter((item) => Number(item.is_leader) === 1)
    .map((item) => Number(item.admin_user_id));
  staffDialogVisible.value = true;

  if (staffList.value.length === 0) {
    staffLoading.value = true;
    try {
      const response = await adminStaffApi.list({ page: 1, page_size: 500 });
      staffList.value = response.list || [];
    } catch (error) {
      MessagePlugin.error(errorMessage(error, '加载员工列表失败'));
    } finally {
      staffLoading.value = false;
    }
  }
}

async function submitStaffForm() {
  const department = staffDialogDepartment.value;
  if (!department) return;
  if (staffForm.staff_ids.length === 0) {
    MessagePlugin.warning('请至少选择一名部门成员');
    return;
  }

  const validLeaderIds = staffForm.leader_ids.filter((id) => staffForm.staff_ids.includes(id));
  if (validLeaderIds.length !== staffForm.leader_ids.length) {
    MessagePlugin.warning('负责人必须在已选成员内，已自动剔除失效项');
    staffForm.leader_ids = validLeaderIds;
    return;
  }

  staffSaving.value = true;
  try {
    await ticketsApi.departments.updateStaff(department.id, {
      staff_ids: staffForm.staff_ids,
      leader_ids: validLeaderIds,
    });
    MessagePlugin.success('部门成员已更新');
    staffDialogVisible.value = false;
    await loadDepartments();
  } catch (error) {
    MessagePlugin.error(errorMessage(error, '保存部门成员失败'));
  } finally {
    staffSaving.value = false;
  }
}

function handleMobileAction(row: TicketDepartmentRecord, option: DropdownOption) {
  const action = String(option.value || '');
  if (action === 'staff') {
    void openStaffDialog(row);
    return;
  }
  if (action === 'edit') {
    openEditDialog(row);
    return;
  }
  if (action === 'toggle') handleToggle(row);
  if (action === 'delete') handleDelete(row);
}

onMounted(loadDepartments);
</script>
