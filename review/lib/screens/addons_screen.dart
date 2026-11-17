import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../core/currency/money.dart';
import '../core/utils/helper.dart';
import '../widgets/proxy_info_dialog.dart';
import '../widgets/proxy_prompt_dialog.dart';
import '../services/auth_api.dart';
import '../widgets/proxy_connection_dialog.dart';

class AddonsScreen extends StatefulWidget {
  const AddonsScreen({
    super.key,
    required this.api,
    required this.token,
    required this.user,
    required this.onLogout,
    required this.onDeleted,
  });

  final AuthApi api;
  final String token;
  final Map<String, dynamic> user;
  final Future<void> Function() onLogout;
  final VoidCallback onDeleted;

  @override
  State<AddonsScreen> createState() => _AddonsScreenState();
}

class _AddonsScreenState extends State<AddonsScreen> {
  Map<String, dynamic> _profile = const {};
  List<Map<String, dynamic>> _walletRecords = const [];
  List<Map<String, dynamic>> _addonRecords = const [];
  bool _refreshing = false;
  bool _hasLoadedAddons = false;
  bool _paying = false;
  String? _profileError;
  String? _addonsError;
  Object? _pendingIntentId;
  int? _pendingAddonId;
  int _requestGeneration = 0;

  @override
  void initState() {
    super.initState();
    _profile = _stringMap(widget.user);
    _refresh();
  }

