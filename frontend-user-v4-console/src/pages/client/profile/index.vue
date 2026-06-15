<template>
  <section class="profile-page">
    <header class="client-page-heading">
      <h1>个人资料</h1>
    </header>

    <aside class="profile-nav">
      <t-card class="profile-card" :bordered="false">
        <template #title>账户中心</template>
        <t-menu :value="activeTab" theme="light" @change="handleProfileTabChange">
          <t-menu-item value="profile">个人资料</t-menu-item>
          <t-menu-item value="security">账户安全</t-menu-item>
          <t-menu-item value="agent">合作代理</t-menu-item>
          <t-menu-item value="notification">消息提醒</t-menu-item>
        </t-menu>
      </t-card>
    </aside>

    <main class="profile-main">
      <t-card v-if="activeTab === 'profile'" class="profile-card" :bordered="false">
        <template #title>个人资料</template>
        <template #actions><t-tag variant="light">基础信息</t-tag></template>
        <t-form label-align="left" label-width="6rem" class="profile-form">
          <t-form-item label="账户ID">
            <t-input :value="profileForm.id" readonly />
            <t-button variant="outline" @click="copyText(profileForm.id)">复制</t-button>
          </t-form-item>
          <t-form-item label="注册时间"><t-input :value="profileForm.createdAt || '--'" readonly /></t-form-item>
          <t-form-item label="用户名"><t-input v-model="profileForm.nickname" maxlength="50" placeholder="请输入用户名" /></t-form-item>
          <t-form-item label="账户余额"><t-input :value="balanceText" readonly /></t-form-item>
          <t-form-item label="登录邮箱"><t-input :value="profileForm.email || '--'" readonly /></t-form-item>
          <t-form-item label="账户状态">
            <t-tag :theme="profileForm.is_verified ? 'success' : 'default'" variant="light">
              {{ profileForm.is_verified ? '已实名' : '未实名' }}
            </t-tag>
          </t-form-item>
        </t-form>
        <div class="profile-footer">
          <span>保存后会立即更新当前账户资料。</span>
          <t-button theme="primary" :loading="profileLoading" @click="updateProfile">保存资料</t-button>
        </div>
      </t-card>

      <t-card v-else-if="activeTab === 'security'" class="profile-card" :bordered="false">
        <template #title>账户安全</template>
        <div class="security-list">
          <article v-for="item in securityItems" :key="item.key" class="security-item">
            <div>
              <div class="security-item__head">
                <strong>{{ item.name }}</strong>
                <t-tag :theme="item.theme" variant="light">{{ item.tag }}</t-tag>
              </div>
              <p>{{ item.desc }}</p>
            </div>
            <t-button theme="primary" variant="text" @click="item.action">{{ item.actionLabel }}</t-button>
          </article>
        </div>
      </t-card>

      <t-card v-else-if="activeTab === 'agent'" class="profile-card" :bordered="false">
        <template #title>合作代理</template>
        <t-empty description="代理合作功能暂未开通" />
        <div class="agent-list">
          <span>专属代理折扣与价格体系</span>
          <span>邀请客户后的返佣统计</span>
          <span>代理等级、结算与权益说明</span>
        </div>
      </t-card>

      <t-card v-else class="profile-card" :bordered="false">
        <template #title>消息提醒</template>
        <template #actions><t-tag variant="light">已开启 {{ enabledNotificationCount }}</t-tag></template>
        <div class="notification-list">
          <article v-for="item in notificationList" :key="item.key" class="notification-item">
            <div>
              <strong>{{ item.name }}</strong>
              <p>{{ item.desc }}</p>
            </div>
            <t-switch v-model="item.enabled" />
          </article>
        </div>
        <div class="profile-footer">
          <span>关闭安全提醒可能会错过密码、邮箱或手机号变更通知。</span>
          <t-button theme="primary" :loading="notificationLoading" @click="saveNotificationPreferences">保存设置</t-button>
        </div>
      </t-card>
    </main>

    <t-dialog v-model:visible="passwordDialogVisible" header="修改登录密码" width="min(30rem, calc(100vw - 2rem))">
      <t-form label-align="top">
        <t-form-item label="原密码"><t-input v-model="passwordForm.oldPassword" type="password" /></t-form-item>
        <t-form-item label="新密码"><t-input v-model="passwordForm.newPassword" type="password" /></t-form-item>
        <t-form-item label="确认密码"><t-input v-model="passwordForm.confirmPassword" type="password" /></t-form-item>
      </t-form>
      <template #footer>
        <t-button variant="outline" @click="passwordDialogVisible = false">取消</t-button>
        <t-button theme="primary" :loading="profileLoading" @click="changePassword">确定</t-button>
      </template>
    </t-dialog>
  </section>
