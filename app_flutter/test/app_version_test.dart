import 'dart:io';

import 'package:flutter_test/flutter_test.dart';

/// 版本号守门：界面展示的版本必须与 pubspec.yaml 一致。
///
/// 此前出现过分歧：pubspec 是 0.4.1，而「我的」页硬编码显示 0.2.1。
/// 这里直接读文件比对，避免再次漂移。
void main() {
  test('展示版本与 pubspec.yaml 一致', () {
    final pubspec = File('pubspec.yaml').readAsStringSync();
    final match = RegExp(r'^version:\s*([0-9]+\.[0-9]+\.[0-9]+)',
            multiLine: true)
        .firstMatch(pubspec);
    expect(match, isNotNull, reason: 'pubspec.yaml 缺少 version 字段');
    final pubspecVersion = match!.group(1)!;

    final profile =
        File('lib/features/profile/profile_page.dart').readAsStringSync();
    final shown = RegExp(r"_appVersion\s*=\s*'([^']+)'").firstMatch(profile);
    expect(shown, isNotNull, reason: 'profile_page 未定义 _appVersion');
    expect(shown!.group(1), pubspecVersion,
        reason: '界面显示的版本 ${shown.group(1)} 与 pubspec 的 '
            '$pubspecVersion 不一致');
  });
}
