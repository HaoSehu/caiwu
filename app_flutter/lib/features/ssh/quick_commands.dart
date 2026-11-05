import 'package:tdesign_flutter/tdesign_flutter.dart';

import '../../core/theme/app_tokens.dart';

/// 一条常用命令：展示名 + 实际下发的命令 + 分组。
class QuickCommand {
  const QuickCommand({
    required this.label,
    required this.command,
    required this.group,
  });

  /// 显示名，如「查看磁盘」。
  final String label;

  /// 实际命令，如 `df -h`。
  final String command;

  /// 分组：系统 / 网络 / 进程 / Docker。
  final String group;
}

/// 分组展示顺序（按运维排查的常见先后排列）。
const kQuickCommandGroups = <String>['系统', '网络', '进程', 'Docker'];

/// 常用命令清单，均为 Linux 发行版常见的真实命令，不需要额外安装。
const kQuickCommands = <QuickCommand>[
  // ── 系统 ────────────────────────────────────────────────────────────
  QuickCommand(label: '运行时长与负载', command: 'uptime', group: '系统'),
  QuickCommand(label: '磁盘使用', command: 'df -h', group: '系统'),
  QuickCommand(label: '内存使用', command: 'free -m', group: '系统'),
  QuickCommand(label: '系统版本', command: 'cat /etc/os-release', group: '系统'),
  QuickCommand(label: '内核版本', command: 'uname -a', group: '系统'),
  QuickCommand(label: 'CPU 信息', command: 'lscpu', group: '系统'),
  QuickCommand(label: '磁盘分区', command: 'lsblk', group: '系统'),
  QuickCommand(label: '当前用户', command: 'whoami', group: '系统'),
  QuickCommand(label: '系统时间', command: 'date', group: '系统'),
  QuickCommand(label: '在线用户', command: 'who', group: '系统'),
  QuickCommand(label: '最近内核日志', command: 'dmesg | tail -n 30', group: '系统'),

  // ── 网络 ────────────────────────────────────────────────────────────
  QuickCommand(label: '监听端口', command: 'ss -tuln', group: '网络'),
  QuickCommand(label: '网卡地址', command: 'ip a', group: '网络'),
  QuickCommand(label: '路由表', command: 'ip route', group: '网络'),
  QuickCommand(label: '网卡流量统计', command: 'ip -s link', group: '网络'),
  QuickCommand(label: '连接数概览', command: 'ss -s', group: '网络'),
  QuickCommand(label: '公网 IP', command: 'curl -s ifconfig.me', group: '网络'),
  QuickCommand(label: '连通性测试', command: 'ping -c 4 8.8.8.8', group: '网络'),
  QuickCommand(label: '域名解析', command: 'getent hosts example.com', group: '网络'),

  // ── 进程 ────────────────────────────────────────────────────────────
  QuickCommand(label: '实时进程', command: 'top -bn1 | head -n 20', group: '进程'),
  QuickCommand(
    label: 'CPU 占用前 10',
    command: 'ps aux --sort=-%cpu | head -n 11',
    group: '进程',
  ),
  QuickCommand(
    label: '内存占用前 10',
    command: 'ps aux --sort=-%mem | head -n 11',
    group: '进程',
  ),
  QuickCommand(label: '进程树', command: 'ps -ef --forest', group: '进程'),
  QuickCommand(label: '异常服务', command: 'systemctl --failed', group: '进程'),
  QuickCommand(
    label: '错误日志',
    command: 'journalctl -p err -n 50 --no-pager',
    group: '进程',
  ),
  QuickCommand(label: '登录历史', command: 'last -n 10', group: '进程'),

  // ── Docker ─────────────────────────────────────────────────────────
  QuickCommand(label: '运行中容器', command: 'docker ps', group: 'Docker'),
  QuickCommand(label: '全部容器', command: 'docker ps -a', group: 'Docker'),
  QuickCommand(label: '本地镜像', command: 'docker images', group: 'Docker'),
  QuickCommand(
    label: '容器资源占用',
    command: 'docker stats --no-stream',
    group: 'Docker',
  ),
  QuickCommand(
    label: 'Docker 磁盘占用',
    command: 'docker system df',
    group: 'Docker',
  ),
];

