import { request } from '@/utils/request';
import type {
  CouponListParams,
  CouponRecord,
  CouponPayload,
  CouponCampaignListParams,
  CouponCampaignRecord,
  CouponCampaignPayload,
  CouponProductGroupChildrenParams,
  CouponProductGroupListParams,
  CouponProductGroupProductsParams,
  CouponProductGroupRecord,
  CouponProductRecord,
} from './types';

type PagedResult<T> = {
  list?: T[];
  total?: number;
  page?: number;
  page_size?: number;
};

type ProductTreeNode = Record<string, unknown> & {
  children?: ProductTreeNode[];
};

const PRODUCT_TREE_PAGE_SIZE = 100;

async function fetchAllPages<T>(loader: (page: number) => Promise<PagedResult<T>>) {
  const result: T[] = [];
  let page = 1;
  let total = 0;

  do {
    const response = await loader(page);
    const list = Array.isArray(response.list) ? response.list : [];
    total = Number(response.total || list.length || 0);
    result.push(...list);

    const pageSize = Number(response.page_size || PRODUCT_TREE_PAGE_SIZE);
    if (!list.length || result.length >= total || list.length < pageSize) break;
    page += 1;
  } while (page <= 1000);

  return result;
}

function productGroupNodeType(level: number) {
  if (level === 1) return 'first_product_group';
  if (level === 2) return 'second_product_group';
  return 'third_product_group';
}

function productGroupTreeNode(group: CouponProductGroupRecord, children: ProductTreeNode[] = []): ProductTreeNode {
  const level = Number(group.level || group.effective_product_group_level || 0);
  const label = String(group.label || group.name || '未命名分类');

  return {
    ...group,
    id: group.node_key || `${level}:${group.id}`,
    label,
    node_type: group.node_type || productGroupNodeType(level),
    leaf: false,
    disabled: false,
    effective_product_group_full_name: group.group_path || '',
    children,
  };
}

function productTreeNode(product: CouponProductRecord): ProductTreeNode {
  const label = String(product.label || product.display_name || product.product_display_name || product.name || `商品 #${product.id}`);

  return {
    ...product,
    id: Number(product.product_id || product.id),
    label,
    node_type: 'product',
    leaf: true,
    disabled: false,
    effective_product_group_full_name: product.group_path || product.category_full_name || '',
  };
}

function otherGroupNode(group: CouponProductGroupRecord, children: ProductTreeNode[]): ProductTreeNode {
  const path = String(group.group_path || group.label || group.name || '').trim();

  return {
    ...group,
    id: `${group.node_key || `second:${group.id}`}:other`,
    label: '其他',
    node_type: 'third_product_group',
    third_product_group_id: null,
    third_product_group_name: '其他',
    effective_product_group_id: group.id,
    effective_product_group_level: 2,
    effective_product_group_full_name: path ? `${path} / 其他` : '其他',
    leaf: false,
    disabled: false,
    children,
  };
}

export const couponsApi = {
  productGroups: (params?: CouponProductGroupListParams) =>
    request.get<PagedResult<CouponProductGroupRecord>>({ url: '/v2/admin/coupon-product-groups', params }),
  productGroupChildren: (group: number | string, params: CouponProductGroupChildrenParams) =>
    request.get<PagedResult<CouponProductGroupRecord>>({
      url: `/v2/admin/coupon-product-groups/${group}/children`,
      params,
    }),
  productGroupProducts: (group: number | string, params: CouponProductGroupProductsParams) =>
    request.get<PagedResult<CouponProductRecord>>({
      url: `/v2/admin/coupon-product-groups/${group}/products`,
      params,
    }),
  /** 批量拉取多个分组的产品 */
  batchProducts: (groups: Array<{ id: number; level: number }>) =>
    request.post<Record<number, CouponProductRecord[]>>({
      url: '/v2/admin/coupon-product-groups/batch-products',
      data: { groups },
    }),
  productTree: () =>
    buildCouponProductTree(),
  summary: (params?: Record<string, unknown>) =>
    request.get<{ enabled?: boolean; [key: string]: unknown }>({ url: '/v2/admin/coupons/summary', params }),
  list: (params: CouponListParams) =>
    request.get<{ list?: CouponRecord[]; total?: number; page?: number; page_size?: number }>({
      url: '/v2/admin/coupons',
      params,
    }),
  create: (data: CouponPayload) => request.post<CouponRecord>({ url: '/v2/admin/coupons', data }),
  update: (id: number | string, data: CouponPayload) =>
    request.put<CouponRecord>({ url: `/v2/admin/coupons/${id}`, data }),
  toggleStatus: (id: number | string, enabled: boolean) =>
    request.patch({ url: `/v2/admin/coupons/${id}/status`, data: { enabled } }),
  delete: (id: number | string) => request.delete({ url: `/v2/admin/coupons/${id}` }),
};

