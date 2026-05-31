<template>
  <div class="page-container admin-page coupon-campaign-page" v-loading="loading">
    <section class="admin-page-head">
      <div class="admin-page-heading">
        <span class="admin-page-kicker">运营</span>
        <h2>优惠券活动</h2>
        <p>配置按星期和时间自动发放的优惠券活动，例如每周五 18:00 自动生成一批“周五特惠”公开优惠券。</p>
      </div>

      <div class="campaign-head-side">
        <div class="page-actions">
          <el-button @click="loadData">刷新</el-button>
          <el-button type="primary" @click="openCreateDialog">新增活动</el-button>
        </div>
      </div>
    </section>

    <section class="filter-panel">
      <div class="search-bar campaign-search-bar">
        <el-input
          v-model="filters.keyword"
          placeholder="搜索活动名称 / 描述 / 备注"
          clearable
          style="width: 280px"
          @keyup.enter="handleSearch"
        >
          <template #prefix><el-icon><Search /></el-icon></template>
        </el-input>

        <el-select v-model="filters.status" placeholder="状态" clearable style="width: 140px">
          <el-option label="全部状态" value="" />
          <el-option label="运行中" value="1" />
          <el-option label="已停用" value="0" />
        </el-select>


      </div>
    </section>

    <el-card shadow="never" class="campaign-table-card">
      <el-table :data="list" stripe>
        <el-table-column prop="id" label="ID" width="72" />

        <el-table-column label="活动信息" min-width="260">
          <template #default="{ row }">
            <div class="campaign-main">
              <div class="campaign-title-row">
                <strong>{{ row.name }}</strong>
                <el-tag size="small" effect="plain">自动发放</el-tag>
              </div>
              <p class="campaign-desc">{{ row.description || '暂无描述' }}</p>
            </div>
          </template>
        </el-table-column>

        <el-table-column label="调度规则" min-width="220">
          <template #default="{ row }">
            <div class="campaign-meta">
              <strong>{{ row.schedule_text }}</strong>
              <span>下次执行：{{ row.next_run_at || '未配置' }}</span>
              <span>{{ row.valid_duration_hours ? `生成后 ${row.valid_duration_hours} 小时失效` : '生成后长期有效' }}</span>
            </div>
          </template>
        </el-table-column>

        <el-table-column label="优惠规则" min-width="220">
          <template #default="{ row }">
            <div class="campaign-meta">
              <div class="campaign-meta-top">
                <el-tag size="small" effect="plain" :type="row.discount_type === 'fixed' ? 'danger' : 'warning'">
                  {{ row.discount_type_label }}
                </el-tag>
                <el-tag size="small" effect="plain" type="info">
                  {{ row.discount_scope_label }}
                </el-tag>
                <strong>{{ row.discount_label }}</strong>
              </div>
              <span>发放数量：{{ row.issue_quantity }} 张</span>
              <span>最低消费：¥{{ row.min_amount }}</span>
              <span>{{ row.per_user_limit ? `每人可用 ${row.per_user_limit} 次` : '每人不限使用次数' }}</span>
            </div>
          </template>
        </el-table-column>

        <el-table-column label="适用范围" min-width="220" show-overflow-tooltip>
          <template #default="{ row }">
            <div class="campaign-meta">
              <span>{{ row.product_scope_text }}</span>
              <span>{{ row.billing_cycle_text }}</span>
              <span v-if="row.first_order_only" class="meta-highlight">仅首单可用</span>
            </div>
          </template>
        </el-table-column>

        <el-table-column label="最近发放" min-width="220">
          <template #default="{ row }">
            <div class="campaign-meta">
              <span>{{ row.last_dispatched_at || '暂未发放' }}</span>
              <span v-if="row.last_coupon_name">最近批次：{{ row.last_coupon_name }}</span>
              <span v-if="row.last_coupon_code">券码：{{ row.last_coupon_code }}</span>
              <span>累计生成 {{ row.generated_coupon_count || 0 }} 批</span>
            </div>
          </template>
        </el-table-column>

        <el-table-column label="状态" width="110">
          <template #default="{ row }">
            <el-tag size="small" :type="row.display_status === 'active' ? 'success' : 'info'">
              {{ row.display_status_label }}
            </el-tag>
          </template>
        </el-table-column>

        <el-table-column label="更新时间" min-width="170">
          <template #default="{ row }">{{ formatDateTime(row.updated_at) }}</template>
        </el-table-column>

        <el-table-column label="操作" :width="isMobile ? 60 : 190" fixed="right">
          <template #default="{ row }">
            <div v-if="!isMobile" class="table-actions">
              <el-button size="small" text type="primary" :disabled="isRowActionBusy(row.id)" @click="openEditDialog(row)">编辑</el-button>
              <el-button size="small" text type="success" :loading="isRowActionRunning(row.id, 'trigger')" :disabled="Number(row.status) !== 1 || (isRowActionBusy(row.id) && !isRowActionRunning(row.id, 'trigger'))" @click="handleTrigger(row)">立即发放</el-button>
              <el-button size="small" text :loading="isRowActionRunning(row.id, 'toggle')" :disabled="isRowActionBusy(row.id) && !isRowActionRunning(row.id, 'toggle')" @click="handleToggleStatus(row)">
                {{ Number(row.status) === 1 ? '停用' : '启用' }}
              </el-button>
              <el-button size="small" text type="danger" :loading="isRowActionRunning(row.id, 'delete')" :disabled="isRowActionBusy(row.id) && !isRowActionRunning(row.id, 'delete')" @click="handleDelete(row)">删除</el-button>
            </div>
            <el-dropdown v-else trigger="click" @command="(cmd) => handleCampaignAction(cmd, row)">
              <span class="action-link">···</span>
              <template #dropdown>
                <el-dropdown-menu>
                  <el-dropdown-item command="edit">编辑</el-dropdown-item>
                  <el-dropdown-item command="trigger">立即发放</el-dropdown-item>
                  <el-dropdown-item command="toggle">{{ Number(row.status) === 1 ? '停用' : '启用' }}</el-dropdown-item>
                  <el-dropdown-item command="delete" divided>删除</el-dropdown-item>
                </el-dropdown-menu>
              </template>
            </el-dropdown>
          </template>
        </el-table-column>
      </el-table>

      <div class="table-pagination">
        <el-pagination
          v-model:current-page="page"
          v-model:page-size="pageSize"
          :page-sizes="[20, 50, 100]"
          :total="total"
          layout="total, sizes, prev, pager, next"
          @size-change="loadList"
          @current-change="loadList"
        />
      </div>
    </el-card>

    <el-drawer
      v-model="dialogVisible"
      size="min(1280px, 92vw)"
      direction="rtl"
      destroy-on-close
      class="campaign-edit-drawer"
      @closed="resetValidate"
    >
      <template #header>
        <div class="drawer-header">
          <strong>{{ form.id ? '编辑优惠券活动' : '新增优惠券活动' }}</strong>
          <span>{{ form.id ? '更新自动发放规则与优惠参数' : '创建一个可定期自动发放优惠券的营销活动' }}</span>
        </div>
      </template>

      <div class="drawer-body">
        <div class="drawer-content">
          <div class="dialog-intro dialog-intro--plain">
            <strong>配置说明</strong>
            <p>折扣券按百分比填写，例如 `80` 表示 8 折。每次任务执行时会自动生成一张新的公开优惠券批次，发放数量即该批次可领取上限。</p>
          </div>

          <el-form ref="formRef" :model="form" :rules="formRules" label-position="top">
            <div class="dialog-grid">
              <el-form-item label="活动名称" prop="name">
                <el-input v-model="form.name" class="field-md" maxlength="120" placeholder="例如：周五特惠" />
              </el-form-item>

              <el-form-item label="发放时间" prop="trigger_time">
                <el-time-picker
                  v-model="form.trigger_time"
                  class="field-sm"
                  format="HH:mm"
                  value-format="HH:mm:ss"
                  placeholder="选择发放时间"
                />
              </el-form-item>

              <el-form-item label="每批发放数量" prop="issue_quantity">
                <el-input-number
                  v-model="form.issue_quantity"
                  class="field-number-sm"
                  :min="1"
                  :max="999999"
                  controls-position="right"
                />
              </el-form-item>

              <el-form-item label="优惠类型" prop="discount_type">
                <el-select v-model="form.discount_type" class="field-sm">
                  <el-option label="满减券" value="fixed" />
                  <el-option label="折扣券" value="percentage" />
                </el-select>
              </el-form-item>

              <el-form-item label="优惠阶段" prop="discount_scope">
                <el-select v-model="form.discount_scope" class="field-sm">
                  <el-option label="首月优惠" value="first_month" />
                  <el-option label="持续优惠" value="recurring" />
                  <el-option label="续费优惠" value="renew" />
                </el-select>
              </el-form-item>

              <el-form-item :label="form.discount_type === 'percentage' ? '优惠值（百分比）' : '优惠金额'" prop="discount_value">
                <div class="discount-input-wrap">
                  <el-input-number
                    v-model="form.discount_value"
                    class="field-number-sm"
                    :min="0"
                    :max="form.discount_type === 'percentage' ? 100 : 999999999"
                    :precision="2"
                    controls-position="right"
                  />
                  <span class="discount-input-suffix">{{ form.discount_type === 'percentage' ? '%' : '元' }}</span>
                </div>
              </el-form-item>

              <el-form-item label="最低消费金额">
                <el-input-number
                  v-model="form.min_amount"
                  class="field-number-md"
                  :min="0"
                  :max="999999999"
                  :precision="2"
                  controls-position="right"
                />
              </el-form-item>

              <el-form-item label="最高优惠金额">
                <el-input-number
                  v-model="form.max_discount_amount"
                  class="field-number-md"
                  :min="0"
                  :max="999999999"
                  :precision="2"
                  controls-position="right"
                />
              </el-form-item>

              <el-form-item label="有效时长（小时）">
                <el-input-number
                  v-model="form.valid_duration_hours"
                  class="field-number-sm"
                  :min="1"
                  :max="87600"
                  controls-position="right"
                />
              </el-form-item>

              <el-form-item label="每人可用次数">
                <el-input-number
                  v-model="form.per_user_limit"
                  class="field-number-sm"
                  :min="1"
                  :max="999999"
                  controls-position="right"
                />
              </el-form-item>

              <el-form-item label="排序值">
                <el-input-number
                  v-model="form.sort_order"
                  class="field-number-sm"
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

              <el-form-item label="仅限首单可用">
                <el-switch v-model="form.first_order_only" />
              </el-form-item>

              <el-form-item label="发放星期" prop="weekdays" class="dialog-span-2">
                <el-checkbox-group v-model="form.weekdays" class="weekday-group">
                  <el-checkbox-button v-for="item in weekdayOptions" :key="item.value" :value="item.value">
                    {{ item.label }}
                  </el-checkbox-button>
                </el-checkbox-group>
              </el-form-item>

              <el-form-item label="适用计费周期" class="dialog-span-2">
                <el-select
                  v-model="form.billing_cycles"
                  class="field-cycle"
                  multiple
                  collapse-tags
                  collapse-tags-tooltip
                  placeholder="留空表示全部周期可用"
                >
                  <el-option
                    v-for="item in billingCycleOptions"
                    :key="item.value"
                    :label="item.label"
                    :value="item.value"
                  />
                </el-select>
              </el-form-item>

              <el-form-item label="适用商品" class="dialog-span-2">
                <div class="product-tree-panel">
                  <div class="product-tree-toolbar">
                    <span>留空表示全站商品可用。勾选分组时会自动包含该分组下的全部商品。</span>
                    <el-button text type="primary" @click="clearProductSelection">清空选择</el-button>
                  </div>
                  <div class="product-tree-shell" v-loading="productTreeLoading">
                    <el-tree
                      ref="productTreeRef"
                      :data="productTreeData"
                      node-key="id"
                      show-checkbox
                      :expand-on-click-node="false"
                      class="coupon-product-tree"
                      @node-click="handleProductTreeNodeClick"
                    />
                  </div>
                </div>
              </el-form-item>

              <el-form-item label="描述" class="dialog-span-2">
                <el-input
                  v-model="form.description"
                  type="textarea"
                  :rows="3"
                  maxlength="255"
                  show-word-limit
                  placeholder="前台展示给客户的活动说明"
                />
              </el-form-item>

              <el-form-item label="后台备注" class="dialog-span-2">
                <el-input
                  v-model="form.remark"
                  type="textarea"
                  :rows="3"
                  maxlength="255"
                  show-word-limit
                  placeholder="例如：周末活动、节日促销、测试批次说明"
                />
              </el-form-item>
            </div>
          </el-form>
        </div>
      </div>

      <template #footer>
        <div class="drawer-footer">
          <el-button @click="dialogVisible = false">取消</el-button>
          <el-button type="primary" :loading="saving" @click="submitForm">保存</el-button>
        </div>
      </template>
    </el-drawer>
  </div>
