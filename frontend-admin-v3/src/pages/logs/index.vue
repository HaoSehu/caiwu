<template>
  <div class="logs-page">
    <t-card :bordered="false">
      <div class="page-tabs-toolbar">
        <t-tabs :value="activeTab" @change="handleTabChange">
          <t-tab-panel v-for="item in tabOptions" :key="item.value" :value="item.value" :label="item.label" />
        </t-tabs>
        <t-button variant="outline" :loading="currentLoading" @click="refreshCurrentTab">
          <template #icon><refresh-icon /></template>
          刷新
        </t-button>
      </div>
    </t-card>

    <template v-if="isLogTab(activeTab)">
      <t-card :bordered="false">
        <div class="log-filter-grid" :class="`log-filter-grid--${activeTab}`">
          <t-select v-if="showFilter('level')" v-model="filters.level" clearable placeholder="日志级别">
            <t-option v-for="item in logLevelOptions" :key="item" :label="item" :value="item" />
          </t-select>
          <t-select v-if="showFilter('task_key')" v-model="filters.task_key" clearable placeholder="任务名称">
            <t-option v-for="item in taskLogOptions" :key="item.value" :label="item.label" :value="item.value" />
          </t-select>
          <t-select v-if="showFilter('method')" v-model="filters.method" clearable placeholder="请求方法">
            <t-option v-for="item in methodOptions" :key="item" :label="item" :value="item" />
          </t-select>
          <t-input v-if="showFilter('module')" v-model="filters.module" clearable placeholder="模块，例如 auth / order" @enter="handleLogSearch" />
          <t-select v-if="showFilter('user_type')" v-model="filters.user_type" clearable placeholder="调用端">
            <t-option label="管理员" value="admin" />
            <t-option label="客户" value="client" />
            <t-option label="访客" value="guest" />
          </t-select>
          <t-select v-if="showFilter('status')" v-model="filters.status" clearable :placeholder="statusPlaceholder">
            <t-option v-for="item in statusOptions" :key="String(item.value)" :label="item.label" :value="item.value" />
          </t-select>
          <t-input v-if="showFilter('phone')" v-model="filters.phone" clearable placeholder="输入接收手机号" @enter="handleLogSearch" />
          <t-input v-if="showFilter('email')" v-model="filters.email" clearable placeholder="输入收件邮箱" @enter="handleLogSearch" />
          <t-input v-if="showFilter('keyword')" v-model="filters.keyword" clearable :placeholder="keywordPlaceholder" @enter="handleLogSearch">
            <template #suffix-icon><search-icon /></template>
          </t-input>
          <t-date-picker
            v-if="showFilter('date')"
            v-model="filters.start_date"
            clearable
            mode="date"
            format="YYYY-MM-DD"
            value-type="YYYY-MM-DD"
            placeholder="开始日期"
            @change="handleLogSearch"
          />
          <t-date-picker
            v-if="showFilter('date')"
            v-model="filters.end_date"
            clearable
            mode="date"
            format="YYYY-MM-DD"
            value-type="YYYY-MM-DD"
            placeholder="结束日期"
            @change="handleLogSearch"
          />
          <t-button theme="primary" @click="handleLogSearch">
            <template #icon><search-icon /></template>
            搜索
          </t-button>
          <t-button variant="outline" @click="resetLogFilters">重置</t-button>
        </div>

        <div class="table-scroll">
          <t-table row-key="id" :data="logRows" :columns="logTableColumns" :loading="logLoading" hover table-layout="fixed">
            <template #time="{ row }">{{ formatDate(row.time || row.created_at || row.sent_at) }}</template>
            <template #primary="{ row }">
              <div class="stack-cell">
                <strong>{{ primaryTitle(row) }}</strong>
                <span>{{ primarySubText(row) }}</span>
              </div>
            </template>
            <template #request="{ row }">
              <div class="request-cell">
                <div>
                  <t-tag variant="light">{{ fieldValue(row.method) }}</t-tag>
                  <strong>{{ fieldValue(row.path) }}</strong>
                </div>
                <span>{{ fieldValue(row.request_id || '无请求号') }}</span>
              </div>
            </template>
            <template #level="{ row }">
              <t-tag :theme="levelTheme(row.level)" variant="light">{{ fieldValue(row.level) }}</t-tag>
            </template>
            <template #status="{ row }">
              <t-tag :theme="statusTheme(row.status)" variant="light">{{ statusLabel(row.status) }}</t-tag>
            </template>
            <template #httpStatus="{ row }">
              <t-tag :theme="httpStatusTheme(row.status)" variant="light">{{ fieldValue(row.status) }}</t-tag>
            </template>
            <template #message="{ row }">
              <span class="line-clamp">{{ messageText(row) }}</span>
            </template>
            <template #error="{ row }">
              <span :class="{ 'danger-text': row.error_msg }">{{ fieldValue(row.error_msg) }}</span>
            </template>
            <template #actions="{ row }">
              <t-button theme="primary" variant="text" @click="openDetail(row)">详情</t-button>
            </template>
          </t-table>
        </div>

        <div v-if="logPagination.total > 0" class="pagination-row">
          <t-pagination
            :current="logPagination.page"
            :page-size="logPagination.per_page"
            :total="logPagination.total"
            :page-size-options="[10, 15, 20, 50, 100]"
            show-jumper
            @change="handleLogPageChange"
          />
        </div>
      </t-card>
    </template>

    <template v-else-if="activeTab === 'schedules'">
      <t-card v-if="!canManageSchedules" :bordered="false">
        <t-alert theme="warning" message="当前账号缺少 settings.manage 权限，无法查看或触发定时任务。" />
      </t-card>
      <template v-else>
        <t-card :bordered="false" :loading="scheduleLoading">
          <template #title>任务清单</template>
          <template #subtitle>共 {{ scheduleTasks.length }} 个任务</template>
          <div class="table-scroll">
            <t-table row-key="key" :data="scheduleTasks" :columns="scheduleColumns" hover table-layout="fixed">
              <template #task="{ row }">
                <div class="stack-cell">
                  <strong>{{ fieldValue(row.title || row.key) }}</strong>
                  <span>{{ fieldValue(row.category) }}</span>
                </div>
              </template>
              <template #cycle="{ row }">
                <div class="stack-cell">
                  <strong>{{ formatScheduleCycle(row) }}</strong>
                  <span>{{ fieldValue(row.expression) }}</span>
                </div>
              </template>
              <template #last="{ row }">{{ fieldValue(row.last_run_at || toRecord(row.last_log).time) }}</template>
              <template #level="{ row }">
                <t-tag :theme="levelTheme(toRecord(row.last_log).level)" variant="light">
                  {{ fieldValue(toRecord(row.last_log).level || '待执行') }}
                </t-tag>
              </template>
              <template #next="{ row }">{{ fieldValue(row.next_run_at) }}</template>
              <template #actions="{ row }">
                <t-button
                  v-if="row.manual_triggerable"
                  theme="primary"
                  variant="outline"
                  size="small"
                  :loading="triggeringKey === row.key"
                  @click="triggerTask(row)"
                >
                  立即执行
                </t-button>
                <span v-else class="muted-text">自动</span>
              </template>
            </t-table>
          </div>
        </t-card>

        <t-card :bordered="false" :loading="scheduleLoading">
          <template #title>最近执行日志</template>
          <div class="table-scroll">
            <t-table row-key="id" :data="scheduleLogs" :columns="scheduleLogColumns" hover table-layout="fixed">
              <template #time="{ row }">{{ fieldValue(row.time) }}</template>
              <template #message="{ row }">{{ fieldValue(row.message) }}</template>
            </t-table>
          </div>
        </t-card>
      </template>
    </template>

    <template v-else>
      <t-card :bordered="false" :loading="cleanupLoading">
        <t-alert theme="warning" message="日志清理为不可逆操作，请确认保留天数、清理类型和确认文本。" />

        <section class="cleanup-section">
          <div class="section-title">
            <h2>数据库日志概览</h2>
            <p>当前可清理的数据库日志记录总览。</p>
          </div>
          <div class="cleanup-card-grid">
            <article v-for="item in databaseCards" :key="item.key">
              <span>{{ item.label }}</span>
              <strong>{{ item.value }}</strong>
            </article>
          </div>
        </section>

        <section class="cleanup-section">
          <div class="section-title">
            <h2>文件日志概览</h2>
            <p>当前 laravel.log 的基础信息与日志条目数量。</p>
          </div>
          <div class="cleanup-card-grid">
            <article v-for="item in fileCards" :key="item.key">
              <span>{{ item.label }}</span>
              <strong>{{ item.value }}</strong>
            </article>
          </div>
          <div class="file-meta-grid">
            <article>
              <span>文件路径</span>
              <strong>{{ fieldValue(toRecord(cleanupOverview.file).path) }}</strong>
            </article>
            <article>
              <span>最后更新时间</span>
              <strong>{{ formatDate(toRecord(cleanupOverview.file).updated_at) }}</strong>
            </article>
          </div>
        </section>

        <section class="cleanup-section">
          <div class="section-title">
            <h2>执行清理</h2>
            <p>请输入确认文本后执行清理。</p>
          </div>
          <t-form :data="cleanupForm" label-align="top" class="cleanup-form">
            <t-form-item label="清理类型" name="type">
              <t-select v-model="cleanupForm.type" placeholder="请选择清理类型">
                <t-option
                  v-for="item in cleanupTypes"
                  :key="String(item.value)"
                  :label="String(item.label || item.value)"
                  :value="String(item.value)"
                />
              </t-select>
            </t-form-item>
            <t-form-item label="保留天数" name="keep_days">
              <t-input-number v-model="cleanupForm.keep_days" :min="1" :max="3650" theme="normal" />
            </t-form-item>
            <t-form-item label="确认文本" name="confirm_text">
              <t-input v-model="cleanupForm.confirm_text" clearable placeholder="请输入 立即清理" />
            </t-form-item>
            <div class="cleanup-actions">
              <t-button theme="danger" :loading="cleanupSubmitting" @click="handleCleanup">立即清理</t-button>
              <t-button variant="outline" @click="cleanupForm.confirm_text = ''">清空确认</t-button>
            </div>
          </t-form>
        </section>

        <section v-if="lastCleanupResult" class="cleanup-section">
          <div class="section-title">
            <h2>最近一次清理结果</h2>
            <p>显示本次清理范围、截止时间和影响条数。</p>
          </div>
          <div class="file-meta-grid">
            <article>
              <span>清理类型</span>
              <strong>{{ fieldValue(lastCleanupResult.type) }}</strong>
            </article>
            <article>
              <span>保留天数</span>
              <strong>{{ fieldValue(lastCleanupResult.keep_days) }} 天</strong>
            </article>
            <article>
              <span>截止时间</span>
              <strong>{{ formatDate(lastCleanupResult.cutoff_at) }}</strong>
            </article>
            <article>
              <span>影响条数</span>
              <strong>{{ affectedCountText }}</strong>
            </article>
          </div>
          <pre class="json-block">{{ formatJson(lastCleanupResult.affected) }}</pre>
        </section>
      </t-card>
    </template>

    <t-drawer v-model:visible="detailVisible" :size="drawerSize" :header="detailTitle" :footer="false" @close="closeDetailDrawer">
      <template v-if="currentLog">
        <div class="detail-grid">
          <article v-for="item in detailFields" :key="item.label">
            <span>{{ item.label }}</span>
            <strong>{{ item.value }}</strong>
          </article>
        </div>
        <div v-for="item in detailBlocks" :key="item.label" class="detail-block">
          <h3>{{ item.label }}</h3>
          <iframe
            v-if="item.html"
            class="mail-preview-frame"
            :srcdoc="item.value"
            sandbox="allow-same-origin"
            title="邮件正文预览"
          />
          <pre v-else class="json-block">{{ item.value }}</pre>
        </div>
        <div class="detail-drawer-actions">
          <t-button variant="outline" @click="closeDetailDrawer">
            <template #icon><chevron-left-icon /></template>
            返回
          </t-button>
        </div>
      </template>
    </t-drawer>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { ChevronLeftIcon, RefreshIcon, SearchIcon } from 'tdesign-icons-vue-next';
