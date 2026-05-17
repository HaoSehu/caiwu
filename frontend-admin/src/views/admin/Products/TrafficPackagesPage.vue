<template>
  <div class="traffic-package-page admin-page">
    <section class="admin-page-head">
      <div class="admin-page-heading">
        <span class="admin-page-kicker">产品</span>
        <h2>流量包分组</h2>
        <p>按管理员创建的分组管理流量包档位，每个分组必须绑定当前已有配置。</p>
      </div>
      <div class="page-actions">
        <el-button :loading="loading" @click="loadData">刷新</el-button>
        <el-button type="primary" :loading="saving" :disabled="!selectedGroupId" @click="saveCurrentGroup">
          保存当前分组
        </el-button>
      </div>
    </section>

    <section class="traffic-package-layout">
      <el-card shadow="never" class="group-card" v-loading="categoriesLoading">
        <div class="group-card__toolbar">
          <el-select v-model="selectedProductType" placeholder="商品类型" :loading="typesLoading" @change="handleTypeChange" style="flex:1;min-width:0;">
            <el-option v-for="item in productTypeOptions" :key="item.value" :label="item.label" :value="item.value" />
          </el-select>
          <el-button type="primary" plain @click="openCreateGroupDialog">新增</el-button>
        </div>

        <el-input v-model="groupKeyword" clearable placeholder="搜索分组" size="small" class="group-card__search" />

        <el-empty
          v-if="!filteredGroups.length"
          :description="groupKeyword ? '没有匹配的分组' : '暂无流量包分组，请先新增并绑定配置'"
          :image-size="60"
        >
          <el-button v-if="!groupKeyword" type="primary" plain @click="openCreateGroupDialog">新增分组</el-button>
        </el-empty>
        <div v-else class="group-list">
          <div
            v-for="group in filteredGroups"
            :key="group.id"
            class="group-list-item"
            :class="{ 'is-active': group.id === selectedGroupId }"
            @click="handleGroupSelect(group.id)"
          >
            <div class="group-list-item__body">
              <strong>{{ group.name }}</strong>
              <span class="group-list-item__sub">
                {{ group.category_label || `分类 #${group.category_id}` }}
                · 已绑定 {{ groupPackageStatsMap.get(group.id)?.scopedProductCount || 0 }} 个配置
                · {{ groupPackageStatsMap.get(group.id)?.packageCount || 0 }} 档
              </span>
            </div>
            <div class="group-list-item__actions">
              <el-button link size="small" @click.stop="openEditGroupDialog(group)">编辑</el-button>
              <el-button type="danger" link size="small" @click.stop="removeGroup(group)">删除</el-button>
            </div>
          </div>
        </div>
      </el-card>

      <el-card shadow="never" class="table-card" v-loading="loading">
        <el-empty v-if="!selectedGroupId" description="请先在左侧选择或新建分组" :image-size="72" />
        <template v-else>
          <div class="table-card__toolbar">
            <div class="table-card__info">
              <strong>{{ selectedGroupLabel }}</strong>
              <span>{{ selectedGroupCategoryLabel }} · 已保存 {{ selectedGroupSavedPackageCount }} 档</span>
            </div>
            <div class="table-card__actions">
              <el-button :loading="pulling" @click="openPullDialog">从上游拉取</el-button>
              <el-button type="primary" plain @click="addPackageRow">新增流量包</el-button>
            </div>
          </div>

          <div class="table-card__scope">
            <span class="scope-label">绑定配置</span>
            <el-select
              v-model="selectedProductIds"
              multiple
              collapse-tags
              collapse-tags-tooltip
              :max-collapse-tags="0"
              filterable
              clearable
              disabled
              :loading="productsLoading"
              placeholder="当前分组未绑定配置"
              size="small"
              style="flex:1;min-width:180px;"
            >
              <el-option v-for="item in categoryProducts" :key="item.id" :label="item.display_name" :value="item.id" />
            </el-select>
            <el-button size="small" @click="openEditGroupDialog(selectedGroup)">调整绑定</el-button>
          </div>

          <el-table :data="currentRows" row-key="_rowKey" class="package-table" empty-text="暂无流量包" size="small">
            <el-table-column label="名称" min-width="160">
              <template #default="{ row }">
                <el-input v-model="row.label" maxlength="40" placeholder="500G / 1T" />
              </template>
            </el-table-column>
            <el-table-column label="流量(G)" width="130">
              <template #default="{ row }">
                <el-input-number v-model="row.target_value" :min="1" :max="999999" controls-position="right" style="width:100%;" />
              </template>
            </el-table-column>
            <el-table-column label="售价(元)" width="130">
              <template #default="{ row }">
                <el-input-number v-model="row.price" :min="0" :precision="2" :step="1" controls-position="right" style="width:100%;" />
              </template>
            </el-table-column>
            <el-table-column label="排序" width="100">
              <template #default="{ row }">
                <el-input-number v-model="row.sort_order" :min="0" :max="9999" controls-position="right" style="width:100%;" />
              </template>
            </el-table-column>
            <el-table-column label="启用" width="70" align="center">
              <template #default="{ row }">
                <el-switch v-model="row.enabled" />
              </template>
            </el-table-column>
            <el-table-column width="60" align="right">
              <template #default="{ $index }">
                <el-button type="danger" link size="small" @click="removePackageRow($index)">删除</el-button>
              </template>
            </el-table-column>
          </el-table>
        </template>
      </el-card>
    </section>

    <el-dialog v-model="pullDialogVisible" title="从上游接口拉取" width="520px">
      <el-form label-position="top">
        <el-form-item label="来源配置">
          <el-select v-model="selectedSourceProductId" placeholder="选择已绑定上游的本地配置" :loading="productsLoading" filterable clearable style="width:100%;" @change="handleSourceProductChange">
            <el-option v-for="item in categoryProducts" :key="item.id" :label="item.display_name" :value="item.id" :disabled="!item.has_supplier_binding">
              <span class="source-product-option">
                <span class="source-product-option__name">{{ item.display_name }}</span>
                <span v-if="item.has_supplier_binding" class="source-product-option__tag source-product-option__tag--ok">已绑定</span>
                <span v-else class="source-product-option__tag source-product-option__tag--missing">未绑定</span>
              </span>
            </el-option>
          </el-select>
        </el-form-item>
        <el-form-item label="供应商来源（可选）">
          <el-select v-model="selectedSupplierId" placeholder="不选则自动查找" :loading="supplierLoading" clearable filterable style="width:100%;" @change="handleSupplierChange">
            <el-option v-for="item in suppliers" :key="item.id" :label="item.name" :value="item.id" />
          </el-select>
        </el-form-item>
        <el-form-item v-if="selectedSupplierId && !selectedSourceProductId" label="上游商品（备用）">
          <el-select v-model="selectedSupplierProductId" placeholder="仅在未选来源商品时生效" :loading="supplierProductsLoading" filterable clearable style="width:100%;">
            <el-option-group v-for="group in supplierProductGroups" :key="group.key" :label="group.label">
              <el-option v-for="item in group.items" :key="item.id" :label="item.type_label ? `${item.name} · ${item.type_label}` : item.name" :value="item.id" />
            </el-option-group>
          </el-select>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="pullDialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="pulling" @click="confirmPullFromProvider">确认拉取</el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="groupDialogVisible" :title="groupDialogMode === 'create' ? '新增分组' : '编辑分组'" width="520px">
      <el-form label-position="top">
        <el-form-item label="分组名称">
          <el-input v-model="groupForm.name" maxlength="30" placeholder="例如：美国区通用流量包" />
        </el-form-item>
        <el-form-item label="商品类型">
          <el-select v-model="groupForm.product_type" placeholder="请选择商品类型" @change="handleGroupFormTypeChange">
            <el-option v-for="item in productTypeOptions" :key="item.value" :label="item.label" :value="item.value" />
          </el-select>
        </el-form-item>
        <el-form-item label="关联商品分类">
          <el-select v-model="groupForm.category_id" placeholder="请选择商品分类" :loading="groupFormCategoriesLoading" filterable @change="handleGroupFormCategoryChange">
            <el-option v-for="item in groupFormCategoryOptions" :key="item.id" :label="item.label" :value="item.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="绑定配置">
          <el-select
            v-model="groupForm.product_ids"
            multiple
            collapse-tags
            collapse-tags-tooltip
            :max-collapse-tags="0"
            filterable
            clearable
            :disabled="!groupForm.category_id"
            :loading="groupFormProductsLoading"
            placeholder="请选择当前分类下已有配置"
            style="width:100%;"
          >
            <el-option v-for="item in groupFormProductOptions" :key="item.id" :label="item.display_name" :value="item.id" />
          </el-select>
          <div class="form-help">流量包只会对绑定配置下的服务生效，不再默认覆盖分类下全部配置。</div>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="groupDialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="groupSaving" @click="submitGroupForm">保存分组</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import adminApi from '@/api/admin'
