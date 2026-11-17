import 'package:flutter/material.dart';

import '../core/currency/money.dart';
import '../services/auth_api.dart';

// ---------------------------------------------------------------------------
// Design language (shared with the app listings and review templates consoles)
// ---------------------------------------------------------------------------

abstract final class _Palette {
  static const Color indigo = Color(0xFF4338CA);
  static const Color violet = Color(0xFF7C3AED);
  static const Color pink = Color(0xFFEC4899);
  static const Color green = Color(0xFF16A34A);
  static const Color teal = Color(0xFF0D9488);
  static const Color amber = Color(0xFFF59E0B);
  static const Color orange = Color(0xFFF97316);
  static const Color sky = Color(0xFF0284C7);
  static const Color rose = Color(0xFFE11D48);

  static const LinearGradient hero = LinearGradient(
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
    colors: <Color>[Color(0xFF3730A3), Color(0xFF7C3AED), Color(0xFFEC4899)],
  );

  static const LinearGradient mint = LinearGradient(
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
    colors: <Color>[Color(0xFF0D9488), Color(0xFF22C55E)],
  );

  static const LinearGradient sunset = LinearGradient(
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
    colors: <Color>[Color(0xFFF59E0B), Color(0xFFF97316)],
  );

  static const LinearGradient ocean = LinearGradient(
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
    colors: <Color>[Color(0xFF0284C7), Color(0xFF4338CA)],
  );

  static const LinearGradient berry = LinearGradient(
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
    colors: <Color>[Color(0xFFEC4899), Color(0xFF7C3AED)],
  );
}

ThemeData _adminTheme(BuildContext context) {
  final ThemeData base = Theme.of(context);
  return base.copyWith(
    colorScheme: ColorScheme.fromSeed(
      seedColor: _Palette.indigo,
      brightness: base.brightness,
    ),
  );
}

/// Rotates a tier gradient so consecutive ranks read as a ladder.
LinearGradient _tierGradient(int index) {
  const List<LinearGradient> tiers = <LinearGradient>[
    _Palette.ocean,
    _Palette.mint,
    _Palette.sunset,
    _Palette.berry,
    _Palette.hero,
  ];
  return tiers[index % tiers.length];
}

/// Snackbar helper that takes an already-resolved messenger so it can safely
/// run after an `await`.
void _showToast(
  ScaffoldMessengerState messenger,
  String text, {
  bool error = false,
  bool success = false,
}) {
  messenger
    ..hideCurrentSnackBar()
    ..showSnackBar(
      SnackBar(
        behavior: SnackBarBehavior.floating,
        backgroundColor: error
            ? _Palette.rose
            : success
            ? _Palette.green
            : _Palette.indigo,
        margin: const EdgeInsets.fromLTRB(14, 0, 14, 18),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
        content: Row(
          children: <Widget>[
            Icon(
              error
                  ? Icons.error_outline_rounded
                  : success
                  ? Icons.check_circle_outline_rounded
                  : Icons.info_outline_rounded,
              color: Colors.white,
              size: 20,
            ),
            const SizedBox(width: 10),
            Expanded(
              child: Text(
                text,
                style: const TextStyle(
                  color: Colors.white,
                  fontWeight: FontWeight.w600,
                ),
              ),
            ),
          ],
        ),
      ),
    );
}

// ---------------------------------------------------------------------------
// Screen
// ---------------------------------------------------------------------------

class AdminUserRankingsScreen extends StatefulWidget {
  const AdminUserRankingsScreen({super.key, required this.api});

  final AuthApi api;

  @override
  State<AdminUserRankingsScreen> createState() =>
      _AdminUserRankingsScreenState();
}

class _AdminUserRankingsScreenState extends State<AdminUserRankingsScreen> {
  final TextEditingController _email = TextEditingController(
    text: 'admin@tlstore.test',
  );
  final TextEditingController _password = TextEditingController(
    text: 'meandmyson',
  );
  final TextEditingController _search = TextEditingController();

  String? _token;
  String? _error;
  bool _loading = false;
  bool _passwordVisible = false;
  bool _enabled = false;
  List<Map<String, dynamic>> _levels = <Map<String, dynamic>>[];
  String _query = '';
  String _statusFilter = 'all';

  @override
  void dispose() {
    _email.dispose();
    _password.dispose();
    _search.dispose();
    super.dispose();
  }

  // -- data --------------------------------------------------------------

  double _amount(dynamic value) =>
      value is num
      ? value.toDouble()
      : double.tryParse(value?.toString() ?? '') ?? 0;

  String _money(dynamic value) => '\$${formatGroupedNumber(_amount(value), 2)}';

  int get _activeCount =>
      _levels.where((Map<String, dynamic> level) => level['is_active'] == true)
          .length;

  /// Estimated monthly spend if every currently qualified user is paid.
  double get _estimatedMonthlyPayout => _levels.fold<double>(
    0,
    (double sum, Map<String, dynamic> level) =>
        sum +
        _amount(level['monthly_reward_usd']) *
            (int.tryParse(level['qualifications_count']?.toString() ?? '') ??
                0),
  );

  List<Map<String, dynamic>> get _visibleLevels {
    final String needle = _query.trim().toLowerCase();
    return _levels.where((Map<String, dynamic> level) {
      final bool active = level['is_active'] == true;
      if (_statusFilter == 'active' && !active) return false;
      if (_statusFilter == 'paused' && active) return false;
      if (needle.isEmpty) return true;
      return '${level['name']} ${level['level']}'.toLowerCase().contains(
        needle,
      );
    }).toList();
  }

  Future<void> _login() async {
    final ScaffoldMessengerState messenger = ScaffoldMessenger.of(context);
    FocusScope.of(context).unfocus();
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final Map<String, dynamic> response = await widget.api.postPublic(
        'admin/auth/login',
        <String, dynamic>{
          'email': _email.text.trim(),
          'password': _password.text,
        },
      );
      final String? token = response['token']?.toString();
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
    if (!mounted) return;
    if (_token != null && _error == null) {
      _showToast(messenger, 'Signed in. Ranking levels are up to date.');
    }
  }

