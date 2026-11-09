import 'dart:async';
import 'dart:convert';

import 'package:flutter/services.dart';
import 'package:provider/provider.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:tdesign_flutter/tdesign_flutter.dart';

import '../../core/layout/responsive.dart';
import '../../core/net/app_services.dart';
import '../../core/store/session.dart';
import '../../core/theme/app_tokens.dart';
import '../../core/util/format.dart';
import '../../core/util/payment_launch.dart';
import '../../widgets/app_feedback.dart';
import '../../widgets/app_kit.dart';
import '../../widgets/data_state.dart';

/// 账单详情：信息 → 商品 → 金额 → 支付明细，字段全部使用 TDesign
/// `TCellGroup` + `TCell`，应付金额用 `AppType.stat` 突出。
///
/// - 待支付账单可取消（`payment_options.can_cancel`）。
/// - 支付需要 `payment_options.payment_security.session_token`，且只有
///   `can_pay` 为真、状态为待支付（0）时才展示支付按钮；已支付 / 已取消 / 已退款
///   （1 / 4 / 5）不显示支付入口。
/// - 第三方渠道来自 `pay_methods`（余额与零元已剔除），同一插件下的支付宝与
///   微信是两条独立通道，各自带 `key` + `payment_type`。
/// - 支付单号与轮询令牌（`payment_no` + `poll_token`）来自下单响应，
///   必须保存后才能查询支付结果。
/// - 手机端点支付时按渠道直接唤起支付宝 / 微信 App，失败才回落浏览器。
/// - 支付成功在页面顶部展示可关闭的 TDesign `TNoticeBar` 成功条，不只依赖瞬时 toast。
class InvoiceDetailPage extends StatefulWidget {
  const InvoiceDetailPage({super.key, required this.invoiceId});

  final int invoiceId;

  @override
  State<InvoiceDetailPage> createState() => _InvoiceDetailPageState();
}

class _InvoiceDetailPageState extends State<InvoiceDetailPage> {
  Map<String, dynamic>? _invoice;

  /// 请求序号：旧响应不得覆盖新数据（支付后刷新与下拉刷新并发时兜底）。
  int _seq = 0;
  bool _loading = true;
  String? _error;

  /// 正在发起的支付方式：`balance` 或第三方 `option_key`；
  /// 查询与取消共用 [_checking] / [_cancelling]。
  String? _payingMethod;
  bool _checking = false;
  bool _cancelling = false;

  /// 支付成功后顶部成功条的展示状态（可手动关闭）。
  bool _showPaidBanner = false;

  /// 已发起的在线支付凭证：发起后才展示「我已完成支付」查询入口。
  String _paymentNo = '';
  String _pollToken = '';

  /// 已发起通道的查询参数：`gateway`（插件编码）。
  String _payGateway = '';

  /// 本次下发的支付链接：微信支付只能在微信内完成，页面提供复制入口。
  String _payUrl = '';

  /// 本次下发链接的渠道：只有微信需要用户复制链接到微信内打开，
  /// 支付宝能直接唤起 App，给它挂复制按钮只是干扰。
  PaymentChannel _payChannel = PaymentChannel.other;

  /// 轮询凭证持久化 key：进程被回收后「我已完成支付」入口不能丢（M-28②）。
  /// 与 Web 控制台同口径，按账单 id 区分；SharedPreferences 是明文存储，
  /// poll_token 本身是短 TTL 的查单凭证，不含支付能力，可接受。
  static const _pollStoragePrefix = 'finance.invoice_poll.';

  bool _restoredPoll = false;

  Map<String, dynamic> get _basic => mapOf(_invoice?['basic']);
  Map<String, dynamic> get _display => mapOf(_invoice?['display']);
  Map<String, dynamic> get _financial => mapOf(_invoice?['financial']);
  Map<String, dynamic> get _paymentOptions =>
      mapOf(_invoice?['payment_options']);
  Map<String, dynamic> get _security =>
      mapOf(_paymentOptions['payment_security']);
  Map<String, dynamic> get _paymentChain => mapOf(_invoice?['payment_chain']);
  Map<String, dynamic> get _paymentSummary =>
      mapOf(_paymentChain['payment_summary']);

