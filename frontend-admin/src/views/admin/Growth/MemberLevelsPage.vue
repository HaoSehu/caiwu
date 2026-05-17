<template>
  <div class="member-levels-page admin-page" v-loading="loading">
    <section class="admin-page-head">
      <div class="admin-page-heading">
        <span class="admin-page-kicker">用户体系</span>
        <h2>会员等级与返利档位</h2>
        <p>按累计销售额设置会员等级和对应返利比例，推荐奖励会优先匹配这里的等级配置。</p>
      </div>

      <div class="page-actions">
        <el-button @click="loadLevels">刷新</el-button>
        <el-button type="primary" @click="openCreateDialog">新增等级</el-button>
      </div>
    </section>

    <el-card shadow="never">
      <template #header>
        <div class="panel-header">
          <div class="panel-header-meta">
            <strong>等级列表</strong>
            <span>共 {{ formatCount(levels.length) }} 个档位，按排序值和门槛展示</span>
          </div>
        </div>
      </template>

      <el-table :data="levels" stripe>
        <template #empty>
          <div class="panel-empty">
            <strong>暂无会员等级</strong>
            <p>建议先创建基础等级，用于控制推荐返利比例。</p>
            <el-button type="primary" size="small" @click="openCreateDialog">新增等级</el-button>
          </div>
        </template>

        <el-table-column prop="sort_order" label="排序" width="90" />

        <el-table-column label="等级信息" min-width="220">
          <template #default="{ row }">
            <div class="level-main">
              <strong>{{ row.name }}</strong>
              <span>{{ row.code || '--' }}</span>
              <small>创建于 {{ formatDateTime(row.created_at) }}</small>
            </div>
          </template>
        </el-table-column>

        <el-table-column label="累计销售额门槛" min-width="220">
          <template #default="{ row }">
            <div class="range-cell">
              <strong>{{ formatCurrency(row.sales_amount_min) }}</strong>
              <span>至 {{ row.sales_amount_max ? formatCurrency(row.sales_amount_max) : '不封顶' }}</span>
            </div>
          </template>
        </el-table-column>

        <el-table-column label="返利比例" width="140">
          <template #default="{ row }">
            <el-tag size="small" type="success" effect="plain">
              {{ formatPercent(row.reward_rate) }}
            </el-tag>
          </template>
        </el-table-column>

        <el-table-column label="状态" width="120">
          <template #default="{ row }">
            <el-tag size="small" :type="Number(row.status) === 1 ? 'success' : 'info'">
              {{ Number(row.status) === 1 ? '启用' : '停用' }}
            </el-tag>
          </template>
        </el-table-column>

        <el-table-column label="备注" min-width="220">
          <template #default="{ row }">
            <span class="remark-text">{{ row.remark || '--' }}</span>
          </template>
        </el-table-column>

        <el-table-column label="更新时间" min-width="180">
          <template #default="{ row }">
            {{ formatDateTime(row.updated_at) }}
          </template>
        </el-table-column>

        <el-table-column label="操作" :width="isMobile ? 60 : 120" fixed="right">
          <template #default="{ row }">
            <div v-if="!isMobile" class="table-actions">
              <el-button size="small" text type="primary" @click="openEditDialog(row)">编辑</el-button>
              <el-button size="small" text type="danger" @click="handleDelete(row)">删除</el-button>
            </div>
            <el-dropdown v-else trigger="click" @command="(cmd) => handleLevelAction(cmd, row)">
              <span class="action-link">···</span>
              <template #dropdown>
                <el-dropdown-menu>
                  <el-dropdown-item command="edit">编辑</el-dropdown-item>
                  <el-dropdown-item command="delete" divided>删除</el-dropdown-item>
                </el-dropdown-menu>
              </template>
            </el-dropdown>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <el-dialog
      v-model="dialogVisible"
      :title="form.id ? '编辑会员等级' : '新增会员等级'"
      width="720px"
      destroy-on-close
      @closed="resetValidate"
    >
      <div class="dialog-intro">
        <strong>{{ form.id ? '更新等级规则' : '创建新的会员等级' }}</strong>
        <p>累计销售额达到门槛后，系统将自动命中对应等级并使用该返利比例。</p>
      </div>

      <el-form
        ref="formRef"
        :model="form"
        :rules="formRules"
        label-position="top"
      >
        <div class="dialog-grid">
          <el-form-item label="等级名称" prop="name">
            <el-input v-model="form.name" maxlength="50" placeholder="例如：黄金会员" />
          </el-form-item>

          <el-form-item label="等级编码">
            <el-input v-model="form.code" maxlength="30" placeholder="例如：gold" />
          </el-form-item>

          <el-form-item label="累计销售额下限" prop="sales_amount_min">
            <el-input-number
              v-model="form.sales_amount_min"
              :min="0"
              :max="999999999"
              :precision="2"
              controls-position="right"
            />
          </el-form-item>

          <el-form-item label="累计销售额上限">
            <el-input
              v-model="form.sales_amount_max"
              placeholder="留空表示不封顶"
            />
          </el-form-item>

          <el-form-item label="返利比例（%）" prop="reward_rate">
            <el-input-number
              v-model="form.reward_rate"
              :min="0"
              :max="100"
              :precision="2"
              controls-position="right"
            />
          </el-form-item>

          <el-form-item label="排序值">
            <el-input-number
              v-model="form.sort_order"
              :min="0"
              :max="999999"
              controls-position="right"
            />
          </el-form-item>

          <el-form-item label="状态">
            <el-switch
              v-model="form.status"
              :active-value="1"
              :inactive-value="0"
              active-text="启用"
              inactive-text="停用"
            />
          </el-form-item>

          <el-form-item class="dialog-span-2" label="备注">
            <el-input
              v-model="form.remark"
              type="textarea"
              :rows="3"
              maxlength="255"
              show-word-limit
              placeholder="用于后台备注，例如适用人群或升级说明"
            />
          </el-form-item>
        </div>
      </el-form>

      <template #footer>
        <div class="dialog-footer">
          <el-button @click="dialogVisible = false">取消</el-button>
          <el-button type="primary" :loading="saving" @click="submitForm">保存</el-button>
        </div>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import adminApi from '@/api/admin'
