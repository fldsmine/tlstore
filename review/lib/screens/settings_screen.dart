import 'package:flutter/material.dart';
import 'package:share_plus/share_plus.dart';

import '../config/admin_ui.dart';
import '../core/theme/app_theme_controller.dart';
import '../core/update/update_service.dart';
import '../core/utils/helper.dart';
import '../services/auth_api.dart';
import '../widgets/auth_components.dart';
import 'notification_settings_screen.dart';
import '../services/push_notification_service.dart';
import 'admin/admin_app_versions_screen.dart';
import 'admin/admin_payment_gateways_screen.dart';
import 'admin/admin_announcements_screen.dart';
import 'admin/admin_reward_settings_screen.dart';
import 'admin/admin_user_rankings_screen.dart';
import 'admin/admin_package_addons_screen.dart';
import 'admin/admin_subscription_plans_screen.dart';
import 'admin/admin_moderation_screen.dart';
import 'admin/admin_app_reports_screen.dart';
import 'admin/admin_users_screen.dart';
import 'admin/admin_campaign_orders_screen.dart';
import 'admin/admin_app_listings_screen.dart';
import 'admin/admin_review_templates_screen.dart';
import 'admin/admin_ads_screen.dart';
import 'admin/admin_frontend_content_screen.dart';
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
      final response = await widget.api.appCurrencies();
      final data = response['data'];
      if (!mounted) return;
      setState(() {
        _currencies = data is List
            ? data.whereType<Map<String, dynamic>>().toList()
            : const [];
      });
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

    // Only display currencies supported by the application.
    final apiCodes = _currencies
        .map((item) => item['code']?.toString().toUpperCase())
        .whereType<String>()
        .toSet();

    final availableCurrencies = supportedCurrencies
        .where((currency) => apiCodes.contains(currency.code))
        .toList();

    if (availableCurrencies.isEmpty) {
      showAuthMessage(context, 'Currency options are unavailable right now.');
      return;
    }

    final selected = await showModalBottomSheet<String>(
      context: context,
      showDragHandle: true,
      isScrollControlled: true,
      backgroundColor: Theme.of(context).colorScheme.surface,
      builder: (sheetContext) {
        final theme = Theme.of(sheetContext);
        final colors = theme.colorScheme;

        return StatefulBuilder(
          builder: (context, setSheetState) {
            String searchQuery = '';

            return CurrencyPickerContent(
              currencies: availableCurrencies,
              current: current,
              onSelect: (code) {
                Navigator.pop(context, code);
              },
            );
          },
        );
      },
    );

    if (selected == null || selected == current || !mounted) return;
    setState(() => _currencyLoading = true);
    try {
      final result = await widget.api.patch('me', widget.token, {
        'preferred_currency': selected,
      });
      if (result['data'] is Map<String, dynamic>) {
        widget.user.addAll(result['data'] as Map<String, dynamic>);
      }
      widget.api.setPreferredCurrency(selected);
      if (mounted) {
        setState(() {});
        showAuthMessage(
          context,
          'Preferred currency updated to ${selected.toUpperCase()}.',
        );
      }
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
    if (update == null) {
      showAuthMessage(context, 'Could not check for updates. Try again later.');
    } else if (!update.available) {
      showAuthMessage(context, 'You are using the latest version.');
    } else {
      Navigator.push(
        context,
        MaterialPageRoute<void>(
          builder: (_) =>
              _UpdateDetailsScreen(controller: _updates, info: update),
        ),
      );
    }
  }

  Future<void> _deleteAccount() async {
    final password = TextEditingController();
    final shouldDelete = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        icon: Icon(
          Icons.warning_amber_rounded,
          color: Theme.of(dialogContext).colorScheme.error,
          size: 34,
        ),
        title: const Text('Delete account?'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'Your account will be deactivated immediately. You can contact support during the next 30 days; after that, the account and associated data are permanently deleted.',
            ),
            const SizedBox(height: 16),
            TextField(
              controller: password,
              obscureText: true,
              decoration: const InputDecoration(
                labelText: 'Current password (if set)',
                prefixIcon: Icon(Icons.lock_outline_rounded),
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
            style: FilledButton.styleFrom(
              backgroundColor: Theme.of(dialogContext).colorScheme.error,
              foregroundColor: Theme.of(dialogContext).colorScheme.onError,
            ),
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

  Future<void> _shareApp() async {
    await SharePlus.instance.share(
      ShareParams(
        text: 'Explore TrustLens: app feedback and sponsored sharing rewards.',
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final currency = widget.user['preferred_currency']?.toString() ?? 'USD';

    return Scaffold(
      appBar: AppBar(title: const Text('Settings'), centerTitle: true),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(18, 12, 18, 30),
        children: [
          const _SectionHeading(
            title: 'APPEARANCE',
            icon: Icons.palette_outlined,
          ),
          const SizedBox(height: 12),
          _Panel(
            child: Column(
              children: [
                Padding(
                  padding: const EdgeInsets.fromLTRB(18, 20, 18, 16),
                  child: Row(
                    children: [
                      _IconTile(
                        icon: Icons.palette_rounded,
                        background: scheme.primaryContainer,
                        foreground: scheme.onPrimaryContainer,
                        size: 54,
                      ),
                      const SizedBox(width: 14),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              'Theme',
                              style: theme.textTheme.titleLarge?.copyWith(
                                fontWeight: FontWeight.w800,
                              ),
                            ),
                            const SizedBox(height: 3),
                            Text(
                              'Choose your appearance',
                              style: theme.textTheme.bodyMedium?.copyWith(
                                color: scheme.onSurfaceVariant,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
                Padding(
                  padding: const EdgeInsets.fromLTRB(14, 0, 14, 18),
                  child: AnimatedBuilder(
                    animation: AppThemeController.instance,
                    builder: (context, _) {
                      final mode = AppThemeController.instance.mode;
                      return Row(
                        children: [
                          Expanded(
                            child: _ThemeChoice(
                              label: 'Light',
                              icon: Icons.light_mode_rounded,
                              selected: mode == ThemeMode.light,
                              onTap: () =>
                                  AppThemeController.instance.setDark(false),
                            ),
                          ),
                          const SizedBox(width: 10),
                          Expanded(
                            child: _ThemeChoice(
                              label: 'Dark',
                              icon: Icons.dark_mode_rounded,
                              selected: mode == ThemeMode.dark,
                              onTap: () =>
                                  AppThemeController.instance.setDark(true),
                            ),
                          ),
                          const SizedBox(width: 10),
                          Expanded(
                            child: _ThemeChoice(
                              label: 'System',
                              icon: Icons.settings_brightness_rounded,
                              selected: mode == ThemeMode.system,
                              enabled: false,
                              onTap: () {},
                            ),
                          ),
                        ],
                      );
                    },
                  ),
                ),
                Divider(height: 1, color: scheme.outlineVariant),
                Padding(
                  padding: const EdgeInsets.fromLTRB(18, 16, 18, 16),
                  child: Row(
                    children: [
                      _IconTile(
                        icon: Icons.color_lens_outlined,
                        background: scheme.secondaryContainer,
                        foreground: scheme.onSecondaryContainer,
                        size: 46,
                      ),
                      const SizedBox(width: 14),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              'Accent color',
                              style: theme.textTheme.titleMedium?.copyWith(
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                            const SizedBox(height: 3),
                            Text(
                              'More color options coming soon',
                              style: theme.textTheme.bodySmall?.copyWith(
                                color: scheme.onSurfaceVariant,
                              ),
                            ),
                          ],
                        ),
                      ),
                      Icon(Icons.auto_awesome_rounded, color: scheme.primary),
                    ],
                  ),
                ),
              ],
            ),
          ),

          const SizedBox(height: 28),
          const _SectionHeading(title: 'PREFERENCES', icon: Icons.tune_rounded),
          const SizedBox(height: 12),
          _Panel(
            child: Column(
              children: [
                _SettingsTile(
                  icon: Icons.currency_exchange_rounded,
                  title: 'Display currency',
                  subtitle: '$currency  ·  Estimates only',
                  trailing: _currencyLoading
                      ? const SizedBox(
                          width: 20,
                          height: 20,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : null,
                  onTap: _currencyLoading ? null : _chooseCurrency,
                ),
                const _PanelDivider(),
                AnimatedBuilder(
                  animation: _updates,
                  builder: (context, _) => _SettingsTile(
                    icon: Icons.system_update_alt_rounded,
                    title: 'App updates',
                    subtitle: _updates.checking
                        ? 'Checking for updates…'
                        : 'Version $appVersionName ($appBuildNumber)',
                    trailing: _updates.checking
                        ? const SizedBox(
                            width: 20,
                            height: 20,
                            child: CircularProgressIndicator(strokeWidth: 2),
                          )
                        : null,
                    onTap: _updates.checking ? null : _checkUpdate,
                  ),
                ),
              ],
            ),
          ),

          const SizedBox(height: 28),
          const _SectionHeading(
            title: 'SHARE & CONNECT',
            icon: Icons.ios_share_rounded,
          ),
          const SizedBox(height: 12),
          _Panel(
            child: Column(
              children: [
                _SettingsTile(
                  icon: Icons.share_rounded,
                  title: 'Share TrustLens',
                  subtitle: 'Invite others to join',
                  onTap: _shareApp,
                ),
                const _PanelDivider(),
                _SettingsTile(
                  icon: Icons.notifications_active_outlined,
                  title: 'Notification settings',
                  subtitle: 'Push and email preferences',
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
              ],
            ),
          ),

          const SizedBox(height: 28),
          const _SectionHeading(title: 'LEGAL', icon: Icons.gavel_rounded),
          const SizedBox(height: 12),
          _Panel(
            child: Column(
              children: [
                _SettingsTile(
                  icon: Icons.privacy_tip_outlined,
                  title: 'Privacy information',
                  subtitle: 'How your information is handled',
                  onTap: () => _openLegal('privacy'),
                ),
                const _PanelDivider(),
                _SettingsTile(
                  icon: Icons.description_outlined,
                  title: 'Participation terms',
                  subtitle: 'Rules and conditions for using TrustLens',
                  onTap: () => _openLegal('terms'),
                ),
              ],
            ),
          ),

          if (kEnableAdminUi) ...[
            const SizedBox(height: 28),
            const _SectionHeading(
              title: 'ADMIN PAGES',
              icon: Icons.admin_panel_settings_outlined,
            ),
            const SizedBox(height: 12),
            _Panel(
              child: Column(
                children: [
                  _SettingsTile(
                    icon: Icons.web_rounded,
                    title: 'Landing page content',
                    subtitle: 'Admin access: edit the public hero, features, contact details and how-it-works copy',
                    onTap: () => Navigator.push(
                      context,
                      MaterialPageRoute<void>(
                        builder: (_) =>
                            AdminFrontendContentScreen(api: widget.api),
                      ),
                    ),
                  ),
                  const _PanelDivider(),
                  _SettingsTile(
                    icon: Icons.app_registration_outlined,
                    title: 'App listings and review questions',
                    subtitle: 'Admin access: create apps, campaigns, screenshots and assign questionnaires',
                    onTap: () => Navigator.push(
                      context,
                      MaterialPageRoute<void>(
                        builder: (_) => AdminAppListingsScreen(api: widget.api),
                      ),
                    ),
                  ),
                  const _PanelDivider(),
                  _SettingsTile(
                    icon: Icons.quiz_outlined,
                    title: 'Questionnaire templates',
                    subtitle: 'Admin access: manage question bank, versions and publishing',
                    onTap: () => Navigator.push(
                      context,
                      MaterialPageRoute<void>(
                        builder: (_) =>
                            AdminReviewTemplatesScreen(api: widget.api),
                      ),
                    ),
                  ),
                  const _PanelDivider(),
                  _SettingsTile(
                    icon: Icons.campaign_rounded,
                    title: 'Ad listings',
                    subtitle: 'Admin access: create, edit, approve and remove ad campaigns',
                    onTap: () => Navigator.push(
                      context,
                      MaterialPageRoute<void>(
                        builder: (_) => AdminAdsScreen(api: widget.api),
                      ),
                    ),
                  ),
                  const _PanelDivider(),
                  _SettingsTile(
                    icon: Icons.admin_panel_settings_outlined,
                    title: 'App release management',
                    subtitle: 'Admin access: publish Android/iOS updates',
                    onTap: () => Navigator.push(
                      context,
                      MaterialPageRoute<void>(
                        builder: (_) => AdminAppVersionsScreen(api: widget.api),
                      ),
                    ),
                  ),
                  const _PanelDivider(),
                  _SettingsTile(
                    icon: Icons.payments_outlined,
                    title: 'Payment gateway settings',
                    subtitle:
                        'Admin access: active gateways and supported currencies',
                    onTap: () => Navigator.push(
                      context,
                      MaterialPageRoute<void>(
                        builder: (_) =>
                            AdminPaymentGatewaysScreen(api: widget.api),
                      ),
                    ),
                  ),

                  const _PanelDivider(),
                  _SettingsTile(
                    icon: Icons.campaign_outlined,
                    title: 'Home announcements',
                    subtitle:
                        'Admin access: create, schedule, and manage home notices',
                    onTap: () => Navigator.push(
                      context,
                      MaterialPageRoute<void>(
                        builder: (_) => AdminAnnouncementsScreen(api: widget.api),
                      ),
                    ),
                  ),

                  const _PanelDivider(),
                  _SettingsTile(
                    icon: Icons.flag_outlined,
                    title: 'App listing reports',
                    subtitle: 'Admin access: review user reports about apps',
                    onTap: () => Navigator.push(
                      context,
                      MaterialPageRoute<void>(
                        builder: (_) => AdminAppReportsScreen(api: widget.api),
                      ),
                    ),
                  ),

                  const _PanelDivider(),
                  _SettingsTile(
                    icon: Icons.campaign_outlined,
                    title: 'Campaign approvals',
                    subtitle: 'Admin access: review paid user campaigns and refund rejected orders',
                    onTap: () => Navigator.push(
                      context,
                      MaterialPageRoute<void>(
                        builder: (_) =>
                            AdminCampaignOrdersScreen(api: widget.api),
                      ),
                    ),
                  ),

                  const _PanelDivider(),
                  _SettingsTile(
                    icon: Icons.manage_accounts_outlined,
                    title: 'User management',
                    subtitle:
                        'Admin access: accounts, wallets, bans and passwords',
                    onTap: () => Navigator.push(
                      context,
                      MaterialPageRoute<void>(
                        builder: (_) => AdminUsersScreen(api: widget.api),
                      ),
                    ),
                  ),

                  const _PanelDivider(),
                  _SettingsTile(
                    icon: Icons.card_giftcard_outlined,
                    title: 'Reward settings',
                    subtitle: 'Admin access: enable or pause reward programs',
                    onTap: () => Navigator.push(
                      context,
                      MaterialPageRoute<void>(
                        builder: (_) =>
                            AdminRewardSettingsScreen(api: widget.api),
                      ),
                    ),
                  ),

                  const _PanelDivider(),
                  _SettingsTile(
                    icon: Icons.military_tech_outlined,
                    title: 'User ranking management',
                    subtitle:
                        'Admin access: ranks, requirements, and monthly rewards',
                    onTap: () => Navigator.push(
                      context,
                      MaterialPageRoute<void>(
                        builder: (_) => AdminUserRankingsScreen(api: widget.api),
                      ),
                    ),
                  ),
                  const _PanelDivider(),
                  _SettingsTile(
                    icon: Icons.workspace_premium_outlined,
                    title: 'Subscription plans',
                    subtitle: 'Admin access: create, edit, activate plans and review subscribers',
                    onTap: () => Navigator.push(
                      context,
                      MaterialPageRoute<void>(
                        builder: (_) =>
                            AdminSubscriptionPlansScreen(api: widget.api),
                      ),
                    ),
                  ),
                  const _PanelDivider(),
                  _SettingsTile(
                    icon: Icons.fact_check_outlined,
                    title: 'Task moderation',
                    subtitle: 'Admin access: review tasks, ad shares, deposits and withdrawals',
                    onTap: () => Navigator.push(
                      context,
                      MaterialPageRoute<void>(
                        builder: (_) => AdminModerationScreen(api: widget.api),
                      ),
                    ),
                  ),
                  const _PanelDivider(),
                  _SettingsTile(
                    icon: Icons.add_box_outlined,
                    title: 'Review package add-ons',
                    subtitle: 'Admin access: monthly prices and availability',
                    onTap: () => Navigator.push(
                      context,
                      MaterialPageRoute<void>(
                        builder: (_) => AdminPackageAddonsScreen(api: widget.api),
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ],

          const SizedBox(height: 28),
          const _SectionHeading(
            title: 'ACCOUNT',
            icon: Icons.manage_accounts_outlined,
          ),
          const SizedBox(height: 12),
          _Panel(
            child: Column(
              children: [
                _SettingsTile(
                  icon: Icons.logout_rounded,
                  title: 'Sign out',
                  subtitle: 'Sign out of this device',
                  iconColor: scheme.error,
                  onTap: widget.onLogout,
                ),
                const _PanelDivider(),
                _SettingsTile(
                  icon: Icons.delete_forever_outlined,
                  title: 'Delete account',
                  subtitle: 'Deactivate and permanently remove your account',
                  iconColor: scheme.error,
                  titleColor: scheme.error,
                  showChevron: false,
                  onTap: _deleteAccount,
                ),
              ],
            ),
          ),

          const SizedBox(height: 28),
          Center(
            child: Column(
              children: [
                Icon(
                  Icons.shield_moon_outlined,
                  size: 25,
                  color: scheme.primary,
                ),
                const SizedBox(height: 8),
                Text(
                  'TrustLens',
                  style: theme.textTheme.titleSmall?.copyWith(
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 3),
                Text(
                  'Privacy-first task participation',
                  textAlign: TextAlign.center,
                  style: theme.textTheme.bodySmall?.copyWith(
                    color: scheme.onSurfaceVariant,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _SectionHeading extends StatelessWidget {
  const _SectionHeading({required this.title, required this.icon});
  final String title;
  final IconData icon;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return Row(
      children: [
        Container(
          width: 4,
          height: 21,
          decoration: BoxDecoration(
            color: scheme.primary,
            borderRadius: BorderRadius.circular(10),
          ),
        ),
        const SizedBox(width: 10),
        Icon(icon, size: 17, color: scheme.primary),
        const SizedBox(width: 8),
        Text(
          title,
          style: Theme.of(context).textTheme.labelLarge?.copyWith(
            color: scheme.onSurfaceVariant,
            fontWeight: FontWeight.w800,
            letterSpacing: 1.05,
          ),
        ),
      ],
    );
  }
}

class _Panel extends StatelessWidget {
  const _Panel({required this.child});
  final Widget child;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return Card(
      margin: EdgeInsets.zero,
      elevation: 0,
      clipBehavior: Clip.antiAlias,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(22),
        side: BorderSide(color: scheme.outlineVariant.withValues(alpha: 0.45)),
      ),
      child: child,
    );
  }
}

class _IconTile extends StatelessWidget {
  const _IconTile({
    required this.icon,
    required this.background,
    required this.foreground,
    this.size = 48,
  });
  final IconData icon;
  final Color background;
  final Color foreground;
  final double size;

  @override
  Widget build(BuildContext context) => Container(
    width: size,
    height: size,
    decoration: BoxDecoration(
      color: background,
      borderRadius: BorderRadius.circular(15),
    ),
    child: Icon(icon, color: foreground, size: size * 0.52),
  );
}

class _SettingsTile extends StatelessWidget {
  const _SettingsTile({
    required this.icon,
    required this.title,
    required this.subtitle,
    this.onTap,
    this.trailing,
    this.iconColor,
    this.titleColor,
    this.showChevron = true,
  });

  final IconData icon;
  final String title;
  final String subtitle;
  final VoidCallback? onTap;
  final Widget? trailing;
  final Color? iconColor;
  final Color? titleColor;
  final bool showChevron;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    return ListTile(
      contentPadding: const EdgeInsets.symmetric(horizontal: 17, vertical: 7),
      minVerticalPadding: 10,
      leading: _IconTile(
        icon: icon,
        background: scheme.secondaryContainer,
        foreground: iconColor ?? scheme.onSecondaryContainer,
        size: 47,
      ),
      title: Text(
        title,
        style: theme.textTheme.titleMedium?.copyWith(
          color: titleColor,
          fontWeight: FontWeight.w700,
        ),
      ),
      subtitle: Padding(
        padding: const EdgeInsets.only(top: 4),
        child: Text(
          subtitle,
          style: theme.textTheme.bodySmall?.copyWith(
            color: scheme.onSurfaceVariant,
            height: 1.35,
          ),
        ),
      ),
      trailing:
          trailing ??
          (showChevron
              ? Icon(
                  Icons.chevron_right_rounded,
                  color: scheme.onSurfaceVariant,
                )
              : null),
      onTap: onTap,
    );
  }
}

class _PanelDivider extends StatelessWidget {
  const _PanelDivider();

  @override
  Widget build(BuildContext context) => Divider(
    height: 1,
    indent: 78,
    color: Theme.of(context).colorScheme.outlineVariant.withValues(alpha: 0.55),
  );
}

class _ThemeChoice extends StatelessWidget {
  const _ThemeChoice({
    required this.label,
    required this.icon,
    required this.selected,
    required this.onTap,
    this.enabled = true,
  });

  final String label;
  final IconData icon;
  final bool selected;
  final bool enabled;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return Semantics(
      button: true,
      selected: selected,
      enabled: enabled,
      label: '$label theme',
      child: InkWell(
        onTap: enabled ? onTap : null,
        borderRadius: BorderRadius.circular(18),
        child: AnimatedContainer(
          duration: const Duration(milliseconds: 180),
          constraints: const BoxConstraints(minHeight: 112),
          padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 14),
          decoration: BoxDecoration(
            color: selected
                ? scheme.primaryContainer
                : scheme.surfaceContainerHighest,
            borderRadius: BorderRadius.circular(18),
            border: Border.all(
              color: selected ? scheme.primary : scheme.outlineVariant,
              width: selected ? 2 : 1,
            ),
          ),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Icon(
                icon,
                size: 30,
                color: selected
                    ? scheme.onPrimaryContainer
                    : scheme.onSurfaceVariant,
              ),
              const SizedBox(height: 9),
              Text(
                label,
                textAlign: TextAlign.center,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: Theme.of(context).textTheme.labelLarge?.copyWith(
                  color: selected
                      ? scheme.onPrimaryContainer
                      : scheme.onSurfaceVariant,
                  fontWeight: selected ? FontWeight.w800 : FontWeight.w500,
                ),
              ),
              if (!enabled) ...[
                const SizedBox(height: 3),
                Text(
                  'Unavailable',
                  style: Theme.of(context).textTheme.labelSmall
                      ?.copyWith(color: scheme.onSurfaceVariant),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
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
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    return Scaffold(
      appBar: AppBar(title: const Text('App update')),
      body: ListView(
        padding: const EdgeInsets.all(22),
        children: [
          Container(
            width: 88,
            height: 88,
            decoration: BoxDecoration(
              color: scheme.primaryContainer,
              borderRadius: BorderRadius.circular(26),
            ),
            child: Icon(
              Icons.system_update_alt_rounded,
              size: 46,
              color: scheme.onPrimaryContainer,
            ),
          ),
          const SizedBox(height: 22),
          Text(
            info.title?.isNotEmpty == true
                ? info.title!
                : 'TrustLens ${info.versionName}',
            textAlign: TextAlign.center,
            style: theme.textTheme.headlineSmall?.copyWith(
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 16),
          Card(
            child: Padding(
              padding: const EdgeInsets.all(18),
              child: Text(
                info.notes?.isNotEmpty == true
                    ? info.notes!
                    : 'The latest version includes improvements and fixes.',
                style: theme.textTheme.bodyMedium?.copyWith(height: 1.5),
              ),
            ),
          ),
          const SizedBox(height: 20),
          FilledButton.icon(
            onPressed: info.hasDownloadUrl ? () => _openUpdate(context) : null,
            icon: const Icon(Icons.download_rounded),
            label: const Text('Update now'),
          ),
        ],
      ),
    );
  }
}
