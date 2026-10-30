import { ORDER_STATUS_FILTER_OPTIONS, ORDER_TYPE_MAP } from '@caiwu/shared/statusConfig';
import { DialogPlugin } from 'tdesign-vue-next/es/dialog/plugin';
import { MessagePlugin } from 'tdesign-vue-next/es/message/plugin';
import { ref, shallowRef } from 'vue';

import clientApi from '@/api/client';
import type { OrderRecord } from '@/types/client';
import { getErrorMessage } from '@/utils/error';

import { useRecordList } from './useRecords';

export const ORDER_STATUS_OPTIONS = ORDER_STATUS_FILTER_OPTIONS;

export const ORDER_TYPE_OPTIONS = ['new', 'renew', 'upgrade'].map((value) => ({
  label: ORDER_TYPE_MAP[value] || value,
  value,
}));

export function orderProductDisplay(row: Pick<OrderRecord, 'product_full_path' | 'product_name'> | null | undefined) {
  return String(row?.product_full_path || row?.product_name || '').trim() || '--';
}

type OrderPayabilityRow = Pick<OrderRecord, 'status' | 'invoice'> | null | undefined;

/** 关联账单 id：缺失或脏数据（非正整数）一律视为没有可支付的账单。 */
function linkedInvoiceId(row: OrderPayabilityRow): number {
  const id = Number(row?.invoice?.id);
  return Number.isInteger(id) && id > 0 ? id : 0;
}

/**
 * 待支付订单是否还能进入支付页：订单待支付、关联账单存在且本身待支付、账单支付窗口未关闭。
 * 窗口标记由后端按统一判据下发（续费账单与挂账单跟随 due_date，其余按 5 分钟窗口），
 * 与账单列表的 payment_window_expired 同源，避免订单页给出必然被拦下的无效跳转。
 * 管理端「只建订单不建账单」时后端下发 invoice=null，此时拼不出支付页地址，必须直接不给入口。
 */
export function canPayOrderRow(row: OrderPayabilityRow) {
  if (Number(row?.status) !== 0) return false;
  if (linkedInvoiceId(row) === 0 || Number(row?.invoice?.status) !== 0) return false;
  return row?.invoice?.payment_window_expired !== true;
}

/** 订单待支付、关联账单也待支付，只是支付窗口已关闭：入口改为「支付窗口已关闭」提示。 */
export function isPayWindowClosedOrderRow(row: OrderPayabilityRow) {
  if (Number(row?.status) !== 0) return false;
  if (linkedInvoiceId(row) === 0 || Number(row?.invoice?.status) !== 0) return false;
  return row?.invoice?.payment_window_expired === true;
}

/**
 * 订单待支付但没有任何可支付的关联账单（账单缺失，或账单已支付/已取消/已退款）：
 * 不给支付入口，也不能谎报「支付窗口已关闭」。
 */
export function isPayUnavailableOrderRow(row: OrderPayabilityRow) {
  if (Number(row?.status) !== 0) return false;
  return !canPayOrderRow(row) && !isPayWindowClosedOrderRow(row);
}

export function useOrderList() {
  // 取消中的订单 id 集合：按行隔离 loading，避免取消某一行时所有行按钮同时转圈
  const cancelingIds = ref(new Set<number>());
  // 已弹出取消确认框的订单 id 集合：确认框打开期间再次点击不再叠加第二个确认框
  const confirmingIds = ref(new Set<number>());
  const isCanceling = (id: number | string) =>
    cancelingIds.value.has(Number(id)) || confirmingIds.value.has(Number(id));
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
    resetFilters,
    dateRange,
  } = useRecordList<OrderRecord>({
    fetcher: (params) => clientApi.orders(params),
    errorMessage: '订单列表加载失败',
  });

  function cancelOrder(row: OrderRecord) {
    const id = Number(row.id);
    // 防重入：确认框已打开或取消请求在途时直接忽略，避免连点叠出多个确认框、逐个确认后并发取消
    if (confirmingIds.value.has(id) || cancelingIds.value.has(id)) return;
    confirmingIds.value.add(id);
    const releaseConfirm = () => {
      confirmingIds.value.delete(id);
    };
    const dialog = DialogPlugin.confirm({
      header: '取消订单',
      body: '确定取消该订单？取消后不可恢复，关联账单将一并取消，使用的优惠券会退回账户；新产品购买的库存将被释放。',
      confirmBtn: '确定',
      cancelBtn: '取消',
      theme: 'warning',
      async onConfirm() {
        dialog.setConfirmLoading(true);
        cancelingIds.value.add(id);
        try {
          await clientApi.cancelOrder(row.id);
          MessagePlugin.success('订单已取消');
          await loadList();
          dialog.hide();
        } catch (error: unknown) {
          MessagePlugin.error(getErrorMessage(error, '取消订单失败'));
        } finally {
          cancelingIds.value.delete(id);
          releaseConfirm();
          dialog.setConfirmLoading(false);
        }
      },
      onCancel: releaseConfirm,
      onClose: releaseConfirm,
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
    resetFilters,
    dateRange,
    cancelOrder,
  };
}

export function useOrderDetail() {
  const loading = ref(false);
  const canceling = ref(false);
  // 确认框占位：确认框打开期间即上锁，连点不会叠出第二个确认框（不影响按钮自身的 loading 表现）
  const confirming = ref(false);
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
    if (!detail.value || canceling.value || confirming.value) return;
    const row = detail.value;
    confirming.value = true;
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
          confirming.value = false;
          dialog.setConfirmLoading(false);
        }
      },
      onCancel: () => {
        confirming.value = false;
      },
      onClose: () => {
        confirming.value = false;
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