  @override
  void didUpdateWidget(covariant AddonsScreen oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.token != widget.token || oldWidget.api != widget.api) {
      _profile = _stringMap(widget.user);
      _hasLoadedAddons = false;
      _refresh();
    } else if (!identical(oldWidget.user, widget.user)) {
      _profile = {..._profile, ..._stringMap(widget.user)};
    }
  }

  Future<void> _openProxyConnection(Map<String, dynamic> addonState) async {
    await Navigator.of(context).push<void>(
      MaterialPageRoute<void>(
        builder: (_) => ProxyConnectionDialog(
          addonState: addonState,
          user: Map<String, dynamic>.from(_profile),
        ),
      ),
    );
  }

  Map<String, dynamic> _stringMap(Object? value) => value is Map
      ? value.map<String, dynamic>(
          (key, value) => MapEntry(key.toString(), value),
        )
      : <String, dynamic>{};

  List<Map<String, dynamic>> _rowsFromResponse(Map<String, dynamic> response) {
    // The endpoint returns Laravel pagination: response.data.data is the row
    // list. Accept a direct list too for API versions that omit pagination.
    final envelope = response['data'];
    final rows = envelope is List
        ? envelope
        : envelope is Map
        ? envelope['data']
        : null;
    if (rows is! List) return const [];
    return rows.whereType<Map>().map(_stringMap).toList(growable: false);
  }

  Future<_ApiOutcome> _capture(Future<Map<String, dynamic>> request) async {
    try {
      return _ApiOutcome(data: await request);
    } catch (error) {
      return _ApiOutcome(error: error);
    }
  }

  Future<Map<String, dynamic>> _walletsResponse() =>
      widget.api.get('wallets', widget.token);

  Future<void> _refresh() async {
    if (!mounted) return;
    final generation = ++_requestGeneration;
    setState(() {
      _refreshing = true;
      _profileError = null;
      _addonsError = null;
    });

    await widget.api.loadExchangeRates(widget.token);
    final outcomes = await Future.wait<_ApiOutcome>([
      _capture(widget.api.get('me', widget.token)),
      _capture(widget.api.get('package-addons', widget.token)),
      _capture(_walletsResponse()),
    ]);
    if (!mounted || generation != _requestGeneration) return;

    final profileOutcome = outcomes[0];
    final addonsOutcome = outcomes[1];
    final walletsOutcome = outcomes[2];
    setState(() {
      if (profileOutcome.data != null) {
        final data = profileOutcome.data!['data'];
        if (data is Map) {
          _profile = {..._profile, ..._stringMap(data)};
          final preferred = _profile['preferred_currency']?.toString();
          if (preferred != null && preferred.isNotEmpty) {
            widget.api.setPreferredCurrency(preferred);
          }
        }
      } else {
        _profileError = _errorMessage(
          profileOutcome.error,
          'Account details could not be refreshed.',
        );
      }
      if (addonsOutcome.data != null) {
        _addonRecords = _rowsFromResponse(addonsOutcome.data!);
        _addonsError = null;
        _hasLoadedAddons = true;
      } else {
        _addonsError = _errorMessage(
          addonsOutcome.error,
          'Proxy add-ons could not be loaded.',
        );
        _hasLoadedAddons = true;
      }
      _walletRecords = walletsOutcome.data == null
          ? const []
          : _rowsFromResponse(walletsOutcome.data!);
      _refreshing = false;
    });
  }

  String _errorMessage(Object? error, String fallback) =>
      error is AuthException ? error.message : fallback;

  Map<String, dynamic> _map(Object? value) => _stringMap(value);

  List<Map<String, dynamic>> get _activeMarketplaces {
    final context = _map(_profile['subscription_context']);
    final values = context['active_marketplaces'];
    if (values is List) {
      return values.whereType<Map>().map(_stringMap).toList(growable: false);
    }
    final legacy = context['active_marketplace'];
    return legacy is Map
        ? <Map<String, dynamic>>[_stringMap(legacy)]
        : const [];
  }

  Map<String, Map<String, dynamic>> get _plansById {
    final subscription = _map(_profile['subscription_context']);
    final catalog = _map(subscription['catalog']);
    final result = <String, Map<String, dynamic>>{};
    final groups = catalog['subscription_groups'];
    if (groups is! List) return result;
    for (final groupValue in groups.whereType<Map>()) {
      final group = _map(groupValue);
      final plans = group['plans'];
      if (plans is! List) continue;
      for (final planValue in plans.whereType<Map>()) {
        final plan = _map(planValue);
        final id = plan['id']?.toString();
        if (id != null) result[id] = plan;
      }
    }
    return result;
  }

  Map<String, dynamic> _addon(Map<String, dynamic> record) =>
      _map(record['addon']);

  Map<String, dynamic> _planFor(Map<String, dynamic> record) {
    final addon = _addon(record);
    final nested = _map(addon['plan']);
    final id = (addon['subscription_plan_id'] ?? nested['id'])?.toString();
    final catalogPlan = id == null
        ? const <String, dynamic>{}
        : (_plansById[id] ?? const {});
    return {...catalogPlan, ...nested};
  }

  Map<String, dynamic> _effectiveEntitlement(Map<String, dynamic> record) {
    final active = _map(record['active_entitlement']);
    if (active.isNotEmpty) return active;
    if (_recordIsActive(record)) return record;
    return const {};
  }

  bool _recordIsActive(Map<String, dynamic> record) {
    final active = _map(record['active_entitlement']);
    final status = (active['status'] ?? record['status'])
        ?.toString()
        .toLowerCase();
    if (status != 'active' && !(status == null && active.isNotEmpty)) {
      return false;
    }
    final end = DateTime.tryParse(
      active['ends_at']?.toString() ?? record['ends_at']?.toString() ?? '',
    );
    return end == null || end.isAfter(DateTime.now());
  }

  bool _recordIsPending(Map<String, dynamic> record) {
    final latest = _map(record['latest_entitlement']);
    final status = (latest['status'] ?? record['status'])
        ?.toString()
        .toLowerCase();
    return status == 'pending' || status == 'processing';
  }

  int? _addonId(Map<String, dynamic> record) {
    final addon = _addon(record);
    final value = addon['id'] ?? record['package_addon_id'];
    if (value is num) return value.toInt();
    return int.tryParse(value?.toString() ?? '');
  }

  String _money(Object? minorValue, String currency, [Object? units]) {
    return moneyConverted(
      widget.api,
      minorValue,
      currency.trim().isEmpty ? 'USD' : currency,
      units,
      currency,
      isMinor: true,
    );
  }

  String get _preferredCurrency =>
      (_profile['preferred_currency'] ??
              widget.user['preferred_currency'] ??
              widget.api.preferredCurrency)
          .toString()
          .toUpperCase();

  String _date(Object? value) {
    final date = DateTime.tryParse(value?.toString() ?? '')?.toLocal();
    if (date == null) return '—';
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
    return '${months[date.month - 1]} ${date.day}, ${date.year}';
  }

  Map<String, dynamic> _wallet() {
    final contextWallets = _map(_profile['subscription_context'])['wallets'];
    final profileWallets = _profile['wallets'];
    final candidates = <Map<String, dynamic>>[
      ..._walletRecords,
      if (profileWallets is List)
        ...profileWallets.whereType<Map>().map(_stringMap),
      if (contextWallets is List)
        ...contextWallets.whereType<Map>().map(_stringMap),
    ];
    final currency =
        _profile['wallet_currency']?.toString().toUpperCase() ??
        _preferredCurrency;
    for (final wallet in candidates) {
      if (wallet['type']?.toString() == 'topup_fund' ||
          wallet['wallet_type']?.toString() == 'topup_fund') {
        return wallet;
      }
    }
    return <String, dynamic>{
      'currency': currency,
      'balance_minor': 0,
      'available_balance_minor': 0,
    };
  }

  Map<String, dynamic> _subscriptionPlan(Map<String, dynamic> subscription) {
    final id = subscription['subscription_plan_id']?.toString();
    return id == null ? const {} : (_plansById[id] ?? const {});
  }

  List<_CheckoutOption> _checkoutOptions(Map<String, dynamic> record) {
    final addon = _addon(record);
    final plan = _planFor(record);
    final sources = [
      record['checkout_options'],
      addon['checkout_options'],
      plan['checkout_options'],
    ];
    final options = <_CheckoutOption>[];
    final seen = <String>{};
    for (final source in sources) {
      if (source is! List) continue;
      for (final value in source.whereType<Map>()) {
        final item = _map(value);
        final gateway = item['gateway']?.toString().trim().toLowerCase() ?? '';
        final currency =
            item['currency']?.toString().trim().toUpperCase() ?? '';
        if (gateway.isEmpty || currency.isEmpty) continue;
        final key = '$gateway.$currency';
        if (seen.add(key)) {
          options.add(
            _CheckoutOption(
              gateway: gateway,
              currency: currency,
              label: item['label']?.toString() ?? currency,
              amountMinor: item['amount_minor'],
              currencyMinorUnits: item['currency_minor_units'],
            ),
          );
        }
      }
    }
    final preferred = _profile['preferred_currency']?.toString().toUpperCase();
    options.sort((a, b) {
      final aPreferred = a.currency == preferred ? 0 : 1;
      final bPreferred = b.currency == preferred ? 0 : 1;
      return aPreferred.compareTo(bPreferred);
    });
    return options;
  }

  String _providerName(String gateway) => gateway
      .split(RegExp(r'[_\-\s]+'))
      .map(
        (part) => part.isEmpty
            ? part
            : '${part[0].toUpperCase()}${part.substring(1)}',
      )
      .join(' ');

  String _checkoutAmount(Map<String, dynamic> record, _CheckoutOption option) {
    final amount = formatMajorMoney(
      _integer(option.amountMinor) /
          math.pow(
            10,
            _integer(
              option.currencyMinorUnits,
              fallback: currencyPrecision(option.currency),
            ),
          ),
      option.currency,
    );
    return amount;
  }

  String _initials(String name) {
    final parts = name.trim().split(RegExp(r'\s+')).where((p) => p.isNotEmpty);
    final values = parts.take(2).map((p) => p[0].toUpperCase()).join();
    return values.isEmpty ? 'TL' : values;
  }

  void _message(String text, {bool error = false}) {
    if (!mounted) return;
    final messenger = ScaffoldMessenger.of(context);
    messenger
      ..hideCurrentSnackBar()
      ..showSnackBar(
        SnackBar(
          content: Text(text),
          backgroundColor: error ? _AddonColors.danger : _AddonColors.ink,
          behavior: SnackBarBehavior.floating,
        ),
      );
  }

  @override
  Widget build(BuildContext context) {
    final name = _profile['full_name']?.toString().trim().isNotEmpty == true
        ? _profile['full_name'].toString().trim()
        : (_profile['username']?.toString() ?? 'Member');
    final preferredCurrency = _preferredCurrency;
    final activeAddonCount = _addonRecords.where(_recordIsActive).length;
    final activeSubscriptions = _activeMarketplaces
        .where((item) => item['status']?.toString().toLowerCase() == 'active')
        .toList(growable: false);
    final wallet = _wallet();
    final walletCurrency = _preferredCurrency;
    final walletBalance = _money(
      wallet['available_balance_minor'] ?? wallet['balance_minor'] ?? 0,
      walletCurrency,
      wallet['currency_minor_units'],
    );
    final theme = Theme.of(context);

    return Theme(
      data: theme.copyWith(
        colorScheme: ColorScheme.fromSeed(
          seedColor: _AddonColors.green,
          brightness: theme.brightness,
        ),
        scaffoldBackgroundColor: const Color(0xFFF5F8F7),
      ),
      child: Builder(
        builder: (context) {
          final colors = Theme.of(context).colorScheme;
          return Scaffold(
            appBar: AppBar(
              title: const Text(
                'TLsotre Proxy',
                style: TextStyle(fontWeight: FontWeight.w800),
              ),
              foregroundColor: Colors.white,
              backgroundColor: _AddonColors.greenDark,
              flexibleSpace: const DecoratedBox(
                decoration: BoxDecoration(
                  gradient: _AddonColors.headerGradient,
                ),
              ),
              actions: [
                IconButton(
                  tooltip: 'Refresh add-ons',
                  onPressed: _refreshing ? null : _refresh,
                  icon: const Icon(Icons.refresh_rounded),
                ),
                Container(
                  width: 40, // Container size
                  height: 40,
                  clipBehavior:
                      Clip.antiAlias, // Crucial for clipping the image
                  decoration: BoxDecoration(
                    color: Colors.grey[200], // Optional background color
                    borderRadius: BorderRadius.circular(
                      12,
                    ), // Rounded corners radius
                  ),
                  child: Image.asset(
                    'assets/logo.jpg',
                    fit: BoxFit
                        .cover, // Ensures the image fills the container space
                  ),
                ),
                const SizedBox(width: 8),
              ],
            ),
            body: RefreshIndicator(
              onRefresh: _refresh,
              color: _AddonColors.green,
              child: ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.fromLTRB(0, 0, 0, 0),
                children: [
                  const _ProxyBenefits(imageLink: 'assets/cards/header.jpg'),
                  _AccountHeader(
                    country:
                        [
                              _profile['country_name']?.toString(),
                              _profile['country_code']?.toString(),
                            ]
                            .whereType<String>()
                            .where((part) => part.isNotEmpty)
                            .join(' · '),
                    initials: _initials(name),
                    emailVerified:
                        _profile['email_verified'] == true ||
                        _profile['email_verified'] == 1,
                    activePackageCount: activeSubscriptions.length,
                    preferredCurrency: preferredCurrency,
                  ),
                  Padding(
                    padding: const EdgeInsets.fromLTRB(15, 0, 15, 0),
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const SizedBox(height: 14),
                        _SummaryRow(
                          activeAddons: activeAddonCount,
                          activePackages: activeSubscriptions.length,
                          walletBalance: walletBalance,
                          walletCurrency: walletCurrency,
                        ),
                        if (_profileError != null) ...[
                          const SizedBox(height: 12),
                          _InlineNotice(
                            message: _profileError!,
                            icon: Icons.cloud_off_outlined,
                            onRetry: _refresh,
                          ),
                        ],
                        if (_pendingIntentId != null) ...[
                          const SizedBox(height: 12),
                          _PendingPaymentCard(
                            addonName: _pendingAddonName,
                            onCheck: _checkPendingPayment,
                            loading: _paying,
                          ),
                        ],
                        const SizedBox(height: 20),
                        _SectionHeading(
                          eyebrow: 'SECURE ACCESS',
                          title: 'Your proxy add-ons',
                          subtitle: 'Review proxies, locations access, IP addresses and security features attached to your app packages.',
                        ),
                        const SizedBox(height: 11),
                        if (_refreshing && !_hasLoadedAddons)
                          const _AddonsLoading()
                        else if (_addonsError != null && _addonRecords.isEmpty)
                          _InlineNotice(
                            message: _addonsError!,
                            icon: Icons.cloud_off_outlined,
                            onRetry: _refresh,
                          )
                        else if (_addonRecords.isEmpty)
                          const _EmptyAddons()
                        else
                          for (final record in _addonRecords) ...[
                            _addonCard(context, record),
                            const SizedBox(height: 11),
                          ],
                        if (_addonRecords.isNotEmpty &&
                            _addonsError != null) ...[
                          const SizedBox(height: 2),
                          _InlineNotice(
                            message: _addonsError!,
                            icon: Icons.info_outline_rounded,
                            onRetry: _refresh,
                          ),
                        ],
                        const SizedBox(height: 11),
                      ],
                    ),
                  ),
                  const _PrivacyFootnote(),
                  const SizedBox(height: 10),
                  const _ProxyBenefits(imageLink: 'assets/cards/benefits.png'),
                ],
              ),
            ),
            floatingActionButton: FloatingActionButton(
              onPressed: () => showProxyInformation(context),
              tooltip: 'Why do you need TLstore Proxy?',
              backgroundColor: Colors.transparent,
              elevation: 4,
              child: Ink(
                decoration: const BoxDecoration(
                  gradient: LinearGradient(
                    colors: [
                      Color(0xFF2196F3), // Blue
                      Color(0xFF9C27B0), // Purple
                      Color(0xFFFF4081), // Pink
                    ],
                    begin: Alignment.topLeft,
                    end: Alignment.bottomRight,
                  ),
                  shape: BoxShape.circle,
                ),
                child: const Center(
                  child: Icon(
                    Icons.help_outline,
                    color: Colors.white,
                    size: 38,
                  ),
                ),
              ),
            ),
          );
        },
      ),
    );
  }

  String? get _pendingAddonName {
    if (_pendingAddonId == null) return null;
    for (final record in _addonRecords) {
      if (_addonId(record) == _pendingAddonId) {
        return _addon(record)['name']?.toString();
      }
    }
    return null;
  }

  Widget _addonCard(BuildContext context, Map<String, dynamic> record) {
    final addon = _addon(record);
    final plan = _planFor(record);
    final isActive = _recordIsActive(record);
    final pending = _recordIsPending(record);
    final available =
        addon['is_active'] != false &&
        addon['is_active'] != 0 &&
        addon['is_active'] != '0';
    final colors = Theme.of(context).colorScheme;
    final name = addon['name']?.toString() ?? 'Proxy';
    final entitlement = _effectiveEntitlement(record);
    final currency =
        (entitlement['currency'] ??
                record['currency'] ??
                addon['currency'] ??
                'USD')
            .toString()
            .toUpperCase();
    final amount =
        entitlement['price_minor'] ??
        record['price_minor'] ??
        record['payable_price_minor'] ??
        addon['price_minor'];
    final units =
        entitlement['currency_minor_units'] ??
        record['currency_minor_units'] ??
        addon['currency_minor_units'] ??
        plan['currency_minor_units'];
    final price = amount == null ? null : _money(amount, currency, units);
    final endsAt = entitlement['ends_at'] ?? record['ends_at'];
    final status = isActive
        ? 'ACTIVE'
        : pending
        ? 'PAYMENT PENDING'
        : !available
        ? 'UNAVAILABLE'
        : (record['status']?.toString().toLowerCase() == 'expired'
              ? 'EXPIRED'
              : 'READY TO ACTIVATE');
    final statusColor = isActive
        ? _AddonColors.green
        : pending
        ? _AddonColors.amber
        : !available
        ? _AddonColors.muted
        : _AddonColors.blue;
    final features = proxyFeatures(record);
    final locationFeatures = features
        .where((item) => item.icon == Icons.location_on_rounded)
        .toList(growable: false);
    final locations = locationFeatures.isEmpty
        ? null
        : locationFeatures.first.label.replaceFirst('Locations: ', '');
    final subtitle = [
      if (plan['name'] != null) plan['name'].toString(),
      if (locations != null) locations,
      if (isActive && endsAt != null) 'Renews/ends ${_date(endsAt)}',
      if (!isActive && price != null) '$price ${getDuration(addon)}',
    ].join(' · ');
    final actionLabel = isActive
        ? 'View proxy details'
        : pending
        ? 'Check payment'
        : !available
        ? 'Unavailable'
        : 'Purchase Proxy';

    final nestedActive = _map(record['active_entitlement']);
    final isDirectActive = const [
      'active',
      'trialing',
    ].contains(record['status']?.toString().toLowerCase());
    final active = nestedActive.isNotEmpty
        ? nestedActive
        : isDirectActive
        ? record
        : const <String, dynamic>{};

    final proxy_features = <String, dynamic>{
      ..._map(addon['features']),
      ..._map(active['features']),
      ..._map(record['features']),
    };

    final hasConnection =
        proxy_features.containsKey('location') ||
        proxy_features.containsKey('locations') ||
        proxy_features.containsKey('ip_addresses') ||
        proxy_features['encrypted_tunnel'] == true ||
        proxy_features['encrypted_tunnel'] == 1;

    return Material(
      color: Colors.white,
      borderRadius: BorderRadius.circular(23),
      child: InkWell(
        onTap: isActive || available || pending
            ? () => _openAddon(record)
            : null,
        borderRadius: BorderRadius.circular(23),
        child: Container(
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(23),
            border: Border.all(color: colors.outlineVariant),
            boxShadow: const [
              BoxShadow(
                color: Color(0x0A1E293B),
                blurRadius: 18,
                offset: Offset(0, 7),
              ),
            ],
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Container(
                    width: 49,
                    height: 49,
                    decoration: BoxDecoration(
                      gradient: isActive
                          ? _AddonColors.greenTile
                          : _AddonColors.blueTile,
                      borderRadius: BorderRadius.circular(16),
                    ),
                    child: Icon(
                      isActive
                          ? Icons.verified_user_rounded
                          : Icons.shield_outlined,
                      color: Colors.white,
                      size: 25,
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          '$name proxy',
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: Theme.of(context).textTheme.titleMedium
                              ?.copyWith(
                                color: _AddonColors.ink,
                                fontWeight: FontWeight.w900,
                              ),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          subtitle.isEmpty
                              ? 'Protected package access'
                              : subtitle,
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          style: Theme.of(context).textTheme.bodySmall
                              ?.copyWith(
                                color: colors.onSurfaceVariant,
                                height: 1.35,
                              ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(width: 7),
                  _StatusBadge(label: status, color: statusColor),
                ],
              ),
              const SizedBox(height: 14),
              Wrap(
                spacing: 7,
                runSpacing: 7,
                children: features
                    .take(4)
                    .map(
                      (feature) => _FeaturePill(
                        label: feature.label,
                        icon: feature.icon,
                      ),
                    )
                    .toList(),
              ),
              const SizedBox(height: 14),
              Row(
                children: [
                  Expanded(
                    child: Text(
                      price == null
                          ? (isActive
                                ? 'Active coverage'
                                : 'Pricing shown at checkout')
                          : '$price ${isActive ? 'paid' : getDuration(addon)}',
                      style: Theme.of(context).textTheme.labelLarge?.copyWith(
                        color: isActive
                            ? _AddonColors.greenDark
                            : _AddonColors.ink,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                  ),

                  if (isActive && hasConnection) ...[
                    const SizedBox(height: 5),
                    Align(
                      alignment: Alignment.centerLeft,
                      child: TextButton.icon(
                        onPressed: () => _openProxyConnection(record),
                        style: TextButton.styleFrom(
                          foregroundColor: colors.primary,
                          padding: const EdgeInsets.symmetric(
                            horizontal: 10,
                            vertical: 5,
                          ),
                          minimumSize: const Size(0, 32),
                          tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                          visualDensity: VisualDensity.compact,
                        ),
                        icon: const Icon(Icons.vpn_key_rounded, size: 15),
                        label: const Text(
                          'View connection',
                          style: TextStyle(fontWeight: FontWeight.w800),
                        ),
                      ),
                    ),
                  ] else
                    TextButton.icon(
                      onPressed: isActive || available || pending
                          ? () => _openAddon(record)
                          : null,
                      icon: Icon(
                        isActive
                            ? Icons.manage_search_rounded
                            : pending
                            ? Icons.fact_check_outlined
                            : Icons.arrow_forward_rounded,
                        size: 18,
                      ),
                      label: Text(actionLabel),
                      style: TextButton.styleFrom(
                        foregroundColor: _AddonColors.green, // Icon and text color
                        side: BorderSide(
                          color: _AddonColors.green.withValues(alpha: .25), // Border color
                          width: 1.5,         // Border thickness
                        ),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(8), // Rounded corners
                        ),
                        padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                      ),
                    ),

                ],
              ),

              TextButton.icon(
                onPressed: () => showProxyInformation(context),
                style: TextButton.styleFrom(
                  padding: EdgeInsets.zero,
                  minimumSize: Size.zero,
                  tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                ),
                icon: Icon(Icons.help_outline, size: 18, color: Colors.red),
                label: Text('Why purchase proxy', 
                  style: TextStyle(color: Colors.red,  fontWeight: FontWeight.w800),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Future<void> _openAddon(Map<String, dynamic> record) async {
    if (!mounted) return;
    if (_recordIsActive(record)) {
      await _showActiveDetails(record);
      return;
    }
    if (_recordIsPending(record)) {
      final intent =
          record['payment_intent_id'] ??
          _map(record['latest_entitlement'])['payment_intent_id'];
      if (intent != null) {
        _pendingIntentId = intent;
        _pendingAddonId = _addonId(record);
        await _checkPendingPayment();
        return;
      }
    }
    final addon = _addon(record);
    final plan = _planFor(record);
    final addonName = addon['name']?.toString() ?? 'Proxy';
    final planName = plan['name']?.toString() ?? 'Apps';
    final currency = (record['currency'] ?? addon['currency'] ?? 'USD')
        .toString()
        .toUpperCase();
    final amount = record['payable_price_minor'] ?? addon['price_minor'];
    final units =
        record['currency_minor_units'] ??
        addon['currency_minor_units'] ??
        plan['currency_minor_units'];
    final price = amount == null
        ? 'Price at checkout'
        : _money(amount, currency, units);
    final featureList = proxyFeatures(record);
    final featured = featureList.take(6).toList(growable: false);
    await ProxyAddonsDialog.show<void>(
      context: context,
      plan: ProxyPromptPlan(
        storeName: 'TrustLens Store',
        storeWord: 'proxy',
        titleLead: 'Why do i need a\n',
        titleHighlight: 'Proxy',
        titleTail: '?',
        noticeLead: 'An ',
        noticeHighlight: 'active $addonName',
        noticeBody:
            ' is required to submit $planName reviews on TrustLens Store. Work done without a proxy ',
        noticeWarning: 'may be rejected.',
        price: price,
        billingPeriod: getDuration(addon),
        requiredLabel: 'Required',
        purchaseLabel: 'ACTIVATE PROXY',
        trustLabel: 'Secure checkout',
        footerBrand: 'TrustLens Store',
        features: featured,
      ),
      onPurchase: () => _showPaymentOptions(record),
      onReview: () => Navigator.pop(context, 'cancel'),
    );
  }

  Future<void> _showActiveDetails(Map<String, dynamic> record) async {
    final addon = _addon(record);
    final name = addon['name']?.toString() ?? 'Proxy add-on';
    final entitlement = _effectiveEntitlement(record);
    final features = _map(entitlement['features']).isNotEmpty
        ? _map(entitlement['features'])
        : _map(record['features']);
    final locations = features['location'] ?? features['country_code'];
    final ips = _map(features['ip_addresses']);
    final featureList = proxyFeatures(record);
    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      showDragHandle: true,
      backgroundColor: Colors.white,
      builder: (sheetContext) => Padding(
        padding: EdgeInsets.fromLTRB(
          20,
          4,
          20,
          MediaQuery.viewInsetsOf(sheetContext).bottom + 22,
        ),
        child: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Container(
                    width: 48,
                    height: 48,
                    decoration: const BoxDecoration(
                      gradient: _AddonColors.greenTile,
                      shape: BoxShape.circle,
                    ),
                    child: const Icon(
                      Icons.verified_user_rounded,
                      color: Colors.white,
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          '$name proxy',
                          style: Theme.of(sheetContext).textTheme.titleLarge
                              ?.copyWith(fontWeight: FontWeight.w900),
                        ),
                        const SizedBox(height: 3),
                        const _StatusBadge(
                          label: 'ACTIVE',
                          color: _AddonColors.green,
                        ),
                      ],
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 16),
              _DetailLine(
                icon: Icons.event_available_outlined,
                label: 'Valid until',
                value: _date(entitlement['ends_at'] ?? record['ends_at']),
              ),
              _DetailLine(
                icon: Icons.payments_outlined,
                label: 'Last payment',
                value: _money(
                  entitlement['price_minor'] ?? record['price_minor'],
                  (entitlement['currency'] ?? record['currency'] ?? 'USD')
                      .toString(),
                  entitlement['currency_minor_units'] ??
                      record['currency_minor_units'],
                ),
              ),
              if (locations is List && locations.isNotEmpty)
                _DetailLine(
                  icon: Icons.public_rounded,
                  label: 'Locations',
                  value: locations.map((item) => item.toString()).join(', '),
                ),
              if (ips.isNotEmpty)
                for (final entry in ips.entries)
                  _DetailLine(
                    icon: Icons.dns_outlined,
                    label: '${entry.key} proxy IP',
                    value: entry.value.toString(),
                    selectable: true,
                  ),
              const SizedBox(height: 9),
              Text(
                'Included features',
                style: Theme.of(sheetContext).textTheme.titleSmall
                    ?.copyWith(fontWeight: FontWeight.w900),
              ),
              const SizedBox(height: 8),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: featureList
                    .map(
                      (feature) => _FeaturePill(
                        label: feature.label,
                        icon: feature.icon,
                      ),
                    )
                    .toList(),
              ),
              const SizedBox(height: 18),
              SizedBox(
                width: double.infinity,
                child: OutlinedButton(
                  onPressed: () => Navigator.of(sheetContext).pop(),
                  child: const Text('Done'),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Future<void> _showPaymentOptions(Map<String, dynamic> record) async {
    if (!mounted || _paying) return;
    final addon = _addon(record);
    final name = addon['name']?.toString() ?? 'Proxy add-on';
    final options = _checkoutOptions(record);
    final wallet = _wallet();
    final walletCurrency = _preferredCurrency;
    final walletAmount = _money(
      wallet['available_balance_minor'] ?? wallet['balance_minor'] ?? 0,
      walletCurrency,
      wallet['currency_minor_units'],
    );

    final choice = await showModalBottomSheet<_PaymentChoice>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      showDragHandle: true,
      backgroundColor: Colors.white,
      builder: (sheetContext) => Padding(
        padding: EdgeInsets.fromLTRB(
          20,
          3,
          20,
          MediaQuery.viewInsetsOf(sheetContext).bottom + 18,
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Choose payment method',
              style: Theme.of(sheetContext).textTheme.titleLarge
                  ?.copyWith(fontWeight: FontWeight.w900),
            ),
            const SizedBox(height: 5),
            Text(
              'Pay for $name securely. Your selected plan features are shown before this step.',
              style: Theme.of(sheetContext).textTheme.bodyMedium
                  ?.copyWith(color: _AddonColors.muted),
            ),
            const SizedBox(height: 15),
            _PaymentMethodTile(
              icon: Icons.account_balance_wallet_outlined,
              title: 'Use account funds',
              subtitle: 'Available balance · $walletAmount',
              onTap: () =>
                  Navigator.of(sheetContext).pop(_PaymentChoice.wallet()),
              type: false,
            ),
            if (options.isNotEmpty) ...[
              const Padding(
                padding: EdgeInsets.symmetric(vertical: 10),
                child: _OrDivider(),
              ),
              for (final option in options) ...[
                _PaymentMethodTile(
                  type: true,
                  flag: _currencyInfo(option.currency)?.flag ?? '🌐',
                  title:
                      '${_providerName(option.gateway)} · ${option.currency}',
                  subtitle:
                      'Amount to pay · ${_checkoutAmount(record, option)}',
                  onTap: () =>
                      Navigator.of(sheetContext)
                          .pop(_PaymentChoice.online(option)),
                ),
              ],
            ] else ...[
              const SizedBox(height: 10),
              Text(
                'Online checkout options are not available for this package right now.',
                style: Theme.of(sheetContext).textTheme.bodySmall
                    ?.copyWith(color: _AddonColors.muted),
              ),
            ],
            const SizedBox(height: 9),
          ],
        ),
      ),
    );
    if (!mounted || choice == null) return;
    if (choice.wallet) {
      await _payWithWallet(record);
    } else if (choice.option != null) {
      await _startOnlineCheckout(record, choice.option!);
    }
  }

  Future<void> _payWithWallet(Map<String, dynamic> record) async {
    final addonId = _addonId(record);
    if (addonId == null) {
      _message(
        'This add-on is missing its package ID. Please refresh.',
        error: true,
      );
      return;
    }
    final messenger = ScaffoldMessenger.of(context);
    setState(() => _paying = true);
    try {
      await widget.api.purchasePackageAddonWithFunds(
        token: widget.token,
        addonId: addonId,
        idempotencyKey: _idempotencyKey(addonId),
      );
      if (!mounted) return;
      await _refresh();
      if (!mounted) return;
      messenger.showSnackBar(
        const SnackBar(
          content: Text('Proxy add-on activated successfully.'),
          behavior: SnackBarBehavior.floating,
        ),
      );
    } on AuthException catch (error) {
      if (!mounted) return;
      messenger.showSnackBar(
        SnackBar(
          content: Text(error.message),
          backgroundColor: _AddonColors.danger,
          behavior: SnackBarBehavior.floating,
        ),
      );
    } catch (_) {
      if (!mounted) return;
      messenger.showSnackBar(
        const SnackBar(
          content: Text(
            'Wallet payment could not be completed. Try an online method.',
          ),
          backgroundColor: _AddonColors.danger,
        ),
      );
    } finally {
      if (mounted) setState(() => _paying = false);
    }
  }

  Future<void> _startOnlineCheckout(
    Map<String, dynamic> record,
    _CheckoutOption option,
  ) async {
    final addonId = _addonId(record);
    if (addonId == null) {
      _message(
        'This add-on is missing its package ID. Please refresh.',
        error: true,
      );
      return;
    }
    final messenger = ScaffoldMessenger.of(context);
    setState(() => _paying = true);
    try {
      final response = await widget.api.checkoutPackageAddon(
        token: widget.token,
        addonId: addonId,
        gateway: option.gateway,
        currency: option.currency,
        idempotencyKey: _idempotencyKey(addonId),
      );
      if (!mounted) return;
      final payload = _map(response['data']);
      final intent =
          payload['payment_intent_id'] ?? payload['intent_id'] ?? payload['id'];
      final checkoutUrl = _checkoutUrl(payload);
      if (intent != null) {
        setState(() {
          _pendingIntentId = intent;
          _pendingAddonId = addonId;
        });
      }
      if (checkoutUrl != null) {
        final launched = await launchUrl(
          checkoutUrl,
          mode: LaunchMode.externalApplication,
        );
        if (!mounted) return;
        if (!launched) {
          messenger.showSnackBar(
            const SnackBar(
              content: Text(
                'Could not open the payment page. Use Check payment after retrying.',
              ),
              backgroundColor: _AddonColors.danger,
            ),
          );
          return;
        }
        messenger.showSnackBar(
          const SnackBar(
            content: Text(
              'Checkout opened. Return here and tap Check payment when finished.',
            ),
            behavior: SnackBarBehavior.floating,
          ),
        );
      } else if (_isPaid(response)) {
        await _refresh();
        if (mounted)
          messenger.showSnackBar(
            const SnackBar(
              content: Text('Proxy add-on activated successfully.'),
            ),
          );
      } else if (intent != null) {
        messenger.showSnackBar(
          const SnackBar(
            content: Text(
              'Payment started. Tap Check payment when you finish.',
            ),
            behavior: SnackBarBehavior.floating,
          ),
        );
      } else {
        messenger.showSnackBar(
          SnackBar(
            content: Text(
              response['message']?.toString() ??
                  'Checkout response was incomplete.',
            ),
            backgroundColor: _AddonColors.danger,
          ),
        );
      }
    } on AuthException catch (error) {
      if (!mounted) return;
      messenger.showSnackBar(
        SnackBar(
          content: Text(error.message),
          backgroundColor: _AddonColors.danger,
          behavior: SnackBarBehavior.floating,
        ),
      );
    } catch (_) {
      if (!mounted) return;
      messenger.showSnackBar(
        const SnackBar(
          content: Text(
            'Secure checkout could not be started. Please try again.',
          ),
          backgroundColor: _AddonColors.danger,
        ),
      );
    } finally {
      if (mounted) setState(() => _paying = false);
    }
  }

  Uri? _checkoutUrl(Map<String, dynamic> payload) {
    final raw =
        payload['checkout_url'] ??
        payload['payment_url'] ??
        payload['authorization_url'] ??
        payload['url'];
    final uri = Uri.tryParse(raw?.toString() ?? '');
    if (uri == null || !uri.hasAuthority) return null;
    return (uri.scheme == 'http' || uri.scheme == 'https') ? uri : null;
  }

  bool _isPaid(Map<String, dynamic> response) {
    final data = _map(response['data']);
    final status = (data['status'] ?? response['status'])
        ?.toString()
        .toLowerCase();
    return status == 'active' || status == 'paid' || status == 'success';
  }

  String _idempotencyKey(int addonId) =>
      'addon-$addonId-${DateTime.now().microsecondsSinceEpoch}';

  Future<void> _checkPendingPayment() async {
    final intent = _pendingIntentId;
    if (intent == null || _paying) return;
    final messenger = ScaffoldMessenger.of(context);
    setState(() => _paying = true);
    try {
      final response = await widget.api.checkPackageAddonPayment(
        widget.token,
        intent,
      );
      if (!mounted) return;
      if (_isPaid(response)) {
        setState(() {
          _pendingIntentId = null;
          _pendingAddonId = null;
        });
        await _refresh();
        if (!mounted) return;
        messenger.showSnackBar(
          const SnackBar(
            content: Text('Payment confirmed. Proxy access is active.'),
          ),
        );
      } else {
        messenger.showSnackBar(
          const SnackBar(
            content: Text(
              'Payment is still processing. Check again in a moment.',
            ),
          ),
        );
      }
    } on AuthException catch (error) {
      if (!mounted) return;
      messenger.showSnackBar(
        SnackBar(
          content: Text(error.message),
          backgroundColor: _AddonColors.danger,
        ),
      );
    } catch (_) {
      if (!mounted) return;
      messenger.showSnackBar(
        const SnackBar(
          content: Text('Payment status could not be checked. Please retry.'),
          backgroundColor: _AddonColors.danger,
        ),
      );
    } finally {
      if (mounted) setState(() => _paying = false);
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

  int _integer(Object? value, {int fallback = 0}) => value is num
      ? value.toInt()
      : int.tryParse(value?.toString() ?? '') ?? fallback;
}

class _ApiOutcome {
  const _ApiOutcome({this.data, this.error});
  final Map<String, dynamic>? data;
  final Object? error;
}

class _CheckoutOption {
  const _CheckoutOption({
    required this.gateway,
    required this.currency,
    required this.label,
    this.amountMinor,
    this.currencyMinorUnits,
  });
  final String gateway;
  final String currency;
  final String label;
  final Object? amountMinor;
  final Object? currencyMinorUnits;
}

class _PaymentChoice {
  const _PaymentChoice._({this.wallet = false, this.option});
  const _PaymentChoice.wallet() : this._(wallet: true);
  const _PaymentChoice.online(_CheckoutOption value) : this._(option: value);
  final bool wallet;
  final _CheckoutOption? option;
}

abstract final class _AddonColors {
  static const green = Color(0xFF0B9B5B);
  static const greenDark = Color(0xFF076C43);
  static const greenLight = Color(0xFFE9F8F0);
  static const blue = Color(0xFF3476C5);
  static const amber = Color(0xFFB77A19);
  static const danger = Color(0xFFB42318);
  static const ink = Color(0xFF18232D);
  static const muted = Color(0xFF697780);
  static const headerGradient = LinearGradient(
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
    colors: [Color(0xFF075F3D), Color(0xFF0A9B5B), Color(0xFF29B878)],
  );
  static const greenTile = LinearGradient(
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
    colors: [Color(0xFF12A565), Color(0xFF057344)],
  );
  static const blueTile = LinearGradient(
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
    colors: [Color(0xFF3984D4), Color(0xFF245E9B)],
  );
}

class _AccountHeader extends StatelessWidget {
  const _AccountHeader({
    required this.country,
    required this.initials,
    required this.emailVerified,
    required this.activePackageCount,
    required this.preferredCurrency,
  });

  final String country;
  final String initials;
  final bool emailVerified;
  final int activePackageCount;
  final String preferredCurrency;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(0),
    decoration: BoxDecoration(
      gradient: _AddonColors.headerGradient,
      borderRadius: BorderRadius.circular(0),
      boxShadow: const [
        BoxShadow(
          color: Color(0x24056B44),
          blurRadius: 21,
          offset: Offset(0, 9),
        ),
      ],
    ),
    child: Stack(
      children: [
        Positioned.fill(
          child: ClipRRect(
            borderRadius: const BorderRadius.only(
              bottomLeft: Radius.circular(0),
              bottomRight: Radius.circular(0),
            ),
            child: Opacity(
              opacity: 0.95,
              child: Image.asset(
                'assets/cards/background.jpg',
                fit: BoxFit.cover,
                colorBlendMode: BlendMode.darken,
              ),
            ),
          ),
        ),
        Padding(
          padding: const EdgeInsets.all(10),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const SizedBox(width: 72),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'TLSTORE CONTROL CENTER',
                          style: TextStyle(
                            color: Colors.black.withValues(alpha: .78),
                            fontSize: 10,
                            letterSpacing: 1.25,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                      ],
                    ),
                  ),
                  if (emailVerified) ...[
                    const SizedBox(width: 5),
                    const Icon(
                      Icons.verified_rounded,
                      size: 20,
                      color: Colors.black,
                    ),
                  ],
                ],
              ),
              const SizedBox(height: 17),
              const SizedBox(height: 15),
              Text(
                'Manage your account proxies, assigned IP Address, Phone numbers and package-linked proxy access.',
                style: TextStyle(
                  color: Colors.black.withValues(alpha: .92),
                  fontSize: 13,
                  height: 1.45,
                ),
              ),
            ],
          ),
        ),
      ],
    ),
  );
}

class _HeaderTag extends StatelessWidget {
  const _HeaderTag({required this.icon, required this.label});
  final IconData icon;
  final String label;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 7),
    decoration: BoxDecoration(
      color: Colors.black.withValues(alpha: .15),
      borderRadius: BorderRadius.circular(30),
      border: Border.all(color: Colors.black.withValues(alpha: .2)),
    ),
    child: Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Icon(icon, size: 14, color: Colors.black),
        const SizedBox(width: 6),
        Text(
          label,
          style: const TextStyle(
            color: Colors.black,
            fontSize: 11,
            fontWeight: FontWeight.w700,
          ),
        ),
      ],
    ),
  );
}

