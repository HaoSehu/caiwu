import type { ProductCategoryRecord, ProductRecord } from '@/api/product';

import { isSelectableProductGroup, productGroupOptionKey, toPlainRecord } from './useProductShared';

/**
 * 提供商「批量对接」弹层的树形数据工具（P2-6：从 Suppliers.vue 抽出的纯函数层）。
 *
 * 这里只做数据变换，不碰响应式状态：远端商品按分组路径建树、本地商品按分类建树、
 * 折叠状态过滤。抽出的目的是让 Suppliers.vue 只保留编排逻辑，树构建可单独读。
 */

export interface SupplierBatchProduct {
  id: number;
  name: string;
  type_label: string;
  remote_group_name: string;
  is_connected: boolean;
  connected_display_name: string;
  local_product_id?: number | null;
  local_product_name?: string | null;
  local_group_path?: string[];
  [key: string]: unknown;
}

export interface SupplierBatchTreeRow {
  key: string;
  node_type: 'group' | 'product';
  level: number;
  label: string;
  parentKey?: string;
  count?: number;
  hasChildren?: boolean;
  isExpanded?: boolean;
  selectable?: boolean;
  groupKey?: string;
  group?: ProductCategoryRecord;
  productId?: number;
  product?: SupplierBatchProduct;
  localProduct?: ProductRecord;
}

interface SupplierBatchRemoteGroupNode {
  key: string;
  label: string;
  level: number;
  parentKey?: string;
  children: SupplierBatchRemoteGroupNode[];
  childMap: Map<string, SupplierBatchRemoteGroupNode>;
  products: SupplierBatchProduct[];
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
    is_connected: Boolean(item.is_connected ?? item.is_bound),
    connected_display_name: String(
      item.connected_display_name || item.local_product_name || item.local_product_full_path || '',
    ).trim(),
    local_product_id: Number(item.local_product_id || 0) || null,
    local_product_name: String(item.local_product_name || item.local_product_full_path || '').trim() || null,
    local_group_path: Array.isArray(item.local_group_path)
      ? item.local_group_path.map((name) => String(name || '').trim()).filter(Boolean)
      : [],
  };
}

