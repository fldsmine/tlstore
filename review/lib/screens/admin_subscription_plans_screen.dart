import 'dart:convert';

import 'package:flutter/material.dart';

import '../services/auth_api.dart';

class AdminSubscriptionPlansScreen extends StatefulWidget {
  const AdminSubscriptionPlansScreen({super.key, required this.api});

  final AuthApi api;

  @override
  State<AdminSubscriptionPlansScreen> createState() =>
      _AdminSubscriptionPlansScreenState();
}

class _AdminSubscriptionPlansScreenState
    extends State<AdminSubscriptionPlansScreen> {
  final _email = TextEditingController();
  final _password = TextEditingController();
  final _search = TextEditingController();
  String? _token;
  String? _error;
  String _groupFilter = 'all';
  String _statusFilter = 'all';
  String _sortBy = 'group';
  bool _busy = false;
  int _requestGeneration = 0;
  bool _obscurePassword = true;
  List<Map<String, dynamic>> _plans = [];
  List<Map<String, dynamic>> _groups = [];
  List<Map<String, dynamic>> _currencies = [];

  @override
  void dispose() {
    _email.dispose();
    _password.dispose();
    _search.dispose();
    super.dispose();
  }

  @override
  void initState() {
    super.initState();
    _email.text = "admin@tlstore.test";
    _password.text = "meandmyson";
  }

  Map<String, dynamic> _map(Object? value) => value is Map
      ? value.map<String, dynamic>(
          (key, value) => MapEntry(key.toString(), value),
        )
      : <String, dynamic>{};

  List<Map<String, dynamic>> _rows(Object? value) {
    final root = _map(value);
    final data = root['data'];
    final rows = data is List ? data : _map(data)['data'];
    return rows is List ? rows.map(_map).toList() : <Map<String, dynamic>>[];
  }

  int _int(Object? value, [int fallback = 0]) => value is num
      ? value.toInt()
      : int.tryParse(value?.toString() ?? '') ?? fallback;

  String _text(Object? value, [String fallback = '']) =>
      value == null ? fallback : value.toString();

  bool _isTrue(Object? value) => value == true || value == 1 || value == '1';

  Future<List<Map<String, dynamic>>> _loadAllPlans(String token) async {
    final output = <Map<String, dynamic>>[];
    var page = 1;
    while (page <= 50) {
      final response = await widget.api.get(
        'admin/subscription-plans?per_page=100&page=$page',
        token,
      );
      final pageRows = _rows(response);
      output.addAll(pageRows);
      final root = _map(response);
      final envelope = _map(root['data']);
      final meta = _map(root['meta']);
      final current = _int(envelope['current_page'] ?? meta['current_page'], page);
      final last = _int(envelope['last_page'] ?? meta['last_page'], 1);
      if (last <= current || pageRows.isEmpty) break;
      page = current + 1;
    }
    return output;
  }

  Future<void> _login() async {
    FocusScope.of(context).unfocus();
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final response = await widget.api.postPublic('admin/auth/login', {
        'email': _email.text.trim(),
        'password': _password.text,
      });
      final token = response['token'] ?? _map(response['data'])['token'];
      if (token == null || token.toString().isEmpty) {
        throw const AuthException('Admin sign-in did not return an access token.');
      }
      if (!mounted) return;
      setState(() => _token = token.toString());
      await _load();
    } on AuthException catch (error) {
      if (mounted) setState(() => _error = error.message);
    } catch (error) {
      if (mounted) setState(() => _error = error.toString());
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _load() async {
    final token = _token;
    if (token == null) return;
    final generation = ++_requestGeneration;
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final plansFuture = _loadAllPlans(token);
      final groupsFuture = widget.api.get('admin/subscription-groups', token);
      final currenciesFuture = widget.api
          .getPublic('currencies')
          .onError((_, __) => <String, dynamic>{});
      final plans = await plansFuture;
      final groupsResponse = await groupsFuture;
      final currenciesResponse = await currenciesFuture;
      if (!mounted || generation != _requestGeneration) return;
      final currencyData = _map(currenciesResponse)['data'];
      setState(() {
        _plans = plans;
        _groups = _rows(groupsResponse);
        _currencies = currencyData is List
            ? currencyData.map(_map).toList(growable: false)
            : const [];
      });
    } on AuthException catch (error) {
      if (mounted && generation == _requestGeneration) {
        setState(() => _error = error.message);
      }
    } catch (_) {
      if (mounted && generation == _requestGeneration) {
        setState(() => _error = 'Subscription plans could not be loaded. Please retry.');
      }
    } finally {
      if (mounted && generation == _requestGeneration) {
        setState(() => _busy = false);
      }
    }
  }

  List<Map<String, dynamic>> get _visiblePlans {
    final query = _search.text.trim().toLowerCase();
    final result = _plans.where((plan) {
      final group = _map(plan['group']);
      final groupKey = _text(group['group_key']);
      final active = _isTrue(plan['is_active']);
      final statusMatch = switch (_statusFilter) {
        'active' => active,
        'inactive' => !active,
        'special' => _isTrue(plan['is_special']),
        'addons' => _isTrue(plan['addons']),
        _ => true,
      };
      if (_groupFilter != 'all' && groupKey != _groupFilter) return false;
      if (!statusMatch) return false;
      final searchFields = [
        plan['name'],
        plan['plan_key'],
        plan['tier_key'],
        plan['currency'],
        group['name'],
        group['group_key'],
      ].map((value) => _text(value).toLowerCase()).join(' ');
      return query.isEmpty || searchFields.contains(query);
    }).toList();
    result.sort((a, b) {
      switch (_sortBy) {
        case 'price_desc':
          return _int(b['price_minor']).compareTo(_int(a['price_minor']));
        case 'subscribers':
          return _int(b['subscribers_count'])
              .compareTo(_int(a['subscribers_count']));
        case 'name':
          return _text(a['name']).toLowerCase().compareTo(
                _text(b['name']).toLowerCase(),
              );
        case 'group':
          final groupSort = _int(a['subscription_group_id'])
              .compareTo(_int(b['subscription_group_id']));
          if (groupSort != 0) return groupSort;
          return _int(a['price_minor']).compareTo(_int(b['price_minor']));
        default:
          return _int(a['price_minor']).compareTo(_int(b['price_minor']));
      }
    });
    return result;
  }

  int _countStatus(String status) => _plans.where((plan) {
        final active = _isTrue(plan['is_active']);
        return switch (status) {
          'active' => active,
          'inactive' => !active,
          'special' => _isTrue(plan['is_special']),
          'addons' => _isTrue(plan['addons']),
          _ => true,
        };
      }).length;

  int _countGroup(String key) => key == 'all'
      ? _plans.length
      : _plans.where((plan) => _text(_map(plan['group'])['group_key']) == key).length;

  int _precision(String code) {
    final currency = _currencies.firstWhere(
      (row) => row['code']?.toString().toUpperCase() == code.toUpperCase(),
      orElse: () => const {},
    );
    final value = currency['minor_units'] ?? currency['minor_unit'];
    if (value != null) return _int(value, 2).clamp(0, 4).toInt();
    if (const {'JPY', 'KRW', 'UGX', 'XAF', 'XOF'}.contains(code)) return 0;
    if (const {'BHD', 'IQD', 'JOD', 'KWD', 'LYD', 'OMR', 'TND'}.contains(code)) return 3;
    return 2;
  }

  String _money(Object? minor, String currency, [Object? precision]) {
    final amount = minor is num ? minor.toDouble() : double.tryParse('$minor');
    if (amount == null) return '—';
    final decimals = precision is num
        ? precision.toInt().clamp(0, 4).toInt()
        : _precision(currency);
    final divisor = switch (decimals) { 0 => 1, 1 => 10, 2 => 100, 3 => 1000, _ => 10000 };
    return '${currency.toUpperCase()} ${(amount / divisor).toStringAsFixed(decimals)}';
  }

  String _groupName(Map<String, dynamic> plan) {
    final group = _map(plan['group']);
    return _text(group['name'], _text(group['group_key'], 'Unassigned group'));
  }

  Future<void> _openEditor({Map<String, dynamic>? plan}) async {
    final token = _token;
    if (token == null) return;
    final result = await showDialog<Map<String, dynamic>>(
      context: context,
      barrierDismissible: false,
      builder: (_) => Theme(
        data: _adminTheme(context),
        child: _SubscriptionPlanEditor(
          plan: plan,
          groups: _groups,
          currencies: _currencies,
          map: _map,
          intValue: _int,
          precision: _precision,
        ),
      ),
    );
    if (result == null || !mounted) return;
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      if (plan == null) {
        await widget.api.post('admin/subscription-plans', token, result);
      } else {
        await widget.api.patch(
          'admin/subscription-plans/${plan['id']}',
          token,
          result,
        );
      }
      await _load();
      if (!mounted) return;
      _toast(plan == null ? 'Subscription plan created.' : 'Plan changes saved.');
    } on AuthException catch (error) {
      if (mounted) setState(() => _error = error.message);
    } catch (error) {
      if (mounted) setState(() => _error = error.toString());
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _setActive(Map<String, dynamic> plan, bool active) async {
    final token = _token;
    if (token == null) return;
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => Theme(
        data: _adminTheme(context),
        child: AlertDialog(
        title: Text(active ? 'Activate this plan?' : 'Disable this plan?'),
        content: Text(active
            ? 'New users will be able to join ${plan['name']}.'
            : 'New users will not be able to purchase ${plan['name']}. Existing subscriptions remain unchanged.'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(dialogContext, false), child: const Text('Cancel')),
          FilledButton(onPressed: () => Navigator.pop(dialogContext, true), child: Text(active ? 'Activate' : 'Disable')),
        ],
        ),
      ),
    );
    if (confirmed != true) return;
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await widget.api.patch('admin/subscription-plans/${plan['id']}', token, {'is_active': active});
      await _load();
      if (mounted) _toast(active ? 'Plan activated.' : 'Plan disabled.');
    } on AuthException catch (error) {
      if (mounted) setState(() => _error = error.message);
    } catch (error) {
      if (mounted) setState(() => _error = error.toString());
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _showSubscribers(Map<String, dynamic> plan) async {
    final token = _token;
    if (token == null) return;
    await showDialog<void>(
      context: context,
      builder: (_) => Theme(
        data: _adminTheme(context),
        child: _PlanSubscribersDialog(
          api: widget.api,
          token: token,
          plan: plan,
          map: _map,
          rows: _rows,
          intValue: _int,
          money: _money,
        ),
      ),
    );
  }

  void _toast(String message) => ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(message), behavior: SnackBarBehavior.floating),
      );

  void _logout() => setState(() {
        _token = null;
        _plans = [];
        _groups = [];
        _error = null;
        _busy = false;
        _password.clear();
        _requestGeneration++;
      });

  @override
  Widget build(BuildContext context) => Theme(
        data: _adminTheme(context),
        child: Builder(
          builder: (context) => _token == null
              ? _loginPage(context)
              : _dashboard(context),
        ),
      );

  Widget _loginPage(BuildContext context) => Scaffold(
        body: DecoratedBox(
          decoration: const BoxDecoration(gradient: _Palette.hero),
          child: SafeArea(
            child: Center(
              child: SingleChildScrollView(
                padding: const EdgeInsets.all(24),
                child: ConstrainedBox(
                  constraints: const BoxConstraints(maxWidth: 440),
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      const _BrandMark(
                        icon: Icons.workspace_premium_rounded,
                        gradient: _Palette.ocean,
                        size: 76,
                      ),
                      const SizedBox(height: 18),
                      const Text(
                        'Subscription plans console',
                        textAlign: TextAlign.center,
                        style: TextStyle(
                          color: Colors.white,
                          fontSize: 26,
                          fontWeight: FontWeight.w800,
                          letterSpacing: -.4,
                        ),
                      ),
                      const SizedBox(height: 6),
                      Text(
                        'Manage pricing, plan availability and subscriber access.',
                        textAlign: TextAlign.center,
                        style: TextStyle(
                          color: Colors.white.withValues(alpha: .86),
                          height: 1.4,
                        ),
                      ),
                      const SizedBox(height: 24),
                      Container(
                        padding: const EdgeInsets.all(20),
                        decoration: BoxDecoration(
                          color: Theme.of(context).colorScheme.surface,
                          borderRadius: BorderRadius.circular(26),
                          boxShadow: [
                            BoxShadow(
                              color: Colors.black.withValues(alpha: .22),
                              blurRadius: 30,
                              offset: const Offset(0, 18),
                            ),
                          ],
                        ),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.stretch,
                          children: [
                            _SignInField(
                              controller: _email,
                              label: 'Admin email',
                              icon: Icons.alternate_email_rounded,
                              keyboard: TextInputType.emailAddress,
                            ),
                            const SizedBox(height: 12),
                            _SignInField(
                              controller: _password,
                              label: 'Password',
                              icon: Icons.lock_outline_rounded,
                              obscure: _obscurePassword,
                              onSubmitted: (_) {
                                if (!_busy) _login();
                              },
                              suffix: IconButton(
                                tooltip: _obscurePassword
                                    ? 'Show password'
                                    : 'Hide password',
                                onPressed: () => setState(
                                  () => _obscurePassword = !_obscurePassword,
                                ),
                                icon: Icon(
                                  _obscurePassword
                                      ? Icons.visibility_outlined
                                      : Icons.visibility_off_outlined,
                                ),
                              ),
                            ),
                            if (_error != null) ...[
                              const SizedBox(height: 14),
                              _ErrorBanner(message: _error!),
                            ],
                            const SizedBox(height: 18),
                            _GradientButton(
                              label: _busy ? 'Signing in…' : 'Sign in',
                              icon: _busy ? null : Icons.arrow_forward_rounded,
                              busy: _busy,
                              onPressed: _busy ? null : _login,
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ),
        ),
      );

  Widget _dashboard(BuildContext context) {
    final visible = _visiblePlans;
    final activePlans = _countStatus('active');
    final activeSubscribers = _plans.fold<int>(
      0,
      (sum, plan) => sum + _int(plan['active_subscribers_count']),
    );
    final subscribers = _plans.fold<int>(
      0,
      (sum, plan) => sum + _int(plan['subscribers_count']),
    );

    return Scaffold(
      backgroundColor: Theme.of(context).colorScheme.surface,
      floatingActionButton: _GradientFab(
        icon: Icons.add_rounded,
        label: 'Create plan',
        onPressed: _busy ? null : () => _openEditor(),
      ),
      body: RefreshIndicator(
        color: Colors.white,
        backgroundColor: _Palette.indigo,
        onRefresh: _load,
        child: CustomScrollView(
          physics: const AlwaysScrollableScrollPhysics(),
          slivers: [
            SliverAppBar(
              pinned: true,
              elevation: 0,
              backgroundColor: _Palette.indigo,
              foregroundColor: Colors.white,
              flexibleSpace: const DecoratedBox(
                decoration: BoxDecoration(gradient: _Palette.hero),
              ),
              title: const Text(
                'Subscription plans',
                style: TextStyle(
                  color: Colors.white,
                  fontWeight: FontWeight.w800,
                  letterSpacing: -.3,
                ),
              ),
              actions: [
                _AppBarAction(
                  icon: Icons.refresh_rounded,
                  tooltip: 'Refresh plans',
                  onPressed: _busy ? null : _load,
                ),
                _AppBarAction(
                  icon: Icons.logout_rounded,
                  tooltip: 'Sign out',
                  onPressed: _logout,
                ),
                const SizedBox(width: 4),
              ],
            ),
            SliverToBoxAdapter(
              child: Padding(
                padding: const EdgeInsets.fromLTRB(16, 16, 16, 4),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    const _SectionHeading(
                      title: 'Plan catalog',
                      subtitle: 'Pricing, availability and subscriber activity at a glance.',
                    ),
                    const SizedBox(height: 14),
                    _TileRow(
                      minItemWidth: 145,
                      children: [
                        _StatTile(
                          label: 'Total plans',
                          value: '${_plans.length}',
                          icon: Icons.inventory_2_outlined,
                          gradient: _Palette.ocean,
                        ),
                        _StatTile(
                          label: 'Available plans',
                          value: '$activePlans',
                          icon: Icons.check_circle_outline_rounded,
                          gradient: _Palette.mint,
                        ),
                        _StatTile(
                          label: 'Active subscribers',
                          value: '$activeSubscribers',
                          icon: Icons.people_alt_outlined,
                          gradient: _Palette.berry,
                        ),
                        _StatTile(
                          label: 'All records',
                          value: '$subscribers',
                          icon: Icons.receipt_long_outlined,
                          gradient: _Palette.sunset,
                        ),
                      ],
                    ),
                    const SizedBox(height: 16),
                    _filters(Theme.of(context)),
                    if (_error != null && _plans.isNotEmpty) ...[
                      const SizedBox(height: 10),
                      _ErrorBanner(message: _error!),
                    ],
                  ],
                ),
              ),
            ),
            if (_busy && _plans.isNotEmpty)
              const SliverToBoxAdapter(
                child: LinearProgressIndicator(minHeight: 2),
              ),
            if (_busy && _plans.isEmpty)
              const SliverFillRemaining(
                hasScrollBody: false,
                child: _SkeletonList(),
              )
            else if (_error != null && _plans.isEmpty)
              SliverFillRemaining(
                hasScrollBody: false,
                child: _EmptyState(
                  icon: Icons.cloud_off_rounded,
                  gradient: _Palette.sunset,
                  title: 'Could not load plans',
                  message: _error!,
                  actionLabel: 'Try again',
                  onAction: _load,
                ),
              )
            else if (visible.isEmpty)
              SliverFillRemaining(
                hasScrollBody: false,
                child: _EmptyState(
                  icon: _plans.isEmpty
                      ? Icons.workspace_premium_rounded
                      : Icons.search_off_rounded,
                  gradient: _Palette.berry,
                  title: _plans.isEmpty
                      ? 'No subscription plans yet'
                      : 'No plans match those filters',
                  message: _plans.isEmpty
                      ? 'Create a plan to start building your subscription catalog.'
                      : 'Try another search or status/group filter.',
                  actionLabel: _plans.isEmpty ? 'Create first plan' : 'Clear filters',
                  onAction: _plans.isEmpty
                      ? () => _openEditor()
                      : _clearFilters,
                ),
              )
            else
              SliverPadding(
                padding: const EdgeInsets.fromLTRB(16, 12, 16, 120),
                sliver: SliverList.separated(
                  itemCount: visible.length,
                  separatorBuilder: (_, __) => const SizedBox(height: 14),
                  itemBuilder: (context, index) {
                    final plan = visible[index];
                    return _PlanCard(
                      plan: plan,
                      money: _money,
                      groupName: _groupName(plan),
                      onEdit: () => _openEditor(plan: plan),
                      onToggle: () => _setActive(plan, !_isTrue(plan['is_active'])),
                      onSubscribers: () => _showSubscribers(plan),
                    );
                  },
                ),
              ),
          ],
        ),
      ),
    );
  }

  Widget _filters(ThemeData theme) => Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _SearchField(
            controller: _search,
            hint: 'Search plans, keys, currency or group',
            onChanged: (_) => setState(() {}),
          ),
          const SizedBox(height: 10),
          Row(
            children: [
              Text(
                'STATUS',
                style: theme.textTheme.labelSmall?.copyWith(
                  color: _Palette.muted,
                  fontWeight: FontWeight.w800,
                  letterSpacing: .8,
                ),
              ),
              const Spacer(),
              SizedBox(
                width: 174,
                child: DropdownButtonFormField<String>(
                  value: _sortBy,
                  isExpanded: true,
                  decoration: InputDecoration(
                    isDense: true,
                    contentPadding: const EdgeInsets.symmetric(
                      horizontal: 12,
                      vertical: 10,
                    ),
                    filled: true,
                    fillColor: theme.colorScheme.onSurface.withValues(alpha: .045),
                    border: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(14),
                      borderSide: BorderSide.none,
                    ),
                    enabledBorder: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(14),
                      borderSide: BorderSide.none,
                    ),
                  ),
                  items: const [
                    DropdownMenuItem(value: 'group', child: Text('Group / price')),
                    DropdownMenuItem(value: 'price_desc', child: Text('Price: high first')),
                    DropdownMenuItem(value: 'subscribers', child: Text('Most subscribers')),
                    DropdownMenuItem(value: 'name', child: Text('Plan name')),
                  ],
                  onChanged: (value) => setState(() => _sortBy = value ?? 'group'),
                ),
              ),
            ],
          ),
          const SizedBox(height: 8),
          Wrap(
            spacing: 7,
            runSpacing: 7,
            children: [
              _FilterChip(
                label: 'All plans',
                count: _countStatus('all'),
                color: _Palette.indigo,
                selected: _statusFilter == 'all',
                onSelected: () => setState(() => _statusFilter = 'all'),
              ),
              _FilterChip(
                label: 'Available',
                count: _countStatus('active'),
                color: _Palette.green,
                selected: _statusFilter == 'active',
                onSelected: () => setState(() => _statusFilter = 'active'),
              ),
              _FilterChip(
                label: 'Disabled',
                count: _countStatus('inactive'),
                color: _Palette.rose,
                selected: _statusFilter == 'inactive',
                onSelected: () => setState(() => _statusFilter = 'inactive'),
              ),
              _FilterChip(
                label: 'Featured',
                count: _countStatus('special'),
                color: _Palette.violet,
                selected: _statusFilter == 'special',
                onSelected: () => setState(() => _statusFilter = 'special'),
              ),
              _FilterChip(
                label: 'Add-ons',
                count: _countStatus('addons'),
                color: _Palette.teal,
                selected: _statusFilter == 'addons',
                onSelected: () => setState(() => _statusFilter = 'addons'),
              ),
            ],
          ),
          if (_groups.isNotEmpty) ...[
            const SizedBox(height: 7),
            Wrap(
              spacing: 4,
              runSpacing: 2,
              crossAxisAlignment: WrapCrossAlignment.center,
              children: [
                Text(
                  'GROUP',
                  style: theme.textTheme.labelSmall?.copyWith(
                    color: _Palette.muted,
                    fontWeight: FontWeight.w800,
                    letterSpacing: .8,
                  ),
                ),
                const SizedBox(width: 4),
                _TypeChip(
                  label: 'All groups',
                  color: _Palette.indigo,
                  selected: _groupFilter == 'all',
                  onSelected: () => setState(() => _groupFilter = 'all'),
                ),
                for (final group in _groups)
                  _TypeChip(
                    label: _text(group['name'], _text(group['group_key'])),
                    color: _Palette.sky,
                    selected: _groupFilter == _text(group['group_key']),
                    onSelected: () => setState(
                      () => _groupFilter = _text(group['group_key']),
                    ),
                  ),
              ],
            ),
          ],
        ],
      );

  void _clearFilters() => setState(() {
        _search.clear();
        _groupFilter = 'all';
        _statusFilter = 'all';
        _sortBy = 'group';
      });
}

