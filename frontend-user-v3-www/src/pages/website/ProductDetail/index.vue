<template>
  <div class="pd-page" v-loading="loading">
    <div v-if="product">
      <!-- 顶部工具栏：分类 + 型号横向Tab -->
      <div class="pd-topbar">
        <button
          type="button"
          class="category-btn"
          @click="router.push('/products')"
        >
          <svg viewBox="0 0 16 16" fill="none" width="14" height="14">
            <rect
              x="1"
              y="1"
              width="6"
              height="6"
              rx="1"
              stroke="currentColor"
              stroke-width="1.5"
            />
            <rect
              x="9"
              y="1"
              width="6"
              height="6"
              rx="1"
              stroke="currentColor"
              stroke-width="1.5"
            />
            <rect
              x="1"
              y="9"
              width="6"
              height="6"
              rx="1"
              stroke="currentColor"
              stroke-width="1.5"
            />
            <rect
              x="9"
              y="9"
              width="6"
              height="6"
              rx="1"
              stroke="currentColor"
              stroke-width="1.5"
            />
          </svg>
          <span>分类</span>
        </button>

        <!-- 产品线 Tab（同一产品组下的同级） -->
        <div class="sibling-scroll" v-if="siblings.length > 1">
          <button
            v-for="sib in siblings"
            :key="sib.id"
            type="button"
            class="sib-tab"
            :class="{ active: sib.id === product.id }"
            @click="switchProduct(sib.id)"
          >
            {{ siblingDisplayNames[sib.id] }}
          </button>
        </div>
      </div>

      <!-- 当前机型标签：纯展示文本。此前渲染成带下拉箭头的 button 却无点击行为，属于假控件 -->
      <div v-if="selectedMachineSpec.displayName" class="pd-name-bar">
        <span class="product-name-tag">{{
          selectedMachineSpec.displayName
        }}</span>
      </div>

      <section v-if="showCatalogHero" class="pd-hero">
        <div class="pd-hero-copy">
          <span v-if="detailCatalogEyebrow" class="pd-hero-eyebrow">{{
            detailCatalogEyebrow
          }}</span>
          <h1>{{ detailCatalogTitle }}</h1>
          <p v-if="detailCatalogSlogan">{{ detailCatalogSlogan }}</p>
        </div>
        <div v-if="detailCatalogTags.length" class="pd-hero-tags">
          <span
            v-for="tag in detailCatalogTags"
            :key="tag"
            class="pd-hero-tag"
            >{{ tag }}</span
          >
        </div>
      </section>

      <!-- 主体：左配置 + 右摘要 -->
      <div class="pd-body">
        <div class="pd-config">
          <!-- 区域 -->
          <div class="cfg-group" v-if="regionOptions.length">
            <h2 class="cfg-group-head">区域</h2>
            <div class="cfg-group-body">
              <div class="opt-wrap">
                <button
                  v-for="opt in regionOptions"
                  :key="opt.id"
                  type="button"
                  class="opt-btn"
                  :class="{ active: configForm[regionKey] === opt.id }"
                  @click="configForm[regionKey] = opt.id"
                >
                  {{ opt.label }}
                </button>
              </div>
            </div>
          </div>

          <!-- 操作系统 -->
          <div class="cfg-group" v-if="osConfig">
            <h2 class="cfg-group-head">操作系统</h2>
            <div class="cfg-group-body">
              <div v-if="isMobile" class="pd-mobile-picker-row">
                <div class="pd-mobile-picker-col">
                  <div class="pd-mobile-picker-label">系统</div>
                  <button
                    type="button"
                    class="pd-mobile-picker-trigger"
                    @click="openMobileOsDrawer"
                  >
                    <span>{{ currentOsGroup?.label || "请选择系统" }}</span>
                    <svg
                      viewBox="0 0 12 12"
                      fill="none"
                      width="12"
                      height="12"
                      aria-hidden="true"
                    >
                      <path
                        d="M3 4.5l3 3 3-3"
                        stroke="currentColor"
                        stroke-width="1.5"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                      />
                    </svg>
                  </button>
                </div>
                <div class="pd-mobile-picker-col">
                  <div class="pd-mobile-picker-label">版本</div>
                  <button
                    type="button"
                    class="pd-mobile-picker-trigger"
                    :disabled="!currentOsGroup?.versions?.length"
                    @click="openMobileOsDrawer"
                  >
                    <span>{{ currentOsVerLabel || "请选择版本" }}</span>
                    <svg
                      viewBox="0 0 12 12"
                      fill="none"
                      width="12"
                      height="12"
                      aria-hidden="true"
                    >
                      <path
                        d="M3 4.5l3 3 3-3"
                        stroke="currentColor"
                        stroke-width="1.5"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                      />
                    </svg>
                  </button>
                </div>
              </div>
              <div class="os-row" v-else>
                <div class="os-col">
                  <div class="os-col-label">系统</div>
                  <el-select
                    v-model="configForm.os_group"
                    placeholder="请选择系统"
                    @change="handleOsGroupChange"
                  >
                    <el-option
                      v-for="os in osGroups"
                      :key="os.id"
                      :label="os.label"
                      :value="os.id"
                    />
                  </el-select>
                </div>
                <div class="os-col">
                  <div class="os-col-label">版本</div>
                  <el-select
                    v-model="configForm.os"
                    placeholder="请选择版本"
                    :disabled="!currentOsGroup?.versions?.length"
                  >
                    <el-option
                      v-for="ver in currentOsGroup?.versions"
                      :key="ver.id"
                      :label="ver.label"
                      :value="ver.id"
                    />
                  </el-select>
                </div>
              </div>
            </div>
          </div>

          <!-- 机型配置 -->
          <div class="cfg-group" v-if="specConfigs.length">
            <h2 class="cfg-group-head">机型配置</h2>
            <div class="cfg-group-body">
              <div class="spec-row" v-for="cfg in specConfigs" :key="cfg.key">
                <div class="spec-label">{{ cfg.label }}</div>
                <div class="spec-ctrl">
                  <template v-if="cfg.isNumber">
                    <el-input-number
                      v-model="configForm[cfg.key + '_num']"
                      :min="cfg.min ?? 1"
                      :max="cfg.max ?? undefined"
                      controls-position="right"
                    />
                  </template>
                  <div class="opt-wrap" v-else-if="cfg.options.length > 1">
                    <button
                      v-for="opt in cfg.options"
                      :key="opt.id"
                      type="button"
                      class="opt-btn"
                      :class="{ active: configForm[cfg.key] === opt.id }"
                      @click="configForm[cfg.key] = opt.id"
                    >
                      {{ opt.label }}
                    </button>
                  </div>
                  <span
                    v-else-if="cfg.options.length === 1"
                    class="opt-btn active opt-btn--static"
                    >{{ cfg.options[0].label }}</span
                  >
                </div>
              </div>
            </div>
          </div>

          <!-- 网络配置 -->
          <div class="cfg-group" v-if="netConfigs.length">
            <h2 class="cfg-group-head">网络配置</h2>
            <div class="cfg-group-body">
              <div class="spec-row" v-for="cfg in netConfigs" :key="cfg.key">
                <div class="spec-label">{{ cfg.label }}</div>
                <div class="spec-ctrl">
                  <template v-if="cfg.isNumber">
                    <el-input-number
                      v-model="configForm[cfg.key + '_num']"
                      :min="cfg.min ?? 1"
                      :max="cfg.max ?? undefined"
                      controls-position="right"
                    />
                  </template>
                  <div class="opt-wrap" v-else-if="cfg.options.length > 1">
                    <button
                      v-for="opt in cfg.options"
                      :key="opt.id"
                      type="button"
                      class="opt-btn"
                      :class="{ active: configForm[cfg.key] === opt.id }"
                      @click="configForm[cfg.key] = opt.id"
                    >
                      {{ opt.label }}
                    </button>
                  </div>
                  <span
                    v-else-if="cfg.options.length === 1"
                    class="opt-btn active opt-btn--static"
                    >{{ cfg.options[0].label }}</span
                  >
                </div>
              </div>
            </div>
          </div>

          <!-- 基础设置 -->
          <div class="cfg-group">
            <h2 class="cfg-group-head">基础设置</h2>
            <div class="cfg-group-body">
              <!-- 计费周期 -->
              <div class="spec-row">
                <div class="spec-label">计费周期</div>
                <div class="spec-ctrl">
                  <div class="cycle-wrap">
                    <button
                      v-for="item in pricingEntries"
                      :key="item.cycle"
                      type="button"
                      class="cycle-btn"
                      :class="{ active: selectedCycle === item.cycle }"
                      @click="selectedCycle = item.cycle"
                    >
                      <span class="cycle-name">{{ item.label }}</span>
                      <span class="cycle-price">¥{{ item.amount }}</span>
                    </button>
                  </div>
                </div>
              </div>
              <!-- 单笔订单仅支持创建一台服务实例，避免多台计价但仅开通一台。 -->
              <div class="spec-row spec-row--last">
                <div class="spec-label">购买数量</div>
                <div class="spec-ctrl">
                  <span>1 台（多台请分次下单）</span>
                </div>
              </div>
            </div>
          </div>
        </div>
        <!-- /pd-config -->

        <!-- 右侧：配置费用摘要 -->
        <aside class="pd-aside">
          <div class="aside-card">
            <div class="cost-header">
              <span class="cost-title">配置费用</span>
              <span class="stock-badge" :class="stockClass">{{
                stockLabel
              }}</span>
            </div>

            <div :class="['stock-info', `stock-info--${stockClass}`]">
              <div
                v-if="resolvedStock !== null && resolvedStock !== -1"
                class="stock-main"
              >
                当前库存&nbsp;&nbsp;
                <strong>剩余 {{ resolvedStock }} 台</strong>
              </div>
              <div v-else-if="productStockLoading" class="stock-main">
                当前库存&nbsp;&nbsp;<strong>同步中...</strong>
              </div>
              <div v-else-if="productStockError" class="stock-main">
                当前库存&nbsp;&nbsp;<strong>同步失败</strong>
              </div>
              <div v-else class="stock-main">
                当前库存&nbsp;&nbsp;<strong>可直接购买</strong>
              </div>
              <div class="stock-hint">{{ stockHint }}</div>
            </div>

            <el-alert
              v-if="purchaseRequirementList.length"
              type="warning"
              :closable="false"
              show-icon
              class="purchase-guard-alert"
            >
              <template #title
                >购买要求：{{ purchaseRequirementSummary }}</template
              >
            </el-alert>

            <div class="aside-divider"></div>

            <section class="aside-section">
              <div class="aside-section-title">配置摘要</div>
              <div
                class="cost-detail"
                :class="{ 'cost-detail--loading': quoteLoading }"
              >
                <div class="cost-item">
                  <span>产品</span>
                  <span>{{ selectedMachineSpec.displayName }}</span>
                </div>
                <div
                  class="cost-item"
                  v-for="item in summaryItems"
                  :key="item.key"
                >
                  <span>{{ item.label }}</span>
                  <span>{{ item.value }}</span>
                </div>
              </div>
            </section>

            <div class="aside-divider"></div>

            <section class="aside-section">
              <div class="aside-section-title">费用明细</div>
              <div
                class="cost-breakdown"
                :class="{ 'cost-breakdown--loading': quoteLoading }"
              >
                <div class="cost-item">
                  <span>基础价格</span>
                  <span>¥{{ baseAmount }}</span>
                </div>
                <div class="cost-item" v-if="toMoneyNumber(setupFee) > 0">
                  <span>开通费</span>
                  <span>¥{{ setupFee }}</span>
                </div>
                <div
                  class="cost-item cost-item--extra"
                  v-for="item in quoteItems"
                  :key="item.field"
                >
                  <span>+ {{ item.label }}</span>
                  <span>¥{{ item.amount }}</span>
                </div>
                <div
                  class="cost-item cost-item--discount"
                  v-if="memberDiscountAmount > 0"
                >
                  <span
                    >会员折扣{{
                      memberDiscount.level_name
                        ? `（${memberDiscount.level_name}）`
                        : ""
                    }}</span
                  >
                  <span>-¥{{ memberDiscount.amount }}</span>
                </div>
                <div class="cost-item cost-item--discount" v-if="appliedCoupon">
                  <span
                    >优惠券 {{ appliedCoupon.code || appliedCoupon.name }}</span
                  >
                  <span>-¥{{ appliedCoupon.discount_amount }}</span>
                </div>
              </div>
            </section>

            <div class="coupon-panel">
              <div class="coupon-panel-head">
                <span class="coupon-panel-title">优惠券</span>
                <button
                  v-if="appliedCoupon"
                  type="button"
                  class="coupon-clear-btn"
                  @click="confirmClearCoupon"
                >
                  移除
                </button>
              </div>
              <div class="coupon-panel-form">
                <button
                  v-if="isMobile"
                  type="button"
                  class="pd-mobile-trigger"
                  @click="openMobileCouponDrawer"
                >
                  <span>{{ selectedCouponLabel || "请选择优惠券" }}</span>
                  <svg
                    viewBox="0 0 12 12"
                    fill="none"
                    width="12"
                    height="12"
                    aria-hidden="true"
                  >
                    <path
                      d="M3 4.5l3 3 3-3"
                      stroke="currentColor"
                      stroke-width="1.5"
                      stroke-linecap="round"
                      stroke-linejoin="round"
                    />
                  </svg>
                </button>
                <el-select
                  v-else
                  :model-value="selectedCouponId || undefined"
                  clearable
                  placeholder="请选择优惠券"
                  @change="handleCouponChange"
                >
                  <el-option
                    v-for="item in availableCoupons"
                    :key="item.id"
                    :label="`${item.name} · ${item.discount_label}`"
                    :value="item.id"
                  />
                </el-select>
              </div>
              <div v-if="appliedCoupon" class="coupon-panel-tip">
                {{ appliedCoupon.name }}，{{
                  appliedCoupon.discount_label
                }}，本次已减免 ¥{{ appliedCoupon.discount_amount }}
              </div>
              <div
                v-else-if="!availableCoupons.length"
                class="coupon-panel-tip coupon-panel-tip--muted"
              >
                {{
                  product
                    ? "当前暂无可用优惠券，登录后如有优惠券会自动展示在这里。"
                    : "请选择商品后查看可用优惠券。"
                }}
              </div>
            </div>

            <div class="aside-divider"></div>

            <div
              class="cost-total"
              :class="{ 'cost-total--loading': quoteLoading }"
            >
              <span class="cost-total-label">合计费用</span>
              <div class="cost-price-wrap">
                <template v-if="quoteError && !quoteLoading">
                  <span class="cost-quote-error">价格计算失败</span>
                  <button
                    type="button"
                    class="cost-quote-retry"
                    @click="retryQuote"
                  >
                    重试
                  </button>
                </template>
                <template v-else>
                  <span class="cost-currency">¥</span>
                  <span
                    v-if="quoteLoading"
                    class="cost-amount cost-amount--loading"
                    >计算中</span
                  >
                  <span v-else class="cost-amount">{{ totalPrice }}</span>
                  <span class="cost-cycle"
                    >/{{ selectedCycleLabel || "月付" }}</span
                  >
                </template>
              </div>
            </div>

            <button
              class="buy-btn"
              :disabled="!canSubmit || submitting || quoteLoading"
              :class="{ loading: submitting, 'is-sold-out': soldOut }"
              @click="handleSubmit"
            >
              <span>{{ submitButtonText }}</span>
            </button>
          </div>
        </aside>
      </div>
      <!-- /pd-body -->

      <div class="allocation-footer">
        <div class="allocation-footer-inner">
          <div class="allocation-footer-main">
            <div class="allocation-footer-label">合计费用</div>
            <div class="allocation-footer-price">
              <template v-if="quoteError && !quoteLoading">
                <span class="allocation-footer-quote-error">价格计算失败</span>
                <button
                  type="button"
                  class="allocation-footer-quote-retry"
                  @click="retryQuote"
                >
                  重试
                </button>
              </template>
              <template v-else>
                <span class="allocation-footer-symbol">¥</span>
                <span class="allocation-footer-num" v-if="quoteLoading">…</span>
                <span class="allocation-footer-num" v-else>{{
                  totalPrice
                }}</span>
                <span class="allocation-footer-cycle"
                  >/{{ selectedCycleLabel || "月付" }}</span
                >
              </template>
            </div>
            <div class="allocation-footer-meta">
              <span v-if="selectedCycleLabel">{{ selectedCycleLabel }}</span>
              <span v-if="regionOptions.length && configForm[regionKey]">
                {{
                  regionOptions.find((o) => o.id === configForm[regionKey])
                    ?.label || configForm[regionKey]
                }}
              </span>
              <span v-if="currentOsVerLabel">{{ currentOsVerLabel }}</span>
            </div>
          </div>
          <button
            class="allocation-footer-action"
            :disabled="!canSubmit || submitting || quoteLoading"
            :class="{ loading: submitting, 'is-sold-out': soldOut }"
            @click="handleSubmit"
          >
            <span>{{ submitButtonText }}</span>
          </button>
        </div>
      </div>
    </div>

    <!-- role=status：加载失败与「商品不存在」必须被读屏播报，否则只剩视觉反差 -->
    <div class="pd-empty" v-else-if="!loading" role="status" aria-live="polite">
      <el-empty
        :description="
          loadFailed ? '商品加载失败，请检查网络连接' : '商品不存在或已下架'
        "
      >
        <div class="pd-empty__actions">
          <el-button v-if="loadFailed" type="primary" @click="loadProduct"
            >重新加载</el-button
          >
          <el-button @click="router.push('/products')">返回产品页</el-button>
        </div>
      </el-empty>
    </div>

    <!-- 移动专属抽屉用 isMobile 门控：异步组件只切 chunk 不阻止加载，
         桌面端只要渲染到这个 vnode 就会去拉 MobileOsPicker / MobileSheet。 -->
    <MobileOsPicker
      v-if="isMobile && osConfig"
      :visible="mobileOsDrawer"
      :os-groups="osGroups"
      :active-os-group-id="configForm.os_group || ''"
      :active-os-version-id="configForm.os || ''"
      @close="mobileOsDrawer = false"
      @confirm="confirmMobileOsSelection"
    />

    <MobileSheet
      v-if="isMobile"
      v-model="mobileCouponDrawer"
      size="42%"
      title="选择优惠券"
      confirm-text="关闭"
      @confirm="mobileCouponDrawer = false"
    >
      <div class="pd-coupon-sheet">
        <button
          type="button"
          class="pd-coupon-option pd-coupon-option--none"
          :class="{ 'is-active': !selectedCouponId }"
          @click="selectMobileCoupon(0)"
        >
          <span>不使用优惠券</span>
        </button>
        <button
          v-for="item in availableCoupons"
          :key="item.id"
          type="button"
          class="pd-coupon-option"
          :class="{ 'is-active': selectedCouponId === item.id }"
          @click="selectMobileCoupon(item.id)"
        >
          <span class="pd-coupon-name"
            >{{ item.name }} · {{ item.discount_label }}</span
          >
          <span v-if="selectedCouponId === item.id" class="pd-coupon-check"
            >✓</span
          >
        </button>
      </div>
    </MobileSheet>
  </div>