</template>

<script setup>
import { nextTick, onMounted, reactive, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Search } from '@element-plus/icons-vue'
import adminApi from '@/api/admin'
import { formatDateTime } from '@/utils/datetime'
import { useResponsive } from '@/composables/useResponsive'

const { isMobile } = useResponsive()

const billingCycleOptions = [
  { label: '月付', value: 'monthly' },
  { label: '季付', value: 'quarterly' },
  { label: '半年付', value: 'semiannually' },
  { label: '年付', value: 'annually' },
]

const weekdayOptions = [
  { label: '周一', value: 1 },
  { label: '周二', value: 2 },
  { label: '周三', value: 3 },
  { label: '周四', value: 4 },
  { label: '周五', value: 5 },
  { label: '周六', value: 6 },
  { label: '周日', value: 0 },
]

const loading = ref(false)
const saving = ref(false)
const dialogVisible = ref(false)
const formRef = ref(null)
const productTreeRef = ref(null)
const productTreeLoading = ref(false)
const productTreeData = ref([])
const list = ref([])
const total = ref(0)
const page = ref(1)
const pageSize = ref(20)
const rowActionState = reactive({})
const filters = reactive({
  keyword: '',
  status: '',
})

const createDefaultForm = () => ({
  id: null,
  name: '',
  weekdays: [5],
  trigger_time: '18:00:00',
  issue_quantity: 20,
  valid_duration_hours: 48,
  discount_type: 'percentage',
  discount_scope: 'first_month',
  discount_value: 80,
  min_amount: 0,
  max_discount_amount: null,
  billing_cycles: [],
  product_ids: [],
  first_order_only: false,
  per_user_limit: 1,
  status: 1,
  sort_order: 0,
  description: '',
  remark: '',
})

