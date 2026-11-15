import 'dart:async';
import 'dart:convert';

import 'package:cross_file/cross_file.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:share_plus/share_plus.dart';

import '../core/currency/money.dart';
import '../services/auth_api.dart';
import 'admin_user_detail_screen.dart';

class AdminUsersScreen extends StatefulWidget {
  const AdminUsersScreen({super.key, required this.api});
  final AuthApi api;

  @override
  State<AdminUsersScreen> createState() => _AdminUsersScreenState();
}

class _AdminUsersScreenState extends State<AdminUsersScreen> {
  final TextEditingController _email = TextEditingController(
    text: 'admin@tlstore.test',
  );
  final TextEditingController _password = TextEditingController(
    text: 'meandmyson',
  );

  final _search = TextEditingController();
  String? _token;
  String? _error;
  bool _loading = false;
  bool _passwordVisible = false;
  bool _includeDeleted = false;
  String? _statusFilter;
  int _page = 1;
  int _lastPage = 1;
  int _total = 0;
  Timer? _debounce;
  List<Map<String, dynamic>> _users = const [];

  @override
  void dispose() {
    _email.dispose();
    _password.dispose();
    _search.dispose();
    _debounce?.cancel();
    super.dispose();
  }

  Future<void> _login() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final response = await widget.api.postPublic('admin/auth/login', {
        'email': _email.text.trim(),
        'password': _password.text,
      });
      _token = response['token']?.toString();
      if (_token == null || _token!.isEmpty)
        throw const AuthException(
          'Admin login did not return an access token.',
        );
      await _load();
    } on AuthException catch (error) {
      if (mounted) setState(() => _error = error.message);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _load({int? page}) async {
    final token = _token;
    if (token == null) return;
    final requestedPage = page ?? _page;
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final query = <String, String>{
        'per_page': '30',
        'search': _search.text.trim(),
        'page': '$requestedPage',
      };
      if (_statusFilter != null) query['status'] = _statusFilter!;
      if (_includeDeleted) query['include_deleted'] = '1';
      final suffix = query.entries
          .where((entry) => entry.value.isNotEmpty)
          .map(
            (entry) =>
                '${Uri.encodeQueryComponent(entry.key)}=${Uri.encodeQueryComponent(entry.value)}',
          )
          .join('&');
      final response = await widget.api.get('admin/users?$suffix', token);
      final body = response['data'];
      final rows = body is Map ? body['data'] : body;
      if (mounted) {
        setState(
          () {
            _users = rows is List
                ? rows.whereType<Map<String, dynamic>>().toList()
                : const [];
            final pageInfo = body is Map ? body : <String, dynamic>{};
            _page = pageInfo['current_page'] is num
                ? (pageInfo['current_page'] as num).toInt()
                : requestedPage;
            _lastPage = pageInfo['last_page'] is num
                ? (pageInfo['last_page'] as num).toInt().clamp(1, 100000).toInt()
                : 1;
            _total = pageInfo['total'] is num
                ? (pageInfo['total'] as num).toInt()
                : _users.length;
          },
        );
      }
    } on AuthException catch (error) {
      if (mounted) setState(() => _error = error.message);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  void _logout() {
    _debounce?.cancel();
    _password.clear();
    setState(() {
      _token = null;
      _users = const [];
      _error = null;
      _search.clear();
      _statusFilter = null;
      _includeDeleted = false;
      _page = 1;
      _lastPage = 1;
      _total = 0;
    });
  }

  Future<void> _openReport() async {
    final token = _token;
    if (token == null) return;
    await showDialog<void>(
      context: context,
      builder: (_) => _UserReportDialog(
        api: widget.api,
        token: token,
        search: _search.text.trim(),
        status: _statusFilter,
        includeDeleted: _includeDeleted,
      ),
    );
  }

  void _searchChanged(String _) {
    _debounce?.cancel();
    setState(() {});
    _debounce = Timer(const Duration(milliseconds: 350), () => _load(page: 1));
  }

  Future<String?> _reasonDialog(String title, String description) async {
    final reason = TextEditingController();
    final okay = await showDialog<bool>(
      context: context,
      barrierDismissible: false,
      builder: (dialogContext) => AlertDialog(
        scrollable: true,
        title: Text(title),
        content: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 440),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(description),
              const SizedBox(height: 16),
              TextField(
                controller: reason,
                minLines: 2,
                maxLines: 4,
                textCapitalization: TextCapitalization.sentences,
                decoration: const InputDecoration(
                  labelText: 'Reason (required)',
                  hintText: 'Enter at least 4 characters',
                  border: OutlineInputBorder(),
                  alignLabelWithHint: true,
                ),
              ),
            ],
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(dialogContext, true),
            child: const Text('Continue'),
          ),
        ],
      ),
    );
    final value = reason.text.trim();
    reason.dispose();
    if (okay != true) return null;
    if (value.length < 4) {
      _message('Enter a reason of at least 4 characters.');
      return null;
    }
    return value;
  }

  Future<void> _setBan(Map<String, dynamic> user, bool banned) async {
    final reason = banned
        ? await _reasonDialog(
            'Ban user',
            'This blocks account access. The reason is recorded in the admin audit log.',
          )
        : null;
    if (banned && reason == null) return;
    final token = _token;
    if (token == null) return;
    try {
      await widget.api.patch('admin/users/${user['id']}', token, {
        'is_banned': banned,
        'ban_reason': reason,
      });
      await _load();
      _message(banned ? 'User banned.' : 'User unbanned.');
    } on AuthException catch (error) {
      _message(error.message);
    }
  }

  Future<void> _delete(Map<String, dynamic> user) async {
    final reason = await _reasonDialog(
      'Delete user account',
      'The user will be soft-deleted and signed out from active sessions. This can be recovered before permanent cleanup.',
    );
    if (reason == null || _token == null) return;
    try {
      await widget.api.delete(
        'admin/users/${user['id']}',
        _token!,
        body: {'reason': reason},
      );
      await _load();
      _message('User account soft-deleted.');
    } on AuthException catch (error) {
      _message(error.message);
    }
  }

  Future<void> _restore(Map<String, dynamic> user) async {
    final reason = await _reasonDialog(
      'Restore user account',
      'This restores access to a soft-deleted account and clears its banned state.',
    );
    if (reason == null || _token == null) return;
    try {
      await widget.api.post(
        'admin/users/${user['id']}/restore',
        _token!,
        {'reason': reason},
      );
      await _load();
      _message('User account restored.');
    } on AuthException catch (error) {
      _message(error.message);
    }
  }

  Future<void> _resetPassword(Map<String, dynamic> user) async {
    final reason = await _reasonDialog(
      'Reset password',
      'A temporary password will be generated and shown once. Share it with the user through a secure channel.',
    );
    if (reason == null || _token == null) return;
    try {
      final response = await widget.api.post(
        'admin/users/${user['id']}/reset-password',
        _token!,
        {'reason': reason},
      );
      final data = response['data'] is Map
          ? Map<String, dynamic>.from(response['data'] as Map)
          : <String, dynamic>{};
      final password = data['temporary_password']?.toString() ?? '';
      if (mounted)
        await showDialog<void>(
          context: context,
          builder: (dialogContext) => AlertDialog(
            scrollable: true,
            title: const Text('Temporary password'),
            content: SelectableText(
              password.isEmpty
                  ? 'Password reset. The API did not return the temporary credential.'
                  : password,
            ),
            actions: [
              if (password.isNotEmpty)
                TextButton.icon(
                  onPressed: () async {
                    await Clipboard.setData(ClipboardData(text: password));
                    if (dialogContext.mounted) Navigator.pop(dialogContext);
                    _message('Temporary password copied.');
                  },
                  icon: const Icon(Icons.copy),
                  label: const Text('Copy'),
                ),
              FilledButton(
                onPressed: () => Navigator.pop(dialogContext),
                child: const Text('Done'),
              ),
            ],
          ),
        );
    } on AuthException catch (error) {
      _message(error.message);
    }
  }

  Future<void> _adjustWallet(Map<String, dynamic> user) async {
    final wallets = user['wallets'] is List
        ? (user['wallets'] as List).whereType<Map<String, dynamic>>().toList()
        : <Map<String, dynamic>>[];
    final types = const [
      'review_group_b',
      'review_group_c',
      'share',
      'referral',
      'bonus',
      'topup_fund',
    ];
    final walletCurrency = user['wallet_currency']?.toString().toUpperCase() ??
        user['preferred_currency']?.toString().toUpperCase() ??
        'USD';
    var walletType = wallets.isEmpty
        ? 'review_group_b'
        : wallets.first['wallet_type']?.toString() ?? 'review_group_b';
    if (!types.contains(walletType)) walletType = 'review_group_b';

    final currency = walletCurrency;

    var direction = 'credit';
    final amount = TextEditingController();
    final reason = TextEditingController();

    final okay = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      showDragHandle: true,
      builder: (sheetContext) => StatefulBuilder(
        builder: (context, setSheetState) {
          final colors = Theme.of(context).colorScheme;
          return Padding(
            padding: EdgeInsets.only(
              bottom: MediaQuery.viewInsetsOf(context).bottom,
            ),
            child: ConstrainedBox(
              constraints: BoxConstraints(
                maxHeight: MediaQuery.sizeOf(context).height * .86,
              ),
              child: SingleChildScrollView(
                padding: const EdgeInsets.fromLTRB(20, 8, 20, 24),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Text(
                      'Adjust wallet',
                      style: Theme.of(context).textTheme.titleLarge?.copyWith(
                            fontWeight: FontWeight.w800,
                          ),
                    ),
                    const SizedBox(height: 6),
                    Text(
                      'Add funds or deduct funds from this user’s wallet.',
                      style: Theme.of(context).textTheme.bodySmall?.copyWith(
                            color: colors.onSurfaceVariant,
                          ),
                    ),
                    const SizedBox(height: 18),
                    Container(
                      width: double.infinity,
                      padding: const EdgeInsets.all(13),
                      decoration: BoxDecoration(
                        color: colors.primaryContainer,
                        borderRadius: BorderRadius.circular(14),
                      ),
                      child: Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Icon(Icons.currency_exchange_rounded, color: colors.onPrimaryContainer),
                          const SizedBox(width: 10),
                          Expanded(
                            child: Text(
                              'User wallet currency: $currency. All wallet credits and debits for this account use this currency.',
                              style: Theme.of(context).textTheme.bodySmall?.copyWith(
                                    color: colors.onPrimaryContainer,
                                    fontWeight: FontWeight.w700,
                                  ),
                            ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 14),
                    DropdownButtonFormField<String>(
                      value: walletType,
                      isExpanded: true,
                      decoration: const InputDecoration(
                        labelText: 'Wallet',
                        prefixIcon: Icon(Icons.account_balance_wallet_outlined),
                      ),
                      items: types.map((value) {
                        return DropdownMenuItem(
                          value: value,
                          child: Text(_walletLabel(value)),
                        );
                      }).toList(),
                      onChanged: (value) {
                        if (value != null) {
                          setSheetState(() {
                            walletType = value;
                          });
                        }
                      },
                    ),
                    const SizedBox(height: 14),
                    SegmentedButton<String>(
                      segments: const [
                        ButtonSegment(
                          value: 'credit',
                          label: Text('Credit'),
                          icon: Icon(Icons.add_rounded),
                        ),
                        ButtonSegment(
                          value: 'debit',
                          label: Text('Debit'),
                          icon: Icon(Icons.remove_rounded),
                        ),
                      ],
                      selected: {direction},
                      onSelectionChanged: (values) {
                        setSheetState(() => direction = values.first);
                      },
                    ),
                    const SizedBox(height: 14),
                    TextField(
                      controller: amount,
                      keyboardType: const TextInputType.numberWithOptions(
                        decimal: true,
                      ),
                      decoration: InputDecoration(
                        labelText: 'Amount ($currency)',
                        prefixIcon: const Icon(Icons.payments_outlined),
                        helperText: 'Enter major units, e.g. 12.50',
                      ),
                    ),
                    const SizedBox(height: 12),
                    TextField(
                      controller: reason,
                      minLines: 2,
                      maxLines: 4,
                      textCapitalization: TextCapitalization.sentences,
                      decoration: const InputDecoration(
                        labelText: 'Reason (required)',
                        alignLabelWithHint: true,
                        border: OutlineInputBorder(),
                      ),
                    ),
                    const SizedBox(height: 18),
                    FilledButton.icon(
                      onPressed: () => Navigator.pop(sheetContext, true),
                      icon: const Icon(Icons.check_rounded),
                      label: const Text('Continue'),
                    ),
                  ],
                ),
              ),
            ),
          );
        },
      ),
    );

    if (okay != true || _token == null) {
      amount.dispose();
      reason.dispose();
      return;
    }

    final major = double.tryParse(amount.text.trim());
    final note = reason.text.trim();
    amount.dispose();
    reason.dispose();

    if (major == null || major <= 0 || note.length < 4) {
      _message('Enter a valid amount and reason.');
      return;
    }

    final factor = _power10(currencyPrecision(currency));
    final minor = (major * factor).round();

    try {
      await widget.api.post(
        'admin/users/${user['id']}/wallet-adjustments',
        _token!,
        {
          'wallet_type': walletType,
          'currency': currency,
          'direction': direction,
          'amount_minor': minor,
          'reason': note,
          'idempotency_key':
              'flutter-admin-${user['id']}-${DateTime.now().microsecondsSinceEpoch}',
        },
      );
      await _load();
      _message('Wallet $direction completed.');
    } on AuthException catch (error) {
      _message(error.message);
    }
  }

  int _power10(int digits) => digits == 0
      ? 1
      : digits == 3
      ? 1000
      : 100;
  String _walletLabel(String value) => switch (value) {
    'review' => 'Legacy review wallet',
    'review_group_b' => 'Play Store & Galaxy review wallet',
    'review_group_c' => 'Apple Store review wallet',
    'share' => 'Share wallet',
    'referral' => 'Referral wallet',
    'bonus' => 'Bonus wallet',
    _ => 'Top-up funds',
  };
  void _message(String message) {
    if (mounted)
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(message)));
  }

  @override
  Widget build(BuildContext context) {
    final theme = _adminTheme(context);
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
          title: const Text(
            'User management',
            style: TextStyle(fontWeight: FontWeight.w800, letterSpacing: -.3),
          ),
          actions: [
            if (_token != null) ...[
              _AppBarAction(
                icon: Icons.analytics_outlined,
                tooltip: 'User reports',
                onPressed: _loading ? null : _openReport,
              ),
              _AppBarAction(
                icon: Icons.refresh_rounded,
                tooltip: 'Refresh users',
                onPressed: _loading ? null : _load,
              ),
              _AppBarAction(
                icon: Icons.logout_rounded,
                tooltip: 'Sign out',
                onPressed: _logout,
              ),
            ],
            const SizedBox(width: 8),
          ],
        ),
        body: _token == null ? _loginForm() : _usersPage(),
      ),
    );
  }

  Widget _usersPage() {
    final colors = Theme.of(context).colorScheme;
    const filters = <(String?, String, Color)>[
      (null, 'All', _Palette.indigo),
      ('active', 'Active', _Palette.green),
      ('restricted', 'Restricted', _Palette.amber),
      ('suspended', 'Suspended', _Palette.orange),
      ('banned', 'Banned', _Palette.rose),
      ('pending', 'Pending', _Palette.sky),
    ];
    final bannedOnPage = _users.where((user) => _truthy(user['is_banned'])).length;
    final verifiedOnPage = _users.where((user) =>
        user['email_verified'] == true || user['email_verified_at'] != null).length;

    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 16, 16, 8),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              _UserSummary(
                total: _total,
                onPage: _users.length,
                banned: bannedOnPage,
                verified: verifiedOnPage,
              ),
              const SizedBox(height: 12),
              _SearchField(
                controller: _search,
                hint: 'Search name, username or email',
                onChanged: _searchChanged,
                onClear: () {
                  _debounce?.cancel();
                  _load(page: 1);
                },
              ),
              const SizedBox(height: 10),
              Wrap(
                spacing: 7,
                runSpacing: 7,
                children: [
                  for (final filter in filters)
                    _StatusFilterChip(
                      label: filter.$2,
                      color: filter.$3,
                      selected: _statusFilter == filter.$1,
                      onSelected: () {
                        setState(() => _statusFilter = filter.$1);
                        _load(page: 1);
                      },
                    ),
                ],
              ),
              const SizedBox(height: 8),
              Container(
                padding: const EdgeInsets.fromLTRB(13, 8, 10, 8),
                decoration: BoxDecoration(
                  color: colors.surfaceContainerLow,
                  borderRadius: BorderRadius.circular(15),
                  border: Border.all(color: colors.outlineVariant),
                ),
                child: Row(
                  children: [
                    Icon(Icons.delete_sweep_outlined, color: colors.onSurfaceVariant),
                    const SizedBox(width: 9),
                    Expanded(
                      child: Text(
                        'Include soft-deleted accounts',
                        style: Theme.of(context).textTheme.bodySmall?.copyWith(
                              fontWeight: FontWeight.w700,
                            ),
                      ),
                    ),
                    Switch.adaptive(
                      value: _includeDeleted,
                      onChanged: (value) {
                        setState(() => _includeDeleted = value);
                        _load(page: 1);
                      },
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
        if (_error != null)
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 0, 16, 8),
            child: _ErrorBanner(message: _error!, onRetry: _load),
          ),
        Expanded(
          child: _loading
              ? const _SkeletonList()
              : _users.isEmpty
                  ? _emptyUsers()
                  : RefreshIndicator(
                      color: Colors.white,
                      backgroundColor: _Palette.indigo,
                      onRefresh: _load,
                      child: ListView.separated(
                        physics: const AlwaysScrollableScrollPhysics(),
                        padding: const EdgeInsets.fromLTRB(16, 8, 16, 20),
                        itemCount: _users.length,
                        separatorBuilder: (_, _) =>
                            const SizedBox(height: 12),
                        itemBuilder: (context, index) =>
                            _userCard(_users[index]),
                      ),
                    ),
        ),
        if (_total > 0)
          SafeArea(
            top: false,
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 5),
              decoration: BoxDecoration(
                color: colors.surface,
                border: Border(top: BorderSide(color: colors.outlineVariant)),
              ),
              child: Row(children: [
                Text(
                  'Page $_page of $_lastPage · $_total users',
                  style: Theme.of(context).textTheme.labelMedium?.copyWith(
                        color: _Palette.muted,
                        fontWeight: FontWeight.w700,
                      ),
                ),
                const Spacer(),
                IconButton(
                  tooltip: 'Previous page',
                  onPressed: _page > 1 && !_loading
                      ? () => _load(page: _page - 1)
                      : null,
                  icon: const Icon(Icons.chevron_left_rounded),
                ),
                IconButton(
                  tooltip: 'Next page',
                  onPressed: _page < _lastPage && !_loading
                      ? () => _load(page: _page + 1)
                      : null,
                  icon: const Icon(Icons.chevron_right_rounded),
                ),
              ]),
            ),
          ),
      ],
    );
  }

  Widget _emptyUsers() => _EmptyState(
        icon: Icons.person_search_rounded,
        title: 'No users found',
        message: 'Try another name, username, email or status filter.',
        actionLabel: 'Clear filters',
        onAction: () {
          _debounce?.cancel();
          _search.clear();
          setState(() => _statusFilter = null);
          _load(page: 1);
        },
      );

  bool _truthy(Object? value) => value == true || value == 1 || value == '1';

  Future<void> _openUser(Map<String, dynamic> user) async {
    final token = _token;
    if (token == null) return;
    await Navigator.of(context).push<void>(
      MaterialPageRoute<void>(
        builder: (_) => AdminUserDetailScreen(
          api: widget.api,
          token: token,
          user: user,
          onAdjustWallet: _adjustWallet,
          onResetPassword: _resetPassword,
          onSetBan: _setBan,
          onDelete: _delete,
          onRestore: _restore,
        ),
      ),
    );
    if (mounted) await _load();
  }

  Widget _userCard(Map<String, dynamic> user) {
    final theme = Theme.of(context);
    final colors = theme.colorScheme;

    final wallets = user['wallets'] is List
        ? (user['wallets'] as List)
            .whereType<Map<String, dynamic>>()
            .toList()
        : <Map<String, dynamic>>[];

    final deleted = user['deleted_at'] != null;
    final banned = _truthy(user['is_banned']);
    final currency = user['preferred_currency']?.toString() ?? 'USD';

    final fullName = user['full_name']?.toString().trim() ?? '';
    final username = user['username']?.toString().trim() ?? '';
    final email = user['email']?.toString() ?? '';
    final displayName = fullName.isNotEmpty
        ? fullName
        : username.isNotEmpty
            ? username
            : 'User';

    final initial = displayName.isNotEmpty
        ? displayName.substring(0, 1).toUpperCase()
        : '?';

    return Card(
      elevation: 0,
      color: colors.surfaceContainerLow,
      margin: EdgeInsets.zero,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(20),
        side: BorderSide(color: colors.outlineVariant),
      ),
      clipBehavior: Clip.antiAlias,
      child: ExpansionTile(
        tilePadding: const EdgeInsets.fromLTRB(14, 8, 14, 8),
        childrenPadding: const EdgeInsets.fromLTRB(14, 0, 14, 16),
        shape: const Border(),
        collapsedShape: const Border(),
        leading: _UserAvatar(
          initial: initial,
          gradient: deleted
              ? _Palette.sunset
              : banned
                  ? _Palette.berry
                  : _Palette.ocean,
        ),
        title: Text(
          displayName,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: theme.textTheme.titleSmall?.copyWith(
            fontWeight: FontWeight.w800,
          ),
        ),
        subtitle: Padding(
          padding: const EdgeInsets.only(top: 4),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              if (email.isNotEmpty)
                Text(
                  email,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: theme.textTheme.bodySmall?.copyWith(
                    color: colors.onSurfaceVariant,
                  ),
                ),
              const SizedBox(height: 6),
              Wrap(
                spacing: 6,
                runSpacing: 5,
                children: [
                  _userStatusChip(
                    (user['status'] ?? 'unknown').toString(),
                    colors.onSurfaceVariant,
                    colors.surfaceContainerHighest,
                  ),
                  if (banned)
                    _userStatusChip('Banned', colors.error, colors.errorContainer),
                  if (deleted)
                    _userStatusChip(
                      'Deleted',
                      colors.onErrorContainer,
                      colors.errorContainer,
                    ),
                ],
              ),
            ],
          ),
        ),
        children: [
          Align(
            alignment: Alignment.centerLeft,
            child: Wrap(
              spacing: 14,
              runSpacing: 6,
              children: [
                _detailText('@${username.isNotEmpty ? username : '—'}'),
                _detailText('Country: ${user['country_code'] ?? '—'}'),
                _detailText('Currency: $currency'),
              ],
            ),
          ),
          const SizedBox(height: 14),
          Row(
            children: [
              Icon(
                Icons.account_balance_wallet_outlined,
                size: 17,
                color: colors.primary,
              ),
              const SizedBox(width: 7),
              Text(
                'Wallet balances',
                style: theme.textTheme.titleSmall?.copyWith(
                  fontWeight: FontWeight.w800,
                ),
              ),
              const Spacer(),
              Text(
                '${wallets.length} wallet${wallets.length == 1 ? '' : 's'}',
                style: theme.textTheme.labelSmall?.copyWith(
                  color: colors.onSurfaceVariant,
                ),
              ),
            ],
          ),
          const SizedBox(height: 8),
          if (wallets.isEmpty)
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: colors.surfaceContainerHighest,
                borderRadius: BorderRadius.circular(12),
              ),
              child: Text(
                'No wallet records available.',
                style: theme.textTheme.bodySmall?.copyWith(
                  color: colors.onSurfaceVariant,
                ),
              ),
            )
          else
            ...wallets.map((wallet) {
              final walletCurrency =
                  wallet['currency']?.toString() ?? currency;
              final balanceMinor =
                  (wallet['balance_minor'] as num?)?.toDouble() ?? 0;
              final balance = balanceMinor /
                  _power10(currencyPrecision(walletCurrency));

              return Container(
                margin: const EdgeInsets.only(bottom: 7),
                padding: const EdgeInsets.symmetric(
                  horizontal: 12,
                  vertical: 11,
                ),
                decoration: BoxDecoration(
                  color: colors.surfaceContainerHighest,
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Row(
                  children: [
                    Expanded(
                      child: Text(
                        '${_walletLabel(wallet['wallet_type']?.toString() ?? '')} · $walletCurrency',
                        style: theme.textTheme.bodySmall?.copyWith(
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    ),
                    const SizedBox(width: 8),
                    Flexible(
                      child: Text(
                        formatMajorMoney(balance, walletCurrency),
                        textAlign: TextAlign.end,
                        style: theme.textTheme.bodyMedium?.copyWith(
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                    ),
                  ],
                ),
              );
            }),
          const SizedBox(height: 12),
          SizedBox(
            width: double.infinity,
            child: FilledButton.tonalIcon(
              onPressed: () => _openUser(user),
              icon: const Icon(Icons.open_in_new_rounded),
              label: const Text('View full account and activity'),
            ),
          ),
          const SizedBox(height: 12),
          Text(
            'Account actions',
            style: theme.textTheme.titleSmall?.copyWith(
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 10),
          LayoutBuilder(
            builder: (context, constraints) {
              final compact = constraints.maxWidth < 390;
              final width = compact
                  ? constraints.maxWidth
                  : (constraints.maxWidth - 8) / 2;

              return Wrap(
                spacing: 8,
                runSpacing: 8,
                children: [
                  SizedBox(
                    width: width,
                    child: OutlinedButton.icon(
                      onPressed: deleted ? null : () => _adjustWallet(user),
                      icon: const Icon(Icons.account_balance_wallet_outlined),
                      label: const Text('Adjust wallet'),
                    ),
                  ),
                  SizedBox(
                    width: width,
                    child: OutlinedButton.icon(
                      onPressed: deleted ? null : () => _resetPassword(user),
                      icon: const Icon(Icons.password_rounded),
                      label: const Text('Reset password'),
                    ),
                  ),
                  SizedBox(
                    width: width,
                    child: OutlinedButton.icon(
                      onPressed: deleted ? null : () => _setBan(user, !banned),
                      icon: Icon(
                        banned
                            ? Icons.lock_open_rounded
                            : Icons.block_rounded,
                      ),
                      label: Text(banned ? 'Unban user' : 'Ban user'),
                    ),
                  ),
                  SizedBox(
                    width: width,
                    child: OutlinedButton.icon(
                      onPressed: deleted ? null : () => _delete(user),
                      icon: const Icon(Icons.delete_outline_rounded),
                      label: const Text('Delete user'),
                    ),
                  ),
                ],
              );
            },
          ),
        ],
      ),
    );
  }

  Widget _userStatusChip(String label, Color foreground, Color background) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      decoration: BoxDecoration(
        color: background,
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: foreground.withValues(alpha: .14)),
      ),
      child: Text(
        label,
        style: Theme.of(context).textTheme.labelSmall?.copyWith(
              color: foreground,
              fontWeight: FontWeight.w700,
            ),
      ),
    );
  }

  Widget _detailText(String value) {
    return Text(
      value,
      style: Theme.of(context).textTheme.bodySmall?.copyWith(
            color: Theme.of(context).colorScheme.onSurfaceVariant,
          ),
    );
  }

  Widget _loginForm() => DecoratedBox(
        decoration: const BoxDecoration(gradient: _Palette.hero),
        child: SafeArea(
          child: Center(
            child: SingleChildScrollView(
              padding: const EdgeInsets.all(24),
              child: ConstrainedBox(
                constraints: const BoxConstraints(maxWidth: 440),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    const _BrandMark(
                      icon: Icons.people_alt_rounded,
                      gradient: _Palette.ocean,
                      size: 76,
                    ),
                    const SizedBox(height: 18),
                    const Text(
                      'Users console',
                      textAlign: TextAlign.center,
                      style: TextStyle(
                        color: Colors.white,
                        fontSize: 26,
                        fontWeight: FontWeight.w800,
                        letterSpacing: -.4,
                      ),
                    ),
                    const SizedBox(height: 6),
                    Text(
                      'Review accounts, activity and wallet health from one secure workspace.',
                      textAlign: TextAlign.center,
                      style: TextStyle(
                        color: Colors.white.withValues(alpha: .86),
                        height: 1.4,
                      ),
                    ),
                    const SizedBox(height: 24),
                    Container(
                      padding: const EdgeInsets.all(20),
                      decoration: BoxDecoration(
                        color: Theme.of(context).colorScheme.surface,
                        borderRadius: BorderRadius.circular(26),
                        boxShadow: [
                          BoxShadow(
                            color: Colors.black.withValues(alpha: .22),
                            blurRadius: 30,
                            offset: const Offset(0, 18),
                          ),
                        ],
                      ),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          _SignInField(
                            controller: _email,
                            label: 'Admin email',
                            icon: Icons.alternate_email_rounded,
                            keyboard: TextInputType.emailAddress,
                          ),
                          const SizedBox(height: 12),
                          _SignInField(
                            controller: _password,
                            label: 'Password',
                            icon: Icons.lock_outline_rounded,
                            obscure: !_passwordVisible,
                            onSubmitted: (_) {
                              if (!_loading) _login();
                            },
                            suffix: IconButton(
                              tooltip: _passwordVisible
                                  ? 'Hide password'
                                  : 'Show password',
                              onPressed: () => setState(
                                () => _passwordVisible = !_passwordVisible,
                              ),
                              icon: Icon(
                                _passwordVisible
                                    ? Icons.visibility_off_outlined
                                    : Icons.visibility_outlined,
                              ),
                            ),
                          ),
                          if (_error != null) ...[
                            const SizedBox(height: 14),
                            _ErrorBanner(message: _error!, onRetry: null),
                          ],
                          const SizedBox(height: 18),
                          _GradientButton(
                            label: _loading ? 'Signing in…' : 'Sign in',
                            icon: _loading ? null : Icons.arrow_forward_rounded,
                            busy: _loading,
                            onPressed: _loading ? null : _login,
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      );

}

class _UserReportDialog extends StatefulWidget {
  const _UserReportDialog({
    required this.api,
    required this.token,
    required this.search,
    required this.status,
    required this.includeDeleted,
  });

  final AuthApi api;
  final String token;
  final String search;
  final String? status;
  final bool includeDeleted;

  @override
  State<_UserReportDialog> createState() => _UserReportDialogState();
}

class _UserReportDialogState extends State<_UserReportDialog> {
  List<Map<String, dynamic>> _allUsers = const [];
  bool _loading = true;
  bool _exporting = false;
  bool _truncated = false;
  int _days = 30;
  String? _error;

  Map<String, dynamic> _map(Object? value) => value is Map
      ? value.map<String, dynamic>(
          (key, value) => MapEntry(key.toString(), value),
        )
      : <String, dynamic>{};

  int _int(Object? value, [int fallback = 0]) => value is num
      ? value.toInt()
      : int.tryParse(value?.toString() ?? '') ?? fallback;

  bool _truthy(Object? value) => value == true || value == 1 || value == '1';

  @override
  void initState() {
    super.initState();
    _loadReportUsers();
  }

  Future<void> _loadReportUsers() async {
    if (mounted) setState(() { _loading = true; _error = null; });
    final output = <Map<String, dynamic>>[];
    const pageSize = 100;
    var page = 1;
    var lastPage = 1;
    try {
      while (page <= 100) {
        final query = <String, String>{
          'per_page': '$pageSize',
          'page': '$page',
        };
        if (widget.search.isNotEmpty) query['search'] = widget.search;
        if (widget.status != null) query['status'] = widget.status!;
        if (widget.includeDeleted) query['include_deleted'] = '1';
        final suffix = query.entries
            .map((entry) =>
                '${Uri.encodeQueryComponent(entry.key)}=${Uri.encodeQueryComponent(entry.value)}')
            .join('&');
        final response = await widget.api.get('admin/users?$suffix', widget.token);
        final body = _map(response)['data'];
        final envelope = _map(body);
        final rows = body is List ? body : envelope['data'];
        if (rows is List) output.addAll(rows.whereType<Map>().map(_map));
        final meta = _map(_map(response)['meta']);
        lastPage = _int(
          envelope['last_page'] ?? meta['last_page'],
          page,
        ).clamp(1, 100000).toInt();
        final currentPage = _int(
          envelope['current_page'] ?? meta['current_page'],
          page,
        );
        if (currentPage >= lastPage || rows is! List || rows.isEmpty) break;
        page = currentPage + 1;
      }
      if (!mounted) return;
      setState(() {
        _allUsers = output;
        _truncated = lastPage > 100;
        _loading = false;
      });
    } on AuthException catch (error) {
      if (mounted) setState(() { _error = error.message; _loading = false; });
    } catch (_) {
      if (mounted) setState(() {
        _error = 'The user report could not be loaded. Please try again.';
        _loading = false;
      });
    }
  }

  List<Map<String, dynamic>> get _usersInPeriod {
    if (_days == 0) return _allUsers;
    final cutoff = DateTime.now().subtract(Duration(days: _days));
    return _allUsers.where((user) {
      final created = DateTime.tryParse(user['created_at']?.toString() ?? '')
          ?.toLocal();
      return created != null && !created.isBefore(cutoff);
    }).toList(growable: false);
  }

  String get _periodLabel => switch (_days) {
        0 => 'All time',
        7 => 'Last 7 days',
        30 => 'Last 30 days',
        90 => 'Last 90 days',
        _ => 'Last 365 days',
      };

  Future<void> _exportCsv() async {
    final users = _usersInPeriod;
    if (users.isEmpty) return;
    setState(() { _exporting = true; _error = null; });
    try {
      final rows = <List<String>>[
        [
          'user_id',
          'full_name',
          'username',
          'email',
          'status',
          'role',
          'is_banned',
          'is_deleted',
          'ban_reason',
          'email_verified',
          'country_code',
          'preferred_currency',
          'created_at',
          'wallet_balances',
        ],
      ];
      for (final user in users) {
        final wallets = user['wallets'] is List
            ? (user['wallets'] as List).whereType<Map>().map(_map)
            : const <Map<String, dynamic>>[];
        final walletSummary = wallets.map((wallet) {
          final currency = wallet['currency']?.toString() ??
              user['wallet_currency']?.toString() ??
              user['preferred_currency']?.toString() ??
              'USD';
          final minor = wallet['balance_minor'] is num
              ? (wallet['balance_minor'] as num).toDouble()
              : double.tryParse('${wallet['balance_minor']}') ?? 0;
          final major = minor / _power10(currencyPrecision(currency));
          return '${wallet['wallet_type'] ?? 'wallet'}: ${formatMajorMoney(major, currency)}';
        }).join(' | ');
        rows.add([
          '${user['id'] ?? ''}',
          '${user['full_name'] ?? ''}',
          '${user['username'] ?? ''}',
          '${user['email'] ?? ''}',
          '${user['status'] ?? ''}',
          '${user['role'] ?? ''}',
          _truthy(user['is_banned']).toString(),
          (user['deleted_at'] != null).toString(),
          '${user['ban_reason'] ?? ''}',
          (user['email_verified'] == true || user['email_verified_at'] != null)
              .toString(),
          '${user['country_code'] ?? ''}',
          '${user['preferred_currency'] ?? ''}',
          '${user['created_at'] ?? ''}',
          walletSummary,
        ]);
      }
      final csv = '\uFEFF${rows.map((row) => row.map(_csvCell).join(',')).join('\r\n')}';
      final date = DateTime.now().toIso8601String().substring(0, 10);
      await SharePlus.instance.share(
        ShareParams(
          files: [
            XFile.fromData(
              utf8.encode(csv),
              mimeType: 'text/csv',
              name: 'users-report-$date.csv',
            ),
          ],
          title: 'User account report',
        ),
      );
    } catch (_) {
      if (mounted) setState(() => _error = 'Could not export the report.');
    } finally {
      if (mounted) setState(() => _exporting = false);
    }
  }

  String _csvCell(String value) => '"${value.replaceAll('"', '""')}"';

  int _power10(int digits) => digits == 0 ? 1 : digits == 3 ? 1000 : 100;

  @override
  Widget build(BuildContext context) {
    final users = _usersInPeriod;
    final active = users.where((user) =>
        (user['status']?.toString().toLowerCase() ?? '') == 'active' &&
        !_truthy(user['is_banned']) && user['deleted_at'] == null).length;
    final banned = users.where((user) => _truthy(user['is_banned'])).length;
    final verified = users.where((user) =>
        user['email_verified'] == true || user['email_verified_at'] != null).length;
    final statusCounts = <String, int>{};
    for (final user in users) {
      final status = user['deleted_at'] != null
          ? 'deleted'
          : _truthy(user['is_banned'])
              ? 'banned'
              : (user['status']?.toString().trim().toLowerCase().isNotEmpty ?? false)
                  ? user['status'].toString().trim().toLowerCase()
                  : 'unknown';
      statusCounts.update(status, (count) => count + 1, ifAbsent: () => 1);
    }
    final theme = Theme.of(context);

    return Dialog(
      insetPadding: const EdgeInsets.all(16),
      clipBehavior: Clip.antiAlias,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(24)),
      child: ConstrainedBox(
        constraints: const BoxConstraints(maxWidth: 920, maxHeight: 850),
        child: Column(
          children: [
            Container(
              padding: const EdgeInsets.fromLTRB(20, 16, 12, 16),
              decoration: const BoxDecoration(gradient: _Palette.hero),
              child: Row(children: [
                const _BrandMark(
                  icon: Icons.analytics_outlined,
                  gradient: _Palette.ocean,
                  size: 44,
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text(
                        'User reports',
                        style: TextStyle(
                          color: Colors.white,
                          fontWeight: FontWeight.w900,
                          fontSize: 19,
                        ),
                      ),
                      Text(
                        'A complete export of the matching user accounts.',
                        style: TextStyle(
                          color: Colors.white.withValues(alpha: .85),
                          fontSize: 12,
                        ),
                      ),
                    ],
                  ),
                ),
                IconButton(
                  tooltip: 'Close report',
                  onPressed: () => Navigator.pop(context),
                  icon: const Icon(Icons.close_rounded, color: Colors.white),
                ),
              ]),
            ),
            Expanded(
              child: _loading
                  ? const _SkeletonList()
                  : _error != null && _allUsers.isEmpty
                      ? _EmptyState(
                          icon: Icons.cloud_off_rounded,
                          title: 'Report unavailable',
                          message: _error!,
                          actionLabel: 'Retry',
                          onAction: _loadReportUsers,
                        )
                      : ListView(
                          padding: const EdgeInsets.all(18),
                          children: [
                            Row(
                              children: [
                                Expanded(
                                  child: Text(
                                    'Report period',
                                    style: theme.textTheme.titleSmall?.copyWith(
                                      fontWeight: FontWeight.w900,
                                    ),
                                  ),
                                ),
                                SizedBox(
                                  width: 190,
                                  child: DropdownButtonFormField<int>(
                                    value: _days,
                                    isExpanded: true,
                                    decoration: const InputDecoration(
                                      isDense: true,
                                      border: OutlineInputBorder(),
                                    ),
                                    items: const [
                                      DropdownMenuItem(value: 7, child: Text('Last 7 days')),
                                      DropdownMenuItem(value: 30, child: Text('Last 30 days')),
                                      DropdownMenuItem(value: 90, child: Text('Last 90 days')),
                                      DropdownMenuItem(value: 365, child: Text('Last year')),
                                      DropdownMenuItem(value: 0, child: Text('All time')),
                                    ],
                                    onChanged: (value) => setState(() => _days = value ?? 30),
                                  ),
                                ),
                              ],
                            ),
                            const SizedBox(height: 6),
                            Text(
                              'Search: ${widget.search.isEmpty ? 'All users' : widget.search} · Status: ${widget.status ?? 'All'} · Deleted accounts: ${widget.includeDeleted ? 'included' : 'excluded'}',
                              style: theme.textTheme.bodySmall?.copyWith(
                                color: _Palette.muted,
                              ),
                            ),
                            const SizedBox(height: 14),
                            LayoutBuilder(builder: (context, constraints) {
                              final columns = (constraints.maxWidth / 155)
                                  .floor()
                                  .clamp(1, 4)
                                  .toInt();
                              final width = (constraints.maxWidth - (columns - 1) * 10) / columns;
                              final stats = [
                                ('Users in period', '${users.length}', Icons.people_alt_outlined, _Palette.indigo),
                                ('Active', '$active', Icons.verified_user_outlined, _Palette.green),
                                ('Banned', '$banned', Icons.block_outlined, _Palette.rose),
                                ('Email verified', '$verified', Icons.mark_email_read_outlined, _Palette.sky),
                              ];
                              return Wrap(
                                spacing: 10,
                                runSpacing: 10,
                                children: [
                                  for (final stat in stats)
                                    SizedBox(
                                      width: width,
                                      child: _ReportMetric(
                                        label: stat.$1,
                                        value: stat.$2,
                                        icon: stat.$3,
                                        color: stat.$4,
                                      ),
                                    ),
                                ],
                              );
                            }),
                            const SizedBox(height: 16),
                            _SectionCard(
                              title: 'Account status breakdown',
                              subtitle: 'Counts reflect the selected period and current list filters.',
                              child: Wrap(
                                spacing: 8,
                                runSpacing: 8,
                                children: [
                                  for (final entry in statusCounts.entries)
                                    _ReportStatusCount(label: entry.key, count: entry.value),
                                ],
                              ),
                            ),
                            const SizedBox(height: 12),
                            _SectionCard(
                              title: 'Report contents',
                              subtitle: 'CSV includes account identity, state, verification, country, currency, join date and wallet balances.',
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text('Period: $_periodLabel'),
                                  Text('Matching records: ${users.length}'),
                                  if (_truncated)
                                    const Text('The export is capped at 10,000 users. Narrow your search for a complete report.'),
                                  const SizedBox(height: 8),
                                  const _PrivacyNote(),
                                ],
                              ),
                            ),
                            if (_error != null) ...[
                              const SizedBox(height: 10),
                              _ErrorBanner(message: _error!),
                            ],
                            const SizedBox(height: 12),
                            if (users.isNotEmpty) ...[
                              Text('Newest matching users', style: theme.textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w900)),
                              const SizedBox(height: 6),
                              for (final user in users.take(5))
                                ListTile(
                                  contentPadding: EdgeInsets.zero,
                                  leading: _UserAvatar(
                                    initial: (user['full_name']?.toString().trim().isNotEmpty == true
                                            ? user['full_name'].toString().trim()[0]
                                            : user['username']?.toString().trim().isNotEmpty == true
                                                ? user['username'].toString().trim()[0]
                                                : '?')
                                        .toUpperCase(),
                                    gradient: _Palette.ocean,
                                    size: 38,
                                  ),
                                  title: Text('${user['full_name'] ?? user['username'] ?? 'User'}', maxLines: 1, overflow: TextOverflow.ellipsis),
                                  subtitle: Text('${user['email'] ?? 'No email'} · ${user['created_at'] ?? 'Join date unavailable'}', maxLines: 1, overflow: TextOverflow.ellipsis),
                                  trailing: _StatusBadge(status: '${user['status'] ?? 'unknown'}'),
                                ),
                            ],
                          ],
                        ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 10, 16, 16),
              child: Row(children: [
                Expanded(
                  child: OutlinedButton(
                    onPressed: _loading || users.isEmpty ? null : _loadReportUsers,
                    child: const Text('Refresh report'),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: FilledButton.icon(
                    onPressed: _loading || _exporting || users.isEmpty ? null : _exportCsv,
                    icon: _exporting
                        ? const SizedBox(width: 17, height: 17, child: CircularProgressIndicator(strokeWidth: 2))
                        : const Icon(Icons.download_rounded),
                    label: Text(_exporting ? 'Preparing…' : 'Export CSV'),
                  ),
                ),
              ]),
            ),
          ],
        ),
      ),
    );
  }
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
  static const LinearGradient mint = LinearGradient(
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
    colors: [Color(0xFF0D9488), Color(0xFF22C55E)],
  );
  static const LinearGradient sunset = LinearGradient(
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
    colors: [amber, orange],
  );
  static const LinearGradient ocean = LinearGradient(
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
    colors: [sky, indigo],
  );
  static const LinearGradient berry = LinearGradient(
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
    colors: [pink, violet],
  );
}