import productApi from '@/api/product'
import supplierApi from '@/api/supplier'
const loading = ref(false)
const saving = ref(false)
const pulling = ref(false)
const typesLoading = ref(false)
const categoriesLoading = ref(false)
const productsLoading = ref(false)
const supplierLoading = ref(false)
const supplierProductsLoading = ref(false)

const productTypeOptions = ref([])
const categoryOptions = ref([])
const groupItems = ref([])
const allPackageItems = ref([])
const currentRows = ref([])
const categoryProducts = ref([])
const suppliers = ref([])
const supplierProductGroups = ref([])

const selectedProductType = ref('')
const selectedCategoryId = ref(0)
const selectedGroupId = ref('')
const groupKeyword = ref('')
const selectedProductIds = ref([])
const selectedSupplierId = ref(null)
const selectedSupplierProductId = ref(null)
const selectedSourceProductId = ref(null)

const pullDialogVisible = ref(false)
const groupDialogVisible = ref(false)
const groupDialogMode = ref('create')
const groupSaving = ref(false)
const groupFormCategoriesLoading = ref(false)
const groupFormCategoryOptions = ref([])
const groupFormProductsLoading = ref(false)
const groupFormProductOptions = ref([])
const groupForm = ref(createEmptyGroupForm())

let rowSeed = 1
let groupSeed = 1

function createEmptyGroupForm() {
  return {
    id: '',
    name: '',
    product_type: '',
    category_id: 0,
    product_ids: [],
  }
}

function generateGroupId() {
  return `traffic-group-${Date.now()}-${groupSeed++}`
}

function buildLegacyGroupId(productType, categoryId) {
  return `legacy:${String(productType || '').trim()}:${Number(categoryId || 0)}`
}

function normalizeProductIds(values = []) {
  return Array.from(new Set(
    (Array.isArray(values) ? values : [])
      .map((value) => Number(value || 0))
      .filter((value) => Number.isFinite(value) && value > 0)
  ))
}

function resolveProductTypeLabel(productType) {
  return productTypeOptions.value.find((item) => String(item.value) === String(productType || ''))?.label || String(productType || '')
}

function resolveCategoryLabel(categoryId, options = categoryOptions.value) {
  return options.find((item) => Number(item.id || 0) === Number(categoryId || 0))?.label || ''
}

function resolveGroupItemKey(item) {
  const explicitGroupId = String(item?.group_id || '').trim()
  if (explicitGroupId) {
    return explicitGroupId
  }

  return buildLegacyGroupId(item?.product_type, item?.category_id)
}

