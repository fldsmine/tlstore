import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../../core/currency/money.dart';
import '../../../../core/utils/helper.dart';
import '../../../../services/auth_api.dart';

class PackageAddonPurchaseSheet extends StatefulWidget {
  const PackageAddonPurchaseSheet({
    super.key,
    required this.api,
    required this.token,
    required this.addonState,
  });
  final AuthApi api;
  final String token;
  final Map<String, dynamic> addonState;

  @override
  State<PackageAddonPurchaseSheet> createState() =>
      _PackageAddonPurchaseSheetState();
}

class _PackageAddonPurchaseSheetState extends State<PackageAddonPurchaseSheet> {
  bool _busy = false;
  String? _message;
  String? _selectedKey;
  Object? _pendingIntent;

  Map<String, dynamic> get _addon => _map(widget.addonState['addon']);
  List<Map<String, dynamic>> get _options =>
      _rows(widget.addonState['checkout_options']);
  Map<String, dynamic>? get _selected {
    for (final option in _options) {
      if (option['option_key']?.toString() == _selectedKey) return option;
    }
    return null;
  }

  Future<void> _payWithFunds() async {
    setState(() {
      _busy = true;
      _message = null;
    });
    try {
      await widget.api.purchasePackageAddonWithFunds(
        token: widget.token,
        addonId: _integer(_addon['id']),
        idempotencyKey:
            'subscription-addon-wallet-${DateTime.now().microsecondsSinceEpoch}',
      );
      if (mounted) Navigator.pop(context, 'paid');
    } on AuthException catch (error) {
      if (mounted)
        setState(() {
          _busy = false;
          _message = error.message;
        });
    }
  }

  Future<void> _securePayment() async {
    final option = _selected;
    if (option == null) {
      setState(() => _message = 'Select a payment currency and gateway first.');
      return;
    }
    setState(() {
      _busy = true;
      _message = null;
    });
    try {
      final response = await widget.api.checkoutPackageAddon(
        token: widget.token,
        addonId: _integer(_addon['id']),
        gateway: option['gateway'].toString(),
        currency: option['currency'].toString(),
        idempotencyKey:
            'subscription-addon-checkout-${DateTime.now().microsecondsSinceEpoch}',
      );
      final data = _map(response['data']);
      final url = Uri.tryParse(data['checkout_url']?.toString() ?? '');
      _pendingIntent = data['intent_id'];
      if (url == null ||
          url.scheme != 'https' ||
          _pendingIntent == null ||
          !await launchUrl(url, mode: LaunchMode.inAppBrowserView)) {
        throw const AuthException('Could not open secure checkout. Try again.');
      }
      await _checkPayment();
    } on AuthException catch (error) {
      if (mounted)
        setState(() {
          _busy = false;
          _message = error.message;
        });
    }
  }

  Future<void> _checkPayment() async {
    final intent = _pendingIntent;
    if (intent == null) return;
    setState(() {
      _busy = true;
      _message = null;
    });
    try {
      final response = await widget.api.checkPackageAddonPayment(
        widget.token,
        intent,
      );
      final status = _map(response['data'])['status']?.toString();
      if (status == 'paid') {
        if (mounted) Navigator.pop(context, 'paid');
      } else if (mounted) {
        setState(() {
          _busy = false;
          _message = 'Payment is $status. Finish checkout, then check again.';
        });
      }
    } on AuthException catch (error) {
      if (mounted)
        setState(() {
          _busy = false;
          _message = error.message;
        });
    }
  }