const form = reactive(createDefaultForm())

const formRules = {
  name: [{ required: true, message: '请输入活动名称', trigger: 'blur' }],
  weekdays: [{
    validator: (_, value, callback) => {
      if (Array.isArray(value) && value.length > 0) {
        callback()
        return
      }
      callback(new Error('至少选择一个发放星期'))
    },
    trigger: 'change',
  }],
  trigger_time: [{ required: true, message: '请选择发放时间', trigger: 'change' }],
  issue_quantity: [{ required: true, message: '请输入发放数量', trigger: 'change' }],
  discount_type: [{ required: true, message: '请选择优惠类型', trigger: 'change' }],
  discount_scope: [{ required: true, message: '请选择优惠阶段', trigger: 'change' }],
  discount_value: [{
    required: true,
    validator: (_, value, callback) => {
      const numericValue = Number(value || 0)
      if (numericValue <= 0) {
        callback(new Error('优惠值必须大于 0'))
        return
      }
      if (form.discount_type === 'percentage' && numericValue > 100) {
        callback(new Error('折扣值不能大于 100'))
        return
      }
      callback()
    },
    trigger: 'change',
  }],
}

function resetForm() {
  Object.assign(form, createDefaultForm())
}

function resetValidate() {
  formRef.value?.clearValidate?.()
}

