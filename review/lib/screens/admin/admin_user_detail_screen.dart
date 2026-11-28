import 'dart:convert';
import 'dart:math' as math;

import 'package:cached_network_image/cached_network_image.dart';
import 'package:cross_file/cross_file.dart';
import 'package:flutter/material.dart';
import 'package:share_plus/share_plus.dart';

import '../../core/currency/money.dart';
import '../../services/auth_api.dart';
import 'admin_moderation_screen.dart';

class AdminUserDetailScreen extends StatefulWidget {
  const AdminUserDetailScreen({
    super.key,
    required this.api,
    required this.token,
    required this.user,
    this.adminRole,
    required this.onAdjustWallet,
    required this.onResetPassword,
    required this.onSetBan,
    required this.onDelete,
    required this.onRestore,
  });

  final AuthApi api;
  final String token;
  final Map<String, dynamic> user;

  /// Role of the signed-in administrator; impersonation is super-admin only.
  final String? adminRole;
  final Future<void> Function(Map<String, dynamic>) onAdjustWallet;
  final Future<void> Function(Map<String, dynamic>) onResetPassword;
  final Future<void> Function(Map<String, dynamic>, bool) onSetBan;
  final Future<void> Function(Map<String, dynamic>) onDelete;
  final Future<void> Function(Map<String, dynamic>) onRestore;

  /// Requests a one-time hand-off link and opens the web app as this user.
  final Future<void> Function(Map<String, dynamic>)? onImpersonate;

  @override
  State<AdminUserDetailScreen> createState() => _AdminUserDetailScreenState();
}

