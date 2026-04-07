<template>
  <div class="pd-page" v-loading="loading">
    <div v-if="product">

      <!-- 顶部工具栏：分类 + 型号横向Tab -->
      <div class="pd-topbar">
        <button type="button" class="category-btn" @click="router.push('/products')">
          <svg viewBox="0 0 16 16" fill="none" width="14" height="14">
            <rect x="1" y="1" width="6" height="6" rx="1" stroke="currentColor" stroke-width="1.5"/>
            <rect x="9" y="1" width="6" height="6" rx="1" stroke="currentColor" stroke-width="1.5"/>
            <rect x="1" y="9" width="6" height="6" rx="1" stroke="currentColor" stroke-width="1.5"/>
            <rect x="9" y="9" width="6" height="6" rx="1" stroke="currentColor" stroke-width="1.5"/>
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
          >{{ sib.name }}</button>
        </div>
      </div>

      <!-- 产品名称下拉按钮 -->
      <div class="pd-name-bar">
        <button type="button" class="product-name-btn active">
          <span>{{ product.name }}</span>
          <svg viewBox="0 0 12 12" fill="none" width="12" height="12"><path d="M3 4.5l3 3 3-3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </button>
      </div>

      <!-- 主体：左配置 + 右摘要 -->
      <div class="pd-body">
        <div class="pd-config">

          <!-- 区域 -->
          <div class="cfg-group" v-if="regionOptions.length">
            <div class="cfg-group-head">区域</div>
            <div class="cfg-group-body">
              <div class="opt-wrap">
                <button
                  v-for="opt in regionOptions"
                  :key="opt.id"
                  type="button"
                  class="opt-btn"
                  :class="{ active: configForm[regionKey] === opt.id }"
                  @click="configForm[regionKey] = opt.id; fetchQuote()"
                >{{ opt.label }}</button>
              </div>
            </div>
          </div>

          <!-- 操作系统 -->
          <div class="cfg-group" v-if="osConfig">
            <div class="cfg-group-head">操作系统</div>
            <div class="cfg-group-body">
              <div class="os-row">
                <div class="os-col">
                  <div class="os-col-label">系统</div>
                  <div class="custom-sel" @click="osGroupOpen = !osGroupOpen; osVerOpen = false">
                    <span class="sel-text">{{ configForm.os_group || '' }}</span>
                    <svg viewBox="0 0 12 12" fill="none" width="12" height="12" class="sel-arrow"><path d="M3 4.5l3 3 3-3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    <div class="sel-dropdown" v-if="osGroupOpen" @click.stop>
                      <div
                        v-for="os in osGroups"
                        :key="os.id"
                        class="sel-item"
                        :class="{ active: configForm.os_group === os.id }"
                        @click="selectOsGroup(os); osGroupOpen = false"
                      >{{ os.label }}</div>
                    </div>
                  </div>
                </div>
                <div class="os-col">
                  <div class="os-col-label">版本</div>
                  <div class="custom-sel" @click="osVerOpen = !osVerOpen; osGroupOpen = false">
                    <span class="sel-text sel-text--sm">{{ currentOsVerLabel }}</span>
                    <svg viewBox="0 0 12 12" fill="none" width="12" height="12" class="sel-arrow"><path d="M3 4.5l3 3 3-3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    <div class="sel-dropdown" v-if="osVerOpen" @click.stop>
                      <div
                        v-for="ver in currentOsGroup?.versions"
                        :key="ver.id"
                        class="sel-item"
                        :class="{ active: configForm.os === ver.id }"
                        @click="configForm.os = ver.id; osVerOpen = false; fetchQuote()"
                      >{{ ver.label }}</div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- 机型配置 -->
          <div class="cfg-group" v-if="specConfigs.length">
            <div class="cfg-group-head">机型配置</div>
            <div class="cfg-group-body">
              <div class="spec-row" v-for="cfg in specConfigs" :key="cfg.key">
                <div class="spec-label">{{ cfg.label }}</div>
                <div class="spec-ctrl">
                  <template v-if="cfg.mode === 'range'">
                    <div class="stepper">
                      <button type="button" class="stepper-dec" @click="stepRange(cfg, -1)" :disabled="configForm[cfg.key] <= cfg.min">
                        <svg viewBox="0 0 14 14" fill="none" width="14" height="14"><path d="M2.5 7h9" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                      </button>
                      <input type="number" class="stepper-val" :value="configForm[cfg.key]" readonly />
                      <button type="button" class="stepper-inc" @click="stepRange(cfg, 1)" :disabled="cfg.max > 0 && configForm[cfg.key] >= cfg.max">
                        <svg viewBox="0 0 14 14" fill="none" width="14" height="14"><path d="M7 2.5v9M2.5 7h9" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                      </button>
                    </div>
                  </template>
                  <div class="opt-wrap" v-else-if="cfg.options.length > 1">
                    <button
                      v-for="opt in cfg.options"
                      :key="opt.id"
                      type="button"
                      class="opt-btn"
                      :class="{ active: configForm[cfg.key] === opt.id }"
                      @click="configForm[cfg.key] = opt.id; fetchQuote()"
                    >{{ opt.label }}</button>
                  </div>
                  <button v-else-if="cfg.options.length === 1" type="button" class="opt-btn active">{{ cfg.options[0].label }}</button>
                </div>
              </div>
            </div>
          </div>

          <!-- 网络配置 -->
          <div class="cfg-group" v-if="netConfigs.length">
            <div class="cfg-group-head">网络配置</div>
            <div class="cfg-group-body">
              <div class="spec-row" v-for="cfg in netConfigs" :key="cfg.key">
                <div class="spec-label">{{ cfg.label }}</div>
                <div class="spec-ctrl">
                  <template v-if="cfg.mode === 'range'">
                    <div class="stepper">
                      <button type="button" class="stepper-dec" @click="stepRange(cfg, -1)" :disabled="configForm[cfg.key] <= cfg.min">
                        <svg viewBox="0 0 14 14" fill="none" width="14" height="14"><path d="M2.5 7h9" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                      </button>
                      <input type="number" class="stepper-val" :value="configForm[cfg.key]" readonly />
                      <button type="button" class="stepper-inc" @click="stepRange(cfg, 1)" :disabled="cfg.max > 0 && configForm[cfg.key] >= cfg.max">
                        <svg viewBox="0 0 14 14" fill="none" width="14" height="14"><path d="M7 2.5v9M2.5 7h9" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                      </button>
                    </div>
                  </template>
                  <div class="opt-wrap" v-else-if="cfg.options.length > 1">
                    <button
                      v-for="opt in cfg.options"
                      :key="opt.id"
                      type="button"
                      class="opt-btn"
                      :class="{ active: configForm[cfg.key] === opt.id }"
                      @click="configForm[cfg.key] = opt.id; fetchQuote()"
                    >{{ opt.label }}</button>
                  </div>
                  <button v-else-if="cfg.options.length === 1" type="button" class="opt-btn active">{{ cfg.options[0].label }}</button>
                </div>
              </div>
            </div>
          </div>

          <!-- 基础设置 -->
          <div class="cfg-group">
            <div class="cfg-group-head">基础设置</div>
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
              <!-- 购买数量 -->
              <div class="spec-row spec-row--last">
                <div class="spec-label">购买数量</div>
                <div class="spec-ctrl">
                  <div class="stepper">
                    <button type="button" class="stepper-dec" @click="quantity > 1 && quantity--" :disabled="quantity <= 1">
                      <svg viewBox="0 0 14 14" fill="none" width="14" height="14"><path d="M2.5 7h9" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                    </button>
                    <input type="number" class="stepper-val" :value="quantity" readonly />
                    <button type="button" class="stepper-inc" @click="quantity < 10 && quantity++" :disabled="quantity >= 10">
                      <svg viewBox="0 0 14 14" fill="none" width="14" height="14"><path d="M7 2.5v9M2.5 7h9" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>

        </div><!-- /pd-config -->

        <!-- 右侧：配置费用摘要 -->
        <aside class="pd-aside">
          <div class="aside-card">
            <div class="aside-title">配置费用</div>
            <div class="aside-list">
              <div class="aside-item">
                <span class="ai-key">产品</span>
                <span class="ai-val">{{ product.name }}</span>
              </div>
              <div class="aside-item" v-if="regionOptions.length && configForm[regionKey]">
                <span class="ai-key">区域</span>
                <span class="ai-val">{{ regionOptions.find(o => o.id === configForm[regionKey])?.label || configForm[regionKey] }}</span>
              </div>
              <div class="aside-item" v-if="currentOsVerLabel">
                <span class="ai-key">操作系统</span>
                <span class="ai-val">{{ currentOsVerLabel }}</span>
              </div>
              <template v-for="cfg in specConfigs" :key="cfg.key">
                <div class="aside-item" v-if="configForm[cfg.key] !== undefined && configForm[cfg.key] !== ''">
                  <span class="ai-key">{{ cfg.label }}</span>
                  <span class="ai-val">
                    <template v-if="cfg.mode === 'range'">{{ configForm[cfg.key] }}{{ cfg.suffix }}</template>
                    <template v-else>{{ cfg.options.find(o => o.id === configForm[cfg.key])?.label || configForm[cfg.key] }}</template>
                  </span>
                </div>
              </template>
              <template v-for="cfg in netConfigs" :key="cfg.key">
                <div class="aside-item" v-if="configForm[cfg.key] !== undefined && configForm[cfg.key] !== ''">
                  <span class="ai-key">{{ cfg.label }}</span>
                  <span class="ai-val">
                    <template v-if="cfg.mode === 'range'">{{ configForm[cfg.key] }}{{ cfg.suffix }}</template>
                    <template v-else>{{ cfg.options.find(o => o.id === configForm[cfg.key])?.label || configForm[cfg.key] }}</template>
                  </span>
                </div>
              </template>
              <div class="aside-item" v-if="selectedCycleLabel">
                <span class="ai-key">周期</span>
                <span class="ai-val">{{ selectedCycleLabel }}</span>
              </div>
              <div class="aside-item coupon-discount" v-if="appliedCoupon">
                <span class="ai-key">优惠券</span>
                <span class="ai-val">-¥{{ appliedCoupon.discount_amount }}</span>
              </div>
            </div>

            <div class="aside-divider"></div>

            <div class="coupon-panel">
              <div class="coupon-panel-head">
                <span class="coupon-panel-title">优惠券</span>
                <button v-if="appliedCoupon" type="button" class="coupon-clear-btn" @click="clearCoupon">移除</button>
              </div>
              <div class="coupon-panel-form">
                <el-select
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
                {{ appliedCoupon.name }}，{{ appliedCoupon.discount_label }}，本次已减免 ¥{{ appliedCoupon.discount_amount }}
              </div>
              <div v-else-if="!availableCoupons.length" class="coupon-panel-tip coupon-panel-tip--muted">
                {{ product ? '当前暂无可用优惠券，登录后如有优惠券会自动展示在这里。' : '请选择商品后查看可用优惠券。' }}
              </div>
            </div>

            <div class="aside-divider"></div>

            <div class="aside-total-row">
              <span class="at-label">合计费用</span>
              <div class="at-price">
                <span class="atp-symbol">¥</span>
                <span class="atp-num" v-if="quoteLoading">…</span>
                <span class="atp-num" v-else>{{ totalAmount }}</span>
                <span class="atp-cycle">/{{ selectedCycleLabel || '月付' }}</span>
              </div>
            </div>

            <button
              class="buy-btn"
              :disabled="!canSubmit || submitting || quoteLoading"
              :class="{ loading: submitting }"
              @click="handleSubmit"
            >
              <span v-if="submitting">提交中...</span>
              <span v-else>立即购买</span>
            </button>
          </div>
        </aside>
      </div><!-- /pd-body -->
    </div>

    <div class="pd-empty" v-else-if="!loading">
      <el-empty description="商品不存在或已下架">
        <el-button type="primary" @click="router.push('/products')">返回产品页</el-button>
      </el-empty>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import clientApi from '@/api/client'
