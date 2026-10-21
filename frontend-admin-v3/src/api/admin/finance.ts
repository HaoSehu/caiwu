import { request } from '@/utils/request';

import type {
  InvoiceDetailResponse,
  InvoiceListParams,
  InvoiceRecord,
  NewCustomerDailySummary,
  NewCustomerSummaryParams,
  OrderListParams,
  OrderRecord,
  RechargeListParams,
  RechargeRecord,
} from './types';

interface V2AdminOrderDetailPayload {
  order?: Record<string, unknown> | null;
}

interface V2AdminInvoiceDetailPayload {
  invoice?: Record<string, unknown> | null;
}

function normalizeV2AdminInvoiceDetail(payload: V2AdminInvoiceDetailPayload): InvoiceDetailResponse {
  const invoice = toRecord(payload.invoice);
  const basic = toRecord(invoice.basic);
  const display = toRecord(invoice.display);
  const financial = toRecord(invoice.financial);
  const order = toRecord(invoice.order);
  const product = toRecord(invoice.product);
  const configuration = toRecord(invoice.configuration);
  const paymentChain = toRecord(invoice.payment_chain);
  const audit = toRecord(invoice.audit);
  const actions = toRecord(invoice.actions);
  const timestamps = toRecord(invoice.timestamps);

  return {
    invoice: {
      id: invoice.id,
      invoice_no: basic.invoice_no,
      user_id: toRecord(invoice.user).id,
      product_spec_snapshot: display.product_spec_snapshot,
      product_spec_display: display.product_spec_display,
      product_display_name: display.product_display_name,
      combined_display_name: display.combined_display_name,
      user: invoice.user,
      order_id: order.id,
      order: invoice.order,
      product_id: product.id,
      product: invoice.product,
      service: invoice.service,
      type: basic.type,
      type_label: basic.type_label,
      scene: invoice.scene,
      amount: financial.amount,
      discount: financial.discount,
      member_discount_amount: financial.member_discount_amount,
      member_discount_snapshot:
        toRecord(financial.member_discount_snapshot) || toRecord(configuration.member_discount_snapshot),
      coupon_code: financial.coupon_code,
      coupon_name: financial.coupon_name,
      paid_amount: financial.paid_amount,
      payable_amount: financial.payable_amount,
      status: basic.status,
      status_label: basic.status_label,
      raw_status: basic.raw_status,
      raw_status_label: basic.raw_status_label,
      // 退款投影（纯标记与真退款同表累计，详见执行计划 2026-10-11）
      refund_amount: financial.refund_amount,
      refundable_amount: financial.refundable_amount,
      refund_method: financial.refund_method,
      refunded_at: financial.refunded_at,
      refund_status: financial.refund_status,
      refund_status_label: financial.refund_status_label,
      is_partially_refunded: financial.is_partially_refunded,
      refunds: Array.isArray(invoice.refund_records) ? (invoice.refund_records as Record<string, unknown>[]) : [],
      can_refund_to_balance: actions.can_refund_to_balance,
      refundable_amount_for_balance: financial.refundable_amount_for_balance,
      billing_cycle: basic.billing_cycle,
      quantity: basic.quantity,
      summary: display.summary,
      due_date: basic.due_date,
      paid_at: financial.paid_at,
      created_at: timestamps.created_at,
      updated_at: timestamps.updated_at,
      trace_id: audit.trace_id,
      refund_trace_id: audit.refund_trace_id,
      config_snapshot: configuration.config_snapshot,
      config_pricing_snapshot: configuration.config_pricing_snapshot,
      coupon_snapshot: configuration.coupon_snapshot,
      payment_summary: paymentChain.payment_summary,
      can_cancel: actions.can_cancel,
    } as InvoiceRecord,
    payments: Array.isArray(paymentChain.payments) ? (paymentChain.payments as Record<string, unknown>[]) : [],
    items: Array.isArray(invoice.items) ? (invoice.items as Record<string, unknown>[]) : [],
    logs: Array.isArray(invoice.logs) ? (invoice.logs as Record<string, unknown>[]) : [],
  };
}

