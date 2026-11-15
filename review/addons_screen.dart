import 'package:flutter/material.dart';

import '../core/currency/money.dart';
import '../widgets/proxy_connection_dialog.dart';
import '../features/subscriptions/presentation/widgets/package_addon_purchase_sheet.dart';
import '../services/auth_api.dart';
import '../services/media_url.dart';
import '../widgets/auth_components.dart';
import 'settings_screen.dart';
import 'subscriptions_screen.dart';
import 'topup_screen.dart';

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

  bool _packageAddonsLoading = true;
  String? _packageAddonsError;
  List<Map<String, dynamic>> _packageAddons = const [];

  Map<String, dynamic> get _user => widget.user;

  @override
  void initState() {
    super.initState();
    _refreshPage();
    _loadPackageAddons();
  }

  Future<void> _openSettings() async {
    await Navigator.of(context).push<void>(
      MaterialPageRoute<void>(
        builder: (_) => SettingsScreen(
          api: widget.api,
          token: widget.token,
          user: _user,
          onLogout: widget.onLogout,
          onDeleted: widget.onDeleted,
        ),
      ),
    );
  }

  Future<void> _refreshPage() async {
    try {
      final response = await widget.api.get('me', widget.token);
      final data = response['data'];
      if (data is Map<String, dynamic> && mounted) {
        setState(() {
          _user.addAll(data);
        });
      }
    } on AuthException {
      // Keep the session profile visible when the device is offline.
    }
  }

  Future<void> _loadPackageAddons() async {
    if (mounted) {
      setState(() {
        _packageAddonsLoading = true;
        _packageAddonsError = null;
      });
    }
    try {
      await widget.api.loadExchangeRates(widget.token);
      final response = await widget.api.get('package-addons', widget.token);
      final payload = response['data'];
      final rows = payload is List
          ? payload
          : payload is Map && payload['data'] is List
              ? payload['data'] as List
              : const [];
      if (!mounted) return;
      setState(() {
        _packageAddons = rows
            .whereType<Map>()
            .map((row) => Map<String, dynamic>.from(row))
            .toList(growable: false);
        _packageAddonsLoading = false;
      });
    } on AuthException catch (error) {
      if (!mounted) return;
      setState(() {
        _packageAddonsError = error.message;
        _packageAddonsLoading = false;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _packageAddonsError = 'Unable to load package add-ons.';
        _packageAddonsLoading = false;
      });
    }
  }

  Future<void> _openPackageAddon(Map<String, dynamic> addonState) async {
    final nestedActive = addonState['active_entitlement'];
    final active = nestedActive is Map
        ? nestedActive
        : const ['active', 'trialing'].contains(
            addonState['status']?.toString().toLowerCase(),
          )
            ? addonState
            : null;
    if (active is Map) {
      final entitlement = Map<String, dynamic>.from(active);
      final addon = addonState['addon'] is Map
          ? Map<String, dynamic>.from(addonState['addon'] as Map)
          : <String, dynamic>{};
      final theme = Theme.of(context);
      await showModalBottomSheet<void>(
        context: context,
        showDragHandle: true,
        builder: (sheetContext) => SafeArea(
          child: Padding(
            padding: const EdgeInsets.fromLTRB(22, 8, 22, 26),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  '${addon['name'] ?? 'Review'} add-on is active',
                  style: theme.textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w800),
                ),
                const SizedBox(height: 10),
                Text('Valid until ${_formatDate(entitlement['ends_at']?.toString())}'),
                const SizedBox(height: 6),
                Text(
                  'Paid ${moneyConverted(widget.api, entitlement['price_minor'], entitlement['currency']?.toString() ?? 'USD', null, _user['preferred_currency']?.toString() ?? 'USD', isMinor: true)}',
                  style: theme.textTheme.bodyMedium?.copyWith(color: theme.colorScheme.onSurfaceVariant),
                ),
              ],
            ),
          ),
        ),
      );
      return;
    }

    final addon = addonState['addon'] is Map
        ? Map<String, dynamic>.from(addonState['addon'] as Map)
        : <String, dynamic>{};
    if (addon['is_active'] == false) {
      showAuthMessage(context, 'This package add-on is temporarily unavailable.');
      return;
    }

    final result = await showModalBottomSheet<String>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      showDragHandle: true,
      builder: (_) => PackageAddonPurchaseSheet(
        api: widget.api,
        token: widget.token,
        addonState: addonState,
      ),
    );
    if (!mounted) return;
    if (result == 'topup') {
      await Navigator.of(context).push<void>(
        MaterialPageRoute<void>(
          builder: (_) => TopUpScreen(api: widget.api, token: widget.token),
        ),
      );
      if (mounted) await _loadPackageAddons();
    } else if (result == 'paid') {
      await _loadPackageAddons();
      if (mounted) showAuthMessage(context, 'Your package add-on is active.');
    }
  }

  Future<void> _openProxyConnection(Map<String, dynamic> addonState) async {
    await Navigator.of(context).push<void>(
      MaterialPageRoute<void>(
        builder: (_) => ProxyConnectionDialog(
          addonState: addonState,
          user: Map<String, dynamic>.from(_user),
        ),
      ),
    );
  }

  Future<void> _openSubscriptions() async {
    await Navigator.of(context).push<void>(
      MaterialPageRoute<void>(
        builder: (_) => SubscriptionsScreen(
          api: widget.api,
          token: widget.token,
          user: _user,
        ),
      ),
    );
    if (mounted) {
      await _refreshPage();
      await _loadPackageAddons();
    }
  }


  @override
  void dispose() {
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final user = _user;
    final name = user['full_name']?.toString().trim() ?? '';
    final email = user['email']?.toString() ?? '';
    final avatar = MediaUrl.resolve(user['avatar']?.toString());
    final cover = MediaUrl.resolve(user['cover_image']?.toString());
    final countryName = user['country_name']?.toString();
    final countryCode = user['country_code']?.toString() ?? '';
    final country = (countryName == null || countryName.isEmpty)
        ? (countryCode.isEmpty ? 'Not set' : countryCode)
        : '$countryName ($countryCode)';
    final theme = Theme.of(context);
    final colors = theme.colorScheme;

    final subscriptionContext = user['subscription_context'] is Map
        ? Map<String, dynamic>.from(user['subscription_context'] as Map)
        : <String, dynamic>{};
    final activeSubscriptions =
        (subscriptionContext['active_marketplaces'] as List? ?? const [])
            .whereType<Map>()
            .map((item) => Map<String, dynamic>.from(item))
            .toList(growable: false);
    final catalog = subscriptionContext['catalog'] is Map
        ? Map<String, dynamic>.from(subscriptionContext['catalog'] as Map)
        : <String, dynamic>{};
    final plansById = <String, Map<String, dynamic>>{};
    for (final group in (catalog['subscription_groups'] as List? ?? const [])
        .whereType<Map>()) {
      for (final plan in (group['plans'] as List? ?? const []).whereType<Map>()) {
        final id = plan['id']?.toString();
        if (id != null) plansById[id] = Map<String, dynamic>.from(plan);
      }
    }
    final hasAddonSubscription = activeSubscriptions.any((subscription) {
      final plan = plansById[
        subscription['subscription_plan_id']?.toString()
      ];
      return plan?['addons'] == true || plan?['addons'] == 1;
    });

    return Theme(
      data: theme.copyWith(
        scaffoldBackgroundColor: theme.scaffoldBackgroundColor,
        cardTheme: CardThemeData(
          color: theme.cardTheme.color,
          elevation: 0,
          margin: EdgeInsets.zero,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(19),
            side: BorderSide(color: colors.outlineVariant),
          ),
        ),
        inputDecorationTheme: InputDecorationTheme(
          filled: true,
          fillColor: theme.inputDecorationTheme.fillColor,
          contentPadding: const EdgeInsets.symmetric(
            horizontal: 14,
            vertical: 15,
          ),
          border: OutlineInputBorder(
            borderRadius: BorderRadius.circular(13),
            borderSide: BorderSide(color: colors.outlineVariant),
          ),
          enabledBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(13),
            borderSide: BorderSide(color: colors.outlineVariant),
          ),
          focusedBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(13),
            borderSide: BorderSide(color: colors.primary, width: 1.4),
          ),
        ),
      ),
      child: Scaffold(
        appBar: AppBar(
          title: Text( 'Addons Dashboard',
            style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 20),
          ),
          backgroundColor: theme.scaffoldBackgroundColor,
          surfaceTintColor: Colors.transparent,
          elevation: 0,
          actions: [
              IconButton(
                tooltip: 'Settings',
                onPressed: _openSettings,
                icon: const Icon(Icons.settings_outlined),
              ),
          ],
        ),
        body: RefreshIndicator(
          onRefresh: _refreshPage,
          color: colors.primary,
          child: Center(
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 760),
              child: ListView(
                physics: const AlwaysScrollableScrollPhysics(),
            padding: const EdgeInsets.fromLTRB(16, 5, 16, 30),
            children: [

                _SectionCard(
                  title: 'My subscriptions',
                  subtitle: activeSubscriptions.isEmpty
                      ? 'Your active packages will appear here.'
                      : 'Your currently active earning packages and their terms.',
                  child: activeSubscriptions.isEmpty
                      ? _NoActiveSubscription(onBrowse: _openSubscriptions)
                      : Column(
                          children: [
                            for (var index = 0;
                                index < activeSubscriptions.length;
                                index++) ...[
                              _ActiveSubscriptionCard(
                                subscription: activeSubscriptions[index],
                                plan: plansById[
                                  activeSubscriptions[index]
                                          ['subscription_plan_id']
                                      ?.toString()
                                ],
                                preferredCurrency:
                                    user['preferred_currency']?.toString() ??
                                    'USD',
                                api: widget.api,
                                formatDate: _formatDate,
                              ),
                              if (index < activeSubscriptions.length - 1)
                                const SizedBox(height: 10),
                            ],
                            const SizedBox(height: 12),
                            Align(
                              alignment: Alignment.centerLeft,
                              child: TextButton.icon(
                                onPressed: _openSubscriptions,
                                icon: const Icon(Icons.manage_accounts_outlined),
                                label: const Text('Manage packages'),
                              ),
                            ),
                          ],
                        ),
                ),
                const SizedBox(height: 12),
                if (_packageAddons.isNotEmpty ||
                    (hasAddonSubscription &&
                        (_packageAddonsLoading || _packageAddonsError != null))) ...[
                  _SectionCard(
                    title: 'Review package add-ons',
                    subtitle: 'Pay for or check the add-on coverage linked to your active review packages.',
                    child: _packageAddonsLoading
                        ? const Padding(
                            padding: EdgeInsets.symmetric(vertical: 10),
                            child: Center(child: CircularProgressIndicator()),
                          )
                        : _packageAddonsError != null
                        ? Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                _packageAddonsError!,
                                style: TextStyle(color: colors.onSurfaceVariant),
                              ),
                              TextButton.icon(
                                onPressed: _loadPackageAddons,
                                icon: const Icon(Icons.refresh_rounded),
                                label: const Text('Retry'),
                              ),
                            ],
                          )
                        : Column(
                            children: [
                              for (var index = 0; index < _packageAddons.length; index++) ...[
                                _PackageAddonProfileRow(
                                  addonState: _packageAddons[index],
                                  preferredCurrency: user['preferred_currency']?.toString() ?? 'USD',
                                  api: widget.api,
                                  formatDate: _formatDate,
                                  onTap: () => _openPackageAddon(_packageAddons[index]),
                                  onViewConnection: () =>
                                      _openProxyConnection(_packageAddons[index]),
                                ),
                                if (index < _packageAddons.length - 1)
                                  const SizedBox(height: 9),
                              ],
                            ],
                          ),
                  ),
                  const SizedBox(height: 12),
                ],

                const SizedBox(height: 13),
                const _ProxyBenefits(),
              ],
              ),
            ),
          ),
        ),
      ),
    );
  }

  String _formatDate(String? value) {
    final date = DateTime.tryParse(value ?? '')?.toLocal();
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

}