/// 打开快捷命令底部弹层。
///
/// 选中命令后回调 [onPick] 并自动关闭弹层；[onPick] 由终端页负责写入 shell。
Future<void> showSshQuickCommandSheet(
  BuildContext context, {
  required void Function(String command) onPick,
}) {
  return showModalBottomSheet<void>(
    context: context,
    isScrollControlled: true,
    backgroundColor: context.tTheme.bgColorContainer,
    shape: const RoundedRectangleBorder(
      borderRadius: BorderRadius.vertical(
        top: Radius.circular(AppRadius.sheet),
      ),
    ),
    builder: (sheetContext) => SshQuickCommandSheet(onPick: onPick),
  );
}

/// 快捷命令弹层内容：按分组列出 [kQuickCommands]，点击即回调并关闭。
class SshQuickCommandSheet extends StatelessWidget {
  const SshQuickCommandSheet({super.key, required this.onPick});

  final void Function(String command) onPick;

  @override
  Widget build(BuildContext context) {
    final theme = context.tTheme;
    final maxHeight = MediaQuery.sizeOf(context).height * 0.72;
    final groups = <String, List<QuickCommand>>{
      for (final group in kQuickCommandGroups)
        group: kQuickCommands.where((item) => item.group == group).toList(),
    };
    return SafeArea(
      top: false,
      child: ConstrainedBox(
        constraints: BoxConstraints(maxHeight: maxHeight),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const SizedBox(height: AppSpacing.gapS),
            Container(
              width: 36,
              height: 4,
              decoration: BoxDecoration(
                color: theme.borderLevel2Color,
                borderRadius: BorderRadius.circular(AppRadius.pill),
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(
                AppSpacing.page,
                AppSpacing.gapM,
                AppSpacing.page,
                AppSpacing.gapS,
              ),
              child: Row(
                children: [
                  Expanded(child: Text('快捷命令', style: AppType.section(theme))),
                  Text(
                    '共 ${kQuickCommands.length} 条',
                    style: AppType.caption(theme),
                  ),
                ],
              ),
            ),
            Flexible(
              child: ListView(
                shrinkWrap: true,
                padding: const EdgeInsets.fromLTRB(
                  AppSpacing.page,
                  0,
                  AppSpacing.page,
                  AppSpacing.pageBottom,
                ),
                children: [
                  for (final entry in groups.entries)
                    if (entry.value.isNotEmpty) ...[
                      Padding(
                        padding: const EdgeInsets.only(
                          top: AppSpacing.gapM,
                          bottom: AppSpacing.gapXs,
                        ),
                        child: Text(entry.key, style: AppType.secondary(theme)),
                      ),
                      for (final item in entry.value)
                        _QuickCommandTile(
                          item: item,
                          onTap: () => _pick(context, item.command),
                        ),
                    ],
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  void _pick(BuildContext context, String command) {
    onPick(command);
    // 弹层由 showModalBottomSheet 打开，有可关闭的模态路由。
    final navigator = Navigator.of(context);
    if (navigator.canPop()) navigator.pop();
  }
}

class _QuickCommandTile extends StatelessWidget {
  const _QuickCommandTile({required this.item, required this.onTap});

  final QuickCommand item;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final theme = context.tTheme;
    return Material(
      color: Colors.transparent,
      child: InkWell(
        borderRadius: BorderRadius.circular(8),
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.symmetric(
            horizontal: AppSpacing.gapXs,
            vertical: AppSpacing.gapM,
          ),
          child: Row(
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(item.label, style: AppType.body(theme)),
                    const SizedBox(height: 2),
                    Text(
                      item.command,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: AppType.caption(
                        theme,
                      ).copyWith(fontFamily: 'monospace'),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: AppSpacing.gapS),
              Icon(TIcons.add, size: 18, color: theme.textColorSecondary),
            ],
          ),
        ),
      ),
    );
  }
}
