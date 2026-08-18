export type GenericRecord = Record<string, unknown>;

export interface ContentNormalizerOptions {
  /** 资源 URL 解析（如把 API 域下 uploads/media 相对路径改写为绝对地址），由消费端注入 */
  resolveAssetUrl?: (value: unknown) => string;
}

export interface NormalizedContentCategoryRecord extends GenericRecord {
  id: number;
  name: string;
  slug: string;
  description: string;
  status: number;
  sort_order: number;
  articles_count: number;
}

export interface NormalizedContentArticleRecord extends GenericRecord {
  id: number;
  content_type: string;
  type: string;
  category_id: number;
  content_category_id: number;
  title: string;
  slug: string;
  summary: string;
  excerpt: string;
  content: string;
  category_name: string;
  category: string;
  category_slug: string;
  keywords: string | string[];
  status: number;
  is_pinned: number;
  is_recommended: number;
  cover_image: string;
  sort_order: number;
  view_count: number;
  publish_at: string;
  last_published_at: string;
  operator: string;
  created_at: string;
  updated_at: string;
  category_detail: NormalizedContentCategoryRecord | null;
}

export interface NormalizedContentOverviewPayload extends GenericRecord {
  notices: NormalizedContentArticleRecord[];
  help_articles: NormalizedContentArticleRecord[];
  notice_categories: NormalizedContentCategoryRecord[];
  help_categories: NormalizedContentCategoryRecord[];
}

export interface NormalizedContentListPayload extends GenericRecord {
  list: NormalizedContentArticleRecord[];
  categories: NormalizedContentCategoryRecord[];
  page: number;
  page_size: number;
  total: number;
}

export interface NormalizedSiteConfigRecord extends GenericRecord {
  site_name: string;
  browser_title: string;
  site_logo: string;
  site_favicon: string;
  service_qq_group: string;
  service_phone: string;
  service_email: string;
  service_hours: string;
  support_group_title: string;
  support_group_text: string;
  support_group_qr: string;
}

function pickFirst(...values: unknown[]) {
  return values.find(
    (value) => value !== undefined && value !== null && value !== "",
  );
}

function toNumber(value: unknown, fallback = 0) {
  const normalized = Number(value);
  return Number.isFinite(normalized) ? normalized : fallback;
}

function resolveAssetUrl(
  value: unknown,
  options?: ContentNormalizerOptions,
): string {
  if (!options?.resolveAssetUrl) {
    return String(value || "");
  }

  return options.resolveAssetUrl(value);
}

export function normalizeContentCategory(
  item: GenericRecord = {},
  // 分类记录不含资源 URL 字段，保留入参以与其余 normalizer 签名一致
  _options?: ContentNormalizerOptions,
): NormalizedContentCategoryRecord {
  const id = toNumber(pickFirst(item.id, item.category_id, item.categoryId), 0);
  const name = String(
    pickFirst(item.name, item.title, item.category_name, item.category) || "",
  );

  return {
    ...item,
    id,
    name,
    slug: String(
      pickFirst(item.slug, item.category_slug, item.categorySlug, item.alias) ||
        "",
    ),
    description: String(
      pickFirst(item.description, item.summary, item.remark) || "",
    ),
    status: toNumber(pickFirst(item.status, item.state, item.enabled), 1),
    sort_order: toNumber(
      pickFirst(item.sort_order, item.sortOrder, item.sort),
      0,
    ),
    articles_count: toNumber(
      pickFirst(
        item.articles_count,
        item.article_count,
        item.articleCount,
        item.total,
      ),
      0,
    ),
  };
}

