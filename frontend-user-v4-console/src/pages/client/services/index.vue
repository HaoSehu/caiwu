<template>
  <section class="client-services">
    <t-card class="service-filter-card" :bordered="false">
      <div class="service-filter-bar">
        <t-input
          v-model="filters.keyword"
          clearable
          class="service-filter-bar__search"
          placeholder="搜索服务名称、域名、账单号"
          @enter="handleSearch"
          @clear="handleSearch"
        >
          <template #suffixIcon>
            <search-icon />
          </template>
        </t-input>

        <t-select v-model="filters.catalog_type" clearable placeholder="服务分类" @change="handleSearch">
          <t-option label="全部分类" value="" />
          <t-option
            v-for="item in catalogTypeOptions"
            :key="item.value"
            :label="`${item.label} (${item.count})`"
            :value="item.value"
          />
        </t-select>

        <t-select v-model="filters.status" clearable placeholder="状态分类" @change="handleSearch">
          <t-option v-for="item in statusOptions" :key="item.value" :label="item.label" :value="item.value" />
        </t-select>

        <t-select v-model="filters.auto_renew" clearable placeholder="续费方式" @change="handleSearch">
          <t-option label="全部" value="" />
          <t-option label="自动续费" value="1" />
          <t-option label="未开启自动续费" value="0" />
        </t-select>

        <div class="service-filter-actions">
          <t-button
            variant="outline"
            class="service-view-toggle"
            :aria-label="viewMode === 'grid' ? '切换为列表视图' : '切换为卡片视图'"
            @click="setViewMode(viewMode === 'grid' ? 'list' : 'grid')"
          >
            <template #icon>
              <catalog-icon v-if="viewMode === 'grid'" />
              <dashboard-icon v-else />
            </template>
            {{ viewMode === 'grid' ? '列表' : '卡片' }}
          </t-button>
        </div>
      </div>
    </t-card>

    <section class="service-list-section">
      <data-state
        :loading="loading"
        :empty="!loading && !error && !list.length"
        :error="!loading && Boolean(error)"
        :error-text="error || ''"
        :description="hasActiveFilters ? '未检索到匹配结果，请调整筛选条件' : '当前还没有任何服务实例'"
        @retry="loadData"
      >
        <template v-if="!hasActiveFilters" #empty-action>
          <t-button theme="primary" @click="router.push('/client/catalog')">去产品目录选购</t-button>
        </template>
        <template #default>
          <div v-if="viewMode === 'grid'" class="service-card-grid">
            <article v-for="item in list" :key="item.id" class="service-row-card">
              <div class="service-row-actions service-row-actions--corner">
                <button
                  type="button"
                  class="service-action-console"
                  :aria-label="`${resolveServiceName(item)} 控制台`"
                  @click="openDetail(item.id)"
                >
                  控制台
                </button>
                <t-dropdown
                  trigger="click"
                  :options="actionOptions(item)"
                  @click="({ value }: { value: string | number }) => handleServiceAction(String(value), item)"
                >
                  <button
                    type="button"
                    class="service-action-more"
                    :aria-label="`${resolveServiceName(item)} 更多操作`"
                  >
                    更多
                  </button>
                </t-dropdown>
              </div>
              <div class="service-row-head">
                <div class="service-system-icon" :class="{ 'is-provisioning': isProvisioningService(item) }">
                  <span v-if="isProvisioningService(item)" class="service-system-loader" aria-hidden="true">
                    <span class="service-system-loader__ring"></span>
                    <span class="service-system-loader__core"></span>
                  </span>
                  <img
                    v-else-if="shouldShowServiceOsIcon(item)"
                    :src="resolveServiceOsIcon(item)"
                    :alt="String(resolveServiceOsText(item) || resolveServiceName(item))"
                    class="service-system-icon__image"
                    @error="markServiceOsIconFailed(item)"
                  />
                  <span v-else class="service-system-icon__fallback">{{ resolveServiceMark(item) }}</span>
                </div>

                <div class="service-row-body">
                  <div class="service-row-body-top">
                    <div class="service-row-topline">
                      <div class="service-row-titleblock">
                        <div class="service-title-row">
                          <button type="button" class="service-name-button" @click="openDetail(item.id)">
                            {{ resolveServiceName(item) }}
                          </button>
                          <span class="service-row-id">ID {{ item.id }}</span>
                        </div>

                        <div class="service-remark-line">
                          <span
                            class="service-remark-text"
                            :class="{ empty: !item.remark }"
                            :title="item.remark || '编辑备注'"
                          >
                            {{ item.remark || '编辑备注' }}
                          </span>
                          <button
                            type="button"
                            class="service-remark-trigger"
                            :aria-label="item.remark ? '编辑备注' : '设置备注'"
                            @click="openRemark(item)"
                          >
                            <edit-icon />
                          </button>
                        </div>
                      </div>
                    </div>

                    <div class="service-spec-line">
                      <span>CPU {{ findListSpecValue(item, ['CPU', '核心']) }}</span>
                      <span>内存 {{ findListSpecValue(item, ['内存', 'RAM']) }}</span>
                      <span>带宽 {{ resolveListBandwidthText(item) }}</span>
                    </div>

                    <div class="service-expire-line" :class="{ warning: isExpiringSoon(item.expires_at) }">
                      到期时间：{{ item.expires_at || '长期有效' }}
                    </div>
                  </div>

                  <div class="service-row-foot">
                    <t-tag :theme="resolveTdesignStatusTheme(item)" variant="light" class="service-status-tag">
                      {{ resolveRuntimeStatusLabel(item) }}
                    </t-tag>

                    <div class="service-ip-line">
                      <span class="service-ip-label">公网 IP</span>
                      <button
                        type="button"
                        class="service-ip-button"
                        :class="{ disabled: !(item.upstream?.dedicated_ip && item.upstream.dedicated_ip !== '--') }"
                        :disabled="!(item.upstream?.dedicated_ip && item.upstream.dedicated_ip !== '--')"
                        :title="item.upstream?.dedicated_ip ? `点击复制 ${item.upstream.dedicated_ip}` : '暂无公网 IP'"
                        @click="copyText(item.upstream?.dedicated_ip || '')"
                      >
                        {{ item.upstream?.dedicated_ip || '--' }}
                      </button>
                    </div>
                  </div>
                </div>
              </div>
              <div v-if="Number(item.auto_renew) === 1" class="service-auto-renew-badge">
                <span class="service-auto-renew-badge__text">自</span>
              </div>
            </article>
          </div>

          <div v-else class="service-table-card">
            <t-table row-key="id" :data="list" :columns="columns" :pagination="null" hover>
              <template #service="{ row }">
                <div class="service-table-service">
                  <div class="service-system-icon">
                    <img
                      v-if="shouldShowServiceOsIcon(row)"
                      :src="resolveServiceOsIcon(row)"
                      :alt="String(resolveServiceOsText(row) || resolveServiceName(row))"
                      class="service-system-icon__image"
                      @error="markServiceOsIconFailed(row)"
                    />
                    <span v-else class="service-system-icon__fallback">{{ resolveServiceMark(row) }}</span>
                  </div>
                  <div class="service-table-copy">
                    <div class="service-table-title-row">
                      <t-button theme="primary" variant="text" class="service-name-button" @click="openDetail(row.id)">
                        {{ resolveServiceName(row) }}
                      </t-button>
                      <span v-if="Number(row.auto_renew) === 1" class="service-table-auto-renew">自</span>
                      <span class="service-row-id">ID {{ row.id }}</span>
                    </div>
                    <div class="service-table-meta">
                      <span>{{ row.product?.group_name || row.product?.type_label || '云服务' }}</span>
                      <span class="service-table-meta__dot"></span>
                      <span :class="{ empty: !row.remark }">{{ row.remark || '未设置备注' }}</span>
                      <t-button
                        shape="square"
                        variant="text"
                        size="small"
                        aria-label="编辑备注"
                        @click="openRemark(row)"
                      >
                        <template #icon><edit-icon /></template>
                      </t-button>
                    </div>
                  </div>
                </div>
              </template>
              <template #specs="{ row }">
                <div class="service-table-specs">
                  <span>CPU {{ findListSpecValue(row, ['CPU', '核心']) }}</span>
                  <span>内存 {{ findListSpecValue(row, ['内存', 'RAM']) }}</span>
                  <span>带宽 {{ resolveListBandwidthText(row) }}</span>
                </div>
              </template>
              <template #expires="{ row }">
                <span class="service-expire-line" :class="{ warning: isExpiringSoon(row.expires_at) }">
                  {{ row.expires_at || '长期有效' }}
                </span>
              </template>
              <template #status="{ row }">
                <t-tag :theme="resolveTdesignStatusTheme(row)" variant="light">{{
                  resolveRuntimeStatusLabel(row)
                }}</t-tag>
              </template>
              <template #ip="{ row }">
                <t-button
                  variant="text"
                  size="small"
                  :disabled="!(row.upstream?.dedicated_ip && row.upstream.dedicated_ip !== '--')"
                  @click="copyText(row.upstream?.dedicated_ip || '')"
                >
                  {{ row.upstream?.dedicated_ip || '--' }}
                </t-button>
              </template>
              <template #operation="{ row }">
                <t-space>
                  <t-button size="small" theme="primary" variant="outline" @click="openDetail(row.id)">控制台</t-button>
                  <t-dropdown
                    trigger="click"
                    :options="actionOptions(row)"
                    @click="({ value }: { value: string | number }) => handleServiceAction(String(value), row)"
                  >
                    <t-button size="small" variant="outline">更多</t-button>
                  </t-dropdown>
                </t-space>
              </template>
            </t-table>
          </div>
        </template>
      </data-state>
    </section>

    <div v-if="total > 0" class="service-pagination">
      <t-pagination
        v-model="filters.page"
        v-model:page-size="filters.page_size"
        :page-size-options="[10, 20, 50]"
        :total="total"
        show-total
        @change="loadList"
        @page-size-change="handlePageSizeChange"
      />
    </div>

    <t-dialog v-model:visible="renewVisible" header="服务续费" width="min(34rem, calc(100vw - 2rem))" destroy-on-close>
      <loading-state :loading="renewPreviewLoading" text="数据加载中..." compact>
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

          <t-radio-group v-model="renewForm.billing_cycle" class="renew-cycle-group" @change="handleRenewCycleChange">
            <t-radio-button
              v-for="cycle in renewData.cycles || []"
              :key="cycle.billing_cycle"
              :value="cycle.billing_cycle"
            >
              {{ cycle.billing_cycle_label }} · ¥{{ formatMoney(cycle.amount) }}
            </t-radio-button>
          </t-radio-group>

          <div v-if="renewCouponBinding" class="renew-coupon-row">
            <span>持续优惠</span>
            <span>
              {{ renewCouponBinding.coupon_name || renewCouponBinding.coupon_code || '持续优惠' }}（{{
                renewCouponBinding.discount_label || '优惠价'
              }}）已生效，每次续费均按优惠价计算
            </span>
          </div>
          <div v-if="availableRenewCoupons.length" class="renew-coupon-row">
            <span>续费优惠</span>
            <t-select
              :model-value="renewForm.user_coupon_id || undefined"
              clearable
              placeholder="选择优惠券"
              @change="handleRenewCouponChange"
            >
              <t-option
                v-for="coupon in availableRenewCoupons"
                :key="coupon.id"
                :label="`${coupon.name} · ${coupon.discount_label}`"
                :value="coupon.id"
              />
            </t-select>
          </div>

          <div class="renew-total-line">
            <span>本次应付</span>
            <strong>¥{{ selectedRenewAmount }}</strong>
          </div>
        </template>

        <t-empty v-else description="未获取到可续费周期" />
      </loading-state>

      <template #footer>
        <t-button variant="outline" @click="renewVisible = false">取消</t-button>
        <t-button theme="primary" :loading="renewSubmitting" :disabled="!renewForm.billing_cycle" @click="submitRenew">
          创建续费账单
        </t-button>
      </template>
    </t-dialog>

    <t-dialog v-model:visible="remarkVisible" header="编辑备注" width="min(28rem, calc(100vw - 2rem))" destroy-on-close>
      <t-textarea
        v-model="remarkForm.remark"
        :autosize="{ minRows: 4, maxRows: 6 }"
        :maxlength="120"
        placeholder="填写服务备注，便于快速识别"
      />
      <template #footer>
        <t-button variant="outline" @click="remarkVisible = false">取消</t-button>
        <t-button theme="primary" :loading="remarkSubmitting" @click="submitRemark">保存备注</t-button>
      </template>
    </t-dialog>
  </section>