import { MessagePlugin } from 'tdesign-vue-next';
import type { PageInfo, PrimaryTableCol, TableRowData } from 'tdesign-vue-next';

import { adminApi, type LaravelPagination, type LogListParams } from '@/api/admin';
import { AdminPermissions } from '@/constants/permissions';
import { useUserStore } from '@/store';

import './index.less';

type LogTab = 'system' | 'admin-logins' | 'api' | 'sms' | 'email' | 'tasks';
type LogsTab = LogTab | 'schedules' | 'cleanup';
type RecordRow = Record<string, unknown>;

const route = useRoute();
const router = useRouter();
const userStore = useUserStore();
const validTabs: LogsTab[] = ['system', 'admin-logins', 'api', 'sms', 'email', 'tasks', 'schedules', 'cleanup'];
const activeTab = ref<LogsTab>(normalizeTab(route.query.tab));
const logLoading = ref(false);
const scheduleLoading = ref(false);
const cleanupLoading = ref(false);
const cleanupSubmitting = ref(false);
const triggeringKey = ref('');
const detailVisible = ref(false);
const currentLog = ref<RecordRow | null>(null);
const logRows = ref<RecordRow[]>([]);
const scheduleOverview = ref<Record<string, unknown>>({ tasks: [], recent_logs: [] });
const cleanupOverview = ref<Record<string, unknown>>({ database: {}, file: {}, supported_cleanup_types: [] });
const lastCleanupResult = ref<Record<string, unknown> | null>(null);

