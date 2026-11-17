import 'package:flutter/material.dart';

import '../core/currency/money.dart';
import '../services/auth_api.dart';

/// Admin console for the package_addons catalog and its user entitlements.
///
/// Expected admin API contract (the reporting endpoints are additive):
/// - GET admin/package-addons?per_page=100 returns Laravel pagination or a list;
///   each add-on may include `plan`, `users_count`, `active_users_count`,
///   `revenue_minor` (or `total_revenue_minor`) and `revenue_currency`.
/// - GET admin/subscription-plans?per_page=100 returns plan IDs/names/keys.
/// - POST admin/package-addons and PATCH admin/package-addons/{id} accept
///   subscription_plan_id, addon_key, name, price_minor, discount, features,
///   currency, billing_interval, duration_count, and is_active.
/// - DELETE admin/package-addons/{id} removes an unused offer; the UI explains
///   that the server should protect packages that still have entitlements.
/// - GET admin/package-addons/{id}/users?per_page={n}&page={n}&search={term}&status={status}
///   returns Laravel pagination; rows include `user`, `payment_intent`, and the
///   UserPackageAddon fields (status, price_minor, currency, features,
///   starts_at, ends_at). The report uses entitlement feature snapshots to show
///   locations and assigned IPs without changing the historical purchase data.
class AdminPackageAddonsScreen extends StatefulWidget {
  const AdminPackageAddonsScreen({super.key, required this.api});

  final AuthApi api;

  @override
  State<AdminPackageAddonsScreen> createState() =>
      _AdminPackageAddonsScreenState();
}

class _AdminPackageAddonsScreenState extends State<AdminPackageAddonsScreen> {
  final _emailController = TextEditingController(text: 'admin@tlstore.test');
  final _passwordController = TextEditingController(text: 'meandmyson');
  final _searchController = TextEditingController();

  String? _token;
  String? _error;
  bool _busy = false;
  bool _obscurePassword = true;
  String _statusFilter = 'all';
  String _sortBy = 'name';
  List<Map<String, dynamic>> _addons = const [];
  List<Map<String, dynamic>> _plans = const [];
  int _requestGeneration = 0;

  @override
  void dispose() {
    _emailController.dispose();
    _passwordController.dispose();
    _searchController.dispose();
    super.dispose();
  }

  Map<String, dynamic> _map(Object? value) => value is Map
      ? value.map<String, dynamic>((key, value) => MapEntry(key.toString(), value))
      : <String, dynamic>{};

  List<Map<String, dynamic>> _rows(Object? response) {
    final root = _map(response);
    final data = root['data'];
    final values = data is List ? data : _map(data)['data'];
    if (values is! List) return const [];
    return values.whereType<Map>().map(_map).toList(growable: false);
  }

  int _int(Object? value, [int fallback = 0]) => value is num
      ? value.toInt()
      : int.tryParse(value?.toString() ?? '') ?? fallback;

  String _text(Object? value, [String fallback = '']) =>
      value == null ? fallback : value.toString();

  bool _enabled(Map<String, dynamic> addon) =>
      addon['is_active'] == true || addon['is_active'] == 1;

  Future<void> _login() async {
    FocusScope.of(context).unfocus();
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final response = await widget.api.postPublic('admin/auth/login', {
        'email': _emailController.text.trim(),
        'password': _passwordController.text,
      });
      final payload = _map(response['data']);
      final token = response['token'] ?? payload['token'] ?? payload['access_token'];
      if (token == null || token.toString().isEmpty) {
        throw const AuthException('Admin login did not return an access token.');
      }
      if (!mounted) return;
      setState(() => _token = token.toString());
      await _load();
    } on AuthException catch (error) {
      if (mounted) setState(() => _error = error.message);
    } catch (error) {
      if (mounted) setState(() => _error = _friendlyError(error));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<List<Map<String, dynamic>>> _loadAllAddons(String token) async {
    final rows = <Map<String, dynamic>>[];
    var page = 1;
    while (page <= 50) {
      final response = await widget.api.get(
        'admin/package-addons?per_page=100&page=$page',
        token,
      );
      rows.addAll(_rows(response));
      final root = _map(response);
      final envelope = _map(root['data']);
      final meta = _map(root['meta']);
      final currentPage = _int(envelope['current_page'] ?? meta['current_page'], page);
      final lastPage = _int(envelope['last_page'] ?? meta['last_page'], 1);
      if (lastPage <= currentPage || _rows(response).isEmpty) break;
      page = currentPage + 1;
    }
    return rows;
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
      // The catalog stays usable even if an optional plan-list endpoint has not
      // been added yet; the editor offers a manual plan ID fallback in that case.
      final addonFuture = _loadAllAddons(token);
      final plansFuture = widget.api
          .get('admin/subscription-plans?per_page=100', token)
          .onError((_, __) => <String, dynamic>{});
      final addons = await addonFuture;
      final planResponse = await plansFuture;
      if (!mounted || generation != _requestGeneration) return;
      final plans = _rows(planResponse);
      setState(() {
        _addons = addons;
        _plans = plans;
      });
    } on AuthException catch (error) {
      if (mounted && generation == _requestGeneration) {
        setState(() => _error = error.message);
      }
    } catch (error) {
      if (mounted && generation == _requestGeneration) {
        setState(() => _error = _friendlyError(error));
      }
    } finally {
      if (mounted && generation == _requestGeneration) {
        setState(() => _busy = false);
      }
    }
  }

  String _friendlyError(Object error) => error is AuthException
      ? error.message
      : 'Something went wrong. Please try again.';

  String _money(Object? minor, Object? currency, [Object? minorUnits]) {
    final amount = minor is num ? minor.toDouble() : double.tryParse('$minor');
    if (amount == null) return '—';
    final code = _text(currency, 'USD').toUpperCase();
    final decimals = minorUnits is num
        ? minorUnits.toInt().clamp(0, 4).toInt()
        : _currencyMinorUnits(code);
    final divisor = switch (decimals) {
      0 => 1,
      1 => 10,
      2 => 100,
      3 => 1000,
      _ => 10000,
    };
    return '$code ${formatGroupedNumber(amount / divisor, decimals)}';
  }

  int _currencyMinorUnits(String code) {
    if (const {'JPY', 'KRW', 'UGX', 'XAF', 'XOF'}.contains(code)) return 0;
    if (const {'BHD', 'IQD', 'JOD', 'KWD', 'LYD', 'OMR', 'TND'}.contains(code)) return 3;
    if (code == 'CLF') return 4;
    return 2;
  }

  DateTime? _date(Object? value) => DateTime.tryParse(_text(value))?.toLocal();

  String _dateLabel(Object? value) {
    final date = _date(value);
    if (date == null) return '—';
    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    return '${months[date.month - 1]} ${date.day}, ${date.year}';
  }

  List<Map<String, dynamic>> get _visibleAddons {
    final query = _searchController.text.trim().toLowerCase();
    final filtered = _addons.where((addon) {
      final enabled = _enabled(addon);
      final statusMatch = switch (_statusFilter) {
        'active' => enabled,
        'inactive' => !enabled,
        _ => true,
      };
      final plan = _map(addon['plan']);
      final queryMatch = query.isEmpty || [
        addon['name'], addon['addon_key'], addon['id'], plan['name'], plan['plan_key'],
      ].any((value) => _text(value).toLowerCase().contains(query));
      return statusMatch && queryMatch;
    }).toList();
    filtered.sort((a, b) {
      if (_sortBy == 'price') return _int(b['price_minor']).compareTo(_int(a['price_minor']));
      if (_sortBy == 'subscribers') return _int(b['active_users_count'] ?? b['active_entitlements_count']).compareTo(_int(a['active_users_count'] ?? a['active_entitlements_count']));
      return _text(a['name']).toLowerCase().compareTo(_text(b['name']).toLowerCase());
    });
    return filtered;
  }

  int _aggregate(String key, {String? alternative}) => _addons.fold<int>(
        0,
        (sum, row) => sum + _int(row[key] ?? (alternative == null ? null : row[alternative])),
      );

