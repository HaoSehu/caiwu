import type { ProductCategoryRecord } from '@/api/product';

import { normalizeCategoryScopeValue } from './catalogDisplay';
import { isSelectableProductGroup, productGroupLevel, productGroupOptionKey } from './useProductShared';

/**
 * 商品分类树工具（从 ProductCatalog.vue 抽离，P2-6）。
 *
 * 全部为纯函数：树结构由分类列表推导，展开状态、搜索词、当前商品类型都由调用方显式传入，
 * 便于单独测试，也避免页面文件里堆叠上百行树操作。
 */

export interface CategoryTreeNode {
  item: ProductCategoryRecord;
  children: CategoryTreeNode[];
}

export function categoryIdKey(row: ProductCategoryRecord) {
  return productGroupOptionKey(row);
}

export function isCategoryVisible(row: ProductCategoryRecord) {
  const value = row.is_visible;
  return !(value === false || value === 0 || value === '0');
}

export function buildCategoryTree(list: ProductCategoryRecord[]): CategoryTreeNode[] {
  const nodeMap = new Map<string, CategoryTreeNode>();
  const roots: CategoryTreeNode[] = [];

  // 第一轮：创建全部节点
  list.forEach((item) => {
    const key = categoryIdKey(item);
    const existingNode = nodeMap.get(key);
    if (existingNode) {
      // 节点已存在时合并后端返回的子节点
      const backendChildren = Array.isArray(item.children) ? item.children : [];
      if (backendChildren.length > 0 && existingNode.children.length === 0) {
        existingNode.children = backendChildren.map((child) => ({ item: child, children: [] }));
      }
    } else {
      nodeMap.set(key, { item, children: [] });
    }
  });

  // 第二轮：用 parent_id 建立父子关系。
  // parent_id 只是一个数字，同一 parent_id 在不同层级可能重复，必须再拼上级别前缀才唯一。
  list.forEach((item) => {
    const node = nodeMap.get(categoryIdKey(item));
    if (!node) return;

    const parentId =
      item.parent_id === undefined || item.parent_id === null || item.parent_id === '' ? '' : String(item.parent_id);
    if (!parentId) {
      roots.push(node);
      return;
    }

    // 父节点级别 = 当前级别 - 1，据此拼出父节点的唯一键
    const currentLevel = productGroupLevel(item);
    const parentLevel = currentLevel - 1;
    if (parentLevel < 1) {
      roots.push(node);
      return;
    }

    const parentKey = `${parentLevel}:${parentId}`;
    const parent = nodeMap.get(parentKey);
    if (parent) {
      // 检查节点是否已在父节点子集中
      if (!parent.children.some((child) => categoryIdKey(child.item) === categoryIdKey(item))) {
        parent.children.push(node);
      }
    } else {
      roots.push(node);
    }
  });

  return roots;
}

export function filterCategoryTree(nodes: CategoryTreeNode[], keyword: string): CategoryTreeNode[] {
  if (!keyword) return nodes;

  return nodes
    .map((node) => {
      const children = filterCategoryTree(node.children, keyword);
      const matched = [node.item.name, node.item.label].some((value) =>
        String(value || '')
          .toLowerCase()
          .includes(keyword),
      );
      if (matched) return { ...node, children: node.children };
      if (children.length) return { ...node, children };
      return null;
    })
    .filter((node): node is CategoryTreeNode => Boolean(node));
}

export function toDisplayCategoryTree(nodes: CategoryTreeNode[]): CategoryTreeNode[] {
  const lifted: CategoryTreeNode[] = [];

  nodes.forEach((root) => {
    if (productGroupLevel(root.item) === 1) {
      lifted.push(...root.children);
      return;
    }

    lifted.push(root);
  });

  return lifted;
}

interface CategoryTreeRowsOptions {
  /** 已展开的节点 key 集合；为空时只渲染顶层 */
  expandedKeys: ReadonlySet<string>;
  /** 搜索词（小写、已 trim）；非空时忽略展开状态全量展开 */
  keyword: string;
}

export function flattenCategoryTreeRows(
  nodes: CategoryTreeNode[],
  options: CategoryTreeRowsOptions,
  level = 0,
): Array<{ node: ProductCategoryRecord; level: number; childCount: number }> {
  const rows: Array<{ node: ProductCategoryRecord; level: number; childCount: number }> = [];

  nodes.forEach((treeNode) => {
    const childCount = treeNode.children.length;
    rows.push({ node: treeNode.item, level, childCount });

    if (childCount > 0 && (options.keyword || options.expandedKeys.has(categoryIdKey(treeNode.item)))) {
      rows.push(...flattenCategoryTreeRows(treeNode.children, options, level + 1));
    }
  });

  return rows;
}

export function findCategoryTreeNode(nodes: CategoryTreeNode[], key: number | string): CategoryTreeNode | null {
  const targetKey = String(key);
  for (const node of nodes) {
    if (categoryIdKey(node.item) === targetKey) return node;
    const matchedChild = findCategoryTreeNode(node.children, targetKey);
    if (matchedChild) return matchedChild;
  }
  return null;
}

export function firstDisplayCategoryNode(node: CategoryTreeNode): ProductCategoryRecord {
  if (isSelectableProductGroup(node.item)) return node.item;
  for (const child of node.children) {
    const matched = firstDisplayCategoryNode(child);
    if (isSelectableProductGroup(matched)) return matched;
  }
  return node.item;
}

/**
 * 同级分类的归组键：一级类型 + parent_id。
 * 页面里「上移/下移」按同级排序，fallbackProductType 用于一级节点没有 first_product_group_code 时回落到当前筛选类型。
 */
export function categoryScopeKey(row: ProductCategoryRecord, fallbackProductType: string) {
  return [
    normalizeCategoryScopeValue(row.first_product_group_code || fallbackProductType),
    normalizeCategoryScopeValue(row.parent_id),
  ].join(':');
}
