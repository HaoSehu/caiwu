<template>
  <div class="page-container admin-page user-detail-page" v-loading="detailLoading">
    <!-- Hero 区域 -->
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
        <el-button type="primary" :disabled="!userDetail.id" :loading="loginAsLoading" @click="handleLoginAs">以该客户登录</el-button>
        <el-button :disabled="!userDetail.id" @click="openEditDialog">编辑资料</el-button>
        <el-button :disabled="!userDetail.id" @click="openRechargeDialog">账户充值</el-button>
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

    <!-- 统计卡片 -->
    <section class="stats-grid">
      <article
        v-for="item in statsCards"
        :key="item.label"
        class="stats-card"
        :class="`stats-card--${item.tone}`"
      >
        <span>{{ item.label }}</span>
        <strong>{{ item.value }}</strong>
      </article>
    </section>

    <!-- Tabs -->
    <el-card shadow="never" class="tabs-card">
      <el-tabs v-model="activeTab" @tab-change="handleTabChange">
        <!-- 基本信息 -->
        <el-tab-pane label="基本信息" name="basic">
          <UserBasicInfo
            :info-items="infoItems"
            :admin-note="userDetail.admin_note"
          />
        </el-tab-pane>

        <!-- 推荐信息 -->
        <el-tab-pane label="推荐信息" name="referral">
          <div class="referral-metrics">
            <div class="referral-metric"><span>推荐码</span><strong>{{ referral.referral_code || '-' }}</strong></div>
            <div class="referral-metric"><span>当前等级</span><strong>{{ referral.member_level?.name || userDetail.member_level?.name || '未分级' }}</strong></div>
            <div class="referral-metric"><span>直推人数</span><strong>{{ stats.direct_referral_count || 0 }}</strong></div>
            <div class="referral-metric"><span>累计奖励</span><strong class="text-success">{{ formatMoney(stats.total_referral_reward) }}</strong></div>
            <div class="referral-metric"><span>可提现奖励</span><strong class="text-success">{{ formatMoney(referral.referral_available_amount) }}</strong></div>
          </div>
          <div class="recent-referrals">
            <div class="recent-referrals__head"><strong>最近推荐用户</strong></div>
            <el-empty v-if="!referral.recent_referrals.length" description="暂无推荐记录" :image-size="72" />
            <div v-else class="referral-list">
              <div v-for="item in referral.recent_referrals" :key="item.id" class="referral-list__item">
                <div><strong>{{ item.nickname || item.display_name || item.email }}</strong><p>{{ item.email }}</p></div>
                <span>{{ formatDateTime(item.referred_at || item.created_at) }}</span>
              </div>
            </div>
          </div>
        </el-tab-pane>

        <!-- 产品/服务 -->
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
            @open-console="openServiceConsole"
            @refresh-row="handleRefreshService"
            @delete-row="handleDeleteServiceRow"
          />
        </el-tab-pane>

        <!-- 账单 -->
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

        <!-- 余额记录 -->
        <el-tab-pane label="余额记录" name="balance">
          <UserBalanceLogs
            :state="balanceState"
            :format-money="formatMoney"
            :to-number="toNumber"
            :resolve-balance-type="resolveBalanceType"
            @reload="loadBalance"
          />
        </el-tab-pane>

        <!-- 工单 -->
        <el-tab-pane label="工单" name="tickets">
          <UserTickets
            :state="ticketsState"
            :resolve-priority="resolvePriority"
            :resolve-ticket-status="resolveTicketStatus"
            @reload="loadTickets"
          />
        </el-tab-pane>

        <!-- 操作日志 -->
        <el-tab-pane label="操作日志" name="logs">
          <UserOperationLogs
            :state="logsState"
            @search="searchLogs"
            @reload="loadLogs"
          />
        </el-tab-pane>

        <!-- 通知记录 -->
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
                <el-tag :type="{ success: 'success', failed: 'danger', pending: 'warning' }[row.status] || 'info'" size="small">
                  {{ { success: '成功', failed: '失败', pending: '待发送' }[row.status] || row.status || '-' }}
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

    <!-- 编辑资料弹窗 -->
    <el-dialog v-model="editDialogVisible" title="编辑资料" width="520px" destroy-on-close>
      <el-form ref="editFormRef" :model="editForm" :rules="editRules" label-width="82px">
        <el-form-item label="邮箱"><el-input :model-value="userDetail.email" disabled /></el-form-item>
        <el-form-item label="昵称" prop="nickname"><el-input v-model="editForm.nickname" /></el-form-item>
        <el-form-item label="手机号" prop="phone"><el-input v-model="editForm.phone" /></el-form-item>
        <el-form-item label="新密码" prop="password">
          <el-input v-model="editForm.password" type="password" show-password placeholder="留空则不修改" />
        </el-form-item>
        <el-form-item label="信用额度" prop="credit_limit">
          <el-input-number v-model="editForm.credit_limit" :min="0" :precision="2" style="width: 100%;" />
        </el-form-item>
        <el-form-item label="状态">
          <el-switch v-model="editForm.status" :active-value="1" :inactive-value="0" inline-prompt active-text="启用" inactive-text="禁用" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="editDialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="saveLoading" @click="handleSave">保存修改</el-button>
      </template>
    </el-dialog>

    <!-- 充值弹窗 -->
    <el-dialog v-model="rechargeVisible" title="手动充值" width="420px" destroy-on-close>
      <el-form ref="rechargeFormRef" :model="rechargeForm" :rules="rechargeRules" label-width="70px">
        <el-form-item label="用户"><el-input :value="rechargeForm.email" disabled /></el-form-item>
        <el-form-item label="金额" prop="amount">
          <el-input-number v-model="rechargeForm.amount" :min="0.01" :max="999999" :precision="2" style="width: 100%;" />
        </el-form-item>
        <el-form-item label="备注" prop="remark">
          <el-input v-model="rechargeForm.remark" placeholder="请输入充值备注" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="rechargeVisible = false">取消</el-button>
        <el-button type="primary" :loading="rechargeLoading" @click="handleRecharge">确定充值</el-button>
      </template>
    </el-dialog>

    <!-- 添加商品弹窗 -->
    <el-dialog
      v-model="addServiceDialogVisible"
      title="添加商品"
      width="760px"
      destroy-on-close
      class="add-service-dialog"
    >
      <div class="service-source-banner" :class="{ success: addServiceForm.source_type === 'upstream' && addServiceCanLinkUpstream }">
        <span v-if="addServiceForm.source_type === 'manual'">手动开通适合人工交付场景，可录入主机信息但不会自动发起上游控制。</span>
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
            <el-select v-model="addServiceForm.product_id" filterable placeholder="搜索并选择要开通的商品" @change="handleAddServiceProductChange">
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
              <el-input v-model="addServiceForm.name" placeholder="为空时默认使用商品名或主机名" />
            </el-form-item>
            <el-form-item label="主机名/域名">
              <el-input v-model="addServiceForm.domain" placeholder="如 svr-1001 / vm.example.com" />
            </el-form-item>
            <el-form-item label="服务金额" prop="amount">
              <el-input-number v-model="addServiceForm.amount" :min="0" :precision="2" style="width: 100%;" />
            </el-form-item>
            <el-form-item label="到期时间">
              <el-date-picker v-model="addServiceForm.expires_at" type="datetime" value-format="YYYY-MM-DD HH:mm:ss" placeholder="不填则按计费周期自动计算" style="width: 100%;" />
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
            <el-input v-model="addServiceForm.remark" type="textarea" :rows="4" maxlength="200" show-word-limit placeholder="记录本次人工开通说明、上游备注或交付信息" />
          </el-form-item>
        </div>
      </el-form>

      <template #footer>
        <el-button @click="addServiceDialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="addServiceSubmitting" @click="handleSubmitAddService">确认添加</el-button>
      </template>
    </el-dialog>

    <!-- 服务管理抽屉 -->
    <el-drawer
      v-model="serviceConsoleVisible"
      title="服务管理"
      direction="rtl"
      size="1080px"
      class="service-console-drawer"
      destroy-on-close
      @closed="resetServiceConsoleState"
    >
      <div class="service-console-shell" v-loading="serviceConsoleLoading">
        <template v-if="serviceConsoleDetail.id">
          <section class="service-console-head">
            <div class="service-console-copy">
              <div class="service-console-title-row">
                <h3>{{ serviceConsoleDetail.name || '-' }}</h3>
                <el-tag :type="resolveServiceToneTagType(serviceConsoleDetail.status_tone)" effect="plain">
                  {{ serviceConsoleDetail.status_label || '-' }}
                </el-tag>
                <el-tag v-if="serviceConsoleDetail.upstream?.status_label" type="success" effect="plain">
                  {{ serviceConsoleDetail.upstream.status_label }}
                </el-tag>
                <el-tag v-if="serviceConsoleDetail.runtime?.power_label" :type="resolveRuntimeTagType(serviceConsoleDetail.runtime.power_state)" effect="plain">
                  {{ serviceConsoleDetail.runtime.power_label }}
                </el-tag>
              </div>
              <div class="service-console-meta">
                <span>{{ serviceConsoleDetail.domain || '-' }}</span>
                <button
                  type="button"
                  class="service-console-meta-link"
                  :class="{ 'is-disabled': !serviceConsoleOrderLinkAvailable }"
                  @click="handleOpenServiceOrderDetail"
                >
                  订单 {{ serviceConsoleDetail.order?.order_no || '-' }}
                </button>
                <span>上游实例 #{{ serviceConsoleDetail.upstream?.host_id || 0 }}</span>
              </div>
            </div>

            <div class="service-console-actions">
              <el-button :loading="serviceConsoleRefreshing" @click="loadServiceConsoleRemoteStatus()">刷新</el-button>
              <el-button
                v-if="serviceConsoleDetail.actions?.manual_provision"
                type="success"
                :loading="serviceConsoleActionLoading"
                @click="openManualProvisionDialog"
              >
                重试开通
              </el-button>
              <el-button type="primary" :loading="serviceConsoleActionLoading" :disabled="!serviceConsoleDetail.actions?.power" @click="handleServicePower('on')">开机</el-button>
              <el-button :loading="serviceConsoleActionLoading" :disabled="!serviceConsoleDetail.actions?.power" @click="handleServicePower('off')">关机</el-button>
              <el-button :loading="serviceConsoleActionLoading" :disabled="!serviceConsoleDetail.actions?.power" @click="handleServicePower('reboot')">重启</el-button>
              <el-button :loading="serviceConsoleActionLoading" :disabled="!serviceConsoleDetail.actions?.password_reset" @click="openServicePasswordDialog">重置密码</el-button>
              <el-button :loading="serviceConsoleActionLoading" :disabled="!serviceConsoleDetail.actions?.reinstall" @click="openServiceReinstallDialog">重装系统</el-button>
              <el-button :loading="serviceConsoleActionLoading" :disabled="!serviceConsoleDetail.actions?.power" @click="handleOpenServiceVnc">VNC远程</el-button>
              <el-button
                type="warning"
                plain
                :disabled="!serviceConsoleOrderLinkAvailable"
                @click="handleOpenServiceOrderDetail"
              >
                退款
              </el-button>
              <el-button type="danger" plain :loading="serviceConsoleActionLoading" :disabled="!serviceConsoleDetail.actions?.power" @click="handleServicePower('hard_off')">强制关机</el-button>
              <el-button type="danger" plain :loading="serviceConsoleActionLoading" :disabled="!serviceConsoleDetail.actions?.power" @click="handleServicePower('hard_reboot')">强制重启</el-button>
              <el-button type="danger" plain :loading="serviceConsoleActionLoading" @click="handleServiceDelete">删除记录</el-button>
            </div>
          </section>

          <!-- 业务信息 + 主机信息 -->
          <section class="service-console-grid">
            <div class="sc-table-block">
              <div class="sc-table-title">业务信息</div>
              <el-table :data="serviceBusinessRows" border size="small" :show-header="false" class="sc-detail-table">
                <el-table-column prop="label" width="100" />
                <el-table-column prop="value">
                  <template #default="{ row }">
                    <span v-if="row.key === 'amount'" class="service-price-cell">
                      {{ formatMoney(serviceConsoleDetail.amount) }}
                      <el-tag v-if="serviceConsoleDetail.has_custom_renew_pricing" size="small" type="warning" effect="plain" style="margin-left:6px;">已调整</el-tag>
                      <el-button text type="primary" size="small" style="margin-left:4px;" @click="openPricingDialog">设置定价</el-button>
                    </span>
                    <span v-else>{{ row.value }}</span>
                  </template>
                </el-table-column>
              </el-table>
            </div>

            <div class="sc-table-block">
              <div class="sc-table-title">主机信息</div>
              <el-table :data="serviceHostRows" border size="small" :show-header="false" class="sc-detail-table">
                <el-table-column prop="label" width="80" />
                <el-table-column prop="value">
                  <template #default="{ row }">
                    <span v-if="row.key === 'password'">
                      {{ resolvedServicePassword }}
                      <el-button
                        v-if="serviceConsoleDetail.connection?.has_password"
                        text type="primary" size="small"
                        @click="serviceConsoleShowPassword = !serviceConsoleShowPassword"
                      >{{ serviceConsoleShowPassword ? '隐藏' : '显示' }}</el-button>
                    </span>
                    <span v-else>{{ row.value }}</span>
                  </template>
                </el-table-column>
              </el-table>
            </div>
          </section>

          <!-- 规格配置 -->
          <div class="sc-table-block" v-if="serviceConsoleDetail.specs?.length">
            <div class="sc-table-title">规格配置</div>
            <el-table :data="[serviceConsoleDetail.specs.reduce((acc, item) => { acc[item.key] = item.value; return acc }, {})]" border size="small" class="sc-detail-table">
              <el-table-column v-for="item in serviceConsoleDetail.specs" :key="item.key" :label="item.label" :prop="item.key" />
            </el-table>
          </div>
          <el-empty v-else-if="serviceConsoleDetail.id" description="暂无规格配置" :image-size="48" />

          <!-- 运行状态 -->
          <div
            v-if="serviceConsoleDetail.upstream?.remote_error || serviceConsoleDetail.runtime?.description || serviceConsoleTaskStatuses.length"
            class="sc-table-block"
          >
            <div class="sc-table-title">运行状态</div>
            <el-table :data="serviceStatusRows" border size="small" :show-header="false" class="sc-detail-table">
              <el-table-column prop="label" width="100" />
              <el-table-column prop="description">
                <template #default="{ row }">
                  <span :class="row.isDanger ? 'text-danger' : ''">{{ row.description }}</span>
                  <el-button v-if="row.type" text type="primary" size="small" style="margin-left:8px;" @click="handleFetchModuleStatus(row.type)">检查状态</el-button>
                </template>
              </el-table-column>
            </el-table>
          </div>
        </template>

        <el-empty v-else description="请选择需要管理的实例" :image-size="72" />
      </div>
    </el-drawer>

    <el-dialog v-model="serviceRefundDialogVisible" title="服务关联账单退款" width="520px" destroy-on-close>
      <el-alert
        type="warning"
        :closable="false"
        show-icon
        title="当前版本仅支持按原支付金额全额退款。"
      />

      <el-form
        ref="serviceRefundFormRef"
        :model="serviceRefundForm"
        :rules="serviceRefundRules"
        label-width="90px"
        style="margin-top: 18px;"
        v-loading="serviceRefundLoading"
      >
        <el-form-item label="账单编号">
          <el-input :model-value="serviceRefundDetail?.invoice?.invoice_no || serviceConsoleDetail.order?.invoice_no || '--'" disabled />
        </el-form-item>
        <el-form-item label="支付方式">
          <el-input :model-value="serviceRefundDetail?.invoice?.payment_method_label || '--'" disabled />
        </el-form-item>
        <el-form-item label="退款金额">
          <el-input :model-value="serviceRefundAmountText" disabled />
        </el-form-item>
        <el-form-item label="退款方式" prop="refund_method">
          <el-radio-group v-model="serviceRefundForm.refund_method">
            <el-radio value="balance">退回余额</el-radio>
            <el-radio value="original" :disabled="!serviceRefundCanOriginal">原路退款</el-radio>
          </el-radio-group>
          <div v-if="!serviceRefundCanOriginal" class="refund-tip">
            {{ serviceRefundOriginalBlockedReason || '当前支付方式不支持原路退款' }}
          </div>
        </el-form-item>
        <el-form-item label="退款原因" prop="remark">
          <el-input
            v-model="serviceRefundForm.remark"
            type="textarea"
            :rows="4"
            maxlength="200"
            show-word-limit
            placeholder="请输入退款原因"
          />
        </el-form-item>
      </el-form>

      <template #footer>
        <el-button @click="closeServiceRefundDialog">取消</el-button>
        <el-button type="warning" :loading="serviceRefundSubmitting" @click="submitServiceRefund">确认退款</el-button>
      </template>
    </el-dialog>

    <!-- 设置定价弹窗 -->
    <el-dialog v-model="servicePricingDialogVisible" title="设置续费定价" width="760px" destroy-on-close>
      <el-form ref="servicePricingFormRef" :model="servicePricingForm" label-width="0">
        <el-alert style="margin-bottom:16px;" type="info" :closable="false" show-icon>
          <template #title>默认续费价按该实例下单时的订单金额折算四个周期；人工调整后按人工价续费，可单独控制每个周期开关。</template>
        </el-alert>
        <el-divider>续费周期配置</el-divider>
        <el-table :data="BILLING_CYCLES" border size="small" class="pricing-cycle-table">
          <el-table-column label="周期" width="90">
            <template #default="{ row }">
              <strong>{{ row.label }}</strong>
            </template>
          </el-table-column>
          <el-table-column label="开关" width="100" align="center">
            <template #default="{ row }">
              <el-switch
                v-model="servicePricingForm.locked_pricing[row.key].enabled"
                inline-prompt
                active-text="开"
                inactive-text="关"
              />
            </template>
          </el-table-column>
          <el-table-column label="默认快照价" min-width="120" align="center">
            <template #default="{ row }">
              <span class="pricing-cycle-table__price">
                {{ servicePricingForm.locked_pricing[row.key].base_amount != null ? formatMoney(servicePricingForm.locked_pricing[row.key].base_amount) : '未配置' }}
              </span>
            </template>
          </el-table-column>
          <el-table-column label="人工价" min-width="220">
            <template #default="{ row }">
              <el-input-number
                v-model="servicePricingForm.locked_pricing[row.key].manual_amount"
                :min="0"
                :precision="2"
                :step="1"
                :controls="false"
                :disabled="!servicePricingForm.locked_pricing[row.key].enabled"
                style="width:100%;"
                placeholder="留空使用默认快照价"
              />
            </template>
          </el-table-column>
          <el-table-column label="当前生效价" min-width="120" align="center">
            <template #default="{ row }">
              <span class="pricing-cycle-table__price">
                {{
                  servicePricingForm.locked_pricing[row.key].manual_amount != null
                    ? formatMoney(servicePricingForm.locked_pricing[row.key].manual_amount)
                    : (servicePricingForm.locked_pricing[row.key].base_amount != null ? formatMoney(servicePricingForm.locked_pricing[row.key].base_amount) : '未配置')
                }}
              </span>
            </template>
          </el-table-column>
          <el-table-column label="操作" width="110" align="center">
            <template #default="{ row }">
              <el-button text type="primary" @click="servicePricingForm.locked_pricing[row.key].manual_amount = null">
                恢复默认
              </el-button>
            </template>
          </el-table-column>
        </el-table>
        <div class="form-tip">留空人工价时，默认使用购买时快照价格；关闭周期后，前台续费不会显示该周期。</div>
      </el-form>
      <template #footer>
        <el-button @click="servicePricingDialogVisible = false">取消</el-button>
        <el-button type="warning" plain :loading="servicePricingLoading" @click="handleClearLockedPricing">恢复默认</el-button>
        <el-button type="primary" :loading="servicePricingLoading" @click="handleSavePricing">保存定价</el-button>
      </template>
    </el-dialog>

    <!-- 手动开通弹窗 -->
    <el-dialog v-model="serviceManualProvisionDialogVisible" title="重新提交上游开通" width="560px" destroy-on-close>
      <el-alert v-if="serviceConsoleDetail.upstream?.remote_error" type="warning" :closable="false" show-icon style="margin-bottom: 16px;">
        <template #title>{{ serviceConsoleDetail.upstream.remote_error }}</template>
      </el-alert>

      <el-alert type="info" :closable="false" show-icon>
        <template #title>系统会直接复用该订单原始配置，重新加入上游购物车并提交购买。</template>
      </el-alert>

      <div class="service-source-banner" style="margin-top: 16px; margin-bottom: 0;">
        不再人工填写主机名、密码、IP、系统版本和到期时间。
        如果上游再次返回错误，当前服务会继续保持待开通状态，并刷新最新失败原因。
      </div>

      <template #footer>
        <el-button @click="serviceManualProvisionDialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="serviceConsoleActionLoading" @click="handleManualProvisionService">确认重试</el-button>
      </template>
    </el-dialog>

    <!-- 重置密码弹窗 -->
    <el-dialog v-model="servicePasswordDialogVisible" title="重置实例密码" width="420px" destroy-on-close>
      <el-form ref="servicePasswordFormRef" :model="servicePasswordForm" :rules="servicePasswordRules" label-width="90px">
        <el-form-item label="新密码" prop="password">
          <el-input v-model="servicePasswordForm.password" type="password" show-password placeholder="至少 8 位" />
        </el-form-item>
        <el-form-item label="确认密码" prop="password_confirmation">
          <el-input v-model="servicePasswordForm.password_confirmation" type="password" show-password />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="servicePasswordDialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="serviceConsoleActionLoading" @click="handleServiceResetPassword">提交</el-button>
      </template>
    </el-dialog>

    <!-- 重装系统弹窗 -->
    <el-dialog v-model="serviceReinstallDialogVisible" title="重装系统" width="560px" destroy-on-close>
      <div v-loading="serviceReinstallOptionsLoading">
        <el-form :model="serviceReinstallForm" label-width="90px">
          <el-form-item label="系统分组">
            <el-select v-model="serviceReinstallForm.os_group" placeholder="请选择系统分组" @change="handleServiceReinstallGroupChange">
              <el-option v-for="group in serviceReinstallGroupedOptions" :key="group.group_name" :label="group.group_name" :value="group.group_name" />
            </el-select>
          </el-form-item>
          <el-form-item label="系统版本">
            <el-select v-model="serviceReinstallForm.os_id" placeholder="请选择系统版本">
              <el-option v-for="item in currentServiceReinstallOptions" :key="item.os_id" :label="item.name" :value="item.os_id" />
            </el-select>
          </el-form-item>
        </el-form>
        <el-empty v-if="!serviceReinstallGroupedOptions.length" description="当前没有可用的重装系统选项" :image-size="64" />
      </div>
      <template #footer>
        <el-button @click="serviceReinstallDialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="serviceConsoleActionLoading" :disabled="!serviceReinstallForm.os_id" @click="handleServiceReinstall">提交重装</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { computed, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import userApi from '@/api/user'
