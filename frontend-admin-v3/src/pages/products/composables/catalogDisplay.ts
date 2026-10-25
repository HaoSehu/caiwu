import type { ProductCategoryRecord, ProductRecord } from '@/api/product';

import { productGroupEffectiveId } from './useProductShared';

/** 数量字段归一化：非有限数或非正数一律归零，避免表格中出现 NaN / 负数。 */
export function countValue(value: unknown): number {
  const count = Number(value);
  return Number.isFinite(count) && count > 0 ? count : 0;
}

export function categoryDisplayName(row: ProductCategoryRecord): string {
  return String(row.name || row.label || `分类 #${row.id}`).trim();
}

export function categoryActiveProductCount(row: ProductCategoryRecord): number {
  return countValue(row.products_count ?? row.product_count);
}

export function categoryTotalProductCount(row: ProductCategoryRecord): number {
  const total = countValue(row.products_with_trashed_count);
  return total > 0 ? total : categoryActiveProductCount(row);
}

export function isProductVisible(row: ProductRecord): boolean {
  return Number(row.status) === 1;
}

export function productVisibilityLabel(row: ProductRecord): string {
  if (row.is_deleted) return '已删除';
  return isProductVisible(row) ? '显示中' : '已隐藏';
}

export function productVisibilityTheme(row: ProductRecord): 'success' | 'default' | 'danger' {
  if (row.is_deleted) return 'danger';
  return isProductVisible(row) ? 'success' : 'default';
}

export function textValue(value: unknown): string {
  return String(value ?? '').trim();
}

export function productSpecDisplayName(row: ProductRecord): string {
  return (
    [
      row.product_display_name,
      row.display_name,
      row.name,
      row.combined_display_name,
      row.custom_display_name,
      row.product_spec_display,
      row.cpu_memory_display,
    ]
      .map(textValue)
      .find(Boolean) || '-'
  );
}

export function normalizeCategoryScopeValue(value: unknown): string {
  return value === undefined || value === null || value === '' ? '' : String(value);
}

export function categoryParentOptionLabel(item: ProductCategoryRecord): string {
  return String(
    item.second_product_group_name || item.name || item.label || `二级分类 #${productGroupEffectiveId(item)}`,
  ).trim();
}
