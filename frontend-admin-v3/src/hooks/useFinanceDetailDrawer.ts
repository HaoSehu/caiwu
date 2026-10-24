import { MessagePlugin } from 'tdesign-vue-next/es/message/plugin';
import { reactive } from 'vue';

import type { InvoiceRecord } from '@/api/admin';
import { errorMessage } from '@/utils/userMessage';

export interface InvoiceDetailState {
  visible: boolean;
  loading: boolean;
  /** 首次加载失败，交由详情组的失败态呈现（含重新加载） */
  error: boolean;
  errorText: string;
  cancelLoading: boolean;
  currentId: number;
  detail: {
    invoice: InvoiceRecord;
    payments: Record<string, unknown>[];
    items: Record<string, unknown>[];
    logs: Record<string, unknown>[];
  };
}

export type InvoiceDetailPayload = InvoiceDetailState['detail'];

interface UseFinanceDetailDrawerOptions {
  /** 加载详情的 API 函数 */
  fetchDetail: (id: number) => Promise<Record<string, unknown>>;
  /** 详情加载失败的回退文案 */
  errorFallback?: string;
  /** 自定义详情归一化（默认处理 v2 扁平结构；充值页需补 type 兜底与保留已有 payments） */
  normalize?: (payload: Record<string, unknown>, fallback: InvoiceRecord) => InvoiceDetailPayload;
}

export function useFinanceDetailDrawer(options: UseFinanceDetailDrawerOptions) {
  const { fetchDetail, errorFallback = '加载详情失败', normalize } = options;

  const detailState = reactive<InvoiceDetailState>({
    visible: false,
    loading: false,
    error: false,
    errorText: '',
    cancelLoading: false,
    currentId: 0,
    detail: {
      invoice: {} as InvoiceRecord,
      payments: [],
      items: [],
      logs: [],
    },
  });

  function normalizeInvoiceDetail(
    payload: Record<string, unknown> = {},
    fallback: InvoiceRecord = {} as InvoiceRecord,
  ) {
    const invoice =
      payload.invoice && typeof payload.invoice === 'object'
        ? (payload.invoice as InvoiceRecord)
        : (payload as InvoiceRecord);
    return {
      invoice: {
        ...fallback,
        ...invoice,
        payment_summary: { ...(fallback.payment_summary || {}), ...(invoice.payment_summary || {}) },
        order: invoice.order || fallback.order || null,
        product: invoice.product || fallback.product || null,
        scene: invoice.scene || fallback.scene || {},
      },
      payments: Array.isArray(payload.payments) ? (payload.payments as Record<string, unknown>[]) : [],
      items: Array.isArray(payload.items) ? (payload.items as Record<string, unknown>[]) : [],
      logs: Array.isArray(payload.logs) ? (payload.logs as Record<string, unknown>[]) : [],
    };
  }

  async function openDetail(row: InvoiceRecord) {
    if (!row.id) return;
    detailState.visible = true;
    detailState.currentId = Number(row.id);
    detailState.detail = { invoice: row, payments: [], items: [], logs: [] };
    await reloadDetail();
  }

  async function reloadDetail() {
    if (!detailState.currentId) return;
    detailState.loading = true;
    detailState.error = false;
    detailState.errorText = '';
    try {
      const response = await fetchDetail(detailState.currentId);
      detailState.detail = normalize
        ? normalize(response, detailState.detail.invoice)
        : normalizeInvoiceDetail(response, detailState.detail.invoice);
    } catch (error) {
      // 首次打开失败交给抽屉内详情的失败态呈现（含重新加载），刷新失败仍用 toast
      if (!detailState.detail.invoice.id) {
        detailState.error = true;
        detailState.errorText = errorMessage(error, errorFallback);
      } else {
        MessagePlugin.error(errorMessage(error, errorFallback));
      }
    } finally {
      detailState.loading = false;
    }
  }

  function closeDetail() {
    detailState.visible = false;
    detailState.currentId = 0;
    detailState.error = false;
    detailState.errorText = '';
    detailState.cancelLoading = false;
    detailState.detail = { invoice: {} as InvoiceRecord, payments: [], items: [], logs: [] };
  }

  return { detailState, openDetail, reloadDetail, closeDetail, normalizeInvoiceDetail };
}
