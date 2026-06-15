import { computed, reactive, ref } from 'vue';
import { DialogPlugin, MessagePlugin } from 'tdesign-vue-next';

import clientApi from '@/api/client';
import { resolveErrorMessage } from './useConsoleCore';

type AnyRecord = Record<string, any>;

export interface UseConsoleSecurityOptions {
  serviceId: { value: number };
}

export function useConsoleSecurity(options: UseConsoleSecurityOptions) {
  const { serviceId } = options;

  const securityState = reactive({
    loading: false,
    submitting: false,
    rulesLoading: false,
    supported: true,
    message: '',
    error: '',
    directions: [] as AnyRecord[],
    protocols: [] as AnyRecord[],
    groups: [] as AnyRecord[],
    rules: [] as AnyRecord[],
  });

  const activeSecurityGroupId = ref(0);
  const groupVisible = ref(false);
  const groupForm = reactive({ name: '', description: '' });
  const ruleVisible = ref(false);
  const ruleForm = reactive({ direction: '', protocol: '', port: '', ip: '', description: '' });

  const activeSecurityGroup = computed(() =>
    securityState.groups.find((item: AnyRecord) => Number(item?.id || 0) === activeSecurityGroupId.value) || null,
  );

  async function loadSecurityGroups() {
    securityState.loading = true;
    try {
      const res = await clientApi.serviceSecurityGroups(serviceId.value);
      const payload = (res as AnyRecord).data || {};
      securityState.supported = payload.supported !== false;
      securityState.message = String(payload.message || '');
      securityState.error = String(payload.error || '');
      securityState.directions = Array.isArray(payload.directions) ? payload.directions : [];
      securityState.protocols = Array.isArray(payload.protocols) ? payload.protocols : [];
      securityState.groups = Array.isArray(payload.groups) ? payload.groups : [];
      const current = securityState.groups.find((item: AnyRecord) => Number(item?.id || 0) === activeSecurityGroupId.value);
      const active = current || securityState.groups.find((item: AnyRecord) => item.is_applied) || securityState.groups[0];
      if (active?.id) {
        await selectSecurityGroup(active, true);
      } else {
        activeSecurityGroupId.value = 0;
        securityState.rules = [];
      }
    } catch (error: any) {
      securityState.error = resolveErrorMessage(error, '加载安全组失败');
    } finally {
      securityState.loading = false;
    }
  }

  async function loadSecurityGroupRules(groupId: number | string) {
    securityState.rulesLoading = true;
    try {
      const res = await clientApi.serviceSecurityGroupRules(serviceId.value, groupId);
      securityState.rules = Array.isArray((res as AnyRecord).data?.list) ? (res as AnyRecord).data.list : [];
    } catch (error: any) {
      MessagePlugin.error(resolveErrorMessage(error, '加载安全组规则失败'));
    } finally {
      securityState.rulesLoading = false;
    }
  }

  async function selectSecurityGroup(group: AnyRecord, silent = false) {
    const groupId = Number(group?.id || 0);
    activeSecurityGroupId.value = groupId;
    if (!groupId) {
      securityState.rules = [];
      return;
    }
    await loadSecurityGroupRules(groupId);
    if (!silent) MessagePlugin.success('安全组规则已加载');
  }

  function openSecurityGroupDialog() {
    groupForm.name = '';
    groupForm.description = '';
    groupVisible.value = true;
  }

  function openSecurityRuleDialog() {
    ruleForm.direction = String(securityState.directions[0]?.value || '');
    ruleForm.protocol = String(securityState.protocols[0]?.value || '');
    ruleForm.port = '';
    ruleForm.ip = '0.0.0.0/0';
    ruleForm.description = '';
    ruleVisible.value = true;
  }

  async function submitSecurityGroup() {
    if (!groupForm.name.trim()) {
      MessagePlugin.warning('请输入安全组名称');
      return;
    }
    securityState.submitting = true;
    try {
      const res = await clientApi.createSecurityGroup(serviceId.value, {
        name: groupForm.name.trim(),
        description: groupForm.description.trim(),
      });
      groupVisible.value = false;
      MessagePlugin.success(String((res as AnyRecord).data?.message || '安全组创建成功'));
      await loadSecurityGroups();
    } catch (error: any) {
      MessagePlugin.error(resolveErrorMessage(error, '创建安全组失败'));
    } finally {
      securityState.submitting = false;
    }
  }

  async function applySecurityGroup(group: AnyRecord) {
    if (!group?.id) return;
    securityState.submitting = true;
    try {
      const res = await clientApi.applySecurityGroup(serviceId.value, group.id);
      MessagePlugin.success(String((res as AnyRecord).data?.message || '安全组已应用'));
      await loadSecurityGroups();
    } catch (error: any) {
      MessagePlugin.error(resolveErrorMessage(error, '应用安全组失败'));
    } finally {
      securityState.submitting = false;
    }
  }

  async function deleteSecurityGroup(group: AnyRecord) {
    if (!group?.id) return;
    const dialog = DialogPlugin.confirm({
      header: '删除安全组',
      body: `确认删除安全组"${group.name || group.id}"吗？`,
      theme: 'warning',
      confirmBtn: '确认删除',
      cancelBtn: '取消',
      onConfirm: async () => {
        dialog.setConfirmLoading(true);
        securityState.submitting = true;
        try {
          const res = await clientApi.deleteSecurityGroup(serviceId.value, group.id);
          MessagePlugin.success(String((res as AnyRecord).data?.message || '安全组已删除'));
          await loadSecurityGroups();
          dialog.hide();
        } catch (error: any) {
          MessagePlugin.error(resolveErrorMessage(error, '删除安全组失败'));
        } finally {
          securityState.submitting = false;
          dialog.setConfirmLoading(false);
        }
      },
    });
  }

  async function submitSecurityRule() {
    if (!activeSecurityGroupId.value) {
      MessagePlugin.warning('请先选择安全组');
      return;
    }
    if (!ruleForm.direction || !ruleForm.protocol || !ruleForm.port.trim() || !ruleForm.ip.trim()) {
      MessagePlugin.warning('请填写完整的规则信息');
      return;
    }
    securityState.submitting = true;
    try {
      const res = await clientApi.createSecurityRule(serviceId.value, activeSecurityGroupId.value, {
        direction: ruleForm.direction,
        protocol: ruleForm.protocol,
        port: ruleForm.port.trim(),
        ip: ruleForm.ip.trim(),
        description: ruleForm.description.trim(),
      });
      ruleVisible.value = false;
      MessagePlugin.success(String((res as AnyRecord).data?.message || '安全组规则创建成功'));
      await loadSecurityGroupRules(activeSecurityGroupId.value);
    } catch (error: any) {
      MessagePlugin.error(resolveErrorMessage(error, '创建安全组规则失败'));
    } finally {
      securityState.submitting = false;
    }
  }

  async function deleteSecurityRule(rule: AnyRecord) {
    if (!rule?.id || !activeSecurityGroupId.value) return;
    const dialog = DialogPlugin.confirm({
      header: '删除规则',
      body: '确认删除该安全组规则吗？',
      theme: 'warning',
      confirmBtn: '确认删除',
      cancelBtn: '取消',
      onConfirm: async () => {
        dialog.setConfirmLoading(true);
        securityState.submitting = true;
        try {
          const res = await clientApi.deleteSecurityRule(serviceId.value, activeSecurityGroupId.value, rule.id);
          MessagePlugin.success(String((res as AnyRecord).data?.message || '安全组规则已删除'));
          await loadSecurityGroupRules(activeSecurityGroupId.value);
          dialog.hide();
        } catch (error: any) {
          MessagePlugin.error(resolveErrorMessage(error, '删除安全组规则失败'));
        } finally {
          securityState.submitting = false;
          dialog.setConfirmLoading(false);
        }
      },
    });
  }

  return {
    securityState,
    activeSecurityGroupId,
    activeSecurityGroup,
    groupVisible,
    groupForm,
    ruleVisible,
    ruleForm,
    loadSecurityGroups,
    loadSecurityGroupRules,
    selectSecurityGroup,
    openSecurityGroupDialog,
    openSecurityRuleDialog,
    submitSecurityGroup,
    applySecurityGroup,
    deleteSecurityGroup,
    submitSecurityRule,
    deleteSecurityRule,
  };
}