const filters = reactive({
  keyword: '',
  level: '',
  task_key: '',
  method: '',
  module: '',
  user_type: '',
  status: '' as string | number,
  phone: '',
  email: '',
  start_date: '',
  end_date: '',
});

const logPagination = reactive({
  page: 1,
  per_page: 15,
  total: 0,
});
const cleanupForm = reactive({
  type: '',
  keep_days: 30,
  confirm_text: '',
});

const tabOptions: Array<{ value: LogsTab; label: string }> = [
  { value: 'system', label: '系统日志' },
  { value: 'admin-logins', label: '管理员登录' },
  { value: 'api', label: 'API 日志' },
  { value: 'sms', label: '短信日志' },
  { value: 'email', label: '邮件日志' },
  { value: 'tasks', label: '任务日志' },
  { value: 'schedules', label: '定时任务' },
  { value: 'cleanup', label: '日志清理' },
];

const logLevelOptions = ['DEBUG', 'INFO', 'NOTICE', 'WARNING', 'ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY'];
const methodOptions = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'HEAD'];
const httpStatusOptions = [200, 201, 204, 400, 401, 403, 404, 422, 500].map((value) => ({ label: String(value), value }));
const notifyStatusOptions = [
  { label: '待发送', value: 'pending' },
  { label: '发送成功', value: 'success' },
  { label: '发送失败', value: 'failed' },
];
const taskLogOptions = [
  { value: 'refresh-hosting-panel-auth', label: '接口认证刷新' },
  { value: 'service-auto-renew', label: '服务自动续费' },
  { value: 'referral-release-rewards', label: '推荐奖励释放' },
  { value: 'service-lifecycle-maintenance', label: '服务生命周期维护' },
  { value: 'service-status-sync', label: '用户产品状态同步' },
  { value: 'billing-maintenance', label: '账单自动化维护' },
  { value: 'product-upstream-config-sync', label: '上游产品配置同步' },
  { value: 'coupon-campaign-dispatch', label: '优惠券活动发放' },
  { value: 'ticket-auto-close', label: '工单自动关闭' },
  { value: 'order-cleanup', label: '账单与充值清理' },
  { value: 'sync-processing-order-status', label: '账单状态同步（兼容）' },
  { value: 'queue-backlog-drain', label: '队列积压消费' },
];
const logMeta: Record<LogTab, { title: string; description: string; filters: string[]; keyword: string }> = {
  system: {
    title: '系统日志',
    description: '查看应用运行日志、警告与错误信息。',
    filters: ['level', 'keyword', 'date'],
    keyword: '日志内容关键词',
  },
  'admin-logins': {
    title: '管理员登录日志',
    description: '记录管理员账号最近登录情况。',
    filters: ['keyword', 'date'],
    keyword: '账号、昵称、角色或 IP',
  },
  api: {
    title: 'API 日志',
    description: '审计后台与客户端接口访问情况。',
    filters: ['keyword', 'method', 'module', 'user_type', 'status', 'date'],
    keyword: '路径、模块、请求号或 IP',
  },
  sms: {
    title: '短信日志',
    description: '查看短信发送状态、请求号与失败原因。',
    filters: ['phone', 'keyword', 'status'],
    keyword: '搜索模板编号、请求号或内容',
  },
  email: {
    title: '邮件日志',
    description: '查看邮件发送状态、失败原因与内容摘要。',
    filters: ['email', 'keyword', 'status'],
    keyword: '搜索模板编号、主题或正文关键词',
  },
  tasks: {
    title: '定时任务日志',
    description: '聚合展示调度任务执行结果、错误级别和原始日志内容。',
    filters: ['task_key', 'level', 'keyword', 'date'],
    keyword: '任务名称或日志内容',
  },
};

