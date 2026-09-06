import { MessagePlugin } from 'tdesign-vue-next';
import { computed, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';

import clientApi from '@/api/client';
import { formatMoney } from '@/domains/services/useServiceCenter';
import type { CouponOption, RenewCycleOption, ServiceRenewPreview } from '@/types/client';

import { resolveErrorMessage } from './useConsoleCore';

export interface UseConsoleRenewOptions {
  serviceId: { value: number };
}

export function useConsoleRenew(options: UseConsoleRenewOptions) {
  const { serviceId } = options;
  const router = useRouter();

  const renewVisible = ref(false);
  const renewLoading = ref(false);
  const renewSubmitting = ref(false);
  const renewData = ref<ServiceRenewPreview | null>(null);
  const renewForm = reactive({ billing_cycle: '', user_coupon_id: 0 });

  const renewAmount = computed(() => {
    const cycles: RenewCycleOption[] = Array.isArray(renewData.value?.cycles) ? renewData.value.cycles : [];
    const current = cycles.find((item) => item.billing_cycle === renewForm.billing_cycle);
    return formatMoney(current?.amount || 0);
  });

  const renewCoupons = computed<CouponOption[]>(() =>
    Array.isArray(renewData.value?.available_coupons) ? renewData.value.available_coupons : [],
  );

  async function loadRenewPreview() {
    renewLoading.value = true;
    try {
      const res = await clientApi.serviceRenewPreview(serviceId.value, {
        billing_cycle: renewForm.billing_cycle || undefined,
        user_coupon_id: renewForm.user_coupon_id || undefined,
      });
      renewData.value = res.data || null;
      renewForm.billing_cycle = String(
        res.data?.default_cycle || res.data?.billing_cycle || res.data?.cycles?.[0]?.billing_cycle || '',
      );
      renewForm.user_coupon_id = Number(res.data?.selected_user_coupon_id || 0);
    } catch (error: unknown) {
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
      const invoiceId = Number(res.data?.id || 0);
      const invoiceStatus = Number(res.data?.status ?? 0);
      renewVisible.value = false;
      if (invoiceId > 0 && invoiceStatus === 0) {
        MessagePlugin.success('续费账单已创建，正在跳转支付');
        router.push(`/client/invoices/${invoiceId}/pay`);
        return;
      }
      // 后端对"已支付处理中"的续费已直接拒绝；这里兜底处理非待支付账单，避免跳进支付页空转。
      MessagePlugin.info(
        invoiceStatus === 1
          ? '该周期已有一笔处理中的续费（已支付），请勿重复支付，可稍后刷新查看'
          : '续费账单已创建，可在账单列表中查看',
      );
      router.push('/client/invoices');
    } catch (error: unknown) {
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