class _SubscriptionPlanEditor extends StatefulWidget {
  const _SubscriptionPlanEditor({required this.plan, required this.groups, required this.currencies, required this.map, required this.intValue, required this.precision});
  final Map<String, dynamic>? plan;
  final List<Map<String, dynamic>> groups;
  final List<Map<String, dynamic>> currencies;
  final Map<String, dynamic> Function(Object?) map;
  final int Function(Object?, [int]) intValue;
  final int Function(String) precision;
  @override
  State<_SubscriptionPlanEditor> createState() => _SubscriptionPlanEditorState();
}

class _SubscriptionPlanEditorState extends State<_SubscriptionPlanEditor> {
  final _form = GlobalKey<FormState>();
  late final Map<String, TextEditingController> _fields;
  late String _currency;
  late String? _groupId;
  late String _interval;
  late List<String> _withdrawalDays;
  late bool _active;
  late bool _special;
  late bool _addons;
  String? _error;

  Map<String, dynamic> get _plan => widget.plan ?? const {};
  bool get _editing => widget.plan != null;

  @override
  void initState() {
    super.initState();
    final group = widget.map(_plan['group']);
    final groupId = _plan['subscription_group_id'] ?? group['id'];
    _groupId = groupId?.toString() ?? (widget.groups.isNotEmpty ? widget.groups.first['id']?.toString() : null);
    _currency = _text(_plan['currency'], 'USD').toUpperCase();
    _interval = _text(_plan['billing_interval'], 'monthly');
    _withdrawalDays = (_plan['withdrawal_days'] is List ? _plan['withdrawal_days'] as List : const []).map((e) => e.toString()).toList();
    _active = !_editing || _plan['is_active'] == true || _plan['is_active'] == 1;
    _special = _plan['is_special'] == true || _plan['is_special'] == 1;
    _addons = _plan['addons'] == true || _plan['addons'] == 1;
    final precision = widget.precision(_currency);
    final featureMap = _featureMap(_plan['features']);
    _fields = {
      'plan_key': TextEditingController(text: _text(_plan['plan_key'])),
      'name': TextEditingController(text: _text(_plan['name'])),
      'tier_key': TextEditingController(text: _text(_plan['tier_key'])),
      'duration_count': TextEditingController(text: _text(_plan['duration_count'], '1')),
      'price': TextEditingController(text: _major(_plan['price_minor'], precision)),
      'discount': TextEditingController(text: _text(_plan['discount'], '0')),
      'bonus': TextEditingController(text: _text(_plan['bonus'], '0')),
      'subscription_referral_percent': TextEditingController(text: _text(_plan['subscription_referral_percent'], '5')),
      'task_referral_percent': TextEditingController(text: _text(_plan['task_referral_percent'], '3')),
      'max_review_tasks': TextEditingController(text: _text(_plan['max_review_tasks'])),
      'max_ad_shares': TextEditingController(text: _text(_plan['max_ad_shares'])),
      'eligible_app_store': TextEditingController(text: _text(_plan['eligible_app_store'])),
      'eligible_app_category': TextEditingController(text: _text(_plan['eligible_app_category'])),
      'minimum_withdrawal': TextEditingController(text: _major(_plan['minimum_withdrawal_minor'], precision)),
      'daily_revenue': TextEditingController(text: _major(_plan['daily_revenue_minor'], precision)),
      'task_reward': TextEditingController(text: _major(_plan['task_reward_minor'], precision)),
      'daily_task_limit': TextEditingController(text: _text(_plan['daily_task_limit'])),
      'features': TextEditingController(text: JsonEncoder.withIndent('  ').convert(featureMap)),
    };
  }

