<template>
  <t-card :bordered="false">
    <div class="filter-row supplier-filter-row">
      <t-input
        class="supplier-filter-keyword"
        v-model="supplierFilters.keyword"
        clearable
        placeholder="搜索接口名称 / 用户名"
        @enter="handleSupplierSearch"
        @clear="handleSupplierSearch"
      >
        <template #suffix-icon><search-icon /></template>
      </t-input>
      <t-select
        class="supplier-filter-status"
        v-model="supplierFilters.status"
        clearable
        placeholder="接口状态"
        @change="handleSupplierSearch"
      >
        <t-option :value="1" label="启用中" />
        <t-option :value="0" label="已停用" />
      </t-select>
      <div class="supplier-filter-actions">
        <t-button v-if="canManageSuppliers" theme="primary" @click="openSupplierDialog()">
          <template #icon><add-icon /></template>
          新增提供商
        </t-button>
        <t-button theme="primary" variant="outline" @click="handleSupplierSearch">
          <template #icon><search-icon /></template>
          搜索
        </t-button>
        <t-button variant="outline" @click="resetSupplierFilters">
          <template #icon><refresh-icon /></template>
          重置
        </t-button>
      </div>
    </div>

    <div class="supplier-grid">
      <article v-for="row in suppliers" :key="row.id" class="supplier-card">
        <div class="supplier-card__head">
          <div>
            <strong>{{ row.name || '-' }}</strong>
            <span>{{ supplierInterfaceTypeLabel(row) }}</span>
          </div>
          <t-tag :theme="Number(row.status) === 1 ? 'success' : 'default'" variant="light">
            {{ Number(row.status) === 1 ? '启用中' : '已停用' }}
          </t-tag>
        </div>
        <dl>
          <div>
            <dt>用户名</dt>
            <dd>{{ row.api_username || '-' }}</dd>
          </div>
          <div>
            <dt>上游余额</dt>
            <dd>{{ row.remote_balance_status === 'success' ? `¥ ${row.remote_balance}` : balanceStatusLabel(row) }}</dd>
          </div>
          <div>
            <dt>最近更新</dt>
            <dd>{{ formatDateTime(row.updated_at) }}</dd>
          </div>
        </dl>
        <div class="supplier-card__actions">
          <t-button size="small" variant="text" theme="primary" @click="openSupplierDialog(row)">{{ canManageSuppliers ? '编辑' : '查看' }}</t-button>
          <t-button v-if="canSyncSuppliers" size="small" variant="text" :disabled="!canSupplierBatchConnect(row)" @click="openSupplierBatchDialog(row)">
            批量对接
          </t-button>
          <t-button v-if="canManageSuppliers" size="small" variant="text" :loading="supplierActionLoading === row.id" @click="handleToggleSupplier(row)">
            {{ Number(row.status) === 1 ? '停用' : '启用' }}
          </t-button>
          <t-button v-if="canManageSuppliers" size="small" variant="text" theme="danger" @click="handleDeleteSupplier(row)">删除</t-button>
        </div>
      </article>
      <t-empty v-if="!supplierLoading && suppliers.length === 0" description="暂无提供商" />
    </div>

    <div v-if="supplierTotal > 0" class="pagination-row">
      <t-pagination
        :current="supplierPage"
        :page-size="supplierPageSize"
        :total="supplierTotal"
        :page-size-options="[20, 50, 100]"
        show-jumper
        @change="handleSupplierPageChange"
      />
    </div>
  </t-card>

  <t-dialog
    v-model:visible="supplierBatchDialogVisible"
    :header="supplierBatchSupplier?.name ? `批量对接 · ${supplierBatchSupplier.name}` : '批量对接'"
    width="780px"
    :confirm-btn="canSyncSuppliers ? { content: '执行对接', loading: supplierBatchSubmitting } : null"
    @confirm="submitSupplierBatchConnect"
  >
    <div class="split-dialog-intro">
      <strong>批量导入并绑定上游商品</strong>
      <p>选择上游商品后，会按目标种类和分类创建或更新本地商品，并绑定当前提供商商品 ID。</p>
    </div>

    <t-form class="supplier-batch-form" :data="supplierBatchForm" label-width="110px">
      <div class="supplier-batch-form-grid">
        <t-form-item label="商品种类" name="product_type">
          <t-select
            v-model="supplierBatchForm.product_type"
            filterable
            placeholder="请选择商品种类"
            @change="handleSupplierBatchTypeChange"
          >
            <t-option v-for="item in supplierBatchProductTypes" :key="item.value" :label="item.label" :value="item.value" />
          </t-select>
        </t-form-item>
        <t-form-item label="导入分类" name="product_group_key">
          <t-select v-model="supplierBatchForm.product_group_key" filterable clearable placeholder="请选择目标分类">
            <t-option
              v-for="item in supplierBatchCategories"
              :key="productGroupOptionKey(item)"
              :label="productGroupOptionLabel(item)"
              :value="productGroupOptionKey(item)"
            />
          </t-select>
        </t-form-item>
        <t-form-item label="默认上架">
          <t-switch v-model="supplierBatchForm.default_status" :custom-value="[1, 0]" />
        </t-form-item>
        <t-form-item label="自动开通">
          <t-switch v-model="supplierBatchForm.default_auto_setup" :custom-value="[1, 0]" />
        </t-form-item>
        <t-form-item label="同步配置项">
          <t-switch v-model="supplierBatchForm.sync_config_options" :custom-value="[1, 0]" />
        </t-form-item>
      </div>
    </t-form>

    <div class="supplier-batch-toolbar">
      <span>已选 {{ supplierBatchSelectedKeys.length }} / {{ supplierBatchProducts.length }} 个上游商品</span>
      <t-space size="small">
        <t-button size="small" variant="outline" :loading="supplierBatchLoading" @click="reloadSupplierBatchProducts">刷新商品</t-button>
        <t-button size="small" variant="text" :disabled="!supplierBatchProducts.length" @click="selectPendingSupplierBatchProducts">
          选择未对接
        </t-button>
        <t-button size="small" variant="text" :disabled="!supplierBatchSelectedKeys.length" @click="supplierBatchSelectedKeys = []">
          清空
        </t-button>
      </t-space>
    </div>

    <t-table
      row-key="id"
      :data="supplierBatchProducts"
      :columns="supplierBatchColumns"
      :loading="supplierBatchLoading"
      :selected-row-keys="supplierBatchSelectedKeys"
      hover
      table-layout="fixed"
      @select-change="handleSupplierBatchSelectChange"
    >
      <template #name="{ row }">
        <div class="product-name">
          <strong>{{ row.name || '-' }}</strong>
          <span>{{ row.type_label || '-' }}</span>
        </div>
      </template>
      <template #connection="{ row }">
        <t-tag :theme="row.is_connected ? 'success' : 'warning'" variant="light">
          {{ row.is_connected ? '已对接' : '未对接' }}
        </t-tag>
      </template>
    </t-table>

    <div v-if="supplierBatchResult" class="supplier-batch-result">
      新增 {{ supplierBatchResult.created_count || 0 }}，更新 {{ supplierBatchResult.updated_count || 0 }}，跳过
      {{ supplierBatchResult.skipped_count || 0 }}
    </div>
  </t-dialog>

  <t-dialog
    v-model:visible="supplierDialogVisible"
    :header="editingSupplier ? '编辑提供商' : '新增提供商'"
    width="620px"
    :confirm-btn="canManageSuppliers ? { content: '保存', loading: supplierSubmitting } : null"
    @confirm="submitSupplier"
  >
    <t-form ref="supplierFormRef" :data="supplierForm" :rules="supplierRules" label-width="110px" :disabled="!canManageSuppliers">
      <t-form-item label="插件提供商" name="provider_key">
        <t-select v-model="supplierForm.provider_key" filterable @change="handleSupplierProviderChange">
          <t-option v-for="item in providerTypeOptions" :key="item.value" :label="item.label" :value="item.value" />
        </t-select>
      </t-form-item>
      <t-form-item label="接口名称" name="name"><t-input v-model="supplierForm.name" /></t-form-item>
      <div v-if="selectedSupplierFormHelp" class="supplier-form-help">{{ selectedSupplierFormHelp }}</div>
      <t-form-item v-for="field in supplierCredentialFields" :key="field.key" :label="field.label" :name="field.key">
        <t-select
          v-if="field.type === 'select'"
          v-model="supplierCredentialValues[field.key]"
          :placeholder="field.placeholder || `请选择${field.label}`"
          clearable
          filterable
        >
          <t-option
            v-for="option in field.options || []"
            :key="String(option.value)"
            :label="option.label"
            :value="option.value"
          />
        </t-select>
        <t-switch
          v-else-if="field.type === 'switch' || field.type === 'boolean'"
          v-model="supplierCredentialValues[field.key]"
        />
        <t-input-number
          v-else-if="field.type === 'number'"
          v-model="supplierCredentialValues[field.key]"
          :placeholder="field.placeholder || `请输入${field.label}`"
          style="width: 100%"
        />
        <t-textarea
          v-else-if="field.type === 'textarea'"
          v-model="supplierCredentialValues[field.key]"
          :autosize="{ minRows: 3, maxRows: 6 }"
          :placeholder="field.placeholder || `请输入${field.label}`"
        />
        <secret-input
          v-else-if="field.secret"
          :model-value="secretSupplierFieldValue(field)"
          :has-value="hasExistingSupplierSecret(field)"
          :placeholder="supplierFieldPlaceholder(field)"
          :reset-key="supplierSecretResetKey(field)"
          :can-reveal="canRevealSupplierSecrets"
          :reveal="() => revealSupplierSecret(field)"
          @update:model-value="(value: string) => (supplierCredentialValues[field.key] = value)"
          @edited-change="(value: boolean) => (supplierSecretEdited[field.key] = value)"
          @reveal-error="(error: unknown) => MessagePlugin.error(errorMessage(error, '读取敏感配置失败'))"
        />
        <t-input
          v-else
          v-model="supplierCredentialValues[field.key]"
          :type="field.type === 'password' ? 'password' : 'text'"
          clearable
          :placeholder="supplierFieldPlaceholder(field)"
        />
        <p v-if="field.description" class="supplier-field-tip">{{ field.description }}</p>
      </t-form-item>
      <t-form-item label="状态" name="status"><t-switch v-model="supplierForm.status" :custom-value="[1, 0]" /></t-form-item>
    </t-form>
  </t-dialog>
