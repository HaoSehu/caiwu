<template>
  <div class="page-container admin-page coupons-page" v-loading="loading">
    <section class="admin-page-head">
      <div class="admin-page-heading">
        <span class="admin-page-kicker">运营</span>
        <h2>优惠券管理</h2>
        <p>管理满减券和折扣券，控制适用商品、使用周期、次数限制和生效时间。</p>
      </div>

      <div class="coupons-head-side">
        <div class="page-actions">
          <el-button @click="loadData">刷新</el-button>
          <el-button type="primary" :disabled="!couponFeatureEnabled" @click="openCreateDialog">新增优惠券</el-button>
        </div>
      </div>
    </section>

    <el-alert
      v-if="!couponFeatureEnabled"
      class="coupon-feature-alert"
      type="warning"
      :closable="false"
      show-icon
      title="当前环境未启用优惠券功能，新增、编辑、启停和删除操作已禁用。"
    />

    <el-card shadow="never" class="panel-card filter-card">
      <el-form :model="filters" class="filter-form coupons-filter-form" @submit.prevent>
        <el-form-item class="coupons-filter-keyword">
          <el-input
            v-model="filters.keyword"
            placeholder="搜索优惠券名称 / 描述"
            clearable
            @keyup.enter="handleSearch"
            @clear="handleSearch"
          >
            <template #prefix><el-icon><Search /></el-icon></template>
          </el-input>
        </el-form-item>

        <div class="coupons-filter-selects">
          <el-select v-model="filters.status" placeholder="状态" clearable @change="handleSearch">
            <el-option label="全部状态" value="" />
            <el-option label="生效中" value="1" />
            <el-option label="已停用" value="0" />
            <el-option label="已过期" value="expired" />
          </el-select>

          <el-select v-model="filters.discount_type" placeholder="类型" clearable @change="handleSearch">
            <el-option label="全部类型" value="" />
            <el-option label="满减券" value="fixed" />
            <el-option label="折扣券" value="percentage" />
          </el-select>

          <el-select v-model="filters.discount_scope" placeholder="优惠阶段" clearable @change="handleSearch">
            <el-option label="全部阶段" value="" />
            <el-option label="首月优惠" value="first_month" />
            <el-option label="持续优惠" value="recurring" />
            <el-option label="续费优惠" value="renew" />
          </el-select>

          <el-select v-model="filters.distribution_type" placeholder="发放方式" clearable @change="handleSearch">
            <el-option label="全部方式" value="" />
            <el-option label="公开优惠券" value="public" />
            <el-option label="私有优惠券" value="private" />
          </el-select>
        </div>
      </el-form>
    </el-card>

    <el-card shadow="never" class="coupons-table-card">
      <el-table :data="list" stripe row-key="id">
        <el-table-column prop="id" label="ID" width="72" />

        <el-table-column label="优惠券信息" min-width="260">
          <template #default="{ row }">
            <div class="coupon-main">
              <div class="coupon-title-row">
                <strong>{{ row.name }}</strong>
                <el-tag size="small" effect="plain">{{ row.distribution_type_label }}</el-tag>
                <el-tag v-if="row.coupon_campaign_name" size="small" type="warning" effect="plain">
                  活动：{{ row.coupon_campaign_name }}
                </el-tag>
              </div>
              <p class="coupon-desc">{{ row.description || '暂无描述' }}</p>
            </div>
          </template>
        </el-table-column>

        <el-table-column label="优惠规则" min-width="220">
          <template #default="{ row }">
            <div class="coupon-meta">
              <div class="coupon-meta-top">
                <el-tag size="small" effect="plain" :type="row.discount_type === 'fixed' ? 'danger' : 'warning'">
                  {{ row.discount_type_label }}
                </el-tag>
                <el-tag size="small" effect="plain" type="info">
                  {{ row.discount_scope_label }}
                </el-tag>
                <strong>{{ row.discount_label }}</strong>
              </div>
              <span>最低消费：¥{{ row.min_amount }}</span>
              <span v-if="row.max_discount_amount">最高优惠：¥{{ row.max_discount_amount }}</span>
            </div>
          </template>
        </el-table-column>

        <el-table-column label="适用范围" min-width="240" show-overflow-tooltip>
          <template #default="{ row }">
            <div class="coupon-meta">
              <span>{{ row.product_scope_text }}</span>
              <span>{{ row.billing_cycle_text }}</span>
              <span v-if="row.first_order_only" class="meta-highlight">仅首单可用</span>
            </div>
          </template>
        </el-table-column>

        <el-table-column label="使用情况" min-width="180">
          <template #default="{ row }">
            <div class="coupon-meta">
              <span>已使用 {{ row.used_count }} 次</span>
              <span>{{ formatLimitText(row.total_usage_limit, row.remaining_stock, '总量') }}</span>
              <span>{{ formatLimitText(row.per_user_limit, null, '每人') }}</span>
            </div>
          </template>
        </el-table-column>

        <el-table-column label="状态" width="110">
          <template #default="{ row }">
            <StatusTag :status-map="COUPON_DISPLAY_STATUS_MAP" :status="row.display_status">
              {{ row.display_status_label }}
            </StatusTag>
          </template>
        </el-table-column>

        <el-table-column label="有效期" min-width="200">
          <template #default="{ row }">
            <div class="coupon-meta">
              <span>{{ row.validity_text }}</span>
              <span>{{ row.display_status_reason }}</span>
            </div>
          </template>
        </el-table-column>

        <el-table-column label="更新时间" min-width="170">
          <template #default="{ row }">{{ formatDateTime(row.updated_at) }}</template>
        </el-table-column>

        <el-table-column label="操作" :width="isMobile ? 60 : 150" fixed="right">
          <template #default="{ row }">
            <div v-if="!isMobile" class="table-actions">
              <el-button size="small" text type="primary" :disabled="!couponFeatureEnabled" @click="openEditDialog(row)">编辑</el-button>
              <el-button size="small" text :disabled="!couponFeatureEnabled" @click="handleToggleStatus(row)">
                {{ Number(row.status) === 1 ? '停用' : '启用' }}
              </el-button>
              <el-button size="small" text type="danger" :disabled="!couponFeatureEnabled || !row.can_delete" @click="handleDelete(row)">删除</el-button>
            </div>
            <el-dropdown v-else trigger="click" @command="(cmd) => handleCouponAction(cmd, row)">
              <span class="action-link">···</span>
              <template #dropdown>
                <el-dropdown-menu>
                  <el-dropdown-item command="edit">编辑</el-dropdown-item>
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
      size="min(1360px, 92vw)"
      direction="rtl"
      destroy-on-close
      class="coupon-edit-drawer"
      @closed="resetValidate"
    >
      <template #header>
        <div class="drawer-header">
          <strong>{{ form.id ? '编辑优惠券' : '新增优惠券' }}</strong>
          <span>{{ form.id ? '更新优惠券规则和发放范围' : '创建新的优惠券并配置发放方式' }}</span>
        </div>
      </template>

      <div class="drawer-body">
        <div class="drawer-content">
          <div class="dialog-intro dialog-intro--plain">
            <strong>{{ form.id ? '更新优惠券规则' : '创建新的优惠券' }}</strong>
            <p>折扣券的“优惠值”按百分比填写，例如 `60` 表示 6 折，客户最终支付 60% 金额。</p>
          </div>

          <el-form ref="formRef" :model="form" :rules="formRules" label-position="top">
            <div class="dialog-grid">
              <el-form-item label="优惠券名称" prop="name">
                <el-input v-model="form.name" class="field-md" maxlength="120" placeholder="例如：新客首单立减券" />
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

              <el-form-item label="发放方式" prop="distribution_type">
                <el-select v-model="form.distribution_type" class="field-sm">
                  <el-option label="公开优惠券" value="public" />
                  <el-option label="私有优惠券" value="private" />
                </el-select>
              </el-form-item>

              <el-form-item :label="form.discount_type === 'percentage' ? '优惠值（百分比）' : '优惠金额'" prop="discount_value">
                <div class="discount-input-wrap">
                  <el-input-number
                    class="field-xs"
                    v-model="form.discount_value"
                    :min="0"
                    :max="form.discount_type === 'percentage' ? 100 : 999999999"
                    :precision="2"
                    controls-position="right"
                  />
                  <span v-if="form.discount_type === 'percentage'" class="discount-input-suffix">%</span>
                  <span v-else class="discount-input-suffix">元</span>
                </div>
              </el-form-item>

              <el-form-item label="最低消费金额">
                <el-input-number
                  class="field-number-md"
                  v-model="form.min_amount"
                  :min="0"
                  :max="999999999"
                  :precision="2"
                  controls-position="right"
                />
              </el-form-item>

              <el-form-item label="最高优惠金额">
                <el-input-number
                  class="field-number-md"
                  v-model="form.max_discount_amount"
                  :min="0"
                  :max="999999999"
                  :precision="2"
                  controls-position="right"
                  placeholder="留空表示不限制"
                />
              </el-form-item>

              <el-form-item label="总发放次数上限">
                <el-input-number
                  class="field-number-sm"
                  v-model="form.total_usage_limit"
                  :min="0"
                  :max="999999999"
                  controls-position="right"
                />
              </el-form-item>

              <el-form-item label="每人可用次数">
                <el-input-number
                  class="field-number-sm"
                  v-model="form.per_user_limit"
                  :min="0"
                  :max="999999999"
                  controls-position="right"
                />
              </el-form-item>

              <el-form-item label="开始时间">
                <el-date-picker
                  v-model="form.starts_at"
                  class="field-date-sm"
                  type="datetime"
                  value-format="YYYY-MM-DD HH:mm:ss"
                  placeholder="留空表示立即生效"
                />
              </el-form-item>

              <el-form-item label="结束时间">
                <el-date-picker
                  v-model="form.expires_at"
                  class="field-date-sm"
                  type="datetime"
                  value-format="YYYY-MM-DD HH:mm:ss"
                  placeholder="留空表示长期有效"
                />
              </el-form-item>

              <el-form-item label="排序值">
                <el-input-number
                  class="field-xs"
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

              <el-form-item label="仅限首单可用">
                <el-switch v-model="form.first_order_only" />
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

              <div class="selection-workspace dialog-span-2">
                <el-form-item label="适用商品" class="selection-panel">
                  <div class="product-tree-panel">
                    <div class="product-tree-toolbar">
                      <span>留空表示全站商品可用。勾选商品纳入优惠范围。</span>
                      <el-button text type="primary" @click="clearProductSelection">清空选择</el-button>
                    </div>
                    <!-- 一级分类下拉 -->
                    <div class="product-group-selector">
                      <el-select
                        v-model="selectedGroupId"
                        placeholder="请先选择产品分类"
                        clearable
                        class="product-group-select"
                        @change="handleGroupChange"
                      >
                        <el-option
                          v-for="g in productGroupOptions"
                          :key="g.value"
                          :label="g.label"
                          :value="g.value"
                        />
                      </el-select>
                    </div>
                    <!-- 二级三级树状图 -->
                    <div class="product-tree-shell" v-loading="productTreeLoading">
                      <el-tree
                        v-if="currentGroupChildren.length"
                        ref="productTreeRef"
                        :data="currentGroupChildren"
                        node-key="id"
                        show-checkbox
                        :expand-on-click-node="false"
                        class="coupon-product-tree"
                      />
                      <el-empty
                        v-else-if="!productTreeLoading"
                        :description="selectedGroupId ? '该分组暂无商品' : '请先选择产品分组'"
                        :image-size="48"
                      />
                    </div>
                    <!-- 已选统计 -->
                    <div v-if="productSelectedIds.size > 0" class="product-nav-selected-bar">
                      已选 <strong>{{ productSelectedIds.size }}</strong> 个商品
                    </div>
                  </div>
                </el-form-item>

                <el-form-item label="发放用户" class="selection-panel" prop="user_ids">
                  <div v-if="form.distribution_type === 'private'" class="user-picker-panel">
                    <div class="user-picker-toolbar">
                      <el-input
                        v-model="userSearchKeyword"
                        placeholder="搜索用户 ID / 邮箱 / 手机号 / 昵称"
                        clearable
                        @keyup.enter="searchUsers"
                        @clear="searchUsers"
                      >
                        <template #prefix><el-icon><Search /></el-icon></template>
                      </el-input>
                    </div>

                    <div class="user-picker-grid">
                      <section class="user-picker-column">
                        <div class="user-picker-column-head">
                          <strong>搜索结果</strong>
                          <span>{{ userSearchResults.length }} 条</span>
                        </div>

                        <div class="user-picker-table-shell" v-loading="userOptionsLoading">
                          <el-table
                            v-if="userSearchResults.length"
                            :data="userSearchResults"
                            stripe
                            size="small"
                            height="320"
                            class="user-picker-table"
                          >
                            <el-table-column label="用户" min-width="220">
                              <template #default="{ row }">
                                <div class="table-user-cell">
                                  <strong>{{ row.title }}</strong>
                                  <span>{{ row.meta }}</span>
                                </div>
                              </template>
                            </el-table-column>
                            <el-table-column label="操作" width="72" align="center">
                              <template #default="{ row }">
                                <el-button
                                  size="small"
                                  :type="form.user_ids.includes(row.value) ? 'info' : 'primary'"
                                  plain
                                  @click="toggleUserSelection(row)"
                                >
                                  {{ form.user_ids.includes(row.value) ? '已选' : '添加' }}
                                </el-button>
                              </template>
                            </el-table-column>
                          </el-table>
                          <el-empty v-else description="暂无搜索结果" :image-size="56" />
                        </div>
                      </section>

                      <section class="user-picker-column">
                        <div class="user-picker-column-head">
                          <strong>已选用户</strong>
                          <span>{{ selectedUsers.length }} 人</span>
                        </div>

                        <div class="user-picker-table-shell">
                          <el-table
                            v-if="selectedUsers.length"
                            :data="selectedUsers"
                            stripe
                            size="small"
                            height="320"
                            class="user-picker-table"
                          >
                            <el-table-column label="用户" min-width="220">
                              <template #default="{ row }">
                                <div class="table-user-cell">
                                  <strong>{{ row.title }}</strong>
                                  <span>{{ row.meta }}</span>
                                </div>
                              </template>
                            </el-table-column>
                            <el-table-column label="操作" width="72" align="center">
                              <template #default="{ row }">
                                <el-button size="small" text type="danger" @click="removeSelectedUser(row.value)">移除</el-button>
                              </template>
                            </el-table-column>
                          </el-table>
                          <el-empty v-else description="还没有选择发放用户" :image-size="56" />
                        </div>
                      </section>
                    </div>
                  </div>

                  <div v-else class="selection-placeholder">
                    <strong>当前为公开优惠券</strong>
                    <p>公开优惠券无需指定客户。用户会在客户端看到公开券列表，并手动领取到自己的账户后再使用。</p>
                  </div>
                </el-form-item>
              </div>

              <el-form-item label="描述" class="dialog-span-2">
                <el-input
                  v-model="form.description"
                  type="textarea"
                  :rows="3"
                  maxlength="255"
                  show-word-limit
                  placeholder="前台展示给客户的说明文案"
                />
              </el-form-item>

              <el-form-item label="后台备注" class="dialog-span-2">
                <el-input
                  v-model="form.remark"
                  type="textarea"
                  :rows="3"
                  maxlength="255"
                  show-word-limit
                  placeholder="仅后台可见，例如投放渠道、活动说明"
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
import { computed, nextTick, onMounted, reactive, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Refresh, Search } from '@element-plus/icons-vue'
import adminApi from '@/api/admin'
import userApi from '@/api/user'
import { formatDateTime } from '@/utils/datetime'
import StatusTag from '@shared/components/StatusTag.vue'
import { COUPON_DISPLAY_STATUS_MAP } from '@shared/extraStatusMaps'
import { useResponsive } from '@/composables/useResponsive'

