<template>
  <div class="content-list-page">
    <div class="content-list-layout">
      <section class="content-main">
        <section class="hero-panel">
          <div class="hero-panel__inner">
            <div class="hero-copy">
              <h1>{{ config.heroTitle }}</h1>
              <p>{{ config.heroDescription }}</p>

              <div class="hero-keywords">
                <span class="hero-keywords__label">热门搜索:</span>
                <button
                  v-for="item in heroKeywords"
                  :key="item"
                  type="button"
                  class="hero-keywords__item"
                  @click="applyKeyword(item)"
                >
                  {{ item }}
                </button>
              </div>
            </div>

            <div class="hero-search">
              <t-input-group class="hero-search__group">
                <t-input
                  v-model="keyword"
                  :placeholder="config.searchPlaceholder"
                  class="hero-search__input"
                  autocomplete="off"
                  clearable
                  @enter="submitSearch"
                  @clear="submitSearch"
                />
                <t-button theme="primary" @click="submitSearch">搜索</t-button>
              </t-input-group>
            </div>
          </div>
        </section>

        <section class="category-panel">
          <button
            type="button"
            class="category-tab"
            :class="{ 'is-active': activeCategoryId === 0 }"
            :aria-pressed="activeCategoryId === 0"
            @click="selectCategory(0)"
          >
            {{ config.allCategoryLabel }}
          </button>

          <button
            v-for="item in categories"
            :key="item.id"
            type="button"
            class="category-tab"
            :class="{ 'is-active': activeCategoryId === item.id }"
            :aria-pressed="activeCategoryId === item.id"
            @click="selectCategory(item.id)"
          >
            {{ item.name }}
          </button>
        </section>

        <t-loading :loading="loading">
          <section class="list-panel">
            <template v-if="articleList.length">
              <article
                v-for="item in articleList"
                :key="item.id"
                class="list-item"
              >
                <div class="list-item__head">
                  <router-link
                    class="list-item__title"
                    :to="buildDetailRoute(item)"
                  >
                    {{ item.title }}
                  </router-link>

                  <span
                    v-if="Number(item.is_pinned) === 1"
                    class="list-item__badge"
                    >置顶</span
                  >
                </div>

                <p v-if="item.excerpt" class="list-item__summary">
                  {{ item.excerpt }}
                </p>

                <div class="list-item__meta">
                  <span>{{ item.category_name || currentCategoryLabel }}</span>
                  <span>{{ item.publish_at || item.created_at || "--" }}</span>
                  <span>浏览量: {{ item.view_count || 0 }}</span>
                </div>
              </article>
            </template>

            <div v-else-if="loadError" class="list-error" role="alert">
              <p class="list-error__text">内容加载失败，请检查网络后重试</p>
              <t-button theme="primary" @click="retryLoad">重新加载</t-button>
            </div>

            <!-- role=status：搜到空结果时读屏必须播报，否则用户以为还在加载 -->
            <div v-else-if="!loading" class="list-empty">
              <t-empty
                size="large"
                role="status"
                aria-live="polite"
                :title="emptyText"
              />
              <div class="list-empty__actions">
                <t-button v-if="keyword.trim()" @click="clearSearch"
                  >清除搜索</t-button
                >
                <t-button theme="primary" @click="router.push('/products')"
                  >查看产品</t-button
                >
              </div>
            </div>

            <div v-if="total > 0" class="list-panel__pager">
              <t-pagination
                v-model:current="page"
                :page-size="pageSize"
                :page-size-options="PAGE_SIZE_OPTIONS"
                :total="total"
                :show-page-size="hasMultiplePages"
                :show-jumper="hasMultiplePages"
                @current-change="updatePage"
                @page-size-change="updatePageSize"
              />
            </div>
          </section>
        </t-loading>
      </section>

      <aside class="content-sidebar">
        <section class="sidebar-card">
          <div class="sidebar-card__title">
            {{ config.sidebarCategoryTitle }}
          </div>

          <div class="shortcut-grid">
            <button
              v-for="item in config.shortcuts"
              :key="item.key"
              type="button"
              class="shortcut-item"
              @click="goShortcut(item.route)"
            >
              <span class="shortcut-item__icon">
                <component :is="item.icon" />
              </span>
              <span class="shortcut-item__label">{{ item.label }}</span>
            </button>
          </div>
        </section>

        <section class="sidebar-card">
          <div class="sidebar-card__title">{{ config.hotTitle }}</div>

          <div v-if="sidebarLoading" class="sidebar-hint">加载中…</div>
          <div v-else-if="sidebarError" class="sidebar-hint">
            <span>加载失败</span>
            <t-button variant="text" theme="primary" @click="retrySidebar"
              >重试</t-button
            >
          </div>
          <div v-else-if="hotArticles.length" class="ranking-list">
            <router-link
              v-for="(item, index) in hotArticles"
              :key="item.id"
              class="ranking-item"
              :to="buildDetailRoute(item)"
            >
              <span
                class="ranking-item__index"
                :class="index < 3 ? `top-${index + 1}` : undefined"
                >{{ index + 1 }}</span
              >
              <span class="ranking-item__title">{{ item.title }}</span>
            </router-link>
          </div>
          <t-empty
            v-else
            role="status"
            aria-live="polite"
            title="暂无数据"
          />
        </section>

        <section class="sidebar-card">
          <div class="sidebar-card__title">{{ config.secondaryTitle }}</div>

          <div v-if="sidebarLoading" class="sidebar-hint">加载中…</div>
          <div v-else-if="sidebarError" class="sidebar-hint">
            <span>加载失败</span>
            <t-button variant="text" theme="primary" @click="retrySidebar"
              >重试</t-button
            >
          </div>
          <div v-else-if="recentArticles.length" class="recent-list">
            <router-link
              v-for="item in recentArticles"
              :key="item.id"
              class="recent-item"
              :to="buildDetailRoute(item)"
            >
              <span class="recent-item__title">{{ item.title }}</span>
              <span class="recent-item__time">{{
                item.publish_at || item.created_at || "--"
              }}</span>
            </router-link>
          </div>
          <t-empty
            v-else
            role="status"
            aria-live="polite"
            title="暂无数据"
          />
        </section>
      </aside>
    </div>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import siteApi from "@/api/site";