async function loadProductTree() {
  productTreeLoading.value = true
  try {
    const res = await adminApi.coupons.productTree()
    productTreeData.value = res.data?.tree || []
  } finally {
    productTreeLoading.value = false
  }
}

function getSelectedProductIds() {
  const checkedKeys = productTreeRef.value?.getCheckedKeys?.(true) || []
  return checkedKeys
    .map((key) => Number(key))
    .filter((id) => Number.isInteger(id) && id > 0)
}

async function applyCheckedProductIds(productIds = []) {
  await nextTick()
  productTreeRef.value?.setCheckedKeys?.([])
  if (productIds.length) {
    productTreeRef.value?.setCheckedKeys?.(productIds)
  }
}

function clearProductSelection() {
  productTreeRef.value?.setCheckedKeys?.([])
}

function isRowActionRunning(rowId, action) {
  return rowActionState[Number(rowId || 0)] === action
}

function isRowActionBusy(rowId) {
  return Boolean(rowActionState[Number(rowId || 0)])
}

async function runRowAction(row, action, fallbackMessage, task) {
  const rowId = Number(row?.id || 0)
  if (rowId <= 0 || isRowActionBusy(rowId)) {
    return
  }

  rowActionState[rowId] = action

  try {
    await task()
  } catch (error) {
    if (!error?.response) {
      ElMessage.error(error?.message || fallbackMessage)
    }
  } finally {
    if (rowActionState[rowId] === action) {
      delete rowActionState[rowId]
    }
  }
}

