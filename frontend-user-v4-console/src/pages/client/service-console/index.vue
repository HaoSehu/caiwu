<template>
  <section class="service-console">
    <div class="console-breadcrumb">
      <button type="button" class="console-breadcrumb__link" @click="router.push('/client/services')">用户中心</button>
      <span class="console-breadcrumb__sep">/</span>
      <span>{{ detail.product?.type_label || '实例服务' }}</span>
      <span class="console-breadcrumb__sep">/</span>
      <strong>{{ detail.name || `实例 #${serviceId}` }}</strong>
    </div>

    <LoadingState :loading="detailLoading" text="正在加载实例控制台">
      <t-card class="console-header-card" :bordered="false">
        <div class="console-header-main">
          <div class="console-title-line">
            <h1>{{ detail.name || `服务 #${serviceId}` }}</h1>
            <t-button theme="primary" variant="text" @click="openNameDialog">修改名称</t-button>
            <t-tag :theme="resolveTdesignStatusTheme(detail)" variant="light">{{ resolveServiceStatusLabel(detail.status) }}</t-tag>
            <t-tag v-if="detail.product?.type_label" variant="light">{{ detail.product.type_label }}</t-tag>
          </div>

          <div class="console-meta-line">
            <span>实例 ID：{{ detail.id || '--' }}</span>
            <span>地址：{{ serviceRegion }}</span>
            <span>{{ primaryConnectionLabel }}：{{ primaryConnectionText }}</span>
          </div>

          <div class="console-remark-line">
            <span>备注：</span>
            <strong :class="{ 'is-empty': !detail.remark }">{{ detail.remark || '点击添加备注' }}</strong>
            <t-button
              shape="square"
              variant="text"
              size="small"
              :aria-label="detail.remark ? '编辑备注' : '添加备注'"
              @click="openRemarkDialog"
            >
              <template #icon><EditIcon /></template>
            </t-button>
          </div>
        </div>

        <div class="console-header-actions">
          <t-button v-if="isInstanceRunning" variant="outline" :disabled="!detail.actions?.power || actionLoading" @click="handlePowerAction('off')">
            <template #icon><PauseCircleFilledIcon /></template>
            关机
          </t-button>
          <t-button v-else theme="primary" :disabled="!detail.actions?.power || actionLoading" @click="handlePowerAction('on')">
            <template #icon><PlayCircleFilledIcon /></template>
            开机
          </t-button>
          <t-button variant="outline" :disabled="!detail.actions?.power || actionLoading" @click="handlePowerAction('reboot')">
            <template #icon><RotateIcon /></template>
            重启
          </t-button>
          <t-button variant="outline" :loading="statusSyncing" :disabled="!canSyncStatus || actionLoading" @click="handleSyncStatus">
            <template #icon><RefreshIcon /></template>
            状态同步
          </t-button>
          <t-dropdown trigger="click" :options="moreOptions" @click="({ value }: { value: string | number }) => handleMoreCommand(String(value))">
            <t-button variant="outline">
              <template #icon><EllipsisIcon /></template>
              更多
            </t-button>
          </t-dropdown>
        </div>
      </t-card>

      <t-alert v-if="isExpiringSoon" theme="warning" class="console-alert">
        实例将于 {{ detail.expires_at }} 到期，建议提前续费避免服务中断。
        <template #operation>
          <t-button variant="text" theme="primary" @click="openRenewDialog">立即续费</t-button>
        </template>
      </t-alert>

      <t-alert v-if="!canManageConsole" theme="info" class="console-alert">
        当前实例未接入完整控制能力，页面将以只读模式展示基础信息。
      </t-alert>

      <div class="console-workbench">
        <aside class="console-sidebar">
          <button
            v-for="item in consoleNavItems"
            :key="item.key"
            type="button"
            class="console-sidebar-item"
            :class="{ 'is-active': activeTab === item.key }"
            @click="activeTab = item.key"
          >
            <component :is="item.icon" />
            <span>{{ item.label }}</span>
          </button>
        </aside>

        <main class="console-content">
          <section v-if="activeTab === 'overview'" class="console-overview-grid">
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
                <InfoCell :label="primaryConnectionLabel" :value="primaryConnectionText" copyable @copy="copyText" />
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
                    <t-tooltip v-if="detail.connection?.has_password" :content="showPassword ? '隐藏密码' : '显示密码'">
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
                    <t-tooltip v-if="detail.connection?.has_password" content="复制密码">
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

          <section v-else-if="activeTab === 'monitor'" class="console-panel-section">
            <t-card title="监控信息" :bordered="false">
              <template #actions>
                <t-space>
                  <t-radio-group v-model="monitorState.range" variant="default-filled" @change="() => loadMonitor(true)">
                    <t-radio-button value="3h">3 小时</t-radio-button>
                    <t-radio-button value="24h">24 小时</t-radio-button>
                    <t-radio-button value="7d">7 天</t-radio-button>
                    <t-radio-button value="30d">30 天</t-radio-button>
                  </t-radio-group>
                  <t-button :loading="monitorState.loading" @click="loadMonitor(true)">刷新</t-button>
                </t-space>
              </template>
              <t-alert v-if="monitorState.error" theme="warning" class="console-inline-alert">{{ monitorState.error }}</t-alert>
              <t-empty v-if="monitorState.supported === false" :description="monitorState.message || '当前实例暂不支持监控'" />
              <div v-else class="monitor-grid">
                <article v-for="chart in monitorChartViews" :key="chart.key" class="monitor-card">
                  <header class="monitor-card-header">
                    <div>
                      <span>{{ chart.label }}</span>
                      <strong>{{ chart.latestText }}</strong>
                    </div>
                    <small v-if="chart.latestTime">{{ chart.latestTime }}</small>
                  </header>
                  <div v-if="chart.series.length" class="monitor-chart-shell">
                    <div class="monitor-y-axis">
                      <span
                        v-for="tick in chart.yAxisTicks"
                        :key="tick.key"
                        :style="{ top: `${tick.top}%` }"
                      >
                        {{ tick.label }}
                      </span>
                    </div>
                    <div class="monitor-chart-main">
                      <div class="monitor-chart-plot">
                        <svg
                          class="monitor-chart"
                          :viewBox="`0 0 ${MONITOR_CHART_WIDTH} ${MONITOR_CHART_HEIGHT}`"
                          role="img"
                          :aria-label="`${chart.label}趋势图`"
                          preserveAspectRatio="none"
                          @mousemove="handleMonitorPointerMove($event, chart)"
                          @mouseleave="clearMonitorPointer"
                        >
                          <g class="monitor-chart-grid">
                            <line
                              v-for="tick in chart.yAxisTicks"
                              :key="`${tick.key}-line`"
                              x1="0"
                              :y1="tick.y"
                              :x2="MONITOR_CHART_WIDTH"
                              :y2="tick.y"
                            />
                          </g>
                          <line x1="0" :y1="MONITOR_CHART_BOTTOM" :x2="MONITOR_CHART_WIDTH" :y2="MONITOR_CHART_BOTTOM" class="monitor-chart-axis" />
                          <line x1="0" :y1="MONITOR_CHART_TOP" x2="0" :y2="MONITOR_CHART_BOTTOM" class="monitor-chart-axis" />
                          <path
                            v-for="series in chart.series"
                            :key="series.key"
                            class="monitor-chart-line"
                            :d="series.path"
                            :stroke="series.color"
                            :stroke-width="series.lineWidth"
                          />
                          <g v-if="resolveActiveMonitorPoint(chart)" class="monitor-chart-pointer">
                            <line
                              :x1="resolveActiveMonitorPoint(chart)?.x"
                              :y1="MONITOR_CHART_TOP"
                              :x2="resolveActiveMonitorPoint(chart)?.x"
                              :y2="MONITOR_CHART_BOTTOM"
                            />
                            <template
                              v-for="seriesPoint in resolveActiveMonitorPoint(chart)?.seriesPoints || []"
                              :key="`${seriesPoint.key}-point`"
                            >
                              <circle class="monitor-point-halo" :cx="seriesPoint.x" :cy="seriesPoint.y" r="4.8" :stroke="seriesPoint.color" stroke-width="1.5" />
                              <circle :cx="seriesPoint.x" :cy="seriesPoint.y" r="2.2" :fill="seriesPoint.color" />
                            </template>
                          </g>
                        </svg>
                        <div
                          v-if="resolveActiveMonitorPoint(chart)"
                          class="monitor-tooltip"
                          :style="resolveMonitorTooltipStyle(chart)"
                        >
                          <strong>{{ resolveActiveMonitorPoint(chart)?.time }}</strong>
                          <span
                            v-for="seriesPoint in resolveActiveMonitorPoint(chart)?.seriesPoints || []"
                            :key="seriesPoint.key"
                            class="monitor-tooltip-row"
                          >
                            <i :style="{ backgroundColor: seriesPoint.color }"></i>
                            <b>{{ seriesPoint.name || chart.label }}</b>
                            <em>{{ seriesPoint.valueText }}</em>
                          </span>
                        </div>
                      </div>
                      <div class="monitor-x-axis">
                        <span>{{ chart.xAxisLabels.start }}</span>
                        <span>{{ chart.xAxisLabels.middle }}</span>
                        <span>{{ chart.xAxisLabels.end }}</span>
                      </div>
                    </div>
                  </div>
                  <div v-else class="monitor-chart-empty">{{ chart.message || '当前时间范围内暂无趋势数据' }}</div>
                  <div class="monitor-metrics">
                    <span>平均 <strong>{{ chart.averageText }}</strong></span>
                    <span>峰值 <strong>{{ chart.peakText }}</strong></span>
                    <span>最低 <strong>{{ chart.lowestText }}</strong></span>
                  </div>
                </article>
                <t-empty v-if="!monitorState.loading && !monitorState.charts.length" description="当前时间范围内暂无监控数据" />
              </div>
            </t-card>
          </section>

          <section v-else-if="activeTab === 'security'" class="console-panel-section">
            <t-card title="安全组" :bordered="false">
              <template #actions>
                <t-space>
                  <t-button :loading="securityState.loading" @click="loadSecurityGroups">刷新</t-button>
                  <t-button theme="primary" :disabled="securityState.supported === false" @click="openSecurityGroupDialog">新建安全组</t-button>
                </t-space>
              </template>
              <t-alert v-if="securityState.error" theme="warning" class="console-inline-alert">{{ securityState.error }}</t-alert>
              <t-empty v-else-if="securityState.supported === false" :description="securityState.message || '当前实例暂不支持安全组'" />
              <template v-else>
                <div v-if="securityState.groups.length" class="security-group-list">
                  <article
                    v-for="group in securityState.groups"
                    :key="group.id"
                    class="security-group-card"
                    :class="{ 'is-active': activeSecurityGroup?.id === group.id }"
                    @click="group.can_view !== false && selectSecurityGroup(group)"
                  >
                    <div class="security-group-card__main">
                      <div class="security-row__name">
                        <strong>{{ group.name || `安全组 #${group.id}` }}</strong>
                        <t-tag size="small" variant="outline">ID {{ group.id }}</t-tag>
                        <t-tag v-if="group.is_applied" size="small" theme="success" variant="light">已应用</t-tag>
                        <t-tag v-else size="small" variant="light">未应用</t-tag>
                      </div>
                      <p>{{ group.description || '暂无备注说明' }}</p>
                    </div>
                    <div class="security-group-card__actions">
                      <t-button size="small" variant="outline" :disabled="group.can_view === false || securityState.submitting" @click.stop="selectSecurityGroup(group)">查看</t-button>
                      <t-button
                        v-if="!group.is_applied"
                        size="small"
                        theme="primary"
                        variant="outline"
                        :disabled="!group.can_apply || group.apply_disabled || securityState.submitting"
                        @click.stop="applySecurityGroup(group)"
                      >
                        应用
                      </t-button>
                      <t-button
                        size="small"
                        theme="danger"
                        variant="outline"
                        :disabled="!group.can_delete || group.delete_disabled || securityState.submitting"
                        @click.stop="deleteSecurityGroup(group)"
                      >
                        删除
                      </t-button>
                    </div>
                  </article>
                </div>
                <t-empty v-else description="当前没有安全组，可先创建后再添加规则" />

                <div v-if="activeSecurityGroup" class="security-rules-panel">
                  <div class="security-rules-panel__head">
                    <div>
                      <strong>{{ activeSecurityGroup.name || `安全组 #${activeSecurityGroup.id}` }} 规则</strong>
                      <span>{{ securityState.rules.length }} 条规则</span>
                    </div>
                    <t-button
                      theme="primary"
                      :disabled="!activeSecurityGroup.can_add_rule || securityState.submitting"
                      @click="openSecurityRuleDialog"
                    >
                      新增规则
                    </t-button>
                  </div>
                  <t-table
                    row-key="id"
                    :data="securityState.rules"
                    :columns="securityColumns"
                    :pagination="null"
                    :loading="securityState.rulesLoading"
                    size="small"
                  >
                    <template #operation="{ row }">
                      <t-button theme="danger" variant="text" :disabled="securityState.submitting" @click="deleteSecurityRule(row)">删除</t-button>
                    </template>
                  </t-table>
                </div>
              </template>
            </t-card>
          </section>

          <section v-else-if="activeTab === 'nat'" class="console-panel-section">
            <t-card title="端口转发" :bordered="false">
              <template #actions>
                <t-button :loading="natState.loading" @click="loadNatForwardings">刷新</t-button>
              </template>
              <t-alert v-if="natState.error" theme="warning" class="console-inline-alert">{{ natState.error }}</t-alert>
              <t-empty v-else-if="natState.supported === false" :description="natState.message || '当前实例暂不支持 NAT 转发'" />
              <t-table v-else row-key="id" :data="natState.list" :columns="natColumns" :pagination="null" size="small" />
            </t-card>
          </section>

          <section v-else-if="activeTab === 'power'" class="console-panel-section">
            <t-card title="电源管理" :bordered="false">
              <div class="detail-grid">
                <InfoCell label="当前状态" :value="instanceStatusText" strong />
                <InfoCell label="状态描述" :value="detail.runtime?.description || '状态正常'" strong />
              </div>
              <t-divider />
              <div class="maintenance-box">
                <h3>维护操作</h3>
                <p>重置实例密码或重装系统会下发到上游控制台，执行后请等待任务完成。</p>
                <t-space>
                  <t-button variant="outline" :disabled="!detail.actions?.password_reset || actionLoading" @click="openPasswordDialog">重置密码</t-button>
                  <t-button variant="outline" :disabled="!detail.actions?.reinstall || actionLoading" @click="openReinstallDialog">重装系统</t-button>
                </t-space>
              </div>
              <t-divider />
              <div class="danger-box">
                <h3>危险操作</h3>
                <p>以下操作可能导致数据丢失，仅在实例无响应时使用。</p>
                <t-space>
                  <t-button theme="danger" variant="outline" :disabled="!detail.actions?.power || actionLoading" @click="handlePowerAction('hard_off')">强制关机</t-button>
                  <t-button theme="danger" variant="outline" :disabled="!detail.actions?.power || actionLoading" @click="handlePowerAction('hard_reboot')">强制重启</t-button>
                </t-space>
              </div>
            </t-card>
          </section>

          <section v-else-if="activeTab === 'logs'" class="console-panel-section">
            <t-card title="操作日志" :bordered="false">
              <template #actions>
                <t-button :loading="logsState.loading" @click="loadLogs">刷新</t-button>
              </template>
              <div class="log-summary">
                <span>共 {{ logsState.summary.total || logsState.total || 0 }} 条</span>
                <span>今日 {{ logsState.summary.today_total || 0 }} 条</span>
                <span v-if="logsState.summary.latest_created_at">最近 {{ logsState.summary.latest_created_at }}</span>
              </div>
              <t-table row-key="id" :data="logsState.list" :columns="logColumns" :pagination="null" size="small" />
              <div v-if="logsState.total > 0" class="console-pagination">
                <t-pagination
                  v-model="logsState.page"
                  v-model:page-size="logsState.page_size"
                  :total="logsState.total"
                  :page-size-options="[10, 20, 50]"
                  show-total
                  @change="loadLogs"
                />
              </div>
            </t-card>
          </section>

          <section v-else-if="activeTab === 'finance'" class="console-panel-section">
            <t-card title="财务日志" :bordered="false">
              <template #actions>
                <t-button :loading="financeState.loading" @click="loadFinanceLogs">刷新</t-button>
              </template>
              <div class="log-summary finance-summary">
                <span>共 {{ financeState.summary.total_count || financeState.total || 0 }} 条</span>
                <span>收入 ¥{{ formatMoney(financeState.summary.total_in || 0) }}</span>
                <span>支出 ¥{{ formatMoney(financeState.summary.total_out || 0) }}</span>
                <span>退款 ¥{{ formatMoney(financeState.summary.refund_in || 0) }}</span>
              </div>
              <t-table
                row-key="id"
                :data="financeState.list"
                :columns="financeColumns"
                :loading="financeState.loading"
                :pagination="null"
                size="small"
              >
                <template #event_type="{ row }">
                  <div class="finance-type-cell">
                    <t-tag size="small" :theme="resolveFinanceTagTheme(row)" variant="light">
                      {{ resolveFinanceBusinessLabel(row) }}
                    </t-tag>
                    <span>{{ resolveFinanceEventLabel(row.event_type) || row.display?.badge || '--' }}</span>
                  </div>
                </template>
                <template #amount="{ row }">
                  <span class="finance-amount" :class="{ 'is-income': Number(row.change_amount || 0) > 0, 'is-outcome': Number(row.change_amount || 0) < 0 }">
                    {{ Number(row.change_amount || 0) > 0 ? '+' : '' }}¥{{ formatMoney(row.change_amount || 0) }}
                  </span>
                </template>
                <template #summary="{ row }">
                  <div class="finance-summary-cell">
                    <strong>{{ resolveFinanceBusinessLabel(row) }}</strong>
                    <span>{{ row.remark || row.display?.subtitle || '--' }}</span>
                  </div>
                </template>
                <template #invoice_no="{ row }">
                  {{ row.invoice?.invoice_no || '--' }}
                </template>
              </t-table>
              <div v-if="financeState.total > 0" class="console-pagination">
                <t-pagination
                  v-model="financeState.page"
                  v-model:page-size="financeState.page_size"
                  :total="financeState.total"
                  :page-size-options="[10, 20, 50]"
                  show-total
                  @change="loadFinanceLogs"
                />
              </div>
            </t-card>
          </section>

          <section v-else-if="activeTab === 'vnc'" class="console-panel-section">
            <t-card title="VNC 控制台" :bordered="false">
              <div class="vnc-panel">
                <div class="vnc-toolbar">
                  <p>{{ vncUrl ? 'VNC 控制台已在当前页面载入。' : '点击连接后将向后端申请一次性 VNC 地址。' }}</p>
                  <t-space>
                    <t-button theme="primary" :loading="actionLoading" @click="handleOpenVnc()">连接 VNC</t-button>
                    <t-button variant="outline" :loading="actionLoading" @click="handleOpenVnc('window')">新窗口打开</t-button>
                  </t-space>
                </div>

                <div class="vnc-frame-shell">
                  <iframe
                    v-if="vncUrl"
                    :key="vncUrl"
                    class="vnc-frame"
                    :src="vncUrl"
                    title="VNC 控制台"
                    allow="clipboard-read; clipboard-write; fullscreen"
                    allowfullscreen
                    referrerpolicy="no-referrer"
                  />
                  <div v-else class="vnc-empty">等待连接</div>
                </div>
              </div>
            </t-card>
          </section>
        </main>
      </div>
    </LoadingState>

    <t-dialog v-model:visible="groupVisible" header="新增安全组" width="min(30rem, calc(100vw - 2rem))" destroy-on-close>
      <div class="dialog-form">
        <label>
          <span>名称</span>
          <t-input v-model="groupForm.name" :maxlength="64" placeholder="请输入安全组名称" />
        </label>
        <label>
          <span>描述</span>
          <t-textarea v-model="groupForm.description" :autosize="{ minRows: 3, maxRows: 5 }" :maxlength="200" placeholder="选填" />
        </label>
      </div>
      <template #footer>
        <t-button variant="outline" @click="groupVisible = false">取消</t-button>
        <t-button theme="primary" :loading="securityState.submitting" @click="submitSecurityGroup">创建</t-button>
      </template>
    </t-dialog>

    <t-dialog v-model:visible="ruleVisible" header="新增安全组规则" width="min(34rem, calc(100vw - 2rem))" destroy-on-close>
      <div class="dialog-form">
        <label>
          <span>方向</span>
          <t-select v-model="ruleForm.direction" placeholder="请选择方向">
            <t-option v-for="item in securityState.directions" :key="item.value" :label="item.label" :value="item.value" />
          </t-select>
        </label>
        <label>
          <span>协议</span>
          <t-select v-model="ruleForm.protocol" placeholder="请选择协议">
            <t-option v-for="item in securityState.protocols" :key="item.value" :label="item.label" :value="item.value" />
          </t-select>
        </label>
        <label>
          <span>端口</span>
          <t-input v-model="ruleForm.port" placeholder="例如 22 或 80-90" />
        </label>
        <label>
          <span>IP 范围</span>
          <t-input v-model="ruleForm.ip" placeholder="例如 0.0.0.0/0" />
        </label>
        <label>
          <span>备注</span>
          <t-textarea v-model="ruleForm.description" :autosize="{ minRows: 3, maxRows: 5 }" :maxlength="200" placeholder="选填" />
        </label>
      </div>
      <template #footer>
        <t-button variant="outline" @click="ruleVisible = false">取消</t-button>
        <t-button theme="primary" :loading="securityState.submitting" @click="submitSecurityRule">创建规则</t-button>
      </template>
    </t-dialog>

    <t-dialog v-model:visible="passwordVisible" header="重置实例密码" width="min(30rem, calc(100vw - 2rem))" destroy-on-close>
      <div class="dialog-form">
        <label>
          <span>新密码</span>
          <div class="password-generate-row">
            <t-input v-model="passwordForm.password" type="password" placeholder="至少 8 位" />
            <t-tooltip content="随机生成强密码">
              <button type="button" class="dice-icon-button" aria-label="随机生成强密码" @click="generateStrongPassword">
                <DiceIcon />
              </button>
            </t-tooltip>
          </div>
        </label>
        <label>
          <span>确认密码</span>
          <t-input v-model="passwordForm.password_confirmation" type="password" placeholder="再次输入新密码" />
        </label>
      </div>
      <template #footer>
        <t-button variant="outline" @click="passwordVisible = false">取消</t-button>
        <t-button theme="primary" :loading="actionLoading" @click="submitResetPassword">提交</t-button>
      </template>
    </t-dialog>

    <t-dialog v-model:visible="reinstallVisible" header="重装系统" width="min(34rem, calc(100vw - 2rem))" destroy-on-close>
      <LoadingState :loading="reinstallState.loading" text="正在加载系统列表" compact>
        <div class="dialog-form">
          <label>
            <span>系统分组</span>
            <t-select v-model="reinstallState.os_group" placeholder="请选择系统分组" @change="handleReinstallGroupChange">
              <t-option v-for="group in reinstallGroupedOptions" :key="group.group_name" :label="group.group_name" :value="group.group_name" />
            </t-select>
          </label>
          <label>
            <span>系统版本</span>
            <t-select v-model="reinstallState.os_id" placeholder="请选择系统版本">
              <t-option v-for="item in currentReinstallOptions" :key="item.os_id" :label="item.name" :value="item.os_id" />
            </t-select>
          </label>
        </div>
      </LoadingState>
      <template #footer>
        <t-button variant="outline" @click="reinstallVisible = false">取消</t-button>
        <t-button theme="primary" :loading="actionLoading" :disabled="!reinstallState.os_id" @click="submitReinstall">提交重装</t-button>
      </template>
    </t-dialog>

    <t-dialog v-model:visible="renewVisible" header="服务续费" width="min(34rem, calc(100vw - 2rem))" destroy-on-close>
      <LoadingState :loading="renewLoading" text="正在加载续费信息" compact>
        <template v-if="renewData">
          <t-radio-group v-model="renewForm.billing_cycle" class="renew-cycle-group" @change="handleRenewCycleChange">
            <t-radio-button v-for="cycle in renewData.cycles || []" :key="cycle.billing_cycle" :value="cycle.billing_cycle">
              {{ cycle.billing_cycle_label }} · ¥{{ formatMoney(cycle.amount) }}
            </t-radio-button>
          </t-radio-group>
          <div v-if="renewCoupons.length" class="renew-coupon-row">
            <span>续费优惠</span>
            <t-select :model-value="renewForm.user_coupon_id || undefined" clearable placeholder="选择优惠券" @change="handleRenewCouponChange">
              <t-option v-for="coupon in renewCoupons" :key="coupon.id" :label="`${coupon.name} · ${coupon.discount_label}`" :value="coupon.id" />
            </t-select>
          </div>
          <div class="renew-total-line">
            <span>本次应付</span>
            <strong>¥{{ renewAmount }}</strong>
          </div>
        </template>
        <t-empty v-else-if="!renewLoading" description="未获取到可续费周期" />
      </LoadingState>
      <template #footer>
        <t-button variant="outline" @click="renewVisible = false">取消</t-button>
        <t-button theme="primary" :loading="renewSubmitting" :disabled="!renewForm.billing_cycle" @click="submitRenew">创建续费账单</t-button>
      </template>
    </t-dialog>

    <t-dialog v-model:visible="nameVisible" header="修改实例名称" width="min(28rem, calc(100vw - 2rem))" destroy-on-close>
      <t-input v-model="nameForm.name" :maxlength="120" placeholder="填写便于识别的实例名称" />
      <template #footer>
        <t-button variant="outline" @click="nameVisible = false">取消</t-button>
        <t-button theme="primary" :loading="nameSubmitting" @click="submitName">保存名称</t-button>
      </template>
    </t-dialog>

    <t-dialog v-model:visible="remarkVisible" header="编辑备注" width="min(28rem, calc(100vw - 2rem))" destroy-on-close>
      <t-textarea v-model="remarkForm.remark" :autosize="{ minRows: 4, maxRows: 6 }" :maxlength="120" placeholder="填写实例备注，便于识别" />
      <template #footer>
        <t-button variant="outline" @click="remarkVisible = false">取消</t-button>
        <t-button theme="primary" :loading="remarkSubmitting" @click="submitRemark">保存备注</t-button>
      </template>
    </t-dialog>
  </section>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue';
