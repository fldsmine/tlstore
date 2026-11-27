import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';

import '../../core/currency/money.dart';
import '../../services/auth_api.dart';

// ---------------------------------------------------------------------------
// Design language (shared with the other admin consoles)
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

class _PlatformMeta {
  const _PlatformMeta(this.label, this.icon, this.color);

  final String label;
  final IconData icon;
  final Color color;
}

const Map<String, _PlatformMeta> _platforms = <String, _PlatformMeta>{
  'telegram': _PlatformMeta('Telegram', Icons.send_rounded, Color(0xFF229ED9)),
  'facebook': _PlatformMeta('Facebook', Icons.groups_rounded, Color(0xFF1877F2)),
  'youtube': _PlatformMeta(
    'YouTube',
    Icons.play_circle_filled_rounded,
    Color(0xFFE53935),
  ),
  'instagram': _PlatformMeta(
    'Instagram',
    Icons.camera_alt_rounded,
    Color(0xFFE1306C),
  ),
  'tiktok': _PlatformMeta('TikTok', Icons.music_note_rounded, Color(0xFF00B3B3)),
  'x': _PlatformMeta('X (Twitter)', Icons.alternate_email_rounded, Color(0xFF52525E)),
  'whatsapp': _PlatformMeta('WhatsApp', Icons.chat_rounded, Color(0xFF25D366)),
  'others': _PlatformMeta('Others', Icons.public_rounded, _Palette.indigo),
};

_PlatformMeta _platformFor(String? type) =>
    _platforms[type] ??
    const _PlatformMeta('Others', Icons.public_rounded, _Palette.indigo);

Color _statusColor(String status) => switch (status) {
  'active' => _Palette.green,
  'pending' => _Palette.sky,
  'rejected' => _Palette.rose,
  'paused' => _Palette.orange,
  'archived' => _Palette.violet,
  _ => _Palette.amber,
};

IconData _statusIcon(String status) => switch (status) {
  'active' => Icons.play_circle_filled_rounded,
  'pending' => Icons.hourglass_top_rounded,
  'rejected' => Icons.block_rounded,
  'paused' => Icons.pause_circle_filled_rounded,
  'archived' => Icons.inventory_2_rounded,
  _ => Icons.edit_note_rounded,
};

String _pretty(String value) => value
    .split('_')
    .map(
      (String word) =>
          word.isEmpty ? '' : '${word[0].toUpperCase()}${word.substring(1)}',
    )
    .join(' ');

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

class AdminAdsScreen extends StatefulWidget {
  const AdminAdsScreen({super.key, required this.api});

  final AuthApi api;

  @override
  State<AdminAdsScreen> createState() => _AdminAdsScreenState();
}

class _AdminAdsScreenState extends State<AdminAdsScreen> {
  final TextEditingController _email = TextEditingController();
  final TextEditingController _password = TextEditingController();
  final TextEditingController _search = TextEditingController();

  String? _token;
  String? _error;
  String _filter = 'all';
  String _query = '';
  bool _loading = false;
  bool _obscure = true;
  List<Map<String, dynamic>> _ads = <Map<String, dynamic>>[];
  List<Map<String, dynamic>> _currencies = <Map<String, dynamic>>[];
  List<Map<String, dynamic>> _countries = <Map<String, dynamic>>[];
  List<Map<String, dynamic>> _regions = <Map<String, dynamic>>[];

  @override
  void dispose() {
    _email.dispose();
    _password.dispose();
    _search.dispose();
    super.dispose();
  }

  // -- data --------------------------------------------------------------

  Map<String, dynamic> _map(dynamic value) =>
      value is Map ? Map<String, dynamic>.from(value) : <String, dynamic>{};

  List<Map<String, dynamic>> _rows(dynamic value) {
    final Map<String, dynamic> page = _map(value);
    final dynamic rows = page['data'] ?? value;
    return rows is List ? rows.map(_map).toList() : <Map<String, dynamic>>[];
  }

  bool _isAvailable(Map<String, dynamic> row) =>
      row['is_available'] != false && row['is_available']?.toString() != '0';

  List<Map<String, dynamic>> get _visibleAds {
    final String query = _query.trim().toLowerCase();
    return _ads.where((Map<String, dynamic> ad) {
      final String status = ad['status']?.toString() ?? 'draft';
      if (_filter != 'all' && status != _filter) return false;
      return query.isEmpty ||
          '${ad['title'] ?? ''} ${ad['type'] ?? ''} '
                  '${ad['destination_url'] ?? ''}'
              .toLowerCase()
              .contains(query);
    }).toList();
  }

  int _countWhere(String status) =>
      _ads.where((Map<String, dynamic> ad) => ad['status'] == status).length;

