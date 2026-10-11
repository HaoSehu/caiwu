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
  MediaReindexResult,
} from './types';

interface PagedResult<T> {
  list?: T[];
  total?: number;
  page?: number;
  page_size?: number;
}

/** 知识库标签（M8，对应 ContentTagResource） */
export interface ContentTagRecord {
  id: number;
  name: string;
  usage_count: number;
  sort_order: number;
  created_at?: string | null;
  updated_at?: string | null;
}

export interface ContentTagPayload {
  name: string;
  sort_order?: number;
}

/**
 * 文章保存入参扩展：tag_ids 语义（后端 ContentArticleService）——
 * 字段缺省 / null = 不改动既有绑定；显式 [] = 清空全部绑定；非法 id 返回 422。
 */
export type ContentArticleSavePayload = ContentArticlePayload & {
  tag_ids?: number[] | string;
};

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
  tags: {
    list: (params?: { keyword?: string; page?: number; page_size?: number }) =>
      request.get<PagedResult<ContentTagRecord>>({
        url: '/v2/admin/content-tags',
        params,
      }),
    create: (data: ContentTagPayload) => request.post<ContentTagRecord>({ url: '/v2/admin/content-tags', data }),
    update: (id: number | string, data: ContentTagPayload) =>
      request.put<ContentTagRecord>({ url: `/v2/admin/content-tags/${id}`, data }),
    delete: (id: number | string) => request.delete({ url: `/v2/admin/content-tags/${id}` }),
  },
  articles: {
    list: (params: ContentListParams) =>
      request.get<{ list?: ContentArticleRecord[]; total?: number; page?: number; page_size?: number }>({
        url: '/v2/admin/content/articles',
        params,
      }),
    detail: (id: number | string) => request.get<ContentArticleRecord>({ url: `/v2/admin/content/articles/${id}` }),
    create: (data: ContentArticleSavePayload) =>
      request.post<ContentArticleRecord>({ url: '/v2/admin/content/articles', data }),
    update: (id: number | string, data: ContentArticleSavePayload) =>
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
  upload: (data: FormData) =>
    request.post<MediaFileRecord>({
      url: '/v2/admin/media-files',
      data,
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
};

export const siteHeroApi = {
  get: () => request.get<HomeHeroPayload>({ url: '/v2/admin/site/home-hero' }),
  save: (data: { slides: HomeHeroSlide[]; features: HomeHeroFeature[] }) =>
    request.post<HomeHeroPayload>({ url: '/v2/admin/site/home-hero', data }),
};