import { BillIcon, BrowseIcon, BrowseOffIcon, CatalogIcon, ChartLineDataIcon, CopyIcon, DashboardIcon, DesktopIcon, EditIcon, EllipsisIcon, FileIcon, ForwardIcon, LockOnIcon, PauseCircleFilledIcon, PlayCircleFilledIcon, RefreshIcon, RotateIcon, ServerIcon } from 'tdesign-icons-vue-next';

import { useServiceConsole } from '@/domains/services/useServiceConsole';
import LoadingState from '@shared/user-v3/components/LoadingState.vue';
import { resolveInvoiceStatusLabel } from '@/domains/finance/useInvoices';
import { InfoCell } from './components/InfoCell';
import { DiceIcon } from './components/DiceIcon';
import { useConsoleMonitor, MONITOR_CHART_WIDTH, MONITOR_CHART_HEIGHT, MONITOR_CHART_TOP, MONITOR_CHART_BOTTOM } from './composables/useConsoleMonitor';
import { securityColumns, natColumns, logColumns, financeColumns, resolveFinanceTagTheme, resolveFinanceBusinessLabel, resolveFinanceEventLabel } from './composables/useConsoleTables';

const {
  detail,
  detailLoading,
  statusSyncing,
  actionLoading,
  autoRenewLoading,
  showPassword,
  activeTab,
  vncUrl,
  renewVisible,
  renewLoading,
  renewSubmitting,
  renewData,
  renewForm,
  nameVisible,
  nameSubmitting,
  nameForm,
  remarkVisible,
  remarkSubmitting,
  remarkForm,
  passwordVisible,
  passwordForm,
  reinstallVisible,
  reinstallState,
  monitorState,
  securityState,
  activeSecurityGroup,
  groupVisible,
  groupForm,
  ruleVisible,
  ruleForm,
  natState,
  logsState,
  financeState,
  serviceId,
  availableTabs,
  canManageConsole,
  canSyncStatus,
  serviceRegion,
  serviceOs,
  primaryConnectionLabel,
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
  renewAmount,
  renewCoupons,
  reinstallGroupedOptions,
  currentReinstallOptions,
  findSpecValue,
  resolveServiceStatusLabel,
  resolveTdesignStatusTheme,
  formatMoney,
  router,
  handleSyncStatus,
  handlePowerAction,
  openRenewDialog,
  handleRenewCycleChange,
  handleRenewCouponChange,
  submitRenew,
  openNameDialog,
  submitName,
  openRemarkDialog,
  submitRemark,
  openPasswordDialog,
  generateStrongPassword,
  submitResetPassword,
  openReinstallDialog,
  handleReinstallGroupChange,
  submitReinstall,
  handleToggleAutoRenew,
  copyText,
  loadMonitor,
  loadSecurityGroups,
  selectSecurityGroup,
  openSecurityGroupDialog,
  openSecurityRuleDialog,
  submitSecurityGroup,
  applySecurityGroup,
  deleteSecurityGroup,
  submitSecurityRule,
  deleteSecurityRule,
  loadNatForwardings,
  loadLogs,
  loadFinanceLogs,
  handleOpenVnc,
} = useServiceConsole();

