import 'package:flutter/material.dart';

import '../services/auth_api.dart';

class AdminUserRankingsScreen extends StatefulWidget {
  const AdminUserRankingsScreen({super.key, required this.api});
  final AuthApi api;

  @override
  State<AdminUserRankingsScreen> createState() => _AdminUserRankingsScreenState();
}

class _AdminUserRankingsScreenState extends State<AdminUserRankingsScreen> {
  final _email = TextEditingController();
  final _password = TextEditingController();
  String? _token;
  String? _error;
  bool _loading = false;
  bool _enabled = false;
  List<Map<String, dynamic>> _levels = [];

  @override
  void dispose() { _email.dispose(); _password.dispose(); super.dispose(); }

  Future<void> _login() async {
    setState(() { _loading = true; _error = null; });
    try {
      final response = await widget.api.postPublic('admin/auth/login', {'email': _email.text.trim(), 'password': _password.text});
      final token = response['token']?.toString();
      if (token == null || token.isEmpty) throw const AuthException('Admin login did not return an access token.');
      _token = token;
      await _load();
    } on AuthException catch (error) { if (mounted) setState(() => _error = error.message); }
    finally { if (mounted) setState(() => _loading = false); }
  }

  Future<void> _load() async {
    final token = _token;
    if (token == null) return;
    setState(() { _loading = true; _error = null; });
    try {
      final results = await Future.wait([
        widget.api.get('admin/rank-levels', token),
        widget.api.get('admin/settings', token),
      ]);
      final rows = results[0]['data'];
      final settings = results[1]['data'];
      var enabled = false;
      if (settings is List) {
        for (final row in settings.whereType<Map<String, dynamic>>()) {
          if (row['setting_key'] == 'rewards.user_ranking_active') {
            final raw = row['value'] is Map ? row['value']['enabled'] : row['value'];
            enabled = raw == true || raw.toString() == '1' || raw.toString() == 'true';
          }
        }
      }
      if (mounted) setState(() {
        _enabled = enabled;
        _levels = rows is List ? rows.whereType<Map<String, dynamic>>().toList() : [];
      });
    } on AuthException catch (error) { if (mounted) setState(() => _error = error.message); }
    finally { if (mounted) setState(() => _loading = false); }
  }

  Future<void> _setEnabled(bool value) async {
    final token = _token;
    if (token == null) return;
    setState(() => _enabled = value);
    try {
      await widget.api.put('admin/settings/rewards.user_ranking_active', token, {'value': value, 'is_public': true});
    } on AuthException catch (error) {
      if (mounted) { setState(() => _enabled = !value); _message(error.message); }
    }
  }

  Future<void> _edit(Map<String, dynamic> item) async {
    final token = _token;
    if (token == null) return;
    final fields = <String, TextEditingController>{
      'level': TextEditingController(text: '${item['level']}'),
      'name': TextEditingController(text: '${item['name'] ?? ''}'),
      'icon': TextEditingController(text: '${item['icon'] ?? ''}'),
      'minimum_subscribed_referrals': TextEditingController(text: '${item['minimum_subscribed_referrals'] ?? 0}'),
      'minimum_referral_cash_inflow_usd': TextEditingController(text: '${item['minimum_referral_cash_inflow_usd'] ?? 0}'),
      'minimum_user_cash_inflow_usd': TextEditingController(text: '${item['minimum_user_cash_inflow_usd'] ?? 0}'),
      'monthly_reward_usd': TextEditingController(text: '${item['monthly_reward_usd'] ?? 0}'),
      'reward_duration_months': TextEditingController(text: '${item['reward_duration_months'] ?? 1}'),
      'sort_order': TextEditingController(text: '${item['sort_order'] ?? item['level']}'),
    };
    var active = item['is_active'] == true;
    final save = await showDialog<bool>(context: context, builder: (dialogContext) => StatefulBuilder(builder: (context, setDialogState) => AlertDialog(
      title: Text('Edit ${item['name'] ?? 'rank'} level'),
      content: SizedBox(width: 480, child: SingleChildScrollView(child: Column(mainAxisSize: MainAxisSize.min, children: [
        for (final entry in fields.entries) Padding(padding: const EdgeInsets.only(bottom: 10), child: TextField(controller: entry.value, keyboardType: entry.key == 'name' || entry.key == 'icon' ? TextInputType.text : const TextInputType.numberWithOptions(decimal: true), decoration: InputDecoration(labelText: _label(entry.key)))),
        SwitchListTile.adaptive(contentPadding: EdgeInsets.zero, title: const Text('Level active'), value: active, onChanged: (value) => setDialogState(() => active = value)),
      ]))),
      actions: [TextButton(onPressed: () => Navigator.pop(dialogContext, false), child: const Text('Cancel')), FilledButton(onPressed: () => Navigator.pop(dialogContext, true), child: const Text('Save'))],
    )));
    if (save != true) { for (final controller in fields.values) { controller.dispose(); } return; }
    try {
      final payload = <String, dynamic>{};
      for (final entry in fields.entries) {
        final key = entry.key;
        payload[key] = ['level', 'minimum_subscribed_referrals', 'reward_duration_months', 'sort_order'].contains(key)
            ? int.tryParse(entry.value.text.trim())
            : ['minimum_referral_cash_inflow_usd', 'minimum_user_cash_inflow_usd', 'monthly_reward_usd'].contains(key)
                ? double.tryParse(entry.value.text.trim())
                : entry.value.text.trim().isEmpty ? null : entry.value.text.trim();
      }
      payload['is_active'] = active;
      await widget.api.patch('admin/rank-levels/${item['id']}', token, payload);
      await _load();
      if (mounted) _message('Ranking level saved.');
    } on AuthException catch (error) { if (mounted) _message(error.message); }
    finally { for (final controller in fields.values) { controller.dispose(); } }
  }

