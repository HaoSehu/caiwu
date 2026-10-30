import { MessagePlugin } from 'tdesign-vue-next/es/message/plugin';
import { computed, ref, watch } from 'vue';
import type { LocationQueryRaw, RouteLocationRaw } from 'vue-router';
import { useRoute, useRouter } from 'vue-router';

import clientApi from '@/api/client';
import type {
  ApiEnvelope,
  ContentArticleRecord,
  ContentCategoryRecord,
  ContentDetailPayload,
  ContentListPayload,
  ContentOverviewPayload,
} from '@/types/client';
import { getErrorMessage } from '@/utils/error';
import { renderMarkdown } from '@/utils/markdown';

type ContentType = 'notice' | 'help';

interface TocItem {
  id: string;
  label: string;
  level: number;
}

type ListQueryParams = Record<string, unknown> & {
  page: number;
  page_size: number;
  category_id?: number;
  keyword?: string;
};

interface ContentListConfig {
  pageTitle: string;
  detailTitle: string;
  heroDescription: string;
  searchPlaceholder: string;
  emptyText: string;
  allCategoryLabel: string;
  hotTitle: string;
  secondaryTitle: string;
  categoryTitle: string;
  routeBasePath: string;
  detailRouteName: 'ClientNoticeDetail' | 'ClientHelpDetail';
  overviewCategoryKey: keyof Pick<ContentOverviewPayload, 'notice_categories' | 'help_categories'>;
  fetchList: (params?: Record<string, unknown>) => Promise<ApiEnvelope<ContentListPayload>>;
  fetchDetail: (id: number | string) => Promise<ApiEnvelope<ContentDetailPayload>>;
  keywordSuggestions: string[];
}

const CONTENT_CONFIG: Record<ContentType, ContentListConfig> = {
  notice: {
    pageTitle: '官方公告',
    detailTitle: '公告详情',
    heroDescription: '查看平台最新通知、维护公告、产品更新与重要业务提醒。',
    searchPlaceholder: '请输入要搜索的公告关键词',
    emptyText: '暂无公告内容',
    allCategoryLabel: '全部分类',
    hotTitle: '热门公告',
    secondaryTitle: '最近更新',
    categoryTitle: '公告分类',
    routeBasePath: '/client/notices',
    detailRouteName: 'ClientNoticeDetail',
    overviewCategoryKey: 'notice_categories',
    fetchList: clientApi.notices,
    fetchDetail: clientApi.noticeDetail,
    keywordSuggestions: ['服务条款', '公告通知', '云服务器', '产品更新'],
  },
  help: {
    pageTitle: '帮助中心',
    detailTitle: '帮助详情',
    heroDescription: '快速查找购买、支付、续费、实例管理等常见操作指引。',
    searchPlaceholder: '搜索帮助文档、账单说明、续费规则',
    emptyText: '暂无帮助内容',
    allCategoryLabel: '全部分类',
    hotTitle: '热门文档',
    secondaryTitle: '最近更新',
    categoryTitle: '帮助分类',
    routeBasePath: '/client/help',
    detailRouteName: 'ClientHelpDetail',
    overviewCategoryKey: 'help_categories',
    fetchList: clientApi.helpArticles,
    fetchDetail: clientApi.helpDetail,
    keywordSuggestions: ['新手入门', '支付账单', '服务管理', '续费说明'],
  },
};

function parseQueryNumber(value: unknown, fallback = 0) {
  const normalized = Array.isArray(value) ? value[0] : value;
  const parsed = Number.parseInt(String(normalized || ''), 10);
  return Number.isInteger(parsed) && parsed > 0 ? parsed : fallback;
}

function parseQueryString(value: unknown) {
  const normalized = Array.isArray(value) ? value[0] : value;
  return String(normalized || '').trim();
}

function compactQuery(query: Record<string, unknown>): LocationQueryRaw {
  const result: LocationQueryRaw = {};
  for (const [key, value] of Object.entries(query)) {
    if (value === undefined || value === null || value === '' || value === 0) {
      continue;
    }
    if (Array.isArray(value)) {
      result[key] = value.map((item) => String(item));
      continue;
    }
    result[key] = typeof value === 'number' ? value : String(value);
  }
  return result;
}