const consoleNavItems = computed(() => {
  const labels: Record<string, string> = {
    overview: '控制台总览',
    monitor: '监控信息',
    security: '安全组',
    nat: '端口转发',
    power: '电源管理',
    logs: '操作日志',
    finance: '财务日志',
    vnc: 'VNC 控制台',
  };
  const icons: Record<string, unknown> = {
    overview: DashboardIcon,
    monitor: ChartLineDataIcon,
    security: LockOnIcon,
    nat: ForwardIcon,
    power: ServerIcon,
    logs: CatalogIcon,
    finance: BillIcon,
    vnc: DesktopIcon,
  };
  return availableTabs.value.map((key) => ({ key, label: labels[key] || key, icon: icons[key] || DashboardIcon }));
});

const moreOptions = computed(() => [
  { content: '重置密码', value: 'password', disabled: !detail.value.actions?.password_reset },
  { content: '重装系统', value: 'reinstall', disabled: !detail.value.actions?.reinstall },
  { content: '强制关机', value: 'hard_off', disabled: !detail.value.actions?.power },
  { content: '强制重启', value: 'hard_reboot', disabled: !detail.value.actions?.power },
]);

const isExpiringSoon = computed(() => {
  if (!detail.value.expires_at) return false;
  const expiresAt = new Date(String(detail.value.expires_at).replace(/-/g, '/')).getTime();
  if (!Number.isFinite(expiresAt)) return false;
  return expiresAt - Date.now() <= 7 * 24 * 60 * 60 * 1000;
});

