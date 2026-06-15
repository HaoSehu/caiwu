import { request } from '@/utils/request';
import type {
  ContentListParams,
  ContentCategoryRecord,
  ContentCategoryPayload,
  ContentArticleRecord,
  ContentArticlePayload,
  MediaFileRecord,
  HomeHeroSlide,
  HomeHeroFeature,
  HomeHeroPayload,
} from './types';

export const contentApi = {
  summary: () => request.get<Record<string, unknown>>({ url: '/admin/content/summary' }),
  categories: {
    list: (params: { content_type: string }) =>
      request.get<ContentCategoryRecord[]>({ url: '/admin/content/categories', params }),
    create: (data: ContentCategoryPayload) =>
      request.post<ContentCategoryRecord>({ url: '/admin/content/categories', data }),
    update: (id: number | string, data: ContentCategoryPayload) =>
      request.put<ContentCategoryRecord>({ url: `/admin/content/categories/${id}`, data }),
    delete: (id: number | string) =>
      request.delete({ url: `/admin/content/categories/${id}` }),
  },
  articles: {
    list: (params: ContentListParams) =>
      request.get<{ list?: ContentArticleRecord[]; total?: number; page?: number; page_size?: number }>({
        url: '/admin/content/articles',
        params,
      }),
    detail: (id: number | string) =>
      request.get<ContentArticleRecord>({ url: `/admin/content/articles/${id}` }),
    create: (data: ContentArticlePayload) =>
      request.post<ContentArticleRecord>({ url: '/admin/content/articles', data }),
    update: (id: number | string, data: ContentArticlePayload) =>
      request.put<ContentArticleRecord>({ url: `/admin/content/articles/${id}`, data }),
    delete: (id: number | string) =>
      request.delete({ url: `/admin/content/articles/${id}` }),
  },
};

export const mediaApi = {
  upload: (data: FormData) =>
    request.post<MediaFileRecord>({
      url: '/admin/media-files',
      data,
      headers: { 'Content-Type': 'multipart/form-data' },
    }),
};

export const siteHeroApi = {
  get: () => request.get<HomeHeroPayload>({ url: '/admin/site/home-hero' }),
  save: (data: { slides: HomeHeroSlide[]; features: HomeHeroFeature[] }) =>
    request.post<HomeHeroPayload>({ url: '/admin/site/home-hero', data }),
};
