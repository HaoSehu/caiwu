/**
 * API 通用类型。
 *
 * 分页请求参数与响应包裹已收拢到 @caiwu/shared/types，这里只做 re-export，
 * 保证既有 `import type { PagedListParams } from '@/api/types'` 继续可用。
 */
export type { ApiEnvelope, PagedList, PagedListParams } from '@caiwu/shared/types';