const baseLogColumns: Record<LogTab, PrimaryTableCol<RecordRow>[]> = {
  system: [
    { colKey: 'time', title: '记录时间', width: 170 },
    { colKey: 'level', title: '级别', width: 100 },
    { colKey: 'message', title: '日志内容', minWidth: 520 },
    { colKey: 'actions', title: '操作', fixed: 'right', width: 90 },
  ],
  'admin-logins': [
    { colKey: 'primary', title: '账号信息', minWidth: 230 },
    { colKey: 'role_name', title: '角色', minWidth: 140 },
    { colKey: 'ip_address', title: '登录 IP', minWidth: 150 },
    { colKey: 'time', title: '登录时间', width: 170 },
    { colKey: 'status', title: '来源', width: 120 },
    { colKey: 'actions', title: '操作', fixed: 'right', width: 90 },
  ],
  api: [
    { colKey: 'request', title: '请求', minWidth: 320 },
    { colKey: 'httpStatus', title: '状态码', width: 100 },
    { colKey: 'module', title: '模块', minWidth: 120 },
    { colKey: 'primary', title: '调用端', minWidth: 160 },
    { colKey: 'ip_address', title: 'IP', minWidth: 140 },
    { colKey: 'time', title: '记录时间', width: 170 },
    { colKey: 'actions', title: '操作', fixed: 'right', width: 90 },
  ],
  sms: [
    { colKey: 'id', title: 'ID', width: 80 },
    { colKey: 'primary', title: '接收信息', minWidth: 210 },
    { colKey: 'template_code', title: '模板编号', minWidth: 130 },
    { colKey: 'message', title: '内容摘要', minWidth: 260 },
    { colKey: 'status', title: '状态', width: 110 },
    { colKey: 'request_id', title: '请求号', minWidth: 170, ellipsis: true },
    { colKey: 'error', title: '错误信息', minWidth: 210 },
    { colKey: 'time', title: '创建时间', width: 170 },
    { colKey: 'actions', title: '操作', fixed: 'right', width: 90 },
  ],
  email: [
    { colKey: 'id', title: 'ID', width: 80 },
    { colKey: 'primary', title: '收件信息', minWidth: 220 },
    { colKey: 'template_code', title: '模板编号', minWidth: 130 },
    { colKey: 'subject', title: '主题', minWidth: 220, ellipsis: true },
    { colKey: 'message', title: '正文摘要', minWidth: 260 },
    { colKey: 'status', title: '状态', width: 110 },
    { colKey: 'error', title: '错误信息', minWidth: 210 },
    { colKey: 'time', title: '创建时间', width: 170 },
    { colKey: 'actions', title: '操作', fixed: 'right', width: 90 },
  ],
  tasks: [
    { colKey: 'time', title: '记录时间', width: 170 },
    { colKey: 'primary', title: '任务', minWidth: 220 },
    { colKey: 'level', title: '级别', width: 100 },
    { colKey: 'message', title: '日志内容', minWidth: 420 },
    { colKey: 'actions', title: '操作', fixed: 'right', width: 90 },
  ],
};
const scheduleColumns: PrimaryTableCol<RecordRow>[] = [
  { colKey: 'task', title: '任务名称', minWidth: 260 },
  { colKey: 'cycle', title: '刷新周期', minWidth: 180 },
  { colKey: 'last', title: '最后执行', width: 170 },
  { colKey: 'level', title: '状态', width: 110 },
  { colKey: 'next', title: '下次执行', width: 170 },
  { colKey: 'actions', title: '操作', fixed: 'right', width: 120 },
];
const scheduleLogColumns: PrimaryTableCol<RecordRow>[] = [
  { colKey: 'time', title: '时间', width: 170 },
  { colKey: 'task_key', title: '任务', minWidth: 160 },
  { colKey: 'message', title: '内容', minWidth: 360 },
];