class _SummaryRow extends StatelessWidget {
  const _SummaryRow({
    required this.activeAddons,
    required this.activePackages,
    required this.walletBalance,
    required this.walletCurrency,
  });

  final int activeAddons;
  final int activePackages;
  final String walletBalance;
  final String walletCurrency;

  @override
  Widget build(BuildContext context) {
    final width = MediaQuery.sizeOf(context).width;
    final compact = width < 380;
    final values = [
      _SummaryMetric(
        'Active proxies',
        '$activeAddons',
        Icons.shield_rounded,
        _AddonColors.green,
      ),
      _SummaryMetric(
        'Wallet · $walletCurrency',
        walletBalance,
        Icons.account_balance_wallet_outlined,
        _AddonColors.amber,
      ),
    ];
    return Row(
      children: [
        for (var index = 0; index < values.length; index++) ...[
          if (index > 0) const SizedBox(width: 8),
          Expanded(
            child: _MetricCard(metric: values[index], compact: compact),
          ),
        ],
      ],
    );
  }
}

class _SummaryMetric {
  const _SummaryMetric(this.label, this.value, this.icon, this.color);
  final String label;
  final String value;
  final IconData icon;
  final Color color;
}

class _MetricCard extends StatelessWidget {
  const _MetricCard({required this.metric, required this.compact});
  final _SummaryMetric metric;
  final bool compact;

