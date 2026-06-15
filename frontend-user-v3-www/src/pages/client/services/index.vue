<template>
  <div class="client-page service-page">
    <ServiceFilters
      :filters="filters"
      :status-options="statusOptions"
      :catalog-type-options="catalogTypeOptions"
      :view-mode="viewMode"
      :view-mode-options="viewModeOptions"
      @search="handleSearch"
      @reset="resetFilters"
      @pick-category="pickCategory"
      @change-view-mode="setViewMode"
    />

    <section class="service-list-shell" v-loading="loading">
      <template v-if="list.length">
        <div v-if="mountedViews.grid" v-show="viewMode === 'grid'" class="service-card-grid">
          <ServiceCard
            v-for="item in list"
            :key="item.id"
            :item="item"
            @open-detail="openDetail"
            @open-remark="openRemark"
            @action="handleServiceAction"
            @copy-ip="copyText"
          />
        </div>
        <ServiceListTable
          v-if="mountedViews.list"
          v-show="viewMode === 'list'"
          :items="list"
          @open-detail="openDetail"
          @open-remark="openRemark"
          @action="handleServiceAction"
          @copy-ip="copyText"
        />
      </template>

      <el-empty v-else-if="!loading" description="当前还没有任何服务实例">
        <el-button type="primary" @click="router.push('/products')">去选购产品</el-button>
      </el-empty>
    </section>

    <div v-if="total > 0" class="service-pagination">
      <el-pagination
        v-model:current-page="filters.page"
        v-model:page-size="filters.page_size"
        :page-sizes="[10, 20, 50]"
        :total="total"
        layout="total, sizes, prev, pager, next"
        @current-change="loadList"
        @size-change="handlePageSizeChange"
      />
    </div>

    <el-dialog v-model="renewVisible" title="服务续费" :width="renewDialogWidth" destroy-on-close>
      <div v-loading="renewPreviewLoading" class="renew-dialog-body">
        <template v-if="renewData">
          <div class="renew-summary-card">
            <div class="renew-summary-row">
              <span>服务实例</span>
              <strong>{{ resolveServiceName(renewTarget) }}</strong>
            </div>
            <div class="renew-summary-row">
              <span>当前到期</span>
              <strong>{{ renewData.expires_at || '--' }}</strong>
            </div>
            <div class="renew-summary-row">
              <span>自动续费</span>
              <strong>{{ Number(renewData.auto_renew) === 1 ? '已开启' : '未开启' }}</strong>
            </div>
          </div>

          <el-radio-group v-model="renewForm.billing_cycle" class="renew-cycle-group" @change="handleRenewCycleChange">
            <el-radio-button
              v-for="cycle in renewData.cycles || []"
              :key="cycle.billing_cycle"
              :value="cycle.billing_cycle"
            >
              <span>{{ cycle.billing_cycle_label }}</span>
              <strong>￥{{ formatMoney(cycle.amount) }}</strong>
            </el-radio-button>
          </el-radio-group>

          <div v-if="availableRenewCoupons.length" class="renew-coupon-row">
            <span>续费优惠</span>
            <el-select
              :model-value="renewForm.user_coupon_id || undefined"
              clearable
              placeholder="选择优惠券"
              @change="handleRenewCouponChange"
            >
              <el-option
                v-for="coupon in availableRenewCoupons"
                :key="coupon.id"
                :label="`${coupon.name} · ${coupon.discount_label}`"
                :value="coupon.id"
              />
            </el-select>
          </div>

          <div class="renew-total-line">
            <span>本次应付</span>
            <strong>￥{{ selectedRenewAmount }}</strong>
          </div>
        </template>

        <el-empty v-else-if="!renewPreviewLoading" description="未获取到可续费周期" :image-size="64" />
      </div>

      <template #footer>
        <el-button @click="renewVisible = false">取消</el-button>
        <el-button type="primary" :loading="renewSubmitting" :disabled="!renewForm.billing_cycle" @click="submitRenew">
          创建续费账单
        </el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="remarkVisible" title="编辑备注" :width="remarkDialogWidth" destroy-on-close>
      <el-input
        v-model="remarkForm.remark"
        type="textarea"
        :rows="4"
        maxlength="120"
        show-word-limit
        placeholder="填写服务备注，便于快速识别"
      />

      <template #footer>
        <el-button @click="remarkVisible = false">取消</el-button>
        <el-button type="primary" :loading="remarkSubmitting" @click="submitRemark">保存备注</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { computed, reactive, watch } from 'vue'
import ServiceFilters from '@/features/services/ServiceFilters.vue'
import ServiceCard from '@/widgets/services/ServiceCard.vue'
import ServiceListTable from '@/widgets/services/ServiceListTable.vue'
import {
  formatMoney,
  resolveServiceName,
  useServiceCenter,
} from '@/domains/services/useServiceCenter'
import { resolveDialogWidth, useViewport } from '@/composables/useViewport'

const { viewportWidth } = useViewport()
const renewDialogWidth = computed(() => resolveDialogWidth(viewportWidth.value, 520))
const remarkDialogWidth = computed(() => resolveDialogWidth(viewportWidth.value, 420))
const mountedViews = reactive({
  grid: true,
  list: false,
})

const {
  loading,
  list,
  total,
  filters,
  viewMode,
  viewModeOptions,
  renewVisible,
  renewPreviewLoading,
  renewSubmitting,
  renewTarget,
  renewData,
  remarkVisible,
  remarkSubmitting,
  renewForm,
  remarkForm,
  statusOptions,
  catalogTypeOptions,
  selectedRenewAmount,
  availableRenewCoupons,
  loadList,
  handleSearch,
  handlePageSizeChange,
  pickCategory,
  resetFilters,
  setViewMode,
  openDetail,
  handleRenewCycleChange,
  handleRenewCouponChange,
  submitRenew,
  openRemark,
  submitRemark,
  copyText,
  handleServiceAction,
  router,
} = useServiceCenter()

watch(
  viewMode,
  (mode) => {
    mountedViews[mode] = true
  },
  { immediate: true },
)
</script>

<style lang="scss" scoped>
.service-page {
  gap: 20px;
}

.service-list-shell {
  min-height: 240px;
}

.service-card-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(389px, 389px));
  justify-content: start;
  gap: 16px;
}

.service-pagination {
  display: flex;
  justify-content: flex-end;
}

.renew-dialog-body {
  min-height: 140px;
}

.renew-summary-card {
  display: grid;
  gap: 10px;
  padding: 16px 18px;
  border: 1px solid #e4e9f2;
  border-radius: 14px;
  background: #f8faff;
}

.renew-summary-row,
.renew-total-line,
.renew-coupon-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;

  span {
    color: #74839a;
    font-size: 13px;
  }

  strong {
    color: #1f2a44;
    font-size: 14px;
    font-weight: 700;
  }
}

.renew-cycle-group {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
  gap: 12px;
  margin-top: 18px;

  :deep(.el-radio-button__inner) {
    width: 100%;
    min-height: 72px;
    border-radius: 14px !important;
    border-left: 1px solid #dfe6f1 !important;
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    justify-content: center;
    gap: 8px;
    box-shadow: none !important;
  }
}

.renew-coupon-row,
.renew-total-line {
  margin-top: 18px;
}

.service-page {
  :deep(.el-dialog__body) {
    padding-top: 18px;
  }
}

@media (max-width: 960px) {
  .service-card-grid {
    grid-template-columns: minmax(0, 1fr);
  }

  .service-pagination {
    justify-content: flex-start;
  }
}
</style>
