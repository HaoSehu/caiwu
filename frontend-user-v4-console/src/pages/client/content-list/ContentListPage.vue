<template>
  <section class="content-list-page">
    <div class="content-list-layout">
      <main class="content-main">
        <t-card class="content-card hero-card" :bordered="false">
          <div class="hero-copy">
            <h1>{{ config.pageTitle }}</h1>
            <p>{{ config.heroDescription }}</p>
            <div class="hero-keywords">
              <span>热门搜索：</span>
              <t-button
                v-for="item in heroKeywords"
                :key="item"
                theme="primary"
                variant="text"
                @click="applyKeyword(item)"
              >
                {{ item }}
              </t-button>
            </div>
          </div>
          <t-input
            v-model="keyword"
            clearable
            :placeholder="config.searchPlaceholder"
            @enter="submitSearch"
            @clear="submitSearch"
          >
            <!-- O-02：suffixIcon 槽在 clearable 且有内容时会被 TDesign 换成清除图标，
                 放大镜随鼠标悬停消失、点下去变成清空。改用 #suffix 槽放可点按钮，位置稳定。 -->
            <template #suffix>
              <search-field-button @search="submitSearch" />
            </template>
          </t-input>
        </t-card>

        <t-card class="content-card category-card" :bordered="false">
          <t-space break-line>
            <t-button
              :class="{ 'is-active': activeCategoryId === 0 }"
              :theme="activeCategoryId === 0 ? 'primary' : 'default'"
              variant="outline"
              @click="selectCategory(0)"
            >
              {{ config.allCategoryLabel }}
            </t-button>
            <t-button
              v-for="item in categories"
              :key="item.id"
              :class="{ 'is-active': activeCategoryId === Number(item.id) }"
              :theme="activeCategoryId === Number(item.id) ? 'primary' : 'default'"
              variant="outline"
              @click="selectCategory(item.id)"
            >
              {{ item.name }}
            </t-button>
          </t-space>
        </t-card>

        <t-card class="content-card list-card" :bordered="false">
          <template v-if="isNotice && unreadCount > 0" #actions>
            <t-button theme="primary" variant="text" @click="handleMarkAllRead"
              >全部标记已读 ({{ unreadCount }})</t-button
            >
          </template>
          <!-- 手机形态标记：公告 / 帮助共用本组件，列表在手机端以卡片形态呈现 -->
          <div :data-mobile-view="isMobile ? 'card' : undefined">
            <data-state
              :loading="loading"
              :error="!loading && Boolean(loadError)"
              :error-text="loadError"
              :empty="!loading && !loadError && !articleList.length"
              :description="hasActiveFilters ? '当前筛选条件下没有匹配内容' : config.emptyText"
              @retry="syncPage"
            >
              <!-- N-06：空态必须给一条可执行的出口，否则用户只能自己猜是换个词还是清分类。
                   补：没有筛选条件时的空态原先没有任何按钮（docs/DESIGN.md §5.2 要求空数据必须给出引导路径），
                   与账单/订单/服务页口径不一致，这里补一条回控制台的出口。 -->
              <template #empty-action>
                <t-button v-if="hasActiveFilters" theme="primary" variant="outline" @click="resetFilters"
                  >清除筛选条件</t-button
                >
                <t-button v-else theme="primary" variant="outline" @click="router.push('/client/dashboard')"
                  >回到控制台</t-button
                >
              </template>
              <article v-for="item in articleList" :key="item.id" class="article-row">
                <div class="article-row__head">
                  <router-link class="article-row__title" :to="buildDetailRoute(item)">{{ item.title }}</router-link>
                  <t-tag v-if="Number(item.is_pinned) === 1" theme="primary" variant="light" class="pin-tag"
                    >置顶</t-tag
                  >
                </div>
                <p v-if="item.excerpt">{{ item.excerpt }}</p>
                <div class="article-row__meta">
                  <span>{{ item.category_name || currentCategoryLabel }}</span>
                  <span>{{ formatDateTime(item.publish_at || item.created_at) }}</span>
                  <span>浏览量 {{ item.view_count || 0 }}</span>
                </div>
              </article>
            </data-state>
          </div>
        </t-card>

        <div v-if="total > pageSize" class="content-pagination">
          <t-pagination
            v-model="page"
            :page-size="pageSize"
            :page-size-options="[10, 20, 50]"
            :total="total"
            show-total
            @change="updatePage"
          />
        </div>
      </main>

      <aside class="content-sidebar">
        <t-card class="content-card" :bordered="false">
          <template #title>{{ config.hotTitle }}</template>
          <div v-if="hotArticles.length" class="rank-list">
            <router-link
              v-for="(item, index) in hotArticles"
              :key="item.id"
              :to="buildDetailRoute(item)"
              class="rank-row"
            >
              <span>{{ index + 1 }}</span>
              <strong>{{ item.title }}</strong>
            </router-link>
          </div>
          <t-empty v-else :description="hasActiveFilters ? '当前分类下暂无内容' : '暂无数据'" />
        </t-card>

        <t-card class="content-card" :bordered="false">
          <template #title>{{ config.secondaryTitle }}</template>
          <div v-if="recentArticles.length" class="recent-list">
            <router-link v-for="item in recentArticles" :key="item.id" :to="buildDetailRoute(item)" class="recent-row">
              <strong>{{ item.title }}</strong>
              <span>{{ formatDateTime(item.publish_at || item.created_at) }}</span>
            </router-link>
          </div>
          <t-empty v-else :description="hasActiveFilters ? '当前分类下暂无内容' : '暂无数据'" />
        </t-card>
      </aside>
    </div>
  </section>