function normalizeV2AdminOrderDetail(payload: V2AdminOrderDetailPayload): OrderRecord {
  const order = toRecord(payload.order);
  const basic = toRecord(order.basic);
  const financial = toRecord(order.financial);
  const invoice = toRecord(order.invoice);
  const product = toRecord(order.product);
  const coupon = toRecord(order.coupon);
  const configuration = toRecord(order.configuration);
  const paymentChain = toRecord(order.payment_chain);
  const audit = toRecord(order.audit);
  const timestamps = toRecord(order.timestamps);

  const invoiceIdentity = invoice.id as number | string | undefined;
  if (!invoiceIdentity) {
    console.warn(
      '[admin/finance] 订单详情缺少内嵌 invoice 对象，退款与关联账单入口不可用：',
      basic.order_no ?? order.id,
    );
  }

  return {
    id: order.id as OrderRecord['id'],
    order_no: basic.order_no as string | undefined,
    type: basic.type as string | undefined,
    type_label: basic.type_label as string | undefined,
    status: basic.status as number | string | undefined,
    status_label: basic.status_label as string | undefined,
    billing_cycle: basic.billing_cycle as string | undefined,
    quantity: basic.quantity as number | string | undefined,
    remark: basic.remark,
    amount: financial.amount as number | string | undefined,
    discount: financial.discount as number | string | undefined,
    member_discount_amount: financial.member_discount_amount as number | string | undefined,
    member_discount_snapshot:
      toRecord(financial.member_discount_snapshot) || toRecord(configuration.member_discount_snapshot),
    paid_amount: financial.paid_amount as number | string | undefined,
    paid_at: financial.paid_at as string | undefined,
    // 退款投影（订单侧与账单同值，由 TradeLifecycleService 同步写）
    refund_amount: financial.refund_amount as number | string | undefined,
    refunded_at: financial.refunded_at as string | undefined,
    refund_status: financial.refund_status as number | undefined,
    user_id: toRecord(order.user).id as number | string | undefined,
    user: order.user as Record<string, unknown> | undefined,
    // invoice_id 没有订单表列可读，完全依赖后端订单详情资源内嵌的 order.invoice 对象
    // （AdminOrderDetailResource 继承 AdminOrderSummaryResource，HasOne 组装在
    // backend/app/Http/Resources/Admin/V2/AdminOrderSummaryResource.php:116-134 的 invoice()）。
    // 该对象缺失时订单详情页 `v-if="order.invoice_id"`（pages/finance/orders/detail/index.vue:23/185）
    // 的退款/看账单入口会静默消失，
    // 这里显式判空并告警，让降级可见；不抛错，避免整页详情因缺账单而不可用。
    invoice_id: invoiceIdentity,
    invoice: order.invoice as Record<string, unknown> | null,
    product_id: product.id,
    product_name: product.name as string | undefined,
    product_full_path: product.full_path as string | undefined,
    product_type: product.type as string | undefined,
    service: order.service as Record<string, unknown> | null,
    coupon: order.coupon,
    coupon_code: coupon.code as string | undefined,
    config_snapshot: configuration.config_snapshot,
    config_pricing_snapshot: configuration.config_pricing_snapshot,
    service_snapshot: configuration.service_snapshot as Record<string, unknown> | null | undefined,
    payments: Array.isArray(paymentChain.payments) ? paymentChain.payments : [],
    trace_id: audit.trace_id,
    created_at: timestamps.created_at as string | undefined,
    updated_at: timestamps.updated_at as string | undefined,
  };
}

function toRecord(value: unknown): Record<string, unknown> {
  return value && typeof value === 'object' ? (value as Record<string, unknown>) : {};
}

export const invoiceApi = {
  list: (params: InvoiceListParams) =>
    request.get<{ list?: InvoiceRecord[]; total?: number; page?: number; page_size?: number }>({
      url: '/v2/admin/invoices',
      params,
    }),
  detail: async (id: number | string) =>
    normalizeV2AdminInvoiceDetail(await request.get<V2AdminInvoiceDetailPayload>({ url: `/v2/admin/invoices/${id}` })),
  cancel: (id: number | string) => request.post({ url: `/v2/admin/invoices/${id}/cancellations` }),
  // 退款到余额（唯一退款通道，动资金：加余额 + 台账 + 批次 + 红字账单）。
  // manual_mark 纯标记通道已于 2026-10-20 裁决移除。
  // 幂等键必须由调用方（退款弹窗）在「打开弹窗时」生成并在重试中复用：
  // 每次调用现生成会让网络重试/双击变成两笔独立退款，服务端幂等层形同虚设。
  refundToBalance: (
    id: number | string,
    payload: { amount: number | string; remark: string; idempotency_key: string; request_id?: string },
  ) =>
    request.post({
      url: `/v2/admin/invoices/${id}/refunds`,
      data: {
        amount: payload.amount,
        remark: payload.remark,
        idempotency_key: payload.idempotency_key,
      },
      headers: { 'X-Request-Id': payload.request_id || payload.idempotency_key },
    }),
};

export const orderApi = {
  list: (params: OrderListParams) =>
    request.get<{ list?: OrderRecord[]; total?: number; page?: number; page_size?: number }>({
      url: '/v2/admin/orders',
      params,
    }),
  detail: async (id: number | string) =>
    normalizeV2AdminOrderDetail(await request.get<V2AdminOrderDetailPayload>({ url: `/v2/admin/orders/${id}` })),
  // 订单侧退款到余额：后端解析关联账单后走同一服务（无关联账单直接拒绝）。
  // 幂等键同样由调用方持有，理由见 invoices.refundToBalance。
  refundToBalance: (
    id: number | string,
    payload: { amount: number | string; remark: string; idempotency_key: string; request_id?: string },
  ) =>
    request.post({
      url: `/v2/admin/orders/${id}/refunds`,
      data: {
        amount: payload.amount,
        remark: payload.remark,
        idempotency_key: payload.idempotency_key,
      },
      headers: { 'X-Request-Id': payload.request_id || payload.idempotency_key },
    }),
};

export const financeMenuApi = {
  recharges: (params: RechargeListParams) =>
    request.get<{ list?: RechargeRecord[]; total?: number; page?: number; page_size?: number }>({
      url: '/v2/admin/finance/recharges',
      params,
    }),
  newCustomerDailySummary: (params: NewCustomerSummaryParams) =>
    request.get<NewCustomerDailySummary>({
      url: '/v2/admin/finance/new-customer-daily-summary',
      params,
    }),
  renewalOrders: (params: OrderListParams) =>
    request.get<{ list?: OrderRecord[]; total?: number; page?: number; page_size?: number }>({
      url: '/v2/admin/finance/renewal-orders',
      params,
    }),
  upgradeOrders: (params: OrderListParams) =>
    request.get<{ list?: OrderRecord[]; total?: number; page?: number; page_size?: number }>({
      url: '/v2/admin/finance/upgrade-orders',
      params,
    }),
};
