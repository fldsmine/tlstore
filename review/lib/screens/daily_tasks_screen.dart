import 'package:flutter/material.dart';

import '../core/currency/money.dart';
import '../features/ads/presentation/screens/ad_evidence_screen.dart';
import '../features/ads/presentation/screens/social_ad_task_screen.dart';
import '../features/marketplace/presentation/screens/review_task_screen.dart';
import 'my_tasks_screen.dart';
import 'social_accounts_setup_screen.dart';
import '../services/auth_api.dart';
import '../widgets/auth_components.dart';

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
  final _meta = <String, Map<String, dynamic>>{};
  Map<String, String> _socialAccounts = {};
  bool _loadingApps = true;
  bool _loadingAds = true;
  String? _appsError;
  String? _adsError;
  final Set<String> _starting = {};

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
              backgroundColor: Colors.amber.shade100,
              child: Icon(
                Icons.warning_amber_rounded,
                color: Colors.amber.shade900,
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
    final currency = item['currency']?.toString() ?? 'USD';
    return moneyConverted(
      widget.api,
      type == 'apps' ? item['review_reward_minor'] : item['share_reward'],
      currency,
      item['currency_minor_units'],
      widget.api.preferredCurrency,
      isMinor: type == 'apps',
    );
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Scaffold(
      appBar: AppBar(
        title: const Text(
          'Tasks for today',
          style: TextStyle(fontWeight: FontWeight.w800),
        ),
        bottom: TabBar(
          controller: _tabs,
          tabs: const [
            Tab(text: 'Apps'),
            Tab(text: 'Ads'),
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
        ],
      ),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 13, 16, 8),
            child: TextField(
              controller: _search,
              onSubmitted: (_) =>
                  _load(type: _tabs.index == 0 ? 'apps' : 'ads'),
              decoration: InputDecoration(
                prefixIcon: const Icon(Icons.search_rounded),
                hintText: 'Search ${_tabs.index == 0 ? 'apps' : 'ads'}',
                suffixIcon: IconButton(
                  icon: const Icon(Icons.search_rounded),
                  onPressed: () =>
                      _load(type: _tabs.index == 0 ? 'apps' : 'ads'),
                ),
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(14),
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
      backgroundColor: theme.scaffoldBackgroundColor,
    );
  }

  Widget _taskList(String type) {
    final loading = type == 'apps' ? _loadingApps : _loadingAds;
    final error = type == 'apps' ? _appsError : _adsError;
    final rows = _items[type] ?? const [];
    final meta = _meta[type] ?? const {};
    final remaining = meta['remaining_today'];
    final dailyRevenueLimit = meta['daily_revenue_limit_minor'];
    final dailyRevenueUsed = meta['daily_revenue_used_minor'] ??
        meta['daily_revenue_earned_minor'];
    final dailyRevenuePending = meta['daily_revenue_pending_minor'];
    final dailyRevenueCurrency =
        meta['daily_revenue_currency']?.toString() ?? 'USD';
    if (loading && rows.isEmpty) {
      return const Center(child: CircularProgressIndicator());
    }
    if (error != null && rows.isEmpty) {
      return Center(
        child: TextButton.icon(
          onPressed: () => _load(type: type),
          icon: const Icon(Icons.refresh),
          label: Text(error),
        ),
      );
    }
    return RefreshIndicator(
      onRefresh: () => _load(type: type),
      child: ListView(
        padding: const EdgeInsets.fromLTRB(16, 8, 16, 96),
        children: [
          if (meta['daily_limit'] != null)
            Padding(
              padding: const EdgeInsets.only(bottom: 12),
              child: Text(
                'Today: ${meta['used_today'] ?? 0} of ${meta['daily_limit']} tasks used${remaining == null ? '' : ' · $remaining remaining'}',
                style: Theme.of(context).textTheme.bodySmall,
              ),
            ),
          if (type == 'ads' && dailyRevenueLimit is num)
            Padding(
              padding: const EdgeInsets.only(bottom: 12),
              child: Text(
                'Daily cap used: ${moneyConverted(widget.api, dailyRevenueUsed ?? 0, dailyRevenueCurrency, null, widget.api.preferredCurrency, isMinor: true)} of ${moneyConverted(widget.api, dailyRevenueLimit, dailyRevenueCurrency, null, widget.api.preferredCurrency, isMinor: true)}${(dailyRevenuePending is num && dailyRevenuePending > 0) ? ' (includes pending proof)' : ''}',
                style: Theme.of(context).textTheme.bodySmall,
              ),
            ),
          if (meta['reason'] == 'marketplace_plan_required')
            _empty(
              'An active subscription is required to view today’s tasks.',
              Icons.lock_outline_rounded,
            )
          else if (meta['reason'] == 'daily_limit_reached')
            _empty(
              'You have reached today’s task limit. Come back tomorrow.',
              Icons.event_busy_rounded,
            )
          else if (meta['reason'] == 'daily_revenue_limit_reached')
            _empty(
              'You have reached your package’s daily ad earnings limit. You can share ads again tomorrow.',
              Icons.event_busy_rounded,
            )
          else if (meta['reason'] == 'daily_revenue_unavailable')
            _empty(
              'Daily earnings could not be checked right now. Refresh and try again shortly.',
              Icons.currency_exchange_rounded,
            )
          else if (meta['reason'] == 'package_task_type_not_included')
            _empty(
              'Your active package does not include ${type == 'apps' ? 'app reviews' : 'ad shares'}.',
              Icons.lock_outline_rounded,
            )
          else if (meta['reason'] == 'package_limit_reached')
            _empty(
              'You have used the ${type == 'apps' ? 'app review' : 'ad share'} limit for your active package.',
              Icons.event_busy_rounded,
            )
          else if (rows.isEmpty)
            _empty(
              'No eligible ${type == 'apps' ? 'app reviews' : 'ad shares'} are available right now.',
              Icons.inbox_outlined,
            )
          else
            ...rows.map((item) => _taskCard(type, item)),
        ],
      ),
    );
  }

  Widget _taskCard(String type, Map<String, dynamic> item) {
    final app = item['app'] is Map
        ? Map<String, dynamic>.from(item['app'])
        : <String, dynamic>{};
    final platform = type == 'apps'
        ? (app['store']?.toString() ?? 'App store')
        : _platformName(_platform(item['type']));
    final title = type == 'apps'
        ? (app['name']?.toString() ?? 'App review')
        : (item['title']?.toString() ?? 'Sponsored task');
    final subtitle = type == 'apps'
        ? (app['developer']?.toString() ?? '')
        : (item['description']?.toString() ?? '');
    final slots = item['slots_remaining'];
    final id = '$type:${item['id']}';
    final busy = _starting.contains(id);
    final theme = Theme.of(context);
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
                CircleAvatar(
                  backgroundColor: theme.colorScheme.primaryContainer,
                  child: Icon(
                    type == 'apps'
                        ? Icons.apps_rounded
                        : _platformIcon(_platform(item['type'])),
                    color: theme.colorScheme.onPrimaryContainer,
                  ),
                ),
                const SizedBox(width: 11),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        '$platform · ${type == 'apps' ? 'App review' : 'Task'}',
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
                Text(
                  _reward(type, item),
                  style: TextStyle(
                    color: theme.colorScheme.primary,
                    fontWeight: FontWeight.w900,
                    fontSize: 16,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 12),
            SizedBox(
              width: double.infinity,
              child: OutlinedButton(
                onPressed: busy ? null : () => _start(type, item),
                child: busy
                    ? const SizedBox(
                        height: 18,
                        width: 18,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : Text(type == 'apps' ? 'Review app' : 'Start task'),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _pill(IconData icon, String label) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
    decoration: BoxDecoration(
      color: Theme.of(context).colorScheme.surfaceContainerHighest,
      borderRadius: BorderRadius.circular(20),
    ),
    child: Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Icon(icon, size: 15),
        const SizedBox(width: 5),
        Text(
          label,
          style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 11),
        ),
      ],
    ),
  );

  Widget _empty(String text, IconData icon) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 60, horizontal: 24),
    child: Column(
      children: [
        Icon(
          icon,
          size: 45,
          color: Theme.of(context).colorScheme.onSurfaceVariant,
        ),
        const SizedBox(height: 12),
        Text(
          text,
          textAlign: TextAlign.center,
          style: Theme.of(context).textTheme.bodyMedium,
        ),
      ],
    ),
  );

  IconData _platformIcon(String platform) => switch (platform) {
    'telegram' => Icons.send_rounded,
    'facebook' => Icons.facebook_rounded,
    'youtube' => Icons.smart_display_rounded,
    'instagram' => Icons.camera_alt_outlined,
    'tiktok' => Icons.music_video_rounded,
    'whatsapp' => Icons.chat_rounded,
    'x' => Icons.alternate_email_rounded,
    _ => Icons.public_rounded,
  };
}