class _SectionCard extends StatelessWidget {
  const _SectionCard({required this.title, required this.child, this.subtitle});
  final String title;
  final String? subtitle;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: colors.surfaceContainerLow,
        borderRadius: BorderRadius.circular(19),
        border: Border.all(color: colors.outlineVariant),
        boxShadow: [
          BoxShadow(
            color: colors.shadow.withValues(alpha: .02),
            blurRadius: 12,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            title,
            style: TextStyle(
              color: colors.onSurface,
              fontSize: 16,
              fontWeight: FontWeight.w900,
            ),
          ),
          if (subtitle != null) ...[
            const SizedBox(height: 4),
            Text(
              subtitle!,
              style: TextStyle(
                color: colors.onSurfaceVariant,
                fontSize: 12,
                height: 1.4,
              ),
            ),
          ],
          const SizedBox(height: 13),
          child,
        ],
      ),
    );
  }
}

class _NoActiveSubscription extends StatelessWidget {
  const _NoActiveSubscription({required this.onBrowse});

  final VoidCallback onBrowse;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(15),
      decoration: BoxDecoration(
        color: colors.surfaceContainerHighest,
        borderRadius: BorderRadius.circular(15),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(Icons.layers_outlined, color: colors.primary),
              const SizedBox(width: 9),
              Expanded(
                child: Text(
                  'No active package',
                  style: TextStyle(
                    color: colors.onSurface,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 5),
          Text(
            'Join a package to unlock its app review or ad sharing tasks.',
            style: TextStyle(color: colors.onSurfaceVariant, height: 1.4),
          ),
          const SizedBox(height: 10),
          TextButton.icon(
            onPressed: onBrowse,
            icon: const Icon(Icons.arrow_forward_rounded, size: 18),
            label: const Text('Browse packages'),
          ),
        ],
      ),
    );
  }
}

