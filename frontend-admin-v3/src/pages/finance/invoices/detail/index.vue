<template>
  <div class="invoice-detail-page">
    <invoice-detail-drawer
      embedded
      :visible="true"
      :loading="loading"
      :invoice="invoice"
      :payments="payments"
      :items="items"
      :logs="logs"
      :status-label="statusLabel"
      :status-theme="statusTheme"
      :cancelable="cancelable"
      :cancel-loading="cancelLoading"
      @refresh="load"
      @cancel="handleCancel"
      @mark-refund="openRefundMarkDialog"
      @view-order="(id) => id && router.push(`/admin/finance/orders/${id}`)"
      @view-user="(id) => id && router.push(`/admin/users/${id}`)"
    />

    <refund-mark-dialog
      v-model:visible="refundMarkVisible"
      :target-label="fieldValue(invoice.invoice_no || invoice.id)"
      :paid-amount="Number(invoice.paid_amount || 0)"
      :refunded-amount="Number(invoice.refund_amount || 0)"
      :mark-refundable-amount="Number(invoice.refundable_amount || 0)"
      :balance-refundable-amount="Number(invoice.refundable_amount_for_balance || 0)"
      :can-mark-refund="Boolean(invoice.can_mark_refund)"
      :can-refund-to-balance="Boolean(invoice.can_refund_to_balance)"
      :has-manual-mark-refund="Boolean(invoice.has_manual_mark_refund)"
      :submit="submitRefund"
    />
  </div>
</template>
<script setup lang="ts">
import { INVOICE_STATUS, INVOICE_STATUS_MAP, toLabelMap, toTagTypeMap } from '@shared/statusConfig';
import { DialogPlugin, MessagePlugin } from 'tdesign-vue-next';
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';

import type { InvoiceDetailResponse, InvoiceRecord } from '@/api/admin';
import { adminApi } from '@/api/admin';
import InvoiceDetailDrawer from '@/components/finance-record-detail/InvoiceDetailDrawer.vue';
import RefundMarkDialog from '@/components/finance-record-detail/RefundMarkDialog.vue';
import { AdminPermissions, hasPermissionInList } from '@/constants/permissions';
import { useUserStore } from '@/store';
import { fieldValue } from '@/utils/format';
import { errorMessage } from '@/utils/userMessage';

/**
 * 财务账单详情页（/admin/finance/invoices/:id）。
 *
 * 与订单详情页对称的「整页详情」入口：用户详情页账单 tab、财务账单列表页的
 * 「详情」都跳转到此页；渲染复用 InvoiceDetailDrawer 的 embedded 模式，
 * 保证列表抽屉与详情页的字段、退款标记操作完全一致。
 */
defineOptions({
  name: 'AdminFinanceInvoiceDetail',
});

const route = useRoute();
const router = useRouter();
const userStore = useUserStore();

const hasInvoiceManagePermission = computed(() => {
  const permissions = (userStore.userInfo as { permissions?: string[] } | undefined)?.permissions || [];
  return hasPermissionInList(permissions, AdminPermissions.INVOICE_MANAGE);
});

const loading = ref(false);
const cancelLoading = ref(false);
const detail = ref<InvoiceDetailResponse>({} as InvoiceDetailResponse);

const invoice = computed<InvoiceRecord>(() => detail.value.invoice || ({} as InvoiceRecord));
const payments = computed(() => detail.value.payments || []);
const items = computed(() => detail.value.items || []);
const logs = computed(() => detail.value.logs || []);

const statusLabelMap = toLabelMap(INVOICE_STATUS_MAP);
const statusTypeMap = toTagTypeMap(INVOICE_STATUS_MAP);

const statusLabel = computed(
  () => statusLabelMap[String(invoice.value.status ?? '')] || fieldValue(invoice.value.status),
);
const statusTheme = computed(() => {
  const theme = statusTypeMap[String(invoice.value.status ?? '')] || 'default';
  return theme === 'info' ? 'default' : theme;
});

// 取消账单：仅待支付且具备 invoice.manage 权限（与财务账单列表页口径一致）
const cancelable = computed(
  () => hasInvoiceManagePermission.value && Number(invoice.value.status) === INVOICE_STATUS.UNPAID,
);

const refundMarkVisible = ref(false);

async function load(): Promise<void> {
  const id = Number(route.params.id || 0);
  if (!id) {
    MessagePlugin.error('账单不存在');
    router.replace('/admin/finance/invoices');
    return;
  }
  loading.value = true;
  try {
    detail.value = await adminApi.invoices.detail(id);
  } catch (error) {
    MessagePlugin.error(errorMessage(error, '加载账单详情失败'));
  } finally {
    loading.value = false;
  }
}

function openRefundMarkDialog(): void {
  if (!invoice.value.can_mark_refund && !invoice.value.can_refund_to_balance) {
    MessagePlugin.warning('该账单当前不支持退款处理');
    return;
  }
  refundMarkVisible.value = true;
}

async function submitRefund(payload: { mode: 'mark' | 'balance'; amount: number; remark: string }): Promise<void> {
  if (!invoice.value.id) return;
  if (payload.mode === 'balance') {
    await adminApi.invoices.refundToBalance(invoice.value.id, { amount: payload.amount, remark: payload.remark });
    MessagePlugin.success('已退款至用户余额');
  } else {
    await adminApi.invoices.markRefund(invoice.value.id, { amount: payload.amount, remark: payload.remark });
    MessagePlugin.success('退款标记成功');
  }
  await load();
}

async function handleCancel(): Promise<void> {
  if (!invoice.value.id) return;
  const confirmed = await DialogPlugin.confirm({
    header: '取消账单',
    body: '取消后账单不可继续支付，确认取消？',
    confirmBtn: { content: '确认取消', theme: 'danger' },
    cancelBtn: '再想想',
  });
  if (!confirmed) return;

  cancelLoading.value = true;
  try {
    await adminApi.invoices.cancel(invoice.value.id);
    MessagePlugin.success('账单已取消');
    await load();
  } catch (error) {
    MessagePlugin.error(errorMessage(error, '取消账单失败'));
  } finally {
    cancelLoading.value = false;
  }
}

onMounted(load);
</script>
<style lang="less" scoped>
.invoice-detail-page {
  min-height: 100%;
  padding: var(--td-comp-margin-l);
  background: var(--td-bg-color-page);
}
</style>
