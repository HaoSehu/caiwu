<template>
  <section class="client-services">
    <h1 class="sr-only">我的服务</h1>
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
          <!-- O-02：suffixIcon 槽在 clearable 且有内容时会被 TDesign 换成清除图标，
                放大镜随鼠标悬停消失、点下去变成清空。改用 #suffix 槽放可点按钮，位置稳定。 -->
          <template #suffix>
            <search-field-button @search="handleSearch" />
          </template>
        </t-input>

        <t-select v-model="filters.catalog_type" clearable placeholder="服务分类" @change="handleFilterChange">
          <t-option label="全部分类" value="" />
          <t-option
            v-for="item in catalogTypeOptions"
            :key="item.value"
            :label="`${item.label} (${item.count})`"
            :value="item.value"
          />
        </t-select>

        <t-select v-model="filters.status" clearable placeholder="状态分类" @change="handleFilterChange">
          <t-option v-for="item in statusOptions" :key="item.value" :label="item.label" :value="item.value" />
        </t-select>

        <t-select v-model="filters.auto_renew" clearable placeholder="续费方式" @change="handleFilterChange">
          <t-option label="全部" value="" />
          <t-option label="自动续费" value="1" />
          <t-option label="未开启自动续费" value="0" />
        </t-select>

        <div class="service-filter-actions">
          <!-- S-03：手机端隐藏了视图切换（手机端本来就只有卡片视图，
               见 useServiceCenter 的 restoreViewMode），但「重置筛选」的入口也被一起藏掉了，
               用户改完三个下拉后没有一键复位的地方。这里补一个只在窄屏出现的复位按钮。 -->
          <t-button v-if="hasActiveFilters" variant="text" class="service-filter-reset" @click="resetFilters">
            重置筛选
          </t-button>
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
        :description="hasActiveFilters ? '未找到匹配结果' : '当前还没有任何服务实例'"
        @retry="loadData"
      >
        <!-- W-17：无筛选时引导去选购，命中筛选时给「清除筛选条件」——两种空态都要有下一步动作。 -->
        <template v-if="hasActiveFilters" #empty-action>
          <t-button theme="primary" variant="outline" @click="resetFilters">清除筛选条件</t-button>
        </template>
        <template v-else #empty-action>
          <t-button theme="primary" @click="router.push('/client/catalog')">去产品目录选购</t-button>
        </template>
        <template #default>
          <div v-if="viewMode === 'grid'" class="service-card-grid">
            <article v-for="item in list" :key="item.id" class="service-row-card">
              <div class="service-row-actions service-row-actions--corner">
                <button
                  type="button"
                  class="service-action-console"
                  :aria-label="`${vm(item).name} 控制台`"
                  @click="openDetail(item.id)"
                >
                  控制台
                </button>
                <t-dropdown
                  trigger="click"
                  :options="vm(item).actions"
                  @click="({ value }: { value: string | number }) => handleServiceAction(String(value), item)"
                >
                  <button type="button" class="service-action-more" :aria-label="`${vm(item).name} 更多操作`">
                    更多
                  </button>
                </t-dropdown>
              </div>
              <div class="service-row-head">
                <div class="service-system-icon" :class="{ 'is-provisioning': vm(item).provisioning }">
                  <span v-if="vm(item).provisioning" class="service-system-loader" aria-hidden="true">
                    <span class="service-system-loader__ring"></span>
                    <span class="service-system-loader__core"></span>
                  </span>
                  <img
                    v-else-if="vm(item).showOsIcon"
                    :src="vm(item).osIcon"
                    :alt="vm(item).osText || vm(item).name"
                    class="service-system-icon__image"
                    loading="lazy"
                    decoding="async"
                    @error="markServiceOsIconFailed(item)"
                  />
                  <span v-else class="service-system-icon__fallback">{{ vm(item).mark }}</span>
                </div>

                <div class="service-row-body">
                  <div class="service-row-body-top">
                    <div class="service-row-topline">
                      <div class="service-row-titleblock">
                        <div class="service-title-row">
                          <button type="button" class="service-name-button" @click="openDetail(item.id)">
                            {{ vm(item).name }}
                          </button>
                          <span class="service-row-id">ID {{ item.id }}</span>
                        </div>

                        <div class="service-remark-line">
                          <span
                            class="service-remark-text"
                            :class="{ empty: !vm(item).remark }"
                            :title="vm(item).remark || '编辑备注'"
                          >
                            {{ vm(item).remark || '编辑备注' }}
                          </span>
                          <button
                            type="button"
                            class="service-remark-trigger"
                            :aria-label="vm(item).remark ? '编辑备注' : '设置备注'"
                            @click="openRemark(item)"
                          >
                            <edit-icon />
                          </button>
                        </div>
                      </div>
                    </div>

                    <div class="service-spec-line">
                      <span>CPU {{ vm(item).cpu }}</span>
                      <span>内存 {{ vm(item).memory }}</span>
                      <span>带宽 {{ vm(item).bandwidth }}</span>
                    </div>

                    <div class="service-expire-line" :class="{ warning: vm(item).expiring }">
                      到期时间：{{ vm(item).expiresText }}
                    </div>
                  </div>

                  <div class="service-row-foot">
                    <t-tag :theme="vm(item).statusTheme" variant="light" class="service-status-tag">
                      {{ vm(item).statusLabel }}
                    </t-tag>

                    <div class="service-ip-line">
                      <span class="service-ip-label">公网 IP</span>
                      <button
                        type="button"
                        class="service-ip-button"
                        :class="{ disabled: !vm(item).hasIp }"
                        :disabled="!vm(item).hasIp"
                        :title="vm(item).hasIp ? `点击复制 ${vm(item).ipText}` : '暂无公网 IP'"
                        @click="copyText(vm(item).hasIp ? vm(item).ipText : '')"
                      >
                        {{ vm(item).ipText }}
                      </button>
                    </div>
                  </div>
                </div>
              </div>
              <!-- 徽标文案直接放在容器里：__text 这个内层 span 从未有过任何样式，白白多一层盒 -->
              <div v-if="vm(item).autoRenew" class="service-auto-renew-badge">自</div>
            </article>
          </div>

          <div v-else class="service-table-card">
            <t-table row-key="id" :data="list" :columns="columns" :pagination="null" :loading="loading" hover>
              <template #service="{ row }">
                <div class="service-table-service">
                  <div class="service-system-icon">
                    <img
                      v-if="vm(row).showOsIcon"
                      :src="vm(row).osIcon"
                      :alt="vm(row).osText || vm(row).name"
                      class="service-system-icon__image"
                      loading="lazy"
                      decoding="async"
                      @error="markServiceOsIconFailed(row)"
                    />
                    <span v-else class="service-system-icon__fallback">{{ vm(row).mark }}</span>
                  </div>
                  <div class="service-table-copy">
                    <div class="service-table-title-row">
                      <t-button theme="primary" variant="text" class="service-name-button" @click="openDetail(row.id)">
                        {{ vm(row).name }}
                      </t-button>
                      <span v-if="vm(row).autoRenew" class="service-table-auto-renew">自</span>
                      <span class="service-row-id">ID {{ row.id }}</span>
                    </div>
                    <div class="service-table-meta">
                      <span>{{ row.product?.group_name || row.product?.type_label || '云服务' }}</span>
                      <span class="service-table-meta__dot"></span>
                      <span :class="{ empty: !vm(row).remark }">{{ vm(row).remark || '未设置备注' }}</span>
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
                  <span>CPU {{ vm(row).cpu }}</span>
                  <span>内存 {{ vm(row).memory }}</span>
                  <span>带宽 {{ vm(row).bandwidth }}</span>
                </div>
              </template>
              <template #expires="{ row }">
                <span class="service-expire-line" :class="{ warning: vm(row).expiring }">
                  {{ vm(row).expiresText }}
                </span>
              </template>
              <template #status="{ row }">
                <t-tag :theme="vm(row).statusTheme" variant="light">{{ vm(row).statusLabel }}</t-tag>
              </template>
              <template #ip="{ row }">
                <t-button
                  variant="text"
                  size="small"
                  :disabled="!vm(row).hasIp"
                  @click="copyText(vm(row).hasIp ? vm(row).ipText : '')"
                >
                  {{ vm(row).ipText }}
                </t-button>
              </template>
              <template #operation="{ row }">
                <t-space>
                  <t-button size="small" theme="primary" variant="outline" @click="openDetail(row.id)">控制台</t-button>
                  <t-dropdown
                    trigger="click"
                    :options="vm(row).actions"
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

    <t-dialog v-model:visible="renewVisible" header="服务续费" width="min(36rem, calc(100vw - 2rem))" destroy-on-close>
      <loading-state :loading="renewPreviewLoading" text="数据加载中…" compact>
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
          确定
        </t-button>
      </template>
    </t-dialog>

    <t-dialog v-model:visible="remarkVisible" header="编辑备注" width="min(32rem, calc(100vw - 2rem))" destroy-on-close>
      <t-textarea
        v-model="remarkForm.remark"
        :autosize="{ minRows: 4, maxRows: 6 }"
        :maxlength="120"
        placeholder="填写服务备注，便于快速识别"
      />
      <template #footer>
        <t-button variant="outline" @click="remarkVisible = false">取消</t-button>
        <t-button theme="primary" :loading="remarkSubmitting" @click="submitRemark">确定</t-button>
      </template>
    </t-dialog>
  </section>
