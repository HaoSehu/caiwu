<template>
  <div class="tickets-page">
    <t-card :bordered="false">
      <div class="tickets-filter">
        <t-input
          v-model="filters.keyword"
          class="tickets-filter-keyword"
          clearable
          placeholder="搜索工单标题或 ID"
          @enter="handleSearch"
          @clear="handleSearch"
        >
          <template #suffix-icon><search-icon /></template>
        </t-input>
        <t-select
          v-model="filters.status"
          class="tickets-filter-status"
          clearable
          placeholder="工单状态"
          @change="handleSearch"
        >
          <t-option v-for="option in statusOptions" :key="option.value" :label="option.label" :value="option.value" />
        </t-select>
        <t-select
          v-model="filters.priority"
          class="tickets-filter-priority"
          clearable
          placeholder="优先级"
          @change="handleSearch"
        >
          <t-option v-for="option in priorityOptions" :key="option.value" :label="option.label" :value="option.value" />
        </t-select>
        <t-select
          v-model="filters.department"
          class="tickets-filter-department"
          clearable
          placeholder="工单分类"
          @change="handleSearch"
        >
          <t-option
            v-for="option in departmentOptions"
            :key="option.value"
            :label="option.label"
            :value="option.value"
          />
        </t-select>
        <div class="tickets-filter-actions">
          <t-button theme="primary" @click="handleSearch">查询</t-button>
          <t-button theme="default" variant="base" @click="resetFilters">重置</t-button>
        </div>
      </div>
    </t-card>

    <t-card :bordered="false">
      <t-table
        row-key="id"
        :data="list"
        :columns="columns"
        :loading="loading"
        hover
        table-layout="fixed"
        aria-label="工单列表"
      >
        <template #empty>
          <t-empty :description="loading ? '加载中…' : hasActiveFilters ? '未找到匹配结果' : '暂无工单记录'" />
        </template>
        <template #subject="{ row }">
          <button class="ticket-subject" type="button" @click="openDetail(row.id)">
            {{ row.subject || '-' }}
          </button>
        </template>
        <template #user="{ row }">
          <button class="ticket-user" type="button" @click="goUserDetail(row.user?.id || row.user_id)">
            {{ userName(row) }}
          </button>
        </template>
        <template #assignee="{ row }">{{ assigneeName(row) }}</template>
        <template #status="{ row }">
          <t-tag :theme="statusTheme(row.status)" variant="light">{{ statusLabel(row.status) }}</t-tag>
        </template>
        <template #priority="{ row }">
          <t-tag :theme="priorityTheme(row.priority)" variant="light">{{ priorityLabel(row.priority) }}</t-tag>
        </template>
        <template #department="{ row }">{{ departmentLabel(row.department) }}</template>
        <template #updatedAt="{ row }">{{ formatDateTime(row.updated_at || row.created_at) }}</template>
        <template #operation="{ row }">
          <t-button size="small" variant="text" theme="primary" @click="openDetail(row.id)">查看</t-button>
        </template>
      </t-table>

      <div v-if="total > 0" class="ticket-pagination">
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
  </div>
</template>
<script setup lang="ts">
import './index.less';

import { SearchIcon } from 'tdesign-icons-vue-next';
import type { PrimaryTableCol } from 'tdesign-vue-next';
import { MessagePlugin } from 'tdesign-vue-next/es/message/plugin';
import { useRouter } from 'vue-router';

import type { TicketRecord } from '@/api/admin';
import { adminApi } from '@/api/admin';
import { useListPage } from '@/hooks/useListPage';
import { formatDateTime } from '@/utils/format';
import { errorMessage } from '@/utils/userMessage';

const DEFAULT_STATUS_FILTER = 'ongoing';

const router = useRouter();

interface TicketFilter {
  keyword: string;
  status: string | number;
  priority: string | number;
  department: string;
}

const {
  filters,
  list,
  total,
  loading,
  hasActiveFilters,
  pagination,
  handleSearch,
  resetFilters,
  handlePaginationChange,
} = useListPage<TicketFilter, TicketRecord>({
  defaultFilters: {
    keyword: '',
    status: DEFAULT_STATUS_FILTER,
    priority: '',
    department: '',
  },
  defaultPageSize: 20,
  onError: (error) => MessagePlugin.error(errorMessage(error, '加载工单列表失败')),
  fetch: (params) => adminApi.tickets.list(params),
});

const columns: PrimaryTableCol<TicketRecord>[] = [
  { title: 'ID', colKey: 'id', width: 70 },
  { title: '标题', colKey: 'subject', minWidth: 200 },
  { title: '用户', colKey: 'user', width: 170 },
  { title: '处理人', colKey: 'assignee', width: 130 },
  { title: '状态', colKey: 'status', width: 100 },
  { title: '优先级', colKey: 'priority', width: 90 },
  { title: '分类', colKey: 'department', width: 96 },
  { title: '最后更新', colKey: 'updatedAt', width: 170 },
  { title: '操作', colKey: 'operation', width: 80 },
];

const statusOptions = [
  { label: '进行中', value: DEFAULT_STATUS_FILTER },
  { label: '开启', value: 0 },
  { label: '客户回复', value: 1 },
  { label: '员工回复', value: 2 },
  { label: '已关闭', value: 3 },
];
const priorityOptions = [
  { label: '低', value: 1 },
  { label: '中', value: 2 },
  { label: '高', value: 3 },
  { label: '紧急', value: 4 },
];
const departmentOptions = [
  { label: '销售', value: 'sales' },
  { label: '技术支持', value: 'support' },
  { label: '财务', value: 'billing' },
  { label: '投诉', value: 'abuse' },
];

function openDetail(id: number | string) {
  router.push(`/admin/ticket-conversations/${id}`);
}

function goUserDetail(userId: unknown) {
  if (!userId) return;
  router.push(`/admin/users/${userId}`);
}

function userName(item: TicketRecord) {
  const user = item.user || {};
  return String(user.nickname || user.display_name || user.email || `用户 #${item.user_id || '-'}`);
}

function assigneeName(item: TicketRecord) {
  const assignee = item.assignee || {};
  return String(assignee.nickname || assignee.username || '未指派');
}

function departmentLabel(value: unknown) {
  return departmentOptions.find((item) => item.value === value)?.label || String(value || '--');
}

function priorityLabel(value: unknown) {
  return priorityOptions.find((item) => item.value === Number(value))?.label || '--';
}

function priorityTheme(value: unknown): 'default' | 'success' | 'warning' | 'danger' {
  const number = Number(value);
  if (number === 2) return 'success';
  if (number === 3) return 'warning';
  if (number === 4) return 'danger';
  return 'default';
}

function statusLabel(value: unknown) {
  return (
    (
      {
        0: '开启',
        1: '客户回复',
        2: '员工回复',
        3: '已关闭',
      } as Record<number, string>
    )[Number(value)] || '--'
  );
}

function statusTheme(value: unknown): 'default' | 'success' | 'warning' | 'danger' {
  const number = Number(value);
  if (number === 0) return 'warning';
  if (number === 1) return 'danger';
  if (number === 2) return 'success';
  return 'default';
}
</script>
