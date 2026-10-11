<template>
  <div class="content-page">
    <t-card :bordered="false">
      <div class="content-filter">
        <t-input
          v-model="keyword"
          clearable
          placeholder="搜索标签名称"
          @enter="handleSearch"
          @clear="handleSearch"
        >
          <template #suffix-icon><search-icon /></template>
        </t-input>
        <div class="content-filter-actions">
          <t-button theme="primary" @click="handleSearch">查询</t-button>
          <t-button theme="default" variant="base" @click="resetFilters">重置</t-button>
          <t-button v-if="canManage" theme="primary" @click="openCreateDialog">
            <template #icon><add-icon /></template>
            新建标签
          </t-button>
        </div>
      </div>
    </t-card>

    <t-card :bordered="false" :loading="loading">
      <template #title>知识库标签</template>
      <template #subtitle>共 {{ total }} 条</template>

      <t-table row-key="id" :data="tags" :columns="columns" hover table-layout="fixed">
        <template #name="{ row }">
          <t-tag theme="primary" variant="light">{{ row.name }}</t-tag>
        </template>
        <template #usage_count="{ row }">
          <span>{{ Number(row.usage_count) || 0 }} 篇文章</span>
        </template>
        <template #created_at="{ row }">
          <span>{{ formatDateTime(row.created_at) }}</span>
        </template>
        <template #actions="{ row }">
          <t-space size="small">
            <t-button v-if="canManage" theme="primary" variant="text" @click="openEditDialog(row)">编辑</t-button>
            <t-button v-if="canManage" theme="danger" variant="text" @click="handleDelete(row)">删除</t-button>
          </t-space>
        </template>
      </t-table>

      <div v-if="total > 0" class="pagination-row">
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

    <t-dialog
      v-model:visible="dialogVisible"
      :header="form.id ? '编辑标签' : '新建标签'"
      width="480px"
      :confirm-btn="{ content: form.id ? '保存' : '创建', theme: 'primary', loading: saving }"
      @confirm="submit"
    >
      <t-form ref="formRef" :data="form" :rules="rules" label-align="top">
        <t-form-item label="标签名称" name="name">
          <t-input v-model="form.name" placeholder="请输入标签名称" :maxlength="50" />
        </t-form-item>
        <t-form-item label="排序值" name="sort_order">
          <t-input-number v-model="form.sort_order" :min="0" :max="999999" theme="normal" style="width: 100%" />
        </t-form-item>
        <p v-if="form.id" class="muted-text">提示：标签仍被文章绑定时无法删除，请先在文章编辑页解绑。</p>
      </t-form>
    </t-dialog>
  </div>
</template>
<script setup lang="ts">
import '../index.less';

import { AddIcon, SearchIcon } from 'tdesign-icons-vue-next';
import type { FormInstanceFunctions, FormRule, PrimaryTableCol } from 'tdesign-vue-next';
import { DialogPlugin, MessagePlugin } from 'tdesign-vue-next';
import { computed, onMounted, reactive, ref } from 'vue';

import type { ContentTagRecord } from '@/api/admin/content';
import { adminApi } from '@/api/admin';
import { AdminPermissions, hasPermissionInList } from '@/constants/permissions';
import { useUserStore } from '@/store';
import { formatDateTime } from '@/utils/format';
import { required } from '@/utils/formRules';
import { errorMessage } from '@/utils/userMessage';

const loading = ref(false);
const saving = ref(false);
const dialogVisible = ref(false);
const keyword = ref('');
const tags = ref<ContentTagRecord[]>([]);
const total = ref(0);
const pagination = reactive({ page: 1, page_size: 20 });
const formRef = ref<FormInstanceFunctions>();

const form = reactive({
  id: null as number | null,
  name: '',
  sort_order: 0,
});

const rules: Record<string, FormRule[]> = {
  name: [required('请输入标签名称')],
};

const columns: PrimaryTableCol<ContentTagRecord>[] = [
  { colKey: 'id', title: 'ID', width: 80 },
  { colKey: 'name', title: '标签名称', minWidth: 200 },
  { colKey: 'usage_count', title: '关联文章数', minWidth: 140 },
  { colKey: 'sort_order', title: '排序', width: 100 },
  { colKey: 'created_at', title: '创建时间', minWidth: 180 },
  { colKey: 'actions', title: '操作', fixed: 'right', width: 130 },
];

async function loadList() {
  loading.value = true;
  try {
    const res = await adminApi.content.tags.list({
      keyword: keyword.value.trim() || undefined,
      page: pagination.page,
      page_size: pagination.page_size,
    });
    tags.value = res.list || [];
    total.value = Number(res.total || 0);
  } catch (error) {
    MessagePlugin.error(errorMessage(error, '加载标签列表失败'));
  } finally {
    loading.value = false;
  }
}

function handleSearch() {
  pagination.page = 1;
  loadList();
}

function resetFilters() {
  keyword.value = '';
  handleSearch();
}

function handlePaginationChange(pageInfo: { current?: number; pageSize?: number }) {
  if (pageInfo.current) pagination.page = pageInfo.current;
  if (pageInfo.pageSize) pagination.page_size = pageInfo.pageSize;
  loadList();
}

function resetForm() {
  form.id = null;
  form.name = '';
  form.sort_order = 0;
  formRef.value?.clearValidate?.();
}

function openCreateDialog() {
  resetForm();
  dialogVisible.value = true;
}

function openEditDialog(row: ContentTagRecord) {
  form.id = Number(row.id);
  form.name = String(row.name || '');
  form.sort_order = Number(row.sort_order || 0);
  formRef.value?.clearValidate?.();
  dialogVisible.value = true;
}

async function submit() {
  const result = await formRef.value?.validate?.();
  if (result !== true) return;

  const payload = {
    name: form.name.trim(),
    sort_order: Number(form.sort_order || 0),
  };

  saving.value = true;
  try {
    if (form.id) {
      await adminApi.content.tags.update(form.id, payload);
      MessagePlugin.success('标签已更新');
    } else {
      await adminApi.content.tags.create(payload);
      MessagePlugin.success('标签已创建');
    }
    dialogVisible.value = false;
    await loadList();
  } catch (error) {
    // 后端 422（如重名）等业务错误消息直接透出
    MessagePlugin.error(errorMessage(error, '保存标签失败'));
  } finally {
    saving.value = false;
  }
}

function handleDelete(row: ContentTagRecord) {
  const dialog = DialogPlugin.confirm({
    header: '删除标签',
    body: `确认删除标签"${row.name}"吗？`,
    confirmBtn: { content: '确定', theme: 'danger' },
    async onConfirm() {
      try {
        await adminApi.content.tags.delete(row.id);
        MessagePlugin.success('标签已删除');
        dialog.destroy();
        await loadList();
      } catch (error) {
        // 仍被文章绑定时后端返回 422（提示先解绑），错误消息直接透出
        MessagePlugin.error(errorMessage(error, '删除标签失败'));
      }
    },
  });
}

// 写操作需要 content.manage（页面路由仅 content.list；list-only 角色只读）
const userStore = useUserStore();
const canManage = computed(() =>
  hasPermissionInList(
    (userStore.userInfo as { permissions?: string[] }).permissions,
    AdminPermissions.CONTENT_MANAGE,
  ),
);

onMounted(() => {
  loadList();
});
</script>
