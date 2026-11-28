import 'dart:async';
import 'dart:math' as math;

import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:review/screens/addons_screen.dart';
import 'package:review/features/vas/presentation/screens/vas_screens.dart';
import 'package:review/screens/referral_team_screen.dart';
import 'package:review/widgets/quick_slideshow.dart';
import 'package:url_launcher/url_launcher.dart';

import '../core/links/referral_link.dart';
import '../core/theme/app_colors.dart';
import '../core/currency/money.dart';
import '../core/utils/helper.dart';
import '../features/history/presentation/widgets/activity_transaction_tile.dart';
import '../features/marketplace/presentation/listing_kind.dart';
import '../features/social_follow/presentation/social_handles_dialog.dart';
import '../features/wallets/presentation/wallet_detail_screens.dart';
import '../services/auth_api.dart';
import '../widgets/auth_components.dart';
import '../widgets/advanced_app_drawer.dart';
import '../widgets/withdrawal_pin_sheet.dart';
import 'frontend/landing_sreen.dart';
import 'history_screen.dart';
import 'daily_tasks_screen.dart';
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
import 'support_ticket_screen.dart';
import 'about_screen.dart';
import 'topup_screen.dart';
import 'wallet_transfer_screen.dart';
import 'user_ranking_screen.dart';
import 'payment_details_setup_screen.dart';
import 'campaign_creation_screen.dart';
import '../services/push_notification_service.dart';