ThemeData _adminTheme(BuildContext context) {
  final base = Theme.of(context);
  return base.copyWith(
    colorScheme: ColorScheme.fromSeed(
      seedColor: _Palette.indigo,
      brightness: base.brightness,
    ),
  );
}

class _BrandMark extends StatelessWidget {
  const _BrandMark({required this.icon, required this.gradient, this.size = 46});
  final IconData icon;
  final LinearGradient gradient;
  final double size;

  @override
  Widget build(BuildContext context) => Container(
        width: size,
        height: size,
        decoration: BoxDecoration(
          gradient: gradient,
          borderRadius: BorderRadius.circular(size * .32),
        ),
        alignment: Alignment.center,
        child: Icon(icon, color: Colors.white, size: size * .5),
      );
}

class _AppBarAction extends StatelessWidget {
  const _AppBarAction({required this.icon, required this.tooltip, required this.onPressed});
  final IconData icon;
  final String tooltip;
  final VoidCallback? onPressed;

  @override
  Widget build(BuildContext context) => IconButton(
        tooltip: tooltip,
        onPressed: onPressed,
        icon: Icon(icon, color: Colors.white),
        style: IconButton.styleFrom(
          backgroundColor: Colors.white.withValues(alpha: .18),
          shape: const CircleBorder(),
          fixedSize: const Size(38, 38),
        ),
      );
}

