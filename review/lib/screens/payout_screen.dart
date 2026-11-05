import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

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

  List<String> get _fields => (_option?['fields'] as List? ?? const [])
      .map((field) => field.toString())
      .toList();
  String get _currency => _wallet?['currency']?.toString() ?? 'USD';
  int get _digits {
    const zero = {'JPY', 'KRW', 'XOF', 'XAF', 'CLP', 'BIF'};
    const three = {'KWD', 'BHD', 'TND'};
    return zero.contains(_currency) ? 0 : (three.contains(_currency) ? 3 : 2);
  }

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _amount.dispose();
    for (final controller in _details.values) {
      controller.dispose();
    }
    super.dispose();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
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
            (wallet) =>
                wallet['type'] != 'topup_fund' &&
                (widget.walletId == null ||
                    (wallet['id'] as num?)?.toInt() == widget.walletId),
          )
          .toList();
      final options = list(results[1]['data']);
      final requests = list(results[2]['data']);
      final user = results[3]['data'];
      if (!mounted) return;
      setState(() {
        _wallets = wallets;
        _options = options;
        _requests = requests;
        _securityPinSet =
            user is Map<String, dynamic> && user['security_pin_set'] == true;
        _wallet =
            wallets
                .where(
                  (wallet) =>
                      (wallet['id'] as num?)?.toInt() == widget.walletId,
                )
                .firstOrNull ??
            wallets.firstOrNull;
        _option = options.firstOrNull;
        _loading = false;
      });
      _syncDetailFields();
    } on AuthException catch (error) {
      if (mounted)
        setState(() {
          _error = error.message;
          _loading = false;
        });
    }
  }

  void _syncDetailFields() {
    for (final field in _fields) {
      _details.putIfAbsent(field, TextEditingController.new);
    }
  }

  Future<void> _submit() async {
    final wallet = _wallet;
    final option = _option;
    if (wallet == null || option == null) {
      showAuthMessage(context, 'Choose a wallet and withdrawal method.');
      return;
    }
    final major = double.tryParse(_amount.text.trim().replaceAll(',', ''));
    if (major == null || major <= 0) {
      showAuthMessage(context, 'Enter a valid withdrawal amount.');
      return;
    }
    final amountMinor = (major * math.pow(10, _digits)).round();
    final payout = {
      for (final field in _fields) field: _details[field]?.text.trim() ?? '',
    };
    if (payout.values.any((value) => value.isEmpty)) {
      showAuthMessage(context, 'Complete all payout details for this method.');
      return;
    }
    final securityPin = _securityPinSet ? await _askForSecurityPin() : null;
    if (_securityPinSet && securityPin == null) return;
    setState(() => _submitting = true);
    try {
      await widget.api.post('withdrawals', widget.token, {
        'wallet_id': wallet['id'],
        'withdrawal_option_id': option['id'],
        'amount_minor': amountMinor,
        'payout_details': payout,
        'idempotency_key':
            'withdraw-mobile-${DateTime.now().microsecondsSinceEpoch}',
        if (securityPin != null) 'security_pin': securityPin,
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
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  Future<String?> _askForSecurityPin() {
    final pin = TextEditingController();
    return showDialog<String>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Confirm withdrawal'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Text(
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
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(
              dialogContext,
              pin.text.length == 4 ? pin.text : null,
            ),
            child: const Text('Continue'),
          ),
        ],
      ),
    ).whenComplete(pin.dispose);
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    backgroundColor: Theme.of(context).scaffoldBackgroundColor,
    appBar: AppBar(
      title: const Text(
        'Payout',
        style: TextStyle(fontWeight: FontWeight.w800),
      ),
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
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
                  Text(
                    _error!,
                    textAlign: TextAlign.center,
                    style: const TextStyle(color: mutedInk),
                  ),
                  TextButton(onPressed: _load, child: const Text('Retry')),
                ],
              ),
            ),
          )
        : RefreshIndicator(
            onRefresh: _load,
            child: ListView(
              padding: const EdgeInsets.all(17),
              children: [
                Container(
                  padding: const EdgeInsets.all(17),
                  decoration: BoxDecoration(
                    gradient: const LinearGradient(
                      colors: [Color(0xFF145A3D), Color(0xFF28704F)],
                    ),
                    borderRadius: BorderRadius.circular(18),
                  ),
                  child: const Row(
                    children: [
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              'Withdraw earnings',
                              style: TextStyle(
                                color: Colors.white,
                                fontSize: 18,
                                fontWeight: FontWeight.w800,
                              ),
                            ),
                            SizedBox(height: 6),
                            Text(
                              'Choose an earned wallet and payout method. Top-up funds cannot be withdrawn.',
                              style: TextStyle(
                                color: Colors.white70,
                                fontSize: 11,
                                height: 1.4,
                              ),
                            ),
                          ],
                        ),
                      ),
                      Icon(
                        Icons.account_balance_wallet_outlined,
                        color: Color(0xFFF2D48C),
                        size: 31,
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 16),
                _panel(
                  children: [
                    const Text(
                      'Request a payout',
                      style: TextStyle(
                        color: ink,
                        fontWeight: FontWeight.w800,
                        fontSize: 16,
                      ),
                    ),
                    const SizedBox(height: 14),
                    DropdownButtonFormField<int>(
                      initialValue: (_wallet?['id'] as num?)?.toInt(),
                      decoration: const InputDecoration(
                        labelText: 'Earnings wallet',
                      ),
                      items: _wallets
                          .map(
                            (wallet) => DropdownMenuItem(
                              value: (wallet['id'] as num).toInt(),
                              child: Text(
                                '${_pretty(wallet['type'].toString())} • ${_formatMinor((wallet['available_balance_minor'] as num?)?.toInt() ?? 0, wallet['currency']?.toString() ?? 'USD')}',
                              ),
                            ),
                          )
                          .toList(),
                      onChanged: (id) => setState(
                        () => _wallet = _wallets
                            .where(
                              (wallet) => (wallet['id'] as num).toInt() == id,
                            )
                            .firstOrNull,
                      ),
                    ),
                    const SizedBox(height: 12),
                    DropdownButtonFormField<int>(
                      initialValue: (_option?['id'] as num?)?.toInt(),
                      decoration: const InputDecoration(
                        labelText: 'Payout method',
                      ),
                      items: _options
                          .map(
                            (option) => DropdownMenuItem(
                              value: (option['id'] as num).toInt(),
                              child: Text(
                                '${option['icon'] ?? '•'}  ${option['name']}',
                              ),
                            ),
                          )
                          .toList(),
                      onChanged: (id) => setState(() {
                        _option = _options
                            .where(
                              (option) => (option['id'] as num).toInt() == id,
                            )
                            .firstOrNull;
                        _syncDetailFields();
                      }),
                    ),
                    if (_option?['description'] != null)
                      Padding(
                        padding: const EdgeInsets.only(top: 7),
                        child: Text(
                          _option!['description'].toString(),
                          style: const TextStyle(color: mutedInk, fontSize: 11),
                        ),
                      ),
                    const SizedBox(height: 12),
                    TextField(
                      controller: _amount,
                      keyboardType: const TextInputType.numberWithOptions(
                        decimal: true,
                      ),
                      decoration: InputDecoration(
                        labelText: 'Amount ($_currency)',
                        hintText: 'Minimum ${_option?['min_withdrawal'] ?? ''}',
                      ),
                    ),
                    if (_option?['tax'] != null)
                      Padding(
                        padding: const EdgeInsets.only(top: 6),
                        child: Text(
                          'Method fee: ${_option!['tax']}% of the requested amount.',
                          style: const TextStyle(color: mutedInk, fontSize: 11),
                        ),
                      ),
                    if (_fields.isNotEmpty) ...[
                      const SizedBox(height: 15),
                      const Text(
                        'Payout details',
                        style: TextStyle(
                          color: ink,
                          fontWeight: FontWeight.w700,
                          fontSize: 13,
                        ),
                      ),
                      ..._fields.map(
                        (field) => Padding(
                          padding: const EdgeInsets.only(top: 10),
                          child: TextField(
                            controller: _details[field],
                            decoration: InputDecoration(
                              labelText: _pretty(field),
                              hintText: _pretty(field),
                            ),
                          ),
                        ),
                      ),
                    ],
                    const SizedBox(height: 17),
                    FilledButton(
                      onPressed:
                          _submitting || _wallets.isEmpty || _options.isEmpty
                          ? null
                          : _submit,
                      style: FilledButton.styleFrom(
                        backgroundColor: brandGreen,
                        minimumSize: const Size.fromHeight(50),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(13),
                        ),
                      ),
                      child: _submitting
                          ? const SizedBox(
                              width: 20,
                              height: 20,
                              child: CircularProgressIndicator(
                                strokeWidth: 2,
                                color: Colors.white,
                              ),
                            )
                          : const Text('Submit withdrawal request'),
                    ),
                  ],
                ),
                const SizedBox(height: 21),
                const Text(
                  'Recent payout requests',
                  style: TextStyle(
                    color: ink,
                    fontWeight: FontWeight.w800,
                    fontSize: 17,
                  ),
                ),
                const SizedBox(height: 10),
                if (_requests.isEmpty)
                  _panel(
                    children: const [
                      Text(
                        'No payout requests yet.',
                        style: TextStyle(color: mutedInk, fontSize: 12),
                      ),
                    ],
                  )
                else
                  ..._requests
                      .take(10)
                      .map(
                        (request) => Padding(
                          padding: const EdgeInsets.only(bottom: 9),
                          child: _PayoutRow(request: request),
                        ),
                      ),
              ],
            ),
          ),
  );
}