import { formatDateTime } from '@/utils/datetime'
import { useResponsive } from '@/composables/useResponsive'

const { isMobile } = useResponsive()

const loading = ref(false)
const saving = ref(false)
const dialogVisible = ref(false)
const formRef = ref(null)
const levels = ref([])

const createDefaultForm = () => ({
  id: null,
  name: '',
  code: '',
  sales_amount_min: 0,
  sales_amount_max: '',
  reward_rate: 0,
  status: 1,
  sort_order: 0,
  remark: '',
})

const form = reactive(createDefaultForm())

const formRules = {
  name: [{ required: true, message: '请输入等级名称', trigger: 'blur' }],
  sales_amount_min: [{ required: true, message: '请输入累计销售额下限', trigger: 'change' }],
  reward_rate: [{ required: true, message: '请输入返利比例', trigger: 'change' }],
}

function formatCount(value) {
  return Number(value || 0).toLocaleString('zh-CN')
}

function formatCurrency(value) {
  return `¥${Number(value || 0).toFixed(2)}`
}

function formatPercent(value) {
  return `${Number(value || 0).toFixed(2)}%`
}

function resetForm() {
  Object.assign(form, createDefaultForm())
}

function resetValidate() {
  formRef.value?.clearValidate?.()
}

function openCreateDialog() {
  resetForm()
  dialogVisible.value = true
}

function handleLevelAction(command, row) {
  if (command === 'edit') {
    openEditDialog(row)
  } else if (command === 'delete') {
    handleDelete(row)
  }
}

