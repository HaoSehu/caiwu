<template>
  <div class="page-container admin-page user-detail-page" v-loading="detailLoading">
    <section class="admin-page-head user-hero">
      <div class="hero-main">
        <el-button link class="back-link" @click="goBack">
          <el-icon><ArrowLeft /></el-icon>
          返回用户列表
        </el-button>

        <div class="hero-profile">
          <div class="hero-avatar">{{ avatarText }}</div>
          <div class="hero-copy">
            <div class="hero-title-row">
              <h2>{{ pageTitle }}</h2>
              <el-tag :type="statusTagType" effect="plain" round>{{ statusText }}</el-tag>
              <el-tag v-if="userDetail.is_verified" type="success" effect="plain" round>已实名</el-tag>
            </div>
            <p>{{ userDetail.email || '-' }}</p>
            <div class="hero-meta">
              <span>用户 ID #{{ userDetail.id || '--' }}</span>
              <span>注册时间 {{ formatDateTime(userDetail.created_at) }}</span>
              <span>注册时长 {{ registeredDaysLabel }}</span>
            </div>
          </div>
        </div>
      </div>

      <div class="hero-actions">
        <el-button type="primary" :disabled="!userDetail.id" :loading="loginAsLoading" @click="handleLoginAs">
          以该客户登录
        </el-button>
        <el-button :disabled="!userDetail.id" @click="openEditDialog">编辑资料</el-button>
        <el-button :disabled="!userDetail.id" @click="openRechargeDialog">资金管理</el-button>
        <el-button
          :disabled="!userDetail.id"
          :loading="actionLoading"
          :type="userDetail.status === 1 ? 'danger' : 'success'"
          plain
          @click="handleToggleStatus"
        >
          {{ userDetail.status === 1 ? '禁用账号' : '启用账号' }}
        </el-button>
      </div>
    </section>

    <section class="quick-stats">
      <div class="quick-stat">
        <span class="quick-stat__label">在线工单</span>
        <span class="quick-stat__value quick-stat__value--warning">{{ stats.ticket_open || 0 }}</span>
      </div>
      <div class="quick-stat__divider" />
      <div class="quick-stat">
        <span class="quick-stat__label">余额</span>
        <span class="quick-stat__value quick-stat__value--success">{{ formatMoney(userDetail.balance) }}</span>
      </div>
      <div class="quick-stat__divider" />
      <div class="quick-stat">
        <span class="quick-stat__label">总消费</span>
        <span class="quick-stat__value">{{ formatMoney(stats.total_expense) }}</span>
      </div>
      <div class="quick-stat__divider" />
      <div class="quick-stat quick-stat--note">
        <span class="quick-stat__label">管理员备注</span>
        <span class="quick-stat__value quick-stat__value--muted">{{ userDetail.admin_note || '暂无' }}</span>
      </div>
    </section>

    <el-card shadow="never" class="tabs-card">
      <el-tabs v-model="activeTab" @tab-change="handleTabChange">
        <el-tab-pane label="基本信息" name="basic">
          <UserBasicInfo :info-items="infoItems" :admin-note="userDetail.admin_note" />
        </el-tab-pane>

        <el-tab-pane label="推荐信息" name="referral">
          <div class="referral-strip">
            <div class="referral-strip__item">
              <span>推荐码</span>
              <strong>{{ referral.referral_code || '-' }}</strong>
            </div>
            <div class="referral-strip__divider" />
            <div class="referral-strip__item">
              <span>当前等级</span>
              <strong>{{ referral.member_level?.name || userDetail.member_level?.name || '未分级' }}</strong>
            </div>
            <div class="referral-strip__divider" />
            <div class="referral-strip__item">
              <span>直推人数</span>
              <strong>{{ stats.direct_referral_count || 0 }}</strong>
            </div>
            <div class="referral-strip__divider" />
            <div class="referral-strip__item">
              <span>累计奖励</span>
              <strong class="text-success">{{ formatMoney(stats.total_referral_reward) }}</strong>
            </div>
            <div class="referral-strip__divider" />
            <div class="referral-strip__item">
              <span>可提现奖励</span>
              <strong class="text-success">{{ formatMoney(referral.referral_available_amount) }}</strong>
            </div>
          </div>

          <div class="recent-referrals">
            <div class="recent-referrals__head">
              <strong>最近推荐用户</strong>
            </div>
            <el-empty v-if="!referral.recent_referrals.length" description="暂无推荐记录" :image-size="72" />
            <div v-else class="referral-list">
              <div v-for="item in referral.recent_referrals" :key="item.id" class="referral-list__item">
                <div>
                  <strong>{{ item.nickname || item.display_name || item.email || '-' }}</strong>
                  <p>{{ item.email || '-' }}</p>
                </div>
                <span>{{ formatDateTime(item.referred_at || item.created_at) }}</span>
              </div>
            </div>
          </div>
        </el-tab-pane>

        <el-tab-pane label="产品/服务" name="services">
          <UserServices
            :state="servicesState"
            :format-money="formatMoney"
            :resolve-service-tone-tag-type="resolveServiceToneTagType"
            @search="searchServices"
            @reset="resetServicesFilters"
            @add="openAddServiceDialog"
            @reload="loadServices"
            @refresh-status="handleRefreshServicesStatus"
            @refresh-row="handleRefreshService"
            @delete-row="handleDeleteServiceRow"
            @manage="openServiceConsole"
          />
        </el-tab-pane>

        <el-tab-pane label="账单" name="invoices">
          <UserInvoices
            :user-id="userId"
            :state="invoicesState"
            :format-money="formatMoney"
            :resolve-invoice-status="resolveInvoiceStatus"
            :resolve-invoice-type="resolveInvoiceType"
            @search="searchInvoices"
            @reset="resetInvoicesFilters"
            @reload="loadInvoices"
            @detail-refresh="reloadDetail"
          />
        </el-tab-pane>

        <el-tab-pane label="资金流水" name="balance">
          <UserBalanceLogs
            :state="balanceState"
            :format-money="formatMoney"
            :to-number="toNumber"
            :resolve-balance-type="resolveBalanceType"
            @reload="loadBalance"
          />
        </el-tab-pane>

        <el-tab-pane label="工单" name="tickets">
          <UserTickets
            :state="ticketsState"
            :resolve-priority="resolvePriority"
            :resolve-ticket-status="resolveTicketStatus"
            @update:page="(v) => ticketsState.page = v"
            @update:page-size="(v) => ticketsState.pageSize = v"
            @reload="loadTickets"
          />
        </el-tab-pane>

        <el-tab-pane label="操作日志" name="logs">
          <UserOperationLogs :state="logsState" @search="searchLogs" @reload="loadLogs" />
        </el-tab-pane>

        <el-tab-pane label="通知记录" name="notices">
          <div class="toolbar compact">
            <el-radio-group v-model="noticesState.channel" @change="reloadNotices">
              <el-radio-button value="email">邮件</el-radio-button>
              <el-radio-button value="sms">短信</el-radio-button>
            </el-radio-group>
          </div>

          <el-table :data="noticesState.list" v-loading="noticesState.loading" stripe>
            <el-table-column
              :prop="noticesState.channel === 'email' ? 'to_email' : 'phone'"
              :label="noticesState.channel === 'email' ? '接收地址' : '手机号'"
              min-width="180"
            />
            <el-table-column
              :prop="noticesState.channel === 'email' ? 'subject' : 'template_code'"
              :label="noticesState.channel === 'email' ? '主题' : '模板'"
              min-width="180"
            />
            <el-table-column prop="status" label="状态" width="100">
              <template #default="{ row }">
                <el-tag :type="noticeStatusTagType(row.status)" size="small" effect="plain">
                  {{ noticeStatusLabel(row.status) }}
                </el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="sent_at" label="发送时间" min-width="170">
              <template #default="{ row }">
                {{ formatDateTime(row.sent_at || row.created_at) }}
              </template>
            </el-table-column>
          </el-table>

          <div class="pager">
            <el-pagination
              v-model:current-page="noticesState.page"
              v-model:page-size="noticesState.pageSize"
              :total="noticesState.total"
              :page-sizes="[10, 20, 50]"
              layout="total, sizes, prev, pager, next"
              @size-change="loadNotices"
              @current-change="loadNotices"
            />
          </div>
        </el-tab-pane>
      </el-tabs>
    </el-card>

    <el-dialog v-model="editDialogVisible" title="编辑资料" width="520px" destroy-on-close>
      <el-form ref="editFormRef" :model="editForm" :rules="editRules" label-width="82px">
        <el-form-item label="邮箱">
          <el-input :model-value="userDetail.email" disabled />
        </el-form-item>
        <el-form-item label="昵称" prop="nickname">
          <el-input v-model="editForm.nickname" />
        </el-form-item>
        <el-form-item label="手机号" prop="phone">
          <el-input v-model="editForm.phone" />
        </el-form-item>
        <el-form-item label="新密码" prop="password">
          <el-input v-model="editForm.password" type="password" show-password placeholder="留空则不修改" />
        </el-form-item>
        <el-form-item label="信用额度" prop="credit_limit">
          <el-input-number v-model="editForm.credit_limit" :min="0" :precision="2" style="width: 100%;" />
        </el-form-item>
        <el-form-item label="状态">
          <el-switch
            v-model="editForm.status"
            :active-value="1"
            :inactive-value="0"
            inline-prompt
            active-text="启用"
            inactive-text="禁用"
          />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="editDialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="saveLoading" @click="handleSave">保存修改</el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="rechargeVisible" title="资金管理" width="440px" destroy-on-close>
      <el-form ref="rechargeFormRef" :model="rechargeForm" :rules="rechargeRules" label-width="80px">
        <el-form-item label="用户">
          <el-input :model-value="rechargeForm.email" disabled />
        </el-form-item>
        <el-form-item label="操作类型">
          <el-radio-group v-model="rechargeForm.type">
            <el-radio value="increase">增加余额</el-radio>
            <el-radio value="decrease">扣减余额</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="金额" prop="amount">
          <el-input-number v-model="rechargeForm.amount" :min="0.01" :max="999999" :precision="2" style="width: 100%;" />
        </el-form-item>
        <el-form-item label="备注" prop="remark">
          <el-input v-model="rechargeForm.remark" placeholder="请填写操作原因" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="rechargeVisible = false">取消</el-button>
        <el-button :type="rechargeForm.type === 'decrease' ? 'danger' : 'primary'" :loading="rechargeLoading" @click="handleRecharge">
          {{ rechargeForm.type === 'decrease' ? '确认扣减' : '确认增加' }}
        </el-button>
      </template>
    </el-dialog>

    <el-dialog
      v-model="addServiceDialogVisible"
      title="添加实例"
      width="760px"
      destroy-on-close
      class="add-service-dialog"
    >
      <div class="service-source-banner" :class="{ success: addServiceForm.source_type === 'upstream' && addServiceCanLinkUpstream }">
        <span v-if="addServiceForm.source_type === 'manual'">手动开通适合人工交付场景，可录入主机信息，但不会自动发起上游控制。</span>
        <span v-else-if="addServiceCanLinkUpstream">对接上游主机后，当前服务会直接接入现有实例管理能力。</span>
        <span v-else>该商品尚未绑定可控上游，当前只能手动开通。</span>
      </div>

      <el-form
        ref="addServiceFormRef"
        :model="addServiceForm"
        :rules="addServiceRules"
        label-width="92px"
        v-loading="addServiceProductsLoading || addServiceProductDetailLoading"
      >
        <el-form-item label="服务来源" prop="source_type">
          <el-radio-group v-model="addServiceForm.source_type" @change="handleAddServiceSourceChange">
            <el-radio-button value="manual">手动开通</el-radio-button>
            <el-radio-button value="upstream" :disabled="!addServiceCanLinkUpstream">对接上游主机</el-radio-button>
          </el-radio-group>
        </el-form-item>

        <div class="service-form-grid">
          <el-form-item label="选择商品" prop="product_id" class="service-form-span-2">
            <el-select
              v-model="addServiceForm.product_id"
              filterable
              placeholder="搜索并选择要开通的商品"
              @change="handleAddServiceProductChange"
            >
              <el-option v-for="item in addServiceProductOptions" :key="item.id" :label="item.name" :value="item.id">
                <div class="service-product-option">
                  <strong>{{ item.name }}</strong>
                  <span>{{ item.group_full_name || item.type_label || '-' }}</span>
                </div>
              </el-option>
            </el-select>
          </el-form-item>

          <el-form-item label="计费周期" prop="billing_cycle">
            <el-select v-model="addServiceForm.billing_cycle" placeholder="请选择计费周期" @change="syncAddServiceAmountFromCycle">
              <el-option v-for="item in addServiceBillingOptions" :key="item.value" :label="item.label" :value="item.value" />
            </el-select>
          </el-form-item>

          <template v-if="addServiceForm.source_type === 'manual'">
            <el-form-item label="服务状态" prop="status">
              <el-select v-model="addServiceForm.status" placeholder="请选择服务状态">
                <el-option v-for="item in serviceStatusOptions" :key="item.value" :label="item.label" :value="item.value" />
              </el-select>
            </el-form-item>
            <el-form-item label="服务名称">
              <el-input v-model="addServiceForm.name" placeholder="为空时默认使用配置名或主机名" />
            </el-form-item>
            <el-form-item label="主机名/域名">
              <el-input v-model="addServiceForm.domain" placeholder="如 svr-1001 / vm.example.com" />
            </el-form-item>
            <el-form-item label="服务金额" prop="amount">
              <el-input-number v-model="addServiceForm.amount" :min="0" :precision="2" style="width: 100%;" />
            </el-form-item>
            <el-form-item label="到期时间">
              <el-date-picker
                v-model="addServiceForm.expires_at"
                type="datetime"
                value-format="YYYY-MM-DD HH:mm:ss"
                placeholder="不填则按计费周期自动计算"
                style="width: 100%;"
              />
            </el-form-item>
            <el-form-item label="自动续费">
              <el-switch v-model="addServiceForm.auto_renew" :active-value="1" :inactive-value="0" />
            </el-form-item>
            <el-form-item label="公网 IP">
              <el-input v-model="addServiceForm.dedicated_ip" placeholder="如 1.2.3.4" />
            </el-form-item>
            <el-form-item label="内网 IP">
              <el-input v-model="addServiceForm.internal_ip" placeholder="如 10.0.0.8" />
            </el-form-item>
            <el-form-item label="登录端口">
              <el-input-number v-model="addServiceForm.port" :min="1" :max="65535" style="width: 100%;" />
            </el-form-item>
            <el-form-item label="登录账号">
              <el-input v-model="addServiceForm.username" placeholder="如 root / administrator" />
            </el-form-item>
            <el-form-item label="登录密码">
              <el-input v-model="addServiceForm.password" type="password" show-password />
            </el-form-item>
            <el-form-item label="系统类型">
              <el-input v-model="addServiceForm.os" placeholder="如 Ubuntu 22.04 / Windows Server 2022" />
            </el-form-item>
          </template>

          <template v-else>
            <el-form-item label="上游通道">
              <el-input :model-value="addServiceUpstreamChannel" disabled />
            </el-form-item>
            <el-form-item label="上游实例 ID" prop="upstream_host_id">
              <el-input-number v-model="addServiceForm.upstream_host_id" :min="1" style="width: 100%;" />
            </el-form-item>
          </template>

          <el-form-item label="备注" class="service-form-span-2">
            <el-input
              v-model="addServiceForm.remark"
              type="textarea"
              :rows="4"
              maxlength="200"
              show-word-limit
              placeholder="记录本次人工开通说明、上游备注或交付信息"
            />
          </el-form-item>
        </div>
      </el-form>

      <template #footer>
        <el-button @click="addServiceDialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="addServiceSubmitting" @click="handleSubmitAddService">确认添加</el-button>
      </template>
    </el-dialog>

    <ServiceConsoleDrawer
      :state="serviceConsoleState"
      :format-money="formatMoney"
      :resolve-service-tone-tag-type="resolveServiceToneTagType"
      @close="closeServiceConsole"
      @refresh-remote="handleRefreshConsoleRemoteStatus"
      @power="handleServicePower"
      @reset-password="handleResetServicePassword"
      @edit-upstream="openServiceUpstreamDialog"
      @edit-pricing="openServicePricingDialog"
      @edit-name="openServiceNameDialog"
      @manual-provision="handleManualProvisionFromConsole"
      @refund="handleServiceRefund"
    />

    <el-dialog
      v-model="serviceUpstreamDialogVisible"
      title="更换上游 ID"
      width="560px"
      destroy-on-close
    >
      <el-form
        ref="serviceUpstreamFormRef"
        :model="serviceUpstreamForm"
        :rules="serviceUpstreamRules"
        label-width="120px"
      >
        <el-form-item label="上游接口" prop="supplier_id">
          <el-select
            v-model="serviceUpstreamForm.supplier_id"
            filterable
            clearable
            :loading="serviceUpstreamSuppliersLoading"
            placeholder="请选择 hosting_panel_api 上游接口"
            style="width: 100%;"
          >
            <el-option
              v-for="item in serviceUpstreamSupplierOptions"
              :key="item.id"
              :label="item.label"
              :value="item.id"
            />
          </el-select>
        </el-form-item>

        <el-form-item label="上游实例 ID" prop="upstream_host_id">
          <el-input-number
            v-model="serviceUpstreamForm.upstream_host_id"
            :min="1"
            style="width: 100%;"
          />
        </el-form-item>
      </el-form>

      <template #footer>
        <el-button @click="closeServiceUpstreamDialog">取消</el-button>
        <el-button type="primary" :loading="serviceUpstreamSubmitting" @click="submitServiceUpstream">
          保存
        </el-button>
      </template>
    </el-dialog>

    <el-dialog
      v-model="servicePricingDialogVisible"
      title="调整价格"
      width="760px"
      destroy-on-close
    >
      <el-form
        ref="servicePricingFormRef"
        :model="servicePricingForm"
        :rules="servicePricingRules"
        label-width="120px"
      >
        <el-form-item label="购买价格" prop="amount">
          <el-input-number
            v-model="servicePricingForm.amount"
            :min="0"
            :precision="2"
            :step="1"
            style="width: 100%;"
          />
        </el-form-item>

        <el-form-item label="续费价格">
          <div class="service-meta-pricing">
            <div
              v-for="item in servicePricingEntries"
              :key="item.cycle"
              class="service-meta-pricing__row"
            >
              <div class="service-meta-pricing__label">
                <strong>{{ item.label }}</strong>
                <span>基础价 {{ item.base_amount ? `¥${item.base_amount}` : '未配置' }}</span>
              </div>
              <el-switch v-model="servicePricingForm.locked_pricing[item.cycle].enabled" />
              <el-input-number
                v-model="servicePricingForm.locked_pricing[item.cycle].manual_amount"
                :min="0"
                :precision="2"
                :step="1"
                placeholder="留空则跟随基础价"
                style="width: 180px;"
              />
            </div>
            <div class="service-meta-pricing__actions">
              <el-checkbox v-model="servicePricingForm.clear_locked_pricing">
                恢复默认续费价格
              </el-checkbox>
            </div>
          </div>
        </el-form-item>
      </el-form>

      <template #footer>
        <el-button @click="closeServicePricingDialog">取消</el-button>
        <el-button type="primary" :loading="servicePricingSubmitting" @click="submitServicePricing">
          保存
        </el-button>
      </template>
    </el-dialog>

    <el-dialog
      v-model="serviceNameDialogVisible"
      title="修改实例名称"
      width="420px"
      destroy-on-close
    >
      <el-form :model="serviceNameForm" label-width="90px">
        <el-form-item label="实例名称">
          <el-input
            v-model="serviceNameForm.service_name"
            maxlength="120"
            show-word-limit
            placeholder="填写便于识别的实例名称"
          />
        </el-form-item>
      </el-form>

      <template #footer>
        <el-button @click="closeServiceNameDialog">取消</el-button>
        <el-button type="primary" :loading="serviceNameSubmitting" @click="submitServiceName">
          保存
        </el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ArrowLeft } from '@element-plus/icons-vue'