import productApi from '@/api/product'
import { useUserStore } from '@/stores/user'
import { useUserDetail } from './composables/useUserDetail.js'
import UserBasicInfo from './components/UserBasicInfo.vue'
import UserServices from './components/UserServices.vue'
import UserInvoices from './components/UserInvoices.vue'
import UserBalanceLogs from './components/UserBalanceLogs.vue'
import UserTickets from './components/UserTickets.vue'
import UserOperationLogs from './components/UserOperationLogs.vue'
import { SERVICE_STATUS_MAP, toSelectOptions } from '@shared/statusConfig'

const router = useRouter()
const userStore = useUserStore()

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
  editFormRef,
  editForm,
  rechargeFormRef,
  rechargeForm,
  loadedTabs,
  servicesState,
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
  statsCards,
  infoItems,
  editRules,
  rechargeRules,
  loadDetail,
  reloadDetail,
  handleTabChange,
  loadServices,
  searchServices,
  resetServicesFilters,
  loadInvoices,
  searchInvoices,
  resetInvoicesFilters,
  loadBalance,
  loadTickets,
  loadLogs,
  searchLogs,
  loadNotices,
  reloadNotices,
  handleSave,
  handleToggleStatus,
  openRechargeDialog,
  handleRecharge,
  handleLoginAs,
  openEditDialog,
  goBack,
  handleRefreshService,
  handleRefreshServicesStatus,
  handleDeleteServiceRow,
  patchServiceListItem,
  normalizeServiceConsoleDetail,
  createEmptyServiceConsoleDetail,
  formatMoney,
  formatInteger,
  formatDateTime,
  toNumber,
  resolveInvoiceStatus,
  resolveInvoiceType,
  resolveBalanceType,
  resolvePriority,
  resolveTicketStatus,
  resolveServiceToneTagType,
} = useUserDetail()

