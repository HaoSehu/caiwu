<template>
  <div class="schedule-page admin-page" v-loading="loading">
    <section class="admin-page-head">
      <div class="admin-page-heading">
        <h2>定时任务</h2>
        <p>管理系统调度任务的执行状态与手动触发</p>
      </div>

      <div class="page-actions">
        <el-button @click="loadOverview">刷新</el-button>
      </div>
    </section>

    <el-card shadow="never">
      <template #header>
        <div class="panel-header">
          <div class="panel-header-meta">
            <strong>任务清单</strong>
            <span>共 {{ formatCount((overview.tasks || []).length) }} 个任务</span>
          </div>
        </div>
      </template>

      <p class="mobile-scroll-hint">左右滑动可查看完整表格</p>

      <div class="table-scroll-shell task-table-shell">
        <el-table :data="taskRows" stripe class="schedule-table schedule-table--tasks">
          <template #empty>
            <div class="panel-empty">
              <strong>暂无任务数据</strong>
              <p>请确认后端已正确注册调度任务</p>
            </div>
          </template>

          <el-table-column label="任务名称" min-width="260">
            <template #default="{ row }">
              <div class="task-name">
                <strong>{{ row.title || row.key }}</strong>
                <span v-if="row.category" class="task-category">{{ row.category }}</span>
              </div>
            </template>
          </el-table-column>

          <el-table-column label="刷新周期" min-width="180">
            <template #default="{ row }">
              <div class="task-cycle">
                <strong>{{ formatScheduleCycle(row) }}</strong>
                <span v-if="row.expression" class="task-cycle-expression">{{ row.expression }}</span>
              </div>
            </template>
          </el-table-column>

          <el-table-column label="最后执行" width="170">
            <template #default="{ row }">
              <span class="time-text">{{ row.last_run_at || row.last_log?.time || '--' }}</span>
            </template>
          </el-table-column>

          <el-table-column label="状态" width="110">
            <template #default="{ row }">
              <el-tag
                v-if="row.last_log"
                size="small"
                effect="plain"
                :type="logTagType(row.last_log.level)">
                {{ row.last_log.level || '成功' }}
              </el-tag>
              <el-tag v-else size="small" effect="plain" type="info">待执行</el-tag>
            </template>
          </el-table-column>

          <el-table-column label="下次执行" width="170">
            <template #default="{ row }">
              <span>{{ row.next_run_at || '--' }}</span>
            </template>
          </el-table-column>

          <el-table-column label="操作" width="130" :fixed="isMobile ? false : 'right'" align="center">
            <template #default="{ row }">
              <el-button
                v-if="isTriggerable(row)"
                size="small"
                type="primary"
                :loading="triggeringKey === row.key"
                @click="triggerTask(row)"
              >
                立即执行
              </el-button>
              <span v-else class="muted-text">自动</span>
            </template>
          </el-table-column>
        </el-table>
      </div>
    </el-card>

    <!-- 最近日志 -->
    <el-card shadow="never" style="margin-top: 16px;">
      <template #header>
        <div class="panel-header">
          <div class="panel-header-meta">
            <strong>最近执行日志</strong>
          </div>
        </div>
      </template>

      <div class="table-scroll-shell log-table-shell">
        <el-table :data="recentLogs" stripe size="small" class="schedule-table schedule-table--logs">
          <template #empty>
            <div class="panel-empty">
              <strong>暂无日志</strong>
            </div>
          </template>
          <el-table-column prop="time" label="时间" width="160" />
          <el-table-column prop="task_key" label="任务" min-width="140" />
          <el-table-column prop="message" label="内容" min-width="340" />
        </el-table>
      </div>
    </el-card>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { ElMessage } from 'element-plus'
import adminApi from '@/api/admin'
import { useResponsive } from '@/composables/useResponsive'

const { isMobile } = useResponsive()
const loading = ref(false)
const triggeringKey = ref('')
const overview = ref({
  tasks: [],
  recent_logs: [],
})

const taskRows = computed(() => overview.value.tasks || [])
const recentLogs = computed(() => overview.value.recent_logs || [])

function formatCount(value) {
  return Number(value || 0).toLocaleString('zh-CN')
}

function isTriggerable(task) {
  return Boolean(task?.manual_triggerable)
}