</template>

<script setup>
import {
  computed,
  defineAsyncComponent,
  onBeforeUnmount,
  onMounted,
  ref,
  watch,
} from "vue";
import { useRoute, useRouter } from "vue-router";
import siteApi from "@/api/site";
import {
  applyDetailPageMeta,
  buildDetailCanonical,
  resolvePublicSiteUrl,
} from "@/composables/usePageMeta";
import { useAppStore } from "@/app/stores/app";
import {
  buildWebsiteProductPath,
  resolveWebsiteProductRoutePayloadByDetail,
} from "@/utils/productRoute";
import {
  resolveProductDisplayName,
  toMoneyNumber,
} from "@/utils/websiteProductConfig";
import {
  isCpuConfigKey,
  isMemoryConfigKey,
  resolveMachineSpecPresentation,
} from "@/domains/products/machineSpecResolver";
import { useWebsiteProductConfigurator } from "@/domains/products/useWebsiteProductConfigurator";
import { useWebsiteProductCheckout } from "@/domains/products/useWebsiteProductCheckout";
import { useMobileBreakpoint } from "@/composables/useMobileBreakpoint";
// 这两个组件只在移动端弹层里出现，桌面端永不渲染；
// 异步组件让它们不再随本页 chunk 静态下发。
const MobileOsPicker = defineAsyncComponent(
  () => import("@/components/MobileOsPicker.vue"),
);
const MobileSheet = defineAsyncComponent(
  () => import("@/components/MobileSheet.vue"),
);

