<template>
  <div :class="['client-page', 'record-page', 'invoice-panel-page', pageClass]">
    <div class="record-toolbar-grid" :class="{ 'record-toolbar-grid--col2': !showTypeSelector }">
      <el-input v-model="filters.keyword" :placeholder="keywordPlaceholder" clearable @keyup.enter="handleSearch" />
      <el-select v-model="filters.status" placeholder="全部状态" clearable @change="handleSearch">
        <el-option label="待支付" :value="0" />
        <el-option label="已支付" :value="1" />
        <el-option label="已取消" :value="2" />
        <el-option label="已过期" :value="3" />
        <el-option label="已退款" :value="5" />
      </el-select>
      <el-select v-if="showTypeSelector" v-model="filters.type" placeholder="全部类型" clearable @change="handleSearch">
        <el-option label="新购账单" value="new" />
        <el-option label="续费账单" value="renew" />
        <el-option label="升级账单" value="upgrade" />
        <el-option label="流量包" value="traffic" />
      </el-select>
    </div>

    <el-table v-if="!isMobileScreen" :data="list" v-loading="loading">
      <el-table-column label="账单号" min-width="170">
        <template #default="{ row }">{{ row.invoice_no || `#${row.id}` }}</template>
      </el-table-column>
      <el-table-column label="商品" min-width="220">
        <template #default="{ row }">
          <div class="record-stack-cell">
            <strong>{{ resolveInvoiceTitle(row) }}</strong>
            <span>{{ resolveInvoiceSubtitle(row) }}</span>
          </div>
        </template>
      </el-table-column>
      <el-table-column label="金额" min-width="120">
        <template #default="{ row }">¥ {{ row.amount || '0.00' }}</template>
      </el-table-column>
      <el-table-column label="待付" min-width="120">
        <template #default="{ row }">¥ {{ row.payable_amount || '0.00' }}</template>
      </el-table-column>
      <el-table-column label="状态" min-width="120">
        <template #default="{ row }">
          <el-tag :type="resolveInvoiceTagType(row.status)" effect="light">
            {{ row.status_label || '--' }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column label="创建时间" min-width="180" prop="created_at" />
      <el-table-column label="操作" width="80" fixed="right">
        <template #default="{ row }">
          <el-button text type="primary" size="small" @click="openDetail(row)">详情</el-button>
        </template>
      </el-table-column>
    </el-table>
