import 'package:flutter/material.dart';

import '../features/ads/presentation/screens/ad_evidence_screen.dart';
import '../features/marketplace/presentation/widgets/app_listing_card.dart';
import '../features/marketplace/presentation/widgets/listing_components.dart';
import '../features/marketplace/presentation/screens/review_task_screen.dart';
import '../features/marketplace/presentation/listing_kind.dart';
import '../services/auth_api.dart';
import '../widgets/auth_components.dart';
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
  bool _loading = true;
  String? _error;
  List<Map<String, dynamic>> _items = const [];

  String get _path => widget.kind == ListingKind.appReview
      ? 'listings/apps?per_page=50'
      : 'listings/ads?per_page=50';
  String get _title =>
      widget.kind == ListingKind.appReview ? 'Review apps' : 'Share ads';

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      await widget.api.loadExchangeRates(widget.token);
      final response = await widget.api.get(_path, widget.token);
      final list = response['data'];
      if (!mounted) return;
      setState(() {
        _items = list is List
            ? list.whereType<Map<String, dynamic>>().toList()
            : const [];
        _loading = false;
      });
    } on AuthException catch (error) {
      if (mounted) {
        setState(() {
          _error = error.message;
          _loading = false;
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    backgroundColor: Theme.of(context).scaffoldBackgroundColor,
    appBar: AppBar(
      title: Text(_title, style: const TextStyle(fontWeight: FontWeight.w800)),
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
    ),
    body: _loading
        ? const Center(child: CircularProgressIndicator())
        : _error != null
        ? ListingErrorState(message: _error!, retry: _load)
        : _items.isEmpty
        ? ListingEmptyState(
            title: 'No tasks available yet',
            message: 'There are no active campaigns to show right now. Available apps and ads are visible whether or not you have a subscription.',
          )
        : RefreshIndicator(
            onRefresh: _load,
            child: ListView(
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
              children: [
                ListingIntro(kind: widget.kind, count: _items.length),
                const SizedBox(height: 14),
                const Padding(
                  padding: EdgeInsets.only(bottom: 12),
                  child: Text(
                    'Browse campaigns across all locations. Each listing shows whether your current plan covers it.',
                    style: TextStyle(
                      color: mutedInk,
                      fontSize: 12,
                      height: 1.4,
                    ),
                  ),
                ),
                ..._items.map(
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

  Future<void> _showAccessPrompt({required bool needsLocation}) async {
    if (needsLocation) {
      await showLocationCoveragePackages(
        context: context,
        api: widget.api,
        token: widget.token,
        user: widget.user,
        location:
            _item['location']?.toString() ??
            _app['location']?.toString() ??
            'this location',
      );
      if (mounted) await _loadListingGuidance();
      return;
    }
    final openPlans = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(
          needsLocation ? 'Add location coverage' : 'Choose a marketplace plan',
        ),
        content: Text(
          needsLocation
              ? 'Your marketplace plan is active, but this listing targets a location outside your current coverage. Add a location plan to continue.'
              : 'Subscribe to a marketplace plan to review apps and share ads. You can browse all available listings before subscribing.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: const Text('Not now'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(dialogContext, true),
            child: Text(
              needsLocation ? 'View location plans' : 'View marketplace plans',
            ),
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

  Future<void> _claim() async {
    final access = _item['task_access_status']?.toString();
    if (access == 'marketplace_plan_required') {
      await _showAccessPrompt(needsLocation: false);
      return;
    }
    if (access == 'location_coverage_required') {
      await _showAccessPrompt(needsLocation: true);
      return;
    }
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
      await Navigator.of(context).push(
        MaterialPageRoute<void>(
          builder: (_) => _isReview
              ? ReviewTaskScreen(
                  api: widget.api,
                  token: widget.token,
                  task: task,
                )
              : AdEvidenceScreen(
                  api: widget.api,
                  token: widget.token,
                  task: task,
                ),
        ),
      );
    } on AuthException catch (error) {
      if (!mounted) return;
      final message = error.message.toLowerCase();
      if (message.contains('marketplace plan') ||
          message.contains('marketplace subscription')) {
        await _showAccessPrompt(needsLocation: false);
      } else if (message.contains('location coverage') ||
          message.contains('location add-on') ||
          message.contains('location addon')) {
        await _showAccessPrompt(needsLocation: true);
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
      body: ListView(
        padding: const EdgeInsets.fromLTRB(18, 8, 18, 28),
        children: [
          if (_isReview)
            AppListingCard(
              item: _item,
              reward: reward,
              expanded: true,
              onTap: null,
            )
          else
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
              DetailLine(
                label: 'Target location',
                value: _item['location']?.toString() ?? 'Open to all locations',
              ),
              if (_isReview) ...[
                DetailLine(
                  label: 'Store',
                  value: _app['store']?.toString() ?? 'App store',
                ),
                DetailLine(
                  label: 'Rating',
                  value:
                      '${_app['rating'] ?? '—'}  •  ${_app['total_reviews'] ?? 0} reviews',
                ),
              ],
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
            label: Text(
              _claiming
                  ? 'Starting task…'
                  : _isReview
                  ? (_item['task_access_status'] == 'marketplace_plan_required'
                        ? 'View marketplace plans'
                        : _item['task_access_status'] ==
                              'location_coverage_required'
                        ? 'Add location coverage'
                        : 'Start app review')
                  : (_item['task_access_status'] == 'marketplace_plan_required'
                        ? 'View marketplace plans'
                        : _item['task_access_status'] ==
                              'location_coverage_required'
                        ? 'Add location coverage'
                        : 'Start ad share'),
            ),
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
