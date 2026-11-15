import 'package:flutter/material.dart';

import '../features/ads/presentation/screens/ad_evidence_screen.dart';
import '../features/ads/presentation/screens/social_ad_task_screen.dart';
import '../features/marketplace/presentation/screens/review_task_screen.dart';
import '../services/auth_api.dart';

class MyTasksScreen extends StatefulWidget {
  const MyTasksScreen({
    super.key,
    required this.api,
    required this.token,
    this.user = const {},
  });

  final AuthApi api;
  final String token;
  final Map<String, dynamic> user;

  @override
  State<MyTasksScreen> createState() => _MyTasksScreenState();
}

class _MyTasksScreenState extends State<MyTasksScreen> {
  static const _paths = [
    'me/review-tasks',
    'me/review-submissions',
    'me/ad-share-tasks',
    'me/ad-share-events',
  ];
  final List<List<Map<String, dynamic>>> _sources = [[], [], [], []];
  final List<int> _pages = [1, 1, 1, 1];
  final List<int> _lastPages = [1, 1, 1, 1];
  bool _loading = true;
  bool _loadingMore = false;
  String? _error;
  String _filter = 'All';

  @override
  void initState() {
    super.initState();
    _load(reset: true);
  }

  Map<String, dynamic>? _map(dynamic value) =>
      value is Map ? Map<String, dynamic>.from(value) : null;

  List<Map<String, dynamic>> _rows(dynamic data) {
    final page = _map(data);
    final raw = page?['data'] ?? data;
    if (raw is! List) return const [];
    return raw.map(_map).whereType<Map<String, dynamic>>().toList();
  }

  Future<void> _load({bool reset = false, bool more = false}) async {
    if (reset) {
      for (final source in _sources) {
        source.clear();
      }
      for (var i = 0; i < 4; i++) {
        _pages[i] = 1;
        _lastPages[i] = 1;
      }
    }
    setState(() {
      _loading = !more;
      _loadingMore = more;
      _error = null;
    });
    try {
      await Future.wait(
        List.generate(_paths.length, (index) async {
          if (_pages[index] > _lastPages[index]) return;
          final response = await widget.api.get(
            '${_paths[index]}?page=${_pages[index]}',
            widget.token,
          );
          final payload = response['data'];
          final page = _map(payload);
          _sources[index].addAll(_rows(payload));
          final last = int.tryParse(page?['last_page']?.toString() ?? '');
          final current = int.tryParse(page?['current_page']?.toString() ?? '');
          if (last != null) _lastPages[index] = last;
          if (current != null) {
            _pages[index] = current + 1;
          } else {
            _lastPages[index] = _pages[index];
          }
        }),
      );
      if (mounted)
        setState(() {
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
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _error = 'We could not load your task activity. Please try again.';
        _loading = false;
        _loadingMore = false;
      });
    }
  }

  String _id(dynamic value) => value?.toString() ?? '';

