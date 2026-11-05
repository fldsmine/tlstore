import 'package:flutter/material.dart';

import '../services/auth_api.dart';

class AdminPaymentGatewaysScreen extends StatefulWidget {
  const AdminPaymentGatewaysScreen({super.key, required this.api});
  final AuthApi api;

  @override
  State<AdminPaymentGatewaysScreen> createState() =>
      _AdminPaymentGatewaysScreenState();
}

class _AdminPaymentGatewaysScreenState
    extends State<AdminPaymentGatewaysScreen> {
  static const _gatewayNames = {
    'paystack': 'Paystack',
    'korapay': 'Korapay',
    'flutterwave': 'Flutterwave',
    'bachs': 'BACHS PAY',
    'manual': 'Manual bank deposit',
  };
  static const _defaults = <String, List<String>>{
    'paystack': ['NGN', 'GHS', 'ZAR', 'USD'],
    'korapay': ['NGN', 'GHS', 'KES', 'UGX', 'USD'],
    'flutterwave': ['NGN', 'USD', 'GHS', 'KES', 'UGX', 'ZAR', 'GBP', 'EUR'],
    'bachs': ['USD', 'NGN', 'GHS', 'KES', 'ZAR'],
  };

  final _email = TextEditingController();
  final _password = TextEditingController();
  final _currencyInputs = <String, TextEditingController>{
    for (final gateway in ['paystack', 'korapay', 'flutterwave', 'bachs'])
      gateway: TextEditingController(text: _defaults[gateway]!.join(', ')),
  };
  final _labelInputs = <String, TextEditingController>{
    for (final gateway in ['paystack', 'korapay', 'flutterwave', 'bachs'])
      gateway: TextEditingController(
        text: _defaults[gateway]!
            .map((currency) => '$currency=$currency')
            .join(', '),
      ),
  };
  final _enabled = <String, bool>{
    'paystack': true,
    'korapay': true,
    'flutterwave': true,
    'bachs': false,
    'manual': true,
  };
  String? _token;
  bool _loading = false;
  bool _saving = false;
  String? _error;

  @override
  void initState() {
    super.initState();

    _email.text = "admin@tlstore.test";
    _password.text = "meandmyson";
  }

  @override
  void dispose() {
    _email.dispose();
    _password.dispose();
    for (final controller in _currencyInputs.values) {
      controller.dispose();
    }
    for (final controller in _labelInputs.values) {
      controller.dispose();
    }
    super.dispose();
  }

  Future<void> _login() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final response = await widget.api.postPublic('admin/auth/login', {
        'email': _email.text.trim(),
        'password': _password.text,
      });
      final token = response['token']?.toString();
      if (token == null || token.isEmpty) {
        throw const AuthException(
          'Admin login did not return an access token.',
        );
      }
      _token = token;
      await _load();
    } on AuthException catch (error) {
      if (mounted) setState(() => _error = error.message);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _load() async {
    final token = _token;
    if (token == null) return;
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final response = await widget.api.get('admin/settings', token);
      final rows = response['data'];
      final setting = rows is List
          ? rows
                .whereType<Map<String, dynamic>>()
                .where((row) => row['setting_key'] == 'payment.active_gateways')
                .firstOrNull
          : null;
      final value = setting?['value'];
      final currencySetting = rows is List
          ? rows
                .whereType<Map<String, dynamic>>()
                .where(
                  (row) =>
                      row['setting_key'] == 'payment.active_payment_currency',
                )
                .firstOrNull
          : null;
      final options = currencySetting?['value'];
      if (options is Map) {
        final byGateway = <String, Map<String, String>>{};
        for (final entry in options.entries) {
          final parts = entry.key.toString().split('.');
          if (parts.length != 2 || !_gatewayNames.containsKey(parts.first))
            continue;
          byGateway.putIfAbsent(parts.first, () => {})[parts.last
              .toUpperCase()] = entry.value
              .toString();
        }
        for (final gateway in _gatewayNames.keys) {
          final configured = byGateway[gateway] ?? const <String, String>{};
          _enabled[gateway] = gateway == 'manual'
              ? (value is Map
                    ? value.containsKey('manual')
                    : value is List && value.contains('manual'))
              : configured.isNotEmpty;
          if (gateway != 'manual' && configured.isNotEmpty) {
            final currencies = configured.keys.toList()..sort();
            _currencyInputs[gateway]!.text = currencies.join(', ');
            _labelInputs[gateway]!.text = currencies
                .map((currency) => '$currency=${configured[currency]}')
                .join(', ');
          }
        }
      } else if (value is Map) {
        for (final gateway in _gatewayNames.keys) {
          final configured = value[gateway];
          _enabled[gateway] = configured is List;
          if (gateway != 'manual' && configured is List) {
            _currencyInputs[gateway]!.text = configured
                .map((code) => code.toString().toUpperCase())
                .join(', ');
            _labelInputs[gateway]!.text = configured
                .map(
                  (code) =>
                      '${code.toString().toUpperCase()}=${code.toString().toUpperCase()}',
                )
                .join(', ');
          }
        }
      } else if (value is List) {
        // Load old list-based settings while the database is being upgraded.
        for (final gateway in _gatewayNames.keys) {
          _enabled[gateway] = value.contains(gateway);
          if (gateway != 'manual' && value.contains(gateway)) {
            _currencyInputs[gateway]!.text = _defaults[gateway]!.join(', ');
            _labelInputs[gateway]!.text = _defaults[gateway]!
                .map((code) => '$code=$code')
                .join(', ');
          }
        }
      }
    } on AuthException catch (error) {
      if (mounted) setState(() => _error = error.message);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _save() async {
    final token = _token;
    if (token == null) return;
    final legacyValue = <String, List<String>>{};
    final currencyOptions = <String, String>{};
    for (final gateway in _gatewayNames.keys.where(
      (key) => _enabled[key] == true,
    )) {
      if (gateway == 'manual') {
        legacyValue[gateway] = [];
        continue;
      }
      final currencies = _currencyInputs[gateway]!.text
          .split(RegExp(r'[,\s]+'))
          .map((code) => code.trim().toUpperCase())
          .where((code) => code.isNotEmpty)
          .toSet()
          .toList();
      if (currencies.isEmpty ||
          currencies.any((code) => !RegExp(r'^[A-Z]{3}$').hasMatch(code))) {
        setState(
          () => _error =
              'Enter one or more three-letter currency codes for ${_gatewayNames[gateway]}.',
        );
        return;
      }
      legacyValue[gateway] = currencies;
      final labels = <String, String>{};
      for (final pair in _labelInputs[gateway]!.text.split(',')) {
        final parts = pair.split('=');
        if (parts.length == 2 &&
            parts.first.trim().isNotEmpty &&
            parts.last.trim().isNotEmpty) {
          labels[parts.first.trim().toUpperCase()] = parts.last.trim();
        }
      }
      for (final currency in currencies) {
        currencyOptions['$gateway.$currency'] = labels[currency] ?? currency;
      }
    }

    setState(() {
      _saving = true;
      _error = null;
    });
    try {
      await widget.api.put(
        'admin/settings/payment.active_payment_currency',
        token,
        {'value': currencyOptions, 'is_public': true},
      );
      await widget.api.put('admin/settings/payment.active_gateways', token, {
        'value': legacyValue,
        'is_public': true,
      });
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Payment gateway settings saved.')),
        );
      }
    } on AuthException catch (error) {
      if (mounted) setState(() => _error = error.message);
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Payment gateways')),
    body: _token == null ? _loginForm(context) : _settingsView(context),
  );

  Widget _loginForm(BuildContext context) => Center(
    child: SingleChildScrollView(
      padding: const EdgeInsets.all(24),
      child: ConstrainedBox(
        constraints: const BoxConstraints(maxWidth: 420),
        child: Card(
          child: Padding(
            padding: const EdgeInsets.all(22),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Icon(
                  Icons.admin_panel_settings_outlined,
                  size: 42,
                  color: Theme.of(context).colorScheme.primary,
                ),
                const SizedBox(height: 12),
                Text(
                  'Admin sign-in',
                  textAlign: TextAlign.center,
                  style: Theme.of(context).textTheme.titleLarge
                      ?.copyWith(fontWeight: FontWeight.w800),
                ),
                const SizedBox(height: 18),
                TextField(
                  controller: _email,
                  keyboardType: TextInputType.emailAddress,
                  decoration: const InputDecoration(labelText: 'Admin email'),
                ),
                const SizedBox(height: 10),
                TextField(
                  controller: _password,
                  obscureText: true,
                  decoration: const InputDecoration(labelText: 'Password'),
                ),
                if (_error != null) ...[
                  const SizedBox(height: 10),
                  Text(
                    _error!,
                    style: TextStyle(
                      color: Theme.of(context).colorScheme.error,
                    ),
                  ),
                ],
                const SizedBox(height: 16),
                FilledButton(
                  onPressed: _loading ? null : _login,
                  child: _loading
                      ? const CircularProgressIndicator()
                      : const Text('Sign in'),
                ),
              ],
            ),
          ),
        ),
      ),
    ),
  );

  Widget _settingsView(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return _loading
        ? const Center(child: CircularProgressIndicator())
        : ListView(
            padding: const EdgeInsets.all(16),
            children: [
              Text(
                'Choose active payment methods and the currencies each provider may accept.',
                style: Theme.of(context).textTheme.bodyMedium
                    ?.copyWith(color: scheme.onSurfaceVariant),
              ),
              const SizedBox(height: 12),
              for (final gateway in _gatewayNames.keys)
                Card(
                  child: Padding(
                    padding: const EdgeInsets.all(14),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        SwitchListTile.adaptive(
                          contentPadding: EdgeInsets.zero,
                          title: Text(
                            _gatewayNames[gateway]!,
                            style: const TextStyle(fontWeight: FontWeight.w700),
                          ),
                          value: _enabled[gateway] ?? false,
                          onChanged: (enabled) =>
                              setState(() => _enabled[gateway] = enabled),
                        ),
                        if (gateway != 'manual' &&
                            _enabled[gateway] == true) ...[
                          const SizedBox(height: 4),
                          TextField(
                            controller: _currencyInputs[gateway],
                            textCapitalization: TextCapitalization.characters,
                            decoration: const InputDecoration(
                              labelText: 'Supported currencies',
                              hintText: 'NGN, USD, GHS',
                              helperText: 'Enter ISO currency codes separated by commas.',
                            ),
                          ),
                          const SizedBox(height: 8),
                          TextField(
                            controller: _labelInputs[gateway],
                            decoration: const InputDecoration(
                              labelText: 'Currency option labels',
                              hintText: 'NGN=NGN-1, USD=USD',
                              helperText: 'Use CURRENCY=LABEL pairs. Labels appear next to the selectable currency in checkout.',
                            ),
                          ),
                        ],
                        if (gateway == 'manual' && _enabled[gateway] == true)
                          Padding(
                            padding: const EdgeInsets.only(left: 12),
                            child: Text(
                              'Currencies come from the configured bank deposit options.',
                              style: Theme.of(context).textTheme.bodySmall
                                  ?.copyWith(color: scheme.onSurfaceVariant),
                            ),
                          ),
                      ],
                    ),
                  ),
                ),
              if (_error != null)
                Padding(
                  padding: const EdgeInsets.symmetric(vertical: 8),
                  child: Text(_error!, style: TextStyle(color: scheme.error)),
                ),
              const SizedBox(height: 8),
              FilledButton.icon(
                onPressed: _saving ? null : _save,
                icon: const Icon(Icons.save_outlined),
                label: Text(_saving ? 'Saving…' : 'Save gateway settings'),
              ),
              TextButton(
                onPressed: _loading ? null : _load,
                child: const Text('Reload settings'),
              ),
            ],
          );
  }
}