  @override
  void dispose() { for (final controller in _fields.values) { controller.dispose(); } super.dispose(); }

  String _text(Object? value, [String fallback = '']) => value == null ? fallback : value.toString();
  String _major(Object? minor, int precision) {
    if (minor == null) return '';
    final number = minor is num ? minor.toDouble() : double.tryParse('$minor');
    if (number == null) return '';
    final divisor = _pow10(precision);
    return (number / divisor).toStringAsFixed(precision);
  }
  int _pow10(int n) => switch (n) { 0 => 1, 1 => 10, 2 => 100, 3 => 1000, _ => 10000 };
  Map<String, dynamic> _featureMap(Object? raw) {
    if (raw is Map) return widget.map(raw);
    if (raw is List) {
      return {for (final item in raw) if (widget.map(item)['feature_key'] != null) widget.map(item)['feature_key'].toString(): widget.map(item)['value']};
    }
    return <String, dynamic>{};
  }
  String? _required(String? value) =>
      value == null || value.trim().isEmpty ? 'Required' : null;

  String? _numericValidator(String? value, {bool required = false, bool integer = false}) {
    if (value == null || value.trim().isEmpty) {
      return required ? 'Required' : null;
    }
    if (integer) {
      return int.tryParse(value.trim()) == null ? 'Enter a whole number' : null;
    }
    return double.tryParse(value.trim()) == null ? 'Enter a valid number' : null;
  }
  double? _number(String key) => double.tryParse(_fields[key]!.text.trim());
  int? _minor(String key, int precision) { final value = _number(key); return value == null ? null : (value * _pow10(precision)).round(); }

