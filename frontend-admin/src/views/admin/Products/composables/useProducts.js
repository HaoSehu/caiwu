/**
 * useProducts — 商品状态 + API
 */
import { computed, reactive, ref } from 'vue'
import { ElMessage } from 'element-plus'
import productApi from '@/api/product'
import supplierApi from '@/api/supplier'
import {
  buildConfigPricingPayload,
  buildDerivedNumericPricingFromMonthly,
  compactDateTime,
  createConfigOptionRecordFromSource,
  createDefaultConfigOptionForm as buildDefaultConfigOptionForm,
  createDefaultPricing,
  createDefaultProductForm as buildDefaultProductForm,
  createEmptySubItemPricing,
  formatSupplierOptionLabel,
  interfaceTypeLabel,
  nextConfigOptionUid,
  normalizeConfigOptions,
  normalizeConfigPricingFromSource,
  normalizeProviderSource,
  parseSupplierAmount,
  resolveConfigOptionMode,
  resolveHostingPanelOptionSpec,
  resolveMonthlyAmountFromPricing,
  sanitizePricing,
  serializeConfigOptions as serializeConfigOptionList,
  syncConfigPricingFieldsFromMonthly,
} from '../catalogUtils'
import { billingCycleLabel } from '../catalogUtils'

export function useProducts({ filters, productTypes }) {
  // ── loading ───────────────────────────────────────────────────────────────
  const productLoading = ref(false)
  const productDialogLoading = ref(false)
  const supplierLoading = ref(false)
  const supplierProductsLoading = ref(false)
  const supplierProductsSyncing = ref(false)
  const productSubmitting = ref(false)
  const ownersLoading = ref(false)

  // ── data ──────────────────────────────────────────────────────────────────
  const products = ref([])
  const suppliers = ref([])
  const supplierProductGroups = ref([])
  const total = ref(0)
  const page = ref(1)
  const pageSize = ref(20)

  // ── drag ──────────────────────────────────────────────────────────────────
  const draggingProductId = ref(0)
  const productDropTargetId = ref(0)
  const productDropPosition = ref('')
  const productDropGroupId = ref(0)

  // ── dialog state ──────────────────────────────────────────────────────────
  const productDialogVisible = ref(false)
  const configOptionDialogVisible = ref(false)
  const ownersDrawerVisible = ref(false)
  const editingProduct = ref(null)
  const productFormRef = ref()
  const productDrawerTab = ref('details')
  const editingConfigOptionIndex = ref(-1)

  // ── owners ────────────────────────────────────────────────────────────────
  const ownersProduct = ref(null)
  const ownersList = ref([])
  const ownersSummary = ref(null)
  const ownersTotal = ref(0)
  const ownersPage = ref(1)
  const ownersPageSize = ref(20)
  const ownersKeyword = ref('')

  // ── sub-item add row ─────────────────────────────────────────────────────
  const newSubItemLabel = ref('')
  const newSubItemSort = ref(0)
  const newSubItemHidden = ref(false)

  const productDrawerTabs = [
    { key: 'details', label: '详情' },
    { key: 'pricing', label: '定价' },
    { key: 'automation', label: '自动开通' },
    { key: 'config', label: '商品配置' },
  ]

  const productRules = {
    category_id: [{ required: true, message: '请选择商品分类或子菜单', trigger: 'change' }],
    name: [{ required: true, message: '请输入配置名称', trigger: 'blur' }],
    product_type: [{ required: true, message: '请选择商品类型', trigger: 'change' }],
  }

  // ── forms ─────────────────────────────────────────────────────────────────
  function resolveDefaultProductType(fallback = '') {
    return filters.product_type || productTypes.value[0]?.value || fallback
  }
  function createDefaultProductForm() {
    return buildDefaultProductForm(resolveDefaultProductType('other'))
  }
  function createDefaultConfigOptionForm() {
    return buildDefaultConfigOptionForm()
  }

  const productForm = reactive(createDefaultProductForm())
  const configOptionForm = reactive(createDefaultConfigOptionForm())

  // ── computed ─────────────────────────────────────────────────────────────
  const selectedSupplier = computed(
    () => suppliers.value.find((item) => item.id === productForm.supplier_id) || null,
  )
  const supplierProductItems = computed(
    () => supplierProductGroups.value.flatMap((group) => group.items || []),
  )
  const selectedSupplierProduct = computed(
    () =>
      supplierProductItems.value.find(
        (item) => item.id === Number(productForm.supplier_product_id || 0),
      ) || null,
  )
  const canPullConfigOptions = computed(
    () => Boolean(productForm.supplier_id && productForm.supplier_product_id),
  )
  const supplierProductCascaderProps = {
    value: 'value',
    label: 'label',
    children: 'children',
    emitPath: false,
    expandTrigger: 'click',
  }
  const supplierProductCascaderOptions = computed(() => {
    const groups = supplierProductGroups.value.slice()
    if (
      productForm.supplier_product_id &&
      !groups.some((group) =>
        group.items?.some((item) => item.id === productForm.supplier_product_id),
      )
    ) {
      groups.unshift({
        key: 'saved-product',
        label: '已绑定上游产品',
        items: [{ id: productForm.supplier_product_id, name: `上游产品 #${productForm.supplier_product_id}`, type_label: '已保存' }],
      })
    }
    return groups
      .filter((group) => Array.isArray(group.items) && group.items.length)
      .map((group) => ({
        value: group.key,
        label: group.label,
        children: group.items.map((item) => ({
          value: Number(item.id),
          label: item.name,
          type_label: item.type_label || '',
          leaf: true,
        })),
      }))
  })
  const activeConfigOptionSpec = computed(() =>
    resolveHostingPanelOptionSpec(configOptionForm.spec_key || configOptionForm.field),
  )

  // ── helpers ───────────────────────────────────────────────────────────────
  function typeTagType(type) {
    if (type === 'vps') return 'primary'
    if (type === 'dedicated') return 'warning'
    if (type === 'hosting') return 'success'
    if (type === 'domain') return ''
    return 'info'
  }

  function resolveSupplierProvisionModule(supplierId = productForm.supplier_id) {
    const id = Number(supplierId || 0)
    if (id <= 0) return ''
    return suppliers.value.find((item) => item.id === id)?.interface_type || ''
  }

  function syncProvisionModuleWithSupplier(supplierId = productForm.supplier_id) {
    productForm.provision_module = resolveSupplierProvisionModule(supplierId)
  }

  function applySupplierProductPricing(supplierProduct) {
    if (!supplierProduct || typeof supplierProduct !== 'object') return false
    const monthlyPrice = parseSupplierAmount(supplierProduct.monthly_price ?? supplierProduct.product_price)
    const setupFee = parseSupplierAmount(supplierProduct.setup_fee)
    let synced = false
    if (monthlyPrice !== null && monthlyPrice > 0) {
      Object.assign(productForm.pricing, buildDerivedNumericPricingFromMonthly(monthlyPrice))
      synced = true
    }
    if (setupFee !== null && setupFee >= 0) { productForm.setup_fee = setupFee; synced = true }
    return synced
  }

  function applyProductForm(product = null) {
    Object.assign(productForm, createDefaultProductForm(), {
      category_id: product?.category_id ?? null,
      product_type: product?.product_type ?? (filters.product_type || productTypes.value[0]?.value || 'other'),
      pricing: sanitizePricing(product?.pricing),
      setup_fee: Number(product?.setup_fee ?? 0),
      stock: Number(product?.stock ?? -1),
      status: Number(product?.status ?? 1),
      sort_order: Number(product?.sort_order ?? 0),
      provision_module: product?.provision_module ?? '',
      auto_setup: Number(product?.auto_setup ?? 0),
      supplier_id: product?.supplier_id ?? null,
      supplier_product_id: product?.supplier_product_id ?? null,
      config_options: normalizeConfigOptions(product?.config_options),
    })
    syncProvisionModuleWithSupplier(product?.supplier_id ?? null)
  }

  function normalizePricing(pricing) {
    const filteredPricing = sanitizePricing(pricing)
    const monthlyAmount = resolveMonthlyAmountFromPricing(filteredPricing)
    if (monthlyAmount === null || monthlyAmount <= 0) return {}
    return buildDerivedNumericPricingFromMonthly(monthlyAmount)
  }

  function serializeConfigOptions() {
    return serializeConfigOptionList(productForm.config_options)
  }

  function resolveSelectedCategoryId() {
    return Number(productForm.category_id || 0) || null
  }

  // ── API ───────────────────────────────────────────────────────────────────
  async function loadProducts() {
    if (!filters.product_type) { products.value = []; total.value = 0; return }
    productLoading.value = true
    try {
      const res = await productApi.list({
        ...filters,
        product_type: filters.product_type || undefined,
        category_id: filters.category_id || undefined,
        page: page.value,
        page_size: pageSize.value,
      })
      products.value = res.data.list || []
      total.value = res.data.total || 0
    } finally {
      productLoading.value = false
    }
  }

  async function loadSupplierOptions() {
    supplierLoading.value = true
    try {
      const res = await supplierApi.list({ status: 1, page: 1, page_size: 100 })
      suppliers.value = (res.data.list || []).map((item) => ({
        id: Number(item.id), name: item.name, interface_type: item.interface_type, api_url: item.api_url,
      }))
      if (productForm.supplier_id && !suppliers.value.some((item) => item.id === productForm.supplier_id)) {
        productForm.supplier_id = null
        productForm.supplier_product_id = null
        productForm.provision_module = ''
        supplierProductGroups.value = []
      } else if (productForm.supplier_id) {
        syncProvisionModuleWithSupplier(productForm.supplier_id)
      }
    } finally {
      supplierLoading.value = false
    }
  }

  async function loadSupplierProducts(supplierId, options = {}) {
    const id = Number(supplierId || 0)
    if (id <= 0) { supplierProductGroups.value = []; return }
    const loadingRef = options.syncing ? supplierProductsSyncing : supplierProductsLoading
    loadingRef.value = true
    try {
      const res = await supplierApi.products(id, { silent: true })
      supplierProductGroups.value = (res.data.groups || []).map((group) => ({
        key: group.key, label: group.label,
        items: (group.items || []).map((item) => ({
          id: Number(item.id), name: item.name, type: item.type || '',
          type_label: item.type_label || item.type || '', description: item.description || '',
          group_label: item.group_label || group.label, billingcycle: item.billingcycle || '',
          product_price: item.product_price || null, monthly_price: item.monthly_price || null,
          setup_fee: item.setup_fee ?? null,
        })),
      }))
      if (options.message) ElMessage.success('供应商商品已同步')
    } catch {
      supplierProductGroups.value = []
      if (!options.silent) ElMessage.error('供应商商品同步失败')
    } finally {
      loadingRef.value = false
    }
  }

  async function pullConfigOptionsFromSupplierProduct(options = {}) {
    const supplierId = Number(productForm.supplier_id || 0)
    const supplierProductId = Number(productForm.supplier_product_id || 0)
    if (supplierId <= 0) { if (!options.silent) ElMessage.warning('请先选择供应商'); return false }
    if (supplierProductId <= 0) { if (!options.silent) ElMessage.warning('请先选择供应商商品'); return false }
    try {
      const res = await supplierApi.productConfigTemplate(supplierId, supplierProductId, { silent: true })
      const configOptions = normalizeConfigOptions(res.data.config_options || [])
      productForm.config_options = configOptions
      if (!options.silent && configOptions.length > 0) {
        const autoFilledFields = Array.isArray(res.data.auto_filled_fields) ? res.data.auto_filled_fields : []
        const autoFilledText = autoFilledFields.length ? `，已自动带出 ${autoFilledFields.join('、')}` : ''
        ElMessage.success(`已从接口拉取 ${configOptions.length} 项配置${autoFilledText}，保存商品后生效`)
      }
      if (!options.silent && configOptions.length === 0) ElMessage.warning('接口未返回可保存的商品配置项')
      return true
    } catch (error) {
      if (!options.silent) ElMessage.error(error?.message || '商品配置拉取失败')
      return false
    }
  }

  async function syncSelectedSupplierProductData(options = {}) {
    const matchedProduct = selectedSupplierProduct.value
    if (!matchedProduct) return false
    const pricingSynced = applySupplierProductPricing(matchedProduct)
    const configSynced = await pullConfigOptionsFromSupplierProduct({ silent: true })
    if (!configSynced && options.warnOnConfigFailure) {
      ElMessage.warning('当前商品未能自动拉取配置项，可稍后点击"从接口拉取"重试')
    }
    if (!options.silent) {
      const syncedParts = []
      if (pricingSynced) syncedParts.push('价格')
      if (configSynced) syncedParts.push('配置')
      if (syncedParts.length > 0) ElMessage.success(`已自动同步上游${syncedParts.join('和')}`)
    }
    return pricingSynced || configSynced
  }

  function fillPricingFromMonthly(options = {}) {
    const monthlyPrice = parseSupplierAmount(productForm.pricing.monthly)
    if (monthlyPrice === null || (!options.allowZero && monthlyPrice <= 0)) {
      Object.assign(productForm.pricing, createDefaultPricing())
      if (!options.silent) ElMessage.warning('请先填写月付价格')
      return
    }
    Object.assign(productForm.pricing, buildDerivedNumericPricingFromMonthly(monthlyPrice))
    if (!options.silent) ElMessage.success('已按月付补齐其他周期价格')
  }

  async function handleSupplierChange(value) {
    const id = Number(value || 0)
    productForm.supplier_id = id > 0 ? id : null
    productForm.supplier_product_id = null
    productForm.config_options = []
    syncProvisionModuleWithSupplier(productForm.supplier_id)
    supplierProductGroups.value = []
    if (productForm.supplier_id) await loadSupplierProducts(productForm.supplier_id, { silent: true })
  }

  async function handleSupplierProductChange(value) {
    const id = Number(value || 0)
    productForm.supplier_product_id = id > 0 ? id : null
    if (!productForm.supplier_product_id) {
      productForm.config_options = []
      return
    }
    await syncSelectedSupplierProductData({ silent: true, warnOnConfigFailure: true })
  }

  async function syncSupplierProducts() {
    if (!productForm.supplier_id) { ElMessage.warning('请先选择供应商'); return }
    await loadSupplierProducts(productForm.supplier_id, { syncing: true, silent: true })
    if (productForm.supplier_product_id) await syncSelectedSupplierProductData({ silent: true })
    ElMessage.success('供应商商品已同步')
  }

  async function openProductDialog(product = null) {
    editingProduct.value = product
    productDrawerTab.value = 'details'
    supplierProductGroups.value = []
    editingConfigOptionIndex.value = -1
    configOptionDialogVisible.value = false
    productDialogVisible.value = true
    productDialogLoading.value = false
    applyProductForm(null)
    if (!product?.id) { editingProduct.value = null; return }
    productDialogLoading.value = true
    try {
      const res = await productApi.detail(product.id)
      const detailProduct = res.data || null
      if (!detailProduct) throw new Error('商品详情为空')
      editingProduct.value = detailProduct
      applyProductForm(detailProduct)
      if (detailProduct.supplier_id) {
        await loadSupplierProducts(detailProduct.supplier_id, { silent: true })
        if (productForm.supplier_product_id) await syncSelectedSupplierProductData({ silent: true })
      }
    } catch {
      productDialogVisible.value = false
    } finally {
      productDialogLoading.value = false
    }
  }

  async function handleSubmitProduct() {
    try { await productFormRef.value?.validate() } catch { return }
    const pricing = normalizePricing(productForm.pricing)
    if (Object.keys(pricing).length === 0) { ElMessage.error('请填写大于 0 的月付价格'); return }
    let configOptions = []
    try { configOptions = serializeConfigOptions() } catch (error) { ElMessage.error(error.message); return }
    productSubmitting.value = true
    try {
      const resolvedCategoryId = resolveSelectedCategoryId()
      const payload = {
        category_id: resolvedCategoryId,
        product_type: productForm.product_type,
        name: productForm.name, pricing,
        setup_fee: productForm.setup_fee, config_options: configOptions,
        stock: productForm.stock, status: productForm.status, sort_order: productForm.sort_order,
        provision_module: productForm.provision_module, auto_setup: productForm.auto_setup,
        supplier_id: productForm.supplier_id, supplier_product_id: productForm.supplier_product_id,
      }
      if (editingProduct.value) {
        await productApi.update(editingProduct.value.id, payload); ElMessage.success('商品已更新')
      } else {
        await productApi.create(payload); ElMessage.success('商品已创建')
      }
      productDialogVisible.value = false
    } finally {
      productSubmitting.value = false
    }
  }

  async function handleToggleProductStatus(product) {
    await productApi.toggleStatus(product.id)
    ElMessage.success(`商品已${product.status === 1 ? '下架' : '上架'}`)
  }

  async function handleDeleteProduct(product) {
    await productApi.delete(product.id)
    ElMessage.success('商品已删除')
    if (products.value.length === 1 && page.value > 1) page.value -= 1
  }

  async function handleProductAction(command, product) {
    if (command === 'owners') { await openOwnersDrawer(product); return }
    if (command === 'enable' || command === 'disable') { await handleToggleProductStatus(product); return }
    if (command === 'delete') await handleDeleteProduct(product)
  }

  // ── drag ─────────────────────────────────────────────────────────────────
  function resetProductDragState() {
    draggingProductId.value = 0; productDropTargetId.value = 0
    productDropPosition.value = ''; productDropGroupId.value = 0
  }

  function resolveProductDropPosition(event) {
    const rect = event.currentTarget.getBoundingClientRect()
    return event.clientY - rect.top < rect.height / 2 ? 'before' : 'after'
  }

  function handleProductDragStart(row, event) {
    draggingProductId.value = Number(row.id)
    productDropTargetId.value = 0; productDropPosition.value = ''; productDropGroupId.value = 0
    event.dataTransfer.effectAllowed = 'move'
    event.dataTransfer.setData('text/plain', String(row.id))
  }

  function handleProductRowDragOver(row, event) {
    if (!draggingProductId.value || Number(row.id) === Number(draggingProductId.value)) return
    productDropTargetId.value = Number(row.id)
    const target = event.target.closest('.el-tree-node__content, .product-drop-zone, .drag-handle')
    if (!target) {
      productDropPosition.value = 'after'
    } else {
      const rect = target.getBoundingClientRect()
      productDropPosition.value = (event.clientY - rect.top) / Math.max(rect.height, 1) <= 0.5 ? 'before' : 'after'
    }
    productDropGroupId.value = 0
    event.dataTransfer.dropEffect = 'move'
  }

  function productDropZoneClass(row) {
    const isCurrentTarget = Number(productDropTargetId.value) === Number(row?.id)
    const isSrcRow = Number(draggingProductId.value) === Number(row?.id)
    return {
      'product-drop-zone': true,
      'is-drop-before': isCurrentTarget && productDropPosition.value === 'before',
      'is-drop-after': isCurrentTarget && productDropPosition.value === 'after',
      'is-dragging-src': isSrcRow && draggingProductId.value !== 0,
    }
  }

  async function moveProductByDrag(payload) {
    productLoading.value = true
    try {
      await productApi.reorderProduct(payload)
      ElMessage.success('商品位置已更新')
    } finally {
      productLoading.value = false; resetProductDragState()
    }
  }

  async function handleProductRowDrop(row, event) {
    if (!draggingProductId.value || Number(row.id) === Number(draggingProductId.value)) {
      resetProductDragState(); return
    }
    const position = productDropPosition.value || resolveProductDropPosition(event)
    await moveProductByDrag({
      product_id: Number(draggingProductId.value),
      target_category_id: Number(row.category_id || 0) || undefined,
      reference_product_id: Number(row.id), position,
    })
  }

  function canAssignProductToGroup(group) {
    if (!group) return false
    return Number(group.level || 0) > 1 || Number(group.children_count || 0) === 0
  }

  function handleProductTreeDragOver(group, event) {
    if (!draggingProductId.value || !canAssignProductToGroup(group)) return
    productDropTargetId.value = 0; productDropPosition.value = ''
    productDropGroupId.value = Number(group.id)
    event.dataTransfer.dropEffect = 'move'
  }

  async function handleProductTreeDrop(group) {
    if (!draggingProductId.value) return
    if (!canAssignProductToGroup(group)) {
      ElMessage.warning('请将商品拖到最终可售菜单下'); resetProductDragState(); return
    }
    await moveProductByDrag({
      product_id: Number(draggingProductId.value),
      target_category_id: Number(group.category_id || 0) || undefined,
      position: 'append',
    })
  }

  function handleProductDragEnd() { resetProductDragState() }

  function groupTreeNodeMainClassWithProduct(data, groupDropTargetId, groupDropPosition) {
    const isGroupCurrentTarget = Number(groupDropTargetId) === Number(data.id)
    const isProductCurrentTarget = Number(productDropGroupId.value) === Number(data.id) && canAssignProductToGroup(data)
    return {
      'is-group-drop-inner': isGroupCurrentTarget && groupDropPosition === 'inner',
      'is-group-drop-before': isGroupCurrentTarget && groupDropPosition === 'before',
      'is-group-drop-after': isGroupCurrentTarget && groupDropPosition === 'after',
      'is-product-drop-target': isProductCurrentTarget,
    }
  }

  // ── config option dialog ──────────────────────────────────────────────────
  function openConfigOptionDialog(option = null, index = -1) {
    editingConfigOptionIndex.value = index
    const defaults = createDefaultConfigOptionForm()
    if (!option) {
      Object.assign(configOptionForm, defaults, { sort_order: productForm.config_options.length + 1 })
      newSubItemLabel.value = ''; newSubItemSort.value = 0; newSubItemHidden.value = false
      configOptionDialogVisible.value = true; return
    }
    const base = createConfigOptionRecordFromSource(option, index)
    const raw = option
    const mode = resolveConfigOptionMode(raw)

    const subItems = []
    if (Array.isArray(raw.sub_items) && raw.sub_items.length > 0) {
      for (const s of raw.sub_items) {
        const p = normalizeConfigPricingFromSource(s.pricing || {})
        subItems.push({ label: String(s.label ?? ''), value: String(s.value ?? s.label ?? ''), pricing: p, sort_order: Number(s.sort_order ?? 0), hidden: Boolean(s.hidden), raw_id: s.raw_id ?? s.id ?? '' })
      }
    } else if (Array.isArray(raw.sub) && raw.sub.length > 0 && mode !== 'range') {
      for (const s of raw.sub) {
        const p = normalizeConfigPricingFromSource(s.pricing || {})
        subItems.push({ label: String(s.option_name ?? s.version ?? s.label ?? s.option_name_first ?? s.id ?? ''), value: String(s.option_name_first ?? s.value ?? s.id ?? s.option_name ?? ''), pricing: p, sort_order: Number(s.sort_order ?? 0), hidden: Boolean(s.hidden), raw_id: s.id ?? '' })
      }
    } else if (typeof raw.parameter === 'string' && raw.parameter.trim() && mode !== 'range') {
      const segments = raw.parameter.trim().split(',').map((s) => s.trim()).filter(Boolean)
      for (const seg of segments) {
        const pipeIdx = seg.indexOf('|')
        if (pipeIdx > 0) {
          subItems.push({ label: seg.slice(pipeIdx + 1).trim() || seg.slice(0, pipeIdx).trim(), value: seg.slice(0, pipeIdx).trim(), pricing: createEmptySubItemPricing(), sort_order: 0, hidden: false, raw_id: '' })
        } else {
          subItems.push({ label: seg, value: seg, pricing: createEmptySubItemPricing(), sort_order: 0, hidden: false, raw_id: '' })
        }
      }
    }

    const rangePricing = []
    if (Array.isArray(raw.range_pricing) && raw.range_pricing.length > 0) {
      for (const r of raw.range_pricing) {
        const p = normalizeConfigPricingFromSource(r.pricing || {})
        rangePricing.push({ qty_minimum: Number(r.qty_minimum ?? 0), qty_maximum: Number(r.qty_maximum ?? 0), pricing: p })
      }
    } else if (Array.isArray(raw.sub) && raw.sub.length > 0 && mode === 'range') {
      for (const s of raw.sub) {
        const p = normalizeConfigPricingFromSource(s.pricing || {})
        rangePricing.push({ qty_minimum: Number(s.qty_minimum ?? 0), qty_maximum: Number(s.qty_maximum ?? 0), pricing: p })
      }
    }

    Object.assign(configOptionForm, defaults, base, {
      option_mode: mode,
      show_advanced: Boolean(raw.show_advanced || raw.description || raw.suffix_text),
      suffix_text: String(raw.suffix_text ?? ''), sub_items: subItems,
      qty_minimum: Number(raw.qty_minimum ?? 0), qty_maximum: Number(raw.qty_maximum ?? 100), qty_step: Number(raw.qty_step ?? 1),
      range_pricing: rangePricing,
    })
    newSubItemLabel.value = ''; newSubItemSort.value = 0; newSubItemHidden.value = false
    configOptionDialogVisible.value = true
  }

  function saveConfigOption() {
    const field = String(configOptionForm.field || '').trim()
    const name = String(configOptionForm.name || '').trim()
    if (!field) { ElMessage.error('请输入配置项标识'); return }
    if (!name) { ElMessage.error('请输入配置项名称'); return }
    const mode = configOptionForm.option_mode
    const isRange = mode === 'range'
    if (isRange && configOptionForm.range_pricing.length === 0) { ElMessage.error('范围型配置项至少需要一个价格区间'); return }
    if (!isRange && configOptionForm.sub_items.length === 0 && !String(configOptionForm.parameter || '').trim()) {
      ElMessage.error('单选型配置项请添加至少一个子项，或填写参数字段'); return
    }
    const normalizedField = field.toLowerCase()
    const hasDuplicate = productForm.config_options.some(
      (item, index) => index !== editingConfigOptionIndex.value && String(item.field || '').trim().toLowerCase() === normalizedField,
    )
    if (hasDuplicate) { ElMessage.error('配置项标识重复，同一个配置项只需维护一次'); return }

    let sub = []
    if (isRange) {
      sub = configOptionForm.range_pricing.map((r, ri) => ({ id: ri, qty_minimum: Number(r.qty_minimum ?? 0), qty_maximum: Number(r.qty_maximum ?? 0), pricing: buildConfigPricingPayload(r.pricing), hidden: 0 }))
    } else {
      sub = configOptionForm.sub_items.map((s, si) => ({ id: String(s.raw_id || s.value || s.label || si), option_name: String(s.label || ''), option_name_first: String(s.value || s.label || ''), version: String(s.label || ''), pricing: buildConfigPricingPayload(s.pricing), sort_order: Number(s.sort_order ?? si), hidden: s.hidden ? 1 : 0 }))
    }

    let parameter = String(configOptionForm.parameter || '').trim()
    if (!isRange && configOptionForm.sub_items.length > 0) {
      parameter = configOptionForm.sub_items.map((s) => `${s.value}|${s.label}`).join(',')
    }

    const payload = {
      uid: configOptionForm.uid || nextConfigOptionUid(),
      source: normalizeProviderSource(configOptionForm.source),
      spec_key: configOptionForm.spec_key || resolveHostingPanelOptionSpec(field)?.field || '',
      field, name, option_mode: mode, parameter,
      description: String(configOptionForm.description || '').trim(),
      suffix_text: String(configOptionForm.suffix_text || '').trim(),
      required: Boolean(configOptionForm.required),
      default_value: String(configOptionForm.default_value || '').trim(),
      sort_order: Math.max(Number(configOptionForm.sort_order || 0), 0),
      hidden: Boolean(configOptionForm.hidden), allow_upgrade: Boolean(configOptionForm.allow_upgrade), allow_promo_code: Boolean(configOptionForm.allow_promo_code),
      sub,
      ...(isRange ? { qty_minimum: Number(configOptionForm.qty_minimum ?? 0), qty_maximum: Number(configOptionForm.qty_maximum ?? 100), qty_step: Number(configOptionForm.qty_step ?? 1) } : {}),
      ...(!isRange ? { sub_items: [...configOptionForm.sub_items] } : {}),
      range_pricing: isRange ? [...configOptionForm.range_pricing] : [],
      extra: { ...(configOptionForm.extra || {}) },
    }

    if (editingConfigOptionIndex.value >= 0) {
      productForm.config_options.splice(editingConfigOptionIndex.value, 1, payload)
    } else {
      productForm.config_options.push(payload)
    }
    configOptionDialogVisible.value = false
  }

  function removeConfigOption(index) { productForm.config_options.splice(index, 1) }

  function addSubItem() {
    const label = newSubItemLabel.value.trim()
    if (!label) { ElMessage.warning('请输入子项名称'); return }
    configOptionForm.sub_items.push({ label, value: label, pricing: createEmptySubItemPricing(), sort_order: newSubItemSort.value, hidden: newSubItemHidden.value })
    newSubItemLabel.value = ''; newSubItemSort.value = 0; newSubItemHidden.value = false
  }

  function removeSubItem(index) { configOptionForm.sub_items.splice(index, 1) }
  function addRangePricingRow() { configOptionForm.range_pricing.push({ qty_minimum: 0, qty_maximum: 0, pricing: createEmptySubItemPricing() }) }
  function removeRangePricingRow(index) { configOptionForm.range_pricing.splice(index, 1) }
  function onOptionModeChange() { /* 切换类型时清空另一种类型的数据，避免混用 */ }

  // ── owners ────────────────────────────────────────────────────────────────
  async function openOwnersDrawer(product) {
    ownersProduct.value = product; ownersKeyword.value = ''; ownersPage.value = 1
    ownersList.value = []; ownersSummary.value = null; ownersDrawerVisible.value = true
    await loadOwners(1)
  }

  async function loadOwners(pg) {
    if (!ownersProduct.value) return
    if (pg) ownersPage.value = pg
    ownersLoading.value = true
    try {
      const res = await productApi.owners(ownersProduct.value.id, {
        page: ownersPage.value, page_size: ownersPageSize.value, keyword: ownersKeyword.value || undefined,
      })
      ownersList.value = res.data.list || []; ownersSummary.value = res.data.summary || null; ownersTotal.value = res.data.total || 0
    } finally {
      ownersLoading.value = false
    }
  }

  return {
    // state
    productLoading, productDialogLoading, supplierLoading, supplierProductsLoading,
    supplierProductsSyncing, productSubmitting, ownersLoading,
    products, suppliers, supplierProductGroups, total, page, pageSize,
    draggingProductId, productDropTargetId, productDropPosition, productDropGroupId,
    productDialogVisible, configOptionDialogVisible, ownersDrawerVisible,
    editingProduct, productFormRef, productDrawerTab, editingConfigOptionIndex,
    productDrawerTabs, productRules, productForm, configOptionForm,
    newSubItemLabel, newSubItemSort, newSubItemHidden,
    ownersProduct, ownersList, ownersSummary, ownersTotal, ownersPage, ownersPageSize, ownersKeyword,
    // computed
    selectedSupplier, supplierProductItems, selectedSupplierProduct,
    canPullConfigOptions, supplierProductCascaderProps, supplierProductCascaderOptions,
    activeConfigOptionSpec,
    // re-exports
    compactDateTime, interfaceTypeLabel, formatSupplierOptionLabel, syncConfigPricingFieldsFromMonthly,
    billingCycleLabel,
    // methods
    typeTagType, loadProducts, loadSupplierOptions, loadSupplierProducts,
    openProductDialog, handleSubmitProduct, handleToggleProductStatus, handleDeleteProduct, handleProductAction,
    handleSupplierChange, handleSupplierProductChange, syncSupplierProducts,
    fillPricingFromMonthly, pullConfigOptionsFromSupplierProduct,
    openConfigOptionDialog, saveConfigOption, removeConfigOption,
    addSubItem, removeSubItem, addRangePricingRow, removeRangePricingRow, onOptionModeChange,
    productDropZoneClass, handleProductDragStart, handleProductRowDragOver, handleProductRowDrop,
    handleProductTreeDragOver, handleProductTreeDrop, handleProductDragEnd, resetProductDragState,
    canAssignProductToGroup, groupTreeNodeMainClassWithProduct,
    openOwnersDrawer, loadOwners,
  }
}
