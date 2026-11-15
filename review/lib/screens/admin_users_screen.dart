import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

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
  final _email = TextEditingController();
  final _password = TextEditingController();
  final _search = TextEditingController();
  String? _token;
  String? _error;
  bool _loading = false;
  bool _includeDeleted = false;
  String? _statusFilter;
  int _page = 1;
  int _lastPage = 1;
  int _total = 0;
  Timer? _debounce;
  List<Map<String, dynamic>> _users = const [];

  @override
  void initState() {
    super.initState();
    _email.text = 'admin@tlstore.test';
    _password.text = 'meandmyson';
  }

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
                ? (pageInfo['last_page'] as num).toInt().clamp(1, 100000)
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

  void _searchChanged(String _) {
    _debounce?.cancel();
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
    final theme = Theme.of(context);
    final colors = theme.colorScheme;

    return Scaffold(
      backgroundColor: colors.surface,
      appBar: AppBar(
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text('User management'),
            if (_token != null)
              Text(
                'Admin console',
                style: theme.textTheme.labelSmall?.copyWith(
                  color: colors.onSurfaceVariant,
                ),
              ),
          ],
        ),
        actions: [
          if (_token != null)
            IconButton(
              tooltip: 'Refresh users',
              onPressed: _loading ? null : _load,
              icon: const Icon(Icons.refresh_rounded),
            ),
          const SizedBox(width: 6),
        ],
      ),
      body: _token == null ? _loginForm() : _usersPage(),
    );
  }

  Widget _usersPage() {
    final theme = Theme.of(context);
    final colors = theme.colorScheme;
    const filters = <(String?, String)>[
      (null, 'All'),
      ('active', 'Active'),
      ('restricted', 'Restricted'),
      ('suspended', 'Suspended'),
      ('banned', 'Banned'),
      ('pending', 'Pending'),
    ];

    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 12, 16, 8),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              TextField(
                controller: _search,
                onChanged: _searchChanged,
                textInputAction: TextInputAction.search,
                decoration: InputDecoration(
                  hintText: 'Search name, username or email',
                  prefixIcon: const Icon(Icons.search_rounded),
                  suffixIcon: _search.text.isEmpty
                      ? null
                      : IconButton(
                          tooltip: 'Clear search',
                          onPressed: () {
                            _debounce?.cancel();
                            _search.clear();
                            _load(page: 1);
                            setState(() {});
                          },
                          icon: const Icon(Icons.close_rounded),
                        ),
                  filled: true,
                  fillColor: colors.surfaceContainerLow,
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(16),
                    borderSide: BorderSide(color: colors.outlineVariant),
                  ),
                  enabledBorder: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(16),
                    borderSide: BorderSide(color: colors.outlineVariant),
                  ),
                ),
              ),
              const SizedBox(height: 12),
              Container(
                padding: const EdgeInsets.symmetric(
                  horizontal: 14,
                  vertical: 10,
                ),
                decoration: BoxDecoration(
                  color: colors.surfaceContainerLow,
                  borderRadius: BorderRadius.circular(15),
                  border: Border.all(color: colors.outlineVariant),
                ),
                child: Row(
                  children: [
                    Icon(
                      Icons.people_alt_outlined,
                      color: colors.primary,
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            '$_total users · ${_users.length} on this page',
                            style: theme.textTheme.titleSmall?.copyWith(
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                          Text(
                            'Manage accounts, wallets and access',
                            style: theme.textTheme.bodySmall?.copyWith(
                              color: colors.onSurfaceVariant,
                            ),
                          ),
                        ],
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
              if (_includeDeleted)
                Padding(
                  padding: const EdgeInsets.only(top: 6, left: 4),
                  child: Text(
                    'Including soft-deleted accounts',
                    style: theme.textTheme.labelSmall?.copyWith(
                      color: colors.tertiary,
                    ),
                  ),
                ),
              const SizedBox(height: 8),
              SingleChildScrollView(
                scrollDirection: Axis.horizontal,
                child: Row(children: [
                  for (var index = 0; index < filters.length; index++) ...[
                    if (index > 0) const SizedBox(width: 7),
                    ChoiceChip(
                      label: Text(filters[index].$2),
                      selected: _statusFilter == filters[index].$1,
                      onSelected: (_) {
                        setState(() => _statusFilter = filters[index].$1);
                        _load(page: 1);
                      },
                    ),
                  ],
                ]),
              ),
            ],
          ),
        ),
        if (_error != null)
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 0, 16, 8),
            child: Material(
              color: colors.errorContainer,
              borderRadius: BorderRadius.circular(12),
              child: Padding(
                padding: const EdgeInsets.all(12),
                child: Row(
                  children: [
                    Icon(Icons.error_outline, color: colors.onErrorContainer),
                    const SizedBox(width: 10),
                    Expanded(
                      child: Text(
                        _error!,
                        style: theme.textTheme.bodySmall?.copyWith(
                          color: colors.onErrorContainer,
                        ),
                      ),
                    ),
                    IconButton(
                      visualDensity: VisualDensity.compact,
                      onPressed: _load,
                      icon: Icon(
                        Icons.refresh_rounded,
                        color: colors.onErrorContainer,
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
        Expanded(
          child: _loading
              ? const Center(child: CircularProgressIndicator())
              : _users.isEmpty
                  ? _emptyUsers()
                  : RefreshIndicator(
                      onRefresh: _load,
                      child: ListView.separated(
                        physics: const AlwaysScrollableScrollPhysics(),
                        padding: const EdgeInsets.fromLTRB(16, 8, 16, 28),
                        itemCount: _users.length,
                        separatorBuilder: (_, _) =>
                            const SizedBox(height: 10),
                        itemBuilder: (context, index) =>
                            _userCard(_users[index]),
                      ),
                    ),
        ),
        if (_total > 0)
          SafeArea(
            top: false,
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 4),
              child: Row(children: [
                Text('Page $_page of $_lastPage', style: Theme.of(context).textTheme.labelMedium),
                const Spacer(),
                IconButton(
                  tooltip: 'Previous page',
                  onPressed: _page > 1 && !_loading ? () => _load(page: _page - 1) : null,
                  icon: const Icon(Icons.chevron_left_rounded),
                ),
                IconButton(
                  tooltip: 'Next page',
                  onPressed: _page < _lastPage && !_loading ? () => _load(page: _page + 1) : null,
                  icon: const Icon(Icons.chevron_right_rounded),
                ),
              ]),
            ),
          ),
      ],
    );
  }

  Widget _emptyUsers() {
    final theme = Theme.of(context);
    final colors = theme.colorScheme;

    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.all(32),
      children: [
        const SizedBox(height: 70),
        Icon(
          Icons.person_search_rounded,
          size: 54,
          color: colors.onSurfaceVariant,
        ),
        const SizedBox(height: 16),
        Text(
          'No users found',
          textAlign: TextAlign.center,
          style: theme.textTheme.titleMedium?.copyWith(
            fontWeight: FontWeight.w800,
          ),
        ),
        const SizedBox(height: 6),
        Text(
          'Try another name, username or email address.',
          textAlign: TextAlign.center,
          style: theme.textTheme.bodySmall?.copyWith(
            color: colors.onSurfaceVariant,
          ),
        ),
      ],
    );
  }

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
    final banned = user['is_banned'] == true;
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
        leading: CircleAvatar(
          radius: 23,
          backgroundColor: colors.primaryContainer,
          foregroundColor: colors.onPrimaryContainer,
          child: Text(
            initial,
            style: theme.textTheme.titleMedium?.copyWith(
              fontWeight: FontWeight.w800,
            ),
          ),
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
        borderRadius: BorderRadius.circular(7),
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

  Widget _loginForm() {
    final theme = Theme.of(context);
    final colors = theme.colorScheme;

    return LayoutBuilder(
      builder: (context, constraints) {
        return SingleChildScrollView(
          padding: const EdgeInsets.all(22),
          child: ConstrainedBox(
            constraints: BoxConstraints(
              minHeight: constraints.maxHeight - 44,
            ),
            child: Center(
              child: ConstrainedBox(
                constraints: const BoxConstraints(maxWidth: 440),
                child: Card(
                  elevation: 0,
                  color: colors.surfaceContainerLow,
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(24),
                    side: BorderSide(color: colors.outlineVariant),
                  ),
                  child: Padding(
                    padding: const EdgeInsets.all(24),
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        Center(
                          child: Container(
                            height: 68,
                            width: 68,
                            decoration: BoxDecoration(
                              color: colors.primaryContainer,
                              borderRadius: BorderRadius.circular(20),
                            ),
                            child: Icon(
                              Icons.admin_panel_settings_outlined,
                              size: 34,
                              color: colors.onPrimaryContainer,
                            ),
                          ),
                        ),
                        const SizedBox(height: 18),
                        Text(
                          'Admin access',
                          textAlign: TextAlign.center,
                          style: theme.textTheme.headlineSmall?.copyWith(
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                        const SizedBox(height: 7),
                        Text(
                          'Sign in to manage users, account access and wallet balances.',
                          textAlign: TextAlign.center,
                          style: theme.textTheme.bodySmall?.copyWith(
                            color: colors.onSurfaceVariant,
                            height: 1.5,
                          ),
                        ),
                        const SizedBox(height: 24),
                        TextField(
                          controller: _email,
                          keyboardType: TextInputType.emailAddress,
                          textInputAction: TextInputAction.next,
                          decoration: const InputDecoration(
                            labelText: 'Admin email',
                            prefixIcon: Icon(Icons.email_outlined),
                          ),
                        ),
                        const SizedBox(height: 14),
                        TextField(
                          controller: _password,
                          obscureText: true,
                          onSubmitted: (_) {
                            if (!_loading) _login();
                          },
                          decoration: const InputDecoration(
                            labelText: 'Password',
                            prefixIcon: Icon(Icons.lock_outline_rounded),
                          ),
                        ),
                        if (_error != null) ...[
                          const SizedBox(height: 12),
                          Text(
                            _error!,
                            style: theme.textTheme.bodySmall?.copyWith(
                              color: colors.error,
                            ),
                          ),
                        ],
                        const SizedBox(height: 20),
                        SizedBox(
                          height: 50,
                          child: FilledButton(
                            onPressed: _loading ? null : _login,
                            child: _loading
                                ? const SizedBox(
                                    height: 20,
                                    width: 20,
                                    child: CircularProgressIndicator(
                                      strokeWidth: 2,
                                    ),
                                  )
                                : const Text('Sign in as admin'),
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            ),
          ),
        );
      },
    );
  }
}
