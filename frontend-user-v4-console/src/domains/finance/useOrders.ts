import { ORDER_STATUS_FILTER_OPTIONS, ORDER_TYPE_MAP } from '@caiwu/shared/statusConfig';
import { DialogPlugin, MessagePlugin } from 'tdesign-vue-next';
import { ref, shallowRef } from 'vue';

import clientApi from '@/api/client';
import type { OrderRecord } from '@/types/client';
import { getErrorMessage } from '@/utils/error';
import { formatMoney } from '@/utils/format';

import { useRecordList } from './useRecords';

export const ORDER_STATUS_OPTIONS = ORDER_STATUS_FILTER_OPTIONS;

export const ORDER_TYPE_OPTIONS = ['new', 'renew', 'upgrade'].map((value) => ({
  label: ORDER_TYPE_MAP[value] || value,
  value,
}));

export { formatMoney };

export function orderProductDisplay(row: Pick<OrderRecord, 'product_full_path' | 'product_name'> | null | undefined) {
  return String(row?.product_full_path || row?.product_name || '').trim() || '--';
}

export function useOrderList() {
  // 取消中的订单 id 集合：按行隔离 loading，避免取消某一行时所有行按钮同时转圈
  const cancelingIds = ref(new Set<number>());
  const isCanceling = (id: number | string) => cancelingIds.value.has(Number(id));
  const {
    loading,
    list,
    total,
    filters,
    hasRows,
    hasActiveFilters,
    loadError,
    loadErrorText,
    loadList,
    handleSearch,
    handlePageSizeChange,
    applyQuickFilter,
    dateRange,
  } = useRecordList<OrderRecord>({
    fetcher: (params) => clientApi.orders(params),
    errorMessage: '订单列表加载失败',
  });

  function cancelOrder(row: OrderRecord) {
    const dialog = DialogPlugin.confirm({
      header: '取消订单',
      body: '确定取消该订单？取消后不可恢复，关联账单将一并取消，使用的优惠券会退回账户；新产品购买的库存将被释放。',
      confirmBtn: '确定',
      cancelBtn: '取消',
      theme: 'warning',
      async onConfirm() {
        dialog.setConfirmLoading(true);
        cancelingIds.value.add(Number(row.id));
        try {
          await clientApi.cancelOrder(row.id);
          MessagePlugin.success('订单已取消');
          await loadList();
          dialog.hide();
        } catch (error: unknown) {
          MessagePlugin.error(getErrorMessage(error, '取消订单失败'));
        } finally {
          cancelingIds.value.delete(Number(row.id));
          dialog.setConfirmLoading(false);
        }
      },
    });
  }

  return {
    loading,
    isCanceling,
    list,
    total,
    filters,
    hasRows,
    hasActiveFilters,
    loadError,
    loadErrorText,
    loadList,
    handleSearch,
    handlePageSizeChange,
    applyQuickFilter,
    dateRange,
    cancelOrder,
  };
}

export function useOrderDetail() {
  const loading = ref(false);
  const canceling = ref(false);
  const detail = shallowRef<OrderRecord | null>(null);
  const loadError = ref(false);
  const loadErrorText = ref('');

  // 并发守卫：快速切换订单详情时丢弃晚返回的旧请求结果，避免旧数据覆盖当前路由的最新态。
  let requestSeq = 0;

  async function loadDetail(id: number | string) {
    const validId = Number(id);
    if (!Number.isInteger(validId) || validId <= 0) {
      // 无效 ID（0/负数/NaN）：失效进行中的请求并复位详情与加载态，避免调用接口。
      invalidateDetail();
      return;
    }
    const seq = ++requestSeq;
    loading.value = true;
    loadError.value = false;
    loadErrorText.value = '';
    try {
      const res = await clientApi.orderDetail(validId);
      if (seq !== requestSeq) {
        return;
      }
      detail.value = res.data || null;
    } catch (error: unknown) {
      if (seq !== requestSeq) {
        return;
      }
      // 错误交给页面用 DataState 呈现（含重试），此处不再额外弹 toast 造成双重反馈
      detail.value = null;
      loadError.value = true;
      loadErrorText.value = getErrorMessage(error, '订单详情加载失败');
    } finally {
      if (seq === requestSeq) {
        loading.value = false;
      }
    }
  }

  function invalidateDetail(): void {
    requestSeq += 1; // 使进行中的旧请求失效
    detail.value = null;
    loading.value = false;
    loadError.value = false;
    loadErrorText.value = '';
  }

  function cancelOrder(onSuccess?: () => void) {
    if (!detail.value) return;
    const row = detail.value;
    const dialog = DialogPlugin.confirm({
      header: '取消订单',
      body: '确定取消该订单？取消后不可恢复，关联账单将一并取消，使用的优惠券会退回账户；新产品购买的库存将被释放。',
      confirmBtn: '确定',
      cancelBtn: '取消',
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
    loadError,
    loadErrorText,
    loadDetail,
    invalidateDetail,
    cancelOrder,
  };
}