import { getContentConfig } from "./contentConfig";

const props = defineProps({
  contentType: {
    type: String,
    required: true,
  },
  scope: {
    type: String,
    default: "client",
  },
});

const route = useRoute();
const router = useRouter();
const api = computed(() => siteApi);
const config = computed(() => getContentConfig(props.contentType, props.scope));

const loading = ref(false);
// 列表请求失败标记；与「空结果」分开，否则失败会伪装成「暂无内容」
const loadError = ref(false);
// 侧栏独立的加载/失败态
const sidebarLoading = ref(false);
const sidebarError = ref(false);
const categories = ref([]);
const articleList = ref([]);
const hotArticles = ref([]);
const recentArticles = ref([]);
const keyword = ref("");
const page = ref(1);
const PAGE_SIZE_OPTIONS = [10, 20, 50];
// 侧栏热门/最新固定最多显示 5 条。概览接口返回 5 条公告 + 6 篇帮助，
// 不截断会让两类页面的侧栏条数随内容类型漂移。
const SIDEBAR_ARTICLE_LIMIT = 5;
const pageSize = ref(10);
const total = ref(0);
const activeCategoryId = ref(0);

// 只有一页时不需要「每页条数下拉」和「跳页输入框」：它们改变不了任何结果，
// 却会占掉小屏分页条的整行宽度。多页时才放出这两个控件。
const hasMultiplePages = computed(() => total.value > pageSize.value);

// 空态文案：搜到空结果时要说清是「没搜到」，否则用户会以为是还没加载完
const emptyText = computed(() =>
  keyword.value.trim()
    ? `未找到与“${keyword.value.trim()}”相关的内容`
    : config.value.emptyText,
);

const heroKeywords = computed(() => {
  const categoryNames = categories.value.slice(0, 5).map((item) => item.name);
  return categoryNames.length ? categoryNames : config.value.keywordSuggestions;
});

const currentCategoryLabel = computed(() => {
  const matched = categories.value.find(
    (item) => item.id === activeCategoryId.value,
  );
  return matched?.name || config.value.allCategoryLabel;
});