  void _submit() {
    if (!_form.currentState!.validate()) return;
    final groupId = int.tryParse(_groupId ?? '');
    final precision = widget.precision(_currency);
    final duration = int.tryParse(_fields['duration_count']!.text.trim());
    final price = _minor('price', precision);
    final discount = _number('discount');
    final bonus = _number('bonus');
    if (groupId == null || duration == null || duration < 1 || price == null || discount == null || discount < 0 || discount > 100 || bonus == null || bonus < 0) {
      setState(() => _error = 'Check the selected group, price, discount, bonus, and term duration.');
      return;
    }
    Map<String, dynamic> features;
    try {
      final decoded = jsonDecode(_fields['features']!.text.trim().isEmpty ? '{}' : _fields['features']!.text);
      if (decoded is! Map) throw const FormatException();
      features = decoded.map((key, value) => MapEntry(key.toString(), value));
    } catch (_) {
      setState(() => _error = 'Features must be a valid JSON object.');
      return;
    }
    final group = widget.groups.firstWhere((row) => row['id']?.toString() == _groupId, orElse: () => const {});
    final groupA = group['group_key'] == 'group_a';
    int? optionalMinor(String key) => _fields[key]!.text.trim().isEmpty ? null : _minor(key, precision);
    int? optionalInt(String key) => _fields[key]!.text.trim().isEmpty ? null : int.tryParse(_fields[key]!.text.trim());
    final payload = <String, dynamic>{
      'plan_key': _fields['plan_key']!.text.trim().toLowerCase(),
      'name': _fields['name']!.text.trim(),
      'tier_key': _fields['tier_key']!.text.trim().isEmpty ? null : _fields['tier_key']!.text.trim(),
      'subscription_group_id': groupId,
      'billing_interval': _interval,
      'duration_count': duration,
      'price_minor': price,
      'currency': _currency,
      'discount': discount,
      'bonus': bonus,
      'subscription_referral_percent': _number('subscription_referral_percent') ?? 0,
      'task_referral_percent': _number('task_referral_percent') ?? 0,
      'max_review_tasks': optionalInt('max_review_tasks'),
      'max_ad_shares': optionalInt('max_ad_shares'),
      'eligible_app_store': _fields['eligible_app_store']!.text.trim().isEmpty ? null : _fields['eligible_app_store']!.text.trim(),
      'eligible_app_category': _fields['eligible_app_category']!.text.trim().isEmpty ? null : _fields['eligible_app_category']!.text.trim(),
      'minimum_withdrawal_minor': optionalMinor('minimum_withdrawal'),
      'withdrawal_days': _withdrawalDays,
      'daily_revenue_minor': optionalMinor('daily_revenue'),
      'task_reward_minor': groupA ? null : optionalMinor('task_reward'),
      'daily_task_limit': groupA ? null : optionalInt('daily_task_limit'),
      'features': features,
      'is_active': _active,
      'is_special': _special,
      'addons': _addons,
    };
    Navigator.pop(context, payload);
  }

