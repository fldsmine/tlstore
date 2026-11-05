import 'dart:async';
import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../core/theme/app_colors.dart';
import '../features/marketplace/presentation/listing_kind.dart';
import '../features/social_follow/presentation/social_handles_dialog.dart';
import '../features/wallets/presentation/wallet_detail_screens.dart';
import '../services/auth_api.dart';
import '../widgets/auth_components.dart';
import '../widgets/advanced_app_drawer.dart';
import 'frontend/landing_sreen.dart';
import 'history_screen.dart';
import 'my_tasks_screen.dart';
import 'faq_screen.dart';
import 'auth/login_screen.dart';
import 'listings_screen.dart';
import 'payout_screen.dart';
import 'profile_screen.dart';
import 'notifications_screen.dart';
import 'settings_screen.dart';
import 'referrals_screen.dart';
import 'legal_document_screen.dart';
import 'subscriptions_screen.dart';
import 'support_ticket_demo_screen.dart';
import 'about_screen.dart';
import 'topup_screen.dart';
import 'wallet_transfer_screen.dart';
import '../services/push_notification_service.dart';

const _dashboardInk = Color(0xFF202A38);

class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key, required this.result, required this.api});

  final AuthResult result;
  final AuthApi api;

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen>
    with SingleTickerProviderStateMixin {
  late final AnimationController _drawerController;
  late final Animation<double> _drawerProgress;
  bool _loading = true;
  String? _error;
  List<Map<String, dynamic>> _wallets = const [];
  List<Map<String, dynamic>> _transactions = const [];
  List<Map<String, dynamic>> _featuredApps = const [];
  List<Map<String, dynamic>> _featuredAds = const [];
  List<Map<String, dynamic>> _announcements = const [];
  Map<String, dynamic> _sessionUser = const {};
  final Set<int> _dismissedAnnouncements = {};
  bool _announcementDialogShowing = false;
  bool _announcementPromptShown = false;
  bool _featuredLoading = true;
  bool _socialPromptShown = false;
  int _selectedTab = 1;
  int _selectedNav = 0;
  AppDrawerDestination? _activeDrawerDestination;
  StreamSubscription<void>? _notificationTapSubscription;

  String get _displayName {
    final name =
        _sessionUser['full_name'] ??
        _sessionUser['name'] ??
        _sessionUser['username'];
    final value = name?.toString().trim() ?? '';
    return value.isEmpty ? 'there' : value.split(RegExp(r'\s+')).first;
  }

  String get _currency {
    final preferred = _sessionUser['preferred_currency']?.toString();
    if (preferred != null && preferred.isNotEmpty) {
      return preferred.toUpperCase();
    }
    final earningWallet = _wallets
        .where((wallet) => wallet['type'] != 'topup_fund')
        .firstOrNull;
    return (earningWallet?['currency'] ?? 'USD').toString().toUpperCase();
  }

  double get _totalEarningsMajor => _wallets
      .where((wallet) => wallet['type'] != 'topup_fund')
      .fold<double>(0, (sum, wallet) {
        final source =
            wallet['currency']?.toString().toUpperCase() ?? _currency;
        final minor = (wallet['available_balance_minor'] as num?)?.toInt() ?? 0;
        final sourceMajor = minor / math.pow(10, _currencyPrecision(source));
        return sum +
            (widget.api.convertMajor(sourceMajor, source, _currency) ??
                (source == _currency ? sourceMajor : 0));
      });

  @override
  void initState() {
    super.initState();
    _sessionUser = widget.result.user;
    _drawerController = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 350),
    );
    _drawerProgress = CurvedAnimation(
      parent: _drawerController,
      curve: Curves.easeOutCubic,
    );
    _loadDashboard();
    _loadFeatured();
    _loadAnnouncements();
    _notificationTapSubscription = PushNotificationService
        .instance
        .notificationOpened
        .listen((_) {
          if (mounted &&
              PushNotificationService.instance.consumePendingOpen()) {
            _openNotifications();
          }
        });
    unawaited(
      PushNotificationService.instance.connect(widget.api, widget.result.token),
    );
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted && PushNotificationService.instance.consumePendingOpen()) {
        _openNotifications();
      }
    });
  }

  @override
  void dispose() {
    _notificationTapSubscription?.cancel();
    _drawerController.dispose();
    super.dispose();
  }

  void _openNotifications() {
    unawaited(
      _pushPage(
        NotificationsScreen(api: widget.api, token: widget.result.token),
        0,
      ),
    );
  }

  Future<void> _loadDashboard() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final responses = await Future.wait([
        widget.api.wallets(widget.result.token),
        widget.api.accountHistory(widget.result.token, perPage: 5),
      ]);
      final wallets = responses[0] as List<Map<String, dynamic>>;
      final historyData = (responses[1] as Map<String, dynamic>)['data'];
      final transactions =
          historyData is Map<String, dynamic> && historyData['data'] is List
          ? (historyData['data'] as List)
                .whereType<Map<String, dynamic>>()
                .toList()
          : <Map<String, dynamic>>[];
      await widget.api.loadExchangeRates(widget.result.token);
      if (!mounted) return;
      setState(() {
        _wallets = wallets;
        _transactions = transactions;
        _loading = false;
      });
      unawaited(_maybeShowSocialFollowDialog());
    } on AuthException catch (error) {
      if (error.unauthorized) {
        await widget.api.clearSession();
        if (!mounted) return;
        Navigator.of(context).pushAndRemoveUntil(
          MaterialPageRoute<void>(builder: (_) => const LoginScreen()),
          (_) => false,
        );
        return;
      }
      if (mounted) {
        setState(() {
          _error = error.message;
          _loading = false;
        });
      }
    }
  }

  Future<void> _maybeShowSocialFollowDialog() async {
    if (_socialPromptShown || !mounted) return;
    try {
      final handles = await widget.api.socialHandles(widget.result.token);
      if (!mounted || _socialPromptShown) return;
      _socialPromptShown = true;
      if (!handles.any((item) => item['followed'] != true)) return;
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (!mounted) return;
        unawaited(
          SocialHandlesDialog.showIfEligible(
            context: context,
            handles: handles,
            api: widget.api,
            token: widget.result.token,
            onRewardClaimed: () => unawaited(_loadDashboard()),
          ),
        );
      });
    } on AuthException catch (error) {
      if (error.unauthorized) {
        await widget.api.clearSession();
      }
    }
  }

  Future<void> _loadFeatured() async {
    try {
      await widget.api.loadExchangeRates(widget.result.token);
      final response = await widget.api.spotlights();
      final data = response['data'];
      if (!mounted || data is! Map<String, dynamic>) return;
      setState(() {
        _featuredApps = data['products'] is List
            ? (data['products'] as List)
                  .whereType<Map<String, dynamic>>()
                  .toList()
            : const [];
        _featuredAds = data['ads'] is List
            ? (data['ads'] as List).whereType<Map<String, dynamic>>().toList()
            : const [];
        _featuredLoading = false;
      });
    } on AuthException {
      if (mounted) setState(() => _featuredLoading = false);
    }
  }

  Future<void> _loadAnnouncements() async {
    try {
      final response = await widget.api.get(
        'announcements',
        widget.result.token,
      );
      final data = response['data'];
      final rows = data is List
          ? data.whereType<Map<String, dynamic>>().toList()
          : const <Map<String, dynamic>>[];
      if (!mounted) return;
      setState(() => _announcements = rows);
      _scheduleAnnouncementDialog();
    } on AuthException {
      // Announcements are optional home content; keep the dashboard usable.
    } catch (_) {
      // Ignore malformed optional home content and keep the dashboard usable.
    }
  }

  void _scheduleAnnouncementDialog() {
    if (!mounted || _announcementDialogShowing || _announcementPromptShown) {
      return;
    }
    final announcement = _announcements
        .where((item) => _displayType(item) == 0)
        .where(
          (item) => !_dismissedAnnouncements.contains(_announcementId(item)),
        )
        .firstOrNull;
    if (announcement == null) return;

    _announcementPromptShown = true;
    _announcementDialogShowing = true;
    WidgetsBinding.instance.addPostFrameCallback((_) async {
      if (!mounted) return;
      final dismissible = announcement['is_dismissible'] != false;
      await showDialog<void>(
        context: context,
        barrierDismissible: dismissible,
        builder: (dialogContext) => PopScope(
          canPop: dismissible,
          child: AlertDialog(
            icon: Icon(
              _announcementIcon(announcement),
              color: _announcementColor(context, announcement),
              size: 34,
            ),
            title: Text(announcement['title']?.toString() ?? 'Announcement'),
            content: SingleChildScrollView(
              child: Text(announcement['body']?.toString() ?? ''),
            ),
            actions: [
              if (announcement['action_url']?.toString().isNotEmpty == true)
                TextButton(
                  onPressed: () => _openAnnouncementAction(
                    dialogContext,
                    announcement,
                    closeDialog: true,
                  ),
                  child: Text(
                    announcement['action_label']?.toString().isNotEmpty == true
                        ? announcement['action_label'].toString()
                        : 'Learn more',
                  ),
                ),
              if (dismissible)
                FilledButton(
                  onPressed: () => Navigator.of(dialogContext).pop(),
                  child: const Text('Got it'),
                ),
            ],
          ),
        ),
      );
      if (!mounted) return;
      setState(() {
        _announcementDialogShowing = false;
        _dismissedAnnouncements.add(_announcementId(announcement));
      });
      if (dismissible) {
        unawaited(_persistAnnouncementDismissal(_announcementId(announcement)));
      }
    });
  }

  Future<void> _persistAnnouncementDismissal(int id) async {
    try {
      await widget.api.post(
        'announcements/$id/dismiss',
        widget.result.token,
        const <String, dynamic>{},
      );
    } catch (_) {
      // Local dismissal remains active for this screen if the request fails.
    }
  }

  Widget _buildInlineAnnouncements() {
    final inline = _announcements.where((announcement) {
      final displayType = _displayType(announcement);
      return (displayType == 1 || displayType == 2) &&
          !_dismissedAnnouncements.contains(_announcementId(announcement));
    });
    if (inline.isEmpty) return const SizedBox.shrink();
    return Padding(
      padding: const EdgeInsets.only(top: 12),
      child: Column(
        children: [
          for (final announcement in inline)
            Padding(
              padding: const EdgeInsets.only(bottom: 9),
              child: _announcementBanner(announcement),
            ),
        ],
      ),
    );
  }

  Widget _announcementBanner(Map<String, dynamic> announcement) {
    final color = _announcementColor(context, announcement);
    final pinned = _displayType(announcement) == 2;
    final canDismiss = !pinned && announcement['is_dismissible'] != false;
    final actionUrl = announcement['action_url']?.toString();
    return Container(
      padding: const EdgeInsets.fromLTRB(13, 12, 8, 12),
      decoration: BoxDecoration(
        color: color.withValues(alpha: .08),
        border: Border.all(color: color.withValues(alpha: .28)),
        borderRadius: BorderRadius.circular(15),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Padding(
            padding: const EdgeInsets.only(top: 1),
            child: Icon(
              _announcementIcon(announcement),
              color: color,
              size: 21,
            ),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  announcement['title']?.toString() ?? 'Announcement',
                  style: Theme.of(context).textTheme.titleSmall
                      ?.copyWith(fontWeight: FontWeight.w800),
                ),
                const SizedBox(height: 3),
                Text(
                  announcement['body']?.toString() ?? '',
                  style: Theme.of(context).textTheme.bodySmall?.copyWith(
                    color: Theme.of(context).colorScheme.onSurfaceVariant,
                    height: 1.35,
                  ),
                ),
                if (actionUrl != null && actionUrl.isNotEmpty) ...[
                  const SizedBox(height: 4),
                  Align(
                    alignment: Alignment.centerLeft,
                    child: TextButton(
                      onPressed: () =>
                          _openAnnouncementAction(context, announcement),
                      style: TextButton.styleFrom(
                        padding: EdgeInsets.zero,
                        minimumSize: const Size(0, 32),
                        tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                      ),
                      child: Text(
                        announcement['action_label']?.toString().isNotEmpty ==
                                true
                            ? announcement['action_label'].toString()
                            : 'Learn more',
                      ),
                    ),
                  ),
                ],
              ],
            ),
          ),
          if (canDismiss)
            IconButton(
              tooltip: 'Dismiss announcement',
              visualDensity: VisualDensity.compact,
              onPressed: () {
                final id = _announcementId(announcement);
                setState(() => _dismissedAnnouncements.add(id));
                unawaited(_persistAnnouncementDismissal(id));
              },
              icon: const Icon(Icons.close_rounded, size: 19),
            ),
        ],
      ),
    );
  }

  Future<void> _openAnnouncementAction(
    BuildContext routeContext,
    Map<String, dynamic> announcement, {
    bool closeDialog = false,
  }) async {
    final uri = Uri.tryParse(announcement['action_url']?.toString() ?? '');
    if (uri == null || !uri.hasScheme) {
      showAuthMessage(routeContext, 'This announcement link is unavailable.');
      return;
    }
    try {
      final opened = await launchUrl(uri, mode: LaunchMode.externalApplication);
      if (!opened && routeContext.mounted) {
        showAuthMessage(
          routeContext,
          'Could not open that link. Try again later.',
        );
      } else if (opened && closeDialog && routeContext.mounted) {
        Navigator.of(routeContext).pop();
      }
    } catch (_) {
      if (routeContext.mounted) {
        showAuthMessage(
          routeContext,
          'Could not open that link. Try again later.',
        );
      }
    }
  }

  int _announcementId(Map<String, dynamic> announcement) =>
      (announcement['id'] as num?)?.toInt() ?? announcement.hashCode;

  int _displayType(Map<String, dynamic> announcement) =>
      (announcement['display_type'] as num?)?.toInt() ?? 0;

  IconData _announcementIcon(Map<String, dynamic> announcement) =>
      switch (announcement['type']?.toString()) {
        'success' => Icons.check_circle_outline_rounded,
        'warning' => Icons.warning_amber_rounded,
        'danger' => Icons.error_outline_rounded,
        _ => Icons.info_outline_rounded,
      };

  Color _announcementColor(
    BuildContext context,
    Map<String, dynamic> announcement,
  ) => switch (announcement['type']?.toString()) {
    'success' => const Color(0xFF2E7D32),
    'warning' => const Color(0xFF9A6500),
    'danger' => Theme.of(context).colorScheme.error,
    _ => Theme.of(context).colorScheme.primary,
  };

  Future<void> _refreshHome() async {
    await Future.wait([
      _loadDashboard(),
      _loadFeatured(),
      _loadAnnouncements(),
    ]);
  }

  Future<void> _logout() async {
    final shouldLogout = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Sign out?'),
        content: const Text('Are you sure you want to sign out of TrustLens?'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(dialogContext, true),
            child: const Text('Sign out'),
          ),
        ],
      ),
    );
    if (shouldLogout != true || !mounted) return;

    await PushNotificationService.instance.disconnect(
      widget.api,
      widget.result.token,
    );
    try {
      await widget.api.logout(widget.result.token);
    } on AuthException catch (error) {
      if (mounted) showAuthMessage(context, error.message);
    }
    if (!mounted) return;
    Navigator.of(context).pushAndRemoveUntil(
      MaterialPageRoute<void>(builder: (_) => const LoginScreen()),
      (_) => false,
    );
  }

  void _accountDeleted() {
    Navigator.of(context).pushAndRemoveUntil(
      MaterialPageRoute<void>(builder: (_) => const LoginScreen()),
      (_) => false,
    );
  }

  void _notReady(String section) {
    if (section == 'Withdrawals' || section == 'Payout') {
      _pushPage(PayoutScreen(api: widget.api, token: widget.result.token), 3);
    } else if (section == 'Transaction history' || section == 'History') {
      _pushPage(HistoryScreen(api: widget.api, token: widget.result.token), 1);
    } else if (section == 'Profile') {
      _pushPage(
        ProfileScreen(
          api: widget.api,
          token: widget.result.token,
          user: _sessionUser,
        ),
        2,
      );
    } else {
      showAuthMessage(context, '$section is not available yet.');
    }
  }

  Future<void> _pushPage(Widget page, int navIndex) async {
    setState(() => _selectedNav = navIndex);
    await Navigator.of(context)
        .push(MaterialPageRoute<void>(builder: (_) => page));
    if (mounted) {
      setState(() => _selectedNav = 0);
      try {
        final updated = await widget.api.refreshUser(widget.result.token);
        if (mounted) setState(() => _sessionUser = updated);
      } on AuthException {
        // Keep the cached account snapshot available while the server is offline.
      }
      await Future.wait([_loadDashboard(), _loadFeatured()]);
    }
  }

  @override
  Widget build(BuildContext context) {
    final width = MediaQuery.sizeOf(context).width;
    return GestureDetector(
      onHorizontalDragUpdate: _onDrawerDragUpdate,
      onHorizontalDragEnd: _onDrawerDragEnd,
      child: AnimatedBuilder(
        animation: _drawerProgress,
        builder: (context, _) {
          final progress = _drawerProgress.value;
          final slide = width * .56 * progress;
          final scale = 1 - (.1 * progress);
          final verticalInset = 65 * progress;

          return Stack(
            children: [
              Transform.translate(
                offset: Offset(-width * .2 * (1 - progress), 0),
                child: AdvancedAppDrawer(
                  user: _sessionUser,
                  preferredCurrency: _currency,
                  selectedDestination: _activeDrawerDestination,
                  onSelect: _handleDrawerSelection,
                ),
              ),
              Transform(
                alignment: Alignment.center,
                transform: Matrix4.identity()
                  ..translate(slide)
                  ..scale(scale),
                child: Container(
                  margin: EdgeInsets.symmetric(vertical: verticalInset),
                  decoration: BoxDecoration(
                    borderRadius: BorderRadius.circular(24 * progress),
                    boxShadow: [
                      if (progress > 0)
                        BoxShadow(
                          color: Colors.black26.withValues(alpha: progress),
                          blurRadius: 30 * progress,
                          offset: const Offset(0, 20),
                        ),
                    ],
                  ),
                  child: ClipRRect(
                    borderRadius: BorderRadius.circular(24 * progress),
                    child: _buildHomeScaffold(),
                  ),
                ),
              ),
            ],
          );
        },
      ),
    );
  }

  Widget _buildHomeScaffold() => Scaffold(
    backgroundColor: Theme.of(context).scaffoldBackgroundColor,
    body: SafeArea(
      child: Column(
        children: [
          _buildHeader(),
          Expanded(
            child: RefreshIndicator(
              onRefresh: _refreshHome,
              color: brandGreen,
              child: ListView(
                padding: const EdgeInsets.fromLTRB(18, 10, 18, 22),
                children: [
                  _buildEarningsCard(),
                  _buildInlineAnnouncements(),
                  const SizedBox(height: 19),
                  _buildCategoryTabs(),
                  const SizedBox(height: 20),
                  if (_selectedTab == 1) ...[
                    _buildFeaturedSection(),
                    const SizedBox(height: 17),
                    _buildWalletGrid(),
                    const SizedBox(height: 23),
                    _buildRecentHeading(),
                    const SizedBox(height: 10),
                    _buildTransactions(),
                  ] else
                    _buildTabTeaser(),
                  if (_error != null) ...[
                    const SizedBox(height: 15),
                    _buildErrorCard(),
                  ],
                ],
              ),
            ),
          ),
          _buildBottomBar(),
        ],
      ),
    ),
  );

  void _toggleDrawer() {
    if (_drawerController.value > 0) {
      _drawerController.reverse();
    } else {
      _drawerController.forward();
    }
  }

  void _onDrawerDragUpdate(DragUpdateDetails details) {
    final width = MediaQuery.sizeOf(context).width;
    final delta = details.primaryDelta ?? 0;
    _drawerController.value = (_drawerController.value + delta / width).clamp(
      0.0,
      1.0,
    );
  }

  void _onDrawerDragEnd(DragEndDetails details) {
    final velocity = details.primaryVelocity ?? 0;
    if (velocity > 500) {
      _drawerController.forward();
    } else if (velocity < -500) {
      _drawerController.reverse();
    } else if (_drawerController.value > .5) {
      _drawerController.forward();
    } else {
      _drawerController.reverse();
    }
  }

  void _handleDrawerSelection(AppDrawerDestination destination) {
    setState(() => _activeDrawerDestination = destination);
    unawaited(_selectDrawerDestinationAfterClose(destination));
  }

  Future<void> _selectDrawerDestinationAfterClose(
    AppDrawerDestination destination,
  ) async {
    await _drawerController.reverse();
    if (!mounted) return;
    switch (destination) {
      case AppDrawerDestination.home:
        setState(() => _selectedTab = 1);
      case AppDrawerDestination.apps:
        _pushPage(
          ListingsScreen(
            api: widget.api,
            token: widget.result.token,
            user: _sessionUser,
            kind: ListingKind.appReview,
            preferredCurrency: _currency,
          ),
          0,
        );
      case AppDrawerDestination.ads:
        _pushPage(
          ListingsScreen(
            api: widget.api,
            token: widget.result.token,
            user: _sessionUser,
            kind: ListingKind.adShare,
            preferredCurrency: _currency,
          ),
          0,
        );
      case AppDrawerDestination.wallets:
        setState(() => _selectedTab = 3);
      case AppDrawerDestination.subscriptions:
        _pushPage(
          SubscriptionsScreen(
            api: widget.api,
            token: widget.result.token,
            user: _sessionUser,
          ),
          0,
        );
      case AppDrawerDestination.topUp:
        _pushPage(TopUpScreen(api: widget.api, token: widget.result.token), 0);
      case AppDrawerDestination.transfer:
        _pushPage(
          WalletTransferScreen(api: widget.api, token: widget.result.token),
          0,
        );
      case AppDrawerDestination.referrals:
        _pushPage(
          ReferralsScreen(
            api: widget.api,
            token: widget.result.token,
            user: widget.result.user,
          ),
          0,
        );
      case AppDrawerDestination.notifications:
        _pushPage(
          NotificationsScreen(api: widget.api, token: widget.result.token),
          0,
        );
      case AppDrawerDestination.history:
        _pushPage(
          HistoryScreen(api: widget.api, token: widget.result.token),
          0,
        );
      case AppDrawerDestination.tasks:
        _pushPage(
          MyTasksScreen(api: widget.api, token: widget.result.token),
          0,
        );
      case AppDrawerDestination.help:
        _pushPage(FaqScreen(api: widget.api), 0);
      case AppDrawerDestination.profile:
        _pushPage(
          ProfileScreen(
            api: widget.api,
            token: widget.result.token,
            user: _sessionUser,
          ),
          0,
        );
      case AppDrawerDestination.settings:
        _pushPage(
          SettingsScreen(
            api: widget.api,
            token: widget.result.token,
            user: _sessionUser,
            onLogout: _logout,
            onDeleted: _accountDeleted,
          ),
          0,
        );
      case AppDrawerDestination.privacy:
        _pushPage(LegalDocumentScreen(api: widget.api, slug: 'privacy'), 0);
      case AppDrawerDestination.terms:
        _pushPage(LegalDocumentScreen(api: widget.api, slug: 'terms'), 0);
      case AppDrawerDestination.about:
        _pushPage(AboutScreen(api: widget.api), 0);
      case AppDrawerDestination.support:
        _pushPage(SupportTicketDemoScreen(user: _sessionUser), 0);
      case AppDrawerDestination.signOut:
        _logout();
    }
  }

  Widget _buildHeader() => SizedBox(
    height: 63,
    child: Row(
      children: [
        const SizedBox(width: 6),
        IconButton(
          onPressed: _toggleDrawer,
          icon: AnimatedIcon(
            icon: AnimatedIcons.menu_arrow,
            progress: _drawerProgress,
            color: const Color(0xFF18563D),
            size: 29,
          ),
        ),
        Expanded(
          child: GestureDetector(
            onTap: () {
              Navigator.of(
                context,
              ).push(MaterialPageRoute(builder: (_) => const LandingScreen()));
            },
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Hi, $_displayName',
                  style: Theme.of(context).textTheme.titleMedium
                      ?.copyWith(fontWeight: FontWeight.w800),
                ),
                Text(
                  'Review • Share • Earn',
                  style: Theme.of(context).textTheme.bodySmall?.copyWith(
                    color: Theme.of(context).colorScheme.onSurface
                        .withOpacity(0.7),
                    fontSize: 10,
                    letterSpacing: 0.5,
                  ),
                ),
              ],
            ),
          ),
        ),
        IconButton(
          onPressed: () => _pushPage(
            NotificationsScreen(api: widget.api, token: widget.result.token),
            0,
          ),
          icon: const Icon(
            Icons.notifications_none_rounded,
            color: Color(0xFF18563D),
            size: 26,
          ),
        ),
        Padding(
          padding: const EdgeInsets.only(right: 18),
          child: Container(
            width: 43,
            height: 43,
            decoration: BoxDecoration(
              color: Colors.white,
              shape: BoxShape.circle,
              border: Border.all(color: brandGreen, width: 1.5),
            ),
            child: _avatar(),
          ),
        ),
      ],
    ),
  );

  Widget _avatar() {
    final avatar = _sessionUser['avatar']?.toString();
    return ClipOval(
      child: avatar != null && avatar.isNotEmpty
          ? Image.network(
              avatar,
              fit: BoxFit.cover,
              errorBuilder: (_, _, _) =>
                  const Icon(Icons.verified_user_rounded, color: brandGreen),
            )
          : const Icon(Icons.verified_user_rounded, color: brandGreen),
    );
  }

  Widget _buildEarningsCard() {
    final total = _formatMajor(_totalEarningsMajor, _currency);
    final username = _sessionUser['username']?.toString() ?? _displayName;
    return Container(
      height: 225,
      padding: const EdgeInsets.fromLTRB(23, 22, 21, 18),
      clipBehavior: Clip.antiAlias,
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(25),
        gradient: LinearGradient(
          colors: [
            Color(AppColors.gradientStart),
            Color(AppColors.gradientCenter),
            Color(AppColors.gradientMiddel),
            Color(AppColors.gradientEnd),
          ],
          begin: Alignment.centerLeft,
          end: Alignment.centerRight,
        ),
        boxShadow: const [
          BoxShadow(
            color: Color(0x241C3F30),
            blurRadius: 18,
            offset: Offset(0, 9),
          ),
        ],
      ),
      child: Stack(
        children: [
          Positioned(
            right: -32,
            top: 75,
            child: Transform.rotate(
              angle: -.55,
              child: Container(
                width: 190,
                height: 140,
                decoration: BoxDecoration(
                  border: Border.all(
                    color: Colors.white.withValues(alpha: .18),
                    width: 1,
                  ),
                  borderRadius: BorderRadius.circular(35),
                ),
              ),
            ),
          ),
          Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  const Text(
                    'Total Earnings',
                    style: TextStyle(
                      color: Colors.white,
                      fontSize: 23,
                      fontWeight: FontWeight.w500,
                    ),
                  ),
                  Icon(
                    Icons.auto_awesome_rounded,
                    color: Colors.white.withValues(alpha: .9),
                    size: 27,
                  ),
                ],
              ),
              const SizedBox(height: 2),
              _loading
                  ? const SizedBox(
                      height: 47,
                      width: 38,
                      child: Padding(
                        padding: EdgeInsets.symmetric(vertical: 13),
                        child: CircularProgressIndicator(
                          color: Colors.white,
                          strokeWidth: 2,
                        ),
                      ),
                    )
                  : Text(
                      total,
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 36,
                        fontWeight: FontWeight.w800,
                        letterSpacing: -.8,
                      ),
                    ),
              const Spacer(),
              Row(
                crossAxisAlignment: CrossAxisAlignment.end,
                children: [
                  Expanded(
                    child: Row(
                      children: [
                        Flexible(child: _heroMeta('Account', 'Member')),
                        Container(
                          width: 1,
                          height: 41,
                          margin: const EdgeInsets.symmetric(horizontal: 14),
                          color: Colors.white.withValues(alpha: .65),
                        ),
                        Flexible(child: _heroMeta('Account Holder', username)),
                      ],
                    ),
                  ),
                  const SizedBox(width: 10),
                  OutlinedButton.icon(
                    onPressed: () => _notReady('Withdrawals'),
                    icon: const Icon(Icons.arrow_forward_rounded, size: 17),
                    label: const Text('Withdraw'),
                    style: OutlinedButton.styleFrom(
                      foregroundColor: Colors.white,
                      side: const BorderSide(color: Colors.white, width: 1.2),
                      textStyle: const TextStyle(fontWeight: FontWeight.w800),
                      padding: const EdgeInsets.symmetric(
                        horizontal: 15,
                        vertical: 13,
                      ),
                      shape: const StadiumBorder(),
                    ),
                  ),
                ],
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _heroMeta(String label, String value) => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Text(
        label,
        style: TextStyle(
          fontSize: 11,
          color: Colors.white.withValues(alpha: .82),
        ),
      ),
      const SizedBox(height: 4),
      Text(
        value,
        maxLines: 1,
        overflow: TextOverflow.ellipsis,
        style: const TextStyle(
          color: Colors.white,
          fontSize: 15,
          fontWeight: FontWeight.w800,
        ),
      ),
    ],
  );

  Widget _buildCategoryTabs() => Container(
    height: 48,
    padding: const EdgeInsets.all(3),
    decoration: BoxDecoration(
      color: const Color(0xFFECEDEA),
      border: Border.all(color: const Color(0xFFD8DAD6)),
      borderRadius: BorderRadius.circular(16),
    ),
    child: Row(
      children: [
        _categoryButton('Apps', 0),
        _categoryButton('Earnings', 1),
        _categoryButton('Ads', 2),
        _categoryButton('Wallet', 3),
      ],
    ),
  );

  Widget _categoryButton(String label, int index) => Expanded(
    child: GestureDetector(
      onTap: () {
        if (index == 0) {
          _pushPage(
            ListingsScreen(
              api: widget.api,
              token: widget.result.token,
              user: _sessionUser,
              kind: ListingKind.appReview,
              preferredCurrency: _currency,
            ),
            0,
          );
        } else if (index == 2) {
          _pushPage(
            ListingsScreen(
              api: widget.api,
              token: widget.result.token,
              user: _sessionUser,
              kind: ListingKind.adShare,
              preferredCurrency: _currency,
            ),
            0,
          );
        } else {
          setState(() => _selectedTab = index);
        }
      },
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 180),
        alignment: Alignment.center,
        decoration: BoxDecoration(
          color: _selectedTab == index ? brandGreen : Colors.transparent,
          borderRadius: BorderRadius.circular(14),
          boxShadow: _selectedTab == index
              ? const [
                  BoxShadow(
                    color: Color(0x1C145A3D),
                    blurRadius: 6,
                    offset: Offset(0, 2),
                  ),
                ]
              : null,
        ),
        child: Text(
          label,
          style: TextStyle(
            color: _selectedTab == index
                ? Colors.white
                : const Color(0xFF58616B),
            fontSize: 14,
            fontWeight: FontWeight.w800,
          ),
        ),
      ),
    ),
  );

  Widget _buildWalletGrid() {
    const walletOrder = {
      'review': 0,
      'share': 1,
      'referral': 2,
      'bonus': 3,
      'topup_fund': 4,
    };

    const details = {
      'review': ('Review wallet', Icons.rate_review_rounded),
      'share': ('Share wallet', Icons.campaign_rounded),
      'referral': ('Referral wallet', Icons.group_add_rounded),
      'bonus': ('Bonus wallet', Icons.card_giftcard_rounded),
      'topup_fund': ('Top-up fund', Icons.account_balance_wallet_rounded),
    };

    final wallets = [..._wallets]
      ..sort((a, b) {
        final typeA = a['type']?.toString() ?? '';
        final typeB = b['type']?.toString() ?? '';

        final byType = (walletOrder[typeA] ?? 9).compareTo(
          walletOrder[typeB] ?? 9,
        );

        return byType != 0
            ? byType
            : (a['currency']?.toString() ?? '').compareTo(
                b['currency']?.toString() ?? '',
              );
      });

    if (wallets.isEmpty) return const SizedBox.shrink();

    Widget buildWalletCard(Map<String, dynamic> wallet, double width) {
      final type = wallet['type']?.toString() ?? '';

      final detailsForType =
          details[type] ?? ('Wallet', Icons.account_balance_wallet_rounded);

      final currency = wallet['currency']?.toString() ?? _currency;

      final balance = (wallet['available_balance_minor'] as num?)?.toInt() ?? 0;

      return SizedBox(
        width: width,
        child: _WalletCard(
          title: detailsForType.$1,
          icon: detailsForType.$2,
          balance: _formatMinor(balance, currency),
          pending: (wallet['pending_balance_minor'] as num?)?.toInt() ?? 0,
          currency: currency,
          onTap: () => _openWallet(wallet),
        ),
      );
    }

    return LayoutBuilder(
      builder: (context, constraints) {
        const gap = 12.0;

        // First row: two equal cards
        final cardWidth = (constraints.maxWidth - gap) / 2;

        // Second row: wider cards with a peek at the next card
        final scrollCardWidth = constraints.maxWidth * 0.62;

        final firstRow = wallets.take(2).toList();
        final secondRow = wallets.skip(2).toList();

        return Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // FIRST ROW
            Wrap(
              spacing: gap,
              runSpacing: gap,
              children: firstRow.map((wallet) {
                return buildWalletCard(wallet, cardWidth);
              }).toList(),
            ),

            const SizedBox(height: 12),

            // SECOND ROW - HORIZONTAL SCROLL
            if (secondRow.isNotEmpty)
              SingleChildScrollView(
                scrollDirection: Axis.horizontal,
                physics: const BouncingScrollPhysics(),
                child: Row(
                  children: secondRow.map((wallet) {
                    return Padding(
                      padding: const EdgeInsets.only(right: gap),
                      child: buildWalletCard(wallet, scrollCardWidth),
                    );
                  }).toList(),
                ),
              ),
          ],
        );
      },
    );
  }

  void _openWallet(Map<String, dynamic> wallet) {
    final page = switch (wallet['type']?.toString()) {
      'review' => ReviewWalletScreen(
        api: widget.api,
        token: widget.result.token,
        wallet: wallet,
      ),
      'share' => ShareWalletScreen(
        api: widget.api,
        token: widget.result.token,
        wallet: wallet,
      ),
      'referral' => ReferralWalletScreen(
        api: widget.api,
        token: widget.result.token,
        wallet: wallet,
      ),
      'bonus' => BonusWalletScreen(
        api: widget.api,
        token: widget.result.token,
        wallet: wallet,
      ),
      'topup_fund' => TopupFundWalletScreen(
        api: widget.api,
        token: widget.result.token,
        wallet: wallet,
      ),
      _ => null,
    };
    if (page != null) unawaited(_pushPage(page, 0));
  }

  Widget _buildFeaturedSection() {
    final items = <(ListingKind, Map<String, dynamic>)>[
      ..._featuredApps.map((item) => (ListingKind.appReview, item)),
      ..._featuredAds.map((item) => (ListingKind.adShare, item)),
    ];
    if (_featuredLoading || items.isEmpty) return const SizedBox.shrink();
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            const Expanded(
              child: Text(
                'Featured for you',
                style: TextStyle(
                  fontSize: 17,
                  fontWeight: FontWeight.w800,
                  color: _dashboardInk,
                ),
              ),
            ),
            Text(
              '${items.length} listings',
              style: const TextStyle(
                color: mutedInk,
                fontSize: 10,
                fontWeight: FontWeight.w700,
              ),
            ),
          ],
        ),
        const SizedBox(height: 10),
        SizedBox(
          height: 100,
          child: ListView.separated(
            scrollDirection: Axis.horizontal,
            itemCount: items.length,
            separatorBuilder: (_, _) => const SizedBox(width: 10),
            itemBuilder: (context, index) => _FeaturedHomeCard(
              api: widget.api,
              preferredCurrency: _currency,
              kind: items[index].$1,
              item: items[index].$2,
              onTap: () {
                final kind = items[index].$1;
                final featured = items[index].$2;
                final detailItem = kind == ListingKind.appReview
                    ? <String, dynamic>{
                        'id': featured['campaign_id'],
                        'app': featured['app'],
                        'review_reward_minor': featured['review_reward_minor'],
                        'currency': featured['currency'],
                        'currency_minor_units':
                            featured['currency_minor_units'],
                        'location': featured['location'],
                      }
                    : featured;
                Navigator.of(context).push(
                  MaterialPageRoute<void>(
                    builder: (_) => ListingDetailScreen(
                      api: widget.api,
                      token: widget.result.token,
                      user: _sessionUser,
                      kind: kind,
                      item: detailItem,
                      preferredCurrency: _currency,
                    ),
                  ),
                );
              },
            ),
          ),
        ),
      ],
    );
  }

  Widget _buildRecentHeading() => Row(
    mainAxisAlignment: MainAxisAlignment.spaceBetween,
    children: [
      const Text(
        'Recent Transactions',
        style: TextStyle(
          fontSize: 19,
          fontWeight: FontWeight.w800,
          color: _dashboardInk,
        ),
      ),
      if (_transactions.length >= 5)
        TextButton.icon(
          onPressed: () => _notReady('Transaction history'),
          iconAlignment: IconAlignment.end,
          icon: const Icon(
            Icons.arrow_forward_rounded,
            color: Color(0xFFE84D3F),
            size: 17,
          ),
          label: const Text(
            'View more',
            style: TextStyle(color: brandGreen, fontWeight: FontWeight.w800),
          ),
        ),
    ],
  );

  Widget _buildTransactions() {
    if (_loading) {
      return const Padding(
        padding: EdgeInsets.all(25),
        child: Center(child: CircularProgressIndicator(strokeWidth: 2)),
      );
    }
    if (_transactions.isEmpty) {
      return Container(
        padding: const EdgeInsets.symmetric(vertical: 22),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(16),
        ),
        child: const Center(
          child: Text(
            'Your transactions will appear here.',
            style: TextStyle(color: mutedInk, fontSize: 12),
          ),
        ),
      );
    }
    return Column(
      children: _transactions
          .map(
            (entry) => Padding(
              padding: const EdgeInsets.only(bottom: 10),
              child: _TransactionCard(
                entry: entry,
                currency: entry['currency']?.toString() ?? _currency,
                precision:
                    (entry['currency_minor_units'] as num?)?.toInt() ?? 2,
              ),
            ),
          )
          .toList(),
    );
  }

  Widget _buildTabTeaser() {
    final title = [
      'App Reviews',
      'Earnings',
      'Ad Shares',
      'Wallets',
    ][_selectedTab];
    final desc = [
      'Discover apps available for review.',
      'Your wallet balances and recent transactions.',
      'Browse sponsored campaigns to share.',
      'Manage your earnings and top-up funds.',
    ][_selectedTab];
    if (_selectedTab == 3) return Column(children: [_buildWalletGrid()]);
    if (_selectedTab == 1) return Column(children: [_buildWalletGrid()]);
    return Container(
      padding: const EdgeInsets.all(22),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(17),
      ),
      child: Column(
        children: [
          Icon(
            _selectedTab == 0
                ? Icons.rate_review_outlined
                : Icons.campaign_outlined,
            color: brandGreen,
            size: 34,
          ),
          const SizedBox(height: 8),
          Text(
            title,
            style: const TextStyle(
              fontSize: 17,
              color: _dashboardInk,
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 5),
          Text(
            desc,
            textAlign: TextAlign.center,
            style: const TextStyle(color: mutedInk, fontSize: 12),
          ),
          const SizedBox(height: 12),
          const Text(
            'Your active subscription unlocks available tasks.',
            style: TextStyle(color: mutedInk, fontSize: 11),
          ),
        ],
      ),
    );
  }

  Widget _buildErrorCard() => Container(
    padding: const EdgeInsets.all(13),
    decoration: BoxDecoration(
      color: const Color(0xFFFFF0ED),
      borderRadius: BorderRadius.circular(13),
    ),
    child: Row(
      children: [
        const Icon(
          Icons.cloud_off_outlined,
          color: Color(0xFFB44A39),
          size: 19,
        ),
        const SizedBox(width: 9),
        Expanded(
          child: Text(
            _error!,
            style: const TextStyle(color: Color(0xFF8B4538), fontSize: 11),
          ),
        ),
        TextButton(onPressed: _loadDashboard, child: const Text('Retry')),
      ],
    ),
  );

  Widget _buildBottomBar() {
    const items = [
      (Icons.home_rounded, 'Home'),
      (Icons.hourglass_bottom_rounded, 'History'),
      (Icons.person_rounded, 'Profile'),
      (Icons.account_balance_wallet_rounded, 'Payout'),
    ];
    return Container(
      padding: const EdgeInsets.fromLTRB(10, 8, 10, 4),
      decoration: BoxDecoration(
        color: Colors.white,
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: .06),
            blurRadius: 12,
            offset: const Offset(0, -3),
          ),
        ],
      ),
      child: SafeArea(
        top: false,
        child: Row(
          children: List.generate(items.length, (index) {
            final selected = _selectedNav == index;
            return Expanded(
              child: InkWell(
                onTap: () {
                  if (index == 0) {
                    setState(() => _selectedNav = index);
                  } else {
                    _notReady(items[index].$2);
                  }
                },
                child: Padding(
                  padding: const EdgeInsets.symmetric(vertical: 4),
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Icon(
                        items[index].$1,
                        color: selected ? brandGreen : const Color(0xFF525B66),
                        size: 25,
                      ),
                      const SizedBox(height: 3),
                      Text(
                        items[index].$2,
                        style: TextStyle(
                          color: selected
                              ? brandGreen
                              : const Color(0xFF616A74),
                          fontSize: 11,
                          fontWeight: selected
                              ? FontWeight.w800
                              : FontWeight.w500,
                        ),
                      ),
                      const SizedBox(height: 3),
                      Container(
                        width: 32,
                        height: 2,
                        decoration: BoxDecoration(
                          color: selected
                              ? const Color(0xFFE84D3F)
                              : Colors.transparent,
                          borderRadius: BorderRadius.circular(3),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            );
          }),
        ),
      ),
    );
  }
}

class _FeaturedHomeCard extends StatelessWidget {
  const _FeaturedHomeCard({
    required this.api,
    required this.preferredCurrency,
    required this.kind,
    required this.item,
    required this.onTap,
  });
  final AuthApi api;
  final String preferredCurrency;
  final ListingKind kind;
  final Map<String, dynamic> item;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final isApp = kind == ListingKind.appReview;
    final app = item['app'] is Map<String, dynamic>
        ? item['app'] as Map<String, dynamic>
        : const <String, dynamic>{};
    final title = isApp
        ? item['name']?.toString() ?? app['name']?.toString() ?? 'Featured app'
        : item['title']?.toString() ?? 'Featured ad';
    final subtitle = isApp
        ? item['title']?.toString() ?? app['store']?.toString() ?? 'App review'
        : 'Sponsored share';
    final image = isApp
        ? item['cover_image']?.toString()
        : item['creative_path']?.toString();
    final sourceCurrency = item['currency']?.toString() ?? 'USD';
    final sourceAmount = isApp
        ? ((item['review_reward_minor'] as num?)?.toDouble() ??
                  ((item['price'] as num?)?.toDouble() ?? 0) * 100) /
              math.pow(10, (item['currency_minor_units'] as num?)?.toInt() ?? 2)
        : double.tryParse(item['share_reward']?.toString() ?? '') ?? 0;
    final converted = api.convertMajor(
      sourceAmount,
      sourceCurrency,
      preferredCurrency,
    );
    final shownCurrency = converted == null
        ? sourceCurrency
        : preferredCurrency;
    final shownAmount = converted ?? sourceAmount;
    final reward =
        '${_formatMajor(shownAmount, shownCurrency)} ${isApp ? 'review' : 'share'} reward';
    return SizedBox(
      width: 265,
      child: Material(
        color: Colors.white,
        borderRadius: BorderRadius.circular(15),
        child: InkWell(
          onTap: onTap,
          borderRadius: BorderRadius.circular(15),
          child: Container(
            padding: const EdgeInsets.all(11),
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(15),
              border: Border.all(color: const Color(0xFFE4EAE4)),
            ),
            child: Row(
              children: [
                Container(
                  width: 66,
                  height: 66,
                  clipBehavior: Clip.antiAlias,
                  decoration: BoxDecoration(
                    color: softGreen,
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: image != null && image.startsWith('http')
                      ? Image.network(
                          image,
                          fit: BoxFit.cover,
                          errorBuilder: (_, _, _) => Icon(
                            isApp
                                ? Icons.apps_rounded
                                : Icons.campaign_outlined,
                            color: brandGreen,
                          ),
                        )
                      : Icon(
                          isApp ? Icons.apps_rounded : Icons.campaign_outlined,
                          color: brandGreen,
                        ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 7,
                          vertical: 3,
                        ),
                        decoration: BoxDecoration(
                          color: const Color(0xFFFFF0EC),
                          borderRadius: BorderRadius.circular(10),
                        ),
                        child: const Text(
                          'FEATURED',
                          style: TextStyle(
                            color: Color(0xFFD24D3F),
                            fontSize: 8,
                            fontWeight: FontWeight.w900,
                            letterSpacing: .5,
                          ),
                        ),
                      ),
                      const SizedBox(height: 5),
                      Text(
                        title,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          color: _dashboardInk,
                          fontWeight: FontWeight.w800,
                          fontSize: 12,
                        ),
                      ),
                      Text(
                        subtitle,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(color: mutedInk, fontSize: 9),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        reward,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          color: brandGreen,
                          fontSize: 9,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                    ],
                  ),
                ),
                const Icon(Icons.chevron_right_rounded, color: mutedInk),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _WalletCard extends StatelessWidget {
  const _WalletCard({
    required this.title,
    required this.icon,
    required this.balance,
    required this.pending,
    required this.currency,
    required this.onTap,
  });
  final String title;
  final IconData icon;
  final String balance;
  final int pending;
  final String currency;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => Material(
    color: Colors.white,
    borderRadius: BorderRadius.circular(15),
    elevation: 1,
    shadowColor: const Color(0x0B15251D),
    clipBehavior: Clip.antiAlias,
    child: InkWell(
      onTap: onTap,
      child: SizedBox(
        height: 132,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(14, 12, 13, 11),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Container(
                    width: 39,
                    height: 39,
                    decoration: const BoxDecoration(
                      color: Color(0xFFE8F1EA),
                      shape: BoxShape.circle,
                    ),
                    child: Icon(icon, color: brandGreen, size: 22),
                  ),
                  const Spacer(),
                  const Icon(
                    Icons.chevron_right_rounded,
                    color: Color(0xFF818992),
                    size: 23,
                  ),
                ],
              ),
              const Spacer(),
              Text(
                title,
                style: const TextStyle(
                  color: _dashboardInk,
                  fontSize: 14,
                  fontWeight: FontWeight.w800,
                ),
              ),
              const SizedBox(height: 4),
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  const Text(
                    'Balance',
                    style: TextStyle(color: Color(0xFF858D95), fontSize: 11),
                  ),
                  Text(
                    balance,
                    style: const TextStyle(
                      color: brandGreen,
                      fontSize: 16,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ],
              ),
              if (pending > 0)
                Padding(
                  padding: const EdgeInsets.only(top: 2),
                  child: Text(
                    'Pending ${_formatMinor(pending, currency)}',
                    style: const TextStyle(
                      color: Color(0xFF9A866A),
                      fontSize: 9,
                    ),
                  ),
                ),
            ],
          ),
        ),
      ),
    ),
  );
}