import siteApi from '@/api/site'
import { getToken } from '@/utils/auth'
import {
  buildWebsiteProductPath,
  resolveWebsiteProductRoutePayloadByDetail,
} from '@/utils/productRoute'
import {
  buildOsGroups,
  buildPricingEntries,
  normalizeMoneyText,
  OS_KEYS,
  parseField,
  parseParamOptions,
  REGION_FIELD_KEYS as REGION_KEYS,
} from '@/utils/websiteProductConfig'
import { buildIdempotencyKey, savePendingWebsiteCheckout } from '@/utils/websiteCheckout'

const route = useRoute()
const router = useRouter()

const loading = ref(false)
const submitting = ref(false)
const product = ref(null)
const siblings = ref([])
const selectedCycle = ref('')
const hostname = ref('')
const password = ref('')
const quantity = ref(1)
const selectedCouponId = ref(0)

const configForm = reactive({})
const quoteResult = ref(null)
const quoteLoading = ref(false)
const quoteToken = ref('')
const productStock = ref(null)
const productStockLoading = ref(false)
const productStockError = ref('')

// OS 下拉开关
const osGroupOpen = ref(false)
const osVerOpen = ref(false)
// 网络关键词（带宽/IP/流量/防护等）
const NET_KEYWORDS = ['bandwidth', 'bw', 'ip', 'traffic', 'ddos', 'port', 'speed', 'mbps', 'gbps', '带宽', '流量']

