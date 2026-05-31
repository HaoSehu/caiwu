<template>
  <div class="page-container users-page admin-page">
    <section class="admin-page-head">
      <div class="admin-page-heading">
        <span class="admin-page-kicker">用户中心</span>
        <h2>用户管理</h2>
        <p>维护用户资料、账户状态与充值操作。</p>
      </div>

      <div class="users-head-side">
        <div class="users-head-buttons">
          <el-button type="primary" :icon="Plus" @click="openDialog()">新增用户</el-button>
        </div>
      </div>
    </section>

    <section class="filter-panel">
      <div class="search-bar users-search-bar">
      <el-input
        v-model="filters.keyword"
        placeholder="搜索邮箱/昵称/手机号"
        clearable
        class="users-search-input"
        @keyup.enter="loadList"
      >
        <template #prefix><el-icon><Search /></el-icon></template>
      </el-input>
      <el-select v-model="filters.status" placeholder="状态" clearable class="users-search-select">
        <el-option label="正常" :value="1" />
        <el-option label="禁用" :value="0" />
      </el-select>
      </div>
    </section>

    <el-card shadow="never" class="users-table-card">
      <el-table :data="list" v-loading="loading" stripe row-key="id">
        <el-table-column prop="id" label="ID" width="70" />
        <el-table-column label="手机号 / 邮箱" min-width="220">
          <template #default="{ row }">
            <span class="account-cell account-cell--link" @click="openDetail(row.id)">{{ row.phone || row.email || '-' }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="nickname" label="昵称" min-width="180">
          <template #default="{ row }">
            <span class="nickname-cell">
              <el-icon v-if="Number(row.verification_status) === 2 || Number(row.is_verified) === 1" class="verified-shield">
                <SuccessFilled />
              </el-icon>
              <span>{{ row.display_name || row.nickname || '-' }}</span>
            </span>
          </template>
        </el-table-column>
        <el-table-column prop="balance" label="余额" min-width="110">
          <template #default="{ row }">
            <span class="balance-text">¥{{ Number(row.balance || 0).toFixed(2) }}</span>
          </template>
        </el-table-column>
        <el-table-column label="已开通服务" width="120" align="center">
          <template #default="{ row }">
            <span :class="['opened-services-text', { 'opened-services-text--empty': !row.opened_product_count }]">
              {{ row.opened_product_count ? `${row.opened_product_count} 个` : '-' }}
            </span>
          </template>
        </el-table-column>
        <el-table-column prop="status" label="状态" width="80">
          <template #default="{ row }">
            <el-tag :type="row.status === 1 ? 'success' : 'danger'" size="small">
              {{ row.status === 1 ? '正常' : '禁用' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="注册时间" min-width="160">
          <template #default="{ row }">{{ formatDateTime(row.created_at) }}</template>
        </el-table-column>
        <el-table-column label="操作" width="80" fixed="right">
          <template #default="{ row }">
            <div class="action-toolbar">
              <el-dropdown trigger="click" @command="(command) => handleAction(command, row)">
                <span class="action-link">
                  更多<el-icon class="el-icon--right"><ArrowDown /></el-icon>
                </span>
                <template #dropdown>
                  <el-dropdown-menu>
                    <el-dropdown-item :command="row.status === 1 ? 'disable' : 'enable'">
                      {{ row.status === 1 ? '禁用账号' : '启用账号' }}
                    </el-dropdown-item>
                    <el-dropdown-item command="recharge">资金管理</el-dropdown-item>
                    <el-dropdown-item command="delete" divided>删除用户</el-dropdown-item>
                  </el-dropdown-menu>
                </template>
              </el-dropdown>
            </div>
          </template>
        </el-table-column>
      </el-table>

      <!-- 分页 -->
      <div class="table-pagination">
        <el-pagination
          v-model:current-page="page"
          v-model:page-size="pageSize"
          :total="total"
          :page-sizes="[20, 50, 100]"
          layout="total, sizes, prev, pager, next"
          @size-change="loadList"
          @current-change="loadList"
        />
      </div>
    </el-card>

    <!-- 新增弹窗 -->
    <el-dialog
      v-model="dialogVisible"
      title="新增用户"
      width="500px"
      destroy-on-close
    >
      <el-form ref="editFormRef" :model="editForm" :rules="editRules" label-width="80px">
        <el-form-item label="邮箱" prop="email">
          <el-input v-model="editForm.email" />
        </el-form-item>
        <el-form-item label="昵称" prop="nickname">
          <el-input v-model="editForm.nickname" />
        </el-form-item>
        <el-form-item label="手机号" prop="phone">
          <el-input v-model="editForm.phone" />
        </el-form-item>
        <el-form-item label="密码" prop="password">
          <el-input v-model="editForm.password" type="password" show-password />
        </el-form-item>
        <el-form-item label="信用额度" prop="credit_limit">
          <el-input-number v-model="editForm.credit_limit" :min="0" :precision="2" style="width:100%;" />
        </el-form-item>
      </el-form>

      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="submitLoading" @click="handleSubmit">确定</el-button>
      </template>
    </el-dialog>

    <!-- 资金管理弹窗 -->
    <el-dialog v-model="rechargeVisible" title="资金管理" width="440px" destroy-on-close>
      <el-form ref="rechargeFormRef" :model="rechargeForm" :rules="rechargeRules" label-width="80px">
        <el-form-item label="用户">
          <el-input :value="rechargeForm.email" disabled />
        </el-form-item>
        <el-form-item label="操作类型">
          <el-radio-group v-model="rechargeForm.type">
            <el-radio value="increase">增加余额</el-radio>
            <el-radio value="decrease">扣减余额</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="金额" prop="amount">
          <el-input-number v-model="rechargeForm.amount" :min="0.01" :max="999999" :precision="2" style="width:100%;" />
        </el-form-item>
        <el-form-item label="备注" prop="remark">
          <el-input v-model="rechargeForm.remark" placeholder="请填写操作原因" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="rechargeVisible = false">取消</el-button>
        <el-button :type="rechargeForm.type === 'decrease' ? 'danger' : 'primary'" :loading="submitLoading" @click="handleRecharge">
          {{ rechargeForm.type === 'decrease' ? '确认扣减' : '确认增加' }}
        </el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { ArrowDown, Plus, Search, SuccessFilled } from '@element-plus/icons-vue'
import userApi from '@/api/user'
import { formatDateTime } from '@/utils/datetime'
import { ElMessage, ElMessageBox } from 'element-plus'

const router = useRouter()

// ========== 列表 ==========
const loading = ref(false)
const list = ref([])
const total = ref(0)
const page = ref(1)
const pageSize = ref(20)
const filters = reactive({ keyword: '', status: '' })

async function loadList() {
  loading.value = true
  try {
    const res = await userApi.list({ ...filters, page: page.value, page_size: pageSize.value })
    list.value = res.data.list
    total.value = res.data.total
  } catch {
    // 请求层已统一提示，这里只消费异常，避免页面事件出现未处理 Promise。
  } finally { loading.value = false }
}

function resetFilters() {
  filters.keyword = ''
  filters.status = ''
  page.value = 1
  loadList()
}

// ========== 新增 ==========
const dialogVisible = ref(false)
const editFormRef = ref()
const submitLoading = ref(false)

const editForm = reactive({ email: '', nickname: '', phone: '', password: '', credit_limit: 0 })

const editRules = {
  email: [{ required: true, type: 'email', message: '请输入有效邮箱', trigger: 'blur' }],
  password: [{ required: true, message: '请输入密码', trigger: 'blur' }],
}

function openDialog() {
  Object.assign(editForm, { email: '', nickname: '', phone: '', password: '', credit_limit: 0 })
  dialogVisible.value = true
}

function openDetail(id) {
  router.push(`/admin/users/${id}`)
}

async function handleSubmit() {
  try { await editFormRef.value?.validate() } catch { return }
  submitLoading.value = true
  try {
    await userApi.create(editForm)
    ElMessage.success('创建成功')
    dialogVisible.value = false
    loadList()
  } catch {
    // 请求层已统一提示，这里避免未处理 Promise 冒到控制台。
  } finally { submitLoading.value = false }
}

// ========== 状态切换 ==========
async function handleToggleStatus(row) {
  try {
    await userApi.toggleStatus(row.id)
    ElMessage.success('操作成功')
    loadList()
  } catch {
    // 请求层已统一提示，这里避免未处理 Promise 冒到控制台。
  }
}

async function handleAction(command, row) {
  if (command === 'recharge') {
    openRechargeDialog(row)
    return
  }

  if (command === 'enable' || command === 'disable') {
    await handleToggleStatus(row)
    return
  }

  if (command === 'delete') {
      try {
      await ElMessageBox.confirm(
        `确认删除用户“${row.display_name || row.nickname || row.email}”吗？此操作不可恢复。`,
        '删除确认',
        {
          confirmButtonText: '确认删除',
          cancelButtonText: '取消',
          type: 'warning',
        }
      )
    } catch {
      return
    }
    await handleDelete(row.id)
  }
}

// ========== 删除 ==========
async function handleDelete(id) {
  try {
    await userApi.delete(id)
    ElMessage.success('删除成功')
    loadList()
  } catch {
    // 请求层已统一提示，这里避免未处理 Promise 冒到控制台。
  }
}

// ========== 资金管理 ==========
const rechargeVisible = ref(false)
const rechargeFormRef = ref()
const rechargeForm = reactive({ userId: null, email: '', type: 'increase', amount: 100, remark: '' })
const rechargeRules = {
  amount: [{ required: true, message: '请输入金额', trigger: 'blur' }],
  remark: [{ required: true, message: '请填写操作备注', trigger: 'blur' }],
}

function openRechargeDialog(row) {
  rechargeForm.userId = row.id
  rechargeForm.email = row.email || row.phone || '-'
  rechargeForm.type = 'increase'
  rechargeForm.amount = 100
  rechargeForm.remark = ''
  rechargeVisible.value = true
}

async function handleRecharge() {
  try { await rechargeFormRef.value?.validate() } catch { return }
  const signedAmount = rechargeForm.type === 'decrease' ? -rechargeForm.amount : rechargeForm.amount
  submitLoading.value = true
  try {
    await userApi.recharge(rechargeForm.userId, { amount: signedAmount, remark: rechargeForm.remark })
    ElMessage.success(rechargeForm.type === 'decrease' ? '扣减成功' : '增加成功')
    rechargeVisible.value = false
    loadList()
  } catch {
    // 请求层已统一提示，这里避免未处理 Promise 冒到控制台。
  } finally { submitLoading.value = false }
}

onMounted(loadList)
</script>

<style scoped lang="scss">
.users-head-side {
  display: flex;
  flex-direction: column;
  gap: 12px;
  width: min(100%, 640px);
}

.users-head-buttons {
  display: flex;
  justify-content: flex-end;
}

.filter-panel {
  padding: 16px;
  border: 1px solid $border-color;
  border-radius: $base-border-radius;
  background: $bg-color-card;
}

.users-search-bar {
  align-items: center;
  margin: 0;

  // 搜索输入框占满可用宽度
  .users-search-input {
    flex: 1 1 220px;
    min-width: 160px;
  }

  // 状态下拉框
  .users-search-select {
    flex: 0 1 130px;
    min-width: 100px;
  }

  // 按钮不换行
  .el-button {
    flex-shrink: 0;
    white-space: nowrap;
  }
}

// 窄屏下优化
@include tablet-and-below {
  .users-search-bar {
    flex-direction: row !important;
    align-items: center !important;

    .users-search-input {
      flex: 1 1 100%;
      min-width: 0;
    }
    .users-search-select {
      flex: 1 1 auto;
      min-width: 100px;
      width: auto !important;
    }
    .el-button {
      flex-shrink: 0;
    }
  }
}

.users-table-card {
  overflow: hidden;
}

.account-cell {
  color: $text-color-primary;
  font-weight: 500;
}

.account-cell--link {
  cursor: pointer;
  transition: color 0.15s ease-out;

  &:hover {
    color: $color-primary;
  }
}

.opened-services-text {
  color: $text-color-secondary;
  font-weight: 500;
}

.opened-services-text--empty {
  color: $text-color-placeholder;
}

.balance-text {
  color: $color-success;
  font-weight: 600;
}

.nickname-cell {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  color: $text-color-primary;
  font-weight: 500;
}

.verified-shield {
  color: $color-success;
  font-size: 14px;
}

.action-toolbar {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  flex-wrap: nowrap;
  white-space: nowrap;
}

.action-toolbar :deep(.el-button) {
  margin-left: 0;
  min-height: auto;
}

.action-link {
  display: inline-flex;
  align-items: center;
  cursor: pointer;
  color: var(--el-color-primary);
  font-size: 13px;
  white-space: nowrap;
  outline: none;

  &:hover {
    opacity: 0.8;
  }
}

.table-pagination {
  display: flex;
  justify-content: flex-end;
  margin-top: 16px;
}

@media (max-width: 900px) {
  .users-head-side,
  .users-head-buttons {
    width: 100%;
  }

  .users-head-buttons {
    justify-content: flex-start;
  }
}
</style>