function parseQueryNumber(value, fallback = 0) {
  const normalized = Array.isArray(value) ? value[0] : value;
  const parsed = Number.parseInt(normalized || "", 10);
  return Number.isInteger(parsed) && parsed > 0 ? parsed : fallback;
}

function parseQueryString(value) {
  const normalized = Array.isArray(value) ? value[0] : value;
  return String(normalized || "").trim();
}

function normalizeQuery(query) {
  const result = {};
  for (const [key, value] of Object.entries(query)) {
    if (value !== undefined && value !== null && value !== "" && value !== 0) {
      result[key] = value;
    }
  }
  return result;
}

function parseTimestamp(item) {
  const value = new Date(item.publish_at || item.created_at || 0).getTime();
  return Number.isNaN(value) ? 0 : value;
}

// 概览接口已同时返回分类与「最新若干条」，侧栏热门/最新都从这里派生，
// 不再额外列表接口打一次（原实现首屏对同一列表接口发两次请求）。
// 已知语义限制：服务端无按 view_count 排序的能力，热门只能在该类型最新若干条内排序。
function applySidebarFromOverview(articles) {
  const pool = Array.isArray(articles) ? articles : [];
  recentArticles.value = [...pool]
    .sort((timeA, timeB) => parseTimestamp(timeB) - parseTimestamp(timeA))
    .slice(0, SIDEBAR_ARTICLE_LIMIT);
  hotArticles.value = [...pool]
    .sort((a, b) => Number(b.view_count || 0) - Number(a.view_count || 0))
    .slice(0, SIDEBAR_ARTICLE_LIMIT);
}

async function loadOverview(token) {
  sidebarLoading.value = true;
  try {
    const res = await api.value.contentOverview();
    if (token !== syncToken) return;
    categories.value = res.data?.[config.value.overviewCategoryKey] || [];
    applySidebarFromOverview(res.data?.[config.value.overviewArticleKey]);
    sidebarError.value = false;
  } catch {
    // 分类拉取失败保留空态，错误提示由 HTTP 拦截器统一处理；
    // 但侧栏需要可重试入口，不能永远停在「暂无数据」
    if (token === syncToken) {
      sidebarError.value = true;
    }
  } finally {
    if (token === syncToken) {
      sidebarLoading.value = false;
    }
  }
}

async function loadList(token) {
  loading.value = true;
  loadError.value = false;

  try {
    const params = {
      page: page.value,
      page_size: pageSize.value,
    };

    if (activeCategoryId.value > 0) {
      params.category_id = activeCategoryId.value;
    }

    if (keyword.value) {
      params.keyword = keyword.value;
    }

    const res = await api.value[config.value.apiListMethod](params);
    if (token !== syncToken) return;
    articleList.value = res.data?.list || [];
    total.value = Number(res.data?.total || 0);

    if (!categories.value.length) {
      categories.value = res.data?.categories || [];
    }
  } catch {
    // 请求失败：清空列表，避免展示与当前 URL 不一致的旧数据，
    // 并置失败标记，让模板渲染可重试的错误态而不是「暂无内容」
    if (token === syncToken) {
      articleList.value = [];
      total.value = 0;
      loadError.value = true;
    }
  } finally {
    if (token === syncToken) {
      loading.value = false;
    }
  }
}

let syncToken = 0;
let syncDebounceTimer = null;
let didInitialSync = false;

async function syncPage() {
  const token = ++syncToken;
  keyword.value = parseQueryString(route.query.keyword);
  page.value = parseQueryNumber(route.query.page, 1);
  activeCategoryId.value = parseQueryNumber(route.query.category, 0);

  try {
    await Promise.all([loadOverview(token), loadList(token)]);
  } catch {
    // 各子请求已内部处理失败，此处兜底避免 unhandled rejection
  }
}

function replaceListQuery(nextQuery) {
  router.replace({
    path: config.value.routeBasePath,
    query: normalizeQuery(nextQuery),
  });
}