</template>
<script setup lang="ts">
import DataState from '@caiwu/shared/user-v3/components/DataState.vue';
import { MessagePlugin } from 'tdesign-vue-next/es/message/plugin';
import { computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';

import SearchFieldButton from '@/components/common/SearchFieldButton.vue';
import { useDeviceLayout } from '@/composables/useDeviceLayout';
import { useContentList } from '@/domains/content/useContent';
import { useNoticeReadStatus } from '@/domains/content/useNoticeReadStatus';
import { getErrorMessage } from '@/utils/error';
import { formatDateTime } from '@/utils/format';

const props = defineProps<{
  contentType: 'notice' | 'help';
}>();

const {
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
} = useContentList(props.contentType);

const router = useRouter();
const { unreadCount, markAllRead, fetchUnreadCount } = useNoticeReadStatus();
const { isMobile } = useDeviceLayout();
const isNotice = props.contentType === 'notice';
// 空态文案要能区分「真没有内容」与「被筛选条件筛空」，两者的可执行出口不同
const hasActiveFilters = computed(() => Boolean(keyword.value) || activeCategoryId.value > 0);

// N-03：公告未读数是跨页共享的模块级状态，进列表页先对齐一次，
// 否则「全部标记已读 (n)」显示的可能是上一次路由停留时的旧值或 0。
onMounted(() => {
  if (!isNotice) return;
  void fetchUnreadCount();
});

async function handleMarkAllRead() {
  // markAllRead 失败会抛错，未捕获会变成未处理的 rejection 且用户看不到任何反馈
  try {
    await markAllRead();
  } catch (error: unknown) {
    MessagePlugin.error(getErrorMessage(error, '批量标记已读失败，请稍后重试'));

    return;
  }
  try {
    await fetchUnreadCount(true);
  } catch {
    // 未读计数只是辅助信息，刷新失败不回滚已成功的标记结果
  }
}
</script>
<style scoped lang="less">
.content-list-page {
  // padding 由 Starter 布局层统一提供
}

.content-list-layout {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(18rem, 24%);
  gap: var(--td-comp-margin-m);
  align-items: start;
}

.content-main,
.content-sidebar {
  display: flex;
  flex-direction: column;
  gap: var(--td-comp-margin-m);
  min-width: 0;
}

.content-card {
  background: var(--td-bg-color-container);
  border: thin solid var(--td-component-stroke);
  border-radius: var(--td-radius-medium);
  box-shadow: var(--td-shadow-1);
}

.category-card {
  // N-04：选中态原来只靠 theme=primary 的描边色，浅色主题下与未选中几乎同色。
  // 再加一条底色与字重，让当前分类在扫视时能被一眼拎出来。
  :deep(.t-button.is-active) {
    background: var(--td-brand-color-light);
    font-weight: 600;
  }
}

.pin-tag {
  flex: none;
}

.hero-card {
  display: flex;
  flex-direction: column;
  gap: var(--td-comp-margin-m);
}

.hero-copy {
  h1 {
    margin: 0;
    color: var(--td-text-color-primary);
    font: var(--td-font-title-large);
  }

  p {
    margin: var(--td-comp-margin-xs) 0 0;
    color: var(--td-text-color-secondary);
    font: var(--td-font-body-medium);
    line-height: var(--td-line-height-body-medium);
  }
}

.hero-keywords {
  display: flex;
  flex-wrap: wrap;
  gap: var(--td-comp-margin-xs);
  align-items: center;
  margin-top: var(--td-comp-margin-m);
  color: var(--td-text-color-secondary);
  font: var(--td-font-body-small);
}

.article-row {
  // N-07：原来给首条写 padding-top: 0，卡片自身还有一层内边距抵扣掉，
  // 结果首条标题与列表卡上边缘的距离小于条目之间，读起来像黏在标题下面。
  padding: var(--td-comp-paddingTB-l) 0;
  border-bottom: thin solid var(--td-component-stroke);

  &:last-child {
    border-bottom: 0;
  }

  p {
    margin: var(--td-comp-margin-s) 0 0;
    color: var(--td-text-color-secondary);
    font: var(--td-font-body-medium);
    line-height: var(--td-line-height-body-medium);
  }
}

.article-row__head {
  display: flex;
  flex-wrap: wrap;
  gap: var(--td-comp-margin-s);
  align-items: center;
}

.article-row__title {
  color: var(--td-text-color-primary);
  font: var(--td-font-title-medium);
  font-weight: 600;
  text-decoration: none;

  &:hover {
    color: var(--td-brand-color);
  }
}

.article-row__meta {
  display: flex;
  flex-wrap: wrap;
  gap: var(--td-comp-margin-m);
  margin-top: var(--td-comp-margin-s);
  color: var(--td-text-color-placeholder);
  font: var(--td-font-body-small);
}

.rank-list,
.recent-list {
  display: flex;
  flex-direction: column;
}

.rank-row,
.recent-row {
  display: flex;
  gap: var(--td-comp-margin-s);
  align-items: center;
  min-height: var(--td-comp-size-xxl);
  color: var(--td-text-color-primary);
  text-decoration: none;
  border-bottom: thin solid var(--td-component-stroke);

  &:last-child {
    border-bottom: 0;
  }

  &:hover {
    color: var(--td-brand-color);
  }
}

.rank-row {
  span {
    width: 1.5rem;
    color: var(--td-brand-color);
    font: var(--td-font-title-small);
    text-align: center;
  }

  strong {
    flex: 1;
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
}

.recent-row {
  flex-direction: column;
  align-items: flex-start;
  justify-content: center;
  padding: var(--td-comp-paddingTB-s) 0;

  strong,
  span {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    max-width: 100%;
  }

  span {
    color: var(--td-text-color-secondary);
    font: var(--td-font-body-small);
  }
}

.content-pagination {
  display: flex;
  justify-content: flex-end;
}

@media (max-width: @screen-lg-rem) {
  .content-list-layout {
    grid-template-columns: 1fr;
  }

  .content-sidebar {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: @screen-mobile-max) {
  // .hero-card 是 flex 纵向容器，原先与 .content-sidebar 合并声明 grid-template-columns 属于死规则，
  // 这里只保留真正需要单列的侧栏。
  .content-sidebar {
    grid-template-columns: 1fr;
  }

  // 单列时 grid 轨道默认按内容最小宽度撑开，而热门榜标题是 nowrap，
  // 会把卡片撑到 570px 以上并顶出屏幕；轨道与卡片显式归零让省略号生效
  .content-list-layout {
    grid-template-columns: minmax(0, 1fr);
  }

  .content-main,
  .content-sidebar,
  .content-card {
    min-width: 0;
  }

  // 手机端列表转卡片：每条内容自成一张卡，标题换行不截断，元信息纵向成组。
  // 桌面端仍是分隔线列表（本条断点内改动，桌面零影响）。
  .article-row {
    padding: var(--td-comp-paddingTB-m) var(--td-comp-paddingLR-m);
    background: var(--td-bg-color-container);
    border: thin solid var(--td-component-stroke);
    border-radius: var(--td-radius-medium);
    box-shadow: var(--td-shadow-1);

    &:last-child {
      border-bottom: thin solid var(--td-component-stroke);
    }

    p {
      overflow: hidden;
      display: -webkit-box;
      -webkit-line-clamp: 2;
      -webkit-box-orient: vertical;
    }
  }

  // 列表容器在手机端由卡片自带边框与阴影承载，去掉卡片壳的重复留白与描边
  .list-card :deep(.t-card__body) {
    display: flex;
    flex-direction: column;
    gap: var(--td-comp-margin-s);
    padding: var(--td-comp-paddingTB-s) var(--td-comp-paddingLR-s);
    background: transparent;
  }

  .article-row__head {
    flex-wrap: nowrap;
    align-items: flex-start;
  }

  .article-row__title {
    flex: 1;
    min-width: 0;
    font: var(--td-font-title-small);
    font-weight: 600;
    white-space: normal;
    overflow-wrap: anywhere;
  }

  .article-row__meta {
    gap: var(--td-comp-margin-xs) var(--td-comp-margin-m);
    margin-top: var(--td-comp-margin-s);
    font: var(--td-font-body-small);
  }

  .hero-copy h1 {
    font: var(--td-font-title-medium);
  }

  .hero-keywords {
    margin-top: var(--td-comp-margin-s);
  }

  // 分类筛选按钮在手机端换行排布，热区走 theme.css 的 44px 基线
  .category-card :deep(.t-space) {
    display: flex;
    flex-wrap: wrap;
    gap: var(--td-comp-margin-xs);
  }

  .content-pagination {
    justify-content: flex-start;
    overflow-x: auto;
  }
}
</style>