class _PackageAddonProfileRow extends StatelessWidget {
  const _PackageAddonProfileRow({
    required this.addonState,
    required this.preferredCurrency,
    required this.api,
    required this.formatDate,
    required this.onTap,
    required this.onViewConnection,
  });

  final Map<String, dynamic> addonState;
  final String preferredCurrency;
  final AuthApi api;
  final String Function(String?) formatDate;
  final VoidCallback onTap;
  final VoidCallback onViewConnection;

  Map<String, dynamic> _map(Object? value) => value is Map
      ? value.map<String, dynamic>(
          (key, value) => MapEntry(key.toString(), value),
        )
      : <String, dynamic>{};

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    final addon = _map(addonState['addon']);
    final plan = _map(addonState['plan']);
    final nestedActive = _map(addonState['active_entitlement']);
    final isDirectActive = const ['active', 'trialing']
        .contains(addonState['status']?.toString().toLowerCase());
    final active = nestedActive.isNotEmpty
        ? nestedActive
        : isDirectActive
            ? addonState
            : const <String, dynamic>{};
    final latest = _map(addonState['latest_entitlement']);
    final isActive = active.isNotEmpty;
    final available = addon['is_active'] != false && addon['is_active'] != 0;
    final features = <String, dynamic>{
      ..._map(addon['features']),
      ..._map(active['features']),
      ..._map(addonState['features']),
    };
    final hasConnection = features.containsKey('location') ||
        features.containsKey('locations') ||
        features.containsKey('ip_addresses') ||
        features['encrypted_tunnel'] == true ||
        features['encrypted_tunnel'] == 1;
    final amount = addonState['payable_price_minor'];
    final currency = addon['currency']?.toString() ?? 'USD';
    final price = amount == null
        ? null
        : moneyConverted(
            api,
            amount,
            currency,
            addonState['currency_minor_units'],
            preferredCurrency,
            isMinor: true,
          );
    final pending = latest['status']?.toString() == 'pending';
    final secondary = isActive
        ? 'Active until ${formatDate(active['ends_at']?.toString())}'
        : '${plan['name'] ?? 'Review package'} · ${pending ? 'Payment pending' : (price == null ? 'Add-on' : '$price / month')}';
    final action = isActive
        ? 'View details'
        : !available
        ? 'Unavailable'
        : pending
        ? 'Resume payment'
        : 'Pay add-on';