class _TransactionCard extends StatelessWidget {
  const _TransactionCard({
    required this.entry,
    required this.currency,
    required this.precision,
  });
  final Map<String, dynamic> entry;
  final String currency;
  final int precision;

  @override
  Widget build(BuildContext context) {
    final direction = entry['direction']?.toString() ?? '';
    final credit = direction == 'credit';
    final minor = (entry['amount_minor'] as num?)?.toInt() ?? 0;
    final title = entry['description']?.toString().trim().isNotEmpty == true
        ? entry['description'].toString()
        : _humanize(entry['entry_type']?.toString() ?? 'Wallet transaction');
    final status = entry['status']?.toString() ?? '';
    final date = _shortDate(entry['created_at']?.toString());
    return Container(
      height: 77,
      padding: const EdgeInsets.symmetric(horizontal: 13),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        border: const Border(
          left: BorderSide(color: Color(0xFFE84D3F), width: 4),
        ),
        boxShadow: const [
          BoxShadow(
            color: Color(0x0A15251D),
            blurRadius: 8,
            offset: Offset(0, 3),
          ),
        ],
      ),
      child: Row(
        children: [
          Container(
            width: 43,
            height: 43,
            decoration: const BoxDecoration(
              color: Color(0xFFE8F1EA),
              shape: BoxShape.circle,
            ),
            child: Icon(
              credit ? Icons.south_west_rounded : Icons.north_east_rounded,
              color: brandGreen,
              size: 22,
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: _dashboardInk,
                    fontSize: 12,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  '$date${status.isNotEmpty ? '  •  ${_humanize(status)}' : ''}',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: Color(0xFF8A9199),
                    fontSize: 10,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(width: 6),
          Text(
            '${credit ? '+' : '−'}${_formatMinor(minor, currency)}',
            style: TextStyle(
              color: credit ? brandGreen : const Color(0xFF5B6672),
              fontWeight: FontWeight.w800,
              fontSize: 14,
            ),
          ),
          const Icon(
            Icons.chevron_right_rounded,
            color: Color(0xFF8A9199),
            size: 21,
          ),
        ],
      ),
    );
  }
}

String _formatMinor(int amount, String currency) {
  final digits = _currencyPrecision(currency);
  final divisor = math.pow(10, digits);
  final value = amount / divisor;
  final symbols = {
    'USD': r'$',
    'NGN': '₦',
    'GBP': '£',
    'EUR': '€',
    'GHS': '₵',
    'ZAR': 'R',
    'KES': 'KSh',
    'CAD': r'$',
    'AUD': r'$',
    'INR': '₹',
  };
  final symbol =
      symbols[currency.toUpperCase()] ?? '${currency.toUpperCase()} ';
  return '$symbol${value.toStringAsFixed(digits)}';
}

int _currencyPrecision(String currency) {
  const zero = {'JPY', 'KRW', 'XOF', 'XAF', 'CLP', 'BIF', 'UGX', 'RWF', 'TZS'};
  const three = {'KWD', 'BHD', 'TND'};
  return zero.contains(currency.toUpperCase())
      ? 0
      : (three.contains(currency.toUpperCase()) ? 3 : 2);
}

String _formatMajor(double amount, String currency) {
  final digits = _currencyPrecision(currency);
  final symbols = {
    'USD': r'$',
    'NGN': '₦',
    'GBP': '£',
    'EUR': '€',
    'GHS': '₵',
    'ZAR': 'R',
    'KES': 'KSh',
    'CAD': r'$',
    'AUD': r'$',
    'INR': '₹',
  };
  return '${symbols[currency.toUpperCase()] ?? '${currency.toUpperCase()} '}${amount.toStringAsFixed(digits)}';
}

String _humanize(String value) => value
    .replaceAll('_', ' ')
    .split(' ')
    .map(
      (part) =>
          part.isEmpty ? part : '${part[0].toUpperCase()}${part.substring(1)}',
    )
    .join(' ');

String _shortDate(String? raw) {
  if (raw == null) return 'Recent';
  final date = DateTime.tryParse(raw)?.toLocal();
  if (date == null) return 'Recent';
  const months = [
    'Jan',
    'Feb',
    'Mar',
    'Apr',
    'May',
    'Jun',
    'Jul',
    'Aug',
    'Sep',
    'Oct',
    'Nov',
    'Dec',
  ];
  final hour = date.hour == 0
      ? 12
      : (date.hour > 12 ? date.hour - 12 : date.hour);
  final minute = date.minute.toString().padLeft(2, '0');
  final suffix = date.hour >= 12 ? 'PM' : 'AM';
  return '${hour.toString().padLeft(2, '0')}:$minute $suffix  ${months[date.month - 1]} ${date.day}';
}
