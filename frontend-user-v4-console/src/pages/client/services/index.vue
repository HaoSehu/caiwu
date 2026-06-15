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
            <SearchIcon />
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

        <div class="service-filter-actions">
          <t-button theme="primary" @click="handleSearch">
            <template #icon><SearchIcon /></template>
            搜索
          </t-button>
          <t-button variant="outline" @click="resetFilters">重置</t-button>
          <t-button variant="outline" @click="setViewMode(viewMode === 'grid' ? 'list' : 'grid')">
            <template #icon>
              <CatalogIcon v-if="viewMode === 'grid'" />
              <DashboardIcon v-else />
            </template>
            {{ viewMode === 'grid' ? '列表' : '卡片' }}
          </t-button>
          <t-button variant="outline" :loading="loading || overviewLoading" @click="refreshAll">刷新</t-button>
        </div>
      </div>
    </t-card>

    <section class="service-list-section">
      <t-loading :loading="loading" text="正在加载服务实例">
        <template v-if="list.length">
          <div v-if="viewMode === 'grid'" class="service-card-grid">
            <article v-for="item in list" :key="item.id" class="service-row-card">
              <div class="service-row-head">
                <div class="service-system-icon" :class="{ 'is-provisioning': isProvisioningService(item) }">
                  <span v-if="isProvisioningService(item)" class="service-system-loader" aria-hidden="true">
                    <span class="service-system-loader__ring"></span>
                    <span class="service-system-loader__core"></span>
                  </span>
                  <img
                    v-else-if="shouldShowServiceOsIcon(item)"
                    :src="resolveServiceOsIcon(item)"
                    :alt="resolveServiceOsText(item) || resolveServiceName(item)"
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
                          <span class="service-remark-text" :class="{ empty: !item.remark }" :title="item.remark || '添加备注'">
                            {{ item.remark || '添加备注' }}
                          </span>
                          <button
                            type="button"
                            class="service-remark-trigger"
                            :aria-label="item.remark ? '编辑备注' : '添加备注'"
                            @click="openRemark(item)"
                          >
                            <EditIcon />
                          </button>
                        </div>
                      </div>

                      <div class="service-row-actions">
                        <button type="button" class="service-action-console" @click="openDetail(item.id)">控制台</button>
                        <t-dropdown
                          trigger="click"
                          :options="actionOptions(item)"
                          @click="({ value }) => handleServiceAction(String(value), item)"
                        >
                          <button type="button" class="service-action-more">更多</button>
                        </t-dropdown>
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
                    <div
                      class="service-status-line"
                      :class="[`is-${item.status_tone || 'info'}`, { 'is-provisioning': isProvisioningService(item) }]"
                    >
                      <i class="service-status-dot"></i>
                      <span class="service-status-text" :class="{ 'is-provisioning': isProvisioningService(item) }">
                        {{ resolveRuntimeStatusLabel(item) }}
                      </span>
                    </div>

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
                        :alt="resolveServiceOsText(row) || resolveServiceName(row)"
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
                      <span class="service-row-id">ID {{ row.id }}</span>
                    </div>
                    <div class="service-table-meta">
                      <span>{{ row.product?.group_name || row.product?.type_label || '云服务' }}</span>
                      <span class="service-table-meta__dot"></span>
                      <span :class="{ empty: !row.remark }">{{ row.remark || '未添加备注' }}</span>
                      <t-button shape="square" variant="text" size="small" @click="openRemark(row)">
                        <template #icon><EditIcon /></template>
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
                <t-tag :theme="resolveTdesignStatusTheme(row)" variant="light">{{ resolveRuntimeStatusLabel(row) }}</t-tag>
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
                    @click="({ value }) => handleServiceAction(String(value), row)"
                  >
                    <t-button size="small" variant="outline">更多</t-button>
                  </t-dropdown>
                </t-space>
              </template>
            </t-table>
          </div>
        </template>

        <t-empty v-else description="当前还没有任何服务实例">
          <t-button theme="primary" @click="router.push('/products')">去选购产品</t-button>
        </t-empty>
      </t-loading>
    </section>

    <div v-if="total > 0" class="service-pagination">
      <t-pagination
        v-model="filters.page"
        v-model:pageSize="filters.page_size"
        :page-size-options="[10, 20, 50]"
        :total="total"
        show-total
        @change="loadList"
        @page-size-change="handlePageSizeChange"
      />
    </div>

    <t-dialog v-model:visible="renewVisible" header="服务续费" width="min(34rem, calc(100vw - 2rem))" destroy-on-close>
      <t-loading :loading="renewPreviewLoading" text="正在加载续费信息">
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

          <t-radio-group
            v-model="renewForm.billing_cycle"
            class="renew-cycle-group"
            @change="handleRenewCycleChange"
          >
            <t-radio-button
              v-for="cycle in renewData.cycles || []"
              :key="cycle.billing_cycle"
              :value="cycle.billing_cycle"
            >
              {{ cycle.billing_cycle_label }} · ￥{{ formatMoney(cycle.amount) }}
            </t-radio-button>
          </t-radio-group>

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
            <strong>￥{{ selectedRenewAmount }}</strong>
          </div>
        </template>

        <t-empty v-else description="未获取到可续费周期" />
      </t-loading>

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
import { ref } from 'vue';
import type { PrimaryTableCol } from 'tdesign-vue-next';
import { CatalogIcon, DashboardIcon, EditIcon, SearchIcon } from 'tdesign-icons-vue-next';

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

