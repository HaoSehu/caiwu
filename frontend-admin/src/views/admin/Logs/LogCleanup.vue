<template>
  <div class="admin-log-page">
    <section class="page-header">
      <div class="page-title-group">
        <h2>日志清理</h2>
        <p>按类型清理数据库日志与系统文件日志，释放存储并保留必要追踪窗口。</p>
      </div>

      <div class="page-actions">
        <el-button :icon="RefreshRight" @click="loadOverview">刷新概览</el-button>
      </div>
    </section>

    <el-alert
      title="日志清理为不可逆操作，建议先确认保留天数和清理范围。"
      type="warning"
      :closable="false"
      show-icon
    />

    <section class="section-block">
      <div class="section-head">
        <div>
          <h3>数据库日志概览</h3>
          <p>当前可清理的数据库日志记录总览。</p>
        </div>
      </div>

      <div class="card-grid">
        <article v-for="card in databaseCards" :key="card.key" class="count-card">
          <strong>{{ card.value }}</strong>
          <span>{{ card.label }}</span>
        </article>
      </div>
    </section>

    <section class="section-block">
      <div class="section-head">
        <div>
          <h3>文件日志概览</h3>
          <p>当前 `laravel.log` 的基础信息与日志条目数量。</p>
        </div>
      </div>

      <div class="card-grid">
        <article v-for="card in fileCards" :key="card.key" class="count-card">
          <strong>{{ card.value }}</strong>
          <span>{{ card.label }}</span>
        </article>
      </div>

      <el-card shadow="never" class="panel-card">
        <div class="file-meta">
          <div class="file-meta-item">
            <span class="drawer-label">文件路径</span>
            <strong class="mono-text">{{ overview.file?.path || '-' }}</strong>
          </div>
          <div class="file-meta-item">
            <span class="drawer-label">最后更新时间</span>
            <strong>{{ formatLogDate(overview.file?.updated_at) }}</strong>
          </div>
        </div>
      </el-card>
    </section>

    <el-card shadow="never" class="panel-card">
      <template #header>
        <div class="table-header">
          <div>
            <h3>执行清理</h3>
            <p>请输入确认文本后执行清理，按钮会自动防止重复提交。</p>
          </div>
        </div>
      </template>

      <el-form ref="formRef" :model="cleanupForm" :rules="rules" label-position="top" class="cleanup-form">
        <el-form-item label="清理类型" prop="type">
          <el-select v-model="cleanupForm.type" placeholder="请选择清理类型">
            <el-option
              v-for="item in overview.supported_cleanup_types || []"
              :key="item.value"
              :label="item.label"
              :value="item.value"
            />
          </el-select>
        </el-form-item>

        <el-form-item label="保留天数" prop="keep_days">
          <el-input-number v-model="cleanupForm.keep_days" :min="1" :max="3650" :step="1" />
        </el-form-item>

        <el-form-item label="确认文本" prop="confirm_text">
          <el-input
            v-model="cleanupForm.confirm_text"
            placeholder="请输入 立即清理"
            clearable
          />
        </el-form-item>

        <div class="filter-actions">
          <el-button type="danger" :loading="submitting" @click="handleCleanup">立即清理</el-button>
          <el-button :icon="RefreshRight" @click="resetConfirmText">清空确认</el-button>
        </div>
      </el-form>
    </el-card>

    <el-card v-if="lastResult" shadow="never" class="panel-card">
      <template #header>
        <div class="table-header">
          <div>
            <h3>最近一次清理结果</h3>
            <p>显示本次清理范围、截止时间和影响条数。</p>
          </div>
        </div>
      </template>

      <div class="drawer-grid">
        <div class="drawer-item">
          <span class="drawer-label">清理类型</span>
          <strong>{{ lastResult.type || '-' }}</strong>
        </div>
        <div class="drawer-item">
          <span class="drawer-label">保留天数</span>
          <strong>{{ lastResult.keep_days || 0 }} 天</strong>
        </div>
        <div class="drawer-item">
          <span class="drawer-label">截止时间</span>
          <strong>{{ formatLogDate(lastResult.cutoff_at) }}</strong>
        </div>
        <div class="drawer-item">
          <span class="drawer-label">影响条数</span>
          <strong>{{ affectedCountText }}</strong>
        </div>
      </div>

      <div class="json-title">影响明细</div>
      <div class="json-block">{{ formatJsonBlock(lastResult.affected) }}</div>
    </el-card>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { ElMessage } from 'element-plus'
