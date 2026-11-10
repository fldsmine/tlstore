import 'package:flutter/material.dart';

import '../services/auth_api.dart';
import '../widgets/auth_components.dart';

class SocialAccountsSetupScreen extends StatefulWidget {
  const SocialAccountsSetupScreen({super.key, required this.api, required this.token, this.focusPlatform});
  final AuthApi api;
  final String token;
  final String? focusPlatform;

  @override
  State<SocialAccountsSetupScreen> createState() => _SocialAccountsSetupScreenState();
}

class _SocialAccountsSetupScreenState extends State<SocialAccountsSetupScreen> {
  static const _platforms = <(String, String, String)>[
    ('whatsapp', 'WhatsApp', 'Phone number or profile link'),
    ('facebook', 'Facebook', 'Username or profile link'),
    ('instagram', 'Instagram', 'Username or profile link'),
    ('x', 'X (Twitter)', 'Username or profile link'),
    ('tiktok', 'TikTok', 'Username or profile link'),
    ('youtube', 'YouTube', 'Username or channel link'),
    ('telegram', 'Telegram', 'Username or profile link'),
  ];
  final Map<String, TextEditingController> _controllers = {};
  final Map<String, bool> _saving = {};
  Map<String, String> _accounts = {};
  bool _loading = true;

  List<(String, String, String)> get _orderedPlatforms {
    final focus = widget.focusPlatform;
    if (focus == null) return _platforms;
    return [..._platforms]..sort((a, b) => (a.$1 == focus ? 0 : 1).compareTo(b.$1 == focus ? 0 : 1));
  }

  @override
  void initState() { super.initState(); _load(); }
  @override
  void dispose() { for (final controller in _controllers.values) { controller.dispose(); } super.dispose(); }

  Future<void> _load() async {
    try { final accounts = await widget.api.socialAccounts(widget.token); if (!mounted) return; setState(() { _accounts = accounts; _loading = false; }); }
    on AuthException catch (error) { if (mounted) { setState(() => _loading = false); showAuthMessage(context, error.message); } }
  }

  Future<void> _save(String platform) async {
    final value = _controllers.putIfAbsent(platform, TextEditingController.new).text.trim();
    if (value.isEmpty) { showAuthMessage(context, 'Enter your ${_label(platform)} account or link.'); return; }
    setState(() => _saving[platform] = true);
    try {
      final response = await widget.api.setSocialAccount(widget.token, platform, value);
      final data = response['data'];
      if (!mounted) return;
      setState(() { _accounts[platform] = data is Map ? data['handle'].toString() : value; _saving[platform] = false; });
      showAuthMessage(context, '${_label(platform)} account saved. It cannot be changed later.');
    } on AuthException catch (error) { if (mounted) { setState(() => _saving[platform] = false); showAuthMessage(context, error.message); } }
  }

  String _label(String platform) => _platforms.firstWhere((row) => row.$1 == platform).$2;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    return Scaffold(
      appBar: AppBar(title: const Text('Social accounts')),
      body: _loading ? const Center(child: CircularProgressIndicator()) : ListView(
        padding: const EdgeInsets.all(18),
        children: [
          Text('Add the social accounts you use for tasks. Each account can be saved once and is then locked.', style: Theme.of(context).textTheme.bodyMedium),
          const SizedBox(height: 14),
          for (final platform in _orderedPlatforms)
            Card(
              margin: const EdgeInsets.only(bottom: 10),
              child: Padding(
                padding: const EdgeInsets.all(14),
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Row(children: [Icon(Icons.public_rounded, color: colors.primary), const SizedBox(width: 10), Expanded(child: Text(platform.$2, style: const TextStyle(fontWeight: FontWeight.w800))), if ((_accounts[platform.$1] ?? '').isNotEmpty) Icon(Icons.lock_rounded, size: 18, color: colors.onSurfaceVariant)]),
                  const SizedBox(height: 10),
                  if ((_accounts[platform.$1] ?? '').isNotEmpty)
                    SelectableText(_accounts[platform.$1]!, style: Theme.of(context).textTheme.bodyLarge)
                  else ...[
                    TextField(controller: _controllers.putIfAbsent(platform.$1, TextEditingController.new), decoration: InputDecoration(labelText: platform.$3, border: const OutlineInputBorder(), isDense: true)),
                    const SizedBox(height: 9),
                    Align(alignment: Alignment.centerRight, child: FilledButton(onPressed: _saving[platform.$1] == true ? null : () => _save(platform.$1), child: _saving[platform.$1] == true ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2)) : const Text('Save account'))),
                  ],
                ]),
              ),
            ),
        ],
      ),
    );
  }
}