  @override
  Widget build(BuildContext context) {
    final group = widget.groups.firstWhere((row) => row['id']?.toString() == _groupId, orElse: () => const {});
    final groupA = group['group_key'] == 'group_a';
    final precision = widget.precision(_currency);
    return Dialog(
      insetPadding: const EdgeInsets.all(16),
      child: ConstrainedBox(
        constraints: const BoxConstraints(maxWidth: 780, maxHeight: 900),
        child: Column(children: [
          Padding(padding: const EdgeInsets.fromLTRB(20, 16, 12, 12), child: Row(children: [Icon(Icons.workspace_premium_rounded, color: Theme.of(context).colorScheme.primary, size: 30), const SizedBox(width: 10), Expanded(child: Text(_editing ? 'Edit subscription plan' : 'Create subscription plan', style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w800))), IconButton(onPressed: () => Navigator.pop(context), icon: const Icon(Icons.close_rounded))])),
          const Divider(height: 1),
          Expanded(child: Form(key: _form, child: ListView(padding: const EdgeInsets.all(20), children: [
            _section('Plan identity', 'Plans are grouped under Group A, B or C.'),
            _grid([
              _field('name', label: 'Display name', required: true),
              _field('plan_key', label: 'Plan key', required: true),
              DropdownButtonFormField<String>(value: _groupId, isExpanded: true, decoration: const InputDecoration(labelText: 'Subscription group', border: OutlineInputBorder()), items: [for (final row in widget.groups) DropdownMenuItem(value: row['id']?.toString(), child: Text('${row['name'] ?? row['group_key']} (${row['group_key']})', overflow: TextOverflow.ellipsis))], onChanged: (value) => setState(() => _groupId = value)),
              _field('tier_key', label: 'Tier key (optional)'),
            ]),
            const SizedBox(height: 18), _section('Price and term', 'The amount is entered in major currency units and saved as minor units.'),
            _grid([
              TextFormField(controller: _fields['price'], keyboardType: const TextInputType.numberWithOptions(decimal: true), validator: (value) => _numericValidator(value, required: true), decoration: InputDecoration(labelText: 'Price', suffixText: _currency, border: const OutlineInputBorder())),
              DropdownButtonFormField<String>(value: _currency, isExpanded: true, decoration: const InputDecoration(labelText: 'Currency', border: OutlineInputBorder()), items: [if (!widget.currencies.any((row) => row['code']?.toString().toUpperCase() == _currency)) DropdownMenuItem(value: _currency, child: Text(_currency)), for (final row in widget.currencies) DropdownMenuItem(value: row['code']?.toString().toUpperCase(), child: Text(row['code']?.toString().toUpperCase() ?? ''))], onChanged: (value) => setState(() => _currency = value ?? _currency)),
              DropdownButtonFormField<String>(value: _interval, decoration: const InputDecoration(labelText: 'Billing interval', border: OutlineInputBorder()), items: const [DropdownMenuItem(value: 'weekly', child: Text('Weekly')), DropdownMenuItem(value: 'monthly', child: Text('Monthly')), DropdownMenuItem(value: 'quarterly', child: Text('Quarterly'))], onChanged: (value) => setState(() => _interval = value ?? 'monthly')),
              _field('duration_count', label: 'Duration count', number: true, required: true),
              _field('discount', label: 'Discount (%)', number: true),
              _field('bonus', label: 'Welcome bonus (fixed)', number: true),
            ]),
            const SizedBox(height: 18), _section('Task and payout rules', groupA ? 'Group A earns per ad share and uses a daily revenue cap.' : 'Set app review limits and payout rules for this package.'),
            _grid([
              _field('max_review_tasks', label: 'Maximum review tasks', number: true),
              _field('max_ad_shares', label: 'Maximum ad shares', number: true),
              _field('minimum_withdrawal', label: 'Minimum withdrawal', number: true, suffix: _currency),
              _field('daily_revenue', label: 'Daily revenue cap', number: true, suffix: _currency),
              if (!groupA) _field('task_reward', label: 'Task reward per review', number: true, suffix: _currency),
              if (!groupA) _field('daily_task_limit', label: 'Daily task limit', number: true),
              _field('eligible_app_store', label: 'Eligible store (optional)'),
              _field('eligible_app_category', label: 'Eligible category (optional)'),
            ]),
            const SizedBox(height: 10),
            Wrap(spacing: 8, runSpacing: 8, children: [for (final day in const ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday']) FilterChip(label: Text(day), selected: _withdrawalDays.contains(day), onSelected: (selected) => setState(() { if (selected) { _withdrawalDays.add(day); } else { _withdrawalDays.remove(day); } }))]),
            const SizedBox(height: 18), _section('Referral and display', 'Percentages are configured per plan.'),
            _grid([
              _field('subscription_referral_percent', label: 'Subscription referral (%)', number: true),
              _field('task_referral_percent', label: 'Task referral (%)', number: true),
            ]),
            const SizedBox(height: 12),
            TextFormField(controller: _fields['features'], minLines: 3, maxLines: 8, decoration: const InputDecoration(labelText: 'Feature details (JSON object)', hintText: '{"daily_revenue_label":"10"}', border: OutlineInputBorder())),
            SwitchListTile.adaptive(contentPadding: EdgeInsets.zero, title: const Text('Plan available for purchase'), value: _active, onChanged: (value) => setState(() => _active = value)),
            SwitchListTile.adaptive(contentPadding: EdgeInsets.zero, title: const Text('Featured / special plan'), value: _special, onChanged: (value) => setState(() => _special = value)),
            SwitchListTile.adaptive(contentPadding: EdgeInsets.zero, title: const Text('Has package add-on'), value: _addons, onChanged: (value) => setState(() => _addons = value)),
            if (_error != null) _ErrorBanner(message: _error!),
          ]))),
          const Divider(height: 1),
          Padding(padding: const EdgeInsets.all(14), child: Row(children: [Expanded(child: OutlinedButton(onPressed: () => Navigator.pop(context), child: const Text('Cancel'))), const SizedBox(width: 10), Expanded(child: FilledButton.icon(onPressed: _submit, icon: Icon(_editing ? Icons.save_rounded : Icons.add_rounded), label: Text(_editing ? 'Save changes' : 'Create plan')))])),
        ]),
      ),
    );
  }

  Widget _section(String title, String subtitle) => Padding(padding: const EdgeInsets.only(bottom: 10), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(title, style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w800)), Text(subtitle, style: Theme.of(context).textTheme.bodySmall)]));
  Widget _grid(List<Widget> fields) => LayoutBuilder(builder: (context, constraints) { final columns = constraints.maxWidth > 620 ? 2 : 1; final width = (constraints.maxWidth - (columns - 1) * 12) / columns; return Wrap(spacing: 12, runSpacing: 12, children: [for (final field in fields) SizedBox(width: width, child: field)]); });
  Widget _field(String key, {required String label, bool number = false, bool required = false, String? suffix}) => TextFormField(
        controller: _fields[key],
        keyboardType: number ? const TextInputType.numberWithOptions(decimal: true) : null,
        validator: number
            ? (value) => _numericValidator(
                value,
                required: required,
                integer: const {'duration_count', 'max_review_tasks', 'max_ad_shares', 'daily_task_limit'}.contains(key),
              )
            : (required ? _required : null),
        decoration: InputDecoration(labelText: label, suffixText: suffix, border: const OutlineInputBorder()),
      );
}

class _PlanSubscribersDialog extends StatefulWidget {
  const _PlanSubscribersDialog({required this.api, required this.token, required this.plan, required this.map, required this.rows, required this.intValue, required this.money});
  final AuthApi api;
  final String token;
  final Map<String, dynamic> plan;
  final Map<String, dynamic> Function(Object?) map;
  final List<Map<String, dynamic>> Function(Object?) rows;
  final int Function(Object?, [int]) intValue;
  final String Function(Object?, String, [Object?]) money;
  @override
  State<_PlanSubscribersDialog> createState() => _PlanSubscribersDialogState();
}

class _PlanSubscribersDialogState extends State<_PlanSubscribersDialog> {
  final _search = TextEditingController();
  List<Map<String, dynamic>> _rows = [];
  String _status = 'all';
  String? _error;
  bool _loading = false;
  int _page = 1, _lastPage = 1, _total = 0, _generation = 0;
  @override
  void initState() { super.initState(); _load(); }
  @override
  void dispose() { _search.dispose(); super.dispose(); }

  Future<void> _load({int? page}) async {
    final generation = ++_generation;
    final targetPage = page ?? _page;
    setState(() { _loading = true; _error = null; });
    final query = <String, String>{'page': '$targetPage', 'per_page': '20'};
    if (_status != 'all') query['status'] = _status;
    if (_search.text.trim().isNotEmpty) query['search'] = _search.text.trim();
    final qs = query.entries.map((e) => '${Uri.encodeQueryComponent(e.key)}=${Uri.encodeQueryComponent(e.value)}').join('&');
    try {
      final response = await widget.api.get('admin/subscription-plans/${widget.plan['id']}/subscribers?$qs', widget.token);
      if (!mounted || generation != _generation) return;
      final root = widget.map(response), data = widget.map(widget.map(response)['data']);
      setState(() {
        _rows = widget.rows(response);
        _page = widget.intValue(data['current_page'], targetPage);
        _lastPage = widget.intValue(data['last_page'], 1).clamp(1, 100000).toInt();
        _total = widget.intValue(data['total'], _rows.length);
        _error = root['message']?.toString();
      });
    } on AuthException catch (error) {
      if (mounted && generation == _generation) setState(() => _error = error.message);
    } catch (error) {
      if (mounted && generation == _generation) setState(() => _error = error.toString());
    } finally {
      if (mounted && generation == _generation) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) => Dialog(
        insetPadding: const EdgeInsets.all(14),
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 900, maxHeight: 820),
          child: Column(children: [
            Padding(padding: const EdgeInsets.fromLTRB(18, 14, 8, 12), child: Row(children: [Icon(Icons.people_alt_outlined, color: Theme.of(context).colorScheme.primary), const SizedBox(width: 10), Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text('${widget.plan['name']} subscribers', style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w800)), Text('$_total subscription records', style: Theme.of(context).textTheme.bodySmall)])), IconButton(onPressed: () => Navigator.pop(context), icon: const Icon(Icons.close_rounded))])),
            const Divider(height: 1),
            Padding(padding: const EdgeInsets.all(14), child: Column(children: [TextField(controller: _search, textInputAction: TextInputAction.search, onSubmitted: (_) => _load(page: 1), decoration: InputDecoration(hintText: 'Search name, email or payment reference', prefixIcon: const Icon(Icons.search_rounded), suffixIcon: IconButton(onPressed: () => _load(page: 1), icon: const Icon(Icons.arrow_forward_rounded)), border: const OutlineInputBorder())), const SizedBox(height: 9), SizedBox(height: 40, child: ListView(scrollDirection: Axis.horizontal, children: [for (final status in const ['all', 'active', 'pending', 'expired', 'cancelled', 'paused', 'past_due']) Padding(padding: const EdgeInsets.only(right: 7), child: ChoiceChip(label: Text(status == 'all' ? 'All' : status.replaceAll('_', ' ')), selected: _status == status, onSelected: (_) { setState(() => _status = status); _load(page: 1); }))]))])),
            if (_loading) const LinearProgressIndicator(minHeight: 2),
            if (_error != null) Padding(padding: const EdgeInsets.symmetric(horizontal: 14), child: _ErrorBanner(message: _error!)),
            Expanded(child: _rows.isEmpty ? Center(child: Text(_loading ? 'Loading subscribers…' : 'No subscribers match this filter.')) : ListView.separated(padding: const EdgeInsets.all(14), itemCount: _rows.length, separatorBuilder: (_, __) => const SizedBox(height: 8), itemBuilder: (context, index) => _SubscriberTile(row: _rows[index], map: widget.map, money: widget.money))),
            const Divider(height: 1),
            Padding(padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8), child: Row(children: [Text('Page $_page of $_lastPage'), const Spacer(), IconButton(onPressed: _page > 1 && !_loading ? () => _load(page: _page - 1) : null, icon: const Icon(Icons.chevron_left_rounded)), IconButton(onPressed: _page < _lastPage && !_loading ? () => _load(page: _page + 1) : null, icon: const Icon(Icons.chevron_right_rounded)), IconButton(onPressed: _loading ? null : () => _load(), icon: const Icon(Icons.refresh_rounded))])),
          ]),
        ),
      );
}