  @override
  Widget build(BuildContext context) => Container(
    constraints: const BoxConstraints(minHeight: 60),
    padding: EdgeInsets.all(compact ? 6 : 9),
    decoration: BoxDecoration(
      color: Colors.white,
      borderRadius: BorderRadius.circular(18),
      border: Border.all(color: const Color(0xFFE4EAE8)),
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Icon(metric.icon, size: 17, color: metric.color),
            const SizedBox(width: 5),
            Expanded(
              child: Text(
                metric.label,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  color: _AddonColors.muted,
                  fontSize: 10,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ),
          ],
        ),
        const SizedBox(height: 2),
        FittedBox(
          fit: BoxFit.scaleDown,
          alignment: Alignment.centerLeft,
          child: Text(
            metric.value,
            maxLines: 1,
            style: TextStyle(
              color: _AddonColors.ink,
              fontSize: compact ? 13 : 16,
              fontWeight: FontWeight.w900,
            ),
          ),
        ),
      ],
    ),
  );
}

class _SectionHeading extends StatelessWidget {
  const _SectionHeading({
    required this.eyebrow,
    required this.title,
    required this.subtitle,
  });
  final String eyebrow;
  final String title;
  final String subtitle;

  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Text(
        eyebrow,
        style: const TextStyle(
          color: _AddonColors.green,
          fontSize: 10,
          letterSpacing: 1.1,
          fontWeight: FontWeight.w900,
        ),
      ),
      const SizedBox(height: 4),
      Text(
        title,
        style: Theme.of(context).textTheme.titleLarge
            ?.copyWith(color: _AddonColors.ink, fontWeight: FontWeight.w900),
      ),
      const SizedBox(height: 4),
      Text(
        subtitle,
        style: Theme.of(context).textTheme.bodySmall
            ?.copyWith(color: _AddonColors.muted, height: 1.45),
      ),
    ],
  );
}