// ── 服务控制台状态（仅在 index.vue 使用） ───────────────────
const serviceConsoleVisible = ref(false)
const serviceConsoleLoading = ref(false)
const serviceConsoleRefreshing = ref(false)
const serviceConsoleActionLoading = ref(false)
const serviceConsoleShowPassword = ref(false)
const serviceConsoleDetail = ref(createEmptyServiceConsoleDetail())
const serviceRefundDialogVisible = ref(false)
const serviceRefundLoading = ref(false)
const serviceRefundSubmitting = ref(false)
const serviceRefundFormRef = ref()
const serviceRefundDetail = ref(null)
const serviceRefundForm = reactive({
  refund_method: 'balance',
  remark: '',
})
const serviceConsoleTaskStatus = reactive({ repassword: null, reinstall: null })
const serviceConsoleOrderId = computed(() => Number(serviceConsoleDetail.value?.order?.id || 0))
const serviceConsoleOrderNo = computed(() => String(serviceConsoleDetail.value?.order?.order_no || '').trim())
const serviceConsoleInvoiceId = computed(() => Number(serviceConsoleDetail.value?.order?.invoice_id || 0))
const serviceConsoleOrderLinkAvailable = computed(() => (
  serviceConsoleOrderId.value > 0 || serviceConsoleOrderNo.value !== ''
))
const canManageInvoice = computed(() => userStore.permissions.includes('*') || userStore.permissions.includes('invoice.manage'))
const serviceConsoleRefundButtonEnabled = computed(() => canManageInvoice.value && serviceConsoleInvoiceId.value > 0)
const serviceRefundCanOriginal = computed(() => Boolean(serviceRefundDetail.value?.invoice?.refund_actions?.can_original))
const serviceRefundOriginalBlockedReason = computed(() => String(serviceRefundDetail.value?.invoice?.refund_actions?.original_blocked_reason || ''))
const serviceRefundAmountText = computed(() => {
  const invoice = serviceRefundDetail.value?.invoice || {}
  const amount = invoice.payment_summary?.amount || invoice.paid_amount || invoice.amount || '0.00'

  return `¥${amount}`
})

