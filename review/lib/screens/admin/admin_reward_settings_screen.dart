import 'package:flutter/material.dart';

import '../../services/auth_api.dart';
import '../../widgets/auth_components.dart';

class AdminRewardSettingsScreen extends StatefulWidget {
  const AdminRewardSettingsScreen({super.key, required this.api});
  final AuthApi api;

  @override
  State<AdminRewardSettingsScreen> createState() =>
      _AdminRewardSettingsScreenState();
}

class _AdminRewardSettingsScreenState extends State<AdminRewardSettingsScreen> {
  static const _keys = <String, String>{
    'rewards.referral_subscription_active': 'Referral subscription rewards',
    'rewards.referral_task_active': 'Referral task rewards',
    'rewards.subscription_bonus_active': 'Subscription welcome bonuses',
    'rewards.social_follow_active': 'Social follow rewards',
    'withdrawal.wallet.referral': 'Referral wallet withdrawals (available daily)',
    'withdrawal.wallet.bonus': 'Bonus wallet withdrawals',
  };
  final _email = TextEditingController();
  final _password = TextEditingController();
  final Map<String, bool> _values = {
    for (final key in _keys.keys)
      key: key == 'withdrawal.wallet.bonus' ? false : true,
  };
  String? _token;
  bool _loading = false;
  bool _saving = false;
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
      if (token == null || token.isEmpty)
        throw const AuthException(
          'Admin login did not return an access token.',
        );
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
      final response = await widget.api.get('admin/settings', token);
      final rows = response['data'];
      if (rows is List) {
        for (final row in rows.whereType<Map<String, dynamic>>()) {
          final key = row['setting_key']?.toString();
          if (!_keys.containsKey(key)) continue;
          final raw = row['value'];
          final value = raw is Map
              ? (key?.startsWith('withdrawal.wallet.') == true
                    ? raw['active']
                    : raw['enabled'])
              : raw;
          _values[key!] =
              value == true ||
              value.toString() == '1' ||
              value.toString() == 'true';
        }
      }
    } on AuthException catch (error) {
      if (mounted) setState(() => _error = error.message);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _save() async {
    final token = _token;
    if (token == null) return;
    setState(() {
      _saving = true;
      _error = null;
    });
    try {
      for (final entry in _values.entries) {
        await widget.api.put('admin/settings/${entry.key}', token, {
          'value': entry.key.startsWith('withdrawal.wallet.')
              ? {'active': entry.value}
              : entry.value,
          'is_public': false,
        });
      }
      if (mounted) showAuthMessage(context, 'Reward settings saved.');
    } on AuthException catch (error) {
      if (mounted) setState(() => _error = error.message);
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final token = _token;
    return Scaffold(
      appBar: AppBar(title: const Text('Rewards & withdrawal settings')),
      body: token == null
          ? _buildLogin()
          : _loading
          ? const Center(child: CircularProgressIndicator())
          : RefreshIndicator(
              onRefresh: _load,
              child: ListView(
                padding: const EdgeInsets.all(20),
                children: [
                  if (_error != null)
                    Padding(
                      padding: const EdgeInsets.only(bottom: 12),
                      child: Text(
                        _error!,
                        style: TextStyle(
                          color: Theme.of(context).colorScheme.error,
                        ),
                      ),
                    ),
                  const Text(
                    'Control reward programs and wallet withdrawals. Referral wallet withdrawals are available daily when enabled; bonus wallet withdrawals start disabled.',
                  ),
                  const SizedBox(height: 14),
                  for (final entry in _keys.entries)
                    SwitchListTile.adaptive(
                      contentPadding: EdgeInsets.zero,
                      title: Text(entry.value),
                      value: _values[entry.key] ?? true,
                      onChanged: (value) =>
                          setState(() => _values[entry.key] = value),
                    ),
                  const SizedBox(height: 16),
                  FilledButton.icon(
                    onPressed: _saving ? null : _save,
                    icon: const Icon(Icons.save_outlined),
                    label: Text(_saving ? 'Saving…' : 'Save settings'),
                  ),
                ],
              ),
            ),
    );
  }

  Widget _buildLogin() => ListView(
    padding: const EdgeInsets.all(24),
    children: [
      Text('Admin access', style: Theme.of(context).textTheme.headlineSmall),
      const SizedBox(height: 8),
      const Text('Sign in with an admin account to manage reward and withdrawal switches.'),
      const SizedBox(height: 18),
      TextField(
        controller: _email,
        keyboardType: TextInputType.emailAddress,
        decoration: const InputDecoration(labelText: 'Admin email'),
      ),
      const SizedBox(height: 12),
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
      const SizedBox(height: 18),
      FilledButton(
        onPressed: _loading ? null : _login,
        child: Text(_loading ? 'Signing in…' : 'Sign in'),
      ),
    ],
  );
}