class _StatusBadge extends StatelessWidget {
  const _StatusBadge({required this.label, required this.color});
  final String label;
  final Color color;

  @override
  Widget build(BuildContext context) => Container(
    constraints: const BoxConstraints(maxWidth: 122),
    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 6),
    decoration: BoxDecoration(
      color: color.withValues(alpha: .11),
      borderRadius: BorderRadius.circular(20),
    ),
    child: Text(
      label,
      maxLines: 1,
      overflow: TextOverflow.ellipsis,
      style: TextStyle(
        color: color,
        fontSize: 9,
        letterSpacing: .25,
        fontWeight: FontWeight.w900,
      ),
    ),
  );
}

class _FeaturePill extends StatelessWidget {
  const _FeaturePill({required this.label, required this.icon});
  final String label;
  final IconData icon;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 7),
    decoration: BoxDecoration(
      color: const Color(0xFFF1F8F4),
      borderRadius: BorderRadius.circular(30),
      border: Border.all(color: const Color(0xFFDDEDE4)),
    ),
    child: Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Icon(icon, size: 14, color: _AddonColors.greenDark),
        const SizedBox(width: 5),
        ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 180),
          child: Text(
            label,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(
              color: _AddonColors.ink,
              fontSize: 10,
              fontWeight: FontWeight.w700,
            ),
          ),
        ),
      ],
    ),
  );
}