  /// 第三方支付渠道：后端 `payment_options.pay_methods` 剔除
  /// 「余额支付 / 确认支付」后的列表。同一插件开启支付宝与微信时会返回两条，
  /// 各自带 `key` + `payment_type`。
  List<Map<String, dynamic>> get _payMethods => [
    for (final item in listOf(_paymentOptions['pay_methods']))
      if (!const {'balance', 'free'}.contains(strOf(item, 'key'))) item,
  ];

  /// 渠道唯一键：优先 `option_key`，回落 `key:payment_type`。
  String _methodKey(Map<String, dynamic> method) {
    final optionKey = strOf(method, 'option_key').trim();
    if (optionKey.isNotEmpty) return optionKey;
    final gateway = strOf(method, 'key').trim();
    final paymentType = strOf(method, 'payment_type').trim();
    return paymentType.isEmpty ? gateway : '$gateway:$paymentType';
  }

  /// 渠道展示名：只取 `channel_name` / `label`，不得回落插件名 `name`。
  String _methodLabel(Map<String, dynamic> method) {
    for (final key in const ['channel_name', 'label', 'gateway_label']) {
      final value = strOf(method, key).trim();
      if (value.isNotEmpty) return value;
    }
    return strOf(method, 'key').trim();
  }

  int get _status => intOf(_basic, 'status');
  String get _sessionToken => strOf(_security, 'session_token');

  bool get _canPay =>
      _status == 0 && _security['can_pay'] == true && _sessionToken.isNotEmpty;

