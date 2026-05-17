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

    <section class="panel-card">
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
    </section>

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
              <div class="coupon-hierarchy-sheet__head">
                <span>一级菜单</span>
                <span>二级菜单</span>
                <span>三级菜单</span>
                <span>子产品</span>
              </div>
              <div
                v-for="item in couponProductHierarchy"
                :key="item.productId"
                class="coupon-hierarchy-sheet__row"
              >
                <span>{{ item.level1 || '--' }}</span>
                <span>{{ item.level2 || '--' }}</span>
                <span>{{ item.level3 || '--' }}</span>
                <span>{{ item.productName || '--' }}</span>
              </div>
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

.coupon-filter-card,
.panel-card {
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

.panel-card {
  padding: 20px;
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
  grid-template-columns: repeat(auto-fit, minmax(389px, 389px));
  justify-content: start;
  gap: 16px;
}

.coupon-card {
  position: relative;
  width: 100%;
  aspect-ratio: 389 / 187;
  overflow: hidden;
  padding: 16px 18px 14px;
  border: 1px solid rgba(225, 231, 241, 0.9);
  border-radius: 16px;
  background:
    radial-gradient(circle at top left, rgba(76, 132, 255, 0.07), transparent 26%),
    linear-gradient(180deg, #ffffff 0%, #fbfcff 100%);
  box-shadow: 0 10px 24px rgba(20, 47, 88, 0.05);
  transition: border-color $motion-fast ease, box-shadow $motion-fast ease, transform $motion-fast ease;

  &:hover {
    border-color: rgba(76, 132, 255, 0.24);
    box-shadow: 0 16px 32px rgba(20, 47, 88, 0.08);
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

.coupon-card__head,
.coupon-card__foot,
.coupon-card__foot--action {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
}

.coupon-card__head {
  padding-right: 28px;
}

.coupon-card__value {
  display: grid;
  gap: 4px;
  min-width: 0;
}

.coupon-card__value span {
  color: #3978ff;
  font-size: 12px;
  font-weight: 700;
}

.coupon-card__value strong {
  color: #19263d;
  font-size: 30px;
  font-weight: 800;
  line-height: 1.05;
}

.coupon-card__body {
  display: grid;
  gap: 12px;
  margin-top: 12px;
}

.coupon-card__title {
  display: grid;
  gap: 4px;
  min-width: 0;
}

.coupon-card__title strong {
  display: -webkit-box;
  overflow: hidden;
  color: #19263d;
  font-size: 14px;
  font-weight: 700;
  line-height: 1.3;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 1;
}

.coupon-card__title p {
  display: -webkit-box;
  overflow: hidden;
  margin: 0;
  color: #7d8aa0;
  font-size: 11px;
  line-height: 1.4;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 1;
}

.coupon-card__amounts {
  display: flex;
  flex-wrap: wrap;
  gap: 6px 10px;
}

.coupon-card__amounts span {
  display: inline-flex;
  align-items: center;
  min-height: 24px;
  padding: 0 9px;
  border-radius: 999px;
  background: #f4f7fb;
  color: #5d6b83;
  font-size: 11px;
  font-weight: 700;
}

.coupon-card__foot {
  margin-top: auto;
  padding-top: 10px;
  border-top: 1px solid #edf1f7;
}

.coupon-card__foot span,
.coupon-card__foot--action span {
  color: #6f7d93;
  font-size: 11px;
  line-height: 1.4;
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

.coupon-hierarchy-sheet__head,
.coupon-hierarchy-sheet__row {
  display: grid;
  grid-template-columns: 96px 96px 96px minmax(160px, 1fr);
}

.coupon-hierarchy-sheet__head {
  background: #f5f8fd;
}

.coupon-hierarchy-sheet__head span,
.coupon-hierarchy-sheet__row span {
  min-width: 0;
  padding: 10px 12px;
  border-right: 1px solid #dbe4f0;
  border-bottom: 1px solid #dbe4f0;
  font-size: 12px;
  line-height: 1.5;
  word-break: break-word;
}

.coupon-hierarchy-sheet__head span {
  color: #6f7d93;
  font-weight: 700;
}

.coupon-hierarchy-sheet__row span {
  color: #1f2a44;
  background: #fff;
}

.coupon-hierarchy-sheet__head span:last-child,
.coupon-hierarchy-sheet__row span:last-child {
  border-right: none;
}

.coupon-hierarchy-sheet__row:last-child span {
  border-bottom: none;
}

@media (max-width: 767px) {
  .coupon-hierarchy-sheet {
    overflow-x: auto;
  }

  .coupon-hierarchy-sheet__head,
  .coupon-hierarchy-sheet__row {
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

  .panel-card {
    padding: 16px;
  }

  .coupon-card {
    aspect-ratio: 389 / 187;
    padding: 12px 14px;
  }

  .coupon-card__value strong {
    font-size: 26px;
  }
}
</style>