</template>

<script setup lang="ts">
import { AddIcon, RefreshIcon, SearchIcon } from 'tdesign-icons-vue-next';
import type { PageInfo, PrimaryTableCol } from 'tdesign-vue-next';
import { DialogPlugin, MessagePlugin } from 'tdesign-vue-next';
import { computed, onMounted, reactive, ref } from 'vue';

import SecretInput from '@/components/secret-input/index.vue';
import { productApi, type ProductCategoryRecord, type ProductTypeRecord } from '@/api/product';
import { supplierApi, type ProviderTypeRecord, type SupplierFormField, type SupplierFormSchema, type SupplierRecord, type SupplierUpsertPayload } from '@/api/supplier';
import { AdminPermissions } from '@/constants/permissions';
import { hasAdminPermission } from '@/utils/permission';

import {
  errorMessage,
  findProductGroupByKey,
  flattenCategories,
  formatDateTime,
  isSelectableProductGroup,
  mergeProviderTypeOptions,
  normalizeProductIds,
  productGroupOptionKey,
  productGroupOptionLabel,
  productGroupPayload,
  providerTypeFallbackLabels,
  providerTypeLabel,
  toPlainRecord,
} from '../composables/useProductShared';

interface SupplierBatchProduct {
  id: number;
  name: string;
  type_label: string;
  remote_group_name: string;
  is_connected: boolean;
  connected_display_name: string;
  [key: string]: unknown;
}