function handleProductTreeNodeClick(data) {
  if (data?.node_type !== 'group') return
  const treeNode = productTreeRef.value?.getNode?.(data.id)
  if (!treeNode) return
  if (treeNode.expanded) {
    treeNode.collapse?.()
    return
  }
  treeNode.expand?.()
}

async function loadList() {
  loading.value = true
  try {
    const res = await adminApi.couponCampaigns.list({
      ...filters,
      page: page.value,
      page_size: pageSize.value,
    })
    list.value = res.data?.list || []
    total.value = res.data?.total || 0
  } finally {
    loading.value = false
  }
}

async function loadData() {
  await loadList()
}

function handleSearch() {
  page.value = 1
  loadData()
}

function resetFilters() {
  filters.keyword = ''
  filters.status = ''
  page.value = 1
  loadData()
}

async function openCreateDialog() {
  resetForm()
  dialogVisible.value = true
  if (!productTreeData.value.length) {
    await loadProductTree()
  }
  await applyCheckedProductIds([])
}

function handleCampaignAction(command, row) {
  if (command === 'edit') {
    openEditDialog(row)
  } else if (command === 'trigger') {
    handleTrigger(row)
  } else if (command === 'toggle') {
    handleToggleStatus(row)
  } else if (command === 'delete') {
    handleDelete(row)
  }
}