  Map<String, dynamic> _activityRows() {
    final byTask = <String, Map<String, dynamic>>{};
    for (final submission in _sources[1]) {
      final taskId = _id(submission['review_task_id'] ?? submission['task_id']);
      if (taskId.isNotEmpty) byTask['review:$taskId'] = submission;
    }
    for (final event in _sources[3]) {
      final taskId = _id(event['ad_share_task_id'] ?? event['task_id']);
      if (taskId.isNotEmpty) byTask['ad:$taskId'] = event;
    }

    final entries = <Map<String, dynamic>>[];
    for (final task in _sources[0]) {
      final status = byTask['review:${_id(task['id'])}'];
      final campaign = _map(task['campaign']);
      final app = _map(campaign?['app']);
      entries.add(
        _entry(
          id: 'review:${task['id']}',
          title:
              app?['name']?.toString() ??
              campaign?['title']?.toString() ??
              'App review',
          kind: 'App review',
          status:
              status?['status']?.toString() ??
              task['status']?.toString() ??
              'in_progress',
          date:
              status?['submitted_at'] ??
              task['claimed_at'] ??
              task['created_at'],
          decision: _lastDecision(status),
          task: task,
          canResume: _canResumeReview(task, status),
        ),
      );
    }
    for (final task in _sources[2]) {
      final event = byTask['ad:${_id(task['id'])}'];
      final ad = _map(task['ad']);
      entries.add(
        _entry(
          id: 'ad:${task['id']}',
          title: ad?['title']?.toString() ?? 'Sponsored ad',
          kind: 'Ad share',
          status:
              event?['status']?.toString() ??
              task['status']?.toString() ??
              'in_progress',
          date: event?['shared_at'] ?? task['claimed_at'] ?? task['created_at'],
          decision: _lastDecision(event),
          task: task,
          canResume: task['status']?.toString() == 'reserved' && event == null,
        ),
      );
    }
    entries.sort(
      (a, b) => (b['date'] as DateTime).compareTo(a['date'] as DateTime),
    );
    return {'entries': entries};
  }

  String? _lastDecision(Map<String, dynamic>? record) {
    final decisions = record?['moderation_decisions'];
    if (decisions is List && decisions.isNotEmpty) {
      final decision = _map(decisions.last);
      final reason = decision?['reason']?.toString();
      if (reason != null && reason.trim().isNotEmpty) return reason;
    }
    return null;
  }

  bool _canResumeReview(
    Map<String, dynamic> task,
    Map<String, dynamic>? submission,
  ) {
    if (submission != null) return false;
    final status = task['status']?.toString();
    if (!const {'reserved', 'in_progress', 'assigned'}.contains(status)) {
      return false;
    }
    final expiresAt = DateTime.tryParse(task['expires_at']?.toString() ?? '');
    return expiresAt == null || expiresAt.isAfter(DateTime.now());
  }

  Map<String, dynamic> _entry({
    required String id,
    required String title,
    required String kind,
    required String status,
    required dynamic date,
    String? decision,
    Map<String, dynamic>? task,
    bool canResume = false,
  }) => {
    'id': id,
    'title': title,
    'kind': kind,
    'status': status,
    'date': DateTime.tryParse(date?.toString() ?? '') ?? DateTime(2000),
    'decision': decision,
    'task': task,
    'can_resume': canResume,
  };

  String _adType(Map<String, dynamic> task) {
    final ad = _map(task['ad']);
    return (ad?['type']?.toString() ?? 'others').trim().toLowerCase();
  }

  bool _hasSocialFlow(String type) =>
      const {
        'telegram',
        'facebook',
        'youtube',
        'instagram',
        'tiktok',
        'x',
        'whatsapp',
      }.any(
        (platform) =>
            type.contains(platform) ||
            (platform == 'x' && type.contains('twitter')),
      );

  Future<void> _resumeTask(Map<String, dynamic> row) async {
    final task = _map(row['task']);
    if (task == null) return;
    final isReview = row['kind'] == 'App review';
    if (!isReview && task['status']?.toString() != 'reserved') {
      return;
    }
    if (!isReview && !_hasSocialFlow(_adType(task))) {
      await Navigator.of(context).push(
        MaterialPageRoute<void>(
          builder: (_) => AdEvidenceScreen(
            api: widget.api,
            token: widget.token,
            task: task,
            user: widget.user,
          ),
        ),
      );
    } else if (!isReview) {
      await Navigator.of(context).push(
        MaterialPageRoute<void>(
          builder: (_) => SocialAdTaskScreen(
            api: widget.api,
            token: widget.token,
            task: task,
            user: widget.user,
          ),
        ),
      );
    } else {
      await Navigator.of(context).push(
        MaterialPageRoute<void>(
          builder: (_) => ReviewTaskScreen(
            api: widget.api,
            token: widget.token,
            task: task,
            user: widget.user,
          ),
        ),
      );
    }
    if (mounted) await _load(reset: true);
  }

