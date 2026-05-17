<template>
  <div class="service-table-card">
    <el-table
      :data="items"
      row-key="id"
      table-layout="auto"
      class="service-list-table"
      empty-text="暂无服务实例"
    >
      <el-table-column label="服务信息" min-width="280">
        <template #default="{ row }">
          <div class="service-table-service">
            <div class="service-system-icon">
              <img
                v-if="resolveServiceOsIcon(row)"
                :src="resolveServiceOsIcon(row)"
                :alt="resolveServiceOsText(row) || resolveServiceName(row)"
                class="service-system-icon__image"
              />
              <span v-else class="service-system-icon__fallback">{{ resolveServiceMark(row) }}</span>
            </div>

            <div class="service-table-copy">
              <div class="service-table-title-row">
                <button type="button" class="service-name-button" @click="$emit('open-detail', row.id)">
                  {{ resolveServiceName(row) }}
                </button>
                <span class="service-row-id">ID {{ row.id }}</span>
              </div>

              <div class="service-table-meta">
                <span class="service-table-meta__text">{{ row.product?.group_name || row.product?.type_label || '云服务' }}</span>
                <span class="service-table-meta__divider"></span>
                <span class="service-table-meta__text" :class="{ empty: !row.remark }">
                  {{ row.remark || '未添加备注' }}
                </span>
                <button
                  type="button"
                  class="service-remark-trigger"
                  :aria-label="row.remark ? '编辑备注' : '添加备注'"
                  @click="$emit('open-remark', row)"
                >
                  <el-icon><EditPen /></el-icon>
                </button>
              </div>
            </div>
          </div>
        </template>
      </el-table-column>

      <el-table-column label="配置摘要" min-width="220">
        <template #default="{ row }">
          <div class="service-table-specs">
            <span>CPU {{ findListSpecValue(row, ['CPU', '核心']) }}</span>
            <span>内存 {{ findListSpecValue(row, ['内存', 'RAM']) }}</span>
            <span>带宽 {{ resolveListBandwidthText(row) }}</span>
          </div>
        </template>
      </el-table-column>

      <el-table-column label="到期时间" min-width="150">
        <template #default="{ row }">
          <span class="service-expire-line" :class="{ warning: isExpiringSoon(row.expires_at) }">
            {{ row.expires_at || '长期有效' }}
          </span>
        </template>
      </el-table-column>

      <el-table-column label="状态" min-width="110">
        <template #default="{ row }">
          <div class="service-status-line" :class="`is-${row.status_tone || 'info'}`">
            <i class="service-status-dot"></i>
            <span>{{ resolveRuntimeStatusLabel(row) }}</span>
          </div>
        </template>
      </el-table-column>

      <el-table-column label="公网 IP" min-width="160">
        <template #default="{ row }">
          <button
            type="button"
            class="service-ip-button"
            :class="{ disabled: !(row.upstream?.dedicated_ip && row.upstream.dedicated_ip !== '--') }"
            :disabled="!(row.upstream?.dedicated_ip && row.upstream.dedicated_ip !== '--')"
            :title="row.upstream?.dedicated_ip ? `点击复制 ${row.upstream.dedicated_ip}` : '暂无公网 IP'"
            @click="$emit('copy-ip', row.upstream?.dedicated_ip || '')"
          >
            {{ row.upstream?.dedicated_ip || '--' }}
          </button>
        </template>
      </el-table-column>

      <el-table-column label="操作" width="190" fixed="right" align="right">
        <template #default="{ row }">
          <div class="service-table-actions">
            <button type="button" class="service-action-button service-login-button" @click="$emit('open-detail', row.id)">
              控制台
            </button>
            <el-dropdown trigger="click" @command="(command) => $emit('action', command, row)">
              <button type="button" class="service-action-button service-more-button">更多</button>
              <template #dropdown>
                <el-dropdown-menu>
                  <el-dropdown-item command="renew">立即续费</el-dropdown-item>
                  <el-dropdown-item v-if="row.invoice?.id || row.order?.invoice_id" command="invoice">账单详情</el-dropdown-item>
                </el-dropdown-menu>
              </template>
            </el-dropdown>
          </div>
        </template>
      </el-table-column>
    </el-table>
  </div>