class _TileRow extends StatelessWidget {
  const _TileRow({required this.children, this.minItemWidth = 145});
  final List<Widget> children;
  final double minItemWidth;

  @override
  Widget build(BuildContext context) => LayoutBuilder(
        builder: (context, constraints) {
          final columns = (constraints.maxWidth / (minItemWidth + 12))
              .floor()
              .clamp(1, children.length)
              .toInt();
          final width = (constraints.maxWidth - (columns - 1) * 12) / columns;
          return Wrap(
            spacing: 12,
            runSpacing: 12,
            children: [
              for (final child in children)
                SizedBox(width: width, child: child),
            ],
          );
        },
      );
}

class _StatTile extends StatelessWidget {
  const _StatTile({
    required this.label,
    required this.value,
    required this.icon,
    required this.gradient,
  });
  final String label;
  final String value;
  final IconData icon;
  final LinearGradient gradient;

  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          gradient: gradient,
          borderRadius: BorderRadius.circular(20),
          boxShadow: [
            BoxShadow(
              color: gradient.colors.last.withValues(alpha: .28),
              blurRadius: 15,
              offset: const Offset(0, 7),
            ),
          ],
        ),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Row(children: [
            Icon(icon, color: Colors.white, size: 18),
            const Spacer(),
            Container(
              width: 6,
              height: 6,
              decoration: BoxDecoration(
                color: Colors.white.withValues(alpha: .7),
                shape: BoxShape.circle,
              ),
            ),
          ]),
          const SizedBox(height: 11),
          FittedBox(
            fit: BoxFit.scaleDown,
            alignment: Alignment.centerLeft,
            child: Text(
              value,
              maxLines: 1,
              style: const TextStyle(
                color: Colors.white,
                fontSize: 23,
                fontWeight: FontWeight.w900,
              ),
            ),
          ),
          const SizedBox(height: 2),
          Text(
            label,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: TextStyle(
              color: Colors.white.withValues(alpha: .9),
              fontSize: 11,
              fontWeight: FontWeight.w700,
            ),
          ),
        ]),
      );
}