// 商品树加载单例缓存：避免并发重复请求
let cachedTreePromise: Promise<{ tree?: ProductTreeNode[] }> | null = null;

async function buildCouponProductTree(): Promise<{ tree?: ProductTreeNode[] }> {
  // 单例缓存：同一次页面生命周期内复用结果，避免并发重复请求
  if (cachedTreePromise) return cachedTreePromise;
  cachedTreePromise = buildCouponProductTreeInternal();
  return cachedTreePromise;
}

/** 内部实现，不缓存 */
async function buildCouponProductTreeInternal(): Promise<{ tree?: ProductTreeNode[] }> {
  const roots = await fetchAllPages<CouponProductGroupRecord>((page) =>
    couponsApi.productGroups({ page, page_size: PRODUCT_TREE_PAGE_SIZE }),
  );

  // 第一阶段：收集所有分组（不拉产品），构造成平面列表
  const allGroups: Array<{ group: CouponProductGroupRecord; parentId: number | null; level: number }> = [];
  const productGroups: Array<{ group: CouponProductGroupRecord; level: number }> = [];

  function collectGroups(group: CouponProductGroupRecord, parentId: number | null) {
    const level = Number(group.level || 0) as 1 | 2 | 3;
    allGroups.push({ group, parentId, level });
    if (Number(group.direct_products_count || 0) > 0) {
      productGroups.push({ group, level });
    }
    return group;
  }

  // 递归收集（不拉产品，只拉子分组）
  async function collectRecursive(group: CouponProductGroupRecord, parentId: number | null): Promise<void> {
    const level = Number(group.level || 0) as 1 | 2 | 3;
    collectGroups(group, parentId);
    if (group.has_children && level < 3) {
      const childGroups = await fetchAllPages<CouponProductGroupRecord>((page) =>
        couponsApi.productGroupChildren(group.id, { level: level as 1 | 2, page, page_size: PRODUCT_TREE_PAGE_SIZE }),
      );
      await Promise.all(childGroups.map((child) => collectRecursive(child, group.id)));
    }
  }

  await Promise.all(roots.map((root) => collectRecursive(root, null)));

  // 第二阶段：一次性批量拉取所有分组的产品（1 次请求替代 N 次）
  const productMap = new Map<number, CouponProductRecord[]>();
  if (productGroups.length > 0) {
    try {
      const batchResponse = await couponsApi.batchProducts(
        productGroups.map(({ group, level }) => ({ id: group.id, level })),
      );
      // batchResponse 已被 Axios 拦截器解包为 Record<number, CouponProductRecord[]>
      const data = (batchResponse && typeof batchResponse === 'object' ? batchResponse : {}) as Record<string, unknown>;
      for (const [groupId, products] of Object.entries(data)) {
        if (Array.isArray(products)) {
          productMap.set(Number(groupId), products as CouponProductRecord[]);
        }
      }
      // 如果批量接口未返回全部数据，补漏
      if (productMap.size < productGroups.length) {
        const missing = productGroups.filter(({ group }) => !productMap.has(group.id));
        if (missing.length > 0) {
          const supplement = await Promise.all(
            missing.map(async ({ group, level }) => {
              const products = await fetchAllPages<CouponProductRecord>((page) =>
                couponsApi.productGroupProducts(group.id, { level: level as 1 | 2 | 3, page, page_size: PRODUCT_TREE_PAGE_SIZE }),
              );
              return { groupId: group.id, products };
            }),
          );
          for (const { groupId, products } of supplement) {
            productMap.set(groupId, products);
          }
        }
      }
    } catch {
      // 批量接口失败时回退到逐个请求
      const productResults = await Promise.all(
        productGroups.map(async ({ group, level }) => {
          const response = await fetchAllPages<CouponProductRecord>((page) =>
            couponsApi.productGroupProducts(group.id, { level: level as 1 | 2 | 3, page, page_size: PRODUCT_TREE_PAGE_SIZE }),
          );
          return { groupId: group.id, products: response };
        }),
      );
      for (const { groupId, products } of productResults) {
        productMap.set(groupId, products);
      }
    }
  }

  // 第三阶段：用缓存构建树
  const groupNodeMap = new Map<number, ProductTreeNode>();

  function buildNode(group: CouponProductGroupRecord, children: ProductTreeNode[] = []): ProductTreeNode {
    const level = Number(group.level || 0) as 1 | 2 | 3;
    const products = productMap.get(group.id) || [];
    const productNodes = products.map(productTreeNode);
    const mergedChildren = level === 2 && children.length > 0 && productNodes.length > 0
      ? [...children, otherGroupNode(group, productNodes)]
      : [...children, ...productNodes];
    const node = productGroupTreeNode(group, mergedChildren);
    groupNodeMap.set(group.id, node);
    return node;
  }

  // 自底向上构建
  const sorted = [...allGroups].sort((a, b) => b.level - a.level); // 最深优先
  for (const entry of sorted) {
    if (!groupNodeMap.has(entry.group.id)) {
      buildNode(entry.group);
    }
  }

  // 组装根节点
  const tree = roots
    .map((root) => {
      const node = groupNodeMap.get(root.id);
      if (!node) return null;

      // 重组 children：将子分组节点挂回
      const childIds = allGroups
        .filter((g) => g.parentId === root.id)
        .map((g) => groupNodeMap.get(g.group.id))
        .filter(Boolean) as ProductTreeNode[];

      if (childIds.length > 0) {
        const products = productMap.get(root.id) || [];
        const productNodes = products.map(productTreeNode);
        return productGroupTreeNode(root, [
          ...childIds,
          ...productNodes,
        ]);
      }
      return node;
    })
    .filter(Boolean) as ProductTreeNode[];

  return { tree };
}

