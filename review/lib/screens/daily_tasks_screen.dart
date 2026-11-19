import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../core/currency/money.dart';
import '../features/ads/presentation/screens/ad_evidence_screen.dart';
import '../features/ads/presentation/screens/social_ad_task_screen.dart';
import '../features/marketplace/presentation/screens/review_task_screen.dart';
import '../features/marketplace/presentation/widgets/app_report_dialog.dart';
import '../features/marketplace/presentation/widgets/app_review_details_sheet.dart';
import '../features/marketplace/presentation/utils/app_review_reward.dart';
import '../services/media_url.dart';
import 'my_tasks_screen.dart';
import 'subscriptions_screen.dart';
import 'questionnaire_screen.dart';
import 'social_accounts_setup_screen.dart';
import '../services/auth_api.dart';
import '../widgets/store_filter_chips.dart';
import '../widgets/auth_components.dart';
import '../core/utils/helper.dart';

class DailyTasksScreen extends StatefulWidget {
  const DailyTasksScreen({
    super.key,
    required this.api,
    required this.token,
    this.user = const {},
  });

  final AuthApi api;
  final String token;
  final Map<String, dynamic> user;

  @override
  State<DailyTasksScreen> createState() => _DailyTasksScreenState();
}

class _DailyTasksScreenState extends State<DailyTasksScreen>
    with SingleTickerProviderStateMixin {
  late final TabController _tabs;
  final _search = TextEditingController();
  final _items = <String, List<Map<String, dynamic>>>{'apps': [], 'ads': []};
  final _activeItems = <String, List<Map<String, dynamic>>>{
    'apps': [],
    'ads': [],
  };
  final _meta = <String, Map<String, dynamic>>{};
  Map<String, String> _socialAccounts = {};
  bool _loadingApps = true;
  bool _loadingAds = true;
  String? _appsError;
  String? _adsError;
  final Set<String> _starting = {};
  String _selectedAppStore = 'all';

  String? _pendingQuestionnaireGroup() {
    final contextData = widget.user['subscription_context'];
    final active = contextData is Map
        ? contextData['active_marketplaces']
        : null;
    final questionnaire = widget.user['subscription_questionnaire'];
    final completed =
        questionnaire is Map && questionnaire['completed_groups'] is List
        ? (questionnaire['completed_groups'] as List)
              .map((item) => item.toString())
              .toSet()
        : <String>{};
    if (active is! List) return null;
    for (final item in active.whereType<Map>()) {
      final group = item['group_key']?.toString();
      if ((group == 'group_b' || group == 'group_c') &&
          !completed.contains(group))
        return group;
    }
    return null;
  }

  Future<void> _openPackages() async {
    await Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (_) => SubscriptionsScreen(
          api: widget.api,
          token: widget.token,
          user: widget.user,
        ),
      ),
    );
    if (mounted) await _load();
  }

  Future<void> _answerPackageQuestions(String groupKey) async {
    // The questionnaire floats over this page with the list blurred behind it,
    // so the user never loses their place.
    final answered = await showQuestionnaireDialog(
      context,
      api: widget.api,
      token: widget.token,
      groupKey: groupKey,
      user: widget.user,
    );
    if (answered == true && mounted) {
      try {
        final refreshed = await widget.api.refreshUser(widget.token);
        widget.user.addAll(refreshed);
      } on AuthException {
        // The current session snapshot is still usable; a later refresh will reconcile it.
      }
      if (mounted) await _load(type: 'apps');
    }
  }

  Widget _taskEmptyPromotion({
    required String title,
    required String message,
    required IconData icon,
    required String actionLabel,
    required VoidCallback onPressed,
  }) {
    final colors = Theme.of(context).colorScheme;
    return Container(
      width: double.infinity,
      margin: const EdgeInsets.only(top: 4),
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          colors: [
            colors.primaryContainer.withValues(alpha: .72),
            colors.secondaryContainer.withValues(alpha: .48),
          ],
        ),
        borderRadius: BorderRadius.circular(22),
        border: Border.all(color: colors.primary.withValues(alpha: .18)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                padding: const EdgeInsets.all(10),
                decoration: BoxDecoration(
                  color: colors.primary.withValues(alpha: .10),
                  borderRadius: BorderRadius.circular(14),
                ),
                child: Icon(icon, color: colors.primary),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Text(
                  title,
                  style: Theme.of(context).textTheme.titleMedium
                      ?.copyWith(fontWeight: FontWeight.w900),
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          Text(
            message,
            style: Theme.of(context).textTheme.bodyMedium
                ?.copyWith(color: colors.onSurfaceVariant, height: 1.4),
          ),
          const SizedBox(height: 14),
          SizedBox(
            width: double.infinity,
            child: FilledButton.icon(
              onPressed: onPressed,
              icon: const Icon(Icons.arrow_forward_rounded),
              label: Text(actionLabel),
            ),
          ),
        ],
      ),
    );
  }

  @override
  void initState() {
    super.initState();
    _tabs = TabController(length: 2, vsync: this);
    _load();
  }

  @override
  void dispose() {
    _tabs.dispose();
    _search.dispose();
    super.dispose();
  }

  Future<void> _load({String? type}) async {
    final selected = type == null ? const ['apps', 'ads'] : [type];
    setState(() {
      if (selected.contains('apps')) {
        _loadingApps = true;
        _appsError = null;
      }
      if (selected.contains('ads')) {
        _loadingAds = true;
        _adsError = null;
      }
    });
    try {
      await widget.api.loadExchangeRates(widget.token);
      final results = await Future.wait([
        ...selected.map((taskType) async {
          final query = _search.text.trim();
          return widget.api
              .dailyTasks(widget.token, taskType, search: query)
              .then((response) => (taskType, response));
        }),
        ...selected.map((taskType) async {
          final path = taskType == 'apps'
              ? 'me/review-tasks'
              : 'me/ad-share-tasks';
          return widget.api
              .get('$path?per_page=50', widget.token)
              .then((response) => ('active_$taskType', response));
        }),
        if (type == null || _socialAccounts.isEmpty)
          widget.api
              .socialAccounts(widget.token)
              .then(
                (accounts) => ('social', <String, dynamic>{'data': accounts}),
              ),
      ]);
      if (!mounted) return;
      for (final result in results) {
        final taskType = result.$1;
        final response = result.$2;
        if (taskType == 'social') {
          final value = response['data'];
          _socialAccounts = value is Map
              ? value.map(
                  (key, value) => MapEntry(key.toString(), value.toString()),
                )
              : {};
          continue;
        }
        if (taskType.startsWith('active_')) {
          final activeType = taskType.substring('active_'.length);
          final page = response['data'];
          final activeRows = page is Map ? page['data'] : page;
          _activeItems[activeType] = activeRows is List
              ? activeRows
                    .whereType<Map>()
                    .map((item) => Map<String, dynamic>.from(item))
                    .where((item) => item['status']?.toString() == 'reserved')
                    .toList()
              : [];
          continue;
        }
        final rows = response['data'];
        final meta = response['meta'];
        _items[taskType] = rows is List
            ? rows
                  .whereType<Map>()
                  .map((item) => Map<String, dynamic>.from(item))
                  .toList()
            : [];
        _meta[taskType] = meta is Map ? Map<String, dynamic>.from(meta) : {};
      }
      setState(() {
        if (selected.contains('apps')) _loadingApps = false;
        if (selected.contains('ads')) _loadingAds = false;
      });
    } on AuthException catch (error) {
      if (!mounted) return;
      setState(() {
        if (selected.contains('apps')) {
          _appsError = error.message;
          _loadingApps = false;
        }
        if (selected.contains('ads')) {
          _adsError = error.message;
          _loadingAds = false;
        }
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        if (selected.contains('apps')) {
          _appsError = 'Could not load today’s app tasks.';
          _loadingApps = false;
        }
        if (selected.contains('ads')) {
          _adsError = 'Could not load today’s ad tasks.';
          _loadingAds = false;
        }
      });
    }
  }

  Future<void> _start(String type, Map<String, dynamic> item) async {
    if (type == 'ads') {
      final platform = _platform(item['type']?.toString());
      if (platform != 'others') {
        if ((_socialAccounts[platform] ?? '').trim().isEmpty) {
          await _promptMissingHandle(platform);
          return;
        }
        final proceed = await _showRiskWarning(platform);
        if (proceed != true) return;
      }
    }

    final id = item['id'];
    if (id == null) return;
    final key = 'daily-task-${DateTime.now().microsecondsSinceEpoch}';
    final busyKey = '$type:$id';
    setState(() => _starting.add(busyKey));
    try {
      final response = type == 'apps'
          ? await widget.api.post('review-campaigns/$id/claim', widget.token, {
              'idempotency_key': key,
            })
          : await widget.api.post('ads/$id/claim', widget.token, {
              'idempotency_key': key,
            });
      final task = response['data'];
      if (!mounted || task is! Map<String, dynamic>) return;
      final adType = type == 'ads'
          ? _platform(item['type']?.toString())
          : 'others';
      await Navigator.of(context).push(
        MaterialPageRoute<void>(
          builder: (_) => type == 'apps'
              ? ReviewTaskScreen(
                  api: widget.api,
                  token: widget.token,
                  task: task,
                  user: widget.user,
                )
              : adType == 'others'
              ? AdEvidenceScreen(
                  api: widget.api,
                  token: widget.token,
                  task: task,
                  user: widget.user,
                )
              : SocialAdTaskScreen(
                  api: widget.api,
                  token: widget.token,
                  task: task,
                  user: widget.user,
                ),
        ),
      );
      if (mounted) await _load(type: type);
    } on AuthException catch (error) {
      if (mounted) showAuthMessage(context, error.message);
    } finally {
      if (mounted) setState(() => _starting.remove(busyKey));
    }
  }

  Future<void> _resumeActiveTask(
    String type,
    Map<String, dynamic> task,
  ) async {
    final id = task['id'];
    if (id == null) return;
    final busyKey = 'active:$type:$id';
    setState(() => _starting.add(busyKey));
    try {
      final ad = task['ad'] is Map
          ? Map<String, dynamic>.from(task['ad'] as Map)
          : <String, dynamic>{};
      final platform = type == 'ads' ? _platform(ad['type']?.toString()) : '';
      await Navigator.of(context).push(
        MaterialPageRoute<void>(
          builder: (_) => type == 'apps'
              ? ReviewTaskScreen(
                  api: widget.api,
                  token: widget.token,
                  task: task,
                  user: widget.user,
                )
              : platform == 'others'
              ? AdEvidenceScreen(
                  api: widget.api,
                  token: widget.token,
                  task: task,
                  user: widget.user,
                )
              : SocialAdTaskScreen(
                  api: widget.api,
                  token: widget.token,
                  task: task,
                  user: widget.user,
                ),
        ),
      );
      if (mounted) await _load(type: type);
    } on AuthException catch (error) {
      if (mounted) showAuthMessage(context, error.message);
    } finally {
      if (mounted) setState(() => _starting.remove(busyKey));
    }
  }

  Future<void> _reviewDetailsThenStart(Map<String, dynamic> item) async {
    final app = item['app'] is Map
        ? Map<String, dynamic>.from(item['app'] as Map)
        : <String, dynamic>{};
    final screenshots = _appScreenshots(app);
    final start = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      showDragHandle: true,
      builder: (sheetContext) => AppReviewDetailsSheet(
        app: app,
        screenshots: screenshots,
        rewardText: _reward('apps', item),
        onViewAll: screenshots.length > 1
            ? () {
                Navigator.of(sheetContext).push(
                  MaterialPageRoute<void>(
                    builder: (_) => AppScreenshotGalleryPage(
                      name: app['name']?.toString() ?? 'App screenshots',
                      screenshots: screenshots,
                    ),
                  ),
                );
              }
            : null,
        onOpenStore: () async {
          final url = Uri.tryParse(app['store_link']?.toString() ?? '');
          if (url == null || !url.hasScheme) return;
          await launchUrl(url, mode: LaunchMode.externalApplication);
        },
        onReport: () async {
          final sent = await AppReportDialog.show(
            context,
            api: widget.api,
            token: widget.token,
            campaignId: item['id'] as Object,
          );
          if (sent && mounted) {
            showAuthMessage(
              context,
              'Your app report was sent to the review team.',
            );
          }
        },
        onStartReview: () => Navigator.of(sheetContext).pop(true),
      ),
    );
    if (start == true && mounted) await _start('apps', item);
  }

  List<String> _appScreenshots(Map<String, dynamic> app) {
    final values = app['screenshots'];
    if (values is! List) return const [];
    return values
        .map((value) {
          if (value is Map) {
            return MediaUrl.resolve(
              value['image_url']?.toString() ??
                  value['storage_path']?.toString(),
            );
          }
          return MediaUrl.resolve(value?.toString());
        })
        .whereType<String>()
        .toList(growable: false);
  }

  Future<bool?> _showRiskWarning(String platform) => showModalBottomSheet<bool>(
    context: context,
    showDragHandle: true,
    isScrollControlled: true,
    builder: (context) => SafeArea(
      child: Padding(
        padding: const EdgeInsets.fromLTRB(22, 8, 22, 24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            CircleAvatar(
              radius: 25,
              backgroundColor: Theme.of(context).colorScheme.tertiaryContainer,
              child: Icon(
                Icons.warning_amber_rounded,
                color: Theme.of(context).colorScheme.onTertiaryContainer,
              ),
            ),
            const SizedBox(height: 16),
            Text(
              'Before you start this task',
              style: Theme.of(context).textTheme.titleLarge
                  ?.copyWith(fontWeight: FontWeight.w800),
            ),
            const SizedBox(height: 10),
            Text(
              'Continue only if you have an active ${_platformName(platform)} account and the task link works for you.',
            ),
            const SizedBox(height: 10),
            const Text(
              'Submitting proof for a broken link or a task you did not complete may lead to account restrictions and withheld rewards.',
            ),
            const SizedBox(height: 20),
            SizedBox(
              width: double.infinity,
              child: FilledButton(
                onPressed: () => Navigator.pop(context, true),
                child: const Text('I understand, continue'),
              ),
            ),
            SizedBox(
              width: double.infinity,
              child: TextButton(
                onPressed: () => Navigator.pop(context, false),
                child: const Text('Cancel'),
              ),
            ),
          ],
        ),
      ),
    ),
  );

  Future<void> _promptMissingHandle(String platform) async {
    await Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (_) => SocialAccountsSetupScreen(
          api: widget.api,
          token: widget.token,
          focusPlatform: platform,
        ),
      ),
    );
    if (mounted) {
      try {
        _socialAccounts = await widget.api.socialAccounts(widget.token);
      } catch (_) {}
    }
  }

  String _platform(dynamic raw) {
    final value = (raw?.toString() ?? 'others').toLowerCase().trim();
    if (value.contains('twitter') || value == 'x') return 'x';
    for (final known in const [
      'telegram',
      'facebook',
      'youtube',
      'instagram',
      'tiktok',
      'whatsapp',
    ]) {
      if (value.contains(known)) return known;
    }
    return 'others';
  }

  String _platformName(String key) =>
      key == 'x' ? 'X (Twitter)' : '${key[0].toUpperCase()}${key.substring(1)}';

  String _reward(String type, Map<String, dynamic> item) {
    if (type == 'apps') {
      return appReviewRewardText(
        api: widget.api,
        user: widget.user,
        listing: item,
        preferredCurrency: widget.api.preferredCurrency,
      );
    }
    final currency = item['currency']?.toString() ?? 'USD';
    return moneyConverted(
      widget.api,
      item['share_reward'],
      currency,
      item['currency_minor_units'],
      widget.api.preferredCurrency,
      isMinor: false,
    );
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final colors = theme.colorScheme;

    return Scaffold(
      backgroundColor: colors.surface,
      appBar: AppBar(
        backgroundColor: colors.surface,
        surfaceTintColor: Colors.transparent,
        elevation: 0,
        scrolledUnderElevation: 0,
        titleSpacing: 20,
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Daily tasks',
              style: theme.textTheme.titleLarge?.copyWith(
                fontWeight: FontWeight.w900,
                letterSpacing: -0.4,
              ),
            ),
            Text(
              'Earn rewards by completing simple tasks',
              style: theme.textTheme.bodySmall?.copyWith(
                color: colors.onSurfaceVariant,
              ),
            ),
          ],
        ),
        actions: [
          IconButton(
            tooltip: 'Task activity',
            onPressed: () => Navigator.of(context).push(
              MaterialPageRoute<void>(
                builder: (_) => MyTasksScreen(
                  api: widget.api,
                  token: widget.token,
                  user: widget.user,
                ),
              ),
            ),
            icon: const Icon(Icons.history_rounded),
          ),
          const SizedBox(width: 6),
        ],
        bottom: PreferredSize(
          preferredSize: const Size.fromHeight(64),
          child: Padding(
            padding: const EdgeInsets.fromLTRB(16, 4, 16, 10),
            child: Container(
              height: 48,
              decoration: BoxDecoration(
                color: colors.surfaceContainerHighest,
                borderRadius: BorderRadius.circular(15),
              ),
              child: TabBar(
                controller: _tabs,
                dividerColor: Colors.transparent,
                indicatorSize: TabBarIndicatorSize.tab,
                indicator: BoxDecoration(
                  color: colors.primary,
                  borderRadius: BorderRadius.circular(12),
                ),
                labelColor: colors.onPrimary,
                unselectedLabelColor: colors.onSurfaceVariant,
                labelStyle: theme.textTheme.labelLarge?.copyWith(
                  fontWeight: FontWeight.w800,
                ),
                tabs: const [
                  Tab(
                    icon: Icon(Icons.apps_rounded, size: 18),
                    text: 'App reviews',
                  ),
                  Tab(
                    icon: Icon(Icons.campaign_outlined, size: 18),
                    text: 'Ad shares',
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 8),
            child: TextField(
              controller: _search,
              textInputAction: TextInputAction.search,
              onSubmitted: (_) =>
                  _load(type: _tabs.index == 0 ? 'apps' : 'ads'),
              decoration: InputDecoration(
                hintText: 'Search ${_tabs.index == 0 ? 'apps' : 'ads'}...',
                prefixIcon: const Icon(Icons.search_rounded),
                suffixIcon: ValueListenableBuilder<TextEditingValue>(
                  valueListenable: _search,
                  builder: (context, value, _) => value.text.isEmpty
                      ? IconButton(
                          tooltip: 'Search',
                          onPressed: () =>
                              _load(type: _tabs.index == 0 ? 'apps' : 'ads'),
                          icon: const Icon(Icons.arrow_forward_rounded),
                        )
                      : IconButton(
                          tooltip: 'Clear search',
                          onPressed: () {
                            _search.clear();
                            _load(type: _tabs.index == 0 ? 'apps' : 'ads');
                          },
                          icon: const Icon(Icons.close_rounded),
                        ),
                ),
                filled: true,
                fillColor: colors.surfaceContainerLow,
                contentPadding: const EdgeInsets.symmetric(vertical: 14),
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(15),
                  borderSide: BorderSide(color: colors.outlineVariant),
                ),
                enabledBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(15),
                  borderSide: BorderSide(color: colors.outlineVariant),
                ),
                focusedBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(15),
                  borderSide: BorderSide(color: colors.primary, width: 1.5),
                ),
              ),
            ),
          ),
          Expanded(
            child: TabBarView(
              controller: _tabs,
              children: [_taskList('apps'), _taskList('ads')],
            ),
          ),
        ],
      ),
    );
  }

  Widget _taskList(String type) {
    final loading = type == 'apps' ? _loadingApps : _loadingAds;
    final error = type == 'apps' ? _appsError : _adsError;
    final rows = _items[type] ?? const [];
    final activeTasks = _activeItems[type] ?? const <Map<String, dynamic>>[];
    final visibleRows = type == 'apps'
        ? rows
              .where(
                (item) => StoreFilterChips.matches(item, _selectedAppStore),
              )
              .toList()
        : rows;
    final meta = _meta[type] ?? const {};
    final dailyRevenueLimit = meta['daily_revenue_limit_minor'];
    final dailyRevenueUsed =
        meta['daily_revenue_used_minor'] ?? meta['daily_revenue_earned_minor'];
    final dailyRevenuePending = meta['daily_revenue_pending_minor'];
    final dailyRevenueCurrency =
        meta['daily_revenue_currency']?.toString() ?? 'USD';
    final theme = Theme.of(context);
    final colors = theme.colorScheme;

    if (loading && rows.isEmpty && activeTasks.isEmpty) {
      return Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const CircularProgressIndicator(),
            const SizedBox(height: 14),
            Text(
              'Finding available ${type == 'apps' ? 'app reviews' : 'ad tasks'}...',
              style: theme.textTheme.bodyMedium?.copyWith(
                color: colors.onSurfaceVariant,
              ),
            ),
          ],
        ),
      );
    }

    if (error != null && rows.isEmpty && activeTasks.isEmpty) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(28),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(Icons.cloud_off_outlined, size: 42, color: colors.error),
              const SizedBox(height: 12),
              Text(
                error,
                textAlign: TextAlign.center,
                style: theme.textTheme.bodyMedium,
              ),
              const SizedBox(height: 16),
              FilledButton.tonalIcon(
                onPressed: () => _load(type: type),
                icon: const Icon(Icons.refresh_rounded),
                label: const Text('Try again'),
              ),
            ],
          ),
        ),
      );
    }

    return RefreshIndicator(
      onRefresh: () => _load(type: type),
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(16, 10, 16, 100),
        children: [
          if (type == 'apps') ...[
            StoreFilterChips(
              selected: _selectedAppStore,
              onSelected: (value) => setState(() => _selectedAppStore = value),
            ),
            const SizedBox(height: 14),
            _DailyOverviewCard(type: type, meta: meta, colors: colors),
          ],
          if (type == 'ads' && dailyRevenueLimit is num) ...[
            const SizedBox(height: 12),
            _RevenueLimitCard(
              used: dailyRevenueUsed,
              pending: dailyRevenuePending,
              limit: dailyRevenueLimit,
              currency: dailyRevenueCurrency,
              api: widget.api,
              colors: colors,
            ),
          ],
          const SizedBox(height: 22),
          Row(
            children: [
              Expanded(
                child: Text(
                  type == 'apps' ? 'App review tasks' : 'Ad tasks',
                  style: theme.textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
              if (loading)
                SizedBox(
                  width: 16,
                  height: 16,
                  child: CircularProgressIndicator(
                    strokeWidth: 2,
                    color: colors.primary,
                  ),
                )
              else
                Text(
                  '${visibleRows.length + activeTasks.length} found',
                  style: theme.textTheme.labelMedium?.copyWith(
                    color: colors.onSurfaceVariant,
                    fontWeight: FontWeight.w700,
                  ),
                ),
            ],
          ),
          const SizedBox(height: 12),
          if (activeTasks.isEmpty &&
              type == 'apps' &&
              visibleRows.isEmpty &&
              meta['reason'] == 'subscription_questionnaire_required' &&
              _pendingQuestionnaireGroup() != null)
            _taskEmptyPromotion(
              title: 'Finish your app reviewer setup',
              message:
                  'Your ${_pendingQuestionnaireGroup() == 'group_b' ? 'Play Store / Galaxy' : 'Apple Store'} package is active. Answer a few quick questions to unlock its app review tasks.',
              icon: Icons.fact_check_outlined,
              actionLabel: 'Answer questions',
              onPressed: () =>
                  _answerPackageQuestions(_pendingQuestionnaireGroup()!),
            )
          else if (activeTasks.isEmpty &&
              type == 'apps' &&
              visibleRows.isEmpty &&
              _pendingQuestionnaireGroup() != null)
            _taskEmptyPromotion(
              title: 'Complete your reviewer setup',
              message: 'Answer a few quick questions for your active store package to unlock app review tasks.',
              icon: Icons.fact_check_outlined,
              actionLabel: 'Answer questions',
              onPressed: () =>
                  _answerPackageQuestions(_pendingQuestionnaireGroup()!),
            )
          else if (activeTasks.isEmpty &&
              meta['reason'] == 'marketplace_plan_required')
            _taskEmptyPromotion(
              title: 'Choose a package to get started',
              message: 'Join a package to unlock eligible tasks. You can add another package later to access more earning options.',
              icon: Icons.lock_open_rounded,
              actionLabel: 'Explore packages',
              onPressed: _openPackages,
            )
          else if (activeTasks.isEmpty && meta['reason'] == 'daily_limit_reached')
            _taskEmptyPromotion(
              title: 'Today’s task limit is reached',
              message: 'Your current package has no more tasks available today. Explore other packages for additional task access.',
              icon: Icons.event_busy_rounded,
              actionLabel: 'Explore more packages',
              onPressed: _openPackages,
            )
          else if (activeTasks.isEmpty &&
              meta['reason'] == 'daily_revenue_limit_reached')
            _taskEmptyPromotion(
              title: 'Daily ad earnings goal reached',
              message: 'You have reached today’s earning limit for this package. Review other packages to find another way to keep earning.',
              icon: Icons.trending_up_rounded,
              actionLabel: 'Explore more packages',
              onPressed: _openPackages,
            )
          else if (activeTasks.isEmpty &&
              meta['reason'] == 'daily_revenue_unavailable')
            _taskEmptyPromotion(
              title: 'Earning limit is temporarily unavailable',
              message: 'Refresh to check again, or explore packages with other task options.',
              icon: Icons.currency_exchange_rounded,
              actionLabel: 'Explore packages',
              onPressed: () {
                _load(type: type);
                _openPackages();
              },
            )
          else if (activeTasks.isEmpty &&
              meta['reason'] == 'package_task_type_not_included')
            _taskEmptyPromotion(
              title:
                  'Add a package for ${type == 'apps' ? 'app reviews' : 'ad shares'}',
              message:
                  'Your active package does not include ${type == 'apps' ? 'app reviews' : 'ad shares'}. Join an eligible package to unlock this task type.',
              icon: Icons.add_circle_outline_rounded,
              actionLabel: 'See eligible packages',
              onPressed: _openPackages,
            )
          else if (activeTasks.isEmpty &&
              meta['reason'] == 'package_limit_reached')
            _taskEmptyPromotion(
              title: 'Package task allowance used',
              message:
                  'You have used the ${type == 'apps' ? 'app review' : 'ad share'} allowance for this package. Explore other packages for more task access.',
              icon: Icons.add_task_rounded,
              actionLabel: 'Explore more packages',
              onPressed: _openPackages,
            )
          else if (visibleRows.isEmpty && activeTasks.isEmpty)
            _taskEmptyPromotion(
              title:
                  type == 'apps' &&
                      _selectedAppStore != 'all' &&
                      rows.isNotEmpty
                  ? 'No tasks for this store yet'
                  : 'No tasks available right now',
              message:
                  type == 'apps' &&
                      _selectedAppStore != 'all' &&
                      rows.isNotEmpty
                  ? 'You do not have any app reviews for this store. Explore packages for other supported stores or ad sharing.'
                  : 'There are no eligible ${type == 'apps' ? 'app reviews' : 'ad shares'} right now. Explore packages to see additional ways to earn.',
              icon: Icons.inbox_outlined,
              actionLabel: 'Explore packages',
              onPressed: _openPackages,
            ),
          ...activeTasks.map((task) => _activeTaskCard(type, task)),
          if (type == 'apps')
            ...visibleRows.map((item) => _taskAppCard(type, item))
          else if (type == 'ads')
            ...visibleRows.map((item) => _taskAdsCard(type, item)),
          if (error != null && visibleRows.isNotEmpty) ...[
            const SizedBox(height: 8),
            TextButton.icon(
              onPressed: () => _load(type: type),
              icon: const Icon(Icons.refresh_rounded),
              label: Text('Refresh failed: $error'),
            ),
          ],
        ],
      ),
    );
  }

  Widget _activeTaskCard(String type, Map<String, dynamic> task) {
    // Render active reservations with the same card design as available tasks.
    // Keep the original reservation payload for the Continue action.
    if (type == 'apps') {
      final campaign = task['campaign'] is Map
          ? Map<String, dynamic>.from(task['campaign'] as Map)
          : <String, dynamic>{};
      final app = campaign['app'] is Map
          ? Map<String, dynamic>.from(campaign['app'] as Map)
          : <String, dynamic>{};
      final item = <String, dynamic>{
        ...campaign,
        'id': task['id'],
        'app': app,
        'slots_remaining':
            task['slots_remaining'] ?? campaign['slots_remaining'],
        'reserved_reward_minor': task['reserved_reward_minor'],
      };
      return _taskAppCard(type, item, activeTask: task);
    }

    final ad = task['ad'] is Map
        ? Map<String, dynamic>.from(task['ad'] as Map)
        : <String, dynamic>{};
    final item = <String, dynamic>{
      ...ad,
      'id': task['id'],
      'type': ad['type'] ?? task['type'],
      'slots_remaining': task['slots_remaining'] ?? ad['slots_remaining'],
    };
    return _taskAdsCard(type, item, activeTask: task);
  }

  Widget _taskAppCard(
    String type,
    Map<String, dynamic> item, {
    Map<String, dynamic>? activeTask,
  }) {
    final app = Map<String, dynamic>.from(item['app']);
    final isApp = true;
    final platform = (app['store']?.toString() ?? 'App store');
    final title = (app['name']?.toString() ?? 'App review');
    final subtitle = (app['developer']?.toString() ?? '');
    final slots = item['slots_remaining'];
    final location = item['location']?.toString();
    final id = activeTask == null
        ? '$type:${item['id']}'
        : 'active:$type:${activeTask['id']}';
    final busy = _starting.contains(id);
    final theme = Theme.of(context);
    final colors = theme.colorScheme;
    final imageUrl = MediaUrl.resolve(app['app_icon']?.toString());
    final category = app['category']?.toString() ?? '';

    return Card(
      elevation: 0,
      margin: const EdgeInsets.only(bottom: 14),
      color: colors.surfaceContainerLow,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(21),
        side: BorderSide(color: colors.outlineVariant),
      ),
      clipBehavior: Clip.antiAlias,
      child: Padding(
        padding: const EdgeInsets.all(15),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                _TaskImage(url: imageUrl, isApp: isApp, colors: colors),
                const SizedBox(width: 13),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Flexible(
                            child: Text(
                              platform.toUpperCase(),
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: theme.textTheme.labelSmall?.copyWith(
                                color: colors.primary,
                                fontWeight: FontWeight.w900,
                                letterSpacing: .7,
                              ),
                            ),
                          ),
                          if (item['featured'] == true) ...[
                            const SizedBox(width: 7),
                            Icon(
                              Icons.star_rounded,
                              size: 15,
                              color: colors.tertiary,
                            ),
                            Text(
                              'Featured',
                              style: theme.textTheme.labelSmall?.copyWith(
                                color: colors.tertiary,
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                          ],
                        ],
                      ),
                      const SizedBox(height: 5),
                      Text(
                        title,
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: theme.textTheme.titleMedium?.copyWith(
                          fontWeight: FontWeight.w900,
                          height: 1.2,
                        ),
                      ),
                      if (subtitle.trim().isNotEmpty) ...[
                        const SizedBox(height: 4),
                        Text(
                          subtitle,
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          style: theme.textTheme.bodySmall?.copyWith(
                            color: colors.onSurfaceVariant,
                            height: 1.35,
                          ),
                        ),
                      ],
                    ],
                  ),
                ),
              ],
            ),
            const SizedBox(height: 13),
            Wrap(
              spacing: 7,
              runSpacing: 7,
              children: [
                if (category.trim().isNotEmpty)
                  _InfoPill(
                    icon: Icons.category_outlined,
                    label: category,
                    colors: colors,
                  ),
                if (location != null && location.isNotEmpty)
                  _InfoPill(
                    icon: Icons.location_on_outlined,
                    label: location,
                    colors: colors,
                  ),
              ],
            ),
            const SizedBox(height: 15),
            Container(
              padding: const EdgeInsets.fromLTRB(13, 11, 13, 11),
              decoration: BoxDecoration(
                color: colors.primaryContainer.withValues(alpha: .45),
                borderRadius: BorderRadius.circular(14),
              ),
              child: Row(
                children: [
                  Icon(
                    Icons.account_balance_wallet_outlined,
                    color: colors.primary,
                    size: 19,
                  ),
                  const SizedBox(width: 8),
                  Text(
                    'Your reward',
                    style: theme.textTheme.labelMedium?.copyWith(
                      color: colors.onSurfaceVariant,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                  const Spacer(),
                  Text(
                    _reward(type, item),
                    style: theme.textTheme.titleLarge?.copyWith(
                      color: colors.primary,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 13),
            SizedBox(
              width: double.infinity,
              child: FilledButton.icon(
                onPressed: busy
                    ? null
                    : activeTask == null
                    ? () => _reviewDetailsThenStart(item)
                    : () => _resumeActiveTask(type, activeTask),
                icon: busy
                    ? const SizedBox(
                        height: 17,
                        width: 17,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : Icon(Icons.rate_review_outlined, size: 19),
                label: Text(
                  busy
                      ? (activeTask == null ? 'Preparing task...' : 'Opening task...')
                      : (activeTask == null ? 'View app & review' : 'Continue review'),
                ),
                style: FilledButton.styleFrom(
                  minimumSize: const Size.fromHeight(49),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(14),
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _taskAdsCard(
    String type,
    Map<String, dynamic> item, {
    Map<String, dynamic>? activeTask,
  }) {
    final platform = _platformName(_platform(item['type']));
    final title = (item['title']?.toString() ?? 'Sponsored task');
    final subtitle = (item['description']?.toString() ?? '');
    final slots = item['slots_remaining'];
    final id = activeTask == null
        ? '$type:${item['id']}'
        : 'active:$type:${activeTask['id']}';
    final busy = _starting.contains(id);
    final theme = Theme.of(context);

    final image = MediaUrl.resolve(item['creative_path']?.toString() );
    final brandColor = platformColor(platform);

    return Card(
      margin: const EdgeInsets.only(bottom: 11),
      clipBehavior: Clip.antiAlias,
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                _buildPlatformIcon(_platform(item['type'])),
                const SizedBox(width: 11),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        '$platform · Task',
                        style: theme.textTheme.bodySmall?.copyWith(
                          color: theme.colorScheme.onSurfaceVariant,
                        ),
                      ),
                      const SizedBox(height: 3),
                      Text(
                        title,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: theme.textTheme.titleMedium?.copyWith(
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                      if (subtitle.isNotEmpty)
                        Text(
                          subtitle,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: theme.textTheme.bodySmall?.copyWith(
                            color: theme.colorScheme.onSurfaceVariant,
                          ),
                        ),
                    ],
                  ),
                ),
              ],
            ),
            const SizedBox(height: 13),
            Row(
              children: [
                _pill(
                  Icons.people_outline_rounded,
                  slots == null ? 'Open slots' : '$slots slots left',
                ),
                const Spacer(),
                Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Reward',
                      style: TextStyle(
                        color: theme.colorScheme.onSurfaceVariant,
                        fontWeight: FontWeight.w500,
                        fontSize: 12,
                        height: 1.0,
                      ),
                    ),
                    Text(
                      _reward(type, item),
                      style: TextStyle(
                        color: theme.colorScheme.primary,
                        fontWeight: FontWeight.w900,
                        fontSize: 16,
                        height: 1.0,
                      ),
                    ),
                  ],
                ),
                const SizedBox(width: 12),
                OutlinedButton(
                  style: OutlinedButton.styleFrom(
                    backgroundColor: platformColor(_platform(item['type']))
                        .withOpacity(0.55),
                    foregroundColor: Colors.white,
                    side: BorderSide.none,
                    padding: const EdgeInsets.symmetric(
                      vertical: 4,
                      horizontal: 15,
                    ),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(15),
                    ),
                  ),
                  onPressed: busy
                      ? null
                      : activeTask == null
                      ? () => _start(type, item)
                      : () => _resumeActiveTask(type, activeTask),
                  child: busy
                      ? const SizedBox(
                          height: 18,
                          width: 18,
                          child: CircularProgressIndicator(
                            strokeWidth: 2,
                            color: Colors.white,
                          ),
                        )
                      : Text(activeTask == null ? 'Start task' : 'Continue'),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildPlatformIcon(String platform) {

    return Container(
      width: 58,
      height: 58,
      clipBehavior: Clip.antiAlias,
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(14),
      ),
      child: Image.asset(
          'assets/platform/$platform.png',
          fit: BoxFit.cover,
          errorBuilder: (_, _, _) => Icon(
            Icons.campaign_outlined,
            color: brandGreen,
            size: 27,
          ),
        )
      );
  }

  Widget _pill(IconData icon, String label) => _InfoPill(
    icon: icon,
    label: label,
    colors: Theme.of(context).colorScheme,
  );

  Widget _empty(String text, IconData icon) {
    final theme = Theme.of(context);
    final colors = theme.colorScheme;
    return Container(
      width: double.infinity,
      margin: const EdgeInsets.only(top: 12),
      padding: const EdgeInsets.fromLTRB(24, 30, 24, 30),
      decoration: BoxDecoration(
        color: colors.surfaceContainerLow,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: colors.outlineVariant),
      ),
      child: Column(
        children: [
          Container(
            width: 58,
            height: 58,
            alignment: Alignment.center,
            decoration: BoxDecoration(
              color: colors.secondaryContainer,
              shape: BoxShape.circle,
            ),
            child: Icon(icon, size: 27, color: colors.onSecondaryContainer),
          ),
          const SizedBox(height: 15),
          Text(
            text,
            textAlign: TextAlign.center,
            style: theme.textTheme.bodyMedium?.copyWith(
              color: colors.onSurfaceVariant,
              height: 1.5,
            ),
          ),
        ],
      ),
    );
  }
}

class _DailyOverviewCard extends StatelessWidget {
  const _DailyOverviewCard({
    required this.type,
    required this.meta,
    required this.colors,
  });

  final String type;
  final Map<String, dynamic> meta;
  final ColorScheme colors;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final used = meta['used_today'] ?? 0;
    final limit = meta['daily_limit'];
    final remaining = meta['remaining_today'];
    final hasLimit = limit is num && limit > 0;
    final progress = hasLimit
        ? ((used is num ? used : 0) / limit).clamp(0.0, 1.0).toDouble()
        : null;

    return Container(
      padding: const EdgeInsets.all(17),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [colors.primaryContainer, colors.secondaryContainer],
        ),
        borderRadius: BorderRadius.circular(21),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(Icons.bolt_rounded, color: colors.onPrimaryContainer),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  type == 'apps'
                      ? 'Your review activity'
                      : 'Your sharing activity',
                  style: theme.textTheme.titleSmall?.copyWith(
                    color: colors.onPrimaryContainer,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
              if (remaining != null)
                Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 10,
                    vertical: 6,
                  ),
                  decoration: BoxDecoration(
                    color: colors.surface.withValues(alpha: .65),
                    borderRadius: BorderRadius.circular(30),
                  ),
                  child: Text(
                    '$remaining left today',
                    style: theme.textTheme.labelSmall?.copyWith(
                      color: colors.onSurface,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
            ],
          ),
          if (progress != null) ...[
            const SizedBox(height: 11),
            ClipRRect(
              borderRadius: BorderRadius.circular(20),
              child: LinearProgressIndicator(
                value: progress,
                minHeight: 7,
                backgroundColor: colors.surface.withValues(alpha: .55),
                color: colors.primary,
              ),
            ),
          ],
        ],
      ),
    );
  }
}