import UserBasicInfo from './components/UserBasicInfo.vue'
import UserServices from './components/UserServices.vue'
import UserInvoices from './components/UserInvoices.vue'
import UserBalanceLogs from './components/UserBalanceLogs.vue'
import UserTickets from './components/UserTickets.vue'
import UserOperationLogs from './components/UserOperationLogs.vue'
import ServiceConsoleDrawer from './components/ServiceConsoleDrawer.vue'
import { useUserDetail } from './composables/useUserDetail.js'

const {
  userId,
  userDetail,
  stats,
  referral,
  detailLoading,
  saveLoading,
  actionLoading,
  rechargeLoading,
  loginAsLoading,
  activeTab,
  editDialogVisible,
  rechargeVisible,
  addServiceDialogVisible,
  addServiceSubmitting,
  addServiceProductsLoading,
  addServiceProductDetailLoading,
  serviceUpstreamDialogVisible,
  serviceUpstreamSubmitting,
  serviceUpstreamSuppliersLoading,
  servicePricingDialogVisible,
  servicePricingSubmitting,
  serviceNameDialogVisible,
  serviceNameSubmitting,
  editFormRef,
  editForm,
  rechargeFormRef,
  rechargeForm,
  addServiceFormRef,
  addServiceForm,
  serviceUpstreamFormRef,
  servicePricingFormRef,
  addServiceProductOptions,
  servicesState,
  serviceConsoleState,
  invoicesState,
  balanceState,
  ticketsState,
  logsState,
  noticesState,
  pageTitle,
  avatarText,
  statusText,
  statusTagType,
  registeredDaysLabel,
  infoItems,
  addServiceCanLinkUpstream,
  addServiceBillingOptions,
  addServiceUpstreamChannel,
  serviceUpstreamForm,
  servicePricingForm,
  serviceNameForm,
  servicePricingEntries,
  serviceUpstreamSupplierOptions,
  serviceStatusOptions,
  editRules,
  rechargeRules,
  addServiceRules,
  serviceUpstreamRules,
  servicePricingRules,
  handleTabChange,
  searchServices,
  resetServicesFilters,
  loadServices,
  searchInvoices,
  resetInvoicesFilters,
  loadInvoices,
  loadBalance,
  loadTickets,
  searchLogs,
  loadLogs,
  reloadDetail,
  loadNotices,
  reloadNotices,
  handleSave,
  handleToggleStatus,
  openRechargeDialog,
  handleRecharge,
  handleLoginAs,
  openEditDialog,
  openAddServiceDialog,
  handleAddServiceProductChange,
  handleAddServiceSourceChange,
  handleSubmitAddService,
  syncAddServiceAmountFromCycle,
  goBack,
  handleRefreshService,
  handleRefreshServicesStatus,
  handleDeleteServiceRow,
  openServiceConsole,
  closeServiceConsole,
  openServiceUpstreamDialog,
  closeServiceUpstreamDialog,
  submitServiceUpstream,
  openServicePricingDialog,
  closeServicePricingDialog,
  submitServicePricing,
  openServiceNameDialog,
  closeServiceNameDialog,
  submitServiceName,
  handleRefreshConsoleRemoteStatus,
  handleServicePower,
  handleResetServicePassword,
  handleManualProvisionFromConsole,
  handleServiceRefund,
  formatMoney,
  formatDateTime,
  toNumber,
  resolveInvoiceStatus,
  resolveInvoiceType,
  resolveBalanceType,
  resolvePriority,
  resolveTicketStatus,
  resolveServiceToneTagType,
  noticeStatusLabel,
  noticeStatusTagType,
} = useUserDetail()
</script>

