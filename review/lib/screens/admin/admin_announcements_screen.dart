import 'package:flutter/material.dart';

import '../../services/auth_api.dart';

class AdminAnnouncementsScreen extends StatefulWidget {
  const AdminAnnouncementsScreen({super.key, required this.api});

  final AuthApi api;

  @override
  State<AdminAnnouncementsScreen> createState() =>
      _AdminAnnouncementsScreenState();
}

class _AdminAnnouncementsScreenState extends State<AdminAnnouncementsScreen> {
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
  bool _obscurePassword = true;
  String _statusFilter = 'all';
  String _typeFilter = 'all';
  List<Map<String, dynamic>> _items = const [];
  int _requestGeneration = 0;

  @override
  void dispose() {
    _email.dispose();
    _password.dispose();
    _search.dispose();
    super.dispose();
  }

  Map<String, dynamic> _map(Object? value) => value is Map
      ? value.map<String, dynamic>(
          (key, value) => MapEntry(key.toString(), value),
        )
      : <String, dynamic>{};

  String _string(Object? value, [String fallback = '']) =>
      value == null ? fallback : value.toString();

  List<Map<String, dynamic>> _rows(Object? response) {
    final data = _map(response)['data'];
    final rows = data is List ? data : _map(data)['data'];
    if (rows is! List) return const [];
    return rows.whereType<Map>().map(_map).toList(growable: false);
  }

  Future<List<Map<String, dynamic>>> _loadAll(String token) async {
    final output = <Map<String, dynamic>>[];
    var page = 1;
    while (page <= 50) {
      final response = await widget.api.get(
        'admin/announcements?per_page=100&page=$page',
        token,
      );
      final rows = _rows(response);
      output.addAll(rows);
      final envelope = _map(_map(response)['data']);
      final meta = _map(_map(response)['meta']);
      final current = _int(envelope['current_page'] ?? meta['current_page'], page);
      final last = _int(envelope['last_page'] ?? meta['last_page'], 1);
      if (last <= current || rows.isEmpty) break;
      page = current + 1;
    }
    return output;
  }

  int _int(Object? value, [int fallback = 0]) => value is num
      ? value.toInt()
      : int.tryParse(value?.toString() ?? '') ?? fallback;

