<template>
  <div class="console-overview-grid">
  <!-- 基本信息 -->
  <section class="console-panel overview-panel">
    <div class="console-panel__header console-panel__header--compact"><h3>基本信息</h3></div>
    <div class="detail-grid detail-grid--two">
      <div class="detail-cell">
        <span class="detail-cell__label">实例名称</span>
        <div class="detail-cell__value"><strong>{{ detail.name || `服务 #${serviceId}` }}</strong></div>
      </div>
      <div class="detail-cell">
        <span class="detail-cell__label">实例 ID</span>
        <div class="detail-cell__value">
          <strong>{{ detail.id || '--' }}</strong>
          <el-button v-if="detail.id" text type="primary" @click="emit('copy', String(detail.id))">复制</el-button>
        </div>
      </div>
      <div class="detail-cell">
        <span class="detail-cell__label">实例规格</span>
        <div class="detail-cell__value"><strong>{{ detail.combined_display_name || detail.product_display_name || detail.product?.display_name || detail.product?.type_label || '--' }}</strong></div>
      </div>
      <div class="detail-cell">
        <span class="detail-cell__label">操作系统</span>
        <div class="detail-cell__value"><strong>{{ serviceOs }}</strong></div>
      </div>
      <div class="detail-cell">
        <span class="detail-cell__label">创建时间</span>
        <div class="detail-cell__value"><strong>{{ detail.created_at || '--' }}</strong></div>
      </div>
      <div class="detail-cell">
        <span class="detail-cell__label">实例状态</span>
        <div class="detail-cell__value">
          <el-tag :type="resolveToneTagType(detail.status_tone)" effect="plain">
            {{ resolveServiceStatusLabel(detail.status) }}
          </el-tag>
        </div>
      </div>
    </div>
  </section>

  <!-- 配置信息 -->
  <section class="console-panel overview-panel overview-panel--wide overview-panel--config">
    <div class="console-panel__header console-panel__header--compact"><h3>配置信息</h3></div>
    <div class="detail-grid detail-grid--two">
      <div class="detail-cell detail-cell--traffic">
        <span class="detail-cell__label">流量</span>
        <div v-if="detail.traffic?.limited" class="detail-cell__value detail-cell__value--traffic">
          <div class="traffic-progress-cell">
            <div class="traffic-progress-cell__main">
              <el-progress
                :percentage="Number(detail.traffic?.usage_percent || 0)"
                :stroke-width="10"
              />
              <div class="traffic-progress-cell__summary">
                <strong>{{ detail.traffic?.usage_label || '0G' }}</strong>
                <span>/ {{ detail.traffic?.limit_label || '不限' }}</span>
                <small>剩余 {{ detail.traffic?.remaining_label || '不限' }}</small>
              </div>
            </div>
            <el-button
              v-if="detail.actions?.traffic_package"
              type="primary"
              plain
              size="small"
              @click="emit('open-traffic-package')"
            >
              {{ detail.traffic?.button_text || '购买流量包' }}
            </el-button>
          </div>
        </div>
        <div v-else class="detail-cell__value">
          <strong>{{ detail.traffic ? '不限' : findSpecValue(['流量', '月流量', '总流量']) }}</strong>
        </div>
      </div>
      <div class="detail-cell"><span class="detail-cell__label">区域</span><div class="detail-cell__value"><strong>{{ serviceRegion }}</strong></div></div>
      <div class="detail-cell"><span class="detail-cell__label">CPU</span><div class="detail-cell__value"><strong>{{ findSpecValue(['CPU', '核心']) }}</strong></div></div>
      <div class="detail-cell"><span class="detail-cell__label">内存</span><div class="detail-cell__value"><strong>{{ findSpecValue(['内存', 'RAM']) }}</strong></div></div>
      <div class="detail-cell"><span class="detail-cell__label">系统盘</span><div class="detail-cell__value"><strong>{{ findSpecValue(['系统盘']) }}</strong></div></div>
      <div class="detail-cell"><span class="detail-cell__label">带宽</span><div class="detail-cell__value"><strong>{{ bandwidthText }}</strong></div></div>
      <div class="detail-cell"><span class="detail-cell__label">IP数量</span><div class="detail-cell__value"><strong>{{ serviceIpCount }}</strong></div></div>
      <div class="detail-cell detail-cell--full"><span class="detail-cell__label">数据盘</span><div class="detail-cell__value"><strong>{{ findSpecValue(['数据盘']) }}</strong></div></div>
    </div>
  </section>

  <!-- 网络信息 -->
  <section class="console-panel overview-panel">
    <div class="console-panel__header console-panel__header--compact"><h3>网络信息</h3></div>
    <div class="detail-grid detail-grid--two">
      <div class="detail-cell">
        <span class="detail-cell__label">{{ primaryConnectionLabel }}</span>
        <div class="detail-cell__value">
          <strong>{{ primaryConnectionText }}</strong>
          <el-button
            v-if="primaryConnectionText && primaryConnectionText !== '--'"
            text
            type="primary"
            @click="emit('copy', primaryConnectionText)"
          >
            复制
          </el-button>
        </div>
      </div>
      <div class="detail-cell">
        <span class="detail-cell__label">内网 IP</span>
        <div class="detail-cell__value">
          <strong>{{ detail.connection?.internal_ip || '--' }}</strong>
          <el-button v-if="detail.connection?.internal_ip" text type="primary" @click="emit('copy', detail.connection.internal_ip)">复制</el-button>
        </div>
      </div>
    </div>
  </section>

  <!-- 登录凭据 -->
  <section class="console-panel overview-panel">
    <div class="console-panel__header console-panel__header--compact"><h3>登录凭据</h3></div>
    <div class="credential-grid">
      <div class="credential-item">
        <span class="credential-item__label">用户名</span>
        <div class="credential-item__content">
          <strong class="credential-item__value">{{ detail.connection?.username || '--' }}</strong>
          <div class="credential-item__actions">
            <el-button v-if="detail.connection?.username" text type="primary" @click="emit('copy', detail.connection.username)">复制</el-button>
          </div>
        </div>
      </div>
      <div class="credential-item">
        <span class="credential-item__label">密码</span>
        <div class="credential-item__content">
          <strong class="credential-item__value">{{ resolvedPassword }}</strong>
          <div class="credential-item__actions">
            <el-button v-if="detail.connection?.has_password" text type="primary" @click="emit('toggle-password')">
              {{ showPassword ? '隐藏' : '显示' }}
            </el-button>
            <el-button v-if="detail.connection?.has_password" text type="primary" @click="emit('copy', detail.connection?.password || '')">复制</el-button>
            <el-button v-if="detail.actions?.password_reset" text type="primary" @click="emit('open-password-dialog')">重置密码</el-button>
          </div>
        </div>
      </div>
      <div class="credential-item">
        <span class="credential-item__label">{{ connectionPortLabel }}</span>
        <div class="credential-item__content">
          <strong class="credential-item__value">{{ connectionPortText }}</strong>
        </div>
      </div>
      <div class="credential-item">
        <span class="credential-item__label">{{ connectionEndpointLabel }}</span>
        <div class="credential-item__content">
          <strong class="credential-item__value">{{ connectionEndpointText }}</strong>
          <div class="credential-item__actions">
            <el-button
              v-if="connectionEndpointText && connectionEndpointText !== '--'"
              text
              type="primary"
              @click="emit('copy', connectionEndpointText)"
            >
              复制
            </el-button>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- 付费信息 -->
  <section class="console-panel overview-panel overview-panel--wide overview-panel--billing">
    <div class="console-panel__header console-panel__header--compact">
      <h3>付费信息</h3>
      <el-button link type="primary" @click="emit('open-renew')">续费管理</el-button>
    </div>
    <div class="detail-grid detail-grid--two">
      <div class="detail-cell">
        <span class="detail-cell__label">计费方式</span>
        <div class="detail-cell__value"><strong>{{ detail.billing_cycle_label || '--' }}</strong></div>
      </div>
      <div class="detail-cell">
        <span class="detail-cell__label">续费价格</span>
        <div class="detail-cell__value"><strong>{{ renewPriceText }}</strong></div>
      </div>
      <div class="detail-cell">
        <span class="detail-cell__label">到期时间</span>
        <div class="detail-cell__value"><strong class="text-warning">{{ detail.expires_at || '长期有效' }}</strong></div>
      </div>
      <div class="detail-cell">
        <span class="detail-cell__label">自动续费</span>
        <div class="detail-cell__value">
          <el-tag :type="Number(detail.auto_renew) === 1 ? 'success' : 'info'" effect="plain">{{ autoRenewLabel }}</el-tag>
          <el-button text type="primary" :loading="autoRenewLoading" @click="emit('toggle-auto-renew', Number(detail.auto_renew) !== 1)">
            {{ Number(detail.auto_renew) === 1 ? '关闭' : '开启' }}
          </el-button>
        </div>
      </div>
      <div class="detail-cell">
        <span class="detail-cell__label">账单号</span>
        <div class="detail-cell__value">
          <strong>{{ detail.invoice?.invoice_no || detail.order?.invoice_no || detail.order?.order_no || '--' }}</strong>
          <el-button v-if="detail.invoice?.invoice_no || detail.order?.invoice_no" text type="primary" @click="emit('copy', detail.invoice?.invoice_no || detail.order?.invoice_no)">复制</el-button>
        </div>
      </div>
      <div class="detail-cell">
        <span class="detail-cell__label">账单状态</span>
        <div class="detail-cell__value">
          <el-tag effect="plain" :type="detail.order?.status_label === '已支付' ? 'success' : 'info'">
            {{ detail.order?.status_label || '--' }}
          </el-tag>
        </div>
      </div>
    </div>
  </section>
  </div>