<style lang="scss" scoped>
.user-detail-page {
  gap: 14px;
}

.tabs-card {
  overflow: hidden;
}

.user-hero {
  align-items: flex-start;
  gap: 18px;
  padding: 18px 20px;
}

.hero-main {
  display: flex;
  flex-direction: column;
  gap: 12px;
  min-width: 0;
  flex: 1;
}

.back-link {
  align-self: flex-start;
  padding: 0;
  height: auto;
  color: $text-color-secondary;

  &:hover {
    color: $color-primary;
  }
}

.hero-profile {
  display: flex;
  align-items: center;
  gap: 14px;
  min-width: 0;
}

.hero-avatar {
  display: grid;
  place-items: center;
  width: 56px;
  height: 56px;
  border-radius: 16px;
  background: linear-gradient(135deg, $color-primary, color.adjust($color-primary, $lightness: 12%));
  color: $text-color-inverse;
  font-size: 22px;
  font-weight: 600;
  box-shadow: 0 10px 24px rgba($color-primary, 0.22);
  flex-shrink: 0;
  letter-spacing: -0.5px;
}

.hero-copy {
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.hero-title-row {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.hero-title-row h2 {
  margin: 0;
  font-size: 18px;
  font-weight: 600;
  color: $text-color-primary;
  letter-spacing: -0.3px;
  line-height: 1.25;
}

.hero-copy p {
  margin: 0;
  color: $text-color-secondary;
  font-size: 13px;
  line-height: 1.4;
}

.hero-meta {
  display: flex;
  gap: 14px;
  color: $text-color-placeholder;
  font-size: 12px;
  flex-wrap: wrap;
  line-height: 1.5;

  > span + span {
    padding-left: 14px;
    border-left: 1px solid $divider-color;
  }
}

.hero-actions {
  display: flex;
  justify-content: flex-end;
  align-items: flex-start;
  gap: 8px;
  flex-wrap: wrap;
  flex-shrink: 0;
}

.quick-stats {
  display: flex;
  align-items: center;
  gap: 0;
  padding: 10px 20px;
  background: $bg-color-card;
  border: 1px solid $border-color;
  border-radius: $lg-border-radius;
}

.quick-stat {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 0 20px;
  white-space: nowrap;

  &--note {
    flex: 1;
    min-width: 0;

    .quick-stat__value {
      overflow: hidden;
      text-overflow: ellipsis;
    }
  }
}

.quick-stat__label {
  color: $text-color-placeholder;
  font-size: 13px;
}

.quick-stat__value {
  font-size: 15px;
  font-weight: 600;
  color: $text-color-primary;
  font-variant-numeric: tabular-nums;

  &--success { color: $color-success; }
  &--warning { color: $color-warning; }
  &--danger  { color: $color-danger; }
  &--muted   { color: $text-color-secondary; font-weight: 400; }
}

.quick-stat__divider {
  width: 1px;
  height: 20px;
  background: $divider-color;
  flex-shrink: 0;
}

.text-success { color: $color-success; }
.text-danger  { color: $color-danger; }
.text-warning { color: $color-warning; }
.text-primary { color: $color-primary; }

.tabs-card :deep(.el-tabs__header) {
  margin-bottom: 12px;
}

.tabs-card :deep(.el-tabs__item) {
  height: 36px;
  font-size: 14px;
  font-weight: 500;
}

.toolbar {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 12px;
  flex-wrap: wrap;
}

.toolbar.compact :deep(.el-input),
.toolbar.compact :deep(.el-select) {
  width: 150px;
}

.pager {
  display: flex;
  justify-content: flex-end;
  margin-top: 14px;
}

.referral-strip {
  display: flex;
  align-items: center;
  padding: 10px 20px;
  background: $bg-color-card;
  border: 1px solid $border-color;
  border-radius: $lg-border-radius;
  margin-bottom: 18px;
}

.referral-strip__item {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 0 20px;
  white-space: nowrap;

  span {
    color: $text-color-placeholder;
    font-size: 13px;
  }

  strong {
    font-size: 15px;
    font-weight: 600;
    color: $text-color-primary;
    font-variant-numeric: tabular-nums;
  }
}

.referral-strip__divider {
  width: 1px;
  height: 20px;
  background: $divider-color;
  flex-shrink: 0;
}

.recent-referrals {
  margin-top: 18px;
  padding-top: 18px;
  border-top: 1px solid $divider-color;
}

.recent-referrals__head {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 12px;
}

.recent-referrals__head strong {
  color: $text-color-primary;
  font-size: 14px;
  font-weight: 600;
}

.referral-list {
  display: grid;
  gap: 10px;
}

.referral-list__item {
  display: flex;
  justify-content: space-between;
  gap: 16px;
  align-items: center;
  padding: 12px 14px;
  border: 1px solid $divider-color;
  border-radius: $base-border-radius;
  background: $bg-color-soft;
  transition: border-color $duration-fast $ease-standard, background $duration-fast $ease-standard;

  &:hover {
    border-color: rgba($color-primary, 0.3);
    background: rgba($color-primary, 0.02);
  }
}

.referral-list__item strong {
  color: $text-color-primary;
  font-size: 13px;
  font-weight: 500;
}

.referral-list__item p {
  margin-top: 2px;
  color: $text-color-placeholder;
  font-size: 12px;
}

.referral-list__item span {
  color: $text-color-secondary;
  font-size: 12px;
  white-space: nowrap;
}

.service-source-banner {
  margin-bottom: 14px;
  padding: 10px 14px;
  border-radius: $base-border-radius;
  background: $bg-color-soft;
  color: $text-color-secondary;
  font-size: 12px;
  line-height: 1.7;
  border-left: 3px solid $border-color;

  &.success {
    background: rgba($color-success, 0.08);
    color: color.adjust($color-success, $lightness: -10%);
    border-left-color: $color-success;
  }
}

.service-form-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 2px 14px;
}

.service-form-span-2 {
  grid-column: span 2;
}

.service-product-option {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  align-items: center;
}

.service-product-option strong {
  color: $text-color-primary;
  font-size: 13px;
  font-weight: 500;
}

.service-product-option span {
  color: $text-color-placeholder;
  font-size: 12px;
  white-space: nowrap;
}

.service-meta-pricing {
  display: flex;
  flex-direction: column;
  gap: 12px;
  width: 100%;
}

.service-meta-pricing__row {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto auto;
  gap: 12px;
  align-items: center;
  padding: 12px 14px;
  border: 1px solid $divider-color;
  border-radius: $base-border-radius;
  background: $bg-color-soft;
}

.service-meta-pricing__label {
  display: flex;
  flex-direction: column;
  gap: 4px;

  strong {
    color: $text-color-primary;
    font-size: 13px;
    font-weight: 600;
  }

  span {
    color: $text-color-secondary;
    font-size: 12px;
  }
}

.service-meta-pricing__actions {
  color: $text-color-secondary;
  font-size: 12px;
}

@media (max-width: 1280px) {
  .quick-stats,
  .referral-strip {
    flex-wrap: wrap;
    gap: 8px;
  }

  .quick-stat__divider,
  .referral-strip__divider {
    display: none;
  }
}

@media (max-width: 768px) {
  .hero-profile {
    align-items: flex-start;
  }

  .hero-actions {
    justify-content: stretch;
  }

  .hero-actions :deep(.el-button) {
    width: 100%;
  }

  .service-form-grid {
    grid-template-columns: 1fr;
  }

  .service-form-span-2 {
    grid-column: span 1;
  }

  .service-meta-pricing__row {
    grid-template-columns: 1fr;
  }

  .referral-list__item {
    flex-direction: column;
    align-items: flex-start;
  }
}
</style>
