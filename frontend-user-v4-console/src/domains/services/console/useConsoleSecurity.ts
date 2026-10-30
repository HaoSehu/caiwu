import { DialogPlugin } from 'tdesign-vue-next/es/dialog/plugin';
import { MessagePlugin } from 'tdesign-vue-next/es/message/plugin';
import { computed, reactive, ref } from 'vue';

import clientApi from '@/api/client';
import type { ConsoleSelectOption, SecurityGroupRecord, SecurityRuleRecord } from '@/types/client';
import { getErrorMessage } from '@/utils/error';

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
    canCreate: true,
    message: '',
    error: '',
    directions: [] as ConsoleSelectOption[],
    protocols: [] as ConsoleSelectOption[],
    groups: [] as SecurityGroupRecord[],
    rules: [] as SecurityRuleRecord[],
  });

  const activeSecurityGroupId = ref(0);
  // 竞态防护：安全组列表与规则都按序号丢弃过期响应（对齐 useConsoleTabs.ts 的 requestSeq 模式）
  let securityGroupsRequestSeq = 0;
  let securityRulesRequestSeq = 0;
  const groupVisible = ref(false);
  const groupForm = reactive({ name: '', description: '' });
  const ruleVisible = ref(false);
  const ruleForm = reactive({ direction: '', protocol: '', port: '', ip: '', description: '' });

  /** 已知协议的默认端口映射（小写）。值为 null 表示该协议无端口（如 ICMP）。 */
  const PROTOCOL_DEFAULT_PORT: Record<string, string | null> = {
    all: '1-65535',
    icmp: null,
    icmpv6: null,
    ssh: '22',
    telnet: '23',
    smtp: '25',
    dns: '53',
    dhcp: '67-68',
    http: '80',
    pop3: '110',
    imap: '143',
    https: '443',
    rdp: '3389',
    mysql: '3306',
    redis: '6379',
    postgres: '5432',
  };

  /** 解析当前选中协议的默认端口（兼容 全部/全部 TCP/UDP 这种组合标签） */
  function resolveProtocolDefaultPort(): string | null {
    const raw = String(ruleForm.protocol || '').toLowerCase();
    const label = (
      securityState.protocols.find((p) => String(p.value).toLowerCase() === raw)?.label || ''
    ).toLowerCase();

    // 精确匹配 PROTOCOL_DEFAULT_PORT
    // 用 hasOwnProperty.call 而非 Object.hasOwn：后者要 Chrome 93 / Safari 15.4，
    // 超出仓库声明的浏览器基线（见 frontend-user-v3-www/package.json 的 browserslist）。
    if (Object.prototype.hasOwnProperty.call(PROTOCOL_DEFAULT_PORT, raw)) {
      return PROTOCOL_DEFAULT_PORT[raw];
    }

    // 兼容标签中含 all/全部 的组合（全部、全部 TCP、全部 UDP、TCP ALL 等）→ 端口 1-65535
    if (raw.includes('all') || label.includes('全部') || label === 'all') {
      return '1-65535';
    }

    // 兼容标签中含 icmp/ICMP 的组合
    if (raw.includes('icmp') || label.includes('icmp')) {
      return null;
    }

    // 常规协议（TCP/UDP）由用户手动填写端口
    return undefined;
  }

  /** 端口输入是否禁用（协议为固定/无端口类型时） */
  const isPortDisabled = computed(() => {
    const port = resolveProtocolDefaultPort();
    return port !== undefined;
  });

  /** 旧字段保留兼容：是否所有端口 */
  const isAllPortProtocol = computed(() => resolveProtocolDefaultPort() === '1-65535');

  /** 协议变化时自动填充端口 */
  function onProtocolChange(val: string) {
    ruleForm.protocol = val;
    const defaultPort = resolveProtocolDefaultPort();
    if (defaultPort === undefined) {
      // 普通协议：若之前是自动填充值则清空，让用户输入
      if (ruleForm.port === '1-65535') ruleForm.port = '';
    } else if (defaultPort === null) {
      // ICMP 等无端口协议：留空提交
      ruleForm.port = '';
    } else {
      ruleForm.port = defaultPort;
    }
  }

  const activeSecurityGroup = computed(
    () => securityState.groups.find((item) => Number(item.id || 0) === activeSecurityGroupId.value) || null,
  );

  function resolveActionMessage(payload: { message?: string } | null | undefined, fallback: string) {
    return String(payload?.message || '').trim() || fallback;
  }

  async function loadSecurityGroups(fresh = false): Promise<boolean> {
    const requestSeq = ++securityGroupsRequestSeq;
    securityState.loading = true;
    try {
      const res = await clientApi.serviceSecurityGroups(serviceId.value, fresh ? { fresh: true } : undefined);
      if (requestSeq !== securityGroupsRequestSeq) return false;
      const payload = res.data || {};
      securityState.supported = payload.supported !== false;
      securityState.canCreate = payload.can_create !== false;
      securityState.message = String(payload.message || '');
      securityState.error = String(payload.error || '');
      securityState.directions = Array.isArray(payload.directions) ? payload.directions : [];
      securityState.protocols = Array.isArray(payload.protocols) ? payload.protocols : [];
      securityState.groups = Array.isArray(payload.groups) ? payload.groups : [];
      const viewableGroups = securityState.groups.filter((item) => item.can_view !== false);
      const current = viewableGroups.find((item) => Number(item.id || 0) === activeSecurityGroupId.value);
      const active = current || viewableGroups.find((item) => item.is_applied) || viewableGroups[0];
      if (active?.id) {
        await selectSecurityGroup(active);
      } else {
        activeSecurityGroupId.value = 0;
        securityState.rules = [];
      }
      return true;
    } catch (error: unknown) {
      if (requestSeq !== securityGroupsRequestSeq) return false;
      securityState.error = getErrorMessage(error, '加载安全组失败');
      return false;
    } finally {
      if (requestSeq === securityGroupsRequestSeq) securityState.loading = false;
    }
  }

  async function loadSecurityGroupRules(groupId: number | string) {
    const requestSeq = ++securityRulesRequestSeq;
    securityState.rulesLoading = true;
    try {
      const res = await clientApi.serviceSecurityGroupRules(serviceId.value, groupId);
      if (requestSeq !== securityRulesRequestSeq) return;
      securityState.rules = Array.isArray(res.data?.list) ? res.data.list : [];
    } catch (error: unknown) {
      if (requestSeq !== securityRulesRequestSeq) return;
      MessagePlugin.error(getErrorMessage(error, '加载安全组规则失败'));
    } finally {
      if (requestSeq === securityRulesRequestSeq) securityState.rulesLoading = false;
    }
  }

  // 切换安全组只是查看动作，不提示成功：整卡与「查看」按钮都走这里，弹 toast 会变成噪声
  async function selectSecurityGroup(group: SecurityGroupRecord) {
    const groupId = Number(group.id || 0);
    if (group.can_view === false) {
      activeSecurityGroupId.value = 0;
      securityState.rules = [];
      return;
    }

    activeSecurityGroupId.value = groupId;
    if (!groupId) {
      securityState.rules = [];
      return;
    }
    await loadSecurityGroupRules(groupId);
  }

  function openSecurityGroupDialog() {
    groupForm.name = '';
    groupForm.description = '';
    groupVisible.value = true;
  }

  function openSecurityRuleDialog() {
    ruleForm.direction = String(securityState.directions[0]?.value || '');
    ruleForm.protocol = String(securityState.protocols[0]?.value || '');
    const defaultPort = resolveProtocolDefaultPort();
    ruleForm.port = defaultPort ?? '';
    ruleForm.ip = '0.0.0.0/0';
    ruleForm.description = '';
    ruleVisible.value = true;
  }

  async function submitSecurityGroup() {
    // 重入守卫：按钮只受 :loading 约束，回车连击或双击仍会二次进入，
    // 而 submitting 是异步才复位，这里必须自己在入口挡住（与 applySecurityGroup 同口径）。
    if (securityState.submitting) return;
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
      MessagePlugin.success(resolveActionMessage(res.data, '安全组创建成功'));
      await loadSecurityGroups();
    } catch (error: unknown) {
      MessagePlugin.error(getErrorMessage(error, '创建安全组失败'));
    } finally {
      securityState.submitting = false;
    }
  }

  // 应用安全组会立即改变实例的可达性（可能一刀切断现网连接），
  // 与同文件的删除组/删除规则一致，必须走二次确认，禁止单击直发。
  // 应用组的二次确认也是异步等待：等待期间提交中标记必须占住，
  // 否则连点会叠出多个确认框，每个确认后都各发一次应用请求。
  let applyConfirming = false;
  async function applySecurityGroup(group: SecurityGroupRecord) {
    if (!group.id || applyConfirming) return;
    applyConfirming = true;
    let confirmed = false;
    try {
      confirmed = await new Promise<boolean>((resolve) => {
        const dialog = DialogPlugin.confirm({
          header: '应用安全组',
          body: `确定将安全组“${group.name || group.id}”应用到当前实例吗？应用后新的入站/出站规则立即生效，可能中断现有连接。`,
          confirmBtn: '应用',
          cancelBtn: '取消',
          onConfirm: () => {
            dialog.destroy();
            resolve(true);
          },
          onCancel: () => {
            dialog.destroy();
            resolve(false);
          },
          onClose: () => {
            dialog.destroy();
            resolve(false);
          },
        });
      });
    } finally {
      applyConfirming = false;
    }
    if (!confirmed) return;
    securityState.submitting = true;
    try {
      const res = await clientApi.applySecurityGroup(serviceId.value, group.id);
      MessagePlugin.success(resolveActionMessage(res.data, '安全组已应用'));
      await loadSecurityGroups();
    } catch (error: unknown) {
      MessagePlugin.error(getErrorMessage(error, '应用安全组失败'));
    } finally {
      securityState.submitting = false;
    }
  }

  async function deleteSecurityGroup(group: SecurityGroupRecord) {
    if (!group.id) return;
    const dialog = DialogPlugin.confirm({
      header: '删除安全组',
      body: `确定删除安全组“${group.name || group.id}”吗？删除后其规则随之失效，且不可恢复。`,
      theme: 'danger',
      confirmBtn: { content: '删除', theme: 'danger' },
      cancelBtn: '取消',
      onConfirm: async () => {
        dialog.setConfirmLoading(true);
        securityState.submitting = true;
        try {
          const res = await clientApi.deleteSecurityGroup(serviceId.value, group.id);
          MessagePlugin.success(resolveActionMessage(res.data, '安全组已删除'));
          await loadSecurityGroups();
          dialog.hide();
        } catch (error: unknown) {
          MessagePlugin.error(getErrorMessage(error, '删除安全组失败'));
        } finally {
          securityState.submitting = false;
          dialog.setConfirmLoading(false);
        }
      },
    });
  }

  async function submitSecurityRule() {
    // 与 submitSecurityGroup 同口径：submitting 异步才复位，入口必须自己挡重入。
    if (securityState.submitting) return;
    if (!activeSecurityGroupId.value) {
      MessagePlugin.warning('请先选择安全组');
      return;
    }
    const defaultPort = resolveProtocolDefaultPort();
    const isNoPortProtocol = defaultPort === null;
    if (
      !ruleForm.direction ||
      !ruleForm.protocol ||
      (!isNoPortProtocol && !ruleForm.port.trim()) ||
      !ruleForm.ip.trim()
    ) {
      MessagePlugin.warning('请填写完整的规则信息');
      return;
    }
    securityState.submitting = true;
    try {
      const portValue = isNoPortProtocol ? ruleForm.port.trim() || '1-65535' : ruleForm.port.trim();
      const res = await clientApi.createSecurityRule(serviceId.value, activeSecurityGroupId.value, {
        direction: ruleForm.direction,
        protocol: ruleForm.protocol,
        port: portValue,
        ip: ruleForm.ip.trim(),
        description: ruleForm.description.trim(),
      });
      ruleVisible.value = false;
      MessagePlugin.success(resolveActionMessage(res.data, '安全组规则创建成功'));
      await loadSecurityGroupRules(activeSecurityGroupId.value);
    } catch (error: unknown) {
      MessagePlugin.error(getErrorMessage(error, '创建安全组规则失败'));
    } finally {
      securityState.submitting = false;
    }
  }

  async function deleteSecurityRule(rule: SecurityRuleRecord) {
    if (!rule.id || !activeSecurityGroupId.value) return;
    const dialog = DialogPlugin.confirm({
      header: '删除规则',
      body: '确定删除该安全组规则吗？删除后对应端口/来源的访问控制立即失效，且不可恢复。',
      theme: 'danger',
      confirmBtn: { content: '删除', theme: 'danger' },
      cancelBtn: '取消',
      onConfirm: async () => {
        dialog.setConfirmLoading(true);
        securityState.submitting = true;
        try {
          const res = await clientApi.deleteSecurityRule(serviceId.value, activeSecurityGroupId.value, rule.id);
          MessagePlugin.success(resolveActionMessage(res.data, '安全组规则已删除'));
          await loadSecurityGroupRules(activeSecurityGroupId.value);
          dialog.hide();
        } catch (error: unknown) {
          MessagePlugin.error(getErrorMessage(error, '删除安全组规则失败'));
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
    isPortDisabled,
    isAllPortProtocol,
    onProtocolChange,
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