  String _statusLabel(String value) {
    switch (value.toLowerCase()) {
      case 'reserved':
        return 'Proof not submitted';
      case 'pending_validation':
      case 'submitted':
      case 'under_review':
      case 'pending_review':
        return 'Pending review';
      case 'needs_information':
        return 'More information needed';
      case 'approved':
      case 'validated':
      case 'completed':
        return 'Approved';
      case 'rejected':
        return 'Rejected';
      case 'expired':
        return 'Expired';
      case 'cancelled':
        return 'Cancelled';
      case 'in_progress':
        return 'In progress';
      default:
        return 'Proof not submitted';
    }
  }

  bool _matches(String filter, String status) {
    final label = _statusLabel(status);
    return filter == 'All' ||
        (filter == 'Proof needed' && label == 'Proof not submitted') ||
        (filter == 'Pending review' &&
            (label == 'Pending review' ||
                label == 'More information needed')) ||
        (filter == 'Completed' &&
            ['Approved', 'Rejected', 'Expired', 'Cancelled'].contains(label));
  }

  Color _statusColor(ColorScheme colors, String status) {
    switch (_statusLabel(status)) {
      case 'Approved':
        return colors.primary;
      case 'Rejected':
      case 'Expired':
        return colors.error;
      case 'Pending review':
      case 'More information needed':
        return colors.tertiary;
      case 'Proof not submitted':
        return colors.tertiary;
      default:
        return colors.primary;
    }
  }