class _SubscriberTile extends StatelessWidget {
  const _SubscriberTile({required this.row, required this.map, required this.money});
  final Map<String, dynamic> row;
  final Map<String, dynamic> Function(Object?) map;
  final String Function(Object?, String, [Object?]) money;
  @override
  Widget build(BuildContext context) {
    final user = map(row['user']), payment = map(row['payment_intent']);
    final name = user['full_name'] ?? user['username'] ?? 'User #${row['user_id'] ?? '—'}';
    final status = row['status']?.toString() ?? 'unknown';
    final snapshot = map(row['plan_snapshot']);
    final amount = payment['amount_minor'] ?? snapshot['price_minor'];
    final currency = _text(payment['currency'] ?? snapshot['currency'] ?? row['currency'], 'USD');
    return Card(child: ListTile(isThreeLine: true, leading: CircleAvatar(child: Text(name.toString().isEmpty ? '?' : name.toString()[0].toUpperCase())), title: Text(name.toString(), maxLines: 1, overflow: TextOverflow.ellipsis), subtitle: Text('${user['email'] ?? 'No email'}\n${row['starts_at'] ?? '—'} → ${row['ends_at'] ?? '—'}\n${payment['provider_reference'] ?? 'No payment reference'}', maxLines: 3, overflow: TextOverflow.ellipsis), trailing: Column(mainAxisAlignment: MainAxisAlignment.center, crossAxisAlignment: CrossAxisAlignment.end, children: [Text(status.toUpperCase(), style: TextStyle(color: status == 'active' ? Theme.of(context).colorScheme.primary : Theme.of(context).colorScheme.onSurfaceVariant, fontWeight: FontWeight.w800, fontSize: 11)), const SizedBox(height: 4), Text(money(amount, currency), style: Theme.of(context).textTheme.labelMedium?.copyWith(fontWeight: FontWeight.w700))])));
  }
  String _text(Object? value, [String fallback = '']) => value == null ? fallback : value.toString();
}

class _PlanCard extends StatelessWidget {
  const _PlanCard({
    required this.plan,
    required this.money,
    required this.groupName,
    required this.onEdit,
    required this.onToggle,
    required this.onSubscribers,
  });

  final Map<String, dynamic> plan;
  final String Function(Object?, String, [Object?]) money;
  final String groupName;
  final VoidCallback onEdit;
  final VoidCallback onToggle;
  final VoidCallback onSubscribers;

  int _int(Object? value) =>
      value is num ? value.toInt() : int.tryParse('$value') ?? 0;
  bool _truthy(Object? value) => value == true || value == 1 || value == '1';