  Future<void> _login() async {
    FocusScope.of(context).unfocus();
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final response = await widget.api.postPublic('admin/auth/login', {
        'email': _email.text.trim(),
        'password': _password.text,
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
    } catch (_) {
      if (mounted) setState(() => _error = 'Sign-in failed. Please try again.');
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _load() async {
    final token = _token;
    if (token == null) return;
    final generation = ++_requestGeneration;
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final items = await _loadAll(token);
      if (!mounted || generation != _requestGeneration) return;
      setState(() => _items = items);
    } on AuthException catch (error) {
      if (mounted && generation == _requestGeneration) {
        setState(() => _error = error.message);
      }
    } catch (_) {
      if (mounted && generation == _requestGeneration) {
        setState(() => _error = 'Announcements could not be loaded. Please retry.');
      }
    } finally {
      if (mounted && generation == _requestGeneration) {
        setState(() => _loading = false);
      }
    }
  }

  bool _isActive(Map<String, dynamic> item) {
    final value = item['is_active'];
    return value != false && value != 0 && value != '0' && value != 'false';
  }

  String _effectiveStatus(Map<String, dynamic> item) {
    if (!_isActive(item)) return 'inactive';
    final now = DateTime.now();
    final start = DateTime.tryParse(_string(item['starts_at']))?.toLocal();
    final end = DateTime.tryParse(_string(item['ends_at']))?.toLocal();
    if (start != null && start.isAfter(now)) return 'scheduled';
    if (end != null && end.isBefore(now)) return 'ended';
    return 'live';
  }

  List<Map<String, dynamic>> get _visibleItems {
    final query = _search.text.trim().toLowerCase();
    final items = _items.where((item) {
      final matchesStatus = _statusFilter == 'all' ||
          _effectiveStatus(item) == _statusFilter;
      final matchesType = _typeFilter == 'all' ||
          _string(item['type'], 'info').toLowerCase() == _typeFilter;
      final haystack = [
        item['title'],
        item['body'],
        item['action_label'],
        item['action_url'],
      ].map((value) => _string(value).toLowerCase()).join(' ');
      return matchesStatus && matchesType && (query.isEmpty || haystack.contains(query));
    }).toList();
    items.sort((a, b) {
      final aDate = DateTime.tryParse(_string(a['created_at'])) ?? DateTime(0);
      final bDate = DateTime.tryParse(_string(b['created_at'])) ?? DateTime(0);
      return bDate.compareTo(aDate);
    });
    return items;
  }

  int _countStatus(String status) =>
      _items.where((item) => _effectiveStatus(item) == status).length;

  int _countType(String type) => type == 'all'
      ? _items.length
      : _items.where((item) => _string(item['type'], 'info') == type).length;

  int _displayType(Map<String, dynamic> item) => _int(item['display_type']);

  String _displayLabel(int value) => switch (value) {
    1 => 'Inline below earnings',
    2 => 'Pinned below earnings',
    _ => 'Pop-up dialog',
  };

  String _dateLabel(Object? value) {
    final date = DateTime.tryParse(_string(value))?.toLocal();
    if (date == null) return 'No date limit';
    const months = [
      'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
      'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec',
    ];
    return '${months[date.month - 1]} ${date.day}, ${date.year}';
  }

  _AnnouncementStyle _styleFor(String value) => switch (value.toLowerCase()) {
    'success' => const _AnnouncementStyle(
      label: 'Success',
      icon: Icons.check_circle_outline_rounded,
      color: _Palette.green,
      gradient: _Palette.mint,
    ),
    'warning' => const _AnnouncementStyle(
      label: 'Warning',
      icon: Icons.warning_amber_rounded,
      color: _Palette.amber,
      gradient: _Palette.sunset,
    ),
    'danger' => const _AnnouncementStyle(
      label: 'Important',
      icon: Icons.error_outline_rounded,
      color: _Palette.rose,
      gradient: _Palette.berry,
    ),
    _ => const _AnnouncementStyle(
      label: 'Information',
      icon: Icons.info_outline_rounded,
      color: _Palette.sky,
      gradient: _Palette.ocean,
    ),
  };

  Future<void> _openEditor([Map<String, dynamic>? existing]) async {
    final token = _token;
    if (token == null || !mounted) return;
    final theme = _adminTheme(context);
    final saved = MediaQuery.sizeOf(context).width < 760
        ? await showModalBottomSheet<bool>(
            context: context,
            isScrollControlled: true,
            useSafeArea: true,
            showDragHandle: true,
            backgroundColor: Colors.white,
            builder: (sheetContext) => SizedBox(
              height: MediaQuery.sizeOf(sheetContext).height * .88,
              child: Theme(
                data: theme,
                child: _AnnouncementForm(
                  api: widget.api,
                  token: token,
                  existing: existing,
                  styleFor: _styleFor,
                ),
              ),
            ),
          )
        : await showDialog<bool>(
            context: context,
            builder: (dialogContext) => Theme(
              data: theme,
              child: Dialog(
                insetPadding: const EdgeInsets.symmetric(
                  horizontal: 24,
                  vertical: 22,
                ),
                child: SizedBox(
                  width: 820,
                  height: MediaQuery.sizeOf(dialogContext).height * .82,
                  child: _AnnouncementForm(
                    api: widget.api,
                    token: token,
                    existing: existing,
                    styleFor: _styleFor,
                  ),
                ),
              ),
            ),
          );
    if (saved == true && mounted) await _load();
  }

  Future<void> _delete(Map<String, dynamic> item) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => Theme(
        data: _adminTheme(context),
        child: AlertDialog(
          icon: const Icon(Icons.delete_outline_rounded, color: _Palette.rose),
          title: const Text('Delete announcement?'),
          content: Text(
            '“${_string(item['title'], 'Untitled announcement')}” will be removed.',
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(dialogContext, false),
              child: const Text('Cancel'),
            ),
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
      _loading = true;
      _error = null;
    });
    try {
      await widget.api.delete('admin/announcements/${item['id']}', _token!);
      if (!mounted) return;
      messenger.showSnackBar(
        const SnackBar(content: Text('Announcement deleted.')),
      );
      await _load();
    } on AuthException catch (error) {
      if (mounted) setState(() => _error = error.message);
    } catch (_) {
      if (mounted) setState(() => _error = 'Announcement could not be deleted.');
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  void _logout() {
    ++_requestGeneration;
    _password.clear();
    setState(() {
      _token = null;
      _items = const [];
      _error = null;
      _statusFilter = 'all';
      _typeFilter = 'all';
      _search.clear();
    });
  }

  @override
  Widget build(BuildContext context) => Theme(
        data: _adminTheme(context),
        child: Builder(
          builder: (context) => _token == null
              ? _loginPage(context)
              : _managementPage(context),
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
                          Icons.campaign_rounded,
                          size: 38,
                          color: Colors.white,
                        ),
                      ),
                      const SizedBox(height: 18),
                      const Text(
                        'Announcements console',
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
                        'Publish timely messages, promotions and important service updates.',
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
                              onSubmitted: (_) { if (!_loading) _login(); },
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
                        'Test-environment credentials are pre-filled.',
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

  Widget _managementPage(BuildContext context) {
    final visible = _visibleItems;
    return Scaffold(
      backgroundColor: Theme.of(context).colorScheme.surface,
      floatingActionButton: _GradientFab(
        icon: Icons.add_rounded,
        label: 'New announcement',
        onPressed: _loading ? null : () => _openEditor(),
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
                'Announcements',
                style: TextStyle(
                  color: Colors.white,
                  fontWeight: FontWeight.w800,
                  letterSpacing: -.3,
                ),
              ),
              actions: [
                _AppBarAction(
                  icon: Icons.refresh_rounded,
                  tooltip: 'Refresh announcements',
                  onPressed: _loading ? null : _load,
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
                    _TileRow(
                      minItemWidth: 150,
                      children: [
                        _StatTile(
                          label: 'Total announcements',
                          value: '${_items.length}',
                          icon: Icons.campaign_rounded,
                          gradient: _Palette.ocean,
                        ),
                        _StatTile(
                          label: 'Live now',
                          value: '${_countStatus('live')}',
                          icon: Icons.bolt_rounded,
                          gradient: _Palette.mint,
                        ),
                        _StatTile(
                          label: 'Scheduled',
                          value: '${_countStatus('scheduled')}',
                          icon: Icons.schedule_rounded,
                          gradient: _Palette.sunset,
                        ),
                        _StatTile(
                          label: 'Pinned',
                          value: '${_items.where((item) => _displayType(item) == 2).length}',
                          icon: Icons.push_pin_outlined,
                          gradient: _Palette.berry,
                        ),
                      ],
                    ),
                    const SizedBox(height: 14),
                    _SearchField(
                      controller: _search,
                      hint: 'Search title, message or action link',
                      onChanged: (_) => setState(() {}),
                    ),
                    const SizedBox(height: 10),
                    _statusFilters(),
                    const SizedBox(height: 8),
                    _typeFilters(),
                    if (_error != null && _items.isNotEmpty) ...[
                      const SizedBox(height: 10),
                      _ErrorBanner(message: _error!),
                    ],
                  ],
                ),
              ),
            ),
            if (_loading && _items.isEmpty)
              const SliverFillRemaining(
                hasScrollBody: false,
                child: _SkeletonList(),
              )
            else if (_error != null && _items.isEmpty)
              SliverFillRemaining(
                hasScrollBody: false,
                child: _EmptyState(
                  icon: Icons.cloud_off_rounded,
                  gradient: _Palette.sunset,
                  title: 'Could not load announcements',
                  message: _error!,
                  actionLabel: 'Try again',
                  onAction: _load,
                ),
              )
            else if (visible.isEmpty)
              SliverFillRemaining(
                hasScrollBody: false,
                child: _EmptyState(
                  icon: _items.isEmpty
                      ? Icons.campaign_outlined
                      : Icons.search_off_rounded,
                  gradient: _Palette.berry,
                  title: _items.isEmpty
                      ? 'No announcements yet'
                      : 'Nothing matches those filters',
                  message: _items.isEmpty
                      ? 'Create a clear, timely message for customers.'
                      : 'Adjust the search or filters to see more announcements.',
                  actionLabel: _items.isEmpty ? 'Create announcement' : 'Clear filters',
                  onAction: _items.isEmpty
                      ? () => _openEditor()
                      : () => setState(() {
                          _search.clear();
                          _statusFilter = 'all';
                          _typeFilter = 'all';
                        }),
                ),
              )
            else
              SliverPadding(
                padding: const EdgeInsets.fromLTRB(16, 12, 16, 120),
                sliver: SliverList.separated(
                  itemCount: visible.length,
                  separatorBuilder: (_, __) => const SizedBox(height: 14),
                  itemBuilder: (context, index) {
                    final item = visible[index];
                    return _AnnouncementCard(
                      item: item,
                      status: _effectiveStatus(item),
                      style: _styleFor(_string(item['type'], 'info')),
                      displayLabel: _displayLabel(_displayType(item)),
                      dateLabel: _dateLabel,
                      onEdit: () => _openEditor(item),
                      onDelete: () => _delete(item),
                    );
                  },
                ),
              ),
          ],
        ),
      ),
    );
  }

  Widget _statusFilters() => Wrap(
        spacing: 8,
        runSpacing: 8,
        children: [
          _FilterChip(
            label: 'All', count: _items.length, color: _Palette.indigo,
            selected: _statusFilter == 'all',
            onSelected: () => setState(() => _statusFilter = 'all'),
          ),
          _FilterChip(
            label: 'Live', count: _countStatus('live'), color: _Palette.green,
            selected: _statusFilter == 'live',
            onSelected: () => setState(() => _statusFilter = 'live'),
          ),
          _FilterChip(
            label: 'Scheduled', count: _countStatus('scheduled'), color: _Palette.sky,
            selected: _statusFilter == 'scheduled',
            onSelected: () => setState(() => _statusFilter = 'scheduled'),
          ),
          _FilterChip(
            label: 'Ended', count: _countStatus('ended'), color: _Palette.violet,
            selected: _statusFilter == 'ended',
            onSelected: () => setState(() => _statusFilter = 'ended'),
          ),
          _FilterChip(
            label: 'Disabled', count: _countStatus('inactive'), color: _Palette.rose,
            selected: _statusFilter == 'inactive',
            onSelected: () => setState(() => _statusFilter = 'inactive'),
          ),
        ],
      );

  Widget _typeFilters() => Wrap(
        spacing: 8,
        runSpacing: 8,
        children: [
          for (final entry in const [
            ('all', 'All styles'),
            ('info', 'Info'),
            ('success', 'Success'),
            ('warning', 'Warning'),
            ('danger', 'Important'),
          ])
            _TypeChip(
              label: entry.$2,
              selected: _typeFilter == entry.$1,
              color: entry.$1 == 'all' ? _Palette.indigo : _styleFor(entry.$1).color,
              onSelected: () => setState(() => _typeFilter = entry.$1),
            ),
        ],
      );
}

