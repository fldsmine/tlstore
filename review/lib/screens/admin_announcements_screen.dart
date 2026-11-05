import 'package:flutter/material.dart';

import '../services/auth_api.dart';

class AdminAnnouncementsScreen extends StatefulWidget {
  const AdminAnnouncementsScreen({super.key, required this.api});

  final AuthApi api;

  @override
  State<AdminAnnouncementsScreen> createState() =>
      _AdminAnnouncementsScreenState();
}

class _AdminAnnouncementsScreenState extends State<AdminAnnouncementsScreen> {
  final _email = TextEditingController();
  final _password = TextEditingController();
  String? _token;
  List<Map<String, dynamic>> _items = const [];
  bool _loading = false;
  String? _error;

  @override
  void initState() {
    super.initState();

    _email.text = "admin@tlstore.test";
    _password.text = "meandmyson";
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
      final token = response['token']?.toString();
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
        'admin/announcements?per_page=100',
        token,
      );
      final data = response['data'];
      final rows = data is Map<String, dynamic> ? data['data'] : data;
      if (mounted) {
        setState(
          () => _items = rows is List
              ? rows.whereType<Map<String, dynamic>>().toList()
              : const [],
        );
      }
    } on AuthException catch (error) {
      if (mounted) setState(() => _error = error.message);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _edit([Map<String, dynamic>? existing]) async {
    final saved = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (_) => _AnnouncementForm(
        api: widget.api,
        token: _token!,
        existing: existing,
      ),
    );
    if (saved == true) await _load();
  }

  Future<void> _delete(Map<String, dynamic> item) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Delete announcement?'),
        content: Text('“${item['title']}” will be removed.'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(dialogContext, true),
            child: const Text('Delete'),
          ),
        ],
      ),
    );
    if (confirmed != true) return;
    try {
      await widget.api.delete('admin/announcements/${item['id']}', _token!);
      await _load();
    } on AuthException catch (error) {
      if (mounted) _message(error.message);
    }
  }

  void _message(String value) =>
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(value)));

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(
      title: const Text('Announcements'),
      actions: [
        if (_token != null)
          IconButton(
            tooltip: 'Refresh',
            onPressed: _loading ? null : _load,
            icon: const Icon(Icons.refresh_rounded),
          ),
      ],
    ),
    floatingActionButton: _token == null
        ? null
        : FloatingActionButton.extended(
            onPressed: _loading ? null : () => _edit(),
            icon: const Icon(Icons.add_rounded),
            label: const Text('New announcement'),
          ),
    body: _token == null ? _loginForm() : _managementBody(),
  );

  Widget _loginForm() => Center(
    child: SingleChildScrollView(
      padding: const EdgeInsets.all(22),
      child: ConstrainedBox(
        constraints: const BoxConstraints(maxWidth: 430),
        child: Card(
          child: Padding(
            padding: const EdgeInsets.all(20),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Icon(
                  Icons.campaign_rounded,
                  size: 48,
                  color: Theme.of(context).colorScheme.primary,
                ),
                const SizedBox(height: 12),
                Text(
                  'Admin access',
                  style: Theme.of(context).textTheme.titleLarge
                      ?.copyWith(fontWeight: FontWeight.w800),
                ),
                const SizedBox(height: 6),
                const Text(
                  'Sign in with an administrator account to manage home announcements.',
                  textAlign: TextAlign.center,
                ),
                const SizedBox(height: 18),
                TextField(
                  controller: _email,
                  keyboardType: TextInputType.emailAddress,
                  decoration: const InputDecoration(
                    labelText: 'Admin email',
                    prefixIcon: Icon(Icons.email_outlined),
                  ),
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: _password,
                  obscureText: true,
                  decoration: const InputDecoration(
                    labelText: 'Password',
                    prefixIcon: Icon(Icons.lock_outline_rounded),
                  ),
                  onSubmitted: (_) {
                    if (!_loading) _login();
                  },
                ),
                if (_error != null) ...[
                  const SizedBox(height: 10),
                  Text(
                    _error!,
                    style: TextStyle(
                      color: Theme.of(context).colorScheme.error,
                    ),
                  ),
                ],
                const SizedBox(height: 16),
                SizedBox(
                  width: double.infinity,
                  child: FilledButton(
                    onPressed: _loading ? null : _login,
                    child: _loading
                        ? const SizedBox.square(
                            dimension: 19,
                            child: CircularProgressIndicator(strokeWidth: 2),
                          )
                        : const Text('Sign in'),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    ),
  );

  Widget _managementBody() {
    if (_loading && _items.isEmpty)
      return const Center(child: CircularProgressIndicator());
    if (_error != null && _items.isEmpty) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(_error!, textAlign: TextAlign.center),
              TextButton(onPressed: _load, child: const Text('Retry')),
            ],
          ),
        ),
      );
    }
    if (_items.isEmpty)
      return const Center(child: Text('No announcements yet.'));
    return RefreshIndicator(
      onRefresh: _load,
      child: ListView.builder(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 90),
        itemCount: _items.length,
        itemBuilder: (context, index) {
          final item = _items[index];
          final mode = _displayName(
            (item['display_type'] as num?)?.toInt() ?? 0,
          );
          final type = item['type']?.toString() ?? 'info';
          final active = item['is_active'] == true;
          return Card(
            margin: const EdgeInsets.only(bottom: 10),
            child: ListTile(
              leading: CircleAvatar(child: Icon(_typeIcon(type))),
              title: Text(
                item['title']?.toString() ?? '',
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
              ),
              subtitle: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const SizedBox(height: 4),
                  Text(
                    item['body']?.toString() ?? '',
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                  ),
                  const SizedBox(height: 5),
                  Text(
                    '$mode • ${type.toUpperCase()} • ${active ? 'ACTIVE' : 'INACTIVE'}',
                  ),
                ],
              ),
              isThreeLine: true,
              trailing: PopupMenuButton<String>(
                onSelected: (value) =>
                    value == 'edit' ? _edit(item) : _delete(item),
                itemBuilder: (_) => const [
                  PopupMenuItem(value: 'edit', child: Text('Edit')),
                  PopupMenuItem(value: 'delete', child: Text('Delete')),
                ],
              ),
              onTap: () => _edit(item),
            ),
          );
        },
      ),
    );
  }
}

