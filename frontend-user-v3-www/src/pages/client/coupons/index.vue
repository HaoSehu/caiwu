<template>
  <div class="client-page coupons-page">
    <section class="coupon-filter-card">
      <div class="coupon-filter-bar">
        <el-input
          v-model="currentState.keyword"
          clearable
          class="coupon-filter-bar__search"
          :placeholder="activeTab === 'plaza' ? '搜索可领取的优惠券' : '搜索优惠券名称'"
          @keyup.enter="handleSearch(activeTab)"
          @clear="handleSearch(activeTab)"
        >
          <template #suffix>
            <button type="button" class="coupon-search-trigger" aria-label="搜索优惠券" @click="handleSearch(activeTab)">
              <el-icon><Search /></el-icon>
            </button>
          </template>
        </el-input>

        <el-select
          v-model="currentState.status"
          clearable
          class="coupon-filter-bar__select"
          placeholder="全部状态"
          @change="handleSearch(activeTab)"
        >
          <el-option
            v-for="item in currentStatusOptions"
            :key="item.value"
            :label="item.label"
            :value="item.value"
          />
        </el-select>

        <el-button class="coupon-filter-bar__toggle" @click="setViewMode(nextViewMode)">
          <el-icon><component :is="toggleIcon" /></el-icon>
          切换
        </el-button>
      </div>
    </section>

    <el-tabs :model-value="activeTab" class="coupon-tabs" @tab-change="handleTabChange">
      <el-tab-pane label="我拥有的优惠券" name="owned">
        <div v-loading="ownedState.loading" class="coupon-list-shell">
          <template v-if="ownedState.list.length">
            <div v-show="viewMode === 'grid'" class="coupon-grid">
              <article v-for="item in ownedState.list" :key="item.id" class="coupon-card">
                  <button class="coupon-card__info" type="button" aria-label="查看优惠券详情" @click="openCouponDetail(item)">
                    <el-icon><InfoFilled /></el-icon>
                  </button>

                  <div class="coupon-card__head">
                    <div class="coupon-card__value">
                      <span>{{ resolveDiscountTypeLabel(item.discount_type) }}</span>
                      <strong>{{ resolveDiscountValue(item) }}</strong>
                    </div>
                    <el-tag effect="light" :type="resolveStatusTagType(item.status)">
                      {{ item.status_label || item.status || '--' }}
                    </el-tag>
                  </div>

                  <div class="coupon-card__body">
                    <div class="coupon-card__title">
                      <strong>{{ item.name || '优惠券' }}</strong>
                      <p>{{ item.description || '满足条件后可在结算时直接抵扣' }}</p>
                    </div>

                    <div class="coupon-card__amounts">
                      <span>{{ resolveThresholdText(item) }}</span>
                      <span>{{ resolveDiscountAmountText(item) }}</span>
                    </div>
                  </div>

                  <div class="coupon-card__foot">
                    <span>{{ item.validity_text || item.expires_at || '--' }}</span>
                    <span v-if="item.receive_type_label">{{ item.receive_type_label }}</span>
                  </div>
                </article>
              </div>

              <div v-show="viewMode === 'list'" class="coupon-table-card">
                <el-table
                  :data="ownedState.list"
                  row-key="id"
                  table-layout="auto"
                  class="coupon-list-table"
                  empty-text="你还没有优惠券"
                >
                  <el-table-column label="优惠券信息" min-width="280">
                    <template #default="{ row }">
                      <div class="coupon-table-info">
                        <div class="coupon-table-mark">
                          {{ resolveDiscountTypeLabel(row.discount_type).slice(0, 2) }}
                        </div>
                        <div class="coupon-table-copy">
                          <div class="coupon-table-title-row">
                            <strong>{{ row.name || '优惠券' }}</strong>
                            <span class="coupon-table-id">ID {{ row.id }}</span>
                          </div>
                          <p>{{ row.description || row.status_reason || '满足条件后可在结算时直接抵扣' }}</p>
                        </div>
                      </div>
                    </template>
                  </el-table-column>

                  <el-table-column label="优惠类型" min-width="110">
                    <template #default="{ row }">{{ resolveDiscountTypeLabel(row.discount_type) }}</template>
                  </el-table-column>

                  <el-table-column label="满减金额" min-width="150">
                    <template #default="{ row }">{{ resolveThresholdText(row) }}</template>
                  </el-table-column>

                  <el-table-column label="折扣金额" min-width="150">
                    <template #default="{ row }">{{ resolveDiscountAmountText(row) }}</template>
                  </el-table-column>

                  <el-table-column label="状态" min-width="110">
                    <template #default="{ row }">
                      <el-tag effect="light" :type="resolveStatusTagType(row.status)">
                        {{ row.status_label || row.status || '--' }}
                      </el-tag>
                    </template>
                  </el-table-column>

                  <el-table-column label="有效期" min-width="180" show-overflow-tooltip>
                    <template #default="{ row }">{{ row.validity_text || row.expires_at || '--' }}</template>
                  </el-table-column>

                  <el-table-column label="操作" width="96" fixed="right" align="right">
                    <template #default="{ row }">
                      <button type="button" class="coupon-detail-button" @click="openCouponDetail(row)">详情</button>
                    </template>
                  </el-table-column>
                </el-table>
              </div>
            </template>

            <el-empty v-else-if="!ownedState.loading" description="你还没有优惠券" />
          </div>

          <div v-if="ownedState.total > 0" class="pager-wrap">
            <el-pagination
              v-model:current-page="ownedState.page"
              v-model:page-size="ownedState.pageSize"
              :page-sizes="[10, 20, 50]"
              :total="ownedState.total"
              layout="total, sizes, prev, pager, next"
              @current-change="handlePageChange('owned')"
              @size-change="handlePageSizeChange('owned')"
            />
          </div>
        </el-tab-pane>

        <el-tab-pane label="优惠券广场" name="plaza">
          <div v-loading="plazaState.loading" class="coupon-list-shell">
            <template v-if="plazaState.list.length">
              <div v-show="viewMode === 'grid'" class="coupon-grid">
                <article v-for="item in plazaState.list" :key="item.id" class="coupon-card coupon-card--plaza">
                  <button class="coupon-card__info" type="button" aria-label="查看优惠券详情" @click="openCouponDetail(item)">
                    <el-icon><InfoFilled /></el-icon>
                  </button>

                  <div class="coupon-card__head">
                    <div class="coupon-card__value">
                      <span>{{ resolveDiscountTypeLabel(item.discount_type) }}</span>
                      <strong>{{ resolveDiscountValue(item) }}</strong>
                    </div>
                    <el-tag effect="light" :type="resolveStatusTagType(item.status)">
                      {{ item.status_label || item.status || '--' }}
                    </el-tag>
                  </div>

                  <div class="coupon-card__body">
                    <div class="coupon-card__title">
                      <strong>{{ item.name || '优惠券' }}</strong>
                      <p>{{ item.description || item.status_reason || '领取后可在结算时使用' }}</p>
                    </div>

                    <div class="coupon-card__amounts">
                      <span>{{ resolveThresholdText(item) }}</span>
                      <span>{{ resolveDiscountAmountText(item) }}</span>
                    </div>
                  </div>

                  <div class="coupon-card__foot coupon-card__foot--action">
                    <span v-if="item.remaining_stock !== null">剩余 {{ item.remaining_stock }} 张</span>
                    <span v-else>{{ item.status_reason || '领取后进入你的优惠券账户' }}</span>
                    <el-button
                      size="small"
                      type="primary"
                      :disabled="!item.can_claim"
                      :loading="claimingId === item.id"
                      @click="claimCoupon(item.id)"
                    >
                      {{ item.can_claim ? '领取' : (item.status_label || '不可领') }}
                    </el-button>
                  </div>
                </article>
              </div>

              <div v-show="viewMode === 'list'" class="coupon-table-card">
                <el-table
                  :data="plazaState.list"
                  row-key="id"
                  table-layout="auto"
                  class="coupon-list-table"
                  empty-text="当前暂无可领取的优惠券"
                >
                  <el-table-column label="优惠券信息" min-width="280">
                    <template #default="{ row }">
                      <div class="coupon-table-info">
                        <div class="coupon-table-mark">
                          {{ resolveDiscountTypeLabel(row.discount_type).slice(0, 2) }}
                        </div>
                        <div class="coupon-table-copy">
                          <div class="coupon-table-title-row">
                            <strong>{{ row.name || '优惠券' }}</strong>
                            <span class="coupon-table-id">ID {{ row.id }}</span>
                          </div>
                          <p>{{ row.description || row.status_reason || '领取后可在结算时使用' }}</p>
                        </div>
                      </div>
                    </template>
                  </el-table-column>

                  <el-table-column label="优惠类型" min-width="110">
                    <template #default="{ row }">{{ resolveDiscountTypeLabel(row.discount_type) }}</template>
                  </el-table-column>

                  <el-table-column label="满减金额" min-width="150">
                    <template #default="{ row }">{{ resolveThresholdText(row) }}</template>
                  </el-table-column>

                  <el-table-column label="折扣金额" min-width="150">
                    <template #default="{ row }">{{ resolveDiscountAmountText(row) }}</template>
                  </el-table-column>

                  <el-table-column label="状态" min-width="110">
                    <template #default="{ row }">
                      <el-tag effect="light" :type="resolveStatusTagType(row.status)">
                        {{ row.status_label || row.status || '--' }}
                      </el-tag>
                    </template>
                  </el-table-column>

                  <el-table-column label="有效期" min-width="180" show-overflow-tooltip>
                    <template #default="{ row }">{{ row.validity_text || row.expires_at || '--' }}</template>
                  </el-table-column>

                  <el-table-column label="操作" width="172" fixed="right" align="right">
                    <template #default="{ row }">
                      <div class="coupon-table-actions">
                        <button type="button" class="coupon-detail-button" @click="openCouponDetail(row)">详情</button>
                        <el-button
                          size="small"
                          type="primary"
                          :disabled="!row.can_claim"
                          :loading="claimingId === row.id"
                          @click="claimCoupon(row.id)"
                        >
                          {{ row.can_claim ? '领取' : '不可领' }}
                        </el-button>
                      </div>
                    </template>
                  </el-table-column>
                </el-table>
              </div>
            </template>

            <el-empty v-else-if="!plazaState.loading" description="当前暂无可领取的优惠券" />
          </div>

          <div v-if="plazaState.total > 0" class="pager-wrap">
            <el-pagination
              v-model:current-page="plazaState.page"
              v-model:page-size="plazaState.pageSize"
              :page-sizes="[10, 20, 50]"
              :total="plazaState.total"
              layout="total, sizes, prev, pager, next"
              @current-change="handlePageChange('plaza')"
              @size-change="handlePageSizeChange('plaza')"
            />
          </div>
        </el-tab-pane>
      </el-tabs>

    <el-drawer
      v-model="couponDrawerVisible"
      title="优惠券详情"
      direction="rtl"
      :size="drawerSize"
      destroy-on-close
    >
      <div v-if="selectedCoupon" class="coupon-detail-drawer">
        <div class="coupon-detail-hero">
          <span>{{ resolveDiscountTypeLabel(selectedCoupon.discount_type) }}</span>
          <strong>{{ resolveDiscountValue(selectedCoupon) }}</strong>
          <p>{{ selectedCoupon.name || '优惠券' }}</p>
        </div>

        <el-descriptions :column="1" border class="coupon-detail-descriptions">
          <el-descriptions-item label="优惠券名称">{{ selectedCoupon.name || '优惠券' }}</el-descriptions-item>
          <el-descriptions-item label="优惠类型">
            {{ resolveDiscountTypeLabel(selectedCoupon.discount_type) }}
          </el-descriptions-item>
          <el-descriptions-item label="满减金额">{{ resolveThresholdText(selectedCoupon) }}</el-descriptions-item>
          <el-descriptions-item label="折扣金额">{{ resolveDiscountAmountText(selectedCoupon) }}</el-descriptions-item>
          <el-descriptions-item label="优惠范围">
            {{ selectedCoupon.discount_scope_label || '优惠券' }}
          </el-descriptions-item>
          <el-descriptions-item label="适用产品">
            <div v-if="couponProductHierarchyLoading" class="coupon-hierarchy-loading">
              正在加载产品层级...
            </div>
            <div v-else-if="couponProductHierarchy.length" class="coupon-hierarchy-sheet">
              <table class="coupon-hierarchy-table">
                <thead>
                  <tr>
                    <th>一级菜单</th>
                    <th>二级菜单</th>
                    <th>三级菜单</th>
                    <th>子产品</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="item in mergedCouponProductHierarchy" :key="item.productId">
                    <td v-if="item.level1Rowspan" :rowspan="item.level1Rowspan">{{ item.level1 || '--' }}</td>
                    <td v-if="item.level2Rowspan" :rowspan="item.level2Rowspan">{{ item.level2 || '--' }}</td>
                    <td v-if="item.level3Rowspan" :rowspan="item.level3Rowspan">{{ item.level3 || '--' }}</td>
                    <td>{{ item.productName || '--' }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
            <span v-else>{{ selectedCoupon.product_scope_text || '全场通用' }}</span>
          </el-descriptions-item>
          <el-descriptions-item label="适用周期">
            {{ selectedCoupon.billing_cycle_text || '全部周期' }}
          </el-descriptions-item>
          <el-descriptions-item label="有效期">
            {{ selectedCoupon.validity_text || selectedCoupon.expires_at || '--' }}
          </el-descriptions-item>
          <el-descriptions-item label="状态">
            {{ selectedCoupon.status_label || selectedCoupon.status || '--' }}
          </el-descriptions-item>
          <el-descriptions-item v-if="selectedCoupon.remaining_stock !== null" label="剩余库存">
            {{ selectedCoupon.remaining_stock }} 张
          </el-descriptions-item>
          <el-descriptions-item v-if="selectedCoupon.receive_type_label" label="获取方式">
            {{ selectedCoupon.receive_type_label }}
          </el-descriptions-item>
          <el-descriptions-item label="使用说明">
            {{ selectedCoupon.description || selectedCoupon.status_reason || '满足条件后可在结算时直接抵扣' }}
          </el-descriptions-item>
        </el-descriptions>
      </div>
    </el-drawer>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { Grid, InfoFilled, Search, Tickets } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import siteApi from '@/api/site'
import { useCoupons } from '@/composables/useCoupons'
import { resolveDialogWidth, useViewport } from '@/composables/useViewport'

const {
  activeTab,
  claimingId,
  viewMode,
  ownedState,
  plazaState,
  loadData,
  handleSearch,
  handlePageChange,
  handlePageSizeChange,
  switchTab,
  setViewMode,
  restoreViewMode,
  claimCoupon,
} = useCoupons()

const { viewportWidth } = useViewport()
const drawerSize = computed(() => resolveDialogWidth(viewportWidth.value, 520))
const selectedCoupon = ref(null)
const couponDrawerVisible = ref(false)
const couponProductHierarchyLoading = ref(false)
const couponProductHierarchy = ref([])
const nextViewMode = computed(() => (viewMode.value === 'grid' ? 'list' : 'grid'))
const toggleIcon = computed(() => (viewMode.value === 'grid' ? Tickets : Grid))
const currentState = computed(() => (activeTab.value === 'plaza' ? plazaState : ownedState))
const couponProductHierarchyCache = new Map()

const mergedCouponProductHierarchy = computed(() => {
  const data = couponProductHierarchy.value
  if (!data.length) return []

  // 先排序，确保同级内容相邻
  const sorted = [...data].sort((a, b) => {
    const l1 = (a.level1 || '').localeCompare(b.level1 || '', 'zh-CN')
    if (l1 !== 0) return l1
    const l2 = (a.level2 || '').localeCompare(b.level2 || '', 'zh-CN')
    if (l2 !== 0) return l2
    const l3 = (a.level3 || '').localeCompare(b.level3 || '', 'zh-CN')
    if (l3 !== 0) return l3
    return (a.productName || '').localeCompare(b.productName || '', 'zh-CN')
  })

  const result = sorted.map((item, index) => ({
    ...item,
    index,
    level1Rowspan: 0,
    level2Rowspan: 0,
    level3Rowspan: 0,
  }))

  // 一级菜单：连续相同值合并
  let i = 0
  while (i < result.length) {
    let j = i + 1
    while (j < result.length && result[j].level1 === result[i].level1) j++
    result[i].level1Rowspan = j - i
    i = j
  }

  // 二级菜单：仅在同一级菜单内合并
  i = 0
  while (i < result.length) {
    const l1End = i + result[i].level1Rowspan
    let j = i
    while (j < l1End) {
      let k = j + 1
      while (k < l1End && result[k].level2 === result[j].level2) k++
      result[j].level2Rowspan = k - j
      j = k
    }
    i = l1End
  }

  // 三级菜单：仅在同一二级菜单内合并
  i = 0
  while (i < result.length) {
    const l2End = i + result[i].level2Rowspan
    let j = i
    while (j < l2End) {
      let k = j + 1
      while (k < l2End && result[k].level3 === result[j].level3) k++
      result[j].level3Rowspan = k - j
      j = k
    }
    i = l2End
  }

  return result
})
const currentStatusOptions = computed(() => {
  if (activeTab.value === 'plaza') {
    return [
      { label: '可领取', value: 'available' },
      { label: '已领完', value: 'used_up' },
      { label: '已过期', value: 'expired' },
    ]
  }

  return [
    { label: '可用', value: 'available' },
    { label: '已用完', value: 'used_up' },
    { label: '已过期', value: 'expired' },
  ]
})

function handleTabChange(name) {
  void switchTab(String(name || 'owned'))
}

async function openCouponDetail(item) {
  selectedCoupon.value = item
  couponDrawerVisible.value = true
  await loadCouponProductHierarchy(item)
}

function resolveStatusTagType(status) {
  if (status === 'available') return 'success'
  if (status === 'used_up') return 'warning'
  return 'info'
}

function resolveDiscountTypeLabel(type) {
  if (type === 'fixed') return '满减券'
  if (type === 'percentage') return '折扣券'
  return '优惠券'
}

function formatCouponAmount(value) {
  const amount = Number(value || 0)
  if (!Number.isFinite(amount) || amount <= 0) return '0'
  return amount % 1 === 0 ? String(amount) : amount.toFixed(2)
}

function resolveDiscountValue(item) {
  if (item.discount_type === 'fixed') {
    return `¥${formatCouponAmount(item.discount_value)}`
  }

  if (item.discount_type === 'percentage') {
    const discount = Number(item.discount_value || 0) / 10
    if (!Number.isFinite(discount) || discount <= 0) return item.discount_label || '--'
    return `${discount % 1 === 0 ? discount.toFixed(0) : discount.toFixed(1)}折`
  }

  return item.discount_label || '--'
}

function resolveThresholdText(item) {
  const amount = Number(item.min_amount || 0)
  return amount > 0 ? `满 ¥${formatCouponAmount(amount)} 可用` : '无门槛'
}

function resolveDiscountAmountText(item) {
  if (item.discount_type === 'fixed') {
    return `减 ¥${formatCouponAmount(item.discount_value)}`
  }

  if (item.discount_type === 'percentage') {
    return item.max_discount_amount
      ? `最高减 ¥${formatCouponAmount(item.max_discount_amount)}`
      : (item.discount_label || '--')
  }

  return item.discount_amount ? `减 ¥${formatCouponAmount(item.discount_amount)}` : (item.discount_label || '--')
}

async function loadCouponProductHierarchy(coupon) {
  // 优先使用后端直接返回的产品层级数据
  const serverProducts = Array.isArray(coupon?.products) ? coupon.products : []

  if (serverProducts.length) {
    const hierarchy = serverProducts
      .map((item) => {
        if (!item || typeof item !== 'object') return null
        return {
          productId: Number(item.id || 0),
          level1: String(item.type_label || '--').trim() || '--',
          level2: String(item.parent_group_name || '--').trim() || '--',
          level3: String(item.group_name || '--').trim() || '--',
          productName: String(item.name || '--').trim() || '--',
        }
      })
      .filter(Boolean)

    couponProductHierarchy.value = hierarchy
    return
  }

  // 回退：从 product_ids 逐个请求产品详情
  const productIds = Array.isArray(coupon?.product_ids)
    ? coupon.product_ids.map((id) => Number(id || 0)).filter((id) => id > 0)
    : []

  if (!productIds.length) {
    couponProductHierarchy.value = []
    return
  }

  const cacheKey = productIds.slice().sort((a, b) => a - b).join(',')
  if (couponProductHierarchyCache.has(cacheKey)) {
    couponProductHierarchy.value = couponProductHierarchyCache.get(cacheKey) || []
    return
  }

  couponProductHierarchyLoading.value = true

  try {
    const detailList = await Promise.all(productIds.map((productId) => siteApi.product(productId)))
    const hierarchy = detailList
      .map((response) => normalizeCouponProductHierarchy(response?.data?.product || null))
      .filter(Boolean)

    couponProductHierarchyCache.set(cacheKey, hierarchy)
    couponProductHierarchy.value = hierarchy
  } catch (error) {
    couponProductHierarchy.value = []
    if (!error?.__handled) {
      ElMessage.error(error?.message || '优惠券产品层级加载失败')
    }
  } finally {
    couponProductHierarchyLoading.value = false
  }
}

function normalizeCouponProductHierarchy(product) {
  if (!product || typeof product !== 'object') {
    return null
  }

  const group = product.group && typeof product.group === 'object' ? product.group : {}
  const typeLabel = String(product.type_label || group.parent_product_type || group.product_type_label || '').trim()
  const level2 = String(group.parent_name || '').trim()
  const level3 = String(group.name || '').trim()
  const productName = String(product.display_name || product.name || '').trim()

  return {
    productId: Number(product.id || 0),
    level1: typeLabel || '--',
    level2: level2 || '--',
    level3: level3 || '--',
    productName: productName || '--',
  }
}

onMounted(() => {
  restoreViewMode()
  void loadData('owned')
})
</script>

<style scoped lang="scss">
.coupons-page {
  gap: 20px;
}

.coupon-filter-card {
  border: 1px solid rgba(225, 231, 241, 0.9);
  border-radius: 18px;
  background: linear-gradient(180deg, #ffffff 0%, #fbfcff 100%);
  box-shadow: 0 12px 28px rgba(20, 47, 88, 0.05);
}

.coupon-filter-bar {
  display: grid;
  grid-template-columns: minmax(240px, 1.5fr) minmax(160px, 0.72fr) auto;
  gap: 14px;
  align-items: center;
  padding: 18px;
}

.coupon-filter-bar__toggle {
  min-width: 96px;
}

.coupon-search-trigger {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 28px;
  border: none;
  border-radius: 8px;
  background: rgba(76, 132, 255, 0.08);
  color: #3978ff;
  cursor: pointer;
}

.coupon-filter-bar {
  :deep(.el-input__wrapper),
  :deep(.el-select__wrapper) {
    min-height: 42px;
    border: 1px solid #dfe6f1;
    border-radius: 12px;
    background: #fff;
    box-shadow: none;
  }
}

.coupon-tabs {
  :deep(.el-tabs__header) {
    margin-bottom: 20px;
  }

  :deep(.el-tabs__nav-wrap::after) {
    background: rgba(15, 23, 42, 0.08);
  }
}

.coupon-list-shell {
  min-height: 240px;
}

.coupon-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
  gap: 16px;
}

.coupon-card {
  position: relative;
  width: 100%;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  padding: 18px 20px 16px;
  border: 1px solid rgba(225, 231, 241, 0.9);
  border-radius: 16px;
  background:
    radial-gradient(circle at top left, rgba(76, 132, 255, 0.06), transparent 28%),
    linear-gradient(180deg, #ffffff 0%, #fbfcff 100%);
  box-shadow: 0 8px 20px rgba(20, 47, 88, 0.04);
  transition: border-color $motion-fast ease, box-shadow $motion-fast ease, transform $motion-fast ease;

  &:hover {
    border-color: rgba(76, 132, 255, 0.22);
    box-shadow: 0 14px 28px rgba(20, 47, 88, 0.07);
    transform: translateY(-2px);
  }
}

.coupon-card__info {
  display: inline-flex;
  position: absolute;
  top: 12px;
  right: 12px;
  z-index: 2;
  align-items: center;
  justify-content: center;
  width: 24px;
  height: 24px;
  padding: 0;
  border: 1px solid rgba(245, 158, 11, 0.28);
  border-radius: 50%;
  background: $color-warning-soft;
  color: $color-warning;
  cursor: pointer;
}

.coupon-card__info:hover,
.coupon-card__info:focus-visible {
  border-color: rgba(245, 158, 11, 0.48);
  background: #fff1cc;
  color: #c76a05;
  outline: none;
}

.coupon-card__head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
  padding-right: 28px;
}

.coupon-card__value {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}

.coupon-card__value span {
  color: #3978ff;
  font-size: 12px;
  font-weight: 700;
  letter-spacing: 0.02em;
}

.coupon-card__value strong {
  color: #19263d;
  font-size: 28px;
  font-weight: 800;
  line-height: 1.1;
  letter-spacing: -0.01em;
}

.coupon-card__body {
  display: flex;
  flex-direction: column;
  gap: 10px;
  flex: 1;
  margin-top: 14px;
  min-height: 0;
}

.coupon-card__title {
  display: flex;
  flex-direction: column;
  gap: 4px;
  min-width: 0;
}

.coupon-card__title strong {
  display: -webkit-box;
  overflow: hidden;
  color: #19263d;
  font-size: 15px;
  font-weight: 700;
  line-height: 1.35;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 1;
}

.coupon-card__title p {
  display: -webkit-box;
  overflow: hidden;
  margin: 0;
  color: #8b96a9;
  font-size: 12px;
  line-height: 1.45;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 1;
}

.coupon-card__amounts {
  display: flex;
  flex-wrap: wrap;
  gap: 6px 8px;
}

.coupon-card__amounts span {
  display: inline-flex;
  align-items: center;
  height: 22px;
  padding: 0 8px;
  border-radius: 6px;
  background: #f0f4fa;
  color: #5d6b83;
  font-size: 11px;
  font-weight: 600;
}

.coupon-card__foot {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-top: auto;
  padding-top: 12px;
  border-top: 1px solid #edf1f7;
}

.coupon-card__foot span {
  color: #6f7d93;
  font-size: 11px;
  line-height: 1.4;
}

.coupon-card__foot--action {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.coupon-card__foot--action span {
}

.coupon-card__foot--action {
  align-items: center;
}

.coupon-table-card {
  overflow: hidden;
  border: 1px solid rgba(225, 231, 241, 0.9);
  border-radius: 18px;
  background: #fff;
  box-shadow: 0 12px 28px rgba(20, 47, 88, 0.05);
}

.coupon-list-table {
  :deep(.el-table__inner-wrapper::before) {
    display: none;
  }

  :deep(.el-table__header-wrapper th) {
    background: #f8faff;
    color: #74839a;
    font-size: 12px;
    font-weight: 700;
  }

  :deep(.el-table__row td) {
    padding-top: 18px;
    padding-bottom: 18px;
  }
}

.coupon-table-info {
  display: flex;
  align-items: flex-start;
  gap: 14px;
}

.coupon-table-mark {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 54px;
  min-width: 54px;
  height: 54px;
  border-radius: 14px;
  background: linear-gradient(145deg, #f5f8ff, #eaf0fb);
  color: #3978ff;
  font-size: 16px;
  font-weight: 800;
}

.coupon-table-copy {
  min-width: 0;
}

.coupon-table-title-row {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 10px;
}

.coupon-table-title-row strong {
  color: #19263d;
  font-size: 15px;
  font-weight: 700;
}

.coupon-table-copy p {
  display: -webkit-box;
  overflow: hidden;
  margin: 8px 0 0;
  color: #7d8aa0;
  font-size: 12px;
  line-height: 1.5;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 1;
}

.coupon-table-id {
  display: inline-flex;
  align-items: center;
  min-height: 22px;
  padding: 0 8px;
  border-radius: 999px;
  background: #f4f7fb;
  color: #91a0b6;
  font-size: 12px;
  font-weight: 600;
}

.coupon-table-actions {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
}

.coupon-detail-button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 52px;
  height: 30px;
  padding: 0 11px;
  border: none;
  border-radius: 10px;
  background: transparent;
  color: #256dff;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
}

.pager-wrap {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  margin-top: 16px;
}

.coupon-detail-drawer {
  display: grid;
  gap: 18px;
}

.coupon-detail-hero {
  display: grid;
  gap: 8px;
  padding: 22px;
  border: 1px solid #dfe6f1;
  border-radius: 16px;
  background:
    radial-gradient(circle at top right, rgba(221, 122, 31, 0.16), transparent 32%),
    linear-gradient(145deg, #f8faff, #ffffff);
}

.coupon-detail-hero span {
  color: #3978ff;
  font-size: 13px;
  font-weight: 700;
}

.coupon-detail-hero strong {
  color: #19263d;
  font-size: 36px;
  font-weight: 800;
  line-height: 1.1;
}

.coupon-detail-hero p {
  margin: 0;
  color: #5d6b83;
  font-size: 14px;
}

.coupon-detail-descriptions {
  :deep(.el-descriptions__label) {
    width: 98px;
    color: #74839a;
    font-weight: 600;
  }
}

.coupon-hierarchy-loading {
  color: #7d8aa0;
  font-size: 13px;
}

.coupon-hierarchy-sheet {
  overflow: hidden;
  border: 1px solid #dbe4f0;
  border-radius: 10px;
  background: #fff;
}

.coupon-hierarchy-table {
  width: 100%;
  border-collapse: collapse;
  table-layout: fixed;
}

.coupon-hierarchy-table th,
.coupon-hierarchy-table td {
  padding: 10px 12px;
  border-right: 1px solid #dbe4f0;
  border-bottom: 1px solid #dbe4f0;
  font-size: 12px;
  line-height: 1.5;
  word-break: break-word;
  text-align: left;
  vertical-align: middle;
}

.coupon-hierarchy-table th {
  color: #6f7d93;
  font-weight: 700;
  background: #f5f8fd;
}

.coupon-hierarchy-table td {
  color: #1f2a44;
  background: #fff;
}

.coupon-hierarchy-table th:last-child,
.coupon-hierarchy-table td:last-child {
  border-right: none;
}

.coupon-hierarchy-table tbody tr:last-child td {
  border-bottom: none;
}

@media (max-width: 767px) {
  .coupon-hierarchy-sheet {
    overflow-x: auto;
  }

  .coupon-hierarchy-table {
    min-width: 460px;
  }
}

@media (max-width: 1080px) {
  .coupon-filter-bar {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 960px) {
  .coupon-grid {
    grid-template-columns: minmax(0, 1fr);
  }

  .pager-wrap {
    justify-content: flex-start;
  }
}

@media (max-width: 767px) {
  .coupon-filter-card {
    border-radius: 22px;
    background: linear-gradient(180deg, #f9fbff 0%, #f6f8fd 100%);
    box-shadow: 0 14px 28px rgba(20, 47, 88, 0.06);
  }

  .coupon-filter-bar {
    grid-template-columns: minmax(0, 1fr) 104px 68px;
    gap: 10px;
    align-items: stretch;
    padding: 14px 12px;
  }

  .coupon-filter-bar__search,
  .coupon-filter-bar__select,
  .coupon-filter-bar__toggle {
    width: 100%;
  }

  .coupon-filter-bar__toggle {
    min-width: 0;
    min-height: 44px;
    border-radius: 14px;
  }

  .coupon-card {
    padding: 14px 16px;
  }

  .coupon-card__value strong {
    font-size: 24px;
  }
}
</style>
