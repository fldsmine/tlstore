import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../core/currency/money.dart';
import '../services/auth_api.dart';
import '../widgets/auth_components.dart';
import 'topup_screen.dart';

class CampaignCreationScreen extends StatefulWidget {
  const CampaignCreationScreen({
    super.key,
    required this.api,
    required this.token,
  });

  final AuthApi api;
  final String token;

  @override
  State<CampaignCreationScreen> createState() => _CampaignCreationScreenState();
}

class _CampaignCreationScreenState extends State<CampaignCreationScreen> {
  final _name = TextEditingController();
  final _quantity = TextEditingController();
  final _url = TextEditingController();
  final _instructions = TextEditingController();
  bool _loading = true;
  bool _submitting = false;
  String? _error;
  String? _message;
  Map<String, dynamic> _config = const {};
  List<Map<String, dynamic>> _wallets = const [];
  List<Map<String, dynamic>> _orders = const [];
  String _platform = '';
  String? _action;
  bool _serviceSelected = false;

  String get _currency => _config['currency']?.toString() ?? 'NGN';
  int get _precision => _asInt(
    _config['currency_minor_units'],
    fallback: currencyPrecision(_currency),
  );
  Map<String, dynamic> get _services => _asMap(_config['services']);
  Map<String, dynamic> get _platformServices => _asMap(_services[_platform]);
  Map<String, dynamic> get _platformActions =>
      Map<String, dynamic>.from(_platformServices)..remove('min');
  Map<String, dynamic> get _service => {
    'amount': _platformServices[_action],
    'min': _platformServices['min'],
  };
  int get _quantityValue => int.tryParse(_quantity.text) ?? 0;
  int get _unitPriceMinor => _toMinor(_service['amount']);
  int get _minimum => _asInt(_service['min']);
  int get _subtotalMinor => _unitPriceMinor * _quantityValue;
  double get _feePercent =>
      double.tryParse(_config['service_fee_percent']?.toString() ?? '') ?? 5;
  int get _feeMinor => (_subtotalMinor * _feePercent / 100).round();
  int get _totalMinor => _subtotalMinor + _feeMinor;
  Map<String, dynamic>? get _topupWallet {
    for (final wallet in _wallets) {
      if (wallet['type']?.toString() == 'topup_fund') return wallet;
    }
    return null;
  }

  @override
  void initState() {
    super.initState();
    _quantity.addListener(_refresh);
    _load();
  }

  @override
  void dispose() {
    _quantity.removeListener(_refresh);
    _name.dispose();
    _quantity.dispose();
    _url.dispose();
    _instructions.dispose();
    super.dispose();
  }

