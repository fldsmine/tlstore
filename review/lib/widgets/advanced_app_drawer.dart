import 'package:flutter/material.dart';

import '../core/theme/app_colors.dart';
import '../services/media_url.dart';

enum AppDrawerDestination {
  home,
  apps,
  ads,
  wallets,
  subscriptions,
  valueAddedServices,
  topUp,
  transfer,
  referrals,
  rankings,
  notifications,
  history,
  tasks,
  help,
  profile,
  settings,
  campaigns,
  privacy,
  terms,
  about,
  support,
  signOut,
}

class AdvancedAppDrawer extends StatelessWidget {
  const AdvancedAppDrawer({
    super.key,
    required this.user,
    required this.preferredCurrency,
    required this.onCurrencyTap,
    required this.onSelect,
    this.selectedDestination,
  });

  final Map<String, dynamic> user;
  final String preferredCurrency;
  final VoidCallback onCurrencyTap;
  final ValueChanged<AppDrawerDestination> onSelect;
  final AppDrawerDestination? selectedDestination;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final colors = theme.colorScheme;
    final fullName = (user['full_name'] ?? user['name'] ?? 'TL Store member')
        .toString();
    final username = user['username']?.toString().trim();
    final subtitle = username == null || username.isEmpty
        ? (user['email']?.toString() ?? '')
        : '@$username';
    final avatar = MediaUrl.resolve(user['avatar']?.toString());
    const items = <(IconData, String, AppDrawerDestination)>[
      (
        Icons.workspace_premium_rounded,
        'Subscription',
        AppDrawerDestination.subscriptions,
      ),
      (
        Icons.people_alt_rounded,
        'Refer & earn',
        AppDrawerDestination.referrals,
      ),
      (
        Icons.military_tech_rounded,
        'Rank rewards',
        AppDrawerDestination.rankings,
      ),
      (Icons.add_card_rounded, 'Topup fund', AppDrawerDestination.topUp),
      (Icons.campaign_outlined, 'Campaign', AppDrawerDestination.campaigns),
      (Icons.task_alt_rounded, 'My Tasks', AppDrawerDestination.tasks),
      (
        Icons.bolt_rounded,
        'Utilities',
        AppDrawerDestination.valueAddedServices,
      ),
      (Icons.help_center_rounded, 'FAQs', AppDrawerDestination.help),
      (Icons.settings_rounded, 'Settings', AppDrawerDestination.settings),
    ];

