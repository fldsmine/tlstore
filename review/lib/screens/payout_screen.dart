import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../core/currency/money.dart';
import '../services/auth_api.dart';
import '../widgets/auth_components.dart';

class PayoutScreen extends StatefulWidget {
  const PayoutScreen({
    super.key,
    required this.api,
    required this.token,
    this.walletId,
  });

  final AuthApi api;
  final String token;
  final int? walletId;

  @override
  State<PayoutScreen> createState() => _PayoutScreenState();
}

class _PayoutScreenState extends State<PayoutScreen> {
  final _amount = TextEditingController();
  final Map<String, TextEditingController> _details = {};
  bool _loading = true;
  bool _submitting = false;
  String? _error;
  List<Map<String, dynamic>> _wallets = const [];
  List<Map<String, dynamic>> _options = const [];
  List<Map<String, dynamic>> _requests = const [];
  Map<String, dynamic>? _wallet;
  Map<String, dynamic>? _option;
  bool _securityPinSet = false;
  Map<String, dynamic> _packagePlan = const {};
  Map<String, dynamic> _savedPayoutProfile = const {};
  String _preferredCurrency = 'USD';

  List<String> get _fields => (_option?['fields'] as List? ?? const [])
      .map((field) => field.toString())
      .toList();
  String get _currency => _wallet?['currency']?.toString() ?? 'USD';
  double get _packageMinimumMajorInWallet {
    final minor = (_packagePlan['minimum_withdrawal_minor'] as num?)?.toDouble() ?? 0;
    if (minor <= 0) return 0;
    final sourceCurrency = _packagePlan['currency']?.toString().toUpperCase() ?? _currency;
    final sourceMajor = minor / math.pow(10, currencyPrecision(sourceCurrency));
    return widget.api.convertMajor(sourceMajor, sourceCurrency, _currency) ?? sourceMajor;
  }

  double get _minimumMajor {
    final methodMinimum = double.tryParse(_option?['min_withdrawal']?.toString() ?? '') ?? 0;
    return math.max(methodMinimum, _packageMinimumMajorInWallet).toDouble();
  }

  int get _digits {
    const zero = {'JPY', 'KRW', 'XOF', 'XAF', 'CLP', 'BIF'};
    const three = {'KWD', 'BHD', 'TND'};
    return zero.contains(_currency) ? 0 : (three.contains(_currency) ? 3 : 2);
  }

  double get _enteredAmount =>
      double.tryParse(_amount.text.trim().replaceAll(',', '')) ?? 0;
  double get _feeRate =>
      double.tryParse(_option?['tax']?.toString() ?? '') ?? 0;
  double get _fee => _enteredAmount > 0 ? _enteredAmount * _feeRate / 100 : 0;

  @override
  void initState() {
    super.initState();
    _amount.addListener(_amountChanged);
    _load();
  }

  void _amountChanged() {
    if (mounted) setState(() {});
  }

  @override
  void dispose() {
    _amount.removeListener(_amountChanged);
    _amount.dispose();
    for (final controller in _details.values) {
      controller.dispose();
    }
    super.dispose();
  }