class _AdminUserDetailScreenState extends State<AdminUserDetailScreen>
    with SingleTickerProviderStateMixin {
  late final TabController _tabs;
  Map<String, dynamic> _detail = const {};
  bool _loading = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _tabs = TabController(length: 5, vsync: this);
    _detail = {'user': widget.user};
    _load();
  }

  @override
  void dispose() {
    _tabs.dispose();
    super.dispose();
  }

  Map<String, dynamic> _map(Object? value) => value is Map
      ? value.map<String, dynamic>((key, value) => MapEntry(key.toString(), value))
      : <String, dynamic>{};

  List<Map<String, dynamic>> _list(Object? value) => value is List
      ? value.whereType<Map>().map(_map).toList(growable: false)
      : const [];

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final response = await widget.api.get(
        'admin/users/${widget.user['id']}',
        widget.token,
      );
      final data = _map(response['data']);
      if (mounted) setState(() => _detail = data);
    } on AuthException catch (error) {
      if (mounted) setState(() => _error = error.message);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Map<String, dynamic> get _user => _map(_detail['user']).isEmpty
      ? widget.user
      : _map(_detail['user']);

  String _text(Object? value, [String fallback = '—']) =>
      value == null || value.toString().trim().isEmpty
          ? fallback
          : value.toString();

  String _date(Object? value) {
    final date = DateTime.tryParse(value?.toString() ?? '')?.toLocal();
    if (date == null) return '—';
    return '${date.year}-${date.month.toString().padLeft(2, '0')}-${date.day.toString().padLeft(2, '0')}';
  }

  int _int(Object? value) => value is num
      ? value.toInt()
      : int.tryParse(value?.toString() ?? '') ?? 0;

  bool get _deleted => _user['deleted_at'] != null;
  bool get _banned =>
      _user['is_banned'] == true || _user['is_banned'] == 1 || _user['is_banned'] == '1';

  bool get _canImpersonate =>
      widget.adminRole == 'super_admin' &&
      widget.onImpersonate != null &&
      !_deleted &&
      !_banned &&
      _user['status']?.toString() == 'active';

  Future<void> _runAction(Future<void> Function() action) async {
    await action();
    if (mounted) await _load();
  }

  Future<void> _exportReport() async {
    final user = _user;
    final rows = <List<String>>[
      ['section', 'id', 'date', 'status', 'amount', 'currency', 'summary'],
      [
        'account',
        '${user['id'] ?? ''}',
        '${user['created_at'] ?? ''}',
        '${user['status'] ?? ''}',
        '',
        '${user['preferred_currency'] ?? ''}',
        '${user['full_name'] ?? user['username'] ?? 'User'} · ${user['email'] ?? ''}',
      ],
    ];

    void addRows(
      String section,
      List<Map<String, dynamic>> values,
      String Function(Map<String, dynamic>) summary,
    ) {
      for (final row in values) {
        rows.add([
          section,
          '${row['id'] ?? ''}',
          '${row['created_at'] ?? row['requested_at'] ?? row['submitted_at'] ?? ''}',
          '${row['status'] ?? ''}',
          '${row['amount_minor'] ?? row['amount'] ?? row['reserved_reward_minor'] ?? ''}',
          '${row['currency'] ?? ''}',
          summary(row),
        ]);
      }
    }

    addRows('wallet', _list(_detail['wallets']), (row) =>
        '${row['wallet_type'] ?? ''} · ${row['balance_minor'] ?? 0} minor units');
    addRows('transaction', _list(_detail['transactions']), (row) =>
        '${row['direction'] ?? ''} · ${row['description'] ?? row['type'] ?? ''}');
    addRows('withdrawal', _list(_detail['withdrawals']), (row) =>
        '${_map(row['option'])['name'] ?? 'Withdrawal'} · fee ${row['fee_minor'] ?? 0}');
    addRows('manual_deposit', _list(_detail['manual_deposits']), (row) =>
        'Bank reference ${row['bank_reference'] ?? ''}');
    addRows('payment_attempt', _list(_detail['payment_intents']), (row) =>
        '${row['purpose'] ?? 'Payment'} · ${row['provider_reference'] ?? ''}');
    addRows('review_task', _list(_detail['review_tasks']), (row) =>
        'App review task · ${row['app_name'] ?? row['campaign_id'] ?? ''}');
    addRows('ad_share_task', _list(_detail['ad_share_tasks']), (row) =>
        'Ad share task · ${row['ad_title'] ?? row['ad_id'] ?? ''}');
    addRows('subscription', _list(_detail['subscriptions']), (row) =>
        '${_map(row['plan'])['name'] ?? 'Subscription'} · ends ${row['ends_at'] ?? ''}');
    addRows('package_addon', _list(_detail['package_addons']), (row) =>
        '${_map(row['addon'])['name'] ?? row['name'] ?? 'Package add-on'} · ${row['status'] ?? ''}');
    addRows('referral', _list(_detail['referrals']), (row) =>
        '${_map(row['referred_user'])['email'] ?? ''} · ${row['rewards_count'] ?? 0} rewards');
    addRows('social_follow_reward', _list(_detail['social_follows']), (row) =>
        '${row['platform'] ?? ''} · ${row['status'] ?? ''}');
    addRows('social_account', _list(_detail['social_accounts']), (row) =>
        '${row['platform'] ?? ''} · ${row['handle'] ?? row['username'] ?? ''}');

    final csv = '\uFEFF${rows.map((row) => row.map(_csvCell).join(',')).join('\r\n')}';
    final date = DateTime.now().toIso8601String().substring(0, 10);
    try {
      await SharePlus.instance.share(
        ShareParams(
          files: [
            XFile.fromData(
              utf8.encode(csv),
              mimeType: 'text/csv',
              name: 'user-${user['id']}-report-$date.csv',
            ),
          ],
          title: 'User account report',
        ),
      );
    } catch (_) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Could not export this user report.')),
        );
      }
    }
  }

  String _csvCell(String value) => '"${value.replaceAll('"', '""')}"';

  @override
  Widget build(BuildContext context) {
    final theme = _adminTheme(context);
    final user = _user;
    final name = _text(user['full_name'], _text(user['username'], 'User account'));
    final stats = _map(_detail['stats']);
    return Theme(
      data: theme,
      child: Scaffold(
        backgroundColor: theme.colorScheme.surface,
        appBar: AppBar(
          elevation: 0,
          backgroundColor: _Palette.indigo,
          foregroundColor: Colors.white,
          flexibleSpace: const DecoratedBox(
            decoration: BoxDecoration(gradient: _Palette.hero),
          ),
          title: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(name, maxLines: 1, overflow: TextOverflow.ellipsis),
              Text(
                'Account #${user['id']}',
                style: TextStyle(
                  color: Colors.white.withValues(alpha: .82),
                  fontSize: 11,
                ),
              ),
            ],
          ),
          actions: [
            IconButton(
              tooltip: 'Export user report',
              onPressed: _loading ? null : _exportReport,
              icon: const Icon(Icons.download_rounded, color: Colors.white),
            ),
            IconButton(
              tooltip: 'Refresh account',
              onPressed: _loading ? null : _load,
              style: IconButton.styleFrom(
                backgroundColor: Colors.white.withValues(alpha: .18),
                shape: const CircleBorder(),
              ),
              icon: const Icon(Icons.refresh_rounded, color: Colors.white),
            ),
            PopupMenuButton<String>(
              iconColor: Colors.white,
              onSelected: _handleAction,
              itemBuilder: (_) => [
                if (!_deleted)
                  const PopupMenuItem(value: 'wallet', child: Text('Adjust wallet')),
                if (!_deleted)
                  const PopupMenuItem(value: 'profile', child: Text('Edit profile details')),
                if (!_deleted)
                  const PopupMenuItem(value: 'access', child: Text('Change role or status')),
                if (!_deleted)
                  const PopupMenuItem(value: 'password', child: Text('Reset password')),
                if (!_deleted)
                  PopupMenuItem(value: 'ban', child: Text(_banned ? 'Unban account' : 'Ban account')),
                if (!_deleted)
                  const PopupMenuItem(value: 'delete', child: Text('Soft-delete account')),
                if (_deleted)
                  const PopupMenuItem(value: 'restore', child: Text('Restore account')),
                if (_canImpersonate)
                  const PopupMenuItem(
                    value: 'impersonate',
                    child: Text('Impersonate (open web app)'),
                  ),
              ],
            ),
            const SizedBox(width: 4),
          ],
          bottom: TabBar(
            controller: _tabs,
            isScrollable: true,
            indicatorColor: Colors.white,
            indicatorWeight: 3,
            dividerColor: Colors.transparent,
            labelColor: Colors.white,
            unselectedLabelColor: Colors.white.withValues(alpha: .74),
            tabs: [
              const Tab(text: 'Overview', icon: Icon(Icons.person_outline_rounded)),
              Tab(
                text: 'Tasks (${_int(stats['review_tasks']) + _int(stats['ad_share_tasks'])})',
                icon: const Icon(Icons.task_alt_rounded),
              ),
              Tab(
                text: 'Finance (${_int(stats['transactions'])})',
                icon: const Icon(Icons.account_balance_wallet_outlined),
              ),
              const Tab(text: 'Access', icon: Icon(Icons.admin_panel_settings_outlined)),
              const Tab(text: 'Reports', icon: Icon(Icons.analytics_outlined)),
            ],
          ),
        ),
        body: Column(
          children: [
            if (_loading) const LinearProgressIndicator(minHeight: 2),
            if (_error != null)
              MaterialBanner(
                content: Text(_error!),
                leading: const Icon(Icons.cloud_off_outlined),
                actions: [TextButton(onPressed: _load, child: const Text('Retry'))],
              ),
            Expanded(
              child: TabBarView(
                controller: _tabs,
                children: [
                  _overview(theme, stats),
                  _tasks(theme),
                  _finance(theme),
                  _access(theme),
                  _reports(theme, stats),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _handleAction(String action) async {
    switch (action) {
      case 'wallet':
        await _runAction(() => widget.onAdjustWallet(_user));
        return;
      case 'profile':
        await _editProfile();
        return;
      case 'access':
        await _editAccess();
        return;
      case 'password':
        await _runAction(() => widget.onResetPassword(_user));
        return;
      case 'ban':
        await _runAction(() => widget.onSetBan(_user, !_banned));
        return;
      case 'delete':
        await _runAction(() => widget.onDelete(_user));
        return;
      case 'restore':
        await _runAction(() => widget.onRestore(_user));
        return;
      case 'impersonate':
        await widget.onImpersonate?.call(_user);
        return;
    }
  }

  Future<void> _editProfile() async {
    final fullName = TextEditingController(text: _text(_user['full_name'], ''));
    final username = TextEditingController(text: _text(_user['username'], ''));
    final email = TextEditingController(text: _text(_user['email'], ''));
    final phone = TextEditingController(text: _text(_user['phone'], ''));
    final values = await showDialog<Map<String, String?>>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Edit profile details'),
        content: SingleChildScrollView(child: Column(mainAxisSize: MainAxisSize.min, children: [
          TextField(controller: fullName, decoration: const InputDecoration(labelText: 'Full name')),
          TextField(controller: username, decoration: const InputDecoration(labelText: 'Username')),
          TextField(controller: email, keyboardType: TextInputType.emailAddress, decoration: const InputDecoration(labelText: 'Email')),
          TextField(controller: phone, keyboardType: TextInputType.phone, decoration: const InputDecoration(labelText: 'Phone')),
        ])),
        actions: [
          TextButton(onPressed: () => Navigator.pop(dialogContext), child: const Text('Cancel')),
          FilledButton(onPressed: () => Navigator.pop(dialogContext, {
            'full_name': fullName.text.trim(),
            'username': username.text.trim().isEmpty ? null : username.text.trim(),
            'email': email.text.trim(),
            'phone': phone.text.trim(),
          }), child: const Text('Save')),
        ],
      ),
    );
    fullName.dispose();
    username.dispose();
    email.dispose();
    phone.dispose();
    if (values == null) return;
    try {
      await widget.api.patch('admin/users/${_user['id']}', widget.token, values);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('User profile updated.')));
      await _load();
    } on AuthException catch (error) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(error.message)));
    }
  }

  Future<void> _editAccess() async {
    var status = _text(_user['status'], 'active').toLowerCase();
    var role = _text(_user['role'], 'reviewer').toLowerCase();
    final changes = await showDialog<Map<String, dynamic>>(
      context: context,
      builder: (dialogContext) => StatefulBuilder(
        builder: (context, setDialogState) => AlertDialog(
          title: const Text('Account role and status'),
          content: Column(mainAxisSize: MainAxisSize.min, children: [
            DropdownButtonFormField<String>(
              value: const ['active', 'restricted', 'suspended', 'pending'].contains(status) ? status : 'active',
              decoration: const InputDecoration(labelText: 'Account status'),
              items: const [
                DropdownMenuItem(value: 'active', child: Text('Active')),
                DropdownMenuItem(value: 'restricted', child: Text('Restricted')),
                DropdownMenuItem(value: 'suspended', child: Text('Suspended')),
                DropdownMenuItem(value: 'pending', child: Text('Pending')),
              ],
              onChanged: (value) => setDialogState(() => status = value ?? status),
            ),
            const SizedBox(height: 12),
            DropdownButtonFormField<String>(
              value: role == 'owner' ? 'owner' : 'reviewer',
              decoration: const InputDecoration(labelText: 'Account role'),
              items: const [
                DropdownMenuItem(value: 'reviewer', child: Text('Reviewer')),
                DropdownMenuItem(value: 'owner', child: Text('Owner')),
              ],
              onChanged: (value) => setDialogState(() => role = value ?? role),
            ),
          ]),
          actions: [
            TextButton(onPressed: () => Navigator.pop(dialogContext), child: const Text('Cancel')),
            FilledButton(
              onPressed: () => Navigator.pop(dialogContext, {
                'status': status,
                'role': role,
                if (_banned) 'is_banned': false,
                if (_banned) 'ban_reason': null,
              }),
              child: const Text('Save changes'),
            ),
          ],
        ),
      ),
    );
    if (changes == null) return;
    try {
      await widget.api.patch('admin/users/${_user['id']}', widget.token, changes);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Account access updated.')));
        await _load();
      }
    } on AuthException catch (error) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(error.message)));
    }
  }

  Widget _overview(ThemeData theme, Map<String, dynamic> stats) {
    final user = _user;
    final colors = theme.colorScheme;
    final wallets = _list(_detail['wallets']);
    final name = _text(user['full_name'], _text(user['username'], 'User'));
    final avatar = _text(user['avatar'], '');
    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        Card(
          child: Padding(
            padding: const EdgeInsets.all(18),
            child: Row(children: [
              CircleAvatar(
                radius: 32,
                backgroundImage: avatar.isEmpty
                    ? null
                    : CachedNetworkImageProvider(avatar),
                child: avatar.isEmpty ? Text(name.isEmpty ? '?' : name.substring(0, 1).toUpperCase()) : null,
              ),
              const SizedBox(width: 14),
              Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text(name, style: theme.textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w800)),
                Text('@${_text(user['username'], '—')} · ${_text(user['email'])}', style: theme.textTheme.bodySmall),
                const SizedBox(height: 8),
                Wrap(spacing: 6, runSpacing: 6, children: [
                  _chip(_text(user['status'], 'unknown'), colors.primaryContainer),
                  if (_banned) _chip('Banned', colors.errorContainer),
                  if (_deleted) _chip('Deleted', colors.errorContainer),
                ]),
              ])),
            ]),
          ),
        ),
        const SizedBox(height: 10),
        _section('Account details', [
          Align(alignment: Alignment.centerRight, child: TextButton.icon(onPressed: _deleted ? null : _editProfile, icon: const Icon(Icons.edit_outlined), label: const Text('Edit profile'))),
          _line(Icons.alternate_email_rounded, 'Email', _text(user['email'])),
          _line(Icons.phone_outlined, 'Phone', _text(user['phone'])),
          _line(Icons.public_rounded, 'Country', '${_text(user['country_name'], _text(user['country_code']))}'),
          _line(Icons.currency_exchange_rounded, 'Preferred currency', _text(user['preferred_currency'])),
          _line(Icons.calendar_today_outlined, 'Joined', _date(user['created_at'])),
        ]),
        const SizedBox(height: 10),
        _section('Wallet balances', [
          if (wallets.isEmpty) const Text('No wallet records found.'),
          for (final wallet in wallets) _walletLine(theme, wallet),
          if (!_deleted)
            Align(alignment: Alignment.centerRight, child: TextButton.icon(onPressed: () => widget.onAdjustWallet(_user), icon: const Icon(Icons.tune_rounded), label: const Text('Adjust wallet'))),
        ]),
        const SizedBox(height: 10),
        _section('Account activity', [
          _line(Icons.rate_review_outlined, 'App review tasks', '${_int(stats['review_tasks'])} (${_int(stats['reviews_submitted'])} submitted)'),
          _line(Icons.campaign_outlined, 'Ad share tasks', '${_int(stats['ad_share_tasks'])} (${_int(stats['ad_shares_submitted'])} with submissions)'),
          _line(Icons.receipt_long_outlined, 'Transactions', '${_int(stats['transactions'])}'),
          _line(Icons.people_outline_rounded, 'Referrals', '${_int(stats['referrals'])}'),
        ]),
        const SizedBox(height: 12),
        _actionButtons(),
      ],
    );
  }

  Widget _reports(ThemeData theme, Map<String, dynamic> stats) {
    final reviewTasks = _list(_detail['review_tasks']).length;
    final shareTasks = _list(_detail['ad_share_tasks']).length;
    final transactions = _list(_detail['transactions']).length;
    final referrals = _list(_detail['referrals']).length;
    final withdrawals = _list(_detail['withdrawals']).length;
    final deposits = _list(_detail['manual_deposits']).length;
    final wallets = _list(_detail['wallets']);
    final user = _user;
    final status = _text(user['status'], 'active');
    final safeToAccess = !_deleted && !_banned && status == 'active';

    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        Container(
          padding: const EdgeInsets.all(17),
          decoration: const BoxDecoration(
            gradient: _Palette.hero,
            borderRadius: BorderRadius.all(Radius.circular(20)),
          ),
          child: Row(children: [
            const _ReportBrandMark(),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text(
                    'Account activity report',
                    style: TextStyle(
                      color: Colors.white,
                      fontSize: 17,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    'A snapshot of this user’s activity, access and finances.',
                    style: TextStyle(
                      color: Colors.white.withValues(alpha: .86),
                      fontSize: 12,
                      height: 1.35,
                    ),
                  ),
                ],
              ),
            ),
          ]),
        ),
        const SizedBox(height: 12),
        LayoutBuilder(builder: (context, constraints) {
          final columns = (constraints.maxWidth / 145).floor().clamp(1, 4).toInt();
          final width = (constraints.maxWidth - (columns - 1) * 10) / columns;
          final values = [
            ('Review tasks', '$reviewTasks', Icons.rate_review_outlined, _Palette.sky),
            ('Ad share tasks', '$shareTasks', Icons.campaign_outlined, _Palette.teal),
            ('Transactions', '$transactions', Icons.receipt_long_outlined, _Palette.violet),
            ('Referrals', '$referrals', Icons.people_outline_rounded, _Palette.orange),
          ];
          return Wrap(
            spacing: 10,
            runSpacing: 10,
            children: [
              for (final item in values)
                SizedBox(
                  width: width,
                  child: _DetailReportMetric(
                    label: item.$1,
                    value: item.$2,
                    icon: item.$3,
                    color: item.$4,
                  ),
                ),
            ],
          );
        }),
        const SizedBox(height: 12),
        _section('Account health', [
          _line(Icons.badge_outlined, 'Account status', status),
          _line(Icons.block_outlined, 'Ban status', _banned ? 'Banned' : 'Not banned'),
          _line(Icons.delete_outline_rounded, 'Deletion status', _deleted ? 'Soft-deleted' : 'Active record'),
          _line(Icons.mark_email_read_outlined, 'Email verification', userVerified ? 'Verified' : 'Not verified'),
          _line(Icons.phonelink_lock_outlined, 'Two-factor authentication', user['two_factor'] == true ? 'Enabled' : 'Disabled'),
          _line(Icons.calendar_month_outlined, 'Joined', _date(user['created_at'])),
        ]),
        const SizedBox(height: 12),
        _section('Financial activity', [
          _line(Icons.account_balance_wallet_outlined, 'Wallet records', '${wallets.length}'),
          _line(Icons.payments_outlined, 'Transactions', '$transactions'),
          _line(Icons.south_west_rounded, 'Withdrawals', '$withdrawals'),
          _line(Icons.north_east_rounded, 'Manual deposits', '$deposits'),
          if (wallets.isEmpty)
            Padding(
              padding: const EdgeInsets.only(top: 6),
              child: Text('No wallet balances were returned for this account.', style: theme.textTheme.bodySmall),
            )
          else
            for (final wallet in wallets)
              _walletLine(theme, wallet),
        ]),
        const SizedBox(height: 12),
        _section('Work & community', [
          _line(Icons.rate_review_outlined, 'Review tasks submitted', '${_int(stats['reviews_submitted'])} / $reviewTasks'),
          _line(Icons.campaign_outlined, 'Ad share submissions', '${_int(stats['ad_shares_submitted'])} / $shareTasks'),
          _line(Icons.people_outline_rounded, 'Referred users', '$referrals'),
          _line(Icons.gpp_good_outlined, 'Account access', safeToAccess ? 'Active and not banned' : 'Restricted or inactive'),
        ]),
        const SizedBox(height: 12),
        _section('Export this report', [
          const Text(
            'Downloadable CSV includes account details and the activity, finance and access records currently available for this user.',
          ),
          const SizedBox(height: 10),
          const _DetailPrivacyNote(),
          const SizedBox(height: 12),
          SizedBox(
            width: double.infinity,
            child: FilledButton.icon(
              onPressed: _loading ? null : _exportReport,
              icon: const Icon(Icons.download_rounded),
              label: const Text('Export account report (CSV)'),
            ),
          ),
        ]),
      ],
    );
  }

  Widget _tasks(ThemeData theme) => ListView(
    padding: const EdgeInsets.all(16),
    children: [
      SizedBox(
        width: double.infinity,
        child: FilledButton.tonalIcon(
          onPressed: () => Navigator.of(context).push<void>(
            MaterialPageRoute<void>(
              builder: (_) => AdminModerationScreen(
                api: widget.api,
                adminToken: widget.token,
                initialSearch: _text(_user['email'], ''),
              ),
            ),
          ),
          icon: const Icon(Icons.fact_check_outlined),
          label: const Text('Open moderation queues for this user'),
        ),
      ),
      const SizedBox(height: 12),
      _recordSection('App review tasks', _list(_detail['review_tasks']), (row) {
        final campaign = _map(row['campaign']);
        final app = _map(campaign['app']);
        final submission = _map(row['submission']);
        return ('${_text(app['name'], 'App review')}', 'Task ${row['id']} · ${_text(row['status'])} · Reward ${_minor(row['reserved_reward_minor'], row['reward_currency'] ?? campaign['currency'])}\n${submission.isEmpty ? 'Not submitted' : 'Submission ${_text(submission['status'])} · ${_date(submission['submitted_at'])}'}');
      }),
      const SizedBox(height: 12),
      _recordSection('Ad share tasks', _list(_detail['ad_share_tasks']), (row) {
        final ad = _map(row['ad']);
        final events = _list(row['events']);
        final status = events.isEmpty ? 'No proof submitted' : events.map((event) => _text(event['status'])).join(', ');
        return ('${_text(ad['title'], 'Ad share')}', 'Task ${row['id']} · ${_text(row['status'])} · Reward ${_minor(row['reserved_reward_minor'], ad['currency'])}\n$status');
      }),
    ],
  );

  Widget _finance(ThemeData theme) => ListView(
    padding: const EdgeInsets.all(16),
    children: [
      _recordSection('Transactions', _list(_detail['transactions']), (row) => (
        '${_text(row['description'], _text(row['type']))}',
        '${_text(row['direction'])} · ${_minorMajor(row['amount'], _map(row['meta'])['currency'])} · ${_text(row['status'])} · ${_date(row['created_at'])}\n${_text(row['reference'], '')}'
      )),
      const SizedBox(height: 12),
      _recordSection('Withdrawals', _list(_detail['withdrawals']), (row) {
        final option = _map(row['option']);
        final wallet = _map(row['wallet']);
        return ('${_text(option['name'], 'Withdrawal')}', '${_minor(row['amount_minor'], row['currency'])} · fee ${_minor(row['fee_minor'], row['currency'])} · ${_text(row['status'])}\nWallet ${_text(wallet['wallet_type'])} · ${_date(row['requested_at'])}');
      }),
      const SizedBox(height: 12),
      _recordSection('Manual deposits', _list(_detail['manual_deposits']), (row) => (
        'Deposit ${_text(row['bank_reference'], '#${row['id']}')}',
        '${_minor(row['amount_minor'], row['currency'])} · ${_text(row['status'])} · ${_date(row['submitted_at'])}\n${_map(row['payment_intent'])['provider'] ?? ''}'
      )),
      const SizedBox(height: 12),
      _recordSection('Payment attempts', _list(_detail['payment_intents']), (row) => (
        '${_text(row['purpose'], 'Payment')} · ${_text(row['provider'])}',
        '${_minor(row['amount_minor'], row['currency'])} · ${_text(row['status'])} · ${_date(row['created_at'])}\nReference ${_text(row['provider_reference'])}'
      )),
    ],
  );

  Widget _access(ThemeData theme) => ListView(
    padding: const EdgeInsets.all(16),
    children: [
      _recordSection('Subscriptions', _list(_detail['subscriptions']), (row) {
        final plan = _map(row['plan']);
        final group = _map(plan['group']);
        return ('${_text(plan['name'], 'Subscription')}', '${_text(group['name'], _text(row['product_type']))} · ${_text(row['status'])}\n${_date(row['starts_at'])} to ${_date(row['ends_at'])}');
      }),
      const SizedBox(height: 12),
      _recordSection('Package add-ons', _list(_detail['package_addons']), (row) {
        final addon = _map(row['addon']);
        return ('${_text(addon['name'], 'Package add-on')}', '${_text(row['status'])} · ${_minor(row['price_minor'], row['currency'])}\n${_date(row['starts_at'])} to ${_date(row['ends_at'])}');
      }),
      const SizedBox(height: 12),
      _recordSection('Referred users', _list(_detail['referrals']), (row) {
        final referred = _map(row['referred_user']);
        return ('${_text(referred['full_name'], _text(referred['username'], 'User'))}', '${_text(referred['email'])} · ${_text(row['status'])}\nJoined ${_date(referred['created_at'])} · ${row['rewards_count'] ?? 0} rewards');
      }),
      const SizedBox(height: 12),
      _recordSection('Social follow rewards', _list(_detail['social_follows']), (row) {
        final handle = _map(row['social_handle']);
        return ('${_text(handle['name'], _text(handle['handle_key']))} · ${_text(handle['channel_url'], '')}', '${_text(row['status'])} · ${_minor(row['reward_amount_minor'], row['currency'])} · ${_date(row['followed_at'])}');
      }),
      const SizedBox(height: 12),
      _section('Security and connected accounts', [
        _line(Icons.mark_email_read_outlined, 'Email verified', userVerified ? 'Yes' : 'No'),
        _line(Icons.lock_outline_rounded, 'Two-factor authentication', _user['two_factor'] == true ? 'Enabled' : 'Disabled'),
        _line(Icons.pin_outlined, 'Security PIN', _user['security_pin_set'] == true ? 'Set' : 'Not set'),
        for (final account in _list(_detail['social_accounts']))
          _line(Icons.alternate_email_rounded, _text(account['platform']), _text(account['handle'])),
        for (final identity in _list(_user['social_identities']))
          _line(Icons.login_rounded, '${_text(identity['provider'])} login', _text(identity['provider_user_id'])),
      ]),
      const SizedBox(height: 12),
      _section('Payout profile', [
        if (_map(_user['payout_profile']).isEmpty) const Text('No payout profile saved.'),
        if (_map(_user['payout_profile']).isNotEmpty) ...[
          _line(Icons.account_balance_outlined, 'Method', _text(_map(_map(_user['payout_profile'])['option'])['name'])),
          for (final entry in _map(_map(_user['payout_profile'])['details']).entries)
            _line(Icons.info_outline_rounded, entry.key.replaceAll('_', ' '), entry.value.toString()),
        ],
      ]),
    ],
  );

  bool get userVerified => _user['email_verified_at'] != null;

  String _minor(Object? value, Object? currency) {
    final code = _text(currency, _text(_user['wallet_currency'], 'USD'));
    final minor = value is num ? value.toDouble() : double.tryParse('$value');
    if (minor == null) return '—';
    final major = minor / math.pow(10, currencyPrecision(code));
    return formatMajorMoney(major.toDouble(), code);
  }

  String _minorMajor(Object? value, Object? currency) {
    final code = _text(currency, _text(_user['wallet_currency'], 'USD'));
    final number = double.tryParse(value?.toString() ?? '');
    return number == null ? '—' : formatMajorMoney(number, code);
  }

  Widget _actionButtons() => Wrap(
    spacing: 8,
    runSpacing: 8,
    children: [
      if (!_deleted) OutlinedButton.icon(onPressed: () => _runAction(() => widget.onAdjustWallet(_user)), icon: const Icon(Icons.account_balance_wallet_outlined), label: const Text('Adjust wallet')),
      if (!_deleted) OutlinedButton.icon(onPressed: () => _runAction(() => widget.onResetPassword(_user)), icon: const Icon(Icons.password_rounded), label: const Text('Reset password')),
      if (!_deleted) OutlinedButton.icon(onPressed: () => _runAction(() => widget.onSetBan(_user, !_banned)), icon: Icon(_banned ? Icons.lock_open_rounded : Icons.block_rounded), label: Text(_banned ? 'Unban account' : 'Ban account')),
      if (!_deleted) OutlinedButton.icon(onPressed: () => _runAction(() => widget.onDelete(_user)), icon: const Icon(Icons.delete_outline_rounded), label: const Text('Soft-delete account')),
      if (_deleted) FilledButton.icon(onPressed: () => _runAction(() => widget.onRestore(_user)), icon: const Icon(Icons.restore_rounded), label: const Text('Restore account')),
      if (_canImpersonate) FilledButton.icon(onPressed: () => widget.onImpersonate?.call(_user), icon: const Icon(Icons.open_in_new_rounded), label: const Text('Impersonate')),
    ],
  );

  Widget _walletLine(ThemeData theme, Map<String, dynamic> wallet) {
    final code = _text(wallet['currency'], _text(_user['wallet_currency'], 'USD'));
    final minor = _int(wallet['balance_minor']);
    return _line(Icons.account_balance_wallet_outlined, _text(wallet['wallet_type']).replaceAll('_', ' '), '${_minor(minor, code)} · $code');
  }

  Widget _section(String title, List<Widget> children) => Card(
    child: Padding(
      padding: const EdgeInsets.all(15),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Text(title, style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w800)),
        const SizedBox(height: 9),
        ...children,
      ]),
    ),
  );

  Widget _line(IconData icon, String label, String value) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 5),
    child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Icon(icon, size: 18, color: Theme.of(context).colorScheme.primary),
      const SizedBox(width: 9),
      SizedBox(width: 124, child: Text(label, style: Theme.of(context).textTheme.bodySmall?.copyWith(fontWeight: FontWeight.w700))),
      const SizedBox(width: 7),
      Expanded(child: SelectableText(value, style: Theme.of(context).textTheme.bodySmall)),
    ]),
  );

  Widget _recordSection(
    String title,
    List<Map<String, dynamic>> rows,
    (String, String) Function(Map<String, dynamic>) describe,
  ) => Card(
    child: Padding(
      padding: const EdgeInsets.all(14),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(children: [Expanded(child: Text(title, style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w800))), _chip('${rows.length} recent', Theme.of(context).colorScheme.secondaryContainer)]),
        const SizedBox(height: 6),
        if (rows.isEmpty) Padding(padding: const EdgeInsets.symmetric(vertical: 8), child: Text('No records found.', style: Theme.of(context).textTheme.bodySmall)),
        for (final row in rows) ...[
          const Divider(height: 15),
          Builder(builder: (_) {
            final details = describe(row);
            return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(details.$1, style: Theme.of(context).textTheme.bodyMedium?.copyWith(fontWeight: FontWeight.w700)),
              const SizedBox(height: 3),
              SelectableText(details.$2, style: Theme.of(context).textTheme.bodySmall?.copyWith(color: Theme.of(context).colorScheme.onSurfaceVariant, height: 1.4)),
            ]);
          }),
        ],
      ]),
    ),
  );

  Widget _chip(String label, Color color) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 5),
    decoration: BoxDecoration(color: color, borderRadius: BorderRadius.circular(20)),
    child: Text(label, style: Theme.of(context).textTheme.labelSmall?.copyWith(fontWeight: FontWeight.w700)),
  );
}