// --- State ---
const supplierLoading = ref(false);
const supplierSubmitting = ref(false);
const supplierActionLoading = ref<number | string | null>(null);
let supplierBalanceBatchId = 0;

const suppliers = ref<SupplierRecord[]>([]);
const supplierTotal = ref(0);
const supplierPage = ref(1);
const supplierPageSize = ref(20);
const supplierSummary = reactive({ total: 0, active: 0, inactive: 0 });
const providerTypes = ref<ProviderTypeRecord[]>([]);

const supplierFilters = reactive({
  keyword: '',
  status: '' as number | string,
});

const supplierDialogVisible = ref(false);
const editingSupplier = ref<SupplierRecord | null>(null);
const supplierFormRef = ref();
const supplierBatchDialogVisible = ref(false);
const supplierBatchLoading = ref(false);
const supplierBatchSubmitting = ref(false);
const supplierBatchSupplier = ref<SupplierRecord | null>(null);
const supplierBatchProducts = ref<SupplierBatchProduct[]>([]);
const supplierBatchProductTypes = ref<ProductTypeRecord[]>([]);
const supplierBatchCategories = ref<ProductCategoryRecord[]>([]);
const supplierBatchSelectedKeys = ref<Array<string | number>>([]);
const supplierBatchResult = ref<Record<string, unknown> | null>(null);
const supplierBatchForm = reactive({
  product_type: '',
  product_group_key: '' as string,
  default_status: 1,
  default_auto_setup: 1,
  sync_config_options: 1,
});
const supplierForm = reactive({
  provider_key: '',
  name: '',
  status: 1,
});
const supplierCredentialValues = reactive<Record<string, unknown>>({});
const supplierSecretEdited = reactive<Record<string, boolean>>({});
const canManageSuppliers = computed(() => hasAdminPermission(AdminPermissions.SUPPLIER_MANAGE));
const canSyncSuppliers = computed(() => hasAdminPermission(AdminPermissions.SUPPLIER_SYNC));
const canRevealSupplierSecrets = computed(() => hasAdminPermission(AdminPermissions.SUPPLIER_SECRET_REVEAL));

const supplierRules = {
  provider_key: [{ required: true, message: '请选择插件提供商', trigger: 'change' }],
  name: [{ required: true, message: '请输入接口名称', trigger: 'blur' }],
};