function isNetConfig(key, label) {
  const k = key.toLowerCase()
  const l = String(label || '').toLowerCase()
  return NET_KEYWORDS.some(kw => k.includes(kw) || l.includes(kw))
}

const allParsedConfigs = computed(() => {
  const raw = product.value?.config_options || []
  return raw.map(item => {
    const { key, label } = parseField(item)
    const mode = item.option_mode === 'range' ? 'range' : 'select'
    return {
      key, label, mode,
      required: item.required === true || item.required === 1,
      description: item.description || '',
      hidden: item.hidden === 1,
      sortOrder: Number(item.sort_order || 0),
      options: mode === 'range' ? [] : parseParamOptions(item.parameter),
      min: Number(item.qty_minimum ?? 0),
      max: Number(item.qty_maximum ?? 0),
      step: Number(item.qty_step ?? item.qty_stage ?? 1),
      suffix: String(item.suffix_text || ''),
    }
  }).filter(c => c.key && !c.hidden && (c.mode === 'range' || c.options.length > 0))
    .sort((a, b) => a.sortOrder - b.sortOrder)
})

// 区域
const regionKey = computed(() => {
  const cfg = allParsedConfigs.value.find(c => REGION_KEYS.some(k => c.key.toLowerCase().includes(k)))
  return cfg?.key || 'region'
})
const regionOptions = computed(() => {
  const cfg = allParsedConfigs.value.find(c => REGION_KEYS.some(k => c.key.toLowerCase().includes(k)))
  return cfg?.options || []
})