class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key, required this.result, required this.api});

  final AuthResult result;
  final AuthApi api;

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen>
    with SingleTickerProviderStateMixin, WidgetsBindingObserver {
  static const _earningsVisibilityKey = 'tlstore.hide_total_earnings';
  static const _deviceStorage = FlutterSecureStorage();

  late final AnimationController _drawerController;
  late final Animation<double> _drawerProgress;
  Future<void>? _earningsVisibilityReady;
  bool _hideTotalEarnings = false;
  bool _openingWithdrawalPin = false;
  int _unreadNotificationCount = 0;
  bool _loading = true;
  String? _error;
  List<Map<String, dynamic>> _wallets = const [];
  List<Map<String, dynamic>> _transactions = const [];
  List<Map<String, dynamic>> _announcements = const [];
  Map<String, dynamic> _sessionUser = const {};
  final Set<int> _dismissedAnnouncements = {};
  bool _announcementDialogShowing = false;
  bool _announcementPromptShown = false;
  bool _socialPromptShown = false;
  bool _currencyUpdating = false;
  int _selectedTab = 1;
  int _selectedNav = 0;
  AppDrawerDestination? _activeDrawerDestination;
  StreamSubscription<void>? _notificationTapSubscription;
  StreamSubscription<void>? _notificationReceivedSubscription;

  String get _displayName {
    final name =
        _sessionUser['full_name'] ??
        _sessionUser['name'] ??
        _sessionUser['username'];
    final value = name?.toString().trim() ?? '';
    return value.isEmpty ? 'there' : value.split(RegExp(r'\s+')).first;
  }

  String get _currency {
    final preferred = widget.api.preferredCurrency.isNotEmpty
        ? widget.api.preferredCurrency
        : _sessionUser['preferred_currency']?.toString();
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
    WidgetsBinding.instance.addObserver(this);
    _earningsVisibilityReady = _restoreEarningsVisibility();
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
    _loadUnreadNotificationCount();
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
    _notificationReceivedSubscription = PushNotificationService
        .instance
        .notificationReceived
        .listen((_) => unawaited(_loadUnreadNotificationCount()));
    unawaited(
      PushNotificationService.instance.connect(widget.api, widget.result.token),
    );
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted && PushNotificationService.instance.consumePendingOpen()) {
        _openNotifications();
      }
    });
  }

  Future<void> _restoreEarningsVisibility() async {
    try {
      final hidden = await _deviceStorage.read(key: _earningsVisibilityKey);
      if (mounted && hidden != null) {
        setState(() => _hideTotalEarnings = hidden == 'true');
      }
    } catch (_) {
      // Keep earnings visible if device storage is unavailable.
    }
  }

  Future<void> _toggleTotalEarningsVisibility() async {
    await _earningsVisibilityReady;
    if (!mounted) return;

    final hidden = !_hideTotalEarnings;
    setState(() => _hideTotalEarnings = hidden);
    try {
      await _deviceStorage.write(
        key: _earningsVisibilityKey,
        value: hidden.toString(),
      );
    } catch (_) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Could not save the earnings display preference.'),
          ),
        );
      }
    }
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _notificationTapSubscription?.cancel();
    _notificationReceivedSubscription?.cancel();
    _drawerController.dispose();
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      unawaited(_loadUnreadNotificationCount());
    }
  }

  void _openNotifications() {
    unawaited(_openNotificationsAndRefreshCount());
  }

  Future<void> _openNotificationsAndRefreshCount() async {
    await _pushPage(
      NotificationsScreen(api: widget.api, token: widget.result.token),
      0,
    );
    await _loadUnreadNotificationCount();
  }

  Future<void> _loadUnreadNotificationCount() async {
    try {
      final response = await widget.api.unreadNotificationCount(
        widget.result.token,
      );
      final data = response['data'];
      final count = data is Map ? data['unread_count'] : null;
      if (!mounted || count is! num) return;
      setState(() => _unreadNotificationCount = count.toInt().clamp(0, 9999));
    } on AuthException {
      // Keep the last known badge count if the count endpoint is unavailable.
    }
  }

  Future<void> _choosePreferredCurrency() async {
    if (_currencyUpdating) return;

    try {
      // Fetch currencies from API.
      final response = await widget.api.appCurrencies();

      final rows = response['data'];

      final currencies = rows is List
          ? rows
                .whereType<Map>()
                .map((row) => Map<String, dynamic>.from(row))
                .toList()
          : <Map<String, dynamic>>[];

      if (!mounted || currencies.isEmpty) {
        if (mounted) {
          showAuthMessage(
            context,
            'Currency options are unavailable right now.',
          );
        }
        return;
      }

      // Only display currencies supported by the application.
      final apiCodes = currencies
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

      final current = _currency.toUpperCase();

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

      if (!mounted || selected == null || selected.toUpperCase() == current) {
        return;
      }

      setState(() => _currencyUpdating = true);

      final result = await widget.api.patch('me', widget.result.token, {
        'preferred_currency': selected.toUpperCase(),
      });

      final data = result['data'];

      if (data is Map) {
        _sessionUser.addAll(Map<String, dynamic>.from(data));
      }

      widget.api.setPreferredCurrency(selected);

      if (mounted) {
        setState(() {});

        showAuthMessage(
          context,
          'Preferred currency updated to ${selected.toUpperCase()}.',
        );
      }
    } on AuthException catch (error) {
      if (mounted) {
        showAuthMessage(context, error.message);
      }
    } finally {
      if (mounted) {
        setState(() => _currencyUpdating = false);
      }
    }
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
      _loadUnreadNotificationCount(),
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
          onLogout: _logout,
          onDeleted: _accountDeleted,
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
      await Future.wait([_loadDashboard()]);
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
                child: ScaffoldMessenger(
                  // The drawer Scaffold sits behind the dashboard Scaffold on
                  // this same route. Keep its messenger separate so a
                  // dashboard snackbar is not rendered twice with the same
                  // Hero tag.
                  child: AdvancedAppDrawer(
                    user: _sessionUser,
                    preferredCurrency: _currency,
                    onCurrencyTap: _choosePreferredCurrency,
                    selectedDestination: _activeDrawerDestination,
                    onSelect: _handleDrawerSelection,
                  ),
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
                  _buildAccountSetupCard(),
                  _buildInlineAnnouncements(),
                  _buildQuickAccess(),
                  const SizedBox(height: 14),
                  _buildCategoryTabs(),
                  const SizedBox(height: 19),
                  _buildWalletGrid(),
                  const SizedBox(height: 23),
                  _buildRecentHeading(),
                  const SizedBox(height: 10),
                  _buildTransactions(),

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

  Widget _buildQuickAccess() {
    final scheme = Theme.of(context).colorScheme;

    final slides = <QuickySlide>[
      QuickySlide(
        actionKey: 'referrals',
        icon: Icons.group_add_rounded,
        eyebrow: 'Share The Good News',
        title: 'Invite friends. Earn together.',
        subtitle: 'Share your referral link and follow your rewards.',
        ctaLabel: 'Refer & earn',
      ),
      QuickySlide(
        actionKey: 'subscriptions',
        icon: Icons.workspace_premium_rounded,
        eyebrow: 'Find Your Next Level',
        title: 'Choose a package that fits.',
        subtitle: 'See your active plans and explore more access.',
        ctaLabel: 'View packages',
      ),
      QuickySlide(
        actionKey: 'vas:data',
        serviceKey: 'data',
        icon: Icons.wifi_rounded,
        eyebrow: 'Stay Connected',
        title: 'Get data for your network.',
        subtitle: 'Pick a network and bundle in a few quick steps.',
        ctaLabel: 'Buy data',
      ),
      QuickySlide(
        actionKey: 'vas:airtime',
        serviceKey: 'airtime',
        icon: Icons.phone_android_rounded,
        eyebrow: 'Instant Recharge',
        title: 'Top up airtime in a tap.',
        subtitle: 'Recharge your line securely from your top-up fund.',
        ctaLabel: 'Buy airtime',
      ),
      QuickySlide(
        actionKey: 'tasks',
        icon: Icons.task_alt_rounded,
        eyebrow: 'Your Next Reward',
        title: 'Today’s tasks are waiting.',
        subtitle: 'Review apps and share ads to earn rewards.',
        ctaLabel: 'Explore tasks',
      ),
    ];

    return Padding(
      padding: const EdgeInsets.only(top: 18),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(Icons.auto_awesome_rounded, color: scheme.primary, size: 19),
              const SizedBox(width: 8),
              Text(
                'Quick access',
                style: Theme.of(context).textTheme.titleMedium
                    ?.copyWith(fontWeight: FontWeight.w800),
              ),
              const Spacer(),
              Text(
                'Swipe to explore',
                style: Theme.of(context).textTheme.labelSmall
                    ?.copyWith(color: scheme.onSurfaceVariant),
              ),
            ],
          ),
          const SizedBox(height: 10),
          QuickSlideShow(
            slides: slides,
            height: kSlideShowHeight,
            accent: Color(0xFFD7F52F),
            foreground: scheme.primary,
            onSlideTap: _openQuickAccessSlide,
          ),
        ],
      ),
    );
  }

  Future<void> _openQuickAccessSlide(int index, QuickySlide slide) async {
    switch (slide.actionKey) {
      case 'referrals':
        _pushPage(
          ReferralsScreen(
            api: widget.api,
            token: widget.result.token,
            user: _sessionUser,
          ),
          0,
        );
        return;
      case 'subscriptions':
        _pushPage(
          SubscriptionsScreen(
            api: widget.api,
            token: widget.result.token,
            user: _sessionUser,
          ),
          0,
        );
        return;
      case 'tasks':
        _pushPage(
          DailyTasksScreen(
            api: widget.api,
            token: widget.result.token,
            user: _sessionUser,
          ),
          0,
        );
        return;
      case 'vas:data':
      case 'vas:airtime':
        await _openQuickVasService(slide);
        return;
    }
  }

  Future<void> _openQuickVasService(QuickySlide slide) async {
    try {
      final response = await widget.api.vasCatalog(widget.result.token);
      final catalog = response['data'];
      if (catalog is! Map || catalog['enabled'] != true) {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(
              content: Text(
                'Airtime and data services are not available right now.',
              ),
            ),
          );
        }
        return;
      }
      final services = catalog['services'];
      final key = slide.serviceKey;
      if (services is! List || key == null) return;
      Map<String, dynamic>? service;
      for (final item in services.whereType<Map>()) {
        if ('${item['key']}' == key) {
          service = Map<String, dynamic>.from(item);
          break;
        }
      }
      if (!mounted) return;
      if (service == null || catalog['provider_ready'] != true) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('${slide.title} is unavailable right now.')),
        );
        return;
      }
      _pushPage(
        VasPurchaseScreen(
          api: widget.api,
          token: widget.result.token,
          service: service,
          currency: _currency,
          initialProviderId: slide.providerId,
          securityPinSet:
              _sessionUser['security_pin_set'] == true ||
              _sessionUser['security_pin_set'] == 1 ||
              _sessionUser['security_pin_set'] == '1',
        ),
        0,
      );
    } on AuthException catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(error.message)));
      }
    }
  }

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
      case AppDrawerDestination.valueAddedServices:
        _pushPage(
          VasHomeScreen(
            api: widget.api,
            token: widget.result.token,
            currency: _currency,
            securityPinSet:
                _sessionUser['security_pin_set'] == true ||
                _sessionUser['security_pin_set'] == 1 ||
                _sessionUser['security_pin_set'] == '1' ||
                _sessionUser['has_security_pin'] == true ||
                _sessionUser['has_security_pin'] == 1 ||
                _sessionUser['has_security_pin'] == '1',
          ),
          0,
        );
      case AppDrawerDestination.topUp:
        _pushPage(TopUpScreen(api: widget.api, token: widget.result.token), 0);
      case AppDrawerDestination.campaigns:
        _pushPage(
          CampaignCreationScreen(api: widget.api, token: widget.result.token),
          0,
        );
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
      case AppDrawerDestination.rankings:
        _pushPage(
          UserRankingScreen(api: widget.api, token: widget.result.token),
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
          DailyTasksScreen(
            api: widget.api,
            token: widget.result.token,
            user: _sessionUser,
          ),
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
            onLogout: _logout,
            onDeleted: _accountDeleted,
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
        _pushPage(
          SupportTicketsScreen(
            api: widget.api,
            token: widget.result.token,
            user: _sessionUser,
          ),
          0,
        );
      case AppDrawerDestination.signOut:
        _logout();
    }
  }

  Widget _buildHeader() {
    final hasAddon = _sessionUser['has_addon']?.toInt() == 1;

    return SizedBox(
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
                Navigator.of(context).push(
                  MaterialPageRoute(
                    builder: (_) => LandingScreen(
                      api: widget.api,
                      isUser: _sessionUser,
                      token: widget.result.token,
                    ),
                  ),
                );
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

          // Addon image (visible only when user has addon)
          if (hasAddon) ...[
            GestureDetector(
              onTap: () {
                _pushPage(
                  AddonsScreen(
                    api: widget.api,
                    token: widget.result.token,
                    user: _sessionUser,
                    onLogout: _logout,
                    onDeleted: _accountDeleted,
                  ),
                  0,
                );
              },
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 1),
                child: Container(
                  width: 44,
                  height: 44,
                  decoration: BoxDecoration(
                    shape: BoxShape.circle,
                    color: Colors.white,
                    border: Border.all(color: brandGreen, width: 1.5),
                  ),
                  padding: const EdgeInsets.all(5),
                  child: Icon(
                    Icons.verified_user_outlined,
                    color: brandGreen,
                    size: 26,
                  ),
                ),
              ),
            ),
          ],

          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 7),
            child: _HeaderCircleButton(
              tooltip: 'Notifications',
              onTap: _openNotifications,
              child: Stack(
                clipBehavior: Clip.none,
                children: [
                  const Icon(
                    Icons.notifications_none_rounded,
                    color: brandGreen,
                    size: 26,
                  ),
                  Positioned(
                    top: -8,
                    right: -10,
                    child: _unreadNotificationCount == 0
                        ? const SizedBox.shrink()
                        : Container(
                            constraints: const BoxConstraints(
                              minWidth: 20,
                              minHeight: 20,
                            ),
                            alignment: Alignment.center,
                            padding: const EdgeInsets.symmetric(horizontal: 5),
                            decoration: const BoxDecoration(
                              color: Color(0xFFF24B3D),
                              shape: BoxShape.circle,
                            ),
                            child: Text(
                              _unreadNotificationCount > 99
                                  ? '99+'
                                  : '$_unreadNotificationCount',
                              style: const TextStyle(
                                color: Colors.white,
                                fontSize: 11,
                                fontWeight: FontWeight.w800,
                              ),
                            ),
                          ),
                  ),
                ],
              ),
            ),
          ),

          // Clickable avatar container
          Padding(
            padding: const EdgeInsets.only(left: 1, right: 15),
            child: GestureDetector(
              onTap: () {
                _pushPage(
                  ProfileScreen(
                    api: widget.api,
                    token: widget.result.token,
                    user: _sessionUser,
                    onLogout: _logout,
                    onDeleted: _accountDeleted,
                  ),
                  0,
                );
              },
              child: Container(
                width: 54,
                height: 54,
                padding: const EdgeInsets.all(4),
                decoration: const BoxDecoration(
                  shape: BoxShape.circle,
                  gradient: LinearGradient(
                    begin: Alignment.topLeft,
                    end: Alignment.bottomRight,
                    colors: [
                      Color(0xFFF28A18),
                      Color(0xFFE4C344),
                      Color(0xFF24784C),
                      Color(0xFFEF553F),
                    ],
                  ),
                ),
                child: Container(
                  decoration: const BoxDecoration(
                    color: brandGreen,
                    shape: BoxShape.circle,
                  ),
                  child: ClipOval(child: _avatar()),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _avatar() {
    final avatar = _sessionUser['avatar']?.toString();
    return ClipOval(
      child: avatar != null && avatar.isNotEmpty
          ? CachedNetworkImage(
              imageUrl: avatar,
              fit: BoxFit.cover,
              errorWidget: (_, _, _) => _avatarPlaceholder(),
            )
          : _avatarPlaceholder(),
    );
  }

  Widget _avatarPlaceholder() => const Center(
    child: Text(
      'TL',
      style: TextStyle(
        color: Colors.white,
        fontSize: 19,
        fontWeight: FontWeight.w800,
        letterSpacing: .2,
      ),
    ),
  );

  Widget _buildEarningsActions() {
    final scheme = Theme.of(context).colorScheme;

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 2, vertical: 0),
      decoration: BoxDecoration(
        color: Colors.black.withValues(alpha: .92),
        borderRadius: BorderRadius.circular(8),
        border: Border.all(color: scheme.primary.withValues(alpha: .6)),
        boxShadow: [
          BoxShadow(
            color: scheme.shadow.withValues(alpha: .10),
            blurRadius: 18,
            offset: const Offset(0, 7),
          ),
        ],
      ),
      child: Row(
        children: [
          // Add button
          SizedBox(
            width: 38,
            height: 38,
            child: Material(
              color: scheme.onSecondary,
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.only(
                  topLeft: Radius.circular(6),
                  topRight: Radius.circular(16),
                  bottomLeft: Radius.circular(6),
                  bottomRight: Radius.circular(16),
                ),
              ),
              elevation: 1,
              child: InkWell(
                onTap: () {
                  _pushPage(
                    TopUpScreen(api: widget.api, token: widget.result.token),
                    0,
                  );
                },
                customBorder: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(19),
                ),
                child: Icon(
                  Icons.add_rounded,
                  size: 23,
                  color: scheme.onSurface,
                ),
              ),
            ),
          ),
          const SizedBox(width: 8),

          // Messages button
          _earningsCircleButton(
            icon: Icons.receipt_long_rounded,
            onPressed: () {
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
            },
          ),
          const SizedBox(width: 6),

          // Withdraw button
          Expanded(
            child: SizedBox(
              height: 34,
              child: OutlinedButton.icon(
                onPressed: () {
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
                },
                icon: const Icon(Icons.apple, size: 15),
                label: const Text('Apps'),
                style: OutlinedButton.styleFrom(
                  backgroundColor: scheme.onSurface,
                  foregroundColor: scheme.onSecondary,
                  side: BorderSide(color: scheme.outlineVariant, width: 1),
                  textStyle: const TextStyle(
                    fontSize: 13,
                    fontWeight: FontWeight.w700,
                  ),
                  padding: const EdgeInsets.symmetric(horizontal: 5),
                  shape: const StadiumBorder(),
                ),
              ),
            ),
          ),
          const SizedBox(width: 6),

          // Upgrade button
          Expanded(
            child: SizedBox(
              height: 34,
              child: ElevatedButton.icon(
                onPressed: () {
                  _pushPage(
                    DailyTasksScreen(
                      api: widget.api,
                      token: widget.result.token,
                      user: _sessionUser,
                    ),
                    0,
                  );
                },
                icon: const Icon(Icons.campaign_rounded, size: 15),
                label: const Text('Ads'),
                style: ElevatedButton.styleFrom(
                  backgroundColor: scheme.primary,
                  foregroundColor: scheme.onPrimary,
                  elevation: 0,
                  textStyle: const TextStyle(
                    fontSize: 13,
                    fontWeight: FontWeight.w700,
                  ),
                  padding: const EdgeInsets.symmetric(horizontal: 5),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.only(
                      topLeft: Radius.circular(16),
                      topRight: Radius.circular(6),
                      bottomLeft: Radius.circular(16),
                      bottomRight: Radius.circular(6),
                    ),
                  ),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildEarningsCard() {
    final total = _formatMajor(_totalEarningsMajor, _currency);
    final totalDisplay = _hideTotalEarnings
        ? '${_currencySymbol(_currency)}●●●●●'
        : total;
    final username = _sessionUser['username']?.toString() ?? _displayName;
    final memberStat = switch (_sessionUser['is_member']) {
      1 => 'Member',
      2 => 'Reviewer',
      _ => 'Freebie',
    };

    return SizedBox(
      height: 185,
      child: Stack(
        clipBehavior: Clip.none,
        children: [
          Container(
            height: 185,
            padding: const EdgeInsets.fromLTRB(15, 14, 13, 12),
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
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
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
                        Text(
                          'Total Earnings',
                          style: TextStyle(
                            color: Colors.white.withValues(alpha: .95),
                            fontSize: 23,
                            fontWeight: FontWeight.w500,
                          ),
                        ),
                        IconButton(
                          onPressed: _toggleTotalEarningsVisibility,
                          tooltip: _hideTotalEarnings
                              ? 'Show total earnings'
                              : 'Hide total earnings',
                          color: Colors.white.withValues(alpha: .95),
                          iconSize: 26,
                          visualDensity: VisualDensity.compact,
                          icon: Icon(
                            _hideTotalEarnings
                                ? Icons.visibility_rounded
                                : Icons.visibility_off_rounded,
                          ),
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
                            totalDisplay,
                            style: const TextStyle(
                              color: Colors.white,
                              fontSize: 32,
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
                              Flexible(child: _heroMeta('Account', memberStat)),
                              Container(
                                width: 1,
                                height: 41,
                                margin: const EdgeInsets.symmetric(
                                  horizontal: 14,
                                ),
                                color: Colors.white.withValues(alpha: .65),
                              ),
                              Flexible(child: _heroMeta('Holder', username)),
                            ],
                          ),
                        ),
                        const SizedBox(width: 10),
                        OutlinedButton.icon(
                          onPressed: () => _notReady('Withdrawals'),
                          icon: const Icon(
                            Icons.arrow_forward_rounded,
                            size: 17,
                          ),
                          label: const Text('Withdraw'),
                          style: OutlinedButton.styleFrom(
                            foregroundColor: Colors.white,
                            side: const BorderSide(
                              color: Colors.white,
                              width: 1.2,
                            ),
                            textStyle: const TextStyle(
                              fontWeight: FontWeight.w800,
                            ),
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
          ),
        ],
      ),
    );
  }

  Widget _buildAccountSetupCard() {
    final contextData = _sessionUser['subscription_context'] is Map
        ? Map<String, dynamic>.from(_sessionUser['subscription_context'] as Map)
        : <String, dynamic>{};

    final hasPlan = contextData['active_marketplace'] is Map;
    final hasPin = _sessionUser['security_pin_set'] == true;
    final hasPayment = _sessionUser['payout_profile'] is Map;

    final completed =
        1 + (hasPlan ? 1 : 0) + (hasPin ? 1 : 0) + (hasPayment ? 1 : 0);

    if (completed >= 4) return const SizedBox.shrink();

    final nextTitle = !hasPlan
        ? 'Activate Plan'
        : !hasPin
        ? 'Set Withdrawal PIN'
        : 'Set Payment Details';

    final progress = completed / 4;
    final percentage = (progress * 100).round();

    final theme = Theme.of(context);
    final colors = theme.colorScheme;

    final primary = colors.primary;
    final secondary = colors.secondary;
    final surface = colors.surface;
    final onSurface = colors.onSurface;
    final muted = colors.onSurfaceVariant;

    final success = colors.tertiary;
    final outline = colors.outlineVariant;

    const accent = Color(0xFFF28A18);

    void continueSetup() {
      if (!hasPlan) {
        _pushPage(
          SubscriptionsScreen(
            api: widget.api,
            token: widget.result.token,
            user: _sessionUser,
          ),
          0,
        );
      } else if (!hasPin) {
        unawaited(_openWithdrawalPinSetup());
      } else {
        _pushPage(
          PaymentDetailsSetupScreen(
            api: widget.api,
            token: widget.result.token,
            user: _sessionUser,
          ),
          0,
        );
      }
    }

    return Container(
      margin: const EdgeInsets.only(top: 12),
      padding: const EdgeInsets.all(15),
      decoration: BoxDecoration(
        color: colors.surface,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: colors.outlineVariant, width: 0.8),
        boxShadow: [
          BoxShadow(
            color: colors.shadow.withOpacity(0.06),
            blurRadius: 14,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Header
          Row(
            children: [
              Container(
                width: 38,
                height: 38,
                decoration: BoxDecoration(
                  color: primary.withOpacity(0.09),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Icon(
                  Icons.rocket_launch_rounded,
                  color: primary,
                  size: 20,
                ),
              ),
              const SizedBox(width: 11),

              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Complete Your Setup',
                      style: Theme.of(context).textTheme.titleSmall?.copyWith(
                        fontWeight: FontWeight.w800,
                        fontSize: 14,
                        color: colors.onSurface,
                      ),
                    ),
                    const SizedBox(height: 3),
                    Text(
                      'A few more steps to get started',
                      style: TextStyle(fontSize: 11.5, color: muted),
                    ),
                  ],
                ),
              ),

              // Progress percentage
              Container(
                padding: const EdgeInsets.symmetric(
                  horizontal: 10,
                  vertical: 6,
                ),
                decoration: BoxDecoration(
                  color: colors.primaryContainer,
                  borderRadius: BorderRadius.circular(20),
                ),
                child: Text(
                  '$percentage%',
                  style: TextStyle(
                    color: colors.onPrimaryContainer,
                    fontWeight: FontWeight.w800,
                    fontSize: 12,
                  ),
                ),
              ),
            ],
          ),

          const SizedBox(height: 16),

          // Progress information
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                'Setup progress',
                style: TextStyle(
                  color: colors.onSurface,
                  fontSize: 11.5,
                  fontWeight: FontWeight.w600,
                ),
              ),
              Text(
                '$completed of 4 completed',
                style: TextStyle(
                  color: muted,
                  fontSize: 11,
                  fontWeight: FontWeight.w500,
                ),
              ),
            ],
          ),

          const SizedBox(height: 8),

          ClipRRect(
            borderRadius: BorderRadius.circular(10),
            child: LinearProgressIndicator(
              value: progress,
              minHeight: 7,
              backgroundColor: colors.surfaceContainerHighest,
              valueColor: AlwaysStoppedAnimation(colors.primary),
            ),
          ),

          const SizedBox(height: 15),

          // Next action
          Material(
            color: Colors.transparent,
            child: InkWell(
              onTap: continueSetup,
              borderRadius: BorderRadius.circular(13),
              child: Ink(
                width: double.infinity,
                padding: const EdgeInsets.symmetric(
                  horizontal: 15,
                  vertical: 12,
                ),
                decoration: BoxDecoration(
                  gradient: LinearGradient(
                    colors: [colors.primary, colors.secondary],
                  ),
                  borderRadius: BorderRadius.circular(13),
                ),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Text(
                      nextTitle,
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 13,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const SizedBox(width: 7),
                    const Icon(
                      Icons.arrow_forward_rounded,
                      color: Colors.white,
                      size: 17,
                    ),
                  ],
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Future<void> _openWithdrawalPinSetup() async {
    if (_openingWithdrawalPin) return;
    _openingWithdrawalPin = true;
    try {
      final saved = await showWithdrawalPinSheet(
        context,
        api: widget.api,
        token: widget.result.token,
        endpoint: 'me/security-pin',
        title: 'Create a withdrawal PIN',
        requireAccountPassword: _sessionUser['has_password'] == true,
        onSuccess: (response) {
          final data = response['data'];
          if (!mounted || data is! Map) return;
          setState(() => _sessionUser.addAll(Map<String, dynamic>.from(data)));
        },
      );
      if (saved && mounted) showAuthMessage(context, 'Withdrawal PIN saved.');
    } finally {
      _openingWithdrawalPin = false;
    }
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
        _categoryButton('Tasks', 3),
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
        } else if (index == 3) {
          _pushPage(
            DailyTasksScreen(
              api: widget.api,
              token: widget.result.token,
              user: _sessionUser,
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
      'review_group_b': 0,
      'review_group_c': 1,
      'topup_fund': 3,
      'share': 4,
      'referral': 5,
      'bonus': 6,
      'review': 2,
    };

    const details = {
      'review_group_b': (
        'Android reviews',
        Icons.android_rounded,
        Color(0xFF16A34A),
      ),
      'review_group_c': ('Apple reviews', Icons.apple, Color(0xFF16A34A)),
      'review': (
        'Legacy reviews',
        Icons.rate_review_rounded,
        Color(0xFF16A34A),
      ),
      'share': ('Share wallet', Icons.campaign_rounded, Color(0xFF16A34A)),
      'referral': (
        'Referral wallet',
        Icons.group_add_rounded,
        Color(0xFF16A34A),
      ),
      'bonus': ('Bonus wallet', Icons.card_giftcard_rounded, Color(0xFF16A34A)),
      'topup_fund': (
        'Top-up fund',
        Icons.account_balance_wallet_rounded,
        Color(0xFFE34234),
      ),
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
                a['currency']?.toString() ?? '',
              );
      });

    if (wallets.isEmpty) return const SizedBox.shrink();

    Widget buildWalletCard(Map<String, dynamic> wallet, double width) {
      final type = wallet['type']?.toString() ?? '';

      final info =
          details[type] ??
          (
            'Wallet',
            Icons.account_balance_wallet_rounded,
            const Color(0xFF18563D),
          );

      final currency = wallet['currency']?.toString() ?? _currency;

      final balance = (wallet['available_balance_minor'] as num?)?.toInt() ?? 0;

      final pending = (wallet['pending_balance_minor'] as num?)?.toInt() ?? 0;

      return SizedBox(
        width: width,
        child: _WalletCard(
          title: info.$1,
          icon: info.$2,
          color: info.$3,
          balance: moneyConverted(
            widget.api,
            balance,
            currency,
            _currencyPrecision(currency),
            _currency,
            isMinor: true,
          ),
          pending: moneyConverted(
            widget.api,
            pending,
            currency,
            _currencyPrecision(currency),
            _currency,
            isMinor: true,
          ),
          onTap: () => _openWallet(wallet),
        ),
      );
    }

    return LayoutBuilder(
      builder: (context, constraints) {
        const gap = 12.0;

        final cardWidth = (constraints.maxWidth - gap) / 2;
        final scrollCardWidth = constraints.maxWidth * 0.65;

        final firstRow = wallets.take(2).toList();
        final secondRow = wallets.skip(2).toList();

        return Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(
                    'Users Wallets [${wallets.length}]',
                    style: TextStyle(fontSize: 14, fontWeight: FontWeight.w700),
                  ),
                ),
                Text(
                  '',
                  style: TextStyle(fontSize: 16, fontWeight: FontWeight.w800),
                ),
              ],
            ),
            const SizedBox(height: 10),

            Wrap(
              spacing: gap,
              runSpacing: gap,
              children: firstRow.map((wallet) {
                return buildWalletCard(wallet, cardWidth);
              }).toList(),
            ),

            if (secondRow.isNotEmpty) ...[
              const SizedBox(height: 12),

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
          ],
        );
      },
    );
  }

  void _openWallet(Map<String, dynamic> wallet) {
    String referralCode = _sessionUser['referral_code']?.toString() ?? '';

    final page = switch (wallet['type']?.toString()) {
      'review' || 'review_group_b' || 'review_group_c' => ReviewWalletScreen(
        api: widget.api,
        token: widget.result.token,
        wallet: wallet,
      ),
      'share' => ShareWalletScreen(
        api: widget.api,
        token: widget.result.token,
        wallet: wallet,
      ),
      'referral' => ReferralTeamScreen(
        api: widget.api,
        token: widget.result.token,
        referralCode: referralCode,
        referralLink: ReferralLink.forCode(referralCode).toString(),
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

  Widget _buildRecentHeading() => Row(
    mainAxisAlignment: MainAxisAlignment.spaceBetween,
    children: [
      const Text(
        'Recent Transactions',
        style: TextStyle(fontSize: 16, fontWeight: FontWeight.w800),
      ),
      if (_transactions.length >= 5)
        TextButton.icon(
          onPressed: () => _notReady('Transaction history'),
          iconAlignment: IconAlignment.end,
          icon: const Icon(
            Icons.arrow_forward_rounded,
            color: Color(0xFFE84D3F),
            size: 15,
          ),
          label: const Text(
            'View more',
            style: TextStyle(
              color: brandGreen,
              fontSize: 12,
              fontWeight: FontWeight.w800,
            ),
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
              child: ActivityTransactionTile(
                transaction: entry,
                displayAmount: moneyConverted(
                  widget.api,
                  entry['amount_minor'],
                  entry['currency']?.toString() ?? _currency,
                  entry['currency_minor_units'],
                  _currency,
                  isMinor: true,
                ),
                compact: true,
                preferDescriptionTitle: true,
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
      'Tasks',
    ][_selectedTab];
    final desc = [
      'Discover apps available for review.',
      'Your wallet balances and recent transactions.',
      'Browse sponsored campaigns to share.',
      'View users active task for the day.',
    ][_selectedTab];
    if (_selectedTab == 1) return Column(children: [_buildWalletGrid()]);
    return Container(
      padding: const EdgeInsets.all(22),
      decoration: BoxDecoration(
        color: Theme.of(context).colorScheme.primary.withValues(alpha: 0.15),
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
            style: const TextStyle(fontSize: 17, fontWeight: FontWeight.w800),
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
        color: Theme.of(context).colorScheme.primary.withValues(alpha: 0.05),
        boxShadow: [
          BoxShadow(
            color: Theme.of(context).colorScheme.shadow.withValues(alpha: .08),
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
                        color: selected
                            ? Theme.of(context).colorScheme.primary
                            : Theme.of(context).colorScheme.onSurfaceVariant,
                        size: 25,
                      ),
                      const SizedBox(height: 3),
                      Text(
                        items[index].$2,
                        style: TextStyle(
                          color: selected
                              ? Theme.of(context).colorScheme.primary
                              : Theme.of(context).colorScheme.onSurfaceVariant,
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
                              ? Theme.of(context).colorScheme.secondary
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

  Widget _earningsCircleButton({
    required IconData icon,
    required VoidCallback onPressed,
  }) {
    final scheme = Theme.of(context).colorScheme;

    return SizedBox(
      width: 38,
      height: 38,
      child: Material(
        color: scheme.onSecondary,
        shape: const CircleBorder(),
        elevation: 1,
        child: InkWell(
          onTap: onPressed,
          customBorder: const CircleBorder(),
          child: Icon(icon, size: 23, color: scheme.onSurface),
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
    required this.onTap,
    this.color = brandGreen,
  });

  final String title;
  final IconData icon;
  final String balance;
  final String pending;
  final VoidCallback onTap;
  final Color color;

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;

    final textColor = Theme.of(context).colorScheme.onSurface;

    return Material(
      color: isDark
          ? const Color(0xFF202D27)
          : Theme.of(context).colorScheme.primary.withValues(alpha: 0.07),
      borderRadius: BorderRadius.circular(16),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: onTap,
        child: Container(
          height: 98,
          padding: const EdgeInsets.fromLTRB(10, 8, 10, 6),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(16),
            border: Border.all(color: color.withValues(alpha: 0.13)),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // ICON AND ARROW
              Row(
                crossAxisAlignment: CrossAxisAlignment.center,
                children: [
                  // WALLET ICON
                  Container(
                    width: 34,
                    height: 34,
                    decoration: BoxDecoration(
                      color: color.withValues(alpha: 0.12),
                      shape: BoxShape.circle,
                    ),
                    child: Icon(icon, color: color, size: 19),
                  ),

                  const SizedBox(width: 9),

                  // WALLET TITLE - TWO LINES
                  Expanded(
                    child: Text(
                      title.replaceAll(' ', '\n'),
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(
                        color: textColor,
                        fontSize: 12,
                        height: 1.2,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ),

                  const SizedBox(width: 3),

                  // ARROW
                  Icon(
                    Icons.arrow_outward_rounded,
                    color: textColor.withValues(alpha: 0.45),
                    size: 17,
                  ),
                ],
              ),

              const Spacer(),

              // BALANCE
              Row(
                children: [
                  Expanded(
                    child: Text(
                      balance,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(
                        color: color,
                        fontSize: 16,
                        fontWeight: FontWeight.w900,
                        letterSpacing: -0.4,
                      ),
                    ),
                  ),
                ],
              ),

              // PENDING BALANCE
              if (pending.isNotEmpty)
                Padding(
                  padding: const EdgeInsets.only(top: 3),
                  child: Text(
                    'Pending: $pending',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                      color: textColor.withValues(alpha: 0.55),
                      fontSize: 9,
                      fontWeight: FontWeight.w500,
                    ),
                  ),
                ),
            ],
          ),
        ),
      ),
    );
  }
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
  return '${_currencySymbol(currency)}${formatGroupedNumber(amount, digits)}';
}

String _currencySymbol(String currency) {
  const symbols = {
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
  final code = currency.toUpperCase();
  return symbols[code] ?? '$code ';
}

class _HeaderCircleButton extends StatelessWidget {
  const _HeaderCircleButton({
    required this.tooltip,
    required this.onTap,
    required this.child,
  });

  final String tooltip;
  final VoidCallback onTap;
  final Widget child;

  @override
  Widget build(BuildContext context) => Tooltip(
    message: tooltip,
    child: Material(
      color: Colors.white,
      elevation: 3,
      shadowColor: Colors.black.withValues(alpha: .12),
      shape: const CircleBorder(),
      child: InkWell(
        onTap: onTap,
        customBorder: const CircleBorder(),
        child: SizedBox(width: 44, height: 44, child: Center(child: child)),
      ),
    ),
  );
}