const route = useRoute();
const router = useRouter();
const appStore = useAppStore();

// 详情页 meta 与 canonical 的公网站点前缀
const publicSiteUrl = resolvePublicSiteUrl();

// 移动布局断点与商品列表、站点头部共用 useMobileBreakpoint 的同一份 matchMedia：
// 声明时即读取初始值，避免移动端首帧先渲染桌面分支（el-select 等）再切移动
// picker 的一帧闪变；matchMedia change 监听也由该 composable 在挂载/卸载时成对
// 注册与移除，本页不再各自维护 mobileMediaQuery / mobileMediaListener。
const { isMobile, updateMobileState } = useMobileBreakpoint();
const mobileOsDrawer = ref(false);
const mobileCouponDrawer = ref(false);

function openMobileOsDrawer() {
  if (!osGroups.value.length) return;
  mobileOsDrawer.value = true;
}

function confirmMobileOsSelection(groupId, versionId) {
  mobileOsDrawer.value = false;
  const group = osGroups.value.find((g) => g.id === groupId);
  if (!group) {
    return;
  }
  selectOsGroup(group);
  if (versionId) {
    configForm.os = versionId;
  }
}

const selectedCouponLabel = computed(() => {
  if (!appliedCoupon.value) return "";
  return `${appliedCoupon.value.code || appliedCoupon.value.name} · ${
    appliedCoupon.value.discount_label
  }`;
});

