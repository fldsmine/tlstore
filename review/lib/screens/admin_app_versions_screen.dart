import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';

import '../services/auth_api.dart';

class AdminAppVersionsScreen extends StatefulWidget {
  const AdminAppVersionsScreen({super.key, required this.api});

  final AuthApi api;

  @override
  State<AdminAppVersionsScreen> createState() => _AdminAppVersionsScreenState();
}

class _AdminAppVersionsScreenState extends State<AdminAppVersionsScreen> {
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
  String _platformFilter = 'all';
  List<Map<String, dynamic>> _versions = const [];
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

  int _int(Object? value, [int fallback = 0]) => value is num
      ? value.toInt()
      : int.tryParse(value?.toString() ?? '') ?? fallback;

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
        'admin/app-versions?per_page=100&page=$page',
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
      final versions = await _loadAll(token);
      if (!mounted || generation != _requestGeneration) return;
      setState(() => _versions = versions);
    } on AuthException catch (error) {
      if (mounted && generation == _requestGeneration) {
        setState(() => _error = error.message);
      }
    } catch (_) {
      if (mounted && generation == _requestGeneration) {
        setState(() => _error = 'App releases could not be loaded. Please retry.');
      }
    } finally {
      if (mounted && generation == _requestGeneration) {
        setState(() => _loading = false);
      }
    }
  }

  String _platform(Map<String, dynamic> version) =>
      _string(version['platform'], 'android').toLowerCase();

  bool _isTrue(Object? value) => value == true || value == 1 || value == '1';

  bool _active(Map<String, dynamic> version) => _isTrue(version['is_active']);

  DateTime? _publishedAt(Map<String, dynamic> version) =>
      DateTime.tryParse(_string(version['published_at']))?.toLocal();

  String _releaseStatus(Map<String, dynamic> version) {
    if (!_active(version)) return 'inactive';
    final published = _publishedAt(version);
    if (published == null) return 'draft';
    if (published.isAfter(DateTime.now())) return 'scheduled';
    return 'live';
  }

  List<Map<String, dynamic>> get _visibleVersions {
    final query = _search.text.trim().toLowerCase();
    final values = _versions.where((version) {
      final matchesStatus =
          _statusFilter == 'all' || _releaseStatus(version) == _statusFilter;
      final matchesPlatform =
          _platformFilter == 'all' || _platform(version) == _platformFilter;
      final haystack = [
        version['platform'],
        version['version_name'],
        version['build_number'],
        version['minimum_supported_build'],
        version['title'],
        version['release_notes'],
      ].map((value) => _string(value).toLowerCase()).join(' ');
      return matchesStatus && matchesPlatform &&
          (query.isEmpty || haystack.contains(query));
    }).toList();
    values.sort((a, b) {
      final aDate = _publishedAt(a) ??
          DateTime.tryParse(_string(a['created_at'])) ??
          DateTime(0);
      final bDate = _publishedAt(b) ??
          DateTime.tryParse(_string(b['created_at'])) ??
          DateTime(0);
      final dateOrder = bDate.compareTo(aDate);
      return dateOrder != 0
          ? dateOrder
          : _int(b['build_number']).compareTo(_int(a['build_number']));
    });
    return values;
  }

  int _countStatus(String status) =>
      _versions.where((version) => _releaseStatus(version) == status).length;

  int _countPlatform(String platform) =>
      _versions.where((version) => _platform(version) == platform).length;

  String _dateLabel(Object? value) {
    final date = DateTime.tryParse(_string(value))?.toLocal();
    if (date == null) return 'Not published';
    const months = [
      'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
      'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec',
    ];
    return '${months[date.month - 1]} ${date.day}, ${date.year}';
  }

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
                child: _VersionForm(
                  api: widget.api,
                  token: token,
                  existing: existing,
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
                  width: 880,
                  height: MediaQuery.sizeOf(dialogContext).height * .82,
                  child: _VersionForm(
                    api: widget.api,
                    token: token,
                    existing: existing,
                  ),
                ),
              ),
            ),
          );
    if (saved == true && mounted) await _load();
  }

  Future<void> _delete(Map<String, dynamic> version) async {
    final confirm = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => Theme(
        data: _adminTheme(context),
        child: AlertDialog(
          icon: const Icon(Icons.delete_outline_rounded, color: _Palette.rose),
          title: const Text('Delete this release?'),
          content: Text(
            '${_string(version['platform']).toUpperCase()} ${_string(version['version_name'])} '
            '(build ${_string(version['build_number'])}) and its uploaded APK will be removed.',
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(dialogContext, false),
              child: const Text('Keep release'),
            ),
            FilledButton.tonalIcon(
              onPressed: () => Navigator.pop(dialogContext, true),
              icon: const Icon(Icons.delete_outline_rounded),
              label: const Text('Delete release'),
            ),
          ],
        ),
      ),
    );
    final token = _token;
    if (confirm != true || !mounted || token == null) return;
    final messenger = ScaffoldMessenger.of(context);
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      await widget.api.delete('admin/app-versions/${version['id']}', token);
      if (!mounted) return;
      messenger.showSnackBar(
        const SnackBar(content: Text('App release deleted.')),
      );
      await _load();
    } on AuthException catch (error) {
      if (mounted) setState(() => _error = error.message);
    } catch (_) {
      if (mounted) setState(() => _error = 'The release could not be deleted.');
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  void _logout() {
    ++_requestGeneration;
    _password.clear();
    setState(() {
      _token = null;
      _versions = const [];
      _error = null;
      _search.clear();
      _statusFilter = 'all';
      _platformFilter = 'all';
    });
  }

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
                          Icons.system_update_alt_rounded,
                          size: 38,
                          color: Colors.white,
                        ),
                      ),
                      const SizedBox(height: 18),
                      const Text(
                        'App releases console',
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
                        'Manage Android and iOS versions, store links and update requirements.',
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

  Widget _dashboard(BuildContext context) {
    final visible = _visibleVersions;
    final liveAndroid = _versions
        .where((version) => _platform(version) == 'android' && _releaseStatus(version) == 'live')
        .length;
    final liveIos = _versions
        .where((version) => _platform(version) == 'ios' && _releaseStatus(version) == 'live')
        .length;
    final forced = _versions.where((version) => _isTrue(version['force_update'])).length;
    return Scaffold(
      backgroundColor: Theme.of(context).colorScheme.surface,
      floatingActionButton: _GradientFab(
        icon: Icons.add_rounded,
        label: 'Add release',
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
                'App releases',
                style: TextStyle(
                  color: Colors.white,
                  fontWeight: FontWeight.w800,
                  letterSpacing: -.3,
                ),
              ),
              actions: [
                _AppBarAction(
                  icon: Icons.refresh_rounded,
                  tooltip: 'Refresh releases',
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
                          label: 'Total releases',
                          value: '${_versions.length}',
                          icon: Icons.system_update_alt_rounded,
                          gradient: _Palette.ocean,
                        ),
                        _StatTile(
                          label: 'Live Android',
                          value: '$liveAndroid',
                          icon: Icons.android_rounded,
                          gradient: _Palette.mint,
                        ),
                        _StatTile(
                          label: 'Live iOS',
                          value: '$liveIos',
                          icon: Icons.apple,
                          gradient: _Palette.berry,
                        ),
                        _StatTile(
                          label: 'Force update',
                          value: '$forced',
                          icon: Icons.gpp_maybe_outlined,
                          gradient: _Palette.sunset,
                        ),
                      ],
                    ),
                    const SizedBox(height: 14),
                    _SearchField(
                      controller: _search,
                      hint: 'Search version, build, title or release notes',
                      onChanged: (_) => setState(() {}),
                    ),
                    const SizedBox(height: 10),
                    _statusFilters(),
                    const SizedBox(height: 8),
                    _platformFilters(),
                    if (_error != null && _versions.isNotEmpty) ...[
                      const SizedBox(height: 10),
                      _ErrorBanner(message: _error!),
                    ],
                  ],
                ),
              ),
            ),
            if (_loading && _versions.isEmpty)
              const SliverFillRemaining(
                hasScrollBody: false,
                child: _SkeletonList(),
              )
            else if (_error != null && _versions.isEmpty)
              SliverFillRemaining(
                hasScrollBody: false,
                child: _EmptyState(
                  icon: Icons.cloud_off_rounded,
                  gradient: _Palette.sunset,
                  title: 'Could not load releases',
                  message: _error!,
                  actionLabel: 'Try again',
                  onAction: _load,
                ),
              )
            else if (visible.isEmpty)
              SliverFillRemaining(
                hasScrollBody: false,
                child: _EmptyState(
                  icon: _versions.isEmpty
                      ? Icons.system_update_alt_rounded
                      : Icons.search_off_rounded,
                  gradient: _Palette.berry,
                  title: _versions.isEmpty
                      ? 'No app releases yet'
                      : 'No releases match those filters',
                  message: _versions.isEmpty
                      ? 'Add a version to publish a new Android or iOS release.'
                      : 'Try another search or platform/status filter.',
                  actionLabel: _versions.isEmpty ? 'Add first release' : 'Clear filters',
                  onAction: _versions.isEmpty
                      ? () => _openEditor()
                      : () => setState(() {
                          _search.clear();
                          _statusFilter = 'all';
                          _platformFilter = 'all';
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
                    final version = visible[index];
                    return _VersionCard(
                      version: version,
                      status: _releaseStatus(version),
                      dateLabel: _dateLabel,
                      onEdit: () => _openEditor(version),
                      onDelete: () => _delete(version),
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
            label: 'All', count: _versions.length, color: _Palette.indigo,
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
            label: 'Draft', count: _countStatus('draft'), color: _Palette.amber,
            selected: _statusFilter == 'draft',
            onSelected: () => setState(() => _statusFilter = 'draft'),
          ),
          _FilterChip(
            label: 'Inactive', count: _countStatus('inactive'), color: _Palette.rose,
            selected: _statusFilter == 'inactive',
            onSelected: () => setState(() => _statusFilter = 'inactive'),
          ),
        ],
      );

  Widget _platformFilters() => Wrap(
        spacing: 8,
        runSpacing: 8,
        children: [
          _TypeChip(
            label: 'All platforms', color: _Palette.indigo,
            selected: _platformFilter == 'all',
            onSelected: () => setState(() => _platformFilter = 'all'),
          ),
          _TypeChip(
            label: 'Android · ${_countPlatform('android')}', color: _Palette.green,
            selected: _platformFilter == 'android',
            onSelected: () => setState(() => _platformFilter = 'android'),
          ),
          _TypeChip(
            label: 'iOS · ${_countPlatform('ios')}', color: _Palette.muted,
            selected: _platformFilter == 'ios',
            onSelected: () => setState(() => _platformFilter = 'ios'),
          ),
        ],
      );
}

class _VersionForm extends StatefulWidget {
  const _VersionForm({required this.api, required this.token, this.existing});

  final AuthApi api;
  final String token;
  final Map<String, dynamic>? existing;

  @override
  State<_VersionForm> createState() => _VersionFormState();
}

class _VersionFormState extends State<_VersionForm> {
  final GlobalKey<FormState> _formKey = GlobalKey<FormState>();
  late String _platform;
  late final Map<String, TextEditingController> _fields;
  late bool _forceUpdate;
  late bool _active;
  late bool _publishNow;
  PlatformFile? _apk;
  bool _saving = false;
  bool _removeApk = false;
  String? _error;

  Map<String, dynamic> get _version => widget.existing ?? const {};

  static const List<String> _fieldKeys = [
    'version_name',
    'build_number',
    'minimum_supported_build',
    'title',
    'release_notes',
    'direct_download_url',
    'play_store_url',
    'app_store_url',
  ];

  @override
  void initState() {
    super.initState();
    _platform = _string(_version['platform'], 'android').toLowerCase();
    if (_platform != 'ios') _platform = 'android';
    _fields = {
      for (final key in _fieldKeys)
        key: TextEditingController(text: _string(_version[key])),
    };
    _forceUpdate = _isTrue(_version['force_update']);
    _active = widget.existing == null || _isTrue(_version['is_active']);
    _publishNow = widget.existing == null;
  }

  bool _isTrue(Object? value) => value == true || value == 1 || value == '1';
  String _string(Object? value, [String? s]) => value == null ? '' : value.toString();

  @override
  void dispose() {
    for (final controller in _fields.values) {
      controller.dispose();
    }
    super.dispose();
  }

  Future<void> _pickApk() async {
    try {
      final result = await FilePicker.platform.pickFiles(
        type: FileType.custom,
        allowedExtensions: const ['apk'],
        withData: true,
      );
      if (!mounted || result == null || result.files.isEmpty) return;
      final file = result.files.first;
      if (!file.name.toLowerCase().endsWith('.apk')) {
        _showMessage('Choose a file with the .apk extension.');
        return;
      }
      if (file.bytes == null || file.bytes!.isEmpty) {
        _showMessage('The selected APK could not be read. Try another file.');
        return;
      }
      setState(() {
        _apk = file;
        _removeApk = false;
        _error = null;
      });
    } on AuthException catch (error) {
      _showMessage(error.message);
    } catch (_) {
      if (mounted) _showMessage('APK selection failed. Please try again.');
    }
  }

  String? _validateUrl(String? value) {
    final input = value?.trim() ?? '';
    if (input.isEmpty) return null;
    final uri = Uri.tryParse(input);
    if (uri == null ||
        !uri.hasAuthority ||
        (uri.scheme != 'http' && uri.scheme != 'https')) {
      return 'Enter a valid http or https URL.';
    }
    return null;
  }

  Future<void> _save() async {
    if (!_formKey.currentState!.validate()) return;
    final versionName = _fields['version_name']!.text.trim();
    final buildNumber = int.tryParse(_fields['build_number']!.text.trim());
    final minBuildRaw = _fields['minimum_supported_build']!.text.trim();
    final minimumBuild = minBuildRaw.isEmpty ? 0 : int.tryParse(minBuildRaw);
    if (versionName.isEmpty || buildNumber == null || buildNumber < 0) {
      setState(() => _error = 'Enter a version name and a non-negative numeric build number.');
      return;
    }
    if (minimumBuild == null || minimumBuild < 0) {
      setState(() => _error = 'Minimum supported build must be a non-negative number.');
      return;
    }
    if (minimumBuild > buildNumber) {
      setState(() => _error = 'Minimum supported build cannot be greater than this release build.');
      return;
    }
    final urlKeys = _platform == 'android'
        ? const ['direct_download_url', 'play_store_url']
        : const ['direct_download_url', 'app_store_url'];
    for (final key in urlKeys) {
      final message = _validateUrl(_fields[key]!.text);
      if (message != null) {
        setState(() => _error = '${key.replaceAll('_', ' ')}: $message');
        return;
      }
    }
    if (_apk != null && _apk!.bytes == null) {
      setState(() => _error = 'The selected APK is no longer available. Please select it again.');
      return;
    }

    setState(() {
      _saving = true;
      _error = null;
    });
    final multipartFields = <String, String>{
      for (final entry in _fields.entries) entry.key: entry.value.text.trim(),
      'platform': _platform,
      'minimum_supported_build': '$minimumBuild',
      'force_update': _forceUpdate ? '1' : '0',
      'is_active': _active ? '1' : '0',
      if (_publishNow && _version['published_at'] == null) 'publish_now': '1',
      if (_removeApk) 'remove_apk': '1',
    };
    try {
      final id = _version['id'];
      if (_apk != null) {
        if (id != null) multipartFields['_method'] = 'PATCH';
        await widget.api.postMultipart(
          path: id == null ? 'admin/app-versions' : 'admin/app-versions/$id',
          token: widget.token,
          fields: multipartFields,
          fileField: 'apk',
          fileBytes: _apk!.bytes!,
          fileName: _apk!.name,
          method: 'POST',
        );
      } else if (id == null) {
        await widget.api.post(
          'admin/app-versions',
          widget.token,
          _typedFields(multipartFields),
        );
      } else {
        await widget.api.patch(
          'admin/app-versions/$id',
          widget.token,
          _typedFields(multipartFields),
        );
      }
      if (mounted) Navigator.of(context).pop(true);
    } on AuthException catch (error) {
      if (mounted) setState(() => _error = error.message);
    } catch (_) {
      if (mounted) setState(() => _error = 'The app release could not be saved.');
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  Map<String, dynamic> _typedFields(Map<String, String> fields) => {
        for (final entry in fields.entries)
          entry.key: const {
            'force_update',
            'is_active',
            'publish_now',
            'remove_apk',
          }.contains(entry.key)
              ? entry.value == '1'
              : entry.key == 'build_number' ||
                      entry.key == 'minimum_supported_build'
                  ? int.tryParse(entry.value) ?? 0
                  : entry.value.isEmpty
                      ? null
                      : entry.value,
      };

  void _showMessage(String message) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(message), behavior: SnackBarBehavior.floating),
    );
  }

  String _fileSize(int? bytes) {
    if (bytes == null || bytes <= 0) return 'Size unavailable';
    if (bytes < 1024 * 1024) return '${(bytes / 1024).toStringAsFixed(0)} KB';
    return '${(bytes / (1024 * 1024)).toStringAsFixed(1)} MB';
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
                      _BrandMark(
                        icon: _platform == 'ios' ? Icons.apple : Icons.android_rounded,
                        gradient: _platform == 'ios' ? _Palette.berry : _Palette.mint,
                        size: 46,
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              widget.existing == null ? 'Publish a release' : 'Edit release',
                              style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w900),
                            ),
                            const Text('Version details, distribution and update policy.'),
                          ],
                        ),
                      ),
                      IconButton(
                        tooltip: 'Close',
                        onPressed: _saving ? null : () => Navigator.pop(context),
                        icon: const Icon(Icons.close_rounded),
                      ),
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
                        _SectionHeading(
                          title: 'Platform and version',
                          subtitle: 'Choose the platform and provide an increasing build number.',
                        ),
                        const SizedBox(height: 12),
                        SegmentedButton<String>(
                          segments: const [
                            ButtonSegment(
                              value: 'android',
                              label: Text('Android'),
                              icon: Icon(Icons.android_rounded),
                            ),
                            ButtonSegment(
                              value: 'ios',
                              label: Text('iOS'),
                              icon: Icon(Icons.apple),
                            ),
                          ],
                          selected: {_platform},
                          onSelectionChanged: _saving
                              ? null
                              : (selection) => setState(() {
                                  final nextPlatform = selection.first;
                                  if (nextPlatform != _platform) {
                                    _apk = null;
                                    _removeApk = false;
                                  }
                                  _platform = nextPlatform;
                                }),
                        ),
                        const SizedBox(height: 14),
                        _ResponsiveFields(
                          children: [
                            _LabeledField(
                              label: 'Version name',
                              child: TextField(
                                controller: _fields['version_name'],
                                textInputAction: TextInputAction.next,
                                decoration: const InputDecoration(
                                  prefixIcon: Icon(Icons.sell_outlined),
                                  hintText: '1.4.0',
                                ),
                              ),
                            ),
                            _LabeledField(
                              label: 'Build number',
                              child: TextField(
                                controller: _fields['build_number'],
                                keyboardType: TextInputType.number,
                                textInputAction: TextInputAction.next,
                                decoration: const InputDecoration(
                                  prefixIcon: Icon(Icons.numbers_rounded),
                                  hintText: '104',
                                ),
                              ),
                            ),
                            _LabeledField(
                              label: 'Minimum supported build',
                              child: TextField(
                                controller: _fields['minimum_supported_build'],
                                keyboardType: TextInputType.number,
                                textInputAction: TextInputAction.next,
                                decoration: const InputDecoration(
                                  prefixIcon: Icon(Icons.shield_outlined),
                                  hintText: '0',
                                ),
                              ),
                            ),
                            _LabeledField(
                              label: 'Release title (optional)',
                              child: TextField(
                                controller: _fields['title'],
                                textInputAction: TextInputAction.next,
                                decoration: const InputDecoration(
                                  prefixIcon: Icon(Icons.title_rounded),
                                  hintText: 'Performance and reliability updates',
                                ),
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 14),
                        TextField(
                          controller: _fields['release_notes'],
                          minLines: 3,
                          maxLines: 7,
                          maxLength: 10000,
                          textCapitalization: TextCapitalization.sentences,
                          decoration: const InputDecoration(
                            labelText: 'Release notes',
                            alignLabelWithHint: true,
                            prefixIcon: Icon(Icons.notes_rounded),
                            hintText: 'Describe what changed in this release.',
                          ),
                        ),
                        const SizedBox(height: 18),
                        _SectionHeading(
                          title: 'Distribution',
                          subtitle: _platform == 'android'
                              ? 'Add a store listing and/or direct APK delivery.'
                              : 'Add the App Store listing and/or a direct download URL.',
                        ),
                        const SizedBox(height: 12),
                        if (_platform == 'android') ...[
                          _LabeledField(
                            label: 'Google Play URL',
                            child: TextFormField(
                              controller: _fields['play_store_url'],
                              keyboardType: TextInputType.url,
                              decoration: const InputDecoration(
                                prefixIcon: Icon(Icons.shop_2_outlined),
                                hintText: 'https://play.google.com/store/apps/details?id=…',
                              ),
                              validator: _validateUrl,
                            ),
                          ),
                          const SizedBox(height: 12),
                          _LabeledField(
                            label: 'Direct download URL (optional)',
                            child: TextFormField(
                              controller: _fields['direct_download_url'],
                              keyboardType: TextInputType.url,
                              decoration: const InputDecoration(
                                prefixIcon: Icon(Icons.download_rounded),
                                hintText: 'https://…/app-release.apk',
                              ),
                              validator: _validateUrl,
                            ),
                          ),
                          const SizedBox(height: 14),
                          _apkPicker(),
                        ] else ...[
                          _LabeledField(
                            label: 'App Store URL',
                            child: TextFormField(
                              controller: _fields['app_store_url'],
                              keyboardType: TextInputType.url,
                              decoration: const InputDecoration(
                                prefixIcon: Icon(Icons.apple),
                                hintText: 'https://apps.apple.com/app/…',
                              ),
                              validator: _validateUrl,
                            ),
                          ),
                          const SizedBox(height: 12),
                          _LabeledField(
                            label: 'Direct download URL (optional)',
                            child: TextFormField(
                              controller: _fields['direct_download_url'],
                              keyboardType: TextInputType.url,
                              decoration: const InputDecoration(
                                prefixIcon: Icon(Icons.download_rounded),
                                hintText: 'https://…',
                              ),
                              validator: _validateUrl,
                            ),
                          ),
                        ],
                        const SizedBox(height: 18),
                        _SectionHeading(
                          title: 'Release policy',
                          subtitle: 'Set rollout visibility and minimum-version behavior.',
                        ),
                        const SizedBox(height: 8),
                        SwitchListTile.adaptive(
                          contentPadding: EdgeInsets.zero,
                          title: const Text('Force update', style: TextStyle(fontWeight: FontWeight.w800)),
                          subtitle: const Text('Require users below the minimum build to update.'),
                          value: _forceUpdate,
                          onChanged: _saving ? null : (value) => setState(() => _forceUpdate = value),
                        ),
                        SwitchListTile.adaptive(
                          contentPadding: EdgeInsets.zero,
                          title: const Text('Active', style: TextStyle(fontWeight: FontWeight.w800)),
                          subtitle: const Text('Inactive releases are hidden from the app update check.'),
                          value: _active,
                          onChanged: _saving ? null : (value) => setState(() => _active = value),
                        ),
                        if (_version['published_at'] == null)
                          SwitchListTile.adaptive(
                            contentPadding: EdgeInsets.zero,
                            title: const Text('Publish now', style: TextStyle(fontWeight: FontWeight.w800)),
                            subtitle: const Text('Make this release available immediately after saving.'),
                            value: _publishNow,
                            onChanged: _saving ? null : (value) => setState(() => _publishNow = value),
                          ),
                        if (_error != null) ...[
                          const SizedBox(height: 8),
                          _ErrorBanner(message: _error!),
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
                      Expanded(
                        child: OutlinedButton(
                          onPressed: _saving ? null : () => Navigator.pop(context),
                          child: const Text('Cancel'),
                        ),
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: _GradientButton(
                          label: _saving ? 'Saving…' : 'Save release',
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

  Widget _apkPicker() {
    final existingApk = _version['apk_url']?.toString();
    final selectedName = _apk?.name;
    final isRemoving = _removeApk;
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: _Palette.sky.withValues(alpha: .06),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: _Palette.sky.withValues(alpha: .2)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const Icon(Icons.android_rounded, color: _Palette.green),
              const SizedBox(width: 8),
              const Expanded(child: Text('Android APK', style: TextStyle(fontWeight: FontWeight.w800))),
              if (selectedName != null)
                IconButton(
                  tooltip: 'Remove selected file',
                  onPressed: _saving ? null : () => setState(() => _apk = null),
                  icon: const Icon(Icons.close_rounded),
                ),
            ],
          ),
          if (selectedName != null) ...[
            Text(selectedName, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontWeight: FontWeight.w700)),
            const SizedBox(height: 3),
            Text(_fileSize(_apk?.size), style: const TextStyle(color: _Palette.muted, fontSize: 12)),
          ] else if (existingApk != null && !isRemoving) ...[
            const Text('An APK is currently attached to this release.', style: TextStyle(color: _Palette.muted)),
            const SizedBox(height: 4),
            SelectableText(existingApk, maxLines: 2, style: const TextStyle(color: _Palette.sky, fontSize: 12)),
          ] else if (isRemoving) ...[
            const Text('The attached APK will be removed when you save.', style: TextStyle(color: _Palette.rose)),
          ] else ...[
            const Text('No APK uploaded. You can add one now or provide a direct URL above.', style: TextStyle(color: _Palette.muted)),
          ],
          const SizedBox(height: 10),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              OutlinedButton.icon(
                onPressed: _saving ? null : _pickApk,
                icon: const Icon(Icons.upload_file_rounded),
                label: Text(selectedName == null ? 'Choose APK' : 'Replace APK'),
              ),
              if (existingApk != null && !isRemoving && selectedName == null)
                TextButton.icon(
                  onPressed: _saving ? null : () => setState(() => _removeApk = true),
                  icon: const Icon(Icons.delete_outline_rounded),
                  label: const Text('Remove attached APK'),
                ),
              if (isRemoving)
                TextButton.icon(
                  onPressed: _saving ? null : () => setState(() => _removeApk = false),
                  icon: const Icon(Icons.undo_rounded),
                  label: const Text('Undo removal'),
                ),
            ],
          ),
        ],
      ),
    );
  }
}