class _InlineNotice extends StatelessWidget {
  const _InlineNotice({
    required this.message,
    required this.icon,
    this.onRetry,
  });
  final String message;
  final IconData icon;
  final VoidCallback? onRetry;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(13),
    decoration: BoxDecoration(
      color: const Color(0xFFFFF8EA),
      borderRadius: BorderRadius.circular(16),
      border: Border.all(color: const Color(0xFFF0DFB7)),
    ),
    child: Row(
      children: [
        Icon(icon, color: _AddonColors.amber, size: 20),
        const SizedBox(width: 9),
        Expanded(
          child: Text(
            message,
            style: const TextStyle(color: _AddonColors.ink, fontSize: 12),
          ),
        ),
        if (onRetry != null)
          IconButton(
            tooltip: 'Retry',
            onPressed: onRetry,
            icon: const Icon(Icons.refresh_rounded),
            color: _AddonColors.green,
          ),
      ],
    ),
  );
}

class _EmptyAddons extends StatelessWidget {
  const _EmptyAddons();

  @override
  Widget build(BuildContext context) => Container(
    width: double.infinity,
    padding: const EdgeInsets.all(22),
    decoration: BoxDecoration(
      color: Colors.white,
      borderRadius: BorderRadius.circular(21),
      border: Border.all(color: const Color(0xFFE2EAE6)),
    ),
    child: Column(
      children: [
        Container(
          width: 57,
          height: 57,
          decoration: const BoxDecoration(
            color: _AddonColors.greenLight,
            shape: BoxShape.circle,
          ),
          child: const Icon(Icons.shield_outlined, color: _AddonColors.green),
        ),
        const SizedBox(height: 11),
        const Text(
          'No proxy add-ons yet',
          style: TextStyle(
            color: _AddonColors.ink,
            fontWeight: FontWeight.w900,
          ),
        ),
        const SizedBox(height: 5),
        const Text(
          'Once a proxy add-on is attached to one of your review packages, its locations, IPs, and renewal dates will appear here.',
          textAlign: TextAlign.center,
          style: TextStyle(
            color: _AddonColors.muted,
            height: 1.45,
            fontSize: 12,
          ),
        ),
      ],
    ),
  );
}

