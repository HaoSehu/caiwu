import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { ElMessage } from 'element-plus'
import { useRouter } from 'vue-router'
import clientApi from '@/api/client'
import siteApi from '@/api/site'
import { useUserStore } from '@/stores/user'
import { getToken } from '@/utils/auth'
import {
  resolveMissingPurchaseRequirements,
  resolvePurchaseRequirementList,
  resolvePurchaseRequirementSummary,
} from '@/utils/productPurchaseRequirements'
import { normalizeMoneyText, resolveProductDisplayName } from '@/utils/websiteProductConfig'
import { buildIdempotencyKey, savePendingWebsiteCheckout } from '@/utils/websiteCheckout'
import { clearPendingWebsiteCoupon, getPendingWebsiteCouponId } from '@/utils/websiteCoupon'

export function useWebsiteProductCheckout({
  productDetail,
  configForm,
  pricingEntries,
  buildConfigPayload,
  initProductDefaults,
  resetConfigForm,
}) {
  const router = useRouter()
  const userStore = useUserStore()

  const configLoading = ref(false)
  const submitting = ref(false)
  const selectedCycle = ref('')
  const quantity = ref(1)
  const quoteResult = ref(null)
  const quoteLoading = ref(false)
  const quoteToken = ref('')
  const productStock = ref(null)
  const productStockLoading = ref(false)
  const productStockError = ref('')
  const selectedCouponId = ref(getPendingWebsiteCouponId())
  const productDetailCache = {}

  let currentProductId = 0
  let detailToken = 0
  let stockToken = 0
  let quoteTokenId = 0
  let quoteTimer = null
  let detailAbortController = null
  let stockAbortController = null
  let quoteAbortController = null
  let quoteWatchSuspendCount = 0

  const selectedProduct = computed(() => productDetail.value)
  const selectedProductDisplayName = computed(() => resolveProductDisplayName(selectedProduct.value))
  const selectedPricingEntry = computed(() => pricingEntries.value.find((item) => item.cycle === selectedCycle.value) || null)
  const cyclePrice = computed(() => selectedPricingEntry.value?.amount || '0.00')
  const baseAmount = computed(() => quoteResult.value?.base_amount ?? cyclePrice.value)
  const setupFee = computed(() => quoteResult.value?.setup_fee ?? productDetail.value?.setup_fee_display ?? normalizeMoneyText(productDetail.value?.setup_fee || 0))
  const quoteItems = computed(() => quoteResult.value?.items || [])
  const appliedCoupon = computed(() => quoteResult.value?.coupon || null)
  const availableCoupons = computed(() => quoteResult.value?.available_coupons || [])
  const totalPrice = computed(() => {
    if (quoteResult.value) {
      return quoteResult.value.total_amount || '0.00'
    }

    return (Number(selectedPricingEntry.value?.total_amount || 0) * quantity.value).toFixed(2)
  })
  const selectedCycleLabel = computed(() => selectedPricingEntry.value?.label || '')
  const resolvedStock = computed(() => {
    if (productStock.value !== null && productStock.value !== undefined) {
      return Number(productStock.value)
    }

    return null
  })
  const stockClass = computed(() => {
    if (productStockLoading.value || productStockError.value) return 'sync'
    const stock = resolvedStock.value
    if (stock === null) return 'sync'
    if (stock === -1 || stock > 10) return 'ok'
    if (stock > 0) return 'warn'
    return 'empty'
  })
  const stockLabel = computed(() => {
    if (productStockLoading.value) return '库存同步中'
    if (productStockError.value) return '库存同步失败'
    const stock = resolvedStock.value
    if (stock === null) return '库存同步中'
    if (stock === -1 || stock > 10) return '库存充足'
    if (stock > 0) return '库存紧张'
    return '暂无库存'
  })
  const stockHint = computed(() => {
    if (productStockLoading.value) return '正在同步实时库存，请稍候。'
    if (productStockError.value) return '实时库存同步失败，请稍后重试。'
    const stock = resolvedStock.value
    if (stock === null) return '正在同步实时库存，请稍候。'
    if (stock === -1 || stock > 10) return '当前库存充足，可直接提交账单。'
    if (stock > 0) return `剩余 ${stock} 台，请尽快下单。`
    return '当前库存不足，请联系客服。'
  })
  const canSubmit = computed(() => {
    const stock = resolvedStock.value
    return Boolean(selectedCycle.value && selectedProduct.value && quoteToken.value)
      && !productStockLoading.value
      && !productStockError.value
      && stock !== null
      && stock !== 0
  })
  const purchaseRequirementList = computed(() => resolvePurchaseRequirementList(selectedProduct.value))
  const purchaseRequirementSummary = computed(() => resolvePurchaseRequirementSummary(selectedProduct.value))

  function resetQuoteState() {
    quoteResult.value = null
    quoteToken.value = ''
  }

  function suspendQuoteWatch() {
    quoteWatchSuspendCount += 1
  }

  function resumeQuoteWatch(triggerQuote = false) {
    quoteWatchSuspendCount = Math.max(quoteWatchSuspendCount - 1, 0)

    if (quoteWatchSuspendCount === 0 && triggerQuote) {
      fetchQuote()
    }
  }

  function applyQuoteResult(payload, nextCouponId = selectedCouponId.value) {
    quoteResult.value = payload || null
    quoteToken.value = String(payload?.quote_token || '')
    selectedCouponId.value = Number(nextCouponId || payload?.user_coupon_id || 0)
  }

  function resetSelection() {
    currentProductId = 0
    detailAbortController?.abort()
    stockAbortController?.abort()
    quoteAbortController?.abort()
    productDetail.value = null
    resetConfigForm()
    selectedCycle.value = ''
    quantity.value = 1
    resetQuoteState()
    productStock.value = null
    productStockLoading.value = false
    productStockError.value = ''
  }

  function buildOrderPayload() {
    const payload = {
      product_id: Number(selectedProduct.value?.id || 0),
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

  async function redirectToLoginForCheckout(orderPayload, idempotencyKey) {
    savePendingWebsiteCheckout({
      source: 'website-products',
      createdAt: Date.now(),
      idempotencyKey,
      orderPayload,
    })
    ElMessage.success('请先登录，登录后将继续创建账单')
    await router.push({
      path: '/client/login',
      query: { redirect: '/client/checkout-resume' },
    })
  }

  async function ensureClientCanCreateOrder(orderPayload, idempotencyKey) {
    if (!getToken()) {
      await redirectToLoginForCheckout(orderPayload, idempotencyKey)
      return false
    }

    let userInfo = userStore.info
    if (!userInfo || userStore.userType !== 'client') {
      try {
        await userStore.fetchUserInfo('client')
        userInfo = userStore.info
      } catch {
        await redirectToLoginForCheckout(orderPayload, idempotencyKey)
        return false
      }
    }

    const missingRequirements = resolveMissingPurchaseRequirements(selectedProduct.value, userInfo)
    if (missingRequirements.length === 0) {
      return true
    }

    const nextRequirement = missingRequirements[0]
    const requirementNames = missingRequirements.map((item) => item.label).join('、')
    ElMessage.warning(
      missingRequirements.length > 1
        ? `该商品购买前需先完成${requirementNames}。`
        : nextRequirement.unmetMessage
    )
    await router.push(nextRequirement.route)
    return false
  }

  async function requestQuote(nextCouponId = selectedCouponId.value) {
    return siteApi.productQuote(selectedProduct.value.id, {
      billing_cycle: selectedCycle.value,
      config: buildConfigPayload(),
      quantity: quantity.value,
      user_coupon_id: Number(nextCouponId || 0) || undefined,
    }, {
      signal: quoteAbortController?.signal,
    })
  }

  function looksLikeCouponError(error) {
    const message = String(error?.response?.data?.message || error?.message || '')
    return message.includes('优惠券') || message.includes('优惠码')
  }

  async function executeQuote(nextCouponId = selectedCouponId.value, options = {}) {
    if (!selectedProduct.value || !selectedCycle.value) {
      resetQuoteState()
      return false
    }

    const snapshot = options.rollbackOnError
      ? {
          quoteResult: quoteResult.value,
          quoteToken: quoteToken.value,
          selectedCouponId: selectedCouponId.value,
        }
      : null

    const token = ++quoteTokenId
    quoteAbortController?.abort()
    quoteAbortController = new AbortController()
    quoteLoading.value = true

    try {
      const res = await requestQuote(nextCouponId)
      if (token !== quoteTokenId || currentProductId !== Number(selectedProduct.value?.id || 0)) {
        return false
      }

      applyQuoteResult(res.data || null, nextCouponId)
      return true
    } catch (error) {
      if (error?.code === 'ERR_CANCELED' || error?.name === 'CanceledError') {
        return false
      }

      if (snapshot) {
        quoteResult.value = snapshot.quoteResult
        quoteToken.value = snapshot.quoteToken
        selectedCouponId.value = snapshot.selectedCouponId
        return false
      }

      if (Number(nextCouponId || 0) > 0 && options.fallbackInvalidCoupon && looksLikeCouponError(error)) {
        selectedCouponId.value = 0
        clearPendingWebsiteCoupon()

        try {
          const fallbackRes = await requestQuote(0)
          applyQuoteResult(fallbackRes.data || null, 0)
          return false
        } catch {
          resetQuoteState()
          return false
        }
      }

      resetQuoteState()
      return false
    } finally {
      if (token === quoteTokenId) {
        quoteLoading.value = false
      }
    }
  }

  function handleCouponChange(value) {
    if (!selectedProduct.value || !selectedCycle.value) {
      selectedCouponId.value = Number(value || 0)
      return
    }

    selectedCouponId.value = Number(value || 0)
    fetchQuote()
  }

  async function clearCoupon() {
    selectedCouponId.value = 0
    clearPendingWebsiteCoupon()
    await executeQuote(0, { fallbackInvalidCoupon: false })
  }

  function fetchQuote() {
    if (quoteWatchSuspendCount > 0) {
      return
    }

    clearTimeout(quoteTimer)
    quoteTimer = setTimeout(() => {
      executeQuote(selectedCouponId.value, { fallbackInvalidCoupon: true })
    }, 250)
  }

  async function submitOrder() {
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
      ElMessage.warning('报价凭证已失效，请稍后重试')
      return
    }

    if (!canSubmit.value) {
      ElMessage.warning('请选择计费周期')
      return
    }

    if (quoteLoading.value) {
      ElMessage.warning('价格计算中，请稍候')
      return
    }

    submitting.value = true

    try {
      const orderPayload = buildOrderPayload()
      const idempotencyKey = buildIdempotencyKey('website-order')

      if (!await ensureClientCanCreateOrder(orderPayload, idempotencyKey)) {
        return
      }

      const res = await clientApi.createInvoice(orderPayload, {
        headers: {
          'X-Idempotency-Key': idempotencyKey,
        },
      })
      const invoiceId = Number(res.data?.id || 0)
      ElMessage.success('账单创建成功，正在跳转支付')
      await router.push(invoiceId > 0 ? `/client/invoices/${invoiceId}` : '/client/invoices')
    } catch (error) {
      ElMessage.error(error?.response?.data?.message || '下单失败，请重试')
    } finally {
      submitting.value = false
    }
  }

  async function loadProductDetail(id) {
    if (!id) {
      return false
    }

    const token = ++detailToken
    detailAbortController?.abort()
    detailAbortController = new AbortController()
    const cached = productDetailCache[id]
    if (cached) {
      if (token !== detailToken || currentProductId !== id) {
        return false
      }

      suspendQuoteWatch()
      try {
        productDetail.value = cached
        initProductDefaults({
          selectedCycleRef: selectedCycle,
          quantityRef: quantity,
          resetQuoteState,
        })
      } finally {
        resumeQuoteWatch(true)
      }

      return true
    }

    configLoading.value = true
    try {
      const res = await siteApi.product(id, {
        signal: detailAbortController.signal,
      })
      if (token !== detailToken || currentProductId !== id) {
        return false
      }

      const product = res.data.product || null
      productDetail.value = product

      if (product) {
        productDetailCache[id] = product
        suspendQuoteWatch()
        try {
          initProductDefaults({
            selectedCycleRef: selectedCycle,
            quantityRef: quantity,
            resetQuoteState,
          })
        } finally {
          resumeQuoteWatch(true)
        }

        return true
      }

      return false
    } catch (error) {
      if (error?.code === 'ERR_CANCELED' || error?.name === 'CanceledError') {
        return false
      }

      return false
    } finally {
      if (token === detailToken && currentProductId === id) {
        configLoading.value = false
      }
    }
  }

  function loadSelectedProduct(id, options = {}) {
    currentProductId = Number(id || 0)

    if (options.refreshStockOnly) {
      refreshProductStock(currentProductId)
      return
    }

    detailAbortController?.abort()
    stockAbortController?.abort()
    quoteAbortController?.abort()
    productDetail.value = null
    resetQuoteState()
    productStock.value = null
    productStockLoading.value = false
    productStockError.value = ''
    void loadProductDetail(currentProductId).finally(() => {
      if (currentProductId === Number(id || 0)) {
        refreshProductStock(currentProductId)
      }
    })
  }

  async function refreshProductStock(id) {
    if (!id) {
      return
    }

    const token = ++stockToken
    stockAbortController?.abort()
    stockAbortController = new AbortController()
    productStockLoading.value = true
    productStockError.value = ''
    productStock.value = null

    try {
      const res = await siteApi.productStock(id, {
        signal: stockAbortController.signal,
      })
      if (token !== stockToken || currentProductId !== id) {
        return
      }

      productStock.value = Number(res.data?.stock ?? 0)
    } catch (error) {
      if (error?.code === 'ERR_CANCELED' || error?.name === 'CanceledError') {
        return
      }

      if (token !== stockToken || currentProductId !== id) {
        return
      }

      productStockError.value = error?.response?.data?.message || '库存同步失败'
    } finally {
      if (token === stockToken && currentProductId === id) {
        productStockLoading.value = false
      }
    }
  }

  watch(selectedCycle, fetchQuote)
  watch(configForm, fetchQuote, { deep: true })
  watch(quantity, fetchQuote)
  onBeforeUnmount(() => {
    clearTimeout(quoteTimer)
    detailAbortController?.abort()
    stockAbortController?.abort()
    quoteAbortController?.abort()
    clearPendingWebsiteCoupon()
  })

  return {
    configLoading,
    submitting,
    selectedProduct,
    selectedCycle,
    quantity,
    quoteLoading,
    productStockLoading,
    productStockError,
    selectedProductDisplayName,
    baseAmount,
    setupFee,
    quoteItems,
    appliedCoupon,
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
    clearCoupon,
    handleSubmit: submitOrder,
    loadSelectedProduct,
    resetSelection,
  }
}