function formatScheduleCycle(task) {
  const expression = String(task?.expression || '').trim()

  if (!expression) return '--'
  if (expression === '* * * * *') return '每 1 分钟'
  if (expression === '0 * * * *') return '每 1 小时'

  const everyMinutesMatch = expression.match(/^\*\/(\d+) \* \* \* \*$/)
  if (everyMinutesMatch) {
    return `每 ${everyMinutesMatch[1]} 分钟`
  }

  const fixedMinuteHourlyMatch = expression.match(/^(\d{1,2}) \* \* \* \*$/)
  if (fixedMinuteHourlyMatch) {
    return `每小时第 ${fixedMinuteHourlyMatch[1]} 分钟`
  }

  const dailyMatch = expression.match(/^(\d{1,2}) (\d{1,2}) \* \* \*$/)
  if (dailyMatch) {
    const minute = String(dailyMatch[1]).padStart(2, '0')
    const hour = String(dailyMatch[2]).padStart(2, '0')
    return `每天 ${hour}:${minute}`
  }

  return '自定义周期'
}

function logTagType(level) {
  return ({
    ERROR: 'danger',
    WARNING: 'warning',
    INFO: 'success',
    SUCCESS: 'success',
  })[String(level || '').toUpperCase()] || 'info'
}

async function loadOverview(options = {}) {
  loading.value = true
  try {
    const res = await adminApi.schedules.overview({ silent: options.silent === true })
    overview.value = {
      tasks: res.data?.tasks || [],
      recent_logs: res.data?.recent_logs || [],
    }
  } catch (e) {
    overview.value = { tasks: [], recent_logs: [] }
    console.error(e)
    if (options.silent) {
      throw e
    }
    if (!options.silent) {
      ElMessage.error('定时任务状态加载失败，请稍后重试')
    }
  } finally {
    loading.value = false
  }
}

async function triggerTask(task) {
  const taskKey = String(task?.key || '')
  if (!isTriggerable(task)) return

  triggeringKey.value = taskKey
  try {
    const res = await adminApi.schedules.trigger({ task: taskKey }, { silent: true })
    const modeText = ({
      sync: '同步执行',
      after_response: '后台立即执行',
      queue: '已进入队列',
    })[String(res?.data?.execution_mode || '').toLowerCase()] || '已提交执行'

    ElMessage.success(`${task.title || taskKey}${modeText}`)
    try {
      await loadOverview({ silent: true })
    } catch (refreshError) {
      console.error(refreshError)
      ElMessage.warning('任务已提交，但状态刷新失败，请稍后手动刷新')
    }
    void refreshTaskStateAfterTrigger()
  } catch (e) {
    const message = String(e?.message || '').trim()
    ElMessage.error(message ? `${task.title || taskKey}：${message}` : `${task.title || taskKey} 执行失败`)
  } finally {
    triggeringKey.value = ''
  }
}

async function refreshTaskStateAfterTrigger() {
  for (const delay of [1200, 3000]) {
    await new Promise((resolve) => window.setTimeout(resolve, delay))
    try {
      await loadOverview({ silent: true })
    } catch (e) {
      console.error(e)
      break
    }
  }
}

onMounted(() => {
  loadOverview()
})
</script>

<style scoped lang="scss">
.admin-page-head {
  margin-bottom: 20px;
}

.panel-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.panel-header-meta strong {
  font-size: 15px;
  font-weight: 600;
  color: $text-color-primary;
}

.task-name {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.task-name strong {
  color: $text-color-primary;
}

.task-category {
  font-size: 12px;
  color: $text-color-secondary;
}

.task-cycle {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.task-cycle strong {
  color: $text-color-primary;
  font-size: 13px;
  font-weight: 600;
}

.task-cycle-expression {
  font-size: 12px;
  color: $text-color-secondary;
  font-family: Consolas, 'Courier New', monospace;
}

.time-text {
  font-size: 13px;
  color: $text-color-secondary;
}

.muted-text {
  color: $text-color-placeholder;
  font-size: 12px;
}

.panel-empty {
  padding: 60px 0;
  text-align: center;
  color: $text-color-secondary;
}

.mobile-scroll-hint {
  display: none;
  margin: 0 0 10px;
  color: $text-color-secondary;
  font-size: 12px;
}

.table-scroll-shell {
  overflow-x: auto;
}

.table-scroll-shell :deep(.el-table) {
  width: 100%;
}

.schedule-table--tasks {
  min-width: 980px;
}

.schedule-table--logs {
  min-width: 680px;
}

@media (max-width: 768px) {
  .admin-page-head,
  .panel-header {
    flex-direction: column;
    align-items: stretch;
    gap: 12px;
  }

  .page-actions {
    width: 100%;
  }

  .page-actions :deep(.el-button) {
    width: 100%;
  }

  .mobile-scroll-hint {
    display: block;
  }

  .table-scroll-shell {
    margin: 0 -12px;
    padding: 0 12px 6px;
  }

  .panel-empty {
    padding: 36px 0;
  }
}
</style>
