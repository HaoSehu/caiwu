import { computed, nextTick, ref, watch } from 'vue';
import type { LocationQueryRaw } from 'vue-router';
import { MessagePlugin } from 'tdesign-vue-next';
import { useRoute, useRouter } from 'vue-router';

import clientApi from '@/api/client';
import { renderMarkdown } from '@/utils/markdown';

type ContentType = 'notice' | 'help';
type AnyRecord = Record<string, any>;

const CONTENT_CONFIG = {
  notice: {
    pageTitle: '官方公告',
    detailTitle: '公告详情',
    heroDescription: '查看平台最新通知、维护公告、产品更新与重要业务提醒。',
    searchPlaceholder: '请输入您要搜索的关键词',
    emptyText: '暂无公告内容',
    allCategoryLabel: '所有分类',
    hotTitle: '热门文章',
    secondaryTitle: '最新活动',
    categoryTitle: '公告分类',
    routeBasePath: '/client/notices',
    detailRouteName: 'ClientNoticeDetail',
    listMethod: 'notices',
    detailMethod: 'noticeDetail',
    overviewCategoryKey: 'notice_categories',
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
    listMethod: 'helpArticles',
    detailMethod: 'helpDetail',
    overviewCategoryKey: 'help_categories',
    keywordSuggestions: ['新手入门', '支付账单', '服务管理', '续费说明'],
  },
} as const;

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

function resolveList(data: AnyRecord | undefined) {
  return Array.isArray(data?.list) ? data.list : [];
}

export function useContentList(contentType: ContentType) {
  const route = useRoute();
  const router = useRouter();
  const config = CONTENT_CONFIG[contentType];
  const loading = ref(false);
  const categories = ref<AnyRecord[]>([]);
  const articleList = ref<AnyRecord[]>([]);
  const hotArticles = ref<AnyRecord[]>([]);
  const recentArticles = ref<AnyRecord[]>([]);
  const keyword = ref('');
  const page = ref(1);
  const pageSize = 10;
  const total = ref(0);
  const activeCategoryId = ref(0);

  const heroKeywords = computed(() => {
    const names = categories.value.slice(0, 5).map((item) => String(item.name || '')).filter(Boolean);
    return names.length ? names : [...config.keywordSuggestions];
  });

  const currentCategoryLabel = computed(() => {
    const matched = categories.value.find((item) => Number(item.id) === activeCategoryId.value);
    return matched?.name || config.allCategoryLabel;
  });

  async function loadOverview() {
    const res = await clientApi.contentOverview();
    categories.value = (res as AnyRecord).data?.[config.overviewCategoryKey] || [];
  }

  async function loadList() {
    const params: AnyRecord = {
      page: page.value,
      page_size: pageSize,
    };
    if (activeCategoryId.value > 0) params.category_id = activeCategoryId.value;
    if (keyword.value) params.keyword = keyword.value;

    const method = clientApi[config.listMethod] as (params?: AnyRecord) => Promise<unknown>;
    const res = await method(params);
    const payload = (res as AnyRecord).data || {};
    articleList.value = resolveList(payload);
    total.value = Number(payload.total || 0);
    if (!categories.value.length) categories.value = payload.categories || [];
  }

  async function loadSidebarContent() {
    const method = clientApi[config.listMethod] as (params?: AnyRecord) => Promise<unknown>;
    const res = await method({ page: 1, page_size: 20 });
    const list = resolveList((res as AnyRecord).data);
    hotArticles.value = [...list].sort((a, b) => Number(b.view_count || 0) - Number(a.view_count || 0)).slice(0, 5);
    recentArticles.value = [...list]
      .sort((a, b) => {
        const timeA = new Date(a.publish_at || a.created_at || 0).getTime();
        const timeB = new Date(b.publish_at || b.created_at || 0).getTime();
        return timeB - timeA;
      })
      .slice(0, 5);
  }

  async function syncPage() {
    loading.value = true;
    try {
      keyword.value = parseQueryString(route.query.keyword);
      page.value = parseQueryNumber(route.query.page, 1);
      activeCategoryId.value = parseQueryNumber(route.query.category, 0);
      await Promise.all([loadOverview(), loadList(), loadSidebarContent()]);
    } catch (error: any) {
      MessagePlugin.error(error?.message || `${config.pageTitle}加载失败`);
    } finally {
      loading.value = false;
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

  function buildDetailRoute(item: AnyRecord) {
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
    heroKeywords,
    currentCategoryLabel,
    selectCategory,
    updatePage,
    submitSearch,
    applyKeyword,
    buildDetailRoute,
  };
}

export function useContentDetail(contentType: ContentType) {
  const route = useRoute();
  const router = useRouter();
  const config = CONTENT_CONFIG[contentType];
  const loading = ref(false);
  const categories = ref<AnyRecord[]>([]);
  const currentArticle = ref<AnyRecord | null>(null);
  const currentCategoryId = ref<number | null>(null);
  const tocItems = ref([{ id: 'article-top', label: '全文', level: 1 }]);
  const contentRef = ref<HTMLElement | null>(null);

  const backToListRoute = computed(() => ({
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
  const articleContentHtml = computed(() =>
    renderMarkdown(currentArticle.value?.content, {
      imageAltFallback: currentArticle.value?.title || config.detailTitle || '相关配图',
    }),
  );

  async function loadOverview() {
    const res = await clientApi.contentOverview();
    categories.value = (res as AnyRecord).data?.[config.overviewCategoryKey] || [];
  }

  async function loadArticleDetail(articleId: unknown) {
    const method = clientApi[config.detailMethod] as (id: unknown) => Promise<unknown>;
    const res = await method(articleId);
    currentArticle.value = (res as AnyRecord).data || null;
    currentCategoryId.value = Number((res as AnyRecord).data?.category_id || 0) || null;
  }

  async function syncPage() {
    loading.value = true;
    try {
      await Promise.all([loadOverview(), loadArticleDetail(route.params.id)]);
    } catch (error: any) {
      MessagePlugin.error(error?.message || `${config.detailTitle}加载失败`);
    } finally {
      loading.value = false;
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

  function buildToc() {
    const container = contentRef.value;
    if (!container) {
      tocItems.value = [{ id: 'article-top', label: '全文', level: 1 }];
      return;
    }

    const headings = Array.from(container.querySelectorAll('h1, h2, h3, h4'));
    const items = [{ id: 'article-top', label: '全文', level: 1 }];
    headings.forEach((heading, index) => {
      const label = heading.textContent?.trim();
      if (!label) return;
      const id = `${contentType}-heading-${currentArticle.value?.id || 'current'}-${index + 1}`;
      heading.id = id;
      items.push({
        id,
        label,
        level: Number.parseInt(heading.tagName.slice(1), 10) || 2,
      });
    });
    tocItems.value = items;
  }

  watch(
    () => route.params.id,
    () => {
      void syncPage();
    },
    { immediate: true },
  );

  watch(
    () => articleContentHtml.value,
    async () => {
      await nextTick();
      buildToc();
    },
  );

  return {
    config,
    loading,
    categories,
    currentArticle,
    currentCategoryId,
    tocItems,
    contentRef,
    backToListRoute,
    timeLabel,
    currentCategoryName,
    currentPublisher,
    currentPublishTime,
    articleContentHtml,
    goCategoryList,
    scrollToAnchor,
  };
}