const fallbackSupplierCredentialFields: SupplierFormField[] = [
  {
    key: 'api_url',
    label: '接口地址',
    type: 'url',
    required: true,
    placeholder: 'https://panel.example.com',
  },
  {
    key: 'api_username',
    label: '接口账号',
    type: 'text',
    required: true,
  },
  {
    key: 'api_key',
    label: '接口密钥',
    type: 'password',
    required: true,
    secret: true,
    placeholder: '编辑时留空则保持原密钥',
  },
];

const supplierBatchColumns: PrimaryTableCol<SupplierBatchProduct>[] = [
  { colKey: 'row-select', type: 'multiple', width: 54, fixed: 'left' },
  { colKey: 'name', title: '上游商品', minWidth: 220 },
  { colKey: 'remote_group_name', title: '上游分组', width: 160 },
  { colKey: 'connection', title: '对接状态', width: 130 },
];

// --- Computeds ---
const providerTypeOptions = computed(() => {
  return mergeProviderTypeOptions(providerTypes.value);
});

const selectedProviderType = computed(() => {
  return providerTypeOptions.value.find((item) => item.value === supplierForm.provider_key) || null;
});

const selectedSupplierForm = computed<SupplierFormSchema>(() => {
  const schema = toPlainRecord(selectedProviderType.value?.supplier_form);
  const fields = normalizeSupplierFormFields(schema.fields);

  return {
    fields: fields.length > 0 ? fields : fallbackSupplierCredentialFields,
    help: String(schema.help || '').trim(),
  };
});

const selectedSupplierFormHelp = computed(() => selectedSupplierForm.value.help || '');

const supplierCredentialFields = computed(() => selectedSupplierForm.value.fields || []);

// --- Methods ---
function canSupplierBatchConnect(row: SupplierRecord) {
  if (!canSyncSuppliers.value) return false;

  const upstreamBinding = toPlainRecord(row.upstream_binding);
  const providerKey = String(upstreamBinding.provider_key || row.provider_key || '').trim();
  const schema = providerTypeOptions.value.find((item) => item.value === providerKey)?.supplier_form;
  const fields = normalizeSupplierFormFields(toPlainRecord(schema).fields);
  if (!fields.length) {
    return Boolean(
      (upstreamBinding.has_base_url || row.has_api_url || row.api_url) &&
        (upstreamBinding.account_name || row.api_username) &&
        (row.has_api_key || row.api_key),
    );
  }

  return fields.every((field) => {
    if (!field.required) return true;
    if (field.key === 'api_url') return Boolean(upstreamBinding.has_base_url || row.has_api_url || row.api_url);
    if (field.key === 'api_username') return Boolean(upstreamBinding.account_name || row.api_username);
    if (field.key === 'api_key') return Boolean(row.has_api_key || row.api_key);
    if (field.secret) return Boolean(toPlainRecord(upstreamBinding.has_secret_values)[field.key] || row.has_provider_secret_values?.[field.key]);
    return hasSupplierCredentialValue(toPlainRecord(row.provider_config)[field.key]);
  });
}

function hasSupplierCredentialValue(value: unknown) {
  if (typeof value === 'boolean') return true;
  if (typeof value === 'number') return Number.isFinite(value);
  return String(value ?? '').trim() !== '';
}

function supplierInterfaceTypeLabel(row: SupplierRecord) {
  const upstreamBinding = toPlainRecord(row.upstream_binding);
  const rawType = String(upstreamBinding.provider_key || row.provider_key || '').trim();
  if (rawType && providerTypeFallbackLabels[rawType]) return providerTypeFallbackLabels[rawType];
  return row.provider_label || providerTypeLabel(rawType, providerTypeOptions.value);
}

function balanceStatusLabel(row: SupplierRecord) {
  if (row.remote_balance_status === 'loading') return '同步中...';
  if (row.remote_balance_status === 'error') return '同步失败';
  if (row.remote_balance_status === 'disabled') return '未配置';
  return '-';
}

function handleSupplierSearch() {
  supplierPage.value = 1;
  void loadSuppliers();
}

function resetSupplierFilters() {
  supplierFilters.keyword = '';
  supplierFilters.status = '';
  supplierPage.value = 1;
  void loadSuppliers();
}

function handleSupplierPageChange(pageInfo: PageInfo) {
  supplierPage.value = pageInfo.current;
  supplierPageSize.value = pageInfo.pageSize;
  void loadSuppliers();
}

async function loadSupplierSummary() {
  try {
    Object.assign(supplierSummary, await supplierApi.summary());
  } catch {
    Object.assign(supplierSummary, { total: 0, active: 0, inactive: 0 });
  }
}

