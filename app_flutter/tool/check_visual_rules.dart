#!/usr/bin/env dart
// 防回退守卫：App 的视觉基调是「平实的移动端列表流」。
//
// 与 tool/check_no_webview.dart 同一意图：把已经确认的视觉约束固化成可执行检查，
// 而不是只写在 docs/DESIGN.md 里等下一次改版再犯。
//
// 用法：cd app_flutter && dart run tool/check_visual_rules.dart
import 'dart:io';

/// 业务页面里禁止出现的装饰性写法（出现即为模板化/营销感回流）。
///
/// 例外：`service_detail_page` 的监控折线图用 `LinearGradient` 做面积填充，
/// 属数据可视化而非装饰，按文件+行豁免。
const _allowedGradients = <String>{
  'lib/features/services/service_detail_page.dart',
};

/// 这些目录之外的文件（core / widgets）允许定义阴影与渐变的基础能力。
const _paletteFiles = <String>{
  'lib/core/theme/app_theme.dart',
  'lib/core/theme/app_tokens.dart',
  'lib/core/theme/console_theme.dart',
};

const _forbiddenInPages = <String, String>{
  'LinearGradient': '页面内不得使用渐变（头区横幅/装饰光斑）',
  'RadialGradient': '页面内不得使用径向渐变（发光/光斑）',
  'SweepGradient': '页面内不得使用扫描渐变',
  'BoxShadow': '页面内不得加投影（白卡靠描边与底色区分，不做浮起卡片）',
  'BackdropFilter': '不得使用玻璃拟态',
};

/// 页面骨架里不得再出现「品牌色整块背景」的写法。
const _forbiddenPatterns = <String, String>{
  r'color:\s*theme\.brandColor,\s*\n\s*child:': '不得用品牌色整块铺底做头区横幅',
};

Future<void> main() async {
  final root = Directory.current;
  final libDir = Directory('${root.path}/lib');
  if (!libDir.existsSync()) {
    stderr.writeln('未找到 lib/，请在 app_flutter 目录运行');
    exit(2);
  }

  var failures = 0;

  final files = libDir
      .listSync(recursive: true)
      .whereType<File>()
      .where((file) => file.path.endsWith('.dart'))
      .toList()
    ..sort((a, b) => a.path.compareTo(b.path));

  for (final file in files) {
    final relative = file.path
        .replaceAll('\\', '/')
        .split('/lib/')
        .last
        .replaceFirst(RegExp(r'^'), 'lib/');
    final inPages = relative.startsWith('lib/features/');
    if (!inPages) continue;
    if (_paletteFiles.contains(relative)) continue;

    final lines = file.readAsLinesSync();
    for (var index = 0; index < lines.length; index++) {
      final line = lines[index];
      if (line.trimLeft().startsWith('//')) continue;

      for (final entry in _forbiddenInPages.entries) {
        if (!line.contains(entry.key)) continue;
        if (entry.key == 'LinearGradient' &&
            _allowedGradients.contains(relative)) {
          continue;
        }
        failures++;
        stderr.writeln(
          '✗ $relative:${index + 1} 使用了 ${entry.key} —— ${entry.value}',
        );
      }
    }

    final content = file.readAsStringSync();
    for (final entry in _forbiddenPatterns.entries) {
      if (RegExp(entry.key, multiLine: true).hasMatch(content)) {
        failures++;
        stderr.writeln('✗ $relative —— ${entry.value}');
      }
    }
  }

  // 深色控制台「外框」配色必须集中在 ConsoleColors，不得各页重新定义。
  // 只认顶栏/画布/工具条这几档；终端 ANSI 调色板（cursor/black/red…）
  // 属于终端内容本身，允许就地定义。
  final consoleHex = RegExp(r'0xFF(1F1F24|2A2A31|35353D)');
  for (final file in files) {
    final relative = file.path.replaceAll('\\', '/').split('/lib/').last;
    if (relative == 'core/theme/console_theme.dart') continue;
    final lines = file.readAsLinesSync();
    for (var index = 0; index < lines.length; index++) {
      if (consoleHex.hasMatch(lines[index])) {
        failures++;
        stderr.writeln(
          '✗ lib/$relative:${index + 1} 重复定义了深色控制台色值 —— '
          '请改用 ConsoleColors',
        );
      }
    }
  }

  if (failures > 0) {
    stderr.writeln('\n共 $failures 处违反视觉基线，见 docs/DESIGN.md §2「App 端移动视觉补充」');
    exit(1);
  }
  stdout.writeln('✓ 视觉基线检查通过：无渐变/投影/玻璃拟态回流，深色令牌未重复定义');
}