class _AnnouncementForm extends StatefulWidget {
  const _AnnouncementForm({
    required this.api,
    required this.token,
    this.existing,
  });
  final AuthApi api;
  final String token;
  final Map<String, dynamic>? existing;

  @override
  State<_AnnouncementForm> createState() => _AnnouncementFormState();
}

class _AnnouncementFormState extends State<_AnnouncementForm> {
  final _formKey = GlobalKey<FormState>();
  late final _title = TextEditingController(
    text: widget.existing?['title']?.toString(),
  );
  late final _body = TextEditingController(
    text: widget.existing?['body']?.toString(),
  );
  late final _actionUrl = TextEditingController(
    text: widget.existing?['action_url']?.toString(),
  );
  late final _actionLabel = TextEditingController(
    text: widget.existing?['action_label']?.toString(),
  );
  late String _type = widget.existing?['type']?.toString() ?? 'info';
  late int _displayType =
      (widget.existing?['display_type'] as num?)?.toInt() ?? 0;
  late bool _dismissible = widget.existing?['is_dismissible'] != false;
  late bool _active = widget.existing?['is_active'] != false;
  late DateTime? _startsAt = DateTime.tryParse(
    widget.existing?['starts_at']?.toString() ?? '',
  )?.toLocal();
  late DateTime? _endsAt = DateTime.tryParse(
    widget.existing?['ends_at']?.toString() ?? '',
  )?.toLocal();
  bool _saving = false;

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

  String _dateLabel(DateTime? value) => value == null
      ? 'No date limit'
      : '${value.year}-${value.month.toString().padLeft(2, '0')}-${value.day.toString().padLeft(2, '0')}';