class _RevenueLimitCard extends StatelessWidget {
  const _RevenueLimitCard({
    required this.used,
    required this.pending,
    required this.limit,
    required this.currency,
    required this.api,
    required this.colors,
  });

  final dynamic used;
  final dynamic pending;
  final num limit;
  final String currency;
  final AuthApi api;
  final ColorScheme colors;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final amount = used is num ? used : 0;
    final progress = limit <= 0
        ? 0.0
        : (amount / limit).clamp(0.0, 1.0).toDouble();

    return Container(
      padding: const EdgeInsets.all(15),
      decoration: BoxDecoration(
        color: colors.surfaceContainerLow,
        borderRadius: BorderRadius.circular(17),
        border: Border.all(color: colors.outlineVariant),
      ),
      child: Column(
        children: [
          Row(
            children: [
              Icon(Icons.savings_outlined, color: colors.primary, size: 19),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  'Daily ad earnings cap',
                  style: theme.textTheme.titleSmall?.copyWith(
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
              Text(
                moneyConverted(
                  api,
                  used ?? 0,
                  currency,
                  null,
                  api.preferredCurrency,
                  isMinor: true,
                ),
                style: theme.textTheme.titleSmall?.copyWith(
                  color: colors.primary,
                  fontWeight: FontWeight.w900,
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          ClipRRect(
            borderRadius: BorderRadius.circular(20),
            child: LinearProgressIndicator(
              value: progress,
              minHeight: 7,
              backgroundColor: colors.surfaceContainerHighest,
              color: colors.primary,
            ),
          ),
          const SizedBox(height: 8),
          Row(
            children: [
              Expanded(
                child: Text(
                  '${(progress * 100).round()}% of daily cap used',
                  style: theme.textTheme.labelSmall?.copyWith(
                    color: colors.onSurfaceVariant,
                  ),
                ),
              ),
              Text(
                'of ${moneyConverted(api, limit, currency, null, api.preferredCurrency, isMinor: true)}',
                style: theme.textTheme.labelSmall?.copyWith(
                  color: colors.onSurfaceVariant,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ],
          ),
          if (pending is num && pending > 0) ...[
            const SizedBox(height: 7),
            Text(
              'Includes ${moneyConverted(api, pending, currency, null, api.preferredCurrency, isMinor: true)} pending proof',
              style: theme.textTheme.labelSmall?.copyWith(
                color: colors.onSurfaceVariant,
              ),
            ),
          ],
        ],
      ),
    );
  }
}

class _InfoPill extends StatelessWidget {
  const _InfoPill({
    required this.icon,
    required this.label,
    required this.colors,
  });

  final IconData icon;
  final String label;
  final ColorScheme colors;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 7),
    decoration: BoxDecoration(
      color: colors.surfaceContainerHighest,
      borderRadius: BorderRadius.circular(30),
    ),
    child: Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Icon(icon, size: 14, color: colors.onSurfaceVariant),
        const SizedBox(width: 6),
        Text(
          label,
          style: Theme.of(context).textTheme.labelSmall?.copyWith(
            color: colors.onSurfaceVariant,
            fontWeight: FontWeight.w700,
          ),
        ),
      ],
    ),
  );
}

class _TaskImage extends StatelessWidget {
  const _TaskImage({
    required this.url,
    required this.isApp,
    required this.colors,
  });

  final String? url;
  final bool isApp;
  final ColorScheme colors;

  @override
  Widget build(BuildContext context) => ClipRRect(
    borderRadius: BorderRadius.circular(16),
    child: SizedBox(
      width: 66,
      height: 66,
      child: url == null
          ? _fallback()
          : Image.network(
              url!,
              fit: BoxFit.cover,
              errorBuilder: (_, _, _) => _fallback(),
            ),
    ),
  );

  Widget _fallback() => ColoredBox(
    color: colors.primaryContainer,
    child: Icon(
      isApp ? Icons.apps_rounded : Icons.campaign_outlined,
      color: colors.onPrimaryContainer,
      size: 29,
    ),
  );
}