</template>
<script setup lang="ts">
import DataState from '@caiwu/shared/user-v3/components/DataState.vue';
import LoadingState from '@caiwu/shared/user-v3/components/LoadingState.vue';
import { CatalogIcon, DashboardIcon, EditIcon } from 'tdesign-icons-vue-next';
import type { PrimaryTableCol } from 'tdesign-vue-next';
import { computed } from 'vue';

import SearchFieldButton from '@/components/common/SearchFieldButton.vue';
import {
  DEFAULT_SERVICE_STATUS_SCOPE,
  findListSpecValue,
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
import { formatMoney } from '@/utils/format';

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
  resetFilters,
  handleFilterChange,
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

// 是否存在生效筛选：空态区分“暂无数据”与“未找到匹配结果”（DESIGN.md §4.3）
// W-17：status 的默认值是「默认分类（运行中 / 开通中）」而不是空串，不能一概按非空判定，
// 否则一进页面就被算成“正在筛选”，空态永远显示「未找到匹配结果」。
// quick_filter 也要计入：它由带参跳入写入（如首页「即将到期」入口），本身就是一个筛选条件。
const hasActiveFilters = computed(
  () =>
    filters.keyword.trim() !== '' ||
    filters.catalog_type !== '' ||
    filters.auto_renew !== '' ||
    String(filters.quick_filter || '') !== '' ||
    (filters.status !== '' &&
      filters.status !== null &&
      filters.status !== undefined &&
      // 引用领域层的默认口径，不在这里重复字面量：默认值一旦调整，这里不跟随就会一直误判「在筛选」。
      filters.status !== DEFAULT_SERVICE_STATUS_SCOPE),
);

const columns: PrimaryTableCol[] = [
  { colKey: 'service', title: '服务信息', minWidth: '18rem' },
  { colKey: 'specs', title: '配置摘要', minWidth: '14rem' },
  { colKey: 'expires', title: '到期时间', minWidth: '10rem' },
  { colKey: 'status', title: '状态', width: '8rem' },
  { colKey: 'ip', title: '公网 IP', minWidth: '10rem' },
  { colKey: 'operation', title: '操作', width: '13rem', fixed: 'right', align: 'right' },
];

interface ServiceRowViewModel {
  name: string;
  osText: string;
  osIcon: string;
  showOsIcon: boolean;
  mark: string;
  cpu: string;
  memory: string;
  bandwidth: string;
  expiresText: string;
  expiring: boolean;
  provisioning: boolean;
  statusLabel: string;
  statusTheme: ReturnType<typeof resolveTdesignStatusTheme>;
  ipText: string;
  hasIp: boolean;
  autoRenew: boolean;
  remark: string;
  actions: Array<{ content: string; value: string }>;
}

// 行视图模型：列表重建时才计算一次。
// PR-12：卡片与表格两套模板原先各自在模板内逐行调用 resolveServiceName / resolveServiceOsIcon /
// resolveRuntimeStatusLabel 等纯函数，同一个值在一次渲染里被算两遍以上（列表 20 行就是几十次
// 字符串切分与数组 find）。这里一次算清，模板只做取值——两套模板共用同一份结果，
// 也不会出现「卡片和表格对同一行给出不同文案」的口径漂移。
const rowViewModels = computed(() => {
  const map = new Map<number, ServiceRowViewModel>();
  for (const item of list.value) {
    const actions: ServiceRowViewModel['actions'] = [{ content: '立即续费', value: 'renew' }];
    if (item.invoice?.id) {
      actions.push({ content: '账单详情', value: 'invoice' });
    }
    // 卡片的「自」徽标与表格的「自」标记同源，避免一处判 Number(...) === 1、另一处写字符串比较
    const ipText = String(item.upstream?.dedicated_ip || '').trim();
    map.set(item.id, {
      name: String(resolveServiceName(item)),
      osText: resolveServiceOsText(item),
      osIcon: resolveServiceOsIcon(item),
      // shouldShowServiceOsIcon 依赖图标加载失败集合（shallowRef + triggerRef），
      // 放进 computed 后失败集合变化会正常触发重算，语义与原先在模板里调用一致。
      showOsIcon: shouldShowServiceOsIcon(item),
      mark: resolveServiceMark(item),
      cpu: findListSpecValue(item, ['CPU', '核心']),
      memory: findListSpecValue(item, ['内存', 'RAM']),
      bandwidth: resolveListBandwidthText(item),
      expiresText: item.expires_at || '长期有效',
      expiring: isExpiringSoon(item.expires_at || ''),
      provisioning: isProvisioningService(item),
      statusLabel: resolveRuntimeStatusLabel(item),
      statusTheme: resolveTdesignStatusTheme(item),
      ipText: ipText || '--',
      hasIp: Boolean(ipText) && ipText !== '--',
      autoRenew: Number(item.auto_renew) === 1,
      remark: String(item.remark || ''),
      actions,
    });
  }
  return map;
});

const emptyRowViewModel: ServiceRowViewModel = {
  name: '',
  osText: '',
  osIcon: '',
  showOsIcon: false,
  mark: '',
  cpu: '--',
  memory: '--',
  bandwidth: '--',
  expiresText: '长期有效',
  expiring: false,
  provisioning: false,
  statusLabel: '-',
  statusTheme: 'default',
  ipText: '--',
  hasIp: false,
  autoRenew: false,
  remark: '',
  actions: [],
};

function vm(item: { id: number }): ServiceRowViewModel {
  return rowViewModels.value.get(item.id) ?? emptyRowViewModel;
}
</script>
<style src="./index.less" lang="less" scoped></style>
