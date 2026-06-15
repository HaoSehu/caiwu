<template>
  <div class="client-page service-console-page">
    <div class="console-breadcrumbs">
      <button type="button" class="console-breadcrumbs__link" @click="router.push('/client/services')">用户中心</button>
      <el-icon><ArrowRight /></el-icon>
      <span>{{ detail.product?.type_label || '实例服务' }}</span>
      <el-icon><ArrowRight /></el-icon>
      <strong>{{ detail.name || `实例 #${serviceId}` }}</strong>
    </div>

    <ServiceConsoleHeader
      :detail="detail"
      :service-id="serviceId"
      :service-region="serviceRegion"
      :header-connection-label="primaryConnectionLabel"
      :header-connection-text="primaryConnectionText"
      :detail-loading="detailLoading"
      :action-loading="actionLoading"
      :status-syncing="statusSyncing"
      :can-sync-status="canSyncStatus"
      :can-manage-console="canManageConsole"
      @open-name="openNameDialog"
      @open-remark="openRemarkDialog"
      @open-renew="openRenewDialog"
      @power-action="handlePowerAction"
      @sync-status="handleSyncStatus"
      @more-command="handleMoreCommand"
    />

    <section class="console-workbench">
      <aside class="console-sidebar">
        <button
          v-for="item in consoleNavItems"
          :key="item.key"
          type="button"
          class="console-sidebar__item"
          :class="{ 'is-active': activeTab === item.key }"
          @click="activeTab = item.key"
        >
          <span class="console-sidebar__icon"><el-icon><component :is="item.icon" /></el-icon></span>
          <span>{{ item.label }}</span>
        </button>
      </aside>

      <div class="console-content">
        <template v-if="activeTab === 'overview'">
          <ServiceConsoleOverview
            :detail="detail"
            :service-id="serviceId"
            :service-os="serviceOs"
            :service-region="serviceRegion"
            :primary-connection-label="primaryConnectionLabel"
            :primary-connection-text="primaryConnectionText"
            :connection-port-label="connectionPortLabel"
            :connection-endpoint-label="connectionEndpointLabel"
            :connection-endpoint-text="connectionEndpointText"
            :service-ip-count="serviceIpCount"
            :bandwidth-text="bandwidthText"
            :connection-port-text="connectionPortText"
            :renew-price-text="renewPriceText"
            :auto-renew-label="autoRenewLabel"
            :auto-renew-loading="autoRenewLoading"
            :resolved-password="resolvedPassword"
            :show-password="showPassword"
            :find-spec-value="findSpecValue"
            @copy="copyText"
            @toggle-password="showPassword = !showPassword"
            @open-password-dialog="passwordDialogVisible = true"
            @open-renew="openRenewDialog"
            @open-traffic-package="openTrafficPackageDialog"
            @toggle-auto-renew="handleToggleAutoRenew"
          />
        </template>

        <template v-else-if="activeTab === 'monitor'">
          <section class="console-panel console-panel--monitor">
            <div class="console-panel__header console-panel__header--compact">
              <div>
                <h3>监控信息</h3>
              </div>
              <div class="console-toolbar">
                <el-radio-group v-model="monitorState.range" size="small" @change="handleMonitorRangeChange">
                  <el-radio-button v-for="item in monitorRangeOptions" :key="item.value" :value="item.value">{{ item.label }}</el-radio-button>
                </el-radio-group>
                <el-button size="small" :loading="monitorState.loading" @click="loadMonitor(true)">
                  <el-icon><RefreshRight /></el-icon>刷新
                </el-button>
              </div>
            </div>
            <div class="console-panel__body">
              <div class="monitor-window">
                <span>{{ monitorWindow.start }}</span><span>到</span><span>{{ monitorWindow.end }}</span>
              </div>
              <el-alert v-if="monitorState.error" class="console-inline-alert" type="warning" :closable="false" show-icon :title="monitorState.error" />
              <el-empty v-if="monitorState.supported === false" :description="monitorState.message || '当前实例暂不支持监控'" :image-size="72" />
              <div v-else class="monitor-grid">
                <ServiceTrendChart
                  v-for="item in monitorState.charts"
                  :key="item.key || item.type"
                  :title="item.label || item.type"
                  :chart="item.chart || {}"
                  :summary="item.summary"
                  :loading="item.loading"
                  :error-text="item.error"
                />
                <el-empty v-if="!monitorState.loading && !monitorState.charts.length" description="当前时间范围内暂无监控数据" :image-size="72" />
              </div>
            </div>
          </section>
        </template>

        <template v-else-if="activeTab === 'security'">
          <ServiceConsoleSecurityGroup
            ref="securityGroupPanelRef"
            :security-state="securityState"
            :security-rules="securityRules"
            :active-security-group="activeSecurityGroup"
            :group-form="groupForm"
            :group-rules="groupRules"
            :rule-form="ruleForm"
            :rule-rules="ruleRules"
            :resolve-security-group-row-class-name="resolveSecurityGroupRowClassName"
            v-model:group-visible="groupDialogVisible"
            v-model:rule-visible="ruleDialogVisible"
            @refresh="loadSecurityGroups"
            @open-group-dialog="groupDialogVisible = true"
            @open-rule-dialog="ruleDialogVisible = true"
            @select-group="selectSecurityGroup($event)"
            @apply-group="handleApplySecurityGroup"
            @delete-group="handleDeleteSecurityGroup"
            @delete-rule="handleDeleteSecurityRule"
            @submit-group="handleSubmitSecurityGroup"
            @submit-rule="handleSubmitSecurityRule"
          />
        </template>

        <template v-else-if="activeTab === 'power'">
          <ServiceConsolePower
            :detail="detail"
            :task-statuses="taskStatuses"
            :action-loading="actionLoading"
            @power-action="handlePowerAction"
            @fetch-module-status="handleFetchModuleStatus"
            @open-password-dialog="passwordDialogVisible = true"
            @open-reinstall-dialog="openReinstallDialog"
          />
        </template>

        <template v-else-if="activeTab === 'logs'">
          <ServiceConsoleLogs
            :logs-state="logsState"
            :log-category-options="logCategoryOptions"
            @load="loadLogs"
            @reload="reloadLogs"
            @reset="resetLogFilters"
            @size-change="handleLogPageSizeChange"
          />
        </template>

        <template v-else-if="activeTab === 'vnc'">
          <ServiceConsoleVnc
            :vnc-url="vncUrl"
            :action-loading="actionLoading"
            :vnc-window-loading="vncWindowLoading"
            :can-manage-console="canManageConsole"
            @refresh-vnc="handleOpenVnc"
            @open-new-window="openVncNewWindow"
            @connect-vnc="handleOpenVnc"
          />
        </template>
      </div>
    </section>

    <el-dialog v-model="renewDialogVisible" title="服务续费" width="520px" destroy-on-close>
      <div v-loading="renewState.loading" class="dialog-body">
        <template v-if="renewState.data">
          <div class="renew-cycle-grid">
            <button
              v-for="cycle in renewState.data.cycles || []"
              :key="cycle.billing_cycle"
              type="button"
              class="renew-cycle-btn"
              :class="{ active: renewState.billing_cycle === cycle.billing_cycle }"
              @click="handleRenewCycleChange(cycle.billing_cycle)"
            >
              <span>{{ cycle.billing_cycle_label }}</span>
              <strong>¥{{ formatMoney(cycle.amount) }}</strong>
            </button>
          </div>
          <div class="renew-coupon-row">
            <span>续费优惠</span>
            <div class="renew-coupon-field">
            <el-select
              :model-value="renewState.user_coupon_id || undefined"
              clearable
              :disabled="!renewAvailableCoupons.length"
              placeholder="选择优惠券"
              :placeholder="renewAvailableCoupons.length ? '选择优惠券' : '当前无可用优惠券'"
              no-data-text="暂无可用优惠券"
              @change="handleRenewCouponChange"
            >
              <el-option
                v-for="coupon in renewAvailableCoupons"
                :key="coupon.id"
                :label="`${coupon.name} · ${coupon.discount_label}`"
                :value="coupon.id"
              />
            </el-select>
            <p class="renew-coupon-hint">
              {{ renewAvailableCoupons.length ? '仅展示当前续费周期可用的优惠券' : '当前续费周期暂无可用优惠券' }}
            </p>
            </div>
          </div>
          <div class="renew-total-line">
            <span>本次应付</span><strong>¥{{ renewAmount }}</strong>
          </div>
        </template>
      </div>
      <template #footer>
        <el-button @click="renewDialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="renewState.submitting" :disabled="!renewState.billing_cycle" @click="submitRenew">创建续费账单</el-button>
      </template>
    </el-dialog>

    <ServiceConsoleTrafficPackageDialog
      v-model:visible="trafficPackageDialogVisible"
      :state="trafficPackageState"
      :amount="trafficPackageAmount"
      @choice-change="handleTrafficPackageChoiceChange"
      @qty-change="handleTrafficPackageQtyChange"
      @submit="submitTrafficPackageOrder"
    />

    <el-dialog v-model="nameDialogVisible" title="修改实例名称" width="420px" destroy-on-close>
      <el-form :model="nameForm" @submit.prevent>
        <el-form-item>
          <el-input v-model="nameForm.name" maxlength="120" show-word-limit placeholder="填写便于识别的实例名称" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="nameDialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="nameSubmitting" @click="submitName">保存名称</el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="remarkDialogVisible" title="编辑备注" width="420px" destroy-on-close>
      <el-form :model="remarkForm" @submit.prevent>
        <el-form-item>
          <el-input v-model="remarkForm.remark" type="textarea" :rows="4" maxlength="120" show-word-limit placeholder="填写实例备注，便于识别" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="remarkDialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="remarkSubmitting" @click="submitRemark">保存备注</el-button>
      </template>
    </el-dialog>

    <ServiceConsoleReinstall
      ref="reinstallDialogRef"
      :password-form="passwordForm"
      :password-rules="passwordRules"
      :reinstall-state="reinstallState"
      :reinstall-rules="reinstallRules"
      :reinstall-grouped-options="reinstallGroupedOptions"
      :current-reinstall-options="currentReinstallOptions"
      :action-loading="actionLoading"
      v-model:password-visible="passwordDialogVisible"
      v-model:reinstall-visible="reinstallDialogVisible"
      @submit-password="handleSubmitResetPassword"
      @submit-reinstall="handleSubmitReinstall"
      @reinstall-group-change="handleReinstallGroupChange"
    />
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import { ArrowRight, Document, Grid, Lock, Monitor, RefreshRight, SwitchButton, TrendCharts } from '@element-plus/icons-vue'
import ServiceTrendChart from '../components/ServiceTrendChart.vue'
import ServiceConsoleHeader from '../components/ServiceConsoleHeader.vue'
import ServiceConsoleOverview from '../components/ServiceConsoleOverview.vue'
import ServiceConsoleLogs from '../components/ServiceConsoleLogs.vue'
import ServiceConsolePower from '../components/ServiceConsolePower.vue'
import ServiceConsoleReinstall from '../components/ServiceConsoleReinstall.vue'
import ServiceConsoleSecurityGroup from '../components/ServiceConsoleSecurityGroup.vue'
import ServiceConsoleTrafficPackageDialog from '../components/ServiceConsoleTrafficPackageDialog.vue'
import ServiceConsoleVnc from '../components/ServiceConsoleVnc.vue'
import { useServiceConsole, formatMoney, copyText } from '../composables/useServiceConsole.js'

