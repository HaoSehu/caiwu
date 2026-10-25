import { MessagePlugin } from 'tdesign-vue-next/es/message/plugin';
import { computed, ref } from 'vue';

import type { ProviderTypeRecord, SupplierFormField, SupplierRecord } from '@/api/supplier';
import { supplierApi } from '@/api/supplier';

import {
  errorMessage,
  mergeProviderTypeOptions,
  normalizeProviderTypeOptions,
  providerTypeFallbackLabels,
  providerTypeLabel,
  toPlainRecord,
} from './useProductShared';

/**
 * 商品编辑抽屉里的供应商 / 上游商品绑定逻辑。
 *
 * 从 ProductCatalog.vue 抽离：供应商选项、提供商类型、上游商品同步与级联选项。
 * 宿主只需传入商品表单中与上游绑定相关的字段，读写的仍是同一个响应式对象。
 */

export interface SupplierBatchProduct {
  id: number;
  name: string;
  type_label: string;
  remote_group_name: string;
  is_connected: boolean;
  connected_display_name: string;
  [key: string]: unknown;
}

/** 宿主商品表单中与上游绑定相关的部分（productForm） */
export interface SupplierBindingHost {
  supplier_id: number | string;
  upstream_product_id: number | string;
}

export function useSupplierBinding(host: SupplierBindingHost) {
  const productSupplierOptions = ref<SupplierRecord[]>([]);
  const productSupplierLoading = ref(false);
  const supplierProductOptions = ref<SupplierBatchProduct[]>([]);
  const supplierProductLoading = ref(false);
  const providerTypes = ref<ProviderTypeRecord[]>([]);

  const providerTypeOptions = computed(() => {
    return mergeProviderTypeOptions(providerTypes.value);
  });

  const supplierProductCascaderOptions = computed(() => {
    const groups = new Map<string, SupplierBatchProduct[]>();
    supplierProductOptions.value.forEach((item) => {
      const groupName = String(item.remote_group_name || item.type_label || '默认分组');
      const list = groups.get(groupName) || [];
      list.push(item);
      groups.set(groupName, list);
    });

    return Array.from(groups.entries()).map(([groupName, items], index) => ({
      label: groupName,
      value: `supplier-product-group-${index}`,
      children: items.map((item) => ({
        label: supplierProductOptionLabel(item),
        value: item.id,
      })),
    }));
  });

  function supplierOptionLabel(row: SupplierRecord) {
    const typeLabel = supplierInterfaceTypeLabel(row);
    return typeLabel ? `${row.name || row.id} / ${typeLabel}` : String(row.name || row.id || '-');
  }

  function supplierInterfaceTypeLabel(row: SupplierRecord) {
    const upstreamBinding = toPlainRecord(row.upstream_binding);
    const rawType = String(upstreamBinding.provider_key || row.provider_key || '').trim();
    if (rawType && providerTypeFallbackLabels[rawType]) return providerTypeFallbackLabels[rawType];
    return row.provider_label || providerTypeLabel(rawType, providerTypeOptions.value);
  }

  function supplierProductOptionLabel(row: SupplierBatchProduct) {
    const typeLabel = row.type_label || row.remote_group_name || '';
    return typeLabel ? `${row.name || row.id} · ${typeLabel}` : String(row.name || row.id || '-');
  }

  function canSupplierBatchConnect(row: SupplierRecord) {
    const upstreamBinding = toPlainRecord(row.upstream_binding);
    const providerKey = String(upstreamBinding.provider_key || row.provider_key || '').trim();
    const schema = providerTypeOptions.value.find((item) => item.value === providerKey)?.supplier_form;
    const fields = normalizeSupplierCredentialFields(toPlainRecord(schema).fields);
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
      if (field.key === 'api_key')
        return Boolean(toPlainRecord(upstreamBinding.has_secret_values).api_key || row.has_api_key || row.api_key);
      if (field.secret) {
        return Boolean(
          toPlainRecord(upstreamBinding.has_secret_values)[field.key] || row.has_provider_secret_values?.[field.key],
        );
      }

      return hasSupplierCredentialValue(toPlainRecord(row.provider_config)[field.key]);
    });
  }

  function normalizeSupplierCredentialFields(value: unknown): SupplierFormField[] {
    if (!Array.isArray(value)) return [];

    return value
      .map((item) => {
        const record = toPlainRecord(item);
        const key = String(record.key || '').trim();
        if (!key) return null;

        const type = normalizeSupplierCredentialFieldType(record.type);
        const field: SupplierFormField = {
          key,
          label: String(record.label || record.title || key).trim(),
          type,
          required: Boolean(record.required),
          secret: Boolean(record.secret),
        };

        return field;
      })
      .filter((item): item is SupplierFormField => Boolean(item));
  }

  function normalizeSupplierCredentialFieldType(value: unknown): SupplierFormField['type'] {
    const type = String(value || 'text').trim();
    if (['text', 'url', 'password', 'select', 'switch', 'boolean', 'number', 'textarea'].includes(type)) {
      return type as SupplierFormField['type'];
    }

    return 'text';
  }

  function hasSupplierCredentialValue(value: unknown) {
    if (typeof value === 'boolean') return true;
    if (typeof value === 'number') return Number.isFinite(value);
    return String(value ?? '').trim() !== '';
  }

  function handleProductSupplierChange(value: string | number) {
    host.supplier_id = value || '';
    host.upstream_product_id = '';
    supplierProductOptions.value = [];
    if (value) {
      void loadProductSupplierProducts(value, true);
    }
  }

  async function loadProductSupplierProducts(supplierId: string | number, notify = false) {
    if (!supplierId) return;
    await ensureProviderTypes();

    const supplier = productSupplierOptions.value.find((item) => String(item.id) === String(supplierId));
    if (supplier && !canSupplierBatchConnect(supplier)) {
      supplierProductOptions.value = [];
      MessagePlugin.warning('接口配置不完整，暂时无法同步上游商品');
      return;
    }

    supplierProductLoading.value = true;
    try {
      const response = await supplierApi.products(supplierId, { silent: true });
      supplierProductOptions.value = buildSupplierBatchProducts(response);
      if (host.upstream_product_id) {
        const hasCurrent = supplierProductOptions.value.some(
          (item) => String(item.id) === String(host.upstream_product_id),
        );
        if (!hasCurrent) {
          supplierProductOptions.value.unshift({
            id: Number(host.upstream_product_id),
            name: `已绑定商品 #${host.upstream_product_id}`,
            type_label: '当前绑定',
            remote_group_name: '',
            is_connected: true,
            connected_display_name: '',
          });
        }
      }
      if (notify) MessagePlugin.success('上游商品已同步');
    } catch (error) {
      supplierProductOptions.value = [];
      MessagePlugin.error(errorMessage(error, '同步上游商品失败'));
    } finally {
      supplierProductLoading.value = false;
    }
  }

  function normalizeSupplierBatchProduct(itemValue: unknown): SupplierBatchProduct {
    const item = toPlainRecord(itemValue);
    return {
      ...item,
      id: Number(item.id || item.product_id || 0),
      name: String(item.name || item.product_name || '').trim(),
      type_label: String(item.type_label || item.type_name || item.type || item.billingcycle || '').trim(),
      remote_group_name: String(
        item.remote_group_name || item.group_name || item.second_group_name || item._group_label || '',
      ).trim(),
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
    return groups
      .flatMap((groupValue) => {
        const group = toPlainRecord(groupValue);
        const groupLabel = String(group.label || group.name || '').trim();
        const items = Array.isArray(group.items) ? group.items : [];
        return items.map((item) => normalizeSupplierBatchProduct({ ...toPlainRecord(item), _group_label: groupLabel }));
      })
      .filter((item) => item.id > 0);
  }

  async function loadProviderTypes() {
    try {
      const response = await supplierApi.providerTypes();
      providerTypes.value = normalizeProviderTypeOptions(response);
    } catch {
      providerTypes.value = [];
    }
  }

  async function ensureProviderTypes() {
    if (providerTypes.value.length) return;
    await loadProviderTypes();
  }

  return {
    productSupplierOptions,
    productSupplierLoading,
    supplierProductOptions,
    supplierProductLoading,
    providerTypes,
    providerTypeOptions,
    supplierProductCascaderOptions,
    supplierOptionLabel,
    supplierInterfaceTypeLabel,
    supplierProductOptionLabel,
    canSupplierBatchConnect,
    handleProductSupplierChange,
    loadProductSupplierProducts,
    normalizeSupplierBatchProduct,
    buildSupplierBatchProducts,
    loadProviderTypes,
    ensureProviderTypes,
  };
}
