import { reactive, ref } from 'vue'

function cloneValue(value) {
  if (Array.isArray(value)) {
    return [...value]
  }

  if (value && typeof value === 'object') {
    return { ...value }
  }

  return value
}

function cloneShape(source) {
  return Object.fromEntries(
    Object.entries(source).map(([key, value]) => [key, cloneValue(value)])
  )
}

export function usePagedQuery({
  createFilters,
  initialPerPage = 15,
  query,
  onSuccess,
  onError,
}) {
  const loading = ref(false)
  const filters = reactive(cloneShape(createFilters()))
  const pagination = reactive({
    page: 1,
    per_page: initialPerPage,
    total: 0,
  })

  function resetFilterState() {
    const nextFilters = cloneShape(createFilters())

    for (const key of Object.keys(filters)) {
      if (!(key in nextFilters)) {
        delete filters[key]
      }
    }

    for (const [key, value] of Object.entries(nextFilters)) {
      filters[key] = value
    }
  }

  async function loadData() {
    loading.value = true

    try {
      const result = await query({
        filters: cloneShape(filters),
        pagination: { ...pagination },
      })

      if (result?.pagination) {
        pagination.total = Number(result.pagination.total || 0)
        pagination.page = Number(result.pagination.page || pagination.page)
        pagination.per_page = Number(result.pagination.per_page || pagination.per_page)
      }

      if (typeof onSuccess === 'function') {
        onSuccess(result)
      }
    } catch (error) {
      if (typeof onError === 'function') {
        onError(error)
      } else {
        throw error
      }
    } finally {
      loading.value = false
    }
  }

  function handleSearch() {
    pagination.page = 1
    return loadData()
  }

  function handleReset() {
    resetFilterState()
    pagination.page = 1
    return loadData()
  }

  function handlePageChange(page) {
    pagination.page = page
    return loadData()
  }

  function handleSizeChange(size) {
    pagination.per_page = size
    pagination.page = 1
    return loadData()
  }

  return {
    loading,
    filters,
    pagination,
    loadData,
    handleSearch,
    handleReset,
    handlePageChange,
    handleSizeChange,
  }
}