const {
  loading,
  overviewLoading,
  list,
  total,
  filters,
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
  loadList,
  refreshAll,
  handleSearch,
  handlePageSizeChange,
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
} = useServiceCenter();

const failedServiceOsIconKeys = ref(new Set<string>());

function resolveServiceIconKey(item: Record<string, any>) {
  return `${item?.id || ''}:${resolveServiceOsText(item)}`;
}

function shouldShowServiceOsIcon(item: Record<string, any>) {
  return Boolean(resolveServiceOsIcon(item)) && !failedServiceOsIconKeys.value.has(resolveServiceIconKey(item));
}

function markServiceOsIconFailed(item: Record<string, any>) {
  failedServiceOsIconKeys.value = new Set([...failedServiceOsIconKeys.value, resolveServiceIconKey(item)]);
}

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
  if (item.invoice?.id || item.order?.invoice_id) {
    options.push({ content: '账单详情', value: 'invoice' });
  }
  return options;
}
</script>

<style scoped lang="less">
.client-services {
  display: flex;
  flex-direction: column;
  gap: var(--td-comp-margin-m);
  padding: var(--td-comp-paddingTB-l) var(--td-comp-paddingLR-l);
}

.service-filter-card {
  background: var(--td-bg-color-container);
  border: thin solid var(--td-border-color);
  border-radius: var(--td-radius-medium);
  box-shadow: var(--td-shadow-1);
}

.service-list-section {
  display: grid;
  gap: var(--td-comp-margin-m);
}

.service-filter-bar {
  display: grid;
  grid-template-columns: minmax(16rem, 1.5fr) minmax(10rem, 0.72fr) minmax(10rem, 0.72fr) auto;
  gap: var(--td-comp-margin-s);
  align-items: center;
}

.service-filter-actions {
  display: flex;
  gap: var(--td-comp-margin-s);
  justify-content: flex-end;
}

.service-card-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(24rem, 1fr));
  justify-content: stretch;
  gap: var(--td-comp-margin-m);
}