  void _refresh() {
    if (mounted) setState(() {});
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      await widget.api.loadExchangeRates(widget.token);
      final results = await Future.wait([
        widget.api.get('campaigns/config', widget.token),
        widget.api.get('wallets', widget.token),
        widget.api.get('me/campaign-orders?per_page=10', widget.token),
      ]);
      final config = _asMap(results[0]['data']);
      final wallets = results[1]['data'];
      final services = _asMap(config['services']);
      if (_platform.isNotEmpty && !services.containsKey(_platform)) {
        _platform = '';
        _action = null;
        _serviceSelected = false;
      }
      _config = config;
      _wallets = wallets is List
          ? wallets
                .whereType<Map>()
                .map((row) => Map<String, dynamic>.from(row))
                .toList()
          : const [];
      final orderPage = results[2]['data'];
      final orderRows = orderPage is Map ? orderPage['data'] : orderPage;
      _orders = orderRows is List
          ? orderRows
                .whereType<Map>()
                .map((row) => Map<String, dynamic>.from(row))
                .toList()
          : const [];
      if (_platform.isEmpty) _action = null;
    } on AuthException catch (error) {
      _error = error.message;
    } catch (_) {
      _error = 'Could not load campaign pricing. Please try again.';
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _choosePlatform(String platform) async {
    _quantity.clear();
    setState(() {
      _platform = platform;
      _action = null;
      _serviceSelected = false;
      _message = null;
    });
  }

  int _asInt(Object? value, {int fallback = 0}) => value is num
      ? value.toInt()
      : int.tryParse(value?.toString() ?? '') ?? fallback;

  int _toMinor(Object? amount) =>
      ((double.tryParse(amount?.toString() ?? '') ?? 0) * _pow10(_precision))
          .round();

  int _pow10(int n) => n <= 0
      ? 1
      : (n == 1
            ? 10
            : n == 2
            ? 100
            : 1000);

  Map<String, dynamic> _asMap(Object? value) => value is Map
      ? value.map<String, dynamic>(
          (key, value) => MapEntry(key.toString(), value),
        )
      : <String, dynamic>{};

  String _label(String value) => value
      .split('_')
      .map(
        (word) =>
            word.isEmpty ? '' : '${word[0].toUpperCase()}${word.substring(1)}',
      )
      .join(' ');

  String _amount(int minor) {
    final preferred = widget.api.preferredCurrency;
    return moneyConverted(
      widget.api,
      minor,
      _currency,
      _precision,
      preferred,
      isMinor: true,
    );
  }

  String _amountFor(int minor, String currency, int digits) => moneyConverted(
    widget.api,
    minor,
    currency,
    digits,
    widget.api.preferredCurrency,
    isMinor: true,
  );

  int? get _walletBalanceInCampaignMinor {
    final wallet = _topupWallet;
    if (wallet == null) return null;
    final walletCurrency = wallet['currency']?.toString() ?? 'USD';
    final walletDigits = currencyPrecision(walletCurrency);
    final balanceMinor = _asInt(
      wallet['balance_minor'] ?? wallet['available_balance_minor'],
    );
    final balanceMajor = balanceMinor / _pow10(walletDigits);
    final campaignMajor = widget.api.convertMajor(
      balanceMajor,
      walletCurrency,
      _currency,
    );
    if (campaignMajor == null) return null;
    return (campaignMajor * _pow10(_precision)).floor();
  }

  Future<void> _submit() async {
    if (_name.text.trim().isEmpty ||
        _action == null ||
        !_serviceSelected ||
        _quantityValue < _minimum ||
        _url.text.trim().isEmpty) {
      setState(
        () => _message = 'Enter a campaign name, choose a service, meet its minimum quantity, and add a target link.',
      );
      return;
    }
    final uri = Uri.tryParse(_url.text.trim());
    if (uri == null ||
        !uri.hasAuthority ||
        !{'http', 'https'}.contains(uri.scheme)) {
      setState(() => _message = 'Enter a valid http or https target link.');
      return;
    }
    final available = _walletBalanceInCampaignMinor;
    if (available != null && available < _totalMinor) {
      setState(
        () => _message = 'Your top-up fund balance is not enough. Top up your funds wallet to continue.',
      );
      return;
    }

    setState(() {
      _submitting = true;
      _message = null;
    });
    try {
      await widget.api.post('campaign-orders', widget.token, {
        'campaign_name': _name.text.trim(),
        'platform': _platform,
        'action': _action,
        'quantity': _quantityValue,
        'target_url': _url.text.trim(),
        'instructions': _instructions.text.trim().isEmpty
            ? null
            : _instructions.text.trim(),
        'idempotency_key': 'campaign-${DateTime.now().microsecondsSinceEpoch}',
      });
      if (!mounted) return;
      await _load();
      setState(() {
        _message = 'Campaign submitted. It is awaiting admin approval.';
        _action = null;
        _serviceSelected = false;
      });
      _name.clear();
      _quantity.clear();
      _url.clear();
      _instructions.clear();
    } on AuthException catch (error) {
      if (mounted) setState(() => _message = error.message);
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  Future<void> _topUp() async {
    await Navigator.of(context).push<void>(
      MaterialPageRoute<void>(
        builder: (_) => TopUpScreen(api: widget.api, token: widget.token),
      ),
    );
    if (mounted) await _load();
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final colors = theme.colorScheme;
    final wallet = _topupWallet;
    final balance = wallet == null
        ? 'No top-up wallet'
        : moneyConverted(
            widget.api,
            wallet['balance_minor'] ?? wallet['available_balance_minor'],
            wallet['currency']?.toString() ?? 'USD',
            null,
            widget.api.preferredCurrency,
            isMinor: true,
          );
    final enough =
        _walletBalanceInCampaignMinor == null ||
        _walletBalanceInCampaignMinor! >= _totalMinor;
    final platformColor = _platform.isEmpty
        ? colors.primary
        : _platformColor(_platform);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Create campaign'),
        centerTitle: false,
        scrolledUnderElevation: 0,
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
          ? Center(
              child: Padding(
                padding: const EdgeInsets.all(24),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Icon(
                      Icons.cloud_off_rounded,
                      size: 42,
                      color: colors.error,
                    ),
                    const SizedBox(height: 12),
                    Text(_error!, textAlign: TextAlign.center),
                    const SizedBox(height: 16),
                    FilledButton.tonalIcon(
                      onPressed: _load,
                      icon: const Icon(Icons.refresh_rounded),
                      label: const Text('Try again'),
                    ),
                  ],
                ),
              ),
            )
          : LayoutBuilder(
              builder: (context, constraints) {
                final contentWidth =
                    constraints.maxWidth > 820 ? 760.0 : constraints.maxWidth;
                return Align(
                  alignment: Alignment.topCenter,
                  child: SizedBox(
                    width: contentWidth,
                    child: ListView(
                      padding: const EdgeInsets.fromLTRB(16, 12, 16, 32),
                      children: [
                        Container(
                          padding: const EdgeInsets.all(20),
                          decoration: BoxDecoration(
                            gradient: LinearGradient(
                              begin: Alignment.topLeft,
                              end: Alignment.bottomRight,
                              colors: [
                                colors.primary,
                                colors.tertiary,
                              ],
                            ),
                            borderRadius: BorderRadius.circular(26),
                          ),
                          child: Row(
                            children: [
                              Expanded(
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(
                                      'Grow your audience',
                                      style: theme.textTheme.headlineSmall
                                          ?.copyWith(
                                            color: colors.onPrimary,
                                            fontWeight: FontWeight.w900,
                                          ),
                                    ),
                                    const SizedBox(height: 7),
                                    Text(
                                      'Create a campaign for the platform you want to grow. Your request will be reviewed before it goes live.',
                                      style: theme.textTheme.bodyMedium
                                          ?.copyWith(
                                            color: colors.onPrimary.withOpacity(0.9,
                                            ),
                                            height: 1.4,
                                          ),
                                    ),
                                  ],
                                ),
                              ),
                              const SizedBox(width: 10),
                              Icon(
                                Icons.rocket_launch_rounded,
                                size: 46,
                                color: colors.onPrimary.withOpacity(0.95),
                              ),
                            ],
                          ),
                        ),
                        const SizedBox(height: 18),
                        _panel(
                          context,
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              _sectionHeading(
                                context,
                                '01',
                                'Campaign details',
                                'Give your campaign a name',
                              ),
                              const SizedBox(height: 16),
                              _fieldLabel(context, 'Campaign name'),
                              TextField(
                                controller: _name,
                                textCapitalization:
                                    TextCapitalization.sentences,
                                decoration: InputDecoration(
                                  hintText: 'e.g. Instagram Growth',
                                  prefixIcon: const Icon(
                                    Icons.drive_file_rename_outline_rounded,
                                  ),
                                  filled: true,
                                  fillColor: colors.surfaceContainerHighest
                                      .withOpacity(0.45),
                                ),
                              ),
                              const SizedBox(height: 22),
                              _sectionHeading(
                                context,
                                '02',
                                'Choose a platform',
                                'Select where you want to grow',
                              ),
                              const SizedBox(height: 14),
                              LayoutBuilder(
                                builder: (context, innerConstraints) {
                                  final columns =
                                      innerConstraints.maxWidth >= 540 ? 3 : 2;
                                  final gap = 10.0;
                                  final itemWidth =
                                      (innerConstraints.maxWidth -
                                          gap * (columns - 1)) /
                                      columns;
                                  return Wrap(
                                    spacing: gap,
                                    runSpacing: gap,
                                    children: _services.keys.map((key) {
                                      final platform = key.toString();
                                      final selected = platform == _platform;
                                      final brand = _platformColor(platform);
                                      return SizedBox(
                                        width: itemWidth,
                                        child: InkWell(
                                          borderRadius: BorderRadius.circular(
                                            17,
                                          ),
                                          onTap: () =>
                                              _choosePlatform(platform),
                                          child: AnimatedContainer(
                                            duration: const Duration(
                                              milliseconds: 180,
                                            ),
                                            padding: const EdgeInsets.symmetric(
                                              horizontal: 11,
                                              vertical: 13,
                                            ),
                                            decoration: BoxDecoration(
                                              color: selected
                                                  ? brand.withOpacity(0.12,
                                                    )
                                                  : colors.surface,
                                              borderRadius:
                                                  BorderRadius.circular(17),
                                              border: Border.all(
                                                color: selected
                                                    ? brand
                                                    : colors.outlineVariant,
                                                width: selected ? 1.7 : 1,
                                              ),
                                            ),
                                            child: Row(
                                              children: [
                                                Container(
                                                  width: 38,
                                                  height: 38,
                                                  decoration: BoxDecoration(
                                                    color: brand,
                                                    borderRadius:
                                                        BorderRadius.circular(
                                                          12,
                                                        ),
                                                  ),
                                                  child: Icon(
                                                    _platformIcon(platform),
                                                    color: Colors.white,
                                                    size: 21,
                                                  ),
                                                ),
                                                const SizedBox(width: 9),
                                                Expanded(
                                                  child: Text(
                                                    _label(platform),
                                                    maxLines: 1,
                                                    overflow:
                                                        TextOverflow.ellipsis,
                                                    style: theme
                                                        .textTheme
                                                        .labelLarge
                                                        ?.copyWith(
                                                          fontWeight:
                                                              FontWeight.w800,
                                                          color: selected
                                                              ? brand
                                                              : colors
                                                                    .onSurface,
                                                        ),
                                                  ),
                                                ),
                                                if (selected)
                                                  Icon(
                                                    Icons.check_circle_rounded,
                                                    color: brand,
                                                    size: 18,
                                                  ),
                                              ],
                                            ),
                                          ),
                                        ),
                                      );
                                    }).toList(),
                                  );
                                },
                              ),
                              if (_platform.isNotEmpty) ...[
                                const SizedBox(height: 22),
                                _sectionHeading(
                                  context,
                                  '03',
                                  '${_label(_platform)} services',
                                  'Choose the service you want to order',
                                  accent: platformColor,
                                ),
                                const SizedBox(height: 12),
                                ..._platformActions.entries.map((entry) {
                                  final action = entry.key.toString();
                                  final selected = action == _action;
                                  final rate = _toMinor(entry.value);
                                  final minimum = _asInt(
                                    _platformServices['min'],
                                  );
                                  return Padding(
                                    padding: const EdgeInsets.only(bottom: 9),
                                    child: InkWell(
                                      borderRadius: BorderRadius.circular(17),
                                      onTap: () {
                                        setState(() {
                                          _action = action;
                                          _serviceSelected = true;
                                          _message = null;
                                        });
                                      },
                                      child: AnimatedContainer(
                                        duration: const Duration(
                                          milliseconds: 180,
                                        ),
                                        padding: const EdgeInsets.all(14),
                                        decoration: BoxDecoration(
                                          color: selected
                                              ? platformColor.withOpacity(0.09,
                                                )
                                              : colors.surface,
                                          borderRadius: BorderRadius.circular(
                                            17,
                                          ),
                                          border: Border.all(
                                            color: selected
                                                ? platformColor
                                                : colors.outlineVariant,
                                            width: selected ? 1.6 : 1,
                                          ),
                                        ),
                                        child: Row(
                                          children: [
                                            Container(
                                              width: 42,
                                              height: 42,
                                              decoration: BoxDecoration(
                                                color: platformColor.withOpacity(0.13,
                                                ),
                                                borderRadius:
                                                    BorderRadius.circular(13),
                                              ),
                                              child: Icon(
                                                _actionIcon(action),
                                                color: platformColor,
                                              ),
                                            ),
                                            const SizedBox(width: 12),
                                            Expanded(
                                              child: Column(
                                                crossAxisAlignment:
                                                    CrossAxisAlignment.start,
                                                children: [
                                                  Text(
                                                    _label(action),
                                                    style: theme
                                                        .textTheme
                                                        .titleSmall
                                                        ?.copyWith(
                                                          fontWeight:
                                                              FontWeight.w800,
                                                        ),
                                                  ),
                                                  const SizedBox(height: 3),
                                                  Text(
                                                    'Minimum $minimum',
                                                    style: theme
                                                        .textTheme
                                                        .bodySmall
                                                        ?.copyWith(
                                                          color: colors
                                                              .onSurfaceVariant,
                                                        ),
                                                  ),
                                                ],
                                              ),
                                            ),
                                            const SizedBox(width: 8),
                                            Column(
                                              crossAxisAlignment:
                                                  CrossAxisAlignment.end,
                                              children: [
                                                Text(
                                                  _amount(rate),
                                                  style: theme
                                                      .textTheme
                                                      .titleSmall
                                                      ?.copyWith(
                                                        color: platformColor,
                                                        fontWeight:
                                                            FontWeight.w900,
                                                      ),
                                                ),
                                                Text(
                                                  'per unit',
                                                  style: theme
                                                      .textTheme
                                                      .labelSmall
                                                      ?.copyWith(
                                                        color: colors
                                                            .onSurfaceVariant,
                                                      ),
                                                ),
                                              ],
                                            ),
                                            const SizedBox(width: 8),
                                            Icon(
                                              selected
                                                  ? Icons
                                                        .radio_button_checked_rounded
                                                  : Icons
                                                        .radio_button_off_rounded,
                                              color: selected
                                                  ? platformColor
                                                  : colors.outline,
                                            ),
                                          ],
                                        ),
                                      ),
                                    ),
                                  );
                                }),
                              ],
                              if (_serviceSelected && _action != null) ...[
                                const SizedBox(height: 14),
                                Divider(color: colors.outlineVariant),
                                const SizedBox(height: 12),
                                _sectionHeading(
                                  context,
                                  '04',
                                  'Campaign target',
                                  'Set your quantity and destination',
                                  accent: platformColor,
                                ),
                                const SizedBox(height: 14),
                                _fieldLabel(
                                  context,
                                  'Quantity (minimum $_minimum)',
                                ),
                                TextField(
                                  controller: _quantity,
                                  keyboardType: TextInputType.number,
                                  inputFormatters: [
                                    FilteringTextInputFormatter.digitsOnly,
                                  ],
                                  decoration: InputDecoration(
                                    hintText: 'Enter quantity',
                                    prefixIcon: const Icon(
                                      Icons.tag_rounded,
                                    ),
                                    filled: true,
                                    fillColor: colors.surfaceContainerHighest
                                        .withOpacity(0.45),
                                  ),
                                ),
                                const SizedBox(height: 15),
                                _fieldLabel(context, 'Target link / URL'),
                                TextField(
                                  controller: _url,
                                  keyboardType: TextInputType.url,
                                  decoration: InputDecoration(
                                    hintText:
                                        'https://${_platform}.com/your-profile',
                                    prefixIcon: const Icon(
                                      Icons.link_rounded,
                                    ),
                                    filled: true,
                                    fillColor: colors.surfaceContainerHighest
                                        .withOpacity(0.45),
                                  ),
                                ),
                                const SizedBox(height: 15),
                                _fieldLabel(
                                  context,
                                  'Additional instructions (optional)',
                                ),
                                TextField(
                                  controller: _instructions,
                                  maxLines: 3,
                                  maxLength: 3000,
                                  decoration: InputDecoration(
                                    hintText:
                                        'Add any notes for the review team...',
                                    alignLabelWithHint: true,
                                    filled: true,
                                    fillColor: colors.surfaceContainerHighest
                                        .withOpacity(0.45),
                                  ),
                                ),
                                if (_message != null) ...[
                                  const SizedBox(height: 8),
                                  _inlineMessage(context, _message!),
                                ],
                                const SizedBox(height: 10),
                                SizedBox(
                                  width: double.infinity,
                                  child: FilledButton.icon(
                                    style: FilledButton.styleFrom(
                                      backgroundColor: platformColor,
                                      foregroundColor: Colors.white,
                                      padding: const EdgeInsets.symmetric(
                                        vertical: 16,
                                      ),
                                      shape: RoundedRectangleBorder(
                                        borderRadius: BorderRadius.circular(15),
                                      ),
                                    ),
                                    onPressed: _submitting ? null : _submit,
                                    icon: _submitting
                                        ? const SizedBox(
                                            width: 18,
                                            height: 18,
                                            child: CircularProgressIndicator(
                                              strokeWidth: 2,
                                            ),
                                          )
                                        : const Icon(
                                            Icons.rocket_launch_rounded,
                                          ),
                                    label: Text(
                                      _submitting
                                          ? 'Submitting campaign...'
                                          : 'Launch campaign',
                                    ),
                                  ),
                                ),
                              ] else if (_platform.isNotEmpty) ...[
                                const SizedBox(height: 10),
                                Container(
                                  padding: const EdgeInsets.all(13),
                                  decoration: BoxDecoration(
                                    color: platformColor.withOpacity(0.08),
                                    borderRadius: BorderRadius.circular(14),
                                  ),
                                  child: Row(
                                    children: [
                                      Icon(
                                        Icons.touch_app_rounded,
                                        color: platformColor,
                                      ),
                                      const SizedBox(width: 9),
                                      Expanded(
                                        child: Text(
                                          'Choose one of the services above to continue.',
                                          style: theme.textTheme.bodySmall
                                              ?.copyWith(
                                                color: colors.onSurfaceVariant,
                                              ),
                                        ),
                                      ),
                                    ],
                                  ),
                                ),
                              ],
                              if (_message != null &&
                                  !_serviceSelected) ...[
                                const SizedBox(height: 12),
                                _inlineMessage(context, _message!),
                              ],
                            ],
                          ),
                        ),
                        if (_serviceSelected && _action != null) ...[
                          const SizedBox(height: 14),
                          _panel(
                            context,
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Row(
                                  children: [
                                    Container(
                                      width: 40,
                                      height: 40,
                                      decoration: BoxDecoration(
                                        color: platformColor.withOpacity(0.13,
                                        ),
                                        borderRadius: BorderRadius.circular(13),
                                      ),
                                      child: Icon(
                                        Icons.receipt_long_rounded,
                                        color: platformColor,
                                      ),
                                    ),
                                    const SizedBox(width: 11),
                                    Expanded(
                                      child: Column(
                                        crossAxisAlignment:
                                            CrossAxisAlignment.start,
                                        children: [
                                          Text(
                                            'Order summary',
                                            style: theme.textTheme.titleMedium
                                                ?.copyWith(
                                                  fontWeight: FontWeight.w900,
                                                ),
                                          ),
                                          Text(
                                            'Review your campaign cost',
                                            style: theme.textTheme.bodySmall
                                                ?.copyWith(
                                                  color:
                                                      colors.onSurfaceVariant,
                                                ),
                                          ),
                                        ],
                                      ),
                                    ),
                                    Container(
                                      padding: const EdgeInsets.symmetric(
                                        horizontal: 10,
                                        vertical: 6,
                                      ),
                                      decoration: BoxDecoration(
                                        color: platformColor.withOpacity(0.12,
                                        ),
                                        borderRadius: BorderRadius.circular(30),
                                      ),
                                      child: Text(
                                        _label(_platform),
                                        style: TextStyle(
                                          color: platformColor,
                                          fontWeight: FontWeight.w800,
                                          fontSize: 11,
                                        ),
                                      ),
                                    ),
                                  ],
                                ),
                                const SizedBox(height: 18),
                                _summaryRow(
                                  context,
                                  'Service',
                                  _label(_action!),
                                ),
                                _summaryRow(
                                  context,
                                  'Base rate',
                                  '${_amount(_unitPriceMinor)} / unit',
                                ),
                                _summaryRow(
                                  context,
                                  'Quantity',
                                  '$_quantityValue',
                                ),
                                _summaryRow(
                                  context,
                                  'Service fee (${_feePercent.toStringAsFixed(0)}%)',
                                  _amount(_feeMinor),
                                ),
                                Divider(
                                  height: 24,
                                  color: colors.outlineVariant,
                                ),
                                _summaryRow(
                                  context,
                                  'Total amount',
                                  _amount(_totalMinor),
                                  total: true,
                                ),
                                const SizedBox(height: 14),
                                Container(
                                  width: double.infinity,
                                  padding: const EdgeInsets.all(14),
                                  decoration: BoxDecoration(
                                    color: enough
                                        ? colors.secondaryContainer
                                        : colors.errorContainer,
                                    borderRadius: BorderRadius.circular(16),
                                  ),
                                  child: Column(
                                    crossAxisAlignment:
                                        CrossAxisAlignment.start,
                                    children: [
                                      Row(
                                        children: [
                                          Icon(
                                            enough
                                                ? Icons.account_balance_wallet_rounded
                                                : Icons.warning_amber_rounded,
                                            color: enough
                                                ? colors.onSecondaryContainer
                                                : colors.onErrorContainer,
                                          ),
                                          const SizedBox(width: 8),
                                          Expanded(
                                            child: Text(
                                              'Top-up funds · $balance',
                                              style: TextStyle(
                                                fontWeight: FontWeight.w900,
                                                color: enough
                                                    ? colors.onSecondaryContainer
                                                    : colors.onErrorContainer,
                                              ),
                                            ),
                                          ),
                                        ],
                                      ),
                                      const SizedBox(height: 5),
                                      Text(
                                        enough && wallet != null
                                            ? 'Campaigns are paid from top-up funds only.'
                                            : 'Top up your funds wallet to pay for this campaign.',
                                        style: TextStyle(
                                          color: enough
                                              ? colors.onSecondaryContainer
                                              : colors.onErrorContainer,
                                        ),
                                      ),
                                      if (!enough || wallet == null) ...[
                                        const SizedBox(height: 10),
                                        SizedBox(
                                          width: double.infinity,
                                          child: FilledButton.tonalIcon(
                                            onPressed: _topUp,
                                            icon: const Icon(
                                              Icons.add_card_rounded,
                                            ),
                                            label: const Text('Top up wallet'),
                                          ),
                                        ),
                                      ],
                                    ],
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ],
                        if (_orders.isNotEmpty) ...[
                          const SizedBox(height: 18),
                          _panel(
                            context,
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                _sectionHeading(
                                  context,
                                  '•',
                                  'My campaigns',
                                  'Track your recent requests',
                                ),
                                const SizedBox(height: 8),
                                ..._orders.map((order) {
                                  final status =
                                      order['status']?.toString() ?? 'pending';
                                  final statusColor = status == 'approved'
                                      ? colors.primary
                                      : status == 'rejected'
                                      ? colors.error
                                      : colors.tertiary;
                                  final total = _asInt(order['total_minor']);
                                  final currency =
                                      order['currency']?.toString() ?? _currency;
                                  final digits = currencyPrecision(currency);
                                  return Container(
                                    margin: const EdgeInsets.only(bottom: 8),
                                    decoration: BoxDecoration(
                                      color: colors.surfaceContainerHighest
                                          .withOpacity(0.45),
                                      borderRadius: BorderRadius.circular(15),
                                    ),
                                    child: ListTile(
                                      contentPadding:
                                          const EdgeInsets.symmetric(
                                            horizontal: 13,
                                            vertical: 3,
                                          ),
                                      title: Text(
                                        order['campaign_name']?.toString() ??
                                            'Campaign',
                                        style: const TextStyle(
                                          fontWeight: FontWeight.w800,
                                        ),
                                      ),
                                      subtitle: Text(
                                        '${_label(order['platform']?.toString() ?? '')} · ${_label(order['action']?.toString() ?? '')} · ${order['quantity']} actions${status == 'rejected' && (order['admin_note']?.toString().isNotEmpty ?? false) ? '\n${order['admin_note']}' : ''}',
                                      ),
                                      isThreeLine: status == 'rejected',
                                      trailing: Column(
                                        mainAxisAlignment:
                                            MainAxisAlignment.center,
                                        crossAxisAlignment:
                                            CrossAxisAlignment.end,
                                        children: [
                                          Text(
                                            _amountFor(total, currency, digits),
                                            style: const TextStyle(
                                              fontWeight: FontWeight.w800,
                                            ),
                                          ),
                                          Text(
                                            status.toUpperCase(),
                                            style: TextStyle(
                                              color: statusColor,
                                              fontSize: 10,
                                              fontWeight: FontWeight.w900,
                                            ),
                                          ),
                                        ],
                                      ),
                                    ),
                                  );
                                }),
                              ],
                            ),
                          ),
                        ],
                        const SizedBox(height: 14),
                        Text(
                          'Campaign currency: $_currency · totals are displayed in your preferred currency where conversion rates are available.',
                          textAlign: TextAlign.center,
                          style: theme.textTheme.bodySmall?.copyWith(
                            color: colors.onSurfaceVariant,
                          ),
                        ),
                      ],
                    ),
                  ),
                );
              },
            ),
    );
  }

  Widget _sectionHeading(
    BuildContext context,
    String step,
    String title,
    String subtitle, {
    Color? accent,
  }) {
    final colors = Theme.of(context).colorScheme;
    final tint = accent ?? colors.primary;
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Container(
          width: 31,
          height: 31,
          alignment: Alignment.center,
          decoration: BoxDecoration(
            color: tint.withOpacity(0.13),
            borderRadius: BorderRadius.circular(10),
          ),
          child: Text(
            step,
            style: TextStyle(
              color: tint,
              fontWeight: FontWeight.w900,
              fontSize: 12,
            ),
          ),
        ),
        const SizedBox(width: 10),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                title,
                style: Theme.of(context).textTheme.titleSmall?.copyWith(
                  fontWeight: FontWeight.w900,
                ),
              ),
              const SizedBox(height: 2),
              Text(
                subtitle,
                style: Theme.of(context).textTheme.bodySmall?.copyWith(
                  color: colors.onSurfaceVariant,
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }

  Widget _inlineMessage(BuildContext context, String message) {
    final colors = Theme.of(context).colorScheme;
    final success = message.toLowerCase().contains('submitted');
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: (success ? colors.primary : colors.error).withOpacity(0.10,
        ),
        borderRadius: BorderRadius.circular(13),
      ),
      child: Row(
        children: [
          Icon(
            success ? Icons.check_circle_rounded : Icons.info_outline_rounded,
            color: success ? colors.primary : colors.error,
            size: 19,
          ),
          const SizedBox(width: 8),
          Expanded(child: Text(message)),
        ],
      ),
    );
  }

  IconData _actionIcon(String action) {
    final value = action.toLowerCase();
    if (value.contains('follower') || value.contains('subscriber')) {
      return Icons.people_alt_rounded;
    }
    if (value.contains('like')) return Icons.favorite_rounded;
    if (value.contains('share')) return Icons.share_rounded;
    if (value.contains('join') || value.contains('channel')) {
      return Icons.groups_rounded;
    }
    return Icons.trending_up_rounded;
  }

  Color _platformColor(String platform) => switch (platform) {
    'facebook' => const Color(0xFF1877F2),
    'instagram' => const Color(0xFFE1306C),
    'twitter' => const Color(0xFF1D9BF0),
    'youtube' => const Color(0xFFFF0033),
    'tiktok' => const Color(0xFF111111),
    'whatsapp' => const Color(0xFF25D366),
    'telegram' => const Color(0xFF229ED9),
    _ => Theme.of(context).colorScheme.primary,
  };

  Widget _panel(BuildContext context, {required Widget child}) => Container(
    padding: const EdgeInsets.all(17),
    decoration: BoxDecoration(
      color: Theme.of(context).colorScheme.surfaceContainerLow,
      borderRadius: BorderRadius.circular(22),
      border: Border.all(color: Theme.of(context).colorScheme.outlineVariant),
      boxShadow: [
        BoxShadow(
          color: Theme.of(context).colorScheme.shadow.withOpacity(0.035),
          blurRadius: 18,
          offset: const Offset(0, 7),
        ),
      ],
    ),
    child: child,
  );

  Widget _fieldLabel(BuildContext context, String text) => Padding(
    padding: const EdgeInsets.only(bottom: 8),
    child: Text(
      text,
      style: Theme.of(context).textTheme.labelLarge
          ?.copyWith(fontWeight: FontWeight.w700),
    ),
  );

  Widget _summaryRow(
    BuildContext context,
    String label,
    String value, {
    bool total = false,
  }) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 5),
    child: Row(
      children: [
        Expanded(
          child: Text(
            label,
            style: TextStyle(
              color: total
                  ? Theme.of(context).colorScheme.onSurface
                  : Theme.of(context).colorScheme.onSurfaceVariant,
              fontWeight: total ? FontWeight.w800 : FontWeight.normal,
            ),
          ),
        ),
        Text(
          value,
          style: TextStyle(
            color: total
                ? Theme.of(context).colorScheme.primary
                : Theme.of(context).colorScheme.onSurface,
            fontWeight: FontWeight.w800,
            fontSize: total ? 18 : 14,
          ),
        ),
      ],
    ),
  );

  IconData _platformIcon(String platform) => switch (platform) {
    'facebook' => Icons.facebook_rounded,
    'instagram' => Icons.camera_alt_outlined,
    'twitter' => Icons.alternate_email_rounded,
    'youtube' => Icons.play_circle_outline_rounded,
    'tiktok' => Icons.music_note_rounded,
    'whatsapp' => Icons.chat_rounded,
    'telegram' => Icons.send_rounded,
    _ => Icons.campaign_outlined,
  };
}