const { isMobile } = useResponsive()

const billingCycleOptions = [
  { label: '月付', value: 'monthly' },
  { label: '季付', value: 'quarterly' },
  { label: '半年付', value: 'semiannually' },
  { label: '年付', value: 'annually' },
]

const loading = ref(false)
const saving = ref(false)
const dialogVisible = ref(false)
const formRef = ref(null)
const list = ref([])
const total = ref(0)
const page = ref(1)
const pageSize = ref(20)
const couponFeatureEnabled = ref(false)
const productTreeData = ref([])
const productTreeLoading = ref(false)
const productTreeRef = ref(null)
const selectedGroupId = ref('')
const productSelectedIds = ref(new Set())
const userOptions = ref([])
const userOptionsLoading = ref(false)
const userSearchKeyword = ref('')
const userSearchResults = ref([])
const filters = reactive({
  keyword: '',
  status: '',
  discount_type: '',
  discount_scope: '',
  distribution_type: '',
})

const createDefaultForm = () => ({
  id: null,
  name: '',
  distribution_type: 'public',
  discount_scope: 'first_month',
  discount_type: 'fixed',
  discount_value: 0,
  min_amount: 0,
  max_discount_amount: null,
  billing_cycles: [],
  product_ids: [],
  first_order_only: false,
  user_ids: [],
  total_usage_limit: null,
  per_user_limit: null,
  status: 1,
  sort_order: 0,
  starts_at: '',
  expires_at: '',
  description: '',
  remark: '',
})

