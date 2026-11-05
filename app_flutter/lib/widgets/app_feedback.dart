import 'package:tdesign_flutter/tdesign_flutter.dart';

/// 统一反馈入口，避免各页散写 Toast/Message。
class AppFeedback {
  AppFeedback._();

  static void toast(BuildContext context, String message) {
    TToast.showText(message, context: context);
  }

  static void success(BuildContext context, String message) {
    TToast.showSuccess(message, context: context);
  }

  static void error(BuildContext context, String message) {
    TToast.showFail(message, context: context);
  }

  /// 二次确认对话框，返回是否确认。
  static Future<bool> confirm(
    BuildContext context, {
    required String title,
    String? content,
    String confirmText = '确定',
    String cancelText = '取消',
    bool danger = false,
  }) async {
    final result = await TDialog.show<bool>(
      context,
      dialog: TDialog(
        title: Text(title),
        content: content == null ? null : Text(content),
        actions: [
          TDialogAction(
            child: Text(cancelText),
            result: false,
          ),
          TDialogAction(
            child: Text(confirmText),
            result: true,
            role: danger ? TDialogActionRole.destructive : TDialogActionRole.primary,
          ),
        ],
      ),
    );
    return result ?? false;
  }

  /// 加载中提示（阻塞式），返回关闭句柄。
  static String loading(BuildContext context, [String text = '加载中…']) {
    return TToast.showLoading(text: text, context: context);
  }

  static void dismissLoading(String id) {
    TToast.dismissToast(id);
  }
}
