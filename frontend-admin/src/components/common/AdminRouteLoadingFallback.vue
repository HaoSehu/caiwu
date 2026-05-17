<template>
  <div class="admin-route-loading">
    <AdminAsyncPane />
  </div>
</template>

<script setup>
import AdminAsyncPane from './AdminAsyncPane.vue'
</script>

<style scoped lang="scss">
.admin-route-loading {
  min-height: 100vh;
  padding: 20px 24px 32px;
  background: $bg-color;
}

@media (max-width: 1280px) {
  .admin-route-loading {
    padding: 16px 18px 24px;
  }
}

@media (max-width: 900px) {
  .admin-route-loading {
    padding: 14px 16px 20px;
  }
}

@media (max-width: 640px) {
  .admin-route-loading {
    padding: 12px 12px 20px;
  }
}
</style>