// OS
const osConfig = computed(() => {
  const raw = product.value?.config_options || []
  return raw.find(item => {
    const { key } = parseField(item)
    return OS_KEYS.includes(key) && item.hidden !== 1
  })
})

const osGroups = computed(() => buildOsGroups(osConfig.value))

const currentOsGroup = computed(() => osGroups.value.find(g => g.id === configForm.os_group))
const currentOsVerLabel = computed(() => currentOsGroup.value?.versions?.find(v => v.id === configForm.os)?.label || '')

function selectOsGroup(os) {
  configForm.os_group = os.id
  if (os.versions?.length) configForm.os = os.versions[0].id
  fetchQuote()
}

// 机型配置（排除 区域/OS/网络）
const specConfigs = computed(() => allParsedConfigs.value.filter(c =>
  !OS_KEYS.includes(c.key) &&
  !REGION_KEYS.some(k => c.key.toLowerCase().includes(k)) &&
  !isNetConfig(c.key, c.label)
))

// 网络配置
const netConfigs = computed(() => allParsedConfigs.value.filter(c =>
  !OS_KEYS.includes(c.key) &&
  !REGION_KEYS.some(k => c.key.toLowerCase().includes(k)) &&
  isNetConfig(c.key, c.label)
))

function stepRange(cfg, dir) {
  const cur = Number(configForm[cfg.key] ?? cfg.min)
  const next = cur + dir * cfg.step
  configForm[cfg.key] = Math.min(Math.max(next, cfg.min), cfg.max > 0 ? cfg.max : Infinity)
  fetchQuote()
}

// 价格
const pricingEntries = computed(() => buildPricingEntries(product.value))

