import 'package:flutter/material.dart';
import 'package:share_plus/share_plus.dart';

import '../core/theme/app_theme_controller.dart';
import '../core/update/update_service.dart';
import '../services/auth_api.dart';
import '../widgets/auth_components.dart';
import 'notification_settings_screen.dart';
import 'admin_app_versions_screen.dart';
import 'admin_payment_gateways_screen.dart';
import 'admin_announcements_screen.dart';
import 'admin_reward_settings_screen.dart';
import '../services/push_notification_service.dart';
import 'legal_document_screen.dart';

class SettingsScreen extends StatefulWidget {
  const SettingsScreen({
    super.key,
    required this.api,
    required this.token,
    required this.user,
    required this.onLogout,
    required this.onDeleted,
  });
  final AuthApi api;
  final String token;
  final Map<String, dynamic> user;
  final Future<void> Function() onLogout;
  final VoidCallback onDeleted;
  @override
  State<SettingsScreen> createState() => _SettingsScreenState();
}

class _SettingsScreenState extends State<SettingsScreen> {
  final _updates = UpdateController.instance;
  List<Map<String, dynamic>> _currencies = const [];
  bool _currencyLoading = false;

  @override
  void initState() {
    super.initState();
    _loadCurrencies();
  }

  Future<void> _loadCurrencies() async {
    try {
      final response = await widget.api.getPublic('currencies');
      final data = response['data'];
      if (mounted)
        setState(
          () => _currencies = data is List
              ? data.whereType<Map<String, dynamic>>().toList()
              : const [],
        );
    } on AuthException {
      // The currency selector can retry when opened.
    }
  }

  Future<void> _chooseCurrency() async {
    if (_currencies.isEmpty) await _loadCurrencies();
    if (_currencies.isEmpty || !mounted) {
      showAuthMessage(context, 'Currency options are unavailable right now.');
      return;
    }
    final current = widget.user['preferred_currency']?.toString() ?? 'USD';
    final selected = await showModalBottomSheet<String>(
      context: context,
      showDragHandle: true,
      builder: (ctx) => SafeArea(
        child: ListView(
          shrinkWrap: true,
          children: [
            const ListTile(
              title: Text(
                'Display currency',
                style: TextStyle(fontWeight: FontWeight.w800),
              ),
            ),
            for (final item in _currencies)
              ListTile(
                title: Text(item['code']?.toString() ?? ''),
                trailing: item['code'] == current
                    ? const Icon(Icons.check_rounded)
                    : null,
                onTap: () => Navigator.pop(ctx, item['code']?.toString()),
              ),
          ],
        ),
      ),
    );
    if (selected == null || selected == current) return;
    setState(() => _currencyLoading = true);
    try {
      final result = await widget.api.patch('me', widget.token, {
        'preferred_currency': selected,
      });
      if (result['data'] is Map<String, dynamic>)
        widget.user.addAll(result['data'] as Map<String, dynamic>);
      if (mounted)
        showAuthMessage(
          context,
          'Display currency set to $selected. Campaign rewards remain in their listed currency.',
        );
    } on AuthException catch (e) {
      if (mounted) showAuthMessage(context, e.message);
    } finally {
      if (mounted) setState(() => _currencyLoading = false);
    }
  }

  void _openLegal(String slug) {
    Navigator.push(
      context,
      MaterialPageRoute<void>(
        builder: (_) => LegalDocumentScreen(api: widget.api, slug: slug),
      ),
    );
  }

  Future<void> _checkUpdate() async {
    final update = await _updates.check();
    if (!mounted) return;
    if (update == null)
      showAuthMessage(context, 'Could not check for updates. Try again later.');
    else if (!update.available)
      showAuthMessage(context, 'You are using the latest version.');
    else
      Navigator.push(
        context,
        MaterialPageRoute<void>(
          builder: (_) =>
              _UpdateDetailsScreen(controller: _updates, info: update),
        ),
      );
  }

