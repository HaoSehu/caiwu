<template>
  <div class="users-verification-page">
    <t-card :bordered="false">
      <verification-panel />
    </t-card>
  </div>
</template>

<script setup lang="ts">
import VerificationPanel from '@/pages/users/components/VerificationPanel.vue';

import './index.less';

defineOptions({
  name: 'AdminUserVerification',
});
</script>
