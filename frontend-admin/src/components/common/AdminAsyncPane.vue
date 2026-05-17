<template>
  <div class="admin-async-pane admin-page">
    <section class="admin-page-head admin-async-pane__head">
      <div class="admin-page-heading admin-async-pane__heading">
        <el-skeleton-item variant="p" class="admin-async-pane__kicker" />
        <el-skeleton-item variant="h3" class="admin-async-pane__title" />
        <el-skeleton-item variant="text" class="admin-async-pane__desc" />
      </div>
    </section>

    <el-card shadow="never" class="admin-async-pane__card">
      <el-skeleton :rows="8" animated />
    </el-card>
  </div>
</template>

<style lang="scss" scoped>
.admin-async-pane {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.admin-async-pane__head {
  align-items: flex-start;
}

.admin-async-pane__heading {
  display: flex;
  flex-direction: column;
  gap: 12px;
  width: min(520px, 100%);
}

.admin-async-pane__kicker {
  width: 92px;
  height: 14px;
}

.admin-async-pane__title {
  width: min(240px, 70%);
  height: 30px;
}

.admin-async-pane__desc {
  width: min(420px, 100%);
  height: 16px;
}

.admin-async-pane__card {
  border-radius: 18px;
}
</style>