const form = reactive(createDefaultForm())

const formRules = {
  name: [{ required: true, message: '请输入优惠券名称', trigger: 'blur' }],
  distribution_type: [{ required: true, message: '请选择发放方式', trigger: 'change' }],
  discount_scope: [{ required: true, message: '请选择优惠阶段', trigger: 'change' }],
  discount_type: [{ required: true, message: '请选择优惠类型', trigger: 'change' }],
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
  user_ids: [{
    validator: (_, value, callback) => {
      if (form.distribution_type !== 'private') {
        callback()
        return
      }

      if (Array.isArray(value) && value.length > 0) {
        callback()
        return
      }

      callback(new Error('私有优惠券至少需要选择一个发放用户'))
    },
    trigger: 'change',
  }],
}

const selectedUsers = computed(() => {
  return (form.user_ids || [])
    .map((id) => {
      const matched = userOptions.value.find((item) => Number(item.value) === Number(id))
      return matched || {
        value: Number(id),
        title: `#${id}`,
        meta: '用户信息加载中',
        label: `#${id}`,
      }
    })
})

function resetForm() {
  Object.assign(form, createDefaultForm())
  selectedGroupId.value = ''
  productSelectedIds.value = new Set()
}

function resetValidate() {
  formRef.value?.clearValidate?.()
}

