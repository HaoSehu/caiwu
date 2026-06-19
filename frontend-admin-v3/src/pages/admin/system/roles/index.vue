<template>
  <div class="admin-roles-page">
    <t-card class="roles-card" :bordered="false">
      <div class="roles-filter">
        <t-input
          v-model="keyword"
          clearable
          placeholder="搜索角色编码/名称"
          @enter="loadRoles"
          @clear="loadRoles"
        >
          <template #suffix-icon><search-icon /></template>
        </t-input>
        <t-button theme="primary" @click="loadRoles">
          <template #icon><search-icon /></template>
          搜索
        </t-button>
        <t-button variant="base" @click="resetSearch">
          <template #icon><refresh-icon /></template>
          重置
        </t-button>
        <t-button v-if="canManage" theme="primary" @click="openCreateDialog">
          <template #icon><add-icon /></template>
          新增角色
        </t-button>
      </div>

      <t-table
        row-key="id"
        :data="roles"
        :columns="columns"
        :loading="loading"
        hover
        table-layout="fixed"
      >
        <template #role="{ row }">
          <div class="role-cell">
            <strong>{{ row.label || row.name || '-' }}</strong>
            <span>{{ row.name || '-' }}</span>
          </div>
        </template>
        <template #permissions="{ row }">
          <div class="permission-summary">
            <t-tag variant="light">{{ permissionSummary(row) }}</t-tag>
            <t-tag v-if="riskPermissionCount(row) > 0" theme="danger" variant="light">
              高危 {{ riskPermissionCount(row) }}
            </t-tag>
          </div>
        </template>
        <template #adminCount="{ row }">{{ Number(row.admin_count || 0) }} 人</template>
        <template #updatedAt="{ row }">{{ row.updated_at || '-' }}</template>
        <template #actions="{ row }">
          <t-space size="small">
            <t-button theme="primary" variant="text" @click="openEditDialog(row)">{{ canManage ? '权限' : '查看' }}</t-button>
            <t-button v-if="canManage" theme="default" variant="text" @click="handleCopy(row)">复制</t-button>
            <t-button v-if="canManage" theme="danger" variant="text" @click="handleDelete(row)">删除</t-button>
          </t-space>
        </template>
      </t-table>
    </t-card>

    <t-dialog
      v-model:visible="dialogVisible"
      :header="form.id ? '编辑角色权限' : '新增角色'"
      width="960px"
      :confirm-btn="dialogConfirmBtn"
      :confirm-loading="saving"
      @confirm="submitForm"
    >
      <t-form ref="formRef" :data="form" :rules="rules" label-align="top">
        <div class="role-form-grid">
          <t-form-item label="角色编码" name="name">
            <t-input v-model="form.name" :disabled="!canManage || isReadonlySuperRole" placeholder="例如：operator" />
          </t-form-item>
          <t-form-item label="角色名称" name="label">
            <t-input v-model="form.label" :disabled="!canManage" placeholder="例如：运营人员" />
          </t-form-item>
        </div>

        <section class="permission-panel">
          <div class="permission-panel__head">
            <div class="permission-panel__title">
              <strong>权限目录</strong>
              <span>已选 {{ selectedPermissionCount }} / {{ permissions.length }}</span>
            </div>
            <t-space size="small">
              <t-button v-if="canManage" variant="text" theme="primary" @click="selectNoDangerousDefaults">常用权限</t-button>
              <t-button v-if="canManage" variant="text" @click="clearAllPermissions">清空全部</t-button>
            </t-space>
          </div>

          <div class="permission-toolbar">
            <t-input v-model="permissionKeyword" clearable placeholder="搜索权限名称/权限码/模块" />
            <t-checkbox v-model="showDangerousOnly">只看高危</t-checkbox>
            <t-space v-if="canManage" size="small">
              <t-button size="small" variant="base" @click="selectVisiblePermissions">选中当前结果</t-button>
              <t-button size="small" variant="base" @click="clearVisiblePermissions">清空当前结果</t-button>
            </t-space>
          </div>

          <t-checkbox-group v-model="form.permissions" class="permission-groups">
            <div v-for="group in permissionGroups" :key="group.key" class="permission-group">
              <div class="permission-group__head">
                <div>
                  <h4>{{ group.label }}</h4>
                  <span>{{ group.parentLabel }} · {{ group.selectedCount }} / {{ group.items.length }}</span>
                </div>
                <t-space v-if="canManage" size="small">
                  <t-button size="small" variant="text" theme="primary" @click="selectGroupPermissions(group)">全选</t-button>
                  <t-button size="small" variant="text" @click="clearGroupPermissions(group)">清空</t-button>
                </t-space>
              </div>
              <div class="permission-grid">
                <t-checkbox
                  v-for="item in group.items"
                  :key="item.key"
                  :value="item.key"
                  :disabled="!canManage || (hasAllPermissionSelected && item.key !== AdminPermissions.ALL)"
                >
                  <span class="permission-name">
                    {{ item.name || item.key }}
                    <t-tag v-if="item.action_label" variant="light" size="small">{{ item.action_label }}</t-tag>
                    <t-tag v-if="item.is_dangerous" theme="danger" variant="light" size="small">高危</t-tag>
                    <t-tag v-else-if="item.risk_level === 'medium'" theme="warning" variant="light" size="small">敏感</t-tag>
                  </span>
                  <em>{{ item.key }}</em>
                </t-checkbox>
              </div>
            </div>
            <div v-if="permissionGroups.length === 0" class="permission-empty">暂无匹配权限</div>
          </t-checkbox-group>
        </section>
      </t-form>
    </t-dialog>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { AddIcon, RefreshIcon, SearchIcon } from 'tdesign-icons-vue-next';