const selectedPricingEntry = computed(() => pricingEntries.value.find(i => i.cycle === selectedCycle.value) || null)
const selectedCycleLabel = computed(() => selectedPricingEntry.value?.label || '')
const appliedCoupon = computed(() => quoteResult.value?.coupon || null)
const availableCoupons = computed(() => quoteResult.value?.available_coupons || [])
const totalAmount = computed(() => {
  if (quoteResult.value) return quoteResult.value.total_amount || '0.00'
  return (Number(selectedPricingEntry.value?.total_amount || 0) * quantity.value).toFixed(2)
})

let quoteTimer = null
function applyQuoteResult(payload, nextCouponId = selectedCouponId.value) {
  quoteResult.value = payload || null
  quoteToken.value = String(payload?.quote_token || '')
  selectedCouponId.value = Number(nextCouponId || payload?.user_coupon_id || 0)
}

async function requestQuote(nextCouponId = selectedCouponId.value) {
  return siteApi.productQuote(product.value.id, {
    billing_cycle: selectedCycle.value,
    config: buildConfigPayload(),
    quantity: quantity.value,
    user_coupon_id: Number(nextCouponId || 0) || undefined,
  })
}

function looksLikeCouponError(error) {
  const message = String(error?.response?.data?.message || error?.message || '')
  return message.includes('优惠券') || message.includes('优惠码')
}

async function executeQuote(nextCouponId = selectedCouponId.value, options = {}) {
  if (!product.value || !selectedCycle.value) return

  const snapshot = options.rollbackOnError
    ? {
        quoteResult: quoteResult.value,
        quoteToken: quoteToken.value,
        selectedCouponId: selectedCouponId.value,
      }
    : null

  quoteLoading.value = true

  try {
    const res = await requestQuote(nextCouponId)
    applyQuoteResult(res.data || null, nextCouponId)
    return true
  } catch (error) {
    if (snapshot) {
      quoteResult.value = snapshot.quoteResult
      quoteToken.value = snapshot.quoteToken
      selectedCouponId.value = snapshot.selectedCouponId
      return false
    }

    if (Number(nextCouponId || 0) > 0 && options.fallbackInvalidCoupon && looksLikeCouponError(error)) {
      selectedCouponId.value = 0

      try {
        const fallbackRes = await requestQuote(0)
        applyQuoteResult(fallbackRes.data || null, 0)
        return false
      } catch {
        quoteResult.value = null
        quoteToken.value = ''
        return false
      }
    }

    quoteResult.value = null
    quoteToken.value = ''
    return false
  } finally {
    quoteLoading.value = false
  }
}

function handleCouponChange(value) {
  if (!product.value || !selectedCycle.value) {
    selectedCouponId.value = Number(value || 0)
    return
  }

  selectedCouponId.value = Number(value || 0)
  fetchQuote()
}

async function clearCoupon() {
  selectedCouponId.value = 0
  await executeQuote(0, { fallbackInvalidCoupon: false })
}

function fetchQuote() {
  clearTimeout(quoteTimer)
  quoteTimer = setTimeout(() => {
    executeQuote(selectedCouponId.value, { fallbackInvalidCoupon: true })
  }, 300)
}

function buildConfigPayload() {
  const cfg = {}
  for (const key of Object.keys(configForm)) {
    if (configForm[key] !== undefined && configForm[key] !== null && configForm[key] !== '') {
      cfg[key] = configForm[key]
    }
  }
  return cfg
}

const defaultHostname = computed(() => `svr${Math.floor(Math.random() * 9e8 + 1e8)}`)
const resolvedStock = computed(() => {
  if (productStock.value !== null && productStock.value !== undefined) {
    return Number(productStock.value)
  }

  return null
})
const canSubmit = computed(() => {
  const stock = resolvedStock.value
  return Boolean(selectedCycle.value) && Boolean(quoteToken.value) && !quoteLoading.value && !productStockLoading.value && !productStockError.value && stock !== null && stock !== 0
})

function buildOrderPayload() {
  const payload = {
    product_id: Number(product.value?.id || 0),
    billing_cycle: selectedCycle.value,
    quantity: quantity.value,
    config: buildConfigPayload(),
    quote_token: quoteToken.value,
  }

  if (selectedCouponId.value > 0) {
    payload.user_coupon_id = selectedCouponId.value
  }

  return payload
}