class _AnnouncementForm extends StatefulWidget {
  const _AnnouncementForm({
    required this.api,
    required this.token,
    required this.styleFor,
    this.existing,
  });

  final AuthApi api;
  final String token;
  final Map<String, dynamic>? existing;
  final _AnnouncementStyle Function(String) styleFor;

  @override
  State<_AnnouncementForm> createState() => _AnnouncementFormState();
}

class _AnnouncementFormState extends State<_AnnouncementForm> {
  final GlobalKey<FormState> _formKey = GlobalKey<FormState>();
  late final TextEditingController _title = TextEditingController(
    text: widget.existing?['title']?.toString() ?? '',
  );
  late final TextEditingController _body = TextEditingController(
    text: widget.existing?['body']?.toString() ?? '',
  );
  late final TextEditingController _actionUrl = TextEditingController(
    text: widget.existing?['action_url']?.toString() ?? '',
  );
  late final TextEditingController _actionLabel = TextEditingController(
    text: widget.existing?['action_label']?.toString() ?? '',
  );
  late String _type = _asType(widget.existing?['type']);
  late int _displayType = _asDisplayType(widget.existing?['display_type']);
  late bool _dismissible = _asBool(widget.existing?['is_dismissible'], true);
  late bool _active = _asBool(widget.existing?['is_active'], true);
  late DateTime? _startsAt = _asDate(widget.existing?['starts_at']);
  late DateTime? _endsAt = _asDate(widget.existing?['ends_at']);
  bool _saving = false;
  String? _saveError;