class _UserSummary extends StatelessWidget {
  const _UserSummary({
    required this.total,
    required this.onPage,
    required this.banned,
    required this.verified,
  });
  final int total;
  final int onPage;
  final int banned;
  final int verified;

  @override
  Widget build(BuildContext context) => _TileRow(
        minItemWidth: 138,
        children: [
          _StatTile(
            label: 'Matching users',
            value: '$total',
            icon: Icons.people_alt_outlined,
            gradient: _Palette.ocean,
          ),
          _StatTile(
            label: 'On this page',
            value: '$onPage',
            icon: Icons.view_list_rounded,
            gradient: _Palette.mint,
          ),
          _StatTile(
            label: 'Banned on page',
            value: '$banned',
            icon: Icons.block_outlined,
            gradient: _Palette.sunset,
          ),
          _StatTile(
            label: 'Verified on page',
            value: '$verified',
            icon: Icons.mark_email_read_outlined,
            gradient: _Palette.berry,
          ),
        ],
      );
}

class _SearchField extends StatelessWidget {
  const _SearchField({
    required this.controller,
    required this.hint,
    required this.onChanged,
    required this.onClear,
  });
  final TextEditingController controller;
  final String hint;
  final ValueChanged<String> onChanged;
  final VoidCallback onClear;