  String _revenueSummary() {
    final totals = <String, int>{};
    final units = <String, Object?>{};
    for (final addon in _addons) {
      final amount = addon['revenue_minor'] ??
          addon['total_revenue_minor'] ??
          addon['gross_revenue_minor'];
      if (amount == null) continue;
      final currency = _text(
        addon['revenue_currency'] ?? addon['currency'],
        'USD',
      ).toUpperCase();
      totals.update(currency, (value) => value + _int(amount), ifAbsent: () => _int(amount));
      units[currency] ??= addon['currency_minor_units'];
    }
    if (totals.isEmpty) return 'Not reported';
    return totals.entries
        .map((entry) => _money(entry.value, entry.key, units[entry.key]))
        .join(' · ');
  }

  Future<void> _openEditor({Map<String, dynamic>? addon}) async {
    final token = _token;
    if (token == null) return;
    final result = await showDialog<Map<String, dynamic>>(
      context: context,
      barrierDismissible: false,
      builder: (_) => Theme(
        data: _adminTheme(context),
        child: _AddonEditorDialog(
          addon: addon,
          plans: _plans,
          map: _map,
          intValue: _int,
        ),
      ),
    );
    if (result == null || !mounted) return;
    final messenger = ScaffoldMessenger.of(context);
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      if (addon == null) {
        await widget.api.post('admin/package-addons', token, result);
      } else {
        await widget.api.patch('admin/package-addons/${addon['id']}', token, result);
      }
      await _load();
      if (!mounted) return;
      messenger.showSnackBar(SnackBar(
        content: Text(addon == null ? 'Add-on package created.' : 'Add-on package updated.'),
        behavior: SnackBarBehavior.floating,
      ));
    } on AuthException catch (error) {
      if (mounted) setState(() => _error = error.message);
    } catch (error) {
      if (mounted) setState(() => _error = _friendlyError(error));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _deleteAddon(Map<String, dynamic> addon) async {
    final id = addon['id'];
    final activeBuyers = _int(addon['active_users_count'] ?? addon['active_entitlements_count']);
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => Theme(
        data: _adminTheme(context),
        child: AlertDialog(
        icon: const Icon(Icons.delete_outline_rounded, color: _AdminPalette.rose),
        title: const Text('Delete this add-on?'),
        content: Text(
          activeBuyers > 0
              ? '${addon['name']} currently has $activeBuyers active user entitlements. The server may reject deletion to protect those records; disable it instead to prevent new purchases.'
              : 'Delete ${addon['name']} from the package catalog? This action may be blocked if purchase records depend on it.',
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(dialogContext, false), child: const Text('Keep add-on')),
          FilledButton.tonalIcon(
            onPressed: () => Navigator.pop(dialogContext, true),
            icon: const Icon(Icons.delete_outline_rounded),
            label: const Text('Delete'),
          ),
        ],
        ),
      ),
    );
    if (confirmed != true || !mounted || _token == null) return;
    final messenger = ScaffoldMessenger.of(context);
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await widget.api.delete('admin/package-addons/$id', _token!);
      await _load();
      if (!mounted) return;
      messenger.showSnackBar(const SnackBar(content: Text('Add-on package deleted.')));
    } on AuthException catch (error) {
      if (mounted) setState(() => _error = error.message);
    } catch (error) {
      if (mounted) setState(() => _error = _friendlyError(error));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _showUsers(Map<String, dynamic> addon) async {
    final token = _token;
    if (token == null) return;
    await showDialog<void>(
      context: context,
      builder: (_) => Theme(
        data: _adminTheme(context),
        child: _AddonUsersDialog(
          api: widget.api,
          token: token,
          addon: addon,
          map: _map,
          rowsFrom: _rows,
          intValue: _int,
          money: _money,
          dateLabel: _dateLabel,
        ),
      ),
    );
  }

  void _logout() {
    ++_requestGeneration;
    _passwordController.clear();
    setState(() {
      _token = null;
      _addons = const [];
      _plans = const [];
      _error = null;
    });
  }

  @override
  Widget build(BuildContext context) => Theme(
        data: _adminTheme(context),
        child: Builder(
          builder: (context) => _token == null
              ? _loginPage(context)
              : _reportPage(context),
        ),
      );

  Widget _loginPage(BuildContext context) => Scaffold(
        body: DecoratedBox(
          decoration: const BoxDecoration(gradient: _AdminPalette.hero),
          child: SafeArea(
            child: Center(
              child: SingleChildScrollView(
                padding: const EdgeInsets.all(24),
                child: ConstrainedBox(
                  constraints: const BoxConstraints(maxWidth: 440),
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Container(
                        width: 76,
                        height: 76,
                        decoration: BoxDecoration(
                          color: Colors.white.withOpacity(.18),
                          shape: BoxShape.circle,
                          border: Border.all(color: Colors.white.withOpacity(.35)),
                        ),
                        child: const Icon(
                          Icons.extension_rounded,
                          size: 38,
                          color: Colors.white,
                        ),
                      ),
                      const SizedBox(height: 18),
                      const Text(
                        'Package add-ons console',
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
                        'Manage package coverage, pricing and customer entitlements.',
                        textAlign: TextAlign.center,
                        style: TextStyle(
                          color: Colors.white.withOpacity(.85),
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
                              color: Colors.black.withOpacity(.22),
                              blurRadius: 30,
                              offset: const Offset(0, 18),
                            ),
                          ],
                        ),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.stretch,
                          children: [
                            TextField(
                              controller: _emailController,
                              keyboardType: TextInputType.emailAddress,
                              textInputAction: TextInputAction.next,
                              decoration: const InputDecoration(
                                labelText: 'Admin email',
                                prefixIcon: Icon(Icons.alternate_email_rounded),
                              ),
                            ),
                            const SizedBox(height: 12),
                            TextField(
                              controller: _passwordController,
                              obscureText: _obscurePassword,
                              onSubmitted: (_) { if (!_busy) _login(); },
                              decoration: InputDecoration(
                                labelText: 'Password',
                                prefixIcon: const Icon(Icons.lock_outline_rounded),
                                suffixIcon: IconButton(
                                  tooltip: _obscurePassword ? 'Show password' : 'Hide password',
                                  onPressed: () => setState(() => _obscurePassword = !_obscurePassword),
                                  icon: Icon(_obscurePassword ? Icons.visibility_outlined : Icons.visibility_off_outlined),
                                ),
                              ),
                            ),
                            if (_error != null) ...[
                              const SizedBox(height: 14),
                              _InlineNotice(text: _error!, isError: true),
                            ],
                            const SizedBox(height: 18),
                            _PackageGradientButton(
                              label: _busy ? 'Signing in…' : 'Sign in',
                              icon: _busy ? null : Icons.arrow_forward_rounded,
                              busy: _busy,
                              onPressed: _busy ? null : _login,
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(height: 18),
                      Text(
                        'Sign in with an admin account to manage package add-ons.',
                        style: TextStyle(
                          color: Colors.white.withOpacity(.8),
                          fontSize: 12,
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

  Widget _reportPage(BuildContext context) {
    final enabled = _addons.where(_enabled).length;
    final activeBuyers = _aggregate(
      'active_users_count',
      alternative: 'active_entitlements_count',
    );
    final users = _aggregate('total_users_count', alternative: 'users_count');
    final filtered = _visibleAddons;
    return Scaffold(
      backgroundColor: Theme.of(context).colorScheme.surface,
      floatingActionButton: _PackageGradientFab(
        icon: Icons.add_rounded,
        label: 'Create add-on',
        onPressed: _busy ? null : () => _openEditor(),
      ),
      body: RefreshIndicator(
        color: Colors.white,
        backgroundColor: _AdminPalette.indigo,
        onRefresh: _load,
        child: CustomScrollView(
          physics: const AlwaysScrollableScrollPhysics(),
          slivers: [
            SliverAppBar(
              pinned: true,
              elevation: 0,
              backgroundColor: _AdminPalette.indigo,
              foregroundColor: Colors.white,
              flexibleSpace: const DecoratedBox(
                decoration: BoxDecoration(gradient: _AdminPalette.hero),
              ),
              title: const Text(
                'Package add-ons',
                style: TextStyle(
                  color: Colors.white,
                  fontWeight: FontWeight.w800,
                  letterSpacing: -.3,
                ),
              ),
              actions: [
                _PackageAppBarAction(
                  icon: Icons.refresh_rounded,
                  tooltip: 'Refresh add-ons',
                  onPressed: _busy ? null : _load,
                ),
                _PackageAppBarAction(
                  icon: Icons.logout_rounded,
                  tooltip: 'Sign out',
                  onPressed: _logout,
                ),
                const SizedBox(width: 4),
              ],
            ),
            if (_busy && _addons.isNotEmpty)
              const SliverToBoxAdapter(
                child: LinearProgressIndicator(minHeight: 2),
              ),
            SliverToBoxAdapter(
              child: Padding(
                padding: const EdgeInsets.fromLTRB(16, 16, 16, 4),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    _PackageTileRow(
                      minItemWidth: 150,
                      children: [
                        _PackageStatTile(
                          label: 'Catalog packages',
                          value: '${_addons.length}',
                          icon: Icons.widgets_rounded,
                          gradient: _AdminPalette.ocean,
                        ),
                        _PackageStatTile(
                          label: 'Available',
                          value: '$enabled',
                          icon: Icons.check_circle_outline_rounded,
                          gradient: _AdminPalette.mint,
                        ),
                        _PackageStatTile(
                          label: 'Active entitlements',
                          value: '$activeBuyers',
                          icon: Icons.people_alt_outlined,
                          gradient: _AdminPalette.sunset,
                        ),
                        _PackageStatTile(
                          label: 'Recorded users',
                          value: '$users',
                          icon: Icons.receipt_long_outlined,
                          gradient: _AdminPalette.berry,
                        ),
                        _PackageStatTile(
                          label: 'Reported revenue',
                          value: _revenueSummary(),
                          icon: Icons.payments_outlined,
                          gradient: _AdminPalette.slate,
                        ),
                      ],
                    ),
                    const SizedBox(height: 14),
                    _filters(),
                    if (_error != null && _addons.isNotEmpty) ...[
                      const SizedBox(height: 10),
                      _InlineNotice(text: _error!, isError: true),
                    ],
                  ],
                ),
              ),
            ),
            if (_busy && _addons.isEmpty)
              const SliverFillRemaining(
                hasScrollBody: false,
                child: Center(child: CircularProgressIndicator()),
              )
            else if (_error != null && _addons.isEmpty)
              SliverFillRemaining(
                hasScrollBody: false,
                child: _PackageEmptyState(
                  icon: Icons.cloud_off_rounded,
                  gradient: _AdminPalette.sunset,
                  title: 'Could not load add-ons',
                  message: _error!,
                  actionLabel: 'Try again',
                  onAction: _load,
                ),
              )
            else if (filtered.isEmpty)
              SliverFillRemaining(
                hasScrollBody: false,
                child: _PackageEmptyState(
                  icon: _addons.isEmpty
                      ? Icons.extension_outlined
                      : Icons.search_off_rounded,
                  gradient: _AdminPalette.berry,
                  title: _addons.isEmpty
                      ? 'No package add-ons yet'
                      : 'Nothing matches that search',
                  message: _addons.isEmpty
                      ? 'Create an add-on package to offer extra proxy coverage.'
                      : 'Try another keyword or clear the availability filter.',
                  actionLabel: _addons.isEmpty ? 'Create add-on' : 'Clear filters',
                  onAction: _addons.isEmpty
                      ? () => _openEditor()
                      : () => setState(() {
                          _searchController.clear();
                          _statusFilter = 'all';
                        }),
                ),
              )
            else
              SliverPadding(
                padding: const EdgeInsets.fromLTRB(16, 12, 16, 120),
                sliver: SliverList.separated(
                  itemCount: filtered.length,
                  separatorBuilder: (_, __) => const SizedBox(height: 14),
                  itemBuilder: (context, index) {
                    final addon = filtered[index];
                    return _AddonCard(
                      addon: addon,
                      money: _money,
                      intValue: _int,
                      onEdit: () => _openEditor(addon: addon),
                      onDelete: () => _deleteAddon(addon),
                      onUsers: () => _showUsers(addon),
                    );
                  },
                ),
              ),
          ],
        ),
      ),
    );
  }

  Widget _filters() => LayoutBuilder(builder: (context, constraints) {
        final search = TextField(
          controller: _searchController,
          textInputAction: TextInputAction.search,
          onChanged: (_) => setState(() {}),
          decoration: InputDecoration(
            hintText: 'Search add-on, key or plan',
            isDense: true,
            prefixIcon: const Icon(Icons.search_rounded, size: 20),
            suffixIcon: _searchController.text.isEmpty
                ? null
                : IconButton(
                    tooltip: 'Clear search',
                    onPressed: () {
                      _searchController.clear();
                      setState(() {});
                    },
                    icon: const Icon(Icons.close_rounded),
                  ),
            filled: true,
            fillColor: Theme.of(context).colorScheme.onSurface.withValues(alpha: .045),
            contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
            border: OutlineInputBorder(
              borderRadius: BorderRadius.circular(16),
              borderSide: BorderSide.none,
            ),
            enabledBorder: OutlineInputBorder(
              borderRadius: BorderRadius.circular(16),
              borderSide: BorderSide.none,
            ),
            focusedBorder: OutlineInputBorder(
              borderRadius: BorderRadius.circular(16),
              borderSide: const BorderSide(color: _AdminPalette.indigo, width: 1.6),
            ),
          ),
        );
        final activeCount = _addons.where(_enabled).length;
        final controls = Wrap(
          spacing: 8,
          runSpacing: 8,
          children: [
            _PackageFilterChip(
              label: 'All',
              count: _addons.length,
              color: _AdminPalette.indigo,
              selected: _statusFilter == 'all',
              onSelected: () => setState(() => _statusFilter = 'all'),
            ),
            _PackageFilterChip(
              label: 'Available',
              count: activeCount,
              color: _AdminPalette.green,
              selected: _statusFilter == 'active',
              onSelected: () => setState(() => _statusFilter = 'active'),
            ),
            _PackageFilterChip(
              label: 'Disabled',
              count: _addons.length - activeCount,
              color: _AdminPalette.amber,
              selected: _statusFilter == 'inactive',
              onSelected: () => setState(() => _statusFilter = 'inactive'),
            ),
            PopupMenuButton<String>(
              initialValue: _sortBy,
              tooltip: 'Sort packages',
              onSelected: (value) => setState(() => _sortBy = value),
              itemBuilder: (_) => const [
                PopupMenuItem(value: 'name', child: Text('Sort: Name')),
                PopupMenuItem(value: 'price', child: Text('Sort: Price')),
                PopupMenuItem(value: 'subscribers', child: Text('Sort: Active users')),
              ],
              child: Chip(
                avatar: const Icon(Icons.sort_rounded, size: 18),
                label: Text('Sort: ${_sortBy[0].toUpperCase()}${_sortBy.substring(1)}'),
              ),
            ),
          ],
        );
        if (constraints.maxWidth < 800) {
          return Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [search, const SizedBox(height: 10), controls],
          );
        }
        return Row(
          children: [Expanded(child: search), const SizedBox(width: 12), controls],
        );
      });
}

class _AddonEditorDialog extends StatefulWidget {
  const _AddonEditorDialog({required this.addon, required this.plans, required this.map, required this.intValue});
  final Map<String, dynamic>? addon;
  final List<Map<String, dynamic>> plans;
  final Map<String, dynamic> Function(Object?) map;
  final int Function(Object?, [int]) intValue;
  @override
  State<_AddonEditorDialog> createState() => _AddonEditorDialogState();
}

class _AddonEditorDialogState extends State<_AddonEditorDialog> {
  late final TextEditingController _name;
  late final TextEditingController _key;
  late final TextEditingController _price;
  late final TextEditingController _discount;
  late final TextEditingController _currency;
  late final TextEditingController _duration;
  late final TextEditingController _planIdController;
  late final TextEditingController _locations;
  late final TextEditingController _countryCodes;
  late final TextEditingController _bandwidth;
  late String? _planId;
  late String _interval;
  late bool _active;
  late bool _sso;
  late bool _phone;
  late bool _emailSupport;
  late bool _dedicatedIps;
  late bool _encryptedTunnel;
  String? _error;

  @override
  void initState() {
    super.initState();
    final addon = widget.addon ?? const <String, dynamic>{};
    final features = widget.map(addon['features']);
    final plan = widget.map(addon['plan']);
    final ids = widget.plans.map((p) => widget.intValue(p['id'])).where((id) => id > 0).map((id) => '$id').toSet();
    final currentPlanId = _textOrNull(addon['subscription_plan_id'] ?? plan['id']);
    _planId = ids.contains(currentPlanId)
        ? currentPlanId
        : (ids.isEmpty ? currentPlanId : ids.first);
    _planIdController = TextEditingController(text: _planId ?? '');
    _name = TextEditingController(text: _textOrNull(addon['name']) ?? '');
    _key = TextEditingController(text: _textOrNull(addon['addon_key']) ?? '');
    _currency = TextEditingController(text: _textOrNull(addon['currency']) ?? 'USD');
    final minorUnits = widget.intValue(addon['currency_minor_units'], 2);
    final divisor = minorUnits == 0 ? 1 : minorUnits == 1 ? 10 : minorUnits == 3 ? 1000 : minorUnits == 4 ? 10000 : 100;
    _price = TextEditingController(text: (widget.intValue(addon['price_minor']) / divisor).toStringAsFixed(minorUnits.clamp(0, 4).toInt()));
    _discount = TextEditingController(text: _textOrNull(addon['discount']) ?? '0');
    _duration = TextEditingController(text: _textOrNull(addon['duration_count']) ?? '1');
    _locations = TextEditingController(text: _stringList(features['location']).join(', '));
    _countryCodes = TextEditingController(text: _stringList(features['country_code']).join(', '));
    _bandwidth = TextEditingController(text: (_number(features['bandwidth']) / 1000000000).toStringAsFixed(2));
    _interval = _textOrNull(addon['billing_interval']) ?? 'monthly';
    if (!const ['weekly', 'monthly', 'quarterly', 'yearly'].contains(_interval)) _interval = 'monthly';
    _active = addon['is_active'] == true || addon['is_active'] == 1;
    _sso = features['SSO_and_SCIM'] == true || features['SSO_and_SCIM'] == 1;
    _phone = features['phone_no'] == true || features['phone_no'] == 1;
    _emailSupport = features['email_support'] == true || features['email_support'] == 1;
    _dedicatedIps = features['dedicated_ips'] == true || features['dedicated_ips'] == 1;
    _encryptedTunnel = features['encrypted_tunnel'] == true || features['encrypted_tunnel'] == 1;
  }

  String? _textOrNull(Object? value) => value == null ? null : value.toString();
  double _number(Object? value) => value is num ? value.toDouble() : double.tryParse('$value') ?? 0;
  List<String> _stringList(Object? value) => value is List ? value.map((e) => e.toString()).toList() : value is String ? value.split(',').map((e) => e.trim()).where((e) => e.isNotEmpty).toList() : const [];

  @override
  void dispose() {
    _name.dispose(); _key.dispose(); _price.dispose(); _discount.dispose(); _currency.dispose(); _duration.dispose(); _planIdController.dispose(); _locations.dispose(); _countryCodes.dispose(); _bandwidth.dispose();
    super.dispose();
  }

  void _submit() {
    final name = _name.text.trim();
    final key = _key.text.trim().toLowerCase();
    final price = double.tryParse(_price.text.trim());
    final discount = double.tryParse(_discount.text.trim());
    final duration = int.tryParse(_duration.text.trim());
    final currency = _currency.text.trim().toUpperCase();
    if (name.isEmpty || key.isEmpty || !RegExp(r'^[a-z0-9][a-z0-9_-]*$').hasMatch(key)) {
      setState(() => _error = 'Enter a name and a package key using letters, numbers, underscores, or hyphens.');
      return;
    }
    final planId = _planId ?? _planIdController.text.trim();
    if (int.tryParse(planId) == null) {
      setState(() => _error = 'Choose a valid subscription plan.');
      return;
    }
    if (price == null || price < 0 || discount == null || discount < 0 || discount > 100 || duration == null || duration < 1 || duration > 120 || !RegExp(r'^[A-Z]{3}$').hasMatch(currency)) {
      setState(() => _error = 'Check the price, discount (0–100), currency, and duration (1–120).');
      return;
    }
    final bandwidthGb = double.tryParse(_bandwidth.text.trim());
    if (bandwidthGb == null || bandwidthGb < 0 || bandwidthGb > 100000) {
      setState(() => _error = 'Bandwidth must be a non-negative number of GB.');
      return;
    }
    final units = _currencyMinorUnits(currency);
    final multiplier = units == 0 ? 1 : units == 1 ? 10 : units == 3 ? 1000 : units == 4 ? 10000 : 100;
    final featureMap = <String, dynamic>{
      'bandwidth': (bandwidthGb * 1000000000).round(),
      'SSO_and_SCIM': _sso,
      'location': _parseCsv(_locations.text),
      'phone_no': _phone,
      'email_support': _emailSupport,
      'dedicated_ips': _dedicatedIps,
      'encrypted_tunnel': _encryptedTunnel,
      'country_code': _parseCsv(_countryCodes.text).map((value) => value.toUpperCase()).toList(),
    };
    Navigator.pop(context, <String, dynamic>{
      'subscription_plan_id': int.parse(planId),
      'addon_key': key,
      'name': name,
      'price_minor': (price * multiplier).round(),
      'discount': discount,
      'features': featureMap,
      'currency': currency,
      'billing_interval': _interval,
      'duration_count': duration,
      'is_active': _active,
    });
  }

  int _currencyMinorUnits(String code) {
    if (const {'JPY', 'KRW', 'UGX', 'XAF', 'XOF'}.contains(code)) return 0;
    if (const {'BHD', 'IQD', 'JOD', 'KWD', 'LYD', 'OMR', 'TND'}.contains(code)) return 3;
    if (code == 'CLF') return 4;
    return 2;
  }
  List<String> _parseCsv(String value) => value.split(',').map((part) => part.trim()).where((part) => part.isNotEmpty).toList(growable: false);

  @override
  Widget build(BuildContext context) {
    final editing = widget.addon != null;
    final viewInsets = MediaQuery.viewInsetsOf(context);
    return Dialog(
      insetPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 20),
      child: ConstrainedBox(
        constraints: const BoxConstraints(maxWidth: 760, maxHeight: 900),
        child: Padding(
          padding: EdgeInsets.only(bottom: viewInsets.bottom),
          child: Column(mainAxisSize: MainAxisSize.min, children: [
            Padding(padding: const EdgeInsets.fromLTRB(24, 20, 18, 12), child: Row(children: [const _BrandMark(icon: Icons.tune_rounded, gradient: _AdminPalette.ocean, size: 44), const SizedBox(width: 12), Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(editing ? 'Edit package add-on' : 'Create package add-on', style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w900)), const Text('Catalog settings and customer-facing proxy coverage')])), IconButton(tooltip: 'Close', onPressed: () => Navigator.pop(context), icon: const Icon(Icons.close_rounded))])),
            const Divider(height: 1),
            Expanded(child: SingleChildScrollView(padding: const EdgeInsets.fromLTRB(24, 18, 24, 18), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              _SectionHeading(title: 'Package identity', subtitle: 'Connect this add-on to a subscription plan.'),
              const SizedBox(height: 12),
              _ResponsiveFields(children: [
                _LabeledField(label: 'Display name', child: TextField(controller: _name, textCapitalization: TextCapitalization.words, decoration: const InputDecoration(hintText: 'Playstore proxy access'))),
                _LabeledField(label: 'Package key', child: TextField(controller: _key, textCapitalization: TextCapitalization.none, decoration: const InputDecoration(hintText: 'playstore'))),
                _LabeledField(
                  label: 'Subscription plan',
                  child: widget.plans.isEmpty
                      ? TextField(
                          controller: _planIdController,
                          keyboardType: TextInputType.number,
                          decoration: const InputDecoration(
                            prefixIcon: Icon(Icons.inventory_2_outlined),
                            labelText: 'Plan ID',
                            helperText: 'Plan list is unavailable; enter subscription_plan_id.',
                          ),
                        )
                      : DropdownButtonFormField<String>(
                          value: _planId,
                          isExpanded: true,
                          decoration: const InputDecoration(
                            prefixIcon: Icon(Icons.inventory_2_outlined),
                          ),
                          items: [
                            for (final plan in widget.plans)
                              DropdownMenuItem(
                                value: '${plan['id']}',
                                child: Text(
                                  '${plan['name'] ?? plan['plan_key'] ?? 'Plan ${plan['id']}'}',
                                  overflow: TextOverflow.ellipsis,
                                ),
                              ),
                          ],
                          onChanged: (value) => setState(() {
                            _planId = value;
                            _planIdController.text = value ?? '';
                          }),
                        ),
                ),
                _LabeledField(label: 'Currency code', child: TextField(controller: _currency, readOnly: true, decoration: const InputDecoration(hintText: 'USD', helperText: 'Package add-ons are priced in USD.'))),
              ]),
              const SizedBox(height: 20),
              _SectionHeading(title: 'Price and term', subtitle: 'Values are saved in minor currency units, as expected by package_addons.'),
              const SizedBox(height: 12),
              _ResponsiveFields(children: [
                _LabeledField(label: 'Price per term', child: TextField(controller: _price, keyboardType: const TextInputType.numberWithOptions(decimal: true), decoration: InputDecoration(prefixIcon: const Icon(Icons.payments_outlined), suffixText: _currency.text.toUpperCase()))),
                _LabeledField(label: 'Discount', child: TextField(controller: _discount, keyboardType: const TextInputType.numberWithOptions(decimal: true), decoration: const InputDecoration(prefixIcon: Icon(Icons.discount_outlined), suffixText: '%'))),
                _LabeledField(label: 'Billing interval', child: DropdownButtonFormField<String>(value: _interval, decoration: const InputDecoration(prefixIcon: Icon(Icons.event_repeat_rounded)), items: const [DropdownMenuItem(value: 'weekly', child: Text('Weekly')), DropdownMenuItem(value: 'monthly', child: Text('Monthly')), DropdownMenuItem(value: 'quarterly', child: Text('Quarterly')), DropdownMenuItem(value: 'yearly', child: Text('Yearly'))], onChanged: (value) => setState(() => _interval = value ?? 'monthly'))),
                _LabeledField(label: 'Term count', child: TextField(controller: _duration, keyboardType: TextInputType.number, decoration: const InputDecoration(prefixIcon: Icon(Icons.numbers_rounded), helperText: 'Example: 4 months'))),
              ]),
              const SizedBox(height: 20),
              _SectionHeading(title: 'Proxy coverage', subtitle: 'These feature values are also copied to a purchaser entitlement when they buy.'),
              const SizedBox(height: 12),
              _ResponsiveFields(children: [
                _LabeledField(label: 'Bandwidth allowance (GB)', child: TextField(controller: _bandwidth, keyboardType: const TextInputType.numberWithOptions(decimal: true), decoration: const InputDecoration(prefixIcon: Icon(Icons.speed_rounded), hintText: '10.24'))),
                _LabeledField(label: 'Locations', child: TextField(controller: _locations, decoration: const InputDecoration(prefixIcon: Icon(Icons.public_rounded), hintText: 'United States, Nigeria', helperText: 'Separate values with commas'))),
                _LabeledField(label: 'Country / region codes', child: TextField(controller: _countryCodes, textCapitalization: TextCapitalization.characters, decoration: const InputDecoration(prefixIcon: Icon(Icons.flag_outlined), hintText: 'US, NG'))),
              ]),
              const SizedBox(height: 10),
              Wrap(spacing: 8, runSpacing: 8, children: [
                _FeatureSwitch(label: 'SSO + SCIM', value: _sso, onChanged: (v) => setState(() => _sso = v)),
                _FeatureSwitch(label: 'Phone support', value: _phone, onChanged: (v) => setState(() => _phone = v)),
                _FeatureSwitch(label: 'Email support', value: _emailSupport, onChanged: (v) => setState(() => _emailSupport = v)),
                _FeatureSwitch(label: 'Dedicated IPs', value: _dedicatedIps, onChanged: (v) => setState(() => _dedicatedIps = v)),
                _FeatureSwitch(label: 'Encrypted tunnel', value: _encryptedTunnel, onChanged: (v) => setState(() => _encryptedTunnel = v)),
              ]),
              const SizedBox(height: 10),
              SwitchListTile.adaptive(contentPadding: EdgeInsets.zero, title: const Text('Available for purchase', style: TextStyle(fontWeight: FontWeight.w800)), subtitle: Text(_active ? 'Visible at checkout for eligible plans.' : 'Hidden from new purchases; existing entitlements are not changed.'), value: _active, onChanged: (value) => setState(() => _active = value)),
              if (_error != null) ...[const SizedBox(height: 12), _InlineNotice(text: _error!, isError: true)],
            ]))),
            const Divider(height: 1),
            Padding(padding: const EdgeInsets.all(16), child: Row(children: [Expanded(child: OutlinedButton(onPressed: () => Navigator.pop(context), child: const Text('Cancel'))), const SizedBox(width: 10), Expanded(child: FilledButton.icon(onPressed: _submit, icon: Icon(editing ? Icons.save_outlined : Icons.add_rounded), label: Text(editing ? 'Save changes' : 'Create add-on')))])),
          ]),
        ),
      ),
    );
  }
}