</template>

<script setup lang="ts">
import { EditPen } from '@element-plus/icons-vue'
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
  items: { type: Array, default: () => [] },
})

defineEmits(['open-detail', 'open-remark', 'action', 'copy-ip'])
</script>

<style lang="scss" scoped>
.service-table-card {
  border: 1px solid rgba(225, 231, 241, 0.9);
  border-radius: 18px;
  background: #fff;
  box-shadow: 0 12px 28px rgba(20, 47, 88, 0.05);
  overflow: hidden;
}

.service-list-table {
  :deep(.el-table__inner-wrapper::before) {
    display: none;
  }

  :deep(.el-table__header-wrapper th) {
    background: #f8faff;
    color: #74839a;
    font-size: 12px;
    font-weight: 700;
  }

  :deep(.el-table__row td) {
    padding-top: 18px;
    padding-bottom: 18px;
  }
}

.service-table-service {
  display: flex;
  align-items: flex-start;
  gap: 14px;
}

.service-system-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 54px;
  min-width: 54px;
  height: 54px;
  border-radius: 14px;
  background: linear-gradient(145deg, #f5f8ff, #eaf0fb);
  overflow: hidden;
}

.service-system-icon__image {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.service-system-icon__fallback {
  color: #3978ff;
  font-size: 22px;
  font-weight: 700;
}

.service-table-copy {
  min-width: 0;
  flex: 1;
}

.service-table-title-row {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}

.service-name-button,
.service-remark-trigger,
.service-ip-button,
.service-action-button {
  border: none;
  padding: 0;
  background: transparent;
}

.service-name-button {
  color: #19263d;
  font-size: 15px;
  font-weight: 700;
  cursor: pointer;
}

.service-row-id {
  display: inline-flex;
  align-items: center;
  min-height: 22px;
  padding: 0 8px;
  border-radius: 999px;
  background: #f4f7fb;
  color: #91a0b6;
  font-size: 12px;
  font-weight: 600;
}

.service-table-meta {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
  margin-top: 8px;
}

.service-table-meta__text {
  color: #7d8aa0;
  font-size: 12px;

  &.empty {
    color: #a1adbe;
  }
}

.service-table-meta__divider {
  width: 4px;
  height: 4px;
  border-radius: 50%;
  background: #ced7e4;
}

.service-remark-trigger {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 22px;
  height: 22px;
  border-radius: 8px;
  color: #7c8aa1;
  cursor: pointer;
}

.service-table-specs {
  display: grid;
  gap: 8px;

  span {
    color: #53617a;
    font-size: 12px;
    line-height: 1.5;
  }
}

.service-expire-line {
  color: #6f7d93;
  font-size: 12px;

  &.warning {
    color: #ff8a00;
    font-weight: 600;
  }
}

.service-status-line {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  min-height: 30px;
  padding: 0 12px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 700;

  &.is-success {
    background: rgba(56, 189, 121, 0.12);
    color: #22945f;
  }

  &.is-warning {
    background: rgba(255, 138, 0, 0.12);
    color: #ff8a00;
  }

  &.is-danger {
    background: rgba(235, 19, 92, 0.12);
    color: #d71457;
  }

  &.is-info {
    background: rgba(76, 132, 255, 0.12);
    color: #3978ff;
  }
}

.service-status-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: currentColor;
}

.service-ip-button {
  display: inline-flex;
  align-items: center;
  min-height: 32px;
  padding: 0 12px;
  border-radius: 10px;
  background: #f5f8fc;
  color: #21314c;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;

  &.disabled {
    color: #97a3b6;
    cursor: default;
  }
}

.service-table-actions {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
}

.service-action-button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 68px;
  height: 32px;
  padding: 0 12px;
  border-radius: 10px;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
}

.service-login-button {
  background: linear-gradient(90deg, #2f6dff, #4d87ff);
  color: #fff;
  box-shadow: 0 8px 16px rgba(47, 109, 255, 0.18);
}

.service-more-button {
  border: 1px solid #dbe3f0;
  background: #fff;
  color: #4b5a74;
}
</style>