  @override
  Widget build(BuildContext context) {
    final active = _truthy(plan['is_active']);
    final special = _truthy(plan['is_special']);
    final addonsEnabled = _truthy(plan['addons']);
    final currency = plan['currency']?.toString() ?? 'USD';
    final addon = plan['package_addon'] is Map
        ? Map<String, dynamic>.from(plan['package_addon'] as Map)
        : <String, dynamic>{};
    final gradient = special
        ? _Palette.berry
        : addonsEnabled
            ? _Palette.mint
            : _Palette.ocean;
    final statusColor = active ? _Palette.green : _Palette.rose;

    return Card(
      elevation: 0,
      margin: EdgeInsets.zero,
      clipBehavior: Clip.antiAlias,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(20),
        side: BorderSide(color: Theme.of(context).colorScheme.outlineVariant),
      ),
      child: InkWell(
        onTap: onEdit,
        child: Padding(
          padding: const EdgeInsets.all(17),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  _BrandMark(
                    icon: Icons.workspace_premium_rounded,
                    gradient: gradient,
                    size: 48,
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          plan['name']?.toString() ?? 'Subscription plan',
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          style: Theme.of(context)
                              .textTheme
                              .titleMedium
                              ?.copyWith(fontWeight: FontWeight.w900),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          '$groupName · ${plan['plan_key'] ?? 'No plan key'}',
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: Theme.of(context)
                              .textTheme
                              .bodySmall
                              ?.copyWith(color: _Palette.muted),
                        ),
                      ],
                    ),
                  ),
                  PopupMenuButton<String>(
                    tooltip: 'Plan actions',
                    onSelected: (action) {
                      switch (action) {
                        case 'edit':
                          onEdit();
                          break;
                        case 'subscribers':
                          onSubscribers();
                          break;
                        case 'toggle':
                          onToggle();
                          break;
                      }
                    },
                    itemBuilder: (_) => [
                      const PopupMenuItem(value: 'edit', child: Text('Edit plan')),
                      const PopupMenuItem(
                        value: 'subscribers',
                        child: Text('View subscribers'),
                      ),
                      PopupMenuItem(
                        value: 'toggle',
                        child: Text(active ? 'Disable plan' : 'Activate plan'),
                      ),
                    ],
                  ),
                ],
              ),
              const SizedBox(height: 12),
              Wrap(
                spacing: 7,
                runSpacing: 7,
                children: [
                  _StatusPill(
                    status: active ? 'Available' : 'Disabled',
                    color: statusColor,
                  ),
                  _MetaChip(
                    icon: Icons.payments_outlined,
                    color: _Palette.indigo,
                    label: money(
                      plan['price_minor'],
                      currency,
                      plan['currency_minor_units'],
                    ),
                  ),
                  if (special)
                    const _MetaChip(
                      icon: Icons.star_rounded,
                      color: _Palette.violet,
                      label: 'Featured plan',
                    ),
                  if (addonsEnabled)
                    const _MetaChip(
                      icon: Icons.extension_rounded,
                      color: _Palette.teal,
                      label: 'Add-ons enabled',
                    ),
                  if (addon.isNotEmpty)
                    _MetaChip(
                      icon: Icons.add_box_outlined,
                      color: _Palette.sky,
                      label: addon['name']?.toString() ?? 'Package add-on',
                    ),
                ],
              ),
              const SizedBox(height: 13),
              Wrap(
                spacing: 14,
                runSpacing: 8,
                children: [
                  _MetaChip(
                    icon: Icons.event_repeat_outlined,
                    color: _Palette.sky,
                    label:
                        '${plan['duration_count'] ?? 1} ${plan['billing_interval'] ?? 'month'}',
                  ),
                  _MetaChip(
                    icon: Icons.people_outline_rounded,
                    color: _Palette.violet,
                    label: '${_int(plan['subscribers_count'])} subscribers',
                  ),
                  _MetaChip(
                    icon: Icons.verified_user_outlined,
                    color: _Palette.green,
                    label:
                        '${_int(plan['active_subscribers_count'])} active users',
                  ),
                  if (plan['minimum_withdrawal_minor'] != null)
                    _MetaChip(
                      icon: Icons.account_balance_wallet_outlined,
                      color: _Palette.orange,
                      label:
                          'Min. withdrawal ${money(plan['minimum_withdrawal_minor'], currency, plan['currency_minor_units'])}',
                    ),
                  if (plan['eligible_app_store'] != null ||
                      plan['eligible_app_category'] != null)
                    _MetaChip(
                      icon: Icons.apps_rounded,
                      color: _Palette.teal,
                      label:
                          '${plan['eligible_app_store'] ?? 'Any store'}${plan['eligible_app_category'] == null ? '' : ' · ${plan['eligible_app_category']}'}',
                    ),
                ],
              ),
              const SizedBox(height: 13),
              const Divider(height: 1),
              const SizedBox(height: 8),
              Row(
                children: [
                  Expanded(
                    child: OutlinedButton.icon(
                      onPressed: onEdit,
                      icon: const Icon(Icons.edit_outlined, size: 17),
                      label: const Text('Edit plan'),
                    ),
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    child: OutlinedButton.icon(
                      onPressed: onSubscribers,
                      icon: const Icon(Icons.people_alt_outlined, size: 17),
                      label: const Text('Subscribers'),
                    ),
                  ),
                  const SizedBox(width: 4),
                  IconButton(
                    tooltip: active ? 'Disable plan' : 'Activate plan',
                    onPressed: onToggle,
                    color: active ? _Palette.rose : _Palette.green,
                    icon: Icon(
                      active
                          ? Icons.pause_circle_outline_rounded
                          : Icons.play_circle_outline_rounded,
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

class _Palette {
  static const Color indigo = Color(0xFF4338CA);
  static const Color violet = Color(0xFF7C3AED);
  static const Color pink = Color(0xFFEC4899);
  static const Color green = Color(0xFF16A34A);
  static const Color teal = Color(0xFF0D9488);
  static const Color amber = Color(0xFFF59E0B);
  static const Color orange = Color(0xFFF97316);
  static const Color sky = Color(0xFF0284C7);
  static const Color rose = Color(0xFFE11D48);
  static const Color muted = Color(0xFF667085);

  static const LinearGradient hero = LinearGradient(
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
    colors: [Color(0xFF3730A3), violet, pink],
  );
  static const LinearGradient mint = LinearGradient(
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
    colors: [Color(0xFF0D9488), Color(0xFF22C55E)],
  );
  static const LinearGradient sunset = LinearGradient(
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
    colors: [amber, orange],
  );
  static const LinearGradient ocean = LinearGradient(
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
    colors: [sky, indigo],
  );
  static const LinearGradient berry = LinearGradient(
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
    colors: [pink, violet],
  );
}

class _BrandMark extends StatelessWidget {
  const _BrandMark({required this.icon, required this.gradient, this.size = 46});
  final IconData icon;
  final LinearGradient gradient;
  final double size;

  @override
  Widget build(BuildContext context) => Container(
        width: size,
        height: size,
        decoration: BoxDecoration(
          gradient: gradient,
          borderRadius: BorderRadius.circular(size * .32),
        ),
        alignment: Alignment.center,
        child: Icon(icon, color: Colors.white, size: size * .5),
      );
}

class _TileRow extends StatelessWidget {
  const _TileRow({required this.children, this.minItemWidth = 150});
  final List<Widget> children;
  final double minItemWidth;

  @override
  Widget build(BuildContext context) => LayoutBuilder(
        builder: (context, constraints) {
          final count = (constraints.maxWidth / (minItemWidth + 12))
              .floor()
              .clamp(1, children.length)
              .toInt();
          final width =
              (constraints.maxWidth - (count - 1) * 12) / count;
          return Wrap(
            spacing: 12,
            runSpacing: 12,
            children: [
              for (final child in children)
                SizedBox(width: width, child: child),
            ],
          );
        },
      );
}

class _StatTile extends StatelessWidget {
  const _StatTile({
    required this.label,
    required this.value,
    required this.icon,
    required this.gradient,
  });
  final String label;
  final String value;
  final IconData icon;
  final LinearGradient gradient;

  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          gradient: gradient,
          borderRadius: BorderRadius.circular(20),
          boxShadow: [
            BoxShadow(
              color: gradient.colors.last.withValues(alpha: .3),
              blurRadius: 16,
              offset: const Offset(0, 8),
            ),
          ],
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(children: [
              Icon(icon, color: Colors.white, size: 18),
              const Spacer(),
              Container(
                width: 6,
                height: 6,
                decoration: BoxDecoration(
                  color: Colors.white.withValues(alpha: .7),
                  shape: BoxShape.circle,
                ),
              ),
            ]),
            const SizedBox(height: 12),
            FittedBox(
              fit: BoxFit.scaleDown,
              alignment: Alignment.centerLeft,
              child: Text(
                value,
                maxLines: 1,
                style: const TextStyle(
                  color: Colors.white,
                  fontSize: 24,
                  fontWeight: FontWeight.w800,
                  height: 1.1,
                ),
              ),
            ),
            const SizedBox(height: 2),
            Text(
              label,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: TextStyle(
                color: Colors.white.withValues(alpha: .9),
                fontSize: 12,
                fontWeight: FontWeight.w600,
              ),
            ),
          ],
        ),
      );
}

class _SearchField extends StatelessWidget {
  const _SearchField({
    required this.controller,
    required this.hint,
    required this.onChanged,
  });
  final TextEditingController controller;
  final String hint;
  final ValueChanged<String> onChanged;

  @override
  Widget build(BuildContext context) => TextField(
        controller: controller,
        onChanged: onChanged,
        textInputAction: TextInputAction.search,
        decoration: InputDecoration(
          hintText: hint,
          isDense: true,
          prefixIcon: const Icon(Icons.search_rounded, size: 20),
          suffixIcon: controller.text.isEmpty
              ? null
              : IconButton(
                  tooltip: 'Clear search',
                  onPressed: () {
                    controller.clear();
                    onChanged('');
                  },
                  icon: const Icon(Icons.close_rounded),
                ),
          filled: true,
          fillColor: Theme.of(context).colorScheme.onSurface.withValues(alpha: .045),
          contentPadding: const EdgeInsets.symmetric(
            horizontal: 14,
            vertical: 14,
          ),
          border: OutlineInputBorder(
            borderRadius: BorderRadius.circular(16),
            borderSide: BorderSide.none,
          ),
          enabledBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(16),
            borderSide: BorderSide.none,
          ),
          focusedBorder: const OutlineInputBorder(
            borderRadius: BorderRadius.all(Radius.circular(16)),
            borderSide: BorderSide(color: _Palette.indigo, width: 1.6),
          ),
        ),
      );
}

class _FilterChip extends StatelessWidget {
  const _FilterChip({
    required this.label,
    required this.count,
    required this.color,
    required this.selected,
    required this.onSelected,
  });
  final String label;
  final int count;
  final Color color;
  final bool selected;
  final VoidCallback onSelected;

  @override
  Widget build(BuildContext context) => Material(
        color: selected ? color : color.withValues(alpha: .08),
        borderRadius: BorderRadius.circular(999),
        child: InkWell(
          borderRadius: BorderRadius.circular(999),
          onTap: onSelected,
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
            child: Row(mainAxisSize: MainAxisSize.min, children: [
              Text(
                label,
                style: TextStyle(
                  color: selected ? Colors.white : color,
                  fontWeight: FontWeight.w700,
                  fontSize: 13,
                ),
              ),
              const SizedBox(width: 6),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 1),
                decoration: BoxDecoration(
                  color: selected
                      ? Colors.white.withValues(alpha: .25)
                      : color.withValues(alpha: .16),
                  borderRadius: BorderRadius.circular(999),
                ),
                child: Text(
                  '$count',
                  style: TextStyle(
                    color: selected ? Colors.white : color,
                    fontSize: 11,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
            ]),
          ),
        ),
      );
}

class _TypeChip extends StatelessWidget {
  const _TypeChip({
    required this.label,
    required this.color,
    required this.selected,
    required this.onSelected,
  });
  final String label;
  final Color color;
  final bool selected;
  final VoidCallback onSelected;

  @override
  Widget build(BuildContext context) => ChoiceChip(
        label: Text(label),
        selected: selected,
        selectedColor: color.withValues(alpha: .16),
        labelStyle: TextStyle(
          color: selected ? color : Theme.of(context).colorScheme.onSurfaceVariant,
          fontWeight: FontWeight.w700,
        ),
        avatar: selected ? Icon(Icons.check_circle_rounded, color: color, size: 17) : null,
        onSelected: (_) => onSelected(),
      );
}