class _VersionCard extends StatelessWidget {
  const _VersionCard({required this.version, required this.status, required this.dateLabel, required this.onEdit, required this.onDelete});
  final Map<String, dynamic> version;
  final String status;
  final String Function(Object?) dateLabel;
  final VoidCallback onEdit;
  final VoidCallback onDelete;

  String _text(Object? value, [String fallback = '']) => value == null ? fallback : value.toString();
  bool _isTrue(Object? value) => value == true || value == 1 || value == '1';
  int _int(Object? value) => value is num ? value.toInt() : int.tryParse(value?.toString() ?? '') ?? 0;

  @override
  Widget build(BuildContext context) {
    final platform = _text(version['platform'], 'android').toLowerCase();
    final isAndroid = platform == 'android';
    final forced = _isTrue(version['force_update']);
    final active = _isTrue(version['is_active']);
    final statusColor = switch (status) {
      'live' => _Palette.green,
      'scheduled' => _Palette.sky,
      'draft' => _Palette.amber,
      _ => _Palette.rose,
    };
    final gradient = isAndroid ? _Palette.mint : _Palette.berry;
    final minBuild = _int(version['minimum_supported_build']);
    final pubDate = dateLabel(version['published_at']);
    final storeUrl = isAndroid ? version['play_store_url'] : version['app_store_url'];
    final directUrl = version['direct_download_url'];
    final apkUrl = version['apk_url'];
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
                  _BrandMark(icon: isAndroid ? Icons.android_rounded : Icons.apple, gradient: gradient, size: 48),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          '${_text(version['version_name'], 'Version')} · build ${_text(version['build_number'], '—')}',
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w900),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          '${isAndroid ? 'Android' : 'iOS'} · minimum supported build $minBuild',
                          style: Theme.of(context).textTheme.bodySmall?.copyWith(color: _Palette.muted),
                        ),
                      ],
                    ),
                  ),
                  PopupMenuButton<String>(
                    tooltip: 'Release actions',
                    onSelected: (value) => value == 'edit' ? onEdit() : onDelete(),
                    itemBuilder: (_) => const [
                      PopupMenuItem(value: 'edit', child: Text('Edit release')),
                      PopupMenuItem(value: 'delete', child: Text('Delete release')),
                    ],
                  ),
                ],
              ),
              const SizedBox(height: 12),
              Wrap(
                spacing: 7,
                runSpacing: 7,
                children: [
                  _StatusPill(status: status, color: statusColor),
                  if (forced)
                    const _MetaChip(icon: Icons.gpp_maybe_outlined, color: _Palette.rose, label: 'Force update'),
                  if (active)
                    const _MetaChip(icon: Icons.verified_outlined, color: _Palette.green, label: 'Active'),
                  if (apkUrl != null && apkUrl.toString().isNotEmpty)
                    const _MetaChip(icon: Icons.insert_drive_file_outlined, color: _Palette.teal, label: 'APK attached'),
                ],
              ),
              if (_text(version['title']).isNotEmpty || _text(version['release_notes']).isNotEmpty) ...[
                const SizedBox(height: 13),
                if (_text(version['title']).isNotEmpty)
                  Text(_text(version['title']), style: const TextStyle(fontWeight: FontWeight.w800)),
                if (_text(version['release_notes']).isNotEmpty) ...[
                  if (_text(version['title']).isNotEmpty) const SizedBox(height: 4),
                  Text(
                    _text(version['release_notes']),
                    maxLines: 3,
                    overflow: TextOverflow.ellipsis,
                    style: Theme.of(context).textTheme.bodyMedium?.copyWith(height: 1.4),
                  ),
                ],
              ],
              const SizedBox(height: 13),
              Wrap(
                spacing: 14,
                runSpacing: 8,
                children: [
                  _MetaChip(icon: Icons.event_available_outlined, color: _Palette.indigo, label: status == 'live' ? 'Published $pubDate' : 'Publish date: $pubDate'),
                  if (storeUrl != null && storeUrl.toString().isNotEmpty)
                    _MetaChip(icon: isAndroid ? Icons.shop_2_outlined : Icons.apple, color: _Palette.sky, label: isAndroid ? 'Play Store link set' : 'App Store link set'),
                  if (directUrl != null && directUrl.toString().isNotEmpty)
                    const _MetaChip(icon: Icons.download_rounded, color: _Palette.teal, label: 'Direct download set'),
                ],
              ),
              const SizedBox(height: 10),
              const Divider(height: 1),
              const SizedBox(height: 8),
              Row(
                children: [
                  Expanded(
                    child: OutlinedButton.icon(
                      onPressed: onEdit,
                      icon: const Icon(Icons.edit_outlined, size: 17),
                      label: const Text('Edit release'),
                    ),
                  ),
                  const SizedBox(width: 8),
                  IconButton(
                    tooltip: 'Delete release',
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

class _AnnouncementStyle {
  const _AnnouncementStyle({required this.label, required this.icon, required this.color, required this.gradient});
  final String label;
  final IconData icon;
  final Color color;
  final LinearGradient gradient;
}

class _StylePill extends StatelessWidget {
  const _StylePill({required this.status, required this.color});
  final String status;
  final Color color;
  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 5),
        decoration: BoxDecoration(color: color.withValues(alpha: .14), borderRadius: BorderRadius.circular(999), border: Border.all(color: color.withValues(alpha: .3))),
        child: Text(status.toUpperCase(), style: TextStyle(color: color, fontSize: 10, fontWeight: FontWeight.w800, letterSpacing: .5)),
      );
}

class _StatusPill extends StatelessWidget {
  const _StatusPill({required this.status, required this.color});
  final String status;
  final Color color;
  @override
  Widget build(BuildContext context) => _StylePill(status: status, color: color);
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
        decoration: BoxDecoration(color: _Palette.rose.withValues(alpha: .1), borderRadius: BorderRadius.circular(14), border: Border.all(color: _Palette.rose.withValues(alpha: .35))),
        child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
          const Icon(Icons.error_outline_rounded, size: 18, color: _Palette.rose),
          const SizedBox(width: 10),
          Expanded(child: Text(message, style: const TextStyle(color: _Palette.rose, fontWeight: FontWeight.w600, height: 1.35))),
        ]),
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
        decoration: BoxDecoration(gradient: _Palette.hero, borderRadius: BorderRadius.circular(16), boxShadow: enabled ? [BoxShadow(color: _Palette.indigo.withValues(alpha: .35), blurRadius: 18, offset: const Offset(0, 9))] : null),
        child: Material(
          color: Colors.transparent,
          child: InkWell(
            onTap: onPressed,
            borderRadius: BorderRadius.circular(16),
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 15),
              child: Row(mainAxisAlignment: MainAxisAlignment.center, mainAxisSize: MainAxisSize.min, children: [
                if (leading != null) ...[leading, const SizedBox(width: 10)],
                Flexible(child: Text(label, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 15))),
              ]),
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
        decoration: BoxDecoration(gradient: _Palette.hero, borderRadius: BorderRadius.circular(20), boxShadow: [BoxShadow(color: _Palette.violet.withValues(alpha: .45), blurRadius: 20, offset: const Offset(0, 10))]),
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
        style: IconButton.styleFrom(backgroundColor: Colors.white.withValues(alpha: .18), shape: const CircleBorder(), fixedSize: const Size(38, 38)),
      );
}