class _AddonUsersDialog extends StatefulWidget {
  const _AddonUsersDialog({required this.api, required this.token, required this.addon, required this.map, required this.rowsFrom, required this.intValue, required this.money, required this.dateLabel});
  final AuthApi api;
  final String token;
  final Map<String, dynamic> addon;
  final Map<String, dynamic> Function(Object?) map;
  final List<Map<String, dynamic>> Function(Object?) rowsFrom;
  final int Function(Object?, [int]) intValue;
  final String Function(Object?, Object?, [Object?]) money;
  final String Function(Object?) dateLabel;
  @override
  State<_AddonUsersDialog> createState() => _AddonUsersDialogState();
}

class _AddonUsersDialogState extends State<_AddonUsersDialog> {
  final _search = TextEditingController();
  List<Map<String, dynamic>> _users = const [];
  String _status = 'all';
  String? _error;
  bool _loading = true;
  int _page = 1;
  int _lastPage = 1;
  int _total = 0;
  int _generation = 0;

  @override
  void initState() { super.initState(); _load(); }
  @override
  void dispose() { _search.dispose(); super.dispose(); }

  Future<void> _load({int? page}) async {
    final generation = ++_generation;
    final nextPage = page ?? _page;
    setState(() { _loading = true; _error = null; });
    final query = <String, String>{'per_page': '20', 'page': '$nextPage'};
    if (_search.text.trim().isNotEmpty) query['search'] = _search.text.trim();
    if (_status != 'all') query['status'] = _status;
    final uriQuery = query.entries.map((e) => '${Uri.encodeQueryComponent(e.key)}=${Uri.encodeQueryComponent(e.value)}').join('&');
    try {
      final response = await widget.api.get('admin/package-addons/${widget.addon['id']}/users?$uriQuery', widget.token);
      if (!mounted || generation != _generation) return;
      final root = widget.map(response);
      final data = widget.map(root['data']);
      final meta = widget.map(root['meta']).isNotEmpty ? widget.map(root['meta']) : data;
      setState(() {
        _users = widget.rowsFrom(response);
        _page = widget.intValue(data['current_page'] ?? meta['current_page'], nextPage);
        _lastPage = widget.intValue(data['last_page'] ?? meta['last_page'], 1).clamp(1, 100000).toInt();
        _total = widget.intValue(data['total'] ?? meta['total'], _users.length);
      });
    } on AuthException catch (error) {
      if (mounted && generation == _generation) setState(() => _error = error.message);
    } catch (_) {
      if (mounted && generation == _generation) setState(() => _error = 'Could not load entitlement records. Try refreshing.');
    } finally {
      if (mounted && generation == _generation) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final addon = widget.addon;
    final name = addon['name']?.toString() ?? 'Package add-on';
    return Dialog(
      insetPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 18),
      child: ConstrainedBox(
        constraints: const BoxConstraints(maxWidth: 940, maxHeight: 820),
        child: Column(children: [
          Padding(padding: const EdgeInsets.fromLTRB(22, 18, 14, 14), child: Row(children: [const _BrandMark(icon: Icons.people_alt_outlined, gradient: _AdminPalette.ocean, size: 44), const SizedBox(width: 12), Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text('$name users', style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w900)), Text('Entitlements, term dates and payment references', style: Theme.of(context).textTheme.bodySmall?.copyWith(color: _AdminPalette.muted))])), _CountBadge(label: '$_total records', color: _AdminPalette.blue), IconButton(tooltip: 'Close', onPressed: () => Navigator.pop(context), icon: const Icon(Icons.close_rounded))])),
          const Divider(height: 1),
          Padding(padding: const EdgeInsets.all(16), child: LayoutBuilder(builder: (context, c) {
            final field = TextField(controller: _search, textInputAction: TextInputAction.search, onSubmitted: (_) => _load(page: 1), decoration: InputDecoration(hintText: 'Search name, email, payment ID…', prefixIcon: const Icon(Icons.search_rounded), suffixIcon: IconButton(tooltip: 'Search', onPressed: () => _load(page: 1), icon: const Icon(Icons.arrow_forward_rounded))));
            final filters = Wrap(spacing: 6, children: [for (final status in const ['all', 'active', 'pending', 'expired', 'cancelled']) _ChoiceChip(label: status[0].toUpperCase() + status.substring(1), selected: _status == status, onTap: () { setState(() => _status = status); _load(page: 1); })]);
            if (c.maxWidth < 680) return Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [field, const SizedBox(height: 10), filters]);
            return Row(children: [Expanded(child: field), const SizedBox(width: 12), Flexible(child: filters)]);
          })),
          if (_error != null) Padding(padding: const EdgeInsets.symmetric(horizontal: 16), child: _InlineNotice(text: _error!, isError: true)),
          if (_loading) const LinearProgressIndicator(minHeight: 2),
          Expanded(child: _users.isEmpty ? Center(child: _loading ? const CircularProgressIndicator() : const Text('No matching entitlement records.')) : ListView.separated(padding: const EdgeInsets.fromLTRB(16, 8, 16, 14), itemCount: _users.length, separatorBuilder: (_, __) => const SizedBox(height: 8), itemBuilder: (context, index) => _EntitlementTile(row: _users[index], map: widget.map, money: widget.money, dateLabel: widget.dateLabel))),
          const Divider(height: 1),
          Padding(padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12), child: Row(children: [Text('Page $_page of $_lastPage', style: Theme.of(context).textTheme.labelLarge), const Spacer(), IconButton(tooltip: 'Previous page', onPressed: _page > 1 && !_loading ? () => _load(page: _page - 1) : null, icon: const Icon(Icons.chevron_left_rounded)), IconButton(tooltip: 'Next page', onPressed: _page < _lastPage && !_loading ? () => _load(page: _page + 1) : null, icon: const Icon(Icons.chevron_right_rounded)), const SizedBox(width: 8), OutlinedButton.icon(onPressed: _loading ? null : () => _load(), icon: const Icon(Icons.refresh_rounded), label: const Text('Refresh'))])),
        ]),
      ),
    );
  }
}