const currentLoading = computed(() => logLoading.value || scheduleLoading.value || cleanupLoading.value || cleanupSubmitting.value);
const currentLogMeta = computed(() => (isLogTab(activeTab.value) ? logMeta[activeTab.value] : logMeta.system));
const logTableColumns = computed(() => (isLogTab(activeTab.value) ? baseLogColumns[activeTab.value] : []));
const keywordPlaceholder = computed(() => currentLogMeta.value.keyword);
const statusPlaceholder = computed(() => (activeTab.value === 'api' ? '全部状态码' : '全部发送状态'));
const statusOptions = computed(() => (activeTab.value === 'api' ? httpStatusOptions : notifyStatusOptions));
const drawerSize = computed(() => (window.matchMedia?.('(max-width: 768px)').matches ? '100%' : '700px'));
const detailTitle = computed(() => `${currentLogMeta.value.title}详情`);
const scheduleTasks = computed(() => asArray(scheduleOverview.value.tasks));
const scheduleLogs = computed(() => asArray(scheduleOverview.value.recent_logs));
const canManageSchedules = computed(() => hasPermission(AdminPermissions.SETTINGS_MANAGE));
const cleanupTypes = computed(() => asArray(cleanupOverview.value.supported_cleanup_types));
const databaseCards = computed(() => {
  const database = toRecord(cleanupOverview.value.database);
  return [
    { key: 'sms', label: '短信日志', value: numberText(database.sms) },
    { key: 'email', label: '邮件日志', value: numberText(database.email) },
    { key: 'api', label: 'API 日志', value: numberText(database.api) },
    { key: 'admin_login', label: '管理员登录日志', value: numberText(database.admin_login) },
  ];
});
const fileCards = computed(() => {
  const file = toRecord(cleanupOverview.value.file);
  return [
    { key: 'exists', label: '日志文件状态', value: file.exists ? '存在' : '缺失' },
    { key: 'size_bytes', label: '文件大小', value: formatBytes(file.size_bytes) },
    { key: 'task_log_count', label: '任务日志条数', value: numberText(file.task_log_count) },
    { key: 'system_log_count', label: '系统日志条数', value: numberText(file.system_log_count) },
  ];
});
const affectedCountText = computed(() => {
  const affected = Object.values(toRecord(lastCleanupResult.value?.affected));
  return affected.reduce((total: number, value) => total + Number(value || 0), 0);
});
const detailFields = computed(() => {
  const row = currentLog.value || {};
  if (activeTab.value === 'api') {
    return [
      { label: '请求方法', value: fieldValue(row.method) },
      { label: '状态码', value: fieldValue(row.status) },
      { label: '请求路径', value: fieldValue(row.path) },
      { label: '模块', value: fieldValue(row.module) },
      { label: '请求号', value: fieldValue(row.request_id) },
      { label: '记录时间', value: formatDate(row.created_at) },
      { label: '调用端', value: userTypeLabel(row.user_type) },
      { label: 'IP 地址', value: fieldValue(row.ip_address) },
    ];
  }
  if (activeTab.value === 'admin-logins') {
    return [
      { label: '登录账号', value: fieldValue(row.admin_username) },
      { label: '昵称', value: fieldValue(row.admin_nickname || row.actor_name) },
      { label: '角色', value: fieldValue(row.role_name) },
      { label: '登录时间', value: formatDate(row.created_at) },
      { label: 'IP 地址', value: fieldValue(row.ip_address) },
      { label: '数据来源', value: sourceLabel(row.source) },
    ];
  }
  if (activeTab.value === 'sms') {
    return [
      { label: '手机号', value: fieldValue(row.phone) },
      { label: '发送状态', value: statusLabel(row.status) },
      { label: '模板编号', value: fieldValue(row.template_code) },
      { label: '供应商', value: fieldValue(row.provider) },
      { label: '请求号', value: fieldValue(row.request_id) },
      { label: '发送时间', value: formatDate(row.sent_at) },
      { label: '创建时间', value: formatDate(row.created_at) },
      { label: '错误信息', value: fieldValue(row.error_msg) },
    ];
  }
  if (activeTab.value === 'email') {
    return [
      { label: '收件邮箱', value: fieldValue(row.to_email) },
      { label: '发送状态', value: statusLabel(row.status) },
      { label: '主题', value: fieldValue(row.subject) },
      { label: '模板编号', value: fieldValue(row.template_code) },
      { label: '发送时间', value: formatDate(row.sent_at) },
      { label: '创建时间', value: formatDate(row.created_at) },
      { label: '错误信息', value: fieldValue(row.error_msg) },
    ];
  }
  if (activeTab.value === 'tasks') {
    return [
      { label: '任务名称', value: fieldValue(row.task_title || row.task_key) },
      { label: '任务键', value: fieldValue(row.task_key) },
      { label: '日志级别', value: fieldValue(row.level) },
      { label: '记录时间', value: formatDate(row.time) },
    ];
  }
  return [
    { label: '日志级别', value: fieldValue(row.level) },
    { label: '记录时间', value: formatDate(row.time) },
  ];
});
const detailBlocks = computed(() => {
  const row = currentLog.value || {};
  if (activeTab.value === 'api') {
    return [
      { label: '请求参数', value: formatJson(row.params) },
      { label: '完整上下文', value: formatJson(row.detail) },
      { label: 'User-Agent', value: formatJson(row.user_agent) },
    ];
  }
  if (activeTab.value === 'admin-logins') {
    return [{ label: '上下文详情', value: formatJson(row.detail) }];
  }
  if (activeTab.value === 'sms') {
    return [
      { label: '模板参数', value: formatJson(row.params, '暂无模板参数') },
      { label: '短信内容', value: fieldValue(row.content) },
    ];
  }
  if (activeTab.value === 'email') {
    return [
      { label: '邮件正文预览', value: buildContentPreviewDoc(row.content), html: true },
      { label: 'HTML 源码', value: fieldValue(row.content) },
    ];
  }
  return [
    { label: '格式化内容', value: fieldValue(row.message) },
    { label: '原始日志', value: fieldValue(row.raw) },
  ];
});