function sortGroups(groups = []) {
  return [...groups].sort((left, right) => {
    if (Number(left.sort_order || 0) !== Number(right.sort_order || 0)) {
      return Number(left.sort_order || 0) - Number(right.sort_order || 0)
    }

    return String(left.name || '').localeCompare(String(right.name || ''), 'zh-CN')
  })
}

function normalizeGroupsOrder(groups = []) {
  return sortGroups(groups).map((group, index) => ({
    ...group,
    sort_order: index + 1,
  }))
}

function parseJsonArray(rawValue) {
  try {
    if (typeof rawValue === 'string') {
      return JSON.parse(rawValue || '[]')
    }
    return Array.isArray(rawValue) ? rawValue : []
  } catch {
    return []
  }
}


const selectedGroup = computed(() => (
  displayGroups.value.find((group) => String(group.id) === String(selectedGroupId.value)) || null
))

const selectedGroupLabel = computed(() => selectedGroup.value?.name || '')

const selectedGroupCategoryLabel = computed(() => {
  if (!selectedGroup.value) {
    return ''
  }

  return selectedGroup.value.category_label || resolveCategoryLabel(selectedGroup.value.category_id) || `分类 #${selectedGroup.value.category_id}`
})

const groupPackageStatsMap = computed(() => {
  const map = new Map()

  groupItems.value.forEach((group) => {
    map.set(String(group.id), {
      packageCount: 0,
      scopedProductCount: normalizeProductIds(group.product_ids || []).length,
    })
  })

  allPackageItems.value.forEach((item) => {
    const groupId = resolveGroupItemKey(item)
    const record = map.get(groupId) || { packageCount: 0, scopedProductCount: 0 }
    record.packageCount += 1
    record.scopedProductCount = Math.max(record.scopedProductCount, normalizeProductIds(item.product_ids || []).length)
    map.set(groupId, record)
  })

  return map
})

const filteredGroups = computed(() => {
  const keyword = String(groupKeyword.value || '').trim().toLowerCase()

  return sortGroups(displayGroups.value).filter((group) => {
    if (selectedProductType.value && String(group.product_type) !== String(selectedProductType.value)) {
      return false
    }

    if (!keyword) {
      return true
    }

    return [group.name, group.category_label, resolveProductTypeLabel(group.product_type)]
      .filter(Boolean)
      .some((value) => String(value).toLowerCase().includes(keyword))
  })
})

const displayGroups = computed(() => {
  return normalizeGroupsOrder(groupItems.value)
})

const selectedGroupSavedPackageCount = computed(() => (
  groupPackageStatsMap.value.get(String(selectedGroupId.value))?.packageCount || 0
))

function createRow(item = {}) {
  return {
    _rowKey: `traffic-package-${rowSeed++}`,
    group_id: String(item.group_id || selectedGroupId.value || ''),
    category_id: Number(item.category_id || selectedCategoryId.value || 0),
    product_type: String(item.product_type || selectedProductType.value || ''),
    label: String(item.label || ''),
    target_value: Number(item.target_value || 0) || null,
    price: Number(item.price || 0),
    enabled: !(item.enabled === false || item.enabled === 0 || item.enabled === '0'),
    sort_order: Number(item.sort_order || currentRows.value.length + 1),
  }
}

function parseCatalogPayload(payload = []) {
  if (!Array.isArray(payload)) {
    return []
  }

  return payload
    .filter((item) => item && typeof item === 'object')
    .map((item) => ({
      group_id: String(item.group_id || '').trim(),
      group_name: String(item.group_name || '').trim(),
      category_id: Number(item.category_id || 0),
      category_label: String(item.category_label || '').trim(),
      product_type: String(item.product_type || '').trim(),
      product_ids: normalizeProductIds(item.product_ids || []),
      label: String(item.label || ''),
      target_value: Number(item.target_value || 0),
      price: Number(item.price || 0),
      enabled: !(item.enabled === false || item.enabled === 0 || item.enabled === '0'),
      sort_order: Number(item.sort_order || 0),
    }))
    .filter((item) => item.category_id > 0 && item.target_value > 0)
}

function serializeCatalogPayload(items = []) {
  return items.map((item) => ({
    group_id: String(item.group_id || '').trim(),
    group_name: String(item.group_name || '').trim(),
    category_id: Number(item.category_id || 0),
    category_label: String(item.category_label || '').trim(),
    product_type: String(item.product_type || '').trim(),
    product_ids: normalizeProductIds(item.product_ids || []),
    label: String(item.label || '').trim(),
    target_value: Number(item.target_value || 0),
    price: Number(item.price || 0).toFixed(2),
    enabled: item.enabled ? 1 : 0,
    sort_order: Number(item.sort_order || 0),
  }))
}

function parseGroupsPayload(payload = []) {
  if (!Array.isArray(payload)) {
    return []
  }

  return normalizeGroupsOrder(payload
    .filter((item) => item && typeof item === 'object')
    .map((item) => ({
      id: String(item.id || '').trim(),
      name: String(item.name || '').trim(),
      product_type: String(item.product_type || '').trim(),
      category_id: Number(item.category_id || 0),
      category_label: String(item.category_label || '').trim(),
      product_ids: normalizeProductIds(item.product_ids || []),
      sort_order: Number(item.sort_order || 0),
    }))
    .filter((item) => item.id && item.name && item.product_type && item.category_id > 0))
}

function serializeGroupsPayload(groups = []) {
  return normalizeGroupsOrder(groups).map((group) => ({
    id: String(group.id || '').trim(),
    name: String(group.name || '').trim(),
    product_type: String(group.product_type || '').trim(),
    category_id: Number(group.category_id || 0),
    category_label: String(group.category_label || '').trim(),
    product_ids: normalizeProductIds(group.product_ids || []),
    sort_order: Number(group.sort_order || 0),
  }))
}