const BILLING_CYCLES = [
  { key: 'monthly',      label: '月付' },
  { key: 'quarterly',    label: '季付' },
  { key: 'semiannually', label: '半年付' },
  { key: 'annually',     label: '年付' },
]

const servicePricingDialogVisible = ref(false)
const servicePricingLoading = ref(false)
const servicePricingFormRef = ref()
const servicePricingForm = reactive({ locked_pricing: createDefaultPricingCycles() })

const serviceManualProvisionDialogVisible = ref(false)

const servicePasswordDialogVisible = ref(false)
const servicePasswordFormRef = ref()
const servicePasswordForm = reactive({ password: '', password_confirmation: '' })
const servicePasswordRules = {
  password: [
    { required: true, message: '请输入新密码', trigger: 'blur' },
    { min: 8, message: '密码至少 8 位', trigger: 'blur' },
  ],
  password_confirmation: [{
    validator: (_rule, value, callback) => {
      if (value !== servicePasswordForm.password) {
        callback(new Error('两次输入的密码不一致'))
        return
      }
      callback()
    },
    trigger: 'blur',
  }],
}
const serviceRefundRules = {
  refund_method: [{ required: true, message: '请选择退款方式', trigger: 'change' }],
  remark: [
    { required: true, message: '请输入退款原因', trigger: 'blur' },
    { min: 2, max: 200, message: '退款原因长度需为 2-200 个字符', trigger: 'blur' },
  ],
}

const serviceReinstallDialogVisible = ref(false)
const serviceReinstallOptionsLoading = ref(false)
const serviceReinstallOptions = ref([])
const serviceReinstallForm = reactive({ os_group: '', os_id: '' })

// ── 添加服务状态 ──────────────────────────────────────────
const addServiceDialogVisible = ref(false)
const addServiceSubmitting = ref(false)
const addServiceProductsLoading = ref(false)
const addServiceProductDetailLoading = ref(false)
const addServiceFormRef = ref()
const addServiceProductOptions = ref([])
const addServiceProductDetail = ref(null)
const addServiceForm = reactive(createDefaultAddServiceForm())

const addServiceRules = {
  product_id: [{ required: true, message: '请选择商品', trigger: 'change' }],
  billing_cycle: [{ required: true, message: '请选择计费周期', trigger: 'change' }],
  status: [{ required: true, message: '请选择服务状态', trigger: 'change' }],
  amount: [{ required: true, message: '请输入服务金额', trigger: 'blur' }],
  upstream_host_id: [{
    validator: (_rule, value, callback) => {
      if (addServiceForm.source_type === 'upstream' && (!value || Number(value) <= 0)) {
        callback(new Error('请输入有效的上游实例 ID'))
        return
      }
      callback()
    },
    trigger: 'blur',
  }],
}

const serviceStatusOptions = toSelectOptions(SERVICE_STATUS_MAP, false)

// ── 计算属性 ──────────────────────────────────────────────
const serviceConsoleServiceId = computed(() => Number(serviceConsoleDetail.value?.id || 0))

const resolvedServicePassword = computed(() => {
  if (!serviceConsoleDetail.value.connection?.has_password) return '--'
  return serviceConsoleShowPassword.value
    ? (serviceConsoleDetail.value.connection?.password || '--')
    : '••••••••'
})

const serviceReinstallGroupedOptions = computed(() => {
  const groups = {}
  for (const item of serviceReinstallOptions.value) {
    const groupName = item.group_name || '默认分组'
    if (!groups[groupName]) groups[groupName] = { group_name: groupName, items: [] }
    groups[groupName].items.push(item)
  }
  return Object.values(groups)
})

