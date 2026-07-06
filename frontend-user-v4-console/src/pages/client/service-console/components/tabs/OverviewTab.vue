<template>
  <section class="console-overview-grid">
    <t-card class="console-panel" title="基本信息" :bordered="false">
      <div class="detail-grid">
        <InfoCell label="实例名称" :value="detail.name || `服务 #${serviceId}`" strong />
        <InfoCell label="实例 ID" :value="String(detail.id || '--')" copyable @copy="copyText" />
        <InfoCell label="实例规格" :value="detail.combined_display_name || detail.product_display_name || detail.product?.display_name || detail.product?.type_label || '--'" strong />
        <InfoCell label="操作系统" :value="serviceOs" strong />
        <InfoCell label="创建时间" :value="detail.created_at || '--'" strong />
        <div class="detail-cell">
          <span>实例状态</span>
          <t-tag :theme="instanceStatusTheme" variant="light">{{ instanceStatusText }}</t-tag>
        </div>
      </div>
    </t-card>

    <t-card class="console-panel console-panel-wide" title="配置信息" :bordered="false">
      <template #actions>
        <t-button v-if="canBuyTrafficPackage" variant="text" theme="primary" :loading="trafficLoading" @click="openTrafficPackageDialog">
          {{ detail.traffic?.button_text || '购买流量包' }}
        </t-button>
      </template>
      <div class="detail-grid">
        <InfoCell label="流量" :value="detail.traffic?.limited ? `${detail.traffic.usage_label || '0G'} / ${detail.traffic.limit_label || '不限'}` : '不限'" strong />
        <InfoCell label="区域" :value="serviceRegion" strong />
        <InfoCell label="CPU" :value="findSpecValue(['CPU', '核心'])" strong />
        <InfoCell label="内存" :value="findSpecValue(['内存', 'RAM'])" strong />
        <InfoCell label="系统盘" :value="findSpecValue(['系统盘'])" strong />
        <InfoCell label="带宽" :value="bandwidthText" strong />
        <InfoCell label="IP数量" :value="serviceIpCount" strong />
        <InfoCell label="数据盘" :value="findSpecValue(['数据盘'])" strong />
      </div>
    </t-card>

    <t-card class="console-panel" title="网络信息" :bordered="false">
      <div class="detail-grid">
        <div class="detail-cell connection-ip-cell">
          <span>{{ primaryConnectionLabel }}</span>
          <div class="detail-cell-value connection-ip-list">
            <template v-if="primaryConnectionValues.length">
              <span v-for="ip in primaryConnectionValues" :key="ip" class="connection-ip-chip">
                <strong>{{ ip }}</strong>
                <button type="button" class="copy-link" :aria-label="`复制${ip}`" @click="copyText(ip)">
                  <CopyIcon size="1rem" />
                </button>
              </span>
              <t-tooltip v-if="primaryConnectionValues.length > 1" :content="`复制全部${primaryConnectionLabel}`">
                <button type="button" class="copy-link connection-copy-all" :aria-label="`复制全部${primaryConnectionLabel}`" @click="copyText(primaryConnectionText)">
                  <CopyIcon size="1.125rem" />
                </button>
              </t-tooltip>
            </template>
            <strong v-else>--</strong>
          </div>
        </div>
        <InfoCell label="内网 IP" :value="detail.connection?.internal_ip || '--'" copyable @copy="copyText" />
      </div>
    </t-card>

    <t-card class="console-panel" title="登录凭据" :bordered="false">
      <div class="credential-grid">
        <InfoCell label="用户名" :value="detail.connection?.username || '--'" copyable @copy="copyText" />
        <div class="detail-cell">
          <span>密码</span>
          <div class="detail-cell-value credential-password-value">
            <strong class="credential-password-text">{{ resolvedPassword }}</strong>
            <t-tooltip v-if="hasConnectionPassword" :content="showPassword ? '隐藏密码' : '显示密码'">
              <button
                type="button"
                class="copy-link credential-icon-button"
                :aria-label="showPassword ? '隐藏密码' : '显示密码'"
                @click="showPassword = !showPassword"
              >
                <BrowseOffIcon v-if="showPassword" size="1.125rem" />
                <BrowseIcon v-else size="1.125rem" />
              </button>
            </t-tooltip>
            <t-tooltip v-if="hasConnectionPassword" content="复制密码">
              <button
                type="button"
                class="copy-link credential-icon-button"
                aria-label="复制密码"
                @click="copyText(detail.connection?.password || '')"
              >
                <CopyIcon size="1.125rem" />
              </button>
            </t-tooltip>
            <t-button v-if="detail.actions?.password_reset" size="small" variant="text" theme="primary" @click="openPasswordDialog">重置</t-button>
          </div>
        </div>
        <InfoCell label="端口" :value="connectionPortText" strong copyable @copy="copyText" />
        <InfoCell label="主机名" :value="connectionEndpointText" copyable @copy="copyText" />
      </div>
    </t-card>

    <t-card class="console-panel console-panel-wide" title="付费信息" :bordered="false">
      <template #actions>
        <t-button variant="text" theme="primary" @click="openRenewDialog">续费管理</t-button>
      </template>
      <div class="detail-grid">
        <InfoCell label="计费方式" :value="detail.billing_cycle_label || '--'" strong />
        <InfoCell label="续费价格" :value="renewPriceText" strong />
        <InfoCell label="到期时间" :value="detail.expires_at || '长期有效'" strong warning />
        <div class="detail-cell">
          <span>自动续费</span>
          <div class="detail-cell-value">
            <t-tag :theme="Number(detail.auto_renew) === 1 ? 'success' : 'default'" variant="light">{{ autoRenewLabel }}</t-tag>
            <t-button variant="text" theme="primary" :loading="autoRenewLoading" @click="handleToggleAutoRenew(Number(detail.auto_renew) !== 1)">
              {{ Number(detail.auto_renew) === 1 ? '关闭' : '开启' }}
            </t-button>
          </div>
        </div>
        <InfoCell label="账单号" :value="detail.invoice?.invoice_no || '--'" copyable @copy="copyText" />
        <InfoCell label="账单状态" :value="resolveInvoiceStatusLabel(detail.invoice?.status)" strong />
      </div>
    </t-card>
  </section>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { BrowseIcon, BrowseOffIcon, CopyIcon } from 'tdesign-icons-vue-next';

import { resolveInvoiceStatusLabel } from '@/domains/finance/useInvoices';
import { InfoCell } from '../InfoCell';
import { useServiceConsoleContext } from '../context';

const {
  detail,
  serviceId,
  autoRenewLoading,
  showPassword,
  serviceRegion,
  serviceOs,
  primaryConnectionLabel,
  primaryConnectionValues,
  primaryConnectionText,
  connectionEndpointText,
  connectionPortText,
  instanceStatusText,
  instanceStatusTheme,
  serviceIpCount,
  bandwidthText,
  renewPriceText,
  autoRenewLabel,
  resolvedPassword,
  findSpecValue,
  openRenewDialog,
  openTrafficPackageDialog,
  openPasswordDialog,
  handleToggleAutoRenew,
  copyText,
  trafficLoading,
} = useServiceConsoleContext();

const canBuyTrafficPackage = computed(() => Boolean(detail.value.actions?.traffic_package && detail.value.traffic?.purchase_enabled !== false));
const hasConnectionPassword = computed(() => String(detail.value.connection?.password || '').trim() !== '');
</script>
