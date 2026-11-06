import 'dart:async';

import 'package:flutter/services.dart';
import 'package:provider/provider.dart';
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

/// 账户充值：余额一行 + 金额快捷胶囊 + 自定义金额 + 支付方式单选 + 底部固定提交。
///
/// - 金额范围 1–50000，最多两位小数（与后端 `amount: min:1|max:50000|decimal:0,2` 一致）。
/// - 自定义输入实时千分位格式化，非法输入即时在输入框下方提示。
/// - 快捷金额与自定义金额互斥：点快捷金额清空输入，输入自定义金额取消快捷选中。
/// - 支付方式来自 `finance.rechargeGateways()`，兼容 `gateways` / `list` / `methods`
///   三种返回键；为空时回落到「支付宝 / alipay」。每项使用 TDesign
///   `TCell` + `TRadio` 呈现，选中态由 `TRadio` 官方指示器表达。
/// - 同一插件可能同时开启支付宝与微信（`key` 相同、`payment_type` 不同），
///   选中值用 `option_key` 保证两条通道互不覆盖，提交时同时下发
///   `gateway` 与 `payment_type`。
/// - 提交成功后展示「支付单已创建」提示条与信息组，并提供刷新余额入口。
///   手机端按渠道直接唤起支付宝 / 微信 App。
class RechargePage extends StatefulWidget {
  const RechargePage({super.key});

  @override
  State<RechargePage> createState() => _RechargePageState();
}

/// 已创建的充值支付单（用于提交后展示）。
class _CreatedRecharge {
  const _CreatedRecharge({
    required this.amount,
    required this.gatewayLabel,
    required this.payUrl,
    required this.channel,
  });

  final String amount;
  final String gatewayLabel;

  /// 网关下发的支付链接；微信支付需要它在微信内打开，页面提供复制入口。
  final String payUrl;

  /// 本次链接的渠道：只有微信需要搬运到微信内，支付宝可直接唤起 App。
  final PaymentChannel channel;
}

class _RechargePageState extends State<RechargePage> {
  static const List<String> _quickAmounts = [
    '50',
    '100',
    '200',
    '500',
    '1000',
    '2000',
  ];

  static const List<Map<String, String>> _fallbackGateways = [
    {'value': 'alipay', 'label': '支付宝', 'gateway': 'alipay', 'paymentType': ''},
  ];

  final TextEditingController _amountController = TextEditingController();

  /// 当前选中的快捷金额；自定义输入有内容时为 null（两者互斥）。
  String? _quickAmount = '100';

  /// 选中值使用 `option_key`：`alipay`、`yi_pay:wxpay` 等。
  String _gateway = 'alipay';
  List<Map<String, String>> _gateways = _fallbackGateways;
  bool _loadingGateways = true;
  bool _submitting = false;
  bool _refreshing = false;
  String? _amountError;
  _CreatedRecharge? _created;

  @override
  void initState() {
    super.initState();
    unawaited(_loadGateways());
  }

  @override
  void dispose() {
    _amountController.dispose();
    super.dispose();
  }

  Future<void> _loadGateways() async {
    final api = AppServices.of(context).finance;
    try {
      final data = await api.rechargeGateways();
      if (!mounted) return;
      final options = _parseGateways(data);
      setState(() {
        _gateways = options.isEmpty ? _fallbackGateways : options;
        _gateway = _gateways.first['value'] ?? 'alipay';
        _loadingGateways = false;
      });
    } catch (_) {
      // 网关列表拉取失败不阻断页面：回落支付宝，提交时由后端校验。
      if (!mounted) return;
      setState(() {
        _gateways = _fallbackGateways;
        _gateway = 'alipay';
        _loadingGateways = false;
      });
    }
  }

