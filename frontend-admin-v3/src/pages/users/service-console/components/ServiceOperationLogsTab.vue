<template>
  <section class="console-tab-section">
    <div class="console-toolbar">
      <t-select v-model="state.filters.category" clearable placeholder="操作分类" @change="search">
        <t-option v-for="option in categoryOptions" :key="option.value" :label="option.label" :value="option.value" />
      </t-select>
      <t-input
        v-model="state.filters.keyword"
        clearable
        placeholder="搜索操作说明"
        class="console-toolbar__search"
        @enter="search"
        @clear="search"
      />
      <t-button variant="outline" @click="search">查询</t-button>
    </div>
    <div class="console-table-scroll">
      <t-table
        row-key="id"
        :data="state.list"
        :columns="columns"
        :loading="state.loading"
        :pagination="pagination"
        table-layout="fixed"
        @page-change="handlePageChange"
      >
        <template #empty>
          <t-empty :description="state.loading ? '加载中…' : hasActiveFilters ? '未找到匹配结果' : '暂无操作日志'" />
        </template>
        <template #logTime="{ row }">{{ formatDateTime(row.created_at) }}</template>
        <template #logCategory="{ row }">
          <t-tag v-if="row.category_label" theme="default" variant="light">{{ row.category_label }}</t-tag>
          <span v-else>-</span>
        </template>
        <template #logActor="{ row }">
          <div class="console-actor">
            <span>{{ fieldValue(row.actor_name || row.actor_label) }}</span>
            <span v-if="row.actor_type === 'admin'" class="console-actor__badge">管理员</span>
          </div>
        </template>
        <template #logOperation="{ row }">
          <t-button variant="text" theme="primary" size="small" @click="openDetail(row)">详情</t-button>
        </template>
      </t-table>
    </div>

    <t-dialog v-model:visible="detailVisible" header="操作详情" width="560px" :footer="false">
      <div class="console-log-detail">
        <div class="console-log-detail__summary">{{ detailRow.summary || '-' }}</div>
        <div v-if="detailItems.length" class="console-log-detail__items">
          <div v-for="item in detailItems" :key="item.label" class="console-log-detail__item">
            <span>{{ item.label }}</span>
            <strong>{{ item.value }}</strong>
          </div>
        </div>
        <t-empty v-else description="暂无更多详情" />
      </div>
    </t-dialog>
  </section>
</template>
<script setup lang="ts">
import type { PageInfo, PrimaryTableCol, TableRowData } from 'tdesign-vue-next';
import { MessagePlugin } from 'tdesign-vue-next/es/message/plugin';
import { computed, onMounted, reactive, ref } from 'vue';

import type { PageParams } from '@/api/user';
import { userApi } from '@/api/user';
import { fieldValue, formatDateTime } from '@/utils/format';
import { errorMessage } from '@/utils/userMessage';

type Row = Record<string, any>;

interface TabPageState {
  loading: boolean;
  list: Row[];
  total: number;
  page: number;
  pageSize: number;
  filters: Record<string, string>;
}

const props = defineProps<{ userId: number | string; serviceId: number | string }>();

/** 操作分类与后端 ServiceTransformService::OPERATION_CATEGORY_LABELS 一一对应。 */
const categoryOptions = [
  { label: '电源管理', value: 'power' },
  { label: '密码重置', value: 'password' },
  { label: '重装系统', value: 'reinstall' },
  { label: '续费管理', value: 'renew' },
  { label: '规格升级', value: 'upgrade' },
  { label: '端口转发', value: 'nat_forwarding' },
  { label: '安全组', value: 'security_group' },
  { label: '安全组规则', value: 'security_rule' },
  { label: 'VNC远程', value: 'vnc' },
  { label: '实例变更', value: 'service' },
];

const state = reactive<TabPageState>({
  loading: false,
  list: [],
  total: 0,
  page: 1,
  pageSize: 10,
  filters: { category: '', keyword: '' },
});

// 空态文案区分「未筛选但无数据」与「筛选后无匹配」（docs/DESIGN.md 术语表）
const hasActiveFilters = computed(() => Boolean(state.filters.category) || Boolean(state.filters.keyword));

const columns: PrimaryTableCol<TableRowData>[] = [
  { title: '时间', colKey: 'logTime', width: 180 },
  { title: '操作', colKey: 'action_label', width: 180 },
  { title: '分类', colKey: 'logCategory', width: 120 },
  { title: '说明', colKey: 'summary', minWidth: 260 },
  { title: '操作人', colKey: 'logActor', width: 180 },
  { title: 'IP', colKey: 'ip_address', width: 150 },
  { title: '操作', colKey: 'logOperation', width: 90 },
];

const pagination = computed(() => ({
  current: state.page,
  pageSize: state.pageSize,
  total: state.total,
  showJumper: true,
}));

/** 列表请求序号，用于丢弃过期响应 */
let requestSeq = 0;

const detailVisible = ref(false);
const detailRow = ref<Row>({});
const detailItems = computed<Array<{ label: string; value: string }>>(() => {
  const items = detailRow.value?.detail_items;
  return Array.isArray(items) ? items : [];
});

async function load() {
  const currentSeq = ++requestSeq;
  state.loading = true;
  try {
    const params: PageParams = {
      category: state.filters.category,
      keyword: state.filters.keyword.trim(),
      page: state.page,
      page_size: state.pageSize,
    };
    const response = await userApi.serviceOperationLogs(props.userId, props.serviceId, params);
    if (currentSeq !== requestSeq) return;
    state.list = Array.isArray(response.list) ? response.list : [];
    state.total = Number(response.total || 0);
    state.page = Number(response.page || state.page);
    state.pageSize = Number(response.page_size || state.pageSize);
  } catch (error) {
    if (currentSeq !== requestSeq) return;
    MessagePlugin.error(errorMessage(error, '加载操作日志失败'));
  } finally {
    if (currentSeq === requestSeq) state.loading = false;
  }
}

function search() {
  state.page = 1;
  load();
}

function handlePageChange(pageInfo: PageInfo) {
  state.page = pageInfo.current;
  state.pageSize = pageInfo.pageSize;
  load();
}

function openDetail(row: TableRowData) {
  detailRow.value = { ...row };
  detailVisible.value = true;
}

defineExpose({ reload: load });

onMounted(load);
</script>