  String _label(String key) => switch (key) {
    'level' => 'Level number', 'icon' => 'Icon image URL/path',
    'minimum_subscribed_referrals' => 'Minimum subscribed referrals',
    'minimum_referral_cash_inflow_usd' => 'Referral inflow requirement (USD)',
    'minimum_user_cash_inflow_usd' => 'Personal inflow requirement (USD)',
    'monthly_reward_usd' => 'Monthly reward (USD)',
    'reward_duration_months' => 'Reward duration (months)',
    'sort_order' => 'Display order', _ => 'Name',
  };

  void _message(String text) => ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(text)));


@override
Widget build(BuildContext context) {
  return Scaffold(
    appBar: AppBar(
      title: const Text('User ranking management'),
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
        : _loading
            ? const Center(child: CircularProgressIndicator())
            : RefreshIndicator(
                onRefresh: _load,
                child: ListView(
                  padding: const EdgeInsets.all(16),
                  children: [
                    if (_error != null)
                      Padding(
                        padding: const EdgeInsets.only(bottom: 10),
                        child: Text(
                          _error!,
                          style: TextStyle(
                            color: Theme.of(context).colorScheme.error,
                          ),
                        ),
                      ),

                    Card(
                      child: SwitchListTile.adaptive(
                        title: const Text('Ranking rewards system'),
                        subtitle: const Text(
                          'Enable eligibility checks and monthly reward payouts',
                        ),
                        value: _enabled,
                        onChanged: _setEnabled,
                      ),
                    ),

                    const SizedBox(height: 8),

                    for (final level in _levels)
                      Card(
                        child: ListTile(
                          leading: CircleAvatar(
                            child: Text('${level['level']}'),
                          ),
                          title: Text(
                            '${level['name']} · \$${level['monthly_reward_usd']}/month',
                          ),
                          subtitle: Text(
                            '${level['minimum_subscribed_referrals']} subscribed referrals · '
                            '\$${level['minimum_referral_cash_inflow_usd']} referral inflow · '
                            '${level['reward_duration_months']} months · '
                            '${level['qualifications_count'] ?? 0} qualified',
                          ),
                          trailing: Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Icon(
                                level['is_active'] == true
                                    ? Icons.check_circle
                                    : Icons.pause_circle,
                                color: level['is_active'] == true
                                    ? Theme.of(context).colorScheme.primary
                                    : Theme.of(context).colorScheme.error,
                              ),
                              IconButton(
                                onPressed: () => _edit(level),
                                icon: const Icon(Icons.edit_outlined),
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


  Widget _loginForm() => ListView(padding: const EdgeInsets.all(24), children: [
    Text('Admin access', style: Theme.of(context).textTheme.headlineSmall),
    const SizedBox(height: 8), const Text('Sign in to manage ranking level requirements and payouts.'),
    const SizedBox(height: 16), TextField(controller: _email, keyboardType: TextInputType.emailAddress, decoration: const InputDecoration(labelText: 'Admin email')),
    const SizedBox(height: 12), TextField(controller: _password, obscureText: true, decoration: const InputDecoration(labelText: 'Password')),
    if (_error != null) Padding(padding: const EdgeInsets.only(top: 12), child: Text(_error!, style: TextStyle(color: Theme.of(context).colorScheme.error))),
    const SizedBox(height: 18), FilledButton(onPressed: _loading ? null : _login, child: Text(_loading ? 'Signing in…' : 'Sign in')),
  ]);
}
