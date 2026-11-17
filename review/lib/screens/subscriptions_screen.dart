import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../core/currency/money.dart';
import '../core/utils/helper.dart';
import '../services/auth_api.dart';
import 'questionnaire_screen.dart';
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
  List<Map<String, dynamic>> _groups = const [];
  String? _selectedGroupKey;
  Map<String, dynamic>? _activeMarketplace;
  List<Map<String, dynamic>> _activeMarketplaces = const [];
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
    _groups = _rows(catalog['subscription_groups']);
    _plans = _groups
        .expand((group) {
          final groupKey = group['group_key']?.toString();
          return _rows(group['plans']).map((plan) {
            final item = Map<String, dynamic>.from(plan);
            item['group_key'] = groupKey;
            return item;
          });
        })
        .toList(growable: false);
    _activeMarketplace = _mapOrNull(context['active_marketplace']);
    _activeMarketplaces = _rows(context['active_marketplaces']);
    if (_activeMarketplaces.isEmpty && _activeMarketplace != null) {
      _activeMarketplaces = [_activeMarketplace!];
    }
  }

  Map<String, dynamic>? get _selectedGroup {
    for (final group in _groups) {
      if (group['group_key']?.toString() == _selectedGroupKey) return group;
    }
    return null;
  }

  Map<String, dynamic> _planForSubscription(
    Map<String, dynamic>? subscription,
  ) {
    final planId = _asInt(subscription?['subscription_plan_id']);
    for (final plan in _plans) {
      if (_asInt(plan['id']) == planId) return plan;
    }
    return const {};
  }

  Future<void> _load() async {
    final hasCachedData = _plans.isNotEmpty || _activeMarketplace != null;
    if (!hasCachedData && mounted) setState(() => _loading = true);
    setState(() => _error = null);
    try {
      await widget.api.loadExchangeRates(widget.token);
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

  bool _isActivePlan(Map<String, dynamic> plan) => _activeMarketplaces.any(
    (subscription) =>
        _asInt(subscription['subscription_plan_id']) == _asInt(plan['id']),
  );

  Map<String, dynamic>? _subscriptionForPlan(Map<String, dynamic> plan) {
    for (final subscription in _activeMarketplaces) {
      if (_asInt(subscription['subscription_plan_id']) == _asInt(plan['id'])) {
        return subscription;
      }
    }
    return null;
  }

  bool _questionnaireComplete(String? groupKey) {
    if (groupKey != 'group_b' && groupKey != 'group_c') return true;
    final questionnaire = _map(_user['subscription_questionnaire']);
    final completed = questionnaire['completed_groups'];
    return completed is List &&
        completed.any((value) => value.toString() == groupKey);
  }

  Future<bool> _showQuestionnaire(String groupKey) async {
    // Presented as a dialog over the plans list (parent page blurred) instead
    // of pushing a separate page.
    final result = await showQuestionnaireDialog(
      context,
      api: widget.api,
      token: widget.token,
      groupKey: groupKey,
      user: _user,
    );
    if (!mounted || result != true) return false;
    final refreshed = await widget.api.refreshUser(widget.token);
    if (!mounted) return false;
    setState(() => _applySnapshot(refreshed));
    return _questionnaireComplete(groupKey);
  }

  Future<void> _ensureQuestionnaire(String? groupKey) async {
    if (groupKey != null &&
        (groupKey == 'group_b' || groupKey == 'group_c') &&
        !_questionnaireComplete(groupKey)) {
      await _showQuestionnaire(groupKey);
    }
  }

  Future<void> _openPurchase(Map<String, dynamic> plan) async {
    if (_isActivePlan(plan)) {
      final activeSubscription = _subscriptionForPlan(plan)!;
      final groupKey = plan['group_key']?.toString();
      if (!_questionnaireComplete(groupKey)) {
        await _ensureQuestionnaire(groupKey);
      } else {
        _showActiveDetails(activeSubscription);
      }
      return;
    }
    setState(() => _purchasing = _asInt(plan['id']));
    try {
      final groupKey = plan['group_key']?.toString();
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
        if (_isActivePlan(plan)) await _ensureQuestionnaire(groupKey);
      }
    } finally {
      if (mounted) setState(() => _purchasing = null);
    }
  }

  void _showActiveDetails(Map<String, dynamic> subscription) {
    final plan = _planForSubscription(subscription);
    final ends = _formatDate(subscription['ends_at']);
    final dailyAdEarnings = _dailyAdEarningsLabel(
      plan,
      widget.api,
      widget.api.preferredCurrency,
    );
    final features = [..._planFeatures(plan, widget.api), ?dailyAdEarnings];
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
    final activePlan = _planForSubscription(active);

    return Scaffold(
      backgroundColor: colors.surface,
      appBar: AppBar(
        leading: _selectedGroupKey == null
            ? null
            : IconButton(
                tooltip: 'All package groups',
                onPressed: () => setState(() => _selectedGroupKey = null),
                icon: const Icon(Icons.arrow_back_rounded),
              ),
        title: Text(_selectedGroup?['name']?.toString() ?? 'Subscriptions'),
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
                    Icon(
                      Icons.cloud_off_rounded,
                      size: 46,
                      color: colors.error,
                    ),
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
                    planName:
                        activePlan['name']?.toString() ?? 'No active plan',
                    endsAt: active?['ends_at'],
                    onTap: active == null
                        ? null
                        : () => _showActiveDetails(active),
                  ),
                  const SizedBox(height: 22),
                  if (_activeMarketplaces.isNotEmpty) ...[
                    Text(
                      'Your active packages',
                      style: theme.textTheme.titleMedium?.copyWith(
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const SizedBox(height: 8),
                    ..._activeMarketplaces.map((subscription) {
                      final plan = _planForSubscription(subscription);
                      return Card(
                        margin: const EdgeInsets.only(bottom: 8),
                        clipBehavior: Clip.antiAlias,
                        child: Column(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            ListTile(
                              leading: Icon(
                                Icons.verified_rounded,
                                color: colors.primary,
                              ),
                              title: Text(
                                plan['name']?.toString() ?? 'Active package',
                                style: const TextStyle(
                                  fontWeight: FontWeight.w700,
                                ),
                              ),
                              subtitle: Text(
                                '${plan['group_key']?.toString().replaceAll('_', ' ').toUpperCase() ?? 'PACKAGE'} · until ${_formatDate(subscription['ends_at'])}',
                              ),
                              trailing: const Icon(Icons.chevron_right_rounded),
                              onTap: () => _showActiveDetails(subscription),
                            ),
                          ],
                        ),
                      );
                    }),
                    const SizedBox(height: 14),
                  ],
                  Row(
                    crossAxisAlignment: CrossAxisAlignment.end,
                    children: [
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              _selectedGroupKey == null
                                  ? 'CHOOSE A PACKAGE GROUP'
                                  : 'CHOOSE YOUR PLAN',
                              style: theme.textTheme.labelSmall?.copyWith(
                                color: colors.primary,
                                fontWeight: FontWeight.w800,
                                letterSpacing: 1.3,
                              ),
                            ),
                            const SizedBox(height: 5),
                            Text(
                              _selectedGroupKey == null
                                  ? 'Subscription groups'
                                  : 'Available packages',
                              style: theme.textTheme.headlineSmall?.copyWith(
                                fontWeight: FontWeight.w900,
                                letterSpacing: -.5,
                              ),
                            ),
                          ],
                        ),
                      ),
                      if (_plans.isNotEmpty)
                        Text(
                          '${_plans.length} available',
                          style: theme.textTheme.bodySmall?.copyWith(
                            color: colors.onSurfaceVariant,
                          ),
                        ),
                    ],
                  ),
                  const SizedBox(height: 7),
                  Text(
                    _selectedGroupKey == null
                        ? 'Choose a group to see its subscription packages.'
                        : 'Choose a package. Joining another package switches your current package.',
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
                          Text(
                            'No marketplace packages are available right now.',
                          ),
                        ],
                      ),
                    )
                  else if (_selectedGroupKey == null)
                    ..._groups.map(
                      (group) => Padding(
                        padding: const EdgeInsets.only(bottom: 12),
                        child: _GroupCard(
                          group: group,
                          planCount: _rows(group['plans']).length,
                          onTap: () => setState(
                            () => _selectedGroupKey = group['group_key']
                                ?.toString(),
                          ),
                        ),
                      ),
                    )
                  else
                    ..._plans
                        .where(
                          (plan) =>
                              plan['group_key']?.toString() ==
                              _selectedGroupKey,
                        )
                        .map(
                          (plan) => Padding(
                            padding: const EdgeInsets.only(bottom: 14),
                            child: _PlanCard(
                              api: widget.api,
                              preferredCurrency: widget.api.preferredCurrency,
                              plan: plan,
                              active: _isActivePlan(plan),
                              purchasing: _purchasing == _asInt(plan['id']),
                              onTap: () => _openPurchase(plan),
                            ),
                          ),
                        ),
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
                              const Text(
                                'Secure subscription payments',
                                style: TextStyle(fontWeight: FontWeight.w800),
                              ),
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
    required this.api,
    required this.preferredCurrency,
    required this.plan,
    required this.active,
    required this.purchasing,
    required this.onTap,
  });

  final AuthApi api;
  final String preferredCurrency;
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
    final original = _asInt(plan['price_minor']);
    final discount = _asDouble(plan['discount']).clamp(0, 100);
    final payable = (original * (100 - discount) / 100).round();
    final bonus = _asDouble(plan['bonus']);
    final special = _asInt(plan['is_special']) == 1;
    final features = _planFeatures(plan, api);
    final dailyAdEarnings = _dailyAdEarningsLabel(plan, api, preferredCurrency);
    final name = plan['name']?.toString() ?? 'Plan';

    return Container(
      decoration: BoxDecoration(
        color: colors.surface,
        borderRadius: BorderRadius.circular(22),
        border: Border.all(
          color: active
              ? colors.primary
              : special
              ? colors.tertiary
              : colors.outlineVariant,
          width: active || special ? 1.6 : 1,
        ),
        boxShadow: [
          BoxShadow(
            color: colors.shadow.withValues(alpha: .045),
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
                padding: const EdgeInsets.symmetric(
                  horizontal: 18,
                  vertical: 8,
                ),
                color: active ? colors.primary : colors.tertiaryContainer,
                child: Row(
                  children: [
                    Icon(
                      active
                          ? Icons.verified_rounded
                          : Icons.auto_awesome_rounded,
                      size: 15,
                      color: active
                          ? colors.onPrimary
                          : colors.onTertiaryContainer,
                    ),
                    const SizedBox(width: 7),
                    Text(
                      active ? 'CURRENT PACKAGE' : 'RECOMMENDED',
                      style: theme.textTheme.labelSmall?.copyWith(
                        color: active
                            ? colors.onPrimary
                            : colors.onTertiaryContainer,
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
                            Text(
                              name,
                              style: theme.textTheme.titleLarge?.copyWith(
                                fontWeight: FontWeight.w900,
                                letterSpacing: -.3,
                              ),
                            ),
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
                          padding: const EdgeInsets.symmetric(
                            horizontal: 10,
                            vertical: 6,
                          ),
                          decoration: BoxDecoration(
                            color: colors.secondaryContainer,
                            borderRadius: BorderRadius.circular(30),
                          ),
                          child: Text(
                            '${discount.toStringAsFixed(0)}% OFF',
                            style: theme.textTheme.labelSmall?.copyWith(
                              color: colors.onSecondaryContainer,
                              fontWeight: FontWeight.w900,
                            ),
                          ),
                        ),
                    ],
                  ),
                  const SizedBox(height: 15),
                  Row(
                    crossAxisAlignment: CrossAxisAlignment.end,
                    children: [
                      Text(
                        moneyConverted(
                          api,
                          payable,
                          currency,
                          digits,
                          preferredCurrency,
                          isMinor: true,
                        ),
                        style: theme.textTheme.headlineMedium?.copyWith(
                          color: colors.primary,
                          fontWeight: FontWeight.w900,
                          letterSpacing: -1,
                        ),
                      ),
                      const SizedBox(width: 7),
                      Padding(
                        padding: const EdgeInsets.only(bottom: 5),
                        child: Text(
                          '/ ${plan['billing_interval'] ?? 'month'}',
                          style: theme.textTheme.bodySmall?.copyWith(
                            color: colors.onSurfaceVariant,
                          ),
                        ),
                      ),
                    ],
                  ),
                  if (discount > 0)
                    Padding(
                      padding: const EdgeInsets.only(top: 2),
                      child: Text(
                        'Regular price ${moneyConverted(api, original, currency, digits, preferredCurrency, isMinor: true)}',
                        style: theme.textTheme.bodySmall?.copyWith(
                          color: colors.onSurfaceVariant,
                          decoration: TextDecoration.lineThrough,
                        ),
                      ),
                    ),
                  if (bonus > 0 && plan['bonus_active'] != false) ...[
                    const SizedBox(height: 10),
                    Row(
                      children: [
                        Icon(
                          Icons.card_giftcard_rounded,
                          size: 17,
                          color: colors.tertiary,
                        ),
                        const SizedBox(width: 7),
                        Expanded(
                          child: Text(
                            'Includes ${moneyConverted(api, bonus, currency, digits, preferredCurrency, isMinor: false)} welcome bonus',
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
                  Text(
                    'WHAT’S INCLUDED',
                    style: theme.textTheme.labelSmall?.copyWith(
                      color: colors.onSurfaceVariant,
                      fontWeight: FontWeight.w900,
                      letterSpacing: 1,
                    ),
                  ),
                  const SizedBox(height: 10),
                  if (features.isNotEmpty)
                    ...features
                        .take(6)
                        .map((feature) => _PlanFeature(text: feature))
                  else ...[
                    if (plan['max_review_tasks'] != null)
                      _PlanFeature(
                        text: 'Up to ${plan['max_review_tasks']} app reviews',
                      ),
                    if (plan['max_ad_shares'] != null)
                      if (_asInt(plan['max_ad_shares']) > 0)
                        _PlanFeature(
                          text:
                              'Up to ${plan['max_ad_shares']} ad shares per package period',
                        ),
                    if (_asInt(plan['max_review_tasks']) > 0)
                      _PlanFeature(
                        text:
                            'Up to ${plan['max_review_tasks']} app reviews per package period',
                      ),
                    if (plan['eligible_app_store'] != null)
                      _PlanFeature(
                        text: 'Review ${plan['eligible_app_store']} apps only',
                      ),
                    if (plan['eligible_app_category'] != null)
                      _PlanFeature(
                        text:
                            'Review ${plan['eligible_app_category']} category apps only',
                      ),
                    if (plan['minimum_withdrawal_minor'] != null)
                      _PlanFeature(
                        text:
                            'Minimum withdrawal ${moneyConverted(api, plan['minimum_withdrawal_minor'], currency, digits, preferredCurrency, isMinor: true)}',
                      ),
                    if (plan['withdrawal_days'] is List)
                      _PlanFeature(
                        text:
                            'Withdraw ${(plan['withdrawal_days'] as List).join(' & ')}',
                      ),
                    if (plan['daily_task_limit'] != null)
                      _PlanFeature(
                        text: '${plan['daily_task_limit']} tasks each day',
                      ),
                  ],
                  if (dailyAdEarnings != null)
                    _PlanFeature(text: dailyAdEarnings),
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
                                Text(
                                  active
                                      ? 'View current package'
                                      : 'Join Package',
                                ),
                                const SizedBox(width: 8),
                                const Icon(
                                  Icons.arrow_forward_rounded,
                                  size: 18,
                                ),
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

class _GroupCard extends StatelessWidget {
  const _GroupCard({
    required this.group,
    required this.planCount,
    required this.onTap,
  });

  final Map<String, dynamic> group;
  final int planCount;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    return Card(
      margin: EdgeInsets.zero,
      color: colors.surfaceContainerLow,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(20),
        side: BorderSide(color: colors.outlineVariant),
      ),
      child: InkWell(
        borderRadius: BorderRadius.circular(20),
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 20),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
			Row(
			  children: [
				  CircleAvatar(
					backgroundColor: colors.primaryContainer,
					child: Icon(
					  Icons.card_membership_rounded,
					  color: colors.onPrimaryContainer,
					),
				  ),
				const Spacer(),
				Container(
				  padding: const EdgeInsets.symmetric(
					horizontal: 10,
					vertical: 6,
				  ),
				  decoration: BoxDecoration(
					color: colors.secondaryContainer,
					borderRadius: BorderRadius.circular(30),
				  ),
				  child: Text(
					'$planCount ${planCount == 1 ? 'package' : 'packages'}',
					style: Theme.of(context).textTheme.labelSmall?.copyWith(
					  color: colors.onSecondaryContainer,
					  fontWeight: FontWeight.w800,
					),
				  ),
				),
			  ],
			),
              const SizedBox(height: 12),
              Text(
                group['name']?.toString() ?? 'Package group',
                style: Theme.of(context).textTheme.titleMedium
                    ?.copyWith(fontWeight: FontWeight.w800),
              ),
              const SizedBox(height: 4),
              Text(
                '${group['description'] ?? ''}',
                maxLines: 3,
                overflow: TextOverflow.ellipsis,
                style: Theme.of(context).textTheme.bodySmall
                    ?.copyWith(color: colors.onSurfaceVariant),
              ),
              const SizedBox(height: 12),
              Align(
                alignment: AlignmentDirectional.centerEnd,
                child: FilledButton(
                  onPressed: onTap,
                  child: const Text('JOIN PACKAGE'),
                ),
              ),
            ],
          ),
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
          Expanded(
            child: Text(text, style: Theme.of(context).textTheme.bodyMedium),
          ),
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
              colors: [
                colors.primary,
                Color.lerp(colors.primary, colors.tertiary, .55)!,
              ],
            ),
            borderRadius: BorderRadius.circular(24),
          ),
          child: Stack(
            children: [
              Positioned(
                right: -12,
                top: -18,
                child: Icon(
                  Icons.workspace_premium_rounded,
                  size: 125,
                  color: foreground.withValues(alpha: .10),
                ),
              ),
              Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Icon(
                        Icons.auto_awesome_rounded,
                        color: foreground,
                        size: 17,
                      ),
                      const SizedBox(width: 7),
                      Text(
                        'YOUR MARKETPLACE ACCESS',
                        style: Theme.of(context).textTheme.labelSmall?.copyWith(
                          color: foreground.withValues(alpha: .9),
                          fontWeight: FontWeight.w900,
                          letterSpacing: 1.2,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 17),
                  Text(
                    active ? planName : 'Unlock more opportunities',
                    style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                      color: foreground,
                      fontWeight: FontWeight.w900,
                      letterSpacing: -.5,
                    ),
                  ),
                  const SizedBox(height: 7),
                  Text(
                    active
                        ? 'Your package is active until ${_formatDate(endsAt)}.'
                        : 'Choose a package to review apps and share ads across the marketplace.',
                    style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                      color: foreground.withValues(alpha: .9),
                      height: 1.4,
                    ),
                  ),
                  const SizedBox(height: 17),
                  Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 12,
                      vertical: 8,
                    ),
                    decoration: BoxDecoration(
                      color: foreground.withValues(alpha: .15),
                      borderRadius: BorderRadius.circular(30),
                      border: Border.all(
                        color: foreground.withValues(alpha: .22),
                      ),
                    ),
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Icon(
                          active
                              ? Icons.verified_rounded
                              : Icons.explore_rounded,
                          size: 16,
                          color: foreground,
                        ),
                        const SizedBox(width: 7),
                        Text(
                          active ? 'Subscription active' : 'Explore packages',
                          style: TextStyle(
                            color: foreground,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                        if (active) ...[
                          const SizedBox(width: 7),
                          Icon(
                            Icons.arrow_forward_rounded,
                            size: 15,
                            color: foreground,
                          ),
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

  String get _currency => widget.plan['currency']?.toString() ?? 'USD';
  List<Map<String, dynamic>> get _checkoutOptions =>
      _rows(widget.plan['checkout_options']);
  Map<String, dynamic>? get _selectedOption {
    for (final option in _checkoutOptions) {
      if (option['option_key']?.toString() == _selectedOptionKey) return option;
    }
    return null;
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

  Map<String, dynamic>? _fundsQuote() {
    final snapshot = _map(widget.user['subscription_context']);
    final wallets = _rows(snapshot['wallets'])
        .where((wallet) => wallet['type'] == 'topup_fund')
        .toList()
      ..sort((a, b) {
        final aNative = a['currency']?.toString().toUpperCase() == _currency.toUpperCase();
        final bNative = b['currency']?.toString().toUpperCase() == _currency.toUpperCase();
        return (bNative ? 1 : 0).compareTo(aNative ? 1 : 0);
      });
    Map<String, dynamic>? firstAvailableQuote;
    for (final wallet in wallets) {
      final walletCurrency = wallet['currency']?.toString().toUpperCase() ?? _currency.toUpperCase();
      final planMajor = _priceMinor / math.pow(10, currencyPrecision(_currency));
      final walletMajor = walletCurrency == _currency.toUpperCase()
          ? planMajor
          : widget.api.convertMajor(planMajor, _currency, walletCurrency);
      if (walletMajor == null) continue;
      final requiredMinor = (walletMajor * math.pow(10, currencyPrecision(walletCurrency))).round();
      final quote = <String, dynamic>{
        'currency': walletCurrency,
        'required_minor': requiredMinor,
        'balance_minor': _asInt(wallet['available_balance_minor']),
      };
      firstAvailableQuote ??= quote;
      if (quote['balance_minor'] >= quote['required_minor']) return quote;
    }
    return firstAvailableQuote;
  }

  Future<void> _payWithFunds() async {
    final quote = _fundsQuote();
    final walletCurrency = quote?['currency']?.toString() ?? _currency;
    if (quote == null || quote['balance_minor']! < quote['required_minor']!) {
      setState(
        () => _message =
            quote == null
                ? 'A current exchange rate or top-up wallet is unavailable. Use Secure payment or fund your wallet first.'
                : 'Your $walletCurrency top-up wallet needs ${_money(quote['required_minor']!, walletCurrency, currencyPrecision(walletCurrency))}. Use Secure payment or fund your wallet first.',
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
      if (mounted) {
        setState(() {
          _busy = false;
          _message = error.message;
        });
      }
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
      if (url == null || url.scheme != 'https' || intent == null) {
        throw const AuthException(
          'The payment provider returned an invalid checkout link.',
        );
      }
      _pendingIntent = intent;
      final opened = await launchUrl(url, mode: LaunchMode.inAppBrowserView);
      if (!opened) {
        throw const AuthException('Could not open secure checkout. Try again.');
      }
      await _checkPayment();
    } on AuthException catch (error) {
      if (mounted) {
        setState(() {
          _busy = false;
          _message = error.message;
        });
      }
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
      if (mounted) {
        setState(() {
          _busy = false;
          _message = error.message;
        });
      }
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
    final fundsQuote = _fundsQuote();
    final fundsCurrency = fundsQuote?['currency']?.toString();
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
                  _rows(
                    _map(
                      widget.user['subscription_context'],
                    )['active_marketplaces'],
                  ).any(
                    (subscription) =>
                        subscription['group_key'] == widget.plan['group_key'],
                  )) ...[
                const SizedBox(height: 8),
                Text(
                  'Joining replaces only the active package in this group.',
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
                  constraints: const BoxConstraints(maxHeight: 270),
                  child: ListView.separated(
                    shrinkWrap: true,
                    itemCount: _checkoutOptions.length,
                    separatorBuilder: (_, __) => const SizedBox(height: 6),
                    itemBuilder: (context, index) {
                      final option = _checkoutOptions[index];

                      final optionKey = option['option_key']?.toString() ?? '';

                      final currency =
                          option['currency']?.toString().toUpperCase() ?? _currency;

                      final currencyInfo = _currencyInfo(currency);

                      final currencyName = currencyInfo?.name ?? currency;
                      final flag = currencyInfo?.flag ?? '🌐';

                      final gateway = option['gateway']?.toString() ?? '';

                      final amount = _money(
                        _asInt(option['amount_minor']),
                        currency,
                        _asInt(
                          option['currency_minor_units'],
                          fallback: 2,
                        ).clamp(0, 4),
                      );

                      final isSelected = _selectedOptionKey == optionKey;

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
                                    _selectedOptionKey = optionKey;
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
                                        '$currencyName · $currency',
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
                  label: Text(fundsCurrency == null
                      ? 'Funds wallet'
                      : 'Funds wallet · $fundsCurrency'),
                ),
              ),
              Padding(
                padding: const EdgeInsets.only(top: 4),
                child: Text(
                  'Funds wallet checks your available top-up balances and converts the package price when needed.',
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

Map<String, dynamic> _map(Object? value) => value is Map
    ? value.map((key, value) => MapEntry(key.toString(), value))
    : <String, dynamic>{};

Map<String, dynamic>? _mapOrNull(Object? value) =>
    value is Map ? _map(value) : null;

List<Map<String, dynamic>> _rows(Object? value) => value is List
    ? value.whereType<Map>().map((item) => _map(item)).toList(growable: false)
    : const [];

List<String> _planFeatures(Map<String, dynamic> plan, AuthApi api) {
  final value = plan['features'];
  if (value is List) {
    final currency = plan['currency']?.toString() ?? 'USD';
    final digits = _asInt(
      plan['currency_minor_units'],
      fallback: 2,
    ).clamp(0, 4);
    return value
        .whereType<Map>()
        .map((item) {
          final label =
              item['label']?.toString() ??
              item['feature_key']?.toString() ??
              '';
          final value = item['value'];
          if (label.isEmpty || value == null) return label;
          final key = item['feature_key']?.toString();
          if (const {
                'amount',
                'minimum_withdrawal',
                'daily_revenue',
                'per_survey',
                'task_reward',
              }.contains(key) &&
              value is num) {
            return '$label: ${moneyConverted(api, value, currency, digits, api.preferredCurrency, isMinor: false)}';
          }
          return '$label: ${value is List ? value.join(', ') : value}';
        })
        .where((label) => label.isNotEmpty)
        .toList();
  }
  return const [];
}

String? _dailyAdEarningsLabel(
  Map<String, dynamic> plan,
  AuthApi api,
  String preferredCurrency,
) {
  if (plan['group_key']?.toString() != 'group_a' ||
      plan['daily_revenue_minor'] is! num) {
    return null;
  }

  final currency = plan['currency']?.toString() ?? 'USD';
  final digits = _asInt(plan['currency_minor_units'], fallback: 2).clamp(0, 4);
  final amount = moneyConverted(
    api,
    plan['daily_revenue_minor'],
    currency,
    digits,
    preferredCurrency,
    isMinor: true,
  );

  return 'Daily ad earnings up to $amount';
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
  return '${symbols[currency.toUpperCase()] ?? '$currency '}${formatGroupedNumber(value, digits)}';
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