  static int _asInt(Object? value) => value is num
      ? value.toInt()
      : int.tryParse(value?.toString() ?? '') ?? 0;

  static String _asType(Object? value) {
    final type = value?.toString().toLowerCase();
    return const {'info', 'success', 'warning', 'danger'}.contains(type)
        ? type!
        : 'info';
  }

  static int _asDisplayType(Object? value) {
    final displayType = _asInt(value);
    return const {0, 1, 2}.contains(displayType) ? displayType : 0;
  }

  static bool _asBool(Object? value, bool fallback) => switch (value) {
    true || 1 || '1' => true,
    false || 0 || '0' => false,
    _ => fallback,
  };

  static DateTime? _asDate(Object? value) =>
      DateTime.tryParse(value?.toString() ?? '')?.toLocal();

  @override
  void dispose() {
    _title.dispose();
    _body.dispose();
    _actionUrl.dispose();
    _actionLabel.dispose();
    super.dispose();
  }

  Future<void> _pickDate({required bool start}) async {
    final current = start ? _startsAt : _endsAt;
    final picked = await showDatePicker(
      context: context,
      initialDate: current ?? DateTime.now(),
      firstDate: DateTime(2020),
      lastDate: DateTime(2100),
      builder: (context, child) => Theme(
        data: _adminTheme(context),
        child: child!,
      ),
    );
    if (picked == null || !mounted) return;
    setState(() {
      if (start) {
        _startsAt = DateTime(picked.year, picked.month, picked.day);
      } else {
        _endsAt = DateTime(picked.year, picked.month, picked.day, 23, 59, 59);
      }
    });
  }

  String _dateLabel(DateTime? value) {
    if (value == null) return 'No date limit';
    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    return '${months[value.month - 1]} ${value.day}, ${value.year}';
  }

  String? _validateActionUrl(String? value) {
    final input = value?.trim() ?? '';
    if (input.isEmpty) return null;
    final uri = Uri.tryParse(input);
    if (uri == null || !uri.hasAuthority || (uri.scheme != 'http' && uri.scheme != 'https')) {
      return 'Enter a valid http or https URL.';
    }
    return null;
  }

