/**
 * 列表分页的每页条数选项。
 *
 * 统一出口：列表页一律绑定 `pagination.pageSizeOptions`（由 useListPage 提供），
 * 不走 useListPage 的局部列表（队列抽屉、产品目录等）也从这里取，避免各页面各写一份数组。
 */
export const PAGE_SIZE_OPTIONS = [20, 50, 100];

/** 子列表使用的细粒度选项（服务跟踪等单页条数较少的场景）。 */
export const COMPACT_PAGE_SIZE_OPTIONS = [10, 20, 50];