function normalizeTab(value: unknown): LogsTab {
  const tab = Array.isArray(value) ? value[0] : value;
  return validTabs.includes(tab as LogsTab) ? (tab as LogsTab) : 'system';
}

function isLogTab(value: LogsTab): value is LogTab {
  return ['system', 'admin-logins', 'api', 'sms', 'email', 'tasks'].includes(value);
}

function showFilter(name: string) {
  return currentLogMeta.value.filters.includes(name);
}

function handleTabChange(value: string | number) {
  activeTab.value = normalizeTab(value);
  router.replace({ path: '/admin/logs', query: activeTab.value === 'system' ? {} : { tab: activeTab.value } });
  resetLogFilters(false);
  refreshCurrentTab();
}

function refreshCurrentTab() {
  if (isLogTab(activeTab.value)) return loadLogs();
  if (activeTab.value === 'schedules') return loadScheduleOverview();
  return loadCleanupOverview();
}

function buildLogParams(): LogListParams {
  const params: LogListParams = {
    page: logPagination.page,
    per_page: logPagination.per_page,
  };
  for (const key of currentLogMeta.value.filters) {
    if (key === 'date') {
      if (filters.start_date.trim()) params.start_date = filters.start_date.trim();
      if (filters.end_date.trim()) params.end_date = filters.end_date.trim();
      continue;
    }
    const value = filters[key as keyof typeof filters];
    if (value !== '' && value !== null && value !== undefined) {
      (params as Record<string, unknown>)[key] = typeof value === 'string' ? value.trim() : value;
    }
  }
  return params;
}

async function loadLogs() {
  if (!isLogTab(activeTab.value)) return;
  logLoading.value = true;
  try {
    const params = buildLogParams();
    const response = await requestLogList(activeTab.value, params);
    logRows.value = response.data || [];
    logPagination.total = Number(response.total || 0);
    logPagination.page = Number(response.current_page || logPagination.page);
    logPagination.per_page = Number(response.per_page || logPagination.per_page);
  } catch (error) {
    logRows.value = [];
    logPagination.total = 0;
    MessagePlugin.error(errorMessage(error, `加载${currentLogMeta.value.title}失败`));
  } finally {
    logLoading.value = false;
  }
}

function requestLogList(tab: LogTab, params: LogListParams): Promise<LaravelPagination> {
  const map = {
    system: adminApi.logs.system,
    'admin-logins': adminApi.logs.adminLogins,
    api: adminApi.logs.api,
    sms: adminApi.logs.sms,
    email: adminApi.logs.email,
    tasks: adminApi.logs.tasks,
  };
  return map[tab](params);
}

function handleLogSearch() {
  logPagination.page = 1;
  loadLogs();
}

function resetLogFilters(shouldLoad = true) {
  Object.assign(filters, {
    keyword: '',
    level: '',
    task_key: '',
    method: '',
    module: '',
    user_type: '',
    status: '',
    phone: '',
    email: '',
    start_date: '',
    end_date: '',
  });
  logPagination.page = 1;
  if (shouldLoad && isLogTab(activeTab.value)) loadLogs();
}

function handleLogPageChange(data: PageInfo) {
  logPagination.page = data.current;
  logPagination.per_page = data.pageSize;
  loadLogs();
}

async function loadScheduleOverview() {
  if (!canManageSchedules.value) return;
  scheduleLoading.value = true;
  try {
    scheduleOverview.value = { ...(await adminApi.schedules.overview()) };
  } catch (error) {
    scheduleOverview.value = { tasks: [], recent_logs: [] };
    MessagePlugin.error(errorMessage(error, '定时任务状态加载失败'));
  } finally {
    scheduleLoading.value = false;
  }
}