    return Material(
      color: colors.surfaceContainerLow,
      borderRadius: BorderRadius.circular(15),
      child: InkWell(
        onTap: available || isActive ? onTap : null,
        borderRadius: BorderRadius.circular(15),
        child: Container(
          padding: const EdgeInsets.all(13),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(15),
            border: Border.all(color: colors.outlineVariant),
          ),
          child: Row(
            children: [
              Container(
                width: 42,
                height: 42,
                decoration: BoxDecoration(
                  color: isActive
                      ? colors.primaryContainer
                      : colors.secondaryContainer,
                  borderRadius: BorderRadius.circular(13),
                ),
                child: Icon(
                  isActive
                      ? Icons.verified_rounded
                      : Icons.workspace_premium_outlined,
                  color: isActive
                      ? colors.onPrimaryContainer
                      : colors.onSecondaryContainer,
                ),
              ),
              const SizedBox(width: 11),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      '${addon['name'] ?? 'Review'} add-on',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(
                        color: colors.onSurface,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const SizedBox(height: 3),
                    Text(
                      secondary,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(
                        color: colors.onSurfaceVariant,
                        fontSize: 12,
                      ),
                    ),
                    const SizedBox(height: 5),
                    Text(
                      action,
                      style: TextStyle(
                        color: available || isActive
                            ? colors.primary
                            : colors.onSurfaceVariant,
                        fontSize: 12,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    if (isActive && hasConnection) ...[
                      const SizedBox(height: 5),
                      Align(
                        alignment: Alignment.centerLeft,
                        child: TextButton.icon(
                          onPressed: onViewConnection,
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
                    ],
                  ],
                ),
              ),
              const SizedBox(width: 6),
              Icon(Icons.chevron_right_rounded, color: colors.onSurfaceVariant),
            ],
          ),
        ),
      ),
    );
  }
}