function openMobileCouponDrawer() {
  mobileCouponDrawer.value = true;
}

async function selectMobileCoupon(id) {
  if (!id) {
    // 当前本就没选券：移除请求无事可做，confirmClearCoupon() 会直接返回 false，
    // 若照搬「取消就不关抽屉」的逻辑，用户点这一行将毫无反应。
    if (!selectedCouponId.value) {
      mobileCouponDrawer.value = false;
      return;
    }
    // 已选券时选「不使用优惠券」等价于移除，需要二次确认；取消时抽屉保持打开
    if (!(await confirmClearCoupon())) {
      return;
    }
    mobileCouponDrawer.value = false;
    return;
  }

  handleCouponChange(id);
  mobileCouponDrawer.value = false;
}

const loading = ref(false);
const product = ref(null);
const siblings = ref([]);

const configurator = useWebsiteProductConfigurator(
  computed(() => product.value),
);
const configForm = configurator.configForm;
// 报价/库存/下单逻辑复用目录页同一套 composable，避免资金链路双实现
const {
  submitting,
  selectedCycle,
  quoteLoading,
  quoteError,
  retryQuote,
  productStockLoading,
  productStockError,
  baseAmount,
  setupFee,
  quoteItems,
  appliedCoupon,
  memberDiscount,
  memberDiscountAmount,
  availableCoupons,
  totalPrice,
  selectedCycleLabel,
  resolvedStock,
  stockClass,
  stockLabel,
  stockHint,
  selectedCouponId,
  canSubmit,
  purchaseRequirementList,
  purchaseRequirementSummary,
  handleCouponChange,
  confirmClearCoupon,
  handleSubmit,
  applyLoadedProduct,
} = useWebsiteProductCheckout({
  productDetail: product,
  configForm: configurator.configForm,
  pricingEntries: configurator.pricingEntries,
  buildConfigPayload: configurator.buildConfigPayload,
  initProductDefaults: configurator.initProductDefaults,
  resetConfigForm: configurator.resetConfigForm,
});

// 网络关键词（带宽/IP/流量/防护等）
const NET_KEYWORDS = [
  "bandwidth",
  "bw",
  "ip",
  "traffic",
  "ddos",
  "port",
  "speed",
  "mbps",
  "gbps",
  "带宽",
  "流量",
];

function isNetConfig(key, label) {
  const k = key.toLowerCase();
  const l = String(label || "").toLowerCase();
  return NET_KEYWORDS.some((kw) => k.includes(kw) || l.includes(kw));
}
const regionKey = computed(() => configurator.regionFieldKey.value || "region");
const regionOptions = computed(() => configurator.regionOptions.value || []);
const osConfig = computed(() => configurator.osConfig.value || null);
const osGroups = computed(() => configurator.osGroups.value || []);
const currentOsGroup = computed(
  () => configurator.currentOsGroup.value || null,
);
const currentOsVerLabel = computed(
  () => configurator.currentOsVersionLabel.value || "",
);
const selectedMachineSpec = computed(() =>
  resolveMachineSpecPresentation(product.value, {
    cpu: configForm.cpu,
    memory: configForm.memory,
  }),
);

// 同级 Tab 的规格展示名缓存：仅 siblings 变化时重算，避免每次渲染对每个同级重复正则解析
const siblingDisplayNames = computed(() => {
  const map = new Map();
  (siblings.value || []).forEach((sib) => {
    const id = Number(sib?.id || 0);
    if (id > 0) {
      map.set(id, resolveMachineSpecPresentation(sib).displayName);
    }
  });
  return map;
});

function selectOsGroup(os) {
  configForm.os_group = os.id;
  if (os.versions?.length) configForm.os = os.versions[0].id;
  // configForm 变化会经 checkout 的 quoteTrigger 自动重新报价
}

function handleOsGroupChange(value) {
  const nextGroup = osGroups.value.find((item) => item.id === value);
  if (!nextGroup) {
    configForm.os = "";
    return;
  }

  selectOsGroup(nextGroup);
}

// 机型配置（排除 区域/OS/网络）
const specConfigs = computed(() =>
  configurator.machineConfigs.value.filter(
    (cfg) => !isNetConfig(cfg.key, cfg.label),
  ),
);

// 网络配置
const netConfigs = computed(() =>
  [
    ...configurator.networkConfigs.value,
    ...configurator.otherConfigs.value,
  ].filter((cfg) => isNetConfig(cfg.key, cfg.label)),
);

function isSpecSummaryConfig(cfg) {
  return (
    isCpuConfigKey(cfg.key, cfg.label) || isMemoryConfigKey(cfg.key, cfg.label)
  );
}

function resolveSummaryConfigValue(cfg) {
  if (cfg.isNumber) {
    const value = configForm[cfg.key + "_num"];
    return value !== undefined && value !== null && value !== ""
      ? String(value)
      : "";
  }

  const value = configForm[cfg.key];
  if (value === undefined || value === null || value === "") {
    return "";
  }

  return cfg.options.find((item) => item.id === value)?.label || String(value);
}