async function buildCouponProductGroupTree(group: CouponProductGroupRecord): Promise<ProductTreeNode> {
  // 保留旧函数签名兼容，实际走 buildCouponProductTree 全并行路径
  return buildCouponProductTree().then((r) => {
    return (r.tree || []).find((n) => n.id === group.id) || productGroupTreeNode(group);
  });
}

export const couponCampaignsApi = {
  summary: (params?: Record<string, unknown>) =>
    request.get<Record<string, unknown>>({ url: '/v2/admin/coupon-campaigns/summary', params }),
  list: (params: CouponCampaignListParams) =>
    request.get<{ list?: CouponCampaignRecord[]; total?: number; page?: number; page_size?: number }>({
      url: '/v2/admin/coupon-campaigns',
      params,
    }),
  create: (data: CouponCampaignPayload) =>
    request.post<CouponCampaignRecord>({ url: '/v2/admin/coupon-campaigns', data }),
  update: (id: number | string, data: CouponCampaignPayload) =>
    request.put<CouponCampaignRecord>({ url: `/v2/admin/coupon-campaigns/${id}`, data }),
  toggleStatus: (id: number | string, enabled: boolean) =>
    request.patch({ url: `/v2/admin/coupon-campaigns/${id}/status`, data: { enabled } }),
  trigger: (id: number | string) =>
    request.post({ url: `/v2/admin/coupon-campaigns/${id}/tasks`, data: { type: 'trigger', payload: {} } }),
  delete: (id: number | string) => request.delete({ url: `/v2/admin/coupon-campaigns/${id}` }),
};
