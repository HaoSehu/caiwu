import { onMounted, reactive, ref } from 'vue';
import type { Ref } from 'vue';

export interface UseListPageOptions<F, T> {
  /** 列表请求函数，接收 { ...filters, page, page_size }，返回 { list, total } */
  fetch: (params: F & { page: number; page_size: number }) => Promise<{ list: T[]; total: number }>;
  /** 默认筛选值 */
  defaultFilters: F;
  /** 默认每页条数 */
  defaultPageSize?: number;
  /** 是否 onMounted 自动加载 */
  immediate?: boolean;
}

export function useListPage<F extends Record<string, any>, T>(options: UseListPageOptions<F, T>) {
  const { fetch, defaultFilters, defaultPageSize = 20, immediate = true } = options;

  const filters = reactive({ ...defaultFilters }) as F;
  const list: Ref<T[]> = ref([]);
  const total = ref(0);
  const loading = ref(false);
  const pagination = reactive({
    page: 1,
    page_size: defaultPageSize,
    pageSizeOptions: [20, 50, 100],
  });

  async function loadList() {
    loading.value = true;
    try {
      const res = await fetch({
        ...filters,
        page: pagination.page,
        page_size: pagination.page_size,
      });
      list.value = res.list || [];
      total.value = res.total || 0;
    } finally {
      loading.value = false;
    }
  }

  function handleSearch() {
    pagination.page = 1;
    return loadList();
  }

  function resetFilters() {
    Object.assign(filters, defaultFilters);
    pagination.page = 1;
    return loadList();
  }

  function handlePageChange(page: number) {
    pagination.page = page;
    return loadList();
  }

  function handlePageSizeChange(size: number) {
    pagination.page_size = size;
    pagination.page = 1;
    return loadList();
  }

  if (immediate) onMounted(loadList);

  return {
    filters,
    list,
    total,
    loading,
    pagination,
    loadList,
    handleSearch,
    resetFilters,
    handlePageChange,
    handlePageSizeChange,
  };
}
