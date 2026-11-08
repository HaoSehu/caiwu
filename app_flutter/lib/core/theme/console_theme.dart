import 'package:flutter/widgets.dart';

/// 终端类全屏页（SSH 终端、VNC 远程桌面）的深色配色。
///
/// 这两类是「沉浸式工作区」而不是普通业务页：内容本身是深色画面，
/// 外框必须跟着变深，否则浅色边框会把终端框成一个突兀的方块。
/// 唯一例外是品牌色：连接中、主按钮仍用品牌蓝，保证与全 App 一致。
class ConsoleColors {
  ConsoleColors._();

  /// 终端/桌面画布底色。
  static const Color canvas = Color(0xFF1F1F1F);

  /// VNC 桌面画布底色（比终端略冷，用来区分「远程桌面」与「终端」）。
  static const Color desktopCanvas = Color(0xFF1F1F24);

  /// 顶栏与工具条底色。
  static const Color bar = Color(0xFF2A2A31);

  /// 顶栏主文字/图标。
  static const Color barText = Color(0xFFE5E7EB);

  /// 顶栏次要文字（状态、提示）。
  static const Color barSubtle = Color(0xFFB0B0B8);

  /// 工具条按钮的分隔与描边。
  static const Color barDivider = Color(0xFF3A3A42);

  /// VNC 底部工具条底色（比顶栏略亮，与远程桌面画布区分）。
  static const Color toolBar = Color(0xFF35353D);

  /// 深色页面上的浮层遮罩（缩略图关闭、菜单背景）。
  static const Color scrim = Color(0x9E000000);
}