</template>

<script setup>
import { resolveToneTagType } from '@/views/client/ServiceConsole/composables/useServiceConsole.js'
import { resolveServiceStatusLabel } from '@/domains/services/useServiceCenter'

defineProps({
  detail: { type: Object, required: true },
  serviceId: { type: Number, required: true },
  serviceOs: { type: String, default: '--' },
  serviceRegion: { type: String, default: '--' },
  primaryConnectionLabel: { type: String, default: '公网 IP' },
  primaryConnectionText: { type: String, default: '--' },
  connectionPortLabel: { type: String, default: '端口' },
  connectionEndpointLabel: { type: String, default: '主机名' },
  connectionEndpointText: { type: String, default: '--' },
  serviceIpCount: { type: String, default: '--' },
  bandwidthText: { type: String, default: '--' },
  connectionPortText: { type: String, default: '--' },
  renewPriceText: { type: String, default: '--' },
  autoRenewLabel: { type: String, default: '' },
  autoRenewLoading: { type: Boolean, default: false },
  resolvedPassword: { type: String, default: '--' },
  showPassword: { type: Boolean, default: false },
  findSpecValue: { type: Function, required: true },
})

const emit = defineEmits(['copy', 'toggle-password', 'open-password-dialog', 'open-renew', 'open-traffic-package', 'toggle-auto-renew'])
</script>
