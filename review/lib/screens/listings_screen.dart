import 'dart:async';

import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../features/ads/presentation/screens/ad_evidence_screen.dart';
import '../features/ads/presentation/screens/social_ad_task_screen.dart';
import '../features/marketplace/presentation/widgets/app_listing_card.dart';
import '../features/marketplace/presentation/widgets/app_review_details_sheet.dart';
import '../features/marketplace/presentation/widgets/app_report_dialog.dart';
import '../features/marketplace/presentation/widgets/listing_components.dart';
import '../features/marketplace/presentation/screens/review_task_screen.dart';
import '../features/marketplace/presentation/listing_kind.dart';
import '../services/auth_api.dart';
import '../services/media_url.dart';
import '../widgets/auth_components.dart';
import '../widgets/store_filter_chips.dart';
import 'social_accounts_setup_screen.dart';
import 'subscriptions_screen.dart';

class ListingsScreen extends StatefulWidget {
  const ListingsScreen({
    super.key,
    required this.api,
    required this.token,
    required this.kind,
    this.user = const {},
    this.preferredCurrency = 'USD',
  });
  final AuthApi api;
  final String token;
  final ListingKind kind;
  final Map<String, dynamic> user;
  final String preferredCurrency;

  @override
  State<ListingsScreen> createState() => _ListingsScreenState();
}

class _ListingsScreenState extends State<ListingsScreen> {
  final TextEditingController _searchController = TextEditingController();
  Timer? _searchDebounce;
  bool _loading = true;
  bool _featuredOnly = false;
  String _sort = 'latest';
  String _selectedStore = 'all';
  String? _error;
  List<Map<String, dynamic>> _items = const [];
  int _loadGeneration = 0;
  int _totalCount = 0;