const props = defineProps({
  permissions: { type: Object, default: () => ({}) },
  availableTabs: { type: Array, default: () => ['overview', 'monitor', 'security', 'power', 'logs', 'vnc'] },
})

const router = useRouter()
const console$ = useServiceConsole(props)
const reinstallDialogRef = ref(null)
const securityGroupPanelRef = ref(null)

const {
  detail, detailLoading, statusSyncing, actionLoading,
  vncWindowLoading, autoRenewLoading, showPassword, activeTab, vncUrl,
  renewDialogVisible, renewState,
  trafficPackageDialogVisible, trafficPackageState,
  nameDialogVisible, nameSubmitting, nameForm,
  remarkDialogVisible, remarkSubmitting, remarkForm,
  passwordDialogVisible, passwordForm, passwordRules,
  reinstallDialogVisible, reinstallState, reinstallRules,
  monitorState,
  securityState, securityRules,
  groupDialogVisible, groupForm, groupRules,
  ruleDialogVisible, ruleForm, ruleRules,
  logsState,
  serviceId, canManageConsole, canSyncStatus,
  resolvedPassword, renewAmount, renewAvailableCoupons, trafficPackageAmount, reinstallGroupedOptions, currentReinstallOptions,
  taskStatuses, activeSecurityGroup, serviceRegion, serviceOs,
  primaryConnectionLabel, primaryConnectionText, connectionPortLabel,
  connectionEndpointLabel, connectionEndpointText, connectionPortText, serviceIpCount, bandwidthText, renewPriceText,
  autoRenewLabel, monitorWindow, logCategoryOptions,
  findSpecValue, resolveSecurityGroupRowClassName,
  handleSyncStatus, handlePowerAction, handleToggleAutoRenew,
  openRenewDialog, handleRenewCycleChange, handleRenewCouponChange, submitRenew,
  openTrafficPackageDialog, handleTrafficPackageChoiceChange, handleTrafficPackageQtyChange, submitTrafficPackageOrder,
  openNameDialog, submitName,
  openRemarkDialog, submitRemark,
  submitResetPassword,
  openReinstallDialog, handleReinstallGroupChange, submitReinstall,
  handleFetchModuleStatus,
  handleOpenVnc, openVncNewWindow,
  handleMoreCommand,
  loadMonitor, handleMonitorRangeChange,
  loadSecurityGroups, selectSecurityGroup,
  submitSecurityGroup, handleApplySecurityGroup, handleDeleteSecurityGroup,
  submitSecurityRule, handleDeleteSecurityRule,
  loadLogs, reloadLogs, resetLogFilters, handleLogPageSizeChange,
} = console$