function formatLimitText(limit, remain, label) {
  if (!limit) {
    return `${label}：不限`
  }

  if (label === '总量') {
    return `${label}：${limit} 次，剩余 ${remain ?? 0} 次`
  }

  return `${label}：${limit} 次`
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

const PRODUCT_TYPE_LABELS = {
  vps: '云服务器',
  dedicated: '独立服务器',
  hosting: '虚拟主机',
  domain: '域名',
  other: '其他',
}

const productGroupOptions = computed(() => {
  const typeMap = new Map()
  for (const group of productTreeData.value) {
    const type = group.product_type || 'other'
    if (!typeMap.has(type)) {
      typeMap.set(type, {
        value: type,
        label: PRODUCT_TYPE_LABELS[type] || type,
        groups: [],
      })
    }
    typeMap.get(type).groups.push(group)
  }
  return [...typeMap.values()]
})

const currentGroupChildren = computed(() => {
  if (!selectedGroupId.value) return []
  const option = productGroupOptions.value.find((o) => o.value === selectedGroupId.value)
  return option?.groups.map((g) => ({ ...g })) || []
})

function handleGroupChange() {
  // 切换分组前先保存当前树的勾选状态
  collectTreeChecked()
  nextTick(() => {
    syncCheckedToTree()
  })
}

function syncCheckedToTree() {
  const treeRef = productTreeRef.value
  if (!treeRef?.setCheckedKeys) return
  treeRef.setCheckedKeys([...productSelectedIds.value])
}

function mergeUserOptions(items = [], preserveSelectedOnly = false) {
  const optionMap = new Map()
  const selectedIdSet = new Set((form.user_ids || []).map((id) => Number(id)))

  if (preserveSelectedOnly) {
    userOptions.value
      .filter((item) => selectedIdSet.has(Number(item.value)))
      .forEach((item) => {
        optionMap.set(Number(item.value), item)
      })
  } else {
    userOptions.value.forEach((item) => {
      optionMap.set(Number(item.value), item)
    })
  }

  items.forEach((item) => {
    optionMap.set(Number(item.value), item)
  })

  userOptions.value = Array.from(optionMap.values())
}

async function loadUserOptions(keyword = '') {
  userOptionsLoading.value = true

  try {
    const trimmedKeyword = String(keyword || '').trim()
    const isUserIdSearch = /^\d+$/.test(trimmedKeyword)
    const res = await userApi.list({
      user_id: isUserIdSearch ? Number(trimmedKeyword) : undefined,
      keyword: !isUserIdSearch ? trimmedKeyword || undefined : undefined,
      page_size: 50,
    })

    const items = (res.data?.list || []).map((item) => ({
      value: Number(item.id),
      title: `#${item.id} / ${item.email || '未填写邮箱'}`,
      meta: `${item.phone || '未填写手机号'}${item.nickname ? ` / ${item.nickname}` : ''}`,
      label: `#${item.id} / ${item.email}${item.phone ? ` / ${item.phone}` : ''}${item.nickname ? ` / ${item.nickname}` : ''}`,
    }))

    mergeUserOptions(items, true)
    userSearchResults.value = items
  } finally {
    userOptionsLoading.value = false
  }
}

async function ensureSelectedUsersLoaded(userIds = []) {
  const missingIds = (userIds || []).filter((id) => !userOptions.value.some((item) => Number(item.value) === Number(id)))

  if (!missingIds.length) {
    return
  }

  const results = await Promise.all(
    missingIds.map(async (userId) => {
      const res = await userApi.list({ user_id: Number(userId), page_size: 1 })
      const row = res.data?.list?.[0]
      if (!row) {
        return null
      }

      return {
        value: Number(row.id),
        title: `#${row.id} / ${row.email || '未填写邮箱'}`,
        meta: `${row.phone || '未填写手机号'}${row.nickname ? ` / ${row.nickname}` : ''}`,
        label: `#${row.id} / ${row.email}${row.phone ? ` / ${row.phone}` : ''}${row.nickname ? ` / ${row.nickname}` : ''}`,
      }
    })
  )

  mergeUserOptions(results.filter(Boolean))
}

function getSelectedProductIds() {
  // 同步当前树的勾选状态
  collectTreeChecked()
  return [...productSelectedIds.value].filter((id) => Number.isInteger(id) && id > 0)
}

function collectTreeChecked() {
  const treeRef = productTreeRef.value
  if (!treeRef?.getCheckedKeys) return
  const checked = treeRef.getCheckedKeys(true).filter((id) => Number.isInteger(Number(id)) && Number(id) > 0)
  // 保留非当前分组的已选，合并当前树的勾选
  const currentGroupIds = new Set()
  const walk = (nodes) => {
    for (const n of nodes || []) {
      if (n.node_type === 'product') currentGroupIds.add(n.id)
      walk(n.children)
    }
  }
  walk(currentGroupChildren.value)

  const next = new Set()
  for (const id of productSelectedIds.value) {
    if (!currentGroupIds.has(id)) next.add(id)
  }
  for (const id of checked) {
    if (currentGroupIds.has(Number(id))) next.add(Number(id))
  }
  productSelectedIds.value = next
}

function applyCheckedProductIds(productIds = []) {
  productSelectedIds.value = new Set((productIds || []).map(Number).filter((id) => id > 0))
  nextTick(() => syncCheckedToTree())
}

function clearProductSelection() {
  productSelectedIds.value = new Set()
  productTreeRef.value?.setCheckedKeys?.([])
}

function autoSelectGroupForProducts(productIds) {
  const idSet = new Set(productIds.map(Number))
  for (const option of productGroupOptions.value) {
    for (const group of option.groups) {
      const groupProductIds = new Set()
      const walk = (nodes) => {
        for (const n of nodes || []) {
          if (n.node_type === 'product') groupProductIds.add(n.id)
          walk(n.children)
        }
      }
      walk(group.children || [])
      for (const pid of idSet) {
        if (groupProductIds.has(pid)) {
          selectedGroupId.value = option.value
          nextTick(() => syncCheckedToTree())
          return
        }
      }
    }
  }
}

function validateSelectedUsers() {
  formRef.value?.validateField?.('user_ids').catch(() => {})
}

function toggleUserSelection(item) {
  const nextId = Number(item.value)
  if (!nextId) {
    return
  }

  if (form.user_ids.includes(nextId)) {
    form.user_ids = form.user_ids.filter((id) => Number(id) !== nextId)
  } else {
    form.user_ids = [...form.user_ids, nextId]
  }

  validateSelectedUsers()
}

function removeSelectedUser(userId) {
  form.user_ids = form.user_ids.filter((id) => Number(id) !== Number(userId))
  validateSelectedUsers()
}

function searchUsers() {
  loadUserOptions(userSearchKeyword.value)
}

function resetUserSearch() {
  userSearchKeyword.value = ''
  loadUserOptions('')
}

async function loadList() {
  loading.value = true

  try {
    const res = await adminApi.coupons.list({
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

async function loadSummary() {
  const res = await adminApi.coupons.summary({
    keyword: filters.keyword || undefined,
    discount_type: filters.discount_type || undefined,
    discount_scope: filters.discount_scope || undefined,
    distribution_type: filters.distribution_type || undefined,
  })

  couponFeatureEnabled.value = Boolean(res.data?.enabled)
}

async function loadData() {
  await Promise.all([loadList(), loadSummary()])
}

function handleSearch() {
  page.value = 1
  loadData()
}

function resetFilters() {
  filters.keyword = ''
  filters.status = ''
  filters.discount_type = ''
  filters.discount_scope = ''
  filters.distribution_type = ''
  page.value = 1
  loadData()
}

async function openCreateDialog() {
  if (!couponFeatureEnabled.value) {
    ElMessage.warning('当前环境未启用优惠券功能')
    return
  }

  resetForm()

  try {
    userSearchKeyword.value = ''
    userSearchResults.value = []
    dialogVisible.value = true
    if (!productTreeData.value.length) {
      await loadProductTree()
    }
    await loadUserOptions('')
    await applyCheckedProductIds([])
  } catch (error) {
    dialogVisible.value = false
    if (!error?.response) {
      ElMessage.error(error?.message || '加载优惠券配置失败')
    }
  }
}

function handleCouponAction(command, row) {
  if (command === 'edit') {
    openEditDialog(row)
  } else if (command === 'toggle') {
    handleToggleStatus(row)
  } else if (command === 'delete') {
    handleDelete(row)
  }
}

async function openEditDialog(row) {
  if (!couponFeatureEnabled.value) {
    ElMessage.warning('当前环境未启用优惠券功能')
    return
  }

  resetForm()

  form.id = Number(row.id)
  form.name = row.name || ''
  form.distribution_type = row.distribution_type || 'public'
  form.discount_scope = row.discount_scope || 'first_month'
  form.discount_type = row.discount_type || 'fixed'
  form.discount_value = Number(row.discount_value_raw || 0)
  form.min_amount = Number(row.min_amount_raw || 0)
  form.max_discount_amount = row.max_discount_amount_raw === null ? null : Number(row.max_discount_amount_raw || 0)
  form.billing_cycles = Array.isArray(row.billing_cycles) ? [...row.billing_cycles] : []
  form.product_ids = Array.isArray(row.product_ids) ? row.product_ids.map((id) => Number(id)) : []
  form.first_order_only = Boolean(row.first_order_only)
  form.user_ids = Array.isArray(row.user_ids) ? row.user_ids.map((id) => Number(id)) : []
  form.total_usage_limit = row.total_usage_limit === null ? null : Number(row.total_usage_limit || 0)
  form.per_user_limit = row.per_user_limit === null ? null : Number(row.per_user_limit || 0)
  form.status = Number(row.status ?? 1)
  form.sort_order = Number(row.sort_order || 0)
  form.starts_at = row.starts_at || ''
  form.expires_at = row.expires_at || ''
  form.description = row.description || ''
  form.remark = row.remark || ''

  try {
    userSearchKeyword.value = ''
    userSearchResults.value = []
    dialogVisible.value = true
    if (!productTreeData.value.length) {
      await loadProductTree()
    }
    await ensureSelectedUsersLoaded(form.user_ids)
    await loadUserOptions('')
    await applyCheckedProductIds(form.product_ids)
    // 自动选中包含已选商品的分组
    if (form.product_ids.length) {
      autoSelectGroupForProducts(form.product_ids)
    }
  } catch (error) {
    dialogVisible.value = false
    if (!error?.response) {
      ElMessage.error(error?.message || '加载优惠券详情失败')
    }
  }
}

function buildPayload() {
  return {
    name: form.name.trim(),
    description: form.description.trim() || null,
    distribution_type: form.distribution_type,
    discount_scope: form.discount_scope,
    discount_type: form.discount_type,
    discount_value: Number(form.discount_value || 0),
    min_amount: Number(form.min_amount || 0),
    max_discount_amount: form.max_discount_amount === null || form.max_discount_amount === ''
      ? null
      : Number(form.max_discount_amount || 0),
    billing_cycles: form.billing_cycles,
    product_ids: getSelectedProductIds(),
    first_order_only: Boolean(form.first_order_only),
    user_ids: form.distribution_type === 'private' ? form.user_ids : [],
    total_usage_limit: form.total_usage_limit === null || form.total_usage_limit === ''
      ? null
      : Number(form.total_usage_limit || 0),
    per_user_limit: form.per_user_limit === null || form.per_user_limit === ''
      ? null
      : Number(form.per_user_limit || 0),
    status: Number(form.status ?? 1),
    sort_order: Number(form.sort_order || 0),
    starts_at: form.starts_at || null,
    expires_at: form.expires_at || null,
    remark: form.remark.trim() || null,
  }
}

async function submitForm() {
  if (!couponFeatureEnabled.value) {
    ElMessage.warning('当前环境未启用优惠券功能')
    return
  }

  const valid = await formRef.value?.validate?.().catch(() => false)
  if (valid === false) {
    return
  }

  saving.value = true

  try {
    const payload = buildPayload()
    if (form.id) {
      await adminApi.coupons.update(form.id, payload)
      ElMessage.success('优惠券已更新')
    } else {
      await adminApi.coupons.create(payload)
      ElMessage.success('优惠券已创建')
    }

    dialogVisible.value = false
    await loadData()
  } catch (error) {
    if (!error?.response) {
      ElMessage.error(error?.message || '保存优惠券失败')
    }
  } finally {
    saving.value = false
  }
}

async function handleToggleStatus(row) {
  if (!couponFeatureEnabled.value) {
    ElMessage.warning('当前环境未启用优惠券功能')
    return
  }

  try {
    await adminApi.coupons.toggleStatus(row.id)
    ElMessage.success('优惠券状态已更新')
    await loadData()
  } catch (error) {
    if (!error?.response) {
      ElMessage.error(error?.message || '更新优惠券状态失败')
    }
  }
}

async function handleDelete(row) {
  if (!couponFeatureEnabled.value) {
    ElMessage.warning('当前环境未启用优惠券功能')
    return
  }

  try {
    await ElMessageBox.confirm(
      `确认删除优惠券“${row.name}”吗？`,
      '删除优惠券',
      {
        type: 'warning',
        confirmButtonText: '确认删除',
        cancelButtonText: '取消',
      }
    )
  } catch {
    return
  }

  try {
    await adminApi.coupons.delete(row.id)
    ElMessage.success('优惠券已删除')
    await loadData()
  } catch (error) {
    if (!error?.response) {
      ElMessage.error(error?.message || '删除优惠券失败')
    }
  }
}

onMounted(async () => {
  try {
    await Promise.all([loadData(), loadProductTree(), loadUserOptions()])
  } catch {
    // 请求拦截器已统一提示，这里只负责避免未处理 Promise
  }
})
</script>

<style scoped lang="scss">
.coupons-head-side {
  display: flex;
  flex-direction: column;
  gap: 12px;
  width: min(100%, 860px);
}

.page-actions,
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

.coupon-feature-alert {
  margin-bottom: 16px;
}

.coupons-filter-form {
  flex-direction: column;
  align-items: stretch;
  gap: 10px;
}

.coupons-filter-keyword {
  margin-bottom: 0;
  width: 100%;

  :deep(.el-input) {
    width: 100%;
  }
}

.coupons-filter-selects {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;

  .el-select {
    flex: 1 1 0;
    min-width: 80px;
  }
}

:deep(.coupons-filter-selects .el-select__wrapper) {
  font-size: 13px;
  padding: 4px 8px;
  min-height: 28px;
}

.coupons-table-card {
  overflow: hidden;
}

.coupon-main,
.coupon-meta {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.coupon-title-row,
.coupon-meta-top {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.coupon-title-row strong,
.coupon-meta-top strong,
.dialog-intro strong {
  color: $text-color-primary;
  font-size: 14px;
  font-weight: 600;
}

.coupon-desc,
.coupon-meta span,
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
  justify-content: center;
  gap: 14px 18px;
}

.dialog-span-2 {
  grid-column: 1 / -1;
}

.selection-workspace {
  display: grid;
  grid-template-columns: minmax(280px, 320px) minmax(0, 1fr);
  gap: 16px;
  align-items: stretch;
}

.selection-panel {
  display: flex;
  flex-direction: column;
  margin-bottom: 0;
  min-height: 0;
}

.selection-panel :deep(.el-form-item__content) {
  display: flex;
  flex: 1;
  min-height: 0;
}

.drawer-footer {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
}

.discount-input-wrap {
  display: grid;
  grid-template-columns: auto auto;
  align-items: center;
  gap: 10px;
  justify-content: flex-start;
}

.discount-input-suffix {
  color: $text-color-secondary;
  font-size: 13px;
  font-weight: 600;
}

.field-xs {
  width: 108px;
}

.field-sm {
  width: 240px;
  max-width: 100%;
}

.field-md {
  width: 420px;
  max-width: 100%;
}

.field-lg {
  width: 520px;
  max-width: 100%;
}

.field-number-sm {
  width: 180px;
  max-width: 100%;
}

.field-number-md {
  width: 220px;
  max-width: 100%;
}

.field-cycle {
  width: 320px;
  max-width: 100%;
}

.field-date-sm {
  width: 240px;
  max-width: 100%;
}

.product-tree-panel {
  display: flex;
  flex-direction: column;
  flex: 1;
  min-height: 452px;
  border: 1px solid $divider-color;
  border-radius: $sm-border-radius;
  background: $bg-color-card;
  overflow: hidden;
}

.product-tree-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  min-height: 44px;
  padding: 10px 12px;
  border-bottom: 1px solid $divider-color;
  background: $bg-color-soft;

  span {
    color: $text-color-secondary;
    font-size: 12px;
    line-height: 1.6;
  }
}

.product-tree-shell {
  flex: 1;
  min-height: 0;
  overflow: auto;
  padding: 8px 12px 12px;
  background: $bg-color-card;
}

.product-group-selector {
  padding: 10px 12px;
  border-bottom: 1px solid $divider-color;
}

.product-group-select {
  width: 100%;
}

.coupon-product-tree :deep(.el-tree-node__content) {
  min-height: 34px;
  border-radius: 0;
}

.coupon-product-tree :deep(.el-tree-node__content:hover) {
  background: $bg-color-hover;
}

.product-nav-selected-bar {
  display: flex;
  align-items: center;
  gap: 4px;
  padding: 10px 0 2px;
  border-top: 1px solid $divider-color;
  margin-top: 8px;
  font-size: 12px;
  color: $text-color-secondary;

  strong {
    color: $color-primary;
    font-weight: 600;
  }
}

.user-picker-panel {
  display: flex;
  flex-direction: column;
  flex: 1;
  min-height: 452px;
  border: 1px solid $divider-color;
  border-radius: $sm-border-radius;
  background: $bg-color-card;
  overflow: hidden;
}

.user-picker-toolbar {
  display: grid;
  grid-template-columns: minmax(0, 1fr);
  gap: 10px;
  padding: 10px 12px;
  border-bottom: 1px solid $divider-color;
  background: $bg-color-soft;
}

.user-picker-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  flex: 1;
  min-height: 0;
  gap: 12px;
  padding: 12px;
}

.user-picker-column {
  display: flex;
  flex-direction: column;
  min-width: 0;
  min-height: 0;
  border: 1px solid $divider-color;
  border-radius: $sm-border-radius;
  background: $bg-color-card;
  overflow: hidden;
}

.user-picker-column-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  min-height: 44px;
  padding: 10px 12px;
  border-bottom: 1px solid $divider-color;
  background: $bg-color-soft;

  strong {
    color: $text-color-primary;
    font-size: 13px;
    font-weight: 600;
  }

  span {
    color: $text-color-placeholder;
    font-size: 12px;
  }
}

.user-picker-table-shell {
  flex: 1;
  min-height: 0;
  background: $bg-color-card;
  overflow-x: auto;
}

.user-picker-table {
  border: none !important;
  border-radius: 0 !important;
}

.table-user-cell {
  display: flex;
  flex-direction: column;
  gap: 4px;
  min-width: 0;

  strong {
    color: $text-color-primary;
    font-size: 13px;
    font-weight: 600;
    line-height: 1.5;
    word-break: break-all;
  }

  span {
    color: $text-color-secondary;
    font-size: 12px;
    line-height: 1.6;
    word-break: break-all;
  }
}

.selection-placeholder {
  display: flex;
  flex: 1;
  flex-direction: column;
  gap: 8px;
  min-height: 452px;
  padding: 16px 18px;
  border: 1px solid $divider-color;
  border-radius: $sm-border-radius;
  background: $bg-color-soft;

  strong {
    color: $text-color-primary;
    font-size: 15px;
    font-weight: 600;
  }

  p {
    color: $text-color-secondary;
    font-size: 13px;
    line-height: 1.8;
  }
}

.table-pagination {
  display: flex;
  justify-content: flex-end;
  margin-top: 16px;
}

.coupons-page :deep(.el-input-number) {
  width: 100%;
}

.coupons-page :deep(.el-input-number .el-input__wrapper) {
  width: 100%;
}

.coupons-page :deep(.field-xs.el-input-number) {
  width: 108px !important;
}

.coupons-page :deep(.field-number-sm.el-input-number) {
  width: 180px !important;
}

.coupons-page :deep(.field-number-md.el-input-number) {
  width: 220px !important;
}

.coupons-page :deep(.field-sm.el-select),
.coupons-page :deep(.field-sm.el-input),
.coupons-page :deep(.field-sm.el-date-editor),
.coupons-page :deep(.field-sm.el-select .el-select__wrapper),
.coupons-page :deep(.field-sm .el-input__wrapper) {
  width: 240px !important;
  max-width: 100%;
}

.coupons-page :deep(.field-md.el-input),
.coupons-page :deep(.field-md .el-input__wrapper) {
  width: 420px !important;
  max-width: 100%;
}

.coupons-page :deep(.field-cycle.el-select),
.coupons-page :deep(.field-cycle .el-select__wrapper) {
  width: 320px !important;
  max-width: 100%;
}

.coupons-page :deep(.field-date-sm.el-date-editor),
.coupons-page :deep(.field-date-sm .el-input__wrapper) {
  width: 240px !important;
  max-width: 100%;
}

.coupons-page :deep(.coupon-edit-drawer .el-drawer__header) {
  margin-bottom: 0;
  padding: 22px 28px 18px;
  border-bottom: 1px solid $divider-color;
}

.coupons-page :deep(.coupon-edit-drawer .el-drawer__body) {
  padding: 18px 28px 10px;
}

.coupons-page :deep(.coupon-edit-drawer .el-drawer__footer) {
  padding: 18px 28px 22px;
  border-top: 1px solid $divider-color;
}

@media (max-width: 1280px) {
  .dialog-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .selection-workspace {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 900px) {
  .coupons-head-side {
    width: 100%;
  }

  .page-actions,
  .drawer-footer {
    justify-content: flex-start;
    flex-wrap: wrap;
  }

  .dialog-grid {
    grid-template-columns: 1fr;
  }

  .dialog-span-2 {
    grid-column: span 1;
  }

  .selection-workspace {
    grid-template-columns: 1fr;
  }

  .product-tree-toolbar {
    flex-direction: column;
    align-items: flex-start;
  }

  .user-picker-toolbar {
    grid-template-columns: 1fr;
  }

  .user-picker-grid {
    grid-template-columns: 1fr;
  }

  .drawer-body {
    padding-right: 0;
  }
}

@media (max-width: 768px) {
  .coupons-page :deep(.coupon-edit-drawer .el-drawer__header) {
    padding: 16px 16px 14px;
  }

  .coupons-page :deep(.coupon-edit-drawer .el-drawer__body) {
    padding: 14px 16px 10px;
  }

  .coupons-page :deep(.coupon-edit-drawer .el-drawer__footer) {
    padding: 14px 16px 18px;
  }

  .drawer-header strong {
    font-size: 18px;
  }

  .drawer-header span {
    font-size: 12px;
  }

  .drawer-content {
    width: 100%;
  }

  .dialog-grid {
    gap: 10px 12px;
  }

  .dialog-intro {
    padding: 10px 12px;
    margin-bottom: 12px;
  }

  .dialog-intro--plain {
    padding: 10px 12px;
  }

  .coupons-page :deep(.field-xs.el-input-number) {
    width: 100% !important;
  }

  .coupons-page :deep(.field-sm.el-select),
  .coupons-page :deep(.field-sm.el-input),
  .coupons-page :deep(.field-sm.el-date-editor),
  .coupons-page :deep(.field-sm.el-select .el-select__wrapper),
  .coupons-page :deep(.field-sm .el-input__wrapper),
  .coupons-page :deep(.field-md.el-input),
  .coupons-page :deep(.field-md .el-input__wrapper),
  .coupons-page :deep(.field-number-sm.el-input-number),
  .coupons-page :deep(.field-number-md.el-input-number),
  .coupons-page :deep(.field-cycle.el-select),
  .coupons-page :deep(.field-cycle .el-select__wrapper),
  .coupons-page :deep(.field-date-sm.el-date-editor),
  .coupons-page :deep(.field-date-sm .el-input__wrapper) {
    width: 100% !important;
  }

  .discount-input-wrap {
    width: 100%;
    grid-template-columns: 1fr auto;
  }

  .product-tree-panel,
  .user-picker-panel,
  .selection-placeholder {
    min-height: 260px;
  }

  .user-picker-grid {
    gap: 10px;
    padding: 10px;
  }

  .user-picker-column-head {
    min-height: 38px;
    padding: 8px 10px;
  }

  .product-tree-toolbar {
    padding: 8px 10px;
  }

  .product-tree-shell {
    padding: 6px 10px 10px;
  }

  .drawer-footer {
    gap: 8px;
  }

  .drawer-footer .el-button {
    flex: 1;
  }
}

</style>
