import 'package:go_router/go_router.dart';
import 'package:tdesign_flutter/tdesign_flutter.dart';

import '../../features/auth/login_page.dart';
import '../../features/auth/register_page.dart';
import '../../features/auth/forgot_password_page.dart';
import '../../features/shell/home_shell.dart';
import '../../features/home/home_page.dart';
import '../../features/services/services_page.dart';
import '../../features/services/service_detail_page.dart';
import '../../features/ssh/host_edit_page.dart';
import '../../features/ssh/host_list_page.dart';
import '../../features/ssh/session_manager_page.dart';
import '../../features/ssh/ssh_page.dart';
import '../../features/vnc/vnc_page.dart';
import '../../features/tickets/tickets_page.dart';
import '../../features/tickets/ticket_detail_page.dart';
import '../../features/tickets/ticket_create_page.dart';
import '../../features/finance/finance_page.dart';
import '../../features/finance/invoice_detail_page.dart';
import '../../features/finance/order_detail_page.dart';
import '../../features/finance/recharge_page.dart';
import '../../features/notices/notices_page.dart';
import '../../features/notices/notice_detail_page.dart';
import '../../features/profile/profile_page.dart';
import '../../core/store/session.dart';

/// 路由表。登录态守卫在 [createRouter] 中通过 [Session] 判断。
GoRouter createRouter(Session session) {
  return GoRouter(
    initialLocation: '/home',
    redirect: (context, state) {
      final loggedIn = session.isLoggedIn;
      final loggingIn = state.matchedLocation == '/login' ||
          state.matchedLocation == '/register' ||
          state.matchedLocation == '/forgot-password';
      if (!loggedIn && !loggingIn) return '/login';
      if (loggedIn && loggingIn) return '/home';
      return null;
    },
    refreshListenable: session,
    // 未知路径（如后端下发的网页端 /client/... 链接）不应出现红色错误屏。
    errorBuilder: (context, state) => Scaffold(
      appBar: AppBar(title: const Text('页面不存在')),
      body: Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text('无法打开 ${state.uri.path}', textAlign: TextAlign.center),
              const SizedBox(height: 16),
              FilledButton(
                onPressed: () => context.go('/home'),
                child: const Text('返回首页'),
              ),
            ],
          ),
        ),
      ),
    ),
    routes: [
      GoRoute(path: '/login', builder: (context, state) => const LoginPage()),
      GoRoute(path: '/register', builder: (context, state) => const RegisterPage()),
      GoRoute(
        path: '/forgot-password',
        builder: (context, state) => const ForgotPasswordPage(),
      ),
      StatefulShellRoute.indexedStack(
        builder: (context, state, shell) => HomeShell(shell: shell),
        branches: [
          StatefulShellBranch(routes: [
            GoRoute(path: '/home', builder: (context, state) => const HomePage()),
          ]),
          StatefulShellBranch(routes: [
            GoRoute(
              path: '/services',
              builder: (context, state) => const ServicesPage(),
              routes: [
                GoRoute(
                  path: ':id',
                  builder: (context, state) => ServiceDetailPage(
                    serviceId: int.parse(state.pathParameters['id']!),
                  ),
                ),
              ],
            ),
          ]),
          StatefulShellBranch(routes: [
            GoRoute(
              path: '/tickets',
              builder: (context, state) => const TicketsPage(),
              routes: [
                GoRoute(
                  path: 'create',
                  builder: (context, state) => const TicketCreatePage(),
                ),
                GoRoute(
                  path: ':id',
                  builder: (context, state) => TicketDetailPage(
                    ticketId: int.parse(state.pathParameters['id']!),
                  ),
                ),
              ],
            ),
          ]),
          StatefulShellBranch(routes: [
            GoRoute(path: '/profile', builder: (context, state) => const ProfilePage()),
          ]),
        ],
      ),
      // 独立 SSH 主机管理（不依赖 caiwu 服务）。
      GoRoute(
        path: '/ssh',
        builder: (context, state) => const SshHostListPage(),
        routes: [
          GoRoute(
            path: 'host/new',
            builder: (context, state) => const SshHostEditPage(),
          ),
          GoRoute(
            path: 'host/:id',
            builder: (context, state) => SshHostEditPage(
              hostId: state.pathParameters['id'],
            ),
          ),
          GoRoute(
            path: 'session/:hostId',
            builder: (context, state) => SshPage(
              // 主机模式的 serviceId 不参与连接，仅作占位。
              serviceId: 0,
              sshHostId: state.pathParameters['hostId'],
            ),
          ),
        ],
      ),
      GoRoute(
        path: '/sessions',
        builder: (context, state) => const SshSessionManagerPage(),
      ),
      // 非底部 Tab 的页面：从服务详情等入口进入。
      GoRoute(
        path: '/services/:id/ssh',
        builder: (context, state) => SshPage(
          serviceId: int.parse(state.pathParameters['id']!),
        ),
      ),
      GoRoute(
        path: '/services/:id/vnc',
        builder: (context, state) => VncPage(
          serviceId: int.parse(state.pathParameters['id']!),
        ),
      ),
      GoRoute(
        path: '/finance',
        builder: (context, state) => const FinancePage(),
        routes: [
          GoRoute(
            path: 'invoices/:id',
            builder: (context, state) => InvoiceDetailPage(
              invoiceId: int.parse(state.pathParameters['id']!),
            ),
          ),
          GoRoute(
            path: 'orders/:id',
            builder: (context, state) => OrderDetailPage(
              orderId: int.parse(state.pathParameters['id']!),
            ),
          ),
          GoRoute(
            path: 'recharge',
            builder: (context, state) => const RechargePage(),
          ),
        ],
      ),
      GoRoute(
        path: '/notices',
        builder: (context, state) => const NoticesPage(),
        routes: [
          GoRoute(
            path: ':id',
            builder: (context, state) => NoticeDetailPage(
              noticeId: int.parse(state.pathParameters['id']!),
            ),
          ),
        ],
      ),
    ],
  );
}