function selectCategory(categoryId) {
  activeCategoryId.value = Number(categoryId || 0);
  page.value = 1;

  replaceListQuery({
    ...route.query,
    category: activeCategoryId.value || undefined,
    page: undefined,
  });
}

function updatePage(nextPage) {
  page.value = nextPage;

  replaceListQuery({
    ...route.query,
    page: nextPage > 1 ? nextPage : undefined,
  });
}

// 改变每页条数后回到第一页，避免停留在越界页码上
function updatePageSize(nextSize) {
  pageSize.value = Number(nextSize) || 10;
  updatePage(1);
}

// 列表失败后的显式重试（失败态与空态分离后必须有出口）
function retryLoad() {
  loadError.value = false;
  syncPage();
}

function retrySidebar() {
  sidebarError.value = false;
  // 传 ++syncToken 而不是当前值：loadOverview 用 token !== syncToken 判定「已被更新的
  // 同步取代」，传当前值只能通过一次；若期间 syncPage 推进了 token，本次重试结果会被丢弃，
  // 且 finally 里的 token === syncToken 不成立，sidebarLoading 会永久停在 true。
  loadOverview(++syncToken);
}

function submitSearch() {
  page.value = 1;

  replaceListQuery({
    ...route.query,
    keyword: keyword.value.trim() || undefined,
    page: undefined,
  });
}

function applyKeyword(value) {
  keyword.value = value;
  submitSearch();
}

// 清除搜索并回到无条件列表
function clearSearch() {
  keyword.value = "";
  submitSearch();
}

function goShortcut(path) {
  if (path === route.path) {
    return;
  }

  router.push(path);
}

function buildDetailRoute(item) {
  return {
    name: config.value.detailRouteName,
    params: { id: item.id },
    query: normalizeQuery({
      category: activeCategoryId.value || undefined,
      // 只用已提交的搜索词，避免输入框未提交内容泄漏进详情链接
      keyword: String(route.query.keyword || "") || undefined,
      page: page.value > 1 ? page.value : undefined,
    }),
  };
}

watch(
  () => [route.query.category, route.query.keyword, route.query.page].join("|"),
  () => {
    // 首次同步立即执行；后续分类/翻页连续变化防抖合并，避免连发多组请求
    if (!didInitialSync) {
      didInitialSync = true;
      syncPage();
      return;
    }
    clearTimeout(syncDebounceTimer);
    syncDebounceTimer = setTimeout(syncPage, 250);
  },
  { immediate: true },
);

onBeforeUnmount(() => {
  clearTimeout(syncDebounceTimer);
  // 使仍在途的列表请求响应失效，避免写已卸载组件状态
  syncToken += 1;
});
</script>

<style scoped lang="scss">
.content-list-page {
  display: flex;
  flex-direction: column;
  // 补齐站点统一容器：此前根节点无 max-width、无水平留白，内容在宽屏上贴边
  @include site-container;
  padding: 24px 0 56px;
}

.content-list-layout {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 330px;
  gap: 18px;
  align-items: start;
}

.content-main {
  min-width: 0;
}

.hero-panel {
  position: relative;
  overflow: hidden;
  min-height: 150px;
  margin-bottom: 18px;
  border: 1px solid $border-color;
  background:
    radial-gradient(
      circle at top right,
      $color-accent-orange-soft,
      transparent 32%
    ),
    linear-gradient(
      135deg,
      rgba(255, 255, 255, 0.98),
      rgba(245, 247, 251, 0.96)
    );
  box-shadow: $shadow-md;

  &::after {
    content: "";
    position: absolute;
    inset: 0;
    background-image:
      linear-gradient(rgba(15, 23, 42, 0.05) 1px, transparent 1px),
      linear-gradient(90deg, rgba(15, 23, 42, 0.05) 1px, transparent 1px);
    background-size: 24px 24px;
    opacity: 0.12;
    pointer-events: none;
  }
}

.hero-panel__inner {
  position: relative;
  z-index: 1;
  display: grid;
  grid-template-columns: minmax(0, 1fr) 360px;
  gap: 28px;
  padding: 26px 30px;
}

.hero-copy h1 {
  color: $text-color-primary;
  // 列表页 h1 与详情页正文 h1 同档，此前 24px 是全站最小的一级标题
  font-size: $font-size-h1;
  font-weight: 700;
}