const isInstanceRunning = computed(() => instanceStatusTheme.value === 'success');

const {
  monitorChartViews,
  activeMonitorPoint,
  handleMonitorPointerMove,
  clearMonitorPointer,
  resolveActiveMonitorPoint,
  resolveMonitorTooltipStyle,
} = useConsoleMonitor(monitorState);

function handleMoreCommand(command: string) {
  if (command === 'hard_off' || command === 'hard_reboot') {
    handlePowerAction(command);
    return;
  }
  if (command === 'password') {
    openPasswordDialog();
    return;
  }
  if (command === 'reinstall') {
    void openReinstallDialog();
  }
}
</script>

<style scoped lang="less">
.service-console {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  // padding 由 Starter 布局层统一提供
}

/* ---- 面包屑 ---- */
.console-breadcrumb {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  color: var(--td-text-color-secondary);
  font-size: 0.8125rem;

  strong {
    color: var(--td-text-color-primary);
    font-weight: 600;
  }
}

.console-breadcrumb__link {
  padding: 0;
  border: 0;
  background: transparent;
  color: var(--td-text-color-secondary);
  cursor: pointer;

  &:hover {
    color: var(--td-brand-color);
  }
}

.console-breadcrumb__sep {
  color: var(--td-text-color-placeholder);
}