  Future<void> _save() async {
    final form = _formKey.currentState;
    if (form == null || !form.validate()) return;
    if (_startsAt != null && _endsAt != null && _endsAt!.isBefore(_startsAt!)) {
      setState(() => _saveError = 'End date must be on or after the start date.');
      return;
    }
    if (_displayType == 0 && !_dismissible && _actionUrl.text.trim().isEmpty) {
      setState(() => _saveError = 'A non-dismissible pop-up needs an action link.');
      return;
    }
    if (_actionUrl.text.trim().isEmpty != _actionLabel.text.trim().isEmpty) {
      setState(() => _saveError = 'Enter both an action link and its button label, or leave both blank.');
      return;
    }
    setState(() {
      _saving = true;
      _saveError = null;
    });
    final payload = <String, dynamic>{
      'title': _title.text.trim(),
      'body': _body.text.trim(),
      'action_url': _actionUrl.text.trim().isEmpty ? null : _actionUrl.text.trim(),
      'action_label': _actionLabel.text.trim().isEmpty ? null : _actionLabel.text.trim(),
      'type': _type,
      'display_type': _displayType,
      'is_dismissible': _displayType == 2 ? false : _dismissible,
      'is_active': _active,
      'starts_at': _startsAt?.toIso8601String(),
      'ends_at': _endsAt?.toIso8601String(),
    };
    try {
      final id = widget.existing?['id'];
      if (id == null) {
        await widget.api.post('admin/announcements', widget.token, payload);
      } else {
        await widget.api.patch('admin/announcements/$id', widget.token, payload);
      }
      if (mounted) Navigator.of(context).pop(true);
    } on AuthException catch (error) {
      if (mounted) setState(() => _saveError = error.message);
    } catch (_) {
      if (mounted) setState(() => _saveError = 'The announcement could not be saved.');
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) => PopScope(
        canPop: !_saving,
        child: Padding(
          padding: EdgeInsets.only(bottom: MediaQuery.viewInsetsOf(context).bottom),
          child: SafeArea(
            top: false,
            child: Column(
              children: [
                Padding(
                  padding: const EdgeInsets.fromLTRB(22, 18, 14, 14),
                  child: Row(
                    children: [
                      _BrandMark(icon: Icons.campaign_rounded, gradient: widget.styleFor(_type).gradient, size: 46),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(widget.existing == null ? 'New announcement' : 'Edit announcement', style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w900)),
                            const Text('Write, schedule and preview a customer message.'),
                          ],
                        ),
                      ),
                      IconButton(tooltip: 'Close', onPressed: _saving ? null : () => Navigator.pop(context), icon: const Icon(Icons.close_rounded)),
                    ],
                  ),
                ),
                const Divider(height: 1),
                Expanded(
                  child: Form(
                    key: _formKey,
                    child: ListView(
                      padding: const EdgeInsets.fromLTRB(22, 18, 22, 22),
                      children: [
                        _SectionHeading(title: 'Message', subtitle: 'Keep it direct, useful and easy to scan.'),
                        const SizedBox(height: 12),
                        TextFormField(
                          controller: _title,
                          maxLength: 255,
                          textCapitalization: TextCapitalization.sentences,
                          decoration: const InputDecoration(labelText: 'Title', prefixIcon: Icon(Icons.title_rounded)),
                          validator: (value) => value == null || value.trim().isEmpty ? 'Title is required.' : null,
                          onChanged: (_) => setState(() {}),
                        ),
                        const SizedBox(height: 10),
                        TextFormField(
                          controller: _body,
                          minLines: 3,
                          maxLines: 6,
                          maxLength: 20000,
                          textCapitalization: TextCapitalization.sentences,
                          decoration: const InputDecoration(labelText: 'Message', alignLabelWithHint: true, prefixIcon: Icon(Icons.notes_rounded)),
                          validator: (value) => value == null || value.trim().isEmpty ? 'Message is required.' : null,
                          onChanged: (_) => setState(() {}),
                        ),
                        const SizedBox(height: 18),
                        _SectionHeading(title: 'Appearance and placement', subtitle: 'Choose the tone and where customers will see it.'),
                        const SizedBox(height: 12),
                        _ResponsiveFields(children: [
                          _LabeledField(
                            label: 'Message style',
                            child: DropdownButtonFormField<String>(
                              initialValue: _type,
                              isExpanded: true,
                              decoration: const InputDecoration(prefixIcon: Icon(Icons.palette_outlined)),
                              items: const [
                                DropdownMenuItem(value: 'info', child: Text('Information')),
                                DropdownMenuItem(value: 'success', child: Text('Success')),
                                DropdownMenuItem(value: 'warning', child: Text('Warning')),
                                DropdownMenuItem(value: 'danger', child: Text('Important / danger')),
                              ],
                              onChanged: (value) => setState(() => _type = value ?? 'info'),
                            ),
                          ),
                          _LabeledField(
                            label: 'Display location',
                            child: DropdownButtonFormField<int>(
                              initialValue: _displayType,
                              isExpanded: true,
                              decoration: const InputDecoration(prefixIcon: Icon(Icons.view_agenda_outlined)),
                              items: const [
                                DropdownMenuItem(value: 0, child: Text('Pop-up dialog')),
                                DropdownMenuItem(value: 1, child: Text('Inline below earnings')),
                                DropdownMenuItem(value: 2, child: Text('Pinned below earnings')),
                              ],
                              onChanged: (value) => setState(() => _displayType = value ?? 0),
                            ),
                          ),
                        ]),
                        const SizedBox(height: 10),
                        SwitchListTile.adaptive(
                          contentPadding: EdgeInsets.zero,
                          title: const Text('Dismissible', style: TextStyle(fontWeight: FontWeight.w800)),
                          subtitle: Text(_displayType == 2 ? 'Pinned notices stay visible and cannot be dismissed.' : 'Allow the customer to close this announcement.'),
                          value: _displayType == 2 ? false : _dismissible,
                          onChanged: _displayType == 2 ? null : (value) => setState(() => _dismissible = value),
                        ),
                        const SizedBox(height: 18),
                        _SectionHeading(title: 'Optional action', subtitle: 'Add a button that takes the customer to a relevant page.'),
                        const SizedBox(height: 12),
                        _ResponsiveFields(children: [
                          _LabeledField(
                            label: 'Action URL',
                            child: TextFormField(
                              controller: _actionUrl,
                              keyboardType: TextInputType.url,
                              decoration: const InputDecoration(prefixIcon: Icon(Icons.link_rounded), hintText: 'https://example.com'),
                              validator: _validateActionUrl,
                              onChanged: (_) => setState(() {}),
                            ),
                          ),
                          _LabeledField(
                            label: 'Button label',
                            child: TextFormField(
                              controller: _actionLabel,
                              maxLength: 60,
                              decoration: const InputDecoration(prefixIcon: Icon(Icons.smart_button_outlined), hintText: 'Learn more'),
                              validator: (value) => (value?.length ?? 0) > 60 ? 'Use 60 characters or fewer.' : null,
                              onChanged: (_) => setState(() {}),
                            ),
                          ),
                        ]),
                        const SizedBox(height: 18),
                        _SectionHeading(title: 'Schedule and status', subtitle: 'Leave dates blank to show the announcement without a date limit.'),
                        const SizedBox(height: 8),
                        _datePickerRow(title: 'Starts', value: _startsAt, onPick: () => _pickDate(start: true), onClear: () => setState(() => _startsAt = null)),
                        _datePickerRow(title: 'Ends', value: _endsAt, onPick: () => _pickDate(start: false), onClear: () => setState(() => _endsAt = null)),
                        SwitchListTile.adaptive(
                          contentPadding: EdgeInsets.zero,
                          title: const Text('Active', style: TextStyle(fontWeight: FontWeight.w800)),
                          subtitle: const Text('Turn off to hide this announcement without deleting it.'),
                          value: _active,
                          onChanged: (value) => setState(() => _active = value),
                        ),
                        const SizedBox(height: 18),
                        _SectionHeading(title: 'Preview', subtitle: 'A quick preview of the message style and action.'),
                        const SizedBox(height: 10),
                        _AnnouncementPreview(
                          title: _title.text.trim().isEmpty ? 'Announcement title' : _title.text.trim(),
                          body: _body.text.trim().isEmpty ? 'Your announcement message will appear here.' : _body.text.trim(),
                          actionLabel: _actionLabel.text.trim(),
                          style: widget.styleFor(_type),
                        ),
                        if (_saveError != null) ...[
                          const SizedBox(height: 14),
                          _ErrorBanner(message: _saveError!),
                        ],
                      ],
                    ),
                  ),
                ),
                const Divider(height: 1),
                Padding(
                  padding: const EdgeInsets.fromLTRB(18, 12, 18, 16),
                  child: Row(
                    children: [
                      Expanded(child: OutlinedButton(onPressed: _saving ? null : () => Navigator.pop(context), child: const Text('Cancel'))),
                      const SizedBox(width: 10),
                      Expanded(
                        child: _GradientButton(
                          label: _saving ? 'Saving…' : 'Save announcement',
                          icon: _saving ? null : Icons.save_outlined,
                          busy: _saving,
                          onPressed: _saving ? null : _save,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ),
      );

  Widget _datePickerRow({
    required String title,
    required DateTime? value,
    required VoidCallback onPick,
    required VoidCallback onClear,
  }) => ListTile(
        contentPadding: EdgeInsets.zero,
        leading: Icon(title == 'Starts' ? Icons.play_arrow_rounded : Icons.event_outlined, color: _Palette.indigo),
        title: Text(title, style: const TextStyle(fontWeight: FontWeight.w700)),
        subtitle: Text(_dateLabel(value)),
        trailing: Wrap(
          spacing: 2,
          children: [
            IconButton(tooltip: 'Choose date', onPressed: _saving ? null : onPick, icon: const Icon(Icons.calendar_month_outlined)),
            if (value != null) IconButton(tooltip: 'Clear date', onPressed: _saving ? null : onClear, icon: const Icon(Icons.clear_rounded)),
          ],
        ),
      );
}

class _AnnouncementCard extends StatelessWidget {
  const _AnnouncementCard({
    required this.item,
    required this.status,
    required this.style,
    required this.displayLabel,
    required this.dateLabel,
    required this.onEdit,
    required this.onDelete,
  });

  final Map<String, dynamic> item;
  final String status;
  final _AnnouncementStyle style;
  final String displayLabel;
  final String Function(Object?) dateLabel;
  final VoidCallback onEdit;
  final VoidCallback onDelete;

  @override
  Widget build(BuildContext context) {
    final color = switch (status) {
      'live' => _Palette.green,
      'scheduled' => _Palette.sky,
      'ended' => _Palette.violet,
      _ => _Palette.rose,
    };
    final active = item['is_active'] != false &&
        item['is_active'] != 0 &&
        item['is_active'] != '0' &&
        item['is_active'] != 'false';
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
                  _BrandMark(icon: style.icon, gradient: style.gradient, size: 46),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          item['title']?.toString() ?? 'Untitled announcement',
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w900),
                        ),
                        const SizedBox(height: 4),
                        Wrap(
                          spacing: 7,
                          runSpacing: 6,
                          children: [
                            _MetaChip(icon: style.icon, color: style.color, label: style.label),
                            _MetaChip(icon: _placementIcon(_int(item['display_type'])), color: _Palette.indigo, label: displayLabel),
                            _StatusPill(status: status, color: color),
                          ],
                        ),
                      ],
                    ),
                  ),
                  PopupMenuButton<String>(
                    tooltip: 'Announcement actions',
                    onSelected: (value) => value == 'edit' ? onEdit() : onDelete(),
                    itemBuilder: (_) => const [
                      PopupMenuItem(value: 'edit', child: Text('Edit')),
                      PopupMenuItem(value: 'delete', child: Text('Delete')),
                    ],
                  ),
                ],
              ),
              const SizedBox(height: 13),
              Text(
                item['body']?.toString() ?? '',
                maxLines: 3,
                overflow: TextOverflow.ellipsis,
                style: Theme.of(context).textTheme.bodyMedium?.copyWith(height: 1.4),
              ),
              const SizedBox(height: 13),
              Wrap(
                spacing: 14,
                runSpacing: 8,
                children: [
                  _MetaChip(icon: Icons.play_arrow_rounded, color: _Palette.teal, label: 'Starts: ${dateLabel(item['starts_at'])}'),
                  _MetaChip(icon: Icons.event_outlined, color: _Palette.orange, label: 'Ends: ${dateLabel(item['ends_at'])}'),
                  _MetaChip(icon: Icons.touch_app_outlined, color: _Palette.violet, label: item['is_dismissible'] == false ? 'Not dismissible' : 'Dismissible'),
                  if ((item['action_label'] ?? '').toString().isNotEmpty)
                    _MetaChip(icon: Icons.link_rounded, color: _Palette.sky, label: item['action_label'].toString()),
                ],
              ),
              if (!active) ...[
                const SizedBox(height: 12),
                const _ErrorBanner(message: 'Inactive — hidden from customers.'),
              ],
              const SizedBox(height: 10),
              const Divider(height: 1),
              const SizedBox(height: 8),
              Row(
                children: [
                  Expanded(
                    child: OutlinedButton.icon(
                      onPressed: onEdit,
                      icon: const Icon(Icons.edit_outlined, size: 17),
                      label: const Text('Edit announcement'),
                    ),
                  ),
                  const SizedBox(width: 8),
                  IconButton(
                    tooltip: 'Delete announcement',
                    onPressed: onDelete,
                    color: _Palette.rose,
                    icon: const Icon(Icons.delete_outline_rounded),
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

IconData _placementIcon(int displayType) => switch (displayType) {
  1 => Icons.view_agenda_outlined,
  2 => Icons.push_pin_outlined,
  _ => Icons.open_in_new_rounded,
};

int _int(Object? value) => value is num ? value.toInt() : int.tryParse(value?.toString() ?? '') ?? 0;

class _AnnouncementPreview extends StatelessWidget {
  const _AnnouncementPreview({required this.title, required this.body, required this.actionLabel, required this.style});
  final String title;
  final String body;
  final String actionLabel;
  final _AnnouncementStyle style;

  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.all(15),
        decoration: BoxDecoration(
          color: style.color.withValues(alpha: .07),
          borderRadius: BorderRadius.circular(16),
          border: Border.all(color: style.color.withValues(alpha: .25)),
        ),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Icon(style.icon, color: style.color),
            const SizedBox(width: 11),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(title, style: TextStyle(color: style.color, fontWeight: FontWeight.w900)),
                  const SizedBox(height: 4),
                  Text(body, maxLines: 4, overflow: TextOverflow.ellipsis, style: const TextStyle(height: 1.35)),
                  if (actionLabel.isNotEmpty) ...[
                    const SizedBox(height: 8),
                    Text(actionLabel, style: TextStyle(color: style.color, fontWeight: FontWeight.w800)),
                  ],
                ],
              ),
            ),
          ],
        ),
      );
}

