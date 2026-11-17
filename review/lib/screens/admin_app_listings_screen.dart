import 'dart:math' as math;

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';

import '../core/currency/money.dart';
import '../services/auth_api.dart';

// ---------------------------------------------------------------------------
// Design language
// ---------------------------------------------------------------------------

/// Shared, deliberately colourful palette for the admin surfaces.
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

/// Wraps the app theme in a seeded, vivid colour scheme so both admin screens
/// look identical no matter which theme the host app ships.
ThemeData _adminTheme(BuildContext context) {
  final ThemeData base = Theme.of(context);
  return base.copyWith(
    colorScheme: ColorScheme.fromSeed(
      seedColor: _Palette.indigo,
      brightness: base.brightness,
    ),
  );
}

Color _statusColor(String status) => switch (status) {
  'active' => _Palette.green,
  'published' => _Palette.green,
  'rejected' => _Palette.rose,
  'draft' => _Palette.amber,
  'archived' => _Palette.sky,
  _ => _Palette.violet,
};

IconData _statusIcon(String status) => switch (status) {
  'active' => Icons.bolt_rounded,
  'published' => Icons.verified_rounded,
  'rejected' => Icons.block_rounded,
  'draft' => Icons.edit_note_rounded,
  'archived' => Icons.inventory_2_rounded,
  _ => Icons.hourglass_bottom_rounded,
};

/// Tolerant `#RRGGBB` / `#AARRGGBB` parser used for the per-app brand colour.
Color? _parseHex(String? value) {
  if (value == null) return null;
  String hex = value.trim().replaceFirst('#', '');
  if (hex.length == 6) hex = 'FF$hex';
  if (hex.length != 8) return null;
  final int? parsed = int.tryParse(hex, radix: 16);
  return parsed == null ? null : Color(parsed);
}

String? _required(String? value) =>
    value == null || value.trim().isEmpty ? 'Required' : null;

Map<String, dynamic> _map(dynamic value) =>
    value is Map ? Map<String, dynamic>.from(value) : <String, dynamic>{};

// ---------------------------------------------------------------------------
// Screen
// ---------------------------------------------------------------------------

class AdminAppListingsScreen extends StatefulWidget {
  const AdminAppListingsScreen({super.key, required this.api});

  final AuthApi api;

  @override
  State<AdminAppListingsScreen> createState() => _AdminAppListingsScreenState();
}

class _AdminAppListingsScreenState extends State<AdminAppListingsScreen> {
  final TextEditingController _email = TextEditingController(
    text: 'admin@tlstore.test',
  );
  final TextEditingController _password = TextEditingController(
    text: 'meandmyson',
  );
  final TextEditingController _search = TextEditingController();

  String? _token;
  List<Map<String, dynamic>> _apps = <Map<String, dynamic>>[];
  List<Map<String, dynamic>> _versions = <Map<String, dynamic>>[];
  List<Map<String, dynamic>> _currencies = <Map<String, dynamic>>[];
  bool _loading = false;
  String? _error;
  String _statusFilter = 'all';
  String _query = '';
  bool _obscurePassword = true;

  @override
  void dispose() {
    _email.dispose();
    _password.dispose();
    _search.dispose();
    super.dispose();
  }

  // -- data --------------------------------------------------------------

  List<Map<String, dynamic>> _pageRows(dynamic value) {
    final Map<String, dynamic> page = _map(value);
    final dynamic rows = page['data'] ?? value;
    return rows is List ? rows.map(_map).toList() : <Map<String, dynamic>>[];
  }

  /// Currency code -> minor units, straight from the `currencies` endpoint.
  Map<String, int> get _precisionByCode => <String, int>{
    for (final Map<String, dynamic> row in _currencies)
      if (row['code'] != null)
        row['code'].toString().toUpperCase():
            int.tryParse(row['minor_units']?.toString() ?? '') ?? 2,
  };

  List<Map<String, dynamic>> get _visibleApps {
    final String needle = _query.trim().toLowerCase();
    return _apps.where((Map<String, dynamic> app) {
      final String status = app['status']?.toString() ?? 'draft';
      if (_statusFilter != 'all' && status != _statusFilter) return false;
      if (needle.isEmpty) return true;
      final String haystack =
          '${app['name']} ${app['developer']} ${app['store']} '
                  '${app['short_code']} ${app['category']}'
              .toLowerCase();
      return haystack.contains(needle);
    }).toList();
  }

  int _countWhere(String status) => _apps
      .where(
        (Map<String, dynamic> app) =>
            (app['status']?.toString() ?? 'draft') == status,
      )
      .length;

