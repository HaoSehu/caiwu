<template>
  <article class="service-row-card">
    <div class="service-row-head">
      <div class="service-system-icon" :class="{ 'is-provisioning': isProvisioningService(item) }">
        <span v-if="isProvisioningService(item)" class="service-system-loader" aria-hidden="true">
          <span class="service-system-loader__ring"></span>
          <span class="service-system-loader__core"></span>
        </span>
        <img
          v-else-if="resolveServiceOsIcon(item)"
          :src="resolveServiceOsIcon(item)"
          :alt="resolveServiceOsText(item) || resolveServiceName(item)"
          class="service-system-icon__image"
        />
        <span v-else class="service-system-icon__fallback">{{ resolveServiceMark(item) }}</span>
      </div>

      <div class="service-row-body">
        <div class="service-row-topline">
          <div class="service-row-titleblock">
            <div class="service-title-row">
              <el-tooltip :content="resolveServiceName(item)" placement="top-start" :show-after="180" effect="dark">
                <button type="button" class="service-name-button" @click="$emit('open-detail', item.id)">
                  {{ resolveServiceName(item) }}
                </button>
              </el-tooltip>
              <span class="service-row-id">ID {{ item.id }}</span>
            </div>

            <div class="service-remark-line">
              <span class="service-remark-text" :class="{ empty: !item.remark }" :title="item.remark || '添加备注'">
                {{ item.remark || '添加备注' }}
              </span>
              <button
                type="button"
                class="service-remark-trigger"
                :aria-label="item.remark ? '编辑备注' : '添加备注'"
                @click="$emit('open-remark', item)"
              >
                <el-icon><EditPen /></el-icon>
              </button>
            </div>
          </div>

          <div class="service-row-actions">
            <button type="button" class="service-action-link" @click="$emit('open-detail', item.id)">控制台</button>
            <el-dropdown trigger="click" @command="(command) => $emit('action', command, item)">
              <button type="button" class="service-action-button service-more-button">更多</button>
              <template #dropdown>
                <el-dropdown-menu>
                  <el-dropdown-item command="renew">立即续费</el-dropdown-item>
                  <el-dropdown-item v-if="item.invoice?.id || item.order?.invoice_id" command="invoice">账单详情</el-dropdown-item>
                </el-dropdown-menu>
              </template>
            </el-dropdown>
          </div>
        </div>

        <div class="service-spec-line">
          <span>CPU {{ findListSpecValue(item, ['CPU', '核心']) }}</span>
          <span>内存 {{ findListSpecValue(item, ['内存', 'RAM']) }}</span>
          <span>带宽 {{ resolveListBandwidthText(item) }}</span>
        </div>

        <div class="service-expire-line" :class="{ warning: isExpiringSoon(item.expires_at) }">
          到期时间：{{ item.expires_at || '长期有效' }}
        </div>

        <div class="service-row-divider"></div>

        <div class="service-row-foot">
          <div class="service-status-line" :class="[ `is-${item.status_tone || 'info'}`, { 'is-provisioning': isProvisioningService(item) } ]">
            <i class="service-status-dot"></i>
            <span class="service-status-text" :class="{ 'is-provisioning': isProvisioningService(item) }">
              {{ resolveRuntimeStatusLabel(item) }}
            </span>
          </div>

          <div class="service-ip-line">
            <span class="service-ip-label">公网 IP</span>
            <button
              type="button"
              class="service-ip-button"
              :class="{ disabled: !(item.upstream?.dedicated_ip && item.upstream.dedicated_ip !== '--') }"
              :disabled="!(item.upstream?.dedicated_ip && item.upstream.dedicated_ip !== '--')"
              :title="item.upstream?.dedicated_ip ? `点击复制 ${item.upstream.dedicated_ip}` : '暂无公网 IP'"
              @click="$emit('copy-ip', item.upstream?.dedicated_ip || '')"
            >
              {{ item.upstream?.dedicated_ip || '--' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </article>
</template>

<script setup lang="ts">
import { EditPen } from '@element-plus/icons-vue'
import { SERVICE_STATUS } from '@shared/statusConfig'
import {
  resolveServiceName,
  resolveServiceOsText,
  resolveServiceOsIcon,
  resolveServiceMark,
  findListSpecValue,
  resolveListBandwidthText,
  resolveRuntimeStatusLabel,
  isExpiringSoon,
} from '@/domains/services/useServiceCenter'

defineProps({
  item: { type: Object, required: true },
})

defineEmits(['open-detail', 'open-remark', 'action', 'copy-ip'])

function isProvisioningService(item: any) {
  if (Number(item?.status) === SERVICE_STATUS.PENDING) {
    return true
  }

  return resolveRuntimeStatusLabel(item) === '开通中'
}
</script>

<style lang="scss" scoped>
.service-row-card {
  width: 100%;
  aspect-ratio: 389 / 187;
  padding: 16px 18px 14px;
  border: 1px solid rgba(225, 231, 241, 0.9);
  border-radius: 16px;
  background:
    radial-gradient(circle at top left, rgba(76, 132, 255, 0.05), transparent 24%),
    linear-gradient(180deg, #ffffff 0%, #fbfcff 100%);
  box-shadow: 0 10px 24px rgba(20, 47, 88, 0.05);
  overflow: hidden;
  transition: border-color $motion-fast ease, box-shadow $motion-fast ease, transform $motion-fast ease;

  &:hover {
    border-color: rgba(76, 132, 255, 0.24);
    box-shadow: 0 16px 32px rgba(20, 47, 88, 0.08);
    transform: translateY(-2px);
  }
}

.service-row-head {
  display: flex;
  align-items: stretch;
  gap: 12px;
  height: 100%;
  padding: 0;
}

.service-system-icon {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 44px;
  min-width: 44px;
  height: 44px;
}

.service-system-icon__image {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.service-system-icon__fallback {
  color: #3978ff;
  font-size: 18px;
  font-weight: 700;
}

.service-system-loader {
  position: relative;
  width: 28px;
  height: 28px;
}

.service-system-loader__ring,
.service-system-loader__core {
  position: absolute;
  inset: 0;
  border-radius: 50%;
}

.service-system-loader__ring {
  border: 3px solid rgba(235, 19, 92, 0.1);
  border-top-color: #eb135c;
  animation: service-loader-spin 1.2s linear infinite;
}

.service-system-loader__core {
  inset: 7px;
  background: radial-gradient(circle, rgba(235, 19, 92, 0.18), rgba(235, 19, 92, 0.02));
}

.service-row-body {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
}

.service-row-topline {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
}

.service-row-titleblock {
  min-width: 0;
  flex: 1;
}

.service-title-row {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.service-name-button,
.service-action-link,
.service-remark-trigger,
.service-action-button,
.service-ip-button {
  border: none;
  padding: 0;
  background: transparent;
}

.service-name-button {
  color: #19263d;
  font-size: 14px;
  font-weight: 700;
  line-height: 1.3;
  text-align: left;
  cursor: pointer;
  display: -webkit-box;
  overflow: hidden;
  -webkit-line-clamp: 1;
  -webkit-box-orient: vertical;
}

.service-row-id {
  display: inline-flex;
  align-items: center;
  min-height: 20px;
  padding: 0 7px;
  border-radius: 999px;
  background: #f4f7fb;
  color: #91a0b6;
  font-size: 11px;
  font-weight: 600;
}

.service-remark-line {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-top: 4px;
}

.service-remark-text {
  color: #7d8aa0;
  font-size: 11px;
  line-height: 1.4;
  display: -webkit-box;
  overflow: hidden;
  -webkit-line-clamp: 1;
  -webkit-box-orient: vertical;

  &.empty {
    color: #a1adbe;
  }
}

.service-remark-trigger {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 18px;
  height: 18px;
  border-radius: 8px;
  color: #7c8aa1;
  cursor: pointer;
  transition: background-color $motion-fast ease, color $motion-fast ease;

  &:hover {
    background: rgba(76, 132, 255, 0.08);
    color: #3978ff;
  }
}

.service-row-actions {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-shrink: 0;
}

.service-action-link,
.service-action-button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 52px;
  height: 30px;
  padding: 0 11px;
  border-radius: 10px;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
}

.service-action-link {
  color: #256dff;
}

.service-more-button {
  border: 1px solid #dbe3f0;
  background: #fff;
  color: #4b5a74;
}

.service-spec-line {
  display: flex;
  flex-wrap: wrap;
  gap: 6px 14px;
  margin-top: 14px;

  span {
    display: inline-block;
    color: #5d6b83;
    font-size: 12px;
    font-weight: 600;
  }
}

.service-expire-line {
  margin-top: 8px;
  color: #6f7d93;
  font-size: 12px;

  &.warning {
    color: #ff8a00;
    font-weight: 600;
  }
}

.service-row-divider {
  margin-top: auto;
  border-top: 1px solid #edf1f7;
}

.service-row-foot {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 14px;
  padding-top: 10px;
  flex-wrap: wrap;
}

.service-status-line {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  min-height: 24px;
  font-size: 11px;
  font-weight: 700;

  &.is-success {
    color: #22945f;
  }

  &.is-warning {
    color: #ff8a00;
  }

  &.is-danger {
    color: #d71457;
  }

  &.is-info {
    color: #3978ff;
  }
}

.service-status-dot {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: currentColor;
}

.service-ip-line {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.service-ip-label {
  color: #8e9bb0;
  font-size: 11px;
}

.service-ip-button {
  display: inline-flex;
  align-items: center;
  min-height: 28px;
  color: #21314c;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;

  &.disabled {
    color: #97a3b6;
    cursor: default;
  }
}

@keyframes service-loader-spin {
  to {
    transform: rotate(360deg);
  }
}

@media (max-width: 767px) {
  .service-row-card {
    aspect-ratio: 389 / 187;
    padding: 12px 14px;
  }

  .service-system-icon {
    width: 40px;
    min-width: 40px;
    height: 40px;
    border-radius: 12px;
  }

  .service-row-head {
    gap: 10px;
  }

  .service-row-topline {
    gap: 8px;
  }

  .service-name-button {
    font-size: 13px;
  }

  .service-action-link,
  .service-action-button {
    min-width: 48px;
    height: 28px;
    padding: 0 9px;
    font-size: 11px;
  }

  .service-spec-line {
    gap: 4px 10px;
    margin-top: 12px;

    span {
      font-size: 11px;
    }
  }

  .service-expire-line,
  .service-ip-button {
    font-size: 11px;
  }

  .service-row-foot {
    gap: 10px;
    padding-top: 8px;
  }

  .service-status-line,
  .service-ip-label {
    font-size: 10px;
  }
}
</style>
