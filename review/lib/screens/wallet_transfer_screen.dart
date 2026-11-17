import 'package:flutter/material.dart';

import '../core/currency/money.dart';
import '../services/auth_api.dart';
import '../widgets/auth_components.dart';

class WalletTransferScreen extends StatefulWidget {
  const WalletTransferScreen({
    super.key,
    required this.api,
    required this.token,
    this.sourceWalletId,
  });
  final AuthApi api;
  final String token;
  final int? sourceWalletId;
  @override
  State<WalletTransferScreen> createState() => _WalletTransferScreenState();
}

class _WalletTransferScreenState extends State<WalletTransferScreen> {
  final _amount = TextEditingController();
  bool _loading = true;
  bool _sending = false;
  String? _error;
  List<Map<String, dynamic>> _wallets = const [];
  Map<String, dynamic>? _source;
  String _target = 'review_group_b';
  static const _targets = {
    'review_group_b': 'Play Store & Galaxy review wallet',
    'review_group_c': 'Apple Store review wallet',
    'share': 'Share wallet',
    'referral': 'Referral wallet',
    'bonus': 'Bonus wallet',
  };

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _amount.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      await widget.api.loadExchangeRates(widget.token);
      final wallets = (await widget.api.wallets(widget.token))
          .where(
            (wallet) =>
                wallet['type'] == 'topup_fund' &&
                (widget.sourceWalletId == null ||
                    (wallet['id'] as num?)?.toInt() == widget.sourceWalletId),
          )
          .toList();
      if (!mounted) return;
      setState(() {
        _wallets = wallets;
        _source =
            wallets
                .where(
                  (wallet) =>
                      (wallet['id'] as num?)?.toInt() == widget.sourceWalletId,
                )
                .firstOrNull ??
            wallets.firstOrNull;
        _loading = false;
      });
    } on AuthException catch (error) {
      if (mounted)
        setState(() {
          _error = error.message;
          _loading = false;
        });
    }
  }

  Future<void> _submit() async {
    final source = _source;
    if (source == null) {
      showAuthMessage(context, 'There is no top-up wallet available.');
      return;
    }
    final currency = source['currency']?.toString().toUpperCase() ?? 'USD';
    final digits = currencyPrecision(currency);
    final amount = double.tryParse(_amount.text.trim().replaceAll(',', ''));
    if (amount == null || amount <= 0) {
      showAuthMessage(context, 'Enter a valid transfer amount.');
      return;
    }
    final minor = (amount * _pow10(digits)).round();
    final available = (source['available_balance_minor'] as num?)?.toInt() ?? 0;
    if (minor > available) {
      showAuthMessage(
        context,
        'The transfer is higher than your available top-up balance.',
      );
      return;
    }
    setState(() => _sending = true);
    try {
      await widget.api.post('wallets/topup-transfer', widget.token, {
        'amount_minor': minor,
        'target_wallet': _target,
        'idempotency_key':
            'wallet-transfer-${DateTime.now().microsecondsSinceEpoch}',
      });
      if (!mounted) return;
      _amount.clear();
      showAuthMessage(
        context,
        '${formatMajorMoney(amount, currency)} moved to ${_targets[_target]}.',
      );
      await _load();
    } on AuthException catch (error) {
      if (mounted) showAuthMessage(context, error.message);
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    backgroundColor: Theme.of(context).scaffoldBackgroundColor,
    appBar: AppBar(
      title: const Text(
        'Move top-up funds',
        style: TextStyle(fontWeight: FontWeight.w800),
      ),
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
    ),
    body: _loading
        ? const Center(child: CircularProgressIndicator())
        : _error != null
        ? Center(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(_error!, style: const TextStyle(color: mutedInk)),
                TextButton(onPressed: _load, child: const Text('Retry')),
              ],
            ),
          )
        : ListView(
            padding: const EdgeInsets.all(17),
            children: [
              Container(
                padding: const EdgeInsets.all(17),
                decoration: BoxDecoration(
                  gradient: const LinearGradient(
                    colors: [Color(0xFF145A3D), Color(0xFF28704F)],
                  ),
                  borderRadius: BorderRadius.circular(17),
                ),
                child: const Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Transfer without a fee',
                      style: TextStyle(
                        color: Colors.white,
                        fontSize: 18,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    SizedBox(height: 6),
                    Text(
                      'Top-up funds can be moved to a reward wallet for withdrawal. Top-up funds themselves cannot be withdrawn.',
                      style: TextStyle(
                        color: Colors.white70,
                        fontSize: 12,
                        height: 1.45,
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 15),
              _panel(
                children: [
                  const Text(
                    'From',
                    style: TextStyle(color: ink, fontWeight: FontWeight.w800),
                  ),
                  const SizedBox(height: 9),
                  if (_wallets.isEmpty)
                    const Text(
                      'Top up your account before transferring funds.',
                      style: TextStyle(color: mutedInk, fontSize: 12),
                    )
                  else ...[
                    DropdownButtonFormField<int>(
                      initialValue: (_source?['id'] as num?)?.toInt(),
                      decoration: const InputDecoration(
                        labelText: 'Top-up wallet',
                      ),
                      items: _wallets.map((wallet) {
                        final currency =
                            wallet['currency']?.toString() ?? 'USD';
                        final digits = currencyPrecision(currency);
                        final balance =
                            (wallet['available_balance_minor'] as num?)
                                ?.toInt() ??
                            0;
                        return DropdownMenuItem(
                          value: (wallet['id'] as num).toInt(),
                          child: Text(
                            '${moneyConverted(widget.api, balance, currency, digits, widget.api.preferredCurrency, isMinor: true)} available · $currency',
                          ),
                        );
                      }).toList(),
                      onChanged: (id) => setState(
                        () => _source = _wallets
                            .where(
                              (wallet) => (wallet['id'] as num).toInt() == id,
                            )
                            .firstOrNull,
                      ),
                    ),
                    const SizedBox(height: 15),
                    DropdownButtonFormField<String>(
                      initialValue: _target,
                      decoration: const InputDecoration(labelText: 'To'),
                      items: _targets.entries
                          .map(
                            (entry) => DropdownMenuItem(
                              value: entry.key,
                              child: Text(entry.value),
                            ),
                          )
                          .toList(),
                      onChanged: (value) =>
                          setState(() => _target = value ?? 'review_group_b'),
                    ),
                    const SizedBox(height: 13),
                    TextField(
                      controller: _amount,
                      keyboardType: const TextInputType.numberWithOptions(
                        decimal: true,
                      ),
                      decoration: InputDecoration(
                        labelText: 'Amount (${_source?['currency'] ?? 'USD'})',
                        hintText: 'Enter amount',
                      ),
                    ),
                    const SizedBox(height: 7),
                    const Text(
                      'No transfer fee. The amount and currency stay the same.',
                      style: TextStyle(color: mutedInk, fontSize: 11),
                    ),
                    const SizedBox(height: 15),
                    FilledButton(
                      onPressed: _sending ? null : _submit,
                      style: FilledButton.styleFrom(
                        backgroundColor: brandGreen,
                        minimumSize: const Size.fromHeight(49),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(12),
                        ),
                      ),
                      child: _sending
                          ? const SizedBox(
                              width: 19,
                              height: 19,
                              child: CircularProgressIndicator(
                                strokeWidth: 2,
                                color: Colors.white,
                              ),
                            )
                          : const Text('Transfer funds'),
                    ),
                  ],
                ],
              ),
            ],
          ),
  );
}

Widget _panel({required List<Widget> children}) => Container(
  padding: const EdgeInsets.all(16),
  decoration: BoxDecoration(
    color: Colors.white,
    borderRadius: BorderRadius.circular(16),
    border: Border.all(color: const Color(0xFFE5E9E4)),
  ),
  child: Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: children,
  ),
);

double _pow10(int digits) => digits == 0 ? 1 : (digits == 3 ? 1000 : 100);
