<template>
  <div class="tickets-page">
    <t-card :bordered="false">
      <div class="tickets-filter">
        <t-input
          v-model="filters.keyword"
          clearable
          placeholder="搜索工单标题或 ID"
          @enter="handleSearch"
          @clear="handleSearch"
        >
          <template #suffix-icon><search-icon /></template>
        </t-input>
        <t-select v-model="filters.status" clearable placeholder="工单状态" @change="handleSearch">
          <t-option v-for="option in statusOptions" :key="option.value" :label="option.label" :value="option.value" />
        </t-select>
        <t-select v-model="filters.priority" clearable placeholder="优先级" @change="handleSearch">
          <t-option v-for="option in priorityOptions" :key="option.value" :label="option.label" :value="option.value" />
        </t-select>
        <t-select v-model="filters.department" clearable placeholder="工单分类" @change="handleSearch">
          <t-option
            v-for="option in departmentOptions"
            :key="option.value"
            :label="option.label"
            :value="option.value"
          />
        </t-select>
      </div>
    </t-card>

    <t-loading :loading="loading" size="small">
      <div v-if="list.length" class="ticket-grid">
        <article v-for="item in list" :key="item.id" class="ticket-card" @click="openDetail(item.id)">
          <div class="ticket-card__head">
            <span>#{{ item.id }}</span>
            <t-tag :theme="statusTheme(item.status)" variant="light">{{ statusLabel(item.status) }}</t-tag>
          </div>
          <h2>{{ item.subject || '-' }}</h2>
          <div class="ticket-card__meta">
            <span>
              <user-icon />
              <button type="button" @click.stop="goUserDetail(item.user?.id || item.user_id)">
                {{ userName(item) }}
              </button>
            </span>
            <span>处理人：{{ assigneeName(item) }}</span>
          </div>
          <div class="ticket-card__foot">
            <t-tag :theme="priorityTheme(item.priority)" variant="light">{{ priorityLabel(item.priority) }}</t-tag>
            <t-tag theme="default" variant="light">{{ departmentLabel(item.department) }}</t-tag>
            <time>{{ formatDateTime(item.updated_at || item.created_at) }}</time>
          </div>
        </article>
      </div>
      <t-empty v-else-if="!loading" description="暂无工单记录" />
    </t-loading>

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
  </div>
</template>
<script setup lang="ts">
import './index.less';

import { SearchIcon, UserIcon } from 'tdesign-icons-vue-next';
import { MessagePlugin } from 'tdesign-vue-next';
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

const { filters, list, total, loading, pagination, handleSearch, handlePaginationChange } = useListPage<
  TicketFilter,
  TicketRecord
>({
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
