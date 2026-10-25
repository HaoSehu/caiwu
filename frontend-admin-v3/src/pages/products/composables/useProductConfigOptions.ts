import { MessagePlugin } from 'tdesign-vue-next/es/message/plugin';
import { reactive, ref } from 'vue';

import { supplierApi } from '@/api/supplier';

import { errorMessage, toPlainRecord } from './useProductShared';

/**
 * 商品配置项（config_options）编辑逻辑。
 *
 * 从 ProductCatalog.vue 抽离：对话框状态、子项表格行、参数/子项互转与保存、拉取上游模板。
 * 宿主只需传入持有 config_options 的商品表单对象，读写的仍是同一个响应式对象。
 */

export interface ProductConfigOptionRecord {
  uid?: string;
  field: string;
  name: string;
  option_name?: string;
  option_mode: string;
  parameter?: string;
  sub?: Array<Record<string, unknown>>;
  sub_items?: Array<Record<string, unknown>>;
  required?: boolean;
  hidden?: boolean;
  sort_order?: number;
  [key: string]: unknown;
}

export interface ConfigOptionSubItemFormRow {
  uid: string;
  name: string;
  value: string;
  monthly_price: string;
  sort_order: number;
}

/** 宿主商品表单中配置项相关的部分（productForm） */
export interface ProductConfigOptionsHost {
  supplier_id: number | string;
  upstream_product_id: number | string;
  config_options: ProductConfigOptionRecord[];
}

export function normalizeConfigOptions(value: unknown): ProductConfigOptionRecord[] {
  const items = Array.isArray(value) ? value : [];
  return items.map((itemValue, index) => {
    const item = toPlainRecord(itemValue);
    const field = String(item.field || item.spec_key || `option_${index + 1}`).trim();
    const name = String(item.name || item.option_name || field).trim();
    return {
      ...item,
      uid: String(item.uid || `${field}-${index}`),
      field,
      name,
      option_name: name,
      option_mode: String(item.option_mode || (item.option_type === 'quantity' ? 'range' : 'select')),
      parameter: String(item.parameter || ''),
      sub: Array.isArray(item.sub) ? (item.sub as Array<Record<string, unknown>>) : [],
      sub_items: Array.isArray(item.sub_items)
        ? (item.sub_items as Array<Record<string, unknown>>)
        : Array.isArray(item.sub)
          ? (item.sub as Array<Record<string, unknown>>)
          : [],
      required: Boolean(item.required ?? true),
      hidden: Boolean(item.hidden ?? false),
      sort_order: Number(item.sort_order || index + 1),
    };
  });
}

export function serializeConfigOptions(options: ProductConfigOptionRecord[]) {
  return options.map((item, index) => ({
    ...item,
    name: String(item.name || item.option_name || item.field || '').trim(),
    option_name: String(item.option_name || item.name || item.field || '').trim(),
    field: String(item.field || '').trim(),
    option_mode: String(item.option_mode || 'select'),
    parameter: String(item.parameter || '').trim(),
    required: Boolean(item.required),
    hidden: Boolean(item.hidden),
    sort_order: Number(item.sort_order || index + 1),
  }));
}

export function splitConfigSubItemText(rawValue: string) {
  const tokens = rawValue
    .split(',')
    .map((item) => item.trim())
    .filter(Boolean);
  const items: string[] = [];
  let buffer: string[] = [];

  tokens.forEach((token) => {
    buffer.push(token);
    if (token.includes('|')) {
      items.push(buffer.join(','));
      buffer = [];
    }
  });

  if (buffer.length) {
    if (items.length === 0) return tokens;
    items.push(buffer.join(','));
  }

  return items;
}

export function parseConfigSubItems(rawValue: string) {
  return splitConfigSubItemText(rawValue).map((item, index) => {
    const separatorIndex = item.indexOf('|');
    const rawValue = separatorIndex >= 0 ? item.slice(0, separatorIndex).trim() : item.trim();
    const rawLabel = separatorIndex >= 0 ? item.slice(separatorIndex + 1).trim() : '';
    const value = rawValue || rawLabel || '';
    const label = rawLabel || rawValue || '';
    return {
      id: value || index,
      value: value || label,
      label: label || value,
      option_name: label || value,
      option_name_first: value || label,
      pricing: {},
      sort_order: index + 1,
      hidden: 0,
    };
  });
}