function resolveList(payload?: ContentListPayload | null) {
  return Array.isArray(payload?.list) ? payload.list : [];
}

function resolveCategories(
  payload: ContentOverviewPayload | null | undefined,
  key: keyof Pick<ContentOverviewPayload, 'notice_categories' | 'help_categories'>,
) {
  if (!payload) return [] as ContentCategoryRecord[];
  const value = payload[key];
  return Array.isArray(value) ? value : [];
}

function sortByPublishTime(list: ContentArticleRecord[]) {
  return [...list].sort((a, b) => {
    const timeA = new Date(a.publish_at || a.created_at || 0).getTime();
    const timeB = new Date(b.publish_at || b.created_at || 0).getTime();
    return timeB - timeA;
  });
}

export function useContentList(contentType: ContentType) {
  const route = useRoute();
  const router = useRouter();
  const config = CONTENT_CONFIG[contentType];
  const loading = ref(false);
  const categories = ref<ContentCategoryRecord[]>([]);
  const articleList = ref<ContentArticleRecord[]>([]);
  const hotArticles = ref<ContentArticleRecord[]>([]);
  const recentArticles = ref<ContentArticleRecord[]>([]);
  const keyword = ref('');
  const page = ref(1);
  const pageSize = 10;
  const total = ref(0);
  const activeCategoryId = ref(0);
  const loadError = ref('');

  const heroKeywords = computed(() => {
    const names = categories.value
      .slice(0, 5)
      .map((item) => String(item.name || ''))
      .filter(Boolean);
    return names.length ? names : [...config.keywordSuggestions];
  });

  const currentCategoryLabel = computed(() => {
    const matched = categories.value.find((item) => Number(item.id) === activeCategoryId.value);
    return matched?.name || config.allCategoryLabel;
  });

  // 分类总览整页只拉一次（loadList 里同样只在 categories 为空时回填）。
  // 原先每次切分类/翻页都重拉，把一次切换的请求数从 2 抬到 3。
  // 失败不置位，下一次 syncPage 会自然重试。
  let overviewLoaded = false;
  async function loadOverview() {
    if (overviewLoaded) return;
    const response = await clientApi.contentOverview();
    categories.value = resolveCategories(response.data, config.overviewCategoryKey);
    overviewLoaded = true;
  }

  // 列表侧的竞态防护：连续改筛选/翻页时响应顺序没有保证，
  // 晚返回的旧条件结果会把新结果覆盖掉。序号由 syncPage 递增后传进来，
  // 每个写入 state 的 loader 自己判一次，避免在 await 之后统一判（那时已经写完了）。
  let listRequestSeq = 0;
  async function loadList(requestSeq: number) {
    const params: ListQueryParams = {
      page: page.value,
      page_size: pageSize,
    };
    if (activeCategoryId.value > 0) params.category_id = activeCategoryId.value;
    if (keyword.value) params.keyword = keyword.value;

    const response = await config.fetchList(params);
    if (requestSeq !== listRequestSeq) return;
    const payload = response.data;
    articleList.value = resolveList(payload);
    total.value = Number(payload.total || 0);
    if (!categories.value.length) {
      categories.value = Array.isArray(payload.categories) ? payload.categories : [];
    }
  }

  // N-11：侧栏「热门 / 最近更新」跟随当前分类。原先固定 page=1&page_size=20 且不带分类，
  // 切换分类后侧栏仍是首次加载的结果，与主列表说的不是同一批内容。
  // 不按分类做缓存：侧栏是「热门 / 最近更新」，浏览量与文章本身一直在变，
  // 省下的那一次请求要拿「切回旧分类看到过期列表」来换，正确性优先。
  async function loadSidebarContent(requestSeq: number) {
    const response = await config.fetchList({
      page: 1,
      page_size: 20,
      category_id: activeCategoryId.value || undefined,
    });
    if (requestSeq !== listRequestSeq) return;
    const list = resolveList(response.data);
    hotArticles.value = [...list].sort((a, b) => Number(b.view_count || 0) - Number(a.view_count || 0)).slice(0, 5);
    recentArticles.value = sortByPublishTime(list).slice(0, 5);
  }

  async function syncPage() {
    const requestSeq = ++listRequestSeq;
    loading.value = true;
    loadError.value = '';
    try {
      keyword.value = parseQueryString(route.query.keyword);
      page.value = parseQueryNumber(route.query.page, 1);
      activeCategoryId.value = parseQueryNumber(route.query.category, 0);
      await Promise.all([loadOverview(), loadList(requestSeq), loadSidebarContent(requestSeq)]);
    } catch (error: unknown) {
      if (requestSeq !== listRequestSeq) return;
      loadError.value = getErrorMessage(error, `${config.pageTitle}加载失败`);
      articleList.value = [];
      total.value = 0;
      MessagePlugin.error(loadError.value);
    } finally {
      if (requestSeq === listRequestSeq) loading.value = false;
    }
  }

  function replaceListQuery(nextQuery: Record<string, unknown>) {
    router.replace({
      path: config.routeBasePath,
      query: compactQuery(nextQuery),
    });
  }

  function selectCategory(categoryId: unknown) {
    activeCategoryId.value = Number(categoryId || 0);
    page.value = 1;
    replaceListQuery({ ...route.query, category: activeCategoryId.value || undefined, page: undefined });
  }

  function updatePage(pageInfo: { current?: number }) {
    const nextPage = Number(pageInfo?.current || page.value || 1);
    page.value = nextPage;
    replaceListQuery({ ...route.query, page: nextPage > 1 ? nextPage : undefined });
  }

  function submitSearch() {
    page.value = 1;
    replaceListQuery({ ...route.query, keyword: keyword.value.trim() || undefined, page: undefined });
  }

  function applyKeyword(value: string) {
    keyword.value = value;
    submitSearch();
  }

  // N-06：空态需要一条能立刻脱离「无结果」的出口。分类与关键词一起复位，
  // 只发一次路由变更，避免 selectCategory + submitSearch 连写两次 replace 造成两条历史记录。
  function resetFilters() {
    keyword.value = '';
    activeCategoryId.value = 0;
    page.value = 1;
    replaceListQuery({});
  }

  function buildDetailRoute(item: ContentArticleRecord): RouteLocationRaw {
    return {
      name: config.detailRouteName,
      params: { id: item.id },
      query: compactQuery({
        category: activeCategoryId.value || undefined,
        keyword: keyword.value || undefined,
        page: page.value > 1 ? page.value : undefined,
      }),
    };
  }

  watch(
    () => [route.query.category, route.query.keyword, route.query.page],
    () => {
      void syncPage();
    },
    { immediate: true },
  );

  return {
    config,
    loading,
    categories,
    articleList,
    hotArticles,
    recentArticles,
    keyword,
    page,
    pageSize,
    total,
    activeCategoryId,
    loadError,
    heroKeywords,
    currentCategoryLabel,
    syncPage,
    selectCategory,
    updatePage,
    submitSearch,
    applyKeyword,
    resetFilters,
    buildDetailRoute,
  };
}