async function loadProviderTypes() {
  try {
    const response = await supplierApi.providerTypes();
    providerTypes.value = normalizeProviderTypeOptions(response);
  } catch {
    providerTypes.value = [];
  }
}

async function loadSuppliers() {
  supplierLoading.value = true;
  try {
    const response = await supplierApi.list({
      ...supplierFilters,
      page: supplierPage.value,
      page_size: supplierPageSize.value,
    });
    const rows = (Array.isArray(response.list) ? response.list : []).map((row) => ({
      ...row,
      remote_balance: null,
      remote_balance_status: 'idle',
      remote_client: {},
    }));
    suppliers.value = rows;
    supplierTotal.value = Number(response.total || 0);
    supplierPage.value = Number(response.page || supplierPage.value);
    supplierPageSize.value = Number(response.page_size || supplierPageSize.value);
    if (canSyncSuppliers.value) {
      void syncSupplierBalances(rows);
    }
  } catch (error) {
    supplierBalanceBatchId += 1;
    suppliers.value = [];
    supplierTotal.value = 0;
    MessagePlugin.error(errorMessage(error, '加载提供商失败'));
  } finally {
    supplierLoading.value = false;
  }
}

async function syncSupplierBalances(rows: SupplierRecord[]) {
  const currentBatchId = ++supplierBalanceBatchId;
  await Promise.allSettled(
    rows.map(async (row) => {
      if (!canSupplierBatchConnect(row)) {
        patchSupplierBalance(row.id, {
          remote_balance: null,
          remote_client: {},
          remote_balance_status: 'disabled',
        });
        return;
      }

      patchSupplierBalance(row.id, {
        remote_balance: null,
        remote_balance_status: 'loading',
      });
      try {
        const response = await supplierApi.balance(row.id, { silent: true });
        if (currentBatchId !== supplierBalanceBatchId) return;
        const record = toPlainRecord(response);
        patchSupplierBalance(row.id, {
          remote_balance: String(record.balance || '0.00'),
          remote_client: record.client || {},
          remote_balance_status: 'success',
        });
      } catch {
        if (currentBatchId !== supplierBalanceBatchId) return;
        patchSupplierBalance(row.id, {
          remote_balance: null,
          remote_client: {},
          remote_balance_status: 'error',
        });
      }
    }),
  );
}

function patchSupplierBalance(supplierId: SupplierRecord['id'], patch: Partial<SupplierRecord>) {
  const index = suppliers.value.findIndex((item) => String(item.id) === String(supplierId));
  if (index === -1) return;
  suppliers.value[index] = {
    ...suppliers.value[index],
    ...patch,
  };
}

function resetSupplierBatchState() {
  supplierBatchProducts.value = [];
  supplierBatchCategories.value = [];
  supplierBatchSelectedKeys.value = [];
  supplierBatchResult.value = null;
  Object.assign(supplierBatchForm, {
    product_type: '',
    product_group_key: '',
    default_status: 1,
    default_auto_setup: 1,
    sync_config_options: 1,
  });
}

async function loadSupplierBatchTypes() {
  const response = await productApi.types();
  supplierBatchProductTypes.value = Array.isArray(response) ? response : response.list || [];
  if (!supplierBatchForm.product_type && supplierBatchProductTypes.value[0]?.value) {
    supplierBatchForm.product_type = supplierBatchProductTypes.value[0].value;
  }
}

async function loadSupplierBatchCategories() {
  if (!supplierBatchForm.product_type) {
    supplierBatchCategories.value = [];
    return;
  }
  const response = await productApi.categories({ product_type: supplierBatchForm.product_type });
  supplierBatchCategories.value = flattenCategories(response.tree || response.list || []).filter((item) => isSelectableProductGroup(item));
}

function normalizeSupplierBatchProduct(itemValue: unknown): SupplierBatchProduct {
  const item = toPlainRecord(itemValue);
  return {
    ...item,
    id: Number(item.id || item.product_id || 0),
    name: String(item.name || item.product_name || '').trim(),
    type_label: String(item.type_label || item.type_name || item.type || item.billingcycle || '').trim(),
    remote_group_name: String(item.remote_group_name || item.group_name || item.second_group_name || item._group_label || '').trim(),
    is_connected: Boolean(item.is_connected),
    connected_display_name: String(item.connected_display_name || '').trim(),
  };
}

function buildSupplierBatchProducts(payloadValue: unknown) {
  const payload = toPlainRecord(payloadValue);
  const directProducts = Array.isArray(payload.products) ? payload.products : Array.isArray(payload) ? payload : [];
  if (directProducts.length) {
    return directProducts.map(normalizeSupplierBatchProduct).filter((item) => item.id > 0);
  }

  const groups = Array.isArray(payload.groups) ? payload.groups : [];
  return groups.flatMap((groupValue) => {
    const group = toPlainRecord(groupValue);
    const groupLabel = String(group.label || group.name || '').trim();
    const items = Array.isArray(group.items) ? group.items : [];
    return items.map((item) => normalizeSupplierBatchProduct({ ...toPlainRecord(item), _group_label: groupLabel }));
  }).filter((item) => item.id > 0);
}