  Future<void> _deleteAccount() async {
    final password = TextEditingController();
    final shouldDelete = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Delete account?'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'Your account will be deactivated immediately. You can contact support during the next 30 days; after that, the account and associated data are permanently deleted.',
            ),
            const SizedBox(height: 12),
            TextField(
              controller: password,
              obscureText: true,
              decoration: const InputDecoration(
                labelText: 'Current password (if set)',
              ),
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(dialogContext, true),
            style: FilledButton.styleFrom(backgroundColor: Colors.red),
            child: const Text('Delete account'),
          ),
        ],
      ),
    );
    if (shouldDelete != true) {
      password.dispose();
      return;
    }
    try {
      await widget.api.delete(
        'me',
        widget.token,
        body: {'current_password': password.text},
      );
      await PushNotificationService.instance.disconnect(
        widget.api,
        widget.token,
      );
      await widget.api.clearSession();
      if (mounted) {
        Navigator.pop(context);
        widget.onDeleted();
      }
    } on AuthException catch (e) {
      if (mounted) showAuthMessage(context, e.message);
    } finally {
      password.dispose();
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Settings')),
    body: ListView(
      padding: const EdgeInsets.all(18),
      children: [
        Card(
          child: Column(
            children: [
              ListTile(
                leading: const Icon(Icons.dark_mode_outlined),
                title: const Text('Dark theme'),
                subtitle: const Text('Choose a comfortable appearance'),
                trailing: Switch.adaptive(
                  value: AppThemeController.instance.mode == ThemeMode.dark,
                  onChanged: (value) =>
                      AppThemeController.instance.setDark(value),
                ),
              ),
              const Divider(height: 1),
              ListTile(
                leading: const Icon(Icons.notifications_outlined),
                title: const Text('Notifications'),
                subtitle: const Text('Push and email preferences'),
                trailing: const Icon(Icons.chevron_right),
                onTap: () => Navigator.push(
                  context,
                  MaterialPageRoute<void>(
                    builder: (_) => NotificationSettingsScreen(
                      api: widget.api,
                      token: widget.token,
                    ),
                  ),
                ),
              ),
              const Divider(height: 1),
              ListTile(
                leading: const Icon(Icons.currency_exchange_rounded),
                title: const Text('Display currency'),
                subtitle: Text(
                  '${widget.user['preferred_currency'] ?? 'USD'} — estimates only',
                ),
                trailing: _currencyLoading
                    ? const SizedBox(
                        width: 18,
                        height: 18,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : const Icon(Icons.chevron_right),
                onTap: _currencyLoading ? null : _chooseCurrency,
              ),
              const Divider(height: 1),
              AnimatedBuilder(
                animation: _updates,
                builder: (context, _) => ListTile(
                  leading: const Icon(Icons.system_update_alt_rounded),
                  title: const Text('Check for updates'),
                  subtitle: Text(
                    _updates.checking
                        ? 'Checking…'
                        : 'Version $appVersionName ($appBuildNumber)',
                  ),
                  trailing: _updates.checking
                      ? const SizedBox(
                          width: 18,
                          height: 18,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : const Icon(Icons.chevron_right),
                  onTap: _updates.checking ? null : _checkUpdate,
                ),
              ),
              const Divider(height: 1),
              ListTile(
                leading: const Icon(Icons.share_outlined),
                title: const Text('Share TrustLens'),
                subtitle: const Text('Invite others to join'),
                onTap: () => SharePlus.instance.share(
                  ShareParams(
                    text: 'Explore TrustLens: app feedback and sponsored sharing rewards.',
                  ),
                ),
              ),
              const Divider(height: 1),
                ListTile(
                  leading: const Icon(Icons.admin_panel_settings_outlined),
                  title: const Text('App release management'),
                  subtitle: const Text(
                    'Admin access: publish Android/iOS updates',
                  ),
                  onTap: () => Navigator.push(
                    context,
                    MaterialPageRoute<void>(
                      builder: (_) => AdminAppVersionsScreen(api: widget.api),
                    ),
                  ),
                ),
                const Divider(height: 1),
                ListTile(
                  leading: const Icon(Icons.payments_outlined),
                  title: const Text('Payment gateway settings'),
                  subtitle: const Text(
                    'Admin access: active gateways and supported currencies',
                  ),
                  onTap: () => Navigator.push(
                    context,
                    MaterialPageRoute<void>(
                      builder: (_) => AdminPaymentGatewaysScreen(api: widget.api),
                    ),
                  ),
                ),
                const Divider(height: 1),
                ListTile(
                  leading: const Icon(Icons.campaign_outlined),
                  title: const Text('Home announcements'),
                  subtitle: const Text(
                    'Admin access: create, schedule, and manage home notices',
                  ),
                  onTap: () => Navigator.push(
                    context,
                    MaterialPageRoute<void>(
                      builder: (_) => AdminAnnouncementsScreen(api: widget.api),
                    ),
                  ),
                ),
                const Divider(height: 1),
                ListTile(
                  leading: const Icon(Icons.card_giftcard_outlined),
                  title: const Text('Reward settings'),
                  subtitle: const Text(
                    'Admin access: enable or pause reward programs',
                  ),
                  onTap: () => Navigator.push(
                    context,
                    MaterialPageRoute<void>(
                      builder: (_) => AdminRewardSettingsScreen(api: widget.api),
                    ),
                  ),
                ),
                const Divider(height: 1),
              ListTile(
                leading: const Icon(Icons.privacy_tip_outlined),
                title: const Text('Privacy information'),
                onTap: () => _openLegal('privacy'),
              ),
              ListTile(
                leading: const Icon(Icons.description_outlined),
                title: const Text('Participation terms'),
                onTap: () => _openLegal('terms'),
              ),
            ],
          ),
        ),
        const SizedBox(height: 20),
        Text(
          'Account',
          style: Theme.of(context).textTheme.titleSmall
              ?.copyWith(fontWeight: FontWeight.w800),
        ),
        const SizedBox(height: 8),
        Card(
          child: Column(
            children: [
              ListTile(
                leading: const Icon(
                  Icons.logout_rounded,
                  color: Colors.redAccent,
                ),
                title: const Text('Sign out'),
                onTap: widget.onLogout,
              ),
              const Divider(height: 1),
              ListTile(
                leading: const Icon(
                  Icons.delete_forever_rounded,
                  color: Colors.redAccent,
                ),
                title: const Text(
                  'Delete account',
                  style: TextStyle(color: Colors.redAccent),
                ),
                subtitle: const Text(
                  'Remove your personal profile information',
                ),
                onTap: _deleteAccount,
              ),
            ],
          ),
        ),
        const SizedBox(height: 24),
        const Center(
          child: Text(
            'TrustLens • privacy-first task participation',
            textAlign: TextAlign.center,
          ),
        ),
      ],
    ),
  );
}

class _UpdateDetailsScreen extends StatelessWidget {
  const _UpdateDetailsScreen({required this.controller, required this.info});
  final UpdateController controller;
  final UpdateInfo info;

  Future<void> _openUpdate(BuildContext context) async {
    final opened = await controller.openDownload();
    if (!opened && context.mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('The update link is unavailable right now.'),
        ),
      );
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('App update')),
    body: ListView(
      padding: const EdgeInsets.all(22),
      children: [
        Icon(
          Icons.system_update_alt_rounded,
          size: 68,
          color: Theme.of(context).colorScheme.primary,
        ),
        const SizedBox(height: 18),
        Text(
          info.title?.isNotEmpty == true
              ? info.title!
              : 'TrustLens ${info.versionName}',
          textAlign: TextAlign.center,
          style: Theme.of(context).textTheme.headlineSmall
              ?.copyWith(fontWeight: FontWeight.w800),
        ),
        const SizedBox(height: 12),
        Card(
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Text(
              info.notes?.isNotEmpty == true
                  ? info.notes!
                  : 'The latest version includes improvements and fixes.',
            ),
          ),
        ),
        const SizedBox(height: 18),
        FilledButton.icon(
          onPressed: info.hasDownloadUrl ? () => _openUpdate(context) : null,
          icon: const Icon(Icons.download),
          label: const Text('Update now'),
        ),
      ],
    ),
  );
}