export function normalizeContentArticle(
  item: GenericRecord = {},
  options?: ContentNormalizerOptions,
): NormalizedContentArticleRecord {
  const categoryId = toNumber(
    pickFirst(
      item.category_id,
      item.content_category_id,
      item.categoryId,
      item.contentCategoryId,
    ),
    0,
  );
  const categoryName = String(
    pickFirst(
      item.category_name,
      item.category,
      item.content_category_name,
      item.contentCategoryName,
      item.category_title,
      item.categoryTitle,
    ) || "",
  );
  const summary = String(
    pickFirst(item.summary, item.excerpt, item.description, item.intro) || "",
  );
  const excerpt = String(
    pickFirst(item.excerpt, item.summary, item.description, item.intro) || "",
  );
  const publishAt = String(
    pickFirst(
      item.publish_at,
      item.published_at,
      item.publishedAt,
      item.last_published_at,
      item.created_at,
    ) || "",
  );

  return {
    ...item,
    id: toNumber(pickFirst(item.id, item.article_id, item.articleId), 0),
    content_type: String(
      pickFirst(
        item.content_type,
        item.type,
        item.article_type,
        item.articleType,
      ) || "",
    ),
    type: String(
      pickFirst(
        item.type,
        item.content_type,
        item.article_type,
        item.articleType,
      ) || "",
    ),
    category_id: categoryId,
    content_category_id: categoryId,
    title: String(pickFirst(item.title, item.name) || ""),
    slug: String(pickFirst(item.slug, item.alias) || ""),
    summary,
    excerpt,
    content: String(
      pickFirst(item.content, item.body, item.details, item.html) || "",
    ),
    category_name: categoryName,
    category: categoryName,
    category_slug: String(
      pickFirst(
        item.category_slug,
        item.categorySlug,
        item.category_alias,
        item.categoryAlias,
      ) || "",
    ),
    keywords: (pickFirst(item.keywords, item.keyword_list, item.keywordList) ??
      "") as string | string[],
    status: toNumber(pickFirst(item.status, item.state), 0),
    is_pinned: toNumber(pickFirst(item.is_pinned, item.is_top, item.isTop), 0),
    is_recommended: toNumber(
      pickFirst(item.is_recommended, item.is_hot, item.isHot, item.recommended),
      0,
    ),
    cover_image: resolveAssetUrl(
      pickFirst(item.cover_image, item.coverImage),
      options,
    ),
    sort_order: toNumber(
      pickFirst(item.sort_order, item.sortOrder, item.sort),
      0,
    ),
    view_count: toNumber(
      pickFirst(item.view_count, item.views, item.read_count, item.readCount),
      0,
    ),
    publish_at: publishAt,
    last_published_at: String(
      pickFirst(item.last_published_at, item.lastPublishedAt, publishAt) || "",
    ),
    operator: String(
      pickFirst(item.operator, item.author, item.publisher) || "",
    ),
    created_at: String(pickFirst(item.created_at, item.createdAt) || ""),
    updated_at: String(
      pickFirst(
        item.updated_at,
        item.updatedAt,
        item.publish_at,
        item.published_at,
      ) || "",
    ),
    category_detail:
      item.category_detail && typeof item.category_detail === "object"
        ? normalizeContentCategory(
            item.category_detail as GenericRecord,
            options,
          )
        : null,
  };
}

export function normalizeContentCategoryList(
  list: unknown,
  options?: ContentNormalizerOptions,
): NormalizedContentCategoryRecord[] {
  return Array.isArray(list)
    ? list.map((item) =>
        normalizeContentCategory(item as GenericRecord, options),
      )
    : [];
}

export function normalizeContentArticleList(
  list: unknown,
  options?: ContentNormalizerOptions,
): NormalizedContentArticleRecord[] {
  return Array.isArray(list)
    ? list.map((item) =>
        normalizeContentArticle(item as GenericRecord, options),
      )
    : [];
}

export function normalizeContentOverviewPayload(
  data: GenericRecord = {},
  options?: ContentNormalizerOptions,
): NormalizedContentOverviewPayload {
  return {
    ...data,
    notices: normalizeContentArticleList(
      pickFirst(data.notices, data.notice_list, data.noticeList),
      options,
    ),
    help_articles: normalizeContentArticleList(
      pickFirst(
        data.help_articles,
        data.helpArticles,
        data.help_list,
        data.helpList,
      ),
      options,
    ),
    notice_categories: normalizeContentCategoryList(
      pickFirst(
        data.notice_categories,
        data.noticeCategories,
        data.notice_category_list,
        data.noticeCategoryList,
      ),
      options,
    ),
    help_categories: normalizeContentCategoryList(
      pickFirst(
        data.help_categories,
        data.helpCategories,
        data.help_category_list,
        data.helpCategoryList,
      ),
      options,
    ),
  };
}