// 价格
const pricingEntries = computed(() => configurator.pricingEntries.value || []);
const detailGroup = computed(() => product.value?.group || {});
const detailCatalogTitle = computed(
  () =>
    selectedMachineSpec.value.displayName ||
    resolveProductDisplayName(product.value),
);
const detailCatalogSlogan = computed(() =>
  String(
    detailGroup.value.slogan || detailGroup.value.parent_slogan || "",
  ).trim(),
);
const detailCatalogEyebrow = computed(() => {
  const parentTitle = String(detailGroup.value.parent_name || "").trim();
  if (parentTitle && parentTitle !== detailCatalogTitle.value) {
    return parentTitle;
  }

  return String(product.value?.type_label || "").trim();
});
const detailCatalogTags = computed(() => {
  const tags = [];
  const fullName = String(detailGroup.value.full_name || "").trim();
  const typeLabel = String(product.value?.type_label || "").trim();

  if (fullName) {
    tags.push(fullName);
  }

  if (typeLabel && !tags.includes(typeLabel)) {
    tags.push(typeLabel);
  }

  return tags;
});
const showCatalogHero = computed(() =>
  Boolean(
    detailCatalogTitle.value ||
    detailCatalogSlogan.value ||
    detailCatalogEyebrow.value,
  ),
);

const summaryItems = computed(() => {
  const items = [];

  if (regionOptions.value.length && configForm[regionKey.value]) {
    items.push({
      key: "region",
      label: "区域",
      value:
        regionOptions.value.find(
          (item) => item.id === configForm[regionKey.value],
        )?.label || configForm[regionKey.value],
    });
  }

  if (currentOsVerLabel.value) {
    items.push({
      key: "os",
      label: "系统",
      value: currentOsVerLabel.value,
    });
  }

  if (selectedCycleLabel.value) {
    items.push({
      key: "billing_cycle",
      label: "周期",
      value: selectedCycleLabel.value,
    });
  }

  specConfigs.value.forEach((cfg) => {
    if (isSpecSummaryConfig(cfg)) {
      return;
    }

    const value = resolveSummaryConfigValue(cfg);
    if (!value) {
      return;
    }

    items.push({
      key: `spec-${cfg.key}`,
      label: cfg.label,
      value,
    });
  });

  netConfigs.value.forEach((cfg) => {
    const value = resolveSummaryConfigValue(cfg);
    if (!value) {
      return;
    }

    items.push({
      key: `net-${cfg.key}`,
      label: cfg.label,
      value,
    });
  });

  return items;
});

let productLoadToken = 0;

const soldOut = computed(
  () =>
    resolvedStock.value !== null &&
    !productStockLoading.value &&
    !productStockError.value &&
    resolvedStock.value === 0,
);
const submitButtonText = computed(() => {
  if (submitting.value) return "提交中...";
  if (soldOut.value) return "已售罄";
  return "立即购买";
});

function switchProduct(id) {
  const routePayload = resolveWebsiteProductRoutePayloadByDetail(product.value);
  // 用 push 保留浏览历史，否则同级切换后按浏览器后退会直接跳回上上级页面
  router.push(
    buildWebsiteProductPath({
      ...routePayload,
      productId: Number(id || 0),
    }),
  );
}

const loadFailed = ref(false);

async function loadProduct() {
  const pid = Number(route.params.id || 0);
  if (!pid) {
    router.push("/products");
    return;
  }
  const token = ++productLoadToken;
  loading.value = true;
  loadFailed.value = false;
  try {
    const res = await siteApi.product(pid);
    if (token !== productLoadToken) return;
    product.value = res.data.product || null;
    siblings.value = res.data.product?.siblings || [];
    // 数据就绪后先覆盖静态 meta：否则商品详情会沿用列表页标题且缺少 description/canonical。
    // 放在 applyLoadedProduct 之前，确保配置器初始化异常也不影响 SEO 元信息。
    applyDetailPageMeta({
      title: res.data.product?.name,
      description:
        res.data.product?.description || res.data.product?.summary || "",
      canonical: buildDetailCanonical(publicSiteUrl, `/products/${pid}`),
      siteName: appStore.siteName || "",
      listName: "产品与服务",
      listUrl: `${publicSiteUrl}/products`,
      structuredType: "Product",
    });
    // 注入已加载商品：由 checkout 统一完成默认配置初始化、报价与库存同步
    applyLoadedProduct(res.data.product);
  } catch {
    if (token !== productLoadToken) return;
    product.value = null;
    loadFailed.value = true;
  } finally {
    if (token === productLoadToken) {
      loading.value = false;
    }
  }
}

watch(
  () => route.params.id,
  (v) => {
    if (v) loadProduct();
  },
);
// 抽屉外层带 v-if="isMobile"，切到桌面即整块卸载；不复位 ref 的话，切回移动端时
// 组件重新挂载会凭残留的 true 立刻弹出上次打开的抽屉。
watch(isMobile, (mobile) => {
  if (!mobile) {
    mobileOsDrawer.value = false;
    mobileCouponDrawer.value = false;
  }
});
watch(
  () => route.query.user_coupon_id,
  (value) => {
    const nextCouponId = Number(value || 0);

    if (nextCouponId === selectedCouponId.value) {
      return;
    }

    handleCouponChange(nextCouponId);
  },
  { immediate: true },
);
onMounted(() => {
  // 挂载后再同步一次：脚本执行到挂载之间视口可能已经变化；
  // matchMedia change 监听由 useMobileBreakpoint 内部注册，这里不再重复注册
  updateMobileState();
  loadProduct();
});

onBeforeUnmount(() => {
  // 使仍在途的 loadProduct 响应失效；报价/库存在途请求由 checkout 的 onBeforeUnmount 中止
  // 断点监听由 useMobileBreakpoint 的 onBeforeUnmount 移除
  productLoadToken += 1;
});
</script>

<style scoped lang="scss">
/* 本页色值统一收编到全局 SCSS token（variables.scss），不再维护独立色板：
   主色   $color-primary / hover $color-primary-hover
   激活bg $color-primary-soft
   页面bg $bg-color
   卡片bg $bg-color-card
   边框   $border-color / 强边框 $border-color-strong
   文字   $text-color-primary / secondary / placeholder
*/

.pd-page {
  /* 100vh 减去站头与页脚可见基线；原写死 160px 与 $navbar-height 无关联，
     改由令牌推导，站头改高时不必再回来找魔法数。 */
  min-height: calc(100vh - ($navbar-height + 96px));
  background: $bg-color;
  padding-bottom: 40px;

  @media (max-width: $breakpoint-nav) {
    padding-bottom: 108px;
  }
}

/* ===== 顶部工具栏 ===== */
.pd-topbar {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 16px 0;
  overflow: hidden;
}

.category-btn {
  flex-shrink: 0;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  height: 34px;
  padding: 0 12px;
  border: 1px solid $border-color-strong;
  border-radius: 4px;
  background: $bg-color-card;
  color: $text-color-secondary;
  font-size: 13px;
  cursor: pointer;
  white-space: nowrap;
  transition:
    border-color 0.15s,
    color 0.15s;

  &:hover {
    border-color: $color-primary;
    color: $color-primary;
  }

  svg {
    color: $text-color-placeholder;
  }
}

.sibling-scroll {
  display: flex;
  gap: 8px;
  overflow-x: auto;
  scrollbar-width: none;
  &::-webkit-scrollbar {
    display: none;
  }
}