function createFallbackGroupsFromItems(items = []) {
  const map = new Map()

  items.forEach((item) => {
    const productType = String(item.product_type || '').trim()
    const categoryId = Number(item.category_id || 0)
    if (!productType || categoryId <= 0) {
      return
    }

    const groupId = resolveGroupItemKey(item)
    if (map.has(groupId)) {
      return
    }

    const categoryLabel = String(item.category_label || '').trim() || `分类 #${categoryId}`
    const groupName = String(item.group_name || '').trim() || `${resolveProductTypeLabel(productType)} · ${categoryLabel}`
    map.set(groupId, {
      id: groupId,
      name: groupName,
      product_type: productType,
      category_id: categoryId,
      category_label: categoryLabel,
      product_ids: normalizeProductIds(item.product_ids || []),
      sort_order: map.size + 1,
    })
  })

  return normalizeGroupsOrder(Array.from(map.values()))
}

function hydrateGroupsWithItemProductIds(groups = [], items = []) {
  const productIdsByGroup = new Map()

  items.forEach((item) => {
    const groupId = resolveGroupItemKey(item)
    const ids = normalizeProductIds(item.product_ids || [])
    if (!groupId || ids.length === 0) {
      return
    }

    const current = productIdsByGroup.get(groupId) || []
    productIdsByGroup.set(groupId, normalizeProductIds(current.concat(ids)))
  })

  return groups.map((group) => {
    const groupProductIds = normalizeProductIds(group.product_ids || [])
    if (groupProductIds.length > 0) {
      return group
    }

    return {
      ...group,
      product_ids: productIdsByGroup.get(String(group.id)) || [],
    }
  })
}

function resetCurrentSelectionState() {
  selectedCategoryId.value = 0
  currentRows.value = []
  categoryProducts.value = []
  selectedProductIds.value = []
  selectedSourceProductId.value = null
}

function syncCurrentRowsFromCatalog() {
  if (!selectedGroup.value) {
    resetCurrentSelectionState()
    return
  }

  selectedCategoryId.value = Number(selectedGroup.value.category_id || 0)

  const scopedItems = allPackageItems.value
    .filter((item) => String(resolveGroupItemKey(item)) === String(selectedGroup.value.id))
    .sort((left, right) => {
      if (Number(left.sort_order || 0) !== Number(right.sort_order || 0)) {
        return Number(left.sort_order || 0) - Number(right.sort_order || 0)
      }

      return Number(left.target_value || 0) - Number(right.target_value || 0)
    })
  const groupProductIds = normalizeProductIds(selectedGroup.value.product_ids || [])
  const scopedProductIds = groupProductIds.length ? groupProductIds : normalizeProductIds(scopedItems[0]?.product_ids || [])
  selectedProductIds.value = scopedProductIds
  currentRows.value = scopedItems.map((item) => createRow(item))
}

function refreshGroupCategoryLabels(productType, options = []) {
  const labelMap = new Map(options.map((item) => [Number(item.id || 0), String(item.label || '').trim()]))

  groupItems.value = groupItems.value.map((group) => {
    if (String(group.product_type) !== String(productType || '')) {
      return group
    }

    const categoryLabel = labelMap.get(Number(group.category_id || 0)) || group.category_label
    return categoryLabel && categoryLabel !== group.category_label
      ? { ...group, category_label: categoryLabel }
      : group
  })
}

async function requestCategoryOptions(productType) {
  if (!productType) {
    return []
  }

  const res = await productApi.categories({ product_type: productType })
  return Array.isArray(res.data?.options) ? res.data.options.map((item) => ({
    ...item,
    id: Number(item.id || 0),
    label: String(item.label || item.name || ''),
  })).filter((item) => item.id > 0) : []
}

async function requestProductOptions(productType, categoryId) {
  if (!productType || Number(categoryId || 0) <= 0) {
    return []
  }

  const res = await productApi.list({
    product_type: productType,
    category_id: Number(categoryId || 0),
    page: 1,
    page_size: 100,
  })

  return (res.data?.list || []).map((item) => {
    const supplierId = Number(item.supplier_id || 0)
    const supplierProductId = Number(item.supplier_product_id || 0)
    const productId = Number(item.id || 0)
    const displayName = String(item.display_name || '').trim() || `未配置规格 #${productId}`
    return {
      id: productId,
      name: String(item.name || ''),
      display_name: displayName,
      supplier_id: supplierId,
      supplier_product_id: supplierProductId,
      has_supplier_binding: supplierId > 0 && supplierProductId > 0,
    }
  }).filter((item) => item.id > 0).sort((left, right) => {
    if (left.has_supplier_binding !== right.has_supplier_binding) {
      return left.has_supplier_binding ? -1 : 1
    }
    return left.display_name.localeCompare(right.display_name, 'zh-CN')
  })
}

async function loadProductTypes() {
  typesLoading.value = true
  try {
    const res = await productApi.types()
    productTypeOptions.value = (res.data?.list || res.data || []).map((item) => ({
      value: item.value || item.code || item.id,
      label: item.label || item.name || item.value || item.code,
    }))

    if (!selectedProductType.value) {
      selectedProductType.value = productTypeOptions.value[0]?.value || ''
    }
  } finally {
    typesLoading.value = false
  }
}

async function loadCategories() {
  if (!selectedProductType.value) {
    categoryOptions.value = []
    selectedCategoryId.value = 0
    return
  }

  categoriesLoading.value = true
  try {
    categoryOptions.value = await requestCategoryOptions(selectedProductType.value)
    refreshGroupCategoryLabels(selectedProductType.value, categoryOptions.value)

    if (selectedGroup.value) {
      selectedCategoryId.value = Number(selectedGroup.value.category_id || 0)
    }
  } finally {
    categoriesLoading.value = false
  }
}

