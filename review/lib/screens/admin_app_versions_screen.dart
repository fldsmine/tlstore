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
  final _email = TextEditingController();
  final _password = TextEditingController();
  String? _token;
  List<Map<String, dynamic>> _versions = [];
  bool _loading = false;
  String? _error;


  @override
  void initState() {
    super.initState();

    _email.text = "admin@tlstore.test";
    _password.text = "meandmyson";
  }

  Future<void> _login() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final response = await widget.api.postPublic('admin/auth/login', {
        'email': _email.text.trim(),
        'password': _password.text,
      });
      final token = response['token']?.toString();
      if (token == null || token.isEmpty)
        throw const AuthException(
          'Admin login response did not contain a token.',
        );
      _token = token;
      await _load();
    } on AuthException catch (e) {
      if (mounted) setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _load() async {
    final token = _token;
    if (token == null) return;
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final response = await widget.api.get(
        'admin/app-versions?per_page=100',
        token,
      );
      final data = response['data'];
      final rows = data is Map<String, dynamic> ? data['data'] : data;
      if (mounted)
        setState(
          () => _versions = rows is List
              ? rows.whereType<Map<String, dynamic>>().toList()
              : [],
        );
    } on AuthException catch (e) {
      if (mounted) setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _delete(Map<String, dynamic> version) async {
    final confirm = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Delete release?'),
        content: Text(
          '${version['platform']} ${version['version_name']} (build ${version['build_number']}) and uploaded APK will be removed.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(ctx, true),
            child: const Text('Delete'),
          ),
        ],
      ),
    );
    if (confirm != true) return;
    try {
      await widget.api.delete('admin/app-versions/${version['id']}', _token!);
      await _load();
    } on AuthException catch (e) {
      if (mounted) _show(e.message);
    }
  }

  Future<void> _edit([Map<String, dynamic>? version]) async {
    final saved = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (_) =>
          _VersionForm(api: widget.api, token: _token!, existing: version),
    );
    if (saved == true) _load();
  }

  void _show(String message) =>
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(message)));

  @override
  Widget build(BuildContext context) {
    Widget body;
    if (_token == null) {
      body = Center(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(24),
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 420),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                const Icon(Icons.admin_panel_settings_outlined, size: 54),
                const SizedBox(height: 12),
                const Text(
                  'Admin sign-in',
                  style: TextStyle(fontSize: 21, fontWeight: FontWeight.w800),
                ),
                const SizedBox(height: 16),
                TextField(
                  controller: _email,
                  keyboardType: TextInputType.emailAddress,
                  decoration: const InputDecoration(labelText: 'Admin email'),
                ),
                const SizedBox(height: 10),
                TextField(
                  controller: _password,
                  obscureText: true,
                  decoration: const InputDecoration(labelText: 'Password'),
                ),
                if (_error != null)
                  Padding(
                    padding: const EdgeInsets.all(8),
                    child: Text(
                      _error!,
                      style: const TextStyle(color: Colors.red),
                    ),
                  ),
                const SizedBox(height: 10),
                FilledButton(
                  onPressed: _loading ? null : _login,
                  child: Text(_loading ? 'Signing in…' : 'Sign in'),
                ),
              ],
            ),
          ),
        ),
      );
    } else if (_loading) {
      body = const Center(child: CircularProgressIndicator());
    } else if (_error != null) {
      body = Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(_error!, textAlign: TextAlign.center),
            TextButton(onPressed: _load, child: const Text('Retry')),
          ],
        ),
      );
    } else {
      body = RefreshIndicator(
        onRefresh: _load,
        child: _versions.isEmpty
            ? ListView(
                children: const [
                  SizedBox(height: 160),
                  Center(child: Text('No app releases yet. Tap + to add one.')),
                ],
              )
            : ListView.separated(
                padding: const EdgeInsets.all(16),
                itemCount: _versions.length,
                separatorBuilder: (_, __) => const SizedBox(height: 8),
                itemBuilder: (_, index) {
                  final v = _versions[index];
                  final published = DateTime.tryParse(
                    v['published_at']?.toString() ?? '',
                  );
                  final live =
                      v['is_active'] == true &&
                      published != null &&
                      published.isBefore(DateTime.now());
                  return Card(
                    child: ListTile(
                      onTap: () => _edit(v),
                      leading: Icon(
                        v['platform'] == 'ios' ? Icons.apple : Icons.android,
                        color: Theme.of(context).colorScheme.primary,
                      ),
                      title: Text(
                        '${v['version_name']} • build ${v['build_number']}',
                        style: const TextStyle(fontWeight: FontWeight.w800),
                      ),
                      subtitle: Text(
                        '${live ? 'Published' : 'Inactive or scheduled'}${v['force_update'] == true ? ' • forced update' : ''}\\n${v['title'] ?? ''}',
                        maxLines: 2,
                      ),
                      isThreeLine: true,
                      trailing: IconButton(
                        onPressed: () => _delete(v),
                        icon: const Icon(
                          Icons.delete_outline,
                          color: Colors.redAccent,
                        ),
                      ),
                    ),
                  );
                },
              ),
      );
    }
    return Scaffold(
      appBar: AppBar(
        title: const Text('App releases'),
        actions: [
          if (_token != null)
            IconButton(
              onPressed: _load,
              icon: const Icon(Icons.refresh_rounded),
            ),
          if (_token != null)
            IconButton(
              onPressed: () => _edit(),
              icon: const Icon(Icons.add_rounded),
            ),
        ],
      ),
      body: body,
    );
  }
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
  late String _platform;
  late final Map<String, TextEditingController> _fields;
  late bool _force, _active, _publish;
  PlatformFile? _apk;
  bool _saving = false, _removeApk = false;
  dynamic get _v => widget.existing ?? const {};

  @override
  void initState() {
    super.initState();
    _platform = _v['platform']?.toString() ?? 'android';
    _fields = {
      for (final key in [
        'version_name',
        'build_number',
        'minimum_supported_build',
        'title',
        'release_notes',
        'direct_download_url',
        'play_store_url',
        'app_store_url',
      ])
        key: TextEditingController(text: _v[key]?.toString() ?? ''),
    };
    _force = _v['force_update'] == true;
    _active = widget.existing == null || _v['is_active'] == true;
    _publish = widget.existing == null;
  }

  @override
  void dispose() {
    for (final c in _fields.values) {
      c.dispose();
    }
    super.dispose();
  }

  Future<void> _pickApk() async {
    final result = await FilePicker.platform.pickFiles(
      type: FileType.any,
      withData: true,
    );
    final file = result?.files.firstOrNull;
    if (file == null) return;
    if (!file.name.toLowerCase().endsWith('.apk')) {
      ScaffoldMessenger.of(context)
          .showSnackBar(const SnackBar(content: Text('Select an APK file.')));
      return;
    }
    setState(() {
      _apk = file;
      _removeApk = false;
    });
  }

  Future<void> _save() async {
    if (_fields['version_name']!.text.trim().isEmpty ||
        int.tryParse(_fields['build_number']!.text.trim()) == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Version name and numeric build number are required.'),
        ),
      );
      return;
    }
    setState(() => _saving = true);
    final fields = {
      for (final e in _fields.entries) e.key: e.value.text.trim(),
      'platform': _platform,
      'minimum_supported_build':
          _fields['minimum_supported_build']!.text.trim().isEmpty
          ? '0'
          : _fields['minimum_supported_build']!.text.trim(),
      'force_update': _force ? '1' : '0',
      'is_active': _active ? '1' : '0',
      if (_publish) 'publish_now': '1',
      if (_removeApk) 'remove_apk': '1',
    };
    try {
      final id = _v['id'];
      if (_apk?.bytes != null) {
        if (id != null) fields['_method'] = 'PATCH';
        await widget.api.postMultipart(
          path: id == null ? 'admin/app-versions' : 'admin/app-versions/$id',
          token: widget.token,
          fields: fields,
          fileField: 'apk',
          fileBytes: _apk!.bytes!,
          fileName: _apk!.name,
          method: 'POST',
        );
      } else if (id == null) {
        await widget.api.post(
          'admin/app-versions',
          widget.token,
          _typedFields(fields),
        );
      } else {
        await widget.api.patch(
          'admin/app-versions/$id',
          widget.token,
          _typedFields(fields),
        );
      }
      if (mounted) Navigator.pop(context, true);
    } on AuthException catch (e) {
      if (mounted)
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(e.message)));
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  Map<String, dynamic> _typedFields(Map<String, String> fields) => {
    for (final entry in fields.entries)
      entry.key:
          [
            'force_update',
            'is_active',
            'publish_now',
            'remove_apk',
          ].contains(entry.key)
          ? entry.value == '1'
          : entry.key == 'build_number' ||
                entry.key == 'minimum_supported_build'
          ? int.tryParse(entry.value) ?? 0
          : entry.value.isEmpty
          ? null
          : entry.value,
  };

  Widget _field(String key, {int lines = 1, TextInputType? keyboard}) =>
      Padding(
        padding: const EdgeInsets.only(bottom: 10),
        child: TextField(
          controller: _fields[key],
          maxLines: lines,
          keyboardType: keyboard,
          decoration: InputDecoration(labelText: key.replaceAll('_', ' ')),
        ),
      );

  @override
  Widget build(BuildContext context) => Padding(
    padding: EdgeInsets.only(bottom: MediaQuery.viewInsetsOf(context).bottom),
    child: SafeArea(
      child: ListView(
        shrinkWrap: true,
        padding: const EdgeInsets.fromLTRB(18, 8, 18, 20),
        children: [
          Text(
            widget.existing == null ? 'Publish release' : 'Edit release',
            style: Theme.of(context).textTheme.titleLarge
                ?.copyWith(fontWeight: FontWeight.w800),
          ),
          const SizedBox(height: 12),
          SegmentedButton<String>(
            segments: const [
              ButtonSegment(value: 'android', label: Text('Android')),
              ButtonSegment(value: 'ios', label: Text('iOS')),
            ],
            selected: {_platform},
            onSelectionChanged: (x) => setState(() => _platform = x.first),
          ),
          const SizedBox(height: 12),
          _field('version_name'),
          _field('build_number', keyboard: TextInputType.number),
          _field('minimum_supported_build', keyboard: TextInputType.number),
          _field('title'),
          _field('release_notes', lines: 4),
          if (_platform == 'android') ...[
            _field('play_store_url'),
            _field('direct_download_url'),
            OutlinedButton.icon(
              onPressed: _saving ? null : _pickApk,
              icon: const Icon(Icons.upload_file),
              label: Text(_apk?.name ?? 'Upload/replace APK'),
            ),
            if (_v['apk_url'] != null && _apk == null)
              TextButton(
                onPressed: () => setState(() => _removeApk = true),
                child: const Text('Remove APK'),
              ),
          ] else ...[
            _field('app_store_url'),
            _field('direct_download_url'),
          ],
          SwitchListTile.adaptive(
            title: const Text('Force update'),
            value: _force,
            onChanged: (v) => setState(() => _force = v),
          ),
          SwitchListTile.adaptive(
            title: const Text('Active'),
            value: _active,
            onChanged: (v) => setState(() => _active = v),
          ),
          if (_v['published_at'] == null)
            SwitchListTile.adaptive(
              title: const Text('Publish now'),
              value: _publish,
              onChanged: (v) => setState(() => _publish = v),
            ),
          FilledButton(
            onPressed: _saving ? null : _save,
            child: Text(_saving ? 'Saving…' : 'Save release'),
          ),
        ],
      ),
    ),
  );
}
