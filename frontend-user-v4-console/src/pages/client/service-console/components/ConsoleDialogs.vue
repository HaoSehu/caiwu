<template>
  <SecurityGroupDialog />
  <SecurityRuleDialog />
  <PasswordResetDialog />
  <ReinstallDialog />
  <RenewDialog />
  <TrafficPackageDialog />
  <NameDialog />
  <RemarkDialog />
</template>

<script setup lang="ts">
import NameDialog from './dialogs/NameDialog.vue';
import PasswordResetDialog from './dialogs/PasswordResetDialog.vue';
import ReinstallDialog from './dialogs/ReinstallDialog.vue';
import RemarkDialog from './dialogs/RemarkDialog.vue';
import RenewDialog from './dialogs/RenewDialog.vue';
import SecurityGroupDialog from './dialogs/SecurityGroupDialog.vue';
import SecurityRuleDialog from './dialogs/SecurityRuleDialog.vue';
import TrafficPackageDialog from './dialogs/TrafficPackageDialog.vue';
</script>