async function loadSuppliers() {
  supplierLoading.value = true
  try {
    const res = await supplierApi.list({ status: 1, page: 1, page_size: 100 })
    suppliers.value = (res.data?.list || []).map((item) => ({
      id: Number(item.id || 0),
      name: String(item.name || ''),
      interface_type: String(item.interface_type || ''),
    })).filter((item) => item.id > 0)
  } finally {
    supplierLoading.value = false
  }
}

async function loadSupplierProducts(supplierId) {
  const normalizedSupplierId = Number(supplierId || 0)
  if (normalizedSupplierId <= 0) {
    supplierProductGroups.value = []
    selectedSupplierProductId.value = null
    return
  }

  supplierProductsLoading.value = true
  try {
    const res = await supplierApi.products(normalizedSupplierId, { silent: true })
    supplierProductGroups.value = (res.data?.groups || []).map((group) => ({
      key: group.key,
      label: group.label,
      items: (group.items || []).map((item) => ({
        id: Number(item.id || 0),
        name: String(item.name || ''),
        type_label: String(item.type_label || item.type || ''),
      })).filter((item) => item.id > 0),
    })).filter((group) => group.items.length > 0)
  } catch (error) {
    supplierProductGroups.value = []
    selectedSupplierProductId.value = null
    ElMessage.error(error?.message || '加载供应商商品失败')
  } finally {
    supplierProductsLoading.value = false
  }
}

async function loadCatalog() {
  const res = await adminApi.settings.list({ group: 'traffic_package_catalog' })
  const rawItems = Array.isArray(res.data) ? res.data.find((item) => item.key === 'items')?.value : '[]'
  const rawGroups = Array.isArray(res.data) ? res.data.find((item) => item.key === 'groups')?.value : '[]'
  const parsedItems = parseCatalogPayload(parseJsonArray(rawItems))
  const parsedGroups = parseGroupsPayload(parseJsonArray(rawGroups))
  const nextGroups = parsedGroups.length ? parsedGroups : createFallbackGroupsFromItems(parsedItems)

  allPackageItems.value = parsedItems
  groupItems.value = hydrateGroupsWithItemProductIds(nextGroups, parsedItems)

  if (!selectedProductType.value && groupItems.value.length) {
    selectedProductType.value = groupItems.value[0].product_type
  }
}

async function loadCategoryProducts() {
  if (!selectedGroup.value || !selectedProductType.value || !selectedCategoryId.value) {
    categoryProducts.value = []
    selectedProductIds.value = []
    selectedSourceProductId.value = null
    return
  }

  productsLoading.value = true
  try {
    categoryProducts.value = await requestProductOptions(selectedProductType.value, selectedCategoryId.value)

    const validIds = new Set(categoryProducts.value.map((item) => item.id))
    selectedProductIds.value = normalizeProductIds(selectedProductIds.value).filter((id) => validIds.has(id))

    const boundProducts = categoryProducts.value.filter((item) => item.has_supplier_binding)
    const currentSource = Number(selectedSourceProductId.value || 0)
    if (currentSource <= 0 || !boundProducts.some((item) => item.id === currentSource)) {
      selectedSourceProductId.value = boundProducts[0]?.id || null
    }
  } finally {
    productsLoading.value = false
  }
}

async function loadGroupFormCategories(productType) {
  if (!productType) {
    groupFormCategoryOptions.value = []
    return
  }

  groupFormCategoriesLoading.value = true
  try {
    groupFormCategoryOptions.value = await requestCategoryOptions(productType)
  } finally {
    groupFormCategoriesLoading.value = false
  }
}

async function loadGroupFormProducts(productType, categoryId) {
  if (!productType || Number(categoryId || 0) <= 0) {
    groupFormProductOptions.value = []
    return
  }

  groupFormProductsLoading.value = true
  try {
    groupFormProductOptions.value = await requestProductOptions(productType, categoryId)
    const validIds = new Set(groupFormProductOptions.value.map((item) => item.id))
    groupForm.value.product_ids = normalizeProductIds(groupForm.value.product_ids || []).filter((id) => validIds.has(id))
  } finally {
    groupFormProductsLoading.value = false
  }
}

async function saveCatalogSettings(nextItems, nextGroups) {
  await adminApi.settings.save({
    group: 'traffic_package_catalog',
    settings: {
      items: JSON.stringify(serializeCatalogPayload(nextItems)),
      groups: JSON.stringify(serializeGroupsPayload(nextGroups)),
    },
  })
}

async function syncSelectedGroupForCurrentType() {
  let matchingGroups = sortGroups(displayGroups.value).filter((group) => (
    !selectedProductType.value || String(group.product_type) === String(selectedProductType.value)
  ))

  if (!matchingGroups.length && displayGroups.value.length) {
    const fallbackGroup = sortGroups(displayGroups.value)[0]
    if (fallbackGroup) {
      selectedProductType.value = fallbackGroup.product_type
      await loadCategories()
      matchingGroups = sortGroups(displayGroups.value).filter((group) => (
        String(group.product_type) === String(selectedProductType.value)
      ))
    }
  }

  const nextGroup = matchingGroups.find((group) => String(group.id) === String(selectedGroupId.value)) || matchingGroups[0] || null

  selectedGroupId.value = nextGroup?.id || ''
  syncCurrentRowsFromCatalog()
  await loadCategoryProducts()
}

async function loadData() {
  loading.value = true
  try {
    await loadProductTypes()
    await loadCatalog()
    await loadSuppliers()
    await loadCategories()
    await syncSelectedGroupForCurrentType()
  } catch (error) {
    ElMessage.error(error?.message || '加载流量包配置失败')
  } finally {
    loading.value = false
  }
}