class _EntitlementTile extends StatelessWidget {
  const _EntitlementTile({required this.row, required this.map, required this.money, required this.dateLabel});
  final Map<String, dynamic> row;
  final Map<String, dynamic> Function(Object?) map;
  final String Function(Object?, Object?, [Object?]) money;
  final String Function(Object?) dateLabel;
  @override
  Widget build(BuildContext context) {
    final user = map(row['user']);
    final payment = map(row['payment_intent']);
    final status = (row['status'] ?? 'unknown').toString().toLowerCase();
    final name = user['name']?.toString() ?? user['full_name']?.toString() ?? 'User #${user['id'] ?? row['user_id'] ?? '—'}';
    final email = user['email']?.toString() ?? 'No email supplied';
    final paymentId = row['payment_intent_id'] ?? payment['id'] ?? payment['reference'];
    final snapshot = map(row['features']);
    final locationValue = snapshot['location'] ?? snapshot['country_code'];
    final locationLabel = locationValue is List
        ? locationValue.join(', ')
        : locationValue?.toString();
    final ipValue = snapshot['ip_addresses'];
    final ipLabel = ipValue is Map
        ? ipValue.entries.map((entry) => '${entry.key}: ${entry.value}').join(', ')
        : ipValue is List
            ? ipValue.join(', ')
            : ipValue?.toString();
    final bandwidth = snapshot['bandwidth'];
    final supplemental = <String>[
      if (locationLabel != null && locationLabel.isNotEmpty) 'Locations: $locationLabel',
      if (bandwidth is num && bandwidth > 0) 'Bandwidth: ${(bandwidth / 1000000000).toStringAsFixed(1)} GB',
      if (ipLabel != null && ipLabel.isNotEmpty) 'Assigned IPs: $ipLabel',
      if (paymentId != null) 'Payment: $paymentId',
    ].join(' · ');
    final color = switch (status) { 'active' => _AdminPalette.green, 'pending' || 'processing' => _AdminPalette.blue, 'expired' || 'cancelled' || 'failed' => _AdminPalette.rose, _ => _AdminPalette.amber };
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(color: Colors.white, border: Border.all(color: _AdminPalette.line), borderRadius: BorderRadius.circular(16)),
      child: LayoutBuilder(builder: (context, c) {
        final compact = c.maxWidth < 620;
        final identity = Row(children: [CircleAvatar(backgroundColor: color.withOpacity(.12), foregroundColor: color, child: Text(name.isEmpty ? '?' : name.substring(0, 1).toUpperCase())), const SizedBox(width: 10), Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(name, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontWeight: FontWeight.w800)), Text(email, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(color: _AdminPalette.muted, fontSize: 12))]))]);
        final facts = Wrap(spacing: 12, runSpacing: 8, children: [Text(money(row['price_minor'], row['currency']), style: const TextStyle(fontWeight: FontWeight.w800)), _TinyFact(icon: Icons.play_arrow_rounded, text: dateLabel(row['starts_at'])), _TinyFact(icon: Icons.event_outlined, text: dateLabel(row['ends_at']))]);
        final paymentLabel = supplemental.isEmpty
            ? const SizedBox.shrink()
            : Padding(
                padding: const EdgeInsets.only(top: 8),
                child: Text(
                  supplemental,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(color: _AdminPalette.muted, fontSize: 11),
                ),
              );
        if (compact) {
          return Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(children: [Expanded(child: identity), _StatusPill(label: status, color: color)]),
              const SizedBox(height: 12),
              facts,
              paymentLabel,
            ],
          );
        }
        return Row(
          children: [
            Expanded(flex: 3, child: identity),
            Expanded(flex: 3, child: facts),
            if (supplemental.isNotEmpty) Expanded(flex: 3, child: paymentLabel),
            _StatusPill(label: status, color: color),
          ],
        );
      }),
    );
  }
}