async function handleSubmit() {
  if (productStockLoading.value) {
    ElMessage.warning('库存同步中，请稍候')
    return
  }
  if (productStockError.value) {
    ElMessage.warning('库存同步失败，请稍后重试')
    return
  }
  if (resolvedStock.value === 0) {
    ElMessage.warning('当前库存不足，暂时无法购买')
    return
  }
  if (!quoteToken.value) {
    ElMessage.warning('报价凭证已失效，请稍候重试')
    return
  }
  if (!canSubmit.value) { ElMessage.warning('请选择计费周期'); return }
  submitting.value = true
  try {
    const orderPayload = buildOrderPayload()
    const idempotencyKey = buildIdempotencyKey('website-order')

    if (!getToken()) {
      savePendingWebsiteCheckout({
        source: 'website-product-detail',
        createdAt: Date.now(),
        idempotencyKey,
        orderPayload,
      })
      ElMessage.success('请先登录，登录后将继续创建订单')
      await router.push({
        path: '/client/login',
        query: { redirect: '/client/checkout-resume' },
      })
      return
    }

    const res = await clientApi.createOrder(orderPayload, {
      headers: {
        'X-Idempotency-Key': idempotencyKey,
      },
    })
    const orderId = Number(res.data?.id || 0)
    ElMessage.success('订单创建成功，正在跳转支付')
    await router.push(orderId > 0 ? `/client/orders/${orderId}` : '/client/orders')
  } catch (err) {
    ElMessage.error(err?.response?.data?.message || '下单失败，请重试')
  } finally {
    submitting.value = false
  }
}

function switchProduct(id) {
  const routePayload = resolveWebsiteProductRoutePayloadByDetail(product.value)
  router.replace(buildWebsiteProductPath({
    ...routePayload,
    productId: Number(id || 0),
  }))
}

function initDefaults() {
  if (pricingEntries.value.length) selectedCycle.value = pricingEntries.value[0].cycle

  for (const cfg of [...specConfigs.value, ...netConfigs.value]) {
    if (cfg.mode === 'range') {
      configForm[cfg.key] = cfg.min
    } else if (cfg.options.length > 0) {
      configForm[cfg.key] = cfg.options[0].id
    }
  }

  if (regionOptions.value.length) configForm[regionKey.value] = regionOptions.value[0].id
  if (osGroups.value.length) selectOsGroup(osGroups.value[0])

  hostname.value = defaultHostname.value
  password.value = ''
  quantity.value = 1
  quoteResult.value = null
  quoteToken.value = ''
  fetchQuote()
}

async function loadProduct() {
  const pid = Number(route.params.id || 0)
  if (!pid) { router.push('/products'); return }
  productStock.value = null
  productStockError.value = ''
  refreshProductStock(pid)
  loading.value = true
  try {
    const res = await siteApi.product(pid)
    product.value = res.data.product || null
    siblings.value = res.data.product?.siblings || []
    initDefaults()
  } catch { product.value = null } finally { loading.value = false }
}

async function refreshProductStock(id) {
  if (!id) return

  productStockLoading.value = true
  productStockError.value = ''
  productStock.value = null

  try {
    const res = await siteApi.productStock(id)
    if (Number(route.params.id || 0) !== id) return
    productStock.value = Number(res.data?.stock ?? 0)
  } catch (err) {
    if (Number(route.params.id || 0) !== id) return
    productStockError.value = err?.response?.data?.message || '库存同步失败'
  } finally {
    if (Number(route.params.id || 0) === id) {
      productStockLoading.value = false
    }
  }
}

watch(() => route.params.id, (v) => { if (v) loadProduct() })
watch(selectedCycle, fetchQuote)
watch(configForm, fetchQuote, { deep: true })
watch(quantity, fetchQuote)
watch(() => route.query.user_coupon_id, (value) => {
  const nextCouponId = Number(value || 0)

  if (nextCouponId === selectedCouponId.value) {
    return
  }

  selectedCouponId.value = nextCouponId

  if (product.value && selectedCycle.value) {
    fetchQuote()
  }
}, { immediate: true })
onMounted(loadProduct)
</script>

<style scoped lang="scss">
/* 目标页精确色值：
   主色   #165dff (rgb(22,93,255))
   激活bg #e8f1ff (rgb(232,241,255))
   页面bg #f7f8fa (rgb(247,248,250))
   卡片bg #fff
   边框   #e8e8e8 / #d0d3d9
   文字主 #1d2129
   文字辅 #4e5969
   文字淡 #86909c
*/