  CurrencyOption? _currencyInfo(String currency) {
    final code = currency.toUpperCase();

    for (final item in supportedCurrencies) {
      if (item.code.toUpperCase() == code) {
        return item;
      }
    }

    return null;
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final addon = _addon;
    final currency = addon['currency']?.toString() ?? 'USD';
    final listed = _integer(widget.addonState['listed_price_minor']);
    final payable = _integer(widget.addonState['payable_price_minor']);
    final discount = double.tryParse(addon['discount']?.toString() ?? '') ?? 0;
    final selected = _selected;
    final selectedCurrency = selected?['currency']?.toString() ?? currency;
    final selectedAmount = selected == null
        ? null
        : _integer(selected['amount_minor']);
    return SingleChildScrollView(
      child: Padding(
        padding: EdgeInsets.fromLTRB(
          22,
          8,
          22,
          20 + MediaQuery.viewInsetsOf(context).bottom,
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              '${addon['name'] ?? 'review'} Proxy',
              style: theme.textTheme.headlineSmall?.copyWith(
                fontWeight: FontWeight.w800,
              ),
            ),
            const SizedBox(height: 7),
            Text.rich(
              TextSpan(
                children: [
                  if (discount > 0)
                    TextSpan(
                      text:
                          '${moneyConverted(widget.api, listed, currency, widget.addonState['currency_minor_units'], selected == null ? widget.api.preferredCurrency : selectedCurrency, isMinor: true)}  ',
                      style: TextStyle(
                        decoration: TextDecoration.lineThrough,
                        color: theme.colorScheme.onSurfaceVariant,
                      ),
                    ),
                  TextSpan(
                    text: selectedAmount == null
                        ? '${moneyConverted(widget.api, payable, currency, widget.addonState['currency_minor_units'], widget.api.preferredCurrency, isMinor: true)} / month'
                        : '${formatMajorMoney(selectedAmount / math.pow(10, _integer(selected?['currency_minor_units'], fallback: currencyPrecision(selectedCurrency))), selectedCurrency)} / month',
                    style: const TextStyle(fontWeight: FontWeight.w800),
                  ),
                  if (discount > 0)
                    TextSpan(text: '  · ${_percent(discount)}% off'),
                ],
              ),
            ),
            const SizedBox(height: 14),
            Text(
              'Select a currency and gateway for secure payment',
              style: theme.textTheme.titleSmall,
            ),
            if (_options.isEmpty)
              const Padding(
                padding: EdgeInsets.symmetric(vertical: 10),
                child: Text(
                  'No secure payment options are available right now.',
                ),
              ),

            if (_options.isNotEmpty) ...[
              const SizedBox(height: 10),

              ConstrainedBox(
                constraints: const BoxConstraints(maxHeight: 270),
                child: ListView.separated(
                  shrinkWrap: true,
                  itemCount: _options.length,
                  separatorBuilder: (_, __) => const SizedBox(height: 6),
                  itemBuilder: (context, index) {
                    final option = _options[index];

                    final key = option['option_key']?.toString();
                    final code =
                        option['currency']?.toString().toUpperCase() ?? currency;

                    final currencyInfo = _currencyInfo(code);

                    final currencyName = currencyInfo?.name ?? code;
                    final flag = currencyInfo?.flag ?? '🌐';

                    final gateway = option['gateway']?.toString() ?? '';

                    final amount = formatMajorMoney(
                      _integer(option['amount_minor']) /
                          math.pow(
                            10,
                            _integer(
                              option['currency_minor_units'],
                              fallback: currencyPrecision(code),
                            ),
                          ),
                      code,
                    );

                    final isSelected = _selectedKey == key;

                    return Material(
                      color: isSelected
                          ? theme.colorScheme.primaryContainer.withValues(alpha: .45)
                          : theme.colorScheme.surface,
                      borderRadius: BorderRadius.circular(14),
                      child: InkWell(
                        borderRadius: BorderRadius.circular(14),
                        onTap: _busy
                            ? null
                            : () {
                                setState(() {
                                  _selectedKey = key;
                                  _message = null;
                                });
                              },
                        child: Padding(
                          padding: const EdgeInsets.symmetric(
                            horizontal: 12,
                            vertical: 12,
                          ),
                          child: Row(
                            children: [
                              // Country flag
                              Container(
                                width: 46,
                                height: 46,
                                alignment: Alignment.center,
                                decoration: BoxDecoration(
                                  color: theme.colorScheme.surfaceContainerHighest
                                      .withValues(alpha: .6),
                                  borderRadius: BorderRadius.circular(13),
                                ),
                                child: Text(
                                  flag,
                                  style: const TextStyle(fontSize: 26),
                                ),
                              ),

                              const SizedBox(width: 12),

                              // Currency and payment information
                              Expanded(
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(
                                      '$currencyName · $code',
                                      style: theme.textTheme.bodyLarge?.copyWith(
                                        fontWeight: FontWeight.w700,
                                      ),
                                    ),

                                    const SizedBox(height: 4),

                                    Text(
                                      '${_humanize(gateway)} · $amount',
                                      style: theme.textTheme.bodyMedium?.copyWith(
                                        color: theme.colorScheme.onSurfaceVariant,
                                        fontWeight: FontWeight.w500,
                                      ),
                                    ),
                                  ],
                                ),
                              ),

                              const SizedBox(width: 8),

                              // Selection indicator
                              Icon(
                                isSelected
                                    ? Icons.check_circle_rounded
                                    : Icons.circle_outlined,
                                color: isSelected
                                    ? theme.colorScheme.primary
                                    : theme.colorScheme.outline,
                                size: 24,
                              ),
                            ],
                          ),
                        ),
                      ),
                    );
                  },
                ),
              ),
            ],

            SizedBox(
              width: double.infinity,
              child: FilledButton.icon(
                onPressed: _busy || selected == null ? null : _securePayment,
                icon: const Icon(Icons.lock_outline_rounded),
                label: const Text('Secure payment'),
              ),
            ),
            const SizedBox(height: 8),
            SizedBox(
              width: double.infinity,
              child: OutlinedButton.icon(
                onPressed: _busy ? null : _payWithFunds,
                icon: const Icon(Icons.account_balance_wallet_outlined),
                label: const Text('Funds wallet · Auto'),
              ),
            ),
            Padding(
              padding: const EdgeInsets.only(top: 4),
              child: Text(
                'Ensure you have sufficient funds in your wallet.',
                style: theme.textTheme.bodySmall?.copyWith(
                  color: theme.colorScheme.onSurfaceVariant,
                ),
              ),
            ),
            if (_pendingIntent != null)
              Align(
                alignment: Alignment.center,
                child: TextButton(
                  onPressed: _busy ? null : _checkPayment,
                  child: const Text('Check payment status'),
                ),
              ),
            if (_message != null) ...[
              Padding(
                padding: const EdgeInsets.only(top: 8),
                child: Text(
                  _message!,
                  style: TextStyle(color: theme.colorScheme.error),
                ),
              ),
              if (_message!.toLowerCase().contains('fund') ||
                  _message!.toLowerCase().contains('insufficient'))
                Align(
                  alignment: Alignment.centerRight,
                  child: TextButton.icon(
                    onPressed: _busy
                        ? null
                        : () => Navigator.pop(context, 'topup'),
                    icon: const Icon(Icons.add_rounded),
                    label: const Text('Fund wallet'),
                  ),
                ),
            ],
            if (_busy)
              const Padding(
                padding: EdgeInsets.all(12),
                child: Center(child: CircularProgressIndicator()),
              ),
          ],
        ),
      ),
    );
  }
}

Map<String, dynamic> _map(Object? value) => value is Map
    ? value.map<String, dynamic>(
        (key, value) => MapEntry(key.toString(), value),
      )
    : <String, dynamic>{};
List<Map<String, dynamic>> _rows(Object? value) => value is List
    ? value
          .whereType<Map>()
          .map(
            (item) => item.map<String, dynamic>(
              (key, value) => MapEntry(key.toString(), value),
            ),
          )
          .toList()
    : <Map<String, dynamic>>[];
int _integer(Object? value, {int fallback = 0}) =>
    value is num
        ? value.toInt()
        : int.tryParse(value?.toString() ?? '') ?? fallback;
String _percent(double value) =>
    value.toStringAsFixed(value.truncateToDouble() == value ? 0 : 2);
String _humanize(String value) => value
    .split('_')
    .map(
      (part) =>
          part.isEmpty ? '' : '${part[0].toUpperCase()}${part.substring(1)}',
    )
    .join(' ');