class _Palette {
  static const Color indigo = Color(0xFF4338CA);
  static const Color violet = Color(0xFF7C3AED);
  static const Color pink = Color(0xFFEC4899);
  static const Color green = Color(0xFF16A34A);
  static const Color teal = Color(0xFF0D9488);
  static const Color amber = Color(0xFFF59E0B);
  static const Color orange = Color(0xFFF97316);
  static const Color sky = Color(0xFF0284C7);
  static const Color rose = Color(0xFFE11D48);
  static const Color muted = Color(0xFF667085);

  static const LinearGradient hero = LinearGradient(
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
    colors: [Color(0xFF3730A3), violet, pink],
  );
  static const LinearGradient ocean = LinearGradient(
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
    colors: [sky, indigo],
  );
}

ThemeData _adminTheme(BuildContext context) {
  final base = Theme.of(context);
  final colors = ColorScheme.fromSeed(
    seedColor: _Palette.indigo,
    brightness: base.brightness,
  );
  return base.copyWith(
    colorScheme: colors,
    cardTheme: CardThemeData(
      color: colors.surfaceContainerLow,
      elevation: 0,
      margin: EdgeInsets.zero,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(19),
        side: BorderSide(color: colors.outlineVariant),
      ),
    ),
    inputDecorationTheme: InputDecorationTheme(
      filled: true,
      fillColor: colors.surface,
      contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 13),
      border: OutlineInputBorder(
        borderRadius: BorderRadius.circular(13),
        borderSide: BorderSide(color: colors.outlineVariant),
      ),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(13),
        borderSide: BorderSide(color: colors.outlineVariant),
      ),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(13),
        borderSide: BorderSide(color: colors.primary, width: 1.5),
      ),
    ),
  );
}

