import { request } from '@/utils/request';

import type {
  ContentArticlePayload,
  ContentArticleRecord,
  ContentCategoryPayload,
  ContentCategoryRecord,
  ContentListParams,
  HomeHeroFeature,
  HomeHeroPayload,
  HomeHeroSlide,
  MediaFileRecord,
  MediaMigrationProgress,
  MediaReindexResult,
  MediaStorageDriver,
} from './types';

interface PagedResult<T> {
  list?: T[];
  total?: number;
  page?: number;
  page_size?: number;
}

type MediaReindexV2Result = MediaReindexResult & {
  detail?: {
    media?: MediaReindexResult;
  };
};

function pageList<T>(response: PagedResult<T> | T[]): T[] {
  return Array.isArray(response) ? response : response.list || [];
}

function normalizeMediaReindex(response: MediaReindexV2Result): MediaReindexResult {
  return response.detail?.media || response;
}

export const contentApi = {
  summary: () => request.get<Record<string, unknown>>({ url: '/v2/admin/content/summary' }),
  categories: {
    list: (params: { content_type: string }) =>
      request.get<PagedResult<ContentCategoryRecord>>({ url: '/v2/admin/content/categories', params }).then(pageList),
    create: (data: ContentCategoryPayload) =>
      request.post<ContentCategoryRecord>({ url: '/v2/admin/content/categories', data }),
    update: (id: number | string, data: ContentCategoryPayload) =>
      request.put<ContentCategoryRecord>({ url: `/v2/admin/content/categories/${id}`, data }),
    delete: (id: number | string) => request.delete({ url: `/v2/admin/content/categories/${id}` }),
  },
  articles: {
    list: (params: ContentListParams) =>
      request.get<{ list?: ContentArticleRecord[]; total?: number; page?: number; page_size?: number }>({
        url: '/v2/admin/content/articles',
        params,
      }),
    detail: (id: number | string) => request.get<ContentArticleRecord>({ url: `/v2/admin/content/articles/${id}` }),
    create: (data: ContentArticlePayload) =>
      request.post<ContentArticleRecord>({ url: '/v2/admin/content/articles', data }),
    update: (id: number | string, data: ContentArticlePayload) =>
      request.put<ContentArticleRecord>({ url: `/v2/admin/content/articles/${id}`, data }),
    delete: (id: number | string) => request.delete({ url: `/v2/admin/content/articles/${id}` }),
  },
};

export const mediaApi = {
  list: (params?: { group?: string; keyword?: string; type?: string; page?: number; page_size?: number }) =>
    request.get<{ list?: MediaFileRecord[]; total?: number; page?: number; page_size?: number }>({
      url: '/v2/admin/media-files',
      params,
    }),
  /** 上传媒体文件；onUploadProgress 透传 axios 进度回调（AdminRequestConfig extends AxiosRequestConfig） */
  upload: (data: FormData, onUploadProgress?: (event: { loaded: number; total?: number }) => void) =>
    request.post<MediaFileRecord>({
      url: '/v2/admin/media-files',
      data,
      headers: { 'Content-Type': 'multipart/form-data' },
      onUploadProgress,
    }),
  reindex: () =>
    request
      .post<MediaReindexV2Result>({
        url: '/v2/admin/media-file-reindexes',
      })
      .then(normalizeMediaReindex),
  remove: (id: number | string) =>
    request.delete({
      url: `/v2/admin/media-files/${id}`,
    }),
  references: (id: number | string) =>
    request.get<{ references?: string[] }>({
      url: `/v2/admin/media-files/${id}/references`,
    }),
  /** 可用存储区列表（只含可用渠道） */
  drivers: () =>
    request.get<{ drivers?: MediaStorageDriver[] }>({
      url: '/v2/admin/media-storage-drivers',
    }),
  /** 发起转存，返回进度快照（含 id） */
  migrateCreate: (data: {
    direction: 'to_remote' | 'to_local';
    driver?: string;
    scope: 'ids';
    ids: Array<number | string>;
    delete_source?: boolean;
  }) =>
    request.post<MediaMigrationProgress>({
      url: '/v2/admin/media-migrations',
      data,
    }),
  /** 查询转存进度 */
  migrateProgress: (batchId: number | string) =>
    request.get<MediaMigrationProgress>({
      url: `/v2/admin/media-migrations/${batchId}`,
    }),
};

export const siteHeroApi = {
  get: () => request.get<HomeHeroPayload>({ url: '/v2/admin/site/home-hero' }),
  save: (data: { slides: HomeHeroSlide[]; features: HomeHeroFeature[] }) =>
    request.post<HomeHeroPayload>({ url: '/v2/admin/site/home-hero', data }),
};