.pd-page {
  min-height: calc(100vh - 160px);
  background: #f7f8fa;
  padding-bottom: 40px;
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
  border: 1px solid #d0d3d9;
  border-radius: 4px;
  background: #fff;
  color: #4e5969;
  font-size: 13px;
  cursor: pointer;
  white-space: nowrap;
  transition: border-color 0.15s, color 0.15s;

  &:hover { border-color: #165dff; color: #165dff; }

  svg { color: #86909c; }
}

.sibling-scroll {
  display: flex;
  gap: 8px;
  overflow-x: auto;
  scrollbar-width: none;
  &::-webkit-scrollbar { display: none; }
}

.sib-tab {
  flex-shrink: 0;
  height: 34px;
  padding: 0 14px;
  border: 1px solid #d0d3d9;
  border-radius: 4px;
  background: #fff;
  color: #4e5969;
  font-size: 13px;
  cursor: pointer;
  white-space: nowrap;
  transition: all 0.15s;

  &:hover { border-color: #165dff; color: #165dff; }
  &.active {
    border-color: #165dff;
    background: #165dff;
    color: #fff;
    font-weight: 600;
  }
}

/* ===== 产品名称栏 ===== */
.pd-name-bar {
  padding: 10px 16px 0;
}

.product-name-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  height: 36px;
  padding: 0 14px;
  border: 1px solid #d0d3d9;
  border-radius: 4px;
  background: #fff;
  color: #1d2129;
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
  max-width: 100%;

  span {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  svg { flex-shrink: 0; color: #86909c; }

  &.active {
    border-color: #165dff;
    color: #165dff;
    svg { color: #165dff; }
  }
}

/* ===== 主体 ===== */
.pd-body {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 268px;
  gap: 12px;
  padding: 12px 16px 0;
  align-items: start;

  @media (max-width: 768px) {
    grid-template-columns: 1fr;
  }
}

/* ===== 配置分组卡片 ===== */
.cfg-group {
  background: #fff;
  border-radius: 4px;
  margin-bottom: 10px;
  border: 1px solid #e8e8e8;
}

.cfg-group-head {
  padding: 12px 16px 10px;
  font-size: 14px;
  font-weight: 600;
  color: #1d2129;
  border-bottom: 1px solid #f2f3f5;
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
  border: 1px solid #d0d3d9;
  border-radius: 4px;
  background: #fafafa;
  color: #1d2129;
  font-size: 13px;
  cursor: pointer;
  white-space: nowrap;
  transition: all 0.15s;

  &:hover { border-color: #165dff; color: #165dff; background: #fff; }
  &.active {
    border-color: #165dff;
    background: #e8f1ff;
    color: #165dff;
    font-weight: 600;
  }
}

/* ===== 规格行 ===== */
.spec-row {
  display: flex;
  align-items: flex-start;
  gap: 16px;
  padding: 10px 0;
  border-bottom: 1px solid #f2f3f5;

  &:first-child { padding-top: 0; }
  &:last-child, &--last { border-bottom: none; padding-bottom: 0; }
}

.spec-label {
  flex-shrink: 0;
  width: 64px;
  padding-top: 8px;
  font-size: 13px;
  color: #86909c;
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
  border: 1px solid #d0d3d9;
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
  background: #fafafa;
  color: #4e5969;
  cursor: pointer;
  transition: background 0.15s;
  flex-shrink: 0;

  &:hover:not(:disabled) { background: #e8f1ff; color: #165dff; }
  &:disabled { opacity: 0.38; cursor: not-allowed; }
}

.stepper-dec { border-right: 1px solid #d0d3d9; }
.stepper-inc { border-left: 1px solid #d0d3d9; }

.stepper-val {
  width: 52px;
  border: none;
  text-align: center;
  font-size: 14px;
  font-weight: 600;
  color: #1d2129;
  background: #fff;
  outline: none;

  &::-webkit-outer-spin-button,
  &::-webkit-inner-spin-button { -webkit-appearance: none; }
}

/* ===== 操作系统双列 ===== */
.os-row {
  display: flex;
  gap: 12px;
}

.os-col {
  flex: 1;
  min-width: 0;
}

.os-col-label {
  font-size: 12px;
  color: #86909c;
  margin-bottom: 6px;
}

.custom-sel {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: space-between;
  height: 34px;
  padding: 0 10px;
  border: 1px solid #d0d3d9;
  border-radius: 4px;
  background: #fff;
  cursor: pointer;
  gap: 4px;
  transition: border-color 0.15s;

  &:hover { border-color: #165dff; }
}

.sel-text {
  flex: 1;
  font-size: 13px;
  color: #1d2129;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;

  &--sm { font-size: 12px; }
}

.sel-arrow {
  flex-shrink: 0;
  color: #86909c;
}

.sel-dropdown {
  position: absolute;
  top: calc(100% + 4px);
  left: 0;
  right: 0;
  background: #fff;
  border: 1px solid #e8e8e8;
  border-radius: 4px;
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
  z-index: 200;
  max-height: 220px;
  overflow-y: auto;
}

.sel-item {
  padding: 9px 12px;
  font-size: 13px;
  color: #1d2129;
  cursor: pointer;
  transition: background 0.12s;

  &:hover { background: #f7f8fa; }
  &.active { background: #e8f1ff; color: #165dff; font-weight: 600; }
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
  border: 1px solid #d0d3d9;
  border-radius: 4px;
  background: #fafafa;
  cursor: pointer;
  transition: all 0.15s;

  &:hover { border-color: #165dff; }
  &.active {
    border-color: #165dff;
    background: #e8f1ff;

    .cycle-name { color: #165dff; font-weight: 600; }
    .cycle-price { color: #165dff; }
  }
}

.cycle-name {
  font-size: 13px;
  color: #1d2129;
  line-height: 1.4;
}

.cycle-price {
  font-size: 12px;
  color: #86909c;
  line-height: 1.4;
}

/* ===== 右侧摘要 ===== */
.pd-aside {
  position: sticky;
  top: 80px;

  @media (max-width: 768px) {
    position: static;
  }
}

.aside-card {
  background: #fff;
  border: 1px solid #e8e8e8;
  border-radius: 4px;
  padding: 16px;
}

.aside-title {
  font-size: 15px;
  font-weight: 700;
  color: #1d2129;
  margin-bottom: 14px;
}

.aside-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-bottom: 12px;
}

.aside-item {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 8px;
  font-size: 13px;
}

.ai-key {
  color: #86909c;
  flex-shrink: 0;
}

.ai-val {
  color: #1d2129;
  font-weight: 500;
  text-align: right;
  word-break: break-all;
}

.coupon-discount .ai-val {
  color: $color-success;
}

.aside-divider {
  height: 1px;
  background: #f2f3f5;
  margin: 12px 0;
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
  color: #1d2129;
}

.coupon-clear-btn {
  border: none;
  background: none;
  padding: 0;
  color: $color-danger;
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
  color: $color-success;
  font-size: 12px;
  line-height: 1.6;
}

.coupon-panel-tip--muted {
  background: $bg-color-soft;
  color: $text-color-secondary;
}

@media (max-width: 768px) {
  .coupon-panel-form {
    grid-template-columns: 1fr;
  }
}

.aside-total-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 14px;
}

.at-label {
  font-size: 13px;
  color: #86909c;
}

.at-price {
  display: flex;
  align-items: baseline;
  gap: 1px;
}

.atp-symbol {
  font-size: 14px;
  color: #165dff;
  font-weight: 600;
}

.atp-num {
  font-size: 26px;
  font-weight: 700;
  color: #165dff;
  line-height: 1;
}

.atp-cycle {
  font-size: 12px;
  color: #86909c;
  margin-left: 2px;
}

.buy-btn {
  width: 100%;
  height: 42px;
  border: none;
  border-radius: 4px;
  background: #165dff;
  color: #fff;
  font-size: 15px;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.15s, opacity 0.15s;

  &:hover:not(:disabled) { background: #0e4ee0; }
  &:disabled { opacity: 0.5; cursor: not-allowed; }
  &.loading { opacity: 0.7; }
}

/* ===== 空状态 ===== */
.pd-empty {
  display: flex;
  justify-content: center;
  align-items: center;
  min-height: 400px;
}
</style>