class _TileRow extends StatelessWidget {
  const _TileRow({required this.children, this.minItemWidth = 150});
  final List<Widget> children;
  final double minItemWidth;

  @override
  Widget build(BuildContext context) => LayoutBuilder(builder: (context, constraints) {
        final count = (constraints.maxWidth / (minItemWidth + 12)).floor().clamp(1, children.length).toInt();
        final width = (constraints.maxWidth - (count - 1) * 12) / count;
        return Wrap(spacing: 12, runSpacing: 12, children: [for (final child in children) SizedBox(width: width, child: child)]);
      });
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
        decoration: BoxDecoration(gradient: gradient, borderRadius: BorderRadius.circular(20), boxShadow: [BoxShadow(color: gradient.colors.last.withValues(alpha: .3), blurRadius: 16, offset: const Offset(0, 8))]),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Row(children: [Icon(icon, color: Colors.white, size: 18), const Spacer(), Container(width: 6, height: 6, decoration: BoxDecoration(color: Colors.white.withValues(alpha: .7), shape: BoxShape.circle))]),
          const SizedBox(height: 12),
          FittedBox(fit: BoxFit.scaleDown, alignment: Alignment.centerLeft, child: Text(value, maxLines: 1, style: const TextStyle(color: Colors.white, fontSize: 24, fontWeight: FontWeight.w800, height: 1.1))),
          const SizedBox(height: 2),
          Text(label, maxLines: 1, overflow: TextOverflow.ellipsis, style: TextStyle(color: Colors.white.withValues(alpha: .9), fontSize: 12, fontWeight: FontWeight.w600)),
        ]),
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
          suffixIcon: controller.text.isEmpty ? null : IconButton(tooltip: 'Clear search', onPressed: () { controller.clear(); onChanged(''); }, icon: const Icon(Icons.close_rounded)),
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
            child: Row(mainAxisSize: MainAxisSize.min, children: [
              Text(label, style: TextStyle(color: selected ? Colors.white : color, fontWeight: FontWeight.w700, fontSize: 13)),
              const SizedBox(width: 6),
              Container(padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 1), decoration: BoxDecoration(color: selected ? Colors.white.withValues(alpha: .25) : color.withValues(alpha: .16), borderRadius: BorderRadius.circular(999)), child: Text('$count', style: TextStyle(color: selected ? Colors.white : color, fontSize: 11, fontWeight: FontWeight.w800))),
            ]),
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