  Future<void> _load() async {
    if (mounted) {
      setState(() {
        _loading = true;
        _error = null;
      });
    }
    try {
      await widget.api.loadExchangeRates(widget.token);
      final results = await Future.wait([
        widget.api.get('wallets', widget.token),
        widget.api.get('withdrawal-options', widget.token),
        widget.api.get('withdrawals', widget.token),
        widget.api.get('me', widget.token),
      ]);

      List<Map<String, dynamic>> list(dynamic data) {
        final rows = data is Map<String, dynamic> ? data['data'] : data;
        return rows is List
            ? rows.whereType<Map<String, dynamic>>().toList()
            : const [];
      }

      final wallets = list(results[0]['data'])
          .where(
            (w) =>
                w['type'] != 'topup_fund' &&
                (widget.walletId == null ||
                    (w['id'] as num?)?.toInt() == widget.walletId),
          )
          .toList();
      final options =
          list(results[1]['data'])
              .where((o) => o['is_available'] != false)
              .toList()
            ..sort(
              (a, b) => ((a['order'] as num?)?.toInt() ?? 999).compareTo(
                (b['order'] as num?)?.toInt() ?? 999,
              ),
            );
      final requests = list(results[2]['data']);
      final user = results[3]['data'];
      final payoutProfile =
          user is Map<String, dynamic> && user['payout_profile'] is Map
          ? Map<String, dynamic>.from(user['payout_profile'] as Map)
          : <String, dynamic>{};
      final savedDetails = payoutProfile['details'] is Map
          ? Map<String, dynamic>.from(payoutProfile['details'] as Map)
          : <String, dynamic>{};
      final savedOptionId = int.tryParse(
        payoutProfile['withdrawal_option_id']?.toString() ?? '',
      );
      Map<String, dynamic> asMap(dynamic value) => value is Map
          ? Map<String, dynamic>.from(value)
          : <String, dynamic>{};
      final subscriptionContext = user is Map<String, dynamic>
          ? asMap(user['subscription_context'])
          : <String, dynamic>{};
      final activeSubscriptions =
          (subscriptionContext['active_marketplaces'] as List? ?? const [])
              .whereType<Map>()
              .map((value) => Map<String, dynamic>.from(value))
              .toList();
      final selectedWallet = wallets
              .where((w) => (w['id'] as num?)?.toInt() == widget.walletId)
              .firstOrNull ??
          wallets.firstOrNull;
      final targetGroup = switch (selectedWallet?['type']?.toString()) {
        'share' => 'group_a',
        'review_group_c' => 'group_c',
        'review_group_b' || 'review' => 'group_b',
        _ => null,
      };
      final activeSubscription = activeSubscriptions
              .where((item) => targetGroup == null || item['group_key'] == targetGroup)
              .firstOrNull ??
          activeSubscriptions.firstOrNull;
      final planId = activeSubscription?['subscription_plan_id']?.toString();
      final catalog = asMap(subscriptionContext['catalog']);
      Map<String, dynamic> packagePlan = const {};
      for (final group in (catalog['subscription_groups'] as List? ?? const []).whereType<Map>()) {
        for (final plan in (group['plans'] as List? ?? const []).whereType<Map>()) {
          if (plan['id']?.toString() == planId) {
            packagePlan = Map<String, dynamic>.from(plan);
            break;
          }
        }
      }
      if (!mounted) return;
      setState(() {
        _preferredCurrency = user is Map<String, dynamic>
            ? (user['preferred_currency']?.toString() ??
                  widget.api.preferredCurrency)
            : widget.api.preferredCurrency;
        widget.api.setPreferredCurrency(_preferredCurrency);
        _wallets = wallets;
        _options = options;
        _requests = requests;
        _securityPinSet =
            user is Map<String, dynamic> && user['security_pin_set'] == true;
        _savedPayoutProfile =
            user is Map<String, dynamic> && user['payout_profile'] is Map
            ? Map<String, dynamic>.from(user['payout_profile'] as Map)
            : const {};
        _packagePlan = packagePlan;
        _wallet = selectedWallet;
        final oldOptionId = (_option?['id'] as num?)?.toInt();
        _option =
            options
                .where(
                  (o) =>
                      (o['id'] as num?)?.toInt() ==
                      (savedOptionId ?? oldOptionId),
                )
                .firstOrNull ??
            options.firstOrNull;
        for (final entry in savedDetails.entries) {
          _details.putIfAbsent(entry.key, () => TextEditingController()).text =
              entry.value.toString();
        }
        _loading = false;
      });
      _syncDetailFields();
    } on AuthException catch (error) {
      if (mounted)
        setState(() {
          _error = error.message;
          _loading = false;
        });
    } catch (_) {
      if (mounted)
        setState(() {
          _error = 'Unable to load payout information. Please try again.';
          _loading = false;
        });
    }
  }

  void _syncDetailFields() {
    for (final field in _fields) {
      _details.putIfAbsent(field, () => TextEditingController());
    }
  }

  Future<void> _submit() async {
    final wallet = _wallet;
    final option = _option;
    if (wallet == null || option == null) {
      showAuthMessage(context, 'Choose a wallet and withdrawal method.');
      return;
    }
    final major = _enteredAmount;
    if (major <= 0) {
      showAuthMessage(context, 'Enter a valid withdrawal amount.');
      return;
    }
    final minimum = _minimumMajor;
    if (minimum > 0 && major < minimum) {
      showAuthMessage(
        context,
        'The minimum withdrawal is ${moneyConverted(widget.api, minimum, _currency, _digits, _preferredCurrency, isMinor: false)}.',
      );
      return;
    }
    final balanceMinor =
        (wallet['available_balance_minor'] as num?)?.toInt() ?? 0;
    final available = balanceMinor / math.pow(10, _digits);
    if (major > available) {
      showAuthMessage(
        context,
        'The amount is higher than your available balance.',
      );
      return;
    }
    final payout = {
      for (final field in _fields) field: _details[field]?.text.trim() ?? '',
    };
    final profileOptionId = int.tryParse(
      _savedPayoutProfile['withdrawal_option_id']?.toString() ?? '',
    );
    final selectedOptionId = int.tryParse(option['id']?.toString() ?? '');
    final savedDetails = _savedPayoutProfile['details'];
    if (profileOptionId == selectedOptionId && savedDetails is Map) {
      for (final key in const ['bank_id', 'bank_code']) {
        if (savedDetails[key] != null)
          payout[key] = savedDetails[key].toString();
      }
    }
    if (payout.values.any((value) => value.isEmpty)) {
      showAuthMessage(context, 'Complete all payout details for this method.');
      return;
    }
    final pin = _securityPinSet ? await _askForSecurityPin() : null;
    if (_securityPinSet && pin == null) return;
    if (!mounted) return;
    setState(() => _submitting = true);
    try {
      await widget.api.post('withdrawals', widget.token, {
        'wallet_id': wallet['id'],
        'withdrawal_option_id': option['id'],
        'amount_minor': (major * math.pow(10, _digits)).round(),
        'payout_details': payout,
        'idempotency_key':
            'withdraw-mobile-${DateTime.now().microsecondsSinceEpoch}',
        if (pin != null) 'security_pin': pin,
      });
      if (!mounted) return;
      _amount.clear();
      for (final controller in _details.values) {
        controller.clear();
      }
      showAuthMessage(
        context,
        'Withdrawal request submitted for admin approval.',
      );
      await _load();
    } on AuthException catch (error) {
      if (mounted) showAuthMessage(context, error.message);
    } catch (_) {
      if (mounted)
        showAuthMessage(
          context,
          'Could not submit the request. Please try again.',
        );
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  Future<String?> _askForSecurityPin() {
    final pin = TextEditingController();
    return showDialog<String>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
        title: Text(
          'Confirm withdrawal',
          style: TextStyle(fontWeight: FontWeight.w800),
        ),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(
              'Enter your 4-digit security PIN to submit this withdrawal request.',
            ),
            const SizedBox(height: 12),
            TextField(
              controller: pin,
              obscureText: true,
              keyboardType: TextInputType.number,
              inputFormatters: [FilteringTextInputFormatter.digitsOnly],
              maxLength: 4,
              textAlign: TextAlign.center,
              decoration: const InputDecoration(
                labelText: 'Security PIN',
                counterText: '',
              ),
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext),
            child: Text('Cancel'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(
              dialogContext,
              pin.text.length == 4 ? pin.text : null,
            ),
            child: Text('Continue'),
          ),
        ],
      ),
    ).whenComplete(pin.dispose);
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final colors = theme.colorScheme;

