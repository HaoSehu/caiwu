<template>
  <div class="forbidden">
    <h1>403</h1>
    <p>抱歉，您没有访问该页面的权限</p>
    <el-button type="primary" @click="$router.push('/admin/dashboard')">返回仪表盘</el-button>
  </div>
</template>
<style scoped>
.forbidden { height: 100vh; display: flex; flex-direction: column; align-items: center; justify-content: center; }
h1 { font-size: 120px; color: var(--el-color-warning); margin: 0; }
p { font-size: 18px; color: var(--el-text-color-secondary); margin: 20px 0 30px; }
</style>
