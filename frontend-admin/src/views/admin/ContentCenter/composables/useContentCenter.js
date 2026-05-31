import { computed, reactive, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import adminApi from '@/api/admin'

export function useContentCenter() {
  const route = useRoute()

  const statusOptions = [
    { label: '草稿', value: 0 },
    { label: '已发布', value: 1 },
    { label: '已下线', value: 2 },
  ]

  const pinOptions = [
    { label: '仅看置顶', value: 1 },
    { label: '仅看普通', value: 0 },
  ]

  const loading = ref(false)
  const categoryLoading = ref(false)
  const articleDetailLoading = ref(false)
  const categorySaving = ref(false)
  const articleSaving = ref(false)
  const categoryDialogVisible = ref(false)
  const articleDialogVisible = ref(false)
  const categoryFormRef = ref(null)
  const articleFormRef = ref(null)

  const list = ref([])
  const categories = ref([])
  const total = ref(0)
  const page = ref(1)
  const pageSize = ref(20)

  const filters = reactive({
    keyword: '',
    category_id: null,
    status: null,
    is_pinned: null,
  })

  const categoryForm = reactive({
    id: null,
    name: '',
    slug: '',
    description: '',
    status: 1,
    sort_order: 0,
  })

  const articleForm = reactive({
    id: null,
    title: '',
    category_id: null,
    slug: '',
    summary: '',
    content: '',
    keywords: '',
    meta_title: '',
    meta_description: '',
    status: 1,
    is_pinned: 0,
    is_recommended: 0,
    cover_image: '',
    sort_order: 0,
    publish_at: '',
    operator: '',
    remark: '',
  })

  const categoryRules = {
    name: [{ required: true, message: '请输入分类名称', trigger: 'blur' }],
  }

  const articleRules = {
    title: [{ required: true, message: '请输入标题', trigger: 'blur' }],
    category_id: [{ required: true, message: '请选择分类', trigger: 'change' }],
    status: [{ required: true, message: '请选择状态', trigger: 'change' }],
    content: [{ required: true, message: '请输入正文内容', trigger: 'blur' }],
  }

  const currentContentType = computed(() => (
    route.meta.contentType === 'help' ? 'help' : 'notice'
  ))

  const contentMeta = {
    notice: { label: '系统公告', description: '维护平台公告、升级通知、风控提醒等系统级内容。' },
    help: { label: '帮助中心', description: '维护新手指南、常见问题、操作教程等帮助文章。' },
  }

  const pageTitle = computed(() => contentMeta[currentContentType.value]?.label || '系统公告')
  const pageDescription = computed(() => contentMeta[currentContentType.value]?.description || '')
  const currentArticleLabel = computed(() => (currentContentType.value === 'notice' ? '公告' : '帮助文章'))
  const articleDialogTitle = computed(() => (
    articleForm.id ? `编辑${currentArticleLabel.value}` : `新增${currentArticleLabel.value}`
  ))

  const activeFilterTags = computed(() => {
    const tags = []
    const category = categories.value.find((item) => Number(item.id) === Number(filters.category_id))
    const status = statusOptions.find((item) => Number(item.value) === Number(filters.status))
    const pin = pinOptions.find((item) => Number(item.value) === Number(filters.is_pinned))

    if (filters.keyword) {
      tags.push({ key: 'keyword', label: `关键词：${filters.keyword}` })
    }
    if (category) {
      tags.push({ key: 'category_id', label: `分类：${category.name}` })
    }
    if (status) {
      tags.push({ key: 'status', label: `状态：${status.label}` })
    }
    if (pin) {
      tags.push({ key: 'is_pinned', label: pin.label })
    }

    return tags
  })

  function formatCount(value) {
    return Number(value || 0).toLocaleString('zh-CN')
  }

  function statusLabel(value) {
    return ({
      0: '草稿',
      1: '已发布',
      2: '已下线',
    })[Number(value)] || '--'
  }

  function statusTagType(value) {
    return ({
      0: 'info',
      1: 'success',
      2: 'warning',
    })[Number(value)] || 'info'
  }

  function buildContentParams() {
    return {
      content_type: currentContentType.value,
      keyword: filters.keyword || undefined,
      category_id: filters.category_id || undefined,
      status: filters.status ?? undefined,
      is_pinned: filters.is_pinned ?? undefined,
      page: page.value,
      page_size: pageSize.value,
    }
  }

  function buildCategoryPayload() {
    return {
      content_type: currentContentType.value,
      name: categoryForm.name.trim(),
      slug: categoryForm.slug.trim() || null,
      description: categoryForm.description.trim() || null,
      status: Number(categoryForm.status),
      sort_order: Number(categoryForm.sort_order || 0),
    }
  }

  function buildArticlePayload() {
    return {
      content_type: currentContentType.value,
      category_id: Number(articleForm.category_id),
      title: articleForm.title.trim(),
      slug: articleForm.slug.trim() || null,
      summary: articleForm.summary.trim() || null,
      content: articleForm.content,
      keywords: articleForm.keywords.trim() || null,
      meta_title: articleForm.meta_title.trim() || null,
      meta_description: articleForm.meta_description.trim() || null,
      status: Number(articleForm.status),
      is_pinned: Number(articleForm.is_pinned),
      is_recommended: Number(articleForm.is_recommended),
      cover_image: articleForm.cover_image || null,
      sort_order: Number(articleForm.sort_order || 0),
      publish_at: articleForm.publish_at || null,
      operator: articleForm.operator.trim() || null,
      remark: articleForm.remark.trim() || null,
    }
  }

  function resetFiltersState() {
    filters.keyword = ''
    filters.category_id = null
    filters.status = null
    filters.is_pinned = null
    page.value = 1
  }

  function resetCategoryForm() {
    categoryForm.id = null
    categoryForm.name = ''
    categoryForm.slug = ''
    categoryForm.description = ''
    categoryForm.status = 1
    categoryForm.sort_order = 0
    categoryFormRef.value?.clearValidate?.()
  }

  function resetArticleForm() {
    articleForm.id = null
    articleForm.title = ''
    articleForm.category_id = null
    articleForm.slug = ''
    articleForm.summary = ''
    articleForm.content = ''
    articleForm.keywords = ''
    articleForm.meta_title = ''
    articleForm.meta_description = ''
    articleForm.status = 1
    articleForm.is_pinned = 0
    articleForm.is_recommended = 0
    articleForm.cover_image = ''
    articleForm.sort_order = 0
    articleForm.publish_at = ''
    articleForm.operator = ''
    articleForm.remark = ''
  }

  function resetArticleValidate() {
    articleFormRef.value?.clearValidate?.()
  }

  function fillCategoryForm(row) {
    categoryForm.id = Number(row.id)
    categoryForm.name = row.name || ''
    categoryForm.slug = row.slug || ''
    categoryForm.description = row.description || ''
    categoryForm.status = Number(row.status ?? 1)
    categoryForm.sort_order = Number(row.sort_order || 0)
  }

  function fillArticleForm(data) {
    articleForm.id = Number(data.id)
    articleForm.title = data.title || ''
    articleForm.category_id = data.category_id ? Number(data.category_id) : null
    articleForm.slug = data.slug || ''
    articleForm.summary = data.summary || ''
    articleForm.content = data.content || ''
    articleForm.keywords = data.keywords || ''
    articleForm.meta_title = data.meta_title || ''
    articleForm.meta_description = data.meta_description || ''
    articleForm.status = Number(data.status ?? 1)
    articleForm.is_pinned = Number(data.is_pinned ?? 0)
    articleForm.is_recommended = Number(data.is_recommended ?? 0)
    articleForm.cover_image = data.cover_image || ''
    articleForm.sort_order = Number(data.sort_order || 0)
    articleForm.publish_at = data.publish_at || ''
    articleForm.operator = data.operator || ''
    articleForm.remark = data.remark || ''
  }

  async function loadCategories() {
    categoryLoading.value = true
    try {
      const res = await adminApi.content.categories.list({ content_type: currentContentType.value })
      categories.value = res.data || []
    } catch {
      categories.value = []
    } finally {
      categoryLoading.value = false
    }
  }

  async function loadArticles() {
    loading.value = true
    try {
      const res = await adminApi.content.articles.list(buildContentParams())
      list.value = res.data?.list || []
      total.value = res.data?.total || 0
      page.value = res.data?.page || page.value
      pageSize.value = res.data?.page_size || pageSize.value
    } catch {
      list.value = []
      total.value = 0
    } finally {
      loading.value = false
    }
  }

  async function loadAll() {
    await Promise.allSettled([loadCategories(), loadArticles()])
  }

  function handleSearch() {
    page.value = 1
    loadArticles()
  }

  function resetFilters() {
    resetFiltersState()
    loadArticles()
  }

  function clearFilter(key) {
    if (!(key in filters)) {
      return
    }

    filters[key] = null
    if (key === 'keyword') {
      filters.keyword = ''
    }
    page.value = 1
    loadArticles()
  }

  function applyCategoryFilter(categoryId) {
    filters.category_id = categoryId ? Number(categoryId) : null
    page.value = 1
    loadArticles()
  }

  function openCategoryDialog() {
    categoryDialogVisible.value = true
    resetCategoryForm()
  }

  async function submitCategory() {
    const valid = await categoryFormRef.value?.validate?.().catch(() => false)
    if (valid === false) {
      return
    }

    categorySaving.value = true
    try {
      const payload = buildCategoryPayload()
      if (categoryForm.id) {
        await adminApi.content.categories.update(categoryForm.id, payload)
        ElMessage.success('分类已更新')
      } else {
        await adminApi.content.categories.create(payload)
        ElMessage.success('分类已创建')
      }

      await Promise.allSettled([loadCategories(), loadArticles()])
      resetCategoryForm()
    } finally {
      categorySaving.value = false
    }
  }

  async function handleDeleteCategory(row) {
    try {
      await ElMessageBox.confirm(`确认删除分类"${row.name}"吗？`, '删除分类', {
        type: 'warning',
        confirmButtonText: '确认删除',
        cancelButtonText: '取消',
      })
    } catch {
      return
    }

    await adminApi.content.categories.delete(row.id)
    ElMessage.success('分类已删除')

    if (Number(filters.category_id) === Number(row.id)) {
      filters.category_id = null
      page.value = 1
    }

    if (Number(categoryForm.id) === Number(row.id)) {
      resetCategoryForm()
    }

    await Promise.allSettled([loadCategories(), loadArticles()])
  }

  async function openCreateArticleDialog() {
    if (!categories.value.length) {
      ElMessage.warning(`请先创建${currentArticleLabel.value}分类`)
      openCategoryDialog()
      return
    }

    resetArticleForm()
    articleDialogVisible.value = true
  }

  async function openEditArticleDialog(id) {
    articleDialogVisible.value = true
    articleDetailLoading.value = true
    resetArticleForm()

    try {
      const res = await adminApi.content.articles.detail(id)
      fillArticleForm(res.data || {})
    } catch {
      articleDialogVisible.value = false
    } finally {
      articleDetailLoading.value = false
    }
  }

  async function submitArticle() {
    const valid = await articleFormRef.value?.validate?.().catch(() => false)
    if (valid === false) {
      return
    }

    articleSaving.value = true
    try {
      const payload = buildArticlePayload()
      if (articleForm.id) {
        await adminApi.content.articles.update(articleForm.id, payload)
        ElMessage.success(`${currentArticleLabel.value}已更新`)
      } else {
        await adminApi.content.articles.create(payload)
        ElMessage.success(`${currentArticleLabel.value}已创建`)
      }

      articleDialogVisible.value = false
      await Promise.allSettled([loadCategories(), loadArticles()])
    } finally {
      articleSaving.value = false
    }
  }

  async function handleDeleteArticle(row) {
    try {
      await ElMessageBox.confirm(`确认删除"${row.title}"吗？此操作不可恢复。`, '删除内容', {
        type: 'warning',
        confirmButtonText: '确认删除',
        cancelButtonText: '取消',
      })
    } catch {
      return
    }

    await adminApi.content.articles.delete(row.id)
    ElMessage.success(`${currentArticleLabel.value}已删除`)

    if (list.value.length === 1 && page.value > 1) {
      page.value -= 1
    }

    await Promise.allSettled([loadCategories(), loadArticles()])
  }

  watch(currentContentType, async () => {
    resetFiltersState()
    resetCategoryForm()
    resetArticleForm()
    categoryDialogVisible.value = false
    articleDialogVisible.value = false
    await loadAll()
  }, { immediate: true })

  return {
    // constants
    statusOptions,
    pinOptions,
    categoryRules,
    articleRules,
    // state
    loading,
    categoryLoading,
    articleDetailLoading,
    categorySaving,
    articleSaving,
    categoryDialogVisible,
    articleDialogVisible,
    categoryFormRef,
    articleFormRef,
    list,
    categories,
    total,
    page,
    pageSize,
    filters,
    categoryForm,
    articleForm,
    // computed
    pageTitle,
    pageDescription,
    currentArticleLabel,
    articleDialogTitle,
    activeFilterTags,
    // methods
    formatCount,
    statusLabel,
    statusTagType,
    loadArticles,
    loadAll,
    handleSearch,
    resetFilters,
    clearFilter,
    applyCategoryFilter,
    openCategoryDialog,
    submitCategory,
    handleDeleteCategory,
    fillCategoryForm,
    openCreateArticleDialog,
    openEditArticleDialog,
    submitArticle,
    handleDeleteArticle,
    resetArticleValidate,
    resetCategoryForm,
  }
}