class _AnnouncementStyle {
  const _AnnouncementStyle({required this.label, required this.icon, required this.color, required this.gradient});
  final String label;
  final IconData icon;
  final Color color;
  final LinearGradient gradient;
}

class _SignInField extends StatelessWidget {
  const _SignInField({required this.controller, required this.label, required this.icon, this.keyboard, this.obscure = false, this.onSubmitted, this.suffix});
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
          border: OutlineInputBorder(borderRadius: BorderRadius.circular(14), borderSide: BorderSide.none),
          enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(14), borderSide: BorderSide.none),
          focusedBorder: const OutlineInputBorder(borderRadius: BorderRadius.all(Radius.circular(14)), borderSide: BorderSide(color: _Palette.indigo, width: 1.6)),
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
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Icon(Icons.error_outline_rounded, size: 18, color: _Palette.rose),
            const SizedBox(width: 10),
            Expanded(child: Text(message, style: const TextStyle(color: _Palette.rose, fontWeight: FontWeight.w600, height: 1.35))),
          ],
        ),
      );
}

class _GradientButton extends StatelessWidget {
  const _GradientButton({required this.label, required this.onPressed, this.icon, this.busy = false});
  final String label;
  final VoidCallback? onPressed;
  final IconData? icon;
  final bool busy;

