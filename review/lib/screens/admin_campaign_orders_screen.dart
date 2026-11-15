import 'package:flutter/material.dart';

import '../core/currency/money.dart';
import '../services/auth_api.dart';

class AdminCampaignOrdersScreen extends StatefulWidget {
  const AdminCampaignOrdersScreen({super.key, required this.api});
  final AuthApi api;

  @override
  State<AdminCampaignOrdersScreen> createState() =>
      _AdminCampaignOrdersScreenState();
}

class _AdminCampaignOrdersScreenState extends State<AdminCampaignOrdersScreen> {
  final _email = TextEditingController(text: 'admin@tlstore.test');
  final _password = TextEditingController();
  String? _token;
  String? _error;
  bool _loading = false;
  List<Map<String, dynamic>> _orders = const [];

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
        'admin/campaign-orders?status=pending&per_page=100',
        token,
      );
      final data = response['data'];
      final rows = data is Map ? data['data'] : data;
      if (mounted)
        setState(
          () => _orders = rows is List
              ? rows
                    .whereType<Map>()
                    .map((row) => Map<String, dynamic>.from(row))
                    .toList()
              : const [],
        );
    } on AuthException catch (error) {
      if (mounted) setState(() => _error = error.message);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _decide(Map<String, dynamic> order, String decision) async {
    String? note;
    if (decision == 'rejected') {
      note = await showDialog<String>(
        context: context,
        builder: (context) {
          final controller = TextEditingController();
          return AlertDialog(
            title: const Text('Reject campaign'),
            content: TextField(
              controller: controller,
              maxLines: 3,
              decoration: const InputDecoration(
                labelText: 'Reason for rejection',
              ),
            ),
            actions: [
              TextButton(
                onPressed: () => Navigator.pop(context),
                child: const Text('Cancel'),
              ),
              FilledButton(
                onPressed: () {
                  if (controller.text.trim().isNotEmpty)
                    Navigator.pop(context, controller.text.trim());
                },
                child: const Text('Reject'),
              ),
            ],
          );
        },
      );
      if (note == null) return;
    }
    final token = _token;
    if (token == null) return;
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      await widget.api.post(
        'admin/campaign-orders/${order['id']}/decision',
        token,
        {'decision': decision, if (note != null) 'note': note},
      );
      await _load();
    } on AuthException catch (error) {
      if (mounted)
        setState(() {
          _loading = false;
          _error = error.message;
        });
    }
  }

  String _pretty(String value) => value
      .split('_')
      .map(
        (word) =>
            word.isEmpty ? '' : '${word[0].toUpperCase()}${word.substring(1)}',
      )
      .join(' ');

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    return Scaffold(
      appBar: AppBar(
        title: const Text('Campaign approvals'),
        actions: [
          if (_token != null)
            IconButton(
              onPressed: _load,
              icon: const Icon(Icons.refresh_rounded),
            ),
        ],
      ),
      body: _token == null
          ? ListView(
              padding: const EdgeInsets.all(20),
              children: [
                const Text(
                  'Admin sign in',
                  style: TextStyle(fontSize: 20, fontWeight: FontWeight.w800),
                ),
                const SizedBox(height: 14),
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
                    padding: const EdgeInsets.only(top: 10),
                    child: Text(_error!, style: TextStyle(color: colors.error)),
                  ),
                const SizedBox(height: 14),
                FilledButton(
                  onPressed: _loading ? null : _login,
                  child: Text(_loading ? 'Signing in...' : 'Sign in'),
                ),
              ],
            )
          : _loading && _orders.isEmpty
          ? const Center(child: CircularProgressIndicator())
          : _error != null && _orders.isEmpty
          ? Center(
              child: Padding(
                padding: const EdgeInsets.all(24),
                child: Text(_error!, textAlign: TextAlign.center),
              ),
            )
          : _orders.isEmpty
          ? const Center(
              child: Text('No campaign orders are awaiting approval.'),
            )
          : ListView.separated(
              padding: const EdgeInsets.all(14),
              itemCount: _orders.length,
              separatorBuilder: (context, index) => const SizedBox(height: 10),
              itemBuilder: (context, index) {
                final order = _orders[index];
                final user = order['user'] is Map
                    ? Map<String, dynamic>.from(order['user'] as Map)
                    : <String, dynamic>{};
                final currency = order['currency']?.toString() ?? 'NGN';
                final amount = _asInt(order['total_minor']) / 100;
                return Card(
                  child: Padding(
                    padding: const EdgeInsets.all(15),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            Expanded(
                              child: Text(
                                order['campaign_name']?.toString() ??
                                    'Campaign',
                                style: const TextStyle(
                                  fontWeight: FontWeight.w800,
                                  fontSize: 16,
                                ),
                              ),
                            ),
                            Chip(
                              label: Text(
                                order['status']?.toString().toUpperCase() ??
                                    'PENDING',
                              ),
                            ),
                          ],
                        ),
                        Text(
                          '${_pretty(order['platform']?.toString() ?? '')} · ${_pretty(order['action']?.toString() ?? '')} · ${order['quantity']} actions',
                        ),
                        const SizedBox(height: 6),
                        Text(
                          '${user['full_name'] ?? user['username'] ?? user['email'] ?? 'User'} · ${formatMajorMoney(amount, currency)}',
                        ),
                        const SizedBox(height: 6),
                        SelectableText(
                          order['target_url']?.toString() ?? '',
                          style: TextStyle(color: colors.primary),
                        ),
                        if ((order['instructions']?.toString() ?? '')
                            .isNotEmpty)
                          Padding(
                            padding: const EdgeInsets.only(top: 7),
                            child: Text(order['instructions'].toString()),
                          ),
                        const SizedBox(height: 12),
                        Row(
                          children: [
                            Expanded(
                              child: OutlinedButton.icon(
                                onPressed: _loading
                                    ? null
                                    : () => _decide(order, 'rejected'),
                                icon: const Icon(Icons.close_rounded),
                                label: const Text('Reject & refund'),
                              ),
                            ),
                            const SizedBox(width: 10),
                            Expanded(
                              child: FilledButton.icon(
                                onPressed: _loading
                                    ? null
                                    : () => _decide(order, 'approved'),
                                icon: const Icon(Icons.check_rounded),
                                label: const Text('Approve'),
                              ),
                            ),
                          ],
                        ),
                      ],
                    ),
                  ),
                );
              },
            ),
    );
  }

  int _asInt(Object? value) =>
      value is num ? value.toInt() : int.tryParse(value?.toString() ?? '') ?? 0;
}