async function handleTypeChange() {
  selectedProductIds.value = []
  selectedSourceProductId.value = null
  await loadCategories()
  await syncSelectedGroupForCurrentType()
}

async function handleGroupSelect(groupId) {
  const nextGroup = displayGroups.value.find((group) => String(group.id) === String(groupId))
  if (!nextGroup) {
    return
  }

  selectedGroupId.value = nextGroup.id
  if (String(selectedProductType.value) !== String(nextGroup.product_type)) {
    selectedProductType.value = nextGroup.product_type
    await loadCategories()
  } else if (!categoryOptions.value.length) {
    await loadCategories()
  }

  syncCurrentRowsFromCatalog()
  await loadCategoryProducts()
}

async function handleSupplierChange(value) {
  selectedSupplierId.value = Number(value || 0) || null
  selectedSupplierProductId.value = null
  await loadSupplierProducts(selectedSupplierId.value)
}

function handleSourceProductChange(value) {
  selectedSourceProductId.value = Number(value || 0) || null
  if (selectedSourceProductId.value > 0) {
    selectedSupplierId.value = null
    selectedSupplierProductId.value = null
    supplierProductGroups.value = []
  }
}

async function handleGroupFormTypeChange(value) {
  groupForm.value.product_type = String(value || '')
  groupForm.value.category_id = 0
  groupForm.value.product_ids = []
  groupFormProductOptions.value = []
  await loadGroupFormCategories(groupForm.value.product_type)
}

async function handleGroupFormCategoryChange(value) {
  groupForm.value.category_id = Number(value || 0)
  groupForm.value.product_ids = []
  await loadGroupFormProducts(groupForm.value.product_type, groupForm.value.category_id)
}

async function openCreateGroupDialog() {
  groupDialogMode.value = 'create'
  groupForm.value = {
    id: '',
    name: '',
    product_type: String(selectedProductType.value || productTypeOptions.value[0]?.value || ''),
    category_id: 0,
    product_ids: [],
  }
  groupDialogVisible.value = true
  await loadGroupFormCategories(groupForm.value.product_type)
  groupFormProductOptions.value = []
}

async function openEditGroupDialog(group) {
  groupDialogMode.value = 'edit'
  groupForm.value = {
    id: String(group.id),
    name: String(group.name || ''),
    product_type: String(group.product_type || ''),
    category_id: Number(group.category_id || 0),
    product_ids: normalizeProductIds(group.product_ids || []),
  }
  groupDialogVisible.value = true
  await loadGroupFormCategories(groupForm.value.product_type)
  await loadGroupFormProducts(groupForm.value.product_type, groupForm.value.category_id)
}

function addPackageRow() {
  if (!selectedGroup.value) {
    return
  }

  currentRows.value.push(createRow({
    group_id: selectedGroup.value.id,
    category_id: selectedGroup.value.category_id,
    product_type: selectedGroup.value.product_type,
    sort_order: currentRows.value.length + 1,
  }))
}

function removePackageRow(index) {
  currentRows.value.splice(index, 1)
}

function validateCurrentRows() {
  const targetValues = new Set()

  for (const row of currentRows.value) {
    const label = String(row.label || '').trim()
    const targetValue = Number(row.target_value || 0)
    const price = Number(row.price || 0)

    if (!label) {
      throw new Error('请填写流量包名称')
    }

    if (!Number.isFinite(targetValue) || targetValue <= 0) {
      throw new Error('目标流量必须大于 0')
    }

    if (targetValues.has(targetValue)) {
      throw new Error('同一分组下的目标流量不能重复')
    }
    targetValues.add(targetValue)

    if (!Number.isFinite(price) || price < 0) {
      throw new Error('售价不能小于 0')
    }
  }

  if (normalizeProductIds(selectedProductIds.value).length === 0) {
    throw new Error('请先在分组中绑定至少一个配置')
  }
}

async function submitGroupForm() {
  const groupId = String(groupForm.value.id || generateGroupId())
  const groupName = String(groupForm.value.name || '').trim()
  const productType = String(groupForm.value.product_type || '').trim()
  const categoryId = Number(groupForm.value.category_id || 0)
  const productIds = normalizeProductIds(groupForm.value.product_ids || [])

  if (!groupName) {
    ElMessage.warning('请填写分组名称')
    return
  }

  if (!productType) {
    ElMessage.warning('请选择商品类型')
    return
  }

  if (categoryId <= 0) {
    ElMessage.warning('请选择关联商品分类')
    return
  }

  if (productIds.length === 0) {
    ElMessage.warning('请至少绑定一个配置')
    return
  }

  const productNameMap = new Map(groupFormProductOptions.value.map((item) => [Number(item.id), String(item.display_name || '')]))
  const conflictGroup = groupItems.value.find((group) => {
    if (
      String(group.id) === groupId
      || String(group.product_type) !== productType
      || Number(group.category_id || 0) !== categoryId
    ) {
      return false
    }

    const boundIds = normalizeProductIds(group.product_ids || [])
    return boundIds.some((id) => productIds.includes(id))
  })

  if (conflictGroup) {
    const conflictIds = normalizeProductIds(conflictGroup.product_ids || []).filter((id) => productIds.includes(id))
    const conflictName = productNameMap.get(conflictIds[0]) || `配置 #${conflictIds[0]}`
    ElMessage.warning(`配置「${conflictName}」已绑定在分组「${conflictGroup.name}」`)
    return
  }

  const categoryLabel = groupFormCategoryOptions.value.find((item) => Number(item.id) === categoryId)?.label || `分类 #${categoryId}`
  const previousGroup = groupItems.value.find((group) => String(group.id) === groupId) || null
  const nextGroup = {
    id: groupId,
    name: groupName,
    product_type: productType,
    category_id: categoryId,
    category_label: categoryLabel,
    product_ids: productIds,
    sort_order: Number(previousGroup?.sort_order || groupItems.value.length + 1),
  }

  groupSaving.value = true
  try {
    const nextGroups = normalizeGroupsOrder(previousGroup
      ? groupItems.value.map((group) => (String(group.id) === groupId ? nextGroup : group))
      : [...groupItems.value, nextGroup])
    const nextItems = allPackageItems.value.map((item) => {
      if (String(resolveGroupItemKey(item)) !== groupId) {
        return item
      }

      return {
        ...item,
        group_id: groupId,
        group_name: groupName,
        category_id: categoryId,
        category_label: categoryLabel,
        product_type: productType,
        product_ids: productIds,
      }
    })

    await saveCatalogSettings(nextItems, nextGroups)

    groupItems.value = parseGroupsPayload(nextGroups)
    allPackageItems.value = parseCatalogPayload(nextItems)
    groupDialogVisible.value = false
    selectedProductType.value = productType
    selectedGroupId.value = groupId
    await loadCategories()
    syncCurrentRowsFromCatalog()
    await loadCategoryProducts()
    ElMessage.success(groupDialogMode.value === 'create' ? '分组已创建' : '分组已更新')
  } catch (error) {
    ElMessage.error(error?.message || '保存分组失败')
  } finally {
    groupSaving.value = false
  }
}