    return Scaffold(
      backgroundColor: Colors.transparent,
      body: Stack(
        fit: StackFit.expand,
        children: [
          Positioned.fill(
            child: Image.asset('assets/splash-bg.png', fit: BoxFit.cover),
          ),
          Positioned.fill(
            child: Container(
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  colors: [
                    Color(AppColors.gradientStart).withValues(alpha: .50),
                    Color(AppColors.gradientCenter).withValues(alpha: .50),
                    Color(AppColors.gradientMiddel).withValues(alpha: .50),
                    Color(AppColors.gradientEnd).withValues(alpha: .50),
                  ],
                  begin: Alignment.centerLeft,
                  end: Alignment.centerRight,
                ),
              ),
            ),
          ),
          SafeArea(
            child: Padding(
              padding: const EdgeInsets.fromLTRB(12, 14, 16, 14),
              child: Column(
                children: [
                  Row(
                    children: [
                      Expanded(
                        child: _topLink(
                          context,
                          icon: Icons.privacy_tip_outlined,
                          title: 'Privacy',
                          onTap: () => onSelect(AppDrawerDestination.privacy),
                        ),
                      ),
                      Expanded(
                        child: _topLink(
                          context,
                          icon: Icons.policy_outlined,
                          title: 'Terms',
                          onTap: () => onSelect(AppDrawerDestination.terms),
                        ),
                      ),
                      Expanded(
                        child: _topLink(
                          context,
                          icon: Icons.info_outline_rounded,
                          title: 'About',
                          onTap: () => onSelect(AppDrawerDestination.about),
                        ),
                      ),
                      Expanded(
                        child: _topLink(
                          context,
                          icon: Icons.support_agent_rounded,
                          title: 'Support',
                          onTap: () => onSelect(AppDrawerDestination.support),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 32),
                  GestureDetector(
                    behavior: HitTestBehavior.opaque,
                    onTap: () => onSelect(AppDrawerDestination.profile),
                    child: Row(
                      children: [
                        Stack(
                          clipBehavior: Clip.none,
                          children: [
                            Hero(
                              tag: 'profile_image',
                              child: CircleAvatar(
                                radius: 30,
                                backgroundColor: colors.surface,
                                foregroundImage: avatar == null
                                    ? null
                                    : NetworkImage(avatar),
                                child: Icon(
                                  Icons.person_rounded,
                                  color: colors.primary,
                                  size: 32,
                                ),
                              ),
                            ),
                            Positioned(
                              right: -2,
                              bottom: -2,
                              child: Material(
                                color: colors.surface,
                                shape: const CircleBorder(),
                                elevation: 2,
                                child: InkWell(
                                  customBorder: const CircleBorder(),
                                  onTap: () =>
                                      onSelect(AppDrawerDestination.profile),
                                  child: Padding(
                                    padding: const EdgeInsets.all(4),
                                    child: Icon(
                                      Icons.settings_rounded,
                                      color: colors.primary,
                                      size: 16,
                                    ),
                                  ),
                                ),
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                fullName,
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                                style: theme.textTheme.titleMedium?.copyWith(
                                  color: Colors.white,
                                  fontWeight: FontWeight.w700,
                                ),
                              ),
                              if (subtitle.isNotEmpty)
                                Text(
                                  subtitle,
                                  maxLines: 1,
                                  overflow: TextOverflow.ellipsis,
                                  style: theme.textTheme.bodySmall?.copyWith(
                                    color: Colors.white.withValues(alpha: .72),
                                  ),
                                ),
                            ],
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 32),
                  Expanded(
                    child: SingleChildScrollView(
                      child: Column(
                        children: [
                          for (final item in items)
                            _menuItem(
                              context,
                              icon: item.$1,
                              title: item.$2,
                              destination: item.$3,
                              active: selectedDestination == item.$3,
                            ),
                        ],
                      ),
                    ),
                  ),
                  const SizedBox(height: 8),
                  Row(
                    children: [
                      Expanded(
                        child: Align(
                          alignment: Alignment.centerLeft,
                          child: Material(
                            color: Colors.transparent,
                            borderRadius: BorderRadius.circular(10),
                            child: InkWell(
                              borderRadius: BorderRadius.circular(10),
                              onTap: onCurrencyTap,
                              child: Container(
                                padding: const EdgeInsets.symmetric(
                                  horizontal: 9,
                                  vertical: 8,
                                ),
                                decoration: BoxDecoration(
                                  color: Colors.white.withValues(alpha: .12),
                                  borderRadius: BorderRadius.circular(10),
                                ),
                                child: Row(
                                  mainAxisSize: MainAxisSize.min,
                                  children: [
                                    const Icon(
                                      Icons.currency_exchange_rounded,
                                      color: Colors.white,
                                      size: 19,
                                    ),
                                    const SizedBox(width: 8),
                                    Flexible(
                                      child: Text(
                                        preferredCurrency.toUpperCase(),
                                        maxLines: 1,
                                        overflow: TextOverflow.ellipsis,
                                        style: theme.textTheme.bodyMedium
                                            ?.copyWith(
                                              color: Colors.white,
                                              fontWeight: FontWeight.w600,
                                            ),
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                            ),
                          ),
                        ),
                      ),
                      const SizedBox(width: 8),
                      Expanded(
                        child: Align(
                          alignment: Alignment.centerRight,
                          child: InkWell(
                            borderRadius: BorderRadius.circular(10),
                            onTap: () => onSelect(AppDrawerDestination.signOut),
                            child: Padding(
                              padding: const EdgeInsets.symmetric(
                                horizontal: 7,
                                vertical: 10,
                              ),
                              child: Row(
                                mainAxisSize: MainAxisSize.min,
                                children: [
                                  const Icon(
                                    Icons.logout_rounded,
                                    color: Colors.white,
                                    size: 19,
                                  ),
                                  const SizedBox(width: 6),
                                  Flexible(
                                    child: Text(
                                      'Sign out',
                                      maxLines: 1,
                                      softWrap: false,
                                      overflow: TextOverflow.ellipsis,
                                      style: theme.textTheme.bodyMedium
                                          ?.copyWith(
                                            color: Colors.white,
                                            fontWeight: FontWeight.w600,
                                          ),
                                    ),
                                  ),
                                ],
                              ),
                            ),
                          ),
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _topLink(
    BuildContext context, {
    required IconData icon,
    required String title,
    required VoidCallback onTap,
  }) => _DrawerTopLink(icon: icon, title: title, onTap: onTap);

  Widget _menuItem(
    BuildContext context, {
    required IconData icon,
    required String title,
    required AppDrawerDestination destination,
    required bool active,
  }) => AnimatedContainer(
    duration: const Duration(milliseconds: 180),
    margin: const EdgeInsets.only(bottom: 2),
    child: Material(
      color: Colors.transparent,
      borderRadius: BorderRadius.circular(12),
      child: Ink(
        decoration: BoxDecoration(
          color: active
              ? Colors.white.withValues(alpha: .12)
              : Colors.transparent,
          borderRadius: BorderRadius.circular(12),
          border: active
              ? const Border(left: BorderSide(color: Colors.white, width: 4))
              : null,
        ),
        child: ListTile(
          contentPadding: const EdgeInsets.symmetric(horizontal: 12),
          minLeadingWidth: 20,
          horizontalTitleGap: 10,
          leading: Icon(
            icon,
            color: active ? Colors.white : Colors.white70,
            size: 23,
          ),
          title: Text(
            title,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: Theme.of(context).textTheme.titleSmall?.copyWith(
              color: active ? Colors.white : Colors.white70,
              fontWeight: active ? FontWeight.w700 : FontWeight.w500,
            ),
          ),
          onTap: () => onSelect(destination),
        ),
      ),
    ),
  );
}

class _DrawerTopLink extends StatefulWidget {
  const _DrawerTopLink({
    required this.icon,
    required this.title,
    required this.onTap,
  });

  final IconData icon;
  final String title;
  final VoidCallback onTap;

  @override
  State<_DrawerTopLink> createState() => _DrawerTopLinkState();
}

class _DrawerTopLinkState extends State<_DrawerTopLink> {
  bool _pressed = false;

  @override
  Widget build(BuildContext context) => Material(
    color: Colors.transparent,
    child: InkWell(
      onTap: widget.onTap,
      onHighlightChanged: (pressed) => setState(() => _pressed = pressed),
      borderRadius: BorderRadius.circular(8),
      child: AnimatedScale(
        duration: const Duration(milliseconds: 100),
        scale: _pressed ? .92 : 1,
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 2),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(widget.icon, color: Colors.white, size: 22),
              const SizedBox(height: 3),
              Text(
                widget.title,
                maxLines: 1,
                style: Theme.of(context).textTheme.labelMedium?.copyWith(
                  color: Colors.white,
                  fontWeight: FontWeight.w600,
                ),
              ),
            ],
          ),
        ),
      ),
    ),
  );
}