  Future<void> _login() async {
    final ScaffoldMessengerState messenger = ScaffoldMessenger.of(context);
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
        throw const AuthException('Admin sign-in did not return a token.');
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
      _toast(messenger, 'Signed in. Listings are up to date.');
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
        widget.api.get('admin/apps?per_page=100', token),
        widget.api.get('admin/review-templates?per_page=100', token),
        widget.api.getPublic('currencies'),
      ]);
      if (!mounted) return;
      setState(() {
        _apps = _pageRows(results[0]['data']);
        final List<Map<String, dynamic>> templates = _pageRows(
          results[1]['data'],
        );
        _versions = <Map<String, dynamic>>[
          for (final Map<String, dynamic> template in templates)
            for (final Map<String, dynamic> version
                in (template['versions'] is List
                    ? (template['versions'] as List).map(_map)
                    : const <Map<String, dynamic>>[]))
              if (version['status'] == 'published')
                <String, dynamic>{
                  ...version,
                  'template_name': template['name'],
                  'template_id': template['id'],
                },
        ];
        final dynamic currencyRows = results[2]['data'];
        _currencies = currencyRows is List
            ? currencyRows.map(_map).toList()
            : <Map<String, dynamic>>[];
      });
    } on AuthException catch (error) {
      if (mounted) setState(() => _error = error.message);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
    if (!mounted || !announce) return;
    if (_error == null) {
      _toast(messenger, 'Refreshed ${_apps.length} listing(s).');
    }
  }

  void _signOut() {
    setState(() {
      _token = null;
      _apps = <Map<String, dynamic>>[];
      _versions = <Map<String, dynamic>>[];
      _currencies = <Map<String, dynamic>>[];
      _error = null;
      _query = '';
      _statusFilter = 'all';
      _search.clear();
    });
  }

  Future<void> _createListing({
    Map<String, dynamic>? source,
    bool clone = false,
  }) async {
    final String? token = _token;
    if (token == null) return;
    if (_versions.isEmpty) {
      _message(
        'Create and publish a question template before adding a listing.',
      );
      return;
    }
    final bool? saved = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      backgroundColor: Colors.transparent,
      barrierColor: Colors.black.withValues(alpha: .55),
      // Dialog and sheet routes are pushed above this screen's Theme widget,
      // so the admin palette has to be reapplied inside the route.
      builder: (BuildContext sheetContext) => Theme(
        data: _adminTheme(sheetContext),
        child: _AppListingForm(
          api: widget.api,
          token: token,
          versions: _versions,
          currencies: _currencies,
          existing: source,
          clone: clone,
        ),
      ),
    );
    if (saved == true) await _load();
  }

  Future<void> _editListing(Map<String, dynamic> app) =>
      _createListing(source: app);

  Future<void> _cloneListing(Map<String, dynamic> app) =>
      _createListing(source: app, clone: true);

  Future<void> _changeStatus(Map<String, dynamic> app, String status) async {
    final String? token = _token;
    if (token == null) return;
    final ScaffoldMessengerState messenger = ScaffoldMessenger.of(context);
    final String name = app['name']?.toString() ?? 'This listing';
    final bool? confirmed = await _confirmDialog(
      title: status == 'active' ? 'Activate listing?' : 'Move to draft?',
      message: status == 'active'
          ? '$name will be visible to reviewers and can start earning reviews.'
          : '$name will stop collecting reviews until you activate it again.',
      confirmLabel: status == 'active' ? 'Activate' : 'Set draft',
      color: status == 'active' ? _Palette.green : _Palette.amber,
      icon: status == 'active'
          ? Icons.rocket_launch_rounded
          : Icons.pause_circle_outline_rounded,
    );
    if (confirmed != true) return;
    try {
      await widget.api.patch(
        'admin/apps/${app['id']}',
        token,
        <String, dynamic>{'status': status},
      );
      await _load();
      _toast(
        messenger,
        '$name is now ${status == 'active' ? 'active' : 'a draft'}.',
        success: true,
      );
    } on AuthException catch (error) {
      _toast(messenger, error.message, error: true);
    }
  }

  Future<bool?> _confirmDialog({
    required String title,
    required String message,
    required String confirmLabel,
    required Color color,
    required IconData icon,
  }) => showDialog<bool>(
    context: context,
    builder: (BuildContext dialogContext) {
      final ThemeData theme = Theme.of(dialogContext);
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
                    _IconBadge(gradient: _gradientOf(color), icon: icon),
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
                    height: 1.4,
                  ),
                ),
                const SizedBox(height: 20),
                Wrap(
                  alignment: WrapAlignment.end,
                  spacing: 8,
                  runSpacing: 8,
                  children: <Widget>[
                    TextButton(
                      onPressed: () => Navigator.pop(dialogContext, false),
                      child: const Text('Cancel'),
                    ),
                    _GradientButton(
                      label: confirmLabel,
                      gradient: _gradientOf(color),
                      onPressed: () => Navigator.pop(dialogContext, true),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ),
      );
    },
  );

  // -- feedback ----------------------------------------------------------

  /// Resolves the messenger *before* any `await` so a snack bar can never be
  /// shown from a defunct element.
  void _toast(
    ScaffoldMessengerState messenger,
    String text, {
    bool error = false,
    bool success = false,
  }) {
    final Color background = error
        ? _Palette.rose
        : success
        ? _Palette.green
        : _Palette.indigo;
    messenger
      ..hideCurrentSnackBar()
      ..showSnackBar(
        SnackBar(
          behavior: SnackBarBehavior.floating,
          backgroundColor: background,
          margin: const EdgeInsets.fromLTRB(14, 0, 14, 18),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(14),
          ),
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

  void _message(String value) => _toast(ScaffoldMessenger.of(context), value);

  LinearGradient _gradientOf(Color color) => LinearGradient(
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
    colors: <Color>[color, Color.lerp(color, Colors.black, .22)!],
  );

  // -- build -------------------------------------------------------------

  @override
  Widget build(BuildContext context) {
    return Theme(
      data: _adminTheme(context),
      child: Builder(
        builder: (BuildContext context) =>
            _token == null ? _buildSignIn(context) : _buildDashboard(context),
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
                        Icons.storefront_rounded,
                        size: 38,
                        color: Colors.white,
                      ),
                    ),
                    const SizedBox(height: 18),
                    const Text(
                      'App listings console',
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
                      'Publish apps, wire up questionnaires and fund review '
                      'campaigns from one place.',
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
                            obscure: _obscurePassword,
                            onSubmitted: (_) => _login(),
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
                            icon: _loading ? null : Icons.arrow_forward_rounded,
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
    final List<Map<String, dynamic>> visible = _visibleApps;
    final Map<String, int> precision = _precisionByCode;

    return Scaffold(
      backgroundColor: theme.colorScheme.surface,
      floatingActionButton: _GradientFab(
        icon: Icons.add_rounded,
        label: 'Create listing',
        onPressed: _createListing,
      ),
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
              title: const Text(
                'App listings',
                style: TextStyle(
                  color: Colors.white,
                  fontWeight: FontWeight.w800,
                  letterSpacing: -.3,
                ),
              ),
              actions: <Widget>[
                _AppBarAction(
                  icon: Icons.refresh_rounded,
                  tooltip: 'Refresh listings',
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
                          label: 'Total listings',
                          value: '${_apps.length}',
                          icon: Icons.apps_rounded,
                          gradient: _Palette.ocean,
                        ),
                        _StatTile(
                          label: 'Active',
                          value: '${_countWhere('active')}',
                          icon: Icons.bolt_rounded,
                          gradient: _Palette.mint,
                        ),
                        _StatTile(
                          label: 'Drafts',
                          value: '${_countWhere('draft')}',
                          icon: Icons.edit_note_rounded,
                          gradient: _Palette.sunset,
                        ),
                        _StatTile(
                          label: 'Questionnaires',
                          value: '${_versions.length}',
                          icon: Icons.fact_check_rounded,
                          gradient: _Palette.berry,
                        ),
                      ],
                    ),
                    const SizedBox(height: 14),
                    _SearchField(
                      controller: _search,
                      hint: 'Search name, developer, store or category',
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
                          count: _apps.length,
                          color: _Palette.indigo,
                          selected: _statusFilter == 'all',
                          onSelected: () =>
                              setState(() => _statusFilter = 'all'),
                        ),
                        _FilterChip(
                          label: 'Active',
                          count: _countWhere('active'),
                          color: _Palette.green,
                          selected: _statusFilter == 'active',
                          onSelected: () =>
                              setState(() => _statusFilter = 'active'),
                        ),
                        _FilterChip(
                          label: 'Draft',
                          count: _countWhere('draft'),
                          color: _Palette.amber,
                          selected: _statusFilter == 'draft',
                          onSelected: () =>
                              setState(() => _statusFilter = 'draft'),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ),
            if (_loading && _apps.isEmpty)
              const SliverFillRemaining(
                hasScrollBody: false,
                child: _SkeletonList(),
              )
            else if (_error != null && _apps.isEmpty)
              SliverFillRemaining(
                hasScrollBody: false,
                child: _EmptyState(
                  icon: Icons.cloud_off_rounded,
                  gradient: _Palette.sunset,
                  title: 'Could not load listings',
                  message: _error!,
                  actionLabel: 'Try again',
                  onAction: _load,
                ),
              )
            else if (visible.isEmpty)
              SliverFillRemaining(
                hasScrollBody: false,
                child: _EmptyState(
                  icon: _apps.isEmpty
                      ? Icons.storefront_rounded
                      : Icons.search_off_rounded,
                  gradient: _Palette.berry,
                  title: _apps.isEmpty
                      ? 'No app listings yet'
                      : 'Nothing matches that search',
                  message: _apps.isEmpty
                      ? 'Create the first listing and pair it with a published '
                            'questionnaire.'
                      : 'Try a different keyword or clear the status filter.',
                  actionLabel: _apps.isEmpty
                      ? 'Create app listing'
                      : 'Clear filters',
                  onAction: _apps.isEmpty
                      ? _createListing
                      : () => setState(() {
                          _query = '';
                          _statusFilter = 'all';
                          _search.clear();
                        }),
                ),
              )
            else
              SliverPadding(
                padding: const EdgeInsets.fromLTRB(16, 12, 16, 120),
                sliver: SliverList.separated(
                  itemCount: visible.length,
                  separatorBuilder: (BuildContext context, int index) =>
                      const SizedBox(height: 14),
                  itemBuilder: (BuildContext context, int index) {
                    final Map<String, dynamic> app = visible[index];
                    return _AppCard(
                      app: app,
                      precision: precision,
                      onActivate: () => _changeStatus(app, 'active'),
                      onDraft: () => _changeStatus(app, 'draft'),
                      onEdit: () => _editListing(app),
                      onClone: () => _cloneListing(app),
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
// App card
// ---------------------------------------------------------------------------

class _AppCard extends StatelessWidget {
  const _AppCard({
    required this.app,
    required this.precision,
    required this.onActivate,
    required this.onDraft,
    required this.onEdit,
    required this.onClone,
  });

  final Map<String, dynamic> app;
  final Map<String, int> precision;
  final VoidCallback onActivate;
  final VoidCallback onDraft;
  final VoidCallback onEdit;
  final VoidCallback onClone;

  Map<String, dynamic> _campaign() {
    final List<Map<String, dynamic>> campaigns = app['review_campaigns'] is List
        ? (app['review_campaigns'] as List).map(_map).toList()
        : <Map<String, dynamic>>[];
    return campaigns.isEmpty ? <String, dynamic>{} : _map(campaigns.first);
  }

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    final ColorScheme scheme = theme.colorScheme;
    final String status = app['status']?.toString() ?? 'draft';
    final Color statusColor = _statusColor(status);
    final Map<String, dynamic> campaign = _campaign();
    final Map<String, dynamic> version = _map(
      campaign['review_template_version'],
    );
    final Map<String, dynamic> template = _map(version['template']);
    final String templateName = template['name']?.toString() ?? 'Not assigned';
    final String versionLabel = version['version'] == null
        ? ''
        : ' · v${version['version']}';
    final List<dynamic> screenshots = app['screenshots'] is List
        ? app['screenshots'] as List
        : const <dynamic>[];
    final Color? brand = _parseHex(app['brand_color']?.toString());
    final bool featured =
        app['featured'] == true || app['featured']?.toString() == '1';

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
          Padding(
            padding: const EdgeInsets.fromLTRB(14, 14, 14, 12),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    _AppIcon(
                      url: app['app_icon']?.toString(),
                      fallbackLabel: app['name']?.toString() ?? 'A',
                      brand: brand,
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: <Widget>[
                          Text(
                            app['name']?.toString() ?? 'App',
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
                            style: theme.textTheme.titleMedium?.copyWith(
                              fontWeight: FontWeight.w800,
                              height: 1.2,
                            ),
                          ),
                          const SizedBox(height: 3),
                          Text(
                            <String>[
                                  app['store']?.toString() ?? '',
                                  app['developer']?.toString() ?? '',
                                ]
                                .where((String part) => part.isNotEmpty)
                                .join(' · '),
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
                    _StatusPill(status: status, color: statusColor),
                  ],
                ),
                const SizedBox(height: 12),
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: <Widget>[
                    _MetaChip(
                      icon: Icons.fact_check_rounded,
                      color: _Palette.violet,
                      label: '$templateName$versionLabel'.trim(),
                    ),
                    _MetaChip(
                      icon: Icons.collections_rounded,
                      color: _Palette.sky,
                      label:
                          '${screenshots.length} screenshot'
                          '${screenshots.length == 1 ? '' : 's'}',
                    ),
                    if ((app['install_count_label']?.toString() ?? '')
                        .isNotEmpty)
                      _MetaChip(
                        icon: Icons.download_rounded,
                        color: _Palette.teal,
                        label: app['install_count_label'].toString(),
                      ),
                    if ((app['category']?.toString() ?? '').isNotEmpty)
                      _MetaChip(
                        icon: Icons.category_rounded,
                        color: _Palette.orange,
                        label: app['category'].toString(),
                      ),
                    if (featured)
                      const _MetaChip(
                        icon: Icons.star_rounded,
                        color: _Palette.amber,
                        label: 'Featured',
                      ),
                  ],
                ),
              ],
            ),
          ),
          if (campaign.isNotEmpty)
            _CampaignStrip(campaign: campaign, precision: precision),
          Container(
            decoration: BoxDecoration(
              border: Border(
                top: BorderSide(
                  color: scheme.outlineVariant.withValues(alpha: .7),
                ),
              ),
            ),
            padding: const EdgeInsets.fromLTRB(8, 4, 8, 6),
            child: Row(
              children: <Widget>[
                Expanded(
                  child: Align(
                    alignment: Alignment.centerLeft,
                    child: Text(
                      'ID ${app['id'] ?? '—'}'
                      '${(app['short_code']?.toString() ?? '').isEmpty ? '' : ' · ${app['short_code']}'}',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: theme.textTheme.labelSmall?.copyWith(
                        color: scheme.onSurfaceVariant,
                      ),
                    ),
                  ),
                ),
                if (status != 'active')
                  TextButton.icon(
                    style: TextButton.styleFrom(
                      foregroundColor: _Palette.green,
                    ),
                    onPressed: onActivate,
                    icon: const Icon(Icons.bolt_rounded, size: 18),
                    label: const Text('Activate'),
                  ),
                if (status != 'draft')
                  TextButton.icon(
                    style: TextButton.styleFrom(
                      foregroundColor: _Palette.amber,
                    ),
                    onPressed: onDraft,
                    icon: const Icon(Icons.edit_note_rounded, size: 18),
                    label: const Text('Set draft'),
                  ),
                PopupMenuButton<String>(
                  tooltip: 'More listing actions',
                  onSelected: (String action) {
                    if (action == 'edit') onEdit();
                    if (action == 'clone') onClone();
                  },
                  itemBuilder: (BuildContext context) =>
                      const <PopupMenuEntry<String>>[
                        PopupMenuItem<String>(
                          value: 'edit',
                          child: ListTile(
                            dense: true,
                            leading: Icon(Icons.edit_outlined),
                            title: Text('Edit listing'),
                          ),
                        ),
                        PopupMenuItem<String>(
                          value: 'clone',
                          child: ListTile(
                            dense: true,
                            leading: Icon(Icons.copy_all_outlined),
                            title: Text('Clone as new listing'),
                          ),
                        ),
                      ],
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

/// Colourful money strip: reward pool, reward per review and capacity.
class _CampaignStrip extends StatelessWidget {
  const _CampaignStrip({required this.campaign, required this.precision});

  final Map<String, dynamic> campaign;
  final Map<String, int> precision;

  String _money(dynamic minor, String code) {
    final int units = precision[code] ?? 2;
    final int value = int.tryParse(minor?.toString() ?? '') ?? 0;
    final double amount = value / math.pow(10, units);
    return '$code ${formatGroupedNumber(amount, units)}';
  }

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    final String code = campaign['currency']?.toString().toUpperCase() ?? 'USD';
    final int pool =
        int.tryParse(campaign['paid_amount_minor']?.toString() ?? '') ?? 0;
    final int reward =
        int.tryParse(campaign['review_reward_minor']?.toString() ?? '') ?? 0;
    final int maximum =
        int.tryParse(campaign['maximum_review']?.toString() ?? '') ?? 0;
    final int committed = reward * maximum;
    final double ratio = pool <= 0
        ? 0
        : (committed / pool).clamp(0, 1).toDouble();
    final String status = campaign['status']?.toString() ?? 'draft';

    return Container(
      margin: const EdgeInsets.fromLTRB(14, 0, 14, 12),
      padding: const EdgeInsets.fromLTRB(14, 12, 14, 14),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(18),
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: <Color>[
            _Palette.indigo.withValues(alpha: .1),
            _Palette.pink.withValues(alpha: .1),
          ],
        ),
        border: Border.all(color: _Palette.indigo.withValues(alpha: .18)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Row(
            children: <Widget>[
              const Icon(
                Icons.payments_rounded,
                size: 18,
                color: _Palette.indigo,
              ),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  'Reward pool',
                  style: theme.textTheme.labelLarge?.copyWith(
                    fontWeight: FontWeight.w800,
                    color: _Palette.indigo,
                  ),
                ),
              ),
              _StatusPill(status: status, color: _statusColor(status)),
            ],
          ),
          const SizedBox(height: 10),
          _TileRow(
            spacing: 10,
            minItemWidth: 108,
            children: <Widget>[
              _MetricBox(label: 'Pool', value: _money(pool, code)),
              _MetricBox(label: 'Per review', value: _money(reward, code)),
              _MetricBox(label: 'Reviews', value: '$maximum'),
            ],
          ),
          const SizedBox(height: 10),
          ClipRRect(
            borderRadius: BorderRadius.circular(999),
            child: LinearProgressIndicator(
              value: ratio,
              minHeight: 7,
              backgroundColor: _Palette.indigo.withValues(alpha: .14),
              valueColor: AlwaysStoppedAnimation<Color>(
                ratio > .95 ? _Palette.rose : _Palette.green,
              ),
            ),
          ),
          const SizedBox(height: 6),
          Text(
            '${(ratio * 100).round()}% of the pool is committed '
            '(${_money(committed, code)} of ${_money(pool, code)}).',
            style: theme.textTheme.labelSmall?.copyWith(
              color: theme.colorScheme.onSurfaceVariant,
            ),
          ),
        ],
      ),
    );
  }
}

// ---------------------------------------------------------------------------
// Shared primitives
// ---------------------------------------------------------------------------

/// Overflow-proof equal-width row: computes how many tiles fit and never
/// forces a child past the available width.
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
    child: Row(
      mainAxisSize: MainAxisSize.min,
      children: <Widget>[
        Icon(_statusIcon(status), size: 13, color: color),
        const SizedBox(width: 4),
        Flexible(
          child: Text(
            status.toUpperCase(),
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: TextStyle(
              color: color,
              fontSize: 10,
              fontWeight: FontWeight.w800,
              letterSpacing: .6,
            ),
          ),
        ),
      ],
    ),
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

class _GradientFab extends StatelessWidget {
  const _GradientFab({
    required this.icon,
    required this.label,
    required this.onPressed,
  });

  final IconData icon;
  final String label;
  final VoidCallback onPressed;

  @override
  Widget build(BuildContext context) => Container(
    decoration: BoxDecoration(
      gradient: _Palette.hero,
      borderRadius: BorderRadius.circular(20),
      boxShadow: <BoxShadow>[
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
      label: Text(
        label,
        style: const TextStyle(
          color: Colors.white,
          fontWeight: FontWeight.w700,
        ),
      ),
    ),
  );
}

class _AppIcon extends StatelessWidget {
  const _AppIcon({
    required this.url,
    required this.fallbackLabel,
    this.brand,
    this.size = 54,
  });

  final String? url;
  final String fallbackLabel;
  final Color? brand;
  final double size;

  @override
  Widget build(BuildContext context) {
    final Color base = brand ?? _Palette.indigo;
    final String trimmed = fallbackLabel.trim();
    final Widget fallback = Container(
      decoration: BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: <Color>[base, Color.lerp(base, Colors.black, .3)!],
        ),
      ),
      alignment: Alignment.center,
      child: trimmed.isEmpty
          ? Icon(Icons.apps_rounded, color: Colors.white, size: size * .48)
          : Text(
              trimmed.substring(0, 1).toUpperCase(),
              style: TextStyle(
                color: Colors.white,
                fontSize: size * .42,
                fontWeight: FontWeight.w800,
              ),
            ),
    );
    return ClipRRect(
      borderRadius: BorderRadius.circular(16),
      child: SizedBox(
        width: size,
        height: size,
        child: url == null || !url!.startsWith('http')
            ? fallback
            : Image.network(
                url!,
                fit: BoxFit.cover,
                errorBuilder: (
                  BuildContext context,
                  Object error,
                  StackTrace? trace,
                ) => fallback,
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

enum _SaveStep { idle, app, campaign, media }

/// Snackbar helper that takes an already-resolved messenger, so it can be
/// called safely after an `await`.
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

class _AppListingForm extends StatefulWidget {
  const _AppListingForm({
    required this.api,
    required this.token,
    required this.versions,
    required this.currencies,
    this.existing,
    this.clone = false,
  });

  final AuthApi api;
  final String token;
  final List<Map<String, dynamic>> versions;
  final List<Map<String, dynamic>> currencies;
  final Map<String, dynamic>? existing;
  final bool clone;

  @override
  State<_AppListingForm> createState() => _AppListingFormState();
}

class _AppListingFormState extends State<_AppListingForm> {
  static const List<String> _textKeys = <String>[
    'name',
    'short_code',
    'developer',
    'category',
    'install_count_label',
    'description',
    'store_url',
    'app_icon',
    'brand_color',
    'paid_amount',
    'review_reward',
    'maximum_review',
  ];
  static const List<String> _plainKeys = <String>[
    'name',
    'short_code',
    'developer',
    'category',
    'description',
    'store_url',
    'brand_color',
  ];
  static const List<String> _moneyKeys = <String>[
    'paid_amount',
    'review_reward',
    'maximum_review',
  ];

  final GlobalKey<FormState> _formKey = GlobalKey<FormState>();
  late final Map<String, TextEditingController> _fields =
      <String, TextEditingController>{
        for (final String key in _textKeys) key: TextEditingController(),
      };

  String _store = 'Google Play';
  late String _currency;
  int? _templateVersionId;
  PlatformFile? _icon;
  List<PlatformFile> _screenshots = <PlatformFile>[];
  bool _featured = false;
  bool _saving = false;
  String? _error;
  _SaveStep _step = _SaveStep.idle;
  final Set<int> _removedScreenshotIds = <int>{};
  final Set<int> _deletedScreenshotIds = <int>{};
  final Set<int> _uploadedScreenshotIndexes = <int>{};
  int? _createdAppId;
  int? _createdCampaignId;

  Map<String, dynamic> get _existing => widget.existing ?? const {};

  Map<String, dynamic> get _existingCampaign {
    final dynamic campaigns = _existing['review_campaigns'];
    if (campaigns is! List || campaigns.isEmpty) return const {};
    return _map(campaigns.first);
  }

  @override
  void initState() {
    super.initState();
    final Map<String, dynamic> source = _existing;
    final Map<String, dynamic> campaign = _existingCampaign;
    final Map<String, dynamic> campaignVersion = _map(
      campaign['review_template_version'],
    );
    for (final String key in _textKeys) {
      if (key == 'paid_amount') {
        _fields[key]!.text = _sourceAmount(
          campaign['paid_amount_minor'],
          campaign['currency']?.toString() ?? source['currency']?.toString(),
          campaign['paid_amount_minor'] == null ? source['paid_amount'] : null,
        );
      } else if (key == 'review_reward') {
        _fields[key]!.text = _sourceAmount(
          campaign['review_reward_minor'],
          campaign['currency']?.toString() ?? source['currency']?.toString(),
          campaign['review_reward_minor'] == null
              ? source['review_reward']
              : null,
        );
      } else if (key == 'maximum_review') {
        _fields[key]!.text =
            (campaign['maximum_review'] ?? source['maximum_review'] ?? '')
                .toString();
      } else {
        final String sourceKey = key == 'store_url' ? 'store_link' : key;
        _fields[key]!.text = source[sourceKey]?.toString() ?? '';
      }
    }
    _store = source['store']?.toString() ?? _store;
    _featured =
        source['featured'] == true || source['featured']?.toString() == '1';
    _currency = widget.currencies.isEmpty
        ? (campaign['currency']?.toString().toUpperCase() ??
              source['currency']?.toString().toUpperCase() ??
              'USD')
        : campaign['currency']?.toString().toUpperCase() ??
              source['currency']?.toString().toUpperCase() ??
              widget.currencies.first['code']?.toString().toUpperCase() ??
              'USD';
    final int? currentVersionId = int.tryParse(
      campaign['review_template_version_id']?.toString() ??
          campaignVersion['id']?.toString() ??
          '',
    );
    if (currentVersionId != null &&
        widget.versions.any(
          (row) =>
              int.tryParse(row['id']?.toString() ?? '') == currentVersionId,
        )) {
      _templateVersionId = currentVersionId;
    } else if (widget.versions.length == 1) {
      _templateVersionId = int.tryParse(widget.versions.first['id'].toString());
    }
    // Live budget preview reacts to the three money fields.
    for (final String key in _moneyKeys) {
      _fields[key]!.addListener(_onMoneyChanged);
    }
  }

  String _sourceAmount(
    dynamic minorValue,
    String? currency,
    dynamic majorValue,
  ) {
    if (minorValue == null) return majorValue?.toString() ?? '';
    final String code = (currency ?? _existing['currency']?.toString() ?? 'USD')
        .toUpperCase();
    final Map<String, dynamic> row = widget.currencies.firstWhere(
      (Map<String, dynamic> item) =>
          item['code']?.toString().toUpperCase() == code,
      orElse: () => const <String, dynamic>{},
    );
    final int units = int.tryParse(row['minor_units']?.toString() ?? '') ?? 2;
    final int value = int.tryParse(minorValue.toString()) ?? 0;
    return (value / math.pow(10, units)).toStringAsFixed(units);
  }

  @override
  void dispose() {
    for (final String key in _moneyKeys) {
      _fields[key]!.removeListener(_onMoneyChanged);
    }
    for (final TextEditingController controller in _fields.values) {
      controller.dispose();
    }
    super.dispose();
  }

  void _onMoneyChanged() {
    if (mounted) setState(() {});
  }

  // -- money -------------------------------------------------------------

  int get _precision {
    final Map<String, dynamic> match = widget.currencies.firstWhere(
      (Map<String, dynamic> row) =>
          row['code']?.toString().toUpperCase() == _currency,
      orElse: () => const <String, dynamic>{},
    );
    return int.tryParse(match['minor_units']?.toString() ?? '') ?? 2;
  }

  int _minor(String value) =>
      ((double.tryParse(value.trim()) ?? 0) * math.pow(10, _precision)).round();

  String _money(int minor) {
    final int units = _precision;
    return '${formatGroupedNumber(minor / math.pow(10, units), units)} $_currency';
  }

  // -- pickers -----------------------------------------------------------

  Future<void> _pickIcon() async {
    final FilePickerResult? result = await FilePicker.platform.pickFiles(
      type: FileType.image,
      allowMultiple: false,
      withData: true,
    );
    if (!mounted || result == null || result.files.isEmpty) return;
    setState(() {
      _icon = result.files.first;
      _error = null;
    });
  }

  Future<void> _pickScreenshots() async {
    final FilePickerResult? result = await FilePicker.platform.pickFiles(
      type: FileType.image,
      allowMultiple: true,
      withData: true,
    );
    if (!mounted || result == null || result.files.isEmpty) return;
    setState(() {
      _screenshots = result.files
          .where((PlatformFile file) => file.bytes != null)
          .toList();
      _error = null;
    });
  }

  // -- save --------------------------------------------------------------

  Future<void> _save() async {
    if (_saving) return;
    final FormState? form = _formKey.currentState;
    if (form == null || !form.validate()) return;
    if (_templateVersionId == null) {
      setState(() => _error = 'Select a published question template.');
      return;
    }
    final int maximum =
        int.tryParse(_fields['maximum_review']!.text.trim()) ?? 0;
    final int pool = _minor(_fields['paid_amount']!.text);
    final int reward = _minor(_fields['review_reward']!.text);
    if (pool < reward * maximum) {
      setState(
        () => _error =
            'Reward pool must cover reward per review × maximum reviews.',
      );
      return;
    }

    final ScaffoldMessengerState messenger = ScaffoldMessenger.of(context);
    final Map<String, dynamic> source = _existing;
    final bool editing = source['id'] != null && !widget.clone;
    final bool updatingTarget = editing || _createdAppId != null;
    setState(() {
      _saving = true;
      _error = null;
      _step = _SaveStep.app;
    });

    int? appId = editing
        ? int.tryParse(source['id'].toString())
        : _createdAppId;
    final Map<String, dynamic> campaign = _existingCampaign;
    final int? campaignId =
        int.tryParse(campaign['id']?.toString() ?? '') ?? _createdCampaignId;
    String? failure;
    try {
      final Map<String, dynamic> payload = <String, dynamic>{
        for (final String key in _plainKeys) key: _fields[key]!.text.trim(),
        'store': _store,
        'install_count_label':
            _fields['install_count_label']!.text.trim().isEmpty
            ? null
            : _fields['install_count_label']!.text.trim(),
        'featured': _featured,
        'status': editing
            ? source['status']?.toString() ?? 'draft'
            : widget.clone
            ? 'draft'
            : 'active',
        if (_icon == null && _fields['app_icon']!.text.trim().isNotEmpty)
          'app_icon': _fields['app_icon']!.text.trim(),
        if (_icon != null) 'app_icon_file': 'upload',
        if (widget.clone && source['id'] != null)
          'clone_from_app_id': source['id'],
        if (widget.clone && source['id'] != null)
          'clone_screenshot_ids': _removedScreenshotIds.toList(),
      };

      final Map<String, dynamic> appPayload = <String, dynamic>{...payload};
      if (updatingTarget) {
        appPayload
          ..remove('store_url')
          ..remove('clone_from_app_id')
          ..remove('clone_screenshot_ids')
          ..remove('app_icon_file')
          ..['store_link'] = _fields['store_url']!.text.trim();
      }

      if (_icon?.bytes != null) {
        final Map<String, String> fields = _multipartFields(appPayload);
        if (updatingTarget) fields['_method'] = 'PATCH';
        final Map<String, dynamic> appResponse = await widget.api.postMultipart(
          path: updatingTarget ? 'admin/apps/$appId' : 'admin/apps',
          token: widget.token,
          fields: fields,
          fileField: 'app_icon_file',
          fileBytes: _icon!.bytes!,
          fileName: _icon!.name,
        );
        if (!updatingTarget) {
          final dynamic data = appResponse['data'];
          if (data is! Map || data['id'] == null) {
            throw const AuthException('App listing was created without an ID.');
          }
          appId = int.tryParse(data['id'].toString());
          _createdAppId = appId;
        }
      } else if (updatingTarget) {
        await widget.api.patch('admin/apps/$appId', widget.token, appPayload);
      } else {
        final Map<String, dynamic> appResponse = await widget.api.post(
          'admin/apps',
          widget.token,
          appPayload,
        );
        final dynamic data = appResponse['data'];
        if (data is! Map || data['id'] == null) {
          throw const AuthException('App listing was created without an ID.');
        }
        appId = int.tryParse(data['id'].toString());
        _createdAppId = appId;
      }
      if (appId == null) {
        throw const AuthException('Could not read the created app ID.');
      }

      if (mounted) setState(() => _step = _SaveStep.campaign);
      final Map<String, dynamic> campaignPayload = <String, dynamic>{
        'app_id': appId,
        'review_template_version_id': _templateVersionId,
        'paid_amount_minor': pool,
        'currency': _currency,
        'review_reward_minor': reward,
        'maximum_review': maximum,
        'status': campaign['status']?.toString() ?? 'active',
      };
      if (campaignId != null) {
        campaignPayload.remove('app_id');
        await widget.api.patch(
          'admin/review-campaigns/$campaignId',
          widget.token,
          campaignPayload,
        );
      } else {
        final Map<String, dynamic> response = await widget.api.post(
          'admin/review-campaigns',
          widget.token,
          campaignPayload,
        );
        final dynamic createdCampaign = response['data'];
        if (createdCampaign is Map && createdCampaign['id'] != null) {
          _createdCampaignId = int.tryParse(createdCampaign['id'].toString());
        }
      }

      if ((_screenshots.isNotEmpty || _removedScreenshotIds.isNotEmpty) &&
          mounted) {
        setState(() => _step = _SaveStep.media);
      }
      for (final int screenshotId in _removedScreenshotIds) {
        if (_deletedScreenshotIds.contains(screenshotId)) continue;
        await widget.api.delete(
          'admin/apps/$appId/screenshots/$screenshotId',
          widget.token,
        );
        _deletedScreenshotIds.add(screenshotId);
      }
      for (var index = 0; index < _screenshots.length; index++) {
        if (_uploadedScreenshotIndexes.contains(index)) continue;
        final PlatformFile image = _screenshots[index];
        if (image.bytes == null) continue;
        await widget.api.postMultipart(
          path: 'admin/apps/$appId/screenshots',
          token: widget.token,
          fields: <String, String>{
            'alt_text': '${_fields['name']!.text.trim()} screenshot',
          },
          fileField: 'image',
          fileBytes: image.bytes!,
          fileName: image.name,
        );
        _uploadedScreenshotIndexes.add(index);
      }
    } on AuthException catch (error) {
      failure = appId == null
          ? error.message
          : 'App #$appId was saved, but another setup step failed: '
                '${error.message}';
    } catch (error) {
      failure = 'Unexpected error while saving: $error';
    }

    if (failure == null) {
      _showToast(
        messenger,
        editing
            ? 'Listing and campaign updated.'
            : widget.clone
            ? 'Listing cloned as a new draft.'
            : 'Listing and campaign created.',
        success: true,
      );
      if (mounted) Navigator.of(context).pop(true);
      return;
    }

    _showToast(messenger, failure, error: true);
    if (!mounted) return;
    setState(() {
      _saving = false;
      _step = _SaveStep.idle;
      _error = failure;
    });
  }

  Map<String, String> _multipartFields(Map<String, dynamic> values) {
    final Map<String, String> result = <String, String>{};
    for (final MapEntry<String, dynamic> entry in values.entries) {
      final dynamic value = entry.value;
      if (value is List) {
        for (var index = 0; index < value.length; index++) {
          result['${entry.key}[$index]'] = value[index].toString();
        }
      } else {
        result[entry.key] = value is bool
            ? (value ? '1' : '0')
            : value?.toString() ?? '';
      }
    }
    return result;
  }

  String get _stepLabel => switch (_step) {
    _SaveStep.app =>
      widget.existing != null && !widget.clone
          ? 'Updating the app listing…'
          : 'Creating the app listing…',
    _SaveStep.campaign =>
      widget.existing != null && !widget.clone
          ? 'Updating the review campaign…'
          : 'Setting up the review campaign…',
    _SaveStep.media => 'Uploading screenshots…',
    _SaveStep.idle => '',
  };

  // -- build -------------------------------------------------------------

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    final double keyboardInset = MediaQuery.viewInsetsOf(context).bottom;

    return PopScope(
      canPop: !_saving,
      onPopInvokedWithResult: (bool didPop, Object? result) {
        if (didPop) return;
        final ScaffoldMessengerState? messenger = ScaffoldMessenger.maybeOf(
          context,
        );
        if (messenger != null) {
          _showToast(
            messenger,
            'Finishing the save — please wait before closing.',
          );
        }
      },
      child: Padding(
        padding: EdgeInsets.only(bottom: keyboardInset),
        child: Container(
          clipBehavior: Clip.antiAlias,
          decoration: BoxDecoration(
            color: theme.colorScheme.surface,
            borderRadius: const BorderRadius.vertical(top: Radius.circular(28)),
          ),
          child: SafeArea(
            top: false,
            child: Column(
              children: <Widget>[
                _buildHeader(theme),
                if (_saving && _step != _SaveStep.idle) _buildStepBar(theme),
                Expanded(
                  child: Form(
                    key: _formKey,
                    child: ListView(
                      padding: const EdgeInsets.fromLTRB(18, 16, 18, 24),
                      children: <Widget>[
                        _sectionHeader(
                          theme,
                          'App identity',
                          'How the listing is named and filed',
                          _Palette.ocean,
                        ),
                        _field(
                          'name',
                          label: 'App name',
                          icon: Icons.badge_rounded,
                        ),
                        _field(
                          'short_code',
                          label: 'Short code',
                          hint: 'Max 12 characters',
                          icon: Icons.tag_rounded,
                          maxLength: 12,
                        ),
                        _SelectField<String>(
                          label: 'Store',
                          icon: Icons.storefront_rounded,
                          value: _store,
                          items: const <DropdownMenuItem<String>>[
                            DropdownMenuItem<String>(
                              value: 'Apple App Store',
                              child: Text('Apple App Store'),
                            ),
                            DropdownMenuItem<String>(
                              value: 'Samsung Galaxy Store',
                              child: Text('Samsung Galaxy Store'),
                            ),
                            DropdownMenuItem<String>(
                              value: 'Google Play',
                              child: Text('Google Play'),
                            ),
                          ],
                          onChanged: (String? value) =>
                              setState(() => _store = value ?? _store),
                        ),
                        _field('developer', icon: Icons.person_rounded),
                        _field('category', icon: Icons.category_rounded),
                        _field(
                          'install_count_label',
                          label: 'Install count label (optional)',
                          hint: 'e.g. 100K+',
                          icon: Icons.download_rounded,
                          validator: (String? value) => null,
                        ),
                        const SizedBox(height: 6),
                        _sectionHeader(
                          theme,
                          'Store presence',
                          'Copy, links and artwork',
                          _Palette.berry,
                        ),
                        _field(
                          'description',
                          icon: Icons.notes_rounded,
                          lines: 4,
                        ),
                        _field(
                          'store_url',
                          label: 'Store listing URL',
                          icon: Icons.link_rounded,
                          keyboard: TextInputType.url,
                        ),
                        _field(
                          'brand_color',
                          label: 'Brand colour',
                          hint: '#147D45',
                          icon: Icons.palette_rounded,
                          validator: (String? value) {
                            final String text = (value ?? '').trim();
                            if (text.isEmpty) return 'Required';
                            return _parseHex(text) == null
                                ? 'Use a hex value such as #147D45'
                                : null;
                          },
                          suffix: _brandSwatch(),
                        ),
                        _field(
                          'app_icon',
                          label: 'App icon URL (optional if uploading)',
                          icon: Icons.image_rounded,
                          validator: (String? value) =>
                              _icon == null &&
                                  (value ?? '').trim().isEmpty &&
                                  !(widget.existing != null && !widget.clone)
                              ? 'Add an icon URL or upload an icon'
                              : null,
                        ),
                        _IconPickerRow(
                          file: _icon,
                          onPick: _saving ? null : _pickIcon,
                          onClear: _saving || _icon == null
                              ? null
                              : () => setState(() => _icon = null),
                        ),
                        const SizedBox(height: 6),
                        _sectionHeader(
                          theme,
                          'Questionnaire',
                          'What reviewers are asked',
                          _Palette.mint,
                        ),
                        _SelectField<int>(
                          label: 'Published question template',
                          icon: Icons.fact_check_rounded,
                          value: _templateVersionId,
                          items: <DropdownMenuItem<int>>[
                            for (final Map<String, dynamic> version
                                in widget.versions)
                              DropdownMenuItem<int>(
                                value: int.tryParse(version['id'].toString()),
                                child: Text(
                                  '${version['template_name']} · v'
                                  '${version['version']}',
                                  maxLines: 1,
                                  overflow: TextOverflow.ellipsis,
                                ),
                              ),
                          ],
                          onChanged: (int? value) =>
                              setState(() => _templateVersionId = value),
                        ),
                        _SelectField<String>(
                          label: 'Campaign currency',
                          icon: Icons.currency_exchange_rounded,
                          value: _currency,
                          items: <DropdownMenuItem<String>>[
                            for (final Map<String, dynamic> item
                                in widget.currencies)
                              DropdownMenuItem<String>(
                                value: item['code']?.toString().toUpperCase(),
                                child: Text(
                                  item['code']?.toString().toUpperCase() ?? '',
                                ),
                              ),
                          ],
                          onChanged: (String? value) =>
                              setState(() => _currency = value ?? _currency),
                        ),
                        const SizedBox(height: 6),
                        _sectionHeader(
                          theme,
                          'Reward economics',
                          'Pool, payout per review and capacity',
                          _Palette.sunset,
                        ),
                        _field(
                          'paid_amount',
                          label: 'Total reward pool',
                          icon: Icons.account_balance_wallet_rounded,
                          keyboard: const TextInputType.numberWithOptions(
                            decimal: true,
                          ),
                        ),
                        _field(
                          'review_reward',
                          label: 'Reward per approved review',
                          icon: Icons.redeem_rounded,
                          keyboard: const TextInputType.numberWithOptions(
                            decimal: true,
                          ),
                        ),
                        _field(
                          'maximum_review',
                          label: 'Maximum reviews',
                          icon: Icons.numbers_rounded,
                          keyboard: TextInputType.number,
                        ),
                        _buildBudget(theme),
                        const SizedBox(height: 6),
                        _sectionHeader(
                          theme,
                          'Gallery & promotion',
                          'Screenshots and home placement',
                          _Palette.berry,
                        ),
                        _buildScreenshots(theme),
                        _buildFeatured(theme),
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

  // -- sheet chrome ------------------------------------------------------

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
              child: const Icon(
                Icons.add_business_rounded,
                color: Colors.white,
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  Text(
                    widget.existing != null && !widget.clone
                        ? 'Edit app listing'
                        : widget.clone
                        ? 'Clone app listing'
                        : 'Create app listing',
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
                    widget.existing != null && !widget.clone
                        ? 'Update the app, questionnaire and campaign'
                        : widget.clone
                        ? 'A new draft copied from an existing listing'
                        : 'App · questionnaire · campaign in one pass',
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
              onPressed: _saving
                  ? null
                  : () => Navigator.of(context).pop(false),
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

  Widget _buildStepBar(ThemeData theme) => Container(
    width: double.infinity,
    color: _Palette.indigo.withValues(alpha: .1),
    padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 11),
    child: Row(
      children: <Widget>[
        const SizedBox(
          width: 15,
          height: 15,
          child: CircularProgressIndicator(strokeWidth: 2),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: Text(
            _stepLabel,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(
              color: _Palette.indigo,
              fontWeight: FontWeight.w700,
              fontSize: 13,
            ),
          ),
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
          label: _saving
              ? 'Saving listing…'
              : widget.existing != null && !widget.clone
              ? 'Save listing changes'
              : widget.clone
              ? 'Create cloned listing'
              : 'Create listing and campaign',
          icon: _saving ? null : Icons.publish_rounded,
          busy: _saving,
          onPressed: _saving ? null : _save,
        ),
        const SizedBox(height: 8),
        Text(
          'The app, its active review campaign, screenshots and the selected '
          'questionnaire are created in sequence.',
          textAlign: TextAlign.center,
          style: theme.textTheme.labelSmall?.copyWith(
            color: theme.colorScheme.onSurfaceVariant,
            height: 1.35,
          ),
        ),
      ],
    ),
  );

  // -- section helpers ---------------------------------------------------

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

  Widget _field(
    String key, {
    String? label,
    String? hint,
    int lines = 1,
    TextInputType? keyboard,
    IconData? icon,
    int? maxLength,
    Widget? suffix,
    String? Function(String?)? validator,
  }) {
    final ThemeData theme = Theme.of(context);
    final ColorScheme scheme = theme.colorScheme;
    return Padding(
      padding: const EdgeInsets.only(bottom: 14),
      child: TextFormField(
        controller: _fields[key],
        maxLines: lines,
        minLines: lines > 1 ? 3 : null,
        maxLength: maxLength,
        keyboardType: keyboard,
        validator: validator ?? _required,
        decoration: InputDecoration(
          labelText: label ?? key.replaceAll('_', ' '),
          hintText: hint,
          prefixIcon: icon == null ? null : Icon(icon, size: 20),
          suffixIcon: suffix,
          alignLabelWithHint: lines > 1,
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

  Widget? _brandSwatch() {
    final Color? color = _parseHex(_fields['brand_color']?.text);
    return Padding(
      padding: const EdgeInsets.only(right: 12),
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 180),
        width: 26,
        height: 26,
        decoration: BoxDecoration(
          color: color ?? Colors.grey.withValues(alpha: .25),
          borderRadius: BorderRadius.circular(8),
          border: Border.all(color: Colors.black.withValues(alpha: .12)),
        ),
      ),
    );
  }

  Widget _buildBudget(ThemeData theme) {
    final int pool = _minor(_fields['paid_amount']!.text);
    final int reward = _minor(_fields['review_reward']!.text);
    final int maximum =
        int.tryParse(_fields['maximum_review']!.text.trim()) ?? 0;
    final int committed = reward * maximum;
    final int remaining = pool - committed;
    final bool balanced = pool > 0 && remaining >= 0;
    final Color color = balanced ? _Palette.green : _Palette.rose;
    final double ratio = pool <= 0
        ? 0
        : (committed / pool).clamp(0.0, 1.0).toDouble();

    return Container(
      margin: const EdgeInsets.only(bottom: 14),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(18),
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: <Color>[
            color.withValues(alpha: .12),
            _Palette.violet.withValues(alpha: .1),
          ],
        ),
        border: Border.all(color: color.withValues(alpha: .35)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Row(
            children: <Widget>[
              Icon(
                balanced ? Icons.balance_rounded : Icons.warning_amber_rounded,
                size: 18,
                color: color,
              ),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  balanced ? 'Budget is covered' : 'Budget needs attention',
                  style: TextStyle(
                    color: color,
                    fontWeight: FontWeight.w800,
                    fontSize: 13,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          _TileRow(
            spacing: 10,
            minItemWidth: 96,
            children: <Widget>[
              _MetricBox(label: 'Pool', value: _money(pool)),
              _MetricBox(label: 'Committed', value: _money(committed)),
              _MetricBox(label: 'Remaining', value: _money(remaining)),
            ],
          ),
          const SizedBox(height: 10),
          ClipRRect(
            borderRadius: BorderRadius.circular(999),
            child: LinearProgressIndicator(
              value: ratio,
              minHeight: 7,
              backgroundColor: color.withValues(alpha: .16),
              valueColor: AlwaysStoppedAnimation<Color>(color),
            ),
          ),
          const SizedBox(height: 8),
          Text(
            '${_money(reward)} × $maximum review'
            '${maximum == 1 ? '' : 's'} = ${_money(committed)} of '
            '${_money(pool)} committed.',
            style: theme.textTheme.labelSmall?.copyWith(
              color: theme.colorScheme.onSurfaceVariant,
              height: 1.4,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildScreenshots(ThemeData theme) {
    final dynamic rawExisting = _existing['screenshots'];
    final List<Map<String, dynamic>> existingScreenshots = rawExisting is List
        ? rawExisting.map(_map).toList()
        : <Map<String, dynamic>>[];
    return Container(
      margin: const EdgeInsets.only(bottom: 14),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: _Palette.sky.withValues(alpha: .07),
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: _Palette.sky.withValues(alpha: .25)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          Row(
            children: <Widget>[
              const Icon(
                Icons.collections_rounded,
                size: 18,
                color: _Palette.sky,
              ),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  _screenshots.isEmpty
                      ? 'No screenshots selected'
                      : '${_screenshots.length} screenshot'
                            '${_screenshots.length == 1 ? '' : 's'} ready',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: _Palette.sky,
                    fontWeight: FontWeight.w800,
                    fontSize: 13,
                  ),
                ),
              ),
              if (_screenshots.isNotEmpty)
                TextButton(
                  onPressed: _saving
                      ? null
                      : () => setState(() => _screenshots = <PlatformFile>[]),
                  child: const Text('Clear'),
                ),
            ],
          ),
          if (existingScreenshots.isNotEmpty) ...<Widget>[
            const SizedBox(height: 10),
            Text(
              widget.clone ? 'Screenshots to copy' : 'Current screenshots',
              style: theme.textTheme.labelLarge?.copyWith(
                color: theme.colorScheme.onSurfaceVariant,
                fontWeight: FontWeight.w800,
              ),
            ),
            const SizedBox(height: 8),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: <Widget>[
                for (final Map<String, dynamic> screenshot
                    in existingScreenshots)
                  _ExistingScreenshotTile(
                    screenshot: screenshot,
                    removed: _removedScreenshotIds.contains(
                      int.tryParse(screenshot['id']?.toString() ?? ''),
                    ),
                    onToggle: _saving || screenshot['id'] == null
                        ? null
                        : () => setState(() {
                            final int? id = int.tryParse(
                              screenshot['id'].toString(),
                            );
                            if (id == null) return;
                            if (!_removedScreenshotIds.add(id)) {
                              _removedScreenshotIds.remove(id);
                            }
                          }),
                  ),
              ],
            ),
          ],
          if (_screenshots.isNotEmpty) ...<Widget>[
            const SizedBox(height: 6),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: <Widget>[
                for (final PlatformFile file in _screenshots)
                  Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 10,
                      vertical: 6,
                    ),
                    decoration: BoxDecoration(
                      color: theme.colorScheme.surface,
                      borderRadius: BorderRadius.circular(999),
                      border: Border.all(
                        color: theme.colorScheme.outlineVariant,
                      ),
                    ),
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: <Widget>[
                        const Icon(
                          Icons.image_rounded,
                          size: 14,
                          color: _Palette.sky,
                        ),
                        const SizedBox(width: 6),
                        ConstrainedBox(
                          constraints: const BoxConstraints(maxWidth: 150),
                          child: Text(
                            file.name,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: theme.textTheme.labelSmall,
                          ),
                        ),
                      ],
                    ),
                  ),
              ],
            ),
            const SizedBox(height: 4),
          ],
          const SizedBox(height: 6),
          OutlinedButton.icon(
            style: OutlinedButton.styleFrom(
              foregroundColor: _Palette.sky,
              side: BorderSide(color: _Palette.sky.withValues(alpha: .5)),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(14),
              ),
              padding: const EdgeInsets.symmetric(vertical: 14),
            ),
            onPressed: _saving ? null : _pickScreenshots,
            icon: const Icon(Icons.add_photo_alternate_outlined, size: 20),
            label: Text(
              _screenshots.isEmpty
                  ? 'Choose app screenshots'
                  : 'Replace selection',
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildFeatured(ThemeData theme) => Container(
    margin: const EdgeInsets.only(bottom: 8),
    padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
    decoration: BoxDecoration(
      gradient: LinearGradient(
        begin: Alignment.centerLeft,
        end: Alignment.centerRight,
        colors: <Color>[
          _Palette.amber.withValues(alpha: .14),
          _Palette.amber.withValues(alpha: .04),
        ],
      ),
      borderRadius: BorderRadius.circular(18),
      border: Border.all(color: _Palette.amber.withValues(alpha: .3)),
    ),
    child: SwitchListTile(
      value: _featured,
      onChanged: _saving
          ? null
          : (bool value) => setState(() => _featured = value),
      secondary: const Icon(Icons.star_rounded, color: _Palette.amber),
      title: const Text(
        'Feature on home',
        style: TextStyle(fontWeight: FontWeight.w700),
      ),
      subtitle: Text(
        'Pins this listing in the featured carousel.',
        style: theme.textTheme.labelSmall?.copyWith(
          color: theme.colorScheme.onSurfaceVariant,
        ),
      ),
    ),
  );
}

// ---------------------------------------------------------------------------
// Form support widgets
// ---------------------------------------------------------------------------

class _IconPickerRow extends StatelessWidget {
  const _IconPickerRow({
    required this.file,
    required this.onPick,
    required this.onClear,
  });

  final PlatformFile? file;
  final VoidCallback? onPick;
  final VoidCallback? onClear;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    return Padding(
      padding: const EdgeInsets.only(bottom: 14),
      child: Row(
        children: <Widget>[
          ClipRRect(
            borderRadius: BorderRadius.circular(14),
            child: SizedBox(
              width: 54,
              height: 54,
              child: file?.bytes == null
                  ? Container(
                      color: theme.colorScheme.onSurface.withValues(alpha: .06),
                      child: Icon(
                        Icons.image_outlined,
                        color: theme.colorScheme.onSurfaceVariant,
                      ),
                    )
                  : Image.memory(
                      file!.bytes!,
                      fit: BoxFit.cover,
                      errorBuilder:
                          (
                            BuildContext context,
                            Object error,
                            StackTrace? trace,
                          ) => Icon(
                            Icons.broken_image_outlined,
                            color: theme.colorScheme.onSurfaceVariant,
                          ),
                    ),
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Text(
                  file == null ? 'No icon uploaded' : file!.name,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: theme.textTheme.bodyMedium?.copyWith(
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: 6),
                Row(
                  children: <Widget>[
                    Expanded(
                      child: OutlinedButton.icon(
                        style: OutlinedButton.styleFrom(
                          foregroundColor: _Palette.violet,
                          side: BorderSide(
                            color: _Palette.violet.withValues(alpha: .5),
                          ),
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(12),
                          ),
                        ),
                        onPressed: onPick,
                        icon: const Icon(Icons.upload_rounded, size: 18),
                        label: Text(
                          file == null ? 'Upload icon' : 'Replace',
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                    ),
                    if (onClear != null) ...<Widget>[
                      const SizedBox(width: 8),
                      IconButton(
                        tooltip: 'Remove uploaded icon',
                        onPressed: onClear,
                        icon: const Icon(Icons.delete_outline_rounded),
                        color: _Palette.rose,
                      ),
                    ],
                  ],
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _ExistingScreenshotTile extends StatelessWidget {
  const _ExistingScreenshotTile({
    required this.screenshot,
    required this.removed,
    required this.onToggle,
  });

  final Map<String, dynamic> screenshot;
  final bool removed;
  final VoidCallback? onToggle;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    final String? url = screenshot['image_url']?.toString();
    return Opacity(
      opacity: removed ? .42 : 1,
      child: SizedBox(
        width: 78,
        child: Column(
          children: <Widget>[
            Stack(
              children: <Widget>[
                ClipRRect(
                  borderRadius: BorderRadius.circular(12),
                  child: Container(
                    width: 74,
                    height: 84,
                    color: theme.colorScheme.surfaceContainerHighest,
                    child: url == null || url.isEmpty
                        ? const Icon(Icons.image_not_supported_outlined)
                        : Image.network(
                            url,
                            fit: BoxFit.cover,
                            errorBuilder: (_, _, _) =>
                                const Icon(Icons.broken_image_outlined),
                          ),
                  ),
                ),
                Positioned(
                  top: 2,
                  right: 2,
                  child: Material(
                    color: removed
                        ? _Palette.green.withValues(alpha: .9)
                        : _Palette.rose.withValues(alpha: .9),
                    shape: const CircleBorder(),
                    child: InkWell(
                      customBorder: const CircleBorder(),
                      onTap: onToggle,
                      child: Padding(
                        padding: const EdgeInsets.all(4),
                        child: Icon(
                          removed ? Icons.undo_rounded : Icons.close_rounded,
                          size: 14,
                          color: Colors.white,
                        ),
                      ),
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 4),
            Text(
              removed ? 'Will remove' : 'Screenshot',
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: theme.textTheme.labelSmall?.copyWith(
                color: removed
                    ? theme.colorScheme.error
                    : theme.colorScheme.onSurfaceVariant,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// `DropdownButton` inside an `InputDecorator`: keeps the look of a text field
/// without depending on `DropdownButtonFormField`, whose `value` argument has
/// been renamed across Flutter releases.
class _SelectField<T> extends StatelessWidget {
  const _SelectField({
    required this.label,
    required this.value,
    required this.items,
    required this.onChanged,
    this.icon,
  });

  final String label;
  final T? value;
  final List<DropdownMenuItem<T>> items;
  final ValueChanged<T?> onChanged;
  final IconData? icon;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    final ColorScheme scheme = theme.colorScheme;
    return Padding(
      padding: const EdgeInsets.only(bottom: 14),
      child: InputDecorator(
        decoration: InputDecoration(
          labelText: label,
          prefixIcon: icon == null ? null : Icon(icon, size: 20),
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
        ),
        child: DropdownButton<T>(
          value: value,
          isExpanded: true,
          underline: const SizedBox.shrink(),
          borderRadius: BorderRadius.circular(16),
          icon: const Icon(Icons.expand_more_rounded),
          style: TextStyle(color: scheme.onSurface, fontSize: 15),
          items: items,
          onChanged: onChanged,
        ),
      ),
    );
  }
}