export function buildSupplierBatchProducts(payloadValue: unknown) {
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

export function buildRemoteProductTreeRows(products: SupplierBatchProduct[]): SupplierBatchTreeRow[] {
  const roots: SupplierBatchRemoteGroupNode[] = [];
  const rootMap = new Map<string, SupplierBatchRemoteGroupNode>();

  products.forEach((product) => {
    const groupPath = remoteProductGroupPath(product);
    const segments = groupPath.length ? groupPath : ['未分组'];
    const groupKeyParts: string[] = [];
    let parentNode: SupplierBatchRemoteGroupNode | undefined;
    let siblings = roots;
    let siblingMap = rootMap;

    segments.forEach((segment) => {
      groupKeyParts.push(normalizeSupplierTreeKeyPart(segment));
      const key = `remote-group:${groupKeyParts.join('/')}`;
      let node = siblingMap.get(key);

      if (!node) {
        node = {
          key,
          label: segment,
          level: groupKeyParts.length - 1,
          parentKey: parentNode?.key,
          children: [],
          childMap: new Map<string, SupplierBatchRemoteGroupNode>(),
          products: [],
        };
        siblingMap.set(key, node);
        siblings.push(node);
      }

      parentNode = node;
      siblings = node.children;
      siblingMap = node.childMap;
    });

    parentNode?.products.push(product);
  });

  const rows: SupplierBatchTreeRow[] = [];
  appendRemoteGroupRows(roots, rows);
  return rows;
}

function appendRemoteGroupRows(nodes: SupplierBatchRemoteGroupNode[], rows: SupplierBatchTreeRow[]) {
  nodes.forEach((node) => {
    rows.push({
      key: node.key,
      node_type: 'group',
      level: node.level,
      label: node.label,
      parentKey: node.parentKey,
      count: countRemoteGroupProducts(node),
      hasChildren: node.children.length > 0 || node.products.length > 0,
    });

    appendRemoteGroupRows(node.children, rows);
    node.products.forEach((product) => {
      rows.push({
        key: `remote-product:${product.id}`,
        node_type: 'product',
        level: node.level + 1,
        label: product.name,
        parentKey: node.key,
        productId: product.id,
        product,
      });
    });
  });
}

function countRemoteGroupProducts(node: SupplierBatchRemoteGroupNode): number {
  return node.products.length + node.children.reduce((total, child) => total + countRemoteGroupProducts(child), 0);
}

function normalizeSupplierTreeKeyPart(value: string) {
  return encodeURIComponent(String(value || '未命名').trim() || '未命名');
}

function remoteProductGroupPath(product: SupplierBatchProduct): string[] {
  const pathCandidates = [product.remote_group_path, product.group_path, product.category_path];

  for (const value of pathCandidates) {
    if (Array.isArray(value)) {
      const path = value.map((name) => String(name || '').trim()).filter(Boolean);
      if (path.length) return path;
    }
  }

  return [product.remote_group_name, product.group_name, product.second_group_name, product.type_label]
    .map((name) => String(name || '').trim())
    .filter(Boolean)
    .slice(0, 2);
}

export function buildLocalProductTreeRows(
  groups: ProductCategoryRecord[],
  products: ProductRecord[],
): SupplierBatchTreeRow[] {
  const productsByGroupKey = groupLocalProductsByGroup(products, buildLocalGroupPathKeyMap(groups));
  const rows: SupplierBatchTreeRow[] = [];

  groups.forEach((group) => appendLocalGroupRows(group, 0, rows, productsByGroupKey));

  return rows;
}

function appendLocalGroupRows(
  group: ProductCategoryRecord,
  level: number,
  rows: SupplierBatchTreeRow[],
  productsByGroupKey: Map<string, ProductRecord[]>,
  parentKey?: string,
) {
  const groupKey = productGroupOptionKey(group);
  const children = Array.isArray(group.children) ? group.children : [];
  const groupProducts = groupKey ? productsByGroupKey.get(groupKey) || [] : [];
  const rowKey = `local-group:${groupKey || group.id}`;
  rows.push({
    key: rowKey,
    node_type: 'group',
    level,
    label: String(group.label || group.name || `分类 #${group.id}`).trim(),
    parentKey,
    count: countLocalGroupProducts(group, productsByGroupKey),
    hasChildren: children.length > 0 || groupProducts.length > 0,
    selectable: isSelectableProductGroup(group),
    groupKey,
    group,
  });

  children.forEach((child) => appendLocalGroupRows(child, level + 1, rows, productsByGroupKey, rowKey));
  groupProducts.forEach((product) => {
    rows.push({
      key: `local-product:${product.id}`,
      node_type: 'product',
      level: level + 1,
      label: localProductDisplayName(product),
      parentKey: rowKey,
      localProduct: product,
    });
  });
}

function countLocalGroupProducts(
  group: ProductCategoryRecord,
  productsByGroupKey: Map<string, ProductRecord[]>,
): number {
  const groupKey = productGroupOptionKey(group);
  const ownCount = groupKey ? productsByGroupKey.get(groupKey)?.length || 0 : 0;
  const children = Array.isArray(group.children) ? group.children : [];
  return children.reduce((total, child) => total + countLocalGroupProducts(child, productsByGroupKey), ownCount);
}

function groupLocalProductsByGroup(products: ProductRecord[], groupPathKeyMap: Map<string, string>) {
  const result = new Map<string, ProductRecord[]>();
  products.forEach((product) => {
    const key = localProductGroupKey(product, groupPathKeyMap);
    if (!key) return;
    if (!result.has(key)) result.set(key, []);
    result.get(key)?.push(product);
  });

  return result;
}

function localProductGroupKey(product: ProductRecord, groupPathKeyMap: Map<string, string>) {
  const thirdId = Number(product.third_product_group_id || 0);
  if (thirdId > 0) return `3:${thirdId}`;
  const secondId = Number(product.second_product_group_id || 0);
  if (secondId > 0) return `2:${secondId}`;
  const firstId = Number(product.first_product_group_id || 0);
  if (firstId > 0) return `1:${firstId}`;
  const effectiveId = Number(product.effective_product_group_id || 0);
  const effectiveLevel = Number(product.effective_product_group_level || 0);
  if (effectiveId > 0 && effectiveLevel > 0) return `${effectiveLevel}:${effectiveId}`;

  const pathKey = normalizeLocalGroupPath(localProductGroupPath(product));
  if (pathKey && groupPathKeyMap.has(pathKey)) return groupPathKeyMap.get(pathKey) || '';
  return '';
}

function localProductGroupPath(product: ProductRecord) {
  const explicitPath = product.local_group_path;
  if (Array.isArray(explicitPath)) {
    const path = explicitPath.map((name) => String(name || '').trim()).filter(Boolean);
    if (path.length) return path;
  }

  const fullName = String(product.effective_product_group_full_name || product.category_full_name || '').trim();
  if (fullName) {
    return fullName
      .split(/\s*[/>＞]\s*/)
      .map((name) => name.trim())
      .filter(Boolean);
  }

  return [product.first_product_group_name, product.second_product_group_name, product.third_product_group_name]
    .map((name) => String(name || '').trim())
    .filter(Boolean);
}

function buildLocalGroupPathKeyMap(groups: ProductCategoryRecord[]) {
  const result = new Map<string, string>();
  const append = (items: ProductCategoryRecord[], parentPath: string[]) => {
    items.forEach((item) => {
      const label = String(item.name || item.label || '')
        .replace(/^\u3000+/, '')
        .trim();
      const path = label ? [...parentPath, label] : parentPath;
      const key = normalizeLocalGroupPath(path);
      const groupKey = productGroupOptionKey(item);
      if (key && groupKey) result.set(key, groupKey);
      append(Array.isArray(item.children) ? item.children : [], path);
    });
  };

  append(groups, []);
  return result;
}

function normalizeLocalGroupPath(path: unknown[]) {
  return path
    .map((name) =>
      String(name || '')
        .replace(/^\u3000+/, '')
        .trim(),
    )
    .filter(Boolean)
    .join(' / ');
}

export function localProductDisplayName(product?: ProductRecord) {
  if (!product) return '-';
  return (
    String(
      product.display_name ||
        product.product_display_name ||
        product.custom_display_name ||
        product.name ||
        product.id ||
        '-',
    ).trim() || '-'
  );
}

export function localProductSubtitle(product?: ProductRecord) {
  if (!product) return '-';
  const upstreamBinding = toPlainRecord(product.upstream_binding);
  const supplierName = String(upstreamBinding.supplier_name || '').trim();
  const upstreamProductId = String(upstreamBinding.upstream_product_id || '').trim();
  if (supplierName && upstreamProductId) return `提供商：${supplierName} · 上游ID：${upstreamProductId}`;
  if (supplierName) return `提供商：${supplierName}`;
  return (
    String(product.effective_product_group_full_name || product.product_type_label || '本地商品').trim() || '本地商品'
  );
}

export function visibleSupplierBatchTreeRows(rows: SupplierBatchTreeRow[], expandedKeySet: Set<string>) {
  const rowMap = new Map(rows.map((row) => [row.key, row]));
  return rows
    .filter((row) => isSupplierBatchTreeRowVisible(row, rowMap, expandedKeySet))
    .map((row) => (row.node_type === 'group' ? { ...row, isExpanded: expandedKeySet.has(row.key) } : row));
}

function isSupplierBatchTreeRowVisible(
  row: SupplierBatchTreeRow,
  rowMap: Map<string, SupplierBatchTreeRow>,
  expandedKeySet: Set<string>,
) {
  let parentKey = row.parentKey;
  while (parentKey) {
    if (!expandedKeySet.has(parentKey)) return false;
    parentKey = rowMap.get(parentKey)?.parentKey;
  }
  return true;
}

export function expandableSupplierBatchTreeKeys(rows: SupplierBatchTreeRow[]) {
  return rows.filter((row) => row.node_type === 'group' && row.hasChildren).map((row) => row.key);
}