  String _formatDate(DateTime date) =>
      '${date.day.toString().padLeft(2, '0')} ${_months[date.month - 1]} ${date.year}';
  static const _months = [
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

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final colors = theme.colorScheme;
    final all = (_activityRows()['entries'] as List<Map<String, dynamic>>);
    final visible = all
        .where((row) => _matches(_filter, row['status'] as String))
        .toList();
    final hasMore = List.generate(
      4,
      (i) => _pages[i] <= _lastPages[i],
    ).any((v) => v);
    return Scaffold(
      appBar: AppBar(title: const Text('My tasks')),
      body: RefreshIndicator(
        onRefresh: () => _load(reset: true),
        child: ListView(
          padding: const EdgeInsets.fromLTRB(18, 10, 18, 24),
          children: [
            Text(
              'Track every review and share you have started.',
              style: theme.textTheme.bodyMedium?.copyWith(
                color: colors.onSurfaceVariant,
              ),
            ),
            const SizedBox(height: 14),
            SingleChildScrollView(
              scrollDirection: Axis.horizontal,
              child: Row(
                children: [
                  for (final filter in const [
                    'All',
                    'Proof needed',
                    'Pending review',
                    'Completed',
                  ]) ...[
                    Padding(
                      padding: const EdgeInsets.only(right: 8),
                      child: ChoiceChip(
                        label: Text(filter),
                        selected: _filter == filter,
                        onSelected: (_) => setState(() => _filter = filter),
                      ),
                    ),
                  ],
                ],
              ),
            ),
            if (_loading)
              const Padding(
                padding: EdgeInsets.all(40),
                child: Center(child: CircularProgressIndicator()),
              )
            else if (_error != null && all.isEmpty)
              Card(
                child: Padding(
                  padding: const EdgeInsets.all(20),
                  child: Column(
                    children: [
                      Text(_error!, textAlign: TextAlign.center),
                      TextButton.icon(
                        onPressed: () => _load(reset: true),
                        icon: const Icon(Icons.refresh_rounded),
                        label: const Text('Try again'),
                      ),
                    ],
                  ),
                ),
              )
            else if (visible.isEmpty)
              Padding(
                padding: const EdgeInsets.symmetric(vertical: 40),
                child: Column(
                  children: [
                    Icon(
                      Icons.assignment_turned_in_outlined,
                      size: 44,
                      color: colors.onSurfaceVariant,
                    ),
                    const SizedBox(height: 12),
                    Text(
                      all.isEmpty ? 'No tasks yet' : 'No tasks in this status',
                      style: theme.textTheme.titleMedium,
                    ),
                    const SizedBox(height: 5),
                    Text(
                      all.isEmpty
                          ? 'Reviews and ad shares you start will appear here.'
                          : 'Choose another filter to see more activity.',
                      textAlign: TextAlign.center,
                      style: theme.textTheme.bodyMedium?.copyWith(
                        color: colors.onSurfaceVariant,
                      ),
                    ),
                  ],
                ),
              )
            else
              ...visible.map((row) {
                final status = row['status'] as String;
                final color = _statusColor(colors, status);
                final date = row['date'] as DateTime;
                final canResume = row['can_resume'] == true;
                return Card(
                  margin: const EdgeInsets.only(top: 10),
                  clipBehavior: Clip.antiAlias,
                  child: InkWell(
                    onTap: canResume ? () => _resumeTask(row) : null,
                    child: Padding(
                      padding: const EdgeInsets.all(15),
                      child: Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          CircleAvatar(
                            backgroundColor: colors.primaryContainer,
                            foregroundColor: colors.onPrimaryContainer,
                            child: Icon(
                              row['kind'] == 'App review'
                                  ? Icons.rate_review_rounded
                                  : Icons.campaign_rounded,
                            ),
                          ),
                          const SizedBox(width: 13),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  row['title'] as String,
                                  maxLines: 2,
                                  overflow: TextOverflow.ellipsis,
                                  style: theme.textTheme.titleSmall?.copyWith(
                                    fontWeight: FontWeight.w700,
                                  ),
                                ),
                                const SizedBox(height: 5),
                                Text(
                                  '${row['kind']} · ${_formatDate(date)}',
                                  style: theme.textTheme.bodySmall?.copyWith(
                                    color: colors.onSurfaceVariant,
                                  ),
                                ),
                                if (canResume) ...[
                                  const SizedBox(height: 5),
                                  Text(
                                    'Tap to continue this task',
                                    style: theme.textTheme.labelSmall?.copyWith(
                                      color: colors.primary,
                                      fontWeight: FontWeight.w700,
                                    ),
                                  ),
                                ],
                                if (row['decision'] != null) ...[
                                  const SizedBox(height: 8),
                                  Text(
                                    row['decision'] as String,
                                    style: theme.textTheme.bodySmall?.copyWith(
                                      color: colors.onSurfaceVariant,
                                    ),
                                  ),
                                ],
                              ],
                            ),
                          ),
                          const SizedBox(width: 8),
                          Container(
                            padding: const EdgeInsets.symmetric(
                              horizontal: 9,
                              vertical: 6,
                            ),
                            decoration: BoxDecoration(
                              color: color.withValues(alpha: .12),
                              borderRadius: BorderRadius.circular(20),
                            ),
                            child: Text(
                              _statusLabel(status),
                              style: theme.textTheme.labelSmall?.copyWith(
                                color: color,
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                          ),
                          if (canResume) ...[
                            const SizedBox(width: 4),
                            Icon(
                              Icons.chevron_right_rounded,
                              color: colors.primary,
                            ),
                          ],
                        ],
                      ),
                    ),
                  ),
                );
              }),
            if (_loadingMore)
              const Padding(
                padding: EdgeInsets.all(16),
                child: Center(child: CircularProgressIndicator()),
              )
            else if (hasMore)
              Padding(
                padding: const EdgeInsets.only(top: 12),
                child: OutlinedButton.icon(
                  onPressed: () => _load(more: true),
                  icon: const Icon(Icons.expand_more_rounded),
                  label: const Text('Load more tasks'),
                ),
              ),
          ],
        ),
      ),
    );
  }
}