</template>
<script setup lang="ts">
import DataState from '@shared/user-v3/components/DataState.vue';
import LoadingState from '@shared/user-v3/components/LoadingState.vue';
import { CatalogIcon, DashboardIcon, EditIcon, SearchIcon } from 'tdesign-icons-vue-next';
import type { PrimaryTableCol } from 'tdesign-vue-next';
import { computed } from 'vue';

import {
  findListSpecValue,
  formatMoney,
  isExpiringSoon,
  isProvisioningService,
  resolveListBandwidthText,
  resolveRuntimeStatusLabel,
  resolveServiceMark,
  resolveServiceName,
  resolveServiceOsIcon,
  resolveServiceOsText,
  resolveTdesignStatusTheme,
  useServiceCenter,
} from '@/domains/services/useServiceCenter';

import { useServiceOsIconFallback } from './composables/useServiceOsIconFallback';

const { shouldShowServiceOsIcon, markServiceOsIconFailed } = useServiceOsIconFallback();

const {
  loading,
  list,
  total,
  filters,
  error,
  viewMode,
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
  renewCouponBinding,
  loadList,
  loadData,
  handleSearch,
  handlePageSizeChange,
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
} = useServiceCenter();

// 是否存在生效筛选：空态区分“暂无数据”与“未检索到匹配结果”（DESIGN.md §4.3）
const hasActiveFilters = computed(
  () =>
    filters.keyword.trim() !== '' || filters.catalog_type !== '' || filters.status !== '' || filters.auto_renew !== '',
);

const columns: PrimaryTableCol[] = [
  { colKey: 'service', title: '服务信息', minWidth: '18rem' },
  { colKey: 'specs', title: '配置摘要', minWidth: '14rem' },
  { colKey: 'expires', title: '到期时间', minWidth: '10rem' },
  { colKey: 'status', title: '状态', width: '8rem' },
  { colKey: 'ip', title: '公网 IP', minWidth: '10rem' },
  { colKey: 'operation', title: '操作', width: '13rem', fixed: 'right', align: 'right' },
];

function actionOptions(item: Record<string, any>) {
  const options = [{ content: '立即续费', value: 'renew' }];
  if (item.invoice?.id) {
    options.push({ content: '账单详情', value: 'invoice' });
  }
  return options;
}
</script>
<style src="./index.less" lang="less" scoped></style>