const currentServiceReinstallOptions = computed(() => (
  serviceReinstallGroupedOptions.value.find((item) => item.group_name === serviceReinstallForm.os_group)?.items || []
))

const serviceConsoleTaskStatuses = computed(() => (
  Object.entries(serviceConsoleTaskStatus)
    .map(([type, item]) => {
      if (!item) return null
      return {
        type,
        label: type === 'repassword' ? '密码任务状态' : '重装任务状态',
        description: item.description || item.status || '处理中',
      }
    })
    .filter(Boolean)
))

const serviceBusinessRows = computed(() => {
  const d = serviceConsoleDetail.value
  return [
    { key: 'id', label: '服务 ID', value: `#${d.id || '-'}` },
    { key: 'type', label: '产品类型', value: d.product?.type_label || '-' },
    { key: 'cycle', label: '计费周期', value: d.billing_cycle_label || '-' },
    { key: 'amount', label: '续费价格', value: '' },
    { key: 'invoice_no', label: '账单号', value: d.order?.invoice_no || '-' },
    { key: 'created_at', label: '创建时间', value: d.created_at || '-' },
    { key: 'expires_at', label: '到期时间', value: d.expires_at || '-' },
    { key: 'order_status', label: '订单状态', value: d.order?.status_label || '-' },
    { key: 'upstream_status', label: '上游状态', value: d.upstream?.status_label || '-' },
  ]
})

const serviceHostRows = computed(() => {
  const c = serviceConsoleDetail.value.connection
  return [
    { key: 'public_ip', label: '公网 IP', value: c?.dedicated_ip || '-' },
    { key: 'internal_ip', label: '内网 IP', value: c?.internal_ip || '-' },
    { key: 'hostname', label: '主机名', value: c?.hostname || '-' },
    { key: 'port', label: '端口', value: c?.port || '-' },
    { key: 'username', label: '用户名', value: c?.username || '-' },
    { key: 'password', label: '密码', value: '' },
  ]
})

const serviceStatusRows = computed(() => {
  const d = serviceConsoleDetail.value
  const rows = []
  if (d.runtime?.description) rows.push({ label: '运行说明', description: d.runtime.description, isDanger: false, type: null })
  if (d.upstream?.remote_error) rows.push({ label: '远程错误', description: d.upstream.remote_error, isDanger: true, type: null })
  serviceConsoleTaskStatuses.value.forEach((item) => {
    rows.push({ label: item.label, description: item.description, isDanger: false, type: item.type })
  })
  return rows
})

const addServiceSelectedProduct = computed(() => (
  addServiceProductOptions.value.find((item) => Number(item.id) === Number(addServiceForm.product_id)) || null
))

const addServiceCanLinkUpstream = computed(() => (
  Number(addServiceProductDetail.value?.supplier_id || 0) > 0
  && Number(addServiceProductDetail.value?.supplier_product_id || 0) > 0
))

const addServiceBillingOptions = computed(() => {
  const pricing = addServiceProductDetail.value?.pricing || {}
  return Object.entries(pricing)
    .filter(([, amount]) => Number(amount) > 0)
    .map(([value, amount]) => ({
      value,
      label: `${resolveBillingCycleLabel(value)} · ¥${toNumber(amount).toFixed(2)}`,
      amount: toNumber(amount),
    }))
})

const addServiceUpstreamChannel = computed(() => (
  addServiceProductDetail.value?.supplier_name || addServiceSelectedProduct.value?.supplier_name || '-'
))

// ── 工具函数（本地） ──────────────────────────────────────
function resolveBillingCycleLabel(value) {
  return ({
    monthly: '月付',
    quarterly: '季付',
    semiannually: '半年付',
    annually: '年付',
    biennially: '两年付',
    triennially: '三年付',
    one_time: '一次性',
  })[value] || value || '-'
}

function resolveRuntimeTagType(powerState) {
  const normalized = String(powerState || '').toLowerCase()
  if (['on', 'running', 'active'].includes(normalized)) return 'success'
  if (['off', 'stopped', 'shutdown'].includes(normalized)) return 'info'
  if (normalized !== '') return 'warning'
  return 'info'
}

function mergeServiceConsoleDetail(current = {}, patch = {}) {
  return normalizeServiceConsoleDetail({
    ...current,
    ...patch,
    product: { ...(current.product || {}), ...(patch.product || {}) },
    order: { ...(current.order || {}), ...(patch.order || {}) },
    upstream: { ...(current.upstream || {}), ...(patch.upstream || {}) },
    runtime: { ...(current.runtime || {}), ...(patch.runtime || {}) },
    connection: { ...(current.connection || {}), ...(patch.connection || {}) },
    actions: { ...(current.actions || {}), ...(patch.actions || {}) },
    specs: Array.isArray(patch.specs) ? patch.specs : (Array.isArray(current.specs) ? current.specs : []),
  })
}

function createDefaultAddServiceForm() {
  return {
    source_type: 'manual',
    product_id: null,
    billing_cycle: '',
    status: 1,
    name: '',
    domain: '',
    amount: null,
    expires_at: '',
    auto_renew: 1,
    dedicated_ip: '',
    internal_ip: '',
    port: 22,
    username: '',
    password: '',
    upstream_host_id: null,
    upstream_status: '',
    os: '',
    remark: '',
  }
}

function createDefaultPricingCycles() {
  const cycles = {}
  BILLING_CYCLES.forEach(({ key }) => {
    cycles[key] = {
      enabled: false,
      base_amount: null,
      manual_amount: null,
    }
  })
  return cycles
}

// ── 服务控制台方法 ────────────────────────────────────────
function resetServiceConsoleState() {
  serviceConsoleDetail.value = createEmptyServiceConsoleDetail()
  serviceConsoleRefreshing.value = false
  serviceConsoleShowPassword.value = false
  serviceRefundDialogVisible.value = false
  serviceRefundLoading.value = false
  serviceRefundSubmitting.value = false
  serviceRefundDetail.value = null
  serviceRefundForm.refund_method = 'balance'
  serviceRefundForm.remark = ''
  serviceConsoleTaskStatus.repassword = null
  serviceConsoleTaskStatus.reinstall = null
  serviceManualProvisionDialogVisible.value = false
  servicePasswordDialogVisible.value = false
  serviceReinstallDialogVisible.value = false
  servicePasswordForm.password = ''
  servicePasswordForm.password_confirmation = ''
  serviceReinstallForm.os_group = ''
  serviceReinstallForm.os_id = ''
  serviceReinstallOptions.value = []
}

async function loadServiceConsole(serviceId = serviceConsoleServiceId.value, refresh = false, background = false) {
  if (!serviceId) return
  if (background) serviceConsoleRefreshing.value = true
  else serviceConsoleLoading.value = true
  try {
    const res = await userApi.serviceDetail(userId.value, serviceId, refresh ? { refresh: true } : undefined)
    serviceConsoleDetail.value = mergeServiceConsoleDetail(serviceConsoleDetail.value, res.data || {})
    patchServiceListItem(serviceConsoleDetail.value)
  } finally {
    if (background) serviceConsoleRefreshing.value = false
    else serviceConsoleLoading.value = false
  }
}

async function loadServiceConsoleBase(serviceId = serviceConsoleServiceId.value) {
  if (!serviceId) return
  serviceConsoleLoading.value = true
  try {
    const res = await userApi.serviceBaseDetail(userId.value, serviceId)
    serviceConsoleDetail.value = mergeServiceConsoleDetail(serviceConsoleDetail.value, res.data || {})
    patchServiceListItem(serviceConsoleDetail.value)
  } finally {
    serviceConsoleLoading.value = false
  }
}