.sib-tab {
  flex-shrink: 0;
  height: 34px;
  padding: 0 14px;
  border: 1px solid $border-color-strong;
  border-radius: 4px;
  background: $bg-color-card;
  color: $text-color-secondary;
  font-size: 13px;
  cursor: pointer;
  white-space: nowrap;
  transition:
    border-color $motion-fast ease,
    background-color $motion-fast ease,
    color $motion-fast ease;

  &:hover {
    border-color: $color-primary;
    color: $color-primary;
  }
  &.active {
    border-color: $color-primary;
    background: $color-primary;
    color: $text-color-inverse;
    font-weight: 600;
  }
}

/* ===== 产品名称栏 ===== */
.pd-name-bar {
  padding: 10px 16px 0;
}

.pd-hero {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 20px;
  margin: 12px 16px 0;
  padding: 18px 20px;
  border: 1px solid $border-color;
  border-radius: 4px;
  background: linear-gradient(135deg, $bg-color-card 0%, $bg-color-hover 100%);
}

.pd-hero-copy {
  min-width: 0;
}

.pd-hero-eyebrow {
  display: inline-flex;
  align-items: center;
  min-height: 24px;
  padding: 0 10px;
  border-radius: 999px;
  background: $color-primary-soft;
  color: $color-primary;
  font-size: 12px;
  font-weight: 600;
}

.pd-hero-copy h1 {
  margin: 10px 0 0;
  color: $text-color-primary;
  font-size: 26px;
  line-height: 1.2;
}

.pd-hero-copy p {
  margin: 8px 0 0;
  color: $text-color-secondary;
  font-size: 14px;
  line-height: 1.7;
}

.pd-hero-tags {
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: 8px;
}

.pd-hero-tag {
  display: inline-flex;
  align-items: center;
  min-height: 32px;
  padding: 0 12px;
  border: 1px solid $border-color-strong;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.9);
  color: $text-color-secondary;
  font-size: 12px;
  white-space: nowrap;
}