Widget _panel({required List<Widget> children}) => Container(
  padding: const EdgeInsets.all(16),
  decoration: BoxDecoration(
    color: Colors.white,
    borderRadius: BorderRadius.circular(16),
    border: Border.all(color: const Color(0xFFE6EAE5)),
  ),
  child: Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: children,
  ),
);
String _pretty(String value) => value
    .replaceAll('_', ' ')
    .split(' ')
    .map(
      (word) =>
          word.isEmpty ? word : '${word[0].toUpperCase()}${word.substring(1)}',
    )
    .join(' ');
String _formatMinor(int amount, String currency) {
  const zero = {'JPY', 'KRW', 'XOF', 'XAF', 'CLP', 'BIF'};
  const three = {'KWD', 'BHD', 'TND'};
  final digits = zero.contains(currency)
      ? 0
      : (three.contains(currency) ? 3 : 2);
  final symbols = {'USD': r'$', 'NGN': '₦', 'GBP': '£', 'EUR': '€', 'GHS': '₵'};
  final value = amount / math.pow(10, digits);
  return '${symbols[currency] ?? '$currency '}${value.toStringAsFixed(digits)}';
}

class _PayoutRow extends StatelessWidget {
  const _PayoutRow({required this.request});
  final Map<String, dynamic> request;
  @override
  Widget build(BuildContext context) {
    final option = request['option'] is Map
        ? request['option'] as Map
        : const {};
    final amount = (request['amount_minor'] as num?)?.toInt() ?? 0;
    final currency = request['currency']?.toString() ?? 'USD';
    return _panel(
      children: [
        Row(
          children: [
            const Icon(
              Icons.account_balance_wallet_outlined,
              color: brandGreen,
            ),
            const SizedBox(width: 9),
            Expanded(
              child: Text(
                option['name']?.toString() ?? 'Withdrawal',
                style: const TextStyle(color: ink, fontWeight: FontWeight.w700),
              ),
            ),
            Text(
              _formatMinor(amount, currency),
              style: const TextStyle(
                color: brandGreen,
                fontWeight: FontWeight.w800,
              ),
            ),
          ],
        ),
        const SizedBox(height: 7),
        Text(
          'Status: ${_pretty(request['status']?.toString() ?? 'pending')}',
          style: const TextStyle(color: mutedInk, fontSize: 11),
        ),
      ],
    );
  }
}
