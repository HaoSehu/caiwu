<template>
  <section class="content-detail-page">
    <div class="reader-breadcrumb">
      <router-link :to="backToListRoute">{{ config.pageTitle }}</router-link>
      <chevron-right-icon />
      <span>{{ currentCategoryName }}</span>
      <chevron-right-icon />
      <strong>{{ currentArticle?.title || config.detailTitle }}</strong>
    </div>

    <div class="reader-layout">
      <main class="reader-main">
        <t-card class="reader-card" :bordered="false">
          <data-state
            :loading="loading"
            :error="!loading && Boolean(detailLoadError)"
            :error-text="detailLoadError"
            :empty="!loading && !detailLoadError && !currentArticle"
            :description="config.emptyText"
            @retry="syncPage"
          >
            <article id="article-top" class="reader-article">
              <header class="reader-header">
                <h1>{{ currentArticle?.title || config.detailTitle }}</h1>
                <div v-if="currentArticle" class="reader-meta">
                  <span>发布人：{{ currentPublisher }}</span>
                  <span>{{ timeLabel }}：{{ currentPublishTime }}</span>
                  <span>阅读量：{{ currentArticle.view_count || 0 }}</span>
                </div>
              </header>

              <t-divider v-if="currentArticle" />
              <div class="reader-content" v-html="articleContentHtml"></div>
            </article>
          </data-state>
        </t-card>
      </main>

      <aside class="reader-sidebar">
        <t-card class="reader-card" :bordered="false">
          <template #title>{{ config.categoryTitle }}</template>
          <div class="category-list">
            <t-button
              v-for="item in categories"
              :key="item.id"
              :theme="Number(item.id) === Number(currentCategoryId) ? 'primary' : 'default'"
              variant="outline"
              block
              @click="goCategoryList(item.id)"
            >
              {{ item.name }}
            </t-button>
          </div>
        </t-card>

        <t-card class="reader-card" :bordered="false">
          <template #title>目录结构</template>
          <button
            v-for="item in tocItems"
            :key="item.id"
            type="button"
            class="toc-row"
            @click="scrollToAnchor(item.id)"
          >
            {{ item.label }}
          </button>
        </t-card>
      </aside>
    </div>
  </section>
</template>
<script setup lang="ts">
import DataState from '@caiwu/shared/user-v3/components/DataState.vue';
import { ChevronRightIcon } from 'tdesign-icons-vue-next';

import { useContentDetail } from '@/domains/content/useContent';

const props = defineProps<{
  contentType: 'notice' | 'help';
}>();

const {
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
} = useContentDetail(props.contentType);
</script>
<style scoped lang="less">
.content-detail-page {
  display: flex;
  flex-direction: column;
  gap: var(--td-comp-margin-m);
  // padding 由 Starter 布局层统一提供
}

.reader-breadcrumb {
  display: flex;
  flex-wrap: wrap;
  gap: var(--td-comp-margin-xs);
  align-items: center;
  color: var(--td-text-color-secondary);
  font: var(--td-font-body-small);

  a {
    color: var(--td-text-color-secondary);
    text-decoration: none;
  }

  strong {
    color: var(--td-text-color-primary);
    font-weight: 500;
  }
}

.reader-layout {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(18rem, 24%);
  gap: var(--td-comp-margin-m);
  align-items: start;
}

.reader-card {
  background: var(--td-bg-color-container);
  border: thin solid var(--td-component-stroke);
  border-radius: var(--td-radius-medium);
  box-shadow: var(--td-shadow-1);
}

.reader-header {
  h1 {
    margin: 0;
    color: var(--td-text-color-primary);
    font: var(--td-font-title-large);
    line-height: var(--td-line-height-headline-medium);
  }
}

.reader-meta {
  display: flex;
  flex-wrap: wrap;
  gap: var(--td-comp-margin-m);
  margin-top: var(--td-comp-margin-m);
  padding: var(--td-comp-paddingTB-s) var(--td-comp-paddingLR-m);
  color: var(--td-text-color-secondary);
  background: var(--td-brand-color-light);
  border: thin solid var(--td-brand-color-focus);
  border-radius: var(--td-radius-medium);
  font: var(--td-font-body-small);
}