import { DialogPlugin, MessagePlugin } from 'tdesign-vue-next';
import type { FormInstanceFunctions, FormRule, PrimaryTableCol, TableRowData } from 'tdesign-vue-next';

import { adminRoleApi, type PermissionItem, type RolePayload, type RoleRecord } from '@/api/admin-roles';
import { errorMessage } from '@/utils/userMessage';
import { required } from '@/utils/formRules';
import { AdminPermissions } from '@/constants/permissions';
import { useUserStore } from '@/store';

import './index.less';

defineOptions({
  name: 'AdminRoles',
});

interface RoleForm {
  id: number | string | null;
  name: string;
  label: string;
  permissions: string[];
}

interface PermissionGroup {
  key: string;
  label: string;
  parentLabel: string;
  items: PermissionItem[];
  selectedCount: number;
}

const userStore = useUserStore();
const loading = ref(false);
const saving = ref(false);
const dialogVisible = ref(false);
const formRef = ref<FormInstanceFunctions>();
const keyword = ref('');
const permissionKeyword = ref('');
const showDangerousOnly = ref(false);
const roles = ref<RoleRecord[]>([]);
const permissions = ref<PermissionItem[]>([]);
const form = reactive<RoleForm>(createDefaultForm());

const canManage = computed(() => hasPermission(AdminPermissions.ROLE_MANAGE));
const hasAllPermissionSelected = computed(() => form.permissions.includes(AdminPermissions.ALL));
const isReadonlySuperRole = computed(() => hasAllPermissionSelected.value);
const selectedPermissionCount = computed(() => (hasAllPermissionSelected.value ? permissions.value.length : form.permissions.length));
const dialogConfirmBtn = computed(() => (canManage.value ? { content: '保存', theme: 'primary' } : null));
const permissionMap = computed(() => new Map(permissions.value.map((item) => [item.key, item])));
const filteredPermissions = computed(() => {
  const keywordValue = permissionKeyword.value.trim().toLowerCase();

  return permissions.value.filter((item) => {
    if (showDangerousOnly.value && !item.is_dangerous) return false;
    if (!keywordValue) return true;

    return [item.key, item.name, item.module, item.module_label, item.group, item.group_label, item.action_label]
      .filter(Boolean)
      .some((value) => String(value).toLowerCase().includes(keywordValue));
  });
});
const permissionGroups = computed<PermissionGroup[]>(() => {
  const grouped = new Map<string, PermissionItem[]>();
  filteredPermissions.value.forEach((item) => {
    const group = item.group || item.module || 'system';
    grouped.set(group, [...(grouped.get(group) || []), item]);
  });

  return Array.from(grouped.entries()).map(([key, items]) => ({
    key,
    label: items[0]?.group_label || items[0]?.module_label || groupFallbackLabel(key),
    parentLabel: items[0]?.module_label || '系统',
    items,
    selectedCount: items.filter((item) => hasPermissionSelected(item.key)).length,
  }));
});

