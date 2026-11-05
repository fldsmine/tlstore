import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../services/auth_api.dart';
import 'topup_screen.dart';

class SubscriptionsScreen extends StatefulWidget {
  const SubscriptionsScreen({
    super.key,
    required this.api,
    required this.token,
    this.user,
  });

  final AuthApi api;
  final String token;
  final Map<String, dynamic>? user;

  @override
  State<SubscriptionsScreen> createState() => _SubscriptionsScreenState();
}

class _SubscriptionsScreenState extends State<SubscriptionsScreen> {
  Map<String, dynamic> _user = const {};
  List<Map<String, dynamic>> _plans = const [];
  Map<String, dynamic>? _activeMarketplace;
  List<Map<String, dynamic>> _activeAddons = const [];
  String? _error;
  bool _loading = true;
  int? _purchasing;

  @override
  void initState() {
    super.initState();
    _user = widget.user ?? const {};
    _applySnapshot(_user);
    _load();
  }

  void _applySnapshot(Map<String, dynamic> user) {
    _user = user;
    final context = _map(user['subscription_context']);
    final catalog = _map(context['catalog']);
    _plans = _rows(catalog['marketplace_access']);
    _activeMarketplace = _mapOrNull(context['active_marketplace']);
    _activeAddons = _rows(context['active_location_addons']);
  }

  Future<void> _load() async {
    final hasCachedData = _plans.isNotEmpty || _activeMarketplace != null;
    if (!hasCachedData && mounted) setState(() => _loading = true);
    setState(() => _error = null);
    try {
      final fresh = await widget.api.refreshUser(widget.token);
      if (!mounted) return;
      setState(() {
        _applySnapshot(fresh);
        _loading = false;
      });
    } on AuthException catch (error) {
      if (!mounted) return;
      setState(() {
        _error = _plans.isNotEmpty || _activeMarketplace != null
            ? null
            : error.message;
        _loading = false;
      });
    }
  }

  bool _isActivePlan(Map<String, dynamic> plan) =>
      _asInt(_activeMarketplace?['subscription_plan_id']) == _asInt(plan['id']);

  Future<void> _openPurchase(Map<String, dynamic> plan) async {
    if (_isActivePlan(plan)) {
      _showActiveDetails(_activeMarketplace!);
      return;
    }
    setState(() => _purchasing = _asInt(plan['id']));
    try {
      final result = await showPlanPurchaseSheet(
        context: context,
        api: widget.api,
        token: widget.token,
        user: _user,
        plan: plan,
      );
      if (!mounted) return;
      if (result == 'topup') {
        await Navigator.of(context).push(
          MaterialPageRoute<void>(
            builder: (_) => TopUpScreen(api: widget.api, token: widget.token),
          ),
        );
        await _load();
      }
      if (result == 'paid') {
        await _load();
      }
    } finally {
      if (mounted) setState(() => _purchasing = null);
    }
  }