  Future<void> _load({bool announce = false}) async {
    final String? token = _token;
    if (token == null) return;
    final ScaffoldMessengerState messenger = ScaffoldMessenger.of(context);
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final List<dynamic> results = await Future.wait(<Future<dynamic>>[
        widget.api.get('admin/rank-levels', token),
        widget.api.get('admin/settings', token),
      ]);

      final dynamic rows = results[0]['data'];
      final dynamic settings = results[1]['data'];
      bool enabled = false;

      if (settings is List) {
        for (final Map<dynamic, dynamic> row in settings.whereType<Map>()) {
          if (row['setting_key'] == 'rewards.user_ranking_active') {
            final dynamic raw = row['value'] is Map
                ? (row['value'] as Map)['enabled']
                : row['value'];
            enabled =
                raw == true ||
                raw.toString() == '1' ||
                raw.toString() == 'true';
          }
        }
      }

      if (!mounted) return;
      setState(() {
        _enabled = enabled;
        _levels = rows is List
            ? rows
                  .whereType<Map>()
                  .map((Map<dynamic, dynamic> row) =>
                      Map<String, dynamic>.from(row))
                  .toList()
            : <Map<String, dynamic>>[];
      });
    } on AuthException catch (error) {
      if (mounted) setState(() => _error = error.message);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
    if (!mounted || !announce) return;
    if (_error == null) {
      _showToast(messenger, 'Refreshed ${_levels.length} ranking level(s).');
    }
  }

  void _signOut() {
    setState(() {
      _token = null;
      _levels = <Map<String, dynamic>>[];
      _enabled = false;
      _error = null;
      _query = '';
      _statusFilter = 'all';
      _search.clear();
    });
  }

  /// Optimistic toggle with rollback. Turning the whole system off is
  /// consequential, so that direction asks first.
  Future<void> _setEnabled(bool value) async {
    final String? token = _token;
    if (token == null || _loading) return;
    final ScaffoldMessengerState messenger = ScaffoldMessenger.of(context);

    if (!value) {
      final bool? confirmed = await showDialog<bool>(
        context: context,
        builder: (BuildContext dialogContext) => Theme(
          data: _adminTheme(dialogContext),
          child: _ConfirmDialog(
            title: 'Pause ranking rewards?',
            icon: Icons.pause_circle_outline_rounded,
            gradient: _Palette.sunset,
            message:
                'Eligibility checks and monthly payouts stop immediately. '
                'Levels keep their configuration, and you can switch the '
                'system back on at any time.',
            confirmLabel: 'Pause rewards',
            cancelLabel: 'Keep running',
          ),
        ),
      );
      if (confirmed != true) return;
    }

    final bool previous = _enabled;
    if (!mounted) return;
    setState(() {
      _enabled = value;
      _error = null;
    });
    try {
      await widget.api.put(
        'admin/settings/rewards.user_ranking_active',
        token,
        <String, dynamic>{'value': value, 'is_public': true},
      );
      _showToast(
        messenger,
        value
            ? 'Ranking rewards have been enabled.'
            : 'Ranking rewards have been disabled.',
        success: true,
      );
    } on AuthException catch (error) {
      if (mounted) setState(() => _enabled = previous);
      _showToast(messenger, error.message, error: true);
    }
  }

