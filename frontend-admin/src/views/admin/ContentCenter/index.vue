<template>
  <div class="page-container admin-page content-page">
    <section class="admin-page-head">
      <div class="admin-page-heading">
        <span class="admin-page-kicker">内容中心</span>
        <h2>{{ pageTitle }}</h2>
        <p>{{ pageDescription }}</p>
      </div>
    </section>

    <ContentToolbar
      :content-tabs="contentTabs"
      :current-content-type="currentContentType"
      :current-article-label="currentArticleLabel"
      :categories="categories"
      :status-options="statusOptions"
      :pin-options="pinOptions"
      :active-filter-tags="activeFilterTags"
      :filters="filters"
      @switch-type="switchContentType"
      @refresh="loadAll"
      @open-category="openCategoryDialog"
      @create="openCreateArticleDialog"
      @search="handleSearch"
      @reset-filters="resetFilters"
      @category-filter="applyCategoryFilter"
      @clear-filter="clearFilter"
    />

    <ContentList
      :list="list"
      :loading="loading"
      :total="total"
      :page-title="pageTitle"
      :current-article-label="currentArticleLabel"
      :current-page="page"
      :current-page-size="pageSize"
      @edit="openEditArticleDialog"
      @delete="handleDeleteArticle"
      @create="openCreateArticleDialog"
      @reset-filters="resetFilters"
      @page-change="loadArticles"
    />

    <ContentCategoryPanel
      v-model="categoryDialogVisible"
      :categories="categories"
      :category-loading="categoryLoading"
      :category-saving="categorySaving"
      :category-form="categoryForm"
      :category-form-ref="categoryFormRef"
      :category-rules="categoryRules"
      :current-article-label="currentArticleLabel"
      @submit="submitCategory"
      @delete="handleDeleteCategory"
      @fill-form="fillCategoryForm"
      @reset-form="resetCategoryForm"
    />

    <ContentEditor
      v-model="articleDialogVisible"
      :article-form="articleForm"
      :article-form-ref="articleFormRef"
      :article-dialog-title="articleDialogTitle"
      :page-title="pageTitle"
      :article-detail-loading="articleDetailLoading"
      :article-saving="articleSaving"
      :categories="categories"
      :status-options="statusOptions"
      :article-rules="articleRules"
      @submit="submitArticle"
      @closed="resetArticleValidate"
    />
  </div>
</template>

<script setup>
import { onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useContentCenter } from './composables/useContentCenter'
import ContentToolbar from './components/ContentToolbar.vue'
import ContentList from './components/ContentList.vue'
import ContentCategoryPanel from './components/ContentCategoryPanel.vue'
import ContentEditor from './components/ContentEditor.vue'

const router = useRouter()
const route = useRoute()

const {
  contentTabs, statusOptions, pinOptions, categoryRules, articleRules,
  loading, categoryLoading, articleDetailLoading, categorySaving, articleSaving,
  categoryDialogVisible, articleDialogVisible, categoryFormRef, articleFormRef,
  list, categories, total, page, pageSize, filters, categoryForm, articleForm,
  currentContentType, pageTitle, pageDescription, currentArticleLabel,
  articleDialogTitle, activeFilterTags,
  loadArticles, switchContentType, handleSearch, resetFilters,
  clearFilter, applyCategoryFilter, openCategoryDialog, submitCategory, handleDeleteCategory,
  fillCategoryForm, openCreateArticleDialog, openEditArticleDialog, submitArticle,
  handleDeleteArticle, resetArticleValidate, resetCategoryForm,
} = useContentCenter()

onMounted(() => {
  if (!route.meta.contentType) {
    router.replace('/admin/content/notices')
  }
})
</script>