async function loadServiceConsoleRemoteStatus(serviceId = serviceConsoleServiceId.value, silent = false) {
  if (!serviceId) return
  serviceConsoleRefreshing.value = true
  try {
    const res = await userApi.serviceRemoteStatus(userId.value, serviceId)
    serviceConsoleDetail.value = mergeServiceConsoleDetail(serviceConsoleDetail.value, res.data || {})
    patchServiceListItem(serviceConsoleDetail.value)
    if (!silent) ElMessage.success('实例状态已刷新')
  } finally {
    serviceConsoleRefreshing.value = false
  }
}

async function openServiceConsole(row) {
  serviceConsoleVisible.value = true
  resetServiceConsoleState()
  const serviceId = Number(row?.id || 0)
  await loadServiceConsoleBase(serviceId)
  void loadServiceConsoleRemoteStatus(serviceId, true)
}

function handleOpenServiceOrderDetail() {
  if (!serviceConsoleOrderLinkAvailable.value) {
    ElMessage.warning('当前服务暂无关联订单')
    return
  }

  router.push({
    name: 'AdminOrders',
    query: {
      ...(serviceConsoleOrderNo.value ? { order_no: serviceConsoleOrderNo.value } : {}),
      ...(serviceConsoleOrderId.value > 0 ? { open_order_id: String(serviceConsoleOrderId.value) } : {}),
    },
  })
}

async function openServiceRefundDialog() {
  if (!serviceConsoleRefundButtonEnabled.value) {
    ElMessage.warning('当前服务暂无可退款账单')
    return
  }

  serviceRefundLoading.value = true
  try {
    const res = await userApi.invoiceDetail(userId.value, serviceConsoleInvoiceId.value)
    const invoice = res.data?.invoice || {}
    const refundActions = invoice?.refund_actions || {}

    if (!refundActions.can_balance && !refundActions.can_original) {
      ElMessage.warning(refundActions.blocked_reason || '当前账单不支持退款')
      return
    }

    serviceRefundDetail.value = res.data || null
    serviceRefundForm.refund_method = refundActions.can_original ? 'original' : 'balance'
    serviceRefundForm.remark = serviceRefundForm.refund_method === 'original' ? '后台发起原路退款' : '后台退回用户余额'
    serviceRefundDialogVisible.value = true
  } finally {
    serviceRefundLoading.value = false
  }
}

function closeServiceRefundDialog() {
  if (serviceRefundSubmitting.value) {
    return
  }

  serviceRefundDialogVisible.value = false
  serviceRefundDetail.value = null
  serviceRefundForm.refund_method = 'balance'
  serviceRefundForm.remark = ''
  serviceRefundFormRef.value?.clearValidate?.()
}

async function submitServiceRefund() {
  const invoiceId = Number(serviceRefundDetail.value?.invoice?.id || serviceConsoleInvoiceId.value || 0)
  if (!invoiceId) {
    return
  }

  await serviceRefundFormRef.value?.validate()
  serviceRefundSubmitting.value = true

  try {
    const invoice = serviceRefundDetail.value?.invoice || {}
    const paymentSummary = invoice.payment_summary || {}

    const res = await userApi.refundInvoice(userId.value, invoiceId, {
      refund_method: serviceRefundForm.refund_method,
      amount: paymentSummary.amount || invoice.paid_amount || invoice.amount,
      remark: serviceRefundForm.remark,
    })

    ElMessage.success(res.message || '账单已完成退款')
    closeServiceRefundDialog()
    await Promise.all([
      reloadDetail(),
      loadInvoices(),
      loadServiceConsole(serviceConsoleServiceId.value, true),
    ])
  } catch (error) {
    ElMessage.error(error?.response?.data?.message || '账单退款失败')
  } finally {
    serviceRefundSubmitting.value = false
  }
}

async function handleServicePower(action) {
  if (!serviceConsoleServiceId.value) return
  serviceConsoleActionLoading.value = true
  try {
    const res = await userApi.servicePower(userId.value, serviceConsoleServiceId.value, { action })
    if (res.data?.detail) {
      serviceConsoleDetail.value = normalizeServiceConsoleDetail(res.data.detail)
      patchServiceListItem(serviceConsoleDetail.value)
    }
    ElMessage.success(res.data?.message || res.message || '操作已提交')
  } finally {
    serviceConsoleActionLoading.value = false
  }
}

function openManualProvisionDialog() {
  serviceManualProvisionDialogVisible.value = true
}

async function handleManualProvisionService() {
  serviceConsoleActionLoading.value = true
  try {
    const res = await userApi.manualProvisionService(userId.value, serviceConsoleServiceId.value, {})
    serviceConsoleDetail.value = normalizeServiceConsoleDetail(res.data || {})
    patchServiceListItem(serviceConsoleDetail.value)
    serviceManualProvisionDialogVisible.value = false
    await reloadDetail()
    ElMessage.success(res.message || '已重新提交上游开通')
  } catch (error) {
    await reloadDetail()
    ElMessage.error(error?.response?.data?.message || '重新提交上游开通失败')
  } finally {
    serviceConsoleActionLoading.value = false
  }
}

function openServicePasswordDialog() {
  servicePasswordForm.password = ''
  servicePasswordForm.password_confirmation = ''
  servicePasswordDialogVisible.value = true
}

async function handleServiceResetPassword() {
  try { await servicePasswordFormRef.value?.validate() } catch { return }
  serviceConsoleActionLoading.value = true
  try {
    const res = await userApi.serviceResetPassword(userId.value, serviceConsoleServiceId.value, {
      password: servicePasswordForm.password,
      password_confirmation: servicePasswordForm.password_confirmation,
    })
    if (res.data?.detail) {
      serviceConsoleDetail.value = normalizeServiceConsoleDetail(res.data.detail)
      patchServiceListItem(serviceConsoleDetail.value)
    }
    if (res.data?.status) serviceConsoleTaskStatus.repassword = res.data.status
    servicePasswordDialogVisible.value = false
    ElMessage.success(res.data?.message || res.message || '重置密码指令已提交')
  } finally {
    serviceConsoleActionLoading.value = false
  }
}

async function loadServiceReinstallOptions(refresh = false) {
  serviceReinstallOptionsLoading.value = true
  try {
    const res = await userApi.serviceReinstallOptions(
      userId.value,
      serviceConsoleServiceId.value,
      refresh ? { refresh: true } : undefined
    )
    serviceReinstallOptions.value = Array.isArray(res.data?.os) ? res.data.os : []
    const firstGroup = serviceReinstallGroupedOptions.value[0]
    serviceReinstallForm.os_group = firstGroup?.group_name || ''
    serviceReinstallForm.os_id = firstGroup?.items?.[0]?.os_id || ''
  } finally {
    serviceReinstallOptionsLoading.value = false
  }
}

async function openServiceReinstallDialog() {
  serviceReinstallDialogVisible.value = true
  await loadServiceReinstallOptions(false)
}

function handleServiceReinstallGroupChange(groupName) {
  const group = serviceReinstallGroupedOptions.value.find((item) => item.group_name === groupName)
  serviceReinstallForm.os_id = group?.items?.[0]?.os_id || ''
}

async function handleServiceReinstall() {
  if (!serviceReinstallForm.os_id) { ElMessage.warning('请选择系统版本'); return }
  serviceConsoleActionLoading.value = true
  try {
    const res = await userApi.serviceReinstall(userId.value, serviceConsoleServiceId.value, { os_id: serviceReinstallForm.os_id })
    if (res.data?.detail) {
      serviceConsoleDetail.value = normalizeServiceConsoleDetail(res.data.detail)
      patchServiceListItem(serviceConsoleDetail.value)
    }
    if (res.data?.status) serviceConsoleTaskStatus.reinstall = res.data.status
    serviceReinstallDialogVisible.value = false
    ElMessage.success(res.data?.message || res.message || '重装系统任务已提交')
  } finally {
    serviceConsoleActionLoading.value = false
  }
}