  @override
  Widget build(BuildContext context) => TextField(
        controller: controller,
        onChanged: onChanged,
        textInputAction: TextInputAction.search,
        decoration: InputDecoration(
          hintText: hint,
          isDense: true,
          prefixIcon: const Icon(Icons.search_rounded, size: 20),
          suffixIcon: controller.text.isEmpty
              ? null
              : IconButton(
                  tooltip: 'Clear search',
                  onPressed: () {
                    controller.clear();
                    onClear();
                  },
                  icon: const Icon(Icons.close_rounded),
                ),
          filled: true,
          fillColor: Theme.of(context).colorScheme.onSurface.withValues(alpha: .045),
          contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
          border: OutlineInputBorder(
            borderRadius: BorderRadius.circular(16),
            borderSide: BorderSide.none,
          ),
          enabledBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(16),
            borderSide: BorderSide.none,
          ),
          focusedBorder: const OutlineInputBorder(
            borderRadius: BorderRadius.all(Radius.circular(16)),
            borderSide: BorderSide(color: _Palette.indigo, width: 1.6),
          ),
        ),
      );
}

class _StatusFilterChip extends StatelessWidget {
  const _StatusFilterChip({
    required this.label,
    required this.color,
    required this.selected,
    required this.onSelected,
  });
  final String label;
  final Color color;
  final bool selected;
  final VoidCallback onSelected;

