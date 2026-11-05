#!/usr/bin/env dart
// 防回退守卫：App 必须保持"原生渲染"，不得引入任何 WebView 依赖性。
//
// 与 frontend-user-v3-www/scripts/check-no-legacy-ui.mjs 同一意图：
// 在源码层面拦截会破坏产品红线（禁止网页嵌套）的依赖与调用。
//
// 用法：cd app_flutter && dart run tool/check_no_webview.dart
import 'dart:io';

const _forbiddenPackages = <String>[
  'webview_flutter',
  'flutter_inappwebview',
  'webviewx',
  'flutter_webview_plugin',
  'webview_cookie_manager',
  'flutter_html_iframe',
];

const _forbiddenImports = <String>[
  'package:webview_flutter/',
  'package:flutter_inappwebview/',
  'package:webviewx/',
  'package:flutter_webview_plugin/',
];

Future<void> main() async {
  final root = Directory.current;
  var failures = 0;

  // 1. pubspec 依赖检查
  final pubspec = File('${root.path}/pubspec.yaml');
  if (!pubspec.existsSync()) {
    stderr.writeln('未找到 pubspec.yaml，请在 app_flutter 目录运行');
    exit(2);
  }
  final pubspecText = pubspec.readAsStringSync();
  for (final package in _forbiddenPackages) {
    final pattern = RegExp('^\\s{2}$package\\s*:', multiLine: true);
    if (pattern.hasMatch(pubspecText)) {
      stderr.writeln('✗ pubspec.yaml 引入了禁用依赖：$package');
      failures++;
    }
  }

  // 2. 源码 import 检查
  final libDir = Directory('${root.path}/lib');
  if (libDir.existsSync()) {
    await for (final entity in libDir.list(recursive: true)) {
      if (entity is! File || !entity.path.endsWith('.dart')) continue;
      final content = entity.readAsStringSync();
      for (final forbidden in _forbiddenImports) {
        if (content.contains(forbidden)) {
          stderr.writeln('✗ ${entity.path} 引用了禁用包：$forbidden');
          failures++;
        }
      }
      // WebView 组件名（宽松匹配，避免误伤注释中的 "webview" 说明）
      if (RegExp(r'\b(WebViewWidget|InAppWebView|WebViewX)\s*\(').hasMatch(content)) {
        stderr.writeln('✗ ${entity.path} 使用了 WebView 组件');
        failures++;
      }
    }
  }

  if (failures == 0) {
    stdout.writeln('✓ 未发现 WebView 依赖或调用，"原生渲染"约束保持');
    exit(0);
  }
  stderr.writeln('共 $failures 处违规');
  exit(1);
}
