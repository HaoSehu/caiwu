import 'dart:async';

import 'package:provider/provider.dart';
import 'package:tdesign_flutter/tdesign_flutter.dart';

import '../../core/layout/responsive.dart';
import '../../core/net/api_exception.dart';
import '../../core/net/app_services.dart';
import '../../core/router/router.dart';
import '../../core/store/session.dart';
import '../../core/theme/app_theme.dart';
import '../../features/ssh/host_store.dart';

/// 应用入口：恢复会话 → 挂载服务 → 路由。
class CaiwuApp extends StatefulWidget {
  const CaiwuApp({super.key});

  @override
  State<CaiwuApp> createState() => _CaiwuAppState();
}

class _CaiwuAppState extends State<CaiwuApp> {
  late final Session _session;
  late final AppServices _services;
  late final RouterConfig<Object> _router;

  @override
  void initState() {
    super.initState();
    _session = Session();
    _services = AppServices(
      session: _session,
      onUnauthorized: () {
        if (_session.isLoggedIn) {
          unawaited(_session.clear());
        }
      },
    );
    _router = createRouter(_session);
    unawaited(_bootstrap());
  }

  Future<void> _bootstrap() async {
    try {
      await _session.restore();
    } catch (_) {
      // 安全存储不可用（极少数设备）时按未登录处理。
      return;
    }
    if (!_session.isLoggedIn) return;
    // 启动时用 /auth/info 校验 token；401/403 清会话，网络异常保留本地会话。
    try {
      final info = await _services.auth.info();
      // 后端 /auth/info 的 data 就是用户对象本身（无 user 包装层）。
      final user = info['user'] is Map ? Map<String, dynamic>.from(info['user'] as Map) : info;
      await _session.updateUser(user);
    } on ApiException catch (error) {
      if (error.code == 40100 || error.code == 40300) {
        await _session.clear();
      }
    } catch (_) {
      // 网络波动时保留本地会话，由后续请求的 401 兜底。
    }
  }

  @override
  void dispose() {
    _session.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return MultiProvider(
      providers: [
        ChangeNotifierProvider<Session>.value(value: _session),
        ChangeNotifierProvider<SshHostStore>.value(value: _services.sshHosts),
        Provider<AppServices>.value(value: _services),
      ],
      child: MaterialApp.router(
        title: 'Caiwu 控制台',
        debugShowCheckedModeBanner: false,
        theme: AppTheme.lightTheme(),
        routerConfig: _router,
        builder: (context, child) {
          // 统一页面底色（DESIGN.md：页面底 #F5F7FB），并把窗口断点注入子树，
          // 供页面按手机/平板切换布局。
          final size = MediaQuery.sizeOf(context);
          return ColoredBox(
            color: context.tTheme.bgColorPage,
            child: WindowSizeProvider(
              size: Breakpoints.of(size.width),
              width: size.width,
              child: child ?? const SizedBox.shrink(),
            ),
          );
        },
      ),
    );
  }
}