/*
  N-12：目录锚点跳转原先会被布局层吸顶的路由页签挡住标题。
  .tdesign-starter-layout 是滚动容器（src/style/layout.less:132-133），子元素 -tabs-nav
  绑了 position: sticky / top: 0（LayoutContent.vue:10 的行内样式，会盖过 layout.less:135-139
  的 position: fixed），scrollIntoView({ block: 'start' }) 把目标顶到容器上沿后页签正压在上面。
  scroll-margin-top 让浏览器滚动时预留这段距离，是这条链路里唯一不用改布局层的解法
  （全仓此前没有任何 scroll-margin / scroll-padding）。预留量按页签栏保守估算：一档控件高加一段间隙。
*/
.reader-article {
  scroll-margin-top: calc(var(--td-comp-size-xxl) + var(--td-comp-margin-s));
}

.reader-content {
  color: var(--td-text-color-primary);
  font: var(--td-font-body-medium);
  line-height: var(--td-line-height-body-large);
  overflow-wrap: break-word;

  :deep(h1),
  :deep(h2),
  :deep(h3),
  :deep(h4) {
    margin: var(--td-comp-margin-l) 0 var(--td-comp-margin-s);
    color: var(--td-text-color-primary);
    font-weight: 600;
    // N-12：同上，正文小标题被目录跳转顶到固定页签下面
    scroll-margin-top: calc(var(--td-comp-size-xxl) + var(--td-comp-margin-s));
  }

  :deep(p),
  :deep(ul),
  :deep(ol),
  :deep(pre),
  :deep(blockquote),
  :deep(table) {
    margin: 0 0 var(--td-comp-margin-m);
  }

  :deep(a) {
    color: var(--td-brand-color);
  }

  :deep(pre),
  :deep(code),
  :deep(blockquote),
  :deep(th) {
    background: var(--td-bg-color-component);
  }

  :deep(pre) {
    padding: var(--td-comp-paddingTB-m) var(--td-comp-paddingLR-m);
    overflow-x: auto;
  }

  :deep(code) {
    padding: var(--td-comp-paddingTB-xxs) var(--td-comp-paddingLR-xs);
    border-radius: var(--td-radius-small);
  }

  :deep(blockquote) {
    padding: var(--td-comp-paddingTB-s) var(--td-comp-paddingLR-m);
    // 原误用尺寸令牌当边框宽度（过粗），改用引用块标准左边线
    border-left: 0.25rem solid var(--td-brand-color);
  }

  :deep(table) {
    width: 100%;
    border-collapse: collapse;
  }

  :deep(th),
  :deep(td) {
    padding: var(--td-comp-paddingTB-s) var(--td-comp-paddingLR-s);
    border: thin solid var(--td-component-stroke);
    text-align: left;
  }

  :deep(img) {
    max-width: 100%;
    height: auto;
  }
}

.reader-sidebar {
  display: flex;
  flex-direction: column;
  gap: var(--td-comp-margin-m);
}

.category-list {
  display: flex;
  flex-direction: column;
  gap: var(--td-comp-margin-s);

  :deep(.t-button) {
    width: 100%;
    box-sizing: border-box;
  }

  :deep(.t-button + .t-button) {
    margin-left: 0;
  }
}

.toc-row {
  width: 100%;
  // 原纵向内边距偏小、行高约 28 未达触控基线，改为等比放大
  min-height: var(--td-comp-size-l);
  padding: var(--td-comp-paddingTB-s) 0;
  color: var(--td-text-color-secondary);
  background: transparent;
  border: 0;
  border-bottom: thin solid var(--td-component-stroke);
  cursor: pointer;
  font: var(--td-font-body-small);
  text-align: left;

  &:hover {
    color: var(--td-brand-color);
  }
}

@media (max-width: @screen-lg-rem) {
  .reader-layout {
    grid-template-columns: 1fr;
  }
}
</style>