.service-row-card {
  width: 100%;
  aspect-ratio: 389 / 187;
  padding: 16px 18px 14px;
  border: 1px solid rgba(225, 231, 241, 0.9);
  border-radius: 16px;
  background:
    radial-gradient(circle at top left, rgba(76, 132, 255, 0.05), transparent 24%),
    linear-gradient(180deg, #ffffff 0%, #fbfcff 100%);
  box-shadow: 0 10px 24px rgba(20, 47, 88, 0.05);
  overflow: hidden;
  transition: border-color 0.2s ease, box-shadow 0.2s ease, transform 0.2s ease;

  &:hover {
    border-color: rgba(76, 132, 255, 0.24);
    box-shadow: 0 16px 32px rgba(20, 47, 88, 0.08);
    transform: translateY(-2px);
  }
}

.service-row-head {
  display: flex;
  align-items: stretch;
  gap: 12px;
  height: 100%;
}

.service-system-icon {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 48px;
  min-width: 48px;
  height: 48px;
  border-radius: 12px;
  overflow: hidden;
}

.service-system-icon__image {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.service-system-icon__fallback {
  color: #3978ff;
  font-size: 18px;
  font-weight: 700;
}

.service-system-loader {
  position: relative;
  width: 28px;
  height: 28px;
}

.service-system-loader__ring,
.service-system-loader__core {
  position: absolute;
  inset: 0;
  border-radius: 50%;
}

.service-system-loader__ring {
  border: 3px solid rgba(235, 19, 92, 0.1);
  border-top-color: #eb135c;
  animation: service-loader-spin 1.2s linear infinite;
}

.service-system-loader__core {
  inset: 7px;
  background: radial-gradient(circle, rgba(235, 19, 92, 0.18), rgba(235, 19, 92, 0.02));
}

.service-row-body {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
}

.service-row-body-top {
  flex: 0 0 auto;
}

.service-row-topline,
.service-title-row,
.service-remark-line,
.service-row-actions,
.service-row-foot,
.service-ip-line,
.service-table-title-row,
.service-table-meta {
  display: flex;
  align-items: center;
}

.service-row-topline {
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
}

.service-row-titleblock {
  min-width: 0;
  flex: 1;
}

.service-title-row {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
  min-width: 0;
}

.service-name-button,
.service-action-console,
.service-action-more,
.service-remark-trigger,
.service-ip-button {
  border: none;
  padding: 0;
  background: transparent;
  cursor: pointer;
  font-family: inherit;
}

.service-name-button {
  display: block;
  max-width: 100%;
  color: #19263d;
  font-size: 14px;
  font-weight: 700;
  line-height: 1.3;
  text-align: left;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.service-row-id {
  display: inline-flex;
  align-items: center;
  min-height: 20px;
  padding: 0 7px;
  border-radius: 999px;
  background: #f4f7fb;
  color: #91a0b6;
  font-size: 11px;
  font-weight: 600;
}

.service-remark-line,
.service-ip-line,
.service-table-meta {
  display: flex;
  align-items: center;
  gap: 6px;
}

.service-remark-line {
  margin-top: 4px;
}

.service-remark-text {
  color: #7d8aa0;
  font-size: 11px;
  line-height: 1.4;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;

  &.empty {
    color: #a1adbe;
  }
}

.service-remark-trigger {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 18px;
  height: 18px;
  border-radius: 8px;
  color: #7c8aa1;
  font-size: 13px;
  transition: background-color 0.2s ease, color 0.2s ease;

  &:hover {
    background: rgba(76, 132, 255, 0.08);
    color: #3978ff;
  }
}

.service-row-actions {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-shrink: 0;
}

.service-action-console {
  color: #256dff;
  font-size: 12px;
  font-weight: 600;
  padding: 0 11px;
  height: 30px;
  border-radius: 10px;
}

.service-action-more {
  border: 1px solid #dbe3f0;
  background: #fff;
  color: #4b5a74;
  font-size: 12px;
  font-weight: 600;
  min-width: 52px;
  height: 30px;
  padding: 0 11px;
  border-radius: 10px;
}

.service-spec-line {
  display: flex;
  flex-wrap: wrap;
  gap: 6px 14px;
  margin-top: 14px;

  span {
    display: inline-block;
    color: #5d6b83;
    font-size: 12px;
    font-weight: 600;
  }
}

.service-expire-line {
  margin-top: 8px;
  color: #6f7d93;
  font-size: 12px;

  &.warning {
    color: #ff8a00;
    font-weight: 600;
  }
}

.service-row-foot {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 14px;
  margin-top: auto;
  padding-top: 10px;
  border-top: 1px solid #edf1f7;
  flex-wrap: wrap;
}

.service-status-line {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  min-height: 24px;
  font-size: 11px;
  font-weight: 700;

  &.is-success {
    color: #22945f;
  }

  &.is-warning {
    color: #ff8a00;
  }

  &.is-danger {
    color: #d71457;
  }

  &.is-info {
    color: #3978ff;
  }

  &.is-provisioning {
    color: #eb135c;
  }
}

.service-status-dot {
  display: inline-block;
  flex-shrink: 0;
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: currentColor;
}

.service-status-text {
  font-size: 11px;
  font-weight: 700;

  &.is-provisioning {
    color: #eb135c;
  }
}

.service-ip-line {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.service-ip-label {
  color: #8e9bb0;
  font-size: 11px;
}

.service-ip-button {
  display: inline-flex;
  align-items: center;
  min-height: 28px;
  padding: 0;
  border: none;
  background: transparent;
  color: #21314c;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;

  &.disabled {
    color: #97a3b6;
    cursor: default;
  }
}

.service-table-card {
  overflow: hidden;
  background: var(--td-bg-color-container);
  border: thin solid var(--td-border-color);
  border-radius: var(--td-radius-medium);
  box-shadow: var(--td-shadow-1);
}

.service-table-service {
  display: flex;
  gap: var(--td-comp-margin-s);
  align-items: flex-start;

  .service-system-icon {
    width: 40px;
    min-width: 40px;
    height: 40px;
    border-radius: 10px;
  }

  .service-system-icon__fallback {
    font-size: 16px;
  }
}

.service-table-copy {
  flex: 1;
  min-width: 0;
}

.service-table-title-row,
.service-table-meta {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
}

.service-table-meta {
  margin-top: 4px;
  gap: 6px;
  color: var(--td-text-color-secondary);
  font-size: 12px;
}

.service-table-meta__dot {
  width: 4px;
  height: 4px;
  background: var(--td-border-color);
  border-radius: 50%;
}

.service-table-specs {
  display: grid;
  gap: 4px;
  color: var(--td-text-color-secondary);
  font-size: 12px;
}

.service-pagination {
  display: flex;
  justify-content: flex-end;
}

.renew-summary-card {
  display: grid;
  gap: var(--td-comp-margin-s);
  padding: var(--td-comp-paddingTB-m) var(--td-comp-paddingLR-m);
  background: var(--td-bg-color-component);
  border: thin solid var(--td-border-color);
  border-radius: var(--td-radius-medium);
}

.renew-summary-row,
.renew-total-line,
.renew-coupon-row {
  display: flex;
  gap: var(--td-comp-margin-s);
  align-items: center;
  justify-content: space-between;

  span {
    color: var(--td-text-color-secondary);
    font: var(--td-font-body-small);
  }

  strong {
    color: var(--td-text-color-primary);
    font: var(--td-font-body-medium);
  }
}

.renew-cycle-group {
  display: flex;
  flex-wrap: wrap;
  gap: var(--td-comp-margin-s);
  margin-top: var(--td-comp-margin-m);
}

.renew-coupon-row,
.renew-total-line {
  margin-top: var(--td-comp-margin-m);
}

@keyframes service-loader-spin {
  to {
    transform: rotate(360deg);
  }
}

@media (max-width: 67.5rem) {
  .service-filter-bar {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .service-filter-actions {
    justify-content: flex-start;
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

@media (max-width: 767px) {
  .service-filter-bar {
    grid-template-columns: 1fr;
  }

  .service-row-card {
    aspect-ratio: auto;
    min-height: 12rem;
    padding: 12px 14px;
  }

  .service-system-icon {
    width: 40px;
    min-width: 40px;
    height: 40px;
    border-radius: 10px;
  }

  .service-row-head {
    gap: 10px;
  }

  .service-row-topline {
    gap: 8px;
  }

  .service-name-button {
    font-size: 13px;
  }

  .service-action-console,
  .service-action-more {
    min-width: 48px;
    height: 28px;
    padding: 0 9px;
    font-size: 11px;
  }

  .service-spec-line {
    gap: 4px 10px;
    margin-top: 12px;

    span {
      font-size: 11px;
    }
  }

  .service-expire-line,
  .service-ip-button {
    font-size: 11px;
  }

  .service-row-foot {
    gap: 10px;
    padding-top: 8px;
  }

  .service-status-line,
  .service-status-text,
  .service-ip-label {
    font-size: 10px;
  }

  .service-filter-actions {
    flex-wrap: wrap;
  }

  .service-pagination {
    justify-content: flex-start;
    overflow-x: auto;
  }

  .service-row-topline,
  .renew-summary-row,
  .renew-total-line,
  .renew-coupon-row {
    align-items: flex-start;
    flex-direction: column;
  }
}
</style>