async function openEditDialog(row) {
  resetForm()
  form.id = Number(row.id)
  form.name = row.name || ''
  form.weekdays = Array.isArray(row.weekdays) ? [...row.weekdays] : [5]
  form.trigger_time = row.trigger_time || '18:00:00'
  form.issue_quantity = Number(row.issue_quantity || 1)
  form.valid_duration_hours = row.valid_duration_hours === null ? null : Number(row.valid_duration_hours || 0)
  form.discount_type = row.discount_type || 'fixed'
  form.discount_scope = row.discount_scope || 'first_month'
  form.discount_value = Number(row.discount_value_raw || 0)
  form.min_amount = Number(row.min_amount_raw || 0)
  form.max_discount_amount = row.max_discount_amount_raw === null ? null : Number(row.max_discount_amount_raw || 0)
  form.billing_cycles = Array.isArray(row.billing_cycles) ? [...row.billing_cycles] : []
  form.product_ids = Array.isArray(row.product_ids) ? row.product_ids.map((id) => Number(id)) : []
  form.first_order_only = Boolean(row.first_order_only)
  form.per_user_limit = row.per_user_limit === null ? null : Number(row.per_user_limit || 0)
  form.status = Number(row.status ?? 1)
  form.sort_order = Number(row.sort_order || 0)
  form.description = row.description || ''
  form.remark = row.remark || ''

  dialogVisible.value = true
  if (!productTreeData.value.length) {
    await loadProductTree()
  }
  await applyCheckedProductIds(form.product_ids)
}

function buildPayload() {
  return {
    name: form.name.trim(),
    description: form.description.trim() || null,
    weekdays: [...form.weekdays],
    trigger_time: form.trigger_time || null,
    issue_quantity: Number(form.issue_quantity || 0),
    valid_duration_hours: form.valid_duration_hours === null || form.valid_duration_hours === ''
      ? null
      : Number(form.valid_duration_hours || 0),
    discount_type: form.discount_type,
    discount_scope: form.discount_scope,
    discount_value: Number(form.discount_value || 0),
    min_amount: Number(form.min_amount || 0),
    max_discount_amount: form.max_discount_amount === null || form.max_discount_amount === ''
      ? null
      : Number(form.max_discount_amount || 0),
    billing_cycles: form.billing_cycles,
    product_ids: getSelectedProductIds(),
    first_order_only: Boolean(form.first_order_only),
    per_user_limit: form.per_user_limit === null || form.per_user_limit === ''
      ? null
      : Number(form.per_user_limit || 0),
    status: Number(form.status ?? 1),
    sort_order: Number(form.sort_order || 0),
    remark: form.remark.trim() || null,
  }
}

async function submitForm() {
  const valid = await formRef.value?.validate?.().catch(() => false)
  if (valid === false) return

  saving.value = true
  try {
    const payload = buildPayload()
    if (form.id) {
      await adminApi.couponCampaigns.update(form.id, payload)
      ElMessage.success('活动已更新')
    } else {
      await adminApi.couponCampaigns.create(payload)
      ElMessage.success('活动已创建')
    }

    dialogVisible.value = false
    await loadData()
  } finally {
    saving.value = false
  }
}

async function handleTrigger(row) {
  try {
    await ElMessageBox.confirm(
      `确认立即发放活动“${row.name}”的新一批优惠券吗？`,
      '立即发放',
      {
        type: 'warning',
        confirmButtonText: '确认发放',
        cancelButtonText: '取消',
      }
    )
  } catch {
    return
  }

  await runRowAction(row, 'trigger', '发放活动批次失败', async () => {
    await adminApi.couponCampaigns.trigger(row.id)
    ElMessage.success('活动批次已发放')
    await loadData()
  })
}

async function handleToggleStatus(row) {
  await runRowAction(row, 'toggle', '更新活动状态失败', async () => {
    await adminApi.couponCampaigns.toggleStatus(row.id)
    ElMessage.success('活动状态已更新')
    await loadData()
  })
}