class _AddonsLoading extends StatelessWidget {
  const _AddonsLoading();

  @override
  Widget build(BuildContext context) => const Center(
    child: Padding(
      padding: EdgeInsets.symmetric(vertical: 48),
      child: CircularProgressIndicator(color: _AddonColors.green),
    ),
  );
}

class _MarketplaceCard extends StatelessWidget {
  const _MarketplaceCard({
    required this.subscription,
    required this.plan,
    required this.dateFormatter,
  });
  final Map<String, dynamic> subscription;
  final Map<String, dynamic> plan;
  final String Function(Object?) dateFormatter;

  @override
  Widget build(BuildContext context) {
    final groupKey = subscription['group_key']?.toString() ?? '';
    final groupName = switch (groupKey) {
      'group_a' => 'Sponsored sharing',
      'group_b' => 'Android app reviews',
      'group_c' => 'iOS app reviews',
      _ => 'Marketplace package',
    };
    final name =
        plan['name']?.toString() ??
        subscription['plan_name']?.toString() ??
        'Active package';
    final endDate = dateFormatter(subscription['ends_at']);
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: const Color(0xFFE3E9E7)),
      ),
      child: Row(
        children: [
          Container(
            width: 39,
            height: 39,
            decoration: BoxDecoration(
              color: const Color(0xFFEAF5EF),
              borderRadius: BorderRadius.circular(13),
            ),
            child: Icon(
              groupKey == 'group_a'
                  ? Icons.campaign_outlined
                  : Icons.rate_review_outlined,
              color: _AddonColors.green,
              size: 20,
            ),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  name,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    fontWeight: FontWeight.w800,
                    color: _AddonColors.ink,
                  ),
                ),
                const SizedBox(height: 3),
                Text(
                  '$groupName · Valid until $endDate',
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: _AddonColors.muted,
                    fontSize: 11,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(width: 8),
          const _StatusBadge(label: 'ACTIVE', color: _AddonColors.green),
        ],
      ),
    );
  }
}