async function loadSupplierBatchProducts() {
  if (!supplierBatchSupplier.value?.id) return;
  supplierBatchLoading.value = true;
  try {
    const response = await supplierApi.products(supplierBatchSupplier.value.id, { silent: true });
    supplierBatchProducts.value = buildSupplierBatchProducts(response);
    const availableIds = new Set(supplierBatchProducts.value.map((item) => Number(item.id)));
    supplierBatchSelectedKeys.value = supplierBatchSelectedKeys.value.filter((id) => availableIds.has(Number(id)));
  } catch (error) {
    supplierBatchProducts.value = [];
    MessagePlugin.error(errorMessage(error, '加载上游商品失败'));
  } finally {
    supplierBatchLoading.value = false;
  }
}

async function openSupplierBatchDialog(row: SupplierRecord) {
  if (!canSyncSuppliers.value) return;

  if (!canSupplierBatchConnect(row)) {
    MessagePlugin.warning('接口配置不完整，暂时无法批量对接商品');
    return;
  }

  resetSupplierBatchState();
  supplierBatchSupplier.value = row;
  supplierBatchDialogVisible.value = true;
  try {
    await loadSupplierBatchTypes();
    await Promise.all([loadSupplierBatchCategories(), loadSupplierBatchProducts()]);
  } catch (error) {
    MessagePlugin.error(errorMessage(error, '加载批量对接数据失败'));
  }
}

function handleSupplierBatchTypeChange(value: string | number) {
  supplierBatchForm.product_type = String(value || '');
  supplierBatchForm.product_group_key = '';
  void loadSupplierBatchCategories();
}

function handleSupplierBatchSelectChange(value: Array<string | number>) {
  supplierBatchSelectedKeys.value = value;
}

function selectPendingSupplierBatchProducts() {
  supplierBatchSelectedKeys.value = supplierBatchProducts.value.filter((item) => !item.is_connected).map((item) => item.id);
}

function reloadSupplierBatchProducts() {
  if (!canSyncSuppliers.value) return;

  void loadSupplierBatchProducts();
}

function resolveSupplierBatchCategoryPayload() {
  return productGroupPayload(findProductGroupByKey(supplierBatchCategories.value, supplierBatchForm.product_group_key));
}

async function submitSupplierBatchConnect() {
  if (!canSyncSuppliers.value) return;
  if (!supplierBatchSupplier.value?.id) return;
  if (!supplierBatchForm.product_type) {
    MessagePlugin.warning('请选择商品种类');
    return;
  }
  if (!supplierBatchForm.product_group_key) {
    MessagePlugin.warning('请选择导入分类');
    return;
  }
  if (!supplierBatchSelectedKeys.value.length) {
    MessagePlugin.warning('请选择上游商品');
    return;
  }

  supplierBatchSubmitting.value = true;
  try {
    const response = await supplierApi.batchConnectProducts(supplierBatchSupplier.value.id, {
      product_type: supplierBatchForm.product_type,
      ...resolveSupplierBatchCategoryPayload(),
      product_ids: selectedProductIdsFromKeys(supplierBatchSelectedKeys.value),
      default_status: Number(supplierBatchForm.default_status || 0),
      default_auto_setup: Number(supplierBatchForm.default_auto_setup || 0),
      sync_config_options: Number(supplierBatchForm.sync_config_options || 0),
    });
    supplierBatchResult.value = toPlainRecord(response);
    MessagePlugin.success('批量对接完成');
    await Promise.all([loadSupplierBatchProducts(), loadSuppliers()]);
  } catch (error) {
    MessagePlugin.error(errorMessage(error, '批量对接失败'));
  } finally {
    supplierBatchSubmitting.value = false;
  }
}

function selectedProductIdsFromKeys(keys: Array<string | number>) {
  return keys.map((id) => Number(id)).filter((id) => Number.isFinite(id) && id > 0);
}

async function openSupplierDialog(row?: SupplierRecord) {
  if (!providerTypeOptions.value.length) {
    await loadProviderTypes();
  }

  const detail = row?.id ? await loadSupplierDetail(row) : null;
  const source = detail || row;
  const upstreamBinding = toPlainRecord(source?.upstream_binding);
  editingSupplier.value = source || null;
  Object.assign(supplierForm, {
    provider_key: upstreamBinding.provider_key || source?.provider_key || providerTypeOptions.value[0]?.value || '',
    name: source?.name || '',
    status: Number(source?.status ?? 1),
  });
  resetSupplierCredentialValues(source || null);
  supplierDialogVisible.value = true;
}