export function normalizeContentListPayload(
  data: GenericRecord = {},
  options?: ContentNormalizerOptions,
): NormalizedContentListPayload {
  return {
    ...data,
    list: normalizeContentArticleList(
      pickFirst(data.list, data.items, data.rows),
      options,
    ),
    categories: normalizeContentCategoryList(
      pickFirst(data.categories, data.category_list, data.categoryList),
      options,
    ),
    page: toNumber(data.page, 1),
    page_size: toNumber(data.page_size, 10),
    total: toNumber(pickFirst(data.total, data.count), 0),
  };
}

export function normalizeContentDetailPayload(
  data: GenericRecord = {},
  options?: ContentNormalizerOptions,
): NormalizedContentArticleRecord {
  return normalizeContentArticle(
    (pickFirst(data.article, data.item, data.record, data) ||
      {}) as GenericRecord,
    options,
  );
}

export function normalizeSiteConfig(
  config: GenericRecord = {},
  options?: ContentNormalizerOptions,
): NormalizedSiteConfigRecord {
  return {
    ...config,
    site_name: String(pickFirst(config.site_name, config.siteName) || ""),
    browser_title: String(
      pickFirst(
        config.browser_title,
        config.browserTitle,
        config.site_name,
        config.siteName,
      ) || "",
    ),
    site_logo: resolveAssetUrl(
      pickFirst(config.site_logo, config.siteLogo),
      options,
    ),
    site_favicon: resolveAssetUrl(
      pickFirst(config.site_favicon, config.siteFavicon),
      options,
    ),
    service_qq_group: String(
      pickFirst(
        config.service_qq_group,
        config.serviceQqGroup,
        config.service_phone,
        config.servicePhone,
      ) || "",
    ),
    service_phone: String(
      pickFirst(
        config.service_phone,
        config.servicePhone,
        config.service_qq_group,
        config.serviceQqGroup,
      ) || "",
    ),
    service_email: String(
      pickFirst(config.service_email, config.serviceEmail) || "",
    ),
    service_hours: String(
      pickFirst(config.service_hours, config.serviceHours) || "",
    ),
    support_group_title: String(
      pickFirst(config.support_group_title, config.supportGroupTitle) || "",
    ),
    support_group_text: String(
      pickFirst(config.support_group_text, config.supportGroupText) || "",
    ),
    support_group_qr: resolveAssetUrl(
      pickFirst(config.support_group_qr, config.supportGroupQr),
      options,
    ),
  };
}

export function normalizeSiteHomePayload(
  data: GenericRecord = {},
  options?: ContentNormalizerOptions,
): GenericRecord {
  return {
    ...data,
    site_config: normalizeSiteConfig(
      pickFirst(data.site_config, data.siteConfig, {}) as GenericRecord,
      options,
    ),
    notices: normalizeContentArticleList(
      pickFirst(data.notices, data.notice_list, data.noticeList),
      options,
    ),
    help_articles: normalizeContentArticleList(
      pickFirst(
        data.help_articles,
        data.helpArticles,
        data.help_list,
        data.helpList,
      ),
      options,
    ),
    root_groups: Array.isArray(pickFirst(data.root_groups, data.rootGroups))
      ? pickFirst(data.root_groups, data.rootGroups)
      : [],
    group_catalog_map: pickFirst(
      data.group_catalog_map,
      data.groupCatalogMap,
      {},
    ),
  };
}

export interface EnvelopeShape<T> {
  code: number;
  message?: string;
  data: T;
  timestamp?: number;
  [key: string]: unknown;
}

export function withNormalizedData<T>(
  response: {
    code?: number;
    message?: string;
    data: Record<string, unknown> | unknown;
    timestamp?: number;
  },
  normalizer: (payload: GenericRecord) => T,
): EnvelopeShape<T> {
  const payload =
    response?.data && typeof response.data === "object"
      ? (response.data as GenericRecord)
      : {};
  return {
    ...response,
    code: typeof response.code === "number" ? response.code : 0,
    data: normalizer(payload),
  };
}