/* 当前机型标签：纯文本展示，不带 pointer 与箭头，避免误导为可展开控件 */
.product-name-tag {
  display: inline-flex;
  align-items: center;
  max-width: 100%;
  min-height: 24px;
  padding: 0 10px;
  border-radius: 999px;
  background: $color-primary-soft;
  color: $color-primary;
  font-size: 12px;
  font-weight: 600;

  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

/* ===== 主体 ===== */
.pd-body {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 268px;
  gap: 12px;
  padding: 12px 16px 0;
  align-items: start;

  @media (max-width: $breakpoint-nav) {
    grid-template-columns: 1fr;
  }
}

@media (max-width: $breakpoint-nav) {
  .pd-hero {
    flex-direction: column;
    align-items: flex-start;
    margin-top: 10px;
    padding: 16px;
  }

  .pd-hero-copy h1 {
    font-size: 22px;
  }

  .pd-hero-tags {
    justify-content: flex-start;
  }
}

/* ===== 配置分组卡片 ===== */
.cfg-group {
  background: $bg-color-card;
  border-radius: 4px;
  margin-bottom: 10px;
  border: 1px solid $border-color;
}

.cfg-group-head {
  padding: 12px 16px 10px;
  font-size: 14px;
  font-weight: 600;
  color: $text-color-primary;
  border-bottom: 1px solid $divider-color;
}

.cfg-group-body {
  padding: 12px 16px;
}

/* ===== 选项按钮 ===== */
.opt-wrap {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.opt-btn {
  display: inline-flex;
  align-items: center;
  height: 34px;
  padding: 0 14px;
  border: 1px solid $border-color-strong;
  border-radius: 4px;
  background: $bg-color-soft;
  color: $text-color-primary;
  font-size: 13px;
  cursor: pointer;
  white-space: nowrap;
  transition:
    border-color $motion-fast ease,
    background-color $motion-fast ease,
    color $motion-fast ease;

  &:hover {
    border-color: $color-primary;
    color: $color-primary;
    background: $bg-color-card;
  }
  &.active {
    border-color: $color-primary;
    background: $color-primary-soft;
    color: $color-primary;
    font-weight: 600;
  }

}

// 只有一个可选值时渲染为纯展示标签（span），不带手型光标与 hover 反馈。
// 该 span 恒带 .active，.opt-btn.active 已给出最终配色，因此这里不能也不需要
// 再写 :hover —— 上一版保留了整组 hover 声明，与注释矛盾且永远不可见。
.opt-btn--static {
  // 保留文本可选中，只去掉手型光标；悬停配色由恒生效的 .active 提供，
  // 不再重复写 :hover（写了也看不出差别，且与「无 hover 反馈」的意图矛盾）
  cursor: default;
}

/* ===== 规格行 ===== */
.spec-row {
  display: flex;
  align-items: flex-start;
  gap: 16px;
  padding: 10px 0;
  border-bottom: 1px solid $divider-color;

  &:first-child {
    padding-top: 0;
  }
  &:last-child,
  &--last {
    border-bottom: none;
    padding-bottom: 0;
  }
}

.spec-label {
  flex-shrink: 0;
  width: 64px;
  padding-top: 8px;
  font-size: 13px;
  color: $text-color-placeholder;
  line-height: 1.4;
}

.spec-ctrl {
  flex: 1;
  min-width: 0;
}

/* ===== 步进器 ===== */
.stepper {
  display: inline-flex;
  align-items: stretch;
  border: 1px solid $border-color-strong;
  border-radius: 4px;
  overflow: hidden;
  height: 34px;
}

.stepper-dec,
.stepper-inc {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 34px;
  border: none;
  background: $bg-color-soft;
  color: $text-color-secondary;
  cursor: pointer;
  transition: background 0.15s;
  flex-shrink: 0;

  &:hover:not(:disabled) {
    background: $color-primary-soft;
    color: $color-primary;
  }
  &:disabled {
    opacity: 0.38;
    cursor: not-allowed;
  }
}

.stepper-dec {
  border-right: 1px solid $border-color-strong;
}
.stepper-inc {
  border-left: 1px solid $border-color-strong;
}

.stepper-val {
  width: 52px;
  border: none;
  text-align: center;
  font-size: 14px;
  font-weight: 600;
  color: $text-color-primary;
  background: $bg-color-card;

  &:focus-visible {
    outline: 2px solid $color-primary;
    outline-offset: -2px;
  }

  &::-webkit-outer-spin-button,
  &::-webkit-inner-spin-button {
    -webkit-appearance: none;
  }
}

/* ===== 操作系统双列 ===== */
.os-row {
  display: flex;
  gap: 12px;
}

.os-col {
  flex: 1;
  min-width: 0;

  :deep(.el-select) {
    width: 100%;
  }
}

.os-col-label {
  font-size: 12px;
  color: $text-color-placeholder;
  margin-bottom: 6px;
}

/* ===== 计费周期 ===== */
.cycle-wrap {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.cycle-btn {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 2px;
  padding: 8px 14px;
  border: 1px solid $border-color-strong;
  border-radius: 4px;
  background: $bg-color-soft;
  cursor: pointer;
  transition:
    border-color $motion-fast ease,
    background-color $motion-fast ease;

  &:hover {
    border-color: $color-primary;
  }
  &.active {
    border-color: $color-primary;
    background: $color-primary-soft;

    .cycle-name {
      color: $color-primary;
      font-weight: 600;
    }
    .cycle-price {
      color: $color-primary;
    }
  }
}

.cycle-name {
  font-size: 13px;
  color: $text-color-primary;
  line-height: 1.4;
}

.cycle-price {
  font-size: 12px;
  color: $text-color-placeholder;
  line-height: 1.4;
}

/* ===== 右侧摘要 ===== */
.pd-aside {
  position: sticky;
  /* 站头 64px + 16px 呼吸位；与 products 页侧栏的 64px 贴顶不同，
     这里是要塞在站头下方带间距的卡片区，故多留 16px。 */
  top: calc($navbar-height + 16px);

  @media (max-width: $breakpoint-nav) {
    position: static;
  }
}

.aside-card {
  background: $bg-color-card;
  border: 1px solid $border-color;
  border-radius: 4px;
  padding: 16px;
}

.cost-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 12px;
}

.cost-title {
  font-size: 15px;
  font-weight: 700;
  color: $text-color-primary;
}

.stock-badge {
  font-size: 12px;
  font-weight: 600;
  padding: 2px 10px;
  border-radius: 2px;

  // 浅底标签文字用深色变体，保证 12px 小字满足 WCAG AA（≥4.5:1）
  &.ok {
    background: $color-success-soft;
    color: $color-success-text;
  }
  &.warn {
    background: $color-warning-soft;
    color: $color-warning-text;
  }
  &.empty {
    background: $color-danger-soft;
    color: $color-danger-text;
  }
  &.sync {
    background: $color-primary-soft;
    color: $color-primary;
  }
}

.stock-info {
  padding: 10px 12px;
  border-radius: 2px;
  margin-bottom: 4px;

  &--ok {
    background: $color-success-soft;
  }

  &--warn {
    background: $color-warning-soft;
  }

  &--empty {
    background: $color-danger-soft;
  }

  &--sync {
    background: $color-primary-soft;
  }
}

.stock-main {
  font-size: 13px;
  color: $text-color-secondary;

  strong {
    font-size: 14px;
    font-weight: 700;
  }
}

.stock-info--ok .stock-main strong {
  color: $color-success-text;
}
.stock-info--warn .stock-main strong {
  color: $color-warning-text;
}
.stock-info--empty .stock-main strong {
  color: $color-danger-text;
}
.stock-info--sync .stock-main strong {
  color: $color-primary;
}

.stock-hint {
  font-size: 12px;
  color: $text-color-placeholder;
  margin-top: 4px;
}

.aside-divider {
  height: 1px;
  background: $divider-color;
  margin: 12px 0;
}

.aside-section {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.aside-section-title {
  font-size: 13px;
  font-weight: 700;
  color: $text-color-primary;
}

.cost-detail {
  display: flex;
  flex-direction: column;
  gap: 9px;
}

.cost-detail--loading,
.cost-breakdown--loading,
.cost-total--loading {
  position: relative;

  &::after {
    content: "";
    position: absolute;
    inset: -4px -6px;
    border-radius: 8px;
    background: linear-gradient(
      90deg,
      rgba($bg-color-card, 0) 0%,
      rgba($color-primary, 0.08) 35%,
      rgba($bg-color-card, 0.45) 50%,
      rgba($color-primary, 0.08) 65%,
      rgba($bg-color-card, 0) 100%
    );
    background-size: 220% 100%;
    pointer-events: none;
    animation: costLoadingSweep 1.35s linear infinite;
  }
}

.cost-breakdown {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.cost-item {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  font-size: 13px;
  gap: 8px;

  > span:first-child {
    color: $text-color-placeholder;
    flex-shrink: 0;
  }

  > span:last-child {
    color: $text-color-primary;
    font-weight: 500;
    text-align: right;
    word-break: break-all;
  }
}

.cost-item--extra {
  > span:first-child {
    color: $text-color-placeholder;
    font-size: 12px;
  }

  > span:last-child {
    color: $color-warning-text;
    font-size: 12px;
  }
}

.cost-item--discount {
  > span:first-child {
    color: $color-success-text;
    font-size: 12px;
    font-weight: 600;
  }

  > span:last-child {
    color: $color-success-text;
    font-size: 13px;
    font-weight: 700;
  }
}

.coupon-panel {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.coupon-panel-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
}

.coupon-panel-title {
  font-size: 13px;
  font-weight: 700;
  color: $text-color-primary;
}

.coupon-clear-btn {
  border: none;
  background: none;
  padding: 8px 0;
  color: $color-danger-text;
  font-size: 12px;
  cursor: pointer;
}

.coupon-panel-form {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  gap: 8px;
}

.coupon-panel-tip {
  padding: 10px 12px;
  border-radius: 4px;
  background: $color-success-soft;
  color: $color-success-text;
  font-size: 12px;
  line-height: 1.6;
}

.coupon-panel-tip--muted {
  background: $bg-color-soft;
  color: $text-color-secondary;
}

.purchase-guard-alert {
  margin: 12px 0;
}

.cost-total {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 8px 0 16px;
  border-top: 1px solid $divider-color;
  margin-top: 8px;
}

.cost-total-label {
  font-size: 13px;
  color: $text-color-placeholder;
}

.cost-price-wrap {
  display: flex;
  align-items: baseline;
  gap: 1px;
}

.cost-currency {
  font-size: 14px;
  color: $color-primary;
  font-weight: 700;
}

.cost-amount {
  font-size: $font-price-md;
  font-weight: 700;
  color: $color-primary;
  line-height: 1;
  font-variant-numeric: tabular-nums;
}

.cost-amount--loading {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 18px;
  color: $text-color-placeholder;

  &::after {
    content: "";
    width: 22px;
    height: 8px;
    border-radius: 999px;
    background:
      radial-gradient(circle, rgba($color-primary, 0.85) 0 2px, transparent 2.4px) left
        center / 8px 8px no-repeat,
      radial-gradient(circle, rgba($color-primary, 0.55) 0 2px, transparent 2.4px)
        center center / 8px 8px no-repeat,
      radial-gradient(circle, rgba($color-primary, 0.3) 0 2px, transparent 2.4px) right
        center / 8px 8px no-repeat;
    animation: costLoadingDots 0.95s ease-in-out infinite;
  }
}

.cost-cycle {
  font-size: 12px;
  color: $text-color-placeholder;
  margin-left: 2px;
}

@media (max-width: $breakpoint-nav) {
  .coupon-panel-form {
    grid-template-columns: 1fr;
  }
}

.buy-btn {
  width: 100%;
  height: 44px;
  border: none;
  border-radius: $btn-border-radius-sm;
  background: $color-primary;
  color: $text-color-inverse;
  font-size: 15px;
  font-weight: 700;
  cursor: pointer;
  letter-spacing: 0.04em;
  transition:
    background 0.15s,
    opacity 0.15s,
    transform 0.15s;

  &:hover:not(:disabled) {
    background: $color-primary-hover;
  }
  &:active:not(:disabled) {
    background: $color-primary-active;
    transform: scale(0.985);
  }
  &:disabled {
    opacity: 0.5;
    cursor: not-allowed;
  }
  &.loading {
    opacity: 0.7;
  }

  &.is-sold-out {
    background: linear-gradient(
      135deg,
      $color-danger-text 0%,
      $color-danger 100%
    );
    box-shadow: 0 0 0 0 $color-danger-a34;
    animation: soldOutPulse 1.7s ease-in-out infinite;
  }

  &.is-sold-out:hover {
    background: linear-gradient(
      135deg,
      $color-danger-text 0%,
      $color-danger 100%
    );
  }
}

.allocation-footer {
  display: none;

  @media (max-width: $breakpoint-nav) {
    position: fixed;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 30;
    display: block;
    padding: 10px 12px calc(10px + env(safe-area-inset-bottom, 0px));
    background: rgba(247, 248, 250, 0.94);
    backdrop-filter: blur(10px);
    border-top: 1px solid rgba(208, 211, 217, 0.78);
  }
}

.allocation-footer-inner {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 12px;
  border: 1px solid $border-color;
  border-radius: 14px;
  background: rgba(255, 255, 255, 0.98);
  box-shadow: 0 10px 28px rgba(29, 33, 41, 0.12);
}

.allocation-footer-main {
  flex: 1;
  min-width: 0;
}

.allocation-footer-label {
  font-size: 11px;
  line-height: 1;
  color: $text-color-placeholder;
}

.allocation-footer-price {
  display: flex;
  align-items: baseline;
  gap: 2px;
  margin-top: 6px;
  color: $color-primary;
}

.allocation-footer-symbol {
  font-size: 14px;
  font-weight: 700;
}

.allocation-footer-num {
  // 与商品配置页的吸底条统一为同一档金额字号（原 24px 与 22px 两页不一致）
  font-size: $font-price-md;
  line-height: 1;
  font-weight: 700;
  font-variant-numeric: tabular-nums;
}

.allocation-footer-cycle {
  font-size: 12px;
  color: $text-color-placeholder;
}

.allocation-footer-meta {
  display: flex;
  flex-wrap: wrap;
  gap: 4px 8px;
  margin-top: 6px;
  font-size: 11px;
  line-height: 1.5;
  color: $text-color-secondary;

  span {
    min-width: 0;
  }
}

.allocation-footer-action {
  flex-shrink: 0;
  width: 112px;
  height: 44px;
  border: none;
  border-radius: $btn-border-radius-sm;
  background: $color-primary;
  color: $text-color-inverse;
  font-size: 15px;
  font-weight: 600;
  cursor: pointer;
  transition:
    background 0.15s,
    opacity 0.15s,
    transform 0.15s;

  &:hover:not(:disabled) {
    background: $color-primary-hover;
  }
  &:active:not(:disabled) {
    background: $color-primary-active;
    transform: scale(0.97);
  }
  &:disabled {
    opacity: 0.5;
    cursor: not-allowed;
  }
  &.loading {
    opacity: 0.7;
  }

  &.is-sold-out {
    background: linear-gradient(
      135deg,
      $color-danger-text 0%,
      $color-danger 100%
    );
    box-shadow: 0 0 0 0 $color-danger-a34;
    animation: soldOutPulse 1.7s ease-in-out infinite;
  }

  &.is-sold-out:hover {
    background: linear-gradient(
      135deg,
      $color-danger-text 0%,
      $color-danger 100%
    );
  }
}

/* ===== 报价失败提示（桌面合计区 + 移动吸底栏共用） ===== */
.cost-quote-error {
  color: $color-danger-text;
  font-size: 14px;
  font-weight: 600;
}

.cost-quote-retry,
.allocation-footer-quote-retry {
  border: none;
  background: none;
  padding: 8px 4px;
  color: $color-primary;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;

  &:hover {
    color: $color-primary-hover;
  }
}

.allocation-footer-quote-error {
  color: $color-danger-text;
  font-size: 12px;
  font-weight: 600;
}

@keyframes costLoadingSweep {
  0% {
    background-position: 100% 0;
  }

  100% {
    background-position: -120% 0;
  }
}

@keyframes costLoadingDots {
  0%,
  100% {
    opacity: 0.8;
    transform: translateX(0);
  }

  50% {
    opacity: 1;
    transform: translateX(2px);
  }
}

@keyframes soldOutPulse {
  0%,
  100% {
    box-shadow: 0 0 0 0 $color-danger-a34;
  }

  50% {
    box-shadow: 0 0 0 6px $color-danger-a0;
  }
}

@media (max-width: $breakpoint-nav) {
  .pd-aside {
    position: static;
  }

  .aside-card {
    padding-bottom: 18px;
  }
}

/* ===== 空状态 ===== */
.pd-empty {
  display: flex;
  justify-content: center;
  align-items: center;
  min-height: 400px;
}

.pd-empty__actions {
  display: flex;
  gap: 12px;
  justify-content: center;
}

/* ===== 移动端选择器（≤992px 替换 el-select） ===== */
.pd-mobile-picker-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}

.pd-mobile-picker-col {
  min-width: 0;
}

.pd-mobile-picker-label {
  margin-bottom: 8px;
  font-size: 13px;
  color: $text-color-secondary;
}

.pd-mobile-picker-trigger,
.pd-mobile-trigger {
  width: 100%;
  min-height: 48px;
  padding: 0 14px;
  border: 1px solid $border-color-strong;
  border-radius: 4px;
  background: $bg-color-card;
  color: $text-color-primary;
  display: inline-flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  text-align: left;
  transition:
    border-color 0.15s,
    color 0.15s;

  span {
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  &:hover:not(:disabled) {
    border-color: $color-primary;
    color: $color-primary;
  }

  &:disabled {
    // 原 #c0c4cc 对白仅 1.75:1，改用已校验的禁用色令牌
    color: $text-color-disabled;
    cursor: not-allowed;
  }
}

.pd-coupon-sheet {
  padding: 10px 14px 18px;
}

.pd-coupon-option {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  width: 100%;
  min-height: 52px;
  padding: 0 14px;
  border: 1px solid $divider-color;
  border-radius: 6px;
  background: $bg-color-card;
  color: $text-color-primary;
  font-size: 14px;
  text-align: left;
  cursor: pointer;
  transition:
    border-color 0.15s,
    background-color 0.15s;

  & + .pd-coupon-option {
    margin-top: 8px;
  }

  &.is-active {
    border-color: $color-primary;
    background: $color-primary-soft;
    color: $color-primary;
    font-weight: 600;
  }

  &--none {
    color: $text-color-secondary;
    font-weight: 400;
  }
}

.pd-coupon-name {
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.pd-coupon-check {
  flex-shrink: 0;
  font-weight: 700;
}
</style>
