/**
 * 跨端共享的 API 协议类型。
 *
 * 这里只放「三端约定完全一致」的结构，例如统一响应包裹、分页列表信封。
 * 各端特有的业务字段类型（如 ClientUserInfo、InvoiceRecord）仍留在各自
 * `src/types/` 下，不要往这里搬，否则共享包会变成业务类型的垃圾场。
 */

/** 后端统一响应包裹：code=0 视为成功，data 为业务数据。 */
export interface ApiEnvelope<T> {
  code: number;
  message?: string;
  data: T;
}

/** 分页列表信封：list + total 是后端固定返回字段，其余为可选回显。 */
export interface PagedList<T> {
  list: T[];
  total: number;
  page?: number;
  page_size?: number;
}

/** 分页请求的公共参数。 */
export interface PagedListParams {
  page?: number;
  page_size?: number;
  keyword?: string;
}