async function handleDelete(row) {
  try {
    await ElMessageBox.confirm(
      `确认删除活动“${row.name}”吗？删除后不会影响已经生成的优惠券批次。`,
      '删除活动',
      {
        type: 'warning',
        confirmButtonText: '确认删除',
        cancelButtonText: '取消',
      }
    )
  } catch {
    return
  }

  await runRowAction(row, 'delete', '删除活动失败', async () => {
    await adminApi.couponCampaigns.delete(row.id)
    ElMessage.success('活动已删除')
    await loadData()
  })
}

onMounted(async () => {
  await Promise.all([loadData(), loadProductTree()])
})
</script>

<style scoped lang="scss">
.campaign-head-side {
  display: flex;
  flex-direction: column;
  gap: 12px;
  width: min(100%, 900px);
}

.page-actions,
.drawer-footer,
.table-actions {
  display: flex;
  gap: 12px;
  align-items: center;
}

.page-actions,
.drawer-footer {
  justify-content: flex-end;
}

.filter-panel {
  padding: 16px;
  border: 1px solid $border-color;
  border-radius: $base-border-radius;
  background: $bg-color-card;
}

.campaign-search-bar {
  align-items: center;
  margin: 0;
}

.campaign-table-card {
  overflow: hidden;
}

.campaign-main,
.campaign-meta {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.campaign-title-row,
.campaign-meta-top {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.campaign-title-row strong,
.campaign-meta-top strong,
.dialog-intro strong {
  color: $text-color-primary;
  font-size: 14px;
  font-weight: 600;
}

.campaign-desc,
.campaign-meta span,
.dialog-intro p {
  color: $text-color-secondary;
  font-size: 12px;
  line-height: 1.6;
}

.meta-highlight {
  color: $color-primary !important;
  font-weight: 600;
}

.drawer-header {
  display: flex;
  flex-direction: column;
  gap: 6px;

  strong {
    color: $text-color-primary;
    font-size: 22px;
    font-weight: 600;
    line-height: 1.3;
  }

  span {
    color: $text-color-secondary;
    font-size: 13px;
    line-height: 1.6;
  }
}

.drawer-body {
  height: 100%;
  padding: 8px 0 20px;
  overflow-y: auto;
}

.drawer-content {
  width: min(100%, 980px);
  margin: 0 auto;
}

.dialog-intro {
  margin-bottom: 16px;
  padding: 14px 16px;
  border: 1px solid $divider-color;
  border-radius: 12px;
  background: $bg-color-soft;
}

.dialog-intro--plain {
  padding: 12px 14px;
  border-radius: $sm-border-radius;
  background: $bg-color-card;
}

.dialog-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 14px 18px;
}

.dialog-span-2 {
  grid-column: 1 / -1;
}

.discount-input-wrap {
  display: flex;
  align-items: center;
  gap: 8px;
}

.discount-input-suffix {
  color: $text-color-secondary;
  font-size: 12px;
}

.weekday-group {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.product-tree-panel {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.product-tree-toolbar {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  align-items: center;
  color: $text-color-secondary;
  font-size: 12px;
}

.product-tree-shell {
  min-height: 300px;
  padding: 12px;
  border: 1px solid $border-color;
  border-radius: $base-border-radius;
  background: $bg-color-card;
}

.coupon-product-tree :deep(.el-tree-node__content) {
  height: 34px;
  border-radius: 8px;
}

.field-sm.el-select,
.field-sm.el-time-picker,
.field-sm :deep(.el-input__wrapper),
.field-md.el-input,
.field-md :deep(.el-input__wrapper),
.field-cycle.el-select,
.field-cycle :deep(.el-select__wrapper) {
  width: 100%;
}

.field-number-sm.el-input-number,
.field-number-md.el-input-number {
  width: 100%;
}

@media (max-width: 1200px) {
  .dialog-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 768px) {
  .dialog-grid {
    grid-template-columns: 1fr;
  }

  .campaign-head-side {
    width: 100%;
  }

  .product-tree-toolbar {
    flex-direction: column;
    align-items: flex-start;
  }
}
</style>