async function loadSupplierDetail(row: SupplierRecord) {
  try {
    return await supplierApi.detail(row.id);
  } catch {
    return row;
  }
}

async function submitSupplier() {
  if (!canManageSuppliers.value) {
    MessagePlugin.warning('当前账号无供应商管理权限');
    return;
  }

  const validateResult = await supplierFormRef.value?.validate?.();
  if (validateResult !== true) return;
  const credentialValidationMessage = validateSupplierCredentialValues();
  if (credentialValidationMessage) {
    MessagePlugin.warning(credentialValidationMessage);
    return;
  }

  supplierSubmitting.value = true;
  try {
    const payload = buildSupplierPayload();
    if (editingSupplier.value?.id) {
      await supplierApi.update(editingSupplier.value.id, payload);
      MessagePlugin.success('提供商已更新');
    } else {
      await supplierApi.create(payload);
      MessagePlugin.success('提供商已创建');
    }
    supplierDialogVisible.value = false;
    await Promise.all([loadSupplierSummary(), loadSuppliers()]);
  } catch (error) {
    MessagePlugin.error(errorMessage(error, '保存提供商失败'));
  } finally {
    supplierSubmitting.value = false;
  }
}

async function handleToggleSupplier(row: SupplierRecord) {
  if (!canManageSuppliers.value) return;

  supplierActionLoading.value = row.id;
  try {
    await supplierApi.toggleStatus(row.id);
    MessagePlugin.success('状态已更新');
    await Promise.all([loadSupplierSummary(), loadSuppliers()]);
  } catch (error) {
    MessagePlugin.error(errorMessage(error, '更新状态失败'));
  } finally {
    supplierActionLoading.value = null;
  }
}

function handleDeleteSupplier(row: SupplierRecord) {
  if (!canManageSuppliers.value) return;

  const dialog = DialogPlugin.confirm({
    header: '删除提供商',
    body: `确认删除「${row.name || row.id}」吗？`,
    theme: 'warning',
    confirmBtn: '确认删除',
    cancelBtn: '取消',
    async onConfirm() {
      supplierActionLoading.value = row.id;
      try {
        await supplierApi.delete(row.id);
        MessagePlugin.success('提供商已删除');
        dialog.hide();
        await Promise.all([loadSupplierSummary(), loadSuppliers()]);
      } catch (error) {
        MessagePlugin.error(errorMessage(error, '删除提供商失败'));
      } finally {
        supplierActionLoading.value = null;
      }
    },
  });
}

function normalizeProviderTypeOptions(value: unknown): ProviderTypeRecord[] {
  const record = toPlainRecord(value);
  const rawItems = Array.isArray(value)
    ? value
    : Array.isArray(record.list)
      ? record.list
      : Array.isArray(record.options)
        ? record.options
        : Array.isArray(record.items)
          ? record.items
          : Array.isArray(record.types)
            ? record.types
            : Array.isArray(record.provider_types)
              ? record.provider_types
              : record.value
                ? [record]
                : Object.entries(record).map(([entryValue, entryLabel]) => ({ value: entryValue, label: entryLabel }));

  return rawItems
    .map((item) => {
      if (typeof item === 'string') {
        return { value: item, label: providerTypeFallbackLabels[item] || item };
      }

      const rec = toPlainRecord(item);
      const val = String(rec.value ?? rec.key ?? rec.type ?? rec.code ?? '').trim();
      if (!val) return null;

      const rawLabel = rec.label ?? rec.name ?? rec.title;
      const label = providerTypeFallbackLabels[val] || (typeof rawLabel === 'string' ? rawLabel : String(rawLabel || val));
      return { ...rec, value: val, label };
    })
    .filter((item): item is ProviderTypeRecord => !!item);
}

function handleSupplierProviderChange() {
  resetSupplierCredentialValues(editingSupplier.value);
}

function resetSupplierCredentialValues(source: SupplierRecord | null) {
  Object.keys(supplierCredentialValues).forEach((key) => {
    delete supplierCredentialValues[key];
  });
  Object.keys(supplierSecretEdited).forEach((key) => {
    delete supplierSecretEdited[key];
  });

  const providerConfig = toPlainRecord(source?.provider_config);
  const upstreamBinding = toPlainRecord(source?.upstream_binding);
  supplierCredentialFields.value.forEach((field) => {
    if (field.key === 'api_url') {
      supplierCredentialValues[field.key] = upstreamBinding.base_url || source?.api_url || field.default || '';
      return;
    }
    if (field.key === 'api_username') {
      supplierCredentialValues[field.key] = upstreamBinding.account_name || source?.api_username || field.default || '';
      return;
    }
    if (field.key === 'api_key') {
      supplierCredentialValues[field.key] = '';
      return;
    }
    supplierCredentialValues[field.key] = providerConfig[field.key] ?? field.default ?? defaultSupplierFieldValue(field);
  });
}