.hero-copy p {
  max-width: 560px;
  margin-top: 10px;
  color: $text-color-secondary;
  font-size: 13px;
  line-height: 1.8;
}

.hero-keywords {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  margin-top: 18px;
  color: $text-color-secondary;
  font-size: 13px;
}

.hero-keywords__label {
  font-weight: 600;
}

.hero-keywords__item {
  border: none;
  background: transparent;
  color: inherit;
  cursor: pointer;
  transition: color $motion-fast ease;

  &:hover {
    color: $color-primary;
  }
}

.hero-search {
  display: flex;
  align-items: center;
  justify-content: flex-end;
}

.hero-search__group {
  width: 100%;
  // 拼接组内输入框按 100% 撑开，按钮不能再被压缩，否则窄屏文案会溢出
  :deep(.t-button) {
    flex-shrink: 0;
  }
}

.hero-search__input {
  width: 100%;
}

.category-panel {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 10px;
  margin-bottom: 8px;
  padding: 0 20px;
  border: 1px solid $border-color;
  background: $bg-color-card;
  box-shadow: $shadow-sm;
}

.category-tab {
  position: relative;
  min-height: 50px;
  padding: 0 10px;
  border: none;
  background: transparent;
  color: $text-color-primary;
  font-size: 14px;
  cursor: pointer;
  transition: color $motion-fast ease;

  &:hover,
  &.is-active {
    color: $color-primary;
  }

  &.is-active::after {
    content: "";
    position: absolute;
    right: 0;
    bottom: 0;
    left: 0;
    height: 3px;
    background: $color-primary;
  }
}

.list-panel,
.sidebar-card {
  border: 1px solid $border-color;
  background: $bg-color-card;
  box-shadow: $shadow-sm;
}

.list-panel {
  overflow: hidden;
}

.list-item {
  padding: 26px 30px 22px;
  border-bottom: 1px solid $divider-color;
}

.list-item:last-child {
  border-bottom: none;
}

.list-item__head {
  display: flex;
  align-items: center;
  gap: 12px;
}

.list-item__title {
  color: $text-color-primary;
  font-size: 16px;
  font-weight: 700;
  line-height: 1.5;
  transition: color $motion-fast ease;
  // 超长标题限两行，避免条目高度不可控
  display: -webkit-box;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
  line-clamp: 2;
  overflow: hidden;

  &:hover {
    color: $color-primary;
  }
}

// 置顶徽标：按设计基线使用浅底标签（实底红白字对比 3.76 不达标）
.list-item__badge {
  display: inline-flex;
  align-items: center;
  height: 24px;
  padding: 0 10px;
  background: $color-danger-soft;
  color: $color-danger-text;
  font-size: 12px;
  font-weight: 600;
  border-radius: 2px;
}

.list-item__summary {
  margin-top: 12px;
  color: $text-color-secondary;
  font-size: 14px;
  line-height: 1.8;
  display: -webkit-box;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 3;
  overflow: hidden;
}

.list-item__meta {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 14px;
  margin-top: 14px;
  color: $text-color-placeholder;
  font-size: 12px;
}

.list-panel__pager {
  display: flex;
  justify-content: flex-end;
  padding: 20px 24px;

  // t-pagination 根节点默认 width:100% 且总条数 flex:1，会把「共 N 条数据」推到面板最左，
  // 与迁移前「总条数与页码控件并排靠右」不一致。收成内容宽度保持原版式（仅本站命名空间内）。
  //
  // 收成 width:auto 之后，窄屏下 6 个子项（总条数/每页条数/上一页/页码/下一页/跳页）按内容宽度
  // 撑到 740px，而 TDesign 根规则是 white-space:nowrap 且没有 flex-wrap，父级 .list-panel 又是
  // overflow:hidden → 溢出部分被静默裁掉，总条数与跳页完全不可见、下一页按钮点在裁切外点不到。
  // 因此这里必须允许换行；宽屏（≥815px）内容本来就装得下，换行不生效，版式不变。
  :deep(.t-pagination) {
    width: auto;
    flex-wrap: wrap;
  }

  @media (max-width: $breakpoint-compact) {
    flex-wrap: wrap;
    justify-content: center;
    gap: 8px;
  }
}

