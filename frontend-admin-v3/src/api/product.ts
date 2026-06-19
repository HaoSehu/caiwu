import { request } from '@/utils/request';

import type { PagedListParams } from './types';

export interface ProductListParams extends PagedListParams {
  product_type?: string;
  first_product_group_id?: number | string;
  second_product_group_id?: number | string;
  third_product_group_id?: number | string;
  status?: number | string;
  [key: string]: unknown;
}

export interface ProductRecord {
  id: number | string;
  name?: string;
  display_name?: string;
  custom_display_name?: string;
  product_display_name?: string;
  product_spec_display?: string;
  cpu_memory_display?: string;
  combined_display_name?: string;
  description?: string;
  first_product_group_id?: number | string | null;
  first_product_group_code?: string;
  first_product_group_name?: string;
  second_product_group_id?: number | string | null;
  second_product_group_name?: string;
  third_product_group_id?: number | string | null;
  third_product_group_name?: string;
  effective_product_group_id?: number | string | null;
  effective_product_group_level?: number | string | null;
  effective_product_group_full_name?: string;
  service_type_code?: string | null;
  product_type?: string;
  product_type_label?: string;
  status?: number | string;
  status_label?: string;
  auto_setup?: number | string | boolean;
  monthly_price?: number | string;
  primary_price?: { cycle?: string; amount?: number | string };
  stock?: number | string;
  services_count?: number | string;
  active_services_count?: number | string;
  total_services_count?: number | string;
  pricing?: Record<string, number | string>;
  supplier_id?: number | string;
  supplier_product_id?: number | string;
  sort_order?: number | string;
  [key: string]: unknown;
}

export interface ProductTypeRecord {
  value: string;
  label: string;
  icon?: string;
  usage_count?: number;
  first_product_group_id?: number | string | null;
  first_product_group_code?: string;
  first_product_group_name?: string;
  is_hidden?: boolean;
  sort_order?: number;
  [key: string]: unknown;
}

export interface ProductCategoryRecord {
  id: number | string;
  name?: string;
  label?: string;
  product_type?: string;
  product_type_label?: string;
  first_product_group_id?: number | string | null;
  first_product_group_code?: string;
  first_product_group_name?: string;
  second_product_group_id?: number | string | null;
  second_product_group_name?: string;
  third_product_group_id?: number | string | null;
  third_product_group_name?: string | null;
  effective_product_group_id?: number | string | null;
  effective_product_group_level?: number | string | null;
  parent_id?: number | string | null;
  slogan?: string;
  is_visible?: number | string | boolean;
  children?: ProductCategoryRecord[];
  children_count?: number | string;
  sort_order?: number | string;
  product_count?: number | string;
  [key: string]: unknown;
}

export interface ProductSummary {
  groups_total?: number;
  root_groups_total?: number;
  sub_groups_total?: number;
  products_total?: number;
  products_active?: number;
  products_low_stock?: number;
  [key: string]: unknown;
}

export const productApi = {
  summary: () => request.get<ProductSummary>({ url: '/admin/products/summary' }),
  list: (params: ProductListParams) =>
    request.get<{ list?: ProductRecord[]; total?: number; page?: number; page_size?: number }>({
      url: '/admin/products',
      params,
    }),
  detail: (id: number | string) => request.get<ProductRecord>({ url: `/admin/products/${id}` }),
  create: (data: Record<string, unknown>) => request.post({ url: '/admin/products', data }),
  update: (id: number | string, data: Record<string, unknown>) =>
    request.put({ url: `/admin/products/${id}`, data }),
  delete: (id: number | string) => request.delete({ url: `/admin/products/${id}` }),
  toggleStatus: (id: number | string) => request.post({ url: `/admin/products/${id}/toggle-status` }),
  reorderProduct: (data: Record<string, unknown>) =>
    request.post({ url: '/admin/products/reorder', data }),
  splitPreview: (data: Record<string, unknown>) =>
    request.post({ url: '/admin/products/split-preview', data }),
  splitProducts: (data: Record<string, unknown>) =>
    request.post({ url: '/admin/products/split', data }),
  batchUpdateCategory: (data: Record<string, unknown>) =>
    request.post({ url: '/admin/products/category/batch', data }),
  batchUpdateProvisionHostname: (data: Record<string, unknown>) =>
    request.post({ url: '/admin/products/provision-hostname/batch', data }),
  pullTrafficPackages: (data: Record<string, unknown>) =>
    request.post({ url: '/admin/products/traffic-packages/pull', data }),
  owners: (id: number | string, params?: Record<string, unknown>) =>
    request.get({ url: `/admin/products/${id}/owners`, params }),
  types: () => request.get<ProductTypeRecord[] | { list?: ProductTypeRecord[] }>({ url: '/admin/product-types' }),
  reorderTypes: (data: Record<string, unknown>) => request.post({ url: '/admin/product-types/reorder', data }),
  createType: (data: Record<string, unknown>) => request.post({ url: '/admin/product-types', data }),
  updateType: (value: number | string, data: Record<string, unknown>) =>
    request.put({ url: `/admin/product-types/${value}`, data }),
  deleteType: (value: number | string) => request.delete({ url: `/admin/product-types/${value}` }),
  categories: (params?: Record<string, unknown>) =>
    request.get<{ tree?: ProductCategoryRecord[]; list?: ProductCategoryRecord[] }>({
      url: '/admin/product-categories',
      params,
    }),
  createCategory: (data: Record<string, unknown>) =>
    request.post({ url: '/admin/product-categories', data }),
  updateCategory: (id: number | string, data: Record<string, unknown>) =>
    request.put({ url: `/admin/product-categories/${id}`, data }),
  deleteCategory: (id: number | string, params?: Record<string, unknown>) =>
    request.delete({ url: `/admin/product-categories/${id}`, params }),
  reorderCategory: (data: Record<string, unknown>) =>
    request.post({ url: '/admin/product-categories/reorder', data }),
};