  @override
  Widget build(BuildContext context) => Material(
        color: selected ? color : color.withValues(alpha: .08),
        borderRadius: BorderRadius.circular(999),
        child: InkWell(
          borderRadius: BorderRadius.circular(999),
          onTap: onSelected,
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 13, vertical: 8),
            child: Text(
              label,
              style: TextStyle(
                color: selected ? Colors.white : color,
                fontSize: 12,
                fontWeight: FontWeight.w800,
              ),
            ),
          ),
        ),
      );
}

class _UserAvatar extends StatelessWidget {
  const _UserAvatar({
    required this.initial,
    required this.gradient,
    this.size = 46,
  });
  final String initial;
  final LinearGradient gradient;
  final double size;

  @override
  Widget build(BuildContext context) => Container(
        width: size,
        height: size,
        decoration: BoxDecoration(
          gradient: gradient,
          shape: BoxShape.circle,
          boxShadow: [
            BoxShadow(
              color: gradient.colors.last.withValues(alpha: .24),
              blurRadius: 10,
              offset: const Offset(0, 4),
            ),
          ],
        ),
        alignment: Alignment.center,
        child: Text(
          initial,
          style: TextStyle(
            color: Colors.white,
            fontSize: size * .38,
            fontWeight: FontWeight.w900,
          ),
        ),
      );
}