  /// 兼容 `gateways` → `list` → `methods` 三种列表键。
  ///
  /// 每项拆成四个字段，避免把插件名当渠道名、或让同一插件的支付宝/微信
  /// 两条通道在去重时互相覆盖：
  /// - `value`：`option_key`（如 `yi_pay:wxpay`），单选项唯一值；
  /// - `gateway`：提交用的网关编码（如 `yi_pay`）；
  /// - `paymentType`：提交用的通道类型（`alipay` / `wxpay`，可能为空）；
  /// - `label`：用户可见的渠道名（`channel_name`，禁回落到插件名 `name`）。
  List<Map<String, String>> _parseGateways(Map<String, dynamic> data) {
    var raw = listOf(data['gateways']);
    if (raw.isEmpty) raw = listOf(data['list']);
    if (raw.isEmpty) raw = listOf(data['methods']);
    final options = <Map<String, String>>[];
    final seen = <String>{};
    for (final item in raw) {
      final gateway = _firstNonEmpty(item, const ['key', 'gateway', 'value']);
      final paymentType = strOf(item, 'payment_type').trim();
      final optionKey = _firstNonEmpty(item, const ['option_key']);
      final value = optionKey.isNotEmpty
          ? optionKey
          : (paymentType.isEmpty ? gateway : '$gateway:$paymentType');
      if (value.isEmpty || !seen.add(value)) continue;
      final label = _firstNonEmpty(item, const [
        'channel_name',
        'label',
        'gateway_label',
      ]);
      options.add({
        'value': value,
        'label': label.isEmpty ? value : label,
        'gateway': gateway.isEmpty ? value : gateway,
        'paymentType': paymentType,
      });
    }
    return options;
  }

  String _firstNonEmpty(Map<String, dynamic> map, List<String> keys) {
    for (final key in keys) {
      final value = strOf(map, key).trim();
      if (value.isNotEmpty) return value;
    }
    return '';
  }

  /// 当前选中的支付方式；列表未加载完时回落到第一项。
  Map<String, String> get _selectedGateway {
    for (final gateway in _gateways) {
      if (gateway['value'] == _gateway) return gateway;
    }
    return _gateways.isEmpty ? _fallbackGateways.first : _gateways.first;
  }

  /// 提交用的金额：去掉千分位逗号与末尾小数点后的自定义值，未填时用快捷金额。
  String _resolveAmount() {
    var custom = _amountController.text.replaceAll(',', '').trim();
    if (custom.endsWith('.')) custom = custom.substring(0, custom.length - 1);
    return custom.isNotEmpty ? custom : (_quickAmount ?? '');
  }

  /// 提交按钮的金额文本：非法输入原样展示（带错误色由校验行承担），避免显示 `¥0.00`。
  String get _submitAmountLabel {
    final amount = _resolveAmount();
    if (amount.isEmpty) return '立即充值';
    return '立即充值 ${formatMoneyText(amount)}';
  }

  String? _validateAmount(String amount) {
    // 输入过程中允许以小数点结尾（如 `100.`），校验时按去掉末尾小数点处理。
    final normalized = amount.endsWith('.')
        ? amount.substring(0, amount.length - 1)
        : amount;
    if (normalized.isEmpty) return '请输入充值金额';
    if (!RegExp(r'^\d+(\.\d{1,2})?$').hasMatch(normalized)) {
      return '金额最多两位小数，且需为数字';
    }
    final value = double.tryParse(normalized);
    if (value == null || value < 1 || value > 50000) {
      return '充值金额需在 1–50000 元之间';
    }
    return null;
  }

  void _onAmountChanged(String raw) {
    final amount = raw.replaceAll(',', '').trim();
    setState(() {
      // 输入自定义金额即取消快捷金额选中，保持两者互斥。
      _quickAmount = null;
      _amountError = amount.isEmpty ? null : _validateAmount(amount);
      _created = null;
    });
  }

  void _selectQuickAmount(String amount) {
    setState(() {
      _quickAmount = amount;
      _amountController.clear();
      _amountError = null;
      _created = null;
    });
    FocusScope.of(context).unfocus();
  }