async function triggerTask(row: TableRowData) {
  const task = String(row.key || '');
  if (!task) return;
  triggeringKey.value = task;
  try {
    const response = await adminApi.schedules.trigger({ task });
    const modeText =
      {
        sync: '同步执行',
        after_response: '后台立即执行',
        queue: '已进入队列',
      }[String(response.execution_mode || '').toLowerCase()] || '已提交执行';
    MessagePlugin.success(`${fieldValue(row.title || row.key)}${modeText}`);
    await loadScheduleOverview();
  } catch (error) {
    MessagePlugin.error(errorMessage(error, `${fieldValue(row.title || row.key)}执行失败`));
  } finally {
    triggeringKey.value = '';
  }
}

async function loadCleanupOverview() {
  cleanupLoading.value = true;
  try {
    cleanupOverview.value = await adminApi.logs.cleanupOverview();
    if (!cleanupForm.type) cleanupForm.type = String(cleanupTypes.value[0]?.value || 'sms');
  } catch (error) {
    cleanupOverview.value = { database: {}, file: {}, supported_cleanup_types: [] };
    MessagePlugin.error(errorMessage(error, '加载日志清理概览失败'));
  } finally {
    cleanupLoading.value = false;
  }
}

async function handleCleanup() {
  if (!cleanupForm.type) {
    MessagePlugin.warning('请选择清理类型');
    return;
  }
  if (!Number.isFinite(Number(cleanupForm.keep_days)) || Number(cleanupForm.keep_days) < 1) {
    MessagePlugin.warning('保留天数必须大于 0');
    return;
  }
  if (cleanupForm.confirm_text.trim() !== '立即清理') {
    MessagePlugin.warning('确认文本必须为 立即清理');
    return;
  }
  cleanupSubmitting.value = true;
  try {
    lastCleanupResult.value = await adminApi.logs.cleanup({
      type: cleanupForm.type,
      keep_days: Number(cleanupForm.keep_days),
      confirm_text: cleanupForm.confirm_text.trim(),
    });
    cleanupForm.confirm_text = '';
    await loadCleanupOverview();
    MessagePlugin.success('日志清理完成');
  } catch (error) {
    MessagePlugin.error(errorMessage(error, '日志清理失败'));
  } finally {
    cleanupSubmitting.value = false;
  }
}

function openDetail(row: TableRowData) {
  currentLog.value = row as RecordRow;
  detailVisible.value = true;
}

function closeDetailDrawer() {
  detailVisible.value = false;
  currentLog.value = null;
}

function primaryTitle(row: RecordRow) {
  if (activeTab.value === 'admin-logins') return fieldValue(row.admin_username);
  if (activeTab.value === 'api') return fieldValue(row.actor_name || userTypeLabel(row.user_type));
  if (activeTab.value === 'sms') return fieldValue(row.phone);
  if (activeTab.value === 'email') return fieldValue(row.to_email);
  if (activeTab.value === 'tasks') return fieldValue(row.task_title || row.task_key);
  return fieldValue(row.id);
}

function primarySubText(row: RecordRow) {
  if (activeTab.value === 'admin-logins') return fieldValue(row.admin_nickname || row.actor_name || '未设置昵称');
  if (activeTab.value === 'api') return userTypeLabel(row.user_type);
  if (activeTab.value === 'sms' || activeTab.value === 'email') return `发送时间：${formatDate(row.sent_at)}`;
  if (activeTab.value === 'tasks') return fieldValue(row.task_key);
  return '';
}

function messageText(row: RecordRow) {
  if (activeTab.value === 'email') return contentPreview(row.content);
  return fieldValue(row.message || row.content);
}

function statusLabel(status: unknown) {
  if (activeTab.value === 'admin-logins') return sourceLabel(status);
  const statusKey = String(status || '').toLowerCase();
  return String({ success: '发送成功', failed: '发送失败', pending: '待发送' }[statusKey] || fieldValue(status));
}

function statusTheme(status: unknown) {
  if (activeTab.value === 'admin-logins') return String(status) === 'operation_log' ? 'success' : 'warning';
  return (
    {
      success: 'success',
      failed: 'danger',
      pending: 'warning',
    }[String(status || '').toLowerCase()] || 'default'
  );
}

function levelTheme(level: unknown) {
  return (
    {
      DEBUG: 'default',
      INFO: 'success',
      NOTICE: 'primary',
      WARNING: 'warning',
      ERROR: 'danger',
      CRITICAL: 'danger',
      ALERT: 'danger',
      EMERGENCY: 'danger',
      SUCCESS: 'success',
    }[String(level || '').toUpperCase()] || 'default'
  );
}

function httpStatusTheme(status: unknown) {
  const code = Number(status || 0);
  if (code >= 500) return 'danger';
  if (code >= 400) return 'warning';
  if (code >= 200) return 'success';
  return 'default';
}

function userTypeLabel(value: unknown) {
  return (
    {
      admin: '管理员',
      client: '客户',
      guest: '访客',
    }[String(value || '').toLowerCase()] || '系统'
  );
}