export function formatConfigSubItems(items: Array<Record<string, unknown>> = []) {
  return items
    .map(
      (item) =>
        `${item.value || item.option_name_first || item.label || item.option_name || ''}|${item.label || item.option_name || item.value || ''}`,
    )
    .join(',');
}

export function createConfigSubItemRow(item: Record<string, unknown> = {}, index = 0): ConfigOptionSubItemFormRow {
  const pricing = toPlainRecord(item.pricing);
  const monthlyPrice = item.monthly_price ?? item.monthly ?? item.month ?? pricing.monthly ?? pricing.month ?? '';
  return {
    uid: String(item.uid || `config-subitem-${Date.now()}-${index}-${Math.random().toString(36).slice(2)}`),
    name: String(item.label || item.option_name || item.name || '').trim(),
    value: String(item.value || item.option_name_first || '').trim(),
    monthly_price:
      monthlyPrice === '' || monthlyPrice === undefined || monthlyPrice === null ? '0.00' : String(monthlyPrice),
    sort_order: Number(item.sort_order || index + 1),
  };
}

export function useProductConfigOptions(host: ProductConfigOptionsHost) {
  const configOptionDialogVisible = ref(false);
  const configOptionSubmitting = ref(false);
  const configOptionEditingIndex = ref(-1);
  const configTemplateLoading = ref(false);
  const configOptionForm = reactive({
    name: '',
    field: '',
    option_mode: 'select',
    parameter: '',
    sub_items_text: '',
    description: '',
    suffix_text: '',
    advanced: true,
    required: true,
    hidden: false,
    sort_order: 0,
  });
  const configOptionSubItemRows = ref<ConfigOptionSubItemFormRow[]>([]);

  function resetConfigOptionForm() {
    Object.assign(configOptionForm, {
      name: '',
      field: '',
      option_mode: 'select',
      parameter: '',
      sub_items_text: '',
      description: '',
      suffix_text: '',
      advanced: true,
      required: true,
      hidden: false,
      sort_order: host.config_options.length + 1,
    });
    configOptionSubItemRows.value = [createConfigSubItemRow({}, 0)];
  }

  function configSubItemRecordsFromParameter(rawValue: string): Array<Record<string, unknown>> {
    return parseConfigSubItems(rawValue);
  }

  function addConfigSubItemRow() {
    configOptionSubItemRows.value.push(createConfigSubItemRow({}, configOptionSubItemRows.value.length));
  }

  function removeConfigSubItemRow(index: number) {
    if (configOptionSubItemRows.value.length <= 1) return;
    configOptionSubItemRows.value.splice(index, 1);
  }

  function handleConfigOptionModeChange(value: string | number) {
    configOptionForm.option_mode = String(value || 'select');
    if (configOptionForm.option_mode === 'select' && configOptionSubItemRows.value.length === 0) {
      configOptionSubItemRows.value = [createConfigSubItemRow({}, 0)];
    }
  }

  function openConfigOptionDialog(row?: ProductConfigOptionRecord, index = -1) {
    configOptionEditingIndex.value = index;
    if (row) {
      Object.assign(configOptionForm, {
        name: row.name || row.option_name || '',
        field: row.field || '',
        option_mode: row.option_mode || 'select',
        parameter: row.parameter || '',
        sub_items_text: formatConfigSubItems(row.sub_items || row.sub || []),
        description: String(row.description || row.option_description || '').trim(),
        suffix_text: String(row.suffix_text || row.suffix || '').trim(),
        advanced: Boolean(row.advanced ?? true),
        required: Boolean(row.required ?? true),
        hidden: Boolean(row.hidden ?? false),
        sort_order: Number(row.sort_order || index + 1),
      });
      const sourceSubItems =
        Array.isArray(row.sub_items) && row.sub_items.length ? row.sub_items : Array.isArray(row.sub) ? row.sub : [];
      const rowSubItems = sourceSubItems.length
        ? sourceSubItems.map((item) => toPlainRecord(item))
        : configSubItemRecordsFromParameter(String(row.parameter || ''));
      configOptionSubItemRows.value = rowSubItems.length
        ? rowSubItems.map((item, itemIndex) => createConfigSubItemRow(item, itemIndex))
        : [createConfigSubItemRow({}, 0)];
    } else {
      resetConfigOptionForm();
    }
    configOptionDialogVisible.value = true;
  }

  function buildConfigSubItemsFromRows() {
    const rows = configOptionSubItemRows.value
      .map((row, index) => ({
        ...row,
        name: row.name.trim(),
        value: row.value.trim(),
        sort_order: Number(row.sort_order || index + 1),
        monthly_price: String(row.monthly_price ?? '').trim(),
      }))
      .filter((row) => row.name || row.value);

    const invalidRow = rows.find((row) => !row.name || !row.value);
    if (invalidRow) {
      throw new Error('请完整填写子项名称和传参值');
    }

    return rows.map((row, index) => {
      const monthlyPrice = Number(row.monthly_price || 0);
      const monthlyAmount = Number.isFinite(monthlyPrice) ? monthlyPrice.toFixed(2) : '0.00';
      return {
        id: row.value || index,
        value: row.value,
        label: row.name,
        option_name: row.name,
        option_name_first: row.value,
        monthly: monthlyAmount,
        monthly_price: monthlyAmount,
        pricing: {
          monthly: monthlyAmount,
        },
        sort_order: row.sort_order || index + 1,
        hidden: 0,
      };
    });
  }

  function submitConfigOption() {
    const name = configOptionForm.name.trim();
    const field = configOptionForm.field.trim();
    if (!name || !field) {
      MessagePlugin.warning('请填写配置名称和字段名');
      return;
    }
    let subItems: ReturnType<typeof parseConfigSubItems> = [];
    try {
      subItems = configOptionForm.option_mode === 'select' ? buildConfigSubItemsFromRows() : [];
    } catch (error) {
      MessagePlugin.warning(errorMessage(error, '请检查子项配置'));
      return;
    }
    const parameter = subItems.length
      ? subItems.map((item) => `${item.value}|${item.label}`).join(',')
      : configOptionForm.parameter.trim();
    if (configOptionForm.option_mode === 'select' && !parameter) {
      MessagePlugin.warning('请填写参数值或子项');
      return;
    }

    configOptionSubmitting.value = true;
    const payload: ProductConfigOptionRecord = {
      uid: `${field}-${Date.now()}`,
      source: 'manual',
      field,
      name,
      option_name: name,
      option_mode: configOptionForm.option_mode,
      option_type: configOptionForm.option_mode === 'range' ? 'quantity' : 'select',
      parameter,
      sub: subItems,
      sub_items: subItems,
      range_pricing: [],
      description: configOptionForm.description.trim(),
      suffix_text: configOptionForm.suffix_text.trim(),
      advanced: Boolean(configOptionForm.advanced),
      required: Boolean(configOptionForm.required),
      hidden: Boolean(configOptionForm.hidden),
      sort_order: Number(configOptionForm.sort_order || host.config_options.length + 1),
    };
    if (configOptionEditingIndex.value >= 0) {
      host.config_options.splice(configOptionEditingIndex.value, 1, payload);
    } else {
      host.config_options.push(payload);
    }
    configOptionDialogVisible.value = false;
    configOptionSubmitting.value = false;
  }

  function removeConfigOption(index: number) {
    host.config_options.splice(index, 1);
  }

  async function pullProductConfigTemplate() {
    const supplierId = host.supplier_id;
    const upstreamProductId = host.upstream_product_id;
    if (!supplierId || !upstreamProductId) {
      MessagePlugin.warning('请先绑定提供商和提供商商品 ID');
      return;
    }

    configTemplateLoading.value = true;
    try {
      const response = await supplierApi.productConfigTemplate(supplierId, upstreamProductId);
      const options = Array.isArray(response.config_options) ? response.config_options : [];
      host.config_options = normalizeConfigOptions(options);
      MessagePlugin.success('配置项模板已拉取');
    } catch (error) {
      MessagePlugin.error(errorMessage(error, '拉取配置项模板失败'));
    } finally {
      configTemplateLoading.value = false;
    }
  }

  return {
    configOptionDialogVisible,
    configOptionSubmitting,
    configOptionEditingIndex,
    configTemplateLoading,
    configOptionForm,
    configOptionSubItemRows,
    resetConfigOptionForm,
    formatConfigSubItems,
    createConfigSubItemRow,
    addConfigSubItemRow,
    removeConfigSubItemRow,
    handleConfigOptionModeChange,
    openConfigOptionDialog,
    splitConfigSubItemText,
    parseConfigSubItems,
    buildConfigSubItemsFromRows,
    submitConfigOption,
    removeConfigOption,
    pullProductConfigTemplate,
  };
}