class _NoPackagesCard extends StatelessWidget {
  const _NoPackagesCard();

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(15),
    decoration: BoxDecoration(
      color: Colors.white,
      borderRadius: BorderRadius.circular(17),
      border: Border.all(color: const Color(0xFFE2EAE6)),
    ),
    child: const Row(
      children: [
        Icon(Icons.info_outline_rounded, color: _AddonColors.muted),
        SizedBox(width: 10),
        Expanded(
          child: Text(
            'No active app review package is linked to this account.',
            style: TextStyle(
              color: _AddonColors.muted,
              height: 1.4,
              fontSize: 12,
            ),
          ),
        ),
      ],
    ),
  );
}

class _PendingPaymentCard extends StatelessWidget {
  const _PendingPaymentCard({
    required this.addonName,
    required this.onCheck,
    required this.loading,
  });
  final String? addonName;
  final VoidCallback onCheck;
  final bool loading;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(14),
    decoration: BoxDecoration(
      color: const Color(0xFFFFF7E8),
      borderRadius: BorderRadius.circular(17),
      border: Border.all(color: const Color(0xFFF0D9A8)),
    ),
    child: Row(
      children: [
        const Icon(Icons.hourglass_top_rounded, color: _AddonColors.amber),
        const SizedBox(width: 9),
        Expanded(
          child: Text(
            '${addonName ?? 'Proxy'} payment is awaiting confirmation.',
            style: const TextStyle(
              color: _AddonColors.ink,
              fontSize: 12,
              height: 1.35,
            ),
          ),
        ),
        const SizedBox(width: 6),
        TextButton(
          onPressed: loading ? null : onCheck,
          child: loading
              ? const SizedBox(
                  width: 16,
                  height: 16,
                  child: CircularProgressIndicator(strokeWidth: 2),
                )
              : const Text('Check'),
        ),
      ],
    ),
  );
}

class _PaymentMethodTile extends StatelessWidget {
  const _PaymentMethodTile({
    this.icon,
    required this.title,
    required this.subtitle,
    required this.onTap,
    required this.type,
    this.flag,
  });
  final IconData? icon;
  final String title;
  final String subtitle;
  final VoidCallback onTap;
  final bool type;
  final String? flag;

  @override
  Widget build(BuildContext context) => ListTile(
    onTap: onTap,
    leading: Container(
      width: 42,
      height: 42,
      decoration: BoxDecoration(
        color: const Color(0xFFEAF6EF),
        borderRadius: BorderRadius.circular(13),
      ),
      child: type
          ? Text(flag ?? '', style: const TextStyle(fontSize: 26))
          : Icon(icon, color: _AddonColors.greenDark),
    ),
    title: Text(title, style: const TextStyle(fontWeight: FontWeight.w800)),
    subtitle: Text(subtitle, style: const TextStyle(fontSize: 12)),
    trailing: const Icon(Icons.chevron_right_rounded),
    contentPadding: EdgeInsets.zero,
  );
}

class _OrDivider extends StatelessWidget {
  const _OrDivider();

  @override
  Widget build(BuildContext context) => Row(
    children: [
      const Expanded(child: Divider()),
      Padding(
        padding: const EdgeInsets.symmetric(horizontal: 12),
        child: Text(
          'OR',
          style: Theme.of(context).textTheme.labelSmall?.copyWith(
            color: _AddonColors.muted,
            fontWeight: FontWeight.w800,
          ),
        ),
      ),
      const Expanded(child: Divider()),
    ],
  );
}

class _DetailLine extends StatelessWidget {
  const _DetailLine({
    required this.icon,
    required this.label,
    required this.value,
    this.selectable = false,
  });
  final IconData icon;
  final String label;
  final String value;
  final bool selectable;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 8),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Icon(icon, size: 18, color: _AddonColors.green),
        const SizedBox(width: 10),
        SizedBox(
          width: 100,
          child: Text(
            label,
            style: const TextStyle(color: _AddonColors.muted, fontSize: 12),
          ),
        ),
        Expanded(
          child: selectable
              ? SelectableText(
                  value,
                  style: const TextStyle(
                    color: _AddonColors.ink,
                    fontSize: 12,
                    fontWeight: FontWeight.w700,
                  ),
                )
              : Text(
                  value,
                  style: const TextStyle(
                    color: _AddonColors.ink,
                    fontSize: 12,
                    fontWeight: FontWeight.w700,
                  ),
                ),
        ),
      ],
    ),
  );
}

class _PrivacyFootnote extends StatelessWidget {
  const _PrivacyFootnote();

  @override
  Widget build(BuildContext context) => const Padding(
    padding: EdgeInsets.only(top: 4),
    child: Row(
      mainAxisAlignment: MainAxisAlignment.center,
      children: [
        Icon(Icons.lock_outline_rounded, size: 14, color: _AddonColors.muted),
        SizedBox(width: 6),
        Flexible(
          child: Text(
            'Proxy credentials and assigned locations are shown only to your account.',
            textAlign: TextAlign.center,
            style: TextStyle(color: _AddonColors.muted, fontSize: 10),
          ),
        ),
      ],
    ),
  );
}

class _ProxyBenefits extends StatelessWidget {
  const _ProxyBenefits({this.imageLink = 'assets/cards/benefits.png'});

  final String imageLink;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;

    return Container(
      width: double.infinity,
      decoration: BoxDecoration(
        color: colors.surface,
        borderRadius: BorderRadius.circular(0),
        boxShadow: [
          BoxShadow(
            color: colors.shadow.withOpacity(0.06),
            blurRadius: 12,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      clipBehavior: Clip.antiAlias,
      child: Image.asset(
        imageLink,
        width: double.infinity,
        fit: BoxFit.contain,
        alignment: Alignment.center,
      ),
    );
  }
}
