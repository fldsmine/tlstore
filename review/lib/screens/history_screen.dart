import 'package:flutter/material.dart';

import '../features/history/presentation/widgets/account_transaction_card.dart';
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
    ('other', 'Other'),
  ];

  late final TabController _tabController;
  final ScrollController _scrollController = ScrollController();
  final List<Map<String, dynamic>> _transactions = [];
  List<Map<String, dynamic>> _notifications = const [];
  int _page = 1;
  int _selectedActivityFilter = 0;
  bool _loading = true;
  bool _loadingMore = false;
  bool _hasMore = false;
  bool _notificationsLoaded = false;
  bool _notificationsLoading = false;
  String? _error;
  String? _notificationError;
  String _notificationType = 'all';

  @override
  void initState() {
    super.initState();
    _selectedActivityFilter = widget.initialTab
        .clamp(0, _activityFilters.length - 1)
        .toInt();
    _tabController = TabController(length: 2, vsync: this);
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

  void _onTabChanged() {
    if (_tabController.index == 1 &&
        !_notificationsLoaded &&
        !_notificationsLoading) {
      _loadNotifications();
    }
  }

  void _onScroll() {
    if (_scrollController.hasClients &&
        _scrollController.position.extentAfter < 240 &&
        _hasMore &&
        !_loadingMore) {
      _loadTransactions();
    }
  }

  Future<void> _loadTransactions({bool reset = false}) async {
    if (_loadingMore || (!reset && !_hasMore && _transactions.isNotEmpty)) {
      return;
    }
    if (reset) {
      setState(() {
        _loading = true;
        _error = null;
        _page = 1;
        _hasMore = false;
        _transactions.clear();
      });
    } else {
      setState(() => _loadingMore = true);
    }
    try {
      final response = await widget.api.accountHistory(
        widget.token,
        page: _page,
        perPage: 5,
      );
      final pageData = response['data'];
      final rows = pageData is Map<String, dynamic> && pageData['data'] is List
          ? (pageData['data'] as List)
                .whereType<Map<String, dynamic>>()
                .toList()
          : <Map<String, dynamic>>[];
      if (!mounted) return;
      setState(() {
        _transactions.addAll(rows);
        _page = _number(pageData, 'current_page', _page);
        final lastPage = _number(pageData, 'last_page', _page);
        _hasMore = _page < lastPage;
        _page++;
        _loading = false;
        _loadingMore = false;
      });
    } on AuthException catch (error) {
      if (mounted) {
        setState(() {
          _error = error.message;
          _loading = false;
          _loadingMore = false;
        });
      }
    }
  }

  int _number(dynamic map, String key, int fallback) =>
      map is Map<String, dynamic> && map[key] is num
      ? (map[key] as num).toInt()
      : fallback;

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
      if (mounted) {
        setState(() {
          _notificationError = error.message;
          _notificationsLoading = false;
        });
      }
    }
  }

  Future<void> _markNotificationRead(Map<String, dynamic> item) async {
    final id = item['id'];
    if (id == null || item['read_at'] != null) return;
    try {
      await widget.api.patch('notifications/$id/read', widget.token, const {});
      if (mounted) {
        setState(() => item['read_at'] = DateTime.now().toIso8601String());
      }
    } on AuthException catch (error) {
      if (mounted) showAuthMessage(context, error.message);
    }
  }

  List<Map<String, dynamic>> get _visibleTransactions {
    final filter = _activityFilters[_selectedActivityFilter].$1;
    if (filter == 'all') return _transactions;
    return _transactions.where((transaction) {
      final value =
          '${transaction['type'] ?? ''} ${transaction['entry_type'] ?? ''}'
              .toLowerCase();
      return switch (filter) {
        'review' => value.contains('review'),
        'share' => value.contains('share') || value.contains('ad_'),
        'payment' =>
          value.contains('payment') ||
              value.contains('topup') ||
              value.contains('deposit'),
        'withdrawal' => value.contains('withdraw'),
        'referral' => value.contains('referral'),
        _ =>
          !value.contains('review') &&
              !value.contains('share') &&
              !value.contains('ad_') &&
              !value.contains('payment') &&
              !value.contains('topup') &&
              !value.contains('deposit') &&
              !value.contains('withdraw') &&
              !value.contains('referral'),
      };
    }).toList();
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    backgroundColor: Theme.of(context).scaffoldBackgroundColor,
    appBar: AppBar(
      title: const Text(
        'History',
        style: TextStyle(fontWeight: FontWeight.w800),
      ),
      bottom: TabBar(
        controller: _tabController,
        tabs: const [
          Tab(text: 'Account activity'),
          Tab(text: 'Notifications'),
        ],
      ),
      actions: [
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
          icon: const Icon(Icons.tune_rounded),
        ),
      ],
    ),
    body: TabBarView(
      controller: _tabController,
      children: [_buildActivity(), _buildNotifications()],
    ),
  );

  Widget _buildActivity() {
    if (_loading) return const Center(child: CircularProgressIndicator());
    if (_error != null && _transactions.isEmpty) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(
                _error!,
                textAlign: TextAlign.center,
                style: const TextStyle(color: mutedInk),
              ),
              TextButton(
                onPressed: () => _loadTransactions(reset: true),
                child: const Text('Retry'),
              ),
            ],
          ),
        ),
      );
    }

    final visible = _visibleTransactions;
    return Column(
      children: [
        SizedBox(
          height: 49,
          child: ListView.separated(
            padding: const EdgeInsets.symmetric(horizontal: 15, vertical: 5),
            scrollDirection: Axis.horizontal,
            itemCount: _activityFilters.length,
            separatorBuilder: (_, _) => const SizedBox(width: 7),
            itemBuilder: (context, index) => ChoiceChip(
              label: Text(_activityFilters[index].$2),
              selected: _selectedActivityFilter == index,
              onSelected: (_) =>
                  setState(() => _selectedActivityFilter = index),
              showCheckmark: false,
              selectedColor: brandGreen,
              labelStyle: TextStyle(
                color: _selectedActivityFilter == index
                    ? Colors.white
                    : mutedInk,
                fontWeight: FontWeight.w700,
                fontSize: 11,
              ),
            ),
          ),
        ),
        Expanded(
          child: RefreshIndicator(
            onRefresh: () => _loadTransactions(reset: true),
            child: visible.isEmpty
                ? ListView(
                    physics: const AlwaysScrollableScrollPhysics(),
                    children: [
                      const SizedBox(height: 150),
                      Center(
                        child: Text(
                          _transactions.isEmpty
                              ? 'No account activity yet.'
                              : 'No ${_activityFilters[_selectedActivityFilter].$2.toLowerCase()} activity yet.',
                          style: const TextStyle(color: mutedInk),
                        ),
                      ),
                    ],
                  )
                : ListView.separated(
                    controller: _scrollController,
                    physics: const AlwaysScrollableScrollPhysics(),
                    padding: const EdgeInsets.fromLTRB(16, 10, 16, 22),
                    itemCount:
                        visible.length + (_loadingMore || _hasMore ? 1 : 0),
                    separatorBuilder: (_, _) => const SizedBox(height: 9),
                    itemBuilder: (context, index) {
                      if (index >= visible.length) {
                        return const Padding(
                          padding: EdgeInsets.all(14),
                          child: Center(
                            child: CircularProgressIndicator(strokeWidth: 2),
                          ),
                        );
                      }
                      return AccountTransactionCard(
                        transaction: visible[index],
                      );
                    },
                  ),
          ),
        ),
      ],
    );
  }

  Widget _buildNotifications() {
    if (_notificationsLoading) {
      return const Center(child: CircularProgressIndicator());
    }
    if (_notificationError != null && !_notificationsLoaded) {
      return Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(_notificationError!, style: const TextStyle(color: mutedInk)),
            TextButton(
              onPressed: _loadNotifications,
              child: const Text('Retry'),
            ),
          ],
        ),
      );
    }
    return NotificationTypeList(
      items: _notifications,
      selectedType: _notificationType,
      onSelectType: (type) => setState(() => _notificationType = type),
      onRefresh: _loadNotifications,
      onOpen: _markNotificationRead,
    );
  }
}