  Future<void> _edit(Map<String, dynamic> item) async {
    final String? token = _token;
    if (token == null) return;
    final bool? saved = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      backgroundColor: Colors.transparent,
      barrierColor: Colors.black.withValues(alpha: .55),
      // Sheet routes sit above this screen's Theme widget, so the admin
      // palette has to be reapplied inside the route.
      builder: (BuildContext sheetContext) => Theme(
        data: _adminTheme(sheetContext),
        child: _RankLevelSheet(
          api: widget.api,
          token: token,
          level: item,
        ),
      ),
    );
    if (saved == true) await _load();
  }
  // -- build -------------------------------------------------------------

  @override
  Widget build(BuildContext context) {
    return Theme(
      data: _adminTheme(context),
      child: Builder(
        builder: (BuildContext context) => _token == null
            ? _buildSignIn(context)
            : _buildDashboard(context),
      ),
    );
  }

  Widget _buildSignIn(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    return Scaffold(
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
                  children: <Widget>[
                    Container(
                      width: 76,
                      height: 76,
                      decoration: BoxDecoration(
                        color: Colors.white.withValues(alpha: .18),
                        shape: BoxShape.circle,
                        border: Border.all(
                          color: Colors.white.withValues(alpha: .35),
                        ),
                      ),
                      child: const Icon(
                        Icons.emoji_events_rounded,
                        size: 38,
                        color: Colors.white,
                      ),
                    ),
                    const SizedBox(height: 18),
                    const Text(
                      'User ranking console',
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
                      'Set qualification targets and monthly rewards for every '
                      'achievement tier.',
                      textAlign: TextAlign.center,
                      style: TextStyle(
                        color: Colors.white.withValues(alpha: .85),
                        height: 1.4,
                      ),
                    ),
                    const SizedBox(height: 24),
                    Container(
                      padding: const EdgeInsets.all(20),
                      decoration: BoxDecoration(
                        color: theme.colorScheme.surface,
                        borderRadius: BorderRadius.circular(26),
                        boxShadow: <BoxShadow>[
                          BoxShadow(
                            color: Colors.black.withValues(alpha: .22),
                            blurRadius: 30,
                            offset: const Offset(0, 18),
                          ),
                        ],
                      ),
                      child: Column(
                        mainAxisSize: MainAxisSize.min,
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: <Widget>[
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
                            obscure: !_passwordVisible,
                            onSubmitted: (_) => _login(),
                            suffix: IconButton(
                              tooltip: _passwordVisible
                                  ? 'Hide password'
                                  : 'Show password',
                              onPressed: () => setState(
                                () => _passwordVisible = !_passwordVisible,
                              ),
                              icon: Icon(
                                _passwordVisible
                                    ? Icons.visibility_off_outlined
                                    : Icons.visibility_outlined,
                                size: 20,
                              ),
                            ),
                          ),
                          if (_error != null) ...<Widget>[
                            const SizedBox(height: 14),
                            _ErrorBanner(message: _error!),
                          ],
                          const SizedBox(height: 18),
                          _GradientButton(
                            label: _loading ? 'Signing in…' : 'Sign in',
                            icon: _loading
                                ? null
                                : Icons.arrow_forward_rounded,
                            busy: _loading,
                            onPressed: _loading ? null : _login,
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 18),
                    Text(
                      'Credentials are pre-filled for the test environment.',
                      style: TextStyle(
                        color: Colors.white.withValues(alpha: .8),
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
  }

  Widget _buildDashboard(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    final List<Map<String, dynamic>> visible = _visibleLevels;

    return Scaffold(
      backgroundColor: theme.colorScheme.surface,
      body: RefreshIndicator(
        color: Colors.white,
        backgroundColor: _Palette.indigo,
        onRefresh: () => _load(announce: true),
        child: CustomScrollView(
          physics: const AlwaysScrollableScrollPhysics(),
          slivers: <Widget>[
            SliverAppBar(
              pinned: true,
              elevation: 0,
              centerTitle: false,
              backgroundColor: _Palette.indigo,
              foregroundColor: Colors.white,
              flexibleSpace: const DecoratedBox(
                decoration: BoxDecoration(gradient: _Palette.hero),
              ),
              title: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  const Text(
                    'User rankings',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                      color: Colors.white,
                      fontWeight: FontWeight.w800,
                      letterSpacing: -.3,
                    ),
                  ),
                  Text(
                    '${_levels.length} levels · $_activeCount active · '
                    '${_enabled ? 'rewards on' : 'rewards paused'}',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                      color: Colors.white.withValues(alpha: .85),
                      fontSize: 11.5,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ],
              ),
              actions: <Widget>[
                _AppBarAction(
                  icon: Icons.refresh_rounded,
                  tooltip: 'Refresh rankings',
                  onPressed: _loading ? null : () => _load(announce: true),
                ),
                _AppBarAction(
                  icon: Icons.logout_rounded,
                  tooltip: 'Sign out',
                  onPressed: _signOut,
                ),
                const SizedBox(width: 4),
              ],
            ),
            SliverToBoxAdapter(
              child: Padding(
                padding: const EdgeInsets.fromLTRB(16, 16, 16, 4),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: <Widget>[
                    _TileRow(
                      minItemWidth: 150,
                      children: <Widget>[
                        _StatTile(
                          label: 'Total levels',
                          value: '${_levels.length}',
                          icon: Icons.layers_rounded,
                          gradient: _Palette.ocean,
                        ),
                        _StatTile(
                          label: 'Active',
                          value: '$_activeCount',
                          icon: Icons.check_circle_rounded,
                          gradient: _Palette.mint,
                        ),
                        _StatTile(
                          label: 'Paused',
                          value: '${_levels.length - _activeCount}',
                          icon: Icons.pause_circle_rounded,
                          gradient: _Palette.sunset,
                        ),
                        _StatTile(
                          label: 'Est. monthly payout',
                          value: _money(_estimatedMonthlyPayout),
                          icon: Icons.payments_rounded,
                          gradient: _Palette.berry,
                        ),
                      ],
                    ),
                    const SizedBox(height: 14),
                    _SystemSwitchCard(
                      enabled: _enabled,
                      busy: _loading,
                      onChanged: _setEnabled,
                    ),
                    const SizedBox(height: 14),
                    if (_error != null) ...<Widget>[
                      _ErrorBanner(message: _error!),
                      const SizedBox(height: 14),
                    ],
                    _SearchField(
                      controller: _search,
                      hint: 'Search ranks by name or level',
                      onChanged: (String value) =>
                          setState(() => _query = value),
                    ),
                    const SizedBox(height: 10),
                    Wrap(
                      spacing: 8,
                      runSpacing: 8,
                      children: <Widget>[
                        _FilterChip(
                          label: 'All',
                          count: _levels.length,
                          color: _Palette.indigo,
                          selected: _statusFilter == 'all',
                          onSelected: () =>
                              setState(() => _statusFilter = 'all'),
                        ),
                        _FilterChip(
                          label: 'Active',
                          count: _activeCount,
                          color: _Palette.green,
                          selected: _statusFilter == 'active',
                          onSelected: () =>
                              setState(() => _statusFilter = 'active'),
                        ),
                        _FilterChip(
                          label: 'Paused',
                          count: _levels.length - _activeCount,
                          color: _Palette.amber,
                          selected: _statusFilter == 'paused',
                          onSelected: () =>
                              setState(() => _statusFilter = 'paused'),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ),
            if (_loading && _levels.isEmpty)
              const SliverFillRemaining(
                hasScrollBody: false,
                child: _SkeletonList(),
              )
            else if (_error != null && _levels.isEmpty)
              SliverFillRemaining(
                hasScrollBody: false,
                child: _EmptyState(
                  icon: Icons.cloud_off_rounded,
                  gradient: _Palette.sunset,
                  title: 'Could not load ranking levels',
                  message: _error!,
                  actionLabel: 'Try again',
                  onAction: () => _load(),
                ),
              )
            else if (visible.isEmpty)
              SliverFillRemaining(
                hasScrollBody: false,
                child: _EmptyState(
                  icon: _levels.isEmpty
                      ? Icons.emoji_events_rounded
                      : Icons.search_off_rounded,
                  gradient: _Palette.berry,
                  title: _levels.isEmpty
                      ? 'No ranking levels found'
                      : 'Nothing matches that search',
                  message: _levels.isEmpty
                      ? 'Pull down to refresh and check for available ranking '
                            'levels.'
                      : 'Try a different rank name or clear the status filter.',
                  actionLabel: _levels.isEmpty ? 'Refresh' : 'Clear filters',
                  onAction: _levels.isEmpty
                      ? () => _load()
                      : () => setState(() {
                          _query = '';
                          _statusFilter = 'all';
                          _search.clear();
                        }),
                ),
              )
            else
              SliverPadding(
                padding: const EdgeInsets.fromLTRB(16, 12, 16, 28),
                sliver: SliverList.separated(
                  itemCount: visible.length,
                  separatorBuilder: (BuildContext context, int index) =>
                      const SizedBox(height: 14),
                  itemBuilder: (BuildContext context, int index) {
                    final Map<String, dynamic> level = visible[index];
                    final int tierIndex = _levels.indexOf(level);
                    return _LevelCard(
                      level: level,
                      gradient: _tierGradient(tierIndex < 0 ? index : tierIndex),
                      money: _money,
                      busy: _loading,
                      onEdit: () => _edit(level),
                    );
                  },
                ),
              ),
          ],
        ),
      ),
    );
  }
}

// ---------------------------------------------------------------------------
// Ranking level card
// ---------------------------------------------------------------------------

class _LevelCard extends StatelessWidget {
  const _LevelCard({
    required this.level,
    required this.gradient,
    required this.money,
    required this.onEdit,
    this.busy = false,
  });

  final Map<String, dynamic> level;
  final LinearGradient gradient;
  final String Function(dynamic) money;
  final VoidCallback onEdit;
  final bool busy;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    final ColorScheme scheme = theme.colorScheme;
    final bool active = level['is_active'] == true;
    final Color accent = active ? _Palette.green : _Palette.amber;
    final String name =
        level['name']?.toString().trim().isNotEmpty == true
        ? level['name'].toString()
        : 'Unnamed rank';
    final int qualifications =
        int.tryParse(level['qualifications_count']?.toString() ?? '') ?? 0;
    final double monthly = _amountOf(level['monthly_reward_usd']);
    final int months =
        int.tryParse(level['reward_duration_months']?.toString() ?? '') ?? 1;

    return Container(
      clipBehavior: Clip.antiAlias,
      decoration: BoxDecoration(
        color: scheme.surface,
        borderRadius: BorderRadius.circular(24),
        border: Border.all(color: scheme.outlineVariant.withValues(alpha: .7)),
        boxShadow: <BoxShadow>[
          BoxShadow(
            color: scheme.shadow.withValues(alpha: .06),
            blurRadius: 18,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          Container(height: 4, decoration: BoxDecoration(gradient: gradient)),
          Padding(
            padding: const EdgeInsets.fromLTRB(14, 14, 14, 12),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    Container(
                      width: 54,
                      height: 54,
                      decoration: BoxDecoration(
                        gradient: gradient,
                        borderRadius: BorderRadius.circular(17),
                      ),
                      alignment: Alignment.center,
                      child: Text(
                        '${level['level'] ?? '—'}',
                        style: const TextStyle(
                          color: Colors.white,
                          fontSize: 21,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: <Widget>[
                          Text(
                            name,
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
                            style: theme.textTheme.titleMedium?.copyWith(
                              fontWeight: FontWeight.w800,
                              height: 1.2,
                            ),
                          ),
                          const SizedBox(height: 3),
                          Text(
                            'Level ${level['level'] ?? '—'} · '
                            '${money(level['monthly_reward_usd'])}/month',
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: theme.textTheme.bodySmall?.copyWith(
                              color: scheme.onSurfaceVariant,
                            ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(width: 8),
                    _StatusPill(active: active),
                  ],
                ),
                const SizedBox(height: 12),
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: <Widget>[
                    _MetaChip(
                      icon: Icons.people_alt_rounded,
                      color: _Palette.indigo,
                      label:
                          '${level['minimum_subscribed_referrals'] ?? 0} '
                          'referrals',
                    ),
                    _MetaChip(
                      icon: Icons.groups_rounded,
                      color: _Palette.violet,
                      label:
                          '${money(level['minimum_referral_cash_inflow_usd'])} '
                          'referral inflow',
                    ),
                    _MetaChip(
                      icon: Icons.person_rounded,
                      color: _Palette.sky,
                      label:
                          '${money(level['minimum_user_cash_inflow_usd'])} '
                          'personal inflow',
                    ),
                    _MetaChip(
                      icon: Icons.calendar_month_rounded,
                      color: _Palette.teal,
                      label: '$months month'
                          '${months == 1 ? '' : 's'}',
                    ),
                  ],
                ),
                const SizedBox(height: 12),
                _TileRow(
                  spacing: 10,
                  minItemWidth: 104,
                  children: <Widget>[
                    _MetricBox(
                      label: 'Qualified users',
                      value: '$qualifications',
                    ),
                    _MetricBox(
                      label: 'Total value',
                      value: money(monthly * months),
                    ),
                    _MetricBox(
                      label: 'Display order',
                      value: '${level['sort_order'] ?? level['level'] ?? '—'}',
                    ),
                  ],
                ),
              ],
            ),
          ),
          Container(
            decoration: BoxDecoration(
              border: Border(
                top: BorderSide(
                  color: scheme.outlineVariant.withValues(alpha: .7),
                ),
              ),
            ),
            padding: const EdgeInsets.fromLTRB(14, 6, 8, 8),
            child: Row(
              children: <Widget>[
                Expanded(
                  child: Row(
                    children: <Widget>[
                      Icon(
                        Icons.verified_user_rounded,
                        size: 16,
                        color: accent,
                      ),
                      const SizedBox(width: 6),
                      Expanded(
                        child: Text(
                          active
                              ? 'Accepting qualifications'
                              : 'Hidden from qualification',
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: theme.textTheme.labelSmall?.copyWith(
                            color: scheme.onSurfaceVariant,
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
                TextButton.icon(
                  style: TextButton.styleFrom(
                    foregroundColor: _Palette.violet,
                  ),
                  onPressed: busy ? null : onEdit,
                  icon: const Icon(Icons.edit_outlined, size: 18),
                  label: const Text('Edit level'),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

double _amountOf(dynamic value) =>
    value is num
    ? value.toDouble()
    : double.tryParse(value?.toString() ?? '') ?? 0;

class _StatusPill extends StatelessWidget {
  const _StatusPill({required this.active});

  final bool active;

  @override
  Widget build(BuildContext context) {
    final Color color = active ? _Palette.green : _Palette.amber;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 5),
      decoration: BoxDecoration(
        color: color.withValues(alpha: .14),
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: color.withValues(alpha: .3)),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: <Widget>[
          Icon(
            active ? Icons.check_circle_rounded : Icons.pause_circle_rounded,
            size: 13,
            color: color,
          ),
          const SizedBox(width: 4),
          Text(
            active ? 'ACTIVE' : 'PAUSED',
            style: TextStyle(
              color: color,
              fontSize: 10,
              fontWeight: FontWeight.w800,
              letterSpacing: .6,
            ),
          ),
        ],
      ),
    );
  }
}

class _SystemSwitchCard extends StatelessWidget {
  const _SystemSwitchCard({
    required this.enabled,
    required this.busy,
    required this.onChanged,
  });

  final bool enabled;
  final bool busy;
  final ValueChanged<bool> onChanged;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    return Container(
      padding: const EdgeInsets.fromLTRB(14, 12, 6, 12),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.centerLeft,
          end: Alignment.centerRight,
          colors: <Color>[
            (enabled ? _Palette.green : _Palette.amber).withValues(alpha: .16),
            (enabled ? _Palette.teal : _Palette.orange).withValues(alpha: .05),
          ],
        ),
        borderRadius: BorderRadius.circular(20),
        border: Border.all(
          color: (enabled ? _Palette.green : _Palette.amber).withValues(
            alpha: .35,
          ),
        ),
      ),
      child: Row(
        children: <Widget>[
          Container(
            width: 44,
            height: 44,
            decoration: BoxDecoration(
              gradient: enabled ? _Palette.mint : _Palette.sunset,
              borderRadius: BorderRadius.circular(14),
            ),
            child: Icon(
              enabled
                  ? Icons.settings_suggest_rounded
                  : Icons.pause_circle_outline_rounded,
              color: Colors.white,
              size: 22,
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Text(
                  'Ranking rewards system',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: theme.textTheme.titleSmall?.copyWith(
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  enabled
                      ? 'Eligibility checks and monthly payouts are running.'
                      : 'Eligibility checks and monthly payouts are paused.',
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: theme.textTheme.labelSmall?.copyWith(
                    color: theme.colorScheme.onSurfaceVariant,
                    height: 1.3,
                  ),
                ),
              ],
            ),
          ),
          Switch.adaptive(
            value: enabled,
            onChanged: busy ? null : onChanged,
          ),
        ],
      ),
    );
  }
}

// ---------------------------------------------------------------------------
// Edit ranking level sheet
// ---------------------------------------------------------------------------

class _RankLevelSheet extends StatefulWidget {
  const _RankLevelSheet({
    required this.api,
    required this.token,
    required this.level,
  });

  final AuthApi api;
  final String token;
  final Map<String, dynamic> level;

  @override
  State<_RankLevelSheet> createState() => _RankLevelSheetState();
}

class _RankLevelSheetState extends State<_RankLevelSheet> {
  static const List<String> _fieldKeys = <String>[
    'level',
    'name',
    'icon',
    'minimum_subscribed_referrals',
    'minimum_referral_cash_inflow_usd',
    'minimum_user_cash_inflow_usd',
    'monthly_reward_usd',
    'reward_duration_months',
    'sort_order',
  ];
  static const List<String> _intKeys = <String>[
    'level',
    'minimum_subscribed_referrals',
    'reward_duration_months',
    'sort_order',
  ];
  static const List<String> _doubleKeys = <String>[
    'minimum_referral_cash_inflow_usd',
    'minimum_user_cash_inflow_usd',
    'monthly_reward_usd',
  ];

  final GlobalKey<FormState> _formKey = GlobalKey<FormState>();
  late final Map<String, TextEditingController> _fields;
  late bool _active;
  bool _saving = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    final Map<String, dynamic> row = widget.level;
    _active = row['is_active'] == true;
    _fields = <String, TextEditingController>{
      for (final String key in _fieldKeys)
        key: TextEditingController(text: _initialValue(key, row)),
    };
    _fields['monthly_reward_usd']!.addListener(_onRewardChanged);
    _fields['reward_duration_months']!.addListener(_onRewardChanged);
  }

  @override
  void dispose() {
    _fields['monthly_reward_usd']!.removeListener(_onRewardChanged);
    _fields['reward_duration_months']!.removeListener(_onRewardChanged);
    for (final TextEditingController controller in _fields.values) {
      controller.dispose();
    }
    super.dispose();
  }

  void _onRewardChanged() {
    if (mounted) setState(() {});
  }

  String _initialValue(String key, Map<String, dynamic> row) {
    final dynamic value = row[key];
    if (_doubleKeys.contains(key) || key == 'minimum_subscribed_referrals') {
      return '${value ?? 0}';
    }
    if (key == 'reward_duration_months') return '${value ?? 1}';
    if (key == 'sort_order') return '${value ?? row['level'] ?? 1}';
    return '${value ?? ''}';
  }

  static const Map<String, String> _labels = <String, String>{
    'level': 'Level number',
    'name': 'Rank name',
    'icon': 'Icon image URL / path',
    'minimum_subscribed_referrals': 'Minimum subscribed referrals',
    'minimum_referral_cash_inflow_usd': 'Referral cash inflow requirement (USD)',
    'minimum_user_cash_inflow_usd': 'Personal cash inflow requirement (USD)',
    'monthly_reward_usd': 'Monthly reward (USD)',
    'reward_duration_months': 'Reward duration (months)',
    'sort_order': 'Display order',
  };

  static const Map<String, IconData> _icons = <String, IconData>{
    'level': Icons.stairs_rounded,
    'name': Icons.workspace_premium_outlined,
    'icon': Icons.image_outlined,
    'minimum_subscribed_referrals': Icons.people_alt_outlined,
    'minimum_referral_cash_inflow_usd': Icons.group_outlined,
    'minimum_user_cash_inflow_usd': Icons.person_outline_rounded,
    'monthly_reward_usd': Icons.payments_outlined,
    'reward_duration_months': Icons.calendar_month_outlined,
    'sort_order': Icons.sort_rounded,
  };

  String? _validate(String key, String? value) {
    final String text = value?.trim() ?? '';
    if (key == 'name' && text.isEmpty) return 'Enter a name for this rank.';
    if (key == 'icon') return null;
    if (text.isEmpty) return 'This field is required.';
    if (_doubleKeys.contains(key)) {
      final double? number = double.tryParse(text);
      if (number == null || number < 0) {
        return 'Enter a valid amount (zero or higher).';
      }
      return null;
    }
    final int? number = int.tryParse(text);
    if (number == null || number < 0) {
      return 'Enter a whole number (zero or higher).';
    }
    if (key == 'level' && number < 1) return 'Level must be at least 1.';
    if (key == 'reward_duration_months' && number < 1) {
      return 'Duration must be at least 1 month.';
    }
    return null;
  }

  void _blocked() {
    final ScaffoldMessengerState? messenger = ScaffoldMessenger.maybeOf(
      context,
    );
    if (messenger != null) {
      _showToast(messenger, 'Saving the level — please wait.');
    }
  }

  Future<void> _save() async {
    if (_saving) return;
    final FormState? form = _formKey.currentState;
    if (form == null || !form.validate()) return;
    FocusScope.of(context).unfocus();
    final ScaffoldMessengerState messenger = ScaffoldMessenger.of(context);
    setState(() {
      _saving = true;
      _error = null;
    });

    final Map<String, dynamic> payload = <String, dynamic>{};
    for (final MapEntry<String, TextEditingController> entry
        in _fields.entries) {
      final String key = entry.key;
      final String value = entry.value.text.trim();
      if (_intKeys.contains(key)) {
        payload[key] = int.tryParse(value) ?? 0;
      } else if (_doubleKeys.contains(key)) {
        payload[key] = double.tryParse(value) ?? 0;
      } else {
        payload[key] = value.isEmpty ? null : value;
      }
    }
    payload['is_active'] = _active;

    try {
      await widget.api.patch(
        'admin/rank-levels/${widget.level['id']}',
        widget.token,
        payload,
      );
      _showToast(messenger, 'Ranking level saved.', success: true);
      if (mounted) Navigator.of(context).pop(true);
      return;
    } on AuthException catch (error) {
      _showToast(messenger, error.message, error: true);
      if (!mounted) return;
      setState(() {
        _saving = false;
        _error = error.message;
      });
    } catch (error) {
      _showToast(messenger, 'Unexpected error: $error', error: true);
      if (!mounted) return;
      setState(() {
        _saving = false;
        _error = '$error';
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    final double keyboardInset = MediaQuery.viewInsetsOf(context).bottom;

    return PopScope(
      canPop: !_saving,
      onPopInvokedWithResult: (bool didPop, Object? result) {
        if (!didPop) _blocked();
      },
      child: Padding(
        padding: EdgeInsets.only(bottom: keyboardInset),
        child: Container(
          clipBehavior: Clip.antiAlias,
          decoration: BoxDecoration(
            color: theme.colorScheme.surface,
            borderRadius: const BorderRadius.vertical(
              top: Radius.circular(28),
            ),
          ),
          child: SafeArea(
            top: false,
            child: Column(
              children: <Widget>[
                _buildHeader(theme),
                Expanded(
                  child: Form(
                    key: _formKey,
                    child: ListView(
                      padding: const EdgeInsets.fromLTRB(18, 16, 18, 20),
                      children: <Widget>[
                        _sectionHeader(
                          theme,
                          'Rank identity',
                          'Basic information shown to users',
                          _Palette.ocean,
                        ),
                        _TileRow(
                          spacing: 12,
                          minItemWidth: 140,
                          children: <Widget>[
                            _field('level'),
                            _field('sort_order'),
                          ],
                        ),
                        _field('name', hint: 'e.g. Silver achiever'),
                        _field(
                          'icon',
                          hint: 'Optional icon URL or asset path',
                        ),
                        const SizedBox(height: 4),
                        _sectionHeader(
                          theme,
                          'Eligibility requirements',
                          'What a user must achieve to qualify',
                          _Palette.berry,
                        ),
                        _field('minimum_subscribed_referrals'),
                        _field(
                          'minimum_referral_cash_inflow_usd',
                          hint: '0.00',
                        ),
                        _field('minimum_user_cash_inflow_usd', hint: '0.00'),
                        const SizedBox(height: 4),
                        _sectionHeader(
                          theme,
                          'Reward configuration',
                          'Set the payout and its duration',
                          _Palette.mint,
                        ),
                        _TileRow(
                          spacing: 12,
                          minItemWidth: 150,
                          children: <Widget>[
                            _field('monthly_reward_usd', hint: '0.00'),
                            _field('reward_duration_months'),
                          ],
                        ),
                        _buildValueCard(theme),
                        _buildActiveCard(theme),
                      ],
                    ),
                  ),
                ),
                _buildFooter(theme),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildHeader(ThemeData theme) => Container(
    padding: const EdgeInsets.fromLTRB(18, 10, 10, 16),
    decoration: const BoxDecoration(gradient: _Palette.hero),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        Center(
          child: Container(
            width: 44,
            height: 4,
            decoration: BoxDecoration(
              color: Colors.white.withValues(alpha: .55),
              borderRadius: BorderRadius.circular(999),
            ),
          ),
        ),
        const SizedBox(height: 12),
        Row(
          children: <Widget>[
            Container(
              width: 44,
              height: 44,
              decoration: BoxDecoration(
                color: Colors.white.withValues(alpha: .2),
                borderRadius: BorderRadius.circular(14),
                border: Border.all(color: Colors.white.withValues(alpha: .3)),
              ),
              child: const Icon(Icons.edit_rounded, color: Colors.white),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  const Text(
                    'Edit ranking level',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                      color: Colors.white,
                      fontSize: 18,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    '${widget.level['name'] ?? 'Rank'} · Level '
                    '${widget.level['level'] ?? ''}',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                      color: Colors.white.withValues(alpha: .85),
                      fontSize: 12.5,
                    ),
                  ),
                ],
              ),
            ),
            IconButton(
              tooltip: 'Close',
              onPressed: _saving ? null : () => Navigator.of(context).pop(),
              icon: const Icon(Icons.close_rounded, color: Colors.white),
              style: IconButton.styleFrom(
                backgroundColor: Colors.white.withValues(alpha: .16),
                shape: const CircleBorder(),
              ),
            ),
          ],
        ),
      ],
    ),
  );

  Widget _buildFooter(ThemeData theme) => Container(
    padding: const EdgeInsets.fromLTRB(18, 12, 18, 12),
    decoration: BoxDecoration(
      border: Border(
        top: BorderSide(
          color: theme.colorScheme.outlineVariant.withValues(alpha: .8),
        ),
      ),
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        if (_error != null) ...<Widget>[
          _ErrorBanner(message: _error!),
          const SizedBox(height: 12),
        ],
        _GradientButton(
          label: _saving ? 'Saving level…' : 'Save changes',
          icon: _saving ? null : Icons.save_rounded,
          busy: _saving,
          onPressed: _saving ? null : _save,
        ),
        const SizedBox(height: 8),
        Text(
          'Changes apply to future qualification checks. Users already '
          'qualified keep their current tier.',
          textAlign: TextAlign.center,
          style: theme.textTheme.labelSmall?.copyWith(
            color: theme.colorScheme.onSurfaceVariant,
            height: 1.35,
          ),
        ),
      ],
    ),
  );

  Widget _sectionHeader(
    ThemeData theme,
    String title,
    String subtitle,
    LinearGradient gradient,
  ) => Padding(
    padding: const EdgeInsets.only(bottom: 12, top: 6),
    child: Row(
      children: <Widget>[
        Container(
          width: 30,
          height: 30,
          decoration: BoxDecoration(
            gradient: gradient,
            borderRadius: BorderRadius.circular(10),
          ),
          child: const Icon(
            Icons.auto_awesome_rounded,
            size: 16,
            color: Colors.white,
          ),
        ),
        const SizedBox(width: 10),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              Text(
                title,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: theme.textTheme.titleSmall?.copyWith(
                  fontWeight: FontWeight.w800,
                ),
              ),
              Text(
                subtitle,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: theme.textTheme.labelSmall?.copyWith(
                  color: theme.colorScheme.onSurfaceVariant,
                ),
              ),
            ],
          ),
        ),
      ],
    ),
  );

  Widget _buildValueCard(ThemeData theme) {
    final double monthly =
        double.tryParse(_fields['monthly_reward_usd']!.text.trim()) ?? 0;
    final int months =
        int.tryParse(_fields['reward_duration_months']!.text.trim()) ?? 0;
    final double total = monthly * months;

    return Container(
      margin: const EdgeInsets.only(bottom: 14),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(18),
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: <Color>[
            _Palette.green.withValues(alpha: .12),
            _Palette.violet.withValues(alpha: .1),
          ],
        ),
        border: Border.all(color: _Palette.green.withValues(alpha: .3)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          const Row(
            children: <Widget>[
              Icon(
                Icons.savings_rounded,
                size: 18,
                color: _Palette.green,
              ),
              SizedBox(width: 8),
              Expanded(
                child: Text(
                  'Lifetime value of this rank',
                  style: TextStyle(
                    color: _Palette.green,
                    fontWeight: FontWeight.w800,
                    fontSize: 13,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 8),
          Text(
            '\$${formatGroupedNumber(monthly, 2)} × $months month'
            '${months == 1 ? '' : 's'} = '
            '\$${formatGroupedNumber(total, 2)} per qualifying user.',
            style: theme.textTheme.bodyMedium?.copyWith(
              fontWeight: FontWeight.w700,
              height: 1.35,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildActiveCard(ThemeData theme) => Container(
    decoration: BoxDecoration(
      gradient: LinearGradient(
        begin: Alignment.centerLeft,
        end: Alignment.centerRight,
        colors: <Color>[
          (_active ? _Palette.green : _Palette.amber).withValues(alpha: .13),
          (_active ? _Palette.green : _Palette.amber).withValues(alpha: .03),
        ],
      ),
      borderRadius: BorderRadius.circular(18),
      border: Border.all(
        color: (_active ? _Palette.green : _Palette.amber).withValues(alpha: .3),
      ),
    ),
    child: SwitchListTile.adaptive(
      value: _active,
      onChanged: _saving
          ? null
          : (bool value) => setState(() {
              _active = value;
              _error = null;
            }),
      secondary: Icon(
        _active
            ? Icons.check_circle_outline_rounded
            : Icons.pause_circle_outline_rounded,
        color: _active ? _Palette.green : _Palette.amber,
      ),
      title: const Text(
        'Level is active',
        style: TextStyle(fontWeight: FontWeight.w700),
      ),
      subtitle: Text(
        _active
            ? 'Users can qualify for this level.'
            : 'This level is hidden from qualification.',
        style: theme.textTheme.labelSmall?.copyWith(
          color: theme.colorScheme.onSurfaceVariant,
        ),
      ),
      contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 4),
    ),
  );

  Widget _field(String key, {String? hint}) {
    final ThemeData theme = Theme.of(context);
    final ColorScheme scheme = theme.colorScheme;
    final bool isText = key == 'name' || key == 'icon';
    return Padding(
      padding: const EdgeInsets.only(bottom: 14),
      child: TextFormField(
        controller: _fields[key],
        textCapitalization: key == 'name'
            ? TextCapitalization.words
            : TextCapitalization.none,
        keyboardType: isText
            ? TextInputType.text
            : TextInputType.numberWithOptions(
                decimal: _doubleKeys.contains(key),
              ),
        validator: (String? value) => _validate(key, value),
        decoration: InputDecoration(
          labelText: _labels[key] ?? key,
          hintText: hint,
          prefixIcon: Icon(_icons[key] ?? Icons.edit_outlined, size: 20),
          filled: true,
          fillColor: scheme.onSurface.withValues(alpha: .04),
          contentPadding: const EdgeInsets.symmetric(
            horizontal: 14,
            vertical: 15,
          ),
          border: OutlineInputBorder(
            borderRadius: BorderRadius.circular(14),
            borderSide: BorderSide.none,
          ),
          enabledBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(14),
            borderSide: BorderSide.none,
          ),
          focusedBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(14),
            borderSide: const BorderSide(color: _Palette.indigo, width: 1.6),
          ),
          errorBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(14),
            borderSide: const BorderSide(color: _Palette.rose, width: 1.4),
          ),
          focusedErrorBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(14),
            borderSide: const BorderSide(color: _Palette.rose, width: 1.8),
          ),
        ),
      ),
    );
  }
}

// ---------------------------------------------------------------------------
// Confirm dialog
// ---------------------------------------------------------------------------

class _ConfirmDialog extends StatelessWidget {
  const _ConfirmDialog({
    required this.title,
    required this.icon,
    required this.gradient,
    required this.message,
    required this.confirmLabel,
    required this.cancelLabel,
  });

  final String title;
  final IconData icon;
  final LinearGradient gradient;
  final String message;
  final String confirmLabel;
  final String cancelLabel;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    return Dialog(
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(26)),
      child: ConstrainedBox(
        constraints: const BoxConstraints(maxWidth: 420),
        child: Padding(
          padding: const EdgeInsets.fromLTRB(22, 22, 22, 16),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              Row(
                children: <Widget>[
                  _IconBadge(gradient: gradient, icon: icon),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Text(
                      title,
                      style: theme.textTheme.titleMedium?.copyWith(
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 14),
              Text(
                message,
                style: theme.textTheme.bodyMedium?.copyWith(
                  color: theme.colorScheme.onSurfaceVariant,
                  height: 1.45,
                ),
              ),
              const SizedBox(height: 20),
              Wrap(
                alignment: WrapAlignment.end,
                spacing: 8,
                runSpacing: 8,
                children: <Widget>[
                  TextButton(
                    onPressed: () => Navigator.of(context).pop(false),
                    child: Text(cancelLabel),
                  ),
                  _GradientButton(
                    label: confirmLabel,
                    gradient: gradient,
                    onPressed: () => Navigator.of(context).pop(true),
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


// ---------------------------------------------------------------------------
// Shared primitives (mirrored from the other admin consoles)
// ---------------------------------------------------------------------------

class _TileRow extends StatelessWidget {
  const _TileRow({
    required this.children,
    required this.minItemWidth,
    this.spacing = 12,
  });

  final List<Widget> children;
  final double minItemWidth;
  final double spacing;

  @override
  Widget build(BuildContext context) {
    if (children.isEmpty) return const SizedBox.shrink();
    return LayoutBuilder(
      builder: (BuildContext context, BoxConstraints constraints) {
        final double span = constraints.maxWidth.isFinite
            ? constraints.maxWidth
            : minItemWidth;
        int columns = ((span + spacing) / (minItemWidth + spacing)).floor();
        if (columns < 1) columns = 1;
        if (columns > children.length) columns = children.length;
        final double width = (span - spacing * (columns - 1)) / columns;
        return Wrap(
          spacing: spacing,
          runSpacing: spacing,
          children: <Widget>[
            for (final Widget child in children)
              SizedBox(width: width, child: child),
          ],
        );
      },
    );
  }
}

class _IconBadge extends StatelessWidget {
  const _IconBadge({
    required this.gradient,
    required this.icon,
    this.size = 46,
  });

  final LinearGradient gradient;
  final IconData icon;
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

class _MetaChip extends StatelessWidget {
  const _MetaChip({
    required this.icon,
    required this.color,
    required this.label,
  });

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
    child: Row(
      mainAxisSize: MainAxisSize.min,
      children: <Widget>[
        Icon(icon, size: 14, color: color),
        const SizedBox(width: 5),
        Flexible(
          child: Text(
            label,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: TextStyle(
              color: color,
              fontSize: 11.5,
              fontWeight: FontWeight.w700,
            ),
          ),
        ),
      ],
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
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: <Widget>[
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
          ],
        ),
      ),
    ),
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
    padding: const EdgeInsets.fromLTRB(14, 14, 14, 14),
    decoration: BoxDecoration(
      gradient: gradient,
      borderRadius: BorderRadius.circular(20),
      boxShadow: <BoxShadow>[
        BoxShadow(
          color: gradient.colors.last.withValues(alpha: .3),
          blurRadius: 16,
          offset: const Offset(0, 8),
        ),
      ],
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        Row(
          children: <Widget>[
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
          ],
        ),
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

class _MetricBox extends StatelessWidget {
  const _MetricBox({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 9),
      decoration: BoxDecoration(
        color: theme.colorScheme.surface,
        borderRadius: BorderRadius.circular(13),
        border: Border.all(
          color: theme.colorScheme.outlineVariant.withValues(alpha: .8),
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Text(
            label,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: theme.textTheme.labelSmall?.copyWith(
              color: theme.colorScheme.onSurfaceVariant,
              fontWeight: FontWeight.w600,
            ),
          ),
          const SizedBox(height: 2),
          FittedBox(
            fit: BoxFit.scaleDown,
            alignment: Alignment.centerLeft,
            child: Text(
              value,
              maxLines: 1,
              style: theme.textTheme.titleSmall?.copyWith(
                fontWeight: FontWeight.w800,
              ),
            ),
          ),
        ],
      ),
    );
  }
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
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    return TextField(
      controller: controller,
      onChanged: onChanged,
      textInputAction: TextInputAction.search,
      decoration: InputDecoration(
        hintText: hint,
        isDense: true,
        prefixIcon: const Icon(Icons.search_rounded, size: 20),
        filled: true,
        fillColor: theme.colorScheme.onSurface.withValues(alpha: .045),
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
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(16),
          borderSide: BorderSide(color: _Palette.indigo, width: 1.6),
        ),
      ),
    );
  }
}

class _AppBarAction extends StatelessWidget {
  const _AppBarAction({
    required this.icon,
    required this.tooltip,
    required this.onPressed,
  });

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
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    return TextField(
      controller: controller,
      keyboardType: keyboard,
      obscureText: obscure,
      onSubmitted: onSubmitted,
      decoration: InputDecoration(
        labelText: label,
        prefixIcon: Icon(icon, size: 20),
        suffixIcon: suffix,
        filled: true,
        fillColor: theme.colorScheme.onSurface.withValues(alpha: .04),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(14),
          borderSide: BorderSide.none,
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(14),
          borderSide: BorderSide.none,
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(14),
          borderSide: const BorderSide(color: _Palette.indigo, width: 1.6),
        ),
      ),
    );
  }
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
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
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
      ],
    ),
  );
}

class _GradientButton extends StatelessWidget {
  const _GradientButton({
    required this.label,
    required this.onPressed,
    this.icon,
    this.gradient,
    this.busy = false,
  });

  final String label;
  final VoidCallback? onPressed;
  final IconData? icon;
  final LinearGradient? gradient;
  final bool busy;

  @override
  Widget build(BuildContext context) {
    final LinearGradient fill = gradient ?? _Palette.hero;
    final bool enabled = onPressed != null;
    final Widget? leading = busy
        ? const SizedBox(
            width: 18,
            height: 18,
            child: CircularProgressIndicator(
              strokeWidth: 2.2,
              color: Colors.white,
            ),
          )
        : icon == null
        ? null
        : Icon(icon, color: Colors.white, size: 20);
    return Opacity(
      opacity: enabled ? 1 : .55,
      child: DecoratedBox(
        decoration: BoxDecoration(
          gradient: fill,
          borderRadius: BorderRadius.circular(16),
          boxShadow: enabled
              ? <BoxShadow>[
                  BoxShadow(
                    color: fill.colors.first.withValues(alpha: .35),
                    blurRadius: 18,
                    offset: const Offset(0, 9),
                  ),
                ]
              : null,
        ),
        child: Material(
          color: Colors.transparent,
          child: InkWell(
            borderRadius: BorderRadius.circular(16),
            onTap: onPressed,
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 15),
              child: Row(
                mainAxisSize: MainAxisSize.min,
                mainAxisAlignment: MainAxisAlignment.center,
                children: <Widget>[
                  if (leading != null) ...<Widget>[
                    leading,
                    const SizedBox(width: 10),
                  ],
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

class _EmptyState extends StatelessWidget {
  const _EmptyState({
    required this.icon,
    required this.gradient,
    required this.title,
    required this.message,
    this.actionLabel,
    this.onAction,
  });

  final IconData icon;
  final LinearGradient gradient;
  final String title;
  final String message;
  final String? actionLabel;
  final VoidCallback? onAction;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    return Center(
      child: SingleChildScrollView(
        padding: const EdgeInsets.all(28),
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 380),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: <Widget>[
              Container(
                width: 96,
                height: 96,
                decoration: BoxDecoration(
                  gradient: gradient,
                  shape: BoxShape.circle,
                  boxShadow: <BoxShadow>[
                    BoxShadow(
                      color: gradient.colors.last.withValues(alpha: .35),
                      blurRadius: 26,
                      offset: const Offset(0, 12),
                    ),
                  ],
                ),
                child: Icon(icon, size: 44, color: Colors.white),
              ),
              const SizedBox(height: 20),
              Text(
                title,
                textAlign: TextAlign.center,
                style: theme.textTheme.titleLarge?.copyWith(
                  fontWeight: FontWeight.w800,
                ),
              ),
              const SizedBox(height: 8),
              Text(
                message,
                textAlign: TextAlign.center,
                style: theme.textTheme.bodyMedium?.copyWith(
                  color: theme.colorScheme.onSurfaceVariant,
                  height: 1.45,
                ),
              ),
              if (actionLabel != null && onAction != null) ...<Widget>[
                const SizedBox(height: 22),
                _GradientButton(
                  label: actionLabel!,
                  onPressed: onAction,
                  gradient: gradient,
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}

class _SkeletonList extends StatefulWidget {
  const _SkeletonList();

  @override
  State<_SkeletonList> createState() => _SkeletonListState();
}

class _SkeletonListState extends State<_SkeletonList>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 1100),
  )..repeat(reverse: true);

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    final Color tint = theme.colorScheme.onSurface.withValues(alpha: .08);
    // SliverFillRemaining hands this child a tight height, so the placeholder
    // list scrolls rather than overflowing on short screens.
    return SingleChildScrollView(
      padding: const EdgeInsets.fromLTRB(16, 20, 16, 40),
      child: FadeTransition(
        opacity: Tween<double>(begin: .45, end: 1).animate(_controller),
        child: Column(
          children: <Widget>[
            for (int i = 0; i < 4; i++)
              Container(
                margin: EdgeInsets.only(bottom: i == 3 ? 0 : 14),
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: theme.colorScheme.surface,
                  borderRadius: BorderRadius.circular(24),
                  border: Border.all(
                    color: theme.colorScheme.outlineVariant.withValues(
                      alpha: .6,
                    ),
                  ),
                ),
                child: Row(
                  children: <Widget>[
                    Container(
                      width: 54,
                      height: 54,
                      decoration: BoxDecoration(
                        color: tint,
                        borderRadius: BorderRadius.circular(16),
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: <Widget>[
                          Container(
                            height: 13,
                            decoration: BoxDecoration(
                              color: tint,
                              borderRadius: BorderRadius.circular(6),
                            ),
                          ),
                          const SizedBox(height: 8),
                          FractionallySizedBox(
                            widthFactor: .6,
                            child: Container(
                              height: 10,
                              decoration: BoxDecoration(
                                color: tint,
                                borderRadius: BorderRadius.circular(6),
                              ),
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

// ---------------------------------------------------------------------------
// Create listing sheet
// ---------------------------------------------------------------------------