async function removeGroup(group) {
  try {
    await ElMessageBox.confirm(`删除分组「${group.name}」后，该分组下的流量包配置也会一并移除，是否继续？`, '删除分组', {
      confirmButtonText: '删除',
      cancelButtonText: '取消',
      type: 'warning',
    })
  } catch {
    return
  }

  loading.value = true
  try {
    const nextGroups = normalizeGroupsOrder(groupItems.value.filter((item) => String(item.id) !== String(group.id)))
    const nextItems = allPackageItems.value.filter((item) => String(resolveGroupItemKey(item)) !== String(group.id))

    await saveCatalogSettings(nextItems, nextGroups)

    groupItems.value = parseGroupsPayload(nextGroups)
    allPackageItems.value = parseCatalogPayload(nextItems)

    if (String(selectedGroupId.value) === String(group.id)) {
      selectedGroupId.value = ''
    }

    await syncSelectedGroupForCurrentType()
    ElMessage.success('分组已删除')
  } catch (error) {
    ElMessage.error(error?.message || '删除分组失败')
  } finally {
    loading.value = false
  }
}

async function saveCurrentGroup() {
  if (!selectedGroup.value || !selectedCategoryId.value || !selectedProductType.value) {
    ElMessage.warning('请先选择流量包分组')
    return
  }

  try {
    validateCurrentRows()
  } catch (error) {
    ElMessage.error(error.message)
    return
  }

  saving.value = true
  try {
    const scopedProductIds = normalizeProductIds(selectedProductIds.value)
    const preserved = allPackageItems.value.filter((item) => String(resolveGroupItemKey(item)) !== String(selectedGroup.value.id))
    const nextGroups = groupItems.value.some((group) => String(group.id) === String(selectedGroup.value.id))
      ? groupItems.value.map((group) => (String(group.id) === String(selectedGroup.value.id)
        ? { ...group, product_ids: scopedProductIds }
        : group))
      : normalizeGroupsOrder([...groupItems.value, {
        id: String(selectedGroup.value.id),
        name: String(selectedGroup.value.name || selectedGroupCategoryLabel.value || '').trim(),
        category_id: Number(selectedGroup.value.category_id || 0),
        category_label: String(selectedGroup.value.category_label || selectedGroupCategoryLabel.value || '').trim(),
        product_type: String(selectedGroup.value.product_type || ''),
        product_ids: scopedProductIds,
        sort_order: groupItems.value.length + 1,
      }])

    const nextItems = preserved.concat(currentRows.value.map((row) => ({
      group_id: String(selectedGroup.value.id),
      group_name: String(selectedGroup.value.name || '').trim(),
      category_id: Number(selectedGroup.value.category_id || 0),
      category_label: String(selectedGroup.value.category_label || selectedGroupCategoryLabel.value || '').trim(),
      product_type: String(selectedGroup.value.product_type || ''),
      product_ids: scopedProductIds,
      label: String(row.label || '').trim(),
      target_value: Number(row.target_value || 0),
      price: Number(row.price || 0),
      enabled: row.enabled ? 1 : 0,
      sort_order: Number(row.sort_order || 0),
    })))

    await saveCatalogSettings(nextItems, nextGroups)

    groupItems.value = parseGroupsPayload(nextGroups)
    allPackageItems.value = parseCatalogPayload(nextItems)
    syncCurrentRowsFromCatalog()
    ElMessage.success('当前分组流量包已保存')
  } catch (error) {
    ElMessage.error(error?.message || '保存流量包配置失败')
  } finally {
    saving.value = false
  }
}

function openPullDialog() {
  if (!selectedGroup.value || !selectedCategoryId.value || !selectedProductType.value) {
    ElMessage.warning('请先选择流量包分组')
    return
  }
  pullDialogVisible.value = true
}

