import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';

import '../services/auth_api.dart';
import '../services/push_notification_service.dart';

class NotificationSettingsScreen extends StatefulWidget {
  const NotificationSettingsScreen({
    super.key,
    required this.api,
    required this.token,
  });
  final AuthApi api;
  final String token;
  @override
  State<NotificationSettingsScreen> createState() =>
      _NotificationSettingsScreenState();
}

class _NotificationSettingsScreenState
    extends State<NotificationSettingsScreen> {
  List<Map<String, dynamic>>? _categories;
  String? _error;
  final Set<String> _saving = {};
  bool _pushAvailable = false;
  bool _emailAvailable = false;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() => _error = null);
    try {
      final response = await widget.api.get(
        'me/notification-preferences',
        widget.token,
      );
      final data = response['data'];
      final list = data is Map<String, dynamic> ? data['categories'] : data;
      if (mounted)
        setState(() {
          _categories = list is List
              ? list.whereType<Map<String, dynamic>>().toList()
              : [];
          _pushAvailable =
              data is Map<String, dynamic> && data['push_available'] == true;
          _emailAvailable =
              data is Map<String, dynamic> && data['email_available'] == true;
        });
    } on AuthException catch (e) {
      if (mounted) setState(() => _error = e.message);
    }
  }

  Future<void> _toggle(
    Map<String, dynamic> category,
    String channel,
    bool value,
  ) async {
    final key = category['key'].toString();
    final before = Map<String, dynamic>.from(category);
    setState(() {
      _saving.add(key);
      category[channel] = value;
    });
    try {
      if (channel == 'push' && value && kIsWeb) {
        final enabled = await PushNotificationService.instance
            .enableFromUserGesture(widget.api, widget.token);
        if (!enabled) {
          throw const AuthException(
            'Allow browser notifications to enable push delivery. Check Firebase web settings and try again.',
          );
        }
      }
      await widget.api.put('me/notification-preferences', widget.token, {
        'categories': {
          key: {channel: value},
        },
      });
    } on AuthException catch (e) {
      if (mounted) {
        setState(() => category[channel] = before[channel]);
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(e.message)));
      }
    } finally {
      if (mounted) setState(() => _saving.remove(key));
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Notification settings')),
    body: _error != null
        ? Center(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(_error!, textAlign: TextAlign.center),
                TextButton(onPressed: _load, child: const Text('Try again')),
              ],
            ),
          )
        : _categories == null
        ? const Center(child: CircularProgressIndicator())
        : RefreshIndicator(
            onRefresh: _load,
            child: ListView(
              padding: const EdgeInsets.all(16),
              children: [
                Text(
                  'Choose how TrustLens reaches you. Your in-app inbox continues to receive all account notifications.',
                  style: TextStyle(
                    color: Theme.of(context).colorScheme.onSurfaceVariant,
                    height: 1.45,
                  ),
                ),
                const SizedBox(height: 12),
                if (!_pushAvailable)
                  const Text(
                    'Push notifications are not configured for this app build or server.',
                    style: TextStyle(color: Colors.red),
                  ),
                if (!_emailAvailable)
                  const Padding(
                    padding: EdgeInsets.only(top: 6),
                    child: Text(
                      'Email delivery is not configured by the server. In-app notifications remain available.',
                      style: TextStyle(color: Colors.red),
                    ),
                  ),
                for (final item in _categories!)
                  Card(
                    margin: const EdgeInsets.only(bottom: 12),
                    child: Padding(
                      padding: const EdgeInsets.fromLTRB(14, 12, 8, 4),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            children: [
                              Expanded(
                                child: Text(
                                  item['label']?.toString() ??
                                      item['key'].toString(),
                                  style: const TextStyle(
                                    fontWeight: FontWeight.w800,
                                  ),
                                ),
                              ),
                              if (item['locked'] == true)
                                const Icon(Icons.lock_outline, size: 18),
                              if (_saving.contains(item['key']))
                                const Padding(
                                  padding: EdgeInsets.only(right: 8),
                                  child: SizedBox(
                                    width: 16,
                                    height: 16,
                                    child: CircularProgressIndicator(
                                      strokeWidth: 2,
                                    ),
                                  ),
                                ),
                            ],
                          ),
                          if ((item['description']?.toString() ?? '')
                              .isNotEmpty)
                            Padding(
                              padding: const EdgeInsets.only(top: 4),
                              child: Text(
                                item['description'].toString(),
                                style: TextStyle(
                                  fontSize: 12,
                                  color: Theme.of(context)
                                      .colorScheme
                                      .onSurfaceVariant,
                                ),
                              ),
                            ),
                          SwitchListTile.adaptive(
                            dense: true,
                            contentPadding: EdgeInsets.zero,
                            secondary: const Icon(
                              Icons.notifications_active_outlined,
                            ),
                            title: const Text('Push'),
                            value: item['push'] != false,
                            onChanged:
                                (item['locked'] == true ||
                                    !_pushAvailable ||
                                    _saving.contains(item['key']))
                                ? null
                                : (value) => _toggle(item, 'push', value),
                          ),
                          SwitchListTile.adaptive(
                            dense: true,
                            contentPadding: EdgeInsets.zero,
                            secondary: const Icon(Icons.mail_outline),
                            title: const Text('Email'),
                            value: item['email'] == true,
                            onChanged:
                                (item['locked'] == true ||
                                    !_emailAvailable ||
                                    _saving.contains(item['key']))
                                ? null
                                : (value) => _toggle(item, 'email', value),
                          ),
                        ],
                      ),
                    ),
                  ),
              ],
            ),
          ),
  );
}