/* ---- 顶部卡片 ---- */
.console-header-card {
  background: var(--td-bg-color-container);
  border: 0.0625rem solid var(--td-border-color);
  border-radius: var(--td-radius-medium);
  box-shadow: var(--td-shadow-1);
}

.console-header-card :deep(.t-card__body) {
  display: flex;
  gap: 1.25rem;
  align-items: flex-start;
  justify-content: space-between;
  padding: 1.375rem 1.5rem;
}

.console-header-main {
  min-width: 0;
}

.console-title-line {
  display: flex;
  flex-wrap: wrap;
  gap: 0.625rem;
  align-items: center;

  h1 {
    margin: 0;
    color: var(--td-text-color-primary);
    font-size: 1.125rem;
    font-weight: 700;
    line-height: 1.4;
  }
}

.console-meta-line {
  display: flex;
  flex-wrap: wrap;
  gap: 1.25rem;
  margin-top: 0.75rem;
  color: var(--td-text-color-secondary);
  font-size: 0.875rem;
}

.console-remark-line {
  display: flex;
  align-items: flex-start;
  gap: 0.375rem;
  margin-top: 0.625rem;
  color: var(--td-text-color-secondary);
  font-size: 0.8125rem;
  line-height: 1.7;

  span {
    flex: none;
    color: var(--td-text-color-placeholder);
  }

  strong {
    color: var(--td-text-color-primary);
    font-weight: 500;

    &.is-empty {
      color: var(--td-text-color-placeholder);
      font-weight: 400;
    }
  }
}