class _ReportBrandMark extends StatelessWidget {
  const _ReportBrandMark();

  @override
  Widget build(BuildContext context) => Container(
        width: 44,
        height: 44,
        decoration: BoxDecoration(
          color: Colors.white.withValues(alpha: .18),
          borderRadius: BorderRadius.circular(14),
          border: Border.all(color: Colors.white.withValues(alpha: .28)),
        ),
        child: const Icon(Icons.analytics_outlined, color: Colors.white),
      );
}

class _DetailReportMetric extends StatelessWidget {
  const _DetailReportMetric({
    required this.label,
    required this.value,
    required this.icon,
    required this.color,
  });
  final String label;
  final String value;
  final IconData icon;
  final Color color;

  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.all(13),
        decoration: BoxDecoration(
          color: color.withValues(alpha: .07),
          borderRadius: BorderRadius.circular(16),
          border: Border.all(color: color.withValues(alpha: .18)),
        ),
        child: Row(children: [
          Icon(icon, color: color, size: 20),
          const SizedBox(width: 10),
          Expanded(
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(
                value,
                style: TextStyle(color: color, fontSize: 18, fontWeight: FontWeight.w900),
              ),
              Text(
                label,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: Theme.of(context).textTheme.labelSmall?.copyWith(fontWeight: FontWeight.w700),
              ),
            ]),
          ),
        ]),
      );
}

class _DetailPrivacyNote extends StatelessWidget {
  const _DetailPrivacyNote();

  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.all(10),
        decoration: BoxDecoration(
          color: _Palette.amber.withValues(alpha: .1),
          borderRadius: BorderRadius.circular(12),
        ),
        child: const Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Icon(Icons.lock_outline_rounded, size: 16, color: _Palette.orange),
          SizedBox(width: 8),
          Expanded(
            child: Text(
              'Contains personal and financial details. Share only with authorized staff.',
              style: TextStyle(color: _Palette.muted, fontSize: 11, height: 1.35),
            ),
          ),
        ]),
      );
}