.list-empty {
  padding: 40px 20px;
}

.list-error {
  padding: 56px 24px;
  text-align: center;
}

.list-error__text {
  margin: 0 0 16px;
  color: $text-color-secondary;
  font-size: 14px;
  line-height: 1.8;
}

.sidebar-hint {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 16px 0 4px;
  color: $text-color-placeholder;
  font-size: 13px;
}

.list-empty__actions {
  display: flex;
  justify-content: center;
  gap: 12px;
  // TDesign 的 t-empty 自身没有下外边距，此处补一档描述与操作区之间的间距
  margin-top: 16px;
}

.content-sidebar {
  display: grid;
  gap: 18px;
}

.sidebar-card {
  padding: 20px;
}

.sidebar-card__title {
  padding-bottom: 14px;
  border-bottom: 1px solid $divider-color;
  color: $text-color-primary;
  font-size: 16px;
  font-weight: 600;
}

.shortcut-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 14px;
  margin-top: 16px;
}

.shortcut-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 10px;
  border: none;
  background: transparent;
  cursor: pointer;
  color: $text-color-primary;
}

.shortcut-item__icon {
  display: grid;
  place-items: center;
  width: 48px;
  height: 48px;
  background: $color-primary-soft;
  color: $color-primary;
  font-size: 22px;
}

.shortcut-item__label {
  font-size: 13px;
}

.ranking-list,
.recent-list {
  display: grid;
  gap: 2px;
  margin-top: 14px;
}

.ranking-item,
.recent-item {
  display: flex;
  align-items: center;
  gap: 12px;
  min-width: 0;
  padding: 11px 0;
  border-bottom: 1px solid $divider-color;
  color: $text-color-primary;
  transition: color $motion-fast ease;

  &:last-child {
    border-bottom: none;
  }

  &:hover {
    color: $color-primary;
  }
}

.ranking-item__index {
  display: inline-grid;
  place-items: center;
  width: 24px;
  height: 24px;
  background: $bg-color-soft;
  color: $text-color-secondary;
  font-size: 12px;
  font-weight: 700;
  flex-shrink: 0;

  // 12px 文字需 ≥4.5:1，三档配色在 variables 里已校验并注明换值要求
  &.top-1 {
    background: $badge-rank-1-bg;
    color: $badge-rank-1-text;
  }

  &.top-2 {
    background: $badge-rank-2-bg;
    color: $badge-rank-2-text;
  }

  &.top-3 {
    background: $badge-rank-3-bg;
    color: $badge-rank-3-text;
  }
}

.ranking-item__title,
.recent-item__title {
  min-width: 0;
  flex: 1;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-size: 14px;
}

.recent-item {
  justify-content: space-between;
  gap: 16px;
}

.recent-item__time {
  color: $text-color-placeholder;
  font-size: 12px;
  flex-shrink: 0;
}

@media (max-width: 1260px) {
  .content-list-layout {
    grid-template-columns: 1fr;
  }

  .content-sidebar {
    grid-template-columns: repeat(3, minmax(0, 1fr));
    align-items: start;
  }
}

@media (max-width: $breakpoint-nav) {
  .hero-panel__inner,
  .content-sidebar {
    grid-template-columns: 1fr;
  }

  .hero-search {
    justify-content: flex-start;
  }

  .shortcut-grid {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
}

@media (max-width: $breakpoint-compact) {
  .content-list-page {
    width: calc(100% - 24px);
    padding: 16px 0 40px;
  }

  .hero-panel__inner,
  .list-item,
  .sidebar-card {
    padding-right: 16px;
    padding-left: 16px;
  }

  .category-panel {
    padding: 0 12px;
  }

  .shortcut-grid {
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 8px;
  }

  .shortcut-item {
    gap: 6px;
  }

  .shortcut-item__icon {
    width: 40px;
    height: 40px;
    font-size: 18px;
  }

  .shortcut-item__label {
    font-size: 12px;
  }

  .list-item__head {
    align-items: flex-start;
    flex-direction: column;
  }
}
</style>