.console-header-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.625rem;
  justify-content: flex-end;

  :deep(.t-button + .t-button) {
    margin-left: 0;
  }
}

.console-alert,
.console-inline-alert {
  border-radius: var(--td-radius-medium);
}

.console-workbench {
  display: grid;
  grid-template-columns: 10.5rem minmax(0, 1fr);
  gap: 1rem;
  align-items: start;
}

.console-sidebar {
  position: sticky;
  top: 0;
  align-self: start;
  z-index: 2;
  display: grid;
  gap: 0;
  padding: 0.625rem 0;
  background: var(--td-bg-color-container);
  border: 0.0625rem solid var(--td-border-color);
  border-radius: var(--td-radius-medium);
  box-shadow: var(--td-shadow-1);
  overflow: hidden;
}

.console-sidebar-item {
  position: relative;
  display: flex;
  gap: 0.625rem;
  align-items: center;
  width: 100%;
  padding: 0.875rem 1.125rem;
  color: var(--td-text-color-primary);
  text-align: left;
  cursor: pointer;
  background: transparent;
  border: 0;
  transition: background-color 0.2s ease, color 0.2s ease;

  svg {
    width: 1.125rem;
    height: 1.125rem;
    flex-shrink: 0;
  }

  &:hover {
    color: var(--td-brand-color);
    background: var(--td-brand-color-light);
  }

  &.is-active {
    color: var(--td-brand-color);
    background: var(--td-brand-color-light);
  }
}

.console-content {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  min-width: 0;
}

/* ---- 面板通用 ---- */
.console-panel,
.console-content :deep(.t-card) {
  background: var(--td-bg-color-container);
  border: 0.0625rem solid var(--td-border-color);
  border-radius: var(--td-radius-medium);
  box-shadow: var(--td-shadow-1);
  overflow: hidden;
}

.console-overview-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  grid-auto-flow: dense;
  gap: 0.625rem;
}

.console-panel-wide {
  grid-column: 1 / -1;
}

/* ---- 详情格 ---- */
.detail-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(10rem, 1fr));
  gap: 1rem;
}

.credential-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 0.875rem;
}

.credential-grid .detail-cell,
.credential-grid :deep(.detail-cell) {
  display: grid;
  grid-template-columns: 4rem minmax(0, 1fr);
  gap: 0.75rem;
  align-items: center;
  padding: 0.125rem 0;
}

.detail-cell {
  display: flex;
  gap: 0.75rem;
  align-items: center;
  padding: 0.25rem 0;

  > span {
    min-width: 3.75rem;
    flex-shrink: 0;
    color: var(--td-text-color-placeholder);
    font-size: 0.75rem;
    line-height: 1.6;
  }
}

.detail-cell > :deep(span:first-child) {
  min-width: 3.75rem;
  flex-shrink: 0;
  color: var(--td-text-color-placeholder);
  font-size: 0.75rem;
  line-height: 1.6;
}

.detail-cell-value {
  display: flex;
  gap: 0.5rem;
  align-items: center;
  flex-wrap: wrap;
  min-width: 0;
  flex: 1;
  color: var(--td-text-color-primary);
  font-size: 0.875rem;
  font-weight: 600;

  &.is-warning {
    color: var(--td-warning-color);
  }

  strong {
    color: var(--td-text-color-primary);
    font-weight: 600;
    word-break: break-all;
  }
}

.detail-cell :deep(.detail-cell-value) {
  display: flex;
  flex: 1;
  flex-wrap: nowrap;
  gap: 0.5rem;
  align-items: center;
  min-width: 0;
  color: var(--td-text-color-primary);
  font-size: 0.875rem;
  font-weight: 600;
}

.detail-cell :deep(.detail-cell-value.is-warning) {
  color: var(--td-warning-color);
}

.detail-cell :deep(.detail-cell-value strong) {
  flex: 1;
  min-width: 0;
  overflow: hidden;
  color: var(--td-text-color-primary);
  font-weight: 600;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.detail-cell :deep(.detail-cell-value .copy-link) {
  flex: none;
}

.credential-password-value {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  align-items: center;
  width: 100%;
}

.credential-password-text {
  flex: 0 1 auto;
  min-width: 0;
  max-width: 100%;
  overflow: visible;
  overflow-wrap: anywhere;
  text-overflow: clip;
  white-space: normal;
}

.credential-grid :deep(.detail-cell-value) {
  justify-content: flex-start;
}

.credential-grid :deep(.detail-cell-value strong) {
  flex: 0 1 auto;
}

.copy-link {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 1.5rem;
  height: 1.5rem;
  padding: 0;
  color: var(--td-brand-color);
  cursor: pointer;
  background: transparent;
  border: 0;
  border-radius: var(--td-radius-small);
  line-height: 1;

  &:hover {
    background: var(--td-brand-color-light);
  }
}

:deep(.copy-link) {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 1.5rem;
  height: 1.5rem;
  padding: 0;
  color: var(--td-brand-color);
  cursor: pointer;
  background: transparent;
  border: 0;
  border-radius: var(--td-radius-small);
  line-height: 1;
}

:deep(.copy-link:hover) {
  background: var(--td-brand-color-light);
}

.credential-icon-button {
  flex: none;
}

.credential-icon-button :deep(.t-icon),
:deep(.copy-link .t-icon) {
  font-size: 1.125rem;
  width: 1.125rem;
  height: 1.125rem;
  margin: 0;
  line-height: 1;
}

.console-panel-section {
  min-width: 0;
}

.monitor-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.75rem;
}