const rules: Record<string, FormRule[]> = {
  name: [required('请输入角色编码')],
  label: [required('请输入角色名称')],
};
const columns: PrimaryTableCol<TableRowData>[] = [
  { title: 'ID', colKey: 'id', width: 80 },
  { title: '角色', colKey: 'role', minWidth: 220 },
  { title: '权限', colKey: 'permissions', width: 180 },
  { title: '员工数', colKey: 'adminCount', width: 110 },
  { title: '更新时间', colKey: 'updatedAt', width: 180 },
  { title: '操作', colKey: 'actions', fixed: 'right', width: 180 },
];

watch(
  () => [...form.permissions],
  (values) => {
    if (values.includes(AdminPermissions.ALL) && values.length > 1) {
      form.permissions = [AdminPermissions.ALL];
    }
  },
);

onMounted(async () => {
  await Promise.all([loadPermissions(), loadRoles()]);
});

function createDefaultForm(): RoleForm {
  return {
    id: null,
    name: '',
    label: '',
    permissions: [],
  };
}

async function loadPermissions() {
  try {
    const response = await adminRoleApi.permissions();
    permissions.value = response.list || [];
  } catch (error) {
    MessagePlugin.error(errorMessage(error, '加载权限目录失败'));
  }
}

async function loadRoles() {
  loading.value = true;
  try {
    const response = await adminRoleApi.list({ keyword: keyword.value });
    roles.value = response.list || [];
  } catch (error) {
    roles.value = [];
    MessagePlugin.error(errorMessage(error, '加载角色列表失败'));
  } finally {
    loading.value = false;
  }
}

function resetSearch() {
  keyword.value = '';
  loadRoles();
}

function openCreateDialog() {
  permissionKeyword.value = '';
  showDangerousOnly.value = false;
  Object.assign(form, createDefaultForm());
  dialogVisible.value = true;
}

function openEditDialog(row: RoleRecord) {
  const selectedPermissions = row.stored_permissions?.length ? row.stored_permissions : row.permissions || [];

  permissionKeyword.value = '';
  showDangerousOnly.value = false;
  Object.assign(form, {
    id: row.id,
    name: String(row.name || ''),
    label: String(row.label || row.name || ''),
    permissions: normalizePermissionSelection(selectedPermissions),
  });
  dialogVisible.value = true;
}

async function submitForm() {
  if (!canManage.value) {
    MessagePlugin.warning('当前账号无角色管理权限');
    return;
  }

  const result = await formRef.value?.validate?.();
  if (result !== true) return;

  const payload = buildPayload();
  if (payload.permissions.includes(AdminPermissions.ALL) && !(await confirmAllPermission())) {
    return;
  }

  saving.value = true;
  try {
    if (form.id) {
      await adminRoleApi.update(form.id, payload);
      MessagePlugin.success('角色已更新');
    } else {
      await adminRoleApi.create(payload);
      MessagePlugin.success('角色已创建');
    }
    dialogVisible.value = false;
    await loadRoles();
  } catch (error) {
    MessagePlugin.error(errorMessage(error, '保存角色失败'));
  } finally {
    saving.value = false;
  }
}

function handleCopy(row: RoleRecord) {
  const dialog = DialogPlugin.confirm({
    header: '复制角色',
    body: `确认复制角色「${row.label || row.name || row.id}」？`,
    confirmBtn: { content: '复制', theme: 'primary' },
    cancelBtn: '取消',
    onConfirm: async () => {
      try {
        await adminRoleApi.copy(row.id);
        MessagePlugin.success('角色已复制');
        await loadRoles();
        dialog.destroy();
      } catch (error) {
        MessagePlugin.error(errorMessage(error, '复制角色失败'));
      }
    },
  });
}