async function handleFetchModuleStatus(type) {
  serviceConsoleActionLoading.value = true
  try {
    const res = await userApi.serviceModuleStatus(userId.value, serviceConsoleServiceId.value, { type })
    if (type === 'host') await loadServiceConsole(serviceConsoleServiceId.value, true)
    else serviceConsoleTaskStatus[type] = res.data || null
  } finally {
    serviceConsoleActionLoading.value = false
  }
}

async function handleOpenServiceVnc() {
  if (!serviceConsoleServiceId.value) return
  serviceConsoleActionLoading.value = true
  try {
    const res = await userApi.serviceVnc(userId.value, serviceConsoleServiceId.value)
    const url = String(res.data?.url || '').trim()
    if (url) {
      const targetUrl = new URL(url, window.location.origin)
      targetUrl.searchParams.set('admin_user_id', String(userId.value))
      targetUrl.searchParams.set('service_id', String(serviceConsoleServiceId.value))
      window.open(targetUrl.toString(), '_blank', 'noopener,noreferrer')
      ElMessage.success(res.data?.message || 'VNC 链接已打开')
      return
    }
    ElMessage.warning('未获取到 VNC 地址')
  } finally {
    serviceConsoleActionLoading.value = false
  }
}

async function handleServiceDelete() {
  if (!serviceConsoleServiceId.value) return
  try {
    await ElMessageBox.confirm(
      `确认删除实例"${serviceConsoleDetail.value.name || serviceConsoleServiceId.value}"记录吗？`,
      '删除实例记录',
      { confirmButtonText: '确认删除', cancelButtonText: '取消', type: 'warning' }
    )
  } catch { return }
  serviceConsoleActionLoading.value = true
  try {
    await userApi.serviceDelete(userId.value, serviceConsoleServiceId.value)
    ElMessage.success('实例记录已删除')
    serviceConsoleVisible.value = false
    await Promise.all([loadServices(), reloadDetail()])
  } finally {
    serviceConsoleActionLoading.value = false
  }
}

// ── 定价方法 ──────────────────────────────────────────────
function openPricingDialog() {
  const s = serviceConsoleDetail.value
  const pricingCycles = Array.isArray(s.renew_pricing_cycles) ? s.renew_pricing_cycles : []
  servicePricingForm.locked_pricing = createDefaultPricingCycles()
  BILLING_CYCLES.forEach(({ key }) => {
    const matched = pricingCycles.find((item) => item.billing_cycle === key) || {}
    servicePricingForm.locked_pricing[key] = {
      enabled: Boolean(matched.enabled),
      base_amount: matched.base_amount != null && matched.base_amount !== '' ? Number(matched.base_amount) : null,
      manual_amount: matched.manual_amount != null && matched.manual_amount !== '' ? Number(matched.manual_amount) : null,
    }
  })
  servicePricingFormRef.value?.clearValidate?.()
  servicePricingDialogVisible.value = true
}

async function handleSavePricing() {
  servicePricingLoading.value = true
  try {
    const lockedPricing = {}
    BILLING_CYCLES.forEach(({ key }) => {
      const current = servicePricingForm.locked_pricing[key] || {}
      const baseAmount = current.base_amount != null && current.base_amount !== '' ? Number(current.base_amount) : 0
      const manualAmount = current.manual_amount != null && current.manual_amount !== '' && !Number.isNaN(Number(current.manual_amount))
        ? Number(current.manual_amount)
        : null
      const normalizedManualAmount = manualAmount != null && Math.abs(manualAmount - baseAmount) < 0.0001
        ? null
        : manualAmount

      lockedPricing[key] = {
        enabled: Boolean(current.enabled),
        manual_amount: normalizedManualAmount,
      }
    })

    for (const cycle of BILLING_CYCLES) {
      const current = servicePricingForm.locked_pricing[cycle.key] || {}
      const baseAmount = current.base_amount != null && current.base_amount !== '' ? Number(current.base_amount) : 0
      const manualAmount = current.manual_amount != null && current.manual_amount !== '' && !Number.isNaN(Number(current.manual_amount))
        ? Number(current.manual_amount)
        : null
      const effectiveAmount = manualAmount != null ? manualAmount : baseAmount

      if (current.enabled && effectiveAmount <= 0) {
        ElMessage.warning(`${cycle.label}已开启，请填写有效价格或恢复默认价格`)
        servicePricingLoading.value = false
        return
      }
    }

    await userApi.updateServiceMeta(userId.value, serviceConsoleDetail.value.id, {
      locked_pricing: lockedPricing,
    })
    ElMessage.success('定价已更新')
    servicePricingDialogVisible.value = false
    await loadServiceConsole(serviceConsoleDetail.value.id)
  } catch (e) {
    ElMessage.error(e?.response?.data?.message || '保存失败')
  } finally {
    servicePricingLoading.value = false
  }
}

async function handleClearLockedPricing() {
  try {
    await ElMessageBox.confirm('确认恢复默认续费配置？恢复后将重新使用该实例购买时的周期价格快照。', '恢复默认续费定价', {
      confirmButtonText: '确认恢复',
      cancelButtonText: '取消',
      type: 'warning',
    })
  } catch { return }
  servicePricingLoading.value = true
  try {
    await userApi.updateServiceMeta(userId.value, serviceConsoleDetail.value.id, {
      clear_locked_pricing: true,
    })
    ElMessage.success('默认续费定价已恢复')
    servicePricingDialogVisible.value = false
    await loadServiceConsole(serviceConsoleDetail.value.id)
  } catch (e) {
    ElMessage.error(e?.response?.data?.message || '操作失败')
  } finally {
    servicePricingLoading.value = false
  }
}

// ── 添加服务方法 ──────────────────────────────────────────
function resetAddServiceForm() {
  Object.assign(addServiceForm, createDefaultAddServiceForm())
  addServiceProductDetail.value = null
}

async function loadAddServiceProducts() {
  addServiceProductsLoading.value = true
  try {
    const res = await productApi.list({ status: 1, page: 1, page_size: 200 })
    addServiceProductOptions.value = Array.isArray(res.data?.list) ? res.data.list : []
  } finally {
    addServiceProductsLoading.value = false
  }
}

function syncAddServiceAmountFromCycle() {
  const matchedCycle = addServiceBillingOptions.value.find((item) => item.value === addServiceForm.billing_cycle)
  addServiceForm.amount = matchedCycle ? matchedCycle.amount : null
}

async function handleAddServiceProductChange() {
  addServiceProductDetail.value = null
  addServiceForm.billing_cycle = ''
  addServiceForm.amount = null
  addServiceForm.upstream_host_id = null
  if (!addServiceForm.product_id) return

  addServiceProductDetailLoading.value = true
  try {
    const res = await productApi.detail(addServiceForm.product_id)
    addServiceProductDetail.value = res.data || null
    addServiceForm.name = addServiceForm.name || addServiceProductDetail.value?.name || ''
    const firstCycle = addServiceBillingOptions.value[0]
    addServiceForm.billing_cycle = firstCycle?.value || ''
    syncAddServiceAmountFromCycle()
    if (addServiceForm.source_type === 'upstream' && !addServiceCanLinkUpstream.value) {
      addServiceForm.source_type = 'manual'
    }
  } finally {
    addServiceProductDetailLoading.value = false
  }
}

function handleAddServiceSourceChange() {
  if (addServiceForm.source_type === 'upstream' && !addServiceCanLinkUpstream.value) {
    ElMessage.warning('当前商品未绑定可控上游，无法对接上游主机')
    addServiceForm.source_type = 'manual'
  }
}

async function openAddServiceDialog() {
  addServiceDialogVisible.value = true
  resetAddServiceForm()
  if (!addServiceProductOptions.value.length) await loadAddServiceProducts()
}