    return Scaffold(
      backgroundColor: colors.surface,
      appBar: AppBar(
        title: Text('Payout', style: TextStyle(fontWeight: FontWeight.w800)),
        backgroundColor: colors.surface,
        surfaceTintColor: Colors.transparent,
        elevation: 0,
        actions: [
          IconButton(
            onPressed: _loading ? null : _load,
            tooltip: 'Refresh',
            icon: Icon(Icons.refresh_rounded),
          ),
          const SizedBox(width: 5),
        ],
      ),
      body: _loading
          ? Center(
              child: CircularProgressIndicator(
                color: Theme.of(context).colorScheme.primary,
              ),
            )
          : _error != null
          ? _errorView()
          : RefreshIndicator(
              color: Theme.of(context).colorScheme.primary,
              onRefresh: _load,
              child: ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.fromLTRB(17, 4, 17, 30),
                children: [
                  _hero(),
                  const SizedBox(height: 16),
                  _balanceCard(),
                  const SizedBox(height: 22),
                  _heading(
                    'Request a payout',
                    'Choose where your earnings should go.',
                  ),
                  const SizedBox(height: 12),
                  _requestCard(),
                  const SizedBox(height: 23),
                  _heading(
                    'Recent payout requests',
                    'Track the progress of your withdrawals.',
                    trailing: _requests.isEmpty
                        ? null
                        : '${_requests.length} total',
                  ),
                  const SizedBox(height: 12),
                  if (_requests.isEmpty)
                    _emptyRequests()
                  else
                    ..._requests
                        .take(10)
                        .map(
                          (r) => Padding(
                            padding: const EdgeInsets.only(bottom: 10),
                            child: _PayoutRow(
                              request: r,
                              api: widget.api,
                              preferredCurrency: _preferredCurrency,
                            ),
                          ),
                        ),
                  const SizedBox(height: 10),
                  _securityNote(),
                ],
              ),
            ),
    );
  }

  Widget _errorView() => Center(
    child: Padding(
      padding: const EdgeInsets.all(25),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(
            Icons.cloud_off_rounded,
            size: 42,
            color: Theme.of(context).colorScheme.onSurfaceVariant,
          ),
          const SizedBox(height: 12),
          Text(
            'Could not load payouts',
            style: TextStyle(
              color: Theme.of(context).colorScheme.onSurface,
              fontWeight: FontWeight.w800,
              fontSize: 17,
            ),
          ),
          const SizedBox(height: 6),
          Text(
            _error!,
            textAlign: TextAlign.center,
            style: TextStyle(
              color: Theme.of(context).colorScheme.onSurfaceVariant,
            ),
          ),
          TextButton(onPressed: _load, child: Text('Try again')),
        ],
      ),
    ),
  );

  Widget _hero() => Container(
    padding: const EdgeInsets.all(19),
    decoration: BoxDecoration(
      gradient: const LinearGradient(
        begin: Alignment.topLeft,
        end: Alignment.bottomRight,
        colors: [Color(0xFF104A35), Color(0xFF267451), Color(0xFF31835C)],
      ),
      borderRadius: BorderRadius.circular(21),
      boxShadow: [
        BoxShadow(
          color: const Color(0xFF145A3D).withValues(alpha: .16),
          blurRadius: 18,
          offset: const Offset(0, 8),
        ),
      ],
    ),
    child: Row(
      children: [
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Your earnings,\nyour destination.',
                style: TextStyle(
                  color: Colors.white,
                  fontSize: 21,
                  height: 1.18,
                  fontWeight: FontWeight.w900,
                ),
              ),
              SizedBox(height: 8),
              Text(
                'Withdraw eligible earnings to your preferred payout method.',
                style: TextStyle(
                  color: Colors.white70,
                  fontSize: 12,
                  height: 1.4,
                ),
              ),
              SizedBox(height: 12),
              Row(
                children: [
                  Icon(
                    Icons.verified_user_outlined,
                    color: Color(0xFFF3D58E),
                    size: 15,
                  ),
                  SizedBox(width: 6),
                  Text(
                    'Secure payout request',
                    style: TextStyle(
                      color: Color(0xFFF3D58E),
                      fontSize: 11,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
        const SizedBox(width: 8),
        Container(
          width: 65,
          height: 65,
          decoration: BoxDecoration(
            color: Colors.white.withValues(alpha: .12),
            borderRadius: BorderRadius.circular(19),
          ),
          child: Icon(
            Icons.account_balance_wallet_rounded,
            color: Color(0xFFF4D991),
            size: 34,
          ),
        ),
      ],
    ),
  );

  Widget _balanceCard() {
    final wallet = _wallet;
    final balance = (wallet?['available_balance_minor'] as num?)?.toInt() ?? 0;
    final currency = wallet?['currency']?.toString() ?? _currency;
    return Container(
      padding: const EdgeInsets.all(15),
      decoration: BoxDecoration(
        color: Theme.of(context).colorScheme.surfaceContainerLow,
        borderRadius: BorderRadius.circular(17),
        border: Border.all(color: Theme.of(context).colorScheme.outlineVariant),
      ),
      child: Row(
        children: [
          _bubble(Icons.savings_outlined),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Available to withdraw',
                  style: TextStyle(
                    color: Theme.of(context).colorScheme.onSurfaceVariant,
                    fontSize: 11,
                  ),
                ),
                const SizedBox(height: 3),
                Text(
                  wallet == null
                      ? 'No eligible wallet'
                      : moneyConverted(
                          widget.api,
                          balance,
                          currency,
                          currencyPrecision(currency),
                          _preferredCurrency,
                          isMinor: true,
                        ),
                  style: TextStyle(
                    color: Theme.of(context).colorScheme.onSurface,
                    fontSize: 18,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ],
            ),
          ),
          if (wallet != null)
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 6),
              decoration: BoxDecoration(
                color: Theme.of(context).colorScheme.primaryContainer,
                borderRadius: BorderRadius.circular(20),
              ),
              child: Text(
                _pretty(wallet['type']?.toString() ?? 'Earnings'),
                style: TextStyle(
                  color: Theme.of(context).colorScheme.primary,
                  fontSize: 10,
                  fontWeight: FontWeight.w800,
                ),
              ),
            ),
        ],
      ),
    );
  }

  Widget _requestCard() => Container(
    padding: const EdgeInsets.all(17),
    decoration: BoxDecoration(
      color: Theme.of(context).colorScheme.surfaceContainerLow,
      borderRadius: BorderRadius.circular(20),
      border: Border.all(color: Theme.of(context).colorScheme.outlineVariant),
      boxShadow: [
        BoxShadow(
          color: Theme.of(context).colorScheme.shadow.withValues(alpha: .025),
          blurRadius: 14,
          offset: const Offset(0, 5),
        ),
      ],
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        _stepLabel('1', 'Select earnings wallet'),
        const SizedBox(height: 9),
        DropdownButtonFormField<int>(
          value: (_wallet?['id'] as num?)?.toInt(),
          isExpanded: true,
          decoration: _decoration(
            'Earnings wallet',
            Icons.account_balance_wallet_outlined,
          ),
          items: _wallets
              .map(
                (w) => DropdownMenuItem<int>(
                  value: (w['id'] as num).toInt(),
                  child: Text(
                    '${_pretty(w['type']?.toString() ?? 'Earnings')} • ${moneyConverted(widget.api, w['available_balance_minor'], w['currency']?.toString() ?? 'USD', null, _preferredCurrency, isMinor: true)}',
                    overflow: TextOverflow.ellipsis,
                  ),
                ),
              )
              .toList(),
          onChanged: (id) => setState(
            () => _wallet = _wallets
                .where((w) => (w['id'] as num).toInt() == id)
                .firstOrNull,
          ),
        ),
        if (_wallets.isEmpty)
          _notice(
            'No eligible earnings wallet was found. Top-up funds cannot be withdrawn.',
          ),
        const SizedBox(height: 18),
        _stepLabel('2', 'Choose payout method'),
        const SizedBox(height: 9),
        if (_options.isEmpty)
          _notice('There are currently no payout methods available.')
        else
          ..._options.map(_methodTile),
        if (_option?['description'] != null)
          Padding(
            padding: const EdgeInsets.only(top: 1),
            child: Text(
              _option!['description'].toString(),
              style: TextStyle(
                color: Theme.of(context).colorScheme.onSurfaceVariant,
                fontSize: 11,
                height: 1.4,
              ),
            ),
          ),
        if (_packagePlan.isNotEmpty)
          Padding(
            padding: const EdgeInsets.only(top: 8),
            child: Text(
              'Package: ${_packagePlan['name'] ?? 'Active'} · Withdrawal days: ${(_packagePlan['withdrawal_days'] as List? ?? const []).join(', ')}',
              style: TextStyle(
                color: Theme.of(context).colorScheme.onSurfaceVariant,
                fontSize: 11,
                height: 1.4,
              ),
            ),
          ),
        const SizedBox(height: 18),
        _stepLabel('3', 'Enter withdrawal amount'),
        const SizedBox(height: 9),
        TextField(
          controller: _amount,
          keyboardType: const TextInputType.numberWithOptions(decimal: true),
          inputFormatters: [
            FilteringTextInputFormatter.allow(RegExp(r'[0-9.,]')),
          ],
          decoration: _decoration(
            'Amount ($_currency)',
            Icons.payments_outlined,
            hint:
                'Minimum ${moneyConverted(widget.api, _minimumMajor, _currency, _digits, _preferredCurrency, isMinor: false)}',
          ),
        ),
        if (_option != null) ...[const SizedBox(height: 9), _feeBreakdown()],
        if (_fields.isNotEmpty) ...[
          const SizedBox(height: 19),
          _stepLabel('4', 'Payout details'),
          const SizedBox(height: 5),
          Text(
            'Enter the destination details for this method.',
            style: TextStyle(
              color: Theme.of(context).colorScheme.onSurfaceVariant,
              fontSize: 11,
            ),
          ),
          const SizedBox(height: 10),
          ..._fields.map(
            (field) => Padding(
              padding: const EdgeInsets.only(bottom: 11),
              child: TextField(
                controller: _details[field],
                keyboardType: _keyboardFor(field),
                textCapitalization: _capitalizationFor(field),
                decoration: _decoration(
                  _pretty(field),
                  _fieldIcon(field),
                  hint: _fieldHint(field),
                ),
              ),
            ),
          ),
        ],
        const SizedBox(height: 6),
        SizedBox(
          width: double.infinity,
          height: 52,
          child: FilledButton(
            onPressed: _submitting || _wallets.isEmpty || _options.isEmpty
                ? null
                : _submit,
            style: FilledButton.styleFrom(
              backgroundColor: Theme.of(context).colorScheme.primary,
              disabledBackgroundColor: Theme.of(context).colorScheme.surfaceContainerHighest,
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(14),
              ),
              elevation: 0,
            ),
            child: _submitting
                ? SizedBox(
                    width: 20,
                    height: 20,
                    child: CircularProgressIndicator(
                      strokeWidth: 2,
                      color: Theme.of(context).colorScheme.onPrimary,
                    ),
                  )
                : const Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Icon(Icons.lock_outline_rounded, size: 17),
                      SizedBox(width: 8),
                      Text(
                        'Submit payout request',
                        style: TextStyle(
                          fontWeight: FontWeight.w800,
                          fontSize: 13,
                        ),
                      ),
                      SizedBox(width: 5),
                      Icon(Icons.arrow_forward_rounded, size: 17),
                    ],
                  ),
          ),
        ),
        const SizedBox(height: 9),
        Center(
          child: Text(
            'Your request will be reviewed before it is processed.',
            textAlign: TextAlign.center,
            style: TextStyle(
              color: Theme.of(context).colorScheme.onSurfaceVariant,
              fontSize: 10,
            ),
          ),
        ),
      ],
    ),
  );

  Widget _methodTile(Map<String, dynamic> option) {
    final selected =
        (option['id'] as num?)?.toInt() == (_option?['id'] as num?)?.toInt();
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: InkWell(
        onTap: () => setState(() {
          _option = option;
          _syncDetailFields();
        }),
        borderRadius: BorderRadius.circular(14),
        child: AnimatedContainer(
          duration: const Duration(milliseconds: 160),
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 11),
          decoration: BoxDecoration(
        color: selected ? Theme.of(context).colorScheme.primaryContainer : Theme.of(context).colorScheme.surfaceContainerLow,
            borderRadius: BorderRadius.circular(14),
            border: Border.all(
              color: selected
                  ? Theme.of(context).colorScheme.primary
                  : Theme.of(context).colorScheme.outlineVariant,
              width: selected ? 1.4 : 1,
            ),
          ),
          child: Row(
            children: [
              Container(
                width: 41,
                height: 41,
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  color: selected
                      ? Theme.of(context).colorScheme.secondaryContainer
                      : Theme.of(context).colorScheme.surfaceContainerHighest,
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Text(
                  option['icon']?.toString() ?? '•',
                  style: TextStyle(fontSize: 20),
                ),
              ),
              const SizedBox(width: 11),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      option['name']?.toString() ?? 'Payout method',
                      style: TextStyle(
                        color: Theme.of(context).colorScheme.onSurface,
                        fontWeight: FontWeight.w800,
                        fontSize: 13,
                      ),
                    ),
                    const SizedBox(height: 3),
                    Text(
                      '${option['tax'] ?? '0'}% method fee',
                      style: TextStyle(
                        color: Theme.of(context).colorScheme.onSurfaceVariant,
                        fontSize: 10,
                      ),
                    ),
                  ],
                ),
              ),
              Icon(
                selected
                    ? Icons.radio_button_checked_rounded
                    : Icons.radio_button_off_rounded,
                color: selected
                    ? Theme.of(context).colorScheme.primary
                    : Theme.of(context).colorScheme.onSurfaceVariant,
                size: 21,
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _feeBreakdown() {
    final minimum = _minimumMajor;
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: Theme.of(context).colorScheme.surfaceContainerHighest,
        borderRadius: BorderRadius.circular(13),
      ),
      child: Column(
        children: [
          if (minimum > 0)
            _feeLine(
              'Minimum withdrawal',
              moneyConverted(
                widget.api,
                minimum,
                _currency,
                _digits,
                _preferredCurrency,
                isMinor: false,
              ),
            ),
          _feeLine(
            'Method fee (${_feeRate.toStringAsFixed(2)}%)',
            _enteredAmount > 0
                ? moneyConverted(
                    widget.api,
                    _fee,
                    _currency,
                    _digits,
                    _preferredCurrency,
                    isMinor: false,
                  )
                : '—',
          ),
          Divider(height: 15, color: Theme.of(context).colorScheme.outlineVariant),
          _feeLine(
            'Estimated payout',
            _enteredAmount > 0
                ? moneyConverted(
                    widget.api,
                    math.max(0, _enteredAmount - _fee).toDouble(),
                    _currency,
                    _digits,
                    _preferredCurrency,
                    isMinor: false,
                  )
                : '—',
            bold: true,
          ),
        ],
      ),
    );
  }

  Widget _feeLine(String label, String value, {bool bold = false}) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 3),
    child: Row(
      children: [
        Expanded(
          child: Text(
            label,
            style: TextStyle(
              color: bold
                  ? Theme.of(context).colorScheme.onSurface
                  : Theme.of(context).colorScheme.onSurfaceVariant,
              fontSize: bold ? 12 : 10,
              fontWeight: bold ? FontWeight.w800 : FontWeight.w500,
            ),
          ),
        ),
        Text(
          value,
          style: TextStyle(
            color: bold
                ? Theme.of(context).colorScheme.primary
                : Theme.of(context).colorScheme.onSurface,
            fontSize: bold ? 13 : 11,
            fontWeight: FontWeight.w800,
          ),
        ),
      ],
    ),
  );

  Widget _emptyRequests() => Container(
    padding: const EdgeInsets.symmetric(vertical: 24, horizontal: 18),
    decoration: BoxDecoration(
      color: Theme.of(context).colorScheme.surfaceContainerLow,
      borderRadius: BorderRadius.circular(17),
      border: Border.all(color: Theme.of(context).colorScheme.outlineVariant),
    ),
    child: Column(
      children: [
        _bubble(Icons.receipt_long_outlined),
        const SizedBox(height: 10),
        Text(
          'Nothing here yet',
          style: TextStyle(
            color: Theme.of(context).colorScheme.onSurface,
            fontWeight: FontWeight.w800,
            fontSize: 14,
          ),
        ),
        const SizedBox(height: 4),
        Text(
          'Your payout requests will appear here once submitted.',
          textAlign: TextAlign.center,
          style: TextStyle(
            color: Theme.of(context).colorScheme.onSurfaceVariant,
            fontSize: 11,
            height: 1.4,
          ),
        ),
      ],
    ),
  );

  Widget _securityNote() => Container(
    padding: const EdgeInsets.all(13),
    decoration: BoxDecoration(
      color: Theme.of(context).colorScheme.primaryContainer,
      borderRadius: BorderRadius.circular(14),
    ),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Icon(
          Icons.shield_outlined,
          color: Theme.of(context).colorScheme.onPrimaryContainer,
          size: 19,
        ),
        SizedBox(width: 9),
        Expanded(
          child: Text(
            'For your protection, only withdraw earnings from eligible wallets. Never share your security PIN with anyone.',
            style: TextStyle(
              color: Theme.of(context).colorScheme.onPrimaryContainer,
              fontSize: 10,
              height: 1.45,
            ),
          ),
        ),
      ],
    ),
  );

  Widget _heading(String title, String subtitle, {String? trailing}) => Row(
    crossAxisAlignment: CrossAxisAlignment.end,
    children: [
      Expanded(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              title,
              style: TextStyle(
                color: Theme.of(context).colorScheme.onSurface,
                fontSize: 17,
                fontWeight: FontWeight.w900,
              ),
            ),
            const SizedBox(height: 3),
            Text(
              subtitle,
              style: TextStyle(
                color: Theme.of(context).colorScheme.onSurfaceVariant,
                fontSize: 11,
              ),
            ),
          ],
        ),
      ),
      if (trailing != null)
        Text(
          trailing,
          style: TextStyle(
            color: Theme.of(context).colorScheme.primary,
            fontSize: 10,
            fontWeight: FontWeight.w800,
          ),
        ),
    ],
  );

  Widget _stepLabel(String number, String label) => Row(
    children: [
      Container(
        width: 23,
        height: 23,
        alignment: Alignment.center,
        decoration: BoxDecoration(
          color: Theme.of(context).colorScheme.primaryContainer,
          shape: BoxShape.circle,
        ),
        child: Text(
          number,
          style: TextStyle(
            color: Theme.of(context).colorScheme.primary,
            fontSize: 11,
            fontWeight: FontWeight.w900,
          ),
        ),
      ),
      const SizedBox(width: 8),
      Text(
        label,
        style: TextStyle(
          color: Theme.of(context).colorScheme.onSurface,
          fontSize: 12,
          fontWeight: FontWeight.w800,
        ),
      ),
    ],
  );

  Widget _notice(String message) => Container(
    width: double.infinity,
    margin: const EdgeInsets.only(top: 8),
    padding: const EdgeInsets.all(11),
    decoration: BoxDecoration(
      color: Theme.of(context).colorScheme.tertiaryContainer,
      borderRadius: BorderRadius.circular(12),
    ),
    child: Row(
      children: [
        Icon(Icons.info_outline_rounded, color: Theme.of(context).colorScheme.onTertiaryContainer, size: 17),
        const SizedBox(width: 8),
        Expanded(
          child: Text(
            message,
            style: TextStyle(
              color: Theme.of(context).colorScheme.onTertiaryContainer,
              fontSize: 10,
              height: 1.4,
            ),
          ),
        ),
      ],
    ),
  );

  InputDecoration _decoration(String label, IconData icon, {String? hint}) =>
      InputDecoration(
        labelText: label,
        hintText: hint,
        prefixIcon: Icon(icon, size: 18, color: Theme.of(context).colorScheme.onSurfaceVariant),
        filled: true,
        fillColor: Theme.of(context).colorScheme.surfaceContainerHighest,
        contentPadding: const EdgeInsets.symmetric(
          horizontal: 13,
          vertical: 14,
        ),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(13),
          borderSide: BorderSide(color: Theme.of(context).colorScheme.outlineVariant),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(13),
          borderSide: BorderSide(color: Theme.of(context).colorScheme.outlineVariant),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(13),
          borderSide: BorderSide(
            color: Theme.of(context).colorScheme.primary,
            width: 1.4,
          ),
        ),
      );

  Widget _bubble(IconData icon) => Container(
    width: 43,
    height: 43,
    decoration: BoxDecoration(
      color: Theme.of(context).colorScheme.primaryContainer,
      borderRadius: BorderRadius.circular(13),
    ),
    child: Icon(icon, color: Theme.of(context).colorScheme.primary, size: 21),
  );

  TextInputType _keyboardFor(String field) {
    final key = field.toLowerCase();
    if (key.contains('email')) return TextInputType.emailAddress;
    if (key.contains('phone') || key.contains('account_number'))
      return TextInputType.phone;
    return TextInputType.text;
  }

  TextCapitalization _capitalizationFor(String field) {
    final key = field.toLowerCase();
    return key.contains('email') ||
            key.contains('number') ||
            key.contains('phone')
        ? TextCapitalization.none
        : TextCapitalization.words;
  }

  String _fieldHint(String field) {
    final key = field.toLowerCase();
    if (key.contains('email')) return 'name@example.com';
    if (key.contains('phone')) return 'Enter mobile number';
    if (key.contains('account_number')) return 'Enter account number';
    if (key.contains('wallet_address')) return 'Paste wallet address';
    return 'Enter ${_pretty(field).toLowerCase()}';
  }

  IconData _fieldIcon(String field) {
    final key = field.toLowerCase();
    if (key.contains('email')) return Icons.alternate_email_rounded;
    if (key.contains('phone')) return Icons.phone_rounded;
    if (key.contains('account')) return Icons.account_balance_outlined;
    if (key.contains('wallet')) return Icons.currency_bitcoin_rounded;
    if (key.contains('bank')) return Icons.account_balance_rounded;
    return Icons.edit_outlined;
  }
}