function openEditDialog(row) {
  form.id = Number(row.id)
  form.name = row.name || ''
  form.code = row.code || ''
  form.sales_amount_min = Number(row.sales_amount_min || 0)
  form.sales_amount_max = row.sales_amount_max || ''
  form.reward_rate = Number(row.reward_rate || 0)
  form.status = Number(row.status ?? 1)
  form.sort_order = Number(row.sort_order || 0)
  form.remark = row.remark || ''
  dialogVisible.value = true
}

function buildPayload() {
  const salesAmountMax = String(form.sales_amount_max || '').trim()

  if (salesAmountMax !== '' && Number.isNaN(Number(salesAmountMax))) {
    throw new Error('累计销售额上限必须是有效数字')
  }

  return {
    name: form.name.trim(),
    code: form.code.trim() || null,
    sales_amount_min: Number(form.sales_amount_min || 0),
    sales_amount_max: salesAmountMax === '' ? null : Number(salesAmountMax),
    reward_rate: Number(form.reward_rate || 0),
    status: Number(form.status ?? 1),
    sort_order: Number(form.sort_order || 0),
    remark: form.remark.trim() || null,
  }
}

async function loadLevels() {
  loading.value = true
  try {
    const res = await adminApi.memberLevels.list()
    levels.value = res.data || []
  } catch {
    levels.value = []
  } finally {
    loading.value = false
  }
}

async function submitForm() {
  const valid = await formRef.value?.validate?.().catch(() => false)
  if (valid === false) {
    return
  }

  saving.value = true
  try {
    const payload = buildPayload()
    if (form.id) {
      await adminApi.memberLevels.update(form.id, payload)
      ElMessage.success('会员等级已更新')
    } else {
      await adminApi.memberLevels.create(payload)
      ElMessage.success('会员等级已创建')
    }

    dialogVisible.value = false
    await loadLevels()
  } finally {
    saving.value = false
  }
}

async function handleDelete(row) {
  try {
    await ElMessageBox.confirm(`确认删除等级“${row.name}”吗？`, '删除会员等级', {
      type: 'warning',
      confirmButtonText: '确认删除',
      cancelButtonText: '取消',
    })
  } catch {
    return
  }

  await adminApi.memberLevels.delete(row.id)
  ElMessage.success('会员等级已删除')
  await loadLevels()
}

onMounted(loadLevels)
</script>

<style scoped lang="scss">
.page-actions,
.panel-header,
.dialog-footer,
.table-actions {
  display: flex;
  gap: 12px;
  align-items: center;
}

.page-actions,
.dialog-footer {
  justify-content: flex-end;
}

.panel-header {
  justify-content: space-between;
}

.panel-header-meta {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.panel-header-meta strong,
.dialog-intro strong,
.level-main strong,
.range-cell strong {
  color: $text-color-primary;
  font-size: 15px;
  font-weight: 600;
}

.panel-header-meta span,
.dialog-intro p,
.level-main span,
.level-main small,
.range-cell span,
.remark-text {
  color: $text-color-secondary;
  font-size: 12px;
  line-height: 1.6;
}

.level-main,
.range-cell {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.remark-text {
  display: inline-block;
}

.panel-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 8px;
  min-height: 220px;
}

.panel-empty strong {
  color: $text-color-primary;
  font-size: 15px;
  font-weight: 600;
}

.panel-empty p {
  color: $text-color-secondary;
  font-size: 12px;
}

.dialog-intro {
  margin-bottom: 16px;
  padding: 14px 16px;
  border: 1px solid $divider-color;
  border-radius: 12px;
  background: $bg-color-soft;
}

.dialog-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 4px 12px;
}

.dialog-span-2 {
  grid-column: span 2;
}

.member-levels-page :deep(.el-input-number) {
  width: 100%;
}

.member-levels-page :deep(.el-input-number .el-input__wrapper) {
  width: 100%;
}

@media (max-width: 900px) {
  .page-actions,
  .panel-header,
  .dialog-footer {
    flex-direction: column;
    align-items: stretch;
  }

  .dialog-grid {
    grid-template-columns: 1fr;
  }

  .dialog-span-2 {
    grid-column: span 1;
  }
}

</style>