class _AddonCard extends StatelessWidget {
  const _AddonCard({required this.addon, required this.money, required this.intValue, required this.onEdit, required this.onDelete, required this.onUsers});
  final Map<String, dynamic> addon;
  final String Function(Object?, Object?, [Object?]) money;
  final int Function(Object?, [int]) intValue;
  final VoidCallback onEdit;
  final VoidCallback onDelete;
  final VoidCallback onUsers;
  Map<String, dynamic> _map(Object? value) => value is Map ? value.map<String, dynamic>((k, v) => MapEntry(k.toString(), v)) : const {};
  @override
  Widget build(BuildContext context) {
    final plan = _map(addon['plan']);
    final features = _map(addon['features']);
    final active = addon['is_active'] == true || addon['is_active'] == 1;
    final activeUsers = intValue(addon['active_users_count'] ?? addon['active_entitlements_count']);
    final totalUsers = intValue(addon['total_users_count'] ?? addon['users_count'] ?? addon['entitlements_count']);
    final color = active ? _AdminPalette.green : _AdminPalette.muted;
    final locations = (features['location'] is List ? (features['location'] as List).join(' · ') : features['location']?.toString()) ?? 'Locations not specified';
    final bandwidth = intValue(features['bandwidth']);
    final gb = bandwidth == 0 ? '—' : '${(bandwidth / 1000000000).toStringAsFixed(bandwidth % 1000000000 == 0 ? 0 : 1)} GB';
    final currency = addon['currency'] ?? 'USD';
    final revenue = addon['revenue_minor'] ?? addon['total_revenue_minor'] ?? addon['gross_revenue_minor'];
    final interval = addon['billing_interval']?.toString() ?? 'monthly';
    final duration = intValue(addon['duration_count'], 1);
    final durationLabel = '$duration ${interval == 'monthly' ? 'month' : interval == 'yearly' ? 'year' : interval == 'weekly' ? 'week' : interval == 'quarterly' ? 'quarter' : interval}${duration == 1 ? '' : 's'}';
    return Container(
      decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(20), border: Border.all(color: _AdminPalette.line), boxShadow: const [BoxShadow(color: Color(0x080F172A), blurRadius: 16, offset: Offset(0, 5))]),
      child: Padding(padding: const EdgeInsets.all(17), child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
          _BrandMark(icon: Icons.extension_rounded, gradient: active ? _AdminPalette.mint : _AdminPalette.slate, size: 44),
          const SizedBox(width: 11),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(addon['name']?.toString() ?? 'Package add-on', maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 16)), const SizedBox(height: 3), Text('${addon['addon_key'] ?? 'no-key'} · ${plan['name'] ?? plan['plan_key'] ?? 'Plan #${addon['subscription_plan_id'] ?? '—'}'}', maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(color: _AdminPalette.muted, fontSize: 11))])),
          _StatusPill(label: active ? 'Available' : 'Disabled', color: color),
        ]),
        const SizedBox(height: 14),
        Container(padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 11), decoration: BoxDecoration(color: _AdminPalette.canvas, borderRadius: BorderRadius.circular(14)), child: Row(children: [Expanded(child: _PriceFact(label: 'PRICE / $interval', value: money(addon['price_minor'], currency, addon['currency_minor_units']))), Container(width: 1, height: 34, color: _AdminPalette.line), const SizedBox(width: 12), Expanded(child: _PriceFact(label: 'DISCOUNT', value: '${addon['discount'] ?? 0}%')), Container(width: 1, height: 34, color: _AdminPalette.line), const SizedBox(width: 12), Expanded(child: _PriceFact(label: 'TERM', value: durationLabel))])),
        const SizedBox(height: 12),
        Row(children: [const Icon(Icons.speed_rounded, size: 16, color: _AdminPalette.blue), const SizedBox(width: 5), Text(gb, style: const TextStyle(fontWeight: FontWeight.w800)), const SizedBox(width: 12), const Icon(Icons.public_rounded, size: 16, color: _AdminPalette.teal), const SizedBox(width: 5), Expanded(child: Text(locations, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 12, color: _AdminPalette.muted)))]),
        if (revenue != null) ...[
          const SizedBox(height: 8),
          Row(children: [const Icon(Icons.trending_up_rounded, size: 16, color: _AdminPalette.green), const SizedBox(width: 6), const Text('Reported revenue', style: TextStyle(color: _AdminPalette.muted, fontSize: 11)), const Spacer(), Text(money(revenue, addon['revenue_currency'] ?? currency, addon['currency_minor_units']), style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 12))]),
        ],
        const SizedBox(height: 11),
        InkWell(onTap: onUsers, borderRadius: BorderRadius.circular(12), child: Container(padding: const EdgeInsets.symmetric(horizontal: 11, vertical: 9), decoration: BoxDecoration(color: _AdminPalette.blue.withOpacity(.08), borderRadius: BorderRadius.circular(12)), child: Row(children: [const Icon(Icons.people_alt_outlined, size: 18, color: _AdminPalette.blue), const SizedBox(width: 8), Expanded(child: Text('$activeUsers active · $totalUsers total entitlements', style: const TextStyle(color: _AdminPalette.ink, fontWeight: FontWeight.w700, fontSize: 12))), const Icon(Icons.chevron_right_rounded, size: 18, color: _AdminPalette.muted)]))),
        const SizedBox(height: 10),
        const Divider(height: 18),
        Row(children: [Expanded(child: OutlinedButton.icon(onPressed: onEdit, icon: const Icon(Icons.edit_outlined, size: 17), label: const Text('Edit'))), const SizedBox(width: 8), Expanded(child: OutlinedButton.icon(onPressed: onUsers, icon: const Icon(Icons.group_outlined, size: 17), label: const Text('Users'))), const SizedBox(width: 4), IconButton(tooltip: 'Delete add-on', onPressed: onDelete, color: _AdminPalette.rose, icon: const Icon(Icons.delete_outline_rounded))]),
      ])),
    );
  }
}