  String _locationName(String? code) {
    if (code == null || code.isEmpty) return 'Global';
    for (final Map<String, dynamic> row in _countries) {
      if (row['code']?.toString().toUpperCase() == code.toUpperCase()) {
        return '${row['name']} · $code';
      }
    }
    for (final Map<String, dynamic> row in _regions) {
      if (row['code']?.toString() == code) return '${row['name']} · Region';
    }
    return code;
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
      _showToast(messenger, 'Signed in. Ad listings are up to date.');
    }
  }

  Future<void> _load({bool announce = false}) async {
    // Reached after awaits from _openEditor/_approve/_delete, so confirm the
    // state is still alive before touching context.
    if (!mounted) return;
    final String? token = _token;
    if (token == null) return;
    final ScaffoldMessengerState messenger = ScaffoldMessenger.of(context);
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final List<dynamic> results = await Future.wait(<Future<dynamic>>[
        widget.api.get('admin/ads?per_page=100', token),
        widget.api.get('admin/countries?per_page=100', token),
        widget.api.get('admin/regions?per_page=100', token),
        widget.api.getPublic('currencies'),
      ]);
      if (!mounted) return;
      final dynamic currencyRows = results[3]['data'];
      setState(() {
        _ads = _rows(results[0]['data']);
        _countries = _rows(results[1]['data']).where(_isAvailable).toList();
        _regions = _rows(results[2]['data']).where(_isAvailable).toList();
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
      _showToast(messenger, 'Refreshed ${_ads.length} ad listing(s).');
    }
  }

  void _signOut() {
    setState(() {
      _token = null;
      _ads = <Map<String, dynamic>>[];
      _currencies = <Map<String, dynamic>>[];
      _countries = <Map<String, dynamic>>[];
      _regions = <Map<String, dynamic>>[];
      _error = null;
      _query = '';
      _filter = 'all';
      _search.clear();
    });
  }

  // -- actions -----------------------------------------------------------

  Future<void> _openEditor({Map<String, dynamic>? ad}) async {
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
        child: _AdEditor(
          api: widget.api,
          token: token,
          existing: ad,
          currencies: _currencies,
          countries: _countries,
          regions: _regions,
        ),
      ),
    );
    if (saved == true) await _load();
  }

  Future<void> _approve(Map<String, dynamic> ad) async {
    final String? token = _token;
    if (token == null) return;
    final ScaffoldMessengerState messenger = ScaffoldMessenger.of(context);
    final bool? yes = await showDialog<bool>(
      context: context,
      builder: (BuildContext dialogContext) => Theme(
        data: _adminTheme(dialogContext),
        child: _ConfirmDialog(
          title: 'Approve ad listing?',
          icon: Icons.verified_rounded,
          gradient: _Palette.mint,
          message:
              '“${ad['title'] ?? 'This ad'}” will become active and available '
              'to users.',
          confirmLabel: 'Approve',
          cancelLabel: 'Cancel',
        ),
      ),
    );
    if (yes != true) return;
    try {
      await widget.api.post(
        'admin/ads/${ad['id']}/approve',
        token,
        const <String, dynamic>{},
      );
      await _load();
      _showToast(messenger, 'Ad approved and activated.', success: true);
    } on AuthException catch (error) {
      _showToast(messenger, error.message, error: true);
    }
  }

  Future<void> _delete(Map<String, dynamic> ad) async {
    final String? token = _token;
    if (token == null) return;
    final ScaffoldMessengerState messenger = ScaffoldMessenger.of(context);
    final bool? yes = await showDialog<bool>(
      context: context,
      builder: (BuildContext dialogContext) => Theme(
        data: _adminTheme(dialogContext),
        child: _ConfirmDialog(
          title: 'Delete this ad?',
          icon: Icons.delete_forever_rounded,
          gradient: LinearGradient(
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
            colors: <Color>[_Palette.rose, Color.lerp(_Palette.rose, Colors.black, .25)!],
          ),
          message:
              'The ad will be removed from user listings. Existing share '
              'records are retained for audit history.',
          confirmLabel: 'Delete',
          cancelLabel: 'Keep it',
        ),
      ),
    );
    if (yes != true) return;
    try {
      await widget.api.delete('admin/ads/${ad['id']}', token);
      await _load();
      _showToast(messenger, 'Ad listing deleted.', success: true);
    } on AuthException catch (error) {
      _showToast(messenger, error.message, error: true);
    }
  }

  // -- UI ----------------------------------------------------------------

  @override
  Widget build(BuildContext context) {
    return Theme(
      data: _adminTheme(context),
      child: Scaffold(
        backgroundColor: const Color(0xFFF5F6FB),
        appBar: AppBar(
          backgroundColor: _Palette.indigo,
          foregroundColor: Colors.white,
          elevation: 0,
          title: const Text('Ad Moderation', style: TextStyle(fontWeight: FontWeight.w700)),
          actions: <Widget>[
            if (_token != null)
              IconButton(
                tooltip: 'Refresh',
                onPressed: _loading ? null : () => _load(announce: true),
                icon: _loading
                    ? const SizedBox(
                        width: 18,
                        height: 18,
                        child: CircularProgressIndicator(
                          strokeWidth: 2,
                          color: Colors.white,
                        ),
                      )
                    : const Icon(Icons.refresh_rounded),
              ),
            if (_token != null)
              IconButton(
                tooltip: 'Sign out',
                onPressed: _signOut,
                icon: const Icon(Icons.logout_rounded),
              ),
            const SizedBox(width: 4),
          ],
        ),
        body: _token == null
            ? _buildSignIn()
            : RefreshIndicator(
                color: _Palette.indigo,
                onRefresh: _load,
                child: CustomScrollView(
                  slivers: <Widget>[
                    SliverToBoxAdapter(child: _buildHero()),
                    SliverToBoxAdapter(child: _buildStats()),
                    SliverToBoxAdapter(child: _buildFilters()),
                    if (_error != null)
                      SliverToBoxAdapter(
                        child: _ErrorBanner(message: _error!, onRetry: () => _load()),
                      ),
                    if (_loading && _ads.isEmpty)
                      const SliverFillRemaining(
                        hasScrollBody: false,
                        child: _LoadingState(),
                      )
                    else if (_visibleAds.isEmpty)
                      SliverFillRemaining(
                        hasScrollBody: false,
                        child: _EmptyState(
                          icon: _error == null
                              ? Icons.campaign_outlined
                              : Icons.error_outline_rounded,
                          gradient: _error == null
                              ? _Palette.ocean
                              : const LinearGradient(
                                  colors: <Color>[_Palette.rose, _Palette.orange],
                                ),
                          title: _error != null
                              ? 'Could not load ads'
                              : _ads.isEmpty
                              ? 'No ad listings yet'
                              : 'No ads match this filter',
                          message: _error != null
                              ? 'The request failed — check the message and retry.'
                              : _ads.isEmpty
                              ? 'Create the first campaign to start earning share rewards.'
                              : 'Try another status filter or clear the search.',
                          actionLabel: _error != null
                              ? 'Retry'
                              : _ads.isEmpty
                              ? 'Create ad'
                              : null,
                          onAction: _error != null
                              ? () => _load()
                              : _ads.isEmpty
                              ? () => _openEditor()
                              : null,
                        ),
                      )
                    else
                      SliverPadding(
                        padding: const EdgeInsets.fromLTRB(14, 4, 14, 24),
                        sliver: SliverList.builder(
                          itemCount: _visibleAds.length,
                          itemBuilder: (BuildContext context, int index) =>
                              _buildAdCard(_visibleAds[index], index),
                        ),
                      ),
                  ],
                ),
              ),
        floatingActionButton: _token == null
            ? null
            : FloatingActionButton.extended(
                onPressed: _openEditor,
                backgroundColor: _Palette.indigo,
                foregroundColor: Colors.white,
                icon: const Icon(Icons.add_rounded),
                label: const Text(
                  'Create ad',
                  style: TextStyle(fontWeight: FontWeight.w700),
                ),
              ),
      ),
    );
  }

  Widget _buildSignIn() {
    return Container(
      decoration: const BoxDecoration(gradient: _Palette.hero),
      child: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 24),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 400),
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: <Widget>[
                  Container(
                    width: 72,
                    height: 72,
                    decoration: BoxDecoration(
                      color: Colors.white.withValues(alpha: .18),
                      borderRadius: BorderRadius.circular(22),
                    ),
                    child: const Icon(
                      Icons.campaign_rounded,
                      color: Colors.white,
                      size: 36,
                    ),
                  ),
                  const SizedBox(height: 18),
                  const Text(
                    'Ad Moderation Console',
                    textAlign: TextAlign.center,
                    style: TextStyle(
                      color: Colors.white,
                      fontSize: 24,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  const SizedBox(height: 6),
                  Text(
                    'Review user-submitted campaigns, approve winners and '
                    'manage share rewards.',
                    textAlign: TextAlign.center,
                    style: TextStyle(color: Colors.white.withValues(alpha: .85)),
                  ),
                  const SizedBox(height: 26),
                  Container(
                    padding: const EdgeInsets.all(20),
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(26),
                      boxShadow: <BoxShadow>[
                        BoxShadow(
                          color: Colors.black.withValues(alpha: .22),
                          blurRadius: 30,
                          offset: const Offset(0, 16),
                        ),
                      ],
                    ),
                    child: Column(
                      children: <Widget>[
                        _Field(
                          label: 'Admin email',
                          icon: Icons.alternate_email_rounded,
                          controller: _email,
                          keyboardType: TextInputType.emailAddress,
                          hint: 'you@company.com',
                        ),
                        const SizedBox(height: 12),
                        _Field(
                          label: 'Password',
                          icon: Icons.lock_outline_rounded,
                          controller: _password,
                          obscureText: _obscure,
                          hint: '••••••••',
                          suffix: IconButton(
                            onPressed: () => setState(() => _obscure = !_obscure),
                            icon: Icon(
                              _obscure
                                  ? Icons.visibility_outlined
                                  : Icons.visibility_off_outlined,
                            ),
                          ),
                        ),
                        if (_error != null) ...<Widget>[
                          const SizedBox(height: 14),
                          _ErrorBanner(message: _error!, compact: true),
                        ],
                        const SizedBox(height: 16),
                        SizedBox(
                          width: double.infinity,
                          height: 50,
                          child: FilledButton(
                            onPressed: _loading ? null : _login,
                            style: FilledButton.styleFrom(
                              backgroundColor: _Palette.indigo,
                              shape: RoundedRectangleBorder(
                                borderRadius: BorderRadius.circular(16),
                              ),
                            ),
                            child: _loading
                                ? const SizedBox(
                                    width: 22,
                                    height: 22,
                                    child: CircularProgressIndicator(
                                      strokeWidth: 2.4,
                                      color: Colors.white,
                                    ),
                                  )
                                : const Text(
                                    'Sign in',
                                    style: TextStyle(
                                      fontWeight: FontWeight.w700,
                                      fontSize: 16,
                                    ),
                                  ),
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 14),
                  const Text(
                    'Test-environment credentials are prefilled. '
                    'Replace them for production.',
                    textAlign: TextAlign.center,
                    style: TextStyle(
                      color: Colors.white70,
                      fontSize: 11.5,
                      height: 1.5,
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

  Widget _buildHero() {
    final int pending = _countWhere('pending');
    return Container(
      margin: const EdgeInsets.fromLTRB(14, 14, 14, 0),
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        gradient: _Palette.hero,
        borderRadius: BorderRadius.circular(24),
      ),
      child: Row(
        children: <Widget>[
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: <Widget>[
                const Text(
                  'Ad listings',
                  style: TextStyle(
                    color: Colors.white70,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: 2),
                FittedBox(
                  fit: BoxFit.scaleDown,
                  alignment: Alignment.centerLeft,
                  child: Text(
                    '${_ads.length}',
                    style: const TextStyle(
                      color: Colors.white,
                      fontSize: 32,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
                const SizedBox(height: 6),
                Text(
                  pending > 0
                      ? '$pending listing(s) awaiting review'
                      : 'Nothing waiting for review',
                  style: TextStyle(
                    color: Colors.white.withValues(alpha: .9),
                    fontSize: 12.5,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(width: 12),
          Container(
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: Colors.white.withValues(alpha: .18),
              borderRadius: BorderRadius.circular(18),
            ),
            child: const Icon(
              Icons.campaign_rounded,
              color: Colors.white,
              size: 30,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildStats() {
    final List<_StatSpec> specs = <_StatSpec>[
      _StatSpec(
        'Active',
        _countWhere('active'),
        _Palette.mint,
        Icons.play_circle_filled_rounded,
        () => setState(() => _filter = 'active'),
      ),
      _StatSpec(
        'Pending',
        _countWhere('pending'),
        _Palette.sunset,
        Icons.hourglass_top_rounded,
        () => setState(() => _filter = 'pending'),
      ),
      _StatSpec(
        'Draft',
        _countWhere('draft'),
        _Palette.berry,
        Icons.edit_note_rounded,
        () => setState(() => _filter = 'draft'),
      ),
      _StatSpec(
        'Featured',
        _ads
            .where((Map<String, dynamic> ad) => ad['featured'] == true)
            .length,
        _Palette.ocean,
        Icons.workspace_premium_rounded,
        () => setState(() => _filter = 'all'),
      ),
    ];
    return Padding(
      padding: const EdgeInsets.fromLTRB(14, 14, 14, 0),
      child: LayoutBuilder(
        builder: (BuildContext context, BoxConstraints constraints) {
          final int columns = constraints.maxWidth > 620
              ? 4
              : constraints.maxWidth > 380
              ? 2
              : 1;
          final double width =
              (constraints.maxWidth - (10 * (columns - 1))) / columns;
          return Wrap(
            spacing: 10,
            runSpacing: 10,
            children: specs
                .map(
                  (_StatSpec spec) => SizedBox(
                    width: width,
                    child: _StatTile(spec: spec),
                  ),
                )
                .toList(),
          );
        },
      ),
    );
  }

  Widget _buildFilters() {
    const List<String> filters = <String>[
      'all',
      'pending',
      'active',
      'draft',
      'paused',
      'rejected',
      'archived',
    ];
    return Padding(
      padding: const EdgeInsets.fromLTRB(14, 16, 14, 10),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 12),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: const Color(0xFFE3E7F1)),
            ),
            child: Row(
              children: <Widget>[
                const Icon(Icons.search_rounded, color: _Palette.indigo),
                const SizedBox(width: 8),
                Expanded(
                  child: TextField(
                    controller: _search,
                    onChanged: (String value) => setState(() => _query = value),
                    decoration: const InputDecoration(
                      hintText: 'Search title, platform or link…',
                      border: InputBorder.none,
                    ),
                  ),
                ),
                if (_query.isNotEmpty)
                  IconButton(
                    tooltip: 'Clear search',
                    onPressed: () {
                      _search.clear();
                      setState(() => _query = '');
                    },
                    icon: const Icon(Icons.close_rounded, size: 18),
                  ),
              ],
            ),
          ),
          const SizedBox(height: 10),
          SizedBox(
            height: 38,
            child: ListView.separated(
              scrollDirection: Axis.horizontal,
              itemCount: filters.length,
              separatorBuilder: (BuildContext context, int index) =>
                  const SizedBox(width: 8),
              itemBuilder: (BuildContext context, int index) {
                final String value = filters[index];
                final bool selected = _filter == value;
                final int count = value == 'all'
                    ? _ads.length
                    : _countWhere(value);
                return Material(
                  color: selected ? _Palette.indigo : Colors.white,
                  borderRadius: BorderRadius.circular(12),
                  child: InkWell(
                    borderRadius: BorderRadius.circular(12),
                    onTap: () => setState(() => _filter = value),
                    child: Padding(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 13,
                        vertical: 9,
                      ),
                      child: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: <Widget>[
                          Text(
                            value == 'all' ? 'All' : _pretty(value),
                            style: TextStyle(
                              fontSize: 12.5,
                              fontWeight: FontWeight.w700,
                              color: selected ? Colors.white : Colors.black87,
                            ),
                          ),
                          const SizedBox(width: 6),
                          Container(
                            padding: const EdgeInsets.symmetric(
                              horizontal: 6,
                              vertical: 1,
                            ),
                            decoration: BoxDecoration(
                              color: selected
                                  ? Colors.white.withValues(alpha: .25)
                                  : const Color(0xFFEDF0F8),
                              borderRadius: BorderRadius.circular(8),
                            ),
                            child: Text(
                              '$count',
                              style: TextStyle(
                                fontSize: 11,
                                fontWeight: FontWeight.w800,
                                color: selected
                                    ? Colors.white
                                    : const Color(0xFF6B7280),
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                );
              },
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildAdCard(Map<String, dynamic> ad, int index) {
    final String status = ad['status']?.toString() ?? 'draft';
    final String type = ad['type']?.toString() ?? 'others';
    final _PlatformMeta platform = _platformFor(type);
    final Color accent = _statusColor(status);
    final String creative = ad['creative_path']?.toString() ?? '';
    final String currency = ad['currency']?.toString() ?? 'USD';
    final double reward = double.tryParse('${ad['share_reward']}') ?? 0;
    final double maxShare = double.tryParse('${ad['maximum_share']}') ?? 0;
    final bool featured = ad['featured'] == true;

    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Container(
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(20),
          border: Border.all(
            color: featured
                ? const Color(0xFFF7D060)
                : const Color(0xFFE6EAF3),
          ),
          boxShadow: <BoxShadow>[
            BoxShadow(
              color: const Color(0xFF1E293B).withValues(alpha: .05),
              blurRadius: 16,
              offset: const Offset(0, 6),
            ),
          ],
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            Container(
              height: 5,
              decoration: BoxDecoration(
                gradient: LinearGradient(colors: <Color>[
                  accent,
                  Color.lerp(accent, _Palette.pink, .55) ?? accent,
                ]),
                borderRadius: const BorderRadius.vertical(
                  top: Radius.circular(20),
                ),
              ),
            ),
            Padding(
              padding: const EdgeInsets.all(14),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: <Widget>[
                      _AdThumb(creative: creative, platform: platform),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: <Widget>[
                            Row(
                              children: <Widget>[
                                Expanded(
                                  child: Text(
                                    ad['title']?.toString() ?? 'Untitled ad',
                                    maxLines: 2,
                                    overflow: TextOverflow.ellipsis,
                                    style: const TextStyle(
                                      fontWeight: FontWeight.w800,
                                      fontSize: 15.5,
                                    ),
                                  ),
                                ),
                                if (featured)
                                  const Padding(
                                    padding: EdgeInsets.only(left: 6),
                                    child: Icon(
                                      Icons.workspace_premium_rounded,
                                      size: 20,
                                      color: _Palette.amber,
                                    ),
                                  ),
                              ],
                            ),
                            const SizedBox(height: 7),
                            Wrap(
                              spacing: 6,
                              runSpacing: 6,
                              children: <Widget>[
                                _StatusPill(status: status),
                                _Tag(
                                  icon: platform.icon,
                                  label: platform.label,
                                  color: platform.color,
                                ),
                              ],
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 12),
                  _TileRow(
                    items: <_TileSpec>[
                      _TileSpec(
                        'Reward',
                        '$currency ${formatGroupedNumber(reward, 2)}',
                        Icons.card_giftcard_rounded,
                        _Palette.green,
                      ),
                      _TileSpec(
                        'Max shares',
                        maxShare <= 0 ? '—' : '${maxShare.toStringAsFixed(0)}',
                        Icons.share_rounded,
                        _Palette.teal,
                      ),
                      _TileSpec(
                        'Payout cap',
                        maxShare <= 0
                            ? '—'
                            : '$currency ${formatGroupedNumber(reward * maxShare, 2)}',
                        Icons.savings_rounded,
                        _Palette.violet,
                      ),
                      _TileSpec(
                        'Reach',
                        _locationName(ad['location']?.toString()),
                        Icons.public_rounded,
                        _Palette.sky,
                      ),
                    ],
                  ),
                  if ((ad['destination_url']?.toString() ?? '').isNotEmpty) ...<Widget>[
                    const SizedBox(height: 10),
                    Container(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 10,
                        vertical: 8,
                      ),
                      decoration: BoxDecoration(
                        color: const Color(0xFFF5F7FC),
                        borderRadius: BorderRadius.circular(12),
                      ),
                      child: Row(
                        children: <Widget>[
                          const Icon(
                            Icons.link_rounded,
                            size: 15,
                            color: _Palette.indigo,
                          ),
                          const SizedBox(width: 6),
                          Expanded(
                            child: Text(
                              ad['destination_url'].toString(),
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: const TextStyle(
                                fontSize: 12,
                                color: Color(0xFF475569),
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                  const SizedBox(height: 12),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    alignment: WrapAlignment.end,
                    children: <Widget>[
                      _MiniButton(
                        label: 'Edit',
                        icon: Icons.tune_rounded,
                        color: _Palette.indigo,
                        filled: false,
                        onTap: () => _openEditor(ad: ad),
                      ),
                      _MiniButton(
                        label: 'Delete',
                        icon: Icons.delete_outline_rounded,
                        color: _Palette.rose,
                        filled: false,
                        onTap: () => _delete(ad),
                      ),
                      if (status != 'active')
                        _MiniButton(
                          label: 'Approve',
                          icon: Icons.verified_rounded,
                          color: _Palette.green,
                          gradient: _Palette.mint,
                          onTap: () => _approve(ad),
                        ),
                    ],
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
// Ad editor (self-contained: owns its controllers and performs its own write)
// ---------------------------------------------------------------------------

class _AdEditor extends StatefulWidget {
  const _AdEditor({
    required this.api,
    required this.token,
    required this.currencies,
    required this.countries,
    required this.regions,
    this.existing,
  });

  final AuthApi api;
  final String token;
  final List<Map<String, dynamic>> currencies;
  final List<Map<String, dynamic>> countries;
  final List<Map<String, dynamic>> regions;
  final Map<String, dynamic>? existing;

  @override
  State<_AdEditor> createState() => _AdEditorState();
}

class _AdEditorState extends State<_AdEditor> {
  final GlobalKey<FormState> _formKey = GlobalKey<FormState>();

  static const List<String> _statuses = <String>[
    'draft',
    'pending',
    'active',
    'paused',
    'rejected',
    'archived',
  ];

  late final TextEditingController _title;
  late final TextEditingController _description;
  late final TextEditingController _shareMessage;
  late final TextEditingController _destinationUrl;
  late final TextEditingController _paidAmount;
  late final TextEditingController _shareReward;
  late final TextEditingController _maximumShare;
  late final TextEditingController _creativeUrl;
  late String _type;
  late String _currency;
  late String? _location;
  late bool _featured;
  late String _status;
  String? _creativePath;
  bool _removeImage = false;
  PlatformFile? _pickedFile;
  bool _saving = false;
  String? _error;

  bool get _isEditing => widget.existing != null;

  @override
  void initState() {
    super.initState();
    final Map<String, dynamic> ad =
        widget.existing ?? const <String, dynamic>{};
    _title = TextEditingController(text: ad['title']?.toString() ?? '');
    _description = TextEditingController(
      text: ad['description']?.toString() ?? '',
    );
    _shareMessage = TextEditingController(
      text: ad['share_message']?.toString() ?? '',
    );
    _destinationUrl = TextEditingController(
      text: ad['destination_url']?.toString() ?? '',
    );
    _type = ad['type']?.toString() ?? 'telegram';
    _currency = ad['currency']?.toString() ?? 'USD';
    _location = ad['location']?.toString();
    _featured = ad['featured'] == true;
    // Matches the previous screen's wire behaviour: an edited listing keeps
    // its stored status and a brand-new one starts as 'pending'.
    _status = ad['status']?.toString() ?? 'pending';
    _creativePath = ad['creative_path']?.toString();

    // Keep monetary fields in the currency's real precision so an untouched
    // save round-trips the stored value instead of re-rounding it.
    final int units = _minorUnitsFor(_currency);
    _paidAmount = TextEditingController(
      text: _formatMoney(ad['paid_amount'], units),
    );
    _shareReward = TextEditingController(
      text: _formatMoney(ad['share_reward'], units),
    );
    _maximumShare = TextEditingController(
      text: _formatMoney(ad['maximum_share'], 0),
    );
    _creativeUrl = TextEditingController(text: _creativePath ?? '');
  }

  @override
  void dispose() {
    _title.dispose();
    _description.dispose();
    _shareMessage.dispose();
    _destinationUrl.dispose();
    _paidAmount.dispose();
    _shareReward.dispose();
    _maximumShare.dispose();
    _creativeUrl.dispose();
    super.dispose();
  }

  // -- helpers -----------------------------------------------------------

  String _locationName(String? code) {
    if (code == null || code.isEmpty) return 'Global';
    for (final Map<String, dynamic> row in widget.countries) {
      if (row['code']?.toString().toUpperCase() == code.toUpperCase()) {
        return '${row['name']} · $code';
      }
    }
    for (final Map<String, dynamic> row in widget.regions) {
      if (row['code']?.toString() == code) return '${row['name']} · Region';
    }
    return code;
  }

  String _formatMoney(dynamic value, int units) {
    final double parsed = double.tryParse('$value') ?? 0;
    return parsed.toStringAsFixed(units);
  }

  int _minorUnitsFor(String code) {
    for (final Map<String, dynamic> row in widget.currencies) {
      if (row['code']?.toString() == code) {
        return int.tryParse('${row['minor_units']}') ?? 2;
      }
    }
    return 2;
  }

  String get _currencyName {
    for (final Map<String, dynamic> row in widget.currencies) {
      if (row['code']?.toString() == _currency) {
        return row['name']?.toString() ?? _currency;
      }
    }
    return _currency;
  }

  double get _reward => double.tryParse(_shareReward.text) ?? 0;

  /// Parsed as an int, matching the value that is actually submitted, so the
  /// live budget card can never disagree with the save-time guard.
  int get _maxShare => int.tryParse(_maximumShare.text.trim()) ?? 0;

  double get _cap => _reward * _maxShare;

  double get _paid => double.tryParse(_paidAmount.text) ?? 0;

  bool get _budgetCoversCap => _paid + 1e-6 >= _cap;

  /// Re-formats the money fields when the currency changes precision.
  void _changeCurrency(String? code) {
    if (code == null || code == _currency) return;
    final int units = _minorUnitsFor(code);
    final double paid = _paid;
    final double reward = _reward;
    setState(() {
      _currency = code;
      _paidAmount.text = paid.toStringAsFixed(units);
      _shareReward.text = reward.toStringAsFixed(units);
    });
  }

  Future<void> _pickFile() async {
    final FilePickerResult? result = await FilePicker.platform.pickFiles(
      type: FileType.image,
      allowMultiple: false,
      withData: true,
    );
    if (result == null || result.files.isEmpty) return;
    final PlatformFile file = result.files.first;
    if (file.bytes == null) return;
    if (!mounted) return;
    setState(() {
      _pickedFile = file;
      _removeImage = false;
    });
  }

  void _clearImage() {
    setState(() {
      _pickedFile = null;
      _creativeUrl.clear();
      if (_isEditing) {
        _removeImage = true;
      } else {
        _creativePath = null;
      }
    });
  }

  // -- save --------------------------------------------------------------

  Future<void> _save() async {
    if (_saving) return;
    if (!(_formKey.currentState?.validate() ?? false)) return;

    final double pool = double.tryParse(_paidAmount.text.trim()) ?? 0;
    final double reward = double.tryParse(_shareReward.text.trim()) ?? 0;
    final int maximum = int.tryParse(_maximumShare.text.trim()) ?? 0;
    if (pool + 1e-6 < reward * maximum) {
      setState(
        () => _error =
            'Reward pool must cover reward per share × maximum shares '
            '($_currency ${formatGroupedNumber(reward * maximum, 2)}).',
      );
      return;
    }

    // Captured before the awaits below so the snackbar never resolves a
    // messenger from an unmounted tree.
    final ScaffoldMessengerState messenger = ScaffoldMessenger.of(context);
    final NavigatorState navigator = Navigator.of(context);
    setState(() {
      _saving = true;
      _error = null;
    });

    // Same wire shape as before the redesign: amounts are numeric, optional
    // text is null rather than empty, and creative_path is only sent when it
    // actually changed.
    String? orNull(String value) => value.isEmpty ? null : value;

    final Map<String, dynamic> payload = <String, dynamic>{
      'title': _title.text.trim(),
      'type': _type,
      'description': orNull(_description.text.trim()),
      'share_message': orNull(_shareMessage.text.trim()),
      'destination_url': orNull(_destinationUrl.text.trim()),
      'paid_amount': pool,
      'currency': _currency,
      'share_reward': reward,
      'maximum_share': maximum,
      'location': _location,
      'featured': _featured,
      'status': _status,
    };

    final String enteredCreative = _creativeUrl.text.trim();
    if (_removeImage) {
      payload['creative_path'] = null;
    } else if (!_isEditing ||
        enteredCreative != (widget.existing?['creative_path']?.toString() ?? '')) {
      payload['creative_path'] = enteredCreative.isEmpty ? null : enteredCreative;
    }

    try {
      if (_pickedFile != null) {
        final String id = widget.existing?['id']?.toString() ?? '';
        final Map<String, String> fields = <String, String>{
          for (final MapEntry<String, dynamic> entry in payload.entries)
            if (entry.value != null)
              entry.key: entry.value is bool
                  ? ((entry.value as bool) ? '1' : '0')
                  : entry.value.toString(),
          // Laravel-style method spoofing: multipart POST acts as PATCH.
          if (_isEditing) '_method': 'PATCH',
        };
        await widget.api.postMultipart(
          path: _isEditing ? 'admin/ads/$id' : 'admin/ads',
          token: widget.token,
          fields: fields,
          fileField: 'creative_file',
          fileBytes: _pickedFile!.bytes!,
          fileName: _pickedFile!.name,
          method: 'POST',
        );
      } else {
        if (_isEditing) {
          final String id = widget.existing?['id']?.toString() ?? '';
          final Map<String, dynamic> body = Map<String, dynamic>.from(payload);
          if (_removeImage) {
            body['creative_path'] = null;
          }
          await widget.api.patch('admin/ads/$id', widget.token, body);
        } else {
          await widget.api.post('admin/ads', widget.token, payload);
        }
      }
      messenger
        ..hideCurrentSnackBar()
        ..showSnackBar(
          SnackBar(
            behavior: SnackBarBehavior.floating,
            backgroundColor: _Palette.green,
            margin: const EdgeInsets.fromLTRB(14, 0, 14, 18),
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(14),
            ),
            content: Row(
              children: <Widget>[
                const Icon(Icons.check_circle_outline_rounded, size: 20),
                const SizedBox(width: 10),
                Expanded(
                  child: Text(
                    _isEditing
                        ? 'Ad listing updated.'
                        : 'Ad listing created.',
                    style: const TextStyle(fontWeight: FontWeight.w600),
                  ),
                ),
              ],
            ),
          ),
        );
      if (!mounted) return;
      navigator.pop(true);
    } on AuthException catch (error) {
      if (!mounted) return;
      setState(() {
        _saving = false;
        _error = error.message;
      });
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _saving = false;
        _error = error.toString();
      });
    }
  }

  // -- UI ----------------------------------------------------------------

  @override
  Widget build(BuildContext context) {
    final double maxHeight = MediaQuery.sizeOf(context).height * .92;
    final EdgeInsets keyboard = MediaQuery.viewInsetsOf(context);

    return PopScope(
      // Block back/swipe dismissal mid-save so the request is never orphaned.
      canPop: !_saving,
      onPopInvokedWithResult: (bool didPop, Object? result) {
        if (!didPop && _saving) return;
      },
      child: Padding(
        padding: EdgeInsets.only(bottom: keyboard.bottom),
        child: Container(
          height: maxHeight,
          decoration: const BoxDecoration(
            color: Color(0xFFF7F8FC),
            borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
          ),
          child: Form(
            key: _formKey,
            child: Column(
              children: <Widget>[
                _buildSheetHeader(),
                Expanded(
                  child: ListView(
                    padding: const EdgeInsets.fromLTRB(16, 4, 16, 24),
                    children: <Widget>[
                      _buildCreativeSection(),
                      const SizedBox(height: 16),
                      _sectionTitle('Campaign details'),
                      _Field(
                        label: 'Title',
                        icon: Icons.title_rounded,
                        controller: _title,
                        hint: 'Share and earn 5% back',
                        validator: (String? value) =>
                            (value ?? '').trim().isEmpty
                            ? 'Title is required'
                            : null,
                      ),
                      const SizedBox(height: 12),
                      Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: <Widget>[
                          Expanded(
                            child: _SelectField<String>(
                              label: 'Platform',
                              icon: Icons.devices_rounded,
                              value: _type,
                              items: _platforms.entries
                                  .map(
                                    (MapEntry<String, _PlatformMeta> entry) =>
                                        DropdownMenuItem<String>(
                                          value: entry.key,
                                          child: Text(
                                            entry.value.label,
                                            overflow: TextOverflow.ellipsis,
                                          ),
                                        ),
                                  )
                                  .toList(),
                              onChanged: _saving
                                  ? null
                                  : (String? value) => setState(
                                      () => _type = value ?? _type,
                                    ),
                            ),
                          ),
                          const SizedBox(width: 10),
                          Expanded(
                            child: _SelectField<String>(
                              label: 'Status',
                              icon: Icons.flag_rounded,
                              value: _status,
                              items: _statuses
                                  .map(
                                    (String value) => DropdownMenuItem<String>(
                                      value: value,
                                      child: Text(_pretty(value)),
                                    ),
                                  )
                                  .toList(),
                              onChanged: _saving
                                  ? null
                                  : (String? value) => setState(
                                      () => _status = value ?? _status,
                                    ),
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 12),
                      _Field(
                        label: 'Description',
                        icon: Icons.notes_rounded,
                        controller: _description,
                        hint: 'What should users do?',
                        minLines: 2,
                        maxLines: 4,
                      ),
                      const SizedBox(height: 12),
                      _Field(
                        label: 'Share message',
                        icon: Icons.share_rounded,
                        controller: _shareMessage,
                        hint: 'Pre-filled text for their share',
                        minLines: 2,
                        maxLines: 4,
                      ),
                      const SizedBox(height: 12),
                      _Field(
                        label: 'Destination URL',
                        icon: Icons.link_rounded,
                        controller: _destinationUrl,
                        hint: 'https://…',
                        keyboardType: TextInputType.url,
                      ),
                      const SizedBox(height: 20),
                      _sectionTitle('Targeting'),
                      Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: <Widget>[
                          Expanded(
                            child: _SelectField<String?>(
                              label: 'Country',
                              icon: Icons.flag_outlined,
                              value: null,
                              hint: _location == null || _location!.isEmpty
                                  ? 'Global (everyone)'
                                  : _location,
                              items: <DropdownMenuItem<String?>>[
                                DropdownMenuItem<String?>(
                                  value: null,
                                  child: const Text('Global (everyone)'),
                                ),
                                ...widget.countries.map(
                                  (Map<String, dynamic> row) =>
                                      DropdownMenuItem<String?>(
                                        value: row['code']?.toString(),
                                        child: Text(
                                          row['name']?.toString() ?? '',
                                          overflow: TextOverflow.ellipsis,
                                        ),
                                      ),
                                ),
                                // Keep a stored targeting code selectable even
                                // when it is no longer in the country list.
                                if (_location != null &&
                                    _location!.isNotEmpty &&
                                    !widget.countries.any(
                                      (Map<String, dynamic> row) =>
                                          row['code']?.toString() == _location,
                                    ) &&
                                    !widget.regions.any(
                                      (Map<String, dynamic> row) =>
                                          row['code']?.toString() == _location,
                                    ))
                                  DropdownMenuItem<String?>(
                                    value: _location,
                                    child: Text(
                                      '${_locationName(_location)} (current)',
                                      overflow: TextOverflow.ellipsis,
                                    ),
                                  ),
                              ],
                              onChanged: _saving
                                  ? null
                                  : (String? value) => setState(
                                      () => _location = value,
                                    ),
                            ),
                          ),
                          const SizedBox(width: 10),
                          Expanded(
                            child: _SelectField<String>(
                              label: 'Currency',
                              icon: Icons.payments_rounded,
                              value: _currency,
                              items: widget.currencies.isEmpty
                                  ? <DropdownMenuItem<String>>[
                                      DropdownMenuItem<String>(
                                        value: _currency,
                                        child: Text(_currency),
                                      ),
                                    ]
                                  : widget.currencies
                                        .map(
                                          (Map<String, dynamic> row) =>
                                              DropdownMenuItem<String>(
                                                value:
                                                    row['code']?.toString() ??
                                                    'USD',
                                                child: Text(
                                                  row['name']?.toString() ??
                                                      row['code']?.toString() ??
                                                      '',
                                                  overflow:
                                                      TextOverflow.ellipsis,
                                                ),
                                              ),
                                        )
                                        .toList(),
                              onChanged: _saving ? null : _changeCurrency,
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 20),
                      _sectionTitle('Rewards & budget · $_currencyName'),
                      Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: <Widget>[
                          Expanded(
                            child: _Field(
                              label: 'Paid amount ($_currency)',
                              icon: Icons.account_balance_wallet_rounded,
                              controller: _paidAmount,
                              keyboardType: const TextInputType.numberWithOptions(
                                decimal: true,
                              ),
                              hint: '0.00',
                            ),
                          ),
                          const SizedBox(width: 10),
                          Expanded(
                            child: _Field(
                              label: 'Reward / share ($_currency)',
                              icon: Icons.card_giftcard_rounded,
                              controller: _shareReward,
                              keyboardType: const TextInputType.numberWithOptions(
                                decimal: true,
                              ),
                              hint: '0.00',
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 12),
                      _Field(
                        label: 'Maximum shares',
                        icon: Icons.groups_rounded,
                        controller: _maximumShare,
                        keyboardType: TextInputType.number,
                        hint: '0',
                      ),
                      const SizedBox(height: 12),
                      _buildBudgetCard(),
                      const SizedBox(height: 16),
                      _SwitchCard(
                        icon: Icons.workspace_premium_rounded,
                        gradient: _Palette.sunset,
                        title: 'Feature this ad',
                        subtitle: 'Pinned above regular listings',
                        value: _featured,
                        onChanged: _saving
                            ? null
                            : (bool value) =>
                                  setState(() => _featured = value),
                      ),
                      if (_error != null) ...<Widget>[
                        const SizedBox(height: 16),
                        _ErrorBanner(message: _error!, compact: true),
                      ],
                    ],
                  ),
                ),
                _buildSheetFooter(),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildSheetHeader() {
    return Container(
      padding: const EdgeInsets.fromLTRB(18, 12, 12, 14),
      decoration: const BoxDecoration(
        gradient: _Palette.hero,
        borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
      ),
      child: Row(
        children: <Widget>[
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Text(
                  _isEditing ? 'Edit ad listing' : 'Create ad listing',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 17,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  _isEditing
                      ? 'Changes publish as soon as you save'
                      : 'Draft it now, approve it later',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    color: Colors.white.withValues(alpha: .85),
                    fontSize: 12,
                  ),
                ),
              ],
            ),
          ),
          IconButton(
            onPressed: _saving ? null : () => Navigator.of(context).pop(),
            icon: const Icon(Icons.close_rounded, color: Colors.white),
          ),
        ],
      ),
    );
  }

  Widget _buildSheetFooter() {
    return Container(
      padding: const EdgeInsets.fromLTRB(16, 12, 16, 18),
      decoration: BoxDecoration(
        color: Colors.white,
        border: Border(top: BorderSide(color: const Color(0xFFE6EAF3))),
      ),
      child: SafeArea(
        top: false,
        child: Row(
          children: <Widget>[
            Expanded(
              child: OutlinedButton(
                onPressed: _saving ? null : () => Navigator.of(context).pop(),
                style: OutlinedButton.styleFrom(
                  minimumSize: const Size.fromHeight(48),
                  side: const BorderSide(color: Color(0xFFDDE2EE)),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(15),
                  ),
                ),
                child: const Text(
                  'Cancel',
                  style: TextStyle(
                    fontWeight: FontWeight.w700,
                    color: Color(0xFF475569),
                  ),
                ),
              ),
            ),
            const SizedBox(width: 10),
            Expanded(
              flex: 2,
              child: SizedBox(
                height: 48,
                child: DecoratedBox(
                  decoration: BoxDecoration(
                    gradient: _saving ? null : _Palette.mint,
                    color: _saving ? const Color(0xFFCBD5E1) : null,
                    borderRadius: BorderRadius.circular(15),
                  ),
                  child: FilledButton(
                    onPressed: _saving ? null : _save,
                    style: FilledButton.styleFrom(
                      backgroundColor: Colors.transparent,
                      shadowColor: Colors.transparent,
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(15),
                      ),
                    ),
                    child: _saving
                        ? const SizedBox(
                            width: 20,
                            height: 20,
                            child: CircularProgressIndicator(
                              strokeWidth: 2.2,
                              color: Colors.white,
                            ),
                          )
                        : Text(
                            _isEditing ? 'Save changes' : 'Create listing',
                            style: const TextStyle(
                              fontWeight: FontWeight.w800,
                              fontSize: 14.5,
                            ),
                          ),
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _sectionTitle(String text) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 10, top: 2),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: <Widget>[
          Container(
            width: 4,
            height: 16,
            decoration: BoxDecoration(
              gradient: _Palette.berry,
              borderRadius: BorderRadius.circular(4),
            ),
          ),
          const SizedBox(width: 8),
          Text(
            text,
            style: const TextStyle(
              fontWeight: FontWeight.w800,
              fontSize: 13,
              letterSpacing: .2,
              color: Color(0xFF334155),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildBudgetCard() {
    final int units = _minorUnitsFor(_currency);
    final Color color = _budgetCoversCap ? _Palette.green : _Palette.rose;
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          colors: <Color>[
            color.withValues(alpha: .10),
            Colors.white.withValues(alpha: .55),
          ],
        ),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: color.withValues(alpha: .35)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Row(
            children: <Widget>[
              Icon(
                _budgetCoversCap
                    ? Icons.verified_rounded
                    : Icons.warning_amber_rounded,
                size: 18,
                color: color,
              ),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  _budgetCoversCap
                      ? 'Budget covers the full payout'
                      : 'Budget is below the payout cap',
                  style: TextStyle(
                    fontWeight: FontWeight.w800,
                    fontSize: 12.5,
                    color: color,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          _TileRow(
            items: <_TileSpec>[
              _TileSpec(
                'Per share',
                '$_currency ${formatGroupedNumber(_reward, units)}',
                Icons.card_giftcard_rounded,
                _Palette.green,
              ),
              _TileSpec(
                'Shares',
                _maxShare <= 0 ? '—' : '$_maxShare',
                Icons.groups_rounded,
                _Palette.teal,
              ),
              _TileSpec(
                'Cap',
                '$_currency ${formatGroupedNumber(_cap, units)}',
                Icons.savings_rounded,
                _Palette.violet,
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildCreativeSection() {
    final bool hasPicked = _pickedFile != null;
    final String entered = _creativeUrl.text.trim();
    final bool hasRemote = entered.isNotEmpty;
    final bool showRemote = hasRemote && entered.startsWith('http');
    final String previewUrl = hasPicked ? '' : entered;

    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: const Color(0xFFE6EAF3)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          _sectionTitle('Creative'),
          Row(
            children: <Widget>[
              ClipRRect(
                borderRadius: BorderRadius.circular(14),
                child: SizedBox(
                  width: 76,
                  height: 76,
                  child: hasPicked
                      ? Image.memory(
                          _pickedFile!.bytes!,
                          fit: BoxFit.cover,
                          errorBuilder: (BuildContext context, Object error,
                                  StackTrace? trace) =>
                              const _CreativeFallback(),
                        )
                      : showRemote
                      ? Image.network(
                          previewUrl,
                          fit: BoxFit.cover,
                          loadingBuilder:
                              (BuildContext context, Widget child,
                                  ImageChunkEvent? progress) {
                                if (progress == null) return child;
                                return const _CreativeFallback(
                                  busy: true,
                                );
                              },
                          errorBuilder: (BuildContext context, Object error,
                                  StackTrace? trace) =>
                              const _CreativeFallback(),
                        )
                      : const _CreativeFallback(),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    Text(
                      hasPicked
                          ? _pickedFile!.name
                          : hasRemote
                          ? 'Current image attached'
                          : 'No image selected',
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        fontWeight: FontWeight.w700,
                        fontSize: 12.5,
                      ),
                    ),
                    const SizedBox(height: 8),
                    Wrap(
                      spacing: 8,
                      runSpacing: 8,
                      children: <Widget>[
                        _MiniButton(
                          label: hasPicked ? 'Replace' : 'Upload',
                          icon: Icons.upload_file_rounded,
                          color: _Palette.indigo,
                          gradient: _Palette.ocean,
                          onTap: _saving ? null : _pickFile,
                        ),
                        if (hasPicked || hasRemote)
                          _MiniButton(
                            label: 'Remove',
                            icon: Icons.delete_outline_rounded,
                            color: _Palette.rose,
                            onTap: _saving ? null : _clearImage,
                          ),
                      ],
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          _Field(
            label: 'Or link an existing image',
            icon: Icons.image_rounded,
            controller: _creativeUrl,
            hint: 'https://…/creative.jpg',
            keyboardType: TextInputType.url,
            onChanged: _saving
                ? null
                : (String value) => setState(() => _removeImage = false),
          ),
        ],
      ),
    );
  }
}

// ---------------------------------------------------------------------------
// Shared primitives (mirrored from the other admin consoles)
// ---------------------------------------------------------------------------

class _TileSpec {
  const _TileSpec(this.label, this.value, this.icon, this.color);

  final String label;
  final String value;
  final IconData icon;
  final Color color;
}

class _StatSpec {
  const _StatSpec(this.label, this.value, this.gradient, this.icon, this.onTap);

  final String label;
  final int value;
  final LinearGradient gradient;
  final IconData icon;
  final VoidCallback onTap;
}

/// Equal-width tiles that reflow instead of overflowing on narrow screens.
class _TileRow extends StatelessWidget {
  const _TileRow({required this.items, this.spacing = 10});

  final List<_TileSpec> items;
  final double spacing;

  @override
  Widget build(BuildContext context) {
    if (items.isEmpty) return const SizedBox.shrink();
    return LayoutBuilder(
      builder: (BuildContext context, BoxConstraints constraints) {
        final double span = constraints.maxWidth.isFinite
            ? constraints.maxWidth
            : 140;
        int columns = ((span + spacing) / (140 + spacing)).floor();
        if (columns < 1) columns = 1;
        if (columns > items.length) columns = items.length;
        final double width = (span - spacing * (columns - 1)) / columns;
        return Wrap(
          spacing: spacing,
          runSpacing: spacing,
          children: <Widget>[
            for (final _TileSpec item in items)
              SizedBox(width: width, child: _TileBox(spec: item)),
          ],
        );
      },
    );
  }
}

class _TileBox extends StatelessWidget {
  const _TileBox({required this.spec});

  final _TileSpec spec;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 11, vertical: 10),
    decoration: BoxDecoration(
      color: const Color(0xFFF5F7FC),
      borderRadius: BorderRadius.circular(14),
      border: Border.all(color: spec.color.withValues(alpha: .18)),
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      mainAxisSize: MainAxisSize.min,
      children: <Widget>[
        Row(
          mainAxisSize: MainAxisSize.min,
          children: <Widget>[
            Icon(spec.icon, size: 14, color: spec.color),
            const SizedBox(width: 5),
            Flexible(
              child: Text(
                spec.label,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  fontSize: 10.5,
                  fontWeight: FontWeight.w700,
                  color: Color(0xFF64748B),
                ),
              ),
            ),
          ],
        ),
        const SizedBox(height: 4),
        FittedBox(
          fit: BoxFit.scaleDown,
          alignment: Alignment.centerLeft,
          child: Text(
            spec.value,
            maxLines: 1,
            style: TextStyle(
              fontSize: 13.5,
              fontWeight: FontWeight.w800,
              color: spec.color,
            ),
          ),
        ),
      ],
    ),
  );
}

class _StatTile extends StatelessWidget {
  const _StatTile({required this.spec});

  final _StatSpec spec;

  @override
  Widget build(BuildContext context) => Material(
    color: Colors.transparent,
    child: InkWell(
      borderRadius: BorderRadius.circular(20),
      onTap: spec.onTap,
      child: Container(
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          gradient: spec.gradient,
          borderRadius: BorderRadius.circular(20),
          boxShadow: <BoxShadow>[
            BoxShadow(
              color: spec.gradient.colors.last.withValues(alpha: .3),
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
                Icon(spec.icon, color: Colors.white, size: 18),
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
            const SizedBox(height: 10),
            FittedBox(
              fit: BoxFit.scaleDown,
              alignment: Alignment.centerLeft,
              child: Text(
                '${spec.value}',
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
              spec.label,
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
      ),
    ),
  );
}

class _Tag extends StatelessWidget {
  const _Tag({required this.icon, required this.label, required this.color});

  final IconData icon;
  final String label;
  final Color color;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
    decoration: BoxDecoration(
      color: color.withValues(alpha: .12),
      borderRadius: BorderRadius.circular(999),
      border: Border.all(color: color.withValues(alpha: .3)),
    ),
    child: Row(
      mainAxisSize: MainAxisSize.min,
      children: <Widget>[
        Icon(icon, size: 12, color: color),
        const SizedBox(width: 4),
        Text(
          label,
          style: TextStyle(
            fontSize: 11,
            fontWeight: FontWeight.w700,
            color: color,
          ),
        ),
      ],
    ),
  );
}

class _StatusPill extends StatelessWidget {
  const _StatusPill({required this.status});

  final String status;

  @override
  Widget build(BuildContext context) {
    final Color color = _statusColor(status);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      decoration: BoxDecoration(
        color: color.withValues(alpha: .14),
        borderRadius: BorderRadius.circular(999),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: <Widget>[
          Icon(_statusIcon(status), size: 12, color: color),
          const SizedBox(width: 4),
          Text(
            _pretty(status),
            style: TextStyle(
              fontSize: 11,
              fontWeight: FontWeight.w800,
              color: color,
            ),
          ),
        ],
      ),
    );
  }
}

class _AdThumb extends StatelessWidget {
  const _AdThumb({required this.creative, required this.platform});

  final String creative;
  final _PlatformMeta platform;

  @override
  Widget build(BuildContext context) {
    final Widget fallback = Container(
      width: 56,
      height: 56,
      decoration: BoxDecoration(
        gradient: LinearGradient(
          colors: <Color>[
            platform.color,
            Color.lerp(platform.color, _Palette.indigo, .5) ?? platform.color,
          ],
        ),
        borderRadius: BorderRadius.circular(16),
      ),
      child: Icon(platform.icon, color: Colors.white, size: 24),
    );
    return ClipRRect(
      borderRadius: BorderRadius.circular(16),
      child: SizedBox(
        width: 56,
        height: 56,
        child: creative.startsWith('http')
            ? Image.network(
                creative,
                fit: BoxFit.cover,
                loadingBuilder: (BuildContext context, Widget child,
                    ImageChunkEvent? progress) {
                  if (progress == null) return child;
                  return fallback;
                },
                errorBuilder: (BuildContext context, Object error,
                        StackTrace? trace) =>
                    fallback,
              )
            : fallback,
      ),
    );
  }
}

class _CreativeFallback extends StatelessWidget {
  const _CreativeFallback({this.busy = false});

  final bool busy;

  @override
  Widget build(BuildContext context) => Container(
    width: 76,
    height: 76,
    color: const Color(0xFFEDF0F8),
    child: busy
        ? const Center(
            child: SizedBox(
              width: 20,
              height: 20,
              child: CircularProgressIndicator(
                strokeWidth: 2,
                color: _Palette.indigo,
              ),
            ),
          )
        : const Icon(
            Icons.image_outlined,
            color: Color(0xFF94A3B8),
            size: 26,
          ),
  );
}

class _MiniButton extends StatelessWidget {
  const _MiniButton({
    required this.label,
    required this.icon,
    required this.color,
    this.onTap,
    this.gradient,
    this.filled = true,
  });

  final String label;
  final IconData icon;
  final Color color;
  final VoidCallback? onTap;
  final LinearGradient? gradient;
  final bool filled;

  @override
  Widget build(BuildContext context) {
    final bool disabled = onTap == null;
    if (filled) {
      return Opacity(
        opacity: disabled ? .5 : 1,
        child: Material(
          color: Colors.transparent,
          child: InkWell(
            borderRadius: BorderRadius.circular(12),
            onTap: onTap,
            child: Container(
              padding: const EdgeInsets.symmetric(
                horizontal: 13,
                vertical: 9,
              ),
              decoration: BoxDecoration(
                gradient: gradient,
                color: gradient == null ? color : null,
                borderRadius: BorderRadius.circular(12),
              ),
              child: Row(
                mainAxisSize: MainAxisSize.min,
                children: <Widget>[
                  Icon(icon, size: 15, color: Colors.white),
                  const SizedBox(width: 6),
                  Text(
                    label,
                    style: const TextStyle(
                      color: Colors.white,
                      fontWeight: FontWeight.w700,
                      fontSize: 12.5,
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      );
    }
    return Opacity(
      opacity: disabled ? .5 : 1,
      child: OutlinedButton.icon(
        onPressed: onTap,
        icon: Icon(icon, size: 15),
        label: Text(label),
        style: OutlinedButton.styleFrom(
          foregroundColor: color,
          side: BorderSide(color: color.withValues(alpha: .45)),
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
          minimumSize: Size.zero,
          tapTargetSize: MaterialTapTargetSize.shrinkWrap,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(12),
          ),
          textStyle: const TextStyle(
            fontWeight: FontWeight.w700,
            fontSize: 12.5,
          ),
        ),
      ),
    );
  }
}

class _SwitchCard extends StatelessWidget {
  const _SwitchCard({
    required this.icon,
    required this.gradient,
    required this.title,
    required this.subtitle,
    required this.value,
    this.onChanged,
  });

  final IconData icon;
  final LinearGradient gradient;
  final String title;
  final String subtitle;
  final bool value;
  final ValueChanged<bool>? onChanged;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
    decoration: BoxDecoration(
      color: Colors.white,
      borderRadius: BorderRadius.circular(16),
      border: Border.all(
        color: value ? _Palette.amber.withValues(alpha: .45) : const Color(0xFFE6EAF3),
      ),
    ),
    child: Row(
      children: <Widget>[
        Container(
          width: 38,
          height: 38,
          decoration: BoxDecoration(
            gradient: value ? gradient : null,
            color: value ? null : const Color(0xFFEFF2F8),
            borderRadius: BorderRadius.circular(12),
          ),
          child: Icon(
            icon,
            size: 20,
            color: value ? Colors.white : const Color(0xFF94A3B8),
          ),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              Text(
                title,
                style: const TextStyle(
                  fontWeight: FontWeight.w700,
                  fontSize: 13.5,
                ),
              ),
              Text(
                subtitle,
                style: const TextStyle(
                  fontSize: 11.5,
                  color: Color(0xFF64748B),
                ),
              ),
            ],
          ),
        ),
        Switch(
          value: value,
          onChanged: onChanged,
        ),
      ],
    ),
  );
}

class _Field extends StatelessWidget {
  const _Field({
    required this.label,
    required this.icon,
    required this.controller,
    this.hint,
    this.keyboardType,
    this.obscureText = false,
    this.suffix,
    this.validator,
    this.onChanged,
    this.minLines,
    this.maxLines = 1,
  });

  final String label;
  final IconData icon;
  final TextEditingController controller;
  final String? hint;
  final TextInputType? keyboardType;
  final bool obscureText;
  final Widget? suffix;
  final String? Function(String?)? validator;
  final ValueChanged<String>? onChanged;
  final int? minLines;
  final int maxLines;

  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: <Widget>[
      Text(
        label,
        style: const TextStyle(
          fontSize: 12,
          fontWeight: FontWeight.w700,
          color: Color(0xFF475569),
        ),
      ),
      const SizedBox(height: 6),
      TextFormField(
        controller: controller,
        keyboardType: keyboardType,
        obscureText: obscureText,
        validator: validator,
        onChanged: onChanged,
        minLines: minLines,
        maxLines: obscureText ? 1 : maxLines,
        decoration: InputDecoration(
          hintText: hint,
          prefixIcon: Icon(icon, size: 18, color: const Color(0xFF94A3B8)),
          suffixIcon: suffix,
          isDense: true,
          filled: true,
          fillColor: Colors.white,
          contentPadding: const EdgeInsets.symmetric(
            horizontal: 12,
            vertical: 13,
          ),
          border: OutlineInputBorder(
            borderRadius: BorderRadius.circular(14),
            borderSide: const BorderSide(color: Color(0xFFE3E7F1)),
          ),
          enabledBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(14),
            borderSide: const BorderSide(color: Color(0xFFE3E7F1)),
          ),
          focusedBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(14),
            borderSide: const BorderSide(color: _Palette.indigo, width: 1.6),
          ),
        ),
      ),
    ],
  );
}

class _SelectField<T> extends StatelessWidget {
  const _SelectField({
    required this.label,
    required this.icon,
    required this.value,
    required this.items,
    required this.onChanged,
    this.hint,
  });

  final String label;
  final IconData icon;
  final T value;
  final List<DropdownMenuItem<T>> items;
  final ValueChanged<T?>? onChanged;
  final String? hint;

  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: <Widget>[
      Text(
        label,
        style: const TextStyle(
          fontSize: 12,
          fontWeight: FontWeight.w700,
          color: Color(0xFF475569),
        ),
      ),
      const SizedBox(height: 6),
      Container(
        padding: const EdgeInsets.symmetric(horizontal: 10),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(14),
          border: Border.all(color: const Color(0xFFE3E7F1)),
        ),
        child: DropdownButtonHideUnderline(
          child: DropdownButton<T>(
            value: value,
            isExpanded: true,
            hint: hint == null
                ? null
                : Text(
                    hint!,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(fontSize: 13),
                  ),
            icon: const Icon(
              Icons.keyboard_arrow_down_rounded,
              color: Color(0xFF94A3B8),
            ),
            items: items,
            onChanged: onChanged,
          ),
        ),
      ),
    ],
  );
}

class _IconBadge extends StatelessWidget {
  const _IconBadge({required this.gradient, required this.icon, this.size = 42});

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
      boxShadow: <BoxShadow>[
        BoxShadow(
          color: gradient.colors.last.withValues(alpha: .35),
          blurRadius: 14,
          offset: const Offset(0, 6),
        ),
      ],
    ),
    child: Icon(icon, color: Colors.white, size: size * .5),
  );
}

class _GradientButton extends StatelessWidget {
  const _GradientButton({
    required this.label,
    required this.gradient,
    this.onPressed,
    this.icon,
  });

  final String label;
  final LinearGradient gradient;
  final VoidCallback? onPressed;
  final IconData? icon;

  @override
  Widget build(BuildContext context) => DecoratedBox(
    decoration: BoxDecoration(
      gradient: gradient,
      borderRadius: BorderRadius.circular(15),
      boxShadow: <BoxShadow>[
        BoxShadow(
          color: gradient.colors.last.withValues(alpha: .32),
          blurRadius: 14,
          offset: const Offset(0, 6),
        ),
      ],
    ),
    child: Material(
      color: Colors.transparent,
      child: InkWell(
        borderRadius: BorderRadius.circular(15),
        onTap: onPressed,
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 12),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: <Widget>[
              if (icon != null) ...<Widget>[
                Icon(icon, size: 17, color: Colors.white),
                const SizedBox(width: 7),
              ],
              Text(
                label,
                style: const TextStyle(
                  color: Colors.white,
                  fontWeight: FontWeight.w800,
                  fontSize: 13.5,
                ),
              ),
            ],
          ),
        ),
      ),
    ),
  );
}

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

class _ErrorBanner extends StatelessWidget {
  const _ErrorBanner({required this.message, this.compact = false, this.onRetry});

  final String message;
  final bool compact;
  final VoidCallback? onRetry;

  @override
  Widget build(BuildContext context) => Container(
    margin: EdgeInsets.fromLTRB(compact ? 0 : 14, compact ? 0 : 14, compact ? 0 : 14, 0),
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
              fontSize: 12.5,
            ),
          ),
        ),
        if (onRetry != null) ...<Widget>[
          const SizedBox(width: 8),
          TextButton(
            onPressed: onRetry,
            style: TextButton.styleFrom(
              foregroundColor: _Palette.rose,
              padding: const EdgeInsets.symmetric(horizontal: 10),
              minimumSize: Size.zero,
              tapTargetSize: MaterialTapTargetSize.shrinkWrap,
            ),
            child: const Text(
              'Retry',
              style: TextStyle(fontWeight: FontWeight.w800),
            ),
          ),
        ],
      ],
    ),
  );
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

class _LoadingState extends StatelessWidget {
  const _LoadingState();

  @override
  Widget build(BuildContext context) => const Center(
    child: Padding(
      padding: EdgeInsets.all(32),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: <Widget>[
          SizedBox(
            width: 34,
            height: 34,
            child: CircularProgressIndicator(
              strokeWidth: 3,
              color: _Palette.indigo,
            ),
          ),
          SizedBox(height: 16),
          Text(
            'Loading ad listings…',
            style: TextStyle(
              color: Color(0xFF64748B),
              fontWeight: FontWeight.w600,
            ),
          ),
        ],
      ),
    ),
  );
}