  Future<void> _submit() async {
    if (_submitting) return;
    final amount = _resolveAmount();
    final invalid = _validateAmount(amount);
    if (invalid != null) {
      setState(() => _amountError = invalid);
      AppFeedback.toast(context, invalid);
      return;
    }
    FocusScope.of(context).unfocus();
    setState(() => _submitting = true);
    final api = AppServices.of(context).finance;
    final selected = _selectedGateway;
    final gateway = selected['gateway'] ?? selected['value'] ?? 'alipay';
    final paymentType = selected['paymentType'] ?? '';
    try {
      final result = await api.recharge(
        amount: amount,
        gateway: gateway,
        paymentType: paymentType,
      );
      if (!mounted) return;
      final url = _pickUrl(result);
      final channel = detectPaymentChannel(
        gateway: gateway,
        paymentType: paymentType,
        channelName: selected['label'],
      );
      setState(() {
        _created = _CreatedRecharge(
          amount: amount,
          gatewayLabel: selected['label'] ?? _gatewayLabel(_gateway),
          payUrl: url,
          channel: channel,
        );
      });
      if (url.isEmpty) {
        AppFeedback.toast(context, '充值单已创建，请在支付完成后刷新余额');
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
      if (mounted) setState(() => _submitting = false);
    }
  }

  Future<void> _refreshBalance() async {
    if (_refreshing) return;
    setState(() => _refreshing = true);
    final services = AppServices.of(context);
    final session = context.read<Session>();
    try {
      final info = await services.auth.info();
      final rawUser = info['user'] is Map ? mapOf(info['user']) : info;
      if (rawUser.isNotEmpty) {
        await session.updateUser(rawUser);
      }
      if (!mounted) return;
      AppFeedback.success(context, '余额已刷新');
    } catch (error) {
      if (mounted) AppFeedback.error(context, AppServices.errorText(error));
    } finally {
      if (mounted) setState(() => _refreshing = false);
    }
  }

  /// 微信支付只能在微信内完成，而微信不开放外部唤起协议：把网关下发的
  /// https 收银台链接交给用户复制到微信（可发给文件传输助手）后打开。
  Future<void> _copyPayUrl(String url) async {
    await Clipboard.setData(ClipboardData(text: url));
    if (mounted) AppFeedback.success(context, '支付链接已复制，请在微信中打开');
  }

  /// 选中值的展示名：优先取当前选项的 `label`，回落遍历匹配。
  String _gatewayLabel(String value) {
    for (final gateway in _gateways) {
      if (gateway['value'] == value) return gateway['label'] ?? value;
    }
    return value;
  }

  String _pickUrl(Map<String, dynamic> map) {
    for (final key in const ['pay_url', 'url', 'qr_code', 'qrcode']) {
      final value = strOf(map, key).trim();
      if (value.isNotEmpty) return value;
    }
    return '';
  }

  @override
  Widget build(BuildContext context) {
    final padding = context.pagePadding;
    // 区块间距：平板 32，手机 24。
    final sectionGap = context.isTablet ? 32.0 : AppSpacing.gapXl;
    // 表单页在平板上限宽居中，避免输入行过长。
    final form = ListView(
      padding: EdgeInsets.fromLTRB(
        padding,
        AppSpacing.gapM,
        padding,
        AppSpacing.pageBottom,
      ),
      children: [
        if (_created != null) ...[
          _buildCreatedSection(context),
          SizedBox(height: sectionGap),
        ],
        _buildAmountGroup(context),
        SizedBox(height: sectionGap),
        _buildGatewayGroup(context),
      ],
    );
    return AppScaffold(
      title: '账户充值',
      body: context.isTablet
          ? Center(
              child: ConstrainedBox(
                constraints: const BoxConstraints(
                  maxWidth: Breakpoints.formMaxWidth,
                ),
                child: form,
              ),
            )
          : form,
      bottomBar: _buildSubmitBar(),
    );
  }

  /// 提交成功后的提示条 + 支付单信息组：金额与渠道一目了然，
  /// 并给出刷新余额的下一步。
  Widget _buildCreatedSection(BuildContext context) {
    final created = _created!;
    final theme = context.tTheme;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        TNoticeBar(
          status: TNoticeBarStatus.success,
          content: '支付单已创建，完成支付后刷新余额',
          operation: GestureDetector(
            behavior: HitTestBehavior.opaque,
            onTap: () => setState(() => _created = null),
            child: Semantics(
              button: true,
              label: '关闭提示',
              child: Padding(
                padding: const EdgeInsets.all(4),
                child: Icon(TIcons.close, size: 18, color: theme.successColor),
              ),
            ),
          ),
        ),
        TCellGroup(
          cells: [
            TCell(
              title: Text('充值金额', style: AppType.secondary(theme)),
              note: Text(
                formatMoneyText(created.amount),
                style: AppType.money(theme),
              ),
            ),
            TCell(
              title: Text('支付方式', style: AppType.secondary(theme)),
              note: Text(created.gatewayLabel, style: AppType.body(theme)),
            ),
            // 只有微信链接需要用户复制到微信内打开；支付宝可直接唤起 App，
            // 给它挂复制入口只是干扰。
            if (created.payUrl.isNotEmpty && created.channel == PaymentChannel.wechat)
              TCell(
                title: Text('支付链接', style: AppType.secondary(theme)),
                note: Text(
                  '复制到微信中打开',
                  style: AppType.caption(theme),
                ),
                trailing: TButton(
                  size: TButtonSize.extraSmall,
                  variant: TButtonVariant.text,
                  colorPreset: TButtonColorPreset.primary,
                  onPressed: () => _copyPayUrl(created.payUrl),
                  child: const Text('复制链接'),
                ),
              ),
            TCell(
              enableFeedback: false,
              title: SizedBox(
                width: double.infinity,
                child: TButton(
                  size: TButtonSize.medium,
                  variant: TButtonVariant.outline,
                  colorPreset: TButtonColorPreset.primary,
                  onPressed: _refreshing ? null : _refreshBalance,
                  child: Text(_refreshing ? '刷新中…' : '已完成支付，刷新余额'),
                ),
              ),
            ),
          ],
        ),
      ],
    );
  }

  /// 金额组：余额一行（可刷新）+ 快捷胶囊 + 自定义输入。
  Widget _buildAmountGroup(BuildContext context) {
    final theme = context.tTheme;
    final session = context.watch<Session>();
    final customEmpty = _amountController.text.trim().isEmpty;
    return TCellGroup(
      title: Text('充值金额', style: AppType.section(theme)),
      cells: [
        TCell(
          prefix: Icon(
            TIcons.wallet,
            size: 22,
            color: theme.textColorSecondary,
          ),
          title: Text('账户余额', style: AppType.secondary(theme)),
          note: Text(
            formatMoneyText(session.balance),
            style: AppType.money(theme),
          ),
          trailing: TButton(
            size: TButtonSize.extraSmall,
            variant: TButtonVariant.text,
            colorPreset: TButtonColorPreset.primary,
            icon: const Icon(TIcons.refresh, size: 16),
            onPressed: _refreshing ? null : _refreshBalance,
            child: Text(_refreshing ? '刷新中' : '刷新'),
          ),
        ),
        TCell(
          enableFeedback: false,
          align: TCellAlign.top,
          subtitle: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              const SizedBox(height: AppSpacing.gapXs),
              Wrap(
                spacing: AppSpacing.gapS,
                runSpacing: AppSpacing.gapS,
                children: [
                  for (final amount in _quickAmounts)
                    AppChip(
                      label: formatMoneyText(amount),
                      selected: customEmpty && _quickAmount == amount,
                      onTap: () => _selectQuickAmount(amount),
                    ),
                ],
              ),
              const SizedBox(height: AppSpacing.gapM),
              TInput(
                controller: _amountController,
                hintText: '自定义金额（1–50000，最多两位小数）',
                inputType: const TextInputType.numberWithOptions(decimal: true),
                inputFormatters: const [_MoneyInputFormatter()],
                status: _amountError == null
                    ? TInputStatus.normal
                    : TInputStatus.error,
                clearButtonMode: TInputClearButtonMode.always,
                prefix: _amountPrefix(theme),
                style: AppType.money(
                  theme,
                  size: 16,
                ).copyWith(fontWeight: FontWeight.w400),
                onChanged: _onAmountChanged,
              ),
              if (_amountError != null) ...[
                const SizedBox(height: AppSpacing.gapXs),
                Text(
                  _amountError!,
                  style: AppType.caption(
                    theme,
                  ).copyWith(color: theme.errorColor),
                ),
              ],
            ],
          ),
        ),
      ],
    );
  }

  /// 输入框前置货币符号（IgnorePointer 保证点击穿透到输入框）。
  Widget _amountPrefix(TThemeData theme) {
    return IgnorePointer(
      child: Padding(
        padding: const EdgeInsets.only(left: 12, right: 4),
        child: Text(
          '¥',
          style: AppType.money(theme, size: 16).copyWith(
            color: theme.textColorSecondary,
            fontWeight: FontWeight.w400,
          ),
        ),
      ),
    );
  }

  /// 支付方式组：`TRadioGroup` 管理选中值，组内用 `TCellGroup` + `TCell` 呈现，
  /// 每行右侧是 TDesign `TRadio` 官方指示器，整行可点。
  Widget _buildGatewayGroup(BuildContext context) {
    final theme = context.tTheme;
    if (_loadingGateways) {
      return TCellGroup(
        title: Text('支付方式', style: AppType.section(theme)),
        cells: const [
          TCell(
            enableFeedback: false,
            title: Center(child: TLoading(size: 20, text: '加载支付方式…')),
          ),
        ],
      );
    }
    return TRadioGroup<String>(
      value: _gateway,
      onChanged: (value) => setState(() => _gateway = value),
      child: TCellGroup(
        title: Text('支付方式', style: AppType.section(theme)),
        cells: [
          for (final gateway in _gateways)
            TCell(
              enableFeedback: false,
              prefix: Icon(
                _gatewayIcon(gateway['value'] ?? ''),
                size: 22,
                color: _gateway == gateway['value']
                    ? theme.brandColor
                    : theme.textColorSecondary,
              ),
              title: Text(
                gateway['label'] ?? gateway['value'] ?? '',
                style: AppType.body(theme).copyWith(
                  fontWeight: _gateway == gateway['value']
                      ? FontWeight.w600
                      : FontWeight.w400,
                  color: _gateway == gateway['value']
                      ? theme.brandColor
                      : theme.textColorPrimary,
                ),
              ),
              trailing: TRadio<String>(
                value: gateway['value'] ?? '',
                variant: TRadioVariant.inline,
                size: TRadioSize.medium,
              ),
              onTap: () => setState(() => _gateway = gateway['value'] ?? ''),
            ),
          TCell(
            enableFeedback: false,
            title: Text('支付完成后余额将自动到账', style: AppType.caption(theme)),
          ),
        ],
      ),
    );
  }

  /// 常见渠道用品牌图标，未知渠道用通用钱包图标兜底。
  ///
  /// 入参是选中值 `option_key`（`alipay`、`yi_pay:wxpay` 等），按子串匹配渠道。
  IconData _gatewayIcon(String gateway) {
    final value = gateway.toLowerCase();
    if (value.contains('alipay')) return TIcons.logo_alipay;
    if (value.contains('wechat') ||
        value.contains('weixin') ||
        value.contains('wxpay')) {
      return TIcons.logo_wechatpay;
    }
    return TIcons.wallet;
  }

  /// 底部固定提交区。金额非法时禁用，避免提交后才收到后端错误。
  Widget _buildSubmitBar() {
    final invalid = _submitting || _validateAmount(_resolveAmount()) != null;
    return SizedBox(
      height: 48,
      child: TButton(
        size: TButtonSize.large,
        colorPreset: TButtonColorPreset.primary,
        onPressed: invalid ? null : _submit,
        child: _submitting ? const Text('提交中…') : Text(_submitAmountLabel),
      ),
    );
  }
}