</template>

<script setup lang="ts">
import { useProfile } from '@/domains/account/useProfile';

const {
  activeTab,
  profileLoading,
  notificationLoading,
  passwordDialogVisible,
  profileForm,
  passwordForm,
  notificationList,
  balanceText,
  enabledNotificationCount,
  securityItems,
  copyText,
  updateProfile,
  changePassword,
  saveNotificationPreferences,
  handleProfileTabChange,
} = useProfile();
</script>

<style scoped lang="less">
.profile-page {
  display: grid;
  grid-template-columns: minmax(14rem, 18rem) minmax(0, 1fr);
  gap: var(--td-comp-margin-m);
  padding: var(--td-comp-paddingTB-l) var(--td-comp-paddingLR-l);
}

.client-page-heading {
  grid-column: 1 / -1;

  h1 {
    margin: 0;
    color: var(--td-text-color-primary);
    font: var(--td-font-title-large);
  }
}

.profile-card {
  background: var(--td-bg-color-container);
  border: thin solid var(--td-border-color);
  border-radius: var(--td-radius-medium);
  box-shadow: var(--td-shadow-1);
}

.profile-nav {
  position: sticky;
  top: var(--td-comp-margin-m);
  align-self: start;
}

.profile-main {
  min-width: 0;
}

.profile-form {
  max-width: 46rem;
}

.profile-footer {
  display: flex;
  gap: var(--td-comp-margin-m);
  align-items: center;
  justify-content: space-between;
  margin-top: var(--td-comp-margin-l);
  padding-top: var(--td-comp-margin-m);
  color: var(--td-text-color-secondary);
  border-top: thin dashed var(--td-border-color);
  font: var(--td-font-body-small);
}

.security-list,
.notification-list {
  display: flex;
  flex-direction: column;
  gap: var(--td-comp-margin-s);
}

.security-item,
.notification-item {
  display: flex;
  gap: var(--td-comp-margin-m);
  align-items: center;
  justify-content: space-between;
  padding: var(--td-comp-paddingTB-m) var(--td-comp-paddingLR-m);
  background: var(--td-bg-color-container);
  border: thin solid var(--td-border-color);
  border-radius: var(--td-radius-medium);

  p {
    margin: var(--td-comp-margin-xs) 0 0;
    color: var(--td-text-color-secondary);
    font: var(--td-font-body-small);
  }
}

.security-item__head {
  display: flex;
  flex-wrap: wrap;
  gap: var(--td-comp-margin-s);
  align-items: center;
}

.agent-list {
  display: grid;
  gap: var(--td-comp-margin-s);
  max-width: 32rem;
  margin: var(--td-comp-margin-m) auto 0;

  span {
    padding: var(--td-comp-paddingTB-s) var(--td-comp-paddingLR-m);
    color: var(--td-text-color-primary);
    background: var(--td-bg-color-component);
    border-radius: var(--td-radius-medium);
  }
}

@media (max-width: 56rem) {
  .profile-page {
    grid-template-columns: 1fr;
    padding: var(--td-comp-paddingTB-m) var(--td-comp-paddingLR-s);
  }

  .profile-nav {
    position: static;
  }

  .profile-footer,
  .security-item,
  .notification-item {
    align-items: flex-start;
    flex-direction: column;
  }
}
</style>