async function confirmPullFromProvider() {
  const sourceProduct = categoryProducts.value.find((item) => Number(item.id) === Number(selectedSourceProductId.value))
  if (selectedSourceProductId.value > 0 && sourceProduct && !sourceProduct.has_supplier_binding) {
    ElMessage.warning('所选来源商品未绑定上游，请更换或先绑定上游')
    return
  }

  pulling.value = true
  try {
    const res = await productApi.pullTrafficPackages({
      category_id: selectedCategoryId.value,
      product_type: selectedProductType.value,
      source_product_id: selectedSourceProductId.value || undefined,
      supplier_id: selectedSourceProductId.value ? undefined : selectedSupplierId.value || undefined,
      supplier_product_id: selectedSourceProductId.value ? undefined : selectedSupplierProductId.value || undefined,
    })
    const packages = Array.isArray(res.data?.packages) ? res.data.packages : []

    currentRows.value = packages.map((item, index) => createRow({
      group_id: selectedGroup.value.id,
      category_id: selectedGroup.value.category_id,
      product_type: selectedGroup.value.product_type,
      label: String(item.label || ''),
      target_value: Number(item.target_value || 0),
      price: Number(item.price || 0),
      enabled: !(item.enabled === false || item.enabled === 0 || item.enabled === '0'),
      sort_order: Number(item.sort_order || index + 1),
    }))

    const sourceMode = String(res.data?.source?.mode || '').trim()
    const productText = String(
      res.data?.source?.display_name
      || res.data?.source?.product_display_name
      || res.data?.source?.product_name
      || ''
    ).trim()
    const serviceText = String(res.data?.source?.service_name || '').trim()
    const supplierText = String(res.data?.source?.supplier_name || '').trim()

    if (sourceMode === 'local_product_service' && serviceText) {
      ElMessage.success(productText
        ? `已通过来源配置「${productText}」的实例「${serviceText}」拉取实时流量包报价`
        : `已通过实例「${serviceText}」拉取实时流量包报价`)
    } else if (sourceMode === 'local_product_template') {
      ElMessage.success(productText
        ? `已通过来源配置「${productText}」的上游模板拉取流量包档位`
        : '已通过来源配置的上游模板拉取流量包档位')
    } else if (sourceMode === 'service' && serviceText) {
      ElMessage.success(`已从实例「${serviceText}」拉取流量包配置`)
    } else if (sourceMode === 'supplier_product') {
      ElMessage.success(productText
        ? `已从 ${supplierText || '上游接口'} 配置模板「${productText}」拉取流量包配置`
        : '已从供应商配置模板拉取流量包配置')
    } else {
      ElMessage.success(productText ? `已从配置模板「${productText}」拉取流量包配置` : '已从上游接口拉取流量包配置')
    }
    pullDialogVisible.value = false
  } catch (error) {
    ElMessage.error(error?.message || '拉取流量包配置失败')
  } finally {
    pulling.value = false
  }
}

onMounted(loadData)
</script>

<style scoped lang="scss">
.traffic-package-page {
  gap: 16px;
}

.page-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
}

.traffic-package-layout {
  display: grid;
  grid-template-columns: 280px minmax(0, 1fr);
  gap: 16px;
  align-items: start;
}

.group-card,
.table-card {
  border-radius: $base-border-radius;
}

.group-card {
  min-height: 480px;

  :deep(.el-card__body) {
    padding: 16px;
  }
}

.group-card__toolbar {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 12px;
}

.group-card__search {
  margin-bottom: 12px;
}

.group-list {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.group-list-item {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 12px;
  border: 1px solid $border-color;
  border-radius: $base-border-radius;
  background: $bg-color-card;
  cursor: pointer;
  transition: border-color 0.15s ease-out, background 0.15s ease-out;

  &:hover {
    border-color: rgba($color-primary, 0.35);
    background: $bg-color-soft;
  }

  &.is-active {
    border-color: $color-primary;
    background: color.mix($color-primary-soft, $bg-color-card, 42%);
  }
}

.group-list-item__body {
  min-width: 0;
  flex: 1;

  strong {
    display: block;
    overflow: hidden;
    color: $text-color-primary;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-size: 13px;
    line-height: 1.5;
  }
}

.group-list-item__sub {
  display: block;
  overflow: hidden;
  color: $text-color-secondary;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-size: 12px;
  line-height: 1.5;
}

.group-list-item__actions {
  display: flex;
  align-items: center;
  gap: 2px;
  flex: 0 0 auto;
}

.table-card {
  :deep(.el-card__body) {
    padding: 16px;
  }
}

.table-card__toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 12px;
}

.table-card__info {
  min-width: 0;

  strong {
    display: block;
    color: $text-color-primary;
    font-size: 15px;
    font-weight: 700;
    line-height: 1.4;
  }

  span {
    color: $text-color-secondary;
    font-size: 12px;
  }
}

.table-card__actions {
  display: flex;
  align-items: center;
  gap: 8px;
  flex: 0 0 auto;
}

.table-card__scope {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 10px;
  padding: 10px 12px;
  margin-bottom: 12px;
  border: 1px solid $border-color;
  border-radius: $base-border-radius;
  background: $bg-color-soft;
}

.scope-label {
  color: $text-color-secondary;
  font-size: 12px;
  font-weight: 600;
  flex: 0 0 auto;
}

.package-table {
  :deep(.el-input-number .el-input__wrapper),
  :deep(.el-input .el-input__wrapper) {
    width: 100%;
  }
}

.source-product-option {
  display: flex;
  align-items: center;
  gap: 8px;

  &__name {
    flex: 1;
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  &__tag {
    flex: 0 0 auto;
    padding: 2px 8px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 500;
    line-height: 1.4;

    &--ok {
      color: #107c41;
      background: rgba(16, 124, 65, 0.1);
    }

    &--missing {
      color: $text-color-placeholder;
      background: rgba(100, 116, 139, 0.12);
    }
  }
}

@media (max-width: 1200px) {
  .traffic-package-layout {
    grid-template-columns: 1fr;
  }

  .group-card {
    min-height: auto;
  }
}

@media (max-width: 900px) {
  .table-card__toolbar {
    flex-direction: column;
    align-items: stretch;
  }

  .table-card__actions {
    justify-content: flex-end;
  }
}
</style>