/// 金额输入实时格式化：仅保留数字与一个小数点、最多两位小数，整数部分加千分位。
///
/// 每次编辑后光标置于末尾——千分位分隔符会改变文本长度，保留原光标位置
/// 容易落在逗号中间导致后续输入错乱。
class _MoneyInputFormatter extends TextInputFormatter {
  const _MoneyInputFormatter();

  @override
  TextEditingValue formatEditUpdate(
    TextEditingValue oldValue,
    TextEditingValue newValue,
  ) {
    var raw = newValue.text
        .replaceAll(',', '')
        .replaceAll(RegExp(r'[^0-9.]'), '');
    // 空串：用户整体删除，保持空；纯小数点（如 `abc.` 被过滤成 `.`）也清空。
    if (raw.isEmpty || raw == '.') {
      return const TextEditingValue(text: '');
    }

    final dotIndex = raw.indexOf('.');
    if (dotIndex >= 0) {
      var intPart = raw.substring(0, dotIndex);
      var decimals = raw.substring(dotIndex + 1).replaceAll('.', '');
      if (decimals.length > 2) decimals = decimals.substring(0, 2);
      intPart = _stripLeadingZeros(intPart);
      raw = '$intPart.$decimals';
    } else {
      raw = _stripLeadingZeros(raw);
    }

    final parts = raw.split('.');
    final formatted = _groupThousands(parts[0]);
    final text = parts.length > 1 ? '$formatted.${parts[1]}' : formatted;
    return TextEditingValue(
      text: text,
      selection: TextSelection.collapsed(offset: text.length),
    );
  }

  String _groupThousands(String digits) {
    final buffer = StringBuffer();
    for (var i = 0; i < digits.length; i++) {
      if (i > 0 && (digits.length - i) % 3 == 0) buffer.write(',');
      buffer.write(digits[i]);
    }
    return buffer.toString();
  }

  /// 去掉整数部分的前导 0（`007` → `7`），单独一个 `0` 保留。
  String _stripLeadingZeros(String digits) {
    final trimmed = digits.replaceFirst(RegExp(r'^0+(?=\d)'), '');
    return trimmed.isEmpty ? '0' : trimmed;
  }
}
