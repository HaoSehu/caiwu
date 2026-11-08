import 'dart:async';

import 'package:go_router/go_router.dart';
import 'package:tdesign_flutter/tdesign_flutter.dart';

import '../../core/layout/responsive.dart';
import '../../core/net/app_services.dart';
import '../../core/theme/app_tokens.dart';
import '../../core/util/format.dart';
import '../../widgets/app_feedback.dart';
import '../../widgets/app_kit.dart';
import '../../widgets/data_state.dart';

/// 订单详情：订单信息组 + 进度（TDesign `TSteps`）+ 金额组 + 关联账单。
///
/// 所有字段使用 `TCellGroup` + `TCell`，金额右对齐并使用等宽数字，
/// 空字段自动隐藏。
/// 时间线依赖后端下发的 `created_at` / `paid_at` / `finished_at`：
/// 只有待支付或已支付订单展示（取消 / 退款订单的 `finished_at` 语义不同，不画时间线）。
class OrderDetailPage extends StatefulWidget {
  const OrderDetailPage({super.key, required this.orderId});

  final int orderId;

  @override
  State<OrderDetailPage> createState() => _OrderDetailPageState();
}

class _OrderDetailPageState extends State<OrderDetailPage> {
  Map<String, dynamic>? _order;

  /// 请求序号：旧响应不得覆盖新数据。
  int _seq = 0;
  bool _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    unawaited(_load(showLoading: true));
  }

  Future<void> _load({required bool showLoading}) async {
    final seq = ++_seq;
    final api = AppServices.of(context).finance;
    if (showLoading) {
      setState(() {
        _loading = true;
        _error = null;
      });
    }
    try {
      final order = await api.orderDetail(widget.orderId);
      if (!mounted || seq != _seq) return;
      setState(() {
        _order = order;
        _loading = false;
        _error = null;
      });
    } catch (error) {
      if (!mounted || seq != _seq) return;
      final message = AppServices.errorText(error);
      final hadData = _order != null;
      setState(() {
        _loading = false;
        if (!hadData) _error = message;
      });
      if (hadData) AppFeedback.error(context, message);
    }
  }

  @override
  Widget build(BuildContext context) {
    return AppScaffold(
      title: '订单详情',
      body: _order == null
          ? DataState(
              loading: _loading,
              error: _error,
              retry: () => unawaited(_load(showLoading: true)),
              child: const SizedBox.shrink(),
            )
          : RefreshIndicator(
              onRefresh: () => _load(showLoading: false),
              child: _buildContent(context),
            ),
    );
  }

  Widget _buildContent(BuildContext context) {
    final order = _order ?? const <String, dynamic>{};
    final invoice = mapOf(order['invoice']);
    final invoiceId = intOf(invoice, 'id');
    final status = intOf(order, 'status');
    final showTimeline = status == 0 || status == 1;
    final padding = context.pagePadding;
    // 区块间距：平板 32，手机 24。
    final sectionGap = AppSpacing.sectionGap(context.isTablet);

    final infoColumn = Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        _buildInfoGroup(context, order, invoice),
        if (showTimeline) ...[
          SizedBox(height: sectionGap),
          _buildTimelineGroup(context, order),
        ],
      ],
    );
    final amountColumn = Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        _buildAmountGroup(context, order),
        if (invoiceId > 0) ...[
          SizedBox(height: sectionGap),
          _buildInvoiceLink(context, invoice, invoiceId),
        ],
      ],
    );

    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: EdgeInsets.fromLTRB(
        padding,
        AppSpacing.gapM,
        padding,
        AppSpacing.pageBottom,
      ),
      children: [
        // 详情保持单栏：平板仅把「信息卡」与「金额卡」左右并排。
        if (context.isTablet)
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(child: infoColumn),
              SizedBox(width: sectionGap),
              Expanded(child: amountColumn),
            ],
          )
        else ...[
          infoColumn,
          SizedBox(height: sectionGap),
          amountColumn,
        ],
      ],
    );
  }

  /// 关联账单入口：`TCellGroup` 单行，点击进入账单详情。
  Widget _buildInvoiceLink(
    BuildContext context,
    Map<String, dynamic> invoice,
    int invoiceId,
  ) {
    return TCellGroup(
      cells: [
        TCell(
          prefix: Icon(
            TIcons.file,
            size: 22,
            color: context.tTheme.textColorSecondary,
          ),
          title: Text(
            [
              '关联账单',
              strOf(invoice, 'invoice_no'),
            ].where((text) => text.trim().isNotEmpty).join(' '),
          ),
          arrow: true,
          onTap: () => context.push('/finance/invoices/$invoiceId'),
        ),
      ],
    );
  }

  /// 订单信息组：订单号 + 状态 + 关键字段网格。
  Widget _buildInfoGroup(
    BuildContext context,
    Map<String, dynamic> order,
    Map<String, dynamic> invoice,
  ) {
    final theme = context.tTheme;
    final status = intOf(order, 'status');
    final gatewayLabel = strOf(order, 'gateway_label').isNotEmpty
        ? strOf(order, 'gateway_label')
        : strOf(order, 'channel_name');
    final fields =
        <(String, String)>[
              ('订单类型', strOf(order, 'type_label')),
              ('计费周期', _cycleLabel(strOf(order, 'billing_cycle'))),
              ('数量', '${intOf(order, 'quantity')}'),
              ('支付渠道', gatewayLabel),
              ('商品名称', strOf(order, 'product_name')),
              ('商品路径', strOf(order, 'product_full_path')),
              ('服务名称', strOf(order, 'service_name')),
              ('优惠券', strOf(order, 'coupon_name')),
              ('支付时间', formatDateTime(order['paid_at'])),
              ('完成时间', formatDateTime(order['finished_at'])),
              ('创建时间', formatDateTime(order['created_at'])),
            ]
            .where(
              (field) => field.$2.trim().isNotEmpty && field.$2.trim() != '--',
            )
            .toList();
    return TCellGroup(
      cells: [
        TCell(
          title: Text(
            strOf(order, 'order_no'),
            style: AppType.cardTitle(
              theme,
            ).copyWith(fontFeatures: const [FontFeature.tabularFigures()]),
          ),
          trailing: AppStatusTag(
            label: strOf(order, 'status_label'),
            preset: _statusPreset(status),
          ),
        ),
        for (final field in fields) _fieldCell(field.$1, field.$2),
      ],
    );
  }

  /// 订单进度：TDesign `TSteps` 垂直进度条。
  /// 创建 → 支付 → 完成，已完成节点由组件显示对勾，当前节点高亮。
  Widget _buildTimelineGroup(BuildContext context, Map<String, dynamic> order) {
    final theme = context.tTheme;
    final created = formatDateTime(order['created_at']);
    final paid = formatDateTime(order['paid_at']);
    final finished = formatDateTime(order['finished_at']);
    final status = intOf(order, 'status');

    var activeStep = 0;
    if (status == 1 && paid != '--') {
      activeStep = finished != '--' ? 2 : 1;
    }

    return TCellGroup(
      title: Text('订单进度', style: AppType.section(theme)),
      cells: [
        TCell(
          enableFeedback: false,
          subtitle: Padding(
            padding: const EdgeInsets.symmetric(vertical: AppSpacing.gapS),
            child: TSteps.progress(
              direction: TStepsDirection.vertical,
              value: activeStep,
              steps: [
                TStepsItemData(title: '创建', content: created),
                TStepsItemData(title: '支付', content: paid),
                TStepsItemData(title: '完成', content: finished),
              ],
            ),
          ),
        ),
      ],
    );
  }

  /// 金额组：明细右对齐，实付金额用 `AppType.stat` 突出。
  Widget _buildAmountGroup(BuildContext context, Map<String, dynamic> order) {
    final theme = context.tTheme;
    final memberDiscount =
        double.tryParse(strOf(order, 'member_discount_amount')) ?? 0;
    final discount = double.tryParse(strOf(order, 'discount')) ?? 0;
    return TCellGroup(
      title: Text('金额', style: AppType.section(theme)),
      cells: [
        _fieldCell('订单金额', formatMoneyText(order['amount']), mono: true),
        if (memberDiscount > 0)
          _fieldCell(
            '会员优惠',
            '-${formatMoneyText(memberDiscount)}',
            mono: true,
            color: theme.successColor,
          ),
        if (discount > 0)
          _fieldCell(
            '优惠',
            '-${formatMoneyText(discount)}',
            mono: true,
            color: theme.successColor,
          ),
        if (strOf(order, 'coupon_name').isNotEmpty)
          _fieldCell('优惠券', strOf(order, 'coupon_name')),
        TCell(
          title: Text('实付金额', style: AppType.secondary(theme)),
          note: Text(
            formatMoneyText(order['paid_amount']),
            style: AppType.amountLarge(theme),
          ),
        ),
      ],
    );
  }

  /// 详情字段行：左侧标签、右侧取值（TDesign Cell 的标准键值形态）。
  /// 金额类字段传 `mono: true` 使用等宽数字。
  TCell _fieldCell(
    String label,
    String value, {
    bool mono = false,
    Color? color,
  }) {
    final theme = context.tTheme;
    return TCell(
      title: Text(label, style: AppType.secondary(theme)),
      note: Text(
        value,
        style: mono
            ? AppType.money(theme, weight: FontWeight.w400, color: color)
            : AppType.body(theme),
      ),
    );
  }

  TTagColorPreset _statusPreset(int status) {
    switch (status) {
      case 1:
        return TTagColorPreset.success;
      case 4:
        return TTagColorPreset.defaultTheme;
      case 5:
        return TTagColorPreset.danger;
      default:
        return TTagColorPreset.warning;
    }
  }

  String _cycleLabel(String cycle) {
    return switch (cycle) {
      'monthly' => '月付',
      'quarterly' => '季付',
      'semiannually' => '半年付',
      'annually' => '年付',
      'biennially' => '两年付',
      'triennially' => '三年付',
      'one_time' => '一次性',
      _ => cycle,
    };
  }
}
