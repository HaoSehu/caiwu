<template>
  <t-drawer :visible="visible" :size="drawerSize" :header="headerTitle" :footer="false" @close="emit('close')">
    <div class="queue-jobs-toolbar">
      <span class="queue-jobs-toolbar__summary">
        {{ summaryText }}
        <t-tag v-if="canManage" size="small" variant="light" theme="primary">可管理</t-tag>
        <t-tag v-else size="small" variant="light">只读</t-tag>
      </span>
      <t-button
        v-if="canManage"
        theme="danger"
        variant="outline"
        size="small"
        :disabled="total === 0"
        @click="emit('clear')"
      >
        清空全部
      </t-button>
    </div>

    <div class="table-scroll">
      <t-table row-key="id" :data="rows" :columns="columns" :loading="loading" hover table-layout="fixed">
        <template #job="{ row }">
          <div class="stack-cell">
            <strong>{{ fieldValue(row.display_name) }}</strong>
            <span
              >{{ fieldValue(row.queue) }}<template v-if="row.uuid"> · {{ row.uuid }}</template></span
            >
          </div>
        </template>
        <template #state="{ row }">
          <t-tag :theme="stateTheme(row.state)" variant="light">{{ stateLabel(row.state) }}</t-tag>
        </template>
        <template #exception="{ row }">
          <span class="queue-exception" :title="fieldValue(row.exception)">{{ fieldValue(row.exception) }}</span>
        </template>
        <template #actions="{ row }">
          <t-popconfirm
            v-if="canManage"
            content="删除后将无法恢复，确认继续？"
            :confirm-btn="{ theme: 'danger', content: '删除' }"
            @confirm="removeJob(row)"
          >
            <t-button theme="danger" variant="text" size="small" :loading="deletingId === row.id">删除</t-button>
          </t-popconfirm>
          <span v-else class="muted-text">只读</span>
        </template>
      </t-table>
    </div>

    <div v-if="total > 0" class="queue-jobs-pagination">
      <t-pagination
        :current="page"
        :page-size="pageSize"
        :total="total"
        :page-size-options="[20, 50, 100]"
        show-jumper
        @change="handlePageChange"
        @page-size-change="handlePageSizeChange"
      />
    </div>
    <t-empty v-else-if="!loading" description="队列为空" />
  </t-drawer>
</template>
<script setup lang="ts">
import type { PrimaryTableCol } from 'tdesign-vue-next';
import { MessagePlugin } from 'tdesign-vue-next';
import { computed, ref, watch } from 'vue';

import { adminApi } from '@/api/admin';
import { fieldValue } from '@/utils/format';
import { errorMessage } from '@/utils/userMessage';

type QueueKind = 'pending' | 'failed';
type RecordRow = Record<string, unknown>;

defineOptions({ name: 'QueueJobsDrawer' });

const props = defineProps<{
  visible: boolean;
  queue: QueueKind;
  canManage: boolean;
  drawerSize: string;
  /** 父级完成清空等操作后递增，触发抽屉重新加载 */
  refreshToken?: number;
}>();

const emit = defineEmits<{
  (e: 'close'): void;
  (e: 'changed'): void;
  (e: 'clear'): void;
}>();

const loading = ref(false);
const rows = ref<RecordRow[]>([]);
const total = ref(0);
const page = ref(1);
const pageSize = ref(20);
const deletingId = ref(0);

const headerTitle = computed(() => (props.queue === 'pending' ? '待处理队列任务' : '失败队列任务'));
const summaryText = computed(() =>
  props.queue === 'pending' ? `jobs 表共 ${total.value} 条待处理任务` : `failed_jobs 表累计 ${total.value} 条失败记录`,
);
const columns = computed<PrimaryTableCol<RecordRow>[]>(() => {
  if (props.queue === 'pending') {
    return [
      { colKey: 'id', title: 'ID', width: 76 },
      { colKey: 'job', title: '任务 / 队列', minWidth: 260 },
      { colKey: 'state', title: '状态', width: 100 },
      { colKey: 'attempts', title: '尝试', width: 76 },
      { colKey: 'available_at', title: '可执行时间', width: 168 },
      { colKey: 'created_at', title: '入队时间', width: 168 },
      { colKey: 'actions', title: '操作', fixed: 'right', width: 88 },
    ];
  }

  return [
    { colKey: 'id', title: 'ID', width: 76 },
    { colKey: 'job', title: '任务 / 队列', minWidth: 220 },
    { colKey: 'exception', title: '异常摘要', minWidth: 320 },
    { colKey: 'attempts', title: '尝试', width: 76 },
    { colKey: 'failed_at', title: '失败时间', width: 168 },
    { colKey: 'actions', title: '操作', fixed: 'right', width: 88 },
  ];
});

async function load() {
  if (!props.visible) return;
  loading.value = true;
  try {
    const response = await adminApi.schedules.queueJobs({
      queue: props.queue,
      page: page.value,
      page_size: pageSize.value,
    });
    const list = Array.isArray(response.list) ? response.list : [];
    rows.value = list as RecordRow[];
    total.value = Number(response.total || 0);
  } catch (error) {
    rows.value = [];
    total.value = 0;
    MessagePlugin.error(errorMessage(error, '队列任务加载失败'));
  } finally {
    loading.value = false;
  }
}

function handlePageChange(data: { current: number; pageSize: number }) {
  page.value = Math.max(1, Number(data?.current || 1));
  pageSize.value = Math.max(1, Number(data?.pageSize || pageSize.value));
  load();
}

function handlePageSizeChange() {
  page.value = 1;
  load();
}

async function removeJob(row: RecordRow) {
  const jobId = Number(row.id || 0);
  if (!jobId) return;
  deletingId.value = jobId;
  try {
    await adminApi.schedules.deleteQueueJob(props.queue, jobId);
    MessagePlugin.success(`已删除任务 #${jobId}`);
    emit('changed');
    // 当前页删空后回退一页，避免停留在空页
    if (rows.value.length <= 1 && page.value > 1) {
      page.value -= 1;
    }
    await load();
  } catch (error) {
    MessagePlugin.error(errorMessage(error, '队列任务删除失败'));
  } finally {
    deletingId.value = 0;
  }
}

function stateLabel(state: unknown) {
  const key = String(state || '');
  if (key === 'reserved') return '执行中';
  if (key === 'failed') return '失败';
  return '待处理';
}

function stateTheme(state: unknown) {
  const key = String(state || '');
  if (key === 'reserved') return 'warning';
  if (key === 'failed') return 'danger';
  return 'success';
}

// 切换队列（pending↔failed）时页码必须回到第 1 页，否则可能停留在空页
watch(
  () => props.queue,
  () => {
    page.value = 1;
  },
);

watch(
  () => [props.visible, props.queue, props.refreshToken],
  () => {
    if (!props.visible) return;
    load();
  },
  { immediate: true },
);
</script>