function handleDelete(row: RoleRecord) {
  const dialog = DialogPlugin.confirm({
    header: '删除角色',
    body: `确认删除角色「${row.label || row.name || row.id}」？已分配员工的角色无法删除。`,
    confirmBtn: { content: '删除', theme: 'danger' },
    cancelBtn: '取消',
    onConfirm: async () => {
      try {
        await adminRoleApi.delete(row.id);
        MessagePlugin.success('角色已删除');
        await loadRoles();
        dialog.destroy();
      } catch (error) {
        MessagePlugin.error(errorMessage(error, '删除角色失败'));
      }
    },
  });
}

function selectNoDangerousDefaults() {
  form.permissions = [
    AdminPermissions.DASHBOARD_VIEW,
    AdminPermissions.USER_LIST,
    AdminPermissions.USER_DETAIL,
    AdminPermissions.TICKET_LIST,
    AdminPermissions.TICKET_REPLY,
    AdminPermissions.PRODUCT_LIST,
    AdminPermissions.CONTENT_LIST,
  ];
}

function selectGroupPermissions(group: PermissionGroup) {
  mergePermissions(group.items.map((item) => item.key));
}

function clearGroupPermissions(group: PermissionGroup) {
  clearPermissions(group.items.map((item) => item.key));
}

function selectVisiblePermissions() {
  mergePermissions(filteredPermissions.value.map((item) => item.key));
}

function clearVisiblePermissions() {
  clearPermissions(filteredPermissions.value.map((item) => item.key));
}

function clearAllPermissions() {
  form.permissions = [];
}

function mergePermissions(keys: string[]) {
  if (!canManage.value) return;
  form.permissions = normalizePermissionSelection([...form.permissions, ...keys]);
}

function clearPermissions(keys: string[]) {
  if (!canManage.value) return;
  const keySet = new Set(keys);
  form.permissions = form.permissions.filter((key) => !keySet.has(key));
}

function buildPayload(): RolePayload {
  return {
    name: form.name.trim(),
    label: form.label.trim(),
    permissions: normalizePermissionSelection(form.permissions),
  };
}

function normalizePermissionSelection(values: string[]) {
  const normalized = Array.from(new Set(values.filter(Boolean)));
  if (normalized.includes(AdminPermissions.ALL)) return [AdminPermissions.ALL];
  return normalized;
}

function permissionSummary(row: RoleRecord) {
  const resolved = row.permissions || [];
  if (resolved.includes(AdminPermissions.ALL)) return '全部权限';
  return `${resolved.length} 项权限`;
}

function riskPermissionCount(row: RoleRecord) {
  const resolved = row.permissions || [];
  if (resolved.includes(AdminPermissions.ALL)) return 1;

  return resolved.filter((permission) => permissionMap.value.get(permission)?.is_dangerous).length;
}

function hasPermissionSelected(permission: string) {
  return hasAllPermissionSelected.value || form.permissions.includes(permission);
}

function groupFallbackLabel(group: string) {
  return {
    system_root: '超级权限',
    organization_staff: '员工账号',
    organization_role: '角色授权',
    organization_permission: '权限目录',
    dashboard_workbench: '工作台',
    customer_profile: '客户资料',
    customer_verification: '实名审核',
    finance_order: '订单管理',
    finance_invoice: '账单管理',
    finance_funds: '资金操作',
    finance_report: '财务报表',
    support_ticket: '工单支持',
    product_catalog: '商品配置',
    content_ops: '内容运营',
    marketing_growth: '推广会员',
    system_config: '系统设置',
    system_audit: '日志审计',
  }[group] || group;
}

function confirmAllPermission() {
  return new Promise<boolean>((resolve) => {
    let settled = false;
    const settle = (value: boolean) => {
      if (settled) return;
      settled = true;
      resolve(value);
    };
    const dialog = DialogPlugin.confirm({
      header: '确认授予全部权限',
      body: '全部权限将允许该角色访问并操作后台所有功能。',
      confirmBtn: { content: '确认授予', theme: 'danger' },
      cancelBtn: '取消',
      onConfirm: () => {
        settle(true);
        dialog.destroy();
      },
      onCancel: () => settle(false),
      onClose: () => settle(false),
    });
  });
}

function hasPermission(permission: string) {
  const userPermissions = userStore.userInfo?.permissions || [];
  return userPermissions.includes(AdminPermissions.ALL) || userPermissions.includes(permission);
}

</script>
