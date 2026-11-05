import 'package:flutter/material.dart';

import '../features/notifications/presentation/widgets/notification_type_list.dart';
import '../services/auth_api.dart';
import '../widgets/auth_components.dart';
import 'notification_settings_screen.dart';

class NotificationsScreen extends StatefulWidget {
  const NotificationsScreen({
    super.key,
    required this.api,
    required this.token,
  });
  final AuthApi api;
  final String token;
  @override
  State<NotificationsScreen> createState() => _NotificationsScreenState();
}

class _NotificationsScreenState extends State<NotificationsScreen> {
  bool _loading = true;
  String? _error;
  List<Map<String, dynamic>> _items = const [];
  String _selectedType = 'all';

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final result = await widget.api.get('notifications', widget.token);
      final data = result['data'];
      final items = data is Map<String, dynamic> ? data['data'] : data;
      if (mounted) {
        setState(() {
          _items = items is List
              ? items.whereType<Map<String, dynamic>>().toList()
              : const [];
          _loading = false;
        });
      }
    } on AuthException catch (error) {
      if (mounted) {
        setState(() {
          _error = error.message;
          _loading = false;
        });
      }
    }
  }

  Future<void> _markRead(Map<String, dynamic> item) async {
    final id = item['id'];
    if (id == null || item['read_at'] != null) return;
    try {
      await widget.api.patch('notifications/$id/read', widget.token, const {});
      if (mounted) {
        setState(() => item['read_at'] = DateTime.now().toIso8601String());
      }
    } on AuthException catch (error) {
      if (mounted) showAuthMessage(context, error.message);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    backgroundColor: Theme.of(context).scaffoldBackgroundColor,
    appBar: AppBar(
      title: const Text(
        'Notifications',
        style: TextStyle(fontWeight: FontWeight.w800),
      ),
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      actions: [
        IconButton(
          tooltip: 'Notification settings',
          onPressed: () => Navigator.push(
            context,
            MaterialPageRoute<void>(
              builder: (_) => NotificationSettingsScreen(
                api: widget.api,
                token: widget.token,
              ),
            ),
          ),
          icon: const Icon(Icons.tune_rounded),
        ),
      ],
    ),
    body: _loading
        ? const Center(child: CircularProgressIndicator())
        : _error != null
        ? Center(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(_error!, style: const TextStyle(color: mutedInk)),
                TextButton(onPressed: _load, child: const Text('Retry')),
              ],
            ),
          )
        : NotificationTypeList(
            items: _items,
            selectedType: _selectedType,
            onSelectType: (type) => setState(() => _selectedType = type),
            onRefresh: _load,
            onOpen: _markRead,
          ),
  );
}
