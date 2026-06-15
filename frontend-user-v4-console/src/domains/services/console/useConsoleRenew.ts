import { computed, reactive, ref } from 'vue';
import { MessagePlugin } from 'tdesign-vue-next';
import { useRouter } from 'vue-router';

import clientApi from '@/api/client';
import { formatMoney } from '@/domains/services/useServiceCenter';
import { resolveErrorMessage } from './useConsoleCore';

type AnyRecord = Record<string, any>;

export interface UseConsoleRenewOptions {
  serviceId: { value: number };
}

export function useConsoleRenew(options: UseConsoleRenewOptions) {
  const { serviceId } = options;
  const router = useRouter();

  const renewVisible = ref(false);
  const renewLoading = ref(false);
  const renewSubmitting = ref(false);
  const renewData = ref<AnyRecord | null>(null);
  const renewForm = reactive({ billing_cycle: '', user_coupon_id: 0 });

  const renewAmount = computed(() => {
    const cycles = Array.isArray(renewData.value?.cycles) ? renewData.value.cycles : [];
    const current = cycles.find((item: AnyRecord) => item.billing_cycle === renewForm.billing_cycle);
    return formatMoney(current?.amount || 0);
  });

  const renewCoupons = computed(() =>
    Array.isArray(renewData.value?.available_coupons) ? renewData.value.available_coupons : [],
  );

  async function loadRenewPreview() {
    renewLoading.value = true;
    try {
      const res = await clientApi.serviceRenewPreview(serviceId.value, {
        billing_cycle: renewForm.billing_cycle || undefined,
        user_coupon_id: renewForm.user_coupon_id || undefined,
      });
      renewData.value = (res as AnyRecord).data || null;
      renewForm.billing_cycle = String(
        (res as AnyRecord).data?.default_cycle ||
          (res as AnyRecord).data?.billing_cycle ||
          (res as AnyRecord).data?.cycles?.[0]?.billing_cycle ||
          '',
      );
      renewForm.user_coupon_id = Number((res as AnyRecord).data?.selected_user_coupon_id || 0);
    } catch (error: any) {
      MessagePlugin.error(resolveErrorMessage(error, '加载续费信息失败'));
    } finally {
      renewLoading.value = false;
    }
  }

  async function openRenewDialog() {
    renewVisible.value = true;
    renewData.value = null;
    renewForm.billing_cycle = '';
    renewForm.user_coupon_id = 0;
    await loadRenewPreview();
  }

  async function handleRenewCycleChange(value: unknown) {
    renewForm.billing_cycle = String(value || '');
    await loadRenewPreview();
  }

  async function handleRenewCouponChange(value: unknown) {
    renewForm.user_coupon_id = Number(value || 0);
    await loadRenewPreview();
  }

  async function submitRenew() {
    if (!renewForm.billing_cycle) return;
    renewSubmitting.value = true;
    try {
      const res = await clientApi.createRenewOrder(serviceId.value, {
        billing_cycle: renewForm.billing_cycle,
        user_coupon_id: renewForm.user_coupon_id || undefined,
      });
      const invoiceId = Number((res as AnyRecord).data?.id || 0);
      renewVisible.value = false;
      MessagePlugin.success('续费账单已创建，正在跳转支付');
      router.push(invoiceId > 0 ? `/client/invoices/${invoiceId}/pay` : '/client/invoices');
    } catch (error: any) {
      MessagePlugin.error(resolveErrorMessage(error, '创建续费账单失败'));
    } finally {
      renewSubmitting.value = false;
    }
  }

  return {
    renewVisible,
    renewLoading,
    renewSubmitting,
    renewData,
    renewForm,
    renewAmount,
    renewCoupons,
    openRenewDialog,
    handleRenewCycleChange,
    handleRenewCouponChange,
    submitRenew,
  };
}