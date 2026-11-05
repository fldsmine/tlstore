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
    final colors = theme.colorScheme;
    final active = _activeMarketplace;
    final activePlan = _map(active?['plan']);

    return Scaffold(
      backgroundColor: colors.surface,
      appBar: AppBar(
        title: const Text('Subscriptions'),
        centerTitle: false,
        elevation: 0,
        actions: [
          IconButton(
            tooltip: 'Refresh subscriptions',
            onPressed: _loading ? null : _load,
            icon: const Icon(Icons.refresh_rounded),
          ),
          const SizedBox(width: 6),
        ],
      ),
      body: _loading && _plans.isEmpty
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(
                  child: Padding(
                    padding: const EdgeInsets.all(28),
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Icon(Icons.cloud_off_rounded,
                            size: 46, color: colors.error),
                        const SizedBox(height: 14),
                        Text(_error!, textAlign: TextAlign.center),
                        const SizedBox(height: 18),
                        FilledButton.icon(
                          onPressed: _load,
                          icon: const Icon(Icons.refresh_rounded),
                          label: const Text('Try again'),
                        ),
                      ],
                    ),
                  ),
                )
              : RefreshIndicator(
                  onRefresh: _load,
                  child: ListView(
                    physics: const AlwaysScrollableScrollPhysics(),
                    padding: const EdgeInsets.fromLTRB(18, 8, 18, 32),
                    children: [
                      _SubscriptionHero(
                        active: active != null,
                        planName: activePlan['name']?.toString() ?? 'No active plan',
                        endsAt: active?['ends_at'],
                        onTap: active == null ? null : () => _showActiveDetails(active),
                      ),
                      const SizedBox(height: 22),
                      if (active != null) ...[
                        Row(
                          children: [
                            Expanded(
                              child: _MetricTile(
                                icon: Icons.rate_review_outlined,
                                label: 'Review tasks',
                                value: activePlan['max_review_tasks']?.toString() ?? '—',
                              ),
                            ),
                            const SizedBox(width: 10),
                            Expanded(
                              child: _MetricTile(
                                icon: Icons.campaign_outlined,
                                label: 'Ad shares',
                                value: activePlan['max_ad_shares']?.toString() ?? '—',
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 22),
                      ],
                      Row(
                        crossAxisAlignment: CrossAxisAlignment.end,
                        children: [
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text('CHOOSE YOUR PLAN',
                                    style: theme.textTheme.labelSmall?.copyWith(
                                      color: colors.primary,
                                      fontWeight: FontWeight.w800,
                                      letterSpacing: 1.3,
                                    )),
                                const SizedBox(height: 5),
                                Text('Marketplace packages',
                                    style: theme.textTheme.headlineSmall?.copyWith(
                                      fontWeight: FontWeight.w900,
                                      letterSpacing: -.5,
                                    )),
                              ],
                            ),
                          ),
                          if (_plans.isNotEmpty)
                            Text('${_plans.length} available',
                                style: theme.textTheme.bodySmall?.copyWith(
                                  color: colors.onSurfaceVariant,
                                )),
                        ],
                      ),
                      const SizedBox(height: 7),
                      Text(
                        'Pick the access level that matches how you want to explore, review and share listings.',
                        style: theme.textTheme.bodyMedium?.copyWith(
                          color: colors.onSurfaceVariant,
                          height: 1.4,
                        ),
                      ),
                      const SizedBox(height: 16),
                      if (_plans.isEmpty)
                        Container(
                          padding: const EdgeInsets.all(24),
                          decoration: BoxDecoration(
                            color: colors.surfaceContainerHighest,
                            borderRadius: BorderRadius.circular(20),
                          ),
                          child: const Column(
                            children: [
                              Icon(Icons.inventory_2_outlined, size: 34),
                              SizedBox(height: 10),
                              Text('No marketplace packages are available right now.'),
                            ],
                          ),
                        )
                      else
                        ..._plans.map((plan) => Padding(
                              padding: const EdgeInsets.only(bottom: 14),
                              child: _PlanCard(
                                plan: plan,
                                active: _isActivePlan(plan),
                                purchasing: _purchasing == _asInt(plan['id']),
                                onTap: () => _openPurchase(plan),
                              ),
                            )),
                      if (_activeAddons.isNotEmpty) ...[
                        const SizedBox(height: 12),
                        Container(
                          decoration: BoxDecoration(
                            color: colors.surfaceContainerLow,
                            borderRadius: BorderRadius.circular(20),
                            border: Border.all(color: colors.outlineVariant),
                          ),
                          child: Theme(
                            data: theme.copyWith(dividerColor: Colors.transparent),
                            child: ExpansionTile(
                              leading: Container(
                                width: 42,
                                height: 42,
                                decoration: BoxDecoration(
                                  color: colors.primaryContainer,
                                  borderRadius: BorderRadius.circular(13),
                                ),
                                child: Icon(Icons.public_rounded,
                                    color: colors.onPrimaryContainer),
                              ),
                              title: Text('Location add-ons (${_activeAddons.length})',
                                  style: const TextStyle(fontWeight: FontWeight.w800)),
                              subtitle: const Text('Your extra market coverage'),
                              childrenPadding: const EdgeInsets.fromLTRB(16, 0, 16, 12),
                              children: _activeAddons.map((addon) {
                                final plan = _map(addon['plan']);
                                final coverage = _coverageSummary(plan);
                                return ListTile(
                                  contentPadding: EdgeInsets.zero,
                                  leading: const Icon(Icons.check_circle_rounded),
                                  title: Text(plan['name']?.toString() ?? 'Location add-on'),
                                  subtitle: Text(
                                    '${coverage.isEmpty ? 'Coverage not configured' : coverage}\nActive until ${_formatDate(addon['ends_at'])}',
                                  ),
                                  isThreeLine: true,
                                );
                              }).toList(),
                            ),
                          ),
                        ),
                      ],
                      const SizedBox(height: 20),
                      Container(
                        padding: const EdgeInsets.all(16),
                        decoration: BoxDecoration(
                          color: colors.surfaceContainerLow,
                          borderRadius: BorderRadius.circular(18),
                          border: Border.all(color: colors.outlineVariant),
                        ),
                        child: Row(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Icon(Icons.lock_outline_rounded, color: colors.primary),
                            const SizedBox(width: 12),
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  const Text('Secure subscription payments',
                                      style: TextStyle(fontWeight: FontWeight.w800)),
                                  const SizedBox(height: 4),
                                  Text(
                                    'Choose a supported payment currency and gateway at checkout. You can also pay with your funded wallet.',
                                    style: theme.textTheme.bodySmall?.copyWith(
                                      color: colors.onSurfaceVariant,
                                      height: 1.4,
                                    ),
                                  ),
                                ],
                              ),
                            ),
                          ],
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
    final digits = _asInt(plan['currency_minor_units'], fallback: 2).clamp(0, 4);
    final original = _asInt(plan['price_minor']);
    final discount = _asDouble(plan['discount']).clamp(0, 100);
    final payable = (original * (100 - discount) / 100).round();
    final bonus = _asDouble(plan['bonus']);
    final special = _asInt(plan['is_special']) == 1;
    final features = _planFeatures(plan);
    final name = plan['name']?.toString() ?? 'Plan';

    return Container(
      decoration: BoxDecoration(
        color: colors.surface,
        borderRadius: BorderRadius.circular(22),
        border: Border.all(
          color: active ? colors.primary : special ? colors.tertiary : colors.outlineVariant,
          width: active || special ? 1.6 : 1,
        ),
        boxShadow: [
          BoxShadow(
            color: colors.shadow.withOpacity(.045),
            blurRadius: 18,
            offset: const Offset(0, 7),
          ),
        ],
      ),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(21),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            if (active || special)
              Container(
                width: double.infinity,
                padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 8),
                color: active ? colors.primary : colors.tertiaryContainer,
                child: Row(
                  children: [
                    Icon(
                      active ? Icons.verified_rounded : Icons.auto_awesome_rounded,
                      size: 15,
                      color: active ? colors.onPrimary : colors.onTertiaryContainer,
                    ),
                    const SizedBox(width: 7),
                    Text(
                      active ? 'CURRENT PACKAGE' : 'RECOMMENDED',
                      style: theme.textTheme.labelSmall?.copyWith(
                        color: active ? colors.onPrimary : colors.onTertiaryContainer,
                        fontWeight: FontWeight.w900,
                        letterSpacing: 1.1,
                      ),
                    ),
                  ],
                ),
              ),
            Padding(
              padding: const EdgeInsets.fromLTRB(19, 18, 19, 18),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(name,
                                style: theme.textTheme.titleLarge?.copyWith(
                                  fontWeight: FontWeight.w900,
                                  letterSpacing: -.3,
                                )),
                            const SizedBox(height: 4),
                            Text(
                              '${plan['duration_count'] ?? 1} ${plan['billing_interval'] ?? 'monthly'} access',
                              style: theme.textTheme.bodySmall?.copyWith(
                                color: colors.onSurfaceVariant,
                              ),
                            ),
                          ],
                        ),
                      ),
                      if (discount > 0)
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                          decoration: BoxDecoration(
                            color: colors.secondaryContainer,
                            borderRadius: BorderRadius.circular(30),
                          ),
                          child: Text('${discount.toStringAsFixed(0)}% OFF',
                              style: theme.textTheme.labelSmall?.copyWith(
                                color: colors.onSecondaryContainer,
                                fontWeight: FontWeight.w900,
                              )),
                        ),
                    ],
                  ),
                  const SizedBox(height: 15),
                  Row(
                    crossAxisAlignment: CrossAxisAlignment.end,
                    children: [
                      Text(_money(payable, currency, digits),
                          style: theme.textTheme.headlineMedium?.copyWith(
                            color: colors.primary,
                            fontWeight: FontWeight.w900,
                            letterSpacing: -1,
                          )),
                      const SizedBox(width: 7),
                      Padding(
                        padding: const EdgeInsets.only(bottom: 5),
                        child: Text('/ ${plan['billing_interval'] ?? 'month'}',
                            style: theme.textTheme.bodySmall?.copyWith(
                              color: colors.onSurfaceVariant,
                            )),
                      ),
                    ],
                  ),
                  if (discount > 0)
                    Padding(
                      padding: const EdgeInsets.only(top: 2),
                      child: Text('Regular price ${_money(original, currency, digits)}',
                          style: theme.textTheme.bodySmall?.copyWith(
                            color: colors.onSurfaceVariant,
                            decoration: TextDecoration.lineThrough,
                          )),
                    ),
                  if (bonus > 0 && plan['bonus_active'] != false) ...[
                    const SizedBox(height: 10),
                    Row(
                      children: [
                        Icon(Icons.card_giftcard_rounded, size: 17, color: colors.tertiary),
                        const SizedBox(width: 7),
                        Expanded(
                          child: Text(
                            'Includes ${_money(_asInt((bonus * math.pow(10, digits)).round()), currency, digits)} welcome bonus',
                            style: theme.textTheme.bodySmall?.copyWith(
                              color: colors.onSurfaceVariant,
                              fontWeight: FontWeight.w600,
                            ),
                          ),
                        ),
                      ],
                    ),
                  ],
                  const SizedBox(height: 16),
                  Divider(color: colors.outlineVariant),
                  const SizedBox(height: 8),
                  Text('WHAT’S INCLUDED',
                      style: theme.textTheme.labelSmall?.copyWith(
                        color: colors.onSurfaceVariant,
                        fontWeight: FontWeight.w900,
                        letterSpacing: 1,
                      )),
                  const SizedBox(height: 10),
                  if (features.isNotEmpty)
                    ...features.take(6).map((feature) => _PlanFeature(text: feature))
                  else ...[
                    if (plan['max_review_tasks'] != null)
                      _PlanFeature(text: 'Up to ${plan['max_review_tasks']} app reviews'),
                    if (plan['max_ad_shares'] != null)
                      _PlanFeature(text: 'Up to ${plan['max_ad_shares']} ad shares'),
                    if (plan['global_listing_access'] == true)
                      const _PlanFeature(text: 'Access to global listings'),
                  ],
                  const SizedBox(height: 18),
                  SizedBox(
                    width: double.infinity,
                    height: 50,
                    child: FilledButton(
                      onPressed: purchasing ? null : onTap,
                      style: FilledButton.styleFrom(
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(14),
                        ),
                      ),
                      child: purchasing
                          ? const SizedBox(
                              width: 19,
                              height: 19,
                              child: CircularProgressIndicator(strokeWidth: 2),
                            )
                          : Row(
                              mainAxisAlignment: MainAxisAlignment.center,
                              children: [
                                Text(active ? 'View current package' : 'Continue with $name'),
                                const SizedBox(width: 8),
                                const Icon(Icons.arrow_forward_rounded, size: 18),
                              ],
                            ),
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _PlanFeature extends StatelessWidget {
  const _PlanFeature({required this.text});
  final String text;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    return Padding(
      padding: const EdgeInsets.only(bottom: 9),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(Icons.check_circle_rounded, size: 18, color: colors.primary),
          const SizedBox(width: 9),
          Expanded(child: Text(text, style: Theme.of(context).textTheme.bodyMedium)),
        ],
      ),
    );
  }
}