class _PriceFact extends StatelessWidget {
  const _PriceFact({required this.label, required this.value});
  final String label, value;
  @override
  Widget build(BuildContext context) => Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(label, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(color: _AdminPalette.muted, fontSize: 9, fontWeight: FontWeight.w800, letterSpacing: .4)), const SizedBox(height: 4), Text(value, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(color: _AdminPalette.ink, fontSize: 13, fontWeight: FontWeight.w900))]);
}

class _TinyFact extends StatelessWidget {
  const _TinyFact({required this.icon, required this.text});
  final IconData icon;
  final String text;
  @override
  Widget build(BuildContext context) => Row(mainAxisSize: MainAxisSize.min, children: [Icon(icon, size: 14, color: _AdminPalette.muted), const SizedBox(width: 4), Text(text, style: const TextStyle(fontSize: 11, color: _AdminPalette.muted))]);
}

class _FeatureSwitch extends StatelessWidget {
  const _FeatureSwitch({required this.label, required this.value, required this.onChanged});
  final String label;
  final bool value;
  final ValueChanged<bool> onChanged;
  @override
  Widget build(BuildContext context) => FilterChip(label: Text(label), selected: value, onSelected: onChanged, avatar: Icon(value ? Icons.check_circle_rounded : Icons.circle_outlined, size: 17));
}