function defaultSupplierFieldValue(field: SupplierFormField) {
  if (field.type === 'switch' || field.type === 'boolean') return false;
  if (field.type === 'number') return null;
  return '';
}

function supplierFieldPlaceholder(field: SupplierFormField) {
  if (field.placeholder) return field.placeholder;
  if (field.secret && editingSupplier.value) return '留空则保持原值';
  return `请输入${field.label}`;
}

function hasExistingSupplierSecret(field: SupplierFormField) {
  if (!field.secret || !editingSupplier.value) return false;
  if (field.key === 'api_key') return Boolean(editingSupplier.value.has_api_key);
  return Boolean(editingSupplier.value.has_provider_secret_values?.[field.key]);
}

function secretSupplierFieldValue(field: SupplierFormField) {
  const value = supplierCredentialValues[field.key];
  return value === null || value === undefined ? '' : String(value);
}

function supplierSecretResetKey(field: SupplierFormField) {
  return `${editingSupplier.value?.id || 'new'}:${supplierForm.provider_key}:${field.key}`;
}

async function revealSupplierSecret(field: SupplierFormField) {
  if (!canRevealSupplierSecrets.value) return '';
  if (!editingSupplier.value?.id) return '';
  const response = await supplierApi.revealSecret(editingSupplier.value.id, field.key);
  return response.value;
}

function validateSupplierCredentialValues() {
  for (const field of supplierCredentialFields.value) {
    if (!field.required) continue;
    const value = supplierCredentialValues[field.key];
    const hasExistingSecret = hasExistingSupplierSecret(field);
    if (hasExistingSecret && String(value || '').trim() === '') continue;
    if (String(value ?? '').trim() === '') return `请填写${field.label}`;
  }
  return '';
}

function buildSupplierPayload(): SupplierUpsertPayload {
  const providerConfig: SupplierUpsertPayload['provider_config'] = {};
  const providerKey = String(supplierForm.provider_key || '').trim();
  const payload: SupplierUpsertPayload = {
    name: supplierForm.name,
    status: Number(supplierForm.status),
    api_url: '',
    api_username: '',
    api_key: '',
    provider_config: providerConfig,
    upstream_binding: {
      provider_key: providerKey,
      base_url: '',
      account_name: '',
    },
  };

  supplierCredentialFields.value.forEach((field) => {
    const value = field.secret && hasExistingSupplierSecret(field) && !supplierSecretEdited[field.key]
      ? ''
      : supplierCredentialValues[field.key];
    if (field.key === 'api_url') {
      payload.api_url = value;
      if (payload.upstream_binding) payload.upstream_binding.base_url = value;
      return;
    }
    if (field.key === 'api_username') {
      payload.api_username = value;
      if (payload.upstream_binding) payload.upstream_binding.account_name = value;
      return;
    }
    if (field.key === 'api_key') {
      payload.api_key = value;
      return;
    }
    providerConfig[field.key] = value;
  });

  return payload;
}

function normalizeSupplierFormFields(value: unknown): SupplierFormField[] {
  if (!Array.isArray(value)) return [];
  return value
    .map((item) => {
      const record = toPlainRecord(item);
      const key = String(record.key || '').trim();
      if (!key) return null;
      const label = String(record.label || record.title || key).trim();
      const type = normalizeSupplierFieldType(record.type);
      const options = Array.isArray(record.options)
        ? record.options.map((option) => {
            const optionRecord = toPlainRecord(option);
            return {
              label: String(optionRecord.label ?? optionRecord.name ?? optionRecord.value ?? '').trim(),
              value: (optionRecord.value ?? optionRecord.key ?? optionRecord.label ?? '') as string | number | boolean,
            };
          })
        : [];

      const field: SupplierFormField = {
        key,
        label,
        type,
        required: Boolean(record.required),
        secret: Boolean(record.secret),
        placeholder: String(record.placeholder || '').trim(),
        description: String(record.description || '').trim(),
        default: record.default,
        options,
      };

      return field;
    })
    .filter((item): item is SupplierFormField => Boolean(item));
}

function normalizeSupplierFieldType(value: unknown): SupplierFormField['type'] {
  const type = String(value || 'text').trim();
  if (['text', 'url', 'password', 'select', 'switch', 'boolean', 'number', 'textarea'].includes(type)) {
    return type as SupplierFormField['type'];
  }

  return 'text';
}

// --- Init ---
function loadSupplierTab() {
  void Promise.all([loadSupplierSummary(), loadProviderTypes(), loadSuppliers()]);
}

onMounted(loadSupplierTab);
</script>