  void _showActiveDetails(Map<String, dynamic> subscription) {
    final plan = _map(subscription['plan']);
    final ends = _formatDate(subscription['ends_at']);
    final features = _planFeatures(plan);
    showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (context) => SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(22, 8, 22, 26),
          child: SingleChildScrollView(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  plan['name']?.toString() ?? 'Active package',
                  style: Theme.of(context).textTheme.headlineSmall
                      ?.copyWith(fontWeight: FontWeight.w800),
                ),
                const SizedBox(height: 8),
                Text('Active until $ends'),
                if (features.isNotEmpty) ...[
                  const SizedBox(height: 16),
                  Text(
                    'Package features',
                    style: Theme.of(context).textTheme.titleMedium
                        ?.copyWith(fontWeight: FontWeight.bold),
                  ),
                  ...features.map(
                    (feature) => ListTile(
                      dense: true,
                      contentPadding: EdgeInsets.zero,
                      leading: const Icon(Icons.check_circle_outline_rounded),
                      title: Text(feature),
                    ),
                  ),
                ],
                if (_activeAddons.isNotEmpty) ...[
                  const SizedBox(height: 12),
                  Text(
                    'Active location add-ons',
                    style: Theme.of(context).textTheme.titleMedium
                        ?.copyWith(fontWeight: FontWeight.bold),
                  ),
                  ..._activeAddons.map((addon) {
                    final addonPlan = _map(addon['plan']);
                    final coverage = _coverageSummary(addonPlan);
                    return ListTile(
                      contentPadding: EdgeInsets.zero,
                      leading: const Icon(Icons.public_rounded),
                      title: Text(
                        addonPlan['name']?.toString() ?? 'Location add-on',
                      ),
                      subtitle: Text(
                        '${coverage.isEmpty ? 'Coverage not configured' : coverage} · Ends ${_formatDate(addon['ends_at'])}',
                      ),
                    );
                  }),
                ],
              ],
            ),
          ),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Scaffold(
      appBar: AppBar(
        title: const Text('Marketplace subscription'),
        centerTitle: true,
      ),
      body: _loading && _plans.isEmpty
          ? const Center(child: CircularProgressIndicator())
          : _error != null
          ? Center(
              child: Padding(
                padding: const EdgeInsets.all(24),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(_error!, textAlign: TextAlign.center),
                    const SizedBox(height: 10),
                    FilledButton.tonal(
                      onPressed: _load,
                      child: const Text('Retry'),
                    ),
                  ],
                ),
              ),
            )
          : RefreshIndicator(
              onRefresh: _load,
              child: ListView(
                padding: const EdgeInsets.all(16),
                children: [
                  if (_activeMarketplace case final active?)
                    Card(
                      clipBehavior: Clip.antiAlias,
                      child: InkWell(
                        onTap: () => _showActiveDetails(active),
                        child: Padding(
                          padding: const EdgeInsets.all(16),
                          child: Row(
                            children: [
                              const CircleAvatar(
                                child: Icon(Icons.verified_rounded),
                              ),
                              const SizedBox(width: 13),
                              Expanded(
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(
                                      'Your active package',
                                      style: theme.textTheme.labelLarge,
                                    ),
                                    Text(
                                      _map(
                                            active['plan'],
                                          )['name']?.toString() ??
                                          'Marketplace package',
                                      style: theme.textTheme.titleMedium
                                          ?.copyWith(
                                            fontWeight: FontWeight.w800,
                                          ),
                                    ),
                                    Text(
                                      'Tap to view plan and location add-ons · Ends ${_formatDate(active['ends_at'])}',
                                    ),
                                  ],
                                ),
                              ),
                              const Icon(Icons.chevron_right_rounded),
                            ],
                          ),
                        ),
                      ),
                    )
                  else
                    Container(
                      padding: const EdgeInsets.all(16),
                      decoration: BoxDecoration(
                        color: theme.colorScheme.secondaryContainer,
                        borderRadius: BorderRadius.circular(16),
                      ),
                      child: const Text(
                        'Choose a marketplace package to review apps and share ads. You can browse all listings before subscribing.',
                      ),
                    ),
                  if (_activeAddons.isNotEmpty) ...[
                    const SizedBox(height: 12),
                    Card(
                      child: ExpansionTile(
                        leading: const Icon(Icons.public_rounded),
                        title: Text(
                          'Active location add-ons (${_activeAddons.length})',
                        ),
                        subtitle: const Text(
                          'These stay active when you switch marketplace packages.',
                        ),
                        children: _activeAddons.map((addon) {
                          final plan = _map(addon['plan']);
                          final coverage = _coverageSummary(plan);
                          return ListTile(
                            title: Text(
                              plan['name']?.toString() ?? 'Location add-on',
                            ),
                            subtitle: Text(
                              '${coverage.isEmpty ? 'Coverage not configured' : coverage} · Ends ${_formatDate(addon['ends_at'])}',
                            ),
                          );
                        }).toList(),
                      ),
                    ),
                  ],
                  const SizedBox(height: 18),
                  Text(
                    'Available packages',
                    style: theme.textTheme.titleLarge?.copyWith(
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  const SizedBox(height: 10),
                  if (_plans.isEmpty)
                    const Padding(
                      padding: EdgeInsets.symmetric(vertical: 30),
                      child: Center(
                        child: Text(
                          'No marketplace packages are available right now.',
                        ),
                      ),
                    )
                  else
                    ..._plans.map(
                      (plan) => Padding(
                        padding: const EdgeInsets.only(bottom: 12),
                        child: _PlanCard(
                          plan: plan,
                          active: _isActivePlan(plan),
                          purchasing: _purchasing == _asInt(plan['id']),
                          onTap: () => _openPurchase(plan),
                        ),
                      ),
                    ),
                ],
              ),
            ),
    );
  }
}

