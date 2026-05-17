<template>
  <div class="content-card">
    <div class="card-header">
      <div>
        <div class="header-title">
          <div class="title-bar"></div>
          <h3>合作代理</h3>
        </div>
        <p class="header-desc">代理能力还在准备中，后续会开放专属价格、返佣与业务协作入口。</p>
      </div>
      <div class="card-header__meta">敬请期待</div>
    </div>

    <div class="card-body">
      <div class="not-open">
        <div class="not-open-icon">
          <el-icon><Clock /></el-icon>
        </div>
        <h4>代理合作功能暂未开通</h4>
        <p>等功能上线后，你可以在这里查看代理等级、返佣规则和邀请带来的收益明细。</p>

        <div class="not-open-list">
          <div class="not-open-list__item">专属代理折扣与价格体系</div>
          <div class="not-open-list__item">邀请客户后的返佣统计</div>
          <div class="not-open-list__item">代理等级、结算与权益说明</div>
        </div>

        <el-button plain>了解更多</el-button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { Clock } from '@element-plus/icons-vue'
</script>

<style lang="scss" scoped>
.content-card {
  background: #fff;
  border-radius: $base-border-radius;
  box-shadow: $shadow-sm;
  border: 1px solid $border-color;
  overflow: hidden;
}

.card-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  padding: 28px 32px;
  border-bottom: 1px solid $border-color;
  background: linear-gradient(to right, #fff 0%, $bg-color-soft 100%);
}

.card-header__meta {
  padding: 6px 10px;
  border-radius: 999px;
  background: rgba(22, 93, 255, 0.08);
  color: $color-primary;
  font-size: 12px;
  font-weight: 600;
  white-space: nowrap;
}

.header-title {
  display: flex;
  align-items: center;
  gap: 10px;

  h3 {
    margin: 0;
    font-size: 18px;
    font-weight: 600;
    color: $text-color-primary;
  }
}

.title-bar {
  width: 4px;
  height: 20px;
  background: $color-primary;
  border-radius: 2px;
}

.header-desc {
  margin: 8px 0 0 14px;
  font-size: 13px;
  color: $text-color-secondary;
  line-height: 1.7;
}

.card-body {
  padding: 0;
}

.not-open {
  text-align: center;
  padding: 56px 32px;
}

.not-open-icon {
  width: 86px;
  height: 86px;
  border-radius: 24px;
  background: linear-gradient(180deg, rgba(232, 241, 255, 0.9), rgba(248, 250, 252, 0.9));
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 24px;

  .el-icon {
    font-size: 38px;
    color: $color-primary;
  }
}

.not-open h4 {
  margin: 0 0 10px;
  font-size: 20px;
  font-weight: 600;
  color: $text-color-primary;
}

.not-open p {
  margin: 0 auto;
  max-width: 460px;
  font-size: 14px;
  color: $text-color-secondary;
  line-height: 1.8;
}

.not-open-list {
  display: grid;
  gap: 12px;
  max-width: 460px;
  margin: 26px auto 28px;
  text-align: left;
}

.not-open-list__item {
  padding: 14px 16px;
  border: 1px solid $divider-color;
  border-radius: 12px;
  background: $bg-color-soft;
  color: $text-color-primary;
  font-size: 14px;
}

@media (max-width: 640px) {
  .card-header {
    flex-direction: column;
    align-items: flex-start;
    padding: 20px;
  }

  .not-open {
    padding: 40px 20px;
  }
}
</style>
