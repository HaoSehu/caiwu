import { computed, onMounted, reactive, ref, shallowRef } from 'vue';
import { DialogPlugin, MessagePlugin } from 'tdesign-vue-next';
import { ORDER_STATUS_MAP, getStatusLabel, toSelectOptions } from '@caiwu/shared/statusConfig';

import clientApi from '@/api/client';
import { formatMoney } from '@/utils/format';
import type { OrderListSummary, OrderRecord } from '@/types/client';

export const ORDER_STATUS_OPTIONS = toSelectOptions(ORDER_STATUS_MAP, false);

export const ORDER_TYPE_OPTIONS = [
  { label: '新购', value: 'new' },
  { label: '续费', value: 'renew' },
  { label: '附加配置', value: 'addon' },
];

function getErrorMessage(error: unknown, fallback: string) {
  return error instanceof Error && error.message ? error.message : fallback;
}

export { formatMoney };

export function orderProductDisplay(row: Pick<OrderRecord, 'product_full_path' | 'product_name'> | null | undefined) {
  return String(row?.product_full_path || row?.product_name || '').trim() || '--';
}

export function useOrderList(options: { pageSize?: number } = {}) {
  const loading = ref(false);
  const summaryLoading = ref(false);
  const canceling = ref(false);
  const list = shallowRef<OrderRecord[]>([]);
  const total = ref(0);
  const summary = shallowRef<OrderListSummary>({});
  const filters = reactive({
    page: 1,
    page_size: Number(options.pageSize || 10),
    keyword: '',
    status: '' as string | number,
    type: '',
    quickFilter: '' as string,
  });

  const metricCards = computed(() => [
    {
      key: 'pending',
      label: '待支付订单',
      value: Number(summary.value.pending || 0),
      copy: `待付 ¥${formatMoney(summary.value.unpaid_amount || 0)}`,
    },
    {
      key: 'month',
      label: '本月订单金额',
      value: `¥${formatMoney(summary.value.month_amount || 0)}`,
      copy: '当月创建的订单金额',
    },
    {
      key: 'total',
      label: '订单总数',
      value: Number(summary.value.total || 0),
      copy: '所有状态的订单',
    },
  ]);

  function buildParams() {
    const params: Record<string, string | number> = {
      page: filters.page,
      page_size: filters.page_size,
    };
    if (filters.keyword?.trim()) params.keyword = filters.keyword.trim();
    if (filters.status !== '' && filters.status !== null && filters.status !== undefined) params.status = filters.status;
    if (filters.type?.trim()) params.type = filters.type.trim();
    return params;
  }

  async function loadSummary() {
    summaryLoading.value = true;
    try {
      const res = await clientApi.orderSummary(buildParams());
      summary.value = res.data || {};
    } catch {
      summary.value = {};
    } finally {
      summaryLoading.value = false;
    }
  }

  async function loadList() {
    loading.value = true;
    try {
      const res = await clientApi.orders(buildParams());
      const payload = res.data;
      list.value = payload && !Array.isArray(payload) && Array.isArray(payload.list) ? payload.list : [];
      total.value = payload && !Array.isArray(payload) ? Number(payload.total || 0) : 0;
    } catch (error: unknown) {
      MessagePlugin.error(getErrorMessage(error, '订单列表加载失败'));
    } finally {
      loading.value = false;
    }
  }

  async function loadData() {
    await Promise.allSettled([loadSummary(), loadList()]);
  }

  function handleSearch() {
    filters.page = 1;
    void loadData();
  }

  function handlePageSizeChange() {
    filters.page = 1;
    void loadList();
  }

  function resetFilters() {
    filters.page = 1;
    filters.page_size = Number(options.pageSize || 10);
    filters.keyword = '';
    filters.status = '';
    filters.type = '';
    filters.quickFilter = '';
    void loadData();
  }

  function applyQuickFilter(key: string) {
    filters.quickFilter = key;
    filters.page = 1;
    filters.status = '';
    filters.type = '';

    if (key === 'pending') {
      filters.status = 0;
    }
    void loadData();
  }

  function cancelOrder(row: OrderRecord) {
    const dialog = DialogPlugin.confirm({
      header: '取消订单',
      body: '确定取消该订单？取消后不可恢复。\n关联的账单也将被取消，使用的优惠券将退回账户。\n如果是新产品购买，库存将释放。',
      confirmBtn: '确认取消',
      cancelBtn: '再想想',
      theme: 'warning',
      async onConfirm() {
        dialog.setConfirmLoading(true);
        canceling.value = true;
        try {
          await clientApi.cancelOrder(row.id);
          MessagePlugin.success('订单已取消');
          await loadData();
          dialog.hide();
        } catch (error: unknown) {
          MessagePlugin.error(getErrorMessage(error, '取消订单失败'));
        } finally {
          canceling.value = false;
          dialog.setConfirmLoading(false);
        }
      },
    });
  }

  onMounted(() => {
    void loadData();
  });

  return {
    loading,
    summaryLoading,
    canceling,
    list,
    total,
    summary,
    filters,
    metricCards,
    loadList,
    loadData,
    loadSummary,
    handleSearch,
    handlePageSizeChange,
    resetFilters,
    applyQuickFilter,
    cancelOrder,
  };
}

export function useOrderDetail() {
  const loading = ref(false);
  const canceling = ref(false);
  const detail = shallowRef<OrderRecord | null>(null);

  async function loadDetail(id: number | string) {
    if (!id) return;
    loading.value = true;
    try {
      const res = await clientApi.orderDetail(id);
      detail.value = res.data || null;
    } catch (error: unknown) {
      MessagePlugin.error(getErrorMessage(error, '订单详情加载失败'));
    } finally {
      loading.value = false;
    }
  }

  function cancelOrder(onSuccess?: () => void) {
    if (!detail.value) return;
    const row = detail.value;
    const dialog = DialogPlugin.confirm({
      header: '取消订单',
      body: '确定取消该订单？取消后不可恢复。\n关联的账单也将被取消，使用的优惠券将退回账户。\n如果是新产品购买，库存将释放。',
      confirmBtn: '确认取消',
      cancelBtn: '再想想',
      theme: 'warning',
      async onConfirm() {
        dialog.setConfirmLoading(true);
        canceling.value = true;
        try {
          await clientApi.cancelOrder(row.id);
          MessagePlugin.success('订单已取消');
          await loadDetail(row.id);
          onSuccess?.();
          dialog.hide();
        } catch (error: unknown) {
          MessagePlugin.error(getErrorMessage(error, '取消订单失败'));
        } finally {
          canceling.value = false;
          dialog.setConfirmLoading(false);
        }
      },
    });
  }

  return {
    loading,
    canceling,
    detail,
    loadDetail,
    cancelOrder,
  };
}