async function handleSubmitAddService() {
  try { await addServiceFormRef.value?.validate() } catch { return }
  const payload = {
    ...addServiceForm,
    product_id: Number(addServiceForm.product_id || 0),
    amount: toNumber(addServiceForm.amount),
    auto_renew: Number(addServiceForm.auto_renew ? 1 : 0),
    port: addServiceForm.port ? Number(addServiceForm.port) : null,
    expires_at: addServiceForm.expires_at || null,
    upstream_host_id: addServiceForm.source_type === 'upstream'
      ? Number(addServiceForm.upstream_host_id || 0)
      : null,
  }
  addServiceSubmitting.value = true
  try {
    await userApi.storeService(userId.value, payload)
    ElMessage.success('实例已添加')
    addServiceDialogVisible.value = false
    await Promise.all([loadServices(), reloadDetail()])
  } finally {
    addServiceSubmitting.value = false
  }
}
</script>

<style lang="scss" scoped>
.user-detail-page { gap: 18px; }
.tabs-card { overflow: hidden; }

/* Hero */
.user-hero { align-items: flex-start; }
.hero-main { display: flex; flex-direction: column; gap: 18px; min-width: 0; }
.back-link { align-self: flex-start; padding: 0; }
.hero-profile { display: flex; align-items: center; gap: 18px; min-width: 0; }
.hero-avatar {
  display: grid; place-items: center; width: 72px; height: 72px;
  border-radius: 24px; background: linear-gradient(135deg, #165dff, #4f88ff);
  color: #fff; font-size: 30px; font-weight: 700;
  box-shadow: 0 18px 36px rgba(22,93,255,.18); flex-shrink: 0;
}
.hero-copy { min-width: 0; }
.hero-title-row { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.hero-title-row h2 { margin: 0; }
.hero-copy p { margin-top: 8px; color: #516076; font-size: 15px; }
.hero-meta { display: flex; gap: 18px; margin-top: 12px; color: #7a8799; font-size: 13px; flex-wrap: wrap; }
.hero-actions { display: flex; justify-content: flex-end; align-items: center; gap: 10px; flex-wrap: wrap; }

/* Stats */
.stats-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 14px; }
.stats-card {
  padding: 18px 20px; border: 1px solid #e5eaf3; border-radius: 14px;
  background: #fff; box-shadow: 0 2px 10px rgba(15,23,42,.04);
}
.stats-card span { display: block; color: #7a8799; font-size: 13px; }
.stats-card strong { display: block; margin-top: 12px; font-size: 28px; font-weight: 700; line-height: 1.1; }
.stats-card--success strong, .text-success { color: #12b76a; }
.stats-card--danger strong, .text-danger { color: #f04438; }
.stats-card--warning strong, .text-warning { color: #f59e0b; }
.stats-card--primary strong, .text-primary { color: #165dff; }

/* Tabs */
.tabs-card :deep(.el-tabs__header) { margin-bottom: 14px; }
.tabs-card :deep(.el-tabs__item) { height: 38px; font-size: 15px; }

/* Notices toolbar */
.toolbar { display: flex; align-items: center; gap: 10px; margin-bottom: 12px; flex-wrap: wrap; }
.toolbar.compact :deep(.el-input), .toolbar.compact :deep(.el-select) { width: 150px; }
.pager { display: flex; justify-content: flex-end; margin-top: 14px; }

/* Referral */
.referral-metrics { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 14px; }
.referral-metric { padding: 16px; border: 1px solid #eef2f7; border-radius: 12px; background: linear-gradient(180deg,#fff,#f8fbff); }
.referral-metric span { display: block; color: #7a8799; font-size: 12px; }
.referral-metric strong { display: block; margin-top: 10px; color: #1f2937; font-size: 18px; line-height: 1.4; word-break: break-word; }
.recent-referrals { margin-top: 18px; padding-top: 18px; border-top: 1px solid #eef2f7; }
.recent-referrals__head { display: flex; justify-content: space-between; gap: 12px; margin-bottom: 14px; }
.recent-referrals__head strong { color: #1f2937; font-size: 15px; }
.referral-list { display: grid; gap: 12px; }
.referral-list__item { display: flex; justify-content: space-between; gap: 16px; align-items: center; padding: 14px 16px; border: 1px solid #eef2f7; border-radius: 12px; background: #f8fafc; }
.referral-list__item strong { color: #1f2937; font-size: 14px; }
.referral-list__item p { margin-top: 4px; color: #7a8799; font-size: 13px; }
.referral-list__item span { color: #5b6b82; font-size: 12px; white-space: nowrap; }

/* Service Console */
.service-console-shell { display: flex; flex-direction: column; gap: 18px; }
.service-console-head { display: flex; justify-content: space-between; gap: 18px; align-items: flex-start; }
.service-console-copy { min-width: 0; }
.service-console-title-row { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.service-console-title-row h3 { margin: 0; color: #1f2937; font-size: 24px; font-weight: 700; }
.service-console-meta { display: flex; gap: 14px; flex-wrap: wrap; margin-top: 12px; color: #7a8799; font-size: 13px; }
.service-console-meta-link {
  padding: 0;
  border: 0;
  background: transparent;
  color: #165dff;
  font-size: inherit;
  line-height: inherit;
  cursor: pointer;

  &:hover {
    text-decoration: underline;
  }

  &.is-disabled {
    color: #7a8799;
    cursor: default;
  }

  &.is-disabled:hover {
    text-decoration: none;
  }
}
.service-console-actions { display: flex; gap: 10px; flex-wrap: wrap; justify-content: flex-end; }
.service-console-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }

.sc-table-block { margin-bottom: 16px; }
.sc-table-title { font-size: 13px; font-weight: 600; color: #374151; padding: 0 2px 8px; border-bottom: 1px solid #e5e7eb; margin-bottom: 0; }
.sc-detail-table { width: 100%; }
.sc-detail-table :deep(.el-table__cell) { padding: 8px 12px; font-size: 13px; }
.sc-detail-table :deep(td:first-child .cell) { color: #6b7280; font-weight: 500; white-space: nowrap; }
.sc-detail-table :deep(td:last-child .cell) { color: #111827; word-break: break-word; }

.service-price-cell { display: inline-flex; align-items: center; gap: 4px; }
.pricing-cycle-table { width: 100%; }
.pricing-cycle-table :deep(.el-table__cell) { padding: 10px 12px; }
.pricing-cycle-table__price { color: #111827; font-weight: 600; }

/* Add Service */
.service-source-banner { margin-bottom: 16px; padding: 12px 14px; border-radius: 10px; background: #f5f7fb; color: #667085; font-size: 13px; line-height: 1.7; }
.service-source-banner.success { background: #ecfdf3; color: #15803d; }
.service-form-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 2px 14px; }
.service-form-span-2 { grid-column: span 2; }
.service-product-option { display: flex; justify-content: space-between; gap: 12px; align-items: center; }
.service-product-option strong { color: #1f2937; font-size: 13px; }
.service-product-option span { color: #7a8799; font-size: 12px; white-space: nowrap; }

.form-tip { font-size: 12px; color: #9ca3af; margin-top: 4px; }

@media (max-width: 1280px) {
  .stats-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .referral-metrics { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .service-console-grid { grid-template-columns: 1fr; }
}

@media (max-width: 768px) {
  .hero-profile { align-items: flex-start; }
  .stats-grid, .referral-metrics { grid-template-columns: 1fr; }
  .hero-actions { justify-content: stretch; }
  .hero-actions :deep(.el-button) { width: 100%; }
  .service-form-grid { grid-template-columns: 1fr; }
  .service-form-span-2 { grid-column: span 1; }
  .service-console-head, .service-console-actions { flex-direction: column; align-items: flex-start; }
  .referral-list__item { flex-direction: column; align-items: flex-start; }
}
</style>