class _StatusBadge extends StatelessWidget {
  const _StatusBadge({required this.status});
  final String status;

  @override
  Widget build(BuildContext context) {
    final lower = status.toLowerCase();
    final color = lower == 'active'
        ? _Palette.green
        : lower == 'suspended' || lower == 'banned'
            ? _Palette.rose
            : lower == 'pending' || lower == 'restricted'
                ? _Palette.amber
                : _Palette.muted;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 5),
      decoration: BoxDecoration(
        color: color.withValues(alpha: .12),
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: color.withValues(alpha: .22)),
      ),
      child: Text(
        status.toUpperCase(),
        style: TextStyle(color: color, fontSize: 9, fontWeight: FontWeight.w900),
      ),
    );
  }
}

class _ErrorBanner extends StatelessWidget {
  const _ErrorBanner({required this.message, this.onRetry});
  final String message;
  final VoidCallback? onRetry;

  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: _Palette.rose.withValues(alpha: .1),
          borderRadius: BorderRadius.circular(14),
          border: Border.all(color: _Palette.rose.withValues(alpha: .3)),
        ),
        child: Row(children: [
          const Icon(Icons.error_outline_rounded, size: 18, color: _Palette.rose),
          const SizedBox(width: 9),
          Expanded(
            child: Text(
              message,
              style: const TextStyle(color: _Palette.rose, fontWeight: FontWeight.w600),
            ),
          ),
          if (onRetry != null)
            IconButton(
              visualDensity: VisualDensity.compact,
              tooltip: 'Retry',
              onPressed: onRetry,
              icon: const Icon(Icons.refresh_rounded, color: _Palette.rose),
            ),
        ]),
      );
}