  @override
  Widget build(BuildContext context) {
    final enabled = onPressed != null;
    final Widget? leading = busy
        ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2.2, color: Colors.white))
        : icon == null ? null : Icon(icon, color: Colors.white, size: 20);
    return Opacity(
      opacity: enabled ? 1 : .55,
      child: DecoratedBox(
        decoration: BoxDecoration(
          gradient: _Palette.hero,
          borderRadius: BorderRadius.circular(16),
          boxShadow: enabled ? [BoxShadow(color: _Palette.indigo.withValues(alpha: .35), blurRadius: 18, offset: const Offset(0, 9))] : null,
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
          boxShadow: [BoxShadow(color: _Palette.violet.withValues(alpha: .45), blurRadius: 20, offset: const Offset(0, 10))],
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
          final width = (constraints.maxWidth - (count - 1) * 12) / count;
          return Wrap(
            spacing: 12,
            runSpacing: 12,
            children: [for (final child in children) SizedBox(width: width, child: child)],
          );
        },
      );
}

class _StatTile extends StatelessWidget {
  const _StatTile({required this.label, required this.value, required this.icon, required this.gradient});
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
          boxShadow: [BoxShadow(color: gradient.colors.last.withValues(alpha: .3), blurRadius: 16, offset: const Offset(0, 8))],
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(children: [Icon(icon, color: Colors.white, size: 18), const Spacer(), Container(width: 6, height: 6, decoration: BoxDecoration(color: Colors.white.withValues(alpha: .7), shape: BoxShape.circle))]),
            const SizedBox(height: 12),
            FittedBox(fit: BoxFit.scaleDown, alignment: Alignment.centerLeft, child: Text(value, maxLines: 1, style: const TextStyle(color: Colors.white, fontSize: 24, fontWeight: FontWeight.w800, height: 1.1))),
            const SizedBox(height: 2),
            Text(label, maxLines: 1, overflow: TextOverflow.ellipsis, style: TextStyle(color: Colors.white.withValues(alpha: .9), fontSize: 12, fontWeight: FontWeight.w600)),
          ],
        ),
      );
}

