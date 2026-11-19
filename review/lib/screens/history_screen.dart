import 'dart:math' as math;

import 'package:flutter/material.dart';

import '../core/currency/money.dart';
import '../features/history/presentation/widgets/activity_filter_sheet.dart';
import '../features/history/presentation/widgets/activity_grouped_list.dart';
import '../features/history/presentation/widgets/activity_stats_header.dart';
import '../features/notifications/presentation/widgets/notification_type_list.dart';
import '../services/auth_api.dart';
import '../widgets/auth_components.dart';
import 'notification_settings_screen.dart';

class HistoryScreen extends StatefulWidget {
  const HistoryScreen({
    super.key,
    required this.api,
    required this.token,
    this.initialTab = 0,
  });

  final AuthApi api;
  final String token;

  /// Selects the activity filter. 0=All, 1=Reviews, 2=Ad shares, etc.
  final int initialTab;

  @override
  State<HistoryScreen> createState() => _HistoryScreenState();
}

class _HistoryScreenState extends State<HistoryScreen>
    with SingleTickerProviderStateMixin {
  static const _activityFilters = <(String, String)>[
    ('all', 'All'),
    ('review', 'App reviews'),
    ('share', 'Ad shares'),
    ('payment', 'Payments'),
    ('withdrawal', 'Withdrawals'),
    ('referral', 'Referrals'),
    ('bonus', 'Bonuses'),
    ('other', 'Other'),
  ];

  late final TabController _tabController;
  final ScrollController _scrollController = ScrollController();

  final List<Map<String, dynamic>> _transactions = [];
  List<Map<String, dynamic>> _notifications = const [];
  List<Map<String, dynamic>> _reviewSubmissions = const [];
  Map<String, dynamic> _statistics = const {};

  int _page = 1;
  int _selectedActivityFilter = 0;
  String _statusFilter = 'all';
  DateTime? _fromDate;
  DateTime? _toDate;

  bool _loading = true;
  bool _loadingMore = false;
  bool _hasMore = false;

  bool _notificationsLoaded = false;
  bool _notificationsLoading = false;
  bool _reviewsLoaded = false;
  bool _reviewsLoading = false;

  String? _error;
  String? _notificationError;
  String? _reviewError;
  String _notificationType = 'all';

  @override
  void initState() {
    super.initState();

    _selectedActivityFilter = widget.initialTab
        .clamp(0, _activityFilters.length - 1)
        .toInt();

    _tabController = TabController(length: 3, vsync: this);
    _tabController.addListener(_onTabChanged);

    _scrollController.addListener(_onScroll);

    _loadTransactions(reset: true);
  }

  @override
  void dispose() {
    _tabController
      ..removeListener(_onTabChanged)
      ..dispose();

    _scrollController
      ..removeListener(_onScroll)
      ..dispose();

    super.dispose();
  }

  // ---------------------------------------------------------------------------
  // API
  // ---------------------------------------------------------------------------

  void _onTabChanged() {
    if (_tabController.index == 1 && !_reviewsLoaded && !_reviewsLoading) {
      _loadReviewSubmissions();
    }
    if (_tabController.index == 2 &&
        !_notificationsLoaded &&
        !_notificationsLoading) {
      _loadNotifications();
    }
  }

  Future<void> _markAllRead() async {
    final unread = _notifications
        .where((item) => item['read_at'] == null && item['id'] != null)
        .toList();
    if (unread.isEmpty) return;
    try {
      await Future.wait(
        unread.map(
          (item) => widget.api.patch(
            'notifications/${item['id']}/read',
            widget.token,
            const {},
          ),
        ),
      );
      if (!mounted) return;
      final readAt = DateTime.now().toIso8601String();
      setState(() {
        for (final item in unread) {
          item['read_at'] = readAt;
        }
      });
      showAuthMessage(
        context,
        '${unread.length} notification${unread.length == 1 ? '' : 's'} marked as read.',
      );
    } on AuthException catch (error) {
      if (mounted) showAuthMessage(context, error.message);
    }
  }

  Future<void> _loadReviewSubmissions() async {
    setState(() {
      _reviewsLoading = true;
      _reviewError = null;
    });
    try {
      final response = await widget.api.get(
        'me/review-submissions?per_page=30',
        widget.token,
      );
      final data = response['data'];
      final values = data is Map ? data['data'] : data;
      if (!mounted) return;
      setState(() {
        _reviewSubmissions = values is List
            ? values
                  .whereType<Map>()
                  .map((row) => Map<String, dynamic>.from(row))
                  .toList()
            : const [];
        _reviewsLoaded = true;
        _reviewsLoading = false;
      });
    } on AuthException catch (error) {
      if (!mounted) return;
      setState(() {
        _reviewError = error.message;
        _reviewsLoading = false;
      });
    }
  }

  void _onScroll() {
    if (!_scrollController.hasClients) return;

    if (_scrollController.position.extentAfter < 240 &&
        _hasMore &&
        !_loadingMore &&
        !_loading) {
      _loadTransactions();
    }
  }

  Future<void> _loadTransactions({bool reset = false}) async {
    if (_loadingMore || (_loading && !reset)) return;

    if (reset) {
      setState(() {
        _loading = true;
        _error = null;
        _page = 1;
        _hasMore = false;
        _transactions.clear();
      });
    } else {
      if (!_hasMore) return;
      setState(() => _loadingMore = true);
    }

    try {
      await widget.api.loadExchangeRates(widget.token);

      final response = await widget.api.accountHistory(
        widget.token,
        page: _page,
        perPage: 5,
        category: _activityFilters[_selectedActivityFilter].$1,
        status: _statusFilter,
        fromDate: _fromDate,
        toDate: _toDate,
      );

      final pageData = response['data'];
      final responseMeta = response['meta'];
      final stats =
          responseMeta is Map<String, dynamic> && responseMeta['stats'] is Map
          ? Map<String, dynamic>.from(responseMeta['stats'] as Map)
          : <String, dynamic>{};

      final rows = pageData is Map<String, dynamic> && pageData['data'] is List
          ? (pageData['data'] as List)
                .whereType<Map<String, dynamic>>()
                .toList()
          : <Map<String, dynamic>>[];

      if (!mounted) return;

      setState(() {
        _statistics = stats;
        _transactions.addAll(rows);

        _page = _number(pageData, 'current_page', _page);

        final lastPage = _number(pageData, 'last_page', _page);

        _hasMore = _page < lastPage;
        _page++;

        _loading = false;
        _loadingMore = false;
      });
    } on AuthException catch (error) {
      if (!mounted) return;

      setState(() {
        _error = error.message;
        _loading = false;
        _loadingMore = false;
      });
    }
  }

  Future<void> _showActivityFilters() async {
    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => ActivityFilterSheet(
        status: _statusFilter,
        fromDate: _fromDate,
        toDate: _toDate,
        onApply: (status, fromDate, toDate) {
          setState(() {
            _statusFilter = status;
            _fromDate = fromDate;
            _toDate = toDate;
          });
          _loadTransactions(reset: true);
        },
      ),
    );
  }

  bool get _hasHistoryFilters =>
      _statusFilter != 'all' || _fromDate != null || _toDate != null;

  String _statsAmount(dynamic raw) {
    final target = widget.api.preferredCurrency.toUpperCase();
    if (raw is! Map) return formatMajorMoney(0, target);
    var total = 0.0;
    for (final entry in raw.entries) {
      final currency = entry.key.toString().toUpperCase();
      final minor = double.tryParse(entry.value?.toString() ?? '') ?? 0;
      final major = minor / math.pow(10, currencyPrecision(currency));
      final converted = widget.api.convertMajor(major, currency, target);
      if (converted == null) {
        if (currency != target && minor != 0) return '—';
        total += major;
      } else {
        total += converted;
      }
    }
    return formatMajorMoney(total, target);
  }

  String _displayAmount(Map<String, dynamic> transaction) => moneyConverted(
    widget.api,
    transaction['amount_minor'],
    transaction['currency']?.toString() ?? widget.api.preferredCurrency,
    transaction['currency_minor_units'],
    widget.api.preferredCurrency,
    isMinor: true,
  );

  int _number(dynamic map, String key, int fallback) {
    if (map is Map<String, dynamic> && map[key] is num) {
      return (map[key] as num).toInt();
    }

    return fallback;
  }

  Future<void> _loadNotifications() async {
    setState(() {
      _notificationsLoading = true;
      _notificationError = null;
    });

    try {
      final response = await widget.api.get('notifications', widget.token);

      final data = response['data'];
      final values = data is Map<String, dynamic> ? data['data'] : data;

      if (!mounted) return;

      setState(() {
        _notifications = values is List
            ? values.whereType<Map<String, dynamic>>().toList()
            : const [];

        _notificationsLoaded = true;
        _notificationsLoading = false;
      });
    } on AuthException catch (error) {
      if (!mounted) return;

      setState(() {
        _notificationError = error.message;
        _notificationsLoading = false;
      });
    }
  }

  Future<void> _markNotificationRead(Map<String, dynamic> item) async {
    final id = item['id'];

    if (id == null || item['read_at'] != null) return;

    try {
      await widget.api.patch('notifications/$id/read', widget.token, const {});

      if (!mounted) return;

      setState(() {
        item['read_at'] = DateTime.now().toIso8601String();
      });
    } on AuthException catch (error) {
      if (mounted) {
        showAuthMessage(context, error.message);
      }
    }
  }

  // ---------------------------------------------------------------------------
  // Activity filters
  // ---------------------------------------------------------------------------

  Widget _buildFilters() {
    final colors = Theme.of(context).colorScheme;
    return SizedBox(
      height: 48,
      child: ListView.separated(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
        scrollDirection: Axis.horizontal,
        itemCount: _activityFilters.length,
        separatorBuilder: (_, _) => const SizedBox(width: 8),
        itemBuilder: (context, index) {
          final selected = _selectedActivityFilter == index;
          return ChoiceChip(
            label: Text(_activityFilters[index].$2),
            selected: selected,
            onSelected: (_) {
              setState(() => _selectedActivityFilter = index);
              _loadTransactions(reset: true);
            },
            showCheckmark: false,
            selectedColor: colors.primary,
            backgroundColor: colors.surfaceContainerLow,
            side: BorderSide(
              color: selected ? colors.primary : colors.outlineVariant,
            ),
            labelStyle: TextStyle(
              color: selected ? colors.onPrimary : colors.onSurfaceVariant,
              fontSize: 12,
              fontWeight: FontWeight.w700,
            ),
            padding: const EdgeInsets.symmetric(horizontal: 9),
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(13),
            ),
          );
        },
      ),
    );
  }

  Widget _buildActiveFilters() {
    if (!_hasHistoryFilters) return const SizedBox.shrink();
    final labels = <String>[];
    if (_statusFilter != 'all') {
      labels.add('Status: ${_statusFilter.replaceAll('_', ' ')}');
    }
    if (_fromDate != null) {
      labels.add('From: ${_shortDate(_fromDate!)}');
    }
    if (_toDate != null) {
      labels.add('To: ${_shortDate(_toDate!)}');
    }
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 4, 16, 4),
      child: Wrap(
        spacing: 7,
        runSpacing: 4,
        crossAxisAlignment: WrapCrossAlignment.center,
        children: [
          ...labels.map(
            (label) => Chip(
              label: Text(label, style: const TextStyle(fontSize: 11)),
              visualDensity: VisualDensity.compact,
              backgroundColor: Theme.of(context).colorScheme.primaryContainer,
              side: BorderSide.none,
            ),
          ),
          TextButton(
            onPressed: () {
              setState(() {
                _statusFilter = 'all';
                _fromDate = null;
                _toDate = null;
              });
              _loadTransactions(reset: true);
            },
            child: const Text('Clear'),
          ),
        ],
      ),
    );
  }

  String _shortDate(DateTime value) =>
      '${value.day}/${value.month}/${value.year}';

  // ---------------------------------------------------------------------------
  // Main layout
  // ---------------------------------------------------------------------------

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final colors = theme.colorScheme;

    return Scaffold(
      backgroundColor: colors.surface,
      appBar: AppBar(
        title: Text(
          'History',
          style: theme.textTheme.titleLarge?.copyWith(
            fontWeight: FontWeight.w800,
          ),
        ),
        centerTitle: false,
        elevation: 0,
        scrolledUnderElevation: 0,
        backgroundColor: colors.surface,
        surfaceTintColor: Colors.transparent,
        actions: [
          IconButton(
            tooltip: 'Filter activity',
            onPressed: _showActivityFilters,
            icon: Stack(
              clipBehavior: Clip.none,
              children: [
                const Icon(Icons.tune_rounded),
                if (_hasHistoryFilters)
                  Positioned(
                    right: -1,
                    top: -1,
                    child: Container(
                      width: 8,
                      height: 8,
                      decoration: BoxDecoration(
                        color: colors.error,
                        shape: BoxShape.circle,
                      ),
                    ),
                  ),
              ],
            ),
          ),
          IconButton(
            tooltip: 'Refresh history',
            onPressed: () {
              if (_tabController.index == 1) {
                _loadReviewSubmissions();
              } else if (_tabController.index == 2) {
                _loadNotifications();
              } else {
                _loadTransactions(reset: true);
              }
            },
            icon: const Icon(Icons.refresh_rounded),
          ),
          IconButton(
            tooltip: 'Notification settings',
            onPressed: () => Navigator.push(
              context,
              MaterialPageRoute<void>(
                builder: (_) => NotificationSettingsScreen(
                  api: widget.api,
                  token: widget.token,
                ),
              ),
            ),
            icon: const Icon(Icons.notifications_outlined),
          ),
          const SizedBox(width: 5),
        ],
        bottom: TabBar(
          controller: _tabController,
          tabs: const [
            Tab(text: 'Account activity'),
            Tab(text: 'App reviews'),
            Tab(text: 'Notifications'),
          ],
          labelStyle: theme.textTheme.labelLarge?.copyWith(
            fontWeight: FontWeight.w700,
          ),
          unselectedLabelStyle: theme.textTheme.labelLarge,
          indicatorWeight: 3,
          indicatorSize: TabBarIndicatorSize.label,
        ),
      ),
      body: TabBarView(
        controller: _tabController,
        children: [
          _buildActivity(),
          _buildReviewSubmissions(),
          _buildNotifications(),
        ],
      ),
    );
  }

  // ---------------------------------------------------------------------------
  // Activity screen
  // ---------------------------------------------------------------------------

  Widget _buildActivity() {
    final stats = _statistics;
    final theme = Theme.of(context);
    final colors = theme.colorScheme;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        ActivityStatsHeader(
          total: (stats['total'] as num?)?.toInt() ?? 0,
          thisMonth: (stats['this_month'] as num?)?.toInt() ?? 0,
          earned: _statsAmount(stats['earned_minor_by_currency']),
          spent: _statsAmount(stats['spent_minor_by_currency']),
        ),
        Padding(
          padding: const EdgeInsets.fromLTRB(18, 1, 18, 10),
          child: Row(
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Your activity',
                      style: theme.textTheme.titleMedium?.copyWith(
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const SizedBox(height: 3),
                    Text(
                      'Track account transactions and rewards.',
                      style: theme.textTheme.bodySmall?.copyWith(
                        color: colors.onSurfaceVariant,
                      ),
                    ),
                  ],
                ),
              ),
              if (_loading && _transactions.isNotEmpty)
                const SizedBox(
                  width: 18,
                  height: 18,
                  child: CircularProgressIndicator(strokeWidth: 2),
                ),
            ],
          ),
        ),
        _buildFilters(),
        _buildActiveFilters(),
        if (_error != null && _transactions.isEmpty)
          Expanded(
            child: _buildErrorState(
              message: _error!,
              onRetry: () => _loadTransactions(reset: true),
            ),
          )
        else if (_loading && _transactions.isEmpty)
          const Expanded(child: Center(child: CircularProgressIndicator()))
        else
          Expanded(
            child: RefreshIndicator(
              onRefresh: () => _loadTransactions(reset: true),
              child: _transactions.isEmpty
                  ? _buildEmptyState()
                  : CustomScrollView(
                      controller: _scrollController,
                      physics: const AlwaysScrollableScrollPhysics(),
                      slivers: [
                        SliverPadding(
                          padding: const EdgeInsets.fromLTRB(16, 8, 16, 28),
                          sliver: ActivityGroupedList(
                            transactions: _transactions,
                            displayAmount: _displayAmount,
                          ),
                        ),
                        if (_loadingMore || _hasMore)
                          const SliverToBoxAdapter(
                            child: Padding(
                              padding: EdgeInsets.fromLTRB(16, 4, 16, 25),
                              child: Center(
                                child: SizedBox(
                                  width: 22,
                                  height: 22,
                                  child: CircularProgressIndicator(
                                    strokeWidth: 2,
                                  ),
                                ),
                              ),
                            ),
                          ),
                      ],
                    ),
            ),
          ),
      ],
    );
  }

  Widget _buildReviewSubmissions() {
    final theme = Theme.of(context);
    final colors = theme.colorScheme;
    if (_reviewsLoading && _reviewSubmissions.isEmpty) {
      return const Center(child: CircularProgressIndicator());
    }
    if (_reviewError != null && !_reviewsLoaded) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(_reviewError!, textAlign: TextAlign.center),
              const SizedBox(height: 10),
              TextButton.icon(
                onPressed: _loadReviewSubmissions,
                icon: const Icon(Icons.refresh_rounded),
                label: const Text('Try again'),
              ),
            ],
          ),
        ),
      );
    }
    if (_reviewSubmissions.isEmpty) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(28),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(
                Icons.rate_review_outlined,
                size: 44,
                color: colors.onSurfaceVariant,
              ),
              const SizedBox(height: 12),
              Text(
                'No submitted app reviews yet',
                style: theme.textTheme.titleMedium?.copyWith(
                  fontWeight: FontWeight.w800,
                ),
              ),
              const SizedBox(height: 5),
              Text(
                'Reviews appear here after you submit them. Unsubmitted reviews stay in My Tasks so you can continue them.',
                textAlign: TextAlign.center,
                style: theme.textTheme.bodyMedium?.copyWith(
                  color: colors.onSurfaceVariant,
                ),
              ),
            ],
          ),
        ),
      );
    }

    return RefreshIndicator(
      onRefresh: _loadReviewSubmissions,
      child: ListView.separated(
        padding: const EdgeInsets.all(16),
        itemCount: _reviewSubmissions.length,
        separatorBuilder: (_, _) => const SizedBox(height: 9),
        itemBuilder: (context, index) {
          final submission = _reviewSubmissions[index];
          final task = submission['task'] is Map
              ? Map<String, dynamic>.from(submission['task'] as Map)
              : <String, dynamic>{};
          final campaign = task['campaign'] is Map
              ? Map<String, dynamic>.from(task['campaign'] as Map)
              : <String, dynamic>{};
          final app = campaign['app'] is Map
              ? Map<String, dynamic>.from(campaign['app'] as Map)
              : <String, dynamic>{};
          final status = submission['status']?.toString() ?? 'pending_review';
          final rejected = status == 'rejected';
          final approved = status == 'approved';
          final statusColor = rejected
              ? colors.error
              : approved
              ? colors.primary
              : colors.tertiary;
          final decisions = submission['moderation_decisions'];
          final lastDecision =
              decisions is List && decisions.isNotEmpty && decisions.last is Map
              ? Map<String, dynamic>.from(decisions.last as Map)
              : <String, dynamic>{};
          final reason = lastDecision['reason']?.toString();
          final date = DateTime.tryParse(
            submission['submitted_at']?.toString() ?? '',
          );
          return Card(
            child: ListTile(
              leading: CircleAvatar(
                backgroundColor: statusColor.withValues(alpha: .12),
                foregroundColor: statusColor,
                child: const Icon(Icons.rate_review_rounded),
              ),
              title: Text(
                app['name']?.toString() ??
                    campaign['title']?.toString() ??
                    'App review',
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(fontWeight: FontWeight.w700),
              ),
              subtitle: Text(
                '${date == null ? '' : _shortDate(date)} · ${rejected
                    ? 'Rejected'
                    : approved
                    ? 'Approved'
                    : 'Awaiting admin review'}${rejected && reason != null && reason.trim().isNotEmpty ? '\n$reason' : ''}',
                maxLines: rejected ? 3 : 2,
                overflow: TextOverflow.ellipsis,
              ),
              trailing: Icon(
                rejected
                    ? Icons.cancel_outlined
                    : approved
                    ? Icons.check_circle_outline
                    : Icons.hourglass_top_rounded,
                color: statusColor,
              ),
              isThreeLine:
                  rejected && reason != null && reason.trim().isNotEmpty,
            ),
          );
        },
      ),
    );
  }

  // ---------------------------------------------------------------------------
  // Empty and error states
  // ---------------------------------------------------------------------------

  Widget _buildEmptyState() {
    final theme = Theme.of(context);
    final colors = theme.colorScheme;

    final isAll = _selectedActivityFilter == 0;

    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.symmetric(horizontal: 30),
      children: [
        const SizedBox(height: 95),
        Container(
          height: 76,
          width: 76,
          alignment: Alignment.center,
          decoration: BoxDecoration(
            color: colors.surfaceContainerHighest,
            shape: BoxShape.circle,
          ),
          child: Icon(
            Icons.receipt_long_rounded,
            size: 34,
            color: colors.onSurfaceVariant,
          ),
        ),
        const SizedBox(height: 20),
        Text(
          isAll ? 'No activity yet' : 'Nothing to show here',
          textAlign: TextAlign.center,
          style: theme.textTheme.titleMedium?.copyWith(
            fontWeight: FontWeight.w800,
          ),
        ),
        const SizedBox(height: 8),
        Text(
          isAll
              ? 'Your account transactions and rewards will appear here when available.'
              : 'There are no ${_activityFilters[_selectedActivityFilter].$2.toLowerCase()} records yet.',
          textAlign: TextAlign.center,
          style: theme.textTheme.bodySmall?.copyWith(
            color: colors.onSurfaceVariant,
            height: 1.5,
          ),
        ),
      ],
    );
  }

  Widget _buildErrorState({
    required String message,
    required VoidCallback onRetry,
  }) {
    final theme = Theme.of(context);
    final colors = theme.colorScheme;

    return Center(
      child: Padding(
        padding: const EdgeInsets.all(28),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(Icons.cloud_off_rounded, size: 42, color: colors.error),
            const SizedBox(height: 14),
            Text(
              'Unable to load activity',
              style: theme.textTheme.titleMedium?.copyWith(
                fontWeight: FontWeight.w800,
              ),
            ),
            const SizedBox(height: 7),
            Text(
              message,
              textAlign: TextAlign.center,
              style: theme.textTheme.bodySmall?.copyWith(
                color: colors.onSurfaceVariant,
                height: 1.4,
              ),
            ),
            const SizedBox(height: 18),
            FilledButton.icon(
              onPressed: onRetry,
              icon: const Icon(Icons.refresh_rounded),
              label: const Text('Try again'),
            ),
          ],
        ),
      ),
    );
  }

  // ---------------------------------------------------------------------------
  // Notifications
  // ---------------------------------------------------------------------------

  Widget _buildNotifications() {
    final theme = Theme.of(context);
    final colors = theme.colorScheme;

    if (_notificationsLoading) {
      return const Center(child: CircularProgressIndicator());
    }

    if (_notificationError != null && !_notificationsLoaded) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(
                Icons.notifications_off_outlined,
                size: 38,
                color: colors.onSurfaceVariant,
              ),
              const SizedBox(height: 12),
              Text(
                _notificationError!,
                textAlign: TextAlign.center,
                style: theme.textTheme.bodyMedium?.copyWith(
                  color: colors.onSurfaceVariant,
                ),
              ),
              const SizedBox(height: 12),
              TextButton.icon(
                onPressed: _loadNotifications,
                icon: const Icon(Icons.refresh_rounded),
                label: const Text('Retry'),
              ),
            ],
          ),
        ),
      );
    }

    return NotificationTypeList(
      items: _notifications,
      selectedType: _notificationType,
      onSelectType: (type) {
        setState(() {
          _notificationType = type;
        });
      },
      onRefresh: _loadNotifications,
      onOpen: _markNotificationRead,
      onMarkAllRead: _markAllRead,
    );
  }
}