class _ActiveSubscriptionCard extends StatelessWidget {
  const _ActiveSubscriptionCard({
    required this.subscription,
    required this.plan,
    required this.preferredCurrency,
    required this.api,
    required this.formatDate,
  });

  final Map<String, dynamic> subscription;
  final Map<String, dynamic>? plan;
  final String preferredCurrency;
  final AuthApi api;
  final String Function(String?) formatDate;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    final source = plan ?? const <String, dynamic>{};
    final groupKey = subscription['group_key']?.toString() ??
        source['group_key']?.toString() ??
        '';
    final planName = source['name']?.toString() ??
        subscription['plan_name']?.toString() ??
        'Marketplace package';
    final sourceCurrency = source['currency']?.toString() ?? 'USD';
    final price = source['price_minor'];
    final billingInterval = source['billing_interval']?.toString();
    final durationCount = (source['duration_count'] as num?)?.toInt() ?? 1;
    final withdrawalDays = (source['withdrawal_days'] as List? ?? const [])
        .map((day) => day.toString())
        .where((day) => day.isNotEmpty)
        .toList();
    final groupLabel = switch (groupKey) {
      'group_a' => 'Ad sharing',
      'group_b' => 'Store app reviews',
      'group_c' => 'Apple app reviews',
      _ => 'Marketplace access',
    };
    final intervalUnit = switch (billingInterval?.toLowerCase()) {
      'monthly' => 'month',
      'weekly' => 'week',
      'quarterly' => 'quarter',
      'yearly' || 'annual' => 'year',
      _ => null,
    };
    final durationLabel = intervalUnit == null
        ? null
        : '$durationCount $intervalUnit${durationCount == 1 ? '' : 's'}';
    final endsAt = formatDate(subscription['ends_at']?.toString());
    final startsAt = formatDate(subscription['starts_at']?.toString());

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: colors.surface,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: colors.outlineVariant),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 42,
                height: 42,
                decoration: BoxDecoration(
                  color: colors.primaryContainer,
                  borderRadius: BorderRadius.circular(13),
                ),
                child: Icon(
                  groupKey == 'group_a'
                      ? Icons.campaign_outlined
                      : Icons.rate_review_outlined,
                  color: colors.onPrimaryContainer,
                ),
              ),
              const SizedBox(width: 11),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      planName,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(
                        color: colors.onSurface,
                        fontSize: 15,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 3),
                    Text(
                      groupLabel,
                      style: TextStyle(
                        color: colors.onSurfaceVariant,
                        fontSize: 11,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ],
                ),
              ),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 5),
                decoration: BoxDecoration(
                  color: colors.primaryContainer,
                  borderRadius: BorderRadius.circular(30),
                ),
                child: Text(
                  'ACTIVE',
                  style: TextStyle(
                    color: colors.onPrimaryContainer,
                    fontSize: 9,
                    letterSpacing: .5,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
            ],
          ),
          if (price != null) ...[
            const SizedBox(height: 13),
            Row(
              crossAxisAlignment: CrossAxisAlignment.end,
              children: [
                Expanded(
                  child: Text(
                    moneyConverted(
                      api,
                      price,
                      sourceCurrency,
                      source['currency_minor_units'],
                      preferredCurrency,
                      isMinor: true,
                    ),
                    style: TextStyle(
                      color: colors.primary,
                      fontSize: 20,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                ),
                if (durationLabel != null)
                  Text(
                    'Term · $durationLabel',
                    style: TextStyle(
                      color: colors.onSurfaceVariant,
                      fontSize: 10,
                    ),
                  ),
              ],
            ),
          ],
          const SizedBox(height: 12),
          Align(
            alignment: Alignment.centerLeft,
            child: TextButton.icon(
              onPressed: () => _showSubscriptionFeatures(
                context,
                planName,
                [
                  if (startsAt.isNotEmpty)
                    const MapEntry(Icons.play_circle_outline_rounded, 'Started'),
                  if (endsAt.isNotEmpty)
                    const MapEntry(Icons.event_available_outlined, 'Valid until'),
                  if (source['max_review_tasks'] is num &&
                      (source['max_review_tasks'] as num).toInt() > 0)
                    MapEntry(Icons.rate_review_outlined, '${source['max_review_tasks']} reviews'),
                  if (source['max_ad_shares'] is num &&
                      (source['max_ad_shares'] as num).toInt() > 0)
                    MapEntry(Icons.campaign_outlined, '${source['max_ad_shares']} ad shares'),
                  if (source['daily_task_limit'] is num &&
                      (source['daily_task_limit'] as num).toInt() > 0)
                    MapEntry(Icons.today_outlined, '${source['daily_task_limit']} tasks daily'),
                  if (source['daily_revenue_minor'] is num &&
                      (source['daily_revenue_minor'] as num).toInt() > 0)
                    MapEntry(
                      Icons.trending_up_rounded,
                      'Daily earning limit: ${moneyConverted(api, source['daily_revenue_minor'], sourceCurrency, source['currency_minor_units'], preferredCurrency, isMinor: true)}',
                    ),
                  if (source['minimum_withdrawal_minor'] is num &&
                      (source['minimum_withdrawal_minor'] as num).toInt() > 0)
                    MapEntry(
                      Icons.account_balance_wallet_outlined,
                      'Minimum withdrawal: ${moneyConverted(api, source['minimum_withdrawal_minor'], sourceCurrency, source['currency_minor_units'], preferredCurrency, isMinor: true)}',
                    ),
                  if (withdrawalDays.isNotEmpty)
                    MapEntry(Icons.calendar_month_outlined, 'Withdrawal days: ${withdrawalDays.join(' & ')}'),
                ],
                startedAt: startsAt,
                validUntil: endsAt,
              ),
              icon: const Icon(Icons.visibility_outlined, size: 18),
              label: const Text('View package features'),
            ),
          ),
        ],
      ),
    );
  }
}