class _BrandMark extends StatelessWidget {
  const _BrandMark({required this.icon, required this.gradient, this.size = 46});
  final IconData icon;
  final LinearGradient gradient;
  final double size;
  @override
  Widget build(BuildContext context) => Container(width: size, height: size, decoration: BoxDecoration(gradient: gradient, borderRadius: BorderRadius.circular(size * .32)), alignment: Alignment.center, child: Icon(icon, color: Colors.white, size: size * .5));
}

class _SectionHeading extends StatelessWidget {
  const _SectionHeading({required this.title, required this.subtitle});
  final String title;
  final String subtitle;
  @override
  Widget build(BuildContext context) => Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(title, style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 15)), const SizedBox(height: 3), Text(subtitle, style: const TextStyle(color: _Palette.muted, fontSize: 12))]);
}

class _LabeledField extends StatelessWidget {
  const _LabeledField({required this.label, required this.child});
  final String label;
  final Widget child;
  @override
  Widget build(BuildContext context) => Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(label, style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 12)), const SizedBox(height: 7), child]);
}

class _ResponsiveFields extends StatelessWidget {
  const _ResponsiveFields({required this.children});
  final List<Widget> children;
  @override
  Widget build(BuildContext context) => LayoutBuilder(builder: (context, constraints) {
        final columns = constraints.maxWidth >= 700 ? 2 : 1;
        final width = (constraints.maxWidth - 14 * (columns - 1)) / columns;
        return Wrap(spacing: 14, runSpacing: 14, children: [for (final child in children) SizedBox(width: width, child: child)]);
      });
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
          child: Column(mainAxisSize: MainAxisSize.min, children: [
            _BrandMark(icon: icon, gradient: gradient, size: 62),
            const SizedBox(height: 16),
            Text(title, textAlign: TextAlign.center, style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w800)),
            const SizedBox(height: 6),
            ConstrainedBox(constraints: const BoxConstraints(maxWidth: 380), child: Text(message, textAlign: TextAlign.center, style: TextStyle(color: Theme.of(context).colorScheme.onSurfaceVariant, height: 1.4))),
            const SizedBox(height: 18),
            _GradientButton(label: actionLabel, icon: Icons.refresh_rounded, onPressed: onAction),
          ]),
        ),
      );
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

  static const LinearGradient hero = LinearGradient(begin: Alignment.topLeft, end: Alignment.bottomRight, colors: [Color(0xFF3730A3), violet, pink]);
  static const LinearGradient mint = LinearGradient(begin: Alignment.topLeft, end: Alignment.bottomRight, colors: [Color(0xFF0D9488), Color(0xFF22C55E)]);
  static const LinearGradient sunset = LinearGradient(begin: Alignment.topLeft, end: Alignment.bottomRight, colors: [amber, orange]);
  static const LinearGradient ocean = LinearGradient(begin: Alignment.topLeft, end: Alignment.bottomRight, colors: [sky, indigo]);
  static const LinearGradient berry = LinearGradient(begin: Alignment.topLeft, end: Alignment.bottomRight, colors: [pink, violet]);
}

ThemeData _adminTheme(BuildContext context) {
  final base = Theme.of(context);
  return base.copyWith(colorScheme: ColorScheme.fromSeed(seedColor: _Palette.indigo, brightness: base.brightness));
}