const consoleNavItems = computed(() => [
  { key: 'overview', label: '控制台总览', icon: Grid },
  { key: 'monitor', label: '监控信息', icon: TrendCharts },
  { key: 'security', label: '安全组', icon: Lock },
  { key: 'power', label: '电源管理', icon: SwitchButton },
  { key: 'logs', label: '操作日志', icon: Document },
  { key: 'vnc', label: 'VNC 控制台', icon: Monitor },
])

const monitorRangeOptions = [
  { label: '3 小时', value: '3h' },
  { label: '24 小时', value: '24h' },
  { label: '7 天', value: '7d' },
  { label: '30 天', value: '30d' },
]

async function handleSubmitResetPassword() {
  try {
    await reinstallDialogRef.value?.validatePasswordForm()
  } catch {
    return
  }

  await submitResetPassword()
}

async function handleSubmitReinstall() {
  try {
    await reinstallDialogRef.value?.validateReinstallForm()
  } catch {
    return
  }

  await submitReinstall()
}

async function handleSubmitSecurityGroup() {
  try {
    await securityGroupPanelRef.value?.validateGroupForm()
  } catch {
    return
  }

  await submitSecurityGroup()
}

async function handleSubmitSecurityRule() {
  try {
    await securityGroupPanelRef.value?.validateRuleForm()
  } catch {
    return
  }

  await submitSecurityRule()
}
</script>

<style src="../styles/console.scss" lang="scss"></style>