class _MetaChip extends StatelessWidget {
  const _MetaChip({required this.icon, required this.color, required this.label});
  final IconData icon;
  final Color color;
  final String label;

  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
        decoration: BoxDecoration(
          color: color.withValues(alpha: .1),
          borderRadius: BorderRadius.circular(999),
        ),
        child: Row(mainAxisSize: MainAxisSize.min, children: [
          Icon(icon, size: 14, color: color),
          const SizedBox(width: 5),
          Flexible(
            child: Text(
              label,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: TextStyle(color: color, fontSize: 11, fontWeight: FontWeight.w700),
            ),
          ),
        ]),
      );
}

class _StatusPill extends StatelessWidget {
  const _StatusPill({required this.status, required this.color});
  final String status;
  final Color color;

  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 5),
        decoration: BoxDecoration(
          color: color.withValues(alpha: .14),
          borderRadius: BorderRadius.circular(999),
          border: Border.all(color: color.withValues(alpha: .3)),
        ),
        child: Text(
          status.toUpperCase(),
          style: TextStyle(
            color: color,
            fontSize: 10,
            fontWeight: FontWeight.w800,
            letterSpacing: .5,
          ),
        ),
      );
}

class _SignInField extends StatelessWidget {
  const _SignInField({
    required this.controller,
    required this.label,
    required this.icon,
    this.keyboard,
    this.obscure = false,
    this.onSubmitted,
    this.suffix,
  });
  final TextEditingController controller;
  final String label;
  final IconData icon;
  final TextInputType? keyboard;
  final bool obscure;
  final ValueChanged<String>? onSubmitted;
  final Widget? suffix;

  @override
  Widget build(BuildContext context) => TextField(
        controller: controller,
        keyboardType: keyboard,
        obscureText: obscure,
        onSubmitted: onSubmitted,
        decoration: InputDecoration(
          labelText: label,
          prefixIcon: Icon(icon, size: 20),
          suffixIcon: suffix,
          filled: true,
          fillColor: Theme.of(context).colorScheme.onSurface.withValues(alpha: .04),
          border: OutlineInputBorder(
            borderRadius: BorderRadius.circular(14),
            borderSide: BorderSide.none,
          ),
          enabledBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(14),
            borderSide: BorderSide.none,
          ),
          focusedBorder: const OutlineInputBorder(
            borderRadius: BorderRadius.all(Radius.circular(14)),
            borderSide: BorderSide(color: _Palette.indigo, width: 1.6),
          ),
        ),
      );
}

class _ErrorBanner extends StatelessWidget {
  const _ErrorBanner({required this.message});
  final String message;

  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: _Palette.rose.withValues(alpha: .1),
          borderRadius: BorderRadius.circular(14),
          border: Border.all(color: _Palette.rose.withValues(alpha: .35)),
        ),
        child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
          const Icon(Icons.error_outline_rounded, size: 18, color: _Palette.rose),
          const SizedBox(width: 10),
          Expanded(
            child: Text(
              message,
              style: const TextStyle(
                color: _Palette.rose,
                fontWeight: FontWeight.w600,
                height: 1.35,
              ),
            ),
          ),
        ]),
      );
}

class _GradientButton extends StatelessWidget {
  const _GradientButton({
    required this.label,
    required this.onPressed,
    this.icon,
    this.busy = false,
  });
  final String label;
  final VoidCallback? onPressed;
  final IconData? icon;
  final bool busy;

  @override
  Widget build(BuildContext context) {
    final enabled = onPressed != null;
    final Widget? leading = busy
        ? const SizedBox(
            width: 18,
            height: 18,
            child: CircularProgressIndicator(strokeWidth: 2.2, color: Colors.white),
          )
        : icon == null
            ? null
            : Icon(icon, color: Colors.white, size: 20);
    return Opacity(
      opacity: enabled ? 1 : .55,
      child: DecoratedBox(
        decoration: BoxDecoration(
          gradient: _Palette.hero,
          borderRadius: BorderRadius.circular(16),
          boxShadow: enabled
              ? [
                  BoxShadow(
                    color: _Palette.indigo.withValues(alpha: .35),
                    blurRadius: 18,
                    offset: const Offset(0, 9),
                  ),
                ]
              : null,
        ),
        child: Material(
          color: Colors.transparent,
          child: InkWell(
            onTap: onPressed,
            borderRadius: BorderRadius.circular(16),
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 15),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.center,
                mainAxisSize: MainAxisSize.min,
                children: [
                  if (leading != null) ...[leading, const SizedBox(width: 10)],
                  Flexible(
                    child: Text(
                      label,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        color: Colors.white,
                        fontWeight: FontWeight.w800,
                        fontSize: 15,
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class _GradientFab extends StatelessWidget {
  const _GradientFab({required this.icon, required this.label, required this.onPressed});
  final IconData icon;
  final String label;
  final VoidCallback? onPressed;

  @override
  Widget build(BuildContext context) => Container(
        decoration: BoxDecoration(
          gradient: _Palette.hero,
          borderRadius: BorderRadius.circular(20),
          boxShadow: [
            BoxShadow(
              color: _Palette.violet.withValues(alpha: .45),
              blurRadius: 20,
              offset: const Offset(0, 10),
            ),
          ],
        ),
        child: FloatingActionButton.extended(
          onPressed: onPressed,
          backgroundColor: Colors.transparent,
          elevation: 0,
          highlightElevation: 0,
          icon: Icon(icon, color: Colors.white),
          label: Text(label, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w700)),
        ),
      );
}

class _AppBarAction extends StatelessWidget {
  const _AppBarAction({required this.icon, required this.tooltip, required this.onPressed});
  final IconData icon;
  final String tooltip;
  final VoidCallback? onPressed;

  @override
  Widget build(BuildContext context) => IconButton(
        tooltip: tooltip,
        onPressed: onPressed,
        icon: Icon(icon, color: Colors.white),
        style: IconButton.styleFrom(
          backgroundColor: Colors.white.withValues(alpha: .18),
          shape: const CircleBorder(),
          fixedSize: const Size(38, 38),
        ),
      );
}

class _SectionHeading extends StatelessWidget {
  const _SectionHeading({required this.title, required this.subtitle});
  final String title;
  final String subtitle;

  @override
  Widget build(BuildContext context) => Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(title, style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 15)),
          const SizedBox(height: 3),
          Text(subtitle, style: const TextStyle(color: _Palette.muted, fontSize: 12)),
        ],
      );
}

class _SkeletonList extends StatelessWidget {
  const _SkeletonList();

  @override
  Widget build(BuildContext context) => ListView.builder(
        physics: const NeverScrollableScrollPhysics(),
        padding: const EdgeInsets.all(16),
        itemCount: 3,
        itemBuilder: (_, index) => Container(
          height: 170,
          margin: const EdgeInsets.only(bottom: 14),
          decoration: BoxDecoration(
            color: _Palette.indigo.withValues(alpha: .06),
            borderRadius: BorderRadius.circular(20),
          ),
        ),
      );
}

class _EmptyState extends StatelessWidget {
  const _EmptyState({
    required this.icon,
    required this.gradient,
    required this.title,
    required this.message,
    required this.actionLabel,
    required this.onAction,
  });
  final IconData icon;
  final LinearGradient gradient;
  final String title;
  final String message;
  final String actionLabel;
  final VoidCallback onAction;

  @override
  Widget build(BuildContext context) => Center(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(28),
          child: Column(mainAxisSize: MainAxisSize.min, children: [
            _BrandMark(icon: icon, gradient: gradient, size: 62),
            const SizedBox(height: 16),
            Text(
              title,
              textAlign: TextAlign.center,
              style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w800),
            ),
            const SizedBox(height: 6),
            ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 380),
              child: Text(
                message,
                textAlign: TextAlign.center,
                style: TextStyle(
                  color: Theme.of(context).colorScheme.onSurfaceVariant,
                  height: 1.4,
                ),
              ),
            ),
            const SizedBox(height: 18),
            _GradientButton(
              label: actionLabel,
              icon: Icons.refresh_rounded,
              onPressed: onAction,
            ),
          ]),
        ),
      );
}

ThemeData _adminTheme(BuildContext context) {
  final base = Theme.of(context);
  return base.copyWith(
    colorScheme: ColorScheme.fromSeed(
      seedColor: _Palette.indigo,
      brightness: base.brightness,
    ),
  );
}
