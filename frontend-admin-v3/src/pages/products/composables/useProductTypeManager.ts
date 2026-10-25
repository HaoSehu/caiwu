import { DialogPlugin } from 'tdesign-vue-next/es/dialog/plugin';
import { MessagePlugin } from 'tdesign-vue-next/es/message/plugin';
import { computed, reactive, ref } from 'vue';

import type { ProductTypeRecord } from '@/api/product';
import { productApi } from '@/api/product';

import { errorMessage } from './useProductShared';

/**
 * 一级分类（product types）管理逻辑。
 *
 * 从 ProductCatalog.vue 抽离：列表加载、图标名称归一、新建/编辑/显隐/排序/删除。
 * 宿主传入分类筛选对象与目录刷新函数，保持原有请求与提示文案不变。
 */

export interface ProductTypeManagerFilters {
  product_type: string;
  product_group_key: string;
  lifecycle_status?: string;
}

export interface ProductTypeManagerOptions {
  /** 目录筛选状态（一级分类被删除时需清空对应筛选） */
  catalogFilters: ProductTypeManagerFilters;
  /** 分类/商品目录刷新（refreshTypeCatalog 内部调用） */
  reloadCatalogData: () => Promise<unknown> | unknown;
}

export const businessProductTypeOptions = [
  { value: 'cloud_server', label: '云服务器 (cloud_server)' },
  { value: 'game_cloud', label: '游戏云 (game_cloud)' },
  { value: 'cloud_desktop', label: '云电脑 (cloud_desktop)' },
  { value: 'bare_metal', label: '裸金属 (bare_metal)' },
  { value: 'cdn', label: 'CDN' },
  { value: 'other', label: '其他 (other)' },
  { value: 'physical_machine', label: '物理机 (physical_machine)' },
  { value: 'web_hosting', label: '虚拟主机 (web_hosting)' },
];

export const businessProductTypeValueSet = new Set(businessProductTypeOptions.map((item) => item.value));

export const productTypeIconOptions = [
  { value: 'server', label: '服务器', aliases: ['vps', 'Server'] },
  { value: 'cloud', label: '云服务', aliases: ['cloud'] },
  { value: 'control-platform', label: '平台', aliases: ['Platform'] },
  { value: 'building', label: '机房', aliases: ['dedicated', 'OfficeBuilding'] },
  { value: 'desktop', label: '主机', aliases: ['hosting', 'Monitor'] },
  { value: 'internet', label: '网络', aliases: ['cdn'] },
  { value: 'hard-disk-storage', label: '存储', aliases: ['storage'] },
  { value: 'data-base', label: '数据库', aliases: ['database', 'db'] },
  { value: 'link', label: '域名', aliases: ['domain', 'Link'] },
  { value: 'lock-on', label: '安全', aliases: ['ssl'] },
  { value: 'component-grid', label: '应用', aliases: ['Grid', 'other'] },
  { value: 'tools', label: '工具', aliases: ['tool'] },
  { value: 'shop', label: '商品', aliases: ['product'] },
] satisfies Array<{ value: string; label: string; aliases?: string[] }>;

export function resolveProductTypeIconName(value: unknown) {
  const rawValue = String(value || '').trim();
  if (!rawValue) return '';

  const normalizedRawValue = rawValue.toLowerCase();
  const matched = productTypeIconOptions.find((item) => {
    if (item.value.toLowerCase() === normalizedRawValue) return true;
    return item.aliases?.some((alias) => alias.toLowerCase() === normalizedRawValue);
  });

  return matched?.value || rawValue;
}

export function productTypeIconDisplayLabel(value: unknown) {
  const iconName = resolveProductTypeIconName(value);
  const matched = productTypeIconOptions.find((item) => item.value === iconName);
  return matched?.label || iconName;
}

export function normalizeBusinessProductType(value?: string) {
  const normalized = String(value || '').trim();
  return businessProductTypeValueSet.has(normalized) ? normalized : 'other';
}