class _SearchField extends StatelessWidget {
  const _SearchField({required this.controller, required this.hint, required this.onChanged});
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
              : IconButton(tooltip: 'Clear search', onPressed: () { controller.clear(); onChanged(''); }, icon: const Icon(Icons.close_rounded)),
          filled: true,
          fillColor: Theme.of(context).colorScheme.onSurface.withValues(alpha: .045),
          contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
          border: OutlineInputBorder(borderRadius: BorderRadius.circular(16), borderSide: BorderSide.none),
          enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(16), borderSide: BorderSide.none),
          focusedBorder: const OutlineInputBorder(borderRadius: BorderRadius.all(Radius.circular(16)), borderSide: BorderSide(color: _Palette.indigo, width: 1.6)),
        ),
      );
}

class _FilterChip extends StatelessWidget {
  const _FilterChip({required this.label, required this.count, required this.color, required this.selected, required this.onSelected});
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
                  decoration: BoxDecoration(color: selected ? Colors.white.withValues(alpha: .25) : color.withValues(alpha: .16), borderRadius: BorderRadius.circular(999)),
                  child: Text('$count', style: TextStyle(color: selected ? Colors.white : color, fontSize: 11, fontWeight: FontWeight.w800)),
                ),
              ],
            ),
          ),
        ),
      );
}

class _TypeChip extends StatelessWidget {
  const _TypeChip({required this.label, required this.color, required this.selected, required this.onSelected});
  final String label;
  final Color color;
  final bool selected;
  final VoidCallback onSelected;

  @override
  Widget build(BuildContext context) => ChoiceChip(
        label: Text(label),
        selected: selected,
        selectedColor: color.withValues(alpha: .16),
        labelStyle: TextStyle(color: selected ? color : Theme.of(context).colorScheme.onSurfaceVariant, fontWeight: FontWeight.w700),
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
        decoration: BoxDecoration(color: color.withValues(alpha: .1), borderRadius: BorderRadius.circular(999)),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(icon, size: 14, color: color),
            const SizedBox(width: 5),
            Flexible(child: Text(label, maxLines: 1, overflow: TextOverflow.ellipsis, style: TextStyle(color: color, fontSize: 11, fontWeight: FontWeight.w700))),
          ],
        ),
      );
}

class _StatusPill extends StatelessWidget {
  const _StatusPill({required this.status, required this.color});
  final String status;
  final Color color;

  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 5),
        decoration: BoxDecoration(color: color.withValues(alpha: .14), borderRadius: BorderRadius.circular(999), border: Border.all(color: color.withValues(alpha: .3))),
        child: Text(status.toUpperCase(), style: TextStyle(color: color, fontSize: 10, fontWeight: FontWeight.w800, letterSpacing: .5)),
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
        decoration: BoxDecoration(gradient: gradient, borderRadius: BorderRadius.circular(size * .32)),
        alignment: Alignment.center,
        child: Icon(icon, color: Colors.white, size: size * .5),
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

class _LabeledField extends StatelessWidget {
  const _LabeledField({required this.label, required this.child});
  final String label;
  final Widget child;

  @override
  Widget build(BuildContext context) => Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(label, style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 12)),
          const SizedBox(height: 7),
          child,
        ],
      );
}

class _ResponsiveFields extends StatelessWidget {
  const _ResponsiveFields({required this.children});
  final List<Widget> children;

  @override
  Widget build(BuildContext context) => LayoutBuilder(
        builder: (context, constraints) {
          final columns = constraints.maxWidth >= 700 ? 2 : 1;
          final width = (constraints.maxWidth - 14 * (columns - 1)) / columns;
          return Wrap(spacing: 14, runSpacing: 14, children: [for (final child in children) SizedBox(width: width, child: child)]);
        },
      );
}

class _DateField extends StatelessWidget {
  const _DateField({required this.title, required this.value, required this.onPick, required this.onClear});
  final String title;
  final String value;
  final VoidCallback onPick;
  final VoidCallback onClear;

  @override
  Widget build(BuildContext context) => ListTile(
        contentPadding: EdgeInsets.zero,
        leading: Icon(title == 'Starts' ? Icons.play_arrow_rounded : Icons.event_outlined, color: _Palette.indigo),
        title: Text(title, style: const TextStyle(fontWeight: FontWeight.w700)),
        subtitle: Text(value),
        trailing: Wrap(
          spacing: 2,
          children: [
            IconButton(tooltip: 'Choose date', onPressed: onPick, icon: const Icon(Icons.calendar_month_outlined)),
            if (value != 'No date limit') IconButton(tooltip: 'Clear date', onPressed: onClear, icon: const Icon(Icons.clear_rounded)),
          ],
        ),
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
          decoration: BoxDecoration(color: _Palette.indigo.withValues(alpha: .06), borderRadius: BorderRadius.circular(20)),
        ),
      );
}

class _EmptyState extends StatelessWidget {
  const _EmptyState({required this.icon, required this.gradient, required this.title, required this.message, required this.actionLabel, required this.onAction});
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
              _GradientButton(label: actionLabel, icon: Icons.refresh_rounded, onPressed: onAction),
            ],
          ),
        ),
      );
}

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

ThemeData _adminTheme(BuildContext context) {
  final base = Theme.of(context);
  return base.copyWith(
    colorScheme: ColorScheme.fromSeed(seedColor: _Palette.indigo, brightness: base.brightness),
  );
}