  Future<void> _save() async {
    if (!_formKey.currentState!.validate()) return;
    if (_startsAt != null && _endsAt != null && _endsAt!.isBefore(_startsAt!)) {
      _message('End date must be on or after the start date.');
      return;
    }
    if (_displayType == 0 && !_dismissible && _actionUrl.text.trim().isEmpty) {
      _message('Add an action link to a non-dismissible pop-up.');
      return;
    }
    setState(() => _saving = true);
    final payload = <String, dynamic>{
      'title': _title.text.trim(),
      'body': _body.text.trim(),
      'action_url': _actionUrl.text.trim().isEmpty
          ? null
          : _actionUrl.text.trim(),
      'action_label': _actionLabel.text.trim().isEmpty
          ? null
          : _actionLabel.text.trim(),
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
        await widget.api.patch(
          'admin/announcements/$id',
          widget.token,
          payload,
        );
      }
      if (mounted) Navigator.of(context).pop(true);
    } on AuthException catch (error) {
      if (mounted) _message(error.message);
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  void _message(String value) =>
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(value)));

  @override
  Widget build(BuildContext context) => Padding(
    padding: EdgeInsets.only(bottom: MediaQuery.viewInsetsOf(context).bottom),
    child: SafeArea(
      top: false,
      child: SizedBox(
        height: MediaQuery.sizeOf(context).height * .88,
        child: Form(
          key: _formKey,
          child: ListView(
            padding: const EdgeInsets.fromLTRB(20, 8, 20, 24),
            children: [
              Text(
                widget.existing == null
                    ? 'Create announcement'
                    : 'Edit announcement',
                style: Theme.of(context).textTheme.titleLarge
                    ?.copyWith(fontWeight: FontWeight.w800),
              ),
              const SizedBox(height: 16),
              TextFormField(
                controller: _title,
                maxLength: 255,
                decoration: const InputDecoration(labelText: 'Title'),
                validator: (value) => value == null || value.trim().isEmpty
                    ? 'Title is required.'
                    : null,
              ),
              const SizedBox(height: 10),
              TextFormField(
                controller: _body,
                minLines: 3,
                maxLines: 6,
                maxLength: 20000,
                decoration: const InputDecoration(
                  labelText: 'Message',
                  alignLabelWithHint: true,
                ),
                validator: (value) => value == null || value.trim().isEmpty
                    ? 'Message is required.'
                    : null,
              ),
              const SizedBox(height: 10),
              TextFormField(
                controller: _actionUrl,
                keyboardType: TextInputType.url,
                decoration: const InputDecoration(
                  labelText: 'Action URL (optional)',
                  hintText: 'https://example.com',
                ),
              ),
              const SizedBox(height: 10),
              TextFormField(
                controller: _actionLabel,
                maxLength: 60,
                decoration: const InputDecoration(
                  labelText: 'Action label (optional)',
                ),
              ),
              const SizedBox(height: 10),
              DropdownButtonFormField<String>(
                initialValue: _type,
                decoration: const InputDecoration(labelText: 'Style'),
                items: const [
                  DropdownMenuItem(value: 'info', child: Text('Info')),
                  DropdownMenuItem(value: 'success', child: Text('Success')),
                  DropdownMenuItem(value: 'warning', child: Text('Warning')),
                  DropdownMenuItem(value: 'danger', child: Text('Danger')),
                ],
                onChanged: (value) => setState(() => _type = value ?? 'info'),
              ),
              const SizedBox(height: 10),
              DropdownButtonFormField<int>(
                initialValue: _displayType,
                decoration: const InputDecoration(
                  labelText: 'Display location',
                ),
                items: const [
                  DropdownMenuItem(value: 0, child: Text('Pop-up dialog')),
                  DropdownMenuItem(
                    value: 1,
                    child: Text('Inline below earnings'),
                  ),
                  DropdownMenuItem(
                    value: 2,
                    child: Text('Pinned below earnings'),
                  ),
                ],
                onChanged: (value) => setState(() => _displayType = value ?? 0),
              ),
              SwitchListTile.adaptive(
                contentPadding: EdgeInsets.zero,
                title: const Text('Dismissible'),
                subtitle: Text(
                  _displayType == 2
                      ? 'Pinned notices cannot be dismissed.'
                      : 'Let users close this announcement.',
                ),
                value: _displayType == 2 ? false : _dismissible,
                onChanged: _displayType == 2
                    ? null
                    : (value) => setState(() => _dismissible = value),
              ),
              SwitchListTile.adaptive(
                contentPadding: EdgeInsets.zero,
                title: const Text('Active'),
                value: _active,
                onChanged: (value) => setState(() => _active = value),
              ),
              const SizedBox(height: 8),
              _datePickerRow(
                title: 'Starts',
                value: _startsAt,
                onPick: () => _pickDate(start: true),
                onClear: () => setState(() => _startsAt = null),
              ),
              _datePickerRow(
                title: 'Ends',
                value: _endsAt,
                onPick: () => _pickDate(start: false),
                onClear: () => setState(() => _endsAt = null),
              ),
              const SizedBox(height: 16),
              FilledButton(
                onPressed: _saving ? null : _save,
                child: _saving
                    ? const SizedBox.square(
                        dimension: 19,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : const Text('Save announcement'),
              ),
            ],
          ),
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
    title: Text(title),
    subtitle: Text(_dateLabel(value)),
    trailing: Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        IconButton(
          tooltip: 'Choose date',
          onPressed: onPick,
          icon: const Icon(Icons.calendar_month_outlined),
        ),
        if (value != null)
          IconButton(
            tooltip: 'Clear date',
            onPressed: onClear,
            icon: const Icon(Icons.clear_rounded),
          ),
      ],
    ),
  );
}

String _displayName(int value) => switch (value) {
  1 => 'Inline',
  2 => 'Pinned',
  _ => 'Pop-up',
};

IconData _typeIcon(String type) => switch (type) {
  'success' => Icons.check_circle_outline_rounded,
  'warning' => Icons.warning_amber_rounded,
  'danger' => Icons.error_outline_rounded,
  _ => Icons.info_outline_rounded,
};