class _LabeledField extends StatelessWidget {
  const _LabeledField({required this.label, required this.child});
  final String label;
  final Widget child;
  @override
  Widget build(BuildContext context) => Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(label, style: const TextStyle(color: _AdminPalette.ink, fontWeight: FontWeight.w800, fontSize: 12)), const SizedBox(height: 7), child]);
}

class _ResponsiveFields extends StatelessWidget {
  const _ResponsiveFields({required this.children});
  final List<Widget> children;
  @override
  Widget build(BuildContext context) => LayoutBuilder(builder: (context, constraints) {
        final columns = constraints.maxWidth >= 760 ? 2 : 1;
        final width = (constraints.maxWidth - (columns - 1) * 14) / columns;
        return Wrap(spacing: 14, runSpacing: 14, children: [for (final child in children) SizedBox(width: width, child: child)]);
      });
}

class _SectionHeading extends StatelessWidget {
  const _SectionHeading({required this.title, required this.subtitle});
  final String title, subtitle;
  @override
  Widget build(BuildContext context) => Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(title, style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 15)), const SizedBox(height: 3), Text(subtitle, style: const TextStyle(color: _AdminPalette.muted, fontSize: 12))]);
}

class _BrandMark extends StatelessWidget {
  const _BrandMark({required this.icon, required this.gradient, this.size = 52});
  final IconData icon;
  final LinearGradient gradient;
  final double size;
  @override
  Widget build(BuildContext context) => Container(width: size, height: size, decoration: BoxDecoration(gradient: gradient, borderRadius: BorderRadius.circular(size * .32)), child: Icon(icon, color: Colors.white, size: size * .5));
}