function sourceLabel(value: unknown) {
  return (
    {
      operation_log: '操作日志',
      admin_snapshot: '账号快照',
    }[String(value || '').toLowerCase()] || '未知来源'
  );
}

function hasPermission(permission: string) {
  const permissions = userStore.userInfo?.permissions || [];
  return permissions.includes(AdminPermissions.ALL) || permissions.includes(permission);
}

function fieldValue(value: unknown) {
  if (value === null || value === undefined || value === '') return '-';
  return String(value);
}

function formatDate(value: unknown) {
  return fieldValue(value);
}

function asArray(value: unknown): RecordRow[] {
  return Array.isArray(value) ? (value as RecordRow[]) : [];
}

function toRecord(value: unknown): Record<string, unknown> {
  return value && typeof value === 'object' ? (value as Record<string, unknown>) : {};
}

function numberText(value: unknown) {
  return Number(value || 0).toLocaleString('zh-CN');
}

function formatJson(value: unknown, fallback = '-') {
  if (value === null || value === undefined || value === '') return fallback;
  if (typeof value === 'string') {
    const text = value.trim();
    if (!text) return fallback;
    try {
      return JSON.stringify(JSON.parse(text), null, 2);
    } catch {
      return text;
    }
  }
  try {
    return JSON.stringify(value, null, 2);
  } catch {
    return fallback;
  }
}

function contentPreview(value: unknown) {
  const text = String(value ?? '')
    .replace(/<style[\s\S]*?<\/style>/gi, ' ')
    .replace(/<script[\s\S]*?<\/script>/gi, ' ')
    .replace(/<[^>]+>/g, ' ')
    .replace(/&nbsp;/gi, ' ')
    .replace(/\s+/g, ' ')
    .trim();
  if (!text) return '-';
  return text.length > 96 ? `${text.slice(0, 96)}...` : text;
}

function buildContentPreviewDoc(value: unknown) {
  const normalized = String(value ?? '').trim();
  if (!normalized) {
    return '<!doctype html><html lang="zh-CN"><body style="margin:0;padding:24px;color:#86909c;">暂无正文内容</body></html>';
  }
  if (/<!doctype\s+html|<html\b|<body\b/i.test(normalized)) return normalized;
  if (/<([a-z][a-z0-9]*)(\s|>)/i.test(normalized)) {
    return `<!doctype html><html lang="zh-CN"><head><meta charset="UTF-8"><style>body{margin:0;padding:24px;background:#f5f7fa;font-family:Arial,sans-serif;color:#1f2329}.mail-preview{max-width:680px;margin:0 auto;padding:24px;border:1px solid #dce7ff;border-radius:12px;background:#fff}</style></head><body><div class="mail-preview">${normalized}</div></body></html>`;
  }
  return `<!doctype html><html lang="zh-CN"><body style="margin:0;padding:24px;font-family:Arial,sans-serif;background:#f8fafc;color:#1f2329;"><pre style="margin:0;white-space:pre-wrap;line-height:1.75;">${escapeHtml(normalized)}</pre></body></html>`;
}

function escapeHtml(value: string) {
  return value.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}

function formatBytes(value: unknown) {
  const size = Number(value || 0);
  if (!Number.isFinite(size) || size <= 0) return '0 B';
  const units = ['B', 'KB', 'MB', 'GB'];
  let current = size;
  let unitIndex = 0;
  while (current >= 1024 && unitIndex < units.length - 1) {
    current /= 1024;
    unitIndex += 1;
  }
  return `${current.toFixed(current >= 10 || unitIndex === 0 ? 0 : 1)} ${units[unitIndex]}`;
}

function formatScheduleCycle(row: RecordRow) {
  const expression = String(row.expression || '').trim();
  if (!expression) return '--';
  if (expression === '* * * * *') return '每 1 分钟';
  if (expression === '0 * * * *') return '每 1 小时';
  const everyMinutesMatch = expression.match(/^\*\/(\d+) \* \* \* \*$/);
  if (everyMinutesMatch) return `每 ${everyMinutesMatch[1]} 分钟`;
  const fixedMinuteHourlyMatch = expression.match(/^(\d{1,2}) \* \* \* \*$/);
  if (fixedMinuteHourlyMatch) return `每小时第 ${fixedMinuteHourlyMatch[1]} 分钟`;
  const dailyMatch = expression.match(/^(\d{1,2}) (\d{1,2}) \* \* \*$/);
  if (dailyMatch) return `每天 ${String(dailyMatch[2]).padStart(2, '0')}:${String(dailyMatch[1]).padStart(2, '0')}`;
  return '自定义周期';
}

function errorMessage(error: unknown, fallback: string) {
  const record = toRecord(error);
  const response = toRecord(record.response);
  const data = toRecord(response.data);
  return String(data.message || record.message || fallback);
}

watch(
  () => route.query.tab,
  (value) => {
    const nextTab = normalizeTab(value);
    if (nextTab === activeTab.value) return;
    activeTab.value = nextTab;
    resetLogFilters(false);
    refreshCurrentTab();
  },
);

onMounted(() => refreshCurrentTab());
</script>