.monitor-card {
  display: grid;
  gap: 0.75rem;
  min-height: 14.75rem;
  padding: 1rem;
  background: var(--td-bg-color-component);
  border: 0.0625rem solid var(--td-border-color);
  border-radius: var(--td-radius-medium);
}

.monitor-card-header {
  display: flex;
  gap: 0.75rem;
  align-items: flex-start;
  justify-content: space-between;

  div {
    display: grid;
    gap: 0.25rem;
    min-width: 0;
  }

  span {
    color: var(--td-text-color-secondary);
    font-size: 0.8125rem;
  }

  strong {
    color: var(--td-text-color-primary);
    font-size: 1.375rem;
    font-weight: 700;
    line-height: 1.2;
  }

  small {
    flex: none;
    color: var(--td-text-color-placeholder);
    font-size: 0.75rem;
    line-height: 1.8;
  }
}

.monitor-chart-shell {
  display: grid;
  grid-template-columns: 3.5rem minmax(0, 1fr);
  gap: 0;
  min-height: 10.125rem;
}

.monitor-y-axis {
  position: relative;
  min-width: 0;
  height: 8.75rem;
  padding-right: 0.5rem;

  span {
    position: absolute;
    right: 0.5rem;
    max-width: 3rem;
    overflow: hidden;
    color: var(--td-text-color-placeholder);
    font-size: 0.6875rem;
    line-height: 1;
    text-align: right;
    text-overflow: ellipsis;
    white-space: nowrap;
    transform: translateY(-50%);
  }
}

.monitor-chart-main {
  min-width: 0;
}

.monitor-chart-plot {
  position: relative;
  height: 8.75rem;
  overflow: visible;
  border-radius: var(--td-radius-small);
  background: linear-gradient(to top, var(--td-brand-color-1), transparent);
}

.monitor-chart {
  display: block;
  width: 100%;
  height: 8.75rem;
  overflow: visible;
  cursor: crosshair;
}

.monitor-chart-grid line {
  stroke: var(--td-component-stroke);
  stroke-width: 1;
  vector-effect: non-scaling-stroke;
}

.monitor-chart-axis {
  stroke: var(--td-component-border);
  stroke-width: 1;
  vector-effect: non-scaling-stroke;
}

.monitor-chart-line {
  fill: none;
  stroke-linecap: round;
  stroke-linejoin: round;
  vector-effect: non-scaling-stroke;
}

.monitor-point-halo {
  fill: var(--td-bg-color-container);
}

.monitor-chart-pointer {
  line {
    stroke: var(--td-brand-color);
    stroke-width: 1;
    stroke-dasharray: 4 4;
    opacity: 0.45;
    vector-effect: non-scaling-stroke;
  }
}

.monitor-tooltip {
  position: absolute;
  z-index: 4;
  display: flex;
  min-width: 8.25rem;
  flex-direction: column;
  gap: 0.375rem;
  padding: 0.5rem 0.625rem;
  color: var(--td-text-color-primary);
  pointer-events: none;
  background: var(--td-bg-color-container);
  border: 0.0625rem solid var(--td-component-border);
  border-radius: 0.5rem;
  box-shadow: var(--td-shadow-2);
  transform: translate(-50%, calc(-100% - 0.625rem));

  strong {
    color: var(--td-text-color-primary);
    font-size: 0.75rem;
    font-weight: 600;
    line-height: 1.2;
    white-space: nowrap;
  }
}

.monitor-tooltip-row {
  display: grid;
  grid-template-columns: 0.5rem auto minmax(0, 1fr);
  gap: 0.375rem;
  align-items: center;

  i {
    width: 0.5rem;
    height: 0.5rem;
    border-radius: 62.4375rem;
  }

  b,
  em {
    font-size: 0.75rem;
    line-height: 1.2;
    white-space: nowrap;
  }

  b {
    color: var(--td-text-color-secondary);
    font-style: normal;
    font-weight: 600;
  }

  em {
    overflow: hidden;
    color: var(--td-text-color-primary);
    font-style: normal;
    font-weight: 600;
    text-align: right;
    text-overflow: ellipsis;
  }
}

.monitor-x-axis {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 0.5rem;
  margin-top: 0.375rem;
  color: var(--td-text-color-placeholder);
  font-size: 0.6875rem;
  line-height: 1.3;

  span {
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;

    &:nth-child(2) {
      text-align: center;
    }

    &:last-child {
      text-align: right;
    }
  }
}

.monitor-chart-empty {
  display: flex;
  align-items: center;
  min-height: 7.5rem;
  color: var(--td-text-color-placeholder);
  font-size: 0.8125rem;
}