export function useContentDetail(contentType: ContentType) {
  const route = useRoute();
  const router = useRouter();
  const config = CONTENT_CONFIG[contentType];
  const loading = ref(false);
  const categories = ref<ContentCategoryRecord[]>([]);
  const currentArticle = ref<ContentDetailPayload | null>(null);
  const detailLoadError = ref('');
  const currentCategoryId = ref<number | null>(null);
  const tocItems = ref<TocItem[]>([{ id: 'article-top', label: '全文', level: 1 }]);

  const backToListRoute = computed<RouteLocationRaw>(() => ({
    path: config.routeBasePath,
    query: compactQuery({
      category: route.query.category,
      keyword: route.query.keyword,
      page: route.query.page,
    }),
  }));

  const timeLabel = computed(() => (contentType === 'help' ? '更新时间' : '发布时间'));
  const currentCategoryName = computed(() => {
    const matched = categories.value.find((item) => Number(item.id) === currentCategoryId.value);
    return matched?.name || currentArticle.value?.category_name || config.pageTitle;
  });
  const currentPublisher = computed(
    () =>
      currentArticle.value?.creator?.nickname ||
      currentArticle.value?.creator?.username ||
      currentArticle.value?.operator ||
      '官方客服',
  );
  const currentPublishTime = computed(
    () =>
      currentArticle.value?.updated_at ||
      currentArticle.value?.last_published_at ||
      currentArticle.value?.publish_at ||
      currentArticle.value?.created_at ||
      '--',
  );
  // PR-14 / N-10：目录不再回读 DOM。正文由 v-html 一次性注入后，原先要再
  // querySelectorAll('h1, h2, h3, h4') 全量遍历一遍并逐节点写 heading.id，
  // 长文首帧把「解析 + 注入 + 扫描 + 写属性」全压在同一帧。现在标题 id 与
  // 目录清单由 markdown 渲染阶段顺带产出，一次渲染解决两件事。
  // 渲染阶段把标题清单收进一个普通变量（非响应式），tocItems 的赋值放到 watch 里。
  // 直接在 computed 求值体内写另一个 ref 是渲染期副作用：任何读取 articleContentHtml
  // 的渲染都会触发 tocItems 变更通知，等于在渲染过程中修改状态。
  let renderedHeadings: TocItem[] = [];
  const articleContentHtml = computed(() => {
    const article = currentArticle.value;
    const headings: TocItem[] = [];
    const html = renderMarkdown(article?.content, {
      imageAltFallback: article?.title || config.detailTitle || '相关文章配图',
      headingIdPrefix: `${contentType}-heading-${article?.id || 'current'}-`,
      onHeadings: (items) => headings.push(...items),
    });
    renderedHeadings = headings;
    return html;
  });
  watch(
    articleContentHtml,
    () => {
      tocItems.value = [{ id: 'article-top', label: '全文', level: 1 }, ...renderedHeadings];
    },
    { immediate: true },
  );

  async function loadOverview() {
    const response = await clientApi.contentOverview();
    categories.value = resolveCategories(response.data, config.overviewCategoryKey);
  }

  async function loadArticleDetail(articleId: unknown) {
    const normalizedId = Number(articleId);
    const response = await config.fetchDetail(
      Number.isFinite(normalizedId) && normalizedId > 0 ? normalizedId : String(articleId),
    );
    currentArticle.value = response.data || null;
    currentCategoryId.value = Number(response.data?.category_id || 0) || null;
  }

  // 详情侧的竞态防护：同路由快速切换文章 id 时，先发的请求可能后落地，
  // 把新文章的正文与目录盖回旧文章。
  let detailRequestSeq = 0;
  async function syncPage() {
    const requestSeq = ++detailRequestSeq;
    loading.value = true;
    detailLoadError.value = '';
    try {
      await Promise.all([loadOverview(), loadArticleDetail(route.params.id)]);
      if (requestSeq !== detailRequestSeq) return;
      if (!currentArticle.value) {
        detailLoadError.value = '内容不存在或已被删除';
      }
    } catch (error: unknown) {
      if (requestSeq !== detailRequestSeq) return;
      currentArticle.value = null;
      detailLoadError.value = getErrorMessage(error, `${config.detailTitle}加载失败`);
      MessagePlugin.error(detailLoadError.value);
    } finally {
      if (requestSeq === detailRequestSeq) loading.value = false;
    }
  }

  function goCategoryList(categoryId: unknown) {
    router.push({
      path: config.routeBasePath,
      query: compactQuery({ category: categoryId }),
    });
  }

  function scrollToAnchor(anchorId: string) {
    document.getElementById(anchorId)?.scrollIntoView({
      behavior: 'smooth',
      block: 'start',
    });
  }

  watch(
    () => route.params.id,
    () => {
      void syncPage();
    },
    { immediate: true },
  );

  return {
    config,
    loading,
    categories,
    currentArticle,
    detailLoadError,
    currentCategoryId,
    tocItems,
    backToListRoute,
    timeLabel,
    currentCategoryName,
    currentPublisher,
    currentPublishTime,
    articleContentHtml,
    syncPage,
    goCategoryList,
    scrollToAnchor,
  };
}