void _showSubscriptionFeatures(
  BuildContext context,
  String planName,
  List<MapEntry<IconData, String>> features, {
  required String startedAt,
  required String validUntil,
}) {
  final colors = Theme.of(context).colorScheme;
  final items = <MapEntry<IconData, String>>[
    if (startedAt.isNotEmpty)
      MapEntry(Icons.play_circle_outline_rounded, 'Started: $startedAt'),
    if (validUntil.isNotEmpty)
      MapEntry(Icons.event_available_outlined, 'Valid until: $validUntil'),
    ...features.where((item) => item.key != Icons.play_circle_outline_rounded &&
        item.key != Icons.event_available_outlined),
  ];

  showDialog<void>(
    context: context,
    builder: (dialogContext) => AlertDialog(
      title: Text('$planName features'),
      content: SizedBox(
        width: 420,
        child: items.isEmpty
            ? const Text('No additional feature details are available for this package.')
            : ListView.separated(
                shrinkWrap: true,
                itemCount: items.length,
                separatorBuilder: (_, __) => Divider(
                  height: 1,
                  color: colors.outlineVariant,
                ),
                itemBuilder: (context, index) {
                  final item = items[index];
                  return Padding(
                    padding: const EdgeInsets.symmetric(vertical: 9),
                    child: Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Icon(item.key, size: 19, color: colors.primary),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Text(
                            item.value,
                            style: Theme.of(context).textTheme.bodyMedium,
                          ),
                        ),
                      ],
                    ),
                  );
                },
              ),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.of(dialogContext).pop(),
          child: const Text('Close'),
        ),
      ],
    ),
  );
}


class _ProxyBenefits extends StatelessWidget {
  const _ProxyBenefits();

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;

    return Container(
      width: double.infinity,
      decoration: BoxDecoration(
        color: colors.surface,
        borderRadius: BorderRadius.circular(18),
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
        'assets/cards/benefits.png',
        width: double.infinity,
        fit: BoxFit.contain,
        alignment: Alignment.center,
      ),
    );
  }
}