class _StatusPill extends StatelessWidget {
  const _StatusPill({required this.label, required this.color});
  final String label;
  final Color color;
  @override
  Widget build(BuildContext context) => Container(padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 6), decoration: BoxDecoration(color: color.withOpacity(.1), borderRadius: BorderRadius.circular(30)), child: Text(label.toUpperCase(), style: TextStyle(color: color, fontSize: 9, fontWeight: FontWeight.w900, letterSpacing: .4)));
}

class _CountBadge extends StatelessWidget {
  const _CountBadge({required this.label, required this.color});
  final String label;
  final Color color;
  @override
  Widget build(BuildContext context) => Container(padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 6), decoration: BoxDecoration(color: color.withOpacity(.1), borderRadius: BorderRadius.circular(30)), child: Text(label, style: TextStyle(color: color, fontWeight: FontWeight.w800, fontSize: 11)));
}

class _ChoiceChip extends StatelessWidget {
  const _ChoiceChip({required this.label, required this.selected, required this.onTap});
  final String label;
  final bool selected;
  final VoidCallback onTap;
  @override
  Widget build(BuildContext context) => ChoiceChip(label: Text(label), selected: selected, onSelected: (_) => onTap());
}

class _InlineNotice extends StatelessWidget {
  const _InlineNotice({required this.text, required this.isError});
  final String text;
  final bool isError;
  @override
  Widget build(BuildContext context) {
    final color = isError ? _AdminPalette.rose : _AdminPalette.blue;
    return Container(padding: const EdgeInsets.all(13), decoration: BoxDecoration(color: color.withOpacity(.08), borderRadius: BorderRadius.circular(14), border: Border.all(color: color.withOpacity(.2))), child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [Icon(isError ? Icons.error_outline_rounded : Icons.info_outline_rounded, color: color, size: 18), const SizedBox(width: 9), Expanded(child: Text(text, style: TextStyle(color: color, fontSize: 12, height: 1.4)))]));
  }
}

class _PackageTileRow extends StatelessWidget {
  const _PackageTileRow({required this.children, this.minItemWidth = 150});

  final List<Widget> children;
  final double minItemWidth;

  @override
  Widget build(BuildContext context) => LayoutBuilder(
        builder: (context, constraints) {
          final columns = (constraints.maxWidth / (minItemWidth + 12))
              .floor()
              .clamp(1, children.length)
              .toInt();
          final width = (constraints.maxWidth - (columns - 1) * 12) / columns;
          return Wrap(
            spacing: 12,
            runSpacing: 12,
            children: [
              for (final child in children) SizedBox(width: width, child: child),
            ],
          );
        },
      );
}

class _PackageStatTile extends StatelessWidget {
  const _PackageStatTile({
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
        padding: const EdgeInsets.fromLTRB(14, 14, 14, 14),
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
            Row(children: [Icon(icon, color: Colors.white, size: 18), const Spacer(), Container(width: 6, height: 6, decoration: BoxDecoration(color: Colors.white.withValues(alpha: .7), shape: BoxShape.circle))]),
            const SizedBox(height: 12),
            FittedBox(
              fit: BoxFit.scaleDown,
              alignment: Alignment.centerLeft,
              child: Text(
                value,
                maxLines: 1,
                style: const TextStyle(color: Colors.white, fontSize: 24, fontWeight: FontWeight.w800, height: 1.1),
              ),
            ),
            const SizedBox(height: 2),
            Text(label, maxLines: 1, overflow: TextOverflow.ellipsis, style: TextStyle(color: Colors.white.withValues(alpha: .9), fontSize: 12, fontWeight: FontWeight.w600)),
          ],
        ),
      );
}

class _PackageFilterChip extends StatelessWidget {
  const _PackageFilterChip({
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
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(label, style: TextStyle(color: selected ? Colors.white : color, fontWeight: FontWeight.w700, fontSize: 13)),
                const SizedBox(width: 6),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 1),
                  decoration: BoxDecoration(
                    color: selected ? Colors.white.withValues(alpha: .25) : color.withValues(alpha: .16),
                    borderRadius: BorderRadius.circular(999),
                  ),
                  child: Text('$count', style: TextStyle(color: selected ? Colors.white : color, fontSize: 11, fontWeight: FontWeight.w800)),
                ),
              ],
            ),
          ),
        ),
      );
}

class _PackageAppBarAction extends StatelessWidget {
  const _PackageAppBarAction({required this.icon, required this.tooltip, required this.onPressed});
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

class _PackageGradientButton extends StatelessWidget {
  const _PackageGradientButton({required this.label, required this.onPressed, this.icon, this.busy = false});
  final String label;
  final VoidCallback? onPressed;
  final IconData? icon;
  final bool busy;

  @override
  Widget build(BuildContext context) {
    final enabled = onPressed != null;
    final leading = busy
        ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2.2, color: Colors.white))
        : icon == null ? null : Icon(icon, color: Colors.white, size: 20);
    return Opacity(
      opacity: enabled ? 1 : .55,
      child: DecoratedBox(
        decoration: BoxDecoration(
          gradient: _AdminPalette.hero,
          borderRadius: BorderRadius.circular(16),
          boxShadow: enabled ? [BoxShadow(color: _AdminPalette.indigo.withValues(alpha: .35), blurRadius: 18, offset: const Offset(0, 9))] : null,
        ),
        child: Material(
          color: Colors.transparent,
          child: InkWell(
            borderRadius: BorderRadius.circular(16),
            onTap: onPressed,
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 15),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.center,
                mainAxisSize: MainAxisSize.min,
                children: [
                  if (leading != null) ...[leading, const SizedBox(width: 10)],
                  Flexible(child: Text(label, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 15))),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class _PackageGradientFab extends StatelessWidget {
  const _PackageGradientFab({required this.icon, required this.label, required this.onPressed});
  final IconData icon;
  final String label;
  final VoidCallback? onPressed;

  @override
  Widget build(BuildContext context) => Container(
        decoration: BoxDecoration(
          gradient: _AdminPalette.hero,
          borderRadius: BorderRadius.circular(20),
          boxShadow: [BoxShadow(color: _AdminPalette.violet.withValues(alpha: .4), blurRadius: 20, offset: const Offset(0, 10))],
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

class _PackageEmptyState extends StatelessWidget {
  const _PackageEmptyState({required this.icon, required this.gradient, required this.title, required this.message, required this.actionLabel, required this.onAction});
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
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              _BrandMark(icon: icon, gradient: gradient, size: 62),
              const SizedBox(height: 16),
              Text(title, textAlign: TextAlign.center, style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w800)),
              const SizedBox(height: 6),
              ConstrainedBox(constraints: const BoxConstraints(maxWidth: 380), child: Text(message, textAlign: TextAlign.center, style: TextStyle(color: Theme.of(context).colorScheme.onSurfaceVariant, height: 1.4))),
              const SizedBox(height: 18),
              _PackageGradientButton(label: actionLabel, icon: Icons.refresh_rounded, onPressed: onAction),
            ],
          ),
        ),
      );
}

ThemeData _adminTheme(BuildContext context) {
  final base = Theme.of(context);
  return base.copyWith(
    colorScheme: ColorScheme.fromSeed(
      seedColor: _AdminPalette.indigo,
      brightness: base.brightness,
    ),
  );
}

abstract final class _AdminPalette {
  static const ink = Color(0xFF172033);
  static const muted = Color(0xFF68758A);
  static const canvas = Color(0xFFF7F8FC);
  static const line = Color(0xFFE3E9F1);
  static const indigo = Color(0xFF4338CA);
  static const violet = Color(0xFF7C3AED);
  static const pink = Color(0xFFEC4899);
  static const green = Color(0xFF16A34A);
  static const teal = Color(0xFF0D9488);
  static const blue = Color(0xFF0284C7);
  static const amber = Color(0xFFF59E0B);
  static const rose = Color(0xFFE11D48);

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
  static const LinearGradient ocean = LinearGradient(
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
    colors: [blue, indigo],
  );
  static const LinearGradient sunset = LinearGradient(
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
    colors: [amber, Color(0xFFF97316)],
  );
  static const LinearGradient slate = LinearGradient(
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
    colors: [Color(0xFF64748B), Color(0xFF334155)],
  );
  static const LinearGradient berry = LinearGradient(
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
    colors: [pink, violet],
  );
}