import { RefreshRight } from '@element-plus/icons-vue'
import { cleanupLogs, getLogCleanupOverview } from '@/api/admin'
import {
  formatBytes,
  formatJsonBlock,
  formatLogDate,
} from './logUtils'

const formRef = ref(null)
const submitting = ref(false)
const lastResult = ref(null)
const overview = ref({
  database: {},
  file: {},
  supported_cleanup_types: [],
})

const cleanupForm = reactive({
  type: '',
  keep_days: 30,
  confirm_text: '',
})

const rules = {
  type: [{ required: true, message: '请选择清理类型', trigger: 'change' }],
  keep_days: [{ required: true, message: '请输入保留天数', trigger: 'change' }],
  confirm_text: [{ required: true, message: '请输入确认文本 立即清理', trigger: 'blur' }],
}

const databaseCards = computed(() => [
  { key: 'sms', label: '短信日志', value: overview.value.database?.sms || 0 },
  { key: 'email', label: '邮件日志', value: overview.value.database?.email || 0 },
  { key: 'api', label: 'API 日志', value: overview.value.database?.api || 0 },
  { key: 'admin_login', label: '管理员登录日志', value: overview.value.database?.admin_login || 0 },
])

const fileCards = computed(() => [
  { key: 'exists', label: '日志文件状态', value: overview.value.file?.exists ? '存在' : '缺失' },
  { key: 'size_bytes', label: '文件大小', value: formatBytes(overview.value.file?.size_bytes) },
  { key: 'task_log_count', label: '任务日志条数', value: overview.value.file?.task_log_count || 0 },
  { key: 'system_log_count', label: '系统日志条数', value: overview.value.file?.system_log_count || 0 },
])

const affectedCountText = computed(() => {
  const affected = Object.values(lastResult.value?.affected || {})
  if (!affected.length) {
    return '0'
  }

  return affected.reduce((total, value) => total + Number(value || 0), 0)
})

async function loadOverview() {
  try {
    const res = await getLogCleanupOverview()
    overview.value = res.data || {
      database: {},
      file: {},
      supported_cleanup_types: [],
    }

    if (!cleanupForm.type) {
      cleanupForm.type = overview.value.supported_cleanup_types?.[0]?.value || 'sms'
    }
  } catch (error) {
    ElMessage.error(error.message || '加载日志清理概览失败')
  }
}

async function handleCleanup() {
  if (!formRef.value) {
    return
  }

  try {
    await formRef.value.validate()
  } catch {
    return
  }

  submitting.value = true

  try {
    const res = await cleanupLogs({
      type: cleanupForm.type,
      keep_days: cleanupForm.keep_days,
      confirm_text: cleanupForm.confirm_text,
    })

    lastResult.value = res.data || null
    cleanupForm.confirm_text = ''
    await loadOverview()
    ElMessage.success('日志清理完成')
  } catch (error) {
    ElMessage.error(error.message || '日志清理失败')
  } finally {
    submitting.value = false
  }
}

function resetConfirmText() {
  cleanupForm.confirm_text = ''
}

onMounted(loadOverview)
</script>

<style scoped lang="scss">
@use './logPage.scss';

.cleanup-form {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 16px;
  align-items: end;
}

.cleanup-form :deep(.el-form-item) {
  margin-bottom: 0;
}

.cleanup-form :deep(.el-input__wrapper),
.cleanup-form :deep(.el-select__wrapper),
.cleanup-form :deep(.el-input-number) {
  width: 100%;
}

.file-meta {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 16px;
}

.file-meta-item {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.json-title {
  margin-top: 16px;
  color: $text-color-primary;
  font-size: 13px;
  font-weight: 600;
}

@media (max-width: 1200px) {
  .cleanup-form {
    grid-template-columns: 1fr 1fr;
  }
}

@media (max-width: 768px) {
  .cleanup-form,
  .file-meta {
    grid-template-columns: 1fr;
  }
}
</style>
