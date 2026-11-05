import 'dart:async';

import 'package:app_links/app_links.dart';
import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../services/auth_api.dart';
import '../widgets/auth_components.dart';

class TopUpScreen extends StatefulWidget {
  const TopUpScreen({super.key, required this.api, required this.token});
  final AuthApi api;
  final String token;
  @override
  State<TopUpScreen> createState() => _TopUpScreenState();
}

class _TopUpScreenState extends State<TopUpScreen> with WidgetsBindingObserver {
  final _amount = TextEditingController();
  final _reference = TextEditingController();
  bool _loading = true;
  bool _sending = false;
  String? _error;
  PlatformFile? _proof;
  List<Map<String, dynamic>> _methods = const [];
  List<Map<String, dynamic>> _topups = const [];
  List<String> _gateways = const [];
  Map<String, List<String>> _gatewayCurrencies = const {};
  String _gateway = 'manual';
  String _onlineCurrency = 'USD';
  Map<String, dynamic>? _pendingCheckout;
  final AppLinks _appLinks = AppLinks();
  StreamSubscription<Uri>? _linkSubscription;
  Map<String, dynamic>? _method;
  bool _manualEnabled = false;
  bool _awaitingCheckoutReturn = false;

  String get _currency =>
      _method?['currency']?.toString().toUpperCase() ?? 'USD';
  int get _digits => _precision(_currency);

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _load();
    _linkSubscription = _appLinks.uriLinkStream.listen(_handlePaymentReturn);
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed && _awaitingCheckoutReturn) {
      _awaitingCheckoutReturn = false;
      final intentId = _pendingCheckout?['id'];
      if (intentId != null) _checkCheckoutStatus(intentId);
    }
  }

  @override
  void dispose() {
    _amount.dispose();
    _reference.dispose();
    WidgetsBinding.instance.removeObserver(this);
    _linkSubscription?.cancel();
    super.dispose();
  }

  void _handlePaymentReturn(Uri uri) {
    if (uri.scheme != 'trustlens' || uri.host != 'payment-return') return;
    final id = int.tryParse(uri.queryParameters['intent_id'] ?? '');
    if (id == null) return;
    setState(() => _pendingCheckout = {'id': id});
    _checkCheckoutStatus(id);
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final data = await Future.wait([
        widget.api.spotlights(),
        widget.api.get('payment-gateways', widget.token),
        widget.api.get('me/topups', widget.token),
        widget.api.refreshUser(widget.token),
      ]);
      final spotlight = data[0]['data'];
      final options = spotlight is Map<String, dynamic>
          ? spotlight['pay_option']
          : null;
      final currencies = options is List
          ? options
                .whereType<Map<String, dynamic>>()
                .where(
                  (option) =>
                      RegExp(r'^[A-Za-z]{3}$')
                          .hasMatch(option['currency']?.toString() ?? ''),
                )
                .toList()
          : <Map<String, dynamic>>[];
      final topupData = data[2]['data'];
      final topups = topupData is Map<String, dynamic>
          ? topupData['data']
          : topupData;
      final gateways = data[1]['data'];
      final currentUser = data[3]['data'];
      final preferred = currentUser is Map<String, dynamic>
          ? currentUser['preferred_currency']?.toString().toUpperCase()
          : null;
      if (!mounted) return;
      final gatewayCurrencies = <String, List<String>>{};
      if (gateways is List) {
        for (final value in gateways) {
          if (value is Map<String, dynamic>) {
            final name = value['gateway']?.toString();
            final supported = value['supported_currencies'];
            if (name != null) {
              gatewayCurrencies[name] = supported is List
                  ? supported
                        .map((code) => code.toString().toUpperCase())
                        .toList()
                  : <String>[];
            }
          } else if (value is String) {
            // Keep compatibility with an older API while deployed backends update.
            gatewayCurrencies[value] = value == 'manual'
                ? <String>[]
                : <String>['NGN'];
          }
        }
      }
      final supportedGateways = gatewayCurrencies.keys.toList();
      final selectedGateway = supportedGateways.contains(_gateway)
          ? _gateway
          : supportedGateways.firstOrNull ?? 'manual';
      final allowedOnlineCurrencies =
          gatewayCurrencies[selectedGateway] ?? const <String>[];
      setState(() {
        _methods = currencies;
        _method = currencies.firstOrNull;
        _topups = topups is List
            ? topups.whereType<Map<String, dynamic>>().toList()
            : const [];
        _gateways = supportedGateways;
        _gatewayCurrencies = gatewayCurrencies;
        _gateway = selectedGateway;
        _onlineCurrency = allowedOnlineCurrencies.contains(preferred)
            ? preferred!
            : allowedOnlineCurrencies.firstOrNull ?? 'USD';
        _manualEnabled = supportedGateways.contains('manual');
        _loading = false;
      });
    } on AuthException catch (error) {
      if (mounted) {
        setState(() {
          _error = error.message;
          _loading = false;
        });
      }
    }
  }

  Future<void> _pickProof() async {
    final result = await FilePicker.platform.pickFiles(
      type: FileType.custom,
      allowedExtensions: const ['jpg', 'jpeg', 'png', 'webp', 'pdf'],
      allowMultiple: false,
      withData: true,
    );
    if (result != null && result.files.isNotEmpty && mounted) {
      setState(() => _proof = result.files.first);
    }
  }

  Future<void> _submit() async {
    if (_gateway != 'manual') {
      await _startCheckout();
      return;
    }
    final method = _method;
    final proof = _proof;
    if (!_manualEnabled) {
      if (mounted) {
        showAuthMessage(context, 'Manual bank deposits are not active.');
      }
      return;
    }
    if (method == null) {
      showAuthMessage(context, 'There is no deposit method available.');
      return;
    }
    if (proof?.bytes == null) {
      showAuthMessage(context, 'Choose an image or PDF payment receipt.');
      return;
    }
    final major = double.tryParse(_amount.text.trim().replaceAll(',', ''));
    if (major == null || major <= 0) {
      showAuthMessage(context, 'Enter a valid deposit amount.');
      return;
    }
    final minor = (major * (_digits == 0 ? 1 : 100)).round();
    setState(() => _sending = true);
    try {
      await widget.api.postMultipart(
        path: 'topups/manual-deposits',
        token: widget.token,
        fields: {
          'amount_minor': minor.toString(),
          'currency': _currency,
          'idempotency_key':
              'topup-mobile-${DateTime.now().microsecondsSinceEpoch}',
          if (_reference.text.trim().isNotEmpty)
            'bank_reference': _reference.text.trim(),
        },
        fileField: 'proof',
        fileBytes: proof!.bytes!,
        fileName: proof.name,
      );
      if (!mounted) return;
      _amount.clear();
      _reference.clear();
      setState(() => _proof = null);
      showAuthMessage(context, 'Deposit proof submitted for admin approval.');
      await _load();
    } on AuthException catch (error) {
      if (mounted) showAuthMessage(context, error.message);
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  Future<void> _startCheckout() async {
    final currency = _onlineCurrency;
    final allowedCurrencies = _gatewayCurrencies[_gateway] ?? const <String>[];
    if (!allowedCurrencies.contains(currency)) {
      showAuthMessage(
        context,
        'Choose a currency accepted by ${_humanize(_gateway)}.',
      );
      return;
    }
    final digits = _precision(currency);
    final amount = double.tryParse(_amount.text.trim().replaceAll(',', ''));
    if (amount == null || amount <= 0) {
      showAuthMessage(context, 'Enter a valid top-up amount.');
      return;
    }
    final amountMinor = (amount * _pow10(digits)).round();
    setState(() => _sending = true);
    try {
      final response = await widget.api.post('topups/checkout', widget.token, {
        'amount_minor': amountMinor,
        'currency': currency,
        'gateway': _gateway,
        'idempotency_key':
            'checkout-mobile-${DateTime.now().microsecondsSinceEpoch}',
      });
      final data = response['data'];
      final url = data is Map<String, dynamic>
          ? data['checkout_url']?.toString()
          : null;
      final intentId = data is Map<String, dynamic> ? data['intent_id'] : null;
      if (url == null || intentId == null) {
        throw const AuthException(
          'The payment provider did not return a checkout link.',
        );
      }
      final checkoutUri = Uri.tryParse(url);
      if (checkoutUri == null || checkoutUri.scheme != 'https') {
        throw const AuthException(
          'The payment provider returned an invalid secure checkout link.',
        );
      }
      setState(
        () => _pendingCheckout = {
          'id': intentId,
          'currency': currency,
          'amount_minor': amountMinor,
        },
      );
      _awaitingCheckoutReturn = true;
      final opened = await launchUrl(
        checkoutUri,
        mode: LaunchMode.inAppBrowserView,
      );
      if (!opened && mounted) {
        showAuthMessage(
          context,
          'Could not open the secure checkout. Try again.',
        );
      }
      if (!opened) {
        _awaitingCheckoutReturn = false;
      }
      await _load();
    } on AuthException catch (error) {
      if (mounted) showAuthMessage(context, error.message);
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  Future<void> _checkCheckoutStatus([Object? suppliedIntentId]) async {
    final intentId = suppliedIntentId ?? _pendingCheckout?['id'];
    if (intentId == null) return;
    setState(() => _sending = true);
    try {
      final response = await widget.api.post(
        'topups/$intentId/check',
        widget.token,
        const {},
      );
      final data = response['data'];
      final status = data is Map<String, dynamic>
          ? data['status']?.toString()
          : null;
      if (status == 'paid') {
        if (mounted) {
          setState(() => _pendingCheckout = null);
          showAuthMessage(
            context,
            'Payment received. Your top-up wallet has been credited.',
          );
        }
        await _load();
      } else if (mounted) {
        showAuthMessage(
          context,
          status == 'failed'
              ? 'The payment did not complete. You can start another checkout.'
              : status == 'expired'
              ? 'This checkout request has expired. If you paid before cancelling, check again shortly or contact support.'
              : 'Payment is still pending provider confirmation. Try checking again shortly.',
        );
      }
    } on AuthException catch (error) {
      if (mounted) showAuthMessage(context, error.message);
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  Future<void> _cancelTopup(Object? intentId) async {
    if (intentId == null) return;
    final confirm = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Cancel this checkout?'),
        content: const Text(
          'The top-up request will be marked expired. If you have already paid, use Check payment to confirm it.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: const Text('Keep it'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(dialogContext, true),
            child: const Text('Cancel checkout'),
          ),
        ],
      ),
    );
    if (confirm != true) return;
    try {
      await widget.api.post('topups/$intentId/cancel', widget.token, const {});
      if (mounted) showAuthMessage(context, 'Checkout request expired.');
      await _load();
    } on AuthException catch (error) {
      if (mounted) showAuthMessage(context, error.message);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    backgroundColor: Theme.of(context).scaffoldBackgroundColor,
    appBar: AppBar(
      title: const Text(
        'Top-up funds',
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
        : RefreshIndicator(
            onRefresh: _load,
            child: ListView(
              padding: const EdgeInsets.all(17),
              children: [
                Container(
                  padding: const EdgeInsets.all(16),
                  decoration: BoxDecoration(
                    gradient: const LinearGradient(
                      colors: [Color(0xFF145A3D), Color(0xFF28704F)],
                    ),
                    borderRadius: BorderRadius.circular(17),
                  ),
                  child: const Row(
                    children: [
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              'Add funds to your account',
                              style: TextStyle(
                                color: Colors.white,
                                fontSize: 17,
                                fontWeight: FontWeight.w800,
                              ),
                            ),
                            SizedBox(height: 6),
                            Text(
                              'Submit your transfer details and proof. Funds are added after admin review.',
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
                        Icons.savings_outlined,
                        color: Color(0xFFF2D48C),
                        size: 30,
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 15),
                _panel(
                  children: [
                    const Text(
                      'Choose how to add funds',
                      style: TextStyle(
                        color: ink,
                        fontWeight: FontWeight.w800,
                        fontSize: 16,
                      ),
                    ),
                    if (_gateways.isEmpty) ...[
                      const SizedBox(height: 10),
                      const Text(
                        'No payment method is currently enabled.',
                        style: TextStyle(color: mutedInk, fontSize: 12),
                      ),
                    ] else ...[
                      const SizedBox(height: 12),
                      DropdownButtonFormField<String>(
                        initialValue: _gateway,
                        decoration: const InputDecoration(
                          labelText: 'Payment method',
                        ),
                        items: _gateways
                            .map(
                              (gateway) => DropdownMenuItem(
                                value: gateway,
                                child: Text(_humanize(gateway)),
                              ),
                            )
                            .toList(),
                        onChanged: (value) => setState(() {
                          _gateway = value ?? _gateway;
                          final currencies =
                              _gatewayCurrencies[_gateway] ?? const <String>[];
                          if (!currencies.contains(_onlineCurrency)) {
                            _onlineCurrency = currencies.firstOrNull ?? 'USD';
                          }
                        }),
                      ),
                      if (_gateway == 'manual') ...[
                        if (!_manualEnabled || _methods.isEmpty) ...[
                          const SizedBox(height: 10),
                          const Text(
                            'Manual bank payment instructions are not configured yet.',
                            style: TextStyle(color: mutedInk, fontSize: 12),
                          ),
                        ] else ...[
                          const SizedBox(height: 12),
                          DropdownButtonFormField<int>(
                            initialValue: (_method?['id'] as num?)?.toInt(),
                            decoration: const InputDecoration(
                              labelText: 'Payment instructions',
                            ),
                            items: _methods
                                .map(
                                  (option) => DropdownMenuItem(
                                    value: (option['id'] as num).toInt(),
                                    child: Text(
                                      '${option['name']} (${option['currency']})',
                                    ),
                                  ),
                                )
                                .toList(),
                            onChanged: (id) => setState(
                              () => _method = _methods
                                  .where(
                                    (option) =>
                                        (option['id'] as num).toInt() == id,
                                  )
                                  .firstOrNull,
                            ),
                          ),
                          if (_method?['description'] != null)
                            Padding(
                              padding: const EdgeInsets.only(top: 8),
                              child: Text(
                                _method!['description'].toString(),
                                style: const TextStyle(
                                  color: mutedInk,
                                  fontSize: 11,
                                  height: 1.4,
                                ),
                              ),
                            ),
                          if (_method?['addresses'] is List)
                            ...(_method!['addresses'] as List).map(
                              (address) => Container(
                                width: double.infinity,
                                margin: const EdgeInsets.only(top: 7),
                                padding: const EdgeInsets.all(10),
                                decoration: BoxDecoration(
                                  color: softGreen,
                                  borderRadius: BorderRadius.circular(10),
                                ),
                                child: SelectableText(
                                  address.toString(),
                                  style: const TextStyle(
                                    color: Color(0xFF355944),
                                    fontSize: 12,
                                    fontWeight: FontWeight.w700,
                                  ),
                                ),
                              ),
                            ),
                          const SizedBox(height: 13),
                          TextField(
                            controller: _amount,
                            keyboardType: const TextInputType.numberWithOptions(
                              decimal: true,
                            ),
                            decoration: InputDecoration(
                              labelText: 'Amount ($_currency)',
                              hintText: 'Enter amount',
                            ),
                          ),
                          const SizedBox(height: 11),
                          TextField(
                            controller: _reference,
                            decoration: const InputDecoration(
                              labelText: 'Bank reference (optional)',
                            ),
                          ),
                          const SizedBox(height: 12),
                          OutlinedButton.icon(
                            onPressed: _pickProof,
                            icon: Icon(
                              _proof == null
                                  ? Icons.upload_file_rounded
                                  : Icons.check_circle_outline_rounded,
                            ),
                            label: Text(
                              _proof?.name ??
                                  'Choose transfer proof (JPG, PNG or PDF)',
                            ),
                            style: OutlinedButton.styleFrom(
                              minimumSize: const Size.fromHeight(48),
                              foregroundColor: brandGreen,
                              side: const BorderSide(color: Color(0xFFDCE5DC)),
                              shape: RoundedRectangleBorder(
                                borderRadius: BorderRadius.circular(12),
                              ),
                            ),
                          ),
                          const SizedBox(height: 13),
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
                                : const Text('Submit deposit proof'),
                          ),
                        ],
                      ] else ...[
                        const SizedBox(height: 12),
                        DropdownButtonFormField<String>(
                          initialValue: _onlineCurrency,
                          decoration: const InputDecoration(
                            labelText: 'Payment currency',
                          ),
                          items:
                              (_gatewayCurrencies[_gateway] ?? const <String>[])
                                  .map(
                                    (currency) => DropdownMenuItem(
                                      value: currency,
                                      child: Text(currency),
                                    ),
                                  )
                                  .toList(),
                          onChanged: (value) => setState(
                            () => _onlineCurrency = value ?? _onlineCurrency,
                          ),
                        ),
                        const SizedBox(height: 12),
                        TextField(
                          controller: _amount,
                          keyboardType: const TextInputType.numberWithOptions(
                            decimal: true,
                          ),
                          decoration: InputDecoration(
                            labelText: 'Amount ($_onlineCurrency)',
                            hintText: 'Enter amount',
                          ),
                        ),
                        const SizedBox(height: 13),
                        FilledButton(
                          onPressed: _sending ? null : _startCheckout,
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
                              : Text('Continue to ${_humanize(_gateway)}'),
                        ),
                      ],
                      if (_pendingCheckout != null) ...[
                        const SizedBox(height: 12),
                        OutlinedButton.icon(
                          onPressed: _sending ? null : _checkCheckoutStatus,
                          icon: const Icon(Icons.refresh_rounded),
                          label: const Text('Check payment status'),
                        ),
                        const Text(
                          'Your top-up wallet is credited only after the provider confirms the payment.',
                          style: TextStyle(
                            color: mutedInk,
                            fontSize: 10,
                            height: 1.4,
                          ),
                        ),
                      ],
                    ],
                  ],
                ),
                const SizedBox(height: 21),
                const Text(
                  'Recent top-ups',
                  style: TextStyle(
                    color: ink,
                    fontSize: 17,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 10),
                if (_topups.isEmpty)
                  _panel(
                    children: const [
                      Text(
                        'Your top-up attempts will appear here.',
                        style: TextStyle(color: mutedInk, fontSize: 12),
                      ),
                    ],
                  )
                else
                  ..._topups
                      .take(10)
                      .map(
                        (topup) => Padding(
                          padding: const EdgeInsets.only(bottom: 9),
                          child: _DepositRow(
                            deposit: topup,
                            onCheck:
                                topup['provider']?.toString() != 'manual' &&
                                    const {
                                      'pending',
                                      'processing',
                                      'expired',
                                    }.contains(topup['status']?.toString())
                                ? () => _checkCheckoutStatus(topup['id'])
                                : null,
                            onCancel:
                                topup['provider']?.toString() != 'manual' &&
                                    topup['status']?.toString() == 'pending'
                                ? () => _cancelTopup(topup['id'])
                                : null,
                          ),
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

class _DepositRow extends StatelessWidget {
  const _DepositRow({required this.deposit, this.onCheck, this.onCancel});
  final Map<String, dynamic> deposit;
  final VoidCallback? onCheck;
  final VoidCallback? onCancel;
  @override
  Widget build(BuildContext context) {
    final currency = deposit['currency']?.toString() ?? 'USD';
    final amount = (deposit['amount_minor'] as num?)?.toInt() ?? 0;
    final digits = _precision(currency);
    final provider = _humanize(deposit['provider']?.toString() ?? 'manual');
    final status = deposit['status']?.toString() ?? 'pending';
    return _panel(
      children: [
        Row(
          children: [
            const Icon(Icons.receipt_long_outlined, color: brandGreen),
            const SizedBox(width: 8),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    '$currency ${(amount / _pow10(digits)).toStringAsFixed(digits)}',
                    style: const TextStyle(
                      color: ink,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  Text(
                    provider,
                    style: const TextStyle(color: mutedInk, fontSize: 10),
                  ),
                ],
              ),
            ),
            Text(
              _humanize(status),
              style: const TextStyle(
                color: brandGreen,
                fontSize: 11,
                fontWeight: FontWeight.w800,
              ),
            ),
          ],
        ),
        if (deposit['created_at'] != null)
          Padding(
            padding: const EdgeInsets.only(top: 5),
            child: Text(
              deposit['created_at'].toString(),
              style: const TextStyle(color: mutedInk, fontSize: 10),
            ),
          ),
        if (onCheck != null || onCancel != null)
          Align(
            alignment: Alignment.centerRight,
            child: Wrap(
              spacing: 4,
              children: [
                if (onCheck != null)
                  TextButton.icon(
                    onPressed: onCheck,
                    icon: const Icon(Icons.refresh_rounded, size: 16),
                    label: const Text('Check payment'),
                  ),
                if (onCancel != null)
                  TextButton.icon(
                    onPressed: onCancel,
                    icon: const Icon(Icons.cancel_outlined, size: 16),
                    label: const Text('Cancel'),
                  ),
              ],
            ),
          ),
      ],
    );
  }
}

String _humanize(String value) => value.toLowerCase() == 'bachs'
    ? 'BACHS PAY'
    : value
          .replaceAll('_', ' ')
          .split(' ')
          .map(
            (part) => part.isEmpty
                ? part
                : '${part[0].toUpperCase()}${part.substring(1)}',
          )
          .join(' ');
int _precision(String currency) {
  if (const {
    'BIF',
    'CLP',
    'DJF',
    'GNF',
    'ISK',
    'JPY',
    'KMF',
    'KRW',
    'PYG',
    'RWF',
    'UGX',
    'VND',
    'VUV',
    'XAF',
    'XOF',
    'XPF',
  }.contains(currency.toUpperCase())) {
    return 0;
  }
  if (const {
    'BHD',
    'IQD',
    'JOD',
    'KWD',
    'LYD',
    'OMR',
    'TND',
  }.contains(currency.toUpperCase())) {
    return 3;
  }
  return 2;
}

double _pow10(int digits) => digits == 0 ? 1 : (digits == 3 ? 1000 : 100);