export function useProductTypeManager(options: ProductTypeManagerOptions) {
  const typeLoading = ref(false);
  const typeSubmitting = ref(false);
  const productTypes = ref<ProductTypeRecord[]>([]);
  /** 一级分类下拉请求序号，用于丢弃过期响应 */
  let productTypeRequestVersion = 0;

  const typeManagerProductCount = computed(() =>
    productTypes.value.reduce((total, item) => total + (Number(item.usage_count || 0) || 0), 0),
  );
  const typeManagerHiddenCount = computed(() => productTypes.value.filter((item) => item.is_hidden).length);

  async function loadProductTypes() {
    // 请求竞态防护：刷新分类期间只允许最后一次请求回写结果（docs/FRONTEND.md）
    const requestVersion = ++productTypeRequestVersion;
    typeLoading.value = true;
    try {
      const response = await productApi.types();
      if (requestVersion !== productTypeRequestVersion) return;
      productTypes.value = Array.isArray(response) ? response : response.list || [];
      if (!options.catalogFilters.product_type && productTypes.value[0]?.value) {
        options.catalogFilters.product_type = productTypes.value[0].value;
      }
    } catch (error) {
      if (requestVersion !== productTypeRequestVersion) return;
      MessagePlugin.error(errorMessage(error, '加载一级分类失败'));
    } finally {
      if (requestVersion === productTypeRequestVersion) typeLoading.value = false;
    }
  }

  async function refreshTypeCatalog() {
    await loadProductTypes();
    await options.reloadCatalogData();
  }

  /**
   * 保存一级分类：value 为空表示新建，否则更新。
   */
  async function submitType(payload: { value: string; label: string; product_type: string; icon: string }) {
    const label = payload.label.trim();
    if (!label) {
      MessagePlugin.warning('请输入一级分类名称');
      return false;
    }

    typeSubmitting.value = true;
    try {
      const icon = resolveProductTypeIconName(payload.icon);
      const productType = normalizeBusinessProductType(payload.product_type);
      if (payload.value) {
        await productApi.updateType(payload.value, { label, product_type: productType, icon });
        MessagePlugin.success('一级分类已更新');
      } else {
        await productApi.createType({ label, product_type: productType, icon });
        MessagePlugin.success('一级分类已创建');
      }
      await refreshTypeCatalog();
      return true;
    } catch (error) {
      MessagePlugin.error(errorMessage(error, '保存一级分类失败'));
      return false;
    } finally {
      typeSubmitting.value = false;
    }
  }

  async function toggleTypeHidden(type: ProductTypeRecord) {
    typeSubmitting.value = true;
    try {
      await productApi.updateType(type.value, {
        label: type.label,
        product_type: normalizeBusinessProductType(type.product_type),
        icon: type.icon || '',
        is_hidden: !type.is_hidden,
      });
      MessagePlugin.success(type.is_hidden ? '一级分类已显示' : '一级分类已隐藏');
      await refreshTypeCatalog();
    } catch (error) {
      MessagePlugin.error(errorMessage(error, '更新一级分类失败'));
    } finally {
      typeSubmitting.value = false;
    }
  }

  async function moveType(value: string, direction: -1 | 1) {
    const index = productTypes.value.findIndex((item) => item.value === value);
    const targetIndex = index + direction;
    if (index < 0 || targetIndex < 0 || targetIndex >= productTypes.value.length) return;
    const nextTypes = [...productTypes.value];
    const [current] = nextTypes.splice(index, 1);
    nextTypes.splice(targetIndex, 0, current);

    typeSubmitting.value = true;
    try {
      await productApi.reorderTypes({ values: nextTypes.map((item) => item.value) });
      productTypes.value = nextTypes;
      MessagePlugin.success('一级分类排序已更新');
    } catch (error) {
      MessagePlugin.error(errorMessage(error, '更新一级分类排序失败'));
      await loadProductTypes();
    } finally {
      typeSubmitting.value = false;
    }
  }

  /**
   * 删除一级分类：确认后删除并刷新目录。
   * onDeleted 用于宿主在删除成功后清理编辑表单。
   */
  function deleteType(type: ProductTypeRecord, onDeleted?: () => void) {
    const dialog = DialogPlugin.confirm({
      header: '删除一级分类',
      body: `确认删除「${type.label || type.value}」吗？`,
      theme: 'warning',
      confirmBtn: { content: '确定', theme: 'danger' },
      cancelBtn: '取消',
      async onConfirm() {
        typeSubmitting.value = true;
        try {
          await productApi.deleteType(type.value);
          MessagePlugin.success('一级分类已删除');
          if (false && options.catalogFilters.lifecycle_status === 'all') {
            MessagePlugin.info('当前筛选为“全部商品”，已删除商品仍会显示在列表中');
          }
          dialog.hide();
          if (options.catalogFilters.product_type === type.value) {
            options.catalogFilters.product_type = '';
            options.catalogFilters.product_group_key = '';
          }
          onDeleted?.();
          await refreshTypeCatalog();
        } catch (error) {
          MessagePlugin.error(errorMessage(error, '删除一级分类失败'));
        } finally {
          typeSubmitting.value = false;
        }
      },
    });
  }

  return {
    productTypes,
    typeLoading,
    typeSubmitting,
    typeManagerProductCount,
    typeManagerHiddenCount,
    loadProductTypes,
    refreshTypeCatalog,
    submitType,
    toggleTypeHidden,
    moveType,
    deleteType,
  };
}

/** 一级分类编辑表单（对话框内部持有） */
export interface ProductTypeForm {
  label: string;
  product_type: string;
  icon: string;
}

export function createProductTypeForm(): ProductTypeForm {
  return reactive<ProductTypeForm>({
    label: '',
    product_type: 'cloud_server',
    icon: '',
  });
}