class _PayoutRow extends StatelessWidget {
  const _PayoutRow({
    required this.request,
    required this.api,
    required this.preferredCurrency,
  });
  final Map<String, dynamic> request;
  final AuthApi api;
  final String preferredCurrency;

  @override
  Widget build(BuildContext context) {
    final option = request['option'] is Map
        ? request['option'] as Map
        : const {};
    final amount = (request['amount_minor'] as num?)?.toInt() ?? 0;
    final fee = (request['fee_minor'] as num?)?.toInt();
    final currency = request['currency']?.toString() ?? 'USD';
    final status = request['status']?.toString() ?? 'pending';
    final packageTerms = request['package_withdrawal_terms_snapshot'] is Map
        ? request['package_withdrawal_terms_snapshot'] as Map
        : const {};
    final packageDays = (packageTerms['withdrawal_days'] as List? ?? const [])
        .join(', ');
    final date =
        request['requested_at']?.toString() ??
        request['created_at']?.toString();
    final color = _statusColor(context, status.toLowerCase());
    return Container(
      padding: const EdgeInsets.all(15),
      decoration: BoxDecoration(
      color: Theme.of(context).colorScheme.surfaceContainerLow,
        borderRadius: BorderRadius.circular(17),
      border: Border.all(color: Theme.of(context).colorScheme.outlineVariant),
      ),
      child: Column(
        children: [
          Row(
            children: [
              Container(
                width: 42,
                height: 42,
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  color: Theme.of(context).colorScheme.primaryContainer,
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Text(
                  option['icon']?.toString() ?? '💸',
                  style: TextStyle(fontSize: 19),
                ),
              ),
              const SizedBox(width: 11),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      option['name']?.toString() ?? 'Withdrawal',
                      style: TextStyle(
                        color: Theme.of(context).colorScheme.onSurface,
                        fontSize: 13,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const SizedBox(height: 3),
                    Text(
                      date == null ? 'Date unavailable' : _formatDate(date),
                      style: TextStyle(
                        color: Theme.of(context).colorScheme.onSurfaceVariant,
                        fontSize: 10,
                      ),
                    ),
                    if (packageTerms['plan_name'] != null)
                      Text(
                        '${packageTerms['plan_name']}${packageDays.isEmpty ? '' : ' · $packageDays'}',
                        style: TextStyle(
                          color: Theme.of(context).colorScheme.onSurfaceVariant,
                          fontSize: 9,
                        ),
                      ),
                  ],
                ),
              ),
              Column(
                crossAxisAlignment: CrossAxisAlignment.end,
                children: [
                  Text(
                    moneyConverted(
                      api,
                      amount,
                      currency,
                      null,
                      preferredCurrency,
                      isMinor: true,
                    ),
                    style: TextStyle(
                      color: Theme.of(context).colorScheme.onSurface,
                      fontSize: 14,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                  const SizedBox(height: 5),
                  Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 9,
                      vertical: 4,
                    ),
                    decoration: BoxDecoration(
                      color: color.withValues(alpha: .10),
                      borderRadius: BorderRadius.circular(20),
                    ),
                    child: Text(
                      _pretty(status),
                      style: TextStyle(
                        color: color,
                        fontSize: 9,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ),
                ],
              ),
            ],
          ),
          if (fee != null) ...[
            Padding(
              padding: EdgeInsets.symmetric(vertical: 11),
              child: Divider(height: 1, color: Theme.of(context).colorScheme.outlineVariant),
            ),
            Row(
              children: [
                Expanded(
                  child: Text(
                    'Processing fee',
                    style: TextStyle(
                      color: Theme.of(context).colorScheme.onSurfaceVariant,
                      fontSize: 10,
                    ),
                  ),
                ),
                Text(
                  moneyConverted(
                    api,
                    fee,
                    currency,
                    null,
                    preferredCurrency,
                    isMinor: true,
                  ),
                  style: TextStyle(
                    color: Theme.of(context).colorScheme.onSurface,
                    fontSize: 10,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ],
            ),
          ],
        ],
      ),
    );
  }
}

Color _statusColor(BuildContext context, String status) {
  final colors = Theme.of(context).colorScheme;
  if (['paid', 'completed', 'approved'].contains(status)) return colors.primary;
  if (['processing', 'pending', 'review'].contains(status))
    return colors.tertiary;
  if (['failed', 'rejected', 'cancelled'].contains(status)) return colors.error;
  return colors.onSurfaceVariant;
}

String _pretty(String value) => value
    .replaceAll('_', ' ')
    .split(' ')
    .map(
      (word) =>
          word.isEmpty ? word : '${word[0].toUpperCase()}${word.substring(1)}',
    )
    .join(' ');

String _formatDate(String value) {
  final date = DateTime.tryParse(value)?.toLocal();
  if (date == null) return value;
  const months = [
    'Jan',
    'Feb',
    'Mar',
    'Apr',
    'May',
    'Jun',
    'Jul',
    'Aug',
    'Sep',
    'Oct',
    'Nov',
    'Dec',
  ];
  final hour = date.hour % 12 == 0 ? 12 : date.hour % 12;
  final minute = date.minute.toString().padLeft(2, '0');
  return '${months[date.month - 1]} ${date.day}, ${date.year} • $hour:$minute ${date.hour >= 12 ? 'PM' : 'AM'}';
}