  bool get _busy => _payingMethod != null || _checking || _cancelling;

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
      final invoice = await api.invoiceDetail(widget.invoiceId);
      if (!mounted || seq != _seq) return;
      setState(() {
        _invoice = invoice;
        _loading = false;
        _error = null;
        // 账单已支付/已取消后，之前发起的支付链接就失效了：不清掉会让底部
        // 「复制支付链接」按钮残留并复制出一张已经用不了的链接。
        if (!_canPay) {
          _payUrl = '';
          _paymentNo = '';
          _pollToken = '';
        }
      });
      if (!_canPay) {
        await _clearPollCredentials();
      }
      // 首次加载成功后恢复上次未完成的支付凭证（M-28②）
      if (!_restoredPoll && _canPay) {
        _restoredPoll = true;
        await _restorePollCredentials();
      }
    } catch (error) {
      if (!mounted || seq != _seq) return;
      final message = AppServices.errorText(error);
      final hadData = _invoice != null;
      setState(() {
        _loading = false;
        if (!hadData) _error = message;
      });
      if (hadData) AppFeedback.error(context, message);
    }
  }

  String get _pollStorageKey => '$_pollStoragePrefix${widget.invoiceId}';

  /// 持久化轮询凭证：App 被系统回收后重新进入详情页仍能继续查询支付结果。
  Future<void> _persistPollCredentials() async {
    if (_paymentNo.isEmpty || _pollToken.isEmpty) return;
    try {
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString(
        _pollStorageKey,
        jsonEncode({
          'payment_no': _paymentNo,
          'poll_token': _pollToken,
          'gateway': _payGateway,
          'pay_url': _payUrl,
          'channel': _payChannel.name,
        }),
      );
    } catch (_) {
      // 存储不可用时静默降级：只失去中断恢复能力，不影响本次支付
    }
  }

  Future<void> _clearPollCredentials() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      await prefs.remove(_pollStorageKey);
    } catch (_) {
      // 忽略存储异常
    }
  }

  Future<void> _restorePollCredentials() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final raw = prefs.getString(_pollStorageKey);
      if (raw == null || raw.isEmpty) return;
      final decoded = jsonDecode(raw);
      if (decoded is! Map) return;
      final paymentNo = '${decoded['payment_no'] ?? ''}';
      final pollToken = '${decoded['poll_token'] ?? ''}';
      if (paymentNo.isEmpty || pollToken.isEmpty) return;
      if (!mounted) return;
      setState(() {
        _paymentNo = paymentNo;
        _pollToken = pollToken;
        _payGateway = '${decoded['gateway'] ?? ''}';
        _payUrl = '${decoded['pay_url'] ?? ''}';
        _payChannel = PaymentChannel.values.firstWhere(
          (value) => value.name == '${decoded['channel'] ?? ''}',
          orElse: () => detectPaymentChannel(gateway: _payGateway),
        );
      });
    } catch (_) {
      // 解析失败视为无存档
    }
  }

  Future<void> _payByBalance() async {
    if (_busy || !_canPay) return;
    // 0 元账单同样走余额收口但不扣款，文案必须说清楚，否则用户会以为要扣钱。
    final payableAmount =
        double.tryParse(strOf(_financial, 'payable_amount')) ?? 0;
    final confirmed = await AppFeedback.confirm(
      context,
      title: '确认使用余额支付？',
      content: payableAmount > 0
          ? '应付 ${formatMoneyText(payableAmount)}，支付后立即生效'
          : '该账单金额为 0 元，确认后直接完成账单，不会扣减账户余额',
    );
    if (!confirmed || !mounted) return;
    setState(() => _payingMethod = 'balance');
    final services = AppServices.of(context);
    final session = context.read<Session>();
    try {
      final result = await services.finance.payInvoiceByBalance(
        widget.invoiceId,
        paymentSessionToken: _sessionToken,
      );
      if (!mounted) return;
      final cashBalance = strOf(result, 'cash_balance');
      final user = session.user;
      if (cashBalance.isNotEmpty && user != null) {
        await session.updateUser({...user, 'cash_balance': cashBalance});
      }
      if (!mounted) return;
      setState(() => _showPaidBanner = true);
      AppFeedback.success(context, '支付成功');
      await _load(showLoading: false);
    } catch (error) {
      if (mounted) AppFeedback.error(context, AppServices.errorText(error));
    } finally {
      if (mounted) setState(() => _payingMethod = null);
    }
  }

  /// 第三方渠道支付：下单拿 `qr_code` 后按渠道直接唤起支付宝 / 微信 App。
  ///
  /// 下单响应的 `payment_no` + `poll_token` 是查询支付结果的前置条件，
  /// 必须无论拉起成功与否都保存下来。
  Future<void> _payByGateway(Map<String, dynamic> method) async {
    if (_busy || !_canPay) return;
    final gateway = strOf(method, 'key').trim();
    final paymentType = strOf(method, 'payment_type').trim();
    final channelName = _methodLabel(method);
    final methodKey = _methodKey(method);
    setState(() => _payingMethod = methodKey);
    final api = AppServices.of(context).finance;
    try {
      final result = await api.payInvoiceByAlipay(
        widget.invoiceId,
        paymentSessionToken: _sessionToken,
        gateway: gateway,
        paymentType: paymentType,
      );
      final url = _pickUrl(result);
      // 后端查单要求 payment_no + poll_token，必须从下单响应保存下来。
      final paymentNo = strOf(result, 'payment_no');
      final pollToken = strOf(result, 'poll_token');
      final channel = detectPaymentChannel(
        gateway: gateway,
        paymentType: paymentType,
        channelName: channelName,
      );
      if (!mounted) return;
      setState(() {
        _paymentNo = paymentNo;
        _pollToken = pollToken;
        _payGateway = gateway;
        _payUrl = url;
        _payChannel = channel;
      });
      await _persistPollCredentials();
      if (!mounted) return;
      if (url.isEmpty) {
        AppFeedback.toast(context, '已创建支付单，请在支付完成后点击「我已完成支付」');
        return;
      }
      // 微信 https 收银台在微信外打开必然失败：不拉起，直接引导复制到微信内（M-28③）
      if (channel == PaymentChannel.wechat && !canLaunchWechatUrl(url)) {
        AppFeedback.toast(context, '微信支付只能在微信内完成：请点「复制支付链接」，粘贴到微信（可发给文件传输助手）后打开');
        return;
      }
      final opened = await launchPaymentUrl(url, channel: channel);
      if (!opened && mounted) {
        if (channel == PaymentChannel.wechat) {
          // 微信没有可用的外部唤起协议：提示复制链接到微信内打开，比「打不开」可操作。
          AppFeedback.toast(context, '微信支付需要在微信内完成，请复制链接到微信打开');
        } else {
          AppFeedback.toast(context, '无法打开支付页面，请在浏览器中完成支付');
        }
      }
    } catch (error) {
      if (mounted) AppFeedback.error(context, AppServices.errorText(error));
    } finally {
      if (mounted) setState(() => _payingMethod = null);
    }
  }

  /// 微信支付只能在微信内完成，而微信不开放外部唤起协议：把网关下发的
  /// https 收银台链接交给用户复制到微信（可发给文件传输助手）后打开。
  Future<void> _copyPayUrl() async {
    if (_payUrl.isEmpty) return;
    await Clipboard.setData(ClipboardData(text: _payUrl));
    if (mounted) AppFeedback.success(context, '支付链接已复制，请在微信中打开');
  }

  Future<void> _checkGatewayStatus() async {
    if (_busy) return;
    if (_paymentNo.isEmpty || _pollToken.isEmpty) {
      AppFeedback.toast(context, '请先点击支付方式发起支付后再查询');
      return;
    }
    setState(() => _checking = true);
    final api = AppServices.of(context).finance;
    try {
      final result = await api.alipayStatus(
        widget.invoiceId,
        paymentNo: _paymentNo,
        pollToken: _pollToken,
        gateway: _payGateway,
      );
      if (!mounted) return;
      final paid =
          result['paid'] == true ||
          intOf(result, 'status') == 1 ||
          strOf(result, 'payment_status') == 'paid';
      if (!paid) {
        AppFeedback.toast(context, '暂未查询到支付结果，请稍后再试');
        return;
      }
      setState(() => _showPaidBanner = true);
      AppFeedback.success(context, '支付成功');
      await _clearPollCredentials();
      await _load(showLoading: false);
    } catch (error) {
      if (mounted) AppFeedback.error(context, AppServices.errorText(error));
    } finally {
      if (mounted) setState(() => _checking = false);
    }
  }

  Future<void> _cancelInvoice() async {
    if (_busy) return;
    final confirmed = await AppFeedback.confirm(
      context,
      title: '取消账单',
      content: '取消后该账单将无法继续支付，确定取消吗',
      confirmText: '取消账单',
      danger: true,
    );
    if (!confirmed || !mounted) return;
    setState(() => _cancelling = true);
    final api = AppServices.of(context).finance;
    try {
      await api.cancelInvoice(widget.invoiceId);
      if (!mounted) return;
      AppFeedback.success(context, '账单已取消');
      await _load(showLoading: false);
    } catch (error) {
      if (mounted) AppFeedback.error(context, AppServices.errorText(error));
    } finally {
      if (mounted) setState(() => _cancelling = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final loaded = !_loading && _error == null && _invoice != null;
    return AppScaffold(
      title: '账单详情',
      body: _buildBody(context),
      bottomBar: loaded && _hasActions ? _buildActionBar(context) : null,
    );
  }

  /// 无支付、无取消入口时不渲染底部操作区（避免空白条）。
  bool get _hasActions => _canPay || _paymentOptions['can_cancel'] == true;

  Widget _buildBody(BuildContext context) {
    if (_invoice == null) {
      return DataState(
        loading: _loading,
        error: _error,
        retry: () => unawaited(_load(showLoading: true)),
        child: const SizedBox.shrink(),
      );
    }
    final padding = context.pagePadding;
    // 区块间距：平板 32，手机 24。
    final sectionGap = AppSpacing.sectionGap(context.isTablet);
    return RefreshIndicator(
      onRefresh: () => _load(showLoading: false),
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: EdgeInsets.fromLTRB(
          padding,
          AppSpacing.gapM,
          padding,
          AppSpacing.pageBottom,
        ),
        children: [
          if (_showPaidBanner) ...[
            _buildPaidBanner(context),
            SizedBox(height: sectionGap),
          ],
          // 详情保持单栏：平板仅把「信息卡」与「金额卡」左右并排。
          if (context.isTablet)
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      _buildInfoGroup(context),
                      if (_productItems.isNotEmpty) ...[
                        SizedBox(height: sectionGap),
                        _buildProductGroup(context),
                      ],
                    ],
                  ),
                ),
                SizedBox(width: sectionGap),
                Expanded(child: _buildAmountGroup(context)),
              ],
            )
          else ...[
            _buildInfoGroup(context),
            SizedBox(height: sectionGap),
            _buildProductGroup(context),
            SizedBox(height: sectionGap),
            _buildAmountGroup(context),
          ],
          if (_hasPaymentChain) ...[
            SizedBox(height: sectionGap),
            _buildPaymentChainGroup(context),
          ],
        ],
      ),
    );
  }

  /// 支付成功条：TDesign `TNoticeBar`（success 态），可关闭。
  Widget _buildPaidBanner(BuildContext context) {
    return TNoticeBar(
      status: TNoticeBarStatus.success,
      content: '支付成功，账单已结清',
      operation: GestureDetector(
        behavior: HitTestBehavior.opaque,
        onTap: () => setState(() => _showPaidBanner = false),
        child: Semantics(
          button: true,
          label: '关闭提示',
          child: Padding(
            padding: const EdgeInsets.all(4),
            child: Icon(
              TIcons.close,
              size: 18,
              color: context.tTheme.successColor,
            ),
          ),
        ),
      ),
    );
  }

  /// 信息组：账单号 + 状态 + 关键字段。
  Widget _buildInfoGroup(BuildContext context) {
    final theme = context.tTheme;
    return TCellGroup(
      cells: [
        TCell(
          title: Text(
            strOf(_basic, 'invoice_no'),
            style: AppType.cardTitle(
              theme,
            ).copyWith(fontFeatures: const [FontFeature.tabularFigures()]),
          ),
          trailing: AppStatusTag(
            label: strOf(_basic, 'status_label'),
            preset: _statusPreset(_status),
          ),
        ),
        _fieldCell('账单类型', strOf(_basic, 'type_label')),
        _fieldCell('计费周期', _cycleLabel(strOf(_basic, 'billing_cycle'))),
        _fieldCell('数量', '${intOf(_basic, 'quantity')}'),
        _fieldCell('到期日期', formatDateTime(_basic['due_date'])),
        _fieldCell(
          '创建时间',
          formatDateTime(mapOf(_invoice?['timestamps'])['created_at']),
        ),
      ],
    );
  }

  /// 商品链路：`product_full_path` 是唯一真源（如「云服务器/香港/三网精品/
  /// gscs-2vcpu-2gib」），依次回落分组名拼接、组合名、展示名、规格，
  /// 保证历史账单也能拼出完整链路。
  String get _productPath {
    for (final key in const [
      'product_full_path',
      'combined_display_name',
      'product_display_name',
      'product_spec_display',
    ]) {
      final value = strOf(_display, key).trim();
      if (value.isNotEmpty && value != '--') return value;
    }
    return '';
  }

  /// 商品信息字段：账单只对应一个商品，只展示一行完整链路，
  /// 不得把组合名与规格拆成多行重复同一个规格串。
  List<(String, String)> get _productItems => <(String, String)>[
    ('商品链路', _productPath),
  ].where((item) => item.$2.trim().isNotEmpty).toList();

  bool get _hasPaymentChain => listOf(_paymentChain['payments']).isNotEmpty;

  /// 商品信息组：只在有字段时展示，避免出现空组。
  Widget _buildProductGroup(BuildContext context) {
    final theme = context.tTheme;
    final items = _productItems;
    if (items.isEmpty) return const SizedBox.shrink();
    return TCellGroup(
      title: Text('商品信息', style: AppType.section(theme)),
      cells: [for (final item in items) _fieldCell(item.$1, item.$2)],
    );
  }

  /// 金额组：明细右对齐，应付金额用 `AppType.stat` 品牌色突出。
  /// 已支付状态补充支付时间与渠道，让用户回看时知道钱是怎么付的。
  ///
  /// 口径：后端 `financial.amount` 是**已扣优惠后的应付合计**（账单表 amount 即净额），
  /// 因此「原价」只能由 `amount + 会员优惠 + 券优惠` 反推，行与行之间必须自洽：
  /// 原价 − 各项优惠 = 合计，合计 − 已付 = 应付。
  Widget _buildAmountGroup(BuildContext context) {
    final theme = context.tTheme;
    final memberDiscount =
        double.tryParse(strOf(_display, 'member_discount_amount')) ?? 0;
    final discount = double.tryParse(strOf(_financial, 'discount')) ?? 0;
    final amount = double.tryParse(strOf(_financial, 'amount')) ?? 0;
    final originalAmount = amount + memberDiscount + discount;
    final hasDiscount = memberDiscount > 0 || discount > 0;
    final paidAt = strOf(_financial, 'paid_at');
    final couponName = strOf(_display, 'coupon_name').trim();
    final channel = strOf(_paymentSummary, 'channel_name').isNotEmpty
        ? strOf(_paymentSummary, 'channel_name')
        : strOf(_paymentSummary, 'gateway_label');
    final paid = _status == 1 || _status == 2;
    return TCellGroup(
      title: Text('金额', style: AppType.section(theme)),
      cells: [
        if (hasDiscount)
          _fieldCell('原价', mono: true, formatMoneyText(originalAmount)),
        if (couponName.isNotEmpty && couponName != '--')
          _fieldCell('优惠券', couponName),
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
        // amount 是净额，标签必须是「合计」（扣优惠后），不能叫「账单金额」让人误以为还能再减
        _fieldCell('合计', mono: true, formatMoneyText(amount)),
        _fieldCell(
          '已付金额',
          mono: true,
          formatMoneyText(_financial['paid_amount']),
        ),
        if (paid && paidAt.isNotEmpty)
          _fieldCell('支付时间', formatDateTime(paidAt)),
        if (paid && channel.isNotEmpty) _fieldCell('支付渠道', channel),
        TCell(
          title: Text('应付金额', style: AppType.secondary(theme)),
          note: Text(
            formatMoneyText(_financial['payable_amount']),
            style: AppType.amountDisplay(theme, color: theme.brandColor),
          ),
        ),
      ],
    );
  }

  /// 支付明细组：无记录时不渲染。
  Widget _buildPaymentChainGroup(BuildContext context) {
    final theme = context.tTheme;
    final payments = listOf(_paymentChain['payments']);
    if (payments.isEmpty) return const SizedBox.shrink();
    return TCellGroup(
      title: Text('支付明细', style: AppType.section(theme)),
      cells: [
        for (final payment in payments)
          TCell(
            title: Text(
              strOf(payment, 'payment_no'),
              style: AppType.body(
                theme,
              ).copyWith(fontFeatures: const [FontFeature.tabularFigures()]),
            ),
            subtitle: Text(
              [
                strOf(payment, 'channel_name'),
                strOf(payment, 'status_label'),
                formatDateTime(payment['paid_at']),
              ].where((text) => text.trim().isNotEmpty).join(' · '),
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: AppType.caption(theme),
            ),
            note: Text(
              formatMoneyText(payment['amount']),
              style: AppType.money(theme),
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

  /// 支付操作区（固定底部）：余额支付为主（fill primary），第三方渠道为次
  /// （outline），每行最多两个按钮；取消账单与「我已完成支付」是文字按钮。
  /// 支付中按钮显示 loading 且全部禁用；平板时整组按钮限宽 360 靠右。
  Widget _buildActionBar(BuildContext context) {
    final canCancel = _paymentOptions['can_cancel'] == true;
    final methods = _payMethods;
    final showGatewayCheck = _canPay && _pollToken.isNotEmpty;
    if (!_canPay && !canCancel && !showGatewayCheck) {
      return const SizedBox.shrink();
    }
    final buttons = <Widget>[
      if (_canPay)
        TButton(
          size: TButtonSize.large,
          colorPreset: TButtonColorPreset.primary,
          onPressed: _busy ? null : _payByBalance,
          child: _payingMethod == 'balance'
              ? const Text('支付中…')
              : const Text('余额支付'),
        ),
      if (_canPay)
        for (final method in methods)
          TButton(
            size: TButtonSize.large,
            variant: TButtonVariant.outline,
            colorPreset: TButtonColorPreset.primary,
            onPressed: _busy ? null : () => _payByGateway(method),
            child: Text(
              _payingMethod == _methodKey(method)
                  ? '拉起中…'
                  : _methodLabel(method),
            ),
          ),
    ];
    final bar = Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        for (var i = 0; i < buttons.length; i += 2) ...[
          if (i > 0) const SizedBox(height: AppSpacing.gapS),
          Row(
            children: [
              Expanded(child: buttons[i]),
              if (i + 1 < buttons.length) ...[
                const SizedBox(width: AppSpacing.gapS),
                Expanded(child: buttons[i + 1]),
              ],
            ],
          ),
        ],
        if (_canPay && (canCancel || showGatewayCheck))
          const SizedBox(height: AppSpacing.gapS),
        if (canCancel || showGatewayCheck)
          Row(
            children: [
              if (canCancel)
                TButton(
                  size: TButtonSize.small,
                  variant: TButtonVariant.text,
                  colorPreset: TButtonColorPreset.danger,
                  onPressed: _busy ? null : _cancelInvoice,
                  child: Text(_cancelling ? '取消中…' : '取消账单'),
                ),
              const Spacer(),
              // 只有微信链接需要用户搬到微信内打开；支付宝可直接唤起 App，
              // 给它挂复制按钮既是干扰，也会在窄屏上把三个文字按钮挤在一行。
              if (_payUrl.isNotEmpty && _payChannel == PaymentChannel.wechat)
                TButton(
                  size: TButtonSize.small,
                  variant: TButtonVariant.text,
                  colorPreset: TButtonColorPreset.primary,
                  onPressed: _copyPayUrl,
                  child: const Text('复制支付链接'),
                ),
              if (showGatewayCheck)
                TButton(
                  size: TButtonSize.small,
                  variant: TButtonVariant.text,
                  colorPreset: TButtonColorPreset.primary,
                  onPressed: _busy ? null : _checkGatewayStatus,
                  child: Text(_checking ? '查询中…' : '我已完成支付'),
                ),
            ],
          ),
      ],
    );
    if (!context.isTablet) return bar;
    return Align(
      alignment: Alignment.centerRight,
      child: SizedBox(width: 360, child: bar),
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
        // 0 待支付 / 2 部分已付
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

  String _pickUrl(Map<String, dynamic> map) {
    for (final key in const ['pay_url', 'url', 'qr_code', 'qrcode']) {
      final value = strOf(map, key).trim();
      if (value.isNotEmpty) return value;
    }
    return '';
  }
}