class _PlanCard extends StatelessWidget {
  const _PlanCard({
    required this.plan,
    required this.active,
    required this.purchasing,
    required this.onTap,
  });
  final Map<String, dynamic> plan;
  final bool active;
  final bool purchasing;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final colors = theme.colorScheme;
    final currency = plan['currency']?.toString() ?? 'USD';
    final digits = _asInt(
      plan['currency_minor_units'],
      fallback: 2,
    ).clamp(0, 4);
    final priceMinor = _asInt(plan['price_minor']);
    final discount = _asDouble(plan['discount']);
    final payable = (priceMinor * (100 - discount.clamp(0, 100)) / 100).round();
    final bonus = _asDouble(plan['bonus']);
    final special = _asInt(plan['is_special']) == 1;
    final features = _planFeatures(plan);
    return Card(
      clipBehavior: Clip.antiAlias,
      elevation: 0,
      color: colors.surface,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(18),
        side: BorderSide(
          color: active ? colors.primary : colors.outlineVariant,
          width: active ? 1.7 : 1,
        ),
      ),
      child: Padding(
        padding: const EdgeInsets.all(17),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(
                    plan['name']?.toString() ?? 'Plan',
                    style: theme.textTheme.titleLarge?.copyWith(
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
                if (special)
                  Chip(
                    label: const Text('FEATURED'),
                    visualDensity: VisualDensity.compact,
                    backgroundColor: colors.tertiaryContainer,
                  ),
                if (active) Icon(Icons.verified_rounded, color: colors.primary),
              ],
            ),
            const SizedBox(height: 5),
            Text(
              '${_money(payable, currency, digits)} / ${plan['duration_count'] ?? 1} ${plan['billing_interval'] ?? 'monthly'}',
              style: theme.textTheme.titleMedium?.copyWith(
                color: colors.primary,
                fontWeight: FontWeight.w800,
              ),
            ),
            if (discount > 0)
              Text(
                '${discount.toStringAsFixed(0)}% discount',
                style: theme.textTheme.bodySmall,
              ),
            if (bonus > 0 && plan['bonus_active'] != false)
              Text(
                'Includes ${_money(_asInt((bonus * math.pow(10, digits)).round()), currency, digits)} welcome bonus',
                style: theme.textTheme.bodySmall,
              ),
            if (features.isNotEmpty) ...[
              const SizedBox(height: 10),
              ...features
                  .take(6)
                  .map(
                    (feature) => Padding(
                      padding: const EdgeInsets.only(bottom: 5),
                      child: Row(
                        children: [
                          Icon(
                            Icons.check_rounded,
                            color: colors.primary,
                            size: 17,
                          ),
                          const SizedBox(width: 7),
                          Expanded(
                            child: Text(
                              feature,
                              style: theme.textTheme.bodySmall,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
            ] else ...[
              if (plan['max_review_tasks'] != null)
                _feature(
                  context,
                  'Up to ${plan['max_review_tasks']} app reviews',
                ),
              if (plan['max_ad_shares'] != null)
                _feature(context, 'Up to ${plan['max_ad_shares']} ad shares'),
              if (plan['global_listing_access'] == true)
                _feature(context, 'Access to global listings'),
            ],
            const SizedBox(height: 12),
            SizedBox(
              width: double.infinity,
              child: FilledButton(
                onPressed: purchasing ? null : onTap,
                child: purchasing
                    ? const SizedBox(
                        width: 18,
                        height: 18,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : Text(
                        active
                            ? 'Already active · View details'
                            : 'Join Package',
                      ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _feature(BuildContext context, String text) => Padding(
    padding: const EdgeInsets.only(top: 6),
    child: Row(
      children: [
        Icon(
          Icons.check_rounded,
          size: 16,
          color: Theme.of(context).colorScheme.primary,
        ),
        const SizedBox(width: 7),
        Expanded(
          child: Text(text, style: Theme.of(context).textTheme.bodySmall),
        ),
      ],
    ),
  );
}

Future<String?> showPlanPurchaseSheet({
  required BuildContext context,
  required AuthApi api,
  required String token,
  required Map<String, dynamic> user,
  required Map<String, dynamic> plan,
}) => showModalBottomSheet<String>(
  context: context,
  isScrollControlled: true,
  showDragHandle: true,
  builder: (_) =>
      _PlanPurchaseSheet(api: api, token: token, user: user, plan: plan),
);

class _PlanPurchaseSheet extends StatefulWidget {
  const _PlanPurchaseSheet({
    required this.api,
    required this.token,
    required this.user,
    required this.plan,
  });
  final AuthApi api;
  final String token;
  final Map<String, dynamic> user;
  final Map<String, dynamic> plan;

  @override
  State<_PlanPurchaseSheet> createState() => _PlanPurchaseSheetState();
}

class _PlanPurchaseSheetState extends State<_PlanPurchaseSheet> {
  bool _busy = false;
  Object? _pendingIntent;
  String? _message;
  String? _selectedOptionKey;

  int get _priceMinor {
    final discount = _asDouble(widget.plan['discount']).clamp(0, 100);
    return (_asInt(widget.plan['price_minor']) * (100 - discount) / 100)
        .round();
  }

  int get _digits =>
      _asInt(widget.plan['currency_minor_units'], fallback: 2).clamp(0, 4);
  String get _currency => widget.plan['currency']?.toString() ?? 'USD';
  List<Map<String, dynamic>> get _checkoutOptions =>
      _rows(widget.plan['checkout_options']);
  Map<String, dynamic>? get _selectedOption {
    for (final option in _checkoutOptions) {
      if (option['option_key']?.toString() == _selectedOptionKey) return option;
    }
    return null;
  }

  int _cachedFunds() {
    final snapshot = _map(widget.user['subscription_context']);
    final wallets = _rows(snapshot['wallets']);
    for (final wallet in wallets) {
      if (wallet['type'] == 'topup_fund' &&
          wallet['currency']?.toString().toUpperCase() ==
              _currency.toUpperCase()) {
        return _asInt(wallet['available_balance_minor']);
      }
    }
    return 0;
  }

  Future<void> _payWithFunds() async {
    if (_cachedFunds() < _priceMinor) {
      setState(
        () => _message =
            'Your top-up wallet does not have enough ${_currency} funds. Use Secure payment or fund your wallet first.',
      );
      return;
    }
    setState(() {
      _busy = true;
      _message = null;
    });
    try {
      await widget.api.purchaseSubscriptionWithFunds(
        token: widget.token,
        planId: _asInt(widget.plan['id']),
        idempotencyKey:
            'package-wallet-${DateTime.now().microsecondsSinceEpoch}',
      );
      await widget.api.refreshUser(widget.token);
      if (mounted) Navigator.of(context).pop('paid');
    } on AuthException catch (error) {
      if (mounted)
        setState(() {
          _busy = false;
          _message = error.message;
        });
    }
  }

  Future<void> _securePayment() async {
    final option = _selectedOption;
    if (option == null) {
      setState(() => _message = 'Select a payment currency and gateway first.');
      return;
    }
    setState(() {
      _busy = true;
      _message = null;
    });
    try {
      final response = await widget.api.checkoutSubscription(
        token: widget.token,
        planId: _asInt(widget.plan['id']),
        gateway: option['gateway'].toString(),
        currency: option['currency'].toString(),
        idempotencyKey:
            'package-checkout-${DateTime.now().microsecondsSinceEpoch}',
      );
      final data = _map(response['data']);
      final url = Uri.tryParse(data['checkout_url']?.toString() ?? '');
      final intent = data['intent_id'];
      if (url == null || url.scheme != 'https' || intent == null)
        throw const AuthException(
          'The payment provider returned an invalid checkout link.',
        );
      _pendingIntent = intent;
      final opened = await launchUrl(url, mode: LaunchMode.inAppBrowserView);
      if (!opened)
        throw const AuthException('Could not open secure checkout. Try again.');
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
      final response = await widget.api.checkSubscriptionPayment(
        widget.token,
        intent,
      );
      final status = _map(response['data'])['status']?.toString();
      if (status == 'paid') {
        await widget.api.refreshUser(widget.token);
        if (mounted) Navigator.of(context).pop('paid');
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

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final name = widget.plan['name']?.toString() ?? 'Package';
    final selected = _selectedOption;
    final displayCurrency = selected?['currency']?.toString() ?? _currency;
    final displayPrice = selected == null
        ? _priceMinor
        : _asInt(selected['amount_minor']);
    final displayDigits = _asInt(
      selected?['currency_minor_units'] ?? widget.plan['currency_minor_units'],
      fallback: 2,
    ).clamp(0, 4);
    return SafeArea(
      child: SingleChildScrollView(
        child: Padding(
          padding: EdgeInsets.fromLTRB(
            22,
            4,
            22,
            20 + MediaQuery.viewInsetsOf(context).bottom,
          ),
          child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Join $name',
              style: theme.textTheme.headlineSmall?.copyWith(
                fontWeight: FontWeight.w800,
              ),
            ),
            const SizedBox(height: 6),
            Text(
              selected == null ? 'Package cost' : 'Secure payment cost',
              style: theme.textTheme.bodyMedium,
            ),
            Text(
              _money(displayPrice, displayCurrency, displayDigits),
              style: theme.textTheme.headlineMedium?.copyWith(
                color: theme.colorScheme.primary,
                fontWeight: FontWeight.w900,
              ),
            ),
            if (widget.plan['product_type']?.toString() ==
                    'marketplace_access' &&
                _map(
                  _map(
                    widget.user['subscription_context'],
                  )['active_marketplace'],
                ).isNotEmpty) ...[
              const SizedBox(height: 8),
              Text(
                'Joining replaces your current marketplace package. Your active location add-ons stay in effect.',
                style: theme.textTheme.bodySmall?.copyWith(
                  color: theme.colorScheme.onSurfaceVariant,
                ),
              ),
            ],
            const SizedBox(height: 16),
            Text(
              'Select a currency and payment route for secure payment',
              style: theme.textTheme.titleSmall?.copyWith(
                fontWeight: FontWeight.w700,
              ),
            ),
            const SizedBox(height: 8),
            if (_checkoutOptions.isEmpty)
              Padding(
                padding: const EdgeInsets.symmetric(vertical: 10),
                child: Text(
                  'No secure payment currencies are available for this package right now.',
                  style: theme.textTheme.bodySmall?.copyWith(
                    color: theme.colorScheme.onSurfaceVariant,
                  ),
                ),
              )
            else
              ConstrainedBox(
                constraints: const BoxConstraints(maxHeight: 230),
                child: ListView.builder(
                  shrinkWrap: true,
                  itemCount: _checkoutOptions.length,
                  itemBuilder: (context, index) {
                    final option = _checkoutOptions[index];
                    final optionKey = option['option_key']?.toString() ?? '';
                    final currency =
                        option['currency']?.toString() ?? _currency;
                    final label = option['label']?.toString() ?? currency;
                    final gateway = option['gateway']?.toString() ?? '';
                    final amount = _money(
                      _asInt(option['amount_minor']),
                      currency,
                      _asInt(
                        option['currency_minor_units'],
                        fallback: 2,
                      ).clamp(0, 4),
                    );
                    return CheckboxListTile(
                      dense: true,
                      contentPadding: EdgeInsets.zero,
                      value: _selectedOptionKey == optionKey,
                      title: Text('$label · $currency'),
                      subtitle: Text('${_humanize(gateway)} · $amount'),
                      onChanged: _busy
                          ? null
                          : (checked) => setState(() {
                              _selectedOptionKey = checked == true
                                  ? optionKey
                                  : null;
                              _message = null;
                            }),
                    );
                  },
                ),
              ),
            const SizedBox(height: 8),
            SizedBox(
              width: double.infinity,
              child: FilledButton.icon(
                onPressed: _busy || selected == null ? null : _securePayment,
                icon: const Icon(Icons.lock_rounded),
                label: const Text('Secure payment'),
              ),
            ),
            const SizedBox(height: 8),
            SizedBox(
              width: double.infinity,
              child: OutlinedButton.icon(
                onPressed: _busy ? null : _payWithFunds,
                icon: const Icon(Icons.account_balance_wallet_rounded),
                label: Text('Funds wallet · $_currency'),
              ),
            ),
            Padding(
              padding: const EdgeInsets.only(top: 4),
              child: Text(
                'Funds wallet uses your top-up balance in $_currency.',
                style: theme.textTheme.bodySmall?.copyWith(
                  color: theme.colorScheme.onSurfaceVariant,
                ),
              ),
            ),
            if (_pendingIntent != null) ...[
              const SizedBox(height: 7),
              SizedBox(
                width: double.infinity,
                child: TextButton.icon(
                  onPressed: _busy ? null : _checkPayment,
                  icon: const Icon(Icons.refresh_rounded),
                  label: const Text('Check payment status'),
                ),
              ),
            ],
            if (_message != null) ...[
              const SizedBox(height: 8),
              Text(
                _message!,
                style: theme.textTheme.bodyMedium?.copyWith(
                  color: theme.colorScheme.error,
                ),
              ),
              if (_message!.toLowerCase().contains('fund') ||
                  _message!.toLowerCase().contains('insufficient'))
                Align(
                  alignment: Alignment.centerRight,
                  child: TextButton.icon(
                    onPressed: _busy
                        ? null
                        : () => Navigator.of(context).pop('topup'),
                    icon: const Icon(Icons.add_rounded),
                    label: const Text('Fund wallet'),
                  ),
                ),
            ],
            if (_busy)
              const Padding(
                padding: EdgeInsets.only(top: 8),
                child: Center(child: CircularProgressIndicator()),
              ),
          ],
        ),
      ),
      ),
    );
  }
}

Future<bool?> showLocationCoveragePackages({
  required BuildContext context,
  required AuthApi api,
  required String token,
  required Map<String, dynamic> user,
  required String location,
}) => showModalBottomSheet<bool>(
  context: context,
  isScrollControlled: true,
  showDragHandle: true,
  builder: (_) => _LocationCoverageSheet(
    api: api,
    token: token,
    user: user,
    location: location,
  ),
);

class _LocationCoverageSheet extends StatefulWidget {
  const _LocationCoverageSheet({
    required this.api,
    required this.token,
    required this.user,
    required this.location,
  });
  final AuthApi api;
  final String token;
  final Map<String, dynamic> user;
  final String location;

  @override
  State<_LocationCoverageSheet> createState() => _LocationCoverageSheetState();
}

class _LocationCoverageSheetState extends State<_LocationCoverageSheet> {
  late Map<String, dynamic> _user = widget.user;
  List<Map<String, dynamic>> _plans = const [];
  bool _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _plans = _rows(
      _map(_map(_user['subscription_context'])['catalog'])['location_coverage'],
    );
    if (_plans.isNotEmpty) _loading = false;
    _load();
  }

  Future<void> _load() async {
    try {
      final fresh = await widget.api.refreshUser(widget.token);
      if (!mounted) return;
      final catalog = _map(_map(fresh['subscription_context'])['catalog']);
      setState(() {
        _user = fresh;
        _plans = _rows(catalog['location_coverage']);
        _loading = false;
      });
    } on AuthException catch (error) {
      if (_plans.isEmpty && mounted)
        setState(() {
          _error = error.message;
          _loading = false;
        });
    }
  }

  Future<void> _select(Map<String, dynamic> plan) async {
    final result = await showPlanPurchaseSheet(
      context: context,
      api: widget.api,
      token: widget.token,
      user: _user,
      plan: plan,
    );
    if (!mounted) return;
    if (result == 'paid') {
      _user = await widget.api.refreshUser(widget.token);
      Navigator.of(context).pop(true);
    } else if (result == 'topup') {
      await Navigator.of(context).push(
        MaterialPageRoute<void>(
          builder: (_) => TopUpScreen(api: widget.api, token: widget.token),
        ),
      );
      _user = await widget.api.refreshUser(widget.token);
      if (mounted) setState(() {});
    }
  }

  @override
  Widget build(BuildContext context) => SafeArea(
    child: Padding(
      padding: EdgeInsets.fromLTRB(
        18,
        0,
        18,
        22 + MediaQuery.viewInsetsOf(context).bottom,
      ),
      child: _loading
          ? const SizedBox(
              height: 260,
              child: Center(child: CircularProgressIndicator()),
            )
          : _error != null
          ? SizedBox(
              height: 220,
              child: Center(child: Text(_error!, textAlign: TextAlign.center)),
            )
          : SizedBox(
              height: MediaQuery.sizeOf(context).height * .72,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Add location coverage',
                    style: Theme.of(context).textTheme.headlineSmall
                        ?.copyWith(fontWeight: FontWeight.w800),
                  ),
                  const SizedBox(height: 8),
                  Text(
                    'Your current marketplace plan does not cover ${widget.location}. Buy a location add-on to review apps and share ads there.',
                  ),
                  const SizedBox(height: 14),
                  Expanded(
                    child: _plans.isEmpty
                        ? const Center(
                            child: Text(
                              'No location packages are available right now.',
                            ),
                          )
                        : ListView.separated(
                            itemCount: _plans.length,
                            separatorBuilder: (_, __) =>
                                const SizedBox(height: 9),
                            itemBuilder: (context, index) {
                              final plan = _plans[index];
                              final currency =
                                  plan['currency']?.toString() ?? 'USD';
                              final digits = _asInt(
                                plan['currency_minor_units'],
                                fallback: 2,
                              ).clamp(0, 4);
                              final discount = _asDouble(plan['discount'])
                                  .clamp(0, 100);
                              final cost =
                                  (_asInt(plan['price_minor']) *
                                          (100 - discount) /
                                          100)
                                      .round();
                              final coverage = _coverageSummary(plan);
                              return Card(
                                child: ListTile(
                                  title: Text(
                                    plan['name']?.toString() ??
                                        'Location package',
                                    style: const TextStyle(
                                      fontWeight: FontWeight.w800,
                                    ),
                                  ),
                                  subtitle: Text(
                                    '${_money(cost, currency, digits)} · ${coverage.isEmpty ? 'Coverage not configured' : coverage}',
                                  ),
                                  trailing: const Icon(
                                    Icons.chevron_right_rounded,
                                  ),
                                  onTap: () => _select(plan),
                                ),
                              );
                            },
                          ),
                  ),
                ],
              ),
            ),
    ),
  );
}

Map<String, dynamic> _map(Object? value) => value is Map
    ? value.map((key, value) => MapEntry(key.toString(), value))
    : <String, dynamic>{};

Map<String, dynamic>? _mapOrNull(Object? value) =>
    value is Map ? _map(value) : null;

List<Map<String, dynamic>> _rows(Object? value) => value is List
    ? value.whereType<Map>().map((item) => _map(item)).toList(growable: false)
    : const [];

List<String> _planFeatures(Map<String, dynamic> plan) {
  final value = plan['features'];
  if (value is List) {
    return value
        .whereType<Map>()
        .map(
          (item) =>
              item['label']?.toString() ??
              item['feature_key']?.toString() ??
              '',
        )
        .where((label) => label.isNotEmpty)
        .toList();
  }
  return const [];
}

String _coverageSummary(Map<String, dynamic> plan) {
  final value = plan['location_coverage'];
  if (value is! List) return '';
  final countries = <String>[];
  final regions = <String>[];
  for (final item in value.whereType<Map>()) {
    final type = item['target_type']?.toString() ?? 'country';
    final name =
        item['target_name']?.toString() ??
        item['target_code']?.toString() ??
        '';
    if (name.isEmpty) continue;
    if (type == 'region') {
      regions.add(name);
    } else {
      countries.add(name);
    }
  }
  return [
    if (countries.isNotEmpty) 'Countries: ${countries.join(', ')}',
    if (regions.isNotEmpty) 'Regions: ${regions.join(', ')}',
  ].join(' · ');
}

String _humanize(String key) => key
    .split('_')
    .map(
      (part) =>
          part.isEmpty ? '' : '${part[0].toUpperCase()}${part.substring(1)}',
    )
    .join(' ');

String _money(int minor, String currency, int digits) {
  final value = minor / math.pow(10, digits);
  const symbols = {'USD': r'$', 'NGN': '₦', 'GBP': '£', 'EUR': '€', 'GHS': '₵'};
  return '${symbols[currency.toUpperCase()] ?? '$currency '}${value.toStringAsFixed(digits)}';
}

String _formatDate(Object? value) {
  final date = DateTime.tryParse(value?.toString() ?? '')?.toLocal();
  if (date == null) return '—';
  return '${date.day}/${date.month}/${date.year}';
}

int _asInt(Object? value, {int fallback = 0}) {
  if (value is int) return value;
  if (value is num) return value.toInt();
  return int.tryParse(value?.toString() ?? '') ??
      double.tryParse(value?.toString() ?? '')?.toInt() ??
      fallback;
}

double _asDouble(Object? value, {double fallback = 0}) {
  if (value is num) return value.toDouble();
  return double.tryParse(value?.toString() ?? '') ?? fallback;
}
