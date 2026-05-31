<template>
  <div class="client-page catalog-page">
    <section class="catalog-bridge-card">
      <el-result
        icon="info"
        title="统一使用官网产品目录"
        sub-title="购买、报价和配置流程已经收口到 /products，客户端这里保留为稳定入口。"
      >
        <template #extra>
          <el-button type="primary" @click="router.push('/products')">打开产品目录</el-button>
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
.catalog-bridge-card {
  padding: 24px;
  border: 1px solid $border-color;
  border-radius: $base-border-radius;
  background: #fff;
  box-shadow: $shadow-sm;
}
</style>
