<template>
  <div class="client-page order-create-page">
    <section class="order-bridge-card">
      <el-result
        icon="warning"
        title="下单入口已重定向到统一购买链路"
        sub-title="新的购买、登录续接与账单创建均在 /products 与 /client/checkout-resume 中完成。"
      >
        <template #extra>
          <el-button type="primary" @click="router.push('/products')">打开购买页</el-button>
        </template>
      </el-result>
    </section>
  </div>
</template>

<script setup lang="ts">
import { useRouter } from 'vue-router'

const router = useRouter()
</script>

<style scoped lang="scss">
.order-create-page {
  gap: 20px;
}

.order-bridge-card {
  padding: 24px;
  border: 1px solid $border-color;
  border-radius: $base-border-radius;
  background: #fff;
  box-shadow: $shadow-sm;
}
</style>