class _EmptyState extends StatelessWidget {
  const _EmptyState({
    required this.icon,
    required this.title,
    required this.message,
    required this.actionLabel,
    required this.onAction,
  });
  final IconData icon;
  final String title;
  final String message;
  final String actionLabel;
  final VoidCallback onAction;

  @override
  Widget build(BuildContext context) => Center(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(28),
          child: Column(mainAxisSize: MainAxisSize.min, children: [
            _BrandMark(icon: icon, gradient: _Palette.berry, size: 62),
            const SizedBox(height: 16),
            Text(
              title,
              textAlign: TextAlign.center,
              style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w800),
            ),
            const SizedBox(height: 6),
            ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 380),
              child: Text(
                message,
                textAlign: TextAlign.center,
                style: TextStyle(color: Theme.of(context).colorScheme.onSurfaceVariant, height: 1.4),
              ),
            ),
            const SizedBox(height: 18),
            FilledButton.tonalIcon(
              onPressed: onAction,
              icon: const Icon(Icons.filter_alt_off_rounded),
              label: Text(actionLabel),
            ),
          ]),
        ),
      );
}

class _SkeletonList extends StatelessWidget {
  const _SkeletonList();

  @override
  Widget build(BuildContext context) => ListView.builder(
        physics: const NeverScrollableScrollPhysics(),
        padding: const EdgeInsets.all(16),
        itemCount: 4,
        itemBuilder: (_, index) => Container(
          height: 126,
          margin: const EdgeInsets.only(bottom: 12),
          decoration: BoxDecoration(
            color: _Palette.indigo.withValues(alpha: .06),
            borderRadius: BorderRadius.circular(20),
          ),
        ),
      );
}

class _SignInField extends StatelessWidget {
  const _SignInField({
    required this.controller,
    required this.label,
    required this.icon,
    this.keyboard,
    this.obscure = false,
    this.onSubmitted,
    this.suffix,
  });
  final TextEditingController controller;
  final String label;
  final IconData icon;
  final TextInputType? keyboard;
  final bool obscure;
  final ValueChanged<String>? onSubmitted;
  final Widget? suffix;

  @override
  Widget build(BuildContext context) => TextField(
        controller: controller,
        keyboardType: keyboard,
        obscureText: obscure,
        onSubmitted: onSubmitted,
        decoration: InputDecoration(
          labelText: label,
          prefixIcon: Icon(icon, size: 20),
          suffixIcon: suffix,
          filled: true,
          fillColor: Theme.of(context).colorScheme.onSurface.withValues(alpha: .04),
          border: OutlineInputBorder(borderRadius: BorderRadius.circular(14), borderSide: BorderSide.none),
          enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(14), borderSide: BorderSide.none),
          focusedBorder: const OutlineInputBorder(
            borderRadius: BorderRadius.all(Radius.circular(14)),
            borderSide: BorderSide(color: _Palette.indigo, width: 1.6),
          ),
        ),
      );
}

class _GradientButton extends StatelessWidget {
  const _GradientButton({required this.label, required this.onPressed, this.icon, this.busy = false});
  final String label;
  final VoidCallback? onPressed;
  final IconData? icon;
  final bool busy;

  @override
  Widget build(BuildContext context) {
    final enabled = onPressed != null;
    final Widget? leading = busy
        ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
        : icon == null ? null : Icon(icon, color: Colors.white, size: 20);
    return Opacity(
      opacity: enabled ? 1 : .55,
      child: DecoratedBox(
        decoration: BoxDecoration(
          gradient: _Palette.hero,
          borderRadius: BorderRadius.circular(16),
          boxShadow: enabled
              ? [BoxShadow(color: _Palette.indigo.withValues(alpha: .3), blurRadius: 16, offset: const Offset(0, 8))]
              : null,
        ),
        child: Material(
          color: Colors.transparent,
          child: InkWell(
            onTap: onPressed,
            borderRadius: BorderRadius.circular(16),
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 15),
              child: Row(mainAxisAlignment: MainAxisAlignment.center, mainAxisSize: MainAxisSize.min, children: [
                if (leading != null) ...[leading, const SizedBox(width: 10)],
                Flexible(child: Text(label, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 15))),
              ]),
            ),
          ),
        ),
      ),
    );
  }
}

class _SectionCard extends StatelessWidget {
  const _SectionCard({required this.title, required this.child, this.subtitle});
  final String title;
  final String? subtitle;
  final Widget child;

  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.all(15),
        decoration: BoxDecoration(
          color: Theme.of(context).colorScheme.surfaceContainerLow,
          borderRadius: BorderRadius.circular(18),
          border: Border.all(color: Theme.of(context).colorScheme.outlineVariant),
        ),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(title, style: Theme.of(context).textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w900)),
          if (subtitle != null) ...[
            const SizedBox(height: 4),
            Text(subtitle!, style: Theme.of(context).textTheme.bodySmall?.copyWith(color: _Palette.muted, height: 1.35)),
          ],
          const SizedBox(height: 11),
          child,
        ]),
      );
}

class _PrivacyNote extends StatelessWidget {
  const _PrivacyNote();

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
              'This report contains personal account data. Share and store it securely.',
              style: TextStyle(color: _Palette.muted, fontSize: 11, height: 1.35),
            ),
          ),
        ]),
      );
}

class _ReportMetric extends StatelessWidget {
  const _ReportMetric({required this.label, required this.value, required this.icon, required this.color});
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
          border: Border.all(color: color.withValues(alpha: .17)),
        ),
        child: Row(children: [
          Icon(icon, color: color, size: 20),
          const SizedBox(width: 10),
          Expanded(
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(value, style: TextStyle(color: color, fontWeight: FontWeight.w900, fontSize: 18)),
              Text(label, maxLines: 1, overflow: TextOverflow.ellipsis, style: Theme.of(context).textTheme.labelSmall?.copyWith(fontWeight: FontWeight.w700)),
            ]),
          ),
        ]),
      );
}

class _ReportStatusCount extends StatelessWidget {
  const _ReportStatusCount({required this.label, required this.count});
  final String label;
  final int count;

  @override
  Widget build(BuildContext context) {
    final status = _StatusBadge(status: label);
    return Container(
      padding: const EdgeInsets.fromLTRB(9, 6, 10, 6),
      decoration: BoxDecoration(
        color: Theme.of(context).colorScheme.surface,
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: Theme.of(context).colorScheme.outlineVariant),
      ),
      child: Row(mainAxisSize: MainAxisSize.min, children: [
        status,
        const SizedBox(width: 7),
        Text('$count', style: const TextStyle(fontWeight: FontWeight.w900)),
      ]),
    );
  }
}