  String get _path {
    final path = widget.kind == ListingKind.appReview
        ? 'listings/apps'
        : 'listings/ads';
    final query = <String, String>{'per_page': '50'};
    final search = _searchController.text.trim();
    if (search.isNotEmpty) query['search'] = search;
    if (_featuredOnly) query['featured'] = '1';
    if (_sort != 'latest') query['sort'] = _sort;
    return '$path?${Uri(queryParameters: query).query}';
  }

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _searchDebounce?.cancel();
    _searchController.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    final generation = ++_loadGeneration;
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      await widget.api.loadExchangeRates(widget.token);
      final response = await widget.api.get(_path, widget.token);
      final list = response['data'];
      final meta = response['meta'];
      if (!mounted || generation != _loadGeneration) return;
      setState(() {
        _items = list is List
            ? list.whereType<Map<String, dynamic>>().toList()
            : const [];
        _totalCount = meta is Map<String, dynamic> && meta['total'] is num
            ? (meta['total'] as num).toInt()
            : _items.length;
        _loading = false;
      });
    } on AuthException catch (error) {
      if (mounted && generation == _loadGeneration) {
        setState(() {
          _error = error.message;
          _loading = false;
        });
      }
    }
  }

  void _onSearchChanged(String _) {
    setState(() {});
    _searchDebounce?.cancel();
    _searchDebounce = Timer(const Duration(milliseconds: 350), _load);
  }

  void _updateFilter(VoidCallback update) {
    setState(update);
    _load();
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    backgroundColor: Theme.of(context).scaffoldBackgroundColor,
    appBar: AppBar(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      elevation: 0,
      scrolledUnderElevation: 0,
      toolbarHeight: 60,
      titleSpacing: 0,
      title: SizedBox(height: 48, child: _buildToolbarSearch()),
      actions: [
        Padding(
          padding: const EdgeInsets.only(right: 12, left: 8),
          child: Container(
            width: 48,
            height: 48,
            clipBehavior: Clip.antiAlias,
            decoration: BoxDecoration(
              color: Colors.grey[200],
              borderRadius: BorderRadius.circular(12),
            ),
            child: Image.asset('assets/logo.jpg', fit: BoxFit.cover),
          ),
        ),
      ],
    ),

    body: _loading && _items.isEmpty
        ? const Center(child: CircularProgressIndicator())
        : _error != null
        ? ListingErrorState(message: _error!, retry: _load)
        : RefreshIndicator(
            onRefresh: _load,
            child: ListView(
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
              children: [
                _buildSearchAndFilters(),
                if (widget.kind == ListingKind.appReview) ...[
                  const SizedBox(height: 12),
                  StoreFilterChips(
                    selected: _selectedStore,
                    onSelected: (value) =>
                        setState(() => _selectedStore = value),
                  ),
                ],
                const SizedBox(height: 12),
                const Text(
                  'Browse available tasks. Your subscription determines which task types you can perform.',
                  style: TextStyle(color: mutedInk, fontSize: 12, height: 1.4),
                ),
                const SizedBox(height: 14),
                Row(
                  children: [
                    const Text(
                      'Available listings',
                      style: TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const Spacer(),
                    Text(
                      '$_displayCount found',
                      style: const TextStyle(
                        color: mutedInk,
                        fontSize: 12,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 10),
                if (_loading) const LinearProgressIndicator(minHeight: 2),
                if (_visibleItems.isEmpty)
                  ListingEmptyState(
                    title: _searchController.text.trim().isEmpty
                        ? 'No tasks available yet'
                        : 'No matching listings',
                    message: _searchController.text.trim().isEmpty
                        ? 'There are no active campaigns to show right now. Available apps and ads are visible whether or not you have a subscription.'
                        : 'Try a different search or clear one of the filters.',
                  ),
                ..._visibleItems.map(
                  (item) => Padding(
                    padding: const EdgeInsets.only(bottom: 12),
                    child: widget.kind == ListingKind.appReview
                        ? AppListingCard(
                            item: item,
                            reward: moneyConverted(
                              widget.api,
                              item['review_reward_minor'],
                              item['currency']?.toString() ?? 'USD',
                              item['currency_minor_units'],
                              widget.preferredCurrency,
                              isMinor: true,
                            ),
                            onTap: () {
                              Navigator.of(context).push(
                                MaterialPageRoute<void>(
                                  builder: (_) => ListingDetailScreen(
                                    api: widget.api,
                                    token: widget.token,
                                    user: widget.user,
                                    kind: widget.kind,
                                    item: item,
                                    preferredCurrency: widget.preferredCurrency,
                                  ),
                                ),
                              );
                            },
                          )
                        : ListingCard(
                            api: widget.api,
                            preferredCurrency: widget.preferredCurrency,
                            kind: widget.kind,
                            item: item,
                            onTap: () {
                              Navigator.of(context).push(
                                MaterialPageRoute<void>(
                                  builder: (_) => ListingDetailScreen(
                                    api: widget.api,
                                    token: widget.token,
                                    user: widget.user,
                                    kind: widget.kind,
                                    item: item,
                                    preferredCurrency: widget.preferredCurrency,
                                  ),
                                ),
                              );
                            },
                          ),
                  ),
                ),
              ],
            ),
          ),
  );

  List<Map<String, dynamic>> get _visibleItems =>
      widget.kind == ListingKind.appReview
      ? _items
            .where((item) => StoreFilterChips.matches(item, _selectedStore))
            .toList()
      : _items;

  int get _displayCount =>
      _selectedStore == 'all' || widget.kind != ListingKind.appReview
      ? _totalCount
      : _visibleItems.length;

  Widget _buildToolbarSearch() => TextField(
    controller: _searchController,
    textInputAction: TextInputAction.search,
    onChanged: _onSearchChanged,
    onSubmitted: (_) {
      _searchDebounce?.cancel();
      _load();
    },
    decoration: InputDecoration(
      hintText: widget.kind == ListingKind.appReview
          ? 'Search apps, stores or developers'
          : 'Search ads by title or description',
      prefixIcon: const Icon(Icons.search_rounded),
      suffixIcon: _searchController.text.isEmpty
          ? null
          : IconButton(
              tooltip: 'Clear search',
              onPressed: () {
                _searchDebounce?.cancel();
                _searchController.clear();
                setState(() {});
                _load();
              },
              icon: const Icon(Icons.close_rounded),
            ),
      filled: true,
      fillColor: Theme.of(context).colorScheme.surfaceContainerHighest
          .withValues(alpha: .55),
      contentPadding: const EdgeInsets.symmetric(vertical: 13),
      border: OutlineInputBorder(
        borderRadius: BorderRadius.circular(16),
        borderSide: BorderSide.none,
      ),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(16),
        borderSide: BorderSide(
          color: Theme.of(context).dividerColor.withValues(alpha: .35),
        ),
      ),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(16),
        borderSide: const BorderSide(color: brandGreen, width: 1.4),
      ),
      isDense: true,
    ),
  );

  bool get _hasActiveFilters =>
      _featuredOnly || _sort != 'latest' || _selectedStore != 'all';

  void _clearFilters() {
    _searchDebounce?.cancel();
    _searchController.clear();
    setState(() {
      _featuredOnly = false;
      _sort = 'latest';
      _selectedStore = 'all';
    });
    _load();
  }

  Widget _buildSearchAndFilters() {
    final theme = Theme.of(context);
    return Container(
      padding: const EdgeInsets.fromLTRB(14, 13, 14, 14),
      decoration: BoxDecoration(
        color: theme.colorScheme.surface,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: theme.dividerColor.withValues(alpha: .45)),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: .025),
            blurRadius: 14,
            offset: const Offset(0, 5),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const Icon(Icons.tune_rounded, size: 18, color: brandGreen),
              const SizedBox(width: 7),
              const Text(
                'Refine results',
                style: TextStyle(fontSize: 14, fontWeight: FontWeight.w800),
              ),
              const Spacer(),
              if (_hasActiveFilters || _searchController.text.trim().isNotEmpty)
                TextButton(
                  onPressed: _clearFilters,
                  style: TextButton.styleFrom(
                    visualDensity: VisualDensity.compact,
                    padding: const EdgeInsets.symmetric(horizontal: 8),
                  ),
                  child: const Text('Clear all'),
                ),
            ],
          ),
          const SizedBox(height: 10),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            crossAxisAlignment: WrapCrossAlignment.center,
            children: [
              FilterChip(
                label: const Text('Featured'),
                avatar: const Icon(Icons.star_rounded, size: 17),
                selected: _featuredOnly,
                showCheckmark: false,
                onSelected: (value) =>
                    _updateFilter(() => _featuredOnly = value),
                selectedColor: brandGreen.withValues(alpha: .13),
                side: BorderSide(
                  color: _featuredOnly
                      ? brandGreen.withValues(alpha: .55)
                      : theme.dividerColor.withValues(alpha: .55),
                ),
                labelStyle: TextStyle(
                  color: _featuredOnly ? brandGreen : null,
                  fontWeight: FontWeight.w700,
                ),
              ),
              ActionChip(
                avatar: const Icon(Icons.sort_rounded, size: 17),
                label: Text(
                  _sort == 'reward_desc' ? 'Highest reward' : 'Newest',
                ),
                onPressed: () => _updateFilter(() {
                  _sort = _sort == 'latest' ? 'reward_desc' : 'latest';
                }),
                side: BorderSide(
                  color: theme.dividerColor.withValues(alpha: .55),
                ),
                backgroundColor: theme.colorScheme.surface,
                labelStyle: const TextStyle(fontWeight: FontWeight.w600),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class ListingDetailScreen extends StatefulWidget {
  const ListingDetailScreen({
    super.key,
    required this.api,
    required this.token,
    required this.kind,
    required this.item,
    this.user = const {},
    this.preferredCurrency = 'USD',
  });
  final AuthApi api;
  final String token;
  final ListingKind kind;
  final Map<String, dynamic> item;
  final Map<String, dynamic> user;
  final String preferredCurrency;

  @override
  State<ListingDetailScreen> createState() => _ListingDetailScreenState();
}

class _ListingDetailScreenState extends State<ListingDetailScreen> {
  bool _claiming = false;
  late Map<String, dynamic> _item = widget.item;
  Map<String, dynamic> get _app => _item['app'] is Map<String, dynamic>
      ? _item['app'] as Map<String, dynamic>
      : const {};
  bool get _isReview => widget.kind == ListingKind.appReview;

  @override
  void initState() {
    super.initState();
    if (_item['task_access_status'] == null) _loadListingGuidance();
  }

  Future<void> _loadListingGuidance() async {
    final id = _item['id'];
    if (id == null) return;
    try {
      final path = _isReview ? 'listings/apps/$id' : 'listings/ads/$id';
      final response = await widget.api.get(path, widget.token);
      final data = response['data'];
      if (mounted && data is Map<String, dynamic>) {
        setState(() => _item = data);
      }
    } on AuthException {
      // The backend still enforces access when the task is claimed.
    }
  }

  Future<void> _showAccessPrompt(String access) async {
    final packageMessage = access == 'store_package_required'
        ? 'This app is available to a package for its store or category. Choose the matching review package to continue.'
        : access == 'review_package_required'
        ? 'Your current package is for ad sharing. Join a review package to review apps.'
        : access == 'share_package_required'
        ? 'Your current package is for app reviews. Join an ad sharing package to share ads.'
        : access == 'subscription_questionnaire_required'
        ? 'Complete the app tester qualification questions for your active package before starting tasks.'
        : 'Choose a subscription package that includes this task.';
    final openPlans = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(
          access == 'subscription_questionnaire_required'
              ? 'Complete your package setup'
              : 'Choose a subscription package',
        ),
        content: Text(packageMessage),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: const Text('Not now'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(dialogContext, true),
            child: Text('View subscription groups'),
          ),
        ],
      ),
    );
    if (openPlans == true && mounted) {
      await Navigator.of(context).push(
        MaterialPageRoute<void>(
          builder: (_) => SubscriptionsScreen(
            api: widget.api,
            token: widget.token,
            user: widget.user,
          ),
        ),
      );
      if (mounted) await _loadListingGuidance();
    }
  }

  String? _socialPlatformForAd() {
    if (_isReview) return null;
    final value = (_item['type']?.toString() ?? '').toLowerCase().trim();
    if (value.contains('twitter') || value == 'x') return 'x';
    for (final platform in const [
      'telegram',
      'facebook',
      'youtube',
      'instagram',
      'tiktok',
      'whatsapp',
    ]) {
      if (value.contains(platform)) return platform;
    }
    return null;
  }

  String _socialPlatformLabel(String platform) => platform == 'x'
      ? 'X (Twitter)'
      : '${platform[0].toUpperCase()}${platform.substring(1)}';

  Future<bool> _ensureSocialHandle(String platform) async {
    Map<String, String> accounts;
    try {
      accounts = await widget.api.socialAccounts(widget.token);
    } on AuthException catch (error) {
      if (mounted) showAuthMessage(context, error.message);
      return false;
    }

    if ((accounts[platform] ?? '').trim().isNotEmpty) return true;
    if (!mounted) return false;

    await Navigator.of(context).push<bool>(
      MaterialPageRoute<bool>(
        builder: (_) => SocialAccountsSetupScreen(
          api: widget.api,
          token: widget.token,
          focusPlatform: platform,
        ),
      ),
    );
    if (!mounted) return false;

    try {
      accounts = await widget.api.socialAccounts(widget.token);
    } on AuthException catch (error) {
      if (mounted) showAuthMessage(context, error.message);
      return false;
    }
    if (!mounted) return false;
    if ((accounts[platform] ?? '').trim().isNotEmpty) return true;

    showAuthMessage(
      context,
      'Add your ${_socialPlatformLabel(platform)} account before starting this task.',
    );
    return false;
  }

  Future<void> _claim() async {
    final access = _item['task_access_status']?.toString();
    if (access != null && access != 'eligible') {
      await _showAccessPrompt(access);
      return;
    }

    final socialPlatform = _socialPlatformForAd();
    if (socialPlatform != null && !await _ensureSocialHandle(socialPlatform)) {
      return;
    }

    await _claimTask();
  }

  Future<void> _claimTask({bool allowHandleSetupRetry = true}) async {
    setState(() => _claiming = true);
    try {
      final id = _item['id'];
      final key = 'mobile-${DateTime.now().microsecondsSinceEpoch}';
      final response = _isReview
          ? await widget.api.post('review-campaigns/$id/claim', widget.token, {
              'idempotency_key': key,
            })
          : await widget.api.post('ads/$id/claim', widget.token, {
              'idempotency_key': key,
            });
      final task = response['data'];
      if (!mounted) return;
      if (task is! Map<String, dynamic>) {
        throw const AuthException('The server returned an invalid task.');
      }
      final socialPlatform = _socialPlatformForAd();
      await Navigator.of(context).push(
        MaterialPageRoute<void>(
          builder: (_) => _isReview
              ? ReviewTaskScreen(
                  api: widget.api,
                  token: widget.token,
                  task: task,
                  user: widget.user,
                )
              : socialPlatform == null
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
    } on AuthException catch (error) {
      if (!mounted) return;
      final message = error.message.toLowerCase();
      final socialPlatform = _socialPlatformForAd();
      if (allowHandleSetupRetry &&
          socialPlatform != null &&
          message.contains('handle')) {
        if (await _ensureSocialHandle(socialPlatform) && mounted) {
          await _claimTask(allowHandleSetupRetry: false);
        }
      } else if (message.contains('marketplace plan') ||
          message.contains('marketplace subscription') ||
          message.contains('review package') ||
          message.contains('ad sharing package') ||
          message.contains('store only covers') ||
          message.contains('category apps')) {
        await _showAccessPrompt(
          _item['task_access_status']?.toString() ??
              'marketplace_plan_required',
        );
      } else if (message.contains('plan') && message.contains('limit')) {
        await _showPlanLimitPrompt();
      } else {
        showAuthMessage(context, error.message);
      }
    } finally {
      if (mounted) setState(() => _claiming = false);
    }
  }

  Future<void> _showPlanLimitPrompt() async {
    final openPlans = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Plan task limit reached'),
        content: const Text(
          'Your current marketplace plan has reached its task allowance. Choose a plan with a higher allowance to continue.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: const Text('Close'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(dialogContext, true),
            child: const Text('Compare marketplace plans'),
          ),
        ],
      ),
    );
    if (openPlans == true && mounted) {
      await Navigator.of(context).push(
        MaterialPageRoute<void>(
          builder: (_) => SubscriptionsScreen(
            api: widget.api,
            token: widget.token,
            user: widget.user,
          ),
        ),
      );
      if (mounted) await _loadListingGuidance();
    }
  }

  List<String> _appScreenshots() {
    final values = _app['screenshots'];
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

  Future<void> _openAppStore() async {
    final url = Uri.tryParse(_app['store_link']?.toString() ?? '');
    if (url == null || !url.hasScheme) return;
    await launchUrl(url, mode: LaunchMode.externalApplication);
  }

  void _openAppScreenshots(List<String> screenshots) {
    Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (_) => AppScreenshotGalleryPage(
          name: _app['name']?.toString() ?? 'App screenshots',
          screenshots: screenshots,
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final description = _isReview
        ? _app['description']?.toString()
        : _item['description']?.toString();
    final reward = moneyConverted(
      widget.api,
      _isReview ? _item['review_reward_minor'] : _item['share_reward'],
      _item['currency']?.toString() ?? 'USD',
      _item['currency_minor_units'],
      widget.preferredCurrency,
      isMinor: _isReview,
    );
    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      appBar: AppBar(
        title: Text(
          _isReview ? 'App details' : 'Ad details',
          style: const TextStyle(fontWeight: FontWeight.w800),
        ),
        backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      ),
      body: _isReview
          ? AppReviewDetailsSheet(
              app: _app,
              screenshots: _appScreenshots(),
              rewardText: reward,
              showHeader: false,
              startLabel: 'Start review',
              isStarting: _claiming,
              onOpenStore:
                  _app['store_link']?.toString().trim().isNotEmpty == true
                  ? _openAppStore
                  : null,
              onViewAll: _appScreenshots().length > 1
                  ? () => _openAppScreenshots(_appScreenshots())
                  : null,
              onReport: () async {
                final sent = await AppReportDialog.show(
                  context,
                  api: widget.api,
                  token: widget.token,
                  campaignId: _item['id'] as Object,
                );
                if (sent && mounted) {
                  showAuthMessage(
                    context,
                    'Your app report was sent to the review team.',
                  );
                }
              },
              onStartReview: _claim,
            )
          : ListView(
              padding: const EdgeInsets.fromLTRB(18, 8, 18, 28),
              children: [
                ListingCard(
                  api: widget.api,
                  preferredCurrency: widget.preferredCurrency,
                  kind: widget.kind,
                  item: _item,
                  expanded: true,
                ),
                const SizedBox(height: 18),
                DetailCard(
                  children: [
                    const Text(
                      'About this task',
                      style: TextStyle(
                        fontWeight: FontWeight.w800,
                        fontSize: 16,
                        color: ink,
                      ),
                    ),
                    const SizedBox(height: 9),
                    Text(
                      description?.isNotEmpty == true ? description! : 'Follow the task instructions carefully and provide honest feedback.',
                      style: const TextStyle(color: mutedInk, height: 1.5),
                    ),
                    const SizedBox(height: 14),
                    DetailLine(label: 'Reward', value: reward),
                    if ((_item['location']?.toString().trim().isNotEmpty ??
                        false))
                      DetailLine(
                        label: 'Campaign location · info only',
                        value: _item['location'].toString(),
                      ),
                  ],
                ),
                const SizedBox(height: 18),
                FilledButton.icon(
                  onPressed: _claiming ? null : _claim,
                  icon: _claiming
                      ? const SizedBox(
                          width: 17,
                          height: 17,
                          child: CircularProgressIndicator(
                            strokeWidth: 2,
                            color: Colors.white,
                          ),
                        )
                      : const Icon(Icons.play_arrow_rounded),
                  label: Text(_claiming ? 'Starting task…' : 'Start ad share'),
                  style: FilledButton.styleFrom(
                    backgroundColor: brandGreen,
                    minimumSize: const Size.fromHeight(53),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(14),
                    ),
                  ),
                ),
              ],
            ),
    );
  }
}