class _SubscriptionHero extends StatelessWidget {
  const _SubscriptionHero({
    required this.active,
    required this.planName,
    required this.endsAt,
    required this.onTap,
  });

  final bool active;
  final String planName;
  final Object? endsAt;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    final foreground = colors.onPrimary;
    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(24),
        child: Ink(
          padding: const EdgeInsets.all(22),
          decoration: BoxDecoration(
            gradient: LinearGradient(
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
              colors: [colors.primary, Color.lerp(colors.primary, colors.tertiary, .55)!],
            ),
            borderRadius: BorderRadius.circular(24),
          ),
          child: Stack(
            children: [
              Positioned(
                right: -12,
                top: -18,
                child: Icon(Icons.workspace_premium_rounded,
                    size: 125, color: foreground.withOpacity(.10)),
              ),
              Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Icon(Icons.auto_awesome_rounded, color: foreground, size: 17),
                      const SizedBox(width: 7),
                      Text('YOUR MARKETPLACE ACCESS',
                          style: Theme.of(context).textTheme.labelSmall?.copyWith(
                            color: foreground.withOpacity(.9),
                            fontWeight: FontWeight.w900,
                            letterSpacing: 1.2,
                          )),
                    ],
                  ),
                  const SizedBox(height: 17),
                  Text(active ? planName : 'Unlock more opportunities',
                      style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                        color: foreground,
                        fontWeight: FontWeight.w900,
                        letterSpacing: -.5,
                      )),
                  const SizedBox(height: 7),
                  Text(
                    active
                        ? 'Your package is active until ${_formatDate(endsAt)}.'
                        : 'Choose a package to review apps and share ads across the marketplace.',
                    style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                      color: foreground.withOpacity(.9),
                      height: 1.4,
                    ),
                  ),
                  const SizedBox(height: 17),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                    decoration: BoxDecoration(
                      color: foreground.withOpacity(.15),
                      borderRadius: BorderRadius.circular(30),
                      border: Border.all(color: foreground.withOpacity(.22)),
                    ),
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Icon(active ? Icons.verified_rounded : Icons.explore_rounded,
                            size: 16, color: foreground),
                        const SizedBox(width: 7),
                        Text(active ? 'Subscription active' : 'Explore packages',
                            style: TextStyle(color: foreground, fontWeight: FontWeight.w700)),
                        if (active) ...[
                          const SizedBox(width: 7),
                          Icon(Icons.arrow_forward_rounded, size: 15, color: foreground),
                        ],
                      ],
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _MetricTile extends StatelessWidget {
  const _MetricTile({required this.icon, required this.label, required this.value});
  final IconData icon;
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: colors.surfaceContainerLow,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: colors.outlineVariant),
      ),
      child: Row(
        children: [
          Container(
            width: 38,
            height: 38,
            decoration: BoxDecoration(
              color: colors.primaryContainer,
              borderRadius: BorderRadius.circular(12),
            ),
            child: Icon(icon, size: 19, color: colors.onPrimaryContainer),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(value, style: Theme.of(context).textTheme.titleLarge?.copyWith(
                  fontWeight: FontWeight.w900,
                )),
                Text(label, maxLines: 1, overflow: TextOverflow.ellipsis,
                    style: Theme.of(context).textTheme.labelSmall?.copyWith(
                      color: colors.onSurfaceVariant,
                    )),
              ],
            ),
          ),
        ],
      ),
    );
  }
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