.monitor-metrics {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 0.5rem;
  padding-top: 0.5rem;
  border-top: 0.0625rem solid var(--td-border-color);

  span {
    min-width: 0;
    color: var(--td-text-color-placeholder);
    font-size: 0.75rem;
  }

  strong {
    display: block;
    margin-top: 0.1875rem;
    overflow: hidden;
    color: var(--td-text-color-primary);
    font-size: 0.8125rem;
    font-weight: 600;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
}

.security-group-list {
  display: grid;
  gap: 0.625rem;
}

.security-group-card {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  gap: 0.75rem;
  align-items: center;
  padding: 0.875rem;
  cursor: pointer;
  background: var(--td-bg-color-component);
  border: 0.0625rem solid var(--td-border-color);
  border-radius: var(--td-radius-medium);
  transition: border-color 0.2s ease, background-color 0.2s ease;

  &:hover,
  &.is-active {
    background: var(--td-brand-color-light);
    border-color: var(--td-brand-color);
  }
}

.security-group-card__main {
  display: grid;
  gap: 0.375rem;
  min-width: 0;

  p {
    margin: 0;
    overflow: hidden;
    color: var(--td-text-color-secondary);
    font-size: 0.8125rem;
    line-height: 1.6;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
}

.security-row__name {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  align-items: center;
  min-width: 0;

  strong {
    min-width: 0;
    overflow: hidden;
    color: var(--td-text-color-primary);
    font-size: 0.875rem;
    font-weight: 600;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
}

.security-group-card__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  justify-content: flex-end;
}

.security-rules-panel {
  display: grid;
  gap: 0.75rem;
  margin-top: 1rem;
  padding-top: 1rem;
  border-top: 0.0625rem solid var(--td-border-color);
}

.security-rules-panel__head {
  display: flex;
  gap: 0.75rem;
  align-items: center;
  justify-content: space-between;

  div {
    display: grid;
    gap: 0.25rem;
  }

  strong {
    color: var(--td-text-color-primary);
    font-size: 0.9375rem;
    font-weight: 700;
  }

  span {
    color: var(--td-text-color-placeholder);
    font-size: 0.75rem;
  }
}

.dialog-form {
  display: grid;
  gap: 0.875rem;

  label {
    display: grid;
    gap: 0.4375rem;
  }

  span {
    color: var(--td-text-color-secondary);
    font-size: 0.8125rem;
  }
}

.password-generate-row {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 2rem;
  gap: 0.5rem;
  align-items: center;
}

.dice-icon-button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2rem;
  height: 2rem;
  padding: 0;
  color: var(--td-brand-color);
  cursor: pointer;
  background: var(--td-bg-color-container);
  border: 0.0625rem solid var(--td-border-color);
  border-radius: var(--td-radius-small);
  transition: border-color 0.2s ease, background-color 0.2s ease;

  &:hover {
    background: var(--td-brand-color-light);
    border-color: var(--td-brand-color);
  }
}

.dice-icon {
  width: 1.125rem;
  height: 1.125rem;
}

.log-summary {
  display: flex;
  flex-wrap: wrap;
  gap: 1.25rem;
  margin-bottom: 1rem;
  color: var(--td-text-color-secondary);
  font-size: 0.875rem;
}

.finance-summary span {
  white-space: nowrap;
}

.finance-type-cell {
  display: flex;
  gap: 0.5rem;
  align-items: center;
  white-space: nowrap;
}

.finance-amount {
  font-weight: 700;
  color: var(--td-text-color-primary);

  &.is-income {
    color: var(--td-success-color);
  }

  &.is-outcome {
    color: var(--td-error-color);
  }
}

.finance-summary-cell {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  min-width: 0;

  strong {
    color: var(--td-text-color-primary);
    font-weight: 600;
  }

  span {
    color: var(--td-text-color-secondary);
    font-size: 0.75rem;
    line-height: 1.5;
    word-break: break-word;
  }
}

.maintenance-box,
.danger-box {
  display: grid;
  gap: 0.5rem;
  padding: 1.125rem;
  background: var(--td-bg-color-component);
  border: 0.0625rem solid var(--td-border-color);
  border-radius: var(--td-radius-medium);

  h3 {
    margin: 0;
    color: var(--td-text-color-primary);
    font-size: 0.9375rem;
    font-weight: 700;
  }

  p {
    margin: 0;
    color: var(--td-text-color-secondary);
    font-size: 0.8125rem;
    line-height: 1.8;
  }
}

.vnc-panel {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  padding: 1.125rem;
  background: var(--td-bg-color-component);
  border: 0.0625rem solid var(--td-border-color);
  border-radius: var(--td-radius-medium);
}

.vnc-toolbar {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  gap: 1rem;
  align-items: center;

  p {
    margin: 0;
    color: var(--td-text-color-secondary);
  }
}

.vnc-frame-shell {
  position: relative;
  overflow: hidden;
  min-height: 35rem;
  background: var(--td-bg-color-page);
  border: 0.0625rem solid var(--td-border-color);
  border-radius: var(--td-radius-default);
}

.vnc-frame {
  display: block;
  width: 100%;
  height: 35rem;
  border: 0;
  background: var(--td-bg-color-container);
}

.vnc-empty {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 35rem;
  color: var(--td-text-color-placeholder);
  font-size: 0.875rem;
}

@media (max-width: @screen-sm-rem) {
  .vnc-frame-shell,
  .vnc-frame,
  .vnc-empty {
    min-height: 26.25rem;
  }

  .vnc-frame {
    height: 26.25rem;
  }
}

.console-pagination {
  display: flex;
  justify-content: flex-end;
  margin-top: 1rem;
  overflow-x: auto;
}

/* ---- 续费卡片按钮 ---- */
.renew-cycle-group {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.75rem;

  :deep(.t-radio-button) {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    align-items: flex-start;
    padding: 1rem;
    height: auto;
    min-height: 5rem;
    border: 0.0625rem solid var(--td-border-color) !important;
    border-radius: 0.5rem !important;
    background: var(--td-bg-color-component);
    cursor: pointer;
    transition: border-color 0.2s ease;

    &:hover {
      border-color: var(--td-brand-color) !important;
    }
  }

  :deep(.t-is-checked) {
    border-color: var(--td-brand-color) !important;
    background: var(--td-brand-color-light) !important;
    color: var(--td-brand-color);
  }
}

.renew-coupon-row,
.renew-total-line {
  display: flex;
  gap: 0.75rem;
  align-items: center;
  justify-content: space-between;
  margin-top: 1rem;

  span {
    color: var(--td-text-color-secondary);
    font-size: 0.8125rem;
  }
}

.renew-total-line {
  padding-top: 1rem;
  border-top: 0.0625rem solid var(--td-border-color);

  strong {
    color: var(--td-error-color);
    font-size: 1.5rem;
    font-weight: 700;
  }
}

@media (max-width: @screen-lg-rem) {
  .console-overview-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .monitor-grid {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 56.25rem) {
  .console-header-card :deep(.t-card__body) {
    flex-direction: column;
    gap: 0.875rem;
    padding: 1.125rem 1rem;
  }

  .console-workbench {
    display: flex;
    flex-direction: column;
    align-items: stretch;
  }

  .console-sidebar {
    position: static;
    display: grid;
    width: 100%;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.375rem;
    padding: 0.5rem;
    overflow: visible;
  }

  .console-sidebar-item {
    flex-direction: column;
    justify-content: center;
    gap: 0.25rem;
    min-height: 3.25rem;
    padding: 0.5rem 0.375rem;
    border: 0.0625rem solid var(--td-border-color);
    text-align: center;
    font-size: 0.6875rem;
    line-height: 1.3;

    svg {
      width: 1rem;
      height: 1rem;
    }

    &.is-active {
      border-color: var(--td-brand-color);
      background: var(--td-brand-color-light);
    }

    &:not(.is-active):hover {
      background: var(--td-brand-color-light);
      border-color: var(--td-brand-color-hover);
    }
  }

  .console-header-actions {
    width: 100%;
    justify-content: flex-start;
    gap: 0.5rem;
  }

  .console-overview-grid,
  .detail-grid,
  .credential-grid,
  .monitor-grid {
    grid-template-columns: 1fr;
  }
}

@media (max-width: @screen-sm-rem) {
  .console-meta-line {
    flex-direction: column;
    gap: 0.5rem;
  }

  .console-header-actions {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.375rem;
  }

  .console-header-actions :deep(.t-button) {
    width: 100%;
    min-width: 0;
    margin-left: 0 !important;
    justify-content: center;
  }

  .renew-cycle-group {
    grid-template-columns: 1fr;
  }

  .renew-coupon-row,
  .renew-total-line {
    align-items: flex-start;
    flex-direction: column;
  }
}
</style>
