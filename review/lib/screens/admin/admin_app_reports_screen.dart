import 'package:flutter/material.dart';

import '../../services/auth_api.dart';

class AdminAppReportsScreen extends StatefulWidget {
  const AdminAppReportsScreen({super.key, required this.api});
  final AuthApi api;

  @override
  State<AdminAppReportsScreen> createState() => _AdminAppReportsScreenState();
}

class _AdminAppReportsScreenState extends State<AdminAppReportsScreen> {
  final _email = TextEditingController();
  final _password = TextEditingController();
  String? _token;
  String _filter = 'pending';
  bool _loading = false;
  String? _error;
  List<Map<String, dynamic>> _reports = const [];

  @override
  void initState() {
    super.initState();
    _email.text = 'admin@tlstore.test';
  }

  @override
  void dispose() {
    _email.dispose();
    _password.dispose();
    super.dispose();
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
      _token = response['token']?.toString();
      if (_token == null || _token!.isEmpty)
        throw const AuthException(
          'Admin login did not return an access token.',
        );
      await _load();
    } on AuthException catch (error) {
      if (mounted) setState(() => _error = error.message);
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
        'admin/app-reports?status=$_filter&per_page=100',
        token,
      );
      final body = response['data'];
      final rows = body is Map ? body['data'] : body;
      if (mounted)
        setState(
          () => _reports = rows is List
              ? rows.whereType<Map<String, dynamic>>().toList()
              : const [],
        );
    } on AuthException catch (error) {
      if (mounted) setState(() => _error = error.message);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _update(Map<String, dynamic> report, String status) async {
    final notes = TextEditingController(
      text: report['admin_notes']?.toString() ?? '',
    );
    final saved = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(
          status == 'under_review' ? 'Review report' : 'Close report',
        ),
        content: TextField(
          controller: notes,
          minLines: 2,
          maxLines: 5,
          decoration: const InputDecoration(
            labelText: 'Admin notes (optional)',
            border: OutlineInputBorder(),
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(dialogContext, true),
            child: const Text('Save'),
          ),
        ],
      ),
    );
    if (saved != true || _token == null) {
      notes.dispose();
      return;
    }
    try {
      await widget.api.patch('admin/app-reports/${report['id']}', _token!, {
        'status': status,
        'admin_notes': notes.text.trim(),
      });
      await _load();
    } on AuthException catch (error) {
      if (mounted)
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(error.message)));
    } finally {
      notes.dispose();
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(
      title: const Text('App reports'),
      actions: [
        if (_token != null)
          IconButton(
            onPressed: _loading ? null : _load,
            icon: const Icon(Icons.refresh),
          ),
      ],
    ),
    body: _token == null
        ? _loginForm()
        : Column(
            children: [
              Padding(
                padding: const EdgeInsets.all(16),
                child: SegmentedButton<String>(
                  segments: const [
                    ButtonSegment(value: 'pending', label: Text('Pending')),
                    ButtonSegment(
                      value: 'under_review',
                      label: Text('Reviewing'),
                    ),
                    ButtonSegment(value: 'resolved', label: Text('Resolved')),
                    ButtonSegment(value: 'rejected', label: Text('Rejected')),
                  ],
                  selected: {_filter},
                  onSelectionChanged: (selection) {
                    setState(() => _filter = selection.first);
                    _load();
                  },
                ),
              ),
              if (_error != null)
                Padding(
                  padding: const EdgeInsets.all(16),
                  child: Text(
                    _error!,
                    style: TextStyle(
                      color: Theme.of(context).colorScheme.error,
                    ),
                  ),
                ),
              Expanded(
                child: _loading
                    ? const Center(child: CircularProgressIndicator())
                    : _reports.isEmpty
                    ? const Center(child: Text('No reports in this status.'))
                    : RefreshIndicator(
                        onRefresh: _load,
                        child: ListView.builder(
                          itemCount: _reports.length,
                          itemBuilder: (context, index) {
                            final item = _reports[index];
                            final app = item['app'] is Map
                                ? Map<String, dynamic>.from(item['app'] as Map)
                                : <String, dynamic>{};
                            final reporter = item['reporter'] is Map
                                ? Map<String, dynamic>.from(
                                    item['reporter'] as Map,
                                  )
                                : <String, dynamic>{};
                            return Card(
                              margin: const EdgeInsets.fromLTRB(16, 6, 16, 6),
                              child: Padding(
                                padding: const EdgeInsets.all(16),
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(
                                      app['name']?.toString() ?? 'App listing',
                                      style: Theme.of(context)
                                          .textTheme
                                          .titleMedium
                                          ?.copyWith(
                                            fontWeight: FontWeight.w800,
                                          ),
                                    ),
                                    Text(
                                      '${reporter['full_name'] ?? reporter['email'] ?? 'User'} · ${item['reason_code'] ?? ''}',
                                    ),
                                    if ((item['details']?.toString() ?? '')
                                        .isNotEmpty)
                                      Padding(
                                        padding: const EdgeInsets.only(top: 8),
                                        child: Text(item['details'].toString()),
                                      ),
                                    if ((item['admin_notes']?.toString() ?? '')
                                        .isNotEmpty)
                                      Padding(
                                        padding: const EdgeInsets.only(top: 8),
                                        child: Text(
                                          'Admin notes: ${item['admin_notes']}',
                                        ),
                                      ),
                                    if (_filter == 'pending' ||
                                        _filter == 'under_review')
                                      Wrap(
                                        spacing: 8,
                                        children: [
                                          if (_filter == 'pending')
                                            OutlinedButton(
                                              onPressed: () =>
                                                  _update(item, 'under_review'),
                                              child: const Text(
                                                'Mark reviewing',
                                              ),
                                            ),
                                          TextButton(
                                            onPressed: () =>
                                                _update(item, 'rejected'),
                                            child: const Text('Reject'),
                                          ),
                                          FilledButton(
                                            onPressed: () =>
                                                _update(item, 'resolved'),
                                            child: const Text('Resolve'),
                                          ),
                                        ],
                                      ),
                                  ],
                                ),
                              ),
                            );
                          },
                        ),
                      ),
              ),
            ],
          ),
  );

  Widget _loginForm() => Center(
    child: ConstrainedBox(
      constraints: const BoxConstraints(maxWidth: 420),
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(Icons.admin_panel_settings_outlined, size: 44),
            const SizedBox(height: 12),
            TextField(
              controller: _email,
              keyboardType: TextInputType.emailAddress,
              decoration: const InputDecoration(
                labelText: 'Administrator email',
              ),
            ),
            const SizedBox(height: 10),
            TextField(
              controller: _password,
              obscureText: true,
              decoration: const InputDecoration(labelText: 'Password'),
            ),
            if (_error != null)
              Padding(
                padding: const EdgeInsets.only(top: 12),
                child: Text(
                  _error!,
                  style: TextStyle(color: Theme.of(context).colorScheme.error),
                ),
              ),
            const SizedBox(height: 14),
            SizedBox(
              width: double.infinity,
              child: FilledButton(
                onPressed: _loading ? null : _login,
                child: Text(_loading ? 'Signing in…' : 'Sign in as admin'),
              ),
            ),
          ],
        ),
      ),
    ),
  );
}
