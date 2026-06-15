import { request } from '@/utils/request';

export interface ProductListParams {
  keyword?: string;
  product_type?: string;
  category_id?: number | string;
  status?: number | string;
  page?: number;
  page_size?: number;
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
  product_group_id?: number | string;
  category_id?: number | string;
  group_id?: number | string;
  product_type?: string;
  product_type_label?: string;
  group_name?: string;
  category_name?: string;
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
  is_hidden?: boolean;
  sort_order?: number;
  [key: string]: unknown;
}

export interface ProductCategoryRecord {
  id: number | string;
  name?: string;
  label?: string;
  product_type?: string;
  parent_id?: number | string | null;
  slogan?: string;
  is_visible?: number | string | boolean;
  children?: ProductCategoryRecord[];
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

function normalizeSharedProductParams(payload: Record<string, unknown> = {}) {
  const next = { ...payload };
  if (next.type !== undefined && next.product_type === undefined) {
    next.product_type = next.type;
    delete next.type;
  }
  return next;
}

function normalizeCategoryParams(payload: Record<string, unknown> = {}) {
  const next = normalizeSharedProductParams(payload);
  if (next.parent_category_id !== undefined && next.parent_id === undefined) {
    next.parent_id = next.parent_category_id;
    delete next.parent_category_id;
  }
  if (next.target_parent_category_id !== undefined && next.target_parent_id === undefined) {
    next.target_parent_id = next.target_parent_category_id;
    delete next.target_parent_category_id;
  }
  return next;
}

export const productApi = {
  summary: () => request.get<ProductSummary>({ url: '/admin/products/summary' }),
  list: (params: ProductListParams) =>
    request.get<{ list?: ProductRecord[]; total?: number; page?: number; page_size?: number }>({
      url: '/admin/products',
      params: normalizeSharedProductParams(params),
    }),
  detail: (id: number | string) => request.get<ProductRecord>({ url: `/admin/products/${id}` }),
  create: (data: Record<string, unknown>) => request.post({ url: '/admin/products', data: normalizeSharedProductParams(data) }),
  update: (id: number | string, data: Record<string, unknown>) =>
    request.put({ url: `/admin/products/${id}`, data: normalizeSharedProductParams(data) }),
  delete: (id: number | string) => request.delete({ url: `/admin/products/${id}` }),
  toggleStatus: (id: number | string) => request.post({ url: `/admin/products/${id}/toggle-status` }),
  reorderProduct: (data: Record<string, unknown>) =>
    request.post({ url: '/admin/products/reorder', data: normalizeSharedProductParams(data) }),
  splitPreview: (data: Record<string, unknown>) =>
    request.post({ url: '/admin/products/split-preview', data: normalizeSharedProductParams(data) }),
  splitProducts: (data: Record<string, unknown>) =>
    request.post({ url: '/admin/products/split', data: normalizeSharedProductParams(data) }),
  batchUpdateCategory: (data: Record<string, unknown>) =>
    request.post({ url: '/admin/products/category/batch', data: normalizeSharedProductParams(data) }),
  batchUpdateProvisionHostname: (data: Record<string, unknown>) =>
    request.post({ url: '/admin/products/provision-hostname/batch', data }),
  pullTrafficPackages: (data: Record<string, unknown>) =>
    request.post({ url: '/admin/products/traffic-packages/pull', data: normalizeSharedProductParams(data) }),
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
      params: normalizeCategoryParams(params),
    }),
  createCategory: (data: Record<string, unknown>) =>
    request.post({ url: '/admin/product-categories', data: normalizeCategoryParams(data) }),
  updateCategory: (id: number | string, data: Record<string, unknown>) =>
    request.put({ url: `/admin/product-categories/${id}`, data: normalizeCategoryParams(data) }),
  deleteCategory: (id: number | string) => request.delete({ url: `/admin/product-categories/${id}` }),
  reorderCategory: (data: Record<string, unknown>) =>
    request.post({ url: '/admin/product-categories/reorder', data: normalizeCategoryParams(data) }),
};
